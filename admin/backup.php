<?php
/** One-click backup: database (.sql) + every uploaded file, in one .zip download. */
define('URL_PREFIX', '../');
require dirname(__DIR__) . '/includes/bootstrap.php';
require APP_ROOT . '/includes/admin_layout.php';
require_login();

function backup_sql(PDO $pdo): string
{
    $out = "-- Portfolio backup " . date('Y-m-d H:i:s') . "\n-- Restore: phpMyAdmin > your database > Import > this file\n\n";
    $out .= "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";
    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
        if ($t === 'login_attempts') continue;
        $create = $pdo->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_NUM)[1];
        $out .= "DROP TABLE IF EXISTS `$t`;\n$create;\n\n";
        $rows = $pdo->query("SELECT * FROM `$t`");
        $batch = [];
        while ($r = $rows->fetch(PDO::FETCH_ASSOC)) {
            $vals = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), array_values($r));
            $batch[] = '(' . implode(', ', $vals) . ')';
            if (count($batch) === 100) {
                $out .= "INSERT INTO `$t` (`" . implode('`, `', array_keys($r)) . "`) VALUES\n" . implode(",\n", $batch) . ";\n";
                $batch = [];
            }
        }
        if ($batch) {
            $cols = array_keys($pdo->query("SELECT * FROM `$t` LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: []);
            $out .= "INSERT INTO `$t` (`" . implode('`, `', $cols) . "`) VALUES\n" . implode(",\n", $batch) . ";\n";
        }
        $out .= "\n";
    }
    return $out . "SET FOREIGN_KEY_CHECKS = 1;\n";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    @set_time_limit(300);
    if (!class_exists('ZipArchive')) {
        flash('error', 'This server cannot create zip files (PHP zip extension missing).');
        redirect('backup.php');
    }
    $tmp = tempnam(sys_get_temp_dir(), 'pfbak');
    $zip = new ZipArchive();
    $zip->open($tmp, ZipArchive::OVERWRITE);
    $zip->addFromString('database.sql', backup_sql(db()));
    $count = 0;
    if (is_dir(APP_ROOT . '/uploads')) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(APP_ROOT . '/uploads', FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile()) {
                $zip->addFile($f->getPathname(), 'uploads/' . str_replace('\\', '/', $it->getSubPathname()));
                $count++;
            }
        }
    }
    $zip->addFromString('README.txt', "Portfolio backup made " . date('F j, Y g:i A') . "\n\n"
        . "database.sql  -> import in phpMyAdmin (your database > Import)\n"
        . "uploads/      -> copy into the project's uploads/ folder\n\n"
        . "Your passwords in config/config.php are NOT included. Keep this zip private: it contains your messages and settings.\n");
    $zip->close();
    save_setting('last_backup', date('c'));
    $name = 'portfolio-backup-' . date('Y-m-d-His') . '.zip';
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . filesize($tmp));
    readfile($tmp);
    unlink($tmp);
    exit;
}

$last = setting('last_backup');
$uploadsMb = 0;
if (is_dir(APP_ROOT . '/uploads')) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(APP_ROOT . '/uploads', FilesystemIterator::SKIP_DOTS)) as $f) $uploadsMb += $f->getSize();
}
admin_header('Backup', 'backup');
?>
<div class="panel form narrow">
  <h3><i class="fa-solid fa-box-archive"></i> Download a full backup</h3>
  <p class="muted">One zip with your <b>database</b> (texts, gallery, settings, messages) and <b>all uploaded photos &amp; videos</b> (<?= round($uploadsMb / 1048576, 1) ?> MB).
    Save it to Google Drive or a flash drive. If your laptop breaks, you can restore everything.</p>
  <p class="muted"><?= $last ? 'Last backup: ' . e(date('F j, Y g:i A', strtotime($last))) . ' (' . e(time_ago(date('Y-m-d H:i:s', strtotime($last)))) . ')' : '<b>You have never made a backup.</b>' ?></p>
  <form method="post" class="form-actions"><?= csrf_field() ?>
    <button class="btn btn-gold"><i class="fa-solid fa-download"></i> Download backup (.zip)</button>
  </form>
  <hr>
  <h3>How to restore</h3>
  <ol class="steps-list">
    <li>Unzip the backup.</li>
    <li>phpMyAdmin → your database → <b>Import</b> → choose <code>database.sql</code> → Go.</li>
    <li>Copy the <code>uploads</code> folder into your project folder (replace files).</li>
  </ol>
  <p class="help">Your passwords in <code>config/config.php</code> are not included. Keep the zip private: it contains your messages.</p>
</div>
<?php admin_footer();
