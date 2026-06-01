<?php
require_once __DIR__ . '/../auth/auth.php';
requireLogin();
$conn = mysqli_connect('localhost','root','','s&p');
$current_user = currentUser();

$limit = 12;
$page_number = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$initial_page = ($page_number - 1) * $limit;

$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$tab    = isset($_GET['tab']) ? trim($_GET['tab']) : 'all';

// Real categories from video_categories table
$cats = [];
$cres = @mysqli_query($conn, "SELECT id, name, slug FROM video_categories ORDER BY sort_order, name");
if ($cres) while ($r = mysqli_fetch_assoc($cres)) $cats[$r['slug']] = $r;

// Find selected category by slug
$sel_cat_id = null;
if ($tab !== 'all' && isset($cats[$tab])) $sel_cat_id = (int)$cats[$tab]['id'];

$where_parts = ['1=1'];
$bind_types  = '';
$bind_params = [];
if ($search !== '') {
    $where_parts[] = "(v.title LIKE ? OR v.des LIKE ?)";
    $like = '%' . $search . '%';
    $bind_types .= 'ss';
    $bind_params[] = $like;
    $bind_params[] = $like;
}
if ($sel_cat_id !== null) {
    $where_parts[] = "v.category_id = ?";
    $bind_types .= 'i';
    $bind_params[] = $sel_cat_id;
}
$where = 'WHERE ' . implode(' AND ', $where_parts);

$sql = "SELECT v.*,
             (SELECT COUNT(*) FROM video_quizzes WHERE video_id = v.id) AS quiz_count,
             c.name AS cat_name, c.slug AS cat_slug
      FROM video v
      LEFT JOIN video_categories c ON c.id = v.category_id
      $where
      ORDER BY v.id DESC
      LIMIT ?, ?";
$stmt = mysqli_prepare($conn, $sql);
$exec_types  = $bind_types . 'ii';
$exec_params = array_merge($bind_params, [$initial_page, $limit]);
mysqli_stmt_bind_param($stmt, $exec_types, ...$exec_params);
mysqli_stmt_execute($stmt);
$query = mysqli_stmt_get_result($stmt);

$total_videos = mysqli_fetch_array(mysqli_query($conn, "SELECT COUNT(*) FROM video"))[0];
$total_checkpoints = mysqli_fetch_array(mysqli_query($conn, "SELECT COUNT(*) FROM video_quizzes"))[0];

// Continue Watching — last 8 unfinished videos for this user
$continue_watching = [];
if ($current_user) {
    $uid = (int)$current_user['id'];
    $stmt = mysqli_prepare($conn,
        "SELECT v.id, v.name, v.title, v.des, c.name AS cat_name,
                p.last_position, p.duration_sec, p.progress_pct
         FROM video_progress p
         JOIN video v ON v.id = p.video_id
         LEFT JOIN video_categories c ON c.id = v.category_id
         WHERE p.user_id = ? AND p.completed = 0 AND p.progress_pct >= 2 AND p.progress_pct < 90
         ORDER BY p.last_watched_at DESC
         LIMIT 8");
    mysqli_stmt_bind_param($stmt, 'i', $uid);
    mysqli_stmt_execute($stmt);
    $r = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($r)) $continue_watching[] = $row;
    mysqli_stmt_close($stmt);
}

// Count per category
$cat_counts = [];
$ccres = @mysqli_query($conn, "SELECT category_id, COUNT(*) AS n FROM video GROUP BY category_id");
if ($ccres) while ($r = mysqli_fetch_assoc($ccres)) $cat_counts[(int)$r['category_id']] = (int)$r['n'];

// Count for pagination — same filter
$count_sql = "SELECT COUNT(*) FROM video v $where";
$cstmt = mysqli_prepare($conn, $count_sql);
if ($bind_types !== '') mysqli_stmt_bind_param($cstmt, $bind_types, ...$bind_params);
mysqli_stmt_execute($cstmt);
$cres = mysqli_stmt_get_result($cstmt);
$total_pages = ceil(mysqli_fetch_array($cres)[0] / $limit);
mysqli_stmt_close($cstmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Video Library — MediaNest</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/js/all.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>

<style>
:root {
  --bg: #f6f7fb; --bg-elev: #ffffff;
  --text: #0f172a; --text-soft: #475569; --muted: #94a3b8;
  --border: rgba(15, 23, 42, 0.08);
  --shadow-sm: 0 2px 8px rgba(15, 23, 42, 0.06);
  --shadow-md: 0 10px 30px rgba(15, 23, 42, 0.08);
  --shadow-lg: 0 20px 60px rgba(15, 23, 42, 0.12);
  --brand-1: #0ea5e9; --brand-2: #6366f1;
  --radius: 16px; --radius-lg: 22px;
  --grad-brand: linear-gradient(135deg, #06b6d4, #0ea5e9);
  --grad-text: linear-gradient(135deg, #0ea5e9, #6366f1 50%, #a855f7);
  --training-1: #6366f1; --training-2: #8b5cf6;
  --training-soft: rgba(99, 102, 241, 0.1);
  --event-1: #f59e0b; --event-2: #ef4444;
  --event-soft: rgba(245, 158, 11, 0.1);
}
html.dark {
  --bg: #0a0e1a; --bg-elev: #131826;
  --text: #e2e8f0; --text-soft: #cbd5e1; --muted: #64748b;
  --border: rgba(255, 255, 255, 0.08);
  --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.3);
  --shadow-md: 0 10px 30px rgba(0, 0, 0, 0.4);
  --shadow-lg: 0 20px 60px rgba(0, 0, 0, 0.5);
  --training-soft: rgba(139, 92, 246, 0.15);
  --event-soft: rgba(245, 158, 11, 0.15);
}

* { margin: 0; padding: 0; box-sizing: border-box; }
html { scroll-behavior: smooth; }
body { font-family: 'Inter', sans-serif; color: var(--text); background: var(--bg); min-height: 100vh; transition: background .4s, color .4s; }
h1, h2, h3, h4 { font-family: 'Sora', sans-serif; letter-spacing: -0.02em; }
a { color: inherit; text-decoration: none; }

#scroll-progress {
  position: fixed; top: 0; left: 0; height: 3px; width: 0%;
  background: var(--grad-brand); z-index: 100;
  transition: width .1s linear; box-shadow: 0 0 10px rgba(6, 182, 212, 0.5);
}

/* NAV */
.mn-nav {
  position: sticky; top: 0; z-index: 50;
  backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px);
  background: color-mix(in srgb, var(--bg) 75%, transparent);
  border-bottom: 1px solid var(--border);
}
.mn-nav-inner { max-width: 1280px; margin: 0 auto; padding: 14px 24px; display: flex; align-items: center; justify-content: space-between; }
.mn-logo { display: flex; align-items: center; gap: 10px; font-family: 'Sora', sans-serif; font-weight: 700; font-size: 20px; }
.mn-logo-mark { width: 36px; height: 36px; border-radius: 10px; background: var(--grad-brand); display: grid; place-items: center; color: white; box-shadow: 0 6px 18px rgba(6, 182, 212, 0.4); }
.mn-logo-text span { background: var(--grad-text); -webkit-background-clip: text; background-clip: text; color: transparent; }
.nav-links { display: flex; gap: 4px; }
.nav-links a { padding: 8px 14px; border-radius: 8px; font-size: 14px; font-weight: 500; color: var(--text-soft); transition: all .2s ease; }
.nav-links a:hover { background: var(--bg-elev); color: var(--text); }
.nav-links a.active { background: var(--grad-brand); color: white; box-shadow: 0 4px 12px rgba(6, 182, 212, 0.3); }
.mn-icon-btn { width: 40px; height: 40px; border-radius: 10px; background: transparent; border: 1px solid var(--border); color: var(--text); cursor: pointer; display: grid; place-items: center; transition: all .2s ease; }
.mn-icon-btn:hover { background: var(--bg-elev); transform: translateY(-1px); }
.nav-right { display: flex; align-items: center; gap: 10px; }
.user-chip { display: inline-flex; align-items: center; gap: 8px; padding: 6px 12px 6px 6px; border-radius: 999px; background: var(--bg-elev); border: 1px solid var(--border); font-size: 13px; font-weight: 500; box-shadow: var(--shadow-sm); }
.user-chip .av { width: 28px; height: 28px; border-radius: 50%; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; font-weight: 700; font-size: 12px; display: grid; place-items: center; }
.btn-signin { display: inline-flex; align-items: center; gap: 7px; padding: 8px 14px; border-radius: 10px; background: var(--grad-brand); color: white; font-size: 13px; font-weight: 600; box-shadow: 0 4px 12px rgba(6, 182, 212, 0.3); }

