<?= $this->extend('layouts/public') ?>
<?= $this->section('head') ?>
<link rel="preload" as="image" type="image/webp" href="<?= base_url('assets/img/landing/hero-cafeteria.webp') ?>">
<?= $this->endSection() ?>
<?= $this->section('content') ?>
<header class="hero-section">
    <div class="container py-5">
        <?= view('components/alerts') ?>
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <div class="hero-kicker mb-3">JRMSU-TC · Tampilisan</div>
                <h1 class="hero-title fw-black mb-4">Campus meals, ready when you are.</h1>
                <p class="lead text-secondary mb-4">Browse today’s menu, order for pickup or delivery, and track every step from the cafeteria to your door.</p>
                <div class="d-grid d-sm-flex gap-3">
                    <a class="btn btn-primary btn-lg px-4" href="<?= base_url('register') ?>"><i class="bi bi-bag-check me-2"></i>Start ordering</a>
                    <a class="btn btn-outline-light btn-lg px-4" href="#menu">View menu</a>
                </div>
                <div class="hero-checklist d-flex flex-wrap gap-4 mt-5 small text-secondary">
                    <span><i class="bi bi-check-circle-fill text-success me-1"></i>Secure accounts</span>
                    <span><i class="bi bi-check-circle-fill text-success me-1"></i>Live order status</span>
                    <span><i class="bi bi-check-circle-fill text-success me-1"></i>Cash on Pickup or Delivery</span>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="hero-card">
                    <div class="visual p-5 text-center text-white">
                        <div class="position-relative z-1">
                            <img class="brand-logo-lg mb-4" src="<?= base_url('assets/img/jrmsu-cafeteria-logo.png') ?>" alt="JRMSU-TC Cafeteria logo" width="86" height="86" loading="lazy" decoding="async">
                            <div class="display-6 fw-bold">Fresh food.<br>Faster campus days.</div>
                            <p class="text-white-50 mt-3 mb-0">Ordering, cashier POS, and delivery in one system.</p>
                        </div>
                    </div>
                    <div class="hero-card-strip row g-0 text-center">
                        <div class="col-4 p-3 border-end"><strong class="d-block">Pickup</strong><small class="text-secondary">Skip lines</small></div>
                        <div class="col-4 p-3 border-end"><strong class="d-block">Delivery</strong><small class="text-secondary">Campus-wide</small></div>
                        <div class="col-4 p-3"><strong class="d-block">Tracking</strong><small class="text-secondary">Real time</small></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
<section class="py-5 bg-white" id="how-it-works">
    <div class="container py-4">
        <div class="row align-items-end mb-4"><div class="col-lg-7"><div class="hero-kicker">Simple ordering</div><h2 class="section-title display-6 fw-bold">From menu to meal in three steps.</h2></div></div>
        <div class="row g-4">
            <?php foreach ([
                ['bi-search', 'Browse', 'Find rice meals, snacks, coffee, and cold drinks.', 'step-browse.webp', 'Assorted cafeteria food ready to browse'],
                ['bi-basket', 'Order', 'Choose cafeteria pickup or campus delivery.', 'step-order.webp', 'Cafeteria staff serving a food order'],
                ['bi-geo-alt', 'Track', 'Follow preparation and delivery status from your account.', 'step-track.webp', 'Campus delivery rider on the road'],
            ] as $index => [$icon, $heading, $text, $image, $imageAlt]): ?>
                <div class="col-md-4">
                    <div class="surface-card step-card h-100 overflow-hidden">
                        <img class="step-card-image" src="<?= base_url('assets/img/landing/' . $image) ?>" alt="<?= esc($imageAlt) ?>" width="800" height="600" loading="lazy" decoding="async">
                        <div class="p-4">
                            <div class="feature-icon mb-4"><i class="bi <?= $icon ?>"></i></div>
                            <div class="small text-primary fw-bold mb-2">0<?= $index + 1 ?></div>
                            <h3 class="h4 section-title fw-bold"><?= esc($heading) ?></h3>
                            <p class="text-secondary mb-0"><?= esc($text) ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<section class="py-5" id="menu">
    <div class="container py-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3 mb-4"><div><div class="hero-kicker">Popular today</div><h2 class="section-title display-6 fw-bold mb-0">Made for busy campus days.</h2></div><a class="btn btn-outline-primary" href="<?= base_url('login') ?>">Sign in to order <i class="bi bi-arrow-right ms-1"></i></a></div>
        <div class="d-flex flex-wrap gap-2 mb-4"><?php foreach ($categories as $category): ?><span class="badge rounded-pill text-bg-light border px-3 py-2"><?= esc($category['name']) ?></span><?php endforeach; ?></div>
        <div class="row g-4">
            <?php
            $fallbackImages = [
                'Rice Meals' => 'fallback-rice-meals.webp',
                'Snacks' => 'fallback-snacks.webp',
                'Coffee' => 'fallback-coffee.webp',
                'Cold Drinks' => 'fallback-cold-drinks.webp',
            ];
            ?>
            <?php if ($products): foreach ($products as $product): ?>
                <?php $fallbackImage = $fallbackImages[$product['category_name']] ?? 'fallback-rice-meals.webp'; ?>
                <div class="col-sm-6 col-lg-4"><article class="product-card bg-white"><?php if ($product['image']): ?><img class="product-image" src="<?= media_url($product['image']) ?>" alt="<?= esc($product['name']) ?>" width="800" height="600" loading="lazy" decoding="async"><?php else: ?><img class="product-image product-fallback-image" src="<?= base_url('assets/img/landing/' . $fallbackImage) ?>" alt="<?= esc($product['category_name']) ?> from the JRMSU-TC Cafeteria" width="800" height="600" loading="lazy" decoding="async"><?php endif; ?><div class="p-4"><div class="small text-secondary mb-2"><?= esc($product['category_name']) ?></div><div class="d-flex justify-content-between gap-3"><h3 class="h5 section-title fw-bold"><?= esc($product['name']) ?></h3><span class="price text-nowrap"><?= format_price($product['price']) ?></span></div><p class="text-secondary small mb-0"><?= esc($product['description']) ?></p></div></article></div>
            <?php endforeach; else: ?>
                <div class="col-12"><div class="surface-card"><?= view('components/empty', ['icon' => 'bi-cup-hot', 'title' => 'Menu coming soon', 'message' => 'Run the migrations and database seeder to load sample products.']) ?></div></div>
            <?php endif; ?>
        </div>
    </div>
</section>
<section class="py-5 bg-white">
    <div class="container py-4">
        <div class="surface-card landing-cta p-4 p-lg-5 text-center">
            <div class="landing-cta-content">
                <img class="brand-logo-lg mb-3" src="<?= base_url('assets/img/jrmsu-cafeteria-logo.png') ?>" alt="JRMSU-TC Cafeteria logo" width="86" height="86" loading="lazy" decoding="async">
                <h2 class="display-6 fw-bold">Ready for your next meal?</h2>
                <p class="landing-cta-copy text-white-50 mx-auto">Create your customer account, save your delivery details, and keep all your cafeteria orders in one place.</p>
                <a class="btn btn-warning btn-lg" href="<?= base_url('register') ?>">Create free account</a>
            </div>
        </div>
    </div>
</section>
<?= $this->endSection() ?>
