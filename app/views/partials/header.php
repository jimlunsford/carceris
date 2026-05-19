<?php
$user = current_user();
$appName = carceris_header_brand_name();
$appTagline = carceris_header_brand_tagline();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= e($pageTitle ?? $appName) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="application-name" content="<?= e($appName) ?>">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<header class="site-header">
    <div class="site-header__brand">
        <a class="brand-mark" href="/index.php"><?= e($appName) ?></a>
        <span class="brand-tagline"><?= e($appTagline) ?></span>
    </div>

    <?php if ($user): ?>
        <nav class="site-nav" aria-label="Main navigation">
            <a href="/index.php">Active Log</a>
            <a href="/archive.php">Archive</a>
            <a href="/print.php" target="_blank" rel="noopener">Print</a>
            <?php if (user_can($user, 'manage_users') || user_can($user, 'manage_settings') || user_can($user, 'view_audit') || user_can($user, 'manage_upgrades') || user_can($user, 'manage_backups') || user_can($user, 'view_status') || user_can($user, 'view_reports') || user_can($user, 'send_reports')): ?>
                <a href="/admin/index.php">Admin</a>
            <?php endif; ?>
            <form method="post" action="/logout.php" class="site-nav__logout-form">
                <?= csrf_field() ?>
                <button type="submit" class="site-nav__logout-button">Logout</button>
            </form>
        </nav>
    <?php endif; ?>
</header>

<main class="page-shell">
    <?php if (!is_https_request()): ?>
        <div class="flash flash--warning">
            Carceris is not running over HTTPS. Do not use real operational data until HTTPS or equivalent internal transport protection is in place.
        </div>
    <?php endif; ?>

    <?php foreach (flash_get() as $flash): ?>
        <div class="flash flash--<?= e($flash['type']) ?>">
            <?= e($flash['message']) ?>
        </div>
    <?php endforeach; ?>
