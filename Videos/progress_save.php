<?php
/**
 * MediaNest — Save playback progress (per user, per video)
 * --------------------------------------------------------------
 * POST: video_id, position (seconds), duration (seconds)
 * Auth: requireLogin
 * Returns: {ok, progress_pct, completed}
 *
 * Called by the player every ~10s and once on pause/page-unload.
 * Idempotent: upserts into video_progress, marks completed once past 90%.
 */
require_once __DIR__ . '/../auth/auth.php';
requireLogin();
header('Content-Type: application/json');

$conn = mysqli_connect('localhost', 'root', '', 's&p');
if (!$conn) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'DB']); exit; }

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new RuntimeException('POST only');

    $u   = currentUser();
    if (!$u) throw new RuntimeException('Not authenticated');
    $uid = (int)$u['id'];

    $vid = intval($_POST['video_id'] ?? 0);
    $pos = (float)($_POST['position']  ?? 0);
    $dur = (float)($_POST['duration']  ?? 0);

    if ($vid <= 0 || $pos < 0 || $dur <= 0) throw new RuntimeException('Bad input');
    if ($pos > $dur) $pos = $dur;

    $pct = (int) min(100, max(0, round($pos / $dur * 100)));
    $completed = $pct >= 90 ? 1 : 0;

    $stmt = mysqli_prepare($conn,
        "INSERT INTO video_progress (user_id, video_id, last_position, duration_sec, progress_pct, completed)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            last_position = VALUES(last_position),
            duration_sec  = VALUES(duration_sec),
            progress_pct  = VALUES(progress_pct),
            completed     = GREATEST(completed, VALUES(completed)),
            last_watched_at = CURRENT_TIMESTAMP");
    mysqli_stmt_bind_param($stmt, 'iiddii', $uid, $vid, $pos, $dur, $pct, $completed);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    echo json_encode(['ok' => true, 'progress_pct' => $pct, 'completed' => (bool)$completed]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}