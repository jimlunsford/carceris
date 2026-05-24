<?php

declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$user = require_login();

if (!user_can($user, 'view_status')) {
    http_response_code(403);
    exit('You do not have permission to view system status.');
}

$httpsEnabled = is_https_request();
$installLockExists = carceris_install_lock_exists();
$phpStatus = carceris_php_status();
$databaseStatus = carceris_database_status();
$environmentMode = carceris_environment_mode();
$currentVersion = carceris_current_version();
$zipAvailable = carceris_zip_available();
$securityChecks = carceris_security_status_checks();
$upgradeFailure = carceris_upgrade_failure_status();
$productionReadiness = ['checks' => [], 'failed' => [], 'ready' => false];
$mailCapabilities = carceris_mail_capabilities();

try {
    carceris_ensure_schema_migrations_table();
    $statusMigrationSummary = carceris_migration_summary();
} catch (Throwable $exception) {
    $statusMigrationSummary = ['pending' => ['Migration status unavailable: ' . $exception->getMessage()], 'recent' => [], 'known_files' => []];
}

$productionReadiness = carceris_production_readiness_checks($securityChecks, $httpsEnabled, $databaseStatus, $statusMigrationSummary);

try {
    $schemaHealth = carceris_schema_health_check();
} catch (Throwable $exception) {
    $schemaHealth = [
        'ok' => false,
        'missing_tables' => [],
        'missing_columns' => ['Schema health unavailable: ' . $exception->getMessage()],
    ];
}

audit_event(
    'status_viewed',
    (int) $user['id'],
    $user['username'] ?? null,
    'Viewed system status.'
);

require __DIR__ . '/../app/views/status.php';
