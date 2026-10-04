<?php
// Fig 6 / Fig 7 — trip details popup and flyer: destination, description, places, guide, schedule (REQ-19),
// price, includes/excludes, reviews (REQ-9) and the "Book NOW" action.
require __DIR__ . '/includes/functions.php';

$id = (int)($_GET['id'] ?? 0);
$trip = q('SELECT t.*, u.full_name AS organizer_name, u.avatar AS organizer_avatar, u.status AS organizer_status, op.language_skills
           FROM trips t JOIN users u ON u.id = t.organizer_id LEFT JOIN organizer_profiles op ON op.user_id = u.id
           WHERE t.id = ?', [$id])->fetch();

$owner = $trip && uid() === (int)$trip['organizer_id'];
if (!$trip || ($trip['status'] !== 'published' && !$owner && role() !== 'admin')) {
    http_response_code(404);
    page_header(t('not_found'));
    echo '<div class="card narrow center"><h2>' . e(t('not_found')) . '</h2><a class="btn" href="home.php">' . e(t('back_home')) . '</a></div>';
    page_footer();
    exit;
}

$stops = q('SELECT * FROM trip_stops WHERE trip_id = ? ORDER BY sort_order, id', [$id])->fetchAll();
$reviews = q("SELECT r.*, u.full_name FROM ratings r JOIN users u ON u.id = r.tourist_id
              WHERE r.trip_id = ? AND r.status = 'approved' ORDER BY r.created_at DESC", [$id])->fetchAll();
[$avg, $count] = trip_rating($id);
$left = max(0, $trip['capacity'] - seats_taken($id));
$days = (strtotime($trip['end_date']) - strtotime($trip['start_date'])) / 86400 + 1;
$over = trip_is_over($trip);

$myBooking = null;
$canReview = false;
if (role() === 'tourist') {
    $myBooking = q("SELECT * FROM bookings WHERE trip_id = ? AND tourist_id = ? AND status <> 'cancelled' ORDER BY id DESC LIMIT 1", [$id, uid()])->fetch();
    $canReview = $over && $myBooking && $myBooking['status'] === 'confirmed'
        && !q('SELECT 1 FROM ratings WHERE trip_id = ? AND tourist_id = ?', [$id, uid()])->fetch();
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$shareUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . strtok($_SERVER['REQUEST_URI'], '?') . '?id=' . $id;

page_header($trip['title']);
?>
<article class="detail">
  <div class="card">
    <?php if ($trip['status'] !== 'published'): ?><div class="alert alert-warning"><?= e(t('trip_not_public')) ?>: <?= status_badge($trip['status']) ?></div><?php endif; ?>
    <h1><?= e($trip['title']) ?></h1>
    <?php if ($trip['cover_image']): ?>
      <img class="hero-img" src="<?= e($trip['cover_image']) ?>" alt="<?= e($trip['title']) ?>">
    <?php else: ?>
      <?= trip_cover($trip, 'hero-img') ?>
    <?php endif; ?>

    <div class="facts">
      <div class="fact"><small><?= e(t('destination')) ?></small><b>📍 <?= e($trip['destination']) ?></b></div>
      <div class="fact"><small><?= e(t('schedule')) ?></small><b><?= e(fdate($trip['start_date'])) ?><?= $days > 1 ? ' → ' . e(fdate($trip['end_date'])) : '' ?></b><br><span class="muted small"><?= e(t('n_days', ['n' => $days])) ?></span></div>
      <div class="fact"><small><?= e(t('language')) ?></small><b>🗣 <?= e($trip['language']) ?></b></div>
      <div class="fact"><small><?= e(t('seats_left')) ?></small><b><?= $left ?> / <?= (int)$trip['capacity'] ?></b>
        <div class="capacity-bar"><span style="width:<?= $trip['capacity'] ? round(100 * ($trip['capacity'] - $left) / $trip['capacity']) : 0 ?>%"></span></div></div>
    </div>

    <div class="section-title"><span class="ico">✓</span><?= e(t('overview_area')) ?></div>
    <p class="prewrap"><?= e($trip['description']) ?></p>

    <?php if ($trip['itinerary']): ?>
      <div class="section-title"><span class="ico">🕘</span><?= e(t('itinerary')) ?></div>
      <p class="prewrap"><?= e($trip['itinerary']) ?></p>
    <?php endif; ?>

    <div class="section-title"><span class="ico">☺</span><?= e(t('your_guide')) ?></div>
    <div class="guide-row">
    <a class="guide-chip" href="guide.php?id=<?= (int)$trip['organizer_id'] ?>">
      <?= avatar_html(['full_name' => $trip['organizer_name'], 'avatar' => $trip['organizer_avatar']]) ?>
      <div><b><?= e($trip['organizer_name']) ?></b><br>
        <?php [$oa, $oc] = organizer_rating((int)$trip['organizer_id']); ?>
        <?= stars($oa) ?> <span class="muted small">(<?= $oc ?>)</span><br>
        <span class="muted small"><?= e(t('languages')) ?>: <?= e($trip['language_skills']) ?></span></div>
    </a>
    <div class="row">
      <?= follow_button((int)$trip['organizer_id']) ?>
      <a class="btn ig-btn" href="guide.php?id=<?= (int)$trip['organizer_id'] ?>#about">ℹ <?= e(t('about_organizer')) ?></a>
    </div>
    </div>
  </div>

  <?php if ($stops): ?>
  <div class="card">
    <h2><?= e(t('places_to_visit')) ?></h2>
    <?php foreach ($stops as $s): ?>
      <div class="stop">
        <h2><?= e($s['name']) ?></h2>
        <?php if ($s['image']): ?>
          <img class="stop-img" src="<?= e($s['image']) ?>" alt="<?= e($s['name']) ?>" loading="lazy">
        <?php elseif ($s['kind'] === 'place'): ?>
          <div class="stop-img placeholder" style="--h:<?= crc32($s['name']) % 360 ?>"><span><?= e(mb_substr($s['name'], 0, 1)) ?></span></div>
        <?php endif; ?>
        <?php if ($s['entrance_info']): ?><p class="small mb0"><?= e($s['entrance_info']) ?></p><?php endif; ?>
        <?php if ($s['description']): ?><p class="prewrap"><?= e($s['description']) ?></p><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="card">
    <div class="flyer-price"><?= e(t('trip_price')) ?>:</div>
    <p class="mb0"><?php if ($trip['discount_pct']): ?><del class="muted"><?= money($trip['price']) ?></del> <span class="discount-tag">-<?= (int)$trip['discount_pct'] ?>%</span> <?php endif; ?>
      <b><?= money(effective_price($trip)) ?></b> / <?= e(t('person')) ?></p>
    <?php if ($trip['includes']): ?><p><b><?= e(t('includes')) ?>:</b> <?= e($trip['includes']) ?></p><?php endif; ?>
    <?php if ($trip['excludes']): ?><p><b><?= e(t('excludes')) ?>:</b> <?= e($trip['excludes']) ?></p><?php endif; ?>
    <p><b>🚌 <?= e(t('info_transport')) ?>:</b><br><span class="prewrap"><?= e($trip['transport_info'] ?: t('no_transport_info')) ?></span></p>
    <div class="row">
      <a class="btn outline sm" href="home.php?trip=<?= $id ?>">🗺 <?= e(t('view_on_map')) ?></a>
      <a class="btn outline sm" href="memories.php?trip=<?= $id ?>">📷 <?= e(t('travel_memories')) ?></a>
      <?= fav_button($id, 'inline-fav') ?>
      <button class="btn outline sm" type="button" data-share="<?= e($shareUrl) ?>" data-title="<?= e($trip['title']) ?>" data-text="<?= e($trip['destination'] . ' — ' . fdate($trip['start_date'])) ?>">↗ <?= e(t('share')) ?></button>
    </div>
  </div>

  <div class="card" id="reviews">
    <h2><?= e(t('ratings_reviews')) ?></h2>
    <p><?= stars($avg) ?> <span class="muted">(<?= e(t('n_reviews', ['n' => $count])) ?>)</span></p>

    <?php if ($canReview): ?>
      <form method="post" action="review.php" class="card flat" data-validate novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="trip_id" value="<?= $id ?>">
        <h3><?= e(t('rate_this_trip')) ?></h3>
        <div class="star-input">
          <?php for ($i = 5; $i >= 1; $i--): ?>
            <input type="radio" id="s<?= $i ?>" name="score" value="<?= $i ?>" <?= $i === 5 ? 'required' : '' ?>><label for="s<?= $i ?>" title="<?= $i ?>">★</label>
          <?php endfor; ?>
        </div>
        <label for="comment"><?= e(t('comment')) ?></label>
        <textarea id="comment" name="comment" maxlength="1000"></textarea>
        <button class="btn mt"><?= e(t('submit_review')) ?></button>
        <p class="muted small"><?= e(t('review_moderated')) ?></p>
      </form>
    <?php elseif (role() === 'tourist' && !$over && $myBooking): ?>
      <p class="muted small"><?= e(t('review_after_trip')) ?></p>
    <?php endif; ?>

    <?php if (!$reviews): ?><p class="muted"><?= e(t('no_reviews_yet')) ?></p><?php endif; ?>
    <?php foreach ($reviews as $r): ?>
      <div class="review">
        <div class="row between"><b><?= e($r['full_name']) ?></b><span class="muted small"><?= e(fdate($r['created_at'])) ?></span></div>
        <?= stars((float)$r['score'], false) ?>
        <?php if ($r['comment']): ?><p class="mb0 prewrap"><?= e($r['comment']) ?></p><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="sticky-cta">
    <div><span class="price"><?= money(effective_price($trip)) ?></span> <span class="muted small">/ <?= e(t('person')) ?></span></div>
    <?php if ($myBooking): ?>
      <a class="btn outline" href="profile.php#bookings"><?= e(t('you_joined')) ?> · <?= e(t('st_' . $myBooking['status'])) ?></a>
    <?php elseif ($over): ?>
      <span class="badge"><?= e(t('trip_finished')) ?></span>
    <?php elseif ($left <= 0): ?>
      <span class="badge b-cancelled"><?= e(t('trip_full')) ?></span>
    <?php elseif ($owner): ?>
      <a class="btn" href="org_trip_form.php?id=<?= $id ?>"><?= e(t('edit_trip')) ?></a>
    <?php elseif (in_array(role(), [null, 'tourist'], true)): ?>
      <a class="btn" href="book.php?trip=<?= $id ?>"><?= e(t('book_now')) ?></a>
    <?php endif; ?>
  </div>
</article>
<?php page_footer();
