<?php
/**
 * MediaNest AI — Ask a Document (chat-style Q&A)
 * --------------------------------------------------------------
 * POST: file_id, question, history (optional JSON of prior turns)
 * Returns: JSON {ok, answer, file_title, page_refs[]}
 *
 * Strategy:
 *  - Looks up the cached extract from document_extracts
 *  - Trims to fit Groq's free-tier context window
 *  - Calls Groq Llama 3.1 with a strict "answer from the doc only" prompt
 *  - Detects [Page N] markers in the answer to expose page citations to UI
 *
 * Auth: requireLogin (any signed-in user).
 */
require_once __DIR__ . '/../auth/auth.php';
requireLogin();
require_once __DIR__ . '/../admin/ai_lib.php';

$conn = mysqli_connect('localhost', 'root', '', 's&p');
header('Content-Type: application/json');
set_time_limit(60);

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new RuntimeException('POST only.');

    $fid      = intval($_POST['file_id'] ?? 0);
    $question = trim($_POST['question'] ?? '');
    $history  = json_decode($_POST['history'] ?? '[]', true) ?: [];

    if ($fid <= 0)                  throw new RuntimeException('Missing file_id.');
    if ($question === '')           throw new RuntimeException('Please ask a question.');
    if (mb_strlen($question) > 500) throw new RuntimeException('Question too long.');

    // Load extract + file title
    $stmt = mysqli_prepare($conn,
        "SELECT e.full_text, e.page_count, e.word_count, f.file_name, f.file_desc
         FROM document_extracts e
         JOIN files f ON f.file_id = e.file_id
         WHERE e.file_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $fid);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$row) {
        echo json_encode([
            'ok'    => false,
            'error' => 'This document has not been processed for AI yet. Ask an admin to extract it from the Manage Content page.',
        ]);
        exit;
    }

    $title = $row['file_desc'] ?: $row['file_name'];
    $doc   = $row['full_text'];

    // Trim very long docs — keep start and end, drop the middle if needed.
    // 24000 chars ≈ 6000 tokens, leaves room for history + system + answer.
    $MAX = 24000;
    if (strlen($doc) > $MAX) {
        $half = (int)($MAX / 2);
        $doc  = mb_substr($doc, 0, $half)
              . "\n\n[... middle of document truncated for length ...]\n\n"
              . mb_substr($doc, -$half);
    }

    // Build messages
    $sys = "You are an assistant that answers questions strictly from the document provided.\n\n"
         . "RULES:\n"
         . "- ONLY use facts from the document. If the document doesn't contain the answer, say: \"I couldn't find that in this document.\" Don't guess.\n"
         . "- If the document uses [Page N] markers, cite them inline like (page 3) so users can verify.\n"
         . "- Keep answers concise — 1 to 4 sentences unless the user explicitly asks for detail.\n"
         . "- If the question is ambiguous, ask one clarifying question first.\n\n"
         . "DOCUMENT TITLE: $title\n"
         . "DOCUMENT CONTENT:\n$doc";

    $messages = [['role' => 'system', 'content' => $sys]];

    // Append prior turns (cap to last 6 messages to keep context lean)
    $history = array_slice($history, -6);
    foreach ($history as $turn) {
        if (!isset($turn['role'], $turn['content'])) continue;
        if (!in_array($turn['role'], ['user', 'assistant'])) continue;
        $messages[] = ['role' => $turn['role'], 'content' => mb_substr((string)$turn['content'], 0, 2000)];
    }
    $messages[] = ['role' => 'user', 'content' => $question];

    $answer = groq_chat($messages, ['temperature' => 0.2, 'max_tokens' => 700]);
    $answer = trim($answer);

    // Extract page citations from the answer (so UI can show jump-to-page chips)
    preg_match_all('/\(page\s+(\d+)\)|\[page\s+(\d+)\]/i', $answer, $matches);
    $pages = array_unique(array_filter(array_merge($matches[1] ?? [], $matches[2] ?? [])));
    sort($pages, SORT_NUMERIC);

    echo json_encode([
        'ok'         => true,
        'answer'     => $answer,
        'file_title' => $title,
        'page_refs'  => array_map('intval', array_values($pages)),
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}