<?php
define('URL_PREFIX', '../');
require dirname(__DIR__) . '/includes/bootstrap.php';
require APP_ROOT . '/includes/admin_layout.php';
$admin = require_login();

require APP_ROOT . '/includes/totp.php';
$errors = [];
$form = ['username' => $admin['username'], 'email' => $admin['email']];
$st = db()->prepare('SELECT totp_secret FROM admins WHERE id = ?');
$st->execute([$admin['id']]);
$has2fa = (string) $st->fetchColumn() !== '';
$op = (string) ($_POST['op'] ?? '');

/* ---- Two-step login ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && str_starts_with($op, '2fa_')) {
    require_csrf();
    if ($op === '2fa_start' && !$has2fa) {
        $_SESSION['totp_setup'] = totp_new_secret();
        redirect('account.php#twostep');
    }
    if ($op === '2fa_confirm' && !$has2fa && !empty($_SESSION['totp_setup'])) {
        if (totp_verify($_SESSION['totp_setup'], (string) ($_POST['code'] ?? ''))) {
            db()->prepare('UPDATE admins SET totp_secret = ? WHERE id = ?')->execute([$_SESSION['totp_setup'], $admin['id']]);
            unset($_SESSION['totp_setup']);
            flash('success', 'Two-step login is ON. From now on you\'ll enter a code from your phone after your password.');
        } else {
            flash('error', 'That code didn\'t match. Make sure your phone\'s time is set automatically, then try the newest code.');
        }
        redirect('account.php#twostep');
    }
    if ($op === '2fa_cancel') {
        unset($_SESSION['totp_setup']);
        redirect('account.php#twostep');
    }
    if ($op === '2fa_disable' && $has2fa) {
        $st = db()->prepare('SELECT password_hash FROM admins WHERE id = ?');
        $st->execute([$admin['id']]);
        if (password_verify((string) ($_POST['current_password'] ?? ''), (string) $st->fetchColumn())) {
            db()->prepare('UPDATE admins SET totp_secret = NULL WHERE id = ?')->execute([$admin['id']]);
            flash('success', 'Two-step login is OFF.');
        } else {
            flash('error', 'Wrong password. Two-step login is still on.');
        }
        redirect('account.php#twostep');
    }
    redirect('account.php');
}
$setupSecret = (!$has2fa && !empty($_SESSION['totp_setup'])) ? $_SESSION['totp_setup'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $form['username'] = trim((string) ($_POST['username'] ?? ''));
    $form['email'] = trim((string) ($_POST['email'] ?? ''));
    $currentPw = (string) ($_POST['current_password'] ?? '');
    $newPw = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    $st = db()->prepare('SELECT password_hash FROM admins WHERE id = ?');
    $st->execute([$admin['id']]);
    $hash = (string) $st->fetchColumn();

    if (!password_verify($currentPw, $hash)) $errors[] = 'Your current password is incorrect.';
    if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $form['username'])) $errors[] = 'Username must be 3-50 characters: letters, numbers, _ . -';
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if ($newPw !== '') {
        if (strlen($newPw) < 8) $errors[] = 'New password must be at least 8 characters.';
        if ($newPw !== $confirm) $errors[] = 'New passwords do not match.';
    }
    if (!$errors) {
        $st = db()->prepare('SELECT COUNT(*) FROM admins WHERE (username = ? OR email = ?) AND id <> ?');
        $st->execute([$form['username'], $form['email'], $admin['id']]);
        if ((int) $st->fetchColumn() > 0) $errors[] = 'That username or email is already used by another admin.';
    }
    if (!$errors) {
        db()->prepare('UPDATE admins SET username = ?, email = ? WHERE id = ?')->execute([$form['username'], $form['email'], $admin['id']]);
        if ($newPw !== '') {
            db()->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')->execute([password_hash($newPw, PASSWORD_DEFAULT), $admin['id']]);
            session_regenerate_id(true);
        }
        flash('success', 'Account updated.' . ($newPw !== '' ? ' Your password has been changed.' : ''));
        redirect('account.php');
    }
}

admin_header('My Account', 'account');
?>
<?php if ($errors): ?>
  <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i><span><?= implode('<br>', array_map('e', $errors)) ?></span></div>
<?php endif; ?>
<form class="panel form narrow" method="post" autocomplete="off">
  <?= csrf_field() ?>
  <h3>Profile</h3>
  <div class="form-grid">
    <div class="field half"><label for="username">Username</label><input id="username" name="username" value="<?= e($form['username']) ?>" required autocomplete="username"></div>
    <div class="field half"><label for="email">Email</label><input id="email" type="email" name="email" value="<?= e($form['email']) ?>" required></div>
  </div>
  <h3>Change password <small class="muted">(optional)</small></h3>
  <div class="form-grid">
    <div class="field half"><label for="new_password">New password</label><input id="new_password" type="password" name="new_password" minlength="8" autocomplete="new-password"></div>
    <div class="field half"><label for="confirm_password">Confirm new password</label><input id="confirm_password" type="password" name="confirm_password" autocomplete="new-password"></div>
  </div>
  <hr>
  <div class="form-grid">
    <div class="field"><label for="current_password">Current password <b class="req">*</b></label><input id="current_password" type="password" name="current_password" required autocomplete="current-password"><small class="help">Required to save any change.</small></div>
  </div>
  <div class="form-actions"><button class="btn btn-gold"><i class="fa-solid fa-floppy-disk"></i> Save account</button></div>
</form>

<section class="panel form narrow twostep" id="twostep">
  <h3><i class="fa-solid fa-shield-halved"></i> Two-step login <?= $has2fa ? '<span class="pill pill-gold">ON</span>' : '<span class="pill">OFF</span>' ?></h3>
  <?php if ($has2fa): ?>
    <p class="muted">After your password, you enter a 6-digit code from your phone. Even if someone learns your password, they can't sign in without your phone.</p>
    <form method="post" class="form-grid" data-confirm="Turn off two-step login?">
      <?= csrf_field() ?><input type="hidden" name="op" value="2fa_disable">
      <div class="field half"><label for="pw2">Current password</label><input id="pw2" type="password" name="current_password" required autocomplete="current-password"></div>
      <div class="field half" style="align-self:end"><button class="btn btn-danger"><i class="fa-solid fa-lock-open"></i> Turn off</button></div>
    </form>
  <?php elseif ($setupSecret): ?>
    <ol class="steps-list">
      <li>Install <b>Google Authenticator</b> or <b>Microsoft Authenticator</b> on your phone.</li>
      <li>In the app tap <b>+</b> → <b>Scan a QR code</b> and scan this:</li>
    </ol>
    <div class="qr-wrap"><div class="qr" data-qr="<?= e(totp_uri($setupSecret, $admin['username'], setting('logo_text', 'Portfolio') . ' admin')) ?>" data-size="190"></div>
      <div><small class="muted">Can't scan? Enter this key in the app:</small><br><code class="secret"><?= e(trim(chunk_split($setupSecret, 4, ' '))) ?></code></div></div>
    <form method="post" class="form-grid">
      <?= csrf_field() ?><input type="hidden" name="op" value="2fa_confirm">
      <div class="field half"><label for="code">3. Type the 6-digit code the app shows</label><input id="code" name="code" inputmode="numeric" maxlength="7" required autocomplete="one-time-code" class="code-input"></div>
      <div class="field half" style="align-self:end"><button class="btn btn-gold"><i class="fa-solid fa-check"></i> Turn on</button></div>
    </form>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="op" value="2fa_cancel"><button class="btn btn-ghost btn-sm">Cancel</button></form>
    <p class="help"><b>Keep your phone safe.</b> If you lose it, you can turn this off in phpMyAdmin: table <code>admins</code> → set <code>totp_secret</code> to empty.</p>
  <?php else: ?>
    <p class="muted">Protect your admin with a code from your phone in addition to your password. Recommended before your site goes live on a PHP host.</p>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="op" value="2fa_start"><button class="btn btn-gold"><i class="fa-solid fa-shield-halved"></i> Set up two-step login</button></form>
  <?php endif; ?>
</section>
<?php admin_footer();
