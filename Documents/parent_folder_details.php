<?php
/**
 * MediaNest — Document Folder Detail
 * --------------------------------------------------------------
 * - Unified auth gate.
 * - Shows sub-folders + files for one folder.
 * - Search bar filters files (and sub-folders) within this scope only.
 * - Type-aware file badges.
 */
require_once __DIR__ . '/../auth/auth.php';
requireLogin();

$con = mysqli_connect('localhost', 'root', '', 's&p');
if (!$con) die('DB connection failed: ' . mysqli_connect_error());

$current_user = currentUser();

if (!isset($_GET['id'])) { header('Location: index.php'); exit; }
$parentFolderId = (int) $_GET['id'];
$q = trim($_GET['q'] ?? '');

$stmt = mysqli_prepare($con, "SELECT * FROM folders WHERE albumid = ?");
mysqli_stmt_bind_param($stmt, 'i', $parentFolderId);
mysqli_stmt_execute($stmt);
$folderRow  = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$folderRow) { header('Location: index.php'); exit; }
$folderName = htmlspecialchars($folderRow['name'] ?? 'Folder');
$folderDesc = htmlspecialchars($folderRow['adesc'] ?? '');

// Breadcrumb trail (safe against orphaned parents)
$breadcrumbs = [];
$checkId = $parentFolderId;
$guard = 0;
while ($checkId && $guard++ < 20) {
    $s = mysqli_prepare($con, "SELECT albumid, name, parent_folder_id FROM folders WHERE albumid = ?");
    mysqli_stmt_bind_param($s, 'i', $checkId);
    mysqli_stmt_execute($s);
    $f = mysqli_fetch_assoc(mysqli_stmt_get_result($s));
    if (!$f) break;
    array_unshift($breadcrumbs, ['id' => (int)$f['albumid'], 'name' => htmlspecialchars($f['name'])]);
    $checkId = $f['parent_folder_id'];
}

// Sub-folders (with optional name filter)
if ($q !== '') {
    $like = '%' . $q . '%';
    $stmt = mysqli_prepare($con,
        "SELECT * FROM folders WHERE parent_folder_id = ? AND name LIKE ? ORDER BY name ASC"
    );
    mysqli_stmt_bind_param($stmt, 'is', $parentFolderId, $like);
} else {
    $stmt = mysqli_prepare($con,
        "SELECT * FROM folders WHERE parent_folder_id = ? ORDER BY name ASC"
    );
    mysqli_stmt_bind_param($stmt, 'i', $parentFolderId);
}
mysqli_stmt_execute($stmt);
$subRes = mysqli_stmt_get_result($stmt);
$subs = [];
while ($r = mysqli_fetch_assoc($subRes)) $subs[] = $r;

// Files (with optional file_name / desc filter)
if ($q !== '') {
    $like = '%' . $q . '%';
    $stmt = mysqli_prepare($con,
        "SELECT * FROM files WHERE folder_id = ? AND (file_name LIKE ? OR file_desc LIKE ?) ORDER BY file_id DESC"
    );
    mysqli_stmt_bind_param($stmt, 'iss', $parentFolderId, $like, $like);
} else {
    $stmt = mysqli_prepare($con,
        "SELECT * FROM files WHERE folder_id = ? ORDER BY file_id DESC"
    );
    mysqli_stmt_bind_param($stmt, 'i', $parentFolderId);
}
mysqli_stmt_execute($stmt);
$filesRes = mysqli_stmt_get_result($stmt);
$files = [];
while ($r = mysqli_fetch_assoc($filesRes)) $files[] = $r;

function ftype_meta($ext) {
    $ext = strtolower($ext);
    if ($ext === 'pdf')                                  return ['pdf',   'PDF',   '#dc2626'];
    if (in_array($ext, ['doc','docx','odt']))            return ['word',  'Word',  '#2563eb'];
    if (in_array($ext, ['xls','xlsx','csv','ods']))      return ['excel', 'Excel', '#059669'];
    if (in_array($ext, ['ppt','pptx','odp']))            return ['ppt',   'PPT',   '#ea580c'];
    if (in_array($ext, ['jpg','jpeg','png','gif','webp','svg','bmp'])) return ['img', strtoupper($ext), '#7c3aed'];
    if (in_array($ext, ['mp4','webm','ogg','mov']))      return ['vid',   'Video', '#0891b2'];
    return ['other', strtoupper($ext ?: 'FILE'), '#6b7280'];
}

