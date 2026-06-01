<?php
/**
 * MediaNest Auth Helper
 * --------------------------------------------------------------
 * Include this at the top of any page that needs to know the
 * current user. It is safe to include even on public pages.
 *
 * Public API:
 *   currentUser()        → array|null    user row if logged in
 *   isLoggedIn()         → bool
 *   requireLogin($redir) → redirects to login if not logged in
 *   loginOrRegister($e, $p, $name) → ['ok'=>bool, 'error'=>?string]
 *   logoutUser()         → destroys session
 */

if (session_status() === PHP_SESSION_NONE) session_start();

// Resolve config path — works from any subfolder depth
$__config_paths = [
    __DIR__ . '/../admin/config.php',
    __DIR__ . '/../Photo/admin/config.php',
    __DIR__ . '/../../admin/config.php',
    __DIR__ . '/../../Photo/admin/config.php',
];
foreach ($__config_paths as $__p) {
    if (file_exists($__p)) { include_once $__p; break; }
}

// ---------- Public API ----------

function currentUser() {
    global $conn;
    if (empty($_SESSION['user_id'])) return null;
    static $cache = null;
    if ($cache !== null) return $cache;
    $uid = intval($_SESSION['user_id']);
    $stmt = mysqli_prepare($conn, "SELECT id, email, full_name, group_name, role FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $uid);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $cache = mysqli_fetch_assoc($res) ?: null;
    return $cache;
}

function isLoggedIn() {
    return currentUser() !== null;
}

function requireLogin($redirect_to = null) {
    if (isLoggedIn()) return;
    $return = $redirect_to ?: ($_SERVER['REQUEST_URI'] ?? '/');
    $login_url = _authLoginUrl() . '?return=' . urlencode($return);
    header('Location: ' . $login_url);
    exit;
}

/**
 * Auto-create-on-first-login.
 * If the email doesn't exist → create it with this password.
 * If it exists → verify the password.
 */
function loginOrRegister($email, $password, $full_name = null) {
    global $conn;

    $email = trim(strtolower($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        return ['ok' => false, 'error' => 'Please enter a valid email address.'];
    if (strlen($password) < 6)
        return ['ok' => false, 'error' => 'Password must be at least 6 characters.'];

    // Rate-limit: max 5 failed attempts from this IP in last 5 minutes
    if (_authIsRateLimited()) {
        _authLogAttempt($email, false);
        return ['ok' => false, 'error' => 'Too many attempts. Please wait a minute and try again.'];
    }

    // Look up user
    $stmt = mysqli_prepare($conn, "SELECT id, email, password_hash, full_name FROM users WHERE email = ?");
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if ($user) {
        // Existing user — verify password
        if (!password_verify($password, $user['password_hash'])) {
            _authLogAttempt($email, false);
            return ['ok' => false, 'error' => 'Incorrect password for this email.'];
        }
        _authStartSession($user['id'], $user['full_name']);
        _authLogAttempt($email, true);
        return ['ok' => true, 'new_user' => false];
    }

    // No matching user. Auto-registration is DISABLED — accounts must be
    // created by an administrator (via phpMyAdmin or the admin panel).
    _authLogAttempt($email, false);
    return ['ok' => false, 'error' => 'No account found for that email. Please contact your administrator.'];
}

function logoutUser() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
    }
    session_destroy();
}

/**
 * Get the list of quiz IDs this user has already passed for a given video.
 * Used by the player to auto-skip completed checkpoints.
 */
function getPassedQuizzes($video_id) {
    global $conn;
    $u = currentUser();
    if (!$u) return [];
    $stmt = mysqli_prepare($conn,
        "SELECT DISTINCT quiz_id FROM quiz_responses
         WHERE user_id = ? AND video_id = ? AND is_correct = 1"
    );
    mysqli_stmt_bind_param($stmt, 'ii', $u['id'], $video_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $out = [];
    while ($r = mysqli_fetch_assoc($res)) $out[] = intval($r['quiz_id']);
    return $out;
}

// ---------- Internal helpers (underscore-prefixed) ----------

function _authLoginUrl() {
    // Build a path-safe URL to /auth/login.php regardless of current depth
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    // Find the project root by looking for "/MediaNest/" or fallback to relative
    if (preg_match('#^(.*?)/(Videos|Photo|Documents|admin|auth)(/|$)#', $script, $m)) {
        return $m[1] . '/auth/login.php';
    }
    return '/auth/login.php';
}

function _authStartSession($user_id, $name) {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user_id;
    $_SESSION['user_name'] = $name;
    global $conn;
    $stmt = mysqli_prepare($conn, "UPDATE users SET last_login = NOW() WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
}

function _authIsRateLimited() {
    global $conn;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!$ip) return false;
    $stmt = mysqli_prepare($conn,
        "SELECT COUNT(*) c FROM login_attempts
         WHERE ip = ? AND success = 0 AND attempted_at > (NOW() - INTERVAL 5 MINUTE)"
    );
    mysqli_stmt_bind_param($stmt, 's', $ip);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    return intval($row['c']) >= 5;
}

function _authLogAttempt($email, $success) {
    global $conn;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ok = $success ? 1 : 0;
    $stmt = mysqli_prepare($conn,
        "INSERT INTO login_attempts (ip, email, success) VALUES (?, ?, ?)"
    );
    mysqli_stmt_bind_param($stmt, 'ssi', $ip, $email, $ok);
    mysqli_stmt_execute($stmt);
}

// ---------- CSRF helpers ----------

function csrfToken() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrfCheck($token) {
    return !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token ?? '');
}