<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<h1 class="h3">Customer data deletion requests</h1>
<p class="text-secondary">Approved requests permanently anonymize contact details while preserving financial orders and totals.</p>
<div class="table-responsive"><table class="table"><thead><tr><th>Customer</th><th>Requested</th><th>Action</th></tr></thead><tbody>
<?php foreach($requests as $user): ?><tr><td><?= esc($user['name']) ?> (<?= esc($user['email']) ?>)</td><td><?= esc($user['deletion_requested_at']) ?></td>
<td><form method="post" action="<?= base_url('admin/privacy/deletion-requests/'.$user['id'].'/approve') ?>" data-confirm="Anonymize this customer permanently?" data-confirm-title="Privacy deletion"><input type="hidden" name="<?= csrf_token() ?>" value="<?= csrf_hash() ?>"><button class="btn btn-outline-danger btn-sm">Approve deletion</button></form></td></tr><?php endforeach; ?>
<?php if(!$requests): ?><tr><td colspan="3">No pending requests.</td></tr><?php endif; ?></tbody></table></div>
<?= $this->endSection() ?>
