<?php
// Password recovery: emails a one-time reset link (valid 1 hour).
require __DIR__ . '/includes/functions.php';

if (is_post()) {
    check_csrf();
    $email = mb_strtolower(post('email'));
    if (recent_actions('reset_requested', 60) >= 5) {
        flash('error', t('too_many_requests'));
        redirect('forgot.php');
    }
    audit('reset_requested', $email);
    if (is_demo_email($email)) {
        flash('error', t('demo_no_reset'));
        redirect('forgot.php');
    }
    $user = q("SELECT * FROM users WHERE email = ? AND status <> 'rejected'", [$email])->fetch();
    if ($user && mail_configured()) {
        $token = bin2hex(random_bytes(32));
        q('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, NOW() + INTERVAL 1 HOUR)', [$user['id'], hash('sha256', $token)]);
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $link = $scheme . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/reset.php?token=' . $token;
        $link = str_replace('\\', '/', $link);
        send_mail($user['id'], $user['email'], 'Reset your password', "Hello {$user['full_name']},\n\nUse this link to choose a new password (valid for 1 hour):\n$link\n\nIf you did not request this, ignore this email.");
    }
    // Same message whether or not the email exists (no account enumeration).
    // The link is only ever sent to the mailbox, never shown on screen.
    flash('info', mail_configured() ? t('reset_sent') : t('reset_unavailable'));
    redirect('forgot.php');
}

page_header(t('forgot_password'), ['bottom_nav' => false]);
?>
<div class="card login-card">
  <h1><?= e(t('forgot_password')) ?></h1>
  <p class="muted center"><?= e(t('forgot_help')) ?></p>
  <form method="post" data-validate novalidate>
    <?= csrf_field() ?>
    <label for="email"><?= e(t('email_address')) ?></label>
    <input type="email" id="email" name="email" required>
    <button class="btn steel block"><?= e(t('send_reset')) ?></button>
  </form>
  <p class="center mt"><a href="login.php"><?= e(t('back_to_login')) ?></a></p>
</div>
<?php page_footer();
