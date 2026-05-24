<?php

declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function app_config(string $section, ?string $key = null, mixed $default = null): mixed
{
    global $config;

    if (!array_key_exists($section, $config)) {
        return $default;
    }

    if ($key === null) {
        return $config[$section];
    }

    return $config[$section][$key] ?? $default;
}

function setting(string $key, ?string $default = null): ?string
{
    $stmt = db()->prepare('SELECT setting_value FROM settings WHERE setting_key = :setting_key LIMIT 1');
    $stmt->execute(['setting_key' => $key]);
    $value = $stmt->fetchColumn();

    if ($value === false) {
        return $default;
    }

    return (string) $value;
}

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'][] = [
        'type' => $type,
        'message' => $message,
    ];
}

function flash_get(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return $messages;
}

function request_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function post_string(string $key, int $maxLength = 10000): string
{
    $value = trim((string) ($_POST[$key] ?? ''));

    if (function_exists('mb_strlen') && mb_strlen($value) > $maxLength) {
        $value = mb_substr($value, 0, $maxLength);
    } elseif (!function_exists('mb_strlen') && strlen($value) > $maxLength) {
        $value = substr($value, 0, $maxLength);
    }

    return $value;
}

function get_string(string $key, int $maxLength = 255): string
{
    $value = trim((string) ($_GET[$key] ?? ''));

    if (function_exists('mb_strlen') && mb_strlen($value) > $maxLength) {
        $value = mb_substr($value, 0, $maxLength);
    } elseif (!function_exists('mb_strlen') && strlen($value) > $maxLength) {
        $value = substr($value, 0, $maxLength);
    }

    return $value;
}

