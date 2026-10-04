<?php
// REQ-11 — travel organizers manage their trips: create, update, apply discounts, delete.
require __DIR__ . '/includes/functions.php';

$user = require_login(['organizer']);
$approved = $user['status'] === 'active';

if (is_post()) {
    check_csrf();
    $trip = q('SELECT * FROM trips WHERE id = ? AND organizer_id = ?', [(int)post('trip'), $user['id']])->fetch();
    if (!$trip) { flash('error', t('not_found')); redirect('org_dashboard.php'); }
    $active = (int)q("SELECT COUNT(*) FROM bookings WHERE trip_id = ? AND status IN ('pending','confirmed')", [$trip['id']])->fetchColumn();

    switch (post('action')) {
        case 'discount':
            $pct = (int)post('discount');
            if ($pct < 0 || $pct > MAX_DISCOUNT_PCT) {
                flash('error', t('discount_rule', ['n' => MAX_DISCOUNT_PCT]));
            } else {
                q('UPDATE trips SET discount_pct = ? WHERE id = ?', [$pct, $trip['id']]);
                flash('success', t('discount_applied', ['n' => $pct]));
            }
            break;
        case 'publish':
            if (!$approved) { flash('error', t('pending_cannot_publish')); break; }
            q("UPDATE trips SET status = 'published' WHERE id = ?", [$trip['id']]);
            notify_followers((int)$trip['id']);
            flash('success', t('trip_published'));
            break;
        case 'unpublish':
            if ($active) { flash('error', t('has_bookings_unpublish')); break; }
            q("UPDATE trips SET status = 'draft' WHERE id = ?", [$trip['id']]);
            flash('success', t('trip_unpublished'));
            break;
        case 'delete':
            // Business rule: a trip with active bookings cannot be deleted unless an administrator overrides
            if ($active) {
                flash('error', t('delete_blocked', ['n' => $active]));
                notify_admins('Override requested: delete trip "' . $trip['title'] . '"',
                    "{$user['full_name']} wants to delete trip #{$trip['id']} \"{$trip['title']}\" which has $active active booking(s).\nOpen Admin › Trips to override if appropriate.");
            } else {
                q('DELETE FROM trips WHERE id = ?', [$trip['id']]);
                audit('trip_deleted', "trip #{$trip['id']} {$trip['title']}");
                flash('success', t('trip_deleted'));
            }
            break;
    }
    redirect('org_dashboard.php');
}

