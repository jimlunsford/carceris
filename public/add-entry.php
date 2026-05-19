<?php

declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$user = require_login();

if (!user_can($user, 'create_entry')) {
    http_response_code(403);
    exit('You do not have permission to create log entries.');
}

if (request_method() !== 'POST') {
    redirect('/index.php');
}

csrf_require();

$eventTimeRaw = post_string('event_time', 32);
$isLateEntry = isset($_POST['is_late_entry']) && $_POST['is_late_entry'] === '1';
$lateEntryReason = post_string('late_entry_reason', 1000);
$category = post_string('category', 80);
$priority = post_string('priority', 20);
$location = post_string('location', 120);
$inmateName = post_string('inmate_name', 160);
$entryText = post_string('entry_text', 10000);

$allowedPriorities = ['normal', 'important', 'critical'];

if ($category === '' || $entryText === '') {
    flash_set('error', 'Category and details are required.');
    redirect('/index.php');
}

if (!category_exists($category)) {
    flash_set('error', 'The selected category is not available.');
    redirect('/index.php');
}

if (!in_array($priority, $allowedPriorities, true)) {
    $priority = 'normal';
}

$submittedAt = new DateTimeImmutable('now');
$eventTime = $submittedAt;

if ($isLateEntry) {
    if ($eventTimeRaw === '') {
        flash_set('error', 'Actual event date and time are required for late/backfilled entries.');
        redirect('/index.php');
    }

    if ($lateEntryReason === '') {
        flash_set('error', 'A reason is required for late/backfilled entries.');
        redirect('/index.php');
    }

    try {
        $eventTime = new DateTimeImmutable($eventTimeRaw);
    } catch (Throwable $exception) {
        flash_set('error', 'Invalid actual event date and time.');
        redirect('/index.php');
    }

    if ($eventTime > $submittedAt) {
        flash_set('error', 'Late/backfilled entries cannot use a future event time.');
        redirect('/index.php');
    }
} else {
    $lateEntryReason = '';
}

$logDay = get_or_create_log_day_for_event_time($eventTime, (int) $user['id']);

$entryId = create_log_entry([
    'log_day_id' => (int) $logDay['id'],
    'event_time' => $eventTime->format('Y-m-d H:i:s'),
    'category' => $category,
    'location' => $location,
    'inmate_name' => $inmateName,
    'entry_text' => $entryText,
    'priority' => $priority,
    'is_late_entry' => $isLateEntry,
    'late_entry_reason' => $lateEntryReason,
    'created_by' => (int) $user['id'],
]);

audit_event(
    $isLateEntry ? 'late_log_entry_created' : 'log_entry_created',
    (int) $user['id'],
    $user['username'] ?? null,
    ($isLateEntry ? 'Created late/backfilled log entry. ' : 'Created log entry. ')
        . 'Category: ' . $category
        . '. Event time: ' . $eventTime->format('Y-m-d H:i:s')
        . '. Submitted at: ' . $submittedAt->format('Y-m-d H:i:s')
        . ($isLateEntry ? '. Reason: ' . $lateEntryReason : ''),
    'log_entry',
    $entryId
);

$currentLogDay = get_or_create_current_log_day((int) $user['id']);

if ((int) $logDay['id'] !== (int) $currentLogDay['id']) {
    flash_set(
        'success',
        'Late/backfilled log entry saved to the operational log for ' . format_log_date($logDay['operational_date']) . '.'
    );
} else {
    flash_set('success', $isLateEntry ? 'Late/backfilled log entry saved.' : 'Log entry saved.');
}

redirect('/index.php');
