<?php

declare(strict_types=1);


function carceris_upgrade_failure_marker_path(): string
{
    return carceris_project_root() . '/storage/upgrade-failed.json';
}

function carceris_record_upgrade_failure(array $payload): void
{
    $payload['failed_at'] = date('c');

    file_put_contents(
        carceris_upgrade_failure_marker_path(),
        json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );
}

function carceris_clear_upgrade_failure(): void
{
    $path = carceris_upgrade_failure_marker_path();

    if (is_file($path)) {
        unlink($path);
    }
}

function carceris_upgrade_failure_status(): array
{
    $path = carceris_upgrade_failure_marker_path();

    if (!is_file($path)) {
        return [
            'failed' => false,
        ];
    }

    $raw = file_get_contents($path);
    $payload = is_string($raw) ? json_decode($raw, true) : null;

    if (!is_array($payload)) {
        $payload = [];
    }

    $payload['failed'] = true;

    return $payload;
}


function carceris_upgrade_storage_dir(): string
{
    return carceris_project_root() . '/storage/upgrades';
}

function carceris_maintenance_lock_path(): string
{
    return carceris_project_root() . '/storage/maintenance.lock';
}

function carceris_is_maintenance_mode(): bool
{
    return is_file(carceris_maintenance_lock_path());
}

function carceris_enable_maintenance_mode(string $reason = 'Upgrade in progress.'): void
{
    $payload = [
        'started_at' => date('c'),
        'reason' => $reason,
    ];

    file_put_contents(carceris_maintenance_lock_path(), json_encode($payload, JSON_PRETTY_PRINT), LOCK_EX);
}

function carceris_disable_maintenance_mode(): void
{
    $path = carceris_maintenance_lock_path();

    if (is_file($path)) {
        unlink($path);
    }
}

function carceris_render_maintenance_and_exit(): never
{
    if (!headers_sent()) {
        http_response_code(503);
        header('Content-Type: text/html; charset=utf-8');
        header('Retry-After: 120');
    }

    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>Carceris Maintenance</title>';
    echo '<style>body{font-family:Arial,Helvetica,sans-serif;background:#f4f5f7;color:#111827;margin:0;display:grid;min-height:100vh;place-items:center}main{background:#fff;border:1px solid #d1d5db;border-radius:14px;box-shadow:0 12px 28px rgba(15,23,42,.08);max-width:560px;padding:28px}h1{margin-top:0}</style>';
    echo '</head><body><main>';
    echo '<h1>Carceris is temporarily unavailable.</h1>';
    echo '<p>The system is in maintenance mode. An upgrade may be in progress.</p>';
    echo '<p>Try again shortly or contact the system administrator.</p>';
    echo '</main></body></html>';
    exit;
}

function carceris_current_version(): string
{
    $versionPath = carceris_project_root() . '/VERSION';

    if (is_file($versionPath)) {
        $version = trim((string) file_get_contents($versionPath));

        if (preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            return $version;
        }
    }

    return (string) app_config('app', 'version', '0.0.0');
}

function carceris_version_compare(string $a, string $b): int
{
    return version_compare($a, $b);
}

function carceris_schema_migrations_table_sql(): string
{
    return 'CREATE TABLE IF NOT EXISTS schema_migrations (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        version VARCHAR(40) NOT NULL,
        migration_file VARCHAR(255) NOT NULL UNIQUE,
        checksum CHAR(64) NOT NULL,
        executed_by INT UNSIGNED NULL,
        executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        status ENUM("applied", "baseline") NOT NULL DEFAULT "applied",
        INDEX idx_schema_migrations_version (version),
        CONSTRAINT fk_schema_migrations_user
            FOREIGN KEY (executed_by) REFERENCES users(id)
            ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
}

function carceris_ensure_schema_migrations_table(): void
{
    db()->exec(carceris_schema_migrations_table_sql());
}

function carceris_migration_version_from_file(string $filename): string
{
    if (preg_match('/^(\d+\.\d+\.\d+)-/', basename($filename), $matches)) {
        return $matches[1];
    }

    return '0.0.0';
}

function carceris_migration_checksum(string $path): string
{
    return hash_file('sha256', $path) ?: hash('sha256', basename($path));
}

