<?php

namespace App\Controllers\Account;

use App\Controllers\BaseController;
use App\Services\AuditLogService;
use App\Services\CustomerDataExportService;

class PrivacyController extends BaseController
{
    public function export()
    {
        $id = (int) (session()->get('user')['id'] ?? 0);
        try {
            $data = (new CustomerDataExportService())->export($id);
        } catch (\Throwable $error) {
            log_message('error', 'Personal data export failed for user {id}.', ['id' => $id]);
            return redirect()->to('/settings')->with('error', 'Unable to export personal data.');
        }
        if ($data === null) {
            return redirect()->to('/settings')->with('error', 'Account not found.');
        }
        return $this->response
            ->setHeader('Content-Type', 'application/json; charset=utf-8')
            ->setHeader('Content-Disposition', 'attachment; filename="my-cafeteria-data.json"')
            ->setHeader('Cache-Control', 'private, no-store, max-age=0')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
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
            if (!$db->transStatus()) throw new \RuntimeException('Unable to save request.');
            $db->transCommit();return redirect()->to('/settings')->with('success','Your deletion request has been sent for review.');
        }catch(\Throwable $e){$db->transRollback();return redirect()->to('/settings')->with('error','Unable to submit request.');}
    }
}
