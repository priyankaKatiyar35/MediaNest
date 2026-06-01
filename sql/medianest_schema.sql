-- =====================================================================
-- MediaNest — unified-login + features migration
-- Safe to run repeatedly. Uses IF NOT EXISTS / IGNORE patterns.
-- Target: MySQL 5.7+ / MariaDB 10.2+
-- =====================================================================

-- Pick your database. Change if yours is named differently.
-- (Backticks because `s&p` contains an ampersand.)
USE `s&p`;

-- ---------------------------------------------------------------------
-- 1. USERS — the single auth table for users AND admins.
--    The role column is the new piece. Default = 'user'.
--    To make someone admin, run:  UPDATE users SET role='admin' WHERE email='you@example.com';
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email           VARCHAR(190) NOT NULL,
  password_hash   VARCHAR(255) NOT NULL,
  full_name       VARCHAR(150) DEFAULT NULL,
  group_name      VARCHAR(100) DEFAULT NULL,
  role            ENUM('user','admin') NOT NULL DEFAULT 'user',
  last_login      DATETIME DEFAULT NULL,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- If users table existed already without 'role', add it. Ignore the duplicate-column error.
ALTER TABLE users ADD COLUMN role ENUM('user','admin') NOT NULL DEFAULT 'user' AFTER group_name;

-- ---------------------------------------------------------------------
-- 2. LOGIN ATTEMPTS — rate-limit table used by auth/auth.php
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_attempts (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip            VARCHAR(45) NOT NULL,
  email         VARCHAR(190) DEFAULT NULL,
  success       TINYINT(1) NOT NULL DEFAULT 0,
  attempted_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ip_time (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 3. VIDEO CATEGORIES — new. The "type" classification for videos.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS video_categories (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name         VARCHAR(120) NOT NULL,
  slug         VARCHAR(140) NOT NULL,
  description  VARCHAR(500) DEFAULT NULL,
  sort_order   INT NOT NULL DEFAULT 0,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed a few common categories. INSERT IGNORE so re-runs are safe.
INSERT IGNORE INTO video_categories (name, slug, description, sort_order) VALUES
  ('Training',  'training',  'Course videos with knowledge-check quizzes.', 1),
  ('Events',    'events',    'Event recordings and highlights.',            2),
  ('Tutorials', 'tutorials', 'How-tos and walk-throughs.',                  3),
  ('Webinars',  'webinars',  'Recorded webinars and sessions.',             4);

-- ---------------------------------------------------------------------
-- 4. VIDEO TABLE — add category_id column. Idempotent.
--    If you don't have this table yet, create it.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS video (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(255) NOT NULL,        -- stored filename
  title       VARCHAR(200) NOT NULL,
  des         TEXT,
  category_id INT UNSIGNED DEFAULT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE video ADD COLUMN category_id INT UNSIGNED DEFAULT NULL AFTER des;
ALTER TABLE video ADD KEY idx_category (category_id);

-- ---------------------------------------------------------------------
-- 5. PHOTO EVENT ALBUMS — tbl_album already exists; ensure description
--    and optional event_date columns exist for the new UI.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tbl_album (
  albumid     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(200) NOT NULL,
  adesc       TEXT,
  image       VARCHAR(255) DEFAULT NULL,    -- cover thumbnail filename
  event_date  DATE DEFAULT NULL,
  status      VARCHAR(20) NOT NULL DEFAULT 'process',
  date        DATETIME DEFAULT NULL,
  PRIMARY KEY (albumid),
  KEY idx_status (status),
  FULLTEXT KEY ft_namedesc (name, adesc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE tbl_album ADD COLUMN event_date DATE DEFAULT NULL AFTER image;
-- Make sure search FULLTEXT index exists (ignored if already there)
ALTER TABLE tbl_album ADD FULLTEXT KEY ft_namedesc (name, adesc);

-- ---------------------------------------------------------------------
-- 6. PHOTOS WITHIN ALBUMS — tbl_gallery
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tbl_gallery (
  gid       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  aid       INT UNSIGNED NOT NULL,
  gimages   VARCHAR(255) NOT NULL,
  caption   VARCHAR(255) DEFAULT NULL,
  status    VARCHAR(20) NOT NULL DEFAULT 'process',
  PRIMARY KEY (gid),
  KEY idx_aid (aid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE tbl_gallery ADD COLUMN caption VARCHAR(255) DEFAULT NULL AFTER gimages;

-- ---------------------------------------------------------------------
-- 7. DOCUMENT FOLDERS — recursive (parent_folder_id)
--    Your existing schema; just ensuring it's consistent.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS folders (
  albumid          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name             VARCHAR(200) NOT NULL,
  adesc            TEXT,
  parent_folder_id INT UNSIGNED DEFAULT NULL,
  folder_image     VARCHAR(255) DEFAULT NULL,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (albumid),
  KEY idx_parent (parent_folder_id),
  FULLTEXT KEY ft_name_desc (name, adesc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE folders ADD FULLTEXT KEY ft_name_desc (name, adesc);

-- ---------------------------------------------------------------------
-- 8. DOCUMENT FILES
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS files (
  file_id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  folder_id   INT UNSIGNED NOT NULL,
  file_name   VARCHAR(255) NOT NULL,
  file_path   VARCHAR(500) NOT NULL,
  file_desc   VARCHAR(255) DEFAULT NULL,
  video_link  VARCHAR(500) DEFAULT NULL,
  uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (file_id),
  KEY idx_folder (folder_id),
  FULLTEXT KEY ft_filedesc (file_name, file_desc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE files ADD FULLTEXT KEY ft_filedesc (file_name, file_desc);

-- ---------------------------------------------------------------------
-- 9. BOOTSTRAP ADMIN USER (optional — change credentials before running!)
--    Password hash below is for "admin123". Generate a real one with:
--    php -r "echo password_hash('YOUR_PASSWORD', PASSWORD_DEFAULT);"
-- ---------------------------------------------------------------------
INSERT IGNORE INTO users (email, password_hash, full_name, role) VALUES
  ('admin@medianest.local',
   '$2y$10$wH8XlBYwI6jL3/8.dwLbA.qB3CN3GcsXh3KePqRSvBQGKjEC4LUUu',
   'Site Administrator',
   'admin');

-- =====================================================================
-- DONE. Verify with:
--   SELECT id, email, role FROM users;
--   SELECT * FROM video_categories;
-- =====================================================================
