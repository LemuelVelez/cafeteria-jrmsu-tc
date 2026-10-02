<?php

namespace App\Models;

class ProductModel extends BaseModel
{
    protected $table = 'products';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'category_id', 'name', 'slug', 'sku', 'barcode', 'description', 'price', 'stock', 'reorder_level', 'image',
        'is_available', 'is_featured', 'serving_size', 'calories', 'protein_g', 'carbohydrates_g', 'fat_g',
        'sugar_g', 'fiber_g', 'sodium_mg', 'allergens', 'is_healthy_choice',
    ];
    protected $useSoftDeletes = true;
    protected $deletedField = 'deleted_at';
    protected $validationRules = [
        'category_id' => 'required|is_natural_no_zero',
        'name' => 'required|min_length[2]|max_length[120]',
        'slug' => 'required|alpha_dash|max_length[140]',
        'sku' => 'permit_empty|alpha_dash|max_length[40]',
        'barcode' => 'permit_empty|alpha_dash|max_length[64]',
        'price' => 'required|decimal|greater_than_equal_to[0]',
        'stock' => 'permit_empty|integer|greater_than_equal_to[0]',
        'reorder_level' => 'permit_empty|integer|greater_than_equal_to[0]',
        'serving_size' => 'permit_empty|max_length[50]',
        'calories' => 'permit_empty|integer|greater_than_equal_to[0]',
        'protein_g' => 'permit_empty|decimal|greater_than_equal_to[0]',
        'carbohydrates_g' => 'permit_empty|decimal|greater_than_equal_to[0]',
        'fat_g' => 'permit_empty|decimal|greater_than_equal_to[0]',
        'sugar_g' => 'permit_empty|decimal|greater_than_equal_to[0]',
        'fiber_g' => 'permit_empty|decimal|greater_than_equal_to[0]',
        'sodium_mg' => 'permit_empty|integer|greater_than_equal_to[0]',
        'allergens' => 'permit_empty|max_length[255]',
        'is_healthy_choice' => 'permit_empty|in_list[0,1]',
        'is_available' => 'permit_empty|in_list[0,1]',
        'is_featured' => 'permit_empty|in_list[0,1]',
    ];

    public function menu(?int $categoryId = null, ?string $search = null, ?int $maxCalories = null, ?string $excludeAllergen = null): array
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
        if ($maxCalories !== null) {
            $builder->where('products.calories IS NOT NULL', null, false)->where('products.calories <=', $maxCalories);
        }
        if ($excludeAllergen) {
            $builder->groupStart()->where('products.allergens', null)->orNotLike('products.allergens', $excludeAllergen)->groupEnd();
        }
        return $builder->orderBy('products.is_featured', 'DESC')->orderBy('products.name')->findAll();
    }

    public function featured(int $limit = 4): array
    {
        return $this->select('products.*, categories.name AS category_name')
            ->join('categories', 'categories.id = products.category_id')
            ->where('products.is_available', 1)->where('products.is_featured', 1)->where('products.stock >', 0)
            ->where('categories.is_active', 1)->where('categories.deleted_at', null)
            ->orderBy('products.name')->findAll(max(1, $limit));
    }

    public function withCategory(): array
    {
        return $this->select('products.*, categories.name AS category_name')
            ->join('categories', 'categories.id = products.category_id', 'left')
            ->orderBy('products.created_at', 'DESC')->findAll();
    }
}
