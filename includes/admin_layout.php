<?php
declare(strict_types=1);

function unread_count(): int
{
    return (int) db()->query('SELECT COUNT(*) FROM messages WHERE is_read = 0')->fetchColumn();
}

function admin_header(string $title, string $active = ''): void
{
    $admin = current_admin();
    $entities = require APP_ROOT . '/includes/entities.php';
    $unread = unread_count();
    $nav = function (string $href, string $icon, string $label, string $key, string $badge = '') use ($active) {
        $cls = $key === $active ? ' class="active"' : '';
        echo '<a href="' . e($href) . '"' . $cls . '><i class="' . e($icon) . '"></i><span>' . e($label) . '</span>' . $badge . '</a>';
    };
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> · Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/devicons/devicon@v2.16.0/devicon.min.css">
<link rel="stylesheet" href="assets/admin.css?v=<?= filemtime(APP_ROOT . '/admin/assets/admin.css') ?>">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><path d='M16 2l12 7v14l-12 7-12-7V9z' fill='%23f5b335'/></svg>">
</head>
<body>
<aside class="sidebar" id="sidebar">
  <a class="brand" href="index.php"><span class="brand-mark"><i class="fa-solid fa-code"></i></span><span>Portfolio<b>CMS</b></span></a>
  <nav>
    <?php $nav('index.php', 'fa-solid fa-gauge', 'Dashboard', 'dashboard'); ?>
    <?php $nav('messages.php', 'fa-solid fa-inbox', 'Messages', 'messages', $unread ? '<em class="badge">' . $unread . '</em>' : ''); ?>
    <div class="nav-label">Content</div>
    <?php $nav('settings.php', 'fa-solid fa-sliders', 'Site Settings', 'settings'); ?>
    <?php foreach ($entities as $key => $ent) { $nav('manage.php?type=' . $key, $ent['icon'], $ent['label'], 'manage-' . $key); } ?>
    <div class="nav-label">Account</div>
    <?php $nav('account.php', 'fa-solid fa-user-shield', 'My Account', 'account'); ?>
    <a href="../" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i><span>View Site</span></a>
    <a href="logout.php?token=<?= e(csrf_token()) ?>"><i class="fa-solid fa-right-from-bracket"></i><span>Log out</span></a>
  </nav>
</aside>
<div class="scrim" id="scrim"></div>
<div class="main">
  <header class="topbar">
    <button class="icon-btn menu-btn" id="menuBtn" aria-label="Open menu"><i class="fa-solid fa-bars"></i></button>
    <h1><?= e($title) ?></h1>
    <div class="who"><span class="avatar"><?= e(strtoupper(substr($admin['username'] ?? 'A', 0, 1))) ?></span><span class="hide-sm"><?= e($admin['username'] ?? '') ?></span></div>
  </header>
  <div class="content">
    <?php foreach (take_flashes() as $f): ?>
      <div class="alert alert-<?= e($f['type']) ?>"><i class="fa-solid <?= $f['type'] === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i><span><?= e($f['msg']) ?></span></div>
    <?php endforeach;
}

function admin_footer(): void
{
    ?>
  </div>
</div>
<script src="assets/admin.js?v=<?= filemtime(APP_ROOT . '/admin/assets/admin.js') ?>"></script>
</body>
</html>
<?php
}

/* ---------------- Generic form fields ---------------- */

