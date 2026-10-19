<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->setDefaultNamespace('App\\Controllers');
$routes->setDefaultController('Home');
$routes->setDefaultMethod('index');
$routes->setTranslateURIDashes(false);
$routes->set404Override();
$routes->setAutoRoute(false);

$routes->get('/', 'Home::index');
$routes->get('menu', 'PublicSite\MenuController::index');
$routes->get('privacy', 'PublicSite\PrivacyController::index');
$routes->get('menu/(:segment)', 'PublicSite\MenuController::show/$1');
$routes->group('', ['filter' => 'guest'], static function (RouteCollection $routes): void {
    $routes->get('login', 'Auth\\LoginController::index');
    $routes->post('login', 'Auth\\LoginController::store');
    $routes->get('register', 'Auth\\RegisterController::index');
    $routes->post('register', 'Auth\\RegisterController::store');
    $routes->get('email-verification', 'Auth\\EmailVerificationController::index');
    $routes->post('email-verification/resend', 'Auth\\EmailVerificationController::resend');
    $routes->get('verify-email', 'Auth\\EmailVerificationController::verify');
    $routes->get('forgot-password', 'Auth\\ForgotPasswordController::index');
    $routes->post('forgot-password', 'Auth\\ForgotPasswordController::store');
    $routes->get('reset-password', 'Auth\\ResetPasswordController::index');
    $routes->post('reset-password', 'Auth\\ResetPasswordController::store');
});
$routes->get('two-factor', 'Auth\TotpController::challenge', ['filter'=>'auth']);
$routes->post('two-factor', 'Auth\TotpController::confirmChallenge', ['filter'=>'auth']);
$routes->post('logout', 'Auth\\LogoutController::__invoke', ['filter' => 'auth']);

$routes->get('notifications', 'NotificationController::index', ['filter' => ['auth', 'active']]);

$routes->group('settings', ['filter' => ['auth', 'active']], static function (RouteCollection $routes): void {
    $routes->get('', 'Account\\SettingController::index');
    $routes->post('profile', 'Account\\SettingController::saveProfile');
    $routes->post('password', 'Account\\SettingController::savePassword');
    $routes->post('totp/start', 'Auth\TotpController::start');
    $routes->post('totp/confirm', 'Auth\TotpController::confirmSetup');
    $routes->post('notifications/preferences', 'Account\SettingController::notificationPreferences');
    $routes->post('sessions/logout-others', 'Account\SettingController::logoutOthers');
    $routes->get('data/export', 'Account\PrivacyController::export', ['filter' => 'role:customer']);
    $routes->post('data/delete-request', 'Account\PrivacyController::requestDeletion', ['filter' => 'role:customer']);
    $routes->post('avatar/remove', 'Account\\SettingController::removeAvatar');
});

