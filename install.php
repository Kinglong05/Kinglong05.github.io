<?php
/**
 * One-time installer: creates the tables, loads starter content and your admin account.
 * After it runs, it locks itself (config/installed.lock). Delete install.php on the live server.
 */
define('URL_PREFIX', '');
require __DIR__ . '/includes/bootstrap.php';

if (is_installed()) {
    http_response_code(403);
    exit('Already installed. Go to <a href="admin/login.php">admin/login.php</a>. (To reinstall, delete config/installed.lock.)');
}

function run_sql_file(PDO $pdo, string $file): void
{
    $sql = file_get_contents($file);
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    foreach (preg_split('/;\s*(?:\R|$)/', $sql) as $stmt) {
        if (trim($stmt) !== '') $pdo->exec($stmt);
    }
}

// --- Check database connection (try to create the DB locally if it doesn't exist) ---
$dbError = '';
try {
    db();
} catch (PDOException $e) {
    try {
        $server = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $server->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', DB_NAME) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        db();
    } catch (PDOException $e2) {
        $dbError = $e2->getMessage();
    }
}

$writable = is_writable(APP_ROOT . '/config') && (is_writable(APP_ROOT . '/uploads') || @mkdir(APP_ROOT . '/uploads', 0755, true));

// Moved an existing site (imported your database)? Then there's nothing to install: just lock and sign in.
if (!$dbError && $writable) {
    try {
        $hasAdmin = (int) db()->query('SELECT COUNT(*) FROM admins')->fetchColumn() > 0;
    } catch (PDOException $e) {
        $hasAdmin = false; // tables don't exist yet: fresh install
    }
    if ($hasAdmin) {
        file_put_contents(APP_ROOT . '/config/installed.lock', 'Existing database detected ' . date('c'));
        foreach (['images', 'files', 'videos'] as $d) {
            if (!is_dir(APP_ROOT . "/uploads/$d")) @mkdir(APP_ROOT . "/uploads/$d", 0755, true);
        }
        flash('success', 'Existing site detected, so no install was needed. Sign in with your usual admin account.');
        redirect('admin/login.php');
    }
}
$errors = [];
$form = ['username' => 'admin', 'email' => ''];

if (!$dbError && $writable && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $form['username'] = trim((string) ($_POST['username'] ?? ''));
    $form['email'] = trim((string) ($_POST['email'] ?? ''));
    $pw = (string) ($_POST['password'] ?? '');
    $pw2 = (string) ($_POST['password_confirm'] ?? '');

    if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $form['username'])) $errors[] = 'Username must be 3-50 characters: letters, numbers, _ . -';
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (strlen($pw) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($pw !== $pw2) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        try {
            $pdo = db();
            run_sql_file($pdo, APP_ROOT . '/database/schema.sql');
            if ((int) $pdo->query('SELECT COUNT(*) FROM settings')->fetchColumn() === 0) {
                run_sql_file($pdo, APP_ROOT . '/database/seed.sql');
            }
            $st = $pdo->prepare('INSERT INTO admins (username, email, password_hash) VALUES (?, ?, ?)');
            $st->execute([$form['username'], $form['email'], password_hash($pw, PASSWORD_DEFAULT)]);
            file_put_contents(APP_ROOT . '/config/installed.lock', 'Installed ' . date('c'));
            foreach (['images', 'files'] as $d) {
                if (!is_dir(APP_ROOT . "/uploads/$d")) @mkdir(APP_ROOT . "/uploads/$d", 0755, true);
            }
            flash('success', 'Installation complete. Sign in with the account you just created.');
            redirect('admin/login.php');
        } catch (Throwable $ex) {
            $errors[] = 'Install failed: ' . $ex->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Install · Portfolio CMS</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="admin/assets/admin.css?v=1">
</head>
<body class="auth-page">
  <form class="auth-card wide" method="post">
    <div class="auth-brand"><span class="brand-mark"><i class="fa-solid fa-code"></i></span>Portfolio CMS</div>
    <h1>Install</h1>
    <p class="muted">Creates the database tables, loads starter content and your admin account.</p>

    <ul class="checklist install-checks">
      <li class="<?= $dbError ? 'bad' : 'ok' ?>"><i class="fa-solid <?= $dbError ? 'fa-circle-xmark' : 'fa-circle-check' ?>"></i>Database connection (<code><?= e(DB_NAME) ?></code> on <code><?= e(DB_HOST) ?></code>)</li>
      <li class="<?= $writable ? 'ok' : 'bad' ?>"><i class="fa-solid <?= $writable ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>config/ and uploads/ folders writable</li>
      <li class="ok"><i class="fa-solid fa-circle-check"></i>PHP <?= e(PHP_VERSION) ?></li>
    </ul>

    <?php if ($dbError): ?>
      <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i><span>Could not connect to MySQL: <?= e($dbError) ?><br>Check the values in <code>config/config.php</code> and make sure MySQL is running.</span></div>
    <?php elseif (!$writable): ?>
      <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i><span>Make the <code>config/</code> and <code>uploads/</code> folders writable, then refresh.</span></div>
    <?php else: ?>
      <?php if ($errors): ?><div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i><span><?= implode('<br>', array_map('e', $errors)) ?></span></div><?php endif; ?>
      <?= csrf_field() ?>
      <h3 style="margin:18px 0 6px">Create your admin account</h3>
      <div class="field"><label for="username">Username</label><input id="username" name="username" value="<?= e($form['username']) ?>" required autocomplete="username"></div>
      <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" value="<?= e($form['email']) ?>" required></div>
      <div class="field"><label for="password">Password <small class="muted">(min. 8 characters)</small></label><input id="password" type="password" name="password" minlength="8" required autocomplete="new-password"></div>
      <div class="field"><label for="password_confirm">Confirm password</label><input id="password_confirm" type="password" name="password_confirm" minlength="8" required autocomplete="new-password"></div>
      <button class="btn btn-gold btn-block" type="submit">Install now <i class="fa-solid fa-arrow-right"></i></button>
    <?php endif; ?>
  </form>
</body>
</html>
