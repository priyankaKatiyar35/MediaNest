<?php
/**
 * MediaNest Admin — Dashboard (home)
 * --------------------------------------------------------------
 * Stats overview + quick actions + recent uploads.
 */
$body_class = 'page-dashboard';
$page_title = 'Dashboard';
require __DIR__ . '/header.php';

global $conn;

// ---------- Live stats ----------
function _admin_count($conn, $sql) {
    $r = mysqli_query($conn, $sql);
    return $r ? (int)(mysqli_fetch_row($r)[0] ?? 0) : 0;
}
$count_videos     = _admin_count($conn, "SELECT COUNT(*) FROM video");
$count_albums     = _admin_count($conn, "SELECT COUNT(*) FROM tbl_album WHERE status='process'");
$count_photos     = _admin_count($conn, "SELECT COUNT(*) FROM tbl_gallery WHERE status='process'");
$count_folders    = _admin_count($conn, "SELECT COUNT(*) FROM folders");
$count_files      = _admin_count($conn, "SELECT COUNT(*) FROM files");
$count_users      = _admin_count($conn, "SELECT COUNT(*) FROM users");
$count_admins     = _admin_count($conn, "SELECT COUNT(*) FROM users WHERE role='admin'");

// Quiz stats (best-effort — tables may or may not exist)
$count_quizzes  = 0; $count_responses = 0;
@$q1 = mysqli_query($conn, "SELECT COUNT(*) FROM video_quizzes");
if ($q1) $count_quizzes = (int)(mysqli_fetch_row($q1)[0] ?? 0);
@$q2 = mysqli_query($conn, "SELECT COUNT(*) FROM quiz_responses");
if ($q2) $count_responses = (int)(mysqli_fetch_row($q2)[0] ?? 0);

// Categories
$count_categories = 0;
@$qc = mysqli_query($conn, "SELECT COUNT(*) FROM video_categories");
if ($qc) $count_categories = (int)(mysqli_fetch_row($qc)[0] ?? 0);

