<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="surface-card p-3 p-md-4 mb-4">
    <h1 class="h3 section-title fw-bold mb-2"><i class="bi bi-database-check me-2" aria-hidden="true"></i>Database backups</h1>
    <p class="text-secondary mb-3">Backups are stored privately outside the public website, compressed and verified with SHA-256. Restoration requires command-line confirmation.</p>
    <form action="<?= esc(base_url('admin/backups'), 'attr') ?>" method="post" class="d-flex flex-wrap gap-2 align-items-center">
        <?= csrf_field() ?>
        <button class="btn btn-primary" type="submit"><i class="bi bi-cloud-arrow-down me-2" aria-hidden="true"></i>Create backup now</button>
    </form>
    <p class="small text-secondary mt-3 mb-0">The server uses a native MySQL dump when available, or a table-only PHP backup when native tools cannot run. If backup creation fails, check the application's logs and make sure the web server can write to <code>writable/backups</code>.</p>
</div>
<div class="surface-card p-3 p-md-4">
    <h2 class="h5 fw-bold mb-3">Saved backups</h2>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th scope="col">File</th><th scope="col">Size</th><th scope="col">Integrity</th><th scope="col">Download</th></tr></thead>
            <tbody>
            <?php foreach ($files as $file): ?>
                <tr>
                    <td class="small"><code><?= esc($file['name']) ?></code></td>
                    <td><?= esc(number_format((int) $file['size'])) ?> bytes</td>
                    <td><span class="badge <?= $file['valid'] ? 'text-bg-success' : 'text-bg-danger' ?>"><?= $file['valid'] ? 'Verified' : 'Invalid checksum' ?></span></td>
                    <td>
                        <?php if ($file['valid']): ?>
                            <a class="btn btn-sm btn-outline-primary" href="<?= esc(base_url('admin/backups/' . $file['name']), 'attr') ?>"><i class="bi bi-download me-1" aria-hidden="true"></i>Download</a>
                        <?php else: ?>
                            <span class="small text-secondary">Unavailable</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($files === []): ?>
                <tr><td colspan="4" class="text-center text-secondary py-4">No backups have been saved yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>
