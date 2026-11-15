<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

/** Tables that hold list content (used for whitelisting dynamic table names). */
const CONTENT_TABLES = ['skills', 'services', 'packages', 'projects', 'gallery', 'comparisons', 'experiences', 'certificates', 'gear', 'testimonials', 'socials', 'posts'];

function fetch_rows(string $table, bool $visibleOnly = true): array
{
    if (!in_array($table, CONTENT_TABLES, true)) {
        throw new InvalidArgumentException('Unknown table');
    }
    $sql = "SELECT * FROM `$table`" . ($visibleOnly ? ' WHERE is_visible = 1' : '') . ' ORDER BY sort_order ASC, id ASC';
    return db()->query($sql)->fetchAll();
}

function settings(bool $refresh = false): array
{
    static $cache = null;
    if ($cache === null || $refresh) {
        $cache = [];
        foreach (db()->query('SELECT skey, svalue FROM settings') as $row) {
            $cache[$row['skey']] = (string) $row['svalue'];
        }
    }
    return $cache;
}

function setting(string $key, string $default = ''): string
{
    $s = settings();
    return isset($s[$key]) && $s[$key] !== '' ? $s[$key] : $default;
}

function save_setting(string $key, ?string $value): void
{
    $st = db()->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)');
    $st->execute([$key, $value]);
}

/** Extra photos of a gallery item (its album), in display order. */
function album_photos(int $galleryId): array
{
    $st = db()->prepare('SELECT id, image FROM gallery_photos WHERE gallery_id = ? ORDER BY sort_order ASC, id ASC');
    $st->execute([$galleryId]);
    return $st->fetchAll();
}
