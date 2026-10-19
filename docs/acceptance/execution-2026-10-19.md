# Acceptance execution checkpoint — 2026-10-19

**Status: Partial; this is not acceptance verification for all 17 corrections.**

The v14 patch was applied over the original `Cafeteria.zip` source tree and the following commands were actually executed in this environment (PHP 8.4.24 CLI and Node.js 22.16.0). Do not conflate this environment with the user's own MySQL installation.

| Command | Exit | Evidence and interpretation |
|---|---:|---|
| `npm run check` | 0 | Node syntax checks and barcode fallback tests pass: `Barcode fallback: valid and tampered Code 128, UPC-A EAN encoding all pass.` |
| `./cafe lint` | 0 | No PHP syntax errors, including the new stock-card validation test. |
| `vendor/bin/phpunit` | 127 | `vendor/bin/phpunit: No such file or directory`. PHPUnit did **not** execute. |
| `php spark inventory:reconcile` | 255 | Fatal: missing `vendor/codeigniter4/framework/system/Boot.php`. Reconciliation did **not** execute. |
| `php spark barcode:audit` | 255 | Same missing CodeIgniter bootstrap. Database lookup audit did **not** execute. |
| `php spark nutrition:catalog-audit` | 255 | Same missing CodeIgniter bootstrap. Catalog audit command did **not** execute. |
| Standalone PHP smoke assertions | 0 | 33 assertions pass for eight valid and eight invalid demo barcodes, signed QR scope and expiry, seeding policy, nutrient arithmetic and stock-card input validation. These are **not** PHPUnit or database-backed tests. |

## Environment blockers

* `composer` is not installed, the original archive lacks `vendor/`, and network DNS cannot resolve the Composer package repository. Dependencies cannot be installed here.
* The PHP CLI has no `mysqli`, `pdo_mysql` or `mbstring` extension; no local MySQL/MariaDB server or client is available.
* This environment cannot connect to the user's computer or its real database. The user's shared CLI output did show 38 applied migrations and successful **core** seeding, but the two nutrition seeders remained deferred, with 0/8 sourced demo recipes.
* Nutrition numbers, actual food costs, recipe portions, adviser approval and physical device checks cannot be fabricated or declared complete.

## Code correction made in this checkpoint

`StockCardService::get()` previously accepted malformed calendar dates and invalid movement types for an empty stock card, while `exportRows()` rejected them. The service now shares calendar/date-range validation and validates the type before its empty-card early return. New `StockCardRequestValidationTest` checks bad dates and types, export consistency, and the empty-card case. PHP-only smoke checks of these paths passed; database-backed stock-card pagination still needs PHPUnit/MySQL.

## Next commands on the existing MySQL-capable machine

Run from the complete local repository with `vendor/` installed and a separate `_test` database configured:

```bash
composer install
vendor/bin/phpunit
php spark inventory:reconcile
php spark barcode:audit
php spark nutrition:catalog-audit
npm run check
./cafe lint
```

Inspect and fix genuine failing tests without marking skips as passes. Nutrition source and measured recipe requirements must be fulfilled before strict seeding and correction #5 can be Verified. Finally, run rollback/backup drills and manual scanner, push and printed-report checks on real devices; record their evidence.
