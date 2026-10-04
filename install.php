<?php
// One-time installer: creates the database, the tables (database/schema.sql) and demo data.
// Run it from the browser (http://localhost/tourism/install.php) or the command line (php install.php).
require_once __DIR__ . '/includes/config.php';

$cli = PHP_SAPI === 'cli';
if (!$cli) {
    $local = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
    if (!$local) { http_response_code(403); exit('The installer can only be run from the local machine.'); }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo '<!doctype html><meta charset="utf-8"><title>Install</title><body style="font-family:sans-serif;max-width:560px;margin:60px auto;line-height:1.6">'
           . '<h1>Tourism Company App — installer</h1><p>This will <b>create (or reset)</b> the database <code>' . DB_NAME . '</code> and load demo data. '
           . 'Any existing data in that database will be erased.</p><form method="post"><button style="padding:10px 18px;font-size:16px">Install / reset database</button></form></body>';
        exit;
    }
}

function out(string $msg): void {
    echo PHP_SAPI === 'cli' ? $msg . PHP_EOL : htmlspecialchars($msg) . '<br>';
    @ob_flush(); flush();
}

if (!$cli) echo '<!doctype html><meta charset="utf-8"><body style="font-family:sans-serif;max-width:720px;margin:40px auto;line-height:1.6"><h1>Installing…</h1>';

try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    out('Cannot connect to MySQL: ' . $e->getMessage());
    out('Start MySQL from the XAMPP Control Panel and try again.');
    exit(1);
}
$pdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$pdo->exec('USE `' . DB_NAME . '`');
out('Database ' . DB_NAME . ' ready.');

$sql = file_get_contents(__DIR__ . '/database/schema.sql');
$sql = preg_replace('/^\s*--.*$/m', '', $sql);
foreach (preg_split('/;\s*(\r?\n|$)/', $sql) as $stmt) {
    if (trim($stmt) !== '') $pdo->exec($stmt);
}
out('Tables created.');

// ---------- Demo data ----------
function ins(PDO $pdo, string $table, array $row): int {
    $cols = array_keys($row);
    $st = $pdo->prepare("INSERT INTO $table (" . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')');
    $st->execute(array_values($row));
    return (int)$pdo->lastInsertId();
}
$d = fn(int $days) => date('Y-m-d', strtotime("$days days"));
$dt = fn(int $days, string $time = '10:00:00') => date('Y-m-d', strtotime("$days days")) . ' ' . $time;

foreach ([
    'company_name' => 'FsM-co Travel Organization',
    'company_about' => "We are a Lebanese tourism company organising guided day trips and tours across Lebanon — from the waterfalls of Jezzine to the temples of Baalbek and the Cedars of God.\n\nAll our travel organizers are validated by our team, and every trip includes a licensed guide.",
    'company_email' => 'info@fsm-co.test',
    'company_phone' => '+961 1 234 567',
    'company_address' => 'Hamra Street, Beirut, Lebanon',
    'maintenance' => '0',
    'maintenance_msg' => '',
    'schema_version' => '3',
] as $k => $v) ins($pdo, 'settings', ['k' => $k, 'v' => $v]);

