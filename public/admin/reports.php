<?php

declare(strict_types=1);

require __DIR__ . '/../../app/bootstrap.php';

$user = require_login();

if (!user_can($user, 'view_reports') && !user_can($user, 'send_reports')) {
    http_response_code(403);
    exit('You do not have permission to view Daily Logs.');
}

$logDeliverySettings = carceris_mail_settings();
$logDeliveryConfigStatus = carceris_log_delivery_config_status($logDeliverySettings);
$logDeliveryAvailable = (bool) $logDeliveryConfigStatus['available'];

if (request_method() === 'POST') {
    csrf_require();

    $action = post_string('action', 60);

    if ($action === 'send_log_now') {
        if (!user_can($user, 'send_reports')) {
            http_response_code(403);
            exit('You do not have permission to send daily logs.');
        }

        if (!$logDeliveryAvailable) {
            flash_set('error', carceris_log_delivery_unavailable_message($user, $logDeliverySettings));
            redirect('/admin/reports.php');
        }

        $logDayId = (int) ($_POST['log_day_id'] ?? 0);

        if ($logDayId <= 0) {
            flash_set('error', 'Daily log selection is required.');
            redirect('/admin/reports.php');
        }

        try {
            $result = carceris_send_log_day_report_by_id($logDayId, $user, 'manual');

            flash_set(
                'success',
                'Completed daily log sent. Operational date: ' . $result['operational_date'] . '.'
            );
        } catch (Throwable $exception) {
            flash_set('error', 'Completed daily log send failed: ' . $exception->getMessage());
        }

        redirect('/admin/reports.php');
    }

    if ($action === 'send_previous_log_now') {
        if (!user_can($user, 'send_reports')) {
            http_response_code(403);
            exit('You do not have permission to send daily logs.');
        }

        if (!$logDeliveryAvailable) {
            flash_set('error', carceris_log_delivery_unavailable_message($user, $logDeliverySettings));
            redirect('/admin/reports.php');
        }

        try {
            $result = carceris_send_previous_log_report_now($user, 'manual');

            flash_set(
                'success',
                'Previous completed daily log sent. Operational date: ' . $result['operational_date'] . '.'
            );
        } catch (Throwable $exception) {
            flash_set('error', 'Previous completed daily log send failed: ' . $exception->getMessage());
        }

        redirect('/admin/reports.php');
    }

    if ($action === 'resend_failed_delivery') {
        if (!user_can($user, 'send_reports')) {
            http_response_code(403);
            exit('You do not have permission to resend daily logs.');
        }

        if (!$logDeliveryAvailable) {
            flash_set('error', carceris_log_delivery_unavailable_message($user, $logDeliverySettings));
            redirect('/admin/reports.php');
        }

        $deliveryId = (int) ($_POST['delivery_id'] ?? 0);
        $delivery = carceris_report_email_delivery_by_id($deliveryId);

        if (!$delivery || ($delivery['status'] ?? '') !== 'failed' || empty($delivery['log_day_id'])) {
            flash_set('error', 'Failed delivery record was not found.');
            redirect('/admin/reports.php');
        }

        try {
            $result = carceris_send_log_day_report_by_id((int) $delivery['log_day_id'], $user, 'manual');

            audit_event(
                'daily_log_failed_delivery_resent',
                (int) $user['id'],
                $user['username'] ?? null,
                'Resent failed daily log delivery. Original delivery ID: ' . $deliveryId . '. Operational date: ' . $result['operational_date'] . '.',
                'report_email_delivery',
                (int) $result['delivery_id']
            );

            flash_set('success', 'Failed daily log delivery resent. Operational date: ' . $result['operational_date'] . '.');
        } catch (Throwable $exception) {
            flash_set('error', 'Resend failed: ' . $exception->getMessage());
        }

        redirect('/admin/reports.php');
    }

    flash_set('error', 'Unknown Daily Logs action.');
    redirect('/admin/reports.php');
}

$recentCompletedLogs = carceris_recent_completed_log_days(10);
$logDeliverySummaries = [];

foreach ($recentCompletedLogs as $logDay) {
    $logDeliverySummaries[(int) $logDay['id']] = carceris_delivery_summary_for_log_day((int) $logDay['id']);
}

$failedDeliveries = carceris_recent_failed_log_deliveries(10);
$scheduledDeliveries = carceris_recent_scheduled_log_deliveries(10);
$deliveries = carceris_recent_report_deliveries(25);

$showSendControls = user_can($user, 'send_reports') && $logDeliveryAvailable;
$showLogDeliveryNotice = !$logDeliveryAvailable;
$showFailedSendsPanel = $logDeliveryAvailable || !empty($failedDeliveries);
$showScheduledDeliveryPanel = $logDeliveryAvailable || !empty($scheduledDeliveries);

audit_event(
    'daily_logs_dashboard_viewed',
    (int) $user['id'],
    $user['username'] ?? null,
    'Viewed Daily Logs operations dashboard.'
);

require __DIR__ . '/../../app/views/admin/reports.php';
