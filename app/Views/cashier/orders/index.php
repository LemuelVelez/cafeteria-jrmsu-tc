<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="mb-4">
    <h1 class="h3 section-title fw-bold">Order queue</h1>
    <p class="text-secondary mb-0">Update preparation statuses in sequence.</p><a href="<?= esc(base_url('queue-board'), 'attr') ?>" target="_blank" rel="noopener" class="btn btn-outline-primary mt-3"><i class="bi bi-display"></i> Open queue display</a>
</div>
<div class="surface-card table-card overflow-hidden">
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>Queue #</th><th>Order</th><th>Customer</th><th>Type</th><th>Status</th><th>Total</th><th class="text-end">Next status</th></tr></thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td class="fs-3 fw-bold"><?= esc(format_queue_number(isset($order['queue_number']) ? (int) $order['queue_number'] : null, $order['order_number'])) ?></td>
                        <td>
                            <div class="fw-semibold"><?= esc($order['order_number']) ?></div>
                            <small class="text-secondary"><?= time_ago($order['created_at']) ?></small>
                        </td>
                        <td><?= esc($order['customer_name'] ?: 'Walk-in') ?></td>
                        <td class="text-capitalize"><?= esc(str_replace('_', ' ', $order['order_type'])) ?></td>
                        <td><?= order_status_badge($order['status']) ?></td>
                        <td><?= format_price($order['total']) ?></td>
                        <td class="text-end">
                            <?php if ($order['status_options']): ?>
                                <form class="d-inline-flex gap-2" action="<?= base_url('cashier/orders/' . $order['id'] . '/status') ?>" method="post" data-confirm="Update this order status?" data-confirm-title="Update order" data-confirm-label="Update">
                                    <?= csrf_field() ?>
                                    <select class="form-select form-select-sm" name="status" required>
                                        <?php foreach ($order['status_options'] as $status): ?>
                                            <option value="<?= esc($status, 'attr') ?>"><?= esc(ucwords(str_replace('_', ' ', $status))) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="btn btn-sm btn-outline-primary">Update</button>
                                </form>
                            <?php else: ?>
                                <span class="small text-secondary">No action</span>
                            <?php endif; ?>
                            <?php if ($order['order_type'] === 'pickup' && $order['status'] === 'ready_for_pickup'): ?>
                                <form class="d-inline" method="post" action="<?= esc(base_url('staff/orders/'.$order['id'].'/call-queue'), 'attr') ?>"><?= csrf_field() ?><button class="btn btn-sm btn-warning mt-2" type="submit"><i class="bi bi-megaphone"></i> Call again</button></form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (! $orders): ?>
                    <tr><td colspan="7"><?= view('components/empty', ['message' => 'Orders will appear here.']) ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>