/* HERO */
.hero { position: relative; overflow: hidden; padding: 56px 24px 28px; text-align: center; }
.hero-bg { position: absolute; inset: 0; z-index: 0; pointer-events: none; }
.hero-orb { position: absolute; border-radius: 50%; filter: blur(80px); opacity: 0.4; animation: float 14s ease-in-out infinite; }
.hero-orb-1 { width: 340px; height: 340px; background: var(--brand-1); top: -100px; left: -80px; }
.hero-orb-2 { width: 360px; height: 360px; background: var(--brand-2); top: -120px; right: -80px; animation-delay: -7s; }
html.dark .hero-orb { opacity: 0.3; }
@keyframes float { 0%,100% { transform: translate(0,0);} 50% { transform: translate(30px,20px);} }
.hero-inner { position: relative; z-index: 1; max-width: 900px; margin: 0 auto; }
.hero-badge { display: inline-flex; align-items: center; gap: 8px; padding: 6px 14px; border-radius: 999px; background: var(--bg-elev); border: 1px solid var(--border); font-size: 13px; font-weight: 500; color: var(--text-soft); box-shadow: var(--shadow-sm); margin-bottom: 18px; }
.pulse-dot { width: 8px; height: 8px; border-radius: 50%; background: #ef4444; box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); animation: pulse 2s infinite; }
@keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); } 70% { box-shadow: 0 0 0 12px rgba(239, 68, 68, 0); } 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); } }
.hero h1 { font-size: clamp(34px, 5vw, 52px); font-weight: 800; margin-bottom: 12px; line-height: 1.1; }
.gradient-text { background: var(--grad-text); -webkit-background-clip: text; background-clip: text; color: transparent; }
.hero p { color: var(--text-soft); font-size: 17px; max-width: 600px; margin: 0 auto; }

/* STATS */
.stats-row { max-width: 880px; margin: 28px auto 0; display: flex; align-items: center; justify-content: center; gap: 12px; background: var(--bg-elev); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 18px 28px; box-shadow: var(--shadow-md); position: relative; z-index: 1; }
.stat-cell { flex: 1; text-align: center; min-width: 0; }
.stat-cell .num { font-family: 'Sora', sans-serif; font-weight: 800; font-size: 26px; background: var(--grad-text); -webkit-background-clip: text; background-clip: text; color: transparent; line-height: 1.1; }
.stat-cell .lbl { font-size: 12px; color: var(--text-soft); margin-top: 4px; font-weight: 500; }
.stat-vline { width: 1px; height: 34px; background: var(--border); }

/* TABS */
.tabs-wrap { max-width: 1280px; margin: 36px auto 0; padding: 0 24px; }
.tabs { display: inline-flex; gap: 4px; padding: 5px; background: var(--bg-elev); border: 1px solid var(--border); border-radius: 14px; box-shadow: var(--shadow-sm); }
.tab { display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 10px; background: transparent; border: none; cursor: pointer; font-family: inherit; font-size: 14px; font-weight: 600; color: var(--text-soft); transition: all .2s ease; }
.tab i { font-size: 13px; }
.tab .count { font-size: 11px; padding: 2px 7px; border-radius: 999px; background: var(--bg); color: var(--muted); font-weight: 700; }
.tab:hover { color: var(--text); }
.tab.active { color: white; background: var(--grad-brand); box-shadow: 0 4px 12px rgba(6, 182, 212, 0.3); }
.tab.active .count { background: rgba(255,255,255,0.25); color: white; }

