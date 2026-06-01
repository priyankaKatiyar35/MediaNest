<?php
require_once __DIR__ . '/../auth/auth.php';
requireLogin();
include '../admin/config.php';

$video_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$video_id) { header('Location: index.php'); exit; }

$res   = mysqli_query($conn, "SELECT * FROM video WHERE id=$video_id");
$video = mysqli_fetch_assoc($res);
if (!$video) { header('Location: index.php'); exit; }

$qq   = "SELECT * FROM video_quizzes WHERE video_id=$video_id ORDER BY trigger_time ASC";
$qres = mysqli_query($conn, $qq);
$quizzes = [];
while ($qrow = mysqli_fetch_assoc($qres)) {
    $oid  = intval($qrow['id']);
    $ores = mysqli_query($conn, "SELECT * FROM quiz_options WHERE quiz_id=$oid ORDER BY id ASC");
    $opts = [];
    while ($o = mysqli_fetch_assoc($ores)) {
        $o['correct_option'] = intval($o['correct_option']);
        $opts[] = $o;
    }
    $qrow['options'] = $opts;
    $quizzes[] = $qrow;
}

$quizzes_json = json_encode($quizzes);
$video_name   = htmlspecialchars($video['name']);
$video_title  = htmlspecialchars($video['title']);
$video_des    = htmlspecialchars($video['des']);
$total_checkpoints = count($quizzes);
$is_training  = $total_checkpoints > 0;

$user = currentUser();
$logged_in = $user !== null;

$passed_ids = $logged_in ? getPassedQuizzes($video_id) : [];
$passed_count = count($passed_ids);

