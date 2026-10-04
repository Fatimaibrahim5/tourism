<?php
// Trip creation / update (Feature 5.4): details, itinerary, price, capacity, language, location on the map,
// photos and the list of places/hotels/restaurants visited. Critical changes notify booked tourists.
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/data.php';

$user = require_login(['organizer']);
if ($user['status'] !== 'active') { flash('error', t('pending_cannot_publish')); redirect('org_dashboard.php'); }

$id = (int)($_GET['id'] ?? 0);
$trip = $id ? q('SELECT * FROM trips WHERE id = ? AND organizer_id = ?', [$id, $user['id']])->fetch() : null;
if ($id && !$trip) { flash('error', t('not_found')); redirect('org_dashboard.php'); }

$fields = ['title', 'destination', 'description', 'itinerary', 'start_date', 'end_date', 'price', 'discount_pct', 'capacity',
           'language', 'lat', 'lng', 'transport_info', 'includes', 'excludes', 'status'];
$v = [];
foreach ($fields as $f) $v[$f] = is_post() ? post($f) : ($trip[$f] ?? '');
if (!$trip && !is_post()) {
    $v = array_merge($v, ['discount_pct' => 0, 'capacity' => 20, 'language' => 'English', 'lat' => '33.8938', 'lng' => '35.5018', 'status' => 'published']);
}
$stops = $trip ? q('SELECT * FROM trip_stops WHERE trip_id = ? ORDER BY sort_order, id', [$id])->fetchAll() : [];
$booked = $trip ? seats_taken($id) : 0;
$errors = [];

