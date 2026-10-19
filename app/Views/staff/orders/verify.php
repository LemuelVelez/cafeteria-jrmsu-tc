<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="surface-card p-4 mx-auto" style="max-width:720px">
    <h1 class="h3">Verify order <?= esc($order['order_number']) ?></h1>
    <p><?= order_status_badge($order['status']) ?> · <?= esc(ucfirst($order['order_type'])) ?> · <?= format_price($order['total']) ?></p>
    <p class="text-secondary"><?= esc(date('M j, Y g:i A',strtotime($order['created_at']))) ?> · <?= esc(payment_method_label($order['payment_method'])) ?></p>
    <?php if(!in_array($order['status'],['ready_for_pickup','out_for_delivery'],true)): ?>
        <div class="alert alert-warning">This order is not in the hand-off stage. Confirm status before scanning.</div>
    <?php endif; ?>
<?= view('components/handoff_stages',['handoffs'=>$handoffs]) ?>
    <div class="table-responsive"><table class="table">
        <thead><tr><th>Line</th><th>Preparation</th></tr></thead><tbody>
        <?php foreach($items as $item): ?><tr>
            <td><strong><?= (int)$item['quantity'] ?> × <?= esc($item['product_name']) ?></strong>
                <?php foreach(json_decode($item['addons_json']??'[]',true)?:[] as $addon): ?><div class="small">+ <?= esc($addon['name']??'') ?></div><?php endforeach; ?>
                <?php if($item['notes']): ?><div class="small">Note: <?= esc($item['notes']) ?></div><?php endif; ?></td>
            <td><?= $item['prepared_at']?'Prepared':'Not checked' ?>
                <?php if(!$item['prepared_at']&&in_array($order['status'],['confirmed','preparing'],true)&&in_array($currentUser['role']??'', ['admin','cashier'],true)): ?>
                <form method="post" action="<?= base_url('staff/orders/'.$order['id'].'/prepared/'.$item['id']) ?>"><?= csrf_field() ?><button class="btn btn-outline-primary btn-sm">Mark prepared</button></form>
                <?php endif; ?>
            </td>
        </tr><?php endforeach; ?>
        </tbody></table></div>
    <?php if(in_array($order['status'],['ready_for_pickup','out_for_delivery'],true)): ?>
    <form method="post" action="<?= base_url('staff/orders/'.$order['id'].'/verify-handoff') ?>"><?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= esc($token,'attr') ?>">
        <button class="btn btn-primary">Confirm scanned order for hand-off</button>
    </form>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
