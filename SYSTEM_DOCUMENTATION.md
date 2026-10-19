# JRMSU-TC Cafeteria System Documentation

**Version 1.2.0 · CodeIgniter 4 + Bootstrap 5 + MySQL · Asia/Manila**

## Overview

JRMSU-TC Cafeteria is a role-based food ordering, delivery, POS, inventory, notification, and reporting system. Controllers remain thin; order, inventory, notification, authentication, audit, QR, and report rules are centralized in services. JSON endpoints retain the response shape `{ success, message, data, errors }`.

## Permission matrix

| Feature | Admin | Cashier | Rider | Customer/Public |
|---|---:|---:|---:|---:|
| Dashboard | Yes | Yes | Yes | Customer dashboard |
| Product/category management | Full | No | No | Browse only |
| Barcode labels / product QR | Full | Lookup | No | Product QR destination is public |
| POS | No | Full | No | No |
| Inventory | Stock-in/out, waste, physical count, reconcile | Read only | No | No |
| All orders | Full | Preparation/pickup workflow | No | No |
| Customer order ownership | Admin override | Operational list | Assigned delivery only | Own orders only |
| Rider assignment | Full | No | Assigned deliveries only | No |
| Customer PII | Masked list; audited reveal | Operational order data | Full only during Ready for Pickup / Out for Delivery | Own account/order data |
| Reports | Full | Dashboard summary | No | No |
| Audit logs | Read-only/filterable | No | No | No |
| Notifications | Own notifications | Own notifications | Own notifications | Own notifications |
| Settings | Full | Own profile/2FA | Own profile/2FA | Own profile/privacy |
| Recipes/nutrition verification/cost/profit | Full | No | No | Read only nutrition |
| Backup / deletion approval | Full | No | No | Request own deletion |
| Attendance (flag-enabled) | Own punch; admin shifts/rates/report | Own punch | Own punch | None |

API/controller ownership rules are checked in addition to route roles: customers can open only their own orders and riders can open/update only deliveries assigned to them.

## Core tables

Existing business tables remain in place. The panel corrections add or extend:

- `users`: `failed_login_attempts`, `locked_until`, `session_version`, `privacy_consent_at`, encrypted `phone`, `address`, `totp_secret`, recovery hashes, notification preferences, deletion requests.
- `products`: `sku`, `barcode`, `reorder_level`, `max_stock`, `packaging_cost`, serving/yield, calculated nutrition and review status, allergens, and healthy-choice metadata.
- `orders`: delivered status, signed handoff state and pickup code support.
- `inventory_movements`: immutable stock movement ledger with before/after balances, order/user links, references, and notes.
- `notifications`: per-user in-app order/assignment/stock notifications.
- `audit_logs`: sensitive security/admin activity with actor, entity, scrubbed JSON diff, IP, user agent, and timestamp.
- `ingredients`, `ingredient_cost_history`, `product_recipes`: costing and per-serving nutrition basis.
- `attendance_punches`, `attendance_shifts`, `attendance_pay_rates`: flag-protected attendance and estimated wages.
- `order_issues`: customer-reported missing/wrong/quality issue records.

All new migrations have reversible `down()` methods and follow the sequence after migration `000016`.

### Database CLI workflow

The project extends the normal Spark workflow while retaining CodeIgniter's native migration engine:

- `php spark migrate` is transparently routed to the project migration wrapper, which calls native `migrate --all`. Migration versions already recorded by CodeIgniter are displayed as `SKIPPED`; only pending migrations are applied, then the application migration history is verified and the command explicitly reports `No pending migrations`.
- `php spark seed` first performs migration verification, then checks `seeder_runs`. Seeders already recorded there are displayed as `SKIPPED`. For databases upgraded from an older version, the command can detect the expected data from the current built-in seeders and adopt those seeders as existing without rerunning them. Only genuinely pending seeders execute.
- `seeder_runs` stores the seeder name, source-file hash, whether it was executed or adopted as existing, and the recorded timestamp. New seeders remain pending until they execute successfully.
- `DatabaseSeeder::SEEDERS` is the single source of truth for seeder execution order, while `DatabaseSeeder::isSatisfied()` provides one-time legacy-data detection for the currently bundled seeders.
- Migration/seeding CLI output uses ANSI colors, emoji status markers, numbered progress, explicit `PENDING`/`SKIPPED` states, success/failure states, and final verification summaries.
- `cafe db migrate` and `cafe db seed` delegate to the same Spark commands, so both entry points have identical behavior.

## Order status source of truth

`App\Enums\OrderStatus` defines the database value, UI label, icon, badge variant, customer description, terminal state, and valid transitions.

```text
Pickup:
pending → confirmed → preparing → ready_for_pickup → completed

Delivery:
pending → confirmed → preparing → ready_for_pickup → out_for_delivery → delivered

Cancellation:
pending / confirmed / preparing / ready_for_pickup → cancelled
```

