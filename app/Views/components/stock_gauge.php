<?php
$level = \App\Services\StockLevelPolicy::forProduct($product, $settings ?? []);
$stock = (int) ($product['stock'] ?? 0);
$par = (int) ($product['max_stock'] ?? 0);
?>
<div class="stock-level <?= esc($level['cssClass'], 'attr') ?>" role="group" aria-label="<?= esc($level['label'].' stock: '.$stock.' of '.$par.' units') ?>">
<progress class="stock-level__track" value="<?= $level['percent'] ?>" max="100" aria-label="Stock percentage"><?= $level['percent'] ?>%</progress>
<span class="stock-level__description fw-semibold"><?= esc($level['label']) ?> · <?= $stock ?> / <?= $par ?> (<?= $par > 0 ? $level['percent'].'%' : 'par unset' ?>)</span>
</div>
