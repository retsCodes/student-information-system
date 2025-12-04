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
    role ENUM('admin', 'cashier', 'student') NOT NULL,
    last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =======================================================
-- STUDENTS_INFO TABLE
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
    profile_picture VARCHAR(255),
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
-- INDEXES FOR PERFORMANCE OPTIMIZATION
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

-- =======================================================
-- FUNCTIONS FOR PAYMENT MANAGEMENT
DELIMITER //

CREATE FUNCTION generate_permit_number() RETURNS VARCHAR(6)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE new_permit VARCHAR(6);
    DECLARE counter INT DEFAULT 0;
    
    WHILE counter < 100 DO
        SET new_permit = LPAD(FLOOR(RAND() * 1000000), 6, '0');
        
        IF NOT EXISTS (SELECT 1 FROM payments WHERE permit_number = new_permit) THEN
            RETURN new_permit;
        END IF;
        
        SET counter = counter + 1;
    END WHILE;
    
    RETURN NULL;
END//

-- Function to calculate exam fee based on units
CREATE FUNCTION calculate_exam_fee(units INT, fee_per_unit DECIMAL(10,2)) 
RETURNS DECIMAL(10,2)
DETERMINISTIC
BEGIN
    RETURN units * fee_per_unit;
END//

DELIMITER ;

-- =======================================================
-- COMPREHENSIVE EXAMPLE DATA
-- =======================================================

-- =======================================================
-- DEFAULT ADMIN USER
INSERT INTO users (user_id, name, email, password, role)
VALUES (
    'ADMIN001',
    'System Administrator',
    'admin@system.com',
    '$2y$10$52K4NNdlJ4kAxiD6Ls5MzexJig2IGVeWoyWtszcD8zbb3ewZO4PEu',
    'admin'
);

INSERT INTO employee_info (user_id, name, email, role)
VALUES (
    'ADMIN001',
    'System Administrator',
    'admin@system.com',
    'System Administrator'
);

-- =======================================================
-- SAMPLE CASHIER USERS (Password: "password")
INSERT INTO users (user_id, name, email, password, role) VALUES
('CASH001', 'Maria Santos', 'maria.santos@school.com', '$2y$10$r3uJ.jJj6nL.6X8Q8X8X8u8X8X8X8X8X8X8X8X8X8X8X8X8X8X8X8', 'cashier'),
('CASH002', 'Juan Dela Cruz', 'juan.delacruz@school.com', '$2y$10$r3uJ.jJj6nL.6X8Q8X8X8u8X8X8X8X8X8X8X8X8X8X8X8X8X8X8X8', 'cashier');

INSERT INTO employee_info (user_id, name, email, role) VALUES
('CASH001', 'Maria Santos', 'maria.santos@school.com', 'Senior Cashier'),
('CASH002', 'Juan Dela Cruz', 'juan.delacruz@school.com', 'Cashier');

-- =======================================================
-- SAMPLE BSIT STUDENTS (Year 1-4) (Password: "password")
INSERT INTO users (user_id, name, email, password, role) VALUES
-- Year 1 Students
('C23-01-1001-BSIT101', 'John Michael Santos', 'john.santos@student.com', '$2y$10$r3uJ.jJj6nL.6X8Q8X8X8u8X8X8X8X8X8X8X8X8X8X8X8X8X8X8X8', 'student'),
('C23-01-1002-BSIT101', 'Maria Cristina Reyes', 'maria.reyes@student.com', '$2y$10$r3uJ.jJj6nL.6X8Q8X8X8u8X8X8X8X8X8X8X8X8X8X8X8X8X8X8X8', 'student'),
('C23-01-1003-BSIT101', 'Carlos Antonio Lim', 'carlos.lim@student.com', '$2y$10$r3uJ.jJj6nL.6X8Q8X8X8u8X8X8X8X8X8X8X8X8X8X8X8X8X8X8X8', 'student'),
('C23-01-1004-BSIT101', 'Andrea Marie Tan', 'andrea.tan@student.com', '$2y$10$r3uJ.jJj6nL.6X8Q8X8X8u8X8X8X8X8X8X8X8X8X8X8X8X8X8X8X8', 'student'),
('C23-01-1005-BSIT101', 'James Patrick Cruz', 'james.cruz@student.com', '$2y$10$r3uJ.jJj6nL.6X8Q8X8X8u8X8X8X8X8X8X8X8X8X8X8X8X8X8X8X8', 'student'),

