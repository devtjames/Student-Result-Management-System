-- ============================================================
-- Student Result Management System - Database Schema
-- ============================================================

CREATE DATABASE IF NOT EXISTS srms_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE srms_db;

-- -------------------------------------------------------
-- Admin users table
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS admins (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(50) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    full_name   VARCHAR(100) NOT NULL,
    email       VARCHAR(100) UNIQUE,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_login  DATETIME NULL
) ENGINE=InnoDB;

-- Default admin: username=admin | password=Admin@1234
INSERT INTO admins (username, password, full_name, email) VALUES
('admin', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin@srms.edu.ng');
-- NOTE: Replace the hash above by running: password_hash('Admin@1234', PASSWORD_BCRYPT, ['cost'=>12])

-- -------------------------------------------------------
-- Students table
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS students (
    matric_no       VARCHAR(20) PRIMARY KEY,
    full_name       VARCHAR(150) NOT NULL,
    department      VARCHAR(100) NOT NULL,
    faculty         VARCHAR(100) DEFAULT NULL,
    programme       ENUM('B.Sc','HND','B.Eng','B.Tech','B.A','B.Ed') DEFAULT 'B.Sc',
    admission_year  YEAR DEFAULT NULL,
    email           VARCHAR(100) DEFAULT NULL,
    phone           VARCHAR(20) DEFAULT NULL,
    gender          ENUM('M','F','Other') DEFAULT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- Student portal accounts
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS student_accounts (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    matric_no   VARCHAR(20) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    email       VARCHAR(100) DEFAULT NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_login  DATETIME NULL,
    FOREIGN KEY (matric_no) REFERENCES students(matric_no) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- Courses table
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS courses (
    course_code     VARCHAR(20) PRIMARY KEY,
    course_title    VARCHAR(200) NOT NULL,
    course_unit     TINYINT UNSIGNED NOT NULL DEFAULT 3,
    department      VARCHAR(100) DEFAULT NULL,
    semester        TINYINT UNSIGNED DEFAULT NULL COMMENT '1=First, 2=Second',
    level           SMALLINT UNSIGNED DEFAULT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- Raw Results table (one row per student per course per session)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS results_raw (
    id                  BIGINT AUTO_INCREMENT PRIMARY KEY,
    matric_no           VARCHAR(20) NOT NULL,
    course_code         VARCHAR(20) NOT NULL,
    academic_session    VARCHAR(10) NOT NULL  COMMENT 'e.g. 2023/2024',
    semester            TINYINT UNSIGNED NOT NULL COMMENT '1=First, 2=Second',
    level               SMALLINT UNSIGNED NOT NULL COMMENT '100,200,300,400,500',
    score               DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    is_carryover        TINYINT(1) NOT NULL DEFAULT 0,
    uploaded_at         DATETIME DEFAULT CURRENT_TIMESTAMP,
    uploaded_by         INT DEFAULT NULL,
    UNIQUE KEY uq_result (matric_no, course_code, academic_session, semester, level),
    FOREIGN KEY (matric_no)   REFERENCES students(matric_no)  ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (course_code) REFERENCES courses(course_code) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES admins(id)           ON DELETE SET NULL
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- Upload log table
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS upload_logs (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    admin_id        INT NOT NULL,
    filename        VARCHAR(255) NOT NULL,
    total_rows      INT DEFAULT 0,
    inserted        INT DEFAULT 0,
    updated         INT DEFAULT 0,
    skipped         INT DEFAULT 0,
    errors          TEXT DEFAULT NULL,
    status          ENUM('processing','completed','failed') DEFAULT 'processing',
    uploaded_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- Useful indexes for performance
-- -------------------------------------------------------
CREATE INDEX idx_results_matric     ON results_raw(matric_no);
CREATE INDEX idx_results_session    ON results_raw(academic_session, semester);
CREATE INDEX idx_results_level      ON results_raw(level);
CREATE INDEX idx_results_carryover  ON results_raw(is_carryover);

-- -------------------------------------------------------
-- View: computed grades per result row
-- -------------------------------------------------------
CREATE OR REPLACE VIEW v_graded_results AS
SELECT
    r.id,
    r.matric_no,
    r.course_code,
    c.course_title,
    c.course_unit,
    r.academic_session,
    r.semester,
    r.level,
    r.score,
    r.is_carryover,
    -- Grade assignment
    CASE
        WHEN r.score >= 70 THEN 'A'
        WHEN r.score >= 60 THEN 'B'
        WHEN r.score >= 50 THEN 'C'
        WHEN r.score >= 45 THEN 'D'
        WHEN r.score >= 40 THEN 'E'
        ELSE 'F'
    END AS grade,
    -- Grade point
    CASE
        WHEN r.score >= 70 THEN 5
        WHEN r.score >= 60 THEN 4
        WHEN r.score >= 50 THEN 3
        WHEN r.score >= 45 THEN 2
        WHEN r.score >= 40 THEN 1
        ELSE 0
    END AS grade_point,
    -- Quality point
    CASE
        WHEN r.score >= 70 THEN 5
        WHEN r.score >= 60 THEN 4
        WHEN r.score >= 50 THEN 3
        WHEN r.score >= 45 THEN 2
        WHEN r.score >= 40 THEN 1
        ELSE 0
    END * c.course_unit AS quality_point,
    s.full_name,
    s.department
FROM results_raw r
JOIN courses c ON r.course_code = c.course_code
JOIN students s ON r.matric_no = s.matric_no;

-- -------------------------------------------------------
-- Sample data for testing (optional, comment out in prod)
-- -------------------------------------------------------
INSERT IGNORE INTO students (matric_no, full_name, department, faculty, programme, admission_year) VALUES
('CSC/2020/001', 'Adebayo Olanrewaju', 'Computer Science', 'Science', 'B.Sc', 2020),
('CSC/2020/002', 'Fatima Al-Hassan',   'Computer Science', 'Science', 'B.Sc', 2020),
('CSC/2020/003', 'Chukwuemeka Obi',    'Computer Science', 'Science', 'B.Sc', 2020);

INSERT IGNORE INTO courses (course_code, course_title, course_unit, semester, level) VALUES
('CSC101', 'Introduction to Computer Science', 3, 1, 100),
('CSC102', 'Programming Fundamentals',         3, 1, 100),
('MTH101', 'General Mathematics I',            3, 1, 100),
('ENG101', 'Communication Skills I',           2, 1, 100),
('CSC201', 'Data Structures and Algorithms',   3, 1, 200),
('CSC202', 'Object-Oriented Programming',      3, 2, 200),
('MTH201', 'Discrete Mathematics',             3, 1, 200);
