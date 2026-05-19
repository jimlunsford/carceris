<?php $pageTitle = 'Daily Logs | Carceris'; ?>
<?php require __DIR__ . '/../partials/header.php'; ?>

<section class="page-heading">
    <div>
        <h1>Daily Logs</h1>
        <p>Operational dashboard for completed logs, downloads, manual sends, failed sends, and scheduled delivery history.</p>
    </div>
</section>

<?php if ($showLogDeliveryNotice): ?>
    <section class="panel delivery-setup-notice">
        <h2>Log Delivery Not Available</h2>
        <p><?= e(carceris_log_delivery_unavailable_message($user, $logDeliverySettings)) ?></p>

        <?php if (user_can($user, 'manage_settings')): ?>
            <a class="button-link" href="/admin/report-delivery.php">Configure Log Delivery</a>
        <?php endif; ?>
    </section>
<?php endif; ?>

<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Recent Completed Logs</h2>
            <p>Open or download completed daily logs without going through the Archive workflow<?= $showSendControls ? ', or send them through configured Log Delivery' : '' ?>.</p>
        </div>
    </div>

    <?php if (!$recentCompletedLogs): ?>
        <p class="empty-state">No completed daily logs were found yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="log-table daily-logs-dashboard-table">
                <thead>
                    <tr>
                        <th>Operational Date</th>
                        <th>Log</th>
                        <th>Entries</th>
                        <th>Delivery Status</th>
                        <th>Quick Downloads</th>
                        <?php if ($showSendControls): ?>
                            <th>Send</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentCompletedLogs as $logDay): ?>
                        <?php
                            $summary = $logDeliverySummaries[(int) $logDay['id']] ?? [];
                            $statusLabel = carceris_log_delivery_dashboard_status($summary);
                            $statusClass = carceris_log_delivery_dashboard_status_class($summary);
                        ?>
                        <tr>
                            <td>
                                <strong><?= e(format_log_date($logDay['operational_date'])) ?></strong><br>
                                <a class="muted-small" href="/archive.php?date=<?= e($logDay['operational_date']) ?>">Open in Archive</a>
                            </td>
                            <td><?= e($logDay['log_label']) ?></td>
                            <td><?= e((string) ($logDay['entry_count'] ?? 0)) ?></td>
                            <td>
                                <span class="entry-status-pill entry-status-pill--<?= e($statusClass) ?>"><?= e($statusLabel) ?></span>
                                <?php if (!empty($summary['last_activity_at'])): ?>
                                    <br><span class="muted-small">Last activity: <?= e(carceris_format_datetime($summary['last_activity_at'])) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($summary['failed_count'])): ?>
                                    <br><span class="muted-small"><?= e((string) $summary['failed_count']) ?> failed <?= (int) $summary['failed_count'] === 1 ? 'send' : 'sends' ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <form method="post" action="/admin/report-download.php">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="log_day_id" value="<?= e((string) $logDay['id']) ?>">
                                        <input type="hidden" name="format" value="pdf">
                                        <button type="submit">PDF</button>
                                    </form>

                                    <form method="post" action="/admin/report-download.php">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="log_day_id" value="<?= e((string) $logDay['id']) ?>">
                                        <input type="hidden" name="format" value="plain_text">
                                        <button type="submit">Text</button>
                                    </form>

                                    <form method="post" action="/admin/report-download.php">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="log_day_id" value="<?= e((string) $logDay['id']) ?>">
                                        <input type="hidden" name="format" value="html">
                                        <button type="submit">HTML</button>
                                    </form>
                                </div>
                            </td>
                            <?php if ($showSendControls): ?>
                                <td>
                                    <form method="post" action="/admin/reports.php">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="send_log_now">
                                        <input type="hidden" name="log_day_id" value="<?= e((string) $logDay['id']) ?>">
                                        <button type="submit">Send Log</button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php if ($showFailedSendsPanel): ?>
    <section class="panel">
        <h2>Failed Sends</h2>
        <p class="empty-state">
            Failed manual or scheduled sends appear here<?= $showSendControls ? ' so supervisors and admins can retry the completed log.' : ' for review.' ?>
        </p>

        <?php if (!$failedDeliveries): ?>
            <p class="empty-state">No failed daily log sends were found.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="log-table">
                    <thead>
                        <tr>
                            <th>Failed At</th>
                            <th>Log</th>
                            <th>Type</th>
                            <th>Method</th>
                            <th>Error</th>
                            <?php if ($showSendControls): ?>
                                <th>Action</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($failedDeliveries as $delivery): ?>
                            <tr>
                                <td><?= e(carceris_format_datetime($delivery['created_at'])) ?></td>
                                <td>
                                    <?= e($delivery['log_label'] ?? '') ?><br>
                                    <?php if (!empty($delivery['operational_date'])): ?>
                                        <a class="muted-small" href="/archive.php?date=<?= e($delivery['operational_date']) ?>"><?= e(format_log_date($delivery['operational_date'])) ?></a>
                                    <?php endif; ?>
                                </td>
                                <td><?= e(carceris_report_delivery_activity_label($delivery)) ?></td>
                                <td><?= e(carceris_report_delivery_transport_label($delivery['transport'])) ?></td>
                                <td><?= e($delivery['error_message'] ?? 'Unknown error') ?></td>
                                <?php if ($showSendControls): ?>
                                    <td>
                                        <?php if (!empty($delivery['log_day_id'])): ?>
                                            <form method="post" action="/admin/reports.php">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="resend_failed_delivery">
                                                <input type="hidden" name="delivery_id" value="<?= e((string) $delivery['id']) ?>">
                                                <button type="submit">Resend</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="muted-small">No log linked</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php if ($showScheduledDeliveryPanel): ?>
    <section class="panel">
        <h2>Scheduled Delivery History</h2>
        <p class="empty-state">Automatic delivery attempts from the scheduled log delivery system.</p>

        <?php if (!$scheduledDeliveries): ?>
            <p class="empty-state">No scheduled delivery records found yet.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="log-table">
                    <thead>
                        <tr>
                            <th>Created</th>
                            <th>Log</th>
                            <th>Method</th>
                            <th>Format</th>
                            <th>Status</th>
                            <th>Recipients</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($scheduledDeliveries as $delivery): ?>
                            <tr>
                                <td><?= e(carceris_format_datetime($delivery['created_at'])) ?></td>
                                <td>
                                    <?= e($delivery['log_label'] ?? '') ?><br>
                                    <?php if (!empty($delivery['operational_date'])): ?>
                                        <a class="muted-small" href="/archive.php?date=<?= e($delivery['operational_date']) ?>"><?= e(format_log_date($delivery['operational_date'])) ?></a>
                                    <?php endif; ?>
                                </td>
                                <td><?= e(carceris_report_delivery_transport_label($delivery['transport'])) ?></td>
                                <td><?= e(carceris_report_delivery_format_label($delivery)) ?></td>
                                <td><span class="entry-status-pill entry-status-pill--<?= e($delivery['status']) ?>"><?= e(carceris_report_delivery_status_label($delivery['status'])) ?></span></td>
                                <td><?= e(carceris_report_delivery_recipient_label($delivery)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<section class="panel">
    <h2>Recent Log Activity</h2>
    <p class="empty-state">This includes test emails, log downloads, manual sends, scheduled sends, failures, and skipped scheduled runs.</p>

    <?php if (!$deliveries): ?>
        <p class="empty-state">No log delivery records found yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="log-table">
                <thead>
                    <tr>
                        <th>Created</th>
                        <th>Log</th>
                        <th>Activity</th>
                        <th>Method</th>
                        <th>Format</th>
                        <th>Status</th>
                        <th>Recipients</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($deliveries as $delivery): ?>
                        <tr>
                            <td><?= e(carceris_format_datetime($delivery['created_at'])) ?></td>
                            <td>
                                <?= e($delivery['log_label'] ?? '') ?>
                                <?php if (!empty($delivery['operational_date'])): ?>
                                    <br><a class="muted-small" href="/archive.php?date=<?= e($delivery['operational_date']) ?>"><?= e(format_log_date($delivery['operational_date'])) ?></a>
                                <?php endif; ?>
                            </td>
                            <td><?= e(carceris_report_delivery_activity_label($delivery)) ?></td>
                            <td><?= e(carceris_report_delivery_transport_label($delivery['transport'])) ?></td>
                            <td><?= e(carceris_report_delivery_format_label($delivery)) ?></td>
                            <td><span class="entry-status-pill entry-status-pill--<?= e($delivery['status']) ?>"><?= e(carceris_report_delivery_status_label($delivery['status'])) ?></span></td>
                            <td><?= e(carceris_report_delivery_recipient_label($delivery)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../partials/footer.php'; ?>
