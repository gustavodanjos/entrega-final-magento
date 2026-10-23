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

Other scenarios using the same JSON input: `stale-failure`, `legacy-balance`, `partial-reservation`, `reentry`, `recovery`, `order-snapshot`, `concurrent-compensation`, `duplicate`, `duplicate-reservation`, and `race`. Reset the fixture before `race`. `transactions` needs no JSON. For failure observers, use `prepare` with the JSON, then `failure QUOTE_ID EVENT`; supported events are `sales_model_service_quote_submit_before`, `sales_order_place_before`, and `sales_model_service_quote_submit_success`. Observers are registered only in the test process.

`active-owner` starts a competing PHP process after the first process has reserved all components and before it places the order. It checks rejection and an unchanged ledger before letting the owner continue. It also verifies that saving a stale quote cannot overwrite completed ownership.

For upgrade preservation, record `SchemaScenario.php` before and after another `setup:upgrade`. Its ledger digest must remain unchanged. Validate clean declarative installation separately, starting with the Magento installer bootstrap (`setup_module`) in a new database; this does not validate a complete store installation with data patches.

## Evidence — 2026-10-23

- Unit suite: 95 tests and 208 assertions passed.
- PHPCS on changed production files: zero errors and warnings.
- `setup:upgrade` completed against the isolated validation database with the availability route enabled.
- Repeated observer scenario: one quote left exactly seven reservation ledger rows and reduced each selected component by one.
- Forced exception at `sales_model_service_quote_submit_before`: no order persisted, all seven components returned to stock 10, and each quote ledger balance returned to zero with one compensation row.
- Forced exception at `sales_model_service_quote_submit_success`: the order remained persisted, each component stayed at stock 9, and the quote kept one reservation row per component without compensation.
- Eight concurrent compensation calls produced two ledger rows per component (reservation and one compensation), restored all seven stocks to 10, and reconciled each ledger delta to zero.
- CLI race with 40 checkout workers: exactly 10 orders and 30 clean stock refusals. All seven component stocks changed from 10 to 0, and each component's total ledger delta was exactly -10.
- Official `prova-final.sh` at Stage 5 (`SO=corrida`, `CORRIDA=40`): 10 orders, 30 clean refusals, zero 5xx, and the public endpoint reported Sola Lua unavailable. The run completed with 3 checks passed and 0 failed.
- The public route `/V1/atelie/disponibilidade/NUVEM-RUNNER` returned the documented JSON fields and no stock quantities.

## Operational reconciliation

Deploy the schema before enabling the new code and drain existing checkout submissions during rollout. Legacy negative balances without an attempt remain blocked.

There is no timeout-based recovery. A terminated PHP process can leave an `active` attempt, and failure to persist the failure state also keeps it active. Before reconciling such a quote, stop its submissions and establish that its owner can no longer place an order. In a transaction, lock the quote and use a current read of `sales_order` by quote ID. A persisted order keeps its consumed stock. Only when there is no order may an operator explicitly mark the attempt `compensation_pending`; legacy balances additionally need an assigned attempt ID. The next submission then uses the existing transactional recovery. Never release stock solely because a reservation is old.
