<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="JRMSU-TC Cafeteria online food ordering and delivery.">
    <meta name="csrf-name" content="<?= csrf_token() ?>">
    <meta name="csrf-hash" content="<?= csrf_hash() ?>">
    <meta name="app-base-url" content="<?= esc(base_url(), 'attr') ?>">
    <title><?= esc($title ?? $cafeteriaName ?? 'JRMSU-TC Cafeteria') ?></title>
    <?= $this->renderSection('head') ?>
    <link rel="icon" type="image/x-icon" href="<?= base_url('favicon.ico') ?>?v=2">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= base_url('assets/css/app.css') ?>" rel="stylesheet">
</head>
<body>
<?php
$publicPath = trim(service('uri')->getPath(), '/');
$isLandingPage = $publicPath === '';
$isLoginPage = $publicPath === 'login';
?>
<nav class="navbar navbar-expand-lg public-navbar<?= $isLandingPage ? ' public-navbar--overlay' : '' ?> sticky-top py-2" data-public-navbar>
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2 fw-bold" href="<?= base_url('/') ?>">
            <img class="brand-logo" src="<?= base_url('assets/img/jrmsu-cafeteria-logo.webp') ?>" alt="JRMSU-TC Cafeteria logo">
            <span>JRMSU-TC <span class="text-primary">Cafeteria</span></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#publicNav" aria-controls="publicNav" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="publicNav">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                <li class="nav-item"><a class="nav-link public-nav-link" data-public-section="menu" href="<?= base_url('/#menu') ?>">Menu</a></li>
                <li class="nav-item"><a class="nav-link public-nav-link" data-public-section="how-it-works" href="<?= base_url('/#how-it-works') ?>">How it works</a></li>
                <?php if ($currentUser ?? null): ?>
                    <li class="nav-item"><a class="btn btn-primary" href="<?= base_url(role_home($currentUser['role'])) ?>">Open dashboard</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link public-nav-link<?= $isLoginPage ? ' is-active' : '' ?>" href="<?= base_url('login') ?>"<?= $isLoginPage ? ' aria-current="page"' : '' ?>>Sign in</a></li>
                    <li class="nav-item"><a class="btn btn-primary" href="<?= base_url('register') ?>">Create account</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<?= $this->renderSection('content') ?>
<footer class="bg-white border-top py-4">
    <div class="container d-flex flex-column flex-md-row justify-content-between gap-2 small text-secondary">
        <span>© <?= date('Y') ?> JRMSU-TC Cafeteria, Tampilisan.</span>
        <span>Food ordering · POS · Delivery</span>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= base_url('assets/js/public-nav.js') ?>"></script>
<script src="<?= base_url('assets/js/app.js') ?>"></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
