<?php
define('URL_PREFIX', '../');
require dirname(__DIR__) . '/includes/bootstrap.php';
require APP_ROOT . '/includes/admin_layout.php';
require APP_ROOT . '/includes/publish.php';
require_login();

@set_time_limit(180);
$allowed = publish_allowed();
$report = null;
$log = [];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $allowed) {
    require_csrf();
    try {
        $report = build_static_site();
        if (($_POST['op'] ?? '') === 'publish') {
            $log = push_static_site();
            save_setting('last_published', date('c'));
        }
    } catch (Throwable $ex) {
        $error = $ex->getMessage();
    }
}

$live = publish_site_url();
$last = setting('last_published');
$hasFormspree = setting('formspree_id') !== '';
admin_header('Publish', 'publish');
?>
<div class="panel publish">
  <div class="publish-head">
    <span class="stat-ico"><i class="fa-brands fa-github"></i></span>
    <div>
      <h2>Publish to GitHub Pages</h2>
      <p class="muted">Your live site: <a class="link" href="<?= e($live) ?>" target="_blank" rel="noopener"><?= e($live) ?></a>
        <?php if ($last): ?> · last published <?= e(time_ago(date('Y-m-d H:i:s', strtotime($last)))) ?><?php endif; ?></p>
    </div>
  </div>

  <?php if (!$allowed): ?>
    <div class="alert alert-warn"><i class="fa-solid fa-triangle-exclamation"></i><span>Publishing only works on your own computer (XAMPP, opened as <code>localhost</code>) in a folder connected to GitHub.</span></div>
  <?php else: ?>
    <ol class="publish-steps">
      <li>Edit anything in the admin (photos, gallery, text...).</li>
      <li>Click <b>Publish</b>. Your homepage is saved as a static page with its photos and pushed to GitHub.</li>
      <li>About 1 minute later, <b><?= e(parse_url($live, PHP_URL_HOST)) ?></b> shows the same thing you see on XAMPP.</li>
    </ol>

    <?php if (!$hasFormspree): ?>
      <div class="alert alert-warn"><i class="fa-solid fa-envelope"></i><span>
        <b>Contact form on the live site:</b> GitHub Pages can't run PHP, so the form will open the visitor's email app.
        To receive messages directly, create a free form at <a href="https://formspree.io" target="_blank" rel="noopener">formspree.io</a> and paste its ID in
        <a href="settings.php?tab=contact">Settings → Contact</a>.</span></div>
    <?php endif; ?>

    <form method="post" class="form-actions" data-publish>
      <?= csrf_field() ?>
      <button class="btn btn-gold" name="op" value="publish"><i class="fa-solid fa-cloud-arrow-up"></i> Publish now</button>
      <button class="btn btn-ghost" name="op" value="build"><i class="fa-solid fa-hammer"></i> Build only (no upload)</button>
      <span class="muted publish-wait" hidden><i class="fa-solid fa-spinner fa-spin"></i> Working... this can take up to a minute.</span>
    </form>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i><span>Publishing failed. Details below.<br>
      If it says "Authentication failed" or "could not read Username", open a terminal in the project folder and run <code>git push</code> once to sign in, then try again.</span></div>
    <pre class="log"><?= e($error) ?></pre>
  <?php elseif ($report): ?>
    <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i><span>
      <?= $log ? 'Published! Your live site updates in about a minute.' : 'Built index.html (not uploaded).' ?>
      <?= (int) $report['files'] ?> media files (<?= e((string) $report['mb']) ?> MB). Contact form: <?= e($report['form']) ?>.</span></div>
    <?php if ($report['missing']): ?>
      <div class="alert alert-warn"><i class="fa-solid fa-triangle-exclamation"></i><span>Missing files (skipped): <?= e(implode(', ', $report['missing'])) ?></span></div>
    <?php endif; ?>
    <?php if ($log): ?>
      <p><a class="btn btn-outline" href="<?= e($live) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i> Open live site</a></p>
      <details><summary class="muted">Technical log</summary><pre class="log"><?= e(implode("\n\n", $log)) ?></pre></details>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php admin_footer();
