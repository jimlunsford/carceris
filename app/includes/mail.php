<?php

declare(strict_types=1);

function carceris_mail_defaults(): array
{
    return [
        'report_smtp_host' => '',
        'report_smtp_port' => '587',
        'report_smtp_encryption' => 'tls',
        'report_smtp_username' => '',
        'report_smtp_password' => '',
        'report_sendmail_path' => '/usr/sbin/sendmail',
    ];
}

function carceris_mail_settings(): array
{
    $settings = carceris_report_delivery_settings();

    foreach (carceris_mail_defaults() as $key => $default) {
        $settings[$key] = setting($key, $default) ?? $default;
    }

    return $settings;
}

function carceris_parse_email_list(string $emails): array
{
    $emails = carceris_normalize_email_list($emails);

    if ($emails === '') {
        return [];
    }

    return array_values(array_filter(array_map('trim', explode(',', $emails))));
}

function carceris_mail_validate_headers_value(string $value): string
{
    return str_replace(["\r", "\n"], '', $value);
}

function carceris_mail_boundary(): string
{
    return 'carceris_' . bin2hex(random_bytes(16));
}

function carceris_mail_has_attachments(array $message): bool
{
    return !empty($message['attachments']) && is_array($message['attachments']);
}

function carceris_mail_content_type_for_body(string $bodyFormat): string
{
    return $bodyFormat === 'html'
        ? 'text/html; charset=UTF-8'
        : 'text/plain; charset=UTF-8';
}

function carceris_mail_build_headers_array(array $message, bool $includeRecipientsAndSubject = false, ?string $boundary = null, bool $includeBccHeader = false): array
{
    $headers = [];

    if ($includeRecipientsAndSubject) {
        $headers[] = 'To: ' . implode(', ', $message['to']);

        if (!empty($message['cc'])) {
            $headers[] = 'Cc: ' . implode(', ', $message['cc']);
        }

        if ($includeBccHeader && !empty($message['bcc'])) {
            $headers[] = 'Bcc: ' . implode(', ', $message['bcc']);
        }

        $headers[] = 'Subject: ' . carceris_mail_validate_headers_value($message['subject']);
        $headers[] = 'Date: ' . date('r');
    } else {
        if (!empty($message['cc'])) {
            $headers[] = 'Cc: ' . implode(', ', $message['cc']);
        }

        if (!empty($message['bcc'])) {
            $headers[] = 'Bcc: ' . implode(', ', $message['bcc']);
        }
    }

    $fromName = carceris_mail_validate_headers_value($message['from_name'] ?? 'Carceris');
    $fromEmail = carceris_mail_validate_headers_value($message['from_email'] ?? '');

    if ($fromEmail !== '') {
        $headers[] = 'From: ' . ($fromName !== '' ? '"' . addcslashes($fromName, '"\\') . '" ' : '') . '<' . $fromEmail . '>';
    }

    $headers[] = 'MIME-Version: 1.0';

    if ($boundary !== null) {
        $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
    } else {
        $headers[] = 'Content-Type: ' . carceris_mail_content_type_for_body((string) ($message['body_format'] ?? 'plain_text'));
    }

    $headers[] = 'X-Mailer: Carceris';

    return $headers;
}

function carceris_mail_build_headers(array $message, string $lineEnding = "\r\n"): string
{
    $boundary = carceris_mail_has_attachments($message)
        ? ($message['_boundary'] ?? carceris_mail_boundary())
        : null;

    return implode($lineEnding, carceris_mail_build_headers_array($message, false, $boundary, true));
}

function carceris_mail_render_body(array $message, ?string $boundary = null): string
{
    $body = (string) ($message['body'] ?? '');

    if ($boundary === null || !carceris_mail_has_attachments($message)) {
        return $body;
    }

    $lineEnding = "\r\n";
    $parts = [];

    $parts[] = '--' . $boundary;
    $parts[] = 'Content-Type: ' . carceris_mail_content_type_for_body((string) ($message['body_format'] ?? 'plain_text'));
    $parts[] = 'Content-Transfer-Encoding: 8bit';
    $parts[] = '';
    $parts[] = $body;

    foreach ($message['attachments'] as $attachment) {
        $filename = carceris_mail_validate_headers_value((string) ($attachment['filename'] ?? 'attachment.txt'));
        $contentType = carceris_mail_validate_headers_value((string) ($attachment['content_type'] ?? 'application/octet-stream'));
        $content = (string) ($attachment['content'] ?? '');

        $parts[] = '--' . $boundary;
        $parts[] = 'Content-Type: ' . $contentType . '; name="' . addcslashes($filename, '"\\') . '"';
        $parts[] = 'Content-Transfer-Encoding: base64';
        $parts[] = 'Content-Disposition: attachment; filename="' . addcslashes($filename, '"\\') . '"';
        $parts[] = '';
        $parts[] = chunk_split(base64_encode($content));
    }

    $parts[] = '--' . $boundary . '--';
    $parts[] = '';

    return implode($lineEnding, $parts);
}

