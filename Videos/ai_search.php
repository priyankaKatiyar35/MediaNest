<?php
/**
 * MediaNest AI — Smart Video Search
 * --------------------------------------------------------------
 * GET: q (query string), limit (optional, default 20)
 * Returns: JSON array of matches with video metadata + timestamps + snippets
 *
 * Strategy:
 *  1) FULLTEXT search on `video_transcripts.full_text` for ranking
 *  2) For each match, walk Whisper's segment array to find the segments
 *     that contain the query terms → return as timestamped jumps
 *  3) Fall back to LIKE search if FULLTEXT returns nothing (handles
 *     short queries that fall below MySQL's word length minimum)
 */
require_once __DIR__ . '/../auth/auth.php';
requireLogin();
header('Content-Type: application/json');

$conn = mysqli_connect('localhost', 'root', '', 's&p');
if (!$conn) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'DB error']); exit; }

$q     = trim($_GET['q'] ?? '');
$limit = min(50, max(1, intval($_GET['limit'] ?? 20)));

if ($q === '' || mb_strlen($q) < 2) {
    echo json_encode(['ok' => true, 'matches' => [], 'query' => $q]);
    exit;
}

// Try FULLTEXT first (NATURAL LANGUAGE MODE handles phrase relevance nicely)
$matches = [];
$stmt = mysqli_prepare($conn, "
    SELECT t.video_id, t.full_text, t.segments, t.duration_sec,
           v.title, v.name, v.des, c.name AS cat_name,
           MATCH(t.full_text) AGAINST(? IN NATURAL LANGUAGE MODE) AS score
    FROM video_transcripts t
    JOIN video v ON v.id = t.video_id
    LEFT JOIN video_categories c ON c.id = v.category_id
    WHERE MATCH(t.full_text) AGAINST(? IN NATURAL LANGUAGE MODE)
    ORDER BY score DESC
    LIMIT ?");
mysqli_stmt_bind_param($stmt, 'ssi', $q, $q, $limit);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($r = mysqli_fetch_assoc($res)) $matches[] = $r;
mysqli_stmt_close($stmt);

// Fallback: LIKE search (handles 1-2 char words that FULLTEXT skips)
if (empty($matches)) {
    $like = '%' . $q . '%';
    $stmt = mysqli_prepare($conn, "
        SELECT t.video_id, t.full_text, t.segments, t.duration_sec,
               v.title, v.name, v.des, c.name AS cat_name,
               1.0 AS score
        FROM video_transcripts t
        JOIN video v ON v.id = t.video_id
        LEFT JOIN video_categories c ON c.id = v.category_id
        WHERE t.full_text LIKE ?
        ORDER BY t.video_id DESC
        LIMIT ?");
    mysqli_stmt_bind_param($stmt, 'si', $like, $limit);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($r = mysqli_fetch_assoc($res)) $matches[] = $r;
    mysqli_stmt_close($stmt);
}

/**
 * Find segments containing any query word, return up to 3 best hits per video.
 * Returns array of ['t_start' => float, 'snippet' => string]
 */
function find_moments($segments_json, $query) {
    $segs = json_decode($segments_json, true);
    if (!is_array($segs) || empty($segs)) return [];

    // Split query into significant words (3+ chars, lowercase)
    $words = preg_split('/\s+/', mb_strtolower(trim($query)));
    $words = array_filter($words, fn($w) => mb_strlen($w) >= 3);
    if (empty($words)) $words = [mb_strtolower($query)];

    $hits = [];
    foreach ($segs as $seg) {
        $text = mb_strtolower($seg['text'] ?? '');
        if ($text === '') continue;
        // Score = how many query words appear in this segment
        $score = 0;
        foreach ($words as $w) if (mb_strpos($text, $w) !== false) $score++;
        if ($score > 0) {
            $hits[] = [
                't_start' => (float)($seg['start'] ?? 0),
                't_end'   => (float)($seg['end']   ?? 0),
                'snippet' => trim($seg['text']),
                'score'   => $score,
            ];
        }
    }
    if (empty($hits)) return [];
    // Sort by score desc, then by chronological time asc among equally-scored ones
    usort($hits, fn($a, $b) => $b['score'] <=> $a['score'] ?: $a['t_start'] <=> $b['t_start']);
    return array_slice($hits, 0, 3);
}

/**
 * Highlight matched query words inside snippet text (server-side, safe).
 */
function highlight($text, $query) {
    $text = htmlspecialchars($text);
    $words = preg_split('/\s+/', trim($query));
    $words = array_filter($words, fn($w) => mb_strlen($w) >= 3);
    foreach ($words as $w) {
        $text = preg_replace('/(' . preg_quote(htmlspecialchars($w), '/') . ')/iu',
                             '<mark>$1</mark>', $text);
    }
    return $text;
}

$out = [];
foreach ($matches as $m) {
    $moments = find_moments($m['segments'], $q);
    if (empty($moments)) continue; // no timestamp hit → skip
    $out[] = [
        'video_id' => (int)$m['video_id'],
        'title'    => $m['title'],
        'desc'     => $m['des'],
        'category' => $m['cat_name'],
        'duration' => (int)$m['duration_sec'],
        'score'    => round((float)$m['score'], 3),
        'moments'  => array_map(function($mo) use ($q) {
            return [
                't'        => round($mo['t_start'], 1),
                't_label'  => sprintf('%d:%02d', floor($mo['t_start'] / 60), $mo['t_start'] % 60),
                'snippet'  => highlight($mo['snippet'], $q),
            ];
        }, $moments),
    ];
}

echo json_encode(['ok' => true, 'matches' => $out, 'query' => $q]);