<?php $pageTitle = 'System Status | Carceris'; ?>
<?php require __DIR__ . '/partials/header.php'; ?>

<section class="page-heading">
    <div>
        <h1>System Status</h1>
        <p>Deployment checks for Carceris.</p>
    </div>
</section>


<?php if (($upgradeFailure['failed'] ?? false) || count($statusMigrationSummary['pending']) > 0): ?>
<section class="panel panel-danger">
    <h2>System Attention Required</h2>

    <?php if ($upgradeFailure['failed'] ?? false): ?>
        <p><strong>Last upgrade failed.</strong> Review Admin → Upgrade before continuing production use.</p>
        <p class="empty-state">
            Target version: <?= e($upgradeFailure['to_version'] ?? 'unknown') ?>.
            Error: <?= e($upgradeFailure['error'] ?? 'unknown') ?>
        </p>
    <?php endif; ?>

    <?php if (count($statusMigrationSummary['pending']) > 0): ?>
        <p><strong>Pending migrations detected.</strong> The code files and database may not be fully aligned.</p>
        <ul>
            <?php foreach ($statusMigrationSummary['pending'] as $pendingMigration): ?>
                <li><?= e($pendingMigration) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
<?php endif; ?>

<section class="panel">
    <div class="status-grid">

        <div class="status-card">
            <span class="status-label">Current Version</span>
            <strong><?= e($currentVersion) ?></strong>
            <p>Installed Carceris package version.</p>
        </div>

        <div class="status-card">
            <span class="status-label">ZIP Extension</span>
            <strong class="<?= $zipAvailable ? 'status-good' : 'status-bad' ?>">
                <?= $zipAvailable ? 'Available' : 'Missing' ?>
            </strong>
            <p>PHP ZipArchive is required for admin ZIP upgrades.</p>
        </div>

        <div class="status-card">
            <span class="status-label">Migration Status</span>
            <strong class="<?= count($statusMigrationSummary['pending']) === 0 ? 'status-good' : 'status-warn' ?>">
                <?= count($statusMigrationSummary['pending']) === 0 ? 'Current' : e((string) count($statusMigrationSummary['pending'])) . ' Pending' ?>
            </strong>
            <p><?= count($statusMigrationSummary['pending']) === 0 ? 'No pending migration files detected.' : 'Pending migration files detected. Review Admin → Upgrade.' ?></p>
        </div>

        <div class="status-card">
            <span class="status-label">HTTPS</span>
            <strong class="<?= $httpsEnabled ? 'status-good' : 'status-bad' ?>">
                <?= $httpsEnabled ? 'Enabled' : 'Not Detected' ?>
            </strong>
            <p><?= $httpsEnabled ? 'The current request is using HTTPS.' : 'Do not use real operational data until HTTPS or equivalent internal transport protection is in place.' ?></p>
        </div>

        <div class="status-card">
            <span class="status-label">Force HTTPS</span>
            <strong class="<?= $securityChecks['force_https_enabled'] ? 'status-good' : 'status-warn' ?>">
                <?= $securityChecks['force_https_enabled'] ? 'Enabled' : 'Disabled' ?>
            </strong>
            <p>When enabled, Carceris redirects HTTP requests to HTTPS.</p>
        </div>

        <div class="status-card">
            <span class="status-label">Install Lock</span>
            <strong class="<?= $installLockExists ? 'status-good' : 'status-bad' ?>">
                <?= $installLockExists ? 'Present' : 'Missing' ?>
            </strong>
            <p><?= $installLockExists ? 'The installer lock file exists.' : 'The installer lock file is missing. The installer may not be properly locked.' ?></p>
        </div>

        <div class="status-card">
            <span class="status-label">Install Directory</span>
            <strong class="<?= $securityChecks['install_directory_exists'] ? 'status-warn' : 'status-good' ?>">
                <?= $securityChecks['install_directory_exists'] ? 'Present' : 'Not Found' ?>
            </strong>
            <p>For production, delete or server-protect the install directory after setup.</p>
        </div>

        <div class="status-card">
            <span class="status-label">PHP Version</span>
            <strong><?= e($phpStatus['version']) ?></strong>
            <p>SAPI: <?= e($phpStatus['sapi']) ?>. PDO MySQL: <?= $phpStatus['pdo_mysql'] ? 'Available' : 'Missing' ?>.</p>
        </div>

        <div class="status-card">
            <span class="status-label">Database</span>
            <strong class="<?= $databaseStatus['ok'] ? 'status-good' : 'status-bad' ?>">
                <?= e($databaseStatus['message']) ?>
            </strong>
            <p><?= $databaseStatus['ok'] ? 'Carceris can connect to the configured database.' : 'Database connection failed. Review config.local.php and database permissions.' ?></p>
        </div>

        <div class="status-card">
            <span class="status-label">Environment Mode</span>
            <strong><?= e(ucfirst($environmentMode)) ?></strong>
            <p>
                <?php if ($environmentMode === 'testing'): ?>
                    Testing mode should be used for fake data, installer checks, and workflow testing.
                <?php elseif ($environmentMode === 'internal'): ?>
                    Internal mode is intended for controlled network deployments.
                <?php else: ?>
                    Production mode should only be used after server, access, backup, and policy reviews.
                <?php endif; ?>
            </p>
        </div>

        <div class="status-card">
            <span class="status-label">Config File</span>
            <strong class="<?= $securityChecks['config_local_exists'] ? 'status-good' : 'status-bad' ?>">
                <?= $securityChecks['config_local_exists'] ? 'Present' : 'Missing' ?>
            </strong>
            <p>Generated database configuration file.</p>
        </div>

        <div class="status-card">
            <span class="status-label">Config Directory Writable</span>
            <strong class="<?= $securityChecks['config_directory_writable'] ? 'status-warn' : 'status-good' ?>">
                <?= $securityChecks['config_directory_writable'] ? 'Writable' : 'Not Writable' ?>
            </strong>
            <p>Writable is useful during install, but production should restrict it when possible.</p>
        </div>

        <div class="status-card">
            <span class="status-label">Storage Writable</span>
            <strong class="<?= $securityChecks['storage_writable'] ? 'status-good' : 'status-bad' ?>">
                <?= $securityChecks['storage_writable'] ? 'Writable' : 'Not Writable' ?>
            </strong>
            <p>Storage must be writable for locks, exports, and logs.</p>
        </div>
    </div>
