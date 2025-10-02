-- Student Information System Database Setup
-- Create database
CREATE DATABASE IF NOT EXISTS student_info_tracker;
USE student_info_tracker;

-- USERS table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50) UNIQUE NOT NULL,
    user_status ENUM('active', 'locked') DEFAULT 'active',
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'cashier', 'student') NOT NULL,
    last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- STUDENTS_INFO table
CREATE TABLE students_info (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50) UNIQUE NOT NULL,
    student_type ENUM('regular', 'irregular') DEFAULT 'regular',
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    number VARCHAR(20),
    address TEXT,
    profile_picture VARCHAR(255),
    program VARCHAR(50),
    year_level INT,
    student_status ENUM('new', 'old') DEFAULT 'new',
    enrollment_status ENUM('enrolled', 'dropped', 'graduated') DEFAULT 'enrolled',
    enrollment_date DATE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- EMPLOYEE_INFO table
CREATE TABLE employee_info (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    number VARCHAR(20),
    profile_picture VARCHAR(255),
    role VARCHAR(50),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- SUBJECTS table
CREATE TABLE subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_code VARCHAR(20) UNIQUE NOT NULL,
    subject_name VARCHAR(100) NOT NULL,
    units INT NOT NULL,
    sections TEXT, -- JSON array of section codes
    exception TEXT, -- JSON array of irregular student IDs who finished this
    addition TEXT, -- JSON array of irregular student IDs taking this
    description TEXT
);

-- SECTIONS table
CREATE TABLE sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_code VARCHAR(20) UNIQUE NOT NULL,
    user_id TEXT, -- JSON array of student user IDs
    year_level INT,
    program VARCHAR(100),
    status ENUM('active', 'inactive') DEFAULT 'active'
);

-- PAYMENTS table
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
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(user_id),
    FOREIGN KEY (issued_by) REFERENCES users(user_id)
);

-- ACTIVITY_LOGS table
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    log_id VARCHAR(50) UNIQUE NOT NULL,
    user_id VARCHAR(50) NOT NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- LOGIN_ATTEMPTS table for security
CREATE TABLE login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50),
    ip_address VARCHAR(45),
    attempts INT DEFAULT 1,
    last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    locked_until TIMESTAMP NULL
);

-- Insert default admin user (password: admin123)
INSERT INTO users (user_id, name, email, password, role) VALUES 
('ADMIN001', 'System Administrator', 'admin@system.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

INSERT INTO employee_info (user_id, name, email, role) VALUES 
('ADMIN001', 'System Administrator', 'admin@system.com', 'System Administrator');
