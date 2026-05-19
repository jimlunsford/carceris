<?php require __DIR__ . '/partials/header.php'; ?>

<section class="auth-card">
    <h1>Sign out?</h1>
    <p>You are signed in as <?= e($user['display_name'] ?? $user['username'] ?? 'current user') ?>.</p>

    <form method="post" action="/logout.php" class="form-stack">
        <?= csrf_field() ?>

        <div class="form-actions">
            <button type="submit">Logout</button>
            <a class="button-link button-link-secondary" href="/index.php">Cancel</a>
        </div>
    </form>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