if (is_post()) {
    check_csrf();
    if (mb_strlen($v['title']) < 3) $errors['title'] = t('required');
    if (mb_strlen($v['destination']) < 2) $errors['destination'] = t('required');
    if (mb_strlen($v['description']) < 10) $errors['description'] = t('err_message_short');
    $sd = DateTime::createFromFormat('Y-m-d', $v['start_date']);
    $ed = DateTime::createFromFormat('Y-m-d', $v['end_date']);
    if (!$sd) $errors['start_date'] = t('err_date');
    elseif ((!$trip || $v['start_date'] !== $trip['start_date']) && $v['start_date'] < date('Y-m-d')) $errors['start_date'] = t('err_past_date');
    if (!$ed) $errors['end_date'] = t('err_date');
    elseif ($sd && $v['end_date'] < $v['start_date']) $errors['end_date'] = t('err_end_before_start');
    if (!is_numeric($v['price']) || $v['price'] < 0) $errors['price'] = t('err_price');
    if (!ctype_digit((string)$v['discount_pct']) || $v['discount_pct'] > MAX_DISCOUNT_PCT) $errors['discount_pct'] = t('discount_rule', ['n' => MAX_DISCOUNT_PCT]);
    if (!ctype_digit((string)$v['capacity']) || $v['capacity'] < 1) $errors['capacity'] = t('err_capacity');
    elseif ($v['capacity'] < $booked) $errors['capacity'] = t('err_capacity_booked', ['n' => $booked]);
    if (!in_array($v['language'], LANGUAGES, true)) $errors['language'] = t('required');
    if (!is_numeric($v['lat']) || abs($v['lat']) > 90 || !is_numeric($v['lng']) || abs($v['lng']) > 180) $errors['lat'] = t('err_location');
    if (!in_array($v['status'], ['draft', 'published'], true)) $v['status'] = 'draft';
    if ($trip && $v['status'] === 'draft' && $trip['status'] === 'published' && $booked) $errors['status'] = t('has_bookings_unpublish');

    $cover = $trip['cover_image'] ?? null;
    if (!$errors && has_upload('cover')) {
        try { $cover = upload_image($_FILES['cover'], 'trips'); } catch (RuntimeException $ex) { $errors['cover'] = $ex->getMessage(); }
    }

    // Collect stops
    $postedStops = [];
    foreach ($_POST['stops'] ?? [] as $key => $s) {
        $name = trim($s['name'] ?? '');
        if ($name === '' || !empty($s['delete'])) { $postedStops[$key] = ['delete' => true, 'id' => (int)($s['id'] ?? 0)]; continue; }
        $img = null;
        if (isset($_FILES['stop_image']['error'][$key]) && $_FILES['stop_image']['error'][$key] !== UPLOAD_ERR_NO_FILE) {
            $file = [];
            foreach (['name', 'type', 'tmp_name', 'error', 'size'] as $k) $file[$k] = $_FILES['stop_image'][$k][$key];
            try { $img = upload_image($file, 'stops'); } catch (RuntimeException $ex) { $errors['stops'] = $name . ': ' . $ex->getMessage(); }
        }
        $postedStops[$key] = [
            'id' => (int)($s['id'] ?? 0), 'name' => mb_substr($name, 0, 160),
            'kind' => in_array($s['kind'] ?? '', ['place', 'hotel', 'restaurant', 'meeting'], true) ? $s['kind'] : 'place',
            'entrance_info' => mb_substr(trim($s['entrance_info'] ?? ''), 0, 255), 'description' => trim($s['description'] ?? ''),
            'lat' => is_numeric($s['lat'] ?? '') ? $s['lat'] : null, 'lng' => is_numeric($s['lng'] ?? '') ? $s['lng'] : null,
            'image' => $img,
        ];
    }

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        $params = [$v['title'], $v['destination'], $v['description'], $v['itinerary'], $v['start_date'], $v['end_date'], $v['price'],
                   (int)$v['discount_pct'], (int)$v['capacity'], $v['language'], $v['lat'], $v['lng'], $v['transport_info'],
                   $v['includes'], $v['excludes'], $cover, $v['status']];
        if ($trip) {
            q('UPDATE trips SET title=?, destination=?, description=?, itinerary=?, start_date=?, end_date=?, price=?, discount_pct=?, capacity=?,
               language=?, lat=?, lng=?, transport_info=?, includes=?, excludes=?, cover_image=?, status=? WHERE id = ?', [...$params, $id]);
        } else {
            q('INSERT INTO trips (title, destination, description, itinerary, start_date, end_date, price, discount_pct, capacity, language, lat, lng,
               transport_info, includes, excludes, cover_image, status, organizer_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [...$params, $user['id']]);
            $id = (int)$pdo->lastInsertId();
        }
        $order = 0;
        foreach ($postedStops as $s) {
            if (!empty($s['delete'])) {
                if ($s['id']) q('DELETE FROM trip_stops WHERE id = ? AND trip_id = ?', [$s['id'], $id]);
                continue;
            }
            $order++;
            if ($s['id']) {
                q('UPDATE trip_stops SET name=?, kind=?, entrance_info=?, description=?, lat=?, lng=?, sort_order=?' . ($s['image'] ? ', image=?' : '') . ' WHERE id = ? AND trip_id = ?',
                  array_merge([$s['name'], $s['kind'], $s['entrance_info'], $s['description'], $s['lat'], $s['lng'], $order], $s['image'] ? [$s['image']] : [], [$s['id'], $id]));
            } else {
                q('INSERT INTO trip_stops (trip_id, name, kind, entrance_info, description, lat, lng, sort_order, image) VALUES (?,?,?,?,?,?,?,?,?)',
                  [$id, $s['name'], $s['kind'], $s['entrance_info'], $s['description'], $s['lat'], $s['lng'], $order, $s['image']]);
            }
        }
        $pdo->commit();

        // Use case step 6: notify booked tourists when critical fields change
        if ($trip) {
            $changed = [];
            foreach (['start_date' => 'Start date', 'end_date' => 'End date', 'price' => 'Price', 'destination' => 'Destination'] as $f => $label) {
                if ((string)$trip[$f] !== (string)$v[$f] && !($f === 'price' && (float)$trip[$f] === (float)$v[$f])) $changed[] = "$label: {$trip[$f]} → {$v[$f]}";
            }
            if ($changed) {
                $tourists = q("SELECT DISTINCT tourist_id FROM bookings WHERE trip_id = ? AND status IN ('pending','confirmed')", [$id])->fetchAll(PDO::FETCH_COLUMN);
                foreach ($tourists as $tid) {
                    notify_user((int)$tid, 'Trip updated: ' . $v['title'], "The organizer changed important details of your trip:\n\n" . implode("\n", $changed) . "\n\nIf this no longer suits you, you can cancel from your profile.");
                }
            }
        }
        if ($v['status'] === 'published') notify_followers($id);
        flash('success', $trip ? t('trip_updated') : t('trip_created'));
        redirect('org_dashboard.php');
    }
    // Keep posted stops visible after a validation error
    $stops = array_values(array_filter(array_map(fn($s) => empty($s['delete']) ? $s : null, $postedStops)));
}

