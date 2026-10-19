# JRMSU-TC Cafeteria — panel corrections acceptance state

**As of 2026-10-19. This is NOT a database-verified acceptance report.**

The code-only patch preserves the repository's existing folder structure and only bundles changed/new files. No new `.env` keys were introduced and migrations 000001–000033 were not edited. This runtime has no Composer executable, MySQL CLI, or PHP `mysqli` extension; database acceptance tests and migration rollbacks could not run. Printer/scanner/push hardware tests could not run. **Do not claim 100% compliance.**

| # | Correction | Status | Evidence / next acceptance task |
|---|---|---|---|
| 1 | Security / customer information | Implemented–untested | Restricted API fields and newly whitelisted customer data export, audited on one connection; privacy anonymization scrubs order notes, messages, and tokens while preserving accounting; `CustomerPrivacyProjectionTest` and MySQL-required `CustomerPrivacyDatabaseTest` added |
| 2 | Barcode scanning | Implemented–untested | `npm run check` PASS; MySQL barcode lookup and both save endpoints still need tests; scanner hardware outstanding |
| 3 | QR codes | Implemented–untested | v2 purpose-bound signed QR tokens expire after 48 hours; `SignedOrderQrTest` added; full MySQL/phone acceptance outstanding |
| 4 | Nutrition display / allergens | Implemented–untested | Structured allergens and Nutrition Facts views changed; migration up/down HTTP tests required |
| 5 | Reliable nutrition | **Partial** | Strict source CSV loader, idempotent ingredient/recipe seeders, ledger opening, yield/piece conversions, USDA missing-field rejection and unsourced demo cleanup; **source CSVs deliberately contain headers only** pending genuine PhilFCT/USDA references and team-reviewed recipes |
| 6 | Inventory stock card | Implemented–untested | StockCardService supports ingredient/product ledgers, filters and paging; real 60-row database fixture needed |
| 7 | Minimum-stock alerts | Implemented–untested | `StockAlertPolicy` now prevents a second zero-stock alert in the same below-threshold episode; recipient/digest MySQL tests still outstanding |
| 8 | Food costing / profits | Implemented–untested | Missing-recipe statuses, historic snapshots and discount allocation in code; MySQL reconciliation needed |
| 9 | Order status flow | Implemented–untested | Fixed admin delivery-to-pickup-completed loophole; added `OrderTransitionMatrixTest`; real-DB payment/status matrix still required |
| 10 | Customer order history | Implemented–untested | Item summaries, payment badges and progress stepper in code; HTTP/query-count tests needed |
| 11 | Notifications | Implemented–untested | Push/email mocked behavior and real device check required |
| 12 | Printable reports | Implemented–untested | Explicit orders CSV whitelist and formula neutralization; CSV/accounting DB tests and Chrome PDF page-number verification needed |
| 13 | Stock deduction | Implemented–untested | Transactional product and ingredient writer calls, fail-fast on missing recipe; concurrency and cancellation tests needed |
| 14 | Backups | Implemented–untested | `db:restore-check` disposable-schema import, checksum/row-count verification, cleanup and duplicate-safe retention; unit policy tests added; real MySQL restore/permissions drill not run |
| 15 | Audit logs | **Partial** | CLI-safe audit and diff viewer changed; see `audit-coverage.md`: no completed route-by-route MySQL proof |
| 16 | Rider hand-off | Implemented–untested | Separate customer-only delivery QR scope (kitchen/dispatch QR cannot mark Delivered), stage checks, rider ticket access controls; added `DeliveryQrSeparationDatabaseTest`, MySQL tests still blocked |
| 17 | Attendance | Implemented–untested | Strict time/rate/date validation, event-specific audits, formula-safe exports and `AttendanceInputPolicyTest` wage examples; database and role-filter tests outstanding |

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

## Continuation v3: implementation evidence (not MySQL verification)

- Added customer-scoped delivery QR and stage-specific verifier. Customer-only code/QR remains valid only while `out_for_delivery`; kitchen-ticket QR is no longer accepted for `delivery` stage.
- Rider staff QR/ticket pages now reject unrelated orders. The API presenter removes signing secrets, internal handoff fields and completed rider contact information from all order JSON responses.
- Below-threshold stock notifications trigger once per episode; admin delivery orders cannot be marked `completed` as pickup. Audit CSV neutralizes formula prefixes.
- Added PHPUnit `OrderSecurityPolicyTest`, `OrderTransitionMatrixTest`, and MySQL-tagged `DeliveryQrSeparationDatabaseTest`; latter cannot be run here.
- `./cafe lint` and `npm run check`: passed after these modifications.

## Continuation v4 — changed code and verification remaining

