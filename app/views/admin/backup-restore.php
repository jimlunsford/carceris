<?php $pageTitle = 'Backup & Restore | Carceris'; ?>
<?php require __DIR__ . '/../partials/header.php'; ?>

<section class="page-heading">
    <div>
        <h1>Backup & Restore</h1>
        <p>Create backup bundles and restore the Carceris database from a verified backup.</p>
    </div>
</section>

<section class="panel panel-danger">
    <h2>Read This First</h2>
    <p>Backup files contain sensitive operational data, users, audit records, settings, configuration details, and any saved SMTP password. Store them securely and do not email or upload them to unapproved systems.</p>
    <p>Restore is destructive. It imports the backup database and replaces the current database state. Only restore backup ZIP files from a trusted Carceris installation. Carceris creates a pre-restore safety backup first, but you should still have an external backup before restoring.</p>
</section>

<section class="panel">
    <h2>Backup Status</h2>

    <div class="status-list">
        <div class="status-list-row">
            <span>PHP ZipArchive</span>
            <strong class="<?= $zipAvailable ? 'status-good' : 'status-bad' ?>"><?= $zipAvailable ? 'Available' : 'Missing' ?></strong>
        </div>
        <div class="status-list-row">
            <span>Backup storage directory</span>
            <strong class="<?= $backupDirWritable ? 'status-good' : 'status-bad' ?>"><?= $backupDirWritable ? 'Writable' : 'Not writable' ?></strong>
        </div>
    </div>
</section>

<section class="panel">
    <h2>Create Backup Bundle</h2>
    <p class="empty-state">Creates a ZIP backup containing database.sql, manifest.json, config.local.php if present, and selected storage files.</p>

    <form method="post" action="/admin/backup-restore.php" class="form-stack">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create_backup">

        <label>
            <span>Confirm Admin Password</span>
            <input type="password" name="admin_password" autocomplete="current-password" required>
        </label>

        <label class="checkbox-row">
            <input type="checkbox" name="backup_acknowledgement" value="1" required>
            <span>I understand this backup contains sensitive operational data, configuration data, and possible saved mail credentials.</span>
        </label>

        <div class="form-actions">
            <button type="submit" <?= (!$zipAvailable || !$backupDirWritable) ? 'disabled' : '' ?>>Download Backup Bundle</button>
        </div>
    </form>
</section>

<section class="panel panel-danger">
    <h2>Restore Database From Backup</h2>
    <p>The in-app restore imports <strong>database.sql</strong> from a Carceris backup bundle. Config and storage files inside the bundle are for manual disaster recovery and are not automatically restored.</p>

    <form method="post" action="/admin/backup-restore.php" enctype="multipart/form-data" class="form-stack">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="restore_database">

        <label>
            <span>Backup ZIP</span>
            <input type="file" name="backup_zip" accept=".zip,application/zip" required>
        </label>

        <label>
            <span>Confirm Admin Password</span>
            <input type="password" name="admin_password" autocomplete="current-password" required>
        </label>

        <label>
            <span>Type RESTORE to Confirm</span>
            <input type="text" name="restore_confirmation" required>
        </label>

        <label class="checkbox-row">
            <input type="checkbox" name="restore_acknowledgement" value="1" required>
            <span>I understand this will replace the current Carceris database state and I trust this backup source.</span>
        </label>

        <div class="form-actions">
            <button type="submit" <?= (!$zipAvailable || !$backupDirWritable) ? 'disabled' : '' ?>>Restore Database</button>
        </div>
    </form>
</section>

<?php require __DIR__ . '/../partials/footer.php'; ?>
