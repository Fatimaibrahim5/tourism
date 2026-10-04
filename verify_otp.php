<?php
// Second login step for administrators (REQ-18 multi-factor authentication).
require __DIR__ . '/includes/functions.php';

$mfa = $_SESSION['mfa'] ?? null;
if (!$mfa || $mfa['exp'] < time()) {
    unset($_SESSION['mfa']);
    flash('error', t('code_expired'));
    redirect('login.php?role=admin');
}

$error = '';
if (is_post()) {
    check_csrf();
    if (password_verify(post('code'), $mfa['hash'])) {
        $user = q('SELECT * FROM users WHERE id = ?', [$mfa['uid']])->fetch();
        unset($_SESSION['mfa']);
        login_user($user);
        redirect('admin_dashboard.php');
    }
    $_SESSION['mfa']['tries']++;
    if ($_SESSION['mfa']['tries'] >= 5) {
        unset($_SESSION['mfa']);
        audit('mfa_failed', 'too many wrong codes', $mfa['uid']);
        flash('error', t('too_many_attempts'));
        redirect('login.php?role=admin');
    }
    $error = t('wrong_code');
}

page_header(t('verify_code'), ['bottom_nav' => false]);
?>
<div class="card login-card">
  <h1><?= e(t('verify_code')) ?></h1>
  <p class="center muted"><?= e(t('code_sent')) ?></p>
  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" data-validate novalidate>
    <?= csrf_field() ?>
    <label for="code"><?= e(t('code')) ?></label>
    <input type="text" id="code" name="code" inputmode="numeric" maxlength="6" pattern="\d{6}" required autofocus autocomplete="one-time-code">
    <button class="btn steel block"><?= e(t('verify')) ?></button>
  </form>
  <?php if (!empty($mfa['demo'])): ?>
    <div class="demo-box"><b><?= e(t('demo_mode')) ?>:</b> <?= e(t('demo_code')) ?> <b><?= e($mfa['demo']) ?></b></div>
  <?php endif; ?>
</div>
<?php page_footer();
