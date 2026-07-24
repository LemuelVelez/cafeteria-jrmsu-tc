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
                        <a class="small text-decoration-none" href="tel:<?= esc($order['customer_phone'], 'attr') ?>">
                            <?= esc($order['customer_phone']) ?>
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
                    <div class="fw-semibold"><?= nl2br(esc($order['delivery_address'])) ?></div>
                </div>
                <div class="col-12 d-flex flex-wrap gap-2">
                    <a
                        class="btn btn-outline-primary"
                        target="_blank"
                        rel="noopener"
                        href="https://www.google.com/maps/search/?api=1&amp;query=<?= urlencode($order['delivery_address']) ?>"
                    >
                        <i class="bi bi-map me-1"></i>Open in Maps
                    </a>
                    <?php if (! empty($order['customer_phone'])): ?>
                        <a class="btn btn-outline-secondary" href="tel:<?= esc($order['customer_phone'], 'attr') ?>">
                            <i class="bi bi-telephone me-1"></i>Call customer
                        </a>
                    <?php endif; ?>
                </div>
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
        <div class="surface-card p-4 cart-sticky">
            <h2 class="h5 section-title fw-bold">Update delivery</h2>
            <p class="text-secondary small">Status changes are validated by the backend and cannot skip required steps.</p>
            <form action="<?= base_url('rider/deliveries/' . $order['id'] . '/status') ?>" method="post" data-confirm="Update this delivery status?" data-confirm-title="Update delivery" data-confirm-label="Update">
                <?= csrf_field() ?>
                <div class="d-grid gap-2">
                    <button class="btn btn-dark btn-lg" name="status" value="out_for_delivery" data-confirm="Mark this order as out for delivery?" data-confirm-title="Start delivery" data-confirm-label="Start delivery" <?= $order['status'] !== 'ready' ? 'disabled' : '' ?>>
                        <i class="bi bi-bicycle me-1"></i>Out for delivery
                    </button>
                    <button class="btn btn-success btn-lg" name="status" value="delivered" data-confirm="Mark this order as delivered?" data-confirm-title="Complete delivery" data-confirm-label="Mark delivered" <?= $order['status'] !== 'out_for_delivery' ? 'disabled' : '' ?>>
                        <i class="bi bi-check2-circle me-1"></i>Delivered
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
