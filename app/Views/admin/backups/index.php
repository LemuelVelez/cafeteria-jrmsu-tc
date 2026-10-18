<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<h1 class="h3">Database backups</h1><p>Stored outside the public webroot. Restoration requires command-line confirmation.</p><form action="<?= base_url('admin/backups') ?>" method="post" class="mb-4"><?= csrf_field() ?><button class="btn btn-primary">Create backup now</button></form>
<div class="table-responsive"><table class="table"><thead><tr><th>File</th><th>Size (bytes)</th><th>SHA-256</th><th>Download</th></tr></thead><tbody><?php foreach($files as $f): ?><tr><td><?= esc($f['name']) ?></td><td><?= (int)$f['size'] ?></td><td><?= $f['valid']?'Verified':'Invalid' ?></td><td><?php if($f['valid']): ?><a href="<?= base_url('admin/backups/'.$f['name']) ?>">Download</a><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div>
<?= $this->endSection() ?>
