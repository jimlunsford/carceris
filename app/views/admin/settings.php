<?php $pageTitle = 'Settings | Carceris'; ?>
<?php require __DIR__ . '/../partials/header.php'; ?>

<section class="page-heading">
    <div>
        <h1>Settings</h1>
        <p>Manage site-wide Carceris settings.</p>
    </div>
</section>


<section class="panel">
    <h2>Header Branding</h2>

    <form method="post" action="/admin/settings.php" class="form-stack settings-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_header_branding">

        <label>
            <span>Header Name</span>
            <input type="text" name="app_name" value="<?= e($headerBrandName) ?>" maxlength="80" required>
            <small>Example: County Jail, Sheriff's Office, or Carceris.</small>
        </label>

        <label>
            <span>Header Tagline</span>
            <input type="text" name="app_tagline" value="<?= e($headerBrandTagline) ?>" maxlength="160" required>
            <small>Keep this short. This appears under the header name on every page.</small>
        </label>

        <div class="form-actions">
            <button type="submit">Save Header Branding</button>
        </div>
    </form>
</section>



<section class="panel">
    <h2>Login Screen Branding</h2>

    <form method="post" action="/admin/settings.php" class="form-stack settings-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_login_branding">

        <label>
            <span>Login Name</span>
            <input type="text" name="login_name" value="<?= e($loginBrandName) ?>" maxlength="80" required>
            <small>Example: County Jail, Jail Log, or Carceris.</small>
        </label>

        <label>
            <span>Login Tagline</span>
            <input type="text" name="login_tagline" value="<?= e($loginBrandTagline) ?>" maxlength="160" required>
            <small>This appears under the login heading.</small>
        </label>

        <div class="form-actions">
            <button type="submit">Save Login Branding</button>
        </div>
    </form>
</section>

<section class="panel">
    <h2>Print View Branding</h2>

    <form method="post" action="/admin/settings.php" class="form-stack settings-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_print_branding">

        <label>
            <span>Print Log Title</span>
            <input type="text" name="print_log_title" value="<?= e($printLogTitle) ?>" maxlength="120" required>
            <small>This appears at the top of printed logs. Example: Correctional Facility Daily Log.</small>
        </label>

        <div class="form-actions">
            <button type="submit">Save Print View Branding</button>
        </div>
    </form>
</section>


<section class="panel">
    <h2>Daily Log Filenames</h2>

    <form method="post" action="/admin/settings.php" class="form-stack settings-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_filename_pattern">

        <label>
            <span>Daily Log Filename Pattern</span>
            <input type="text" name="daily_log_filename_pattern" value="<?= e($dailyLogFilenamePattern) ?>" maxlength="120" required>
            <small>Use {date} where the operational date should appear. Extensions are added automatically. Example: county-jail-daily-log-{date}</small>
        </label>

        <p class="empty-state">Allowed characters are letters, numbers, hyphens, underscores, and the {date} token. Unsafe characters are removed automatically.</p>

        <div class="form-actions">
            <button type="submit">Save Filename Pattern</button>
        </div>
    </form>
</section>

<section class="panel">
    <h2>Operational Day</h2>

    <form method="post" action="/admin/settings.php" class="form-stack settings-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_operational_day">

        <label>
            <span>Operational Day Start Time</span>

            <div class="time-control-row">
                <?php if ($clockFormat === '12'): ?>
                    <select name="daily_log_start_time_hour" aria-label="Operational day start hour" required>
                        <?php for ($hour = 1; $hour <= 12; $hour++): ?>
                            <option value="<?= e((string) $hour) ?>" <?= $dailyLogStartTimeParts['hour_12'] === (string) $hour ? 'selected' : '' ?>>
                                <?= e((string) $hour) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                <?php else: ?>
                    <select name="daily_log_start_time_hour_24" aria-label="Operational day start hour" required>
                        <?php for ($hour = 0; $hour <= 23; $hour++): ?>
                            <?php $hourValue = sprintf('%02d', $hour); ?>
                            <option value="<?= e($hourValue) ?>" <?= $dailyLogStartTimeParts['hour_24'] === $hourValue ? 'selected' : '' ?>>
                                <?= e($hourValue) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                <?php endif; ?>

                <span class="time-control-separator">:</span>

                <select name="daily_log_start_time_minute" aria-label="Operational day start minute" required>
                    <?php for ($minute = 0; $minute <= 59; $minute++): ?>
                        <?php $minuteValue = sprintf('%02d', $minute); ?>
                        <option value="<?= e($minuteValue) ?>" <?= $dailyLogStartTimeParts['minute'] === $minuteValue ? 'selected' : '' ?>>
                            <?= e($minuteValue) ?>
                        </option>
                    <?php endfor; ?>
                </select>

                <?php if ($clockFormat === '12'): ?>
                    <select name="daily_log_start_time_period" aria-label="Operational day start period" required>
                        <option value="AM" <?= $dailyLogStartTimeParts['period'] === 'AM' ? 'selected' : '' ?>>AM</option>
                        <option value="PM" <?= $dailyLogStartTimeParts['period'] === 'PM' ? 'selected' : '' ?>>PM</option>
                    </select>
                <?php endif; ?>
            </div>
        </label>

        <p class="empty-state">This controls when a new jail log begins. It is separate from the report delivery send time and follows the clock format below.</p>

        <div class="form-actions">
            <button type="submit">Save Operational Day</button>
        </div>
    </form>
</section>

<section class="panel">
    <h2>Time Display</h2>

    <form method="post" action="/admin/settings.php" class="form-stack settings-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_clock_format">

        <label>
            <span>Clock Format</span>
            <select name="clock_format" required>
                <option value="24" <?= $clockFormat === '24' ? 'selected' : '' ?>>24-hour clock, example 17:30</option>
                <option value="12" <?= $clockFormat === '12' ? 'selected' : '' ?>>12-hour clock, example 5:30 PM</option>
            </select>
        </label>

        <div class="form-actions">
            <button type="submit">Save Clock Format</button>
        </div>
    </form>
</section>

<section class="panel">
    <h2>Log Visibility</h2>

    <form method="post" action="/admin/settings.php" class="form-stack settings-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_log_visibility">

        <label>
            <span>Show Voided Entries</span>
            <select name="show_voided_entries" required>
                <option value="1" <?= $showVoidedEntries === '1' ? 'selected' : '' ?>>Yes, show voided entries in logs and reports</option>
                <option value="0" <?= $showVoidedEntries === '0' ? 'selected' : '' ?>>No, hide voided entries from logs and reports</option>
            </select>
        </label>

        <p class="empty-state">Voided entries are still preserved in the database and audit records. This setting only controls normal log and report visibility.</p>

        <div class="form-actions">
            <button type="submit">Save Log Visibility</button>
        </div>
    </form>
</section>

<?php require __DIR__ . '/../partials/footer.php'; ?>
