<?php
/** A single story (blog post). Published as story-<slug>.html on the live site. */
define('URL_PREFIX', '');
require __DIR__ . '/includes/bootstrap.php';
if (!is_installed()) redirect('install.php');

$s = settings();
$slug = (string) ($_GET['slug'] ?? '');
$st = db()->prepare('SELECT * FROM posts WHERE slug = ? AND is_visible = 1');
$st->execute([$slug]);
$post = $st->fetch();
if (!$post) {
    http_response_code(404);
    exit('<!doctype html><meta charset="utf-8"><title>Story not found</title><p style="font:16px sans-serif;padding:40px">Story not found. <a href="./">Back to the portfolio</a></p>');
}
$more = db()->prepare('SELECT title, slug, cover, published_on FROM posts WHERE is_visible = 1 AND id <> ? ORDER BY published_on DESC, id DESC LIMIT 3');
$more->execute([$post['id']]);
$more = $more->fetchAll();

$name = setting('owner_name', 'Portfolio');
$logo = setting('logo_text', 'PORTFOLIO');
$title = str_replace('**', '', $post['title']);
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';
$pageUrl = $base . 'post.php?slug=' . rawurlencode($post['slug']);
$desc = $post['excerpt'] ?: mb_substr(trim(strip_tags(render_story((string) $post['body']))), 0, 160);
$ga4 = strtoupper(trim(setting('ga4_id')));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · <?= e($name) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<link rel="canonical" href="<?= e($pageUrl) ?>">
<meta property="og:type" content="article">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:url" content="<?= e($pageUrl) ?>">
<?php if ($post['cover']): ?><meta property="og:image" content="<?= e($base . $post['cover']) ?>"><?php endif; ?>
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><path d='M16 2l12 7v14l-12 7-12-7V9z' fill='%23f5b335'/></svg>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">
<script>(function(d){d.classList.add('js');try{if(localStorage.getItem('theme')==='light')d.setAttribute('data-theme','light')}catch(e){}})(document.documentElement)</script>
<?php if (preg_match('/^G-[A-Z0-9]{4,16}$/', $ga4)): ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($ga4) ?>"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag('js',new Date());gtag('config','<?= e($ga4) ?>');</script>
<?php endif; ?>
<script type="application/ld+json"><?= json_encode(['@context' => 'https://schema.org', '@type' => 'BlogPosting', 'headline' => $title, 'datePublished' => $post['published_on'], 'author' => ['@type' => 'Person', 'name' => $name], 'description' => $desc], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?></script>
</head>
<body class="post-page">
<div class="scroll-progress" aria-hidden="true"><span id="scrollBar"></span></div>
<div class="bg-fx" aria-hidden="true"><span class="glow g1"></span><span class="glow g2"></span></div>

<header class="nav" id="nav">
  <div class="container nav-inner">
    <a class="logo" href="./" aria-label="<?= e($name) ?> - home">
      <span class="logo-mark"><svg viewBox="0 0 32 32" aria-hidden="true"><path d="M16 2.5 28 9.5v13L16 29.5 4 22.5v-13z" fill="none" stroke="currentColor" stroke-width="2"/><path d="M13 12l-4 4 4 4M19 12l4 4-4 4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
      <span><?= e($logo) ?></span>
    </a>
    <div class="nav-tools post-tools">
      <?php if (($s['show_theme_toggle'] ?? '1') === '1'): ?><button type="button" class="tool-btn" id="themeToggle" aria-label="Switch to light mode"><i class="fa-solid fa-sun"></i></button><?php endif; ?>
      <a href="./#stories" class="btn btn-outline btn-sm"><i class="fa-solid fa-arrow-left"></i> All stories</a>
    </div>
  </div>
</header>

<main class="container post-wrap">
  <article class="card post">
    <?php if ($post['cover']): ?><img class="post-cover" src="<?= e(url($post['cover'])) ?>" alt="" fetchpriority="high"><?php endif; ?>
    <div class="post-inner">
      <a class="post-back" href="./#stories"><i class="fa-solid fa-arrow-left"></i> Stories</a>
      <h1><?= hl($post['title']) ?></h1>
      <p class="post-meta">
        <span><i class="fa-regular fa-user"></i> <?= e($name) ?></span>
        <?php if ($post['published_on']): ?><time datetime="<?= e($post['published_on']) ?>"><i class="fa-regular fa-calendar"></i> <?= e(date('F j, Y', strtotime($post['published_on']))) ?></time><?php endif; ?>
      </p>
      <div class="post-body"><?= render_story((string) $post['body']) ?></div>
      <div class="post-share">
        <span>Share this story</span>
        <a class="sq-btn" href="https://www.facebook.com/sharer/sharer.php?u=<?= rawurlencode($pageUrl) ?>" target="_blank" rel="noopener" aria-label="Share on Facebook" data-share><i class="fa-brands fa-facebook-f"></i></a>
        <button type="button" class="sq-btn" id="nativeShare" data-url="<?= e($pageUrl) ?>" data-title="<?= e($title) ?>" aria-label="Share (Messenger, Viber...)" hidden><i class="fa-solid fa-share-nodes"></i></button>
        <button type="button" class="sq-btn" id="copyLink" data-url="<?= e($pageUrl) ?>" aria-label="Copy link"><i class="fa-solid fa-link"></i></button>
      </div>
      <a class="btn btn-gold post-cta" href="./#contact">Work with me <i class="fa-solid fa-arrow-right"></i></a>
    </div>
  </article>

  <?php if ($more): ?>
  <section class="card section">
    <div class="sec-label"><span class="sec-num">+</span>More stories</div>
    <div class="story-grid">
      <?php foreach ($more as $po): ?>
        <a class="story" href="post.php?slug=<?= e($po['slug']) ?>">
          <span class="story-img"><?php if ($po['cover']): ?><img src="<?= e(url($po['cover'])) ?>" alt="" loading="lazy"><?php else: ?><i class="fa-solid fa-feather-pointed"></i><?php endif; ?></span>
          <span class="story-body"><?php if ($po['published_on']): ?><time><?= e(date('M j, Y', strtotime($po['published_on']))) ?></time><?php endif; ?><b><?= hl($po['title']) ?></b><em>Read story <i class="fa-solid fa-arrow-right"></i></em></span>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
</main>

<footer class="footer">
  <div class="container footer-inner">
    <a class="logo" href="./"><span><?= e($logo) ?></span></a>
    <p class="muted"><?= e(str_replace('{year}', date('Y'), setting('footer_text', '© {year} ' . $name))) ?></p>
    <a href="#nav" class="to-top" aria-label="Back to top"><i class="fa-solid fa-arrow-up"></i></a>
  </div>
</footer>
<script src="assets/js/main.js?v=<?= filemtime(__DIR__ . '/assets/js/main.js') ?>" defer></script>
</body>
</html>
