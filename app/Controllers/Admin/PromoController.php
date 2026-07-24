<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\PromoModel;
use Throwable;

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

        $startsAt = $this->normalizeDateTime((string) $this->request->getPost('starts_at'));
        $endsAt = $this->normalizeDateTime((string) $this->request->getPost('ends_at'));
        if ($startsAt !== null && $endsAt !== null && strtotime($endsAt) < strtotime($startsAt)) {
            return redirect()->to('/admin/promos')
                ->withInput()
                ->with('error', 'The promotion end date must be after its start date.');
        }

        $data = [
            'code' => strtoupper(trim((string) $this->request->getPost('code'))),
            'description' => trim((string) $this->request->getPost('description')),
            'discount_type' => (string) $this->request->getPost('discount_type'),
            'discount_value' => (float) $this->request->getPost('discount_value'),
            'minimum_order' => (float) $this->request->getPost('minimum_order'),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'usage_limit' => (int) $this->request->getPost('usage_limit'),
            'is_active' => $this->request->getPost('is_active') ? 1 : 0,
        ];

        try {
            $ok = $id !== null ? $model->update($id, $data) : $model->insert($data);
        } catch (Throwable $exception) {
            log_message('error', 'Promotion save failed: {message}', ['message' => $exception->getMessage()]);

            return redirect()->to('/admin/promos')
                ->withInput()
                ->with('error', 'The promotion could not be saved.');
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

        $timestamp = strtotime($value);

        return $timestamp === false ? null : date('Y-m-d H:i:s', $timestamp);
    }
}
