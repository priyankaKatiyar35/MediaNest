<?php
/**
 * MediaNest Admin — Quiz Analytics Dashboard
 * --------------------------------------------------------------
 * 5 tabs: Overview · Per-video · Hardest questions · Per-user · Per-group
 * CSV export of the active view via ?export=csv
 * All queries prepared. All filters optional (video, group, date range).
 */
$body_class = 'page-analytics';
$page_title = 'Quiz Analytics';

require_once __DIR__ . '/admin_auth.php';
requireAdmin();
global $conn;

// ─── Filters ────────────────────────────────────────────
$view      = $_GET['view'] ?? 'overview';
$valid_views = ['overview', 'videos', 'questions', 'users', 'groups'];
if (!in_array($view, $valid_views)) $view = 'overview';

$sel_vid   = isset($_GET['vid'])  && $_GET['vid']  !== '' ? intval($_GET['vid'])  : 0;
$sel_group = isset($_GET['grp'])  ? trim($_GET['grp'])  : '';
$date_from = isset($_GET['from']) ? trim($_GET['from']) : '';  // YYYY-MM-DD
$date_to   = isset($_GET['to'])   ? trim($_GET['to'])   : '';
$export    = isset($_GET['export']) && $_GET['export'] === 'csv';

// Helpers
function fmtPct($n, $d) { return $d > 0 ? round($n / $d * 100, 1) : 0; }
function fmtT($s) { return floor($s / 60) . ':' . str_pad(floor($s % 60), 2, '0', STR_PAD_LEFT); }

/**
 * Build WHERE clause + bind types + params for the active filter set.
 * Optionally prefix every column reference (e.g. 'qr.').
 */
function filterWhere($vid, $grp, $from, $to, $prefix = 'qr.') {
    $w = [];
    $t = ''; $p = [];
    if ($vid > 0)        { $w[] = "{$prefix}video_id = ?";     $t.='i'; $p[]=$vid; }
    if ($grp !== '')     { $w[] = "{$prefix}group_name = ?";   $t.='s'; $p[]=$grp; }
    if ($from !== '')    { $w[] = "{$prefix}answered_at >= ?"; $t.='s'; $p[]=$from . ' 00:00:00'; }
    if ($to !== '')      { $w[] = "{$prefix}answered_at <= ?"; $t.='s'; $p[]=$to   . ' 23:59:59'; }
    return [
        'sql'   => $w ? ' WHERE ' . implode(' AND ', $w) : '',
        'types' => $t,
        'params'=> $p,
    ];
}
function runPrepared($conn, $sql, $types, $params) {
    $s = mysqli_prepare($conn, $sql);
    if (!$s) return [];
    if ($types) mysqli_stmt_bind_param($s, $types, ...$params);
    mysqli_stmt_execute($s);
    $r = mysqli_stmt_get_result($s);
    $rows = [];
    while ($row = mysqli_fetch_assoc($r)) $rows[] = $row;
    mysqli_stmt_close($s);
    return $rows;
}

// ─── Lookup data ────────────────────────────────────────
$videos = runPrepared($conn, "SELECT id, title FROM video ORDER BY id DESC", '', []);
$groups = array_column(
    runPrepared($conn, "SELECT DISTINCT group_name FROM quiz_responses WHERE group_name != '' ORDER BY group_name ASC", '', []),
    'group_name'
);

$F = filterWhere($sel_vid, $sel_group, $date_from, $date_to);

// ─── Section data ───────────────────────────────────────
$overview = null;
$per_video = [];
$hardest = [];
$per_user = [];
$per_group = [];

