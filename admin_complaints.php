<?php
// Feature 5.6 — administrators view complaints, respond, resolve or escalate (extension 4a).
require __DIR__ . '/includes/functions.php';

$admin = require_login(['admin']);
$tab = in_array($_GET['tab'] ?? '', ['open', 'escalated', 'resolved'], true) ? $_GET['tab'] : 'open';

if (is_post()) {
    check_csrf();
    $c = q('SELECT c.*, u.email, u.full_name FROM complaints c JOIN users u ON u.id = c.sender_id WHERE c.id = ?', [(int)post('id')])->fetch();
    if ($c && !can_access_user($c['email'])) deny_demo('admin_complaints.php?tab=' . $tab);
    $response = post('response');
    $action = post('action');
    if ($c && in_array($action, ['resolve', 'escalate', 'reply'], true)) {
        if ($action !== 'escalate' && $response === '') {
            flash('error', t('response_required'));
        } else {
            $status = ['resolve' => 'resolved', 'escalate' => 'escalated', 'reply' => $c['status']][$action];
            q('UPDATE complaints SET status = ?, response = COALESCE(NULLIF(?, \'\'), response), responded_by = ?, resolved_at = ? WHERE id = ?',
              [$status, $response, $admin['id'], $status === 'resolved' ? date('Y-m-d H:i:s') : null, $c['id']]);
            $body = "Hello {$c['full_name']},\n\nUpdate on your complaint #{$c['id']} \"{$c['subject']}\": " . t('st_' . $status, [], 'en') . "\n" . ($response ? "\n$response\n" : '');
            send_mail((int)$c['sender_id'], $c['email'], "Complaint #{$c['id']}: " . ucfirst($status), $body);
            audit('complaint_' . $action, "complaint #{$c['id']}");
            flash('success', t('saved'));
        }
    }
    redirect('admin_complaints.php?tab=' . $tab);
}

$counts = q('SELECT c.status, COUNT(*) n FROM complaints c JOIN users u ON u.id = c.sender_id WHERE 1=1' . demo_scope('u.email') . ' GROUP BY c.status')->fetchAll(PDO::FETCH_KEY_PAIR);
$rows = q('SELECT c.*, u.full_name, u.email, u.role, t.title, a.full_name AS admin_name FROM complaints c
           JOIN users u ON u.id = c.sender_id LEFT JOIN trips t ON t.id = c.trip_id LEFT JOIN users a ON a.id = c.responded_by
           WHERE c.status = ?' . demo_scope('u.email') . ' ORDER BY c.created_at ' . ($tab === 'resolved' ? 'DESC' : 'ASC'), [$tab])->fetchAll();

page_header(t('complaints'), ['main_class' => 'wide']);
?>
<div class="page-head"><h1><?= e(t('complaints')) ?></h1></div>
<div class="tabs">
  <?php foreach (['open', 'escalated', 'resolved'] as $s): ?>
    <a href="?tab=<?= $s ?>" class="<?= $tab === $s ? 'active' : '' ?>"><?= e(t('st_' . $s)) ?> (<?= (int)($counts[$s] ?? 0) ?>)</a>
  <?php endforeach; ?>
</div>
<?php if (!$rows): ?><div class="card center muted"><?= e(t('nothing_here')) ?></div><?php endif; ?>
<?php foreach ($rows as $c): ?>
  <div class="card">
    <div class="row between"><h3 class="mb0">#<?= (int)$c['id'] ?> · <?= e($c['subject']) ?></h3><?= status_badge($c['status']) ?></div>
    <p class="muted small"><?= e($c['full_name']) ?> (<?= e(t('role_' . $c['role'])) ?>, <?= e($c['email']) ?>) · <?= e(fdate($c['created_at'], true)) ?>
      <?php if ($c['title']): ?> · <a href="trip.php?id=<?= (int)$c['trip_id'] ?>"><?= e($c['title']) ?></a><?php endif; ?></p>
    <p class="prewrap"><?= e($c['message']) ?></p>
    <?php if ($c['response']): ?><div class="alert alert-info"><b><?= e(t('admin_response')) ?></b> (<?= e($c['admin_name']) ?>):<br><span class="prewrap"><?= e($c['response']) ?></span></div><?php endif; ?>
    <?php if ($c['status'] !== 'resolved'): ?>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
      <textarea name="response" placeholder="<?= e(t('ph_response')) ?>" style="min-height:70px"></textarea>
      <div class="row mt">
        <button class="btn sm success" name="action" value="resolve">✓ <?= e(t('respond_resolve')) ?></button>
        <button class="btn sm outline" name="action" value="reply"><?= e(t('reply_only')) ?></button>
        <?php if ($c['status'] !== 'escalated'): ?><button class="btn sm outline" name="action" value="escalate">⚠ <?= e(t('escalate')) ?></button><?php endif; ?>
      </div>
    </form>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
<?php page_footer();
