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
        if (! $model->find($id)) {
            return $this->jsonError('User account not found.', null, 404);
        }

        if (! $model->update($id, ['status' => $status])) {
            return $this->jsonError('The account status could not be updated.', $model->errors());
        }

        return $this->jsonSuccess('Account status updated.');
    }
}
