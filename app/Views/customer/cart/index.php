<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<section class="cart-page" aria-labelledby="cart-title">
    <div class="page-heading-row">
        <div>
            <span class="page-eyebrow"><i class="bi bi-basket2" aria-hidden="true"></i>Your selection</span>
            <h1 class="section-title fw-bold mb-2" id="cart-title">Your cart</h1>
            <p class="text-secondary mb-0">Review quantities and add-ons before checkout.</p>
        </div>
        <a class="btn btn-outline-primary page-heading-action" href="<?= base_url('customer/menu') ?>">
            <i class="bi bi-plus-lg" aria-hidden="true"></i>
            <span>Add more items</span>
        </a>
    </div>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <div class="cart-list-card surface-card">
                <div class="cart-list-header">
                    <div>
                        <h2 class="h5 section-title fw-bold mb-1">Cart items</h2>
                        <p class="small text-secondary mb-0" data-cart-summary-count>0 items selected</p>
                    </div>
                    <span class="cart-list-icon" aria-hidden="true"><i class="bi bi-bag-check"></i></span>
                </div>
                <div class="cart-list" data-cart-rows></div>
            </div>
        </div>

        <div class="col-lg-4">
            <aside class="surface-card cart-summary cart-sticky" aria-labelledby="summary-title">
                <div class="cart-summary-heading">
                    <span class="cart-summary-icon" aria-hidden="true"><i class="bi bi-receipt"></i></span>
                    <div>
                        <h2 class="h5 section-title fw-bold mb-1" id="summary-title">Order summary</h2>
                        <p class="small text-secondary mb-0">Ready when you are.</p>
                    </div>
                </div>
                <div class="cart-summary-row">
                    <span>Subtotal</span>
                    <strong data-cart-subtotal>₱0.00</strong>
                </div>
                <div class="cart-summary-note">
                    <i class="bi bi-info-circle" aria-hidden="true"></i>
                    <span>Delivery fees and discounts are calculated during checkout.</span>
                </div>
                <a class="btn btn-primary btn-lg w-100 cart-checkout-button" href="<?= base_url('customer/checkout') ?>">
                    <span>Proceed to checkout</span>
                    <i class="bi bi-arrow-right" aria-hidden="true"></i>
                </a>
                <div class="cart-secure-note"><i class="bi bi-shield-check" aria-hidden="true"></i>Secure order review</div>
            </aside>
        </div>
    </div>
</section>
<?= $this->endSection() ?>
