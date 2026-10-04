<?php
// REQ-13 — tourists cancel bookings according to the cancellation policy. Card payments are refunded.
require __DIR__ . '/includes/functions.php';

$user = require_login(['tourist']);
if (!is_post()) redirect('profile.php');
check_csrf();

$id = (int)($_POST['booking'] ?? 0);
$b = q('SELECT b.*, t.title, t.start_date, t.organizer_id FROM bookings b JOIN trips t ON t.id = b.trip_id
        WHERE b.id = ? AND b.tourist_id = ?', [$id, $user['id']])->fetch();

if (!$b || $b['status'] === 'cancelled') {
    flash('error', t('not_found'));
    redirect('profile.php#bookings');
}
$daysBefore = (strtotime($b['start_date']) - strtotime(date('Y-m-d'))) / 86400;
if ($b['status'] === 'confirmed' && $daysBefore < CANCEL_MIN_DAYS) {
    flash('error', t('cancel_too_late', ['n' => CANCEL_MIN_DAYS]));
    redirect('profile.php#bookings');
}

$pdo = db();
$pdo->beginTransaction();
q("UPDATE bookings SET status = 'cancelled', cancelled_at = NOW() WHERE id = ?", [$id]);
$refunded = q("UPDATE payments SET status = 'refunded', message = 'Refunded after cancellation' WHERE booking_id = ? AND status = 'paid'", [$id])->rowCount();
q("UPDATE payments SET status = 'failed', message = 'Cancelled before payment' WHERE booking_id = ? AND status = 'pending'", [$id]);
$pdo->commit();

audit('booking_cancelled', "booking #$id" . ($refunded ? ' (refunded)' : ''));
send_mail($user['id'], $user['email'], 'Booking cancelled: ' . $b['title'],
    "Your booking #$id for \"{$b['title']}\" has been cancelled." . ($refunded ? "\nA refund of " . money($b['total']) . " has been issued to your card." : ''));
notify_user((int)$b['organizer_id'], 'Booking cancelled: ' . $b['title'], "{$b['contact_name']} cancelled booking #$id ({$b['seats']} seat(s)).");
flash('success', $refunded ? t('cancelled_refunded') : t('cancelled'));
redirect('profile.php#bookings');
