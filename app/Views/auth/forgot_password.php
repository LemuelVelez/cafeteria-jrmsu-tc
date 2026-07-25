<?= $this->extend('layouts/auth') ?>
<?= $this->section('content') ?>
<div class="mb-4">
    <div class="hero-kicker mb-2">Account recovery</div>
    <h1 class="section-title fw-bold">Forgot your password?</h1>
    <p class="text-secondary">Enter your email address and we will send a secure reset link.</p>
</div>
<form action="<?= base_url('forgot-password') ?>" method="post" novalidate>
    <?= csrf_field() ?>
    <div class="mb-4">
        <label class="form-label fw-semibold" for="email">Email address</label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
            <input class="form-control" id="email" name="email" type="email" autocomplete="email" value="<?= old('email') ?>" required>
        </div>
    </div>
    <button class="btn btn-primary btn-lg w-100" type="submit">Send reset link</button>
</form>
<p class="text-center text-secondary mt-4 mb-0"><a class="fw-semibold" href="<?= base_url('login') ?>">Back to sign in</a></p>
<?= $this->endSection() ?>
