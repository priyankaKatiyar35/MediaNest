<?php
/**
 * MediaNest AI — Video Summary endpoint (user-facing)
 * --------------------------------------------------------------
 * GET: video_id
 * Returns: JSON {ok, summary, key_topics[], cached, length_sec}
 *
 * Behavior:
 *  1) If a cached summary exists in video_summaries → return it (free, instant)
 *  2) Else if a transcript exists → call Groq chat to generate + cache it
 *  3) Else → return ok:false with "no transcript yet" hint (user-friendly)
 *
 * Auth: requireLogin (any signed-in user can read summaries).
 * Writes go through the AI lib which uses your Groq key server-side.
 */
require_once __DIR__ . '/../auth/auth.php';
requireLogin();
require_once __DIR__ . '/../admin/ai_lib.php';   // groq_chat + config
$conn = mysqli_connect('localhost', 'root', '', 's&p');

header('Content-Type: application/json');
set_time_limit(60);

try {
    $vid = intval($_GET['video_id'] ?? 0);
    if ($vid <= 0) throw new RuntimeException('Missing video_id');

    // 1) Cache hit?
    $stmt = mysqli_prepare($conn,
        "SELECT s.summary, s.key_topics, s.created_at, v.title, t.duration_sec
         FROM video_summaries s
         JOIN video v ON v.id = s.video_id
         LEFT JOIN video_transcripts t ON t.video_id = s.video_id
         WHERE s.video_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $vid);
    mysqli_stmt_execute($stmt);
    $cached = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if ($cached) {
        echo json_encode([
            'ok'         => true,
            'cached'     => true,
            'title'      => $cached['title'],
            'summary'    => $cached['summary'],
            'key_topics' => array_filter(explode("\n", $cached['key_topics'] ?? '')),
            'length_sec' => (int)($cached['duration_sec'] ?? 0),
            'generated'  => $cached['created_at'],
        ]);
        exit;
    }

    // 2) Need to generate — load transcript
    $stmt = mysqli_prepare($conn,
        "SELECT t.full_text, t.duration_sec, v.title
         FROM video_transcripts t
         JOIN video v ON v.id = t.video_id
         WHERE t.video_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $vid);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$row) {
        echo json_encode([
            'ok'    => false,
            'error' => 'This video has not been transcribed yet. Ask an admin to transcribe it from the Manage Content page.',
        ]);
        exit;
    }

    // Trim very long transcripts to fit the model context (rough: 1 token ≈ 4 chars)
    $transcript = trim($row['full_text']);

    // Refuse to summarize when transcript is empty / hallucination-only.
    // Whisper returns " you" / " Thank you" / "." etc on silent or music-only audio.
    $word_count = str_word_count($transcript);
    if ($word_count < 20) {
        echo json_encode([
            'ok'    => false,
            'error' => 'This video does not appear to contain spoken content (transcript is empty or very short). Summaries are only generated for videos with audible speech.',
        ]);
        exit;
    }

    if (strlen($transcript) > 24000) {
        $transcript = mb_substr($transcript, 0, 24000) . "\n[... transcript truncated ...]";
    }

    $sys = "You are summarizing a video transcript for a busy professional. "
         . "Output VALID JSON only, no markdown, no preamble. "
         . "Schema: {\"summary\": \"<3-4 sentence overview>\", \"topics\": [\"<topic 1>\", \"<topic 2>\", ...]} "
         . "Topics: 3-6 short bullet points capturing the main themes (max 8 words each). "
         . "Summary: tell the user what they would learn or gain by watching, in plain language.";

    $user = "Video title: " . $row['title']
          . "\n\nTranscript:\n" . $transcript
          . "\n\nReturn the JSON now.";

    $raw = groq_chat([
        ['role' => 'system', 'content' => $sys],
        ['role' => 'user',   'content' => $user],
    ], ['temperature' => 0.3, 'max_tokens' => 700]);

    // Try to extract JSON from the reply (in case the model wrapped it)
    $json = $raw;
    if (preg_match('/\{[\s\S]*\}/', $raw, $m)) $json = $m[0];
    $parsed = json_decode($json, true);

    if (!is_array($parsed) || empty($parsed['summary'])) {
        // Soft fallback: use whole reply as summary
        $parsed = ['summary' => trim($raw), 'topics' => []];
    }

    $summary = (string)$parsed['summary'];
    $topics  = array_values(array_filter(array_map('trim', (array)($parsed['topics'] ?? []))));
    $topics_blob = implode("\n", $topics);
    $model = ai_cfg('chat_model');

    // Cache it (UPSERT)
    $stmt = mysqli_prepare($conn,
        "INSERT INTO video_summaries (video_id, summary, key_topics, model)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            summary=VALUES(summary),
            key_topics=VALUES(key_topics),
            model=VALUES(model),
            created_at=CURRENT_TIMESTAMP");
    mysqli_stmt_bind_param($stmt, 'isss', $vid, $summary, $topics_blob, $model);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    echo json_encode([
        'ok'         => true,
        'cached'     => false,
        'title'      => $row['title'],
        'summary'    => $summary,
        'key_topics' => $topics,
        'length_sec' => (int)$row['duration_sec'],
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}