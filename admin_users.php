<?php
// Manage user accounts: suspend, reactivate or remove (use case 3, step 5); add administrators.
require __DIR__ . '/includes/functions.php';

$admin = require_login(['admin']);

if (is_post()) {
    check_csrf();
    $action = post('action');
    if ($action === 'add_admin') {
        if (is_demo_viewer()) deny_demo('admin_users.php');
        $name = post('full_name');
        $email = mb_strtolower(post('email'));
        $pw = $_POST['password'] ?? '';
        if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pw) < 8) {
            flash('error', t('fix_errors'));
        } elseif (q('SELECT 1 FROM users WHERE email = ?', [$email])->fetch()) {
            flash('error', t('email_taken'));
        } else {
            q("INSERT INTO users (role, full_name, email, password_hash, status) VALUES ('admin', ?, ?, ?, 'active')", [$name, $email, password_hash($pw, PASSWORD_DEFAULT)]);
            audit('admin_created', $email);
            flash('success', t('admin_created'));
        }
        redirect('admin_users.php?role=admin');
    }

    if (is_demo_viewer()) deny_demo('admin_users.php');   // the demo accounts must stay usable for everyone
    $u = q('SELECT * FROM users WHERE id = ?', [(int)post('user')])->fetch();
    if ($u && !can_access_user($u['email'])) deny_demo('admin_users.php');
    if (!$u || (int)$u['id'] === (int)$admin['id']) {
        flash('error', t('cannot_self'));
    } elseif ($action === 'suspend') {
        q("UPDATE users SET status = 'suspended' WHERE id = ?", [$u['id']]);
        send_mail($u['id'], $u['email'], 'Your account has been suspended', "Your account was suspended by an administrator." . (post('reason') ? "\nReason: " . post('reason') : '') . "\nContact us at " . setting('company_email') . ' for more information.');
        audit('user_suspended', "user #{$u['id']} {$u['email']} " . post('reason'));
        flash('success', t('user_suspended'));
    } elseif ($action === 'reactivate') {
        q("UPDATE users SET status = 'active' WHERE id = ?", [$u['id']]);
        send_mail($u['id'], $u['email'], 'Your account is active again', 'Your account has been reactivated by an administrator.');
        audit('user_reactivated', "user #{$u['id']} {$u['email']}");
        flash('success', t('user_reactivated'));
    } elseif ($action === 'delete') {
        q('DELETE FROM users WHERE id = ?', [$u['id']]);
        audit('user_deleted', "user #{$u['id']} {$u['email']} ({$u['role']})");
        flash('success', t('user_deleted'));
    }
    redirect('admin_users.php?' . http_build_query(['role' => $_GET['role'] ?? '', 'q' => $_GET['q'] ?? '']));
}

$roleF = in_array($_GET['role'] ?? '', ['tourist', 'organizer', 'admin'], true) ? $_GET['role'] : '';
$search = trim($_GET['q'] ?? '');
$where = ['1=1' . demo_scope('u.email')];
$params = [];
if ($roleF) { $where[] = 'role = ?'; $params[] = $roleF; }
if ($search !== '') { $where[] = '(full_name LIKE ? OR email LIKE ? OR phone LIKE ?)'; array_push($params, "%$search%", "%$search%", "%$search%"); }
$users = q('SELECT u.*, (SELECT COUNT(*) FROM bookings b WHERE b.tourist_id = u.id) AS n_bookings,
                   (SELECT COUNT(*) FROM trips t WHERE t.organizer_id = u.id) AS n_trips
            FROM users u WHERE ' . implode(' AND ', $where) . ' ORDER BY u.created_at DESC LIMIT 300', $params)->fetchAll();

page_header(t('users'), ['main_class' => 'wide']);
?>
<div class="page-head"><h1><?= e(t('users')) ?></h1><button class="btn sm outline" data-modal="addAdmin">＋ <?= e(t('add_admin')) ?></button></div>
<div class="tabs">
  <a href="?" class="<?= !$roleF ? 'active' : '' ?>"><?= e(t('all')) ?></a>
  <?php foreach (['tourist', 'organizer', 'admin'] as $r): ?><a href="?role=<?= $r ?>" class="<?= $roleF === $r ? 'active' : '' ?>"><?= e(t('role_' . $r)) ?></a><?php endforeach; ?>
</div>
<form class="row mb" method="get">
  <input type="hidden" name="role" value="<?= e($roleF) ?>">
  <input type="search" name="q" value="<?= e($search) ?>" placeholder="<?= e(t('ph_user_search')) ?>" style="max-width:340px">
  <button class="btn sm"><?= e(t('search')) ?></button>
</form>
<div class="table-wrap mt">
<table>
  <thead><tr><th><?= e(t('name')) ?></th><th><?= e(t('role')) ?></th><th><?= e(t('phone_number')) ?></th><th><?= e(t('status')) ?></th><th><?= e(t('activity')) ?></th><th><?= e(t('registered')) ?></th><th><?= e(t('actions')) ?></th></tr></thead>
  <tbody>
  <?php foreach ($users as $u): ?>
    <tr>
      <td><b><?= e($u['full_name']) ?></b><br><span class="muted small"><?= e($u['email']) ?></span></td>
      <td><?= e(t('role_' . $u['role'])) ?></td>
      <td class="small"><?= e($u['phone']) ?></td>
      <td><?= status_badge($u['status']) ?></td>
      <td class="small"><?= $u['role'] === 'tourist' ? (int)$u['n_bookings'] . ' ' . e(t('bookings')) : ($u['role'] === 'organizer' ? (int)$u['n_trips'] . ' ' . e(t('trips')) : '') ?></td>
      <td class="small"><?= e(fdate($u['created_at'])) ?></td>
      <td>
        <?php if ((int)$u['id'] !== (int)$admin['id']): ?>
        <form method="post" class="row" data-confirm="<?= e(t('confirm_action')) ?>">
          <?= csrf_field() ?><input type="hidden" name="user" value="<?= (int)$u['id'] ?>">
          <?php if ($u['status'] === 'suspended'): ?>
            <button class="btn sm success" name="action" value="reactivate"><?= e(t('reactivate')) ?></button>
          <?php elseif ($u['status'] === 'active'): ?>
            <input type="text" name="reason" placeholder="<?= e(t('reason')) ?>" style="width:120px;padding:5px 8px">
            <button class="btn sm outline" name="action" value="suspend"><?= e(t('suspend')) ?></button>
          <?php endif; ?>
          <button class="btn sm danger" name="action" value="delete"><?= e(t('delete')) ?></button>
        </form>
        <?php else: ?><span class="muted small"><?= e(t('you')) ?></span><?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<div class="modal" id="addAdmin" role="dialog" aria-modal="true">
  <div class="modal-box">
    <button class="modal-close" aria-label="<?= e(t('close')) ?>">×</button>
    <h2><?= e(t('add_admin')) ?></h2>
    <form method="post" data-validate novalidate>
      <?= csrf_field() ?><input type="hidden" name="action" value="add_admin">
      <label><?= e(t('full_name')) ?></label><input type="text" name="full_name" required>
      <label><?= e(t('email_address')) ?></label><input type="email" name="email" required>
      <label><?= e(t('password')) ?> (≥ 8)</label><input type="password" name="password" minlength="8" required autocomplete="new-password">
      <button class="btn block"><?= e(t('create_account')) ?></button>
    </form>
  </div>
</div>
<?php page_footer();
