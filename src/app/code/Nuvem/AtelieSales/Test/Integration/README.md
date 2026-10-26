# F2.3 stock reservation validation

The quote stores the current attempt ID and state. All ownership transitions use the quote row lock. The ledger determines the outstanding quantity; its balance alone never authorizes recovery.

| State | New submission |
| --- | --- |
| `active` | Rejected; the owner may still be placing an order. |
| `compensation_pending` | Recover under the quote lock before acquiring a new attempt. |
| `compensated` | Allowed if there is no order or outstanding balance. |
| `completed` | A persisted order prevents another submission. |

`QuoteStockReservation` owns all state transitions. The local invocation token belongs to the exact quote object; it never determines database ownership. Before reserving, the observer copies that token to the order object. Failure events use the original order token, including when the same quote object has since been reused by another attempt.

`beginSubmission` opens a database transaction before acquiring the attempt and locks the quote through reservation and order persistence. The complete component demand, guarded stock updates, ledger rows and ownership share that transaction. For a new root transaction, `SET TRANSACTION ISOLATION LEVEL READ COMMITTED` applies only to that transaction: empty order and ledger ranges must not retain gap locks while waiting to insert the order. Quote and component row locks still serialize competing writes.

`sales_order_save_after` validates the original token and writes `completed`. `CommitStockReservation::aroundPlace` commits the shared transaction only after the order resource finishes saving, before `sales_model_service_quote_submit_success`. An exception after that commit preserves consumption. If core returns without reaching this boundary despite saving an order, the submit wrapper rejects and rolls back the unfinished unit. Ordinary quotes bypass this stock transaction. A transaction supplied by the caller keeps its final commit or rollback; the module closes only its own nested level.

| Failure | Stock and ledger outcome |
| --- | --- |
| One component cannot be reserved | Roll back all component updates, ledger rows and new ownership. |
| A later `submit_before` observer throws before `place` starts | In the intact transaction, mark pending, compensate the matching token and commit a balanced reservation/compensation pair per component. |
| Order placement or resource save fails | Roll back stock, ledger, ownership, order and its production outbox together. Never add stock after this rollback. |
| A callback or submit observer fails after the order commit | Keep the order and consumed stock; no compensation. |
| Recovery of an earlier committed pending attempt fails | Keep its persisted pending state and debt; reject a new owner. |

Magento's nested rollback invalidates an enclosing transaction. The coordinator unwinds its owned levels before any new recovery transaction. A full SQL rollback removes uncommitted reservation rows, so it must not produce a standalone positive compensation row. If compensation itself fails for a new uncommitted reservation, root rollback restores the original balance and ownership instead of leaving stock debt.

Compensation accepts only the current token in `compensation_pending`, under the quote lock. A new attempt is acquired only after any earlier committed debt has been balanced. Quote resource saves exclude ownership fields so stale model data cannot overwrite transactional updates. No schema or stock service interface changed. Standalone quote orchestration retains bounded deadlock retries; the checkout/payment operation and caller-owned transactions are never automatically retried.

Availability callbacks coalesce the initial and final flags per component. Magento's callback pool retains the snapshot until commit, or discards it on rollback. A balanced reservation/compensation transaction does not invalidate unchanged availability, and a later transaction still invalidates a real availability change.

## Running scenarios

Run stock scenarios only on an isolated Magento database named `f23_validation_*`, with separate cache prefixes. Set `F23_TEST_DATABASE` to the prepared `f23_validation_*` database to isolate the database, file cache, var and generated code through `IsolatedEnvironment.php`; child workers inherit it. The existing `MAGENTO_DC__OVERRIDE` selection remains supported. Keep the main database and deployment files unchanged. Prepare the existing schema, then clean `config` and `compiled_config` so the new order placement plugin is loaded. This correction has no schema migration.

From the Magento root:

```bash
php vendor/bin/phpunit --no-extensions -c phpunit.xml.dist
php app/code/Nuvem/AtelieSales/Test/Integration/SchemaScenario.php
php app/code/Nuvem/AtelieSales/Test/Integration/StockScenarios.php reset-fixture < /path/to/kit/exemplos/configuracao-lua.json
php app/code/Nuvem/AtelieSales/Test/Integration/StockScenarios.php active-owner < /path/to/kit/exemplos/configuracao-lua.json
```

Other scenarios using the same JSON input: `stale-failure`, `stale-order`, `legacy-balance`, `partial-reservation`, `reentry`, `recovery`, `compensation-rollback`, `order-snapshot`, `concurrent-compensation`, `duplicate`, `duplicate-reservation`, `terminated-owner`, `outer-commit`, `outer-rollback`, and `race`. Reset the fixture before `race`. `transactions` needs no JSON. For failure observers, use `prepare` with the JSON, then `failure QUOTE_ID EVENT`; supported events are `sales_model_service_quote_submit_before`, `sales_order_place_before`, `sales_order_save_after`, `sales_model_service_quote_submit_success`, and `sales_order_save_commit_after`. Observers are registered only in the test process.

