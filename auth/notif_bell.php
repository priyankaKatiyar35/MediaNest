<?php
/**
 * MediaNest — Notifications Bell (drop-in include)
 * --------------------------------------------------------------
 * Usage: place this anywhere inside your nav (typically right before the
 * user/avatar/logout area):
 *
 *     <?php include __DIR__ . '/../auth/notif_bell.php'; ?>
 *
 * Renders a bell icon + unread badge. Clicking opens a dropdown with
 * the user's latest notifications.
 *
 * Self-contained: includes its own CSS + JS, no theme assumptions.
 */
if (!function_exists('currentUser')) require_once __DIR__ . '/auth.php';
$__bell_user = function_exists('currentUser') ? currentUser() : null;
if (!$__bell_user) return; // nothing to render for guests

// Path to the JSON API — `notif_api.php` lives in the same folder as this file
$__bell_api = '../auth/notif_api.php';
// If we ARE in auth/ already (rare, e.g. login page) the path differs
if (basename(dirname($_SERVER['SCRIPT_NAME'])) === 'auth') $__bell_api = 'notif_api.php';
?>

<style>
.mn-bell { position: relative; display: inline-block; }
.mn-bell-btn { width: 38px; height: 38px; border-radius: 10px; background: transparent; border: 1px solid rgba(0,0,0,.08); color: inherit; display: grid; place-items: center; cursor: pointer; position: relative; transition: all .15s; }
.mn-bell-btn:hover { background: rgba(0,0,0,.04); }
html.dark .mn-bell-btn { border-color: rgba(255,255,255,.08); }
html.dark .mn-bell-btn:hover { background: rgba(255,255,255,.05); }
.mn-bell-count { position: absolute; top: -5px; right: -5px; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 999px; background: linear-gradient(135deg, #ef4444, #dc2626); color: white; font-size: 10px; font-weight: 700; display: grid; place-items: center; border: 2px solid var(--bg, white); animation: mn-bell-pop .3s ease; }
html.dark .mn-bell-count { border-color: var(--bg, #0a0e1a); }
@keyframes mn-bell-pop { from { transform: scale(0); } to { transform: scale(1); } }
.mn-bell-count.hidden { display: none; }

.mn-bell-panel { position: absolute; top: calc(100% + 8px); right: 0; width: 360px; max-width: calc(100vw - 24px); max-height: 480px; background: var(--bg-elev, white); border: 1px solid var(--border, rgba(0,0,0,.08)); border-radius: 14px; box-shadow: 0 20px 60px rgba(0,0,0,.18); z-index: 200; display: none; flex-direction: column; overflow: hidden; }
.mn-bell-panel.open { display: flex; animation: mn-panel-in .18s ease; }
@keyframes mn-panel-in { from { opacity: 0; transform: translateY(-4px); } to { opacity: 1; transform: translateY(0); } }
.mn-bell-head { padding: 14px 16px; border-bottom: 1px solid var(--border, rgba(0,0,0,.08)); display: flex; align-items: center; justify-content: space-between; }
.mn-bell-head h3 { font-size: 14px; font-weight: 700; margin: 0; }
.mn-bell-head button { background: transparent; border: 0; font: inherit; font-size: 11px; color: var(--brand-1, #6366f1); cursor: pointer; font-weight: 600; }
.mn-bell-head button:hover { text-decoration: underline; }
.mn-bell-body { overflow-y: auto; flex: 1; }
.mn-bell-empty { padding: 40px 20px; text-align: center; color: var(--muted, #94a3b8); }
.mn-bell-empty i { font-size: 28px; opacity: .4; margin-bottom: 10px; display: block; }
.mn-bell-empty p { font-size: 13px; margin: 0; }

.mn-notif { display: flex; gap: 11px; padding: 11px 14px; border-bottom: 1px solid var(--border, rgba(0,0,0,.06)); cursor: pointer; transition: background .15s; text-decoration: none; color: inherit; }
.mn-notif:hover { background: var(--bg, #f8fafc); }
.mn-notif:last-child { border-bottom: 0; }
.mn-notif.unread { background: linear-gradient(135deg, rgba(99,102,241,.04), rgba(168,85,247,.02)); }
.mn-notif.unread:hover { background: rgba(99,102,241,.08); }
.mn-notif-ic { width: 32px; height: 32px; border-radius: 9px; display: grid; place-items: center; color: white; flex-shrink: 0; font-size: 13px; }
.mn-notif-ic.video { background: linear-gradient(135deg, #0ea5e9, #6366f1); }
.mn-notif-ic.album { background: linear-gradient(135deg, #ec4899, #f43f5e); }
.mn-notif-ic.doc   { background: linear-gradient(135deg, #10b981, #059669); }
.mn-notif-ic.quiz  { background: linear-gradient(135deg, #f59e0b, #d97706); }
.mn-notif-ic.bell  { background: linear-gradient(135deg, #a855f7, #ec4899); }
.mn-notif-body { flex: 1; min-width: 0; }
.mn-notif-title { font-size: 13px; font-weight: 600; line-height: 1.3; margin-bottom: 2px; color: var(--text, #0f172a); }
.mn-notif-sub { font-size: 12px; color: var(--text-soft, #64748b); line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.mn-notif-ago { font-size: 10px; color: var(--muted, #94a3b8); margin-top: 4px; }
.mn-notif-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--brand-1, #6366f1); flex-shrink: 0; align-self: center; }
.mn-notif:not(.unread) .mn-notif-dot { display: none; }
</style>

<div class="mn-bell">
  <button class="mn-bell-btn" id="mnBellBtn" aria-label="Notifications">
    <i class="fas fa-bell"></i>
    <span class="mn-bell-count hidden" id="mnBellCount">0</span>
  </button>
  <div class="mn-bell-panel" id="mnBellPanel">
    <div class="mn-bell-head">
      <h3>Notifications</h3>
      <button type="button" id="mnBellMarkAll">Mark all read</button>
    </div>
    <div class="mn-bell-body" id="mnBellBody">
      <div class="mn-bell-empty"><i class="fas fa-bell-slash"></i><p>Loading…</p></div>
    </div>
  </div>
</div>

<script>
(function() {
  const btn   = document.getElementById('mnBellBtn');
  const panel = document.getElementById('mnBellPanel');
  const body  = document.getElementById('mnBellBody');
  const cntEl = document.getElementById('mnBellCount');
  const allBtn = document.getElementById('mnBellMarkAll');
  const API   = <?php echo json_encode($__bell_api); ?>;

  let loaded = false;

  function setCount(n) {
    if (n > 0) {
      cntEl.textContent = n > 99 ? '99+' : n;
      cntEl.classList.remove('hidden');
    } else {
      cntEl.classList.add('hidden');
    }
  }

  function iconFor(type) {
    if (type.indexOf('video') === 0) return ['video', 'fa-film'];
    if (type.indexOf('album') === 0) return ['album', 'fa-images'];
    if (type.indexOf('doc') === 0)   return ['doc',   'fa-file'];
    if (type.indexOf('quiz') === 0)  return ['quiz',  'fa-circle-question'];
    return ['bell', 'fa-bell'];
  }

  function esc(s) { return (s || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

  async function loadList() {
    body.innerHTML = '<div class="mn-bell-empty"><i class="fas fa-spinner fa-spin"></i><p>Loading…</p></div>';
    try {
      const r  = await fetch(API + '?action=list', { credentials: 'same-origin' });
      const js = await r.json();
      if (!js.ok) throw 0;
      setCount(js.unread);
      if (!js.items.length) {
        body.innerHTML = '<div class="mn-bell-empty"><i class="fas fa-bell-slash"></i><p>You\'re all caught up</p></div>';
        return;
      }
      body.innerHTML = js.items.map(n => {
        const [ic, iconClass] = iconFor(n.type);
        const url = n.link || '#';
        return `<a class="mn-notif ${n.is_read ? '' : 'unread'}" data-id="${n.id}" href="${esc(url)}">
          <div class="mn-notif-ic ${ic}"><i class="fas ${iconClass}"></i></div>
          <div class="mn-notif-body">
            <div class="mn-notif-title">${esc(n.title)}</div>
            ${n.body ? '<div class="mn-notif-sub">' + esc(n.body) + '</div>' : ''}
            <div class="mn-notif-ago">${esc(n.ago)}</div>
          </div>
          <span class="mn-notif-dot"></span>
        </a>`;
      }).join('');
      // Click → mark this one read (server-side), then follow link
      body.querySelectorAll('.mn-notif').forEach(el => {
        el.addEventListener('click', async (e) => {
          const id = el.getAttribute('data-id');
          if (id && el.classList.contains('unread')) {
            const fd = new FormData(); fd.append('id', id);
            fetch(API + '?action=read', { method: 'POST', body: fd, credentials: 'same-origin', keepalive: true });
          }
        });
      });
    } catch (e) {
      body.innerHTML = '<div class="mn-bell-empty"><i class="fas fa-circle-exclamation"></i><p>Could not load.</p></div>';
    }
  }

  // Poll count every 60s (silent — no UI flicker)
  async function pollCount() {
    try {
      const r  = await fetch(API + '?action=count', { credentials: 'same-origin' });
      const js = await r.json();
      if (js.ok) setCount(js.unread);
    } catch (e) {}
  }
  pollCount();
  setInterval(pollCount, 60000);

  btn.addEventListener('click', () => {
    const wasOpen = panel.classList.contains('open');
    if (wasOpen) { panel.classList.remove('open'); return; }
    panel.classList.add('open');
    if (!loaded) { loadList(); loaded = true; }
    else loadList(); // refresh on each open
  });

  allBtn.addEventListener('click', async (e) => {
    e.stopPropagation();
    try {
      await fetch(API + '?action=read_all', { method: 'POST', credentials: 'same-origin' });
      setCount(0);
      body.querySelectorAll('.mn-notif.unread').forEach(el => el.classList.remove('unread'));
    } catch (e) {}
  });

  // Close on outside click
  document.addEventListener('click', (e) => {
    if (!panel.classList.contains('open')) return;
    if (e.target.closest('.mn-bell')) return;
    panel.classList.remove('open');
  });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') panel.classList.remove('open'); });
})();
</script>