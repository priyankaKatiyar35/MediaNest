<?php
$body_class = 'page-upload';
$page_title = 'Upload Video';

// ===== PHP logic preserved from previous version =====
require_once __DIR__ . '/admin_auth.php';
requireAdmin();
$success = $failed = $msz = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload'])) {
    if (!csrfCheck($_POST['csrf'] ?? '')) {
        $failed = 'Session expired. Please refresh and try again.';
    } elseif (!isset($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
        $msz = 'Please select a video to upload.';
    } elseif ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $errMap = [
            UPLOAD_ERR_INI_SIZE   => 'File is too large (exceeds server limit).',
            UPLOAD_ERR_FORM_SIZE  => 'File is too large.',
            UPLOAD_ERR_PARTIAL    => 'Upload was interrupted. Please try again.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server error: missing temp folder.',
            UPLOAD_ERR_CANT_WRITE => 'Server error: cannot write file.',
            UPLOAD_ERR_EXTENSION  => 'Upload blocked by a server extension.',
        ];
        $failed = $errMap[$_FILES['file']['error']] ?? 'Upload failed with an unknown error.';
    } else {
        $file   = $_FILES['file'];
        $title  = trim($_POST['title'] ?? '');
        $des    = trim($_POST['des'] ?? '');
        $cat_id = isset($_POST['category_id']) && $_POST['category_id'] !== '' ? intval($_POST['category_id']) : null;

        if ($title === '')                                $failed = 'Title is required.';
        elseif (mb_strlen($title) > 200)                  $failed = 'Title is too long (max 200 characters).';
        elseif (mb_strlen($des) > 2000)                   $failed = 'Description is too long (max 2000 characters).';
        elseif ($file['size'] > 500 * 1024 * 1024)        $failed = 'Video is too large. Maximum is 500 MB.';
        elseif ($file['size'] < 1024)                     $failed = 'File seems empty or corrupted.';
        else {
            $allowed = ['mp4', 'mov', 'mkv', 'avi', 'webm', 'm4v'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) {
                $failed = 'Invalid file type. Allowed: ' . strtoupper(implode(', ', $allowed)) . '.';
            } else {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $detectedMime = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                if (strpos($detectedMime, 'video/') !== 0) {
                    $failed = 'File contents do not appear to be a video.';
                } else {
                    $safe_base = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
                    $safe_base = substr($safe_base, 0, 80);
                    $unique = $safe_base . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;

                    $upload_dir = __DIR__ . '/upload';
                    if (!is_dir($upload_dir)) @mkdir($upload_dir, 0755, true);
                    $destination = $upload_dir . '/' . $unique;

                    if (move_uploaded_file($file['tmp_name'], $destination)) {
                        if ($cat_id !== null) {
                            $stmt = mysqli_prepare($conn, "INSERT INTO video (name, title, des, category_id) VALUES (?, ?, ?, ?)");
                            mysqli_stmt_bind_param($stmt, 'sssi', $unique, $title, $des, $cat_id);
                        } else {
                            $stmt = mysqli_prepare($conn, "INSERT INTO video (name, title, des) VALUES (?, ?, ?)");
                            mysqli_stmt_bind_param($stmt, 'sss', $unique, $title, $des);
                        }
                        if (mysqli_stmt_execute($stmt)) {
                            $new_id = mysqli_insert_id($conn);
                            adminAuditLog('video_upload', "Uploaded video #$new_id: $title" . ($cat_id ? " [cat $cat_id]" : ""));
                            require_once __DIR__ . '/notify.php';
                            notifyAllUsers(
                                'video_new',
                                'New video: ' . $title,
                                !empty($des) ? mb_substr($des, 0, 200) : '',
                                '../Videos/video_player.php?id=' . $new_id
                            );
                            $success = "Video uploaded successfully.";
                        } else {
                            @unlink($destination);
                            $failed = 'Database error. The file was not saved.';
                        }
                    } else {
                        $failed = 'Could not move uploaded file. Check folder permissions.';
                    }
                }
            }
        }
    }
}