</section>



<section class="panel <?= $schemaHealth['ok'] ? '' : 'panel-danger' ?>">
    <h2>Schema Health</h2>
    <p>
        <?php if ($schemaHealth['ok']): ?>
            Required tables and columns are present.
        <?php else: ?>
            Required schema items are missing. Run Admin → Upgrade with the latest package before production use.
        <?php endif; ?>
    </p>

    <?php if (!$schemaHealth['ok']): ?>
        <?php if (!empty($schemaHealth['missing_tables'])): ?>
            <h3>Missing Tables</h3>
            <ul><?php foreach ($schemaHealth['missing_tables'] as $item): ?><li><?= e($item) ?></li><?php endforeach; ?></ul>
        <?php endif; ?>
        <?php if (!empty($schemaHealth['missing_columns'])): ?>
            <h3>Missing Columns</h3>
            <ul><?php foreach ($schemaHealth['missing_columns'] as $item): ?><li><?= e($item) ?></li><?php endforeach; ?></ul>
        <?php endif; ?>
    <?php endif; ?>
</section>

<section class="panel">
    <h2>Mail Transport Capabilities</h2>
    <div class="status-list">
        <div class="status-list-row"><span>PHP mail()</span><strong class="<?= $mailCapabilities['php_mail'] ? 'status-good' : 'status-bad' ?>"><?= $mailCapabilities['php_mail'] ? 'Available' : 'Unavailable' ?></strong></div>
        <div class="status-list-row"><span>Native SMTP socket support</span><strong class="<?= $mailCapabilities['native_smtp'] ? 'status-good' : 'status-bad' ?>"><?= $mailCapabilities['native_smtp'] ? 'Available' : 'Unavailable' ?></strong></div>
        <div class="status-list-row"><span>PHPMailer library</span><strong class="<?= $mailCapabilities['phpmailer'] ? 'status-good' : 'status-warn' ?>"><?= $mailCapabilities['phpmailer'] ? 'Installed' : 'Not installed' ?></strong></div>
        <div class="status-list-row"><span>Sendmail proc_open</span><strong class="<?= $mailCapabilities['sendmail_proc_open'] ? 'status-good' : 'status-bad' ?>"><?= $mailCapabilities['sendmail_proc_open'] ? 'Available' : 'Unavailable' ?></strong></div>
        <div class="status-list-row"><span>Sendmail path executable</span><strong class="<?= $mailCapabilities['sendmail_path_executable'] ? 'status-good' : 'status-bad' ?>"><?= $mailCapabilities['sendmail_path_executable'] ? 'Executable' : 'Not executable' ?></strong></div>
    </div>
</section>

<section class="panel <?= $productionReadiness['ready'] ? '' : 'panel-danger' ?>">
    <h2>Production Readiness</h2>
    <p>
        <?php if ($productionReadiness['ready']): ?>
            Core production readiness checks are passing. Final approval still requires deployment review, backups, user testing, and facility policy review.
        <?php else: ?>
            Carceris is not ready for production use until the failed checks below are resolved.
        <?php endif; ?>
    </p>

    <div class="status-list">
        <?php foreach ($productionReadiness['checks'] as $check): ?>
            <div class="status-list-row">
                <span>
                    <strong><?= e($check['label']) ?></strong><br>
                    <small><?= e($check['message']) ?></small>
                </span>
                <strong class="<?= $check['passed'] ? 'status-good' : 'status-bad' ?>"><?= $check['passed'] ? 'Pass' : 'Review' ?></strong>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="panel">
    <h2>Protected Internal Folders</h2>
    <div class="status-list">
        <?php
            $protectedFolders = [
                'app/.htaccess' => $securityChecks['app_htaccess_exists'],
                'app/config/.htaccess' => $securityChecks['app_config_htaccess_exists'],
                'database/.htaccess' => $securityChecks['database_htaccess_exists'],
                'storage/.htaccess' => $securityChecks['storage_htaccess_exists'],
                'tools/.htaccess' => $securityChecks['tools_htaccess_exists'],
            ];
        ?>
        <?php foreach ($protectedFolders as $label => $exists): ?>
            <div class="status-list-row">
                <span><?= e($label) ?></span>
                <strong class="<?= $exists ? 'status-good' : 'status-bad' ?>"><?= $exists ? 'Present' : 'Missing' ?></strong>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
