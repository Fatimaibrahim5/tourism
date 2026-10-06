<?php
// REQ-5 — administrators approve or reject travel organizer (guide) registrations; extension 2a: request more info.
require __DIR__ . '/includes/functions.php';

$admin = require_login(['admin']);

if (is_post()) {
    check_csrf();
    $g = q("SELECT * FROM users WHERE id = ? AND role = 'organizer'", [(int)post('user')])->fetch();
    $note = post('note');
    if ($g && !can_access_user($g['email'])) deny_demo('admin_guides.php');
    if ($g) {
        switch (post('action')) {
            case 'approve':
                q("UPDATE users SET status = 'active' WHERE id = ?", [$g['id']]);
                q('UPDATE organizer_profiles SET admin_note = ? WHERE user_id = ?', [$note ?: null, $g['id']]);
                send_mail($g['id'], $g['email'], 'Your Travel Organizer account is approved',
                    "Hello {$g['full_name']},\n\nGood news! Your account has been validated by our administrators. You can now create and publish trips.\n" . ($note ? "\nNote: $note\n" : ''));
                audit('guide_approved', "user #{$g['id']} {$g['email']}");
                flash('success', t('guide_approved'));
                break;
            case 'reject':
                q("UPDATE users SET status = 'rejected' WHERE id = ?", [$g['id']]);
                q('UPDATE organizer_profiles SET admin_note = ? WHERE user_id = ?', [$note ?: null, $g['id']]);
                send_mail($g['id'], $g['email'], 'Your Travel Organizer application',
                    "Hello {$g['full_name']},\n\nUnfortunately your application could not be approved." . ($note ? "\n\nReason: $note" : ''));
                audit('guide_rejected', "user #{$g['id']} {$g['email']}");
                flash('success', t('guide_rejected'));
                break;
            case 'request_info':
                q('UPDATE organizer_profiles SET admin_note = ? WHERE user_id = ?', [$note, $g['id']]);
                send_mail($g['id'], $g['email'], 'More information needed for your application',
                    "Hello {$g['full_name']},\n\nTo validate your Travel Organizer account we need more information:\n\n$note\n\nPlease update your profile or reply to " . setting('company_email') . '.');
                audit('guide_info_requested', "user #{$g['id']}");
                flash('success', t('info_requested'));
                break;
        }
    }
    redirect('admin_guides.php?tab=' . urlencode($_GET['tab'] ?? 'pending'));
}

$tab = in_array($_GET['tab'] ?? '', ['pending', 'active', 'rejected', 'suspended'], true) ? $_GET['tab'] : 'pending';
$guides = q("SELECT u.*, op.university, op.training, op.skills, op.language_skills, op.admin_note,
               (SELECT COUNT(*) FROM trips t WHERE t.organizer_id = u.id) AS n_trips
             FROM users u LEFT JOIN organizer_profiles op ON op.user_id = u.id
             WHERE u.role = 'organizer' AND u.status = ?" . demo_scope('u.email') . " ORDER BY u.created_at DESC", [$tab])->fetchAll();

page_header(t('guide_approvals'), ['main_class' => 'wide']);
?>
<div class="page-head"><h1><?= e(t('guide_approvals')) ?></h1></div>
<div class="tabs">
  <?php foreach (['pending', 'active', 'rejected', 'suspended'] as $s): ?>
    <a href="?tab=<?= $s ?>" class="<?= $tab === $s ? 'active' : '' ?>"><?= e(t('st_' . $s)) ?></a>
  <?php endforeach; ?>
</div>
<?php if (!$guides): ?><div class="card center muted"><?= e(t('nothing_here')) ?></div><?php endif; ?>
<div class="grid grid-2">
<?php foreach ($guides as $g): $age = $g['dob'] ? (new DateTime($g['dob']))->diff(new DateTime())->y : '—'; ?>
  <div class="card">
    <div class="row between"><h3 class="mb0"><?= e($g['full_name']) ?></h3><?= status_badge($g['status']) ?></div>
    <p class="muted small"><?= e($g['email']) ?> · <?= e($g['phone']) ?> · <?= e(t('age')) ?> <?= e($age) ?> · <?= e($g['nationality']) ?><br><?= e(t('registered')) ?> <?= e(fdate($g['created_at'])) ?> · <?= (int)$g['n_trips'] ?> <?= e(t('trips')) ?></p>
    <p class="small mb0"><b><?= e(t('university_study')) ?>:</b> <?= e($g['university']) ?></p>
    <p class="small mb0"><b><?= e(t('training_licenses')) ?>:</b> <?= e($g['training']) ?></p>
    <p class="small mb0"><b><?= e(t('skills')) ?>:</b> <?= e($g['skills']) ?></p>
    <p class="small"><b><?= e(t('language_skills')) ?>:</b> <?= e($g['language_skills']) ?></p>
    <?php if ($g['admin_note']): ?><div class="alert alert-info small"><?= e(t('note')) ?>: <?= e($g['admin_note']) ?></div><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="user" value="<?= (int)$g['id'] ?>">
      <input type="text" name="note" placeholder="<?= e(t('ph_admin_note')) ?>">
      <div class="row mt">
        <?php if ($g['status'] !== 'active'): ?><button class="btn sm success" name="action" value="approve">✓ <?= e(t('approve')) ?></button><?php endif; ?>
        <?php if ($g['status'] === 'pending'): ?>
          <button class="btn sm danger" name="action" value="reject" data-confirm="<?= e(t('confirm_reject')) ?>">✕ <?= e(t('reject')) ?></button>
          <button class="btn sm outline" name="action" value="request_info"><?= e(t('request_info')) ?></button>
        <?php endif; ?>
        <a class="btn sm outline" href="guide.php?id=<?= (int)$g['id'] ?>"><?= e(t('view_profile')) ?></a>
      </div>
    </form>
  </div>
<?php endforeach; ?>
</div>
<?php page_footer();