// Render header now that POST handling is done
require __DIR__ . '/header.php';

// Data for the form & sidebar
$recent     = mysqli_query($conn, "
    SELECT v.id, v.name, v.title, v.des, c.name AS category_name
    FROM video v LEFT JOIN video_categories c ON c.id = v.category_id
    ORDER BY v.id DESC LIMIT 5
");
$categories = mysqli_query($conn, "SELECT id, name FROM video_categories ORDER BY sort_order, name");
?>

<style>
.upload-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 22px; }
@media (max-width: 1000px) { .upload-grid { grid-template-columns: 1fr; } }

.field { margin-bottom: 18px; }
.field label { display: block; font-size: 12px; font-weight: 600; color: var(--text-soft); margin-bottom: 7px; text-transform: uppercase; letter-spacing: .05em; }
.field label .opt { color: var(--muted); font-weight: 500; text-transform: none; letter-spacing: 0; }
.input, .textarea, select.input {
  width: 100%; padding: 11px 14px;
  border: 1px solid var(--border); border-radius: 10px;
  background: var(--bg); color: var(--text);
  font: inherit; font-size: 14px; transition: all .15s;
}
.input:focus, .textarea:focus, select.input:focus { outline: 0; border-color: var(--brand-1); box-shadow: 0 0 0 3px rgba(99,102,241,.15); }
.textarea { resize: vertical; min-height: 100px; }
.char-count { font-size: 11px; color: var(--muted); margin-top: 5px; text-align: right; }
.opt-hint { font-size: 11px; color: var(--muted); margin-top: 5px; }

