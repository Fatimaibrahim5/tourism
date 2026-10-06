<?php
// Profile. Tourists get an Instagram-style page (photo, name, stats, "My trips" and "Favorites" tabs,
// edit-profile and settings sheets). Organizers keep the Fig 10 form with rating and tours made.
require __DIR__ . '/includes/functions.php';

$user = require_login();
$isOrg = $user['role'] === 'organizer';
$isTourist = $user['role'] === 'tourist';
$org = $isOrg ? (q('SELECT * FROM organizer_profiles WHERE user_id = ?', [$user['id']])->fetch() ?: []) : [];
$errors = [];
$openSheet = '';   // re-open the sheet that had a validation error

if (is_post()) {
    check_csrf();
    $action = post('action');
    // Public demo accounts can't change their photo, details or password (so nobody can lock them)
    if (is_demo_viewer()) deny_demo($isOrg ? 'guide.php?id=' . $user['id'] : 'profile.php');
    if ($action === 'avatar') {
        try {
            $path = upload_image($_FILES['avatar'] ?? [], 'avatars');
            if (!empty($user['avatar']) && str_starts_with($user['avatar'], UPLOAD_URL)) @unlink(__DIR__ . '/' . $user['avatar']);
            q('UPDATE users SET avatar = ? WHERE id = ?', [$path, $user['id']]);
            flash('success', t('photo_updated'));
        } catch (RuntimeException $ex) {
            flash('error', $ex->getMessage());
        }
        redirect('profile.php');
    } elseif ($action === 'remove_avatar') {
        if (!empty($user['avatar']) && str_starts_with($user['avatar'], UPLOAD_URL)) @unlink(__DIR__ . '/' . $user['avatar']);
        q('UPDATE users SET avatar = NULL WHERE id = ?', [$user['id']]);
        flash('success', t('photo_removed'));
        redirect('profile.php');
    } elseif ($action === 'password') {
        $cur = $_POST['current'] ?? '';
        $pw = $_POST['password'] ?? '';
        if (!password_verify($cur, $user['password_hash'])) $errors['current'] = t('wrong_password');
        if (strlen($pw) < 8 || !preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) $errors['password'] = t('pw_rules');
        if ($pw !== ($_POST['password2'] ?? '')) $errors['password2'] = t('pw_mismatch');
        if (!$errors) {
            q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $user['id']]);
            audit('password_changed');
            send_mail($user['id'], $user['email'], 'Your password was changed', "Hello {$user['full_name']},\n\nThe password of your account was just changed. If this wasn't you, contact us immediately.");
            flash('success', t('pw_changed'));
            redirect('profile.php');
        }
        $openSheet = 'settingsSheet';
    } else {
        $name = post('full_name');
        $email = mb_strtolower(post('email'));
        $phone = post('phone');
        $dob = post('dob');
        if (mb_strlen($name) < 2) $errors['full_name'] = t('err_name');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = t('invalid_email');
        elseif (q('SELECT 1 FROM users WHERE email = ? AND id <> ?', [$email, $user['id']])->fetch()) $errors['email'] = t('email_taken');
        if ($phone !== '' && !preg_match('/^[+0-9 ()-]{6,20}$/', $phone)) $errors['phone'] = t('invalid_phone');
        if ($dob !== '' && (!DateTime::createFromFormat('Y-m-d', $dob) || $dob > date('Y-m-d'))) $errors['dob'] = t('err_date');
        if (!$errors) {
            q('UPDATE users SET full_name = ?, email = ?, phone = ?, dob = ? WHERE id = ?', [$name, $email, $phone ?: null, $dob ?: null, $user['id']]);
            if ($isOrg) {
                q('INSERT INTO organizer_profiles (user_id, university, training, skills, language_skills) VALUES (?, ?, ?, ?, ?)
                   ON DUPLICATE KEY UPDATE university = VALUES(university), training = VALUES(training), skills = VALUES(skills), language_skills = VALUES(language_skills)',
                  [$user['id'], post('university'), post('training'), post('skills'), post('language_skills')]);
            }
            flash('success', t('profile_saved'));
            redirect('profile.php');
        }
        $openSheet = 'editSheet';
    }
}