UI labels are exactly **Pending, Confirmed, Preparing, Ready for Pickup, Out for Delivery, Completed**, plus Cancelled. The status migration widens the enum, maps `ready` to `ready_for_pickup` and `delivered` to `completed` in orders/history, then narrows it. Its `down()` reverses the mapping.

Role constraints remain enforced by `OrderService::allowedTransitions()`: pickup completion belongs to cashier/admin workflow; delivery cannot leave Ready for Pickup without an assigned rider; only the assigned rider can move the delivery to Out for Delivery and Delivered. Payment becomes paid only at Completed.

## Transaction and inventory rules

Writes involving orders, payments, stock, notifications, assignments, or cancellation use explicit database transactions. Affected product/order rows are locked with `SELECT ... FOR UPDATE` before authoritative changes. Client totals, status values, prices, and stock are not trusted.

`InventoryService` is the only application component allowed to mutate `products.stock`:

- `deductForOrder()` → `sale`
- `restockForCancelledOrder()` → `cancel_restock`
- `stockIn()` → `stock_in`
- `adjust()` → `adjustment` and requires a reason
- `recordWaste()` → `waste`

Existing stock receives an `opening` movement during migration. Product edits do not post or overwrite stock. Initial stock is accepted only when creating a product and is ledgered as opening stock. Low/out-of-stock notifications are sent to active admins and cashiers only on a downward reorder threshold crossing (or positive stock reaching zero); restocking above the threshold re-arms alerts.

`php spark inventory:reconcile` checks current product stock against the signed ledger total and completed-order units against net sale/cancellation movements.

## Security controls

### Password policy and login lockout

The reusable `strong_password` validation rule requires 10+ characters containing uppercase, lowercase, number, and symbol characters. It rejects a bundled common-password list and values containing the user's name or email local part. Registration, reset-password, account password change, admin user creation/editing, and rider creation/editing use the same rule and live client-side checklist.

`AuthService::attempt()` retains the existing IP/email throttler and adds per-account lockout. Five consecutive failures set `locked_until` for 15 minutes; a successful login resets the counters. Authentication failures use a generic public message. Login success, failure, and lockout are audited without storing passwords/tokens or customer contact data in logs.

### Sessions, HTTPS, CSP

`AuthFilter` enforces `last_activity` and the current `users.session_version`. Default idle timeouts are 1,800 seconds for admin/cashier/rider and 7,200 seconds for customer, configurable through `Config\Cafeteria`. Password, role, or account-status changes increment the session version. Deactivated accounts continue to be blocked by `ActiveAccountFilter`.

In production, `app.forceGlobalSecureRequests`, secure cookies, and CSP default on and can be explicitly configured in `.env`. Cookies remain HTTP-only with `SameSite=Lax`. Application JavaScript is externalized; new libraries live under `public/assets/vendor/`.

### Customer information protection

Riders see full phone/address only while an assigned order is `ready_for_pickup` or `out_for_delivery`. Completed or cancelled deliveries mask those values. The admin customer list masks phones by default; the reveal action is authorized, server-side, and written to `audit_logs`.

### Audit events

The audit service records login outcomes/lockout, password changes, user/rider/customer status changes, product price/stock-impacting operations, order cancellation, rider assignment, settings changes, and PII reveal. Admin → Audit Logs is read-only and filterable.

## Barcode and QR support

Products support unique `sku` and `barcode` fields. Admin product management accepts scanner input and can generate an internal Code 128-compatible value from SKU. Printable labels include product name, current price, Code 128 barcode, and public menu QR. Barcode drawing uses the vendored script under `public/assets/vendor/jsbarcode/`.

`GET /api/products/barcode/{code}` is restricted to admin/cashier and returns the current product, active add-ons, stock, and availability or the standard 404 JSON shape.

POS and Inventory provide focused scan fields for keyboard-style USB/Bluetooth scanners. POS scanning adds/increments products after a server lookup and gives success/error feedback; the server still performs final stock validation in `OrderService::create()`.

Public product information uses `GET /menu/{slug}` and displays image, category, price, description, add-ons, nutrition, allergens, availability, and ordering action. Product/menu labels use a locally vendored QR generator.

Order QR values are not sequential IDs. `OrderQrService` signs the order ID plus request token with the application encryption key. `GET /staff/orders/verify/{token}` is limited to admin/cashier/rider and rejects tampering. POS and rider scan fields recognize verification URLs.

## Nutrition

Products store serving size, calories, protein, carbohydrates, fat, sugar, fiber, sodium, allergens, and healthy-choice state. The admin product form and product API validate non-negative nutrition values. Customer/public product details show an estimate-per-serving disclaimer. Menu filters include Under 500 kcal and allergen exclusion.

## Customer order history

`Customer\OrderController::index` provides newest-first server pagination (10 rows/page), search by order number, status/type/date filters, and cards for total orders, completed orders, paid amount spent, and active orders. Desktop uses the table view and mobile uses cards.

Order details include item/add-on snapshots, notes, subtotal, discount, delivery fee, total, payment information, delivery data, rider name, status timestamps/progress tracker, and signed QR. Reorder revalidates current price, product availability, selected add-ons, and stock and reports skipped lines.

