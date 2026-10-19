<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="riders-page">
<div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4">
    <div>
        <h1 class="h3 section-title fw-bold">Riders</h1>
        <p class="text-secondary mb-0">Manage delivery staff accounts.</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#riderModal" type="button"><i class="bi bi-plus-lg me-1"></i>New rider</button>
</div>
<div class="surface-card table-card rider-table-card">
    <div class="table-responsive" tabindex="0" aria-label="Riders table, scroll horizontally to see all columns">
        <table class="table responsive-table rider-table">
            <thead>
                <tr>
                    <th>Rider</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th>Last login</th>
                    <th class="text-end">Update</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td data-label="Rider">
                            <div class="user-identity">
                                <img class="user-avatar" src="<?= esc(user_avatar_url($user['avatar'] ?? null), 'attr') ?>" alt="<?= esc($user['name'], 'attr') ?> avatar">
                                <div class="min-w-0">
                                    <div class="fw-semibold text-truncate"><?= esc($user['name']) ?></div>
                                    <small class="text-secondary text-break"><?= esc($user['email']) ?></small>
                                </div>
                            </div>
                        </td>
                        <td data-label="Phone"><?= esc($user['phone'] ?: '—') ?></td>
                        <td data-label="Status"><span class="badge <?= $user['status'] === 'active' ? 'text-bg-success' : 'text-bg-secondary' ?> text-capitalize"><?= esc($user['status']) ?></span></td>
                        <td data-label="Last login"><?= esc(time_ago($user['last_login_at'])) ?></td>
                        <td data-label="Update" class="text-end rider-update-cell">
                            <form class="rider-update-form" action="<?= esc(base_url('admin/riders/' . $user['id'] . '/status'), 'attr') ?>" method="post" data-confirm="Update this rider account status?" data-confirm-title="Update rider" data-confirm-label="Update">
                                <?= csrf_field() ?>
                                <select class="form-select form-select-sm rider-status-select" name="status" aria-label="Status for <?= esc($user['name'], 'attr') ?>">
                                    <option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="inactive" <?= $user['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                    <option value="banned" <?= $user['status'] === 'banned' ? 'selected' : '' ?>>Banned</option>
                                </select>
                                <button class="btn btn-sm btn-outline-primary rider-save-button" data-no-icon type="submit">Save</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($users)): ?>
                    <tr><td colspan="5"><div class="empty-state py-4"><i class="bi bi-bicycle" aria-hidden="true"></i><p class="mb-0">No riders have been added yet.</p></div></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</div>
<div class="modal fade" id="riderModal" tabindex="-1" aria-labelledby="riderModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" action="<?= base_url('admin/riders') ?>" method="post" data-confirm="Create this rider account?" data-confirm-title="Create rider" data-confirm-label="Create">
            <?= csrf_field() ?>
            <div class="modal-header">
                <h2 class="modal-title h5" id="riderModalLabel">New rider account</h2>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="riderName">Full name</label>
                    <input class="form-control" id="riderName" name="name" value="<?= old('name') ?>" placeholder="e.g. Maria Santos" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="riderEmail">Email</label>
                    <input class="form-control" id="riderEmail" type="email" name="email" value="<?= old('email') ?>" placeholder="e.g. maria.santos@example.com" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="riderPhone">Phone</label>
                    <input class="form-control" id="riderPhone" name="phone" value="<?= old('phone') ?>" placeholder="e.g. 0917 123 4567" maxlength="30">
                </div>
                <div>
                    <label class="form-label" for="riderPassword">Temporary password</label>
                    <input class="form-control" id="riderPassword" type="password" name="password" minlength="10" placeholder="At least 10 characters" data-strong-password autocomplete="new-password" required>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" type="submit">Create rider</button>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
