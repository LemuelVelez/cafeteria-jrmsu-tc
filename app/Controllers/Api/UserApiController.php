<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\UserModel;

class UserApiController extends BaseController
{
    public function status(int $id)
    {
        $payload = $this->request->getJSON(true) ?: $this->request->getRawInput();
        $status = (string) ($payload['status'] ?? '');
        if (! in_array($status, ['active', 'inactive', 'banned'], true)) {
            return $this->jsonError('Invalid account status.');
        }

        if ($id === (int) (session()->get('user')['id'] ?? 0)) {
            return $this->jsonError('You cannot change the status of your own account.');
        }

        $model = new UserModel();
        $db=db_connect();$db->transBegin();
        try {
            $existing=$db->query('SELECT id,status,session_version FROM users WHERE id=? FOR UPDATE',[$id])->getRowArray();
            if (!$existing) {
                $db->transRollback();
                return $this->jsonError('User account not found.', null, 404);
            }
            $updates=['status'=>$status];
            if ($existing['status']!==$status) $updates['session_version']=(int)$existing['session_version']+1;
            if (! $model->update($id,$updates)) throw new \DomainException('The account status could not be updated.');
            (new \App\Services\AuditLogService($db))->recordChange('user_status_changed','user',$id,
                $existing,['status'=>$status],(int)(session()->get('user')['id']??0));
            if (!$db->transStatus()) throw new \RuntimeException('Unable to save account status.');
            $db->transCommit();
        } catch (\Throwable $error) {
            $db->transRollback();
            return $this->jsonError($error->getMessage(),null,422);
        }

        return $this->jsonSuccess('Account status updated.');
    }
}
