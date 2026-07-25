<?= $this->extend('layouts/auth') ?>
<?= $this->section('content') ?>
<div class="mb-4"><div class="hero-kicker mb-2">Welcome back</div><h1 class="section-title fw-bold">Sign in to your account</h1><p class="text-secondary">Use the account assigned to your cafeteria role.</p></div>
<form action="<?= base_url('login') ?>" method="post" novalidate>
    <?= csrf_field() ?>
    <div class="mb-3"><label class="form-label fw-semibold" for="email">Email address</label><div class="input-group"><span class="input-group-text"><i class="bi bi-envelope"></i></span><input class="form-control" id="email" name="email" type="email" autocomplete="email" value="<?= old('email') ?>" required></div></div>
    <div class="mb-2"><label class="form-label fw-semibold" for="password">Password</label><div class="input-group"><span class="input-group-text"><i class="bi bi-lock"></i></span><input class="form-control" id="password" name="password" type="password" autocomplete="current-password" required></div></div>
    <div class="text-end mb-4"><a class="small fw-semibold" href="<?= base_url('forgot-password') ?>">Forgot password?</a></div>
    <button class="btn btn-primary btn-lg w-100" type="submit">Sign in <i class="bi bi-arrow-right ms-1"></i></button>
</form>
<p class="text-center text-secondary mt-4 mb-0">New customer? <a class="fw-semibold" href="<?= base_url('register') ?>">Create an account</a></p>
<p class="text-center text-secondary small mt-2 mb-0"><a class="fw-semibold" href="<?= base_url('email-verification') ?>">Resend verification email</a></p>
<?= $this->endSection() ?>
