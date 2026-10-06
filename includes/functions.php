<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lang.php';

// Defaults for settings added after the first release, so an older config.php keeps working
defined('DEMO_DOMAIN') || define('DEMO_DOMAIN', '@tourism.test');
defined('SMTP_HOST') || define('SMTP_HOST', '');
defined('SMTP_PORT') || define('SMTP_PORT', 465);
defined('SMTP_USER') || define('SMTP_USER', '');
defined('SMTP_PASS') || define('SMTP_PASS', '');
defined('SMTP_FROM') || define('SMTP_FROM', '');
defined('SMTP_FROM_NAME') || define('SMTP_FROM_NAME', 'Travel Organization');

// ---------- Session ----------
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

if (PHP_SAPI !== 'cli') ensure_schema();

// Language switch (?lang=en|ar), remembered in session and on the user account
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'ar'], true)) {
    $_SESSION['lang'] = $_GET['lang'];
    if (!empty($_SESSION['uid'])) q('UPDATE users SET ui_lang = ? WHERE id = ?', [$_GET['lang'], $_SESSION['uid']]);
}

// ---------- Output helpers ----------
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function money($amount): string { return '$' . number_format((float)$amount, 2); }

function fdate(?string $d, bool $time = false): string {
    if (!$d) return '';
    return date($time ? 'd/m/Y H:i' : 'd/m/Y', strtotime($d));
}

function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}

function self_url_without_lang(string $lang): string {
    $params = $_GET;
    $params['lang'] = $lang;
    return strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query($params);
}

// ---------- Flash messages ----------
function flash(string $type, string $msg): void { $_SESSION['flash'][] = [$type, $msg]; }

