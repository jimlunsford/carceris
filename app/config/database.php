<?php

declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    global $config;
    $db = $config['database'];

    if (empty($db['host']) || empty($db['name']) || empty($db['user'])) {
        throw new RuntimeException('Carceris database configuration is incomplete.');
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $db['host'],
        (int) ($db['port'] ?? 3306),
        $db['name'],
        $db['charset'] ?? 'utf8mb4'
    );

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    if (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
        $options[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = true;
    }

    $pdo = new PDO($dsn, $db['user'], $db['pass'] ?? '', $options);

    return $pdo;
}
