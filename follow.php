<?php
// Follow / unfollow a travel organizer. Answers JSON to fetch() calls.
require __DIR__ . '/includes/functions.php';

$ajax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
if (!current_user() && $ajax) { http_response_code(401); echo json_encode(['login' => 'login.php?role=tourist']); exit; }
$user = require_login(['tourist']);
if (!is_post()) redirect('profile.php#following');
check_csrf();

$orgId = (int)($_POST['organizer'] ?? 0);
$org = q("SELECT id, full_name, email FROM users WHERE id = ? AND role = 'organizer' AND status = 'active'", [$orgId])->fetch();
if (!$org) {
    if ($ajax) { http_response_code(404); exit; }
    redirect('search.php');
}

$exists = q('SELECT 1 FROM follows WHERE follower_id = ? AND organizer_id = ?', [$user['id'], $orgId])->fetch();
if ($exists) {
    q('DELETE FROM follows WHERE follower_id = ? AND organizer_id = ?', [$user['id'], $orgId]);
} else {
    q('INSERT INTO follows (follower_id, organizer_id) VALUES (?, ?)', [$user['id'], $orgId]);
    notify_about((int)$org['id'], $user['email'], 'New follower', "{$user['full_name']} started following you. They will be notified when you publish a new trip.");
}
$on = !$exists;
$msg = $on ? t('now_following', ['name' => $org['full_name']]) : t('unfollowed', ['name' => $org['full_name']]);

if ($ajax) {
    header('Content-Type: application/json');
    echo json_encode(['on' => $on, 'label' => $on ? t('following') : t('follow'), 'followers' => followers_count($orgId), 'msg' => $msg]);
    exit;
}
flash('success', $msg);
$ref = parse_url($_SERVER['HTTP_REFERER'] ?? '');
redirect(!empty($ref['path']) ? basename($ref['path']) . (isset($ref['query']) ? '?' . $ref['query'] : '') : 'guide.php?id=' . $orgId);
