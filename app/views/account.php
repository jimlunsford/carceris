<?php $pageTitle = 'Account | Carceris'; ?>
<?php require __DIR__ . '/partials/header.php'; ?>

<section class="page-heading">
    <div>
        <h1>Account</h1>
        <p>Review your account details and update your password.</p>
    </div>
</section>

<section class="panel">
    <h2>Account Details</h2>

    <div class="status-list">
        <div class="status-list-row">
            <span>Display Name</span>
            <strong><?= e($user['display_name'] ?? '') ?></strong>
        </div>
        <div class="status-list-row">
            <span>Username</span>
            <strong><?= e($user['username'] ?? '') ?></strong>
        </div>
        <div class="status-list-row">
            <span>Role</span>
            <strong><?= e(carceris_role_label($user['role'] ?? 'viewer')) ?></strong>
        </div>
    </div>
</section>

<section class="panel">
    <h2>Change Password</h2>
    <p class="empty-state">Use a strong password. Changing it here does not change your role or permissions.</p>

    <form method="post" action="/account.php" class="form-stack">
        <?= csrf_field() ?>

        <label>
            <span>Current Password</span>
            <input type="password" name="current_password" autocomplete="current-password" required>
        </label>

        <label>
            <span>New Password</span>
            <input type="password" name="new_password" autocomplete="new-password" minlength="12" required>
        </label>

        <label>
            <span>Confirm New Password</span>
            <input type="password" name="new_password_confirm" autocomplete="new-password" minlength="12" required>
        </label>

        <div class="form-actions">
            <button type="submit">Update Password</button>
        </div>
    </form>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