## Notifications

`notifications` is user-scoped and indexed for unread retrieval. `NotificationService` is invoked inside the surrounding order/stock transaction so rolled-back business changes do not persist in-app notifications.

Recipients include:

- Customer: order placed and each significant status change, including cancellation reason where supplied.
- Admin/cashier: new pending online order and cancellation.
- Rider: assignment and removal.
- Admin: low-stock/out-of-stock.

Endpoints:

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/api/notifications` | Current user's latest notifications |
| GET | `/api/notifications/unread-count` | Current user's unread count |
| PATCH | `/api/notifications/{id}/read` | Mark owned notification read |
| PATCH | `/api/notifications/read-all` | Mark current user's notifications read |

Polling pauses while the tab is hidden and refreshes at approximately 25 seconds. The customer order tracker also polls and reloads when the status changes. Optional status email is controlled by the `email_order_notifications` setting; mail failure does not fail the order change.

## Reports

Admin Reports is a date-range report center with CSV export and print styles.

- **Sales:** only `payment_status='paid'`; gross sales, discounts, delivery fees, net sales, average order value, daily trend, type/payment/channel breakdown.
- **Orders:** status/type/channel counts, cancellation rate, average Confirmed → Ready for Pickup duration, order list.
- **Inventory:** current stock, reorder level, stock value, low/out-of-stock lists, opening/stock-in/sold/restocked/adjustment/waste/closing ledger movement.
- **Popular food:** top/slow sellers by quantity/revenue, category sales, best-selling add-ons from immutable order item JSON.
- **Delivery:** rider totals, completed/cancelled, paid delivery fees, COD collected, average Out for Delivery → Completed duration.

Admin and cashier dashboards reuse `ReportService` for today's paid sales/status/low-stock summary. Revenue reports never count unpaid pending orders.

## Main endpoints added/changed

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/menu/{slug}` | Public food information page |
| GET | `/api/products/barcode/{code}` | Staff product lookup by barcode |
| POST | `/api/orders/{id}/reorder` | Revalidate a customer's prior order |
| GET | `/staff/orders/verify/{token}` | Verify signed order QR |
| GET/PATCH | `/api/notifications...` | Current-user notification APIs |
| GET | `/admin/products/labels` | Printable barcode/QR labels |
| GET/POST | `/admin/inventory...` | Inventory ledger operations/reconcile |
| GET | `/cashier/inventory` | Read-only inventory |
| GET | `/admin/audit-logs` | Audit viewer |
| GET | `/admin/reports/export/{type}` | CSV export |

Existing APIs continue to use route role filters and ownership checks.

## Environment and defaults

Important keys in `.env.example` include:

```dotenv
app.forceGlobalSecureRequests = false
app.cookieSecure = false
app.CSPEnabled = false
CAFETERIA_DELIVERY_FEE = 40.00
CAFETERIA_IDLE_TIMEOUT = 1800
CAFETERIA_IDLE_TIMEOUT_ADMIN = 1800
CAFETERIA_IDLE_TIMEOUT_CASHIER = 1800
CAFETERIA_IDLE_TIMEOUT_RIDER = 1800
CAFETERIA_IDLE_TIMEOUT_CUSTOMER = 7200
```

Production should set the first three security keys true and use `CI_ENVIRONMENT = production`. The delivery-fee precedence is environment `CAFETERIA_DELIVERY_FEE`, then the `Config\Cafeteria` fallback (`40.00`); the fresh-database settings seeder initializes the same `40.00` value.

## Testing and demo checklist

After changes:

```bash
cafe db migrate
cafe test
cafe lint
php spark inventory:reconcile
```

`cafe db refresh` seeds SKU/barcode, nutrition, reorder levels, opening movements, and sample notifications. Demo accounts use `Cafeteria#2026Demo` and should be changed outside development.

## Panel compliance summary (17 corrections)

