<?php
$body_class = 'page-gallery';
$page_title = 'Add Photos to Album';

require_once __DIR__ . '/admin_auth.php';
requireAdmin();
global $conn;
$con = $conn;

$agid = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
if ($agid <= 0) {
    require __DIR__ . '/header.php';
    echo '<div class="panel"><div class="empty-mini"><i class="fas fa-circle-exclamation"></i>No album specified. <a href="addalbum.php" style="color:var(--brand-1);font-weight:600;">Pick an album</a> first.</div></div></main></div></div></body></html>';
    exit;
}

// Get album info
$stmt = mysqli_prepare($con, "SELECT * FROM tbl_album WHERE albumid = ?");
mysqli_stmt_bind_param($stmt, 'i', $agid);
mysqli_stmt_execute($stmt);
$album = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$album) {
    require __DIR__ . '/header.php';
    echo '<div class="panel"><div class="empty-mini"><i class="fas fa-circle-exclamation"></i>Album not found.</div></div></main></div></div></body></html>';
    exit;
}

$success_count = 0; $failed_count = 0; $error = '';
$flash = '';

// ─────────── Delete single photo ───────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_photo'])) {
    if (!csrfCheck($_POST['csrf'] ?? '')) {
        $flash = ['error', 'Session expired.'];
    } else {
        $gid = intval($_POST['photo_id']);
        // Get filename for disk cleanup
        $stmt = mysqli_prepare($con, "SELECT gimages FROM tbl_gallery WHERE gid=? AND aid=?");
        mysqli_stmt_bind_param($stmt, 'ii', $gid, $agid);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if ($row) {
            $stmt = mysqli_prepare($con, "DELETE FROM tbl_gallery WHERE gid=?");
            mysqli_stmt_bind_param($stmt, 'i', $gid);
            $ok = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            if ($ok) {
                @unlink(__DIR__ . '/gupload/' . $row['gimages']);
                @unlink(__DIR__ . '/gcatch/' . $row['gimages']);
                @adminAuditLog('photo_delete', "Removed photo #$gid from album #$agid");
                $flash = ['success', "Photo deleted."];
            } else {
                $flash = ['error', 'DB error deleting photo.'];
            }
        } else {
            $flash = ['error', 'Photo not found in this album.'];
        }
    }
}

if (isset($_FILES['upload1']) && !empty($_FILES['upload1']['name'][0])) {
    if (!is_dir('gupload')) @mkdir('gupload', 0755);
    if (!is_dir('gcatch')) @mkdir('gcatch', 0755);

    $caption = trim($_POST['caption'] ?? '');
    $rd = rand();
    $gdate = date('Y-m-d H:i:s');
    $status = 'process';

    foreach ($_FILES['upload1']['tmp_name'] as $key => $tmp_name) {
        if (empty($tmp_name) || $_FILES['upload1']['error'][$key] !== UPLOAD_ERR_OK) continue;
        $orig = $_FILES['upload1']['name'][$key];
        $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) { $failed_count++; continue; }

        $safe_name = $key . '_' . $rd . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $orig);
        $dest_full = 'gupload/' . $safe_name;
        $dest_thumb = 'gcatch/' . $safe_name;

        if (move_uploaded_file($tmp_name, $dest_full)) {
            // Try to make a thumb
            if (function_exists('imagecreatefromstring')) {
                $img = @imagecreatefromstring(file_get_contents($dest_full));
                if ($img) {
                    $w = imagesx($img); $h = imagesy($img);
                    $nw = 400; $nh = (int)round(($h / $w) * 400);
                    $thumb = imagecreatetruecolor($nw, $nh);
                    imagecopyresampled($thumb, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
                    imagejpeg($thumb, $dest_thumb, 85);
                    imagedestroy($img); imagedestroy($thumb);
                } else {
                    @copy($dest_full, $dest_thumb);
                }
            } else {
                @copy($dest_full, $dest_thumb);
            }

            $status = 'process';
            $stmt = mysqli_prepare($con,
                "INSERT INTO tbl_gallery (aid, gimages, caption, status) VALUES (?, ?, ?, ?)"
            );
            mysqli_stmt_bind_param($stmt, 'isss', $agid, $safe_name, $caption, $status);
            if (mysqli_stmt_execute($stmt)) $success_count++;
            else { $failed_count++; $error = mysqli_stmt_error($stmt); }
            mysqli_stmt_close($stmt);
        } else {
            $failed_count++;
        }
    }
    @adminAuditLog('photo_upload', "Added $success_count photos to album #$agid");
}

// Fetch existing photos in this album (prepared)
$photos = [];
$stmt = mysqli_prepare($con, "SELECT * FROM tbl_gallery WHERE aid = ? AND status = 'process' ORDER BY gid DESC");
mysqli_stmt_bind_param($stmt, 'i', $agid);
mysqli_stmt_execute($stmt);
$pres = mysqli_stmt_get_result($stmt);
if ($pres) while ($p = mysqli_fetch_assoc($pres)) $photos[] = $p;
mysqli_stmt_close($stmt);

