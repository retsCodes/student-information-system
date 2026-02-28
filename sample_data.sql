-- =======================================================
-- CREATE DEFAULT ADMIN USERS FIRST
-- =======================================================

-- Create admin users before referencing them
-- Password: admin123 (hashed using bcrypt)
-- Password: cashier123 (hashed using bcrypt)
-- Password: registrar123 (hashed using bcrypt)
-- Password: student123 (hashed using bcrypt)
INSERT INTO users (user_id, name, email, password, role, profile_picture) VALUES
('ADMIN001', 'System Administrator', 'admin@school.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', NULL),
('CASH001', 'John Cashier', 'cashier@school.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cashier', NULL),
('REG001', 'Sarah Registrar', 'registrar@school.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'registrar', NULL),

-- Create student users
('C23-01-1101-BSIT101', 'Juan Dela Cruz', 'juan.delacruz@student.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', NULL),
('C23-01-1102-BSIT101', 'Maria Santos', 'maria.santos@student.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', NULL),
('C22-02-2101-BSIT201', 'Pedro Reyes', 'pedro.reyes@student.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', NULL),
('C22-02-2102-BSIT201', 'Anna Martinez', 'anna.martinez@student.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', NULL),

-- BSCS Students
('C23-01-1101-BSCS101', 'Alexandra Nicole Garcia', 'alexandra.garcia@student.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', NULL),
('C23-01-1102-BSCS101', 'Miguel Antonio Reyes', 'miguel.reyes@student.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', NULL),
('C22-02-2101-BSCS201', 'Isabella Marie Cruz', 'isabella.cruz@student.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', NULL),
('C22-02-2102-BSCS201', 'Gabriel Matthew Tan', 'gabriel.tan@student.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', NULL);

-- Create employee info for admin users
INSERT INTO employee_info (user_id, name, email, number, role) VALUES
('ADMIN001', 'System Administrator', 'admin@school.edu', '09190000001', 'System Administrator'),
('CASH001', 'John Cashier', 'cashier@school.edu', '09190000002', 'Cashier'),
('REG001', 'Sarah Registrar', 'registrar@school.edu', '09190000003', 'Registrar');

-- Create student info
INSERT INTO students_info (user_id, student_type, name, email, number, address, program, year_level, student_status, enrollment_status, status, enrollment_date, total_units) VALUES
-- BSIT Students
('C23-01-1101-BSIT101', 'regular', 'Juan Dela Cruz', 'juan.delacruz@student.com', '09191234567', '123 Main St, Manila', 'BS Information Technology', 1, 'new', 'enrolled', 'active', '2023-06-20', 24),
('C23-01-1102-BSIT101', 'regular', 'Maria Santos', 'maria.santos@student.com', '09192234567', '456 Elm St, Quezon City', 'BS Information Technology', 1, 'new', 'enrolled', 'active', '2023-06-21', 24),
('C22-02-2101-BSIT201', 'regular', 'Pedro Reyes', 'pedro.reyes@student.com', '09193234567', '789 Oak St, Makati', 'BS Information Technology', 2, 'old', 'enrolled', 'active', '2022-06-20', 27),
('C22-02-2102-BSIT201', 'regular', 'Anna Martinez', 'anna.martinez@student.com', '09194234567', '321 Pine St, Pasig', 'BS Information Technology', 2, 'old', 'enrolled', 'active', '2022-06-21', 27),

-- BSCS Students
('C23-01-1101-BSCS101', 'regular', 'Alexandra Nicole Garcia', 'alexandra.garcia@student.com', '09191234567', '123 Computer St, Manila', 'BS Computer Science', 1, 'new', 'enrolled', 'active', '2023-06-20', 24),
('C23-01-1102-BSCS101', 'regular', 'Miguel Antonio Reyes', 'miguel.reyes@student.com', '09192234567', '456 Algorithm Ave, QC', 'BS Computer Science', 1, 'new', 'enrolled', 'active', '2023-06-21', 24),
('C22-02-2101-BSCS201', 'regular', 'Isabella Marie Cruz', 'isabella.cruz@student.com', '09193234567', '789 Data Blvd, Makati', 'BS Computer Science', 2, 'old', 'enrolled', 'active', '2022-06-20', 27),
('C22-02-2102-BSCS201', 'regular', 'Gabriel Matthew Tan', 'gabriel.tan@student.com', '09194234567', '321 Software Rd, Pasig', 'BS Computer Science', 2, 'old', 'enrolled', 'active', '2022-06-21', 27);

-- =======================================================
-- COURSE DATA
-- =======================================================

-- Main Courses
INSERT INTO courses (course_code, course_name, description, total_units, duration_years, status) VALUES
('BSIT', 'Bachelor of Science in Information Technology', 'Prepares students for careers in software development, networking, database administration, and other IT fields. Focuses on practical skills and theoretical knowledge.', 144, 4, 'active'),
('BSCS', 'Bachelor of Science in Computer Science', 'Focuses on the theoretical foundations of computation and information. Includes algorithms, data structures, programming languages, and software engineering.', 160, 4, 'active'),
('BSCpE', 'Bachelor of Science in Computer Engineering', 'Combines electrical engineering and computer science to design and develop computer systems and hardware.', 150, 4, 'active'),
('BSIS', 'Bachelor of Science in Information Systems', 'Focuses on business applications of information technology, including system analysis, design, and implementation.', 140, 4, 'active'),
('ACT', 'Associate in Computer Technology', 'Two-year program providing fundamental skills in computer applications, programming, and IT support.', 72, 2, 'active'),
('BSBA', 'Bachelor of Science in Business Administration', 'Provides comprehensive business education with specializations in marketing, finance, and management.', 120, 4, 'active'),
('BSCrim', 'Bachelor of Science in Criminology', 'Prepares students for careers in law enforcement, criminal investigation, and crime prevention.', 130, 4, 'active'),
('BSED', 'Bachelor of Secondary Education', 'Teacher education program with majors in English, Mathematics, Science, and Filipino.', 150, 4, 'active'),
('BSA', 'Bachelor of Science in Accountancy', 'Prepares students for careers in accounting, auditing, taxation, and financial management.', 160, 4, 'active'),
('BSPsych', 'Bachelor of Science in Psychology', 'Studies human behavior, mental processes, and psychological assessment techniques.', 140, 4, 'active');

-- =======================================================
-- ADD MISSING SUBJECTS FOR BSCS CURRICULUM
-- =======================================================

-- First, add the BSCS subjects that will be referenced in the curriculum
INSERT INTO subjects (subject_code, subject_name, units, program, year_level, semester, description) VALUES
('CC101', 'Introduction to Computing', 3, 'BS Computer Science', 1, '1st', 'Introduction to computer science concepts and programming'),
('CC102', 'Computer Programming 1', 3, 'BS Computer Science', 1, '1st', 'Fundamentals of programming using a high-level language'),
('MATH101', 'College Algebra', 3, 'BS Computer Science', 1, '1st', 'Algebraic concepts and problem-solving'),
('ENG101', 'English Communication 1', 3, 'BS Computer Science', 1, '1st', 'English communication skills development')
ON DUPLICATE KEY UPDATE 
    subject_name = VALUES(subject_name),
    units = VALUES(units),
    program = VALUES(program),
    year_level = VALUES(year_level),
    semester = VALUES(semester),
    description = VALUES(description);

-- =======================================================
-- COURSE CURRICULUM FOR BSCS
-- =======================================================

-- BSCS Curriculum (sample subjects)
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, suggested_units, order_index)
VALUES
((SELECT id FROM courses WHERE course_code = 'BSCS'), (SELECT id FROM subjects WHERE subject_code = 'CC101'), 1, '1st', 1, 3, 1),
((SELECT id FROM courses WHERE course_code = 'BSCS'), (SELECT id FROM subjects WHERE subject_code = 'CC102'), 1, '1st', 1, 3, 2),
((SELECT id FROM courses WHERE course_code = 'BSCS'), (SELECT id FROM subjects WHERE subject_code = 'MATH101'), 1, '1st', 1, 3, 3),
((SELECT id FROM courses WHERE course_code = 'BSCS'), (SELECT id FROM subjects WHERE subject_code = 'ENG101'), 1, '1st', 1, 3, 4);