// Organizers use their Instagram-style public page (guide.php) with owner controls
if ($isOrg) {
    if ($errors) {
        flash('error', implode(' ', array_unique($errors)));
        redirect('guide.php?id=' . $user['id'] . '&open=' . $openSheet);
    }
    redirect('guide.php?id=' . $user['id']);
}

$age = $user['dob'] ? (new DateTime($user['dob']))->diff(new DateTime())->y : '';

// ====================================================================== Tourist (Instagram-style)
if ($isTourist):
    $bookings = q("SELECT b.*, t.title, t.destination, t.cover_image, t.start_date, t.end_date, t.status AS trip_status,
                          (SELECT method FROM payments p WHERE p.booking_id = b.id ORDER BY p.id DESC LIMIT 1) AS method,
                          (SELECT status FROM payments p WHERE p.booking_id = b.id ORDER BY p.id DESC LIMIT 1) AS pay_status,
                          (SELECT 1 FROM ratings r WHERE r.trip_id = b.trip_id AND r.tourist_id = b.tourist_id) AS reviewed
                   FROM bookings b JOIN trips t ON t.id = b.trip_id WHERE b.tourist_id = ? ORDER BY t.start_date DESC", [$user['id']])->fetchAll();
    $favorites = q("SELECT t.* FROM favorites f JOIN trips t ON t.id = f.trip_id WHERE f.user_id = ? ORDER BY f.created_at DESC", [$user['id']])->fetchAll();
    $nTrips = count(array_filter($bookings, fn($b) => $b['status'] !== 'cancelled'));
    $nReviews = (int)q('SELECT COUNT(*) FROM ratings WHERE tourist_id = ?', [$user['id']])->fetchColumn();
    $followingOrgs = q("SELECT u.id, u.full_name, u.avatar FROM follows f JOIN users u ON u.id = f.organizer_id
                        WHERE f.follower_id = ? AND u.status = 'active' ORDER BY f.created_at DESC", [$user['id']])->fetchAll();
    // Newest upcoming trips from the organizers this tourist follows
    $feed = q("SELECT t.*, u.full_name AS org_name FROM trips t JOIN follows f ON f.organizer_id = t.organizer_id JOIN users u ON u.id = t.organizer_id
               WHERE f.follower_id = ? AND t.status = 'published' AND t.end_date >= CURDATE() AND u.status = 'active'
               ORDER BY t.created_at DESC LIMIT 12", [$user['id']])->fetchAll();

    page_header($user['full_name']);
?>
<section class="ig">
  <header class="ig-head">
    <form method="post" enctype="multipart/form-data" class="ig-avatar-form">
      <?= csrf_field() ?><input type="hidden" name="action" value="avatar">
      <label class="ig-ring" title="<?= e(t('change_photo')) ?>">
        <?= avatar_html($user, 'ig-avatar') ?>
        <span class="ig-cam" aria-hidden="true">📷</span>
        <input type="file" name="avatar" accept="image/*" class="hidden" data-autosubmit>
        <span class="sr-only"><?= e(t('change_photo')) ?></span>
      </label>
    </form>

    <div class="ig-info">
      <div class="ig-name-row">
        <h1><?= e($user['full_name']) ?></h1>
        <div class="row">
          <button class="btn ig-btn" type="button" data-modal="editSheet"><?= e(t('edit_profile')) ?></button>
          <button class="btn ig-btn icon" type="button" data-modal="settingsSheet" title="<?= e(t('settings')) ?>" aria-label="<?= e(t('settings')) ?>">⚙</button>
        </div>
      </div>
      <ul class="ig-stats">
        <li><a href="#trips" data-tab="trips"><b><?= $nTrips ?></b> <?= e(t('stat_trips')) ?></a></li>
        <li><a href="#favorites" data-tab="favorites"><b id="favCount"><?= count($favorites) ?></b> <?= e(t('favorites')) ?></a></li>
        <li><a href="#following" data-tab="following"><b><?= count($followingOrgs) ?></b> <?= e(t('following')) ?></a></li>
        <li><b><?= $nReviews ?></b> <?= e(t('stat_reviews')) ?></li>
      </ul>
      <p class="ig-bio">
        <?php if ($user['nationality']): ?>📍 <?= e($user['nationality']) ?><?php endif; ?>
        <?php if ($user['language']): ?> · 🗣 <?= e($user['language']) ?><?php endif; ?>
        <?php if ($age !== ''): ?> · 🎂 <?= $age ?><?php endif; ?>
        <br><span class="muted small"><?= e(t('member_since')) ?> <?= e(date('m/Y', strtotime($user['created_at']))) ?></span>
        <?php if (empty($user['avatar'])): ?><br><span class="muted small"><?= e(t('tap_to_add_photo')) ?></span><?php endif; ?>
      </p>
    </div>
  </header>

  <nav class="ig-tabs" role="tablist">
    <a href="#trips" data-tab="trips" role="tab">▦ <?= e(t('my_trips')) ?></a>
    <a href="#favorites" data-tab="favorites" role="tab">♥ <?= e(t('favorites')) ?></a>
    <a href="#following" data-tab="following" role="tab">👥 <?= e(t('following')) ?></a>
  </nav>

  <!-- My trips -->
  <div class="ig-panel" id="tab-trips">
    <?php if (!$bookings): ?>
      <div class="ig-empty"><div class="big-icon">🧳</div><p><?= e(t('no_bookings')) ?></p><a class="btn" href="home.php"><?= e(t('browse_trips')) ?></a></div>
    <?php endif; ?>
    <div class="ig-grid">
    <?php foreach ($bookings as $b):
        $over = $b['end_date'] < date('Y-m-d');
        $daysBefore = (strtotime($b['start_date']) - strtotime(date('Y-m-d'))) / 86400;
        $canCancel = $b['status'] === 'pending' || ($b['status'] === 'confirmed' && $daysBefore >= CANCEL_MIN_DAYS); ?>
      <article class="ig-tile <?= $b['status'] === 'cancelled' ? 'faded' : '' ?>">
        <a href="trip.php?id=<?= (int)$b['trip_id'] ?>" class="ig-thumb">
          <?= trip_cover($b) ?>
          <span class="ig-overlay"><b><?= e($b['title']) ?></b><span><?= e(fdate($b['start_date'])) ?> · <?= (int)$b['seats'] ?> 👤</span></span>
          <span class="ig-badge"><?= $over && $b['status'] === 'confirmed' ? '<span class="badge">' . e(t('trip_finished')) . '</span>' : status_badge($b['status']) ?></span>
        </a>
        <div class="ig-actions">
          <a href="receipt.php?booking=<?= (int)$b['id'] ?>" title="<?= e(t('receipt')) ?>">🧾</a>
          <?php if ($b['status'] === 'pending' && $b['method'] === 'card'): ?>
            <a href="bank.php?booking=<?= (int)$b['id'] ?>" class="pay"><?= e(t('pay_now')) ?></a>
          <?php endif; ?>
          <?php if ($over && $b['status'] === 'confirmed'): ?>
            <a href="memories.php?trip=<?= (int)$b['trip_id'] ?>" title="<?= e(t('travel_memories')) ?>">📷</a>
            <?php if (!$b['reviewed']): ?><a href="trip.php?id=<?= (int)$b['trip_id'] ?>#reviews" class="rate">★ <?= e(t('rate')) ?></a><?php endif; ?>
          <?php endif; ?>
          <?php if ($canCancel && !$over): ?>
            <form method="post" action="cancel_booking.php" class="inline" data-confirm="<?= e(t('confirm_cancel')) ?>">
              <?= csrf_field() ?><input type="hidden" name="booking" value="<?= (int)$b['id'] ?>">
              <button class="link-btn danger-text"><?= e(t('cancel')) ?></button>
            </form>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
    </div>
    <?php if ($bookings): ?><p class="muted small center mt"><?= e(t('cancel_policy', ['n' => CANCEL_MIN_DAYS])) ?></p><?php endif; ?>
  </div>

  <!-- Favorites -->
  <div class="ig-panel" id="tab-favorites">
    <div class="ig-empty <?= $favorites ? 'hidden' : '' ?>" id="favEmpty"><div class="big-icon">♡</div><p><?= e(t('no_favorites')) ?></p><a class="btn" href="search.php"><?= e(t('browse_trips')) ?></a></div>
    <div class="ig-grid">
    <?php foreach ($favorites as $t): ?>
      <article class="ig-tile" data-fav-tile>
        <?= fav_button((int)$t['id'], 'on-card') ?>
        <a href="trip.php?id=<?= (int)$t['id'] ?>" class="ig-thumb">
          <?= trip_cover($t) ?>
          <span class="ig-overlay"><b><?= e($t['title']) ?></b><span><?= e(fdate($t['start_date'])) ?> · <?= money(effective_price($t)) ?></span></span>
          <?php if (trip_is_over($t) || $t['status'] !== 'published'): ?><span class="ig-badge"><span class="badge"><?= e(t('trip_finished')) ?></span></span><?php endif; ?>
        </a>
      </article>
    <?php endforeach; ?>
    </div>
  </div>

  <!-- Following: organizers (story-style circles) + their newest trips -->
  <div class="ig-panel" id="tab-following">
    <?php if (!$followingOrgs): ?>
      <div class="ig-empty"><div class="big-icon">👥</div><p><?= e(t('no_following')) ?></p><a class="btn" href="search.php"><?= e(t('find_organizers')) ?></a></div>
    <?php else: ?>
      <div class="stories">
        <?php foreach ($followingOrgs as $o): ?>
          <a class="story" href="guide.php?id=<?= (int)$o['id'] ?>"><span class="ig-ring sm"><?= avatar_html($o, 'ig-avatar') ?></span><span class="story-name"><?= e($o['full_name']) ?></span></a>
        <?php endforeach; ?>
      </div>
      <h3 class="feed-title"><?= e(t('new_from_following')) ?></h3>
      <?php if (!$feed): ?><p class="muted center"><?= e(t('no_new_trips')) ?></p><?php endif; ?>
      <div class="ig-grid">
        <?php foreach ($feed as $t): ?>
          <article class="ig-tile">
            <?= fav_button((int)$t['id'], 'on-card') ?>
            <a href="trip.php?id=<?= (int)$t['id'] ?>" class="ig-thumb">
              <?= trip_cover($t) ?>
              <span class="ig-overlay"><b><?= e($t['title']) ?></b><span><?= e($t['org_name']) ?> · <?= e(fdate($t['start_date'])) ?></span></span>
              <?php if (strtotime($t['created_at']) > strtotime('-14 days')): ?><span class="ig-badge"><span class="badge new-badge"><?= e(t('new')) ?></span></span><?php endif; ?>
            </a>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- Edit profile sheet -->
<div class="modal sheet" id="editSheet" role="dialog" aria-modal="true" <?= $openSheet === 'editSheet' ? 'data-open' : '' ?>>
  <div class="modal-box">
    <button class="modal-close" aria-label="<?= e(t('close')) ?>">×</button>
    <h2><?= e(t('edit_profile')) ?></h2>
    <form method="post" data-validate novalidate>
      <?= csrf_field() ?><input type="hidden" name="action" value="profile">
      <label for="full_name"><?= e(t('name')) ?></label>
      <input type="text" id="full_name" name="full_name" value="<?= e(post('full_name', $user['full_name'])) ?>" required><?= field_error($errors, 'full_name') ?>
      <label for="email"><?= e(t('email')) ?></label>
      <input type="email" id="email" name="email" value="<?= e(post('email', $user['email'])) ?>" required><?= field_error($errors, 'email') ?>
      <label for="phone"><?= e(t('phone_number')) ?></label>
      <input type="tel" id="phone" name="phone" value="<?= e(post('phone', $user['phone'] ?? '')) ?>"><?= field_error($errors, 'phone') ?>
      <label for="dob"><?= e(t('date_of_birth')) ?></label>
      <input type="date" id="dob" name="dob" value="<?= e(post('dob', $user['dob'] ?? '')) ?>" max="<?= date('Y-m-d') ?>"><?= field_error($errors, 'dob') ?>
      <button class="btn block"><?= e(t('save_changes')) ?></button>
    </form>
  </div>
</div>

<?php include __DIR__ . '/includes/settings_sheet.php'; ?>

<?php
    page_footer(['assets/js/profile.js']);
    exit;
endif;

// ====================================================================== Organizer / admin (Fig 10)
page_header(t('profile'));
?>
<div class="card medium" style="padding:0;overflow:hidden">
  <div class="profile-head">
    <form method="post" enctype="multipart/form-data" class="ig-avatar-form">
      <?= csrf_field() ?><input type="hidden" name="action" value="avatar">
      <label class="ig-ring small-ring" title="<?= e(t('change_photo')) ?>"><?= avatar_html($user, 'ig-avatar') ?><span class="ig-cam" aria-hidden="true">📷</span>
        <input type="file" name="avatar" accept="image/*" class="hidden" data-autosubmit></label>
    </form>
    <span><?= e($isOrg ? t('organizer_profile') : t('role_' . $user['role'])) ?></span>
    <?php if ($user['status'] !== 'active'): ?><?= status_badge($user['status']) ?><?php endif; ?>
  </div>
  <form method="post" style="padding:10px 28px 28px" data-validate novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="profile">
    <label for="full_name"><?= e(t('name')) ?></label>
    <input type="text" id="full_name" name="full_name" value="<?= e(post('full_name', $user['full_name'])) ?>" required><?= field_error($errors, 'full_name') ?>

    <?php if ($isOrg): ?>
      <label for="university"><?= e(t('university')) ?></label>
      <input type="text" id="university" name="university" value="<?= e($org['university'] ?? '') ?>">
    <?php endif; ?>

    <label for="phone"><?= e(t('phone_number')) ?></label>
    <input type="tel" id="phone" name="phone" value="<?= e(post('phone', $user['phone'] ?? '')) ?>"><?= field_error($errors, 'phone') ?>
    <label for="email"><?= e(t('email')) ?></label>
    <input type="email" id="email" name="email" value="<?= e(post('email', $user['email'])) ?>" required><?= field_error($errors, 'email') ?>
    <input type="hidden" name="dob" value="<?= e($user['dob']) ?>">
    <?php if ($isOrg): ?>
      <label for="language_skills"><?= e(t('language_skills')) ?></label>
      <textarea id="language_skills" name="language_skills" placeholder="<?= e(t('ph_language_skills')) ?>"><?= e($org['language_skills'] ?? '') ?></textarea>
      <label for="training"><?= e(t('training_license')) ?></label>
      <textarea id="training" name="training" placeholder="<?= e(t('ph_training')) ?>"><?= e($org['training'] ?? '') ?></textarea>
      <label for="skills"><?= e(t('skills')) ?></label>
      <input type="text" id="skills" name="skills" value="<?= e($org['skills'] ?? '') ?>">

      <?php [$oa, $oc] = organizer_rating($user['id']);
        $made = q('SELECT id, title, status FROM trips WHERE organizer_id = ? ORDER BY start_date DESC', [$user['id']])->fetchAll(); ?>
      <label><?= e(t('rating')) ?>:</label>
      <div><?= stars($oa) ?> <span class="muted">(<?= e(t('n_reviews', ['n' => $oc])) ?>)</span></div>
      <h3 class="mt"><?= e(t('tours_made')) ?></h3>
      <?php if (!$made): ?><p class="muted"><?= e(t('no_trips_yet')) ?></p><?php endif; ?>
      <?php foreach ($made as $m): ?>
        <a class="list-box" href="trip.php?id=<?= (int)$m['id'] ?>"><span><?= e($m['title']) ?></span><?= status_badge($m['status']) ?></a>
      <?php endforeach; ?>
    <?php endif; ?>

    <button class="btn block"><?= e(t('save_changes')) ?></button>
  </form>
</div>

<div class="card medium">
  <h2><?= e(t('change_password')) ?></h2>
  <form method="post" data-validate novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="password">
    <label for="current"><?= e(t('current_password')) ?></label>
    <input type="password" id="current" name="current" required autocomplete="current-password"><?= field_error($errors, 'current') ?>
    <div class="form-grid">
      <div><label for="password"><?= e(t('new_password')) ?></label>
        <input type="password" id="password" name="password" required autocomplete="new-password"><?= field_error($errors, 'password') ?></div>
      <div><label for="password2"><?= e(t('confirm_password')) ?></label>
        <input type="password" id="password2" name="password2" required autocomplete="new-password"><?= field_error($errors, 'password2') ?></div>
    </div>
    <button class="btn outline mt"><?= e(t('change_password')) ?></button>
  </form>
  <p class="mt"><a class="btn danger sm" href="logout.php"><?= e(t('logout')) ?></a></p>
</div>
<?php page_footer(['assets/js/profile.js']);
