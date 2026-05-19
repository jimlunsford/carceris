<?php

declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$user = require_login();

if (!user_can($user, 'print_log')) {
    http_response_code(403);
    exit('You do not have permission to print logs.');
}

$date = get_string('date', 10);

if ($date !== '') {
    $logDay = get_log_day_by_date($date);

    if (!$logDay) {
        http_response_code(404);
        exit('Log not found.');
    }
} else {
    $logDay = get_or_create_current_log_day((int) $user['id']);
}

$entries = get_entries_for_log_day((int) $logDay['id']);

audit_event(
    'log_print_viewed',
    (int) $user['id'],
    $user['username'] ?? null,
    'Viewed print page for log: ' . $logDay['operational_date'],
    'log_day',
    (int) $logDay['id']
);

require __DIR__ . '/../app/views/print-log.php';
