<?php
/**
 * MediaNest — Bookmark API
 * --------------------------------------------------------------
 * POST ?action=toggle   body: type, id → {ok, bookmarked, count}
 * GET  ?action=is       params: type, id → {ok, bookmarked}
 * GET  ?action=count    → {ok, count}
 *
 * type ∈ {video, album, file}
 */
require_once __DIR__ . '/auth.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
    exit;
}
$conn = mysqli_connect('localhost', 'root', '', 's&p');
$u    = currentUser();
$uid  = (int)$u['id'];
$VALID_TYPES = ['video','album','file'];

function _bm_user_count($conn, $uid) {
    $s = mysqli_prepare($conn, "SELECT COUNT(*) FROM bookmarks WHERE user_id=?");
    mysqli_stmt_bind_param($s, 'i', $uid);
    mysqli_stmt_execute($s);
    $r = mysqli_fetch_row(mysqli_stmt_get_result($s));
    mysqli_stmt_close($s);
    return (int)($r[0] ?? 0);
}

$action = $_GET['action'] ?? 'is';

if ($action === 'count') {
    echo json_encode(['ok' => true, 'count' => _bm_user_count($conn, $uid)]);
    exit;
}

$type = $_REQUEST['type'] ?? '';
$id   = intval($_REQUEST['id'] ?? 0);
if (!in_array($type, $VALID_TYPES, true) || $id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Bad type or id']);
    exit;
}

if ($action === 'is') {
    $s = mysqli_prepare($conn, "SELECT id FROM bookmarks WHERE user_id=? AND item_type=? AND item_id=?");
    mysqli_stmt_bind_param($s, 'isi', $uid, $type, $id);
    mysqli_stmt_execute($s);
    $exists = mysqli_fetch_assoc(mysqli_stmt_get_result($s));
    mysqli_stmt_close($s);
    echo json_encode(['ok' => true, 'bookmarked' => (bool)$exists]);
    exit;
}

if ($action === 'toggle' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check current state
    $s = mysqli_prepare($conn, "SELECT id FROM bookmarks WHERE user_id=? AND item_type=? AND item_id=?");
    mysqli_stmt_bind_param($s, 'isi', $uid, $type, $id);
    mysqli_stmt_execute($s);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($s));
    mysqli_stmt_close($s);

    if ($row) {
        // Remove
        $s = mysqli_prepare($conn, "DELETE FROM bookmarks WHERE id=?");
        mysqli_stmt_bind_param($s, 'i', $row['id']);
        mysqli_stmt_execute($s);
        mysqli_stmt_close($s);
        echo json_encode(['ok' => true, 'bookmarked' => false, 'count' => _bm_user_count($conn, $uid)]);
    } else {
        // Add
        $s = mysqli_prepare($conn, "INSERT INTO bookmarks (user_id, item_type, item_id) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($s, 'isi', $uid, $type, $id);
        mysqli_stmt_execute($s);
        mysqli_stmt_close($s);
        echo json_encode(['ok' => true, 'bookmarked' => true, 'count' => _bm_user_count($conn, $uid)]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Bad action']);