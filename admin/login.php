<?php
/**
 * MediaNest Admin Login (fixed)
 * --------------------------------------------------------------
 * - Always shows the form when you visit this URL.
 * - Authenticates directly against the users table (role='admin').
 * - Never touches a regular user's session.
 */
require_once __DIR__ . '/admin_auth.php';

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfCheck($_POST['csrf'] ?? '')) {
        $error = 'Session expired. Please try again.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email === '' || $password === '') {
            $error = 'Please enter both email and password.';
        } else {
            global $conn;
            $stmt = mysqli_prepare($conn,
                "SELECT id, password_hash, full_name, role
                 FROM users WHERE email = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt, 's', $email);
            mysqli_stmt_execute($stmt);
            $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

            if (!$user || !password_verify($password, $user['password_hash'])) {
                $error = 'Invalid email or password.';
            } elseif ($user['role'] !== 'admin') {
                $error = 'This account is not an administrator.';
            } else {
                // Start a fresh admin session — replace any existing user session.
                $_SESSION = [];
                session_regenerate_id(true);
                $_SESSION['user_id']   = (int)$user['id'];
                $_SESSION['user_name'] = $user['full_name'];
                header('Location: home.php');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Sign In — MediaNest</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/js/all.min.js" defer></script>
<style>
:root {
  --bg:#0f0a24; --bg-elev:#1a1330; --text:#f1f5f9; --text-soft:#cbd5e1; --muted:#94a3b8;
  --border:rgba(255,255,255,.08); --brand-1:#6366f1; --brand-2:#8b5cf6;
  --grad-brand:linear-gradient(135deg,#6366f1,#8b5cf6);
  --grad-text:linear-gradient(135deg,#a78bfa,#c4b5fd 50%,#f9a8d4);
}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',sans-serif;color:var(--text);background:var(--bg);min-height:100vh;display:grid;place-items:center;padding:20px;overflow:hidden}
h1{font-family:'Sora',sans-serif;letter-spacing:-.02em}
.bg-orbs{position:fixed;inset:0;z-index:0;pointer-events:none;overflow:hidden}
.bg-orb{position:absolute;border-radius:50%;filter:blur(80px);opacity:.45;animation:float 16s ease-in-out infinite}
.bg-orb-1{width:480px;height:480px;background:var(--brand-1);top:-160px;left:-160px}
.bg-orb-2{width:520px;height:520px;background:var(--brand-2);bottom:-180px;right:-160px;animation-delay:-8s}
@keyframes float{0%,100%{transform:translate(0,0)}50%{transform:translate(40px,-30px)}}
.auth-card{position:relative;z-index:1;width:100%;max-width:440px;background:var(--bg-elev);border:1px solid var(--border);border-radius:22px;padding:40px 36px 32px;box-shadow:0 20px 60px rgba(0,0,0,.6)}
.brand-badge{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:999px;background:rgba(99,102,241,.15);border:1px solid rgba(99,102,241,.3);color:#a78bfa;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;margin-bottom:18px}
.logo-mark{width:64px;height:64px;border-radius:18px;background:var(--grad-brand);color:#fff;display:grid;place-items:center;font-size:26px;box-shadow:0 12px 36px rgba(99,102,241,.5);margin-bottom:22px}
h1{font-size:28px;font-weight:800;margin-bottom:6px}
h1 .gt{background:var(--grad-text);-webkit-background-clip:text;background-clip:text;color:transparent}
.subtitle{color:var(--text-soft);font-size:14px;margin-bottom:28px}
.alert{display:flex;align-items:flex-start;gap:10px;padding:12px 14px;border-radius:12px;background:rgba(248,113,113,.1);border:1px solid rgba(248,113,113,.25);color:#fca5a5;font-size:13px;margin-bottom:18px}
.field{margin-bottom:16px}
.field label{display:block;font-size:12px;font-weight:600;color:var(--text-soft);margin-bottom:7px;text-transform:uppercase;letter-spacing:.05em}
.input-wrap{display:flex;align-items:center;background:rgba(15,10,36,.6);border:1px solid var(--border);border-radius:12px;padding:0 14px}
.input-wrap:focus-within{border-color:var(--brand-1);box-shadow:0 0 0 3px rgba(99,102,241,.15)}
.input-wrap i{color:var(--muted);margin-right:10px}
.input-wrap input{flex:1;padding:13px 0;border:0;background:transparent;color:var(--text);font:inherit;font-size:15px;outline:none}
.btn-submit{width:100%;padding:13px 16px;border-radius:12px;border:0;background:var(--grad-brand);color:#fff;font:inherit;font-size:15px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:8px;box-shadow:0 8px 24px rgba(99,102,241,.4);margin-top:6px}
.btn-submit:hover{transform:translateY(-1px)}
.back-link{position:absolute;top:18px;left:18px;color:var(--text-soft);text-decoration:none;font-size:13px;padding:8px 12px;border-radius:8px;background:rgba(255,255,255,.04);border:1px solid var(--border)}
.back-link:hover{background:rgba(255,255,255,.08);color:var(--text)}
</style>
</head>
<body>
<div class="bg-orbs"><div class="bg-orb bg-orb-1"></div><div class="bg-orb bg-orb-2"></div></div>
<a href="../index.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to MediaNest</a>

<div class="auth-card">
  <div class="logo-mark"><i class="fas fa-user-shield"></i></div>
  <span class="brand-badge"><i class="fas fa-lock"></i> Admin Area</span>
  <h1>Sign in to the <span class="gt">Admin Panel</span></h1>
  <p class="subtitle">Use your administrator credentials.</p>

  <?php if ($error): ?>
    <div class="alert"><i class="fas fa-circle-exclamation"></i><div><?php echo htmlspecialchars($error); ?></div></div>
  <?php endif; ?>

  <form method="post" autocomplete="on">
    <input type="hidden" name="csrf" value="<?php echo csrfToken(); ?>">

    <div class="field">
      <label>Admin email</label>
      <div class="input-wrap">
        <i class="fas fa-envelope"></i>
        <input type="email" name="email" placeholder="admin@medianest.local"
               value="<?php echo htmlspecialchars($email); ?>" required autofocus>
      </div>
    </div>

    <div class="field">
      <label>Password</label>
      <div class="input-wrap">
        <i class="fas fa-key"></i>
        <input type="password" name="password" placeholder="••••••••" required>
      </div>
    </div>

    <button type="submit" class="btn-submit">
      <i class="fas fa-arrow-right-to-bracket"></i> Sign in as Admin
    </button>
  </form>
</div>
</body>
</html>