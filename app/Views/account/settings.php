<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="mb-4">
    <h1 class="h3 section-title fw-bold">My settings</h1>
    <p class="text-secondary mb-0">Manage your profile photo, account details, and password.</p>
</div>

<div class="row g-4 align-items-start">
    <div class="col-12 col-xl-8">
        <form class="surface-card p-4 p-lg-5" action="<?= base_url('settings/profile') ?>" method="post" enctype="multipart/form-data" data-confirm="Save these profile changes?" data-confirm-title="Update profile" data-confirm-label="Save changes">
            <?= csrf_field() ?>
            <div class="d-flex flex-column flex-sm-row align-items-sm-center gap-4 mb-4 pb-4 border-bottom">
                <img class="settings-avatar" src="<?= esc(user_avatar_url($profile['avatar'] ?? null), 'attr') ?>" alt="<?= esc($profile['name'], 'attr') ?> avatar">
                <div class="flex-grow-1 min-w-0">
                    <label class="form-label fw-semibold" for="profileAvatar">Profile photo</label>
                    <input class="form-control" id="profileAvatar" name="avatar" type="file" accept="image/png,image/jpeg,image/webp">
                </div>
            </div>

            <div class="row g-4">
                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold" for="profileName">Full name</label>
                    <input class="form-control" id="profileName" name="name" value="<?= old('name', $profile['name']) ?>" autocomplete="name" required>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold" for="profileEmail">Email address</label>
                    <input class="form-control" id="profileEmail" name="email" type="email" value="<?= old('email', $profile['email']) ?>" autocomplete="email" required>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold" for="profilePhone">Phone number</label>
                    <input class="form-control" id="profilePhone" name="phone" value="<?= old('phone', $profile['phone'] ?? '') ?>" autocomplete="tel">
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold">Account role</label>
                    <input class="form-control text-capitalize" value="<?= esc($profile['role']) ?>" disabled>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold" for="profileAddress">Default address</label>
                    <textarea class="form-control" id="profileAddress" name="address" rows="3" autocomplete="street-address"><?= old('address', $profile['address'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="d-flex flex-column flex-sm-row justify-content-end gap-3 mt-4">
                <button class="btn btn-primary px-4" type="submit"><i class="bi bi-check2-circle"></i>Save profile</button>
            </div>
        </form>
    </div>

    <div class="col-12 col-xl-4">
        <div class="d-grid gap-4">
            <section class="surface-card p-4">
                <div class="d-flex align-items-start gap-3 mb-4">
                    <span class="settings-icon"><i class="bi bi-shield-lock"></i></span>
                    <div>
                        <h2 class="h5 fw-bold mb-1">Change password</h2>
                        <p class="text-secondary small mb-0">Use at least eight characters.</p>
                    </div>
                </div>
                <form action="<?= base_url('settings/password') ?>" method="post" data-confirm="Change your account password?" data-confirm-title="Change password" data-confirm-label="Change password">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="currentPassword">Current password</label>
                        <input class="form-control" id="currentPassword" name="current_password" type="password" autocomplete="current-password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="newPassword">New password</label>
                        <input class="form-control" id="newPassword" name="password" type="password" minlength="8" autocomplete="new-password" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-semibold" for="confirmPassword">Confirm new password</label>
                        <input class="form-control" id="confirmPassword" name="password_confirm" type="password" minlength="8" autocomplete="new-password" required>
                    </div>
                    <button class="btn btn-outline-primary w-100" type="submit"><i class="bi bi-key"></i>Change password</button>
                </form>
            </section>

            <?php if (! empty($profile['avatar'])): ?>
                <section class="surface-card p-4 border-danger-subtle">
                    <h2 class="h5 fw-bold mb-2">Use default avatar</h2>
                    <p class="text-secondary small">Remove your uploaded photo and use the cafeteria logo instead.</p>
                    <form action="<?= base_url('settings/avatar/remove') ?>" method="post" data-confirm="Remove your profile photo and use the cafeteria logo?" data-confirm-title="Remove profile photo" data-confirm-label="Remove photo" data-confirm-class="btn-danger">
                        <?= csrf_field() ?>
                        <button class="btn btn-outline-danger w-100" type="submit"><i class="bi bi-trash3"></i>Remove profile photo</button>
                    </form>
                </section>
            <?php endif; ?>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
