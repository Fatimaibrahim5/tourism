<?php
// Simulated external Bank System (payment gateway). Only the last 4 digits of the card are kept;
// the full number and CVV are never stored (PCI-DSS, REQ-16). Payment failure leaves the booking
// pending so the tourist can retry or change method (extension 7a); a timeout keeps it pending (7b).
require __DIR__ . '/includes/functions.php';

$user = require_login(['tourist']);
$bookingId = (int)($_GET['booking'] ?? $_POST['booking'] ?? 0);
$b = q("SELECT b.*, t.title, t.start_date, t.organizer_id, p.id AS payment_id, p.status AS pay_status, p.method
        FROM bookings b JOIN trips t ON t.id = b.trip_id JOIN payments p ON p.booking_id = b.id
        WHERE b.id = ? AND b.tourist_id = ? ORDER BY p.id DESC LIMIT 1", [$bookingId, $user['id']])->fetch();

if (!$b || $b['status'] !== 'pending' || $b['pay_status'] === 'paid') {
    flash('info', t('nothing_to_pay'));
    redirect('profile.php#bookings');
}

function luhn_ok(string $n): bool {
    $sum = 0; $alt = false;
    for ($i = strlen($n) - 1; $i >= 0; $i--) {
        $d = (int)$n[$i];
        if ($alt) { $d *= 2; if ($d > 9) $d -= 9; }
        $sum += $d; $alt = !$alt;
    }
    return $sum % 10 === 0;
}

$errors = [];
$result = null;
if (is_post()) {
    check_csrf();
    $holder = post('holder');
    $number = preg_replace('/\D/', '', post('number'));
    $exp = post('exp');
    $cvv = post('cvv');

    if (mb_strlen($holder) < 2) $errors['holder'] = t('required');
    if (strlen($number) < 13 || strlen($number) > 19 || !luhn_ok($number)) $errors['number'] = t('card_invalid');
    if (!preg_match('/^(0[1-9]|1[0-2])\/(\d{2})$/', $exp, $m) || mktime(0, 0, 0, (int)$m[1] + 1, 1, 2000 + (int)$m[2]) <= time()) $errors['exp'] = t('card_expired');
    if (!preg_match('/^\d{3,4}$/', $cvv)) $errors['cvv'] = t('cvv_invalid');

    if (!$errors) {
        $last4 = substr($number, -4);
        $ref = 'BNK-' . strtoupper(bin2hex(random_bytes(5)));
        $pdo = db();
        if ($last4 === '0002') {
            // Declined by the bank → transaction rolled back, booking stays pending (retry allowed)
            q("UPDATE payments SET status = 'failed', card_last4 = ?, bank_ref = ?, message = 'Declined: insufficient funds' WHERE id = ?", [$last4, $ref, $b['payment_id']]);
            audit('payment_failed', "booking #$bookingId declined");
            $result = 'declined';
        } elseif ($last4 === '0119') {
            // Bank did not answer in time → booking kept pending, tourist notified (7b)
            q("UPDATE payments SET status = 'pending', card_last4 = ?, message = 'Bank timeout' WHERE id = ?", [$last4, $b['payment_id']]);
            send_mail($user['id'], $user['email'], 'Payment pending: ' . $b['title'],
                "The bank did not respond in time. Your booking #$bookingId is still pending. Please retry the payment from your profile within " . PENDING_CARD_TTL_MIN . " minutes, otherwise the seats will be released.");
            audit('payment_timeout', "booking #$bookingId");
            $result = 'timeout';
        } else {
            $pdo->beginTransaction();
            q("UPDATE payments SET status = 'paid', card_last4 = ?, bank_ref = ?, message = 'Authorized' WHERE id = ?", [$last4, $ref, $b['payment_id']]);
            q("UPDATE bookings SET status = 'confirmed' WHERE id = ?", [$bookingId]);
            $pdo->commit();
            audit('payment_success', "booking #$bookingId $ref");
            send_mail($user['id'], $user['email'], 'Booking confirmed: ' . $b['title'],
                "Hello {$b['contact_name']},\n\nYour booking is confirmed!\n\nTrip: {$b['title']}\nDate: " . fdate($b['start_date']) . "\nSeats: {$b['seats']}\nAmount paid: " . money($b['total']) . " (card •••• $last4)\nBank reference: $ref\nBooking reference: #$bookingId\n\nSee you soon!");
            notify_user((int)$b['organizer_id'], 'New confirmed booking: ' . $b['title'],
                "{$b['contact_name']} booked {$b['seats']} seat(s) and paid " . money($b['total']) . " by card.\nPhone: {$b['phone']}\nLanguage: {$b['language']}");
            flash('success', t('payment_success'));
            redirect('receipt.php?booking=' . $bookingId);
        }
    }
}

page_header(t('secure_payment'), ['bottom_nav' => false]);
?>
<div class="bank">
  <div class="bank-head">
    <h2>🏦 <?= e(t('bank_name')) ?></h2>
    <div class="lock">🔒 <?= e(t('secure_conn')) ?></div>
  </div>
  <div class="bank-body">
    <div class="row between"><span><?= e($b['title']) ?> · <?= (int)$b['seats'] ?> × <?= money($b['unit_price']) ?></span><b class="price"><?= money($b['total']) ?></b></div>

    <?php if ($result === 'declined'): ?>
      <div class="alert alert-error mt"><?= e(t('payment_declined')) ?></div>
    <?php elseif ($result === 'timeout'): ?>
      <div class="alert alert-warning mt"><?= e(t('payment_timeout')) ?></div>
    <?php endif; ?>

    <form method="post" data-validate novalidate autocomplete="off">
      <?= csrf_field() ?>
      <input type="hidden" name="booking" value="<?= $bookingId ?>">
      <label for="holder"><?= e(t('card_holder')) ?></label>
      <input type="text" id="holder" name="holder" value="<?= e(post('holder', $b['contact_name'])) ?>" required><?= field_error($errors, 'holder') ?>
      <label for="number"><?= e(t('card_number')) ?></label>
      <input type="text" id="number" name="number" inputmode="numeric" placeholder="4242 4242 4242 4242" maxlength="23" required><?= field_error($errors, 'number') ?>
      <div class="form-grid">
        <div><label for="exp"><?= e(t('expiry')) ?></label>
          <input type="text" id="exp" name="exp" placeholder="MM/YY" maxlength="5" value="<?= e(post('exp')) ?>" required><?= field_error($errors, 'exp') ?></div>
        <div><label for="cvv">CVV</label>
          <input type="password" id="cvv" name="cvv" inputmode="numeric" maxlength="4" required><?= field_error($errors, 'cvv') ?></div>
      </div>
      <button class="btn block success">🔒 <?= e(t('pay')) ?> <?= money($b['total']) ?></button>
    </form>
    <div class="row between mt">
      <a href="profile.php#bookings"><?= e(t('pay_later')) ?></a>
      <form method="post" action="cancel_booking.php" class="inline" data-confirm="<?= e(t('confirm_cancel')) ?>">
        <?= csrf_field() ?><input type="hidden" name="booking" value="<?= $bookingId ?>">
        <button class="link-btn" style="color:var(--danger)"><?= e(t('cancel_booking')) ?></button>
      </form>
    </div>
    <?php if (DEMO_MODE): ?>
      <div class="demo-box"><b><?= e(t('demo_mode')) ?>:</b> <?= e(t('test_cards')) ?><br>
        ✅ 4242 4242 4242 4242 · ❌ 4000 0000 0000 0002 · ⏳ 4000 0000 0000 0119<br><?= e(t('test_cards_exp')) ?></div>
    <?php endif; ?>
  </div>
</div>
<?php page_footer();
