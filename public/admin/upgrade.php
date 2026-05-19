<?php

declare(strict_types=1);

define('CARCERIS_ALLOW_MAINTENANCE', true);

require __DIR__ . '/../../app/bootstrap.php';

$user = require_login();

if (!user_can($user, 'manage_upgrades')) {
    http_response_code(403);
    exit('You do not have permission to manage upgrades.');
}

try {
    carceris_ensure_schema_migrations_table();
    carceris_baseline_existing_migrations((int) $user['id']);
} catch (Throwable $exception) {
    flash_set('error', 'Could not prepare migration tracking: ' . $exception->getMessage());
}

if (request_method() === 'POST') {
    csrf_require();

    $adminPassword = (string) ($_POST['admin_password'] ?? '');

    if (!carceris_verify_admin_password($user, $adminPassword)) {
        audit_event(
            'upgrade_password_failed',
            (int) $user['id'],
            $user['username'] ?? null,
            'Admin password confirmation failed before upgrade.'
        );

        flash_set('error', 'Admin password confirmation failed.');
        redirect('/admin/upgrade.php');
    }

    if (empty($_POST['upgrade_backup_acknowledgement'])) {
        flash_set('error', 'You must acknowledge that a backup has been created before upgrading.');
        redirect('/admin/upgrade.php');
    }

    try {
        $result = carceris_perform_upgrade($_FILES['upgrade_zip'] ?? [], $user);

        flash_set(
            'success',
            'Upgrade complete. Carceris is now at version ' . $result['version'] . '. Migrations run: ' . (count($result['migrations']) ? implode(', ', $result['migrations']) : 'none') . '.'
        );

        redirect('/admin/upgrade.php');
    } catch (Throwable $exception) {
        flash_set('error', 'Upgrade failed: ' . $exception->getMessage());
        redirect('/admin/upgrade.php');
    }
}

$currentVersion = carceris_current_version();
$zipAvailable = carceris_zip_available();
$maintenanceMode = carceris_is_maintenance_mode();
$upgradeFailure = carceris_upgrade_failure_status();
$migrationSummary = carceris_migration_summary();
$upgradeEvents = audit_events_by_types(['upgrade_started', 'upgrade_success', 'upgrade_failed'], 25);

audit_event(
    'upgrade_page_viewed',
    (int) $user['id'],
    $user['username'] ?? null,
    'Viewed upgrade page.'
);

require __DIR__ . '/../../app/views/admin/upgrade.php';