function current_datetime(): string
{
    return (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
}

function categories(): array
{
    $stmt = db()->query(
        'SELECT id, name FROM categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC'
    );

    return $stmt->fetchAll();
}


function is_https_request(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function client_ip_address(): string
{
    $candidates = [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
        $_SERVER['REMOTE_ADDR'] ?? '',
    ];

    foreach ($candidates as $candidate) {
        $candidate = trim((string) $candidate);

        if ($candidate === '') {
            continue;
        }

        if (str_contains($candidate, ',')) {
            $candidate = trim(explode(',', $candidate)[0]);
        }

        if (filter_var($candidate, FILTER_VALIDATE_IP)) {
            return $candidate;
        }
    }

    return 'unknown';
}

function user_agent_string(): string
{
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');

    if (strlen($ua) > 255) {
        $ua = substr($ua, 0, 255);
    }

    return $ua;
}

function category_exists(string $name): bool
{
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM categories WHERE name = :name AND is_active = 1'
    );
    $stmt->execute(['name' => $name]);

    return (int) $stmt->fetchColumn() > 0;
}



function carceris_environment_mode(): string
{
    $mode = setting('environment_mode', app_config('app', 'environment_mode', 'testing')) ?: 'testing';
    $mode = strtolower(trim($mode));

    $allowed = ['testing', 'internal', 'production'];

    if (!in_array($mode, $allowed, true)) {
        return 'testing';
    }

    return $mode;
}

function carceris_install_lock_exists(): bool
{
    return is_file(carceris_project_root() . '/storage/installed.lock');
}

function carceris_database_status(): array
{
    try {
        $stmt = db()->query('SELECT 1 AS ok');
        $result = $stmt->fetch();

        return [
            'ok' => isset($result['ok']) && (int) $result['ok'] === 1,
            'message' => 'Connected',
        ];
    } catch (Throwable $exception) {
        return [
            'ok' => false,
            'message' => $exception->getMessage(),
        ];
    }
}

function carceris_php_status(): array
{
    return [
        'version' => PHP_VERSION,
        'sapi' => PHP_SAPI,
        'pdo_mysql' => extension_loaded('pdo_mysql'),
    ];
}


function carceris_config_local_exists(): bool
{
    return is_file(carceris_project_root() . '/app/config/config.local.php');
}

function carceris_install_directory_exists(): bool
{
    return is_dir(carceris_project_root() . '/install');
}

function carceris_path_writable(string $relativePath): bool
{
    return is_writable(carceris_project_root() . '/' . ltrim($relativePath, '/'));
}

function carceris_htaccess_exists(string $relativePath): bool
{
    return is_file(carceris_project_root() . '/' . trim($relativePath, '/') . '/.htaccess');
}

function carceris_force_https_enabled(): bool
{
    $value = setting('force_https', app_config('app', 'force_https', false) ? '1' : '0');

    return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
}

function carceris_enforce_https_if_enabled(): void
{
    if (!carceris_force_https_enabled() || is_https_request()) {
        return;
    }

    if (headers_sent()) {
        return;
    }

    $host = $_SERVER['HTTP_HOST'] ?? '';

    if ($host === '') {
        return;
    }

    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    header('Location: https://' . $host . $requestUri, true, 302);
    exit;
}

function carceris_security_status_checks(): array
{
    $projectRoot = carceris_project_root();

    return [
        'config_local_exists' => carceris_config_local_exists(),
        'config_directory_writable' => carceris_path_writable('app/config'),
        'storage_writable' => carceris_path_writable('storage'),
        'install_directory_exists' => carceris_install_directory_exists(),
        'app_htaccess_exists' => carceris_htaccess_exists('app'),
        'app_config_htaccess_exists' => carceris_htaccess_exists('app/config'),
        'database_htaccess_exists' => carceris_htaccess_exists('database'),
        'storage_htaccess_exists' => carceris_htaccess_exists('storage'),
        'tools_htaccess_exists' => carceris_htaccess_exists('tools'),
        'force_https_enabled' => carceris_force_https_enabled(),
        'debug_disabled' => !carceris_debug_enabled_from_config(),
        'installer_locked' => carceris_install_lock_exists(),
        'config_local_web_risk' => is_file($projectRoot . '/public/app/config/config.local.php'),
    ];
}

function carceris_roles(): array
{
    return ['admin', 'supervisor', 'officer', 'viewer'];
}

function carceris_role_label(string $role): string
{
    return match ($role) {
        'admin' => 'Admin',
        'supervisor' => 'Supervisor',
        'officer' => 'Officer',
        'viewer' => 'Viewer',
        default => ucfirst($role),
    };
}

function carceris_role_description(string $role): string
{
    return match ($role) {
        'admin' => 'Full system administration. Can manage users, categories, settings, backups, restores, upgrades, audit, status, active logs, archives, and reports.',
        'supervisor' => 'Operational authority. Can enter active logs, correct or void entries, view and print archives, generate daily logs, manually send completed logs, and review audit events.',
        'officer' => 'Standard log user. Can enter active log entries, view archives, and print logs.',
        'viewer' => 'Read-only access. Can view archives and print logs, but cannot view the live Active Log or create entries.',
        default => 'No role description available.',
    };
}

function carceris_role_permission_summary(): array
{
    return [
        'admin' => [
            'Create active log entries',
            'View the live Active Log',
            'View archives and print logs',
            'Generate and manually send daily logs',
            'Correct and void log entries',
            'Manage users and roles',
            'Manage categories and settings',
            'Manage log delivery settings',
            'Run backups, restores, and upgrades',
            'View audit, export audit CSV, prune audit, and view system status',
        ],
        'supervisor' => [
            'Create active log entries',
            'View the live Active Log',
            'View archives and print logs',
            'Generate daily logs',
            'Correct and void log entries',
            'Manually send the previous completed log',
            'View and search audit events',
        ],
        'officer' => [
            'Create active log entries',
            'View the live Active Log',
            'View archives',
            'Print logs',
        ],
        'viewer' => [
            'View archives',
            'Print logs',
        ],
    ];
}

function carceris_role_permission_text(string $role): string
{
    $summary = carceris_role_permission_summary();

    return implode('; ', $summary[$role] ?? []);
}


function setting_update(string $key, string $value): void
{
    $stmt = db()->prepare(
        'INSERT INTO settings (setting_key, setting_value)
         VALUES (:setting_key, :setting_value)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );

    $stmt->execute([
        'setting_key' => $key,
        'setting_value' => $value,
    ]);
}

function carceris_clock_format(): string
{
    $format = strtolower(trim((string) (setting('clock_format', '24') ?? '24')));

    return $format === '12' ? '12' : '24';
}

function carceris_time_php_format(): string
{
    return carceris_clock_format() === '12' ? 'g:i A' : 'H:i';
}

function carceris_datetime_php_format(): string
{
    return 'm/d/Y ' . carceris_time_php_format();
}

function carceris_format_time(string|DateTimeInterface $datetime): string
{
    $dt = is_string($datetime)
        ? new DateTimeImmutable($datetime)
        : DateTimeImmutable::createFromInterface($datetime);

    return $dt->format(carceris_time_php_format());
}

function carceris_format_datetime(string|DateTimeInterface $datetime): string
{
    $dt = is_string($datetime)
        ? new DateTimeImmutable($datetime)
        : DateTimeImmutable::createFromInterface($datetime);

    return $dt->format(carceris_datetime_php_format());
}



function carceris_categories_all(): array
{
    $stmt = db()->query(
        'SELECT id, name, sort_order, is_active, created_at
         FROM categories
         ORDER BY sort_order ASC, name ASC, id ASC'
    );

    return $stmt->fetchAll();
}

function carceris_category_by_id(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT id, name, sort_order, is_active, created_at
         FROM categories
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $id]);
    $category = $stmt->fetch();

    return $category ?: null;
}

