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
        (new UserModel())->update($id, ['status' => $status]);
        return $this->jsonSuccess('Account status updated.');
    }
}
