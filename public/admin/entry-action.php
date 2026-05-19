<?php

declare(strict_types=1);

require __DIR__ . '/../../app/bootstrap.php';

$user = require_login();

if (!user_can($user, 'correct_void_entries')) {
    http_response_code(403);
    exit('You do not have permission to correct or void log entries.');
}

if (request_method() === 'POST') {
    csrf_require();

    $action = post_string('action', 40);
    $entryId = (int) ($_POST['entry_id'] ?? 0);
    $returnTo = carceris_safe_return_path(post_string('return_to', 255), '/index.php');

    try {
        if ($action === 'correct') {
            $category = post_string('category', 80);
            $priority = post_string('priority', 20);

            if (!category_exists($category)) {
                throw new RuntimeException('The selected category is not available.');
            }

            if (!in_array($priority, ['normal', 'important', 'critical'], true)) {
                $priority = 'normal';
            }

            $replacementId = correct_log_entry($entryId, [
                'event_time' => post_string('event_time', 32),
                'category' => $category,
                'priority' => $priority,
                'location' => post_string('location', 120),
                'inmate_name' => post_string('inmate_name', 160),
                'entry_text' => post_string('entry_text', 10000),
                'correction_reason' => post_string('correction_reason', 1000),
            ], $user);

            audit_event(
                'log_entry_corrected',
                (int) $user['id'],
                $user['username'] ?? null,
                'Corrected log entry #' . $entryId . '. Replacement entry #' . $replacementId . '.',
                'log_entry',
                $entryId
            );

            flash_set('success', 'Log entry corrected. Original entry was preserved and marked corrected.');
            redirect($returnTo);
        }

        if ($action === 'void') {
            void_log_entry($entryId, post_string('void_reason', 1000), $user);

            audit_event(
                'log_entry_voided',
                (int) $user['id'],
                $user['username'] ?? null,
                'Voided log entry #' . $entryId . '.',
                'log_entry',
                $entryId
            );

            flash_set('success', 'Log entry voided. Original entry was preserved and marked voided.');
            redirect($returnTo);
        }

        throw new RuntimeException('Unknown entry action.');
    } catch (Throwable $exception) {
        flash_set('error', $exception->getMessage());
        redirect('/admin/entry-action.php?id=' . $entryId . '&return=' . rawurlencode($returnTo));
    }
}

$entryId = (int) get_string('id', 20);
$returnTo = carceris_safe_return_path(get_string('return', 255), '/index.php');
$entry = get_log_entry_by_id($entryId);

if (!$entry) {
    http_response_code(404);
    exit('Log entry not found.');
}

$categories = categories();

audit_event(
    'entry_action_page_viewed',
    (int) $user['id'],
    $user['username'] ?? null,
    'Viewed correct/void page for log entry #' . $entryId . '.',
    'log_entry',
    $entryId
);

require __DIR__ . '/../../app/views/admin/entry-action.php';
