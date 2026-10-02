<?php
$authImageKey = service('uri')->getSegment(1);
$authImages = [
    'login' => [
        'https://images.pexels.com/photos/8617524/pexels-photo-8617524.jpeg?auto=compress&cs=tinysrgb&fit=crop&w=1260&h=900',
        'Students sharing food together in a cafeteria',
    ],
    'register' => [
        'https://images.pexels.com/photos/6238053/pexels-photo-6238053.jpeg?auto=compress&cs=tinysrgb&fit=crop&w=1260&h=900',
        'Students sharing a meal during a campus study break',
    ],
    'forgot-password' => [
        'https://images.pexels.com/photos/8423421/pexels-photo-8423421.jpeg?auto=compress&cs=tinysrgb&fit=crop&w=1260&h=900',
        'Students enjoying snacks together',
    ],
    'reset-password' => [
        'https://images.pexels.com/photos/8423421/pexels-photo-8423421.jpeg?auto=compress&cs=tinysrgb&fit=crop&w=1260&h=900',
        'Students enjoying snacks together',
    ],
    'email-verification' => [
        'https://images.pexels.com/photos/8617514/pexels-photo-8617514.jpeg?auto=compress&cs=tinysrgb&fit=crop&w=1260&h=900',
        'Students enjoying lunch together in a school canteen',
    ],
];
[$authImage, $authImageAlt] = $authImages[$authImageKey] ?? $authImages['login'];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-name" content="<?= csrf_token() ?>">
    <meta name="csrf-hash" content="<?= csrf_hash() ?>">
    <meta name="app-base-url" content="<?= esc(base_url(), 'attr') ?>">
    <title><?= esc($title ?? 'Account') ?> · <?= esc($cafeteriaName ?? 'JRMSU-TC Cafeteria') ?></title>
    <link rel="icon" type="image/x-icon" href="<?= base_url('favicon.ico') ?>?v=2">
    <link rel="preconnect" href="https://images.pexels.com" crossorigin>
    <link rel="dns-prefetch" href="https://images.pexels.com">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= base_url('assets/css/app.css') ?>" rel="stylesheet">
</head>
<body class="auth-page">
<a class="auth-home-button" href="<?= base_url('/') ?>" aria-label="Go to home page">
    <i class="bi bi-house-door-fill" aria-hidden="true"></i>
</a>
<div class="auth-card bg-white">
    <div class="row g-0">
        <div class="col-lg-5 auth-brand-panel p-4 p-lg-5 d-flex flex-column justify-content-between">
            <img class="auth-brand-photo" src="<?= esc($authImage, 'attr') ?>" alt="<?= esc($authImageAlt, 'attr') ?>" width="1260" height="900" decoding="async" fetchpriority="high">
            <a class="auth-brand-content text-white text-decoration-none d-flex align-items-center gap-3" href="<?= base_url('/') ?>">
                <img class="brand-logo-lg" src="<?= base_url('assets/img/jrmsu-cafeteria-logo.webp') ?>" alt="JRMSU-TC Cafeteria logo" width="86" height="86" decoding="async">
                <div><div class="fw-bold fs-5">JRMSU-TC</div><div class="text-white-50">Cafeteria</div></div>
            </a>
            <div class="auth-brand-content my-4">
                <span class="badge text-bg-warning mb-3">Campus dining made simple</span>
                <h1 class="display-6 fw-bold">Order ahead. Skip the line. Enjoy your meal.</h1>
                <p class="text-white-50 mb-0">A secure workspace for customers, cashiers, riders, and cafeteria administrators.</p>
            </div>
            <small class="auth-brand-content text-white-50">JRMSU-TC, Tampilisan</small>
        </div>
        <div class="col-lg-7 p-4 p-sm-5 d-flex align-items-center">
            <div class="w-100 mx-auto" style="max-width: 500px;">
                <?= view('components/alerts') ?>
                <?= $this->renderSection('content') ?>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= base_url('assets/js/app.js') ?>"></script>
</body>
</html>
