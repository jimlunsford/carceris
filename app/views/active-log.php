<?php $pageTitle = 'Active Log | Carceris'; ?>
<?php require __DIR__ . '/partials/header.php'; ?>

<section class="page-heading">
    <div>
        <h1>Active Daily Log</h1>
        <p><?= e($logDay['log_label']) ?></p>
    </div>

    <div class="user-pill">
        Signed in as <?= e($user['display_name']) ?>
    </div>
</section>

<section class="panel">
    <h2>Add Log Entry</h2>

    <?php if (user_can($user, 'create_entry')): ?>
        <form method="post" action="/add-entry.php" class="entry-form" id="log-entry-form">
            <?= csrf_field() ?>

            <label>
                <span>Category</span>
                <select name="category" required>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= e($category['name']) ?>"><?= e($category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span>Priority</span>
                <select name="priority" required>
                    <option value="normal">Normal</option>
                    <option value="important">Important</option>
                    <option value="critical">Critical</option>
                </select>
            </label>

            <label>
                <span>Location</span>
                <input type="text" name="location" maxlength="120" placeholder="Optional">
            </label>

            <label>
                <span>Inmate Name or Identifier</span>
                <input type="text" name="inmate_name" maxlength="160" placeholder="Optional">
            </label>

            <label class="entry-form__details">
                <span>Details</span>
                <textarea name="entry_text" rows="5" maxlength="10000" required></textarea>
            </label>

            <label class="checkbox-row entry-form__late-toggle">
                <input type="checkbox" name="is_late_entry" value="1" id="is_late_entry">
                <span>This is a late or backfilled entry</span>
            </label>

            <div class="entry-form__late-fields" id="late-entry-fields" hidden>
                <div class="late-field">
                    <label for="event_time">Actual Event Date and Time</label>
                    <input type="datetime-local" name="event_time" id="event_time">
                </div>

                <div class="late-field">
                    <label for="late_entry_reason">Reason for Late Entry</label>
                    <textarea name="late_entry_reason" id="late_entry_reason" rows="3" maxlength="1000" placeholder="Example: System was down. Entered after service restored."></textarea>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit">Save Entry</button>
            </div>
        </form>
    <?php else: ?>
        <p>Your account can view logs but cannot create entries.</p>
    <?php endif; ?>
</section>

<section class="panel">
    <div class="panel-header">
        <h2>Today’s Entries</h2>
        <div class="panel-actions">
            <a class="button-link" href="/print.php" target="_blank" rel="noopener">Print View</a>
            <a class="button-link" href="/log-pdf.php">Download PDF</a>
            <a class="button-link" href="/archive.php">Archive</a>
        </div>
    </div>

    <?php if (!$entries): ?>
        <p class="empty-state">No log entries have been recorded for this operational day.</p>
    <?php else: ?>
        <div class="table-wrap">

<table class="log-table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Category</th>
                        <th>Priority</th>
                        <th>Location</th>
                        <th>Inmate</th>
                        <th>Entry</th>
                        <th>Status</th>
                        <th>Officer</th>
                        <?php if (user_can($user, 'correct_void_entries')): ?>
                            <th>Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $entry): ?>
                        <tr class="<?= e(entry_row_class($entry)) ?>">
                            <td><?= e(format_log_time($entry['event_time'])) ?></td>
                            <td><?= e($entry['category']) ?></td>
                            <td><?= e(ucfirst($entry['priority'])) ?></td>
                            <td><?= e($entry['location'] ?? '') ?></td>
                            <td><?= e($entry['inmate_name'] ?? '') ?></td>
                            <td>
                                <?= nl2br(e($entry['entry_text'])) ?>
                                <?php $lateNote = entry_late_note($entry); ?>
                                <?php if ($lateNote !== ''): ?>
                                    <div class="late-entry-note"><?= e($lateNote) ?></div>
                                <?php endif; ?>
                                <?php $statusNote = entry_status_note($entry); ?>
                                <?php if ($statusNote !== ''): ?>
                                    <div class="entry-status-note"><?= e($statusNote) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><span class="entry-status-pill entry-status-pill--<?= e(strtolower(entry_status_label($entry))) ?>"><?= e(entry_status_label($entry)) ?></span></td>
                            <td><?= e($entry['created_by_name'] ?? 'Unknown') ?></td>
                            <?php if (user_can($user, 'correct_void_entries')): ?>
                                <?php $entryReturnPath = '/index.php'; ?>
                                <td>
                                    <div class="table-actions">
                                        <?php if (entry_status_label($entry) === 'Active'): ?>
                                            <a class="button-link" href="/admin/entry-action.php?id=<?= e((string) $entry['id']) ?>&return=<?= e(carceris_return_query_value($entryReturnPath)) ?>">Correct / Void</a>
                                        <?php endif; ?>

                                        <?php if (log_entry_has_history($entry)): ?>
                                            <a class="button-link button-link-secondary" href="/admin/entry-history.php?id=<?= e((string) $entry['id']) ?>&return=<?= e(carceris_return_query_value($entryReturnPath)) ?>">History</a>
                                        <?php elseif (entry_status_label($entry) !== 'Active'): ?>
                                            <span class="muted-small">No action</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
