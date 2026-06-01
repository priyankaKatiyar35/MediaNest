<?php
/**
 * MediaNest — Document Folders (index)
 * --------------------------------------------------------------
 * - Unified auth gate.
 * - Top-level search across folders AND files (with file_id-aware
 *   links). If search has results, we surface those instead of the
 *   default folder grid.
 */
require_once __DIR__ . '/../auth/auth.php';
requireLogin();

$con = mysqli_connect('localhost', 'root', '', 's&p');
if (!$con) die('DB connection failed: ' . mysqli_connect_error());

$current_user = currentUser();
$q = trim($_GET['q'] ?? '');

// ---------- Default: list top-level folders ----------
$folders = [];
if ($q === '') {
    $res = mysqli_query($con, "SELECT * FROM folders WHERE parent_folder_id IS NULL ORDER BY name ASC");
    if ($res) while ($r = mysqli_fetch_assoc($res)) $folders[] = $r;
}

// Hero stats
$folder_count = (int)mysqli_fetch_row(mysqli_query($con, "SELECT COUNT(*) FROM folders"))[0];
$file_count   = (int)mysqli_fetch_row(mysqli_query($con, "SELECT COUNT(*) FROM files"))[0];

// ---------- Search: folders + files at once ----------
$found_folders = $found_files = [];
if ($q !== '') {
    $like = '%' . $q . '%';

    // Folders (any depth) matching name or description
    $stmt = mysqli_prepare($con,
        "SELECT * FROM folders WHERE name LIKE ? OR adesc LIKE ? ORDER BY name ASC LIMIT 50"
    );
    mysqli_stmt_bind_param($stmt, 'ss', $like, $like);
    mysqli_stmt_execute($stmt);
    $r = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($r)) $found_folders[] = $row;

    // Files matching name or description; pull folder name too
    $stmt = mysqli_prepare($con,
        "SELECT f.*, fo.name AS folder_name, fo.albumid AS folder_id
         FROM files f
         LEFT JOIN folders fo ON fo.albumid = f.folder_id
         WHERE f.file_name LIKE ? OR f.file_desc LIKE ?
         ORDER BY f.file_id DESC LIMIT 80"
    );
    mysqli_stmt_bind_param($stmt, 'ss', $like, $like);
    mysqli_stmt_execute($stmt);
    $r = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($r)) $found_files[] = $row;
}

