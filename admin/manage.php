<?php
/**
 * MediaNest Admin — Unified Content Manager
 * --------------------------------------------------------------
 * One page, tabbed: Videos · Categories · Albums · Folders · Files · Users
 * Each tab has search + edit + delete (CSRF-protected, prepared statements,
 * audit logged, cascading deletes with on-disk file cleanup).
 */
$body_class = 'page-manage';
$page_title = 'Manage Content';

require_once __DIR__ . '/admin_auth.php';
requireAdmin();
global $conn;
$admin = currentAdmin();

$tab   = $_GET['tab'] ?? 'videos';
$valid = ['videos', 'categories', 'albums', 'folders', 'files', 'users'];
if (!in_array($tab, $valid)) $tab = 'videos';

$flash = '';

// ─────────── Helpers ───────────
function slugify($s) {
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-');
}
function folder_label($id, $all) {
    foreach ($all as $f) if ($f['albumid'] == $id) {
        $label = $f['name'];
        if (!is_null($f['parent_folder_id'])) {
            foreach ($all as $p) if ($p['albumid'] == $f['parent_folder_id']) { $label = $p['name'] . ' / ' . $f['name']; break; }
        }
        return $label;
    }
    return '—';
}
function file_type_meta($ext) {
    $ext = strtolower($ext);
    if (in_array($ext, ['pdf']))                           return ['fa-file-pdf','PDF','#ef4444'];
    if (in_array($ext, ['doc','docx']))                    return ['fa-file-word','WORD','#0ea5e9'];
    if (in_array($ext, ['xls','xlsx','csv']))              return ['fa-file-excel','EXCEL','#10b981'];
    if (in_array($ext, ['ppt','pptx']))                    return ['fa-file-powerpoint','PPT','#f59e0b'];
    if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) return ['fa-file-image','IMG','#a855f7'];
    if (in_array($ext, ['mp4','mov','mkv','avi']))         return ['fa-file-video','VIDEO','#06b6d4'];
    return ['fa-file', strtoupper($ext) ?: 'FILE', '#64748b'];
}
function fs_unlink($path) { if (is_file($path)) @unlink($path); }
function admin_count($pk_field, $table) {
    return 0; // unused placeholder; counts done inline below
}

