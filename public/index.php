<?php

declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$user = require_login();

$logDay = get_or_create_current_log_day((int) $user['id']);
$entries = get_entries_for_log_day((int) $logDay['id']);
$categories = categories();

require __DIR__ . '/../app/views/active-log.php';
