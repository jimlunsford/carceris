<?php $pageTitle = 'Categories | Carceris'; ?>
<?php require __DIR__ . '/../partials/header.php'; ?>

<section class="page-heading">
    <div>
        <h1>Categories</h1>
        <p>Manage the category list used by log entries.</p>
    </div>
</section>

<section class="panel">
    <h2>Add Category</h2>

    <form method="post" action="/admin/categories.php" class="entry-form category-create-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">

        <label>
            <span>Name</span>
            <input type="text" name="name" maxlength="80" required>
        </label>

        <label class="checkbox-row">
            <input type="checkbox" name="is_active" value="1" checked>
            <span>Active</span>
        </label>

        <div class="form-actions">
            <button type="submit">Add Category</button>
        </div>
    </form>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Existing Categories</h2>
            <p>Use Move Up and Move Down to control the order shown on the log entry form.</p>
        </div>
    </div>

    <?php if (!$categories): ?>
        <p class="empty-state">No categories found.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="log-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Move</th>
                        <th>Edit</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $lastIndex = count($categories) - 1; ?>
                    <?php foreach ($categories as $index => $category): ?>
                        <tr>
                            <td><?= e((string) ($index + 1)) ?></td>
                            <td><strong><?= e($category['name']) ?></strong></td>
                            <td><?= (int) $category['is_active'] === 1 ? 'Active' : 'Inactive' ?></td>
                            <td>
                                <div class="category-move-actions">
                                    <form method="post" action="/admin/categories.php">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="move">
                                        <input type="hidden" name="category_id" value="<?= e((string) $category['id']) ?>">
                                        <input type="hidden" name="direction" value="up">
                                        <button type="submit" <?= $index === 0 ? 'disabled' : '' ?>>Move Up</button>
                                    </form>

                                    <form method="post" action="/admin/categories.php">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="move">
                                        <input type="hidden" name="category_id" value="<?= e((string) $category['id']) ?>">
                                        <input type="hidden" name="direction" value="down">
                                        <button type="submit" <?= $index === $lastIndex ? 'disabled' : '' ?>>Move Down</button>
                                    </form>
                                </div>
                            </td>
                            <td>
                                <form method="post" action="/admin/categories.php" class="inline-edit-form category-edit-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="category_id" value="<?= e((string) $category['id']) ?>">

                                    <label>
                                        <span>Name</span>
                                        <input type="text" name="name" value="<?= e($category['name']) ?>" maxlength="80" required>
                                    </label>

                                    <label class="checkbox-row">
                                        <input type="checkbox" name="is_active" value="1" <?= (int) $category['is_active'] === 1 ? 'checked' : '' ?>>
                                        <span>Active</span>
                                    </label>

                                    <button type="submit">Save</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../partials/footer.php'; ?>
