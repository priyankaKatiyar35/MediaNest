<?php
/**
 * MediaNest — Photo Gallery (event-folder grid) — v2
 * --------------------------------------------------------------
 * Unified auth, prepared statements, server-side search + sort,
 * stats hero, featured event card, special collections row,
 * pagination, dark mode, empty/no-results states.
//  */
//   $bm_type = 'video';   // or 'album' or 'file'
//   $bm_id   = (int)$video['id'];
  include __DIR__ . '/../auth/bookmark_btn.php';
   
require_once __DIR__ . '/../auth/auth.php';
requireLogin();

$conn = mysqli_connect('localhost', 'root', '', 's&p');
if (!$conn) die('DB connection failed: ' . mysqli_connect_error());
$current_user = currentUser();

// ---------- Inputs ----------
$search = trim($_GET['q']   ?? '');
$sort   = $_GET['sort']     ?? 'newest';        // newest | oldest | name | photos
$page   = max(1, (int)($_GET['page'] ?? 1));
$per    = 12;
$offset = ($page - 1) * $per;

$order_by = match ($sort) {
    'oldest' => 'a.albumid ASC',
    'name'   => 'a.name ASC',
    'photos' => 'photo_count DESC, a.albumid DESC',
    default  => 'a.albumid DESC',
};

// ---------- WHERE clause (prepared) ----------
$where  = "a.status = 'process'";
$params = [];
$types  = '';
if ($search !== '') {
    $where .= " AND (a.name LIKE ? OR a.adesc LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like; $params[] = $like;
    $types   .= 'ss';
}

// ---------- Aggregate stats (header band) ----------
$total_events_q = mysqli_query($conn, "SELECT COUNT(*) FROM tbl_album WHERE status='process'");
$total_events = (int)(mysqli_fetch_row($total_events_q)[0] ?? 0);
$total_photos_q = mysqli_query($conn, "SELECT COUNT(*) FROM tbl_gallery WHERE status='process'");
$total_photos = (int)(mysqli_fetch_row($total_photos_q)[0] ?? 0);
$latest_q = mysqli_query($conn, "SELECT name, albumid FROM tbl_album WHERE status='process' ORDER BY albumid DESC LIMIT 1");
$latest_event = mysqli_fetch_assoc($latest_q);

// ---------- Total matching (for pagination) ----------
$count_sql = "SELECT COUNT(*) FROM tbl_album a WHERE $where";
$stmt = mysqli_prepare($conn, $count_sql);
if ($types) mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$matched = (int)(mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0] ?? 0);
$total_pages = max(1, (int)ceil($matched / $per));
if ($page > $total_pages) $page = $total_pages;

// ---------- Page data (with photo count per album) ----------
$list_sql = "SELECT a.*,
             (SELECT COUNT(*) FROM tbl_gallery g WHERE g.aid = a.albumid AND g.status='process') AS photo_count
             FROM tbl_album a
             WHERE $where
             ORDER BY $order_by
             LIMIT ? OFFSET ?";
$stmt = mysqli_prepare($conn, $list_sql);
$bindTypes  = $types . 'ii';
$bindValues = array_merge($params, [$per, $offset]);
mysqli_stmt_bind_param($stmt, $bindTypes, ...$bindValues);
mysqli_stmt_execute($stmt);
$events = mysqli_stmt_get_result($stmt);

// Helper: initials
function initials($name) {
    $parts = preg_split('/\s+/', trim($name ?? ''));
    $s = '';
    foreach ($parts as $p) { if ($p !== '') $s .= mb_substr($p, 0, 1); }
    return strtoupper(mb_substr($s ?: 'U', 0, 2));
}
$first_name = $current_user['full_name'] ?? ($current_user['email'] ?? 'User');
$first_name = explode(' ', $first_name)[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Photo Gallery — MediaNest</title>

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
  --radius: 14px; --radius-lg: 22px;
  --green: #10b981; --red: #ef4444; --gold: #f59e0b;
}
html.dark {
  --bg: #0a0e1a; --bg-elev: #131826; --bg-soft: #1f1432;
  --text: #e2e8f0; --text-soft: #cbd5e1; --muted: #64748b;
  --border: rgba(255, 255, 255, 0.08);
  --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.3);
  --shadow-md: 0 10px 30px rgba(0, 0, 0, 0.4);
  --shadow-lg: 0 20px 60px rgba(0, 0, 0, 0.5);
  --grad-hero: linear-gradient(135deg, #1f1432 0%, #1a1f3a 50%, #2a1729 100%);
}
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Inter', sans-serif; color: var(--text); background: var(--bg); min-height: 100vh; transition: background .4s, color .4s; }
h1, h2, h3, h4 { font-family: 'Sora', sans-serif; letter-spacing: -0.02em; }
a { color: inherit; text-decoration: none; }
button { font-family: inherit; cursor: pointer; }
img { display: block; max-width: 100%; }