if ($view === 'overview') {
    $s = runPrepared($conn,
        "SELECT COUNT(*) AS total,
                SUM(is_correct) AS correct,
                COUNT(DISTINCT user_name) AS users,
                COUNT(DISTINCT video_id) AS videos,
                COUNT(DISTINCT group_name) AS groups,
                MIN(answered_at) AS first_at,
                MAX(answered_at) AS last_at
         FROM quiz_responses qr" . $F['sql'],
        $F['types'], $F['params']);
    $overview = $s[0] ?? null;
    // Trend: responses per day for last 14 days (always — independent of date filter for context)
    $trend = runPrepared($conn,
        "SELECT DATE(answered_at) AS d, COUNT(*) AS n, SUM(is_correct) AS c
         FROM quiz_responses
         WHERE answered_at >= (NOW() - INTERVAL 14 DAY)
         GROUP BY DATE(answered_at) ORDER BY d ASC", '', []);
}
elseif ($view === 'videos') {
    $per_video = runPrepared($conn,
        "SELECT v.id, v.title,
                COUNT(qr.id) AS attempts,
                COUNT(DISTINCT qr.user_name) AS unique_users,
                SUM(qr.is_correct) AS correct,
                AVG(qr.is_correct) * 100 AS pct
         FROM quiz_responses qr
         JOIN video v ON v.id = qr.video_id
         " . $F['sql'] . "
         GROUP BY v.id, v.title
         HAVING attempts > 0
         ORDER BY pct ASC, attempts DESC",
        $F['types'], $F['params']);
}
elseif ($view === 'questions') {
    $hardest = runPrepared($conn,
        "SELECT qo.id, qo.question_text, qo.correct_option,
                v.title AS video_title,
                vq.trigger_time, vq.group_label,
                COUNT(qr.id) AS attempts,
                SUM(qr.is_correct) AS correct,
                AVG(qr.is_correct) * 100 AS pct
         FROM quiz_options qo
         JOIN video_quizzes vq ON vq.id = qo.quiz_id
         JOIN video v ON v.id = vq.video_id
         LEFT JOIN quiz_responses qr ON qr.option_id = qo.id
         " . str_replace(' WHERE ', ' WHERE qr.id IS NOT NULL AND ', $F['sql'] !== '' ? $F['sql'] : ' WHERE qr.id IS NOT NULL') . "
         GROUP BY qo.id
         HAVING attempts >= 1
         ORDER BY pct ASC, attempts DESC
         LIMIT 30",
        $F['types'], $F['params']);
}
elseif ($view === 'users') {
    $per_user = runPrepared($conn,
        "SELECT qr.user_name, qr.group_name,
                COUNT(*) AS attempts,
                COUNT(DISTINCT qr.video_id) AS videos,
                SUM(qr.is_correct) AS correct,
                AVG(qr.is_correct) * 100 AS pct,
                MAX(qr.answered_at) AS last_at
         FROM quiz_responses qr
         " . $F['sql'] . "
         GROUP BY qr.user_name, qr.group_name
         HAVING attempts > 0
         ORDER BY pct DESC, attempts DESC",
        $F['types'], $F['params']);
}
elseif ($view === 'groups') {
    $per_group = runPrepared($conn,
        "SELECT IFNULL(NULLIF(qr.group_name,''),'(unspecified)') AS group_name,
                COUNT(DISTINCT qr.user_name) AS users,
                COUNT(*) AS attempts,
                SUM(qr.is_correct) AS correct,
                AVG(qr.is_correct) * 100 AS pct
         FROM quiz_responses qr
         " . $F['sql'] . "
         GROUP BY IFNULL(NULLIF(qr.group_name,''),'(unspecified)')
         HAVING attempts > 0
         ORDER BY pct DESC",
        $F['types'], $F['params']);
}

// ─── CSV Export ─────────────────────────────────────────
if ($export) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="quiz_' . $view . '_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF"); // BOM so Excel reads UTF-8

    if ($view === 'videos') {
        fputcsv($out, ['Video ID', 'Title', 'Attempts', 'Unique users', 'Correct', 'Pass %']);
        foreach ($per_video as $r) fputcsv($out, [$r['id'], $r['title'], $r['attempts'], $r['unique_users'], $r['correct'], round($r['pct'], 1)]);
    } elseif ($view === 'questions') {
        fputcsv($out, ['Question ID', 'Video', 'Checkpoint', 'Question', 'Attempts', 'Correct', 'Pass %']);
        foreach ($hardest as $r) fputcsv($out, [$r['id'], $r['video_title'], $r['group_label'] . ' @ ' . fmtT($r['trigger_time']), $r['question_text'], $r['attempts'], $r['correct'], round($r['pct'], 1)]);
    } elseif ($view === 'users') {
        fputcsv($out, ['User', 'Group', 'Attempts', 'Videos', 'Correct', 'Pass %', 'Last answered']);
        foreach ($per_user as $r) fputcsv($out, [$r['user_name'], $r['group_name'], $r['attempts'], $r['videos'], $r['correct'], round($r['pct'], 1), $r['last_at']]);
    } elseif ($view === 'groups') {
        fputcsv($out, ['Group', 'Users', 'Attempts', 'Correct', 'Pass %']);
        foreach ($per_group as $r) fputcsv($out, [$r['group_name'], $r['users'], $r['attempts'], $r['correct'], round($r['pct'], 1)]);
    } else {
        fputcsv($out, ['Metric', 'Value']);
        if ($overview) {
            fputcsv($out, ['Total responses', $overview['total']]);
            fputcsv($out, ['Correct', $overview['correct']]);
            fputcsv($out, ['Pass %', $overview['total'] > 0 ? round($overview['correct'] / $overview['total'] * 100, 1) : 0]);
            fputcsv($out, ['Users', $overview['users']]);
            fputcsv($out, ['Videos', $overview['videos']]);
            fputcsv($out, ['Groups', $overview['groups']]);
            fputcsv($out, ['First response', $overview['first_at']]);
            fputcsv($out, ['Last response', $overview['last_at']]);
        }
    }
    fclose($out);
    exit;
}

