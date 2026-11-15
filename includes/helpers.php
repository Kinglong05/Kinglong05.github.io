<?php
declare(strict_types=1);

/** HTML-escape. */
function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Escape, then turn **text** into gold highlighted text and newlines into <br>. */
function hl(?string $s): string
{
    $out = e($s);
    $out = preg_replace('/\*\*(.+?)\*\*/s', '<span class="accent">$1</span>', $out);
    return nl2br($out, false);
}

/** Build a URL for a stored path (uploads/..., assets/...) relative to the current script. */
function url(?string $path): string
{
    $path = (string) $path;
    if ($path === '') {
        return '';
    }
    if (preg_match('#^(https?:)?//#i', $path) || str_starts_with($path, '#') || preg_match('#^(mailto|tel):#i', $path)) {
        return $path;
    }
    return URL_PREFIX . ltrim($path, '/');
}

function is_installed(): bool
{
    return is_file(APP_ROOT . '/config/installed.lock');
}

function redirect(string $to): never
{
    header('Location: ' . $to);
    exit;
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

/* ---------------- CSRF ---------------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valid(?string $token): bool
{
    return is_string($token) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

function require_csrf(): void
{
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        http_response_code(419);
        exit('Your session expired. Go back, refresh the page and try again.');
    }
}

/* ---------------- Flash messages ---------------- */

function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* ---------------- Uploads ---------------- */

const IMAGE_MIMES = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
];
const FILE_MIMES = [
    'application/pdf' => 'pdf',
];
const VIDEO_MIMES = [
    'video/mp4'       => 'mp4',
    'video/webm'      => 'webm',
    'video/quicktime' => 'mov',
];

function max_video_mb(): int
{
    return defined('MAX_VIDEO_MB') ? (int) MAX_VIDEO_MB : 60;
}

/**
 * Validate and store an uploaded file. Returns the stored relative path (e.g. uploads/images/abc.webp).
 * @throws RuntimeException
 */
function handle_upload(string $field, string $kind = 'image'): string
{
    $f = $_FILES[$field] ?? null;
    if (!$f || !is_array($f) || is_array($f['error'])) {
        throw new RuntimeException('Invalid upload.');
    }
    if ($f['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException(match ($f['error']) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File is too large for the server.',
            UPLOAD_ERR_NO_FILE => 'No file was selected.',
            default => 'Upload failed (code ' . $f['error'] . ').',
        });
    }
    $maxMb = match ($kind) { 'image' => MAX_IMAGE_MB, 'video' => max_video_mb(), default => MAX_FILE_MB };
    if ($f['size'] > $maxMb * 1024 * 1024) {
        throw new RuntimeException("File must be smaller than {$maxMb} MB.");
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) ?: '';
    $allowed = match ($kind) { 'image' => IMAGE_MIMES, 'video' => VIDEO_MIMES, default => FILE_MIMES };
    if (!isset($allowed[$mime])) {
        throw new RuntimeException(match ($kind) {
            'image' => 'Only JPG, PNG, WEBP or GIF images are allowed.',
            'video' => 'Only MP4, WEBM or MOV videos are allowed.',
            default => 'Only PDF files are allowed.',
        });
    }
    if ($kind === 'image' && @getimagesize($f['tmp_name']) === false) {
        throw new RuntimeException('That file is not a valid image.');
    }
    $sub = match ($kind) { 'image' => 'images', 'video' => 'videos', default => 'files' };
    $dir = APP_ROOT . '/uploads/' . $sub;
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new RuntimeException('Could not create upload folder.');
    }
    $name = date('Ymd') . '-' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) {
        throw new RuntimeException('Could not save the uploaded file.');
    }
    return 'uploads/' . $sub . '/' . $name;
}

function delete_upload(?string $path): void
{
    $path = (string) $path;
    if ($path === '' || !str_starts_with($path, 'uploads/') || str_contains($path, '..')) {
        return;
    }
    $full = APP_ROOT . '/' . $path;
    if (is_file($full)) {
        @unlink($full);
    }
}

/* ---------------- Gallery / video ---------------- */

const GALLERY_CATEGORIES = [
    'photography' => 'Photography',
    'videography' => 'Videography',
    'drone'       => 'Drone',
];
const GALLERY_ICONS = [
    'photography' => 'fa-solid fa-camera',
    'videography' => 'fa-solid fa-video',
    'drone'       => 'icon-drone',
];