require __DIR__ . '/header.php';
?>

<style>
.breadcrumb { display: flex; align-items: center; gap: 8px; margin-bottom: 18px; font-size: 13px; color: var(--text-soft); }
.breadcrumb a { color: var(--brand-1); font-weight: 600; }
.breadcrumb i { font-size: 10px; opacity: .5; }

.album-header { display: flex; gap: 18px; padding: 22px; background: var(--bg-elev); border: 1px solid var(--border); border-radius: 14px; margin-bottom: 22px; align-items: center; }
.album-header .cover { width: 100px; height: 100px; border-radius: 12px; flex-shrink: 0; overflow: hidden; background: var(--bg); }
.album-header .cover img { width: 100%; height: 100%; object-fit: cover; }
.album-header .info h2 { font-size: 22px; font-weight: 800; margin-bottom: 4px; }
.album-header .info p { color: var(--text-soft); font-size: 13px; margin-bottom: 8px; }
.album-header .info .meta { display: flex; gap: 14px; font-size: 12px; color: var(--muted); }
.album-header .info .meta strong { color: var(--text); font-weight: 600; }

.alert { display: flex; align-items: flex-start; gap: 10px; padding: 12px 14px; border-radius: 10px; font-size: 13px; margin-bottom: 16px; }
.alert.success { background: rgba(16,185,129,.1); color: #065f46; border: 1px solid rgba(16,185,129,.3); }
.alert.error   { background: rgba(239,68,68,.1);  color: #991b1b; border: 1px solid rgba(239,68,68,.3); }
html.dark .alert.success { color: #6ee7b7; }
html.dark .alert.error { color: #fca5a5; }

.field { margin-bottom: 14px; }
.field label { display: block; font-size: 12px; font-weight: 600; color: var(--text-soft); margin-bottom: 7px; text-transform: uppercase; letter-spacing: .05em; }
.input { width: 100%; padding: 11px 14px; border: 1px solid var(--border); border-radius: 10px; background: var(--bg); color: var(--text); font: inherit; font-size: 14px; }

.drop-zone { position: relative; border: 2px dashed var(--border); border-radius: 14px; padding: 36px 20px; text-align: center; cursor: pointer; background: var(--bg); transition: all .2s; }
.drop-zone:hover, .drop-zone.drag { border-color: #ec4899; background: rgba(236,72,153,.04); }
.drop-zone input[type=file] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
.drop-zone .ic { width: 56px; height: 56px; border-radius: 14px; background: linear-gradient(135deg, #ec4899, #f43f5e); color: white; display: grid; place-items: center; margin: 0 auto 12px; font-size: 22px; }
.drop-zone .t { font-weight: 700; font-size: 14px; }
.drop-zone .s { font-size: 12px; color: var(--text-soft); margin-top: 4px; }
.drop-zone .files-list { margin-top: 12px; font-size: 12px; color: var(--brand-1); font-weight: 600; }

.photo-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 12px; }
.photo-thumb { position: relative; aspect-ratio: 1; border-radius: 10px; overflow: hidden; background: var(--bg); border: 1px solid var(--border); }
.photo-thumb img { width: 100%; height: 100%; object-fit: cover; }
.photo-thumb .overlay { position: absolute; inset: 0; background: linear-gradient(to top, rgba(0,0,0,.7), transparent 50%); opacity: 0; transition: opacity .15s; }
.photo-thumb:hover .overlay { opacity: 1; }
.photo-thumb .id { position: absolute; bottom: 6px; left: 8px; color: white; font-size: 10px; font-weight: 700; opacity: 0; transition: opacity .15s; z-index: 2; }
.photo-thumb:hover .id { opacity: 1; }
.photo-del-form { position: absolute; top: 6px; right: 6px; opacity: 0; transition: opacity .15s; z-index: 3; }
.photo-thumb:hover .photo-del-form { opacity: 1; }
.photo-del-btn { background: rgba(239,68,68,.95); color: white; border: 0; padding: 6px 9px; border-radius: 7px; cursor: pointer; font-size: 12px; transition: transform .15s; }
.photo-del-btn:hover { transform: scale(1.1); background: #ef4444; }
</style>

<div class="breadcrumb">
  <a href="home.php"><i class="fas fa-house"></i></a>
  <i class="fas fa-chevron-right"></i>
  <a href="addalbum.php">Photo albums</a>
  <i class="fas fa-chevron-right"></i>
  <span><?php echo htmlspecialchars($album['name']); ?></span>
</div>

<div class="album-header">
  <div class="cover">
    <?php if (!empty($album['image'])): ?>
      <img src="acatch/<?php echo htmlspecialchars($album['image']); ?>" alt="">
    <?php else: ?>
      <div style="width:100%;height:100%;display:grid;place-items:center;color:var(--muted);"><i class="fas fa-image" style="font-size:32px;"></i></div>
    <?php endif; ?>
  </div>
  <div class="info">
    <h2><?php echo htmlspecialchars($album['name']); ?></h2>
    <p><?php echo htmlspecialchars($album['adesc'] ?? ''); ?></p>
    <div class="meta">
      <span><i class="fas fa-images"></i> <strong><?php echo count($photos); ?></strong> photos</span>
      <span><i class="fas fa-calendar"></i> <?php echo htmlspecialchars(date('M j, Y', strtotime($album['date']))); ?></span>
      <a href="../Photo/gallery.php?id=<?php echo $agid; ?>" target="_blank" style="color:var(--brand-1);font-weight:600;"><i class="fas fa-arrow-up-right-from-square"></i> View live</a>
    </div>
  </div>
</div>

<?php if ($flash): ?>
  <div class="alert <?php echo $flash[0]; ?>"><i class="fas fa-<?php echo $flash[0]==='success' ? 'circle-check' : 'circle-exclamation'; ?>"></i><div><?php echo htmlspecialchars($flash[1]); ?></div></div>
<?php endif; ?>

<?php if ($success_count > 0): ?>
  <div class="alert success"><i class="fas fa-circle-check"></i><div><strong><?php echo $success_count; ?></strong> photo<?php echo $success_count == 1 ? '' : 's'; ?> uploaded successfully.</div></div>
<?php endif; ?>
<?php if ($failed_count > 0): ?>
  <div class="alert error"><i class="fas fa-circle-exclamation"></i><div><?php echo $failed_count; ?> file<?php echo $failed_count == 1 ? '' : 's'; ?> failed to upload. <?php echo htmlspecialchars($error); ?></div></div>
<?php endif; ?>

<div class="panel" style="margin-bottom:22px;">
  <h3 style="font-size:16px;font-weight:700;margin-bottom:6px;"><i class="fas fa-cloud-arrow-up" style="color:#ec4899;margin-right:8px;"></i> Upload photos</h3>
  <p style="color:var(--text-soft);font-size:13px;margin-bottom:18px;">Add one or many photos to this album. Supports JPG, PNG, GIF, WebP.</p>

  <form method="post" enctype="multipart/form-data">
    <div class="field">
      <label>Caption <span style="color:var(--muted);font-weight:500;text-transform:none;letter-spacing:0;">(optional — applies to all uploaded photos in this batch)</span></label>
      <input type="text" name="caption" class="input" maxlength="200" placeholder="e.g. Group photo, day 1">
    </div>

    <div class="field">
      <label>Photos</label>
      <div class="drop-zone" id="dropZone">
        <input type="file" name="upload1[]" id="fileInput" accept="image/*" multiple required>
        <div class="ic"><i class="fas fa-images"></i></div>
        <div class="t" id="dropTitle">Drop photos here or click to select multiple</div>
        <div class="s">JPG · PNG · GIF · WebP — you can pick many at once</div>
        <div class="files-list" id="filesList"></div>
      </div>
    </div>

    <button type="submit" class="btn btn-primary"><i class="fas fa-cloud-arrow-up"></i> Upload</button>
  </form>
</div>

<div class="panel">
  <div class="panel-head">
    <h3><i class="fas fa-images"></i> Photos in this album <span style="font-size:13px;color:var(--muted);font-weight:500;">(<?php echo count($photos); ?>)</span></h3>
  </div>
  <?php if (count($photos) === 0): ?>
    <div class="empty-mini"><i class="fas fa-image"></i>No photos yet — upload your first batch above.</div>
  <?php else: ?>
    <div class="photo-grid">
      <?php foreach ($photos as $p): ?>
        <div class="photo-thumb">
          <img src="gcatch/<?php echo htmlspecialchars($p['gimages']); ?>" alt="" loading="lazy" onerror="this.src='gupload/<?php echo htmlspecialchars($p['gimages']); ?>'">
          <div class="overlay"></div>
          <span class="id">#<?php echo (int)$p['gid']; ?></span>
          <form method="post" class="photo-del-form" onsubmit="return confirm('Delete this photo? This cannot be undone.');">
            <input type="hidden" name="csrf" value="<?php echo csrfToken(); ?>">
            <input type="hidden" name="photo_id" value="<?php echo (int)$p['gid']; ?>">
            <button type="submit" name="delete_photo" class="photo-del-btn" title="Delete photo"><i class="fas fa-trash"></i></button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<script>
const dz = document.getElementById('dropZone'), fi = document.getElementById('fileInput'), fl = document.getElementById('filesList');
['dragenter','dragover'].forEach(e => dz.addEventListener(e, ev => { ev.preventDefault(); dz.classList.add('drag'); }));
['dragleave','drop'].forEach(e => dz.addEventListener(e, ev => { ev.preventDefault(); dz.classList.remove('drag'); }));
dz.addEventListener('drop', ev => { if (ev.dataTransfer.files.length) { fi.files = ev.dataTransfer.files; fi.dispatchEvent(new Event('change')); } });
fi.addEventListener('change', () => {
  if (fi.files.length) {
    fl.textContent = fi.files.length + ' file' + (fi.files.length === 1 ? '' : 's') + ' selected';
  }
});
</script>

    </main>
  </div>
</div>
</body>
</html>