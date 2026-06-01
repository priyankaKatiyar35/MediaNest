<?php
/**
 * MediaNest AI — Auto-generate quiz checkpoints from a video transcript
 * --------------------------------------------------------------
 * POST: video_id, csrf, num_checkpoints (optional 2-6), action ('draft' | 'save')
 *
 * Two modes:
 *   1) action=draft → returns JSON array of suggested checkpoints (does NOT save)
 *   2) action=save → POST the (possibly admin-edited) checkpoints JSON → inserts into DB
 *
 * The "draft" step is intentionally separate so the admin can review and
 * edit AI output before it lands in the live quiz. No surprise content.
 */
require_once __DIR__ . '/admin_auth.php';
requireAdmin();
require_once __DIR__ . '/ai_lib.php';
global $conn;

header('Content-Type: application/json');
set_time_limit(90);

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new RuntimeException('POST only.');
    if (!csrfCheck($_POST['csrf'] ?? '')) throw new RuntimeException('Session expired.');

    $vid    = intval($_POST['video_id'] ?? 0);
    $action = $_POST['action'] ?? 'draft';
    if ($vid <= 0) throw new RuntimeException('Missing video_id.');

    // ════════════════════════════════════════════════════════════
    // MODE: SAVE — admin reviewed the draft, now write to DB
    // ════════════════════════════════════════════════════════════
    if ($action === 'save') {
        $payload = json_decode($_POST['checkpoints'] ?? '[]', true);
        if (!is_array($payload) || empty($payload)) {
            throw new RuntimeException('No checkpoints supplied.');
        }
        $saved = 0;
        foreach ($payload as $cp) {
            $trigger = (float)($cp['trigger_time'] ?? 0);
            $label   = trim($cp['group_label'] ?? '');
            $qs      = $cp['questions'] ?? [];
            if ($label === '' || empty($qs)) continue;

            // Insert the checkpoint
            $stmt = mysqli_prepare($conn, "INSERT INTO video_quizzes (video_id, trigger_time, group_label) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'ids', $vid, $trigger, $label);
            if (!mysqli_stmt_execute($stmt)) { mysqli_stmt_close($stmt); continue; }
            $cp_id = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

            // Insert each question
            foreach ($qs as $q) {
                $qt   = trim($q['question_text'] ?? '');
                $a    = trim($q['option_a'] ?? '');
                $b    = trim($q['option_b'] ?? '');
                $c    = trim($q['option_c'] ?? '');
                $d    = trim($q['option_d'] ?? '');
                $corr = intval($q['correct_option'] ?? 1);
                $expl = trim($q['explanation'] ?? '');
                if ($qt === '' || $a === '' || $b === '' || $c === '' || $d === '') continue;
                if ($corr < 1 || $corr > 4) $corr = 1;

                $stmt = mysqli_prepare($conn,
                    "INSERT INTO quiz_options (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_option, explanation)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, 'isssssis', $cp_id, $qt, $a, $b, $c, $d, $corr, $expl);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $saved++;
            }
        }
        adminAuditLog('ai_quiz_save', "Video #$vid: " . count($payload) . " checkpoints, $saved questions saved");
        echo json_encode(['ok' => true, 'checkpoints' => count($payload), 'questions' => $saved]);
        exit;
    }

    // ════════════════════════════════════════════════════════════
    // MODE: DRAFT — generate suggestions
    // ════════════════════════════════════════════════════════════
    $num_cp = max(2, min(6, intval($_POST['num_checkpoints'] ?? 3)));

    // Load transcript + segments
    $stmt = mysqli_prepare($conn,
        "SELECT t.full_text, t.segments, t.duration_sec, v.title
         FROM video_transcripts t JOIN video v ON v.id = t.video_id
         WHERE t.video_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $vid);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$row) {
        echo json_encode(['ok'=>false,'error'=>'No transcript for this video yet. Transcribe it first from Manage Content.']);
        exit;
    }

    $title    = $row['title'];
    $duration = (int) $row['duration_sec'];
    $segments = json_decode($row['segments'], true) ?: [];

    // Build a timestamped transcript representation the model can reason over.
    // Format: [MM:SS] segment text
    $timestamped_lines = [];
    foreach ($segments as $s) {
        if (empty($s['text'])) continue;
        $st = (int)($s['start'] ?? 0);
        $timestamped_lines[] = sprintf('[%d:%02d] %s', floor($st/60), $st%60, trim($s['text']));
    }
    $timestamped = implode("\n", $timestamped_lines);

    // Trim if very long (keep within model context)
    if (strlen($timestamped) > 18000) {
        // Take from the middle, since intro and outro are usually less rich
        $timestamped = mb_substr($timestamped, 0, 9000) . "\n[... middle truncated ...]\n" . mb_substr($timestamped, -9000);
    }

    $sys = <<<PROMPT
You are an instructional designer creating mid-video knowledge-check quizzes.

INPUT: a transcript with timestamps in [M:SS] format and a target number of checkpoints.

TASK: create exactly N quiz checkpoints, EVENLY SPACED across the video duration.
Each checkpoint has:
- trigger_time (in seconds, an integer) — pick a moment shortly AFTER the relevant content was discussed, not before
- group_label (3-5 words) — what the checkpoint tests
- 1 or 2 questions, each with 4 multiple-choice options and ONE correct answer

RULES:
- Questions must be answerable PURELY from what the speaker actually said. No outside knowledge.
- Distractors (wrong options) must be plausible but clearly wrong if you watched the video.
- Avoid "all of the above" / "none of the above".
- Keep questions concise (one sentence).
- Include a 1-sentence explanation for the correct answer.

OUTPUT FORMAT: STRICT JSON. No markdown, no commentary, no code fences.
{
  "checkpoints": [
    {
      "trigger_time": 120,
      "group_label": "Introduction concepts",
      "questions": [
        {
          "question_text": "...",
          "option_a": "...",
          "option_b": "...",
          "option_c": "...",
          "option_d": "...",
          "correct_option": 2,
          "explanation": "..."
        }
      ]
    }
  ]
}
PROMPT;

    $user = "Video title: $title\n"
          . "Video duration: $duration seconds\n"
          . "Target checkpoints: $num_cp\n\n"
          . "Transcript with timestamps:\n$timestamped\n\n"
          . "Return JSON now.";

    $raw = groq_chat(
        [
            ['role' => 'system', 'content' => $sys],
            ['role' => 'user',   'content' => $user],
        ],
        ['temperature' => 0.4, 'max_tokens' => 2800]
    );

    // Extract JSON
    $json = $raw;
    if (preg_match('/\{[\s\S]*\}/', $raw, $m)) $json = $m[0];
    $parsed = json_decode($json, true);
    if (!is_array($parsed) || empty($parsed['checkpoints'])) {
        throw new RuntimeException('AI returned unparseable output. Try again or reduce checkpoint count. Raw: ' . mb_substr($raw, 0, 200));
    }

    // Normalize: clamp trigger_time, fill missing fields
    $cps = [];
    foreach ($parsed['checkpoints'] as $cp) {
        $trigger = (int) round((float)($cp['trigger_time'] ?? 0));
        if ($duration > 0) $trigger = max(5, min($duration - 5, $trigger)); // keep within bounds
        $cps[] = [
            'trigger_time' => $trigger,
            'group_label'  => trim($cp['group_label'] ?? 'Checkpoint'),
            'questions'    => array_map(function($q) {
                return [
                    'question_text'  => trim($q['question_text'] ?? ''),
                    'option_a'       => trim($q['option_a'] ?? ''),
                    'option_b'       => trim($q['option_b'] ?? ''),
                    'option_c'       => trim($q['option_c'] ?? ''),
                    'option_d'       => trim($q['option_d'] ?? ''),
                    'correct_option' => max(1, min(4, intval($q['correct_option'] ?? 1))),
                    'explanation'    => trim($q['explanation'] ?? ''),
                ];
            }, $cp['questions'] ?? []),
        ];
    }
    // Sort by time
    usort($cps, fn($a, $b) => $a['trigger_time'] <=> $b['trigger_time']);

    adminAuditLog('ai_quiz_draft', "Video #$vid: drafted " . count($cps) . " checkpoints");

    echo json_encode([
        'ok' => true,
        'video_id'    => $vid,
        'title'       => $title,
        'duration'    => $duration,
        'checkpoints' => $cps,
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}