function carceris_category_name_exists(string $name, ?int $excludeId = null): bool
{
    if ($excludeId === null) {
        $stmt = db()->prepare('SELECT COUNT(*) FROM categories WHERE name = :name');
        $stmt->execute(['name' => $name]);
        return (int) $stmt->fetchColumn() > 0;
    }

    $stmt = db()->prepare('SELECT COUNT(*) FROM categories WHERE name = :name AND id <> :id');
    $stmt->execute([
        'name' => $name,
        'id' => $excludeId,
    ]);

    return (int) $stmt->fetchColumn() > 0;
}

function carceris_validate_category_name(string $name): string
{
    $name = trim($name);

    if ($name === '') {
        throw new RuntimeException('Category name is required.');
    }

    if (strlen($name) > 80) {
        throw new RuntimeException('Category name cannot exceed 80 characters.');
    }

    return $name;
}

function carceris_create_category(string $name, bool $isActive = true): int
{
    $name = carceris_validate_category_name($name);

    if (carceris_category_name_exists($name)) {
        throw new RuntimeException('A category with that name already exists.');
    }

    $sortOrder = carceris_next_category_sort_order();

    $stmt = db()->prepare(
        'INSERT INTO categories (name, sort_order, is_active)
         VALUES (:name, :sort_order, :is_active)'
    );

    $stmt->execute([
        'name' => $name,
        'sort_order' => $sortOrder,
        'is_active' => $isActive ? 1 : 0,
    ]);

    return (int) db()->lastInsertId();
}

function carceris_update_category(int $id, string $name, bool $isActive): void
{
    $existing = carceris_category_by_id($id);

    if (!$existing) {
        throw new RuntimeException('Category not found.');
    }

    $name = carceris_validate_category_name($name);

    if (carceris_category_name_exists($name, $id)) {
        throw new RuntimeException('A category with that name already exists.');
    }

    $stmt = db()->prepare(
        'UPDATE categories
         SET name = :name,
             is_active = :is_active
         WHERE id = :id'
    );

    $stmt->execute([
        'name' => $name,
        'is_active' => $isActive ? 1 : 0,
        'id' => $id,
    ]);
}


function carceris_next_category_sort_order(): int
{
    $stmt = db()->query('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM categories');

    return (int) $stmt->fetchColumn();
}

function carceris_normalize_category_sort_order(): void
{
    $categories = carceris_categories_all();

    $sortOrder = 10;
    $stmt = db()->prepare('UPDATE categories SET sort_order = :sort_order WHERE id = :id');

    foreach ($categories as $category) {
        $stmt->execute([
            'sort_order' => $sortOrder,
            'id' => (int) $category['id'],
        ]);

        $sortOrder += 10;
    }
}

function carceris_move_category(int $id, string $direction): ?array
{
    if (!in_array($direction, ['up', 'down'], true)) {
        throw new RuntimeException('Invalid category move direction.');
    }

    $category = carceris_category_by_id($id);

    if (!$category) {
        throw new RuntimeException('Category not found.');
    }

    carceris_normalize_category_sort_order();

    $categories = carceris_categories_all();
    $currentIndex = null;

    foreach ($categories as $index => $row) {
        if ((int) $row['id'] === $id) {
            $currentIndex = $index;
            break;
        }
    }

    if ($currentIndex === null) {
        throw new RuntimeException('Category not found.');
    }

    $targetIndex = $direction === 'up'
        ? $currentIndex - 1
        : $currentIndex + 1;

    if (!isset($categories[$targetIndex])) {
        return null;
    }

    $current = $categories[$currentIndex];
    $target = $categories[$targetIndex];

    $stmt = db()->prepare('UPDATE categories SET sort_order = :sort_order WHERE id = :id');

    $stmt->execute([
        'sort_order' => (int) $target['sort_order'],
        'id' => (int) $current['id'],
    ]);

    $stmt->execute([
        'sort_order' => (int) $current['sort_order'],
        'id' => (int) $target['id'],
    ]);

    carceris_normalize_category_sort_order();

    return [
        'moved' => $current,
        'swapped_with' => $target,
        'direction' => $direction,
    ];
}


function carceris_report_delivery_defaults(): array
{
    return [
        'report_delivery_enabled' => '0',
        'report_send_time' => '05:00',
        'report_mail_transport' => 'manual_only',
        'report_recipients_to' => '',
        'report_recipients_cc' => '',
        'report_recipients_bcc' => '',
        'report_body_format' => 'plain_text',
        'report_attachment_format' => 'none',
        'report_from_name' => 'Carceris',
        'report_from_email' => '',
        'report_cron_key' => '',
        'report_smtp_host' => '',
        'report_smtp_port' => '587',
        'report_smtp_encryption' => 'tls',
        'report_smtp_username' => '',
        'report_smtp_password' => '',
        'report_sendmail_path' => '/usr/sbin/sendmail',
    ];
}

function carceris_report_delivery_settings(): array
{
    $defaults = carceris_report_delivery_defaults();
    $settings = [];

    foreach ($defaults as $key => $default) {
        $settings[$key] = setting($key, $default) ?? $default;
    }

    if ($settings['report_cron_key'] === '') {
        $settings['report_cron_key'] = carceris_generate_cron_key();
        setting_update('report_cron_key', $settings['report_cron_key']);
    }

    return $settings;
}

