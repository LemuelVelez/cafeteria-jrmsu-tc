<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
    <div><h1 class="h3 section-title fw-bold mb-1">Inventory</h1><p class="text-secondary mb-0">Stock is changed only through recorded inventory movements.</p></div>
    <?php if ($canEdit): ?><form action="<?= base_url('admin/inventory/reconcile') ?>" method="post"><?= csrf_field() ?><button class="btn btn-outline-primary"><i class="bi bi-check2-square"></i> Reconcile inventory</button></form><?php endif; ?>
</div>
<?php if ($canEdit): ?>
<div class="surface-card p-4 mb-4">
    <div class="row g-3 align-items-end">
        <div class="col-lg-4"><label class="form-label" for="inventoryBarcode">Scan barcode</label><input id="inventoryBarcode" class="form-control" type="text" autocomplete="off" data-inventory-barcode-scan data-target="[data-inventory-product]" placeholder="Scan or type barcode then Enter"><div class="form-text" data-inventory-scan-feedback>Scanning selects the matching product.</div></div>
        <div class="col-lg-8"><div class="small text-secondary">Use Stock-In for received goods, Adjustment for corrections, and Waste for spoiled/damaged units. Every change is ledgered.</div></div>
    </div>
</div>
<div class="row g-4 mb-4">
<?php foreach ([['stock-in','Stock-In','bi-box-arrow-in-down','Quantity received'],['adjust','Adjustment','bi-sliders','Signed quantity (+/-)'],['waste','Waste','bi-trash3','Quantity wasted']] as [$route,$label,$icon,$qtyLabel]): ?>
<div class="col-lg-4"><form class="surface-card p-4 h-100" action="<?= base_url('admin/inventory/'.$route) ?>" method="post"><?= csrf_field() ?><h2 class="h5 section-title fw-bold"><i class="bi <?= esc($icon) ?>"></i> <?= esc($label) ?></h2>
<div class="mb-3"><label class="form-label">Product</label><select class="form-select" name="product_id" data-inventory-product required><option value="">Select product</option><?php foreach ($products as $product): ?><option value="<?= (int)$product['id'] ?>" data-barcode="<?= esc($product['barcode'] ?? '', 'attr') ?>"><?= esc($product['name']) ?> · Stock <?= (int)$product['stock'] ?></option><?php endforeach; ?></select></div>
<div class="mb-3"><label class="form-label"><?= esc($qtyLabel) ?></label><input class="form-control" type="number" name="quantity" value="1" <?= $route === 'adjust' ? '' : 'min="1"' ?> required></div>
<?php if ($route === 'stock-in'): ?><div class="mb-3"><label class="form-label">Reference</label><input class="form-control" name="reference" maxlength="120" placeholder="Supplier receipt"></div><?php endif; ?>
<div class="mb-3"><label class="form-label">Reason / note<?= $route === 'stock-in' ? '' : ' *' ?></label><textarea class="form-control" name="note" maxlength="1000" <?= $route === 'stock-in' ? '' : 'required' ?>></textarea></div>
<button class="btn btn-primary w-100">Save <?= esc($label) ?></button></form></div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<div class="table-card table-responsive">
<table class="table align-middle mb-0"><thead><tr><th>Product</th><th>SKU / Barcode</th><th>Stock</th><th>Reorder level</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($products as $product): ?><tr><td class="fw-semibold"><?= esc($product['name']) ?></td><td><div><?= esc($product['sku'] ?: '—') ?></div><small class="text-secondary"><?= esc($product['barcode'] ?: '—') ?></small></td><td><?= (int)$product['stock'] ?></td><td><?= (int)$product['reorder_level'] ?></td><td><?php if ((int)$product['stock']===0): ?><span class="badge text-bg-danger">Out of stock</span><?php elseif ((int)$product['stock'] <= (int)$product['reorder_level']): ?><span class="badge text-bg-warning">Low stock</span><?php else: ?><span class="badge text-bg-success">Healthy</span><?php endif; ?></td><td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="<?= base_url(($canEdit ? 'admin' : 'cashier').'/inventory?product_id='.$product['id']) ?>">History</a></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php if ($selectedProductId): ?><div class="surface-card p-4 mt-4"><h2 class="h5 section-title fw-bold">Movement history</h2><?php if ($movements): ?><div class="table-responsive"><table class="table"><thead><tr><th>Date</th><th>Type</th><th>Qty</th><th>Before</th><th>After</th><th>User</th><th>Reference / note</th></tr></thead><tbody><?php foreach ($movements as $movement): ?><tr><td><?= esc(date('M j, Y g:i A', strtotime($movement['created_at']))) ?></td><td><?= esc(ucwords(str_replace('_',' ',$movement['movement_type']))) ?></td><td><?= (int)$movement['quantity'] ?></td><td><?= (int)$movement['stock_before'] ?></td><td><?= (int)$movement['stock_after'] ?></td><td><?= esc($movement['user_name'] ?? 'System') ?></td><td><?= esc(trim(($movement['reference'] ?? '').' '.($movement['note'] ?? '')) ?: '—') ?></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><p class="text-secondary mb-0">No movements found for this product.</p><?php endif; ?></div><?php endif; ?>
<?= $this->endSection() ?>
