<?php
$body_class = 'page-quiz-responses';
$page_title = 'Quiz Responses';

require_once __DIR__ . '/admin_auth.php';
requireAdmin();

$sel_vid   = isset($_GET['vid'])  ? intval($_GET['vid'])   : 0;
$sel_group = isset($_GET['grp'])  ? trim($_GET['grp'])     : '';
$sel_user  = isset($_GET['usr'])  ? trim($_GET['usr'])     : '';
$view_mode = isset($_GET['view']) ? trim($_GET['view'])    : 'overview';

$vres   = mysqli_query($conn, "SELECT id, title FROM video ORDER BY id DESC");
$videos = [];
while ($r = mysqli_fetch_assoc($vres)) $videos[] = $r;

// Groups — prepared
$groups = [];
if ($sel_vid) {
    $stmt = mysqli_prepare($conn, "SELECT DISTINCT group_name FROM quiz_responses WHERE video_id = ? AND group_name != '' ORDER BY group_name ASC");
    mysqli_stmt_bind_param($stmt, 'i', $sel_vid);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($r = mysqli_fetch_assoc($res)) $groups[] = $r['group_name'];
    mysqli_stmt_close($stmt);
}

/**
 * Helper: build the (where, types, params) tuple for the active filter.
 * Eliminates the old "$w = ..." string concatenation entirely.
 */
function qr_filter($sel_vid, $sel_group, $sel_user) {
    $where  = ['qr.video_id = ?'];
    $types  = 'i';
    $params = [$sel_vid];
    if ($sel_group !== '') { $where[] = 'qr.group_name = ?'; $types .= 's'; $params[] = $sel_group; }
    if ($sel_user  !== '') { $where[] = 'qr.user_name LIKE ?'; $types .= 's'; $params[] = '%' . $sel_user . '%'; }
    return ['WHERE ' . implode(' AND ', $where), $types, $params];
}

function fmtT($s) {
    $m = floor($s / 60);
    $ss = str_pad(floor($s % 60), 2, '0', STR_PAD_LEFT);
    return "$m:$ss";
}

require __DIR__ . '/header.php';
?>

<style>
.filter-bar { display: flex; gap: 12px; align-items: flex-end; padding: 16px; background: var(--bg-elev); border: 1px solid var(--border); border-radius: 14px; margin-bottom: 22px; flex-wrap: wrap; }
.fg { display: flex; flex-direction: column; gap: 5px; min-width: 180px; }
.fg label { font-size: 11px; font-weight: 600; color: var(--text-soft); text-transform: uppercase; letter-spacing: .05em; }
.fg select, .fg input { padding: 10px 14px; border: 1px solid var(--border); border-radius: 10px; background: var(--bg); color: var(--text); font: inherit; font-size: 13px; }
.fg select:focus, .fg input:focus { outline: 0; border-color: var(--brand-1); box-shadow: 0 0 0 3px rgba(99,102,241,.15); }

.stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 22px; }
.qs-card { background: var(--bg-elev); border: 1px solid var(--border); border-radius: 14px; padding: 16px; display: flex; align-items: center; gap: 12px; }
.qs-card .ic { width: 44px; height: 44px; border-radius: 12px; display: grid; place-items: center; font-size: 16px; flex-shrink: 0; }
.qs-card .ic.users { background: rgba(99,102,241,.12); color: #6366f1; }
.qs-card .ic.answers { background: rgba(14,165,233,.12); color: #0ea5e9; }
.qs-card .ic.accuracy { background: rgba(16,185,129,.12); color: #10b981; }
.qs-card .ic.checkpoints { background: rgba(245,158,11,.12); color: #f59e0b; }
.qs-card .v { font-family: 'Sora', sans-serif; font-weight: 800; font-size: 24px; line-height: 1; }
.qs-card .l { font-size: 11px; color: var(--text-soft); text-transform: uppercase; letter-spacing: .04em; margin-top: 4px; }

.section-h { font-size: 14px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--text-soft); margin: 22px 0 12px; display: flex; align-items: center; gap: 8px; }
.section-h i { color: var(--brand-1); }

.tbl { width: 100%; background: var(--bg-elev); border: 1px solid var(--border); border-radius: 14px; overflow: hidden; }
.tbl table { width: 100%; border-collapse: collapse; }
.tbl th { text-align: left; padding: 12px 16px; font-size: 11px; font-weight: 700; color: var(--text-soft); text-transform: uppercase; letter-spacing: .06em; background: var(--bg); border-bottom: 1px solid var(--border); }
.tbl td { padding: 12px 16px; font-size: 13px; border-bottom: 1px solid var(--border); }
.tbl tr:last-child td { border-bottom: 0; }
.tbl tbody tr:hover { background: var(--bg); }

.name-cell { display: flex; align-items: center; gap: 10px; }
.av { width: 32px; height: 32px; border-radius: 50%; background: var(--grad-brand); color: white; font-weight: 700; font-size: 12px; display: grid; place-items: center; flex-shrink: 0; }
.pb { display: inline-block; width: 80px; height: 6px; background: var(--bg); border-radius: 999px; overflow: hidden; vertical-align: middle; margin-right: 6px; }
.pf { display: block; height: 100%; }
.pf.pass { background: var(--green); }
.pf.fail { background: var(--red); }

.badge { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; }
.badge.pass { background: rgba(16,185,129,.15); color: #065f46; }
.badge.fail { background: rgba(239,68,68,.15); color: #991b1b; }
html.dark .badge.pass { color: #6ee7b7; }
html.dark .badge.fail { color: #fca5a5; }

.time-pill { display: inline-flex; align-items: center; gap: 5px; padding: 3px 9px; border-radius: 999px; background: rgba(99,102,241,.12); color: var(--brand-1); font-size: 11px; font-weight: 700; }

.empty-state { padding: 60px 20px; text-align: center; background: var(--bg-elev); border: 1px dashed var(--border); border-radius: 14px; }
.empty-state i { font-size: 36px; color: var(--muted); margin-bottom: 12px; display: block; }
.empty-state h3 { font-size: 17px; font-weight: 700; margin-bottom: 6px; }
.empty-state p { color: var(--text-soft); font-size: 13px; }

.back-link { display: inline-flex; align-items: center; gap: 7px; padding: 8px 14px; border-radius: 10px; background: var(--bg-elev); border: 1px solid var(--border); font-size: 13px; font-weight: 500; color: var(--text-soft); margin-bottom: 18px; transition: all .15s; }
.back-link:hover { color: var(--brand-1); border-color: var(--brand-1); }

.user-header { display: flex; align-items: center; gap: 16px; padding: 22px; background: var(--bg-elev); border: 1px solid var(--border); border-radius: 14px; margin-bottom: 22px; }
.user-header .av-lg { width: 64px; height: 64px; border-radius: 50%; background: var(--grad-brand); color: white; font-weight: 800; font-size: 22px; display: grid; place-items: center; }
.user-header h2 { font-size: 22px; font-weight: 800; }
.user-header .meta { font-size: 13px; color: var(--text-soft); margin-top: 4px; }

.detail-q { background: var(--bg-elev); border: 1px solid var(--border); border-radius: 14px; padding: 18px; margin-bottom: 14px; }
.dq-head { font-weight: 700; font-size: 15px; margin-bottom: 12px; display: flex; align-items: center; gap: 10px; }
.dq-meta { display: flex; gap: 14px; font-size: 12px; color: var(--text-soft); margin-bottom: 10px; }
.dq-meta span { display: inline-flex; align-items: center; gap: 5px; }
</style>

<form method="GET" class="filter-bar">
  <div class="fg">
    <label>Video</label>
    <select name="vid" onchange="this.form.submit()">
      <option value="">— Select a video —</option>
      <?php foreach ($videos as $v): ?>
        <option value="<?php echo $v['id']; ?>" <?php if ($v['id'] == $sel_vid) echo 'selected'; ?>>
          <?php echo htmlspecialchars($v['title']); ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php if ($sel_vid && !empty($groups)): ?>
    <div class="fg">
      <label>Group</label>
      <select name="grp">
        <option value="">— All groups —</option>
        <?php foreach ($groups as $g): ?>
          <option value="<?php echo htmlspecialchars($g); ?>" <?php if ($g === $sel_group) echo 'selected'; ?>><?php echo htmlspecialchars($g); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  <?php endif; ?>
  <?php if ($sel_vid): ?>
    <div class="fg">
      <label>Search name</label>
      <input type="text" name="usr" value="<?php echo htmlspecialchars($sel_user); ?>" placeholder="Type a name…">
    </div>
    <input type="hidden" name="view" value="overview">
    <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply</button>
  <?php endif; ?>
</form>

<?php if (!$sel_vid): ?>
  <div class="empty-state">
    <i class="fas fa-chart-line"></i>
    <h3>Pick a video to begin</h3>
    <p>Use the filter above to select a video and see who's been answering its checkpoints.</p>
  </div>
<?php else:
  // Build the prepared filter once
  list($whereSQL, $whTypes, $whParams) = qr_filter($sel_vid, $sel_group, $sel_user);

  if ($view_mode === 'user' && $sel_user):
    $cpRes = mysqli_query($conn, "SELECT * FROM video_quizzes WHERE video_id = " . intval($sel_vid) . " ORDER BY trigger_time ASC");
    // ^ video_id already intval'd, safe; using mysqli_query for read-only no-input is fine but use prepared for consistency:
    $cps = [];
    $stmt = mysqli_prepare($conn, "SELECT * FROM video_quizzes WHERE video_id = ? ORDER BY trigger_time ASC");
    mysqli_stmt_bind_param($stmt, 'i', $sel_vid);
    mysqli_stmt_execute($stmt);
    $r = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($r)) $cps[] = $row;
    mysqli_stmt_close($stmt);

    // Detail query — prepared
    $detail_sql = "SELECT qr.*, qo.question_text, qo.option_a, qo.option_b, qo.option_c, qo.option_d, qo.correct_option, qo.explanation, vq.group_label
                   FROM quiz_responses qr
                   JOIN quiz_options qo ON qo.id = qr.option_id
                   JOIN video_quizzes vq ON vq.id = qr.quiz_id
                   WHERE qr.video_id = ? AND qr.user_name = ?
                   ORDER BY vq.trigger_time ASC, qr.answered_at ASC";
    $stmt = mysqli_prepare($conn, $detail_sql);
    mysqli_stmt_bind_param($stmt, 'is', $sel_vid, $sel_user);
    mysqli_stmt_execute($stmt);
    $detailRes = mysqli_stmt_get_result($stmt);
    $details = [];
    while ($r = mysqli_fetch_assoc($detailRes)) $details[] = $r;
    mysqli_stmt_close($stmt);

    $u_tot = count($details);
    $u_cor = array_sum(array_column($details, 'is_correct'));
    $u_pct = $u_tot > 0 ? round($u_cor / $u_tot * 100) : 0;
?>
    <a class="back-link" href="quiz_responses.php?vid=<?php echo $sel_vid; ?>&grp=<?php echo urlencode($sel_group); ?>"><i class="fas fa-arrow-left"></i> Back to overview</a>

    <div class="user-header">
      <div class="av-lg"><?php echo strtoupper(substr($sel_user, 0, 1)); ?></div>
      <div>
        <h2><?php echo htmlspecialchars($sel_user); ?></h2>
        <div class="meta">
          <?php echo $u_tot; ?> answers · <?php echo $u_cor; ?> correct ·
          <strong style="color: <?php echo $u_pct >= 50 ? '#10b981' : '#ef4444'; ?>;"><?php echo $u_pct; ?>%</strong> accuracy
        </div>
      </div>
    </div>

    <div class="section-h"><i class="fas fa-list"></i> All answers</div>
    <?php foreach ($details as $d):
      $chosen = $d['option_chosen'] ? strtoupper($d['option_chosen']) : '?';
      $correct = (int)$d['is_correct'] === 1;
    ?>
      <div class="detail-q">
        <div class="dq-meta">
          <span><i class="fas fa-flag"></i> <?php echo htmlspecialchars($d['group_label']); ?></span>
          <span><i class="far fa-clock"></i> <?php echo (int)$d['time_taken_sec']; ?>s</span>
          <span><i class="far fa-calendar"></i> <?php echo date('M j, H:i', strtotime($d['answered_at'])); ?></span>
        </div>
        <div class="dq-head"><?php echo htmlspecialchars($d['question_text']); ?></div>
        <div style="font-size: 13px; color: var(--text-soft);">
          Chose <strong><?php echo $chosen; ?></strong> ·
          <span class="badge <?php echo $correct ? 'pass' : 'fail'; ?>">
            <i class="fas fa-<?php echo $correct ? 'check' : 'xmark'; ?>"></i>
            <?php echo $correct ? 'Correct' : 'Incorrect'; ?>
          </span>
        </div>
        <?php if (!empty($d['explanation'])): ?>
          <div style="font-size:12px;color:var(--text-soft);padding:8px 12px;background:rgba(99,102,241,.06);border-radius:8px;margin-top:10px;border-left:3px solid var(--brand-1);">
            <i class="fas fa-lightbulb"></i> <?php echo htmlspecialchars($d['explanation']); ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>

<?php else: /* OVERVIEW */
    // Stats — prepared
    $stmt = mysqli_prepare($conn, "SELECT COUNT(DISTINCT user_name) AS u, COUNT(*) AS t, SUM(is_correct) AS c FROM quiz_responses qr $whereSQL");
    mysqli_stmt_bind_param($stmt, $whTypes, ...$whParams);
    mysqli_stmt_execute($stmt);
    $stat = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    $avgPct = $stat['t'] > 0 ? round($stat['c'] / $stat['t'] * 100) : 0;

    // Users — prepared
    $stmt = mysqli_prepare($conn, "
        SELECT user_name, group_name,
               COUNT(DISTINCT quiz_id) AS quizzes,
               COUNT(*) AS total,
               SUM(is_correct) AS correct,
               MAX(answered_at) AS last
        FROM quiz_responses qr $whereSQL
        GROUP BY user_name, group_name ORDER BY last DESC");
    mysqli_stmt_bind_param($stmt, $whTypes, ...$whParams);
    mysqli_stmt_execute($stmt);
    $usrs = mysqli_stmt_get_result($stmt);
    $users = [];
    while ($r = mysqli_fetch_assoc($usrs)) $users[] = $r;
    mysqli_stmt_close($stmt);

    // Checkpoints — prepared (note: JOIN with the filter applied to the joined table)
    $cp_sql = "SELECT vq.id, vq.trigger_time, vq.group_label,
                      COUNT(DISTINCT qr.user_name) AS users,
                      COUNT(qr.id) AS total,
                      SUM(qr.is_correct) AS correct
               FROM video_quizzes vq
               LEFT JOIN quiz_options qo ON qo.quiz_id = vq.id
               LEFT JOIN quiz_responses qr ON qr.option_id = qo.id AND " . str_replace('WHERE ', '', $whereSQL) . "
               WHERE vq.video_id = ?
               GROUP BY vq.id ORDER BY vq.trigger_time ASC";
    $stmt = mysqli_prepare($conn, $cp_sql);
    // Bind: filter params first, then $sel_vid at the end (for WHERE vq.video_id = ?)
    $cp_types = $whTypes . 'i';
    $cp_params = array_merge($whParams, [$sel_vid]);
    mysqli_stmt_bind_param($stmt, $cp_types, ...$cp_params);
    mysqli_stmt_execute($stmt);
    $cpRes = mysqli_stmt_get_result($stmt);
    $cps = [];
    while ($r = mysqli_fetch_assoc($cpRes)) $cps[] = $r;
    mysqli_stmt_close($stmt);
?>
    <div class="stats-row">
      <div class="qs-card"><div class="ic users"><i class="fas fa-users"></i></div><div><div class="v"><?php echo (int)($stat['u'] ?? 0); ?></div><div class="l">Unique users</div></div></div>
      <div class="qs-card"><div class="ic answers"><i class="fas fa-list-check"></i></div><div><div class="v"><?php echo (int)($stat['t'] ?? 0); ?></div><div class="l">Total answers</div></div></div>
      <div class="qs-card"><div class="ic accuracy"><i class="fas fa-bullseye"></i></div><div><div class="v" style="color:#10b981;"><?php echo $avgPct; ?>%</div><div class="l">Accuracy</div></div></div>
      <div class="qs-card"><div class="ic checkpoints"><i class="fas fa-flag-checkered"></i></div><div><div class="v"><?php echo count($cps); ?></div><div class="l">Checkpoints</div></div></div>
    </div>

    <?php if (!empty($cps)): ?>
      <div class="section-h"><i class="fas fa-flag-checkered"></i> Checkpoint pass rates</div>
      <div class="tbl">
        <table>
          <thead><tr><th>Checkpoint</th><th>Time</th><th>Users</th><th>Accuracy</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($cps as $cp):
              $pct = $cp['total'] > 0 ? round($cp['correct'] / $cp['total'] * 100) : 0;
              $cls = $pct >= 50 ? 'pass' : 'fail';
            ?>
              <tr>
                <td style="font-weight:600;"><?php echo htmlspecialchars($cp['group_label'] ?: 'Quiz'); ?></td>
                <td><span class="time-pill"><i class="far fa-clock"></i> <?php echo fmtT($cp['trigger_time']); ?></span></td>
                <td><?php echo $cp['users'] ?: 0; ?></td>
                <td>
                  <span class="pb"><span class="pf <?php echo $cls; ?>" style="width:<?php echo $pct; ?>%"></span></span>
                  <span style="font-weight:600;"><?php echo $pct; ?>%</span>
                </td>
                <td><span class="badge <?php echo $cls; ?>"><i class="fas fa-<?php echo $cls === 'pass' ? 'check' : 'triangle-exclamation'; ?>"></i> <?php echo $pct >= 50 ? 'Passing' : 'Needs review'; ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <?php if (empty($users)): ?>
      <div class="empty-state">
        <i class="fas fa-users-slash"></i>
        <h3>No responses found</h3>
        <p>Nobody has answered the checkpoints for this video yet.</p>
      </div>
    <?php else: ?>
      <div class="section-h"><i class="fas fa-users"></i> User results</div>
      <div class="tbl">
        <table>
          <thead><tr><th>Name</th><th>Group</th><th>Checkpoints</th><th>Score</th><th>Status</th><th>Last activity</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($users as $u):
              $pct = $u['total'] > 0 ? round($u['correct'] / $u['total'] * 100) : 0;
              $pass = $pct >= 50;
            ?>
              <tr>
                <td><div class="name-cell"><div class="av"><?php echo strtoupper(substr($u['user_name'], 0, 1)); ?></div><div><?php echo htmlspecialchars($u['user_name']); ?></div></div></td>
                <td><?php echo htmlspecialchars($u['group_name'] ?: '—'); ?></td>
                <td><?php echo (int)$u['quizzes']; ?></td>
                <td>
                  <span class="pb"><span class="pf <?php echo $pass ? 'pass' : 'fail'; ?>" style="width:<?php echo $pct; ?>%"></span></span>
                  <span style="font-weight:600;"><?php echo $pct; ?>%</span>
                </td>
                <td><span class="badge <?php echo $pass ? 'pass' : 'fail'; ?>"><i class="fas fa-<?php echo $pass ? 'check' : 'triangle-exclamation'; ?>"></i> <?php echo $pass ? 'Passing' : 'Needs review'; ?></span></td>
                <td style="font-size:12px;color:var(--text-soft);"><?php echo date('M j, H:i', strtotime($u['last'])); ?></td>
                <td>
                  <a href="?vid=<?php echo $sel_vid; ?>&grp=<?php echo urlencode($sel_group); ?>&usr=<?php echo urlencode($u['user_name']); ?>&view=user" class="btn btn-ghost" style="padding:5px 10px;font-size:12px;"><i class="fas fa-eye"></i></a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
<?php endif; endif; ?>

    </main>
  </div>
</div>
</body>
</html>