/* ============================================
   MediaNest — Interactivity
   ============================================ */

document.addEventListener('DOMContentLoaded', () => {

  gsap.registerPlugin(ScrollTrigger);

  /* ---------- Theme toggle ---------- */
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
    toast(dark ? 'Dark mode on' : 'Light mode on');
  });

  /* ---------- Scroll progress bar ---------- */
  const progress = document.getElementById('scroll-progress');
  window.addEventListener('scroll', () => {
    const h = document.documentElement;
    const pct = (h.scrollTop / (h.scrollHeight - h.clientHeight)) * 100;
    progress.style.width = pct + '%';
  });

  /* ---------- Scroll-triggered animations ---------- */
  gsap.from('.feature-card', {
    opacity: 0, y: 40, scale: 0.96,
    stagger: 0.15, duration: 0.7, ease: 'power3.out',
    scrollTrigger: { trigger: '.feature-row', start: 'top 85%' }
  });

  gsap.from('.stats-row .stat', {
    opacity: 0, y: 20, stagger: 0.1, duration: 0.6,
    scrollTrigger: { trigger: '.stats-row', start: 'top 90%' }
  });

  gsap.from('.section-head', {
    opacity: 0, y: 30, duration: 0.7,
    scrollTrigger: { trigger: '.section-head', start: 'top 85%' }
  });

  /* ---------- Card tilt + glow follow ---------- */
  document.querySelectorAll('.feature-card').forEach(card => {
    card.addEventListener('mousemove', e => {
      const r = card.getBoundingClientRect();
      const x = e.clientX - r.left;
      const y = e.clientY - r.top;
      card.style.setProperty('--mx', x + 'px');
      card.style.setProperty('--my', y + 'px');

      const rx = ((y / r.height) - 0.5) * -6;
      const ry = ((x / r.width) - 0.5) * 6;
      gsap.to(card, { rotateX: rx, rotateY: ry, duration: 0.4, ease: 'power2.out', transformPerspective: 800 });
    });
    card.addEventListener('mouseleave', () => {
      gsap.to(card, { rotateX: 0, rotateY: 0, duration: 0.5, ease: 'power2.out' });
    });
  });

  /* ---------- Animated stat counters ---------- */
  const stats = document.querySelectorAll('.stat');
  ScrollTrigger.create({
    trigger: '.stats-row', start: 'top 85%', once: true,
    onEnter: () => {
      stats.forEach(stat => {
        const target = +stat.dataset.target;
        const suffix = stat.dataset.suffix || '';
        const el = stat.querySelector('.stat-num');
        const obj = { v: 0 };
        gsap.to(obj, {
          v: target, duration: 1.8, ease: 'power2.out',
          onUpdate: () => {
            const v = Math.round(obj.v);
            el.textContent = (v >= 1000 ? v.toLocaleString() : v) + suffix;
          }
        });
      });
    }
  });

  /* ---------- Ripple effect ---------- */
  document.querySelectorAll('.ripple').forEach(el => {
    el.addEventListener('click', e => {
      const r = el.getBoundingClientRect();
      const wave = document.createElement('span');
      wave.className = 'ripple-wave';
      const size = Math.max(r.width, r.height);
      wave.style.width = wave.style.height = size + 'px';
      wave.style.left = (e.clientX - r.left - size/2) + 'px';
      wave.style.top = (e.clientY - r.top - size/2) + 'px';
      el.appendChild(wave);
      setTimeout(() => wave.remove(), 600);
    });
  });

  /* ---------- Toast helper ---------- */
  const toastEl = document.getElementById('toast');
  let toastTimer;
  function toast(msg) {
    toastEl.textContent = msg;
    toastEl.hidden = false;
    requestAnimationFrame(() => toastEl.classList.add('show'));
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
      toastEl.classList.remove('show');
      setTimeout(() => toastEl.hidden = true, 250);
    }, 2200);
  }

  /* ---------- Search palette ---------- */
  const searchOverlay = document.getElementById('search-overlay');
  const searchInput = document.getElementById('search-input');
  const searchResults = document.getElementById('search-results');
  const searchToggle = document.getElementById('search-toggle');

  const searchItems = [
    { icon: 'fa-video', label: 'Video Gallery', url: 'Videos/index.php', tags: 'videos movies clips' },
    { icon: 'fa-camera', label: 'Photo Gallery', url: 'Photo/index.php', tags: 'photos images pictures' },
    { icon: 'fa-folder-open', label: 'Document Management', url: 'Documents/index.php', tags: 'documents files pdf' },
    { icon: 'fa-moon', label: 'Toggle theme', action: 'theme', tags: 'dark light mode' },
  ];

  function openSearch() {
    searchOverlay.hidden = false;
    setTimeout(() => searchInput.focus(), 50);
    renderSearch('');
  }
  function closeSearch() { searchOverlay.hidden = true; searchInput.value = ''; }

  function renderSearch(q) {
    const query = q.trim().toLowerCase();
    const matched = !query ? searchItems
      : searchItems.filter(i => (i.label + ' ' + i.tags).toLowerCase().includes(query));
    searchResults.innerHTML = matched.length
      ? matched.map((it, i) => `
          <li data-idx="${i}" class="${i === 0 ? 'active' : ''}">
            <i class="fas ${it.icon}"></i> ${it.label}
          </li>
        `).join('')
      : `<li class="empty">No matches found</li>`;
    searchResults.querySelectorAll('li[data-idx]').forEach(li => {
      li.addEventListener('click', () => triggerSearchItem(matched[+li.dataset.idx]));
    });
    searchResults._matched = matched;
  }
  function triggerSearchItem(it) {
    if (!it) return;
    if (it.url) { window.location.href = it.url; return; }
    if (it.action === 'theme') { closeSearch(); themeBtn.click(); }
  }
  searchToggle.addEventListener('click', openSearch);
  searchInput.addEventListener('input', e => renderSearch(e.target.value));
  searchInput.addEventListener('keydown', e => {
    const items = searchResults.querySelectorAll('li[data-idx]');
    let idx = [...items].findIndex(i => i.classList.contains('active'));
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      if (idx < items.length - 1) { items[idx]?.classList.remove('active'); items[idx + 1].classList.add('active'); }
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      if (idx > 0) { items[idx].classList.remove('active'); items[idx - 1].classList.add('active'); }
    } else if (e.key === 'Enter') {
      e.preventDefault();
      triggerSearchItem(searchResults._matched?.[idx >= 0 ? idx : 0]);
    }
  });

  document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
      e.preventDefault();
      searchOverlay.hidden ? openSearch() : closeSearch();
    }
    if (e.key === 'Escape' && !searchOverlay.hidden) closeSearch();
  });

  searchOverlay.addEventListener('click', e => {
    if (e.target === searchOverlay) closeSearch();
  });

   
  /* ---------- Easter egg: triple-click logo ---------- */
  let clicks = 0, clickTimer;
  document.querySelector('.mn-logo').addEventListener('click', e => {
    e.preventDefault();
    clicks++;
    clearTimeout(clickTimer);
    clickTimer = setTimeout(() => clicks = 0, 600);
    if (clicks >= 3) {
      clicks = 0;
      gsap.to('.mn-logo-mark', { rotate: 360, duration: 0.8, ease: 'power2.inOut' });
      toast('✨ You found it!');
    }
  });

});