$hash = fn(string $p) => password_hash($p, PASSWORD_DEFAULT);
$admin = ins($pdo, 'users', ['role' => 'admin', 'full_name' => 'Site Administrator', 'email' => 'admin@tourism.test', 'phone' => '+961 1 234 567', 'password_hash' => $hash('Admin@123'), 'status' => 'active']);
$rami = ins($pdo, 'users', ['role' => 'organizer', 'full_name' => 'Rami Haddad', 'email' => 'rami@tourism.test', 'phone' => '+961 70 111 222', 'dob' => '1990-04-12', 'nationality' => 'Lebanon', 'language' => 'Arabic', 'password_hash' => $hash('Guide@123'), 'status' => 'active', 'created_at' => $dt(-120)]);
$lara = ins($pdo, 'users', ['role' => 'organizer', 'full_name' => 'Lara Khoury', 'email' => 'lara@tourism.test', 'phone' => '+961 71 333 444', 'dob' => '1993-09-02', 'nationality' => 'Lebanon', 'language' => 'French', 'password_hash' => $hash('Guide@123'), 'status' => 'active', 'created_at' => $dt(-100)]);
$karim = ins($pdo, 'users', ['role' => 'organizer', 'full_name' => 'Karim Nassar', 'email' => 'karim@tourism.test', 'phone' => '+961 76 555 666', 'dob' => '1998-01-20', 'nationality' => 'Lebanon', 'language' => 'English', 'password_hash' => $hash('Guide@123'), 'status' => 'pending', 'created_at' => $dt(-1)]);
ins($pdo, 'organizer_profiles', ['user_id' => $rami, 'university' => 'Lebanese University — BA in Tourism & Hospitality', 'training' => 'Licensed tour guide (Ministry of Tourism, 2016) · Wilderness First Aid', 'skills' => 'Hiking, Lebanese history, photography', 'language_skills' => 'Arabic, English, French']);
ins($pdo, 'organizer_profiles', ['user_id' => $lara, 'university' => 'Université Saint-Joseph — Archaeology', 'training' => 'Licensed tour guide (2018) · UNESCO heritage interpretation course', 'skills' => 'Archaeology, wine tasting, storytelling', 'language_skills' => 'French, English, Arabic, Spanish']);
ins($pdo, 'organizer_profiles', ['user_id' => $karim, 'university' => 'AUB — Environmental Science', 'training' => 'Mountain leader certificate (2024)', 'skills' => 'Trekking, bird watching', 'language_skills' => 'English, Arabic']);

$maya = ins($pdo, 'users', ['role' => 'tourist', 'full_name' => 'Maya Saad', 'email' => 'tourist@tourism.test', 'phone' => '+961 3 123 456', 'dob' => '1999-06-15', 'nationality' => 'Lebanon', 'language' => 'English', 'password_hash' => $hash('Tourist@123'), 'created_at' => $dt(-90)]);
$john = ins($pdo, 'users', ['role' => 'tourist', 'full_name' => 'John Miller', 'email' => 'john@tourism.test', 'phone' => '+1 555 0100', 'dob' => '1985-11-03', 'nationality' => 'United States', 'language' => 'English', 'password_hash' => $hash('Tourist@123'), 'created_at' => $dt(-60)]);
$sophie = ins($pdo, 'users', ['role' => 'tourist', 'full_name' => 'Sophie Martin', 'email' => 'sophie@tourism.test', 'phone' => '+33 6 12 34 56 78', 'dob' => '1995-02-28', 'nationality' => 'France', 'language' => 'French', 'password_hash' => $hash('Tourist@123'), 'created_at' => $dt(-50)]);

// Images taken from the SRS mockups
foreach (['jezzine.jpg' => 'trips', 'byblos.jpg' => 'trips', 'baalbek.jpg' => 'trips', 'cedars.jpg' => 'trips', 'jeita.jpg' => 'trips', 'tyre_beach.jpg' => 'trips',
          'jezzine2.jpg' => 'stops', 'bkassine.jpg' => 'stops', 'crusader.jpg' => 'stops', 'ksara.jpg' => 'stops', 'qadisha.jpg' => 'stops',
          'harissa.jpg' => 'stops', 'tyre_ruins.jpg' => 'stops'] as $file => $dir) {
    @mkdir(__DIR__ . "/uploads/$dir", 0775, true);
    copy(__DIR__ . "/database/seed_images/$file", __DIR__ . "/uploads/$dir/$file");
}

$trip = function (array $row, array $stops = []) use ($pdo): int {
    $id = ins($pdo, 'trips', $row);
    foreach ($stops as $i => $s) ins($pdo, 'trip_stops', $s + ['trip_id' => $id, 'sort_order' => $i + 1]);
    return $id;
};

