<?php
require __DIR__ . '/includes/functions.php';

$token = $_GET['token'] ?? '';
$row = $token ? q('SELECT pr.*, u.role, u.email FROM password_resets pr JOIN users u ON u.id = pr.user_id
                   WHERE pr.token_hash = ? AND pr.used = 0 AND pr.expires_at > NOW()', [hash('sha256', $token)])->fetch() : null;
if ($row && is_demo_email($row['email'])) $row = null;   // demo accounts' passwords can't be changed
$errors = [];

if ($row && is_post()) {
    check_csrf();
    $pw = $_POST['password'] ?? '';
    if (strlen($pw) < 8 || !preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) $errors['password'] = t('pw_rules');
    if ($pw !== ($_POST['password2'] ?? '')) $errors['password2'] = t('pw_mismatch');
    if (!$errors) {
        q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $row['user_id']]);
        q('UPDATE password_resets SET used = 1 WHERE user_id = ?', [$row['user_id']]);
        audit('password_reset', '', $row['user_id']);
        flash('success', t('pw_changed'));
        redirect('login.php?role=' . $row['role']);
    }
}

page_header(t('new_password'), ['bottom_nav' => false]);
?>
<div class="card login-card">
  <h1><?= e(t('new_password')) ?></h1>
  <?php if (!$row): ?>
    <div class="alert alert-error"><?= e(t('reset_invalid')) ?></div>
    <a class="btn block" href="forgot.php"><?= e(t('send_reset')) ?></a>
  <?php else: ?>
  <form method="post" data-validate novalidate>
    <?= csrf_field() ?>
    <label for="password"><?= e(t('password')) ?></label>
    <input type="password" id="password" name="password" required autocomplete="new-password">
    <?= field_error($errors, 'password') ?>
    <div class="muted small"><?= e(t('pw_rules')) ?></div>
    <label for="password2"><?= e(t('confirm_password')) ?></label>
    <input type="password" id="password2" name="password2" required autocomplete="new-password">
    <?= field_error($errors, 'password2') ?>
    <button class="btn steel block"><?= e(t('save')) ?></button>
  </form>
  <?php endif; ?>
</div>
<?php page_footer();
