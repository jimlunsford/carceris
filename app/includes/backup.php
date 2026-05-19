<?php

declare(strict_types=1);

function carceris_backup_storage_dir(): string
{
    $dir = carceris_project_root() . '/storage/backups';

    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create backup storage directory.');
    }

    @chmod($dir, 0750);

    return $dir;
}

function carceris_backup_timestamp(): string
{
    return (new DateTimeImmutable('now'))->format('Ymd-His');
}

function carceris_backup_quote_identifier(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function carceris_backup_tables(): array
{
    $tables = [];
    $stmt = db()->query('SHOW FULL TABLES');

    foreach ($stmt->fetchAll(PDO::FETCH_NUM) as $row) {
        if (($row[1] ?? '') === 'BASE TABLE') {
            $tables[] = (string) $row[0];
        }
    }

    sort($tables, SORT_NATURAL | SORT_FLAG_CASE);

    return $tables;
}

function carceris_backup_sql_value(mixed $value): string
{
    if ($value === null) {
        return 'NULL';
    }

    $quoted = db()->quote((string) $value);

    if ($quoted === false) {
        throw new RuntimeException('Could not quote database value during backup.');
    }

    return $quoted;
}

function carceris_backup_database_sql(): string
{
    $version = carceris_current_version();
    $created = (new DateTimeImmutable('now'))->format(DateTimeInterface::ATOM);

    $sql = [];
    $sql[] = '-- Carceris database backup';
    $sql[] = '-- Backup format: carceris-database-sql-v1';
    $sql[] = '-- Version: ' . $version;
    $sql[] = '-- Created: ' . $created;
    $sql[] = 'SET FOREIGN_KEY_CHECKS=0;';
    $sql[] = 'SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";';
    $sql[] = '';

    foreach (carceris_backup_tables() as $table) {
        $quotedTable = carceris_backup_quote_identifier($table);

        $createStmt = db()->query('SHOW CREATE TABLE ' . $quotedTable);
        $createRow = $createStmt->fetch(PDO::FETCH_NUM);

        if (!$createRow || empty($createRow[1])) {
            throw new RuntimeException('Could not read CREATE TABLE for ' . $table . '.');
        }

        $sql[] = '-- Table: ' . $table;
        $sql[] = 'DROP TABLE IF EXISTS ' . $quotedTable . ';';
        $sql[] = $createRow[1] . ';';

        $rows = db()->query('SELECT * FROM ' . $quotedTable);

        while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
            $columns = array_map('carceris_backup_quote_identifier', array_keys($row));
            $values = array_map('carceris_backup_sql_value', array_values($row));

            $sql[] = 'INSERT INTO ' . $quotedTable
                . ' (' . implode(', ', $columns) . ') VALUES ('
                . implode(', ', $values) . ');';
        }

        $sql[] = '';
    }

    $sql[] = 'SET FOREIGN_KEY_CHECKS=1;';
    $sql[] = '';

    return implode("\n", $sql);
}

function carceris_backup_write_database_sql_file(?string $prefix = null): string
{
    $prefix = $prefix ?: 'carceris-database-backup';
    $path = carceris_backup_storage_dir() . '/' . $prefix . '-' . carceris_backup_timestamp() . '.sql';

    if (file_put_contents($path, carceris_backup_database_sql(), LOCK_EX) === false) {
        throw new RuntimeException('Could not write database backup file.');
    }

    @chmod($path, 0640);

    return $path;
}

function carceris_backup_zip_add_directory(ZipArchive $zip, string $sourceDir, string $zipPrefix, array $excludeNames = []): void
{
    if (!is_dir($sourceDir)) {
        return;
    }

    $sourceDir = rtrim($sourceDir, DIRECTORY_SEPARATOR);
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $path = $item->getPathname();
        $relative = str_replace('\\', '/', substr($path, strlen($sourceDir) + 1));
        $topName = explode('/', $relative)[0] ?? $relative;

        if (in_array($topName, $excludeNames, true) || in_array($item->getFilename(), $excludeNames, true)) {
            continue;
        }

        $zipName = trim($zipPrefix . '/' . $relative, '/');

        if ($item->isDir()) {
            $zip->addEmptyDir($zipName);
        } elseif ($item->isFile()) {
            $zip->addFile($path, $zipName);
        }
    }
}

function carceris_create_backup_bundle(array $user): array
{
    if (!class_exists(ZipArchive::class)) {
        throw new RuntimeException('PHP ZipArchive is required to create backup bundles.');
    }

    $backupDir = carceris_backup_storage_dir();
    $timestamp = carceris_backup_timestamp();
    $filename = 'carceris-backup-' . $timestamp . '.zip';
    $zipPath = $backupDir . '/' . $filename;
    $sqlPath = carceris_backup_write_database_sql_file('database-for-bundle');

    $manifest = [
        'app' => 'Carceris',
        'backup_format' => 'carceris-backup-v1',
        'app_version' => carceris_current_version(),
        'created_at' => (new DateTimeImmutable('now'))->format(DateTimeInterface::ATOM),
        'created_by_user_id' => (int) ($user['id'] ?? 0),
        'created_by_username' => (string) ($user['username'] ?? ''),
        'contents' => [
            'database_sql' => true,
            'config_local_php' => is_file(carceris_project_root() . '/app/config/config.local.php'),
            'storage_files' => is_dir(carceris_project_root() . '/storage'),
        ],
        'restore_note' => 'The in-app restore imports database.sql only. Config and storage files are included for manual recovery.',
    ];

    $zip = new ZipArchive();

    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        @unlink($sqlPath);
        throw new RuntimeException('Could not create backup ZIP.');
    }

    $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $zip->addFile($sqlPath, 'database.sql');

    $configLocalPath = carceris_project_root() . '/app/config/config.local.php';

    if (is_file($configLocalPath)) {
        $zip->addFile($configLocalPath, 'config/config.local.php');
    }

    carceris_backup_zip_add_directory(
        $zip,
        carceris_project_root() . '/storage',
        'storage',
        ['backups', 'upgrades', 'maintenance.lock', 'upgrade-failed.json', 'restore-upload']
    );

    $zip->addFromString(
        'RESTORE-README.txt',
        "Carceris backup bundle\n\n"
        . "The in-app restore imports database.sql only.\n"
        . "config/config.local.php and storage/ are included for manual disaster recovery.\n"
        . "Do not store this backup where unauthorized users can access it.\n"
    );

    $zip->close();
    @unlink($sqlPath);
    @chmod($zipPath, 0640);

    return [
        'path' => $zipPath,
        'filename' => $filename,
        'manifest' => $manifest,
    ];
}

