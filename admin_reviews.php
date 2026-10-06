<?php
// REQ-10 — administrators moderate reviews/comments (approve, hide, delete) and shared photos.
require __DIR__ . '/includes/functions.php';

$admin = require_login(['admin']);
$tab = in_array($_GET['tab'] ?? '', ['pending', 'approved', 'hidden', 'photos'], true) ? $_GET['tab'] : 'pending';

if (is_post()) {
    check_csrf();
    $action = post('action');
    if (str_starts_with($action, 'photo_')) {
        $p = q('SELECT p.*, u.email, t.title FROM photos p JOIN users u ON u.id = p.user_id JOIN trips t ON t.id = p.trip_id WHERE p.id = ?', [(int)post('id')])->fetch();
        if ($p && !can_access_user($p['email'])) deny_demo('admin_reviews.php?tab=' . $tab);
        if ($p) {
            if ($action === 'photo_hide' || $action === 'photo_show') {
                q('UPDATE photos SET status = ? WHERE id = ?', [$action === 'photo_hide' ? 'hidden' : 'visible', $p['id']]);
            } elseif ($action === 'photo_delete') {
                q('DELETE FROM photos WHERE id = ?', [$p['id']]);
                if (str_starts_with($p['file_path'], UPLOAD_URL)) @unlink(__DIR__ . '/' . $p['file_path']);
            }
            if ($action !== 'photo_show') send_mail((int)$p['user_id'], $p['email'], 'Your photo was removed', "A photo you shared for \"{$p['title']}\" was removed by a moderator because it does not follow our guidelines.");
            audit('photo_moderated', "$action photo #{$p['id']}");
        }
    } else {
        $r = q('SELECT r.*, u.email, t.title FROM ratings r JOIN users u ON u.id = r.tourist_id JOIN trips t ON t.id = r.trip_id WHERE r.id = ?', [(int)post('id')])->fetch();
        if ($r && !can_access_user($r['email'])) deny_demo('admin_reviews.php?tab=' . $tab);
        if ($r) {
            if ($action === 'approve') {
                q("UPDATE ratings SET status = 'approved' WHERE id = ?", [$r['id']]);
                send_mail((int)$r['tourist_id'], $r['email'], 'Your review is published', "Thank you! Your review of \"{$r['title']}\" is now visible to other travellers.");
            } elseif ($action === 'hide') {
                q("UPDATE ratings SET status = 'hidden' WHERE id = ?", [$r['id']]);
                send_mail((int)$r['tourist_id'], $r['email'], 'Your review was not published', "Your review of \"{$r['title']}\" was not published because it does not follow our community guidelines.");
            } elseif ($action === 'delete') {
                q('DELETE FROM ratings WHERE id = ?', [$r['id']]);
            }
            audit('review_' . $action, "review #{$r['id']} trip #{$r['trip_id']}");
        }
    }
    flash('success', t('saved'));
    redirect('admin_reviews.php?tab=' . $tab);
}

$counts = q('SELECT r.status, COUNT(*) c FROM ratings r JOIN users u ON u.id = r.tourist_id WHERE 1=1' . demo_scope('u.email') . ' GROUP BY r.status')->fetchAll(PDO::FETCH_KEY_PAIR);
page_header(t('reviews'), ['main_class' => 'wide']);
?>
<div class="page-head"><h1><?= e(t('reviews')) ?></h1></div>
<div class="tabs">
  <?php foreach (['pending', 'approved', 'hidden'] as $s): ?>
    <a href="?tab=<?= $s ?>" class="<?= $tab === $s ? 'active' : '' ?>"><?= e(t('st_' . $s)) ?> (<?= (int)($counts[$s] ?? 0) ?>)</a>
  <?php endforeach; ?>
  <a href="?tab=photos" class="<?= $tab === 'photos' ? 'active' : '' ?>">📷 <?= e(t('photos')) ?></a>
</div>

<?php if ($tab === 'photos'):
    $photos = q('SELECT p.*, u.full_name, t.title FROM photos p JOIN users u ON u.id = p.user_id JOIN trips t ON t.id = p.trip_id
                 WHERE 1=1' . demo_scope('u.email') . ' ORDER BY p.created_at DESC LIMIT 60')->fetchAll(); ?>
  <?php if (!$photos): ?><div class="card center muted"><?= e(t('nothing_here')) ?></div><?php endif; ?>
  <div class="grid grid-3">
    <?php foreach ($photos as $p): ?>
      <div class="trip-card">
        <img class="cover" src="<?= e($p['file_path']) ?>" alt="" loading="lazy" style="<?= $p['status'] === 'hidden' ? 'opacity:.35' : '' ?>">
        <div class="body">
          <b><?= e($p['title']) ?></b>
          <span class="muted small"><?= e($p['full_name']) ?> · <?= e(fdate($p['created_at'])) ?> <?= status_badge($p['status']) ?></span>
          <?php if ($p['caption']): ?><span class="small"><?= e($p['caption']) ?></span><?php endif; ?>
          <form method="post" class="row mt"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <?php if ($p['status'] === 'visible'): ?><button class="btn sm outline" name="action" value="photo_hide"><?= e(t('hide')) ?></button>
            <?php else: ?><button class="btn sm success" name="action" value="photo_show"><?= e(t('show')) ?></button><?php endif; ?>
            <button class="btn sm danger" name="action" value="photo_delete" data-confirm="<?= e(t('confirm_delete_photo')) ?>"><?= e(t('delete')) ?></button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php else:
    $rows = q('SELECT r.*, u.full_name, t.title FROM ratings r JOIN users u ON u.id = r.tourist_id JOIN trips t ON t.id = r.trip_id
               WHERE r.status = ?' . demo_scope('u.email') . ' ORDER BY r.created_at DESC', [$tab])->fetchAll(); ?>
  <?php if (!$rows): ?><div class="card center muted"><?= e(t('nothing_here')) ?></div><?php endif; ?>
  <?php foreach ($rows as $r): ?>
    <div class="card">
      <div class="row between"><div><b><?= e($r['full_name']) ?></b> → <a href="trip.php?id=<?= (int)$r['trip_id'] ?>"><?= e($r['title']) ?></a></div><span class="muted small"><?= e(fdate($r['created_at'], true)) ?></span></div>
      <?= stars((float)$r['score']) ?>
      <p class="prewrap"><?= e($r['comment'] ?: '—') ?></p>
      <form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
        <?php if ($r['status'] !== 'approved'): ?><button class="btn sm success" name="action" value="approve">✓ <?= e(t('approve')) ?></button><?php endif; ?>
        <?php if ($r['status'] !== 'hidden'): ?><button class="btn sm outline" name="action" value="hide"><?= e(t('hide')) ?></button><?php endif; ?>
        <button class="btn sm danger" name="action" value="delete" data-confirm="<?= e(t('confirm_action')) ?>"><?= e(t('delete')) ?></button>
      </form>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
<?php page_footer();
