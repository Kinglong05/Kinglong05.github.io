<?php
define('URL_PREFIX', '../');
require dirname(__DIR__) . '/includes/bootstrap.php';
require APP_ROOT . '/includes/admin_layout.php';
$admin = require_login();

$errors = [];
$form = ['username' => $admin['username'], 'email' => $admin['email']];

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
<?php admin_footer();