// ─────────── POST router ───────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfCheck($_POST['csrf'] ?? '')) {
        $flash = ['error', 'Session expired. Please refresh and try again.'];
    } else {
        $action = $_POST['action'] ?? '';
        switch ($action) {

        // ─── VIDEOS ───────────────────────────────
        case 'video_edit': {
            $vid    = intval($_POST['id']);
            $title  = trim($_POST['title']);
            $des    = trim($_POST['des'] ?? '');
            $cat_id = $_POST['category_id'] !== '' ? intval($_POST['category_id']) : null;
            if ($title === '') { $flash = ['error','Title is required.']; break; }
            if ($cat_id !== null) {
                $s = mysqli_prepare($conn, "UPDATE video SET title=?, des=?, category_id=? WHERE id=?");
                mysqli_stmt_bind_param($s, 'ssii', $title, $des, $cat_id, $vid);
            } else {
                $s = mysqli_prepare($conn, "UPDATE video SET title=?, des=?, category_id=NULL WHERE id=?");
                mysqli_stmt_bind_param($s, 'ssi', $title, $des, $vid);
            }
            mysqli_stmt_execute($s) ? $flash=['success',"Video #$vid updated."] : $flash=['error',mysqli_stmt_error($s)];
            mysqli_stmt_close($s);
            adminAuditLog('video_edit', "#$vid: $title");
            break;
        }
        case 'video_delete': {
            $vid = intval($_POST['id']);
            $s = mysqli_prepare($conn, "SELECT name FROM video WHERE id=?"); mysqli_stmt_bind_param($s,'i',$vid); mysqli_stmt_execute($s);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($s)); mysqli_stmt_close($s);
            if (!$row) { $flash=['error','Video not found.']; break; }
            $s = mysqli_prepare($conn, "DELETE qo FROM quiz_options qo INNER JOIN video_quizzes vq ON vq.id=qo.quiz_id WHERE vq.video_id=?");
            mysqli_stmt_bind_param($s,'i',$vid); mysqli_stmt_execute($s); mysqli_stmt_close($s);
            $s = mysqli_prepare($conn, "DELETE FROM video_quizzes WHERE video_id=?"); mysqli_stmt_bind_param($s,'i',$vid); mysqli_stmt_execute($s); mysqli_stmt_close($s);
            $s = mysqli_prepare($conn, "DELETE FROM video WHERE id=?"); mysqli_stmt_bind_param($s,'i',$vid); mysqli_stmt_execute($s); mysqli_stmt_close($s);
            fs_unlink(__DIR__ . '/upload/' . $row['name']);
            adminAuditLog('video_delete', "#$vid");
            $flash = ['success', "Video #$vid and any quizzes deleted."];
            break;
        }

        // ─── CATEGORIES ───────────────────────────
        case 'cat_add': {
            $name = trim($_POST['name']); $slug = trim($_POST['slug']) ?: slugify($name);
            $desc = trim($_POST['description'] ?? ''); $order = intval($_POST['sort_order'] ?? 0);
            if ($name === '' || $slug === '') { $flash=['error','Name and slug required.']; break; }
            $s = mysqli_prepare($conn, "INSERT INTO video_categories (name, slug, description, sort_order) VALUES (?,?,?,?)");
            mysqli_stmt_bind_param($s,'sssi',$name,$slug,$desc,$order);
            if (mysqli_stmt_execute($s)) {
                $flash=['success',"Category '$name' added."]; adminAuditLog('category_create',$name);
            } else {
                $err = mysqli_stmt_error($s);
                $flash=['error', strpos($err,'Duplicate')!==false ? "Slug '$slug' already exists." : $err];
            }
            mysqli_stmt_close($s);
            break;
        }
        case 'cat_edit': {
            $cid = intval($_POST['id']); $name = trim($_POST['name']); $slug = trim($_POST['slug']);
            $desc = trim($_POST['description'] ?? ''); $order = intval($_POST['sort_order'] ?? 0);
            if ($name === '' || $slug === '') { $flash=['error','Name and slug required.']; break; }
            $s = mysqli_prepare($conn, "UPDATE video_categories SET name=?, slug=?, description=?, sort_order=? WHERE id=?");
            mysqli_stmt_bind_param($s,'sssii',$name,$slug,$desc,$order,$cid);
            mysqli_stmt_execute($s) ? $flash=['success',"Category #$cid updated."] : $flash=['error',mysqli_stmt_error($s)];
            mysqli_stmt_close($s);
            adminAuditLog('category_edit', "#$cid");
            break;
        }
        case 'cat_delete': {
            $cid = intval($_POST['id']); $to = $_POST['reassign_to'] !== '' ? intval($_POST['reassign_to']) : null;
            if ($to !== null) {
                $s = mysqli_prepare($conn, "UPDATE video SET category_id=? WHERE category_id=?");
                mysqli_stmt_bind_param($s,'ii',$to,$cid);
            } else {
                $s = mysqli_prepare($conn, "UPDATE video SET category_id=NULL WHERE category_id=?");
                mysqli_stmt_bind_param($s,'i',$cid);
            }
            mysqli_stmt_execute($s); mysqli_stmt_close($s);
            $s = mysqli_prepare($conn, "DELETE FROM video_categories WHERE id=?"); mysqli_stmt_bind_param($s,'i',$cid);
            mysqli_stmt_execute($s); mysqli_stmt_close($s);
            adminAuditLog('category_delete', "#$cid");
            $flash = ['success', "Category #$cid deleted."];
            break;
        }

        // ─── ALBUMS ───────────────────────────────
        case 'album_edit': {
            $aid = intval($_POST['id']); $name = trim($_POST['name']); $desc = trim($_POST['adesc'] ?? '');
            if ($name === '') { $flash=['error','Album name required.']; break; }
            $s = mysqli_prepare($conn, "UPDATE tbl_album SET name=?, adesc=? WHERE albumid=?");
            mysqli_stmt_bind_param($s,'ssi',$name,$desc,$aid);
            mysqli_stmt_execute($s) ? $flash=['success',"Album #$aid updated."] : $flash=['error',mysqli_stmt_error($s)];
            mysqli_stmt_close($s);
            adminAuditLog('album_edit', "#$aid: $name");
            break;
        }
        case 'album_delete': {
            $aid = intval($_POST['id']);
            $s = mysqli_prepare($conn,"SELECT gimages FROM tbl_gallery WHERE aid=?"); mysqli_stmt_bind_param($s,'i',$aid); mysqli_stmt_execute($s);
            $r = mysqli_stmt_get_result($s); $photo_files = []; while ($row=mysqli_fetch_assoc($r)) $photo_files[]=$row['gimages']; mysqli_stmt_close($s);
            $s = mysqli_prepare($conn,"SELECT image, name FROM tbl_album WHERE albumid=?"); mysqli_stmt_bind_param($s,'i',$aid); mysqli_stmt_execute($s);
            $album = mysqli_fetch_assoc(mysqli_stmt_get_result($s)); mysqli_stmt_close($s);
            if (!$album) { $flash=['error','Album not found.']; break; }
            $s = mysqli_prepare($conn,"DELETE FROM tbl_gallery WHERE aid=?"); mysqli_stmt_bind_param($s,'i',$aid); mysqli_stmt_execute($s); mysqli_stmt_close($s);
            $s = mysqli_prepare($conn,"DELETE FROM tbl_album WHERE albumid=?"); mysqli_stmt_bind_param($s,'i',$aid); mysqli_stmt_execute($s); mysqli_stmt_close($s);
            foreach ($photo_files as $f) { fs_unlink(__DIR__.'/gupload/'.$f); fs_unlink(__DIR__.'/gcatch/'.$f); }
            if (!empty($album['image'])) fs_unlink(__DIR__.'/acatch/'.$album['image']);
            adminAuditLog('album_delete', "#$aid: {$album['name']}");
            $flash = ['success', "Album '{$album['name']}' and ".count($photo_files)." photos deleted."];
            break;
        }

        // ─── FOLDERS ──────────────────────────────
        case 'folder_edit': {
            $fid = intval($_POST['id']); $name = trim($_POST['name']); $desc = trim($_POST['adesc'] ?? '');
            if ($name === '') { $flash=['error','Folder name required.']; break; }
            $s = mysqli_prepare($conn,"UPDATE folders SET name=?, adesc=? WHERE albumid=?");
            mysqli_stmt_bind_param($s,'ssi',$name,$desc,$fid);
            mysqli_stmt_execute($s) ? $flash=['success',"Folder #$fid updated."] : $flash=['error',mysqli_stmt_error($s)];
            mysqli_stmt_close($s);
            adminAuditLog('folder_edit', "#$fid: $name");
            break;
        }
        case 'folder_delete': {
            $fid = intval($_POST['id']);
            // Find descendants via BFS
            $to_delete = [$fid]; $queue = [$fid];
            while ($queue) {
                $cur = array_shift($queue);
                $s = mysqli_prepare($conn,"SELECT albumid FROM folders WHERE parent_folder_id=?");
                mysqli_stmt_bind_param($s,'i',$cur); mysqli_stmt_execute($s);
                $r = mysqli_stmt_get_result($s);
                while ($row=mysqli_fetch_assoc($r)) { $to_delete[]=(int)$row['albumid']; $queue[]=(int)$row['albumid']; }
                mysqli_stmt_close($s);
            }
            $place = implode(',', array_fill(0, count($to_delete), '?'));
            $types = str_repeat('i', count($to_delete));
            // Files in those folders
            $s = mysqli_prepare($conn, "SELECT file_path FROM files WHERE folder_id IN ($place)");
            mysqli_stmt_bind_param($s, $types, ...$to_delete); mysqli_stmt_execute($s);
            $r = mysqli_stmt_get_result($s); $file_paths=[]; while ($row=mysqli_fetch_assoc($r)) $file_paths[]=$row['file_path']; mysqli_stmt_close($s);
            // Delete files + folders
            $s = mysqli_prepare($conn, "DELETE FROM files WHERE folder_id IN ($place)");   mysqli_stmt_bind_param($s,$types,...$to_delete); mysqli_stmt_execute($s); mysqli_stmt_close($s);
            $s = mysqli_prepare($conn, "DELETE FROM folders WHERE albumid IN ($place)"); mysqli_stmt_bind_param($s,$types,...$to_delete); mysqli_stmt_execute($s); mysqli_stmt_close($s);
            foreach ($file_paths as $fp) fs_unlink(__DIR__.'/'.$fp);
            adminAuditLog('folder_delete', "#$fid (cascade)");
            $flash = ['success', "Folder + ".(count($to_delete)-1)." subfolder(s), ".count($file_paths)." file(s) deleted."];
            break;
        }

        // ─── FILES ────────────────────────────────
        case 'file_edit': {
            $fid = intval($_POST['id']); $desc = trim($_POST['file_desc'] ?? '');
            $vlink = trim($_POST['video_link'] ?? ''); $fold = intval($_POST['folder_id'] ?? 0);
            if ($fold <= 0) { $flash=['error','Pick a folder.']; break; }
            $s = mysqli_prepare($conn,"UPDATE files SET file_desc=?, video_link=?, folder_id=? WHERE file_id=?");
            mysqli_stmt_bind_param($s,'ssii',$desc,$vlink,$fold,$fid);
            mysqli_stmt_execute($s) ? $flash=['success',"File #$fid updated."] : $flash=['error',mysqli_stmt_error($s)];
            mysqli_stmt_close($s);
            adminAuditLog('file_edit',"#$fid");
            break;
        }
        case 'file_delete': {
            $fid = intval($_POST['id']);
            $s = mysqli_prepare($conn,"SELECT file_path, file_name FROM files WHERE file_id=?"); mysqli_stmt_bind_param($s,'i',$fid); mysqli_stmt_execute($s);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($s)); mysqli_stmt_close($s);
            if (!$row) { $flash=['error','File not found.']; break; }
            $s = mysqli_prepare($conn,"DELETE FROM files WHERE file_id=?"); mysqli_stmt_bind_param($s,'i',$fid); mysqli_stmt_execute($s); mysqli_stmt_close($s);
            fs_unlink(__DIR__.'/'.$row['file_path']);
            adminAuditLog('file_delete',"#$fid: ".$row['file_name']);
            $flash = ['success', "File deleted."];
            break;
        }

        // ─── USERS ────────────────────────────────
        case 'user_add': {
            $email    = trim($_POST['email'] ?? '');
            $name     = trim($_POST['full_name'] ?? '');
            $pw       = $_POST['password'] ?? '';
            $role     = $_POST['role'] === 'admin' ? 'admin' : 'user';
            $group    = trim($_POST['group_name'] ?? '');

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $flash=['error','Enter a valid email address.']; break; }
            if ($name === '')      { $flash=['error','Full name is required.']; break; }
            if (strlen($pw) < 6)   { $flash=['error','Password must be at least 6 characters.']; break; }

            // Check duplicate email
            $s = mysqli_prepare($conn, "SELECT id FROM users WHERE email=?");
            mysqli_stmt_bind_param($s, 's', $email);
            mysqli_stmt_execute($s);
            $exists = mysqli_fetch_assoc(mysqli_stmt_get_result($s));
            mysqli_stmt_close($s);
            if ($exists) { $flash=['error',"An account with email '$email' already exists."]; break; }

            $hash = password_hash($pw, PASSWORD_DEFAULT);
            $s = mysqli_prepare($conn,
                "INSERT INTO users (email, password_hash, full_name, role, group_name) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($s, 'sssss', $email, $hash, $name, $role, $group);
            if (mysqli_stmt_execute($s)) {
                $new_id = mysqli_insert_id($conn);
                adminAuditLog('user_create', "#$new_id: $email ($role)");
                $flash = ['success', "User '$email' created. Share the password with them and ask them to change it."];
            } else {
                $flash = ['error', 'DB error: ' . mysqli_stmt_error($s)];
            }
            mysqli_stmt_close($s);
            break;
        }
        case 'user_role': {
            $uid = intval($_POST['id']); $new_role = $_POST['role'] === 'admin' ? 'admin' : 'user';
            // Prevent demoting last admin
            if ($new_role !== 'admin') {
                $cnt = mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM users WHERE role='admin'"))[0];
                if ($cnt <= 1) { $flash=['error','Cannot demote the last admin.']; break; }
            }
            $s = mysqli_prepare($conn,"UPDATE users SET role=? WHERE id=?");
            mysqli_stmt_bind_param($s,'si',$new_role,$uid);
            mysqli_stmt_execute($s) ? $flash=['success',"User #$uid is now $new_role."] : $flash=['error',mysqli_stmt_error($s)];
            mysqli_stmt_close($s);
            adminAuditLog('user_role_change',"#$uid → $new_role");
            break;
        }
        case 'user_delete': {
            $uid = intval($_POST['id']);
            if ($uid === (int)$admin['id']) { $flash=['error','You cannot delete your own account.']; break; }
            // Prevent deleting last admin
            $s = mysqli_prepare($conn,"SELECT role FROM users WHERE id=?"); mysqli_stmt_bind_param($s,'i',$uid); mysqli_stmt_execute($s);
            $r = mysqli_fetch_assoc(mysqli_stmt_get_result($s)); mysqli_stmt_close($s);
            if ($r && $r['role']==='admin') {
                $cnt = mysqli_fetch_row(mysqli_query($conn,"SELECT COUNT(*) FROM users WHERE role='admin'"))[0];
                if ($cnt <= 1) { $flash=['error','Cannot delete the last admin.']; break; }
            }
            $s = mysqli_prepare($conn,"DELETE FROM users WHERE id=?"); mysqli_stmt_bind_param($s,'i',$uid);
            mysqli_stmt_execute($s) ? $flash=['success',"User #$uid deleted."] : $flash=['error',mysqli_stmt_error($s)];
            mysqli_stmt_close($s);
            adminAuditLog('user_delete',"#$uid");
            break;
        }
        case 'user_password': {
            $uid = intval($_POST['id']); $pw = $_POST['password'] ?? '';
            if (strlen($pw) < 6) { $flash=['error','Password must be at least 6 characters.']; break; }
            $hash = password_hash($pw, PASSWORD_DEFAULT);
            $s = mysqli_prepare($conn,"UPDATE users SET password_hash=? WHERE id=?");
            mysqli_stmt_bind_param($s,'si',$hash,$uid);
            mysqli_stmt_execute($s) ? $flash=['success',"Password updated for user #$uid."] : $flash=['error',mysqli_stmt_error($s)];
            mysqli_stmt_close($s);
            adminAuditLog('user_password_reset',"#$uid");
            break;
        }

        }
    }
}

