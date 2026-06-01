<?php
/**
 * MediaNest — Photo Gallery: Special Collection (Videos)
 * --------------------------------------------------------------
 * Shows videos uploaded via admin/videoalb.php (gallery_video table).
 * Same pink/rose theme as the rest of the Photo section.
 */
include __DIR__ . '/../auth/notif_bell.php';
require_once __DIR__ . '/../auth/auth.php';
requireLogin();

$conn = mysqli_connect('localhost', 'root', '', 's&p');
if (!$conn) die('DB connection failed: ' . mysqli_connect_error());
$current_user = currentUser();

// Optional search
$search = trim($_GET['q'] ?? '');
$where  = '';
$params = []; $types = '';
if ($search !== '') {
    $where = "WHERE title LIKE ? OR des LIKE ?";
    $like = '%' . $search . '%';
    $params = [$like, $like]; $types = 'ss';
}

$videos = [];
$sql = "SELECT * FROM gallery_video $where ORDER BY id DESC";
$stmt = mysqli_prepare($conn, $sql);
if ($stmt) {
    if ($types) mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($r = mysqli_fetch_assoc($res)) $videos[] = $r;
}

function initials($name) {
    $parts = preg_split('/\s+/', trim($name ?? ''));
    $s = '';
    foreach ($parts as $p) if ($p !== '') $s .= mb_substr($p, 0, 1);
    return strtoupper(mb_substr($s ?: 'U', 0, 2));
}
$first_name = explode(' ', $current_user['full_name'] ?? ($current_user['email'] ?? 'User'))[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Special Collection — MediaNest</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/js/all.min.js" defer></script>

<style>
:root {
  --bg: #f6f7fb; --bg-elev: #ffffff; --bg-soft: #fdf2f8;
  --text: #0f172a; --text-soft: #475569; --muted: #94a3b8;
  --border: rgba(15, 23, 42, 0.08);
  --shadow-sm: 0 2px 8px rgba(15, 23, 42, 0.06);
  --shadow-md: 0 10px 30px rgba(15, 23, 42, 0.08);
  --shadow-lg: 0 20px 60px rgba(15, 23, 42, 0.12);
  --shadow-pink: 0 12px 40px rgba(236, 72, 153, 0.25);
  --brand-1: #ec4899; --brand-2: #f43f5e;
  --grad-brand: linear-gradient(135deg, #ec4899, #f43f5e);
  --grad-brand-soft: linear-gradient(135deg, #fce7f3, #ffe4e6);
  --grad-text: linear-gradient(135deg, #ec4899, #f43f5e 50%, #f97316);
  --grad-hero: linear-gradient(135deg, #fdf2f8 0%, #fef3c7 50%, #ffe4e6 100%);
  --radius: 14px;
}
html.dark {
  --bg: #0a0e1a; --bg-elev: #131826; --bg-soft: #1f1432;
  --text: #e2e8f0; --text-soft: #cbd5e1; --muted: #64748b;
  --border: rgba(255, 255, 255, 0.08);
  --grad-hero: linear-gradient(135deg, #1f1432 0%, #1a1f3a 50%, #2a1729 100%);
}
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Inter', sans-serif; color: var(--text); background: var(--bg); min-height: 100vh; transition: background .3s, color .3s; }
h1, h2, h3 { font-family: 'Sora', sans-serif; letter-spacing: -0.02em; }
a { color: inherit; text-decoration: none; }

/* Nav (same as Photo/index.php) */
.nav { position: sticky; top: 0; z-index: 50; backdrop-filter: blur(14px); background: color-mix(in srgb, var(--bg) 78%, transparent); border-bottom: 1px solid var(--border); }
.nav-inner { max-width: 1280px; margin: 0 auto; padding: 14px 24px; display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.logo { display: flex; align-items: center; gap: 10px; font-family: 'Sora', sans-serif; font-weight: 700; font-size: 19px; }
.logo-mark { width: 36px; height: 36px; border-radius: 10px; background: var(--grad-brand); color: white; display: grid; place-items: center; box-shadow: var(--shadow-pink); }
.logo-text span { background: var(--grad-text); -webkit-background-clip: text; background-clip: text; color: transparent; }
.pill { font-size: 10px; padding: 3px 9px; border-radius: 999px; background: rgba(236, 72, 153, .1); color: var(--brand-1); font-weight: 700; text-transform: uppercase; letter-spacing: .08em; }
.nav-links { display: flex; gap: 4px; flex-wrap: wrap; }
.nav-links a { padding: 8px 14px; border-radius: 8px; font-size: 13px; font-weight: 500; color: var(--text-soft); display: inline-flex; align-items: center; gap: 7px; transition: all .2s; }
.nav-links a:hover { background: var(--bg-elev); color: var(--text); }
.nav-links a.active { background: var(--grad-brand); color: white; box-shadow: var(--shadow-pink); }
.nav-right { display: flex; align-items: center; gap: 8px; }
.user-chip { display: inline-flex; align-items: center; gap: 8px; padding: 5px 12px 5px 5px; border-radius: 999px; background: var(--bg-elev); border: 1px solid var(--border); font-size: 13px; font-weight: 500; }
.user-chip .av { width: 28px; height: 28px; border-radius: 50%; background: var(--grad-brand); color: white; font-weight: 700; font-size: 11px; display: grid; place-items: center; }
.icon-btn { width: 38px; height: 38px; border-radius: 10px; background: transparent; border: 1px solid var(--border); color: var(--text); display: grid; place-items: center; transition: all .2s; }
.icon-btn:hover { background: var(--bg-elev); }

/* Hero */
.hero { background: var(--grad-hero); border-bottom: 1px solid var(--border); padding: 48px 24px 40px; position: relative; overflow: hidden; }
.hero::before { content: ''; position: absolute; top: -120px; right: -80px; width: 360px; height: 360px; border-radius: 50%; background: radial-gradient(circle, rgba(236, 72, 153, 0.18), transparent 70%); }
.hero-inner { max-width: 1280px; margin: 0 auto; position: relative; z-index: 1; }
.breadcrumb { display: inline-flex; align-items: center; gap: 8px; padding: 6px 14px; border-radius: 999px; background: rgba(255, 255, 255, .7); backdrop-filter: blur(8px); border: 1px solid var(--border); font-size: 12px; font-weight: 600; color: var(--text-soft); margin-bottom: 16px; }
html.dark .breadcrumb { background: rgba(19, 24, 38, .7); }
.breadcrumb a { color: var(--brand-1); }
.breadcrumb i { font-size: 9px; opacity: .5; }
.hero h1 { font-size: clamp(28px, 4.5vw, 42px); font-weight: 800; line-height: 1.1; margin-bottom: 10px; max-width: 720px; }
.hero h1 .gradient-text { background: var(--grad-text); -webkit-background-clip: text; background-clip: text; color: transparent; }
.hero p.sub { font-size: clamp(14px, 1.3vw, 16px); color: var(--text-soft); max-width: 600px; margin-bottom: 22px; }

.search-card { background: var(--bg-elev); border: 1px solid var(--border); border-radius: 14px; padding: 10px; box-shadow: var(--shadow-md); max-width: 560px; display: flex; gap: 8px; }
.search-card form { display: flex; gap: 8px; width: 100%; }
.search-input { flex: 1; display: flex; align-items: center; gap: 10px; padding: 8px 14px; border-radius: 10px; background: var(--bg); border: 1px solid var(--border); }
.search-input i { color: var(--muted); }
.search-input input { flex: 1; border: 0; background: transparent; outline: none; font: inherit; color: var(--text); font-size: 14px; }
.search-card .btn-go { padding: 10px 18px; border-radius: 10px; background: var(--grad-brand); color: white; font-weight: 600; border: 0; font-size: 13px; }

/* Page */
.page { max-width: 1280px; margin: 0 auto; padding: 32px 24px 60px; }
.section-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 14px; flex-wrap: wrap; margin-bottom: 20px; }
.section-head h2 { font-size: clamp(20px, 2.2vw, 24px); font-weight: 700; }
.section-head .meta { font-size: 13px; color: var(--text-soft); }
.section-head .meta strong { color: var(--text); font-weight: 600; }

/* Video grid */
.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 22px; }

.vid-card { background: var(--bg-elev); border: 1px solid var(--border); border-radius: 16px; overflow: hidden; transition: transform .25s, box-shadow .25s, border-color .25s; }
.vid-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-md); border-color: rgba(236, 72, 153, .35); }
.vid-thumb { position: relative; aspect-ratio: 16/9; background: #000; overflow: hidden; }
.vid-thumb video { width: 100%; height: 100%; object-fit: cover; display: block; }
.vid-thumb .play-overlay { position: absolute; inset: 0; background: rgba(0, 0, 0, .25); display: grid; place-items: center; opacity: 1; transition: opacity .2s; pointer-events: none; }
.vid-thumb .play-overlay i { width: 64px; height: 64px; border-radius: 50%; background: rgba(255, 255, 255, .95); color: var(--brand-1); display: grid; place-items: center; font-size: 22px; box-shadow: 0 8px 30px rgba(0, 0, 0, .3); transition: transform .2s; }
.vid-card:hover .vid-thumb .play-overlay i { transform: scale(1.1); }
.vid-thumb .badge-special { position: absolute; top: 12px; left: 12px; padding: 4px 10px; border-radius: 999px; background: var(--grad-brand); color: white; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; display: inline-flex; align-items: center; gap: 5px; box-shadow: var(--shadow-pink); }

.vid-body { padding: 16px 18px; }
.vid-title { font-family: 'Sora', sans-serif; font-weight: 700; font-size: 16px; line-height: 1.3; margin-bottom: 6px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.vid-desc { font-size: 13px; color: var(--text-soft); line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; min-height: 2.5em; }

/* Modal */
.modal { position: fixed; inset: 0; z-index: 100; display: none; align-items: center; justify-content: center; padding: 20px; background: rgba(0, 0, 0, .85); backdrop-filter: blur(10px); }
.modal.open { display: flex; }
.modal-inner { background: var(--bg-elev); border-radius: 18px; max-width: 960px; width: 100%; max-height: 90vh; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 30px 80px rgba(0, 0, 0, .5); }
.modal-video { aspect-ratio: 16/9; background: #000; }
.modal-video video { width: 100%; height: 100%; }
.modal-body { padding: 22px 26px; }
.modal-body h3 { font-size: 22px; font-weight: 700; margin-bottom: 8px; }
.modal-body p { color: var(--text-soft); font-size: 14px; line-height: 1.6; }
.modal-close { position: absolute; top: 18px; right: 18px; width: 42px; height: 42px; border-radius: 50%; background: rgba(255, 255, 255, .15); color: white; border: 0; display: grid; place-items: center; cursor: pointer; backdrop-filter: blur(8px); transition: all .15s; }
.modal-close:hover { background: rgba(255, 255, 255, .25); transform: rotate(90deg); }

/* Empty */
.empty { text-align: center; padding: 70px 20px; background: var(--bg-elev); border: 1px dashed var(--border); border-radius: 16px; }
.empty .ic { width: 72px; height: 72px; border-radius: 50%; background: var(--grad-brand-soft); color: var(--brand-1); display: grid; place-items: center; font-size: 28px; margin: 0 auto 16px; }
html.dark .empty .ic { background: rgba(236, 72, 153, .12); }
.empty h3 { font-size: 19px; margin-bottom: 6px; }
.empty p { color: var(--text-soft); font-size: 14px; max-width: 380px; margin: 0 auto; }

@keyframes fadeUp { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
.vid-card { animation: fadeUp .4s ease both; }
.vid-card:nth-child(1) { animation-delay: .02s; }
.vid-card:nth-child(2) { animation-delay: .06s; }
.vid-card:nth-child(3) { animation-delay: .10s; }
.vid-card:nth-child(4) { animation-delay: .14s; }
</style>
</head>
<body>

<nav class="nav">
  <div class="nav-inner">
    <div class="logo">
      <div class="logo-mark"><i class="fas fa-camera-retro"></i></div>
      <div class="logo-text">Media<span>Nest</span></div>
      <span class="pill">Photos</span>
    </div>
    <div class="nav-links">
      <a href="../index.php"><i class="fas fa-house"></i> Home</a>
      <a href="../Videos/index.php"><i class="fas fa-video"></i> Videos</a>
      <a href="index.php" class="active"><i class="fas fa-images"></i> Photos</a>
      <a href="../Documents/index.php"><i class="fas fa-folder-open"></i> Documents</a>
    </div>
    <div class="nav-right">
      <button class="icon-btn" id="theme-toggle" aria-label="Toggle theme"><i class="fas fa-moon"></i></button>
      <span class="user-chip">
        <span class="av"><?php echo htmlspecialchars(initials($current_user['full_name'] ?? $current_user['email'] ?? 'U')); ?></span>
        <span><?php echo htmlspecialchars($first_name); ?></span>
      </span>
      <a href="../auth/logout.php" class="icon-btn" title="Sign out"><i class="fas fa-arrow-right-from-bracket"></i></a>
    </div>
  </div>
</nav>

<section class="hero">
  <div class="hero-inner">
    <div class="breadcrumb">
      <a href="index.php"><i class="fas fa-images"></i> Photo Gallery</a>
      <i class="fas fa-chevron-right"></i>
      <span>Special Collection</span>
    </div>
    <h1><span class="gradient-text">Special Collection</span><br>Video Highlights</h1>
    <p class="sub">Curated video moments from our most memorable events. Click any video to play it in full.</p>

    <div class="search-card">
      <form method="get" id="searchForm">
        <div class="search-input">
          <i class="fas fa-magnifying-glass"></i>
          <input type="text" name="q" id="searchInput" placeholder="Search videos by title or description…" value="<?php echo htmlspecialchars($search); ?>" autocomplete="off">
        </div>
        <button type="submit" class="btn-go"><i class="fas fa-arrow-right"></i></button>
      </form>
    </div>
  </div>
</section>

<div class="page">

  <div class="section-head">
    <div>
      <h2><?php echo $search !== '' ? 'Search results' : 'All videos'; ?></h2>
      <div class="meta">
        <?php if ($search !== ''): ?>
          <strong><?php echo count($videos); ?></strong> result<?php echo count($videos) == 1 ? '' : 's'; ?> for "<strong><?php echo htmlspecialchars($search); ?></strong>" · <a href="gallery_video.php" style="color:var(--brand-1);">clear</a>
        <?php else: ?>
          <strong><?php echo count($videos); ?></strong> video<?php echo count($videos) == 1 ? '' : 's'; ?> in this collection
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php if (count($videos) === 0): ?>
    <div class="empty">
      <div class="ic"><i class="fas fa-film"></i></div>
      <h3><?php echo $search !== '' ? 'No videos match that search' : 'No videos yet'; ?></h3>
      <p><?php echo $search !== '' ? 'Try different keywords or clear the filter to see everything.' : 'Once admins upload videos to the Special Collection, they\'ll appear here.'; ?></p>
    </div>
  <?php else: ?>
    <div class="grid">
      <?php foreach ($videos as $v):
        $src = '../admin/upload/' . rawurlencode($v['name']);
      ?>
        <div class="vid-card" onclick='openVideo(<?php echo json_encode([
          'src'   => $src,
          'title' => $v['title'],
          'desc'  => $v['des']
        ]); ?>)' style="cursor:pointer;">
          <div class="vid-thumb">
            <video preload="metadata" muted playsinline>
              <source src="<?php echo htmlspecialchars($src); ?>#t=0.1">
            </video>
            <div class="play-overlay"><i class="fas fa-play"></i></div>
            <span class="badge-special"><i class="fas fa-star"></i> Special</span>
          </div>
          <div class="vid-body">
            <div class="vid-title"><?php echo htmlspecialchars($v['title']); ?></div>
            <div class="vid-desc"><?php echo htmlspecialchars(!empty($v['des']) ? $v['des'] : 'No description provided.'); ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- Video modal -->
<div class="modal" id="videoModal" onclick="if(event.target===this) closeVideo()">
  <button class="modal-close" onclick="closeVideo()"><i class="fas fa-xmark"></i></button>
  <div class="modal-inner">
    <div class="modal-video">
      <video id="modalVideo" controls controlsList="nodownload"></video>
    </div>
    <div class="modal-body">
      <h3 id="modalTitle">—</h3>
      <p id="modalDesc"></p>
    </div>
  </div>
</div>

<script>
// Theme
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

// Modal
function openVideo(v) {
  const m = document.getElementById('videoModal');
  const vid = document.getElementById('modalVideo');
  vid.src = v.src;
  document.getElementById('modalTitle').textContent = v.title;
  document.getElementById('modalDesc').textContent = v.desc || '';
  m.classList.add('open');
  vid.play().catch(() => {});
  document.body.style.overflow = 'hidden';
}
function closeVideo() {
  const m = document.getElementById('videoModal');
  const vid = document.getElementById('modalVideo');
  vid.pause();
  vid.src = '';
  m.classList.remove('open');
  document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeVideo(); });
</script>

</body>
</html>