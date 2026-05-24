<?php

declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$user = require_login();

if (!user_can($user, 'view_active_log')) {
    redirect('/archive.php');
}

$logDay = get_or_create_current_log_day((int) $user['id']);
$entries = get_entries_for_log_day((int) $logDay['id']);
$categories = categories();

require __DIR__ . '/../app/views/active-log.php';
