<?php
define('URL_PREFIX', '../');
require dirname(__DIR__) . '/includes/bootstrap.php';
require APP_ROOT . '/includes/admin_layout.php';
require_login();

$entities = require APP_ROOT . '/includes/entities.php';
$type = (string) ($_GET['type'] ?? '');
if (!isset($entities[$type])) {
    flash('error', 'Unknown section.');
    redirect('index.php');
}
$ent = $entities[$type];
$fields = $ent['fields'];
$table = $type; // whitelisted by $entities keys
$self = 'manage.php?type=' . urlencode($type);

function find_row(string $table, int $id): ?array
{
    $st = db()->prepare("SELECT * FROM `$table` WHERE id = ?");
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

$action = (string) ($_GET['action'] ?? 'list');
$errors = [];

/* -------- POST actions -------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    save_setting('content_changed_at', date('c')); // for the "unpublished changes" reminder
    $op = (string) ($_POST['op'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    /* ---- Album photos (gallery only) ---- */
    if ($type === 'gallery' && in_array($op, ['album_upload', 'album_delete', 'album_clear'], true)) {
        $wantsJson = str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
        $back = $self . '&action=edit&id=' . $id . '#album';
        if (!$id || !find_row($table, $id)) redirect($self);

        if ($op === 'album_upload') {
            $added = 0;
            $errs = [];
            $files = $_FILES['photos'] ?? null;
            if ($files && is_array($files['name'])) {
                $st = db()->prepare('SELECT COALESCE(MAX(sort_order), 0) FROM gallery_photos WHERE gallery_id = ?');
                $st->execute([$id]);
                $next = (int) $st->fetchColumn();
                $ins = db()->prepare('INSERT INTO gallery_photos (gallery_id, image, sort_order) VALUES (?, ?, ?)');
                foreach ($files['name'] as $k => $fileName) {
                    if ($files['error'][$k] === UPLOAD_ERR_NO_FILE) continue;
                    $_FILES['__album_one'] = ['name' => $fileName, 'type' => $files['type'][$k], 'tmp_name' => $files['tmp_name'][$k], 'error' => $files['error'][$k], 'size' => $files['size'][$k]];
                    try {
                        $path = handle_upload('__album_one', 'image');
                        $ins->execute([$id, $path, ++$next]);
                        $added++;
                    } catch (RuntimeException $ex) {
                        $errs[] = $fileName . ': ' . $ex->getMessage();
                    }
                }
            } else {
                $errs[] = 'No photos were received. They may be larger than the server allows.';
            }
            if ($wantsJson) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => $added > 0 && !$errs, 'added' => $added, 'errors' => $errs]);
                exit;
            }
            if ($added) flash('success', "$added photo" . ($added === 1 ? '' : 's') . ' added to the album.');
            foreach ($errs as $er) flash('error', $er);
            redirect($back);
        }

        if ($op === 'album_delete') {
            $st = db()->prepare('SELECT image FROM gallery_photos WHERE id = ? AND gallery_id = ?');
            $st->execute([(int) ($_POST['photo_id'] ?? 0), $id]);
            if ($img = $st->fetchColumn()) {
                db()->prepare('DELETE FROM gallery_photos WHERE id = ? AND gallery_id = ?')->execute([(int) $_POST['photo_id'], $id]);
                delete_upload($img);
                flash('success', 'Photo removed from the album.');
            }
            redirect($back);
        }

        if ($op === 'album_clear') {
            foreach (album_photos($id) as $p) delete_upload($p['image']);
            db()->prepare('DELETE FROM gallery_photos WHERE gallery_id = ?')->execute([$id]);
            flash('success', 'Album cleared.');
            redirect($back);
        }
    }

    if ($op === 'delete' && $id) {
        $row = find_row($table, $id);
        if ($row && $type === 'gallery') {
            foreach (album_photos($id) as $p) delete_upload($p['image']); // rows go via ON DELETE CASCADE
        }
        if ($row) {
            db()->prepare("DELETE FROM `$table` WHERE id = ?")->execute([$id]);
            foreach ($fields as $name => $def) {
                if (in_array($def['type'], ['image', 'file', 'video'], true)) delete_upload($row[$name] ?? null);
            }
            flash('success', $ent['singular'] . ' deleted.');
        }
        redirect($self);
    }

    if ($op === 'toggle' && $id) {
        db()->prepare("UPDATE `$table` SET is_visible = 1 - is_visible WHERE id = ?")->execute([$id]);
        redirect($self);
    }

    if ($op === 'move' && $id) {
        // Swap sort position with neighbour
        $rows = fetch_rows($table, false);
        $ids = array_column($rows, 'id');
        $i = array_search($id, array_map('intval', $ids), true);
        $j = ($_POST['dir'] ?? '') === 'up' ? $i - 1 : $i + 1;
        if ($i !== false && isset($ids[$j])) {
            [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
            $st = db()->prepare("UPDATE `$table` SET sort_order = ? WHERE id = ?");
            foreach ($ids as $pos => $rid) $st->execute([$pos + 1, $rid]);
        }
        redirect($self);
    }

    if ($op === 'save') {
        $existing = $id ? find_row($table, $id) : null;
        if ($id && !$existing) redirect($self);
        $tx = ['new' => [], 'old' => []];
        $data = [];
        foreach ($fields as $name => $def) {
            $data[$name] = collect_field($name, $def, $existing[$name] ?? null, $errors, $tx);
        }
        $data['is_visible'] = (($_POST['is_visible'] ?? '0') === '1') ? 1 : 0;
        if ($type === 'gallery') {
            $data['embed_status'] = null;
            $link = (string) ($data['video_url'] ?? '');
            if ($link !== '' && is_facebook_url($link)) {
                $fb = facebook_lookup($link);
                $data['video_url'] = $fb['url'];
                $isVideo = is_facebook_video($fb['url']);
                if ($isVideo) {
                    $ok = facebook_embed_ok($fb['url']);
                    $data['embed_status'] = $ok === null ? null : ($ok ? 'ok' : 'blocked');
                }
                // Cover photo: for photo/album posts, and for videos Facebook won't let us play
                $wantCover = !$isVideo || $data['embed_status'] === 'blocked';
                if (empty($data['image']) && $wantCover && $fb['image'] && empty($_POST['remove_image'])) {
                    if ($img = download_image($fb['image'])) {
                        $data['image'] = $img;
                        $tx['new'][] = $img;
                        flash('success', 'Cover photo grabbed from Facebook automatically.');
                    }
                }
                if ($data['embed_status'] === 'blocked' && empty($data['video_file'])) {
                    flash('warn', 'Facebook doesn\'t allow this video to play on other websites (it may contain music or content owned by someone else, or it isn\'t Public). '
                        . 'Visitors will see your cover with a "Watch on Facebook" button. To play it right on your site, upload the MP4 in "Or upload the video file".');
                }
            }
        }

        if (!$errors) {
            try {
                $cols = array_keys($data);
                if ($existing) {
                    $set = implode(', ', array_map(fn($c) => "`$c` = ?", $cols));
                    db()->prepare("UPDATE `$table` SET $set WHERE id = ?")->execute([...array_values($data), $id]);
                } else {
                    $data['sort_order'] = (int) db()->query("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM `$table`")->fetchColumn();
                    $cols = array_keys($data);
                    $ph = implode(', ', array_fill(0, count($cols), '?'));
                    $colSql = implode(', ', array_map(fn($c) => "`$c`", $cols));
                    db()->prepare("INSERT INTO `$table` ($colSql) VALUES ($ph)")->execute(array_values($data));
                }
                finish_uploads($tx);
                flash('success', $ent['singular'] . ($existing ? ' updated.' : ' added.'));
                redirect($self);
            } catch (Throwable $ex) {
                rollback_uploads($tx);
                $errors[] = 'Could not save: ' . (APP_DEBUG ? $ex->getMessage() : 'database error.');
            }
        } else {
            rollback_uploads($tx);
        }
        // fall through to re-render form with errors
        $action = $existing ? 'edit' : 'new';
        $formRow = array_merge($existing ?? [], $data);
        if ($existing) {
            foreach ($fields as $n => $d) {
                if (in_array($d['type'], ['image', 'file', 'video'], true)) $formRow[$n] = $existing[$n];
            }
        }
    }
}

