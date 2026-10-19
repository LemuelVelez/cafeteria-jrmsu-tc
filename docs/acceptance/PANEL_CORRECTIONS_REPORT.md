# JRMSU-TC Cafeteria — panel corrections acceptance state

**As of 2026-10-19. This is NOT a database-verified acceptance report.**

The code-only patch preserves the repository's existing folder structure and only bundles changed/new files. No new `.env` keys were introduced and migrations 000001–000033 were not edited. This runtime has no Composer executable, MySQL CLI, or PHP `mysqli` extension; database acceptance tests and migration rollbacks could not run. Printer/scanner/push hardware tests could not run. **Do not claim 100% compliance.**

| # | Correction | Status | Evidence / next acceptance task |
|---|---|---|---|
| 1 | Security / customer information | Implemented–untested | Real-DB password/TOTP, encryption, role filters and session tests needed |
| 2 | Barcode scanning | Implemented–untested | `npm run check` PASS; MySQL barcode lookup and both save endpoints still need tests; scanner hardware outstanding |
| 3 | QR codes | Implemented–untested | HMAC and expiry database tests and phone-scan check outstanding |
| 4 | Nutrition display / allergens | Implemented–untested | Structured allergens and Nutrition Facts views changed; migration up/down HTTP tests required |
| 5 | Reliable nutrition | **Partial** | Strict source CSV loader, idempotent ingredient/recipe seeders, ledger opening, yield/piece conversions, USDA missing-field rejection and unsourced demo cleanup; **source CSVs deliberately contain headers only** pending genuine PhilFCT/USDA references and team-reviewed recipes |
| 6 | Inventory stock card | Implemented–untested | StockCardService supports ingredient/product ledgers, filters and paging; real 60-row database fixture needed |
| 7 | Minimum-stock alerts | Implemented–untested | Threshold and digest database/email tests outstanding |
| 8 | Food costing / profits | Implemented–untested | Missing-recipe statuses, historic snapshots and discount allocation in code; MySQL reconciliation needed |
| 9 | Order status flow | Implemented–untested | Complete role/status matrix with payment assertions required |
| 10 | Customer order history | Implemented–untested | Item summaries, payment badges and progress stepper in code; HTTP/query-count tests needed |
| 11 | Notifications | Implemented–untested | Push/email mocked behavior and real device check required |
| 12 | Printable reports | Implemented–untested | Explicit orders CSV whitelist and formula neutralization; CSV/accounting DB tests and Chrome PDF page-number verification needed |
| 13 | Stock deduction | Implemented–untested | Transactional product and ingredient writer calls, fail-fast on missing recipe; concurrency and cancellation tests needed |
| 14 | Backups | **Partial** | Credential-file escaping and port in code; backup/restore and retention drill not run |
| 15 | Audit logs | **Partial** | CLI-safe audit and diff viewer changed; see `audit-coverage.md`: no completed route-by-route MySQL proof |
| 16 | Rider hand-off | Implemented–untested | Staged handoffs, delivery codes, throttling, rider reassignment guard; role and request tests needed |
| 17 | Attendance | Implemented–untested | Flag/shift/rate DB tests outstanding |

## Executed checks

- `npm run check`: **PASS**, including barcode JavaScript tests.
- `./cafe lint`: **PASS**, PHP syntax checks on repository files.
- `vendor/bin/phpunit`: **BLOCKED** (no `vendor/`; Composer unavailable).
- `php spark migrate`, `php spark seed`, `php spark inventory:reconcile`, `php spark barcode:audit`: **BLOCKED** (MySQL, MySQLi and dependencies unavailable).
- Database rollback and backup/restore: **BLOCKED** (no MySQL).

## Added migrations and seeders

- `000034_ReassignDemoBarcodes`: remaps exact old product/barcode pairs; down restores exact pairs.
- `000035_CreateStructuredAllergens`: adds reference/pivot tables, migrates existing free-text, leaves legacy text for downgrade; down drops new tables.
- `000036_AddCostTraceability`: adds `grams_per_piece` and sale-time `cost_status`; down drops columns.
- `000037_CreateOrderHandoffs`: stages/unique verification and hashed delivery code; down drops the new table and column.
- `000038_ClearUnsourcedDemoNutrition`: clears only unchanged unsourced demo nutrient figures and journals exactly which pre-existing rows were changed; down restores only untouched journaled records and drops the journal. Fresh post-migration seeds are not restored to fabricated numbers.
- `IngredientSeeder`: registry-based CSV import; only nonexisting names are inserted; opening stock passes through `IngredientInventoryService` with movement ledger, no admin values overwritten. No DDL to roll back.
- `RecipeSeeder`: registry-based, skip if product already has recipe lines; validated recipe/ingredient references, recalculates estimates, leaves verification to humans. No DDL to roll back.

## Needs the team

Provide authentic ingredient-level nutrient references (edition/food ID, measured density and piece mass), actual receipts for ingredient costs, and checked recipe weights for all eight demo products before source CSVs can be populated and the seed migration acceptance run. Confirm the suggested allergen catalog with the adviser and sign off nutrition through the admin review route. Complete real-device scanner/camera/push checks, Chrome 131+ printed report page numbering, and the staging backup/restore drill. Re-run all PHPUnit groups against dedicated MySQL test database with zero skipped tests.
