<?php

declare(strict_types=1);

require __DIR__ . '/../../app/bootstrap.php';

$user = require_login();

if (
    !user_can($user, 'manage_users')
    && !user_can($user, 'manage_settings')
    && !user_can($user, 'view_audit')
    && !user_can($user, 'manage_upgrades')
    && !user_can($user, 'manage_backups')
    && !user_can($user, 'view_status')
    && !user_can($user, 'view_reports')
    && !user_can($user, 'send_reports')
) {
    http_response_code(403);
    exit('You do not have permission to view the admin area.');
}

audit_event(
    'admin_area_viewed',
    (int) $user['id'],
    $user['username'] ?? null,
    'Viewed admin area.'
);

require __DIR__ . '/../../app/views/admin/index.php';
