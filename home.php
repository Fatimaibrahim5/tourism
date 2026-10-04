<?php
// Fig 5 — interactive map of available tours (REQ-1, REQ-18) with ratings and the
// "Info about tour", "Join" and "Info about transportation" actions.
require __DIR__ . '/includes/functions.php';

$trips = q("SELECT t.*, u.full_name AS organizer_name,
                   (SELECT AVG(score) FROM ratings r WHERE r.trip_id = t.id AND r.status = 'approved') AS avg_score,
                   (SELECT COUNT(*) FROM ratings r WHERE r.trip_id = t.id AND r.status = 'approved') AS n_reviews
            FROM trips t JOIN users u ON u.id = t.organizer_id
            WHERE t.status = 'published' AND t.end_date >= CURDATE() AND u.status = 'active'
            ORDER BY t.start_date")->fetchAll();

$stops = [];
if ($trips) {
    $ids = implode(',', array_map('intval', array_column($trips, 'id')));
    foreach (q("SELECT trip_id, name, kind, lat, lng FROM trip_stops WHERE trip_id IN ($ids) AND lat IS NOT NULL ORDER BY sort_order")->fetchAll() as $s) {
        $stops[$s['trip_id']][] = $s;
    }
}

$mapData = array_map(fn($t) => [
    'id' => (int)$t['id'],
    'title' => $t['title'],
    'destination' => $t['destination'],
    'lat' => (float)$t['lat'],
    'lng' => (float)$t['lng'],
    'dates' => fdate($t['start_date']) . ($t['end_date'] !== $t['start_date'] ? ' → ' . fdate($t['end_date']) : ''),
    'price' => money(effective_price($t)),
    'discount' => (int)$t['discount_pct'],
    'rating' => round((float)$t['avg_score'], 1),
    'reviews' => (int)$t['n_reviews'],
    'seats' => max(0, $t['capacity'] - seats_taken((int)$t['id'])),
    'transport' => $t['transport_info'] ?: t('no_transport_info'),
    'organizer' => $t['organizer_name'],
    'stops' => array_map(fn($s) => ['name' => $s['name'], 'kind' => $s['kind'], 'lat' => (float)$s['lat'], 'lng' => (float)$s['lng']], $stops[$t['id']] ?? []),
], $trips);

$focus = (int)($_GET['trip'] ?? 0);
page_header(t('map'), ['map' => true]);
?>
<div class="map-screen">
  <div class="map-wrap">
    <div id="map" role="application" aria-label="<?= e(t('map')) ?>"></div>
    <div class="map-legend">
      <span style="--c:var(--green)"><?= e(t('pin_trip')) ?></span>
      <span style="--c:#0e7490"><?= e(t('pin_hotel')) ?></span>
      <span style="--c:#c2410c"><?= e(t('pin_restaurant')) ?></span>
      <span style="--c:#7c3aed"><?= e(t('pin_meeting')) ?></span>
    </div>

    <div class="map-panel" id="mapPanel">
      <?php if (!$trips): ?>
        <p class="muted"><?= e(t('no_trips')) ?></p>
      <?php else: ?>
        <div class="row between">
          <div><span class="trip-name" id="pTitle"></span> <span class="muted small" id="pDates"></span></div>
          <div class="price" id="pPrice"></div>
        </div>
        <h3 class="mt"><?= e(t('ratings_reviews')) ?></h3>
        <div class="pill-actions">
          <span id="pStars"></span><span class="muted small" id="pReviews"></span>
          <a class="btn ghost" id="pInfo" href="#"><?= e(t('info_about_tour')) ?> ›</a>
        </div>
        <div class="pill-actions">
          <a class="btn ghost" id="pJoin" href="#"><?= e(t('join')) ?></a>
          <button class="btn ghost" type="button" data-modal="transportModal"><?= e(t('info_transport')) ?></button>
          <span class="muted small" id="pSeats"></span>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($trips): ?>
  <h2 class="mt"><?= e(t('available_trips')) ?></h2>
  <div class="grid grid-3">
    <?php foreach ($trips as $t): ?>
      <div class="card-wrap">
        <?= fav_button((int)$t['id'], 'on-card') ?>
      <a class="trip-card" href="trip.php?id=<?= (int)$t['id'] ?>">
        <?= trip_cover($t) ?>
        <div class="body">
          <h3><?= e($t['title']) ?></h3>
          <div class="meta">📍 <?= e($t['destination']) ?> · 📅 <?= e(fdate($t['start_date'])) ?></div>
          <div><?= stars((float)$t['avg_score']) ?> <span class="muted small">(<?= (int)$t['n_reviews'] ?>)</span></div>
          <div class="price-row">
            <span class="price"><?php if ($t['discount_pct']): ?><del><?= money($t['price']) ?></del><?php endif; ?><?= money(effective_price($t)) ?></span>
            <?php if ($t['discount_pct']): ?><span class="discount-tag">-<?= (int)$t['discount_pct'] ?>%</span><?php endif; ?>
          </div>
        </div>
      </a></div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<div class="modal" id="transportModal" role="dialog" aria-modal="true">
  <div class="modal-box">
    <button class="modal-close" aria-label="<?= e(t('close')) ?>">×</button>
    <h2>🚌 <?= e(t('info_transport')) ?></h2>
    <p class="trip-name" id="tTitle"></p>
    <p class="prewrap" id="tBody"></p>
  </div>
</div>

<script>
window.MAP_TRIPS = <?= json_encode($mapData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
window.MAP_FOCUS = <?= $focus ?>;
</script>
<?php page_footer(['assets/js/map.js']);