$jezzine = $trip([
    'organizer_id' => $rami, 'title' => 'Jezzine Waterfall & Bkassine Pine Forest', 'destination' => 'Jezzine, South Lebanon',
    'description' => "Jezzine Waterfall is one of the tallest in Lebanon, cascading from a height of over 40 metres. It is surrounded by pine forests, red-roofed houses and dramatic cliffs, making it a popular summer destination. The area offers hiking trails, scenic viewpoints and artisanal shops famous for their handcrafted cutlery.\n\nWe continue to Bkassine, the largest pine forest in the Middle East, for a relaxed walk in the shade.",
    'itinerary' => "07:30 — Meeting point at Cola bridge, Beirut\n09:15 — Jezzine Waterfall & old town\n11:30 — Artisan cutlery workshop\n13:00 — Free time for lunch (not included)\n14:30 — Walk in Bkassine Pine Forest\n17:30 — Return to Beirut",
    'start_date' => $d(7), 'end_date' => $d(7), 'price' => 20, 'discount_pct' => 0, 'capacity' => 25, 'language' => 'English',
    'lat' => 33.5428, 'lng' => 35.5847, 'transport_info' => "Air-conditioned bus from Cola bridge (Beirut) at 07:30.\nReturn around 17:30.\nYou can also join us directly in Jezzine main square at 09:15.",
    'includes' => 'Transportation, guide, entrance fees', 'excludes' => 'Lunch', 'cover_image' => 'uploads/trips/jezzine.jpg', 'status' => 'published',
], [
    ['name' => 'Jezzine waterfall', 'kind' => 'place', 'entrance_info' => 'Free entrance', 'image' => 'uploads/stops/jezzine2.jpg', 'lat' => 33.5452, 'lng' => 35.5826],
    ['name' => 'Bkassine', 'kind' => 'place', 'entrance_info' => 'Entrance: free · N.B: additional activities cost money', 'description' => 'The largest pine forest in the Middle East, with walking and biking trails.', 'image' => 'uploads/stops/bkassine.jpg', 'lat' => 33.5617, 'lng' => 35.5586],
    ['name' => 'Riverside restaurant, Jezzine', 'kind' => 'restaurant', 'description' => 'Optional lunch stop — Lebanese mezze, about $15 per person.', 'lat' => 33.5400, 'lng' => 35.5900],
    ['name' => 'Meeting point — Cola bridge, Beirut', 'kind' => 'meeting', 'lat' => 33.8777, 'lng' => 35.4950],
]);

$byblos = $trip([
    'organizer_id' => $lara, 'title' => 'Byblos Old Souks & Crusader Citadel', 'destination' => 'Byblos (Jbeil)',
    'description' => "Byblos is one of the oldest continuously inhabited cities in the world and a UNESCO World Heritage site. Walk through the old souks, visit the Crusader citadel, the Phoenician temples and the picturesque fishing port.",
    'itinerary' => "09:00 — Departure from Beirut\n10:00 — Citadel & archaeological site\n12:30 — Old souks & port\n14:00 — Seafood lunch by the harbour (optional)\n16:30 — Return",
    'start_date' => $d(12), 'end_date' => $d(12), 'price' => 35, 'discount_pct' => 10, 'capacity' => 20, 'language' => 'French',
    'lat' => 34.1210, 'lng' => 35.6480, 'transport_info' => "Minivan from Beirut (Charles Helou station) at 09:00.\nParking available near the citadel for those who come by car.",
    'includes' => 'Transportation, guide, citadel ticket', 'excludes' => 'Lunch, souvenirs', 'cover_image' => 'uploads/trips/byblos.jpg', 'status' => 'published',
], [
    ['name' => 'Byblos Citadel', 'kind' => 'place', 'entrance_info' => 'Ticket included', 'image' => 'uploads/stops/crusader.jpg', 'lat' => 34.1198, 'lng' => 35.6455],
    ['name' => 'Old souks & fishing port', 'kind' => 'place', 'entrance_info' => 'Free', 'image' => 'uploads/trips/byblos.jpg', 'lat' => 34.1225, 'lng' => 35.6468],
]);

