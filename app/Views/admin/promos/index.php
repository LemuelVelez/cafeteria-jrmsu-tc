<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4"><div><h1 class="h3 section-title fw-bold">Promotions</h1><p class="text-secondary mb-0">Create controlled discounts and usage limits.</p></div><button class="btn btn-primary" type="button" data-create-promo data-bs-toggle="modal" data-bs-target="#promoModal"><i class="bi bi-plus-lg me-1"></i>New promo</button></div>
<div class="row g-3"><?php foreach ($promos as $promo): ?><div class="col-md-6 col-xl-4"><div class="surface-card p-4 h-100"><div class="d-flex justify-content-between"><span class="badge text-bg-primary"><?= esc($promo['code']) ?></span><span class="badge <?= $promo['is_active']?'text-bg-success':'text-bg-secondary' ?>"><?= $promo['is_active']?'Active':'Inactive' ?></span></div><h2 class="h4 section-title fw-bold mt-3"><?= $promo['discount_type']==='percentage' ? esc($promo['discount_value']).'%' : format_price($promo['discount_value']) ?> off</h2><p class="text-secondary"><?= esc($promo['description']) ?></p><div class="small text-secondary">Minimum: <?= format_price($promo['minimum_order']) ?><br>Used: <?= esc($promo['used_count']) ?> / <?= $promo['usage_limit'] ?: 'Unlimited' ?></div><button class="btn btn-sm btn-outline-primary mt-3" data-edit-promo='<?= esc(json_encode($promo),'attr') ?>'>Edit</button></div></div><?php endforeach; ?><?php if (!$promos): ?><div class="col-12"><div class="surface-card"><?= view('components/empty', ['icon'=>'bi-ticket-perforated','message'=>'Create a promotion code.']) ?></div></div><?php endif; ?></div>
<div class="modal fade" id="promoModal" tabindex="-1"><div class="modal-dialog modal-lg"><form class="modal-content" id="promoForm" action="<?= base_url('admin/promos') ?>" data-base-action="<?= esc(base_url('admin/promos'), 'attr') ?>" method="post" data-confirm="Save this promotion?" data-confirm-title="Save promotion" data-confirm-label="Save"><?= csrf_field() ?><div class="modal-header"><h2 class="modal-title h5">Promotion details</h2><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="row g-3"><div class="col-md-5"><label class="form-label">Code</label><input class="form-control text-uppercase" name="code" required></div><div class="col-md-7"><label class="form-label">Description</label><input class="form-control" name="description"></div><div class="col-md-4"><label class="form-label">Type</label><select class="form-select" name="discount_type"><option value="percentage">Percentage</option><option value="fixed">Fixed amount</option></select></div><div class="col-md-4"><label class="form-label">Value</label><input class="form-control" type="number" step="0.01" min="0.01" name="discount_value" required></div><div class="col-md-4"><label class="form-label">Minimum order</label><input class="form-control" type="number" step="0.01" min="0" name="minimum_order" value="0"></div><div class="col-md-4"><label class="form-label">Starts</label><input class="form-control" type="datetime-local" name="starts_at"></div><div class="col-md-4"><label class="form-label">Ends</label><input class="form-control" type="datetime-local" name="ends_at"></div><div class="col-md-4"><label class="form-label">Usage limit</label><input class="form-control" type="number" min="0" name="usage_limit" value="0"></div><div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="promoActive" checked><label class="form-check-label" for="promoActive">Active</label></div></div></div></div><div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save promo</button></div></form></div></div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
(() => {
    const form = document.getElementById('promoForm');
    const modal = document.getElementById('promoModal');
    const resetForm = () => {
        form.reset();
        form.action = form.dataset.baseAction;
        form.elements.discount_type.value = 'percentage';
        form.elements.minimum_order.value = 0;
        form.elements.usage_limit.value = 0;
        form.elements.is_active.checked = true;
    };

    document.querySelector('[data-create-promo]')?.addEventListener('click', resetForm);
    document.querySelectorAll('[data-edit-promo]').forEach((button) => button.addEventListener('click', () => {
        const promo = JSON.parse(button.dataset.editPromo);
        form.action = `${form.dataset.baseAction}/${promo.id}`;
        ['code', 'description', 'discount_type', 'discount_value', 'minimum_order', 'usage_limit'].forEach((name) => { form.elements[name].value = promo[name] ?? ''; });
        form.elements.starts_at.value = (promo.starts_at || '').replace(' ', 'T').slice(0, 16);
        form.elements.ends_at.value = (promo.ends_at || '').replace(' ', 'T').slice(0, 16);
        form.elements.is_active.checked = Number(promo.is_active) === 1;
        bootstrap.Modal.getOrCreateInstance(modal).show();
    }));
})();
</script>
<?= $this->endSection() ?>
