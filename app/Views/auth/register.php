<?= $this->extend('layouts/auth') ?>
<?= $this->section('content') ?>
<div class="mb-4">
    <div class="hero-kicker mb-2">Customer registration</div>
    <h1 class="section-title fw-bold">Create your account</h1>
    <p class="text-secondary">Order, track deliveries, and review completed meals after verifying your email.</p>
</div>
<form action="<?= base_url('register') ?>" method="post" novalidate>
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-12">
            <label class="form-label fw-semibold" for="name">Full name</label>
            <input class="form-control" id="name" name="name" value="<?= old('name') ?>" autocomplete="name" required>
        </div>
        <div class="col-md-7">
            <label class="form-label fw-semibold" for="email">Email address</label>
            <input class="form-control" id="email" name="email" type="email" value="<?= old('email') ?>" autocomplete="email" required>
        </div>
        <div class="col-md-5">
            <label class="form-label fw-semibold" for="phone">Phone number</label>
            <input class="form-control" id="phone" name="phone" value="<?= old('phone') ?>" autocomplete="tel">
        </div>
        <div class="col-12">
            <label class="form-label fw-semibold" for="address">Default campus delivery address</label>
            <textarea class="form-control" id="address" name="address" rows="2"><?= old('address') ?></textarea>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold" for="password">Password</label>
            <input class="form-control" id="password" name="password" type="password" minlength="8" autocomplete="new-password" required>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold" for="password_confirm">Confirm password</label>
            <input class="form-control" id="password_confirm" name="password_confirm" type="password" minlength="8" autocomplete="new-password" required>
        </div>
    </div>
    <button class="btn btn-primary btn-lg w-100 mt-4" type="submit">Create account</button>
</form>
<p class="text-center text-secondary mt-4 mb-0">Already registered? <a class="fw-semibold" href="<?= base_url('login') ?>">Sign in</a></p>
<?= $this->endSection() ?>
