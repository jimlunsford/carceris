<?php $pageTitle = 'Correct or Void Entry | Carceris'; ?>
<?php require __DIR__ . '/../partials/header.php'; ?>

<section class="page-heading">
    <div>
        <h1>Correct or Void Entry</h1>
        <p>Preserve the original record while adding an accountable correction or void action.</p>
    </div>

    <a class="button-link" href="<?= e($returnTo) ?>">Back</a>
</section>

<section class="panel">
    <h2>Original Entry</h2>

    <div class="entry-review-box">
        <p><strong>Operational Period:</strong> <?= e($entry['log_label']) ?></p>
        <p><strong>Event Time:</strong> <?= e(format_log_datetime($entry['event_time'])) ?></p>
        <p><strong>Category:</strong> <?= e($entry['category']) ?></p>
        <p><strong>Priority:</strong> <?= e(ucfirst($entry['priority'])) ?></p>
        <p><strong>Location:</strong> <?= e($entry['location'] ?? '') ?></p>
        <p><strong>Inmate:</strong> <?= e($entry['inmate_name'] ?? '') ?></p>
        <p><strong>Officer:</strong> <?= e($entry['created_by_name'] ?? 'Unknown') ?></p>
        <p><strong>Status:</strong> <?= e(entry_status_label($entry)) ?></p>
        <div class="entry-review-text"><?= nl2br(e($entry['entry_text'])) ?></div>
    </div>
</section>

<?php if (entry_status_label($entry) !== 'Active'): ?>
    <section class="panel">
        <h2>No Action Available</h2>
        <p>This entry is already <?= e(strtolower(entry_status_label($entry))) ?> and cannot be corrected or voided again.</p>
    </section>
<?php else: ?>
    <section class="panel">
        <h2>Correct Entry</h2>

        <form method="post" action="/admin/entry-action.php" class="entry-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="correct">
            <input type="hidden" name="entry_id" value="<?= e((string) $entry['id']) ?>">
            <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
            <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">

            <label>
                <span>Actual Event Date and Time</span>
                <input type="datetime-local" name="event_time" value="<?= e((new DateTimeImmutable($entry['event_time']))->format('Y-m-d\TH:i')) ?>" required>
            </label>

            <label>
                <span>Category</span>
                <select name="category" required>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= e($category['name']) ?>" <?= $category['name'] === $entry['category'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span>Priority</span>
                <select name="priority" required>
                    <?php foreach (['normal' => 'Normal', 'important' => 'Important', 'critical' => 'Critical'] as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $value === $entry['priority'] ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span>Location</span>
                <input type="text" name="location" maxlength="120" value="<?= e($entry['location'] ?? '') ?>">
            </label>

            <label>
                <span>Inmate Name or Identifier</span>
                <input type="text" name="inmate_name" maxlength="160" value="<?= e($entry['inmate_name'] ?? '') ?>">
            </label>

            <label class="entry-form__details">
                <span>Corrected Details</span>
                <textarea name="entry_text" rows="5" maxlength="10000" required><?= e($entry['entry_text']) ?></textarea>
            </label>

            <label class="entry-form__details">
                <span>Correction Reason</span>
                <textarea name="correction_reason" rows="3" maxlength="1000" required placeholder="Explain why this entry is being corrected."></textarea>
            </label>

            <div class="form-actions">
                <button type="submit">Save Correction</button>
            </div>
        </form>
    </section>

    <section class="panel panel-danger">
        <h2>Void Entry</h2>
        <p>Use void only when the original entry should remain visible but marked invalid. The original entry will not be deleted.</p>

        <form method="post" action="/admin/entry-action.php" class="form-stack">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="void">
            <input type="hidden" name="entry_id" value="<?= e((string) $entry['id']) ?>">

            <label>
                <span>Void Reason</span>
                <textarea name="void_reason" rows="3" maxlength="1000" required placeholder="Explain why this entry is being voided."></textarea>
            </label>

            <button type="submit">Void Entry</button>
        </form>
    </section>
<?php endif; ?>

<?php require __DIR__ . '/../partials/footer.php'; ?>
