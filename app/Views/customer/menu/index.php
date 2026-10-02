<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<section class="menu-page" aria-labelledby="menu-title">
    <div class="menu-hero">
        <div class="menu-heading">
            <span class="page-eyebrow"><i class="bi bi-stars" aria-hidden="true"></i>Made fresh on campus</span>
            <h1 class="section-title fw-bold mb-2" id="menu-title">Cafeteria menu</h1>
            <p class="text-secondary mb-0">Fresh campus favorites, ready for pickup or delivery.</p>
        </div>

        <form class="menu-search" method="get" role="search">
            <?php if ($selectedCategory): ?><input type="hidden" name="category" value="<?= (int) $selectedCategory ?>"><?php endif; ?>
            <?php if ($under500): ?><input type="hidden" name="under_500" value="1"><?php endif; ?>
            <?php if ($excludeAllergen): ?><input type="hidden" name="exclude_allergen" value="<?= esc($excludeAllergen, 'attr') ?>"><?php endif; ?>
            <i class="bi bi-search menu-search-icon" aria-hidden="true"></i>
            <input
                class="form-control"
                type="search"
                name="q"
                value="<?= esc($search ?? '') ?>"
                placeholder="Search meals and drinks"
                aria-label="Search meals and drinks"
            >
            <button class="btn btn-primary" type="submit" aria-label="Submit search">
                <span>Search</span>
                <i class="bi bi-arrow-right" aria-hidden="true"></i>
            </button>
        </form>
    </div>

    <nav class="category-filter" aria-label="Menu categories">
        <a class="category-pill <?= !$selectedCategory ? 'active' : '' ?>" href="<?= base_url('customer/menu') ?>">
            <i class="bi bi-grid" aria-hidden="true"></i>
            <span>All</span>
        </a>
        <?php foreach ($categories as $category): ?>
            <a
                class="category-pill <?= (int) $selectedCategory === (int) $category['id'] ? 'active' : '' ?>"
                href="<?= base_url('customer/menu?category=' . $category['id']) ?>"
            >
                <span><?= esc($category['name']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <form class="surface-card p-3 mb-4" method="get"><div class="row g-2 align-items-end"><?php if ($selectedCategory): ?><input type="hidden" name="category" value="<?= (int)$selectedCategory ?>"><?php endif; ?><?php if ($search): ?><input type="hidden" name="q" value="<?= esc($search, 'attr') ?>"><?php endif; ?><div class="col-md-4"><div class="form-check mt-4"><input class="form-check-input" type="checkbox" name="under_500" value="1" id="under500" <?= $under500 ? 'checked' : '' ?>><label class="form-check-label" for="under500">Under 500 kcal</label></div></div><div class="col-md-5"><label class="form-label" for="excludeAllergen">Exclude allergen</label><input class="form-control" id="excludeAllergen" name="exclude_allergen" value="<?= esc($excludeAllergen ?? '') ?>" placeholder="e.g. peanuts"></div><div class="col-md-3"><button class="btn btn-outline-primary w-100">Apply food filters</button></div></div></form>

    <div class="row g-4 menu-grid">
        <?php foreach ($products as $product): ?>
            <div class="col-sm-6 col-xl-4" data-product-column>
                <article
                    class="product-card bg-white"
                    data-product-card
                    data-product-id="<?= $product['id'] ?>"
                    data-product-name="<?= esc($product['name'], 'attr') ?>"
                    data-product-price="<?= $product['price'] ?>"
                    data-product-stock="<?= (int) $product['stock'] ?>"
                    data-product-image="<?= esc($product['image'] ? media_url($product['image']) : base_url('assets/img/jrmsu-cafeteria-logo.webp'), 'attr') ?>"
                >
                    <div class="product-media">
                        <?php if ($product['image']): ?>
                            <img class="product-image" src="<?= media_url($product['image']) ?>" alt="<?= esc($product['name']) ?>">
                        <?php else: ?>
                            <div class="product-placeholder">
                                <img src="<?= base_url('assets/img/jrmsu-cafeteria-logo.webp') ?>" alt="">
                            </div>
                        <?php endif; ?>
                        <span class="product-category-badge"><?= esc($product['category_name']) ?></span>
                    </div>

                    <div class="product-card-body">
                        <div class="product-card-heading">
                            <h2 class="h5 section-title fw-bold mb-0"><?= esc($product['name']) ?></h2>
                            <span class="price text-nowrap"><?= format_price($product['price']) ?></span>
                        </div>
                        <p class="product-description text-secondary"><?= esc($product['description']) ?></p>
                        <div class="d-flex flex-wrap gap-2 mb-3"><?php if ($product['calories'] !== null): ?><span class="badge text-bg-light border"><?= (int)$product['calories'] ?> kcal</span><?php endif; ?><?php if (!empty($product['is_healthy_choice'])): ?><span class="badge text-bg-success">Healthy choice</span><?php endif; ?></div>
                        <details class="mb-3"><summary class="small fw-semibold">Nutrition & allergens</summary><div class="small text-secondary mt-2"><?php if ($product['calories'] !== null || $product['serving_size']): ?>Serving: <?= esc($product['serving_size'] ?: '1 serving') ?> · Protein <?= esc($product['protein_g'] ?? '—') ?>g · Carbs <?= esc($product['carbohydrates_g'] ?? '—') ?>g · Fat <?= esc($product['fat_g'] ?? '—') ?>g<br>Allergens: <?= esc($product['allergens'] ?: 'None listed') ?><br><em>Values are estimates per serving.</em><?php else: ?>Nutrition information not available.<?php endif; ?></div></details>

                        <?php if (!empty($addons[$product['id']])): ?>
                            <div class="product-addons">
                                <div class="product-addons-title">
                                    <span>Add-ons</span>
                                    <i class="bi bi-plus-circle" aria-hidden="true"></i>
                                </div>
                                <div class="product-addon-list">
                                    <?php foreach ($addons[$product['id']] as $addon): ?>
                                        <div class="form-check product-addon-option">
                                            <input
                                                class="form-check-input"
                                                type="checkbox"
                                                data-addon
                                                value="<?= $addon['id'] ?>"
                                                data-name="<?= esc($addon['name'], 'attr') ?>"
                                                data-price="<?= $addon['price'] ?>"
                                                id="addon-<?= $addon['id'] ?>"
                                            >
                                            <label class="form-check-label" for="addon-<?= $addon['id'] ?>">
                                                <span><?= esc($addon['name']) ?></span>
                                                <small>+<?= format_price($addon['price']) ?></small>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="product-purchase-row">
                            <label class="quantity-field">
                                <span class="visually-hidden">Quantity for <?= esc($product['name']) ?></span>
                                <i class="bi bi-123" aria-hidden="true"></i>
                                <input
                                    class="form-control"
                                    type="number"
                                    min="1"
                                    max="<?= $product['stock'] ?>"
                                    value="1"
                                    data-quantity
                                    aria-label="Quantity for <?= esc($product['name'], 'attr') ?>"
                                >
                            </label>
                            <button class="btn btn-primary product-add-button" type="button" data-add-product>
                                <i class="bi bi-bag-plus" aria-hidden="true"></i>
                                <span>Add to cart</span>
                            </button>
                        </div>
                    </div>
                </article>
            </div>
        <?php endforeach; ?>

        <?php if (!$products): ?>
            <div class="col-12">
                <div class="surface-card"><?= view('components/empty', ['icon' => 'bi-search', 'message' => 'No available products match your search.']) ?></div>
            </div>
        <?php endif; ?>
    </div>
</section>
<?= $this->endSection() ?>