- `app/Services/BackupRestoreDrillService.php`, `app/Commands/DatabaseRestoreCheck.php`, `cafe`: new `db:restore-check` command, refuses to overwrite a pre-existing scratch schema, validates the backup checksum, imports into `<db>_restore_check`, compares 16 important table counts, audits its success, and drops only the scratch schema it created. Real MySQL restore results are **blocked** here.
- `app/Services/DatabaseBackupService.php`: deterministic distinct daily/weekly/monthly retention. Added `BackupRetentionTest` and `BackupRestoreDrillTest` (policy and tampering).
- `app/Controllers/Attendance/AttendanceController.php`, `app/Services/AttendanceInputPolicy.php`: validates shift times, grace, employee and wage inputs; serializes edits, avoids same-day rate duplicates, adds auditable diffs, and sanitizes exported wage CSV. Added `AttendanceInputPolicyTest` with worked payroll examples.
- `app/Controllers/Staff/PreparationController.php`: hand-off throttling is now per order/stage/actor, resistant to network-address rotation.
- Ran `npm run check` and `./cafe lint` locally after the changes. PHPUnit and CLI restore-check require Composer/MySQL and are **not verified**. No new environment variable or migration was introduced.

## Continuation v5 — customer privacy code and remaining verification

- `CustomerDataExportService`: explicit SQL field allowlists for owned profile, orders, items, payments and reviews, normalized purchase add-ons; private request/verification tokens, raw password/TOTP fields, internal unit costs and transaction references are not returned. Existing field encryption is used for contact/address columns, including legacy plaintext. `GET /settings/data/export` sends `no-store` and `nosniff` headers and audits the export transactionally.
- `CustomerDataDeletionService`: atomic customer anonymization and single audit row, including optional delivery/item/status/issue notes, review comments, stored push subscriptions, notification content, and auth tokens. Financial amounts/orders/payments remain for accounting; old sessions are invalidated. After commit, removes the old avatar via `MediaStorageService`.
- `CustomerPrivacyProjectionTest` (PHPUnit unit), `CustomerPrivacyDatabaseTest` (MySQL group) test export allowlist, ownership, deletion isolation, retention and audit uniqueness. Pure-logic whitelist smoke check, `npm run check`, and `./cafe lint`: **PASS**. MySQL/Composer unavailable: tests remain **Implemented–untested**, not Verified.

## Continuation v6 — code changes and pending acceptance

- `SignedOrderQr`: new standalone HMAC codec for signed kitchen (`o2`) and delivery (`d2`) QR tokens; the issue timestamp is authenticated and tokens expire after 48 hours with 60-second clock skew allowance. QR proofs are bound to the order request token and cannot cross stages. `OrderQrService` refuses the old unversioned indefinitely valid format and refuses to sign without `encryption.key`. Previously printed old QR tickets need reprinting; stable four-digit pickup/delivery code behavior is unchanged.
- Customer order-detail progress ends at **Delivered** for delivery orders, not **Completed**. Receipt, ticket and customer views explain QR expiry.
- Corrected CI4 stock-card pager arguments to `(currentPage, perPage, totalRows)`. Expanded `PanelDatabaseCorrectionsTest` with second/third page checks and stable opening/closing balances.
- Profit, order and delivery CSV exports now append the matching on-screen summary figures. `CustomerController::revealPhone` refuses PII release when its audit transaction fails; push unsubscription also checks its transaction status.
- `SignedOrderQrTest` and updated `PanelCorrectionsTest` exercise signing, cross-purpose rejection, tampering and exact expiry boundaries without MySQL. **These PHPUnit tests require Composer and could not run here.** CLI-only QR smoke checks, PHP lint and JavaScript checks were run separately. Full MySQL HTTP, reports, printed PDFs, and device acceptance still pending; no status is upgraded to Verified.


## Continuation v7 — full-range stock card and PII-safe audit (not MySQL-verified)

- Stock cards now preserve filters in the normal 25-row pager. CSV export and
  dedicated admin/cashier print routes iterate **all** selected movements,
  without the former silent 50,000-row cap or current-page print truncation.
  The print page uses the existing local Bootstrap and print CSS. New
  `StockCardExportDatabaseTest` checks 60 movements, CSV/print iterator
  completeness, stable running balance, and invalid query rejection.
- Account profile and avatar removal now use same-connection transactions
  with an audit write that must succeed. File cleanup is after commit. Audit
  text scrubbing covers names, freeform reasons, descriptions, supplier data,
  comments and notes. Sensitive-only edits write only changed field *names*.
  `AuditPrivacyTest` covers recursive redaction.
