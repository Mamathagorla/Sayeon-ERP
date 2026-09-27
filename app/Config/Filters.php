<?php

namespace Config;

use App\Modules\Auth\Filters\AuthFilter;
use App\Modules\Auth\Filters\PermissionFilter;
use CodeIgniter\Config\Filters as BaseFilters;
use CodeIgniter\Filters\Cors;
use CodeIgniter\Filters\CSRF;
use CodeIgniter\Filters\DebugToolbar;
use CodeIgniter\Filters\ForceHTTPS;
use CodeIgniter\Filters\Honeypot;
use CodeIgniter\Filters\InvalidChars;
use CodeIgniter\Filters\PageCache;
use CodeIgniter\Filters\PerformanceMetrics;
use CodeIgniter\Filters\SecureHeaders;

class Filters extends BaseFilters
{
    public array $aliases = [
        'csrf'          => CSRF::class,
        'toolbar'       => DebugToolbar::class,
        'honeypot'      => Honeypot::class,
        'invalidchars'  => InvalidChars::class,
        'secureheaders' => SecureHeaders::class,
        'cors'          => Cors::class,
        'forcehttps'    => ForceHTTPS::class,
        'pagecache'     => PageCache::class,
        'performance'   => PerformanceMetrics::class,

        // App-specific
        'auth'       => AuthFilter::class,
        'permission' => PermissionFilter::class,
    ];

    /**
     * 'auth' runs on every route except the ones explicitly excluded
     * (login screen, static assets). 'permission:<slug>' is applied
     * per-route in each module's Routes.php.
     */
    public array $globals = [
        'before' => [
            'auth' => ['except' => ['auth/login', 'auth/attempt-login', 'assets/*']],
        ],
        'after' => [],
    ];

    public array $methods = [];

    public array $filters = [];
}
