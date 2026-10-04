-- Tourism Company App — database schema (MySQL / MariaDB)
-- Based on the UML class diagram: Trip, TravelOrganiser, Tourist, Administrator,
-- Complaints, SystemReport, Rating, Photo, Payment (+ supporting tables).

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS follows, favorites, audit_log, email_log, password_resets, contact_messages, settings,
  system_reports, complaints, photos, ratings, payments, bookings, trip_stops, trips,
  organizer_profiles, users;

SET FOREIGN_KEY_CHECKS = 1;

-- Tourists, travel organisers and administrators share one account table (role-based access)
CREATE TABLE users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  role          ENUM('tourist','organizer','admin') NOT NULL,
  full_name     VARCHAR(120) NOT NULL,
  email         VARCHAR(160) NOT NULL UNIQUE,
  phone         VARCHAR(40)  NULL,
  dob           DATE         NULL,
  nationality   VARCHAR(80)  NULL,
  language      VARCHAR(40)  NULL,
  password_hash VARCHAR(255) NOT NULL,
  status        ENUM('active','pending','rejected','suspended') NOT NULL DEFAULT 'active',
  ui_lang       ENUM('en','ar') NOT NULL DEFAULT 'en',
  avatar        VARCHAR(255) NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Extra professional information for travel organisers (Fig 4 / Fig 10)
CREATE TABLE organizer_profiles (
  user_id        INT PRIMARY KEY,
  university     VARCHAR(160) NULL,
  training       TEXT NULL,
  skills         TEXT NULL,
  language_skills TEXT NULL,
  admin_note     TEXT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE trips (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  organizer_id   INT NOT NULL,
  title          VARCHAR(160) NOT NULL,
  destination    VARCHAR(160) NOT NULL,
  description    TEXT NOT NULL,
  itinerary      TEXT NULL,
  start_date     DATE NOT NULL,
  end_date       DATE NOT NULL,
  price          DECIMAL(10,2) NOT NULL,
  discount_pct   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  capacity       INT NOT NULL,
  language       VARCHAR(80) NOT NULL DEFAULT 'English',
  lat            DECIMAL(9,6) NOT NULL,
  lng            DECIMAL(9,6) NOT NULL,
  transport_info TEXT NULL,
  includes       VARCHAR(255) NULL,
  excludes       VARCHAR(255) NULL,
  cover_image    VARCHAR(255) NULL,
  status         ENUM('draft','published','cancelled') NOT NULL DEFAULT 'published',
  followers_notified TINYINT(1) NOT NULL DEFAULT 0,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT chk_discount CHECK (discount_pct <= 50),
  CONSTRAINT chk_price CHECK (price >= 0),
  FOREIGN KEY (organizer_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Places visited during a trip (Fig 7 flyer: "Jezzine waterfall", "Bkassine", ...) and map pins
CREATE TABLE trip_stops (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  trip_id       INT NOT NULL,
  name          VARCHAR(160) NOT NULL,
  kind          ENUM('place','hotel','restaurant','meeting') NOT NULL DEFAULT 'place',
  description   TEXT NULL,
  entrance_info VARCHAR(255) NULL,
  image         VARCHAR(255) NULL,
  lat           DECIMAL(9,6) NULL,
  lng           DECIMAL(9,6) NULL,
  sort_order    INT NOT NULL DEFAULT 0,
  FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- "Tourist joins Trip" (enrollment: name, phone, language — REQ-2)
CREATE TABLE bookings (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  trip_id      INT NOT NULL,
  tourist_id   INT NOT NULL,
  contact_name VARCHAR(120) NOT NULL,
  phone        VARCHAR(40)  NOT NULL,
  language     VARCHAR(40)  NOT NULL,
  seats        INT NOT NULL DEFAULT 1,
  unit_price   DECIMAL(10,2) NOT NULL,
  total        DECIMAL(10,2) NOT NULL,
  status       ENUM('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  cancelled_at DATETIME NULL,
  FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
  FOREIGN KEY (tourist_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX (trip_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE payments (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  booking_id  INT NOT NULL,
  tourist_id  INT NOT NULL,
  amount      DECIMAL(10,2) NOT NULL,
  method      ENUM('card','cash') NOT NULL,
  status      ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  card_last4  CHAR(4) NULL,
  bank_ref    VARCHAR(40) NULL,
  message     VARCHAR(255) NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  FOREIGN KEY (tourist_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Rating (score + comment), stored as pending until approved by an administrator
CREATE TABLE ratings (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  trip_id     INT NOT NULL,
  tourist_id  INT NOT NULL,
  score       TINYINT NOT NULL,
  comment     TEXT NULL,
  status      ENUM('pending','approved','hidden') NOT NULL DEFAULT 'pending',
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY one_review (trip_id, tourist_id),
  CONSTRAINT chk_score CHECK (score BETWEEN 1 AND 5),
  FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
  FOREIGN KEY (tourist_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Travel memories (Fig 8)
CREATE TABLE photos (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  trip_id    INT NOT NULL,
  user_id    INT NOT NULL,
  file_path  VARCHAR(255) NOT NULL,
  caption    VARCHAR(160) NULL,
  status     ENUM('visible','hidden') NOT NULL DEFAULT 'visible',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE complaints (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  sender_id    INT NOT NULL,
  trip_id      INT NULL,
  subject      VARCHAR(160) NOT NULL,
  message      TEXT NOT NULL,
  status       ENUM('open','resolved','escalated') NOT NULL DEFAULT 'open',
  response     TEXT NULL,
  responded_by INT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at  DATETIME NULL,
  FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE SET NULL,
  FOREIGN KEY (responded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE system_reports (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  report_type  VARCHAR(40) NOT NULL,
  generated_by INT NOT NULL,
  date_from    DATE NULL,
  date_to      DATE NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE settings (
  k VARCHAR(60) PRIMARY KEY,
  v TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE contact_messages (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(120) NOT NULL,
  email      VARCHAR(160) NOT NULL,
  message    TEXT NOT NULL,
  is_read    TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE password_resets (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used       TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Every email/notification the system sends is recorded here and shown in the user's inbox
CREATE TABLE email_log (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NULL,
  to_email   VARCHAR(160) NOT NULL,
  subject    VARCHAR(200) NOT NULL,
  body       TEXT NOT NULL,
  is_read    TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Audit trail of administrative / critical actions (SREQ-17, REQ-8)
CREATE TABLE audit_log (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NULL,
  action     VARCHAR(80) NOT NULL,
  details    TEXT NULL,
  ip         VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Trips a tourist saved with the heart button
CREATE TABLE favorites (
  user_id    INT NOT NULL,
  trip_id    INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, trip_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tourists following travel organizers (notified when the organizer publishes a new trip)
CREATE TABLE follows (
  follower_id  INT NOT NULL,
  organizer_id INT NOT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (follower_id, organizer_id),
  FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (organizer_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
