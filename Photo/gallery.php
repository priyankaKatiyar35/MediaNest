<?php
/**
 * MediaNest — Photo Gallery (event detail)
 * --------------------------------------------------------------
 * Renders the photo set for one event. Modern grid + lightbox.
 * Intra-event filter on caption.
 */
require_once __DIR__ . '/../auth/auth.php';
requireLogin();

$conn = mysqli_connect('localhost', 'root', '', 's&p');
if (!$conn) die('DB connection failed: ' . mysqli_connect_error());

$current_user = currentUser();
$aid = (int)($_GET['id'] ?? 0);
if (!$aid) { header('Location: index.php'); exit; }

// ---------- Fetch album ----------
$stmt = mysqli_prepare($conn, "SELECT * FROM tbl_album WHERE albumid = ? AND status='process'");
mysqli_stmt_bind_param($stmt, 'i', $aid);
mysqli_stmt_execute($stmt);
$album = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$album) { header('Location: index.php'); exit; }

// ---------- Fetch photos with optional caption filter ----------
$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $stmt = mysqli_prepare($conn,
        "SELECT * FROM tbl_gallery WHERE aid = ? AND status='process' AND caption LIKE ? ORDER BY gid DESC"
    );
    $like = '%' . $q . '%';
    mysqli_stmt_bind_param($stmt, 'is', $aid, $like);
} else {
    $stmt = mysqli_prepare($conn,
        "SELECT * FROM tbl_gallery WHERE aid = ? AND status='process' ORDER BY gid DESC"
    );
    mysqli_stmt_bind_param($stmt, 'i', $aid);
}
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$photos = [];
while ($r = mysqli_fetch_assoc($res)) $photos[] = $r;

