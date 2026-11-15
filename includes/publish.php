<?php
declare(strict_types=1);

/**
 * Static "Publish" for GitHub Pages.
 *
 * GitHub Pages can't run PHP, so we render the homepage on XAMPP, save it as
 * index.html, copy the photos/videos it uses into media/, and push to GitHub.
 * You keep editing in the local admin; Publish makes the live site match.
 */

const PUBLISH_MEDIA_DIR = 'media';

function publish_site_url(): string
{
    $u = trim(setting('site_url', 'https://kinglong05.github.io/'));
    return rtrim($u, '/') . '/';
}

/** The local address of the site, e.g. http://localhost/PORTFOLIO/ (from the current admin request). */
function publish_local_base(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/admin/x.php', 2)), '/');
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $dir . '/';
}

function publish_http_get(string $url): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_FOLLOWLOCATION => false, CURLOPT_USERAGENT => 'PortfolioPublisher/1.0']);
    $html = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if (!is_string($html) || $code !== 200) {
        throw new RuntimeException("Could not render the homepage ($url): " . ($err ?: "HTTP $code"));
    }
    return $html;
}

function publish_rrmdir(string $dir): void
{
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) { $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname()); }
    rmdir($dir);
}

/**
 * Build index.html + media/ in the project root. Returns a short report.
 * @throws RuntimeException
 */
