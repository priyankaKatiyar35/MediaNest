<?php
/**
 * MediaNest — Notifications JSON API
 * --------------------------------------------------------------
 * GET ?action=list   → {ok, unread, items[]}
 * GET ?action=count  → {ok, unread}
 * POST ?action=read&id=N   → mark single notification read
 * POST ?action=read_all    → mark all read
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../admin/notify.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
    exit;
}

$action = $_GET['action'] ?? 'list';

if ($action === 'count') {
    echo json_encode(['ok' => true, 'unread' => countMyUnread()]);
    exit;
}

if ($action === 'list') {
    echo json_encode([
        'ok'     => true,
        'unread' => countMyUnread(),
        'items'  => array_map(function($n) {
            $created = strtotime($n['created_at']);
            $now = time();
            $diff = $now - $created;
            if ($diff < 60)      $ago = 'just now';
            elseif ($diff < 3600) $ago = floor($diff / 60) . 'm ago';
            elseif ($diff < 86400) $ago = floor($diff / 3600) . 'h ago';
            elseif ($diff < 604800) $ago = floor($diff / 86400) . 'd ago';
            else $ago = date('M j', $created);
            return [
                'id'      => (int)$n['id'],
                'type'    => $n['type'],
                'title'   => $n['title'],
                'body'    => $n['body'],
                'link'    => $n['link'],
                'is_read' => (int)$n['is_read'],
                'ago'     => $ago,
                'at'      => $n['created_at'],
            ];
        }, getMyNotifications(30)),
    ]);
    exit;
}

if ($action === 'read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    echo json_encode(['ok' => markRead($id)]);
    exit;
}

if ($action === 'read_all' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    echo json_encode(['ok' => true, 'updated' => markAllRead()]);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Bad action']);