/* Nav */
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
.nav-links a i { font-size: 12px; }
.nav-right { display: flex; align-items: center; gap: 8px; }
.user-chip { display: inline-flex; align-items: center; gap: 8px; padding: 5px 12px 5px 5px; border-radius: 999px; background: var(--bg-elev); border: 1px solid var(--border); font-size: 13px; font-weight: 500; }
.user-chip .av { width: 28px; height: 28px; border-radius: 50%; background: var(--grad-brand); color: white; font-weight: 700; font-size: 11px; display: grid; place-items: center; }
.icon-btn { width: 38px; height: 38px; border-radius: 10px; background: transparent; border: 1px solid var(--border); color: var(--text); display: grid; place-items: center; transition: all .2s; }
.icon-btn:hover { background: var(--bg-elev); }

/* Hero */
.hero { background: var(--grad-hero); border-bottom: 1px solid var(--border); padding: 56px 24px 48px; position: relative; overflow: hidden; }
.hero::before { content: ''; position: absolute; top: -120px; right: -80px; width: 400px; height: 400px; border-radius: 50%; background: radial-gradient(circle, rgba(236, 72, 153, 0.18), transparent 70%); pointer-events: none; }
.hero::after { content: ''; position: absolute; bottom: -150px; left: -100px; width: 380px; height: 380px; border-radius: 50%; background: radial-gradient(circle, rgba(244, 63, 94, 0.15), transparent 70%); pointer-events: none; }
.hero-inner { max-width: 1280px; margin: 0 auto; position: relative; z-index: 1; }
.hero-greet { display: inline-flex; align-items: center; gap: 8px; padding: 6px 14px; border-radius: 999px; background: rgba(255, 255, 255, .7); backdrop-filter: blur(8px); border: 1px solid var(--border); font-size: 12px; font-weight: 600; color: var(--text-soft); margin-bottom: 18px; }
html.dark .hero-greet { background: rgba(19, 24, 38, .7); }
.hero-greet i { color: var(--brand-1); }
.hero h1 { font-size: clamp(32px, 5vw, 52px); font-weight: 800; line-height: 1.05; margin-bottom: 14px; max-width: 760px; }
.hero h1 .gradient-text { background: var(--grad-text); -webkit-background-clip: text; background-clip: text; color: transparent; }
.hero p.sub { font-size: clamp(15px, 1.4vw, 18px); color: var(--text-soft); max-width: 620px; margin-bottom: 28px; }

.search-card { background: var(--bg-elev); border: 1px solid var(--border); border-radius: 18px; padding: 14px; box-shadow: var(--shadow-md); max-width: 760px; display: flex; gap: 10px; align-items: center; }
.search-card form { display: flex; gap: 10px; width: 100%; align-items: center; }
.search-input { flex: 1; display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-radius: 12px; background: var(--bg); border: 1px solid var(--border); }
.search-input i { color: var(--muted); }
.search-input input { flex: 1; border: 0; background: transparent; outline: none; font: inherit; color: var(--text); font-size: 15px; }
.search-input kbd { font-family: ui-monospace, monospace; font-size: 11px; padding: 2px 6px; border-radius: 4px; background: var(--border); color: var(--text-soft); }
.search-card select { padding: 11px 14px; border-radius: 12px; background: var(--bg); border: 1px solid var(--border); color: var(--text); font: inherit; font-size: 14px; cursor: pointer; }
.search-card .btn-go { padding: 11px 20px; border-radius: 12px; background: var(--grad-brand); color: white; font-weight: 600; border: 0; font-size: 14px; box-shadow: var(--shadow-pink); transition: transform .15s; }
.search-card .btn-go:hover { transform: translateY(-1px); }

