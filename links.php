<?php
/** Link-in-bio page (for Instagram / TikTok bios, QR codes and business cards). Published as links.html. */
define('URL_PREFIX', '');
require __DIR__ . '/includes/bootstrap.php';
if (!is_installed()) redirect('install.php');

$s = settings();
$name = setting('owner_name', 'Portfolio');
$roles = split_lines(setting('hero_roles'));
$live = rtrim(setting('site_url', 'https://kinglong05.github.io/'), '/') . '/';

/* ---- vCard (save-my-contact). Published as contact.vcf ---- */
if (isset($_GET['vcf'])) {
    $esc = fn(string $v) => str_replace(['\\', ',', ';', "\n"], ['\\\\', '\,', '\;', '\n'], $v);
    $parts = preg_split('/\s+/', trim($name));
    $last = count($parts) > 1 ? array_pop($parts) : '';
    $lines = ['BEGIN:VCARD', 'VERSION:3.0', 'FN:' . $esc($name), 'N:' . $esc($last) . ';' . $esc(implode(' ', $parts)) . ';;;'];
    if ($roles) $lines[] = 'TITLE:' . $esc(implode(', ', $roles));
    if (setting('contact_phone')) $lines[] = 'TEL;TYPE=CELL:' . preg_replace('/[^\d+]/', '', $s['contact_phone']);
    if (setting('contact_email')) $lines[] = 'EMAIL;TYPE=INTERNET:' . $s['contact_email'];
    $lines[] = 'URL:' . $live;
    if (setting('contact_location')) $lines[] = 'ADR;TYPE=WORK:;;;' . $esc($s['contact_location']) . ';;;';
    $lines[] = 'NOTE:' . $esc('Portfolio: ' . $live);
    $lines[] = 'END:VCARD';
    header('Content-Type: text/vcard; charset=utf-8');
    header('Content-Disposition: attachment; filename="contact.vcf"');
    echo implode("\r\n", $lines) . "\r\n";
    exit;
}

$avatar = setting('about_image') ?: setting('hero_image');
$socials = fetch_rows('socials');
$hasPackages = ($s['show_packages'] ?? '1') === '1' && fetch_rows('packages');
$latestStory = (($s['show_posts'] ?? '1') === '1') ? db()->query('SELECT title, slug FROM posts WHERE is_visible = 1 ORDER BY published_on DESC, id DESC LIMIT 1')->fetch() : null;
$messenger = trim(setting('messenger_username'));
$ga4 = strtoupper(trim(setting('ga4_id')));
$title = setting('links_title') ?: $name;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · Links</title>
<meta name="description" content="<?= e(implode(' · ', $roles)) ?>. Portfolio, booking and socials.">
<link rel="canonical" href="<?= e($live) ?>links.html">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e(implode(' · ', $roles)) ?>">
<?php if ($avatar): ?><meta property="og:image" content="<?= e($live . $avatar) ?>"><?php endif; ?>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><path d='M16 2l12 7v14l-12 7-12-7V9z' fill='%23f5b335'/></svg>">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Outfit:wght@500;600;700&family=Great+Vibes&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">
<script>(function(d){d.classList.add('js');try{if(localStorage.getItem('theme')==='light')d.setAttribute('data-theme','light')}catch(e){}})(document.documentElement)</script>
<?php if (preg_match('/^G-[A-Z0-9]{4,16}$/', $ga4)): ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($ga4) ?>"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag('js',new Date());gtag('config','<?= e($ga4) ?>');</script>
<?php endif; ?>
</head>
<body class="links-page">
<div class="bg-fx" aria-hidden="true"><span class="glow g1"></span><span class="glow g2"></span></div>
<main class="links-wrap">
  <header class="links-head">
    <span class="links-avatar"><?php if ($avatar): ?><img src="<?= e(url($avatar)) ?>" alt="<?= e($name) ?>"><?php else: ?><i class="fa-solid fa-user"></i><?php endif; ?></span>
    <h1><?= e($title) ?></h1>
    <?php if ($roles): ?><p class="links-roles"><?= e(implode(' · ', $roles)) ?></p><?php endif; ?>
    <?php if (setting('contact_location')): ?><p class="muted"><i class="fa-solid fa-location-dot"></i> <?= e($s['contact_location']) ?></p><?php endif; ?>
  </header>

  <nav class="links-list" aria-label="Links">
    <a class="link-btn-big primary" href="./" data-track="links_portfolio"><i class="fa-solid fa-briefcase"></i><span>View my portfolio</span><i class="fa-solid fa-arrow-right"></i></a>
    <a class="link-btn-big" href="./#contact"><i class="fa-regular fa-calendar-check"></i><span>Book a shoot / project</span><i class="fa-solid fa-arrow-right"></i></a>
    <?php if ($hasPackages): ?><a class="link-btn-big" href="./#packages"><i class="fa-solid fa-tags"></i><span>Packages &amp; prices</span><i class="fa-solid fa-arrow-right"></i></a><?php endif; ?>
    <?php if ($messenger): ?><a class="link-btn-big" href="https://m.me/<?= e(rawurlencode($messenger)) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-facebook-messenger"></i><span>Message me on Messenger</span><i class="fa-solid fa-arrow-up-right-from-square"></i></a><?php endif; ?>
    <?php if ($latestStory): ?><a class="link-btn-big" href="post.php?slug=<?= e($latestStory['slug']) ?>"><i class="fa-solid fa-feather-pointed"></i><span>New: <?= e(str_replace('**', '', $latestStory['title'])) ?></span><i class="fa-solid fa-arrow-right"></i></a><?php endif; ?>
    <a class="link-btn-big" href="links.php?vcf=1" download="contact.vcf"><i class="fa-regular fa-address-card"></i><span>Save my contact</span><i class="fa-solid fa-download"></i></a>
  </nav>

  <?php if ($socials): ?>
  <div class="links-socials">
    <?php foreach ($socials as $so): ?>
      <a href="<?= e($so['url']) ?>" target="_blank" rel="noopener me" aria-label="<?= e($so['platform']) ?>" title="<?= e($so['platform']) ?>"><i class="<?= e($so['icon'] ?: 'fa-solid fa-link') ?>"></i></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <details class="links-qr">
    <summary><i class="fa-solid fa-qrcode"></i> Show QR code</summary>
    <div class="qr" data-qr="<?= e($live) ?>links.html"></div>
    <p class="muted">Scan to open this page</p>
  </details>

  <p class="links-foot"><a href="./"><?= e(setting('logo_text', $name)) ?></a></p>
</main>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" defer></script>
<script src="assets/js/main.js?v=<?= filemtime(__DIR__ . '/assets/js/main.js') ?>" defer></script>
</body>
</html>