function carceris_applied_migrations(): array
{
    carceris_ensure_schema_migrations_table();

    $stmt = db()->query('SELECT migration_file FROM schema_migrations');
    $rows = $stmt->fetchAll();

    $applied = [];

    foreach ($rows as $row) {
        $applied[$row['migration_file']] = true;
    }

    return $applied;
}

function carceris_migration_files(): array
{
    $migrationDir = carceris_project_root() . '/database/migrations';
    $files = glob($migrationDir . '/*.sql') ?: [];

    usort($files, static fn ($a, $b) => strnatcasecmp(basename($a), basename($b)));

    return $files;
}

function carceris_baseline_existing_migrations(?int $userId = null): void
{
    carceris_ensure_schema_migrations_table();

    $count = (int) db()->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn();

    if ($count > 0) {
        return;
    }

    $currentVersion = carceris_current_version();

    foreach (carceris_migration_files() as $path) {
        $file = basename($path);
        $version = carceris_migration_version_from_file($file);

        if (version_compare($version, $currentVersion, '<=')) {
            $stmt = db()->prepare(
                'INSERT INTO schema_migrations
                    (version, migration_file, checksum, executed_by, status)
                 VALUES
                    (:version, :migration_file, :checksum, :executed_by, "baseline")'
            );
            $stmt->execute([
                'version' => $version,
                'migration_file' => $file,
                'checksum' => carceris_migration_checksum($path),
                'executed_by' => $userId,
            ]);
        }
    }
}


function carceris_schema_table_exists(string $tableName): bool
{
    $stmt = db()->prepare(
        'SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = :table_name'
    );
    $stmt->execute(['table_name' => $tableName]);

    return (int) $stmt->fetchColumn() > 0;
}

function carceris_schema_column_exists(string $tableName, string $columnName): bool
{
    $stmt = db()->prepare(
        'SELECT COUNT(*)
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = :table_name
           AND column_name = :column_name'
    );
    $stmt->execute([
        'table_name' => $tableName,
        'column_name' => $columnName,
    ]);

    return (int) $stmt->fetchColumn() > 0;
}

function carceris_schema_index_exists(string $tableName, string $indexName): bool
{
    $stmt = db()->prepare(
        'SELECT COUNT(*)
         FROM information_schema.statistics
         WHERE table_schema = DATABASE()
           AND table_name = :table_name
           AND index_name = :index_name'
    );
    $stmt->execute([
        'table_name' => $tableName,
        'index_name' => $indexName,
    ]);

    return (int) $stmt->fetchColumn() > 0;
}

function carceris_schema_add_column_if_missing(string $tableName, string $columnName, string $definition): void
{
    if (!carceris_schema_table_exists($tableName) || carceris_schema_column_exists($tableName, $columnName)) {
        return;
    }

    db()->exec('ALTER TABLE `' . $tableName . '` ADD COLUMN ' . $definition);
}

function carceris_schema_add_index_if_missing(string $tableName, string $indexName, string $definition): void
{
    if (!carceris_schema_table_exists($tableName) || carceris_schema_index_exists($tableName, $indexName)) {
        return;
    }

    db()->exec('ALTER TABLE `' . $tableName . '` ADD INDEX ' . $definition);
}