/* Stats strip */
.stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-top: 24px; max-width: 760px; }
.stat { background: var(--bg-elev); border: 1px solid var(--border); border-radius: var(--radius); padding: 14px 16px; display: flex; align-items: center; gap: 12px; box-shadow: var(--shadow-sm); }
.stat .ic { width: 40px; height: 40px; border-radius: 10px; background: var(--grad-brand-soft); color: var(--brand-1); display: grid; place-items: center; font-size: 16px; }
html.dark .stat .ic { background: rgba(236, 72, 153, .15); }
.stat .v { font-family: 'Sora', sans-serif; font-weight: 700; font-size: 18px; line-height: 1.1; }
.stat .l { font-size: 11px; color: var(--text-soft); text-transform: uppercase; letter-spacing: .08em; margin-top: 2px; }

/* Page */
.page { max-width: 1280px; margin: 0 auto; padding: 36px 24px 60px; }
.section-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 14px; flex-wrap: wrap; margin-bottom: 22px; }
.section-head h2 { font-size: clamp(22px, 2.4vw, 28px); font-weight: 700; }
.section-head .meta { font-size: 13px; color: var(--text-soft); }
.section-head .meta strong { color: var(--text); font-weight: 600; }

/* Filter chips (just visual; sort lives in hero) */
.chip-row { display: flex; gap: 8px; flex-wrap: wrap; }
.chip { padding: 6px 12px; border-radius: 999px; border: 1px solid var(--border); background: var(--bg-elev); font-size: 12px; font-weight: 600; color: var(--text-soft); display: inline-flex; align-items: center; gap: 6px; transition: all .15s; }
.chip:hover, .chip.active { background: var(--grad-brand); color: white; border-color: transparent; }
.chip i { font-size: 10px; }

/* Featured/special card row */
.feature-row { display: grid; grid-template-columns: 1fr; gap: 18px; margin-bottom: 32px; }
@media (min-width: 880px) { .feature-row { grid-template-columns: 2fr 1fr; } }

.feature-card { position: relative; border-radius: var(--radius-lg); overflow: hidden; min-height: 260px; box-shadow: var(--shadow-md); border: 1px solid var(--border); background: var(--bg-elev); display: flex; flex-direction: column; justify-content: flex-end; transition: transform .25s, box-shadow .25s; }
.feature-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-lg); }
.feature-card .cover { position: absolute; inset: 0; background-size: cover; background-position: center; transition: transform .6s; }
.feature-card:hover .cover { transform: scale(1.06); }
.feature-card .overlay { position: absolute; inset: 0; background: linear-gradient(to top, rgba(0, 0, 0, .85) 0%, rgba(0, 0, 0, .35) 50%, transparent 100%); }
.feature-card .content { position: relative; padding: 22px 24px 24px; color: white; }
.feature-card .badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; background: rgba(255, 255, 255, .18); backdrop-filter: blur(8px); font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; margin-bottom: 10px; }
.feature-card .badge.pink { background: var(--grad-brand); }
.feature-card h3 { font-size: clamp(20px, 2.2vw, 26px); font-weight: 700; margin-bottom: 6px; }
.feature-card p { font-size: 14px; opacity: .9; margin-bottom: 14px; max-width: 540px; }
.feature-card .cta { display: inline-flex; align-items: center; gap: 8px; padding: 9px 18px; border-radius: 10px; background: white; color: #0f172a; font-size: 13px; font-weight: 600; }
.feature-card .meta-bar { display: inline-flex; gap: 12px; font-size: 12px; opacity: .85; margin-bottom: 12px; }
.feature-card .meta-bar span { display: inline-flex; align-items: center; gap: 5px; }

.feature-card.special { background: var(--grad-brand); color: white; min-height: 260px; padding: 22px 24px; }
.feature-card.special .cover, .feature-card.special .overlay { display: none; }
.feature-card.special .content { padding: 0; position: relative; z-index: 1; height: 100%; display: flex; flex-direction: column; justify-content: space-between; }
.feature-card.special::before { content: ''; position: absolute; bottom: -60px; right: -40px; width: 220px; height: 220px; border-radius: 50%; background: rgba(255, 255, 255, .12); }
.feature-card.special::after { content: '\f03d'; font-family: 'Font Awesome 6 Free'; font-weight: 900; position: absolute; top: 16px; right: 22px; font-size: 110px; opacity: .12; }

/* Cover fallback when album image missing */
.cover-fallback { position: absolute; inset: 0; background: var(--grad-brand-soft); display: grid; place-items: center; color: var(--brand-1); font-size: 48px; }
html.dark .cover-fallback { background: rgba(236, 72, 153, .12); }

/* Event grid */
.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; }

