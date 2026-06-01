<?php
$body_class = 'page-quiz';
$page_title = 'Quiz Manager';

require_once __DIR__ . '/admin_auth.php';
requireAdmin();

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfCheck($_POST['csrf'] ?? '')) {
        $message = ['type' => 'error', 'text' => 'Session expired. Please refresh and try again.'];
    }
    elseif (isset($_POST['add_quiz_group'])) {
        $vid_id  = intval($_POST['video_id']);
        $trigger = floatval($_POST['trigger_time']);
        $label   = trim($_POST['group_label']);
        $stmt = mysqli_prepare($conn, "INSERT INTO video_quizzes (video_id, trigger_time, group_label) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'ids', $vid_id, $trigger, $label);
        if (mysqli_stmt_execute($stmt)) {
            adminAuditLog('quiz_checkpoint_created', "Video #$vid_id @ {$trigger}s — $label");
            $message = ['type' => 'success', 'text' => 'Quiz checkpoint created! Now add questions to it.'];
        } else {
            $message = ['type' => 'error', 'text' => 'DB error: ' . mysqli_error($conn)];
        }
    }
    elseif (isset($_POST['add_question'])) {
        $quiz_id = intval($_POST['quiz_id']);
        $qtext   = trim($_POST['question_text']);
        $opt_a   = trim($_POST['option_a']);
        $opt_b   = trim($_POST['option_b']);
        $opt_c   = trim($_POST['option_c']);
        $opt_d   = trim($_POST['option_d']);
        $correct = intval($_POST['correct_option']);
        $expl    = trim($_POST['explanation']);
        $stmt = mysqli_prepare($conn,
            "INSERT INTO quiz_options (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_option, explanation)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'isssssis', $quiz_id, $qtext, $opt_a, $opt_b, $opt_c, $opt_d, $correct, $expl);
        if (mysqli_stmt_execute($stmt)) {
            adminAuditLog('quiz_question_added', "Quiz #$quiz_id — $qtext");
            $message = ['type' => 'success', 'text' => 'Question added successfully!'];
        } else {
            $message = ['type' => 'error', 'text' => 'DB error: ' . mysqli_error($conn)];
        }
    }
    elseif (isset($_POST['delete_quiz'])) {
        $qid = intval($_POST['quiz_id']);
        $stmt = mysqli_prepare($conn, "DELETE FROM quiz_options WHERE quiz_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $qid);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $stmt = mysqli_prepare($conn, "DELETE FROM video_quizzes WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $qid);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        adminAuditLog('quiz_checkpoint_deleted', "Checkpoint #$qid removed");
        $message = ['type' => 'success', 'text' => 'Quiz checkpoint deleted.'];
    }
    elseif (isset($_POST['delete_question'])) {
        $oid = intval($_POST['option_id']);
        $stmt = mysqli_prepare($conn, "DELETE FROM quiz_options WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $oid);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        adminAuditLog('quiz_question_deleted', "Question #$oid removed");
        $message = ['type' => 'success', 'text' => 'Question deleted.'];
    }
}

// Load videos with checkpoint counts
$selected_video = isset($_GET['video_id']) ? intval($_GET['video_id']) : 0;
$videos_res = mysqli_query($conn, "
    SELECT v.id, v.title, (SELECT COUNT(*) FROM video_quizzes WHERE video_id = v.id) AS checkpoint_count
    FROM video v ORDER BY v.id DESC");
$videos = [];
while ($r = mysqli_fetch_assoc($videos_res)) $videos[] = $r;

// Load quizzes for selected video
$quizzes = [];
$selected_video_title = '';
if ($selected_video) {
    foreach ($videos as $v) if ($v['id'] == $selected_video) { $selected_video_title = $v['title']; break; }
    $stmt = mysqli_prepare($conn, "SELECT * FROM video_quizzes WHERE video_id = ? ORDER BY trigger_time ASC");
    mysqli_stmt_bind_param($stmt, 'i', $selected_video);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $qid = intval($row['id']);
        $oss = mysqli_prepare($conn, "SELECT * FROM quiz_options WHERE quiz_id = ? ORDER BY id ASC");
        mysqli_stmt_bind_param($oss, 'i', $qid);
        mysqli_stmt_execute($oss);
        $ores = mysqli_stmt_get_result($oss);
        $opts = [];
        while ($o = mysqli_fetch_assoc($ores)) $opts[] = $o;
        mysqli_stmt_close($oss);
        $row['questions'] = $opts;
        $quizzes[] = $row;
    }
    mysqli_stmt_close($stmt);
}

require __DIR__ . '/header.php';
?>

<style>
.field { margin-bottom: 14px; }
.field label { display: block; font-size: 12px; font-weight: 600; color: var(--text-soft); margin-bottom: 6px; text-transform: uppercase; letter-spacing: .05em; }
.input, .textarea, select.input { width: 100%; padding: 11px 14px; border: 1px solid var(--border); border-radius: 10px; background: var(--bg); color: var(--text); font: inherit; font-size: 14px; }
.input:focus, .textarea:focus, select.input:focus { outline: 0; border-color: var(--brand-1); box-shadow: 0 0 0 3px rgba(99,102,241,.15); }
.textarea { resize: vertical; min-height: 70px; }

.alert { display: flex; align-items: flex-start; gap: 10px; padding: 12px 14px; border-radius: 10px; font-size: 13px; margin-bottom: 16px; }
.alert.success { background: rgba(16,185,129,.1); color: #065f46; border: 1px solid rgba(16,185,129,.3); }
.alert.error   { background: rgba(239,68,68,.1);  color: #991b1b; border: 1px solid rgba(239,68,68,.3); }
html.dark .alert.success { color: #6ee7b7; }
html.dark .alert.error { color: #fca5a5; }

.quiz-grid { display: grid; grid-template-columns: 280px 1fr; gap: 22px; }
@media (max-width: 900px) { .quiz-grid { grid-template-columns: 1fr; } }

.video-list { display: flex; flex-direction: column; gap: 4px; max-height: 600px; overflow-y: auto; }
.video-list-item { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 10px; transition: all .15s; cursor: pointer; }
.video-list-item:hover { background: var(--bg); }
.video-list-item.active { background: rgba(99,102,241,.12); border-left: 3px solid var(--brand-1); }
.video-list-item .ic { width: 32px; height: 32px; border-radius: 8px; background: rgba(99,102,241,.15); color: var(--brand-1); display: grid; place-items: center; flex-shrink: 0; }
.video-list-item .info { flex: 1; min-width: 0; }
.video-list-item .title { font-size: 13px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.video-list-item .sub { font-size: 11px; color: var(--text-soft); }
.checkpoint-pill { padding: 2px 7px; border-radius: 999px; background: rgba(245,158,11,.15); color: #d97706; font-size: 10px; font-weight: 700; }

.checkpoint-card { background: var(--bg-elev); border: 1px solid var(--border); border-radius: 14px; padding: 18px; margin-bottom: 14px; }
.checkpoint-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 14px; padding-bottom: 14px; border-bottom: 1px solid var(--border); }
.checkpoint-time { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 999px; background: var(--grad-brand); color: white; font-weight: 700; font-size: 12px; }
.checkpoint-label { font-weight: 700; font-size: 15px; }

.question-card { background: var(--bg); border: 1px solid var(--border); border-radius: 10px; padding: 14px; margin-bottom: 10px; }
.question-q { font-weight: 600; font-size: 14px; margin-bottom: 10px; }
.option-list { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; margin-bottom: 8px; }
.option-row { display: flex; align-items: center; gap: 8px; padding: 6px 10px; border-radius: 8px; background: var(--bg-elev); border: 1px solid var(--border); font-size: 12px; }
.option-row.correct { background: rgba(16,185,129,.1); border-color: rgba(16,185,129,.3); color: #065f46; font-weight: 600; }
html.dark .option-row.correct { color: #6ee7b7; }
.option-letter { width: 22px; height: 22px; border-radius: 50%; background: var(--bg); display: grid; place-items: center; font-size: 10px; font-weight: 700; flex-shrink: 0; }
.option-row.correct .option-letter { background: #10b981; color: white; }

.expl { font-size: 12px; color: var(--text-soft); padding: 8px 12px; background: rgba(99,102,241,.06); border-radius: 8px; margin-top: 8px; border-left: 3px solid var(--brand-1); }
</style>

<?php if ($message): ?>
  <div class="alert <?php echo $message['type'] === 'success' ? 'success' : 'error'; ?>">
    <i class="fas fa-<?php echo $message['type'] === 'success' ? 'circle-check' : 'circle-exclamation'; ?>"></i>
    <div><?php echo htmlspecialchars($message['text']); ?></div>
  </div>
<?php endif; ?>

<div class="quiz-grid">
  <!-- Video list -->
  <div class="panel">
    <div class="panel-head">
      <h3><i class="fas fa-film"></i> Videos</h3>
    </div>
    <div class="video-list">
      <?php foreach ($videos as $v): ?>
        <a href="?video_id=<?php echo (int)$v['id']; ?>" class="video-list-item <?php echo $v['id'] == $selected_video ? 'active' : ''; ?>">
          <div class="ic"><i class="fas fa-play"></i></div>
          <div class="info">
            <div class="title"><?php echo htmlspecialchars($v['title']); ?></div>
            <div class="sub">#<?php echo (int)$v['id']; ?></div>
          </div>
          <?php if ($v['checkpoint_count'] > 0): ?>
            <span class="checkpoint-pill"><?php echo (int)$v['checkpoint_count']; ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Quizzes for selected video -->
  <div>
    <?php if (!$selected_video): ?>
      <div class="panel">
        <div class="empty-mini"><i class="fas fa-question"></i>Pick a video on the left to manage its quiz checkpoints.</div>
      </div>
    <?php else: ?>
      <!-- Add checkpoint -->
      <div class="panel" style="margin-bottom:22px;">
        <h3 style="font-size:16px;font-weight:700;margin-bottom:6px;"><i class="fas fa-plus-circle" style="color:var(--brand-1);"></i> Add checkpoint to: <em style="font-weight:500;color:var(--text-soft);"><?php echo htmlspecialchars($selected_video_title); ?></em></h3>
        <form method="post">
          <input type="hidden" name="csrf" value="<?php echo csrfToken(); ?>">
          <input type="hidden" name="video_id" value="<?php echo $selected_video; ?>">
          <div style="display:grid;grid-template-columns:1fr 2fr;gap:14px;">
            <div class="field"><label>Trigger time (seconds)</label><input type="number" name="trigger_time" class="input" step="0.1" min="0" required></div>
            <div class="field"><label>Label</label><input type="text" name="group_label" class="input" required placeholder="e.g. Mid-video check"></div>
          </div>
          <button type="submit" name="add_quiz_group" class="btn btn-primary"><i class="fas fa-plus"></i> Create checkpoint</button>
        </form>
      </div>

      <!-- Checkpoints -->
      <?php if (count($quizzes) === 0): ?>
        <div class="panel"><div class="empty-mini"><i class="fas fa-flag-checkered"></i>No checkpoints yet for this video.</div></div>
      <?php else: ?>
        <?php foreach ($quizzes as $q): ?>
          <div class="checkpoint-card">
            <div class="checkpoint-head">
              <div>
                <span class="checkpoint-time"><i class="fas fa-clock"></i> <?php echo number_format((float)$q['trigger_time'], 1); ?>s</span>
                <span class="checkpoint-label" style="margin-left:8px;"><?php echo htmlspecialchars($q['group_label']); ?></span>
              </div>
              <form method="post" onsubmit="return confirm('Delete this checkpoint and all its questions?');" style="display:inline;">
                <input type="hidden" name="csrf" value="<?php echo csrfToken(); ?>">
                <input type="hidden" name="quiz_id" value="<?php echo (int)$q['id']; ?>">
                <button type="submit" name="delete_quiz" class="btn btn-ghost" style="color:#ef4444;border-color:rgba(239,68,68,.3);"><i class="fas fa-trash"></i></button>
              </form>
            </div>

            <!-- Questions -->
            <?php if (count($q['questions']) === 0): ?>
              <div class="empty-mini" style="padding:14px;"><i class="fas fa-question-circle"></i>No questions yet.</div>
            <?php else: ?>
              <?php foreach ($q['questions'] as $opt): ?>
                <div class="question-card">
                  <div class="question-q"><?php echo htmlspecialchars($opt['question_text']); ?></div>
                  <div class="option-list">
                    <?php foreach (['a','b','c','d'] as $i => $letter):
                      $correct = ((int)$opt['correct_option'] === $i + 1);
                      $val = $opt['option_' . $letter];
                    ?>
                      <div class="option-row <?php echo $correct ? 'correct' : ''; ?>">
                        <div class="option-letter"><?php echo strtoupper($letter); ?></div>
                        <span><?php echo htmlspecialchars($val); ?></span>
                        <?php if ($correct): ?><i class="fas fa-check" style="margin-left:auto;"></i><?php endif; ?>
                      </div>
                    <?php endforeach; ?>
                  </div>
                  <?php if (!empty($opt['explanation'])): ?>
                    <div class="expl"><i class="fas fa-lightbulb" style="margin-right:6px;"></i><?php echo htmlspecialchars($opt['explanation']); ?></div>
                  <?php endif; ?>
                  <form method="post" onsubmit="return confirm('Delete this question?');" style="margin-top:8px;">
                    <input type="hidden" name="csrf" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="option_id" value="<?php echo (int)$opt['id']; ?>">
                    <button type="submit" name="delete_question" class="btn btn-ghost" style="font-size:11px;padding:5px 10px;color:#ef4444;"><i class="fas fa-trash"></i> Delete question</button>
                  </form>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>

            <!-- Add question form -->
            <details style="margin-top:10px;">
              <summary style="cursor:pointer;padding:8px 12px;border-radius:8px;background:var(--bg);font-size:13px;font-weight:600;color:var(--brand-1);"><i class="fas fa-plus"></i> Add question</summary>
              <form method="post" style="padding:14px 0 4px;">
                <input type="hidden" name="csrf" value="<?php echo csrfToken(); ?>">
                <input type="hidden" name="quiz_id" value="<?php echo (int)$q['id']; ?>">
                <div class="field"><label>Question</label><textarea name="question_text" class="textarea" rows="2" required></textarea></div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                  <div class="field"><label>Option A</label><input type="text" name="option_a" class="input" required></div>
                  <div class="field"><label>Option B</label><input type="text" name="option_b" class="input" required></div>
                  <div class="field"><label>Option C</label><input type="text" name="option_c" class="input" required></div>
                  <div class="field"><label>Option D</label><input type="text" name="option_d" class="input" required></div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 2fr;gap:10px;">
                  <div class="field"><label>Correct option</label><select name="correct_option" class="input" required><option value="1">A</option><option value="2">B</option><option value="3">C</option><option value="4">D</option></select></div>
                  <div class="field"><label>Explanation</label><input type="text" name="explanation" class="input" placeholder="Why this is the right answer"></div>
                </div>
                <button type="submit" name="add_question" class="btn btn-primary"><i class="fas fa-plus"></i> Add question</button>
              </form>
            </details>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

    </main>
  </div>
</div>
</body>
</html>