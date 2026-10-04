<?php
// Administrator dashboard (use case 3, step 1).
require __DIR__ . '/includes/functions.php';

require_login(['admin']);
expire_stale_bookings();

$c = fn(string $sql) => (int)q($sql)->fetchColumn();
$stats = [
    ['admin_guides.php', 'pending_guides', $c("SELECT COUNT(*) FROM users WHERE role = 'organizer' AND status = 'pending'"), true],
    ['admin_reviews.php', 'pending_reviews', $c("SELECT COUNT(*) FROM ratings WHERE status = 'pending'"), true],
    ['admin_complaints.php', 'open_complaints', $c("SELECT COUNT(*) FROM complaints WHERE status IN ('open','escalated')"), true],
    ['admin_settings.php#messages', 'unread_messages', $c('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0'), true],
    ['admin_users.php?role=tourist', 'tourists', $c("SELECT COUNT(*) FROM users WHERE role = 'tourist'"), false],
    ['admin_users.php?role=organizer', 'organizers', $c("SELECT COUNT(*) FROM users WHERE role = 'organizer' AND status = 'active'"), false],
    ['admin_trips.php', 'active_trips', $c("SELECT COUNT(*) FROM trips WHERE status = 'published' AND end_date >= CURDATE()"), false],
    ['admin_reports.php', 'bookings_30d', $c("SELECT COUNT(*) FROM bookings WHERE status = 'confirmed' AND created_at >= NOW() - INTERVAL 30 DAY"), false],
];
$revenue = (float)q("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'paid'")->fetchColumn();
$recent = q('SELECT a.*, u.full_name FROM audit_log a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.id DESC LIMIT 12')->fetchAll();

page_header(t('dashboard'), ['main_class' => 'wide']);
?>
<div class="page-head"><h1><?= e(t('admin_dashboard')) ?></h1>
  <?php if (setting('maintenance') === '1'): ?><a class="badge b-pending" href="admin_settings.php">🛠 <?= e(t('maintenance_on')) ?></a><?php endif; ?></div>

<div class="grid grid-4">
  <?php foreach ($stats as [$href, $label, $num, $alert]): ?>
    <a class="stat <?= $alert && $num ? 'alerting' : '' ?>" href="<?= e($href) ?>"><small><?= e(t($label)) ?></small><div class="num"><?= $num ?></div></a>
  <?php endforeach; ?>
  <a class="stat" href="admin_reports.php"><small><?= e(t('revenue_paid')) ?></small><div class="num"><?= money($revenue) ?></div></a>
</div>

<div class="card mt">
  <div class="row between"><h2 class="mb0"><?= e(t('recent_activity')) ?></h2><a href="admin_settings.php#audit"><?= e(t('view_all')) ?></a></div>
  <div class="table-wrap mt">
    <table>
      <thead><tr><th><?= e(t('date')) ?></th><th><?= e(t('user')) ?></th><th><?= e(t('action')) ?></th><th><?= e(t('details')) ?></th></tr></thead>
      <tbody>
      <?php foreach ($recent as $r): ?>
        <tr><td class="small"><?= e(fdate($r['created_at'], true)) ?></td><td><?= e($r['full_name'] ?? '—') ?></td><td><code><?= e($r['action']) ?></code></td><td class="small"><?= e($r['details']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php page_footer();