| # | Panel correction | Changed areas | Panel demonstration |
|---|---|---|---|
| 1 | Security/privacy | `PasswordHasher`, `TotpService`, `FieldEncryption`, registration consent, account privacy, admin deletion approval, route filters | Login Admin → Settings, enroll OTP, require OTP, export Customer's data, approve deletion; inspect encrypted raw phone data |
| 2 | Barcode | `BarcodeService`, POS/Inventory scan inputs, native + locally decoded camera scanning, batch count API | Cashier → POS → Scan with camera, or Admin → Inventory → Start scan count session → repeatedly scan → reconcile; reject invalid EAN |
| 3 | QR | Public `/menu`, signed QR expiry, KOT/receipt, A4/A6 poster | Admin → Settings → Printable poster, scan with logged-out phone; compare altered/expired token |
| 4 | Nutrition | Admin recipes, public nutrition details, verification badges and filters | Admin → Costing → edit recipe; Customer → Menu → filter; review nutrition label |
| 5 | Verified nutrition source | Ingredient `source_reference`, USDA public server lookup/cache, recipe computations and review reset | Admin → Costing → USDA lookup → Import; edit a recipe quantity → verify Estimated; inspect source (PhilFCT seed still pending) |
| 6 | Automated inventory | `InventoryService`, optional `IngredientInventoryService`, two ledgers, stock-out, stock cards | Admin → Costing → stock ingredients → Settings → enable recipe tracking; create/cancel order; run reconcile |
| 7 | Threshold stock alerts | `NotificationService::lowStock`, `max_stock`, reorder quantities | Sell below reorder level, check admin/cashier bells; sell again, check no repeated crossing alert |
| 8 | Food costing/profit | Recipes, cost history, `CostingService`, snapshot `order_items.unit_cost`, profit report | Admin → Costing → edit cost, review margin; Admin → Reports → Profit, verify sale-time snapshots |
| 9 | Delivered status | `OrderStatus`, reversible delivered migration, service/notifications/reports/rider UI | Rider → Deliveries → QR handoff → Delivered; verify paid status and Admin report |
| 10 | Order history/receipts | Customer receipt, QR/pickup code, issue form | Customer → Orders → Receipt → Print; review payment and order issue |
| 11 | Notifications | Notification APIs/filters, email preferences, Web Push opt-in/outbox, maintenance jobs | Admin → Settings → enable push; Customer → Settings → subscribe; change status → bell/email/push; run `notifications:push` |
| 12 | Printed reports | Report tabs, shared date ranges/CSV, print CSS and signatures, profit/audit/low-stock/conditional attendance | Admin → Reports → select quick range; export CSV, print to PDF; enable attendance and open its tab |
| 13 | Sale stock deduction | Order transaction, inventory ledger, idempotent cancellation restock | Cashier → POS → create order; cancel from admin; inspect Inventory movement ledger |
| 14 | Database backup | `DatabaseBackupService`, CLI commands, admin backup management | Admin → Backups → Create / Download; shell `cafe db backup`, verify checksum and restore in test DB |
| 15 | Audit activity | Recursive PII scrubbing, `recordChange`, expanded CRUD coverage, filtered viewer and CSV | Admin → Products → edit price; Audit logs → filter and inspect before/after → CSV |
| 16 | Preparation/verification | Ticket, persisted line preparation, QR/code handoff, issue report | Admin → Orders → mark every line prepared → Ready → Verify QR/code → fulfillment |
| 17 | Attendance (conditional) | `AttendanceService`, flag filter, admin schedules/rates, staff clock, wage estimate | Admin → Settings → Enable flag, login Cashier → Attendance → Clock in/out; Admin → Attendance report |

## Migration and safety notes

The corrections introduce reversible migrations `000023`–`000031`. Migration `000029` introduces ingredient stocks and their movements; `000030` adds cached nutrition lookup; `000031` adds encrypted push subscriptions and the notification outbox. The original migrations are retained without edits. The delivery-specific `delivered` status is terminal and fulfilled; pickup remains `completed`. Historical completed delivery orders and matching status history are remapped to `delivered`. A fresh installation must run all migrations and seeds; upgrade installation must back up the database **before** migrating, especially because customer PII is encrypted with `encryption.key`. Changing that key without decrypting/re-encrypting makes data unreadable.

`products.stock` is reserved at **order creation**, in the same transaction as line items and ledger movements, independently of when payment is received. Cancellation returns stock once. Profit uses the `unit_cost` saved on each sold line, rather than the latest ingredient cost. Nutrition source is `manual`, `computed`, or `imported`; review is `estimated` until admin verifies. Changing a recipe or ingredient resets computed nutrition to Estimated.

Staff roles require `auth`, `active`, and role filters, with separate ownership checks. Admin routes are admin-only, cashier pages are cashier-only, rider delivery pages require rider ownership, customer pages require customer ownership. Shared staff routes accept admin/cashier/rider with per-action validation. Attendance routes also require `attendance_enabled=1`; this defaults to zero.

Nutrition data entered manually and values derived from it are **estimates** unless independently verified; seed values must not be presented as source-certified. Amounts labeled as wages are estimates; actual Philippine tax, SSS, PhilHealth, and Pag-IBIG deductions are outside scope. PDF output uses browser Print → Save as PDF; no server-side PDF package was installed in this change set.

**Remaining limitations requiring development or external resources:** Primary-source PhilFCT ingredient seeding; browser integration tests for opt-in Web Push and its VAPID transport; broader 2D barcode decoding on devices lacking native BarcodeDetector; complete live database verification of every legacy CRUD audit hook; and a server-side PDF renderer. Network-based USDA data lookup uses public demo access and its availability/rate limits cannot be guaranteed; previously cached responses work offline. Only the portions fully validated in a browser and MySQL environment should be considered acceptance-tested.

## Backup operations and scheduling