// ─────────── Data per tab ───────────
$search = trim($_GET['q'] ?? '');
$like   = '%' . $search . '%';
$rows   = [];
$cats   = [];
$all_folders = [];

// Categories (used in Videos tab + Categories tab)
$cres = @mysqli_query($conn, "SELECT id, name, slug, description, sort_order FROM video_categories ORDER BY sort_order, name");
if ($cres) while ($r = mysqli_fetch_assoc($cres)) $cats[] = $r;

// All folders (used in Files + Folders)
$fres = mysqli_query($conn, "SELECT albumid, name, adesc, parent_folder_id FROM folders ORDER BY parent_folder_id, name");
if ($fres) while ($r = mysqli_fetch_assoc($fres)) $all_folders[] = $r;

if ($tab === 'videos') {
    $sql = "SELECT v.id, v.name, v.title, v.des, v.category_id, c.name AS cat_name,
                   (SELECT COUNT(*) FROM video_quizzes WHERE video_id=v.id) AS quiz_count,
                   (SELECT id FROM video_transcripts WHERE video_id=v.id) AS transcript_id,
                   (SELECT duration_sec FROM video_transcripts WHERE video_id=v.id) AS t_dur
            FROM video v LEFT JOIN video_categories c ON c.id = v.category_id";
    if ($search !== '') { $sql .= " WHERE v.title LIKE ? OR v.des LIKE ?"; }
    $sql .= " ORDER BY v.id DESC";
    $s = mysqli_prepare($conn, $sql);
    if ($search !== '') mysqli_stmt_bind_param($s,'ss',$like,$like);
    mysqli_stmt_execute($s); $r = mysqli_stmt_get_result($s);
    while ($row = mysqli_fetch_assoc($r)) $rows[] = $row;
    mysqli_stmt_close($s);
}
elseif ($tab === 'categories') {
    foreach ($cats as &$c) {
        $s = mysqli_prepare($conn,"SELECT COUNT(*) FROM video WHERE category_id=?");
        mysqli_stmt_bind_param($s,'i',$c['id']); mysqli_stmt_execute($s);
        $c['video_count'] = (int)mysqli_fetch_row(mysqli_stmt_get_result($s))[0];
        mysqli_stmt_close($s);
    } unset($c);
    $rows = $cats;
}
elseif ($tab === 'albums') {
    $sql = "SELECT a.albumid, a.name, a.adesc, a.image, a.date,
                   (SELECT COUNT(*) FROM tbl_gallery g WHERE g.aid=a.albumid AND g.status='process') AS pc
            FROM tbl_album a";
    if ($search !== '') $sql .= " WHERE name LIKE ? OR adesc LIKE ?";
    $sql .= " ORDER BY albumid DESC";
    $s = mysqli_prepare($conn,$sql);
    if ($search !== '') mysqli_stmt_bind_param($s,'ss',$like,$like);
    mysqli_stmt_execute($s); $r = mysqli_stmt_get_result($s);
    while ($row = mysqli_fetch_assoc($r)) $rows[] = $row;
    mysqli_stmt_close($s);
}
elseif ($tab === 'folders') {
    foreach ($all_folders as &$f) {
        $s = mysqli_prepare($conn,"SELECT COUNT(*) FROM files WHERE folder_id=?");
        mysqli_stmt_bind_param($s,'i',$f['albumid']); mysqli_stmt_execute($s);
        $f['file_count'] = (int)mysqli_fetch_row(mysqli_stmt_get_result($s))[0]; mysqli_stmt_close($s);
        $s = mysqli_prepare($conn,"SELECT COUNT(*) FROM folders WHERE parent_folder_id=?");
        mysqli_stmt_bind_param($s,'i',$f['albumid']); mysqli_stmt_execute($s);
        $f['sub_count'] = (int)mysqli_fetch_row(mysqli_stmt_get_result($s))[0]; mysqli_stmt_close($s);
    } unset($f);
    $rows = $all_folders;
}
elseif ($tab === 'files') {
    $filter_folder = isset($_GET['folder']) && $_GET['folder'] !== '' ? intval($_GET['folder']) : null;
    $where_parts = []; $types = ''; $params = [];
    if ($search !== '')         { $where_parts[] = '(f.file_name LIKE ? OR f.file_desc LIKE ?)'; $types.='ss'; $params[]=$like; $params[]=$like; }
    if ($filter_folder !== null){ $where_parts[] = 'f.folder_id = ?'; $types.='i'; $params[]=$filter_folder; }
    $where = $where_parts ? ('WHERE ' . implode(' AND ', $where_parts)) : '';
    $sql = "SELECT f.file_id, f.file_name, f.file_desc, f.video_link, f.folder_id,
                   (SELECT id FROM document_extracts WHERE file_id=f.file_id) AS extract_id,
                   (SELECT word_count FROM document_extracts WHERE file_id=f.file_id) AS e_words
            FROM files f $where ORDER BY f.file_id DESC";
    $s = mysqli_prepare($conn, $sql);
    if ($types) mysqli_stmt_bind_param($s, $types, ...$params);
    mysqli_stmt_execute($s); $r = mysqli_stmt_get_result($s);
    while ($row = mysqli_fetch_assoc($r)) $rows[] = $row;
    mysqli_stmt_close($s);
}
elseif ($tab === 'users') {
    $sql = "SELECT id, email, full_name, role, group_name, last_login FROM users";
    if ($search !== '') $sql .= " WHERE email LIKE ? OR full_name LIKE ?";
    $sql .= " ORDER BY id DESC";
    $s = mysqli_prepare($conn,$sql);
    if ($search !== '') mysqli_stmt_bind_param($s,'ss',$like,$like);
    mysqli_stmt_execute($s); $r = mysqli_stmt_get_result($s);
    while ($row = mysqli_fetch_assoc($r)) $rows[] = $row;
    mysqli_stmt_close($s);
}

require __DIR__ . '/header.php';
?>

<style>
.man-tabs { display: flex; gap: 4px; padding: 6px; background: var(--bg-elev); border: 1px solid var(--border); border-radius: 14px; margin-bottom: 22px; overflow-x: auto; }
.man-tab { padding: 9px 16px; border-radius: 9px; font-size: 13px; font-weight: 600; color: var(--text-soft); white-space: nowrap; display: inline-flex; align-items: center; gap: 8px; transition: all .15s; cursor: pointer; }
.man-tab:hover { background: var(--bg); color: var(--text); }
.man-tab.active { background: var(--grad-brand); color: white; box-shadow: 0 6px 18px rgba(99,102,241,.25); }
.man-tab .pill-n { font-size: 10px; padding: 2px 7px; border-radius: 999px; background: rgba(255,255,255,.2); }
.man-tab:not(.active) .pill-n { background: var(--bg); color: var(--text-soft); }

.toolbar { display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap; padding: 12px 14px; background: var(--bg-elev); border: 1px solid var(--border); border-radius: 12px; margin-bottom: 18px; }
.fg { display: flex; flex-direction: column; gap: 4px; }
.fg label { font-size: 10px; font-weight: 700; color: var(--text-soft); text-transform: uppercase; letter-spacing: .06em; }
.fg input, .fg select { padding: 8px 12px; border: 1px solid var(--border); border-radius: 8px; background: var(--bg); color: var(--text); font: inherit; font-size: 13px; min-width: 180px; }
.fg input:focus, .fg select:focus { outline: 0; border-color: var(--brand-1); box-shadow: 0 0 0 3px rgba(99,102,241,.15); }

