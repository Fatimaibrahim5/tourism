<?php
// Page chrome: top bar, side drawer, flash messages, bottom navigation (Fig 5 mockup).

function page_header(string $title, array $opts = []): void {
    $u = current_user();
    $lang = lang();
    $page = basename($_SERVER['SCRIPT_NAME']);
    $unread = unread_count();
    $showNav = ($opts['bottom_nav'] ?? true) && role() !== 'admin';
    ?>
<!doctype html>
<html lang="<?= $lang ?>" dir="<?= $lang === 'ar' ? 'rtl' : 'ltr' ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · <?= e(APP_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<?php if (!empty($opts['map'])): ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<?php endif; ?>
<link rel="stylesheet" href="assets/css/style.css?v=3">
</head>
<body class="<?= e($opts['body_class'] ?? '') ?><?= $showNav ? ' has-bottom-nav' : '' ?>">
<header class="topbar">
  <button class="icon-btn" id="menuBtn" aria-label="<?= e(t('menu')) ?>">
    <svg viewBox="0 0 24 24" width="26" height="26"><path d="M3 6h18M3 12h18M3 18h18" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
  </button>
  <a class="brand" href="<?= e($u ? home_for_role() : 'index.php') ?>"><?= e(t('app_name')) ?></a>
  <div class="topbar-actions">
    <a class="lang-switch" href="<?= e(self_url_without_lang($lang === 'ar' ? 'en' : 'ar')) ?>"><?= $lang === 'ar' ? 'EN' : 'عربي' ?></a>
    <a class="info-btn" href="about.php" title="<?= e(t('about_company')) ?>">i</a>
  </div>
</header>

<div class="drawer-backdrop" id="drawerBackdrop"></div>
<nav class="drawer" id="drawer" aria-label="<?= e(t('menu')) ?>">
  <div class="drawer-head">
    <?php if ($u): ?>
      <?= avatar_html($u) ?>
      <div><b><?= e($u['full_name']) ?></b><br><small><?= e(t('role_' . $u['role'])) ?></small></div>
    <?php else: ?>
      <b><?= e(t('app_name')) ?></b>
    <?php endif; ?>
  </div>
  <?php foreach (menu_links() as [$href, $label]): ?>
    <a href="<?= e($href) ?>" class="<?= $page === strtok($href, '?') ? 'active' : '' ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</nav>

<main class="container <?= e($opts['main_class'] ?? '') ?>">
<?php foreach (take_flashes() as [$type, $msg]): ?>
  <div class="alert alert-<?= e($type) ?>"><?= e($msg) ?></div>
<?php endforeach; ?>
<?php
    $GLOBALS['__show_nav'] = $showNav;
    $GLOBALS['__unread'] = $unread;
}

function menu_links(): array {
    $r = role();
    $links = [];
    if ($r === 'admin') {
        $links = [
            ['admin_dashboard.php', t('dashboard')],
            ['admin_guides.php', t('guide_approvals')],
            ['admin_users.php', t('users')],
            ['admin_trips.php', t('trips')],
            ['admin_reviews.php', t('reviews')],
            ['admin_complaints.php', t('complaints')],
            ['admin_reports.php', t('reports')],
            ['admin_settings.php', t('system')],
            ['inbox.php', t('inbox')],
        ];
    } elseif ($r === 'organizer') {
        $links = [
            ['org_dashboard.php', t('my_trips')],
            ['org_trip_form.php', t('create_trip')],
            ['home.php', t('map')],
            ['profile.php', t('profile')],
            ['inbox.php', t('inbox')],
        ];
    } elseif ($r === 'tourist') {
        $links = [
            ['home.php', t('map')],
            ['search.php', t('search_trips')],
            ['profile.php', t('my_profile_bookings')],
            ['complaints.php', t('complaints')],
            ['inbox.php', t('inbox')],
        ];
    } else {
        $links = [
            ['index.php', t('home')],
            ['home.php', t('map')],
            ['search.php', t('search_trips')],
            ['login.php?role=tourist', t('login')],
            ['register.php?role=tourist', t('create_account')],
        ];
    }
    $links[] = ['about.php', t('about_company')];
    $links[] = ['help.php', t('help_faq')];
    if ($r) $links[] = ['logout.php', t('logout')];
    return $links;
}

function page_footer(array $scripts = []): void {
    $page = basename($_SERVER['SCRIPT_NAME']);
    ?>
</main>
<?php if (!empty($GLOBALS['__show_nav'])): ?>
<nav class="bottom-nav" id="bottomNav">
  <a href="profile.php" class="<?= $page === 'profile.php' ? 'active' : '' ?>" aria-label="<?= e(t('profile')) ?>">
    <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/></svg></a>
  <a href="inbox.php" class="<?= $page === 'inbox.php' ? 'active' : '' ?>" aria-label="<?= e(t('inbox')) ?>">
    <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg>
    <?php if ($GLOBALS['__unread'] > 0): ?><span class="dot"><?= (int)$GLOBALS['__unread'] ?></span><?php endif; ?></a>
  <a href="home.php" class="<?= $page === 'home.php' ? 'active' : '' ?>" aria-label="<?= e(t('home')) ?>">
    <svg viewBox="0 0 24 24"><path d="M3 11l9-7 9 7v9a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1z"/></svg></a>
  <a href="search.php" class="<?= $page === 'search.php' ? 'active' : '' ?>" aria-label="<?= e(t('search_trips')) ?>">
    <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg></a>
</nav>
<?php endif; ?>
<footer class="site-footer">© <?= date('Y') ?> <?= e(setting('company_name', 'FsM-co')) ?> · <a href="about.php"><?= e(t('contact_us')) ?></a> · <a href="help.php"><?= e(t('help_faq')) ?></a></footer>
<script>window.I18N = <?= json_encode(js_strings(), JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="assets/js/app.js?v=3"></script>
<?php foreach ($scripts as $s): ?><script src="<?= e($s) ?>?v=1"></script><?php endforeach; ?>
</body>
</html>
<?php
}
