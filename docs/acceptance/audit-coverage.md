# Mutation route audit coverage (unverified)

All POST/PATCH/PUT/DELETE routes in `app/Config/Routes.php` are listed below. Events are intended
actions from controllers/services (or a documented read-only exception); they **do not**
constitute passing proof. Every route still requires a MySQL-backed feature test asserting
exactly one appropriate audit record and `AuditLogService::scrub()` PII removal.

| Method | Path | Intended audit event / exception | Acceptance test |
|---|---|---|---|
| POST | `/login` | login_success/login_failure | **Not run (MySQL unavailable)** |
| POST | `/register` | account_created | **Not run (MySQL unavailable)** |
| POST | `/email-verification/resend` | `token_issue` (verification only), `AuthenticationTokenAuditDatabaseTest::testVerificationResendInvalidatesPreviousLinkAndAuditsMetadataOnly` | **Written; MySQL not run** |
| POST | `/forgot-password` | `token_issue` (password reset only), `AuthenticationTokenAuditDatabaseTest::testPasswordResetIssuanceAndConsumptionAreAuditedOnceEach` | **Written; MySQL not run** |
| POST | `/reset-password` | password_change | **Not run (MySQL unavailable)** |
| POST | `/two-factor` | two_factor_success | **Not run (MySQL unavailable)** |
| POST | `/logout` | logout | **Not run (MySQL unavailable)** |
| POST | `/settings/profile` | profile_updated | **Not run (MySQL unavailable)** |
| POST | `/settings/password` | password_change | **Not run (MySQL unavailable)** |
| POST | `/settings/totp/start` | two_factor_setup | **Not run (MySQL unavailable)** |
| POST | `/settings/totp/confirm` | two_factor_enabled | **Not run (MySQL unavailable)** |
| POST | `/settings/notifications/preferences` | notification_preferences_updated | **Not run (MySQL unavailable)** |
| POST | `/settings/sessions/logout-others` | sessions_revoked | **Not run (MySQL unavailable)** |
| POST | `/settings/data/delete-request` | account_deletion_requested | **Not run (MySQL unavailable)** |
| POST | `/settings/avatar/remove` | avatar_removed | **Not run (MySQL unavailable)** |
| POST | `/admin/inventory/stock-in` | inventory_movement | **Not run (MySQL unavailable)** |
| POST | `/admin/inventory/stock-out` | inventory_movement | **Not run (MySQL unavailable)** |
| POST | `/admin/inventory/count` | inventory_movement | **Not run (MySQL unavailable)** |
| POST | `/admin/inventory/count/batch` | inventory_movement / inventory_count_session | **Not run (MySQL unavailable)** |
| POST | `/admin/inventory/adjust` | inventory_movement | **Not run (MySQL unavailable)** |
| POST | `/admin/inventory/waste` | inventory_movement | **Not run (MySQL unavailable)** |
| POST | `/admin/inventory/reconcile` | Read-only reconciliation; no state change | **Not run (MySQL unavailable)** |
| POST | `/admin/customers/(:num)/reveal-phone` | pii_reveal (read audited transactionally; no phone in audit) | **Not run (MySQL unavailable)** |
| POST | `/admin/costing/nutrition-import` | **Audit gap: inspect implementation** | **Not run (MySQL unavailable)** |
| POST | `/admin/costing/ingredient` | ingredient_saved | **Not run (MySQL unavailable)** |
| POST | `/admin/costing/recipe` | recipe_changed | **Not run (MySQL unavailable)** |
| POST | `/admin/costing/ingredient/stock-in` | ingredient_inventory_movement | **Not run (MySQL unavailable)** |
| POST | `/admin/costing/verify/(:num)` | **Audit gap: inspect implementation** | **Not run (MySQL unavailable)** |
| POST | `/admin/privacy/deletion-requests/(:num)/approve` | personal_data_deletion_approved (`CustomerDataDeletionService`; `CustomerPrivacyDatabaseTest`) | **Not run (MySQL unavailable)** |
| POST | `/admin/backups` | **Audit gap: inspect implementation** | **Not run (MySQL unavailable)** |
| POST | `/admin/products` | product_created / product_updated | **Not run (MySQL unavailable)** |
| POST | `/admin/products/(:num)` | **Audit gap: inspect implementation** | **Not run (MySQL unavailable)** |
| POST | `/admin/products/(:num)/delete` | **Audit gap: inspect implementation** | **Not run (MySQL unavailable)** |
| POST | `/admin/categories` | **Audit gap: inspect implementation** | **Not run (MySQL unavailable)** |
| POST | `/admin/categories/(:num)` | **Audit gap: inspect implementation** | **Not run (MySQL unavailable)** |
| POST | `/admin/categories/(:num)/delete` | **Audit gap: inspect implementation** | **Not run (MySQL unavailable)** |
| POST | `/admin/orders/(:num)/status` | **Audit gap: inspect implementation** | **Not run (MySQL unavailable)** |
| POST | `/admin/orders/(:num)/assign-rider` | **Audit gap: inspect implementation** | **Not run (MySQL unavailable)** |
| POST | `/admin/users` | **Audit gap: inspect implementation** | **Not run (MySQL unavailable)** |
| POST | `/admin/users/(:num)/status` | **Audit gap: inspect implementation** | **Not run (MySQL unavailable)** |
| POST | `/admin/customers/(:num)/status` | **Audit gap: inspect implementation** | **Not run (MySQL unavailable)** |
| POST | `/admin/riders` | **Audit gap: inspect implementation** | **Not run (MySQL unavailable)** |
| POST | `/admin/riders/(:num)/status` | **Audit gap: inspect implementation** | **Not run (MySQL unavailable)** |
| POST | `/admin/promos` | promo_saved | **Not run (MySQL unavailable)** |
| POST | `/admin/promos/(:num)` | **Audit gap: inspect implementation** | **Not run (MySQL unavailable)** |
| POST | `/admin/settings` | **Audit gap: inspect implementation** | **Not run (MySQL unavailable)** |
| POST | `/cashier/orders/(:num)/status` | **Audit gap: inspect implementation** | **Not run (MySQL unavailable)** |
| POST | `/customer/orders/(:num)/issue` | **Audit gap: inspect implementation** | **Not run (MySQL unavailable)** |
| POST | `/customer/reviews` | review_created | **Not run (MySQL unavailable)** |
| POST | `/rider/deliveries/(:num)/status` | **Audit gap: inspect implementation** | **Not run (MySQL unavailable)** |
| POST | `/staff/orders/(:num)/prepared/(:num)` | **Audit gap: inspect implementation** | **Not run (MySQL unavailable)** |
| POST | `/staff/orders/(:num)/verify-handoff` | **Audit gap: inspect implementation** | **Not run (MySQL unavailable)** |
| POST | `/api/push/subscribe` | `push_subscribed` (WebPushService) | **Not run (MySQL unavailable)** |
| POST | `/api/push/unsubscribe` | `push_unsubscribed` (WebPushService) | **Not run (MySQL unavailable)** |
| PATCH | `/api/notifications/(:num)/read` | No audit (owned read acknowledgment; low-risk, transactional) | **Not run (MySQL unavailable)** |
| PATCH | `/api/notifications/read-all` | No audit (notification-read acknowledgment; low risk) | **Not run (MySQL unavailable)** |
| POST | `/api/orders/(:num)/reorder` | **Audit gap: inspect implementation** | **Not run (MySQL unavailable)** |
| POST | `/api/orders` | order_created | **Not run (MySQL unavailable)** |
| PATCH | `/api/orders/(:num)/status` | **Audit gap: inspect implementation** | **Not run (MySQL unavailable)** |
| PATCH | `/api/orders/(:num)/assign-rider` | **Audit gap: inspect implementation** | **Not run (MySQL unavailable)** |
| POST | `/api/promos/apply` | No mutation; promo preview only | **Not run (MySQL unavailable)** |
| POST | `/api/reviews` | review_created | **Not run (MySQL unavailable)** |
| POST | `/api/products` | product_created / product_updated | **Not run (MySQL unavailable)** |
| PUT | `/api/products/(:num)` | **Audit gap: inspect implementation** | **Not run (MySQL unavailable)** |
| DELETE | `/api/products/(:num)` | **Audit gap: inspect implementation** | **Not run (MySQL unavailable)** |
| PATCH | `/api/users/(:num)/status` | **Audit gap: inspect implementation** | **Not run (MySQL unavailable)** |
| POST | `/attendance/punch` | **Audit gap: inspect implementation** | **Not run (MySQL unavailable)** |
| POST | `/admin/attendance/shift` | **Audit gap: inspect implementation** | **Not run (MySQL unavailable)** |
| POST | `/admin/attendance/rate` | **Audit gap: inspect implementation** | **Not run (MySQL unavailable)** |
| POST | `/admin/attendance/correct/(:num)` | **Audit gap: inspect implementation** | **Not run (MySQL unavailable)** |