-- Year 2 Students
('C22-02-2001-BSIT201', 'Sarah Jane Gonzales', 'sarah.gonzales@student.com', '$2y$10$r3uJ.jJj6nL.6X8Q8X8X8u8X8X8X8X8X8X8X8X8X8X8X8X8X8X8X8', 'student'),
('C22-02-2002-BSIT201', 'Daniel Robert Sy', 'daniel.sy@student.com', '$2y$10$r3uJ.jJj6nL.6X8Q8X8X8u8X8X8X8X8X8X8X8X8X8X8X8X8X8X8X8', 'student'),
('C22-02-2003-BSIT201', 'Michelle Anne Wong', 'michelle.wong@student.com', '$2y$10$r3uJ.jJj6nL.6X8Q8X8X8u8X8X8X8X8X8X8X8X8X8X8X8X8X8X8X8', 'student'),
('C22-02-2004-BSIT201', 'Mark Anthony Chen', 'mark.chen@student.com', '$2y$10$r3uJ.jJj6nL.6X8Q8X8X8u8X8X8X8X8X8X8X8X8X8X8X8X8X8X8X8', 'student'),
('C22-02-2005-BSIT201', 'Jennifer Lee Co', 'jennifer.co@student.com', '$2y$10$r3uJ.jJj6nL.6X8Q8X8X8u8X8X8X8X8X8X8X8X8X8X8X8X8X8X8X8', 'student'),

-- Year 3 Students
('C21-03-3001-BSIT301', 'Robert John Ong', 'robert.ong@student.com', '$2y$10$r3uJ.jJj6nL.6X8Q8X8X8u8X8X8X8X8X8X8X8X8X8X8X8X8X8X8X8', 'student'),
('C21-03-3002-BSIT301', 'Catherine Rose Yu', 'catherine.yu@student.com', '$2y$10$r3uJ.jJj6nL.6X8Q8X8X8u8X8X8X8X8X8X8X8X8X8X8X8X8X8X8X8', 'student'),
('C21-03-3003-BSIT301', 'Paul Vincent Uy', 'paul.uy@student.com', '$2y$10$r3uJ.jJj6nL.6X8Q8X8X8u8X8X8X8X8X8X8X8X8X8X8X8X8X8X8X8', 'student'),
('C21-03-3004-BSIT301', 'Angela Marie Chua', 'angela.chua@student.com', '$2y$10$r3uJ.jJj6nL.6X8Q8X8X8u8X8X8X8X8X8X8X8X8X8X8X8X8X8X8X8', 'student'),
('C21-03-3005-BSIT301', 'Christopher John Go', 'christopher.go@student.com', '$2y$10$r3uJ.jJj6nL.6X8Q8X8X8u8X8X8X8X8X8X8X8X8X8X8X8X8X8X8X8', 'student'),

-- Year 4 Students
('C20-04-4001-BSIT401', 'Stephanie Anne Lim', 'stephanie.lim@student.com', '$2y$10$r3uJ.jJj6nL.6X8Q8X8X8u8X8X8X8X8X8X8X8X8X8X8X8X8X8X8X8', 'student'),
('C20-04-4002-BSIT401', 'Kevin Michael Tan', 'kevin.tan@student.com', '$2y$10$r3uJ.jJj6nL.6X8Q8X8X8u8X8X8X8X8X8X8X8X8X8X8X8X8X8X8X8', 'student'),
('C20-04-4003-BSIT401', 'Nicole Elizabeth Sy', 'nicole.sy@student.com', '$2y$10$r3uJ.jJj6nL.6X8Q8X8X8u8X8X8X8X8X8X8X8X8X8X8X8X8X8X8X8', 'student'),
('C20-04-4004-BSIT401', 'Brian Joseph Co', 'brian.co@student.com', '$2y$10$r3uJ.jJj6nL.6X8Q8X8X8u8X8X8X8X8X8X8X8X8X8X8X8X8X8X8X8', 'student'),
('C20-04-4005-BSIT401', 'Patricia Ann Wong', 'patricia.wong@student.com', '$2y$10$r3uJ.jJj6nL.6X8Q8X8X8u8X8X8X8X8X8X8X8X8X8X8X8X8X8X8X8', 'student');

