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
            if ($line['prepared_at'] !== null) throw new \DomainException('This item has already been checked.');
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
        $actor=session()->get('user');
        if (($actor['role']??'')==='rider' && ($order['order_type']!=='delivery'
            || (int)($order['rider_id']??0)!==(int)($actor['id']??0))) {
            throw PageNotFoundException::forPageNotFound();
        }
        return $this->render('staff/orders/ticket',['title'=>'Kitchen ticket','order'=>$order,'items'=>(new OrderItemModel())->where('order_id',$id)->findAll(),'token'=>(new OrderQrService())->token($order), 'handoffs'=>$db->table('order_handoffs h')->select('h.*,u.name verifier_name')->join('users u','u.id=h.verified_by','left')->where('order_id',$id)->get()->getResultArray()]);
    }
    public function handoff(int $id)
    {
        $actor=session()->get('user');
        $role=(string)($actor['role']??'');
        $actorId=(int)($actor['id']??0);
        $db=db_connect();$db->transBegin();
        try {
            $order=$db->query('SELECT * FROM orders WHERE id=? FOR UPDATE',[$id])->getRowArray();
            if (!$order) throw new \DomainException('Order not found.');
            $delivery=$order['order_type']==='delivery';
            $stage=$delivery ? ($order['status']==='ready_for_pickup'?'dispatch':'delivery') : 'pickup';
            if ($delivery && $stage==='dispatch') {
                if (!in_array($role,['cashier','admin'],true)) throw new \DomainException('Cashier or admin must independently verify rider departure.');
                if (empty($order['rider_id'])) throw new \DomainException('Assign a rider before dispatch verification.');
                if ($db->table('order_items')->where('order_id',$id)->where('prepared_at',null)->countAllResults()) {
                    throw new \DomainException('Every item must be checked as prepared.');
                }
            } elseif ($delivery && $stage==='delivery') {
                if ($order['status']!=='out_for_delivery' || $role!=='rider'
                    || (int)$order['rider_id']!==$actorId) {
                    throw new \DomainException('Only the assigned rider can verify delivery.');
                }
            } elseif ($order['status']!=='ready_for_pickup' || !in_array($role,['cashier','admin'],true)) {
                throw new \DomainException('Pickup is verified at the counter by cashier or admin.');
            }
            if (!service('throttler')->check('handoff-'.$id.'-'.$this->request->getIPAddress(),5,MINUTE)) {
                throw new \DomainException('Too many verification attempts; try again later.');
            }
            $token=trim((string)$this->request->getPost('token'));
            if (str_contains($token,'/')) {
                $path=parse_url($token,PHP_URL_PATH);
                $token=is_string($path)?basename($path):'';
            }
            $code=trim((string)$this->request->getPost('code'));
            $qr=new OrderQrService();
            $signed=$token!==''?($stage === 'delivery' ? $qr->verifyDeliveryToken($token) : $qr->verify($token)):null;
            $validToken=$signed && (int)$signed['id']===$id;
            $validCode=preg_match('/^[0-9]{4}$/',$code)===1;
            $validPickup=$validCode && hash_equals($qr->pickupCode($order),$code);
            $validDelivery=$validCode && !empty($order['delivery_code_hash'])
                && password_verify($code,(string)$order['delivery_code_hash']);
            if (!$validToken && !($stage==='delivery'?$validDelivery:($stage==='pickup' && $validPickup))) {
                throw new \DomainException('Invalid or expired verification code.');
            }
            $method=$validToken?'qr':($stage==='delivery'?'delivery_code':'pickup_code');
            if ($db->table('order_handoffs')->where(['order_id'=>$id,'stage'=>$stage])->countAllResults()) {
                throw new \DomainException('This hand-off stage has already been verified.');
            }
            $now=date('Y-m-d H:i:s');
            $db->table('order_handoffs')->insert([
                'order_id'=>$id,'stage'=>$stage,'verified_by'=>$actorId,
                'rider_id'=>$delivery?(int)$order['rider_id']:null,'method'=>$method,'created_at'=>$now,
            ]);
            // Backward-compatible last verification; the stage table retains each separate event.
            $db->table('orders')->where('id',$id)->update([
                'handoff_verified_by'=>$actorId,'handoff_verified_at'=>$now,'handoff_method'=>$method,
            ]);
            (new AuditLogService($db))->record('handoff_'.$stage.'_verified','order',$id,
                ['stage'=>$stage,'method'=>$method],$actorId);
            if (!$db->transStatus()) throw new \RuntimeException('Handoff verification failed.');
            $db->transCommit();
            return redirect()->back()->with('success','Hand-off stage verified.');
        } catch (\Throwable $error) {
            $db->transRollback();
            return redirect()->back()->with('error',$error->getMessage());
        }
    }
}
