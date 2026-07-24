<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-name" content="<?= csrf_token() ?>">
    <meta name="csrf-hash" content="<?= csrf_hash() ?>">
    <title><?= esc($title ?? 'Account') ?> · JRMSU-TC Cafeteria</title>
    <link rel="icon" href="<?= base_url('favicon.ico') ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= base_url('assets/css/app.css') ?>" rel="stylesheet">
</head>
<body class="auth-page">
<div class="auth-card bg-white">
    <div class="row g-0">
        <div class="col-lg-5 auth-brand-panel p-4 p-lg-5 d-flex flex-column justify-content-between">
            <a class="text-white text-decoration-none d-flex align-items-center gap-3" href="<?= base_url('/') ?>">
                <img class="brand-logo-lg" src="<?= base_url('assets/img/jrmsu-cafeteria-logo.png') ?>" alt="JRMSU-TC Cafeteria logo">
                <div><div class="fw-bold fs-5">JRMSU-TC</div><div class="text-white-50">Cafeteria</div></div>
            </a>
            <div class="my-4">
                <span class="badge text-bg-warning mb-3">Campus dining made simple</span>
                <h1 class="display-6 fw-bold">Order ahead. Skip the line. Enjoy your meal.</h1>
                <p class="text-white-50 mb-0">A secure workspace for customers, cashiers, riders, and cafeteria administrators.</p>
            </div>
            <small class="text-white-50">JRMSU-TC, Tampilisan</small>
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