- Linux cron: `15 1 * * * cd /var/www/Cafeteria && php spark db:backup` and `30 2 * * * cd /var/www/Cafeteria && php spark inventory:reconcile`. Additionally, use `0 7 * * *` for `inventory:low-stock-digest`, `20 2 * * *` for `notifications:prune`, and `* * * * *` for `notifications:push` (only when opt-in Web Push is enabled). Provide locked-down DB credentials through `.env` and an encrypted offsite storage account.
- Windows Task Scheduler: run `php.exe C:\\xampp\\htdocs\\Cafeteria\\spark db:backup` daily from the project directory. Add a second task with `inventory:reconcile`.
- Backups use `mysqldump --single-transaction --routines --triggers` with temp credential file, gzip, SHA-256, configurable 7/4/6 day/week/month retention, and optional S3 server-side AES-256. The portable PHP fallback exports tables only; it **does not** preserve stored procedures or triggers. Run `cafe db restore-check` (or `php spark db:restore-check`) in a quiet maintenance window to take a fresh signed backup, restore into the **absent** `<database>_restore_check` schema, compare selected table row counts and automatically drop only that newly created schema. The drill fails closed if its scratch schema already exists and requires the `mysql` client plus CREATE/DROP DATABASE privileges. Backup retention selects distinct days/weeks/months and is unit tested. The separate, explicitly confirmed `db:restore` command still restores the live database, overwrites its existing data and takes a pre-restore backup; only CLI can run it. A passing scratch drill does not replace staging restore testing of triggers or full data checks.
- Offsite credentials: `AWS_*` environment config; never put secrets in code or logs. Backup files belong under non-public `writable/backups/`.

## Environment settings introduced

`encryption.key` remains the only existing encryption configuration required for private data and optional locally generated Web Push signing keys. **No new environment variables are introduced by this upgrade.** The original optional `AWS_REGION`, `AWS_S3_BUCKET`, `AWS_ACCESS_KEY_ID` and `AWS_SECRET_ACCESS_KEY` remain for S3. All new operational controls live in the `settings` table: `backup_keep_daily` (7), `backup_keep_weekly` (4), `backup_keep_monthly` (6), `attendance_overtime_premium` (0.25), `attendance_night_differential` (0.10), `notification_read_retention_days` (90), `inventory_ingredient_tracking_enabled` (0), `low_stock_email_digest` (0), and `web_push_enabled` (0). `OperationalSettingsSeeder` is registered and idempotent for upgrades. Push keys are generated on first use and encrypted using the existing `encryption.key`.

## Verification and final change report

Restored missing role-protected routes, corrected the locally vendored chart script, cleaned runtime files (excluded by ignore rules), fixed threshold stock alerts, and extended delivered status, public menu, print QR and receipts, admin costing, privacy, attendance, order preparation and reporting. Follow-up changes implement a same-origin barcode decoder, transactional ingredient reservations and restocks, USDA data lookup and caching, scheduled low-stock digest and notification pruning, optional Web Push, expanded audit viewer, date-aware low-stock and attendance reports, and an upgrade-safe operational settings seeder. Added isolated pure-function unit tests for TOTP, hashing, barcode checksums, audit scrub, units, backup checksums, Delivered and attendance wage computations, plus source-route checks.

**Execution limitation:** PHP syntax lint and `npm run check` can be run in the provided sandbox; Composer vendor files, a MySQL server, `php spark migrate`/seed, database-backed PHPUnit tests, full rollback/refresh, and restore into a fresh MySQL schema are unavailable here. Full integration success has not been asserted. Before panel demonstration, install dependencies and configure a test MySQL database, run `cafe db refresh`, `cafe test`, `cafe lint`, `npm run check`, `php spark inventory:reconcile`, migration rollback/reapply, and a backup/restore drill. Live database/browser/external-service behavior is not verified; remaining partial requirements are explicitly stated above.

## Follow-up configuration and safety notes

For upgraded databases, `php spark seed` runs the idempotent `OperationalSettingsSeeder`, adding only missing operational settings while preserving existing administrator choices. New administrative controls are in Admin → Settings, not in the environment file. Recipe stock tracking must remain disabled until every tracked ingredient has a correctly counted opening stock. Ingredient quantity conversions support g/kg, ml/L and pieces; recipes cannot mix dimensionally incompatible units. Sale-time ingredient movements and any cancellation restock use the exact sale ledger, not a changed recipe.

Camera recognition uses built-in `BarcodeDetector` where available and otherwise loads the locally authored Code 128 B / EAN-13 / UPC-A scanner under `public/assets/vendor/cafeteria-barcode-decoder/`. It requires an HTTPS or localhost origin for camera permission; keyboards and USB/Bluetooth scanners still work offline. Decoder checksum checks have an executable `tests/js/barcode-fallback.test.cjs` integrated into `npm run check`. Browser environments require separate camera/performance tests.

The optional USDA source search is Admin → Costing → USDA nutrition search. Searches and detailed responses are cached in `nutrition_search_cache`; imported nutrient numbers are mapped to ingredients per 100 g with an explicit USDA FDC record reference. It uses the USDA public test key internally, which is rate-limited and cannot be guaranteed available. No external key is required. Changes to an imported ingredient recalculate associated recipe totals and reset them to Estimated. FNRI-DOST PhilFCT source values must be independently verified and entered/imported before declaring Philippine-source coverage complete.

