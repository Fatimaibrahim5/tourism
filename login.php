<?php
// Fig 2 — login with two options (Tourist / Travel Organizer, REQ-3) plus the administrator login.
require __DIR__ . '/includes/functions.php';

$role = in_array($_GET['role'] ?? '', ['tourist', 'organizer', 'admin'], true) ? $_GET['role'] : 'tourist';
if (current_user()) redirect(home_for_role());

$error = '';
$email = '';

if (is_post()) {
    check_csrf();
    $email = mb_strtolower(post('email'));
    $password = $_POST['password'] ?? '';

    // Brute-force protection: max 10 failures per IP and 5 per account in 15 minutes
    if (recent_actions('login_failed', 15) >= 10 || recent_actions('login_failed', 15, " $email") >= 5) {
        $error = t('too_many_attempts');
    } else {
        $user = q('SELECT * FROM users WHERE email = ? AND role = ?', [$email, $role])->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            audit('login_failed', "$role $email");
            $error = t('invalid_login');
        } elseif ($user['status'] === 'suspended') {
            $error = t('account_suspended');
        } elseif ($user['status'] === 'rejected') {
            $error = t('account_rejected');
        } else {
            if ($user['role'] === 'admin') {
                // REQ-18: multi-factor authentication for administrators (one-time code by email).
                // Demo admin: the code is shown on screen. Real admins: the code is sent by real email.
                $isDemo = is_demo_email($user['email']);
                if (!$isDemo && !mail_configured()) {
                    // Email sending is not set up yet (SMTP in includes/config.php), so the code
                    // could not be delivered: allow password-only login and record it.
                    audit('mfa_skipped', 'SMTP not configured', $user['id']);
                    login_user($user);
                    redirect('admin_dashboard.php');
                }
                $code = (string)random_int(100000, 999999);
                $_SESSION['mfa'] = ['uid' => $user['id'], 'hash' => password_hash($code, PASSWORD_DEFAULT), 'exp' => time() + 600, 'tries' => 0];
                if (DEMO_MODE && $isDemo) $_SESSION['mfa']['demo'] = $code;
                $sent = send_mail($user['id'], $user['email'], 'Your administrator login code', "Your one-time login code is: $code\nIt expires in 10 minutes.");
                if ($isDemo || $sent) redirect('verify_otp.php');
                unset($_SESSION['mfa']);
                $error = t('code_not_sent');
            } else {
                login_user($user);
                $next = $_SESSION['after_login'] ?? home_for_role();
                unset($_SESSION['after_login']);
                redirect($next);
            }
        }
    }
}

page_header(t('login'), ['bottom_nav' => false]);
?>
<div class="card login-card">
  <h1><?= e(t('login')) ?></h1>
  <?php if ($role !== 'admin'): ?>
  <div class="role-tabs">
    <a href="?role=tourist" class="<?= $role === 'tourist' ? 'active' : '' ?>"><?= e(t('role_tourist')) ?></a>
    <a href="?role=organizer" class="<?= $role === 'organizer' ? 'active' : '' ?>"><?= e(t('role_organizer')) ?></a>
  </div>
  <?php else: ?>
  <p class="center muted"><?= e(t('admin_login')) ?></p>
  <?php endif; ?>

  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

  <form method="post" data-validate novalidate>
    <?= csrf_field() ?>
    <label for="email"><?= e(t('username_email')) ?></label>
    <input type="email" id="email" name="email" value="<?= e($email) ?>" required autocomplete="username">
    <label for="password"><?= e(t('password')) ?></label>
    <input type="password" id="password" name="password" required autocomplete="current-password">
    <button class="btn steel block" type="submit"><?= e(t('login')) ?></button>
  </form>

  <div class="form-links">
    <?php if ($role !== 'admin'): ?>
      <a class="btn outline" href="register.php?role=<?= e($role) ?>"><?= e(t('create_account')) ?></a>
    <?php else: ?><span></span><?php endif; ?>
    <a href="forgot.php" style="color:#111"><?= e(t('forgot_password')) ?></a>
  </div>
</div>
<?php page_footer();
