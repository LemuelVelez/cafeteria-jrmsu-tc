# JRMSU-TC Cafeteria

Responsive food ordering, point-of-sale, delivery, promotion, review, and administration system for JRMSU-TC, Tampilisan.

## Stack

- PHP 8.2+
- CodeIgniter 4.7
- MySQL 8 / MySQLi
- Bootstrap 5.3
- Vanilla JavaScript
- Node.js 18+ frontend development gateway

## Architecture

This repository is a single CodeIgniter MVC monorepo:

- `app/Controllers` handles web and JSON requests.
- `app/Models` owns data access and validation.
- `app/Services` contains reusable authentication, order, and promotion business rules.
- `app/Enums` centralizes order and payment modes.
- `app/Views` contains role-specific responsive Bootstrap interfaces.
- `app/Filters` enforces authentication, account state, and roles.
- `app/Database/Migrations` defines and upgrades the schema.
- `frontend` contains the development gateway that starts after the backend.
- `public` is the only web-accessible application directory.

## Requirements

Enable PHP extensions `intl`, `mbstring`, `json`, `mysqlnd`, and `fileinfo`. Install Composer, MySQL 8, and Node.js 18 or later.

## Setup

From the project root:

```bash
chmod +x cafe
./cafe setup
```

`cafe setup` installs Composer dependencies, creates `.env` from `.env.example` when needed, and installs the `cafe` command into `~/.local/bin`. Add that directory to `PATH` when your shell does not already include it.

Create the database and account:

```sql
CREATE DATABASE jrmsu_tc_cafeteria
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

CREATE USER 'jrmsu_user'@'localhost' IDENTIFIED BY 'change_me';
GRANT ALL PRIVILEGES ON jrmsu_tc_cafeteria.* TO 'jrmsu_user'@'localhost';
FLUSH PRIVILEGES;
```

Update `MySQL_Database_URL` and `encryption.key` in `.env`, then run:

```bash
cafe db migrate
cafe db seed
```

## One-command development server

```bash
cafe run dev
```

The command starts services in this order:

1. CodeIgniter backend at `http://127.0.0.1:8080`.
2. Frontend development gateway at `http://127.0.0.1:5173` after the backend is reachable.

Open `http://127.0.0.1:5173`. Press `Ctrl+C` once to stop both processes.

Optional port overrides:

```bash
BACKEND_PORT=8081 FRONTEND_PORT=5174 cafe run dev
```

## Cafe commands

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

## Fulfillment and payment modes

The application supports two fulfillment/payment combinations:

| Order type | Enforced payment mode |
|---|---|
| Pickup | Cash on Pickup |
| Delivery | Cash on Delivery |

The server derives the payment mode from the order type and rejects mismatched requests. Payment is marked paid automatically when the order reaches `delivered`.

## Seeded accounts

All starter accounts use `Password123!`. Change these immediately outside local development.

| Role | Email |
|---|---|
| Admin | `admin@jrmsu.edu.ph` |
| Cashier | `cashier@jrmsu.edu.ph` |
| Rider | `rider@jrmsu.edu.ph` |
| Customer | `customer@jrmsu.edu.ph` |

## Main workflows

- Customers browse products, manage a browser cart, apply promos, place pickup or delivery orders, track orders, and review delivered orders.
- Cashiers create pickup or delivery POS orders and update preparation or pickup-completion statuses.
- Riders view only assigned deliveries, collect Cash on Delivery, and update `out_for_delivery` and `delivered` statuses.
- Administrators manage products, categories, users, riders, promotions, orders, reports, rider assignments, and settings.

## Security

The application uses password hashing, CSRF protection, role filters, active-account checks, session regeneration, server-side cart price verification, transactional stock updates, controlled order transitions, server-enforced payment modes, randomized upload names, MIME/image validation, output escaping, and login throttling.

For production, point the web root to `public/`, set `CI_ENVIRONMENT = production`, enable HTTPS, replace all sample credentials, and keep `.env` outside version control.
# cafeteria-jrmsu-tc
# cafeteria-jrmsu-tc
# cafeteria-jrmsu-tc
