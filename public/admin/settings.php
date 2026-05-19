<?php

declare(strict_types=1);

require __DIR__ . '/../../app/bootstrap.php';

$user = require_login();

if (!user_can($user, 'manage_settings')) {
    http_response_code(403);
    exit('You do not have permission to manage settings.');
}

if (request_method() === 'POST') {
    csrf_require();

    $action = post_string('action', 60);



    if ($action === 'update_header_branding') {
        $headerBrandName = post_string('app_name', 80);
        $headerBrandTagline = post_string('app_tagline', 160);

        if ($headerBrandName === '') {
            flash_set('error', 'Header name is required.');
            redirect('/admin/settings.php');
        }

        if ($headerBrandTagline === '') {
            flash_set('error', 'Header tagline is required.');
            redirect('/admin/settings.php');
        }

        setting_update('app_name', $headerBrandName);
        setting_update('app_tagline', $headerBrandTagline);

        audit_event(
            'setting_updated',
            (int) $user['id'],
            $user['username'] ?? null,
            'Updated header branding.'
        );

        flash_set('success', 'Header branding updated.');
        redirect('/admin/settings.php');
    }



    if ($action === 'update_login_branding') {
        $loginName = post_string('login_name', 80);
        $loginTagline = post_string('login_tagline', 160);

        if ($loginName === '') {
            flash_set('error', 'Login name is required.');
            redirect('/admin/settings.php');
        }

        if ($loginTagline === '') {
            flash_set('error', 'Login tagline is required.');
            redirect('/admin/settings.php');
        }

        setting_update('login_name', $loginName);
        setting_update('login_tagline', $loginTagline);

        audit_event(
            'setting_updated',
            (int) $user['id'],
            $user['username'] ?? null,
            'Updated login branding.'
        );

        flash_set('success', 'Login branding updated.');
        redirect('/admin/settings.php');
    }

    if ($action === 'update_print_branding') {
        $printLogTitle = post_string('print_log_title', 120);

        if ($printLogTitle === '') {
            flash_set('error', 'Print log title is required.');
            redirect('/admin/settings.php');
        }

        setting_update('print_log_title', $printLogTitle);

        audit_event(
            'setting_updated',
            (int) $user['id'],
            $user['username'] ?? null,
            'Updated print log title.'
        );

        flash_set('success', 'Print log title updated.');
        redirect('/admin/settings.php');
    }


    if ($action === 'update_filename_pattern') {
        $filenamePattern = carceris_normalize_daily_log_filename_pattern(post_string('daily_log_filename_pattern', 120));

        if ($filenamePattern === '') {
            flash_set('error', 'Daily log filename pattern is required.');
            redirect('/admin/settings.php');
        }

        setting_update('daily_log_filename_pattern', $filenamePattern);

        audit_event(
            'setting_updated',
            (int) $user['id'],
            $user['username'] ?? null,
            'Updated daily log filename pattern to ' . $filenamePattern . '.'
        );

        flash_set('success', 'Daily log filename pattern updated.');
        redirect('/admin/settings.php');
    }

    if ($action === 'update_operational_day') {
        $dailyLogStartTime = carceris_time_from_clock_format_post(
            'daily_log_start_time',
            'Operational day start time'
        );

        setting_update('daily_log_start_time', $dailyLogStartTime);

        audit_event(
            'setting_updated',
            (int) $user['id'],
            $user['username'] ?? null,
            'Updated operational day start time to ' . $dailyLogStartTime . '.'
        );

        flash_set('success', 'Operational day settings updated.');
        redirect('/admin/settings.php');
    }

    if ($action === 'update_clock_format') {
        $clockFormat = post_string('clock_format', 2);

        if (!in_array($clockFormat, ['12', '24'], true)) {
            flash_set('error', 'Invalid clock format.');
            redirect('/admin/settings.php');
        }

        setting_update('clock_format', $clockFormat);

        audit_event(
            'setting_updated',
            (int) $user['id'],
            $user['username'] ?? null,
            'Updated clock format to ' . $clockFormat . '-hour.'
        );

        flash_set('success', 'Clock format updated.');
        redirect('/admin/settings.php');
    }


    if ($action === 'update_log_visibility') {
        $showVoidedEntries = post_string('show_voided_entries', 1);

        if (!in_array($showVoidedEntries, ['0', '1'], true)) {
            flash_set('error', 'Invalid voided entry visibility setting.');
            redirect('/admin/settings.php');
        }

        setting_update('show_voided_entries', $showVoidedEntries);

        audit_event(
            'setting_updated',
            (int) $user['id'],
            $user['username'] ?? null,
            'Updated show voided entries setting to ' . ($showVoidedEntries === '1' ? 'yes' : 'no') . '.'
        );

        flash_set('success', 'Log visibility settings updated.');
        redirect('/admin/settings.php');
    }

    flash_set('error', 'Unknown settings action.');
    redirect('/admin/settings.php');
}

$headerBrandName = carceris_header_brand_name();
$headerBrandTagline = carceris_header_brand_tagline();
$loginBrandName = carceris_login_brand_name();
$loginBrandTagline = carceris_login_brand_tagline();
$printLogTitle = carceris_print_log_title();
$dailyLogFilenamePattern = carceris_daily_log_filename_pattern();
$clockFormat = carceris_clock_format();
$dailyLogStartTime = carceris_operational_day_start_time();
$dailyLogStartTimeParts = carceris_time_display_parts($dailyLogStartTime);
$showVoidedEntries = carceris_show_voided_entries() ? '1' : '0';

audit_event(
    'settings_viewed',
    (int) $user['id'],
    $user['username'] ?? null,
    'Viewed settings page.'
);

require __DIR__ . '/../../app/views/admin/settings.php';
