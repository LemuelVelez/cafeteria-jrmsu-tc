<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<h1 class="h3 fw-bold">USDA FoodData Central lookup</h1><p class="text-secondary">Server-side lookup using the public DEMO_KEY. Search responses and food details are cached in MySQL for offline reuse. PhilFCT-sourced ingredients can also be entered manually in Costing.</p>
<form method="get" class="d-flex gap-2 mb-4" action="<?= base_url('admin/costing/nutrition-search') ?>"><label class="visually-hidden" for="foodQuery">Food name</label><input id="foodQuery" class="form-control" name="q" maxlength="80" value="<?= esc($query,'attr') ?>" placeholder="Search USDA ingredients" required><button class="btn btn-primary">Search</button></form>
<?php if($error): ?><div class="alert alert-warning"><?= esc($error) ?></div><?php endif; ?>
<div class="table-responsive"><table class="table"><thead><tr><th>Ingredient</th><th>Data type</th><th>Source ID</th><th>Import into recipe inventory</th></tr></thead><tbody>
<?php foreach($rows as $food): ?><tr><td><?= esc($food['description']) ?></td><td><?= esc($food['dataType']) ?></td><td><?= (int)$food['fdcId'] ?></td><td>
<form class="d-flex flex-wrap gap-2" action="<?= base_url('admin/costing/nutrition-import') ?>" method="post"><?= csrf_field() ?><input type="hidden" name="fdc_id" value="<?= (int)$food['fdcId'] ?>"><input type="hidden" name="purchase_unit" value="g"><label class="form-label">Cost per g (PHP)<input type="number" min="0" step="0.0001" value="0" name="cost_per_unit" class="form-control form-control-sm" required></label><button class="btn btn-outline-primary btn-sm align-self-end">Import</button></form>
</td></tr><?php endforeach; ?>
</tbody></table></div>
<a class="btn btn-outline-secondary" href="<?= base_url('admin/costing') ?>">Back to costing</a>
<?= $this->endSection() ?>