.event { background: var(--bg-elev); border: 1px solid var(--border); border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-sm); transition: transform .25s, box-shadow .25s, border-color .25s; display: flex; flex-direction: column; }
.event:hover { transform: translateY(-4px); box-shadow: var(--shadow-md); border-color: rgba(236, 72, 153, .35); }
.event-cover { position: relative; aspect-ratio: 16 / 10; overflow: hidden; background: var(--bg-soft); }
.event-cover img { width: 100%; height: 100%; object-fit: cover; transition: transform .5s; }
.event:hover .event-cover img { transform: scale(1.06); }
.event-cover .glaze { position: absolute; inset: 0; background: linear-gradient(to top, rgba(0, 0, 0, .55), transparent 50%); opacity: 0; transition: opacity .2s; }
.event:hover .event-cover .glaze { opacity: 1; }
.event-cover .count-badge { position: absolute; top: 12px; right: 12px; padding: 5px 10px; border-radius: 999px; background: rgba(255, 255, 255, .92); backdrop-filter: blur(6px); color: #0f172a; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 5px; }
.event-cover .count-badge i { color: var(--brand-1); font-size: 10px; }
.event-cover .open-cta { position: absolute; left: 12px; bottom: 12px; padding: 6px 12px; border-radius: 8px; background: var(--grad-brand); color: white; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; opacity: 0; transform: translateY(8px); transition: all .25s; }
.event:hover .event-cover .open-cta { opacity: 1; transform: translateY(0); }

.event-body { padding: 16px 18px 18px; flex: 1; display: flex; flex-direction: column; gap: 8px; }
.event-name { font-family: 'Sora', sans-serif; font-weight: 700; font-size: 17px; line-height: 1.25; color: var(--text); display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden; }
.event-desc { font-size: 13px; color: var(--text-soft); line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; min-height: 2.5em; }
.event-foot { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-top: 8px; padding-top: 12px; border-top: 1px solid var(--border); }
.event-date { font-size: 11px; color: var(--muted); display: inline-flex; align-items: center; gap: 5px; }
.event-foot .view { font-size: 12px; color: var(--brand-1); font-weight: 600; display: inline-flex; align-items: center; gap: 5px; }
.event-foot .view i { transition: transform .2s; }
.event:hover .event-foot .view i { transform: translateX(3px); }

/* Empty / no-results state */
.empty { text-align: center; padding: 80px 20px; background: var(--bg-elev); border: 1px dashed var(--border); border-radius: var(--radius-lg); }
.empty .ic { width: 80px; height: 80px; border-radius: 50%; background: var(--grad-brand-soft); color: var(--brand-1); display: grid; place-items: center; font-size: 32px; margin: 0 auto 18px; }
html.dark .empty .ic { background: rgba(236, 72, 153, .12); }
.empty h3 { font-size: 20px; margin-bottom: 6px; }
.empty p { color: var(--text-soft); font-size: 14px; max-width: 380px; margin: 0 auto 16px; }
.empty .reset-btn { display: inline-flex; align-items: center; gap: 8px; padding: 9px 18px; border-radius: 10px; background: var(--grad-brand); color: white; font-weight: 600; font-size: 13px; box-shadow: var(--shadow-pink); }

/* Pagination */
.pager { display: flex; justify-content: center; align-items: center; gap: 6px; margin-top: 40px; flex-wrap: wrap; }
.pager a, .pager span { min-width: 38px; height: 38px; padding: 0 12px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 600; color: var(--text-soft); background: var(--bg-elev); border: 1px solid var(--border); transition: all .15s; }
.pager a:hover { background: var(--bg-soft); color: var(--brand-1); border-color: rgba(236, 72, 153, .3); }
.pager .current { background: var(--grad-brand); color: white; border-color: transparent; box-shadow: var(--shadow-pink); }
.pager .dots { background: transparent; border: 0; color: var(--muted); }

.footer { max-width: 1280px; margin: 0 auto; padding: 28px 24px 36px; text-align: center; color: var(--muted); font-size: 12px; border-top: 1px solid var(--border); margin-top: 40px; }

/* Subtle entrance animation */
@keyframes fadeUp { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
.event, .feature-card, .stat { animation: fadeUp .5s ease both; }
.event:nth-child(1)  { animation-delay: .02s; }
.event:nth-child(2)  { animation-delay: .06s; }
.event:nth-child(3)  { animation-delay: .10s; }
.event:nth-child(4)  { animation-delay: .14s; }
.event:nth-child(5)  { animation-delay: .18s; }
.event:nth-child(6)  { animation-delay: .22s; }
.event:nth-child(7)  { animation-delay: .26s; }
.event:nth-child(8)  { animation-delay: .30s; }

@media (max-width: 720px) {
  .hero { padding: 36px 18px 32px; }
  .search-card { padding: 10px; flex-direction: column; }
  .search-card select { width: 100%; }
  .search-card .btn-go { width: 100%; }
  .nav-links a { padding: 6px 10px; font-size: 12px; }
}
</style>
</head>
<body>

<!-- Nav -->
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

            <a href="../Bookmarks/index.php">
  <i class="fas fa-bookmark"></i> Bookmarks</a>

    </div>
    <div class="nav-right">
    <?php include __DIR__ . '/../auth/notif_bell.php'; ?>

      <button class="icon-btn" id="theme-toggle" aria-label="Toggle theme"><i class="fas fa-moon"></i></button>
      <span class="user-chip">
        <span class="av"><?php echo htmlspecialchars(initials($current_user['full_name'] ?? $current_user['email'] ?? 'U')); ?></span>
        <span><?php echo htmlspecialchars($first_name); ?></span>
      </span>
      <a href="../auth/logout.php" class="icon-btn" title="Sign out"><i class="fas fa-arrow-right-from-bracket"></i></a>
    </div>
  </div>
</nav>

<!-- Hero -->
<section class="hero">
  <div class="hero-inner">
    <div class="hero-greet">
      <i class="fas fa-sparkles"></i>
      Welcome back, <?php echo htmlspecialchars($first_name); ?>
    </div>
    <h1>Browse <span class="gradient-text">event memories</span><br>captured across MediaNest</h1>
    <p class="sub">Every album from every event, in one searchable place. Click into an album to view the full gallery.</p>

    <div class="search-card">
      <form method="get" id="searchForm">
        <div class="search-input">
          <i class="fas fa-magnifying-glass"></i>
          <input type="text" name="q" id="searchInput" placeholder="Search events by name or description…"
                 value="<?php echo htmlspecialchars($search); ?>" autocomplete="off">
          <kbd>/</kbd>
        </div>
        <select name="sort">
          <option value="newest" <?php if($sort==='newest') echo 'selected'; ?>>Newest first</option>
          <option value="oldest" <?php if($sort==='oldest') echo 'selected'; ?>>Oldest first</option>
          <option value="name"   <?php if($sort==='name')   echo 'selected'; ?>>Name A–Z</option>
          <option value="photos" <?php if($sort==='photos') echo 'selected'; ?>>Most photos</option>
        </select>
        <button type="submit" class="btn-go">
          <i class="fas fa-arrow-right"></i>
          Search
        </button>
      </form>
    </div>

    <div class="stats">
      <div class="stat">
        <div class="ic"><i class="fas fa-folder-open"></i></div>
        <div>
          <div class="v"><?php echo number_format($total_events); ?></div>
          <div class="l">Events</div>
        </div>
      </div>
      <div class="stat">
        <div class="ic"><i class="fas fa-images"></i></div>
        <div>
          <div class="v"><?php echo number_format($total_photos); ?></div>
          <div class="l">Photos</div>
        </div>
      </div>
      <?php if ($latest_event): ?>
      <div class="stat">
        <div class="ic"><i class="fas fa-bolt"></i></div>
        <div>
          <div class="v" style="font-size:14px;line-height:1.2;">
            <a href="gallery.php?id=<?php echo (int)$latest_event['albumid']; ?>" style="color:inherit;">
              <?php echo htmlspecialchars(mb_strimwidth($latest_event['name'], 0, 22, '…')); ?>
            </a>
          </div>
          <div class="l">Latest event</div>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- Featured row (only on first page, no search) -->
<div class="page">

  <?php if ($page === 1 && $search === ''):
        // Pick the most-photo'd event as the headline; fall back to newest if photo counts all zero.
        $hero_event = null;
        $h_q = mysqli_query($conn, "SELECT a.*, (SELECT COUNT(*) FROM tbl_gallery g WHERE g.aid=a.albumid AND g.status='process') AS pc
                                    FROM tbl_album a WHERE a.status='process'
                                    ORDER BY pc DESC, a.albumid DESC LIMIT 1");
        if ($h_q) $hero_event = mysqli_fetch_assoc($h_q);
  ?>
  <div class="feature-row">
    <?php if ($hero_event):
        $cover = !empty($hero_event['image']) ? '../admin/acatch/' . rawurlencode($hero_event['image']) : '';
    ?>
    <a href="gallery.php?id=<?php echo (int)$hero_event['albumid']; ?>" class="feature-card">
      <?php if ($cover): ?>
        <div class="cover" style="background-image:url('<?php echo htmlspecialchars($cover); ?>');"></div>
      <?php else: ?>
        <div class="cover-fallback"><i class="fas fa-image"></i></div>
      <?php endif; ?>
      <div class="overlay"></div>
      <div class="content">
        <span class="badge pink"><i class="fas fa-star"></i> Featured Album</span>
        <h3><?php echo htmlspecialchars($hero_event['name']); ?></h3>
        <div class="meta-bar">
          <span><i class="fas fa-images"></i> <?php echo (int)$hero_event['pc']; ?> photo<?php echo $hero_event['pc'] == 1 ? '' : 's'; ?></span>
          <?php if (!empty($hero_event['date'])): ?>
            <span><i class="fas fa-calendar"></i> <?php echo htmlspecialchars($hero_event['date']); ?></span>
          <?php endif; ?>
        </div>
        <?php if (!empty($hero_event['adesc'])): ?>
          <p><?php echo htmlspecialchars(mb_strimwidth($hero_event['adesc'], 0, 160, '…')); ?></p>
        <?php endif; ?>
        <span class="cta"><i class="fas fa-eye"></i> Open album</span>
      </div>
    </a>
    <?php endif; ?>

    <a href="gallery_video.php" class="feature-card special">
      <div class="content">
        <div>
          <span class="badge"><i class="fas fa-film"></i> Special Collection</span>
          <h3>Women's Day '24 — Videos</h3>
          <p style="margin-top:8px;">A curated set of video moments from the International Women's Day celebration.</p>
        </div>
        <span class="cta" style="background:rgba(255,255,255,.95);color:#be185d;align-self:flex-start;margin-top:14px;">
          <i class="fas fa-play"></i> Watch collection
        </span>
      </div>
    </a>
  </div>
  <?php endif; ?>

  <!-- Section header -->
  <div class="section-head">
    <div>
      <h2><?php echo $search !== '' ? 'Search results' : 'All event folders'; ?></h2>
      <div class="meta">
        <?php if ($search !== ''): ?>
          <strong><?php echo number_format($matched); ?></strong>
          result<?php echo $matched == 1 ? '' : 's'; ?> for
          "<strong><?php echo htmlspecialchars($search); ?></strong>"
          · <a href="index.php" style="color:var(--brand-1);">clear</a>
        <?php else: ?>
          Showing <strong><?php echo number_format(min($per, max(0, $matched - $offset))); ?></strong>
          of <strong><?php echo number_format($matched); ?></strong> events
          <?php if ($total_pages > 1): ?> · page <?php echo $page; ?> of <?php echo $total_pages; ?><?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
    <div class="chip-row">
      <a href="?sort=newest<?php if($search) echo '&q='.urlencode($search); ?>" class="chip <?php if($sort==='newest') echo 'active'; ?>"><i class="fas fa-clock"></i> Newest</a>
      <a href="?sort=oldest<?php if($search) echo '&q='.urlencode($search); ?>" class="chip <?php if($sort==='oldest') echo 'active'; ?>"><i class="fas fa-clock-rotate-left"></i> Oldest</a>
      <a href="?sort=name<?php if($search)   echo '&q='.urlencode($search); ?>" class="chip <?php if($sort==='name')   echo 'active'; ?>"><i class="fas fa-arrow-down-a-z"></i> A–Z</a>
      <a href="?sort=photos<?php if($search) echo '&q='.urlencode($search); ?>" class="chip <?php if($sort==='photos') echo 'active'; ?>"><i class="fas fa-images"></i> Photo count</a>
    </div>
  </div>

  <!-- Grid -->
  <?php if ($matched === 0): ?>
    <div class="empty">
      <div class="ic"><i class="fas fa-image"></i></div>
      <h3><?php echo $search !== '' ? 'No events match that search' : 'No events yet'; ?></h3>
      <p>
        <?php if ($search !== ''): ?>
          Try a different keyword, or clear the filter to browse everything.
        <?php else: ?>
          Once events are created in the admin panel, they'll appear here for everyone to browse.
        <?php endif; ?>
      </p>
      <?php if ($search !== ''): ?>
        <a href="index.php" class="reset-btn"><i class="fas fa-rotate-left"></i> Clear search</a>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="grid">
      <?php while ($e = mysqli_fetch_assoc($events)):
        $cover = !empty($e['image']) ? '../admin/acatch/' . rawurlencode($e['image']) : '';
        $count = (int)$e['photo_count'];
      ?>
      <a href="gallery.php?id=<?php echo (int)$e['albumid']; ?>" class="event">
        <div class="event-cover">
          <?php if ($cover): ?>
            <img src="<?php echo htmlspecialchars($cover); ?>" alt="<?php echo htmlspecialchars($e['name']); ?>" loading="lazy" onerror="this.parentNode.innerHTML='<div class=&quot;cover-fallback&quot;><i class=&quot;fas fa-image&quot;></i></div>';">
          <?php else: ?>
            <div class="cover-fallback"><i class="fas fa-image"></i></div>
          <?php endif; ?>
          <div class="glaze"></div>
          <span class="count-badge"><i class="fas fa-images"></i> <?php echo $count; ?></span>
          <span class="open-cta"><i class="fas fa-eye"></i> View album</span>
        </div>
        <div class="event-body">
          <div class="event-name"><?php echo htmlspecialchars($e['name']); ?></div>
          <div class="event-desc"><?php echo htmlspecialchars(!empty($e['adesc']) ? $e['adesc'] : 'No description provided.'); ?></div>
          <div class="event-foot">
            <span class="event-date">
              <i class="fas fa-calendar"></i>
              <?php
                $d = $e['date'] ?? null;
                echo htmlspecialchars($d ? (strtotime($d) ? date('M j, Y', strtotime($d)) : $d) : 'Undated');
              ?>
            </span>
            <span class="view">Open <i class="fas fa-arrow-right"></i></span>
          </div>
        </div>
      </a>
      <?php endwhile; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1):
      $qs = $search !== '' ? '&q=' . urlencode($search) : '';
      $qs .= '&sort=' . urlencode($sort);
    ?>
    <div class="pager">
      <?php if ($page > 1): ?>
        <a href="?page=<?php echo $page-1 . $qs; ?>"><i class="fas fa-chevron-left"></i></a>
      <?php endif; ?>

      <?php
        // Compact pagination: 1 … (p-1) p (p+1) … last
        $window = 1;
        $shown = [];
        for ($i = 1; $i <= $total_pages; $i++) {
            if ($i === 1 || $i === $total_pages || ($i >= $page - $window && $i <= $page + $window)) $shown[] = $i;
        }
        $last = 0;
        foreach ($shown as $i) {
            if ($last && $i - $last > 1) echo '<span class="dots">…</span>';
            if ($i === $page) echo '<span class="current">' . $i . '</span>';
            else              echo '<a href="?page=' . $i . $qs . '">' . $i . '</a>';
            $last = $i;
        }
      ?>

      <?php if ($page < $total_pages): ?>
        <a href="?page=<?php echo $page+1 . $qs; ?>"><i class="fas fa-chevron-right"></i></a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  <?php endif; ?>

  <div class="footer">
    MediaNest · Photo Gallery · Signed in as <?php echo htmlspecialchars($current_user['email'] ?? ''); ?>
  </div>
</div>

<script>
// --- Theme toggle ---
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

// --- Search UX: "/" focuses, debounced autosubmit ---
const searchInput = document.getElementById('searchInput');
const searchForm  = document.getElementById('searchForm');
document.addEventListener('keydown', e => {
  if (e.key === '/' && document.activeElement !== searchInput && !e.ctrlKey && !e.metaKey) {
    e.preventDefault();
    searchInput.focus();
    searchInput.select();
  }
});

let debounce;
searchInput.addEventListener('input', () => {
  clearTimeout(debounce);
  debounce = setTimeout(() => searchForm.submit(), 450);
});

// Sort change → submit immediately
searchForm.querySelector('select[name=sort]').addEventListener('change', () => searchForm.submit());
</script>

</body>
</html>
