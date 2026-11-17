<?php
/** Project case study. Published as project-<slug>.html on the live site. */
define('URL_PREFIX', '');
require __DIR__ . '/includes/bootstrap.php';
if (!is_installed()) redirect('install.php');

$s = settings();
$slug = (string) ($_GET['slug'] ?? '');
$st = db()->prepare("SELECT * FROM projects WHERE case_slug = ? AND is_visible = 1 AND case_study IS NOT NULL AND case_study <> ''");
$st->execute([$slug]);
$p = $st->fetch();
if (!$p) {
    http_response_code(404);
    exit('<!doctype html><meta charset="utf-8"><title>Not found</title><p style="font:16px sans-serif;padding:40px">Case study not found. <a href="./#projects">Back to projects</a></p>');
}
$others = db()->prepare("SELECT title, case_slug, image FROM projects WHERE is_visible = 1 AND id <> ? AND case_slug IS NOT NULL AND case_slug <> '' ORDER BY sort_order LIMIT 3");
$others->execute([$p['id']]);
$others = $others->fetchAll();

$name = setting('owner_name', 'Portfolio');
$logo = setting('logo_text', 'PORTFOLIO');
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';
$pageUrl = $base . 'project.php?slug=' . rawurlencode($p['case_slug']);
$desc = $p['description'] ?: mb_substr(trim(strip_tags(render_story((string) $p['case_study']))), 0, 160);
$tags = split_tags($p['tags']);
$ga4 = strtoupper(trim(setting('ga4_id')));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($p['title']) ?> · Case study · <?= e($name) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<link rel="canonical" href="<?= e($pageUrl) ?>">
<meta property="og:type" content="article">
<meta property="og:title" content="<?= e($p['title']) ?> · Case study">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:url" content="<?= e($pageUrl) ?>">
<?php if ($p['image']): ?><meta property="og:image" content="<?= e($base . $p['image']) ?>"><?php endif; ?>
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><path d='M16 2l12 7v14l-12 7-12-7V9z' fill='%23f5b335'/></svg>">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">
<script>(function(d){d.classList.add('js');try{if(localStorage.getItem('theme')==='light')d.setAttribute('data-theme','light')}catch(e){}})(document.documentElement)</script>
<?php if (preg_match('/^G-[A-Z0-9]{4,16}$/', $ga4)): ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($ga4) ?>"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag('js',new Date());gtag('config','<?= e($ga4) ?>');</script>
<?php endif; ?>
</head>
<body class="post-page">
<div class="scroll-progress" aria-hidden="true"><span id="scrollBar"></span></div>
<div class="bg-fx" aria-hidden="true"><span class="glow g1"></span><span class="glow g2"></span></div>
<header class="nav" id="nav">
  <div class="container nav-inner">
    <a class="logo" href="./"><span class="logo-mark"><svg viewBox="0 0 32 32" aria-hidden="true"><path d="M16 2.5 28 9.5v13L16 29.5 4 22.5v-13z" fill="none" stroke="currentColor" stroke-width="2"/><path d="M13 12l-4 4 4 4M19 12l4 4-4 4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span><span><?= e($logo) ?></span></a>
    <div class="nav-tools post-tools">
      <?php if (($s['show_theme_toggle'] ?? '1') === '1'): ?><button type="button" class="tool-btn" id="themeToggle" aria-label="Switch to light mode"><i class="fa-solid fa-sun"></i></button><?php endif; ?>
      <a href="./#projects" class="btn btn-outline btn-sm"><i class="fa-solid fa-arrow-left"></i> All projects</a>
    </div>
  </div>
</header>

<main class="container post-wrap">
  <article class="card post">
    <?php if ($p['image']): ?><img class="post-cover" src="<?= e(url($p['image'])) ?>" alt="<?= e($p['title']) ?> screenshot" fetchpriority="high"><?php endif; ?>
    <div class="post-inner">
      <a class="post-back" href="./#projects"><i class="fa-solid fa-arrow-left"></i> Projects</a>
      <span class="case-kicker">Case study</span>
      <h1><?= e($p['title']) ?></h1>
      <?php if ($p['description']): ?><p class="case-lead"><?= e($p['description']) ?></p><?php endif; ?>
      <?php if ($tags): ?><div class="tags case-tags"><?php foreach ($tags as $t): ?><span class="tag"><?= e($t) ?></span><?php endforeach; ?></div><?php endif; ?>
      <div class="case-links">
        <?php if ($p['live_url'] && $p['live_url'] !== '#'): ?><a class="btn btn-gold btn-sm" href="<?= e($p['live_url']) ?>" target="_blank" rel="noopener">Live demo <i class="fa-solid fa-arrow-up-right-from-square"></i></a><?php endif; ?>
        <?php if ($p['repo_url']): ?><a class="btn btn-outline btn-sm" href="<?= e($p['repo_url']) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-github"></i> Source code</a><?php endif; ?>
      </div>
      <div class="post-body"><?= render_story((string) $p['case_study']) ?></div>
      <a class="btn btn-gold post-cta" href="./#contact">Build something with me <i class="fa-solid fa-arrow-right"></i></a>
    </div>
  </article>

  <?php if ($others): ?>
  <section class="card section">
    <div class="sec-label"><span class="sec-num">+</span>More case studies</div>
    <div class="story-grid">
      <?php foreach ($others as $o): ?>
        <a class="story" href="project.php?slug=<?= e($o['case_slug']) ?>">
          <span class="story-img"><?php if ($o['image']): ?><img src="<?= e(url($o['image'])) ?>" alt="" loading="lazy"><?php else: ?><i class="fa-solid fa-code"></i><?php endif; ?></span>
          <span class="story-body"><b><?= e($o['title']) ?></b><em>Read case study <i class="fa-solid fa-arrow-right"></i></em></span>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
</main>
<footer class="footer"><div class="container footer-inner">
  <a class="logo" href="./"><span><?= e($logo) ?></span></a>
  <p class="muted"><?= e(str_replace('{year}', date('Y'), setting('footer_text', '© {year} ' . $name))) ?></p>
  <a href="#nav" class="to-top" aria-label="Back to top"><i class="fa-solid fa-arrow-up"></i></a>
</div></footer>
<script src="assets/js/main.js?v=<?= filemtime(__DIR__ . '/assets/js/main.js') ?>" defer></script>
</body>
</html>
