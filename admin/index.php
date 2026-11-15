<?php
define('URL_PREFIX', '../');
require dirname(__DIR__) . '/includes/bootstrap.php';
require APP_ROOT . '/includes/admin_layout.php';
if (!is_installed()) redirect('../install.php');
$admin = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'dismiss_sample') {
    require_csrf();
    save_setting('sample_notice', '0');
    redirect('index.php');
}

$entities = require APP_ROOT . '/includes/entities.php';
$counts = [];
foreach (array_keys($entities) as $t) {
    $counts[$t] = (int) db()->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
}
$totalMsgs = (int) db()->query('SELECT COUNT(*) FROM messages')->fetchColumn();
$unread = unread_count();
$weekMsgs = (int) db()->query('SELECT COUNT(*) FROM messages WHERE created_at >= (NOW() - INTERVAL 7 DAY)')->fetchColumn();
$recent = db()->query('SELECT id, name, email, subject, message, is_read, created_at FROM messages ORDER BY created_at DESC LIMIT 6')->fetchAll();

// Setup checklist
$s = settings();
$checks = [
    ['Upload your hero portrait', !empty($s['hero_image']), 'settings.php?tab=hero'],
    ['Upload your about photo', !empty($s['about_image']), 'settings.php?tab=about'],
    ['Upload your CV (PDF)', !empty($s['cv_file']), 'settings.php?tab=about'],
    ['Set your real contact email', !empty($s['contact_email']) && $s['contact_email'] !== 'hello@example.com', 'settings.php?tab=contact'],
    ['Add project screenshots', (int) db()->query("SELECT COUNT(*) FROM projects WHERE image IS NOT NULL AND image <> ''")->fetchColumn() > 0, 'manage.php?type=projects'],
    ['Add your own photos & videos', (int) db()->query("SELECT COUNT(*) FROM gallery WHERE (image IS NOT NULL AND image <> '') OR (video_url IS NOT NULL AND video_url <> '')")->fetchColumn() > 0, 'manage.php?type=gallery'],
    ['Add a social share image', !empty($s['og_image']), 'settings.php?tab=seo'],
];
$done = count(array_filter($checks, fn($c) => $c[1]));

require APP_ROOT . '/includes/ga_stats.php';
$ga = ga_stats(isset($_GET['refresh_stats']));
admin_header('Dashboard', 'dashboard');
?>
<div class="welcome">
  <div>
    <h2>Hi, <?= e($admin['username']) ?> 👋</h2>
    <p class="muted">Here's what's happening with your portfolio.<?php if ($admin['last_login']): ?> Last sign-in <?= e(date('M j, Y g:i A', strtotime($admin['last_login']))) ?>.<?php endif; ?></p>
  </div>
  <a class="btn btn-outline" href="../" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i> View live site</a>
</div>

<?php if (($s['sample_notice'] ?? '0') === '1'): ?>
<div class="alert alert-warn notice">
  <i class="fa-solid fa-triangle-exclamation"></i>
  <span><b>Your site is showing sample content.</b> Replace the sample <a href="manage.php?type=gallery">photos & videos</a>, <a href="manage.php?type=experiences">experience</a>, <a href="manage.php?type=testimonials">testimonials</a> and <a href="manage.php?type=projects">projects</a> with your own before you go live. Only publish testimonials from real people.</span>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="dismiss_sample"><button class="btn btn-sm btn-ghost">Got it</button></form>
</div>
<?php endif; ?>

<?php if (!empty($s['stats_url'])): ?>
<div class="alert alert-info notice"><i class="fa-solid fa-chart-line"></i>
  <span><b>Visitor stats:</b> see live visitors, countries, devices and where they came from.</span>
  <a class="btn btn-sm btn-gold" href="<?= e($s['stats_url']) ?>" target="_blank" rel="noopener">Open visitor stats <i class="fa-solid fa-arrow-up-right-from-square"></i></a>
</div>
<?php elseif (empty($s['ga4_id']) && empty($s['goatcounter_code'])): ?>
<div class="alert alert-warn notice"><i class="fa-solid fa-chart-line"></i>
  <span><b>Want to see who visits?</b> Turn on free visitor stats in <a href="settings.php?tab=analytics">Settings → Visitor Stats</a>.</span>
</div>
<?php endif; ?>
<div class="stat-grid">
  <a class="stat" href="messages.php?filter=unread"><span class="stat-ico"><i class="fa-solid fa-envelope"></i></span><div><b><?= $unread ?></b><small>Unread messages</small></div></a>
  <a class="stat" href="messages.php"><span class="stat-ico"><i class="fa-solid fa-inbox"></i></span><div><b><?= $totalMsgs ?></b><small>Total messages · <?= $weekMsgs ?> this week</small></div></a>
  <a class="stat" href="manage.php?type=projects"><span class="stat-ico"><i class="fa-solid fa-briefcase"></i></span><div><b><?= $counts['projects'] ?></b><small>Projects</small></div></a>
  <a class="stat" href="manage.php?type=gallery"><span class="stat-ico"><i class="fa-solid fa-images"></i></span><div><b><?= $counts['gallery'] ?></b><small>Photos & videos</small></div></a>
