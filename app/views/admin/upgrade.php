<?php $pageTitle = 'Upgrade | Carceris'; ?>
<?php require __DIR__ . '/../partials/header.php'; ?>

<section class="page-heading">
    <div>
        <h1>Upgrade Carceris</h1>
        <p>Upload a Carceris ZIP package and run pending database migrations.</p>
    </div>
</section>


<?php if ($upgradeFailure['failed'] ?? false): ?>
<section class="panel panel-danger">
    <h2>Last Upgrade Failed</h2>
    <p>Carceris recorded a failed upgrade. Files may have copied before the failure, so review pending migrations and run a newer package if needed.</p>
    <p class="empty-state">
        From: <?= e($upgradeFailure['from_version'] ?? 'unknown') ?>.
        To: <?= e($upgradeFailure['to_version'] ?? 'unknown') ?>.
        Error: <?= e($upgradeFailure['error'] ?? 'unknown') ?>
    </p>
</section>
<?php endif; ?>

<section class="panel">
    <h2>Current Installation</h2>

    <div class="status-grid">
        <div class="status-card">
            <span class="status-label">Current Version</span>
            <strong><?= e($currentVersion) ?></strong>
            <p>The version currently installed on this server.</p>
        </div>

        <div class="status-card">
            <span class="status-label">ZIP Support</span>
            <strong class="<?= $zipAvailable ? 'status-good' : 'status-bad' ?>">
                <?= $zipAvailable ? 'Available' : 'Missing' ?>
            </strong>
            <p>PHP ZipArchive is required for browser-based upgrades.</p>
        </div>

        <div class="status-card">
            <span class="status-label">Pending Migrations</span>
            <strong class="<?= count($migrationSummary['pending']) === 0 ? 'status-good' : 'status-warn' ?>">
                <?= e((string) count($migrationSummary['pending'])) ?>
            </strong>
            <p><?= count($migrationSummary['pending']) === 0 ? 'No pending migration files detected.' : 'Pending migrations are waiting to be run.' ?></p>
        </div>

        <div class="status-card">
            <span class="status-label">Maintenance Mode</span>
            <strong class="<?= $maintenanceMode ? 'status-warn' : 'status-good' ?>">
                <?= $maintenanceMode ? 'Enabled' : 'Off' ?>
            </strong>
            <p>Maintenance mode is enabled automatically while an upgrade runs.</p>
        </div>
    </div>
</section>

<section class="panel">
    <h2>Upload Upgrade ZIP</h2>

    <p class="empty-state">Back up your files and database before upgrading. The upgrade system preserves local config and storage files, but a backup is still the correct move before changing production files.</p>

    <?php if (!$zipAvailable): ?>
        <p class="flash flash--error">PHP ZipArchive is not available. Browser-based upgrades cannot run on this server until the ZIP extension is enabled.</p>
    <?php else: ?>
        <form method="post" action="/admin/upgrade.php" enctype="multipart/form-data" class="form-stack">
            <?= csrf_field() ?>

            <label>
                <span>Carceris Upgrade ZIP</span>
                <input type="file" name="upgrade_zip" accept=".zip,application/zip" required>
            </label>

            <label>
                <span>Confirm Admin Password</span>
                <input type="password" name="admin_password" autocomplete="current-password" required>
            </label>

            <label class="checkbox-row">
                <input type="checkbox" name="upgrade_backup_acknowledgement" value="1" required>
                <span>I confirm that I created a current backup before running this upgrade.</span>
            </label>

            <button type="submit">Run Upgrade</button>
        </form>
    <?php endif; ?>
</section>


<section class="panel">
    <h2>Recent Upgrade Events</h2>

    <?php if (!$upgradeEvents): ?>
        <p class="empty-state">No upgrade events found.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="log-table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Event</th>
                        <th>User</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($upgradeEvents as $event): ?>
                        <tr>
                            <td><?= e(carceris_format_datetime($event['created_at'])) ?></td>
                            <td><?= e($event['event_type']) ?></td>
                            <td><?= e($event['username'] ?? '') ?></td>
                            <td><?= e($event['details'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="panel">
    <h2>Recent Migration Records</h2>

    <?php if (!$migrationSummary['recent']): ?>
        <p class="empty-state">No migration records found. Carceris will baseline the current migration files before future upgrades.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="log-table">
                <thead>
                    <tr>
                        <th>Version</th>
                        <th>Migration File</th>
                        <th>Status</th>
                        <th>Executed At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($migrationSummary['recent'] as $migration): ?>
                        <tr>
                            <td><?= e($migration['version']) ?></td>
                            <td><?= e($migration['migration_file']) ?></td>
                            <td><?= e($migration['status']) ?></td>
                            <td><?= e(carceris_format_datetime($migration['executed_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../partials/footer.php'; ?>
