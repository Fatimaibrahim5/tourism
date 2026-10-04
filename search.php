<?php
// Feature 5.2 — trip browsing and search by destination, date, price and duration.
require __DIR__ . '/includes/functions.php';

$f = [
    'q' => trim($_GET['q'] ?? ''),
    'from' => $_GET['from'] ?? '',
    'to' => $_GET['to'] ?? '',
    'max_price' => $_GET['max_price'] ?? '',
    'duration' => $_GET['duration'] ?? '',
    'sort' => $_GET['sort'] ?? 'date',
];

$where = ["t.status = 'published'", 't.end_date >= CURDATE()', "u.status = 'active'"];
$params = [];
if ($f['q'] !== '') {
    $where[] = '(t.title LIKE ? OR t.destination LIKE ? OR t.description LIKE ? OR u.full_name LIKE ?)';
    $like = '%' . $f['q'] . '%';
    array_push($params, $like, $like, $like, $like);
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['from'])) { $where[] = 't.start_date >= ?'; $params[] = $f['from']; }
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['to'])) { $where[] = 't.start_date <= ?'; $params[] = $f['to']; }
if (is_numeric($f['max_price'])) { $where[] = 't.price * (100 - t.discount_pct) / 100 <= ?'; $params[] = (float)$f['max_price']; }
$durationSql = 'DATEDIFF(t.end_date, t.start_date) + 1';
match ($f['duration']) {
    '1' => $where[] = "$durationSql = 1",
    '2-3' => $where[] = "$durationSql BETWEEN 2 AND 3",
    '4+' => $where[] = "$durationSql >= 4",
    default => null,
};
$order = match ($f['sort']) {
    'price_asc' => 't.price * (100 - t.discount_pct) ASC',
    'price_desc' => 't.price * (100 - t.discount_pct) DESC',
    'rating' => 'avg_score DESC',
    default => 't.start_date ASC',
};