function carceris_generate_cron_key(): string
{
    return bin2hex(random_bytes(32));
}

function carceris_validate_report_send_time(string $time): string
{
    $time = trim($time);

    if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
        throw new RuntimeException('Report send time must use HH:MM format.');
    }

    [$hour, $minute] = array_map('intval', explode(':', $time));

    if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
        throw new RuntimeException('Report send time is invalid.');
    }

    return sprintf('%02d:%02d', $hour, $minute);
}

function carceris_report_transport_options(): array
{
    return [
        'manual_only' => 'Manual Export Only',
        'php_mail' => 'PHP Mail',
        'smtp' => 'Native SMTP',
        'smtp_phpmailer' => 'SMTP via PHPMailer (optional library)',
        'sendmail' => 'Sendmail',
    ];
}

function carceris_report_body_format_options(): array
{
    return [
        'plain_text' => 'Plain Text',
        'html' => 'HTML',
        'none' => 'None',
    ];
}

function carceris_report_attachment_format_options(): array
{
    return [
        'none' => 'None',
        'pdf' => 'PDF',
        'plain_text' => 'Plain Text',
        'html' => 'HTML',
    ];
}

function carceris_validate_option(string $value, array $allowed, string $label): string
{
    if (!array_key_exists($value, $allowed)) {
        throw new RuntimeException($label . ' is invalid.');
    }

    return $value;
}

function carceris_normalize_email_list(string $emails): string
{
    $emails = str_replace(["\r\n", "\r", "\n", ";"], ',', $emails);
    $parts = array_filter(array_map('trim', explode(',', $emails)));

    $clean = [];

    foreach ($parts as $email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Invalid email address: ' . $email);
        }

        $clean[] = strtolower($email);
    }

    return implode(', ', array_unique($clean));
}

function carceris_recent_report_deliveries(int $limit = 25): array
{
    $limit = max(1, min($limit, 100));

    $sql = '
        SELECT activity.*, ld.log_label, ld.operational_date
        FROM (
            SELECT
                red.id,
                "email_delivery" AS activity_source,
                red.log_day_id,
                red.delivery_type,
                red.transport,
                red.body_format,
                red.attachment_format,
                red.recipient_to,
                red.recipient_cc,
                red.recipient_bcc,
                red.subject,
                red.status,
                red.error_message,
                red.sent_at,
                red.triggered_by,
                red.created_at
            FROM report_email_deliveries red

            UNION ALL

            SELECT
                rte.id,
                "test_email" AS activity_source,
                NULL AS log_day_id,
                "test" AS delivery_type,
                rte.transport,
                rte.body_format,
                "none" AS attachment_format,
                rte.recipient_to,
                rte.recipient_cc,
                rte.recipient_bcc,
                rte.subject,
                rte.status,
                rte.error_message,
                rte.sent_at,
                rte.triggered_by,
                rte.created_at
            FROM report_test_emails rte

            UNION ALL

            SELECT
                rdl.id,
                "download" AS activity_source,
                rdl.log_day_id,
                "download" AS delivery_type,
                "download" AS transport,
                rdl.body_format,
                rdl.attachment_format,
                "" AS recipient_to,
                "" AS recipient_cc,
                "" AS recipient_bcc,
                rdl.subject,
                rdl.status,
                NULL AS error_message,
                NULL AS sent_at,
                rdl.triggered_by,
                rdl.created_at
            FROM report_downloads rdl
        ) activity
        LEFT JOIN log_days ld ON ld.id = activity.log_day_id
        ORDER BY activity.created_at DESC, activity.id DESC
        LIMIT :limit_value';

    $stmt = db()->prepare($sql);
    $stmt->bindValue(':limit_value', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}


function carceris_show_voided_entries(): bool
{
    return (setting('show_voided_entries', '1') ?? '1') === '1';
}


function carceris_operational_day_start_time(): string
{
    $time = setting('daily_log_start_time', '05:00') ?? '05:00';

    if (!is_string($time) || !preg_match('/^\d{2}:\d{2}$/', $time)) {
        return '05:00';
    }

    [$hour, $minute] = array_map('intval', explode(':', $time));

    if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
        return '05:00';
    }

    return sprintf('%02d:%02d', $hour, $minute);
}

function carceris_validate_time_value(string $time, string $label): string
{
    $time = trim($time);

    if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
        throw new RuntimeException($label . ' must use HH:MM format.');
    }

    [$hour, $minute] = array_map('intval', explode(':', $time));

    if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
        throw new RuntimeException($label . ' is invalid.');
    }

    return sprintf('%02d:%02d', $hour, $minute);
}


