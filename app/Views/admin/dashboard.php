<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<section class="dashboard-hero d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4"><div><div class="dashboard-hero__eyebrow"><i class="bi bi-speedometer2" aria-hidden="true"></i> Operations & insights</div><h1 class="h3 section-title fw-bold mb-1">Cafeteria overview</h1><p class="text-secondary mb-0">Today’s operating performance and recent activity.</p></div><a class="btn btn-primary" href="<?= base_url('admin/products') ?>"><i class="bi bi-plus-lg me-1"></i>Add product</a></section>
<?= view('components/stock_warning', ['warnings' => $stockWarnings, 'inventoryUrl' => base_url('admin/inventory')]) ?>
<div class="row g-3 mb-4">
<?php foreach ([['Orders today',$stats['orders'],'bi-receipt'],['Revenue today',format_price($stats['revenue']),'bi-cash-stack'],['Customers',$stats['customers'],'bi-people'],['Available products',$stats['products'],'bi-cup-straw'],['Red stock',$stats['redStock'],'bi-exclamation-triangle'],['Yellow stock',$stats['yellowStock'],'bi-exclamation-circle']] as [$label,$value,$icon]): ?>
<div class="col-sm-6 col-xl"><div class="stat-card stock-dashboard-link-card bg-white p-4 h-100"><?php if ($label === 'Red stock' || $label === 'Yellow stock'): ?><a class="stretched-link" href="<?= base_url('admin/inventory?level='.($label === 'Yellow stock' ? 'yellow' : 'red')) ?>" aria-label="View <?= esc($label) ?>"></a><?php endif; ?><div class="d-flex align-items-center justify-content-between"><div><div class="text-secondary small mb-1"><?= esc($label) ?></div><div class="h3 section-title fw-bold mb-0"><?= esc((string)$value) ?></div></div><div class="stat-icon"><i class="bi <?= $icon ?>"></i></div></div></div></div>
<?php endforeach; ?>
</div>
<div class="row g-4">
    <div class="col-xl-7"><div class="surface-card p-4 h-100"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 section-title fw-bold mb-0">Seven-day revenue</h2><span class="small text-secondary">PHP</span></div><canvas id="revenueChart" height="135"></canvas></div></div>
    <div class="col-xl-5"><div class="surface-card table-card h-100 overflow-hidden"><div class="p-4 pb-2 d-flex justify-content-between"><h2 class="h5 section-title fw-bold">Recent orders</h2><a class="small" href="<?= base_url('admin/orders') ?>">View all</a></div><div class="table-responsive"><table class="table"><thead><tr><th>Order</th><th>Status</th><th class="text-end">Total</th></tr></thead><tbody><?php foreach ($recentOrders as $order): ?><tr><td><a class="fw-semibold text-decoration-none" href="<?= base_url('admin/orders/'.$order['id']) ?>"><?= esc($order['order_number']) ?></a><div class="small text-secondary"><?= esc($order['customer_name'] ?: 'Walk-in') ?></div></td><td><?= order_status_badge($order['status']) ?></td><td class="text-end fw-semibold"><?= format_price($order['total']) ?></td></tr><?php endforeach; ?><?php if (!$recentOrders): ?><tr><td colspan="3"><?= view('components/empty', ['message'=>'Orders will appear here.']) ?></td></tr><?php endif; ?></tbody></table></div></div></div>
</div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/vendor/chartjs/chart.umd.min.js') ?>"></script>
<script id="dashboardRevenueData" type="application/json"><?= json_encode($dailyRevenue, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?></script>
<script src="<?= base_url('assets/js/admin-dashboard.js') ?>"></script>
<?= $this->endSection() ?>