-- =======================================================
-- ADDITIONAL SUBJECTS FOR OTHER COURSES
-- =======================================================

-- BSCS Subjects
INSERT INTO subjects (subject_code, subject_name, units, program, year_level, semester, description) VALUES
-- Core CS Subjects
('CS201', 'Data Structures and Algorithms', 3, 'BS Computer Science', 2, '1st', 'Advanced data structures and algorithm analysis'),
('CS202', 'Computer Organization', 3, 'BS Computer Science', 2, '1st', 'Computer architecture and organization'),
('CS301', 'Theory of Computation', 3, 'BS Computer Science', 3, '1st', 'Formal languages and automata theory'),
('CS302', 'Operating Systems', 3, 'BS Computer Science', 3, '1st', 'OS design and implementation'),
('CS401', 'Artificial Intelligence', 3, 'BS Computer Science', 4, '1st', 'AI principles and applications'),
('CS402', 'Machine Learning', 3, 'BS Computer Science', 4, '1st', 'ML algorithms and techniques'),

-- Business Subjects for BSBA
('BUS101', 'Principles of Management', 3, 'BS Business Administration', 1, '1st', 'Introduction to management principles'),
('BUS102', 'Financial Accounting', 3, 'BS Business Administration', 1, '1st', 'Basic accounting principles'),
('BUS201', 'Marketing Management', 3, 'BS Business Administration', 2, '1st', 'Marketing concepts and strategies'),
('BUS202', 'Business Finance', 3, 'BS Business Administration', 2, '1st', 'Financial management principles'),

