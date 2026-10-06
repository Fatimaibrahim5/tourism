<?php
// Fig 3 / Fig 4 — Tourist and Travel Organizer registration (REQ-4: organizer data goes to the admin for validation).
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/data.php';

$role = ($_GET['role'] ?? '') === 'organizer' ? 'organizer' : 'tourist';
if (current_user()) redirect(home_for_role());

$v = [
    'full_name' => post('full_name'), 'email' => mb_strtolower(post('email')), 'phone' => post('phone'),
    'dob' => post('dob'), 'nationality' => post('nationality', 'Lebanon'), 'language' => post('language', 'English'),
    'university' => post('university'), 'training' => post('training'), 'skills' => post('skills'),
];
$errors = [];
$duplicate = false;

if (is_post()) {
    check_csrf();
    $pw = $_POST['password'] ?? '';
    $pw2 = $_POST['password2'] ?? '';

    if (mb_strlen($v['full_name']) < 2 || mb_strlen($v['full_name']) > 120) $errors['full_name'] = t('err_name');
    if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = t('invalid_email');
    if (!preg_match('/^[+0-9 ()-]{6,20}$/', $v['phone'])) $errors['phone'] = t('invalid_phone');
    $dob = DateTime::createFromFormat('Y-m-d', $v['dob']);
    $minAge = $role === 'organizer' ? 18 : 12;
    if (!$dob || $dob->format('Y-m-d') !== $v['dob']) $errors['dob'] = t('err_date');
    elseif ($dob->diff(new DateTime())->y < $minAge || $dob > new DateTime()) $errors['dob'] = t('err_age', ['n' => $minAge]);
    if (!in_array($v['nationality'], NATIONALITIES, true)) $errors['nationality'] = t('required');
    if (!in_array($v['language'], LANGUAGES, true)) $errors['language'] = t('required');
    if (strlen($pw) < 8 || !preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) $errors['password'] = t('pw_rules');
    if ($pw !== $pw2) $errors['password2'] = t('pw_mismatch');
    if ($role === 'organizer') {
        foreach (['university', 'training', 'skills'] as $f) if ($v[$f] === '') $errors[$f] = t('required');
    }
    if (!isset($errors['email']) && is_demo_email($v['email'])) $errors['email'] = t('demo_domain_reserved');
    if (!$errors && q('SELECT 1 FROM users WHERE email = ?', [$v['email']])->fetch()) {
        // Extension 2a: duplicate email → prompt login or password recovery
        $errors['email'] = t('email_taken');
        $duplicate = true;
    }

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        q('INSERT INTO users (role, full_name, email, phone, dob, nationality, language, password_hash, status, ui_lang)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
            $role, $v['full_name'], $v['email'], $v['phone'], $v['dob'], $v['nationality'], $v['language'],
            password_hash($pw, PASSWORD_DEFAULT), $role === 'organizer' ? 'pending' : 'active', lang(),
        ]);
        $id = (int)$pdo->lastInsertId();
        if ($role === 'organizer') {
            q('INSERT INTO organizer_profiles (user_id, university, training, skills, language_skills) VALUES (?, ?, ?, ?, ?)',
              [$id, $v['university'], $v['training'], $v['skills'], $v['language']]);
        }
        $pdo->commit();

        if ($role === 'organizer') {
            send_mail($id, $v['email'], 'Registration received — pending approval',
                "Hello {$v['full_name']},\n\nThank you for registering as a Travel Organizer. Your information has been sent to our administrators for validation. You will receive an email once your account is approved.\n\n— " . setting('company_name', 'FsM-co'));
            notify_admins('New travel organizer to validate: ' . $v['full_name'],
                "A new travel organizer submitted their information:\n\nName: {$v['full_name']}\nEmail: {$v['email']}\nPhone: {$v['phone']}\nNationality: {$v['nationality']}\nUniversity study: {$v['university']}\nTraining / licenses: {$v['training']}\nSkills: {$v['skills']}\n\nReview it in Admin › Guide approvals.", $v['email']);
        } else {
            send_mail($id, $v['email'], 'Welcome to ' . APP_NAME,
                "Hello {$v['full_name']},\n\nYour tourist account was created successfully. You can now browse trips on the map and join the ones you like.\n\n— " . setting('company_name', 'FsM-co'));
        }
        audit('register', "$role {$v['email']}", $id);
        login_user(q('SELECT * FROM users WHERE id = ?', [$id])->fetch());
        flash('success', $role === 'organizer' ? t('org_registered') : t('tourist_registered'));
        redirect(home_for_role());
    }
}

