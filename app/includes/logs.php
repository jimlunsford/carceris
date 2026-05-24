<?php

declare(strict_types=1);

function operational_window_for_datetime(?DateTimeImmutable $when = null): array
{
    $startSetting = carceris_operational_day_start_time();

    $when = $when ?? new DateTimeImmutable('now');
    $todayStart = new DateTimeImmutable($when->format('Y-m-d') . ' ' . $startSetting);

    if ($when < $todayStart) {
        $start = $todayStart->modify('-1 day');
    } else {
        $start = $todayStart;
    }

    $end = $start->modify('+1 day')->modify('-1 minute');

    return [
        'operational_date' => $start->format('Y-m-d'),
        'start_time' => $start->format('Y-m-d H:i:s'),
        'end_time' => $end->format('Y-m-d H:i:s'),
        'label' => carceris_format_datetime($start) . ' to ' . carceris_format_datetime($end),
    ];
}

function current_operational_window(): array
{
    return operational_window_for_datetime(new DateTimeImmutable('now'));
}

function previous_operational_window(): array
{
    $currentWindow = current_operational_window();
    $currentStart = new DateTimeImmutable($currentWindow['start_time']);

    return operational_window_for_datetime($currentStart->modify('-1 minute'));
}

function get_log_day_for_window(array $window): ?array
{
    $stmt = db()->prepare('SELECT * FROM log_days WHERE operational_date = :operational_date LIMIT 1');
    $stmt->execute(['operational_date' => $window['operational_date']]);
    $logDay = $stmt->fetch();

    return $logDay ?: null;
}

function get_previous_log_day(): ?array
{
    return get_log_day_for_window(previous_operational_window());
}

function get_current_log_day(): ?array
{
    return get_log_day_for_window(current_operational_window());
}

