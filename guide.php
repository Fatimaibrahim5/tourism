<?php
// Travel organizer page (Fig 10, Instagram style). Tourists can follow the organizer and open "About"
// for all their information. The organizer sees the same page as their own profile, with edit controls.
require __DIR__ . '/includes/functions.php';

$id = (int)($_GET['id'] ?? 0);
$g = q("SELECT u.*, op.university, op.training, op.skills, op.language_skills FROM users u
        LEFT JOIN organizer_profiles op ON op.user_id = u.id WHERE u.id = ? AND u.role = 'organizer'", [$id])->fetch();
$owner = $g && role() === 'organizer' && uid() === (int)$g['id'];
if (!$g || ($g['status'] !== 'active' && role() !== 'admin' && !$owner)) {
    flash('error', t('not_found'));
    redirect('home.php');
}

[$avg, $count] = organizer_rating($id);
$followers = followers_count($id);
// Upcoming trips first (soonest first), then past trips (newest first)
$trips = q("SELECT t.*, (SELECT COALESCE(SUM(seats),0) FROM bookings b WHERE b.trip_id = t.id AND b.status IN ('pending','confirmed')) AS booked
            FROM trips t WHERE t.organizer_id = ? " . ($owner ? '' : "AND t.status = 'published'") . "
            ORDER BY (t.end_date < CURDATE()), CASE WHEN t.end_date >= CURDATE() THEN t.start_date END ASC, t.start_date DESC", [$id])->fetchAll();
$published = array_filter($trips, fn($t) => $t['status'] === 'published');
$upcoming = array_filter($published, fn($t) => !trip_is_over($t));
$reviews = q("SELECT r.*, u.full_name, u.avatar, t.title FROM ratings r JOIN trips t ON t.id = r.trip_id JOIN users u ON u.id = r.tourist_id
              WHERE t.organizer_id = ? AND r.status = 'approved' ORDER BY r.created_at DESC LIMIT 30", [$id])->fetchAll();
$age = $g['dob'] ? (new DateTime($g['dob']))->diff(new DateTime())->y : '';

// Owner sheets (Edit profile / Settings) post to profile.php; errors come back as ?open=
$user = $owner ? current_user() : null;
$errors = [];
$openSheet = in_array($_GET['open'] ?? '', ['editSheet', 'settingsSheet'], true) ? $_GET['open'] : '';

page_header($g['full_name']);
?>
<section class="ig">
  <header class="ig-head">
    <?php if ($owner): ?>
      <form method="post" action="profile.php" enctype="multipart/form-data" class="ig-avatar-form">
        <?= csrf_field() ?><input type="hidden" name="action" value="avatar">
        <label class="ig-ring" title="<?= e(t('change_photo')) ?>">
          <?= avatar_html($g, 'ig-avatar') ?><span class="ig-cam" aria-hidden="true">📷</span>
          <input type="file" name="avatar" accept="image/*" class="hidden" data-autosubmit>
          <span class="sr-only"><?= e(t('change_photo')) ?></span>
        </label>
      </form>
    <?php else: ?>
      <span class="ig-ring <?= $upcoming ? '' : 'plain' ?>"><?= avatar_html($g, 'ig-avatar') ?></span>
    <?php endif; ?>

    <div class="ig-info">
      <div class="ig-name-row">
        <h1><?= e($g['full_name']) ?>
          <?php if ($g['status'] === 'active'): ?><span class="verified" title="<?= e(t('verified_hint')) ?>">✔</span><?php else: ?> <?= status_badge($g['status']) ?><?php endif; ?></h1>
        <div class="row">
          <?php if ($owner): ?>
            <button class="btn ig-btn" type="button" data-modal="editSheet"><?= e(t('edit_profile')) ?></button>
            <a class="btn ig-btn" href="org_trip_form.php">＋ <?= e(t('create_trip')) ?></a>
            <button class="btn ig-btn icon" type="button" data-modal="settingsSheet" title="<?= e(t('settings')) ?>" aria-label="<?= e(t('settings')) ?>">⚙</button>
          <?php else: ?>
            <?= follow_button($id) ?>
            <a class="btn ig-btn" href="#about" data-tab="about">ℹ <?= e(t('about_organizer')) ?></a>
          <?php endif; ?>
        </div>
      </div>
      <ul class="ig-stats">
        <li><a href="#trips" data-tab="trips"><b><?= count($published) ?></b> <?= e(t('stat_trips')) ?></a></li>
        <li><b id="followersCount"><?= $followers ?></b> <?= e(t('followers')) ?></li>
        <li><a href="#reviews" data-tab="reviews"><b>★ <?= $avg ? number_format($avg, 1) : '—' ?></b> (<?= $count ?>)</a></li>
      </ul>
      <p class="ig-bio">
        <?= e(t('role_organizer')) ?>
        <?php if ($g['language_skills']): ?> · 🗣 <?= e($g['language_skills']) ?><?php endif; ?>
        <?php if ($g['skills']): ?><br>✨ <?= e($g['skills']) ?><?php endif; ?>
        <?php if (!$owner && $upcoming): ?><br><span class="muted small"><?= e(t('n_upcoming', ['n' => count($upcoming)])) ?></span><?php endif; ?>
        <?php if ($owner && $g['status'] !== 'active'): ?><br><span class="muted small">⏳ <?= e(t('pending_notice')) ?></span><?php endif; ?>
      </p>
    </div>
  </header>

  <nav class="ig-tabs" role="tablist">
    <a href="#trips" data-tab="trips" role="tab">▦ <?= e(t('trips')) ?></a>
    <a href="#reviews" data-tab="reviews" role="tab">★ <?= e(t('reviews')) ?></a>
    <a href="#about" data-tab="about" role="tab">ℹ <?= e(t('about_tab')) ?></a>
  </nav>

  <!-- Trips -->
  <div class="ig-panel" id="tab-trips">
    <?php if (!$trips && !$owner): ?><div class="ig-empty"><div class="big-icon">🧭</div><p><?= e(t('no_trips_yet')) ?></p></div><?php endif; ?>
    <div class="ig-grid">
      <?php if ($owner && $g['status'] === 'active'): ?>
        <a class="ig-tile new-tile" href="org_trip_form.php"><span>＋</span><?= e(t('create_trip')) ?></a>
      <?php endif; ?>
      <?php foreach ($trips as $t):
          $over = trip_is_over($t);
          $isNew = !$over && strtotime($t['created_at']) > strtotime('-14 days'); ?>
        <article class="ig-tile <?= $over ? 'faded' : '' ?>">
          <?php if (!$owner && !$over): ?><?= fav_button((int)$t['id'], 'on-card') ?><?php endif; ?>
          <a href="trip.php?id=<?= (int)$t['id'] ?>" class="ig-thumb">
            <?= trip_cover($t) ?>
            <span class="ig-overlay"><b><?= e($t['title']) ?></b><span><?= e(fdate($t['start_date'])) ?> · <?= money(effective_price($t)) ?></span></span>
            <span class="ig-badge">
              <?php if ($t['status'] !== 'published'): ?><?= status_badge($t['status']) ?>
              <?php elseif ($over): ?><span class="badge"><?= e(t('trip_finished')) ?></span>
              <?php elseif ($isNew): ?><span class="badge new-badge"><?= e(t('new')) ?></span><?php endif; ?>
            </span>
          </a>
          <?php if ($owner): ?>
            <div class="ig-actions">
              <a href="org_trip_form.php?id=<?= (int)$t['id'] ?>">✏️ <?= e(t('edit')) ?></a>
              <a href="org_bookings.php?trip=<?= (int)$t['id'] ?>">👥 <?= (int)$t['booked'] ?>/<?= (int)$t['capacity'] ?></a>
            </div>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Reviews -->
  <div class="ig-panel" id="tab-reviews">
    <div class="card flat">
      <div class="review-summary"><span class="big"><?= $avg ? number_format($avg, 1) : '—' ?></span><div><?= stars($avg, false) ?><br><span class="muted"><?= e(t('n_reviews', ['n' => $count])) ?></span></div></div>
      <?php if (!$reviews): ?><p class="muted"><?= e(t('no_reviews_yet')) ?></p><?php endif; ?>
      <?php foreach ($reviews as $r): ?>
        <div class="review row" style="align-items:flex-start;flex-wrap:nowrap">
          <?= avatar_html($r, 'avatar') ?>
          <div class="grow">
            <div class="row between"><b><?= e($r['full_name']) ?></b><span class="muted small"><?= e(fdate($r['created_at'])) ?></span></div>
            <?= stars((float)$r['score'], false) ?> <span class="muted small">· <?= e($r['title']) ?></span>
            <?php if ($r['comment']): ?><p class="mb0 prewrap"><?= e($r['comment']) ?></p><?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- About: everything about the organizer -->
  <div class="ig-panel" id="tab-about">
    <div class="card flat about-list">
      <div class="about-row"><span>👤</span><div><small><?= e(t('full_name')) ?></small><b><?= e($g['full_name']) ?></b></div></div>
      <div class="about-row"><span>✔</span><div><small><?= e(t('status')) ?></small><b><?= $g['status'] === 'active' ? e(t('verified_hint')) : status_badge($g['status']) ?></b></div></div>
      <div class="about-row"><span>📍</span><div><small><?= e(t('nationality')) ?></small><b><?= e($g['nationality'] ?: '—') ?></b></div></div>
      <?php if ($age !== ''): ?><div class="about-row"><span>🎂</span><div><small><?= e(t('age')) ?></small><b><?= $age ?></b></div></div><?php endif; ?>
      <div class="about-row"><span>🗣</span><div><small><?= e(t('language_skills')) ?></small><b><?= e($g['language_skills'] ?: '—') ?></b></div></div>
      <div class="about-row"><span>🎓</span><div><small><?= e(t('university_study')) ?></small><b><?= e($g['university'] ?: '—') ?></b></div></div>
      <div class="about-row"><span>📜</span><div><small><?= e(t('training_licenses')) ?></small><b><?= e($g['training'] ?: '—') ?></b></div></div>
      <div class="about-row"><span>✨</span><div><small><?= e(t('skills')) ?></small><b><?= e($g['skills'] ?: '—') ?></b></div></div>
      <div class="about-row"><span>⭐</span><div><small><?= e(t('rating')) ?></small><b><?= stars($avg) ?> <span class="muted small">(<?= e(t('n_reviews', ['n' => $count])) ?>)</span></b></div></div>
      <div class="about-row"><span>🧭</span><div><small><?= e(t('trips')) ?></small><b><?= e(t('trips_summary', ['n' => count($published), 'u' => count($upcoming)])) ?></b></div></div>
      <div class="about-row"><span>👥</span><div><small><?= e(t('followers')) ?></small><b><?= $followers ?></b></div></div>
      <div class="about-row"><span>📅</span><div><small><?= e(t('member_since')) ?></small><b><?= e(date('m/Y', strtotime($g['created_at']))) ?></b></div></div>
      <?php if ($owner || role() === 'admin'): ?>
        <div class="about-row"><span>✉️</span><div><small><?= e(t('email')) ?> · <?= e(t('phone_number')) ?> <span class="badge"><?= e(t('private')) ?></span></small><b><?= e($g['email']) ?> · <?= e($g['phone'] ?: '—') ?></b></div></div>
      <?php else: ?>
        <div class="about-row"><span>💬</span><div><small><?= e(t('contact')) ?></small><b><a href="about.php#contact"><?= e(t('contact_via_company')) ?></a></b></div></div>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php if ($owner): ?>
<!-- Edit profile sheet (organizer) -->
<div class="modal sheet" id="editSheet" role="dialog" aria-modal="true" <?= $openSheet === 'editSheet' ? 'data-open' : '' ?>>
  <div class="modal-box">
    <button class="modal-close" aria-label="<?= e(t('close')) ?>">×</button>
    <h2><?= e(t('edit_profile')) ?></h2>
    <form method="post" action="profile.php" data-validate novalidate>
      <?= csrf_field() ?><input type="hidden" name="action" value="profile">
      <input type="hidden" name="dob" value="<?= e($g['dob']) ?>">
      <label for="full_name"><?= e(t('name')) ?></label>
      <input type="text" id="full_name" name="full_name" value="<?= e($g['full_name']) ?>" required>
      <label for="email"><?= e(t('email')) ?></label>
      <input type="email" id="email" name="email" value="<?= e($g['email']) ?>" required>
      <label for="phone"><?= e(t('phone_number')) ?></label>
      <input type="tel" id="phone" name="phone" value="<?= e($g['phone']) ?>">
      <label for="university"><?= e(t('university_study')) ?></label>
      <input type="text" id="university" name="university" value="<?= e($g['university']) ?>">
      <label for="language_skills"><?= e(t('language_skills')) ?></label>
      <input type="text" id="language_skills" name="language_skills" value="<?= e($g['language_skills']) ?>" placeholder="<?= e(t('ph_language_skills')) ?>">
      <label for="training"><?= e(t('training_licenses')) ?></label>
      <textarea id="training" name="training" style="min-height:70px"><?= e($g['training']) ?></textarea>
      <label for="skills"><?= e(t('skills')) ?></label>
      <input type="text" id="skills" name="skills" value="<?= e($g['skills']) ?>">
      <button class="btn block"><?= e(t('save_changes')) ?></button>
    </form>
  </div>
</div>
<?php include __DIR__ . '/includes/settings_sheet.php'; ?>
<?php endif; ?>
<?php page_footer(['assets/js/profile.js']);