- `./cafe lint`: PASS; `npm run check`: PASS; direct audit-scrubbing PHP smoke
  check: PASS. PHPUnit and full MySQL stock-card/role tests: BLOCKED (Composer,
  MySQLi and server unavailable). Physical Chrome/print checks: NOT RUN.
- New migration / seeder: **none**. No `.env` keys added. Corrections #1,
  #6 and #15 stay **Implemented–untested** / **Partial** as above, not Verified.

## Continuation v8 — mocked push dispatch and JSON authorization (implementation only)

- `WebPushService` accepts a test-only transport closure. Every pending device is
  individually acknowledged, expired 404/410 subscriptions are removed, and a
  retry never resends previously acknowledged devices. Successful responses,
  retirement, outbox attempt count and a PII-free `push_dispatch_attempt` audit
  are committed on one database connection. Stored endpoints are revalidated
  against the HTTPS provider allowlist before any HTTP request.
- `ApiRequestPolicy` is now shared by `AuthFilter`, `ActiveAccountFilter`, and
  `RoleFilter`. Unauthorized `/api/...` requests return 401/403 JSON even without
  XMLHttpRequest headers or when deployed under a URL prefix.
- Added `WebPushEndpointPolicyTest`, `ApiRequestPolicyTest`,
  `WebPushDispatchDatabaseTest`, and `ApiAuthorizationFeatureTest`. Database and
  Feature tests are **not verified** without Composer, MySQLi and MySQL.
- `npm run check` and `./cafe lint` **PASS**.

## Continuation v9 — code implemented, MySQL acceptance still blocked

- `AuthService`: verification and password-reset token reissuance now lock the user, invalidate older links, persist `token_issue` metadata-only audit once, and commit atomically. `AuthenticationTokenAuditDatabaseTest` covers token reuse prevention, password reset and session-version invalidation, and rollback for missing users. These DB tests **have not executed**.
- `SeedAll` / `DatabaseSeeder`: header-only ingredient/recipe CSV files now leave nutrition seeders **blocked**, not marked completed in `seeder_runs`. A previously recorded placeholder must be corrected with an append-only data migration after real source data is obtained; no seed state is overwritten. `NutritionSeederRegistryDatabaseTest` is added, **not run**.
- `NutritionCatalogAudit` / `php spark nutrition:catalog-audit`: source-name/recipe-coverage/measurement preflight for all eight demos. Missing ingredients and contradictory quantities are reported, with no simulated nutrient values. The existing CSVs remain empty, so correction #5 stays **Partial / blocked on team source data**. `NutritionCatalogAuditTest` contains synthetic test fixtures only.
- `NotificationApiController`: atomic individual and bulk read acknowledgments; documented low-risk audit exemption. `AuditLogService` drops additional PII-prone unstructured details and `AuditPrivacyTest` has expanded assertions. Correction #15 stays **Partial** until all routes have real-DB proof.
- Runtime checks: see final packaging note; MySQL-backed PHPUnit, migrations and hardware acceptance are **not Verified** here. No new settings keys, `.env` variables or migrations.

## Continuation v12 — nutrition verification integrity (code implemented, not MySQL verified)

- Shared `NutritionEditPolicy` protects human sign-off for both the admin product form and product API. Non-nutrition changes preserve verification; actual edits to manual nutrients reset it, and recipe-derived totals must be changed through Food Costing. Omitted API nutrient fields no longer erase previous estimates.
- The Nutrition Facts source display no longer lists recipe food IDs for manually edited nutrient totals. Ingredient form no longer suggests that unknown density is 1 g/mL or silently fills missing nutrients with zero; source reference is required.
- Costing verification recomputes the stored recipe before a human sign-off, records the audit in the same transaction, and refuses duplicate verification events.
- Added `NutritionEditPolicyTest` using synthetic test values. PHP lint, JavaScript barcode checks and direct policy smoke tests passed. Database-backed controller and sign-off behavior remain **Implemented–untested** because MySQLi and Composer are not installed in this environment. The ingredient and recipe source CSVs are still empty; correction #5 remains **Partial**.

## Execution checkpoint — 2026-10-19 (v15)

Detailed command outputs and blockers are recorded in `docs/acceptance/execution-2026-10-19.md`. `npm run check` and `./cafe lint` passed. Thirty-three standalone PHP assertions passed (barcodes, QR signatures, nutrition policy and stock-card validation), but are not a substitute for PHPUnit/MySQL. PHPUnit and three Spark database checks could not start because Composer dependencies and the database-capable PHP runtime were unavailable. `StockCardService` and `StockCardRequestValidationTest` correct/test invalid stock-card dates and types. No database-dependent correction was promoted to Verified; nutrition still requires real source records and eight measured recipes.
