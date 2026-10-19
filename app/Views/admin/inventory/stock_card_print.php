<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Stock card · <?= esc($card['ledger']) ?> #<?= (int) $card['id'] ?></title>
<link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap/bootstrap.min.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/reports-print.css') ?>" media="print">
<style>body{margin:1.5rem;font:14px system-ui,sans-serif}table{width:100%;border-collapse:collapse}th,td{border:1px solid #aaa;padding:5px;text-align:left}thead{display:table-header-group}tr{break-inside:avoid}.numeric{text-align:right}@media print{body{margin:0}.print-actions{display:none}}</style>
</head><body>
<div class="print-actions mb-3"><button class="btn btn-primary" type="button" onclick="window.print()">Print</button></div>
<header><h1 class="h4">JRMSU–TC Cafeteria · <?= esc(ucfirst($card['ledger'])) ?> stock card #<?= (int) $card['id'] ?></h1>
<p>Period: <?= esc($card['from']) ?> to <?= esc($card['to']) ?> · Movement: <?= esc($card['type'] ?: 'All') ?><br>
Generated: <?= esc(date('Y-m-d H:i:s')) ?> · By: <?= esc($generatedBy) ?></p></header>
<p><strong>Opening balance: <?= esc($card['opening']) ?></strong> · <strong>Closing balance: <?= esc($card['closing']) ?></strong> · Movements: <?= (int) $card['total'] ?></p>
<table><thead><tr><th>Date</th><th>Movement</th><th class="numeric">Change</th><th class="numeric">Running balance</th><th>Actor</th><th>Reference / note</th></tr></thead><tbody>
<tr><td colspan="3">Opening balance before <?= esc($card['from']) ?></td><td class="numeric"><?= esc($card['opening']) ?></td><td colspan="2"></td></tr>
<?php foreach ($rows as $row): ?>
<tr><td><?= esc($row['created_at']) ?></td><td><?= esc($row['movement_type']) ?></td><td class="numeric"><?= esc($row['quantity']) ?></td><td class="numeric"><?= esc($row['running_balance']) ?></td><td><?= esc($row['user_name'] ?? 'System') ?></td><td><?= esc(trim(($row['reference'] ?? '') . ' ' . ($row['note'] ?? ''))) ?></td></tr>
<?php endforeach; ?>
<tr><td colspan="3"><strong>Closing balance at <?= esc($card['to']) ?></strong></td><td class="numeric"><strong><?= esc($card['closing']) ?></strong></td><td colspan="2"></td></tr>
</tbody></table></body></html>
