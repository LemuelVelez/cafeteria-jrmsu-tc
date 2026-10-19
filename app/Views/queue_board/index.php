<?= $this->extend('layouts/display') ?>
<?= $this->section('content') ?>
<main class="queue-board" data-queue-board data-feed-url="<?= esc(base_url('queue-board/feed'), 'attr') ?>">
<header class="queue-header"><div class="queue-brand"><img src="<?= esc(base_url('assets/img/jrmsu-cafeteria-logo.webp'),'attr') ?>" alt="Cafeteria logo"><h1><?= esc($cafeteriaName) ?><small class="d-block fs-6 fw-normal">Order pickup queue</small></h1></div>
<div class="queue-controls"><time class="queue-clock" data-queue-clock aria-label="Current Manila time"></time><span class="queue-connection" data-queue-connection hidden>Reconnecting…</span><button class="btn btn-outline-light" type="button" data-queue-fullscreen><i class="bi bi-arrows-fullscreen"></i> Fullscreen</button></div></header>
<section class="queue-calling" aria-label="Now calling"><div class="queue-kicker">Now calling</div><strong class="queue-calling-number" data-queue-calling>—</strong></section>
<div class="queue-columns"><section class="queue-panel"><h2>Preparing</h2><div class="queue-grid" data-queue-preparing></div></section>
<section class="queue-panel queue-ready"><h2>Ready for pickup</h2><div class="queue-grid" data-queue-ready></div></section></div>
<div class="visually-hidden" data-queue-announcement aria-live="polite" aria-atomic="true"></div>
<button class="btn btn-warning btn-lg queue-unlock" type="button" data-queue-unlock><i class="bi bi-volume-up"></i> Tap to enable sound</button>
</main>
<?= $this->endSection() ?>
