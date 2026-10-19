<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-4"><div><h1 class="h3 section-title fw-bold mb-1">Notifications</h1><p class="text-secondary mb-0">Order, assignment, and inventory alerts.</p></div><button class="btn btn-outline-primary" type="button" data-notifications-read-all><i class="bi bi-check2-all me-1" aria-hidden="true"></i>Mark all as read</button></div>
<nav class="d-flex flex-wrap gap-2 mb-3" aria-label="Notification filters">
<?php foreach([''=>['All','bi-grid'],'unread'=>['Unread','bi-envelope'],'order'=>['Orders','bi-receipt'],'inventory'=>['Inventory','bi-box-seam'],'system'=>['System','bi-gear']] as $key=>[$label,$icon]): ?>
<a class="btn btn-sm <?= $filter===$key?'btn-primary':'btn-outline-secondary' ?>" href="<?= base_url('notifications'.($key!==''?'?filter='.$key:'')) ?>"><i class="bi <?= esc($icon, 'attr') ?> me-1" aria-hidden="true"></i><?= esc($label) ?></a>
<?php endforeach; ?>
</nav>
<div class="surface-card overflow-hidden">
<?php if ($notifications): ?><?php foreach ($notifications as $notification): ?><a class="d-block p-4 border-bottom text-decoration-none text-reset <?= empty($notification['read_at']) ? 'bg-light' : '' ?>" href="<?= esc($notification['link'] ? base_url(ltrim($notification['link'],'/')) : '#', 'attr') ?>" data-notification-id="<?= (int)$notification['id'] ?>"><div class="d-flex justify-content-between gap-3"><div><div class="fw-semibold"><?= esc($notification['title']) ?></div><div class="text-secondary"><?= esc($notification['message']) ?></div></div><small class="text-secondary text-nowrap"><?= esc(time_ago($notification['created_at'])) ?></small></div></a><?php endforeach; ?><?php else: ?><?= view('components/empty', ['icon'=>'bi-bell','message'=>'No notifications yet.']) ?><?php endif; ?>
</div>
<div class="mt-3"><?= $pager->links() ?></div>
<?= $this->endSection() ?>
