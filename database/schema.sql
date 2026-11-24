-- =============================================================
-- FeedbackHub - Customer Feedback System
-- Database schema + demo data (MySQL / MariaDB)
-- Import in phpMyAdmin: Import tab -> choose this file -> Go
-- =============================================================

CREATE DATABASE IF NOT EXISTS feedback_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE feedback_system;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS replies, feedback, categories, login_attempts, activity_logs, users;
SET FOREIGN_KEY_CHECKS = 1;

-- Users: customers ("user" role) and administrators ("admin" role)
CREATE TABLE users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name     VARCHAR(100) NOT NULL,
    email         VARCHAR(150) NOT NULL UNIQUE,
    phone         VARCHAR(20)  NULL,
    password_hash VARCHAR(255) NOT NULL,          -- bcrypt hash, never the plain password
    role          ENUM('user','admin') NOT NULL DEFAULT 'user',
    is_active     TINYINT(1) NOT NULL DEFAULT 1,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login    DATETIME NULL
) ENGINE=InnoDB;

CREATE TABLE categories (
    id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(60) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE feedback (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    subject     VARCHAR(150) NOT NULL,
    rating      TINYINT UNSIGNED NOT NULL CHECK (rating BETWEEN 1 AND 5),
    message     TEXT NOT NULL,
    status      ENUM('Pending','In Review','Resolved') NOT NULL DEFAULT 'Pending',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_feedback_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_feedback_category FOREIGN KEY (category_id) REFERENCES categories(id),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

CREATE TABLE replies (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    feedback_id INT UNSIGNED NOT NULL,
    admin_id    INT UNSIGNED NOT NULL,
    message     TEXT NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reply_feedback FOREIGN KEY (feedback_id) REFERENCES feedback(id) ON DELETE CASCADE,
    CONSTRAINT fk_reply_admin FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Brute-force protection: failed logins are counted here
CREATE TABLE login_attempts (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email        VARCHAR(150) NOT NULL,
    ip_address   VARCHAR(45) NOT NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email_time (email, attempted_at),
    INDEX idx_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB;

-- Audit trail of important and security-related events
CREATE TABLE activity_logs (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NULL,
    action     VARCHAR(50) NOT NULL,
    details    VARCHAR(500) NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_action (action),
    INDEX idx_log_created (created_at)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- Demo data
-- Admin login:  admin@feedbackhub.test / Admin@123
-- User login:   demo@feedbackhub.test  / User@1234
-- CHANGE THESE PASSWORDS BEFORE GOING LIVE.
-- ---------------------------------------------------------------
INSERT INTO categories (name) VALUES
('Product Quality'), ('Customer Service'), ('Delivery'), ('Pricing'), ('Website / App'), ('Other');

INSERT INTO users (full_name, email, phone, password_hash, role, created_at) VALUES
('System Administrator', 'admin@feedbackhub.test', '08000000001', '$2y$10$bTbbCjvuxDl1FgUTgdnxoOiE5/5jHM3UoZSX7DtLpmZbtNy.fL.xq', 'admin', NOW() - INTERVAL 200 DAY),
('Demo Customer',        'demo@feedbackhub.test',  '08012345678', '$2y$10$xHwqLvMKfelP/kEQcm/Ltubg2MUlI4tB1L2St.s6YNU2WBdejQIJG', 'user',  NOW() - INTERVAL 180 DAY),
('Chiamaka Obi',         'chiamaka@example.com',   '08023456789', '$2y$10$xHwqLvMKfelP/kEQcm/Ltubg2MUlI4tB1L2St.s6YNU2WBdejQIJG', 'user',  NOW() - INTERVAL 150 DAY),
('Tunde Adeyemi',        'tunde@example.com',      '08034567890', '$2y$10$xHwqLvMKfelP/kEQcm/Ltubg2MUlI4tB1L2St.s6YNU2WBdejQIJG', 'user',  NOW() - INTERVAL 120 DAY),
('Aisha Bello',          'aisha@example.com',      '08045678901', '$2y$10$xHwqLvMKfelP/kEQcm/Ltubg2MUlI4tB1L2St.s6YNU2WBdejQIJG', 'user',  NOW() - INTERVAL 90 DAY);

INSERT INTO feedback (user_id, category_id, subject, rating, message, status, created_at) VALUES
(2, 3, 'Late delivery of my order',          2, 'My order arrived three days after the promised date and nobody told me about the delay.', 'Resolved',  NOW() - INTERVAL 160 DAY),
(3, 2, 'Very helpful support agent',         5, 'The agent on the phone solved my problem in five minutes. Excellent service.',          'Resolved',  NOW() - INTERVAL 140 DAY),
(4, 1, 'Product stopped working',            1, 'The blender I bought stopped working after one week of normal use.',                     'Resolved',  NOW() - INTERVAL 125 DAY),
(5, 4, 'Prices are fair',                    4, 'Your prices are better than most shops in my area. Keep it up.',                          'Resolved',  NOW() - INTERVAL 100 DAY),
(2, 5, 'Checkout page is slow',              3, 'The checkout page takes a long time to load on my phone with mobile data.',              'In Review', NOW() - INTERVAL 80 DAY),
(3, 1, 'Good packaging',                     4, 'Items were well packaged and nothing was damaged.',                                       'Resolved',  NOW() - INTERVAL 65 DAY),
(4, 2, 'No reply to my email',               2, 'I sent an email to support two weeks ago and I have not received any reply.',            'In Review', NOW() - INTERVAL 45 DAY),
(5, 3, 'Fast delivery in Lagos',             5, 'I ordered in the morning and received it the same evening. Impressive.',                 'Resolved',  NOW() - INTERVAL 30 DAY),
(2, 6, 'Suggestion: add pay on delivery',    4, 'Please add a pay-on-delivery option for customers outside Lagos.',                       'Pending',   NOW() - INTERVAL 12 DAY),
(3, 5, 'Password reset email not received',  2, 'I tried to reset my password but the email never came.',                                 'Pending',   NOW() - INTERVAL 5 DAY),
(4, 4, 'Delivery fee too high',              3, 'The delivery fee to Enugu is almost half the price of the item.',                        'Pending',   NOW() - INTERVAL 2 DAY);

INSERT INTO replies (feedback_id, admin_id, message, created_at) VALUES
(1, 1, 'We apologise for the delay. A refund of your delivery fee has been processed.', NOW() - INTERVAL 158 DAY),
(3, 1, 'Sorry about this. A replacement blender has been sent to you.',               NOW() - INTERVAL 123 DAY),
(5, 1, 'Thanks for reporting this. Our developers are optimising the checkout page.', NOW() - INTERVAL 78 DAY);