$title = $role === 'organizer' ? t('organizer_registration') : t('tourist_registration');
page_header($title, ['bottom_nav' => false]);
?>
<div class="card medium">
  <h1 class="form-title"><?= e($title) ?></h1>
  <?php if ($duplicate): ?>
    <div class="alert alert-info"><?= e(t('email_taken_hint')) ?>
      <a href="login.php?role=<?= e($role) ?>"><?= e(t('login')) ?></a> · <a href="forgot.php"><?= e(t('forgot_password')) ?></a></div>
  <?php endif; ?>
  <form method="post" class="form-panel" data-validate novalidate>
    <?= csrf_field() ?>
    <label for="full_name"><?= e(t('full_name')) ?></label>
    <input type="text" id="full_name" name="full_name" value="<?= e($v['full_name']) ?>" placeholder="<?= e(t('ph_full_name')) ?>" required>
    <?= field_error($errors, 'full_name') ?>

    <label for="email"><?= e(t('email_address')) ?></label>
    <input type="email" id="email" name="email" value="<?= e($v['email']) ?>" placeholder="<?= e(t('ph_email')) ?>" required>
    <?= field_error($errors, 'email') ?>

    <label for="phone"><?= e(t('phone_number')) ?></label>
    <input type="tel" id="phone" name="phone" value="<?= e($v['phone']) ?>" placeholder="<?= e(t('ph_phone')) ?>" required>
    <?= field_error($errors, 'phone') ?>

    <label for="dob"><?= e(t('date_of_birth')) ?></label>
    <input type="date" id="dob" name="dob" value="<?= e($v['dob']) ?>" max="<?= date('Y-m-d') ?>" required>
    <?= field_error($errors, 'dob') ?>

    <label for="nationality"><?= e(t('nationality')) ?></label>
    <select id="nationality" name="nationality"><?= options(NATIONALITIES, $v['nationality']) ?></select>

    <?php if ($role === 'organizer'): ?>
      <label for="university"><?= e(t('university_study')) ?></label>
      <input type="text" id="university" name="university" value="<?= e($v['university']) ?>" placeholder="<?= e(t('ph_university')) ?>" required>
      <?= field_error($errors, 'university') ?>

      <label for="training"><?= e(t('training_licenses')) ?></label>
      <input type="text" id="training" name="training" value="<?= e($v['training']) ?>" placeholder="<?= e(t('ph_training')) ?>" required>
      <?= field_error($errors, 'training') ?>

      <label for="skills"><?= e(t('skills')) ?></label>
      <input type="text" id="skills" name="skills" value="<?= e($v['skills']) ?>" placeholder="<?= e(t('ph_skills')) ?>" required>
      <?= field_error($errors, 'skills') ?>
    <?php endif; ?>

    <label for="language"><?= e(t('language')) ?></label>
    <select id="language" name="language"><?= options(LANGUAGES, $v['language']) ?></select>

    <label for="password"><?= e(t('password')) ?></label>
    <input type="password" id="password" name="password" placeholder="<?= e(t('ph_password')) ?>" required autocomplete="new-password">
    <?= field_error($errors, 'password') ?>
    <div class="muted small"><?= e(t('pw_rules')) ?></div>

    <label for="password2"><?= e(t('confirm_password')) ?></label>
    <input type="password" id="password2" name="password2" placeholder="<?= e(t('ph_password2')) ?>" required autocomplete="new-password">
    <?= field_error($errors, 'password2') ?>

    <button class="btn block" type="submit"><?= e(t('submit')) ?></button>
  </form>
  <p class="center mt"><?= e(t('have_account')) ?> <a href="login.php?role=<?= e($role) ?>"><?= e(t('login')) ?></a></p>
</div>
<?php page_footer();