function get_or_create_log_day_for_window(array $window, ?int $openedBy = null): array
{
    $logDay = get_log_day_for_window($window);

    if ($logDay) {
        return $logDay;
    }

    $insert = db()->prepare(
        'INSERT INTO log_days
            (log_label, operational_date, start_time, end_time, status, opened_by)
         VALUES
            (:log_label, :operational_date, :start_time, :end_time, "open", :opened_by)'
    );

    $insert->execute([
        'log_label' => $window['label'],
        'operational_date' => $window['operational_date'],
        'start_time' => $window['start_time'],
        'end_time' => $window['end_time'],
        'opened_by' => $openedBy,
    ]);

    $id = (int) db()->lastInsertId();

    $stmt = db()->prepare('SELECT * FROM log_days WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);

    return $stmt->fetch();
}

function get_or_create_current_log_day(?int $openedBy = null): array
{
    return get_or_create_log_day_for_window(current_operational_window(), $openedBy);
}

function get_or_create_previous_log_day(?int $openedBy = null): array
{
    return get_or_create_log_day_for_window(previous_operational_window(), $openedBy);
}

function get_or_create_log_day_for_event_time(DateTimeImmutable $eventTime, ?int $openedBy = null): array
{
    return get_or_create_log_day_for_window(operational_window_for_datetime($eventTime), $openedBy);
}

function get_log_day_by_date(string $date): ?array
{
    $stmt = db()->prepare('SELECT * FROM log_days WHERE operational_date = :operational_date LIMIT 1');
    $stmt->execute(['operational_date' => $date]);
    $logDay = $stmt->fetch();

    return $logDay ?: null;
}

function get_entries_for_log_day(int $logDayId): array
{
    $stmt = db()->prepare(
        'SELECT
            le.*,
            u.display_name AS created_by_name,
            vu.display_name AS voided_by_name,
            cu.display_name AS corrected_by_name
         FROM log_entries le
         LEFT JOIN users u ON u.id = le.created_by
         LEFT JOIN users vu ON vu.id = le.voided_by
         LEFT JOIN users cu ON cu.id = le.corrected_by
         WHERE le.log_day_id = :log_day_id
           AND (:show_voided_entries = 1 OR (COALESCE(le.status, \'active\') <> \'voided\' AND COALESCE(le.is_voided, 0) = 0))
         ORDER BY le.event_time ASC, le.id ASC'
    );

    $stmt->execute([
        'log_day_id' => $logDayId,
        'show_voided_entries' => carceris_show_voided_entries() ? 1 : 0,
    ]);

    return $stmt->fetchAll();
}

function get_log_entry_by_id(int $entryId): ?array
{
    $stmt = db()->prepare(
        'SELECT
            le.*,
            ld.operational_date,
            ld.log_label,
            u.display_name AS created_by_name,
            vu.display_name AS voided_by_name,
            cu.display_name AS corrected_by_name
         FROM log_entries le
         INNER JOIN log_days ld ON ld.id = le.log_day_id
         LEFT JOIN users u ON u.id = le.created_by
         LEFT JOIN users vu ON vu.id = le.voided_by
         LEFT JOIN users cu ON cu.id = le.corrected_by
         WHERE le.id = :id
         LIMIT 1'
    );

    $stmt->execute(['id' => $entryId]);
    $entry = $stmt->fetch();

    return $entry ?: null;
}

function recent_log_days(int $limit = 14): array
{
    $stmt = db()->prepare(
        'SELECT * FROM log_days ORDER BY operational_date DESC LIMIT :limit_value'
    );
    $stmt->bindValue(':limit_value', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function create_log_entry(array $data): int
{
    $stmt = db()->prepare(
        'INSERT INTO log_entries
            (log_day_id, event_time, category, location, inmate_name, entry_text, priority, is_late_entry, late_entry_reason, created_by, status, parent_entry_id, correction_reason)
         VALUES
            (:log_day_id, :event_time, :category, :location, :inmate_name, :entry_text, :priority, :is_late_entry, :late_entry_reason, :created_by, :status, :parent_entry_id, :correction_reason)'
    );

    $stmt->execute([
        'log_day_id' => (int) $data['log_day_id'],
        'event_time' => $data['event_time'],
        'category' => $data['category'],
        'location' => $data['location'] ?: null,
        'inmate_name' => $data['inmate_name'] ?: null,
        'entry_text' => $data['entry_text'],
        'priority' => $data['priority'],
        'is_late_entry' => !empty($data['is_late_entry']) ? 1 : 0,
        'late_entry_reason' => !empty($data['late_entry_reason']) ? $data['late_entry_reason'] : null,
        'created_by' => (int) $data['created_by'],
        'status' => $data['status'] ?? 'active',
        'parent_entry_id' => !empty($data['parent_entry_id']) ? (int) $data['parent_entry_id'] : null,
        'correction_reason' => !empty($data['correction_reason']) ? $data['correction_reason'] : null,
    ]);

    return (int) db()->lastInsertId();
}

function create_log_entry_snapshot(array $entry): string
{
    return json_encode([
        'id' => (int) $entry['id'],
        'log_day_id' => (int) $entry['log_day_id'],
        'event_time' => $entry['event_time'],
        'category' => $entry['category'],
        'priority' => $entry['priority'],
        'location' => $entry['location'],
        'inmate_name' => $entry['inmate_name'],
        'entry_text' => $entry['entry_text'],
        'created_by' => $entry['created_by'],
        'created_by_name' => $entry['created_by_name'] ?? null,
        'created_at' => $entry['created_at'],
        'status' => $entry['status'] ?? 'active',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

function record_log_entry_action(int $entryId, ?int $replacementEntryId, string $actionType, string $reason, array $entrySnapshot, int $performedBy): int
{
    $stmt = db()->prepare(
        'INSERT INTO log_entry_actions
            (log_entry_id, replacement_entry_id, action_type, reason, entry_snapshot, performed_by, performed_at)
         VALUES
            (:log_entry_id, :replacement_entry_id, :action_type, :reason, :entry_snapshot, :performed_by, NOW())'
    );

    $stmt->execute([
        'log_entry_id' => $entryId,
        'replacement_entry_id' => $replacementEntryId,
        'action_type' => $actionType,
        'reason' => $reason,
        'entry_snapshot' => create_log_entry_snapshot($entrySnapshot),
        'performed_by' => $performedBy,
    ]);

    return (int) db()->lastInsertId();
}

function record_log_entry_revision(int $entryId, int $replacementEntryId, array $oldEntry, array $newData, string $reason, int $correctedBy): void
{
    $stmt = db()->prepare(
        'INSERT INTO log_entry_revisions
            (log_entry_id, old_event_time, new_event_time, old_category, new_category, old_location, new_location, old_inmate_name, new_inmate_name, old_entry_text, new_entry_text, correction_reason, corrected_by)
         VALUES
            (:log_entry_id, :old_event_time, :new_event_time, :old_category, :new_category, :old_location, :new_location, :old_inmate_name, :new_inmate_name, :old_entry_text, :new_entry_text, :correction_reason, :corrected_by)'
    );

    $stmt->execute([
        'log_entry_id' => $entryId,
        'old_event_time' => $oldEntry['event_time'],
        'new_event_time' => $newData['event_time'],
        'old_category' => $oldEntry['category'],
        'new_category' => $newData['category'],
        'old_location' => $oldEntry['location'],
        'new_location' => $newData['location'] ?? null,
        'old_inmate_name' => $oldEntry['inmate_name'],
        'new_inmate_name' => $newData['inmate_name'] ?? null,
        'old_entry_text' => $oldEntry['entry_text'],
        'new_entry_text' => $newData['entry_text'],
        'correction_reason' => $reason,
        'corrected_by' => $correctedBy,
    ]);
}

function correct_log_entry(int $entryId, array $data, array $user): int
{
    $entry = get_log_entry_by_id($entryId);

    if (!$entry) {
        throw new RuntimeException('Log entry not found.');
    }

    if (($entry['status'] ?? 'active') !== 'active' || (int) ($entry['is_voided'] ?? 0) === 1) {
        throw new RuntimeException('Only active log entries can be corrected.');
    }

    $reason = trim((string) ($data['correction_reason'] ?? ''));

    if ($reason === '') {
        throw new RuntimeException('Correction reason is required.');
    }

    $eventTimeRaw = trim((string) ($data['event_time'] ?? ''));

    if ($eventTimeRaw === '') {
        throw new RuntimeException('Corrected event date and time is required.');
    }

    try {
        $eventTime = new DateTimeImmutable($eventTimeRaw);
    } catch (Throwable $exception) {
        throw new RuntimeException('Invalid corrected event date and time.');
    }

    if ($eventTime > new DateTimeImmutable('now')) {
        throw new RuntimeException('Corrected event time cannot be in the future.');
    }

    $logDay = get_or_create_log_day_for_event_time($eventTime, (int) $user['id']);

    $replacementEntryId = create_log_entry([
        'log_day_id' => (int) $logDay['id'],
        'event_time' => $eventTime->format('Y-m-d H:i:s'),
        'category' => $data['category'],
        'location' => $data['location'] ?? '',
        'inmate_name' => $data['inmate_name'] ?? '',
        'entry_text' => $data['entry_text'],
        'priority' => $data['priority'],
        'is_late_entry' => !empty($entry['is_late_entry']),
        'late_entry_reason' => $entry['late_entry_reason'] ?? null,
        'created_by' => (int) $user['id'],
        'status' => 'active',
        'parent_entry_id' => $entryId,
        'correction_reason' => $reason,
    ]);

    $stmt = db()->prepare(
        'UPDATE log_entries
         SET status = "corrected",
             corrected_by = :corrected_by,
             corrected_at = NOW(),
             correction_reason = :correction_reason
         WHERE id = :id'
    );
    $stmt->execute([
        'corrected_by' => (int) $user['id'],
        'correction_reason' => $reason,
        'id' => $entryId,
    ]);

    record_log_entry_revision($entryId, $replacementEntryId, $entry, [
        'event_time' => $eventTime->format('Y-m-d H:i:s'),
        'category' => $data['category'],
        'location' => $data['location'] ?? '',
        'inmate_name' => $data['inmate_name'] ?? '',
        'entry_text' => $data['entry_text'],
    ], $reason, (int) $user['id']);

    record_log_entry_action($entryId, $replacementEntryId, 'correction', $reason, $entry, (int) $user['id']);

    return $replacementEntryId;
}

function void_log_entry(int $entryId, string $reason, array $user): void
{
    $entry = get_log_entry_by_id($entryId);

    if (!$entry) {
        throw new RuntimeException('Log entry not found.');
    }

    if (($entry['status'] ?? 'active') !== 'active' || (int) ($entry['is_voided'] ?? 0) === 1) {
        throw new RuntimeException('Only active log entries can be voided.');
    }

    $reason = trim($reason);

    if ($reason === '') {
        throw new RuntimeException('Void reason is required.');
    }

    $stmt = db()->prepare(
        'UPDATE log_entries
         SET status = "voided",
             is_voided = 1,
             voided_by = :voided_by,
             voided_at = NOW(),
             void_reason = :void_reason
         WHERE id = :id'
    );
    $stmt->execute([
        'voided_by' => (int) $user['id'],
        'void_reason' => $reason,
        'id' => $entryId,
    ]);

    record_log_entry_action($entryId, null, 'void', $reason, $entry, (int) $user['id']);
}



function get_log_entry_actions_for_entry(int $entryId): array
{
    if (!function_exists('carceris_schema_table_exists') || !carceris_schema_table_exists('log_entry_actions')) {
        return [];
    }

    try {
        $stmt = db()->prepare(
            'SELECT
                lea.*,
                u.display_name AS performed_by_name,
                replacement.event_time AS replacement_event_time,
                replacement.category AS replacement_category,
                replacement.entry_text AS replacement_entry_text,
                original.event_time AS original_event_time,
                original.category AS original_category
             FROM log_entry_actions lea
             LEFT JOIN users u ON u.id = lea.performed_by
             LEFT JOIN log_entries replacement ON replacement.id = lea.replacement_entry_id
             LEFT JOIN log_entries original ON original.id = lea.log_entry_id
             WHERE lea.log_entry_id = :entry_id
                OR lea.replacement_entry_id = :entry_id
             ORDER BY lea.performed_at DESC, lea.id DESC'
        );

        $stmt->execute(['entry_id' => $entryId]);

        return $stmt->fetchAll();
    } catch (Throwable $exception) {
        error_log('Carceris entry action history query failed: ' . $exception->getMessage());

        return [];
    }
}

function get_log_entry_revisions_for_entry(int $entryId): array
{
    if (!function_exists('carceris_schema_table_exists') || !carceris_schema_table_exists('log_entry_revisions')) {
        return [];
    }

    try {
        $stmt = db()->prepare(
            'SELECT
                ler.*,
                u.display_name AS corrected_by_name
             FROM log_entry_revisions ler
             LEFT JOIN users u ON u.id = ler.corrected_by
             WHERE ler.log_entry_id = :entry_id
             ORDER BY ler.corrected_at DESC, ler.id DESC'
        );

        $stmt->execute(['entry_id' => $entryId]);

        return $stmt->fetchAll();
    } catch (Throwable $exception) {
        error_log('Carceris entry revision history query failed: ' . $exception->getMessage());

        return [];
    }
}


function get_log_entry_fallback_history_action(array $entry): ?array
{
    $label = entry_status_label($entry);

    if ($label === 'Voided') {
        return [
            'id' => 0,
            'log_entry_id' => (int) $entry['id'],
            'replacement_entry_id' => null,
            'action_type' => 'void',
            'reason' => (string) ($entry['void_reason'] ?? 'No void reason recorded.'),
            'entry_snapshot' => create_log_entry_snapshot($entry),
            'performed_by' => $entry['voided_by'] ?? null,
            'performed_by_name' => $entry['voided_by_name'] ?? 'Unknown',
            'performed_at' => $entry['voided_at'] ?? $entry['updated_at'] ?? $entry['created_at'],
        ];
    }

    if ($label === 'Corrected') {
        return [
            'id' => 0,
            'log_entry_id' => (int) $entry['id'],
            'replacement_entry_id' => null,
            'action_type' => 'correction',
            'reason' => (string) ($entry['correction_reason'] ?? 'No correction reason recorded.'),
            'entry_snapshot' => create_log_entry_snapshot($entry),
            'performed_by' => $entry['corrected_by'] ?? null,
            'performed_by_name' => $entry['corrected_by_name'] ?? 'Unknown',
            'performed_at' => $entry['corrected_at'] ?? $entry['updated_at'] ?? $entry['created_at'],
        ];
    }

    return null;
}


function log_entry_snapshot_from_action(array $action): array
{
    $snapshot = json_decode((string) ($action['entry_snapshot'] ?? ''), true);

    return is_array($snapshot) ? $snapshot : [];
}

function log_entry_has_history(array $entry): bool
{
    return entry_status_label($entry) !== 'Active' || !empty($entry['parent_entry_id']);
}




function log_archive_search_filters_active(array $filters): bool
{
    foreach (['q', 'date_from', 'date_to', 'category', 'priority', 'status', 'inmate', 'location', 'officer'] as $key) {
        if (trim((string) ($filters[$key] ?? '')) !== '') {
            return true;
        }
    }

    return !empty($filters['include_voided']);
}

function log_archive_search_entries(array $filters, int $limit = 150): array
{
    $limit = max(1, min($limit, 1000));

    $where = [];
    $params = [];

    $q = trim((string) ($filters['q'] ?? ''));

    if ($q !== '') {
        $where[] = '(le.entry_text LIKE :q_entry_text
            OR le.category LIKE :q_category
            OR le.location LIKE :q_location
            OR le.inmate_name LIKE :q_inmate
            OR u.display_name LIKE :q_display_name
            OR u.username LIKE :q_username
            OR ld.log_label LIKE :q_log_label)';

        foreach ([
            'q_entry_text',
            'q_category',
            'q_location',
            'q_inmate',
            'q_display_name',
            'q_username',
            'q_log_label',
        ] as $paramName) {
            $params[$paramName] = '%' . $q . '%';
        }
    }

    $dateFrom = trim((string) ($filters['date_from'] ?? ''));

    if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
        $where[] = 'ld.operational_date >= :date_from';
        $params['date_from'] = $dateFrom;
    }

    $dateTo = trim((string) ($filters['date_to'] ?? ''));

    if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
        $where[] = 'ld.operational_date <= :date_to';
        $params['date_to'] = $dateTo;
    }

    $category = trim((string) ($filters['category'] ?? ''));

    if ($category !== '') {
        $where[] = 'le.category = :category';
        $params['category'] = $category;
    }

    $priority = trim((string) ($filters['priority'] ?? ''));

    if (in_array($priority, ['normal', 'important', 'critical'], true)) {
        $where[] = 'le.priority = :priority';
        $params['priority'] = $priority;
    }

    $status = trim((string) ($filters['status'] ?? ''));

    if ($status === 'active') {
        $where[] = "(COALESCE(le.status, 'active') = 'active' AND COALESCE(le.is_voided, 0) = 0 AND le.parent_entry_id IS NULL)";
    } elseif ($status === 'corrected') {
        $where[] = "COALESCE(le.status, 'active') = 'corrected'";
    } elseif ($status === 'voided') {
        $where[] = "(COALESCE(le.status, 'active') = 'voided' OR COALESCE(le.is_voided, 0) = 1)";
    } elseif ($status === 'correction') {
        $where[] = 'le.parent_entry_id IS NOT NULL';
    } elseif (!carceris_show_voided_entries() && empty($filters['include_voided'])) {
        $where[] = "(COALESCE(le.status, 'active') <> 'voided' AND COALESCE(le.is_voided, 0) = 0)";
    }

    $inmate = trim((string) ($filters['inmate'] ?? ''));

    if ($inmate !== '') {
        $where[] = 'le.inmate_name LIKE :inmate';
        $params['inmate'] = '%' . $inmate . '%';
    }

    $location = trim((string) ($filters['location'] ?? ''));

    if ($location !== '') {
        $where[] = 'le.location LIKE :location';
        $params['location'] = '%' . $location . '%';
    }

    $officer = trim((string) ($filters['officer'] ?? ''));

    if ($officer !== '') {
        $where[] = '(u.display_name LIKE :officer_display_name OR u.username LIKE :officer_username)';
        $params['officer_display_name'] = '%' . $officer . '%';
        $params['officer_username'] = '%' . $officer . '%';
    }

    $sql = 'SELECT
            le.*,
            ld.operational_date,
            ld.log_label,
            ld.status AS log_day_status,
            u.display_name AS created_by_name,
            vu.display_name AS voided_by_name,
            cu.display_name AS corrected_by_name
        FROM log_entries le
        INNER JOIN log_days ld ON ld.id = le.log_day_id
        LEFT JOIN users u ON u.id = le.created_by
        LEFT JOIN users vu ON vu.id = le.voided_by
        LEFT JOIN users cu ON cu.id = le.corrected_by';

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY le.event_time DESC, le.id DESC LIMIT :limit_value';

    $stmt = db()->prepare($sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
    }

    $stmt->bindValue(':limit_value', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function log_archive_entries_to_csv(array $entries): string
{
    $handle = fopen('php://temp', 'r+');

    fputcsv($handle, [
        'entry_id',
        'operational_date',
        'log_label',
        'event_time',
        'category',
        'priority',
        'status',
        'location',
        'inmate_name',
        'entry_text',
        'officer',
        'late_entry',
        'late_entry_reason',
        'created_at',
    ]);

    foreach ($entries as $entry) {
        fputcsv($handle, [
            $entry['id'] ?? '',
            $entry['operational_date'] ?? '',
            $entry['log_label'] ?? '',
            $entry['event_time'] ?? '',
            $entry['category'] ?? '',
            $entry['priority'] ?? '',
            entry_status_label($entry),
            $entry['location'] ?? '',
            $entry['inmate_name'] ?? '',
            $entry['entry_text'] ?? '',
            $entry['created_by_name'] ?? '',
            !empty($entry['is_late_entry']) ? 'yes' : 'no',
            $entry['late_entry_reason'] ?? '',
            $entry['created_at'] ?? '',
        ]);
    }

    rewind($handle);
    $csv = stream_get_contents($handle);
    fclose($handle);

    return (string) $csv;
}


function format_log_time(string $datetime): string
{
    return carceris_format_time($datetime);
}

function format_log_datetime(string $datetime): string
{
    return carceris_format_datetime($datetime);
}

function format_log_date(string $date): string
{
    return (new DateTimeImmutable($date))->format('m/d/Y');
}

function entry_late_note(array $entry): string
{
    if (empty($entry['is_late_entry'])) {
        return '';
    }

    $createdAt = !empty($entry['created_at']) ? format_log_datetime($entry['created_at']) : 'unknown time';
    $reason = trim((string) ($entry['late_entry_reason'] ?? ''));

    if ($reason === '') {
        return 'Late/backfilled entry. Entered at ' . $createdAt . '.';
    }

    return 'Late/backfilled entry. Entered at ' . $createdAt . '. Reason: ' . $reason;
}

function entry_status_label(array $entry): string
{
    if ((int) ($entry['is_voided'] ?? 0) === 1 || ($entry['status'] ?? 'active') === 'voided') {
        return 'Voided';
    }

    if (($entry['status'] ?? 'active') === 'corrected') {
        return 'Corrected';
    }

    if (!empty($entry['parent_entry_id'])) {
        return 'Correction';
    }

    return 'Active';
}

function entry_status_note(array $entry): string
{
    $label = entry_status_label($entry);

    if ($label === 'Voided') {
        $parts = ['VOIDED'];

        if (!empty($entry['voided_at'])) {
            $parts[] = 'at ' . format_log_datetime($entry['voided_at']);
        }

        if (!empty($entry['voided_by_name'])) {
            $parts[] = 'by ' . $entry['voided_by_name'];
        }

        $note = implode(' ', $parts) . '.';

        if (!empty($entry['void_reason'])) {
            $note .= ' Reason: ' . $entry['void_reason'];
        }

        return $note;
    }

    if ($label === 'Corrected') {
        $parts = ['CORRECTED'];

        if (!empty($entry['corrected_at'])) {
            $parts[] = 'at ' . format_log_datetime($entry['corrected_at']);
        }

        if (!empty($entry['corrected_by_name'])) {
            $parts[] = 'by ' . $entry['corrected_by_name'];
        }

        $note = implode(' ', $parts) . '. Replacement entry appears separately.';

        if (!empty($entry['correction_reason'])) {
            $note .= ' Reason: ' . $entry['correction_reason'];
        }

        return $note;
    }

    if ($label === 'Correction') {
        $note = 'CORRECTION ENTRY. Replaces an earlier entry.';

        if (!empty($entry['correction_reason'])) {
            $note .= ' Reason: ' . $entry['correction_reason'];
        }

        return $note;
    }

    return '';
}

function entry_row_class(array $entry): string
{
    $classes = [];

    if (($entry['priority'] ?? 'normal') !== 'normal') {
        $classes[] = 'row-priority row-priority--' . e((string) $entry['priority']);
    }

    $label = entry_status_label($entry);

    if ($label === 'Voided') {
        $classes[] = 'row-status row-status--voided';
    } elseif ($label === 'Corrected') {
        $classes[] = 'row-status row-status--corrected';
    } elseif ($label === 'Correction') {
        $classes[] = 'row-status row-status--correction';
    }

    return implode(' ', $classes);
}
