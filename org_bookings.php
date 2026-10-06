<?php
// Organizers view tourist enrollments for their trip and record cash payments (cash logging).
require __DIR__ . '/includes/functions.php';

$user = require_login(['organizer', 'admin']);
$tripId = (int)($_GET['trip'] ?? $_POST['trip'] ?? 0);
$trip = q('SELECT * FROM trips WHERE id = ?', [$tripId])->fetch();
if (!$trip || ($user['role'] === 'organizer' && (int)$trip['organizer_id'] !== (int)$user['id'])) {
    flash('error', t('not_found'));
    redirect(home_for_role());
}

if (is_post()) {
    check_csrf();
    $bid = (int)post('booking');
    $b = q("SELECT b.*, u.email FROM bookings b JOIN users u ON u.id = b.tourist_id WHERE b.id = ? AND b.trip_id = ?", [$bid, $tripId])->fetch();
    if ($b && !can_access_user($b['email'])) deny_demo('org_bookings.php?trip=' . $tripId);
    if ($b && post('action') === 'cash_received' && $b['status'] === 'pending') {
        $pay = q("SELECT * FROM payments WHERE booking_id = ? AND method = 'cash' AND status = 'pending'", [$bid])->fetch();
        if ($pay) {
            $pdo = db();
            $pdo->beginTransaction();
            q("UPDATE payments SET status = 'paid', message = ? WHERE id = ?", ['Cash received by ' . $user['full_name'], $pay['id']]);
            q("UPDATE bookings SET status = 'confirmed' WHERE id = ?", [$bid]);
            $pdo->commit();
            audit('cash_received', "booking #$bid " . money($pay['amount']));
            send_mail((int)$b['tourist_id'], $b['email'], 'Booking confirmed: ' . $trip['title'],
                "Hello {$b['contact_name']},\n\nYour cash payment of " . money($pay['amount']) . " was received. Your booking #$bid for \"{$trip['title']}\" on " . fdate($trip['start_date']) . " is confirmed.");
            flash('success', t('cash_recorded'));
        }
    }
    redirect('org_bookings.php?trip=' . $tripId);
}

$rows = q("SELECT b.*, u.email,
             (SELECT method FROM payments p WHERE p.booking_id = b.id ORDER BY p.id DESC LIMIT 1) AS method,
             (SELECT status FROM payments p WHERE p.booking_id = b.id ORDER BY p.id DESC LIMIT 1) AS pay_status
           FROM bookings b JOIN users u ON u.id = b.tourist_id WHERE b.trip_id = ?" . demo_scope('u.email') . " ORDER BY b.created_at DESC", [$tripId])->fetchAll();
$seats = seats_taken($tripId);

page_header(t('bookings'));
?>
<div class="page-head">
  <div><h1><?= e(t('bookings')) ?>: <?= e($trip['title']) ?></h1>
    <span class="muted"><?= e(fdate($trip['start_date'])) ?> · <?= e(t('seats')) ?> <?= $seats ?> / <?= (int)$trip['capacity'] ?></span></div>
  <a href="<?= $user['role'] === 'admin' ? 'admin_trips.php' : 'org_dashboard.php' ?>">‹ <?= e(t('back')) ?></a>
</div>
<?php if (!$rows): ?>
  <div class="card center muted"><?= e(t('no_bookings_trip')) ?></div>
<?php else: ?>
<div class="table-wrap">
<table>
  <thead><tr><th>#</th><th><?= e(t('full_name')) ?></th><th><?= e(t('phone_number')) ?></th><th><?= e(t('language')) ?></th><th><?= e(t('seats')) ?></th><th><?= e(t('total')) ?></th><th><?= e(t('payment')) ?></th><th><?= e(t('status')) ?></th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><a href="receipt.php?booking=<?= (int)$r['id'] ?>"><?= (int)$r['id'] ?></a></td>
      <td><?= e($r['contact_name']) ?><br><span class="muted small"><?= e($r['email']) ?></span></td>
      <td><a href="tel:<?= e($r['phone']) ?>"><?= e($r['phone']) ?></a></td>
      <td><?= e($r['language']) ?></td>
      <td><?= (int)$r['seats'] ?></td>
      <td><?= money($r['total']) ?></td>
      <td><?= $r['method'] === 'card' ? '💳' : '💵' ?> <?= status_badge($r['pay_status'] ?? 'pending') ?></td>
      <td><?= status_badge($r['status']) ?></td>
      <td>
        <?php if ($r['status'] === 'pending' && $r['method'] === 'cash' && $r['pay_status'] === 'pending'): ?>
          <form method="post" data-confirm="<?= e(t('confirm_cash')) ?>"><?= csrf_field() ?>
            <input type="hidden" name="trip" value="<?= $tripId ?>"><input type="hidden" name="booking" value="<?= (int)$r['id'] ?>"><input type="hidden" name="action" value="cash_received">
            <button class="btn sm success"><?= e(t('cash_received')) ?></button></form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
<?php page_footer();
