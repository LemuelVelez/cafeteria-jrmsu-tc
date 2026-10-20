<?php if (! empty($warnings)): ?>
<div class="alert alert-danger stock-warning mb-4" role="alert"><strong><i class="bi bi-exclamation-triangle"></i> Critical product stock:</strong>
<?php foreach ($warnings as $i => $product): ?><?= $i ? ', ' : '' ?><?= esc($product['name']) ?> (<?= (int) $product['stock'] ?> left)<?php endforeach; ?>.
<a class="alert-link" href="<?= esc($inventoryUrl, 'attr') ?>?level=red">Review red stock</a> or <a class="alert-link" href="<?= esc($inventoryUrl, 'attr') ?>?level=empty">empty stock</a>.</div>
<?php endif; ?>
