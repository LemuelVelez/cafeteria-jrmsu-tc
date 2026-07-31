<?php

namespace App\Models;

class ProductModel extends BaseModel
{
    protected $table = 'products';
    protected $primaryKey = 'id';
    protected $allowedFields = ['category_id', 'name', 'slug', 'description', 'price', 'stock', 'image', 'is_available', 'is_featured'];
    protected $useSoftDeletes = true;
    protected $deletedField = 'deleted_at';
    protected $validationRules = [
        'category_id' => 'required|is_natural_no_zero',
        'name' => 'required|min_length[2]|max_length[120]',
        'slug' => 'required|alpha_dash|max_length[140]',
        'price' => 'required|decimal|greater_than_equal_to[0]',
        'stock' => 'required|integer|greater_than_equal_to[0]',
        'is_available' => 'permit_empty|in_list[0,1]',
        'is_featured' => 'permit_empty|in_list[0,1]',
    ];

    public function menu(?int $categoryId = null, ?string $search = null): array
    {
        $builder = $this->select('products.*, categories.name AS category_name')
            ->join('categories', 'categories.id = products.category_id')
            ->where('products.is_available', 1)
            ->where('products.stock >', 0)
            ->where('categories.is_active', 1)
            ->where('categories.deleted_at', null);
        if ($categoryId) {
            $builder->where('products.category_id', $categoryId);
        }
        if ($search) {
            $builder->groupStart()->like('products.name', $search)->orLike('products.description', $search)->groupEnd();
        }
        return $builder->orderBy('products.is_featured', 'DESC')->orderBy('products.name')->findAll();
    }

    public function withCategory(): array
    {
        return $this->select('products.*, categories.name AS category_name')
            ->join('categories', 'categories.id = products.category_id', 'left')
            ->orderBy('products.created_at', 'DESC')
            ->findAll();
    }
}