$aname = htmlspecialchars($album['name']);
$adesc = htmlspecialchars($album['adesc'] ?? '');
$edate = $album['event_date'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $aname ?> — MediaNest</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/js/all.min.js" defer></script>

<!-- PhotoSwipe v5 (vendor-free CDN; load from cloudflare for caching) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/photoswipe@5.4.4/dist/photoswipe.css">

<style>
:root {
  --bg: #f6f7fb; --bg-elev: #ffffff;
  --text: #0f172a; --text-soft: #475569; --muted: #94a3b8;
  --border: rgba(15, 23, 42, 0.08);
  --shadow-sm: 0 2px 8px rgba(15, 23, 42, 0.06);
  --shadow-md: 0 10px 30px rgba(15, 23, 42, 0.08);
  --brand-1: #ec4899; --brand-2: #f43f5e;
  --radius: 16px; --radius-lg: 22px;
  --grad-brand: linear-gradient(135deg, #ec4899, #f43f5e);
  --grad-text:  linear-gradient(135deg, #ec4899, #a855f7 50%, #6366f1);
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

/* ===== NAV (same as index) ===== */
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

.breadcrumb { font-size: 13px; color: var(--text-soft); margin-bottom: 18px; }
.breadcrumb a { color: var(--brand-1); text-decoration: none; font-weight: 500; }
.breadcrumb a:hover { text-decoration: underline; }
.breadcrumb .sep { margin: 0 6px; opacity: 0.5; }
.breadcrumb .current { color: var(--text); font-weight: 600; }

.event-header {
  background: var(--bg-elev); border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 28px 30px; margin-bottom: 28px;
  box-shadow: var(--shadow-sm);
  position: relative; overflow: hidden;
}
.event-header::before {
  content: ''; position: absolute; top: -50%; right: -10%;
  width: 280px; height: 280px;
  background: radial-gradient(circle, rgba(236,72,153,0.18), transparent 70%);
  pointer-events: none;
}
.event-header h1 { font-size: 28px; margin-bottom: 8px; position: relative; }
.event-header .meta {
  display: flex; flex-wrap: wrap; gap: 16px;
  font-size: 13px; color: var(--text-soft); margin-bottom: 14px;
  position: relative;
}
.event-header .meta span { display: inline-flex; align-items: center; gap: 6px; }
.event-header .meta i { color: var(--brand-1); }
.event-header p { color: var(--text-soft); font-size: 15px; line-height: 1.6; position: relative; max-width: 720px; }

/* Toolbar */
.toolbar { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 24px; }
.search-wrap {
  flex: 1; min-width: 240px;
  background: var(--bg-elev); border: 1.5px solid var(--border);
  border-radius: 14px; padding: 12px 16px;
  display: flex; align-items: center; gap: 12px;
}
.search-wrap:focus-within { border-color: var(--brand-1); box-shadow: 0 0 0 4px rgba(236,72,153,0.12); }
.search-wrap i { color: var(--muted); }
.search-wrap input { flex: 1; border: none; background: transparent; font: inherit; font-size: 15px; color: var(--text); outline: none; }

/* Photo grid */
.photo-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 14px;
}
.photo-tile {
  position: relative; aspect-ratio: 4/3;
  border-radius: 14px; overflow: hidden;
  background: var(--bg-elev); border: 1px solid var(--border);
  cursor: zoom-in;
  transition: transform .25s ease, box-shadow .25s ease;
  animation: fadeUp .35s both;
}
.photo-tile:hover {
  transform: translateY(-3px);
  box-shadow: var(--shadow-md);
}
.photo-tile img { width: 100%; height: 100%; object-fit: cover; transition: transform .4s; }
.photo-tile:hover img { transform: scale(1.05); }
.photo-tile .cap {
  position: absolute; left: 0; right: 0; bottom: 0;
  padding: 18px 14px 10px;
  background: linear-gradient(transparent, rgba(0,0,0,0.65));
  color: white; font-size: 12px; font-weight: 500;
  opacity: 0; transition: opacity .2s;
}
.photo-tile:hover .cap { opacity: 1; }

@keyframes fadeUp { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
.photo-tile:nth-child(n+4) { animation-delay: .05s; }
.photo-tile:nth-child(n+8) { animation-delay: .1s; }
.photo-tile:nth-child(n+12){ animation-delay: .15s; }

.empty {
  text-align: center; padding: 80px 20px; color: var(--text-soft);
  background: var(--bg-elev); border-radius: var(--radius);
  border: 2px dashed var(--border);
}
.empty i { font-size: 56px; opacity: 0.3; margin-bottom: 18px; color: var(--brand-1); }
.empty h3 { font-size: 18px; margin-bottom: 6px; color: var(--text); }

@media (max-width: 640px) {
  .nav-links { display: none; }
  .event-header h1 { font-size: 22px; }
  .photo-grid { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 8px; }
}
</style>
</head>
<body oncontextmenu="return false">

<!-- ===== NAV ===== -->
<nav class="nav">
  <div class="nav-inner">
    <a href="../index.php" class="brand">
      <div class="brand-mark"><i class="fas fa-cube"></i></div>
      <div class="brand-name">Media<span>Nest</span></div>
    </a>
    <div class="nav-links">
      <a href="../index.php" class="nav-link">Home</a>
      <a href="../Videos/index.php" class="nav-link">Videos</a>
      <a href="index.php" class="nav-link active">Photos</a>
      <a href="../Documents/index.php" class="nav-link">Documents</a>
    </div>
    <div class="nav-actions">
      <button id="theme-toggle" class="icon-btn"><i class="fas fa-moon"></i></button>
      <a href="../auth/logout.php" class="user-chip">
        <span class="av"><?= strtoupper(substr($current_user['full_name'] ?? 'U', 0, 1)) ?></span>
        <span><?= htmlspecialchars(strtok($current_user['full_name'] ?? 'You', ' ')) ?></span>
      </a>
    </div>
  </div>
</nav>

<div class="page">

  <div class="breadcrumb">
    <a href="../index.php">Home</a>
    <span class="sep">/</span>
    <a href="index.php">Photo Gallery</a>
    <span class="sep">/</span>
    <span class="current"><?= $aname ?></span>
  </div>

  <div class="event-header">
    <h1 style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
      <span style="flex:1;min-width:0;"><?= $aname ?></span>
      <?php
        $bm_type = 'album';
        $bm_id   = (int)$aid;
        include __DIR__ . '/../auth/bookmark_btn.php';
      ?>
    </h1>
    <div class="meta">
      <?php if ($edate): ?>
        <span><i class="fas fa-calendar"></i> <?= date('F j, Y', strtotime($edate)) ?></span>
      <?php endif; ?>
      <span><i class="fas fa-image"></i> <?= count($photos) ?> photo<?= count($photos) === 1 ? '' : 's' ?></span>
    </div>
    <?php if ($adesc): ?>
      <p><?= nl2br($adesc) ?></p>
    <?php endif; ?>
  </div>

  <?php if (count($photos) >= 4 || $q !== ''): ?>
  <form class="toolbar" method="get">
    <input type="hidden" name="id" value="<?= $aid ?>">
    <div class="search-wrap">
      <i class="fas fa-search"></i>
      <input type="text" name="q" placeholder="Filter photos by caption…"
             value="<?= htmlspecialchars($q) ?>" autocomplete="off" id="filter">
    </div>
  </form>
  <?php endif; ?>

  <?php if (!$photos): ?>
    <div class="empty">
      <i class="fas <?= $q ? 'fa-magnifying-glass' : 'fa-image' ?>"></i>
      <h3><?= $q ? 'No photos match your filter.' : 'No photos in this event yet.' ?></h3>
      <p>Try a different keyword or come back later.</p>
    </div>
  <?php else: ?>
    <div class="photo-grid" id="gallery">
      <?php foreach ($photos as $p):
        $full  = '../admin/gupload/' . $p['gimages'];
        $thumb = '../admin/gcatch/'  . $p['gimages'];
        $cap   = htmlspecialchars($p['caption'] ?? '');
      ?>
        <a class="photo-tile" href="<?= $full ?>"
           data-pswp-width="1600" data-pswp-height="1200"
           target="_blank" rel="noopener"
           <?= $cap ? 'data-caption="' . $cap . '"' : '' ?>>
          <img src="<?= $thumb ?>" alt="<?= $cap ?: 'Event photo' ?>" loading="lazy">
          <?php if ($cap): ?><div class="cap"><?= $cap ?></div><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</div>

<script type="module">
  // PhotoSwipe v5 lightbox — module imports avoid global pollution.
  import PhotoSwipeLightbox from 'https://cdn.jsdelivr.net/npm/photoswipe@5.4.4/dist/photoswipe-lightbox.esm.min.js';
  const lightbox = new PhotoSwipeLightbox({
    gallery: '#gallery',
    children: 'a',
    pswpModule: () => import('https://cdn.jsdelivr.net/npm/photoswipe@5.4.4/dist/photoswipe.esm.min.js'),
  });
  lightbox.on('uiRegister', function () {
    lightbox.pswp.ui.registerElement({
      name: 'custom-caption',
      isButton: false,
      appendTo: 'root',
      html: '',
      onInit: (el, pswp) => {
        pswp.on('change', () => {
          const cur = pswp.currSlide.data.element;
          const cap = cur?.dataset.caption || '';
          el.textContent = cap;
          el.style.cssText = 'position:absolute;left:0;right:0;bottom:0;padding:14px 20px;color:#fff;background:linear-gradient(transparent,rgba(0,0,0,0.7));font-size:14px;text-align:center;';
        });
      },
    });
  });
  lightbox.init();
</script>

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

const filt = document.getElementById('filter');
if (filt) {
  let t;
  filt.addEventListener('input', () => {
    clearTimeout(t);
    t = setTimeout(() => filt.form.submit(), 400);
  });
}
</script>
</body>
</html>