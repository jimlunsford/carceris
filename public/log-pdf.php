<?php

declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$user = require_login();

if (!user_can($user, 'print_log')) {
    http_response_code(403);
    exit('You do not have permission to download log PDFs.');
}

$date = get_string('date', 10);

if ($date === '' && !user_can($user, 'view_active_log')) {
    http_response_code(403);
    exit('You do not have permission to download the active log PDF.');
}

if ($date !== '') {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        http_response_code(400);
        exit('Invalid date.');
    }

    $logDay = get_log_day_by_date($date);

    if (!$logDay) {
        http_response_code(404);
        exit('Log not found.');
    }
} else {
    $logDay = get_or_create_current_log_day((int) $user['id']);
}

$entries = get_entries_for_log_day((int) $logDay['id']);

$downloadId = carceris_record_report_generation($logDay, 'pdf', $user, 'generated');

audit_event(
    $date !== '' ? 'archive_pdf_downloaded' : 'current_log_pdf_downloaded',
    (int) $user['id'],
    $user['username'] ?? null,
    ($date !== '' ? 'Downloaded archived log PDF. ' : 'Downloaded current log PDF. ')
        . 'Operational date: ' . $logDay['operational_date'] . '.',
    'report_download',
    $downloadId
);

$content = carceris_render_log_report_pdf($logDay, $entries);
$filename = carceris_report_download_filename($logDay, 'pdf');

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($content));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

echo $content;
