// Intro video: the first time a visitor taps "Tourist", play the Lebanon video full screen and
// fade the login box in over its last seconds. Remembered per browser, so it only plays once.
(function () {
  const card = document.getElementById('touristCard');
  const intro = document.getElementById('intro');
  const video = document.getElementById('introVideo');
  if (!card || !intro || !video) return;

  const KEY = 'introSeen';
  const LOGIN_AT = 2.2;            // seconds before the end when the login box appears
  const loginBox = document.getElementById('introLogin');
  const title = document.getElementById('introTitle');
  const soundBtn = document.getElementById('introSound');
  const skipBtn = document.getElementById('introSkip');
  const T = window.I18N || {};
  const skipLabel = skipBtn.textContent;

  const seen = () => { try { return localStorage.getItem(KEY) === '1'; } catch (e) { return false; } };
  const markSeen = () => { try { localStorage.setItem(KEY, '1'); } catch (e) { /* private mode */ } };

  function showLogin() {
    if (intro.classList.contains('show-login')) return;
    intro.classList.add('show-login');
    skipBtn.textContent = '✕';
    skipBtn.setAttribute('aria-label', T.close || 'Close');
    if (video.currentTime > 0.5) markSeen();
    setTimeout(() => { const f = document.getElementById('iEmail'); if (f) f.focus({ preventScroll: true }); }, 600);
  }

  function open() {
    intro.hidden = false;
    document.body.classList.add('intro-open');
    intro.classList.remove('show-login');
    skipBtn.textContent = skipLabel;
    requestAnimationFrame(() => intro.classList.add('visible'));
    video.currentTime = 0;
    video.muted = false;               // started by a tap, so sound is allowed
    soundBtn.textContent = '🔊';
    const p = video.play();
    if (p && p.catch) p.catch(() => {   // autoplay with sound blocked → retry muted
      video.muted = true;
      soundBtn.textContent = '🔇';
      video.play().catch(showLogin);
    });
    setTimeout(() => title.classList.add('in'), 400);
  }

  function close() {
    video.pause();
    intro.classList.remove('visible');
    document.body.classList.remove('intro-open');
    setTimeout(() => { intro.hidden = true; }, 400);
  }

  card.addEventListener('click', e => {
    if (seen()) return;   // already watched → normal link to login.php
    e.preventDefault();
    open();
  });

  document.getElementById('replayIntro').addEventListener('click', open);
  skipBtn.addEventListener('click', () => {
    if (intro.classList.contains('show-login')) { close(); return; }
    video.currentTime = Math.max(0, (video.duration || 9) - LOGIN_AT);
    showLogin();
  });
  soundBtn.addEventListener('click', () => {
    video.muted = !video.muted;
    soundBtn.textContent = video.muted ? '🔇' : '🔊';
  });

  video.addEventListener('timeupdate', () => {
    if (video.duration && video.currentTime >= video.duration - LOGIN_AT) showLogin();
  });
  video.addEventListener('ended', showLogin);   // stays on the last frame behind the login box
  video.addEventListener('error', showLogin);
  // Reset with ?intro=1 (e.g. index.php?intro=1) to see the first-time experience again
  if (new URLSearchParams(location.search).has('intro')) { try { localStorage.removeItem(KEY); } catch (e) { } }

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && !intro.hidden) close();
  });
})();
