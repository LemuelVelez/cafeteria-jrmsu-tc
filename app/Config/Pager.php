<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/** Required by CodeIgniter's pager service (including model->paginate()). */
class Pager extends BaseConfig
{
    /** @var array<string, string> */
    public array $templates = [
        'default_full'   => 'CodeIgniter\\Pager\\Views\\default_full',
        'default_simple' => 'CodeIgniter\\Pager\\Views\\default_simple',
        'default_head'   => 'CodeIgniter\\Pager\\Views\\default_head',
    ];

    public int $perPage = 20;
}
