# JRMSU-TC Cafeteria System Documentation

**Version 1.1.0 · CodeIgniter 4 + Bootstrap 5 + MySQL · Asia/Manila**

## Overview

JRMSU-TC Cafeteria is a role-based campus dining platform with public menu discovery, customer pickup and delivery ordering, cashier POS, rider delivery management, and administrator operations. The interface is responsive from mobile phones through desktop workstations.

## Roles

| Role | Access |
|---|---|
| Admin | Dashboard, products, categories, orders, customers, riders, promos, reports, and settings |
| Cashier | Dashboard, point of sale, and preparation/pickup order updates |
| Rider | Assigned delivery list, customer delivery details, cash collection, and delivery status updates |
| Customer | Dashboard, menu, cart, checkout, order tracking, and reviews |

## MVC monorepo structure

```text
app/
├── Config/
├── Controllers/
├── Database/
├── Enums/
├── Filters/
├── Helpers/
├── Models/
├── Services/
└── Views/
frontend/
└── dev-server.mjs
public/
├── assets/
├── uploads/products/
└── index.php
cafe
package.json
composer.json
```

Controllers coordinate requests, models validate and persist data, services centralize reusable business rules, enums provide one source of truth for order/payment modes, views render Bootstrap interfaces, and filters protect routes.

## Development command runner

Run initial project setup:

```bash
chmod +x cafe
./cafe setup
```

Run the full development stack with one command:

```bash
cafe run dev
```

Startup order is guaranteed:

1. The CodeIgniter backend starts on port `8080`.
2. The runner waits until the backend accepts connections.
3. The Node frontend gateway starts on port `5173` and proxies requests to the backend.
4. One `Ctrl+C` stops both processes.

Relevant commands:

```text
cafe setup
cafe run dev
cafe run backend
cafe run frontend
cafe db migrate
cafe db seed
cafe db refresh
cafe test
cafe lint
cafe install-command
cafe help
```

## Core tables

- `users`
- `categories`
- `products`
- `product_addons`
- `promos`
- `orders`
- `order_items`
- `reviews`
- `settings`
- `order_status_history`
- `promo_usages`
- `payments`

All application tables use InnoDB, foreign keys, indexes, and `utf8mb4`.

## Fulfillment and payment rules

| Order type | Payment method | Collection point |
|---|---|---|
| `pickup` | `cash_on_pickup` | When the customer receives the order at the cafeteria |
| `delivery` | `cash_on_delivery` | When the rider delivers the order |

`OrderType` and `PaymentMethod` enums are the reusable source of truth. The backend derives the required payment method from the submitted order type and rejects mismatched requests. When an order changes to `delivered`, both `orders.payment_status` and its payment record are marked `paid` transactionally.

## Order workflow

```text
pending → confirmed → preparing → ready
                              ├─→ delivered (pickup)
                              └─→ out_for_delivery → delivered (delivery)
pending/confirmed/preparing/ready → cancelled
```

The backend validates every transition. A rider can change only an assigned delivery. A delivery cannot move to `out_for_delivery` without an active rider assignment. Cashiers cannot bypass the rider workflow.

## API endpoints

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/api/products` | Available products |
| POST | `/api/orders` | Create a validated pickup or delivery order |
| GET | `/api/orders/{id}` | Authorized order details |
| GET | `/api/orders/pending-count` | Admin/cashier pending count |
| PATCH | `/api/orders/{id}/status` | Authorized status update |
| PATCH | `/api/orders/{id}/assign-rider` | Admin rider assignment |
| POST | `/api/promos/apply` | Promotion validation |
| POST | `/api/reviews` | Customer review creation |
| POST/PUT/DELETE | `/api/products` | Admin product management |
| PATCH | `/api/users/{id}/status` | Admin account state update |

JSON responses use `success`, `message`, `data`, and `errors` fields.

## Environment

The MySQL connection is read from:

```dotenv
MySQL_Database_URL = 'MySQLi://jrmsu_user:change_me@127.0.0.1:3306/jrmsu_tc_cafeteria?charset=utf8mb4&DBCollat=utf8mb4_unicode_ci'
```

Development command overrides include `BACKEND_HOST`, `BACKEND_PORT`, `FRONTEND_HOST`, `FRONTEND_PORT`, `BACKEND_URL`, and `APP_BASE_URL`. Other environment keys control the cafeteria name, currency, timezone, delivery fee, order prefix, session cookie, and upload size.

## Responsive design

- Desktop role sidebar and compact mobile off-canvas navigation
- Responsive Bootstrap grids, tables, cards, forms, and modals
- Touch-friendly controls and sticky cart/POS summaries
- Mobile-safe pickup and delivery checkout forms
- Automatically synchronized payment labels
- Shared components and layouts following DRY principles

## Security controls

- `password_hash()` and `password_verify()`
- CSRF protection on forms and JSON mutations
- Route-level authentication and role filters
- Active-account verification
- Escaped view output
- Query Builder/models and parameterized locking query
- Transactional order creation, stock decrement, status, and cash settlement updates
- Server-authoritative products, prices, add-ons, totals, promotions, and payment methods
- Image MIME, dimensions, size, and generated-filename validation
- Login and registration throttling
- Session ID regeneration after authentication
- Non-public `.env`, logs, sessions, and writable files

## Deployment

1. Install dependencies with Composer.
2. Configure `.env` with production URL, encryption key, and MySQL URL.
3. Run migrations and the initial seeder.
4. Point the server document root to `public/`.
5. Set writable permissions only for required `writable/` and upload folders.
6. Enable HTTPS and set `CI_ENVIRONMENT = production`.
7. Replace seeded passwords and remove development credentials.
8. Configure backups, log rotation, and monitoring.
