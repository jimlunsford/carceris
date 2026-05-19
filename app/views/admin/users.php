<?php $pageTitle = 'Users | Carceris'; ?>
<?php require __DIR__ . '/../partials/header.php'; ?>

<section class="page-heading">
    <div>
        <h1>User Management</h1>
        <p>Create users, assign roles, reset passwords, and deactivate accounts.</p>
    </div>
</section>

<section class="panel">
    <h2>Create User</h2>

    <form method="post" action="/admin/users.php" class="entry-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">

        <label>
            <span>Username</span>
            <input type="text" name="username" required maxlength="80" autocomplete="off">
        </label>

        <label>
            <span>Display Name</span>
            <input type="text" name="display_name" required maxlength="120">
        </label>

        <label>
            <span>Role</span>
            <select name="role" required>
                <?php foreach (carceris_roles() as $role): ?>
                    <option value="<?= e($role) ?>"><?= e(carceris_role_label($role)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            <span>Password</span>
            <input type="password" name="password" required autocomplete="new-password">
        </label>

        <div class="form-actions">
            <button type="submit">Create User</button>
        </div>
    </form>
</section>


<section class="panel">
    <h2>Role Permission Summary</h2>

    <div class="role-summary-grid">
        <?php foreach (carceris_role_permission_summary() as $role => $permissions): ?>
            <div class="role-summary-card">
                <strong><?= e(carceris_role_label($role)) ?></strong>
                <p><?= e(carceris_role_description($role)) ?></p>
                <ul>
                    <?php foreach ($permissions as $permission): ?>
                        <li><?= e($permission) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="panel">
    <h2>Existing Users</h2>

    <div class="table-wrap">
        <table class="log-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th>Update User</th>
                    <th>Reset Password</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $managedUser): ?>
                    <tr>
                        <td>
                            <strong><?= e($managedUser['display_name']) ?></strong><br>
                            <span class="muted-small"><?= e($managedUser['username']) ?></span>
                        </td>
                        <td>
                            <strong><?= e(carceris_role_label($managedUser['role'])) ?></strong><br>
                            <span class="muted-small"><?= e(carceris_role_description($managedUser['role'])) ?></span>
                        </td>
                        <td><?= (int) $managedUser['is_active'] === 1 ? 'Active' : 'Inactive' ?></td>
                        <td><?= !empty($managedUser['last_login_at']) ? e(carceris_format_datetime($managedUser['last_login_at'])) : 'Never' ?></td>
                        <td>
                            <form method="post" action="/admin/users.php" class="inline-edit-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="user_id" value="<?= e((string) $managedUser['id']) ?>">

                                <label>
                                    <span>Display Name</span>
                                    <input type="text" name="display_name" value="<?= e($managedUser['display_name']) ?>" required>
                                </label>

                                <label>
                                    <span>Role</span>
                                    <select name="role" required>
                                        <?php foreach (carceris_roles() as $role): ?>
                                            <option value="<?= e($role) ?>" <?= $role === $managedUser['role'] ? 'selected' : '' ?>><?= e(carceris_role_label($role)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>

                                <label class="checkbox-row">
                                    <input type="checkbox" name="is_active" value="1" <?= (int) $managedUser['is_active'] === 1 ? 'checked' : '' ?>>
                                    <span>Active</span>
                                </label>

                                <button type="submit">Save</button>
                            </form>
                        </td>
                        <td>
                            <form method="post" action="/admin/users.php" class="inline-edit-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="reset_password">
                                <input type="hidden" name="user_id" value="<?= e((string) $managedUser['id']) ?>">

                                <label>
                                    <span>New Password</span>
                                    <input type="password" name="password" autocomplete="new-password" required>
                                </label>

                                <button type="submit">Reset</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require __DIR__ . '/../partials/footer.php'; ?>