function build_static_site(): array
{
    $local = publish_local_base();
    $live = publish_site_url();

    // Pages to publish: the homepage + one page per visible story
    $pages = ['index.html' => publish_http_get($local . 'index.php')];
    $slugs = db()->query('SELECT slug FROM posts WHERE is_visible = 1')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($slugs as $slug) {
        $pages['story-' . $slug . '.html'] = publish_http_get($local . 'post.php?slug=' . rawurlencode($slug));
    }
    foreach ($pages as $file => $page) {
        // 1) Absolute links (canonical, og:url, og:image) -> live address; story links -> static pages
        $page = str_replace($local, $live, $page);
        $page = preg_replace('~post\.php\?slug=([a-z0-9-]+)~', 'story-$1.html', $page);
        $pages[$file] = $page;
    }
    $html = implode("\n<!--page-->\n", $pages); // processed together, split again before saving

    // 2) Media: copy every upload the pages use into media/ and point the pages there
    $mediaRoot = APP_ROOT . '/' . PUBLISH_MEDIA_DIR;
    publish_rrmdir($mediaRoot);
    preg_match_all('~(?<![\w.-])uploads/((?:images|videos|files)/[\w.-]+\.(?:jpe?g|png|webp|gif|mp4|webm|mov|pdf))~i', $html, $m);
    $files = array_unique($m[1]);
    $bytes = 0;
    $missing = [];
    foreach ($files as $rel) {
        $from = APP_ROOT . '/uploads/' . $rel;
        $to = $mediaRoot . '/' . $rel;
        if (!is_file($from)) { $missing[] = $rel; continue; }
        if (!is_dir(dirname($to))) mkdir(dirname($to), 0755, true);
        copy($from, $to);
        $bytes += filesize($to);
    }
    $html = preg_replace('~(?<![\w.-])uploads/(images|videos|files)/~i', PUBLISH_MEDIA_DIR . '/$1/', $html);

    // 2b) Make photos light: convert to WebP (max 1920px) so the site loads fast on mobile data
    $saved = 0;
    foreach ($files as $rel) {
        if (!preg_match('~^images/(.+)\.(jpe?g|png|webp)$~i', $rel, $mm)) continue;
        $src = $mediaRoot . '/' . $rel;
        if (!is_file($src)) continue;
        $webpRel = 'images/' . $mm[1] . '.webp';
        $before = filesize($src);
        if (optimize_to_webp($src, $mediaRoot . '/' . $webpRel)) {
            $after = filesize($mediaRoot . '/' . $webpRel);
            if ($webpRel !== $rel) {
                if ($after < $before) {
                    unlink($src);
                    $html = str_replace(PUBLISH_MEDIA_DIR . '/' . $rel, PUBLISH_MEDIA_DIR . '/' . $webpRel, $html);
                    $saved += $before - $after;
                } else {
                    unlink($mediaRoot . '/' . $webpRel); // original was already smaller
                }
            } else {
                $saved += max(0, $before - $after);
            }
        }
    }
    $bytes = 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($mediaRoot, FilesystemIterator::SKIP_DOTS)) as $f) $bytes += $f->getSize();

    // 3) Contact form: no PHP on GitHub Pages -> Formspree (if set) or the visitor's email app
    $html = preg_replace('~<input type="hidden" name="csrf" value="[^"]*">~', '', $html);
    $html = str_replace('name="website"', 'name="_gotcha"', $html); // Formspree's spam honeypot field
    $formspree = preg_replace('~[^A-Za-z0-9]~', '', setting('formspree_id'));
    if ($formspree !== '') {
        $html = str_replace('action="contact.php"', 'action="https://formspree.io/f/' . $formspree . '"', $html);
        $formMode = 'Formspree';
    } else {
        $email = setting('contact_email');
        $html = str_replace('action="contact.php"', 'action="#contact" data-mailto="' . e($email) . '"', $html);
        $formMode = $email ? "opens the visitor's email app (to $email)" : 'disabled (no email set)';
    }

    // 4) Save every page (remove stories that were deleted or hidden)
    $stamp = date('Y-m-d H:i');
    foreach (glob(APP_ROOT . '/story-*.html') ?: [] as $old) unlink($old);
    $parts = explode("\n<!--page-->\n", $html);
    foreach (array_keys($pages) as $i => $file) {
        $page = preg_replace('~^<!doctype html>~i', "<!doctype html>\n<!-- Generated by Admin > Publish on $stamp. Edit your content in the admin panel, not here. -->", $parts[$i], 1);
        file_put_contents(APP_ROOT . '/' . $file, $page);
    }
    if (!is_file(APP_ROOT . '/.nojekyll')) file_put_contents(APP_ROOT . '/.nojekyll', '');

    // Custom domain for GitHub Pages (CNAME file)
    $domain = strtolower(trim(preg_replace('~^https?://|/.*$~i', '', setting('custom_domain'))));
    if ($domain !== '' && preg_match('~^[a-z0-9.-]+\.[a-z]{2,}$~', $domain)) {
        file_put_contents(APP_ROOT . '/CNAME', $domain . "\n");
    } elseif (is_file(APP_ROOT . '/CNAME')) {
        unlink(APP_ROOT . '/CNAME');
    }

    // 5) Search engines + a friendly "page not found"
    $day = date('Y-m-d');
    $urls = "  <url><loc>" . e($live) . "</loc><lastmod>$day</lastmod><changefreq>weekly</changefreq><priority>1.0</priority></url>\n";
    foreach ($slugs as $slug) $urls .= "  <url><loc>" . e($live . 'story-' . $slug . '.html') . "</loc><lastmod>$day</lastmod><priority>0.7</priority></url>\n";
    file_put_contents(APP_ROOT . '/sitemap.xml', "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n$urls</urlset>\n");
    file_put_contents(APP_ROOT . '/robots.txt', "User-agent: *\nAllow: /\nDisallow: /admin/\nDisallow: /includes/\nDisallow: /database/\nDisallow: /config/\nDisallow: /.deploy/\n\nSitemap: {$live}sitemap.xml\n");
    file_put_contents(APP_ROOT . '/404.html', publish_404_page());

    return [
        'pages'   => count($pages),
        'files'   => count($files) - count($missing),
        'missing' => $missing,
        'mb'      => round($bytes / 1048576, 1),
        'saved_mb'=> round($saved / 1048576, 1),
        'form'    => $formMode,
        'live'    => $live,
    ];
}

