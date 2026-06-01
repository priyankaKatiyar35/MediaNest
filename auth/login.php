<?php
require_once __DIR__ . '/auth.php';

$error = '';
$email = '';
$new_user_hint = isset($_GET['new']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfCheck($_POST['csrf'] ?? '')) {
        $error = 'Session expired. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $name = trim($_POST['name'] ?? '');

        $result = loginOrRegister($email, $password, $name ?: null);
        if ($result['ok']) {
            $return = $_GET['return'] ?? '../index.php';
            // Basic open-redirect safety
            if (!preg_match('#^https?://#', $return)) header('Location: ' . $return);
            else header('Location: ../index.php');
            exit;
        }
        $error = $result['error'];
    }
}

// If already logged in, bounce — honour ?return= if it was supplied
if (isLoggedIn()) {
    $return = $_GET['return'] ?? '../index.php';
    if (preg_match('#^https?://#', $return)) $return = '../index.php';
    header('Location: ' . $return);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign in — MediaNest</title>

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
  --shadow-lg: 0 20px 60px rgba(15, 23, 42, 0.12);
  --brand-1: #0ea5e9; --brand-2: #6366f1;
  --radius: 16px; --radius-lg: 22px;
  --grad-brand: linear-gradient(135deg, #06b6d4, #0ea5e9);
  --grad-text: linear-gradient(135deg, #0ea5e9, #6366f1 50%, #a855f7);
  --red: #ef4444;
}
html.dark {
  --bg: #0a0e1a; --bg-elev: #131826;
  --text: #e2e8f0; --text-soft: #cbd5e1; --muted: #64748b;
  --border: rgba(255, 255, 255, 0.08);
  --shadow-lg: 0 20px 60px rgba(0, 0, 0, 0.5);
}

* { margin: 0; padding: 0; box-sizing: border-box; }
body {
  font-family: 'Inter', sans-serif;
  color: var(--text); background: var(--bg);
  min-height: 100vh;
  display: grid; place-items: center;
  padding: 20px;
  overflow: hidden;
}
h1, h2, h3 { font-family: 'Sora', sans-serif; letter-spacing: -0.02em; }
a { color: inherit; }

/* Background orbs */
.bg-orbs { position: fixed; inset: 0; z-index: 0; pointer-events: none; overflow: hidden; }
.bg-orb { position: absolute; border-radius: 50%; filter: blur(80px); opacity: 0.4; animation: float 14s ease-in-out infinite; }
.bg-orb-1 { width: 420px; height: 420px; background: var(--brand-1); top: -120px; left: -120px; }
.bg-orb-2 { width: 480px; height: 480px; background: var(--brand-2); bottom: -160px; right: -140px; animation-delay: -7s; }
html.dark .bg-orb { opacity: 0.3; }
@keyframes float { 0%,100% { transform: translate(0,0);} 50% { transform: translate(40px,-30px);} }

.auth-card {
  position: relative; z-index: 1;
  width: 100%; max-width: 420px;
  background: var(--bg-elev);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 38px 36px 30px;
  box-shadow: var(--shadow-lg);
  animation: popIn .35s cubic-bezier(.22,.61,.36,1) both;
}
@keyframes popIn { from { opacity: 0; transform: translateY(10px) scale(.97); } to { opacity: 1; transform: none; } }

.brand-row { display: flex; align-items: center; gap: 10px; justify-content: center; margin-bottom: 22px; }
.logo-mark {
  width: 38px; height: 38px; border-radius: 11px;
  background: var(--grad-brand); color: white;
  display: grid; place-items: center;
  box-shadow: 0 6px 18px rgba(6, 182, 212, 0.35);
}
.brand-name { font-family: 'Sora', sans-serif; font-weight: 700; font-size: 20px; }
.brand-name span { background: var(--grad-text); -webkit-background-clip: text; background-clip: text; color: transparent; }

.auth-card h1 {
  font-size: 24px; font-weight: 700;
  text-align: center; margin-bottom: 6px;
}
.auth-sub {
  text-align: center; color: var(--text-soft);
  font-size: 14px; margin-bottom: 26px; line-height: 1.55;
}
.auth-sub strong { color: var(--text); }

.field { margin-bottom: 14px; }
.field label {
  display: block; font-size: 11px; font-weight: 700;
  text-transform: uppercase; letter-spacing: .06em;
  color: var(--text-soft); margin-bottom: 6px;
}
.input-wrap {
  display: flex; align-items: center; gap: 10px;
  padding: 11px 14px;
  background: var(--bg); border: 1.5px solid var(--border);
  border-radius: 10px;
  transition: border-color .2s, box-shadow .2s;
}
.input-wrap:focus-within {
  border-color: var(--brand-2);
  box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.12);
}
.input-wrap i { color: var(--muted); font-size: 14px; }
.input-wrap input {
  flex: 1; border: none; background: transparent;
  font-family: inherit; font-size: 14px; color: var(--text);
  outline: none;
}
.input-wrap input::placeholder { color: var(--muted); }
.toggle-pw {
  background: none; border: none; color: var(--muted);
  cursor: pointer; padding: 2px 4px; font-size: 13px;
}
.toggle-pw:hover { color: var(--text); }

.btn-submit {
  width: 100%; padding: 13px;
  background: var(--grad-brand); color: white;
  font-family: inherit; font-size: 14px; font-weight: 600;
  border: none; border-radius: 10px; cursor: pointer;
  display: inline-flex; align-items: center; justify-content: center; gap: 8px;
  box-shadow: 0 6px 18px rgba(6, 182, 212, 0.35);
  transition: transform .2s, box-shadow .2s;
  margin-top: 8px;
}
.btn-submit:hover { transform: translateY(-2px); box-shadow: 0 10px 24px rgba(6, 182, 212, 0.45); }
.btn-submit:active { transform: translateY(0); }

.error {
  background: rgba(239, 68, 68, 0.08);
  border: 1px solid rgba(239, 68, 68, 0.25);
  color: var(--red);
  padding: 10px 12px; border-radius: 10px;
  font-size: 13px; margin-bottom: 16px;
  display: flex; align-items: flex-start; gap: 9px;
}
.error i { margin-top: 2px; }

.hint {
  text-align: center; margin-top: 20px;
  font-size: 12px; color: var(--text-soft);
  line-height: 1.6;
}
.hint i { color: var(--brand-1); }

.skip-link {
  display: block; text-align: center;
  margin-top: 14px; font-size: 13px;
  color: var(--text-soft); text-decoration: none;
}
.skip-link:hover { color: var(--text); }

.theme-toggle {
  position: fixed; top: 18px; right: 18px; z-index: 10;
  width: 40px; height: 40px; border-radius: 10px;
  background: var(--bg-elev); border: 1px solid var(--border);
  color: var(--text); cursor: pointer;
  display: grid; place-items: center;
  box-shadow: var(--shadow-sm);
  transition: transform .2s;
}
.theme-toggle:hover { transform: translateY(-1px); }
</style>
</head>
<body>

<div class="bg-orbs">
  <div class="bg-orb bg-orb-1"></div>
  <div class="bg-orb bg-orb-2"></div>
</div>

<button id="theme-toggle" class="theme-toggle" title="Toggle theme">
  <i class="fas fa-moon"></i>
</button>

<div class="auth-card">
  <div class="brand-row">
    <div class="logo-mark"><i class="fas fa-cube"></i></div>
    <div class="brand-name">Media<span>Nest</span></div>
  </div>

  <h1>Sign in to MediaNest</h1>
  <p class="auth-sub">New here? Just enter your email and a password — <strong>we'll create your account automatically</strong>.</p>

  <?php if ($error): ?>
    <div class="error">
      <i class="fas fa-circle-exclamation"></i>
      <span><?php echo htmlspecialchars($error); ?></span>
    </div>
  <?php endif; ?>

  <form method="post" autocomplete="on" novalidate>
    <input type="hidden" name="csrf" value="<?php echo csrfToken(); ?>">

    <div class="field">
      <label for="email">Email</label>
      <div class="input-wrap">
        <i class="fas fa-envelope"></i>
        <input type="email" id="email" name="email" placeholder="you@company.com"
               value="<?php echo htmlspecialchars($email); ?>" required autocomplete="email" autofocus>
      </div>
    </div>

    <div class="field">
      <label for="password">Password</label>
      <div class="input-wrap">
        <i class="fas fa-lock"></i>
        <input type="password" id="password" name="password" placeholder="At least 6 characters"
               required autocomplete="current-password" minlength="6">
        <button type="button" class="toggle-pw" onclick="togglePw()" id="togglePw">
          <i class="fas fa-eye"></i>
        </button>
      </div>
    </div>

    <button type="submit" class="btn-submit">
      <i class="fas fa-arrow-right-to-bracket"></i> Sign in
    </button>
  </form>

  <p class="hint">
    <i class="fas fa-shield-halved"></i>
    Your password is encrypted. We never store it as plain text.
  </p>

  <a href="<?php echo htmlspecialchars($_GET['return'] ?? '../index.php'); ?>" class="skip-link">
    Skip — continue without signing in
  </a>
</div>

<script>
function togglePw() {
  const i = document.getElementById('password');
  const btn = document.getElementById('togglePw').querySelector('i');
  if (i.type === 'password') { i.type = 'text'; btn.className = 'fas fa-eye-slash'; }
  else { i.type = 'password'; btn.className = 'fas fa-eye'; }
}

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
</script>
</body>
</html>