function carceris_time_display_parts(string $time): array
{
    $time = carceris_validate_time_value($time, 'Time');

    [$hour, $minute] = array_map('intval', explode(':', $time));

    $period = $hour >= 12 ? 'PM' : 'AM';
    $hour12 = $hour % 12;

    if ($hour12 === 0) {
        $hour12 = 12;
    }

    return [
        'hour_24' => sprintf('%02d', $hour),
        'minute' => sprintf('%02d', $minute),
        'period' => $period,
        'hour_12' => (string) $hour12,
        'value_24' => sprintf('%02d:%02d', $hour, $minute),
    ];
}

function carceris_time_from_clock_format_post(string $field, string $label): string
{
    $minute = (int) ($_POST[$field . '_minute'] ?? -1);

    if ($minute < 0 || $minute > 59) {
        throw new RuntimeException($label . ' minute is invalid.');
    }

    if (carceris_clock_format() === '12') {
        $hour = (int) ($_POST[$field . '_hour'] ?? 0);
        $period = strtoupper(trim((string) ($_POST[$field . '_period'] ?? '')));

        if ($hour < 1 || $hour > 12) {
            throw new RuntimeException($label . ' hour is invalid.');
        }

        if (!in_array($period, ['AM', 'PM'], true)) {
            throw new RuntimeException($label . ' period is invalid.');
        }

        $hour24 = $hour;

        if ($period === 'AM' && $hour === 12) {
            $hour24 = 0;
        }

        if ($period === 'PM' && $hour !== 12) {
            $hour24 = $hour + 12;
        }

        return sprintf('%02d:%02d', $hour24, $minute);
    }

    $hour = (int) ($_POST[$field . '_hour_24'] ?? -1);

    if ($hour < 0 || $hour > 23) {
        throw new RuntimeException($label . ' hour is invalid.');
    }

    return sprintf('%02d:%02d', $hour, $minute);
}


function carceris_safe_return_path(string $path, string $fallback = '/index.php'): string
{
    $path = trim($path);

    if ($path === '') {
        return $fallback;
    }

    if (!str_starts_with($path, '/') || str_starts_with($path, '//')) {
        return $fallback;
    }

    if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $path)) {
        return $fallback;
    }

    if (str_contains($path, "\r") || str_contains($path, "\n")) {
        return $fallback;
    }

    return $path;
}

function carceris_return_query_value(string $path): string
{
    return rawurlencode(carceris_safe_return_path($path));
}


function carceris_report_delivery_activity_label(array $delivery): string
{
    $source = (string) ($delivery['activity_source'] ?? '');
    $type = (string) ($delivery['delivery_type'] ?? '');

    if ($source === 'test_email' || $type === 'test') {
        return 'Test Email';
    }

    if ($source === 'download' || $type === 'download') {
        return 'Log Download';
    }

    if ($type === 'automatic') {
        return 'Scheduled Log Send';
    }

    if ($type === 'manual') {
        return 'Manual Log Send';
    }

    if ($type === 'placeholder') {
        return 'System Placeholder';
    }

    return 'Log Activity';
}

function carceris_report_delivery_status_label(string $status): string
{
    return match ($status) {
        'sent' => 'Sent',
        'failed' => 'Failed',
        'skipped' => 'Skipped',
        'generated' => 'Downloaded',
        'placeholder' => 'Placeholder',
        'pending' => 'Pending',
        default => ucfirst($status),
    };
}

function carceris_report_delivery_transport_label(string $transport): string
{
    return match ($transport) {
        'download' => 'Download',
        'manual_only' => 'Manual / Download',
        'php_mail' => 'PHP Mail',
        'smtp' => 'Native SMTP',
        'smtp_phpmailer' => 'PHPMailer SMTP',
        'sendmail' => 'Sendmail',
        default => ucfirst(str_replace('_', ' ', $transport)),
    };
}

function carceris_report_delivery_format_label(array $delivery): string
{
    $body = (string) ($delivery['body_format'] ?? 'none');
    $attachment = (string) ($delivery['attachment_format'] ?? 'none');

    $labels = [
        'plain_text' => 'Text',
        'html' => 'HTML',
        'pdf' => 'PDF',
        'none' => 'None',
    ];

    $bodyLabel = $labels[$body] ?? ucfirst(str_replace('_', ' ', $body));
    $attachmentLabel = $labels[$attachment] ?? ucfirst(str_replace('_', ' ', $attachment));

    if ($attachment === 'none') {
        return 'Body: ' . $bodyLabel;
    }

    if ($body === 'none') {
        return 'Attachment: ' . $attachmentLabel;
    }

    return 'Body: ' . $bodyLabel . ', Attachment: ' . $attachmentLabel;
}

function carceris_report_delivery_recipient_label(array $delivery): string
{
    $to = trim((string) ($delivery['recipient_to'] ?? ''));

    if ($to === '') {
        return 'None';
    }

    return $to;
}


