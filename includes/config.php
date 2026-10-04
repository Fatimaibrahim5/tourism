<?php
// ---- Database (XAMPP defaults) ----
define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_NAME', 'tourism_app');
define('DB_USER', 'root');
define('DB_PASS', '');

// ---- Application ----
define('APP_NAME', 'Travel Organization');
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_URL', 'uploads/');
define('MAX_UPLOAD_BYTES', 5 * 1024 * 1024);
define('MAX_DISCOUNT_PCT', 50);          // Business rule: discounts must not exceed 50%
define('CANCEL_MIN_DAYS', 2);            // REQ-13: cancel up to 2 days before the trip
define('PENDING_CARD_TTL_MIN', 30);      // Unpaid card bookings release their seats after 30 min

// Demo mode: emails are not really sent (XAMPP has no mail server). Every email is stored
// in the user's Inbox, and one-time codes / reset links are also shown on screen.
define('DEMO_MODE', true);
define('SEND_REAL_EMAIL', false);        // set true once php.ini SMTP is configured

date_default_timezone_set('Asia/Beirut');
