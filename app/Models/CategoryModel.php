<?php

namespace App\Models;

class CategoryModel extends BaseModel
{
    protected $table = 'categories';
    protected $primaryKey = 'id';
    protected $allowedFields = ['name', 'slug', 'description', 'is_active', 'sort_order'];
    protected $useSoftDeletes = true;
    protected $deletedField = 'deleted_at';
    protected $validationRules = [
        'name' => 'required|min_length[2]|max_length[80]',
        'slug' => 'required|alpha_dash|max_length[100]',
        'is_active' => 'permit_empty|in_list[0,1]',
    ];
}