.alert { display: flex; gap: 10px; padding: 12px 14px; border-radius: 10px; font-size: 13px; margin-bottom: 16px; }
.alert.success { background: rgba(16,185,129,.1); color: #065f46; border: 1px solid rgba(16,185,129,.3); }
.alert.error   { background: rgba(239,68,68,.1);  color: #991b1b; border: 1px solid rgba(239,68,68,.3); }
html.dark .alert.success { color: #6ee7b7; } html.dark .alert.error { color: #fca5a5; }

.tbl-wrap { background: var(--bg-elev); border: 1px solid var(--border); border-radius: 14px; overflow: hidden; }
table.crud { width: 100%; border-collapse: collapse; }
table.crud th { text-align: left; padding: 11px 14px; font-size: 10px; font-weight: 700; color: var(--text-soft); text-transform: uppercase; letter-spacing: .06em; background: var(--bg); border-bottom: 1px solid var(--border); }
table.crud td { padding: 11px 14px; font-size: 13px; border-bottom: 1px solid var(--border); vertical-align: middle; }
table.crud tr:last-child td { border-bottom: 0; }
table.crud tbody tr:hover { background: var(--bg); }
.crud-title { font-weight: 600; }
.crud-id { color: var(--muted); font-size: 11px; }
.crud-desc { font-size: 12px; color: var(--text-soft); overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; max-width: 280px; }

.pill { display: inline-block; padding: 2px 9px; border-radius: 999px; font-weight: 700; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; }
.pill.cat { background: rgba(99,102,241,.12); color: var(--brand-1); }
.pill.cat.none { background: rgba(100,116,139,.12); color: var(--muted); }
.pill.quiz { background: rgba(245,158,11,.15); color: #d97706; }
.pill.folder { background: rgba(16,185,129,.12); color: #10b981; }
.pill.role-admin { background: rgba(99,102,241,.15); color: var(--brand-1); }
.pill.role-user  { background: rgba(100,116,139,.12); color: var(--text-soft); }

.pill.ai-ready { background: linear-gradient(135deg, rgba(168,85,247,.15), rgba(236,72,153,.15)); color: #a855f7; border: 1px solid rgba(168,85,247,.25); }
html.dark .pill.ai-ready { color: #c4b5fd; }

.ai-btn { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 8px; border: 1px solid rgba(168,85,247,.3); background: linear-gradient(135deg, rgba(168,85,247,.08), rgba(236,72,153,.08)); color: #a855f7; font-size: 12px; font-weight: 600; cursor: pointer; transition: all .15s; font-family: inherit; }
.ai-btn:hover { background: linear-gradient(135deg, #a855f7, #ec4899); color: white; border-color: transparent; transform: translateY(-1px); box-shadow: 0 6px 16px rgba(168,85,247,.3); }
.ai-btn:disabled { opacity: .55; cursor: not-allowed; transform: none; box-shadow: none; }
.ai-btn .spin { animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* Floating AI toast */
.ai-toast { position: fixed; right: 24px; bottom: 24px; z-index: 2000; background: var(--bg-elev); border: 1px solid var(--border); border-radius: 14px; box-shadow: 0 20px 50px rgba(0,0,0,.25); padding: 14px 18px; display: none; align-items: center; gap: 12px; max-width: 360px; }
.ai-toast.show { display: flex; }
.ai-toast .tic { width: 36px; height: 36px; border-radius: 10px; background: linear-gradient(135deg, #a855f7, #ec4899); color: white; display: grid; place-items: center; flex-shrink: 0; }
.ai-toast .ttxt { font-size: 13px; line-height: 1.4; color: var(--text); }
.ai-toast .ttxt strong { display: block; margin-bottom: 2px; }
.ai-toast.error .tic { background: #ef4444; }
.ai-toast.ok    .tic { background: linear-gradient(135deg, #10b981, #059669); }

.row-actions { display: flex; gap: 6px; justify-content: flex-end; }
.row-actions a, .row-actions button { padding: 6px 10px; border-radius: 7px; border: 1px solid var(--border); background: var(--bg); color: var(--text-soft); font-size: 12px; font-weight: 600; cursor: pointer; transition: all .15s; display: inline-flex; align-items: center; gap: 4px; font-family: inherit; }
.row-actions a:hover, .row-actions button.edit:hover { color: var(--brand-1); border-color: var(--brand-1); }
.row-actions button.danger:hover { color: #ef4444; border-color: #ef4444; }

/* Albums grid */
.album-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 14px; }
.album-card { background: var(--bg-elev); border: 1px solid var(--border); border-radius: 14px; overflow: hidden; transition: all .2s; }
.album-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); border-color: rgba(236,72,153,.3); }
.album-cover { aspect-ratio: 4/3; background: var(--bg); position: relative; }
.album-cover img { width: 100%; height: 100%; object-fit: cover; }
.album-cover .pc { position: absolute; top: 8px; right: 8px; padding: 3px 9px; border-radius: 999px; background: rgba(0,0,0,.7); color: white; font-size: 11px; font-weight: 700; }
.album-body { padding: 12px 14px; }
.album-name { font-weight: 700; font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.album-desc { font-size: 11px; color: var(--text-soft); margin-top: 3px; min-height: 26px; overflow: hidden; }
.album-actions { display: flex; gap: 5px; padding: 0 12px 12px; }
.album-actions a, .album-actions button { flex: 1; padding: 6px 8px; border-radius: 7px; font-size: 11px; font-weight: 600; text-align: center; border: 1px solid var(--border); background: var(--bg); color: var(--text-soft); cursor: pointer; font-family: inherit; }
.album-actions a.primary { background: var(--grad-brand); color: white; border-color: transparent; }
.album-actions button.edit:hover { color: var(--brand-1); border-color: var(--brand-1); }
.album-actions button.danger:hover { color: #ef4444; border-color: #ef4444; }

/* Categories list */
.cat-row { display: flex; align-items: center; gap: 12px; padding: 12px; border: 1px solid var(--border); border-radius: 10px; margin-bottom: 8px; }
.cat-row:hover { background: var(--bg); }
.cat-ic { width: 36px; height: 36px; border-radius: 9px; background: var(--grad-brand); color: white; display: grid; place-items: center; flex-shrink: 0; }
.cat-info { flex: 1; min-width: 0; }
.cat-name { font-weight: 700; font-size: 14px; }
.cat-meta { font-size: 11px; color: var(--text-soft); margin-top: 2px; }
.cat-slug { display: inline-block; padding: 1px 6px; border-radius: 5px; background: var(--bg); font-family: ui-monospace, monospace; font-size: 10px; }
.cat-count { padding: 3px 9px; border-radius: 999px; background: rgba(99,102,241,.12); color: var(--brand-1); font-weight: 700; font-size: 11px; }

/* Folder tree */
.f-row { display: flex; align-items: center; gap: 12px; padding: 11px 14px; border-bottom: 1px solid var(--border); }
.f-row:last-child { border-bottom: 0; }
.f-row:hover { background: var(--bg); }
.f-row.depth-1 { padding-left: 44px; background: linear-gradient(to right, rgba(99,102,241,.04), transparent); }
.f-row.depth-2 { padding-left: 80px; }
.f-icon { width: 32px; height: 32px; border-radius: 8px; display: grid; place-items: center; flex-shrink: 0; }
.f-icon.main { background: rgba(16,185,129,.15); color: #10b981; }
.f-icon.sub  { background: rgba(14,165,233,.12); color: #0ea5e9; }
.f-info { flex: 1; min-width: 0; }
.f-name { font-weight: 600; font-size: 13px; }
.f-desc { font-size: 11px; color: var(--text-soft); }

/* Type pill (files) */
.type-pill { padding: 2px 8px; border-radius: 6px; color: white; font-size: 10px; font-weight: 700; letter-spacing: .04em; }

/* User avatar */
.av { width: 32px; height: 32px; border-radius: 50%; background: var(--grad-brand); color: white; font-weight: 700; font-size: 11px; display: grid; place-items: center; flex-shrink: 0; }
.name-cell { display: flex; align-items: center; gap: 10px; }

/* Modals */
.modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,.6); z-index: 1000; display: none; align-items: center; justify-content: center; padding: 20px; backdrop-filter: blur(4px); }
.modal-backdrop.open { display: flex; }
.modal-box { background: var(--bg-elev); border-radius: 16px; max-width: 520px; width: 100%; max-height: 90vh; overflow-y: auto; }
.modal-head { padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
.modal-head h3 { font-size: 16px; font-weight: 700; }
.modal-head .x { width: 30px; height: 30px; border-radius: 7px; background: var(--bg); border: 1px solid var(--border); color: var(--text-soft); cursor: pointer; display: grid; place-items: center; }
.modal-body { padding: 18px 20px; }
.modal-foot { padding: 12px 20px 18px; display: flex; gap: 10px; justify-content: flex-end; }

.field { margin-bottom: 12px; }
.field label { display: block; font-size: 11px; font-weight: 700; color: var(--text-soft); margin-bottom: 5px; text-transform: uppercase; letter-spacing: .05em; }
.field .input, .field .textarea, .field select.input { width: 100%; padding: 10px 13px; border: 1px solid var(--border); border-radius: 9px; background: var(--bg); color: var(--text); font: inherit; font-size: 14px; }
.field .input:focus, .field .textarea:focus, .field select.input:focus { outline: 0; border-color: var(--brand-1); box-shadow: 0 0 0 3px rgba(99,102,241,.15); }
.field .textarea { resize: vertical; min-height: 60px; }

.empty-mini { padding: 50px 20px; text-align: center; color: var(--muted); }
.empty-mini i { font-size: 32px; opacity: .4; margin-bottom: 12px; display: block; }

.users-header { display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 14px 18px; background: var(--bg-elev); border: 1px solid var(--border); border-radius: 12px; margin-bottom: 14px; flex-wrap: wrap; }
</style>

<?php if ($flash): ?>
  <div class="alert <?php echo $flash[0]; ?>"><i class="fas fa-<?php echo $flash[0]==='success'?'circle-check':'circle-exclamation'; ?>"></i><div><?php echo htmlspecialchars($flash[1]); ?></div></div>
<?php endif; ?>

<!-- Tab bar -->
<div class="man-tabs">
  <?php
    // Live counts for tab pills (cheap COUNTs)
    $n_videos  = (int)mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM video"))[0];
    $n_cats    = count($cats);
    $n_albums  = (int)mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM tbl_album WHERE status='process'"))[0];
    $n_folders = count($all_folders);
    $n_files   = (int)mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM files"))[0];
    $n_users   = (int)mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM users"))[0];
    $TABS = [
      'videos'     => ['fa-film',          'Videos',     $n_videos],
      'categories' => ['fa-tags',          'Categories', $n_cats],
      'albums'     => ['fa-images',        'Albums',     $n_albums],
      'folders'    => ['fa-folder-tree',   'Folders',    $n_folders],
      'files'      => ['fa-file',          'Files',      $n_files],
      'users'      => ['fa-users',         'Users',      $n_users],
    ];
    foreach ($TABS as $key => $t):
  ?>
    <a class="man-tab <?php echo $tab===$key?'active':''; ?>" href="manage.php?tab=<?php echo $key; ?>">
      <i class="fas <?php echo $t[0]; ?>"></i> <?php echo $t[1]; ?>
      <span class="pill-n"><?php echo $t[2]; ?></span>
    </a>
  <?php endforeach; ?>
</div>

<!-- Search toolbar (most tabs) -->
<?php if (in_array($tab, ['videos','albums','folders','files','users'])): ?>
<form method="get" class="toolbar">
  <input type="hidden" name="tab" value="<?php echo $tab; ?>">
  <div class="fg">
    <label>Search</label>
    <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Type to filter…">
  </div>
  <?php if ($tab === 'files'): ?>
    <div class="fg">
      <label>Folder</label>
      <select name="folder" onchange="this.form.submit()">
        <option value="">All folders</option>
        <?php $filter_folder = isset($_GET['folder']) && $_GET['folder']!=='' ? intval($_GET['folder']) : null; ?>
        <?php foreach ($all_folders as $f): ?>
          <option value="<?php echo (int)$f['albumid']; ?>" <?php if ($filter_folder === (int)$f['albumid']) echo 'selected'; ?>>
            <?php echo htmlspecialchars(folder_label($f['albumid'], $all_folders)); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  <?php endif; ?>
  <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply</button>
  <a href="manage.php?tab=<?php echo $tab; ?>" class="btn btn-ghost"><i class="fas fa-rotate-left"></i> Reset</a>
</form>
<?php endif; ?>

<!-- ==================== VIDEOS ==================== -->
<?php if ($tab === 'videos'): ?>
<div class="tbl-wrap">
  <table class="crud">
    <thead><tr><th>Title</th><th>Category</th><th>Quizzes</th><th>AI</th><th style="text-align:right;">Actions</th></tr></thead>
    <tbody>
    <?php if (empty($rows)): ?>
      <tr><td colspan="5"><div class="empty-mini"><i class="fas fa-film"></i>No videos.</div></td></tr>
    <?php else: foreach ($rows as $v): ?>
      <tr>
        <td>
          <div class="crud-title"><?php echo htmlspecialchars($v['title']); ?></div>
          <div class="crud-desc"><?php echo htmlspecialchars(mb_strimwidth($v['des'] ?? '', 0, 100, '…')); ?></div>
          <div class="crud-id">#<?php echo (int)$v['id']; ?></div>
        </td>
        <td>
          <?php if (!empty($v['cat_name'])): ?><span class="pill cat"><?php echo htmlspecialchars($v['cat_name']); ?></span>
          <?php else: ?><span class="pill cat none">Uncategorized</span><?php endif; ?>
        </td>
        <td>
          <?php if ((int)$v['quiz_count'] > 0): ?><span class="pill quiz"><i class="fas fa-bullseye"></i> <?php echo (int)$v['quiz_count']; ?></span>
          <?php else: ?><span style="color:var(--muted);font-size:12px;">—</span><?php endif; ?>
        </td>
        <td>
          <?php if (!empty($v['transcript_id'])): ?>
            <span class="pill ai-ready" title="Transcribed (<?php echo (int)$v['t_dur']; ?>s)"><i class="fas fa-wand-magic-sparkles"></i> ready</span>
          <?php else: ?>
            <button type="button" class="ai-btn" data-transcribe="<?php echo (int)$v['id']; ?>" data-title="<?php echo htmlspecialchars($v['title'], ENT_QUOTES); ?>">
              <i class="fas fa-wand-magic-sparkles"></i> Transcribe
            </button>
          <?php endif; ?>
        </td>
        <td>
          <div class="row-actions">
            <a href="../Videos/video_player.php?id=<?php echo (int)$v['id']; ?>" target="_blank" title="View"><i class="fas fa-eye"></i></a>
            <a href="quiz_editor.php?vid=<?php echo (int)$v['id']; ?>" title="Quiz editor"><i class="fas fa-question"></i></a>
            <button class="edit" type="button" data-edit-video='<?php echo htmlspecialchars(json_encode([
              'id'=>(int)$v['id'],'title'=>$v['title'],'des'=>$v['des'],'cat'=>$v['category_id'],
            ]), ENT_QUOTES); ?>'><i class="fas fa-pen"></i></button>
            <form method="post" class="js-del" style="display:inline;">
              <input type="hidden" name="csrf" value="<?php echo csrfToken(); ?>">
              <input type="hidden" name="action" value="video_delete">
              <input type="hidden" name="id" value="<?php echo (int)$v['id']; ?>">
              <button type="submit" class="danger" data-confirm="Delete this video and its quizzes?"><i class="fas fa-trash"></i></button>
            </form>
          </div>
        </td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<div class="modal-backdrop" id="vidModal">
  <div class="modal-box">
    <div class="modal-head"><h3><i class="fas fa-pen" style="color:var(--brand-1);"></i> Edit video</h3><button class="x" type="button" data-close><i class="fas fa-xmark"></i></button></div>
    <form method="post">
      <div class="modal-body">
        <input type="hidden" name="csrf" value="<?php echo csrfToken(); ?>">
        <input type="hidden" name="action" value="video_edit">
        <input type="hidden" name="id" id="v_id">
        <div class="field"><label>Title</label><input type="text" name="title" id="v_title" class="input" required maxlength="200"></div>
        <div class="field"><label>Description</label><textarea name="des" id="v_des" class="textarea" maxlength="2000"></textarea></div>
        <div class="field"><label>Category</label>
          <select name="category_id" id="v_cat" class="input">
            <option value="">— Uncategorized —</option>
            <?php foreach ($cats as $c): ?><option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>Cancel</button><button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Save</button></div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- ==================== CATEGORIES ==================== -->
<?php if ($tab === 'categories'): ?>
<div style="display:grid;grid-template-columns:1fr 2fr;gap:18px;">
  <div class="panel">
    <h3 style="font-size:15px;font-weight:700;margin-bottom:14px;"><i class="fas fa-plus-circle" style="color:var(--brand-1);"></i> Add category</h3>
    <form method="post">
      <input type="hidden" name="csrf" value="<?php echo csrfToken(); ?>">
      <input type="hidden" name="action" value="cat_add">
      <div class="field"><label>Name</label><input type="text" name="name" class="input" required maxlength="80"></div>
      <div class="field"><label>Slug (auto if empty)</label><input type="text" name="slug" class="input" maxlength="80"></div>
      <div class="field"><label>Description</label><textarea name="description" class="textarea" maxlength="255"></textarea></div>
      <div class="field"><label>Sort order</label><input type="number" name="sort_order" class="input" value="0"></div>
      <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add</button>
    </form>
  </div>
  <div class="panel">
    <h3 style="font-size:15px;font-weight:700;margin-bottom:14px;"><i class="fas fa-tags"></i> All categories</h3>
    <?php if (empty($rows)): ?>
      <div class="empty-mini"><i class="fas fa-folder-open"></i>No categories.</div>
    <?php else: foreach ($rows as $c): ?>
      <div class="cat-row">
        <div class="cat-ic"><i class="fas fa-tag"></i></div>
        <div class="cat-info">
          <div class="cat-name"><?php echo htmlspecialchars($c['name']); ?></div>
          <div class="cat-meta">
            <span class="cat-slug"><?php echo htmlspecialchars($c['slug']); ?></span>
            <?php if (!empty($c['description'])): ?> · <?php echo htmlspecialchars(mb_strimwidth($c['description'], 0, 60, '…')); ?><?php endif; ?>
          </div>
        </div>
        <span class="cat-count"><?php echo (int)$c['video_count']; ?> vid<?php echo $c['video_count']==1?'':'s'; ?></span>
        <div class="row-actions">
          <button class="edit" type="button" data-edit-cat='<?php echo htmlspecialchars(json_encode([
            'id'=>(int)$c['id'],'name'=>$c['name'],'slug'=>$c['slug'],'description'=>$c['description'],'sort_order'=>(int)$c['sort_order'],
          ]), ENT_QUOTES); ?>'><i class="fas fa-pen"></i></button>
          <button class="danger" type="button" data-del-cat='<?php echo htmlspecialchars(json_encode([
            'id'=>(int)$c['id'],'name'=>$c['name'],'count'=>(int)$c['video_count'],
          ]), ENT_QUOTES); ?>'><i class="fas fa-trash"></i></button>
        </div>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<div class="modal-backdrop" id="catEditModal">
  <div class="modal-box">
    <div class="modal-head"><h3><i class="fas fa-pen" style="color:var(--brand-1);"></i> Edit category</h3><button class="x" type="button" data-close><i class="fas fa-xmark"></i></button></div>
    <form method="post">
      <div class="modal-body">
        <input type="hidden" name="csrf" value="<?php echo csrfToken(); ?>">
        <input type="hidden" name="action" value="cat_edit">
        <input type="hidden" name="id" id="ec_id">
        <div class="field"><label>Name</label><input type="text" name="name" id="ec_name" class="input" required maxlength="80"></div>
        <div class="field"><label>Slug</label><input type="text" name="slug" id="ec_slug" class="input" required maxlength="80"></div>
        <div class="field"><label>Description</label><textarea name="description" id="ec_desc" class="textarea" maxlength="255"></textarea></div>
        <div class="field"><label>Sort order</label><input type="number" name="sort_order" id="ec_order" class="input"></div>
      </div>
      <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>Cancel</button><button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Save</button></div>
    </form>
  </div>
</div>

<div class="modal-backdrop" id="catDelModal">
  <div class="modal-box">
    <div class="modal-head"><h3 style="color:#ef4444;"><i class="fas fa-triangle-exclamation"></i> Delete category</h3><button class="x" type="button" data-close><i class="fas fa-xmark"></i></button></div>
    <form method="post">
      <div class="modal-body">
        <input type="hidden" name="csrf" value="<?php echo csrfToken(); ?>">
        <input type="hidden" name="action" value="cat_delete">
        <input type="hidden" name="id" id="dc_id">
        <p style="font-size:14px;line-height:1.5;">Delete <strong id="dc_name"></strong>?</p>
        <div id="dc_reassign_block" style="display:none;margin-top:14px;">
          <p style="font-size:13px;color:var(--text-soft);margin-bottom:8px;"><strong id="dc_count"></strong> video(s) are in this category. Move them to:</p>
          <select name="reassign_to" id="dc_reassign" class="input" style="width:100%;">
            <option value="">— Uncategorized —</option>
            <?php foreach ($cats as $c): ?><option value="<?php echo (int)$c['id']; ?>" data-self="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>Cancel</button><button type="submit" class="btn" style="background:#ef4444;color:white;"><i class="fas fa-trash"></i> Delete</button></div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- ==================== ALBUMS ==================== -->
<?php if ($tab === 'albums'): ?>
<?php if (empty($rows)): ?>
  <div class="panel"><div class="empty-mini"><i class="fas fa-images"></i>No albums.</div></div>
<?php else: ?>
<div class="album-grid">
  <?php foreach ($rows as $a):
    $cover = !empty($a['image']) ? 'acatch/' . rawurlencode($a['image']) : '';
  ?>
  <div class="album-card">
    <div class="album-cover">
      <?php if ($cover): ?><img src="<?php echo htmlspecialchars($cover); ?>" alt="" onerror="this.style.display='none'"><?php endif; ?>
      <span class="pc"><i class="fas fa-images"></i> <?php echo (int)$a['pc']; ?></span>
    </div>
    <div class="album-body">
      <div class="album-name"><?php echo htmlspecialchars($a['name']); ?></div>
      <div class="album-desc"><?php echo htmlspecialchars(mb_strimwidth($a['adesc'] ?? '', 0, 60, '…')); ?></div>
    </div>
    <div class="album-actions">
      <a href="addfiles.php?id=<?php echo (int)$a['albumid']; ?>" class="primary"><i class="fas fa-plus"></i> Photos</a>
      <button class="edit" type="button" data-edit-album='<?php echo htmlspecialchars(json_encode([
        'id'=>(int)$a['albumid'],'name'=>$a['name'],'adesc'=>$a['adesc'],
      ]), ENT_QUOTES); ?>'><i class="fas fa-pen"></i></button>
      <form method="post" class="js-del" style="display:inline;">
        <input type="hidden" name="csrf" value="<?php echo csrfToken(); ?>">
        <input type="hidden" name="action" value="album_delete">
        <input type="hidden" name="id" value="<?php echo (int)$a['albumid']; ?>">
        <button type="submit" class="danger" data-confirm="Delete album '<?php echo htmlspecialchars($a['name']); ?>' AND its <?php echo (int)$a['pc']; ?> photos?"><i class="fas fa-trash"></i></button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="modal-backdrop" id="albumModal">
  <div class="modal-box">
    <div class="modal-head"><h3><i class="fas fa-pen" style="color:#ec4899;"></i> Edit album</h3><button class="x" type="button" data-close><i class="fas fa-xmark"></i></button></div>
    <form method="post">
      <div class="modal-body">
        <input type="hidden" name="csrf" value="<?php echo csrfToken(); ?>">
        <input type="hidden" name="action" value="album_edit">
        <input type="hidden" name="id" id="a_id">
        <div class="field"><label>Name</label><input type="text" name="name" id="a_name" class="input" required maxlength="200"></div>
        <div class="field"><label>Description</label><textarea name="adesc" id="a_desc" class="textarea" maxlength="500"></textarea></div>
      </div>
      <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>Cancel</button><button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Save</button></div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- ==================== FOLDERS ==================== -->
<?php if ($tab === 'folders'): ?>
<?php
  $by_parent = [];
  foreach ($rows as $f) $by_parent[(int)($f['parent_folder_id'] ?? 0)][] = $f;
  function render_folder_row($f, $by_parent, $depth = 0) {
?>
    <div class="f-row depth-<?php echo $depth; ?>">
      <div class="f-icon <?php echo $depth === 0 ? 'main' : 'sub'; ?>"><i class="fas fa-folder<?php echo $depth>0?'-open':''; ?>"></i></div>
      <div class="f-info">
        <div class="f-name"><?php echo htmlspecialchars($f['name']); ?></div>
        <?php if (!empty($f['adesc'])): ?><div class="f-desc"><?php echo htmlspecialchars(mb_strimwidth($f['adesc'], 0, 80, '…')); ?></div><?php endif; ?>
      </div>
      <?php if ((int)$f['file_count'] > 0): ?><span class="pill folder"><i class="fas fa-file"></i> <?php echo (int)$f['file_count']; ?></span><?php endif; ?>
      <?php if ((int)$f['sub_count'] > 0): ?><span class="pill quiz"><i class="fas fa-folder"></i> <?php echo (int)$f['sub_count']; ?></span><?php endif; ?>
      <div class="row-actions">
        <a href="../Documents/parent_folder_details.php?folder_id=<?php echo (int)$f['albumid']; ?>" target="_blank"><i class="fas fa-eye"></i></a>
        <button class="edit" type="button" data-edit-folder='<?php echo htmlspecialchars(json_encode([
          'id'=>(int)$f['albumid'],'name'=>$f['name'],'adesc'=>$f['adesc'],
        ]), ENT_QUOTES); ?>'><i class="fas fa-pen"></i></button>
        <form method="post" class="js-del" style="display:inline;">
          <input type="hidden" name="csrf" value="<?php echo csrfToken(); ?>">
          <input type="hidden" name="action" value="folder_delete">
          <input type="hidden" name="id" value="<?php echo (int)$f['albumid']; ?>">
          <button type="submit" class="danger" data-confirm="Delete folder '<?php echo htmlspecialchars($f['name']); ?>' and ALL its subfolders + files?"><i class="fas fa-trash"></i></button>
        </form>
      </div>
    </div>
<?php
    if (!empty($by_parent[(int)$f['albumid']])) {
      foreach ($by_parent[(int)$f['albumid']] as $child) render_folder_row($child, $by_parent, min($depth + 1, 2));
    }
  }
?>
<div class="tbl-wrap">
  <?php if (empty($by_parent[0] ?? [])): ?>
    <div class="empty-mini"><i class="fas fa-folder-open"></i>No folders.</div>
  <?php else: foreach ($by_parent[0] ?? [] as $top): render_folder_row($top, $by_parent); endforeach; endif; ?>
</div>

<div class="modal-backdrop" id="folderModal">
  <div class="modal-box">
    <div class="modal-head"><h3><i class="fas fa-pen" style="color:#10b981;"></i> Edit folder</h3><button class="x" type="button" data-close><i class="fas fa-xmark"></i></button></div>
    <form method="post">
      <div class="modal-body">
        <input type="hidden" name="csrf" value="<?php echo csrfToken(); ?>">
        <input type="hidden" name="action" value="folder_edit">
        <input type="hidden" name="id" id="fd_id">
        <div class="field"><label>Folder name</label><input type="text" name="name" id="fd_name" class="input" required maxlength="200"></div>
        <div class="field"><label>Description</label><textarea name="adesc" id="fd_desc" class="textarea" maxlength="500"></textarea></div>
      </div>
      <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>Cancel</button><button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Save</button></div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- ==================== FILES ==================== -->
<?php if ($tab === 'files'): ?>
<div class="tbl-wrap">
  <table class="crud">
    <thead><tr><th>Type</th><th>File</th><th>Folder</th><th>AI</th><th style="text-align:right;">Actions</th></tr></thead>
    <tbody>
    <?php if (empty($rows)): ?>
      <tr><td colspan="5"><div class="empty-mini"><i class="fas fa-file"></i>No files.</div></td></tr>
    <?php else: foreach ($rows as $f):
      $ext = strtolower(pathinfo($f['file_name'], PATHINFO_EXTENSION));
      list($ico, $label, $color) = file_type_meta($ext);
      $ai_supported = in_array($ext, ['pdf','docx','txt','md','log','csv']);
    ?>
      <tr>
        <td><span class="type-pill" style="background: <?php echo $color; ?>"><?php echo $label; ?></span></td>
        <td>
          <div class="crud-title"><?php echo htmlspecialchars($f['file_desc'] ?: $f['file_name']); ?></div>
          <div class="crud-id">#<?php echo (int)$f['file_id']; ?> · <?php echo htmlspecialchars($f['file_name']); ?></div>
        </td>
        <td><span class="pill folder"><i class="fas fa-folder"></i> <?php echo htmlspecialchars(folder_label($f['folder_id'], $all_folders)); ?></span></td>
        <td>
          <?php if (!$ai_supported): ?>
            <span style="color:var(--muted);font-size:11px;">—</span>
          <?php elseif (!empty($f['extract_id'])): ?>
            <span class="pill ai-ready" title="<?php echo (int)$f['e_words']; ?> words indexed"><i class="fas fa-wand-magic-sparkles"></i> ready</span>
          <?php else: ?>
            <button type="button" class="ai-btn" data-extract="<?php echo (int)$f['file_id']; ?>" data-name="<?php echo htmlspecialchars($f['file_name'], ENT_QUOTES); ?>">
              <i class="fas fa-wand-magic-sparkles"></i> Extract
            </button>
          <?php endif; ?>
        </td>
        <td>
          <div class="row-actions">
            <a href="../Documents/view_file.php?file_id=<?php echo (int)$f['file_id']; ?>" target="_blank"><i class="fas fa-eye"></i></a>
            <button class="edit" type="button" data-edit-file='<?php echo htmlspecialchars(json_encode([
              'id'=>(int)$f['file_id'],'desc'=>$f['file_desc'],'vlink'=>$f['video_link'],'folder'=>(int)$f['folder_id'],'name'=>$f['file_name'],
            ]), ENT_QUOTES); ?>'><i class="fas fa-pen"></i></button>
            <form method="post" class="js-del" style="display:inline;">
              <input type="hidden" name="csrf" value="<?php echo csrfToken(); ?>">
              <input type="hidden" name="action" value="file_delete">
              <input type="hidden" name="id" value="<?php echo (int)$f['file_id']; ?>">
              <button type="submit" class="danger" data-confirm="Delete this file?"><i class="fas fa-trash"></i></button>
            </form>
          </div>
        </td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<div class="modal-backdrop" id="fileModal">
  <div class="modal-box">
    <div class="modal-head"><h3><i class="fas fa-pen" style="color:var(--brand-1);"></i> Edit file</h3><button class="x" type="button" data-close><i class="fas fa-xmark"></i></button></div>
    <form method="post">
      <div class="modal-body">
        <input type="hidden" name="csrf" value="<?php echo csrfToken(); ?>">
        <input type="hidden" name="action" value="file_edit">
        <input type="hidden" name="id" id="fl_id">
        <div class="field"><label>Filename (read-only)</label><input type="text" id="fl_name" class="input" disabled></div>
        <div class="field"><label>Title / description</label><input type="text" name="file_desc" id="fl_desc" class="input" maxlength="255"></div>
        <div class="field"><label>Move to folder</label>
          <select name="folder_id" id="fl_folder" class="input" required>
            <?php foreach ($all_folders as $f): ?><option value="<?php echo (int)$f['albumid']; ?>"><?php echo htmlspecialchars(folder_label($f['albumid'], $all_folders)); ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label>Companion video link</label><input type="url" name="video_link" id="fl_vlink" class="input" placeholder="https://…"></div>
      </div>
      <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>Cancel</button><button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Save</button></div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- ==================== USERS ==================== -->
