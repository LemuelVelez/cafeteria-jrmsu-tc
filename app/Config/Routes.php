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
$routes->group('', ['filter' => 'guest'], static function (RouteCollection $routes): void {
    $routes->get('login', 'Auth\\LoginController::index');
    $routes->post('login', 'Auth\\LoginController::store');
    $routes->get('register', 'Auth\\RegisterController::index');
    $routes->post('register', 'Auth\\RegisterController::store');
});
$routes->post('logout', 'Auth\\LogoutController::__invoke', ['filter' => 'auth']);

$routes->group('settings', ['filter' => ['auth', 'active']], static function (RouteCollection $routes): void {
    $routes->get('', 'Account\\SettingController::index');
    $routes->post('profile', 'Account\\SettingController::saveProfile');
    $routes->post('password', 'Account\\SettingController::savePassword');
    $routes->post('avatar/remove', 'Account\\SettingController::removeAvatar');
});

$routes->group('admin', ['filter' => ['auth', 'active', 'role:admin']], static function (RouteCollection $routes): void {
    $routes->get('dashboard', 'Admin\\DashboardController::index');
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
    $routes->get('reviews', 'Customer\\ReviewController::index');
    $routes->post('reviews', 'Customer\\ReviewController::save');
});

$routes->group('rider', ['filter' => ['auth', 'active', 'role:rider']], static function (RouteCollection $routes): void {
    $routes->get('dashboard', 'Rider\\DashboardController::index');
    $routes->get('deliveries', 'Rider\\DeliveryController::index');
    $routes->get('deliveries/(:num)', 'Rider\\DeliveryController::show/$1');
    $routes->post('deliveries/(:num)/status', 'Rider\\DeliveryController::status/$1');
});

$routes->group('api', ['filter' => ['auth', 'active']], static function (RouteCollection $routes): void {
    $routes->get('products', 'Api\\ProductApiController::index');
    $routes->post('orders', 'Api\\OrderApiController::create');
    $routes->get('orders/pending-count', 'Api\\OrderApiController::pendingCount', ['filter' => 'role:admin,cashier']);
    $routes->get('orders/(:num)', 'Api\\OrderApiController::show/$1');
    $routes->patch('orders/(:num)/status', 'Api\\OrderApiController::status/$1', ['filter' => 'role:admin,cashier,rider']);
    $routes->patch('orders/(:num)/assign-rider', 'Api\\OrderApiController::assignRider/$1', ['filter' => 'role:admin']);
    $routes->post('promos/apply', 'Api\\PromoApiController::apply');
    $routes->post('reviews', 'Api\\ReviewApiController::create', ['filter' => 'role:customer']);
    $routes->post('products', 'Api\\ProductApiController::save', ['filter' => 'role:admin']);
    $routes->put('products/(:num)', 'Api\\ProductApiController::save/$1', ['filter' => 'role:admin']);
    $routes->delete('products/(:num)', 'Api\\ProductApiController::delete/$1', ['filter' => 'role:admin']);
    $routes->patch('users/(:num)/status', 'Api\\UserApiController::status/$1', ['filter' => 'role:admin']);
});