require __DIR__ . '/header.php';
?>

<style>
.an-tabs { display: flex; gap: 4px; padding: 5px; background: var(--bg-elev); border: 1px solid var(--border); border-radius: 13px; margin-bottom: 18px; overflow-x: auto; }
.an-tab { padding: 9px 14px; border-radius: 9px; font-size: 13px; font-weight: 600; color: var(--text-soft); white-space: nowrap; display: inline-flex; align-items: center; gap: 7px; transition: all .15s; cursor: pointer; }
.an-tab:hover { background: var(--bg); color: var(--text); }
.an-tab.active { background: var(--grad-brand); color: white; box-shadow: 0 6px 18px rgba(99,102,241,.25); }

.an-filters { display: flex; gap: 10px; flex-wrap: wrap; padding: 12px 14px; background: var(--bg-elev); border: 1px solid var(--border); border-radius: 12px; margin-bottom: 18px; align-items: flex-end; }
.an-filters .fg { display: flex; flex-direction: column; gap: 4px; }
.an-filters .fg label { font-size: 10px; font-weight: 700; color: var(--text-soft); text-transform: uppercase; letter-spacing: .06em; }
.an-filters .fg select, .an-filters .fg input { padding: 8px 12px; border: 1px solid var(--border); border-radius: 8px; background: var(--bg); color: var(--text); font: inherit; font-size: 13px; min-width: 150px; }
.an-filters .actions { margin-left: auto; display: flex; gap: 8px; }

.kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 22px; }
.kpi { background: var(--bg-elev); border: 1px solid var(--border); border-radius: 14px; padding: 18px; transition: transform .15s, border-color .15s; }
.kpi:hover { transform: translateY(-2px); border-color: rgba(99,102,241,.3); }
.kpi-ic { width: 38px; height: 38px; border-radius: 10px; display: grid; place-items: center; color: white; margin-bottom: 12px; font-size: 14px; }
.kpi-label { font-size: 11px; font-weight: 700; color: var(--text-soft); text-transform: uppercase; letter-spacing: .06em; margin-bottom: 4px; }
.kpi-value { font-size: 28px; font-weight: 800; line-height: 1; }
.kpi-suffix { font-size: 14px; color: var(--text-soft); margin-left: 4px; font-weight: 600; }
.kpi-sub { font-size: 11px; color: var(--muted); margin-top: 8px; }