/* Drop zone */
.drop-zone {
  position: relative; border: 2px dashed var(--border); border-radius: 14px;
  padding: 36px 24px; text-align: center; cursor: pointer;
  transition: all .2s; background: var(--bg);
}
.drop-zone:hover, .drop-zone.drag { border-color: var(--brand-1); background: rgba(99, 102, 241, .04); }
.drop-zone input[type=file] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
.drop-zone .ic { width: 56px; height: 56px; border-radius: 14px; background: var(--grad-brand); color: white; display: grid; place-items: center; margin: 0 auto 12px; font-size: 22px; box-shadow: 0 10px 28px rgba(99,102,241,.3); }
.drop-zone .t { font-weight: 700; font-size: 15px; margin-bottom: 4px; }
.drop-zone .s { font-size: 12px; color: var(--text-soft); }
.drop-zone .meta { font-size: 11px; color: var(--muted); margin-top: 6px; }
.drop-zone.has-file { background: rgba(16, 185, 129, .06); border-color: var(--green); border-style: solid; }
.drop-zone.has-file .ic { background: linear-gradient(135deg, #10b981, #059669); box-shadow: 0 10px 28px rgba(16, 185, 129, .3); }

.alert { display: flex; align-items: flex-start; gap: 10px; padding: 12px 14px; border-radius: 10px; font-size: 13px; margin-bottom: 18px; }
.alert.success { background: rgba(16,185,129,.1); color: #065f46; border: 1px solid rgba(16,185,129,.3); }
.alert.error   { background: rgba(239,68,68,.1);  color: #991b1b; border: 1px solid rgba(239,68,68,.3); }
.alert.info    { background: rgba(14,165,233,.1); color: #075985; border: 1px solid rgba(14,165,233,.3); }
html.dark .alert.success { color: #6ee7b7; }
html.dark .alert.error   { color: #fca5a5; }
html.dark .alert.info    { color: #7dd3fc; }

.progress-bar { height: 8px; background: var(--bg); border-radius: 999px; overflow: hidden; margin-top: 16px; display: none; }
.progress-fill { height: 100%; background: var(--grad-brand); width: 0%; transition: width .2s; }
.progress-text { margin-top: 8px; font-size: 12px; color: var(--text-soft); }

.recent-item { display: flex; align-items: center; gap: 12px; padding: 10px; border-radius: 10px; transition: background .15s; }
.recent-item:hover { background: var(--bg); }
.recent-thumb { width: 40px; height: 40px; border-radius: 10px; background: rgba(99,102,241,.12); color: var(--brand-1); display: grid; place-items: center; flex-shrink: 0; }
.recent-info { flex: 1; min-width: 0; }
.recent-title { font-weight: 600; font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.recent-meta { font-size: 11px; color: var(--text-soft); margin-top: 2px; }
.recent-meta .cat-pill { display: inline-block; padding: 1px 7px; border-radius: 999px; background: rgba(99,102,241,.12); color: var(--brand-1); font-weight: 700; font-size: 10px; text-transform: uppercase; letter-spacing: .04em; }
</style>

<div class="upload-grid">
  <!-- Main form -->
  <div class="panel">
    <h3 style="font-size:16px;font-weight:700;margin-bottom:6px;"><i class="fas fa-cloud-arrow-up" style="color:var(--brand-1);margin-right:8px;"></i> Upload a new video</h3>
    <p style="color:var(--text-soft);font-size:13px;margin-bottom:22px;">Add a video to the library. Max 500 MB. Supported: MP4, MOV, MKV, AVI, WebM, M4V.</p>

    <?php if ($success): ?>
      <div class="alert success"><i class="fas fa-circle-check"></i><div><?php echo htmlspecialchars($success); ?></div></div>
    <?php endif; ?>
    <?php if ($failed): ?>
      <div class="alert error"><i class="fas fa-circle-exclamation"></i><div><?php echo htmlspecialchars($failed); ?></div></div>
    <?php endif; ?>
    <?php if ($msz): ?>
      <div class="alert info"><i class="fas fa-circle-info"></i><div><?php echo htmlspecialchars($msz); ?></div></div>
    <?php endif; ?>

    <form action="upload.php" method="post" enctype="multipart/form-data" id="uploadForm">
      <input type="hidden" name="csrf" value="<?php echo csrfToken(); ?>">
      <input type="hidden" name="upload" value="1">

      <!-- Drop zone -->
      <div class="field">
        <label>Video file</label>
        <div class="drop-zone" id="dropZone">
          <input type="file" name="file" id="fileInput" accept="video/*" required>
          <div class="ic"><i class="fas fa-film"></i></div>
          <div class="t" id="dropTitle">Drop your video here or click to browse</div>
          <div class="s" id="dropSub">MP4, MOV, MKV, AVI, WebM, M4V — up to 500 MB</div>
          <div class="meta" id="dropMeta"></div>
        </div>
      </div>

      <div class="field">
        <label for="title">Title</label>
        <input type="text" id="title" name="title" class="input" placeholder="e.g. Onboarding — Module 1: Company Overview" maxlength="200" required>
        <div class="char-count"><span id="titleCount">0</span> / 200</div>
      </div>

      <div class="field">
        <label for="category_id">Category <span class="opt">— group videos by type</span></label>
        <select id="category_id" name="category_id" class="input">
          <option value="">— Uncategorized —</option>
          <?php if ($categories): mysqli_data_seek($categories, 0); while ($c = mysqli_fetch_assoc($categories)): ?>
            <option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
          <?php endwhile; endif; ?>
        </select>
        <div class="opt-hint">Categories are managed in the <code>video_categories</code> table. Seeded with Training / Events / Tutorials / Webinars.</div>
      </div>

      <div class="field">
        <label for="des">Description <span class="opt">— shown on the video card</span></label>
        <textarea id="des" name="des" class="textarea" rows="4" placeholder="Briefly describe what this video covers…" maxlength="2000" required></textarea>
        <div class="char-count"><span id="desCount">0</span> / 2000</div>
      </div>

      <div class="progress-bar" id="progressBar"><div class="progress-fill" id="progressFill"></div></div>
      <div class="progress-text" id="progressText"></div>

      <div style="display:flex;gap:10px;margin-top:18px;">
        <button type="submit" class="btn btn-primary"><i class="fas fa-cloud-arrow-up"></i> Upload Video</button>
        <button type="reset" class="btn btn-ghost"><i class="fas fa-rotate-left"></i> Clear</button>
      </div>
    </form>
  </div>

  <!-- Recent uploads sidebar -->
  <div class="panel">
    <div class="panel-head">
      <h3><i class="fas fa-clock-rotate-left"></i> Recent uploads</h3>
    </div>
    <?php if ($recent && mysqli_num_rows($recent)): ?>
      <?php while ($r = mysqli_fetch_assoc($recent)): ?>
        <div class="recent-item">
          <div class="recent-thumb"><i class="fas fa-play"></i></div>
          <div class="recent-info">
            <div class="recent-title"><?php echo htmlspecialchars($r['title']); ?></div>
            <div class="recent-meta">
              #<?php echo (int)$r['id']; ?> ·
              <?php if (!empty($r['category_name'])): ?>
                <span class="cat-pill"><?php echo htmlspecialchars($r['category_name']); ?></span>
              <?php else: ?>
                <span style="opacity:.7;">Uncategorized</span>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <div class="empty-mini"><i class="fas fa-film"></i>No uploads yet.</div>
    <?php endif; ?>
  </div>
</div>

<script>
// Char counts
const title = document.getElementById('title'), des = document.getElementById('des');
title.addEventListener('input', () => document.getElementById('titleCount').textContent = title.value.length);
des  .addEventListener('input', () => document.getElementById('desCount').textContent   = des.value.length);

// Drop zone
const dz = document.getElementById('dropZone'), fi = document.getElementById('fileInput');
['dragenter','dragover'].forEach(e => dz.addEventListener(e, ev => { ev.preventDefault(); dz.classList.add('drag'); }));
['dragleave','drop'].forEach(e => dz.addEventListener(e, ev => { ev.preventDefault(); dz.classList.remove('drag'); }));
dz.addEventListener('drop', ev => { if (ev.dataTransfer.files.length) { fi.files = ev.dataTransfer.files; fi.dispatchEvent(new Event('change')); } });
fi.addEventListener('change', () => {
  if (fi.files.length) {
    const f = fi.files[0];
    document.getElementById('dropTitle').textContent = f.name;
    document.getElementById('dropMeta').textContent = (f.size / 1024 / 1024).toFixed(2) + ' MB';
    dz.classList.add('has-file');
    if (!title.value) title.value = f.name.replace(/\.[^.]+$/, '').replace(/[_-]+/g, ' ');
    title.dispatchEvent(new Event('input'));
  }
});

// Progress (XHR upload — replaces normal form submit if a file is chosen)
const form = document.getElementById('uploadForm');
const pBar = document.getElementById('progressBar'), pFill = document.getElementById('progressFill'), pText = document.getElementById('progressText');
form.addEventListener('submit', e => {
  if (!fi.files.length) return; // let validation kick in
  e.preventDefault();
  const xhr = new XMLHttpRequest();
  const fd = new FormData(form);
  xhr.upload.addEventListener('progress', ev => {
    if (ev.lengthComputable) {
      const pct = (ev.loaded / ev.total * 100);
      pBar.style.display = 'block';
      pFill.style.width = pct + '%';
      pText.textContent = pct.toFixed(1) + '% — ' + (ev.loaded / 1048576).toFixed(2) + ' / ' + (ev.total / 1048576).toFixed(2) + ' MB';
    }
  });
  xhr.addEventListener('load', () => {
    if (xhr.status === 200) { document.open(); document.write(xhr.responseText); document.close(); }
    else pText.textContent = 'Upload failed (HTTP ' + xhr.status + ').';
  });
  xhr.open('POST', 'upload.php', true);
  xhr.send(fd);
});
</script>

    </main>
  </div>
</div>
</body>
</html>