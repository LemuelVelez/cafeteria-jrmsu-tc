<?php
namespace App\Controllers\Account;
use App\Controllers\BaseController;
use App\Models\UserModel;
use App\Models\OrderModel;
use App\Services\AuditLogService;
class PrivacyController extends BaseController
{
    public function export()
    {
        $id=(int)session()->get('user')['id'];
        $user=(new UserModel())->find($id);
        if (!$user)return redirect()->to('/settings');
        $data=['profile'=>array_intersect_key($user,array_flip(['name','email','phone','address','privacy_consent_at'])),
            'orders'=>(new OrderModel())->where('customer_id',$id)->findAll()];
        (new AuditLogService())->record('personal_data_export','user',$id);
        return $this->response->setHeader('Content-Type','application/json; charset=utf-8')->setHeader('Content-Disposition','attachment; filename="my-cafeteria-data.json"')->setBody(json_encode($data,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    }
    public function requestDeletion()
    {
        $id=(int)session()->get('user')['id'];
        $db=db_connect();$db->transBegin();
        try {
            $user=$db->query('SELECT id FROM users WHERE id=? FOR UPDATE',[$id])->getRowArray();
            if (!$user)throw new \DomainException('Account not found.');
            $db->table('users')->where('id',$id)->update(['deletion_requested_at'=>date('Y-m-d H:i:s')]);
            (new AuditLogService($db))->record('account_deletion_requested','user',$id);
            $db->transCommit();return redirect()->to('/settings')->with('success','Your deletion request has been sent for review.');
        }catch(\Throwable $e){$db->transRollback();return redirect()->to('/settings')->with('error','Unable to submit request.');}
    }
}