/* -------- Render -------- */
if ($action === 'new' || $action === 'edit') {
    if (!isset($formRow)) {
        if ($action === 'edit') {
            $formRow = find_row($table, (int) ($_GET['id'] ?? 0));
            if (!$formRow) {
                flash('error', 'Item not found.');
                redirect($self);
            }
        } else {
            $formRow = ['is_visible' => 1];
            foreach ($fields as $n => $d) $formRow[$n] = $d['default'] ?? '';
        }
    }
    $isEdit = !empty($formRow['id']);
    admin_header(($isEdit ? 'Edit ' : 'Add ') . $ent['singular'], 'manage-' . $type);
    ?>
    <a class="back-link" href="<?= e($self) ?>"><i class="fa-solid fa-arrow-left"></i> Back to <?= e($ent['label']) ?></a>
    <?php if ($errors): ?>
      <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i><span><?= implode('<br>', array_map('e', $errors)) ?></span></div>
    <?php endif; ?>
    <form class="panel form" method="post" action="<?= e($self) ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="op" value="save">
      <input type="hidden" name="id" value="<?= (int) ($formRow['id'] ?? 0) ?>">
      <div class="form-grid">
        <?php foreach ($fields as $name => $def) render_field($name, $def, $formRow[$name] ?? ''); ?>
        <?php render_field('is_visible', ['type' => 'checkbox', 'label' => 'Visible on the website'], (string) ($formRow['is_visible'] ?? 1)); ?>
      </div>
      <div class="form-actions">
        <button class="btn btn-gold" type="submit"><i class="fa-solid fa-floppy-disk"></i> <?= $isEdit ? 'Save changes' : 'Add ' . e(strtolower($ent['singular'])) ?></button>
        <a class="btn btn-ghost" href="<?= e($self) ?>">Cancel</a>
      </div>
    </form>

    <?php if ($type === 'gallery'): ?>
    <section class="panel album" id="album">
      <?php if (!$isEdit): ?>
        <div class="panel-head"><h3><i class="fa-solid fa-images"></i> Album photos</h3></div>
        <p class="muted">Save this item first, then you can add all the photos from the shoot here.</p>
      <?php else: $album = album_photos((int) $formRow['id']); ?>
        <div class="panel-head">
          <h3><i class="fa-solid fa-images"></i> Album photos <span class="pill"><?= count($album) ?></span></h3>
          <?php if ($album): ?>
          <form method="post" action="<?= e($self) ?>" data-confirm="Remove all <?= count($album) ?> photos from this album?"><?= csrf_field() ?>
            <input type="hidden" name="op" value="album_clear"><input type="hidden" name="id" value="<?= (int) $formRow['id'] ?>">
            <button class="btn btn-sm btn-danger"><i class="fa-solid fa-trash"></i> Remove all</button></form>
          <?php endif; ?>
        </div>
        <p class="help">Add every photo from this shoot. Visitors can click through them on your site, with a "View on Facebook" button if you added a link.
          Big camera photos are resized automatically before upload. <b>Tip:</b> on your Facebook page, open the album → <i>⋯</i> → <i>Download album</i>, unzip it, and select all the photos here.</p>
        <form class="album-upload" method="post" action="<?= e($self) ?>" enctype="multipart/form-data" data-album-upload>
          <?= csrf_field() ?>
          <input type="hidden" name="op" value="album_upload"><input type="hidden" name="id" value="<?= (int) $formRow['id'] ?>">
          <label class="dropzone">
            <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple required>
            <i class="fa-solid fa-cloud-arrow-up"></i>
            <b data-dz-label>Choose photos or drag them here</b>
            <small>JPG, PNG, WEBP or GIF · select as many as you like</small>
          </label>
          <div class="album-progress" hidden><div class="progress"><span></span></div><p class="muted"></p></div>
          <button class="btn btn-gold" type="submit"><i class="fa-solid fa-upload"></i> Upload photos</button>
        </form>
        <?php if ($album): ?>
          <div class="album-grid">
            <?php foreach ($album as $n => $p): ?>
              <figure>
                <img src="<?= e(url($p['image'])) ?>" alt="" loading="lazy">
                <figcaption><?= $n + 1 ?></figcaption>
                <form method="post" action="<?= e($self) ?>" data-confirm="Remove this photo from the album?"><?= csrf_field() ?>
                  <input type="hidden" name="op" value="album_delete"><input type="hidden" name="id" value="<?= (int) $formRow['id'] ?>"><input type="hidden" name="photo_id" value="<?= (int) $p['id'] ?>">
                  <button class="icon-btn danger" aria-label="Remove photo <?= $n + 1 ?>"><i class="fa-solid fa-xmark"></i></button></form>
              </figure>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </section>
    <?php endif; ?>
    <?php
    admin_footer();
    exit;
}

