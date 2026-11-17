<?php
define('URL_PREFIX', '../');
require dirname(__DIR__) . '/includes/bootstrap.php';
if (!is_installed()) redirect('../install.php');
if (current_admin()) redirect('index.php');

$error = '';
$login = '';
$step2 = pending_2fa() !== null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['op'] ?? '') === 'cancel_2fa') {
    unset($_SESSION['pending_2fa']);
    redirect('login.php');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['op'] ?? '') === '2fa') {
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $error = 'Your session expired. Please sign in again.';
    } elseif (too_many_login_attempts(client_ip())) {
        $error = 'Too many failed attempts. Please wait ' . LOGIN_WINDOW_MINUTES . ' minutes and try again.';
    } elseif (verify_2fa((string) ($_POST['code'] ?? ''))) {
        redirect('index.php');
    } else {
        usleep(400000);
        $step2 = pending_2fa() !== null;
        $error = $step2 ? 'That code is not right. Check your authenticator app and try again.' : 'Took too long. Please sign in again.';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim((string) ($_POST['login'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } elseif (too_many_login_attempts(client_ip())) {
        $error = 'Too many failed attempts. Please wait ' . LOGIN_WINDOW_MINUTES . ' minutes and try again.';
    } elseif ($login === '' || $password === '') {
        $error = 'Enter your username and password.';
    } elseif (attempt_login($login, $password)) {
        redirect(pending_2fa() !== null ? 'login.php' : 'index.php');
    } else {
        usleep(400000); // slow down brute force
        $error = 'Incorrect username or password.';
    }
}
$logo = setting('logo_text', 'PORTFOLIO');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Sign in · Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="assets/admin.css?v=<?= filemtime(APP_ROOT . '/admin/assets/admin.css') ?>">
</head>
<body class="auth-page">
  <?php if ($step2): ?>
  <form class="auth-card" method="post" autocomplete="off">
    <div class="auth-brand"><span class="brand-mark"><i class="fa-solid fa-shield-halved"></i></span><?= e($logo) ?></div>
    <h1>Two-step check</h1>
    <p class="muted">Open your authenticator app and enter the 6-digit code for your portfolio.</p>
    <?php if ($error): ?><div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i><span><?= e($error) ?></span></div><?php endif; ?>
    <?= csrf_field() ?>
    <input type="hidden" name="op" value="2fa">
    <div class="field">
      <label for="code">6-digit code</label>
      <input id="code" name="code" inputmode="numeric" pattern="[0-9 ]{6,7}" maxlength="7" autocomplete="one-time-code" required autofocus class="code-input">
    </div>
    <button class="btn btn-gold btn-block" type="submit">Verify <i class="fa-solid fa-arrow-right"></i></button>
    <button class="back-link link-plain" type="submit" name="op" value="cancel_2fa" formnovalidate><i class="fa-solid fa-arrow-left"></i> Use a different account</button>
  </form>
  <?php else: ?>
  <form class="auth-card" method="post" autocomplete="on">
    <div class="auth-brand"><span class="brand-mark"><i class="fa-solid fa-code"></i></span><?= e($logo) ?></div>
    <h1>Welcome back</h1>
    <p class="muted">Sign in to manage your portfolio.</p>
    <?php if ($error): ?><div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i><span><?= e($error) ?></span></div><?php endif; ?>
    <?= csrf_field() ?>
    <div class="field">
      <label for="login">Username or email</label>
      <input id="login" name="login" value="<?= e($login) ?>" autocomplete="username" required autofocus>
    </div>
    <div class="field">
      <label for="password">Password</label>
      <div class="pw-wrap">
        <input id="password" type="password" name="password" autocomplete="current-password" required>
        <button type="button" class="pw-toggle" data-pw-toggle aria-label="Show password"><i class="fa-regular fa-eye"></i></button>
      </div>
    </div>
    <button class="btn btn-gold btn-block" type="submit">Sign in <i class="fa-solid fa-arrow-right"></i></button>
    <a class="back-link" href="../"><i class="fa-solid fa-arrow-left"></i> Back to site</a>
  </form>
  <?php endif; ?>
<script src="assets/admin.js?v=<?= filemtime(APP_ROOT . '/admin/assets/admin.js') ?>"></script>
</body>
</html>
