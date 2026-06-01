<?php
/**
 * MediaNest Admin — shared header
 * --------------------------------------------------------------
 * Provides the top navbar + sidebar for every admin page.
 * The page body opens here; page content goes between this file
 * and the page's own closing markup.
 *
 * To highlight the active link in a child page, add a body class
 * like <body class="page-dashboard">, page-gallery, page-videos,
 * page-upload, page-quiz, page-documents, page-quiz-responses.
 */
require_once __DIR__ . '/admin_auth.php';
requireAdmin();
$admin = currentAdmin();

function _admin_initials($name) {
    $parts = preg_split('/\s+/', trim($name ?? 'Admin'));
    $s = '';
    foreach ($parts as $p) { if ($p !== '') $s .= mb_substr($p, 0, 1); }
    return strtoupper(mb_substr($s ?: 'A', 0, 2));
}
$ad_name  = $admin['full_name'] ?? 'Administrator';
$ad_email = $admin['email']     ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>MediaNest Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/js/all.min.js" defer></script>

<style>
:root {
  --bg: #f6f7fb; --bg-elev: #ffffff;
  --sidebar-bg: #0f0a24; --sidebar-bg-2: #1a1330;
  --text: #0f172a; --text-soft: #475569; --muted: #94a3b8;
  --border: rgba(15, 23, 42, 0.08);
  --border-dark: rgba(255, 255, 255, 0.08);
  --shadow-sm: 0 2px 8px rgba(15, 23, 42, 0.06);
  --shadow-md: 0 10px 30px rgba(15, 23, 42, 0.08);
  --brand-1: #6366f1; --brand-2: #8b5cf6;
  --grad-brand: linear-gradient(135deg, #6366f1, #8b5cf6);
  --grad-text: linear-gradient(135deg, #a78bfa, #c4b5fd 50%, #f9a8d4);
  --radius: 12px;
  --green: #10b981; --red: #ef4444; --gold: #f59e0b; --blue: #0ea5e9; --pink: #ec4899;
}
html.dark {
  --bg: #0a0e1a; --bg-elev: #131826;
  --text: #e2e8f0; --text-soft: #cbd5e1; --muted: #64748b;
  --border: rgba(255, 255, 255, 0.08);
  --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.3);
  --shadow-md: 0 10px 30px rgba(0, 0, 0, 0.4);
}
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Inter', sans-serif; color: var(--text); background: var(--bg); min-height: 100vh; transition: background .3s, color .3s; }
h1, h2, h3, h4 { font-family: 'Sora', sans-serif; letter-spacing: -0.02em; }
a { color: inherit; text-decoration: none; }
button { font-family: inherit; cursor: pointer; }

/* Layout grid */
.admin-shell { display: grid; grid-template-columns: 250px 1fr; min-height: 100vh; }
.admin-shell.collapsed { grid-template-columns: 72px 1fr; }
@media (max-width: 900px) {
  .admin-shell { grid-template-columns: 1fr; }
  .admin-shell .sidebar { transform: translateX(-100%); position: fixed; top: 0; bottom: 0; z-index: 1000; }
  .admin-shell.mobile-open .sidebar { transform: translateX(0); }
}

/* Sidebar */
.sidebar { background: linear-gradient(180deg, var(--sidebar-bg), var(--sidebar-bg-2)); color: #cbd5e1; display: flex; flex-direction: column; border-right: 1px solid var(--border-dark); transition: width .25s; overflow: hidden; }
.sidebar-head { padding: 22px 22px 18px; display: flex; align-items: center; gap: 11px; border-bottom: 1px solid var(--border-dark); }
.sidebar-head .logo-mark { width: 38px; height: 38px; border-radius: 10px; background: var(--grad-brand); color: white; display: grid; place-items: center; font-size: 16px; box-shadow: 0 8px 24px rgba(99, 102, 241, .35); flex-shrink: 0; }
.sidebar-head .brand { font-family: 'Sora', sans-serif; font-weight: 700; font-size: 17px; color: white; line-height: 1; }
.sidebar-head .brand-sub { font-size: 10px; text-transform: uppercase; letter-spacing: .15em; color: var(--brand-1); font-weight: 700; margin-top: 4px; }
.collapsed .sidebar-head .brand-block { display: none; }

.sidebar-section { font-size: 10px; text-transform: uppercase; letter-spacing: .12em; color: rgba(255, 255, 255, .35); font-weight: 700; padding: 16px 22px 6px; }
.collapsed .sidebar-section { color: transparent; height: 16px; padding: 8px 0; border-top: 1px solid var(--border-dark); margin: 8px 12px 0; }
.collapsed .sidebar-section span { display: none; }

.nav-link { display: flex; align-items: center; gap: 12px; padding: 11px 22px; color: rgba(255, 255, 255, .6); font-size: 14px; font-weight: 500; border-left: 3px solid transparent; transition: all .15s; position: relative; }
.nav-link i { width: 18px; text-align: center; font-size: 14px; flex-shrink: 0; }
.nav-link:hover { background: rgba(255, 255, 255, .04); color: white; }
.nav-link.active { background: rgba(99, 102, 241, .15); color: white; border-left-color: var(--brand-1); }
.nav-link.active::after { content: ''; position: absolute; right: 0; top: 50%; transform: translateY(-50%); width: 4px; height: 24px; background: var(--grad-brand); border-radius: 4px 0 0 4px; }
.collapsed .nav-link span { display: none; }
.collapsed .nav-link { justify-content: center; padding: 13px 0; }
.collapsed .nav-link i { font-size: 16px; }

/* Submenu */
.has-sub { cursor: pointer; }
.has-sub .chev { margin-left: auto; font-size: 10px; opacity: .5; transition: transform .2s; }
.has-sub.open .chev { transform: rotate(180deg); opacity: 1; }
.subnav { max-height: 0; overflow: hidden; transition: max-height .25s; background: rgba(0, 0, 0, .25); }
.has-sub.open + .subnav { max-height: 300px; }
.subnav a { display: flex; align-items: center; gap: 10px; padding: 9px 22px 9px 54px; font-size: 13px; color: rgba(255, 255, 255, .55); transition: all .15s; }
.subnav a::before { content: ''; width: 5px; height: 5px; border-radius: 50%; background: currentColor; opacity: .5; }
.subnav a:hover, .subnav a.active { color: white; background: rgba(255, 255, 255, .04); }
.collapsed .has-sub .chev { display: none; }
.collapsed .subnav { display: none; }

.sidebar-foot { margin-top: auto; padding: 18px 22px; border-top: 1px solid var(--border-dark); }
.sidebar-foot .me { display: flex; align-items: center; gap: 11px; padding: 8px 0; }
.sidebar-foot .av { width: 36px; height: 36px; border-radius: 50%; background: var(--grad-brand); color: white; display: grid; place-items: center; font-weight: 700; font-size: 13px; flex-shrink: 0; }
.sidebar-foot .me-info { min-width: 0; }
.sidebar-foot .me-name { color: white; font-weight: 600; font-size: 13px; line-height: 1.1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sidebar-foot .me-mail { color: rgba(255, 255, 255, .45); font-size: 11px; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.collapsed .sidebar-foot .me-info { display: none; }
.collapsed .sidebar-foot { padding: 14px 0; text-align: center; }

/* Main area */
.main { display: flex; flex-direction: column; min-height: 100vh; min-width: 0; }
.topbar { position: sticky; top: 0; z-index: 50; background: color-mix(in srgb, var(--bg) 80%, transparent); backdrop-filter: blur(12px); border-bottom: 1px solid var(--border); padding: 14px 28px; display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.topbar-left { display: flex; align-items: center; gap: 16px; }
.icon-btn { width: 38px; height: 38px; border-radius: 10px; background: var(--bg-elev); border: 1px solid var(--border); color: var(--text); display: grid; place-items: center; transition: all .15s; }
.icon-btn:hover { background: var(--bg); border-color: var(--brand-1); color: var(--brand-1); }
.topbar h1.page-title { font-size: 18px; font-weight: 700; }
.topbar-right { display: flex; align-items: center; gap: 10px; }
.go-site { padding: 8px 14px; border-radius: 10px; background: var(--bg-elev); border: 1px solid var(--border); color: var(--text-soft); font-size: 13px; font-weight: 500; display: inline-flex; align-items: center; gap: 7px; transition: all .15s; }
.go-site:hover { background: var(--grad-brand); color: white; border-color: transparent; }

/* Page wrap */
.page-wrap { flex: 1; padding: 28px; min-width: 0; }

/* Reusable card */
.card { background: var(--bg-elev); border: 1px solid var(--border); border-radius: 14px; padding: 22px; box-shadow: var(--shadow-sm); }

.btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 10px; border: 0; font: inherit; font-size: 14px; font-weight: 600; cursor: pointer; transition: all .15s; }
.btn-primary { background: var(--grad-brand); color: white; box-shadow: 0 8px 24px rgba(99, 102, 241, .3); }
.btn-primary:hover { transform: translateY(-1px); box-shadow: 0 12px 28px rgba(99, 102, 241, .4); }
.btn-ghost { background: var(--bg-elev); color: var(--text); border: 1px solid var(--border); }
.btn-ghost:hover { border-color: var(--brand-1); color: var(--brand-1); }
.btn-danger { background: var(--red); color: white; }
</style>
</head>
<body class="<?php echo isset($body_class) ? htmlspecialchars($body_class) : ''; ?>">

<div class="admin-shell" id="adminShell">

  <!-- ===== SIDEBAR ===== -->
  <aside class="sidebar">
    <div class="sidebar-head">
      <div class="logo-mark"><i class="fas fa-bolt"></i></div>
      <div class="brand-block">
        <div class="brand">MediaNest</div>
        <div class="brand-sub">Admin</div>
      </div>
    </div>

    <div class="sidebar-section"><span>Main</span></div>
    <a href="home.php" class="nav-link link-dashboard">
      <i class="fas fa-gauge-high"></i><span>Dashboard</span>
    </a>

    <div class="sidebar-section"><span>Media</span></div>
    <a href="addalbum.php" class="nav-link link-gallery">
      <i class="fas fa-images"></i><span>Photo Albums</span>
    </a>
    <a href="videoalb.php" class="nav-link link-videos">
      <i class="fas fa-film"></i><span>Video Library</span>
    </a>

    <div class="sidebar-section"><span>Uploads</span></div>
    <div class="nav-link has-sub" id="uploadToggle" onclick="document.getElementById('uploadToggle').classList.toggle('open')">
      <i class="fas fa-cloud-arrow-up"></i><span>Upload Videos</span>
      <i class="fas fa-chevron-down chev"></i>
    </div>
    <div class="subnav">
      <a href="upload.php" class="link-upload">New video</a>
      <a href="quiz_editor.php" class="link-quiz">Attach quiz</a>
      <a href="quiz_responses.php" class="link-quiz-responses">Quiz responses</a>
    </div>
    <a href="uploadfiles.php" class="nav-link link-documents">
      <i class="fas fa-folder-open"></i><span>Upload Documents</span>
    </a>

    <a href="quiz_analytics.php" class="nav-link link-analytics">
  <i class="fas fa-chart-line"></i><span>Quiz Analytics</span>
</a>

<a href="manage.php" class="nav-link link-manage">
  <i class="fas fa-sliders"></i><span>Manage Content</span>
</a>

    <div class="sidebar-foot">
      <div class="me">
        <div class="av"><?php echo htmlspecialchars(_admin_initials($ad_name)); ?></div>
        <div class="me-info">
          <div class="me-name"><?php echo htmlspecialchars($ad_name); ?></div>
          <div class="me-mail"><?php echo htmlspecialchars($ad_email); ?></div>
        </div>
      </div>
    </div>
  </aside>

  <!-- ===== MAIN ===== -->
  <div class="main">

    <!-- Topbar -->
    <header class="topbar">
      <div class="topbar-left">
        <button class="icon-btn" onclick="document.getElementById('adminShell').classList.toggle('collapsed'); document.getElementById('adminShell').classList.toggle('mobile-open');" aria-label="Toggle sidebar">
          <i class="fas fa-bars"></i>
        </button>
        <h1 class="page-title"><?php echo htmlspecialchars($page_title ?? 'Admin Panel'); ?></h1>
      </div>
      <div class="topbar-right">
        <a href="../index.php" class="go-site" target="_blank">
          <i class="fas fa-arrow-up-right-from-square"></i> View site
        </a>
        <button class="icon-btn" id="adminTheme" aria-label="Toggle theme"><i class="fas fa-moon"></i></button>
        <a href="logout.php" class="icon-btn" title="Sign out"><i class="fas fa-arrow-right-from-bracket"></i></a>
      </div>
    </header>

    <!-- Page content starts here. Each admin page closes the structure. -->
    <main class="page-wrap">

<script>
// Auto-highlight active sidebar link based on body class
(function() {
  const cls = document.body.className;
  const m = cls.match(/page-([\w-]+)/);
  if (!m) return;
  const target = document.querySelector('.link-' + m[1]);
  if (target) {
    target.classList.add('active');
    // If it's a submenu link, open the parent
    const sub = target.closest('.subnav');
    if (sub) {
      const parent = sub.previousElementSibling;
      if (parent && parent.classList.contains('has-sub')) parent.classList.add('open');
    }
  }
})();

// Theme toggle (shared with the public site via the same localStorage key)
const themeBtn = document.getElementById('adminTheme');
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
</script>