-- =======================================================
-- STUDENTS_INFO DATA
INSERT INTO students_info (user_id, student_type, name, email, number, address, program, year_level, student_status, enrollment_status, status, enrollment_date, total_units) VALUES
-- Year 1 BSIT Students
('C23-01-1001-BSIT101', 'regular', 'John Michael Santos', 'john.santos@student.com', '09171234567', '123 Main St, Manila', 'BS Information Technology', 1, 'new', 'enrolled', 'active', '2023-06-15', 24),
('C23-01-1002-BSIT101', 'regular', 'Maria Cristina Reyes', 'maria.reyes@student.com', '09172234567', '456 Oak St, Quezon City', 'BS Information Technology', 1, 'new', 'enrolled', 'active', '2023-06-16', 24),
('C23-01-1003-BSIT101', 'regular', 'Carlos Antonio Lim', 'carlos.lim@student.com', '09173234567', '789 Pine St, Makati', 'BS Information Technology', 1, 'new', 'enrolled', 'active', '2023-06-17', 24),
('C23-01-1004-BSIT101', 'regular', 'Andrea Marie Tan', 'andrea.tan@student.com', '09174234567', '321 Elm St, Mandaluyong', 'BS Information Technology', 1, 'new', 'enrolled', 'active', '2023-06-18', 24),
('C23-01-1005-BSIT101', 'regular', 'James Patrick Cruz', 'james.cruz@student.com', '09175234567', '654 Maple St, Pasig', 'BS Information Technology', 1, 'new', 'enrolled', 'active', '2023-06-19', 24),

-- Year 2 BSIT Students
('C22-02-2001-BSIT201', 'regular', 'Sarah Jane Gonzales', 'sarah.gonzales@student.com', '09176234567', '987 Cedar St, Taguig', 'BS Information Technology', 2, 'old', 'enrolled', 'active', '2022-06-15', 27),
('C22-02-2002-BSIT201', 'regular', 'Daniel Robert Sy', 'daniel.sy@student.com', '09177234567', '147 Birch St, Paranaque', 'BS Information Technology', 2, 'old', 'enrolled', 'active', '2022-06-16', 27),
('C22-02-2003-BSIT201', 'regular', 'Michelle Anne Wong', 'michelle.wong@student.com', '09178234567', '258 Walnut St, Las Pinas', 'BS Information Technology', 2, 'old', 'enrolled', 'active', '2022-06-17', 27),
('C22-02-2004-BSIT201', 'regular', 'Mark Anthony Chen', 'mark.chen@student.com', '09179234567', '369 Spruce St, Muntinlupa', 'BS Information Technology', 2, 'old', 'enrolled', 'active', '2022-06-18', 27),
('C22-02-2005-BSIT201', 'regular', 'Jennifer Lee Co', 'jennifer.co@student.com', '09180234567', '741 Aspen St, Valenzuela', 'BS Information Technology', 2, 'old', 'enrolled', 'active', '2022-06-19', 27),

-- Year 3 BSIT Students
('C21-03-3001-BSIT301', 'regular', 'Robert John Ong', 'robert.ong@student.com', '09181234567', '852 Poplar St, Caloocan', 'BS Information Technology', 3, 'old', 'enrolled', 'active', '2021-06-15', 30),
('C21-03-3002-BSIT301', 'regular', 'Catherine Rose Yu', 'catherine.yu@student.com', '09182234567', '963 Willow St, Malabon', 'BS Information Technology', 3, 'old', 'enrolled', 'active', '2021-06-16', 30),
('C21-03-3003-BSIT301', 'irregular', 'Paul Vincent Uy', 'paul.uy@student.com', '09183234567', '159 Redwood St, Navotas', 'BS Information Technology', 3, 'old', 'enrolled', 'active', '2021-06-17', 24),
('C21-03-3004-BSIT301', 'regular', 'Angela Marie Chua', 'angela.chua@student.com', '09184234567', '357 Sequoia St, Pasay', 'BS Information Technology', 3, 'old', 'enrolled', 'active', '2021-06-18', 30),
('C21-03-3005-BSIT301', 'regular', 'Christopher John Go', 'christopher.go@student.com', '09185234567', '486 Fir St, San Juan', 'BS Information Technology', 3, 'old', 'enrolled', 'active', '2021-06-19', 30),

-- Year 4 BSIT Students
('C20-04-4001-BSIT401', 'regular', 'Stephanie Anne Lim', 'stephanie.lim@student.com', '09186234567', '753 Cypress St, Marikina', 'BS Information Technology', 4, 'old', 'enrolled', 'active', '2020-06-15', 24),
('C20-04-4002-BSIT401', 'regular', 'Kevin Michael Tan', 'kevin.tan@student.com', '09187234567', '864 Magnolia St, Antipolo', 'BS Information Technology', 4, 'old', 'enrolled', 'active', '2020-06-16', 24),
('C20-04-4003-BSIT401', 'regular', 'Nicole Elizabeth Sy', 'nicole.sy@student.com', '09188234567', '975 Dogwood St, Cainta', 'BS Information Technology', 4, 'old', 'enrolled', 'active', '2020-06-17', 24),
('C20-04-4004-BSIT401', 'regular', 'Brian Joseph Co', 'brian.co@student.com', '09189234567', '186 Hickory St, Taytay', 'BS Information Technology', 4, 'old', 'enrolled', 'active', '2020-06-18', 24),
('C20-04-4005-BSIT401', 'regular', 'Patricia Ann Wong', 'patricia.wong@student.com', '09190234567', '297 Palm St, Angono', 'BS Information Technology', 4, 'old', 'enrolled', 'active', '2020-06-19', 24);

