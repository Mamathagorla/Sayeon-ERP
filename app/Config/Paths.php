<?php

namespace Config;

/**
 * The paths config file. Standard CI4 layout — framework is pulled into
 * vendor/codeigniter4/framework/system by `composer install`.
 */
class Paths
{
    public string $systemDirectory = __DIR__ . '/../../vendor/codeigniter4/framework/system';

    public string $appDirectory = __DIR__ . '/..';

    public string $writableDirectory = __DIR__ . '/../../writable';

    public string $testsDirectory = __DIR__ . '/../../tests';

    public string $viewDirectory = __DIR__ . '/../Views';
}
