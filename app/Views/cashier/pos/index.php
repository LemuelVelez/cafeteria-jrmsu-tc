<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php $paymentModes = payment_modes(); ?>
<div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4">
    <div>
        <h1 class="h3 section-title fw-bold">Point of sale</h1>
        <p class="text-secondary mb-0">Create a pickup or delivery order with cash collection on receipt.</p>
    </div>
    <a class="btn btn-outline-primary align-self-start" href="<?= base_url('cashier/orders') ?>">
        <i class="bi bi-receipt me-1"></i>View orders
    </a>
</div>

<div class="row g-4">
    <div class="col-xl-8">
        <div class="d-flex flex-wrap gap-2 mb-3" data-category-filters>
            <button class="btn btn-sm btn-primary" type="button" data-category="all">All</button>
            <?php foreach ($categories as $category): ?>
                <button class="btn btn-sm btn-outline-secondary" type="button" data-category="<?= esc($category['id'], 'attr') ?>">
                    <?= esc($category['name']) ?>
                </button>
            <?php endforeach; ?>
        </div>

        <div class="row g-3">
            <?php foreach ($products as $product): ?>
                <div class="col-sm-6 col-lg-4" data-product-column data-category-id="<?= esc($product['category_id'], 'attr') ?>">
                    <article
                        class="product-card bg-white p-3 h-100"
                        data-product-card
                        data-product-id="<?= esc($product['id'], 'attr') ?>"
                        data-product-name="<?= esc($product['name'], 'attr') ?>"
                        data-product-price="<?= esc($product['price'], 'attr') ?>"
                    >
                        <?php if (! empty($product['image'])): ?>
                            <img class="product-image rounded mb-3" src="<?= media_url($product['image']) ?>" alt="<?= esc($product['name']) ?>">
                        <?php else: ?>
                            <div class="product-placeholder rounded mb-3">
                                <img src="<?= base_url('assets/img/jrmsu-cafeteria-logo.png') ?>" alt="">
                            </div>
                        <?php endif; ?>
                        <div class="d-flex justify-content-between gap-2">
                            <h2 class="h6 fw-bold mb-1"><?= esc($product['name']) ?></h2>
                            <span class="price text-nowrap"><?= format_price($product['price']) ?></span>
                        </div>
                        <small class="text-secondary">Stock: <?= esc($product['stock']) ?></small>
                        <input type="hidden" data-quantity value="1">
                        <button class="btn btn-primary w-100 mt-3" type="button" data-add-product>
                            <i class="bi bi-plus-lg"></i> Add
                        </button>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="surface-card p-4 cart-sticky">
            <h2 class="h5 section-title fw-bold">Current order</h2>
            <div data-pos-rows></div>

            <form data-pos-form class="mt-3" data-payment-modes="<?= esc(json_encode($paymentModes), 'attr') ?>">
                <div class="mb-3">
                    <label class="form-label" for="pos-customer">Customer</label>
                    <select class="form-select" id="pos-customer" name="customer_id">
                        <option value="">Walk-in customer</option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?= esc($customer['id'], 'attr') ?>"><?= esc($customer['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label" for="pos-order-type">Order type</label>
                        <select class="form-select" id="pos-order-type" name="order_type" data-pos-order-type>
                            <option value="pickup">Pickup</option>
                            <option value="delivery">Delivery</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="pos-payment">Payment mode</label>
                        <input class="form-control" id="pos-payment" data-pos-payment-label value="<?= esc($paymentModes['pickup']['label']) ?>" readonly>
                        <input type="hidden" name="payment_method" data-pos-payment-method value="<?= esc($paymentModes['pickup']['value'], 'attr') ?>">
                    </div>
                </div>

                <div class="mt-3" data-pos-delivery-fields hidden>
                    <label class="form-label" for="pos-delivery-address">Delivery address</label>
                    <textarea
                        class="form-control"
                        id="pos-delivery-address"
                        name="delivery_address"
                        rows="2"
                        placeholder="Building, office, dormitory, or campus landmark"
                    ></textarea>
                </div>

                <div class="mt-3">
                    <label class="form-label" for="pos-promo">Promo code</label>
                    <input class="form-control text-uppercase" id="pos-promo" name="promo_code">
                </div>

                <label class="visually-hidden" for="pos-notes">Order notes</label>
                <textarea class="form-control mt-3" id="pos-notes" name="notes" placeholder="Order notes"></textarea>
            </form>

            <div class="d-flex justify-content-between h5 mt-4">
                <span>Total</span>
                <span class="price" data-pos-total>₱0.00</span>
            </div>
            <button class="btn btn-primary btn-lg w-100" type="button" data-pos-submit>Complete order</button>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