<?php if ($tab === 'users'): ?>

<div class="users-header">
  <div>
    <h3 style="font-size:16px;font-weight:700;"><i class="fas fa-users" style="color:var(--brand-1);margin-right:8px;"></i> All users</h3>
    <p style="color:var(--text-soft);font-size:12px;margin-top:4px;">Total: <?php echo count($rows); ?> · Only admins can create new accounts.</p>
  </div>
  <button type="button" class="btn btn-primary" onclick="openModal('addUserModal')"><i class="fas fa-user-plus"></i> Add user</button>
</div>

<div class="tbl-wrap">
  <table class="crud">
    <thead><tr><th>User</th><th>Email</th><th>Role</th><th>Last login</th><th style="text-align:right;">Actions</th></tr></thead>
    <tbody>
    <?php if (empty($rows)): ?>
      <tr><td colspan="5"><div class="empty-mini"><i class="fas fa-users"></i>No users.</div></td></tr>
    <?php else: foreach ($rows as $u):
      $disp = $u['full_name'] ?: explode('@', $u['email'])[0];
      $init = strtoupper(mb_substr($disp, 0, 1));
      $is_me = ((int)$u['id'] === (int)$admin['id']);
    ?>
      <tr>
        <td>
          <div class="name-cell">
            <div class="av"><?php echo htmlspecialchars($init); ?></div>
            <div>
              <div class="crud-title"><?php echo htmlspecialchars($disp); ?> <?php if ($is_me): ?><span style="color:var(--muted);font-size:11px;font-weight:500;">(you)</span><?php endif; ?></div>
              <div class="crud-id">#<?php echo (int)$u['id']; ?></div>
            </div>
          </div>
        </td>
        <td><?php echo htmlspecialchars($u['email']); ?></td>
        <td><span class="pill role-<?php echo $u['role']; ?>"><?php echo htmlspecialchars($u['role']); ?></span></td>
        <td style="font-size:12px;color:var(--text-soft);"><?php echo $u['last_login'] ? date('M j, Y H:i', strtotime($u['last_login'])) : '—'; ?></td>
        <td>
          <div class="row-actions">
            <button class="edit" type="button" data-edit-user='<?php echo htmlspecialchars(json_encode([
              'id'=>(int)$u['id'],'name'=>$disp,'email'=>$u['email'],'role'=>$u['role'],'is_me'=>$is_me,
            ]), ENT_QUOTES); ?>'><i class="fas fa-pen"></i> Manage</button>
            <?php if (!$is_me): ?>
              <form method="post" class="js-del" style="display:inline;">
                <input type="hidden" name="csrf" value="<?php echo csrfToken(); ?>">
                <input type="hidden" name="action" value="user_delete">
                <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
                <button type="submit" class="danger" data-confirm="Delete user <?php echo htmlspecialchars($u['email']); ?>?"><i class="fas fa-trash"></i></button>
              </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<div class="modal-backdrop" id="addUserModal">
  <div class="modal-box">
    <div class="modal-head"><h3><i class="fas fa-user-plus" style="color:var(--brand-1);"></i> Add new user</h3><button class="x" type="button" data-close><i class="fas fa-xmark"></i></button></div>
    <form method="post" autocomplete="off">
      <div class="modal-body">
        <input type="hidden" name="csrf" value="<?php echo csrfToken(); ?>">
        <input type="hidden" name="action" value="user_add">
        <p style="font-size:13px;color:var(--text-soft);margin-bottom:14px;">Share the password with the user and ask them to change it on first sign-in.</p>
        <div class="field"><label>Email</label><input type="email" name="email" class="input" required placeholder="user@example.com" autocomplete="off"></div>
        <div class="field"><label>Full name</label><input type="text" name="full_name" class="input" required placeholder="Jane Doe" autocomplete="off"></div>
        <div class="field"><label>Password</label>
          <div style="display:flex;gap:8px;">
            <input type="text" name="password" id="newPw" class="input" required minlength="6" placeholder="Min 6 characters" autocomplete="new-password" style="flex:1;">
            <button type="button" class="btn btn-ghost" onclick="generatePw()" title="Generate"><i class="fas fa-wand-magic-sparkles"></i></button>
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
          <div class="field" style="margin-bottom:0;"><label>Role</label>
            <select name="role" class="input"><option value="user">user</option><option value="admin">admin</option></select>
          </div>
          <div class="field" style="margin-bottom:0;"><label>Group <span style="color:var(--muted);font-weight:500;text-transform:none;letter-spacing:0;">(optional)</span></label><input type="text" name="group_name" class="input" placeholder="e.g. Sales"></div>
        </div>
      </div>
      <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>Cancel</button><button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Create user</button></div>
    </form>
  </div>