function carceris_repair_correction_void_schema(): void
{
    if (!carceris_schema_table_exists('log_entries')) {
        return;
    }

    carceris_schema_add_column_if_missing('log_entries', 'status', "status ENUM('active', 'corrected', 'voided') NOT NULL DEFAULT 'active' AFTER late_entry_reason");
    carceris_schema_add_column_if_missing('log_entries', 'parent_entry_id', 'parent_entry_id INT UNSIGNED NULL AFTER status');
    carceris_schema_add_column_if_missing('log_entries', 'correction_reason', 'correction_reason TEXT NULL AFTER parent_entry_id');
    carceris_schema_add_column_if_missing('log_entries', 'corrected_by', 'corrected_by INT UNSIGNED NULL AFTER correction_reason');
    carceris_schema_add_column_if_missing('log_entries', 'corrected_at', 'corrected_at DATETIME NULL AFTER corrected_by');
    carceris_schema_add_column_if_missing('log_entries', 'is_voided', 'is_voided TINYINT(1) NOT NULL DEFAULT 0 AFTER updated_at');
    carceris_schema_add_column_if_missing('log_entries', 'void_reason', 'void_reason TEXT NULL AFTER is_voided');
    carceris_schema_add_column_if_missing('log_entries', 'voided_by', 'voided_by INT UNSIGNED NULL AFTER void_reason');
    carceris_schema_add_column_if_missing('log_entries', 'voided_at', 'voided_at DATETIME NULL AFTER voided_by');

    carceris_schema_add_index_if_missing('log_entries', 'idx_log_entries_status', 'idx_log_entries_status (status)');
    carceris_schema_add_index_if_missing('log_entries', 'idx_log_entries_parent', 'idx_log_entries_parent (parent_entry_id)');

    if (carceris_schema_column_exists('log_entries', 'status') && carceris_schema_column_exists('log_entries', 'is_voided')) {
        db()->exec("UPDATE log_entries SET status = 'voided' WHERE is_voided = 1 AND status = 'active'");
    }

    db()->exec(
        "CREATE TABLE IF NOT EXISTS log_entry_revisions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            log_entry_id INT UNSIGNED NOT NULL,
            old_event_time DATETIME NULL,
            new_event_time DATETIME NULL,
            old_category VARCHAR(80) NULL,
            new_category VARCHAR(80) NULL,
            old_location VARCHAR(120) NULL,
            new_location VARCHAR(120) NULL,
            old_inmate_name VARCHAR(160) NULL,
            new_inmate_name VARCHAR(160) NULL,
            old_entry_text TEXT NULL,
            new_entry_text TEXT NULL,
            correction_reason TEXT NOT NULL,
            corrected_by INT UNSIGNED NULL,
            corrected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_log_entry_revisions_entry (log_entry_id),
            INDEX idx_log_entry_revisions_corrected_by (corrected_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    db()->exec(
        "CREATE TABLE IF NOT EXISTS log_entry_actions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            log_entry_id INT UNSIGNED NOT NULL,
            replacement_entry_id INT UNSIGNED NULL,
            action_type ENUM('correction', 'void') NOT NULL,
            reason TEXT NOT NULL,
            entry_snapshot LONGTEXT NOT NULL,
            performed_by INT UNSIGNED NOT NULL,
            performed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_log_entry_actions_entry (log_entry_id),
            INDEX idx_log_entry_actions_replacement (replacement_entry_id),
            INDEX idx_log_entry_actions_type_time (action_type, performed_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}


function carceris_split_sql(string $sql): array
{
    $statements = [];
    $buffer = '';
    $length = strlen($sql);
    $inString = false;
    $stringChar = '';

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = $sql[$i + 1] ?? '';

        if (!$inString && $char === '-' && $next === '-') {
            while ($i < $length && $sql[$i] !== "\n") {
                $i++;
            }
            continue;
        }

        if (!$inString && $char === '#') {
            while ($i < $length && $sql[$i] !== "\n") {
                $i++;
            }
            continue;
        }

        if (!$inString && $char === '/' && $next === '*') {
            $i += 2;

            while ($i < $length && !($sql[$i] === '*' && ($sql[$i + 1] ?? '') === '/')) {
                $i++;
            }

            $i++;
            continue;
        }

        if (($char === "'" || $char === '"') && ($i === 0 || $sql[$i - 1] !== '\\')) {
            if (!$inString) {
                $inString = true;
                $stringChar = $char;
            } elseif ($stringChar === $char) {
                $inString = false;
                $stringChar = '';
            }
        }

        if (!$inString && $char === ';') {
            $statement = trim($buffer);

            if ($statement !== '') {
                $statements[] = $statement;
            }

            $buffer = '';
            continue;
        }

        $buffer .= $char;
    }

    $statement = trim($buffer);

    if ($statement !== '') {
        $statements[] = $statement;
    }

    return $statements;
}

function carceris_execute_migration_statement(string $statement): void
{
    $trimmed = ltrim($statement);

    if ($trimmed === '') {
        return;
    }

    if (preg_match('/^(SELECT|SHOW|DESCRIBE|DESC|EXPLAIN)\b/i', $trimmed)) {
        $stmt = db()->query($statement);

        if ($stmt instanceof PDOStatement) {
            $stmt->fetchAll();
            $stmt->closeCursor();
        }

        return;
    }

    db()->exec($statement);
}

function carceris_run_sql_file(string $path): void
{
    $sql = file_get_contents($path);

    if ($sql === false) {
        throw new RuntimeException('Could not read migration file: ' . basename($path));
    }

    foreach (carceris_split_sql($sql) as $statement) {
        carceris_execute_migration_statement($statement);
    }
}


function carceris_record_migration_applied(string $path, ?int $userId = null): void
{
    $file = basename($path);
    $version = carceris_migration_version_from_file($file);

    $stmt = db()->prepare(
        'INSERT INTO schema_migrations
            (version, migration_file, checksum, executed_by, status)
         VALUES
            (:version, :migration_file, :checksum, :executed_by, "applied")
         ON DUPLICATE KEY UPDATE
            checksum = VALUES(checksum),
            executed_by = VALUES(executed_by),
            executed_at = CURRENT_TIMESTAMP,
            status = "applied"'
    );
    $stmt->execute([
        'version' => $version,
        'migration_file' => $file,
        'checksum' => carceris_migration_checksum($path),
        'executed_by' => $userId,
    ]);
}


function carceris_run_pending_migrations(?int $userId = null): array
{
    carceris_ensure_schema_migrations_table();

    $applied = carceris_applied_migrations();
    $ran = [];

    foreach (carceris_migration_files() as $path) {
        $file = basename($path);

        if (isset($applied[$file])) {
            continue;
        }

        try {
            if (in_array($file, [
                '0.2.0-correction-and-void-system.sql',
                '0.2.6-installation-schema-cleanup.sql',
            ], true)) {
                carceris_repair_correction_void_schema();
            }

            carceris_run_sql_file($path);

            if ($file === '0.2.6-installation-schema-cleanup.sql') {
                carceris_repair_correction_void_schema();
            }

            carceris_record_migration_applied($path, $userId);
            $ran[] = $file;
        } catch (Throwable $exception) {
            if ($file === '0.2.0-correction-and-void-system.sql') {
                try {
                    carceris_repair_correction_void_schema();
                    carceris_record_migration_applied($path, $userId);
                    $ran[] = $file . ' (repaired)';
                    continue;
                } catch (Throwable $repairException) {
                    throw new RuntimeException(
                        'Migration failed for ' . $file . ': ' . $exception->getMessage()
                        . ' Repair also failed: ' . $repairException->getMessage(),
                        0,
                        $repairException
                    );
                }
            }

            throw new RuntimeException('Migration failed for ' . $file . ': ' . $exception->getMessage(), 0, $exception);
        }
    }

    return $ran;
}

function carceris_migration_summary(): array
{
    carceris_ensure_schema_migrations_table();

    $files = array_map('basename', carceris_migration_files());
    $applied = carceris_applied_migrations();

    $pending = [];

    foreach ($files as $file) {
        if (!isset($applied[$file])) {
            $pending[] = $file;
        }
    }

    $stmt = db()->query(
        'SELECT version, migration_file, executed_at, status
         FROM schema_migrations
         ORDER BY executed_at DESC, id DESC
         LIMIT 10'
    );

    return [
        'known_files' => $files,
        'pending' => $pending,
        'recent' => $stmt->fetchAll(),
    ];
}


function carceris_required_schema_definition(): array
{
    return [
        'users' => ['id', 'username', 'password_hash', 'display_name', 'role', 'is_active', 'last_login_at', 'created_at', 'updated_at'],
        'log_days' => ['id', 'log_label', 'operational_date', 'start_time', 'end_time', 'status', 'opened_by', 'closed_by', 'opened_at', 'closed_at', 'created_at'],
        'log_entries' => ['id', 'log_day_id', 'event_time', 'category', 'location', 'inmate_name', 'entry_text', 'priority', 'is_late_entry', 'late_entry_reason', 'status', 'parent_entry_id', 'correction_reason', 'corrected_by', 'corrected_at', 'created_by', 'created_at', 'updated_at', 'is_voided', 'void_reason', 'voided_by', 'voided_at'],
        'log_entry_revisions' => ['id', 'log_entry_id', 'old_event_time', 'new_event_time', 'old_category', 'new_category', 'old_location', 'new_location', 'old_inmate_name', 'new_inmate_name', 'old_entry_text', 'new_entry_text', 'correction_reason', 'corrected_by', 'corrected_at'],
        'settings' => ['id', 'setting_key', 'setting_value', 'created_at', 'updated_at'],
        'categories' => ['id', 'name', 'sort_order', 'is_active', 'created_at'],
        'login_attempts' => ['id', 'username', 'subject_hash', 'user_id', 'ip_address', 'user_agent', 'was_successful', 'failure_reason', 'attempted_at'],
        'audit_events' => ['id', 'event_type', 'user_id', 'username', 'ip_address', 'user_agent', 'details', 'related_type', 'related_id', 'created_at'],
        'schema_migrations' => ['id', 'version', 'migration_file', 'checksum', 'executed_by', 'executed_at', 'status'],
        'report_deliveries' => ['id', 'log_day_id', 'delivery_type', 'transport', 'body_format', 'attachment_format', 'recipient_to', 'recipient_cc', 'recipient_bcc', 'subject', 'status', 'error_message', 'sent_at', 'triggered_by', 'created_at'],
        'log_entry_actions' => ['id', 'log_entry_id', 'replacement_entry_id', 'action_type', 'reason', 'entry_snapshot', 'performed_by', 'performed_at'],
        'report_email_deliveries' => ['id', 'legacy_report_delivery_id', 'log_day_id', 'delivery_type', 'transport', 'body_format', 'attachment_format', 'recipient_to', 'recipient_cc', 'recipient_bcc', 'subject', 'status', 'error_message', 'sent_at', 'triggered_by', 'created_at'],
        'report_test_emails' => ['id', 'legacy_report_delivery_id', 'transport', 'body_format', 'recipient_to', 'recipient_cc', 'recipient_bcc', 'subject', 'status', 'error_message', 'sent_at', 'triggered_by', 'created_at'],
        'report_downloads' => ['id', 'legacy_report_delivery_id', 'log_day_id', 'body_format', 'attachment_format', 'subject', 'status', 'triggered_by', 'created_at'],
    ];
}

function carceris_schema_health_check(): array
{
    $missingTables = [];
    $missingColumns = [];

    foreach (carceris_required_schema_definition() as $table => $columns) {
        if (!carceris_schema_table_exists($table)) {
            $missingTables[] = $table;
            continue;
        }

        foreach ($columns as $column) {
            if (!carceris_schema_column_exists($table, $column)) {
                $missingColumns[] = $table . '.' . $column;
            }
        }
    }

    return [
        'ok' => count($missingTables) === 0 && count($missingColumns) === 0,
        'missing_tables' => $missingTables,
        'missing_columns' => $missingColumns,
    ];
}

function carceris_validate_release_manifest(ZipArchive $zip, string $root, string $version): array
{
    $manifestPath = carceris_package_path($root, 'RELEASE_MANIFEST.json');
    $raw = $zip->getFromName($manifestPath);

    if ($raw === false) {
        throw new RuntimeException('The uploaded ZIP is missing required file: RELEASE_MANIFEST.json');
    }

    $manifest = json_decode((string) $raw, true);

    if (!is_array($manifest)) {
        throw new RuntimeException('RELEASE_MANIFEST.json is not valid JSON.');
    }

    if (($manifest['version'] ?? '') !== $version) {
        throw new RuntimeException('Release manifest version does not match VERSION.');
    }

    if (($manifest['license'] ?? '') !== 'AGPL-3.0-only') {
        throw new RuntimeException('Release manifest license must be AGPL-3.0-only.');
    }

    $files = $manifest['files'] ?? null;

    if (!is_array($files) || !$files) {
        throw new RuntimeException('Release manifest does not contain a file list.');
    }

    $allowedFiles = [];

    foreach ($files as $file) {
        if (!is_array($file)) {
            throw new RuntimeException('Release manifest contains an invalid file entry.');
        }

        $path = str_replace('\\', '/', (string) ($file['path'] ?? ''));
        $hash = (string) ($file['sha256'] ?? '');

        if ($path === '' || str_starts_with($path, '/') || preg_match('#(^|/)\.\.(/|$)#', $path)) {
            throw new RuntimeException('Release manifest contains an unsafe or empty file path.');
        }

        if ($path === 'RELEASE_MANIFEST.json') {
            continue;
        }

        if (!preg_match('/^[a-f0-9]{64}$/', $hash)) {
            throw new RuntimeException('Release manifest contains an invalid SHA-256 hash for: ' . $path);
        }

        if (isset($allowedFiles[$path])) {
            throw new RuntimeException('Release manifest contains a duplicate file entry: ' . $path);
        }

        $content = $zip->getFromName(carceris_package_path($root, $path));

        if ($content === false) {
            throw new RuntimeException('Release manifest lists missing file: ' . $path);
        }

        if (hash('sha256', $content) !== $hash) {
            throw new RuntimeException('Release manifest hash mismatch for: ' . $path);
        }

        $allowedFiles[$path] = true;
    }

    $manifestSelfPath = carceris_package_path($root, 'RELEASE_MANIFEST.json');

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entry = str_replace('\\', '/', $zip->getNameIndex($i));

        if ($entry === '' || str_ends_with($entry, '/')) {
            continue;
        }

        if ($entry === $manifestSelfPath) {
            continue;
        }

        if ($root !== '') {
            $rootPrefix = rtrim($root, '/') . '/';

            if (!str_starts_with($entry, $rootPrefix)) {
                throw new RuntimeException('The uploaded ZIP contains a file outside the package root: ' . $entry);
            }

            $relative = substr($entry, strlen($rootPrefix));
        } else {
            $relative = $entry;
        }

        if (!isset($allowedFiles[$relative])) {
            throw new RuntimeException('The uploaded ZIP contains a file not listed in RELEASE_MANIFEST.json: ' . $relative);
        }
    }

    return $manifest;
}

function carceris_zip_available(): bool
{
    return class_exists('ZipArchive');
}

function carceris_find_package_root(ZipArchive $zip): array
{
    $versionPaths = [];

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = str_replace('\\', '/', $zip->getNameIndex($i));

        if (str_ends_with($name, '/VERSION') || $name === 'VERSION') {
            $versionPaths[] = $name;
        }
    }

    if (!$versionPaths) {
        throw new RuntimeException('The uploaded ZIP does not contain a VERSION file.');
    }

    sort($versionPaths);
    $versionPath = $versionPaths[0];
    $root = '';

    if ($versionPath !== 'VERSION') {
        $root = substr($versionPath, 0, -strlen('/VERSION'));
    }

    return [
        'root' => $root,
        'version_path' => $versionPath,
    ];
}

function carceris_package_path(string $root, string $relative): string
{
    $relative = ltrim($relative, '/');

    return $root === '' ? $relative : $root . '/' . $relative;
}

function carceris_validate_zip_names(ZipArchive $zip): void
{
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = str_replace('\\', '/', $zip->getNameIndex($i));

        if ($name === '' || str_contains($name, "\0")) {
            throw new RuntimeException('The uploaded ZIP contains an invalid file path.');
        }

        if (str_starts_with($name, '/') || preg_match('#(^|/)\.\.(/|$)#', $name)) {
            throw new RuntimeException('The uploaded ZIP contains unsafe paths.');
        }
    }
}

