<?php $pageTitle = 'Log Delivery | Carceris'; ?>
<?php require __DIR__ . '/../partials/header.php'; ?>

<section class="page-heading">
    <div>
        <h1>Log Delivery</h1>
        <p>Configure recipients, log formats, mail transport, test email, and scheduled log delivery.</p>
    </div>
</section>

<section class="panel">
    <h2>Log Delivery Schedule and Format</h2>

    <form method="post" action="/admin/report-delivery.php" class="form-stack report-settings-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_settings">

        <div class="settings-grid">
            <label class="checkbox-row report-enabled-row">
                <input type="checkbox" name="report_delivery_enabled" value="1" <?= $settings['report_delivery_enabled'] === '1' ? 'checked' : '' ?>>
                <span>Enable scheduled automatic delivery</span>
            </label>

            <label>
                <span>Scheduled Send Time</span>

                <div class="time-control-row">
                    <?php if ($clockFormat === '12'): ?>
                        <select name="report_send_time_hour" aria-label="Scheduled send hour" required>
                            <?php for ($hour = 1; $hour <= 12; $hour++): ?>
                                <option value="<?= e((string) $hour) ?>" <?= $reportSendTimeParts['hour_12'] === (string) $hour ? 'selected' : '' ?>>
                                    <?= e((string) $hour) ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    <?php else: ?>
                        <select name="report_send_time_hour_24" aria-label="Scheduled send hour" required>
                            <?php for ($hour = 0; $hour <= 23; $hour++): ?>
                                <?php $hourValue = sprintf('%02d', $hour); ?>
                                <option value="<?= e($hourValue) ?>" <?= $reportSendTimeParts['hour_24'] === $hourValue ? 'selected' : '' ?>>
                                    <?= e($hourValue) ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    <?php endif; ?>

                    <span class="time-control-separator">:</span>

                    <select name="report_send_time_minute" aria-label="Scheduled send minute" required>
                        <?php for ($minute = 0; $minute <= 59; $minute++): ?>
                            <?php $minuteValue = sprintf('%02d', $minute); ?>
                            <option value="<?= e($minuteValue) ?>" <?= $reportSendTimeParts['minute'] === $minuteValue ? 'selected' : '' ?>>
                                <?= e($minuteValue) ?>
                            </option>
                        <?php endfor; ?>
                    </select>

                    <?php if ($clockFormat === '12'): ?>
                        <select name="report_send_time_period" aria-label="Scheduled send period" required>
                            <option value="AM" <?= $reportSendTimeParts['period'] === 'AM' ? 'selected' : '' ?>>AM</option>
                            <option value="PM" <?= $reportSendTimeParts['period'] === 'PM' ? 'selected' : '' ?>>PM</option>
                        </select>
                    <?php endif; ?>
                </div>

                <small>Controls when Carceris attempts to email the previous completed daily log. This is separate from the Operational Day Start Time in Settings.</small>
            </label>

            <label>
                <span>Mail Transport</span>
                <select name="report_mail_transport" required>
                    <?php foreach ($transportOptions as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $settings['report_mail_transport'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <small>Native SMTP works without dependencies. PHPMailer SMTP uses the optional phpmailer/phpmailer Composer library when installed.</small>
            </label>

            <label>
                <span>Email Body Format</span>
                <select name="report_body_format" required>
                    <?php foreach ($bodyFormatOptions as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $settings['report_body_format'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span>Attachment Format</span>
                <select name="report_attachment_format" required>
                    <?php foreach ($attachmentFormatOptions as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $settings['report_attachment_format'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span>From Name</span>
                <input type="text" name="report_from_name" value="<?= e($settings['report_from_name']) ?>" maxlength="120">
            </label>

            <label>
                <span>From Email</span>
                <input type="email" name="report_from_email" value="<?= e($settings['report_from_email']) ?>" maxlength="190">
            </label>
        </div>


        <fieldset class="settings-fieldset">
            <legend>SMTP Mail Settings</legend>

            <div class="settings-grid">
                <label>
                    <span>SMTP Host</span>
                    <input type="text" name="report_smtp_host" value="<?= e($settings['report_smtp_host'] ?? '') ?>" maxlength="190">
                </label>

                <label>
                    <span>SMTP Port</span>
                    <input type="number" name="report_smtp_port" value="<?= e($settings['report_smtp_port'] ?? '587') ?>" min="1" max="65535">
                </label>

                <label>
                    <span>SMTP Encryption</span>
                    <select name="report_smtp_encryption">
                        <option value="none" <?= ($settings['report_smtp_encryption'] ?? 'tls') === 'none' ? 'selected' : '' ?>>None</option>
                        <option value="tls" <?= ($settings['report_smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS / STARTTLS</option>
                        <option value="ssl" <?= ($settings['report_smtp_encryption'] ?? 'tls') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                    </select>
                </label>

                <label>
                    <span>SMTP Username</span>
                    <input type="text" name="report_smtp_username" value="<?= e($settings['report_smtp_username'] ?? '') ?>" maxlength="190" autocomplete="off">
                </label>

                <label>
                    <span>SMTP Password</span>
                    <input type="password" name="report_smtp_password" value="" autocomplete="new-password" placeholder="<?= !empty($settings['report_smtp_password']) ? 'Password saved, leave blank to keep current' : '' ?>">
                </label>
            </div>
        </fieldset>

        <fieldset class="settings-fieldset">
            <legend>Sendmail Mail Settings</legend>

            <label>
                <span>Sendmail Path</span>
                <input type="text" name="report_sendmail_path" value="<?= e($settings['report_sendmail_path'] ?? '/usr/sbin/sendmail') ?>" maxlength="255">
            </label>
        </fieldset>

        <fieldset class="settings-fieldset">
            <legend>Log Recipients</legend>

            <label>
                <span>To</span>
                <textarea name="report_recipients_to" rows="3" placeholder="admin@example.gov, records@example.gov"><?= e($settings['report_recipients_to']) ?></textarea>
            </label>

            <label>
                <span>CC</span>
                <textarea name="report_recipients_cc" rows="2"><?= e($settings['report_recipients_cc']) ?></textarea>
            </label>

            <label>
                <span>BCC</span>
                <textarea name="report_recipients_bcc" rows="2"><?= e($settings['report_recipients_bcc']) ?></textarea>
            </label>
        </fieldset>

        <div class="form-actions">
            <button type="submit">Save Log Delivery</button>
        </div>
    </form>
</section>


<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Test Email</h2>
            <p>Send a test message using the selected mail transport. This verifies delivery settings without sending a daily log.</p>
        </div>
    </div>

    <form method="post" action="/admin/report-delivery.php" class="form-stack test-email-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="send_test_email">

        <label>
            <span>Test Recipient</span>
            <input type="email" name="test_recipient" placeholder="Leave blank to use the To recipients above">
        </label>

        <button type="submit">Send Test Email</button>
    </form>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Cron Key</h2>
            <p>This key protects the web cron endpoint used for scheduled daily report delivery.</p>
        </div>
    </div>

    <div class="cron-key-box">
        <code><?= e($settings['report_cron_key']) ?></code>
    </div>

    <p class="empty-state">Web cron URL:</p>
    <pre class="cron-url-preview"><?= e($cronUrlPreview) ?></pre>

    <form method="post" action="/admin/report-delivery.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="regenerate_cron_key">
        <button type="submit">Regenerate Cron Key</button>
    </form>
</section>

<section class="panel">
    <h2>Log Delivery History</h2>
    <p class="empty-state">This table includes test emails, log downloads, manual sends, scheduled sends, failures, and skipped scheduled runs.</p>

    <?php if (!$deliveries): ?>
        <p class="empty-state">No report delivery records found yet.</p>
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
                            <td><?= e($delivery['log_label'] ?? '') ?></td>
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
