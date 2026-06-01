<?php
/**
 * MediaNest — My Bookmarks page
 * --------------------------------------------------------------
 * Lists all bookmarks for the current user, grouped by type.
 * Tabs: All · Videos · Albums · Files
 */
require_once __DIR__ . '/../auth/auth.php';
requireLogin();
$conn = mysqli_connect('localhost', 'root', '', 's&p');
$user = currentUser();
$uid  = (int)$user['id'];

$tab = $_GET['tab'] ?? 'all';
$VALID = ['all','video','album','file'];
if (!in_array($tab, $VALID, true)) $tab = 'all';

// Count per type
function _bm_type_count($conn, $uid, $type) {
    $s = mysqli_prepare($conn, "SELECT COUNT(*) FROM bookmarks WHERE user_id=? AND item_type=?");
    mysqli_stmt_bind_param($s, 'is', $uid, $type);
    mysqli_stmt_execute($s);
    $r = mysqli_fetch_row(mysqli_stmt_get_result($s));
    mysqli_stmt_close($s);
    return (int)($r[0] ?? 0);
}
$counts = [
    'video' => _bm_type_count($conn, $uid, 'video'),
    'album' => _bm_type_count($conn, $uid, 'album'),
    'file'  => _bm_type_count($conn, $uid, 'file'),
];
$counts['all'] = $counts['video'] + $counts['album'] + $counts['file'];

