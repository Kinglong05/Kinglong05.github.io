<?php
define('URL_PREFIX', '');
require __DIR__ . '/includes/bootstrap.php';
if (!is_installed()) redirect('install.php');

$s = settings();
$on = fn(string $k) => ($s[$k] ?? '1') === '1';

$skills       = fetch_rows('skills');
$services     = $on('show_services') ? fetch_rows('services') : [];
$projects     = $on('show_projects') ? fetch_rows('projects') : [];
$gallery      = $on('show_gallery') ? fetch_rows('gallery') : [];
$experiences  = $on('show_experience') ? fetch_rows('experiences') : [];
$testimonials = $on('show_testimonials') ? fetch_rows('testimonials') : [];
$socials      = fetch_rows('socials');
$packages     = $on('show_packages') ? fetch_rows('packages') : [];
$comparisons  = $on('show_comparisons') ? array_values(array_filter(fetch_rows('comparisons'), fn($c) => $c['before_image'] && $c['after_image'])) : [];
$certificates = $on('show_certificates') ? fetch_rows('certificates') : [];
$gear         = $on('show_gear') ? fetch_rows('gear') : [];
$posts        = $on('show_posts') ? db()->query('SELECT * FROM posts WHERE is_visible = 1 ORDER BY published_on DESC, id DESC LIMIT 6')->fetchAll() : [];

// Showreel (hero button)
$reelSrc = setting('showreel_file') ? url($s['showreel_file']) : setting('showreel_url');
$reel = $reelSrc ? video_info($reelSrc) : null;

// Instagram follow strip: username from settings, or from the Instagram social link
$igHandle = ltrim(trim(setting('instagram_handle')), '@');
if ($igHandle === '') {
    foreach ($socials as $so) {
        if (preg_match('~instagram\.com/([A-Za-z0-9._]+)~i', $so['url'], $m)) { $igHandle = $m[1]; break; }
    }
}
if (!$on('show_instagram')) $igHandle = '';

$featured = array_values(array_filter($projects, fn($p) => (int) $p['is_featured'] === 1));
$extra    = array_values(array_filter($projects, fn($p) => (int) $p['is_featured'] !== 1));
if (!$featured) { $featured = $projects; $extra = []; }

$name = setting('owner_name', 'Your Name');
$logo = setting('logo_text', 'PORTFOLIO');
$heroImg = setting('hero_image') ? url($s['hero_image']) : 'assets/img/portrait-placeholder.svg';
$aboutImg = setting('about_image') ? url($s['about_image']) : 'assets/img/about-placeholder.svg';
$metaTitle = setting('meta_title', $name . ' | Portfolio');
$metaDesc = setting('meta_description');
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$siteUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';

// Section numbering follows which sections are shown
$sections = ['about' => 'About Me', 'skills' => 'Skills & Stack'];
if ($services) $sections['services'] = 'Services';
if ($packages) $sections['packages'] = 'Packages & Prices';
if ($projects) $sections['projects'] = 'Featured Projects';
if ($gallery) $sections['gallery'] = 'Photo, Video & Drone';
if ($comparisons) $sections['before-after'] = 'Before & After';
if ($experiences) $sections['experience'] = 'Experience';
if ($certificates) $sections['certificates'] = 'Certificates & Awards';
if ($gear) $sections['gear'] = 'Gear I Use';
if ($testimonials) $sections['testimonials'] = 'Testimonials';
if ($posts) $sections['stories'] = 'Stories';
$sections['contact'] = 'Get In Touch';
$num = array_combine(array_keys($sections), array_map(fn($i) => str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT), array_keys(array_keys($sections))));

$label = function (string $key) use ($sections, $num) {
    return '<div class="sec-label"><span class="sec-num">' . $num[$key] . '</span><span data-i18n="sec.' . e($key) . '">' . e($sections[$key]) . '</span></div>';
};

// Main menu: kept short so it fits; Skills sits next to About, Certificates/Gear near Experience
$navItems = ['home' => 'Home', 'about' => 'About'];
if ($services) $navItems['services'] = 'Services';
if ($packages) $navItems['packages'] = 'Pricing';
if ($projects) $navItems['projects'] = 'Projects';
if ($gallery) $navItems['gallery'] = 'Gallery';
if ($experiences) $navItems['experience'] = 'Experience';
if ($testimonials) $navItems['testimonials'] = 'Reviews';
if ($posts) $navItems['stories'] = 'Stories';
$navItems['contact'] = 'Contact';

$stats = [];
foreach ([1 => 'fa-solid fa-award', 2 => 'fa-solid fa-briefcase', 3 => 'fa-regular fa-face-smile'] as $i => $icon) {
    if (setting("stat{$i}_value") !== '') $stats[] = [$icon, $s["stat{$i}_value"], $s["stat{$i}_label"] ?? ''];
}
$flashes = take_flashes();
$roles = split_lines(setting('hero_roles'));

