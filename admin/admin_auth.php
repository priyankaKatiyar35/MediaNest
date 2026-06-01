<?php
/**
 * MediaNest Admin Auth
 * --------------------------------------------------------------
 * Thin role-checking layer ON TOP OF the unified /auth/auth.php.
 * No separate sessions, no separate password store — admins are
 * just users with role='admin' in the users table.
 *
 * Public API:
 *   isAdmin()                  → bool
 *   requireAdmin($redirect_to) → redirects to /auth/login.php if guest,
 *                                or 403s if logged in but not admin
 *   currentAdmin()             → user row if admin, else null
 *   adminLogout()              → ends session
 *
 * Backward-compat shims (so the older admin pages keep working):
 *   adminLogin($u, $p)         → maps to loginOrRegister() but
 *                                refuses to auto-create new admins
 */

require_once __DIR__ . '/../auth/auth.php';

// ---------- Public API ----------

function isAdmin() {
    $u = currentUser();
    return $u && (($u['role'] ?? '') === 'admin');
}

function requireAdmin($redirect_to = null) {
    // Not logged in → bounce to unified login, remember where to return
    if (!isLoggedIn()) {
        requireLogin($redirect_to);
        return; // unreachable
    }
    // Logged in but NOT admin → hard stop
    if (!isAdmin()) {
        http_response_code(403);
        echo '<!doctype html><meta charset="utf-8"><title>403 — Admin only</title>'
           . '<div style="font:16px/1.5 system-ui,sans-serif;max-width:540px;margin:80px auto;padding:24px;'
           . 'border:1px solid #fcd5d5;background:#fff5f5;border-radius:12px;color:#7f1d1d;">'
           . '<h1 style="margin:0 0 8px;font-size:20px;">Admin access required</h1>'
           . '<p>You are signed in, but this page is restricted to administrators.</p>'
           . '<p><a href="../index.php" style="color:#0ea5e9;">← Back to MediaNest</a> &nbsp;·&nbsp; '
           . '<a href="../auth/logout.php" style="color:#0ea5e9;">Sign out</a></p>'
           . '</div>';
        exit;
    }
}

function currentAdmin() {
    return isAdmin() ? currentUser() : null;
}

function adminLogout() {
    logoutUser();
    header('Location: ../index.php');
    exit;
}

// ---------- Legacy shim: adminLogin(username, password) ----------
// Some old admin pages may still call this. We translate it to the
// unified flow but ONLY allow login of *existing* admins — no
// silent admin auto-creation.
function adminLogin($username, $password) {
    global $conn;

    $username = trim($username);
    if ($username === '' || $password === '') {
        return ['ok' => false, 'error' => 'Username and password are required.'];
    }

    // Accept either email OR full_name as "username" for convenience.
    $stmt = mysqli_prepare($conn,
        "SELECT id, email, password_hash, full_name, role
         FROM users
         WHERE (email = ? OR full_name = ?) AND role = 'admin'
         LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, 'ss', $username, $username);
    mysqli_stmt_execute($stmt);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$user || !password_verify($password, $user['password_hash'])) {
        // Use the rate-limit + audit-log path
        _authLogAttempt($username, false);
        return ['ok' => false, 'error' => 'Invalid admin credentials.'];
    }

    _authStartSession($user['id'], $user['full_name']);
    _authLogAttempt($user['email'], true);
    return ['ok' => true];
}

// ---------- Optional helper: bootstrap shared header on admin pages ----------
// Use this at the top of any admin/*.php instead of repeating the
// "session_start + check + db connect" boilerplate.
function adminBootstrap() {
    requireAdmin();
    // $conn is already established by auth/auth.php's config include.
    return currentAdmin();
}

// ---------- Admin audit log ----------
// Records every meaningful admin action (uploads, deletions, quiz edits…)
// in a small table. The table is created lazily the first time we write
// to it, so there is nothing to install separately.
function adminAuditLog($action, $details = '') {
    global $conn;
    if (!$conn) return;

    static $ensured = false;
    if (!$ensured) {
        @mysqli_query($conn, "
            CREATE TABLE IF NOT EXISTS admin_audit (
                id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id   INT UNSIGNED NULL,
                email     VARCHAR(190) NULL,
                action    VARCHAR(80)  NOT NULL,
                details   VARCHAR(500) NULL,
                ip        VARCHAR(45)  NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_action (action),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $ensured = true;
    }

    $u   = currentUser();
    $uid = $u ? intval($u['id']) : null;
    $em  = $u['email'] ?? null;
    $ip  = $_SERVER['REMOTE_ADDR'] ?? null;
    $details = mb_substr((string)$details, 0, 500);

    $stmt = mysqli_prepare($conn,
        "INSERT INTO admin_audit (user_id, email, action, details, ip) VALUES (?, ?, ?, ?, ?)"
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'issss', $uid, $em, $action, $details, $ip);
        @mysqli_stmt_execute($stmt);
    }
}
