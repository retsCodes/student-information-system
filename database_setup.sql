-- Student Information System Database Setup
-- =======================================================
-- Create database
CREATE DATABASE IF NOT EXISTS student_info_tracker;
USE student_info_tracker;

-- =======================================================
-- USERS TABLE
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
);

-- =======================================================
-- STUDENTS_INFO TABLE
CREATE TABLE students_info (
    id INT
    
     AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50) UNIQUE NOT NULL,
    student_type ENUM('regular', 'irregular') DEFAULT 'regular',
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    number VARCHAR(20),
    address TEXT,
    program VARCHAR(50),
    course_id INT NULL,
    year_level INT,
    student_status ENUM('new', 'old') DEFAULT 'new',
    enrollment_status ENUM('enrolled', 'dropped', 'graduated') DEFAULT 'enrolled',
    status ENUM('active', 'inactive') DEFAULT 'active',
    enrollment_date DATE,
    total_units INT DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- =======================================================
-- EMPLOYEE_INFO TABLE
CREATE TABLE employee_info (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    number VARCHAR(20),
    role VARCHAR(50),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- =======================================================
-- SUBJECTS TABLE
CREATE TABLE subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_code VARCHAR(20) UNIQUE NOT NULL,
    subject_name VARCHAR(100) NOT NULL,
    units INT NOT NULL,
    sections TEXT,
    exception TEXT,
    addition TEXT,
    description TEXT,
    program VARCHAR(50),
    year_level INT,
    semester ENUM('1st', '2nd', 'summer') DEFAULT '1st'
);

-- =======================================================
-- SECTIONS TABLE
CREATE TABLE sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_code VARCHAR(20) UNIQUE NOT NULL,
    section_name VARCHAR(100) NULL,
    user_id TEXT,
    year_level INT,
    program VARCHAR(100),
    status ENUM('active', 'inactive') DEFAULT 'active',
    semester ENUM('1st', '2nd', 'summer') DEFAULT '1st'
);

-- =======================================================
-- STUDENT-SECTIONS RELATIONSHIP TABLE
CREATE TABLE student_sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL,
    section_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE
);

-- =======================================================
-- STUDENT-SUBJECTS RELATIONSHIP TABLE
CREATE TABLE student_subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL,
    subject_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
);

-- =======================================================
-- PAYMENTS TABLE
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL,
    permit_number VARCHAR(6) UNIQUE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    amount_text VARCHAR(255),
    remaining_balance DECIMAL(10,2) DEFAULT 0,
    payment_status ENUM('paid', 'unpaid', 'partial') DEFAULT 'unpaid',
    description TEXT,
    issued_date DATE NOT NULL,
    issued_by VARCHAR(50) NOT NULL,
    school_year VARCHAR(20),
    payment_type ENUM('regular', 'bulk') DEFAULT 'regular',
    bulk_reference VARCHAR(50),
    payment_category ENUM('tuition', 'exam', 'misc', 'other') DEFAULT 'other',
    units INT DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(user_id),
    FOREIGN KEY (issued_by) REFERENCES users(user_id)
);

-- =======================================================
-- CLASS_SCHEDULE TABLE
CREATE TABLE class_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id INT NOT NULL,
    section_id INT NOT NULL,
    day_of_week ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'),
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    room VARCHAR(50) DEFAULT 'TBA',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE
);

-- =======================================================
-- TRANSACTION_HISTORY TABLE
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
    bulk_reference VARCHAR(50),
    issued_by VARCHAR(50) NOT NULL,
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (student_id) REFERENCES users(user_id),
    FOREIGN KEY (issued_by) REFERENCES users(user_id)
);

-- =======================================================
-- PAYMENT_INSTALLMENTS TABLE
CREATE TABLE payment_installments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_id INT NOT NULL,
    installment_number INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    due_date DATE NOT NULL,
    paid_date DATE NULL,
    status ENUM('pending', 'paid', 'overdue') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE
);

-- =======================================================
-- ACTIVITY_LOGS TABLE
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    log_id VARCHAR(50) UNIQUE NOT NULL,
    user_id VARCHAR(50) NOT NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- =======================================================