const GEAR_CATEGORIES = [
    'camera'   => 'Camera',
    'lens'     => 'Lens',
    'drone'    => 'Drone',
    'audio'    => 'Audio',
    'lighting' => 'Lighting',
    'support'  => 'Tripod / Gimbal',
    'computer' => 'Computer',
    'other'    => 'Other',
];
const GEAR_ICONS = [
    'camera' => 'fa-solid fa-camera', 'lens' => 'fa-solid fa-circle-dot', 'drone' => 'icon-drone',
    'audio' => 'fa-solid fa-microphone', 'lighting' => 'fa-regular fa-lightbulb', 'support' => 'fa-solid fa-person-walking-luggage',
    'computer' => 'fa-solid fa-laptop', 'other' => 'fa-solid fa-toolbox',
];

function slugify(string $s): string
{
    $s = str_replace('**', '', $s);
    if (function_exists('iconv')) $s = (string) @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    $s = strtolower(trim(preg_replace('~[^A-Za-z0-9]+~', '-', $s), '-'));
    return substr($s, 0, 80) ?: 'story';
}

/** Story text -> safe HTML. Paragraphs, ## headings, - lists, > quotes, **gold**, [links](https://), ![photos](url). */
function render_story(string $body): string
{
    $out = '';
    $blocks = preg_split('/\R\s*\R/', trim(str_replace("\r", '', $body)));
    $inline = function (string $t): string {
        $h = hl($t); // escapes + **gold** + line breaks
        return preg_replace('~\[([^\]]+)\]\((https?://[^\s)]+)\)~', '<a href="$2" target="_blank" rel="noopener">$1</a>', $h);
    };
    foreach ($blocks as $b) {
        $b = trim($b);
        if ($b === '') continue;
        if (preg_match('/^!\[([^\]]*)\]\(((?:https?:\/\/|uploads\/)[^\s)]+)\)$/', $b, $m)) {
            $src = str_starts_with($m[2], 'uploads/') ? url($m[2]) : $m[2];
            $out .= '<figure><img src="' . e($src) . '" alt="' . e($m[1]) . '" loading="lazy">' . ($m[1] !== '' ? '<figcaption>' . e($m[1]) . '</figcaption>' : '') . '</figure>';
        } elseif (preg_match('/^(#{2,3})\s+(.+)$/s', $b, $m)) {
            $tag = strlen($m[1]) === 2 ? 'h2' : 'h3';
            $out .= "<$tag>" . $inline($m[2]) . "</$tag>";
        } elseif (preg_match('/^[-*]\s/', $b)) {
            $items = preg_split('/\R/', $b);
            $out .= '<ul>' . implode('', array_map(fn($li) => '<li>' . $inline(preg_replace('/^[-*]\s+/', '', $li)) . '</li>', $items)) . '</ul>';
        } elseif (str_starts_with($b, '>')) {
            $out .= '<blockquote>' . $inline(preg_replace('/^>\s?/m', '', $b)) . '</blockquote>';
        } else {
            $out .= '<p>' . $inline($b) . '</p>';
        }
    }
    return $out;
}

function youtube_id(string $url): ?string
{
    if (preg_match('~(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/|v/))([A-Za-z0-9_-]{11})~', $url, $m)) {
        return $m[1];
    }
    return null;
}

/**
 * Work out how to play a video link.
 * Returns ['kind' => 'iframe'|'file'|'link', 'src' => ..., 'thumb' => ?string]
 */
function is_facebook_url(string $url): bool
{
    return (bool) preg_match('~^https?://([a-z0-9-]+\.)?(facebook\.com|fb\.watch|fb\.com)/~i', trim($url));
}

/** XAMPP's PHP often has no CA bundle configured; point curl at one that exists (verification stays ON). */
function curl_ca_options(): array
{
    if (ini_get('curl.cainfo') || ini_get('openssl.cafile')) return [];
    $candidates = [
        dirname(PHP_BINARY) . '/extras/ssl/cacert.pem',
        dirname(PHP_BINARY, 2) . '/apache/bin/curl-ca-bundle.crt',
        'C:/xampp/apache/bin/curl-ca-bundle.crt',
        '/etc/ssl/certs/ca-certificates.crt',
        '/etc/pki/tls/certs/ca-bundle.crt',
    ];
    foreach ($candidates as $f) {
        if (is_file($f)) return [CURLOPT_CAINFO => $f];
    }
    return [];
}

const FB_CRAWLER_UA ='facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)';

/**
 * Look up a Facebook link the way Facebook's own link-preview crawler does.
 * Share links (/share/p/..., /share/v/..., fb.watch/...) don't embed; the real post/video URL does.
 * Returns ['url' => canonical URL, 'image' => og:image URL|null]. Falls back to the input URL.
 */