function take_flashes(): array {
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

// ---------- CSRF ----------
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function check_csrf(): void {
    if (!hash_equals(csrf_token(), $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('Invalid form token. Please go back and reload the page.');
    }
}

function field_error(array $errors, string $f): string {
    return isset($errors[$f]) ? '<div class="field-error">' . e($errors[$f]) . '</div>' : '';
}

function is_post(): bool { return $_SERVER['REQUEST_METHOD'] === 'POST'; }

function post(string $k, string $default = ''): string { return trim((string)($_POST[$k] ?? $default)); }

// ---------- Authentication / roles ----------
function current_user(bool $refresh = false): ?array {
    static $user = false;
    if ($user === false || $refresh) {
        $user = null;
        if (!empty($_SESSION['uid'])) {
            $user = q('SELECT * FROM users WHERE id = ?', [$_SESSION['uid']])->fetch() ?: null;
            // A suspended / deleted account is logged out on its next request
            if (!$user || in_array($user['status'], ['suspended', 'rejected'], true)) {
                $_SESSION = [];
                session_regenerate_id(true);
                $user = null;
            }
        }
    }
    return $user;
}

function uid(): ?int { return current_user()['id'] ?? null; }

function role(): ?string { return current_user()['role'] ?? null; }

function login_user(array $user): void {
    session_regenerate_id(true);
    $_SESSION['uid'] = $user['id'];
    $_SESSION['lang'] = $_SESSION['lang'] ?? ($user['ui_lang'] ?: 'en');
    current_user(true);
    audit('login', $user['role'] . ' ' . $user['email'], $user['id']);
}

function require_login(array $roles = []): array {
    $u = current_user();
    if (!$u) {
        flash('info', t('please_login'));
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'];
        redirect('login.php' . (count($roles) === 1 ? '?role=' . $roles[0] : ''));
    }
    if ($roles && !in_array($u['role'], $roles, true)) {
        audit('access_denied', $_SERVER['REQUEST_URI'], $u['id']);
        http_response_code(403);
        page_header(t('access_denied'));
        echo '<div class="card narrow"><h2>' . e(t('access_denied')) . '</h2><p>' . e(t('access_denied_msg')) . '</p>'
           . '<a class="btn" href="' . e(home_for_role()) . '">' . e(t('back_home')) . '</a></div>';
        page_footer();
        exit;
    }
    return $u;
}

function home_for_role(): string {
    return match (role()) {
        'admin' => 'admin_dashboard.php',
        'organizer' => 'org_dashboard.php',
        'tourist' => 'home.php',
        default => 'index.php',
    };
}

// ---------- Email / notifications ----------
// Every message is written to email_log (the in-app Inbox). Real sending is optional.
// Every message is written to email_log (the in-app Inbox). Real accounts also get it by real email
// when SMTP is configured; demo accounts never do (their addresses don't exist).
function send_mail(?int $userId, string $to, string $subject, string $body): bool {
    q('INSERT INTO email_log (user_id, to_email, subject, body) VALUES (?, ?, ?, ?)', [$userId, $to, $subject, $body]);
    if (is_demo_email($to) || !mail_configured()) return false;
    require_once __DIR__ . '/mailer.php';
    try {
        smtp_send($to, $subject, $body);
        return true;
    } catch (Throwable $ex) {
        audit('email_failed', $to . ': ' . $ex->getMessage());
        return false;
    }
}

function mail_configured(): bool { return SMTP_HOST !== '' && SMTP_USER !== ''; }

// ---------- Demo accounts vs real accounts ----------
function is_demo_email(?string $email): bool {
    return $email !== null && str_ends_with(mb_strtolower(trim($email)), DEMO_DOMAIN);
}

// Is the logged-in user a public demo account?
function is_demo_viewer(): bool {
    return current_user() !== null && is_demo_email(current_user()['email']);
}

// SQL condition that limits rows to demo users when a demo account is looking. Real accounts see everything.
// $emailCol is the users.email column of the person the row belongs to, e.g. "u.email".
function demo_scope(string $emailCol): string {
    return is_demo_viewer() ? " AND $emailCol LIKE '%" . DEMO_DOMAIN . "'" : '';
}

// May the current viewer see / act on data belonging to $email?
function can_access_user(?string $email): bool {
    return !is_demo_viewer() || is_demo_email($email);
}

// Stop a demo account from touching real data or system settings
function deny_demo(string $back): never {
    flash('error', t('demo_restricted'));
    redirect($back);
}

function notify_user(int $userId, string $subject, string $body): void {
    $u = q('SELECT email FROM users WHERE id = ?', [$userId])->fetch();
    if ($u) send_mail($userId, $u['email'], $subject, $body);
}

// Notify a user about something concerning $aboutEmail. A demo account never receives
// messages containing a real person's details (public demo accounts can be opened by anyone).
function notify_about(int $userId, ?string $aboutEmail, string $subject, string $body): void {
    $u = q('SELECT email FROM users WHERE id = ?', [$userId])->fetch();
    if (!$u || (is_demo_email($u['email']) && !is_demo_email($aboutEmail))) return;
    send_mail($userId, $u['email'], $subject, $body);
}

// $aboutEmail: the person the message is about. Messages about real people never reach demo admins.
function notify_admins(string $subject, string $body, ?string $aboutEmail = null): void {
    foreach (q("SELECT id, email FROM users WHERE role = 'admin' AND status = 'active'")->fetchAll() as $a) {
        if (is_demo_email($a['email']) && !is_demo_email($aboutEmail)) continue;
        send_mail($a['id'], $a['email'], $subject, $body);
    }
}

function unread_count(): int {
    if (!uid()) return 0;
    return (int)q('SELECT COUNT(*) FROM email_log WHERE user_id = ? AND is_read = 0', [uid()])->fetchColumn();
}

// ---------- Audit ----------
function audit(string $action, string $details = '', ?int $userId = null): void {
    q('INSERT INTO audit_log (user_id, action, details, ip) VALUES (?, ?, ?, ?)',
      [$userId ?? ($_SESSION['uid'] ?? null), $action, $details, $_SERVER['REMOTE_ADDR'] ?? 'cli']);
}

// ---------- Uploads ----------
// Validates real image content, size and type, stores under a random name. Returns relative path.
function upload_image(array $file, string $subdir): string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException(t('upload_failed'));
    if ($file['size'] > MAX_UPLOAD_BYTES) throw new RuntimeException(t('upload_too_big'));
    $info = @getimagesize($file['tmp_name']);
    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($types[$info[2]])) throw new RuntimeException(t('upload_bad_type'));
    $dir = UPLOAD_DIR . $subdir;
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $name = bin2hex(random_bytes(12)) . '.' . $types[$info[2]];
    if (!move_uploaded_file($file['tmp_name'], "$dir/$name")) throw new RuntimeException(t('upload_failed'));
    return UPLOAD_URL . "$subdir/$name";
}

