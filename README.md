# JRMSU-TC Cafeteria

Responsive food ordering, point-of-sale, delivery, inventory, promotion, notification, and reporting system for JRMSU-TC, Tampilisan.

## Stack

PHP 8.2+, CodeIgniter 4.7, MySQL 8/MySQLi, Bootstrap 5.3, vanilla JavaScript, and the Node.js 18+ development gateway. New browser libraries are stored locally under `public/assets/vendor/` so barcode, QR, and report functions work on a local network without runtime CDN dependencies.

## Setup

```bash
chmod +x cafe
./cafe setup
```

Create the MySQL database, update `MySQL_Database_URL` and `encryption.key` in `.env`, then run either the Spark commands directly or the `cafe` aliases:

```bash
php spark migrate
php spark seed

# equivalent aliases
cafe db migrate
cafe db seed
```

`php spark migrate` is project-wrapped to run CodeIgniter migrations with `--all`; migrations already present in CodeIgniter's migration history are shown as **SKIPPED**, and only pending migrations are applied. `php spark seed` first verifies migrations, then checks the `seeder_runs` registry. Already-recorded seeders are **SKIPPED**; on an upgraded database, existing seed data is detected once and adopted into the registry without rerunning that seeder. Only pending seeders execute. Both commands use colored/emoji progress and finish with explicit **No pending migrations** / **No pending seeders** results.

For a complete demo reset:

```bash
cafe db refresh
```

Run the development stack with:

```bash
cafe run dev
```

The backend starts on `http://127.0.0.1:8080` and the frontend gateway on `http://127.0.0.1:5173` by default.

## Commands

```text
cafe setup
cafe run dev
cafe run backend
cafe run frontend
php spark migrate
php spark seed
cafe db migrate
cafe db seed
cafe db refresh
cafe test
cafe lint
php spark inventory:reconcile
cafe install-command
cafe help
```

## Fulfillment, payment, and order statuses

| Order type | Enforced payment mode | Workflow |
|---|---|---|
| Pickup | Cash on Pickup | Pending → Confirmed → Preparing → Ready for Pickup → Completed |
| Delivery | Cash on Delivery | Pending → Confirmed → Preparing → Ready for Pickup → Out for Delivery → Completed |

Cancellation is allowed from Pending, Confirmed, Preparing, or Ready for Pickup. Payment is marked `paid` only when an order reaches `completed`; cancellation marks the payment failed where applicable. Prices, add-ons, promotions, stock, totals, and allowed transitions are always recalculated or validated on the server.

## Inventory

`InventoryService` is the only application service that changes `products.stock`. Every opening quantity, stock-in, sale, cancellation restock, adjustment, and waste transaction writes an `inventory_movements` ledger row. Product editing cannot overwrite current stock. Admins manage stock through **Inventory**; cashiers have a read-only inventory view. Run `php spark inventory:reconcile` to compare current stock with the ledger and completed-order quantities.

The default delivery fee is `40.00`. Precedence is: the `CAFETERIA_DELIVERY_FEE` environment value, then the `Config\Cafeteria` fallback of `40.00`; the settings seeder also initializes the stored delivery fee to `40.00` for a fresh database.

## Barcode and QR workflows

Products can have unique SKU and barcode values. Cashiers can keep the POS scan field focused and scan USB/Bluetooth barcodes to add products. Admin inventory stock-in/adjustment/waste forms can select a product by scanning. Printable labels include product name, price, barcode, and a QR code linking to the public product-information page.

Customer order details contain an HMAC-signed verification QR. Staff scans open `/staff/orders/verify/{token}`; sequential order IDs alone are not accepted and tampered signatures are rejected. Riders can also scan the verification QR before hand-off.

## Nutrition and customer history

Products support serving size, calories, protein, carbohydrates, fat, sugar, fiber, sodium, allergens, and an optional healthy-choice tag. Customers can filter menu items under 500 kcal or exclude a selected allergen and can see nutrition details before ordering.

Customer order history supports keyword, status, type, and date filters with pagination. It includes order totals, payment information, status timeline, signed QR, delivery details, and reorder. Reorder rechecks current product availability, prices, add-ons, and stock and reports skipped items.

## Notifications and reports

All roles receive in-app notifications through the notification bell. Order, assignment, cancellation, and inventory alerts are written transactionally with the related business operation. Customer status notifications poll while the page is visible; selected order statuses can also be emailed when `email_order_notifications` is enabled.

Admin reports include paid-only sales, orders, inventory movements, popular/slow food items and add-ons, and delivery transactions. Reports support date ranges, CSV export, print, and local charts. Paid sales are recognized only when `payment_status = 'paid'`.

## Seeded accounts

All starter accounts use `Cafeteria#2026Demo`. Change this immediately outside local development.

| Role | Email |
|---|---|
| Admin | `admin@jrmsu.edu.ph` |
| Cashier | `cashier@jrmsu.edu.ph` |
| Rider | `rider@jrmsu.edu.ph` |
| Customer | `customer@jrmsu.edu.ph` |

## Security

Passwords require at least 10 characters with uppercase, lowercase, number, and symbol characters; common passwords and passwords containing the user's name/email username are rejected. Five consecutive failed logins lock the account for 15 minutes. Login responses stay generic.

Authenticated sessions include an account `session_version` and idle timeout. Admin/cashier/rider default to 30 minutes and customer to two hours. Password, role, and account-state changes invalidate old authenticated sessions. Rider customer phone/address data is visible only while a delivery is Ready for Pickup or Out for Delivery; completed/cancelled details are masked. Admin PII reveals and other sensitive actions are written to `audit_logs`.

Production security is environment-driven. Recommended production values are:

```dotenv
CI_ENVIRONMENT = production
app.forceGlobalSecureRequests = true
app.cookieSecure = true
app.CSPEnabled = true
CAFETERIA_IDLE_TIMEOUT = 1800
CAFETERIA_IDLE_TIMEOUT_ADMIN = 1800
CAFETERIA_IDLE_TIMEOUT_CASHIER = 1800
CAFETERIA_IDLE_TIMEOUT_RIDER = 1800
CAFETERIA_IDLE_TIMEOUT_CUSTOMER = 7200
CAFETERIA_DELIVERY_FEE = 40.00
```

Keep the web root pointed to `public/`, use HTTPS, replace demo credentials, and keep `.env` outside version control.
