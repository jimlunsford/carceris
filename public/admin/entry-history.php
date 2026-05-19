<?php

declare(strict_types=1);

require __DIR__ . '/../../app/bootstrap.php';

$user = require_login();

if (!user_can($user, 'correct_void_entries')) {
    http_response_code(403);
    exit('You do not have permission to view correction or void history.');
}

$entryId = (int) get_string('id', 20);
$returnTo = carceris_safe_return_path(get_string('return', 255), '/index.php');

if (function_exists('carceris_repair_correction_void_schema')) {
    try {
        carceris_repair_correction_void_schema();
    } catch (Throwable $exception) {
        error_log('Carceris entry history schema repair failed: ' . $exception->getMessage());
    }
}

$entry = get_log_entry_by_id($entryId);

if (!$entry) {
    http_response_code(404);
    exit('Log entry not found.');
}

$actions = get_log_entry_actions_for_entry($entryId);

if (!$actions) {
    $fallbackAction = get_log_entry_fallback_history_action($entry);

    if ($fallbackAction) {
        $actions = [$fallbackAction];
    }
}

$revisions = get_log_entry_revisions_for_entry($entryId);

audit_event(
    'entry_history_viewed',
    (int) $user['id'],
    $user['username'] ?? null,
    'Viewed correction/void history for log entry #' . $entryId . '.',
    'log_entry',
    $entryId
);

require __DIR__ . '/../../app/views/admin/entry-history.php';
