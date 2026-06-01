<?php
$body_class = 'page-documents';
$page_title = 'Upload Documents';

require_once __DIR__ . '/admin_auth.php';
requireAdmin();
global $conn;
$con = $conn;

if (!is_dir(__DIR__ . '/uploads/')) mkdir(__DIR__ . '/uploads/', 0777, true);

$msg = [];

// Create Main Folder
if (isset($_POST['submit_main_folder'])) {
    $folder_name  = trim($_POST['aname'] ?? '');
    $folder_desc  = trim($_POST['adesc'] ?? '');
    $folder_image = '';
    if (!empty($_FILES['folder_image']['name'])) {
        $img_path = __DIR__ . '/uploads/' . basename($_FILES['folder_image']['name']);
        if (move_uploaded_file($_FILES['folder_image']['tmp_name'], $img_path)) {
            $folder_image = 'uploads/' . basename($_FILES['folder_image']['name']);
        } else $msg[] = ['type'=>'err','text'=>'Error uploading folder image.'];
    }
    $stmt = mysqli_prepare($con,
        "INSERT INTO folders (name, adesc, parent_folder_id, folder_image) VALUES (?, ?, NULL, ?)"
    );
    mysqli_stmt_bind_param($stmt, 'sss', $folder_name, $folder_desc, $folder_image);
    if (mysqli_stmt_execute($stmt)) {
        @adminAuditLog('folder_create', "Main folder: $folder_name");
        $msg[] = ['type'=>'ok','text'=>'Main folder created successfully!'];
    } else {
        $msg[] = ['type'=>'err','text'=>'DB error: ' . mysqli_stmt_error($stmt)];
    }
    mysqli_stmt_close($stmt);
}

// Create Subfolder
if (isset($_POST['submit_subfolder'])) {
    $parent_id = intval($_POST['parent_folder'] ?? 0);
    $sub_name  = trim($_POST['sub_aname'] ?? '');
    $sub_desc  = trim($_POST['sub_adesc'] ?? '');
    $sub_image = '';
    if (!empty($_FILES['subfolder_image']['name'])) {
        $img_path = __DIR__ . '/uploads/' . basename($_FILES['subfolder_image']['name']);
        if (move_uploaded_file($_FILES['subfolder_image']['tmp_name'], $img_path)) {
            $sub_image = 'uploads/' . basename($_FILES['subfolder_image']['name']);
        } else $msg[] = ['type'=>'err','text'=>'Error uploading subfolder image.'];
    }
    $stmt = mysqli_prepare($con,
        "INSERT INTO folders (name, adesc, parent_folder_id, folder_image) VALUES (?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param($stmt, 'ssis', $sub_name, $sub_desc, $parent_id, $sub_image);
    if (mysqli_stmt_execute($stmt)) {
        @adminAuditLog('subfolder_create', "Subfolder: $sub_name");
        $msg[] = ['type'=>'ok','text'=>'Subfolder created successfully!'];
    } else {
        $msg[] = ['type'=>'err','text'=>'DB error: ' . mysqli_stmt_error($stmt)];
    }
    mysqli_stmt_close($stmt);
}

// Upload File
if (isset($_POST['submit_file_upload'])) {
    $folder_id  = intval($_POST['folder_id'] ?? 0);
    $video_link = trim($_POST['video_link'] ?? '');
    $file_desc  = trim($_POST['file_desc'] ?? '');
    if (!empty($_FILES['file_upload']['name'])) {
        $file_name = basename($_FILES['file_upload']['name']);
        $file_path = 'uploads/' . $file_name;
        $dest = __DIR__ . '/' . $file_path;
        if (move_uploaded_file($_FILES['file_upload']['tmp_name'], $dest)) {
            $stmt = mysqli_prepare($con,
                "INSERT INTO files (folder_id, file_name, file_path, video_link, file_desc) VALUES (?, ?, ?, ?, ?)"
            );
            mysqli_stmt_bind_param($stmt, 'issss', $folder_id, $file_name, $file_path, $video_link, $file_desc);
            if (mysqli_stmt_execute($stmt)) {
                @adminAuditLog('file_upload', "File: $file_name");
                $new_fid = mysqli_insert_id($con);
                require_once __DIR__ . '/notify.php';
                notifyAllUsers(
                    'doc_new',
                    'New document: ' . $file_name,
                    !empty($file_desc) ? mb_substr($file_desc, 0, 200) : '',
                    '../Documents/view_file.php?file_id=' . $new_fid
                );
                $msg[] = ['type'=>'ok','text'=>'File uploaded successfully!'];
            } else {
                $msg[] = ['type'=>'err','text'=>'DB error: ' . mysqli_stmt_error($stmt)];
            }
            mysqli_stmt_close($stmt);
        } else $msg[] = ['type'=>'err','text'=>'Failed to move uploaded file.'];
    } else $msg[] = ['type'=>'err','text'=>'Please select a file to upload.'];
}

// Fetch folders
$all_folders = [];
$main_folders = [];
$fres = mysqli_query($con, "SELECT * FROM folders ORDER BY parent_folder_id ASC, name ASC");
if ($fres) while ($r = mysqli_fetch_assoc($fres)) {
    $all_folders[] = $r;
    if (is_null($r['parent_folder_id'])) $main_folders[] = $r;
}

require __DIR__ . '/header.php';
?>

<style>
.upload-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 18px; margin-bottom: 22px; }
.field { margin-bottom: 14px; }
.field label { display: block; font-size: 12px; font-weight: 600; color: var(--text-soft); margin-bottom: 6px; text-transform: uppercase; letter-spacing: .05em; }
.input, .textarea, select.input { width: 100%; padding: 11px 14px; border: 1px solid var(--border); border-radius: 10px; background: var(--bg); color: var(--text); font: inherit; font-size: 14px; }
.input:focus, .textarea:focus, select.input:focus { outline: 0; border-color: var(--brand-1); box-shadow: 0 0 0 3px rgba(99,102,241,.15); }
.textarea { resize: vertical; min-height: 70px; }
.field-hint { font-size: 11px; color: var(--muted); margin-top: 4px; }