$baalbek = $trip([
    'organizer_id' => $rami, 'title' => 'Baalbek Roman Temples & Ksara Winery', 'destination' => 'Baalbek, Bekaa Valley',
    'description' => "Discover the colossal Roman temples of Jupiter and Bacchus in Baalbek — among the best preserved in the world — then taste local wines in the caves of Ksara, Lebanon's oldest winery.",
    'itinerary' => "08:00 — Departure from Beirut\n10:30 — Baalbek temples guided visit\n13:30 — Lunch in Zahle\n15:30 — Ksara winery caves & tasting\n18:00 — Return",
    'start_date' => $d(20), 'end_date' => $d(20), 'price' => 45, 'discount_pct' => 0, 'capacity' => 30, 'language' => 'English',
    'lat' => 34.0069, 'lng' => 36.2039, 'transport_info' => "Coach from Beirut (Dora roundabout) at 08:00 via the Dahr el Baidar road.",
    'includes' => 'Transportation, guide, entrance tickets, wine tasting', 'excludes' => 'Lunch', 'cover_image' => 'uploads/trips/baalbek.jpg', 'status' => 'published',
], [
    ['name' => 'Temples of Baalbek', 'kind' => 'place', 'entrance_info' => 'Ticket included', 'image' => 'uploads/trips/baalbek.jpg', 'lat' => 34.0069, 'lng' => 36.2039],
    ['name' => 'Château Ksara', 'kind' => 'place', 'entrance_info' => 'Tasting included', 'image' => 'uploads/stops/ksara.jpg', 'lat' => 33.8270, 'lng' => 35.8920],
]);

$cedars = $trip([
    'organizer_id' => $lara, 'title' => 'Cedars of God & Qadisha Valley Hike', 'destination' => 'Bcharre, North Lebanon',
    'description' => "A two-day mountain escape: the ancient Cedars of God forest, the Gibran Museum and a hike down into the Holy Valley of Qadisha with its rock-cut monasteries.",
    'itinerary' => "Day 1 — Cedars forest, Gibran Museum, night in Bcharre\nDay 2 — Qadisha Valley hike (moderate, 5 h), Qannoubine monastery, return in the evening",
    'start_date' => $d(30), 'end_date' => $d(31), 'price' => 120, 'discount_pct' => 0, 'capacity' => 14, 'language' => 'English',
    'lat' => 34.2436, 'lng' => 36.0486, 'transport_info' => "4x4 minibus from Beirut at 07:00 on day 1. Bring hiking shoes!",
    'includes' => 'Transportation, guide, 1 night hotel with breakfast, museum ticket', 'excludes' => 'Lunches and dinner', 'cover_image' => 'uploads/trips/cedars.jpg', 'status' => 'published',
], [
    ['name' => 'Cedars of God', 'kind' => 'place', 'entrance_info' => 'Donation at the entrance', 'image' => 'uploads/trips/cedars.jpg', 'lat' => 34.2436, 'lng' => 36.0486],
    ['name' => 'Qadisha Valley', 'kind' => 'place', 'entrance_info' => 'Free', 'image' => 'uploads/stops/qadisha.jpg', 'lat' => 34.2700, 'lng' => 35.9500],
    ['name' => 'Hotel in Bcharre', 'kind' => 'hotel', 'description' => 'Family-run mountain hotel, twin rooms.', 'lat' => 34.2510, 'lng' => 36.0110],
]);