page_header($trip ? t('edit_trip') : t('create_trip'), ['map' => true]);
?>
<div class="page-head"><h1><?= e($trip ? t('edit_trip') : t('create_trip')) ?></h1><a href="org_dashboard.php">‹ <?= e(t('my_trips')) ?></a></div>
<?php if ($errors): ?><div class="alert alert-error"><?= e(t('fix_errors')) ?></div><?php endif; ?>

<form method="post" enctype="multipart/form-data" data-validate novalidate>
  <?= csrf_field() ?>
  <div class="card">
    <h2><?= e(t('trip_details')) ?></h2>
    <div class="form-grid">
      <div><label for="title"><?= e(t('title')) ?></label><input type="text" id="title" name="title" maxlength="160" value="<?= e($v['title']) ?>" required><?= field_error($errors, 'title') ?></div>
      <div><label for="destination"><?= e(t('destination')) ?></label><input type="text" id="destination" name="destination" maxlength="160" value="<?= e($v['destination']) ?>" required><?= field_error($errors, 'destination') ?></div>
      <div class="full"><label for="description"><?= e(t('overview_area')) ?></label><textarea id="description" name="description" required><?= e($v['description']) ?></textarea><?= field_error($errors, 'description') ?></div>
      <div class="full"><label for="itinerary"><?= e(t('itinerary')) ?></label><textarea id="itinerary" name="itinerary" placeholder="08:00 — Departure from Beirut&#10;10:00 — ..."><?= e($v['itinerary']) ?></textarea></div>
      <div><label for="start_date"><?= e(t('start_date')) ?></label><input type="date" id="start_date" name="start_date" value="<?= e($v['start_date']) ?>" required><?= field_error($errors, 'start_date') ?></div>
      <div><label for="end_date"><?= e(t('end_date')) ?></label><input type="date" id="end_date" name="end_date" value="<?= e($v['end_date']) ?>" required><?= field_error($errors, 'end_date') ?></div>
      <div><label for="price"><?= e(t('price_per_person')) ?> ($)</label><input type="number" id="price" name="price" min="0" step="0.01" value="<?= e($v['price']) ?>" required><?= field_error($errors, 'price') ?></div>
      <div><label for="discount_pct"><?= e(t('discount')) ?> (0–<?= MAX_DISCOUNT_PCT ?>%)</label><input type="number" id="discount_pct" name="discount_pct" min="0" max="<?= MAX_DISCOUNT_PCT ?>" value="<?= e($v['discount_pct']) ?>"><?= field_error($errors, 'discount_pct') ?></div>
      <div><label for="capacity"><?= e(t('capacity')) ?></label><input type="number" id="capacity" name="capacity" min="1" value="<?= e($v['capacity']) ?>" required><?= field_error($errors, 'capacity') ?>
        <?php if ($booked): ?><div class="muted small"><?= e(t('already_booked', ['n' => $booked])) ?></div><?php endif; ?></div>
      <div><label for="language"><?= e(t('trip_language')) ?></label><select id="language" name="language"><?= options(LANGUAGES, $v['language']) ?></select></div>
      <div><label for="includes"><?= e(t('includes')) ?></label><input type="text" id="includes" name="includes" maxlength="255" value="<?= e($v['includes']) ?>" placeholder="<?= e(t('ph_includes')) ?>"></div>
      <div><label for="excludes"><?= e(t('excludes')) ?></label><input type="text" id="excludes" name="excludes" maxlength="255" value="<?= e($v['excludes']) ?>" placeholder="<?= e(t('ph_excludes')) ?>"></div>
      <div class="full"><label for="transport_info"><?= e(t('info_transport')) ?></label><textarea id="transport_info" name="transport_info" placeholder="<?= e(t('ph_transport')) ?>"><?= e($v['transport_info']) ?></textarea></div>
      <div><label for="cover"><?= e(t('cover_photo')) ?></label><input type="file" id="cover" name="cover" accept="image/*" data-preview="coverPreview"><?= field_error($errors, 'cover') ?></div>
      <div><img id="coverPreview" class="<?= !empty($trip['cover_image']) ? '' : 'hidden' ?>" src="<?= e($trip['cover_image'] ?? '') ?>" alt="" style="max-height:130px;border-radius:8px;margin-top:14px"></div>
      <div><label for="status"><?= e(t('status')) ?></label>
        <select id="status" name="status">
          <option value="published" <?= $v['status'] === 'published' ? 'selected' : '' ?>><?= e(t('st_published')) ?></option>
          <option value="draft" <?= $v['status'] === 'draft' ? 'selected' : '' ?>><?= e(t('st_draft')) ?></option>
        </select><?= field_error($errors, 'status') ?></div>
    </div>
  </div>

  <div class="card">
    <h2><?= e(t('location')) ?></h2>
    <p class="muted small"><?= e(t('map_pick_help')) ?> <b id="pickTarget"><?= e(t('trip_location')) ?></b></p>
    <div id="map" style="height:360px"></div>
    <div class="form-grid">
      <div><label for="lat">Latitude</label><input type="text" id="lat" name="lat" value="<?= e($v['lat']) ?>" required></div>
      <div><label for="lng">Longitude</label><input type="text" id="lng" name="lng" value="<?= e($v['lng']) ?>" required></div>
    </div>
    <?= field_error($errors, 'lat') ?>
  </div>

  <div class="card">
    <div class="row between"><h2 class="mb0"><?= e(t('places_to_visit')) ?></h2><button type="button" class="btn sm outline" id="addStop">＋ <?= e(t('add_stop')) ?></button></div>
    <p class="muted small"><?= e(t('stops_help')) ?></p>
    <?= field_error($errors, 'stops') ?>
    <div id="stops">
      <?php foreach ($stops as $i => $s): ?>
        <?= stop_row('e' . $i, $s) ?>
      <?php endforeach; ?>
    </div>
    <template id="stopTpl"><?= stop_row('__KEY__', []) ?></template>
  </div>

  <button class="btn block"><?= e($trip ? t('save_changes') : t('create_trip')) ?></button>
