<?php
// ── ALL AJAX HANDLERS MUST BE FIRST — before any output/includes ──
require_once __DIR__ . '/admin_auth.php';

// Auth: AJAX endpoints get JSON, page load gets redirect
function _qe_is_ajax() {
    return isset($_POST['ajax_save']) || isset($_POST['ajax_add_question'])
        || isset($_POST['ajax_edit_group']) || isset($_POST['ajax_edit_question'])
        || isset($_POST['ajax_delete_question']) || isset($_POST['delete_quiz_id'])
        || isset($_GET['load_quiz']);
}
if (!isAdmin()) {
    if (_qe_is_ajax()) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
        exit;
    }
    requireAdmin();
}

// ── AJAX: Save new quiz group + first question ────────────────────
if (isset($_POST['ajax_save'])) {
    header('Content-Type: application/json');
    if (!$conn) { echo json_encode(['ok'=>false,'error'=>'DB connection failed: '.mysqli_connect_error()]); exit; }
    $video_id    = intval($_POST['video_id']);
    $trigger     = floatval($_POST['trigger_time']);
    $label       = trim($_POST['group_label']);
    $question    = trim($_POST['question_text']);
    $opt_a       = trim($_POST['option_a']);
    $opt_b       = trim($_POST['option_b']);
    $opt_c       = trim($_POST['option_c']);
    $opt_d       = trim($_POST['option_d']);
    $correct     = intval($_POST['correct_option']);
    $explanation = trim($_POST['explanation']);

    $s1 = mysqli_prepare($conn, "INSERT INTO video_quizzes (video_id, trigger_time, group_label) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($s1, 'ids', $video_id, $trigger, $label);
    if (!mysqli_stmt_execute($s1)) { echo json_encode(['ok'=>false,'error'=>mysqli_error($conn)]); exit; }
    $quiz_id = mysqli_insert_id($conn);

    $s2 = mysqli_prepare($conn, "INSERT INTO quiz_options (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_option, explanation) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($s2, 'isssssis', $quiz_id, $question, $opt_a, $opt_b, $opt_c, $opt_d, $correct, $explanation);
    if (!mysqli_stmt_execute($s2)) { echo json_encode(['ok'=>false,'error'=>mysqli_error($conn)]); exit; }

    adminAuditLog('quiz_checkpoint_created', "Video #$video_id @ {$trigger}s — $label");
    echo json_encode(['ok'=>true, 'quiz_id'=>$quiz_id, 'trigger'=>$trigger]);
    exit;
}

// ── AJAX: Add extra question to existing quiz group ───────────────
if (isset($_POST['ajax_add_question'])) {
    header('Content-Type: application/json');
    if (!$conn) { echo json_encode(['ok'=>false,'error'=>'DB connection failed']); exit; }
    $quiz_id     = intval($_POST['quiz_id']);
    $question    = trim($_POST['question_text']);
    $opt_a       = trim($_POST['option_a']);
    $opt_b       = trim($_POST['option_b']);
    $opt_c       = trim($_POST['option_c']);
    $opt_d       = trim($_POST['option_d']);
    $correct     = intval($_POST['correct_option']);
    $explanation = trim($_POST['explanation']);

    $stmt = mysqli_prepare($conn, "INSERT INTO quiz_options (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_option, explanation) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'isssssis', $quiz_id, $question, $opt_a, $opt_b, $opt_c, $opt_d, $correct, $explanation);
    if (!mysqli_stmt_execute($stmt)) { echo json_encode(['ok'=>false,'error'=>mysqli_error($conn)]); exit; }
    adminAuditLog('quiz_question_added', "Quiz #$quiz_id");
    echo json_encode(['ok'=>true, 'qo_id'=>mysqli_insert_id($conn)]);
    exit;
}

// ── AJAX: Edit quiz group label + trigger time ────────────────────
if (isset($_POST['ajax_edit_group'])) {
    header('Content-Type: application/json');
    $quiz_id = intval($_POST['quiz_id']);
    $trigger = floatval($_POST['trigger_time']);
    $label   = trim($_POST['group_label']);
    $stmt = mysqli_prepare($conn, "UPDATE video_quizzes SET trigger_time=?, group_label=? WHERE id=?");
    mysqli_stmt_bind_param($stmt, 'dsi', $trigger, $label, $quiz_id);
    mysqli_stmt_execute($stmt);
    adminAuditLog('quiz_checkpoint_updated', "Quiz #$quiz_id label=$label");
    echo json_encode(['ok'=>true]); exit;
}

// ── AJAX: Edit a single question ──────────────────────────────────
if (isset($_POST['ajax_edit_question'])) {
    header('Content-Type: application/json');
    $qo_id       = intval($_POST['qo_id']);
    $question    = trim($_POST['question_text']);
    $opt_a       = trim($_POST['option_a']);
    $opt_b       = trim($_POST['option_b']);
    $opt_c       = trim($_POST['option_c']);
    $opt_d       = trim($_POST['option_d']);
    $correct     = intval($_POST['correct_option']);
    $explanation = trim($_POST['explanation']);
    $stmt = mysqli_prepare($conn,
        "UPDATE quiz_options SET question_text=?, option_a=?, option_b=?, option_c=?, option_d=?, correct_option=?, explanation=? WHERE id=?");
    mysqli_stmt_bind_param($stmt, 'sssssisi', $question, $opt_a, $opt_b, $opt_c, $opt_d, $correct, $explanation, $qo_id);
    mysqli_stmt_execute($stmt);
    adminAuditLog('quiz_question_updated', "Question #$qo_id");
    echo json_encode(['ok'=>true]); exit;
}

// ── AJAX: Delete single question ──────────────────────────────────
if (isset($_POST['ajax_delete_question'])) {
    header('Content-Type: application/json');
    $qo_id = intval($_POST['qo_id']);
    $stmt = mysqli_prepare($conn, "DELETE FROM quiz_options WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $qo_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    adminAuditLog('quiz_question_deleted', "Question #$qo_id");
    echo json_encode(['ok'=>true]); exit;
}

// ── AJAX: Delete entire quiz group ────────────────────────────────
if (isset($_POST['delete_quiz_id'])) {
    header('Content-Type: application/json');
    $qid = intval($_POST['delete_quiz_id']);
    $stmt = mysqli_prepare($conn, "DELETE FROM quiz_options WHERE quiz_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $qid);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    $stmt = mysqli_prepare($conn, "DELETE FROM video_quizzes WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $qid);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    adminAuditLog('quiz_checkpoint_deleted', "Quiz #$qid");
    echo json_encode(['ok'=>true]); exit;
}

// ── AJAX: Load all questions for a quiz group ─────────────────────
if (isset($_GET['load_quiz'])) {
    header('Content-Type: application/json');
    $qid = intval($_GET['load_quiz']);
    $stmt = mysqli_prepare($conn, "SELECT * FROM video_quizzes WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $qid);
    mysqli_stmt_execute($stmt);
    $group = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($conn, "SELECT * FROM quiz_options WHERE quiz_id = ? ORDER BY id ASC");
    mysqli_stmt_bind_param($stmt, 'i', $qid);
    mysqli_stmt_execute($stmt);
    $res2 = mysqli_stmt_get_result($stmt);
    $questions = [];
    while ($r = mysqli_fetch_assoc($res2)) $questions[] = $r;
    mysqli_stmt_close($stmt);
    $group['questions'] = $questions;
    echo json_encode($group); exit;
}

// ── Page load ────────────────────────────────────────────────────
$sel_vid = isset($_GET['vid']) ? intval($_GET['vid']) : 0;
$vres    = mysqli_query($conn, "SELECT id, title, name FROM video ORDER BY id DESC");
$videos  = [];
while ($r = mysqli_fetch_assoc($vres)) $videos[] = $r;

$existing = [];
if ($sel_vid) {
    $stmt = mysqli_prepare($conn, "
        SELECT vq.id, vq.trigger_time, vq.group_label, COUNT(qo.id) AS q_count
        FROM video_quizzes vq
        LEFT JOIN quiz_options qo ON qo.quiz_id = vq.id
        WHERE vq.video_id = ?
        GROUP BY vq.id ORDER BY vq.trigger_time ASC");
    mysqli_stmt_bind_param($stmt, 'i', $sel_vid);
    mysqli_stmt_execute($stmt);
    $r = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($r)) $existing[] = $row;
    mysqli_stmt_close($stmt);
}

$video_file = $video_title = '';
foreach ($videos as $v) {
    if ($v['id'] == $sel_vid) {
        $video_file  = htmlspecialchars($v['name']);
        $video_title = htmlspecialchars($v['title']);
    }
}

function questionFields($prefix) {
    $letters = ['A','B','C','D'];
    $keys    = ['a','b','c','d'];
    ob_start(); ?>
    <div class="qe-field">
        <label>Question</label>
        <input type="text" id="<?php echo $prefix; ?>_question" placeholder="Type your question here...">
    </div>
    <div class="qe-options-grid">
        <?php foreach ($keys as $i => $k): ?>
        <div class="qe-opt-box" id="<?php echo $prefix; ?>_optbox_<?php echo $k; ?>">
            <label class="qe-opt-label" for="<?php echo $prefix; ?>_radio_<?php echo $k; ?>">
                <div class="qe-opt-letter"><?php echo $letters[$i]; ?></div>
                <input class="qe-opt-radio" type="radio" name="<?php echo $prefix; ?>_correct"
                    id="<?php echo $prefix; ?>_radio_<?php echo $k; ?>"
                    value="<?php echo $i; ?>"
                    onchange="markCorrect('<?php echo $prefix; ?>','<?php echo $k; ?>')">
                <span>Correct answer</span>
            </label>
            <input class="qe-opt-input" type="text" id="<?php echo $prefix; ?>_opt_<?php echo $k; ?>"
                placeholder="Option <?php echo $letters[$i]; ?>">
        </div>
        <?php endforeach; ?>
    </div>
    <div class="qe-field">
        <label>Explanation <span style="font-weight:400;text-transform:none;color:var(--muted);">(optional)</span></label>
        <textarea id="<?php echo $prefix; ?>_explanation" placeholder="Why is this the correct answer?"></textarea>
    </div>
    <?php
    return ob_get_clean();
}

$admin = currentAdmin();
?>
<?php
$body_class = 'page-quiz';
$page_title = 'Quiz Editor';
require __DIR__ . '/header.php';
?>

<style>
:root {
  --bg: #f6f7fb; --bg-elev: #ffffff;
  --text: #0f172a; --text-soft: #475569; --muted: #94a3b8;
  --border: rgba(15, 23, 42, 0.08);
  --shadow-sm: 0 2px 8px rgba(15, 23, 42, 0.06);
  --shadow-md: 0 10px 30px rgba(15, 23, 42, 0.08);
  --shadow-lg: 0 20px 60px rgba(15, 23, 42, 0.12);
  --brand-1: #0ea5e9; --brand-2: #6366f1;
  --radius: 16px; --radius-lg: 22px;
  --grad-brand: linear-gradient(135deg, #06b6d4, #0ea5e9);
  --grad-text: linear-gradient(135deg, #0ea5e9, #6366f1 50%, #a855f7);
  --grad-admin: linear-gradient(135deg, #6366f1, #8b5cf6);
  --green: #10b981; --red: #ef4444; --gold: #f59e0b;
  --training-soft: rgba(99, 102, 241, 0.1);
  /* QE aliases for backward compatibility with JS code */
  --qe-text: var(--text); --qe-muted: var(--text-soft);
}
html.dark {
  --bg: #0a0e1a; --bg-elev: #131826;
  --text: #e2e8f0; --text-soft: #cbd5e1; --muted: #64748b;
  --border: rgba(255, 255, 255, 0.08);
  --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.3);
  --shadow-md: 0 10px 30px rgba(0, 0, 0, 0.4);
  --shadow-lg: 0 20px 60px rgba(0, 0, 0, 0.5);
  --training-soft: rgba(139, 92, 246, 0.15);
}

* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Inter', sans-serif; color: var(--text); background: var(--bg); min-height: 100vh; transition: background .4s, color .4s; }
h1, h2, h3, h4 { font-family: 'Sora', sans-serif; letter-spacing: -0.02em; }
a { color: inherit; text-decoration: none; }
button { font-family: inherit; }

/* Admin nav */
.admin-nav { position: sticky; top: 0; z-index: 50; backdrop-filter: blur(14px); background: color-mix(in srgb, var(--bg) 75%, transparent); border-bottom: 1px solid var(--border); }
.admin-nav-inner { max-width: 1400px; margin: 0 auto; padding: 14px 24px; display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.admin-logo { display: flex; align-items: center; gap: 10px; font-family: 'Sora', sans-serif; font-weight: 700; font-size: 19px; }
.admin-logo-mark { width: 36px; height: 36px; border-radius: 10px; background: var(--grad-admin); color: white; display: grid; place-items: center; box-shadow: 0 6px 18px rgba(99, 102, 241, 0.35); }
.admin-logo-text span { background: var(--grad-text); -webkit-background-clip: text; background-clip: text; color: transparent; }
.admin-pill { font-size: 10px; padding: 3px 9px; border-radius: 999px; background: rgba(99, 102, 241, 0.1); color: #6366f1; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; }
.admin-links { display: flex; gap: 4px; flex-wrap: wrap; }
.admin-links a { padding: 8px 14px; border-radius: 8px; font-size: 13px; font-weight: 500; color: var(--text-soft); display: inline-flex; align-items: center; gap: 7px; transition: all .2s; }
.admin-links a:hover { background: var(--bg-elev); color: var(--text); }
.admin-links a.active { background: var(--grad-admin); color: white; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3); }
.admin-links a i { font-size: 12px; }
.admin-right { display: flex; align-items: center; gap: 8px; }
.admin-user-chip { display: inline-flex; align-items: center; gap: 8px; padding: 5px 10px 5px 5px; border-radius: 999px; background: var(--bg-elev); border: 1px solid var(--border); font-size: 13px; font-weight: 500; }
.admin-user-chip .av { width: 28px; height: 28px; border-radius: 50%; background: var(--grad-admin); color: white; font-weight: 700; font-size: 12px; display: grid; place-items: center; }
.icon-btn { width: 38px; height: 38px; border-radius: 10px; background: transparent; border: 1px solid var(--border); color: var(--text); cursor: pointer; display: grid; place-items: center; transition: all .2s; }
.icon-btn:hover { background: var(--bg-elev); }

/* Page wrapper */
.qe-page { max-width: 1400px; margin: 0 auto; padding: 22px 24px 60px; }
.qe-page-head { margin-bottom: 18px; }
.qe-page-head h1 { font-size: clamp(22px, 2.6vw, 28px); font-weight: 800; margin-bottom: 4px; }
.qe-page-head h1 .gradient-text { background: var(--grad-text); -webkit-background-clip: text; background-clip: text; color: transparent; }
.qe-page-head p { color: var(--text-soft); font-size: 13px; }

/* AI generate button + modal */
.qe-ai-btn { display: inline-flex; align-items: center; gap: 9px; padding: 6px 14px 6px 6px; border-radius: 999px; border: 1px solid rgba(168,85,247,.35); background: linear-gradient(135deg, rgba(168,85,247,.08), rgba(236,72,153,.08)); color: #a855f7; font: inherit; font-size: 13px; font-weight: 700; cursor: pointer; transition: all .2s; margin-left: auto; }
.qe-ai-btn:hover { background: linear-gradient(135deg, #a855f7, #ec4899); color: white; border-color: transparent; transform: translateY(-1px); box-shadow: 0 8px 22px rgba(168,85,247,.35); }
.qe-ai-btn:disabled { opacity: .6; cursor: not-allowed; }
.qe-ai-btn .ai-spark { width: 26px; height: 26px; border-radius: 50%; background: linear-gradient(135deg, #a855f7, #ec4899); color: white; display: grid; place-items: center; font-size: 11px; }
.qe-ai-btn:hover .ai-spark { background: rgba(255,255,255,.25); }
.qe-ai-btn .spin { animation: qe-ai-spin 1s linear infinite; }
@keyframes qe-ai-spin { to { transform: rotate(360deg); } }

.ai-modal { position: fixed; inset: 0; background: rgba(0,0,0,.6); z-index: 5000; display: none; align-items: center; justify-content: center; padding: 20px; backdrop-filter: blur(4px); }
.ai-modal.open { display: flex; }
.ai-modal-box { background: var(--bg-elev, #fff); border-radius: 18px; max-width: 880px; width: 100%; max-height: 90vh; display: flex; flex-direction: column; box-shadow: 0 30px 80px rgba(0,0,0,.5); overflow: hidden; }
.ai-modal-head { padding: 18px 22px; border-bottom: 1px solid var(--border, #eee); display: flex; align-items: center; gap: 12px; background: linear-gradient(135deg, rgba(168,85,247,.08), rgba(236,72,153,.06)); }
.ai-modal-head h3 { font-size: 16px; font-weight: 700; margin: 0; flex: 1; display: inline-flex; align-items: center; gap: 10px; }
.ai-modal-head h3 i { color: #a855f7; }
.ai-modal-head .meta { font-size: 12px; color: var(--text-soft, #64748b); }
.ai-modal-close { width: 32px; height: 32px; border-radius: 8px; background: transparent; border: 1px solid var(--border, #eee); color: var(--text-soft, #64748b); cursor: pointer; display: grid; place-items: center; }
.ai-modal-close:hover { color: #ef4444; border-color: #ef4444; }

.ai-modal-body { flex: 1; overflow-y: auto; padding: 20px 22px; }
.ai-modal-foot { padding: 14px 22px; border-top: 1px solid var(--border, #eee); display: flex; gap: 10px; justify-content: flex-end; background: var(--bg, #f9fafb); }

.ai-config { display: flex; gap: 14px; align-items: center; padding: 14px 16px; border: 1px dashed rgba(168,85,247,.3); border-radius: 12px; background: rgba(168,85,247,.04); margin-bottom: 16px; }
.ai-config label { font-size: 12px; font-weight: 600; color: var(--text-soft, #64748b); }
.ai-config input { width: 70px; padding: 6px 10px; border-radius: 8px; border: 1px solid var(--border, #eee); background: var(--bg, #fff); color: var(--text, #0f172a); font: inherit; font-size: 14px; font-weight: 600; text-align: center; }
.ai-config .hint { font-size: 12px; color: var(--text-soft, #64748b); margin-left: auto; }

.ai-loading { text-align: center; padding: 60px 20px; color: var(--text-soft, #64748b); }
.ai-loading i { display: block; font-size: 38px; color: #a855f7; margin-bottom: 16px; animation: qe-ai-spin 1.4s linear infinite; }

.ai-err { padding: 14px 16px; border-radius: 12px; background: rgba(239,68,68,.1); border: 1px solid rgba(239,68,68,.3); color: #ef4444; font-size: 13px; }

.ai-cp { border: 1px solid var(--border, #eee); border-radius: 14px; margin-bottom: 14px; overflow: hidden; background: var(--bg-elev, #fff); }
.ai-cp-head { padding: 12px 16px; background: var(--bg, #f9fafb); display: flex; align-items: center; gap: 10px; border-bottom: 1px solid var(--border, #eee); }
.ai-cp-time { display: inline-flex; align-items: center; gap: 5px; padding: 4px 12px; border-radius: 999px; background: linear-gradient(135deg, #a855f7, #ec4899); color: white; font-weight: 700; font-size: 12px; }
.ai-cp-time input { background: transparent; border: 0; color: white; width: 50px; text-align: center; font: inherit; font-weight: 700; }
.ai-cp-label { flex: 1; }
.ai-cp-label input { width: 100%; padding: 6px 10px; border-radius: 8px; border: 1px solid var(--border, #eee); background: var(--bg-elev, #fff); color: var(--text, #0f172a); font: inherit; font-size: 14px; font-weight: 600; }
.ai-cp-rm { width: 30px; height: 30px; border-radius: 8px; background: transparent; border: 1px solid var(--border, #eee); color: var(--text-soft, #64748b); cursor: pointer; display: grid; place-items: center; }
.ai-cp-rm:hover { color: #ef4444; border-color: #ef4444; }

.ai-q { padding: 14px 16px; border-top: 1px solid var(--border, #eee); }
.ai-q:first-child { border-top: 0; }
.ai-q-text { width: 100%; padding: 9px 12px; border-radius: 9px; border: 1px solid var(--border, #eee); background: var(--bg, #f9fafb); color: var(--text, #0f172a); font: inherit; font-size: 14px; font-weight: 600; margin-bottom: 10px; resize: vertical; min-height: 50px; }
.ai-opts { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 10px; }
.ai-opt { display: flex; align-items: center; gap: 8px; padding: 7px 10px; border-radius: 9px; background: var(--bg, #f9fafb); border: 1px solid var(--border, #eee); cursor: pointer; transition: all .15s; }
.ai-opt:has(input[type=radio]:checked) { background: rgba(16,185,129,.1); border-color: rgba(16,185,129,.4); }
.ai-opt label-letter { width: 22px; height: 22px; border-radius: 50%; background: var(--bg-elev, #fff); display: grid; place-items: center; font-size: 11px; font-weight: 700; flex-shrink: 0; }
.ai-opt:has(input[type=radio]:checked) .label-letter { background: #10b981; color: white; }
.ai-opt input[type=text] { flex: 1; border: 0; background: transparent; color: var(--text, #0f172a); font: inherit; font-size: 13px; outline: none; }
.ai-opt input[type=radio] { display: none; }
.ai-expl { width: 100%; padding: 7px 12px; border-radius: 9px; border: 1px solid var(--border, #eee); background: rgba(168,85,247,.05); color: var(--text-soft, #64748b); font: inherit; font-size: 12px; font-style: italic; }

/* Sub-topbar: video selector */
.qe-subbar {
    background: var(--bg-elev);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 14px 18px;
    display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
    box-shadow: var(--shadow-sm);
    margin-bottom: 18px;
}
.qe-ts-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--text-soft); flex: 1; min-width: 200px; }
.qe-vid-label { font-size: 13px; font-weight: 600; color: var(--text); }
.qe-vid-pick {
    flex: 0 1 360px;
    padding: 9px 14px; border: 1.5px solid var(--border); border-radius: 10px;
    background: var(--bg); color: var(--text);
    font-family: inherit; font-size: 13px; outline: none;
    transition: border-color .2s, box-shadow .2s;
}
.qe-vid-pick:focus { border-color: #6366f1; box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.12); }

/* Main 2-column layout */
.qe-layout {
    display: grid;
    grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr);
    gap: 20px;
}
@media (max-width: 1100px) { .qe-layout { grid-template-columns: 1fr; } }

/* ── LEFT: video + timeline ── */
.qe-video-side { display: flex; flex-direction: column; gap: 16px; min-width: 0; }

.qe-video-wrap {
    background: #000; border-radius: var(--radius-lg);
    overflow: hidden; box-shadow: var(--shadow-lg);
    border: 1px solid var(--border);
}
#qe-vid { width: 100%; display: block; aspect-ratio: 16/9; background: #000; }

.qe-ts-bar {
    display: flex; align-items: center; justify-content: space-between; gap: 16px;
    background: var(--bg-elev); border: 1px solid var(--border);
    border-radius: var(--radius); padding: 14px 18px;
    box-shadow: var(--shadow-sm);
}
.qe-ts-display {
    font-family: 'Sora', sans-serif; font-weight: 800; font-size: 22px;
    background: var(--grad-text);
    -webkit-background-clip: text; background-clip: text; color: transparent;
    font-variant-numeric: tabular-nums;
}
.qe-ts-bar .qe-ts-label { font-size: 11px; font-weight: 600; color: var(--text-soft); text-transform: uppercase; letter-spacing: .05em; }
.qe-btn-capture {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 10px 18px; border-radius: 10px;
    background: var(--grad-admin); color: white;
    font-weight: 600; font-size: 13px;
    border: none; cursor: pointer;
    box-shadow: 0 6px 18px rgba(99, 102, 241, 0.3);
    transition: transform .2s, box-shadow .2s;
}
.qe-btn-capture:hover { transform: translateY(-2px); box-shadow: 0 10px 24px rgba(99, 102, 241, 0.4); }
.qe-capture-dot { width: 8px; height: 8px; border-radius: 50%; background: white; animation: pulse 2s infinite; }
@keyframes pulse {
  0%,100% { box-shadow: 0 0 0 0 rgba(255,255,255,0.7); }
  70% { box-shadow: 0 0 0 6px rgba(255,255,255,0); }
}

/* Timeline */
.qe-timeline-wrap {
    background: var(--bg-elev); border: 1px solid var(--border);
    border-radius: var(--radius); padding: 18px;
    box-shadow: var(--shadow-sm);
    max-height: 540px;
    overflow-y: auto;
}
.qe-tl-head {
    font-family: 'Sora', sans-serif; font-weight: 700; font-size: 13px;
    color: var(--text); text-transform: uppercase; letter-spacing: .06em;
    margin-bottom: 14px;
    display: flex; align-items: center; gap: 8px;
}
.qe-tl-head::before { content: '\f140'; font-family: 'Font Awesome 6 Free'; font-weight: 900; color: #6366f1; }
.qe-tl-count {
    margin-left: auto; font-size: 11px; padding: 2px 9px; border-radius: 999px;
    background: var(--training-soft); color: #6366f1; font-weight: 700;
}

.qe-tl-item {
    background: var(--bg);
    border: 1.5px solid var(--border);
    border-radius: 12px;
    padding: 12px;
    margin-bottom: 10px;
    transition: all .2s;
}
.qe-tl-item:hover { border-color: #6366f1; }
.qe-tl-item.active-edit {
    border-color: #6366f1;
    background: var(--training-soft);
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.08);
}
.qe-tl-item.offline-item { border-color: var(--gold); background: rgba(245, 158, 11, 0.06); }

.qe-tl-row { display: flex; align-items: center; gap: 12px; }

.qe-tl-badge {
    width: 60px; flex-shrink: 0;
    padding: 8px 4px; text-align: center;
    background: var(--grad-admin); color: white;
    border-radius: 10px;
    font-family: 'Sora', sans-serif; font-weight: 800; font-size: 13px;
    cursor: pointer; transition: transform .2s;
    box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
    font-variant-numeric: tabular-nums;
}
.qe-tl-badge:hover { transform: scale(1.05); }

.qe-tl-info { flex: 1; min-width: 0; cursor: pointer; }
.qe-tl-topic {
    font-family: 'Sora', sans-serif; font-weight: 700; font-size: 14px;
    color: var(--text); margin-bottom: 2px;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.qe-tl-meta { font-size: 11px; color: var(--text-soft); display: flex; align-items: center; gap: 6px; }
.qe-offline-badge {
    display: inline-flex; align-items: center; gap: 4px;
    background: rgba(245, 158, 11, 0.15); color: var(--gold);
    font-size: 10px; font-weight: 700;
    padding: 2px 7px; border-radius: 999px;
    margin-left: 6px;
}

.qe-tl-actions { display: flex; gap: 6px; }
.qe-tl-btn {
    padding: 6px 10px; border-radius: 8px;
    font-size: 11px; font-weight: 600;
    border: 1px solid var(--border); background: var(--bg-elev);
    color: var(--text-soft); cursor: pointer;
    transition: all .15s;
}
.qe-tl-btn.edit-btn:hover { background: #6366f1; color: white; border-color: #6366f1; }
.qe-tl-btn.del-btn { color: var(--red); }
.qe-tl-btn.del-btn:hover { background: var(--red); color: white; border-color: var(--red); }

.qe-tl-empty {
    padding: 30px 20px; text-align: center;
    color: var(--text-soft); font-size: 13px;
    background: var(--bg); border: 1px dashed var(--border);
    border-radius: 12px;
}

.qe-no-vid {
    background: var(--bg-elev); border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 80px 30px; text-align: center;
    box-shadow: var(--shadow-sm);
}
.qe-no-vid-icon {
    width: 80px; height: 80px; margin: 0 auto 16px;
    border-radius: 20px; background: var(--grad-admin);
    display: grid; place-items: center; color: white; font-size: 32px;
    box-shadow: 0 12px 28px rgba(99, 102, 241, 0.35);
}
.qe-no-vid p { color: var(--text-soft); font-size: 15px; }

/* ── RIGHT: form side ── */
.qe-form-side {
    background: var(--bg-elev); border: 1px solid var(--border);
    border-radius: var(--radius); padding: 22px;
    box-shadow: var(--shadow-sm);
    max-height: calc(100vh - 180px); overflow-y: auto;
    min-width: 0;
}
.qe-form-inner { min-width: 0; }

/* Tabs */
.qe-tabs {
    display: flex; gap: 4px; padding: 4px;
    background: var(--bg); border-radius: 12px;
    margin-bottom: 18px;
}
.qe-tab {
    flex: 1; padding: 9px 14px; border-radius: 9px;
    background: transparent; border: none; cursor: pointer;
    font-family: inherit; font-size: 13px; font-weight: 600;
    color: var(--text-soft); transition: all .2s;
}
.qe-tab:hover { color: var(--text); }
.qe-tab.active {
    background: var(--bg-elev); color: var(--text);
    box-shadow: var(--shadow-sm);
}
.qe-panel { display: none; }
.qe-panel.active { display: block; }

.qe-form-head { font-family: 'Sora', sans-serif; font-weight: 700; font-size: 18px; margin-bottom: 4px; }
.qe-form-sub { font-size: 12px; color: var(--text-soft); margin-bottom: 18px; line-height: 1.6; }

/* Captured timestamp box */
.qe-captured {
    background: var(--training-soft);
    border: 1.5px dashed #6366f1;
    border-radius: 12px;
    padding: 18px 20px;
    margin-bottom: 16px;
    display: flex; align-items: center; justify-content: center;
    transition: background .3s;
}
.qe-captured.empty { background: var(--bg); border-color: var(--border); border-style: dashed; }
.qe-captured .ts-val {
    font-family: 'Sora', sans-serif; font-weight: 800; font-size: 30px;
    background: var(--grad-text);
    -webkit-background-clip: text; background-clip: text; color: transparent;
    line-height: 1; font-variant-numeric: tabular-nums; text-align: center;
}
.qe-captured.empty .ts-val { color: var(--muted); background: none; -webkit-background-clip: unset; }
.qe-captured .ts-hint { font-size: 11px; color: var(--text-soft); margin-top: 5px; text-align: center; }

/* Fields */
.qe-field { margin-bottom: 14px; }
.qe-field label {
    display: block; font-size: 11px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .06em;
    color: var(--text-soft); margin-bottom: 7px;
}
.qe-field input[type="text"], .qe-field input[type="number"], .qe-field textarea {
    width: 100%; padding: 11px 13px;
    background: var(--bg); border: 1.5px solid var(--border);
    border-radius: 10px; font-family: inherit; font-size: 13px;
    color: var(--text); outline: none;
    transition: border-color .2s, box-shadow .2s;
}
.qe-field input:focus, .qe-field textarea:focus { border-color: #6366f1; box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.12); }
.qe-field textarea { resize: vertical; min-height: 60px; }

/* Question option grid */
.qe-options-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 14px; }
@media (max-width: 600px) { .qe-options-grid { grid-template-columns: 1fr; } }
.qe-opt-box {
    background: var(--bg); border: 1.5px solid var(--border);
    border-radius: 12px; padding: 10px 12px;
    transition: all .2s;
}
.qe-opt-box.selected {
    border-color: var(--green); background: rgba(16, 185, 129, 0.06);
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
}
.qe-opt-label {
    display: flex; align-items: center; gap: 8px;
    cursor: pointer; margin-bottom: 7px;
    font-size: 11px; font-weight: 600; color: var(--text-soft);
}
.qe-opt-letter {
    width: 22px; height: 22px; border-radius: 6px;
    background: var(--bg-elev); border: 1px solid var(--border);
    color: var(--text-soft);
    display: grid; place-items: center;
    font-family: 'Sora', sans-serif; font-weight: 700; font-size: 11px;
    flex-shrink: 0;
}
.qe-opt-box.selected .qe-opt-letter { background: var(--green); color: white; border-color: var(--green); }
.qe-opt-radio { accent-color: var(--green); cursor: pointer; }
.qe-opt-input {
    width: 100%; padding: 8px 11px;
    background: var(--bg-elev); border: 1.5px solid var(--border);
    border-radius: 8px; font-family: inherit; font-size: 13px;
    color: var(--text); outline: none;
    transition: border-color .2s;
}
.qe-opt-input:focus { border-color: #6366f1; }

/* CP block (multi-question) */
.qe-cp-block {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 14px;
    margin-bottom: 12px;
}
.qe-cp-label {
    font-family: 'Sora', sans-serif; font-weight: 700; font-size: 13px;
    color: var(--text); margin-bottom: 12px;
    display: flex; align-items: center; justify-content: space-between; gap: 8px;
}
.qe-cp-label::before { content: ''; display: inline-block; width: 4px; height: 14px; background: var(--grad-admin); border-radius: 2px; margin-right: 8px; vertical-align: middle; }
.qe-rm-btn {
    background: transparent; border: 1px solid var(--border);
    color: var(--text-soft);
    width: 26px; height: 26px; border-radius: 7px;
    cursor: pointer; font-size: 13px;
    transition: all .15s;
}
.qe-rm-btn:hover { background: var(--red); color: white; border-color: var(--red); }

.qe-add-more {
    width: 100%; padding: 10px;
    background: transparent;
    border: 1.5px dashed var(--border);
    border-radius: 10px;
    color: var(--text-soft); font-weight: 600; font-size: 13px;
    cursor: pointer;
    transition: all .15s;
    margin-bottom: 14px;
    display: inline-flex; align-items: center; justify-content: center; gap: 6px;
}
.qe-add-more:hover { border-color: #6366f1; color: #6366f1; background: var(--training-soft); }

/* Buttons */
.qe-btn-primary {
    width: 100%; padding: 13px;
    background: var(--grad-admin); color: white;
    font-family: inherit; font-size: 14px; font-weight: 600;
    border: none; border-radius: 10px; cursor: pointer;
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    box-shadow: 0 6px 18px rgba(99, 102, 241, 0.35);
    transition: transform .2s, box-shadow .2s;
    margin-top: 4px;
}
.qe-btn-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 24px rgba(99, 102, 241, 0.45); }
.qe-btn-primary:disabled { opacity: .6; cursor: not-allowed; transform: none; }
.qe-btn-secondary {
    padding: 11px 16px;
    background: var(--bg); color: var(--text);
    border: 1.5px solid var(--border);
    border-radius: 10px;
    font-family: inherit; font-size: 13px; font-weight: 600;
    cursor: pointer; transition: all .15s;
    display: inline-flex; align-items: center; justify-content: center; gap: 6px;
}
.qe-btn-secondary:hover { border-color: #6366f1; color: #6366f1; }
.qe-btn-danger {
    padding: 9px 14px;
    background: rgba(239, 68, 68, 0.1); color: var(--red);
    border: 1px solid rgba(239, 68, 68, 0.25);
    border-radius: 9px; font-family: inherit; font-size: 12px; font-weight: 600;
    cursor: pointer; transition: all .15s;
}
.qe-btn-danger:hover { background: var(--red); color: white; }

/* Edit panel */
.qe-ge-bar {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 14px;
    margin-bottom: 18px;
}
.qe-ge-row { display: grid; grid-template-columns: 2fr 1fr; gap: 10px; margin-bottom: 12px; }
@media (max-width: 500px) { .qe-ge-row { grid-template-columns: 1fr; } }

/* Q-blocks in edit view */
.qe-q-block {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 12px;
    margin-bottom: 10px;
    overflow: hidden;
}
.qe-q-header {
    padding: 12px 14px;
    display: flex; align-items: center; gap: 11px;
    cursor: pointer; transition: background .15s;
}
.qe-q-header:hover { background: var(--bg-elev); }
.qe-q-num {
    width: 26px; height: 26px; border-radius: 7px;
    background: var(--grad-admin); color: white;
    display: grid; place-items: center;
    font-family: 'Sora', sans-serif; font-weight: 700; font-size: 12px;
    flex-shrink: 0;
}
.qe-q-preview {
    flex: 1; min-width: 0; font-size: 13px; font-weight: 600; color: var(--text);
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.qe-q-badge {
    font-size: 11px; font-weight: 700; padding: 3px 10px;
    background: rgba(16, 185, 129, 0.1); color: var(--green);
    border-radius: 999px; font-family: 'Sora', sans-serif;
}
.qe-q-body { display: none; padding: 14px; border-top: 1px solid var(--border); background: var(--bg-elev); }
.qe-q-body.open { display: block; }
.qe-q-actions { display: flex; gap: 8px; margin-top: 12px; }

.qe-add-q-toggle {
    width: 100%; padding: 11px;
    background: var(--bg);
    border: 1.5px dashed var(--border);
    border-radius: 10px;
    color: var(--text-soft); font-weight: 600; font-size: 13px;
    cursor: pointer; transition: all .15s;
    margin: 14px 0;
    display: inline-flex; align-items: center; justify-content: center; gap: 6px;
}
.qe-add-q-toggle:hover { border-color: #6366f1; color: #6366f1; }
.qe-add-q-form {
    display: none;
    background: var(--bg); border: 1px solid var(--border);
    border-radius: 12px; padding: 16px;
    margin-top: 8px;
}
.qe-add-q-form.open { display: block; }

/* Toast */
.qe-toast {
    position: fixed; bottom: 28px; left: 50%; transform: translateX(-50%) translateY(20px);
    background: var(--text); color: var(--bg);
    padding: 12px 22px; border-radius: 12px;
    font-size: 13px; font-weight: 500;
    box-shadow: var(--shadow-lg);
    z-index: 9999; opacity: 0;
    transition: opacity .25s, transform .25s;
    max-width: 90%;
}
.qe-toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
.qe-toast.err { background: var(--red); color: white; }
.qe-toast.warn { background: var(--gold); color: #422006; }

/* Offline banner */
#qeOfflineBanner {
    position: fixed; top: 76px; left: 50%; transform: translateX(-50%);
    background: var(--gold); color: #422006;
    padding: 10px 18px; border-radius: 10px;
    font-size: 13px; font-weight: 600;
    box-shadow: var(--shadow-lg);
    z-index: 100;
    display: flex; align-items: center; gap: 10px;
}
#qeOfflineBanner button {
    padding: 5px 12px; background: #422006; color: white;
    border: none; border-radius: 7px;
    font-family: inherit; font-size: 11px; font-weight: 600;
    cursor: pointer;
}

/* Scrollbar */
.qe-form-side::-webkit-scrollbar,
.qe-timeline-wrap::-webkit-scrollbar { width: 6px; }
.qe-form-side::-webkit-scrollbar-thumb,
.qe-timeline-wrap::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }
</style>

<div class="qe-page">

  <div class="qe-page-head">
    <h1>Quiz <span class="gradient-text">Editor</span></h1>
    <p>Watch the video, capture the timestamp at any concept, and attach a checkpoint quiz right there. Works offline too — quizzes you create without internet will sync automatically when you reconnect.</p>
  </div>

  <!-- Sub-topbar: video selector -->
  <div class="qe-subbar">
    <span class="qe-ts-label"><i class="fas fa-circle-info"></i> Select video to attach quizzes</span>
    <label class="qe-vid-label">Video:</label>
    <select class="qe-vid-pick" onchange="location='quiz_editor.php?vid='+this.value">
      <option value="">— Select a video —</option>
      <?php foreach ($videos as $v): ?>
        <option value="<?php echo $v['id']; ?>" <?php if ($v['id'] == $sel_vid) echo 'selected'; ?>>
          <?php echo htmlspecialchars($v['title']); ?>
        </option>
      <?php endforeach; ?>
    </select>
    <?php if ($sel_vid): ?>
      <button type="button" class="qe-ai-btn" id="aiGenBtn" title="Auto-generate quiz checkpoints from the video transcript">
        <span class="ai-spark"><i class="fas fa-wand-magic-sparkles"></i></span>
        Generate with AI
      </button>
    <?php endif; ?>
  </div>

  <!-- Main editor -->
  <div class="qe-layout">

    <!-- ── LEFT: VIDEO + TIMELINE ── -->
    <div class="qe-video-side">
      <?php if ($sel_vid && $video_file): ?>

      <div class="qe-video-wrap">
        <video id="qe-vid" controls controlsList="nodownload">
          <source src="upload/<?php echo $video_file; ?>">
        </video>
      </div>

      <div class="qe-ts-bar">
        <div>
          <div class="qe-ts-display" id="qeTsDisplay">0:00</div>
          <div class="qe-ts-label">Current video time</div>
        </div>
        <button class="qe-btn-capture" onclick="qeCaptureTime()">
          <span class="qe-capture-dot"></span> Capture Timestamp
        </button>
      </div>

      <div class="qe-timeline-wrap">
        <div class="qe-tl-head">
          Quiz Checkpoints
          <span class="qe-tl-count" id="qeTlCount"><?php echo count($existing); ?></span>
        </div>
        <div id="qeTlList">
          <?php if (empty($existing)): ?>
            <div class="qe-tl-empty" id="qeTlEmpty">No quizzes yet — add one using the form on the right.</div>
          <?php else: foreach ($existing as $eq):
            $t = $eq['trigger_time'];
            $mm = floor($t / 60);
            $ss = str_pad(floor($t % 60), 2, '0', STR_PAD_LEFT); ?>
            <div class="qe-tl-item" id="qetl-<?php echo $eq['id']; ?>" data-sec="<?php echo $t; ?>">
              <div class="qe-tl-row">
                <div class="qe-tl-badge" onclick="qeSeekTo(<?php echo $t; ?>)"><?php echo "$mm:$ss"; ?></div>
                <div class="qe-tl-info" onclick="qeSeekTo(<?php echo $t; ?>)">
                  <div class="qe-tl-topic"><?php echo htmlspecialchars($eq['group_label'] ?: 'Quiz'); ?></div>
                  <div class="qe-tl-meta"><?php echo $eq['q_count']; ?> question<?php echo $eq['q_count'] != 1 ? 's' : ''; ?></div>
                </div>
                <div class="qe-tl-actions">
                  <button class="qe-tl-btn edit-btn" onclick="qeOpenEdit(<?php echo $eq['id']; ?>)"><i class="fas fa-pen"></i> Edit</button>
                  <button class="qe-tl-btn del-btn"  onclick="qeDelGroup(<?php echo $eq['id']; ?>)"><i class="fas fa-xmark"></i></button>
                </div>
              </div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

      <?php else: ?>
        <div class="qe-no-vid">
          <div class="qe-no-vid-icon"><i class="fas fa-video"></i></div>
          <p>Select a video above to start adding quizzes.</p>
        </div>
      <?php endif; ?>
    </div>

    <!-- ── RIGHT: FORM SIDE ── -->
    <div class="qe-form-side">
      <div class="qe-form-inner">
        <?php if ($sel_vid): ?>

        <div class="qe-tabs">
          <button class="qe-tab active" onclick="qeSwitchTab('add')"  id="qe-tab-add"><i class="fas fa-plus"></i> Add New Quiz</button>
          <button class="qe-tab"         onclick="qeSwitchTab('edit')" id="qe-tab-edit"><i class="fas fa-pen"></i> Edit Quiz</button>
        </div>

        <!-- ADD PANEL -->
        <div class="qe-panel active" id="qe-panel-add">
          <div class="qe-form-head">Create a Quiz Checkpoint</div>
          <div class="qe-form-sub">Play the video → pause at a concept → <strong>Capture Timestamp</strong> → fill in questions → Save.</div>

          <div class="qe-captured empty" id="qeCapturedBox">
            <div>
              <div class="ts-val" id="qeCapturedVal">—</div>
              <div class="ts-hint">No timestamp captured yet</div>
            </div>
          </div>
          <input type="hidden" id="qeCapturedSeconds" value="">

          <div class="qe-field">
            <label>Topic</label>
            <input type="text" id="qeFlabel" placeholder="e.g. CMMI Level 1 — Quality Concept">
          </div>

          <div id="qeAllQuestions">
            <div class="qe-cp-block" id="qe-qblock-1">
              <div class="qe-cp-label">Question 1</div>
              <?php echo questionFields('qq1'); ?>
            </div>
          </div>

          <button class="qe-add-more" onclick="qeAddQuestion()">
            <i class="fas fa-plus"></i> Add Another Question
          </button>

          <button class="qe-btn-primary" id="qeBtnSave" onclick="qeSaveNewQuiz()">
            <i class="fas fa-bookmark"></i> Save Quiz Checkpoint
          </button>
        </div>

        <!-- EDIT PANEL -->
        <div class="qe-panel" id="qe-panel-edit">
          <div id="qeEditNoSel" style="text-align:center; padding: 40px 20px; color: var(--text-soft); font-size: 13px; line-height: 1.7;">
            <div style="font-size: 36px; color: var(--muted); margin-bottom: 14px;"><i class="fas fa-hand-pointer"></i></div>
            Click <strong style="color: var(--text);"><i class="fas fa-pen"></i> Edit</strong> on any checkpoint<br>in the left timeline to edit it here.
          </div>
          <div id="qeEditContent" style="display:none;">
            <div class="qe-form-head" id="qeEditGroupTitle">Edit Checkpoint</div>
            <div class="qe-form-sub">Edit the label, timestamp, or individual questions. Changes save instantly.</div>

            <div class="qe-ge-bar">
              <div class="qe-ge-row">
                <div class="qe-field">
                  <label>Checkpoint Label</label>
                  <input type="text" id="qeEgLabel" placeholder="Topic label">
                </div>
                <div class="qe-field">
                  <label>Timestamp (sec)</label>
                  <input type="number" id="qeEgTrigger" placeholder="e.g. 150" step="0.1" min="0">
                </div>
              </div>
              <button class="qe-btn-secondary" onclick="qeSaveGroupEdit()" style="width:100%;">
                <i class="fas fa-floppy-disk"></i> Update Label &amp; Timestamp
              </button>
            </div>

            <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--text-soft); margin-bottom: 10px;">Questions</div>
            <div id="qeEditQList"></div>

            <button class="qe-add-q-toggle" id="qeAddQToggle" onclick="qeToggleAddQ()">
              <i class="fas fa-plus"></i> Add Question to this Checkpoint
            </button>
            <div class="qe-add-q-form" id="qeAddQForm">
              <?php echo questionFields('eq_new'); ?>
              <div style="display: flex; gap: 8px; margin-top: 11px;">
                <button class="qe-btn-primary" onclick="qeSaveExtraQuestion()" style="flex: 1; margin-top: 0;">
                  <i class="fas fa-floppy-disk"></i> Save Question
                </button>
                <button class="qe-btn-secondary" onclick="qeToggleAddQ()">Cancel</button>
              </div>
            </div>
          </div>
        </div>

        <?php else: ?>
        <div style="color: var(--text-soft); font-size: 14px; padding: 60px 20px; text-align: center; line-height: 1.7;">
          <div style="font-size: 36px; color: var(--muted); margin-bottom: 14px;"><i class="fas fa-arrow-up"></i></div>
          Select a video above to get started.
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="qe-toast" id="qeToast"></div>

<script>
const QE_VID_ID = <?php echo $sel_vid ?: 0; ?>;
const qeVideo   = document.getElementById('qe-vid');
let qeQCount    = 1;
let qeEditingId = null;

/* Theme */
const themeBtn = document.getElementById('theme-toggle');
const themeIcon = themeBtn.querySelector('i');
if (localStorage.getItem('mn-theme') === 'dark') {
  document.documentElement.classList.add('dark');
  themeIcon.classList.replace('fa-moon', 'fa-sun');
}
themeBtn.addEventListener('click', () => {
  const dark = document.documentElement.classList.toggle('dark');
  themeIcon.classList.toggle('fa-moon', !dark);
  themeIcon.classList.toggle('fa-sun', dark);
  localStorage.setItem('mn-theme', dark ? 'dark' : 'light');
});

/* ── TIMESTAMP ─────────────────────────────────────────────── */
if (qeVideo) {
    qeVideo.addEventListener('timeupdate', () => {
        const t=qeVideo.currentTime, m=Math.floor(t/60), s=Math.floor(t%60).toString().padStart(2,'0');
        document.getElementById('qeTsDisplay').textContent=`${m}:${s}`;
    });
}
function qeCaptureTime() {
    if (!qeVideo) return;
    qeVideo.pause();
    const t=qeVideo.currentTime, m=Math.floor(t/60), s=Math.floor(t%60).toString().padStart(2,'0');
    document.getElementById('qeCapturedVal').textContent=`${m}:${s}`;
    document.getElementById('qeCapturedSeconds').value=t.toFixed(2);
    const box=document.getElementById('qeCapturedBox');
    box.classList.remove('empty');
    box.style.background='rgba(99,102,241,0.18)';
    setTimeout(()=>box.style.background='',400);
    qeShowToast(`Timestamp ${m}:${s} captured!`);
}
function qeSeekTo(sec) { if(qeVideo){qeVideo.currentTime=parseFloat(sec);qeVideo.pause();} }

/* ── TABS ──────────────────────────────────────────────────── */
function qeSwitchTab(tab) {
    document.querySelectorAll('.qe-tab').forEach(t=>t.classList.remove('active'));
    document.querySelectorAll('.qe-panel').forEach(p=>p.classList.remove('active'));
    document.getElementById('qe-tab-'+tab).classList.add('active');
    document.getElementById('qe-panel-'+tab).classList.add('active');
}

/* ── MARK CORRECT ──────────────────────────────────────────── */
function markCorrect(prefix, key) {
    ['a','b','c','d'].forEach(k=>document.getElementById(`${prefix}_optbox_${k}`)?.classList.remove('selected'));
    document.getElementById(`${prefix}_optbox_${key}`)?.classList.add('selected');
}
function qeMarkCorrectEdit(qoId, key) {
    ['a','b','c','d'].forEach(k=>document.getElementById(`eq_optbox_${qoId}_${k}`)?.classList.remove('selected'));
    document.getElementById(`eq_optbox_${qoId}_${key}`)?.classList.add('selected');
}

/* ── BUILD Q HTML ──────────────────────────────────────────── */
function qeBuildQFields(prefix) {
    const L=['A','B','C','D'], K=['a','b','c','d'];
    return `
    <div class="qe-field"><label>Question</label>
        <input type="text" id="${prefix}_question" placeholder="Type your question here..."></div>
    <div class="qe-options-grid">${K.map((k,i)=>`
        <div class="qe-opt-box" id="${prefix}_optbox_${k}">
            <label class="qe-opt-label" for="${prefix}_radio_${k}">
                <div class="qe-opt-letter">${L[i]}</div>
                <input class="qe-opt-radio" type="radio" name="${prefix}_correct" id="${prefix}_radio_${k}" value="${i}"
                    onchange="markCorrect('${prefix}','${k}')">
                <span>Correct answer</span>
            </label>
            <input class="qe-opt-input" type="text" id="${prefix}_opt_${k}" placeholder="Option ${L[i]}">
        </div>`).join('')}</div>
    <div class="qe-field"><label>Explanation <span style="font-weight:400;text-transform:none;color:var(--muted)">(optional)</span></label>
        <textarea id="${prefix}_explanation" placeholder="Why is this the correct answer?"></textarea></div>`;
}

function qeAddQuestion() {
    qeQCount++;
    const prefix=`qq${qeQCount}`;
    const wrap=document.createElement('div');
    wrap.className='qe-cp-block'; wrap.id=`qe-qblock-${qeQCount}`;
    wrap.innerHTML=`<div class="qe-cp-label"><span>Question ${qeQCount}</span>
        <button class="qe-rm-btn" onclick="qeRemoveQuestion(${qeQCount})" title="Remove">✕</button></div>
        ${qeBuildQFields(prefix)}`;
    document.getElementById('qeAllQuestions').appendChild(wrap);
}
function qeRemoveQuestion(num) {
    document.getElementById(`qe-qblock-${num}`)?.remove();
    let idx=1;
    document.querySelectorAll('#qeAllQuestions .qe-cp-block').forEach(b=>{
        const span = b.querySelector('.qe-cp-label span');
        if (span) span.textContent = `Question ${idx++}`;
        else b.querySelector('.qe-cp-label').firstChild.textContent = `Question ${idx++}`;
    });
}

function qeCollectQ(prefix) {
    return {
        question: document.getElementById(`${prefix}_question`)?.value.trim()||'',
        opt_a: document.getElementById(`${prefix}_opt_a`)?.value.trim()||'',
        opt_b: document.getElementById(`${prefix}_opt_b`)?.value.trim()||'',
        opt_c: document.getElementById(`${prefix}_opt_c`)?.value.trim()||'',
        opt_d: document.getElementById(`${prefix}_opt_d`)?.value.trim()||'',
        correct: document.querySelector(`input[name="${prefix}_correct"]:checked`),
        expl: document.getElementById(`${prefix}_explanation`)?.value.trim()||'',
    };
}

function qeAppendQ(fd, q) {
    fd.append('question_text',q.question);
    fd.append('option_a',q.opt_a); fd.append('option_b',q.opt_b);
    fd.append('option_c',q.opt_c); fd.append('option_d',q.opt_d);
    fd.append('correct_option',q.correct.value);
    fd.append('explanation',q.expl);
}

/* ── OFFLINE QUEUE HELPERS ─────────────────────────────────── */
function qeOfflineQueue() {
    try { return JSON.parse(localStorage.getItem('qe_offline_queue')||'[]'); }
    catch(e) { return []; }
}
function qeSaveQueue(q) {
    localStorage.setItem('qe_offline_queue', JSON.stringify(q));
}

/* ── OFFLINE BANNER ────────────────────────────────────────── */
function qeUpdateOfflineBanner() {
    const count = qeOfflineQueue().filter(e => e.video_id == QE_VID_ID).length;
    let banner  = document.getElementById('qeOfflineBanner');
    if (!count) { banner?.remove(); return; }
    if (!banner) {
        banner = document.createElement('div');
        banner.id = 'qeOfflineBanner';
        document.body.appendChild(banner);
    }
    banner.innerHTML = '<i class="fas fa-triangle-exclamation"></i> ' + count + ' quiz(zes) saved offline — not yet synced.' +
        (navigator.onLine
            ? ' <button onclick="qeSyncOffline()">Sync Now</button>'
            : ' (waiting for internet…)');
}

/* ── SYNC OFFLINE QUEUE TO SERVER ──────────────────────────── */
async function qeSyncOffline() {
    const queue = qeOfflineQueue().filter(e => e.video_id == QE_VID_ID);
    if (!queue.length) return;
    qeShowToast('Syncing ' + queue.length + ' offline quiz(zes)…', false, true);
    for (const entry of queue) {
        try {
            const fd = new FormData();
            const q0 = entry.questions[0];
            fd.append('ajax_save','1');
            fd.append('video_id', entry.video_id);
            fd.append('trigger_time', entry.trigger_time);
            fd.append('group_label', entry.group_label);
            fd.append('question_text', q0.question_text);
            fd.append('option_a', q0.option_a);
            fd.append('option_b', q0.option_b);
            fd.append('option_c', q0.option_c);
            fd.append('option_d', q0.option_d);
            fd.append('correct_option', q0.correct_option);
            fd.append('explanation', q0.explanation);
            const res  = await fetch('quiz_editor.php?vid='+QE_VID_ID, {method:'POST',body:fd});
            const data = await res.json();
            if (!data.ok) continue;

            for (let i = 1; i < entry.questions.length; i++) {
                const fd2 = new FormData();
                const q   = entry.questions[i];
                fd2.append('ajax_add_question','1');
                fd2.append('quiz_id', data.quiz_id);
                fd2.append('question_text', q.question_text);
                fd2.append('option_a', q.option_a);
                fd2.append('option_b', q.option_b);
                fd2.append('option_c', q.option_c);
                fd2.append('option_d', q.option_d);
                fd2.append('correct_option', q.correct_option);
                fd2.append('explanation', q.explanation);
                await fetch('quiz_editor.php?vid='+QE_VID_ID, {method:'POST',body:fd2});
            }

            const oldEl = document.getElementById('qetl-' + entry.tempId);
            if (oldEl) {
                oldEl.id = 'qetl-' + data.quiz_id;
                oldEl.classList.remove('offline-item');
                const editBtn = oldEl.querySelector('.qe-tl-btn.edit-btn');
                if (editBtn) editBtn.setAttribute('onclick', 'qeOpenEdit(' + data.quiz_id + ')');
                const delBtn  = oldEl.querySelector('.qe-tl-btn.del-btn');
                if (delBtn)  delBtn.setAttribute('onclick',  'qeDelGroup(' + data.quiz_id + ')');
                oldEl.querySelector('.qe-offline-badge')?.remove();
            }

            qeSaveQueue(qeOfflineQueue().filter(e => e.tempId !== entry.tempId));
        } catch(e) { /* leave in queue */ }
    }
    const remaining = qeOfflineQueue().filter(e => e.video_id == QE_VID_ID).length;
    if (!remaining) qeShowToast('All quizzes synced to server!');
    qeUpdateOfflineBanner();
}

window.addEventListener('online',  () => { qeUpdateOfflineBanner(); qeSyncOffline(); });
window.addEventListener('offline', () => { qeUpdateOfflineBanner(); });

/* ── SAVE NEW QUIZ ─────────────────────────────────────────── */
async function qeSaveNewQuiz() {
    const ts    = document.getElementById('qeCapturedSeconds').value;
    const label = document.getElementById('qeFlabel').value.trim();
    if (!ts)    { qeShowToast('Capture a timestamp first!',true); return; }
    if (!label) { qeShowToast('Enter a checkpoint label!',true);  return; }

    const blocks    = [...document.querySelectorAll('#qeAllQuestions .qe-cp-block')];
    const questions = [];
    for (let i = 0; i < blocks.length; i++) {
        const num = blocks[i].id.replace('qe-qblock-','');
        const d   = qeCollectQ('qq'+num);
        if (!d.question)        { qeShowToast('Q'+(i+1)+': enter question text!',true); return; }
        if (!d.opt_a||!d.opt_b) { qeShowToast('Q'+(i+1)+': options A & B required!',true); return; }
        if (!d.correct)         { qeShowToast('Q'+(i+1)+': select the correct answer!',true); return; }
        questions.push(d);
    }
    if (!questions.length) { qeShowToast('Add at least one question!',true); return; }

    if (!navigator.onLine) {
        const tempId = 'offline_' + Date.now();
        const entry  = {
            tempId, video_id: QE_VID_ID,
            trigger_time: parseFloat(ts), group_label: label,
            questions: questions.map(q => ({
                question_text:  q.question,
                option_a: q.opt_a, option_b: q.opt_b,
                option_c: q.opt_c, option_d: q.opt_d,
                correct_option: parseInt(q.correct.value),
                explanation: q.expl
            }))
        };
        const queue = qeOfflineQueue();
        queue.push(entry);
        qeSaveQueue(queue);
        qeAddToTimeline(tempId, parseFloat(ts), label, questions.length, true);
        qeResetAddPanel();
        qeUpdateOfflineBanner();
        qeShowToast('Saved offline — will sync when internet returns.', false, true);
        return;
    }

    const btn = document.getElementById('qeBtnSave');
    btn.disabled = true; btn.textContent = 'Saving...';
    try {
        const fd = new FormData();
        fd.append('ajax_save','1');
        fd.append('video_id', QE_VID_ID);
        fd.append('trigger_time', ts);
        fd.append('group_label', label);
        qeAppendQ(fd, questions[0]);
        const res  = await fetch('quiz_editor.php?vid='+QE_VID_ID,{method:'POST',body:fd});
        const data = await res.json();
        if (!data.ok) { qeShowToast('Error: '+data.error,true); return; }
        for (let i=1;i<questions.length;i++) {
            const fd2=new FormData();
            fd2.append('ajax_add_question','1');
            fd2.append('quiz_id',data.quiz_id);
            qeAppendQ(fd2,questions[i]);
            await fetch('quiz_editor.php?vid='+QE_VID_ID,{method:'POST',body:fd2});
        }
        qeShowToast(`Checkpoint saved! (${questions.length} question${questions.length>1?'s':''})`);
        qeAddToTimeline(data.quiz_id, parseFloat(ts), label, questions.length);
        qeResetAddPanel();
    } catch(e) { qeShowToast('Network error!',true); }
    finally { btn.disabled=false; btn.innerHTML='<i class="fas fa-bookmark"></i> Save Quiz Checkpoint'; }
}

/* ── ADD TO TIMELINE ───────────────────────────────────────── */
function qeAddToTimeline(qid, sec, label, count, isOffline=false) {
    document.getElementById('qeTlEmpty')?.remove();
    const m = Math.floor(sec/60);
    const s = Math.floor(sec%60).toString().padStart(2,'0');
    const div = document.createElement('div');
    div.className   = 'qe-tl-item' + (isOffline ? ' offline-item' : '');
    div.id          = 'qetl-' + qid;
    div.dataset.sec = sec;

    const offlineBadge = isOffline
        ? '<span class="qe-offline-badge"><i class="fas fa-triangle-exclamation"></i> Pending sync</span>'
        : '';

    const editOnclick = isOffline
        ? `qeShowToast('Connect to internet to edit offline quizzes',true)`
        : `qeOpenEdit(${qid})`;

    div.innerHTML = `<div class="qe-tl-row">
        <div class="qe-tl-badge" onclick="qeSeekTo(${sec})">${m}:${s}</div>
        <div class="qe-tl-info" onclick="qeSeekTo(${sec})">
            <div class="qe-tl-topic">${qeEsc(label)}</div>
            <div class="qe-tl-meta">${count} question${count!==1?'s':''}${offlineBadge}</div>
        </div>
        <div class="qe-tl-actions">
            <button class="qe-tl-btn edit-btn" onclick="${editOnclick}"><i class="fas fa-pen"></i> Edit</button>
            <button class="qe-tl-btn del-btn"  onclick="qeDelGroup('${qid}')"><i class="fas fa-xmark"></i></button>
        </div></div>`;

    const list  = document.getElementById('qeTlList');
    const items = [...list.querySelectorAll('.qe-tl-item')];
    let inserted = false;
    for (const item of items) {
        if (sec < parseFloat(item.dataset.sec||0)) { list.insertBefore(div,item); inserted=true; break; }
    }
    if (!inserted) list.appendChild(div);
    const el = document.getElementById('qeTlCount');
    if (el) el.textContent = parseInt(el.textContent||0)+1;
}

function qeSyncTimelineMeta(qid, label, trigger, count) {
    const item=document.getElementById('qetl-'+qid); if(!item) return;
    const m=Math.floor(trigger/60), s=Math.floor(trigger%60).toString().padStart(2,'0');
    item.querySelector('.qe-tl-topic').textContent=label;
    item.querySelector('.qe-tl-meta').textContent=`${count} question${count!==1?'s':''}`;
    item.querySelector('.qe-tl-badge').textContent=`${m}:${s}`;
    item.dataset.sec=trigger;
}

function qeDelGroup(qid) {
    const qidStr = String(qid);

    if (qidStr.startsWith('offline_')) {
        if (!confirm('Delete this offline checkpoint?')) return;
        qeSaveQueue(qeOfflineQueue().filter(e => e.tempId !== qidStr));
        document.getElementById('qetl-'+qidStr)?.remove();
        qeUpdateOfflineBanner();
        const el=document.getElementById('qeTlCount');
        if(el) el.textContent=Math.max(0,parseInt(el.textContent||0)-1);
        if(!document.querySelector('.qe-tl-item'))
            document.getElementById('qeTlList').innerHTML='<div class="qe-tl-empty" id="qeTlEmpty">No quizzes yet.</div>';
        qeShowToast('Offline checkpoint deleted.');
        return;
    }

    if (!confirm('Delete this entire checkpoint and all its questions?')) return;
    const fd=new FormData(); fd.append('delete_quiz_id', qid);
    fetch('quiz_editor.php?vid='+QE_VID_ID,{method:'POST',body:fd})
        .then(r=>r.json()).then(d=>{
            if(d.ok){
                document.getElementById('qetl-'+qid)?.remove();
                const el=document.getElementById('qeTlCount');
                if(el) el.textContent=Math.max(0,parseInt(el.textContent||0)-1);
                if(!document.querySelector('.qe-tl-item'))
                    document.getElementById('qeTlList').innerHTML='<div class="qe-tl-empty" id="qeTlEmpty">No quizzes yet.</div>';
                if(qeEditingId===qid){
                    document.getElementById('qeEditContent').style.display='none';
                    document.getElementById('qeEditNoSel').style.display='';
                    qeEditingId=null;
                }
                qeShowToast('Checkpoint deleted.');
            }
        });
}

async function qeOpenEdit(qid) {
    qeSwitchTab('edit');
    qeEditingId=qid;
    document.querySelectorAll('.qe-tl-item').forEach(i=>i.classList.remove('active-edit'));
    document.getElementById('qetl-'+qid)?.classList.add('active-edit');
    document.getElementById('qeEditNoSel').style.display='none';
    document.getElementById('qeEditContent').style.display='block';
    document.getElementById('qeEditGroupTitle').textContent='Loading…';
    const res  = await fetch(`quiz_editor.php?vid=${QE_VID_ID}&load_quiz=${qid}`);
    const data = await res.json();
    document.getElementById('qeEditGroupTitle').textContent=`Edit: ${data.group_label||'Quiz'}`;
    document.getElementById('qeEgLabel').value=data.group_label||'';
    document.getElementById('qeEgTrigger').value=parseFloat(data.trigger_time).toFixed(1);
    qeRenderEditQuestions(data.questions||[]);
    document.getElementById('qeAddQForm').classList.remove('open');
    document.getElementById('qeAddQToggle').innerHTML='<i class="fas fa-plus"></i> Add Question to this Checkpoint';
    qeClearQFields('eq_new');
}

function qeRenderEditQuestions(questions) {
    const list=document.getElementById('qeEditQList'); list.innerHTML='';
    const L=['A','B','C','D'], K=['a','b','c','d'];
    questions.forEach((q,idx)=>{
        const card=document.createElement('div');
        card.className='qe-q-block'; card.id=`qeq-${q.id}`;
        card.innerHTML=`
            <div class="qe-q-header" onclick="qeToggleQBlock(${q.id})">
                <div class="qe-q-num">${idx+1}</div>
                <div class="qe-q-preview">${qeEsc(q.question_text)}</div>
                <div class="qe-q-badge">✓ ${L[q.correct_option]||'?'}</div>
            </div>
            <div class="qe-q-body" id="qeqbody-${q.id}">
                <div class="qe-field"><label>Question</label>
                    <input type="text" id="eq_q_${q.id}" value="${qeEscAttr(q.question_text)}"></div>
                <div class="qe-options-grid">${K.map((k,i)=>`
                    <div class="qe-opt-box ${q.correct_option==i?'selected':''}" id="eq_optbox_${q.id}_${k}">
                        <label class="qe-opt-label" for="eq_radio_${q.id}_${k}">
                            <div class="qe-opt-letter">${L[i]}</div>
                            <input class="qe-opt-radio" type="radio" name="eq_correct_${q.id}"
                                id="eq_radio_${q.id}_${k}" value="${i}"
                                ${q.correct_option==i?'checked':''}
                                onchange="qeMarkCorrectEdit(${q.id},'${k}')">
                            <span>Correct answer</span>
                        </label>
                        <input class="qe-opt-input" type="text" id="eq_opt_${q.id}_${k}"
                            value="${qeEscAttr(q['option_'+k]||'')}">
                    </div>`).join('')}</div>
                <div class="qe-field"><label>Explanation</label>
                    <textarea id="eq_expl_${q.id}">${qeEsc(q.explanation||'')}</textarea></div>
                <div class="qe-q-actions">
                    <button class="qe-btn-secondary" onclick="qeSaveQEdit(${q.id})" style="flex:1"><i class="fas fa-floppy-disk"></i> Save Changes</button>
                    <button class="qe-btn-danger"    onclick="qeDeleteQ(${q.id})"><i class="fas fa-trash"></i> Delete</button>
                </div>
            </div>`;
        list.appendChild(card);
    });
}

function qeToggleQBlock(id) { document.getElementById('qeqbody-'+id)?.classList.toggle('open'); }

async function qeSaveGroupEdit() {
    if (!qeEditingId) return;
    const label   = document.getElementById('qeEgLabel').value.trim();
    const trigger = parseFloat(document.getElementById('qeEgTrigger').value);
    if (!label)              { qeShowToast('Label cannot be empty!',true); return; }
    if (isNaN(trigger)||trigger<0) { qeShowToast('Enter a valid timestamp!',true); return; }
    const fd=new FormData();
    fd.append('ajax_edit_group','1'); fd.append('quiz_id',qeEditingId);
    fd.append('group_label',label);  fd.append('trigger_time',trigger);
    const r=await fetch('quiz_editor.php?vid='+QE_VID_ID,{method:'POST',body:fd});
    const d=await r.json();
    if (d.ok) {
        const count=document.querySelectorAll('#qeEditQList .qe-q-block').length;
        qeSyncTimelineMeta(qeEditingId,label,trigger,count);
        document.getElementById('qeEditGroupTitle').textContent=`Edit: ${label}`;
        qeShowToast('Checkpoint updated!');
    } else qeShowToast('Error saving!',true);
}

async function qeSaveQEdit(qoId) {
    const question=document.getElementById(`eq_q_${qoId}`)?.value.trim();
    const opt_a=document.getElementById(`eq_opt_${qoId}_a`)?.value.trim();
    const opt_b=document.getElementById(`eq_opt_${qoId}_b`)?.value.trim();
    const opt_c=document.getElementById(`eq_opt_${qoId}_c`)?.value.trim()||'';
    const opt_d=document.getElementById(`eq_opt_${qoId}_d`)?.value.trim()||'';
    const correct=document.querySelector(`input[name="eq_correct_${qoId}"]:checked`);
    const expl=document.getElementById(`eq_expl_${qoId}`)?.value.trim()||'';
    if (!question||!opt_a||!opt_b) { qeShowToast('Question and options A, B required!',true); return; }
    if (!correct) { qeShowToast('Select the correct answer!',true); return; }
    const fd=new FormData();
    fd.append('ajax_edit_question','1'); fd.append('qo_id',qoId);
    fd.append('question_text',question);
    fd.append('option_a',opt_a); fd.append('option_b',opt_b);
    fd.append('option_c',opt_c); fd.append('option_d',opt_d);
    fd.append('correct_option',correct.value); fd.append('explanation',expl);
    const r=await fetch('quiz_editor.php?vid='+QE_VID_ID,{method:'POST',body:fd});
    const d=await r.json();
    if (d.ok) {
        const card=document.getElementById(`qeq-${qoId}`);
        if (card) {
            card.querySelector('.qe-q-preview').textContent=question;
            card.querySelector('.qe-q-badge').textContent=`✓ ${['A','B','C','D'][parseInt(correct.value)]}`;
        }
        qeShowToast('Question updated!');
    } else qeShowToast('Error saving!',true);
}

async function qeDeleteQ(qoId) {
    if (document.querySelectorAll('#qeEditQList .qe-q-block').length<=1) {
        qeShowToast('Cannot delete the only question — delete the whole checkpoint instead.',true); return;
    }
    if (!confirm('Delete this question?')) return;
    const fd=new FormData(); fd.append('ajax_delete_question','1'); fd.append('qo_id',qoId);
    const r=await fetch('quiz_editor.php?vid='+QE_VID_ID,{method:'POST',body:fd});
    const d=await r.json();
    if (d.ok) {
        document.getElementById(`qeq-${qoId}`)?.remove();
        document.querySelectorAll('#qeEditQList .qe-q-num').forEach((el,i)=>el.textContent=i+1);
        const count=document.querySelectorAll('#qeEditQList .qe-q-block').length;
        qeSyncTimelineMeta(qeEditingId, document.getElementById('qeEgLabel').value,
            parseFloat(document.getElementById('qeEgTrigger').value), count);
        qeShowToast('Question deleted.');
    }
}

function qeToggleAddQ() {
    const form=document.getElementById('qeAddQForm');
    form.classList.toggle('open');
    const btn=document.getElementById('qeAddQToggle');
    btn.innerHTML=form.classList.contains('open')
        ? '<i class="fas fa-xmark"></i> Cancel'
        : '<i class="fas fa-plus"></i> Add Question to this Checkpoint';
}

async function qeSaveExtraQuestion() {
    if (!qeEditingId) return;
    const d=qeCollectQ('eq_new');
    if (!d.question||!d.opt_a||!d.opt_b) { qeShowToast('Question and options A, B required!',true); return; }
    if (!d.correct) { qeShowToast('Select the correct answer!',true); return; }
    const fd=new FormData();
    fd.append('ajax_add_question','1'); fd.append('quiz_id',qeEditingId);
    qeAppendQ(fd,d);
    const r=await fetch('quiz_editor.php?vid='+QE_VID_ID,{method:'POST',body:fd});
    const resp=await r.json();
    if (resp.ok) { await qeOpenEdit(qeEditingId); qeShowToast('Question added!'); }
    else qeShowToast('Error: '+resp.error,true);
}

function qeResetAddPanel() {
    document.getElementById('qeFlabel').value='';
    document.getElementById('qeCapturedSeconds').value='';
    document.getElementById('qeCapturedVal').textContent='—';
    document.getElementById('qeCapturedBox').classList.add('empty');
    document.querySelectorAll('#qeAllQuestions .qe-cp-block').forEach((b,i)=>{ if(i>0) b.remove(); });
    qeClearQFields('qq1');
    qeQCount=1;
}

function qeClearQFields(prefix) {
    ['question','opt_a','opt_b','opt_c','opt_d','explanation'].forEach(f=>{
        const el=document.getElementById(`${prefix}_${f}`); if(el) el.value='';
    });
    document.querySelectorAll(`input[name="${prefix}_correct"]`).forEach(r=>r.checked=false);
    ['a','b','c','d'].forEach(k=>document.getElementById(`${prefix}_optbox_${k}`)?.classList.remove('selected'));
}

/* ── TOAST ─────────────────────────────────────────────────── */
function qeShowToast(msg, isErr=false, isWarn=false) {
    const t=document.getElementById('qeToast');
    t.textContent=msg;
    t.className='qe-toast show'+(isErr?' err':'')+(isWarn?' warn':'');
    clearTimeout(t._timer);
    t._timer=setTimeout(()=>{t.className='qe-toast'+(isErr?' err':'')+(isWarn?' warn':'');},3200);
}

function qeEsc(s=''){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;')}
function qeEscAttr(s=''){return String(s).replace(/"/g,'&quot;').replace(/'/g,'&#39;')}

document.addEventListener('DOMContentLoaded', () => {
    qeOfflineQueue()
        .filter(e => e.video_id == QE_VID_ID)
        .forEach(e => {
            qeAddToTimeline(e.tempId, e.trigger_time, e.group_label, e.questions.length, true);
        });
    qeUpdateOfflineBanner();
    if (navigator.onLine) qeSyncOffline();
});

// ─── AI Quiz Generation ────────────────────────────────────────
(function() {
  const btn = document.getElementById('aiGenBtn');
  if (!btn) return;
  const VID = <?php echo (int)$sel_vid; ?>;
  const CSRF = '<?php echo csrfToken(); ?>';

  let drafted = null;

  btn.addEventListener('click', () => openConfigStep());

  function openConfigStep() {
    showModal(`
      <div class="ai-config">
        <label>How many checkpoints?</label>
        <input type="number" id="aiNumCp" value="3" min="2" max="6">
        <span class="hint">2-6, spaced evenly across the video. AI will write 1 question per checkpoint.</span>
      </div>
      <p style="font-size:13px;color:var(--text-soft,#64748b);line-height:1.5;">
        AI will read this video's transcript and draft checkpoint questions for you to review.
        Nothing is saved until you click <strong>Save all</strong>.
      </p>
    `, [
      ['Cancel', 'btn-ghost', closeModal],
      ['<i class="fas fa-wand-magic-sparkles"></i> Generate', 'btn-primary', () => generateDraft()],
    ]);
  }

  async function generateDraft() {
    const numCp = parseInt(document.getElementById('aiNumCp').value, 10) || 3;
    showLoading('Reading transcript and generating quiz...');
    try {
      const fd = new FormData();
      fd.append('csrf', CSRF);
      fd.append('video_id', VID);
      fd.append('action', 'draft');
      fd.append('num_checkpoints', numCp);
      const r  = await fetch('generate_quiz.php', { method: 'POST', body: fd });
      const js = await r.json();
      if (!js.ok) throw new Error(js.error || 'Generation failed');
      drafted = js.checkpoints;
      renderReview(js);
    } catch (e) {
      showError(e.message);
    }
  }

  function renderReview(js) {
    const body = drafted.map((cp, ci) => `
      <div class="ai-cp" data-cp="${ci}">
        <div class="ai-cp-head">
          <label class="ai-cp-time">
            <i class="fas fa-clock"></i>
            <input type="number" min="0" max="${js.duration}" value="${cp.trigger_time}" data-field="trigger_time">s
          </label>
          <div class="ai-cp-label">
            <input type="text" value="${esc(cp.group_label)}" data-field="group_label" maxlength="80">
          </div>
          <button type="button" class="ai-cp-rm" data-rm-cp="${ci}" title="Remove checkpoint"><i class="fas fa-trash"></i></button>
        </div>
        ${cp.questions.map((q, qi) => `
          <div class="ai-q" data-q="${qi}">
            <textarea class="ai-q-text" data-field="question_text">${esc(q.question_text)}</textarea>
            <div class="ai-opts">
              ${['a','b','c','d'].map((letter, i) => `
                <label class="ai-opt">
                  <span class="label-letter">${letter.toUpperCase()}</span>
                  <input type="radio" name="ai-correct-${ci}-${qi}" value="${i+1}" ${q.correct_option === i+1 ? 'checked' : ''} data-field="correct_option">
                  <input type="text" value="${esc(q['option_'+letter])}" data-field="option_${letter}">
                </label>
              `).join('')}
            </div>
            <input type="text" class="ai-expl" value="${esc(q.explanation)}" data-field="explanation" placeholder="Explanation (why this answer is correct)">
          </div>
        `).join('')}
      </div>
    `).join('');

    showModal(`
      <p style="font-size:13px;color:var(--text-soft,#64748b);margin-bottom:14px;">
        <strong>${drafted.length}</strong> checkpoint${drafted.length===1?'':'s'} generated for <strong>${esc(js.title)}</strong>.
        Review and edit anything, then click <strong>Save all</strong>.
      </p>
      ${body}
    `, [
      ['<i class="fas fa-arrow-left"></i> Regenerate', 'btn-ghost', () => openConfigStep()],
      ['Cancel', 'btn-ghost', closeModal],
      [`<i class="fas fa-floppy-disk"></i> Save all (${drafted.length})`, 'btn-primary', () => saveAll()],
    ]);

    // Wire up live editing back to drafted[]
    document.querySelectorAll('.ai-cp').forEach(cpEl => {
      const ci = parseInt(cpEl.getAttribute('data-cp'), 10);
      cpEl.querySelector('[data-rm-cp]').addEventListener('click', () => {
        drafted.splice(ci, 1);
        renderReview(js); // re-render
      });
      cpEl.querySelectorAll('.ai-cp-head [data-field]').forEach(input => {
        input.addEventListener('input', () => {
          drafted[ci][input.getAttribute('data-field')] = input.value;
        });
      });
      cpEl.querySelectorAll('.ai-q').forEach(qEl => {
        const qi = parseInt(qEl.getAttribute('data-q'), 10);
        qEl.querySelectorAll('[data-field]').forEach(input => {
          input.addEventListener('input', () => {
            const f = input.getAttribute('data-field');
            if (f === 'correct_option' && input.type === 'radio') {
              if (input.checked) drafted[ci].questions[qi][f] = parseInt(input.value, 10);
            } else {
              drafted[ci].questions[qi][f] = input.value;
            }
          });
        });
      });
    });
  }

  async function saveAll() {
    if (!drafted || !drafted.length) { closeModal(); return; }
    showLoading('Saving checkpoints to the database...');
    try {
      const fd = new FormData();
      fd.append('csrf', CSRF);
      fd.append('video_id', VID);
      fd.append('action', 'save');
      fd.append('checkpoints', JSON.stringify(drafted));
      const r  = await fetch('generate_quiz.php', { method: 'POST', body: fd });
      const js = await r.json();
      if (!js.ok) throw new Error(js.error || 'Save failed');
      showModal(`
        <div style="text-align:center;padding:20px 0;">
          <div style="width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,#10b981,#059669);color:white;display:grid;place-items:center;margin:0 auto 16px;font-size:26px;">
            <i class="fas fa-check"></i>
          </div>
          <h3 style="font-size:18px;font-weight:700;margin-bottom:6px;">Saved!</h3>
          <p style="color:var(--text-soft,#64748b);font-size:13px;">${js.checkpoints} checkpoint${js.checkpoints===1?'':'s'} and ${js.questions} question${js.questions===1?'':'s'} added. The page will reload to show them.</p>
        </div>
      `, [['Reload now', 'btn-primary', () => location.reload()]]);
      setTimeout(() => location.reload(), 2500);
    } catch (e) {
      showError(e.message);
    }
  }

  // ─── Modal plumbing ───
  let modalEl;
  function ensureModal() {
    if (modalEl) return modalEl;
    modalEl = document.createElement('div');
    modalEl.className = 'ai-modal';
    modalEl.innerHTML = `
      <div class="ai-modal-box">
        <div class="ai-modal-head">
          <h3><i class="fas fa-wand-magic-sparkles"></i> AI Quiz Generator</h3>
          <span class="meta"></span>
          <button type="button" class="ai-modal-close" data-close><i class="fas fa-xmark"></i></button>
        </div>
        <div class="ai-modal-body"></div>
        <div class="ai-modal-foot"></div>
      </div>
    `;
    modalEl.addEventListener('click', e => { if (e.target === modalEl) closeModal(); });
    modalEl.querySelector('[data-close]').addEventListener('click', closeModal);
    document.body.appendChild(modalEl);
    return modalEl;
  }
  function showModal(bodyHtml, buttons) {
    const m = ensureModal();
    m.querySelector('.ai-modal-body').innerHTML = bodyHtml;
    const foot = m.querySelector('.ai-modal-foot');
    foot.innerHTML = '';
    (buttons || []).forEach(([label, cls, fn]) => {
      const b = document.createElement('button');
      b.className = 'btn ' + (cls || 'btn-ghost');
      b.innerHTML = label;
      b.type = 'button';
      b.addEventListener('click', fn);
      foot.appendChild(b);
    });
    m.classList.add('open');
  }
  function showLoading(msg) {
    showModal(`<div class="ai-loading"><i class="fas fa-wand-magic-sparkles"></i>${msg}</div>`, [['Cancel', 'btn-ghost', closeModal]]);
  }
  function showError(msg) {
    showModal(`<div class="ai-err"><i class="fas fa-circle-exclamation"></i> ${esc(msg)}</div>`,
      [['Close', 'btn-ghost', closeModal], ['Try again', 'btn-primary', openConfigStep]]);
  }
  function closeModal() { if (modalEl) modalEl.classList.remove('open'); }
  function esc(s) { return (s || '').toString().replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
})();
</script>



    </main>
  </div>
</div>
</body>
</html>