<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\CustomerDataDeletionService;

/** A requested anonymization keeps financial entries but clears optional personal data. */
class PrivacyController extends BaseController
{
    public function index(): string
    {
        $rows = db_connect()->table('users')->select('id,name,email,deletion_requested_at')
            ->where('role', 'customer')->where('deletion_requested_at !=', null)
            ->orderBy('deletion_requested_at', 'ASC')->get()->getResultArray();
        return $this->render('admin/privacy/deletion_requests', ['requests' => $rows, 'title' => 'Privacy requests']);
    }

    public function approve(int $id)
    {
        try {
            (new CustomerDataDeletionService())->approve($id);
            return redirect()->to('/admin/privacy/deletion-requests')
                ->with('success', 'Customer data anonymized; financial records retained.');
        } catch (\DomainException $error) {
            return redirect()->to('/admin/privacy/deletion-requests')->with('error', $error->getMessage());
        } catch (\Throwable $error) {
            log_message('error', 'Customer data anonymization failed for account {id}.', ['id' => $id]);
            return redirect()->to('/admin/privacy/deletion-requests')
                ->with('error', 'Could not approve this request.');
        }
    }
}
