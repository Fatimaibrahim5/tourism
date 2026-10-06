<?php
require_once __DIR__ . '/config.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER, DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
            $pdo->exec("SET time_zone = '" . date('P') . "'");   // same "today" as PHP
        } catch (PDOException $e) {
            http_response_code(500);
            echo '<div style="font-family:sans-serif;max-width:560px;margin:60px auto;padding:24px;border:1px solid #f3c2c2;background:#fff5f5;border-radius:12px">'
               . '<h2>Database not available</h2><p>Start MySQL from the XAMPP Control Panel, then open '
               . '<a href="install.php">install.php</a> once to create the database.</p></div>';
            exit;
        }
    }
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement {
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function setting(string $key, ?string $default = null): ?string {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (q('SELECT k, v FROM settings')->fetchAll() as $r) $cache[$r['k']] = $r['v'];
    }
    return $cache[$key] ?? $default;
}

function set_setting(string $key, string $value): void {
    q('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)', [$key, $value]);
}

// Upgrades a database created by an older version of install.php (no reinstall needed).
function ensure_schema(): void {
    $v = (int)setting('schema_version', '1');
    if ($v >= 3) return;
    if ($v < 2) upgrade_to_2();
    // v3: follow travel organizers
    if (!q("SHOW COLUMNS FROM trips LIKE 'followers_notified'")->fetch()) {
        db()->exec('ALTER TABLE trips ADD followers_notified TINYINT(1) NOT NULL DEFAULT 0 AFTER status');
        db()->exec("UPDATE trips SET followers_notified = 1 WHERE status = 'published'");
    }
    db()->exec('CREATE TABLE IF NOT EXISTS follows (
        follower_id INT NOT NULL, organizer_id INT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (follower_id, organizer_id),
        FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (organizer_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    set_setting('schema_version', '3');
}

function upgrade_to_2(): void {
    $hasAvatar = q("SHOW COLUMNS FROM users LIKE 'avatar'")->fetch();
    if (!$hasAvatar) db()->exec('ALTER TABLE users ADD avatar VARCHAR(255) NULL AFTER ui_lang');
    db()->exec('CREATE TABLE IF NOT EXISTS favorites (
        user_id INT NOT NULL, trip_id INT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, trip_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}
