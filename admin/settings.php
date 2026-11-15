<?php
define('URL_PREFIX', '../');
require dirname(__DIR__) . '/includes/bootstrap.php';
require APP_ROOT . '/includes/admin_layout.php';
require_login();

$schema = require APP_ROOT . '/includes/settings_schema.php';
$tab = (string) ($_GET['tab'] ?? 'general');
if (!isset($schema[$tab])) $tab = 'general';
$group = $schema[$tab];
$current = settings();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    save_setting('content_changed_at', date('c')); // for the "unpublished changes" reminder
    $tx = ['new' => [], 'old' => []];
    $values = [];
    foreach ($group['fields'] as $name => $def) {
        $values[$name] = collect_field($name, $def, $current[$name] ?? null, $errors, $tx);
    }
    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            foreach ($values as $k => $v) {
                save_setting($k, $v === null ? '' : (string) $v);
            }
            $pdo->commit();
            finish_uploads($tx);
            flash('success', $group['label'] . ' settings saved.');
            redirect('settings.php?tab=' . urlencode($tab));
        } catch (Throwable $ex) {
            $pdo->rollBack();
            rollback_uploads($tx);
            $errors[] = 'Could not save: ' . (APP_DEBUG ? $ex->getMessage() : 'database error.');
        }
    } else {
        rollback_uploads($tx);
    }
    // Re-show submitted values
    foreach ($values as $k => $v) {
        if (!in_array($group['fields'][$k]['type'], ['image', 'file', 'video'], true)) $current[$k] = (string) $v;
    }
}

admin_header('Site Settings', 'settings');
?>
<div class="tabs">
  <?php foreach ($schema as $key => $g): ?>
    <a href="?tab=<?= e($key) ?>" class="<?= $key === $tab ? 'active' : '' ?>"><i class="<?= e($g['icon']) ?>"></i> <?= e($g['label']) ?></a>
  <?php endforeach; ?>
</div>

<?php if ($errors): ?>
  <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i><span><?= implode('<br>', array_map('e', $errors)) ?></span></div>
<?php endif; ?>

<form class="panel form" method="post" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <div class="form-grid">
    <?php foreach ($group['fields'] as $name => $def) render_field($name, $def, $current[$name] ?? ''); ?>
  </div>
  <div class="form-actions">
    <button class="btn btn-gold" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save changes</button>
    <a class="btn btn-ghost" href="../#<?= e($tab === 'general' || $tab === 'seo' ? 'home' : $tab) ?>" target="_blank" rel="noopener">Preview <i class="fa-solid fa-arrow-up-right-from-square"></i></a>
  </div>
</form>
<?php admin_footer();
