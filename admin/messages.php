<?php
define('URL_PREFIX', '../');
require dirname(__DIR__) . '/includes/bootstrap.php';
require APP_ROOT . '/includes/admin_layout.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $op = (string) ($_POST['op'] ?? '');
    $ids = array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])), fn($i) => $i > 0));
    if ($ids) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        switch ($op) {
            case 'read':   db()->prepare("UPDATE messages SET is_read = 1 WHERE id IN ($ph)")->execute($ids); flash('success', 'Marked as read.'); break;
            case 'unread': db()->prepare("UPDATE messages SET is_read = 0 WHERE id IN ($ph)")->execute($ids); flash('success', 'Marked as unread.'); break;
            case 'delete': db()->prepare("DELETE FROM messages WHERE id IN ($ph)")->execute($ids); flash('success', count($ids) . ' message(s) deleted.'); break;
        }
    }
    $back = (string) ($_POST['back'] ?? 'messages.php');
    redirect(str_starts_with($back, 'messages.php') ? $back : 'messages.php');
}

/* ---- Single message view ---- */
if (isset($_GET['view'])) {
    $st = db()->prepare('SELECT * FROM messages WHERE id = ?');
    $st->execute([(int) $_GET['view']]);
    $m = $st->fetch();
    if (!$m) { flash('error', 'Message not found.'); redirect('messages.php'); }
    if (!$m['is_read']) db()->prepare('UPDATE messages SET is_read = 1 WHERE id = ?')->execute([$m['id']]);

    $replySubject = 'Re: ' . ($m['subject'] ?: 'Your message');
    admin_header('Message', 'messages');
    ?>
    <a class="back-link" href="messages.php"><i class="fa-solid fa-arrow-left"></i> Back to inbox</a>
    <article class="panel message-view">
      <header>
        <span class="avatar lg"><?= e(strtoupper(mb_substr($m['name'], 0, 1))) ?></span>
        <div>
          <h2><?= e($m['subject'] ?: '(no subject)') ?></h2>
          <p class="muted"><b><?= e($m['name']) ?></b> &lt;<?= e($m['email']) ?>&gt; · <?= e(date('M j, Y g:i A', strtotime($m['created_at']))) ?></p>
          <?php if (!empty($m['service'])): ?><span class="pill pill-gold"><i class="fa-solid fa-tag"></i> <?= e($m['service']) ?></span><?php endif; ?>
          <?php if (!empty($m['event_date']) || !empty($m['event_location']) || !empty($m['budget'])): ?>
            <div class="booking-meta">
              <?php if (!empty($m['event_date'])): ?><span><i class="fa-regular fa-calendar"></i> <?= e(date('D, M j, Y', strtotime($m['event_date']))) ?></span><?php endif; ?>
              <?php if (!empty($m['event_location'])): ?><span><i class="fa-solid fa-location-dot"></i> <?= e($m['event_location']) ?></span><?php endif; ?>
              <?php if (!empty($m['budget'])): ?><span><i class="fa-solid fa-wallet"></i> <?= e($m['budget']) ?></span><?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </header>
      <div class="message-body"><?= nl2br(e($m['message'])) ?></div>
      <footer class="form-actions">
        <a class="btn btn-gold" href="mailto:<?= e($m['email']) ?>?subject=<?= rawurlencode($replySubject) ?>"><i class="fa-solid fa-reply"></i> Reply by email</a>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="ids[]" value="<?= (int) $m['id'] ?>"><input type="hidden" name="op" value="unread">
          <button class="btn btn-ghost"><i class="fa-regular fa-envelope"></i> Mark unread</button></form>
        <form method="post" data-confirm="Delete this message?"><?= csrf_field() ?><input type="hidden" name="ids[]" value="<?= (int) $m['id'] ?>"><input type="hidden" name="op" value="delete">
          <button class="btn btn-danger"><i class="fa-solid fa-trash"></i> Delete</button></form>
      </footer>
      <p class="meta muted">IP: <?= e($m['ip'] ?? '-') ?> · <?= e(str_limit((string) $m['user_agent'], 90)) ?></p>
    </article>
    <?php
    admin_footer();
    exit;
}

