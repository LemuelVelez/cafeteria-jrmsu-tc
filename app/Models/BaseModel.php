<?php

namespace App\Models;

use CodeIgniter\Model;

abstract class BaseModel extends Model
{
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $protectFields = true;
    protected $skipValidation = false;
}