</div>

<div class="modal-backdrop" id="userModal">
  <div class="modal-box">
    <div class="modal-head"><h3><i class="fas fa-user-gear" style="color:var(--brand-1);"></i> Manage user</h3><button class="x" type="button" data-close><i class="fas fa-xmark"></i></button></div>
    <div class="modal-body">
      <p style="font-size:14px;margin-bottom:14px;">User <strong id="u_name"></strong> — <span style="color:var(--text-soft);" id="u_email"></span></p>

      <form method="post" style="border:1px solid var(--border); border-radius:10px; padding:14px; margin-bottom:12px;">
        <input type="hidden" name="csrf" value="<?php echo csrfToken(); ?>">
        <input type="hidden" name="action" value="user_role">
        <input type="hidden" name="id" id="u_id_r">
        <p style="font-size:12px; font-weight:700; color:var(--text-soft); text-transform:uppercase; letter-spacing:.05em; margin-bottom:8px;">Change role</p>
        <div style="display:flex; gap:8px; align-items:center;">
          <select name="role" id="u_role" class="input" style="flex:1;">
            <option value="user">user</option>
            <option value="admin">admin</option>
          </select>
          <button type="submit" class="btn btn-primary" style="padding:8px 14px;"><i class="fas fa-arrow-right"></i></button>
        </div>
      </form>

      <form method="post" style="border:1px solid var(--border); border-radius:10px; padding:14px;">
        <input type="hidden" name="csrf" value="<?php echo csrfToken(); ?>">
        <input type="hidden" name="action" value="user_password">
        <input type="hidden" name="id" id="u_id_p">
        <p style="font-size:12px; font-weight:700; color:var(--text-soft); text-transform:uppercase; letter-spacing:.05em; margin-bottom:8px;">Reset password</p>
        <div style="display:flex; gap:8px; align-items:center;">
          <input type="text" name="password" class="input" style="flex:1;" placeholder="New password (min 6 chars)" minlength="6" required>
          <button type="submit" class="btn btn-primary" style="padding:8px 14px;"><i class="fas fa-key"></i></button>
        </div>
      </form>
    </div>
    <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>Close</button></div>
  </div>
