<?php
$user = $currentUser ?? session()->get('user');
$role = $user['role'] ?? 'customer';
$path = service('uri')->getPath();
$menus = [
    'admin' => [
        ['/admin/dashboard', 'bi-grid-1x2', 'Dashboard'], ['/admin/products', 'bi-cup-hot', 'Products'], ['/admin/categories', 'bi-tags', 'Categories'], ['/admin/orders', 'bi-receipt', 'Orders'], ['/admin/users', 'bi-person-gear', 'Users'], ['/admin/customers', 'bi-people', 'Customers'], ['/admin/riders', 'bi-bicycle', 'Riders'], ['/admin/promos', 'bi-ticket-perforated', 'Promotions'], ['/admin/reports', 'bi-bar-chart', 'Reports'], ['/admin/settings', 'bi-sliders', 'Cafeteria settings'], ['/settings', 'bi-person-circle', 'My settings'],
    ],
    'cashier' => [
        ['/cashier/dashboard', 'bi-grid-1x2', 'Dashboard'], ['/cashier/pos', 'bi-calculator', 'Point of Sale'], ['/cashier/orders', 'bi-receipt', 'Orders'], ['/settings', 'bi-person-circle', 'My settings'],
    ],
    'customer' => [
        ['/customer/dashboard', 'bi-house', 'Dashboard'], ['/customer/menu', 'bi-cup-straw', 'Menu'], ['/customer/cart', 'bi-basket', 'Cart'], ['/customer/orders', 'bi-bag-check', 'My Orders'], ['/customer/reviews', 'bi-star', 'Reviews'], ['/settings', 'bi-person-circle', 'My settings'],
    ],
    'rider' => [
        ['/rider/dashboard', 'bi-grid-1x2', 'Dashboard'], ['/rider/deliveries', 'bi-geo-alt', 'Deliveries'], ['/settings', 'bi-person-circle', 'My settings'],
    ],
];
$items = $menus[$role] ?? [];
$nav = static function (array $items, string $path): string {
    $html = '';
    foreach ($items as [$href, $icon, $label]) {
        $active = str_starts_with('/' . trim($path, '/'), $href) ? ' active' : '';
        $badge = $label === 'Orders' && in_array((session()->get('user')['role'] ?? null), ['admin', 'cashier'], true) ? '<span class="badge text-bg-warning ms-auto" data-pending-orders hidden>0</span>' : '';
        $html .= '<a class="nav-link' . $active . '" href="' . base_url(ltrim($href, '/')) . '"><i class="bi ' . $icon . '"></i><span>' . esc($label) . '</span>' . $badge . '</a>';
    }
    return $html;
};
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-name" content="<?= csrf_token() ?>">
    <meta name="csrf-hash" content="<?= csrf_hash() ?>">
    <title><?= esc($title ?? 'Dashboard') ?> · JRMSU-TC Cafeteria</title>
    <link rel="icon" href="<?= base_url('favicon.ico') ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= base_url('assets/css/app.css') ?>" rel="stylesheet">
    <?= $this->renderSection('styles') ?>
</head>
<body data-cart-key="<?= $role === 'cashier' ? 'jrmsu-pos-cart' : 'jrmsu-cafeteria-cart' ?>">
<aside class="app-sidebar p-3 d-none d-lg-flex flex-column">
    <a class="d-flex align-items-center gap-3 text-white text-decoration-none p-2 mb-4" href="<?= base_url(role_home($role)) ?>">
        <img class="brand-logo" src="<?= base_url('assets/img/jrmsu-cafeteria-logo.png') ?>" alt="Logo">
        <div><div class="fw-bold">JRMSU-TC</div><small class="text-white-50">Cafeteria</small></div>
    </a>
    <nav class="nav flex-column gap-1"><?= $nav($items, $path) ?></nav>
    <div class="mt-auto pt-4 border-top border-secondary-subtle">
        <div class="d-flex align-items-center gap-3 px-2 mb-3">
            <img class="sidebar-user-avatar" src="<?= esc(user_avatar_url($user['avatar'] ?? null), 'attr') ?>" alt="<?= esc($user['name'] ?? 'User', 'attr') ?> avatar">
            <div class="min-w-0"><div class="fw-semibold text-truncate"><?= esc($user['name'] ?? 'User') ?></div><small class="text-white-50 text-capitalize"><?= esc($role) ?></small></div>
        </div>
        <form action="<?= base_url('logout') ?>" method="post" data-confirm="Sign out of your account?" data-confirm-title="Sign out" data-confirm-label="Sign out" data-confirm-class="btn-danger"><?= csrf_field() ?><button class="btn btn-outline-light w-100" type="submit"><i class="bi bi-box-arrow-right me-2"></i>Sign out</button></form>
    </div>