`active-owner` holds the first worker after reservation and starts a competitor on the same quote. A separate connection verifies that ownership, stock and ledger have not committed, and the competitor waits for the quote lock. Releasing the owner produces one order and one duplicate refusal. `terminated-owner` kills the held worker, verifies that MySQL rolled back the entire unit, then successfully reuses the same quote. `outer-commit` and `outer-rollback` prove the caller retains transaction ownership. Reentry/recovery scenarios also save a stale quote and verify that it cannot overwrite completed ownership.

For upgrade preservation, record `SchemaScenario.php` before and after another `setup:upgrade`. Its ledger digest must remain unchanged. Validate clean declarative installation separately, starting with the Magento installer bootstrap (`setup_module`) in a new database; this does not validate a complete store installation with data patches.

## Evidence — 2026-10-23

- Full Nuvem unit suite: 291 tests and 733 assertions passed (`phpunit -c phpunit.xml.dist`).
- The CI PHPCS command over `app/code/Nuvem` reported zero errors and warnings.
- Repeated observer scenario: one quote left exactly seven reservation ledger rows and reduced each selected component by one.
- Partial reservation failure: the final component was unavailable after the other six updates; all six updates and their ledger rows rolled back.
- A delayed failure event carrying the old order token did not affect a new open submission on the same quote object. A stale order token was rejected during the real order save, rolling back stock, order, ledger and new ownership together.
- Forced exception at `sales_model_service_quote_submit_before`: no order persisted, all seven components returned to stock 10, and each quote ledger balance returned to zero with one compensation row.
- Forced exception at `sales_order_place_before`: all seven stock updates, reservation rows and new ownership rolled back together.
- Forced exception at `sales_order_save_after`: `completed` was visible inside the order transaction, then the order, production outbox, stock, ledger and state transition rolled back together.
- Forced exception at `sales_model_service_quote_submit_success`: the order remained persisted, each component stayed at stock 9, and the quote kept one reservation row per component without compensation.
- Forced exception at `sales_order_save_commit_after`: the callback saw completed ownership, and its failure preserved the committed order, production outbox and consumed stock.
- Eight concurrent compensation calls produced two ledger rows per component (reservation and one compensation), restored all seven stocks to 10, and reconciled each ledger delta to zero.
- Same-quote lock contention, terminated-owner recovery, caller commit/rollback, reentry, repeated reservation, failed compensation, earlier pending recovery, a stale repeatable-read order snapshot and duplicate submit scenarios passed.
- The 40-process CLI race produced exactly 10 orders and 30 clean stock refusals, with each of the seven stock deltas equal to its ledger total.
- Official Stage 5 at revision `39eaf42`, containing the production fix and integration scenarios: 40 HTTP workers produced 10 orders, 30 clean refusals, zero 5xx and Sola Lua unavailable, with 3 checks passed and 0 failed. A baseline recorded before the run reconciled all seven stocks from 10 to 0 against exactly ten reservation rows and net -10 each. The audit records the exact revision SHA; repeat this command at the final PR HEAD after documentation commits:

  ```bash
  BASE_URL=http://isolated-magento.test SO=corrida CORRIDA=40 ESTOQUE_LUA=10 NAO_INTERATIVO=1 bash kit/scripts/prova-final.sh
  ```

- Nested rollback and SQL ledger failure preserved stock and cleared commit callbacks. A balanced transaction preserved FPC; a later committed availability change invalidated it.
- The public route `/V1/atelie/disponibilidade/NUVEM-RUNNER` returned the documented JSON fields and no stock quantities.

## Operational reconciliation

Drain existing checkout submissions during rollout and refresh the DI caches so stock submission and order placement use the same version. Legacy negative balances without an attempt remain blocked. A new checkout holds quote/component locks through order placement, including its payment operation; avoid retrying that operation automatically.

New submissions do not commit `active` ownership or stock independently of the order. A terminated worker therefore leaves no committed reservation from its unfinished unit. Earlier deployments may still have committed active attempts: before reconciling one, stop its submissions and establish that its owner can no longer place an order. In a transaction, lock the quote and use a current read of `sales_order` by quote ID. A persisted order keeps its consumed stock. Only when there is no order may an operator explicitly mark the attempt `compensation_pending`; legacy balances additionally need an assigned attempt ID. The next submission then uses the existing transactional recovery. Never release stock solely because a reservation is old.