With Web Push enabled under Admin → Settings, a browser on HTTPS can opt in under Account Settings. A VAPID P-256 key is generated locally once and the private portion encrypted before being saved in `settings`. Subscriptions are stored encrypted. The `notifications:push` command attempts delivery of outbox messages after the business transaction has finished; unsupported or unsubscribed browsers continue receiving in-app notifications. This is optional and defaults off. Device/browser verification and transport reliability tests remain necessary.

The read-notification purge job respects `notification_read_retention_days` and leaves unread messages alone. The low-stock digest defaults off and needs working SMTP credentials already supported by the project; it runs once per day with a database advisory lock. Detailed report views, exports and Manila-calendar date filters should be reconciled on a test database. Attendance payroll remains an estimate, not a statutory Philippine payroll calculation.

### Additional integration safeguards (migrations 000032 and 000033)

`000032_TrackPushDeliveries` adds a reversible, per-device push delivery receipt table. The Web Push cron dispatcher obtains a MySQL advisory lock; successful endpoints are recorded and omitted on subsequent retries if other endpoints fail. Push transport is still best effort (power loss after the remote server accepts a request but before the local receipt commits cannot be made exactly-once without remote acknowledgements). No VAPID environment entries or new credentials are needed.

`000033_AddLiquidDensity` adds a reversible `ingredients.density_g_per_ml` field (default 1 for water-like liquids). Admin → Costing can set the actual density; a recipe in millilitres must never be converted directly into a mass unit for cost accounting. Nutrition per 100 g uses the recipe liquid volume multiplied by its recorded density. Accurate densities and the nutrient values from primary food-composition sources must be verified before publishing a Verified nutrition label.

Customers receive an in-app rider-assignment notification naming their assigned rider, and a delivery dispatch message clarifies that ETA depends on actual traffic rather than supplying an unsupported predicted arrival time. Attendance clock-in now locks the employee record for first-punch concurrency safety; reports load only the selected tab rather than querying every report each time.

The distributable `.env.example` is restored to the original project's contents. It does **not** define USDA API, VAPID, backup retention, secondary AWS region/bucket, or attendance-premium environment keys. Existing `AWS_REGION`, `AWS_S3_BUCKET`, `GMAIL_USER`, and `encryption.key` settings remain unchanged.

### Source-controlled nutrition and panel-verification safeguards (2026-10-19)

CSV inputs: `app/Database/Seeds/data/ingredients.csv` and `recipes.csv` are checked against `SourcedRecipeCatalog` before any write. Missing/invalid food-source references and missing nutrient numbers from the USDA service are rejected, not silently replaced with zero. The catalog/recipe seeders follow the `seeder_runs` registry and never overwrite an existing ingredient or a product with recipe lines. Ingredient opening balance is recorded by `IngredientInventoryService` on the same transaction and connection; changing ingredient costs does not alter historical `order_items.unit_cost`. Cooking yield changes cooked mass, not a recipe's total nutrients. A 100g-basis ingredient used as pieces requires measured `grams_per_piece`. Once `inventory_ingredient_tracking_enabled` is enabled, orders lacking ingredient recipes are rejected transactionally instead of bypassing the ingredient ledger.

`000038_ClearUnsourcedDemoNutrition` preserves administrator-edited nutrients and stores a migration-only journal so down migration only reverses rows its up migration actually changed. Staff must personally sign off sourced recipe nutrition; auto-refresh always marks it Estimated. Rider dispatch verification is bound to the assigned rider and prohibits reassignment after sign-off. Barcode stock cards and report CSV exports include escaped spreadsheet cells. See `docs/acceptance/` for the blocked MySQL-backed proof and route audit coverage; hardware checks require real devices. For Chrome print page numbers use Chromium 131+.

### Scoped handoff proofs and status roles (2026-10-19)

Dispatch/pickup QR tokens and delivery-confirmation QR tokens now have different HMAC purposes; the delivery token begins `d.` and is accepted only for an `out_for_delivery` order, in the delivery stage, by its assigned rider. A standard kitchen QR is never sufficient proof of delivered goods. The customer alone receives the delivery QR on the order page. Four-digit delivery verification remains hashed server-side with throttled attempts. Handoff rows continue to capture evidence per stage.

Order JSON APIs use `OrderApiPresenter`, which strips `request_token`, verification hashes and internal handoff fields before returning any role's order data. Rider address/notes are included only when assigned and delivery is actively ready/in transit. Rider ticket lookup and staff verification screens likewise restrict unrelated orders. Admin status transition logic forbids marking a delivery order as pickup `completed`. Low-stock alerts trigger on a single reorder-threshold crossing; audit CSV escapes spreadsheet formulas.

Behavior tests `OrderSecurityPolicyTest`, `OrderTransitionMatrixTest`, and `DeliveryQrSeparationDatabaseTest` were added; database tests require a real dedicated MySQL database and have **not** been verified in this environment.

### Continuation v4 — backup drill and attendance validation (2026-10-19)