function carceris_validate_upgrade_zip(string $zipPath): array
{
    if (!carceris_zip_available()) {
        throw new RuntimeException('PHP ZipArchive is not available on this server.');
    }

    $zip = new ZipArchive();
    $opened = $zip->open($zipPath);

    if ($opened !== true) {
        throw new RuntimeException('Could not open uploaded ZIP package.');
    }

    try {
        carceris_validate_zip_names($zip);

        $package = carceris_find_package_root($zip);
        $root = $package['root'];

        $version = trim((string) $zip->getFromName($package['version_path']));

        if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            throw new RuntimeException('The uploaded package VERSION must use MAJOR.MINOR.PATCH format.');
        }

        $requiredFiles = [
            'VERSION',
            'README.md',
            'RELEASE_NOTES.md',
            'CHANGELOG.md',
            'RELEASE_MANIFEST.json',
            'app/bootstrap.php',
            'app/config/config.php',
            'public/index.php',
            'database/schema.sql',
        ];

        foreach ($requiredFiles as $requiredFile) {
            if ($zip->locateName(carceris_package_path($root, $requiredFile)) === false) {
                throw new RuntimeException('The uploaded ZIP is missing required file: ' . $requiredFile);
            }
        }

        $requiredDirectories = [
            'app/',
            'public/',
            'database/',
        ];

        foreach ($requiredDirectories as $requiredDirectory) {
            $found = false;
            $prefix = carceris_package_path($root, $requiredDirectory);

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = str_replace('\\', '/', $zip->getNameIndex($i));

                if (str_starts_with($name, $prefix)) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                throw new RuntimeException('The uploaded ZIP is missing required directory: ' . $requiredDirectory);
            }
        }

        $manifest = carceris_validate_release_manifest($zip, $root, $version);

        $currentVersion = carceris_current_version();

        if (version_compare($version, $currentVersion, '<=')) {
            throw new RuntimeException('Uploaded version ' . $version . ' is not newer than current version ' . $currentVersion . '.');
        }

        return [
            'version' => $version,
            'root' => $root,
            'current_version' => $currentVersion,
            'manifest' => $manifest,
        ];
    } finally {
        $zip->close();
    }
}