function has_upload(string $field): bool {
    return isset($_FILES[$field]) && $_FILES[$field]['error'] !== UPLOAD_ERR_NO_FILE;
}

// ---------- Trip helpers ----------
function effective_price(array $trip): float {
    return round($trip['price'] * (100 - (int)$trip['discount_pct']) / 100, 2);
}

// Card bookings that were never paid release their seats after PENDING_CARD_TTL_MIN minutes
function expire_stale_bookings(): void {
    $stale = q("SELECT b.id, b.tourist_id FROM bookings b JOIN payments p ON p.booking_id = b.id
                WHERE b.status = 'pending' AND p.method = 'card' AND p.status IN ('pending','failed')
                  AND b.created_at < NOW() - INTERVAL " . PENDING_CARD_TTL_MIN . " MINUTE")->fetchAll();
    foreach ($stale as $b) {
        q("UPDATE bookings SET status = 'cancelled', cancelled_at = NOW() WHERE id = ?", [$b['id']]);
        q("UPDATE payments SET status = 'failed', message = 'Expired: payment not completed' WHERE booking_id = ? AND status = 'pending'", [$b['id']]);
    }
}

function seats_taken(int $tripId): int {
    return (int)q("SELECT COALESCE(SUM(seats),0) FROM bookings WHERE trip_id = ? AND status IN ('pending','confirmed')", [$tripId])->fetchColumn();
}

function trip_rating(int $tripId): array {
    $r = q("SELECT AVG(score) a, COUNT(*) c FROM ratings WHERE trip_id = ? AND status = 'approved'", [$tripId])->fetch();
    return [round((float)$r['a'], 1), (int)$r['c']];
}

function organizer_rating(int $orgId): array {
    $r = q("SELECT AVG(r.score) a, COUNT(*) c FROM ratings r JOIN trips t ON t.id = r.trip_id
            WHERE t.organizer_id = ? AND r.status = 'approved'", [$orgId])->fetch();
    return [round((float)$r['a'], 1), (int)$r['c']];
}

function stars(float $avg, bool $showNumber = true): string {
    $html = '<span class="stars" aria-label="' . e($avg) . ' / 5">';
    for ($i = 1; $i <= 5; $i++) {
        $cls = $avg >= $i ? 'on' : ($avg >= $i - 0.5 ? 'half' : '');
        $html .= '<i class="' . $cls . '">★</i>';
    }
    return $html . '</span>' . ($showNumber && $avg > 0 ? ' <b>' . number_format($avg, 1) . '</b>' : '');
}

// Cover image or a generated gradient placeholder
function trip_cover(array $trip, string $class = 'cover'): string {
    if (!empty($trip['cover_image'])) {
        return '<img class="' . $class . '" src="' . e($trip['cover_image']) . '" alt="' . e($trip['title']) . '" loading="lazy">';
    }
    $hue = crc32($trip['title']) % 360;
    return '<div class="' . $class . ' placeholder" style="--h:' . $hue . '"><span>' . e(mb_substr($trip['destination'], 0, 1)) . '</span></div>';
}

function trip_is_over(array $trip): bool { return $trip['end_date'] < date('Y-m-d'); }

function status_badge(string $status): string {
    return '<span class="badge b-' . e($status) . '">' . e(t('st_' . $status)) . '</span>';
}

// Profile photo (if uploaded) or the first letter of the name
function avatar_html(array $user, string $class = 'avatar'): string {
    if (!empty($user['avatar'])) {
        return '<img class="' . $class . ' has-photo" src="' . e($user['avatar']) . '" alt="' . e($user['full_name']) . '">';
    }
    return '<div class="' . $class . '">' . e(mb_strtoupper(mb_substr($user['full_name'], 0, 1))) . '</div>';
}

// ---------- Favorites ----------
function favorite_ids(): array {
    static $ids = null;
    if ($ids === null) {
        $ids = role() === 'tourist'
            ? array_map('intval', q('SELECT trip_id FROM favorites WHERE user_id = ?', [uid()])->fetchAll(PDO::FETCH_COLUMN))
            : [];
    }
    return $ids;
}

// Heart button; shown to tourists and guests (guests are asked to log in)
function fav_button(int $tripId, string $extra = ''): string {
    if (role() !== null && role() !== 'tourist') return '';
    $on = in_array($tripId, favorite_ids(), true);
    return '<form method="post" action="favorite.php" class="fav-form ' . $extra . '">' . csrf_field()
         . '<input type="hidden" name="trip" value="' . $tripId . '">'
         . '<button class="fav-btn' . ($on ? ' on' : '') . '" type="submit" aria-pressed="' . ($on ? 'true' : 'false') . '" title="'
         . e($on ? t('remove_favorite') : t('add_favorite')) . '"><span aria-hidden="true">' . ($on ? '♥' : '♡') . '</span></button></form>';
}

// ---------- Following travel organizers ----------
function following_ids(): array {
    static $ids = null;
    if ($ids === null) {
        $ids = role() === 'tourist'
            ? array_map('intval', q('SELECT organizer_id FROM follows WHERE follower_id = ?', [uid()])->fetchAll(PDO::FETCH_COLUMN))
            : [];
    }
    return $ids;
}

function followers_count(int $orgId): int {
    return (int)q('SELECT COUNT(*) FROM follows WHERE organizer_id = ?', [$orgId])->fetchColumn();
}

// Follow / Following button; shown to tourists and guests (guests are asked to log in)
function follow_button(int $orgId, string $extra = ''): string {
    if (role() !== null && role() !== 'tourist') return '';
    $on = in_array($orgId, following_ids(), true);
    return '<form method="post" action="follow.php" class="follow-form ' . $extra . '">' . csrf_field()
         . '<input type="hidden" name="organizer" value="' . $orgId . '">'
         . '<button class="btn follow-btn' . ($on ? ' on' : '') . '" type="submit" aria-pressed="' . ($on ? 'true' : 'false') . '">'
         . e($on ? t('following') : t('follow')) . '</button></form>';
}

// Tell an organizer's followers about a newly published trip (only once per trip)
function notify_followers(int $tripId): void {
    $trip = q("SELECT t.*, u.full_name AS org_name FROM trips t JOIN users u ON u.id = t.organizer_id
               WHERE t.id = ? AND t.status = 'published' AND t.followers_notified = 0 AND t.end_date >= CURDATE()", [$tripId])->fetch();
    if (!$trip) return;
    q('UPDATE trips SET followers_notified = 1 WHERE id = ?', [$tripId]);
    $followers = q("SELECT u.id, u.email, u.full_name FROM follows f JOIN users u ON u.id = f.follower_id
                    WHERE f.organizer_id = ? AND u.status = 'active'", [$trip['organizer_id']])->fetchAll();
    foreach ($followers as $f) {
        send_mail((int)$f['id'], $f['email'], "New trip by {$trip['org_name']}: {$trip['title']}",
            "Hello {$f['full_name']},

{$trip['org_name']}, whom you follow, just published a new trip:

"
            . "{$trip['title']}
📍 {$trip['destination']}
📅 " . fdate($trip['start_date']) . "
💲 " . money(effective_price($trip)) . " / person

"
            . "Open it here: trip.php?id={$trip['id']}");
    }
}

require_once __DIR__ . '/layout.php';

// ---------- Maintenance mode (admin use case step 7) ----------
$__page = basename($_SERVER['SCRIPT_NAME']);
if (PHP_SAPI !== 'cli' && setting('maintenance') === '1' && role() !== 'admin'
    && !in_array($__page, ['login.php', 'verify_otp.php', 'logout.php', 'install.php'], true)) {
    http_response_code(503);
    page_header(t('maintenance'));
    echo '<div class="card narrow center"><div class="big-icon">🛠️</div><h2>' . e(t('maintenance')) . '</h2><p>'
       . e(setting('maintenance_msg') ?: t('maintenance_msg')) . '</p><p class="muted"><a href="login.php?role=admin">'
       . e(t('admin_login')) . '</a></p></div>';
    page_footer();
    exit;
}
