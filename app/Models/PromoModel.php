<?php

namespace App\Models;

class PromoModel extends BaseModel
{
    protected $table = 'promos';
    protected $primaryKey = 'id';
    protected $allowedFields = ['code', 'description', 'discount_type', 'discount_value', 'minimum_order', 'starts_at', 'ends_at', 'usage_limit', 'used_count', 'is_active'];
    protected $validationRules = [
        'code' => 'required|alpha_numeric_punct|max_length[40]',
        'discount_type' => 'required|in_list[fixed,percentage]',
        'discount_value' => 'required|decimal|greater_than[0]',
    ];

    public function validCode(string $code): ?array
    {
        $now = date('Y-m-d H:i:s');
        return $this->where('code', strtoupper(trim($code)))
            ->where('is_active', 1)
            ->groupStart()->where('starts_at IS NULL')->orWhere('starts_at <=', $now)->groupEnd()
            ->groupStart()->where('ends_at IS NULL')->orWhere('ends_at >=', $now)->groupEnd()
            ->first();
    }
}
