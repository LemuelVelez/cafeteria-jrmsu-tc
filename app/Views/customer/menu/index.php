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
            <?php if ($selectedCategory): ?>
                <input type="hidden" name="category" value="<?= (int) $selectedCategory ?>">
            <?php endif; ?>
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
                    data-product-image="<?= esc($product['image'] ? media_url($product['image']) : base_url('assets/img/jrmsu-cafeteria-logo.png'), 'attr') ?>"
                >
                    <div class="product-media">
                        <?php if ($product['image']): ?>
                            <img class="product-image" src="<?= media_url($product['image']) ?>" alt="<?= esc($product['name']) ?>">
                        <?php else: ?>
                            <div class="product-placeholder">
                                <img src="<?= base_url('assets/img/jrmsu-cafeteria-logo.png') ?>" alt="">
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
