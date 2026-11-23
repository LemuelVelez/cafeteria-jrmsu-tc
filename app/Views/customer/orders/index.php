<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="mb-4"><h1 class="h3 section-title fw-bold mb-1">My Orders</h1><p class="text-secondary mb-0">Find previous orders, track active orders, and reorder available items.</p></div>
<div class="row g-3 mb-4 customer-orders-stats">
<?php foreach ([['Total orders',$summary['total_orders'] ?? 0,'bi-receipt'],['Completed',$summary['completed_orders'] ?? 0,'bi-check2-circle'],['Total spent',format_price($summary['total_spent'] ?? 0),'bi-wallet2'],['Active',$summary['active_orders'] ?? 0,'bi-hourglass-split']] as [$label,$value,$icon]): ?>
    <div class="col-6 col-xl-3">
        <div class="stat-card h-100 customer-orders-stat">
            <div class="stat-icon" aria-hidden="true"><i class="bi <?= esc($icon) ?>"></i></div>
            <div class="customer-orders-stat__content">
                <div class="text-secondary customer-orders-stat__label"><?= esc($label) ?></div>
                <div class="customer-orders-stat__value fw-bold"><?= esc((string)$value) ?></div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>
<form class="surface-card p-3 mb-4 customer-orders-filters" method="get">
    <div class="row g-3 align-items-end">
        <div class="col-12 col-md-6 col-xxl-3"><label class="form-label" for="customerOrderSearch">Order number</label><input class="form-control" id="customerOrderSearch" name="q" value="<?= esc($q) ?>" placeholder="e.g. JRMSU-2026-001"></div>
        <div class="col-12 col-sm-6 col-md-3 col-xxl-2"><label class="form-label" for="customerOrderStatus">Status</label><select class="form-select" id="customerOrderStatus" name="status"><option value="">All</option><?php foreach (\App\Enums\OrderStatus::cases() as $case): ?><option value="<?= esc($case->value,'attr') ?>" <?= $status===$case->value?'selected':'' ?>><?= esc($case->label()) ?></option><?php endforeach; ?></select></div>
        <div class="col-12 col-sm-6 col-md-3 col-xxl-2"><label class="form-label" for="customerOrderType">Type</label><select class="form-select" id="customerOrderType" name="type"><option value="">All</option><option value="pickup" <?= $type==='pickup'?'selected':'' ?>>Pickup</option><option value="delivery" <?= $type==='delivery'?'selected':'' ?>>Delivery</option></select></div>
        <div class="col-12 col-sm-6 col-md-3 col-xxl-2"><label class="form-label" for="customerOrderFrom">From</label><input class="form-control" id="customerOrderFrom" type="date" name="from" value="<?= esc($from,'attr') ?>"></div>
        <div class="col-12 col-sm-6 col-md-3 col-xxl-2"><label class="form-label" for="customerOrderTo">To</label><input class="form-control" id="customerOrderTo" type="date" name="to" value="<?= esc($to,'attr') ?>"></div>
        <div class="col-12 col-sm-6 col-md-3 col-xxl-1"><button class="btn btn-primary w-100" type="submit"><i class="bi bi-funnel" aria-hidden="true"></i> Filter</button></div>
    </div>
</form>
<div class="table-card surface-card table-responsive d-none d-xxl-block customer-orders-table-wrap">
    <table class="table align-middle mb-0 customer-orders-table">
        <thead><tr><th scope="col">Order</th><th scope="col">Date & time</th><th scope="col">Type</th><th scope="col">Items</th><th scope="col">Total</th><th scope="col">Payment</th><th scope="col">Status</th><th scope="col">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($orders as $order): ?>
            <tr>
                <td class="fw-semibold"><div class="fs-5"><?= esc(format_queue_number(isset($order['queue_number']) ? (int) $order['queue_number'] : null, $order['order_number'])) ?></div><small class="text-secondary"><?= esc($order['order_number']) ?></small></td>
                <td><?= esc(date('M j, Y g:i A',strtotime($order['created_at']))) ?></td>
                <td><?= esc(ucfirst($order['order_type'])) ?></td>
                <td><small><?= esc($order['item_summary'] ?: 'No items') ?></small></td>
                <td class="fw-semibold"><?= format_price($order['total']) ?></td>
                <td><?= payment_status_badge($order['payment_status'], $order['payment_method']) ?></td>
                <td><?= order_status_badge($order['status']) ?><?php if (($order['cancellation_request_status'] ?? '') === 'pending'): ?><span class="badge text-bg-warning ms-1">Cancellation requested</span><?php endif; ?><?= view('components/order_mini_progress',['order'=>$order]) ?></td>
                <td class="text-end customer-orders-actions-cell">
                    <div class="table-actions customer-orders-actions">
                        <a class="btn btn-sm btn-outline-primary" href="<?= base_url('customer/orders/'.$order['id']) ?>">View</a>
                        <button class="btn btn-sm btn-outline-secondary" type="button" data-reorder-order="<?= (int)$order['id'] ?>">Reorder</button>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<div class="d-xxl-none d-grid gap-3 customer-orders-mobile-list">
    <?php foreach ($orders as $order): ?>
        <article class="surface-card p-3 customer-order-card">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div class="customer-order-card__meta">
                    <div class="fw-bold">Queue <?= esc(format_queue_number(isset($order['queue_number']) ? (int) $order['queue_number'] : null, $order['order_number'])) ?></div>
                    <div class="small"><?= esc($order['order_number']) ?></div>
                    <small class="text-secondary"><?= esc(date('M j, Y g:i A',strtotime($order['created_at']))) ?></small>
                </div>
                <?= order_status_badge($order['status']) ?><?php if (($order['cancellation_request_status'] ?? '') === 'pending'): ?><span class="badge text-bg-warning">Cancellation requested</span><?php endif; ?>
            </div>
            <?= view('components/order_mini_progress',['order'=>$order]) ?>
            <div class="row g-2 small mt-2 customer-order-card__details">
                <div class="col-12 col-sm-6">Type: <strong><?= esc(ucfirst($order['order_type'])) ?></strong></div>
                <div class="col-12 col-sm-6">Items: <strong><?= esc($order['item_summary'] ?: 'No items') ?></strong></div>
                <div class="col-12 col-sm-6">Payment: <?= payment_status_badge($order['payment_status'], $order['payment_method']) ?></div>
                <div class="col-12 col-sm-6">Total: <strong><?= format_price($order['total']) ?></strong></div>
            </div>
            <div class="customer-order-card__actions mt-3">
                <a class="btn btn-sm btn-primary" href="<?= base_url('customer/orders/'.$order['id']) ?>">View</a>
                <button class="btn btn-sm btn-outline-secondary" type="button" data-reorder-order="<?= (int)$order['id'] ?>">Reorder</button>
            </div>
        </article>
    <?php endforeach; ?>
</div>
<?php if (!$orders): ?><?= view('components/empty',['icon'=>'bi-receipt','message'=>'No orders match your filters.']) ?><?php endif; ?><div class="mt-3"><?= $pager->links() ?></div>
<?= $this->endSection() ?>