function render_field(string $name, array $def, $value): void
{
    $type = $def['type'];
    $label = $def['label'];
    $req = !empty($def['required']);
    $id = 'f_' . $name;
    $cls = 'field' . (!empty($def['half']) ? ' half' : '') . ($type === 'checkbox' ? ' field-check' : '');
    echo '<div class="' . $cls . '">';

    if ($type === 'checkbox') {
        echo '<label class="switch"><input type="hidden" name="' . e($name) . '" value="0">'
            . '<input type="checkbox" id="' . $id . '" name="' . e($name) . '" value="1"' . ((string) $value === '1' ? ' checked' : '') . '>'
            . '<span class="slider"></span><span class="switch-label">' . e($label) . '</span></label>';
    } else {
        echo '<label for="' . $id . '">' . e($label) . ($req ? ' <b class="req">*</b>' : '') . '</label>';
        switch ($type) {
            case 'textarea':
                $rows = $def['rows'] ?? 4;
                echo '<textarea id="' . $id . '" name="' . e($name) . '" rows="' . (int) $rows . '"' . ($req ? ' required' : '') . '>' . e((string) $value) . '</textarea>';
                break;
            case 'image':
            case 'file':
            case 'video':
                $accept = match ($type) { 'image' => 'image/jpeg,image/png,image/webp,image/gif', 'video' => 'video/mp4,video/webm,video/quicktime', default => 'application/pdf' };
                echo '<div class="upload">';
                if ($value) {
                    if ($type === 'image') {
                        echo '<img class="upload-preview" src="' . e(url((string) $value)) . '" alt="">';
                    } elseif ($type === 'video') {
                        echo '<video class="upload-preview" src="' . e(url((string) $value)) . '#t=0.5" preload="metadata" muted controls></video>';
                    } else {
                        echo '<a class="upload-file" href="' . e(url((string) $value)) . '" target="_blank" rel="noopener"><i class="fa-solid fa-file-pdf"></i> Current file</a>';
                    }
                } else {
                    echo '<div class="upload-preview empty"><i class="fa-regular ' . match ($type) { 'image' => 'fa-image', 'video' => 'fa-file-video', default => 'fa-file' } . '"></i></div>';
                }
                echo '<div class="upload-controls"><input type="file" id="' . $id . '" name="' . e($name) . '" accept="' . $accept . '" data-preview>';
                if ($value) {
                    echo '<label class="check-inline"><input type="checkbox" name="remove_' . e($name) . '" value="1"> Remove current</label>';
                }
                echo '</div></div>';
                break;
            case 'select':
                echo '<select id="' . $id . '" name="' . e($name) . '">';
                foreach ($def['options'] as $k => $text) {
                    echo '<option value="' . e((string) $k) . '"' . ((string) $value === (string) $k ? ' selected' : '') . '>' . e($text) . '</option>';
                }
                echo '</select>';
                break;
            case 'icon':
                echo '<div class="icon-input"><span class="icon-preview"><i class="' . e((string) $value) . '"></i></span>'
                    . '<input type="text" id="' . $id . '" name="' . e($name) . '" value="' . e((string) $value) . '" data-icon-input autocomplete="off"></div>';
                break;
            default:
                $htmlType = in_array($type, ['url', 'email', 'number'], true) ? $type : 'text';
                if ($type === 'url') $htmlType = 'text'; // allow "#" and relative links
                $extra = '';
                if ($type === 'number') {
                    $extra .= isset($def['min']) ? ' min="' . (int) $def['min'] . '"' : '';
                    $extra .= isset($def['max']) ? ' max="' . (int) $def['max'] . '"' : '';
                }
                if ($type === 'url') $extra .= ' placeholder="https://"';
                echo '<input type="' . $htmlType . '" id="' . $id . '" name="' . e($name) . '" value="' . e((string) $value) . '"' . $extra . ($req ? ' required' : '') . '>';
        }
    }
    if (!empty($def['help'])) {
        echo '<small class="help">' . $def['help'] . '</small>'; // help text is trusted config
    }
    echo '</div>';
}

/**
 * Read one submitted field. Upload changes are tracked in $tx so that files are only
 * deleted once the database save succeeds (see finish_uploads / rollback_uploads).
 */
function collect_field(string $name, array $def, $current, array &$errors, array &$tx)
{
    $type = $def['type'];
    $label = $def['label'];

    if ($type === 'checkbox') {
        return (($_POST[$name] ?? '0') === '1') ? 1 : 0;
    }

    if ($type === 'image' || $type === 'file' || $type === 'video') {
        if (!empty($_FILES[$name]['name'])) {
            try {
                $new = handle_upload($name, match ($type) { 'image' => 'image', 'video' => 'video', default => 'pdf' });
                $tx['new'][] = $new;
                if ($current) $tx['old'][] = $current;
                return $new;
            } catch (RuntimeException $ex) {
                $errors[] = $label . ': ' . $ex->getMessage();
                return $current;
            }
        }
        if (!empty($_POST['remove_' . $name])) {
            if ($current) $tx['old'][] = $current;
            return null;
        }
        return $current;
    }

    $v = trim((string) ($_POST[$name] ?? ''));

    if ($type === 'select') {
        return array_key_exists($v, $def['options']) ? $v : (string) array_key_first($def['options']);
    }
    if (!empty($def['required']) && $v === '') {
        $errors[] = "$label is required.";
    }
    if ($type === 'number') {
        $n = (int) $v;
        if (isset($def['min'])) $n = max((int) $def['min'], $n);
        if (isset($def['max'])) $n = min((int) $def['max'], $n);
        return $n;
    }
    if ($type === 'url' && $v !== '' && !preg_match('~^(https?://|mailto:|tel:|#|/)~i', $v)) {
        $errors[] = "$label must start with https:// (or be #).";
    }
    if ($type === 'email' && $v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "$label must be a valid email address.";
    }
    if ($type === 'icon' && $v !== '' && !preg_match('/^[a-z0-9 _-]+$/i', $v)) {
        $errors[] = "$label may only contain letters, numbers, spaces and dashes.";
    }
    $max = $def['max'] ?? ($type === 'textarea' ? 5000 : 255);
    if (mb_strlen($v) > $max) {
        $errors[] = "$label is too long (max $max characters).";
    }
    return $v;
}

function finish_uploads(array $tx): void
{
    foreach ($tx['old'] ?? [] as $p) delete_upload($p);
}

function rollback_uploads(array $tx): void
{
    foreach ($tx['new'] ?? [] as $p) delete_upload($p);
}