// Recent uploads — videos (with category)
$recent_videos = @mysqli_query($conn, "
    SELECT v.id, v.title, v.des, c.name AS cat_name
    FROM video v
    LEFT JOIN video_categories c ON c.id = v.category_id
    ORDER BY v.id DESC LIMIT 5
");
// Recent albums
$recent_albums = @mysqli_query($conn, "
    SELECT albumid, name, image, date,
           (SELECT COUNT(*) FROM tbl_gallery g WHERE g.aid = a.albumid AND g.status='process') AS pc
    FROM tbl_album a WHERE status='process' ORDER BY albumid DESC LIMIT 5
");
// Recent files
$recent_files = @mysqli_query($conn, "
    SELECT f.file_id, f.file_name, f.file_desc, fo.name AS folder_name
    FROM files f LEFT JOIN folders fo ON f.folder_id = fo.albumid
    ORDER BY f.file_id DESC LIMIT 5
");

// Recent users
$recent_users = @mysqli_query($conn, "
    SELECT id, email, full_name, role, last_login
    FROM users ORDER BY id DESC LIMIT 6
");
?>

<style>
/* Dashboard-specific */
.welcome-card {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 60%, #ec4899 100%);
  color: white; border-radius: 18px; padding: 28px 32px;
  display: flex; align-items: center; justify-content: space-between; gap: 24px;
  flex-wrap: wrap; margin-bottom: 24px;
  box-shadow: 0 12px 40px rgba(99, 102, 241, .3);
  position: relative; overflow: hidden;
}
.welcome-card::before { content: ''; position: absolute; top: -100px; right: -80px; width: 320px; height: 320px; border-radius: 50%; background: rgba(255,255,255,.08); }
.welcome-card::after { content: '\f135'; font-family: 'Font Awesome 6 Free'; font-weight: 900; position: absolute; right: -10px; bottom: -20px; font-size: 140px; opacity: .08; }
.welcome-card .text { position: relative; z-index: 1; }
.welcome-card .text h2 { font-size: 26px; font-weight: 800; margin-bottom: 6px; }
.welcome-card .text p { opacity: .92; max-width: 560px; font-size: 14px; }
.welcome-card .actions { position: relative; z-index: 1; display: flex; gap: 10px; flex-wrap: wrap; }
.welcome-card .btn-white { background: white; color: #6366f1; padding: 10px 18px; border-radius: 10px; font-weight: 600; font-size: 14px; display: inline-flex; align-items: center; gap: 7px; }
.welcome-card .btn-outline { background: rgba(255,255,255,.15); color: white; border: 1px solid rgba(255,255,255,.3); padding: 10px 18px; border-radius: 10px; font-weight: 600; font-size: 14px; display: inline-flex; align-items: center; gap: 7px; backdrop-filter: blur(8px); }
.welcome-card .btn-outline:hover { background: rgba(255,255,255,.25); }

.stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 28px; }
.stat-card { background: var(--bg-elev); border: 1px solid var(--border); border-radius: 14px; padding: 18px 20px; transition: transform .2s, box-shadow .2s; position: relative; overflow: hidden; }
.stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
.stat-card .top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
.stat-card .icon { width: 42px; height: 42px; border-radius: 12px; display: grid; place-items: center; font-size: 16px; }
.stat-card .icon.indigo { background: rgba(99, 102, 241, .12); color: #6366f1; }
.stat-card .icon.pink   { background: rgba(236, 72, 153, .12); color: #ec4899; }
.stat-card .icon.green  { background: rgba(16, 185, 129, .12); color: #10b981; }
.stat-card .icon.blue   { background: rgba(14, 165, 233, .12); color: #0ea5e9; }
.stat-card .icon.gold   { background: rgba(245, 158, 11, .12); color: #f59e0b; }
.stat-card .icon.violet { background: rgba(139, 92, 246, .12); color: #8b5cf6; }
.stat-card .trend { font-size: 11px; padding: 3px 8px; border-radius: 999px; font-weight: 700; background: rgba(16, 185, 129, .12); color: #10b981; display: inline-flex; align-items: center; gap: 4px; }
.stat-card .value { font-family: 'Sora', sans-serif; font-weight: 800; font-size: 30px; line-height: 1; }
.stat-card .label { font-size: 12px; color: var(--text-soft); text-transform: uppercase; letter-spacing: .06em; font-weight: 600; margin-top: 6px; }
.stat-card .sub { font-size: 11px; color: var(--muted); margin-top: 8px; }

/* Two-column layout for content rows */
.row-2 { display: grid; grid-template-columns: 2fr 1fr; gap: 22px; margin-bottom: 22px; }
@media (max-width: 1100px) { .row-2 { grid-template-columns: 1fr; } }

.panel { background: var(--bg-elev); border: 1px solid var(--border); border-radius: 14px; padding: 22px; }
.panel-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; gap: 12px; }
.panel-head h3 { font-size: 16px; font-weight: 700; display: flex; align-items: center; gap: 9px; }
.panel-head h3 i { color: var(--brand-1); }
.panel-head a.more { font-size: 12px; color: var(--brand-1); font-weight: 600; display: inline-flex; align-items: center; gap: 5px; }

.activity { display: flex; flex-direction: column; gap: 10px; }
.activity-item { display: flex; align-items: center; gap: 14px; padding: 10px 12px; border-radius: 10px; transition: background .15s; }
.activity-item:hover { background: var(--bg); }
.activity-thumb { width: 44px; height: 44px; border-radius: 10px; display: grid; place-items: center; flex-shrink: 0; font-size: 16px; }
.activity-thumb.video { background: rgba(14, 165, 233, .15); color: #0ea5e9; }
.activity-thumb.album { background: rgba(236, 72, 153, .15); color: #ec4899; }
.activity-thumb.doc   { background: rgba(16, 185, 129, .15); color: #10b981; }
.activity-thumb img { width: 100%; height: 100%; object-fit: cover; border-radius: 10px; }
.activity-body { flex: 1; min-width: 0; }
.activity-title { font-weight: 600; font-size: 14px; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.activity-meta { font-size: 12px; color: var(--text-soft); margin-top: 2px; display: flex; gap: 10px; align-items: center; }
.activity-meta .tag { padding: 1px 7px; border-radius: 999px; background: rgba(99, 102, 241, .12); color: #6366f1; font-weight: 600; font-size: 10px; text-transform: uppercase; letter-spacing: .04em; }

.empty-mini { text-align: center; padding: 28px 20px; color: var(--muted); font-size: 13px; }
.empty-mini i { font-size: 24px; opacity: .4; margin-bottom: 8px; display: block; }

/* Quick actions panel */
.quick-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.quick-item { display: flex; flex-direction: column; align-items: flex-start; gap: 8px; padding: 14px; border-radius: 11px; border: 1px solid var(--border); background: var(--bg-elev); transition: all .15s; }
.quick-item:hover { background: var(--bg); border-color: var(--brand-1); transform: translateY(-2px); }
.quick-item .qic { width: 36px; height: 36px; border-radius: 10px; display: grid; place-items: center; font-size: 14px; }
.quick-item .qt { font-weight: 600; font-size: 13px; }
.quick-item .qd { font-size: 11px; color: var(--muted); }
.quick-item.indigo .qic { background: rgba(99,102,241,.12); color: #6366f1; }
.quick-item.pink   .qic { background: rgba(236, 72, 153, .12); color: #ec4899; }
.quick-item.green  .qic { background: rgba(16, 185, 129, .12); color: #10b981; }
.quick-item.gold   .qic { background: rgba(245, 158, 11, .12); color: #f59e0b; }

/* Users mini-table */
.user-row { display: flex; align-items: center; gap: 12px; padding: 8px 0; border-bottom: 1px solid var(--border); }
.user-row:last-child { border-bottom: 0; }
.user-row .uav { width: 32px; height: 32px; border-radius: 50%; background: var(--grad-brand); color: white; font-weight: 700; font-size: 11px; display: grid; place-items: center; flex-shrink: 0; }
.user-row .uinfo { flex: 1; min-width: 0; }
.user-row .uname { font-size: 13px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.user-row .umail { font-size: 11px; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.role-pill { font-size: 10px; padding: 2px 8px; border-radius: 999px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
.role-pill.admin { background: rgba(99,102,241,.15); color: #6366f1; }
.role-pill.user  { background: rgba(100, 116, 139, .12); color: var(--text-soft); }
</style>

<!-- Welcome card -->
<div class="welcome-card">
  <div class="text">
    <h2>Welcome back, <?php echo htmlspecialchars(explode(' ', $ad_name)[0]); ?> 👋</h2>
    <p>Here's what's happening across MediaNest today. Quick links and live counts are below — jump straight into uploading or jump to the live site to see how things look.</p>
  </div>
  <div class="actions">
    <a href="upload.php" class="btn-white"><i class="fas fa-plus"></i> Upload Video</a>
    <a href="../index.php" target="_blank" class="btn-outline"><i class="fas fa-arrow-up-right-from-square"></i> View Site</a>
  </div>
</div>

<!-- Stat grid -->
<div class="stat-grid">
  <div class="stat-card">
    <div class="top">
      <div class="icon indigo"><i class="fas fa-film"></i></div>
      <span class="trend"><i class="fas fa-video"></i> live</span>
    </div>
    <div class="value"><?php echo number_format($count_videos); ?></div>
    <div class="label">Videos</div>
    <div class="sub"><?php echo $count_categories; ?> categor<?php echo $count_categories == 1 ? 'y' : 'ies'; ?></div>
  </div>

  <div class="stat-card">
    <div class="top">
      <div class="icon pink"><i class="fas fa-images"></i></div>
    </div>
    <div class="value"><?php echo number_format($count_albums); ?></div>
    <div class="label">Photo events</div>
    <div class="sub"><?php echo number_format($count_photos); ?> photos total</div>
  </div>

  <div class="stat-card">
    <div class="top">
      <div class="icon green"><i class="fas fa-folder-open"></i></div>
    </div>
    <div class="value"><?php echo number_format($count_folders); ?></div>
    <div class="label">Document folders</div>
    <div class="sub"><?php echo number_format($count_files); ?> files stored</div>
  </div>

  <div class="stat-card">
    <div class="top">
      <div class="icon blue"><i class="fas fa-users"></i></div>
    </div>
    <div class="value"><?php echo number_format($count_users); ?></div>
    <div class="label">Registered users</div>
    <div class="sub"><?php echo $count_admins; ?> admin<?php echo $count_admins == 1 ? '' : 's'; ?></div>
  </div>

  <div class="stat-card">
    <div class="top">
      <div class="icon gold"><i class="fas fa-question-circle"></i></div>
    </div>
    <div class="value"><?php echo number_format($count_quizzes); ?></div>
    <div class="label">Active quizzes</div>
    <div class="sub"><?php echo number_format($count_responses); ?> response<?php echo $count_responses == 1 ? '' : 's'; ?></div>
  </div>

  <div class="stat-card">
    <div class="top">
      <div class="icon violet"><i class="fas fa-chart-line"></i></div>
    </div>
    <div class="value"><?php echo number_format($count_videos + $count_photos + $count_files); ?></div>
    <div class="label">Total media items</div>
    <div class="sub">Across all sections</div>
  </div>
</div>

<!-- Row 1: Recent videos + Quick actions -->
<div class="row-2">
  <div class="panel">
    <div class="panel-head">
      <h3><i class="fas fa-film"></i> Recent video uploads</h3>
      <a href="upload.php" class="more">Upload new <i class="fas fa-arrow-right"></i></a>
    </div>
    <div class="activity">
      <?php if ($recent_videos && mysqli_num_rows($recent_videos)): ?>
        <?php while ($v = mysqli_fetch_assoc($recent_videos)): ?>
          <div class="activity-item">
            <div class="activity-thumb video"><i class="fas fa-play"></i></div>
            <div class="activity-body">
              <div class="activity-title"><?php echo htmlspecialchars($v['title']); ?></div>
              <div class="activity-meta">
                <span>#<?php echo (int)$v['id']; ?></span>
                <?php if (!empty($v['cat_name'])): ?>
                  <span class="tag"><?php echo htmlspecialchars($v['cat_name']); ?></span>
                <?php endif; ?>
                <span><?php echo htmlspecialchars(mb_strimwidth($v['des'] ?? '', 0, 50, '…')); ?></span>
              </div>
            </div>
            <a href="../Videos/video_player.php?id=<?php echo (int)$v['id']; ?>" target="_blank" class="btn btn-ghost" style="padding:6px 12px;font-size:12px;">
              <i class="fas fa-eye"></i>
            </a>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <div class="empty-mini"><i class="fas fa-film"></i>No videos uploaded yet.</div>
      <?php endif; ?>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head">
      <h3><i class="fas fa-bolt"></i> Quick actions</h3>
    </div>
    <div class="quick-grid">
      <a href="upload.php" class="quick-item indigo">
        <div class="qic"><i class="fas fa-film"></i></div>
        <div class="qt">New video</div>
        <div class="qd">Upload + categorize</div>
      </a>
      <a href="addalbum.php" class="quick-item pink">
        <div class="qic"><i class="fas fa-images"></i></div>
        <div class="qt">New photo event</div>
        <div class="qd">Create album</div>
      </a>
      <a href="uploadfiles.php" class="quick-item green">
        <div class="qic"><i class="fas fa-file-arrow-up"></i></div>
        <div class="qt">Add document</div>
        <div class="qd">Into a folder</div>
      </a>
      <a href="quiz_editor.php" class="quick-item gold">
        <div class="qic"><i class="fas fa-question"></i></div>
        <div class="qt">Build a quiz</div>
        <div class="qd">Attach to video</div>
      </a>
    </div>
  </div>
</div>

<!-- Row 2: Albums + Documents -->
<div class="row-2">
  <div class="panel">
    <div class="panel-head">
      <h3><i class="fas fa-images"></i> Recent photo albums</h3>
      <a href="addalbum.php" class="more">Manage <i class="fas fa-arrow-right"></i></a>
    </div>
    <div class="activity">
      <?php if ($recent_albums && mysqli_num_rows($recent_albums)): ?>
        <?php while ($a = mysqli_fetch_assoc($recent_albums)):
          $cover = !empty($a['image']) ? 'acatch/' . rawurlencode($a['image']) : '';
        ?>
          <div class="activity-item">
            <div class="activity-thumb album">
              <?php if ($cover): ?>
                <img src="<?php echo htmlspecialchars($cover); ?>" alt="" onerror="this.style.display='none';this.parentNode.innerHTML='<i class=&quot;fas fa-image&quot;></i>';">
              <?php else: ?>
                <i class="fas fa-image"></i>
              <?php endif; ?>
            </div>
            <div class="activity-body">
              <div class="activity-title"><?php echo htmlspecialchars($a['name']); ?></div>
              <div class="activity-meta">
                <span><i class="fas fa-images" style="margin-right:4px;"></i><?php echo (int)$a['pc']; ?> photo<?php echo $a['pc'] == 1 ? '' : 's'; ?></span>
                <?php if (!empty($a['date'])): ?>
                  <span><i class="fas fa-calendar" style="margin-right:4px;"></i><?php echo htmlspecialchars(date('M j, Y', strtotime($a['date']))); ?></span>
                <?php endif; ?>
              </div>
            </div>
            <a href="addfiles.php?id=<?php echo (int)$a['albumid']; ?>" class="btn btn-ghost" style="padding:6px 12px;font-size:12px;">
              <i class="fas fa-plus"></i> Add
            </a>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <div class="empty-mini"><i class="fas fa-image"></i>No photo events yet.</div>
      <?php endif; ?>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head">
      <h3><i class="fas fa-users"></i> Recent users</h3>
    </div>
    <?php if ($recent_users && mysqli_num_rows($recent_users)): ?>
      <?php while ($u = mysqli_fetch_assoc($recent_users)):
        $disp = $u['full_name'] ?: explode('@', $u['email'])[0];
      ?>
        <div class="user-row">
          <div class="uav"><?php echo htmlspecialchars(_admin_initials($disp)); ?></div>
          <div class="uinfo">
            <div class="uname"><?php echo htmlspecialchars($disp); ?></div>
            <div class="umail"><?php echo htmlspecialchars($u['email']); ?></div>
          </div>
          <span class="role-pill <?php echo $u['role'] === 'admin' ? 'admin' : 'user'; ?>">
            <?php echo htmlspecialchars($u['role']); ?>
          </span>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <div class="empty-mini"><i class="fas fa-user"></i>No users yet.</div>
    <?php endif; ?>
  </div>
</div>

<!-- Row 3: Recent documents (full width) -->
<div class="panel">
  <div class="panel-head">
    <h3><i class="fas fa-folder-open"></i> Recent documents</h3>
    <a href="uploadfiles.php" class="more">Upload <i class="fas fa-arrow-right"></i></a>
  </div>
  <div class="activity">
    <?php if ($recent_files && mysqli_num_rows($recent_files)): ?>
      <?php while ($f = mysqli_fetch_assoc($recent_files)):
        $ext = strtolower(pathinfo($f['file_name'] ?? '', PATHINFO_EXTENSION));
        $icon = match (true) {
          in_array($ext, ['pdf'])           => 'fa-file-pdf',
          in_array($ext, ['doc', 'docx'])   => 'fa-file-word',
          in_array($ext, ['xls', 'xlsx'])   => 'fa-file-excel',
          in_array($ext, ['ppt', 'pptx'])   => 'fa-file-powerpoint',
          in_array($ext, ['jpg','jpeg','png','gif','webp']) => 'fa-file-image',
          in_array($ext, ['mp4','mov','avi','mkv']) => 'fa-file-video',
          default => 'fa-file',
        };
      ?>
        <div class="activity-item">
          <div class="activity-thumb doc"><i class="fas <?php echo $icon; ?>"></i></div>
          <div class="activity-body">
            <div class="activity-title"><?php echo htmlspecialchars($f['file_desc'] ?: $f['file_name']); ?></div>
            <div class="activity-meta">
              <span><?php echo htmlspecialchars($f['file_name']); ?></span>
              <?php if (!empty($f['folder_name'])): ?>
                <span class="tag" style="background:rgba(16,185,129,.12);color:#10b981;">
                  <?php echo htmlspecialchars($f['folder_name']); ?>
                </span>
              <?php endif; ?>
            </div>
          </div>
          <a href="../Documents/view_file.php?file_id=<?php echo (int)$f['file_id']; ?>" target="_blank" class="btn btn-ghost" style="padding:6px 12px;font-size:12px;">
            <i class="fas fa-eye"></i>
          </a>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <div class="empty-mini"><i class="fas fa-folder"></i>No documents uploaded yet.</div>
    <?php endif; ?>
  </div>
</div>

    </main><!-- /page-wrap -->
  </div><!-- /main -->
</div><!-- /admin-shell -->

</body>
</html>