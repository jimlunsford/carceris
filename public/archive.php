<?php

declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$user = require_login();

if (!user_can($user, 'view_archive')) {
    http_response_code(403);
    exit('You do not have permission to view archived logs.');
}

$selectedDate = get_string('date', 10);
$selectedLogDay = null;
$entries = [];

if ($selectedDate !== '') {
    $selectedLogDay = get_log_day_by_date($selectedDate);

    if ($selectedLogDay) {
        $entries = get_entries_for_log_day((int) $selectedLogDay['id']);
    }
}

$archiveFilters = [
    'q' => get_string('q', 120),
    'date_from' => get_string('date_from', 10),
    'date_to' => get_string('date_to', 10),
    'category' => get_string('category', 80),
    'priority' => get_string('priority', 20),
    'status' => get_string('status', 20),
    'inmate' => get_string('inmate', 160),
    'location' => get_string('location', 120),
    'officer' => get_string('officer', 120),
    'include_voided' => get_string('include_voided', 1) === '1' ? '1' : '',
];

$archiveLimit = (int) ($_GET['limit'] ?? 150);
$archiveLimit = max(25, min($archiveLimit, 1000));

$archiveSearchRan = get_string('archive_search', 1) === '1' || log_archive_search_filters_active($archiveFilters);
$archiveResults = [];

if ($archiveSearchRan) {
    $archiveResults = log_archive_search_entries($archiveFilters, $archiveLimit);
}

// Archive CSV export was removed in v0.3.18.

$archiveAdvancedOpen = trim($archiveFilters['category']) !== ''
    || trim($archiveFilters['priority']) !== ''
    || trim($archiveFilters['status']) !== ''
    || trim($archiveFilters['inmate']) !== ''
    || trim($archiveFilters['location']) !== ''
    || trim($archiveFilters['officer']) !== ''
    || trim($archiveFilters['include_voided']) !== ''
    || $archiveLimit !== 150;

$archiveCategories = categories();
$recentLogs = recent_log_days(14);

$queryString = $_SERVER['QUERY_STRING'] ?? '';
$archiveReturnPath = '/archive.php' . ($queryString !== '' ? '?' . $queryString : '');

audit_event(
    'archive_viewed',
    (int) $user['id'],
    $user['username'] ?? null,
    $archiveSearchRan
        ? 'Searched archive.'
        : ($selectedDate !== '' ? 'Viewed archive date: ' . $selectedDate : 'Viewed archive index.'),
    $selectedLogDay ? 'log_day' : null,
    $selectedLogDay ? (int) $selectedLogDay['id'] : null
);

require __DIR__ . '/../app/views/archive.php';
