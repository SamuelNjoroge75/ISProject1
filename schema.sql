-- ISProject1 database. Create it with:   sudo mysql < schema.sql
-- (The database user is created separately, see README.md, so no password is stored here.)
CREATE DATABASE IF NOT EXISTS ISProject1 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ISProject1;

CREATE TABLE IF NOT EXISTS departments (
  id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(30) NOT NULL UNIQUE,
  name VARCHAR(60) NOT NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO departments (slug, name) VALUES
  ('software', 'Software Development'),
  ('support',  'IT Support & Infrastructure'),
  ('security', 'Cybersecurity'),
  ('data',     'Data & Analytics'),
  ('sales',    'Sales & Marketing'),
  ('hr',       'Human Resources'),
  ('finance',  'Finance & Admin');

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(30) NOT NULL UNIQUE,
  email VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  department_id TINYINT UNSIGNED NOT NULL,
  role ENUM('user','admin') NOT NULL DEFAULT 'user',
  last_login DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_department FOREIGN KEY (department_id) REFERENCES departments(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS login_attempts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(30) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  success TINYINT(1) NOT NULL,
  attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ip_time (ip, attempted_at),
  INDEX idx_user_time (username, attempted_at)
) ENGINE=InnoDB;

-- One row per submitted feedback form.
--   rating:      Q1, overall experience, 1 (rough) to 5 (amazing)
--   recommend:   Q2, would you recommend working here to a friend?
--   improvement: Q3, what would you most like us to improve?
--   user_id is NULL when the person chose to send the feedback anonymously.
CREATE TABLE IF NOT EXISTS feedback (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  department_id TINYINT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NULL,
  rating TINYINT UNSIGNED NOT NULL,
  recommend ENUM('definitely','probably','not_sure','no') NOT NULL,
  improvement VARCHAR(500) NOT NULL,
  anonymous TINYINT(1) NOT NULL DEFAULT 0,
  submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT chk_rating CHECK (rating BETWEEN 1 AND 5),
  CONSTRAINT fk_feedback_department FOREIGN KEY (department_id) REFERENCES departments(id),
  CONSTRAINT fk_feedback_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_dept_time (department_id, submitted_at)
) ENGINE=InnoDB;
