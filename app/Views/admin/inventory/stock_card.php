<?= $this->extend('layouts/main') ?>
<?= $this->section('styles') ?><link href="<?= base_url('assets/css/reports-print.css') ?>" rel="stylesheet" media="print"><?= $this->endSection() ?>
<?= $this->section('content') ?>
<h1 class="h4 mb-3">Stock card · <?= esc(ucfirst($card['ledger'])) ?> #<?= (int)$card['id'] ?></h1>
<form class="surface-card p-3 row g-2 mb-3 report-controls" method="get">
<div class="col-sm-3"><label class="form-label">Ledger<select class="form-select" name="ledger" onchange="this.form.submit()"><option value="product" <?= $card['ledger']==='product'?'selected':'' ?>>Products</option><option value="ingredient" <?= $card['ledger']==='ingredient'?'selected':'' ?>>Ingredients</option></select></label></div>
<div class="col-sm-3"><label class="form-label">Item<select class="form-select" name="<?= $card['ledger']==='product'?'product_id':'ingredient_id' ?>"><?php foreach($items as $item): ?><option value="<?= (int)$item['id'] ?>" <?= (int)$card['id']===(int)$item['id']?'selected':'' ?>><?= esc($item['name']) ?></option><?php endforeach; ?></select></label></div>
<div class="col-sm-2"><label class="form-label">From<input class="form-control" type="date" name="from" value="<?= esc($card['from'],'attr') ?>"></label></div>
<div class="col-sm-2"><label class="form-label">To<input class="form-control" type="date" name="to" value="<?= esc($card['to'],'attr') ?>"></label></div>
<div class="col-sm-2"><label class="form-label">Movement type<input class="form-control" name="movement_type" value="<?= esc($card['type'],'attr') ?>" placeholder="All"></label></div>
<div class="col-12"><button class="btn btn-primary">Filter</button> <a class="btn btn-outline-secondary" href="<?= base_url(($canEdit?'admin':'cashier').'/inventory/stock-card/export?'.http_build_query(['ledger'=>$card['ledger'],($card['ledger']==='product'?'product_id':'ingredient_id')=>$card['id'],'from'=>$card['from'],'to'=>$card['to'],'movement_type'=>$card['type']])) ?>">Download CSV</a> <button class="btn btn-outline-secondary" type="button" data-print-page>Print</button></div>
</form>
<header class="d-none d-print-block"><h2>JRMSU–TC Cafeteria Stock Card</h2><p><?= esc($card['from']) ?> to <?= esc($card['to']) ?> · Generated <?= esc(date('Y-m-d H:i')) ?></p></header>
<div class="mb-3"><strong>Opening balance:</strong> <?= esc($card['opening']) ?> · <strong>Closing balance:</strong> <?= esc($card['closing']) ?> · <strong>Movements:</strong> <?= (int)$card['total'] ?></div>
<div class="table-responsive"><table class="table"><thead><tr><th>Date</th><th>Movement</th><th>Change</th><th>Running balance</th><th>Actor</th><th>Reference / note</th></tr></thead><tbody>
<tr class="table-light"><td colspan="3">Balance before <?= esc($card['from']) ?></td><td><?= esc($card['opening']) ?></td><td colspan="2"></td></tr>
<?php foreach($card['rows'] as $row): ?><tr><td><?= esc($row['created_at']) ?></td><td><?= esc($row['movement_type']) ?></td><td><?= esc($row['quantity']) ?></td><td><?= esc($row['running_balance']) ?></td><td><?= esc($row['user_name']??'System') ?></td><td><?= esc(trim(($row['reference']??'').' '.($row['note']??''))) ?></td></tr><?php endforeach; ?>
<tr class="table-light"><td colspan="3"><strong>Closing balance at <?= esc($card['to']) ?></strong></td><td><strong><?= esc($card['closing']) ?></strong></td><td colspan="2"></td></tr>
</tbody></table></div><div class="report-controls"><?= $pagerLinks ?></div>
<?= $this->endSection() ?>