function carceris_verify_admin_password(array $user, string $password): bool
{
    $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = :id AND role = "admin" AND is_active = 1 LIMIT 1');
    $stmt->execute(['id' => (int) ($user['id'] ?? 0)]);
    $hash = $stmt->fetchColumn();

    return is_string($hash) && password_verify($password, $hash);
}

function carceris_backup_split_sql(string $sql): array
{
    $statements = [];
    $buffer = '';
    $length = strlen($sql);
    $quote = null;
    $escaped = false;
    $lineComment = false;

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = $i + 1 < $length ? $sql[$i + 1] : '';

        if ($lineComment) {
            if ($char === "\n") {
                $lineComment = false;
                $buffer .= $char;
            }

            continue;
        }

        if ($quote === null && $char === '-' && $next === '-') {
            $lineComment = true;
            $i++;
            continue;
        }

        if ($quote !== null) {
            $buffer .= $char;

            if ($escaped) {
                $escaped = false;
                continue;
            }

            if ($char === '\\') {
                $escaped = true;
                continue;
            }

            if ($char === $quote) {
                $quote = null;
            }

            continue;
        }

        if ($char === "'" || $char === '"') {
            $quote = $char;
            $buffer .= $char;
            continue;
        }

        if ($char === ';') {
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

function carceris_restore_database_sql(string $sql): int
{
    if (!str_contains($sql, 'Carceris database backup') || !str_contains($sql, 'Backup format: carceris-database-sql-v1')) {
        throw new RuntimeException('Uploaded backup does not appear to be a Carceris database backup.');
    }

    $statements = carceris_backup_split_sql($sql);
    $count = 0;

    foreach ($statements as $statement) {
        if (trim($statement) === '') {
            continue;
        }

        db()->exec($statement);
        $count++;
    }

    return $count;
}

function carceris_restore_database_from_backup_zip(string $zipPath, array $user): array
{
    if (!class_exists(ZipArchive::class)) {
        throw new RuntimeException('PHP ZipArchive is required to restore backup bundles.');
    }

    if (!is_file($zipPath)) {
        throw new RuntimeException('Uploaded backup file was not found.');
    }

    $zip = new ZipArchive();

    if ($zip->open($zipPath) !== true) {
        throw new RuntimeException('Could not open uploaded backup ZIP.');
    }

    $manifestRaw = $zip->getFromName('manifest.json');
    $databaseSql = $zip->getFromName('database.sql');
    $zip->close();

    if ($databaseSql === false || trim($databaseSql) === '') {
        throw new RuntimeException('Backup ZIP does not contain database.sql.');
    }

    $manifest = is_string($manifestRaw) ? json_decode($manifestRaw, true) : null;

    if (is_array($manifest) && ($manifest['backup_format'] ?? '') !== 'carceris-backup-v1') {
        throw new RuntimeException('Backup format is not supported.');
    }

    if (is_array($manifest) && ($manifest['app'] ?? '') !== 'Carceris') {
        throw new RuntimeException('Backup app marker is not supported.');
    }

    $preRestorePath = carceris_backup_write_database_sql_file('pre-restore-safety-backup');

    carceris_enable_maintenance_mode('Database restore in progress.');

    try {
        $statementCount = carceris_restore_database_sql($databaseSql);
        carceris_disable_maintenance_mode();

        return [
            'status' => 'restored',
            'statements' => $statementCount,
            'pre_restore_backup' => $preRestorePath,
            'manifest' => is_array($manifest) ? $manifest : [],
        ];
    } catch (Throwable $exception) {
        carceris_disable_maintenance_mode();

        throw new RuntimeException(
            'Restore failed. A pre-restore safety backup was saved at ' . $preRestorePath . '. Error: ' . $exception->getMessage(),
            0,
            $exception
        );
    }
}


function carceris_cleanup_backup_working_files(int $maxAgeHours = 24): int
{
    $dir = carceris_backup_storage_dir();
    $cutoff = time() - (max(1, $maxAgeHours) * 3600);
    $deleted = 0;

    foreach (glob($dir . '/*') ?: [] as $path) {
        if (!is_file($path)) {
            continue;
        }

        $name = basename($path);
        $isWorkingFile = str_starts_with($name, 'restore-upload-')
            || str_starts_with($name, 'database-for-bundle-')
            || str_starts_with($name, 'carceris-backup-');

        if (!$isWorkingFile) {
            continue;
        }

        $modified = filemtime($path);

        if ($modified !== false && $modified < $cutoff && @unlink($path)) {
            $deleted++;
        }
    }

    return $deleted;
}
