<?php
/**
 * Private client gallery (proofing). Needs a PHP host: never published to GitHub Pages / Vercel.
 * Link: proof.php?g=<link-name>  + the access code you give your client.
 */
define('URL_PREFIX', '');
require __DIR__ . '/includes/bootstrap.php';
if (!is_installed()) redirect('install.php');

$slug = (string) ($_GET['g'] ?? '');
$st = db()->prepare('SELECT * FROM proofs WHERE slug = ? AND is_visible = 1');
$st->execute([$slug]);
$g = $st->fetch();
$expired = $g && $g['expires_on'] && strtotime($g['expires_on'] . ' 23:59:59') < time();
$key = 'proof_ok_' . ($g['id'] ?? 0);
$error = '';

if ($g && !$expired && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $error = 'Please try again.';
    } elseif (too_many_login_attempts(client_ip())) {
        $error = 'Too many wrong codes. Please wait ' . LOGIN_WINDOW_MINUTES . ' minutes.';
    } elseif (hash_equals(strtolower(trim($g['access_code'])), strtolower(trim((string) ($_POST['code'] ?? ''))))) {
        $_SESSION[$key] = true;
        redirect('proof.php?g=' . rawurlencode($slug));
    } else {
        db()->prepare('INSERT INTO login_attempts (ip) VALUES (?)')->execute([client_ip()]);
        usleep(400000);
        $error = 'That access code is not right.';
    }
}
$unlocked = $g && !$expired && !empty($_SESSION[$key]);
$photos = $unlocked ? album_rows('proof_photos', 'proof_id', (int) $g['id']) : [];
$name = setting('owner_name', 'Portfolio');
$logo = setting('logo_text', 'PORTFOLIO');
if (!$g) http_response_code(404);
header('X-Robots-Tag: noindex, nofollow');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($g ? $g['title'] : 'Gallery not found') ?> · <?= e($name) ?></title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><path d='M16 2l12 7v14l-12 7-12-7V9z' fill='%23f5b335'/></svg>">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Outfit:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">
<script>(function(d){d.classList.add('js');try{if(localStorage.getItem('theme')==='light')d.setAttribute('data-theme','light')}catch(e){}})(document.documentElement)</script>
</head>
<body class="proof-page">
<div class="bg-fx" aria-hidden="true"><span class="glow g1"></span><span class="glow g2"></span></div>
<header class="nav" id="nav"><div class="container nav-inner">
  <a class="logo" href="./"><span class="logo-mark"><svg viewBox="0 0 32 32" aria-hidden="true"><path d="M16 2.5 28 9.5v13L16 29.5 4 22.5v-13z" fill="none" stroke="currentColor" stroke-width="2"/><path d="M13 12l-4 4 4 4M19 12l4 4-4 4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span><span><?= e($logo) ?></span></a>
  <span class="proof-lock"><i class="fa-solid fa-lock"></i> Private gallery</span>
</div></header>

<main class="container post-wrap proof-wrap">
<?php if (!$g || $expired): ?>
  <section class="card proof-gate">
    <i class="fa-regular fa-folder-open proof-ico"></i>
    <h1><?= $expired ? 'This gallery has expired' : 'Gallery not found' ?></h1>
    <p class="muted"><?= $expired ? 'Please message me if you still need your photos.' : 'Check the link you received.' ?></p>
    <a class="btn btn-gold" href="./#contact">Contact <?= e($name) ?></a>
  </section>
<?php elseif (!$unlocked): ?>
  <form class="card proof-gate" method="post">
    <?php if ($g['cover']): ?><img class="proof-cover" src="<?= e(url($g['cover'])) ?>" alt=""><?php endif; ?>
    <i class="fa-solid fa-lock proof-ico"></i>
    <h1><?= e($g['title']) ?></h1>
    <p class="muted">Enter the access code you received to view <?= $g['client_name'] ? e($g['client_name']) . '\'s' : 'your' ?> photos.</p>
    <?php if ($error): ?><p class="form-status"><span class="error"><?= e($error) ?></span></p><?php endif; ?>
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <label class="fl"><span class="sr">Access code</span><input name="code" placeholder="Access code" required autocomplete="off" autofocus></label>
    <button class="btn btn-gold" type="submit">Open gallery <i class="fa-solid fa-arrow-right"></i></button>
  </form>
<?php else: ?>
  <section class="card section">
    <div class="proof-head">
      <div>
        <h1><?= e($g['title']) ?></h1>
        <p class="muted"><?= count($photos) ?> photo<?= count($photos) === 1 ? '' : 's' ?><?= $g['expires_on'] ? ' · available until ' . e(date('F j, Y', strtotime($g['expires_on']))) : '' ?></p>
      </div>
    </div>
    <?php if ($g['message']): ?><p class="proof-msg"><i class="fa-regular fa-envelope-open"></i> <?= hl($g['message']) ?></p><?php endif; ?>
    <?php if (!$photos): ?>
      <p class="muted">Your photos are being prepared. Please check back soon!</p>
    <?php else: ?>
      <div class="proof-grid">
        <?php foreach ($photos as $i => $p): $src = url($p['image']); ?>
          <figure class="proof-item">
            <a href="<?= e($src) ?>" target="_blank" rel="noopener" aria-label="Open photo <?= $i + 1 ?>"><img src="<?= e($src) ?>" alt="Photo <?= $i + 1 ?>" loading="lazy"></a>
            <figcaption><span>#<?= $i + 1 ?></span>
              <?php if ($g['allow_download']): ?><a class="sq-btn" href="<?= e($src) ?>" download aria-label="Download photo <?= $i + 1 ?>"><i class="fa-solid fa-download"></i></a><?php endif; ?>
            </figcaption>
          </figure>
        <?php endforeach; ?>
      </div>
      <p class="help muted proof-tip">Tip: to choose favorites, send me the photo numbers (e.g. #3, #12, #27).</p>
    <?php endif; ?>
  </section>
<?php endif; ?>
</main>
<script src="assets/js/main.js?v=<?= filemtime(__DIR__ . '/assets/js/main.js') ?>" defer></script>
</body>
</html>
