<?php
// System: maintenance mode (use case 3, steps 7–8), company information, contact messages,
// audit log of administrative actions (SREQ-17) and the outgoing email log.
require __DIR__ . '/includes/functions.php';

$admin = require_login(['admin']);

if (is_post()) {
    check_csrf();
    switch (post('action')) {
        case 'maintenance':
            $on = post('on') === '1';
            set_setting('maintenance', $on ? '1' : '0');
            set_setting('maintenance_msg', post('maintenance_msg'));
            audit($on ? 'maintenance_started' : 'maintenance_ended');
            flash('success', $on ? t('maintenance_on') : t('maintenance_off'));
            break;
        case 'company':
            foreach (['company_name', 'company_about', 'company_email', 'company_phone', 'company_address'] as $k) set_setting($k, post($k));
            audit('company_info_updated');
            flash('success', t('saved'));
            break;
        case 'read_messages':
            q('UPDATE contact_messages SET is_read = 1');
            break;
    }
    redirect('admin_settings.php');
}

$messages = q('SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 50')->fetchAll();
$audit = q('SELECT a.*, u.full_name, u.role FROM audit_log a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.id DESC LIMIT 100')->fetchAll();
$emails = q('SELECT * FROM email_log ORDER BY id DESC LIMIT 40')->fetchAll();
$on = setting('maintenance') === '1';

page_header(t('system'), ['main_class' => 'wide']);
?>
<div class="page-head"><h1><?= e(t('system')) ?></h1></div>
<div class="grid grid-2">
  <div class="card">
    <h2>🛠 <?= e(t('maintenance')) ?></h2>
    <p><?= e(t('status')) ?>: <?= $on ? '<span class="badge b-pending">' . e(t('maintenance_on')) . '</span>' : '<span class="badge b-active">' . e(t('online')) . '</span>' ?></p>
    <p class="muted small"><?= e(t('maintenance_help')) ?></p>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="maintenance">
      <label for="mm"><?= e(t('notice_message')) ?></label>
      <input type="text" id="mm" name="maintenance_msg" value="<?= e(setting('maintenance_msg')) ?>" placeholder="<?= e(t('maintenance_msg')) ?>">
      <?php if ($on): ?>
        <button class="btn success mt" name="on" value="0"><?= e(t('end_maintenance')) ?></button>
      <?php else: ?>
        <button class="btn danger mt" name="on" value="1" data-confirm="<?= e(t('confirm_action')) ?>"><?= e(t('start_maintenance')) ?></button>
      <?php endif; ?>
    </form>
  </div>

  <div class="card">
    <h2>ℹ️ <?= e(t('company_info')) ?></h2>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="company">
      <div class="form-grid">
        <div><label><?= e(t('name')) ?></label><input type="text" name="company_name" value="<?= e(setting('company_name')) ?>"></div>
        <div><label><?= e(t('email_address')) ?></label><input type="email" name="company_email" value="<?= e(setting('company_email')) ?>"></div>
        <div><label><?= e(t('phone_number')) ?></label><input type="text" name="company_phone" value="<?= e(setting('company_phone')) ?>"></div>
        <div><label><?= e(t('address')) ?></label><input type="text" name="company_address" value="<?= e(setting('company_address')) ?>"></div>
        <div class="full"><label><?= e(t('about_company')) ?></label><textarea name="company_about"><?= e(setting('company_about')) ?></textarea></div>
      </div>
      <button class="btn mt"><?= e(t('save')) ?></button>
    </form>
  </div>
</div>

<div class="card" id="messages">
  <div class="row between"><h2 class="mb0">✉️ <?= e(t('contact_messages')) ?></h2>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="read_messages"><button class="link-btn"><?= e(t('mark_all_read')) ?></button></form></div>
  <?php if (!$messages): ?><p class="muted"><?= e(t('nothing_here')) ?></p><?php endif; ?>
  <?php foreach ($messages as $m): ?>
    <div class="review"><div class="row between"><b><?= $m['is_read'] ? '' : '● ' ?><?= e($m['name']) ?> &lt;<a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a>&gt;</b><span class="muted small"><?= e(fdate($m['created_at'], true)) ?></span></div>
      <p class="mb0 prewrap"><?= e($m['message']) ?></p></div>
  <?php endforeach; ?>
</div>

<div class="card" id="audit">
  <h2>🧾 <?= e(t('audit_log')) ?></h2>
  <div class="table-wrap" style="max-height:420px;overflow:auto">
    <table>
      <thead><tr><th><?= e(t('date')) ?></th><th><?= e(t('user')) ?></th><th><?= e(t('action')) ?></th><th><?= e(t('details')) ?></th><th>IP</th></tr></thead>
      <tbody><?php foreach ($audit as $a): ?>
        <tr><td class="small"><?= e(fdate($a['created_at'], true)) ?></td><td class="small"><?= e($a['full_name'] ?? '—') ?><?= $a['role'] ? ' (' . e($a['role']) . ')' : '' ?></td><td><code><?= e($a['action']) ?></code></td><td class="small"><?= e($a['details']) ?></td><td class="small"><?= e($a['ip']) ?></td></tr>
      <?php endforeach; ?></tbody>
    </table>
  </div>
</div>

<div class="card">
  <h2>📤 <?= e(t('email_log')) ?></h2>
  <p class="muted small"><?= e(t('email_log_help')) ?></p>
  <?php foreach ($emails as $m): ?>
    <details class="mail inbox-item"><summary><div class="row between"><span><b><?= e($m['subject']) ?></b> → <?= e($m['to_email']) ?></span><span class="muted small"><?= e(fdate($m['created_at'], true)) ?></span></div></summary>
      <div class="mail-body"><?= e($m['body']) ?></div></details>
  <?php endforeach; ?>
</div>
<?php page_footer();
