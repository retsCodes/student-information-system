-- =======================================================
-- STUDENT INFORMATION SYSTEM - DATABASE SETUP
-- =======================================================

CREATE DATABASE IF NOT EXISTS student_info_tracker;
USE student_info_tracker;

-- =======================================================
-- USERS TABLE
-- =======================================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50) UNIQUE NOT NULL,
    user_status ENUM('active', 'locked') DEFAULT 'active',
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'cashier', 'student', 'registrar') NOT NULL,
    profile_picture VARCHAR(255) NULL,
    last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =======================================================
-- STUDENTS_INFO TABLE
-- =======================================================
CREATE TABLE students_info (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50) UNIQUE NOT NULL,
    student_type ENUM('regular', 'irregular') DEFAULT 'regular',
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    number VARCHAR(20),
    address TEXT,
    program VARCHAR(100),
    course_id INT NULL,
    year_level INT,
    student_status ENUM('new', 'old') DEFAULT 'new',
    enrollment_status ENUM('enrolled', 'dropped', 'graduated', 'on_leave') DEFAULT 'enrolled',
    status ENUM('active', 'inactive') DEFAULT 'active',
    enrollment_date DATE,
    total_units INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =======================================================
-- EMPLOYEE_INFO TABLE
-- =======================================================
CREATE TABLE employee_info (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    number VARCHAR(20),
    department VARCHAR(100),
    position VARCHAR(100),
    role VARCHAR(100),
    hire_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =======================================================
-- ACADEMIC_YEARS TABLE
-- =======================================================
CREATE TABLE academic_years (
    id INT AUTO_INCREMENT PRIMARY KEY,
    year_code VARCHAR(20) UNIQUE NOT NULL,
    year_name VARCHAR(50) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_current BOOLEAN DEFAULT FALSE,
    status ENUM('active', 'inactive', 'archived') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =======================================================
-- SEMESTERS TABLE
-- =======================================================
CREATE TABLE semesters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    academic_year_id INT NOT NULL,
    semester ENUM('1st', '2nd', 'summer') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    enrollment_start DATE,
    enrollment_end DATE,
    payment_deadline DATE,
    is_current BOOLEAN DEFAULT FALSE,
    status ENUM('active', 'inactive', 'completed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_year_semester (academic_year_id, semester)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =======================================================
-- COURSES TABLE
-- =======================================================
CREATE TABLE courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_code VARCHAR(20) UNIQUE NOT NULL,
    course_name VARCHAR(100) NOT NULL,
    description TEXT,
    total_units INT DEFAULT 0,
    duration_years INT DEFAULT 4,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =======================================================
-- SUBJECTS TABLE
-- =======================================================
CREATE TABLE subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_code VARCHAR(20) UNIQUE NOT NULL,
    subject_name VARCHAR(100) NOT NULL,
    units INT NOT NULL,
    description TEXT,
    program VARCHAR(100),
    year_level INT,
    semester ENUM('1st', '2nd', 'summer') DEFAULT '1st',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =======================================================
-- COURSE_CURRICULUM TABLE
-- =======================================================
CREATE TABLE course_curriculum (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    subject_id INT NOT NULL,
    year_level INT NOT NULL,
    semester VARCHAR(20) NOT NULL,
    is_required BOOLEAN DEFAULT TRUE,
    order_index INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_course_subject (course_id, subject_id, year_level, semester)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =======================================================
-- SECTIONS TABLE
-- =======================================================
CREATE TABLE sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_code VARCHAR(20) UNIQUE NOT NULL,
    section_name VARCHAR(100),
    program VARCHAR(100) NOT NULL,
    course_id INT NULL,
    year_level INT NOT NULL,
    semester ENUM('1st', '2nd', 'summer') DEFAULT '1st',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =======================================================
-- STUDENT_SECTIONS TABLE (Tracks which section a student belongs to)
-- =======================================================
CREATE TABLE student_sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL,
    section_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_student_section (student_id, section_id),
    INDEX idx_student_sections_student (student_id),
    INDEX idx_student_sections_section (section_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =======================================================
-- SUBJECT_SECTIONS TABLE (Junction for section subjects - EDITABLE)
-- =======================================================
CREATE TABLE subject_sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id INT NOT NULL,
    section_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_auto_filled BOOLEAN DEFAULT FALSE,
    UNIQUE KEY unique_subject_section (subject_id, section_id),
    INDEX idx_subject_sections_subject (subject_id),
    INDEX idx_subject_sections_section (section_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE student_subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL,
    subject_id INT NOT NULL,
    section_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_by VARCHAR(50) NOT NULL,
    reason VARCHAR(255) NULL,
    status ENUM('active', 'dropped', 'completed') DEFAULT 'active',
    UNIQUE KEY unique_student_subject_section (student_id, subject_id, section_id),
    INDEX idx_student_subjects_student (student_id),
    INDEX idx_student_subjects_subject (subject_id),
    INDEX idx_student_subjects_section (section_id),
    INDEX idx_student_subjects_status (status),
    FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =======================================================
-- CLASS_SCHEDULE TABLE
-- =======================================================
CREATE TABLE class_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id INT NOT NULL,
    section_id INT NOT NULL,
    semester_id INT NULL,
    academic_year_id INT NULL,
    day_of_week ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'),
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    room VARCHAR(50) DEFAULT 'TBA',
    instructor VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =======================================================
-- STUDENT_ACADEMIC_RECORDS TABLE (Main connector)
-- =======================================================
CREATE TABLE student_academic_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL,
    academic_year_id INT NOT NULL,
    semester_id INT NOT NULL,
    year_level INT NOT NULL,
    section_id INT NOT NULL,
    course_id INT NOT NULL,
    status ENUM('ONGOING', 'COMPLETED', 'ON_LEAVE', 'DROPPED') DEFAULT 'ONGOING',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_student_year_semester (student_id, academic_year_id, semester_id),
    INDEX idx_student_progress (student_id, year_level, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =======================================================
-- STUDENT_COURSE_ENROLLMENT TABLE (Tracks student enrollment in courses)
-- =======================================================
CREATE TABLE student_course_enrollment (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL,
    course_id INT NOT NULL,
    enrollment_date DATE NOT NULL,
    expected_graduation DATE,
    actual_graduation DATE NULL,
    status ENUM('active', 'completed', 'transferred', 'dropped') DEFAULT 'active',
    current_year_level INT DEFAULT 1,
    current_semester ENUM('1st', '2nd', 'summer') DEFAULT '1st',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_student_course (student_id, course_id),
    INDEX idx_enrollment_student (student_id),
    INDEX idx_enrollment_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =======================================================
-- GRADES TABLE (Add this to your existing schema)
-- =======================================================
CREATE TABLE IF NOT EXISTS grades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL,
    subject_id INT NOT NULL,
    grade DECIMAL(4,2) NULL,
    date_received DATE NULL,
    remarks TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_subject (student_id, subject_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =======================================================
-- STUDENT_COURSE_COMPLETION TABLE (Tracks completed subjects per semester)
-- =======================================================
CREATE TABLE IF NOT EXISTS student_course_completion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL,
    subject_id INT NOT NULL,
    year_level INT NOT NULL,
    semester ENUM('1st', '2nd', 'summer') NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    grade DECIMAL(4,2) NULL,
    date_completed DATE NULL,
    status ENUM('completed', 'in_progress', 'failed', 'dropped') DEFAULT 'completed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_subject_completion (student_id, subject_id, year_level, semester),
    INDEX idx_student_completion (student_id, year_level, semester)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =======================================================
-- PAYMENTS TABLE
-- =======================================================
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL,
    permit_number VARCHAR(20) UNIQUE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    amount_text VARCHAR(255) NULL,                      
    remaining_balance DECIMAL(10,2) DEFAULT 0,
    payment_status ENUM('paid', 'unpaid', 'partial') DEFAULT 'unpaid',
    description TEXT,
    notes TEXT NULL,                                    
    issued_date DATE NOT NULL,
    issued_by VARCHAR(50) NOT NULL,
    school_year VARCHAR(20),
    payment_category ENUM('tuition', 'exam', 'misc', 'other') DEFAULT 'other',
    units INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (issued_by) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =======================================================
-- PAYMENT_INSTALLMENTS TABLE
-- =======================================================
CREATE TABLE payment_installments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_id INT NOT NULL,
    installment_number INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    due_date DATE NOT NULL,
    paid_date DATE NULL,
    status ENUM('pending', 'paid', 'overdue') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =======================================================
-- TRANSACTION_HISTORY TABLE
-- =======================================================
CREATE TABLE transaction_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id VARCHAR(20) UNIQUE NOT NULL,
    student_id VARCHAR(50) NOT NULL,
    transaction_type ENUM('payment', 'refund', 'adjustment', 'waiver', 'bulk_payment') DEFAULT 'payment',
    amount DECIMAL(10,2) NOT NULL,
    previous_balance DECIMAL(10,2) DEFAULT 0,
    new_balance DECIMAL(10,2) DEFAULT 0,
    description TEXT,
    status ENUM('completed', 'pending', 'cancelled') DEFAULT 'completed',
    reference_id VARCHAR(50),
    issued_by VARCHAR(50) NOT NULL,
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =======================================================
-- SETTINGS TABLE
-- =======================================================
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    value TEXT NOT NULL,
    category VARCHAR(50) DEFAULT 'general',
    description TEXT,
    is_editable BOOLEAN DEFAULT TRUE,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =======================================================
-- MOBILE_TOKENS TABLE
-- =======================================================
CREATE TABLE mobile_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50) NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    status ENUM('active', 'revoked') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =======================================================
-- ACTIVITY_LOGS TABLE
-- =======================================================
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    log_id VARCHAR(50) UNIQUE NOT NULL,
    user_id VARCHAR(50) NOT NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =======================================================
-- LOGIN_ATTEMPTS TABLE
-- =======================================================
CREATE TABLE login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50),
    ip_address VARCHAR(45),
    attempts INT DEFAULT 1,
    last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    locked_until TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =======================================================
-- ADD ALL FOREIGN KEY CONSTRAINTS
-- =======================================================

-- students_info foreign keys
ALTER TABLE students_info
ADD CONSTRAINT fk_students_info_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
ADD CONSTRAINT fk_students_info_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL;

-- employee_info foreign keys
ALTER TABLE employee_info
ADD CONSTRAINT fk_employee_info_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE;

-- semesters foreign keys
ALTER TABLE semesters
ADD CONSTRAINT fk_semesters_academic_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE;

-- course_curriculum foreign keys
ALTER TABLE course_curriculum
ADD CONSTRAINT fk_curriculum_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
ADD CONSTRAINT fk_curriculum_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE;

ALTER TABLE student_course_enrollment
ADD CONSTRAINT fk_enrollment_user FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE,
ADD CONSTRAINT fk_enrollment_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE;

-- sections foreign keys
ALTER TABLE sections
ADD CONSTRAINT fk_sections_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL;

-- student_sections foreign keys
ALTER TABLE student_sections
ADD CONSTRAINT fk_student_sections_user FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE,
ADD CONSTRAINT fk_student_sections_section FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE;

ALTER TABLE subject_sections
ADD CONSTRAINT fk_subject_sections_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
ADD CONSTRAINT fk_subject_sections_section FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE;

-- class_schedule foreign keys
ALTER TABLE class_schedule
ADD CONSTRAINT fk_schedule_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
ADD CONSTRAINT fk_schedule_section FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE,
ADD CONSTRAINT fk_schedule_semester FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE SET NULL,
ADD CONSTRAINT fk_schedule_academic_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE SET NULL;

-- student_academic_records foreign keys
ALTER TABLE student_academic_records
ADD CONSTRAINT fk_academic_records_student FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE,
ADD CONSTRAINT fk_academic_records_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE,
ADD CONSTRAINT fk_academic_records_semester FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE CASCADE,
ADD CONSTRAINT fk_academic_records_section FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE,
ADD CONSTRAINT fk_academic_records_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE;

-- payments foreign keys
ALTER TABLE payments
ADD CONSTRAINT fk_payments_student FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE,
ADD CONSTRAINT fk_payments_issued_by FOREIGN KEY (issued_by) REFERENCES users(user_id) ON DELETE CASCADE;

-- payment_installments foreign keys
ALTER TABLE payment_installments
ADD CONSTRAINT fk_installments_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE;

-- transaction_history foreign keys
ALTER TABLE transaction_history
ADD CONSTRAINT fk_transaction_student FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE,
ADD CONSTRAINT fk_transaction_issued_by FOREIGN KEY (issued_by) REFERENCES users(user_id) ON DELETE CASCADE;

-- settings foreign keys
ALTER TABLE settings
ADD CONSTRAINT fk_settings_updated_by FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE SET NULL;

-- mobile_tokens foreign keys
ALTER TABLE mobile_tokens
ADD CONSTRAINT fk_mobile_tokens_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE;

-- activity_logs foreign keys
ALTER TABLE activity_logs
ADD CONSTRAINT fk_activity_logs_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE;

-- =======================================================
-- INDEXES
-- =======================================================
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_students_info_program ON students_info(program);
CREATE INDEX idx_students_info_year ON students_info(year_level);
CREATE INDEX idx_students_info_status ON students_info(enrollment_status);
CREATE INDEX idx_payments_student ON payments(student_id);
CREATE INDEX idx_payments_status ON payments(payment_status);
CREATE INDEX idx_payments_date ON payments(issued_date);
CREATE INDEX idx_transaction_student ON transaction_history(student_id);
CREATE INDEX idx_transaction_date ON transaction_history(transaction_date);
CREATE INDEX idx_subjects_program ON subjects(program);
CREATE INDEX idx_subjects_year ON subjects(year_level);
CREATE INDEX idx_curriculum_course ON course_curriculum(course_id, year_level, semester);
CREATE INDEX idx_sections_program ON sections(program, year_level);
CREATE INDEX idx_sections_course ON sections(course_id);
CREATE INDEX idx_schedule_section ON class_schedule(section_id);
CREATE INDEX idx_schedule_subject ON class_schedule(subject_id);
CREATE INDEX idx_activity_user ON activity_logs(user_id);
CREATE INDEX idx_activity_date ON activity_logs(created_at);
CREATE INDEX idx_settings_category ON settings(category);
CREATE INDEX idx_mobile_user ON mobile_tokens(user_id);
CREATE INDEX idx_mobile_expires ON mobile_tokens(expires_at);
CREATE INDEX idx_completion_student_subject ON student_course_completion(student_id, subject_id, year_level, semester);
CREATE INDEX idx_completion_status ON student_course_completion(status);
CREATE INDEX idx_schedule_day_time ON class_schedule(day_of_week, start_time);
CREATE INDEX idx_payments_student_status ON payments(student_id, payment_status);