-- =======================================================
-- BSIT SUBJECTS (Year 1-4, 1st and 2nd Semester)
INSERT INTO subjects (subject_code, subject_name, units, program, year_level, semester, description) VALUES
-- Year 1 - 1st Semester
('CC101', 'Introduction to Computing', 3, 'BS Information Technology', 1, '1st', 'Fundamentals of computing and computer systems'),
('CC102', 'Computer Programming 1', 3, 'BS Information Technology', 1, '1st', 'Introduction to programming concepts'),
('MATH101', 'College Algebra', 3, 'BS Information Technology', 1, '1st', 'Algebraic concepts and applications'),
('ENG101', 'Purposive Communication', 3, 'BS Information Technology', 1, '1st', 'Effective communication skills'),
('FIL101', 'Komunikasyon sa Akademikong Filipino', 3, 'BS Information Technology', 1, '1st', 'Filipino language in academic context'),
('PE101', 'Physical Fitness', 2, 'BS Information Technology', 1, '1st', 'Physical education and fitness'),
('NSTP101', 'National Service Training Program 1', 3, 'BS Information Technology', 1, '1st', 'Civic welfare training service'),

-- Year 1 - 2nd Semester
('CC103', 'Computer Programming 2', 3, 'BS Information Technology', 1, '2nd', 'Advanced programming concepts'),
('CC104', 'Data Structures and Algorithms', 3, 'BS Information Technology', 1, '2nd', 'Fundamental data structures and algorithms'),
('MATH102', 'Calculus 1', 3, 'BS Information Technology', 1, '2nd', 'Differential and integral calculus'),
('SCI101', 'Science, Technology and Society', 3, 'BS Information Technology', 1, '2nd', 'Impact of science and technology'),
('SOC101', 'Understanding the Self', 3, 'BS Information Technology', 1, '2nd', 'Psychological perspectives of self'),
('PE102', 'Rhythmic Activities', 2, 'BS Information Technology', 1, '2nd', 'Dance and movement education'),
('NSTP102', 'National Service Training Program 2', 3, 'BS Information Technology', 1, '2nd', 'Advanced civic welfare training'),

-- Year 2 - 1st Semester
('CC201', 'Object-Oriented Programming', 3, 'BS Information Technology', 2, '1st', 'Principles of OOP using Java'),
('CC202', 'Discrete Mathematics', 3, 'BS Information Technology', 2, '1st', 'Mathematical structures in computing'),
('CC203', 'Database Management System 1', 3, 'BS Information Technology', 2, '1st', 'Introduction to database concepts'),
('CC204', 'Platform Technologies', 3, 'BS Information Technology', 2, '1st', 'Operating systems and platforms'),
('MATH201', 'Calculus 2', 3, 'BS Information Technology', 2, '1st', 'Advanced calculus topics'),
('ETH201', 'Ethics', 3, 'BS Information Technology', 2, '1st', 'Moral philosophy and ethical principles'),
('PE201', 'Individual/Dual Sports', 2, 'BS Information Technology', 2, '1st', 'Sports and recreational activities'),

-- Year 2 - 2nd Semester
('CC205', 'Information Management', 3, 'BS Information Technology', 2, '2nd', 'Data and information systems management'),
('CC206', 'Networking 1', 3, 'BS Information Technology', 2, '2nd', 'Computer networks fundamentals'),
('CC207', 'Web Development', 3, 'BS Information Technology', 2, '2nd', 'Web programming and development'),
('CC208', 'Database Management System 2', 3, 'BS Information Technology', 2, '2nd', 'Advanced database concepts'),
('RIZAL', 'Life and Works of Rizal', 3, 'BS Information Technology', 2, '2nd', 'Study of Jose Rizals life and works'),
('PE202', 'Team Sports', 2, 'BS Information Technology', 2, '2nd', 'Team-based sports activities'),

