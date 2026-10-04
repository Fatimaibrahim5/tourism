<?php
// REQ-2 — join a tour by submitting name, phone number and language, then choose card or cash (REQ-6).
// Seats are allocated atomically (row lock on the trip) to prevent overbooking (Safety REQ-5).
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/data.php';

$user = require_login(['tourist']);
$tripId = (int)($_GET['trip'] ?? $_POST['trip_id'] ?? 0);
$trip = q("SELECT t.*, u.email AS org_email, u.full_name AS org_name FROM trips t JOIN users u ON u.id = t.organizer_id
           WHERE t.id = ? AND t.status = 'published'", [$tripId])->fetch();
if (!$trip) { flash('error', t('not_found')); redirect('home.php'); }
if ($trip['start_date'] < date('Y-m-d')) { flash('error', t('trip_started')); redirect('trip.php?id=' . $tripId); }

expire_stale_bookings();
$existing = q("SELECT id FROM bookings WHERE trip_id = ? AND tourist_id = ? AND status <> 'cancelled'", [$tripId, $user['id']])->fetch();
if ($existing) { flash('info', t('already_joined')); redirect('profile.php#bookings'); }

$left = max(0, $trip['capacity'] - seats_taken($tripId));
$unit = effective_price($trip);
$v = [
    'contact_name' => post('contact_name', $user['full_name']),
    'phone' => post('phone', $user['phone'] ?? ''),
    'language' => post('language', $user['language'] ?? 'English'),
    'seats' => (int)post('seats', '1'),
    'method' => post('method', 'card'),
];
$errors = [];

if (is_post()) {
    check_csrf();
    if (mb_strlen($v['contact_name']) < 2) $errors['contact_name'] = t('err_name');
    if (!preg_match('/^[+0-9 ()-]{6,20}$/', $v['phone'])) $errors['phone'] = t('invalid_phone');
    if (!in_array($v['language'], LANGUAGES, true)) $errors['language'] = t('required');
    if ($v['seats'] < 1 || $v['seats'] > 10) $errors['seats'] = t('err_seats');
    if (!in_array($v['method'], ['card', 'cash'], true)) $errors['method'] = t('required');
    if (empty($_POST['policy'])) $errors['policy'] = t('accept_policy_err');

    if (!$errors) {
        $pdo = db();
        try {
            $pdo->beginTransaction();
            // Lock the trip row so two tourists cannot take the last seat at the same time
            $locked = q('SELECT capacity FROM trips WHERE id = ? FOR UPDATE', [$tripId])->fetch();
            $taken = (int)q("SELECT COALESCE(SUM(seats),0) FROM bookings WHERE trip_id = ? AND status IN ('pending','confirmed')", [$tripId])->fetchColumn();
            if ($taken + $v['seats'] > $locked['capacity']) {
                $pdo->rollBack();
                $errors['seats'] = $locked['capacity'] - $taken > 0 ? t('only_n_seats', ['n' => $locked['capacity'] - $taken]) : t('trip_full');
            } else {
                $total = round($unit * $v['seats'], 2);
                q('INSERT INTO bookings (trip_id, tourist_id, contact_name, phone, language, seats, unit_price, total, status)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [$tripId, $user['id'], $v['contact_name'], $v['phone'], $v['language'], $v['seats'], $unit, $total, 'pending']);
                $bookingId = (int)$pdo->lastInsertId();
                q('INSERT INTO payments (booking_id, tourist_id, amount, method, status) VALUES (?, ?, ?, ?, ?)',
                  [$bookingId, $user['id'], $total, $v['method'], 'pending']);
                $pdo->commit();
                audit('booking_created', "booking #$bookingId trip #$tripId {$v['seats']} seat(s) {$v['method']}");

                if ($v['method'] === 'card') redirect('bank.php?booking=' . $bookingId);

                send_mail($user['id'], $user['email'], 'Reservation received: ' . $trip['title'],
                    "Hello {$v['contact_name']},\n\nYour seat(s) for \"{$trip['title']}\" on " . fdate($trip['start_date']) . " are reserved.\nSeats: {$v['seats']}\nAmount to pay in cash: " . money($total) . "\n\nYour booking will be confirmed once the travel organizer records your cash payment.\nBooking reference: #$bookingId");
                notify_user((int)$trip['organizer_id'], 'New reservation (cash): ' . $trip['title'],
                    "{$v['contact_name']} reserved {$v['seats']} seat(s) and will pay " . money($total) . " in cash.\nPhone: {$v['phone']}\nLanguage: {$v['language']}");
                flash('success', t('reserved_cash'));
                redirect('receipt.php?booking=' . $bookingId);
            }
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $ex;
        }
    }
}

page_header(t('join_trip'));
?>
<div class="card medium">
  <h1 class="form-title"><?= e(t('join_trip')) ?></h1>
  <div style="border-radius:12px;overflow:hidden"><?= trip_cover($trip) ?></div>
  <h2 class="mt"><?= e($trip['title']) ?></h2>
  <p class="muted">📍 <?= e($trip['destination']) ?> · 📅 <?= e(fdate($trip['start_date'])) ?> · <?= e(t('seats_left')) ?>: <b><?= $left ?></b></p>

  <?php if ($left <= 0): ?>
    <div class="alert alert-error"><?= e(t('trip_full')) ?></div>
    <a class="btn" href="search.php"><?= e(t('see_alternatives')) ?></a>
  <?php else: ?>
  <form method="post" class="form-panel" data-validate novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="trip_id" value="<?= $tripId ?>">
    <label for="contact_name"><?= e(t('full_name')) ?></label>
    <input type="text" id="contact_name" name="contact_name" value="<?= e($v['contact_name']) ?>" required><?= field_error($errors, 'contact_name') ?>
    <label for="phone"><?= e(t('phone_number')) ?></label>
    <input type="tel" id="phone" name="phone" value="<?= e($v['phone']) ?>" required><?= field_error($errors, 'phone') ?>
    <label for="language"><?= e(t('language')) ?></label>
    <select id="language" name="language"><?= options(LANGUAGES, $v['language']) ?></select>
    <label for="seats"><?= e(t('seats')) ?></label>
    <input type="number" id="seats" name="seats" min="1" max="<?= min(10, $left) ?>" value="<?= max(1, min($v['seats'], $left)) ?>" required><?= field_error($errors, 'seats') ?>

    <label><?= e(t('payment_method')) ?></label>
    <label class="check"><input type="radio" name="method" value="card" <?= $v['method'] === 'card' ? 'checked' : '' ?>> 💳 <?= e(t('pay_card')) ?></label>
    <label class="check"><input type="radio" name="method" value="cash" <?= $v['method'] === 'cash' ? 'checked' : '' ?>> 💵 <?= e(t('pay_cash')) ?></label>

    <div class="row between mt">
      <span><?= e(t('total')) ?>:</span>
      <span class="price" id="total" data-unit="<?= e($unit) ?>"><?= money($unit * max(1, $v['seats'])) ?></span>
    </div>
    <label class="check mt"><input type="checkbox" name="policy" value="1" <?= !empty($_POST['policy']) ? 'checked' : '' ?>>
      <span class="small"><?= e(t('cancel_policy', ['n' => CANCEL_MIN_DAYS])) ?></span></label>
    <?= field_error($errors, 'policy') ?>
    <button class="btn block"><?= e(t('continue')) ?></button>
  </form>
  <?php endif; ?>
</div>
<script>
  (function () {
    const s = document.getElementById('seats'), tot = document.getElementById('total');
    if (s && tot) s.addEventListener('input', () => {
      const n = Math.max(1, parseInt(s.value || '1', 10));
      tot.textContent = '$' + (parseFloat(tot.dataset.unit) * n).toFixed(2);
    });
  })();
</script>
<?php page_footer();