// Continue Watching — load saved position
$saved_position = 0;
$saved_pct = 0;
if ($logged_in) {
    $uid = (int)$user['id'];
    $stmt = mysqli_prepare($conn, "SELECT last_position, progress_pct, completed FROM video_progress WHERE user_id=? AND video_id=?");
    mysqli_stmt_bind_param($stmt, 'ii', $uid, $video_id);
    mysqli_stmt_execute($stmt);
    $r = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if ($r && !$r['completed']) {
        $saved_position = (float)$r['last_position'];
        $saved_pct      = (int)$r['progress_pct'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $video_title; ?> — MediaNest</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/js/all.min.js" defer></script>

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
  --training-1: #6366f1; --training-2: #8b5cf6;
  --training-soft: rgba(99, 102, 241, 0.1);
  --event-1: #f59e0b;
  --green: #10b981; --red: #ef4444; --gold: #f59e0b;
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
html { scroll-behavior: smooth; }
body { font-family: 'Inter', sans-serif; color: var(--text); background: var(--bg); min-height: 100vh; transition: background .4s, color .4s; }
h1, h2, h3, h4 { font-family: 'Sora', sans-serif; letter-spacing: -0.02em; }
a { color: inherit; text-decoration: none; }
button { font-family: inherit; }

.mn-nav { position: sticky; top: 0; z-index: 50; backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); background: color-mix(in srgb, var(--bg) 75%, transparent); border-bottom: 1px solid var(--border); }
.mn-nav-inner { max-width: 1280px; margin: 0 auto; padding: 14px 24px; display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.mn-logo { display: flex; align-items: center; gap: 10px; font-family: 'Sora', sans-serif; font-weight: 700; font-size: 20px; }
.mn-logo-mark { width: 36px; height: 36px; border-radius: 10px; background: var(--grad-brand); display: grid; place-items: center; color: white; box-shadow: 0 6px 18px rgba(6, 182, 212, 0.4); }
.mn-logo-text span { background: var(--grad-text); -webkit-background-clip: text; background-clip: text; color: transparent; }
.nav-links { display: flex; gap: 4px; }
.nav-links a { padding: 8px 14px; border-radius: 8px; font-size: 14px; font-weight: 500; color: var(--text-soft); transition: all .2s ease; }
.nav-links a:hover { background: var(--bg-elev); color: var(--text); }
.nav-links a.active { background: var(--grad-brand); color: white; box-shadow: 0 4px 12px rgba(6, 182, 212, 0.3); }
.mn-icon-btn { width: 40px; height: 40px; border-radius: 10px; background: transparent; border: 1px solid var(--border); color: var(--text); cursor: pointer; display: grid; place-items: center; transition: all .2s ease; }
.mn-icon-btn:hover { background: var(--bg-elev); transform: translateY(-1px); }
.nav-right { display: flex; align-items: center; gap: 8px; }
.user-chip { display: inline-flex; align-items: center; gap: 8px; padding: 6px 6px 6px 12px; border-radius: 999px; background: var(--bg-elev); border: 1px solid var(--border); font-size: 13px; font-weight: 500; box-shadow: var(--shadow-sm); }
.user-chip .av { width: 28px; height: 28px; border-radius: 50%; background: linear-gradient(135deg, var(--training-1), var(--training-2)); color: white; font-weight: 700; font-size: 12px; display: grid; place-items: center; }
.btn-signin { display: inline-flex; align-items: center; gap: 7px; padding: 8px 14px; border-radius: 10px; background: var(--grad-brand); color: white; font-size: 13px; font-weight: 600; box-shadow: 0 4px 12px rgba(6, 182, 212, 0.3); }

.page-wrap { max-width: 1280px; margin: 0 auto; padding: 28px 24px 60px; display: grid; grid-template-columns: 1fr 320px; gap: 28px; }
.main-col { min-width: 0; } .side-col { min-width: 0; }
@media (max-width: 1020px) { .page-wrap { grid-template-columns: 1fr; } .side-col { order: -1; } }

.back-link { display: inline-flex; align-items: center; gap: 8px; padding: 8px 14px; border-radius: 10px; background: var(--bg-elev); border: 1px solid var(--border); color: var(--text-soft); font-size: 13px; font-weight: 500; margin-bottom: 18px; transition: all .2s; box-shadow: var(--shadow-sm); }
.back-link:hover { color: var(--text); transform: translateX(-2px); }
.video-type-pill { display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 999px; font-family: 'Sora', sans-serif; font-size: 11px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; margin-bottom: 12px; }
.video-type-pill.training { background: var(--training-soft); color: var(--training-1); }
.video-type-pill.event { background: rgba(245, 158, 11, 0.12); color: var(--event-1); }
.video-title { font-size: clamp(22px, 3vw, 32px); font-weight: 800; line-height: 1.15; margin-bottom: 8px; }
.video-desc { color: var(--text-soft); font-size: 15px; line-height: 1.65; margin-bottom: 20px; }

/* AI Summarize button + panel */
.ai-summary-row { display: flex; align-items: center; gap: 12px; margin-bottom: 18px; flex-wrap: wrap; }
.ai-sum-btn { display: inline-flex; align-items: center; gap: 9px; padding: 9px 16px 9px 9px; border-radius: 999px; border: 1px solid rgba(168,85,247,.3); background: linear-gradient(135deg, rgba(168,85,247,.08), rgba(236,72,153,.08)); color: #a855f7; font: inherit; font-size: 13px; font-weight: 600; cursor: pointer; transition: all .2s; }
.ai-sum-btn:hover { background: linear-gradient(135deg, #a855f7, #ec4899); color: white; border-color: transparent; transform: translateY(-1px); box-shadow: 0 8px 22px rgba(168,85,247,.35); }
.ai-sum-btn:disabled { opacity: .6; cursor: not-allowed; transform: none; box-shadow: none; }
.ai-sum-spark { width: 26px; height: 26px; border-radius: 50%; background: linear-gradient(135deg, #a855f7, #ec4899); color: white; display: grid; place-items: center; font-size: 11px; }
.ai-sum-btn:hover .ai-sum-spark { background: rgba(255,255,255,.25); }
.ai-sum-spark .spin { animation: ai-spin 1s linear infinite; }
@keyframes ai-spin { to { transform: rotate(360deg); } }
.ai-sum-hint { font-size: 12px; color: var(--muted); }

.ai-summary-panel { display: none; background: var(--bg-elev); border: 1px solid var(--border); border-radius: 16px; margin-bottom: 24px; overflow: hidden; box-shadow: 0 12px 30px rgba(0,0,0,.06); animation: ai-slide .3s ease; }
.ai-summary-panel.open { display: block; }
@keyframes ai-slide { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }
.ai-sum-head { padding: 14px 18px; display: flex; align-items: center; gap: 12px; background: linear-gradient(135deg, rgba(168,85,247,.08), rgba(236,72,153,.05)); border-bottom: 1px solid var(--border); }
.ai-sum-title { font-weight: 700; font-size: 14px; display: inline-flex; align-items: center; gap: 8px; color: #a855f7; flex-shrink: 0; }
.ai-sum-meta { font-size: 11px; color: var(--muted); flex: 1; }
.ai-sum-close { width: 28px; height: 28px; border-radius: 7px; background: transparent; border: 1px solid var(--border); color: var(--text-soft); cursor: pointer; display: grid; place-items: center; transition: all .15s; }
.ai-sum-close:hover { color: #ef4444; border-color: #ef4444; }
.ai-sum-body { padding: 18px 20px; }
.ai-sum-text { font-size: 14px; line-height: 1.65; color: var(--text); margin-bottom: 16px; }
.ai-sum-text.loading { color: var(--muted); font-style: italic; }
.ai-sum-topics-wrap { display: none; }
.ai-sum-topics-wrap.show { display: block; }
.ai-sum-topics-wrap h4 { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: var(--text-soft); margin-bottom: 8px; }
.ai-sum-topics { list-style: none; padding: 0; display: flex; flex-direction: column; gap: 6px; }
.ai-sum-topics li { display: flex; align-items: flex-start; gap: 10px; padding: 8px 12px; border-radius: 9px; background: var(--bg); font-size: 13px; line-height: 1.4; }
.ai-sum-topics li::before { content: '\f111'; font-family: 'Font Awesome 6 Free'; font-weight: 900; font-size: 5px; color: #a855f7; margin-top: 8px; flex-shrink: 0; }
.ai-sum-error { padding: 12px 14px; border-radius: 10px; background: rgba(239,68,68,.1); border: 1px solid rgba(239,68,68,.3); color: #ef4444; font-size: 13px; display: flex; align-items: flex-start; gap: 10px; }

.resume-banner { display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-radius: 12px; background: var(--training-soft); border: 1px solid color-mix(in srgb, var(--training-1) 25%, transparent); margin-bottom: 16px; font-size: 13px; color: var(--training-1); font-weight: 500; }
.resume-banner i { font-size: 16px; }
.resume-banner strong { color: var(--text); }

.player-wrap { position: relative; background: #000; border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-lg); border: 1px solid var(--border); }
#mainVideo { width: 100%; display: block; background: #000; aspect-ratio: 16/9; }
#controlBar { position: absolute; bottom: 0; left: 0; right: 0; background: linear-gradient(transparent, rgba(0,0,0,0.85)); padding: 40px 18px 14px; display: flex; align-items: center; gap: 12px; z-index: 10; opacity: 0; transition: opacity .25s; }
.cb { background: rgba(255,255,255,0.12); border: none; color: white; font-size: 14px; cursor: pointer; width: 38px; height: 38px; border-radius: 10px; display: grid; place-items: center; flex-shrink: 0; transition: background .2s, transform .15s; backdrop-filter: blur(8px); }
.cb:hover { background: rgba(255,255,255,0.22); transform: scale(1.05); }
.cb:active { transform: scale(0.95); }
#seekBar { flex: 1; height: 5px; cursor: pointer; accent-color: var(--brand-1); }
#timeDisp { color: white; font-size: 13px; font-weight: 500; white-space: nowrap; min-width: 100px; text-align: right; font-variant-numeric: tabular-nums; }

#userGate { display: flex; position: absolute; inset: 0; z-index: 8000; background: rgba(2, 6, 23, 0.92); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); align-items: center; justify-content: center; padding: 24px; }
.gate-card { background: var(--bg-elev); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 36px 38px 32px; width: 100%; max-width: 460px; box-shadow: var(--shadow-lg); animation: popIn .4s cubic-bezier(.22,.61,.36,1) both; }
@keyframes popIn { from { opacity: 0; transform: scale(.93); } to { opacity: 1; transform: none; } }
.gate-icon { width: 60px; height: 60px; margin: 0 auto 16px; border-radius: 16px; background: linear-gradient(135deg, var(--training-1), var(--training-2)); display: grid; place-items: center; color: white; font-size: 24px; box-shadow: 0 10px 28px rgba(99, 102, 241, 0.35); }
.gate-title { font-size: 22px; font-weight: 700; text-align: center; margin-bottom: 6px; }
.gate-sub { font-size: 14px; color: var(--text-soft); text-align: center; margin-bottom: 22px; line-height: 1.55; }
.gate-field { margin-bottom: 14px; }
.gate-field label { display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--text-soft); margin-bottom: 6px; }
.gate-field input { width: 100%; padding: 12px 14px; background: var(--bg); border: 1.5px solid var(--border); border-radius: 10px; font-family: inherit; font-size: 14px; color: var(--text); outline: none; transition: border-color .2s, box-shadow .2s; }
.gate-field input:focus { border-color: var(--training-1); box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.12); }
.gate-btn { width: 100%; padding: 13px; background: linear-gradient(135deg, var(--training-1), var(--training-2)); color: white; font-size: 14px; font-weight: 600; border: none; border-radius: 10px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 8px; transition: transform .2s, box-shadow .2s; box-shadow: 0 6px 18px rgba(99, 102, 241, 0.35); margin-top: 6px; }
.gate-btn:hover { transform: translateY(-2px); box-shadow: 0 10px 24px rgba(99, 102, 241, 0.45); }
.gate-divider { text-align: center; margin: 14px 0 12px; font-size: 11px; color: var(--muted); font-weight: 600; text-transform: uppercase; letter-spacing: .1em; position: relative; }
.gate-divider::before, .gate-divider::after { content: ''; position: absolute; top: 50%; width: 35%; height: 1px; background: var(--border); }
.gate-divider::before { left: 0; } .gate-divider::after { right: 0; }
.gate-login-link { display: block; text-align: center; padding: 11px; background: var(--bg); border: 1.5px solid var(--border); border-radius: 10px; color: var(--text); font-size: 14px; font-weight: 600; transition: all .2s; }
.gate-login-link:hover { border-color: var(--brand-2); color: var(--brand-2); }
.gate-err { font-size: 13px; color: var(--red); margin-top: 10px; text-align: center; display: none; }

#quizOverlay { display: none; position: absolute; inset: 0; z-index: 7000; background: rgba(2, 6, 23, 0.85); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); align-items: center; justify-content: center; padding: 24px; overflow-y: auto; }
#quizOverlay.active { display: flex; }
.player-wrap:-webkit-full-screen #quizOverlay, .player-wrap:fullscreen #quizOverlay { position: absolute; inset: 0; width: 100%; height: 100%; }
.quiz-card { background: var(--bg-elev); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 30px 34px; width: 100%; max-width: 580px; box-shadow: var(--shadow-lg); animation: slideUp .3s cubic-bezier(.22,.61,.36,1) both; }
@keyframes slideUp { from { opacity: 0; transform: translateY(20px) scale(.97); } to { opacity: 1; transform: none; } }
.quiz-badge { display: inline-flex; align-items: center; gap: 7px; background: var(--training-soft); color: var(--training-1); font-family: 'Sora', sans-serif; font-size: 11px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; padding: 5px 12px; border-radius: 999px; margin-bottom: 10px; }
.quiz-badge::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: var(--training-1); animation: blink 1.4s infinite; }
@keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0.3; } }
.quiz-topic { font-size: 13px; color: var(--text-soft); margin-bottom: 12px; font-weight: 500; }
.quiz-progress { display: flex; gap: 6px; margin-bottom: 18px; }
.q-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--border); transition: all .3s; }
.q-dot.done { background: var(--green); }
.q-dot.current { background: var(--training-1); transform: scale(1.3); box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.18); }
.quiz-question { font-family: 'Sora', sans-serif; font-size: clamp(16px, 2.3vw, 19px); font-weight: 700; color: var(--text); margin-bottom: 20px; line-height: 1.4; }
.quiz-options { display: flex; flex-direction: column; gap: 9px; margin-bottom: 16px; }
.quiz-option { background: var(--bg); border: 1.5px solid var(--border); border-radius: 12px; padding: 13px 16px; cursor: pointer; font-size: 14px; color: var(--text); transition: all .2s; text-align: left; font-family: inherit; display: flex; align-items: center; gap: 12px; width: 100%; }
.quiz-option::before { content: attr(data-letter); font-family: 'Sora', sans-serif; font-weight: 700; font-size: 12px; width: 26px; height: 26px; border-radius: 7px; background: var(--bg-elev); border: 1px solid var(--border); display: grid; place-items: center; color: var(--text-soft); flex-shrink: 0; transition: all .2s; }
.quiz-option:hover:not(.locked) { border-color: var(--training-1); background: var(--training-soft); }
.quiz-option:hover:not(.locked)::before { background: var(--training-1); color: white; border-color: var(--training-1); }
.quiz-option.correct { border-color: var(--green); background: rgba(16, 185, 129, 0.08); }
.quiz-option.correct::before { background: var(--green); color: white; border-color: var(--green); }
.quiz-option.wrong { border-color: var(--red); background: rgba(239, 68, 68, 0.06); color: var(--text-soft); }
.quiz-option.wrong::before { background: var(--red); color: white; border-color: var(--red); }
.quiz-option.locked { cursor: default; }
.feedback-bar { border-radius: 10px; padding: 12px 14px; font-size: 13px; font-weight: 500; display: none; align-items: flex-start; gap: 10px; margin-bottom: 14px; line-height: 1.5; }
.feedback-bar.show { display: flex; }
.feedback-bar.ok { background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.25); color: var(--green); }
.feedback-bar.ng { background: rgba(239, 68, 68, 0.06); border: 1px solid rgba(239, 68, 68, 0.25); color: var(--red); }
.fb-icon { font-size: 16px; flex-shrink: 0; }
.fb-text-wrap { flex: 1; }
.fb-text { font-weight: 600; }
.fb-expl { font-size: 12px; color: var(--text-soft); margin-top: 3px; }
.score-result { display: none; text-align: center; margin-bottom: 14px; }
.score-result.show { display: block; }
.score-circle { width: 92px; height: 92px; border-radius: 50%; margin: 0 auto 12px; display: grid; place-items: center; font-family: 'Sora', sans-serif; font-size: 26px; font-weight: 800; border: 3px solid; }
.score-circle.pass { border-color: var(--green); background: rgba(16, 185, 129, 0.08); color: var(--green); }
.score-circle.fail { border-color: var(--red); background: rgba(239, 68, 68, 0.06); color: var(--red); }
.score-title { font-family: 'Sora', sans-serif; font-size: 18px; font-weight: 700; margin-bottom: 6px; }
.score-title.pass { color: var(--green); } .score-title.fail { color: var(--red); }
.score-msg { font-size: 13px; color: var(--text-soft); line-height: 1.6; }
.retry-cd { font-size: 13px; color: var(--gold); margin-top: 8px; font-weight: 600; }
.btn-continue, .btn-retry { display: none; width: 100%; padding: 13px; font-family: inherit; font-size: 14px; font-weight: 600; border: none; border-radius: 10px; cursor: pointer; transition: transform .2s, box-shadow .2s; align-items: center; justify-content: center; gap: 8px; }
.btn-continue { background: linear-gradient(135deg, var(--training-1), var(--training-2)); color: white; box-shadow: 0 6px 18px rgba(99, 102, 241, 0.35); }
.btn-continue:hover { transform: translateY(-2px); box-shadow: 0 10px 24px rgba(99, 102, 241, 0.45); }
.btn-continue.show { display: flex; }
.btn-retry { background: rgba(245, 158, 11, 0.12); color: var(--gold); border: 1px solid rgba(245, 158, 11, 0.3); margin-top: 9px; }
.btn-retry:hover { background: rgba(245, 158, 11, 0.2); }
.btn-retry.show { display: flex; }

.side-card { background: var(--bg-elev); border: 1px solid var(--border); border-radius: var(--radius); padding: 22px; box-shadow: var(--shadow-sm); margin-bottom: 16px; }
.side-card h3 { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--text-soft); margin-bottom: 14px; display: flex; align-items: center; gap: 8px; }
.side-card h3 i { color: var(--training-1); }
.cp-list { display: flex; flex-direction: column; gap: 10px; }
.cp-item { display: flex; align-items: center; gap: 12px; padding: 12px; background: var(--bg); border: 1px solid var(--border); border-radius: 10px; transition: all .2s; position: relative; }
.cp-item.passed { border-color: var(--green); background: rgba(16, 185, 129, 0.06); }
.cp-item.active { border-color: var(--training-1); background: var(--training-soft); }
.cp-num { width: 32px; height: 32px; border-radius: 8px; background: var(--bg-elev); border: 1px solid var(--border); display: grid; place-items: center; font-family: 'Sora', sans-serif; font-weight: 700; font-size: 12px; color: var(--text-soft); flex-shrink: 0; }
.cp-item.passed .cp-num { background: var(--green); color: white; border-color: var(--green); }
.cp-item.active .cp-num { background: var(--training-1); color: white; border-color: var(--training-1); }
.cp-info { flex: 1; min-width: 0; }
.cp-label { font-size: 13px; font-weight: 600; color: var(--text); }
.cp-time { font-size: 11px; color: var(--muted); margin-top: 1px; font-variant-numeric: tabular-nums; }
.cp-status { font-size: 14px; color: var(--muted); }
.cp-item.passed .cp-status { color: var(--green); }
.progress-summary { display: flex; align-items: center; justify-content: space-between; padding: 14px; background: var(--bg); border-radius: 10px; margin-bottom: 14px; }
.progress-summary .lbl { font-size: 12px; color: var(--text-soft); font-weight: 500; }
.progress-summary .val { font-family: 'Sora', sans-serif; font-size: 22px; font-weight: 800; background: var(--grad-text); -webkit-background-clip: text; background-clip: text; color: transparent; }
.viewer-info { display: flex; align-items: center; gap: 12px; }
.viewer-avatar { width: 42px; height: 42px; border-radius: 12px; background: linear-gradient(135deg, var(--training-1), var(--training-2)); display: grid; place-items: center; color: white; font-weight: 700; font-size: 16px; flex-shrink: 0; }
.viewer-text { flex: 1; min-width: 0; }
.viewer-name { font-size: 14px; font-weight: 600; color: var(--text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.viewer-group { font-size: 12px; color: var(--text-soft); margin-top: 1px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.viewer-logout { display: inline-flex; align-items: center; gap: 6px; margin-top: 12px; font-size: 12px; color: var(--text-soft); padding: 6px 10px; border-radius: 8px; background: var(--bg); border: 1px solid var(--border); transition: all .2s; }
.viewer-logout:hover { color: var(--red); border-color: var(--red); }

.toast { position: fixed; bottom: 28px; left: 50%; transform: translateX(-50%) translateY(20px); background: var(--text); color: var(--bg); padding: 12px 22px; border-radius: 12px; font-size: 14px; font-weight: 500; box-shadow: var(--shadow-lg); z-index: 9999; opacity: 0; transition: opacity .25s, transform .25s; }
.toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }

@media (max-width: 800px) {
  .nav-links { display: none; }
  .page-wrap { padding: 18px 16px 50px; }
  .gate-card { padding: 28px 22px 24px; }
  .quiz-card { padding: 24px 22px; }
}
</style>
</head>
<body>

<nav class="mn-nav">
  <div class="mn-nav-inner">
    <a href="../index.html" class="mn-logo">
      <span class="mn-logo-mark"><i class="fas fa-cube"></i></span>
      <span class="mn-logo-text">Media<span>Nest</span></span>
    </a>
       <div class="nav-links">
      <a href="../index.php"><i class="fas fa-house"></i> Home</a>
   

      <a href="index.php" class="nav-link active"><i class="fas fa-video"></i> Videos</a>
      <a href="../Photo/index.php" class=""><i class="fas fa-images"></i> Photos</a>
      <a href="../Documents/index.php"><i class="fas fa-folder-open"></i> Documents</a>

            <a href="../Bookmarks/index.php">
  <i class="fas fa-bookmark"></i> Bookmarks</a>
    </div>
    <div class="nav-right">
      <?php if ($logged_in): ?>
        <div class="user-chip">
          <span><?php echo htmlspecialchars($user['full_name']); ?></span>
          <span class="av"><?php echo strtoupper(substr($user['full_name'], 0, 1)); ?></span>
        </div>
        <a href="../auth/logout.php" class="mn-icon-btn" title="Sign out"><i class="fas fa-arrow-right-from-bracket"></i></a>
      <?php else: ?>
        <a href="../auth/login.php?return=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="btn-signin">
          <i class="fas fa-arrow-right-to-bracket"></i> Sign in
        </a>
      <?php endif; ?>
      <button id="theme-toggle" class="mn-icon-btn" title="Toggle theme"><i class="fas fa-moon"></i></button>
    </div>
  </div>
</nav>

<div class="page-wrap">

  <div class="main-col">
    <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to library</a>

    <?php if ($is_training): ?>
      <span class="video-type-pill training">
        <i class="fas fa-graduation-cap"></i> Training · <?php echo $total_checkpoints; ?> checkpoint<?php echo $total_checkpoints > 1 ? 's' : ''; ?>
      </span>
    <?php else: ?>
      <span class="video-type-pill event"><i class="fas fa-tower-broadcast"></i> Event</span>
    <?php endif; ?>

    <h1 class="video-title" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
      <span style="flex:1;min-width:0;"><?php echo $video_title; ?></span>
      <?php
        $bm_type = 'video';
        $bm_id   = (int)$video_id;
        include __DIR__ . '/../auth/bookmark_btn.php';
      ?>
    </h1>
    <p class="video-desc"><?php echo $video_des; ?></p>

    <!-- AI Summarize button -->
    <div class="ai-summary-row">
      <button type="button" class="ai-sum-btn" id="aiSumBtn">
        <span class="ai-sum-spark"><i class="fas fa-wand-magic-sparkles"></i></span>
        <span class="ai-sum-label">Summarize this video</span>
      </button>
      <span class="ai-sum-hint" id="aiSumHint">Get an AI overview & key topics in 5 seconds</span>
    </div>

    <!-- AI Summary panel (collapsed by default) -->
    <div class="ai-summary-panel" id="aiSumPanel">
      <div class="ai-sum-head">
        <div class="ai-sum-title"><i class="fas fa-wand-magic-sparkles"></i> Summary</div>
        <div class="ai-sum-meta" id="aiSumMeta"></div>
        <button type="button" class="ai-sum-close" id="aiSumClose"><i class="fas fa-xmark"></i></button>
      </div>
      <div class="ai-sum-body">
        <div class="ai-sum-text" id="aiSumText"></div>
        <div class="ai-sum-topics-wrap" id="aiSumTopicsWrap">
          <h4>Key topics</h4>
          <ul class="ai-sum-topics" id="aiSumTopics"></ul>
        </div>
      </div>
    </div>

    <?php if ($logged_in && $is_training && $passed_count > 0): ?>
      <div class="resume-banner">
        <i class="fas fa-bookmark"></i>
        <div>Welcome back, <strong><?php echo htmlspecialchars($user['full_name']); ?></strong> — you've already cleared <?php echo $passed_count; ?> of <?php echo $total_checkpoints; ?> checkpoints. Those will be skipped automatically.</div>
      </div>
    <?php endif; ?>

    <div class="player-wrap" id="playerWrap">
      <video id="mainVideo" controlsList="nodownload" playsinline>
        <source src="../admin/upload/<?php echo $video_name; ?>">
      </video>

      <div id="controlBar">
        <button class="cb" onclick="togglePlay()" id="playBtn"><i class="fas fa-play"></i></button>
        <input type="range" id="seekBar" value="0" min="0" step="0.1">
        <span id="timeDisp">0:00 / 0:00</span>
        <button class="cb" onclick="toggleMute()" id="muteBtn"><i class="fas fa-volume-high"></i></button>
        <button class="cb" onclick="toggleFS()" id="fsBtn"><i class="fas fa-expand"></i></button>
      </div>

      <?php if ($is_training && !$logged_in): ?>
      <div id="userGate">
        <div class="gate-card">
          <div class="gate-icon"><i class="fas fa-graduation-cap"></i></div>
          <div class="gate-title">Before you start</div>
          <div class="gate-sub">Enter your name and group so your checkpoint responses can be recorded.</div>

          <div class="gate-field">
            <label>Your Full Name</label>
            <input type="text" id="gName" placeholder="e.g. Rahul Sharma" autocomplete="name">
          </div>
          <div class="gate-field">
            <label>Group / Batch</label>
            <input type="text" id="gGroup" placeholder="e.g. Batch A — 2024">
          </div>

          <button class="gate-btn" onclick="startVideo()"><i class="fas fa-play"></i> Start Watching</button>
          <div class="gate-err" id="gateErr">Please fill in both fields.</div>

          <div class="gate-divider">or</div>
          <a class="gate-login-link" href="../auth/login.php?return=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">
            <i class="fas fa-arrow-right-to-bracket"></i> Sign in to save your progress
          </a>
        </div>
      </div>
      <?php endif; ?>

      <div id="quizOverlay">
        <div class="quiz-card">
          <div class="quiz-badge"><i class="fas fa-bullseye"></i> Checkpoint</div>
          <div class="quiz-topic" id="quizTopic"></div>
          <div class="quiz-progress" id="quizProgress"></div>
          <div class="quiz-question" id="quizQuestion"></div>
          <div class="quiz-options" id="quizOptions"></div>
          <div class="feedback-bar" id="feedbackBar">
            <span class="fb-icon" id="fbIcon"></span>
            <div class="fb-text-wrap"><div class="fb-text" id="fbText"></div><div class="fb-expl" id="fbExpl"></div></div>
          </div>
          <div class="score-result" id="scoreResult">
            <div class="score-circle" id="scoreCircle"></div>
            <div class="score-title" id="scoreTitle"></div>
            <div class="score-msg" id="scoreMsg"></div>
            <div class="retry-cd" id="retryCd"></div>
          </div>
          <button class="btn-continue" id="btnContinue" onclick="nextOrClose()">Continue <i class="fas fa-arrow-right"></i></button>
          <button class="btn-retry" id="btnRetry" onclick="retryCheckpoint()"><i class="fas fa-rotate-left"></i> Replay Concept</button>
        </div>
      </div>
    </div>
  </div>

  <div class="side-col">
    <?php if ($is_training): ?>
      <div class="side-card" id="viewerCard" <?php echo $logged_in ? '' : 'style="display:none;"'; ?>>
        <h3><i class="fas fa-user"></i> Viewer</h3>
        <div class="viewer-info">
          <div class="viewer-avatar" id="viewerInitial">
            <?php echo $logged_in ? strtoupper(substr($user['full_name'], 0, 1)) : '?'; ?>
          </div>
          <div class="viewer-text">
            <div class="viewer-name" id="viewerName"><?php echo $logged_in ? htmlspecialchars($user['full_name']) : '—'; ?></div>
            <div class="viewer-group" id="viewerGroup"><?php echo $logged_in ? htmlspecialchars($user['email']) : '—'; ?></div>
          </div>
        </div>
      </div>

      <div class="side-card">
        <h3><i class="fas fa-chart-line"></i> Your progress</h3>
        <div class="progress-summary">
          <div><div class="lbl">Checkpoints cleared</div></div>
          <div class="val"><span id="passedCount"><?php echo $passed_count; ?></span>/<?php echo $total_checkpoints; ?></div>
        </div>

        <h3 style="margin-top:18px;"><i class="fas fa-bullseye"></i> Checkpoints</h3>
        <div class="cp-list" id="cpList">
          <?php foreach ($quizzes as $i => $qz):
            $tt = floatval($qz['trigger_time']);
            $mm = floor($tt / 60); $ss = floor($tt % 60);
            $time_str = sprintf('%d:%02d', $mm, $ss);
            $label = !empty($qz['group_label']) ? htmlspecialchars($qz['group_label']) : "Checkpoint " . ($i + 1);
            $is_passed = in_array(intval($qz['id']), $passed_ids);
          ?>
          <div class="cp-item <?php echo $is_passed ? 'passed' : ''; ?>" data-idx="<?php echo $i; ?>" data-quiz-id="<?php echo intval($qz['id']); ?>">
            <div class="cp-num"><?php echo $i + 1; ?></div>
            <div class="cp-info">
              <div class="cp-label"><?php echo $label; ?></div>
              <div class="cp-time"><i class="far fa-clock"></i> at <?php echo $time_str; ?></div>
            </div>
            <div class="cp-status">
              <?php if ($is_passed): ?>
                <i class="fas fa-circle-check"></i>
              <?php else: ?>
                <i class="far fa-circle"></i>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php else: ?>
      <div class="side-card">
        <h3><i class="fas fa-tower-broadcast"></i> About this video</h3>
        <p style="font-size:13px; color: var(--text-soft); line-height:1.6;">This is an event recording from the MediaNest library. Sit back, watch full-screen, and use the controls below.</p>
      </div>
    <?php endif; ?>
  </div>
</div>

<div id="toast" class="toast" hidden></div>

<script>
const VIDEO_ID = <?php echo $video_id; ?>;
const QUIZZES  = <?php echo $quizzes_json; ?>;
const IS_TRAINING = <?php echo $is_training ? 'true' : 'false'; ?>;
const IS_LOGGED_IN = <?php echo $logged_in ? 'true' : 'false'; ?>;
const PASSED_QUIZ_IDS = <?php echo json_encode(array_map('intval', $passed_ids)); ?>;
const SESSION_USER = <?php echo $logged_in ? json_encode(['name' => $user['full_name'], 'email' => $user['email']]) : 'null'; ?>;

const VIDEO   = document.getElementById('mainVideo');
const OVERLAY = document.getElementById('quizOverlay');
const WRAP    = document.getElementById('playerWrap');
const BAR     = document.getElementById('controlBar');
const LETTERS = ['A','B','C','D'];

let userName  = SESSION_USER ? SESSION_USER.name : '';
let groupName = '';
let quizQueue = [], currentQIdx = 0, currentGroup = null, currentGroupIdx = -1;
let score = 0;
let completedGroups = new Set();
QUIZZES.forEach((q, idx) => { if (PASSED_QUIZ_IDS.includes(parseInt(q.id))) completedGroups.add(idx); });
let waitForResume = false, qStartTime = 0, retryStartTime = 0, cdTimer = null, hideBarTimer = null;

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

window.addEventListener('load', () => {
  if (PASSED_QUIZ_IDS.length > 0) toast('Skipping ' + PASSED_QUIZ_IDS.length + ' completed checkpoint(s)');
});

function startVideo() {
  const n = document.getElementById('gName').value.trim();
  const g = document.getElementById('gGroup').value.trim();
  if (!n || !g) { document.getElementById('gateErr').style.display = 'block'; return; }
  document.getElementById('gateErr').style.display = 'none';
  userName = n; groupName = g;
  const card = document.getElementById('viewerCard');
  if (card) {
    card.style.display = 'block';
    document.getElementById('viewerName').textContent = n;
    document.getElementById('viewerGroup').textContent = g;
    document.getElementById('viewerInitial').textContent = n.charAt(0).toUpperCase();
  }
  document.getElementById('userGate').style.display = 'none';
  VIDEO.play();
  document.getElementById('playBtn').innerHTML = '<i class="fas fa-pause"></i>';
}
if (IS_TRAINING && !IS_LOGGED_IN) {
  document.getElementById('gName').addEventListener('keydown',  e => { if (e.key === 'Enter') document.getElementById('gGroup').focus(); });
  document.getElementById('gGroup').addEventListener('keydown', e => { if (e.key === 'Enter') startVideo(); });
}

function togglePlay() {
  if (VIDEO.paused) { VIDEO.play(); document.getElementById('playBtn').innerHTML = '<i class="fas fa-pause"></i>'; }
  else { VIDEO.pause(); document.getElementById('playBtn').innerHTML = '<i class="fas fa-play"></i>'; }
}
function toggleMute() {
  VIDEO.muted = !VIDEO.muted;
  document.getElementById('muteBtn').innerHTML = VIDEO.muted ? '<i class="fas fa-volume-xmark"></i>' : '<i class="fas fa-volume-high"></i>';
}
VIDEO.addEventListener('play',  () => document.getElementById('playBtn').innerHTML = '<i class="fas fa-pause"></i>');
VIDEO.addEventListener('pause', () => document.getElementById('playBtn').innerHTML = '<i class="fas fa-play"></i>');
VIDEO.addEventListener('loadedmetadata', () => {
  document.getElementById('seekBar').max = VIDEO.duration;
  // Resume priority: ?t= deeplink > saved DB position > start
  const urlT = parseFloat(new URLSearchParams(location.search).get('t'));
  const savedPos = <?php echo (float)$saved_position; ?>;
  if (!isNaN(urlT) && urlT > 0 && urlT < VIDEO.duration) {
    VIDEO.currentTime = urlT;
    VIDEO.play().catch(() => {});
  } else if (savedPos > 5 && savedPos < VIDEO.duration - 5) {
    // Soft resume — don't autoplay, just seek so the user sees where they left off
    VIDEO.currentTime = savedPos;
    showResumeToast(savedPos);
  }
});

// ─── Save playback progress ─────────────────────────────────
const VIDEO_ID_FOR_PROGRESS = <?php echo (int)$video_id; ?>;
let __lastSentPos = -1;
async function saveProgress(force) {
  if (!VIDEO.duration || isNaN(VIDEO.duration)) return;
  const pos = VIDEO.currentTime;
  // Only send if moved at least 4s since last save (unless forced)
  if (!force && Math.abs(pos - __lastSentPos) < 4) return;
  __lastSentPos = pos;
  try {
    const fd = new FormData();
    fd.append('video_id', VIDEO_ID_FOR_PROGRESS);
    fd.append('position', pos.toFixed(1));
    fd.append('duration', VIDEO.duration.toFixed(1));
    // Use keepalive so unload requests still go through
    await fetch('progress_save.php', { method: 'POST', body: fd, keepalive: true });
  } catch (e) { /* silent — UX shouldn't break on network blip */ }
}
setInterval(() => { if (!VIDEO.paused) saveProgress(false); }, 10000);
VIDEO.addEventListener('pause',  () => saveProgress(true));
VIDEO.addEventListener('ended',  () => saveProgress(true));
window.addEventListener('pagehide',     () => saveProgress(true));
window.addEventListener('beforeunload', () => saveProgress(true));

function showResumeToast(pos) {
  const mins = Math.floor(pos / 60);
  const secs = String(Math.floor(pos % 60)).padStart(2, '0');
  const div = document.createElement('div');
  div.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:linear-gradient(135deg,#0ea5e9,#6366f1);color:white;padding:12px 22px;border-radius:999px;box-shadow:0 12px 40px rgba(14,165,233,.4);font-size:14px;font-weight:600;z-index:9999;cursor:pointer;display:flex;align-items:center;gap:10px;animation:resume-in .3s ease;';
  div.innerHTML = `<i class="fas fa-clock-rotate-left"></i> Resumed from ${mins}:${secs} · click to restart from beginning`;
  div.addEventListener('click', () => { VIDEO.currentTime = 0; div.remove(); });
  document.body.appendChild(div);
  setTimeout(() => { div.style.transition = 'opacity .4s'; div.style.opacity = 0; setTimeout(() => div.remove(), 400); }, 6000);
  if (!document.getElementById('resume-anim-style')) {
    const s = document.createElement('style');
    s.id = 'resume-anim-style';
    s.textContent = '@keyframes resume-in { from { opacity:0; transform:translate(-50%,12px); } to { opacity:1; transform:translate(-50%,0); } }';
    document.head.appendChild(s);
  }
}
VIDEO.addEventListener('timeupdate', () => {
  if (!VIDEO.duration) return;
  document.getElementById('seekBar').value = VIDEO.currentTime;
  document.getElementById('timeDisp').textContent = fmt(VIDEO.currentTime) + ' / ' + fmt(VIDEO.duration);
  if (IS_TRAINING) { checkTrigger(); updateActiveCheckpoint(); }
});
document.getElementById('seekBar').addEventListener('input', e => VIDEO.currentTime = e.target.value);
function fmt(s) { return Math.floor(s/60) + ':' + String(Math.floor(s%60)).padStart(2,'0'); }
WRAP.addEventListener('mousemove', () => { BAR.style.opacity = '1'; clearTimeout(hideBarTimer); hideBarTimer = setTimeout(() => BAR.style.opacity = '0', 2500); });
WRAP.addEventListener('click', e => { if (e.target.closest('#controlBar') || e.target.closest('#quizOverlay') || e.target.closest('#userGate')) return; togglePlay(); });
function toggleFS() {
  if (!document.fullscreenElement) (WRAP.requestFullscreen || WRAP.webkitRequestFullscreen).call(WRAP);
  else (document.exitFullscreen || document.webkitExitFullscreen).call(document);
}
const fsBtn = document.getElementById('fsBtn');
document.addEventListener('fullscreenchange',       () => fsBtn.innerHTML = document.fullscreenElement ? '<i class="fas fa-compress"></i>' : '<i class="fas fa-expand"></i>');
document.addEventListener('webkitfullscreenchange', () => fsBtn.innerHTML = document.fullscreenElement ? '<i class="fas fa-compress"></i>' : '<i class="fas fa-expand"></i>');

function checkTrigger() {
  if (waitForResume) return;
  const t = VIDEO.currentTime;
  QUIZZES.forEach((quiz, idx) => {
    if (completedGroups.has(idx)) return;
    if (t >= parseFloat(quiz.trigger_time) && t < parseFloat(quiz.trigger_time) + 2) openQuiz(idx);
  });
}
function updateActiveCheckpoint() {
  const t = VIDEO.currentTime;
  let activeIdx = -1;
  QUIZZES.forEach((q, i) => { if (!completedGroups.has(i) && t < parseFloat(q.trigger_time)) { if (activeIdx === -1) activeIdx = i; } });
  document.querySelectorAll('.cp-item').forEach((el, i) => el.classList.toggle('active', i === activeIdx));
}

function openQuiz(idx) {
  waitForResume = true; VIDEO.pause();
  currentGroupIdx = idx; currentGroup = QUIZZES[idx];
  quizQueue = currentGroup.options || []; currentQIdx = 0; score = 0;
  retryStartTime = parseFloat(currentGroup.trigger_time);
  buildDots(); showQuestion(0);
  OVERLAY.classList.add('active'); BAR.style.opacity = '0';
  document.getElementById('quizTopic').textContent = currentGroup.group_label ? '📌 ' + currentGroup.group_label : '';
}
function buildDots() {
  const p = document.getElementById('quizProgress'); p.innerHTML = '';
  if (quizQueue.length <= 1) return;
  quizQueue.forEach((_, i) => { const d = document.createElement('div'); d.className = 'q-dot' + (i === 0 ? ' current' : ''); p.appendChild(d); });
}
function updateDots(idx) { document.querySelectorAll('.q-dot').forEach((d, i) => d.className = 'q-dot' + (i < idx ? ' done' : i === idx ? ' current' : '')); }

function showQuestion(idx) {
  const q = quizQueue[idx]; if (!q) return;
  qStartTime = Date.now();
  document.getElementById('quizQuestion').textContent = q.question_text;
  const opts = document.getElementById('quizOptions'); opts.innerHTML = '';
  [q.option_a, q.option_b, q.option_c, q.option_d].filter(Boolean).forEach((c, i) => {
    const btn = document.createElement('button');
    btn.className = 'quiz-option'; btn.setAttribute('data-letter', LETTERS[i]); btn.textContent = c;
    btn.addEventListener('click', () => handleAnswer(btn, i, q.correct_option, q.explanation, q));
    opts.appendChild(btn);
  });
  document.getElementById('feedbackBar').className = 'feedback-bar';
  document.getElementById('scoreResult').className = 'score-result';
  document.getElementById('btnContinue').className = 'btn-continue';
  document.getElementById('btnRetry').className = 'btn-retry';
  updateDots(idx);
}

function handleAnswer(btn, chosen, correctIdx, explanation, qObj) {
  const timeTaken = ((Date.now() - qStartTime) / 1000).toFixed(2);
  document.querySelectorAll('.quiz-option').forEach(b => b.classList.add('locked'));
  const isCorrect = chosen === correctIdx;
  if (isCorrect) { score++; btn.classList.add('correct'); }
  else {
    btn.classList.add('wrong');
    const all = document.querySelectorAll('.quiz-option');
    if (all[correctIdx]) all[correctIdx].classList.add('correct');
  }
  document.getElementById('fbIcon').innerHTML = isCorrect ? '<i class="fas fa-circle-check" style="color:var(--green)"></i>' : '<i class="fas fa-circle-xmark" style="color:var(--red)"></i>';
  document.getElementById('fbText').textContent = isCorrect ? 'Correct! Well done.' : 'Incorrect — the right answer is highlighted above.';
  document.getElementById('fbExpl').textContent = explanation || '';
  document.getElementById('feedbackBar').className = 'feedback-bar show ' + (isCorrect ? 'ok' : 'ng');

  const fd = new FormData();
  fd.append('quiz_id',      currentGroup.id);
  fd.append('option_id',    qObj.id);
  fd.append('video_id',     VIDEO_ID);
  fd.append('chosen_option', chosen);
  fd.append('is_correct',   isCorrect ? 1 : 0);
  fd.append('time_taken',   timeTaken);
  fd.append('user_name',    userName);
  fd.append('group_name',   groupName);
  fetch('save_response.php', { method: 'POST', body: fd }).catch(() => {});

  if (currentQIdx >= quizQueue.length - 1) showScoreResult();
  else document.getElementById('btnContinue').className = 'btn-continue show';
}

function showScoreResult() {
  const pct = Math.round((score / quizQueue.length) * 100);
  const passed = pct >= 50;
  const circle = document.getElementById('scoreCircle');
  circle.textContent = pct + '%';
  circle.className = 'score-circle ' + (passed ? 'pass' : 'fail');
  const title = document.getElementById('scoreTitle');
  title.innerHTML = passed ? '<i class="fas fa-trophy"></i> Great job!' : '<i class="fas fa-rotate-left"></i> Need more review';
  title.className = 'score-title ' + (passed ? 'pass' : 'fail');
  document.getElementById('scoreMsg').textContent = passed
    ? 'You scored ' + score + '/' + quizQueue.length + ' — you may continue.'
    : 'You scored ' + score + '/' + quizQueue.length + ' — you need 50% to proceed. The concept will replay.';
  document.getElementById('scoreResult').className = 'score-result show';
  if (passed) {
    document.getElementById('btnContinue').className = 'btn-continue show';
    document.getElementById('retryCd').textContent = '';
  } else {
    document.getElementById('btnRetry').className = 'btn-retry show';
    startCd(5);
  }
}
function startCd(s) {
  const el = document.getElementById('retryCd');
  clearInterval(cdTimer);
  el.textContent = 'Replaying concept in ' + s + 's…';
  cdTimer = setInterval(() => { s--; if (s <= 0) { clearInterval(cdTimer); retryCheckpoint(); } else el.textContent = 'Replaying concept in ' + s + 's…'; }, 1000);
}
function retryCheckpoint() {
  clearInterval(cdTimer);
  OVERLAY.classList.remove('active');
  waitForResume = false;
  VIDEO.currentTime = Math.max(0, retryStartTime - 2);
  VIDEO.play();
}
function nextOrClose() {
  currentQIdx++;
  if (currentQIdx < quizQueue.length) showQuestion(currentQIdx);
  else closeQuiz();
}
function closeQuiz() {
  OVERLAY.classList.remove('active');
  completedGroups.add(currentGroupIdx);
  waitForResume = false;
  const cp = document.querySelector('.cp-item[data-idx="' + currentGroupIdx + '"]');
  if (cp) {
    cp.classList.add('passed'); cp.classList.remove('active');
    cp.querySelector('.cp-status').innerHTML = '<i class="fas fa-circle-check"></i>';
    document.getElementById('passedCount').textContent = completedGroups.size;
    toast('Checkpoint complete!');
  }
  VIDEO.play();
}

const toastEl = document.getElementById('toast');
let toastTimer;
function toast(msg) {
  toastEl.textContent = msg;
  toastEl.hidden = false;
  requestAnimationFrame(() => toastEl.classList.add('show'));
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { toastEl.classList.remove('show'); setTimeout(() => toastEl.hidden = true, 250); }, 2200);
}

// ─── AI: Summarize this video ────────────────────────────────
(function() {
  const btn       = document.getElementById('aiSumBtn');
  const hint      = document.getElementById('aiSumHint');
  const panel     = document.getElementById('aiSumPanel');
  const closeBtn  = document.getElementById('aiSumClose');
  const txtEl     = document.getElementById('aiSumText');
  const topicsWrap = document.getElementById('aiSumTopicsWrap');
  const topicsEl  = document.getElementById('aiSumTopics');
  const metaEl    = document.getElementById('aiSumMeta');
  if (!btn) return;

  const VID_ID = <?php echo (int)$video_id; ?>;
  let loaded = false;

  function setLoading(on) {
    btn.disabled = on;
    btn.querySelector('.ai-sum-spark').innerHTML = on
      ? '<i class="fas fa-spinner spin"></i>'
      : '<i class="fas fa-wand-magic-sparkles"></i>';
    btn.querySelector('.ai-sum-label').textContent = on ? 'Thinking…' : (loaded ? 'Show summary' : 'Summarize this video');
  }

  function showError(msg) {
    panel.classList.add('open');
    txtEl.classList.remove('loading');
    txtEl.innerHTML = '<div class="ai-sum-error"><i class="fas fa-circle-exclamation"></i><div>' + escapeHtml(msg) + '</div></div>';
    topicsWrap.classList.remove('show');
    metaEl.textContent = '';
  }

  function escapeHtml(s) {
    return (s || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]);
  }

  async function loadSummary() {
    panel.classList.add('open');
    hint.style.display = 'none';
    txtEl.classList.add('loading');
    txtEl.textContent = 'Reading the transcript and summarizing…';
    topicsWrap.classList.remove('show');
    setLoading(true);
    try {
      const r  = await fetch('summarize.php?video_id=' + VID_ID);
      const js = await r.json();
      if (!js.ok) { showError(js.error || 'Could not generate summary.'); setLoading(false); return; }
      // Render
      txtEl.classList.remove('loading');
      txtEl.textContent = js.summary;
      if (js.key_topics && js.key_topics.length) {
        topicsEl.innerHTML = js.key_topics.map(t => '<li>' + escapeHtml(t) + '</li>').join('');
        topicsWrap.classList.add('show');
      } else {
        topicsWrap.classList.remove('show');
      }
      const tag = js.cached ? 'cached' : 'fresh';
      const mins = js.length_sec ? Math.round(js.length_sec / 60) + ' min video' : '';
      metaEl.textContent = [mins, tag].filter(Boolean).join(' · ');
      loaded = true;
      setLoading(false);
    } catch (e) {
      showError(e.message || 'Network error.');
      setLoading(false);
    }
  }

  btn.addEventListener('click', () => {
    if (loaded && !panel.classList.contains('open')) {
      panel.classList.add('open');
      btn.querySelector('.ai-sum-label').textContent = 'Hide summary';
    } else if (loaded && panel.classList.contains('open')) {
      panel.classList.remove('open');
      btn.querySelector('.ai-sum-label').textContent = 'Show summary';
    } else {
      loadSummary();
    }
  });
  closeBtn.addEventListener('click', () => {
    panel.classList.remove('open');
    if (loaded) btn.querySelector('.ai-sum-label').textContent = 'Show summary';
  });
})();
</script>
</body>
</html>