function carceris_extract_upgrade_zip(string $zipPath, string $target): void
{
    if (is_dir($target)) {
        carceris_delete_directory($target);
    }

    mkdir($target, 0755, true);

    $zip = new ZipArchive();
    $opened = $zip->open($zipPath);

    if ($opened !== true) {
        throw new RuntimeException('Could not open uploaded ZIP package.');
    }

    try {
        carceris_validate_zip_names($zip);

        if (!$zip->extractTo($target)) {
            throw new RuntimeException('Could not extract uploaded ZIP package.');
        }
    } finally {
        $zip->close();
    }
}

function carceris_delete_directory(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }

    rmdir($dir);
}

function carceris_should_skip_upgrade_copy(string $relative): bool
{
    $relative = str_replace('\\', '/', trim($relative, '/'));

    if ($relative === '') {
        return false;
    }

    $skipExact = [
        'app/config/config.local.php',
        'storage/installed.lock',
        'storage/maintenance.lock',
    ];

    if (in_array($relative, $skipExact, true)) {
        return true;
    }

    $skipPrefixes = [
        'storage/',
    ];

    foreach ($skipPrefixes as $prefix) {
        if (str_starts_with($relative, $prefix)) {
            return true;
        }
    }

    return false;
}

function carceris_copy_upgrade_files(string $sourceRoot, string $destinationRoot): void
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceRoot, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $sourcePath = $item->getPathname();
        $relative = str_replace('\\', '/', substr($sourcePath, strlen($sourceRoot) + 1));

        if (carceris_should_skip_upgrade_copy($relative)) {
            continue;
        }

        $destinationPath = $destinationRoot . '/' . $relative;

        if ($item->isDir()) {
            if (!is_dir($destinationPath)) {
                mkdir($destinationPath, 0755, true);
            }

            continue;
        }

        $destinationDir = dirname($destinationPath);

        if (!is_dir($destinationDir)) {
            mkdir($destinationDir, 0755, true);
        }

        if (!copy($sourcePath, $destinationPath)) {
            throw new RuntimeException('Could not copy upgrade file: ' . $relative);
        }
    }
}


