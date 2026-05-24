<?php

declare(strict_types=1);

require __DIR__ . '/../../app/bootstrap.php';

$user = require_login();

if (!user_can($user, 'manage_settings')) {
    http_response_code(403);
    exit('You do not have permission to manage log delivery settings.');
}

if (request_method() === 'POST') {
    csrf_require();

    $action = post_string('action', 60);

    try {
        if ($action === 'save_settings') {
            $transportOptions = carceris_report_transport_options();
            $bodyFormatOptions = carceris_report_body_format_options();
            $attachmentFormatOptions = carceris_report_attachment_format_options();

            $enabled = isset($_POST['report_delivery_enabled']) && $_POST['report_delivery_enabled'] === '1' ? '1' : '0';
            $sendTime = carceris_time_from_clock_format_post('report_send_time', 'Scheduled send time');
            $transport = carceris_validate_option(post_string('report_mail_transport', 40), $transportOptions, 'Mail transport');
            $bodyFormat = carceris_validate_option(post_string('report_body_format', 40), $bodyFormatOptions, 'Email body format');
            $attachmentFormat = carceris_validate_option(post_string('report_attachment_format', 40), $attachmentFormatOptions, 'Attachment format');
            $fromName = post_string('report_from_name', 120);
            $fromEmail = post_string('report_from_email', 190);
            $smtpHost = post_string('report_smtp_host', 190);
            $smtpPort = (int) ($_POST['report_smtp_port'] ?? 587);
            $smtpEncryption = post_string('report_smtp_encryption', 10);
            $smtpUsername = post_string('report_smtp_username', 190);
            $smtpPassword = (string) ($_POST['report_smtp_password'] ?? '');
            $sendmailPath = post_string('report_sendmail_path', 255);

            if ($fromEmail !== '' && !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('From email address is invalid.');
            }

            if ($smtpPort <= 0 || $smtpPort > 65535) {
                throw new RuntimeException('Native SMTP port is invalid.');
            }

            if (!in_array($smtpEncryption, ['none', 'tls', 'ssl'], true)) {
                throw new RuntimeException('Native SMTP encryption is invalid.');
            }

            $recipientsTo = carceris_normalize_email_list(post_string('report_recipients_to', 4000));
            $recipientsCc = carceris_normalize_email_list(post_string('report_recipients_cc', 4000));
            $recipientsBcc = carceris_normalize_email_list(post_string('report_recipients_bcc', 4000));

            $updates = [
                'report_delivery_enabled' => $enabled,
                'report_send_time' => $sendTime,
                'report_mail_transport' => $transport,
                'report_body_format' => $bodyFormat,
                'report_attachment_format' => $attachmentFormat,
                'report_from_name' => $fromName,
                'report_from_email' => $fromEmail,
                'report_recipients_to' => $recipientsTo,
                'report_recipients_cc' => $recipientsCc,
                'report_recipients_bcc' => $recipientsBcc,
                'report_smtp_host' => $smtpHost,
                'report_smtp_port' => (string) $smtpPort,
                'report_smtp_encryption' => $smtpEncryption,
                'report_smtp_username' => $smtpUsername,
                'report_sendmail_path' => $sendmailPath,
            ];

            if ($smtpPassword !== '') {
                $updates['report_smtp_password'] = $smtpPassword;
            }

            foreach ($updates as $key => $value) {
                setting_update($key, $value);
            }

            audit_event(
                'report_delivery_settings_updated',
                (int) $user['id'],
                $user['username'] ?? null,
                'Updated log delivery settings. Transport: ' . $transport . '. Body: ' . $bodyFormat . '. Attachment: ' . $attachmentFormat . '. Enabled: ' . $enabled . '.'
            );

            flash_set('success', 'Log delivery settings saved.');
            redirect('/admin/report-delivery.php');
        }



        if ($action === 'send_test_email') {
            $settings = carceris_mail_settings();
            $testRecipient = post_string('test_recipient', 190);

            if ($testRecipient !== '' && !filter_var($testRecipient, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Test recipient email address is invalid.');
            }

            $subject = 'Carceris Test Email - ' . carceris_report_facility_name();
            $body = "This is a Carceris test email.\n\n";
            $body .= "Facility: " . carceris_report_facility_name() . "\n";
            $body .= "Transport: " . ($settings['report_mail_transport'] ?? 'manual_only') . "\n";
            $body .= "Generated: " . carceris_format_datetime(new DateTimeImmutable('now')) . "\n\n";
            $body .= "No daily log report was sent with this test message.\n";

            $message = carceris_mail_message_from_settings($settings, $subject, $body, 'plain_text', $testRecipient !== '' ? $testRecipient : null);

            try {
                $transport = (string) ($settings['report_mail_transport'] ?? 'manual_only');

                if (!carceris_mail_transport_available($transport, $settings)) {
                    throw new RuntimeException(carceris_mail_transport_unavailable_message($transport, $settings));
                }

                carceris_mail_send($settings, $message);

                $deliveryId = carceris_record_mail_test_delivery($settings, $message, $user, 'sent');

                audit_event(
                    'report_test_email_sent',
                    (int) $user['id'],
                    $user['username'] ?? null,
                    'Sent log delivery test email using transport: ' . ($settings['report_mail_transport'] ?? 'manual_only') . '.',
                    'report_test_email',
                    $deliveryId
                );

                flash_set('success', 'Test email sent.');
            } catch (Throwable $exception) {
                $deliveryId = carceris_record_mail_test_delivery($settings, $message, $user, 'failed', $exception->getMessage());

                audit_event(
                    'report_test_email_failed',
                    (int) $user['id'],
                    $user['username'] ?? null,
                    'Report delivery test email failed: ' . $exception->getMessage(),
                    'report_test_email',
                    $deliveryId
                );

                flash_set('error', 'Test email failed: ' . $exception->getMessage());
            }

            redirect('/admin/report-delivery.php');
        }

        if ($action === 'regenerate_cron_key') {
            $newKey = carceris_generate_cron_key();
            setting_update('report_cron_key', $newKey);

            audit_event(
                'report_cron_key_regenerated',
                (int) $user['id'],
                $user['username'] ?? null,
                'Regenerated log delivery cron key.'
            );

            flash_set('success', 'Cron key regenerated.');
            redirect('/admin/report-delivery.php');
        }

        flash_set('error', 'Unknown log delivery action.');
    } catch (Throwable $exception) {
        flash_set('error', $exception->getMessage());
    }

    redirect('/admin/report-delivery.php');
}

$settings = carceris_report_delivery_settings();
$clockFormat = carceris_clock_format();
$reportSendTimeParts = carceris_time_display_parts($settings['report_send_time'] ?? '05:00');
$transportOptions = carceris_report_transport_options();
$bodyFormatOptions = carceris_report_body_format_options();
$attachmentFormatOptions = carceris_report_attachment_format_options();
$deliveries = carceris_recent_report_deliveries(25);
$mailCapabilities = carceris_mail_capabilities($settings);
$selectedTransport = (string) ($settings['report_mail_transport'] ?? 'manual_only');
$selectedTransportAvailable = carceris_mail_transport_available($selectedTransport, $settings);
$selectedTransportWarning = $selectedTransportAvailable ? '' : carceris_mail_transport_unavailable_message($selectedTransport, $settings);

$scheme = is_https_request() ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'example.com';
$cronUrlPreview = $scheme . '://' . $host . '/cron/send-daily-report.php?key=' . $settings['report_cron_key'];

audit_event(
    'report_delivery_settings_viewed',
    (int) $user['id'],
    $user['username'] ?? null,
    'Viewed log delivery settings.'
);

require __DIR__ . '/../../app/views/admin/report-delivery.php';