/* TOOLBAR */
.toolbar { max-width: 1280px; margin: 20px auto 0; padding: 0 24px; display: flex; flex-wrap: wrap; gap: 14px; align-items: center; }
.search-form { flex: 1; min-width: 240px; max-width: 480px; display: flex; align-items: center; gap: 10px; padding: 10px 16px; background: var(--bg-elev); border: 1px solid var(--border); border-radius: 12px; box-shadow: var(--shadow-sm); transition: border-color .2s, box-shadow .2s; }
.search-form:focus-within { border-color: var(--brand-1); box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.12); }
.search-form i { color: var(--muted); }
.search-form input { flex: 1; border: none; background: transparent; outline: none; font-family: inherit; font-size: 14px; color: var(--text); }
.kbd-hint { font-size: 11px; padding: 3px 8px; border-radius: 6px; background: var(--bg); border: 1px solid var(--border); color: var(--muted); }
.view-toggle { display: inline-flex; background: var(--bg-elev); border: 1px solid var(--border); border-radius: 10px; padding: 3px; margin-left: auto; }
.view-toggle button { width: 34px; height: 32px; border: none; background: transparent; color: var(--text-soft); cursor: pointer; border-radius: 7px; display: grid; place-items: center; transition: all .2s ease; }
.view-toggle button.active { background: var(--grad-brand); color: white; }