$jeita = $trip([
    'organizer_id' => $rami, 'title' => 'Jeita Grotto & Harissa', 'destination' => 'Jeita, Keserwan',
    'description' => "Explore the spectacular limestone caves of Jeita — by boat in the lower grotto and on foot in the upper grotto — then ride the téléférique up to Our Lady of Lebanon in Harissa for a panoramic view of Jounieh bay.",
    'itinerary' => "09:30 — Departure\n10:15 — Jeita Grotto\n13:00 — Téléférique to Harissa\n15:30 — Return",
    'start_date' => $d(5), 'end_date' => $d(5), 'price' => 40, 'discount_pct' => 20, 'capacity' => 12, 'language' => 'Arabic',
    'lat' => 33.9434, 'lng' => 35.6410, 'transport_info' => "Minivan from Beirut at 09:30. Téléférique tickets included.",
    'includes' => 'Transportation, guide, grotto & téléférique tickets', 'excludes' => 'Lunch', 'cover_image' => 'uploads/trips/jeita.jpg', 'status' => 'published',
], [
    ['name' => 'Jeita Grotto', 'kind' => 'place', 'entrance_info' => 'Ticket included (no photos inside)', 'image' => 'uploads/trips/jeita.jpg', 'lat' => 33.9434, 'lng' => 35.6410],
    ['name' => 'Our Lady of Lebanon, Harissa', 'kind' => 'place', 'entrance_info' => 'Free', 'image' => 'uploads/stops/harissa.jpg', 'lat' => 33.9817, 'lng' => 35.6514],
]);

$tyre = $trip([
    'organizer_id' => $lara, 'title' => 'Tyre Beach & Ancient Ruins', 'destination' => 'Tyre (Sour), South Lebanon',
    'description' => "Visit the Roman hippodrome and the Al-Mina archaeological site, then relax on the sandy beaches of the Tyre Coast Nature Reserve.",
    'itinerary' => "08:30 — Departure\n10:30 — Al-Bass hippodrome & necropolis\n12:30 — Old port & lunch\n14:00 — Beach time\n17:30 — Return",
    'start_date' => $d(45), 'end_date' => $d(45), 'price' => 30, 'discount_pct' => 0, 'capacity' => 25, 'language' => 'English',
    'lat' => 33.2705, 'lng' => 35.1964, 'transport_info' => "Bus from Beirut (Cola) at 08:30.",
    'includes' => 'Transportation, guide, site tickets', 'excludes' => 'Lunch, beach umbrella', 'cover_image' => 'uploads/trips/tyre_beach.jpg', 'status' => 'published',
], [
    ['name' => 'Al-Bass hippodrome & ancient ruins', 'kind' => 'place', 'entrance_info' => 'Ticket included', 'description' => 'Roman hippodrome, colonnaded avenue and necropolis — a UNESCO World Heritage site.', 'image' => 'uploads/stops/tyre_ruins.jpg', 'lat' => 33.2710, 'lng' => 35.2090],
    ['name' => 'Tyre beach', 'kind' => 'place', 'entrance_info' => 'Free · umbrellas extra', 'description' => 'Sandy beach in the Tyre Coast Nature Reserve.', 'image' => 'uploads/trips/tyre_beach.jpg', 'lat' => 33.2620, 'lng' => 35.2000],
]);

$batroun = $trip([
    'organizer_id' => $rami, 'title' => 'Batroun Coastal Day', 'destination' => 'Batroun, North Lebanon',
    'description' => "Phoenician wall, old souk, St. Stephen's cathedral, fresh lemonade and a sunset by the sea.",
    'itinerary' => "10:00 — Old town walk\n13:00 — Fish lunch\n15:00 — Beach\n18:00 — Sunset & return",
    'start_date' => $d(-20), 'end_date' => $d(-20), 'price' => 25, 'discount_pct' => 0, 'capacity' => 20, 'language' => 'English',
    'lat' => 34.2553, 'lng' => 35.6581, 'transport_info' => 'Minivan from Beirut at 09:00.', 'includes' => 'Transportation, guide', 'excludes' => 'Lunch', 'status' => 'published',
], [['name' => 'Phoenician Wall', 'kind' => 'place', 'entrance_info' => 'Free', 'lat' => 34.2560, 'lng' => 35.6570]]);