-- Year 3 - 1st Semester
('CC301', 'Advanced Database Systems', 3, 'BS Information Technology', 3, '1st', 'Enterprise database systems'),
('CC302', 'Networking 2', 3, 'BS Information Technology', 3, '1st', 'Advanced networking concepts'),
('CC303', 'Systems Administration and Maintenance', 3, 'BS Information Technology', 3, '1st', 'IT infrastructure management'),
('CC304', 'Systems Integration and Architecture', 3, 'BS Information Technology', 3, '1st', 'System design and integration'),
('CC305', 'Web Programming', 3, 'BS Information Technology', 3, '1st', 'Server-side web development'),
('IT301', 'Information Assurance and Security 1', 3, 'BS Information Technology', 3, '1st', 'Cybersecurity fundamentals'),

-- Year 3 - 2nd Semester
('CC306', 'Mobile Programming', 3, 'BS Information Technology', 3, '2nd', 'Mobile application development'),
('CC307', 'Game Development', 3, 'BS Information Technology', 3, '2nd', 'Game design and programming'),
('CC308', 'Software Engineering 1', 3, 'BS Information Technology', 3, '2nd', 'Software development methodologies'),
('IT302', 'Information Assurance and Security 2', 3, 'BS Information Technology', 3, '2nd', 'Advanced cybersecurity'),
('IT303', 'Quantitative Methods', 3, 'BS Information Technology', 3, '2nd', 'Statistical methods in IT'),
('IT304', 'IT Elective 1', 3, 'BS Information Technology', 3, '2nd', 'Specialized IT track subject'),

-- Year 4 - 1st Semester
('CC401', 'Software Engineering 2', 3, 'BS Information Technology', 4, '1st', 'Advanced software engineering'),
('CC402', 'IT Project Management', 3, 'BS Information Technology', 4, '1st', 'Project management in IT'),
('CC403', 'Systems Analysis and Design', 3, 'BS Information Technology', 4, '1st', 'System development lifecycle'),
('IT401', 'IT Elective 2', 3, 'BS Information Technology', 4, '1st', 'Specialized IT track subject'),
('IT402', 'IT Elective 3', 3, 'BS Information Technology', 4, '1st', 'Specialized IT track subject'),
('IT403', 'Practicum', 3, 'BS Information Technology', 4, '1st', 'Industry immersion program'),

-- Year 4 - 2nd Semester
('CC404', 'Capstone Project 1', 3, 'BS Information Technology', 4, '2nd', 'Thesis project part 1'),
('CC405', 'Capstone Project 2', 3, 'BS Information Technology', 4, '2nd', 'Thesis project part 2'),
('IT404', 'Social and Professional Issues', 3, 'BS Information Technology', 4, '2nd', 'IT ethics and professional practice'),
('IT405', 'IT Elective 4', 3, 'BS Information Technology', 4, '2nd', 'Specialized IT track subject');

-- =======================================================
-- SECTIONS DATA
INSERT INTO sections (section_code, section_name, year_level, program, status, semester) VALUES
-- Year 1 Sections
('BSIT1A', 'BSIT 1-A', 1, 'BS Information Technology', 'active', '1st'),
('BSIT1B', 'BSIT 1-B', 1, 'BS Information Technology', 'active', '1st'),
('BSIT1C', 'BSIT 1-C', 1, 'BS Information Technology', 'active', '1st'),

-- Year 2 Sections
('BSIT2A', 'BSIT 2-A', 2, 'BS Information Technology', 'active', '1st'),
('BSIT2B', 'BSIT 2-B', 2, 'BS Information Technology', 'active', '1st'),

-- Year 3 Sections
('BSIT3A', 'BSIT 3-A', 3, 'BS Information Technology', 'active', '1st'),
('BSIT3B', 'BSIT 3-B', 3, 'BS Information Technology', 'active', '1st'),

-- Year 4 Sections
('BSIT4A', 'BSIT 4-A', 4, 'BS Information Technology', 'active', '1st'),
('BSIT4B', 'BSIT 4-B', 4, 'BS Information Technology', 'active', '1st');

-- =======================================================
-- STUDENT-SECTIONS ASSIGNMENTS
INSERT INTO student_sections (student_id, section_id) VALUES
-- Year 1 Students to BSIT1A
('C23-01-1001-BSIT101', 1), ('C23-01-1002-BSIT101', 1), ('C23-01-1003-BSIT101', 1),
-- Year 1 Students to BSIT1B
('C23-01-1004-BSIT101', 2), ('C23-01-1005-BSIT101', 2),
-- Year 2 Students to BSIT2A
('C22-02-2001-BSIT201', 4), ('C22-02-2002-BSIT201', 4), ('C22-02-2003-BSIT201', 4),
-- Year 2 Students to BSIT2B
('C22-02-2004-BSIT201', 5), ('C22-02-2005-BSIT201', 5),
-- Year 3 Students to BSIT3A
('C21-03-3001-BSIT301', 6), ('C21-03-3002-BSIT301', 6), ('C21-03-3003-BSIT301', 6),
-- Year 3 Students to BSIT3B
('C21-03-3004-BSIT301', 7), ('C21-03-3005-BSIT301', 7),
-- Year 4 Students to BSIT4A
('C20-04-4001-BSIT401', 8), ('C20-04-4002-BSIT401', 8), ('C20-04-4003-BSIT401', 8),
-- Year 4 Students to BSIT4B
('C20-04-4004-BSIT401', 9), ('C20-04-4005-BSIT401', 9);

