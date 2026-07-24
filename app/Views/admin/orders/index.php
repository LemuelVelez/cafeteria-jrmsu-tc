<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="mb-4"><h1 class="h3 section-title fw-bold">Orders</h1><p class="text-secondary mb-0">Monitor preparation, pickup, and delivery workflows.</p></div>
<div class="surface-card table-card overflow-hidden"><div class="table-responsive"><table class="table"><thead><tr><th>Order</th><th>Customer</th><th>Type</th><th>Status</th><th>Rider</th><th>Total</th><th></th></tr></thead><tbody>
<?php foreach ($orders as $order): ?><tr><td><a class="fw-semibold text-decoration-none" href="<?= base_url('admin/orders/'.$order['id']) ?>"><?= esc($order['order_number']) ?></a><div class="small text-secondary"><?= time_ago($order['created_at']) ?></div></td><td><?= esc($order['customer_name'] ?: 'Walk-in') ?></td><td class="text-capitalize"><?= esc(str_replace('_',' ',$order['order_type'])) ?></td><td><?= order_status_badge($order['status']) ?></td><td><?= esc($order['rider_name'] ?: '—') ?></td><td class="fw-semibold"><?= format_price($order['total']) ?></td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= base_url('admin/orders/'.$order['id']) ?>">Open</a></td></tr><?php endforeach; ?>
<?php if (!$orders): ?><tr><td colspan="7"><?= view('components/empty', ['message'=>'New orders will appear here.']) ?></td></tr><?php endif; ?>
</tbody></table></div></div>
<?= $this->endSection() ?>
