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
| Inventory | Full stock-in/adjust/waste/reconcile | Read only | No | No |
| All orders | Full | Preparation/pickup workflow | No | No |
| Customer order ownership | Admin override | Operational list | Assigned delivery only | Own orders only |
| Rider assignment | Full | No | Assigned deliveries only | No |
| Customer PII | Masked list; audited reveal | Operational order data | Full only during Ready for Pickup / Out for Delivery | Own account/order data |
| Reports | Full | Dashboard summary | No | No |
| Audit logs | Read-only/filterable | No | No | No |
| Notifications | Own notifications | Own notifications | Own notifications | Own notifications |
| Settings | Full | No | No | No |

API/controller ownership rules are checked in addition to route roles: customers can open only their own orders and riders can open/update only deliveries assigned to them.

## Core tables

Existing business tables remain in place. The panel corrections add or extend:

- `users`: `failed_login_attempts`, `locked_until`, `session_version`.
- `products`: `sku`, `barcode`, `reorder_level`, nutrition fields, allergens, and healthy-choice metadata.
- `orders`: normalized status enum.
- `inventory_movements`: immutable stock movement ledger with before/after balances, order/user links, references, and notes.
- `notifications`: per-user in-app order/assignment/stock notifications.
- `audit_logs`: sensitive security/admin activity with actor, entity, JSON details, IP, user agent, and timestamp.

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
pending → confirmed → preparing → ready_for_pickup → out_for_delivery → completed

Cancellation:
pending / confirmed / preparing / ready_for_pickup → cancelled
```

UI labels are exactly **Pending, Confirmed, Preparing, Ready for Pickup, Out for Delivery, Completed**, plus Cancelled. The status migration widens the enum, maps `ready` to `ready_for_pickup` and `delivered` to `completed` in orders/history, then narrows it. Its `down()` reverses the mapping.

Role constraints remain enforced by `OrderService::allowedTransitions()`: pickup completion belongs to cashier/admin workflow; delivery cannot leave Ready for Pickup without an assigned rider; only the assigned rider can move the delivery to Out for Delivery and Completed. Payment becomes paid only at Completed.

## Transaction and inventory rules

Writes involving orders, payments, stock, notifications, assignments, or cancellation use explicit database transactions. Affected product/order rows are locked with `SELECT ... FOR UPDATE` before authoritative changes. Client totals, status values, prices, and stock are not trusted.

`InventoryService` is the only application component allowed to mutate `products.stock`:

- `deductForOrder()` → `sale`
- `restockForCancelledOrder()` → `cancel_restock`
- `stockIn()` → `stock_in`
- `adjust()` → `adjustment` and requires a reason
- `recordWaste()` → `waste`

Existing stock receives an `opening` movement during migration. Product edits do not post or overwrite stock. Initial stock is accepted only when creating a product and is ledgered as opening stock. Low-stock/out-of-stock notifications are created when a balance reaches `reorder_level` or zero.

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

## Panel compliance summary

| # | Panel comment | Primary changed areas | Demonstration |
|---|---|---|---|
| 1 | Stronger security, roles, sessions, customer data | Password rule; AuthService/AuthFilter; security config; AuditLogService; admin/rider/customer controllers/views | Try weak password and 5 failed logins; inspect secure production config; complete a delivery and confirm rider PII masks; reveal admin customer phone and inspect audit log |
| 2 | Barcode scanning | Product schema/model/controller, staff barcode API, POS, Inventory, printable labels | Scan/type a seeded barcode in POS and Inventory; print selected labels |
| 3 | QR codes | OrderQrService, public menu controller/view, staff verification, label/order/rider views | Scan product QR to public page; scan order QR as staff; alter token and confirm rejection |
| 4 | Nutrition facts | Product migration/model/forms/API/menu/public views/seeder | Edit nutrition in Admin and view/filter it from customer/public menu |
| 5 | Clear order statuses | OrderStatus enum, status migration, OrderService, helper/views/controllers/tests | Move pickup and delivery orders through the exact panel status labels and view tracker |
| 6 | Customer order history | Customer OrderController/views and reorder API/cart handling | Filter by status/date/type/order number, open details, use Reorder |
| 7 | Status notifications | Notification migration/model/service/API/layout/app polling/settings | Change order status and observe customer bell/toast within polling interval |
| 8 | Reports | ReportService, Admin ReportController/view, local chart script, dashboard summaries | Select a date range across all five tabs, compare paid totals, export CSV/print |
| 9 | Sales reflected in inventory | Inventory migration/service/controller/views, OrderService, reconciliation command | Create/cancel an order and stock-in/adjust/waste; inspect ledger; run reconciliation |
