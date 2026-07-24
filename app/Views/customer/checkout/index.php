<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php $paymentModes = payment_modes(); ?>
<div class="mb-4">
    <h1 class="h3 section-title fw-bold">Checkout</h1>
    <p class="text-secondary mb-0">Choose pickup or delivery. Payment is collected in cash when the order is received.</p>
</div>

<form data-checkout-form data-delivery-fee="<?= $deliveryFee ?>" data-payment-modes="<?= esc(json_encode($paymentModes), 'attr') ?>">
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="surface-card p-4 p-lg-5">
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="checkout-order-type">Order type</label>
                        <select class="form-select" id="checkout-order-type" name="order_type" data-order-type>
                            <option value="pickup">Pickup</option>
                            <option value="delivery">Delivery</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="checkout-payment-label">Payment mode</label>
                        <input class="form-control" id="checkout-payment-label" data-payment-label value="<?= esc($paymentModes['pickup']['label']) ?>" readonly>
                        <input type="hidden" name="payment_method" data-payment-method value="<?= esc($paymentModes['pickup']['value'], 'attr') ?>">
                    </div>
                    <div class="col-12" data-delivery-fields hidden>
                        <label class="form-label fw-semibold" for="checkout-delivery-address">Delivery address</label>
                        <textarea
                            class="form-control"
                            id="checkout-delivery-address"
                            name="delivery_address"
                            rows="3"
                            placeholder="Building, office, dormitory, or campus landmark"
                        ></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="checkout-promo">Promo code</label>
                        <input class="form-control text-uppercase" id="checkout-promo" name="promo_code" placeholder="WELCOME10">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold" for="checkout-notes">Order notes</label>
                        <textarea class="form-control" id="checkout-notes" name="notes" placeholder="Special preparation or delivery instructions"></textarea>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="surface-card p-4 cart-sticky">
                <h2 class="h5 section-title fw-bold">Summary</h2>
                <div class="d-flex justify-content-between py-2"><span>Subtotal</span><span data-cart-subtotal>₱0.00</span></div>
                <div class="d-flex justify-content-between py-2"><span>Delivery fee</span><span data-delivery-fee>₱0.00</span></div>
                <hr>
                <div class="d-flex justify-content-between h5"><span>Total</span><span class="price" data-checkout-total>₱0.00</span></div>
                <button class="btn btn-primary btn-lg w-100 mt-3" type="submit">Place order</button>
                <div class="small text-secondary mt-3"><i class="bi bi-cash-coin me-1"></i><span data-payment-summary><?= esc($paymentModes['pickup']['label']) ?></span></div>
            </div>
        </div>
    </div>
</form>
<?= $this->endSection() ?>