// Resolve bookmarked items (joined by type)
function load_bookmarks($conn, $uid, $tab) {
    $items = [];
    $types = $tab === 'all' ? ['video','album','file'] : [$tab];

    if (in_array('video', $types)) {
        $s = mysqli_prepare($conn,
            "SELECT b.id AS bm_id, b.item_type, b.created_at, v.id, v.title, v.des, v.name, c.name AS cat_name
             FROM bookmarks b JOIN video v ON v.id = b.item_id
             LEFT JOIN video_categories c ON c.id = v.category_id
             WHERE b.user_id=? AND b.item_type='video'
             ORDER BY b.created_at DESC");
        mysqli_stmt_bind_param($s, 'i', $uid);
        mysqli_stmt_execute($s);
        $r = mysqli_stmt_get_result($s);
        while ($row = mysqli_fetch_assoc($r)) $items[] = $row;
        mysqli_stmt_close($s);
    }
    if (in_array('album', $types)) {
        $s = mysqli_prepare($conn,
            "SELECT b.id AS bm_id, b.item_type, b.created_at, a.albumid AS id, a.name AS title, a.adesc AS des, a.image,
                    (SELECT COUNT(*) FROM tbl_gallery g WHERE g.aid=a.albumid AND g.status='process') AS pc
             FROM bookmarks b JOIN tbl_album a ON a.albumid = b.item_id
             WHERE b.user_id=? AND b.item_type='album'
             ORDER BY b.created_at DESC");
        mysqli_stmt_bind_param($s, 'i', $uid);
        mysqli_stmt_execute($s);
        $r = mysqli_stmt_get_result($s);
        while ($row = mysqli_fetch_assoc($r)) $items[] = $row;
        mysqli_stmt_close($s);
    }
    if (in_array('file', $types)) {
        $s = mysqli_prepare($conn,
            "SELECT b.id AS bm_id, b.item_type, b.created_at, f.file_id AS id, f.file_name AS name, f.file_desc AS title, fo.name AS folder_name
             FROM bookmarks b JOIN files f ON f.file_id = b.item_id
             LEFT JOIN folders fo ON fo.albumid = f.folder_id
             WHERE b.user_id=? AND b.item_type='file'
             ORDER BY b.created_at DESC");
        mysqli_stmt_bind_param($s, 'i', $uid);
        mysqli_stmt_execute($s);
        $r = mysqli_stmt_get_result($s);
        while ($row = mysqli_fetch_assoc($r)) $items[] = $row;
        mysqli_stmt_close($s);
    }

    // Sort all by created_at desc when in "all" tab
    usort($items, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));
    return $items;
}
$items = load_bookmarks($conn, $uid, $tab);

function file_type_meta_b($ext) {
    $ext = strtolower($ext);
    if ($ext === 'pdf')                          return ['PDF',   '#ef4444', 'fa-file-pdf'];
    if (in_array($ext, ['doc','docx']))          return ['DOC',   '#0ea5e9', 'fa-file-word'];
    if (in_array($ext, ['xls','xlsx','csv']))    return ['XLS',   '#10b981', 'fa-file-excel'];
    if (in_array($ext, ['ppt','pptx']))          return ['PPT',   '#f59e0b', 'fa-file-powerpoint'];
    if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) return ['IMG',   '#a855f7', 'fa-file-image'];
    if (in_array($ext, ['mp4','mov','mkv','avi'])) return ['VID',   '#06b6d4', 'fa-file-video'];
    return [strtoupper($ext) ?: 'FILE', '#64748b', 'fa-file'];
}
$first_name = explode(' ', $user['full_name'] ?? $user['email'])[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Bookmarks — MediaNest</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/js/all.min.js" defer></script>

<style>
:root {
  --bg: #f6f7fb; --bg-elev: #fff; --text: #0f172a; --text-soft: #475569; --muted: #94a3b8;
  --border: rgba(15,23,42,.08);
  --brand-1: #f59e0b; --brand-2: #d97706;
  --grad-brand: linear-gradient(135deg, #f59e0b, #d97706);
  --grad-text: linear-gradient(135deg, #f59e0b, #ef4444 50%, #f97316);
  --shadow-sm: 0 2px 8px rgba(15,23,42,.06);
  --shadow-md: 0 10px 30px rgba(15,23,42,.08);
}
html.dark { --bg:#0a0e1a; --bg-elev:#131826; --text:#e2e8f0; --text-soft:#cbd5e1; --muted:#64748b; --border:rgba(255,255,255,.08); }
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Inter', sans-serif; color: var(--text); background: var(--bg); min-height: 100vh; transition: background .3s, color .3s; }
h1, h2, h3 { font-family: 'Sora', sans-serif; letter-spacing: -.02em; }
a { color: inherit; text-decoration: none; }

.nav { position: sticky; top: 0; z-index: 50; backdrop-filter: blur(14px); background: color-mix(in srgb, var(--bg) 78%, transparent); border-bottom: 1px solid var(--border); }
.nav-inner { max-width: 1280px; margin: 0 auto; padding: 14px 24px; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
.logo { display: flex; align-items: center; gap: 10px; font-family: 'Sora',sans-serif; font-weight: 700; font-size: 19px; }
.logo-mark { width: 36px; height: 36px; border-radius: 10px; background: var(--grad-brand); color: white; display: grid; place-items: center; box-shadow: 0 6px 20px rgba(245,158,11,.3); }
.logo-text span { background: var(--grad-text); -webkit-background-clip: text; background-clip: text; color: transparent; }
.nav-links { display: flex; gap: 4px; flex-wrap: wrap; }
.nav-links a { padding: 8px 14px; border-radius: 8px; font-size: 13px; font-weight: 500; color: var(--text-soft); display: inline-flex; align-items: center; gap: 7px; transition: all .2s; }
.nav-links a:hover { background: var(--bg-elev); color: var(--text); }
.nav-links a.active { background: var(--grad-brand); color: white; }
.nav-right { display: flex; align-items: center; gap: 8px; }
.icon-btn { width: 38px; height: 38px; border-radius: 10px; background: transparent; border: 1px solid var(--border); color: var(--text); display: grid; place-items: center; transition: all .2s; }
.icon-btn:hover { background: var(--bg-elev); }

.page { max-width: 1280px; margin: 0 auto; padding: 28px 24px 60px; }

/* HERO (amber/gold theme matching bookmark star color) */
.hero { background: linear-gradient(135deg, #fef3c7 0%, #fed7aa 50%, #fee2e2 100%); border-bottom: 1px solid var(--border); padding: 44px 24px 40px; position: relative; overflow: hidden; }
.hero::before { content: ''; position: absolute; top: -100px; right: -80px; width: 320px; height: 320px; border-radius: 50%; background: radial-gradient(circle, rgba(245,158,11,.22), transparent 70%); }
html.dark .hero { background: linear-gradient(135deg, #1f1410 0%, #2a1a14 50%, #1a0f0e 100%); }
html.dark .hero::before { background: radial-gradient(circle, rgba(245,158,11,.15), transparent 70%); }
.hero-inner { max-width: 1280px; margin: 0 auto; position: relative; z-index: 1; }
.hbreadcrumb { display: inline-flex; align-items: center; gap: 8px; padding: 6px 14px; border-radius: 999px; background: rgba(255,255,255,.7); backdrop-filter: blur(8px); border: 1px solid var(--border); font-size: 12px; font-weight: 600; color: var(--text-soft); margin-bottom: 16px; }
html.dark .hbreadcrumb { background: rgba(19,24,38,.7); }
.hbreadcrumb i { color: #f59e0b; }
.hero h1 { font-size: clamp(28px, 4.5vw, 42px); font-weight: 800; line-height: 1.1; margin-bottom: 10px; max-width: 720px; }
.hero h1 .grad { background: var(--grad-text); -webkit-background-clip: text; background-clip: text; color: transparent; }
.hero p.sub { font-size: clamp(14px, 1.3vw, 16px); color: var(--text-soft); max-width: 600px; }
.hero-stats { display: flex; gap: 12px; margin-top: 20px; flex-wrap: wrap; }
.hstat { display: inline-flex; align-items: center; gap: 8px; padding: 7px 14px; border-radius: 999px; background: rgba(255,255,255,.7); backdrop-filter: blur(8px); border: 1px solid var(--border); font-size: 12px; color: var(--text-soft); }
html.dark .hstat { background: rgba(19,24,38,.55); }
.hstat i { color: #f59e0b; }
.hstat strong { color: var(--text); font-weight: 700; font-size: 14px; }

.bm-tabs { display: flex; gap: 4px; padding: 5px; background: var(--bg-elev); border: 1px solid var(--border); border-radius: 13px; margin-bottom: 22px; overflow-x: auto; }
.bm-tab { padding: 9px 14px; border-radius: 9px; font-size: 13px; font-weight: 600; color: var(--text-soft); display: inline-flex; align-items: center; gap: 7px; cursor: pointer; transition: all .15s; }
.bm-tab:hover { background: var(--bg); color: var(--text); }
.bm-tab.active { background: var(--grad-brand); color: white; box-shadow: 0 6px 18px rgba(245,158,11,.3); }
.bm-tab .n { padding: 1px 8px; border-radius: 999px; background: rgba(255,255,255,.2); font-size: 11px; font-weight: 700; }
.bm-tab:not(.active) .n { background: var(--bg); color: var(--text-soft); }

.bm-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; }
.bm-card { background: var(--bg-elev); border: 1px solid var(--border); border-radius: 14px; overflow: hidden; transition: all .2s; }
.bm-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); border-color: rgba(245,158,11,.35); }
.bm-thumb { position: relative; aspect-ratio: 16/9; background: #000; overflow: hidden; }
.bm-thumb video, .bm-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
.bm-thumb-icon { width: 100%; height: 100%; display: grid; place-items: center; color: white; font-size: 38px; }
.bm-type-pill { position: absolute; top: 10px; left: 10px; padding: 3px 10px; border-radius: 999px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; backdrop-filter: blur(6px); }
.bm-type-pill.video { background: linear-gradient(135deg, #0ea5e9, #6366f1); color: white; }
.bm-type-pill.album { background: linear-gradient(135deg, #ec4899, #f43f5e); color: white; }
.bm-type-pill.file  { background: linear-gradient(135deg, #10b981, #059669); color: white; }
.bm-body { padding: 13px 15px; }
.bm-title { font-weight: 700; font-size: 14px; line-height: 1.3; margin-bottom: 4px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.bm-meta { font-size: 11px; color: var(--text-soft); display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
.bm-cat-pill { padding: 1px 7px; border-radius: 999px; background: rgba(99,102,241,.1); color: #6366f1; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; font-size: 9px; }
.bm-foot { display: flex; align-items: center; gap: 8px; padding: 0 14px 14px; }
.bm-foot .ago { font-size: 11px; color: var(--muted); flex: 1; }

.empty { text-align: center; padding: 80px 20px; background: var(--bg-elev); border: 1px dashed var(--border); border-radius: 16px; }
.empty .ic { width: 72px; height: 72px; border-radius: 50%; background: linear-gradient(135deg, rgba(245,158,11,.15), rgba(239,68,68,.1)); color: #f59e0b; display: grid; place-items: center; font-size: 28px; margin: 0 auto 16px; }
.empty h3 { font-size: 18px; margin-bottom: 6px; }
.empty p { color: var(--text-soft); font-size: 14px; max-width: 380px; margin: 0 auto; line-height: 1.5; }
.empty a { color: var(--brand-1); font-weight: 600; }
</style>
</head>
<body>

<nav class="nav">
  <div class="nav-inner">
    <a class="logo" href="../index.php">
      <span class="logo-mark"><i class="fas fa-bookmark"></i></span>
      <span class="logo-text">Media<span>Nest</span></span>
    </a>
    <div class="nav-links">
      <a href="../index.php"><i class="fas fa-house"></i> Home</a>
      <a href="../Videos/index.php"><i class="fas fa-video"></i> Videos</a>
      <a href="../Photo/index.php"><i class="fas fa-images"></i> Photos</a>
      <a href="../Documents/index.php"><i class="fas fa-folder-open"></i> Documents</a>
      <a class="active"><i class="fas fa-bookmark"></i> Bookmarks</a>
    </div>
    <div class="nav-right">
      <?php include __DIR__ . '/../auth/notif_bell.php'; ?>
      <button class="icon-btn" id="theme-toggle" aria-label="Toggle theme"><i class="fas fa-moon"></i></button>
      <a href="../auth/logout.php" class="icon-btn" title="Sign out"><i class="fas fa-arrow-right-from-bracket"></i></a>
    </div>
  </div>
</nav>

<!-- HERO -->
<section class="hero">
  <div class="hero-inner">
    <div class="hbreadcrumb">
      <i class="fas fa-bookmark"></i>
      <span>My Bookmarks</span>
    </div>
    <h1>My <span class="grad">Bookmarks</span></h1>
    <p class="sub">Hi <?php echo htmlspecialchars($first_name); ?> — everything you've starred, all in one place.</p>
    <div class="hero-stats">
      <div class="hstat"><i class="fas fa-bookmark"></i> <strong><?php echo (int)$counts['all']; ?></strong> total</div>
      <?php if ($counts['video'] > 0): ?><div class="hstat"><i class="fas fa-video"></i> <strong><?php echo (int)$counts['video']; ?></strong> video<?php echo $counts['video']===1?'':'s'; ?></div><?php endif; ?>
      <?php if ($counts['album'] > 0): ?><div class="hstat"><i class="fas fa-images"></i> <strong><?php echo (int)$counts['album']; ?></strong> album<?php echo $counts['album']===1?'':'s'; ?></div><?php endif; ?>
      <?php if ($counts['file']  > 0): ?><div class="hstat"><i class="fas fa-file"></i> <strong><?php echo (int)$counts['file']; ?></strong> file<?php echo $counts['file']===1?'':'s'; ?></div><?php endif; ?>
    </div>
  </div>
</section>

<div class="page">

  <div class="bm-tabs">
    <a class="bm-tab <?php echo $tab==='all'?'active':''; ?>" href="?tab=all">
      <i class="fas fa-layer-group"></i> All <span class="n"><?php echo $counts['all']; ?></span>
    </a>
    <a class="bm-tab <?php echo $tab==='video'?'active':''; ?>" href="?tab=video">
      <i class="fas fa-video"></i> Videos <span class="n"><?php echo $counts['video']; ?></span>
    </a>
    <a class="bm-tab <?php echo $tab==='album'?'active':''; ?>" href="?tab=album">
      <i class="fas fa-images"></i> Albums <span class="n"><?php echo $counts['album']; ?></span>
    </a>
    <a class="bm-tab <?php echo $tab==='file'?'active':''; ?>" href="?tab=file">
      <i class="fas fa-file"></i> Files <span class="n"><?php echo $counts['file']; ?></span>
    </a>
  </div>

  <?php if (empty($items)): ?>
    <div class="empty">
      <div class="ic"><i class="fas fa-bookmark"></i></div>
      <h3>Nothing bookmarked yet</h3>
      <p>Tap the star icon on any video, photo album, or document to save it here for quick access. They'll all appear in this list.</p>
    </div>
  <?php else: ?>
    <div class="bm-grid">
      <?php foreach ($items as $it):
        $type = $it['item_type'];
        $created_ago = (function($at) {
          $diff = time() - strtotime($at);
          if ($diff < 60) return 'just now';
          if ($diff < 3600) return floor($diff / 60) . 'm ago';
          if ($diff < 86400) return floor($diff / 3600) . 'h ago';
          if ($diff < 604800) return floor($diff / 86400) . 'd ago';
          return date('M j', strtotime($at));
        })($it['created_at']);
      ?>
        <div class="bm-card">
          <?php if ($type === 'video'): ?>
            <a href="../Videos/video_player.php?id=<?php echo (int)$it['id']; ?>">
              <div class="bm-thumb">
                <video preload="metadata" muted playsinline>
                  <source src="../admin/upload/<?php echo htmlspecialchars($it['name']); ?>#t=0.1">
                </video>
                <span class="bm-type-pill video"><i class="fas fa-play"></i> Video</span>
              </div>
            </a>
            <div class="bm-body">
              <div class="bm-title"><?php echo htmlspecialchars($it['title']); ?></div>
              <div class="bm-meta">
                <?php if (!empty($it['cat_name'])): ?><span class="bm-cat-pill"><?php echo htmlspecialchars($it['cat_name']); ?></span><?php endif; ?>
              </div>
            </div>

          <?php elseif ($type === 'album'): ?>
            <a href="../Photo/gallery.php?id=<?php echo (int)$it['id']; ?>">
              <div class="bm-thumb">
                <?php if (!empty($it['image'])): ?>
                  <img src="../admin/acatch/<?php echo rawurlencode($it['image']); ?>" alt="" onerror="this.style.display='none'">
                <?php else: ?>
                  <div class="bm-thumb-icon" style="background: linear-gradient(135deg,#ec4899,#f43f5e);"><i class="fas fa-images"></i></div>
                <?php endif; ?>
                <span class="bm-type-pill album"><i class="fas fa-images"></i> Album · <?php echo (int)$it['pc']; ?> photos</span>
              </div>
            </a>
            <div class="bm-body">
              <div class="bm-title"><?php echo htmlspecialchars($it['title']); ?></div>
              <div class="bm-meta"><?php echo htmlspecialchars(mb_strimwidth($it['des'] ?? '', 0, 60, '…')); ?></div>
            </div>

          <?php else: /* file */
            $ext = strtolower(pathinfo($it['name'], PATHINFO_EXTENSION));
            list($label, $color, $ico) = file_type_meta_b($ext);
          ?>
            <a href="../Documents/view_file.php?file_id=<?php echo (int)$it['id']; ?>">
              <div class="bm-thumb">
                <div class="bm-thumb-icon" style="background: linear-gradient(135deg, <?php echo $color; ?>, <?php echo $color; ?>cc);"><i class="fas <?php echo $ico; ?>"></i></div>
                <span class="bm-type-pill file"><i class="fas fa-file"></i> <?php echo $label; ?></span>
              </div>
            </a>
            <div class="bm-body">
              <div class="bm-title"><?php echo htmlspecialchars($it['title'] ?: $it['name']); ?></div>
              <div class="bm-meta">
                <?php if (!empty($it['folder_name'])): ?><span><i class="fas fa-folder"></i> <?php echo htmlspecialchars($it['folder_name']); ?></span><?php endif; ?>
              </div>
            </div>
          <?php endif; ?>

          <div class="bm-foot">
            <span class="ago"><i class="fas fa-clock"></i> Bookmarked <?php echo $created_ago; ?></span>
            <?php
              $bm_type = $type; $bm_id = (int)$it['id'];
              include __DIR__ . '/../auth/bookmark_btn.php';
            ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<script>
// Theme toggle
const themeBtn = document.getElementById('theme-toggle');
if (themeBtn) {
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
}
</script>

</body>
</html>