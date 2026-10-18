<?php
namespace App\Controllers\Customer;
use App\Controllers\BaseController;
use App\Models\OrderItemModel;
use App\Models\OrderModel;
use App\Services\OrderQrService;
use CodeIgniter\Exceptions\PageNotFoundException;
class ReceiptController extends BaseController
{
    public function issue(int $id)
    {
        $reason=(string)$this->request->getPost('reason');$userId=(int)session()->get('user')['id'];
        if(!in_array($reason,['missing_item','wrong_item','quality'],true))return redirect()->back()->with('error','Invalid issue type.');
        $db=db_connect();$db->transBegin();
        try{
            $order=$db->query('SELECT id FROM orders WHERE id=? AND customer_id=? FOR UPDATE',[$id,$userId])->getRowArray();
            if(!$order)throw new \DomainException('Order not found.');
            $db->table('order_issues')->insert(['order_id'=>$id,'user_id'=>$userId,'reason'=>$reason,'details'=>mb_substr(trim((string)$this->request->getPost('details')),0,500),'created_at'=>date('Y-m-d H:i:s')]);
            (new \App\Services\AuditLogService($db))->record('order_issue_reported','order',$id,['reason'=>$reason],$userId);
            $db->transCommit();return redirect()->back()->with('success','Issue reported to staff.');
        }catch(\Throwable $e){$db->transRollback();return redirect()->back()->with('error',$e->getMessage());}
    }
    public function show(int $id): string
    {
        $order=(new OrderModel())->where('id',$id)->where('customer_id',(int)session()->get('user')['id'])->first();
        if (!$order)throw PageNotFoundException::forPageNotFound();
        return $this->render('customer/orders/receipt',['title'=>'Order receipt','order'=>$order,'items'=>(new OrderItemModel())->where('order_id',$id)->findAll(),'token'=>(new OrderQrService())->token($order)]);
    }
}
