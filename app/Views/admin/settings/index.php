<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="mb-4"><h1 class="h3 section-title fw-bold">Settings</h1><p class="text-secondary mb-0">Update customer-facing cafeteria preferences and notification defaults.</p></div>
<div class="row g-4">
<div class="col-lg-8"><form class="surface-card p-4 p-lg-5" action="<?= base_url('admin/settings') ?>" method="post" data-confirm="Save these cafeteria settings?" data-confirm-title="Save settings" data-confirm-label="Save"><?= csrf_field() ?><div class="row g-4">
<div class="col-md-7"><label class="form-label fw-semibold">Cafeteria name</label><input class="form-control" name="cafeteria_name" value="<?= esc($settings['cafeteria_name'] ?? 'JRMSU-TC Cafeteria') ?>"></div>
<div class="col-md-5"><label class="form-label fw-semibold">Delivery fee</label><div class="input-group"><span class="input-group-text">₱</span><input class="form-control" type="number" step="0.01" min="0" name="delivery_fee" value="<?= esc($settings['delivery_fee'] ?? '40.00') ?>"></div></div>
<div class="col-md-6"><label class="form-label fw-semibold">Operating hours</label><input class="form-control" name="operating_hours" value="<?= esc($settings['operating_hours'] ?? '') ?>"></div>
<div class="col-md-6"><label class="form-label fw-semibold">Contact number</label><input class="form-control" name="contact_number" value="<?= esc($settings['contact_number'] ?? '') ?>"></div>
<div class="col-md-4"><label class="form-label fw-semibold">Pickup enabled</label><select class="form-select" name="pickup_enabled"><option value="1" <?= ($settings['pickup_enabled']??'1')==='1'?'selected':'' ?>>Enabled</option><option value="0" <?= ($settings['pickup_enabled']??'1')==='0'?'selected':'' ?>>Disabled</option></select></div>
<div class="col-md-4"><label class="form-label fw-semibold">Delivery enabled</label><select class="form-select" name="delivery_enabled"><option value="1" <?= ($settings['delivery_enabled']??'1')==='1'?'selected':'' ?>>Enabled</option><option value="0" <?= ($settings['delivery_enabled']??'1')==='0'?'selected':'' ?>>Disabled</option></select></div>
<div class="col-md-4"><label class="form-label fw-semibold">Order emails</label><select class="form-select" name="email_order_notifications"><option value="1" <?= ($settings['email_order_notifications']??'1')==='1'?'selected':'' ?>>Enabled</option><option value="0" <?= ($settings['email_order_notifications']??'1')==='0'?'selected':'' ?>>Disabled</option></select></div>
</div><div class="mt-4 text-end"><button class="btn btn-primary px-4">Save settings</button></div></form></div>
<div class="col-lg-4"><div class="surface-card p-4 text-center h-100"><h2 class="h5 section-title fw-bold">Public menu QR</h2><p class="text-secondary small">Print this QR on posters or tables so customers can open the menu.</p><div class="d-inline-block p-2 bg-white border rounded" data-qr-code data-qr-value="<?= esc(base_url('/'), 'attr') ?>" data-qr-size="180"></div><div class="small text-break mt-2"><?= esc(base_url('/')) ?></div><button class="btn btn-outline-primary mt-3" type="button" data-print-page><i class="bi bi-printer me-1"></i>Print QR</button></div></div>
</div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/vendor/qrcode-generator/qrcode.min.js') ?>"></script><script src="<?= base_url('assets/js/qr.js') ?>"></script><script src="<?= base_url('assets/js/product-labels.js') ?>"></script>
<?= $this->endSection() ?>