</div>
<?php endif; ?>

<!-- Floating AI toast (used by transcribe/summarize buttons) -->
<div class="ai-toast" id="aiToast">
  <div class="tic"><i class="fas fa-wand-magic-sparkles"></i></div>
  <div class="ttxt" id="aiToastTxt">Working…</div>
</div>

<script>
// Generate a random password (for Add User modal)
function generatePw() {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
  let pw = '';
  for (let i = 0; i < 12; i++) pw += chars[Math.floor(Math.random() * chars.length)];
  document.getElementById('newPw').value = pw;
}

// ─── AI: Transcribe video ────────────────────────────────────────
function aiToast(msg, type) {
  const t = document.getElementById('aiToast');
  document.getElementById('aiToastTxt').innerHTML = msg;
  t.className = 'ai-toast show' + (type ? ' ' + type : '');
  if (type === 'ok' || type === 'error') setTimeout(() => t.classList.remove('show'), 5000);
}
document.querySelectorAll('[data-transcribe]').forEach(btn => {
  btn.addEventListener('click', async () => {
    const vid   = btn.getAttribute('data-transcribe');
    const title = btn.getAttribute('data-title');
    if (!confirm(`Transcribe "${title}" with AI?\n\nThis may take 30–90 seconds depending on video length.`)) return;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner spin"></i> Transcribing…';
    aiToast(`<strong>Transcribing "${title}"</strong>Extracting audio and sending to Whisper…`);
    try {
      const fd = new FormData();
      fd.append('csrf', '<?php echo csrfToken(); ?>');
      fd.append('video_id', vid);
      const r  = await fetch('transcribe.php', { method: 'POST', body: fd });
      const js = await r.json();
      if (!js.ok) throw new Error(js.error || 'Unknown error');
      aiToast(`<strong>Transcribed ✓</strong>${js.chars.toLocaleString()} chars · ${js.duration}s audio · lang=${js.language}`, 'ok');
      // Swap button → ready pill
      const td = btn.parentNode;
      td.innerHTML = '<span class="pill ai-ready" title="Transcribed (' + js.duration + 's)"><i class="fas fa-wand-magic-sparkles"></i> ready</span>';
    } catch (e) {
      console.error(e);
      aiToast(`<strong>Transcription failed</strong>${e.message}`, 'error');
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-wand-magic-sparkles"></i> Transcribe';
    }
  });
});