-- =======================================================
-- STUDENT-SUBJECTS ASSIGNMENTS (Connecting students to their subjects)
INSERT INTO student_subjects (student_id, subject_id) VALUES
-- Year 1 Students - 1st Semester Subjects
('C23-01-1001-BSIT101', 1), ('C23-01-1001-BSIT101', 2), ('C23-01-1001-BSIT101', 3), ('C23-01-1001-BSIT101', 4), ('C23-01-1001-BSIT101', 5), ('C23-01-1001-BSIT101', 6), ('C23-01-1001-BSIT101', 7),
('C23-01-1002-BSIT101', 1), ('C23-01-1002-BSIT101', 2), ('C23-01-1002-BSIT101', 3), ('C23-01-1002-BSIT101', 4), ('C23-01-1002-BSIT101', 5), ('C23-01-1002-BSIT101', 6), ('C23-01-1002-BSIT101', 7),

-- Year 2 Students - 1st Semester Subjects
('C22-02-2001-BSIT201', 15), ('C22-02-2001-BSIT201', 16), ('C22-02-2001-BSIT201', 17), ('C22-02-2001-BSIT201', 18), ('C22-02-2001-BSIT201', 19), ('C22-02-2001-BSIT201', 20), ('C22-02-2001-BSIT201', 21),
('C22-02-2002-BSIT201', 15), ('C22-02-2002-BSIT201', 16), ('C22-02-2002-BSIT201', 17), ('C22-02-2002-BSIT201', 18), ('C22-02-2002-BSIT201', 19), ('C22-02-2002-BSIT201', 20), ('C22-02-2002-BSIT201', 21),

-- Year 3 Students - 1st Semester Subjects
('C21-03-3001-BSIT301', 29), ('C21-03-3001-BSIT301', 30), ('C21-03-3001-BSIT301', 31), ('C21-03-3001-BSIT301', 32), ('C21-03-3001-BSIT301', 33), ('C21-03-3001-BSIT301', 34),
('C21-03-3002-BSIT301', 29), ('C21-03-3002-BSIT301', 30), ('C21-03-3002-BSIT301', 31), ('C21-03-3002-BSIT301', 32), ('C21-03-3002-BSIT301', 33), ('C21-03-3002-BSIT301', 34),

-- Year 4 Students - 1st Semester Subjects
('C20-04-4001-BSIT401', 41), ('C20-04-4001-BSIT401', 42), ('C20-04-4001-BSIT401', 43), ('C20-04-4001-BSIT401', 44), ('C20-04-4001-BSIT401', 45), ('C20-04-4001-BSIT401', 46),
('C20-04-4002-BSIT401', 41), ('C20-04-4002-BSIT401', 42), ('C20-04-4002-BSIT401', 43), ('C20-04-4002-BSIT401', 44), ('C20-04-4002-BSIT401', 45), ('C20-04-4002-BSIT401', 46);

-- =======================================================
-- CLASS SCHEDULE DATA
INSERT INTO class_schedule (subject_id, section_id, day_of_week, start_time, end_time, room) VALUES
-- BSIT1A Schedule
(1, 1, 'Monday', '08:00:00', '09:30:00', 'Room 101'),
(2, 1, 'Monday', '09:30:00', '11:00:00', 'Lab 201'),
(3, 1, 'Tuesday', '08:00:00', '09:30:00', 'Room 102'),
(4, 1, 'Tuesday', '09:30:00', '11:00:00', 'Room 103'),
(5, 1, 'Wednesday', '08:00:00', '09:30:00', 'Room 104'),
(6, 1, 'Thursday', '07:00:00', '09:00:00', 'Gym'),
(7, 1, 'Friday', '08:00:00', '11:00:00', 'Room 105'),

