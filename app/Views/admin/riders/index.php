<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4">
    <div>
        <h1 class="h3 section-title fw-bold">Riders</h1>
        <p class="text-secondary mb-0">Manage delivery staff accounts.</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#riderModal" type="button"><i class="bi bi-plus-lg me-1"></i>New rider</button>
</div>
<div class="surface-card table-card overflow-hidden">
    <div class="table-responsive">
        <table class="table responsive-table">
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
                        <td data-label="Last login"><?= time_ago($user['last_login_at']) ?></td>
                        <td data-label="Update" class="text-end">
                            <form class="d-inline-flex gap-2" action="<?= base_url('admin/riders/' . $user['id'] . '/status') ?>" method="post" data-confirm="Update this rider account status?" data-confirm-title="Update rider" data-confirm-label="Update">
                                <?= csrf_field() ?>
                                <select class="form-select form-select-sm" name="status">
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
                    <input class="form-control" id="riderName" name="name" value="<?= old('name') ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="riderEmail">Email</label>
                    <input class="form-control" id="riderEmail" type="email" name="email" value="<?= old('email') ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="riderPhone">Phone</label>
                    <input class="form-control" id="riderPhone" name="phone" value="<?= old('phone') ?>">
                </div>
                <div>
                    <label class="form-label" for="riderPassword">Temporary password</label>
                    <input class="form-control" id="riderPassword" type="password" name="password" minlength="8" autocomplete="new-password" required>
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