.panel-mini { background: var(--bg-elev); border: 1px solid var(--border); border-radius: 14px; padding: 20px; }
.panel-mini > h4 { font-size: 14px; font-weight: 700; margin-bottom: 4px; display: flex; align-items: center; gap: 8px; }
.panel-mini > h4 i { width: 30px; height: 30px; border-radius: 8px; display: grid; place-items: center; font-size: 13px; }
.panel-mini.green > h4 i { background: rgba(16,185,129,.15); color: #10b981; }
.panel-mini.blue > h4 i { background: rgba(14,165,233,.15); color: #0ea5e9; }
.panel-mini.violet > h4 i { background: rgba(139,92,246,.15); color: #8b5cf6; }
.panel-mini .h4-sub { font-size: 12px; color: var(--text-soft); margin-bottom: 14px; }

.alert { display: flex; align-items: flex-start; gap: 10px; padding: 11px 13px; border-radius: 10px; font-size: 13px; margin-bottom: 12px; }
.alert.success { background: rgba(16,185,129,.1); color: #065f46; border: 1px solid rgba(16,185,129,.3); }
.alert.error   { background: rgba(239,68,68,.1);  color: #991b1b; border: 1px solid rgba(239,68,68,.3); }
html.dark .alert.success { color: #6ee7b7; }
html.dark .alert.error { color: #fca5a5; }

.folder-tree { display: flex; flex-direction: column; gap: 4px; }
.folder-item { display: flex; align-items: center; gap: 10px; padding: 9px 12px; border-radius: 8px; transition: background .15s; }
.folder-item:hover { background: var(--bg); }
.folder-item .icon { width: 32px; height: 32px; border-radius: 8px; background: rgba(16,185,129,.15); color: #10b981; display: grid; place-items: center; font-size: 13px; }
.folder-item.sub .icon { background: rgba(14,165,233,.12); color: #0ea5e9; margin-left: 22px; }
.folder-item .info { flex: 1; min-width: 0; }
.folder-item .name { font-weight: 600; font-size: 13px; }
.folder-item .desc { font-size: 11px; color: var(--text-soft); }
</style>

<?php foreach ($msg as $m): ?>
  <div class="alert <?php echo $m['type'] === 'ok' ? 'success' : 'error'; ?>">
    <i class="fas fa-<?php echo $m['type'] === 'ok' ? 'circle-check' : 'circle-exclamation'; ?>"></i>
    <div><?php echo htmlspecialchars($m['text']); ?></div>
  </div>
<?php endforeach; ?>

<div class="upload-grid">
  <!-- Main folder -->
  <div class="panel-mini green">
    <h4><i class="fas fa-folder"></i> Create main folder</h4>
    <p class="h4-sub">Top-level folder visible in the documents area.</p>
    <form method="post" enctype="multipart/form-data">
      <div class="field"><label>Folder name</label><input type="text" name="aname" class="input" required></div>
      <div class="field"><label>Description</label><textarea name="adesc" class="textarea" rows="2"></textarea></div>
      <div class="field"><label>Folder image</label><input type="file" name="folder_image" class="input" accept="image/*"></div>
      <button type="submit" name="submit_main_folder" class="btn btn-primary"><i class="fas fa-plus"></i> Create folder</button>
    </form>
  </div>

  <!-- Subfolder -->
  <div class="panel-mini blue">
    <h4><i class="fas fa-folder-tree"></i> Add subfolder</h4>
    <p class="h4-sub">A folder inside an existing main folder.</p>
    <form method="post" enctype="multipart/form-data">
      <div class="field">
        <label>Parent folder</label>
        <select name="parent_folder" class="input" required>
          <option value="">— Pick a parent —</option>
          <?php foreach ($main_folders as $mf): ?>
            <option value="<?php echo (int)$mf['albumid']; ?>"><?php echo htmlspecialchars($mf['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label>Subfolder name</label><input type="text" name="sub_aname" class="input" required></div>
      <div class="field"><label>Description</label><textarea name="sub_adesc" class="textarea" rows="2"></textarea></div>
      <div class="field"><label>Subfolder image</label><input type="file" name="subfolder_image" class="input" accept="image/*"></div>
      <button type="submit" name="submit_subfolder" class="btn btn-primary"><i class="fas fa-plus"></i> Create subfolder</button>
    </form>
  </div>

  <!-- Upload file -->
  <div class="panel-mini violet">
    <h4><i class="fas fa-file-arrow-up"></i> Upload a file</h4>
    <p class="h4-sub">Add a document into any folder/subfolder.</p>
    <form method="post" enctype="multipart/form-data">
      <div class="field">
        <label>Target folder</label>
        <select name="folder_id" class="input" required>
          <option value="">— Pick a folder —</option>
          <?php foreach ($all_folders as $f):
            $label = $f['name'];
            if (!is_null($f['parent_folder_id'])) {
                foreach ($all_folders as $p) if ($p['albumid'] == $f['parent_folder_id']) { $label = $p['name'] . ' / ' . $f['name']; break; }
            }
          ?>
            <option value="<?php echo (int)$f['albumid']; ?>"><?php echo htmlspecialchars($label); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label>File</label><input type="file" name="file_upload" class="input" required></div>
      <div class="field"><label>Description / title</label><input type="text" name="file_desc" class="input" placeholder="Display title for the file"></div>
      <div class="field">
        <label>Optional video link</label>
        <input type="url" name="video_link" class="input" placeholder="https://… (YouTube etc.)">
        <div class="field-hint">If the file has a companion video, paste the URL here.</div>
      </div>
      <button type="submit" name="submit_file_upload" class="btn btn-primary"><i class="fas fa-upload"></i> Upload file</button>
    </form>
  </div>
</div>

<!-- Folder tree -->
<div class="panel">
  <div class="panel-head">
    <h3><i class="fas fa-folder-tree"></i> Folder structure <span style="font-size:13px;color:var(--muted);font-weight:500;">(<?php echo count($all_folders); ?>)</span></h3>
  </div>
  <?php if (count($all_folders) === 0): ?>
    <div class="empty-mini"><i class="fas fa-folder"></i>No folders yet — create your first one above.</div>
  <?php else: ?>
    <div class="folder-tree">
      <?php foreach ($main_folders as $mf): ?>
        <div class="folder-item">
          <div class="icon"><i class="fas fa-folder"></i></div>
          <div class="info">
            <div class="name"><?php echo htmlspecialchars($mf['name']); ?></div>
            <?php if (!empty($mf['adesc'])): ?><div class="desc"><?php echo htmlspecialchars(mb_strimwidth($mf['adesc'], 0, 80, '…')); ?></div><?php endif; ?>
          </div>
          <a href="../Documents/parent_folder_details.php?folder_id=<?php echo (int)$mf['albumid']; ?>" target="_blank" class="btn btn-ghost" style="padding:5px 10px;font-size:12px;"><i class="fas fa-eye"></i></a>
        </div>
        <?php foreach ($all_folders as $sf): if ($sf['parent_folder_id'] != $mf['albumid']) continue; ?>
          <div class="folder-item sub">
            <div class="icon"><i class="fas fa-folder-open"></i></div>
            <div class="info">
              <div class="name"><?php echo htmlspecialchars($sf['name']); ?></div>
              <?php if (!empty($sf['adesc'])): ?><div class="desc"><?php echo htmlspecialchars(mb_strimwidth($sf['adesc'], 0, 80, '…')); ?></div><?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

    </main>
  </div>
</div>
</body>
</html>