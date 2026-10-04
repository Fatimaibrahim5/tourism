// Profile page: tabs, profile-photo upload, re-opening sheets after errors, password helper.
(function () {
  const T = window.I18N || {};

  // ----- Tabs (My trips / Favorites) -----
  const panels = document.querySelectorAll('.ig-panel');
  const tabLinks = document.querySelectorAll('[data-tab]');
  function showTab(name) {
    if (!document.getElementById('tab-' + name)) name = panels[0].id.replace('tab-', '');
    panels.forEach(p => p.classList.toggle('hidden', p.id !== 'tab-' + name));
    document.querySelectorAll('.ig-tabs [data-tab]').forEach(a => {
      const on = a.dataset.tab === name;
      a.classList.toggle('active', on);
      a.setAttribute('aria-selected', on);
    });
  }
  if (panels.length) {
    tabLinks.forEach(a => a.addEventListener('click', e => {
      e.preventDefault();
      history.replaceState(null, '', '#' + a.dataset.tab);
      showTab(a.dataset.tab);
      if (!a.closest('.ig-tabs')) document.querySelector('.ig-tabs').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }));
    const hash = location.hash.replace('#', '');
    showTab(document.getElementById('tab-' + hash) ? hash : panels[0].id.replace('tab-', ''));
  }

  // ----- Profile photo: upload as soon as a file is chosen -----
  document.querySelectorAll('input[data-autosubmit]').forEach(input =>
    input.addEventListener('change', () => { if (input.files.length) input.form.submit(); }));

  // ----- Re-open the sheet that had a validation error -----
  document.querySelectorAll('.modal[data-open]').forEach(m => m.classList.add('open'));

  // ----- Show / hide password -----
  document.querySelectorAll('.pw-field .eye').forEach(btn => btn.addEventListener('click', () => {
    const input = btn.previousElementSibling;
    input.type = input.type === 'password' ? 'text' : 'password';
    btn.classList.toggle('on', input.type === 'text');
  }));

  // ----- Password strength meter + live rules + match -----
  const pw = document.querySelector('input[data-strength]');
  const pw2 = document.getElementById('password2');
  if (pw) {
    const meter = document.getElementById(pw.dataset.strength);
    const match = document.getElementById('pwMatch');
    const rules = {
      len: v => v.length >= 8,
      letter: v => /[A-Za-z]/.test(v),
      digit: v => /\d/.test(v),
    };
    const update = () => {
      const v = pw.value;
      let score = 0;
      Object.entries(rules).forEach(([k, fn]) => {
        const ok = fn(v);
        if (ok) score++;
        const el = document.querySelector('[data-rule="' + k + '"]');
        if (el) { el.classList.toggle('ok', ok); el.textContent = (ok ? '✓ ' : '○ ') + el.textContent.slice(2); }
      });
      if (v.length >= 12) score++;
      if (/[^A-Za-z0-9]/.test(v)) score++;
      const levels = [
        ['', '0%'], ['weak', '25%'], ['weak', '40%'], ['medium', '65%'], ['strong', '85%'], ['strong', '100%'],
      ];
      const [cls, width] = v ? levels[score] : levels[0];
      meter.className = 'pw-meter ' + cls;
      meter.firstElementChild.style.width = width;
      meter.dataset.label = v ? (T['pw_' + cls] || cls) : '';
      if (pw2 && match) {
        if (!pw2.value) match.textContent = '';
        else if (pw2.value === v) { match.textContent = '✓ ' + (T.pw_match || 'Passwords match'); match.className = 'small ok-text'; }
        else { match.textContent = '✗ ' + (T.pw_mismatch || 'Passwords do not match'); match.className = 'small danger-text'; }
      }
    };
    pw.addEventListener('input', update);
    if (pw2) pw2.addEventListener('input', update);
  }
})();