function carceris_removed_upgrade_paths(string $targetVersion): array
{
    $removed = [];

    if (version_compare($targetVersion, '0.6.13', '>=')) {
        $removed[] = 'database/migrations/0.3.2-remove-maintenance-notes.sql';
    }

    return $removed;
}

function carceris_remove_stale_upgrade_files(string $destinationRoot, string $targetVersion): array
{
    $removed = [];

    foreach (carceris_removed_upgrade_paths($targetVersion) as $relative) {
        $relative = str_replace('\\', '/', trim($relative, '/'));

        if ($relative === '' || str_starts_with($relative, '/') || preg_match('#(^|/)\.\.(/|$)#', $relative)) {
            continue;
        }

        if (carceris_should_skip_upgrade_copy($relative)) {
            continue;
        }

        $path = rtrim($destinationRoot, '/') . '/' . $relative;

        if (is_file($path) || is_link($path)) {
            if (!unlink($path)) {
                throw new RuntimeException('Could not remove stale upgrade file: ' . $relative);
            }

            $removed[] = $relative;
        }
    }

    return $removed;
}

function carceris_cleanup_old_upgrade_dirs(int $keep = 3): void
{
    $dir = carceris_upgrade_storage_dir();

    if (!is_dir($dir)) {
        return;
    }

    $folders = array_filter(glob($dir . '/extract-*') ?: [], 'is_dir');
    rsort($folders);

    foreach (array_slice($folders, $keep) as $folder) {
        carceris_delete_directory($folder);
    }
}

