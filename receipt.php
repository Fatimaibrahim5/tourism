<?php
// Booking receipt (use case step 8) — printable.
require __DIR__ . '/includes/functions.php';

$user = require_login(['tourist', 'organizer', 'admin']);
$id = (int)($_GET['booking'] ?? 0);
$b = q('SELECT b.*, t.title, t.destination, t.start_date, t.end_date, t.organizer_id, u.full_name AS org_name
        FROM bookings b JOIN trips t ON t.id = b.trip_id JOIN users u ON u.id = t.organizer_id WHERE b.id = ?', [$id])->fetch();
$allowed = $b && ($b['tourist_id'] == $user['id'] || $b['organizer_id'] == $user['id'] || $user['role'] === 'admin');
if (!$allowed) { flash('error', t('not_found')); redirect(home_for_role()); }
$payments = q('SELECT * FROM payments WHERE booking_id = ? ORDER BY id', [$id])->fetchAll();

page_header(t('receipt'));
?>
<div class="card narrow receipt">
  <div class="center">
    <h2><?= e(setting('company_name', 'FsM-co')) ?></h2>
    <p class="muted small"><?= e(setting('company_email')) ?> · <?= e(setting('company_phone')) ?></p>
    <h3><?= e(t('receipt')) ?> #<?= (int)$b['id'] ?></h3>
    <?= status_badge($b['status']) ?>
  </div>
  <div class="mt">
    <div class="line"><span><?= e(t('trip')) ?></span><b><?= e($b['title']) ?></b></div>
    <div class="line"><span><?= e(t('destination')) ?></span><span><?= e($b['destination']) ?></span></div>
    <div class="line"><span><?= e(t('schedule')) ?></span><span><?= e(fdate($b['start_date'])) ?> → <?= e(fdate($b['end_date'])) ?></span></div>
    <div class="line"><span><?= e(t('your_guide')) ?></span><span><?= e($b['org_name']) ?></span></div>
    <div class="line"><span><?= e(t('full_name')) ?></span><span><?= e($b['contact_name']) ?></span></div>
    <div class="line"><span><?= e(t('phone_number')) ?></span><span><?= e($b['phone']) ?></span></div>
    <div class="line"><span><?= e(t('language')) ?></span><span><?= e($b['language']) ?></span></div>
    <div class="line"><span><?= e(t('seats')) ?></span><span><?= (int)$b['seats'] ?> × <?= money($b['unit_price']) ?></span></div>
    <div class="line"><span><b><?= e(t('total')) ?></b></span><b><?= money($b['total']) ?></b></div>
    <div class="line"><span><?= e(t('booked_on')) ?></span><span><?= e(fdate($b['created_at'], true)) ?></span></div>
  </div>
  <h3 class="mt"><?= e(t('payments')) ?></h3>
  <?php foreach ($payments as $p): ?>
    <div class="line">
      <span><?= $p['method'] === 'card' ? '💳 ' . e(t('pay_card')) . ($p['card_last4'] ? ' •••• ' . e($p['card_last4']) : '') : '💵 ' . e(t('pay_cash')) ?>
        <?php if ($p['bank_ref']): ?><br><small class="muted"><?= e($p['bank_ref']) ?></small><?php endif; ?></span>
      <span><?= money($p['amount']) ?> <?= status_badge($p['status']) ?></span>
    </div>
  <?php endforeach; ?>
  <div class="row between mt no-print">
    <button class="btn outline sm" onclick="window.print()">🖨 <?= e(t('print')) ?></button>
    <?php if ($user['role'] === 'tourist'): ?>
      <?php if ($b['status'] === 'pending' && $payments && end($payments)['method'] === 'card'): ?>
        <a class="btn sm" href="bank.php?booking=<?= (int)$b['id'] ?>"><?= e(t('pay_now')) ?></a>
      <?php endif; ?>
      <a class="btn sm" href="profile.php#bookings"><?= e(t('my_bookings')) ?></a>
    <?php endif; ?>
  </div>
</div>
<?php page_footer();
