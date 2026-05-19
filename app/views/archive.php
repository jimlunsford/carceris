<?php $pageTitle = 'Archive | Carceris'; ?>
<?php require __DIR__ . '/partials/header.php'; ?>

<section class="page-heading">
    <div>
        <h1>Log Archive</h1>
        <p>View, search, filter, print, and export operational daily logs.</p>
    </div>
</section>

<section class="panel">
    <h2>Open Specific Log</h2>

    <form method="get" action="/archive.php" class="archive-form">
        <label>
            <span>Operational Date</span>
            <input type="date" name="date" value="<?= e($selectedDate) ?>">
        </label>

        <button type="submit">View Log</button>
    </form>
</section>


<section class="panel">
    <h2>Search Archive</h2>

    <form method="get" action="/archive.php" class="form-stack archive-search-form">
        <input type="hidden" name="archive_search" value="1">

        <div class="archive-basic-search">
            <label class="archive-basic-search__keyword">
                <span>Search</span>
                <input type="search" name="q" value="<?= e($archiveFilters['q']) ?>" placeholder="Search entries, names, locations, officers, or log labels">
            </label>

            <div class="archive-date-range">
                <label>
                    <span>Date From</span>
                    <input type="date" name="date_from" value="<?= e($archiveFilters['date_from']) ?>">
                </label>

                <label>
                    <span>Date To</span>
                    <input type="date" name="date_to" value="<?= e($archiveFilters['date_to']) ?>">
                </label>
            </div>
        </div>

        <details class="advanced-search-panel" <?= $archiveAdvancedOpen ? 'open' : '' ?>>
            <summary>Advanced Search</summary>

            <div class="settings-grid advanced-search-grid">
                <label>
                    <span>Category</span>
                    <select name="category">
                        <option value="">All categories</option>
                        <?php foreach ($archiveCategories as $category): ?>
                            <option value="<?= e($category['name']) ?>" <?= $archiveFilters['category'] === $category['name'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    <span>Priority</span>
                    <select name="priority">
                        <option value="">All priorities</option>
                        <option value="normal" <?= $archiveFilters['priority'] === 'normal' ? 'selected' : '' ?>>Normal</option>
                        <option value="important" <?= $archiveFilters['priority'] === 'important' ? 'selected' : '' ?>>Important</option>
                        <option value="critical" <?= $archiveFilters['priority'] === 'critical' ? 'selected' : '' ?>>Critical</option>
                    </select>
                </label>

                <label>
                    <span>Status</span>
                    <select name="status">
                        <option value="">All statuses</option>
                        <option value="active" <?= $archiveFilters['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="corrected" <?= $archiveFilters['status'] === 'corrected' ? 'selected' : '' ?>>Corrected</option>
                        <option value="voided" <?= $archiveFilters['status'] === 'voided' ? 'selected' : '' ?>>Voided</option>
                        <option value="correction" <?= $archiveFilters['status'] === 'correction' ? 'selected' : '' ?>>Correction Entry</option>
                    </select>
                </label>

                <label>
                    <span>Inmate Name or Identifier</span>
                    <input type="search" name="inmate" value="<?= e($archiveFilters['inmate']) ?>" placeholder="Optional">
                </label>

                <label>
                    <span>Location</span>
                    <input type="search" name="location" value="<?= e($archiveFilters['location']) ?>" placeholder="Optional">
                </label>

                <label>
                    <span>Officer</span>
                    <input type="search" name="officer" value="<?= e($archiveFilters['officer']) ?>" placeholder="Optional">
                </label>

                <label>
                    <span>Limit</span>
                    <select name="limit">
                        <?php foreach ([50, 150, 250, 500, 1000] as $limitOption): ?>
                            <option value="<?= e((string) $limitOption) ?>" <?= $archiveLimit === $limitOption ? 'selected' : '' ?>><?= e((string) $limitOption) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <label class="checkbox-row">
                <input type="checkbox" name="include_voided" value="1" <?= $archiveFilters['include_voided'] === '1' ? 'checked' : '' ?>>
                <span>Include voided entries when the site-wide voided entry setting is off</span>
            </label>
        </details>

        <div class="form-actions">
            <button type="submit">Search Archive</button>
            <a class="button-link button-link-secondary" href="/archive.php">Reset</a>
        </div>
    </form>
</section>

<?php if ($archiveSearchRan): ?>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h2>Archive Search Results</h2>
                <p><?= e((string) count($archiveResults)) ?> <?= count($archiveResults) === 1 ? 'entry' : 'entries' ?> found.</p>
            </div>
        </div>

        <?php if (!$archiveResults): ?>
            <p class="empty-state">No entries matched your archive search.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="log-table">
                    <thead>
                        <tr>
                            <th>Date</th>
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
                        <?php foreach ($archiveResults as $entry): ?>
                            <tr class="<?= e(entry_row_class($entry)) ?>">
                                <td>
                                    <a href="/archive.php?date=<?= e($entry['operational_date']) ?>">
                                        <?= e(format_log_date($entry['operational_date'])) ?>
                                    </a>
                                    <br>
                                    <a class="muted-small" href="/log-pdf.php?date=<?= e($entry['operational_date']) ?>">Download PDF for this log</a>
                                </td>
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
                                    <?php $entryReturnPath = $archiveReturnPath; ?>
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
<?php endif; ?>

<?php if ($selectedLogDay): ?>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h2><?= e($selectedLogDay['log_label']) ?></h2>
                <p>Status: <?= e(ucfirst($selectedLogDay['status'])) ?></p>
            </div>
            <div class="panel-actions">
                <a class="button-link" href="/print.php?date=<?= e($selectedLogDay['operational_date']) ?>" target="_blank" rel="noopener">Print View</a>
                <a class="button-link" href="/log-pdf.php?date=<?= e($selectedLogDay['operational_date']) ?>">Download PDF</a>
            </div>
        </div>

        <?php if (!$entries): ?>
            <p class="empty-state">No entries were found for this log.</p>
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
                                    <?php $entryReturnPath = '/archive.php?date=' . $selectedLogDay['operational_date']; ?>
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
<?php elseif ($selectedDate !== ''): ?>
    <section class="panel">
        <p class="empty-state">No log was found for <?= e($selectedDate) ?>.</p>
    </section>
<?php endif; ?>

<section class="panel">
    <h2>Recent Logs</h2>

    <?php if (!$recentLogs): ?>
        <p class="empty-state">No archived logs were found.</p>
    <?php else: ?>
        <div class="recent-log-list">
            <?php foreach ($recentLogs as $recent): ?>
                <a href="/archive.php?date=<?= e($recent['operational_date']) ?>">
                    <strong><?= e(format_log_date($recent['operational_date'])) ?></strong>
                    <span><?= e($recent['log_label']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
