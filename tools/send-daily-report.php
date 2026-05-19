<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

require __DIR__ . '/../app/bootstrap.php';

try {
    $result = carceris_run_scheduled_daily_report();

    if (($result['status'] ?? '') === 'sent') {
        echo "Carceris scheduled daily report sent.\n";
        echo "Operational date: " . ($result['operational_date'] ?? '') . "\n";
        echo "Delivery ID: " . ($result['delivery_id'] ?? '') . "\n";
        exit(0);
    }

    echo "Carceris scheduled daily report skipped.\n";
    echo "Reason: " . ($result['reason'] ?? 'Unknown') . "\n";
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, "Carceris scheduled daily report failed.\n");
    fwrite(STDERR, "Error: " . $exception->getMessage() . "\n");
    exit(1);
}