-- BSIT2A Schedule
(15, 4, 'Monday', '13:00:00', '14:30:00', 'Room 201'),
(16, 4, 'Monday', '14:30:00', '16:00:00', 'Room 202'),
(17, 4, 'Tuesday', '13:00:00', '14:30:00', 'Lab 301'),
(18, 4, 'Tuesday', '14:30:00', '16:00:00', 'Room 203'),
(19, 4, 'Wednesday', '13:00:00', '14:30:00', 'Room 204'),
(20, 4, 'Thursday', '13:00:00', '14:30:00', 'Room 205'),
(21, 4, 'Friday', '13:00:00', '15:00:00', 'Gym');

-- =======================================================
-- PAYMENTS DATA (Mixed: Paid, Unpaid, Partial)
INSERT INTO payments (student_id, permit_number, amount, amount_text, remaining_balance, payment_status, description, issued_date, issued_by, school_year, payment_category, units) VALUES
-- PAID EXAM PAYMENTS (Auto-calculated: 2400 per unit)
('C23-01-1001-BSIT101', '123456', 57600, 'Fifty-seven thousand six hundred pesos', 0, 'paid', 'Prelim Examination Fee - 24 units', '2023-09-15', 'CASH001', '2023-2024', 'exam', 24),
('C23-01-1002-BSIT101', '123457', 57600, 'Fifty-seven thousand six hundred pesos', 0, 'paid', 'Prelim Examination Fee - 24 units', '2023-09-15', 'CASH001', '2023-2024', 'exam', 24),
('C22-02-2001-BSIT201', '123458', 64800, 'Sixty-four thousand eight hundred pesos', 0, 'paid', 'Midterm Examination Fee - 27 units', '2023-10-20', 'CASH001', '2023-2024', 'exam', 27),
('C22-02-2002-BSIT201', '123459', 64800, 'Sixty-four thousand eight hundred pesos', 0, 'paid', 'Midterm Examination Fee - 27 units', '2023-10-20', 'CASH001', '2023-2024', 'exam', 27),

-- UNPAID EXAM PAYMENTS
('C23-01-1003-BSIT101', '123460', 57600, 'Fifty-seven thousand six hundred pesos', 57600, 'unpaid', 'Prelim Examination Fee - 24 units', '2023-09-15', 'CASH001', '2023-2024', 'exam', 24),
('C23-01-1004-BSIT101', '123461', 57600, 'Fifty-seven thousand six hundred pesos', 57600, 'unpaid', 'Prelim Examination Fee - 24 units', '2023-09-15', 'CASH001', '2023-2024', 'exam', 24),
('C22-02-2003-BSIT201', '123462', 64800, 'Sixty-four thousand eight hundred pesos', 64800, 'unpaid', 'Midterm Examination Fee - 27 units', '2023-10-20', 'CASH001', '2023-2024', 'exam', 27),
('C21-03-3001-BSIT301', '123463', 72000, 'Seventy-two thousand pesos', 72000, 'unpaid', 'Final Examination Fee - 30 units', '2023-11-25', 'CASH001', '2023-2024', 'exam', 30),

-- PARTIAL PAYMENTS
('C21-03-3002-BSIT301', '123464', 72000, 'Seventy-two thousand pesos', 36000, 'partial', 'Final Examination Fee - 30 units', '2023-11-25', 'CASH001', '2023-2024', 'exam', 30),
('C20-04-4001-BSIT401', '123465', 57600, 'Fifty-seven thousand six hundred pesos', 28800, 'partial', 'Comprehensive Exam Fee - 24 units', '2023-12-10', 'CASH001', '2023-2024', 'exam', 24),
('C20-04-4002-BSIT401', '123466', 57600, 'Fifty-seven thousand six hundred pesos', 57600, 'unpaid', 'Comprehensive Exam Fee - 24 units', '2023-12-10', 'CASH001', '2023-2024', 'exam', 24),

-- MISC PAYMENTS (Custom amounts)
('C23-01-1005-BSIT101', '123467', 5000, 'Five thousand pesos', 0, 'paid', 'Student Council Fee', '2023-08-20', 'CASH001', '2023-2024', 'misc', 0),
('C22-02-2004-BSIT201', '123468', 3000, 'Three thousand pesos', 3000, 'unpaid', 'Library Fine', '2023-10-05', 'CASH001', '2023-2024', 'misc', 0),
('C21-03-3003-BSIT301', '123469', 2000, 'Two thousand pesos', 0, 'paid', 'Laboratory Fee', '2023-09-10', 'CASH001', '2023-2024', 'misc', 0),
('C20-04-4003-BSIT401', '123470', 1500, 'One thousand five hundred pesos', 1500, 'unpaid', 'Graduation Fee', '2023-11-30', 'CASH001', '2023-2024', 'misc', 0),

