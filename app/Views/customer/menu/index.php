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
                placeholder="e.g. Chicken adobo or iced coffee"
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
                <i class="bi bi-tag-fill" aria-hidden="true"></i>
                <span><?= esc($category['name']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <form class="surface-card p-3 mb-4" method="get"><div class="row g-2 align-items-end"><?php if ($selectedCategory): ?><input type="hidden" name="category" value="<?= (int)$selectedCategory ?>"><?php endif; ?><?php if ($search): ?><input type="hidden" name="q" value="<?= esc($search, 'attr') ?>"><?php endif; ?><div class="col-md-4"><div class="form-check mt-4"><input class="form-check-input" type="checkbox" name="under_500" value="1" id="under500" <?= $under500 ? 'checked' : '' ?>><label class="form-check-label" for="under500">Under 500 kcal</label></div></div><div class="col-md-5"><label class="form-label" for="excludeAllergen">Exclude allergen</label><select class="form-select" id="excludeAllergen" name="exclude_allergen"><option value="">All allergens permitted</option><?php foreach ($allergenOptions as $allergen): ?><option value="<?= esc($allergen['code'], 'attr') ?>" <?= ($excludeAllergen === $allergen['code']) ? 'selected' : '' ?>><?= esc($allergen['name']) ?></option><?php endforeach; ?></select></div><div class="col-md-3"><button class="btn btn-outline-primary w-100">Apply food filters</button></div><?php foreach(['high_protein'=>'High protein (20g+)','low_sodium'=>'Low sodium (140mg or less)','healthy_choice'=>'Healthy choice','vegetarian'=>'Vegetarian'] as $key=>$label): ?><div class="col-6 col-md-3"><label><input type="checkbox" name="<?= esc($key) ?>" value="1" <?= !empty($filters[$key])?'checked':'' ?>> <?= esc($label) ?></label></div><?php endforeach; ?></div></form>

    <div class="row g-4 menu-grid">
        <?php foreach ($products as $product): ?>
            <?php
            $fallback = product_fallback_image($product);
            $productImage = $product['image'] ? media_url($product['image']) : $fallback['url'];
            $productImageAlt = $product['image'] ? (string) $product['name'] : $fallback['alt'];
            ?>
            <div class="col-sm-6 col-xl-4" data-product-column>
                <article
                    class="product-card bg-white"
                    data-product-card
                    data-product-id="<?= $product['id'] ?>"
                    data-product-name="<?= esc($product['name'], 'attr') ?>"
                    data-product-price="<?= $product['price'] ?>"
                    data-product-stock="<?= (int) $product['stock'] ?>"
                    data-product-image="<?= esc($productImage, 'attr') ?>"
                >
                    <div class="product-media">
                        <img class="product-image" src="<?= esc($productImage, 'attr') ?>" alt="<?= esc($productImageAlt, 'attr') ?>" width="800" height="600" loading="lazy" decoding="async">
                        <span class="product-category-badge"><?= esc($product['category_name']) ?></span>
                    </div>

                    <div class="product-card-body">
                        <div class="product-card-heading">
                            <h2 class="h5 section-title fw-bold mb-0"><?= esc($product['name']) ?></h2>
                            <span class="price text-nowrap"><?= format_price($product['price']) ?></span>
                        </div>
                        <p class="product-description text-secondary"><?= esc($product['description']) ?></p>
                        <div class="d-flex flex-wrap gap-2 mb-3"><?php if ($product['calories'] !== null): ?><span class="badge text-bg-light border"><?= (int)$product['calories'] ?> kcal</span><?php endif; ?><span class="badge text-bg-secondary"><?= ($product['nutrition_status']??'estimated')==='verified'?'Verified':'Estimated' ?></span><?php if (!empty($product['is_healthy_choice']) && ($product['nutrition_status'] ?? '') === 'verified'): ?><span class="badge text-bg-success">Healthy choice</span><?php endif; ?></div>
                        <button class="btn btn-sm btn-outline-secondary mb-3" type="button" data-bs-toggle="modal" data-bs-target="#nutrition-modal-<?= (int)$product['id'] ?>" aria-haspopup="dialog">Nutrition Facts &amp; Allergens</button>
                        <div class="modal fade" id="nutrition-modal-<?= (int)$product['id'] ?>" tabindex="-1" aria-labelledby="nutrition-title-<?= (int)$product['id'] ?>" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
                                <div class="modal-header"><h2 class="modal-title h5" id="nutrition-title-<?= (int)$product['id'] ?>"><?= esc($product['name']) ?> nutrition</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close nutrition facts"></button></div>
                                <div class="modal-body"><?= view('components/nutrition_facts', ['product' => $product]) ?></div>
                            </div></div>
                        </div>

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
