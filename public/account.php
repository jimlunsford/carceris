<?php

declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$user = require_login();

if (request_method() === 'POST') {
    csrf_require();

    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['new_password_confirm'] ?? '');

    try {
        if (!carceris_verify_user_password($user, $currentPassword)) {
            throw new RuntimeException('Current password is incorrect.');
        }

        if (strlen($newPassword) < 12) {
            throw new RuntimeException('New password must be at least 12 characters.');
        }

        if ($newPassword !== $confirmPassword) {
            throw new RuntimeException('New passwords do not match.');
        }

        carceris_change_user_password((int) $user['id'], $newPassword);

        audit_event(
            'self_password_changed',
            (int) $user['id'],
            $user['username'] ?? null,
            'User changed their own password.'
        );

        flash_set('success', 'Password updated.');
        redirect('/account.php');
    } catch (Throwable $exception) {
        flash_set('error', $exception->getMessage());
        redirect('/account.php');
    }
}

require __DIR__ . '/../app/views/account.php';
