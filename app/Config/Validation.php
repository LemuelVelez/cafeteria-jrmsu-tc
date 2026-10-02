<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Validation\CreditCardRules;
use CodeIgniter\Validation\FileRules;
use CodeIgniter\Validation\FormatRules;
use CodeIgniter\Validation\Rules;
use App\Validation\PasswordRules;

class Validation extends BaseConfig
{
    public array $ruleSets = [Rules::class, FormatRules::class, FileRules::class, CreditCardRules::class, PasswordRules::class];
    public array $templates = [
        'list' => 'CodeIgniter\\Validation\\Views\\list',
        'single' => 'CodeIgniter\\Validation\\Views\\single',
    ];
}
