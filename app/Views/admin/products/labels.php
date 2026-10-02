<?= $this->extend('layouts/main') ?>
<?= $this->section('styles') ?>
<style media="print">.app-sidebar,.topbar,.mobile-bottom-nav,.no-print{display:none!important}.app-main{margin:0!important}.label-sheet{display:grid!important;grid-template-columns:repeat(3,1fr);gap:8mm}.product-label{break-inside:avoid;border:1px solid #000!important}</style>
<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-4 no-print"><div><h1 class="h3 section-title fw-bold">Product labels</h1><p class="text-secondary mb-0">Printable Code 128 barcode and menu QR labels.</p></div><button class="btn btn-primary" type="button" data-print-page><i class="bi bi-printer me-1"></i>Print</button></div>
<div class="label-sheet row g-3">
<?php foreach ($products as $product): ?>
<div class="col-md-6 col-xl-4"><div class="product-label border rounded p-3 text-center h-100"><div class="fw-bold"><?= esc($product['name']) ?></div><div class="mb-2"><?= format_price($product['price']) ?></div><?php if ($product['barcode']): ?><svg data-barcode-value="<?= esc($product['barcode'], 'attr') ?>" aria-label="Barcode <?= esc($product['barcode'], 'attr') ?>"></svg><div class="small font-monospace"><?= esc($product['barcode']) ?></div><?php else: ?><div class="text-secondary small">No barcode assigned</div><?php endif; ?><div class="mt-2" data-qr-code data-qr-value="<?= esc(base_url('menu/'.$product['slug']), 'attr') ?>" data-qr-size="96"></div><small>Scan for food information</small></div></div>
<?php endforeach; ?>
</div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/vendor/jsbarcode/JsBarcode.all.min.js') ?>"></script>
<script src="<?= base_url('assets/vendor/qrcode-generator/qrcode.min.js') ?>"></script>
<script src="<?= base_url('assets/js/qr.js') ?>"></script>
<script src="<?= base_url('assets/js/product-labels.js') ?>"></script>
<?= $this->endSection() ?>