// Gallery: work out what each tile shows and plays
$galleryCats = [];
$albums = [];
if ($gallery) {
    $ids = array_map('intval', array_column($gallery, 'id'));
    $st = db()->prepare('SELECT gallery_id, image FROM gallery_photos WHERE gallery_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY sort_order, id');
    $st->execute($ids);
    foreach ($st as $r) $albums[(int) $r['gallery_id']][] = url($r['image']);
}
$linkLabels = ['facebook' => 'View on Facebook', 'youtube' => 'Watch on YouTube', 'vimeo' => 'Watch on Vimeo', 'drive' => 'Open in Google Drive'];
foreach ($gallery as &$g) {
    $g['title_plain'] = trim(str_replace('**', '', (string) $g['title'])); // **gold** markers only make sense on the card
    // What plays: an uploaded video file wins over a link
    $playSrc = !empty($g['video_file']) ? url($g['video_file']) : (string) $g['video_url'];
    $g['video'] = $playSrc !== '' ? video_info($playSrc) : null;
    $linkInfo = $g['video_url'] ? video_info($g['video_url']) : null;
    // Facebook refuses to play this one on other sites: show the cover + "Watch on Facebook" instead of a broken player
    if (empty($g['video_file']) && ($g['embed_status'] ?? '') === 'blocked' && $g['video']) {
        $g['video']['kind'] = 'external';
        $g['video']['src'] = $g['video_url'];
        $g['video']['inline'] = null;
    }
    $g['thumb'] = $g['image'] ? url($g['image']) : ($g['video']['thumb'] ?? '');
    // Photos to flip through: the cover + the album
    $g['photos'] = array_values(array_filter(array_merge([$g['image'] ? url($g['image']) : null], $albums[(int) $g['id']] ?? [])));
    if (!$g['image'] && !empty($g['photos'])) $g['thumb'] = $g['photos'][0];
    // Photo items with pictures open in the photo viewer (not Facebook's post widget)
    $g['album'] = $g['media_type'] === 'photo' && $g['photos'];
    // "View on Facebook" style button in the viewer
    $g['link'] = ($linkInfo && $linkInfo['kind'] !== 'file') ? $g['video_url'] : '';
    $g['link_label'] = $linkLabels[$linkInfo['provider'] ?? ''] ?? 'Open original';
    $g['link_icon'] = provider_icon($linkInfo);
    // No cover image but an embeddable link: show the real player inside the card
    $g['live'] = !$g['thumb'] && !empty($g['video']['inline']);
    if ($g['live'] && $g['video']['vertical'] && $g['layout'] === 'normal') $g['layout'] = 'tall';
    $galleryCats[$g['category']] = GALLERY_CATEGORIES[$g['category']] ?? ucfirst($g['category']);
}
unset($g);
$galleryCats = array_intersect_key(GALLERY_CATEGORIES, $galleryCats) + $galleryCats;
$igPhotos = array_slice(array_values(array_filter(array_column($gallery, 'thumb'), fn($t) => $t && !str_contains($t, 'ytimg'))), 0, 6);
$serviceOptions = array_column($services ?: fetch_rows('services'), 'title');
$footer = str_replace('{year}', date('Y'), setting('footer_text', '© {year} ' . $name . '. All rights reserved.'));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($metaTitle) ?></title>
<meta name="description" content="<?= e($metaDesc) ?>">
<meta name="theme-color" content="#0a0a0a">
<link rel="canonical" href="<?= e($siteUrl) ?>">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= e($metaTitle) ?>">
<meta property="og:description" content="<?= e($metaDesc) ?>">
<meta property="og:url" content="<?= e($siteUrl) ?>">
<?php if (setting('og_image')): ?><meta property="og:image" content="<?= e($siteUrl . $s['og_image']) ?>"><?php endif; ?>
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><path d='M16 2l12 7v14l-12 7-12-7V9z' fill='%23f5b335'/></svg>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Outfit:wght@400;500;600;700&family=Great+Vibes&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/devicons/devicon@v2.16.0/devicon.min.css">
<link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">
<script>(function(d){d.classList.add('js');try{if(localStorage.getItem('theme')==='light')d.setAttribute('data-theme','light')}catch(e){}})(document.documentElement)</script>
<?php $ga4 = strtoupper(trim(setting('ga4_id'))); if (preg_match('/^G-[A-Z0-9]{4,16}$/', $ga4)): ?>
<!-- Google Analytics 4: visitor stats (Admin > Settings > Visitor Stats) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($ga4) ?>"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag('js',new Date());gtag('config','<?= e($ga4) ?>');</script>
<?php endif; ?>
<?php $gc = strtolower(trim(setting('goatcounter_code'))); if (preg_match('/^[a-z0-9-]{2,50}$/', $gc)): ?>
<!-- GoatCounter: cookie-free visitor stats -->
<script data-goatcounter="https://<?= e($gc) ?>.goatcounter.com/count" async src="https://gc.zgo.at/count.js"></script>
<?php endif; ?>
<script type="application/ld+json"><?= json_encode(['@context' => 'https://schema.org', '@type' => 'Person', 'name' => $name, 'url' => $siteUrl, 'sameAs' => array_column($socials, 'url')], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
</head>
<body>
<div class="scroll-progress" aria-hidden="true"><span id="scrollBar"></span></div>
<div class="bg-fx" aria-hidden="true"><span class="glow g1"></span><span class="glow g2"></span></div>

<header class="nav" id="nav">
  <div class="container nav-inner">
    <a class="logo" href="#home" aria-label="<?= e($name) ?> - home">
      <span class="logo-mark"><svg viewBox="0 0 32 32" aria-hidden="true"><path d="M16 2.5 28 9.5v13L16 29.5 4 22.5v-13z" fill="none" stroke="currentColor" stroke-width="2"/><path d="M13 12l-4 4 4 4M19 12l4 4-4 4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
      <span><?= e($logo) ?></span>
    </a>
    <nav class="nav-links" id="navLinks" aria-label="Main">
      <?php foreach ($navItems as $id => $text): ?>
        <a href="#<?= $id ?>"<?= $id === 'home' ? ' class="active"' : '' ?> data-i18n="nav.<?= $id ?>"><?= e($text) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="nav-tools">
      <?php if ($on('show_lang_toggle')): ?><button type="button" class="tool-btn lang-btn" id="langToggle" aria-label="Switch language to Filipino" title="English / Filipino">FIL</button><?php endif; ?>
      <?php if ($on('show_theme_toggle')): ?><button type="button" class="tool-btn" id="themeToggle" aria-label="Switch to light mode" title="Light / dark mode"><i class="fa-solid fa-sun"></i></button><?php endif; ?>
    </div>
    <a href="#contact" class="btn btn-outline btn-sm nav-cta"><span data-i18n="nav.cta"><?= e(setting('nav_cta', "Let's Talk")) ?></span> <i class="fa-regular fa-paper-plane"></i></a>
    <button class="nav-toggle" id="navToggle" aria-label="Open menu" aria-expanded="false" aria-controls="navLinks"><span></span><span></span><span></span></button>
  </div>
</header>

<main class="container stack">

  <!-- HERO -->
  <section id="home" class="card hero">
    <div class="hero-text reveal">
      <?php if (setting('hero_badge')): ?><span class="badge"><span class="pulse"></span><?= e($s['hero_badge']) ?></span><?php endif; ?>
      <h1><?= hl(setting('hero_title', 'I Build Modern Web **Experiences**')) ?></h1>
      <?php if ($roles): ?>
        <p class="roles"><span data-i18n="hero.im">I'm a</span> <span class="rotator" data-words="<?= e(json_encode($roles, JSON_UNESCAPED_UNICODE)) ?>"><?= e($roles[0]) ?></span><span class="caret" aria-hidden="true"></span>
          <span class="sr"><?= e(implode(', ', $roles)) ?></span></p>
      <?php endif; ?>
      <p class="lead"><?= e(setting('hero_subtitle')) ?></p>
      <div class="hero-actions">
        <a href="#contact" class="btn btn-gold"><span data-i18n="hero.hire">Hire Me</span> <i class="fa-solid fa-arrow-right tilt"></i></a>
        <?php if ($projects || $gallery): ?><a href="#<?= $projects ? 'projects' : 'gallery' ?>" class="btn btn-outline"><span data-i18n="hero.work">View My Work</span> <i class="fa-solid fa-table-cells-large"></i></a><?php endif; ?>
        <?php if ($reel): ?>
          <?php if ($reel['kind'] === 'link'): ?>
            <a class="btn-play" href="<?= e($reel['src']) ?>" target="_blank" rel="noopener"><span class="play-dot"><i class="fa-solid fa-play"></i></span><span data-i18n="hero.reel"><?= e(setting('showreel_label', 'Watch Showreel')) ?></span></a>
          <?php else: ?>
            <button type="button" class="btn-play" id="reelBtn" data-kind="<?= e($reel['kind']) ?>" data-src="<?= e($reel['src']) ?>"<?= $reel['vertical'] ? ' data-vertical="1"' : '' ?>><span class="play-dot"><i class="fa-solid fa-play"></i></span><span data-i18n="hero.reel"><?= e(setting('showreel_label', 'Watch Showreel')) ?></span></button>
          <?php endif; ?>
        <?php endif; ?>
      </div>
      <?php if ($socials): ?>
      <div class="follow">
        <span data-i18n="hero.follow">Follow me on</span>
        <div class="social-row">
          <?php foreach ($socials as $so): ?>
            <a href="<?= e($so['url']) ?>" target="_blank" rel="noopener me" aria-label="<?= e($so['platform']) ?>" title="<?= e($so['platform']) ?>"><i class="<?= e($so['icon'] ?: 'fa-solid fa-link') ?>"></i></a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
    <div class="hero-visual reveal" data-delay="1">
      <span class="streak" aria-hidden="true"></span>
      <span class="ring" aria-hidden="true"><i></i></span>
      <div class="float-badges" aria-hidden="true">
        <span class="fb fb1" title="Web"><i class="fa-solid fa-code"></i></span>
        <span class="fb fb2" title="Photo"><i class="fa-solid fa-camera"></i></span>
        <span class="fb fb3" title="Video"><i class="fa-solid fa-video"></i></span>
        <span class="fb fb4" title="Drone"><i class="icon-drone"></i></span>
      </div>
      <img class="hero-img" src="<?= e($heroImg) ?>" alt="Portrait of <?= e($name) ?>" width="560" height="640" fetchpriority="high">
      <?php if ($stats): ?>
      <div class="stats glass">
        <?php foreach ($stats as [$icon, $val, $lbl]): ?>
          <div class="stat"><div class="stat-top"><i class="<?= e($icon) ?>"></i><b data-count="<?= e($val) ?>"><?= e($val) ?></b></div><span><?= e($lbl) ?></span></div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- ABOUT + SKILLS -->
  <div class="split">
    <section id="about" class="card about reveal">
      <figure class="about-img">
        <img src="<?= e($aboutImg) ?>" alt="<?= e($name) ?>" loading="lazy" width="400" height="500">
        <?php if (setting('about_signature')): ?><figcaption class="signature"><?= e($s['about_signature']) ?></figcaption><?php endif; ?>
      </figure>
      <div class="about-body">
        <?= $label('about') ?>
        <h2><?= hl(setting('about_title', 'Building solutions with code & **creativity.**')) ?></h2>
        <p class="muted"><?= hl(setting('about_text')) ?></p>
        <?php if ($pts = split_lines(setting('about_points'))): ?>
          <ul class="checks">
            <?php foreach ($pts as $pt): ?><li><i class="fa-regular fa-circle-check"></i><?= e($pt) ?></li><?php endforeach; ?>
          </ul>
        <?php endif; ?>
        <?php if (setting('cv_file')): ?>
          <a class="btn btn-outline btn-sm" href="<?= e(url($s['cv_file'])) ?>" download>Download CV <i class="fa-solid fa-download"></i></a>
        <?php endif; ?>
      </div>
    </section>

    <section id="skills" class="card skills reveal" data-delay="1">
      <?= $label('skills') ?>
      <div class="skill-grid">
        <?php foreach ($skills as $sk): ?>
          <div class="skill" title="<?= e($sk['name']) ?>">
            <?php if ($sk['icon_image']): ?>
              <img src="<?= e(url($sk['icon_image'])) ?>" alt="" loading="lazy" width="32" height="32">
            <?php else: ?>
              <i class="<?= e($sk['icon_class'] ?: 'fa-solid fa-code') ?>"></i>
            <?php endif; ?>
            <span><?= e($sk['name']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
      <?php if (count($skills) <= 10): // fill the card when there are only a few skills ?>
      <div class="skill-extra">
        <?php if ($roles): ?>
          <div class="sx-block">
            <span class="sx-label" data-i18n="sk.what">What I do</span>
            <div class="sx-chips"><?php foreach ($roles as $r): ?><span><?= e($r) ?></span><?php endforeach; ?></div>
          </div>
        <?php endif; ?>
        <div class="sx-facts">
          <?php if (setting('hero_badge')): ?><div><span class="pulse"></span><?= e($s['hero_badge']) ?></div><?php endif; ?>
          <?php if (setting('contact_location')): ?><div><i class="fa-solid fa-location-dot"></i><?= e($s['contact_location']) ?></div><?php endif; ?>
          <?php if (setting('contact_availability')): ?><div><i class="fa-regular fa-clock"></i><?= e($s['contact_availability']) ?></div><?php endif; ?>
        </div>
        <a href="#contact" class="btn btn-outline btn-sm sx-cta"><span data-i18n="sk.cta">Work with me</span> <i class="fa-solid fa-arrow-right"></i></a>
      </div>
      <?php endif; ?>
      <?php if (setting('skills_note')): ?><div class="note-pill"><i class="fa-solid fa-heart"></i><?= e($s['skills_note']) ?></div><?php endif; ?>
    </section>
  </div>

  <?php if ($services): ?>
  <!-- SERVICES -->
  <section id="services" class="card section">
    <?= $label('services') ?>
    <?php $n = count($services); $svcCols = in_array($n, [4, 8], true) ? 4 : ($n % 3 === 0 ? 3 : ($n <= 2 ? max(1, $n) : 3)); ?>
    <div class="service-grid" style="--cols: <?= $svcCols ?>">
      <?php foreach ($services as $i => $sv): ?>
        <article class="service reveal" data-delay="<?= $i % 4 ?>">
          <div class="service-head"><span class="ico-box"><i class="<?= e($sv['icon'] ?: 'fa-solid fa-star') ?>"></i></span><h3><?= e($sv['title']) ?></h3></div>
          <p><?= e($sv['description']) ?></p>
          <?php if ($sv['link']): ?>
            <a href="<?= e($sv['link']) ?>" class="more"><span data-i18n="btn.more">Learn more</span> <i class="fa-solid fa-arrow-right"></i></a>
          <?php else: ?>
            <a href="#contact" class="more" data-book="<?= e($sv['title']) ?>" data-service="<?= e($sv['title']) ?>"><span data-i18n="btn.book">Book this</span> <i class="fa-solid fa-arrow-right"></i></a>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($packages): ?>
  <!-- PACKAGES & PRICES -->
  <section id="packages" class="card section">
    <?= $label('packages') ?>
    <?php $pn = count($packages); $pkgCols = $pn % 3 === 0 ? 3 : ($pn % 4 === 0 ? 4 : min(3, max(1, $pn))); ?>
    <div class="pkg-grid" style="--cols: <?= $pkgCols ?>">
      <?php foreach ($packages as $i => $pk): ?>
        <article class="pkg reveal<?= $pk['is_popular'] ? ' popular' : '' ?>" data-delay="<?= $i % 3 ?>">
          <?php if ($pk['is_popular']): ?><span class="pkg-badge"><i class="fa-solid fa-star"></i> <span data-i18n="pkg.popular">Most Popular</span></span><?php endif; ?>
          <div class="pkg-head">
            <span class="ico-box"><i class="<?= e($pk['icon'] ?: 'fa-solid fa-tag') ?>"></i></span>
            <div><h3><?= e($pk['name']) ?></h3><?php if ($pk['service']): ?><small><?= e($pk['service']) ?></small><?php endif; ?></div>
          </div>
          <?php if ($pk['price']): ?><div class="pkg-price"><b><?= e($pk['price']) ?></b><?php if ($pk['price_note']): ?><span><?= e($pk['price_note']) ?></span><?php endif; ?></div><?php endif; ?>
          <?php if ($pk['description']): ?><p class="pkg-desc"><?= e($pk['description']) ?></p><?php endif; ?>
          <?php if ($feat = split_lines((string) $pk['features'])): ?>
            <ul class="pkg-list"><?php foreach ($feat as $f): ?><li><i class="fa-solid fa-check"></i><?= e($f) ?></li><?php endforeach; ?></ul>
          <?php endif; ?>
          <a href="#contact" class="btn <?= $pk['is_popular'] ? 'btn-gold' : 'btn-outline' ?> pkg-btn" data-book="<?= e('Package: ' . $pk['name'] . ($pk['price'] ? ' (' . $pk['price'] . ')' : '')) ?>" data-service="<?= e((string) $pk['service']) ?>"><span data-i18n="pkg.book">Book this package</span> <i class="fa-solid fa-arrow-right"></i></a>
        </article>
      <?php endforeach; ?>
    </div>
    <?php if (setting('packages_note')): ?><p class="pkg-note"><i class="fa-solid fa-circle-info"></i> <?= e($s['packages_note']) ?></p><?php endif; ?>
  </section>
  <?php endif; ?>

  <?php if ($projects): ?>
  <!-- PROJECTS -->
  <section id="projects" class="card section">
    <div class="sec-head">
      <?= $label('projects') ?>
      <?php if ($extra): ?><button class="link-btn" id="viewAll" aria-expanded="false">View all projects <i class="fa-solid fa-arrow-right"></i></button><?php endif; ?>
    </div>
    <div class="project-grid<?= count($featured) === 1 ? ' single' : (count($featured) === 2 ? ' double' : '') ?>">
      <?php foreach ([...$featured, ...$extra] as $i => $p):
        $isExtra = $i >= count($featured);
        $link = $p['live_url'] ?: $p['repo_url']; ?>
        <article class="project reveal<?= $isExtra ? ' is-extra' : '' ?>" data-delay="<?= $i % 3 ?>"<?= $isExtra ? ' hidden' : '' ?>>
          <div class="project-thumb">
            <?php if ($p['image']): ?>
              <img src="<?= e(url($p['image'])) ?>" alt="<?= e($p['title']) ?> screenshot" loading="lazy" width="640" height="400">
            <?php else: ?>
              <div class="thumb-ph" aria-hidden="true"><span></span><span></span><span></span><i class="fa-solid fa-code"></i></div>
            <?php endif; ?>
          </div>
          <div class="project-body">
            <?php if ($tags = split_tags($p['tags'])): ?><div class="tags"><?php foreach ($tags as $t): ?><span class="tag"><?= e($t) ?></span><?php endforeach; ?></div><?php endif; ?>
            <div class="project-row">
              <div>
                <h3><?= e($p['title']) ?></h3>
                <p><?= e($p['description']) ?></p>
              </div>
              <div class="project-links">
                <?php if ($p['repo_url'] && $p['live_url']): ?><a href="<?= e($p['repo_url']) ?>" class="sq-btn" target="_blank" rel="noopener" aria-label="Source code for <?= e($p['title']) ?>"><i class="fa-brands fa-github"></i></a><?php endif; ?>
                <?php if ($link): ?><a href="<?= e($link) ?>" class="sq-btn" target="_blank" rel="noopener" aria-label="Open <?= e($p['title']) ?>"><i class="fa-solid fa-arrow-up-right-from-square"></i></a><?php endif; ?>
              </div>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($gallery): ?>
  <!-- GALLERY: PHOTO / VIDEO / DRONE -->
  <section id="gallery" class="card section">
    <div class="sec-head">
      <?= $label('gallery') ?>
      <?php if (count($galleryCats) > 1): ?>
      <div class="filters" role="tablist" aria-label="Filter gallery">
        <button class="chip active" data-filter="all" role="tab" aria-selected="true" data-i18n="g.all">All</button>
        <?php foreach ($galleryCats as $key => $text): ?>
          <button class="chip" data-filter="<?= e($key) ?>" role="tab" aria-selected="false"><i class="<?= e(GALLERY_ICONS[$key] ?? 'fa-solid fa-image') ?>"></i> <?= e($text) ?></button>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <div class="gallery-grid" id="galleryGrid">
      <?php foreach ($gallery as $i => $g):
        $v = $g['video'];
        $catIcon = GALLERY_ICONS[$g['category']] ?? 'fa-solid fa-image'; ?>
        <div class="g-item g-<?= e($g['layout']) ?><?= $g['live'] ? ' g-live' : '' ?> reveal" data-delay="<?= $i % 4 ?>"
          data-cat="<?= e($g['category']) ?>"
          data-title="<?= e($g['title_plain']) ?>"
          data-caption="<?= e(implode(' · ', array_filter([trim((string) $g['location']), trim((string) $g['description'])], 'strlen'))) ?>"
          <?php if ($g['link']): ?>data-link="<?= e($g['link']) ?>" data-link-label="<?= e($g['link_label']) ?>" data-link-icon="<?= e($g['link_icon']) ?>"<?php endif; ?>
          <?php if ($g['thumb']): ?>data-cover="<?= e($g['thumb']) ?>"<?php endif; ?>
          <?php if ($g['album']): ?>data-kind="photos" data-photos="<?= e(json_encode($g['photos'], JSON_UNESCAPED_SLASHES)) ?>"<?php elseif ($v): ?>data-kind="<?= e($v['kind']) ?>" data-src="<?= e($v['src']) ?>"<?= $v['vertical'] ? ' data-vertical="1"' : '' ?><?= $v['post'] ? ' data-post="1"' : '' ?><?php elseif ($g['thumb']): ?>data-kind="image" data-src="<?= e($g['thumb']) ?>"<?php else: ?>data-kind="none" data-icon="<?= e($catIcon) ?>"<?php endif; ?>>
          <?php if ($g['live']): ?>
            <?php if ($v['kind'] === 'file'): ?>
              <video class="g-embed" src="<?= e($v['inline']) ?>#t=0.5" preload="metadata" controls playsinline></video>
            <?php else: ?>
              <iframe class="g-embed" src="<?= e($v['inline']) ?>" title="<?= e($g['title_plain']) ?>" loading="lazy" scrolling="no"
                allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share; fullscreen" allowfullscreen></iframe>
            <?php endif; ?>
            <button type="button" class="g-open g-expand" aria-label="Open <?= e($g['title_plain']) ?> full screen" title="Full screen"><i class="fa-solid fa-expand"></i></button>
          <?php else: ?>
            <?php if ($g['thumb']): ?>
              <img src="<?= e($g['thumb']) ?>" alt="<?= e($g['title_plain']) ?>" loading="lazy">
            <?php else: ?>
              <span class="g-ph g-ph-<?= e($g['category']) ?>"><i class="<?= e($catIcon) ?>"></i></span>
            <?php endif; ?>
            <?php if (!$g['album'] && $v && $v['kind'] !== 'link' && !$v['post']): ?><span class="play"><i class="fa-solid fa-play"></i></span><?php endif; ?>
            <?php if (count($g['photos']) > 1): ?><span class="g-count"><i class="fa-regular fa-images"></i> <?= count($g['photos']) ?></span><?php endif; ?>
            <span class="g-info">
              <span class="g-cat"><i class="<?= e($v ? provider_icon($v) : $catIcon) ?>"></i> <?= e(GALLERY_CATEGORIES[$g['category']] ?? $g['category']) ?></span>
              <b><?= hl($g['title']) ?></b>
              <?php if ($g['location']): ?><small><i class="fa-solid fa-location-dot"></i> <?= e($g['location']) ?></small><?php endif; ?>
            </span>
            <button type="button" class="g-open" aria-label="Open <?= e($g['title_plain']) ?>"></button>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($comparisons): ?>
  <!-- BEFORE & AFTER -->
  <section id="before-after" class="card section">
    <div class="sec-head"><?= $label('before-after') ?><span class="sec-hint"><i class="fa-solid fa-arrows-left-right"></i> <span data-i18n="ba.hint">Drag to compare</span></span></div>
    <div class="ba-grid<?= count($comparisons) === 1 ? ' single' : '' ?>">
      <?php foreach ($comparisons as $i => $c): ?>
        <figure class="ba reveal" data-delay="<?= $i % 2 ?>">
          <div class="ba-frame" style="--pos: 50%">
            <img class="ba-after" src="<?= e(url($c['after_image'])) ?>" alt="<?= e($c['title']) ?>, after editing" loading="lazy">
            <div class="ba-before"><img src="<?= e(url($c['before_image'])) ?>" alt="<?= e($c['title']) ?>, before editing" loading="lazy"></div>
            <span class="ba-tag ba-tag-b" data-i18n="ba.before">Before</span><span class="ba-tag ba-tag-a" data-i18n="ba.after">After</span>
            <span class="ba-handle" aria-hidden="true"><i class="fa-solid fa-arrows-left-right"></i></span>
            <input class="ba-range" type="range" min="0" max="100" value="50" aria-label="Compare before and after: <?= e($c['title']) ?>">
          </div>
          <figcaption><b><?= e($c['title']) ?></b><?php if ($c['caption']): ?><span><?= e($c['caption']) ?></span><?php endif; ?></figcaption>
        </figure>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($experiences): ?>
  <!-- EXPERIENCE -->
  <section id="experience" class="card section">
    <?= $label('experience') ?>
    <ol class="timeline" style="--n: <?= count($experiences) ?>">
      <?php foreach ($experiences as $i => $x): ?>
        <li class="tl-item reveal" data-delay="<?= $i % 4 ?>">
          <span class="tl-dot" aria-hidden="true"></span>
          <div class="tl-card">
            <span class="tl-logo"><?php if ($x['logo']): ?><img src="<?= e(url($x['logo'])) ?>" alt="<?= e($x['company']) ?> logo" loading="lazy" width="40" height="40"><?php else: ?><i class="<?= e($x['icon'] ?: 'fa-solid fa-building') ?>"></i><?php endif; ?></span>
            <div>
              <h3><?= e($x['role']) ?></h3>
              <p class="tl-co"><?= e($x['company']) ?></p>
              <p class="tl-period"><?= e($x['period']) ?></p>
            </div>
          </div>
          <p class="tl-desc"><?= e($x['description']) ?></p>
        </li>
      <?php endforeach; ?>
    </ol>
  </section>
  <?php endif; ?>

  <?php if ($certificates || $gear): ?>
  <div class="split split-even<?= ($certificates && $gear) ? '' : ' split-one' ?>">
    <?php if ($certificates): ?>
    <!-- CERTIFICATES & AWARDS -->
    <section id="certificates" class="card section">
      <?= $label('certificates') ?>
      <ul class="cert-list">
        <?php foreach ($certificates as $i => $ct):
          $href = $ct['link'] ?: ($ct['image'] ? url($ct['image']) : ''); ?>
          <li class="cert reveal" data-delay="<?= $i % 3 ?>">
            <span class="cert-img"><?php if ($ct['image']): ?><img src="<?= e(url($ct['image'])) ?>" alt="" loading="lazy"><?php else: ?><i class="fa-solid fa-award"></i><?php endif; ?></span>
            <div class="cert-body">
              <b><?= e($ct['title']) ?></b>
              <span><?= e(implode(' · ', array_filter([(string) $ct['issuer'], (string) $ct['issued']], 'strlen'))) ?></span>
            </div>
            <?php if ($href): ?><a class="sq-btn" href="<?= e($href) ?>" target="_blank" rel="noopener" aria-label="View <?= e($ct['title']) ?>"><i class="fa-solid fa-arrow-up-right-from-square"></i></a><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
    <?php endif; ?>
    <?php if ($gear): ?>
    <!-- GEAR -->
    <section id="gear" class="card section">
      <?= $label('gear') ?>
      <div class="gear-grid">
        <?php foreach ($gear as $i => $gi): ?>
          <div class="gear reveal" data-delay="<?= $i % 3 ?>">
            <span class="gear-img"><?php if ($gi['image']): ?><img src="<?= e(url($gi['image'])) ?>" alt="" loading="lazy"><?php else: ?><i class="<?= e(GEAR_ICONS[$gi['category']] ?? 'fa-solid fa-toolbox') ?>"></i><?php endif; ?></span>
            <div><small><?= e(GEAR_CATEGORIES[$gi['category']] ?? $gi['category']) ?></small><b><?= e($gi['name']) ?></b><?php if ($gi['note']): ?><span><?= e($gi['note']) ?></span><?php endif; ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if ($testimonials): ?>
  <!-- TESTIMONIALS -->
  <section id="testimonials" class="card section">
    <div class="sec-head">
      <?= $label('testimonials') ?>
      <div class="arrows">
        <button class="arrow" id="tPrev" aria-label="Previous testimonials"><i class="fa-solid fa-chevron-left"></i></button>
        <button class="arrow" id="tNext" aria-label="Next testimonials"><i class="fa-solid fa-chevron-right"></i></button>
      </div>
    </div>
    <div class="t-track" id="tTrack" tabindex="0" aria-label="Testimonials">
      <?php foreach ($testimonials as $t): ?>
        <figure class="testimonial">
          <i class="fa-solid fa-quote-left q"></i>
          <blockquote><?= e($t['quote']) ?></blockquote>
          <figcaption>
            <?php if ($t['avatar']): ?><img src="<?= e(url($t['avatar'])) ?>" alt="" loading="lazy" width="44" height="44"><?php else: ?><span class="initial"><?= e(mb_strtoupper(mb_substr($t['name'], 0, 1))) ?></span><?php endif; ?>
            <div><b><?= e($t['name']) ?></b><small><?= e($t['position']) ?></small></div>
            <span class="stars" aria-label="<?= (int) $t['rating'] ?> out of 5 stars"><?= str_repeat('<i class="fa-solid fa-star"></i>', max(0, min(5, (int) $t['rating']))) ?></span>
          </figcaption>
        </figure>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($posts): ?>
  <!-- STORIES (BLOG) -->
  <section id="stories" class="card section">
    <?= $label('stories') ?>
    <div class="story-grid<?= count($posts) === 1 ? " single" : "" ?>">
      <?php foreach ($posts as $i => $po): ?>
        <a class="story reveal" data-delay="<?= $i % 3 ?>" href="post.php?slug=<?= e($po['slug']) ?>">
          <span class="story-img"><?php if ($po['cover']): ?><img src="<?= e(url($po['cover'])) ?>" alt="" loading="lazy"><?php else: ?><i class="fa-solid fa-feather-pointed"></i><?php endif; ?></span>
          <span class="story-body">
            <?php if ($po['published_on']): ?><time datetime="<?= e($po['published_on']) ?>"><?= e(date('M j, Y', strtotime($po['published_on']))) ?></time><?php endif; ?>
            <b><?= hl($po['title']) ?></b>
            <?php if ($po['excerpt']): ?><span><?= e($po['excerpt']) ?></span><?php endif; ?>
            <em><span data-i18n="story.read">Read story</span> <i class="fa-solid fa-arrow-right"></i></em>
          </span>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($igHandle): ?>
  <!-- INSTAGRAM FOLLOW STRIP -->
  <section class="card ig-strip reveal" aria-label="Instagram">
    <div class="ig-text">
      <span class="ig-icon"><i class="fa-brands fa-instagram"></i></span>
      <div><b>@<?= e($igHandle) ?></b><span data-i18n="ig.text">More photos, reels and behind-the-scenes on Instagram.</span></div>
    </div>
    <?php if ($igPhotos): ?>
      <div class="ig-photos" aria-hidden="true"><?php foreach ($igPhotos as $ph): ?><img src="<?= e($ph) ?>" alt="" loading="lazy"><?php endforeach; ?></div>
    <?php endif; ?>
    <a class="btn btn-gold ig-btn" href="https://www.instagram.com/<?= e(rawurlencode($igHandle)) ?>/" target="_blank" rel="noopener"><span data-i18n="ig.follow">Follow on Instagram</span> <i class="fa-solid fa-arrow-right tilt"></i></a>
  </section>
  <?php endif; ?>

  <!-- CONTACT -->
  <section id="contact" class="card contact">
    <div class="contact-intro reveal">
      <?= $label('contact') ?>
      <h2><?= hl(setting('contact_title', "Let's build something **amazing** together.")) ?></h2>
      <p class="muted"><?= hl(setting('contact_text')) ?></p>
    </div>
    <div class="contact-box reveal" data-delay="1">
      <form class="contact-form" id="contactForm" action="contact.php" method="post" novalidate>
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <div class="hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
        <div class="row2">
          <label class="fl"><span class="sr">Your name</span><input name="name" placeholder="Your Name" data-i18n-ph="f.name" required maxlength="100" autocomplete="name"></label>
          <label class="fl"><span class="sr">Your email</span><input type="email" name="email" placeholder="Your Email" data-i18n-ph="f.email" required maxlength="190" autocomplete="email"></label>
        </div>
        <div class="row2">
          <label class="fl"><span class="sr">What do you need?</span>
            <select name="service">
              <option value="" data-i18n="f.need">What do you need?</option>
              <?php foreach ($serviceOptions as $opt): ?><option value="<?= e($opt) ?>"><?= e($opt) ?></option><?php endforeach; ?>
              <option value="Other">Other</option>
            </select>
          </label>
          <label class="fl"><span class="sr">Subject</span><input name="subject" placeholder="Subject" data-i18n-ph="f.subject" maxlength="150"></label>
        </div>
        <label class="fl"><span class="sr">Message</span><textarea name="message" placeholder="Your Message" data-i18n-ph="f.message" rows="5" required minlength="10" maxlength="5000"></textarea></label>
        <div class="form-foot">
          <button class="btn btn-gold" type="submit"><span data-i18n="f.send">Send Message</span> <i class="fa-regular fa-paper-plane"></i></button>
          <p class="form-status" id="formStatus" role="status" aria-live="polite">
            <?php foreach ($flashes as $f): ?><span class="<?= e($f['type']) ?>"><?= e($f['msg']) ?></span><?php endforeach; ?>
          </p>
        </div>
      </form>
      <ul class="contact-info">
        <?php
        $info = [
            ['fa-regular fa-envelope', 'Email', setting('contact_email'), setting('contact_email') ? 'mailto:' . $s['contact_email'] : ''],
            ['fa-solid fa-phone', 'Phone', setting('contact_phone'), setting('contact_phone') ? 'tel:' . preg_replace('/[^\d+]/', '', $s['contact_phone']) : ''],
            ['fa-solid fa-location-dot', 'Location', setting('contact_location'), ''],
            ['fa-regular fa-clock', 'Availability', setting('contact_availability'), ''],
        ];
        foreach ($info as [$icon, $lbl, $val, $href]): if ($val === '') continue; ?>
          <li><span class="ico-box sm"><i class="<?= $icon ?>"></i></span><div><b><?= $lbl ?></b><?php if ($href): ?><a href="<?= e($href) ?>"><?= e($val) ?></a><?php else: ?><span><?= e($val) ?></span><?php endif; ?></div></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>
</main>

<footer class="footer">
  <div class="container footer-inner">
    <a class="logo" href="#home"><span class="logo-mark"><svg viewBox="0 0 32 32" aria-hidden="true"><path d="M16 2.5 28 9.5v13L16 29.5 4 22.5v-13z" fill="none" stroke="currentColor" stroke-width="2"/><path d="M13 12l-4 4 4 4M19 12l4 4-4 4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span><span><?= e($logo) ?></span></a>
    <p class="muted"><?= e($footer) ?><?php if (setting('analytics_notice') === '1' && (setting('ga4_id') !== '' || setting('goatcounter_code') !== '')): ?><br><small>This site counts visits anonymously (country, device and browser), never names.</small><?php endif; ?></p>
    <div class="footer-links">
      <?php if (setting('privacy_url')): ?><a href="<?= e($s['privacy_url']) ?>">Privacy Policy</a><?php endif; ?>
      <?php if (setting('terms_url')): ?><a href="<?= e($s['terms_url']) ?>">Terms of Service</a><?php endif; ?>
      <a href="#home" class="to-top" aria-label="Back to top"><i class="fa-solid fa-arrow-up"></i></a>
    </div>
  </div>
</footer>

<?php if ($gallery): ?>
<div class="lightbox" id="lightbox" hidden role="dialog" aria-modal="true" aria-label="Media viewer">
  <button class="lb-btn lb-close" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
  <button class="lb-btn lb-prev" aria-label="Previous"><i class="fa-solid fa-chevron-left"></i></button>
  <button class="lb-btn lb-next" aria-label="Next"><i class="fa-solid fa-chevron-right"></i></button>
  <figure class="lb-figure">
    <div class="lb-stage" id="lbStage"></div>
    <div class="lb-thumbs" id="lbThumbs" hidden></div>
    <figcaption class="lb-caption">
      <b id="lbTitle"></b><span id="lbCaption"></span>
      <span class="lb-meta"><span class="lb-count" id="lbCount" hidden></span><a class="lb-link" id="lbLink" target="_blank" rel="noopener" hidden></a></span>
    </figcaption>
  </figure>
</div>
<?php endif; ?>

<?php if ($reel && $reel['kind'] !== 'link'): ?>
<div class="lightbox reel-box" id="reelBox" hidden role="dialog" aria-modal="true" aria-label="Showreel">
  <button class="lb-btn lb-close" id="reelClose" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
  <div class="lb-stage" id="reelStage"></div>
</div>
<?php endif; ?>

<script src="assets/js/main.js?v=<?= filemtime(__DIR__ . '/assets/js/main.js') ?>" defer></script>
</body>
</html>
