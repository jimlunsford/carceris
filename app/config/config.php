<?php

declare(strict_types=1);

$baseConfig = [
    'app' => [
        'name' => 'Carceris',
        'tagline' => 'Secure daily logging for correctional facilities.',
        'version' => '0.6.14',
        'timezone' => 'America/Indiana/Indianapolis',
        'session_name' => 'carceris_session',
        'environment_mode' => 'testing',
        'debug' => false,
        'force_https' => false,
    ],
    'database' => [
        'host' => '',
        'port' => 3306,
        'name' => '',
        'user' => '',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
];

$localConfigPath = __DIR__ . '/config.local.php';

if (is_file($localConfigPath)) {
    $localConfig = require $localConfigPath;
    if (is_array($localConfig)) {
        $baseConfig = array_replace_recursive($baseConfig, $localConfig);
    }
}

$baseConfig['app']['version'] = '0.6.14';

return $baseConfig;
