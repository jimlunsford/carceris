<?php

declare(strict_types=1);

require __DIR__ . '/../../app/bootstrap.php';

$user = require_login();

if (!user_can($user, 'view_audit')) {
    http_response_code(403);
    exit('You do not have permission to view audit events.');
}

$filters = [
    'q' => get_string('q', 120),
    'event_type' => get_string('event_type', 80),
    'username' => get_string('username', 80),
    'date_from' => get_string('date_from', 10),
    'date_to' => get_string('date_to', 10),
];

$limit = (int) ($_GET['limit'] ?? 150);
$limit = max(25, min($limit, 1000));

if (request_method() === 'POST') {
    csrf_require();

    $action = post_string('action', 60);

    if ($action === 'prune_audit') {
        if (!user_can($user, 'manage_settings')) {
            http_response_code(403);
            exit('You do not have permission to prune audit records.');
        }

        $days = (int) ($_POST['retention_days'] ?? 0);

        if ($days < 1 || $days > 3650) {
            flash_set('error', 'Retention days must be between 1 and 3650.');
            redirect('/admin/audit.php');
        }

        $deleted = audit_prune_older_than_days($days);

        audit_event(
            'audit_pruned',
            (int) $user['id'],
            $user['username'] ?? null,
            'Pruned ' . $deleted . ' audit events older than ' . $days . ' days.'
        );

        flash_set('success', 'Audit retention pruning complete. Deleted ' . $deleted . ' records.');
        redirect('/admin/audit.php');
    }

    flash_set('error', 'Unknown audit action.');
    redirect('/admin/audit.php');
}

$auditAdvancedOpen = trim($filters['event_type']) !== ''
    || trim($filters['username']) !== ''
    || $limit !== 150;

$eventTypes = audit_event_types();
$events = audit_events_filtered($filters, $limit);

if (get_string('export', 10) === 'csv') {
    if (!user_can($user, 'manage_settings')) {
        http_response_code(403);
        exit('You do not have permission to export audit events.');
    }

    audit_event(
        'audit_exported',
        (int) $user['id'],
        $user['username'] ?? null,
        'Exported audit events CSV.'
    );

    $csv = audit_events_to_csv($events);
    $filename = 'carceris-audit-events-' . date('Y-m-d-His') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($csv));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    echo $csv;
    exit;
}

audit_event(
    'audit_viewed',
    (int) $user['id'],
    $user['username'] ?? null,
    'Viewed audit events.'
);

require __DIR__ . '/../../app/views/admin/audit.php';
