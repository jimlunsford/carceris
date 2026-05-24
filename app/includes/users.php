<?php

declare(strict_types=1);

function carceris_users_all(): array
{
    $stmt = db()->query(
        'SELECT id, username, display_name, role, is_active, last_login_at, created_at
         FROM users
         ORDER BY is_active DESC, display_name ASC, username ASC'
    );

    return $stmt->fetchAll();
}

function carceris_user_by_id(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT id, username, display_name, role, is_active, last_login_at, created_at
         FROM users
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $id]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function carceris_active_admin_count(?int $excludeUserId = null): int
{
    if ($excludeUserId === null) {
        $stmt = db()->query('SELECT COUNT(*) FROM users WHERE role = "admin" AND is_active = 1');
        return (int) $stmt->fetchColumn();
    }

    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM users
         WHERE role = "admin"
           AND is_active = 1
           AND id <> :exclude_user_id'
    );
    $stmt->execute(['exclude_user_id' => $excludeUserId]);

    return (int) $stmt->fetchColumn();
}

function carceris_create_user(string $username, string $displayName, string $role, string $password): int
{
    if (!in_array($role, carceris_roles(), true)) {
        throw new RuntimeException('Invalid role.');
    }

    if (!preg_match('/^[a-zA-Z0-9_.-]{3,80}$/', $username)) {
        throw new RuntimeException('Username must be 3 to 80 characters and may only contain letters, numbers, dots, dashes, and underscores.');
    }

    if ($displayName === '') {
        throw new RuntimeException('Display name is required.');
    }

    if (strlen($password) < 12) {
        throw new RuntimeException('Password must be at least 12 characters.');
    }

    $stmt = db()->prepare(
        'INSERT INTO users (username, password_hash, display_name, role, is_active)
         VALUES (:username, :password_hash, :display_name, :role, 1)'
    );

    $stmt->execute([
        'username' => $username,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'display_name' => $displayName,
        'role' => $role,
    ]);

    return (int) db()->lastInsertId();
}

function carceris_update_user(int $id, string $displayName, string $role, bool $isActive): void
{
    $existing = carceris_user_by_id($id);

    if (!$existing) {
        throw new RuntimeException('User not found.');
    }

    if (!in_array($role, carceris_roles(), true)) {
        throw new RuntimeException('Invalid role.');
    }

    if ($displayName === '') {
        throw new RuntimeException('Display name is required.');
    }

    $wouldRemoveAdmin = $existing['role'] === 'admin'
        && ((int) $existing['is_active'] === 1)
        && ($role !== 'admin' || !$isActive);

    if ($wouldRemoveAdmin && carceris_active_admin_count($id) < 1) {
        throw new RuntimeException('You cannot remove or deactivate the last active admin.');
    }

    $stmt = db()->prepare(
        'UPDATE users
         SET display_name = :display_name,
             role = :role,
             is_active = :is_active
         WHERE id = :id'
    );

    $stmt->execute([
        'display_name' => $displayName,
        'role' => $role,
        'is_active' => $isActive ? 1 : 0,
        'id' => $id,
    ]);
}

function carceris_reset_user_password(int $id, string $password): void
{
    $existing = carceris_user_by_id($id);

    if (!$existing) {
        throw new RuntimeException('User not found.');
    }

    if (strlen($password) < 12) {
        throw new RuntimeException('Password must be at least 12 characters.');
    }

    $stmt = db()->prepare(
        'UPDATE users SET password_hash = :password_hash WHERE id = :id'
    );

    $stmt->execute([
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'id' => $id,
    ]);
}

function carceris_verify_user_password(array $user, string $password): bool
{
    $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = :id AND is_active = 1 LIMIT 1');
    $stmt->execute(['id' => (int) ($user['id'] ?? 0)]);
    $row = $stmt->fetch();

    if (!$row) {
        return false;
    }

    return password_verify($password, (string) $row['password_hash']);
}

function carceris_change_user_password(int $id, string $password): void
{
    if (strlen($password) < 12) {
        throw new RuntimeException('Password must be at least 12 characters.');
    }

    $stmt = db()->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
    $stmt->execute([
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'id' => $id,
    ]);
}