/* ---- Inbox list ---- */
$filter = ($_GET['filter'] ?? '') === 'unread' ? 'unread' : 'all';
$q = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$per = 20;

$where = [];
$params = [];
if ($filter === 'unread') $where[] = 'is_read = 0';
if ($q !== '') {
    $where[] = '(name LIKE ? OR email LIKE ? OR subject LIKE ? OR message LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$st = db()->prepare("SELECT COUNT(*) FROM messages $whereSql");
$st->execute($params);
$total = (int) $st->fetchColumn();
$pages = max(1, (int) ceil($total / $per));
$page = min($page, $pages);
$offset = ($page - 1) * $per;
$st = db()->prepare("SELECT * FROM messages $whereSql ORDER BY created_at DESC LIMIT $per OFFSET $offset");
$st->execute($params);
$msgs = $st->fetchAll();
$qs = fn(array $extra) => 'messages.php?' . http_build_query(array_filter(array_merge(['filter' => $filter === 'all' ? null : $filter, 'q' => $q ?: null, 'page' => $page > 1 ? $page : null], $extra), fn($v) => $v !== null));
$back = $qs([]);

admin_header('Messages', 'messages');
?>
<div class="toolbar">
  <div class="tabs compact">
    <a href="messages.php" class="<?= $filter === 'all' ? 'active' : '' ?>">All</a>
    <a href="messages.php?filter=unread" class="<?= $filter === 'unread' ? 'active' : '' ?>">Unread <?php if ($u = unread_count()): ?><em class="badge"><?= $u ?></em><?php endif; ?></a>
  </div>
  <form class="search" method="get">
    <?php if ($filter === 'unread'): ?><input type="hidden" name="filter" value="unread"><?php endif; ?>
    <i class="fa-solid fa-magnifying-glass"></i><input name="q" value="<?= e($q) ?>" placeholder="Search messages">
  </form>
</div>

<?php if (!$msgs): ?>
  <div class="panel empty"><i class="fa-regular fa-envelope-open"></i><p><?= $q !== '' ? 'No messages match your search.' : 'No messages yet.' ?></p></div>
<?php else: ?>
<form method="post" class="panel" id="bulkForm">
  <?= csrf_field() ?>
  <input type="hidden" name="back" value="<?= e($back) ?>">
  <div class="bulk-bar">
    <label class="check-inline"><input type="checkbox" data-check-all> Select all</label>
    <button class="btn btn-sm btn-ghost" name="op" value="read"><i class="fa-regular fa-envelope-open"></i> Mark read</button>
    <button class="btn btn-sm btn-ghost" name="op" value="unread"><i class="fa-regular fa-envelope"></i> Mark unread</button>
    <button class="btn btn-sm btn-danger" name="op" value="delete" data-confirm-click="Delete the selected messages?"><i class="fa-solid fa-trash"></i> Delete</button>
  </div>
  <ul class="msg-list full">
    <?php foreach ($msgs as $m): ?>
    <li class="<?= $m['is_read'] ? '' : 'unread' ?>">
      <input type="checkbox" name="ids[]" value="<?= (int) $m['id'] ?>" aria-label="Select message">
      <a href="messages.php?view=<?= (int) $m['id'] ?>">
        <span class="avatar"><?= e(strtoupper(mb_substr($m['name'], 0, 1))) ?></span>
        <div class="msg-main"><b><?= e($m['name']) ?> <small class="muted"><?= e($m['email']) ?></small></b><span><?php if (!empty($m['service'])): ?><em class="pill pill-gold"><?= e($m['service']) ?></em> <?php endif; ?><strong><?= e($m['subject'] ?: '(no subject)') ?></strong> — <?= e(str_limit($m['message'], 110)) ?></span></div>
        <time><?= e(time_ago($m['created_at'])) ?></time>
      </a>
    </li>
    <?php endforeach; ?>
  </ul>
</form>
<?php if ($pages > 1): ?>
  <nav class="pager">
    <?php for ($p = 1; $p <= $pages; $p++): ?>
      <a href="<?= e($qs(['page' => $p > 1 ? $p : null])) ?>" class="<?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
    <?php endfor; ?>
  </nav>
<?php endif; ?>
<?php endif;
admin_footer();
