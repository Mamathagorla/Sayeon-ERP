<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class App extends BaseConfig
{
    public string $baseURL = '';

    public array $allowedHostnames = [];

    public string $indexPage = '';

    public string $permittedURIChars = 'a-z 0-9~%.:_\-';

    public string $uriProtocol = 'REQUEST_URI';

    public string $defaultLocale = 'en';

    public bool $negotiateLocale = false;

    public array $supportedLocales = ['en'];

    public string $appTimezone = 'Asia/Kolkata';

    public string $charset = 'UTF-8';

    public bool $forceGlobalSecureRequests = false;

    public string $sessionDriver = \CodeIgniter\Session\Handlers\FileHandler::class;

    public string $sessionCookieName = 'ops_erp_session';

    public int $sessionExpiration = 7200;

    public string $sessionSavePath = WRITEPATH . 'session';

    public bool $sessionMatchIP = false;

    public int $sessionTimeToUpdate = 300;

    public bool $sessionRegenerateDestroy = false;

    public string $cookiePrefix = '';

    public string $cookieDomain = '';

    public string $cookiePath = '/';

    public bool $cookieSecure = false;

    public bool $cookieHTTPOnly = true;

    public string $cookieSameSite = 'Lax';

    public bool $CSPEnabled = false;

    public array $proxyIPs = [];
}