/** Resize (max 1920px) and save as WebP, keeping transparency and phone-photo rotation. */
function optimize_to_webp(string $src, string $dest, int $maxSide = 1920, int $quality = 80): bool
{
    if (!function_exists('imagewebp')) return false;
    $data = @file_get_contents($src);
    $im = $data !== false ? @imagecreatefromstring($data) : false;
    if (!$im) return false;
    // Phone photos are often stored sideways with an EXIF "rotate me" flag that WebP doesn't keep
    if (function_exists('exif_read_data') && preg_match('~\.jpe?g$~i', $src)) {
        $exif = @exif_read_data($src);
        $rot = [3 => 180, 6 => -90, 8 => 90][(int) ($exif['Orientation'] ?? 1)] ?? 0;
        if ($rot) { $r = imagerotate($im, $rot, 0); if ($r) { imagedestroy($im); $im = $r; } }
    }
    $w = imagesx($im); $h = imagesy($im);
    $scale = min(1, $maxSide / max($w, $h));
    $nw = max(1, (int) round($w * $scale)); $nh = max(1, (int) round($h * $scale));
    $out = imagecreatetruecolor($nw, $nh);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
    imagecopyresampled($out, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
    $ok = imagewebp($out, $dest, $quality);
    imagedestroy($im); imagedestroy($out);
    return $ok && is_file($dest) && filesize($dest) > 0;
}

function publish_404_page(): string
{
    $name = e(setting('owner_name', 'Portfolio'));
    $live = e(publish_site_url());
    return <<<HTML
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Page not found · $name</title><meta name="robots" content="noindex">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@600;700&family=Inter:wght@400;500&display=swap" rel="stylesheet">
<style>
:root{--gold:#f5b335;--gold2:#ffd27a}*{box-sizing:border-box}
body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:radial-gradient(circle at 75% 10%,rgba(245,179,53,.18),transparent 45%),#070707;color:#f5f5f4;font:15px/1.6 Inter,system-ui,sans-serif;text-align:center}
h1{font:700 clamp(72px,18vw,140px)/1 Outfit,sans-serif;margin:0;background:linear-gradient(100deg,var(--gold),var(--gold2));-webkit-background-clip:text;background-clip:text;color:transparent}
p{color:#9b9b9f;margin:12px 0 26px}a{display:inline-block;padding:12px 22px;border-radius:10px;background:linear-gradient(180deg,var(--gold2),var(--gold));color:#1a1205;font-weight:600;text-decoration:none}
</style></head><body><main><h1>404</h1><p>This page flew off with the drone.<br>Let's get you back to $name's portfolio.</p><a href="$live">Back to home</a></main></body></html>
HTML;
}

/* ---------------- Vercel ---------------- */

const VERCEL_DIR = '.deploy/vercel';

function vercel_ready(): bool
{
    return is_file(APP_ROOT . '/' . VERCEL_DIR . '/.vercel/project.json');
}

/** Copy the built site into .deploy/vercel and deploy it to production. Returns the live URL + log. */
function deploy_vercel(): array
{
    $dir = APP_ROOT . '/' . VERCEL_DIR;
    // Fresh copy of the static site (keep the .vercel project link)
    foreach (glob("$dir/story-*.html") ?: [] as $old) unlink($old);
    $pagesToCopy = array_merge(['index.html', '404.html', 'sitemap.xml', 'robots.txt'], array_map('basename', glob(APP_ROOT . '/story-*.html') ?: []));
    foreach ($pagesToCopy as $f) {
        if (is_file(APP_ROOT . '/' . $f)) copy(APP_ROOT . '/' . $f, "$dir/$f");
    }
    foreach ([PUBLISH_MEDIA_DIR, 'assets'] as $d) {
        publish_rrmdir("$dir/$d");
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(APP_ROOT . "/$d", FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($it as $f) {
            $to = "$dir/$d/" . $it->getSubPathname();
            if ($f->isDir()) {
                if (!is_dir($to)) mkdir($to, 0755, true);
            } else {
                if (!is_dir(dirname($to))) mkdir(dirname($to), 0755, true);
                copy($f->getPathname(), $to);
            }
        }
    }
    file_put_contents("$dir/vercel.json", json_encode(['cleanUrls' => true, 'trailingSlash' => false], JSON_PRETTY_PRINT));

    // Prefer the Vercel tool installed in .deploy/tools (fast, no download); fall back to npx
    $local = APP_ROOT . '/.deploy/tools/node_modules/.bin/' . (PHP_OS_FAMILY === 'Windows' ? 'vercel.cmd' : 'vercel');
    if (is_file($local)) {
        $base = [$local];
    } else {
        $npx = null;
        foreach (['C:/Program Files/nodejs/npx.cmd', '/usr/local/bin/npx', '/usr/bin/npx'] as $c) if (is_file($c)) { $npx = $c; break; }
        if (!$npx) throw new RuntimeException('Node.js was not found, so Vercel could not be updated.');
        $base = [$npx, '--yes', 'vercel@65.16.0'];
    }
    $cmd = array_merge(PHP_OS_FAMILY === 'Windows' ? ['cmd', '/c'] : [], $base, ['deploy', '--prod', '--yes']);
    $env = getenv() ?: [];
    if (empty($env['APPDATA']) && !empty($env['USERPROFILE'])) $env['APPDATA'] = $env['USERPROFILE'] . '\\AppData\\Roaming';
    $env['CI'] = '1';
    $p = @proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $dir, $env);
    if (!is_resource($p)) throw new RuntimeException('Could not start the Vercel tool.');
    $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $code = proc_close($p);
    $out = trim(preg_replace('~<claude-code-hint[^>]*/>~', '', $out));
    if ($code !== 0) {
        $hint = preg_match('~token|log ?in|credentials~i', $out) ? "\n\nVercel needs you to sign in again: open a terminal and run  npx vercel login" : '';
        throw new RuntimeException("Vercel deploy failed:\n" . $out . $hint);
    }
    // Keep the main (production) address, not the one-off link of this single upload
    $url = '';
    if (preg_match('~Production URL:\s*(https://[^\s"]+)~', $out, $m)) {
        $url = $m[1];
        save_setting('vercel_url', $url);
    }
    return ['url' => $url ?: setting('vercel_url'), 'log' => $out];
}

/* ---------------- Git ---------------- */

function git_binary(): ?string
{
    foreach (['git', 'C:/Program Files/Git/cmd/git.exe', 'C:/Program Files (x86)/Git/cmd/git.exe', '/usr/bin/git'] as $g) {
        if ($g === 'git') {
            $r = git_run(['--version'], 'git');
            if ($r['code'] === 0) return 'git';
        } elseif (is_file($g)) {
            return $g;
        }
    }
    return null;
}

/** Run git safely (no shell string building). */
function git_run(array $args, ?string $bin = null): array
{
    $bin ??= git_binary() ?? 'git';
    $cmd = array_merge([$bin, '-C', APP_ROOT], $args);
    $env = array_merge(getenv() ?: [], ['GIT_TERMINAL_PROMPT' => '0', 'GCM_INTERACTIVE' => 'never']);
    $p = @proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, APP_ROOT, $env);
    if (!is_resource($p)) return ['code' => 127, 'out' => 'Could not start git.'];
    $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    return ['code' => proc_close($p), 'out' => trim($out)];
}

/** Commit the generated site and push it. Returns a log of what happened. */
function push_static_site(): array
{
    $log = [];
    // Generated files only (never your code). Include story pages/CNAME that exist now or existed before (deleted ones).
    $paths = ['index.html', '404.html', 'sitemap.xml', 'robots.txt', PUBLISH_MEDIA_DIR, '.nojekyll'];
    $tracked = array_filter(explode("\n", git_run(['ls-files', '--', 'story-*.html', 'CNAME'])['out']));
    $current = array_map('basename', array_merge(glob(APP_ROOT . '/story-*.html') ?: [], is_file(APP_ROOT . '/CNAME') ? [APP_ROOT . '/CNAME'] : []));
    $paths = array_values(array_unique(array_merge($paths, $tracked, $current)));
    $add = git_run(array_merge(['add', '-A', '--'], $paths));
    $log[] = '$ git add index.html media/ sitemap.xml robots.txt 404.html' . ($add['out'] ? "\n" . $add['out'] : '');
    if ($add['code'] !== 0) throw new RuntimeException(implode("\n", $log));

    $staged = git_run(['diff', '--cached', '--quiet']);
    if ($staged['code'] === 0) {
        $log[] = 'Nothing changed since the last publish.';
    } else {
        $c = git_run(['commit', '-m', 'Publish site ' . date('Y-m-d H:i')]);
        $log[] = '$ git commit' . "\n" . $c['out'];
        if ($c['code'] !== 0) throw new RuntimeException(implode("\n\n", $log));
    }
    $p = git_run(['push', 'origin', 'HEAD']);
    $log[] = '$ git push' . "\n" . $p['out'];
    if ($p['code'] !== 0) throw new RuntimeException(implode("\n\n", $log));
    return $log;
}

function publish_allowed(): bool
{
    // Only on your own computer (XAMPP), never on a public server
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return in_array($ip, ['127.0.0.1', '::1'], true) && is_dir(APP_ROOT . '/.git');
}
