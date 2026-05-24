<?php

declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$key = get_string('key', 128);
$expected = setting('report_cron_key', '') ?? '';

header('Content-Type: text/plain; charset=utf-8');

if ($expected === '' || !hash_equals($expected, $key)) {
    http_response_code(403);
    echo "Invalid cron key.\n";
    exit;
}

try {
    $result = carceris_run_scheduled_daily_report();

    if (($result['status'] ?? '') === 'sent') {
        echo "Carceris scheduled daily report sent.\n";
        echo "Operational date: " . ($result['operational_date'] ?? '') . "\n";
        echo "Delivery ID: " . ($result['delivery_id'] ?? '') . "\n";
        exit;
    }

    echo "Carceris scheduled daily report skipped.\n";
    echo "Reason: " . ($result['reason'] ?? 'Unknown') . "\n";
} catch (Throwable $exception) {
    error_log('Carceris scheduled daily report failed: ' . $exception->getMessage());

    http_response_code(500);
    echo "Carceris scheduled daily report failed. Check the Carceris audit log or server error log for details.\n";
}
