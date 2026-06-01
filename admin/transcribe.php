<?php
/**
 * MediaNest AI — Transcribe Video endpoint
 * --------------------------------------------------------------
 * POST: video_id, csrf
 * Action: extracts audio with ffmpeg → sends to Groq Whisper →
 *         stores transcript + segments in video_transcripts table
 * Returns: JSON {ok: bool, text: string, duration: float, error?: string}
 */
require_once __DIR__ . '/admin_auth.php';
requireAdmin();
require_once __DIR__ . '/ai_lib.php';
global $conn;

header('Content-Type: application/json');
set_time_limit(360);  // transcription can be slow

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('POST only.');
    }
    if (!csrfCheck($_POST['csrf'] ?? '')) {
        throw new RuntimeException('Session expired.');
    }

    $vid = intval($_POST['video_id'] ?? 0);
    if ($vid <= 0) throw new RuntimeException('Missing video_id.');

    // Look up the video filename
    $stmt = mysqli_prepare($conn, "SELECT name, title FROM video WHERE id=?");
    mysqli_stmt_bind_param($stmt, 'i', $vid);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$row) throw new RuntimeException('Video not found.');

    $video_path = __DIR__ . '/upload/' . $row['name'];
    if (!is_file($video_path)) throw new RuntimeException("Video file missing on disk: {$row['name']}");

    // Extract audio
    $audio_path = extract_audio($video_path);

    // Transcribe
    try {
        $result = groq_transcribe($audio_path);
    } finally {
        @unlink($audio_path);
    }

    // Save (upsert)
    $text     = trim($result['text']);
    $segments = json_encode($result['segments']);
    $lang     = $result['language'];
    $dur      = (int) round($result['duration']);
    $model    = ai_cfg('whisper_model');

    // Refuse silent/music-only videos.
    // Whisper hallucinates short phrases like " you", " Thank you.", " ." when there's
    // no real speech. We catch that here so the DB never holds junk transcripts.
    $word_count = str_word_count($text);
    if ($word_count < 5 || strlen($text) < 20) {
        throw new RuntimeException(
            "Whisper found no real speech in this video (got '" . mb_substr($text, 0, 50) .
            "'). This usually means the video has no audio track, silent audio, or only background music. " .
            "Nothing was saved."
        );
    }

    $stmt = mysqli_prepare($conn,
        "INSERT INTO video_transcripts (video_id, full_text, segments, language, duration_sec, model)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            full_text=VALUES(full_text),
            segments=VALUES(segments),
            language=VALUES(language),
            duration_sec=VALUES(duration_sec),
            model=VALUES(model),
            created_at=CURRENT_TIMESTAMP");
    mysqli_stmt_bind_param($stmt, 'isssis', $vid, $text, $segments, $lang, $dur, $model);
    if (!mysqli_stmt_execute($stmt)) {
        throw new RuntimeException('DB error: ' . mysqli_stmt_error($stmt));
    }
    mysqli_stmt_close($stmt);

    adminAuditLog('video_transcribe', "#$vid: {$row['title']} ($dur sec, " . strlen($text) . " chars)");

    echo json_encode([
        'ok'        => true,
        'video_id'  => $vid,
        'duration'  => $dur,
        'language'  => $lang,
        'chars'     => strlen($text),
        'preview'   => mb_substr($text, 0, 240),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}