function facebook_lookup(string $url): array
{
    $url = trim($url);
    $out = ['url' => $url, 'image' => null];
    if (!is_facebook_url($url) || !function_exists('curl_init')) return $out;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 6,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_USERAGENT      => FB_CRAWLER_UA,
        CURLOPT_HTTPHEADER     => ['Accept-Language: en-US,en;q=0.8'],
    ] + curl_ca_options());
    $html = (string) curl_exec($ch);
    $final = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);

    $meta = function (string $prop) use ($html): ?string {
        if (preg_match('~<meta[^>]+property="' . preg_quote($prop, '~') . '"[^>]+content="([^"]+)"~i', $html, $m)) {
            return html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        return null;
    };
    $canonical = $meta('og:url');
    if (!$canonical && preg_match('~<link rel="canonical" href="([^"]+)"~i', $html, $m)) {
        $canonical = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    $candidate = $canonical ?: $final;
    if ($candidate && is_facebook_url($candidate) && !preg_match('~/(login|checkpoint)~i', $candidate)) {
        // /{page}/posts/{fancy-slug}/{id}/  ->  /{page}/posts/{id}/   (the slug can be very long)
        if (preg_match('~^(https://www\.facebook\.com/[^/]+/posts/)(?:[^/]+/)?(\d+|pfbid\w+)/?~i', $candidate, $m)) {
            $candidate = $m[1] . $m[2] . '/';
        }
        // /{page}/videos/{fancy-slug}/{id}/  ->  /{page}/videos/{id}/
        if (preg_match('~^(https://www\.facebook\.com/[^/]+/videos/)(?:[^/]+/)?(\d+)/?~i', $candidate, $m)) {
            $candidate = $m[1] . $m[2] . '/';
        }
        $candidate = preg_replace('~([?&])(rdid|share_url|mibextid|_rdr)=[^&]*&?~i', '$1', $candidate);
        $out['url'] = rtrim($candidate, '?&');
    }
    $img = $meta('og:image');
    if ($img && preg_match('~^https://~i', $img)) $out['image'] = $img;
    return $out;
}

function resolve_facebook_url(string $url): string
{
    return facebook_lookup($url)['url'];
}

/** True for near-solid black/white images (Facebook often uses a video's blank first frame as its thumbnail). */
function image_is_blank(string $data): bool
{
    if (!function_exists('imagecreatefromstring')) return false;
    $im = @imagecreatefromstring($data);
    if (!$im) return false;
    $w = imagesx($im); $h = imagesy($im);
    $sum = 0; $sq = 0; $n = 0;
    for ($y = 0; $y < 12; $y++) {
        for ($x = 0; $x < 12; $x++) {
            $c = imagecolorat($im, (int) (($x + .5) * $w / 12), (int) (($y + .5) * $h / 12));
            $l = 0.299 * (($c >> 16) & 255) + 0.587 * (($c >> 8) & 255) + 0.114 * ($c & 255);
            $sum += $l; $sq += $l * $l; $n++;
        }
    }
    imagedestroy($im);
    $mean = $sum / $n;
    $sd = sqrt(max(0, $sq / $n - $mean * $mean));
    return $sd < 6 && ($mean < 20 || $mean > 245);
}

/** Download a remote image into uploads/images (validated like a normal upload). Returns the stored path or null. */
function download_image(string $url, string $ua = FB_CRAWLER_UA): ?string
{
    if (!function_exists('curl_init') || !preg_match('~^https://~i', $url)) return null;
    $max = MAX_IMAGE_MB * 1024 * 1024;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 4, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20, CURLOPT_USERAGENT => $ua,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
    ] + curl_ca_options());
    $data = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if (!is_string($data) || $code !== 200 || $data === '' || strlen($data) > $max) return null;
    $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($data) ?: '';
    if (!isset(IMAGE_MIMES[$mime]) || @getimagesizefromstring($data) === false) return null;
    if (image_is_blank($data)) return null; // e.g. a video's black first frame
    $dir = APP_ROOT . '/uploads/images';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) return null;
    $name = date('Ymd') . '-' . bin2hex(random_bytes(8)) . '.' . IMAGE_MIMES[$mime];
    return file_put_contents($dir . '/' . $name, $data) ? 'uploads/images/' . $name : null;
}

/**
 * Can this Facebook video be played on other websites?
 * Facebook refuses for videos with copyrighted music/content, and for non-public videos.
 * Returns true / false, or null if it couldn't be checked.
 */
function facebook_embed_ok(string $url): ?bool
{
    if (!function_exists('curl_init')) return null;
    $ch = curl_init('https://www.facebook.com/plugins/video.php?href=' . rawurlencode($url) . '&show_text=false');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 15,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
        CURLOPT_HTTPHEADER => ['Accept-Language: en-US,en;q=0.8'],
    ] + curl_ca_options());
    $html = (string) curl_exec($ch);
    curl_close($ch);
    if ($html === '') return null;
    if (preg_match('~content owned by someone else|can.t be embedded~i', $html)) return false;
    if (preg_match('~hd_src|sd_src|playable_url~i', $html)) return true;
    if (preg_match('~video unavailable|isn.t available|not available~i', $html)) return false;
    return null;
}

