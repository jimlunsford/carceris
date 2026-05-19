<?php $pageTitle = 'Entry History | Carceris'; ?>
<?php require __DIR__ . '/../partials/header.php'; ?>

<section class="page-heading">
    <div>
        <h1>Entry History</h1>
        <p>Correction and void history for log entry #<?= e((string) $entry['id']) ?>.</p>
    </div>

    <a class="button-link" href="<?= e($returnTo) ?>">Back</a>
</section>

<section class="panel">
    <h2>Current Entry Record</h2>

    <div class="entry-review-box">
        <p><strong>Status:</strong> <?= e(entry_status_label($entry)) ?></p>
        <p><strong>Operational Period:</strong> <?= e($entry['log_label']) ?></p>
        <p><strong>Event Time:</strong> <?= e(format_log_datetime($entry['event_time'])) ?></p>
        <p><strong>Category:</strong> <?= e($entry['category']) ?></p>
        <p><strong>Priority:</strong> <?= e(ucfirst($entry['priority'])) ?></p>
        <p><strong>Location:</strong> <?= e($entry['location'] ?? '') ?></p>
        <p><strong>Inmate:</strong> <?= e($entry['inmate_name'] ?? '') ?></p>
        <p><strong>Officer:</strong> <?= e($entry['created_by_name'] ?? 'Unknown') ?></p>
        <?php if (!empty($entry['parent_entry_id'])): ?>
            <p>
                <strong>Correction Entry:</strong>
                Replaces entry
                <a href="/admin/entry-history.php?id=<?= e((string) $entry['parent_entry_id']) ?>&return=<?= e(carceris_return_query_value($returnTo)) ?>">#<?= e((string) $entry['parent_entry_id']) ?></a>
            </p>
        <?php endif; ?>

        <?php $statusNote = entry_status_note($entry); ?>
        <?php if ($statusNote !== ''): ?>
            <p><strong>Status Note:</strong> <?= e($statusNote) ?></p>
        <?php endif; ?>

        <div class="entry-review-text"><?= nl2br(e($entry['entry_text'])) ?></div>
    </div>
</section>

<section class="panel">
    <h2>Correction and Void Actions</h2>

    <?php if (!$actions): ?>
        <p class="empty-state">No correction or void actions were found for this entry.</p>
    <?php else: ?>
        <div class="history-list">
            <?php foreach ($actions as $action): ?>
                <?php $snapshot = log_entry_snapshot_from_action($action); ?>
                <article class="history-card">
                    <div class="history-card__header">
                        <div>
                            <strong><?= e(ucfirst($action['action_type'])) ?></strong>
                            <span><?= !empty($action['performed_at']) ? e(format_log_datetime($action['performed_at'])) : 'Unknown time' ?></span>
                        </div>
                        <div class="entry-status-pill entry-status-pill--<?= e($action['action_type'] === 'void' ? 'voided' : 'corrected') ?>">
                            <?= e($action['action_type'] === 'void' ? 'Voided' : 'Corrected') ?>
                        </div>
                    </div>

                    <p><strong>Performed By:</strong> <?= e($action['performed_by_name'] ?? 'Unknown') ?></p>
                    <p><strong>Reason:</strong> <?= e($action['reason']) ?></p>

                    <?php if (!empty($action['replacement_entry_id'])): ?>
                        <p>
                            <strong>Replacement Entry:</strong>
                            <a href="/admin/entry-history.php?id=<?= e((string) $action['replacement_entry_id']) ?>&return=<?= e(carceris_return_query_value($returnTo)) ?>">
                                View entry #<?= e((string) $action['replacement_entry_id']) ?>
                            </a>
                        </p>
                    <?php endif; ?>

                    <details class="history-snapshot" open>
                        <summary>Original Snapshot</summary>

                        <?php if (!$snapshot): ?>
                            <p class="empty-state">Original snapshot could not be decoded.</p>
                        <?php else: ?>
                            <dl class="snapshot-grid">
                                <dt>Original Entry ID</dt>
                                <dd><?= e((string) ($snapshot['id'] ?? '')) ?></dd>

                                <dt>Original Event Time</dt>
                                <dd><?= !empty($snapshot['event_time']) ? e(format_log_datetime($snapshot['event_time'])) : '' ?></dd>

                                <dt>Original Category</dt>
                                <dd><?= e((string) ($snapshot['category'] ?? '')) ?></dd>

                                <dt>Original Priority</dt>
                                <dd><?= e((string) ($snapshot['priority'] ?? '')) ?></dd>

                                <dt>Original Location</dt>
                                <dd><?= e((string) ($snapshot['location'] ?? '')) ?></dd>

                                <dt>Original Inmate</dt>
                                <dd><?= e((string) ($snapshot['inmate_name'] ?? '')) ?></dd>

                                <dt>Original Officer</dt>
                                <dd><?= e((string) ($snapshot['created_by_name'] ?? '')) ?></dd>

                                <dt>Original Created At</dt>
                                <dd><?= !empty($snapshot['created_at']) ? e(format_log_datetime($snapshot['created_at'])) : '' ?></dd>

                                <dt>Original Text</dt>
                                <dd><?= nl2br(e((string) ($snapshot['entry_text'] ?? ''))) ?></dd>
                            </dl>
                        <?php endif; ?>
                    </details>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="panel">
    <h2>Correction Detail Records</h2>

    <?php if (!$revisions): ?>
        <p class="empty-state">No correction detail records were found for this entry.</p>
    <?php else: ?>
        <div class="history-list">
            <?php foreach ($revisions as $revision): ?>
                <article class="history-card">
                    <div class="history-card__header">
                        <div>
                            <strong>Correction Detail</strong>
                            <span><?= e(format_log_datetime($revision['corrected_at'])) ?></span>
                        </div>
                    </div>

                    <p><strong>Corrected By:</strong> <?= e($revision['corrected_by_name'] ?? 'Unknown') ?></p>
                    <p><strong>Reason:</strong> <?= e($revision['correction_reason']) ?></p>

                    <div class="revision-compare">
                        <div>
                            <h3>Original</h3>
                            <p><strong>Time:</strong> <?= !empty($revision['old_event_time']) ? e(format_log_datetime($revision['old_event_time'])) : '' ?></p>
                            <p><strong>Category:</strong> <?= e($revision['old_category'] ?? '') ?></p>
                            <p><strong>Location:</strong> <?= e($revision['old_location'] ?? '') ?></p>
                            <p><strong>Inmate:</strong> <?= e($revision['old_inmate_name'] ?? '') ?></p>
                            <div class="entry-review-text"><?= nl2br(e($revision['old_entry_text'] ?? '')) ?></div>
                        </div>

                        <div>
                            <h3>Corrected</h3>
                            <p><strong>Time:</strong> <?= !empty($revision['new_event_time']) ? e(format_log_datetime($revision['new_event_time'])) : '' ?></p>
                            <p><strong>Category:</strong> <?= e($revision['new_category'] ?? '') ?></p>
                            <p><strong>Location:</strong> <?= e($revision['new_location'] ?? '') ?></p>
                            <p><strong>Inmate:</strong> <?= e($revision['new_inmate_name'] ?? '') ?></p>
                            <div class="entry-review-text"><?= nl2br(e($revision['new_entry_text'] ?? '')) ?></div>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../partials/footer.php'; ?>
