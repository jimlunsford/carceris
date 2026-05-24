<?php

declare(strict_types=1);

require __DIR__ . '/../../app/bootstrap.php';

$user = require_login();

if (!user_can($user, 'view_reports') && !user_can($user, 'send_reports')) {
    http_response_code(403);
    exit('You do not have permission to generate daily logs.');
}

if (request_method() !== 'POST') {
    http_response_code(405);
    exit('Daily log downloads must be generated from the Daily Logs dashboard.');
}

csrf_require();

$log = post_string('log', 40);
$format = post_string('format', 40);
$logDayId = (int) ($_POST['log_day_id'] ?? 0);

if (!in_array($format, ['plain_text', 'html', 'pdf'], true)) {
    http_response_code(400);
    exit('Invalid daily log format.');
}

if ($logDayId > 0) {
    $logDay = carceris_log_day_by_id($logDayId);

    if (!$logDay) {
        http_response_code(404);
        exit('Daily log not found.');
    }
} elseif ($log === 'previous') {
    $logDay = get_previous_log_day();

    if (!$logDay) {
        http_response_code(404);
        exit('Previous completed daily log not found.');
    }
} else {
    http_response_code(400);
    exit('Invalid daily log selection.');
}

$entries = get_entries_for_log_day((int) $logDay['id']);

$downloadFormat = match ($format) {
    'html' => 'html',
    'pdf' => 'pdf',
    default => 'plain_text',
};

$deliveryId = carceris_record_report_generation($logDay, $downloadFormat, $user, 'generated');

audit_event(
    'daily_log_manual_generated',
    (int) $user['id'],
    $user['username'] ?? null,
    'Generated completed daily log. Format: ' . $downloadFormat . '. Operational date: ' . $logDay['operational_date'] . '.',
    'report_download',
    $deliveryId
);

if ($downloadFormat === 'html') {
    $content = carceris_render_log_report_html($logDay, $entries);
    $contentType = 'text/html; charset=utf-8';
    $filename = carceris_report_download_filename($logDay, 'html');
} elseif ($downloadFormat === 'pdf') {
    $content = carceris_render_log_report_pdf($logDay, $entries);
    $contentType = 'application/pdf';
    $filename = carceris_report_download_filename($logDay, 'pdf');
} else {
    $content = carceris_render_log_report_plain_text($logDay, $entries);
    $contentType = 'text/plain; charset=utf-8';
    $filename = carceris_report_download_filename($logDay, 'text');
}

header('Content-Type: ' . $contentType);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($content));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

echo $content;