$routes->group('admin', ['filter' => ['auth', 'active', 'role:admin']], static function (RouteCollection $routes): void {
    $routes->get('dashboard', 'Admin\\DashboardController::index');
    $routes->get('products/labels', 'Admin\ProductController::labels');
    $routes->get('inventory', 'Admin\InventoryController::index');
    $routes->get('inventory/stock-card', 'Admin\InventoryController::stockCard');
    $routes->get('inventory/stock-card/export', 'Admin\InventoryController::stockCardExport');
    $routes->get('inventory/stock-card/print', 'Admin\InventoryController::stockCardPrint');
    $routes->post('inventory/stock-in', 'Admin\InventoryController::stockIn');
    $routes->post('inventory/stock-out', 'Admin\InventoryController::stockOut');
    $routes->post('inventory/count', 'Admin\InventoryController::count');
    $routes->post('inventory/count/batch', 'Admin\InventoryController::batchCount');
    $routes->post('inventory/adjust', 'Admin\InventoryController::adjust');
    $routes->post('inventory/waste', 'Admin\InventoryController::waste');
    $routes->post('inventory/reconcile', 'Admin\InventoryController::reconcile');
    $routes->get('audit-logs', 'Admin\AuditLogController::index');
    $routes->get('reports/export/(:segment)', 'Admin\ReportController::export/$1');
    $routes->post('customers/(:num)/reveal-phone', 'Admin\CustomerController::revealPhone/$1');
    $routes->get('costing', 'Admin\CostingController::index');
    $routes->get('costing/nutrition-search', 'Admin\NutritionLookupController::index');
    $routes->post('costing/nutrition-import', 'Admin\NutritionLookupController::import');
    $routes->get('menu/poster', 'Admin\MenuPosterController::index');
    $routes->post('costing/ingredient', 'Admin\CostingController::saveIngredient');
    $routes->post('costing/recipe', 'Admin\CostingController::saveRecipe');
    $routes->post('costing/ingredient/stock-in', 'Admin\CostingController::stockIngredient');
    $routes->post('costing/verify/(:num)', 'Admin\CostingController::verify/$1');
    $routes->get('backups', 'Admin\BackupController::index');
    $routes->get('privacy/deletion-requests', 'Admin\PrivacyController::index');
    $routes->post('privacy/deletion-requests/(:num)/approve', 'Admin\PrivacyController::approve/$1');
    $routes->post('backups', 'Admin\BackupController::create');
    $routes->get('backups/(:segment)', 'Admin\BackupController::download/$1');
    $routes->get('products', 'Admin\\ProductController::index');
    $routes->post('products', 'Admin\\ProductController::save');
    $routes->post('products/(:num)', 'Admin\\ProductController::save/$1');
    $routes->post('products/(:num)/delete', 'Admin\\ProductController::delete/$1');
    $routes->get('categories', 'Admin\\CategoryController::index');
    $routes->post('categories', 'Admin\\CategoryController::save');
    $routes->post('categories/(:num)', 'Admin\\CategoryController::save/$1');
    $routes->post('categories/(:num)/delete', 'Admin\\CategoryController::delete/$1');
    $routes->get('orders', 'Admin\\OrderController::index');
    $routes->get('orders/(:num)', 'Admin\\OrderController::show/$1');
    $routes->post('orders/(:num)/status', 'Admin\\OrderController::status/$1');
    $routes->post('orders/(:num)/assign-rider', 'Admin\\OrderController::assignRider/$1');
    $routes->get('users', 'Admin\\UserController::index');
    $routes->post('users', 'Admin\\UserController::save');
    $routes->post('users/(:num)/status', 'Admin\\UserController::status/$1');
    $routes->get('customers', 'Admin\\CustomerController::index');
    $routes->post('customers/(:num)/status', 'Admin\\CustomerController::status/$1');
    $routes->get('riders', 'Admin\\RiderController::index');
    $routes->post('riders', 'Admin\\RiderController::save');
    $routes->post('riders/(:num)/status', 'Admin\\RiderController::status/$1');
    $routes->get('promos', 'Admin\\PromoController::index');
    $routes->post('promos', 'Admin\\PromoController::save');
    $routes->post('promos/(:num)', 'Admin\\PromoController::save/$1');
    $routes->get('reports', 'Admin\\ReportController::index');
    $routes->get('settings', 'Admin\\SettingController::index');
    $routes->post('settings', 'Admin\\SettingController::save');
});

$routes->group('cashier', ['filter' => ['auth', 'active', 'role:cashier']], static function (RouteCollection $routes): void {
    $routes->get('dashboard', 'Cashier\\DashboardController::index');
    $routes->get('inventory', 'Admin\InventoryController::index');
    $routes->get('inventory/stock-card', 'Admin\InventoryController::stockCard');
    $routes->get('inventory/stock-card/export', 'Admin\InventoryController::stockCardExport');
    $routes->get('inventory/stock-card/print', 'Admin\InventoryController::stockCardPrint');
    $routes->get('pos', 'Cashier\\PosController::index');
    $routes->get('orders', 'Cashier\\OrderController::index');
    $routes->post('orders/(:num)/status', 'Cashier\\OrderController::status/$1');
});

$routes->group('customer', ['filter' => ['auth', 'active', 'role:customer']], static function (RouteCollection $routes): void {
    $routes->get('dashboard', 'Customer\\DashboardController::index');
    $routes->get('menu', 'Customer\\MenuController::index');
    $routes->get('cart', 'Customer\\CartController::index');
    $routes->get('checkout', 'Customer\\CheckoutController::index');
    $routes->get('orders', 'Customer\\OrderController::index');
    $routes->get('orders/(:num)', 'Customer\\OrderController::show/$1');
    $routes->get('orders/(:num)/receipt', 'Customer\\ReceiptController::show/$1');
    $routes->post('orders/(:num)/issue', 'Customer\\ReceiptController::issue/$1');
    $routes->get('reviews', 'Customer\\ReviewController::index');
    $routes->post('reviews', 'Customer\\ReviewController::save');
});

