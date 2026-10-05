<?php
// Fig 1 — choose Tourist or Travel Organizer; Contact Us and company info are reachable without login.
// The first time a visitor taps "Tourist", an intro video of Lebanon plays and the login box fades in
// over its last seconds (assets/js/intro.js). Later visits go straight to the login page.
require __DIR__ . '/includes/functions.php';

if (current_user()) redirect(home_for_role());

page_header(t('welcome'), ['bottom_nav' => false, 'main_class' => 'landing']);
?>
<div class="landing-top">
  <a class="btn" href="about.php#contact"><?= e(t('contact_us')) ?></a>
  <a class="info-btn" href="about.php" title="<?= e(t('about_company')) ?>">i</a>
</div>

<div class="landing-hero">
  <h1><?= e(t('landing_title')) ?></h1>
  <p><?= e(t('landing_sub')) ?></p>
</div>

<div class="role-cards">
  <a class="role-card tourist" href="login.php?role=tourist" id="touristCard">
    <span class="role-icon" aria-hidden="true">
      <svg viewBox="0 0 64 64">
        <!-- suitcase -->
        <rect x="10" y="22" width="36" height="28" rx="6" fill="#fff" opacity=".95"/>
        <path d="M21 22v-5a4 4 0 0 1 4-4h6a4 4 0 0 1 4 4v5" fill="none" stroke="#fff" stroke-width="3.5"/>
        <path d="M10 33h36" stroke="#f97316" stroke-width="3"/>
        <rect x="24" y="30" width="8" height="6" rx="2" fill="#f97316"/>
        <circle cx="17" cy="52" r="3" fill="#fff"/><circle cx="39" cy="52" r="3" fill="#fff"/>
        <!-- map pin -->
        <path d="M50 6c-6 0-10 4.5-10 10 0 7.5 10 18 10 18s10-10.5 10-18c0-5.5-4-10-10-10z" fill="#e11d48" stroke="#fff" stroke-width="2.5"/>
        <circle cx="50" cy="16" r="3.8" fill="#fff"/>
      </svg>
    </span>
    <h2><?= e(t('role_tourist')) ?></h2>
    <p><?= e(t('tourist_card')) ?></p>
    <span class="role-go"><?= e(t('get_started')) ?> ›</span>
  </a>
  <a class="role-card organizer" href="login.php?role=organizer">
    <span class="role-icon" aria-hidden="true">
      <svg viewBox="0 0 64 64">
        <!-- compass -->
        <circle cx="28" cy="34" r="20" fill="#fff" opacity=".95"/>
        <circle cx="28" cy="34" r="15" fill="none" stroke="#2563eb" stroke-width="2" stroke-dasharray="2 4"/>
        <path d="M28 20l5 14-5 14-5-14z" fill="#2563eb"/>
        <path d="M28 20l5 14h-10z" fill="#e11d48"/>
        <circle cx="28" cy="34" r="2.6" fill="#fff"/>
        <!-- flag -->
        <path d="M48 6v30" stroke="#fff" stroke-width="3" stroke-linecap="round"/>
        <path d="M49.5 7h11l-3.5 5 3.5 5h-11z" fill="#22a447" stroke="#fff" stroke-width="2" stroke-linejoin="round"/>
      </svg>
    </span>
    <h2><?= e(t('role_organizer')) ?></h2>
    <p><?= e(t('organizer_card')) ?></p>
    <span class="role-go"><?= e(t('get_started')) ?> ›</span>
  </a>
</div>

<p class="center mt"><a class="btn outline" href="home.php"><?= e(t('browse_without_login')) ?></a></p>
<p class="center small"><button type="button" class="link-btn" id="replayIntro">▶ <?= e(t('watch_intro')) ?></button></p>
<p class="admin-link"><a href="login.php?role=admin"><?= e(t('admin_login')) ?></a></p>

<!-- Intro video overlay (first visit as a tourist) -->
<div class="intro" id="intro" hidden>
  <video id="introVideo" playsinline preload="none" poster="assets/video/lebanon_poster.jpg">
    <source src="assets/video/lebanon.mp4" type="video/mp4">
  </video>
  <div class="intro-shade"></div>
  <div class="intro-title" id="introTitle">
    <span><?= e(t('intro_welcome')) ?></span>
    <b><?= e(t('intro_lebanon')) ?></b>
  </div>
  <button type="button" class="intro-skip" id="introSkip"><?= e(t('skip')) ?> ›</button>
  <button type="button" class="intro-sound" id="introSound" aria-label="<?= e(t('sound')) ?>">🔊</button>

  <div class="intro-login" id="introLogin">
    <div class="card login-card glass">
      <h1><?= e(t('login')) ?></h1>
      <p class="center muted small"><?= e(t('role_tourist')) ?></p>
      <form method="post" action="login.php?role=tourist" data-validate novalidate>
        <?= csrf_field() ?>
        <label for="iEmail"><?= e(t('username_email')) ?></label>
        <input type="email" id="iEmail" name="email" required autocomplete="username">
        <label for="iPassword"><?= e(t('password')) ?></label>
        <input type="password" id="iPassword" name="password" required autocomplete="current-password">
        <button class="btn steel block" type="submit"><?= e(t('login')) ?></button>
      </form>
      <div class="form-links">
        <a class="btn outline" href="register.php?role=tourist"><?= e(t('create_account')) ?></a>
        <a href="forgot.php" style="color:#111"><?= e(t('forgot_password')) ?></a>
      </div>
    </div>
  </div>
</div>
<?php page_footer(['assets/js/intro.js']);
