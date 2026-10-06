<?php
// Feature 5.10 — notifications and emails sent by the system (envelope icon in the bottom bar).
require __DIR__ . '/includes/functions.php';

$user = require_login();
if (is_post()) {
    check_csrf();
    if (isset($_POST['read'])) {   // one message opened (sent by JavaScript)
        q('UPDATE email_log SET is_read = 1 WHERE id = ? AND user_id = ?', [(int)$_POST['read'], $user['id']]);
        exit;
    }
    q('UPDATE email_log SET is_read = 1 WHERE user_id = ?', [$user['id']]);
    redirect('inbox.php');
}
$mails = q('SELECT * FROM email_log WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 100', [$user['id']])->fetchAll();

page_header(t('inbox'));
?>
<div class="card medium" style="padding:0;overflow:hidden">
  <div class="row between" style="padding:18px 20px 8px">
    <h1 class="mb0"><?= e(t('inbox')) ?></h1>
    <?php if (array_filter($mails, fn($m) => !$m['is_read'])): ?>
      <form method="post"><?= csrf_field() ?><button class="link-btn"><?= e(t('mark_all_read')) ?></button></form>
    <?php endif; ?>
  </div>
  <p class="muted small" style="padding:0 20px"><?= e(t('inbox_hint')) ?></p>
  <?php if (!$mails): ?><p class="muted" style="padding:0 20px 20px"><?= e(t('inbox_empty')) ?></p><?php endif; ?>
  <?php foreach ($mails as $m): ?>
    <details class="mail inbox-item <?= $m['is_read'] ? '' : 'unread' ?>" data-id="<?= (int)$m['id'] ?>">
      <summary>
        <div class="row between"><b><?= e($m['subject']) ?></b><span class="muted small"><?= e(fdate($m['created_at'], true)) ?></span></div>
      </summary>
      <div class="mail-body"><?= e($m['body']) ?></div>
    </details>
  <?php endforeach; ?>
</div>
<script>
  document.querySelectorAll('details.mail.unread').forEach(d => d.addEventListener('toggle', () => {
    if (d.open && d.classList.contains('unread')) {
      d.classList.remove('unread');
      fetch('inbox.php', { method: 'POST', body: new URLSearchParams({ csrf: <?= json_encode(csrf_token()) ?>, read: d.dataset.id }) });
    }
  }, { once: true }));
</script>
<?php page_footer();
