<?php
// Settings sheet shared by the tourist and organizer profiles: photo, password & security, language, logout.
// Expects $user, $errors and $openSheet. Forms post to profile.php.
?>
<!-- Settings sheet: photo, security (password), language, logout -->
<div class="modal sheet" id="settingsSheet" role="dialog" aria-modal="true" <?= $openSheet === 'settingsSheet' ? 'data-open' : '' ?>>
  <div class="modal-box">
    <button class="modal-close" aria-label="<?= e(t('close')) ?>">×</button>
    <h2>⚙ <?= e(t('settings')) ?></h2>

    <div class="set-row">
      <?= avatar_html($user, 'avatar') ?>
      <div class="grow"><b><?= e(t('profile_photo')) ?></b><br><span class="muted small"><?= e(t('profile_photo_hint')) ?></span></div>
      <?php if (!empty($user['avatar'])): ?>
        <form method="post" action="profile.php"><?= csrf_field() ?><input type="hidden" name="action" value="remove_avatar"><button class="link-btn danger-text"><?= e(t('remove_photo')) ?></button></form>
      <?php endif; ?>
    </div>

    <details class="set-block" <?= $openSheet === 'settingsSheet' ? 'open' : '' ?>>
      <summary><span>🔒</span><div class="grow"><b><?= e(t('security')) ?></b><br><span class="muted small"><?= e(t('security_hint')) ?></span></div><span class="chev">›</span></summary>
      <form method="post" action="profile.php" class="pw-form" data-validate novalidate>
        <?= csrf_field() ?><input type="hidden" name="action" value="password">
        <label for="current"><?= e(t('current_password')) ?></label>
        <div class="pw-field"><input type="password" id="current" name="current" required autocomplete="current-password"><button type="button" class="eye" aria-label="<?= e(t('show_password')) ?>">👁</button></div>
        <?= field_error($errors, 'current') ?>
        <label for="password"><?= e(t('new_password')) ?></label>
        <div class="pw-field"><input type="password" id="password" name="password" required autocomplete="new-password" data-strength="pwMeter"><button type="button" class="eye" aria-label="<?= e(t('show_password')) ?>">👁</button></div>
        <div class="pw-meter" id="pwMeter"><span></span></div>
        <div class="pw-checks small">
          <span data-rule="len">○ <?= e(t('rule_len')) ?></span>
          <span data-rule="letter">○ <?= e(t('rule_letter')) ?></span>
          <span data-rule="digit">○ <?= e(t('rule_digit')) ?></span>
        </div>
        <?= field_error($errors, 'password') ?>
        <label for="password2"><?= e(t('confirm_password')) ?></label>
        <div class="pw-field"><input type="password" id="password2" name="password2" required autocomplete="new-password"><button type="button" class="eye" aria-label="<?= e(t('show_password')) ?>">👁</button></div>
        <div class="small" id="pwMatch"></div>
        <?= field_error($errors, 'password2') ?>
        <button class="btn block"><?= e(t('update_password')) ?></button>
      </form>
    </details>

    <a class="set-row link" href="<?= e(self_url_without_lang(lang() === 'ar' ? 'en' : 'ar')) ?>"><span>🌐</span><div class="grow"><b><?= e(t('language')) ?></b><br><span class="muted small"><?= lang() === 'ar' ? 'العربية → English' : 'English → العربية' ?></span></div><span class="chev">›</span></a>
    <?php if ($user['role'] === 'organizer'): ?>
    <a class="set-row link" href="org_dashboard.php"><span>🗂</span><div class="grow"><b><?= e(t('my_trips')) ?></b></div><span class="chev">›</span></a>
    <?php endif; ?>
    <a class="set-row link" href="complaints.php"><span>📝</span><div class="grow"><b><?= e(t('complaints')) ?></b></div><span class="chev">›</span></a>
    <a class="set-row link" href="help.php"><span>❓</span><div class="grow"><b><?= e(t('help_faq')) ?></b></div><span class="chev">›</span></a>
    <a class="set-row link danger-text" href="<?= e(logout_url()) ?>"><span>🚪</span><div class="grow"><b><?= e(t('logout')) ?></b></div></a>
  </div>
</div>
