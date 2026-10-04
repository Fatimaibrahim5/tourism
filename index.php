<?php
// Fig 1 — choose Tourist or Travel Organizer; Contact Us and company info are reachable without login.
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
  <a class="role-card" href="login.php?role=tourist">
    <h2><?= e(t('role_tourist')) ?></h2>
    <p><?= e(t('tourist_card')) ?></p>
  </a>
  <a class="role-card" href="login.php?role=organizer">
    <h2><?= e(t('role_organizer')) ?></h2>
    <p><?= e(t('organizer_card')) ?></p>
  </a>
</div>

<p class="center mt"><a class="btn outline" href="home.php"><?= e(t('browse_without_login')) ?></a></p>
<p class="admin-link"><a href="login.php?role=admin"><?= e(t('admin_login')) ?></a></p>
<?php page_footer();
