<?php

declare(strict_types=1);

require __DIR__ . '/../../app/bootstrap.php';

$user = require_login();

if (!user_can($user, 'manage_backups')) {
    http_response_code(403);
    exit('You do not have permission to manage backups.');
}

if (request_method() === 'POST') {
    csrf_require();

    $action = post_string('action', 60);
    $password = (string) ($_POST['admin_password'] ?? '');

    if (!carceris_verify_admin_password($user, $password)) {
        audit_event(
            'backup_restore_password_failed',
            (int) $user['id'],
            $user['username'] ?? null,
            'Admin password confirmation failed on Backup & Restore.'
        );

        flash_set('error', 'Admin password confirmation failed.');
        redirect('/admin/backup-restore.php');
    }

    if ($action === 'create_backup') {
        if (empty($_POST['backup_acknowledgement'])) {
            flash_set('error', 'You must acknowledge that backup files contain sensitive data.');
            redirect('/admin/backup-restore.php');
        }

        try {
            $backup = carceris_create_backup_bundle($user);

            audit_event(
                'backup_bundle_downloaded',
                (int) $user['id'],
                $user['username'] ?? null,
                'Created and downloaded a Carceris backup bundle: ' . $backup['filename'] . '.'
            );

            if (!headers_sent()) {
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . $backup['filename'] . '"');
                header('Content-Length: ' . filesize($backup['path']));
                header('X-Content-Type-Options: nosniff');
                header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
                header('Pragma: no-cache');
            }

            readfile($backup['path']);
            @unlink($backup['path']);
            exit;
        } catch (Throwable $exception) {
            flash_set('error', 'Backup failed: ' . $exception->getMessage());
            redirect('/admin/backup-restore.php');
        }
    }

    if ($action === 'restore_database') {
        $confirmation = trim((string) ($_POST['restore_confirmation'] ?? ''));

        if ($confirmation !== 'RESTORE') {
            flash_set('error', 'You must type RESTORE to confirm database restore.');
            redirect('/admin/backup-restore.php');
        }

        if (empty($_POST['restore_acknowledgement'])) {
            flash_set('error', 'You must acknowledge that restore replaces the current database.');
            redirect('/admin/backup-restore.php');
        }

        if (empty($_FILES['backup_zip']) || !is_uploaded_file($_FILES['backup_zip']['tmp_name'])) {
            flash_set('error', 'Backup ZIP upload is required.');
            redirect('/admin/backup-restore.php');
        }

        if (($_FILES['backup_zip']['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            flash_set('error', 'Backup ZIP upload failed.');
            redirect('/admin/backup-restore.php');
        }

        $maxRestoreBytes = 250 * 1024 * 1024;

        if ((int) ($_FILES['backup_zip']['size'] ?? 0) <= 0 || (int) ($_FILES['backup_zip']['size'] ?? 0) > $maxRestoreBytes) {
            flash_set('error', 'Backup ZIP must be greater than 0 bytes and no larger than 250 MB.');
            redirect('/admin/backup-restore.php');
        }

        $originalName = (string) ($_FILES['backup_zip']['name'] ?? 'backup.zip');

        if (!preg_match('/\.zip$/i', $originalName)) {
            flash_set('error', 'Only .zip backup bundles are accepted.');
            redirect('/admin/backup-restore.php');
        }

        $uploadPath = carceris_backup_storage_dir() . '/restore-upload-' . carceris_backup_timestamp() . '-' . bin2hex(random_bytes(4)) . '.zip';

        if (!move_uploaded_file($_FILES['backup_zip']['tmp_name'], $uploadPath)) {
            flash_set('error', 'Could not store uploaded backup ZIP.');
            redirect('/admin/backup-restore.php');
        }

        try {
            audit_event(
                'database_restore_started',
                (int) $user['id'],
                $user['username'] ?? null,
                'Database restore started from uploaded backup bundle.'
            );

            $result = carceris_restore_database_from_backup_zip($uploadPath, $user);

            audit_event(
                'database_restore_completed',
                (int) ($user['id'] ?? 0),
                $user['username'] ?? null,
                'Database restore completed. SQL statements executed: ' . $result['statements'] . '. Pre-restore safety backup: ' . basename($result['pre_restore_backup']) . '.'
            );

            @unlink($uploadPath);

            flash_set('success', 'Database restore completed. A pre-restore safety backup was saved in storage/backups.');
            redirect('/admin/backup-restore.php');
        } catch (Throwable $exception) {
            audit_event(
                'database_restore_failed',
                (int) ($user['id'] ?? 0),
                $user['username'] ?? null,
                'Database restore failed: ' . $exception->getMessage()
            );

            @unlink($uploadPath);

            flash_set('error', $exception->getMessage());
            redirect('/admin/backup-restore.php');
        }
    }

    flash_set('error', 'Unknown Backup & Restore action.');
    redirect('/admin/backup-restore.php');
}

$cleanupCount = carceris_cleanup_backup_working_files(24);
$zipAvailable = class_exists(ZipArchive::class);
$backupDir = carceris_backup_storage_dir();
$backupDirWritable = is_writable($backupDir);

audit_event(
    'backup_restore_viewed',
    (int) $user['id'],
    $user['username'] ?? null,
    'Viewed Backup & Restore.'
);

require __DIR__ . '/../../app/views/admin/backup-restore.php';
