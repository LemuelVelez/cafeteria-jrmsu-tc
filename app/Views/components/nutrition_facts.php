<?php
/** @var array $product — the controller eagerly attaches structured_allergens. */
$verified = ($product['nutrition_status'] ?? '') === 'verified';
$verifier = $product['nutrition_verifier_name'] ?? null;
$verifiedAt = $product['nutrition_verified_at'] ?? null;
?>
<section class="nutrition-panel" aria-label="Nutrition facts per serving">
    <h3 class="h5 fw-bold border-bottom border-dark border-3 pb-2">Nutrition Facts</h3>
    <p class="small mb-2">Serving size: <?= esc($product['serving_size'] ?: 'Not specified') ?></p>
    <?php foreach (['calories'=>'Calories', 'protein_g'=>'Protein (g)', 'carbohydrates_g'=>'Carbohydrates (g)', 'fat_g'=>'Total fat (g)', 'sugar_g'=>'Sugar (g)', 'fiber_g'=>'Fiber (g)', 'sodium_mg'=>'Sodium (mg)'] as $key=>$label): ?>
        <div class="d-flex justify-content-between border-bottom py-1"><span><?= esc($label) ?></span><strong><?= $product[$key] === null ? '—' : esc((string) $product[$key]) ?></strong></div>
    <?php endforeach; ?>
    <div class="mt-3"><strong>Allergens:</strong>
        <?php if (empty($product['structured_allergens'])): ?><span class="text-secondary">Not listed; check with the kitchen</span>
        <?php else: ?><?php foreach ($product['structured_allergens'] as $allergen): ?><span class="badge text-bg-warning me-1"><?= esc($allergen['name']) ?></span><?php endforeach; ?><?php endif; ?>
    </div>
    <div class="small text-secondary mt-3"><?php if ($verified && $verifier && $verifiedAt): ?>Verified by <?= esc($verifier) ?> on <?= esc(substr($verifiedAt, 0, 10)) ?><?php else: ?>Estimated — not independently verified<?php endif; ?></div>
    <div class="small text-secondary">Source: <?= esc($product['nutrition_source'] ?: 'Not recorded') ?>
        <?php if (!empty($product['nutrition_sources'])): ?>— <?= esc(implode('; ', $product['nutrition_sources'])) ?><?php endif; ?>
    </div>
</section>