function carceris_header_brand_name(): string
{
    $name = trim((string) (setting('app_name', app_config('app', 'name', 'Carceris')) ?? 'Carceris'));

    if ($name === '') {
        return 'Carceris';
    }

    if (function_exists('mb_strlen') && mb_strlen($name) > 80) {
        return mb_substr($name, 0, 80);
    }

    if (!function_exists('mb_strlen') && strlen($name) > 80) {
        return substr($name, 0, 80);
    }

    return $name;
}

function carceris_header_brand_tagline(): string
{
    $tagline = trim((string) (setting('app_tagline', app_config('app', 'tagline', 'Secure daily logging for correctional facilities.')) ?? 'Secure daily logging for correctional facilities.'));

    if ($tagline === '') {
        return 'Secure daily logging for correctional facilities.';
    }

    if (function_exists('mb_strlen') && mb_strlen($tagline) > 160) {
        return mb_substr($tagline, 0, 160);
    }

    if (!function_exists('mb_strlen') && strlen($tagline) > 160) {
        return substr($tagline, 0, 160);
    }

    return $tagline;
}


function carceris_print_log_title(): string
{
    $title = trim((string) (setting('print_log_title', 'Correctional Facility Daily Log') ?? 'Correctional Facility Daily Log'));

    if ($title === '') {
        return 'Correctional Facility Daily Log';
    }

    if (function_exists('mb_strlen') && mb_strlen($title) > 120) {
        return mb_substr($title, 0, 120);
    }

    if (!function_exists('mb_strlen') && strlen($title) > 120) {
        return substr($title, 0, 120);
    }

    return $title;
}


function carceris_login_brand_name(): string
{
    $name = trim((string) (setting('login_name', carceris_header_brand_name()) ?? carceris_header_brand_name()));

    if ($name === '') {
        return carceris_header_brand_name();
    }

    if (function_exists('mb_strlen') && mb_strlen($name) > 80) {
        return mb_substr($name, 0, 80);
    }

    if (!function_exists('mb_strlen') && strlen($name) > 80) {
        return substr($name, 0, 80);
    }

    return $name;
}

function carceris_login_brand_tagline(): string
{
    $tagline = trim((string) (setting('login_tagline', carceris_header_brand_tagline()) ?? carceris_header_brand_tagline()));

    if ($tagline === '') {
        return carceris_header_brand_tagline();
    }

    if (function_exists('mb_strlen') && mb_strlen($tagline) > 160) {
        return mb_substr($tagline, 0, 160);
    }

    if (!function_exists('mb_strlen') && strlen($tagline) > 160) {
        return substr($tagline, 0, 160);
    }

    return $tagline;
}


