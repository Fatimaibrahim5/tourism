<?php
// Administrators manage all trips; only an administrator can override deletion of a trip with active bookings
// (business rule). An override cancels and refunds the bookings and notifies the tourists.
require __DIR__ . '/includes/functions.php';

$admin = require_login(['admin']);

if (is_post()) {
    check_csrf();
    $trip = q('SELECT t.*, u.email AS org_email FROM trips t JOIN users u ON u.id = t.organizer_id WHERE t.id = ?', [(int)post('trip')])->fetch();
    if ($trip && !can_access_user($trip['org_email'])) deny_demo('admin_trips.php');
    if ($trip) {
        $action = post('action');
        $active = q("SELECT b.*, u.email FROM bookings b JOIN users u ON u.id = b.tourist_id WHERE b.trip_id = ? AND b.status IN ('pending','confirmed')", [$trip['id']])->fetchAll();
        if ($action === 'cancel' || $action === 'delete') {
            if ($action === 'delete' && $active && post('override') !== '1') {
                flash('error', t('delete_blocked', ['n' => count($active)]));
                redirect('admin_trips.php');
            }
            $pdo = db();
            $pdo->beginTransaction();
            foreach ($active as $b) {
                q("UPDATE bookings SET status = 'cancelled', cancelled_at = NOW() WHERE id = ?", [$b['id']]);
                q("UPDATE payments SET status = 'refunded', message = 'Trip cancelled by administrator' WHERE booking_id = ? AND status = 'paid'", [$b['id']]);
                q("UPDATE payments SET status = 'failed', message = 'Trip cancelled' WHERE booking_id = ? AND status = 'pending'", [$b['id']]);
            }
            if ($action === 'delete') q('DELETE FROM trips WHERE id = ?', [$trip['id']]);
            else q("UPDATE trips SET status = 'cancelled' WHERE id = ?", [$trip['id']]);
            $pdo->commit();
            foreach ($active as $b) {
                send_mail((int)$b['tourist_id'], $b['email'], 'Trip cancelled: ' . $trip['title'],
                    "We are sorry — the trip \"{$trip['title']}\" on " . fdate($trip['start_date']) . " has been cancelled. Your booking #{$b['id']} is cancelled and any card payment has been refunded.");
            }
            notify_user((int)$trip['organizer_id'], 'Trip ' . ($action === 'delete' ? 'deleted' : 'cancelled') . ' by administrator: ' . $trip['title'], post('reason') ?: 'No reason given.');
            audit($action === 'delete' ? 'trip_deleted_override' : 'trip_cancelled', "trip #{$trip['id']} {$trip['title']}, " . count($active) . ' booking(s) cancelled. ' . post('reason'));
            flash('success', $action === 'delete' ? t('trip_deleted') : t('trip_cancelled_ok'));
        } elseif ($action === 'publish' || $action === 'unpublish') {
            q('UPDATE trips SET status = ? WHERE id = ?', [$action === 'publish' ? 'published' : 'draft', $trip['id']]);
            if ($action === 'publish') notify_followers((int)$trip['id']);
            audit('trip_' . $action, "trip #{$trip['id']}");
            flash('success', t('saved'));
        }
    }
    redirect('admin_trips.php?' . http_build_query(['status' => $_GET['status'] ?? '']));
}

$status = in_array($_GET['status'] ?? '', ['published', 'draft', 'cancelled'], true) ? $_GET['status'] : '';
$trips = q("SELECT t.*, u.full_name AS org_name,
              (SELECT COALESCE(SUM(seats),0) FROM bookings b WHERE b.trip_id = t.id AND b.status IN ('pending','confirmed')) AS booked,
              (SELECT AVG(score) FROM ratings r WHERE r.trip_id = t.id AND r.status = 'approved') AS avg_score
            FROM trips t JOIN users u ON u.id = t.organizer_id WHERE 1=1" . ($status ? ' AND t.status = ?' : '') . demo_scope('u.email') . "
            ORDER BY t.start_date DESC", $status ? [$status] : [])->fetchAll();

page_header(t('trips'), ['main_class' => 'wide']);
?>
<div class="page-head"><h1><?= e(t('trips')) ?></h1></div>
<div class="tabs">
  <a href="?" class="<?= !$status ? 'active' : '' ?>"><?= e(t('all')) ?></a>
  <?php foreach (['published', 'draft', 'cancelled'] as $s): ?><a href="?status=<?= $s ?>" class="<?= $status === $s ? 'active' : '' ?>"><?= e(t('st_' . $s)) ?></a><?php endforeach; ?>
</div>
<div class="table-wrap">
<table>
  <thead><tr><th><?= e(t('trip')) ?></th><th><?= e(t('role_organizer')) ?></th><th><?= e(t('schedule')) ?></th><th><?= e(t('seats')) ?></th><th><?= e(t('price')) ?></th><th><?= e(t('status')) ?></th><th><?= e(t('actions')) ?></th></tr></thead>
  <tbody>
  <?php foreach ($trips as $t): ?>
    <tr>
      <td><a href="trip.php?id=<?= (int)$t['id'] ?>"><b><?= e($t['title']) ?></b></a><br><span class="muted small"><?= e($t['destination']) ?></span>
        <?php if ($t['avg_score']): ?><br><?= stars((float)$t['avg_score']) ?><?php endif; ?></td>
      <td><a href="guide.php?id=<?= (int)$t['organizer_id'] ?>"><?= e($t['org_name']) ?></a></td>
      <td class="small"><?= e(fdate($t['start_date'])) ?> → <?= e(fdate($t['end_date'])) ?></td>
      <td><?= (int)$t['booked'] ?> / <?= (int)$t['capacity'] ?></td>
      <td><?= money(effective_price($t)) ?><?= $t['discount_pct'] ? ' <span class="discount-tag">-' . (int)$t['discount_pct'] . '%</span>' : '' ?></td>
      <td><?= status_badge($t['status']) ?></td>
      <td>
        <form method="post" class="row">
          <?= csrf_field() ?><input type="hidden" name="trip" value="<?= (int)$t['id'] ?>">
          <a class="btn sm outline" href="org_bookings.php?trip=<?= (int)$t['id'] ?>"><?= e(t('bookings')) ?></a>
          <?php if ($t['status'] === 'draft'): ?><button class="btn sm success" name="action" value="publish"><?= e(t('publish')) ?></button>
          <?php elseif ($t['status'] === 'published' && !$t['booked']): ?><button class="btn sm outline" name="action" value="unpublish"><?= e(t('unpublish')) ?></button><?php endif; ?>
          <?php if ($t['status'] !== 'cancelled'): ?>
            <button class="btn sm outline" name="action" value="cancel" data-confirm="<?= e(t('confirm_cancel_trip')) ?>"><?= e(t('cancel_trip')) ?></button>
          <?php endif; ?>
          <?php if ($t['booked']): ?><input type="hidden" name="override" value="1"><?php endif; ?>
          <button class="btn sm danger" name="action" value="delete" data-confirm="<?= e($t['booked'] ? t('confirm_override', ['n' => (int)$t['booked']]) : t('confirm_delete_trip')) ?>"><?= e($t['booked'] ? t('override_delete') : t('delete')) ?></button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php page_footer();
