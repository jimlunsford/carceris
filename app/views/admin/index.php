<?php $pageTitle = 'Admin | Carceris'; ?>
<?php require __DIR__ . '/../partials/header.php'; ?>

<section class="page-heading">
    <div>
        <h1>Admin</h1>
        <p>Manage Carceris users, categories, settings, upgrades, and system records.</p>
    </div>
</section>

<section class="admin-grid">
    <?php if (user_can($user, 'view_reports') || user_can($user, 'send_reports')): ?>
        <a class="admin-card" href="/admin/reports.php">
            <strong>Daily Logs</strong>
            <span>Review completed logs, download files, send logs, retry failed sends, and review delivery status.</span>
        </a>
    <?php endif; ?>

    <?php if (user_can($user, 'manage_users')): ?>
        <a class="admin-card" href="/admin/users.php">
            <strong>Users</strong>
            <span>Create users, reset passwords, assign roles, and deactivate accounts.</span>
        </a>
    <?php endif; ?>

    <?php if (user_can($user, 'manage_settings')): ?>
        <a class="admin-card" href="/admin/categories.php">
            <strong>Categories</strong>
            <span>Add, edit, reorder, and deactivate log entry categories.</span>
        </a>

        <a class="admin-card" href="/admin/report-delivery.php">
            <strong>Log Delivery</strong>
            <span>Configure daily log delivery settings, formats, recipients, and cron key.</span>
        </a>

        <a class="admin-card" href="/admin/settings.php">
            <strong>Settings</strong>
            <span>Control site-wide settings such as clock format.</span>
        </a>

        <a class="admin-card" href="/admin/backup-restore.php">
            <strong>Backup & Restore</strong>
            <span>Download protected backup bundles and restore the database from a Carceris backup.</span>
        </a>

    <?php endif; ?>

    <?php if (user_can($user, 'manage_upgrades')): ?>
        <a class="admin-card" href="/admin/upgrade.php">
            <strong>Upgrade</strong>
            <span>Upload Carceris ZIP releases and review upgrade history.</span>
        </a>
    <?php endif; ?>

    <?php if (user_can($user, 'view_audit')): ?>
        <a class="admin-card" href="/admin/audit.php">
            <strong>Audit</strong>
            <span>Review officer activity, security events, and administrative events.</span>
        </a>
    <?php endif; ?>

    <?php if (user_can($user, 'view_status')): ?>
        <a class="admin-card" href="/status.php">
            <strong>System Status</strong>
            <span>Check HTTPS, install lock, database connection, migrations, and environment mode.</span>
        </a>
    <?php endif; ?>

</section>

<?php require __DIR__ . '/../partials/footer.php'; ?>
