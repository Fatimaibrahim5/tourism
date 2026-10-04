<?php
// Heart button: add / remove a trip from the tourist's favorites. Answers JSON to fetch() calls.
require __DIR__ . '/includes/functions.php';

$ajax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
if (!current_user()) {
    if ($ajax) { http_response_code(401); echo json_encode(['login' => 'login.php?role=tourist']); exit; }
    require_login(['tourist']);
}
$user = require_login(['tourist']);
if (!is_post()) redirect('profile.php#favorites');
check_csrf();

$tripId = (int)($_POST['trip'] ?? 0);
if (!q('SELECT 1 FROM trips WHERE id = ?', [$tripId])->fetch()) {
    if ($ajax) { http_response_code(404); exit; }
    redirect('home.php');
}

$exists = q('SELECT 1 FROM favorites WHERE user_id = ? AND trip_id = ?', [$user['id'], $tripId])->fetch();
if ($exists) {
    q('DELETE FROM favorites WHERE user_id = ? AND trip_id = ?', [$user['id'], $tripId]);
} else {
    q('INSERT INTO favorites (user_id, trip_id) VALUES (?, ?)', [$user['id'], $tripId]);
}
$on = !$exists;

if ($ajax) {
    header('Content-Type: application/json');
    echo json_encode(['on' => $on, 'title' => $on ? t('remove_favorite') : t('add_favorite'), 'msg' => $on ? t('saved_to_favorites') : t('removed_from_favorites')]);
    exit;
}
flash('success', $on ? t('saved_to_favorites') : t('removed_from_favorites'));
// Go back to the page the heart was clicked on (same site only)
$ref = parse_url($_SERVER['HTTP_REFERER'] ?? '');
redirect(!empty($ref['path']) ? basename($ref['path']) . (isset($ref['query']) ? '?' . $ref['query'] : '') : 'profile.php#favorites');