$trips = q("SELECT t.*, u.full_name AS organizer_name,
                   (SELECT AVG(score) FROM ratings r WHERE r.trip_id = t.id AND r.status = 'approved') AS avg_score,
                   (SELECT COUNT(*) FROM ratings r WHERE r.trip_id = t.id AND r.status = 'approved') AS n_reviews
            FROM trips t JOIN users u ON u.id = t.organizer_id
            WHERE " . implode(' AND ', $where) . " ORDER BY $order", $params)->fetchAll();

// Travel organizers whose name matches the search
$organizers = $f['q'] === '' ? [] : q("SELECT u.id, u.full_name, u.avatar, op.language_skills,
        (SELECT COUNT(*) FROM follows fo WHERE fo.organizer_id = u.id) AS followers,
        (SELECT COUNT(*) FROM trips t WHERE t.organizer_id = u.id AND t.status = 'published') AS n_trips
    FROM users u LEFT JOIN organizer_profiles op ON op.user_id = u.id
    WHERE u.role = 'organizer' AND u.status = 'active' AND u.full_name LIKE ? ORDER BY u.full_name LIMIT 10", ['%' . $f['q'] . '%'])->fetchAll();

$filtered = $f['q'] !== '' || $f['from'] || $f['to'] || $f['max_price'] !== '' || $f['duration'];
$alternatives = (!$trips && $filtered)
    ? q("SELECT t.* FROM trips t JOIN users u ON u.id = t.organizer_id
         WHERE t.status = 'published' AND t.end_date >= CURDATE() AND u.status = 'active' ORDER BY t.start_date LIMIT 3")->fetchAll()
    : [];

page_header(t('search_trips'));
?>
<div class="page-head"><h1><?= e(t('search_trips')) ?></h1></div>

<form class="card filters" method="get">
  <div style="grid-column:1/-1"><label for="q"><?= e(t('search_label')) ?></label>
    <input type="search" id="q" name="q" value="<?= e($f['q']) ?>" placeholder="<?= e(t('ph_search')) ?>"></div>
  <div><label for="from"><?= e(t('date_from')) ?></label><input type="date" id="from" name="from" value="<?= e($f['from']) ?>"></div>
  <div><label for="to"><?= e(t('date_to')) ?></label><input type="date" id="to" name="to" value="<?= e($f['to']) ?>"></div>
  <div><label for="max_price"><?= e(t('max_price')) ?> ($)</label><input type="number" min="0" step="1" id="max_price" name="max_price" value="<?= e($f['max_price']) ?>"></div>
  <div><label for="duration"><?= e(t('duration')) ?></label>
    <select id="duration" name="duration">
      <option value=""><?= e(t('any')) ?></option>
      <option value="1" <?= $f['duration'] === '1' ? 'selected' : '' ?>><?= e(t('one_day')) ?></option>
      <option value="2-3" <?= $f['duration'] === '2-3' ? 'selected' : '' ?>><?= e(t('two_three_days')) ?></option>
      <option value="4+" <?= $f['duration'] === '4+' ? 'selected' : '' ?>><?= e(t('four_plus_days')) ?></option>
    </select></div>
  <div><label for="sort"><?= e(t('sort_by')) ?></label>
    <select id="sort" name="sort">
      <?php foreach (['date' => 'sort_date', 'price_asc' => 'sort_price_asc', 'price_desc' => 'sort_price_desc', 'rating' => 'sort_rating'] as $k => $lbl): ?>
        <option value="<?= $k ?>" <?= $f['sort'] === $k ? 'selected' : '' ?>><?= e(t($lbl)) ?></option>
      <?php endforeach; ?>
    </select></div>
  <div class="row"><button class="btn"><?= e(t('search')) ?></button><a href="search.php"><?= e(t('clear_filters')) ?></a></div>
</form>

<?php if ($organizers): ?>
  <h2><?= e(t('organizers_found')) ?></h2>
  <div class="org-strip">
    <?php foreach ($organizers as $o): [$oa] = organizer_rating((int)$o['id']); ?>
      <div class="org-card">
        <a href="guide.php?id=<?= (int)$o['id'] ?>" class="org-link">
          <span class="ig-ring sm"><?= avatar_html($o, 'ig-avatar') ?></span>
          <b><?= e($o['full_name']) ?> <span class="verified">✔</span></b>
          <span class="muted small"><?= (int)$o['n_trips'] ?> <?= e(t('stat_trips')) ?> · <?= (int)$o['followers'] ?> <?= e(t('followers')) ?><?= $oa ? ' · ★ ' . number_format($oa, 1) : '' ?></span>
        </a>
        <div class="row" style="justify-content:center">
          <?= follow_button((int)$o['id'], 'sm') ?>
          <a class="btn ig-btn" href="guide.php?id=<?= (int)$o['id'] ?>#about">ℹ</a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<p class="muted"><?= e(t('n_results', ['n' => count($trips)])) ?></p>

<?php if (!$trips): ?>
  <div class="card center">
    <p><?= e(t('no_trips_found')) ?></p>
    <a class="btn outline" href="search.php"><?= e(t('clear_filters')) ?></a>
  </div>
  <?php if ($alternatives): ?><h2><?= e(t('you_may_like')) ?></h2><?php $trips = $alternatives; endif; ?>
<?php endif; ?>

<div class="grid grid-3">
  <?php foreach ($trips as $t): $days = (strtotime($t['end_date']) - strtotime($t['start_date'])) / 86400 + 1; ?>
    <div class="card-wrap">
      <?= fav_button((int)$t['id'], 'on-card') ?>
    <a class="trip-card" href="trip.php?id=<?= (int)$t['id'] ?>">
      <?= trip_cover($t) ?>
      <div class="body">
        <h3><?= e($t['title']) ?></h3>
        <div class="meta">📍 <?= e($t['destination']) ?></div>
        <div class="meta">📅 <?= e(fdate($t['start_date'])) ?> · <?= e(t('n_days', ['n' => $days])) ?> · 🗣 <?= e($t['language']) ?></div>
        <?php if (isset($t['avg_score'])): ?><div><?= stars((float)$t['avg_score']) ?> <span class="muted small">(<?= (int)$t['n_reviews'] ?>)</span></div><?php endif; ?>
        <div class="price-row">
          <span class="price"><?php if ($t['discount_pct']): ?><del><?= money($t['price']) ?></del><?php endif; ?><?= money(effective_price($t)) ?></span>
          <?php if ($t['discount_pct']): ?><span class="discount-tag">-<?= (int)$t['discount_pct'] ?>%</span><?php endif; ?>
        </div>
      </div>
    </a></div>
  <?php endforeach; ?>
</div>
<?php page_footer();
