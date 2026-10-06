<?php
// REQ-12 — reports for administrators: bookings, payments, complaints, ratings and user activity.
// Exportable to CSV; printable to PDF from the browser. Each generated report is logged in system_reports.
require __DIR__ . '/includes/functions.php';

$admin = require_login(['admin']);

$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : date('Y-01-01', strtotime('-1 year'));
$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : date('Y-m-d');
$range = [$from . ' 00:00:00', $to . ' 23:59:59'];

// A demo administrator only gets figures about demo accounts
$sU = demo_scope('u.email');   // the person the row belongs to
$sE = demo_scope('email');     // users table without alias

// ----- CSV export -----
$exports = [
    'bookings' => ["SELECT b.id, t.title AS trip, t.start_date, b.contact_name, u.email, b.phone, b.language, b.seats, b.unit_price, b.total, b.status, b.created_at
                    FROM bookings b JOIN trips t ON t.id = b.trip_id JOIN users u ON u.id = b.tourist_id WHERE b.created_at BETWEEN ? AND ?$sU ORDER BY b.created_at"],
    'payments' => ["SELECT p.id, p.booking_id, t.title AS trip, u.full_name AS tourist, p.amount, p.method, p.status, p.card_last4, p.bank_ref, p.message, p.created_at
                    FROM payments p JOIN bookings b ON b.id = p.booking_id JOIN trips t ON t.id = b.trip_id JOIN users u ON u.id = p.tourist_id WHERE p.created_at BETWEEN ? AND ?$sU ORDER BY p.created_at"],
    'complaints' => ["SELECT c.id, u.full_name AS sender, u.role, t.title AS trip, c.subject, c.message, c.status, c.response, c.created_at, c.resolved_at
                      FROM complaints c JOIN users u ON u.id = c.sender_id LEFT JOIN trips t ON t.id = c.trip_id WHERE c.created_at BETWEEN ? AND ?$sU ORDER BY c.created_at"],
    'ratings' => ["SELECT r.id, t.title AS trip, u.full_name AS tourist, r.score, r.comment, r.status, r.created_at
                   FROM ratings r JOIN trips t ON t.id = r.trip_id JOIN users u ON u.id = r.tourist_id WHERE r.created_at BETWEEN ? AND ?$sU ORDER BY r.created_at"],
    'users' => ["SELECT id, role, full_name, email, phone, nationality, language, status, created_at FROM users WHERE created_at BETWEEN ? AND ?$sE ORDER BY created_at"],
];
if (isset($_GET['export'], $exports[$_GET['export']])) {
    $type = $_GET['export'];
    q('INSERT INTO system_reports (report_type, generated_by, date_from, date_to) VALUES (?, ?, ?, ?)', ["csv:$type", $admin['id'], $from, $to]);
    audit('report_exported', "$type $from → $to");
    $rows = q($exports[$type][0], $range)->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"{$type}_{$from}_{$to}.csv\"");
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows Arabic correctly
    if ($rows) fputcsv($out, array_keys($rows[0]));
    foreach ($rows as $r) {
        // Neutralise spreadsheet formulas (CSV injection)
        fputcsv($out, array_map(fn($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v, $r));
    }
    exit;
}

if (isset($_GET['from'])) {
    q('INSERT INTO system_reports (report_type, generated_by, date_from, date_to) VALUES (?, ?, ?, ?)', ['overview', $admin['id'], $from, $to]);
}

// ----- Aggregates -----
$bk = q("SELECT b.status, COUNT(*) n, COALESCE(SUM(b.seats),0) seats, COALESCE(SUM(b.total),0) amount
         FROM bookings b JOIN users u ON u.id = b.tourist_id WHERE b.created_at BETWEEN ? AND ?$sU GROUP BY b.status", $range)->fetchAll(PDO::FETCH_UNIQUE);
$pay = q("SELECT p.method, p.status, COUNT(*) n, COALESCE(SUM(p.amount),0) amount
          FROM payments p JOIN users u ON u.id = p.tourist_id WHERE p.created_at BETWEEN ? AND ?$sU
          GROUP BY p.method, p.status ORDER BY p.method, p.status", $range)->fetchAll();
$paid = array_sum(array_map(fn($p) => $p['status'] === 'paid' ? $p['amount'] : 0, $pay));
$refunded = array_sum(array_map(fn($p) => $p['status'] === 'refunded' ? $p['amount'] : 0, $pay));
$cmp = q("SELECT c.status, COUNT(*) n FROM complaints c JOIN users u ON u.id = c.sender_id
          WHERE c.created_at BETWEEN ? AND ?$sU GROUP BY c.status", $range)->fetchAll(PDO::FETCH_KEY_PAIR);
$rt = q("SELECT AVG(r.score) a, COUNT(*) n FROM ratings r JOIN users u ON u.id = r.tourist_id
         WHERE r.status = 'approved' AND r.created_at BETWEEN ? AND ?$sU", $range)->fetch();
$byTrip = q("SELECT t.id, t.title, COUNT(b.id) n, COALESCE(SUM(b.seats),0) seats, COALESCE(SUM(b.total),0) amount,
                    (SELECT AVG(score) FROM ratings r WHERE r.trip_id = t.id AND r.status = 'approved') avg_score
             FROM trips t JOIN users o ON o.id = t.organizer_id
             LEFT JOIN bookings b ON b.trip_id = t.id AND b.status = 'confirmed' AND b.created_at BETWEEN ? AND ?
                  AND b.tourist_id IN (SELECT id FROM users WHERE 1=1$sE)
             WHERE 1=1" . demo_scope('o.email') . "
             GROUP BY t.id ORDER BY amount DESC, n DESC LIMIT 15", $range)->fetchAll();
$monthly = q("SELECT DATE_FORMAT(b.created_at, '%Y-%m') m, COUNT(*) n, COALESCE(SUM(b.total),0) amount
              FROM bookings b JOIN users u ON u.id = b.tourist_id
              WHERE b.status = 'confirmed' AND b.created_at BETWEEN ? AND ?$sU GROUP BY m ORDER BY m", $range)->fetchAll();
$users = q("SELECT role, COUNT(*) n FROM users WHERE created_at BETWEEN ? AND ?$sE GROUP BY role", $range)->fetchAll(PDO::FETCH_KEY_PAIR);
$logins = (int)q("SELECT COUNT(*) FROM audit_log a JOIN users u ON u.id = a.user_id
                  WHERE a.action = 'login' AND a.created_at BETWEEN ? AND ?$sU", $range)->fetchColumn();
$history = q("SELECT s.*, u.full_name FROM system_reports s JOIN users u ON u.id = s.generated_by WHERE 1=1$sU ORDER BY s.id DESC LIMIT 8")->fetchAll();

$maxTrip = max(1, ...array_map(fn($r) => (float)$r['amount'], $byTrip ?: [['amount' => 1]]));
$maxMonth = max(1, ...array_map(fn($r) => (int)$r['n'], $monthly ?: [['n' => 1]]));
$qs = http_build_query(['from' => $from, 'to' => $to]);

page_header(t('reports'), ['main_class' => 'wide']);
?>
<div class="page-head"><h1><?= e(t('reports')) ?></h1><button class="btn sm outline no-print" onclick="window.print()">🖨 <?= e(t('print_pdf')) ?></button></div>

<form class="card filters no-print" method="get">
  <div><label for="from"><?= e(t('date_from')) ?></label><input type="date" id="from" name="from" value="<?= e($from) ?>"></div>
  <div><label for="to"><?= e(t('date_to')) ?></label><input type="date" id="to" name="to" value="<?= e($to) ?>"></div>
  <div><button class="btn"><?= e(t('generate')) ?></button></div>
</form>
<p class="muted"><?= e(t('period')) ?>: <?= e(fdate($from)) ?> → <?= e(fdate($to)) ?></p>

<div class="grid grid-4">
  <div class="stat"><small><?= e(t('st_confirmed')) ?> <?= e(t('bookings')) ?></small><div class="num"><?= (int)($bk['confirmed']['n'] ?? 0) ?></div><span class="muted small"><?= (int)($bk['confirmed']['seats'] ?? 0) ?> <?= e(t('seats')) ?></span></div>
  <div class="stat"><small><?= e(t('st_pending')) ?> / <?= e(t('st_cancelled')) ?></small><div class="num"><?= (int)($bk['pending']['n'] ?? 0) ?> / <?= (int)($bk['cancelled']['n'] ?? 0) ?></div></div>
  <div class="stat"><small><?= e(t('revenue_paid')) ?></small><div class="num"><?= money($paid) ?></div><span class="muted small"><?= e(t('st_refunded')) ?>: <?= money($refunded) ?></span></div>
  <div class="stat"><small><?= e(t('avg_rating')) ?></small><div class="num"><?= $rt['n'] ? number_format((float)$rt['a'], 1) : '—' ?></div><span class="muted small"><?= e(t('n_reviews', ['n' => (int)$rt['n']])) ?></span></div>
  <div class="stat"><small><?= e(t('complaints')) ?></small><div class="num"><?= array_sum($cmp) ?></div><span class="muted small"><?= e(t('st_open')) ?> <?= (int)($cmp['open'] ?? 0) ?> · <?= e(t('st_escalated')) ?> <?= (int)($cmp['escalated'] ?? 0) ?> · <?= e(t('st_resolved')) ?> <?= (int)($cmp['resolved'] ?? 0) ?></span></div>
  <div class="stat"><small><?= e(t('new_users')) ?></small><div class="num"><?= array_sum($users) ?></div><span class="muted small"><?= e(t('role_tourist')) ?> <?= (int)($users['tourist'] ?? 0) ?> · <?= e(t('role_organizer')) ?> <?= (int)($users['organizer'] ?? 0) ?></span></div>
  <div class="stat"><small><?= e(t('logins')) ?></small><div class="num"><?= $logins ?></div></div>
</div>

<div class="grid grid-2 mt">
  <div class="card">
    <h2><?= e(t('revenue_by_trip')) ?></h2>
    <div class="bar-chart">
      <?php foreach ($byTrip as $r): ?>
        <div class="bar-row"><span class="small" title="<?= e($r['title']) ?>"><?= e(mb_strimwidth($r['title'], 0, 28, '…')) ?></span>
          <div class="bar"><span style="width:<?= round(100 * $r['amount'] / $maxTrip) ?>%"></span></div><b class="small right"><?= money($r['amount']) ?></b></div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="card">
    <h2><?= e(t('bookings_per_month')) ?></h2>
    <?php if (!$monthly): ?><p class="muted"><?= e(t('nothing_here')) ?></p><?php endif; ?>
    <div class="bar-chart">
      <?php foreach ($monthly as $r): ?>
        <div class="bar-row"><span class="small"><?= e($r['m']) ?></span><div class="bar"><span style="width:<?= round(100 * $r['n'] / $maxMonth) ?>%"></span></div><b class="small right"><?= (int)$r['n'] ?></b></div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="grid grid-2">
  <div class="card">
    <h2><?= e(t('payments')) ?></h2>
    <div class="table-wrap"><table>
      <thead><tr><th><?= e(t('payment_method')) ?></th><th><?= e(t('status')) ?></th><th>#</th><th><?= e(t('amount')) ?></th></tr></thead>
      <tbody><?php foreach ($pay as $p): ?><tr><td><?= $p['method'] === 'card' ? '💳 ' . e(t('pay_card')) : '💵 ' . e(t('pay_cash')) ?></td><td><?= status_badge($p['status']) ?></td><td><?= (int)$p['n'] ?></td><td><?= money($p['amount']) ?></td></tr><?php endforeach; ?></tbody>
    </table></div>
  </div>
  <div class="card">
    <h2><?= e(t('ratings_by_trip')) ?></h2>
    <div class="table-wrap"><table>
      <thead><tr><th><?= e(t('trip')) ?></th><th><?= e(t('bookings')) ?></th><th><?= e(t('rating')) ?></th></tr></thead>
      <tbody><?php foreach ($byTrip as $r): ?><tr><td class="small"><?= e($r['title']) ?></td><td><?= (int)$r['n'] ?></td><td><?= $r['avg_score'] ? stars((float)$r['avg_score']) : '—' ?></td></tr><?php endforeach; ?></tbody>
    </table></div>
  </div>
</div>

<div class="card no-print">
  <h2><?= e(t('export_csv')) ?></h2>
  <div class="row">
    <?php foreach (array_keys($exports) as $x): ?><a class="btn sm outline" href="?<?= e($qs) ?>&export=<?= $x ?>">⬇ <?= e(t('exp_' . $x)) ?></a><?php endforeach; ?>
  </div>
  <h3 class="mt"><?= e(t('report_history')) ?></h3>
  <ul class="small muted">
    <?php foreach ($history as $h): ?><li><?= e(fdate($h['created_at'], true)) ?> — <?= e($h['report_type']) ?> (<?= e(fdate($h['date_from'])) ?> → <?= e(fdate($h['date_to'])) ?>) · <?= e($h['full_name']) ?></li><?php endforeach; ?>
  </ul>
</div>
<?php page_footer();
