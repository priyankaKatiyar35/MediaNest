<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

// --- AUTH GATE: only logged-in users may stream protected files ---
require_once __DIR__ . '/../auth/auth.php';
if (!isLoggedIn()) {
    http_response_code(401);
    exit('Authentication required');
}

include 'connect.php';

if (!isset($_GET['file_id'])) {
    http_response_code(403);
    exit('Forbidden');
}

$fileId = (int)$_GET['file_id'];

if ($fileId <= 0) {
    http_response_code(400);
    exit('Invalid file ID');
}

// Verify file exists in DB — no quotes around integer
$res = mysqli_query($con, "SELECT * FROM files WHERE file_id = $fileId");

if (!$res) {
    http_response_code(500);
    error_log('DB error: ' . mysqli_error($con));
    exit('Server error');
}

$file = mysqli_fetch_assoc($res);

if (!$file) {
    http_response_code(404);
    exit('Not found');
}

$filePath = $file['file_path'];

// Build and resolve full path
$rawPath     = __DIR__ . '/../admin/' . $filePath;
$fullPath    = realpath($rawPath);
$allowedBase = realpath(__DIR__ . '/../admin/uploads');

if (!$allowedBase) {
    http_response_code(500);
    error_log('Allowed base not found');
    exit('Server configuration error');
}

if (!$fullPath) {
    http_response_code(404);
    error_log('File not on disk. Raw path: ' . $rawPath);
    exit('File not found on disk');
}

if (strpos($fullPath, $allowedBase) !== 0) {
    http_response_code(403);
    error_log('Path traversal blocked: ' . $fullPath);
    exit('Forbidden');
}

if (!is_file($fullPath) || !is_readable($fullPath)) {
    http_response_code(403);
    error_log('File not readable: ' . $fullPath);
    exit('Forbidden');
}

$ext      = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
$fileSize = filesize($fullPath);

$mimeMap = [
    'pdf'  => 'application/pdf',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls'  => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'ppt'  => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'odt'  => 'application/vnd.oasis.opendocument.text',
    'ods'  => 'application/vnd.oasis.opendocument.spreadsheet',
    'odp'  => 'application/vnd.oasis.opendocument.presentation',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
    'svg'  => 'image/svg+xml',
    'bmp'  => 'image/bmp',
    'txt'  => 'text/plain',
    'csv'  => 'text/csv',
    'json' => 'application/json',
    'xml'  => 'application/xml',
    'mp4'  => 'video/mp4',
    'webm' => 'video/webm',
    'ogg'  => 'video/ogg',
    'mp3'  => 'audio/mpeg',
    'wav'  => 'audio/wav',
    'm4a'  => 'audio/mp4',
];

$mime = $mimeMap[$ext] ?? 'application/octet-stream';

// Clear any accidental output buffers
if (ob_get_level()) {
    ob_end_clean();
}

// Replace your current headers with these:
header('Content-Type: ' . $mime);
header('Content-Length: ' . $fileSize);
header('Content-Disposition: inline; filename="protected-file"'); // fake filename
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Accept-Ranges: none');
header('X-Frame-Options: SAMEORIGIN');
// This CSP only allows the file to render inside your own site
// header('Content-Security-Policy: default-src \'self\'');
header_remove('X-Powered-By');
header_remove('Server');
// NOTE: No CSP header — it was blocking PDF/image rendering

readfile($fullPath);
exit;