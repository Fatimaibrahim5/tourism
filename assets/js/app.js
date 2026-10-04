// Shared front-end behaviour: drawer menu, swipe navigation (REQ-20), confirmations, modals,
// client-side validation and trip sharing.
(function () {
  const T = window.I18N || {};
  const body = document.body;
  const rtl = document.documentElement.dir === 'rtl';

  // ----- Drawer -----
  const menuBtn = document.getElementById('menuBtn');
  const backdrop = document.getElementById('drawerBackdrop');
  if (menuBtn) menuBtn.addEventListener('click', () => body.classList.toggle('drawer-open'));
  if (backdrop) backdrop.addEventListener('click', () => body.classList.remove('drawer-open'));
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
      body.classList.remove('drawer-open');
      document.querySelectorAll('.modal.open').forEach(m => m.classList.remove('open'));
    }
  });

  // ----- Confirm before destructive / irreversible actions -----
  document.addEventListener('submit', e => {
    const msg = e.target.dataset.confirm;
    if (msg && !confirm(msg)) e.preventDefault();
  });
  document.querySelectorAll('a[data-confirm], button[data-confirm]').forEach(a =>
    a.addEventListener('click', e => { if (!confirm(a.dataset.confirm)) e.preventDefault(); }));

  // ----- Modals -----
  document.querySelectorAll('[data-modal]').forEach(btn =>
    btn.addEventListener('click', e => {
      e.preventDefault();
      const m = document.getElementById(btn.dataset.modal);
      if (m) m.classList.add('open');
    }));
  document.querySelectorAll('.modal').forEach(m => {
    m.addEventListener('click', e => { if (e.target === m || e.target.classList.contains('modal-close')) m.classList.remove('open'); });
  });

  // ----- Client-side validation (server validates again) -----
  document.querySelectorAll('form[data-validate]').forEach(form => {
    form.addEventListener('submit', e => {
      let ok = true;
      form.querySelectorAll('.field-error.js').forEach(n => n.remove());
      form.querySelectorAll('.invalid').forEach(n => n.classList.remove('invalid'));
      const fail = (input, msg) => {
        ok = false;
        input.classList.add('invalid');
        const d = document.createElement('div');
        d.className = 'field-error js';
        d.textContent = msg;
        input.insertAdjacentElement('afterend', d);
      };
      form.querySelectorAll('[required]').forEach(i => { if (!i.value.trim()) fail(i, T.required || 'Required'); });
      form.querySelectorAll('input[type=email]').forEach(i => {
        if (i.value && !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(i.value)) fail(i, T.invalid_email || 'Invalid email');
      });
      form.querySelectorAll('input[type=tel]').forEach(i => {
        if (i.value && !/^[+0-9 ()-]{6,20}$/.test(i.value)) fail(i, T.invalid_phone || 'Invalid phone');
      });
      const pw = form.querySelector('input[name=password]');
      const pw2 = form.querySelector('input[name=password2]');
      if (pw && pw2 && pw.value && pw.value.length < 8) fail(pw, T.pw_short || 'At least 8 characters');
      if (pw && pw2 && pw2.value !== pw.value) fail(pw2, T.pw_mismatch || 'Passwords do not match');
      if (!ok) {
        e.preventDefault();
        const first = form.querySelector('.invalid');
        if (first) first.focus();
      }
    });
  });

  // ----- Image preview for file inputs -----
  document.querySelectorAll('input[type=file][data-preview]').forEach(input => {
    input.addEventListener('change', () => {
      const img = document.getElementById(input.dataset.preview);
      if (img && input.files[0]) { img.src = URL.createObjectURL(input.files[0]); img.classList.remove('hidden'); }
    });
  });

  // ----- Share trip (use case step 12) -----
  document.querySelectorAll('[data-share]').forEach(btn => {
    btn.addEventListener('click', async () => {
      const data = { title: btn.dataset.title || document.title, text: btn.dataset.text || '', url: btn.dataset.share };
      try {
        if (navigator.share) { await navigator.share(data); return; }
        await navigator.clipboard.writeText(data.url);
        btn.textContent = T.link_copied || 'Link copied!';
      } catch (err) {
        if (err && err.name === 'AbortError') return; // user cancelled sharing → do nothing
        prompt(T.copy_link || 'Copy this link:', data.url);
      }
    });
  });

  // ----- Favorites (heart) without reloading the page -----
  function toast(msg) {
    let t = document.getElementById('toast');
    if (!t) { t = document.createElement('div'); t.id = 'toast'; t.className = 'toast'; body.appendChild(t); }
    t.textContent = msg;
    t.classList.add('show');
    clearTimeout(t._h);
    t._h = setTimeout(() => t.classList.remove('show'), 1800);
  }
  document.addEventListener('submit', async e => {
    const form = e.target;
    if (!form.classList.contains('fav-form') || e.defaultPrevented) return;
    e.preventDefault();
    const btn = form.querySelector('.fav-btn');
    btn.classList.add('pop');
    try {
      const res = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { 'X-Requested-With': 'fetch' } });
      if (res.status === 401) { location.href = (await res.json()).login; return; }
      if (!res.ok) throw new Error();
      const data = await res.json();
      btn.classList.toggle('on', data.on);
      btn.setAttribute('aria-pressed', data.on);
      btn.title = data.title;
      btn.firstElementChild.textContent = data.on ? '♥' : '♡';
      toast(data.msg);
      // On the profile's Favorites tab, removing a heart removes the tile
      const tile = form.closest('[data-fav-tile]');
      const count = document.getElementById('favCount');
      if (count) count.textContent = Math.max(0, parseInt(count.textContent, 10) + (data.on ? 1 : -1));
      if (tile && !data.on) {
        tile.classList.add('leaving');
        setTimeout(() => {
          tile.remove();
          const empty = document.getElementById('favEmpty');
          if (empty && !document.querySelector('[data-fav-tile]')) empty.classList.remove('hidden');
        }, 250);
      }
    } catch (err) {
      form.submit();
    } finally {
      setTimeout(() => btn.classList.remove('pop'), 300);
    }
  });

  // ----- Follow / unfollow a travel organizer without reloading -----
  document.addEventListener('submit', async e => {
    const form = e.target;
    if (!form.classList.contains('follow-form') || e.defaultPrevented) return;
    e.preventDefault();
    const btn = form.querySelector('.follow-btn');
    btn.disabled = true;
    try {
      const res = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { 'X-Requested-With': 'fetch' } });
      if (res.status === 401) { location.href = (await res.json()).login; return; }
      if (!res.ok) throw new Error();
      const data = await res.json();
      // Update every follow button for this organizer on the page
      const id = form.querySelector('input[name=organizer]').value;
      document.querySelectorAll('.follow-form').forEach(f => {
        if (f.querySelector('input[name=organizer]').value !== id) return;
        const b = f.querySelector('.follow-btn');
        b.classList.toggle('on', data.on);
        b.setAttribute('aria-pressed', data.on);
        b.textContent = data.label;
      });
      const count = document.getElementById('followersCount');
      if (count) count.textContent = data.followers;
      toast(data.msg);
    } catch (err) {
      form.submit();
    } finally {
      btn.disabled = false;
    }
  });

  // ----- Gesture-based navigation between main pages (REQ-20) -----
  const nav = document.getElementById('bottomNav');
  if (nav) {
    const pages = [...nav.querySelectorAll('a')].map(a => a.getAttribute('href'));
    const here = location.pathname.split('/').pop() || 'index.php';
    const idx = pages.indexOf(here);
    let sx = 0, sy = 0, tracking = false;
    const hint = document.createElement('div');
    hint.className = 'swipe-hint';
    body.appendChild(hint);
    document.addEventListener('touchstart', e => {
      // Don't hijack swipes on the map, horizontal tables or form fields
      if (idx < 0 || e.target.closest('#map, .table-wrap, input, textarea, select, .leaflet-container')) { tracking = false; return; }
      tracking = true; sx = e.touches[0].clientX; sy = e.touches[0].clientY;
    }, { passive: true });
    document.addEventListener('touchend', e => {
      if (!tracking) return;
      const dx = e.changedTouches[0].clientX - sx;
      const dy = e.changedTouches[0].clientY - sy;
      if (Math.abs(dx) < 80 || Math.abs(dy) > Math.abs(dx) * 0.6) return;
      // Swipe left → next page, swipe right → previous page (mirrored in RTL)
      let step = dx < 0 ? 1 : -1;
      if (rtl) step = -step;
      const next = pages[idx + step];
      if (next) {
        hint.textContent = step > 0 ? '›' : '‹';
        hint.style[step > 0 ? 'right' : 'left'] = '10px';
        hint.style.opacity = 1;
        location.href = next;
      }
    }, { passive: true });
  }
})();
