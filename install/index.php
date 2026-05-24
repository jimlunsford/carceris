<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$configLocalPath = $root . '/app/config/config.local.php';
$lockPath = $root . '/storage/installed.lock';
$schemaPath = $root . '/database/schema.sql';
$seedPath = $root . '/database/seed.sql';

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; base-uri 'self'; frame-ancestors 'self'; form-action 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; object-src 'none'");
}

function h(?string $value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function v(string $key, string $default = ''): string { return trim((string)($_POST[$key] ?? $default)); }
function installed(string $config, string $lock): bool { return is_file($config) && is_file($lock); }

function split_sql(string $sql): array {
    $sql = preg_replace('/^\s*(--|#).*$/m', '', $sql) ?? $sql;
    $parts = array_map('trim', explode(';', $sql));
    return array_values(array_filter($parts, static fn($s) => $s !== ''));
}

function run_sql_file(PDO $pdo, string $path): void {
    $sql = file_get_contents($path);
    if ($sql === false) { throw new RuntimeException('Could not read ' . basename($path)); }
    foreach (split_sql($sql) as $statement) { $pdo->exec($statement); }
}

function write_config(string $path, array $config): void {
    $content = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($config, true) . ";\n";
    if (file_put_contents($path, $content, LOCK_EX) === false) {
        throw new RuntimeException('Could not write app/config/config.local.php.');
    }
}

function migration_version_from_file(string $filename): string {
    return preg_match('/^(\d+\.\d+\.\d+)-/', basename($filename), $matches) ? $matches[1] : '0.0.0';
}

function baseline_migrations(PDO $pdo, string $root, string $currentVersion, ?int $adminId = null): void {
    $migrationDir = $root . '/database/migrations';
    $files = glob($migrationDir . '/*.sql') ?: [];
    usort($files, static fn($a, $b) => strnatcasecmp(basename($a), basename($b)));

    $stmt = $pdo->prepare(
        'INSERT INTO schema_migrations (version, migration_file, checksum, executed_by, status)
         VALUES (:version, :migration_file, :checksum, :executed_by, "baseline")
         ON DUPLICATE KEY UPDATE checksum = VALUES(checksum), status = "baseline"'
    );

    foreach ($files as $path) {
        $file = basename($path);
        $version = migration_version_from_file($file);

        if (version_compare($version, $currentVersion, '>')) {
            continue;
        }

        $stmt->execute([
            'version' => $version,
            'migration_file' => $file,
            'checksum' => hash_file('sha256', $path) ?: hash('sha256', $file),
            'executed_by' => $adminId,
        ]);
    }
}

$isInstalled = installed($configLocalPath, $lockPath);
$errors = [];
$success = false;

$checks = [
    'PHP 8.1 or newer' => version_compare(PHP_VERSION, '8.1.0', '>='),
    'PDO extension loaded' => extension_loaded('pdo'),
    'PDO MySQL extension loaded' => extension_loaded('pdo_mysql'),
    'app/config writable' => is_writable($root . '/app/config'),
    'storage writable' => is_writable($root . '/storage'),
    'database/schema.sql exists' => is_file($schemaPath),
    'database/seed.sql exists' => is_file($seedPath),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isInstalled) {
    foreach ($checks as $label => $passed) {
        if (!$passed) { $errors[] = 'Server check failed: ' . $label; }
    }

    $dbHost = v('db_host', '127.0.0.1');
    $dbPort = (int)v('db_port', '3306');
    $dbName = v('db_name');
    $dbUser = v('db_user');
    $dbPass = (string)($_POST['db_pass'] ?? '');
    $facility = v('facility_name', 'Correctional Facility');
    $timezone = v('timezone', 'America/Indiana/Indianapolis');
    $environmentMode = v('environment_mode', 'testing');
    $adminUser = v('admin_username');
    $adminName = v('admin_display_name');
    $adminPass = (string)($_POST['admin_password'] ?? '');
    $adminPass2 = (string)($_POST['admin_password_confirm'] ?? '');

    if ($dbHost === '' || $dbName === '' || $dbUser === '') { $errors[] = 'Database host, name, and username are required.'; }
    if ($dbPort <= 0 || $dbPort > 65535) { $errors[] = 'Database port is invalid.'; }
    if ($facility === '') { $errors[] = 'Facility name is required.'; }
    if (!in_array($timezone, timezone_identifiers_list(), true)) { $errors[] = 'Timezone is invalid.'; }
    if (!in_array($environmentMode, ['testing', 'internal', 'production'], true)) { $errors[] = 'Environment mode is invalid.'; }
    if (!preg_match('/^[a-zA-Z0-9_.-]{3,80}$/', $adminUser)) { $errors[] = 'Admin username must be 3 to 80 characters and use only letters, numbers, dots, dashes, and underscores.'; }
    if ($adminName === '') { $errors[] = 'Admin display name is required.'; }
    if (strlen($adminPass) < 12) { $errors[] = 'Admin password must be at least 12 characters.'; }
    if ($adminPass !== $adminPass2) { $errors[] = 'Admin passwords do not match.'; }

    if (!$errors) {
        try {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $dbHost, $dbPort, $dbName);
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            run_sql_file($pdo, $schemaPath);
            run_sql_file($pdo, $seedPath);

            $stmt = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
            foreach (['facility_name' => $facility, 'timezone' => $timezone, 'environment_mode' => $environmentMode] as $k => $val) {
                $stmt->execute(['k' => $k, 'v' => $val]);
            }

            $exists = $pdo->prepare('SELECT id FROM users WHERE username = :u LIMIT 1');
            $exists->execute(['u' => $adminUser]);
            if ($exists->fetch()) { throw new RuntimeException('That admin username already exists.'); }

            $insert = $pdo->prepare('INSERT INTO users (username, password_hash, display_name, role, is_active) VALUES (:u, :p, :d, "admin", 1)');
            $insert->execute(['u' => $adminUser, 'p' => password_hash($adminPass, PASSWORD_DEFAULT), 'd' => $adminName]);
            $adminId = (int) $pdo->lastInsertId();

            baseline_migrations($pdo, $root, '0.6.14', $adminId);

            write_config($configLocalPath, [
                'app' => [
                    'name' => 'Carceris',
                    'tagline' => 'Secure daily logging for correctional facilities.',
                    'version' => '0.6.14',
                    'timezone' => $timezone,
                    'session_name' => 'carceris_session',
                    'environment_mode' => $environmentMode,
                    'debug' => false,
                    'force_https' => $environmentMode === 'production',
                ],
                'database' => [
                    'host' => $dbHost,
                    'port' => $dbPort,
                    'name' => $dbName,
                    'user' => $dbUser,
                    'pass' => $dbPass,
                    'charset' => 'utf8mb4',
                ],
            ]);

            if (file_put_contents($lockPath, 'Installed: ' . date('c') . PHP_EOL, LOCK_EX) === false) {
                throw new RuntimeException('Could not write storage/installed.lock.');
            }

            $success = true;
            $isInstalled = true;
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$tzs = ['America/Indiana/Indianapolis','America/New_York','America/Chicago','America/Detroit','America/Kentucky/Louisville','UTC'];
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Install Carceris</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{--bg:#f4f5f7;--panel:#fff;--text:#111827;--muted:#6b7280;--line:#d1d5db;--dark:#101827;--good:#166534;--bad:#991b1b}*{box-sizing:border-box}body{background:var(--bg);color:var(--text);font-family:Arial,Helvetica,sans-serif;line-height:1.5;margin:0}header{background:var(--dark);color:#fff;padding:22px}header h1{margin:0}header p{color:#cbd5e1;margin:4px 0 0}main{margin:0 auto;max-width:920px;padding:24px}.panel{background:var(--panel);border:1px solid var(--line);border-radius:14px;box-shadow:0 12px 28px rgba(15,23,42,.05);margin-bottom:20px;padding:20px}.checks{display:grid;gap:8px}.check{align-items:center;border:1px solid var(--line);border-radius:10px;display:flex;justify-content:space-between;padding:10px 12px}.pass{color:var(--good);font-weight:700}.fail{color:var(--bad);font-weight:700}form{display:grid;gap:18px}fieldset{border:1px solid var(--line);border-radius:12px;display:grid;gap:14px;margin:0;padding:16px}legend{font-weight:800;padding:0 6px}.grid{display:grid;gap:14px;grid-template-columns:repeat(2,minmax(0,1fr))}label{display:grid;gap:6px}label span{color:#1f2937;font-size:13px;font-weight:700}input,select{border:1px solid var(--line);border-radius:10px;font:inherit;padding:10px 11px;width:100%}button,.button{background:var(--dark);border:1px solid var(--dark);border-radius:10px;color:#fff;cursor:pointer;display:inline-flex;font:inherit;font-weight:700;justify-content:center;padding:11px 14px;text-decoration:none}.errors{background:#fee2e2;border:1px solid #fecaca;border-radius:12px;color:var(--bad);padding:12px}.success{background:#dcfce7;border:1px solid #bbf7d0;border-radius:12px;color:var(--good);padding:12px}.muted{color:var(--muted)}@media(max-width:760px){.grid{grid-template-columns:1fr}main{padding:16px}}
</style>
</head>
<body>
<header><h1>Install Carceris</h1><p>Secure daily logging for correctional facilities.</p></header>
<main>
<?php if ($isInstalled && !$success): ?>
<section class="panel"><h2>Carceris is already installed.</h2><p class="muted">The installer is locked. To reinstall, remove <code>storage/installed.lock</code> and <code>app/config/config.local.php</code> manually.</p><a class="button" href="/login.php">Go to Login</a></section>
<?php elseif ($success): ?>
<section class="panel"><div class="success"><strong>Installation complete.</strong> Carceris is ready.</div><p>You can now sign in with the admin account you created.</p><a class="button" href="/login.php">Go to Login</a></section>
<?php else: ?>
<section class="panel"><h2>Server Checks</h2><div class="checks"><?php foreach($checks as $label=>$passed): ?><div class="check"><span><?= h($label) ?></span><span class="<?= $passed?'pass':'fail' ?>"><?= $passed?'Pass':'Fail' ?></span></div><?php endforeach; ?></div></section>
<?php if ($errors): ?><section class="errors"><strong>Installation failed:</strong><ul><?php foreach($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul></section><?php endif; ?>
<section class="panel"><h2>Setup</h2><p class="muted">Use an empty database or a database dedicated to Carceris.</p><form method="post">
<fieldset><legend>Database</legend><div class="grid"><label><span>Database Host</span><input name="db_host" value="<?= h(v('db_host','127.0.0.1')) ?>" required></label><label><span>Database Port</span><input type="number" name="db_port" value="<?= h(v('db_port','3306')) ?>" required></label><label><span>Database Name</span><input name="db_name" value="<?= h(v('db_name')) ?>" required></label><label><span>Database Username</span><input name="db_user" value="<?= h(v('db_user')) ?>" required></label><label><span>Database Password</span><input type="password" name="db_pass"></label></div></fieldset>
<fieldset><legend>Facility</legend><div class="grid"><label><span>Facility Name</span><input name="facility_name" value="<?= h(v('facility_name','Correctional Facility')) ?>" required></label><label><span>Timezone</span><select name="timezone"><?php $sel=v('timezone','America/Indiana/Indianapolis'); foreach($tzs as $tz): ?><option value="<?= h($tz) ?>" <?= $sel===$tz?'selected':'' ?>><?= h($tz) ?></option><?php endforeach; ?></select></label><label><span>Environment Mode</span><select name="environment_mode"><?php $envSel=v('environment_mode','testing'); foreach(['testing'=>'Testing','internal'=>'Internal','production'=>'Production'] as $envValue=>$envLabel): ?><option value="<?= h($envValue) ?>" <?= $envSel===$envValue?'selected':'' ?>><?= h($envLabel) ?></option><?php endforeach; ?></select></label></div></fieldset>
<fieldset><legend>First Admin User</legend><div class="grid"><label><span>Username</span><input name="admin_username" value="<?= h(v('admin_username')) ?>" required></label><label><span>Display Name</span><input name="admin_display_name" value="<?= h(v('admin_display_name')) ?>" required></label><label><span>Password</span><input type="password" name="admin_password" required></label><label><span>Confirm Password</span><input type="password" name="admin_password_confirm" required></label></div></fieldset>
<button type="submit">Install Carceris</button></form></section>
<?php endif; ?>
</main>
</body>
</html>
