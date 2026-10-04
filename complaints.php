<?php
// Feature 5.6 — tourists (and organizers) submit complaints and follow the administrator's response.
require __DIR__ . '/includes/functions.php';

$user = require_login(['tourist', 'organizer']);
$errors = [];

if (is_post()) {
    check_csrf();
    $subject = post('subject');
    $message = post('message');
    $tripId = (int)post('trip_id') ?: null;
    if (mb_strlen($subject) < 3) $errors['subject'] = t('required');
    if (mb_strlen($message) < 10) $errors['message'] = t('err_message_short');
    if (!$errors) {
        q('INSERT INTO complaints (sender_id, trip_id, subject, message) VALUES (?, ?, ?, ?)', [$user['id'], $tripId, $subject, $message]);
        $cid = db()->lastInsertId();
        notify_admins("New complaint #$cid: $subject", "From: {$user['full_name']} <{$user['email']}>\n\n$message");
        send_mail($user['id'], $user['email'], "Complaint #$cid received", "We received your complaint \"$subject\". An administrator will review it and reply soon.");
        flash('success', t('complaint_sent'));
        redirect('complaints.php');
    }
}

$myTrips = $user['role'] === 'tourist'
    ? q("SELECT DISTINCT t.id, t.title FROM trips t JOIN bookings b ON b.trip_id = t.id WHERE b.tourist_id = ? ORDER BY t.title", [$user['id']])->fetchAll()
    : q('SELECT id, title FROM trips WHERE organizer_id = ? ORDER BY title', [$user['id']])->fetchAll();
$list = q('SELECT c.*, t.title FROM complaints c LEFT JOIN trips t ON t.id = c.trip_id WHERE c.sender_id = ? ORDER BY c.created_at DESC', [$user['id']])->fetchAll();

page_header(t('complaints'));
?>
<div class="card medium">
  <h1><?= e(t('submit_complaint')) ?></h1>
  <form method="post" data-validate novalidate>
    <?= csrf_field() ?>
    <label for="trip_id"><?= e(t('related_trip')) ?></label>
    <select id="trip_id" name="trip_id">
      <option value=""><?= e(t('none')) ?></option>
      <?php foreach ($myTrips as $t): ?><option value="<?= (int)$t['id'] ?>" <?= post('trip_id') == $t['id'] ? 'selected' : '' ?>><?= e($t['title']) ?></option><?php endforeach; ?>
    </select>
    <label for="subject"><?= e(t('subject')) ?></label>
    <input type="text" id="subject" name="subject" maxlength="160" value="<?= e(post('subject')) ?>" required><?= field_error($errors, 'subject') ?>
    <label for="message"><?= e(t('message')) ?></label>
    <textarea id="message" name="message" required><?= e(post('message')) ?></textarea><?= field_error($errors, 'message') ?>
    <button class="btn block"><?= e(t('send')) ?></button>
  </form>
</div>

<div class="card medium">
  <h2><?= e(t('my_complaints')) ?></h2>
  <?php if (!$list): ?><p class="muted"><?= e(t('no_complaints')) ?></p><?php endif; ?>
  <?php foreach ($list as $c): ?>
    <div class="review">
      <div class="row between"><b>#<?= (int)$c['id'] ?> · <?= e($c['subject']) ?></b><?= status_badge($c['status']) ?></div>
      <div class="muted small"><?= e(fdate($c['created_at'], true)) ?><?= $c['title'] ? ' · ' . e($c['title']) : '' ?></div>
      <p class="prewrap"><?= e($c['message']) ?></p>
      <?php if ($c['response']): ?>
        <div class="alert alert-info mb0"><b><?= e(t('admin_response')) ?>:</b><br><span class="prewrap"><?= e($c['response']) ?></span></div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
<?php page_footer();
