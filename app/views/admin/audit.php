<?php $pageTitle = 'Audit Events | Carceris'; ?>
<?php require __DIR__ . '/../partials/header.php'; ?>

<section class="page-heading">
    <div>
        <h1>Audit Events</h1>
        <p>Search and filter security and administrative events recorded by Carceris.</p>
    </div>
</section>


<section class="panel">
    <h2>Search Audit</h2>

    <form method="get" action="/admin/audit.php" class="form-stack audit-filter-form">
        <div class="audit-basic-search">
            <label class="audit-basic-search__keyword">
                <span>Search</span>
                <input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Search event, user, IP, details, or related type">
            </label>

            <div class="audit-date-range">
                <label>
                    <span>Date From</span>
                    <input type="date" name="date_from" value="<?= e($filters['date_from']) ?>">
                </label>

                <label>
                    <span>Date To</span>
                    <input type="date" name="date_to" value="<?= e($filters['date_to']) ?>">
                </label>
            </div>
        </div>

        <details class="advanced-search-panel audit-advanced-search-panel" <?= $auditAdvancedOpen ? 'open' : '' ?>>
            <summary>Advanced Search</summary>

            <div class="settings-grid advanced-search-grid">
                <label>
                    <span>Event Type</span>
                    <select name="event_type">
                        <option value="">All event types</option>
                        <?php foreach ($eventTypes as $eventType): ?>
                            <option value="<?= e($eventType) ?>" <?= $filters['event_type'] === $eventType ? 'selected' : '' ?>><?= e($eventType) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    <span>Username</span>
                    <input type="search" name="username" value="<?= e($filters['username']) ?>" placeholder="Username">
                </label>

                <label>
                    <span>Limit</span>
                    <select name="limit">
                        <?php foreach ([50, 150, 250, 500, 1000] as $limitOption): ?>
                            <option value="<?= e((string) $limitOption) ?>" <?= $limit === $limitOption ? 'selected' : '' ?>><?= e((string) $limitOption) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
        </details>

        <div class="form-actions">
            <button type="submit">Search Audit</button>
            <a class="button-link button-link-secondary" href="/admin/audit.php">Reset</a>
            <?php if (user_can($user, 'manage_settings')): ?>
                <button type="submit" name="export" value="csv">Export CSV</button>
            <?php endif; ?>
        </div>
    </form>
</section>

<?php if (user_can($user, 'manage_settings')): ?>
    <section class="panel">
        <h2>Audit Retention</h2>
        <p class="empty-state">Retention pruning permanently deletes audit events older than the number of days selected. Use carefully.</p>

        <form method="post" action="/admin/audit.php" class="form-stack audit-retention-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="prune_audit">

            <label>
                <span>Delete audit events older than</span>
                <select name="retention_days" required>
                    <option value="90">90 days</option>
                    <option value="180">180 days</option>
                    <option value="365">1 year</option>
                    <option value="730">2 years</option>
                    <option value="1095">3 years</option>
                    <option value="1825">5 years</option>
                </select>
            </label>

            <div class="form-actions">
                <button type="submit">Prune Audit Log</button>
            </div>
        </form>
    </section>
<?php endif; ?>

<section class="panel">
    <h2>Audit Events</h2>

    <?php if (!$events): ?>
        <p class="empty-state">No audit events found.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="log-table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Event</th>
                        <th>User</th>
                        <th>IP Address</th>
                        <th>Details</th>
                        <th>Related</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($events as $event): ?>
                        <tr>
                            <td><?= e(carceris_format_datetime($event['created_at'])) ?></td>
                            <td><?= e($event['event_type']) ?></td>
                            <td><?= e($event['username'] ?? '') ?></td>
                            <td><?= e($event['ip_address']) ?></td>
                            <td><?= e($event['details'] ?? '') ?></td>
                            <td>
                                <?php if ($event['related_type']): ?>
                                    <?= e($event['related_type']) ?> #<?= e((string) $event['related_id']) ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../partials/footer.php'; ?>
