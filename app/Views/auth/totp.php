<?= $this->extend('layouts/auth') ?>
<?= $this->section('content') ?>
<h1 class="h4">Two-factor verification</h1><p>Enter the six-digit code in your authenticator app or a recovery code.</p><form action="<?= base_url('two-factor') ?>" method="post"><?= csrf_field() ?><label class="form-label">Code<input name="code" class="form-control" autocomplete="one-time-code" required autofocus></label><button class="btn btn-primary w-100 mt-3">Verify</button></form>
<?= $this->endSection() ?>
