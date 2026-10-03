-- CampusIQ schema. InnoDB, utf8mb4, foreign keys.
-- Every table has id and created_at. Load with: bash database/reset.sh

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS ai_queries;
DROP TABLE IF EXISTS reports;
DROP TABLE IF EXISTS email_logs;
DROP TABLE IF EXISTS records;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS settings;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE students (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_no      VARCHAR(20)  NOT NULL,
    first_name      VARCHAR(80)  NOT NULL,
    last_name       VARCHAR(80)  NOT NULL,
    section         VARCHAR(20)  NOT NULL,
    email           VARCHAR(190) NOT NULL,
    guardian_name   VARCHAR(160) NOT NULL,
    guardian_email  VARCHAR(190) NOT NULL,
    status          ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_students_student_no (student_no),
    KEY idx_students_section (section)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id                     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role                   ENUM('staff', 'student', 'parent') NOT NULL,
    id_number              VARCHAR(30)  NOT NULL,
    name                   VARCHAR(160) NOT NULL,
    email                  VARCHAR(190) NOT NULL,
    password_hash          VARCHAR(255) NOT NULL,
    student_id             INT UNSIGNED NULL,
    staff_title            VARCHAR(100) NULL,
    notifications_seen_at  DATETIME NULL,
    created_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at             DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_id_number (id_number),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_student (student_id),
    CONSTRAINT fk_users_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE records (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id   INT UNSIGNED NOT NULL,
    type         ENUM('grade', 'attendance', 'library') NOT NULL,
    title        VARCHAR(150) NOT NULL,
    value        VARCHAR(100) NOT NULL,
    note         VARCHAR(255) NULL,
    recorded_by  INT UNSIGNED NULL,
    recorded_on  DATE NOT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_records_student_date (student_id, recorded_on),
    KEY idx_records_type_date (type, recorded_on),
    CONSTRAINT fk_records_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
    CONSTRAINT fk_records_user FOREIGN KEY (recorded_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE email_logs (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id   INT UNSIGNED NOT NULL,
    record_id    INT UNSIGNED NULL,
    trigger_key  VARCHAR(40)  NOT NULL,
    recipients   TEXT NOT NULL,
    subject      VARCHAR(200) NOT NULL,
    message      TEXT NOT NULL,
    status       ENUM('sent', 'failed', 'demo') NOT NULL,
    error        VARCHAR(255) NULL,
    sent_by      INT UNSIGNED NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_email_logs_student (student_id, created_at),
    KEY idx_email_logs_created (created_at),
    CONSTRAINT fk_email_logs_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
    CONSTRAINT fk_email_logs_record FOREIGN KEY (record_id) REFERENCES records (id) ON DELETE SET NULL,
    CONSTRAINT fk_email_logs_user FOREIGN KEY (sent_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reports (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id   INT UNSIGNED NOT NULL,
    report_type  ENUM('full', 'grade_slip', 'attendance', 'library') NOT NULL,
    date_from    DATE NULL,
    date_to      DATE NULL,
    sections     VARCHAR(100) NOT NULL,
    file_path    VARCHAR(255) NOT NULL,
    size_bytes   INT UNSIGNED NOT NULL DEFAULT 0,
    mode         ENUM('live', 'demo') NOT NULL,
    created_by   INT UNSIGNED NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_reports_student (student_id, created_at),
    KEY idx_reports_created (created_at),
    CONSTRAINT fk_reports_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
    CONSTRAINT fk_reports_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ai_queries (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NULL,
    question      TEXT NOT NULL,
    answer        TEXT NOT NULL,
    records_used  INT UNSIGNED NOT NULL DEFAULT 0,
    mode          ENUM('live', 'demo') NOT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ai_queries_created (created_at),
    CONSTRAINT fk_ai_queries_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key`       VARCHAR(80)  NOT NULL,
    value       VARCHAR(255) NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_settings_key (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