-- LOGIN_ATTEMPTS TABLE
CREATE TABLE login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50),
    ip_address VARCHAR(45),
    attempts INT DEFAULT 1,
    last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    locked_until TIMESTAMP NULL
);

-- =======================================================
-- COURSES/PROGRAMS TABLE (e.g., BSIT, BSCS, etc.)
CREATE TABLE courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_code VARCHAR(20) UNIQUE NOT NULL,
    course_name VARCHAR(100) NOT NULL,
    description TEXT,
    total_units INT DEFAULT 0,
    duration_years INT DEFAULT 4,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =======================================================
-- COURSE_CURRICULUM TABLE (Default subjects per year/semester for each course)
CREATE TABLE course_curriculum (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    subject_id INT NOT NULL,
    year_level INT NOT NULL,
    semester ENUM('1st', '2nd', 'summer') DEFAULT '1st',
    is_required BOOLEAN DEFAULT TRUE,
    suggested_units INT,
    prerequisites TEXT, -- JSON array of prerequisite subject IDs
    order_index INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_course_subject (course_id, subject_id, year_level, semester),
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
);

-- =======================================================
-- STUDENT_COURSE_ENROLLMENT TABLE
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
    FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id),
    UNIQUE KEY unique_student_course (student_id, course_id)
);

-- =======================================================
-- STUDENT_CURRICULUM_ADJUSTMENTS TABLE (Admin can override default curriculum)
CREATE TABLE student_curriculum_adjustments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL,
    subject_id INT NOT NULL,
    original_year_level INT,
    original_semester VARCHAR(10),
    adjusted_year_level INT NOT NULL,
    adjusted_semester ENUM('1st', '2nd', 'summer') NOT NULL,
    adjustment_reason TEXT,
    adjusted_by VARCHAR(50) NOT NULL,
    adjustment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (adjusted_by) REFERENCES users(user_id),
    unit_price DECIMAL(10,2) DEFAULT 1000.00
);

-- =======================================================
-- SETTINGS TABLE (Global application settings)
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    value TEXT NOT NULL,
    category VARCHAR(50) DEFAULT 'general',
    description TEXT,
    is_editable BOOLEAN DEFAULT TRUE,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_settings_updated_by
        FOREIGN KEY (updated_by)
        REFERENCES users(user_id)
        ON DELETE SET NULL
);


-- =======================================================
-- MOBILE TOKENS TABLE
CREATE TABLE mobile_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50) NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    status ENUM('active', 'revoked') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- =======================================================
-- INDEXES FOR PERFORMANCE OPTIMIZATION
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_profile_picture ON users(profile_picture);
CREATE INDEX idx_payments_student_id ON payments(student_id);
CREATE INDEX idx_payments_status ON payments(payment_status);
CREATE INDEX idx_payments_issued_date ON payments(issued_date);
CREATE INDEX idx_payments_bulk_reference ON payments(bulk_reference);
CREATE INDEX idx_students_info_status ON students_info(status);
CREATE INDEX idx_students_info_enrollment_status ON students_info(enrollment_status);
CREATE INDEX idx_transaction_history_student_id ON transaction_history(student_id);
CREATE INDEX idx_transaction_history_bulk_reference ON transaction_history(bulk_reference);
CREATE INDEX idx_subjects_program ON subjects(program);
CREATE INDEX idx_subjects_year_level ON subjects(year_level);
CREATE INDEX idx_activity_logs_user_id ON activity_logs(user_id);
CREATE INDEX idx_activity_logs_created_at ON activity_logs(created_at);
CREATE INDEX idx_course_curriculum_course ON course_curriculum(course_id, year_level, semester);
CREATE INDEX idx_student_course_enrollment_status ON student_course_enrollment(status);
CREATE INDEX idx_student_curriculum_adjustments ON student_curriculum_adjustments(student_id, status);
CREATE INDEX idx_settings_category ON settings(category);
CREATE INDEX idx_settings_name ON settings(name);
CREATE INDEX idx_mobile_tokens_user_id ON mobile_tokens(user_id);
CREATE INDEX idx_mobile_tokens_token ON mobile_tokens(token);
CREATE INDEX idx_mobile_tokens_expires_at ON mobile_tokens(expires_at);