function carceris_mail_build_full_message(array $message, bool $includeBccHeader = false): string
{
    $boundary = carceris_mail_has_attachments($message) ? carceris_mail_boundary() : null;

    if ($boundary !== null) {
        $message['_boundary'] = $boundary;
    }

    $headers = carceris_mail_build_headers_array($message, true, $boundary, $includeBccHeader);
    $body = carceris_mail_render_body($message, $boundary);

    return implode("\r\n", $headers) . "\r\n\r\n" . $body;
}

function carceris_mail_message_from_settings(array $settings, string $subject, string $body, string $bodyFormat = 'plain_text', ?string $testRecipient = null): array
{
    $to = $testRecipient !== null && trim($testRecipient) !== ''
        ? carceris_parse_email_list($testRecipient)
        : carceris_parse_email_list($settings['report_recipients_to'] ?? '');

    $cc = $testRecipient !== null && trim($testRecipient) !== ''
        ? []
        : carceris_parse_email_list($settings['report_recipients_cc'] ?? '');

    $bcc = $testRecipient !== null && trim($testRecipient) !== ''
        ? []
        : carceris_parse_email_list($settings['report_recipients_bcc'] ?? '');

    if (!$to) {
        throw new RuntimeException('At least one To recipient is required.');
    }

    $fromEmail = trim((string) ($settings['report_from_email'] ?? ''));

    if ($fromEmail === '') {
        throw new RuntimeException('From Email is required before sending mail.');
    }

    if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('From Email is invalid.');
    }

    return [
        'to' => $to,
        'cc' => $cc,
        'bcc' => $bcc,
        'from_name' => trim((string) ($settings['report_from_name'] ?? 'Carceris')),
        'from_email' => $fromEmail,
        'subject' => $subject,
        'body' => $body,
        'body_format' => $bodyFormat,
        'attachments' => [],
    ];
}

function carceris_mail_send(array $settings, array $message): array
{
    $transport = (string) ($settings['report_mail_transport'] ?? 'manual_only');

    return match ($transport) {
        'php_mail' => carceris_mail_send_php_mail($message),
        'smtp' => carceris_mail_send_smtp($settings, $message),
        'smtp_phpmailer' => carceris_mail_send_phpmailer($settings, $message),
        'sendmail' => carceris_mail_send_sendmail($settings, $message),
        'manual_only' => throw new RuntimeException('Mail transport is set to Manual Export Only. Choose PHP Mail, SMTP, or Sendmail to send email.'),
        default => throw new RuntimeException('Unsupported mail transport: ' . $transport),
    };
}

function carceris_mail_send_php_mail(array $message): array
{
    $boundary = carceris_mail_has_attachments($message) ? carceris_mail_boundary() : null;

    if ($boundary !== null) {
        $message['_boundary'] = $boundary;
    }

    $headers = implode("\r\n", carceris_mail_build_headers_array($message, false, $boundary, true));
    $to = implode(', ', $message['to']);
    $subject = carceris_mail_validate_headers_value($message['subject']);
    $body = carceris_mail_render_body($message, $boundary);

    $sent = mail($to, $subject, $body, $headers);

    if (!$sent) {
        throw new RuntimeException('PHP mail() returned false.');
    }

    return [
        'transport' => 'php_mail',
        'message' => 'PHP mail accepted the message.',
    ];
}

function carceris_mail_send_sendmail(array $settings, array $message): array
{
    $path = trim((string) ($settings['report_sendmail_path'] ?? '/usr/sbin/sendmail'));

    if ($path === '') {
        throw new RuntimeException('Sendmail path is required.');
    }

    if (!is_executable($path)) {
        throw new RuntimeException('Sendmail path is not executable: ' . $path);
    }

    $recipients = array_merge($message['to'], $message['cc'] ?? [], $message['bcc'] ?? []);

    if (!$recipients) {
        throw new RuntimeException('At least one recipient is required.');
    }

    $command = escapeshellcmd($path) . ' -i';

    foreach ($recipients as $recipient) {
        $command .= ' ' . escapeshellarg($recipient);
    }

    $process = proc_open($command, [
        0 => ['pipe', 'w'],
        1 => ['pipe', 'r'],
        2 => ['pipe', 'r'],
    ], $pipes);

    if (!is_resource($process)) {
        throw new RuntimeException('Could not start sendmail process.');
    }

    $payload = carceris_mail_build_full_message($message, false);

    fwrite($pipes[0], $payload);
    fclose($pipes[0]);

    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);

    if ($exitCode !== 0) {
        throw new RuntimeException('Sendmail failed with exit code ' . $exitCode . '. ' . trim((string) $stderr));
    }

    return [
        'transport' => 'sendmail',
        'message' => trim((string) $stdout) !== '' ? trim((string) $stdout) : 'Sendmail accepted the message.',
    ];
}

