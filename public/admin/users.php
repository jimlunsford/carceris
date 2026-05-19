<?php

declare(strict_types=1);

require __DIR__ . '/../../app/bootstrap.php';

$user = require_login();

if (!user_can($user, 'manage_users')) {
    http_response_code(403);
    exit('You do not have permission to manage users.');
}

if (request_method() === 'POST') {
    csrf_require();

    $action = post_string('action', 40);

    try {
        if ($action === 'create') {
            $username = post_string('username', 80);
            $displayName = post_string('display_name', 120);
            $role = post_string('role', 40);
            $password = (string) ($_POST['password'] ?? '');

            $createdId = carceris_create_user($username, $displayName, $role, $password);

            audit_event(
                'user_created',
                (int) $user['id'],
                $user['username'] ?? null,
                'Created user: ' . $username . ' with role: ' . $role,
                'user',
                $createdId
            );

            flash_set('success', 'User created.');
            redirect('/admin/users.php');
        }

        if ($action === 'update') {
            $managedUserId = (int) ($_POST['user_id'] ?? 0);
            $displayName = post_string('display_name', 120);
            $role = post_string('role', 40);
            $isActive = isset($_POST['is_active']) && $_POST['is_active'] === '1';

            $before = carceris_user_by_id($managedUserId);
            carceris_update_user($managedUserId, $displayName, $role, $isActive);
            $after = carceris_user_by_id($managedUserId);

            audit_event(
                'user_updated',
                (int) $user['id'],
                $user['username'] ?? null,
                'Updated user: ' . ($after['username'] ?? (string) $managedUserId) . '. Role/status before: ' . ($before['role'] ?? 'unknown') . '/' . ((int) ($before['is_active'] ?? 0) === 1 ? 'active' : 'inactive') . '. After: ' . ($after['role'] ?? 'unknown') . '/' . ((int) ($after['is_active'] ?? 0) === 1 ? 'active' : 'inactive') . '.',
                'user',
                $managedUserId
            );

            flash_set('success', 'User updated.');
            redirect('/admin/users.php');
        }

        if ($action === 'reset_password') {
            $managedUserId = (int) ($_POST['user_id'] ?? 0);
            $password = (string) ($_POST['password'] ?? '');

            $target = carceris_user_by_id($managedUserId);
            carceris_reset_user_password($managedUserId, $password);

            audit_event(
                'user_password_reset',
                (int) $user['id'],
                $user['username'] ?? null,
                'Reset password for user: ' . ($target['username'] ?? (string) $managedUserId),
                'user',
                $managedUserId
            );

            flash_set('success', 'Password reset.');
            redirect('/admin/users.php');
        }

        flash_set('error', 'Unknown user action.');
    } catch (Throwable $exception) {
        flash_set('error', $exception->getMessage());
    }

    redirect('/admin/users.php');
}

$users = carceris_users_all();

require __DIR__ . '/../../app/views/admin/users.php';