/* CONTINUE WATCHING */
.cw-section { max-width: 1280px; margin: 18px auto 0; padding: 0 24px; }
.cw-head { margin-bottom: 14px; }
.cw-head h2 { font-size: 18px; font-weight: 800; margin: 0; display: inline-flex; align-items: center; gap: 9px; }
.cw-head h2 i { color: var(--brand-1); }
.cw-head p { font-size: 12px; color: var(--text-soft); margin: 3px 0 0; }
.cw-row { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 14px; }
.cw-card { background: var(--bg-elev); border: 1px solid var(--border); border-radius: 14px; overflow: hidden; transition: transform .2s, box-shadow .2s, border-color .2s; }
.cw-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); border-color: rgba(14,165,233,.4); }
.cw-thumb { position: relative; aspect-ratio: 16/9; background: #000; overflow: hidden; }
.cw-thumb video { width: 100%; height: 100%; object-fit: cover; display: block; }
.cw-play { position: absolute; inset: 0; display: grid; place-items: center; background: rgba(0,0,0,.25); transition: background .2s; }
.cw-play i { width: 44px; height: 44px; border-radius: 50%; background: rgba(255,255,255,.95); color: var(--brand-1); display: grid; place-items: center; font-size: 14px; box-shadow: 0 6px 20px rgba(0,0,0,.3); transition: transform .2s; }
.cw-card:hover .cw-play { background: rgba(0,0,0,.1); }
.cw-card:hover .cw-play i { transform: scale(1.12); }
.cw-resume-time { position: absolute; top: 8px; right: 8px; padding: 3px 9px; border-radius: 999px; background: rgba(0,0,0,.75); color: white; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; backdrop-filter: blur(6px); font-family: ui-monospace, monospace; }
.cw-resume-time::before { content: '\f1da'; font-family: 'Font Awesome 6 Free'; font-weight: 900; font-size: 9px; }
.cw-progress { position: absolute; bottom: 0; left: 0; right: 0; height: 4px; background: rgba(0,0,0,.4); }
.cw-progress-fill { height: 100%; background: linear-gradient(90deg, var(--brand-1), var(--brand-2)); transition: width .3s; }
.cw-body { padding: 11px 13px 13px; }
.cw-title { font-weight: 700; font-size: 14px; line-height: 1.3; margin-bottom: 5px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; min-height: 2.6em; }
.cw-meta { display: flex; gap: 6px; align-items: center; font-size: 11px; color: var(--text-soft); }
.cw-cat { padding: 1px 7px; border-radius: 999px; background: rgba(14,165,233,.12); color: var(--brand-1); font-weight: 700; text-transform: uppercase; letter-spacing: .04em; font-size: 10px; }
.cw-left { color: var(--text-soft); }

/* AI SMART SEARCH */
.ai-search-card { max-width: 1280px; margin: 14px auto 0; padding: 0 24px; }
.ai-search-card > div { background: var(--bg-elev); border: 1px solid var(--border); border-radius: 16px; box-shadow: var(--shadow-sm); overflow: hidden; }
.ai-search-head { padding: 14px 18px; display: flex; align-items: center; justify-content: space-between; gap: 12px; background: linear-gradient(135deg, rgba(168,85,247,.06), rgba(236,72,153,.06)); border-bottom: 1px solid var(--border); }
.ai-search-title { display: flex; align-items: center; gap: 12px; }
.ai-icon { width: 38px; height: 38px; border-radius: 10px; background: linear-gradient(135deg, #a855f7, #ec4899); color: white; display: grid; place-items: center; box-shadow: 0 6px 18px rgba(168,85,247,.3); flex-shrink: 0; }
.ai-search-title h3 { font-size: 15px; font-weight: 700; margin: 0; line-height: 1.2; }
.ai-search-title p { font-size: 12px; color: var(--text-soft); margin: 2px 0 0; line-height: 1.3; }
.ai-collapse-btn { width: 32px; height: 32px; border-radius: 8px; background: transparent; border: 1px solid var(--border); color: var(--text-soft); cursor: pointer; display: grid; place-items: center; transition: all .15s; }
.ai-collapse-btn:hover { color: #a855f7; border-color: #a855f7; }
.ai-search-card.collapsed .ai-search-body { display: none; }
.ai-search-card.collapsed .ai-collapse-btn i { transform: rotate(180deg); }

.ai-search-body { padding: 16px 18px; }
.ai-search-input { display: flex; align-items: center; gap: 10px; padding: 10px 14px; background: var(--bg); border: 1px solid var(--border); border-radius: 12px; transition: all .15s; }
.ai-search-input:focus-within { border-color: #a855f7; box-shadow: 0 0 0 3px rgba(168,85,247,.12); }
.ai-search-input > i { color: var(--muted); }
.ai-search-input input { flex: 1; border: 0; background: transparent; outline: none; font: inherit; font-size: 15px; color: var(--text); }
.ai-search-input button { padding: 8px 16px; border-radius: 9px; border: 0; background: linear-gradient(135deg, #a855f7, #ec4899); color: white; font: inherit; font-size: 13px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: transform .15s; }
.ai-search-input button:hover { transform: translateY(-1px); }
.ai-search-input button:disabled { opacity: .55; cursor: not-allowed; transform: none; }
.ai-search-input button .spin { animation: ai-spin 1s linear infinite; }
@keyframes ai-spin { to { transform: rotate(360deg); } }

.ai-search-examples { display: flex; gap: 6px; margin-top: 10px; flex-wrap: wrap; align-items: center; font-size: 12px; color: var(--text-soft); }
.ai-search-examples button { padding: 4px 11px; border-radius: 999px; border: 1px solid var(--border); background: var(--bg); color: var(--text-soft); font: inherit; font-size: 11px; cursor: pointer; transition: all .15s; }
.ai-search-examples button:hover { color: #a855f7; border-color: rgba(168,85,247,.4); background: rgba(168,85,247,.05); }

.ai-search-results { margin-top: 14px; display: flex; flex-direction: column; gap: 12px; }
.ai-empty { padding: 22px 18px; text-align: center; color: var(--muted); font-size: 13px; border: 1px dashed var(--border); border-radius: 12px; }
.ai-empty i { display: block; font-size: 24px; opacity: .4; margin-bottom: 6px; }
.ai-vid-result { border: 1px solid var(--border); border-radius: 12px; padding: 14px 16px; transition: all .15s; }
.ai-vid-result:hover { border-color: rgba(168,85,247,.35); background: linear-gradient(135deg, rgba(168,85,247,.03), rgba(236,72,153,.02)); }
.ai-vid-head { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; flex-wrap: wrap; }
.ai-vid-head .vt { font-weight: 700; font-size: 14px; flex: 1; min-width: 0; }
.ai-vid-head .vt a { color: var(--text); }
.ai-vid-head .vt a:hover { color: #a855f7; }
.ai-vid-cat { padding: 2px 8px; border-radius: 999px; background: rgba(168,85,247,.12); color: #a855f7; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
.ai-vid-score { font-size: 11px; color: var(--muted); font-weight: 600; }
.ai-moments { display: flex; flex-direction: column; gap: 6px; }
.ai-moment { display: flex; align-items: flex-start; gap: 10px; padding: 8px 10px; border-radius: 9px; background: var(--bg); cursor: pointer; transition: background .15s; text-decoration: none; color: inherit; }
.ai-moment:hover { background: rgba(168,85,247,.08); }
.ai-moment .tt { padding: 3px 9px; border-radius: 999px; background: linear-gradient(135deg, #a855f7, #ec4899); color: white; font-size: 11px; font-weight: 700; font-family: ui-monospace, monospace; flex-shrink: 0; display: inline-flex; align-items: center; gap: 5px; }
.ai-moment .snip { font-size: 13px; line-height: 1.5; color: var(--text-soft); flex: 1; }
.ai-moment .snip mark { background: rgba(245,158,11,.3); color: var(--text); padding: 0 2px; border-radius: 3px; font-weight: 600; }
html.dark .ai-moment .snip mark { background: rgba(245,158,11,.25); color: #fef3c7; }

/* SECTION HEADER */
.section-block { max-width: 1280px; margin: 0 auto; padding: 36px 24px 8px; }
.section-head-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 22px; flex-wrap: wrap; gap: 10px; }
.section-head-bar h2 { font-size: 22px; display: flex; align-items: center; gap: 10px; }
.label-pill { font-family: 'Inter', sans-serif; font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 999px; letter-spacing: .04em; display: inline-flex; align-items: center; gap: 5px; }
.label-training { background: var(--training-soft); color: var(--training-1); }
.label-events { background: var(--event-soft); color: var(--event-1); }
.section-head-bar p { font-size: 13px; color: var(--text-soft); }

/* GRID */
.video-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); gap: 24px; }
.video-grid.list-view { grid-template-columns: 1fr; }
.video-grid.list-view .vid-card { display: grid; grid-template-columns: 280px 1fr; gap: 0; }
.video-grid.list-view .vid-info { padding: 22px; }
@media (max-width: 700px) { .video-grid.list-view .vid-card { grid-template-columns: 1fr; } }

/* CARD */
.vid-card {
  position: relative; display: block; overflow: hidden;
  background: var(--bg-elev);
  border-radius: var(--radius);
  border: 1px solid var(--border);
  box-shadow: var(--shadow-sm);
  transition: transform .35s cubic-bezier(.2,.8,.2,1), box-shadow .35s, border-color .35s;
  will-change: transform;
}
.vid-card:hover { transform: translateY(-6px); box-shadow: var(--shadow-lg); }
.vid-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; z-index: 2; }
.vid-card.training::before { background: linear-gradient(90deg, var(--training-1), var(--training-2)); }
.vid-card.event::before { background: linear-gradient(90deg, var(--event-1), var(--event-2)); }
.vid-card.training:hover { border-color: color-mix(in srgb, var(--training-1) 35%, transparent); }
.vid-card.event:hover { border-color: color-mix(in srgb, var(--event-1) 35%, transparent); }

.vid-thumb { position: relative; width: 100%; aspect-ratio: 16/9; background: linear-gradient(135deg, #0f172a, #1e293b); overflow: hidden; }
.vid-thumb video { width: 100%; height: 100%; object-fit: cover; transition: transform .5s; }
.vid-card:hover .vid-thumb video { transform: scale(1.06); }
.thumb-gradient { position: absolute; inset: 0; background: linear-gradient(to top, rgba(0,0,0,0.55), transparent 50%); pointer-events: none; opacity: 0; transition: opacity .25s; }
.vid-card:hover .thumb-gradient { opacity: 1; }
.play-badge { position: absolute; inset: 0; display: grid; place-items: center; background: rgba(0, 8, 20, 0.4); backdrop-filter: blur(2px); opacity: 0; transition: opacity .25s; }
.vid-card:hover .play-badge { opacity: 1; }
.play-icon { width: 64px; height: 64px; border-radius: 50%; background: rgba(255, 255, 255, 0.95); display: grid; place-items: center; font-size: 22px; padding-left: 4px; box-shadow: 0 8px 24px rgba(0,0,0,0.4); transform: scale(0.85); transition: transform .3s ease; }
.vid-card.training .play-icon { color: var(--training-1); }
.vid-card.event .play-icon { color: var(--event-1); }
.vid-card:hover .play-icon { transform: scale(1); }

.type-badge { position: absolute; top: 12px; left: 12px; z-index: 3; display: inline-flex; align-items: center; gap: 5px; padding: 5px 11px; border-radius: 999px; font-family: 'Sora', sans-serif; font-size: 10px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: white; }
.type-badge.training { background: linear-gradient(135deg, var(--training-1), var(--training-2)); box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4); }
.type-badge.event { background: linear-gradient(135deg, var(--event-1), var(--event-2)); box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4); }

.checkpoint-badge { position: absolute; top: 12px; right: 12px; z-index: 3; display: inline-flex; align-items: center; gap: 6px; background: rgba(255, 255, 255, 0.95); color: var(--training-1); font-family: 'Sora', sans-serif; font-size: 12px; font-weight: 700; padding: 5px 11px; border-radius: 999px; box-shadow: 0 4px 12px rgba(0,0,0,0.18); }

.live-badge { position: absolute; top: 12px; right: 12px; z-index: 3; display: inline-flex; align-items: center; gap: 6px; background: #ef4444; color: white; font-family: 'Sora', sans-serif; font-size: 11px; font-weight: 700; padding: 5px 11px; border-radius: 999px; letter-spacing: .06em; text-transform: uppercase; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4); }
.live-badge::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: white; animation: pulse-white 1.4s infinite; }
@keyframes pulse-white { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }

.vid-info { padding: 16px 18px 18px; }
.vid-meta-row { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
.vid-avatar { width: 36px; height: 36px; border-radius: 50%; display: grid; place-items: center; color: white; font-weight: 700; font-size: 13px; border: 2px solid var(--bg-elev); flex-shrink: 0; }
.vid-card.training .vid-avatar { background: linear-gradient(135deg, var(--training-1), var(--training-2)); }
.vid-card.event .vid-avatar { background: linear-gradient(135deg, var(--event-1), var(--event-2)); }
.vid-meta-text { flex: 1; min-width: 0; }
.vid-title { font-family: 'Sora', sans-serif; font-size: 15px; font-weight: 700; color: var(--text); line-height: 1.35; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.vid-bm-wrap { position: absolute; top: 10px; right: 10px; z-index: 5; }
.vid-bm-wrap .mn-bm { background: rgba(0,0,0,.55); backdrop-filter: blur(6px); border-color: transparent; color: white; }
.vid-bm-wrap .mn-bm:hover { background: rgba(0,0,0,.75); color: #fbbf24; }
.vid-channel { font-size: 12px; color: var(--text-soft); margin-top: 2px; display: flex; align-items: center; gap: 6px; }
.vid-channel i { font-size: 10px; }
.vid-desc { font-size: 13px; color: var(--text-soft); line-height: 1.55; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; margin-bottom: 14px; }

/* Training checkpoint visual */
.checkpoint-track { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 10px; background: var(--training-soft); margin-top: 4px; }
.checkpoint-track .ck-label { font-size: 11px; font-weight: 700; color: var(--training-1); text-transform: uppercase; letter-spacing: .05em; white-space: nowrap; }
.ck-dots { flex: 1; height: 8px; display: flex; align-items: center; gap: 4px; }
.ck-dot { flex: 1; height: 6px; border-radius: 4px; background: color-mix(in srgb, var(--training-1) 22%, transparent); }
.ck-dot.filled { background: linear-gradient(90deg, var(--training-1), var(--training-2)); }
.checkpoint-track .ck-count { font-size: 11px; font-weight: 700; color: var(--training-1); white-space: nowrap; }

.event-stats { display: flex; align-items: center; gap: 14px; font-size: 12px; color: var(--text-soft); padding: 8px 0; }
.event-stats span { display: inline-flex; align-items: center; gap: 5px; }
.event-stats i { font-size: 11px; color: var(--event-1); }

/* EMPTY */
.empty { max-width: 480px; margin: 60px auto; text-align: center; padding: 50px 30px; background: var(--bg-elev); border: 1px solid var(--border); border-radius: var(--radius-lg); }
.empty-icon { width: 70px; height: 70px; margin: 0 auto 18px; border-radius: 20px; background: var(--grad-brand); display: grid; place-items: center; color: white; font-size: 30px; box-shadow: 0 10px 28px rgba(6, 182, 212, 0.35); }
.empty h3 { font-size: 20px; margin-bottom: 8px; }
.empty p { color: var(--text-soft); font-size: 14px; margin-bottom: 18px; }
.btn-cta { display: inline-flex; align-items: center; gap: 8px; padding: 10px 22px; border-radius: 10px; background: var(--grad-brand); color: white; font-weight: 600; font-size: 14px; box-shadow: 0 6px 18px rgba(6, 182, 212, 0.3); }

/* PAGINATION */
.pagination { display: flex; justify-content: center; gap: 6px; padding: 40px 24px 60px; flex-wrap: wrap; }
.pagination a { padding: 9px 14px; border-radius: 10px; font-size: 14px; font-weight: 600; color: var(--text-soft); background: var(--bg-elev); border: 1px solid var(--border); transition: all .2s ease; display: inline-flex; align-items: center; gap: 6px; }
.pagination a:hover { color: var(--text); transform: translateY(-1px); }
.pagination a.active { background: var(--grad-brand); color: white; border-color: transparent; box-shadow: 0 4px 12px rgba(6, 182, 212, 0.3); }

/* TOAST */
.toast { position: fixed; bottom: 28px; left: 50%; transform: translateX(-50%) translateY(20px); background: var(--text); color: var(--bg); padding: 12px 22px; border-radius: 12px; font-size: 14px; font-weight: 500; box-shadow: var(--shadow-lg); z-index: 300; opacity: 0; transition: opacity .25s, transform .25s; }
.toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }

@media (max-width: 800px) {
  .nav-links { display: none; }
  .stats-row { padding: 14px 16px; gap: 8px; }
  .stat-cell .num { font-size: 20px; }
  .toolbar { flex-direction: column; align-items: stretch; }
  .search-form { max-width: 100%; }
  .view-toggle { margin-left: 0; align-self: flex-start; }
  .tabs { width: 100%; overflow-x: auto; }
}
</style>
</head>
<body>

<div id="scroll-progress"></div>

<nav class="mn-nav">
  <div class="mn-nav-inner">
    <a href="../index.html" class="mn-logo">
      <span class="mn-logo-mark"><i class="fas fa-cube"></i></span>
      <span class="mn-logo-text">Media<span>Nest</span></span>
    </a>
    <div class="nav-links">
      <a href="../index.php"><i class="fas fa-house"></i> Home</a>
   

      <a href="index.php" class="nav-link active"><i class="fas fa-video"></i> Videos</a>
      <a href="../Photo/index.php" class=""><i class="fas fa-images"></i> Photos</a>
      <a href="../Documents/index.php"><i class="fas fa-folder-open"></i> Documents</a>

            <a href="../Bookmarks/index.php">
  <i class="fas fa-bookmark"></i> Bookmarks</a>
    </div>
    <div class="nav-right">
      <?php if ($current_user): ?>
        <?php include __DIR__ . '/../auth/notif_bell.php'; ?>
        <div class="user-chip">
          <span class="av"><?php echo strtoupper(substr($current_user['full_name'], 0, 1)); ?></span>
          <span><?php echo htmlspecialchars($current_user['full_name']); ?></span>
        </div>
        <a href="../auth/logout.php" class="mn-icon-btn" title="Sign out"><i class="fas fa-arrow-right-from-bracket"></i></a>
      <?php else: ?>
        <a href="../auth/login.php?return=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="btn-signin">
          <i class="fas fa-arrow-right-to-bracket"></i> Sign in
        </a>
      <?php endif; ?>
      <button id="theme-toggle" class="mn-icon-btn" title="Toggle theme"><i class="fas fa-moon"></i></button>
    </div>
  </div>
</nav>

<section class="hero">
  <div class="hero-bg">
    <div class="hero-orb hero-orb-1"></div>
    <div class="hero-orb hero-orb-2"></div>
  </div>
  <div class="hero-inner">
    <div class="hero-badge">
      <span class="pulse-dot"></span>
      Live library · <?php echo (int)$total_videos; ?> videos
    </div>
    <h1>One library, <span class="gradient-text">two ways to watch</span></h1>
    <p>Sharpen your skills with interactive training videos — each with checkpoint quizzes built in. Or jump into events, recordings, and live streams. Pick your mode below.</p>
  </div>

  <div class="stats-row">
    <div class="stat-cell">
      <div class="num" data-target="<?php echo (int)$total_training; ?>">0</div>
      <div class="lbl">Training videos</div>
    </div>
    <div class="stat-vline"></div>
    <div class="stat-cell">
      <div class="num" data-target="<?php echo (int)$total_checkpoints; ?>">0</div>
      <div class="lbl">Quiz checkpoints</div>
    </div>
    <div class="stat-vline"></div>
    <div class="stat-cell">
      <div class="num" data-target="<?php echo (int)$total_events; ?>">0</div>
      <div class="lbl">Events &amp; streams</div>
    </div>
  </div>
</section>

<div class="tabs-wrap">
  <div class="tabs" role="tablist">
    <button class="tab <?php echo $tab === 'all' ? 'active' : ''; ?>" data-tab="all">
      <i class="fas fa-layer-group"></i> All
      <span class="count"><?php echo (int)$total_videos; ?></span>
    </button>
    <?php foreach ($cats as $slug => $c):
      $cn = $cat_counts[(int)$c['id']] ?? 0;
      $icon = match ($slug) {
        'training'  => 'fa-graduation-cap',
        'events'    => 'fa-calendar-day',
        'tutorials' => 'fa-chalkboard-user',
        'webinars'  => 'fa-tower-broadcast',
        default     => 'fa-folder',
      };
    ?>
      <button class="tab <?php echo $tab === $slug ? 'active' : ''; ?>" data-tab="<?php echo htmlspecialchars($slug); ?>">
        <i class="fas <?php echo $icon; ?>"></i> <?php echo htmlspecialchars($c['name']); ?>
        <span class="count"><?php echo $cn; ?></span>
      </button>
    <?php endforeach; ?>
  </div>
</div>

<!-- Continue Watching -->
<?php if (!empty($continue_watching)): ?>
<section class="cw-section">
  <div class="cw-head">
    <h2><i class="fas fa-clock-rotate-left"></i> Continue watching</h2>
    <p>Pick up right where you left off</p>
  </div>
  <div class="cw-row">
    <?php foreach ($continue_watching as $cw):
      $mins_left = (int) round(($cw['duration_sec'] - $cw['last_position']) / 60);
      $mins_in   = (int) floor($cw['last_position'] / 60);
      $secs_in   = (int) ($cw['last_position'] % 60);
    ?>
      <a class="cw-card" href="video_player.php?id=<?php echo (int)$cw['id']; ?>">
        <div class="cw-thumb">
          <video preload="metadata" muted playsinline>
            <source src="../admin/upload/<?php echo htmlspecialchars($cw['name']); ?>#t=<?php echo max(1, (int)$cw['last_position']); ?>">
          </video>
          <div class="cw-play"><i class="fas fa-play"></i></div>
          <span class="cw-resume-time"><?php echo $mins_in; ?>:<?php echo str_pad($secs_in, 2, '0', STR_PAD_LEFT); ?></span>
          <div class="cw-progress"><div class="cw-progress-fill" style="width: <?php echo (int)$cw['progress_pct']; ?>%"></div></div>
        </div>
        <div class="cw-body">
          <div class="cw-title"><?php echo htmlspecialchars($cw['title']); ?></div>
          <div class="cw-meta">
            <?php if (!empty($cw['cat_name'])): ?><span class="cw-cat"><?php echo htmlspecialchars($cw['cat_name']); ?></span><?php endif; ?>
            <span class="cw-left"><?php echo $mins_left > 0 ? "$mins_left min left" : 'Almost done'; ?></span>
          </div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<!-- AI Smart Search -->
<div class="ai-search-card">
  <div class="ai-search-head">
    <div class="ai-search-title">
      <span class="ai-icon"><i class="fas fa-wand-magic-sparkles"></i></span>
      <div>
        <h3>Smart video search</h3>
        <p>Ask anything in plain English — jumps straight to the moment in the video</p>
      </div>
    </div>
    <button type="button" class="ai-collapse-btn" id="aiCollapse" title="Hide / show">
      <i class="fas fa-chevron-up"></i>
    </button>
  </div>
  <div class="ai-search-body" id="aiSearchBody">
    <div class="ai-search-input">
      <i class="fas fa-magnifying-glass"></i>
      <input type="text" id="aiQuery" placeholder='e.g. "what does the policy say about overtime?"' autocomplete="off">
      <button type="button" id="aiAskBtn"><i class="fas fa-arrow-right"></i> Ask</button>
    </div>
    <div class="ai-search-examples" id="aiExamples">
      <span>Try:</span>
      <button type="button" data-q="introduction">introduction</button>
      <button type="button" data-q="how does it work">how does it work</button>
      <button type="button" data-q="conclusion">conclusion</button>
    </div>
    <div class="ai-search-results" id="aiResults"></div>
  </div>
</div>

<form class="toolbar" method="get" action="index.php" id="filter-form">
  <div class="search-form">
    <i class="fas fa-search"></i>
    <input type="text" name="q" placeholder="Search videos by title or topic…" value="<?php echo htmlspecialchars($search); ?>" autocomplete="off"/>
    <span class="kbd-hint">/</span>
  </div>
  <input type="hidden" name="tab" id="tab-input" value="<?php echo htmlspecialchars($tab); ?>"/>
  <div class="view-toggle">
    <button type="button" class="active" data-view="grid" title="Grid view"><i class="fas fa-th-large"></i></button>
    <button type="button" data-view="list" title="List view"><i class="fas fa-list"></i></button>
  </div>
</form>

<div class="section-block">
  <div class="section-head-bar">
    <h2>
      <?php if ($tab === 'training'): ?>
        Training library
        <span class="label-pill label-training"><i class="fas fa-graduation-cap"></i> Learn</span>
      <?php elseif ($tab === 'events'): ?>
        Events &amp; streams
        <span class="label-pill label-events"><i class="fas fa-tower-broadcast"></i> Watch</span>
      <?php else: ?>
        All videos
      <?php endif; ?>
    </h2>
    <?php if ($search !== ''): ?>
      <p>Showing results for "<strong><?php echo htmlspecialchars($search); ?></strong>"</p>
    <?php endif; ?>
  </div>

  <?php if (mysqli_num_rows($query) === 0): ?>
    <div class="empty">
      <div class="empty-icon"><i class="fas fa-film"></i></div>
      <h3>Nothing here yet</h3>
      <p>We couldn't find any videos matching your filters. Try a different search or switch tabs.</p>
      <a href="index.php" class="btn-cta"><i class="fas fa-rotate-left"></i> Show all videos</a>
    </div>
  <?php else: ?>

    <div class="video-grid" id="video-grid">
    <?php
    $index = 0;
    while ($row = mysqli_fetch_array($query)):
      $name       = $row['name'];
      $title      = htmlspecialchars($row['title']);
      $des        = htmlspecialchars($row['des']);
      $vid_id     = intval($row['id']);
      $quiz_count = intval($row['quiz_count']);
      $cat_name   = $row['cat_name'] ?? '';
      $cat_slug   = $row['cat_slug'] ?? '';
      $is_training = $quiz_count > 0; // has quiz checkpoints
      $type_class = $is_training ? 'training' : 'event';
      $initial    = strtoupper(substr($title, 0, 1));
      $index++;
    ?>
    <a class="vid-card <?php echo $type_class; ?>" href="video_player.php?id=<?php echo $vid_id; ?>">
      <div class="vid-thumb">
        <div class="vid-bm-wrap" onclick="event.preventDefault(); event.stopPropagation();">
          <?php
            $bm_type = 'video';
            $bm_id   = (int)$vid_id;
            include __DIR__ . '/../auth/bookmark_btn.php';
          ?>
        </div>
        <video muted preload="metadata"
               onmouseover="this.play()"
               onmouseout="this.pause();this.currentTime=0;"
               controlsList="nodownload">
          <source src="../admin/upload/<?php echo htmlspecialchars($name); ?>">
        </video>
        <div class="thumb-gradient"></div>
        <div class="play-badge"><div class="play-icon"><i class="fas fa-play"></i></div></div>

        <?php if ($is_training): ?>
          <span class="type-badge training"><i class="fas fa-graduation-cap"></i> Training</span>
          <span class="checkpoint-badge">
            <i class="fas fa-bullseye"></i>
            <?php echo $quiz_count; ?> checkpoint<?php echo $quiz_count > 1 ? 's' : ''; ?>
          </span>
        <?php elseif ($cat_name !== ''): ?>
          <span class="type-badge event"><i class="fas fa-tag"></i> <?php echo htmlspecialchars($cat_name); ?></span>
        <?php else: ?>
          <span class="type-badge event"><i class="fas fa-folder"></i> Uncategorized</span>
        <?php endif; ?>
      </div>

      <div class="vid-info">
        <div class="vid-meta-row">
          <div class="vid-avatar"><?php echo $initial; ?></div>
          <div class="vid-meta-text">
            <div class="vid-title"><?php echo $title; ?></div>
            <div class="vid-channel">
              <?php if ($is_training): ?>
                <i class="fas fa-graduation-cap"></i> MediaNest Academy
              <?php elseif ($cat_name !== ''): ?>
                <i class="fas fa-tag"></i> <?php echo htmlspecialchars($cat_name); ?>
              <?php else: ?>
                <i class="fas fa-circle-play"></i> MediaNest
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="vid-desc"><?php echo $des; ?></div>

        <?php if ($is_training): ?>
          <div class="checkpoint-track">
            <span class="ck-label"><i class="fas fa-bullseye"></i> Checkpoints</span>
            <div class="ck-dots">
              <?php
                $shown = min($quiz_count, 6);
                for ($i = 0; $i < $shown; $i++) echo '<span class="ck-dot filled"></span>';
                for ($i = $shown; $i < 6; $i++) echo '<span class="ck-dot"></span>';
              ?>
            </div>
            <span class="ck-count"><?php echo $quiz_count; ?></span>
          </div>
        <?php else: ?>
          <div class="event-stats">
            <span><i class="fas fa-clock"></i> Recent</span>
            <span><i class="fas fa-circle-play"></i> Watch now</span>
          </div>
        <?php endif; ?>
      </div>
    </a>
    <?php endwhile; ?>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="pagination">
      <?php
        $qs = [];
        if ($search !== '') $qs['q'] = $search;
        if ($tab !== 'all') $qs['tab'] = $tab;
        $base_qs = http_build_query($qs);
        $sep = $base_qs ? '&' : '';
      ?>
      <?php if ($page_number > 1): ?>
        <a href="index.php?<?php echo $base_qs.$sep; ?>page=<?php echo $page_number-1; ?>"><i class="fas fa-arrow-left"></i> Prev</a>
      <?php endif; ?>
      <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <a href="index.php?<?php echo $base_qs.$sep; ?>page=<?php echo $i; ?>" <?php if ($i == $page_number) echo 'class="active"'; ?>><?php echo $i; ?></a>
      <?php endfor; ?>
      <?php if ($page_number < $total_pages): ?>
        <a href="index.php?<?php echo $base_qs.$sep; ?>page=<?php echo $page_number+1; ?>">Next <i class="fas fa-arrow-right"></i></a>
      <?php endif; ?>
    </div>
    <?php endif; ?>

  <?php endif; ?>
</div>

<div id="toast" class="toast" hidden></div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  gsap.registerPlugin(ScrollTrigger);

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
    toast(dark ? 'Dark mode on' : 'Light mode on');
  });

  const progress = document.getElementById('scroll-progress');
  window.addEventListener('scroll', () => {
    const h = document.documentElement;
    progress.style.width = (h.scrollTop / (h.scrollHeight - h.clientHeight)) * 100 + '%';
  });

  document.querySelectorAll('.stat-cell .num').forEach(el => {
    const target = +el.dataset.target;
    const obj = { v: 0 };
    ScrollTrigger.create({
      trigger: el, start: 'top 95%', once: true,
      onEnter: () => gsap.to(obj, {
        v: target, duration: 1.6, ease: 'power2.out',
        onUpdate: () => {
          const v = Math.round(obj.v);
          el.textContent = v >= 1000 ? v.toLocaleString() : v;
        }
      })
    });
  });

  gsap.from('.vid-card', {
    opacity: 0, y: 30, stagger: 0.05, duration: 0.6, ease: 'power3.out',
    scrollTrigger: { trigger: '.video-grid', start: 'top 85%' }
  });

  const tabInput = document.getElementById('tab-input');
  document.querySelectorAll('.tab[data-tab]').forEach(t => {
    t.addEventListener('click', () => {
      tabInput.value = t.dataset.tab;
      document.getElementById('filter-form').submit();
    });
  });

  const grid = document.getElementById('video-grid');
  document.querySelectorAll('.view-toggle button').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.view-toggle button').forEach(b => b.classList.toggle('active', b === btn));
      if (!grid) return;
      grid.classList.toggle('list-view', btn.dataset.view === 'list');
      localStorage.setItem('mn-vid-view', btn.dataset.view);
    });
  });
  const savedView = localStorage.getItem('mn-vid-view');
  if (savedView === 'list' && grid) {
    grid.classList.add('list-view');
    document.querySelectorAll('.view-toggle button').forEach(b => b.classList.toggle('active', b.dataset.view === 'list'));
  }

  const toastEl = document.getElementById('toast');
  let toastTimer;
  function toast(msg) {
    toastEl.textContent = msg;
    toastEl.hidden = false;
    requestAnimationFrame(() => toastEl.classList.add('show'));
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
      toastEl.classList.remove('show');
      setTimeout(() => toastEl.hidden = true, 250);
    }, 2000);
  }

  document.addEventListener('keydown', e => {
    if (e.key === '/' && document.activeElement.tagName !== 'INPUT') {
      e.preventDefault();
      document.querySelector('.search-form input').focus();
    }
  });
});
</script>

<script>
// ─── Smart Video Search ─────────────────────────────────────────
(function() {
  const card    = document.querySelector('.ai-search-card');
  const input   = document.getElementById('aiQuery');
  const btn     = document.getElementById('aiAskBtn');
  const results = document.getElementById('aiResults');
  const examples = document.getElementById('aiExamples');
  const collapseBtn = document.getElementById('aiCollapse');

  if (!input) return;

  // Persist collapsed state
  if (localStorage.getItem('ai-search-collapsed') === '1') card.classList.add('collapsed');
  collapseBtn.addEventListener('click', () => {
    card.classList.toggle('collapsed');
    localStorage.setItem('ai-search-collapsed', card.classList.contains('collapsed') ? '1' : '0');
  });

  async function runSearch(q) {
    q = (q || '').trim();
    if (q.length < 2) { results.innerHTML = ''; return; }
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner spin"></i> Searching…';
    results.innerHTML = '<div class="ai-empty"><i class="fas fa-magnifying-glass"></i>Searching transcripts…</div>';
    try {
      const r  = await fetch('ai_search.php?q=' + encodeURIComponent(q) + '&limit=8');
      const js = await r.json();
      if (!js.ok) throw new Error(js.error || 'Search failed');
      renderResults(js.matches);
    } catch (e) {
      results.innerHTML = '<div class="ai-empty" style="color:#ef4444;"><i class="fas fa-circle-exclamation"></i>' + e.message + '</div>';
    } finally {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-arrow-right"></i> Ask';
    }
  }

  function renderResults(matches) {
    if (!matches.length) {
      results.innerHTML = '<div class="ai-empty"><i class="fas fa-circle-info"></i>No matches in any transcribed video. Admin may need to transcribe videos first.</div>';
      return;
    }
    results.innerHTML = matches.map(m => {
      const cat = m.category ? '<span class="ai-vid-cat">' + escapeHtml(m.category) + '</span>' : '';
      const moments = m.moments.map(mo =>
        '<a class="ai-moment" href="video_player.php?id=' + m.video_id + '&t=' + mo.t + '">' +
          '<span class="tt"><i class="fas fa-play"></i> ' + mo.t_label + '</span>' +
          '<span class="snip">' + mo.snippet + '</span>' +
        '</a>'
      ).join('');
      return '<div class="ai-vid-result">' +
        '<div class="ai-vid-head">' +
          '<div class="vt"><a href="video_player.php?id=' + m.video_id + '">' + escapeHtml(m.title) + '</a></div>' +
          cat +
          '<span class="ai-vid-score">relevance ' + m.score + '</span>' +
        '</div>' +
        '<div class="ai-moments">' + moments + '</div>' +
      '</div>';
    }).join('');
  }

  function escapeHtml(s) {
    return (s || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]);
  }

  // Submit
  btn.addEventListener('click', () => runSearch(input.value));
  input.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); runSearch(input.value); } });

  // Example chips
  examples.querySelectorAll('button[data-q]').forEach(b => {
    b.addEventListener('click', () => { input.value = b.getAttribute('data-q'); runSearch(input.value); });
  });

  // Debounced live search after typing pause
  let debounce;
  input.addEventListener('input', () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
      if (input.value.trim().length >= 3) runSearch(input.value);
    }, 600);
  });
})();
</script>

</body>
</html>