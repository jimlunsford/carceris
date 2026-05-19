<?php
$loginName = carceris_login_brand_name();
$loginTagline = carceris_login_brand_tagline();
$pageTitle = 'Login | ' . $loginName;
?>
<?php require __DIR__ . '/partials/header.php'; ?>

<section class="auth-card">
    <h1>Sign in to <?= e($loginName) ?></h1>
    <p><?= e($loginTagline) ?></p>

    <form method="post" action="/login.php" class="form-stack">
        <?= csrf_field() ?>

        <label>
            <span>Username</span>
            <input type="text" name="username" autocomplete="username" required autofocus>
        </label>

        <label>
            <span>Password</span>
            <input type="password" name="password" autocomplete="current-password" required>
        </label>

        <button type="submit">Sign In</button>
    </form>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
