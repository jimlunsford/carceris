<?php
$appName = carceris_header_brand_name();
$printLogTitle = carceris_print_log_title();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Print Log | <?= e($appName) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            color: #111;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            margin: 24px;
        }

        .print-header {
            border-bottom: 2px solid #111;
            margin-bottom: 18px;
            padding-bottom: 12px;
        }

        .print-header h1 {
            font-size: 22px;
            margin: 0 0 4px;
        }

        .print-header p {
            margin: 2px 0;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        th,
        td {
            border: 1px solid #555;
            padding: 6px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #eee;
            font-weight: 700;
        }

        .no-print {
            margin-bottom: 16px;
        }

        .print-noscript {
            color: #555;
            margin-left: 10px;
        }

        @media print {
            .no-print {
                display: none;
            }

            body {
                margin: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button type="button" id="print-page-button">Print</button>
        <noscript><span class="print-noscript">JavaScript is disabled. Use your browser print command.</span></noscript>
    </div>

    <header class="print-header">
        <h1><?= e($printLogTitle) ?></h1>
        <p><strong>System:</strong> <?= e($appName) ?></p>
        <p><strong>Operational Period:</strong> <?= e($logDay['log_label']) ?></p>
        <p><strong>Status:</strong> <?= e(ucfirst($logDay['status'])) ?></p>
        <p><strong>Printed:</strong> <?= e(carceris_format_datetime(new DateTimeImmutable('now'))) ?></p>
    </header>

    <?php if (!$entries): ?>
        <p>No log entries were recorded for this operational period.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Category</th>
                    <th>Priority</th>
                    <th>Location</th>
                    <th>Inmate</th>
                    <th>Entry</th>
                    <th>Officer</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($entries as $entry): ?>
                    <tr>
                        <td><?= e(format_log_time($entry['event_time'])) ?></td>
                        <td><?= e($entry['category']) ?></td>
                        <td><?= e(ucfirst($entry['priority'])) ?><?php if (entry_status_label($entry) !== 'Active'): ?><br><strong><?= e(entry_status_label($entry)) ?></strong><?php endif; ?></td>
                        <td><?= e($entry['location'] ?? '') ?></td>
                        <td><?= e($entry['inmate_name'] ?? '') ?></td>
                        <td>
                            <?= nl2br(e($entry['entry_text'])) ?>
                            <?php $lateNote = entry_late_note($entry); ?>
                            <?php if ($lateNote !== ''): ?>
                                <br><em><?= e($lateNote) ?></em>
                            <?php endif; ?>
                            <?php $statusNote = entry_status_note($entry); ?>
                            <?php if ($statusNote !== ''): ?>
                                <br><strong><?= e($statusNote) ?></strong>
                            <?php endif; ?>
                        </td>
                        <td><?= e($entry['created_by_name'] ?? 'Unknown') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <script src="/assets/js/print.js" defer></script>
</body>
</html>
