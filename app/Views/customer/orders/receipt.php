<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="surface-card p-4 mx-auto" style="max-width:680px"><header class="text-center"><h1 class="h4 fw-bold"><?= esc($cafeteriaName) ?></h1><p>Order receipt · <?= esc($order['order_number']) ?><br><?= esc(date('M j, Y g:i A',strtotime($order['created_at']))) ?></p></header>
<table class="table table-sm"><thead><tr><th>Item</th><th>Qty</th><th class="text-end">Amount</th></tr></thead><tbody><?php foreach($items as $i): ?><tr><td><?= esc($i['product_name']) ?><?php foreach(json_decode($i['addons_json']??'[]',true)??[] as $a): ?><div class="small">+ <?= esc($a['name']??'') ?></div><?php endforeach; ?></td><td><?= (int)$i['quantity'] ?></td><td class="text-end"><?= format_price($i['line_total']) ?></td></tr><?php endforeach; ?></tbody></table>
<div class="text-end">Subtotal <?= format_price($order['subtotal']) ?><br>Discount −<?= format_price($order['discount']) ?><br>Delivery <?= format_price($order['delivery_fee']) ?><h2 class="h5">Total <?= format_price($order['total']) ?></h2></div><p class="small">Payment: <?= esc(payment_method_label($order['payment_method'])) ?> · <?= esc($order['payment_status']) ?></p>
<div class="text-center"><div class="d-inline-block" data-qr-code data-qr-value="<?= esc(base_url('staff/orders/verify/'.$token),'attr') ?>"></div><p class="small">Show this QR to staff for hand-off.</p><p>Pickup code: <strong><?= esc((new \App\Services\OrderQrService())->pickupCode($order)) ?></strong></p><button class="btn btn-outline-primary" type="button" data-print-page>Print / Save PDF</button></div></div>
<section class="surface-card p-3 mt-3 mx-auto" style="max-width:680px"><h2 class="h5">Report an order issue</h2><form action="<?= base_url('customer/orders/'.$order['id'].'/issue') ?>" method="post"><?= csrf_field() ?><select class="form-select mb-2" name="reason"><option value="missing_item">Missing item</option><option value="wrong_item">Wrong item</option><option value="quality">Food quality</option></select><textarea class="form-control mb-2" name="details" maxlength="500" placeholder="Brief description"></textarea><button class="btn btn-outline-danger">Report issue</button></form></section>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/vendor/qrcode-generator/qrcode.min.js') ?>"></script>
<script src="<?= base_url('assets/js/qr.js') ?>"></script>
<script src="<?= base_url('assets/js/product-labels.js') ?>"></script>
<?= $this->endSection() ?>
