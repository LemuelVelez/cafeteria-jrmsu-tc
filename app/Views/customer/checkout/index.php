<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php
$paymentModes = payment_modes();
$orderingEnabled = $pickupEnabled || $deliveryEnabled;
$defaultOrderType = $pickupEnabled ? 'pickup' : 'delivery';
$defaultPaymentMode = $paymentModes[$defaultOrderType];
?>
<section class="checkout-page" aria-labelledby="checkout-title">
    <div class="mb-4">
        <span class="page-eyebrow"><i class="bi bi-bag-check" aria-hidden="true"></i>Review and place order</span>
        <h1 class="h3 section-title fw-bold mb-2" id="checkout-title">Checkout</h1>
        <p class="text-secondary mb-0">Choose pickup or delivery. Payment is collected in cash when the order is received.</p>
    </div>

    <form
        data-checkout-form
        data-delivery-fee="<?= esc((string) $deliveryFee, 'attr') ?>"
        data-payment-modes="<?= esc(json_encode($paymentModes, JSON_THROW_ON_ERROR), 'attr') ?>"
        data-order-endpoint="<?= esc(base_url('api/orders'), 'attr') ?>"
        data-orders-url="<?= esc(base_url('customer/orders'), 'attr') ?>"
        data-menu-url="<?= esc(base_url('customer/menu'), 'attr') ?>"
    >
        <div class="checkout-layout">
            <div class="checkout-content">
                <section class="surface-card checkout-items-card" aria-labelledby="checkout-items-title">
                    <div class="checkout-card-header">
                        <div>
                            <h2 class="h5 section-title fw-bold mb-1" id="checkout-items-title">Order items</h2>
                            <p class="small text-secondary mb-0" data-checkout-item-count>0 items selected</p>
                        </div>
                        <a class="btn btn-sm btn-outline-primary" href="<?= base_url('customer/cart') ?>">
                            <i class="bi bi-pencil-square" aria-hidden="true"></i>
                            <span>Edit cart</span>
                        </a>
                    </div>
                    <div class="checkout-items" data-checkout-items>
                        <div class="checkout-loading"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Loading your cart…</div>
                    </div>
                </section>

                <section class="surface-card checkout-details-card" aria-labelledby="checkout-details-title">
                    <div class="checkout-card-header">
                        <div>
                            <h2 class="h5 section-title fw-bold mb-1" id="checkout-details-title">Order details</h2>
                            <p class="small text-secondary mb-0">Select how you want to receive your order.</p>
                        </div>
                        <span class="checkout-card-icon" aria-hidden="true"><i class="bi bi-clipboard-check"></i></span>
                    </div>

                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="checkout-order-type">Order type</label>
                            <select class="form-select" id="checkout-order-type" name="order_type" data-order-type required <?= $orderingEnabled ? '' : 'disabled' ?>>
                                <?php if ($pickupEnabled): ?><option value="pickup">Pickup</option><?php endif; ?>
                                <?php if ($deliveryEnabled): ?><option value="delivery">Delivery</option><?php endif; ?>
                                <?php if (! $orderingEnabled): ?><option value="">Ordering unavailable</option><?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="checkout-payment-label">Payment mode</label>
                            <input class="form-control" id="checkout-payment-label" data-payment-label value="<?= esc($defaultPaymentMode['label']) ?>" readonly>
                            <input type="hidden" name="payment_method" data-payment-method value="<?= esc($defaultPaymentMode['value'], 'attr') ?>">
                        </div>
                        <div class="col-12" data-delivery-fields hidden>
                            <label class="form-label fw-semibold" for="checkout-delivery-address">Delivery address</label>
                            <textarea
                                class="form-control"
                                id="checkout-delivery-address"
                                name="delivery_address"
                                rows="3"
                                minlength="5"
                                placeholder="Building, office, dormitory, or campus landmark"
                            ></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="checkout-promo">Promo code</label>
                            <input class="form-control text-uppercase" id="checkout-promo" name="promo_code" maxlength="50" placeholder="WELCOME10">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold" for="checkout-notes">Order notes</label>
                            <textarea class="form-control" id="checkout-notes" name="notes" maxlength="1000" placeholder="Special preparation or delivery instructions"></textarea>
                        </div>
                    </div>
                </section>
            </div>

            <aside class="surface-card checkout-summary cart-sticky" aria-labelledby="checkout-summary-title">
                <div class="checkout-summary-heading">
                    <span class="checkout-summary-icon" aria-hidden="true"><i class="bi bi-receipt"></i></span>
                    <div>
                        <h2 class="h5 section-title fw-bold mb-1" id="checkout-summary-title">Order summary</h2>
                        <p class="small text-secondary mb-0">Review the total before placing your order.</p>
                    </div>
                </div>

                <div class="checkout-summary-row">
                    <span>Subtotal</span>
                    <strong data-cart-subtotal>₱0.00</strong>
                </div>
                <div class="checkout-summary-row">
                    <span>Delivery fee</span>
                    <strong data-delivery-fee>₱0.00</strong>
                </div>
                <div class="checkout-summary-total">
                    <span>Total</span>
                    <strong class="price" data-checkout-total>₱0.00</strong>
                </div>

                <div class="alert alert-danger checkout-error" role="alert" data-checkout-error hidden></div>

                <?php if (! $orderingEnabled): ?>
                    <div class="alert alert-warning mt-3 mb-0">Pickup and delivery ordering are currently unavailable.</div>
                <?php endif; ?>
                <button class="btn btn-primary btn-lg w-100 mt-3" type="submit" data-checkout-submit <?= $orderingEnabled ? '' : 'disabled' ?>>
                    <i class="bi bi-bag-check" aria-hidden="true"></i>
                    <span data-checkout-submit-label>Place order</span>
                </button>
                <div class="small text-secondary mt-3">
                    <i class="bi bi-cash-coin me-1" aria-hidden="true"></i>
                    <span data-payment-summary><?= esc($defaultPaymentMode['label']) ?></span>
                </div>
            </aside>
        </div>
    </form>
</section>
<?= $this->endSection() ?>
