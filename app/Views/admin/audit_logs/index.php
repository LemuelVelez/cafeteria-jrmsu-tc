<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="audit-page">
    <header class="audit-header mb-4">
        <div>
            <h1 class="h3 section-title fw-bold mb-1">Audit Logs</h1>
            <p class="text-secondary mb-0">Read-only record of sensitive security and administrative actions.</p>
        </div>
        <div class="audit-header-actions report-controls">
            <a class="btn btn-outline-success" href="<?= esc(base_url('admin/audit-logs') . '?' . http_build_query(array_merge($filters, ['format' => 'csv'])), 'attr') ?>"><i class="bi bi-download me-1" aria-hidden="true"></i>Export CSV</a>
            <button class="btn btn-outline-secondary" type="button" data-print-page data-no-icon><i class="bi bi-printer me-1" aria-hidden="true"></i>Print</button>
        </div>
    </header>

    <form class="surface-card audit-filters p-3 p-lg-4 mb-4 report-controls" method="get">
        <div class="audit-filter-grid">
            <div><label class="form-label" for="audit-action">Action</label><input class="form-control" id="audit-action" name="action" data-icon="bi-activity" value="<?= esc($action, 'attr') ?>" placeholder="e.g. product_updated"></div>
            <div><label class="form-label" for="audit-user">User ID</label><input class="form-control" id="audit-user" type="number" min="1" name="user_id" data-icon="bi-person" value="<?= esc($userId ?: '', 'attr') ?>" placeholder="e.g. 12"></div>
            <div><label class="form-label" for="audit-entity">Entity</label><input class="form-control" id="audit-entity" name="entity" data-icon="bi-diagram-3" value="<?= esc($filters['entity'], 'attr') ?>"></div>
            <div><label class="form-label" for="audit-role">Role</label><select id="audit-role" name="role" class="form-select"><?php foreach ([''=>'All', 'admin'=>'Admin', 'cashier'=>'Cashier', 'rider'=>'Rider', 'customer'=>'Customer'] as $key => $label): ?><option value="<?= esc($key, 'attr') ?>" <?= $filters['role'] === $key ? 'selected' : '' ?>><?= esc($label) ?></option><?php endforeach; ?></select></div>
            <div><label class="form-label" for="audit-from">From</label><input class="form-control" id="audit-from" type="date" name="from" value="<?= esc($filters['from'], 'attr') ?>"></div>
            <div><label class="form-label" for="audit-to">To</label><input class="form-control" id="audit-to" type="date" name="to" value="<?= esc($filters['to'], 'attr') ?>"></div>
            <div class="audit-filter-actions"><button class="btn btn-primary" type="submit">Filter</button><a class="btn btn-outline-secondary" href="<?= esc(base_url('admin/audit-logs'), 'attr') ?>">Reset</a></div>
        </div>
    </form>

    <div class="surface-card table-card audit-table-card">
        <div class="table-responsive" tabindex="0" aria-label="Audit log entries; scroll horizontally to view all columns">
            <table class="table align-middle mb-0 audit-table">
                <thead><tr><th scope="col">Date</th><th scope="col">User</th><th scope="col">Action</th><th scope="col">Entity</th><th scope="col">Details</th><th scope="col">IP</th></tr></thead>
                <tbody>
                <?php foreach ($logs as $log): ?>
                    <?php
                    $actionName = strtolower((string) ($log['action'] ?? ''));
                    $actionType = preg_match('/(?:login|logout|auth|password|session|access)/', $actionName) ? 'auth'
                        : (str_contains($actionName, 'backup') ? 'backup'
                        : (preg_match('/(?:delete|remove|destroy|purge)/', $actionName) ? 'delete'
                        : (preg_match('/(?:create|add|update|edit|restore)/', $actionName) ? 'change' : 'other')));
                    ?>
                    <tr>
                        <td class="audit-nowrap"><?= esc(date('M j, Y g:i:s A', strtotime($log['created_at']))) ?></td>
                        <td class="audit-user-cell"><?= esc($log['user_name'] ?? 'System') ?></td>
                        <td class="audit-nowrap"><span class="audit-action-badge audit-action--<?= esc($actionType, 'attr') ?>"><?= esc($log['action']) ?></span></td>
                        <td class="audit-nowrap"><?= esc(($log['entity_type'] ?? '—') . (!empty($log['entity_id']) ? ' #' . $log['entity_id'] : '')) ?></td>
                        <td class="audit-details-cell"><?= view('admin/audit_logs/_details', ['detailsJson' => $log['details_json'] ?? '']) ?></td>
                        <td class="audit-nowrap audit-ip-cell"><?= esc($log['ip_address'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$logs): ?><tr><td colspan="6"><div class="audit-empty"><i class="bi bi-shield-check" aria-hidden="true"></i><strong>No audit logs found</strong><span>Try another date range or adjust your filters.</span></div></td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <nav class="audit-pager mt-3" aria-label="Audit log pages"><?= $pager->links() ?></nav>
</div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/js/reports.js') ?>"></script>
<?= $this->endSection() ?>
