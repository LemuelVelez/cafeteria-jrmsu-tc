<div class="table-responsive"><table class="table table-bordered table-sm align-middle" style="font-size:.78rem"><thead><tr>
<?php foreach(['Items','Beginning Inventory','Additional','Total Stock','Withdrawn','Ending Inventory','Number of Items Sold','POS Sold','Variance','Cost of Item Sold','Total Cost','Selling Price','Total Amount of Items Sold','Net Profit','Remarks'] as $heading): ?><th><?= esc($heading) ?></th><?php endforeach; ?>
</tr></thead><tbody>
<?php foreach($report['groups'] as $category=>$group): ?>
<tr class="table-secondary"><th colspan="15"><?= esc($category) ?></th></tr>
<?php foreach($group['rows'] as $r): ?>
<tr <?= $r['variance']!==0||$r['invalid_bundle']?'class="table-warning"':'' ?>>
<td><?= esc($r['name']) ?> <small class="text-secondary">(<?= esc($r['unit']) ?>)</small></td>
<td><?= (int)$r['beginning'] ?></td><td><?= (int)$r['additional'] ?></td><td><?= (int)$r['total_stock'] ?></td><td><?= (int)$r['withdrawn'] ?></td><td><?= (int)$r['ending'] ?> <small>(<?= esc($r['ending_source']) ?>)</small></td>
<td><?= (int)$r['sold'] ?></td><td><?= (int)$r['pos_sold'] ?></td><td class="<?= $r['variance']!==0?'text-danger fw-bold':'' ?>"><?= (int)$r['variance'] ?></td>
<td><?= $r['unit_cost']===null?'—':esc(format_price($r['unit_cost'])) ?></td><td><?= $r['total_cost']===null?'—':esc(format_price($r['total_cost'])) ?></td><td><?= esc($r['price_label']) ?></td>
<td><?= $r['amount']===null?'—':esc(format_price($r['amount'])) ?></td><td><?= $r['profit']===null?'—':esc(format_price($r['profit'])) ?></td>
<td><?= esc($r['remarks']) ?><?= $r['invalid_bundle']? ' Bundle remainder has no single-piece price.':'' ?></td></tr>
<?php endforeach; ?>
<tr class="table-light fw-semibold"><td><?= esc($category) ?> subtotal</td><td colspan="5"></td><td><?= (int)$group['totals']['sold'] ?></td><td><?= (int)$group['totals']['pos_sold'] ?></td><td><?= (int)$group['totals']['variance'] ?></td><td></td><td><?= esc(format_price($group['totals']['total_cost'])) ?></td><td></td><td><?= esc(format_price($group['totals']['amount'])) ?></td><td><?= esc(format_price($group['totals']['profit'])) ?></td><td>Uncosted revenue: <?= esc(format_price($group['totals']['uncosted_revenue'])) ?></td></tr>
<?php endforeach; ?>
<tr><th>Credit Payment</th><td colspan="11">Manually entered credits, not POS payments</td><td><?= esc(format_price($report['credit_total'])) ?></td><td colspan="2"></td></tr>
<tr class="table-dark"><th>GRAND TOTAL</th><td colspan="5"></td><td><?= (int)$report['totals']['sold'] ?></td><td><?= (int)$report['totals']['pos_sold'] ?></td><td><?= (int)$report['totals']['variance'] ?></td><td></td><td><?= esc(format_price($report['totals']['total_cost'])) ?></td><td></td><td><?= esc(format_price($report['totals']['amount'])) ?></td><td><?= esc(format_price($report['totals']['profit'])) ?></td><td>Uncosted: <?= esc(format_price($report['totals']['uncosted_revenue'])) ?></td></tr>
</tbody></table></div>
<p class="small">Prepared by: <?= esc($report['check']['prepared_name']??'Pending') ?> · <?= esc($report['check']['prepared_at']??'—') ?> &nbsp; Checked by: <?= esc($report['check']['checked_name']??'Pending') ?> · <?= esc($report['check']['checked_at']??'—') ?></p>
<?php if($report['changed_since_check']): ?><p class="text-danger fw-bold">Ledger movements were entered after this report was checked. Review the updated figures and variances.</p><?php endif; ?>