function folder_subcount2($con, $id) {
    $s = mysqli_prepare($con, "SELECT COUNT(*) FROM folders WHERE parent_folder_id = ?");
    mysqli_stmt_bind_param($s, 'i', $id);
    mysqli_stmt_execute($s);
    return (int) (mysqli_fetch_row(mysqli_stmt_get_result($s))[0] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $folderName ?> — MediaNest</title>

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
  --radius: 16px;
  --grad-brand: linear-gradient(135deg, #10b981, #059669);
  --grad-text:  linear-gradient(135deg, #10b981, #0ea5e9 50%, #6366f1);
}
html.dark {
  --bg: #0a0e1a; --bg-elev: #131826;
  --text: #e2e8f0; --text-soft: #cbd5e1; --muted: #64748b;
  --border: rgba(255, 255, 255, 0.08);
}
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Inter', sans-serif; color: var(--text); background: var(--bg); min-height: 100vh; }
h1, h2 { font-family: 'Sora', sans-serif; letter-spacing: -0.02em; }
a { color: inherit; }

/* NAV */
.nav { position: sticky; top: 0; z-index: 50; background: rgba(246,247,251,0.85); backdrop-filter: blur(12px); border-bottom: 1px solid var(--border); }
html.dark .nav { background: rgba(10,14,26,0.85); }
.nav-inner { max-width: 1280px; margin: 0 auto; padding: 14px 24px; display: flex; align-items: center; justify-content: space-between; gap: 16px; }
.brand { display: flex; align-items: center; gap: 10px; text-decoration: none; color: var(--text); }
.brand-mark { width: 36px; height: 36px; border-radius: 10px; background: var(--grad-brand); color: white; display: grid; place-items: center; }
.brand-name { font-family: 'Sora', sans-serif; font-weight: 700; font-size: 18px; }
.brand-name span { background: var(--grad-text); -webkit-background-clip: text; background-clip: text; color: transparent; }
.nav-links { display: flex; gap: 4px; }
.nav-link { padding: 8px 14px; border-radius: 10px; font-size: 14px; font-weight: 500; color: var(--text-soft); text-decoration: none; }
.nav-link:hover, .nav-link.active { background: var(--border); color: var(--text); }
.nav-actions { display: flex; align-items: center; gap: 10px; }
.icon-btn { width: 38px; height: 38px; border-radius: 10px; background: var(--bg-elev); border: 1px solid var(--border); color: var(--text); cursor: pointer; display: grid; place-items: center; }
.user-chip { display: flex; align-items: center; gap: 8px; padding: 6px 12px 6px 6px; background: var(--bg-elev); border: 1px solid var(--border); border-radius: 999px; font-size: 13px; font-weight: 500; text-decoration: none; color: var(--text); }
.user-chip .av { width: 28px; height: 28px; border-radius: 50%; background: var(--grad-brand); color: white; display: grid; place-items: center; font-size: 11px; font-weight: 700; }

/* PAGE */
.page { max-width: 1280px; margin: 0 auto; padding: 32px 24px 80px; }

/* HERO (emerald theme) */
.hero { background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 50%, #e0f2fe 100%); border-bottom: 1px solid var(--border); padding: 40px 24px 36px; position: relative; overflow: hidden; }
.hero::before { content: ''; position: absolute; top: -100px; right: -80px; width: 320px; height: 320px; border-radius: 50%; background: radial-gradient(circle, rgba(16,185,129,.2), transparent 70%); }
html.dark .hero { background: linear-gradient(135deg, #042f2e 0%, #0a3d34 50%, #0b2438 100%); }
html.dark .hero::before { background: radial-gradient(circle, rgba(16,185,129,.12), transparent 70%); }
.hero-inner { max-width: 1280px; margin: 0 auto; position: relative; z-index: 1; }
.hbreadcrumb { display: inline-flex; align-items: center; gap: 8px; padding: 6px 14px; border-radius: 999px; background: rgba(255,255,255,.7); backdrop-filter: blur(8px); border: 1px solid var(--border); font-size: 12px; font-weight: 600; color: var(--text-soft); margin-bottom: 16px; flex-wrap: wrap; }
html.dark .hbreadcrumb { background: rgba(19,24,38,.7); }
.hbreadcrumb a { color: var(--brand-1); }
.hbreadcrumb a:hover { text-decoration: underline; }
.hbreadcrumb .sep { font-size: 8px; opacity: .5; }
.hbreadcrumb .current { color: var(--text); font-weight: 700; }
.hero h1 { font-size: clamp(24px, 3.5vw, 34px); font-weight: 800; line-height: 1.15; margin-bottom: 8px; display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
.hero p.sub { font-size: clamp(13px, 1.2vw, 15px); color: var(--text-soft); max-width: 640px; line-height: 1.6; }
.hero-stats { display: flex; gap: 12px; margin-top: 20px; flex-wrap: wrap; }
.hstat { display: inline-flex; align-items: center; gap: 8px; padding: 7px 14px; border-radius: 999px; background: rgba(255,255,255,.7); backdrop-filter: blur(8px); border: 1px solid var(--border); font-size: 12px; color: var(--text-soft); }
html.dark .hstat { background: rgba(19,24,38,.55); }
.hstat i { color: #10b981; }
.hstat strong { color: var(--text); font-weight: 700; font-size: 14px; }

/* SEARCH */
.search-wrap {
  background: var(--bg-elev); border: 1.5px solid var(--border);
  border-radius: 14px; padding: 12px 16px;
  display: flex; align-items: center; gap: 12px;
  margin-bottom: 28px;
  box-shadow: var(--shadow-sm);
}
.search-wrap:focus-within { border-color: var(--brand-1); box-shadow: 0 0 0 4px rgba(16,185,129,0.12); }
.search-wrap i { color: var(--muted); }
.search-wrap input { flex: 1; border: none; background: transparent; font: inherit; font-size: 15px; color: var(--text); outline: none; }
.search-wrap .clear { padding: 4px 10px; font-size: 12px; color: var(--text-soft); background: var(--border); border-radius: 6px; text-decoration: none; }

.section-label {
  display: flex; align-items: center; gap: 10px;
  margin-bottom: 14px; font-size: 12px; font-weight: 700;
  letter-spacing: 0.08em; text-transform: uppercase; color: var(--text-soft);
}
.section-label::after { content: ''; flex: 1; height: 1px; background: var(--border); }
.section-label .count { background: var(--border); color: var(--text); border-radius: 999px; padding: 2px 9px; font-size: 11px; font-weight: 700; letter-spacing: 0; }

/* SUB-FOLDER GRID */
.folder-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 16px; margin-bottom: 36px; }
.folder-card { background: var(--bg-elev); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; text-decoration: none; color: inherit; box-shadow: var(--shadow-sm); transition: transform .25s ease, box-shadow .25s ease, border-color .25s; }
.folder-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-md); border-color: rgba(16,185,129,0.4); }
.folder-thumb { width: 100%; aspect-ratio: 4/3; background: linear-gradient(135deg, #d1fae5, #e0f2fe); display: grid; place-items: center; position: relative; overflow: hidden; }
.folder-thumb img { width: 100%; height: 100%; object-fit: cover; }
.folder-thumb .icon { font-size: 40px; color: var(--brand-1); opacity: 0.65; }
.folder-thumb .sub-badge { position: absolute; bottom: 8px; right: 8px; background: rgba(15, 23, 42, 0.7); color: white; padding: 3px 9px; border-radius: 999px; font-size: 11px; font-weight: 600; backdrop-filter: blur(4px); }
.folder-label { padding: 12px 14px 14px; border-top: 1px solid var(--border); }
.folder-name { font-family: 'Sora', sans-serif; font-size: 14px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.folder-sub { margin-top: 3px; font-size: 11px; color: var(--muted); }

/* FILE TABLE */
.files-wrap { background: var(--bg-elev); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; box-shadow: var(--shadow-sm); }
.files-wrap table { width: 100%; border-collapse: collapse; }
.files-wrap th { text-align: left; font-size: 11px; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: var(--text-soft); padding: 14px 18px; border-bottom: 1px solid var(--border); }
.files-wrap td { padding: 14px 18px; border-bottom: 1px solid var(--border); font-size: 14px; }
.files-wrap tr:last-child td { border-bottom: none; }
.files-wrap tr:hover { background: var(--bg); }
.ftype-pill { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; color: white; }
.btn-view { display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 8px; background: var(--bg); border: 1px solid var(--border); font-size: 12px; font-weight: 600; color: var(--text); text-decoration: none; }
.btn-view:hover { border-color: var(--brand-1); color: var(--brand-1); }
.btn-video { display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 8px; background: var(--grad-brand); color: white; font-size: 12px; font-weight: 600; text-decoration: none; }
.btn-none { color: var(--muted); font-size: 12px; }

.empty { text-align: center; padding: 60px 20px; color: var(--text-soft); background: var(--bg-elev); border: 2px dashed var(--border); border-radius: var(--radius); }
.empty i { font-size: 48px; opacity: 0.3; margin-bottom: 14px; color: var(--brand-1); }
.empty h3 { font-size: 17px; margin-bottom: 4px; color: var(--text); }

@media (max-width: 640px) {
  .nav-links { display: none; }
  .folder-grid { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px; }
  .files-wrap th:nth-child(5), .files-wrap td:nth-child(5) { display: none; }
}
</style>
</head>
<body oncontextmenu="return false" onselectstart="return false" ondragstart="return false">

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
    <div class="hbreadcrumb">
      <a href="../index.php"><i class="fas fa-house"></i> Home</a>
      <i class="fas fa-chevron-right sep"></i>
      <a href="index.php">Documents</a>
      <?php foreach ($breadcrumbs as $i => $b):
        $isLast = $i === count($breadcrumbs) - 1; ?>
        <i class="fas fa-chevron-right sep"></i>
        <?php if ($isLast): ?>
          <span class="current"><?= $b['name'] ?></span>
        <?php else: ?>
          <a href="?id=<?= $b['id'] ?>"><?= $b['name'] ?></a>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
    <h1><i class="fas fa-folder-open" style="color:#10b981;"></i> <?= $folderName ?></h1>
    <?php if ($folderDesc): ?>
      <p class="sub"><?= nl2br($folderDesc) ?></p>
    <?php endif; ?>
    <div class="hero-stats">
      <div class="hstat"><i class="fas fa-folder"></i> <strong><?= count($subs) ?></strong> subfolder<?= count($subs) === 1 ? '' : 's' ?></div>
      <div class="hstat"><i class="fas fa-file"></i> <strong><?= count($files) ?></strong> file<?= count($files) === 1 ? '' : 's' ?></div>
    </div>
  </div>
</section>

<div class="page">

  <!-- ===== INTRA-FOLDER SEARCH ===== -->
  <form class="search-wrap" method="get">
    <input type="hidden" name="id" value="<?= $parentFolderId ?>">
    <i class="fas fa-search"></i>
    <input type="text" name="q" placeholder="Search inside this folder…"
           value="<?= htmlspecialchars($q) ?>" autocomplete="off">
    <?php if ($q): ?>
      <a href="?id=<?= $parentFolderId ?>" class="clear">Clear</a>
    <?php endif; ?>
  </form>

  <?php if ($subs): ?>
    <div class="section-label">Sub-Folders <span class="count"><?= count($subs) ?></span></div>
    <div class="folder-grid">
    <?php foreach ($subs as $sub):
      $subId   = (int) $sub['albumid'];
      $subName = htmlspecialchars($sub['name']);
      $subImg  = !empty($sub['folder_image']) ? '../admin/' . htmlspecialchars($sub['folder_image']) : null;
      $nested  = folder_subcount2($con, $subId);
    ?>
      <a class="folder-card" href="?id=<?= $subId ?>">
        <div class="folder-thumb">
          <?php if ($subImg): ?><img src="<?= $subImg ?>" alt="<?= $subName ?>">
          <?php else: ?><i class="fas fa-folder icon"></i><?php endif; ?>
          <?php if ($nested > 0): ?>
            <span class="sub-badge"><i class="fas fa-folder"></i> <?= $nested ?></span>
          <?php endif; ?>
        </div>
        <div class="folder-label">
          <div class="folder-name"><?= $subName ?></div>
          <div class="folder-sub">
            <?= $nested > 0 ? "$nested sub-folder" . ($nested > 1 ? 's' : '') : 'No sub-folders' ?>
          </div>
        </div>
      </a>
    <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($files): ?>
    <div class="section-label">Files <span class="count"><?= count($files) ?></span></div>
    <div class="files-wrap">
      <table>
        <thead>
          <tr><th>Type</th><th>Title</th><th>Filename</th><th>View</th><th>Video</th></tr>
        </thead>
        <tbody>
        <?php foreach ($files as $file):
          $fid       = (int) $file['file_id'];
          $fileDesc  = htmlspecialchars($file['file_desc'] ?? '') ?: htmlspecialchars($file['file_name']);
          $videoLink = $file['video_link'] ?? '';
          $ext       = strtolower(pathinfo($file['file_path'], PATHINFO_EXTENSION));
          [$cls, $label, $color] = ftype_meta($ext);
        ?>
          <tr>
            <td><span class="ftype-pill" style="background: <?= $color ?>;"><?= $label ?></span></td>
            <td><?= $fileDesc ?></td>
            <td style="color: var(--text-soft); font-size: 12px;"><?= htmlspecialchars($file['file_name']) ?></td>
            <td>
              <a class="btn-view" href="view_file.php?file_id=<?= $fid ?>" target="_blank">
                <i class="fas fa-eye"></i> View
              </a>
            </td>
            <td>
              <?php if (!empty($videoLink)): ?>
                <a class="btn-video" href="<?= htmlspecialchars($videoLink) ?>" target="_blank">
                  <i class="fas fa-play"></i> Watch
                </a>
              <?php else: ?>
                <span class="btn-none">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <?php if (!$subs && !$files): ?>
    <div class="empty">
      <i class="fas <?= $q ? 'fa-magnifying-glass' : 'fa-folder-open' ?>"></i>
      <h3><?= $q ? 'No matches in this folder' : 'This folder is empty' ?></h3>
      <p><?= $q ? 'Try a different keyword or clear the search.' : 'Sub-folders and files will appear here once added.' ?></p>
    </div>
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

const searchInput = document.querySelector('input[name="q"]');
let typeTimer;
searchInput.addEventListener('input', () => {
  clearTimeout(typeTimer);
  typeTimer = setTimeout(() => searchInput.form.submit(), 400);
});
</script>
</body>
</html>