function carceris_smtp_read_response($socket): array
{
    $lines = [];

    while (($line = fgets($socket, 515)) !== false) {
        $lines[] = rtrim($line, "\r\n");

        if (preg_match('/^\d{3}\s/', $line)) {
            break;
        }
    }

    if (!$lines) {
        throw new RuntimeException('SMTP server did not respond.');
    }

    $last = end($lines);
    $code = (int) substr((string) $last, 0, 3);

    return [
        'code' => $code,
        'lines' => $lines,
        'text' => implode("\n", $lines),
    ];
}

function carceris_smtp_expect($socket, array $expectedCodes, string $context): array
{
    $response = carceris_smtp_read_response($socket);

    if (!in_array($response['code'], $expectedCodes, true)) {
        throw new RuntimeException('SMTP error during ' . $context . ': ' . $response['text']);
    }

    return $response;
}

function carceris_smtp_write($socket, string $command): void
{
    fwrite($socket, $command . "\r\n");
}

function carceris_smtp_escape_data(string $data): string
{
    $data = str_replace(["\r\n", "\r"], "\n", $data);
    $lines = explode("\n", $data);

    foreach ($lines as &$line) {
        if (str_starts_with($line, '.')) {
            $line = '.' . $line;
        }
    }

    return implode("\r\n", $lines);
}

function carceris_mail_send_smtp(array $settings, array $message): array
{
    $host = trim((string) ($settings['report_smtp_host'] ?? ''));
    $port = (int) ($settings['report_smtp_port'] ?? 587);
    $encryption = strtolower(trim((string) ($settings['report_smtp_encryption'] ?? 'tls')));
    $username = trim((string) ($settings['report_smtp_username'] ?? ''));
    $password = (string) ($settings['report_smtp_password'] ?? '');

    if ($host === '') {
        throw new RuntimeException('SMTP host is required.');
    }

    if ($port <= 0 || $port > 65535) {
        throw new RuntimeException('SMTP port is invalid.');
    }

    if (!in_array($encryption, ['none', 'tls', 'ssl'], true)) {
        throw new RuntimeException('SMTP encryption must be none, TLS, or SSL.');
    }

    $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $socket = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT);

    if (!$socket) {
        throw new RuntimeException('Could not connect to SMTP server: ' . $errstr);
    }

    stream_set_timeout($socket, 20);

    try {
        carceris_smtp_expect($socket, [220], 'connect');

        $serverName = $_SERVER['SERVER_NAME'] ?? 'localhost';

        carceris_smtp_write($socket, 'EHLO ' . $serverName);
        carceris_smtp_expect($socket, [250], 'EHLO');

        if ($encryption === 'tls') {
            carceris_smtp_write($socket, 'STARTTLS');
            carceris_smtp_expect($socket, [220], 'STARTTLS');

            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Could not enable SMTP TLS encryption.');
            }

            carceris_smtp_write($socket, 'EHLO ' . $serverName);
            carceris_smtp_expect($socket, [250], 'EHLO after STARTTLS');
        }

        if ($username !== '') {
            carceris_smtp_write($socket, 'AUTH LOGIN');
            carceris_smtp_expect($socket, [334], 'AUTH LOGIN');

            carceris_smtp_write($socket, base64_encode($username));
            carceris_smtp_expect($socket, [334], 'SMTP username');

            carceris_smtp_write($socket, base64_encode($password));
            carceris_smtp_expect($socket, [235], 'SMTP password');
        }

        carceris_smtp_write($socket, 'MAIL FROM:<' . $message['from_email'] . '>');
        carceris_smtp_expect($socket, [250], 'MAIL FROM');

        $recipients = array_merge($message['to'], $message['cc'] ?? [], $message['bcc'] ?? []);

        foreach ($recipients as $recipient) {
            carceris_smtp_write($socket, 'RCPT TO:<' . $recipient . '>');
            carceris_smtp_expect($socket, [250, 251], 'RCPT TO ' . $recipient);
        }

        carceris_smtp_write($socket, 'DATA');
        carceris_smtp_expect($socket, [354], 'DATA');

        $payload = carceris_mail_build_full_message($message, false);

        carceris_smtp_write($socket, carceris_smtp_escape_data($payload) . "\r\n.");
        carceris_smtp_expect($socket, [250], 'message body');

        carceris_smtp_write($socket, 'QUIT');

        return [
            'transport' => 'smtp',
            'message' => 'SMTP server accepted the message.',
        ];
    } finally {
        fclose($socket);
    }
}