-- Criminology Subjects
('CRIM101', 'Introduction to Criminology', 3, 'BS Criminology', 1, '1st', 'Basic concepts of criminology'),
('CRIM102', 'Criminal Law', 3, 'BS Criminology', 1, '1st', 'Study of criminal laws'),
('CRIM201', 'Forensic Science', 3, 'BS Criminology', 2, '1st', 'Scientific investigation techniques'),
('CRIM202', 'Crime Detection', 3, 'BS Criminology', 2, '1st', 'Methods of crime detection'),

-- Education Subjects
('EDUC101', 'Principles of Teaching', 3, 'BS Secondary Education', 1, '1st', 'Teaching methodologies'),
('EDUC102', 'Child and Adolescent Development', 3, 'BS Secondary Education', 1, '1st', 'Developmental psychology'),
('EDUC201', 'Assessment of Learning', 3, 'BS Secondary Education', 2, '1st', 'Educational assessment techniques'),
('EDUC202', 'Curriculum Development', 3, 'BS Secondary Education', 2, '1st', 'Curriculum design principles'),

-- Accountancy Subjects
('ACC101', 'Fundamentals of Accounting', 3, 'BS Accountancy', 1, '1st', 'Basic accounting concepts'),
('ACC102', 'Financial Accounting 1', 3, 'BS Accountancy', 1, '1st', 'Financial statement preparation'),
('ACC201', 'Managerial Accounting', 3, 'BS Accountancy', 2, '1st', 'Accounting for management decisions'),
('ACC202', 'Cost Accounting', 3, 'BS Accountancy', 2, '1st', 'Cost analysis and control'),

-- Psychology Subjects
('PSY101', 'General Psychology', 3, 'BS Psychology', 1, '1st', 'Introduction to psychology'),
('PSY102', 'Developmental Psychology', 3, 'BS Psychology', 1, '1st', 'Human development across lifespan'),
('PSY201', 'Abnormal Psychology', 3, 'BS Psychology', 2, '1st', 'Psychological disorders'),
('PSY202', 'Social Psychology', 3, 'BS Psychology', 2, '1st', 'Social influences on behavior')

ON DUPLICATE KEY UPDATE 
    subject_name = VALUES(subject_name),
    units = VALUES(units),
    program = VALUES(program),
    year_level = VALUES(year_level),
    semester = VALUES(semester),
    description = VALUES(description);

-- =======================================================
-- ADDITIONAL SECTIONS FOR OTHER COURSES
-- =======================================================

-- BSCS Sections
INSERT INTO sections (section_code, section_name, year_level, program, status, semester) VALUES
('BSCS1A', 'BSCS 1-A', 1, 'BS Computer Science', 'active', '1st'),
('BSCS2A', 'BSCS 2-A', 2, 'BS Computer Science', 'active', '1st'),
('BSCS3A', 'BSCS 3-A', 3, 'BS Computer Science', 'active', '1st'),

-- BSBA Sections
('BSBA1A', 'BSBA 1-A', 1, 'BS Business Administration', 'active', '1st'),
('BSBA2A', 'BSBA 2-A', 2, 'BS Business Administration', 'active', '1st'),

