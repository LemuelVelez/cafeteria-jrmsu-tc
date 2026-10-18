<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\PromoModel;
use Throwable;
use App\Services\AuditLogService;

class PromoController extends BaseController
{
    public function index(): string
    {
        return $this->render('admin/promos/index', [
            'promos' => (new PromoModel())->orderBy('created_at', 'DESC')->findAll(),
        ]);
    }

    public function save(?int $id = null)
    {
        if (! $this->validate([
            'code' => 'required|alpha_numeric_punct|max_length[40]',
            'discount_type' => 'required|in_list[fixed,percentage]',
            'discount_value' => 'required|decimal|greater_than[0]',
            'minimum_order' => 'required|decimal|greater_than_equal_to[0]',
            'usage_limit' => 'required|integer|greater_than_equal_to[0]',
        ])) {
            return redirect()->to('/admin/promos')
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $model = new PromoModel();
        if ($id !== null && ! $model->find($id)) {
            return redirect()->to('/admin/promos')->with('error', 'Promotion not found.');
        }

        $discountType = (string) $this->request->getPost('discount_type');
        $discountValue = (float) $this->request->getPost('discount_value');
        if ($discountType === 'percentage' && $discountValue > 100) {
            return redirect()->to('/admin/promos')
                ->withInput()
                ->with('error', 'Percentage discounts cannot be greater than 100%.');
        }

        $startsAtInput = trim((string) $this->request->getPost('starts_at'));
        $endsAtInput = trim((string) $this->request->getPost('ends_at'));
        $startsAt = $this->normalizeDateTime($startsAtInput);
        $endsAt = $this->normalizeDateTime($endsAtInput);
        if (($startsAtInput !== '' && $startsAt === null) || ($endsAtInput !== '' && $endsAt === null)) {
            return redirect()->to('/admin/promos')
                ->withInput()
                ->with('error', 'Enter valid promotion start and end dates.');
        }
        if ($startsAt !== null && $endsAt !== null && strtotime($endsAt) < strtotime($startsAt)) {
            return redirect()->to('/admin/promos')
                ->withInput()
                ->with('error', 'The promotion end date must be after its start date.');
        }

        $data = [
            'code' => strtoupper(trim((string) $this->request->getPost('code'))),
            'description' => trim((string) $this->request->getPost('description')),
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'minimum_order' => (float) $this->request->getPost('minimum_order'),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'usage_limit' => (int) $this->request->getPost('usage_limit'),
            'is_active' => $this->request->getPost('is_active') ? 1 : 0,
        ];

        $db=db_connect();$db->transBegin();
        try {
            $before=$id!==null?$db->query('SELECT * FROM promos WHERE id=? FOR UPDATE',[$id])->getRowArray():[];
            if($id!==null && !$before)throw new \DomainException('Record not found.');
            $ok=$id!==null?$model->update($id,$data):$model->insert($data,true);
            if(!$ok)throw new \DomainException(implode(' ',$model->errors())?:'Invalid record.');
            $savedId=(int)($id??$ok);
            $after=$model->find($savedId)?:$data;
            (new AuditLogService($db))->recordChange('promo_saved','promo',$savedId,$before?:[],$after);
            if(!$db->transStatus())throw new \RuntimeException('Database transaction failed.');
            $db->transCommit();
        } catch (Throwable $exception) {
            $db->transRollback();
            log_message('error', 'Promo save failed for {id}', ['id'=>(int)($id??0)]);
            return redirect()->to('/admin/promos')->withInput()->with('error', $exception->getMessage());
        }

        return $ok
            ? redirect()->to('/admin/promos')->with('success', 'Promotion saved.')
            : redirect()->to('/admin/promos')->withInput()->with('errors', $model->errors());
    }

    private function normalizeDateTime(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value);
        $errors = \DateTimeImmutable::getLastErrors();
        if (! $date || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }

        return $date->format('Y-m-d H:i:s');
    }
}
