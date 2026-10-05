# 🇱🇧 Lebanon Tourism Booking Web Application

A full-stack tourism booking web application for discovering and booking guided trips across Lebanon.

The application provides separate experiences for **Tourists, Travel Organizers, and Administrators**, with features including trip discovery, interactive maps, booking and payment simulation, reviews, favorites, notifications, reporting, and administration.

**This repository contains my web application implementation based on the requirements, UML design, and UI specifications developed for a Software Engineering course project.**

![Landing page](docs/screenshots/01-landing.jpg)

## 🌐 Live Demo

**Live application:** https://lebanontrip.infinityfreeapp.com/

[![Live demo](https://img.shields.io/badge/Live%20demo-online-brightgreen?style=for-the-badge)](https://lebanontrip.infinityfreeapp.com/)

### Demo Accounts

| Role             | Email                                               | Password    |
| ---------------- | --------------------------------------------------- | ----------- |
| Tourist          | [tourist@tourism.test](mailto:tourist@tourism.test) | Tourist@123 |
| Travel Organizer | [rami@tourism.test](mailto:rami@tourism.test)       | Guide@123   |
| Administrator    | [admin@tourism.test](mailto:admin@tourism.test)     | Admin@123   |

The live demo runs in **demo mode**. Payments use a simulated bank gateway and notifications are delivered through the application's internal inbox.

> **Note:** The free hosting environment may be slow on the first visit.

---

## 🛠️ Technologies

* **Backend:** PHP 8
* **Database:** MySQL / MariaDB
* **Frontend:** HTML5, CSS3, JavaScript
* **Maps:** Leaflet + OpenStreetMap
* **Architecture:** PHP-based web application without a framework
* **Development environment:** XAMPP

---

## 📸 Screenshots

### Welcome experience

| Landing page | Intro video | Login at the end of the video |
|---|---|---|
| ![Landing page](docs/screenshots/01-landing.jpg) | ![Intro video](docs/screenshots/02-intro-video.jpg) | ![Login over the video](docs/screenshots/03-intro-login.jpg) |

### Tourist

| Interactive map | Trip details |
|---|---|
| ![Interactive map](docs/screenshots/04-map.jpg) | ![Trip details](docs/screenshots/06-trip-details.jpg) |
| **Places visited during the trip** | **Booking** |
| ![Places to visit](docs/screenshots/07-trip-places.jpg) | ![Booking](docs/screenshots/08-booking.jpg) |
| **Tourist profile** | **Following organizers and their newest trips** |
| ![Tourist profile](docs/screenshots/09-tourist-profile.jpg) | ![Following](docs/screenshots/10-following.jpg) |
| **Search by destination or organizer** | **Security settings** |
| ![Search](docs/screenshots/05-search-organizer.jpg) | ![Security settings](docs/screenshots/13-settings-password.jpg) |

### Travel Organizer

| Public organizer profile | Organizer information |
|---|---|
| ![Organizer profile](docs/screenshots/11-organizer-page.jpg) | ![Organizer information](docs/screenshots/12-organizer-about.jpg) |
| **Organizer dashboard** | **Trip management with map location picker** |
| ![Organizer dashboard](docs/screenshots/15-organizer-dashboard.jpg) | ![Trip management](docs/screenshots/16-trip-editor.jpg) |

### Administrator

| Administrator dashboard | Reports |
|---|---|
| ![Administrator dashboard](docs/screenshots/17-admin-dashboard.jpg) | ![Reports](docs/screenshots/18-admin-reports.jpg) |
| **Organizer approvals** | **Arabic / RTL interface** |
| ![Organizer approvals](docs/screenshots/19-admin-guides.jpg) | ![Arabic interface](docs/screenshots/14-arabic.jpg) |

### Mobile views

![Mobile views](docs/screenshots/mobile.jpg)

---

## ✨ Main Features

### 👤 Tourist

* User registration and login
* Password recovery
* Browse trips by map or list
* Search by destination or organizer
* Filter trips by date, price, and duration
* View detailed trip information and itinerary
* Interactive map with trip locations
* Book trips and select number of seats
* Simulated card or cash payments
* Printable booking receipt
* Booking cancellation and refund handling
* Rate and review completed trips
* Favorite trips
* Follow travel organizers
* Travel memories photo album
* Complaints and in-app notifications
* Profile and security settings
* English and Arabic interface with RTL support

### 🧭 Travel Organizer

* Organizer registration with qualifications
* Administrator approval workflow
* Create, edit, publish, and unpublish trips
* Select trip locations using an interactive map
* Add visited places, hotels, and restaurants
* Manage discounts
* View and manage bookings
* Record cash payments
* Public organizer profile
* Followers and ratings
* Notifications to followers when new trips are published

### 👨‍💼 Administrator

* Administrator dashboard
* Two-factor authentication using one-time codes
* Approve or reject organizer applications
* User management
* Review and photo moderation
* Complaint management
* Trip management
* Booking and payment reports
* Ratings and user reports
* CSV export
* Print reports to PDF
* Maintenance mode
* Audit logging
* Company information and contact management

---

## 🗺️ Interactive Map

The application uses **Leaflet and OpenStreetMap** to provide an interactive map experience.

Users can:

* Explore upcoming trips geographically
* View trip locations
* See places visited during a trip
* View hotels and restaurants
* Select locations when creating a trip

---

## 🔐 Security

Security was considered throughout the application, including:

* Password hashing using PHP's `password_hash()`
* PDO prepared statements
* Output escaping against XSS
* CSRF protection on forms
* Session ID regeneration after login
* HttpOnly and SameSite cookies
* Role-based access control
* Login throttling after failed attempts
* Secure password reset tokens
* File upload validation
* Randomized uploaded filenames
* Protection against script execution inside the uploads directory
* No storage of CVV information
* Only the last four card digits are stored
* Administrative audit logging

---

## 🗄️ Database

The application uses a relational MySQL/MariaDB database containing **17 tables** covering areas such as:

* Users
* Trips
* Trip stops
* Bookings
* Payments
* Ratings
* Photos
* Complaints
* Favorites
* Follows
* Reports
* Audit logs

The project includes database installation and demo data to make local setup easier.

---

## 📁 Project Structure

```text
├── index.php, login.php, home.php, trip.php, ...
├── org_*.php
├── admin_*.php
├── includes/
│   ├── config
│   ├── database
│   ├── helpers
│   ├── layout
│   └── translations
├── assets/
│   ├── css
│   ├── js
│   └── video
├── database/
│   ├── schema.sql
│   └── seed_images
├── uploads/
├── docs/
│   └── screenshots
└── install.php
```

---

## 🚀 Running the Project Locally

### Requirements

* XAMPP
* PHP 8+
* MySQL or MariaDB
* Modern web browser

### Installation

1. Clone or download the repository.
2. Place the project inside:

```text
C:\xampp\htdocs\tourism
```

3. Start **Apache** and **MySQL** from XAMPP.
4. Open:

```text
http://localhost/tourism/install.php
```

5. Run the installation process.
6. Open:

```text
http://localhost/tourism/
```

7. Use the demo accounts listed above.

Database configuration is located in:

```text
includes/config.php
```

---

## 🧪 Demo Payment Cards

The application includes a simulated payment gateway for testing.

| Card Number           | Result           |
| --------------------- | ---------------- |
| `4242 4242 4242 4242` | Payment accepted |
| `4000 0000 0000 0002` | Payment declined |
| `4000 0000 0000 0119` | Bank timeout     |

Any future expiry date and any CVV can be used in demo mode.

---

## 🎓 Project Background

This application was developed as the **web implementation of a Software Engineering project** at the **Lebanese University, Faculty of Sciences V (2025–2026)**.

The original academic project included the following design deliverables:

* Software Requirements Specification (SRS)
* UML diagrams
* User interface mockups

I then used these specifications as the foundation for **independently implementing the functional web application**, including the frontend, backend, database integration, authentication and authorization, business rules, interactive maps, booking workflow, simulated payments, administration features, and security mechanisms.

The project therefore demonstrates the transition from **software requirements and system design to a working full-stack web application**.

---

## 👩‍💻 My Contribution

I implemented the web application and integrated its main components, including:

* Frontend interface development
* PHP backend development
* MySQL/MariaDB database integration
* Authentication and role-based authorization
* Tourist, organizer, and administrator workflows
* Booking and payment logic
* Interactive map functionality
* Reviews, favorites, follows, and notifications
* Administrative dashboards and reporting
* Security mechanisms
* Arabic/English localization and RTL support
* Local deployment and testing

This project was developed as a portfolio and academic software engineering project to strengthen practical full-stack development skills.

---

## 📌 Project Goals

The main goals of the project were to practice:

* Full-stack web development
* Relational database design
* Software requirements implementation
* Authentication and authorization
* Secure web application development
* Business logic implementation
* REST-style application workflows
* Interactive web interfaces
* Software testing and debugging
* Translating system design into a working application
