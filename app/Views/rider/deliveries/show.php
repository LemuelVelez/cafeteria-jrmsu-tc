<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4">
    <div>
        <a class="small text-decoration-none" href="<?= base_url('rider/deliveries') ?>">
            <i class="bi bi-arrow-left"></i> Deliveries
        </a>
        <h1 class="h3 section-title fw-bold mt-2"><?= esc($order['order_number']) ?></h1>
        <?= order_status_badge($order['status']) ?>
    </div>
    <div class="text-md-end">
        <div class="small text-secondary">Collect / confirm</div>
        <div class="h3 price"><?= format_price($order['total']) ?></div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="surface-card p-4 mb-4">
            <h2 class="h5 section-title fw-bold">Delivery information</h2>
            <div class="row g-3 mt-1">
                <div class="col-md-6">
                    <small class="text-secondary">Customer</small>
                    <div class="fw-semibold"><?= esc($order['customer_name'] ?: 'Walk-in customer') ?></div>
                    <?php if (! empty($order['customer_phone'])): ?>
                        <a class="small text-decoration-none" href="<?= $canViewContact ? 'tel:' . esc($order['customer_phone'], 'attr') : '#' ?>">
                            <?= esc($canViewContact ? $order['customer_phone'] : mask_phone($order['customer_phone'])) ?>
                        </a>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <small class="text-secondary">Payment</small>
                    <div class="fw-semibold text-capitalize">
                        <?= esc(payment_method_label($order['payment_method'])) ?> · <?= esc($order['payment_status']) ?>
                    </div>
                </div>
                <div class="col-12">
                    <small class="text-secondary">Address</small>
                    <div class="fw-semibold" id="deliveryAddress"><?= nl2br(esc($canViewContact ? $order['delivery_address'] : mask_address($order['delivery_address']))) ?></div>
                </div>
                <?php if ($canViewContact): ?><div class="col-12 d-flex flex-wrap gap-2">
                    <button
                        class="btn btn-outline-primary"
                        type="button"
                        data-copy-target="#deliveryAddress"
                        data-copy-default-label="Copy address"
                        data-copy-success-label="Address copied"
                        data-copy-error-label="Copy failed"
                    >
                        <i class="bi bi-copy me-1" aria-hidden="true"></i><span data-copy-label aria-live="polite">Copy address</span>
                    </button>
                    <?php if (! empty($order['customer_phone'])): ?>
                        <a class="btn btn-outline-secondary" href="<?= $canViewContact ? 'tel:' . esc($order['customer_phone'], 'attr') : '#' ?>">
                            <i class="bi bi-telephone me-1"></i>Call customer
                        </a>
                    <?php endif; ?>
                </div><?php else: ?><div class="col-12"><div class="alert alert-light border mb-0"><i class="bi bi-shield-lock me-1"></i>Customer contact details are hidden after completion or cancellation.</div></div><?php endif; ?>
            </div>
        </div>

        <div class="surface-card table-card overflow-hidden">
            <div class="p-4 pb-2"><h2 class="h5 section-title fw-bold">Items</h2></div>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>Item</th><th>Qty</th><th class="text-end">Total</th></tr></thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?= esc($item['product_name']) ?></td>
                                <td><?= esc($item['quantity']) ?></td>
                                <td class="text-end"><?= format_price($item['line_total']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="surface-card p-4 mb-3">
            <label class="form-label fw-semibold" for="riderOrderQrScan">Scan order QR</label>
            <input id="riderOrderQrScan" class="form-control" type="text" autocomplete="off" data-order-qr-scan placeholder="Scan verification QR then Enter">
            <div class="form-text">Use a USB/Bluetooth scanner to verify the order before hand-off.</div>
        </div>
        <div class="surface-card p-4 cart-sticky">
            <h2 class="h5 section-title fw-bold">Update delivery</h2>
            <p class="text-secondary small">Status changes are validated by the backend and cannot skip required steps.</p>
            <form action="<?= base_url('rider/deliveries/' . $order['id'] . '/status') ?>" method="post" data-confirm="Update this delivery status?" data-confirm-title="Update delivery" data-confirm-label="Update">
                <?= csrf_field() ?>
                <div class="d-grid gap-2">
                    <button class="btn btn-dark btn-lg" name="status" value="out_for_delivery" data-confirm="Mark this order as out for delivery?" data-confirm-title="Start delivery" data-confirm-label="Start delivery" <?= $order['status'] !== 'ready_for_pickup' ? 'disabled' : '' ?>>
                        <i class="bi bi-bicycle me-1"></i>Out for delivery
                    </button>
                    <button class="btn btn-success btn-lg" name="status" value="completed" data-confirm="Mark this order as completed?" data-confirm-title="Complete delivery" data-confirm-label="Mark completed" <?= $order['status'] !== 'out_for_delivery' ? 'disabled' : '' ?>>
                        <i class="bi bi-check2-circle me-1"></i>Delivered
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
