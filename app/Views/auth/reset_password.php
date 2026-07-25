<?= $this->extend('layouts/auth') ?>
<?= $this->section('content') ?>
<div class="mb-4">
    <div class="hero-kicker mb-2">Account recovery</div>
    <h1 class="section-title fw-bold">Create a new password</h1>
    <p class="text-secondary">Use at least eight characters for your new password.</p>
</div>
<form action="<?= base_url('reset-password') ?>" method="post" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= esc($token, 'attr') ?>">
    <div class="mb-3">
        <label class="form-label fw-semibold" for="password">New password</label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input class="form-control" id="password" name="password" type="password" minlength="8" autocomplete="new-password" required>
        </div>
    </div>
    <div class="mb-4">
        <label class="form-label fw-semibold" for="password_confirm">Confirm new password</label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-shield-lock"></i></span>
            <input class="form-control" id="password_confirm" name="password_confirm" type="password" minlength="8" autocomplete="new-password" required>
        </div>
    </div>
    <button class="btn btn-primary btn-lg w-100" type="submit">Reset password</button>
</form>
<?= $this->endSection() ?>
