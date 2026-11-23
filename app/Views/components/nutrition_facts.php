<?php
/** @var array $product — the controller eagerly attaches structured_allergens. */
$verified = \App\Services\NutritionSourcePolicy::isVerified($product);
$verifier = $product['nutrition_verifier_name'] ?? null;
$verifiedAt = $product['nutrition_verified_at'] ?? null;
?>
<section class="nutrition-panel" aria-label="Nutrition facts per serving">
    <h3 class="h5 fw-bold border-bottom border-dark border-3 pb-2">Nutrition Facts</h3>
    <?php if (!empty($product['is_demo_recipe'])): ?>
        <p class="small text-warning-emphasis" role="note">Demo-only nutrition, ingredients, and allergens. These values are fictional placeholders, not measured or kitchen-approved. Confirm actual allergens with the kitchen.</p>
    <?php endif; ?>
    <?php if ($verified): ?>
        <p class="small mb-2">Serving size: <?= esc($product['serving_size'] ?: 'Not specified') ?></p>
        <?php foreach (['calories'=>'Calories','protein_g'=>'Protein (g)','carbohydrates_g'=>'Carbohydrates (g)','fat_g'=>'Total fat (g)','sugar_g'=>'Sugar (g)','fiber_g'=>'Fiber (g)','sodium_mg'=>'Sodium (mg)'] as $key => $label): ?>
            <div class="d-flex justify-content-between border-bottom py-1"><span><?= esc($label) ?></span><strong><?= $product[$key] === null ? '—' : esc((string) $product[$key]) ?></strong></div>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="alert alert-info small">Nutrition information is being verified and will be shown once confirmed from a reliable source.</p>
    <?php endif; ?>
    <div class="mt-3"><strong>Allergens:</strong>
        <?php if (empty($product['structured_allergens'])): ?><span class="text-secondary">Not listed; check with the kitchen</span>
        <?php else: ?><?php foreach ($product['structured_allergens'] as $allergen): ?><span class="badge text-bg-warning me-1"><?= esc($allergen['name']) ?></span><?php endforeach; ?><?php endif; ?>
    </div>
    <?php if ($verified): ?>
        <?php if ($verifier && $verifiedAt): ?><div class="small text-secondary mt-3">Verified by <?= esc($verifier) ?> on <?= esc(substr($verifiedAt, 0, 10)) ?></div><?php endif; ?>
        <div class="small text-secondary mt-2">Source: <?= esc(\App\Services\NutritionSourcePolicy::label($product['nutrition_source'] ?? null)) ?>
            <?php if (!empty($product['nutrition_reference'])): ?> — <?= esc($product['nutrition_reference']) ?><?php endif; ?>
        </div>
    <?php endif; ?>
</section>
