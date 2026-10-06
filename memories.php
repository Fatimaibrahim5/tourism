<?php
// Fig 8 — Travel memories: the trip's organizer and the tourists who joined the trip can add photos.
require __DIR__ . '/includes/functions.php';

$tripId = (int)($_GET['trip'] ?? $_POST['trip'] ?? 0);
$trip = q('SELECT * FROM trips WHERE id = ?', [$tripId])->fetch();
if (!$trip) { flash('error', t('not_found')); redirect('home.php'); }

$u = current_user();
$canAdd = $u && (
    ($u['role'] === 'organizer' && (int)$trip['organizer_id'] === (int)$u['id']) ||
    ($u['role'] === 'tourist' && q("SELECT 1 FROM bookings WHERE trip_id = ? AND tourist_id = ? AND status = 'confirmed'", [$tripId, $u['id']])->fetch())
);

if (is_post()) {
    check_csrf();
    $action = post('action');
    if ($action === 'upload' && $canAdd && is_demo_viewer()) deny_demo('memories.php?trip=' . $tripId);
    if ($action === 'upload' && $canAdd) {
        try {
            $path = upload_image($_FILES['photo'] ?? [], 'memories');
            q('INSERT INTO photos (trip_id, user_id, file_path, caption) VALUES (?, ?, ?, ?)', [$tripId, $u['id'], $path, mb_substr(post('caption'), 0, 160)]);
            flash('success', t('photo_added'));
        } catch (RuntimeException $ex) {
            flash('error', $ex->getMessage());
        }
    } elseif ($action === 'delete' && $u) {
        $p = q('SELECT p.*, u.email FROM photos p JOIN users u ON u.id = p.user_id WHERE p.id = ? AND p.trip_id = ?', [(int)post('photo'), $tripId])->fetch();
        $moderator = $u['role'] === 'admin' || (int)$trip['organizer_id'] === (int)$u['id'];
        if ($p && ($p['user_id'] == $u['id'] || ($moderator && can_access_user($p['email'])))) {
            q('DELETE FROM photos WHERE id = ?', [$p['id']]);
            if (str_starts_with($p['file_path'], UPLOAD_URL)) @unlink(__DIR__ . '/' . $p['file_path']);
            if ($u['role'] === 'admin') audit('photo_deleted', "photo #{$p['id']} trip #$tripId");
            flash('success', t('photo_deleted'));
        }
    }
    redirect('memories.php?trip=' . $tripId);
}

$photos = q("SELECT p.*, u.full_name, u.email AS owner_email FROM photos p JOIN users u ON u.id = p.user_id
             WHERE p.trip_id = ? AND p.status = 'visible' ORDER BY p.created_at", [$tripId])->fetchAll();

page_header(t('travel_memories'));
?>
<div class="memories">
  <p><a href="trip.php?id=<?= $tripId ?>">‹ <?= e($trip['title']) ?></a></p>
  <div class="memories-frame">
    <h1><?= e(t('travel_memories')) ?></h1>
    <?php if (!$photos): ?><p class="muted"><?= e(t('no_photos')) ?></p><?php endif; ?>
    <?php foreach ($photos as $p): ?>
      <figure class="memory">
        <img src="<?= e($p['file_path']) ?>" alt="<?= e($p['caption'] ?: $trip['title']) ?>" loading="lazy">
        <div class="row">
          <figcaption class="small"><?php if ($p['caption']): ?><b><?= e($p['caption']) ?></b> · <?php endif; ?><span class="muted"><?= e($p['full_name']) ?>, <?= e(fdate($p['created_at'])) ?></span></figcaption>
          <?php if ($u && ($p['user_id'] == $u['id'] || (($u['role'] === 'admin' || (int)$trip['organizer_id'] === (int)$u['id']) && can_access_user($p['owner_email'])))): ?>
            <form method="post" class="inline" data-confirm="<?= e(t('confirm_delete_photo')) ?>">
              <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="trip" value="<?= $tripId ?>"><input type="hidden" name="photo" value="<?= (int)$p['id'] ?>">
              <button class="plus-btn" title="<?= e(t('delete')) ?>">−</button>
            </form>
          <?php elseif ($canAdd): ?>
            <button class="plus-btn" type="button" data-modal="addPhoto" title="<?= e(t('add_photo')) ?>">+</button>
          <?php endif; ?>
        </div>
      </figure>
    <?php endforeach; ?>
  </div>
  <?php if ($canAdd): ?>
    <div class="add-photo"><button class="btn" type="button" data-modal="addPhoto">＋ <?= e(t('add_photo')) ?></button></div>
  <?php elseif (!$u): ?>
    <p class="muted center mt"><?= e(t('login_to_add_photos')) ?></p>
  <?php else: ?>
    <p class="muted center mt"><?= e(t('only_participants')) ?></p>
  <?php endif; ?>
</div>

<?php if ($canAdd): ?>
<div class="modal" id="addPhoto" role="dialog" aria-modal="true">
  <div class="modal-box">
    <button class="modal-close" aria-label="<?= e(t('close')) ?>">×</button>
    <h2><?= e(t('add_photo')) ?></h2>
    <form method="post" enctype="multipart/form-data" data-validate novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="upload"><input type="hidden" name="trip" value="<?= $tripId ?>">
      <label for="photo"><?= e(t('photo')) ?> (JPG/PNG/WebP, ≤ 5 MB)</label>
      <input type="file" id="photo" name="photo" accept="image/*" capture="environment" data-preview="photoPreview" required>
      <img id="photoPreview" class="hidden mt" alt="" style="max-height:220px;border-radius:8px">
      <label for="caption"><?= e(t('caption')) ?></label>
      <input type="text" id="caption" name="caption" maxlength="160" placeholder="<?= e(t('ph_caption')) ?>">
      <button class="btn block"><?= e(t('upload')) ?></button>
    </form>
  </div>
</div>
<?php endif; ?>
<?php page_footer();