`BackupRestoreDrillService` and `db:restore-check` provide a checksum-validated scratch import, table-count comparison, refusal to overwrite existing schemas, and `finally` cleanup of schemas the command creates. `DatabaseBackupService::retainedNames()` makes daily/weekly/monthly retention deterministic and avoids promoting same-day duplicates into longer-term backups. The attendance controller validates staff, 24-hour schedule times, 0–120 minute grace periods, hourly rates, and corrected dates, serializes edits using a staff row lock, updates existing same-day wage rates, and writes event-specific audit entries and formula-safe UTF-8 CSV. Delivery hand-off attempts are now rate-limited by order/stage/actor rather than IP. These are implementation and local syntax/unit-fixture checks only; a MySQL restore drill, HTTP role checks, and migration rollback still need live testing.

### Privacy export and deletion workflow (2026-10-19 continuation)

`Account\PrivacyController::export()` delegates to `CustomerDataExportService`. Every query is filtered by the session customer ID, uses an explicit field allowlist, and the data access is recorded through `AuditLogService` within the same transaction. Included sections are profile, orders, items, payments and reviews. Internal request tokens, pickup/delivery code hashes, payment transaction references, security fields and historical cost snapshots are excluded. The response is JSON with cache prevention.

`Admin\PrivacyController::approve()` delegates to `CustomerDataDeletionService`, which locks the pending customer row and performs anonymization in one database transaction. Personal contact fields and unstructured notes/reviews/notifications/device subscriptions/auth tokens are cleared; financial order and payment records are retained. Account status becomes inactive and the session version increments. Audit action: `personal_data_deletion_approved`. The avatar file/object is deleted after commit through the existing media storage service. No new settings, environment variables, or migrations are required. MySQL acceptance still pending.


### Signed order QR verification (October 2026)

Kitchen/dispatch (`o2`) and customer delivery (`d2`) QR tokens now use distinct,
HMAC-authenticated scopes, with their issue timestamp signed and a fixed **48-hour**
validity period. A 60-second clock-skew allowance applies; delivery QR is additionally
restricted to orders currently **Out for Delivery**. Verification fails closed when
`encryption.key` is absent. Legacy unsigned-timestamp QR tokens are not accepted:
**reprint older kitchen tickets and customer receipts** if they need verification.
The stable four-digit pickup and delivery confirmation codes remain available under
existing order-stage and rate-limit rules. Printed/digital code scans require real-device
acceptance testing.

The inventory stock card uses 25 rows/page and preserves ledger, date and movement
type filters. Report CSV files include order, profit and delivery summary metrics to
reconcile with on-screen totals. All database-backed acceptance and browser print
verification remain pending on a MySQL-enabled environment.


### Continuation v7 — stock-card completeness and atomic profile audit

`StockCardService::exportRows()` returns a chronological generator of all
filtered movements with ledger running balances calculated across all movement
types before the date/type filters. `InventoryController::stockCardExport()`
uses it for full CSV exports; `stockCardPrint()` passes the generator to
`admin/inventory/stock_card_print` so browser printing is not limited to the
normal 25-row pager. Admin and cashier `stock-card/print` routes are GET-only;
cashier stock edits remain unavailable. The normal pager preserves the same
filter query parameters. Exports include a UTF-8 BOM and formula escaping.

`Account\SettingController` saves profile edits and avatar removal within a
single DB transaction that includes its `AuditLogService` event. Old avatar
media deletion follows successful commit, and failed updates clean up new
uploads. `AuditLogService::scrub()` excludes user names and free-text notes,
comments, reasons and descriptions from JSON diff payloads; `recordChange()`
records field identifiers only for sensitive-only edits. Any failed audit
write now raises an error. Added unit privacy and MySQL stock-card iterator
tests. No migration or new environment variable was introduced; live MySQL
acceptance and Chrome print checks are pending.

### Continuation v8 — API response policy and push delivery audit

`ApiRequestPolicy::isApiPath()` recognizes the complete `api` path segment with
or without the leading slash and with an installation path prefix. Authentication,
active-account, and role-denial filters use the same predicate and emit JSON
errors rather than redirects for API clients without AJAX headers.

`WebPushService` accepts an optional injected HTTP transport for tests. Live
transports still use encrypted VAPID, with trusted-provider HTTPS endpoint
validation performed before sending even for existing subscriptions. When an
outbox item is dispatched, successful per-device receipts, HTTP 404/410
subscription removal, the delivery/attempt state and the scrubbed
`push_dispatch_attempt` audit row commit transactionally. A MySQL advisory lock
prevents simultaneous dispatchers. A power failure between provider acceptance
and recording the receipt can still cause a resend. The transport-mocked DB
acceptance tests require Composer and a running MySQL test schema.

## Source-data and token auditing safeguards (2026-10-19)