-- BSCrim Sections
('BSCRIM1A', 'BSCRIM 1-A', 1, 'BS Criminology', 'active', '1st'),
('BSCRIM2A', 'BSCRIM 2-A', 2, 'BS Criminology', 'active', '1st'),

-- BSED Sections
('BSED1A', 'BSED 1-A', 1, 'BS Secondary Education', 'active', '1st'),
('BSED2A', 'BSED 2-A', 2, 'BS Secondary Education', 'active', '1st'),

-- BSA Sections
('BSA1A', 'BSA 1-A', 1, 'BS Accountancy', 'active', '1st'),
('BSA2A', 'BSA 2-A', 2, 'BS Accountancy', 'active', '1st'),

-- BSPsych Sections
('BSPSYCH1A', 'BSPsych 1-A', 1, 'BS Psychology', 'active', '1st'),
('BSPSYCH2A', 'BSPsych 2-A', 2, 'BS Psychology', 'active', '1st');

-- =======================================================
-- ENROLL STUDENTS IN COURSES
-- =======================================================

-- BSIT Students
INSERT INTO student_course_enrollment (student_id, course_id, enrollment_date, expected_graduation, current_year_level, status)
SELECT 
    u.user_id as student_id,
    (SELECT id FROM courses WHERE course_code = 'BSIT') as course_id,
    si.enrollment_date,
    DATE_ADD(si.enrollment_date, INTERVAL 4 YEAR) as expected_graduation,
    si.year_level as current_year_level,
    'active' as status
FROM users u
JOIN students_info si ON u.user_id = si.user_id
WHERE u.role = 'student' AND si.program LIKE '%BS Information Technology%';

-- BSCS Students
INSERT INTO student_course_enrollment (student_id, course_id, enrollment_date, expected_graduation, current_year_level, status)
SELECT 
    u.user_id as student_id,
    (SELECT id FROM courses WHERE course_code = 'BSCS') as course_id,
    si.enrollment_date,
    DATE_ADD(si.enrollment_date, INTERVAL 4 YEAR) as expected_graduation,
    si.year_level as current_year_level,
    'active' as status
FROM users u
JOIN students_info si ON u.user_id = si.user_id
WHERE u.role = 'student' AND si.program LIKE '%BS Computer Science%';

-- Update course_id in students_info
UPDATE students_info si
JOIN student_course_enrollment sce ON si.user_id = sce.student_id
SET si.course_id = sce.course_id
WHERE sce.status = 'active';

-- =======================================================
-- ACTIVITY LOGS FOR COURSE MANAGEMENT
-- =======================================================

INSERT INTO activity_logs (log_id, user_id, action, description, created_at) VALUES
('LOG101', 'ADMIN001', 'Course Management', 'Added new course: Bachelor of Science in Computer Science', NOW()),
('LOG102', 'ADMIN001', 'Course Management', 'Added new course: Bachelor of Science in Business Administration', NOW()),
('LOG103', 'ADMIN001', 'Course Management', 'Added BSIT curriculum with 45 subjects', NOW()),
('LOG104', 'ADMIN001', 'Course Management', 'Enrolled 20 students in BSIT program', NOW()),
('LOG105', 'ADMIN001', 'Course Management', 'Created sections for all courses', NOW());

-- =======================================================
-- DEFAULT SETTINGS (Global application settings)
-- =======================================================

INSERT INTO settings (name, value, category, description) VALUES
('unit_price', '1000.00', 'payment', 'Price per unit for all courses'),
('school_name', 'Your School Name', 'general', 'Name of the educational institution'),
('school_address', 'Your School Address', 'general', 'Physical address of the school'),
('school_email', 'info@yourschool.edu', 'general', 'Main email address'),
('school_phone', '(123) 456-7890', 'general', 'Contact phone number'),
('currency_symbol', '₱', 'payment', 'Currency symbol for display'),
('currency_code', 'PHP', 'payment', 'ISO currency code'),
('academic_year', '2024-2025', 'academic', 'Current academic year'),
('semester', '1st', 'academic', 'Current semester'),
('payment_deadline_days', '30', 'payment', 'Number of days for payment deadlines'),
('late_payment_fee', '500.00', 'payment', 'Late payment penalty fee'),
('min_payment_percentage', '50', 'payment', 'Minimum percentage required for installment'),
('max_installments', '3', 'payment', 'Maximum number of payment installments');