<?php

/**
 * PHPUnit bootstrap for the E2E suite.
 *
 * In the `testing` environment CodeIgniter switches to the `tests` database group, whose
 * default is SQLite. The ERP needs MySQL, so point that group at the same MySQL server the
 * app uses (credentials read from .env) but ALWAYS at the throw-away `erp_e2e_test`
 * database, never at the development database.
 */
$env = [];
foreach (file(__DIR__ . '/../../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if ($line[0] === '#' || ! str_contains($line, '=')) {
        continue;
    }
    [$k, $v] = array_map('trim', explode('=', $line, 2));
    $env[$k] ??= trim($v, "'\"");
}

$settings = [
    'database.tests.hostname' => $env['database.default.hostname'] ?? '127.0.0.1',
    'database.tests.port'     => $env['database.default.port'] ?? '3306',
    'database.tests.username' => $env['database.default.username'] ?? 'root',
    'database.tests.password' => $env['database.default.password'] ?? '',
    'database.tests.database' => 'erp_e2e_test',
    'database.tests.DBDriver' => 'MySQLi',
    'database.tests.DBPrefix' => '',
    'database.tests.charset'  => 'utf8mb4',
    'database.tests.DBCollat' => 'utf8mb4_general_ci',
    'database.tests.foreignKeys' => '1',
];
foreach ($settings as $k => $v) {
    putenv("$k=$v");
    $_ENV[$k] = $v;
    $_SERVER[$k] = $v;
}

require __DIR__ . '/../../vendor/codeigniter4/framework/system/Test/bootstrap.php';
