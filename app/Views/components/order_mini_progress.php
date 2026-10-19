<?php
/** @var array $order */
$steps = ['pending','confirmed','preparing','ready_for_pickup'];
$steps[] = $order['order_type'] === 'delivery' ? 'out_for_delivery' : 'completed';
if ($order['order_type'] === 'delivery') $steps[] = 'delivered';
$cancelled = $order['status'] === 'cancelled';
$at = $cancelled ? ($order['cancelled_at_status'] ?? 'pending') : $order['status'];
$step = array_search($at,$steps,true);
if ($step === false) $step = 0;
$step++;
$total = count($steps);
$label = \App\Enums\OrderStatus::tryFrom($at)?->label() ?? 'Pending';
$announcement = 'Step ' . $step . ' of ' . $total . ': ' . $label . ($cancelled ? ' (cancelled)' : '');
?>
<div class="mt-2" role="img" aria-label="<?= esc($announcement, 'attr') ?>">
    <div class="d-flex gap-1 mb-1" aria-hidden="true">
        <?php for ($n=1;$n<=$total;$n++): ?><div class="rounded flex-fill <?= $n<=$step ? ($cancelled?'bg-danger':'bg-success') : 'bg-secondary-subtle' ?>" style="height:5px"></div><?php endfor; ?>
    </div>
    <small class="text-secondary"><?= esc($announcement) ?></small>
</div>
