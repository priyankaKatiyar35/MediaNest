<?php
/**
 * MediaNest — Gated photo streaming
 * --------------------------------------------------------------
 * GET ?id=N&size=thumb|full → streams the photo if user is logged in.
 * Replaces direct <img src="../admin/gcatch/foo.jpg"> with
 * <img src="../Photo/serve_photo.php?id=42&size=thumb">.
 *
 * Album cover use:    ?type=cover&id=42  (looks up tbl_album.image)
 * Album photo use:    ?type=photo&id=99  (looks up tbl_gallery.gimages)
 */
require_once __DIR__ . '/../auth/auth.php';
if (!isLoggedIn()) { http_response_code(401); exit('Authentication required'); }
$conn = mysqli_connect('localhost','root','','s&p');

$id   = isset($_GET['id']) ? intval($_GET['id']) : 0;
$type = $_GET['type'] ?? 'photo';
$size = $_GET['size'] ?? 'full';
if ($id <= 0) { http_response_code(400); exit('Bad request'); }

$filename = null;
$dir = null;

if ($type === 'cover') {
    $stmt = mysqli_prepare($conn, "SELECT image FROM tbl_album WHERE albumid=?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if ($row && !empty($row['image'])) { $filename = $row['image']; $dir = '/../admin/acatch/'; }
} else { // photo
    $stmt = mysqli_prepare($conn, "SELECT gimages FROM tbl_gallery WHERE gid=? AND status='process'");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if ($row) {
        $filename = $row['gimages'];
        $dir = $size === 'thumb' ? '/../admin/gcatch/' : '/../admin/gupload/';
    }
}
if (!$filename) { http_response_code(404); exit('Not found'); }

$file = __DIR__ . $dir . basename($filename);
// Thumb fallback to full
if (!is_file($file) && $size === 'thumb') $file = __DIR__ . '/../admin/gupload/' . basename($filename);
if (!is_file($file)) { http_response_code(404); exit('File missing'); }

$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$mime = [
    'jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png',
    'gif'=>'image/gif','webp'=>'image/webp',
][$ext] ?? 'application/octet-stream';

header("Content-Type: $mime");
header("Content-Length: " . filesize($file));
header("Cache-Control: private, max-age=86400");
readfile($file);