-- TUITION FEES
('C22-02-2005-BSIT201', '123471', 45000, 'Forty-five thousand pesos', 0, 'paid', '1st Semester Tuition Fee', '2023-08-15', 'CASH001', '2023-2024', 'tuition', 27),
('C21-03-3004-BSIT301', '123472', 50000, 'Fifty thousand pesos', 25000, 'partial', '2nd Semester Tuition Fee', '2023-01-15', 'CASH001', '2023-2024', 'tuition', 30),
('C21-03-3005-BSIT301', '123473', 50000, 'Fifty thousand pesos', 50000, 'unpaid', '2nd Semester Tuition Fee', '2023-01-15', 'CASH001', '2023-2024', 'tuition', 30),
('C20-04-4004-BSIT401', '123474', 40000, 'Forty thousand pesos', 0, 'paid', 'Thesis Fee', '2023-10-01', 'CASH001', '2023-2024', 'tuition', 0),
('C20-04-4005-BSIT401', '123475', 40000, 'Forty thousand pesos', 40000, 'unpaid', 'Thesis Fee', '2023-10-01', 'CASH001', '2023-2024', 'tuition', 0);

-- =======================================================
-- PAYMENT INSTALLMENTS (For partial payments)
INSERT INTO payment_installments (payment_id, installment_number, amount, due_date, paid_date, status) VALUES
(10, 1, 36000, '2023-12-10', '2023-12-05', 'paid'), -- Catherine Rose Yu
(10, 2, 36000, '2024-01-10', NULL, 'pending'),
(11, 1, 28800, '2023-12-15', '2023-12-10', 'paid'), -- Stephanie Anne Lim
(11, 2, 28800, '2024-01-15', NULL, 'pending'),
(17, 1, 25000, '2023-02-15', '2023-02-10', 'paid'), -- Angela Marie Chua
(17, 2, 25000, '2023-03-15', NULL, 'pending');

-- =======================================================
-- TRANSACTION HISTORY
INSERT INTO transaction_history (transaction_id, student_id, transaction_type, amount, previous_balance, new_balance, description, reference_id, issued_by) VALUES
('TXN001', 'C23-01-1001-BSIT101', 'payment', 57600, 57600, 0, 'Prelim Examination Fee Payment', '123456', 'CASH001'),
('TXN002', 'C23-01-1002-BSIT101', 'payment', 57600, 57600, 0, 'Prelim Examination Fee Payment', '123457', 'CASH001'),
('TXN003', 'C22-02-2001-BSIT201', 'payment', 64800, 64800, 0, 'Midterm Examination Fee Payment', '123458', 'CASH001'),
('TXN004', 'C22-02-2002-BSIT201', 'payment', 64800, 64800, 0, 'Midterm Examination Fee Payment', '123459', 'CASH001'),
('TXN005', 'C21-03-3002-BSIT301', 'payment', 36000, 72000, 36000, 'Partial Payment - Final Examination', '123464', 'CASH001'),
('TXN006', 'C20-04-4001-BSIT401', 'payment', 28800, 57600, 28800, 'Partial Payment - Comprehensive Exam', '123465', 'CASH001'),
('TXN007', 'C23-01-1005-BSIT101', 'payment', 5000, 5000, 0, 'Student Council Fee Payment', '123467', 'CASH001'),
('TXN008', 'C21-03-3003-BSIT301', 'payment', 2000, 2000, 0, 'Laboratory Fee Payment', '123469', 'CASH001'),
('TXN009', 'C22-02-2005-BSIT201', 'payment', 45000, 45000, 0, 'Tuition Fee Payment', '123471', 'CASH001'),
('TXN010', 'C21-03-3004-BSIT301', 'payment', 25000, 50000, 25000, 'Partial Tuition Fee Payment', '123472', 'CASH001'),
('TXN011', 'C20-04-4004-BSIT401', 'payment', 40000, 40000, 0, 'Thesis Fee Payment', '123474', 'CASH001');

-- =======================================================
-- ACTIVITY LOGS
INSERT INTO activity_logs (log_id, user_id, action, description) VALUES
('LOG001', 'ADMIN001', 'Bulk Payment Created', 'Created bulk payments for Year 1 BSIT Prelim Examination'),
('LOG002', 'CASH001', 'Payment Processed', 'Processed payment for John Michael Santos - Permit 123456'),
('LOG003', 'CASH001', 'Payment Processed', 'Processed payment for Maria Cristina Reyes - Permit 123457'),
('LOG004', 'ADMIN001', 'Bulk Payment Created', 'Created bulk payments for Year 2 BSIT Midterm Examination'),
('LOG005', 'CASH001', 'Partial Payment', 'Processed partial payment for Catherine Rose Yu - 50% paid');