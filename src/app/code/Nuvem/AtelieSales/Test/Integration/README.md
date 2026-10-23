# F2.3 stock reservation validation

The quote stores the current attempt ID and state. All ownership transitions use the quote row lock. The ledger determines the outstanding quantity; its balance alone never authorizes recovery.

| State | New submission |
| --- | --- |
| `active` | Rejected; the owner may still be placing an order. |
| `compensation_pending` | Recover under the quote lock before acquiring a new attempt. |
| `compensated` | Allowed if there is no order or outstanding balance. |
| `completed` | A persisted order prevents another submission. |

A stale failure must present its original attempt ID and cannot affect a newer attempt. Quote resource saves exclude ownership fields so stale model data cannot overwrite direct transactional updates. No stock service interface changed. Root transaction deadlocks have at most ten attempts, with randomized exponential waits capped at 250 ms; caller-owned transactions are never retried.

## Running scenarios

Run stock scenarios only on an isolated Magento database named `f23_validation_*`, with separate cache prefixes. Use `MAGENTO_DC__OVERRIDE` to select that database; child workers inherit it. Keep the main database and deployment files unchanged. Generate the module whitelist and run `setup:upgrade` against the isolated database first.

From the Magento root:

```bash
php vendor/bin/phpunit --no-extensions -c dev/tests/unit/phpunit.xml.dist app/code/Nuvem/AtelieSales/Test/Unit
php app/code/Nuvem/AtelieSales/Test/Integration/SchemaScenario.php
php app/code/Nuvem/AtelieSales/Test/Integration/StockScenarios.php reset-fixture < /path/to/kit/exemplos/configuracao-lua.json
php app/code/Nuvem/AtelieSales/Test/Integration/StockScenarios.php active-owner < /path/to/kit/exemplos/configuracao-lua.json
```

Other scenarios using the same JSON input: `stale-failure`, `legacy-balance`, `partial-reservation`, `reentry`, `recovery`, `order-snapshot`, `concurrent-compensation`, `duplicate`, and `race`. Reset the fixture before `race`. `transactions` needs no JSON. For failure observers, use `prepare` with the JSON, then `failure QUOTE_ID EVENT`; supported events are `sales_model_service_quote_submit_before`, `sales_order_place_before`, and `sales_model_service_quote_submit_success`. Observers are registered only in the test process.

`active-owner` starts a competing PHP process after the first process has reserved all components and before it places the order. It checks rejection and an unchanged ledger before letting the owner continue. It also verifies that saving a stale quote cannot overwrite completed ownership.

For upgrade preservation, record `SchemaScenario.php` before and after another `setup:upgrade`. Its ledger digest must remain unchanged. Validate clean declarative installation separately, starting with the Magento installer bootstrap (`setup_module`) in a new database; this does not validate a complete store installation with data patches.

## Evidence — 2026-10-23

- Unit suite: 89 tests, 192 assertions, passing.
- PHPCS on changed production files: zero errors and warnings. The full module has 38 existing warnings in other files and zero errors.
- All scenarios listed above passed, including forced failures at all three supported events.
- Clean declarative schema creation and repeated upgrade passed. The repeated upgrade preserved all 1,257 ledger rows and their SHA-256 digest. `setup:db:status` reported all modules up to date.
- Three consecutive CLI races with 40 workers passed: each produced exactly 10 orders, 30 stock refusals, stock zero and ledger net -10.
- Official `prova-final.sh`, `SO=corrida`, using a temporary HTTP server with 40 PHP workers and an isolated database: 10 orders, 30 clean refusals, zero server errors. Sola Lua stock changed from 10 to 0, with 10 ledger rows totaling -10 and 10 corresponding persisted orders.
- The official script returned failure on its final public availability assertion: `/V1/atelie/disponibilidade/NUVEM-RUNNER` returns 404 because that REST route is absent from this checkout. Stock exhaustion was verified directly in MySQL. The full acceptance script is therefore **not entirely passing**.

## Operational reconciliation

Deploy the schema before enabling the new code and drain existing checkout submissions during rollout. Legacy negative balances without an attempt remain blocked.

There is no timeout-based recovery. A terminated PHP process can leave an `active` attempt, and failure to persist the failure state also keeps it active. Before reconciling such a quote, stop its submissions and establish that its owner can no longer place an order. In a transaction, lock the quote and use a current read of `sales_order` by quote ID. A persisted order keeps its consumed stock. Only when there is no order may an operator explicitly mark the attempt `compensation_pending`; legacy balances additionally need an assigned attempt ID. The next submission then uses the existing transactional recovery. Never release stock solely because a reservation is old.
