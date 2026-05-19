<?php

declare(strict_types=1);

function audit_event(
    string $eventType,
    ?int $userId = null,
    ?string $username = null,
    ?string $details = null,
    ?string $relatedType = null,
    ?int $relatedId = null
): void {
    try {
        $stmt = db()->prepare(
            'INSERT INTO audit_events
                (event_type, user_id, username, ip_address, user_agent, details, related_type, related_id, created_at)
             VALUES
                (:event_type, :user_id, :username, :ip_address, :user_agent, :details, :related_type, :related_id, NOW())'
        );

        $stmt->execute([
            'event_type' => $eventType,
            'user_id' => $userId,
            'username' => $username,
            'ip_address' => client_ip_address(),
            'user_agent' => user_agent_string(),
            'details' => $details,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
        ]);
    } catch (Throwable $exception) {
        error_log('Carceris audit event failed: ' . $exception->getMessage());
    }
}


function audit_events_recent(int $limit = 100): array
{
    $limit = max(1, min($limit, 250));

    $stmt = db()->prepare(
        'SELECT *
         FROM audit_events
         ORDER BY created_at DESC, id DESC
         LIMIT :limit_value'
    );
    $stmt->bindValue(':limit_value', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}


function audit_events_by_types(array $eventTypes, int $limit = 50): array
{
    $eventTypes = array_values(array_filter($eventTypes, static fn ($type) => is_string($type) && $type !== ''));

    if (!$eventTypes) {
        return [];
    }

    $limit = max(1, min($limit, 250));
    $placeholders = [];

    foreach ($eventTypes as $index => $eventType) {
        $placeholders[] = ':event_type_' . $index;
    }

    $sql = 'SELECT *
            FROM audit_events
            WHERE event_type IN (' . implode(', ', $placeholders) . ')
            ORDER BY created_at DESC, id DESC
            LIMIT :limit_value';

    $stmt = db()->prepare($sql);

    foreach ($eventTypes as $index => $eventType) {
        $stmt->bindValue(':event_type_' . $index, $eventType, PDO::PARAM_STR);
    }

    $stmt->bindValue(':limit_value', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}


function audit_events_filtered(array $filters, int $limit = 150): array
{
    $limit = max(1, min($limit, 1000));

    $where = [];
    $params = [];

    $q = trim((string) ($filters['q'] ?? ''));

    if ($q !== '') {
        $where[] = '(event_type LIKE :q_event_type
            OR username LIKE :q_username
            OR details LIKE :q_details
            OR ip_address LIKE :q_ip_address
            OR related_type LIKE :q_related_type)';

        foreach ([
            'q_event_type',
            'q_username',
            'q_details',
            'q_ip_address',
            'q_related_type',
        ] as $paramName) {
            $params[$paramName] = '%' . $q . '%';
        }
    }

    $eventType = trim((string) ($filters['event_type'] ?? ''));

    if ($eventType !== '') {
        $where[] = 'event_type = :event_type';
        $params['event_type'] = $eventType;
    }

    $username = trim((string) ($filters['username'] ?? ''));

    if ($username !== '') {
        $where[] = 'username LIKE :username_filter';
        $params['username_filter'] = '%' . $username . '%';
    }

    $dateFrom = trim((string) ($filters['date_from'] ?? ''));

    if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
        $where[] = 'created_at >= :date_from';
        $params['date_from'] = $dateFrom . ' 00:00:00';
    }

    $dateTo = trim((string) ($filters['date_to'] ?? ''));

    if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
        $where[] = 'created_at <= :date_to';
        $params['date_to'] = $dateTo . ' 23:59:59';
    }

    $sql = 'SELECT * FROM audit_events';

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY created_at DESC, id DESC LIMIT :limit_value';

    $stmt = db()->prepare($sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
    }

    $stmt->bindValue(':limit_value', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function audit_event_types(): array
{
    $stmt = db()->query(
        'SELECT DISTINCT event_type
         FROM audit_events
         ORDER BY event_type ASC'
    );

    return array_map(static fn ($row) => $row['event_type'], $stmt->fetchAll());
}

function audit_events_to_csv(array $events): string
{
    $handle = fopen('php://temp', 'r+');

    fputcsv($handle, [
        'id',
        'created_at',
        'event_type',
        'username',
        'user_id',
        'ip_address',
        'related_type',
        'related_id',
        'details',
        'user_agent',
    ]);

    foreach ($events as $event) {
        fputcsv($handle, [
            $event['id'] ?? '',
            $event['created_at'] ?? '',
            $event['event_type'] ?? '',
            $event['username'] ?? '',
            $event['user_id'] ?? '',
            $event['ip_address'] ?? '',
            $event['related_type'] ?? '',
            $event['related_id'] ?? '',
            $event['details'] ?? '',
            $event['user_agent'] ?? '',
        ]);
    }

    rewind($handle);
    $csv = stream_get_contents($handle);
    fclose($handle);

    return (string) $csv;
}

function audit_prune_older_than_days(int $days): int
{
    $days = max(1, min($days, 3650));
    $cutoff = (new DateTimeImmutable('now'))->modify('-' . $days . ' days')->format('Y-m-d H:i:s');

    $stmt = db()->prepare('DELETE FROM audit_events WHERE created_at < :cutoff');
    $stmt->execute(['cutoff' => $cutoff]);

    return $stmt->rowCount();
}
