<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?= view('components/handoff_stages',['handoffs'=>$handoffs]) ?>
<div class="surface-card p-3 mx-auto" style="max-width:320px"><header class="text-center"><h1 class="h5"><?= esc($cafeteriaName) ?></h1><strong>KITCHEN TICKET</strong><div class="display-3 fw-bold"><?= esc(format_queue_number(isset($order['queue_number']) ? (int) $order['queue_number'] : null, $order['order_number'])) ?></div><h2 class="h4"><?= esc($order['order_number']) ?></h2><p><?= esc(ucfirst($order['order_type'])) ?> · <?= esc($order['created_at']) ?></p></header><hr>
<?php foreach($items as $i): ?><div class="border-bottom py-2"><strong><?= (int)$i['quantity'] ?> × <?= esc($i['product_name']) ?></strong><?php foreach(json_decode($i['addons_json']??'[]',true)??[] as $a): ?><div>+ <?= esc($a['name']??'') ?></div><?php endforeach; ?><div><?= esc($i['notes']??'') ?></div><div>[<?= $i['prepared_at']?'X':' ' ?>] Prepared</div></div><?php endforeach; ?>
<div class="text-center mt-3"><div class="d-inline-block" data-qr-code data-qr-value="<?= esc(base_url('staff/orders/verify/'.$token),'attr') ?>"></div><p>Scan to verify / check items (valid for 48 hours)</p><button type="button" data-print-page class="btn btn-primary">Print ticket</button></div></div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/vendor/qrcode-generator/qrcode.min.js') ?>"></script>
<script src="<?= base_url('assets/js/qr.js') ?>"></script>
<script src="<?= base_url('assets/js/product-labels.js') ?>"></script>
<?= $this->endSection() ?>