</form>

<?php
function stop_row(string $key, array $s): string {
    ob_start(); ?>
  <div class="card flat stop-row" data-key="<?= e($key) ?>">
    <input type="hidden" name="stops[<?= e($key) ?>][id]" value="<?= (int)($s['id'] ?? 0) ?>">
    <div class="form-grid">
      <div><label><?= e(t('name')) ?></label><input type="text" name="stops[<?= e($key) ?>][name]" value="<?= e($s['name'] ?? '') ?>" maxlength="160"></div>
      <div><label><?= e(t('type')) ?></label>
        <select name="stops[<?= e($key) ?>][kind]">
          <?php foreach (['place', 'hotel', 'restaurant', 'meeting'] as $k): ?><option value="<?= $k ?>" <?= ($s['kind'] ?? 'place') === $k ? 'selected' : '' ?>><?= e(t('pin_' . ($k === 'place' ? 'trip' : $k))) ?></option><?php endforeach; ?>
        </select></div>
      <div><label><?= e(t('entrance_info')) ?></label><input type="text" name="stops[<?= e($key) ?>][entrance_info]" value="<?= e($s['entrance_info'] ?? '') ?>" placeholder="<?= e(t('ph_entrance')) ?>"></div>
      <div class="full"><label><?= e(t('description')) ?></label><textarea name="stops[<?= e($key) ?>][description]" style="min-height:60px"><?= e($s['description'] ?? '') ?></textarea></div>
      <div><label>Lat</label><input type="text" class="s-lat" name="stops[<?= e($key) ?>][lat]" value="<?= e($s['lat'] ?? '') ?>"></div>
      <div><label>Lng</label><input type="text" class="s-lng" name="stops[<?= e($key) ?>][lng]" value="<?= e($s['lng'] ?? '') ?>"></div>
      <div><label><?= e(t('photo')) ?></label><input type="file" name="stop_image[<?= e($key) ?>]" accept="image/*">
        <?php if (!empty($s['image'])): ?><img src="<?= e($s['image']) ?>" alt="" style="max-height:60px;margin-top:6px;border-radius:6px"><?php endif; ?></div>
    </div>
    <div class="row between mt">
      <button type="button" class="btn sm outline pick">📍 <?= e(t('pick_on_map')) ?></button>
      <label class="check"><input type="checkbox" name="stops[<?= e($key) ?>][delete]" value="1"> <?= e(t('remove')) ?></label>
    </div>
  </div>
<?php return ob_get_clean();
}
?>
<script>
(function () {
  const T = window.I18N || {};
  const latI = document.getElementById('lat'), lngI = document.getElementById('lng');
  let target = { lat: latI, lng: lngI, label: T.trip_location || 'Trip location' };
  const label = document.getElementById('pickTarget');
  let map, marker;
  if (typeof L !== 'undefined') {
    map = L.map('map').setView([parseFloat(latI.value) || 33.89, parseFloat(lngI.value) || 35.5], 9);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18, attribution: '&copy; OpenStreetMap' }).addTo(map);
    marker = L.marker([parseFloat(latI.value) || 33.89, parseFloat(lngI.value) || 35.5]).addTo(map);
    map.on('click', e => {
      target.lat.value = e.latlng.lat.toFixed(6);
      target.lng.value = e.latlng.lng.toFixed(6);
      if (target.lat === latI) marker.setLatLng(e.latlng);
      else L.circleMarker(e.latlng, { radius: 7, color: '#c2410c' }).addTo(map);
      target = { lat: latI, lng: lngI, label: T.trip_location || 'Trip location' };
      label.textContent = target.label;
    });
  }
  const stops = document.getElementById('stops');
  stops.addEventListener('click', e => {
    const btn = e.target.closest('.pick');
    if (!btn) return;
    const row = btn.closest('.stop-row');
    const name = row.querySelector('input[name$="[name]"]').value || '…';
    target = { lat: row.querySelector('.s-lat'), lng: row.querySelector('.s-lng'), label: name };
    label.textContent = name;
    document.getElementById('map').scrollIntoView({ behavior: 'smooth', block: 'center' });
  });
  let n = 0;
  document.getElementById('addStop').addEventListener('click', () => {
    const html = document.getElementById('stopTpl').innerHTML.replaceAll('__KEY__', 'n' + (++n));
    stops.insertAdjacentHTML('beforeend', html);
    stops.lastElementChild.querySelector('input[type=text]').focus();
  });
})();
</script>
<?php page_footer();