// Helper to count children for a top-level folder
function folder_subcount($con, $id) {
    $stmt = mysqli_prepare($con, "SELECT COUNT(*) FROM folders WHERE parent_folder_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    return (int) (mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0] ?? 0);
}
function folder_filecount($con, $id) {
    $stmt = mysqli_prepare($con, "SELECT COUNT(*) FROM files WHERE folder_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    return (int) (mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0] ?? 0);
}
function file_type_meta($ext) {
    $ext = strtolower($ext);
    if ($ext === 'pdf')                                  return ['pdf',   'PDF',   '#dc2626'];
    if (in_array($ext, ['doc','docx','odt']))            return ['word',  'Word',  '#2563eb'];
    if (in_array($ext, ['xls','xlsx','csv','ods']))      return ['excel', 'Excel', '#059669'];
    if (in_array($ext, ['ppt','pptx','odp']))            return ['ppt',   'PPT',   '#ea580c'];
    if (in_array($ext, ['jpg','jpeg','png','gif','webp','svg','bmp'])) return ['img',  strtoupper($ext), '#7c3aed'];
    if (in_array($ext, ['mp4','webm','ogg','mov']))      return ['vid',   'Video', '#0891b2'];
    return ['other', strtoupper($ext ?: 'FILE'), '#6b7280'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Documents — MediaNest</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/js/all.min.js" defer></script>

<style>
:root {
  --bg: #f6f7fb; --bg-elev: #ffffff;
  --text: #0f172a; --text-soft: #475569; --muted: #94a3b8;
  --border: rgba(15, 23, 42, 0.08);
  --shadow-sm: 0 2px 8px rgba(15, 23, 42, 0.06);
  --shadow-md: 0 10px 30px rgba(15, 23, 42, 0.08);
  --brand-1: #10b981; --brand-2: #059669;
  --radius: 16px; --radius-lg: 22px;
  --grad-brand: linear-gradient(135deg, #10b981, #059669);
  --grad-text:  linear-gradient(135deg, #10b981, #0ea5e9 50%, #6366f1);
}
html.dark {
  --bg: #0a0e1a; --bg-elev: #131826;
  --text: #e2e8f0; --text-soft: #cbd5e1; --muted: #64748b;
  --border: rgba(255, 255, 255, 0.08);
  --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.3);
  --shadow-md: 0 10px 30px rgba(0, 0, 0, 0.4);
}
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Inter', sans-serif; color: var(--text); background: var(--bg); min-height: 100vh; }
h1, h2 { font-family: 'Sora', sans-serif; letter-spacing: -0.02em; }
a { color: inherit; }

/* ===== NAV ===== */
.nav {
  position: sticky; top: 0; z-index: 50;
  background: rgba(246,247,251,0.85); backdrop-filter: blur(12px);
  border-bottom: 1px solid var(--border);
}
html.dark .nav { background: rgba(10,14,26,0.85); }
.nav-inner {
  max-width: 1280px; margin: 0 auto; padding: 14px 24px;
  display: flex; align-items: center; justify-content: space-between; gap: 16px;
}
.brand { display: flex; align-items: center; gap: 10px; text-decoration: none; color: var(--text); }
.brand-mark {
  width: 36px; height: 36px; border-radius: 10px;
  background: var(--grad-brand); color: white;
  display: grid; place-items: center;
  box-shadow: 0 6px 18px rgba(16, 185, 129, 0.35);
}
.brand-name { font-family: 'Sora', sans-serif; font-weight: 700; font-size: 18px; }
.brand-name span { background: var(--grad-text); -webkit-background-clip: text; background-clip: text; color: transparent; }
.nav-links { display: flex; gap: 4px; }
.nav-link { padding: 8px 14px; border-radius: 10px; font-size: 14px; font-weight: 500;
            color: var(--text-soft); text-decoration: none; transition: background .2s, color .2s; }
.nav-link:hover { background: var(--border); color: var(--text); }
.nav-link.active { color: var(--text); background: var(--border); }
.nav-actions { display: flex; align-items: center; gap: 10px; }
.icon-btn {
  width: 38px; height: 38px; border-radius: 10px;
  background: var(--bg-elev); border: 1px solid var(--border);
  color: var(--text); cursor: pointer; display: grid; place-items: center;
}
.user-chip { display: flex; align-items: center; gap: 8px; padding: 6px 12px 6px 6px;
             background: var(--bg-elev); border: 1px solid var(--border); border-radius: 999px;
             font-size: 13px; font-weight: 500; text-decoration: none; color: var(--text); }
.user-chip .av { width: 28px; height: 28px; border-radius: 50%; background: var(--grad-brand);
                 color: white; display: grid; place-items: center; font-size: 11px; font-weight: 700; }

/* ===== PAGE ===== */
.page { max-width: 1280px; margin: 0 auto; padding: 32px 24px 80px; }
.page-head { margin-bottom: 24px; }
.page-head h1 { font-size: 28px; font-weight: 700; margin-bottom: 4px; }
.page-head h1 span { background: var(--grad-text); -webkit-background-clip: text; background-clip: text; color: transparent; }
.page-head p { color: var(--text-soft); font-size: 14px; }

/* HERO (emerald theme matching Documents) */
.hero { background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 50%, #e0f2fe 100%); border-bottom: 1px solid var(--border); padding: 48px 24px 44px; position: relative; overflow: hidden; }
.hero::before { content: ''; position: absolute; top: -100px; right: -80px; width: 320px; height: 320px; border-radius: 50%; background: radial-gradient(circle, rgba(16,185,129,.2), transparent 70%); }
html.dark .hero { background: linear-gradient(135deg, #042f2e 0%, #0a3d34 50%, #0b2438 100%); }
html.dark .hero::before { background: radial-gradient(circle, rgba(16,185,129,.12), transparent 70%); }
.hero-inner { max-width: 1280px; margin: 0 auto; position: relative; z-index: 1; }
.breadcrumb { display: inline-flex; align-items: center; gap: 8px; padding: 6px 14px; border-radius: 999px; background: rgba(255,255,255,.7); backdrop-filter: blur(8px); border: 1px solid var(--border); font-size: 12px; font-weight: 600; color: var(--text-soft); margin-bottom: 16px; }
html.dark .breadcrumb { background: rgba(19,24,38,.7); }
.breadcrumb i { font-size: 12px; color: #10b981; }
.hero h1 { font-size: clamp(28px, 4.5vw, 44px); font-weight: 800; line-height: 1.1; margin-bottom: 10px; max-width: 720px; }
.hero h1 .gradient-text { background: var(--grad-text); -webkit-background-clip: text; background-clip: text; color: transparent; }
.hero p.sub { font-size: clamp(14px, 1.3vw, 16px); color: var(--text-soft); max-width: 560px; margin-bottom: 22px; }

.hero-search { display: flex; flex-direction: column; gap: 10px; max-width: 560px; }
.search-card { background: var(--bg-elev); border: 1px solid var(--border); border-radius: 14px; padding: 6px 6px 6px 16px; box-shadow: 0 12px 30px rgba(15,23,42,.08); display: flex; align-items: center; gap: 10px; transition: box-shadow .2s, border-color .2s; }
.search-card:focus-within { border-color: #10b981; box-shadow: 0 12px 30px rgba(16,185,129,.18); }
.search-card i { color: var(--muted); }
.search-card input { flex: 1; border: 0; background: transparent; outline: none; font: inherit; font-size: 15px; color: var(--text); padding: 10px 0; }
.search-card .btn-go { padding: 10px 18px; border-radius: 10px; background: var(--grad-brand); color: white; font-weight: 600; border: 0; font: inherit; font-size: 13px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
.clear-link { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; color: var(--text-soft); font-weight: 600; }
.clear-link:hover { color: #ef4444; }

.hero-stats { display: flex; gap: 16px; margin-top: 22px; flex-wrap: wrap; }
.hstat { display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; border-radius: 999px; background: rgba(255,255,255,.7); backdrop-filter: blur(8px); border: 1px solid var(--border); font-size: 13px; color: var(--text-soft); }
html.dark .hstat { background: rgba(19,24,38,.55); }
.hstat i { color: #10b981; }
.hstat strong { color: var(--text); font-weight: 700; font-size: 15px; }

/* ===== SEARCH ===== */
.search-wrap {
  background: var(--bg-elev); border: 1.5px solid var(--border);
  border-radius: 14px; padding: 12px 16px;
  display: flex; align-items: center; gap: 12px;
  margin-bottom: 28px;
  box-shadow: var(--shadow-sm);
  transition: border-color .2s, box-shadow .2s;
}
.search-wrap:focus-within {
  border-color: var(--brand-1);
  box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.12);
}
.search-wrap i { color: var(--muted); }
.search-wrap input {
  flex: 1; border: none; background: transparent;
  font: inherit; font-size: 15px; color: var(--text); outline: none;
}
.search-wrap .clear {
  padding: 4px 10px; font-size: 12px; color: var(--text-soft);
  background: var(--border); border-radius: 6px; text-decoration: none;
}

/* ===== SECTION LABEL ===== */
.section-label {
  display: flex; align-items: center; gap: 10px;
  margin-bottom: 14px; font-size: 12px; font-weight: 700;
  letter-spacing: 0.08em; text-transform: uppercase; color: var(--text-soft);
}
.section-label::after { content: ''; flex: 1; height: 1px; background: var(--border); }
.section-label .count {
  background: var(--border); color: var(--text); border-radius: 999px;
  padding: 2px 9px; font-size: 11px; font-weight: 700; letter-spacing: 0;
}

/* ===== FOLDER GRID ===== */
.folder-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 16px;
  margin-bottom: 36px;
}
.folder-card {
  background: var(--bg-elev); border: 1px solid var(--border);
  border-radius: var(--radius); overflow: hidden;
  text-decoration: none; color: inherit;
  display: flex; flex-direction: column;
  box-shadow: var(--shadow-sm);
  transition: transform .25s ease, box-shadow .25s ease, border-color .25s;
  animation: fadeUp .35s both;
}
.folder-card:hover {
  transform: translateY(-4px);
  box-shadow: var(--shadow-md);
  border-color: rgba(16, 185, 129, 0.4);
}
.folder-thumb {
  width: 100%; aspect-ratio: 4/3;
  background: linear-gradient(135deg, #d1fae5, #e0f2fe);
  display: grid; place-items: center; position: relative; overflow: hidden;
}
.folder-thumb img { width: 100%; height: 100%; object-fit: cover; }
.folder-thumb .icon { font-size: 44px; color: var(--brand-1); opacity: 0.65; }
.folder-thumb .sub-badge {
  position: absolute; bottom: 8px; right: 8px;
  background: rgba(15, 23, 42, 0.7); color: white;
  padding: 3px 9px; border-radius: 999px; font-size: 11px; font-weight: 600;
  backdrop-filter: blur(4px);
  display: flex; align-items: center; gap: 4px;
}
.folder-label { padding: 12px 14px 14px; border-top: 1px solid var(--border); }
.folder-name {
  font-family: 'Sora', sans-serif; font-size: 14px; font-weight: 600;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.folder-sub { margin-top: 3px; font-size: 11px; color: var(--muted); }

/* ===== FILE RESULT LIST ===== */
.file-list {
  background: var(--bg-elev); border: 1px solid var(--border);
  border-radius: var(--radius); overflow: hidden;
  box-shadow: var(--shadow-sm);
}
.file-row {
  display: flex; align-items: center; gap: 14px;
  padding: 14px 18px;
  border-bottom: 1px solid var(--border);
  text-decoration: none; color: inherit;
  transition: background .15s;
}
.file-row:last-child { border-bottom: none; }
.file-row:hover { background: var(--bg); }
.ftype {
  width: 44px; height: 44px; border-radius: 10px;
  display: grid; place-items: center;
  color: white; font-size: 11px; font-weight: 700;
  flex-shrink: 0;
}
.file-meta { flex: 1; min-width: 0; }
.file-name { font-weight: 600; font-size: 14px; }
.file-desc { color: var(--text-soft); font-size: 12px; margin-top: 2px; }
.file-folder { font-size: 12px; color: var(--brand-1); margin-top: 2px; }
.file-action {
  background: var(--bg); border: 1px solid var(--border);
  border-radius: 8px; padding: 7px 12px;
  font-size: 12px; font-weight: 600; color: var(--text);
}

/* ===== EMPTY STATE ===== */
.empty {
  text-align: center; padding: 70px 20px; color: var(--text-soft);
  background: var(--bg-elev); border: 2px dashed var(--border); border-radius: var(--radius);
}
.empty i { font-size: 48px; opacity: 0.3; margin-bottom: 16px; color: var(--brand-1); }
.empty h3 { font-size: 18px; margin-bottom: 6px; color: var(--text); }

@keyframes fadeUp { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
.folder-card:nth-child(n+3) { animation-delay: .04s; }
.folder-card:nth-child(n+5) { animation-delay: .08s; }
.folder-card:nth-child(n+7) { animation-delay: .12s; }

@media (max-width: 640px) {
  .nav-links { display: none; }
  .folder-grid { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px; }
}
</style>
</head>
<body oncontextmenu="return false" onselectstart="return false" ondragstart="return false">

<!-- ===== NAV ===== -->
<nav class="nav">
  <div class="nav-inner">
    <a href="../index.php" class="brand">
      <div class="brand-mark"><i class="fas fa-cube"></i></div>
      <div class="brand-name">Media<span>Nest</span></div>
    </a>
    <div class="nav-links">
    <a href="../index.php" class="nav-link"><i class="fas fa-house"></i> Home</a>
      <a href="../Videos/index.php" class="nav-link"><i class="fas fa-video"></i> Videos</a>
      <a href="../Photo/index.php" class="nav-link"><i class="fas fa-image"></i> Photos</a>
      <a href="index.php" class="nav-link active"><i class="fas fa-file"></i> Documents</a>
      <a href="../Bookmarks/index.php" class="nav-link"><i class="fas fa-bookmark"></i> Bookmarks</a>
    </div>
    <div class="nav-actions">
      <?php include __DIR__ . '/../auth/notif_bell.php'; ?>
      <button id="theme-toggle" class="icon-btn"><i class="fas fa-moon"></i></button>
      <a href="../auth/logout.php" class="user-chip">
        <span class="av"><?= strtoupper(substr($current_user['full_name'] ?? 'U', 0, 1)) ?></span>
        <span><?= htmlspecialchars(strtok($current_user['full_name'] ?? 'You', ' ')) ?></span>
      </a>
    </div>
  </div>
</nav>

<!-- HERO -->
<section class="hero">
  <div class="hero-inner">
    <div class="breadcrumb">
      <i class="fas fa-folder-open"></i>
      <span>Document Library</span>
    </div>
    <h1>Knowledge at your <span class="gradient-text">fingertips</span></h1>
    <p class="sub">Browse folders, preview files in your browser, or search every document in the library.</p>

    <!-- ===== SEARCH ===== -->
    <form class="hero-search" method="get">
      <div class="search-card">
        <i class="fas fa-search"></i>
        <input type="text" name="q" placeholder="Search folders and files…"
               value="<?= htmlspecialchars($q) ?>" autocomplete="off">
        <button type="submit" class="btn-go"><i class="fas fa-arrow-right"></i></button>
      </div>
      <?php if ($q): ?>
        <a href="index.php" class="clear-link"><i class="fas fa-xmark"></i> Clear search</a>
      <?php endif; ?>
    </form>

    <div class="hero-stats">
      <div class="hstat"><i class="fas fa-folder"></i> <strong><?= (int)$folder_count ?></strong> folders</div>
      <div class="hstat"><i class="fas fa-file"></i> <strong><?= (int)$file_count ?></strong> files</div>
    </div>
  </div>
</section>

<div class="page">

  <?php if ($q !== ''): ?>
    <!-- SEARCH RESULTS MODE -->
    <?php if (!$found_folders && !$found_files): ?>
      <div class="empty">
        <i class="fas fa-magnifying-glass"></i>
        <h3>No matches for "<?= htmlspecialchars($q) ?>"</h3>
        <p>Try a shorter or different keyword.</p>
      </div>
    <?php endif; ?>

    <?php if ($found_folders): ?>
      <div class="section-label">Matching Folders <span class="count"><?= count($found_folders) ?></span></div>
      <div class="folder-grid">
        <?php foreach ($found_folders as $f):
          $fid = (int) $f['albumid'];
          $fname = htmlspecialchars($f['name']);
          $fimg = !empty($f['folder_image']) ? '../admin/' . htmlspecialchars($f['folder_image']) : null;
          $sub = folder_subcount($con, $fid);
        ?>
          <a class="folder-card" href="parent_folder_details.php?id=<?= $fid ?>">
            <div class="folder-thumb">
              <?php if ($fimg): ?><img src="<?= $fimg ?>" alt="<?= $fname ?>">
              <?php else: ?><i class="fas fa-folder icon"></i><?php endif; ?>
              <?php if ($sub > 0): ?>
                <span class="sub-badge"><i class="fas fa-folder"></i> <?= $sub ?></span>
              <?php endif; ?>
            </div>
            <div class="folder-label">
              <div class="folder-name"><?= $fname ?></div>
              <div class="folder-sub">
                <?= $sub > 0 ? "$sub sub-folder" . ($sub > 1 ? 's' : '') : (folder_filecount($con,$fid) . ' files') ?>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($found_files): ?>
      <div class="section-label">Matching Files <span class="count"><?= count($found_files) ?></span></div>
      <div class="file-list">
        <?php foreach ($found_files as $f):
          $ext = strtolower(pathinfo($f['file_path'], PATHINFO_EXTENSION));
          [$cls, $label, $color] = file_type_meta($ext);
          $fid = (int) $f['file_id'];
        ?>
          <a class="file-row" href="view_file.php?file_id=<?= $fid ?>" target="_blank">
            <div class="ftype" style="background: <?= $color ?>;"><?= $label ?></div>
            <div class="file-meta">
              <div class="file-name"><?= htmlspecialchars($f['file_desc'] ?: $f['file_name']) ?></div>
              <?php if (!empty($f['folder_name'])): ?>
                <div class="file-folder"><i class="fas fa-folder" style="font-size:10px;"></i> <?= htmlspecialchars($f['folder_name']) ?></div>
              <?php endif; ?>
            </div>
            <span class="file-action">View</span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  <?php else: ?>
    <!-- DEFAULT MODE: top-level folders -->
    <div class="section-label">All Folders <span class="count"><?= count($folders) ?></span></div>
    <?php if (!$folders): ?>
      <div class="empty">
        <i class="fas fa-folder-open"></i>
        <h3>No folders yet</h3>
        <p>An administrator can create the first folder from the admin panel.</p>
      </div>
    <?php else: ?>
      <div class="folder-grid">
        <?php foreach ($folders as $f):
          $fid = (int) $f['albumid'];
          $fname = htmlspecialchars($f['name']);
          $fimg = !empty($f['folder_image']) ? '../admin/' . htmlspecialchars($f['folder_image']) : null;
          $sub = folder_subcount($con, $fid);
          $files = folder_filecount($con, $fid);
        ?>
          <a class="folder-card" href="parent_folder_details.php?id=<?= $fid ?>">
            <div class="folder-thumb">
              <?php if ($fimg): ?><img src="<?= $fimg ?>" alt="<?= $fname ?>">
              <?php else: ?><i class="fas fa-folder icon"></i><?php endif; ?>
              <?php if ($sub > 0): ?>
                <span class="sub-badge"><i class="fas fa-folder"></i> <?= $sub ?></span>
              <?php endif; ?>
            </div>
            <div class="folder-label">
              <div class="folder-name"><?= $fname ?></div>
              <div class="folder-sub">
                <?= $sub > 0 ? "$sub sub-folder" . ($sub > 1 ? 's' : '') : '' ?>
                <?= $sub > 0 && $files > 0 ? ' · ' : '' ?>
                <?= $files > 0 ? "$files file" . ($files > 1 ? 's' : '') : ($sub === 0 ? 'Empty' : '') ?>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>

</div>

<script>
const themeBtn = document.getElementById('theme-toggle');
const themeIcon = themeBtn.querySelector('i');
if (localStorage.getItem('mn-theme') === 'dark') {
  document.documentElement.classList.add('dark');
  themeIcon.classList.replace('fa-moon', 'fa-sun');
}
themeBtn.addEventListener('click', () => {
  const dark = document.documentElement.classList.toggle('dark');
  themeIcon.classList.toggle('fa-moon', !dark);
  themeIcon.classList.toggle('fa-sun', dark);
  localStorage.setItem('mn-theme', dark ? 'dark' : 'light');
});

// Live search: auto-submit 400ms after user stops typing
const searchInput = document.querySelector('input[name="q"]');
let typeTimer;
searchInput.addEventListener('input', () => {
  clearTimeout(typeTimer);
  typeTimer = setTimeout(() => searchInput.form.submit(), 400);
});
</script>
</body>
</html>