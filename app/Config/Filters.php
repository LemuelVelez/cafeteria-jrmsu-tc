<?php

namespace Config;

use App\Filters\ActiveAccountFilter;
use App\Filters\AuthFilter;
use App\Filters\GuestFilter;
use App\Filters\RoleFilter;
use CodeIgniter\Config\Filters as BaseFilters;
use CodeIgniter\Filters\CSRF;
use CodeIgniter\Filters\DebugToolbar;
use CodeIgniter\Filters\Honeypot;
use CodeIgniter\Filters\InvalidChars;
use CodeIgniter\Filters\SecureHeaders;

class Filters extends BaseFilters
{
    public array $aliases = [
        'csrf' => CSRF::class,
        'toolbar' => DebugToolbar::class,
        'honeypot' => Honeypot::class,
        'invalidchars' => InvalidChars::class,
        'secureheaders' => SecureHeaders::class,
        'auth' => AuthFilter::class,
        'guest' => GuestFilter::class,
        'role' => RoleFilter::class,
        'active' => ActiveAccountFilter::class,
    ];

    public array $required = [
        'before' => ['invalidchars'],
        'after' => ['secureheaders'],
    ];

    public array $globals = [
        'before' => ['csrf'],
        'after' => [
            'toolbar' => ['except' => ['api/*']],
        ],
    ];

    public array $methods = [];
    public array $filters = [];
}
