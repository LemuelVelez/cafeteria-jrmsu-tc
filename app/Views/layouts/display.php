<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="cafeteria-logo-url" content="<?= esc(base_url('assets/img/jrmsu-cafeteria-logo.webp'), 'attr') ?>">
<title>Queue display · <?= esc($cafeteriaName) ?></title>
<link rel="icon" href="<?= esc(base_url('favicon.ico'),'attr') ?>">
<link rel="stylesheet" href="<?= esc(base_url('assets/vendor/bootstrap/bootstrap.min.css'),'attr') ?>">
<link rel="stylesheet" href="<?= esc(base_url('assets/vendor/bootstrap-icons/bootstrap-icons.min.css'),'attr') ?>">
<link rel="stylesheet" href="<?= esc(base_url('assets/css/queue-board.css') . '?v=20261020b','attr') ?>">
</head><body><?= $this->renderSection('content') ?>
<script src="<?= esc(base_url('assets/vendor/bootstrap/bootstrap.bundle.min.js'),'attr') ?>"></script>
<script src="<?= esc(base_url('assets/js/image-fallback.js'),'attr') ?>"></script>
<script src="<?= esc(base_url('assets/js/queue-sound.js'),'attr') ?>"></script>
<script src="<?= esc(base_url('assets/js/queue-board.js'),'attr') ?>"></script>
</body></html>
