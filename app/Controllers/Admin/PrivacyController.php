<?php
namespace App\Controllers\Admin;
use App\Controllers\BaseController;
use App\Services\AuditLogService;

/** Customer-initiated deletion, approved by an administrator without removing financial records. */
class PrivacyController extends BaseController
{
    public function index(): string
    {
        $rows=db_connect()->table('users')->select('id,name,email,deletion_requested_at')
            ->where('role','customer')->where('deletion_requested_at !=',null)
            ->orderBy('deletion_requested_at','ASC')->get()->getResultArray();
        return $this->render('admin/privacy/deletion_requests', ['requests'=>$rows,'title'=>'Privacy requests']);
    }
    public function approve(int $id)
    {
        $db=db_connect();$db->transBegin();
        try {
            $user=$db->query('SELECT * FROM users WHERE id=? AND role=? FOR UPDATE',[$id,'customer'])->getRowArray();
            if (!$user||empty($user['deletion_requested_at']))throw new \DomainException('No active customer deletion request.');
            $random='deleted-'.$id.'-'.bin2hex(random_bytes(6));
            $db->table('users')->where('id',$id)->update([
                'name'=>'Deleted customer', 'email'=>$random.'@invalid.local', 'phone'=>null,
                'address'=>null,'avatar'=>null,'password_hash'=>password_hash(bin2hex(random_bytes(40)), PASSWORD_DEFAULT),
                'status'=>'inactive', 'failed_login_attempts'=>0,'locked_until'=>null,
                'session_version'=>((int)$user['session_version'])+1,'totp_secret'=>null,
                'totp_recovery_hashes'=>null,'notification_email_prefs'=>null,'deletion_requested_at'=>null,
            ]);
            // Keep orders and line-item financial records; scrub any optional delivery PII.
            $db->table('orders')->where('customer_id',$id)->update(['delivery_address'=>null]);
            (new AuditLogService($db))->record('personal_data_deletion_approved','user',$id,['preserved'=>'financial order history']);
            if (!$db->transStatus())throw new \RuntimeException('Unable to anonymize account.');
            $db->transCommit();return redirect()->to('/admin/privacy/deletion-requests')->with('success','Customer data anonymized; financial records retained.');
        }catch(\Throwable $e){$db->transRollback();return redirect()->to('/admin/privacy/deletion-requests')->with('error','Could not approve this request.');}
    }
}
