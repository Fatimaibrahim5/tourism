<?php
// REQ-8 / Feature 5.9 — company information and contact, available without login.
require __DIR__ . '/includes/functions.php';

$errors = [];
$sent = false;
if (is_post()) {
    check_csrf();
    $name = post('name');
    $email = post('email');
    $message = post('message');
    if ($name === '') $errors['name'] = t('required');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = t('invalid_email');
    if (mb_strlen($message) < 5) $errors['message'] = t('required');
    if (!$errors) {
        q('INSERT INTO contact_messages (name, email, message) VALUES (?, ?, ?)', [$name, $email, $message]);
        notify_admins('New contact message from ' . $name, "From: $name <$email>\n\n$message", $email);
        flash('success', t('message_sent'));
        redirect('about.php#contact');
    }
}

$u = current_user();
page_header(t('about_company'));
?>
<div class="card medium">
  <div class="row"><span class="info-btn">i</span><h1 class="mb0"><?= e(setting('company_name', 'FsM-co')) ?></h1></div>
  <p class="prewrap mt"><?= e(setting('company_about')) ?></p>
  <div class="facts">
    <div class="fact"><small><?= e(t('email_address')) ?></small><b><a href="mailto:<?= e(setting('company_email')) ?>"><?= e(setting('company_email')) ?></a></b></div>
    <div class="fact"><small><?= e(t('phone_number')) ?></small><b><?= e(setting('company_phone')) ?></b></div>
    <div class="fact"><small><?= e(t('address')) ?></small><b><?= e(setting('company_address')) ?></b></div>
  </div>
</div>

<div class="card medium" id="contact">
  <h2><?= e(t('contact_us')) ?></h2>
  <form method="post" data-validate novalidate>
    <?= csrf_field() ?>
    <div class="form-grid">
      <div><label for="name"><?= e(t('full_name')) ?></label>
        <input type="text" id="name" name="name" value="<?= e(post('name', $u['full_name'] ?? '')) ?>" required><?= field_error($errors, 'name') ?></div>
      <div><label for="email"><?= e(t('email_address')) ?></label>
        <input type="email" id="email" name="email" value="<?= e(post('email', $u['email'] ?? '')) ?>" required><?= field_error($errors, 'email') ?></div>
    </div>
    <label for="message"><?= e(t('message')) ?></label>
    <textarea id="message" name="message" required><?= e(post('message')) ?></textarea><?= field_error($errors, 'message') ?>
    <button class="btn block"><?= e(t('send')) ?></button>
  </form>
</div>
<?php page_footer();
