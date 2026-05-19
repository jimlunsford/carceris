<?php

/*
 * Carceris
 * Copyright (C) 2026 Jim Lunsford.
 * SPDX-License-Identifier: AGPL-3.0-only
 */

declare(strict_types=1);

$config = require __DIR__ . '/config/config.php';

require_once __DIR__ . '/includes/error.php';

function carceris_project_root(): string
{
    return dirname(__DIR__);
}

function carceris_is_installed(): bool
{
    return is_file(carceris_project_root() . '/storage/installed.lock')
        && is_file(__DIR__ . '/config/config.local.php');
}

function carceris_install_url(): string
{
    return '/install/index.php';
}

function carceris_secure_cookie_request(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '') === 'on');
}

function carceris_send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; base-uri 'self'; frame-ancestors 'self'; form-action 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; object-src 'none'");
}

carceris_send_security_headers();

if (!defined('CARCERIS_INSTALLER') && !carceris_is_installed()) {
    header('Location: ' . carceris_install_url());
    exit;
}

$composerAutoload = carceris_project_root() . '/vendor/autoload.php';

if (is_file($composerAutoload)) {
    require_once $composerAutoload;
}

date_default_timezone_set($config['app']['timezone'] ?? 'America/Indiana/Indianapolis');

session_name($config['app']['session_name'] ?? 'carceris_session');

$secureCookie = carceris_secure_cookie_request();

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $secureCookie,
    'httponly' => true,
    'samesite' => 'Strict',
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/audit.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/logs.php';
require_once __DIR__ . '/includes/users.php';
require_once __DIR__ . '/includes/upgrade.php';
require_once __DIR__ . '/includes/reports.php';
require_once __DIR__ . '/includes/pdf.php';
require_once __DIR__ . '/includes/mail.php';
require_once __DIR__ . '/includes/backup.php';

if (!defined('CARCERIS_INSTALLER') && carceris_is_installed()) {
    carceris_enforce_https_if_enabled();

    if (carceris_is_maintenance_mode() && !defined('CARCERIS_ALLOW_MAINTENANCE')) {
        carceris_render_maintenance_and_exit();
    }
}