$trips = q("SELECT t.*,
              (SELECT COALESCE(SUM(seats),0) FROM bookings b WHERE b.trip_id = t.id AND b.status IN ('pending','confirmed')) AS booked,
              (SELECT COUNT(*) FROM bookings b WHERE b.trip_id = t.id AND b.status = 'pending') AS pending_n,
              (SELECT AVG(score) FROM ratings r WHERE r.trip_id = t.id AND r.status = 'approved') AS avg_score
            FROM trips t WHERE t.organizer_id = ? ORDER BY t.start_date DESC", [$user['id']])->fetchAll();
$revenue = (float)q("SELECT COALESCE(SUM(p.amount),0) FROM payments p JOIN bookings b ON b.id = p.booking_id JOIN trips t ON t.id = b.trip_id
                     WHERE t.organizer_id = ? AND p.status = 'paid'", [$user['id']])->fetchColumn();
$upcoming = array_filter($trips, fn($t) => $t['end_date'] >= date('Y-m-d') && $t['status'] === 'published');
[$avg, $cnt] = organizer_rating($user['id']);

page_header(t('my_trips'));
?>
<?php if (!$approved): ?>
  <div class="alert alert-warning">⏳ <?= e(t('pending_notice')) ?></div>
<?php endif; ?>

<div class="page-head">
  <h1><?= e(t('my_trips')) ?></h1>
  <?php if ($approved): ?><a class="btn" href="org_trip_form.php">＋ <?= e(t('create_trip')) ?></a><?php endif; ?>
</div>

<div class="grid grid-4 mb">
  <div class="stat"><small><?= e(t('trips')) ?></small><div class="num"><?= count($trips) ?></div></div>
  <div class="stat"><small><?= e(t('upcoming')) ?></small><div class="num"><?= count($upcoming) ?></div></div>
  <div class="stat"><small><?= e(t('revenue_paid')) ?></small><div class="num"><?= money($revenue) ?></div></div>
  <div class="stat"><small><?= e(t('rating')) ?></small><div class="num"><?= $avg ? number_format($avg, 1) : '—' ?></div><span class="muted small"><?= e(t('n_reviews', ['n' => $cnt])) ?></span></div>
</div>

<?php if (!$trips): ?>
  <div class="card center"><p class="muted"><?= e(t('no_trips_yet')) ?></p></div>
<?php else: ?>
<div class="table-wrap mt">
<table>
  <thead><tr><th><?= e(t('trip')) ?></th><th><?= e(t('schedule')) ?></th><th><?= e(t('status')) ?></th><th><?= e(t('seats')) ?></th><th><?= e(t('price')) ?></th><th><?= e(t('discount')) ?></th><th><?= e(t('actions')) ?></th></tr></thead>
  <tbody>
  <?php foreach ($trips as $t): ?>
    <tr>
      <td><a href="trip.php?id=<?= (int)$t['id'] ?>"><b><?= e($t['title']) ?></b></a><br><span class="muted small">📍 <?= e($t['destination']) ?></span>
        <?php if ($t['avg_score']): ?><br><?= stars((float)$t['avg_score']) ?><?php endif; ?></td>
      <td class="small"><?= e(fdate($t['start_date'])) ?><br><?= e(fdate($t['end_date'])) ?><?= trip_is_over($t) ? '<br><span class="badge">' . e(t('trip_finished')) . '</span>' : '' ?></td>
      <td><?= status_badge($t['status']) ?></td>
      <td><?= (int)$t['booked'] ?> / <?= (int)$t['capacity'] ?>
        <?php if ($t['pending_n']): ?><br><span class="badge b-pending"><?= (int)$t['pending_n'] ?> <?= e(t('st_pending')) ?></span><?php endif; ?></td>
      <td><?= money(effective_price($t)) ?><?php if ($t['discount_pct']): ?><br><del class="muted small"><?= money($t['price']) ?></del><?php endif; ?></td>
      <td>
        <form method="post" class="row" style="flex-wrap:nowrap">
          <?= csrf_field() ?><input type="hidden" name="action" value="discount"><input type="hidden" name="trip" value="<?= (int)$t['id'] ?>">
          <input type="number" name="discount" min="0" max="<?= MAX_DISCOUNT_PCT ?>" value="<?= (int)$t['discount_pct'] ?>" style="width:72px;padding:6px">%
          <button class="btn sm outline"><?= e(t('apply')) ?></button>
        </form>
      </td>
      <td>
        <div class="row">
          <a class="btn sm" href="org_trip_form.php?id=<?= (int)$t['id'] ?>"><?= e(t('edit')) ?></a>
          <a class="btn sm outline" href="org_bookings.php?trip=<?= (int)$t['id'] ?>"><?= e(t('bookings')) ?></a>
          <a class="btn sm outline" href="memories.php?trip=<?= (int)$t['id'] ?>">📷</a>
          <?php if ($t['status'] === 'draft' && $approved): ?>
            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="publish"><input type="hidden" name="trip" value="<?= (int)$t['id'] ?>"><button class="btn sm success"><?= e(t('publish')) ?></button></form>
          <?php elseif ($t['status'] === 'published'): ?>
            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="unpublish"><input type="hidden" name="trip" value="<?= (int)$t['id'] ?>"><button class="btn sm outline"><?= e(t('unpublish')) ?></button></form>
          <?php endif; ?>
          <form method="post" class="inline" data-confirm="<?= e(t('confirm_delete_trip')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="trip" value="<?= (int)$t['id'] ?>"><button class="btn sm danger"><?= e(t('delete')) ?></button></form>
        </div>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<p class="muted small mt"><?= e(t('discount_rule', ['n' => MAX_DISCOUNT_PCT])) ?> · <?= e(t('delete_rule')) ?></p>
<?php endif; ?>
<?php page_footer();