// ─── AI: Extract document text ───────────────────────────────────
document.querySelectorAll('[data-extract]').forEach(btn => {
  btn.addEventListener('click', async () => {
    const fid  = btn.getAttribute('data-extract');
    const name = btn.getAttribute('data-name');
    if (!confirm(`Extract text from "${name}" for AI Q&A?\n\nFor PDFs, requires admin/lib/pdfparser.php to be installed.`)) return;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner spin"></i> Extracting…';
    aiToast(`<strong>Extracting "${name}"</strong>Reading the document…`);
    try {
      const fd = new FormData();
      fd.append('csrf', '<?php echo csrfToken(); ?>');
      fd.append('file_id', fid);
      const r  = await fetch('extract_text.php', { method: 'POST', body: fd });
      const js = await r.json();
      if (!js.ok) throw new Error(js.error || 'Unknown error');
      aiToast(`<strong>Extracted ✓</strong>${js.words.toLocaleString()} words · ${js.pages} pages — users can now Ask AI on this file`, 'ok');
      btn.parentNode.innerHTML = '<span class="pill ai-ready" title="' + js.words + ' words"><i class="fas fa-wand-magic-sparkles"></i> ready</span>';
    } catch (e) {
      aiToast(`<strong>Extraction failed</strong>${e.message}`, 'error');
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-wand-magic-sparkles"></i> Extract';
    }
  });
});

// Modal helpers
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeAll() { document.querySelectorAll('.modal-backdrop.open').forEach(m => m.classList.remove('open')); }
document.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', closeAll));
document.querySelectorAll('.modal-backdrop').forEach(m => m.addEventListener('click', e => { if (e.target === m) closeAll(); }));
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeAll(); });

// Delete confirmations (data-confirm)
document.querySelectorAll('button[data-confirm]').forEach(b => {
  b.closest('form')?.addEventListener('submit', e => {
    if (!confirm(b.getAttribute('data-confirm') + '\n\nThis cannot be undone.')) e.preventDefault();
  });
});

// Edit-video
document.querySelectorAll('[data-edit-video]').forEach(b => b.addEventListener('click', () => {
  const d = JSON.parse(b.getAttribute('data-edit-video'));
  document.getElementById('v_id').value    = d.id;
  document.getElementById('v_title').value = d.title;
  document.getElementById('v_des').value   = d.des || '';
  document.getElementById('v_cat').value   = d.cat || '';
  openModal('vidModal');
}));
// Edit-cat
document.querySelectorAll('[data-edit-cat]').forEach(b => b.addEventListener('click', () => {
  const d = JSON.parse(b.getAttribute('data-edit-cat'));
  document.getElementById('ec_id').value    = d.id;
  document.getElementById('ec_name').value  = d.name;
  document.getElementById('ec_slug').value  = d.slug;
  document.getElementById('ec_desc').value  = d.description || '';
  document.getElementById('ec_order').value = d.sort_order;
  openModal('catEditModal');
}));
document.querySelectorAll('[data-del-cat]').forEach(b => b.addEventListener('click', () => {
  const d = JSON.parse(b.getAttribute('data-del-cat'));
  document.getElementById('dc_id').value         = d.id;
  document.getElementById('dc_name').textContent = d.name;
  document.getElementById('dc_count').textContent = d.count;
  document.getElementById('dc_reassign_block').style.display = d.count > 0 ? 'block' : 'none';
  document.querySelectorAll('#dc_reassign option[data-self]').forEach(o => {
    o.style.display = (parseInt(o.getAttribute('data-self'),10) === d.id) ? 'none' : '';
  });
  openModal('catDelModal');
}));
// Edit-album
document.querySelectorAll('[data-edit-album]').forEach(b => b.addEventListener('click', () => {
  const d = JSON.parse(b.getAttribute('data-edit-album'));
  document.getElementById('a_id').value   = d.id;
  document.getElementById('a_name').value = d.name;
  document.getElementById('a_desc').value = d.adesc || '';
  openModal('albumModal');
}));
// Edit-folder
document.querySelectorAll('[data-edit-folder]').forEach(b => b.addEventListener('click', () => {
  const d = JSON.parse(b.getAttribute('data-edit-folder'));
  document.getElementById('fd_id').value   = d.id;
  document.getElementById('fd_name').value = d.name;
  document.getElementById('fd_desc').value = d.adesc || '';
  openModal('folderModal');
}));
// Edit-file
document.querySelectorAll('[data-edit-file]').forEach(b => b.addEventListener('click', () => {
  const d = JSON.parse(b.getAttribute('data-edit-file'));
  document.getElementById('fl_id').value     = d.id;
  document.getElementById('fl_name').value   = d.name;
  document.getElementById('fl_desc').value   = d.desc || '';
  document.getElementById('fl_vlink').value  = d.vlink || '';
  document.getElementById('fl_folder').value = d.folder;
  openModal('fileModal');
}));
// Edit-user
document.querySelectorAll('[data-edit-user]').forEach(b => b.addEventListener('click', () => {
  const d = JSON.parse(b.getAttribute('data-edit-user'));
  document.getElementById('u_id_r').value = d.id;
  document.getElementById('u_id_p').value = d.id;
  document.getElementById('u_name').textContent  = d.name;
  document.getElementById('u_email').textContent = d.email;
  document.getElementById('u_role').value = d.role;
  openModal('userModal');
}));
</script>

    </main>
  </div>
</div>
</body>
</html>