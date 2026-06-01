<?php
$body_class = 'page-gallery';
$page_title = 'Photo Albums';

require_once __DIR__ . '/admin_auth.php';
requireAdmin();

global $conn;
$con = $conn; // alias for legacy code below

$album_success = false;
$album_error   = '';

if (isset($_POST['submit_album'])) {
    $aname  = trim($_POST['aname']);
    $adesc  = trim($_POST['adesc']);
    $adate  = date('Y-m-d H:i:s');
    $status = 'process';
    $rd     = rand();

    if (empty($aname)) {
        $album_error = "Album name cannot be empty.";
    } elseif (empty($_FILES['upload']['name'])) {
        $album_error = "Please select a cover image.";
    } else {
        $ext = strtolower(pathinfo($_FILES['upload']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg'])) {
            $album_error = "Only JPG/JPEG image format is supported.";
        } else {
            if (!is_dir('acatch')) @mkdir('acatch', 0755);
            if (!is_dir('aupload')) @mkdir('aupload', 0755);
            $uploadedfile = $_FILES['upload']['tmp_name'];
            $src = @imagecreatefromjpeg($uploadedfile);
            if ($src) {
                list($width, $height) = getimagesize($uploadedfile);
                $newwidth  = 290;
                $newheight = (int)round(($height / $width) * 300);
                $tmp = imagecreatetruecolor($newwidth, $newheight);
                imagecopyresampled($tmp, $src, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);
                $filename = "acatch/" . $rd . $_FILES['upload']['name'];
                imagejpeg($tmp, $filename, 100);
                imagedestroy($src);
                imagedestroy($tmp);
            }
            $photo = $rd . $_FILES['upload']['name'];
            move_uploaded_file($_FILES["upload"]["tmp_name"], "aupload/" . $rd . $_FILES["upload"]["name"]);

            $aname_safe = $aname;
            $adesc_safe = $adesc;
            $photo_safe = $photo;
            $stmt = mysqli_prepare($con,
                "INSERT INTO tbl_album (name, adesc, image, date, status) VALUES (?, ?, ?, ?, ?)"
            );
            mysqli_stmt_bind_param($stmt, 'sssss', $aname_safe, $adesc_safe, $photo_safe, $adate, $status);
            if (mysqli_stmt_execute($stmt)) {
                @adminAuditLog('album_create', "Created album: $aname");
                $new_aid = mysqli_insert_id($con);
                require_once __DIR__ . '/notify.php';
                notifyAllUsers(
                    'album_new',
                    'New photo album: ' . $aname,
                    !empty($adesc) ? mb_substr($adesc, 0, 200) : '',
                    '../Photo/gallery.php?id=' . $new_aid
                );
                $album_success = true;
            } else {
                $album_error = "Database error: " . mysqli_stmt_error($stmt);
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Fetch albums with photo counts (no user input — safe; but make stmt for consistency)
$albums = [];
$rs = mysqli_query($con, "
    SELECT a.albumid, a.name, a.adesc, a.image, a.date, a.status,
           (SELECT COUNT(*) FROM tbl_gallery g WHERE g.aid=a.albumid AND g.status='process') AS pc
    FROM tbl_album a WHERE a.status='process' ORDER BY albumid DESC
");
if ($rs) while ($r = mysqli_fetch_assoc($rs)) $albums[] = $r;

require __DIR__ . '/header.php';
?>

<style>
.two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 22px; margin-bottom: 22px; }
@media (max-width: 980px) { .two-col { grid-template-columns: 1fr; } }
.field { margin-bottom: 16px; }
.field label { display: block; font-size: 12px; font-weight: 600; color: var(--text-soft); margin-bottom: 7px; text-transform: uppercase; letter-spacing: .05em; }
.input, .textarea, select.input { width: 100%; padding: 11px 14px; border: 1px solid var(--border); border-radius: 10px; background: var(--bg); color: var(--text); font: inherit; font-size: 14px; transition: all .15s; }
.input:focus, .textarea:focus, select.input:focus { outline: 0; border-color: var(--brand-1); box-shadow: 0 0 0 3px rgba(99,102,241,.15); }
.textarea { resize: vertical; min-height: 90px; }

.drop-zone { position: relative; border: 2px dashed var(--border); border-radius: 14px; padding: 28px 20px; text-align: center; cursor: pointer; transition: all .2s; background: var(--bg); }
.drop-zone:hover, .drop-zone.drag { border-color: var(--brand-1); background: rgba(99,102,241,.04); }
.drop-zone input[type=file] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
.drop-zone .ic { width: 48px; height: 48px; border-radius: 12px; background: linear-gradient(135deg, #ec4899, #f43f5e); color: white; display: grid; place-items: center; margin: 0 auto 10px; font-size: 18px; box-shadow: 0 10px 24px rgba(236,72,153,.3); }
.drop-zone .t { font-weight: 700; font-size: 14px; }
.drop-zone .s { font-size: 12px; color: var(--text-soft); margin-top: 4px; }
.drop-zone.has-file { background: rgba(16,185,129,.06); border-color: #10b981; border-style: solid; }

.alert { display: flex; align-items: flex-start; gap: 10px; padding: 12px 14px; border-radius: 10px; font-size: 13px; margin-bottom: 16px; }
.alert.success { background: rgba(16,185,129,.1); color: #065f46; border: 1px solid rgba(16,185,129,.3); }
.alert.error   { background: rgba(239,68,68,.1);  color: #991b1b; border: 1px solid rgba(239,68,68,.3); }
html.dark .alert.success { color: #6ee7b7; }
html.dark .alert.error { color: #fca5a5; }

.album-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; }
.album-card { background: var(--bg-elev); border: 1px solid var(--border); border-radius: 14px; overflow: hidden; transition: all .2s; }
.album-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); border-color: rgba(236,72,153,.3); }
.album-card .cover { aspect-ratio: 4/3; background: var(--bg); position: relative; }
.album-card .cover img { width: 100%; height: 100%; object-fit: cover; }
.album-card .cover .pc-badge { position: absolute; top: 8px; right: 8px; padding: 3px 9px; border-radius: 999px; background: rgba(0,0,0,.7); color: white; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; }
.album-card .body { padding: 12px 14px; }
.album-card .name { font-weight: 700; font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.album-card .desc { font-size: 11px; color: var(--text-soft); margin-top: 3px; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; min-height: 28px; }
.album-card .actions { display: flex; gap: 6px; padding: 0 12px 12px; }
.album-card .actions a { flex: 1; text-align: center; padding: 7px 10px; border-radius: 8px; font-size: 12px; font-weight: 600; transition: all .15s; }
.album-card .actions .primary { background: var(--grad-brand); color: white; }
.album-card .actions .ghost { background: var(--bg); color: var(--text-soft); border: 1px solid var(--border); }
.album-card .actions .ghost:hover { color: var(--brand-1); border-color: var(--brand-1); }
</style>

<div class="two-col">
  <!-- Create new album -->
  <div class="panel">
    <h3 style="font-size:16px;font-weight:700;margin-bottom:6px;"><i class="fas fa-plus-circle" style="color:#ec4899;margin-right:8px;"></i> Create a new photo event</h3>
    <p style="color:var(--text-soft);font-size:13px;margin-bottom:18px;">Set up a new album so admins can upload photos into it.</p>

    <?php if ($album_success): ?>
      <div class="alert success"><i class="fas fa-circle-check"></i><div>Album created successfully. You can now add photos to it.</div></div>
    <?php endif; ?>
    <?php if ($album_error): ?>
      <div class="alert error"><i class="fas fa-circle-exclamation"></i><div><?php echo htmlspecialchars($album_error); ?></div></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
      <div class="field">
        <label for="aname">Event name</label>
        <input type="text" id="aname" name="aname" class="input" required maxlength="200" placeholder="e.g. Annual Day 2025">
      </div>
      <div class="field">
        <label for="adesc">Description</label>
        <textarea id="adesc" name="adesc" class="textarea" rows="3" maxlength="500" placeholder="A short summary of the event…"></textarea>
      </div>
      <div class="field">
        <label>Cover image <span style="color:var(--muted);font-weight:500;text-transform:none;letter-spacing:0;">— JPG only</span></label>
        <div class="drop-zone" id="dropZone">
          <input type="file" name="upload" id="fileInput" accept="image/jpeg" required>
          <div class="ic"><i class="fas fa-image"></i></div>
          <div class="t" id="dropTitle">Choose a cover image</div>
          <div class="s" id="dropSub">JPG / JPEG · automatically resized to 290px wide</div>
        </div>
      </div>
      <button type="submit" name="submit_album" class="btn btn-primary"><i class="fas fa-plus"></i> Create Album</button>
    </form>
  </div>

  <!-- Add photos to existing album -->
  <div class="panel">
    <h3 style="font-size:16px;font-weight:700;margin-bottom:6px;"><i class="fas fa-arrow-up-from-bracket" style="color:#ec4899;margin-right:8px;"></i> Add photos to existing event</h3>
    <p style="color:var(--text-soft);font-size:13px;margin-bottom:18px;">Pick an existing album and upload more pictures.</p>

    <?php if (count($albums) > 0): ?>
      <form action="glink.php" method="post">
        <div class="field">
          <label for="gname">Choose album</label>
          <select id="gname" name="gname" class="input">
            <?php foreach ($albums as $alb): ?>
              <option value="<?php echo (int)$alb['albumid']; ?>">
                <?php echo htmlspecialchars($alb['name']); ?> · <?php echo (int)$alb['pc']; ?> photo<?php echo $alb['pc'] == 1 ? '' : 's'; ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" name="submit" class="btn btn-primary"><i class="fas fa-arrow-right"></i> Continue</button>
      </form>
    <?php else: ?>
      <div class="alert error"><i class="fas fa-circle-info"></i><div>No albums yet. Create one using the form on the left.</div></div>
    <?php endif; ?>
  </div>
</div>

<!-- All albums -->
<div class="panel">
  <div class="panel-head">
    <h3><i class="fas fa-images"></i> All photo events <span style="font-size:13px;color:var(--muted);font-weight:500;">(<?php echo count($albums); ?>)</span></h3>
  </div>
  <?php if (count($albums) > 0): ?>
    <div class="album-grid">
      <?php foreach ($albums as $a):
        $cover = !empty($a['image']) ? 'acatch/' . rawurlencode($a['image']) : '';
      ?>
        <div class="album-card">
          <div class="cover">
            <?php if ($cover): ?>
              <img src="<?php echo htmlspecialchars($cover); ?>" alt="" onerror="this.style.display='none'">
            <?php endif; ?>
            <span class="pc-badge"><i class="fas fa-images"></i> <?php echo (int)$a['pc']; ?></span>
          </div>
          <div class="body">
            <div class="name"><?php echo htmlspecialchars($a['name']); ?></div>
            <div class="desc"><?php echo htmlspecialchars($a['adesc'] ?? '—'); ?></div>
          </div>
          <div class="actions">
            <a href="addfiles.php?id=<?php echo (int)$a['albumid']; ?>" class="primary"><i class="fas fa-plus"></i> Photos</a>
            <a href="../Photo/gallery.php?id=<?php echo (int)$a['albumid']; ?>" target="_blank" class="ghost"><i class="fas fa-eye"></i> View</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="empty-mini"><i class="fas fa-image"></i>No albums yet — create your first one above.</div>
  <?php endif; ?>
</div>

<script>
const dz = document.getElementById('dropZone'), fi = document.getElementById('fileInput');
['dragenter','dragover'].forEach(e => dz.addEventListener(e, ev => { ev.preventDefault(); dz.classList.add('drag'); }));
['dragleave','drop'].forEach(e => dz.addEventListener(e, ev => { ev.preventDefault(); dz.classList.remove('drag'); }));
dz.addEventListener('drop', ev => { if (ev.dataTransfer.files.length) { fi.files = ev.dataTransfer.files; fi.dispatchEvent(new Event('change')); } });
fi.addEventListener('change', () => {
  if (fi.files.length) {
    document.getElementById('dropTitle').textContent = fi.files[0].name;
    document.getElementById('dropSub').textContent = (fi.files[0].size / 1024).toFixed(1) + ' KB';
    dz.classList.add('has-file');
  }
});
</script>

    </main>
  </div>
</div>
</body>
</html>