$routes->group('rider', ['filter' => ['auth', 'active', 'role:rider']], static function (RouteCollection $routes): void {
    $routes->get('dashboard', 'Rider\\DashboardController::index');
    $routes->get('deliveries', 'Rider\\DeliveryController::index');
    $routes->get('deliveries/(:num)', 'Rider\\DeliveryController::show/$1');
    $routes->post('deliveries/(:num)/status', 'Rider\\DeliveryController::status/$1');
});


$routes->group('staff', ['filter' => ['auth', 'active', 'role:admin,cashier,rider']], static function (RouteCollection $routes): void {
    $routes->post('orders/(:num)/prepared/(:num)', 'Staff\PreparationController::check/$1/$2');
    $routes->get('orders/(:num)/ticket', 'Staff\PreparationController::ticket/$1');
    $routes->post('orders/(:num)/verify-handoff', 'Staff\PreparationController::handoff/$1');
    $routes->get('orders/verify/(:segment)', 'Staff\OrderVerifyController::show/$1');
});

$routes->group('api', ['filter' => ['auth', 'active', 'role:admin,cashier,rider,customer']], static function (RouteCollection $routes): void {
    $routes->get('notifications', 'Api\NotificationApiController::index');
    $routes->get('push/config', 'Api\WebPushController::config');
    $routes->post('push/subscribe', 'Api\WebPushController::subscribe');
    $routes->post('push/unsubscribe', 'Api\WebPushController::unsubscribe');
    $routes->get('notifications/unread-count', 'Api\NotificationApiController::unreadCount');
    $routes->patch('notifications/(:num)/read', 'Api\NotificationApiController::read/$1');
    $routes->patch('notifications/read-all', 'Api\NotificationApiController::readAll');
    $routes->get('products/barcode/(:segment)', 'Api\ProductApiController::barcode/$1', ['filter' => 'role:admin,cashier']);
    $routes->post('orders/(:num)/reorder', 'Api\OrderApiController::reorder/$1', ['filter' => 'role:customer']);
    $routes->get('products', 'Api\\ProductApiController::index', ['filter' => 'role:admin,cashier,customer']);
    $routes->post('orders', 'Api\\OrderApiController::create', ['filter' => 'role:customer,cashier']);
    $routes->get('orders/pending-count', 'Api\\OrderApiController::pendingCount', ['filter' => 'role:admin,cashier']);
    $routes->get('orders/(:num)', 'Api\\OrderApiController::show/$1', ['filter' => 'role:admin,cashier,rider,customer']);
    $routes->patch('orders/(:num)/status', 'Api\\OrderApiController::status/$1', ['filter' => 'role:admin,cashier,rider']);
    $routes->patch('orders/(:num)/assign-rider', 'Api\\OrderApiController::assignRider/$1', ['filter' => 'role:admin']);
    $routes->post('promos/apply', 'Api\\PromoApiController::apply', ['filter' => 'role:customer,cashier']);
    $routes->post('reviews', 'Api\\ReviewApiController::create', ['filter' => 'role:customer']);
    $routes->post('products', 'Api\\ProductApiController::save', ['filter' => 'role:admin']);
    $routes->put('products/(:num)', 'Api\\ProductApiController::save/$1', ['filter' => 'role:admin']);
    $routes->delete('products/(:num)', 'Api\\ProductApiController::delete/$1', ['filter' => 'role:admin']);
    $routes->patch('users/(:num)/status', 'Api\\UserApiController::status/$1', ['filter' => 'role:admin']);
});

$routes->group('attendance', ['filter'=>['auth','active','role:admin,cashier,rider','attendance']], static function (RouteCollection $routes): void {
    $routes->get('', 'Attendance\AttendanceController::index');
    $routes->post('punch', 'Attendance\AttendanceController::punch');
});
$routes->group('admin/attendance', ['filter'=>['auth','active','role:admin','attendance']], static function (RouteCollection $routes): void {
    $routes->get('', 'Attendance\AttendanceController::index');
    $routes->post('shift', 'Attendance\AttendanceController::shift');
    $routes->post('rate', 'Attendance\AttendanceController::rate');
    $routes->post('correct/(:num)', 'Attendance\AttendanceController::correct/$1');
    $routes->get('report', 'Attendance\AttendanceController::report');
});