</aside>
<div class="offcanvas offcanvas-start text-bg-dark" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel">
    <div class="offcanvas-header border-bottom border-secondary"><div class="d-flex align-items-center gap-2"><img class="brand-logo" src="<?= base_url('assets/img/jrmsu-cafeteria-logo.png') ?>" alt="Logo"><h5 class="offcanvas-title" id="mobileSidebarLabel">JRMSU-TC Cafeteria</h5></div><button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button></div>
    <div class="offcanvas-body d-flex flex-column">
        <nav class="app-sidebar position-static d-flex flex-column p-0 w-100" style="min-height:auto;background:transparent;"><?= $nav($items, $path) ?></nav>
        <div class="d-flex align-items-center gap-3 mt-auto pt-4 mb-3 border-top border-secondary-subtle">
            <img class="sidebar-user-avatar" src="<?= esc(user_avatar_url($user['avatar'] ?? null), 'attr') ?>" alt="<?= esc($user['name'] ?? 'User', 'attr') ?> avatar">
            <div class="min-w-0"><div class="fw-semibold text-truncate"><?= esc($user['name'] ?? 'User') ?></div><small class="text-white-50 text-capitalize"><?= esc($role) ?></small></div>
        </div>
        <form action="<?= base_url('logout') ?>" method="post" data-confirm="Sign out of your account?" data-confirm-title="Sign out" data-confirm-label="Sign out" data-confirm-class="btn-danger"><?= csrf_field() ?><button class="btn btn-outline-light w-100" type="submit">Sign out</button></form>
    </div>
</div>
<main class="app-main">
    <div class="app-topbar desktop-topbar px-4 align-items-center justify-content-between">
        <div class="topbar-heading">
            <div class="small text-secondary text-uppercase fw-semibold"><?= esc($role) ?> workspace</div>
            <div class="fw-bold"><?= esc($title ?? 'Dashboard') ?></div>
        </div>
        <div class="topbar-actions">
            <?php if ($role === 'customer'): ?>
                <a class="topbar-icon-button position-relative" href="<?= base_url('customer/cart') ?>" aria-label="Open cart">
                    <i class="bi bi-basket2" aria-hidden="true"></i>
                    <span class="cart-count-badge" data-cart-count hidden>0</span>
                </a>
            <?php endif; ?>
            <span class="topbar-date"><i class="bi bi-calendar3" aria-hidden="true"></i><?= date('M j, Y') ?></span>
        </div>
    </div>
    <div class="app-topbar mobile-topbar px-3 align-items-center justify-content-between">
        <button class="topbar-icon-button" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar" aria-controls="mobileSidebar" aria-label="Open navigation">
            <i class="bi bi-list" aria-hidden="true"></i>
        </button>
        <div class="fw-bold text-truncate mx-3"><?= esc($title ?? 'Dashboard') ?></div>
        <?php if ($role === 'customer'): ?>
            <a class="topbar-icon-button position-relative" href="<?= base_url('customer/cart') ?>" aria-label="Open cart">
                <i class="bi bi-basket2" aria-hidden="true"></i>
                <span class="cart-count-badge" data-cart-count hidden>0</span>
            </a>
        <?php else: ?>
            <span class="topbar-icon-spacer" aria-hidden="true"></span>
        <?php endif; ?>
    </div>
    <div class="page-shell">
        <?= view('components/alerts') ?>
        <?= $this->renderSection('content') ?>
    </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= base_url('assets/js/app.js') ?>"></script>
<?php if (in_array($role, ['customer', 'cashier'], true)): ?><script src="<?= base_url('assets/js/cart.js') ?>"></script><?php endif; ?>
<?= $this->renderSection('scripts') ?>
</body>
</html>