$anjar = $trip([
    'organizer_id' => $lara, 'title' => 'Anjar Umayyad City', 'destination' => 'Anjar, Bekaa',
    'description' => 'The unique Umayyad city of Anjar, a UNESCO site, followed by a trout lunch at the springs.',
    'start_date' => $d(-40), 'end_date' => $d(-40), 'price' => 30, 'discount_pct' => 0, 'capacity' => 20, 'language' => 'French',
    'lat' => 33.7260, 'lng' => 35.9300, 'transport_info' => 'Coach from Beirut at 08:00.', 'includes' => 'Transportation, guide, ticket', 'excludes' => 'Lunch', 'status' => 'published',
]);

$trip([
    'organizer_id' => $rami, 'title' => 'Chouf Cedar Reserve (draft)', 'destination' => 'Barouk, Chouf',
    'description' => 'Draft trip — hike in the Shouf Biosphere Reserve and visit Beiteddine palace.',
    'start_date' => $d(60), 'end_date' => $d(60), 'price' => 35, 'capacity' => 20, 'language' => 'English',
    'lat' => 33.6890, 'lng' => 35.6800, 'status' => 'draft',
]);
$pdo->exec("UPDATE trips SET followers_notified = 1 WHERE status = 'published'");
out('Trips created.');

$book = function (int $trip, int $tourist, string $name, string $phone, string $lang, int $seats, float $unit, string $status, string $method, string $payStatus, int $daysAgo) use ($pdo, $dt) {
    $id = ins($pdo, 'bookings', ['trip_id' => $trip, 'tourist_id' => $tourist, 'contact_name' => $name, 'phone' => $phone, 'language' => $lang,
        'seats' => $seats, 'unit_price' => $unit, 'total' => $unit * $seats, 'status' => $status, 'created_at' => $dt(-$daysAgo)]);
    ins($pdo, 'payments', ['booking_id' => $id, 'tourist_id' => $tourist, 'amount' => $unit * $seats, 'method' => $method, 'status' => $payStatus,
        'card_last4' => $method === 'card' ? '4242' : null, 'bank_ref' => $method === 'card' && $payStatus === 'paid' ? 'BNK-' . strtoupper(bin2hex(random_bytes(5))) : null,
        'message' => $payStatus === 'paid' ? ($method === 'card' ? 'Authorized' : 'Cash received') : null, 'created_at' => $dt(-$daysAgo)]);
    return $id;
};
$book($jezzine, $maya, 'Maya Saad', '+961 3 123 456', 'English', 2, 20, 'confirmed', 'card', 'paid', 3);
$book($jezzine, $sophie, 'Sophie Martin', '+33 6 12 34 56 78', 'French', 1, 20, 'confirmed', 'card', 'paid', 2);
$book($jeita, $john, 'John Miller', '+1 555 0100', 'English', 2, 32, 'pending', 'cash', 'pending', 0);
$book($byblos, $sophie, 'Sophie Martin', '+33 6 12 34 56 78', 'French', 2, 31.5, 'confirmed', 'card', 'paid', 4);
$book($baalbek, $john, 'John Miller', '+1 555 0100', 'English', 1, 45, 'confirmed', 'cash', 'paid', 6);
$book($batroun, $maya, 'Maya Saad', '+961 3 123 456', 'English', 1, 25, 'confirmed', 'card', 'paid', 35);
$book($batroun, $john, 'John Miller', '+1 555 0100', 'English', 2, 25, 'confirmed', 'cash', 'paid', 33);
$book($batroun, $sophie, 'Sophie Martin', '+33 6 12 34 56 78', 'French', 1, 25, 'confirmed', 'card', 'paid', 30);
$book($anjar, $maya, 'Maya Saad', '+961 3 123 456', 'English', 1, 30, 'confirmed', 'card', 'paid', 55);
$book($anjar, $sophie, 'Sophie Martin', '+33 6 12 34 56 78', 'French', 1, 30, 'confirmed', 'card', 'paid', 50);
$book($cedars, $maya, 'Maya Saad', '+961 3 123 456', 'English', 1, 120, 'cancelled', 'card', 'refunded', 10);

