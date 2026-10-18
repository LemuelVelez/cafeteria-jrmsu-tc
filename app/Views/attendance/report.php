<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<h1 class="h3">Estimated staff hours and wages</h1><p><strong>ESTIMATE ONLY.</strong> Excludes statutory deductions for tax, SSS, PhilHealth and Pag-IBIG.</p>
<form method="get" class="d-flex gap-2"><input type="date" name="from" value="<?= esc($from) ?>" class="form-control"><input type="date" name="to" value="<?= esc($to) ?>" class="form-control"><button class="btn btn-primary">Filter</button><a class="btn btn-outline-secondary" href="<?= base_url('admin/attendance/report?from='.$from.'&to='.$to.'&format=csv') ?>">CSV</a></form>
<div class="table-responsive"><table class="table"><thead><tr><?php if($rows): ?><?php foreach(array_keys($rows[0]) as $k): ?><th><?= esc(ucwords(str_replace('_',' ',$k))) ?></th><?php endforeach; ?><?php endif; ?></tr></thead><tbody><?php foreach($rows as $row): ?><tr><?php foreach($row as $value): ?><td><?= esc($value) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div><button type="button" data-print-page class="btn btn-primary">Print report</button>
<?= $this->endSection() ?>