- `nutrition:catalog-audit` is a read-only readiness check for eight demo recipe source CSV groups, referenced ingredient names, batch consistency and measurable recipe-to-purchase units. It does not independently verify externally cited food IDs.
- `IngredientSeeder` and `RecipeSeeder` with empty CSVs are NOT satisfied; the `seed` CLI reports `BLOCKED` rather than registering them as complete. Previously recorded placeholder runs must be corrected using an append-only sourced-data migration, not a silent seeder replay.
- `AuthService::issueVerificationToken()` and `issuePasswordResetToken()` replace prior unused tokens in one transaction with a PII-free `token_issue` audit record. The controller may send email only after the transaction commits.
- `NotificationApiController::read()` and `readAll()` use transactional updates. These low-risk per-user read acknowledgments remain documented audit exemptions.
- The audit scrubber also drops raw messages, request payloads, contact URLs, subscription endpoints and other unstructured fields that might contain personal details.
- Database acceptance is pending a real MySQL database, the MySQLi extension and Composer dependencies.

### Team-sourced nutrition import CLI

`nutrition:ingredient-import` uses `NutritionLookupService::details(FDC_ID)` to obtain live or already-cached USDA Foundation/SR Legacy values. `NutritionCatalogImport::ingredientFromUsda()` requires every supported nutrient in the response and operator-supplied purchasing cost, **measured** density for volume conversions, and piece weight where needed; 0 in `density_g_per_ml` explicitly means *unmeasured* for weight-only ingredients and is rejected by volume-to-mass conversions; it never creates synthetic nutrition numbers. `nutrition:recipe-add` validates the product slug, existence of the previously sourced ingredient, unit conversions, batch consistency and duplicate lines. These two commands edit **only** the original `ingredients.csv` and `recipes.csv` inside `app/Database/Seeds/data/`. A stable advisory file lock and atomic CSV replacement prevent interrupted imports from truncating the catalog.

The Spark seed preflight and direct `DatabaseSeeder::run()` both withhold **both** nutrition seeders until the catalog is complete, preventing permanent registry markers for partial catalogs. Normal `php spark seed` returns success for completed core seeders while clearly reporting nutrition seeders **DEFERRED / UNVERIFIED**, including older installations with historical nutrition `seeder_runs` markers (which are preserved and never counted as proof of sourced data); `php spark seed strict` returns failure unless all registered seeders are fully satisfied. Neither mode claims the nutrition catalog is verified while source files are empty. After the catalog covers all eight demo products (`php spark nutrition:catalog-audit`), run `php spark seed` to import into a fresh or eligible unseeded DB. On a DB where `seeder_runs` already records these seeders, deliver corrections using a new reversible data migration. Human matching of source records to purchased food, measured recipe quantities, supplier pricing and final nutrition verification remain mandatory. The source CSVs shipped with this archive stay empty rather than include invented values.

### Nutrition product edits and provenance guards

`App\Services\NutritionEditPolicy::apply()` protects verified nutrition from direct form/API mutations. Numeric string formatting alone does not clear verification; actual manual edits clear `nutrition_verified_by/at`, set `nutrition_status=estimated`, and set `nutrition_source=manual` in the product-save transaction. Recipe-backed nutrients must be recalculated through `CostingService` rather than overwritten in the product editor. `CostingController::verify()` recalculates from the saved sourced recipe before human sign-off and avoids duplicate verification audit rows for an already verified product. Manual ingredient entry requires a formatted PhilFCT/USDA source and seven explicit nutrient numbers; missing density stays unmeasured, not an assumed 1 g/mL. `NutritionEditPolicyTest` exercises these rules, while HTTP/MySQL acceptance remains pending.


### Independent product nutrition imports

`DemoProductNutritionCatalog` gates each of eight product seeders separately against `ingredients.csv`, `recipes.csv` and `recipe_measurement_workbook.csv`: it checks exact source IDs, consistent batch settings, unit conversions, and kitchen approval name/date. `DemoProductRecipeSeedService` performs the isolated import in a transaction using only the product's recipe lines, ledgered ingredient opening balances, `CostingService::refreshNutrition`, and same-connection audit events. `DatabaseSeeder::SEEDERS` lists each custom class and `isSatisfied()` compares the actual DB recipe quantities/units/source references with the source CSV. `SeedAll` marks unapproved products deferred rather than consuming registry markers; legacy global ingredient/recipe seeders remain in the registry for backward compatibility without being replayed for partial catalogs. `DatabaseSeeder::run()` honors the same per-product readiness. Neither the 20 candidate USDA source IDs nor a completed worksheet alone is a substitute for actual nutrient source validation and authorized nutrition verification.

### Interactive nutrition catalog preparation (`php spark populate`)

`App\Commands\Populate` delegates to `NutritionPopulateService` to collect an
actual kitchen recipe, validate each ingredient via an FDC detail response and
`NutritionCatalogImport::ingredientFromUsda`, and check the product with
`DemoProductNutritionCatalog::assess` before changing the three CSVs. The
operator must explicitly confirm recipe inclusion, source identity, signed kitchen
measurements, and final write. Missing/unknown values fail closed. Products can
be populated independently; preexisting signed recipes are never overwritten.
The command updates **source CSV files only**, not the application database.
It requires an interactive terminal, functioning USDA lookup/cache, and human
recipe measurements and approval. Afterwards `php spark seed` uses the separate
registered product seeders.