ins($pdo, 'ratings', ['trip_id' => $batroun, 'tourist_id' => $john, 'score' => 4, 'comment' => 'Beautiful old town and great fish lunch. The bus was a bit late in the morning.', 'status' => 'approved', 'created_at' => $dt(-18)]);
ins($pdo, 'ratings', ['trip_id' => $batroun, 'tourist_id' => $sophie, 'score' => 5, 'comment' => 'Rami is a fantastic guide — très bien organisé !', 'status' => 'approved', 'created_at' => $dt(-17)]);
ins($pdo, 'ratings', ['trip_id' => $anjar, 'tourist_id' => $sophie, 'score' => 5, 'comment' => 'Lara knows everything about the Umayyads. Highly recommended.', 'status' => 'approved', 'created_at' => $dt(-38)]);
ins($pdo, 'ratings', ['trip_id' => $anjar, 'tourist_id' => $maya, 'score' => 4, 'comment' => 'Great trip, the trout lunch was delicious!', 'status' => 'pending', 'created_at' => $dt(-1)]);


ins($pdo, 'complaints', ['sender_id' => $john, 'trip_id' => $batroun, 'subject' => 'Bus was 40 minutes late', 'message' => 'The minivan arrived 40 minutes late at the meeting point and nobody informed us. Please make sure this does not happen again.', 'status' => 'open', 'created_at' => $dt(-18)]);
foreach ([[$maya, $rami], [$sophie, $rami], [$sophie, $lara], [$john, $lara]] as [$f, $o]) ins($pdo, 'follows', ['follower_id' => $f, 'organizer_id' => $o]);
ins($pdo, 'contact_messages', ['name' => 'Nour Fayad', 'email' => 'nour@example.com', 'message' => 'Hello, do you organise private trips for school groups of about 40 students?', 'created_at' => $dt(-2)]);

ins($pdo, 'email_log', ['user_id' => $maya, 'to_email' => 'tourist@tourism.test', 'subject' => 'Booking confirmed: Jezzine Waterfall & Bkassine Pine Forest', 'body' => "Hello Maya Saad,\n\nYour booking is confirmed!\n\nTrip: Jezzine Waterfall & Bkassine Pine Forest\nDate: " . date('d/m/Y', strtotime('+7 days')) . "\nSeats: 2\nAmount paid: $40.00 (card •••• 4242)", 'created_at' => $dt(-3)]);
ins($pdo, 'email_log', ['user_id' => $admin, 'to_email' => 'admin@tourism.test', 'subject' => 'New travel organizer to validate: Karim Nassar', 'body' => "A new travel organizer submitted their information:\n\nName: Karim Nassar\nEmail: karim@tourism.test\nUniversity study: AUB — Environmental Science\nTraining / licenses: Mountain leader certificate (2024)\nSkills: Trekking, bird watching", 'created_at' => $dt(-1)]);
ins($pdo, 'audit_log', ['user_id' => $admin, 'action' => 'install', 'details' => 'Database installed with demo data', 'ip' => 'local']);
out('Demo bookings, reviews and complaints created.');

out('');
out('Installation complete. Demo accounts:');
out('  Administrator   admin@tourism.test   / Admin@123   (a one-time code is shown on screen in demo mode)');
out('  Organizer       rami@tourism.test    / Guide@123');
out('  Organizer       lara@tourism.test    / Guide@123');
out('  Organizer (pending approval) karim@tourism.test / Guide@123');
out('  Tourist         tourist@tourism.test / Tourist@123');
out('  Tourist         john@tourism.test    / Tourist@123');
if (!$cli) echo '<p><a href="index.php" style="font-size:18px">→ Open the application</a></p><p style="color:#b45309">For security, delete or rename install.php once you are done.</p>';