.tbl-wrap { background: var(--bg-elev); border: 1px solid var(--border); border-radius: 14px; overflow: hidden; margin-bottom: 18px; }
table.an { width: 100%; border-collapse: collapse; }
table.an th { text-align: left; padding: 11px 14px; font-size: 10px; font-weight: 700; color: var(--text-soft); text-transform: uppercase; letter-spacing: .06em; background: var(--bg); border-bottom: 1px solid var(--border); }
table.an td { padding: 11px 14px; font-size: 13px; border-bottom: 1px solid var(--border); vertical-align: middle; }
table.an tr:last-child td { border-bottom: 0; }
table.an tbody tr:hover { background: var(--bg); }

.score-bar { display: flex; align-items: center; gap: 10px; min-width: 140px; }
.score-bar .track { flex: 1; height: 7px; background: var(--bg); border-radius: 999px; overflow: hidden; }
.score-bar .fill { height: 100%; border-radius: 999px; }
.score-bar .num { font-weight: 700; font-size: 12px; min-width: 44px; text-align: right; }
.fill.bad   { background: linear-gradient(90deg, #ef4444, #f87171); }
.fill.warn  { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
.fill.good  { background: linear-gradient(90deg, #10b981, #34d399); }

.rank { display: inline-grid; place-items: center; width: 26px; height: 26px; border-radius: 50%; background: var(--bg); font-weight: 700; font-size: 11px; color: var(--text-soft); }
.rank.gold   { background: linear-gradient(135deg, #fbbf24, #f59e0b); color: white; }
.rank.silver { background: linear-gradient(135deg, #cbd5e1, #94a3b8); color: white; }
.rank.bronze { background: linear-gradient(135deg, #f97316, #c2410c); color: white; }

.pill { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; }
.pill.warn { background: rgba(245,158,11,.15); color: #d97706; }
.pill.bad  { background: rgba(239,68,68,.15); color: #ef4444; }
.pill.good { background: rgba(16,185,129,.15); color: #10b981; }
.pill.neutral { background: rgba(99,102,241,.12); color: var(--brand-1); }
.pill.muted { background: rgba(100,116,139,.12); color: var(--muted); }

.trend-card { background: var(--bg-elev); border: 1px solid var(--border); border-radius: 14px; padding: 18px; margin-bottom: 18px; }
.trend-head { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 14px; }
.trend-head h3 { font-size: 14px; font-weight: 700; }
.trend-head .sub { font-size: 11px; color: var(--text-soft); }
.trend-chart { display: flex; align-items: flex-end; gap: 6px; height: 110px; padding-top: 12px; }
.trend-col { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 6px; min-width: 0; }
.trend-bar { width: 100%; max-width: 22px; background: linear-gradient(180deg, var(--brand-1), var(--brand-2)); border-radius: 5px 5px 0 0; min-height: 2px; transition: all .3s; position: relative; }
.trend-bar:hover { opacity: .8; }
.trend-bar:hover::after { content: attr(data-n) ' (' attr(data-pct) '%)'; position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%); background: var(--text); color: var(--bg-elev); padding: 3px 7px; border-radius: 5px; font-size: 10px; font-weight: 700; white-space: nowrap; margin-bottom: 4px; }
.trend-date { font-size: 9px; color: var(--muted); white-space: nowrap; }

.empty-mini { padding: 50px 20px; text-align: center; color: var(--muted); }
.empty-mini i { font-size: 32px; opacity: .4; margin-bottom: 12px; display: block; }

.q-text { font-weight: 600; max-width: 480px; }
.q-meta { font-size: 11px; color: var(--text-soft); margin-top: 3px; }
.q-meta strong { color: var(--text); }

.name-cell { display: flex; align-items: center; gap: 10px; }
.av { width: 30px; height: 30px; border-radius: 50%; background: var(--grad-brand); color: white; font-weight: 700; font-size: 11px; display: grid; place-items: center; flex-shrink: 0; }
</style>

<?php
function scoreClass($pct) { return $pct >= 75 ? 'good' : ($pct >= 50 ? 'warn' : 'bad'); }
function scoreBar($pct) {
    $cls = scoreClass($pct);
    $pct = round((float)$pct, 1);
    echo '<div class="score-bar"><div class="track"><div class="fill ' . $cls . '" style="width:' . max(2, $pct) . '%"></div></div><span class="num">' . $pct . '%</span></div>';
}
?>

<!-- Tab bar -->
<div class="an-tabs">
  <?php
    $TABS = [
      'overview'  => ['fa-chart-pie',   'Overview'],
      'videos'    => ['fa-film',        'Per-video'],
      'questions' => ['fa-circle-question', 'Hardest questions'],
      'users'     => ['fa-user',        'Per-user'],
      'groups'    => ['fa-users',       'Per-group'],
    ];
    $qs = http_build_query(array_filter(['vid'=>$sel_vid?:null,'grp'=>$sel_group?:null,'from'=>$date_from?:null,'to'=>$date_to?:null]));
    foreach ($TABS as $k => $t):
      $href = '?view=' . $k . ($qs ? '&' . $qs : '');
  ?>
    <a class="an-tab <?php echo $view===$k?'active':''; ?>" href="<?php echo $href; ?>">
      <i class="fas <?php echo $t[0]; ?>"></i> <?php echo $t[1]; ?>
    </a>
  <?php endforeach; ?>
</div>

<!-- Filters -->
<form method="get" class="an-filters">
  <input type="hidden" name="view" value="<?php echo $view; ?>">
  <div class="fg">
    <label>Video</label>
    <select name="vid" onchange="this.form.submit()">
      <option value="">All videos</option>
      <?php foreach ($videos as $v): ?>
        <option value="<?php echo (int)$v['id']; ?>" <?php if ($sel_vid === (int)$v['id']) echo 'selected'; ?>><?php echo htmlspecialchars($v['title']); ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="fg">
    <label>Group</label>
    <select name="grp" onchange="this.form.submit()">
      <option value="">All groups</option>
      <?php foreach ($groups as $g): ?>
        <option value="<?php echo htmlspecialchars($g, ENT_QUOTES); ?>" <?php if ($sel_group === $g) echo 'selected'; ?>><?php echo htmlspecialchars($g); ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="fg"><label>From</label><input type="date" name="from" value="<?php echo htmlspecialchars($date_from); ?>"></div>
  <div class="fg"><label>To</label><input type="date" name="to" value="<?php echo htmlspecialchars($date_to); ?>"></div>
  <div class="actions">
    <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply</button>
    <a href="quiz_analytics.php?view=<?php echo $view; ?>" class="btn btn-ghost"><i class="fas fa-rotate-left"></i></a>
    <a href="?view=<?php echo $view; ?>&<?php echo $qs; ?>&export=csv" class="btn btn-ghost" title="Export to CSV"><i class="fas fa-file-csv"></i> CSV</a>
  </div>
</form>

<!-- ════════ OVERVIEW ════════ -->
<?php if ($view === 'overview'): ?>
  <?php
    $total = (int)($overview['total'] ?? 0);
    $corr  = (int)($overview['correct'] ?? 0);
    $pct   = $total > 0 ? round($corr / $total * 100, 1) : 0;
  ?>
  <div class="kpi-grid">
    <div class="kpi">
      <div class="kpi-ic" style="background: linear-gradient(135deg,#6366f1,#8b5cf6);"><i class="fas fa-circle-check"></i></div>
      <div class="kpi-label">Total responses</div>
      <div class="kpi-value"><?php echo number_format($total); ?></div>
    </div>
    <div class="kpi">
      <div class="kpi-ic" style="background: linear-gradient(135deg,#10b981,#059669);"><i class="fas fa-percent"></i></div>
      <div class="kpi-label">Pass rate</div>
      <div class="kpi-value"><?php echo $pct; ?><span class="kpi-suffix">%</span></div>
      <div class="kpi-sub"><?php echo number_format($corr); ?> correct of <?php echo number_format($total); ?></div>
    </div>
    <div class="kpi">
      <div class="kpi-ic" style="background: linear-gradient(135deg,#0ea5e9,#06b6d4);"><i class="fas fa-user"></i></div>
      <div class="kpi-label">Unique users</div>
      <div class="kpi-value"><?php echo number_format((int)($overview['users'] ?? 0)); ?></div>
    </div>
    <div class="kpi">
      <div class="kpi-ic" style="background: linear-gradient(135deg,#ec4899,#f43f5e);"><i class="fas fa-film"></i></div>
      <div class="kpi-label">Videos covered</div>
      <div class="kpi-value"><?php echo (int)($overview['videos'] ?? 0); ?></div>
    </div>
    <div class="kpi">
      <div class="kpi-ic" style="background: linear-gradient(135deg,#f59e0b,#d97706);"><i class="fas fa-users"></i></div>
      <div class="kpi-label">Groups</div>
      <div class="kpi-value"><?php echo (int)($overview['groups'] ?? 0); ?></div>
    </div>
  </div>

  <?php if (!empty($trend)): ?>
  <div class="trend-card">
    <div class="trend-head">
      <h3><i class="fas fa-chart-line" style="color:var(--brand-1);margin-right:6px;"></i> Last 14 days</h3>
      <span class="sub">Quiz responses per day</span>
    </div>
    <?php
      $max_n = max(array_column($trend, 'n'));
      // pad to 14 days
      $by_date = array_column($trend, null, 'd');
    ?>
    <div class="trend-chart">
      <?php for ($i = 13; $i >= 0; $i--):
        $d = date('Y-m-d', strtotime("-$i days"));
        $n = (int)($by_date[$d]['n'] ?? 0);
        $c = (int)($by_date[$d]['c'] ?? 0);
        $h = $max_n > 0 ? max(2, round($n / $max_n * 100)) : 2;
        $dpct = $n > 0 ? round($c / $n * 100) : 0;
      ?>
        <div class="trend-col">
          <div class="trend-bar" style="height: <?php echo $h; ?>%" data-n="<?php echo $n; ?>" data-pct="<?php echo $dpct; ?>"></div>
          <div class="trend-date"><?php echo date('M j', strtotime($d)); ?></div>
        </div>
      <?php endfor; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($total === 0): ?>
    <div class="tbl-wrap"><div class="empty-mini"><i class="fas fa-chart-bar"></i>No quiz responses yet for the current filters.</div></div>
  <?php endif; ?>

<!-- ════════ PER-VIDEO ════════ -->
<?php elseif ($view === 'videos'): ?>
<div class="tbl-wrap">
  <table class="an">
    <thead><tr><th>#</th><th>Video</th><th>Attempts</th><th>Unique users</th><th>Pass rate</th></tr></thead>
    <tbody>
    <?php if (empty($per_video)): ?>
      <tr><td colspan="5"><div class="empty-mini"><i class="fas fa-film"></i>No data.</div></td></tr>
    <?php else: foreach ($per_video as $i => $v): ?>
      <tr>
        <td><span class="rank"><?php echo $i + 1; ?></span></td>
        <td><strong><?php echo htmlspecialchars($v['title']); ?></strong> <span style="color:var(--muted);font-size:11px;">#<?php echo (int)$v['id']; ?></span></td>
        <td><?php echo number_format($v['attempts']); ?></td>
        <td><?php echo number_format($v['unique_users']); ?></td>
        <td><?php scoreBar($v['pct']); ?></td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<!-- ════════ HARDEST QUESTIONS ════════ -->
<?php elseif ($view === 'questions'): ?>
<div class="tbl-wrap">
  <table class="an">
    <thead><tr><th>#</th><th>Question</th><th>Attempts</th><th>Pass rate</th></tr></thead>
    <tbody>
    <?php if (empty($hardest)): ?>
      <tr><td colspan="4"><div class="empty-mini"><i class="fas fa-circle-question"></i>No questions answered yet.</div></td></tr>
    <?php else: foreach ($hardest as $i => $q):
      $rank = $i + 1;
      $rank_class = $rank === 1 ? 'gold' : ($rank === 2 ? 'silver' : ($rank === 3 ? 'bronze' : ''));
    ?>
      <tr>
        <td><span class="rank <?php echo $rank_class; ?>"><?php echo $rank; ?></span></td>
        <td>
          <div class="q-text"><?php echo htmlspecialchars($q['question_text']); ?></div>
          <div class="q-meta"><strong><?php echo htmlspecialchars($q['video_title']); ?></strong> · <?php echo htmlspecialchars($q['group_label']); ?> @ <?php echo fmtT($q['trigger_time']); ?></div>
        </td>
        <td><?php echo number_format($q['attempts']); ?></td>
        <td><?php scoreBar($q['pct']); ?></td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
  <?php if (!empty($hardest)): ?><div style="padding:10px 14px;font-size:11px;color:var(--muted);border-top:1px solid var(--border);">Sorted hardest first. These are the questions your audience struggles with most — consider reviewing the source video or rewording.</div><?php endif; ?>
</div>

<!-- ════════ PER-USER ════════ -->
<?php elseif ($view === 'users'): ?>
<div class="tbl-wrap">
  <table class="an">
    <thead><tr><th>#</th><th>User</th><th>Group</th><th>Attempts</th><th>Videos</th><th>Pass rate</th><th>Last active</th></tr></thead>
    <tbody>
    <?php if (empty($per_user)): ?>
      <tr><td colspan="7"><div class="empty-mini"><i class="fas fa-user"></i>No user activity yet.</div></td></tr>
    <?php else: foreach ($per_user as $i => $u):
      $rank = $i + 1;
      $rank_class = $rank === 1 ? 'gold' : ($rank === 2 ? 'silver' : ($rank === 3 ? 'bronze' : ''));
      $init = strtoupper(mb_substr($u['user_name'], 0, 1));
    ?>
      <tr>
        <td><span class="rank <?php echo $rank_class; ?>"><?php echo $rank; ?></span></td>
        <td><div class="name-cell"><div class="av"><?php echo htmlspecialchars($init); ?></div><div><strong><?php echo htmlspecialchars($u['user_name']); ?></strong></div></div></td>
        <td><?php if (!empty($u['group_name'])): ?><span class="pill neutral"><?php echo htmlspecialchars($u['group_name']); ?></span><?php else: ?><span class="pill muted">none</span><?php endif; ?></td>
        <td><?php echo number_format($u['attempts']); ?></td>
        <td><?php echo number_format($u['videos']); ?></td>
        <td><?php scoreBar($u['pct']); ?></td>
        <td style="font-size:12px;color:var(--text-soft);"><?php echo $u['last_at'] ? date('M j, H:i', strtotime($u['last_at'])) : '—'; ?></td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<!-- ════════ PER-GROUP ════════ -->
<?php elseif ($view === 'groups'): ?>
<div class="tbl-wrap">
  <table class="an">
    <thead><tr><th>#</th><th>Group</th><th>Users</th><th>Attempts</th><th>Pass rate</th></tr></thead>
    <tbody>
    <?php if (empty($per_group)): ?>
      <tr><td colspan="5"><div class="empty-mini"><i class="fas fa-users"></i>No groups have responses yet.</div></td></tr>
    <?php else: foreach ($per_group as $i => $g):
      $rank = $i + 1;
      $rank_class = $rank === 1 ? 'gold' : ($rank === 2 ? 'silver' : ($rank === 3 ? 'bronze' : ''));
    ?>
      <tr>
        <td><span class="rank <?php echo $rank_class; ?>"><?php echo $rank; ?></span></td>
        <td><strong><?php echo htmlspecialchars($g['group_name']); ?></strong></td>
        <td><?php echo number_format($g['users']); ?></td>
        <td><?php echo number_format($g['attempts']); ?></td>
        <td><?php scoreBar($g['pct']); ?></td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

    </main>
  </div>
</div>
</body>
</html>