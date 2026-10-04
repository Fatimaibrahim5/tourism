# Tourism Company App (Travel Organization)

A web application built from the SRS document *C4_TourismCompanyApp* (I3301 Software Engineering).
It uses **HTML, CSS, JavaScript, PHP 8 and MySQL** (no framework) and runs on **XAMPP**.

## 1. Installation (XAMPP)

1. Copy the whole `tourism` folder into `C:\xampp\htdocs\` so you have `C:\xampp\htdocs\tourism\`.
2. Open the **XAMPP Control Panel** and start **Apache** and **MySQL**.
3. Open <http://localhost/tourism/install.php> and click **Install / reset database**.
   This creates the `tourism_app` database (from `database/schema.sql`) and loads the demo data.
4. Open <http://localhost/tourism/>.


Database credentials are in `includes/config.php` (XAMPP defaults: user `root`, empty password).

## 2. Demo accounts

| Role | Email | Password |
|---|---|---|
| Administrator | admin@tourism.test | Admin@123 |
| Travel Organizer | rami@tourism.test | Guide@123 |
| Travel Organizer | lara@tourism.test | Guide@123 |
| Travel Organizer (pending approval) | karim@tourism.test | Guide@123 |
| Tourist | tourist@tourism.test | Tourist@123 |
| Tourist | john@tourism.test / sophie@tourism.test | Tourist@123 |

Administrator login needs a one-time code (MFA). In demo mode the code is shown on screen and saved to the admin inbox.

**Test cards** (simulated Bank System), with any future expiry and any CVV:
`4242 4242 4242 4242` accepted · `4000 0000 0000 0002` declined · `4000 0000 0000 0119` bank timeout.

## 3. Screens (from the SRS mockups)

| Mockup | Page |
|---|---|
| Fig 1: choose Tourist / Travel Organizer, Contact Us, info button | `index.php`, `about.php` |
| Fig 2: Login | `login.php` (+ `forgot.php`, `reset.php`, `verify_otp.php`) |
| Fig 3/4: Tourist / Organizer registration | `register.php?role=tourist / organizer` |
| Fig 5: Map with pins, ratings, *info about tour / Join / Info about transportation*, bottom bar | `home.php` |
| Fig 6/7: Trip details & flyer (places, price, includes/excludes, Book NOW) | `trip.php` |
| Fig 8: Travel memories (photos) | `memories.php` |
| Fig 9: Tourist profile + tours joined | `profile.php` |
| Fig 10: Travel Organizer profile + rating + tours made | `profile.php` (own), `guide.php` (public) |

## 4. Requirements coverage

| Req. | Implementation |
|---|---|
| REQ-1, 18: tours on an interactive map | Leaflet + OpenStreetMap (`home.php`, `assets/js/map.js`); pins for places, hotels, restaurants and meeting points |
| REQ-2, 17: join with name, phone, language, plus confirmation | `book.php` → `bank.php` / cash → `receipt.php` + confirmation email |
| REQ-3: login with Tourist / Travel Organizer options | `login.php` tabs (+ separate admin login) |
| REQ-4, 5: organizer registers, admin validates by email | `register.php` (status *pending*, admins emailed) → `admin_guides.php` approve / reject / request info |
| REQ-6: card (Bank System) and cash | `bank.php` (simulated gateway; only last 4 digits are stored); cash is confirmed by the organizer in `org_bookings.php` |
| REQ-7: secure data | bcrypt password hashes, prepared statements, CSRF tokens, output escaping, role checks, upload validation, session hardening |
| REQ-8: company info button | `i` button on every page → `about.php` (no login needed) |
| REQ-9, 10: ratings/comments plus moderation | `trip.php` / `review.php` (only after attending, *pending* until approved) → `admin_reviews.php` (approve / hide / delete, plus photo moderation) |
| REQ-11: organizers manage trips | `org_dashboard.php`, `org_trip_form.php` (create, update, discount, publish, delete) |
| REQ-12: reports | `admin_reports.php` (bookings, payments, complaints, ratings, users; CSV export; print to PDF) |
| REQ-13: cancellation policy | `cancel_booking.php` (free up to 2 days before; card refunds) |
| REQ-14: English / Arabic with RTL | `includes/lang.php`, the عربي / EN switch |
| REQ-16: external services | Bank System (simulated), Maps (OpenStreetMap), email (`send_mail()`) |
| REQ-19: destination, description, guide, schedule | `trip.php` |
| REQ-20: swipe navigation | `assets/js/app.js` (swipe left/right between Profile, Inbox, Home, Search) |
| Safety: no overbooking | seat allocation inside a transaction with `SELECT … FOR UPDATE` |
| Business rules | discount ≤ 50%; a trip with active bookings can't be deleted except by admin override; reviews moderated |
| Admin flow | suspend / reactivate / delete users, complaints (respond / resolve / escalate), maintenance mode, audit log, admin MFA |

## 5. Notes

- **Emails**: XAMPP has no mail server, so every email the system sends is stored in `email_log` and shown in the recipient's **Inbox** (envelope icon). To send real emails, configure SMTP in `php.ini` and set `SEND_REAL_EMAIL` to `true` in `includes/config.php`.
- **Demo mode** (`DEMO_MODE` in `config.php`) shows admin login codes, reset links and test cards on screen. Set it to `false` for production.
- **HTTPS**: enable SSL in Apache for production (the session cookie switches to `secure` automatically).
- The map tiles and Google Fonts need an internet connection.
- After installation, delete or rename `install.php`. It only runs from localhost, but it resets the database.

## 6. Structure

```
tourism/
├── index.php, login.php, register.php, …   pages (tourist / organizer / admin)
├── org_*.php                               travel organizer pages
├── admin_*.php                             administrator pages
├── includes/  config, db, helpers, layout, translations (EN/AR)
├── assets/    css/style.css, js/app.js, js/map.js
├── database/  schema.sql, seed images
├── uploads/   user photos (scripts blocked by .htaccess)
└── install.php
```
