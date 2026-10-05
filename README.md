# 🇱🇧 Travel Organization: Tourism Booking Web App

A full-stack tourism platform for discovering and booking guided trips across Lebanon.
Tourists explore trips on an interactive map and book them, travel organizers publish and manage their tours, and administrators run the whole system.

**Tech stack:** PHP 8 · MySQL / MariaDB · JavaScript (vanilla) · HTML5 · CSS3 · Leaflet + OpenStreetMap. No framework.

### 🌐 [Live demo: lebanontrip.infinityfreeapp.com](https://lebanontrip.infinityfreeapp.com/)

[![Live demo](https://img.shields.io/badge/Live%20demo-online-brightgreen?style=for-the-badge)](https://lebanontrip.infinityfreeapp.com/)

Try it with a demo account:

| Role | Email | Password |
|---|---|---|
| Tourist | tourist@tourism.test | Tourist@123 |
| Travel organizer | rami@tourism.test | Guide@123 |
| Administrator | admin@tourism.test | Admin@123 (the one-time code is shown on screen) |

> The live demo runs in demo mode: payments go through a simulated bank (test card `4242 4242 4242 4242`) and emails arrive in the app's inbox. Free hosting can be slow on the first visit.

![Landing page](docs/screenshots/01-landing.jpg)

---

## ✨ Highlights

- **Three roles with role-based access:** Tourist, Travel Organizer and Administrator, each with its own interface.
- **Interactive map** of all upcoming trips, with pins for places, hotels, restaurants and meeting points.
- **Booking and payments:** card payments through a simulated bank gateway (approve / decline / timeout) or cash. Overbooking is prevented with database row locking.
- **Instagram-style profiles:** profile photos, stats, photo-grid tabs, ♥ favorites, and **following organizers**, with notifications when they publish a new trip.
- **Admin back-office:** guide approvals, review and photo moderation, complaints, user management, reports with CSV export, maintenance mode and an audit log.
- **Bilingual:** English and Arabic with a full **right-to-left** layout.
- **Mobile-first:** bottom navigation bar and swipe gestures between pages.
- **Intro video:** the first time a visitor chooses "Tourist", a video of Lebanon plays and the login box fades in over its last seconds.

---

## 📸 Screenshots

### Welcome experience
| Intro video | Login appears at the end |
|---|---|
| ![Intro video](docs/screenshots/02-intro-video.jpg) | ![Login over the video](docs/screenshots/03-intro-login.jpg) |

### Tourist
| Interactive map | Trip details |
|---|---|
| ![Map](docs/screenshots/04-map.jpg) | ![Trip details](docs/screenshots/06-trip-details.jpg) |
| **Places visited during the trip** | **Joining a trip** |
| ![Places to visit](docs/screenshots/07-trip-places.jpg) | ![Booking form](docs/screenshots/08-booking.jpg) |
| **Instagram-style profile** | **Following organizers & their newest trips** |
| ![Tourist profile](docs/screenshots/09-tourist-profile.jpg) | ![Following](docs/screenshots/10-following.jpg) |
| **Search by destination or organizer name** | **Password & security settings** |
| ![Search](docs/screenshots/05-search-organizer.jpg) | ![Settings](docs/screenshots/13-settings-password.jpg) |

### Travel organizer
| Public organizer page (follow, trips, reviews) | "About" tab: all the organizer's information |
|---|---|
| ![Organizer page](docs/screenshots/11-organizer-page.jpg) | ![About organizer](docs/screenshots/12-organizer-about.jpg) |
| **Trip management dashboard** | **Trip editor with map location picker** |
| ![Organizer dashboard](docs/screenshots/15-organizer-dashboard.jpg) | ![Trip editor](docs/screenshots/16-trip-editor.jpg) |

### Administrator
| Dashboard | Reports |
|---|---|
| ![Admin dashboard](docs/screenshots/17-admin-dashboard.jpg) | ![Reports](docs/screenshots/18-admin-reports.jpg) |
| **Guide approvals** | **Arabic (right-to-left)** |
| ![Guide approvals](docs/screenshots/19-admin-guides.jpg) | ![Arabic](docs/screenshots/14-arabic.jpg) |

### On a phone
![Mobile views](docs/screenshots/mobile.jpg)

---

## 🧩 Features

<details>
<summary><b>Tourist</b></summary>

- Register and log in; password recovery by email link
- Browse trips on a map or in a list; search by destination or **organizer name**; filter by date, price and duration
- Trip pages: itinerary, places to visit with photos, guide info, price and discount, includes/excludes, transportation
- Join a trip (name, phone, language, seats) and pay by card or cash; printable receipt
- Cancel up to 2 days before the trip (card payments are refunded automatically)
- Rate and review trips after attending (published after moderation)
- ♥ Favorites, follow organizers, "Travel memories" photo album for each trip
- Complaints with admin responses, and an in-app inbox for all notifications
- Profile photo, edit profile, and a password-strength meter with live checks
</details>

<details>
<summary><b>Travel organizer</b></summary>

- Registration with qualifications (university, licenses, skills), then validation by an administrator
- Create, edit, publish or unpublish trips, with the location picked on a map and places, hotels and restaurants as pins
- Apply discounts (business rule: 50% maximum)
- View bookings and record cash payments
- Public profile with followers, rating and reviews; followers are notified of every new trip
</details>

<details>
<summary><b>Administrator</b></summary>

- Login with **two-factor authentication** (one-time code)
- Approve, reject or request more information from organizer applicants
- Moderate reviews and photos; handle complaints (respond / resolve / escalate)
- Suspend, reactivate or delete users; add administrators
- Override-delete trips with bookings (bookings are cancelled and refunded, tourists notified)
- Reports (bookings, payments, ratings, complaints, users) with charts, CSV export and print-to-PDF
- Maintenance mode, company information, contact messages, audit log and outgoing-email log
</details>

---

## 🔒 Security

- Passwords hashed with bcrypt (`password_hash`); one-time tokens for password reset
- Prepared statements everywhere (PDO); all output escaped against XSS
- CSRF token on every form; session ID regenerated on login; HttpOnly / SameSite cookies
- Role-based access control on every page, plus login throttling after failed attempts
- File uploads validated by real image content, renamed randomly, and script execution blocked in `uploads/`
- Only the last 4 card digits are stored; the CVV is never stored
- Audit log of administrative and critical actions

---

## 🚀 Run your own copy (locally)

**Requirements:** [XAMPP](https://www.apachefriends.org/) (PHP 8 + MySQL/MariaDB).

1. Copy the project folder into `C:\xampp\htdocs\tourism`.
2. Start **Apache** and **MySQL** from the XAMPP Control Panel.
3. Open <http://localhost/tourism/install.php> and click **Install**. This creates the database and loads the demo data.
4. Open <http://localhost/tourism/>.

Database settings are in `includes/config.php` (XAMPP defaults: user `root`, no password).

Use the same demo accounts listed at the top. Test cards: `4242 4242 4242 4242` accepted · `4000 0000 0000 0002` declined · `4000 0000 0000 0119` bank timeout (any future expiry and any CVV).

> **Demo mode:** payments go through a simulated bank, and emails are delivered to an in-app inbox instead of being sent. Set `DEMO_MODE = false` in `includes/config.php` for production.

---

## 🗂 Project structure

```
├── index.php, login.php, home.php, trip.php, …   pages (tourist & shared)
├── org_*.php                                     travel organizer pages
├── admin_*.php                                   administrator pages
├── includes/      config, database, helpers, layout, translations (EN/AR)
├── assets/        css, js (app, map, profile, intro), intro video
├── database/      schema.sql and demo images
├── uploads/       user photos (scripts blocked)
├── docs/          screenshots
└── install.php    creates the database with demo data
```

The database has 17 tables (users, trips, trip stops, bookings, payments, ratings, photos, complaints, favorites, follows, reports, audit log and more). Existing databases are upgraded automatically when the app starts.

---

## 📄 Background

This web application is based on the requirements specification (SRS), UML design and UI mockups of a Software Engineering course project (I3301) at the **Lebanese University, Faculty of Sciences V (2025–2026)**.
