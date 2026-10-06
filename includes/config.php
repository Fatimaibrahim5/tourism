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

// ---- Demo accounts ----
// Accounts whose email ends with DEMO_DOMAIN (created by install.php) are public demo accounts:
// their admin login codes are shown on screen, their password/email cannot be changed, and a
// demo administrator only sees demo users and their data. Real accounts are never affected.
define('DEMO_MODE', true);
define('DEMO_DOMAIN', '@tourism.test');

// ---- Real email (SMTP) ----
// Needed for real users: password-reset links and administrator login codes are sent by email.
// Free example: Gmail (smtp.gmail.com, port 465, your Gmail address + a Google "App password").
// Leave SMTP_HOST empty to disable sending (emails then only appear in the in-app Inbox).
define('SMTP_HOST', '');
define('SMTP_PORT', 465);                // 465 = SSL, 587 = STARTTLS
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('SMTP_FROM', '');                 // usually the same as SMTP_USER
define('SMTP_FROM_NAME', 'Travel Organization');

date_default_timezone_set('Asia/Beirut');