</div>

<section class="panel visitors">
  <div class="panel-head">
    <h3><i class="fa-solid fa-chart-line"></i> Website visitors</h3>
    <?php if ($ga['ok']): ?><a class="link" href="?refresh_stats=1"><i class="fa-solid fa-rotate"></i> Refresh · updated <?= e(time_ago(date('Y-m-d H:i:s', $ga['fetched']))) ?></a><?php endif; ?>
  </div>
  <?php if ($ga['ok']): ?>
    <div class="v-grid">
      <div class="v-stat live"><span class="v-dot"></span><b><?= (int) $ga['live'] ?></b><small>on your site right now</small></div>
      <?php foreach (['today' => 'Today', 'week' => 'Last 7 days', 'month' => 'Last 28 days'] as $k => $lbl): ?>
        <div class="v-stat"><b><?= number_format($ga[$k][0]) ?></b><small><?= $lbl ?> · <?= number_format($ga[$k][1]) ?> page views</small></div>
      <?php endforeach; ?>
    </div>
    <div class="v-lists">
      <?php foreach (['countries' => ['Top countries', 'fa-earth-asia'], 'devices' => ['Devices', 'fa-mobile-screen'], 'sources' => ['Came from', 'fa-arrow-right-to-bracket']] as $k => [$lbl, $ico]): ?>
        <div><h4><i class="fa-solid <?= $ico ?>"></i> <?= $lbl ?> <small>(28 days)</small></h4>
          <?php if (empty($ga[$k])): ?><p class="muted">No data yet.</p><?php else: $max = max(1, max(array_column($ga[$k], 1))); ?>
            <ul class="v-bars"><?php foreach ($ga[$k] as [$nm, $n]): ?><li><span><?= e($nm === '(direct)' ? 'Typed / bookmark' : ($nm === '(not set)' ? 'Unknown' : ucfirst($nm))) ?></span><b><?= (int) $n ?></b><i style="width: <?= round($n / $max * 100) ?>%"></i></li><?php endforeach; ?></ul>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php elseif ($ga['error'] === 'not_configured'): ?>
    <p class="muted">See visitor numbers here (live visitors, today, this week, countries, devices) without opening Google Analytics.
      One-time setup in <a class="link" href="settings.php?tab=analytics">Settings → Visitor Stats</a> (paste a Google service-account key).</p>
  <?php else: ?>
    <div class="alert alert-warn"><i class="fa-solid fa-triangle-exclamation"></i><span>Couldn't load visitor numbers: <?= e($ga['error']) ?>
      <?= str_contains(strtolower($ga['error']), 'permission') ? '<br>Add the service account\'s email as a <b>Viewer</b> in Google Analytics → Admin → Property access management.' : '' ?>
      <?= str_contains(strtolower($ga['error']), 'api has not been used') || str_contains(strtolower($ga['error']), 'disabled') ? '<br>Enable the "Google Analytics Data API" in your Google Cloud project, wait 2 minutes, then refresh.' : '' ?></span></div>
  <?php endif; ?>
</section>

<div class="dash-grid">
  <section class="panel">
    <div class="panel-head"><h3>Recent messages</h3><a href="messages.php" class="link">View all <i class="fa-solid fa-arrow-right"></i></a></div>
    <?php if (!$recent): ?>
      <div class="empty"><i class="fa-regular fa-envelope-open"></i><p>No messages yet. They'll appear here when someone uses your contact form.</p></div>
    <?php else: ?>
      <ul class="msg-list">
        <?php foreach ($recent as $m): ?>
        <li class="<?= $m['is_read'] ? '' : 'unread' ?>">
          <a href="messages.php?view=<?= (int) $m['id'] ?>">
            <span class="avatar"><?= e(strtoupper(mb_substr($m['name'], 0, 1))) ?></span>
            <div class="msg-main"><b><?= e($m['name']) ?></b><span><?= e($m['subject'] ?: str_limit($m['message'], 70)) ?></span></div>
            <time><?= e(time_ago($m['created_at'])) ?></time>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <section class="panel">
    <div class="panel-head"><h3>Launch checklist</h3><span class="pill"><?= $done ?>/<?= count($checks) ?></span></div>
    <div class="progress"><span style="width: <?= round($done / count($checks) * 100) ?>%"></span></div>
    <ul class="checklist">
      <?php foreach ($checks as [$label, $ok, $href]): ?>
        <li class="<?= $ok ? 'ok' : '' ?>"><i class="fa-solid <?= $ok ? 'fa-circle-check' : 'fa-circle' ?>"></i><a href="<?= e($href) ?>"><?= e($label) ?></a></li>
      <?php endforeach; ?>
    </ul>
    <div class="panel-head" style="margin-top:22px"><h3>Content</h3></div>
    <div class="content-links">
      <?php foreach ($entities as $key => $ent): ?>
        <a href="manage.php?type=<?= e($key) ?>"><i class="<?= e($ent['icon']) ?>"></i><span><?= e($ent['label']) ?></span><b><?= $counts[$key] ?></b></a>
      <?php endforeach; ?>
    </div>
  </section>
</div>
<?php admin_footer();
