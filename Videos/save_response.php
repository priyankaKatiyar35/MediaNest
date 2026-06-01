<?php
require_once __DIR__ . '/../auth/auth.php';
requireLogin();
include 'admin/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

// Inputs
$quiz_id    = intval($_POST['quiz_id']       ?? 0);
$option_id  = intval($_POST['option_id']     ?? 0);
$video_id   = intval($_POST['video_id']      ?? 0);
$chosen     = intval($_POST['chosen_option'] ?? -1);
$is_correct = intval($_POST['is_correct']    ?? 0);
$time_taken = floatval($_POST['time_taken']  ?? 0);
$user_name  = trim($_POST['user_name']  ?? '');
$group_name = trim($_POST['group_name'] ?? '');

// If logged in → override name/group with session data (more reliable)
$user = currentUser();
$user_id = null;
if ($user) {
    $user_id    = intval($user['id']);
    $user_name  = $user['full_name'];
    $group_name = $user['group_name'] ?? $group_name;
}

// Validate
if (!$quiz_id || !$video_id || $chosen < 0 || $user_name === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing or invalid fields']);
    exit;
}

// Prepared statement — much safer than raw concat
$ip      = $_SERVER['REMOTE_ADDR'] ?? '';
$session = session_id();

$sql = "INSERT INTO quiz_responses
          (user_id, quiz_id, video_id, option_id, user_ip, user_session,
           user_name, group_name, chosen_option, is_correct, time_taken_sec, answered_at)
        VALUES
          (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
          chosen_option  = VALUES(chosen_option),
          is_correct     = VALUES(is_correct),
          time_taken_sec = VALUES(time_taken_sec),
          user_id        = VALUES(user_id),
          answered_at    = NOW()";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param(
    $stmt,
    'iiiissssiid',
    $user_id, $quiz_id, $video_id, $option_id,
    $ip, $session, $user_name, $group_name,
    $chosen, $is_correct, $time_taken
);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(['ok' => true, 'logged_in' => $user !== null]);
} else {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => mysqli_stmt_error($stmt)]);
}