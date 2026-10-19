<?= $this->extend('layouts/public') ?>
<?= $this->section('content') ?>
<?php
$image = product_image($product);
$productImage = $image['url'];
$productImageAlt = $image['alt'];
?>
<main class="container py-5"><div class="row g-5 align-items-start"><div class="col-lg-6"><img class="img-fluid rounded-4 shadow-sm w-100 product-image" src="<?= esc($productImage, 'attr') ?>" alt="<?= esc($productImageAlt, 'attr') ?>" width="800" height="600" loading="lazy" decoding="async" data-fallback-src="<?= esc($image['fallback'], 'attr') ?>"></div><div class="col-lg-6"><div class="d-flex gap-2 mb-2"><?php if (!empty($product['is_healthy_choice']) && ($product['nutrition_status'] ?? '') === 'verified'): ?><span class="badge text-bg-success">Healthy choice</span><?php endif; ?><span class="badge <?= (int)$product['stock'] > 0 && $product['is_available'] ? 'text-bg-primary' : 'text-bg-secondary' ?>"><?= (int)$product['stock'] > 0 && $product['is_available'] ? 'Available' : 'Unavailable' ?></span></div><h1 class="display-6 fw-bold section-title"><?= esc($product['name']) ?></h1><div class="fs-3 fw-bold text-primary mb-3"><?= format_price($product['price']) ?></div><p class="text-secondary"><?= nl2br(esc($product['description'] ?: 'No description available.')) ?></p>
<?php if ($addons): ?><h2 class="h6 fw-bold mt-4">Available add-ons</h2><ul><?php foreach ($addons as $addon): ?><li><?= esc($addon['name']) ?> (+<?= format_price($addon['price']) ?>)</li><?php endforeach; ?></ul><?php endif; ?>
<div class="border rounded-3 p-3 my-4"><?= view('components/nutrition_facts', ['product' => $product]) ?></div>
<a class="btn btn-primary btn-lg" href="<?= base_url(($currentUser ?? null) ? 'customer/menu' : 'login') ?>">Order now</a></div></div></main>
<?= $this->endSection() ?>
