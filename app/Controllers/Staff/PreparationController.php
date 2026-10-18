<?php
namespace App\Controllers\Staff;
use App\Controllers\BaseController;
use App\Services\OrderQrService;
use App\Services\AuditLogService;
use App\Models\OrderItemModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class PreparationController extends BaseController
{
    public function check(int $orderId, int $itemId)
    {
        $actor=session()->get('user');
        if (! in_array($actor['role'], ['admin','cashier'],true)) return redirect()->back()->with('error','Kitchen staff only.');
        $db=db_connect();$db->transBegin();
        try {
            $order=$db->query('SELECT * FROM orders WHERE id=? FOR UPDATE',[$orderId])->getRowArray();
            $line=$db->query('SELECT * FROM order_items WHERE id=? AND order_id=? FOR UPDATE',[$itemId,$orderId])->getRowArray();
            if (!$order||!$line||!in_array($order['status'],['confirmed','preparing'],true)) throw new \DomainException('Order line cannot be checked at this stage.');
            $db->table('order_items')->where('id',$itemId)->update(['prepared_at'=>date('Y-m-d H:i:s'),'prepared_by'=>(int)$actor['id']]);
            (new AuditLogService($db))->record('order_item_prepared','order_item',$itemId,['order_id'=>$orderId],(int)$actor['id']);
            if (!$db->transStatus()) throw new \RuntimeException('Unable to save preparation check.');
            $db->transCommit();return redirect()->back()->with('success','Item checked.');
        }catch(\Throwable $e){$db->transRollback();return redirect()->back()->with('error',$e->getMessage());}
    }
    public function ticket(int $id): string
    {
        $db=db_connect();$order=$db->table('orders')->where('id',$id)->get()->getRowArray();
        if (!$order)throw PageNotFoundException::forPageNotFound();
        return $this->render('staff/orders/ticket',['title'=>'Kitchen ticket','order'=>$order,'items'=>(new OrderItemModel())->where('order_id',$id)->findAll(),'token'=>(new OrderQrService())->token($order)]);
    }
    public function handoff(int $id)
    {
        $actor=session()->get('user');$db=db_connect();$db->transBegin();
        try {
            $order=$db->query('SELECT * FROM orders WHERE id=? FOR UPDATE',[$id])->getRowArray();
            if (!$order) throw new \DomainException('Order not found.');
            $role=$actor['role'];$status=$order['status'];
            $delivery=$order['order_type']==='delivery';
            if ($delivery) {
                if ($role==='rider' && (int)$order['rider_id']!==(int)$actor['id']) throw new \DomainException('Not your delivery.');
                if ($status==='ready_for_pickup' && !in_array($role,['admin','cashier','rider'],true)) throw new \DomainException('Staff role required.');
                if ($status==='out_for_delivery' && $role!=='rider') throw new \DomainException('Assigned rider confirmation required.');
                if (!in_array($status,['ready_for_pickup','out_for_delivery'],true)) throw new \DomainException('Unexpected delivery status.');
            } elseif ($status!=='ready_for_pickup' || !in_array($role,['admin','cashier'],true)) {
                throw new \DomainException('Pickup verification is only available when ready.');
            }
            if (!service('throttler')->check('handoff-'.$id.'-'.$this->request->getIPAddress(),5,MINUTE)) throw new \DomainException('Too many verification attempts.');
            $token=trim((string)$this->request->getPost('token'));
            if (str_contains($token, '/')) {
                $path=parse_url($token, PHP_URL_PATH);
                $token=is_string($path)?basename($path):'';
            }
            $code=trim((string)$this->request->getPost('code'));
            $signed=$token!=='' ? (new OrderQrService())->verify($token) : null;
            $validToken=$signed && (int)$signed['id']===$id;
            $validCode=$code!=='' && preg_match('/^[0-9]{4}$/',$code) && hash_equals((new OrderQrService())->pickupCode($order),$code);
            if (!$validToken && (!$validCode || $delivery)) throw new \DomainException('Invalid or expired verification code.');
            $method=$delivery && $status==='out_for_delivery'?'delivery_confirmation':($validToken?'qr':'pickup_code');
            $db->table('orders')->where('id',$id)->update(['handoff_verified_by'=>(int)$actor['id'],'handoff_verified_at'=>date('Y-m-d H:i:s'),'handoff_method'=>$method]);
            (new AuditLogService($db))->record('handoff_verified','order',$id,['method'=>$method],(int)$actor['id']);
            if (!$db->transStatus())throw new \RuntimeException('Unable to confirm hand-off.');
            $db->transCommit();return redirect()->back()->with('success','Order hand-off verified. You may update the status.');
        }catch(\Throwable $e){$db->transRollback();return redirect()->back()->with('error',$e->getMessage());}
    }
}
