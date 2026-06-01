<?php
// Temporarily show errors so blank-page bugs are visible. Remove these 2 lines in production.
ini_set('display_errors', 1);
error_reporting(E_ALL);

$body_class = 'page-videos';
$page_title = 'Video Library';

require_once __DIR__ . '/admin_auth.php';
requireAdmin();
$con = $conn;

$success = $failed = $msz = '';

// Make sure gallery_video table exists (won't break if it already does)
@mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS gallery_video (
        id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name  VARCHAR(255) NOT NULL,
        title VARCHAR(200) NOT NULL,
        des   TEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

if (isset($_POST['upload'])) {
    if (empty($_FILES['file']['name'])) {
        $msz = "Please select a video to upload.";
    } else {
        $orig = $_FILES['file']['name'];
        $temp = $_FILES['file']['tmp_name'];
        $title = trim($_POST['title'] ?? '');
        $des   = trim($_POST['des'] ?? '');

        if ($title === '') {
            $msz = "Title is required.";
        } else {
            $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            $allowed = ['mp4','mov','mkv','avi','webm','m4v'];
            if (!in_array($ext, $allowed)) {
                $failed = "Invalid file type. Allowed: " . strtoupper(implode(', ', $allowed)) . ".";
            } else {
                $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', $orig);
                $unique = pathinfo($safe, PATHINFO_FILENAME) . '_' . date('Ymd_His') . '.' . $ext;

                if (!is_dir(__DIR__ . '/upload')) @mkdir(__DIR__ . '/upload', 0755, true);
                $dest = __DIR__ . '/upload/' . $unique;

                if (move_uploaded_file($temp, $dest)) {
                    $stmt = mysqli_prepare($conn, "INSERT INTO gallery_video (name, title, des) VALUES (?, ?, ?)");
                    mysqli_stmt_bind_param($stmt, 'sss', $unique, $title, $des);
                    if (mysqli_stmt_execute($stmt)) {
                        @adminAuditLog('gallery_video_upload', "Uploaded gallery video: $title");
                        $success = "Video uploaded successfully.";
                    } else {
                        $failed = "Database error: " . mysqli_error($conn);
                        @unlink($dest);
                    }
                } else {
                    $msz = "Failed to save file. Check upload folder permissions.";
                }
            }
        }
    }
}

// Main library — with defensive fallback if video_categories not yet created
$main_videos = [];
$has_categories = false;
$tbl_check = @mysqli_query($conn, "SHOW TABLES LIKE 'video_categories'");
if ($tbl_check && mysqli_num_rows($tbl_check) > 0) $has_categories = true;

if ($has_categories) {
    $mvres = @mysqli_query($conn, "
        SELECT v.id, v.name, v.title, v.des, c.name AS cat_name
        FROM video v LEFT JOIN video_categories c ON c.id = v.category_id
        ORDER BY v.id DESC
    ");
} else {
    $mvres = @mysqli_query($conn, "SELECT id, name, title, des, NULL AS cat_name FROM video ORDER BY id DESC");
}
if ($mvres) while ($r = mysqli_fetch_assoc($mvres)) $main_videos[] = $r;

// Gallery videos
$videos = [];
$vres = @mysqli_query($conn, "SELECT * FROM gallery_video ORDER BY id DESC");
if ($vres) while ($r = mysqli_fetch_assoc($vres)) $videos[] = $r;

require __DIR__ . '/header.php';
?>

<style>
.field { margin-bottom: 16px; }
.field label { display: block; font-size: 12px; font-weight: 600; color: var(--text-soft); margin-bottom: 7px; text-transform: uppercase; letter-spacing: .05em; }
.input, .textarea { width: 100%; padding: 11px 14px; border: 1px solid var(--border); border-radius: 10px; background: var(--bg); color: var(--text); font: inherit; font-size: 14px; }
.input:focus, .textarea:focus { outline: 0; border-color: var(--brand-1); box-shadow: 0 0 0 3px rgba(99,102,241,.15); }
.textarea { resize: vertical; min-height: 80px; }

.alert { display: flex; align-items: flex-start; gap: 10px; padding: 12px 14px; border-radius: 10px; font-size: 13px; margin-bottom: 16px; }
.alert.success { background: rgba(16,185,129,.1); color: #065f46; border: 1px solid rgba(16,185,129,.3); }
.alert.error   { background: rgba(239,68,68,.1);  color: #991b1b; border: 1px solid rgba(239,68,68,.3); }
.alert.info    { background: rgba(14,165,233,.1); color: #075985; border: 1px solid rgba(14,165,233,.3); }
html.dark .alert.success { color: #6ee7b7; }
html.dark .alert.error { color: #fca5a5; }
html.dark .alert.info { color: #7dd3fc; }

.drop-zone { position: relative; border: 2px dashed var(--border); border-radius: 14px; padding: 24px; text-align: center; cursor: pointer; background: var(--bg); transition: all .2s; }
.drop-zone:hover, .drop-zone.drag { border-color: var(--brand-1); background: rgba(99,102,241,.04); }
.drop-zone input[type=file] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
.drop-zone .ic { width: 44px; height: 44px; border-radius: 12px; background: var(--grad-brand); color: white; display: grid; place-items: center; margin: 0 auto 8px; font-size: 16px; }
.drop-zone .t { font-weight: 700; font-size: 13px; }
.drop-zone .s { font-size: 11px; color: var(--text-soft); margin-top: 3px; }
.drop-zone.has-file { background: rgba(16,185,129,.06); border-color: #10b981; border-style: solid; }

.two-col { display: grid; grid-template-columns: 1fr 2fr; gap: 22px; margin-bottom: 22px; }
@media (max-width: 1000px) { .two-col { grid-template-columns: 1fr; } }

.video-row { display: flex; align-items: center; gap: 14px; padding: 12px 14px; border-radius: 10px; transition: background .15s; border: 1px solid var(--border); margin-bottom: 8px; }
.video-row:hover { background: var(--bg); border-color: rgba(99,102,241,.25); }
.video-thumb { width: 56px; height: 56px; border-radius: 10px; background: rgba(99,102,241,.12); color: var(--brand-1); display: grid; place-items: center; flex-shrink: 0; font-size: 18px; }
.video-info { flex: 1; min-width: 0; }
.video-title { font-weight: 600; font-size: 14px; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.video-desc { font-size: 12px; color: var(--text-soft); margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.video-meta { display: flex; gap: 8px; align-items: center; margin-top: 4px; font-size: 11px; color: var(--muted); }
.cat-pill { padding: 2px 8px; border-radius: 999px; background: rgba(99,102,241,.12); color: var(--brand-1); font-weight: 700; text-transform: uppercase; letter-spacing: .04em; font-size: 10px; }
.video-actions { display: flex; gap: 6px; }
.video-actions a { padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 600; background: var(--bg); border: 1px solid var(--border); color: var(--text-soft); transition: all .15s; }
.video-actions a:hover { color: var(--brand-1); border-color: var(--brand-1); }

.tab-bar { display: flex; gap: 4px; margin-bottom: 18px; border-bottom: 1px solid var(--border); }
.tab { padding: 10px 16px; font-size: 13px; font-weight: 600; color: var(--text-soft); cursor: pointer; border-bottom: 2px solid transparent; transition: all .15s; }
.tab.active { color: var(--brand-1); border-bottom-color: var(--brand-1); }
.tab .badge { display: inline-block; padding: 1px 7px; border-radius: 999px; background: var(--bg); font-size: 10px; margin-left: 4px; }
.tab-panel { display: none; }
.tab-panel.active { display: block; }
</style>

<div class="two-col">
  <div class="panel">
    <h3 style="font-size:16px;font-weight:700;margin-bottom:6px;"><i class="fas fa-cloud-arrow-up" style="color:#ec4899;margin-right:8px;"></i> Upload to "Special Collection"</h3>
    <p style="color:var(--text-soft);font-size:12px;margin-bottom:16px;">These videos appear on the public <strong>Photo Gallery → Special Collection</strong> tile.</p>

    <?php if ($success): ?><div class="alert success"><i class="fas fa-circle-check"></i><div><?php echo htmlspecialchars($success); ?></div></div><?php endif; ?>
    <?php if ($failed): ?><div class="alert error"><i class="fas fa-circle-exclamation"></i><div><?php echo htmlspecialchars($failed); ?></div></div><?php endif; ?>
    <?php if ($msz): ?><div class="alert info"><i class="fas fa-circle-info"></i><div><?php echo htmlspecialchars($msz); ?></div></div><?php endif; ?>

    <form method="post" enctype="multipart/form-data">
      <div class="field"><label>Title</label><input type="text" name="title" class="input" required maxlength="200"></div>
      <div class="field"><label>Description</label><textarea name="des" class="textarea" rows="3"></textarea></div>
      <div class="field">
        <label>Video file</label>
        <div class="drop-zone" id="dropZone">
          <input type="file" name="file" id="fileInput" accept="video/*" required>
          <div class="ic"><i class="fas fa-film"></i></div>
          <div class="t" id="dropTitle">Choose video</div>
          <div class="s" id="dropSub">MP4, MOV, MKV, AVI, WebM</div>
        </div>
      </div>
      <button type="submit" name="upload" class="btn btn-primary"><i class="fas fa-upload"></i> Upload</button>
    </form>
  </div>

  <div class="panel">
    <div class="tab-bar">
      <div class="tab active" data-tab="gallery"><i class="fas fa-star"></i> Special Collection <span class="badge"><?php echo count($videos); ?></span></div>
      <div class="tab" data-tab="main"><i class="fas fa-film"></i> Main library <span class="badge"><?php echo count($main_videos); ?></span></div>
    </div>

    <div class="tab-panel active" data-panel="gallery">
      <?php if (count($videos) === 0): ?>
        <div class="empty-mini"><i class="fas fa-star"></i>No videos in special collection yet — upload one on the left.</div>
      <?php else: ?>
        <?php foreach ($videos as $v): ?>
          <div class="video-row">
            <div class="video-thumb" style="background:rgba(236,72,153,.12);color:#ec4899;"><i class="fas fa-play"></i></div>
            <div class="video-info">
              <div class="video-title"><?php echo htmlspecialchars($v['title']); ?></div>
              <div class="video-desc"><?php echo htmlspecialchars(mb_strimwidth($v['des'] ?? '', 0, 100, '…')); ?></div>
              <div class="video-meta">
                <span>#<?php echo (int)$v['id']; ?></span>
                <span><?php echo htmlspecialchars($v['name']); ?></span>
              </div>
            </div>
            <div class="video-actions">
              <a href="../Photo/gallery_video.php" target="_blank"><i class="fas fa-eye"></i></a>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div class="tab-panel" data-panel="main">
      <?php if (count($main_videos) === 0): ?>
        <div class="empty-mini"><i class="fas fa-film"></i>No videos in main library — head to <a href="upload.php" style="color:var(--brand-1);font-weight:600;">Upload</a>.</div>
      <?php else: ?>
        <?php foreach ($main_videos as $v): ?>
          <div class="video-row">
            <div class="video-thumb"><i class="fas fa-play"></i></div>
            <div class="video-info">
              <div class="video-title"><?php echo htmlspecialchars($v['title']); ?></div>
              <div class="video-desc"><?php echo htmlspecialchars(mb_strimwidth($v['des'] ?? '', 0, 100, '…')); ?></div>
              <div class="video-meta">
                <span>#<?php echo (int)$v['id']; ?></span>
                <?php if (!empty($v['cat_name'])): ?><span class="cat-pill"><?php echo htmlspecialchars($v['cat_name']); ?></span><?php endif; ?>
              </div>
            </div>
            <div class="video-actions">
              <a href="../Videos/video_player.php?id=<?php echo (int)$v['id']; ?>" target="_blank"><i class="fas fa-eye"></i></a>
              <a href="quiz_editor.php?vid=<?php echo (int)$v['id']; ?>" title="Quiz"><i class="fas fa-question"></i></a>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
const dz = document.getElementById('dropZone'), fi = document.getElementById('fileInput');
['dragenter','dragover'].forEach(e => dz.addEventListener(e, ev => { ev.preventDefault(); dz.classList.add('drag'); }));
['dragleave','drop'].forEach(e => dz.addEventListener(e, ev => { ev.preventDefault(); dz.classList.remove('drag'); }));
dz.addEventListener('drop', ev => { if (ev.dataTransfer.files.length) { fi.files = ev.dataTransfer.files; fi.dispatchEvent(new Event('change')); } });
fi.addEventListener('change', () => {
  if (fi.files.length) {
    document.getElementById('dropTitle').textContent = fi.files[0].name;
    document.getElementById('dropSub').textContent = (fi.files[0].size / 1024 / 1024).toFixed(1) + ' MB';
    dz.classList.add('has-file');
  }
});

document.querySelectorAll('.tab').forEach(t => t.addEventListener('click', () => {
  document.querySelectorAll('.tab').forEach(x => x.classList.remove('active'));
  document.querySelectorAll('.tab-panel').forEach(x => x.classList.remove('active'));
  t.classList.add('active');
  document.querySelector('.tab-panel[data-panel=' + t.dataset.tab + ']').classList.add('active');
}));
</script>

    </main>
  </div>
</div>
</body>
</html>