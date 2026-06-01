<?php
/**
 * MediaNest — Bookmark star button (drop-in include)
 * --------------------------------------------------------------
 * Usage:
 *   <?php
 *     $bm_type = 'video';
 *     $bm_id   = (int)$video['id'];
 *     include __DIR__ . '/../auth/bookmark_btn.php';
 *   ?>
 *
 * For lists/grids of items, you can render many buttons on one page —
 * each call below produces a unique button. State is checked in bulk
 * client-side on first paint.
 *
 * Self-contained: includes its own CSS + a small inline JS shim only
 * once per page. Multiple buttons share that one shim.
 */
if (!function_exists('currentUser')) require_once __DIR__ . '/auth.php';
$__bm_user = currentUser();
if (!$__bm_user) return;

$__bm_type = $bm_type ?? '';
$__bm_id   = (int)($bm_id ?? 0);
if (!in_array($__bm_type, ['video','album','file'], true) || $__bm_id <= 0) return;

if (!isset($GLOBALS['__bm_css_emitted'])) {
    $GLOBALS['__bm_css_emitted'] = true;
    // Resolve API path — same trick as the bell
    $__bm_api = '../auth/bookmark_api.php';
    if (basename(dirname($_SERVER['SCRIPT_NAME'])) === 'auth') $__bm_api = 'bookmark_api.php';
    ?>
    <style>
    .mn-bm { background: transparent; border: 1px solid rgba(0,0,0,.1); width: 36px; height: 36px; border-radius: 10px; cursor: pointer; display: inline-grid; place-items: center; color: var(--text-soft, #64748b); transition: all .15s; font-size: 14px; padding: 0; }
    html.dark .mn-bm { border-color: rgba(255,255,255,.08); }
    .mn-bm:hover { color: #f59e0b; border-color: #f59e0b; }
    .mn-bm.saved { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; border-color: transparent; }
    .mn-bm.saved:hover { color: white; filter: brightness(1.1); }
    .mn-bm i { transition: transform .15s; }
    .mn-bm.saved i { transform: scale(1.1); }
    .mn-bm.pulsing { animation: mn-bm-pulse .35s ease; }
    @keyframes mn-bm-pulse { 0%{ transform: scale(1);} 40%{ transform: scale(1.18);} 100%{ transform: scale(1);} }
    </style>
    <script>
    window.MN_BM_API = <?php echo json_encode($__bm_api); ?>;
    window.MN_BM = (function() {
        const cache = {};
        // Bulk-check state for all buttons on the page once they exist
        async function syncAll() {
            const btns = document.querySelectorAll('.mn-bm[data-bm-type]');
            // Group requests cheaply: call ?action=is per button. Small list of items
            // per page — fine. If you ever need bigger, batch this server-side.
            for (const b of btns) {
                const key = b.dataset.bmType + ':' + b.dataset.bmId;
                if (cache[key] === undefined) {
                    try {
                        const r = await fetch(window.MN_BM_API + '?action=is&type=' + b.dataset.bmType + '&id=' + b.dataset.bmId, { credentials: 'same-origin' });
                        const j = await r.json();
                        cache[key] = !!j.bookmarked;
                    } catch (e) { cache[key] = false; }
                }
                b.classList.toggle('saved', cache[key]);
                b.title = cache[key] ? 'Remove bookmark' : 'Bookmark';
            }
        }
        async function toggle(btn) {
            const type = btn.dataset.bmType, id = btn.dataset.bmId;
            const fd = new FormData();
            fd.append('type', type); fd.append('id', id);
            btn.classList.add('pulsing');
            setTimeout(() => btn.classList.remove('pulsing'), 350);
            try {
                const r = await fetch(window.MN_BM_API + '?action=toggle', { method: 'POST', body: fd, credentials: 'same-origin' });
                const j = await r.json();
                if (j.ok) {
                    const key = type + ':' + id;
                    cache[key] = !!j.bookmarked;
                    btn.classList.toggle('saved', j.bookmarked);
                    btn.title = j.bookmarked ? 'Remove bookmark' : 'Bookmark';
                }
            } catch (e) {}
        }
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.mn-bm[data-bm-type]');
            if (!btn) return;
            e.preventDefault(); e.stopPropagation();
            toggle(btn);
        });
        document.addEventListener('DOMContentLoaded', syncAll);
        // Also expose for dynamically-injected buttons later
        return { sync: syncAll, toggle };
    })();
    </script>
    <?php
}
?>
<button type="button" class="mn-bm" data-bm-type="<?php echo $__bm_type; ?>" data-bm-id="<?php echo $__bm_id; ?>" aria-label="Bookmark" title="Bookmark">
  <i class="fas fa-bookmark"></i>
</button>