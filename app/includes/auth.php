<?php

declare(strict_types=1);

const CARCERIS_LOGIN_MAX_FAILURES = 5;
const CARCERIS_LOGIN_WINDOW_MINUTES = 15;
const CARCERIS_LOGIN_LOCKOUT_MINUTES = 15;

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT id, username, display_name, role, is_active FROM users WHERE id = :id LIMIT 1'
    );
    $stmt->execute(['id' => (int) $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || (int) $user['is_active'] !== 1) {
        logout_user(false);
        return null;
    }

    return $user;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function require_login(): array
{
    $user = current_user();

    if (!$user) {
        redirect('/login.php');
    }

    return $user;
}

function login_subject(string $username): string
{
    $username = strtolower(trim($username));

    if ($username === '') {
        $username = 'unknown';
    }

    return hash('sha256', $username . '|' . client_ip_address());
}

function login_is_locked(string $username): bool
{
    $subject = login_subject($username);

    $stmt = db()->prepare(
        'SELECT COUNT(*)
         FROM login_attempts
         WHERE subject_hash = :subject_hash
           AND was_successful = 0
           AND attempted_at >= (NOW() - INTERVAL :window_minutes MINUTE)'
    );

    $stmt->bindValue(':subject_hash', $subject, PDO::PARAM_STR);
    $stmt->bindValue(':window_minutes', CARCERIS_LOGIN_WINDOW_MINUTES, PDO::PARAM_INT);
    $stmt->execute();

    return (int) $stmt->fetchColumn() >= CARCERIS_LOGIN_MAX_FAILURES;
}

function record_login_attempt(string $username, bool $wasSuccessful, ?int $userId = null, ?string $failureReason = null): void
{
    $stmt = db()->prepare(
        'INSERT INTO login_attempts
            (username, subject_hash, user_id, ip_address, user_agent, was_successful, failure_reason, attempted_at)
         VALUES
            (:username, :subject_hash, :user_id, :ip_address, :user_agent, :was_successful, :failure_reason, NOW())'
    );

    $stmt->execute([
        'username' => $username,
        'subject_hash' => login_subject($username),
        'user_id' => $userId,
        'ip_address' => client_ip_address(),
        'user_agent' => user_agent_string(),
        'was_successful' => $wasSuccessful ? 1 : 0,
        'failure_reason' => $failureReason,
    ]);
}

function clear_failed_login_attempts(string $username): void
{
    $stmt = db()->prepare(
        'DELETE FROM login_attempts
         WHERE subject_hash = :subject_hash
           AND was_successful = 0'
    );
    $stmt->execute(['subject_hash' => login_subject($username)]);
}

function login_user(string $username, string $password): bool
{
    $username = trim($username);

    if (login_is_locked($username)) {
        record_login_attempt($username, false, null, 'temporary_lockout');
        audit_event('login_locked', null, $username, 'Login blocked by temporary lockout.');
        return false;
    }

    $stmt = db()->prepare(
        'SELECT id, username, password_hash, display_name, role, is_active
         FROM users
         WHERE username = :username
         LIMIT 1'
    );
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    if (!$user || (int) $user['is_active'] !== 1) {
        record_login_attempt($username, false, null, 'invalid_user');
        audit_event('login_failed', null, $username, 'Invalid or inactive username.');
        return false;
    }

    if (!password_verify($password, $user['password_hash'])) {
        record_login_attempt($username, false, (int) $user['id'], 'invalid_password');
        audit_event('login_failed', (int) $user['id'], $username, 'Invalid password.');
        return false;
    }

    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        $rehash = db()->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
        $rehash->execute([
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'id' => (int) $user['id'],
        ]);

        audit_event('password_rehashed', (int) $user['id'], $username, 'Password hash was refreshed after successful login.');
    }

    session_regenerate_id(true);

    $_SESSION['user_id'] = (int) $user['id'];

    $update = db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
    $update->execute(['id' => (int) $user['id']]);

    record_login_attempt($username, true, (int) $user['id'], null);
    clear_failed_login_attempts($username);
    audit_event('login_success', (int) $user['id'], $username, 'User signed in.');

    return true;
}

function logout_user(bool $audit = true): void
{
    if ($audit && !empty($_SESSION['user_id'])) {
        try {
            $stmt = db()->prepare('SELECT id, username FROM users WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => (int) $_SESSION['user_id']]);
            $user = $stmt->fetch();

            if ($user) {
                audit_event('logout', (int) $user['id'], $user['username'], 'User signed out.');
            }
        } catch (Throwable $exception) {
            error_log('Carceris logout audit failed: ' . $exception->getMessage());
        }
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            (bool) $params['secure'],
            (bool) $params['httponly']
        );
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

function user_can(array $user, string $permission): bool
{
    $role = $user['role'] ?? 'viewer';

    $permissions = [
        'admin' => [
            'create_entry',
            'view_active_log',
            'view_archive',
            'print_log',
            'send_reports',
            'view_reports',
            'correct_void_entries',
            'view_status',
            'view_audit',
            'manage_users',
            'manage_settings',
            'manage_upgrades',
            'manage_backups',
        ],
        'supervisor' => [
            'create_entry',
            'view_active_log',
            'view_archive',
            'print_log',
            'send_reports',
            'view_reports',
            'correct_void_entries',
            'view_audit',
        ],
        'officer' => [
            'create_entry',
            'view_active_log',
            'view_archive',
            'print_log',
        ],
        'viewer' => [
            'view_archive',
            'print_log',
        ],
    ];

    return in_array($permission, $permissions[$role] ?? [], true);
}
