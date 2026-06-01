<?php
/**
 * MediaNest — Gated video streaming
 * --------------------------------------------------------------
 * GET ?id=N → streams the video file if user is logged in.
 * Supports HTTP Range requests so the player can seek and the browser
 * can buffer-as-you-watch.
 *
 * Replaces direct <source src="../admin/upload/foo.mp4"> with
 * <source src="../Videos/serve_video.php?id=42">.
 */
require_once __DIR__ . '/../auth/auth.php';
if (!isLoggedIn()) { http_response_code(401); exit('Authentication required'); }
$conn = mysqli_connect('localhost','root','','s&p');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) { http_response_code(400); exit('Bad request'); }

// Look up filename
$stmt = mysqli_prepare($conn, "SELECT name FROM video WHERE id=?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$row) {
    // Also try gallery_video (special collection)
    $stmt = mysqli_prepare($conn, "SELECT name FROM gallery_video WHERE id=?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
}
if (!$row) { http_response_code(404); exit('Not found'); }

$file = __DIR__ . '/../admin/upload/' . basename($row['name']);
if (!is_file($file)) { http_response_code(404); exit('File missing'); }

$size = filesize($file);
$ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$mime = [
    'mp4'=>'video/mp4','m4v'=>'video/mp4','mov'=>'video/quicktime',
    'mkv'=>'video/x-matroska','avi'=>'video/x-msvideo','webm'=>'video/webm',
][$ext] ?? 'application/octet-stream';

// Defaults
$start = 0;
$end   = $size - 1;
$len   = $size;
$status = 200;

// Honour Range header for video scrubbing
if (isset($_SERVER['HTTP_RANGE'])) {
    if (preg_match('/bytes=(\d+)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
        $start = (int)$m[1];
        if ($m[2] !== '') $end = (int)$m[2];
        if ($end >= $size) $end = $size - 1;
        if ($start > $end || $start >= $size) {
            http_response_code(416);
            header("Content-Range: bytes */$size");
            exit;
        }
        $len = $end - $start + 1;
        $status = 206;
    }
}

http_response_code($status);
header("Content-Type: $mime");
header("Accept-Ranges: bytes");
header("Content-Length: $len");
header("Content-Disposition: inline; filename=\"" . basename($file) . "\"");
header("Cache-Control: private, max-age=3600");
if ($status === 206) header("Content-Range: bytes $start-$end/$size");

// Stream in 8 KB chunks
$fp = fopen($file, 'rb');
fseek($fp, $start);
$remaining = $len;
while ($remaining > 0 && !feof($fp) && connection_status() === 0) {
    $read = ($remaining > 8192) ? 8192 : $remaining;
    echo fread($fp, $read);
    flush();
    $remaining -= $read;
}
fclose($fp);