/** Is this a Facebook video/reel link (plays in the video player) rather than a post/photo? */
function is_facebook_video(string $url): bool
{
    return (bool) preg_match('~(/videos?/|/reel/|/watch|fb\.watch|/share/[vr]/)~i', $url);
}

/**
 * Work out how to show/play a media link.
 * Returns ['kind' => 'iframe'|'file'|'link', 'src' => lightbox URL, 'inline' => in-card URL|null,
 *          'thumb' => ?string, 'vertical' => bool, 'post' => bool, 'provider' => string]
 */
function video_info(?string $url): ?array
{
    $url = trim((string) $url);
    if ($url === '' || $url === '#') return null;
    $base = ['inline' => null, 'thumb' => null, 'vertical' => false, 'post' => false, 'provider' => 'other'];

    if ($id = youtube_id($url)) {
        return ['kind' => 'iframe', 'src' => "https://www.youtube-nocookie.com/embed/$id?autoplay=1&rel=0",
            'inline' => "https://www.youtube-nocookie.com/embed/$id?rel=0", 'thumb' => "https://i.ytimg.com/vi/$id/hqdefault.jpg",
            'vertical' => str_contains($url, '/shorts/'), 'provider' => 'youtube'] + $base;
    }
    if (is_facebook_url($url)) {
        $u = preg_replace('~^https?://(m|web|mbasic|business|touch)\.facebook\.com~i', 'https://www.facebook.com', $url);
        $href = rawurlencode($u);
        $isVideo = is_facebook_video($u);
        if ($isVideo) {
            return ['kind' => 'iframe', 'provider' => 'facebook',
                'src' => "https://www.facebook.com/plugins/video.php?href=$href&show_text=false&autoplay=true&width=1280",
                'inline' => "https://www.facebook.com/plugins/video.php?href=$href&show_text=false&width=560",
                'vertical' => (bool) preg_match('~(/reel/|/share/r/)~i', $u)] + $base;
        }
        // Photo / regular post
        return ['kind' => 'iframe', 'provider' => 'facebook', 'post' => true, 'vertical' => true,
            'src' => "https://www.facebook.com/plugins/post.php?href=$href&show_text=true&width=500",
            'inline' => "https://www.facebook.com/plugins/post.php?href=$href&show_text=false&width=500"] + $base;
    }
    if (preg_match('~vimeo\.com/(?:video/)?(\d+)~', $url, $m)) {
        return ['kind' => 'iframe', 'src' => "https://player.vimeo.com/video/{$m[1]}?autoplay=1",
            'inline' => "https://player.vimeo.com/video/{$m[1]}", 'provider' => 'vimeo'] + $base;
    }
    if (preg_match('~drive\.google\.com/file/d/([A-Za-z0-9_-]+)~', $url, $m)) {
        return ['kind' => 'iframe', 'src' => "https://drive.google.com/file/d/{$m[1]}/preview",
            'inline' => "https://drive.google.com/file/d/{$m[1]}/preview", 'provider' => 'drive'] + $base;
    }
    if (preg_match('~\.(mp4|webm|mov)(\?.*)?$~i', $url)) {
        return ['kind' => 'file', 'src' => $url, 'inline' => $url, 'provider' => 'file'] + $base;
    }
    return ['kind' => 'link', 'src' => $url] + $base;
}

function provider_icon(?array $v): string
{
    return match ($v['provider'] ?? '') {
        'facebook' => 'fa-brands fa-facebook',
        'youtube'  => 'fa-brands fa-youtube',
        'vimeo'    => 'fa-brands fa-vimeo-v',
        'drive'    => 'fa-brands fa-google-drive',
        default    => 'fa-solid fa-play',
    };
}

/* ---------------- Misc ---------------- */

function str_limit(string $s, int $n): string
{
    return mb_strlen($s) > $n ? rtrim(mb_substr($s, 0, $n - 1)) . '…' : $s;
}

function time_ago(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j, Y', strtotime($datetime));
}

function split_lines(string $s): array
{
    return array_values(array_filter(array_map('trim', preg_split('/\R/', $s)), 'strlen'));
}

function split_tags(?string $s): array
{
    // Accepts "React, Node.js" or "React · Node.js" or "React | Node.js"
    return array_values(array_filter(array_map('trim', preg_split('/\s*(?:,|·|•|\|)\s*/u', (string) $s)), 'strlen'));
}
