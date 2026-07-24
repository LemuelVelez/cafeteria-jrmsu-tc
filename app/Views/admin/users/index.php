<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php $currentUserId = (int) ($currentUser['id'] ?? 0); ?>
<div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4">
    <div>
        <h1 class="h3 section-title fw-bold">Users</h1>
        <p class="text-secondary mb-0">View user accounts and create additional administrators.</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#adminModal" type="button"><i class="bi bi-person-plus me-1"></i>New admin</button>
</div>
<div class="surface-card table-card overflow-hidden">
    <div class="table-responsive">
        <table class="table responsive-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Role</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th>Last login</th>
                    <th class="text-end">Update</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <?php $isCurrentUser = (int) $user['id'] === $currentUserId; ?>
                    <tr>
                        <td data-label="User">
                            <div class="user-identity">
                                <img class="user-avatar" src="<?= esc(user_avatar_url($user['avatar'] ?? null), 'attr') ?>" alt="<?= esc($user['name'], 'attr') ?> avatar">
                                <div class="min-w-0">
                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        <span class="fw-semibold text-truncate"><?= esc($user['name']) ?></span>
                                        <?php if ($isCurrentUser): ?><span class="badge text-bg-light border">You</span><?php endif; ?>
                                    </div>
                                    <small class="text-secondary text-break"><?= esc($user['email']) ?></small>
                                </div>
                            </div>
                        </td>
                        <td data-label="Role"><span class="badge text-bg-primary text-capitalize"><?= esc($user['role']) ?></span></td>
                        <td data-label="Phone"><?= esc($user['phone'] ?: '—') ?></td>
                        <td data-label="Status"><span class="badge <?= $user['status'] === 'active' ? 'text-bg-success' : 'text-bg-secondary' ?> text-capitalize"><?= esc($user['status']) ?></span></td>
                        <td data-label="Last login"><?= time_ago($user['last_login_at']) ?></td>
                        <td data-label="Update" class="text-end">
                            <?php if ($isCurrentUser): ?>
                                <span class="text-secondary small">Current account</span>
                            <?php else: ?>
                                <form class="d-inline-flex gap-2" action="<?= base_url('admin/users/' . $user['id'] . '/status') ?>" method="post" data-confirm="Update this user account status?" data-confirm-title="Update user" data-confirm-label="Update">
                                    <?= csrf_field() ?>
                                    <select class="form-select form-select-sm" name="status">
                                        <option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                        <option value="inactive" <?= $user['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                        <option value="banned" <?= $user['status'] === 'banned' ? 'selected' : '' ?>>Banned</option>
                                    </select>
                                    <button class="btn btn-sm btn-outline-primary" type="submit">Save</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="modal fade" id="adminModal" tabindex="-1" aria-labelledby="adminModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" action="<?= base_url('admin/users') ?>" method="post" data-confirm="Create this administrator account?" data-confirm-title="Create administrator" data-confirm-label="Create">
            <?= csrf_field() ?>
            <div class="modal-header">
                <h2 class="modal-title h5" id="adminModalLabel">New administrator account</h2>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="adminName">Full name</label>
                    <input class="form-control" id="adminName" name="name" value="<?= old('name') ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="adminEmail">Email</label>
                    <input class="form-control" id="adminEmail" name="email" type="email" value="<?= old('email') ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="adminPhone">Phone</label>
                    <input class="form-control" id="adminPhone" name="phone" value="<?= old('phone') ?>">
                </div>
                <div>
                    <label class="form-label" for="adminPassword">Temporary password</label>
                    <input class="form-control" id="adminPassword" name="password" type="password" minlength="8" autocomplete="new-password" required>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" type="submit">Create administrator</button>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