function carceris_recent_completed_log_days(int $limit = 10): array
{
    $limit = max(1, min($limit, 50));
    $currentWindow = current_operational_window();
    $currentOperationalDate = $currentWindow['operational_date'];

    $stmt = db()->prepare(
        'SELECT
            ld.*,
            COUNT(le.id) AS entry_count
         FROM log_days ld
         LEFT JOIN log_entries le ON le.log_day_id = ld.id
         WHERE ld.operational_date < :current_operational_date
         GROUP BY ld.id
         ORDER BY ld.operational_date DESC
         LIMIT :limit_value'
    );

    $stmt->bindValue(':current_operational_date', $currentOperationalDate, PDO::PARAM_STR);
    $stmt->bindValue(':limit_value', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function carceris_log_day_by_id(int $logDayId): ?array
{
    $stmt = db()->prepare('SELECT * FROM log_days WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $logDayId]);
    $logDay = $stmt->fetch();

    return $logDay ?: null;
}

function carceris_latest_log_delivery_for_log_day(int $logDayId): ?array
{
    $stmt = db()->prepare(
        'SELECT *
         FROM report_email_deliveries
         WHERE log_day_id = :log_day_id
         ORDER BY created_at DESC, id DESC
         LIMIT 1'
    );

    $stmt->execute(['log_day_id' => $logDayId]);
    $delivery = $stmt->fetch();

    return $delivery ?: null;
}

function carceris_delivery_summary_for_log_day(int $logDayId): array
{
    $stmt = db()->prepare(
        'SELECT
            SUM(CASE WHEN status = "sent" THEN 1 ELSE 0 END) AS sent_count,
            SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) AS failed_count,
            SUM(CASE WHEN delivery_type = "automatic" AND status = "sent" THEN 1 ELSE 0 END) AS scheduled_sent_count,
            MAX(created_at) AS last_activity_at
         FROM report_email_deliveries
         WHERE log_day_id = :log_day_id'
    );

    $stmt->execute(['log_day_id' => $logDayId]);
    $summary = $stmt->fetch() ?: [];

    $latest = carceris_latest_log_delivery_for_log_day($logDayId);

    return [
        'sent_count' => (int) ($summary['sent_count'] ?? 0),
        'failed_count' => (int) ($summary['failed_count'] ?? 0),
        'scheduled_sent_count' => (int) ($summary['scheduled_sent_count'] ?? 0),
        'last_activity_at' => $summary['last_activity_at'] ?? null,
        'latest_status' => $latest['status'] ?? null,
        'latest_type' => $latest['delivery_type'] ?? null,
        'latest_transport' => $latest['transport'] ?? null,
        'latest_error' => $latest['error_message'] ?? null,
    ];
}

function carceris_recent_failed_log_deliveries(int $limit = 10): array
{
    $limit = max(1, min($limit, 50));

    $stmt = db()->prepare(
        'SELECT red.*, ld.log_label, ld.operational_date
         FROM report_email_deliveries red
         LEFT JOIN log_days ld ON ld.id = red.log_day_id
         WHERE red.status = "failed"
         ORDER BY red.created_at DESC, red.id DESC
         LIMIT :limit_value'
    );

    $stmt->bindValue(':limit_value', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function carceris_recent_scheduled_log_deliveries(int $limit = 10): array
{
    $limit = max(1, min($limit, 50));

    $stmt = db()->prepare(
        'SELECT red.*, ld.log_label, ld.operational_date
         FROM report_email_deliveries red
         LEFT JOIN log_days ld ON ld.id = red.log_day_id
         WHERE red.delivery_type = "automatic"
         ORDER BY red.created_at DESC, red.id DESC
         LIMIT :limit_value'
    );

    $stmt->bindValue(':limit_value', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function carceris_report_email_delivery_by_id(int $deliveryId): ?array
{
    $stmt = db()->prepare(
        'SELECT red.*, ld.log_label, ld.operational_date
         FROM report_email_deliveries red
         LEFT JOIN log_days ld ON ld.id = red.log_day_id
         WHERE red.id = :id
         LIMIT 1'
    );

    $stmt->execute(['id' => $deliveryId]);
    $delivery = $stmt->fetch();

    return $delivery ?: null;
}

function carceris_log_delivery_dashboard_status(array $summary): string
{
    if (($summary['latest_status'] ?? null) === 'sent') {
        return 'Sent';
    }

    if (($summary['latest_status'] ?? null) === 'failed') {
        return 'Failed';
    }

    if (($summary['latest_status'] ?? null) === 'skipped') {
        return 'Skipped';
    }

    return 'Not sent';
}

function carceris_log_delivery_dashboard_status_class(array $summary): string
{
    $status = strtolower(carceris_log_delivery_dashboard_status($summary));

    return str_replace(' ', '-', $status);
}


function carceris_log_delivery_config_status(?array $settings = null): array
{
    $settings = $settings ?? carceris_report_delivery_settings();

    $enabled = (string) ($settings['report_delivery_enabled'] ?? '0') === '1';
    $transport = (string) ($settings['report_mail_transport'] ?? 'manual_only');
    $hasTransport = $transport !== 'manual_only';
    $transportAvailable = $hasTransport && function_exists('carceris_mail_transport_available')
        ? carceris_mail_transport_available($transport, $settings)
        : $hasTransport;
    $hasRecipients = trim((string) ($settings['report_recipients_to'] ?? '')) !== ''
        || trim((string) ($settings['report_recipients_cc'] ?? '')) !== ''
        || trim((string) ($settings['report_recipients_bcc'] ?? '')) !== '';

    $issues = [];

    if (!$enabled) {
        $issues[] = 'scheduled delivery is not enabled';
    }

    if (!$hasTransport) {
        $issues[] = 'mail transport is set to Manual Export Only';
    }

    if ($hasTransport && !$transportAvailable) {
        $issues[] = function_exists('carceris_mail_transport_unavailable_message')
            ? carceris_mail_transport_unavailable_message($transport, $settings)
            : 'selected mail transport is not available';
    }

    if (!$hasRecipients) {
        $issues[] = 'no recipients are configured';
    }

    return [
        'available' => $enabled && $hasTransport && $transportAvailable && $hasRecipients,
        'enabled' => $enabled,
        'has_transport' => $hasTransport,
        'transport_available' => $transportAvailable,
        'has_recipients' => $hasRecipients,
        'transport' => $transport,
        'issues' => $issues,
    ];
}

function carceris_log_delivery_available(?array $settings = null): bool
{
    $status = carceris_log_delivery_config_status($settings);

    return (bool) $status['available'];
}

function carceris_log_delivery_unavailable_message(array $user, ?array $settings = null): string
{
    if (user_can($user, 'manage_settings')) {
        return 'Log delivery is not configured. Configure Log Delivery before sending completed daily logs.';
    }

    return 'Log delivery has not been configured. Contact your system administrator.';
}


function carceris_default_daily_log_filename_pattern(): string
{
    return 'carceris-daily-log-{date}';
}

function carceris_normalize_daily_log_filename_pattern(string $pattern): string
{
    $pattern = strtolower(trim($pattern));
    $pattern = preg_replace('/\.(pdf|txt|text|html)$/i', '', $pattern) ?? $pattern;
    $pattern = str_replace([' ', '.', '/', '\\'], '-', $pattern);
    $pattern = preg_replace('/[^a-z0-9_\-\{\}]+/', '-', $pattern) ?? $pattern;
    $pattern = preg_replace('/-+/', '-', $pattern) ?? $pattern;
    $pattern = trim($pattern, '-_');

    if ($pattern === '') {
        return carceris_default_daily_log_filename_pattern();
    }

    if (!str_contains($pattern, '{date}')) {
        $pattern .= '-{date}';
    }

    if (function_exists('mb_strlen') && mb_strlen($pattern) > 120) {
        $pattern = mb_substr($pattern, 0, 120);
    } elseif (!function_exists('mb_strlen') && strlen($pattern) > 120) {
        $pattern = substr($pattern, 0, 120);
    }

    $pattern = trim($pattern, '-_');

    if ($pattern === '' || !str_contains($pattern, '{date}')) {
        return carceris_default_daily_log_filename_pattern();
    }

    return $pattern;
}

function carceris_daily_log_filename_pattern(): string
{
    return carceris_normalize_daily_log_filename_pattern(
        (string) (setting('daily_log_filename_pattern', carceris_default_daily_log_filename_pattern()) ?? carceris_default_daily_log_filename_pattern())
    );
}

function carceris_daily_log_filename_base(array $logDay): string
{
    $pattern = carceris_daily_log_filename_pattern();
    $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($logDay['operational_date'] ?? ''))
        ? (string) $logDay['operational_date']
        : date('Y-m-d');

    $base = str_replace('{date}', $date, $pattern);
    $base = preg_replace('/[^a-z0-9_\-]+/', '-', strtolower($base)) ?? $base;
    $base = preg_replace('/-+/', '-', $base) ?? $base;
    $base = trim($base, '-_');

    return $base !== '' ? $base : 'carceris-daily-log-' . $date;
}


function carceris_production_readiness_checks(array $securityChecks, bool $httpsEnabled, array $databaseStatus, array $migrationSummary): array
{
    $environmentMode = carceris_environment_mode();

    $checks = [
        [
            'label' => 'Environment mode is Production',
            'passed' => $environmentMode === 'production',
            'message' => $environmentMode === 'production'
                ? 'Production mode is active.'
                : 'Set environment mode to Production before real operational use.',
        ],
        [
            'label' => 'HTTPS detected',
            'passed' => $httpsEnabled,
            'message' => $httpsEnabled
                ? 'Current request is using HTTPS.'
                : 'Use HTTPS or equivalent internal transport protection before real operational use.',
        ],
        [
            'label' => 'Force HTTPS enabled',
            'passed' => (bool) ($securityChecks['force_https_enabled'] ?? false),
            'message' => ($securityChecks['force_https_enabled'] ?? false)
                ? 'Force HTTPS is enabled.'
                : 'Enable Force HTTPS for production deployments using HTTPS.',
        ],
        [
            'label' => 'Installer locked',
            'passed' => (bool) ($securityChecks['installer_locked'] ?? false),
            'message' => ($securityChecks['installer_locked'] ?? false)
                ? 'Installer lock exists.'
                : 'Installer lock is missing.',
        ],
        [
            'label' => 'Debug disabled',
            'passed' => (bool) ($securityChecks['debug_disabled'] ?? false),
            'message' => ($securityChecks['debug_disabled'] ?? false)
                ? 'Debug output is disabled.'
                : 'Disable debug output before production use.',
        ],
        [
            'label' => 'Database connection',
            'passed' => (bool) ($databaseStatus['ok'] ?? false),
            'message' => (string) ($databaseStatus['message'] ?? 'Database status unavailable.'),
        ],
        [
            'label' => 'Migrations current',
            'passed' => count($migrationSummary['pending'] ?? []) === 0,
            'message' => count($migrationSummary['pending'] ?? []) === 0
                ? 'No pending migration files detected.'
                : 'Pending migrations detected. Run Admin -> Upgrade.',
        ],
        [
            'label' => 'Internal folder protection markers',
            'passed' => (bool) ($securityChecks['app_htaccess_exists'] ?? false)
                && (bool) ($securityChecks['app_config_htaccess_exists'] ?? false)
                && (bool) ($securityChecks['database_htaccess_exists'] ?? false)
                && (bool) ($securityChecks['storage_htaccess_exists'] ?? false)
                && (bool) ($securityChecks['tools_htaccess_exists'] ?? false),
            'message' => 'Apache .htaccess protection markers should exist. Nginx/IIS deployments still need server-level rules.',
        ],
    ];

    $failed = array_values(array_filter($checks, static fn (array $check): bool => !$check['passed']));

    return [
        'checks' => $checks,
        'failed' => $failed,
        'ready' => count($failed) === 0,
    ];
}
