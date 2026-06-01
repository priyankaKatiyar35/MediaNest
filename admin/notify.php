<?php
/**
 * MediaNest — Notification helpers
 * --------------------------------------------------------------
 * Public API:
 *   notifyAllUsers($type, $title, $body, $link)        → notifies everyone except sender
 *   notifyGroup($group_name, $type, $title, $body, $link)
 *   notifyUser($user_id, $type, $title, $body, $link)
 *   getMyNotifications($limit=20)                      → list for current user
 *   countMyUnread()                                    → integer
 *   markRead($notif_id)                                → bool
 *   markAllRead()                                      → int (rows updated)
 *
 * Connection: relies on global $conn established by config.php.
 * Auth: read/write helpers assume the caller has already auth-checked.
 */

if (!function_exists('_notif_conn')) {
    function _notif_conn() {
        global $conn;
        if ($conn) return $conn;
        // Lazy fallback (e.g. used from API endpoint)
        $c = mysqli_connect('localhost', 'root', '', 's&p');
        if ($c) $conn = $c;
        return $c;
    }
}

function notifyUser($user_id, $type, $title, $body = '', $link = null) {
    $conn = _notif_conn();
    if (!$conn || $user_id <= 0) return false;
    $stmt = mysqli_prepare($conn,
        "INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'issss', $user_id, $type, $title, $body, $link);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

/**
 * Fan out a notification to every user. Excludes the current admin (sender)
 * so they don't get a ping for their own upload.
 */
function notifyAllUsers($type, $title, $body = '', $link = null, $exclude_user_id = null) {
    $conn = _notif_conn();
    if (!$conn) return 0;
    if ($exclude_user_id === null && function_exists('currentUser')) {
        $u = currentUser();
        if ($u) $exclude_user_id = (int)$u['id'];
    }
    $where = $exclude_user_id ? "WHERE id != ?" : '';
    $sql = "INSERT INTO notifications (user_id, type, title, body, link)
            SELECT id, ?, ?, ?, ? FROM users $where";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) return 0;
    if ($exclude_user_id) {
        mysqli_stmt_bind_param($stmt, 'ssssi', $type, $title, $body, $link, $exclude_user_id);
    } else {
        mysqli_stmt_bind_param($stmt, 'ssss', $type, $title, $body, $link);
    }
    mysqli_stmt_execute($stmt);
    $rows = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    return $rows;
}

function notifyGroup($group_name, $type, $title, $body = '', $link = null, $exclude_user_id = null) {
    $conn = _notif_conn();
    if (!$conn) return 0;
    $where = "WHERE group_name = ?";
    $types = 's'; $params = [$group_name];
    if ($exclude_user_id) { $where .= " AND id != ?"; $types .= 'i'; $params[] = $exclude_user_id; }
    $sql = "INSERT INTO notifications (user_id, type, title, body, link)
            SELECT id, ?, ?, ?, ? FROM users $where";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) return 0;
    $all_types = 'ssss' . $types;
    $all_params = array_merge([$type, $title, $body, $link], $params);
    mysqli_stmt_bind_param($stmt, $all_types, ...$all_params);
    mysqli_stmt_execute($stmt);
    $rows = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    return $rows;
}

function getMyNotifications($limit = 20) {
    $conn = _notif_conn();
    if (!$conn || !function_exists('currentUser')) return [];
    $u = currentUser();
    if (!$u) return [];
    $uid = (int)$u['id'];
    $limit = max(1, min(50, (int)$limit));
    $stmt = mysqli_prepare($conn,
        "SELECT id, type, title, body, link, is_read, created_at
         FROM notifications
         WHERE user_id = ?
         ORDER BY created_at DESC
         LIMIT ?");
    mysqli_stmt_bind_param($stmt, 'ii', $uid, $limit);
    mysqli_stmt_execute($stmt);
    $r = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($r)) $rows[] = $row;
    mysqli_stmt_close($stmt);
    return $rows;
}

function countMyUnread() {
    $conn = _notif_conn();
    if (!$conn || !function_exists('currentUser')) return 0;
    $u = currentUser();
    if (!$u) return 0;
    $uid = (int)$u['id'];
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
    mysqli_stmt_bind_param($stmt, 'i', $uid);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_row(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return (int)($row[0] ?? 0);
}

function markRead($notif_id) {
    $conn = _notif_conn();
    if (!$conn || !function_exists('currentUser')) return false;
    $u = currentUser();
    if (!$u) return false;
    $uid = (int)$u['id'];
    $nid = (int)$notif_id;
    $stmt = mysqli_prepare($conn, "UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?");
    mysqli_stmt_bind_param($stmt, 'ii', $nid, $uid);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

function markAllRead() {
    $conn = _notif_conn();
    if (!$conn || !function_exists('currentUser')) return 0;
    $u = currentUser();
    if (!$u) return 0;
    $uid = (int)$u['id'];
    $stmt = mysqli_prepare($conn, "UPDATE notifications SET is_read=1 WHERE user_id=? AND is_read=0");
    mysqli_stmt_bind_param($stmt, 'i', $uid);
    mysqli_stmt_execute($stmt);
    $rows = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    return $rows;
}