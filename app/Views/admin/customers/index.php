<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="mb-4">
    <h1 class="h3 section-title fw-bold">Customers</h1>
    <p class="text-secondary mb-0">Review customer accounts and access status.</p>
</div>
<div class="surface-card table-card overflow-hidden">
    <div class="table-responsive">
        <table class="table responsive-table">
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Phone</th>
                    <th>Address</th>
                    <th>Status</th>
                    <th class="text-end">Update</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td data-label="Customer">
                            <div class="user-identity">
                                <img class="user-avatar" src="<?= esc(user_avatar_url($user['avatar'] ?? null), 'attr') ?>" alt="<?= esc($user['name'], 'attr') ?> avatar">
                                <div class="min-w-0">
                                    <div class="fw-semibold text-truncate"><?= esc($user['name']) ?></div>
                                    <small class="text-secondary text-break"><?= esc($user['email']) ?></small>
                                </div>
                            </div>
                        </td>
                        <td data-label="Phone"><?= esc($user['phone'] ?: '—') ?></td>
                        <td data-label="Address"><?= esc(mb_strimwidth($user['address'] ?? '', 0, 55, '…') ?: '—') ?></td>
                        <td data-label="Status"><span class="badge <?= $user['status'] === 'active' ? 'text-bg-success' : 'text-bg-secondary' ?> text-capitalize"><?= esc($user['status']) ?></span></td>
                        <td data-label="Update" class="text-end">
                            <form class="d-flex justify-content-end gap-2" action="<?= base_url('admin/customers/' . $user['id'] . '/status') ?>" method="post" data-confirm="Update this customer account status?" data-confirm-title="Update customer" data-confirm-label="Update">
                                <?= csrf_field() ?>
                                <select class="form-select form-select-sm" style="width:120px" name="status">
                                    <option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="inactive" <?= $user['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                    <option value="banned" <?= $user['status'] === 'banned' ? 'selected' : '' ?>>Banned</option>
                                </select>
                                <button class="btn btn-sm btn-outline-primary" type="submit">Save</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>
