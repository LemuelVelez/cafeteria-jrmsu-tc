<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\UserModel;
use App\Services\AuditLogService;
use Throwable;

class CustomerController extends BaseController
{
    public function index(): string
    {
        return $this->render('admin/customers/index', [
            'users' => (new UserModel())->where('role', 'customer')->orderBy('created_at', 'DESC')->findAll(),
            'roleLabel' => 'Customers',
        ]);
    }

    public function status(int $id)
    {
        $status = (string) $this->request->getPost('status');
        if (! in_array($status, ['active', 'inactive', 'banned'], true)) {
            return redirect()->to('/admin/customers')->with('error', 'Invalid account status.');
        }
        $db = db_connect();
        $db->transBegin();
        try {
            $customer = $db->query("SELECT * FROM users WHERE id=? AND role='customer' FOR UPDATE", [$id])->getRowArray();
            if (! $customer) {
                throw new \DomainException('Customer account not found.');
            }
            $db->table('users')->where('id', $id)->update(['status' => $status, 'session_version' => (int) ($customer['session_version'] ?? 1) + 1, 'updated_at' => date('Y-m-d H:i:s')]);
            (new AuditLogService($db))->recordChange('customer_status_change', 'user', $id, ['status'=>$customer['status']], ['status'=>$status]);
            if (! $db->transStatus()) {
                throw new \RuntimeException('Unable to update customer status.');
            }
            $db->transCommit();
            return redirect()->to('/admin/customers')->with('success', 'Account status updated.');
        } catch (Throwable $exception) {
            $db->transRollback();
            return redirect()->to('/admin/customers')->with('error', $exception->getMessage());
        }
    }

    public function revealPhone(int $id)
    {
        $customer = (new UserModel())->where('role', 'customer')->find($id);
        if (! $customer) {
            return $this->jsonError('Customer not found.', null, 404);
        }
        // Treat a PII read as an audited operation: do not reveal the value if
        // the audit transaction cannot be committed.
        $db = db_connect();
        $db->transBegin();
        try {
            (new AuditLogService($db))->record('pii_reveal', 'user', $id, ['field' => 'phone']);
            if (! $db->transStatus()) {
                throw new \RuntimeException('Unable to audit phone access.');
            }
            $db->transCommit();
        } catch (Throwable $exception) {
            $db->transRollback();
            return $this->jsonError('Phone access could not be audited.', null, 503);
        }
        return $this->jsonSuccess('Phone revealed.', ['phone' => $customer['phone'] ?: '—'])
            ->setHeader('Cache-Control', 'private, no-store');
    }
}
