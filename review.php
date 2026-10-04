<?php
// REQ-9 — tourists rate and comment on trips they attended. Reviews wait in "pending" until an admin approves them.
require __DIR__ . '/includes/functions.php';

$user = require_login(['tourist']);
if (!is_post()) redirect('profile.php');
check_csrf();

$tripId = (int)($_POST['trip_id'] ?? 0);
$score = (int)($_POST['score'] ?? 0);
$comment = mb_substr(post('comment'), 0, 1000);

$trip = q('SELECT * FROM trips WHERE id = ?', [$tripId])->fetch();
$attended = $trip && trip_is_over($trip) && q("SELECT 1 FROM bookings WHERE trip_id = ? AND tourist_id = ? AND status = 'confirmed'", [$tripId, $user['id']])->fetch();

if (!$attended) {
    flash('error', t('review_not_allowed'));
} elseif ($score < 1 || $score > 5) {
    flash('error', t('choose_stars'));
} elseif (q('SELECT 1 FROM ratings WHERE trip_id = ? AND tourist_id = ?', [$tripId, $user['id']])->fetch()) {
    flash('info', t('already_reviewed'));
} else {
    q("INSERT INTO ratings (trip_id, tourist_id, score, comment, status) VALUES (?, ?, ?, ?, 'pending')", [$tripId, $user['id'], $score, $comment]);
    notify_admins('Review to moderate: ' . $trip['title'], "{$user['full_name']} rated \"{$trip['title']}\" $score/5.\n\n$comment");
    flash('success', t('review_submitted'));
}
redirect('trip.php?id=' . $tripId . '#reviews');