function carceris_mail_send_phpmailer(array $settings, array $message): array
{
    if (!class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) {
        throw new RuntimeException(
            'PHPMailer is not installed. Install phpmailer/phpmailer with Composer or choose Native SMTP, PHP Mail, or Sendmail.'
        );
    }

    $mailerClass = '\\PHPMailer\\PHPMailer\\PHPMailer';
    $mailer = new $mailerClass(true);

    $host = trim((string) ($settings['report_smtp_host'] ?? ''));
    $port = (int) ($settings['report_smtp_port'] ?? 587);
    $encryption = strtolower(trim((string) ($settings['report_smtp_encryption'] ?? 'tls')));
    $username = trim((string) ($settings['report_smtp_username'] ?? ''));
    $password = (string) ($settings['report_smtp_password'] ?? '');

    if ($host === '') {
        throw new RuntimeException('SMTP host is required.');
    }

    $mailer->isSMTP();
    $mailer->Host = $host;
    $mailer->Port = $port;
    $mailer->CharSet = 'UTF-8';

    if ($encryption === 'tls') {
        $mailer->SMTPSecure = 'tls';
    } elseif ($encryption === 'ssl') {
        $mailer->SMTPSecure = 'ssl';
    }

    if ($username !== '') {
        $mailer->SMTPAuth = true;
        $mailer->Username = $username;
        $mailer->Password = $password;
    }

    $mailer->setFrom($message['from_email'], $message['from_name'] ?? 'Carceris');

    foreach ($message['to'] ?? [] as $recipient) {
        $mailer->addAddress($recipient);
    }

    foreach ($message['cc'] ?? [] as $recipient) {
        $mailer->addCC($recipient);
    }

    foreach ($message['bcc'] ?? [] as $recipient) {
        $mailer->addBCC($recipient);
    }

    $mailer->Subject = carceris_mail_validate_headers_value((string) ($message['subject'] ?? 'Carceris'));

    if (($message['body_format'] ?? 'plain_text') === 'html') {
        $mailer->isHTML(true);
        $mailer->Body = (string) ($message['body'] ?? '');
        $mailer->AltBody = strip_tags((string) ($message['body'] ?? ''));
    } else {
        $mailer->isHTML(false);
        $mailer->Body = (string) ($message['body'] ?? '');
    }

    foreach ($message['attachments'] ?? [] as $attachment) {
        $mailer->addStringAttachment(
            (string) ($attachment['content'] ?? ''),
            (string) ($attachment['filename'] ?? 'attachment.txt'),
            'base64',
            (string) ($attachment['content_type'] ?? 'application/octet-stream')
        );
    }

    $mailer->send();

    return [
        'transport' => 'smtp_phpmailer',
        'message' => 'PHPMailer SMTP accepted the message.',
    ];
}


function carceris_record_mail_test_delivery(array $settings, array $message, array $user, string $status, ?string $errorMessage = null): int
{
    $stmt = db()->prepare(
        'INSERT INTO report_test_emails
            (transport, body_format, recipient_to, recipient_cc, recipient_bcc, subject, status, error_message, sent_at, triggered_by, created_at)
         VALUES
            (:transport, :body_format, :recipient_to, :recipient_cc, :recipient_bcc, :subject, :status, :error_message, :sent_at, :triggered_by, NOW())'
    );

    $stmt->execute([
        'transport' => (string) ($settings['report_mail_transport'] ?? 'manual_only'),
        'body_format' => (string) ($message['body_format'] ?? 'plain_text'),
        'recipient_to' => implode(', ', $message['to'] ?? []),
        'recipient_cc' => implode(', ', $message['cc'] ?? []),
        'recipient_bcc' => implode(', ', $message['bcc'] ?? []),
        'subject' => (string) ($message['subject'] ?? 'Carceris Test Email'),
        'status' => in_array($status, ['sent', 'failed', 'pending'], true) ? $status : 'pending',
        'error_message' => $errorMessage,
        'sent_at' => $status === 'sent' ? current_datetime() : null,
        'triggered_by' => (int) $user['id'],
    ]);

    return (int) db()->lastInsertId();
}