Audit verification is **Blocked** until the real MySQL environment and CI4 test bootstrap are available.
`GET /settings/data/export` is also an audited privacy event despite being a GET: 
`CustomerDataExportService` emits `personal_data_export` in the export transaction;
`CustomerPrivacyDatabaseTest::testExportContainsOnlyOwnedOrdersAndAuditsOnce` checks it.
`CustomerPrivacyDatabaseTest::testApprovalScrubsPersonalFieldsButKeepsFinancialRecordsAndOneAudit`
checks deletion approval's single audit and financial retention. Both are MySQL-blocked.

Routes with audit gaps must be fixed and covered before correction #15 can be reported Verified.


### Continuation v7 (implementation only)

- `POST /settings/profile` → `profile_changed`: now a transactionally coupled,
  PII-redacted diff including avatar-only changes (`AuditPrivacyTest` confirms
  safe JSON scrubbing; HTTP/MySQL test remains **not run**).
- `POST /settings/avatar/remove` → `avatar_removed`: newly audited in the same
  transaction as removal; old media is deleted after commit; HTTP/MySQL test
  remains **not run**.
- All audit insertions fail closed on a database insert failure; freeform
  notes/reasons/descriptions and personal names are excluded from diff values.
  `tests/Unit/AuditPrivacyTest.php` is added but cannot run without Composer.

### Continuation v8

`WebPushService::flush()` additionally audits `push_dispatch_attempt` per
notification with sent/retired device counts and retry state. Its updates to
outbox, subscription deletion and successful delivery receipts are transactional.
The MySQL test `WebPushDispatchDatabaseTest` covers attempts, retries and expired
subscriptions, but **was not run** in this environment.

### Continuation v9 — token issuance, nutrition source gating, notification reads

- `POST /email-verification/resend` and `POST /forgot-password`: `token_issue` is persisted on the same MySQL transaction as the previous-token invalidation and new token creation; `purpose` only, with a NULL guest actor; no raw token, hash or email. `AuthenticationTokenAuditDatabaseTest` covers invalidation, verification consumption, password reset consumption, and missing-user rollback, but is **not run** without MySQL.
- `PATCH /api/notifications/(:num)/read` and `PATCH /api/notifications/read-all` intentionally emit no audit for low-risk owned read acknowledgments. Both now use atomic transactions.
- `AuditPrivacyTest` extends recursive PII scrub assertions for freeform messages, bodies, request payloads, endpoints and details (PHPUnit pending).