$rows = fetch_rows($table, false);
// First plain-text column is shown in bold (the item's "name")
$boldCol = null;
foreach ($ent['columns'] as $c => $_) {
    if (in_array($fields[$c]['type'] ?? 'text', ['text', 'textarea'], true)) { $boldCol = $c; break; }
}
admin_header($ent['label'], 'manage-' . $type);
?>
<div class="toolbar">
  <p class="muted"><?= count($rows) ?> item<?= count($rows) === 1 ? '' : 's' ?>. Use the arrows to change the order they appear on your site.</p>
  <a class="btn btn-gold" href="<?= e($self) ?>&action=new"><i class="fa-solid fa-plus"></i> Add <?= e(strtolower($ent['singular'])) ?></a>
</div>

<?php if (!$rows): ?>
  <div class="panel empty"><i class="<?= e($ent['icon']) ?>"></i><p>Nothing here yet.</p><a class="btn btn-gold" href="<?= e($self) ?>&action=new">Add your first <?= e(strtolower($ent['singular'])) ?></a></div>
<?php else: ?>
<div class="panel table-wrap">
  <table class="table">
    <thead><tr>
      <th class="w-order">Order</th>
      <?php foreach ($ent['columns'] as $label): ?><th><?= e($label) ?></th><?php endforeach; ?>
      <th>Visible</th><th class="t-right">Actions</th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $i => $r): ?>
      <tr class="<?= $r['is_visible'] ? '' : 'is-hidden' ?>">
        <td class="w-order">
          <form method="post" class="order-btns"><?= csrf_field() ?><input type="hidden" name="op" value="move"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <button name="dir" value="up" class="icon-btn sm" <?= $i === 0 ? 'disabled' : '' ?> aria-label="Move up"><i class="fa-solid fa-chevron-up"></i></button>
            <button name="dir" value="down" class="icon-btn sm" <?= $i === count($rows) - 1 ? 'disabled' : '' ?> aria-label="Move down"><i class="fa-solid fa-chevron-down"></i></button>
          </form>
        </td>
        <?php foreach ($ent['columns'] as $col => $label):
            $def = $fields[$col] ?? ['type' => 'text'];
            $val = (string) ($r[$col] ?? ''); ?>
          <td data-label="<?= e($label) ?>">
            <?php if ($def['type'] === 'image'): ?>
              <?php $autoThumb = !$val && !empty($r['video_url']) ? (video_info($r['video_url'])['thumb'] ?? null) : null; ?>
              <?php $phIcon = !empty($r['video_url']) && is_facebook_url($r['video_url']) ? 'fa-brands fa-facebook' : (($r['icon'] ?? '') ?: 'fa-regular fa-image'); ?>
              <?php if ($val || $autoThumb): ?><img class="thumb" src="<?= e($val ? url($val) : $autoThumb) ?>" alt=""><?php else: ?><span class="thumb empty"><i class="<?= e($phIcon) ?>"></i></span><?php endif; ?>
            <?php elseif ($def['type'] === 'icon'): ?>
              <?php if ($col === 'icon_class' && !empty($r['icon_image'])): ?><img class="thumb sm" src="<?= e(url($r['icon_image'])) ?>" alt=""><?php else: ?><span class="icon-cell"><i class="<?= e($val) ?>"></i></span><?php endif; ?>
            <?php elseif ($def['type'] === 'checkbox'): ?>
              <?= $val === '1' ? '<span class="pill pill-gold">Yes</span>' : '<span class="pill">No</span>' ?>
            <?php elseif ($def['type'] === 'select'): ?>
              <span class="pill"><?= e($def['options'][$val] ?? $val) ?></span>
            <?php elseif ($col === 'rating'): ?>
              <span class="stars"><?= str_repeat('★', (int) $val) ?></span>
            <?php elseif ($def['type'] === 'url'): ?>
              <a class="link" href="<?= e($val) ?>" target="_blank" rel="noopener"><?= e(str_limit($val, 40)) ?></a>
            <?php else: ?>
              <?= $col === $boldCol ? '<b>' . e($val) . '</b>' : e(str_limit($val, 70)) ?>
            <?php endif; ?>
          </td>
        <?php endforeach; ?>
        <td data-label="Visible">
          <form method="post"><?= csrf_field() ?><input type="hidden" name="op" value="toggle"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <button class="toggle <?= $r['is_visible'] ? 'on' : '' ?>" aria-label="Toggle visibility" title="<?= $r['is_visible'] ? 'Visible - click to hide' : 'Hidden - click to show' ?>"><span></span></button>
          </form>
        </td>
        <td class="t-right actions">
          <a class="icon-btn" href="<?= e($self) ?>&action=edit&id=<?= (int) $r['id'] ?>" aria-label="Edit"><i class="fa-solid fa-pen"></i></a>
          <form method="post" data-confirm="Delete this <?= e(strtolower($ent['singular'])) ?>? This cannot be undone."><?= csrf_field() ?><input type="hidden" name="op" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <button class="icon-btn danger" aria-label="Delete"><i class="fa-solid fa-trash"></i></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif;
admin_footer();