function carceris_max_upgrade_upload_bytes(): int
{
    return 100 * 1024 * 1024;
}

function carceris_perform_upgrade(array $upload, array $adminUser): array
{
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upgrade upload failed. PHP upload error: ' . (string) ($upload['error'] ?? 'unknown'));
    }

    $originalName = (string) ($upload['name'] ?? 'upgrade.zip');

    $uploadSize = (int) ($upload['size'] ?? 0);

    if ($uploadSize <= 0 || $uploadSize > carceris_max_upgrade_upload_bytes()) {
        throw new RuntimeException('Upgrade package is too large or empty. Maximum size is 100 MB.');
    }

    if (!str_ends_with(strtolower($originalName), '.zip')) {
        throw new RuntimeException('Upgrade package must be a ZIP file.');
    }

    $tmpPath = (string) ($upload['tmp_name'] ?? '');

    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        throw new RuntimeException('Uploaded ZIP was not received correctly.');
    }

    $package = carceris_validate_upgrade_zip($tmpPath);

    $upgradeDir = carceris_upgrade_storage_dir();

    if (!is_dir($upgradeDir)) {
        mkdir($upgradeDir, 0755, true);
    }

    $timestamp = date('Ymd-His');
    $extractDir = $upgradeDir . '/extract-' . $timestamp;
    $storedZip = $upgradeDir . '/package-' . $timestamp . '.zip';

    if (!move_uploaded_file($tmpPath, $storedZip)) {
        throw new RuntimeException('Could not store uploaded upgrade package.');
    }

    audit_event(
        'upgrade_started',
        (int) $adminUser['id'],
        $adminUser['username'] ?? null,
        'Started upgrade from ' . $package['current_version'] . ' to ' . $package['version'] . '. Package: ' . $originalName
    );

    carceris_clear_upgrade_failure();
    carceris_enable_maintenance_mode('Upgrade from ' . $package['current_version'] . ' to ' . $package['version'] . ' in progress.');

    try {
        carceris_extract_upgrade_zip($storedZip, $extractDir);

        $sourceRoot = $extractDir;

        if ($package['root'] !== '') {
            $sourceRoot .= '/' . $package['root'];
        }

        if (!is_dir($sourceRoot)) {
            throw new RuntimeException('Extracted upgrade package root was not found.');
        }

        carceris_copy_upgrade_files($sourceRoot, carceris_project_root());
        $removedFiles = carceris_remove_stale_upgrade_files(carceris_project_root(), $package['version']);

        $ranMigrations = carceris_run_pending_migrations((int) $adminUser['id']);
        $schemaHealth = carceris_schema_health_check();

        if (!$schemaHealth['ok']) {
            throw new RuntimeException('Schema health check failed after upgrade. Missing tables: ' . implode(', ', $schemaHealth['missing_tables']) . '. Missing columns: ' . implode(', ', $schemaHealth['missing_columns']) . '.');
        }

        carceris_clear_upgrade_failure();

        audit_event(
            'upgrade_success',
            (int) $adminUser['id'],
            $adminUser['username'] ?? null,
            'Completed upgrade to ' . $package['version'] . '. Migrations run: ' . (count($ranMigrations) ? implode(', ', $ranMigrations) : 'none') . '. Removed stale files: ' . (count($removedFiles) ? implode(', ', $removedFiles) : 'none')
        );

        carceris_cleanup_old_upgrade_dirs(3);

        return [
            'version' => $package['version'],
            'previous_version' => $package['current_version'],
            'migrations' => $ranMigrations,
            'removed_files' => $removedFiles,
        ];
    } catch (Throwable $exception) {
        carceris_record_upgrade_failure([
            'from_version' => $package['current_version'] ?? '',
            'to_version' => $package['version'] ?? '',
            'package' => $originalName,
            'error' => $exception->getMessage(),
            'failed_by' => $adminUser['username'] ?? null,
        ]);

        audit_event(
            'upgrade_failed',
            (int) $adminUser['id'],
            $adminUser['username'] ?? null,
            'Upgrade failed: ' . $exception->getMessage()
        );

        throw $exception;
    } finally {
        carceris_disable_maintenance_mode();
    }
}
