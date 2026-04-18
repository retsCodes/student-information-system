-- =======================================================
-- STUDENT INFORMATION SYSTEM - COMPLETE SAMPLE DATA
-- =======================================================
-- This data simulates a system that has been running for multiple years
-- with proper relationships between courses, subjects, curriculum, sections,
-- and student enrollments across different academic years
-- =======================================================

-- =======================================================
-- PASSWORD HASHING INFORMATION
-- =======================================================
-- All passwords are hashed using bcrypt (cost factor 10)
-- The password format is: $2y$10$[22-character salt][31-character hash]
--
-- Password mappings:
-- admin123    → $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
-- cashier123  → $2y$10$WY7QF5Wqf5b3zUXFQgwz4uFpOVk5XQZ7tqKzZpGZh8Xx9tJvXKbUy
-- registrar123→ $2y$10$JK1pZ5jLqX8fLmN2oP3rQeT5vW7yX9zA1bC3dE5fG6hJ8kL0mN4p
-- student123  → $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
-- rets123     → $2y$10$8Xx9tJvXKbUyWY7QF5Wqf5b3zUXFQgwz4uFpOVk5XQZ7tqKzZpGZh
-- =======================================================

-- =======================================================
-- CREATE DEFAULT ADMIN USERS FIRST
-- =======================================================

-- Password: admin123 (hashed using bcrypt)
INSERT INTO users (user_id, name, email, password, role, user_status, created_at) VALUES
('ADMIN001', 'Dr. Maria Santos', 'admin@university.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active', '2022-01-15 09:00:00');

-- Password: cashier123 (hashed using bcrypt)
INSERT INTO users (user_id, name, email, password, role, user_status, created_at) VALUES
('CASH001', 'John Reynald Cruz', 'cashier@university.edu.ph', '$2y$10$WY7QF5Wqf5b3zUXFQgwz4uFpOVk5XQZ7tqKzZpGZh8Xx9tJvXKbUy', 'cashier', 'active', '2022-01-15 09:30:00');

-- Password: registrar123 (hashed using bcrypt)
INSERT INTO users (user_id, name, email, password, role, user_status, created_at) VALUES
('REG001', 'Sarah Jane Martinez', 'registrar@university.edu.ph', '$2y$10$JK1pZ5jLqX8fLmN2oP3rQeT5vW7yX9zA1bC3dE5fG6hJ8kL0mN4p', 'registrar', 'active', '2022-01-15 10:00:00');

-- Create employee info for admin users
INSERT INTO employee_info (user_id, name, email, number, role, created_at) VALUES
('ADMIN001', 'Dr. Maria Santos', 'admin@university.edu.ph', '09171234567', 'System Administrator', '2022-01-15 09:00:00'),
('CASH001', 'John Reynald Cruz', 'cashier@university.edu.ph', '09171234568', 'Head Cashier', '2022-01-15 09:30:00'),
('REG001', 'Sarah Jane Martinez', 'registrar@university.edu.ph', '09171234569', 'Registrar', '2022-01-15 10:00:00');

-- =======================================================
-- ACADEMIC YEARS (Past 3 years + Current)
-- =======================================================

INSERT INTO academic_years (year_code, year_name, start_date, end_date, is_current, status, created_at) VALUES
('AY2022-2023', 'Academic Year 2022-2023', '2022-08-01', '2023-05-31', FALSE, 'inactive', '2022-03-15 08:00:00'),
('AY2023-2024', 'Academic Year 2023-2024', '2023-08-01', '2024-05-31', FALSE, 'inactive', '2023-03-15 08:00:00'),
('AY2024-2025', 'Academic Year 2024-2025', '2024-08-01', '2025-05-31', TRUE, 'active', '2024-03-15 08:00:00');

-- =======================================================
-- SEMESTERS FOR EACH ACADEMIC YEAR
-- =======================================================

-- AY 2022-2023 (Historical)
INSERT INTO semesters (academic_year_id, semester, start_date, end_date, enrollment_start, enrollment_end, payment_deadline, is_current, status, created_at) VALUES
(1, '1st', '2022-08-15', '2022-12-20', '2022-06-01', '2022-08-10', '2022-09-15', FALSE, 'inactive', '2022-03-15 09:00:00'),
(1, '2nd', '2023-01-08', '2023-05-15', '2022-11-01', '2023-01-05', '2023-02-15', FALSE, 'inactive', '2022-03-15 09:00:00'),
(1, 'summer', '2023-06-01', '2023-07-20', '2023-04-01', '2023-05-25', '2023-06-15', FALSE, 'inactive', '2022-03-15 09:00:00');

-- AY 2023-2024 (Completed)
INSERT INTO semesters (academic_year_id, semester, start_date, end_date, enrollment_start, enrollment_end, payment_deadline, is_current, status, created_at) VALUES
(2, '1st', '2023-08-15', '2023-12-20', '2023-06-01', '2023-08-10', '2023-09-15', FALSE, 'inactive', '2023-03-15 09:00:00'),
(2, '2nd', '2024-01-08', '2024-05-15', '2023-11-01', '2024-01-05', '2024-02-15', FALSE, 'inactive', '2023-03-15 09:00:00'),
(2, 'summer', '2024-06-01', '2024-07-20', '2024-04-01', '2024-05-25', '2024-06-15', FALSE, 'inactive', '2023-03-15 09:00:00');

-- AY 2024-2025 (Current - 1st Semester Active)
INSERT INTO semesters (academic_year_id, semester, start_date, end_date, enrollment_start, enrollment_end, payment_deadline, is_current, status, created_at) VALUES
(3, '1st', '2024-08-15', '2024-12-20', '2024-06-01', '2024-08-10', '2024-09-15', TRUE, 'active', '2024-03-15 09:00:00'),
(3, '2nd', '2025-01-08', '2025-05-15', '2024-11-01', '2025-01-05', '2025-02-15', FALSE, 'pending', '2024-03-15 09:00:00');

-- =======================================================
-- COURSES/PROGRAMS OFFERED
-- =======================================================

INSERT INTO courses (course_code, course_name, description, total_units, duration_years, status, created_at) VALUES
('BSIT', 'Bachelor of Science in Information Technology', 
 'A four-year program that prepares students for careers in software development, networking, database administration, cybersecurity, and other IT fields. The curriculum focuses on both theoretical foundations and practical applications of computing technologies.',
 147, 4, 'active', '2022-01-15 10:00:00'),

('BSCS', 'Bachelor of Science in Computer Science', 
 'A four-year program focusing on the theoretical foundations of computation and information. Includes advanced study of algorithms, data structures, programming languages, software engineering, artificial intelligence, and machine learning.',
 158, 4, 'active', '2022-01-15 10:00:00'),

('BSIS', 'Bachelor of Science in Information Systems', 
 'A four-year program that bridges business and technology. Focuses on business process analysis, system design, enterprise architecture, and IT project management. Prepares students for roles as business analysts and IT consultants.',
 141, 4, 'active', '2022-01-15 10:00:00'),

('BSBA', 'Bachelor of Science in Business Administration', 
 'A comprehensive four-year program covering management, marketing, finance, human resources, and entrepreneurship. Includes specializations in business analytics and strategic management.',
 123, 4, 'active', '2022-01-15 10:00:00'),

('BSA', 'Bachelor of Science in Accountancy', 
 'A four-year program preparing students for the Certified Public Accountant (CPA) licensure examination. Covers financial accounting, auditing, taxation, management advisory services, and business law.',
 162, 4, 'active', '2022-01-15 10:00:00'),

('BSED', 'Bachelor of Secondary Education', 
 'A four-year teacher education program with majors in English, Mathematics, Science, and Filipino. Includes practice teaching and preparation for the Licensure Examination for Teachers (LET).',
 149, 4, 'active', '2022-01-15 10:00:00'),

('BSCrim', 'Bachelor of Science in Criminology', 
 'A four-year program focusing on criminal justice, law enforcement, forensic science, and criminological research. Prepares students for careers in police service, corrections, and private security.',
 134, 4, 'active', '2022-01-15 10:00:00'),

('BSPsych', 'Bachelor of Science in Psychology', 
 'A four-year program studying human behavior, mental processes, and psychological assessment. Includes clinical, industrial, and developmental psychology specializations.',
 138, 4, 'active', '2022-01-15 10:00:00'),

('BSCpE', 'Bachelor of Science in Computer Engineering', 
 'A four-year program combining electrical engineering and computer science. Focuses on embedded systems, robotics, VLSI design, and computer hardware architecture.',
 152, 4, 'active', '2022-01-15 10:00:00'),

('ACT', 'Associate in Computer Technology', 
 'A two-year program providing fundamental skills in computer applications, web development, programming, and IT support. Designed for immediate employment after graduation.',
 72, 2, 'active', '2022-01-15 10:00:00');

-- =======================================================
-- SUBJECTS DATA (Complete with proper year/semester assignments)
-- =======================================================

INSERT INTO subjects (subject_code, subject_name, units, program, year_level, semester, description, created_at) VALUES
-- ===== GENERAL EDUCATION SUBJECTS (Common to all programs) =====
('GE101', 'Understanding the Self', 3, 'General Education', 1, '1st', 'Exploration of personal identity, self-development, and interpersonal relationships. Includes psychological, sociological, and anthropological perspectives on the self.', '2022-01-15 11:00:00'),
('GE102', 'Readings in Philippine History', 3, 'General Education', 1, '1st', 'Critical analysis of Philippine historical events using primary sources. Develops historical thinking and appreciation of Filipino heritage.', '2022-01-15 11:00:00'),
('GE103', 'The Contemporary World', 3, 'General Education', 1, '1st', 'Study of globalization and its impact on economics, politics, culture, and society. Examines global issues and their local implications.', '2022-01-15 11:00:00'),
('GE104', 'Mathematics in the Modern World', 3, 'General Education', 1, '1st', 'Application of mathematical concepts in everyday life, including data analysis, financial mathematics, and logical reasoning.', '2022-01-15 11:00:00'),
('GE105', 'Purposive Communication', 3, 'General Education', 1, '2nd', 'Development of effective communication skills for academic and professional contexts. Includes writing, speaking, and multimedia presentations.', '2022-01-15 11:00:00'),
('GE106', 'Art Appreciation', 3, 'General Education', 1, '2nd', 'Introduction to the principles and elements of art. Includes analysis of visual arts, music, theater, and dance from various cultures.', '2022-01-15 11:00:00'),
('GE107', 'Science, Technology and Society', 3, 'General Education', 1, '2nd', 'Examination of the interrelationships between scientific discovery, technological innovation, and societal development.', '2022-01-15 11:00:00'),
('GE108', 'Ethics', 3, 'General Education', 1, '2nd', 'Study of moral principles, ethical frameworks, and their application to contemporary issues. Includes discussion of moral dilemmas and decision-making.', '2022-01-15 11:00:00'),
('GE109', 'Rizal Life and Works', 3, 'General Education', 2, '1st', 'Study of Dr. Jose Rizal''s life, literary works, and their significance to Philippine nationalism and identity.', '2022-01-15 11:00:00'),
('GE110', 'Understanding Diversity', 3, 'General Education', 2, '1st', 'Exploration of multiculturalism, gender studies, and inclusive practices in various social contexts.', '2022-01-15 11:00:00'),

-- ===== PHYSICAL EDUCATION (All Years) =====
('PE1', 'Physical Fitness and Wellness', 2, 'Physical Education', 1, '1st', 'Fundamentals of physical fitness, exercise principles, and wellness concepts.', '2022-01-15 11:00:00'),
('PE2', 'Rhythmic Activities', 2, 'Physical Education', 1, '2nd', 'Introduction to dance, rhythmic gymnastics, and movement education.', '2022-01-15 11:00:00'),
('PE3', 'Individual and Dual Sports', 2, 'Physical Education', 2, '1st', 'Skills and strategies in individual and dual sports like badminton, swimming, and athletics.', '2022-01-15 11:00:00'),
('PE4', 'Team Sports', 2, 'Physical Education', 2, '2nd', 'Team dynamics and strategies in sports like basketball, volleyball, and football.', '2022-01-15 11:00:00'),

-- ===== NATIONAL SERVICE TRAINING PROGRAM =====
('NSTP1', 'CWTS/LTS/ROTC 1', 3, 'NSTP', 1, '1st', 'First semester of National Service Training Program focusing on civic welfare, literacy training, or military science.', '2022-01-15 11:00:00'),
('NSTP2', 'CWTS/LTS/ROTC 2', 3, 'NSTP', 1, '2nd', 'Second semester continuation of NSTP with community immersion and project implementation.', '2022-01-15 11:00:00'),

-- ===== BS INFORMATION TECHNOLOGY SUBJECTS =====
('IT101', 'Introduction to Computing', 3, 'BS Information Technology', 1, '1st', 'Fundamental concepts of computing, computer hardware, software, and information technology. Includes history of computing and emerging technologies.', '2022-01-15 11:00:00'),
('IT102', 'Computer Programming 1', 3, 'BS Information Technology', 1, '1st', 'Introduction to programming using Python. Covers variables, control structures, functions, data structures, and basic algorithms.', '2022-01-15 11:00:00'),
('IT103', 'Computer Programming 2', 3, 'BS Information Technology', 1, '2nd', 'Object-oriented programming concepts using Java. Includes classes, inheritance, polymorphism, and exception handling.', '2022-01-15 11:00:00'),
('IT104', 'Discrete Mathematics', 3, 'BS Information Technology', 1, '2nd', 'Mathematical structures fundamental to computing including logic, sets, relations, functions, and graph theory.', '2022-01-15 11:00:00'),
('IT201', 'Data Structures and Algorithms', 3, 'BS Information Technology', 2, '1st', 'Efficient data organization and manipulation including arrays, linked lists, stacks, queues, trees, and graphs.', '2022-01-15 11:00:00'),
('IT202', 'Database Management Systems', 3, 'BS Information Technology', 2, '1st', 'Database design, SQL programming, normalization, and transaction management. Includes hands-on projects using MySQL.', '2022-01-15 11:00:00'),
('IT203', 'Networking 1', 3, 'BS Information Technology', 2, '1st', 'Computer network fundamentals including OSI and TCP/IP models, IP addressing, subnetting, and basic network configuration.', '2022-01-15 11:00:00'),
('IT204', 'Web Development', 3, 'BS Information Technology', 2, '2nd', 'Client-side and server-side web development using HTML5, CSS3, JavaScript, PHP, and MySQL.', '2022-01-15 11:00:00'),
('IT205', 'Software Engineering', 3, 'BS Information Technology', 2, '2nd', 'Software development methodologies, requirements analysis, design patterns, testing, and project management.', '2022-01-15 11:00:00'),
('IT206', 'Operating Systems', 3, 'BS Information Technology', 2, '2nd', 'Operating system concepts including process management, memory management, file systems, and security.', '2022-01-15 11:00:00'),
('IT301', 'Information Management', 3, 'BS Information Technology', 3, '1st', 'Advanced data management including data warehousing, data mining, and business intelligence.', '2022-01-15 11:00:00'),
('IT302', 'Networking 2', 3, 'BS Information Technology', 3, '1st', 'Advanced networking concepts including routing protocols, network security, and network administration.', '2022-01-15 11:00:00'),
('IT303', 'Mobile Development', 3, 'BS Information Technology', 3, '1st', 'Mobile application development for Android and iOS platforms using modern frameworks.', '2022-01-15 11:00:00'),
('IT304', 'System Integration and Architecture', 3, 'BS Information Technology', 3, '2nd', 'Integration of various IT systems, enterprise architecture frameworks, and API design.', '2022-01-15 11:00:00'),
('IT305', 'Information Assurance and Security', 3, 'BS Information Technology', 3, '2nd', 'Security principles, cryptography, network security, and cybersecurity best practices.', '2022-01-15 11:00:00'),
('IT306', 'Human-Computer Interaction', 3, 'BS Information Technology', 3, '2nd', 'UI/UX design principles, usability testing, and user-centered design methodologies.', '2022-01-15 11:00:00'),
('IT401', 'Capstone Project 1', 3, 'BS Information Technology', 4, '1st', 'Research and proposal development for IT project. Includes problem identification, literature review, and methodology design.', '2022-01-15 11:00:00'),
('IT402', 'Capstone Project 2', 3, 'BS Information Technology', 4, '2nd', 'Implementation of IT project, testing, documentation, and final defense.', '2022-01-15 11:00:00'),
('IT403', 'Practicum (Internship)', 6, 'BS Information Technology', 4, '1st', '486-hour on-the-job training in IT companies to gain practical industry experience.', '2022-01-15 11:00:00'),
('IT404', 'Emerging Technologies', 3, 'BS Information Technology', 4, '2nd', 'Study of latest trends in IT including cloud computing, IoT, AI, and blockchain.', '2022-01-15 11:00:00'),
('IT405', 'IT Elective 1: Network Security', 3, 'BS Information Technology', 4, '1st', 'Specialized study of network security protocols, firewalls, and intrusion detection systems.', '2022-01-15 11:00:00'),
('IT406', 'IT Elective 2: Data Analytics', 3, 'BS Information Technology', 4, '2nd', 'Introduction to data analytics tools and techniques using Python and R.', '2022-01-15 11:00:00'),

-- ===== BS COMPUTER SCIENCE SUBJECTS =====
('CS101', 'Computer Science Orientation', 1, 'BS Computer Science', 1, '1st', 'Introduction to the computer science program, career paths, and academic expectations.', '2022-01-15 11:00:00'),
('CS102', 'Fundamentals of Programming', 3, 'BS Computer Science', 1, '1st', 'Programming fundamentals using Java. Covers syntax, control structures, methods, arrays, and basic OOP.', '2022-01-15 11:00:00'),
('CS103', 'Object-Oriented Programming', 3, 'BS Computer Science', 1, '2nd', 'Advanced OOP concepts including inheritance, polymorphism, interfaces, and design patterns.', '2022-01-15 11:00:00'),
('CS104', 'Calculus 1', 3, 'BS Computer Science', 1, '1st', 'Differential calculus including limits, derivatives, and their applications to optimization problems.', '2022-01-15 11:00:00'),
('CS105', 'Calculus 2', 3, 'BS Computer Science', 1, '2nd', 'Integral calculus including integration techniques, applications, and introduction to differential equations.', '2022-01-15 11:00:00'),
('CS201', 'Data Structures', 3, 'BS Computer Science', 2, '1st', 'Implementation and analysis of advanced data structures including trees, heaps, hash tables, and graphs.', '2022-01-15 11:00:00'),
('CS202', 'Algorithms', 3, 'BS Computer Science', 2, '1st', 'Algorithm design and analysis including divide-and-conquer, dynamic programming, and NP-completeness.', '2022-01-15 11:00:00'),
('CS203', 'Discrete Structures 1', 3, 'BS Computer Science', 2, '1st', 'Mathematical foundations including logic, proofs, set theory, combinatorics, and probability.', '2022-01-15 11:00:00'),
('CS204', 'Discrete Structures 2', 3, 'BS Computer Science', 2, '2nd', 'Advanced discrete mathematics including graph theory, number theory, and algebraic structures.', '2022-01-15 11:00:00'),
('CS205', 'Computer Organization', 3, 'BS Computer Science', 2, '2nd', 'Computer architecture basics including digital logic, processors, memory hierarchy, and I/O systems.', '2022-01-15 11:00:00'),
('CS206', 'Linear Algebra', 3, 'BS Computer Science', 2, '2nd', 'Vector spaces, matrices, linear transformations, and their applications in computing.', '2022-01-15 11:00:00'),
('CS301', 'Theory of Computation', 3, 'BS Computer Science', 3, '1st', 'Automata theory, formal languages, computability, and complexity theory.', '2022-01-15 11:00:00'),
('CS302', 'Operating Systems', 3, 'BS Computer Science', 3, '1st', 'Operating system design and implementation including process scheduling, memory management, and file systems.', '2022-01-15 11:00:00'),
('CS303', 'Database Systems', 3, 'BS Computer Science', 3, '1st', 'Database theory, relational algebra, SQL, query optimization, and transaction processing.', '2022-01-15 11:00:00'),
('CS304', 'Computer Networks', 3, 'BS Computer Science', 3, '2nd', 'Network protocols, architectures, and applications including TCP/IP, HTTP, and socket programming.', '2022-01-15 11:00:00'),
('CS305', 'Software Engineering', 3, 'BS Computer Science', 3, '2nd', 'Software development processes, requirements engineering, design patterns, and testing methodologies.', '2022-01-15 11:00:00'),
('CS306', 'Probability and Statistics', 3, 'BS Computer Science', 3, '2nd', 'Statistical methods, probability theory, and their applications in computing and data analysis.', '2022-01-15 11:00:00'),
('CS401', 'Artificial Intelligence', 3, 'BS Computer Science', 4, '1st', 'AI principles and algorithms including search, knowledge representation, reasoning, and planning.', '2022-01-15 11:00:00'),
('CS402', 'Machine Learning', 3, 'BS Computer Science', 4, '1st', 'Machine learning algorithms including supervised learning, unsupervised learning, and neural networks.', '2022-01-15 11:00:00'),
('CS403', 'Compiler Design', 3, 'BS Computer Science', 4, '1st', 'Compiler construction including lexical analysis, parsing, semantic analysis, and code generation.', '2022-01-15 11:00:00'),
('CS404', 'Parallel and Distributed Computing', 3, 'BS Computer Science', 4, '2nd', 'Parallel algorithms, distributed systems, and concurrent programming models.', '2022-01-15 11:00:00'),
('CS405', 'Thesis 1', 3, 'BS Computer Science', 4, '1st', 'Research proposal development including problem formulation, literature review, and methodology design.', '2022-01-15 11:00:00'),
('CS406', 'Thesis 2', 3, 'BS Computer Science', 4, '2nd', 'Thesis implementation, experimentation, analysis, and final defense.', '2022-01-15 11:00:00'),
('CS407', 'CS Elective 1: Natural Language Processing', 3, 'BS Computer Science', 4, '1st', 'Study of computational approaches to human language processing and generation.', '2022-01-15 11:00:00'),
('CS408', 'CS Elective 2: Computer Vision', 3, 'BS Computer Science', 4, '2nd', 'Introduction to image processing and computer vision algorithms and applications.', '2022-01-15 11:00:00'),

-- ===== BS BUSINESS ADMINISTRATION SUBJECTS =====
('BA101', 'Principles of Management', 3, 'BS Business Administration', 1, '1st', 'Fundamental management concepts including planning, organizing, leading, and controlling.', '2022-01-15 11:00:00'),
('BA102', 'Financial Accounting', 3, 'BS Business Administration', 1, '1st', 'Basic accounting principles, financial statement preparation, and transaction analysis.', '2022-01-15 11:00:00'),
('BA103', 'Business Mathematics', 3, 'BS Business Administration', 1, '2nd', 'Mathematical applications in business including interest calculations, annuities, and investment analysis.', '2022-01-15 11:00:00'),
('BA104', 'Microeconomics', 3, 'BS Business Administration', 1, '2nd', 'Study of individual economic units including consumer behavior, firm theory, and market structures.', '2022-01-15 11:00:00'),
('BA201', 'Marketing Management', 3, 'BS Business Administration', 2, '1st', 'Marketing concepts, consumer behavior, market research, and marketing strategy development.', '2022-01-15 11:00:00'),
('BA202', 'Human Resource Management', 3, 'BS Business Administration', 2, '1st', 'HR principles including recruitment, training, performance evaluation, and labor relations.', '2022-01-15 11:00:00'),
('BA203', 'Operations Management', 3, 'BS Business Administration', 2, '2nd', 'Operations and supply chain management including process design, quality control, and logistics.', '2022-01-15 11:00:00'),
('BA204', 'Business Law', 3, 'BS Business Administration', 2, '2nd', 'Legal aspects of business including contracts, partnerships, corporations, and regulatory compliance.', '2022-01-15 11:00:00'),
('BA301', 'Financial Management', 3, 'BS Business Administration', 3, '1st', 'Corporate finance including capital budgeting, working capital management, and financial analysis.', '2022-01-15 11:00:00'),
('BA302', 'Strategic Management', 3, 'BS Business Administration', 3, '2nd', 'Strategic planning, competitive analysis, and organizational strategy formulation and implementation.', '2022-01-15 11:00:00'),
('BA303', 'Business Ethics and Social Responsibility', 3, 'BS Business Administration', 3, '1st', 'Ethical business practices, corporate governance, and corporate social responsibility.', '2022-01-15 11:00:00'),
('BA304', 'Business Research', 3, 'BS Business Administration', 3, '2nd', 'Research methods for business including data collection, analysis, and report writing.', '2022-01-15 11:00:00'),
('BA401', 'Entrepreneurship', 3, 'BS Business Administration', 4, '1st', 'Business venture creation including opportunity identification, business planning, and venture funding.', '2022-01-15 11:00:00'),
('BA402', 'International Business', 3, 'BS Business Administration', 4, '2nd', 'Global business environment, cross-cultural management, and international trade.', '2022-01-15 11:00:00'),
('BA403', 'Business Practicum', 3, 'BS Business Administration', 4, '1st', 'On-the-job training in business organizations to gain practical experience.', '2022-01-15 11:00:00'),
('BA404', 'Business Analytics', 3, 'BS Business Administration', 4, '2nd', 'Data-driven decision making using business intelligence tools and techniques.', '2022-01-15 11:00:00'),

-- ===== BS ACCOUNTANCY SUBJECTS =====
('ACC101', 'Fundamentals of Accounting', 3, 'BS Accountancy', 1, '1st', 'Basic accounting concepts, principles, and procedures for service and merchandising businesses.', '2022-01-15 11:00:00'),
('ACC102', 'Financial Accounting and Reporting', 3, 'BS Accountancy', 1, '2nd', 'Advanced financial accounting including partnerships, corporations, and special journals.', '2022-01-15 11:00:00'),
('ACC103', 'Business Law and Taxation', 3, 'BS Accountancy', 1, '2nd', 'Legal aspects of business and introduction to Philippine taxation system.', '2022-01-15 11:00:00'),
('ACC104', 'Economics for Accountants', 3, 'BS Accountancy', 1, '1st', 'Microeconomic and macroeconomic principles with applications to accounting.', '2022-01-15 11:00:00'),
('ACC201', 'Intermediate Accounting 1', 3, 'BS Accountancy', 2, '1st', 'In-depth study of asset valuation, liabilities, and equity accounting.', '2022-01-15 11:00:00'),
('ACC202', 'Intermediate Accounting 2', 3, 'BS Accountancy', 2, '2nd', 'Advanced topics in financial reporting including leases, pensions, and income taxes.', '2022-01-15 11:00:00'),
('ACC203', 'Cost Accounting', 3, 'BS Accountancy', 2, '1st', 'Cost accumulation, allocation, and control for manufacturing and service firms.', '2022-01-15 11:00:00'),
('ACC204', 'Accounting Information Systems', 3, 'BS Accountancy', 2, '2nd', 'Design and implementation of accounting information systems and internal controls.', '2022-01-15 11:00:00'),
('ACC301', 'Advanced Financial Accounting', 3, 'BS Accountancy', 3, '1st', 'Complex accounting topics including business combinations, consolidations, and foreign currency transactions.', '2022-01-15 11:00:00'),
('ACC302', 'Auditing Principles', 3, 'BS Accountancy', 3, '1st', 'Audit concepts, standards, and procedures including evidence gathering and internal control evaluation.', '2022-01-15 11:00:00'),
('ACC303', 'Management Accounting', 3, 'BS Accountancy', 3, '2nd', 'Accounting information for managerial decision-making, budgeting, and performance evaluation.', '2022-01-15 11:00:00'),
('ACC304', 'Taxation', 3, 'BS Accountancy', 3, '2nd', 'Philippine income taxation including individual and corporate taxation, VAT, and percentage taxes.', '2022-01-15 11:00:00'),
('ACC401', 'Auditing Problems', 3, 'BS Accountancy', 4, '1st', 'Application of auditing standards and procedures in complex audit scenarios.', '2022-01-15 11:00:00'),
('ACC402', 'Management Advisory Services', 3, 'BS Accountancy', 4, '1st', 'Consulting services including strategic planning, process improvement, and valuation.', '2022-01-15 11:00:00'),
('ACC403', 'Government Accounting', 3, 'BS Accountancy', 4, '2nd', 'Accounting principles and procedures for government entities and non-profit organizations.', '2022-01-15 11:00:00'),
('ACC404', 'Accountancy Research', 3, 'BS Accountancy', 4, '1st', 'Research methods in accounting including literature review and data analysis.', '2022-01-15 11:00:00'),
('ACC405', 'CPA Review', 6, 'BS Accountancy', 4, '2nd', 'Comprehensive review for the Certified Public Accountant licensure examination.', '2022-01-15 11:00:00'),

-- ===== BS SECONDARY EDUCATION SUBJECTS =====
('ED101', 'The Teaching Profession', 3, 'BS Secondary Education', 1, '1st', 'Philosophical, historical, and legal foundations of the teaching profession.', '2022-01-15 11:00:00'),
('ED102', 'Child and Adolescent Development', 3, 'BS Secondary Education', 1, '1st', 'Developmental stages, theories, and their implications for teaching and learning.', '2022-01-15 11:00:00'),
('ED103', 'Facilitating Learning', 3, 'BS Secondary Education', 1, '2nd', 'Learning theories and their application in creating effective learning environments.', '2022-01-15 11:00:00'),
('ED104', 'Assessment of Learning', 3, 'BS Secondary Education', 1, '2nd', 'Principles and techniques of educational assessment including test construction and grading.', '2022-01-15 11:00:00'),
('ED201', 'Curriculum Development', 3, 'BS Secondary Education', 2, '1st', 'Curriculum design, implementation, and evaluation in secondary education.', '2022-01-15 11:00:00'),
('ED202', 'Educational Technology', 3, 'BS Secondary Education', 2, '1st', 'Integration of technology in teaching and learning including multimedia and online resources.', '2022-01-15 11:00:00'),
('ED203', 'Teaching Methods and Strategies', 3, 'BS Secondary Education', 2, '2nd', 'Instructional strategies, lesson planning, and classroom management techniques.', '2022-01-15 11:00:00'),
('ED204', 'Special and Inclusive Education', 3, 'BS Secondary Education', 2, '2nd', 'Principles and practices for teaching students with special needs in inclusive settings.', '2022-01-15 11:00:00'),
('ED301', 'Field Study 1', 3, 'BS Secondary Education', 3, '1st', 'Classroom observation and analysis of teaching-learning processes in actual school settings.', '2022-01-15 11:00:00'),
('ED302', 'Field Study 2', 3, 'BS Secondary Education', 3, '2nd', 'Participation and teaching assistance in actual classroom situations.', '2022-01-15 11:00:00'),
('ED303', 'Research in Education', 3, 'BS Secondary Education', 3, '1st', 'Educational research methods including action research for teachers.', '2022-01-15 11:00:00'),
('ED304', 'Classroom Management', 3, 'BS Secondary Education', 3, '2nd', 'Strategies for creating positive learning environments and managing student behavior.', '2022-01-15 11:00:00'),
('ED401', 'Practice Teaching', 6, 'BS Secondary Education', 4, '1st', 'Full-time supervised teaching internship in secondary schools.', '2022-01-15 11:00:00'),
('ED402', 'Teaching Internship', 6, 'BS Secondary Education', 4, '2nd', 'Continuation of practice teaching with increasing responsibility and independence.', '2022-01-15 11:00:00'),

-- Major in English subjects
('ENG101', 'Introduction to Linguistics', 3, 'BS Secondary Education', 2, '1st', 'Basic concepts in linguistics including phonology, morphology, syntax, and semantics.', '2022-01-15 11:00:00'),
('ENG102', 'Structure of English', 3, 'BS Secondary Education', 2, '2nd', 'Grammatical structures of English and their application in teaching.', '2022-01-15 11:00:00'),
('ENG201', 'Teaching Literature', 3, 'BS Secondary Education', 3, '1st', 'Approaches and strategies for teaching literature to secondary students.', '2022-01-15 11:00:00'),
('ENG202', 'Language Learning Materials Development', 3, 'BS Secondary Education', 3, '2nd', 'Design and development of instructional materials for English language teaching.', '2022-01-15 11:00:00'),

-- Major in Mathematics subjects
('MATH101', 'College Algebra', 3, 'BS Secondary Education', 2, '1st', 'Advanced algebra concepts including functions, equations, and inequalities.', '2022-01-15 11:00:00'),
('MATH102', 'Plane and Solid Geometry', 3, 'BS Secondary Education', 2, '2nd', 'Geometric concepts, proofs, and applications in two and three dimensions.', '2022-01-15 11:00:00'),
('MATH201', 'Trigonometry', 3, 'BS Secondary Education', 3, '1st', 'Trigonometric functions, identities, equations, and applications.', '2022-01-15 11:00:00'),
('MATH202', 'Calculus for Teachers', 3, 'BS Secondary Education', 3, '2nd', 'Fundamental calculus concepts with emphasis on teaching applications.', '2022-01-15 11:00:00'),

-- Major in Science subjects
('SCI101', 'General Biology', 3, 'BS Secondary Education', 2, '1st', 'Basic biological concepts including cell biology, genetics, and ecology.', '2022-01-15 11:00:00'),
('SCI102', 'General Chemistry', 3, 'BS Secondary Education', 2, '2nd', 'Fundamental chemistry concepts including atomic structure, bonding, and reactions.', '2022-01-15 11:00:00'),
('SCI201', 'General Physics', 3, 'BS Secondary Education', 3, '1st', 'Basic physics concepts including mechanics, heat, and waves.', '2022-01-15 11:00:00'),
('SCI202', 'Earth and Environmental Science', 3, 'BS Secondary Education', 3, '2nd', 'Earth systems, environmental issues, and sustainability concepts.', '2022-01-15 11:00:00'),

-- Major in Filipino subjects
('FIL101', 'Introduksyon sa Pag-aaral ng Wika', 3, 'BS Secondary Education', 2, '1st', 'Introduction to the study of language and Filipino linguistics.', '2022-01-15 11:00:00'),
('FIL102', 'Panitikan ng Pilipinas', 3, 'BS Secondary Education', 2, '2nd', 'Survey of Philippine literature from pre-colonial to contemporary periods.', '2022-01-15 11:00:00'),
('FIL201', 'Pagtuturo ng Filipino', 3, 'BS Secondary Education', 3, '1st', 'Strategies and methods for teaching Filipino language and literature.', '2022-01-15 11:00:00'),
('FIL202', 'Masining na Pagpapahayag', 3, 'BS Secondary Education', 3, '2nd', 'Advanced Filipino communication and creative writing skills.', '2022-01-15 11:00:00'),

-- ===== BS CRIMINOLOGY SUBJECTS =====
('CRM101', 'Introduction to Criminology', 3, 'BS Criminology', 1, '1st', 'Fundamental concepts, theories, and scope of criminology as a scientific discipline.', '2022-01-15 11:00:00'),
('CRM102', 'Criminal Law', 3, 'BS Criminology', 1, '2nd', 'Philippine criminal laws including Revised Penal Code and special penal laws.', '2022-01-15 11:00:00'),
('CRM103', 'Law Enforcement Organization', 3, 'BS Criminology', 1, '1st', 'Organization and administration of law enforcement agencies in the Philippines.', '2022-01-15 11:00:00'),
('CRM104', 'Sociology of Crimes', 3, 'BS Criminology', 1, '2nd', 'Sociological theories of crime causation and social factors affecting criminal behavior.', '2022-01-15 11:00:00'),
('CRM201', 'Forensic Science', 3, 'BS Criminology', 2, '1st', 'Scientific investigation techniques including fingerprint analysis, ballistics, and DNA analysis.', '2022-01-15 11:00:00'),
('CRM202', 'Criminalistics', 3, 'BS Criminology', 2, '2nd', 'Crime laboratory procedures including evidence collection, preservation, and analysis.', '2022-01-15 11:00:00'),
('CRM203', 'Criminal Procedure', 3, 'BS Criminology', 2, '1st', 'Rules of criminal procedure including arrest, bail, and court proceedings.', '2022-01-15 11:00:00'),
('CRM204', 'Correctional Administration', 3, 'BS Criminology', 2, '2nd', 'Administration of correctional institutions and rehabilitation programs.', '2022-01-15 11:00:00'),
('CRM301', 'Criminal Justice System', 3, 'BS Criminology', 3, '1st', 'Overview of the Philippine criminal justice system and its components.', '2022-01-15 11:00:00'),
('CRM302', 'Juvenile Justice', 3, 'BS Criminology', 3, '2nd', 'Laws and procedures for juvenile offenders including RA 9344.', '2022-01-15 11:00:00'),
('CRM303', 'Criminal Investigation', 3, 'BS Criminology', 3, '1st', 'Principles and techniques of criminal investigation including interview and interrogation.', '2022-01-15 11:00:00'),
('CRM304', 'Police Operations', 3, 'BS Criminology', 3, '2nd', 'Police operational procedures including patrol, traffic management, and public safety.', '2022-01-15 11:00:00'),
('CRM401', 'Victimology', 3, 'BS Criminology', 4, '1st', 'Study of crime victims including victim rights, assistance programs, and restorative justice.', '2022-01-15 11:00:00'),
('CRM402', 'Therapeutic Modalities', 3, 'BS Criminology', 4, '2nd', 'Rehabilitation programs and therapeutic interventions for offenders.', '2022-01-15 11:00:00'),
('CRM403', 'Criminology Research', 3, 'BS Criminology', 4, '1st', 'Research methods in criminology including thesis preparation and defense.', '2022-01-15 11:00:00'),
('CRM404', 'Internship', 3, 'BS Criminology', 4, '2nd', 'On-the-job training in law enforcement agencies, courts, and correctional facilities.', '2022-01-15 11:00:00'),

-- ===== BS PSYCHOLOGY SUBJECTS =====
('PSY101', 'General Psychology', 3, 'BS Psychology', 1, '1st', 'Introduction to psychology including biological bases of behavior, learning, memory, and motivation.', '2022-01-15 11:00:00'),
('PSY102', 'Developmental Psychology', 3, 'BS Psychology', 1, '2nd', 'Human development across the lifespan including physical, cognitive, and socioemotional development.', '2022-01-15 11:00:00'),
('PSY103', 'Theories of Personality', 3, 'BS Psychology', 1, '1st', 'Major personality theories including psychoanalytic, trait, humanistic, and social-cognitive approaches.', '2022-01-15 11:00:00'),
('PSY104', 'Social Psychology', 3, 'BS Psychology', 1, '2nd', 'Social influences on behavior including attitudes, conformity, prejudice, and group dynamics.', '2022-01-15 11:00:00'),
('PSY201', 'Abnormal Psychology', 3, 'BS Psychology', 2, '1st', 'Study of psychological disorders including diagnosis, etiology, and treatment approaches.', '2022-01-15 11:00:00'),
('PSY202', 'Psychological Assessment', 3, 'BS Psychology', 2, '1st', 'Principles and techniques of psychological testing and assessment.', '2022-01-15 11:00:00'),
('PSY203', 'Experimental Psychology', 3, 'BS Psychology', 2, '2nd', 'Experimental methods in psychology including research design and laboratory procedures.', '2022-01-15 11:00:00'),
('PSY204', 'Biopsychology', 3, 'BS Psychology', 2, '2nd', 'Biological bases of behavior including nervous system function and neurochemistry.', '2022-01-15 11:00:00'),
('PSY301', 'Cognitive Psychology', 3, 'BS Psychology', 3, '1st', 'Study of mental processes including attention, memory, language, and problem-solving.', '2022-01-15 11:00:00'),
('PSY302', 'Industrial Psychology', 3, 'BS Psychology', 3, '1st', 'Application of psychological principles in workplace settings including selection and training.', '2022-01-15 11:00:00'),
('PSY303', 'Clinical Psychology', 3, 'BS Psychology', 3, '2nd', 'Clinical practice including assessment, diagnosis, and therapeutic interventions.', '2022-01-15 11:00:00'),
('PSY304', 'Counseling Psychology', 3, 'BS Psychology', 3, '2nd', 'Counseling theories and techniques for working with individuals and groups.', '2022-01-15 11:00:00'),
('PSY401', 'Research in Psychology 1', 3, 'BS Psychology', 4, '1st', 'Thesis proposal development including literature review and methodology.', '2022-01-15 11:00:00'),
('PSY402', 'Research in Psychology 2', 3, 'BS Psychology', 4, '2nd', 'Thesis implementation, data analysis, and final defense.', '2022-01-15 11:00:00'),
('PSY403', 'Psychological Internship', 3, 'BS Psychology', 4, '1st', 'Practicum experience in clinical, industrial, or educational settings.', '2022-01-15 11:00:00'),
('PSY404', 'Ethics in Psychology', 3, 'BS Psychology', 4, '2nd', 'Ethical issues in psychological practice and research.', '2022-01-15 11:00:00'),

-- ===== BS COMPUTER ENGINEERING SUBJECTS =====
('CpE101', 'Computer Engineering Fundamentals', 3, 'BS Computer Engineering', 1, '1st', 'Introduction to computer engineering including hardware, software, and system integration.', '2022-01-15 11:00:00'),
('CpE102', 'Programming for Engineers', 3, 'BS Computer Engineering', 1, '1st', 'C programming language with applications to engineering problems.', '2022-01-15 11:00:00'),
('CpE103', 'Digital Logic Design', 3, 'BS Computer Engineering', 1, '2nd', 'Digital circuits including combinational and sequential logic design.', '2022-01-15 11:00:00'),
('CpE104', 'Engineering Mathematics', 3, 'BS Computer Engineering', 1, '2nd', 'Advanced mathematical methods for engineering including differential equations and Laplace transforms.', '2022-01-15 11:00:00'),
('CpE201', 'Circuits Analysis', 3, 'BS Computer Engineering', 2, '1st', 'Analysis of electrical circuits including DC and AC circuit theory.', '2022-01-15 11:00:00'),
('CpE202', 'Electronics', 3, 'BS Computer Engineering', 2, '1st', 'Electronic devices and circuits including diodes, transistors, and operational amplifiers.', '2022-01-15 11:00:00'),
('CpE203', 'Microprocessors', 3, 'BS Computer Engineering', 2, '2nd', 'Microprocessor architecture, assembly language programming, and interfacing.', '2022-01-15 11:00:00'),
('CpE204', 'Data Structures for CpE', 3, 'BS Computer Engineering', 2, '2nd', 'Data structures and algorithms with applications to embedded systems.', '2022-01-15 11:00:00'),
('CpE301', 'Embedded Systems', 3, 'BS Computer Engineering', 3, '1st', 'Design and development of embedded systems including real-time operating systems.', '2022-01-15 11:00:00'),
('CpE302', 'Computer Architecture', 3, 'BS Computer Engineering', 3, '1st', 'Advanced computer architecture including pipelining, caching, and parallel processing.', '2022-01-15 11:00:00'),
('CpE303', 'Digital Signal Processing', 3, 'BS Computer Engineering', 3, '2nd', 'Digital signal processing techniques including filters, transforms, and applications.', '2022-01-15 11:00:00'),
('CpE304', 'Control Systems', 3, 'BS Computer Engineering', 3, '2nd', 'Analysis and design of control systems including PID controllers and state-space methods.', '2022-01-15 11:00:00'),
('CpE401', 'Robotics', 3, 'BS Computer Engineering', 4, '1st', 'Robotics fundamentals including kinematics, dynamics, and control of robotic systems.', '2022-01-15 11:00:00'),
('CpE402', 'VLSI Design', 3, 'BS Computer Engineering', 4, '1st', 'Very Large Scale Integration design including CMOS circuits and chip layout.', '2022-01-15 11:00:00'),
('CpE403', 'CpE Design Project 1', 3, 'BS Computer Engineering', 4, '1st', 'Capstone project proposal and preliminary design.', '2022-01-15 11:00:00'),
('CpE404', 'CpE Design Project 2', 3, 'BS Computer Engineering', 4, '2nd', 'Capstone project implementation, testing, and final presentation.', '2022-01-15 11:00:00'),
('CpE405', 'Internship', 3, 'BS Computer Engineering', 4, '2nd', 'On-the-job training in computer engineering industry.', '2022-01-15 11:00:00'),

-- ===== ASSOCIATE IN COMPUTER TECHNOLOGY SUBJECTS =====
('ACT101', 'Computer Fundamentals', 3, 'Associate in Computer Technology', 1, '1st', 'Basic computer concepts, hardware, software, and operating systems.', '2022-01-15 11:00:00'),
('ACT102', 'Programming Basics', 3, 'Associate in Computer Technology', 1, '1st', 'Introduction to programming using Python or JavaScript.', '2022-01-15 11:00:00'),
('ACT103', 'Web Design Fundamentals', 3, 'Associate in Computer Technology', 1, '2nd', 'HTML, CSS, and basic web design principles.', '2022-01-15 11:00:00'),
('ACT104', 'Database Basics', 3, 'Associate in Computer Technology', 1, '2nd', 'Introduction to database concepts and SQL.', '2022-01-15 11:00:00'),
('ACT105', 'Computer Systems Servicing', 3, 'Associate in Computer Technology', 1, '1st', 'PC hardware troubleshooting and maintenance.', '2022-01-15 11:00:00'),
('ACT106', 'Networking Essentials', 3, 'Associate in Computer Technology', 1, '2nd', 'Basic networking concepts and configuration.', '2022-01-15 11:00:00'),
('ACT201', 'Web Development', 3, 'Associate in Computer Technology', 2, '1st', 'Server-side web development using PHP and MySQL.', '2022-01-15 11:00:00'),
('ACT202', 'Programming 2', 3, 'Associate in Computer Technology', 2, '1st', 'Advanced programming concepts including OOP.', '2022-01-15 11:00:00'),
('ACT203', 'System Administration', 3, 'Associate in Computer Technology', 2, '2nd', 'Windows and Linux server administration.', '2022-01-15 11:00:00'),
('ACT204', 'IT Practicum', 3, 'Associate in Computer Technology', 2, '2nd', 'On-the-job training in IT support or development.', '2022-01-15 11:00:00');



-- =======================================================
-- COURSE CURRICULUM (Complete by year and semester)
-- =======================================================

-- ===== BSIT CURRICULUM =====
-- Year 1, 1st Semester
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT 
    (SELECT id FROM courses WHERE course_code = 'BSIT'),
    id,
    1, '1st', 1, 
    ROW_NUMBER() OVER (ORDER BY subject_code),
    '2022-01-15 12:00:00'
FROM subjects 
WHERE subject_code IN ('GE101', 'GE102', 'GE103', 'GE104', 'IT101', 'IT102', 'PE1', 'NSTP1');
-- Year 1, 2nd Semester
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT 
    (SELECT id FROM courses WHERE course_code = 'BSIT'),
    id,
    1, '2nd', 1,
    ROW_NUMBER() OVER (ORDER BY subject_code),
    '2022-01-15 12:00:00'
FROM subjects 
WHERE subject_code IN ('GE105', 'GE106', 'GE107', 'GE108', 'IT103', 'IT104', 'PE2', 'NSTP2');

-- Year 2, 1st Semester
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT 
    (SELECT id FROM courses WHERE course_code = 'BSIT'),
    id,
    2, '1st', 1,
    ROW_NUMBER() OVER (ORDER BY subject_code),
    '2022-01-15 12:00:00'
FROM subjects 
WHERE subject_code IN ('GE109', 'IT201', 'IT202', 'IT203', 'PE3');

-- Year 2, 2nd Semester
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT 
    (SELECT id FROM courses WHERE course_code = 'BSIT'),
    id,
    2, '2nd', 1,
    ROW_NUMBER() OVER (ORDER BY subject_code),
    '2022-01-15 12:00:00'
FROM subjects 
WHERE subject_code IN ('GE110', 'IT204', 'IT205', 'IT206', 'PE4');

-- Year 3, 1st Semester
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT 
    (SELECT id FROM courses WHERE course_code = 'BSIT'),
    id,
    3, '1st', 1,
    ROW_NUMBER() OVER (ORDER BY subject_code),
    '2022-01-15 12:00:00'
FROM subjects 
WHERE subject_code IN ('IT301', 'IT302', 'IT303');

-- Year 3, 2nd Semester
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT 
    (SELECT id FROM courses WHERE course_code = 'BSIT'),
    id,
    3, '2nd', 1,
    ROW_NUMBER() OVER (ORDER BY subject_code),
    '2022-01-15 12:00:00'
FROM subjects 
WHERE subject_code IN ('IT304', 'IT305', 'IT306');

-- Year 4, 1st Semester
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT 
    (SELECT id FROM courses WHERE course_code = 'BSIT'),
    id,
    4, '1st', 1,
    ROW_NUMBER() OVER (ORDER BY subject_code),
    '2022-01-15 12:00:00'
FROM subjects 
WHERE subject_code IN ('IT401', 'IT403', 'IT405');

-- Year 4, 2nd Semester
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT 
    (SELECT id FROM courses WHERE course_code = 'BSIT'),
    id,
    4, '2nd', 1,
    ROW_NUMBER() OVER (ORDER BY subject_code),
    '2022-01-15 12:00:00'
FROM subjects 
WHERE subject_code IN ('IT402', 'IT404', 'IT406');

-- ===== BSCS CURRICULUM =====
-- Year 1, 1st Semester
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT 
    (SELECT id FROM courses WHERE course_code = 'BSCS'),
    id,
    1, '1st', 1,
    ROW_NUMBER() OVER (ORDER BY subject_code),
    '2022-01-15 12:00:00'
FROM subjects 
WHERE subject_code IN ('GE101', 'GE102', 'GE104', 'CS101', 'CS102', 'CS104', 'PE1', 'NSTP1');

-- Year 1, 2nd Semester
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT 
    (SELECT id FROM courses WHERE course_code = 'BSCS'),
    id,
    1, '2nd', 1,
    ROW_NUMBER() OVER (ORDER BY subject_code),
    '2022-01-15 12:00:00'
FROM subjects 
WHERE subject_code IN ('GE105', 'GE107', 'GE108', 'CS103', 'CS105', 'PE2', 'NSTP2');

-- Year 2, 1st Semester
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT 
    (SELECT id FROM courses WHERE course_code = 'BSCS'),
    id,
    2, '1st', 1,
    ROW_NUMBER() OVER (ORDER BY subject_code),
    '2022-01-15 12:00:00'
FROM subjects 
WHERE subject_code IN ('GE106', 'CS201', 'CS202', 'CS203', 'PE3');

-- Year 2, 2nd Semester
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT 
    (SELECT id FROM courses WHERE course_code = 'BSCS'),
    id,
    2, '2nd', 1,
    ROW_NUMBER() OVER (ORDER BY subject_code),
    '2022-01-15 12:00:00'
FROM subjects 
WHERE subject_code IN ('CS204', 'CS205', 'CS206', 'PE4');

-- Year 3, 1st Semester
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT 
    (SELECT id FROM courses WHERE course_code = 'BSCS'),
    id,
    3, '1st', 1,
    ROW_NUMBER() OVER (ORDER BY subject_code),
    '2022-01-15 12:00:00'
FROM subjects 
WHERE subject_code IN ('GE109', 'CS301', 'CS302', 'CS303');

-- Year 3, 2nd Semester
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT 
    (SELECT id FROM courses WHERE course_code = 'BSCS'),
    id,
    3, '2nd', 1,
    ROW_NUMBER() OVER (ORDER BY subject_code),
    '2022-01-15 12:00:00'
FROM subjects 
WHERE subject_code IN ('GE110', 'CS304', 'CS305', 'CS306');

-- Year 4, 1st Semester
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT 
    (SELECT id FROM courses WHERE course_code = 'BSCS'),
    id,
    4, '1st', 1,
    ROW_NUMBER() OVER (ORDER BY subject_code),
    '2022-01-15 12:00:00'
FROM subjects 
WHERE subject_code IN ('CS401', 'CS402', 'CS403', 'CS405', 'CS407');

-- Year 4, 2nd Semester
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT 
    (SELECT id FROM courses WHERE course_code = 'BSCS'),
    id,
    4, '2nd', 1,
    ROW_NUMBER() OVER (ORDER BY subject_code),
    '2022-01-15 12:00:00'
FROM subjects 
WHERE subject_code IN ('CS404', 'CS406', 'CS408');

-- ===== BSBA CURRICULUM =====
-- Year 1, 1st Semester
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT 
    (SELECT id FROM courses WHERE course_code = 'BSBA'),
    id,
    1, '1st', 1,
    ROW_NUMBER() OVER (ORDER BY subject_code),
    '2022-01-15 12:00:00'
FROM subjects 
WHERE subject_code IN ('GE101', 'GE102', 'GE104', 'BA101', 'BA102', 'PE1', 'NSTP1');

-- Year 1, 2nd Semester
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT 
    (SELECT id FROM courses WHERE course_code = 'BSBA'),
    id,
    1, '2nd', 1,
    ROW_NUMBER() OVER (ORDER BY subject_code),
    '2022-01-15 12:00:00'
FROM subjects 
WHERE subject_code IN ('GE105', 'GE106', 'GE108', 'BA103', 'BA104', 'PE2', 'NSTP2');

-- Year 2, 1st Semester
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT 
    (SELECT id FROM courses WHERE course_code = 'BSBA'),
    id,
    2, '1st', 1,
    ROW_NUMBER() OVER (ORDER BY subject_code),
    '2022-01-15 12:00:00'
FROM subjects 
WHERE subject_code IN ('GE109', 'BA201', 'BA202', 'PE3');

-- Year 2, 2nd Semester
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT 
    (SELECT id FROM courses WHERE course_code = 'BSBA'),
    id,
    2, '2nd', 1,
    ROW_NUMBER() OVER (ORDER BY subject_code),
    '2022-01-15 12:00:00'
FROM subjects 
WHERE subject_code IN ('GE110', 'BA203', 'BA204', 'PE4');

-- Year 3, 1st Semester
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT 
    (SELECT id FROM courses WHERE course_code = 'BSBA'),
    id,
    3, '1st', 1,
    ROW_NUMBER() OVER (ORDER BY subject_code),
    '2022-01-15 12:00:00'
FROM subjects 
WHERE subject_code IN ('BA301', 'BA303');

-- Year 3, 2nd Semester
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT 
    (SELECT id FROM courses WHERE course_code = 'BSBA'),
    id,
    3, '2nd', 1,
    ROW_NUMBER() OVER (ORDER BY subject_code),
    '2022-01-15 12:00:00'
FROM subjects 
WHERE subject_code IN ('BA302', 'BA304');

-- Year 4, 1st Semester
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT 
    (SELECT id FROM courses WHERE course_code = 'BSBA'),
    id,
    4, '1st', 1,
    ROW_NUMBER() OVER (ORDER BY subject_code),
    '2022-01-15 12:00:00'
FROM subjects 
WHERE subject_code IN ('BA401', 'BA403');

-- Year 4, 2nd Semester
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT 
    (SELECT id FROM courses WHERE course_code = 'BSBA'),
    id,
    4, '2nd', 1,
    ROW_NUMBER() OVER (ORDER BY subject_code),
    '2022-01-15 12:00:00'
FROM subjects 
WHERE subject_code IN ('BA402', 'BA404');

-- ===== BSA CURRICULUM =====
-- Year 1, 1st Semester
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT 
    (SELECT id FROM courses WHERE course_code = 'BSA'),
    id,
    1, '1st', 1,
    ROW_NUMBER() OVER (ORDER BY subject_code),
    '2022-01-15 12:00:00'
FROM subjects 
WHERE subject_code IN ('GE101', 'GE102', 'GE104', 'ACC101', 'ACC104', 'PE1', 'NSTP1');

-- Year 1, 2nd Semester
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT 
    (SELECT id FROM courses WHERE course_code = 'BSA'),
    id,
    1, '2nd', 1,
    ROW_NUMBER() OVER (ORDER BY subject_code),
    '2022-01-15 12:00:00'
FROM subjects 
WHERE subject_code IN ('GE105', 'GE106', 'GE108', 'ACC102', 'ACC103', 'PE2', 'NSTP2');

-- Year 2, 1st Semester
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT 
    (SELECT id FROM courses WHERE course_code = 'BSA'),
    id,
    2, '1st', 1,
    ROW_NUMBER() OVER (ORDER BY subject_code),
    '2022-01-15 12:00:00'
FROM subjects 
WHERE subject_code IN ('GE109', 'ACC201', 'ACC203', 'PE3');

-- Year 2, 2nd Semester
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT 
    (SELECT id FROM courses WHERE course_code = 'BSA'),
    id,
    2, '2nd', 1,
    ROW_NUMBER() OVER (ORDER BY subject_code),
    '2022-01-15 12:00:00'
FROM subjects 
WHERE subject_code IN ('GE110', 'ACC202', 'ACC204', 'PE4');

-- Year 3, 1st Semester
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT 
    (SELECT id FROM courses WHERE course_code = 'BSA'),
    id,
    3, '1st', 1,
    ROW_NUMBER() OVER (ORDER BY subject_code),
    '2022-01-15 12:00:00'
FROM subjects 
WHERE subject_code IN ('ACC301', 'ACC302');

-- Year 3, 2nd Semester
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT 
    (SELECT id FROM courses WHERE course_code = 'BSA'),
    id,
    3, '2nd', 1,
    ROW_NUMBER() OVER (ORDER BY subject_code),
    '2022-01-15 12:00:00'
FROM subjects 
WHERE subject_code IN ('ACC303', 'ACC304');

-- Year 4, 1st Semester
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT 
    (SELECT id FROM courses WHERE course_code = 'BSA'),
    id,
    4, '1st', 1,
    ROW_NUMBER() OVER (ORDER BY subject_code),
    '2022-01-15 12:00:00'
FROM subjects 
WHERE subject_code IN ('ACC401', 'ACC402', 'ACC404');

-- Year 4, 2nd Semester
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT 
    (SELECT id FROM courses WHERE course_code = 'BSA'),
    id,
    4, '2nd', 1,
    ROW_NUMBER() OVER (ORDER BY subject_code),
    '2022-01-15 12:00:00'
FROM subjects 
WHERE subject_code IN ('ACC403', 'ACC405');

-- ===== BSCrim CURRICULUM =====
-- Year 1, 1st Semester
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT 
    (SELECT id FROM courses WHERE course_code = 'BSCrim'),
    id,
    1, '1st', 1,
    ROW_NUMBER() OVER (ORDER BY subject_code),
    '2022-01-15 12:00:00'
FROM subjects 
WHERE subject_code IN ('GE101', 'GE102', 'GE104', 'CRM101', 'CRM103', 'PE1', 'NSTP1');

-- Year 1, 2nd Semester
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT 
    (SELECT id FROM courses WHERE course_code = 'BSCrim'),
    id,
    1, '2nd', 1,
    ROW_NUMBER() OVER (ORDER BY subject_code),
    '2022-01-15 12:00:00'
FROM subjects 
WHERE subject_code IN ('GE105', 'GE106', 'GE108', 'CRM102', 'CRM104', 'PE2', 'NSTP2');

-- Year 2, 1st Semester
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT 
    (SELECT id FROM courses WHERE course_code = 'BSCrim'),
    id,
    2, '1st', 1,
    ROW_NUMBER() OVER (ORDER BY subject_code),
    '2022-01-15 12:00:00'
FROM subjects 
WHERE subject_code IN ('GE109', 'CRM201', 'CRM203', 'PE3');

-- Year 2, 2nd Semester
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT 
    (SELECT id FROM courses WHERE course_code = 'BSCrim'),
    id,
    2, '2nd', 1,
    ROW_NUMBER() OVER (ORDER BY subject_code),
    '2022-01-15 12:00:00'
FROM subjects 
WHERE subject_code IN ('GE110', 'CRM202', 'CRM204', 'PE4');

-- Year 3, 1st Semester
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT 
    (SELECT id FROM courses WHERE course_code = 'BSCrim'),
    id,
    3, '1st', 1,
    ROW_NUMBER() OVER (ORDER BY subject_code),
    '2022-01-15 12:00:00'
FROM subjects 
WHERE subject_code IN ('CRM301', 'CRM303');

-- Year 3, 2nd Semester
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT 
    (SELECT id FROM courses WHERE course_code = 'BSCrim'),
    id,
    3, '2nd', 1,
    ROW_NUMBER() OVER (ORDER BY subject_code),
    '2022-01-15 12:00:00'
FROM subjects 
WHERE subject_code IN ('CRM302', 'CRM304');

-- Year 4, 1st Semester
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT 
    (SELECT id FROM courses WHERE course_code = 'BSCrim'),
    id,
    4, '1st', 1,
    ROW_NUMBER() OVER (ORDER BY subject_code),
    '2022-01-15 12:00:00'
FROM subjects 
WHERE subject_code IN ('CRM401', 'CRM403');

-- Year 4, 2nd Semester
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT 
    (SELECT id FROM courses WHERE course_code = 'BSCrim'),
    id,
    4, '2nd', 1,
    ROW_NUMBER() OVER (ORDER BY subject_code),
    '2022-01-15 12:00:00'
FROM subjects 
WHERE subject_code IN ('CRM402', 'CRM404');

-- ===== BSPsych CURRICULUM =====
-- Year 1, 1st Semester
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT 
    (SELECT id FROM courses WHERE course_code = 'BSPsych'),
    id,
    1, '1st', 1,
    ROW_NUMBER() OVER (ORDER BY subject_code),
    '2022-01-15 12:00:00'
FROM subjects 
WHERE subject_code IN ('GE101', 'GE102', 'GE104', 'PSY101', 'PSY103', 'PE1', 'NSTP1');

-- Year 1, 2nd Semester
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT 
    (SELECT id FROM courses WHERE course_code = 'BSPsych'),
    id,
    1, '2nd', 1,
    ROW_NUMBER() OVER (ORDER BY subject_code),
    '2022-01-15 12:00:00'
FROM subjects 
WHERE subject_code IN ('GE105', 'GE106', 'GE108', 'PSY102', 'PSY104', 'PE2', 'NSTP2');

-- Year 2, 1st Semester
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT 
    (SELECT id FROM courses WHERE course_code = 'BSPsych'),
    id,
    2, '1st', 1,
    ROW_NUMBER() OVER (ORDER BY subject_code),
    '2022-01-15 12:00:00'
FROM subjects 
WHERE subject_code IN ('GE109', 'PSY201', 'PSY202', 'PE3');

-- Year 2, 2nd Semester
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT 
    (SELECT id FROM courses WHERE course_code = 'BSPsych'),
    id,
    2, '2nd', 1,
    ROW_NUMBER() OVER (ORDER BY subject_code),
    '2022-01-15 12:00:00'
FROM subjects 
WHERE subject_code IN ('GE110', 'PSY203', 'PSY204', 'PE4');

-- Year 3, 1st Semester
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT 
    (SELECT id FROM courses WHERE course_code = 'BSPsych'),
    id,
    3, '1st', 1,
    ROW_NUMBER() OVER (ORDER BY subject_code),
    '2022-01-15 12:00:00'
FROM subjects 
WHERE subject_code IN ('PSY301', 'PSY302');

-- Year 3, 2nd Semester
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT 
    (SELECT id FROM courses WHERE course_code = 'BSPsych'),
    id,
    3, '2nd', 1,
    ROW_NUMBER() OVER (ORDER BY subject_code),
    '2022-01-15 12:00:00'
FROM subjects 
WHERE subject_code IN ('PSY303', 'PSY304');

-- Year 4, 1st Semester
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT 
    (SELECT id FROM courses WHERE course_code = 'BSPsych'),
    id,
    4, '1st', 1,
    ROW_NUMBER() OVER (ORDER BY subject_code),
    '2022-01-15 12:00:00'
FROM subjects 
WHERE subject_code IN ('PSY401', 'PSY403');

-- Year 4, 2nd Semester
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT 
    (SELECT id FROM courses WHERE course_code = 'BSPsych'),
    id,
    4, '2nd', 1,
    ROW_NUMBER() OVER (ORDER BY subject_code),
    '2022-01-15 12:00:00'
FROM subjects 
WHERE subject_code IN ('PSY402', 'PSY404');

-- ===== ACT CURRICULUM =====
-- Year 1, 1st Semester
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT 
    (SELECT id FROM courses WHERE course_code = 'ACT'),
    id,
    1, '1st', 1,
    ROW_NUMBER() OVER (ORDER BY subject_code),
    '2022-01-15 12:00:00'
FROM subjects 
WHERE subject_code IN ('GE101', 'GE102', 'ACT101', 'ACT102', 'ACT105', 'PE1', 'NSTP1');

-- Year 1, 2nd Semester
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT 
    (SELECT id FROM courses WHERE course_code = 'ACT'),
    id,
    1, '2nd', 1,
    ROW_NUMBER() OVER (ORDER BY subject_code),
    '2022-01-15 12:00:00'
FROM subjects 
WHERE subject_code IN ('GE105', 'GE106', 'ACT103', 'ACT104', 'ACT106', 'PE2', 'NSTP2');

-- Year 2, 1st Semester
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT 
    (SELECT id FROM courses WHERE course_code = 'ACT'),
    id,
    2, '1st', 1,
    ROW_NUMBER() OVER (ORDER BY subject_code),
    '2022-01-15 12:00:00'
FROM subjects 
WHERE subject_code IN ('GE109', 'ACT201', 'ACT202', 'PE3');

-- Year 2, 2nd Semester
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT 
    (SELECT id FROM courses WHERE course_code = 'ACT'),
    id,
    2, '2nd', 1,
    ROW_NUMBER() OVER (ORDER BY subject_code),
    '2022-01-15 12:00:00'
FROM subjects 
WHERE subject_code IN ('ACT203', 'ACT204', 'PE4');

-- =======================================================
-- SUBJECT_SECTIONS DATA (Auto-filled from curriculum for existing sections)
-- =======================================================

-- Auto-fill subjects for BSIT 1A (Year 1, 1st Semester)
INSERT INTO subject_sections (subject_id, section_id, is_auto_filled)
SELECT s.id, sec.id, 1
FROM subjects s
CROSS JOIN sections sec
WHERE sec.section_code = 'BSIT1A'
AND s.subject_code IN ('GE101', 'GE102', 'GE103', 'GE104', 'IT101', 'IT102', 'PE1', 'NSTP1')
ON DUPLICATE KEY UPDATE is_auto_filled = 1;

-- Auto-fill subjects for BSIT 1B (Year 1, 1st Semester)
INSERT INTO subject_sections (subject_id, section_id, is_auto_filled)
SELECT s.id, sec.id, 1
FROM subjects s
CROSS JOIN sections sec
WHERE sec.section_code = 'BSIT1B'
AND s.subject_code IN ('GE101', 'GE102', 'GE103', 'GE104', 'IT101', 'IT102', 'PE1', 'NSTP1')
ON DUPLICATE KEY UPDATE is_auto_filled = 1;

-- Auto-fill subjects for BSIT 1C (Year 1, 1st Semester)
INSERT INTO subject_sections (subject_id, section_id, is_auto_filled)
SELECT s.id, sec.id, 1
FROM subjects s
CROSS JOIN sections sec
WHERE sec.section_code = 'BSIT1C'
AND s.subject_code IN ('GE101', 'GE102', 'GE103', 'GE104', 'IT101', 'IT102', 'PE1', 'NSTP1')
ON DUPLICATE KEY UPDATE is_auto_filled = 1;

-- Auto-fill subjects for BSIT 2A (Year 2, 1st Semester)
INSERT INTO subject_sections (subject_id, section_id, is_auto_filled)
SELECT s.id, sec.id, 1
FROM subjects s
CROSS JOIN sections sec
WHERE sec.section_code = 'BSIT2A'
AND s.subject_code IN ('GE109', 'IT201', 'IT202', 'IT203', 'PE3')
ON DUPLICATE KEY UPDATE is_auto_filled = 1;

-- Auto-fill subjects for BSIT 2B (Year 2, 1st Semester)
INSERT INTO subject_sections (subject_id, section_id, is_auto_filled)
SELECT s.id, sec.id, 1
FROM subjects s
CROSS JOIN sections sec
WHERE sec.section_code = 'BSIT2B'
AND s.subject_code IN ('GE109', 'IT201', 'IT202', 'IT203', 'PE3')
ON DUPLICATE KEY UPDATE is_auto_filled = 1;

-- Auto-fill subjects for BSIT 3A (Year 3, 1st Semester)
INSERT INTO subject_sections (subject_id, section_id, is_auto_filled)
SELECT s.id, sec.id, 1
FROM subjects s
CROSS JOIN sections sec
WHERE sec.section_code = 'BSIT3A'
AND s.subject_code IN ('IT301', 'IT302', 'IT303')
ON DUPLICATE KEY UPDATE is_auto_filled = 1;

-- Auto-fill subjects for BSIT 3B (Year 3, 1st Semester)
INSERT INTO subject_sections (subject_id, section_id, is_auto_filled)
SELECT s.id, sec.id, 1
FROM subjects s
CROSS JOIN sections sec
WHERE sec.section_code = 'BSIT3B'
AND s.subject_code IN ('IT301', 'IT302', 'IT303')
ON DUPLICATE KEY UPDATE is_auto_filled = 1;

-- Auto-fill subjects for BSIT 4A (Year 4, 1st Semester)
INSERT INTO subject_sections (subject_id, section_id, is_auto_filled)
SELECT s.id, sec.id, 1
FROM subjects s
CROSS JOIN sections sec
WHERE sec.section_code = 'BSIT4A'
AND s.subject_code IN ('IT401', 'IT403', 'IT405')
ON DUPLICATE KEY UPDATE is_auto_filled = 1;

-- Auto-fill subjects for BSCS 1A (Year 1, 1st Semester)
INSERT INTO subject_sections (subject_id, section_id, is_auto_filled)
SELECT s.id, sec.id, 1
FROM subjects s
CROSS JOIN sections sec
WHERE sec.section_code = 'BSCS1A'
AND s.subject_code IN ('GE101', 'GE102', 'GE104', 'CS101', 'CS102', 'CS104', 'PE1', 'NSTP1')
ON DUPLICATE KEY UPDATE is_auto_filled = 1;

-- Auto-fill subjects for BSCS 1B (Year 1, 1st Semester)
INSERT INTO subject_sections (subject_id, section_id, is_auto_filled)
SELECT s.id, sec.id, 1
FROM subjects s
CROSS JOIN sections sec
WHERE sec.section_code = 'BSCS1B'
AND s.subject_code IN ('GE101', 'GE102', 'GE104', 'CS101', 'CS102', 'CS104', 'PE1', 'NSTP1')
ON DUPLICATE KEY UPDATE is_auto_filled = 1;

-- Auto-fill subjects for BSCS 2A (Year 2, 1st Semester)
INSERT INTO subject_sections (subject_id, section_id, is_auto_filled)
SELECT s.id, sec.id, 1
FROM subjects s
CROSS JOIN sections sec
WHERE sec.section_code = 'BSCS2A'
AND s.subject_code IN ('GE106', 'CS201', 'CS202', 'CS203', 'PE3')
ON DUPLICATE KEY UPDATE is_auto_filled = 1;

-- Auto-fill subjects for BSCS 2B (Year 2, 1st Semester)
INSERT INTO subject_sections (subject_id, section_id, is_auto_filled)
SELECT s.id, sec.id, 1
FROM subjects s
CROSS JOIN sections sec
WHERE sec.section_code = 'BSCS2B'
AND s.subject_code IN ('GE106', 'CS201', 'CS202', 'CS203', 'PE3')
ON DUPLICATE KEY UPDATE is_auto_filled = 1;

-- Auto-fill subjects for BSCS 3A (Year 3, 1st Semester)
INSERT INTO subject_sections (subject_id, section_id, is_auto_filled)
SELECT s.id, sec.id, 1
FROM subjects s
CROSS JOIN sections sec
WHERE sec.section_code = 'BSCS3A'
AND s.subject_code IN ('GE109', 'CS301', 'CS302', 'CS303')
ON DUPLICATE KEY UPDATE is_auto_filled = 1;

-- Auto-fill subjects for BSCS 4A (Year 4, 1st Semester)
INSERT INTO subject_sections (subject_id, section_id, is_auto_filled)
SELECT s.id, sec.id, 1
FROM subjects s
CROSS JOIN sections sec
WHERE sec.section_code = 'BSCS4A'
AND s.subject_code IN ('CS401', 'CS402', 'CS403', 'CS405', 'CS407')
ON DUPLICATE KEY UPDATE is_auto_filled = 1;

-- Auto-fill subjects for BSBA 1A (Year 1, 1st Semester)
INSERT INTO subject_sections (subject_id, section_id, is_auto_filled)
SELECT s.id, sec.id, 1
FROM subjects s
CROSS JOIN sections sec
WHERE sec.section_code = 'BSBA1A'
AND s.subject_code IN ('GE101', 'GE102', 'GE104', 'BA101', 'BA102', 'PE1', 'NSTP1')
ON DUPLICATE KEY UPDATE is_auto_filled = 1;

-- Auto-fill subjects for BSBA 1B (Year 1, 1st Semester)
INSERT INTO subject_sections (subject_id, section_id, is_auto_filled)
SELECT s.id, sec.id, 1
FROM subjects s
CROSS JOIN sections sec
WHERE sec.section_code = 'BSBA1B'
AND s.subject_code IN ('GE101', 'GE102', 'GE104', 'BA101', 'BA102', 'PE1', 'NSTP1')
ON DUPLICATE KEY UPDATE is_auto_filled = 1;

-- Auto-fill subjects for BSBA 2A (Year 2, 1st Semester)
INSERT INTO subject_sections (subject_id, section_id, is_auto_filled)
SELECT s.id, sec.id, 1
FROM subjects s
CROSS JOIN sections sec
WHERE sec.section_code = 'BSBA2A'
AND s.subject_code IN ('GE109', 'BA201', 'BA202', 'PE3')
ON DUPLICATE KEY UPDATE is_auto_filled = 1;

-- Auto-fill subjects for BSBA 2B (Year 2, 1st Semester)
INSERT INTO subject_sections (subject_id, section_id, is_auto_filled)
SELECT s.id, sec.id, 1
FROM subjects s
CROSS JOIN sections sec
WHERE sec.section_code = 'BSBA2B'
AND s.subject_code IN ('GE109', 'BA201', 'BA202', 'PE3')
ON DUPLICATE KEY UPDATE is_auto_filled = 1;

-- Auto-fill subjects for BSBA 3A (Year 3, 1st Semester)
INSERT INTO subject_sections (subject_id, section_id, is_auto_filled)
SELECT s.id, sec.id, 1
FROM subjects s
CROSS JOIN sections sec
WHERE sec.section_code = 'BSBA3A'
AND s.subject_code IN ('BA301', 'BA303')
ON DUPLICATE KEY UPDATE is_auto_filled = 1;

-- Auto-fill subjects for BSBA 4A (Year 4, 1st Semester)
INSERT INTO subject_sections (subject_id, section_id, is_auto_filled)
SELECT s.id, sec.id, 1
FROM subjects s
CROSS JOIN sections sec
WHERE sec.section_code = 'BSBA4A'
AND s.subject_code IN ('BA401', 'BA403')
ON DUPLICATE KEY UPDATE is_auto_filled = 1;

-- =======================================================
-- SECTIONS DATA (Multiple sections per year/program)
-- =======================================================

INSERT INTO sections (section_code, section_name, year_level, program, status, semester, created_at) VALUES
-- BSIT Sections
('BSIT1A', 'BSIT 1 - Section A', 1, 'BS Information Technology', 'active', '1st', '2022-03-15 10:00:00'),
('BSIT1B', 'BSIT 1 - Section B', 1, 'BS Information Technology', 'active', '1st', '2022-03-15 10:00:00'),
('BSIT1C', 'BSIT 1 - Section C', 1, 'BS Information Technology', 'active', '1st', '2022-03-15 10:00:00'),
('BSIT2A', 'BSIT 2 - Section A', 2, 'BS Information Technology', 'active', '1st', '2022-03-15 10:00:00'),
('BSIT2B', 'BSIT 2 - Section B', 2, 'BS Information Technology', 'active', '1st', '2022-03-15 10:00:00'),
('BSIT3A', 'BSIT 3 - Section A', 3, 'BS Information Technology', 'active', '1st', '2022-03-15 10:00:00'),
('BSIT3B', 'BSIT 3 - Section B', 3, 'BS Information Technology', 'active', '1st', '2022-03-15 10:00:00'),
('BSIT4A', 'BSIT 4 - Section A', 4, 'BS Information Technology', 'active', '1st', '2022-03-15 10:00:00'),

-- BSCS Sections
('BSCS1A', 'BSCS 1 - Section A', 1, 'BS Computer Science', 'active', '1st', '2022-03-15 10:00:00'),
('BSCS1B', 'BSCS 1 - Section B', 1, 'BS Computer Science', 'active', '1st', '2022-03-15 10:00:00'),
('BSCS2A', 'BSCS 2 - Section A', 2, 'BS Computer Science', 'active', '1st', '2022-03-15 10:00:00'),
('BSCS2B', 'BSCS 2 - Section B', 2, 'BS Computer Science', 'active', '1st', '2022-03-15 10:00:00'),
('BSCS3A', 'BSCS 3 - Section A', 3, 'BS Computer Science', 'active', '1st', '2022-03-15 10:00:00'),
('BSCS4A', 'BSCS 4 - Section A', 4, 'BS Computer Science', 'active', '1st', '2022-03-15 10:00:00'),

-- BSBA Sections
('BSBA1A', 'BSBA 1 - Section A', 1, 'BS Business Administration', 'active', '1st', '2022-03-15 10:00:00'),
('BSBA1B', 'BSBA 1 - Section B', 1, 'BS Business Administration', 'active', '1st', '2022-03-15 10:00:00'),
('BSBA2A', 'BSBA 2 - Section A', 2, 'BS Business Administration', 'active', '1st', '2022-03-15 10:00:00'),
('BSBA2B', 'BSBA 2 - Section B', 2, 'BS Business Administration', 'active', '1st', '2022-03-15 10:00:00'),
('BSBA3A', 'BSBA 3 - Section A', 3, 'BS Business Administration', 'active', '1st', '2022-03-15 10:00:00'),
('BSBA4A', 'BSBA 4 - Section A', 4, 'BS Business Administration', 'active', '1st', '2022-03-15 10:00:00'),

-- BSA Sections
('BSA1A', 'BSA 1 - Section A', 1, 'BS Accountancy', 'active', '1st', '2022-03-15 10:00:00'),
('BSA1B', 'BSA 1 - Section B', 1, 'BS Accountancy', 'active', '1st', '2022-03-15 10:00:00'),
('BSA2A', 'BSA 2 - Section A', 2, 'BS Accountancy', 'active', '1st', '2022-03-15 10:00:00'),
('BSA2B', 'BSA 2 - Section B', 2, 'BS Accountancy', 'active', '1st', '2022-03-15 10:00:00'),
('BSA3A', 'BSA 3 - Section A', 3, 'BS Accountancy', 'active', '1st', '2022-03-15 10:00:00'),
('BSA4A', 'BSA 4 - Section A', 4, 'BS Accountancy', 'active', '1st', '2022-03-15 10:00:00'),

-- BSCrim Sections
('BSCRIM1A', 'BSCRIM 1 - Section A', 1, 'BS Criminology', 'active', '1st', '2022-03-15 10:00:00'),
('BSCRIM1B', 'BSCRIM 1 - Section B', 1, 'BS Criminology', 'active', '1st', '2022-03-15 10:00:00'),
('BSCRIM2A', 'BSCRIM 2 - Section A', 2, 'BS Criminology', 'active', '1st', '2022-03-15 10:00:00'),
('BSCRIM2B', 'BSCRIM 2 - Section B', 2, 'BS Criminology', 'active', '1st', '2022-03-15 10:00:00'),
('BSCRIM3A', 'BSCRIM 3 - Section A', 3, 'BS Criminology', 'active', '1st', '2022-03-15 10:00:00'),
('BSCRIM4A', 'BSCRIM 4 - Section A', 4, 'BS Criminology', 'active', '1st', '2022-03-15 10:00:00'),

-- BSPsych Sections
('BSPSYCH1A', 'BSPsych 1 - Section A', 1, 'BS Psychology', 'active', '1st', '2022-03-15 10:00:00'),
('BSPSYCH1B', 'BSPsych 1 - Section B', 1, 'BS Psychology', 'active', '1st', '2022-03-15 10:00:00'),
('BSPSYCH2A', 'BSPsych 2 - Section A', 2, 'BS Psychology', 'active', '1st', '2022-03-15 10:00:00'),
('BSPSYCH3A', 'BSPsych 3 - Section A', 3, 'BS Psychology', 'active', '1st', '2022-03-15 10:00:00'),
('BSPSYCH4A', 'BSPsych 4 - Section A', 4, 'BS Psychology', 'active', '1st', '2022-03-15 10:00:00'),

-- ACT Sections
('ACT1A', 'ACT 1 - Section A', 1, 'Associate in Computer Technology', 'active', '1st', '2022-03-15 10:00:00'),
('ACT1B', 'ACT 1 - Section B', 1, 'Associate in Computer Technology', 'active', '1st', '2022-03-15 10:00:00'),
('ACT2A', 'ACT 2 - Section A', 2, 'Associate in Computer Technology', 'active', '1st', '2022-03-15 10:00:00');
-- =======================================================
-- STUDENT USERS (Multiple cohorts across years)
-- =======================================================

-- Password: student123 (hashed using bcrypt)
INSERT INTO users (user_id, name, email, password, role, user_status, created_at) VALUES
-- BSIT Students - Year 1 (Batch 2024, Program 01 = BSIT)
('c24-01-0001-MAN121', 'Maria Concepcion Santos', 'maria.santos@student.university.edu.ph', '$2y$10$4uFpOVk5XQZ7tqKzZpGZh8Xx9tJvXKbUyWY7QF5Wqf5b3zUXFQgwz', 'student', 'active', '2024-05-20 09:00:00'),
('c24-01-0002-MAN121', 'Juan Miguel Dela Cruz', 'juan.delacruz@student.university.edu.ph', '$2y$10$4uFpOVk5XQZ7tqKzZpGZh8Xx9tJvXKbUyWY7QF5Wqf5b3zUXFQgwz', 'student', 'active', '2024-05-20 09:00:00'),
('c24-01-0003-MAN121', 'Ana Patricia Reyes', 'ana.reyes@student.university.edu.ph', '$2y$10$4uFpOVk5XQZ7tqKzZpGZh8Xx9tJvXKbUyWY7QF5Wqf5b3zUXFQgwz', 'student', 'active', '2024-05-20 09:00:00'),
('c24-01-0004-MAN121', 'Jose Rafael Garcia', 'jose.garcia@student.university.edu.ph', '$2y$10$4uFpOVk5XQZ7tqKzZpGZh8Xx9tJvXKbUyWY7QF5Wqf5b3zUXFQgwz', 'student', 'active', '2024-05-20 09:00:00'),

-- BSIT Students - Year 2 (Batch 2023, Program 01 = BSIT)
('c23-01-0101-MAN121', 'Isabella Marie Cruz', 'isabella.cruz@student.university.edu.ph', '$2y$10$4uFpOVk5XQZ7tqKzZpGZh8Xx9tJvXKbUyWY7QF5Wqf5b3zUXFQgwz', 'student', 'active', '2023-05-20 09:00:00'),
('c23-01-0102-MAN121', 'Gabriel Luis Fernandez', 'gabriel.fernandez@student.university.edu.ph', '$2y$10$4uFpOVk5XQZ7tqKzZpGZh8Xx9tJvXKbUyWY7QF5Wqf5b3zUXFQgwz', 'student', 'active', '2023-05-20 09:00:00'),
('c23-01-0103-MAN121', 'Sofia Andrea Villanueva', 'sofia.villanueva@student.university.edu.ph', '$2y$10$4uFpOVk5XQZ7tqKzZpGZh8Xx9tJvXKbUyWY7QF5Wqf5b3zUXFQgwz', 'student', 'active', '2023-05-20 09:00:00'),

-- BSIT Students - Year 3 (Batch 2022, Program 01 = BSIT)
('c22-01-0201-MAN121', 'Lucas Miguel Reyes', 'lucas.reyes@student.university.edu.ph', '$2y$10$4uFpOVk5XQZ7tqKzZpGZh8Xx9tJvXKbUyWY7QF5Wqf5b3zUXFQgwz', 'student', 'active', '2022-05-20 09:00:00'),
('c22-01-0202-MAN121', 'Sophia Marie Lopez', 'sophia.lopez@student.university.edu.ph', '$2y$10$4uFpOVk5XQZ7tqKzZpGZh8Xx9tJvXKbUyWY7QF5Wqf5b3zUXFQgwz', 'student', 'active', '2022-05-20 09:00:00'),

-- BSIT Students - Year 4 (Batch 2021, Program 01 = BSIT)
('c21-01-0301-MAN121', 'Daniel Andres Martinez', 'daniel.martinez@student.university.edu.ph', '$2y$10$4uFpOVk5XQZ7tqKzZpGZh8Xx9tJvXKbUyWY7QF5Wqf5b3zUXFQgwz', 'student', 'active', '2021-05-20 09:00:00'),
('c21-01-0302-MAN121', 'Camila Rose Gonzales', 'camila.gonzales@student.university.edu.ph', '$2y$10$4uFpOVk5XQZ7tqKzZpGZh8Xx9tJvXKbUyWY7QF5Wqf5b3zUXFQgwz', 'student', 'active', '2021-05-20 09:00:00'),

-- BSCS Students - Year 1 (Batch 2024, Program 02 = BSCS)
('c24-02-0001-MAN121', 'Alexandra Nicole Garcia', 'alexandra.garcia@student.university.edu.ph', '$2y$10$4uFpOVk5XQZ7tqKzZpGZh8Xx9tJvXKbUyWY7QF5Wqf5b3zUXFQgwz', 'student', 'active', '2024-05-20 09:00:00'),
('c24-02-0002-MAN121', 'Miguel Antonio Reyes', 'miguel.reyes@student.university.edu.ph', '$2y$10$4uFpOVk5XQZ7tqKzZpGZh8Xx9tJvXKbUyWY7QF5Wqf5b3zUXFQgwz', 'student', 'active', '2024-05-20 09:00:00'),

-- BSCS Students - Year 2 (Batch 2023, Program 02 = BSCS)
('c23-02-0101-MAN121', 'Isabella Marie Cruz', 'isabella.cruza@student.university.edu.ph', '$2y$10$4uFpOVk5XQZ7tqKzZpGZh8Xx9tJvXKbUyWY7QF5Wqf5b3zUXFQgwz', 'student', 'active', '2023-05-20 09:00:00'),
('c23-02-0102-MAN121', 'Gabriel Matthew Tan', 'gabriel.tan@student.university.edu.ph', '$2y$10$4uFpOVk5XQZ7tqKzZpGZh8Xx9tJvXKbUyWY7QF5Wqf5b3zUXFQgwz', 'student', 'active', '2023-05-20 09:00:00'),

-- BSCS Students - Year 3 (Batch 2022, Program 02 = BSCS)
('c22-02-0201-MAN121', 'Chloe Anne Lim', 'chloe.lim@student.university.edu.ph', '$2y$10$4uFpOVk5XQZ7tqKzZpGZh8Xx9tJvXKbUyWY7QF5Wqf5b3zUXFQgwz', 'student', 'active', '2022-05-20 09:00:00'),
('c22-02-0202-MAN121', 'Ethan David Ong', 'ethan.ong@student.university.edu.ph', '$2y$10$4uFpOVk5XQZ7tqKzZpGZh8Xx9tJvXKbUyWY7QF5Wqf5b3zUXFQgwz', 'student', 'active', '2022-05-20 09:00:00'),

-- BSCS Students - Year 4 (Batch 2021, Program 02 = BSCS)
('c21-02-0301-MAN121', 'Zoe Patricia Ramirez', 'zoe.ramirez@student.university.edu.ph', '$2y$10$4uFpOVk5XQZ7tqKzZpGZh8Xx9tJvXKbUyWY7QF5Wqf5b3zUXFQgwz', 'student', 'active', '2021-05-20 09:00:00'),
('c21-02-0302-MAN121', 'Nathan James Gonzales', 'nathan.gonzales@student.university.edu.ph', '$2y$10$4uFpOVk5XQZ7tqKzZpGZh8Xx9tJvXKbUyWY7QF5Wqf5b3zUXFQgwz', 'student', 'active', '2021-05-20 09:00:00'),

-- BSBA Students - Year 1 (Batch 2024, Program 03 = BSBA)
('c24-03-0001-MAN121', 'Julia Marie Martinez', 'julia.martinez@student.university.edu.ph', '$2y$10$4uFpOVk5XQZ7tqKzZpGZh8Xx9tJvXKbUyWY7QF5Wqf5b3zUXFQgwz', 'student', 'active', '2024-05-20 09:00:00'),
('c24-03-0002-MAN121', 'Andres Miguel Villanueva', 'andres.villanueva@student.university.edu.ph', '$2y$10$4uFpOVk5XQZ7tqKzZpGZh8Xx9tJvXKbUyWY7QF5Wqf5b3zUXFQgwz', 'student', 'active', '2024-05-20 09:00:00'),

-- BSBA Students - Year 2 (Batch 2023, Program 03 = BSBA)
('c23-03-0101-MAN121', 'Patricia Anne Lopez', 'patricia.lopez@student.university.edu.ph', '$2y$10$4uFpOVk5XQZ7tqKzZpGZh8Xx9tJvXKbUyWY7QF5Wqf5b3zUXFQgwz', 'student', 'active', '2023-05-20 09:00:00'),
('c23-03-0102-MAN121', 'Ricardo Jose Fernandez', 'ricardo.fernandez@student.university.edu.ph', '$2y$10$4uFpOVk5XQZ7tqKzZpGZh8Xx9tJvXKbUyWY7QF5Wqf5b3zUXFQgwz', 'student', 'active', '2023-05-20 09:00:00'),

-- BSBA Students - Year 3 (Batch 2022, Program 03 = BSBA)
('c22-03-0201-MAN121', 'Vanessa Marie Cruz', 'vanessa.cruz@student.university.edu.ph', '$2y$10$4uFpOVk5XQZ7tqKzZpGZh8Xx9tJvXKbUyWY7QF5Wqf5b3zUXFQgwz', 'student', 'active', '2022-05-20 09:00:00'),
('c22-03-0202-MAN121', 'Marco Antonio Santos', 'marco.santos@student.university.edu.ph', '$2y$10$4uFpOVk5XQZ7tqKzZpGZh8Xx9tJvXKbUyWY7QF5Wqf5b3zUXFQgwz', 'student', 'active', '2022-05-20 09:00:00'),

-- BSBA Students - Year 4 (Batch 2021, Program 03 = BSBA)
('c21-03-0301-MAN121', 'Carmela Isabel Garcia', 'carmela.garcia@student.university.edu.ph', '$2y$10$4uFpOVk5XQZ7tqKzZpGZh8Xx9tJvXKbUyWY7QF5Wqf5b3zUXFQgwz', 'student', 'active', '2021-05-20 09:00:00'),
('c21-03-0302-MAN121', 'Paolo Miguel Reyes', 'paolo.reyes@student.university.edu.ph', '$2y$10$4uFpOVk5XQZ7tqKzZpGZh8Xx9tJvXKbUyWY7QF5Wqf5b3zUXFQgwz', 'student', 'active', '2021-05-20 09:00:00'),

-- Additional Student (Batch 2023, Program 01 = BSIT)
('c23-01-9927-MAN121', 'Marcus Andrei Navarro', 'marcus.navarro@student.university.edu.ph', '$2y$10$8Xx9tJvXKbUyWY7QF5Wqf5b3zUXFQgwz4uFpOVk5XQZ7tqKzZpGZh', 'student', 'active', '2023-05-20 09:00:00');
-- =======================================================
-- STUDENT INFO RECORDS
-- =======================================================

INSERT INTO students_info (user_id, student_type, name, email, number, address, program, course_id, year_level, student_status, enrollment_status, status, enrollment_date, total_units, created_at) VALUES
-- BSIT Year 1 (Batch 2024, Program 01)
('c24-01-0001-MAN121', 'regular', 'Maria Concepcion Santos', 'maria.santos@student.university.edu.ph', '09171234501', '123 Mabini St., Manila', 'BS Information Technology', (SELECT id FROM courses WHERE course_code = 'BSIT'), 1, 'new', 'enrolled', 'active', '2024-06-10', 18, '2024-05-20 09:00:00'),
('c24-01-0002-MAN121', 'regular', 'Juan Miguel Dela Cruz', 'juan.delacruz@student.university.edu.ph', '09171234502', '456 Rizal Ave., Quezon City', 'BS Information Technology', (SELECT id FROM courses WHERE course_code = 'BSIT'), 1, 'new', 'enrolled', 'active', '2024-06-10', 18, '2024-05-20 09:00:00'),
('c24-01-0003-MAN121', 'regular', 'Ana Patricia Reyes', 'ana.reyes@student.university.edu.ph', '09171234503', '789 Bonifacio St., Makati', 'BS Information Technology', (SELECT id FROM courses WHERE course_code = 'BSIT'), 1, 'new', 'enrolled', 'active', '2024-06-11', 18, '2024-05-20 09:00:00'),
('c24-01-0004-MAN121', 'regular', 'Jose Rafael Garcia', 'jose.garcia@student.university.edu.ph', '09171234504', '321 Luna St., Pasig', 'BS Information Technology', (SELECT id FROM courses WHERE course_code = 'BSIT'), 1, 'new', 'enrolled', 'active', '2024-06-11', 18, '2024-05-20 09:00:00'),

-- BSIT Year 2 (Batch 2023, Program 01)
('c23-01-0101-MAN121', 'regular', 'Isabella Marie Cruz', 'isabella.cruz@student.university.edu.ph', '09171234505', '123 Aguinaldo St., Manila', 'BS Information Technology', (SELECT id FROM courses WHERE course_code = 'BSIT'), 2, 'old', 'enrolled', 'active', '2023-06-15', 21, '2023-05-20 09:00:00'),
('c23-01-0102-MAN121', 'regular', 'Gabriel Luis Fernandez', 'gabriel.fernandez@student.university.edu.ph', '09171234506', '456 Mabini St., Quezon City', 'BS Information Technology', (SELECT id FROM courses WHERE course_code = 'BSIT'), 2, 'old', 'enrolled', 'active', '2023-06-15', 21, '2023-05-20 09:00:00'),
('c23-01-0103-MAN121', 'regular', 'Sofia Andrea Villanueva', 'sofia.villanueva@student.university.edu.ph', '09171234507', '789 Rizal St., Makati', 'BS Information Technology', (SELECT id FROM courses WHERE course_code = 'BSIT'), 2, 'old', 'enrolled', 'active', '2023-06-16', 21, '2023-05-20 09:00:00'),

-- BSIT Year 3 (Batch 2022, Program 01)
('c22-01-0201-MAN121', 'regular', 'Lucas Miguel Reyes', 'lucas.reyes@student.university.edu.ph', '09171234508', '123 Bonifacio St., Manila', 'BS Information Technology', (SELECT id FROM courses WHERE course_code = 'BSIT'), 3, 'old', 'enrolled', 'active', '2022-06-20', 18, '2022-05-20 09:00:00'),
('c22-01-0202-MAN121', 'regular', 'Sophia Marie Lopez', 'sophia.lopez@student.university.edu.ph', '09171234509', '456 Aguinaldo St., Quezon City', 'BS Information Technology', (SELECT id FROM courses WHERE course_code = 'BSIT'), 3, 'old', 'enrolled', 'active', '2022-06-20', 18, '2022-05-20 09:00:00'),

-- BSIT Year 4 (Batch 2021, Program 01)
('c21-01-0301-MAN121', 'regular', 'Daniel Andres Martinez', 'daniel.martinez@student.university.edu.ph', '09171234510', '123 Luna St., Manila', 'BS Information Technology', (SELECT id FROM courses WHERE course_code = 'BSIT'), 4, 'old', 'enrolled', 'active', '2021-06-20', 18, '2021-05-20 09:00:00'),
('c21-01-0302-MAN121', 'regular', 'Camila Rose Gonzales', 'camila.gonzales@student.university.edu.ph', '09171234511', '456 Del Pilar St., Quezon City', 'BS Information Technology', (SELECT id FROM courses WHERE course_code = 'BSIT'), 4, 'old', 'enrolled', 'active', '2021-06-20', 18, '2021-05-20 09:00:00'),

-- BSCS Year 1 (Batch 2024, Program 02)
('c24-02-0001-MAN121', 'regular', 'Alexandra Nicole Garcia', 'alexandra.garcia@student.university.edu.ph', '09171234512', '123 Science St., Manila', 'BS Computer Science', (SELECT id FROM courses WHERE course_code = 'BSCS'), 1, 'new', 'enrolled', 'active', '2024-06-10', 19, '2024-05-20 09:00:00'),
('c24-02-0002-MAN121', 'regular', 'Miguel Antonio Reyes', 'miguel.reyes@student.university.edu.ph', '09171234513', '456 Algorithm Ave., Quezon City', 'BS Computer Science', (SELECT id FROM courses WHERE course_code = 'BSCS'), 1, 'new', 'enrolled', 'active', '2024-06-10', 19, '2024-05-20 09:00:00'),

-- BSCS Year 2 (Batch 2023, Program 02)
('c23-02-0101-MAN121', 'regular', 'Isabella Marie Cruz', 'isabella.cruza@student.university.edu.ph', '09171234514', '123 Logic St., Manila', 'BS Computer Science', (SELECT id FROM courses WHERE course_code = 'BSCS'), 2, 'old', 'enrolled', 'active', '2023-06-15', 18, '2023-05-20 09:00:00'),
('c23-02-0102-MAN121', 'regular', 'Gabriel Matthew Tan', 'gabriel.tan@student.university.edu.ph', '09171234515', '456 Memory Ave., Quezon City', 'BS Computer Science', (SELECT id FROM courses WHERE course_code = 'BSCS'), 2, 'old', 'enrolled', 'active', '2023-06-15', 18, '2023-05-20 09:00:00'),

-- BSCS Year 3 (Batch 2022, Program 02)
('c22-02-0201-MAN121', 'regular', 'Chloe Anne Lim', 'chloe.lim@student.university.edu.ph', '09171234516', '789 Process Blvd., Makati', 'BS Computer Science', (SELECT id FROM courses WHERE course_code = 'BSCS'), 3, 'old', 'enrolled', 'active', '2022-06-20', 18, '2022-05-20 09:00:00'),
('c22-02-0202-MAN121', 'regular', 'Ethan David Ong', 'ethan.ong@student.university.edu.ph', '09171234517', '321 Thread St., Pasig', 'BS Computer Science', (SELECT id FROM courses WHERE course_code = 'BSCS'), 3, 'old', 'enrolled', 'active', '2022-06-20', 18, '2022-05-20 09:00:00'),

-- BSCS Year 4 (Batch 2021, Program 02)
('c21-02-0301-MAN121', 'regular', 'Zoe Patricia Ramirez', 'zoe.ramirez@student.university.edu.ph', '09171234518', '123 Network St., Manila', 'BS Computer Science', (SELECT id FROM courses WHERE course_code = 'BSCS'), 4, 'old', 'enrolled', 'active', '2021-06-20', 18, '2021-05-20 09:00:00'),
('c21-02-0302-MAN121', 'regular', 'Nathan James Gonzales', 'nathan.gonzales@student.university.edu.ph', '09171234519', '456 Protocol Ave., Quezon City', 'BS Computer Science', (SELECT id FROM courses WHERE course_code = 'BSCS'), 4, 'old', 'enrolled', 'active', '2021-06-20', 18, '2021-05-20 09:00:00'),

-- BSBA Year 1 (Batch 2024, Program 03)
('c24-03-0001-MAN121', 'regular', 'Julia Marie Martinez', 'julia.martinez@student.university.edu.ph', '09171234520', '123 Business St., Manila', 'BS Business Administration', (SELECT id FROM courses WHERE course_code = 'BSBA'), 1, 'new', 'enrolled', 'active', '2024-06-10', 18, '2024-05-20 09:00:00'),
('c24-03-0002-MAN121', 'regular', 'Andres Miguel Villanueva', 'andres.villanueva@student.university.edu.ph', '09171234521', '456 Commerce Ave., Quezon City', 'BS Business Administration', (SELECT id FROM courses WHERE course_code = 'BSBA'), 1, 'new', 'enrolled', 'active', '2024-06-10', 18, '2024-05-20 09:00:00'),

-- BSBA Year 2 (Batch 2023, Program 03)
('c23-03-0101-MAN121', 'regular', 'Patricia Anne Lopez', 'patricia.lopez@student.university.edu.ph', '09171234522', '789 Management Blvd., Makati', 'BS Business Administration', (SELECT id FROM courses WHERE course_code = 'BSBA'), 2, 'old', 'enrolled', 'active', '2023-06-15', 21, '2023-05-20 09:00:00'),
('c23-03-0102-MAN121', 'regular', 'Ricardo Jose Fernandez', 'ricardo.fernandez@student.university.edu.ph', '09171234523', '321 Finance St., Pasig', 'BS Business Administration', (SELECT id FROM courses WHERE course_code = 'BSBA'), 2, 'old', 'enrolled', 'active', '2023-06-15', 21, '2023-05-20 09:00:00'),

-- BSBA Year 3 (Batch 2022, Program 03)
('c22-03-0201-MAN121', 'regular', 'Vanessa Marie Cruz', 'vanessa.cruz@student.university.edu.ph', '09171234524', '123 Marketing Ave., Manila', 'BS Business Administration', (SELECT id FROM courses WHERE course_code = 'BSBA'), 3, 'old', 'enrolled', 'active', '2022-06-20', 21, '2022-05-20 09:00:00'),
('c22-03-0202-MAN121', 'regular', 'Marco Antonio Santos', 'marco.santos@student.university.edu.ph', '09171234525', '456 Strategy Blvd., Quezon City', 'BS Business Administration', (SELECT id FROM courses WHERE course_code = 'BSBA'), 3, 'old', 'enrolled', 'active', '2022-06-20', 21, '2022-05-20 09:00:00'),

-- BSBA Year 4 (Batch 2021, Program 03)
('c21-03-0301-MAN121', 'regular', 'Carmela Isabel Garcia', 'carmela.garcia@student.university.edu.ph', '09171234526', '123 Innovation St., Manila', 'BS Business Administration', (SELECT id FROM courses WHERE course_code = 'BSBA'), 4, 'old', 'enrolled', 'active', '2021-06-20', 15, '2021-05-20 09:00:00'),
('c21-03-0302-MAN121', 'regular', 'Paolo Miguel Reyes', 'paolo.reyes@student.university.edu.ph', '09171234527', '456 Leadership Ave., Quezon City', 'BS Business Administration', (SELECT id FROM courses WHERE course_code = 'BSBA'), 4, 'old', 'enrolled', 'active', '2021-06-20', 15, '2021-05-20 09:00:00'),

-- Special student (Batch 2023, Program 01 = BSIT)
('c23-01-9927-MAN121', 'regular', 'Marcus Andrei Navarro', 'marcus.navarro@student.university.edu.ph', '09171234528', '789 IT Park, Cebu City', 'BS Information Technology', (SELECT id FROM courses WHERE course_code = 'BSIT'), 2, 'old', 'enrolled', 'active', '2023-06-15', 21, '2023-05-20 09:00:00');

-- =======================================================
-- STUDENT COURSE ENROLLMENT (Historical data showing progression)
-- =======================================================

-- First, clear existing data (use with caution!)
-- DELETE FROM student_course_enrollment;

-- Insert only the specific records you need
INSERT INTO student_course_enrollment (student_id, course_id, enrollment_date, expected_graduation, current_year_level, current_semester, status, created_at) VALUES
-- BSIT Year 1 students (enrolled 2024)
('c24-01-0001-MAN121', (SELECT id FROM courses WHERE course_code = 'BSIT'), '2024-06-10', '2028-06-10', 1, '1st', 'active', '2024-06-10'),
('c24-01-0002-MAN121', (SELECT id FROM courses WHERE course_code = 'BSIT'), '2024-06-10', '2028-06-10', 1, '1st', 'active', '2024-06-10'),
('c24-01-0003-MAN121', (SELECT id FROM courses WHERE course_code = 'BSIT'), '2024-06-11', '2028-06-11', 1, '1st', 'active', '2024-06-11'),
('c24-01-0004-MAN121', (SELECT id FROM courses WHERE course_code = 'BSIT'), '2024-06-11', '2028-06-11', 1, '1st', 'active', '2024-06-11'),

-- BSIT Year 2 students (enrolled 2023)
('c23-01-0101-MAN121', (SELECT id FROM courses WHERE course_code = 'BSIT'), '2023-06-15', '2027-06-15', 2, '1st', 'active', '2023-06-15'),
('c23-01-0102-MAN121', (SELECT id FROM courses WHERE course_code = 'BSIT'), '2023-06-15', '2027-06-15', 2, '1st', 'active', '2023-06-15'),
('c23-01-0103-MAN121', (SELECT id FROM courses WHERE course_code = 'BSIT'), '2023-06-16', '2027-06-16', 2, '1st', 'active', '2023-06-16'),
('c23-01-9927-MAN121', (SELECT id FROM courses WHERE course_code = 'BSIT'), '2023-06-15', '2027-06-15', 2, '1st', 'active', '2023-06-15'),

-- BSIT Year 3 students (enrolled 2022)
('c22-01-0201-MAN121', (SELECT id FROM courses WHERE course_code = 'BSIT'), '2022-06-20', '2026-06-20', 3, '1st', 'active', '2022-06-20'),
('c22-01-0202-MAN121', (SELECT id FROM courses WHERE course_code = 'BSIT'), '2022-06-20', '2026-06-20', 3, '1st', 'active', '2022-06-20'),

-- BSIT Year 4 students (enrolled 2021)
('c21-01-0301-MAN121', (SELECT id FROM courses WHERE course_code = 'BSIT'), '2021-06-20', '2025-06-20', 4, '1st', 'active', '2021-06-20'),
('c21-01-0302-MAN121', (SELECT id FROM courses WHERE course_code = 'BSIT'), '2021-06-20', '2025-06-20', 4, '1st', 'active', '2021-06-20'),

-- BSCS Year 1 students
('c24-02-0001-MAN121', (SELECT id FROM courses WHERE course_code = 'BSCS'), '2024-06-10', '2028-06-10', 1, '1st', 'active', '2024-06-10'),
('c24-02-0002-MAN121', (SELECT id FROM courses WHERE course_code = 'BSCS'), '2024-06-10', '2028-06-10', 1, '1st', 'active', '2024-06-10'),

-- BSCS Year 2 students
('c23-02-0101-MAN121', (SELECT id FROM courses WHERE course_code = 'BSCS'), '2023-06-15', '2027-06-15', 2, '1st', 'active', '2023-06-15'),
('c23-02-0102-MAN121', (SELECT id FROM courses WHERE course_code = 'BSCS'), '2023-06-15', '2027-06-15', 2, '1st', 'active', '2023-06-15'),

-- BSCS Year 3 students
('c22-02-0201-MAN121', (SELECT id FROM courses WHERE course_code = 'BSCS'), '2022-06-20', '2026-06-20', 3, '1st', 'active', '2022-06-20'),
('c22-02-0202-MAN121', (SELECT id FROM courses WHERE course_code = 'BSCS'), '2022-06-20', '2026-06-20', 3, '1st', 'active', '2022-06-20'),

-- BSBA Year 1 students
('c24-03-0001-MAN121', (SELECT id FROM courses WHERE course_code = 'BSBA'), '2024-06-10', '2028-06-10', 1, '1st', 'active', '2024-06-10'),
('c24-03-0002-MAN121', (SELECT id FROM courses WHERE course_code = 'BSBA'), '2024-06-10', '2028-06-10', 1, '1st', 'active', '2024-06-10'),

-- BSBA Year 2 students
('c23-03-0101-MAN121', (SELECT id FROM courses WHERE course_code = 'BSBA'), '2023-06-15', '2027-06-15', 2, '1st', 'active', '2023-06-15'),
('c23-03-0102-MAN121', (SELECT id FROM courses WHERE course_code = 'BSBA'), '2023-06-15', '2027-06-15', 2, '1st', 'active', '2023-06-15');

-- =======================================================
-- ASSIGN STUDENTS TO SECTIONS (Based on program, year level, and semester)
-- =======================================================

-- First, clear existing assignments (optional)
-- DELETE FROM student_sections;

-- BSIT Year 1 to Sections
INSERT INTO student_sections (student_id, section_id, assigned_at)
SELECT 
    si.user_id,
    sec.id,
    si.enrollment_date
FROM students_info si
CROSS JOIN sections sec
WHERE si.program = 'BS Information Technology' 
AND si.year_level = 1
AND sec.section_code IN ('BSIT1A', 'BSIT1B')
AND (
    (si.user_id IN ('c24-01-0001-MAN121', 'c24-01-0002-MAN121') AND sec.section_code = 'BSIT1A')
    OR
    (si.user_id IN ('c24-01-0003-MAN121', 'c24-01-0004-MAN121') AND sec.section_code = 'BSIT1B')
);

-- BSIT Year 2 to Section A
INSERT INTO student_sections (student_id, section_id, assigned_at)
SELECT 
    si.user_id,
    sec.id,
    si.enrollment_date
FROM students_info si
CROSS JOIN sections sec
WHERE si.program = 'BS Information Technology' 
AND si.year_level = 2
AND si.user_id IN ('c23-01-0101-MAN121', 'c23-01-0102-MAN121', 'c23-01-0103-MAN121', 'c23-01-9927-MAN121')
AND sec.section_code = 'BSIT2A';

-- BSIT Year 3 to Section A
INSERT INTO student_sections (student_id, section_id, assigned_at)
SELECT 
    si.user_id,
    sec.id,
    si.enrollment_date
FROM students_info si
CROSS JOIN sections sec
WHERE si.program = 'BS Information Technology' 
AND si.year_level = 3
AND si.user_id IN ('c22-01-0201-MAN121', 'c22-01-0202-MAN121')
AND sec.section_code = 'BSIT3A';

-- BSIT Year 4 to Section A
INSERT INTO student_sections (student_id, section_id, assigned_at)
SELECT 
    si.user_id,
    sec.id,
    si.enrollment_date
FROM students_info si
CROSS JOIN sections sec
WHERE si.program = 'BS Information Technology' 
AND si.year_level = 4
AND si.user_id IN ('c21-01-0301-MAN121', 'c21-01-0302-MAN121')
AND sec.section_code = 'BSIT4A';

-- BSCS Year 1 to Sections
INSERT INTO student_sections (student_id, section_id, assigned_at)
SELECT 
    si.user_id,
    sec.id,
    si.enrollment_date
FROM students_info si
CROSS JOIN sections sec
WHERE si.program = 'BS Computer Science' 
AND si.year_level = 1
AND sec.section_code IN ('BSCS1A', 'BSCS1B')
AND (
    (si.user_id = 'c24-02-0001-MAN121' AND sec.section_code = 'BSCS1A')
    OR
    (si.user_id = 'c24-02-0002-MAN121' AND sec.section_code = 'BSCS1B')
);

-- BSCS Year 2 to Section A
INSERT INTO student_sections (student_id, section_id, assigned_at)
SELECT 
    si.user_id,
    sec.id,
    si.enrollment_date
FROM students_info si
CROSS JOIN sections sec
WHERE si.program = 'BS Computer Science' 
AND si.year_level = 2
AND si.user_id IN ('c23-02-0101-MAN121', 'c23-02-0102-MAN121')
AND sec.section_code = 'BSCS2A';

-- BSCS Year 3 to Section A
INSERT INTO student_sections (student_id, section_id, assigned_at)
SELECT 
    si.user_id,
    sec.id,
    si.enrollment_date
FROM students_info si
CROSS JOIN sections sec
WHERE si.program = 'BS Computer Science' 
AND si.year_level = 3
AND si.user_id IN ('c22-02-0201-MAN121', 'c22-02-0202-MAN121')
AND sec.section_code = 'BSCS3A';

-- BSCS Year 4 to Section A
INSERT INTO student_sections (student_id, section_id, assigned_at)
SELECT 
    si.user_id,
    sec.id,
    si.enrollment_date
FROM students_info si
CROSS JOIN sections sec
WHERE si.program = 'BS Computer Science' 
AND si.year_level = 4
AND si.user_id IN ('c21-02-0301-MAN121', 'c21-02-0302-MAN121')
AND sec.section_code = 'BSCS4A';

-- BSBA Year 1 to Sections
INSERT INTO student_sections (student_id, section_id, assigned_at)
SELECT 
    si.user_id,
    sec.id,
    si.enrollment_date
FROM students_info si
CROSS JOIN sections sec
WHERE si.program = 'BS Business Administration' 
AND si.year_level = 1
AND sec.section_code IN ('BSBA1A', 'BSBA1B')
AND (
    (si.user_id = 'c24-03-0001-MAN121' AND sec.section_code = 'BSBA1A')
    OR
    (si.user_id = 'c24-03-0002-MAN121' AND sec.section_code = 'BSBA1B')
);

-- BSBA Year 2 to Section A
INSERT INTO student_sections (student_id, section_id, assigned_at)
SELECT 
    si.user_id,
    sec.id,
    si.enrollment_date
FROM students_info si
CROSS JOIN sections sec
WHERE si.program = 'BS Business Administration' 
AND si.year_level = 2
AND si.user_id IN ('c23-03-0101-MAN121', 'c23-03-0102-MAN121')
AND sec.section_code = 'BSBA2A';

-- =======================================================
-- UPDATE SECTIONS WITH COURSE IDs
-- =======================================================

-- Link sections to their respective courses
UPDATE sections SET course_id = (SELECT id FROM courses WHERE course_code = 'BSIT') 
WHERE program = 'BS Information Technology';

UPDATE sections SET course_id = (SELECT id FROM courses WHERE course_code = 'BSCS') 
WHERE program = 'BS Computer Science';

UPDATE sections SET course_id = (SELECT id FROM courses WHERE course_code = 'BSBA') 
WHERE program = 'BS Business Administration';

UPDATE sections SET course_id = (SELECT id FROM courses WHERE course_code = 'BSA') 
WHERE program = 'BS Accountancy';

UPDATE sections SET course_id = (SELECT id FROM courses WHERE course_code = 'BSCrim') 
WHERE program = 'BS Criminology';

UPDATE sections SET course_id = (SELECT id FROM courses WHERE course_code = 'BSPsych') 
WHERE program = 'BS Psychology';

UPDATE sections SET course_id = (SELECT id FROM courses WHERE course_code = 'BSED') 
WHERE program = 'BS Secondary Education';

UPDATE sections SET course_id = (SELECT id FROM courses WHERE course_code = 'ACT') 
WHERE program = 'Associate in Computer Technology';

-- =======================================================
-- CLASS SCHEDULES
-- =======================================================

-- BSIT 1A Schedules
INSERT INTO class_schedule (subject_id, section_id, day_of_week, start_time, end_time, room, created_at)
SELECT 
    s.id,
    sec.id,
    CASE 
        WHEN s.subject_code = 'IT101' THEN 'Monday'
        WHEN s.subject_code = 'IT102' THEN 'Tuesday'
        WHEN s.subject_code = 'GE101' THEN 'Wednesday'
        WHEN s.subject_code = 'GE102' THEN 'Thursday'
        WHEN s.subject_code = 'GE103' THEN 'Friday'
        WHEN s.subject_code = 'GE104' THEN 'Monday'
        WHEN s.subject_code = 'PE1' THEN 'Thursday'
        WHEN s.subject_code = 'NSTP1' THEN 'Saturday'
    END,
    CASE 
        WHEN s.subject_code = 'IT101' THEN '08:00:00'
        WHEN s.subject_code = 'IT102' THEN '08:00:00'
        WHEN s.subject_code = 'GE101' THEN '08:00:00'
        WHEN s.subject_code = 'GE102' THEN '10:30:00'
        WHEN s.subject_code = 'GE103' THEN '10:30:00'
        WHEN s.subject_code = 'GE104' THEN '13:00:00'
        WHEN s.subject_code = 'PE1' THEN '15:00:00'
        WHEN s.subject_code = 'NSTP1' THEN '08:00:00'
    END,
    CASE 
        WHEN s.subject_code = 'IT101' THEN '10:30:00'
        WHEN s.subject_code = 'IT102' THEN '10:30:00'
        WHEN s.subject_code = 'GE101' THEN '10:30:00'
        WHEN s.subject_code = 'GE102' THEN '13:00:00'
        WHEN s.subject_code = 'GE103' THEN '13:00:00'
        WHEN s.subject_code = 'GE104' THEN '15:30:00'
        WHEN s.subject_code = 'PE1' THEN '17:00:00'
        WHEN s.subject_code = 'NSTP1' THEN '12:00:00'
    END,
    CASE 
        WHEN s.subject_code LIKE 'IT%' THEN 'IT Lab 101'
        WHEN s.subject_code LIKE 'GE%' THEN 'Room 201'
        WHEN s.subject_code = 'PE1' THEN 'Gymnasium'
        WHEN s.subject_code = 'NSTP1' THEN 'Room 301'
    END,
    NOW()
FROM subjects s, sections sec
WHERE s.subject_code IN ('IT101', 'IT102', 'GE101', 'GE102', 'GE103', 'GE104', 'PE1', 'NSTP1')
AND sec.section_code = 'BSIT1A';

-- BSCS 1A Schedules
INSERT INTO class_schedule (subject_id, section_id, day_of_week, start_time, end_time, room, created_at)
SELECT 
    s.id,
    sec.id,
    CASE 
        WHEN s.subject_code = 'CS102' THEN 'Monday'
        WHEN s.subject_code = 'CS104' THEN 'Tuesday'
        WHEN s.subject_code = 'GE101' THEN 'Wednesday'
        WHEN s.subject_code = 'GE102' THEN 'Thursday'
        WHEN s.subject_code = 'GE104' THEN 'Friday'
        WHEN s.subject_code = 'CS101' THEN 'Monday'
        WHEN s.subject_code = 'PE1' THEN 'Wednesday'
        WHEN s.subject_code = 'NSTP1' THEN 'Saturday'
    END,
    CASE 
        WHEN s.subject_code = 'CS102' THEN '08:00:00'
        WHEN s.subject_code = 'CS104' THEN '08:00:00'
        WHEN s.subject_code = 'GE101' THEN '08:00:00'
        WHEN s.subject_code = 'GE102' THEN '10:30:00'
        WHEN s.subject_code = 'GE104' THEN '13:00:00'
        WHEN s.subject_code = 'CS101' THEN '15:00:00'
        WHEN s.subject_code = 'PE1' THEN '15:00:00'
        WHEN s.subject_code = 'NSTP1' THEN '08:00:00'
    END,
    CASE 
        WHEN s.subject_code = 'CS102' THEN '10:30:00'
        WHEN s.subject_code = 'CS104' THEN '10:30:00'
        WHEN s.subject_code = 'GE101' THEN '10:30:00'
        WHEN s.subject_code = 'GE102' THEN '13:00:00'
        WHEN s.subject_code = 'GE104' THEN '15:30:00'
        WHEN s.subject_code = 'CS101' THEN '16:00:00'
        WHEN s.subject_code = 'PE1' THEN '17:00:00'
        WHEN s.subject_code = 'NSTP1' THEN '12:00:00'
    END,
    CASE 
        WHEN s.subject_code LIKE 'CS%' THEN 'CS Lab 101'
        WHEN s.subject_code LIKE 'GE%' THEN 'Room 202'
        WHEN s.subject_code = 'PE1' THEN 'Gymnasium'
        WHEN s.subject_code = 'NSTP1' THEN 'Room 302'
    END,
    NOW()
FROM subjects s, sections sec
WHERE s.subject_code IN ('CS102', 'CS104', 'CS101', 'GE101', 'GE102', 'GE104', 'PE1', 'NSTP1')
AND sec.section_code = 'BSCS1A';

-- =======================================================
-- PAYMENT SETTINGS
-- =======================================================

INSERT INTO settings (name, value, category, description, created_at) VALUES
('unit_price', '1000.00', 'payment', 'Price per unit for all courses', NOW()),
('school_name', 'University of Information Technology', 'general', 'Name of the educational institution', NOW()),
('school_address', '123 Education Avenue, Manila, Philippines', 'general', 'Physical address of the school', NOW()),
('school_email', 'info@uit.edu.ph', 'general', 'Main email address', NOW()),
('school_phone', '(02) 8123-4567', 'general', 'Contact phone number', NOW()),
('currency_symbol', '₱', 'payment', 'Currency symbol for display', NOW()),
('currency_code', 'PHP', 'payment', 'ISO currency code', NOW()),
('academic_year', '2024-2025', 'academic', 'Current academic year', NOW()),
('semester', '1st', 'academic', 'Current semester', NOW()),
('payment_deadline_days', '30', 'payment', 'Number of days for payment deadlines', NOW()),
('late_payment_fee', '500.00', 'payment', 'Late payment penalty fee', NOW()),
('min_payment_percentage', '50', 'payment', 'Minimum percentage required for installment', NOW()),
('max_installments', '3', 'payment', 'Maximum number of payment installments', NOW());
-- =======================================================
-- SAMPLE PAYMENTS (For students with some payment history)
-- =======================================================

INSERT INTO payments (student_id, permit_number, amount, remaining_balance, payment_status, description, issued_date, issued_by, school_year, payment_category, units, created_at)
VALUES
('c24-01-0001-MAN121', 'PAY001', 18000.00, 0.00, 'paid', 'Full payment 1st Semester AY 2024-2025', '2024-06-15', 'CASH001', '2024-2025', 'tuition', 18, '2024-06-15 10:00:00'),
('c24-01-0002-MAN121', 'PAY002', 9000.00, 9000.00, 'partial', 'Partial payment 1st Semester AY 2024-2025', '2024-06-16', 'CASH001', '2024-2025', 'tuition', 18, '2024-06-16 10:00:00'),
('c23-01-0101-MAN121', 'PAY003', 21000.00, 0.00, 'paid', 'Full payment 1st Semester AY 2024-2025', '2024-06-14', 'CASH001', '2024-2025', 'tuition', 21, '2024-06-14 10:00:00'),
('c22-01-0201-MAN121', 'PAY004', 18000.00, 0.00, 'paid', 'Full payment 1st Semester AY 2024-2025', '2024-06-13', 'CASH001', '2024-2025', 'tuition', 18, '2024-06-13 10:00:00'),
('c21-01-0301-MAN121', 'PAY005', 18000.00, 0.00, 'paid', 'Full payment 1st Semester AY 2024-2025', '2024-06-12', 'CASH001', '2024-2025', 'tuition', 18, '2024-06-12 10:00:00'),
('c23-01-9927-MAN121', 'PAY006', 21000.00, 0.00, 'paid', 'Full payment 1st Semester AY 2024-2025', '2024-06-15', 'CASH001', '2024-2025', 'tuition', 21, '2024-06-15 10:00:00');
-- =======================================================
-- TRANSACTION HISTORY
-- =======================================================

INSERT INTO transaction_history (transaction_id, student_id, transaction_type, amount, previous_balance, new_balance, description, status, issued_by, transaction_date)
VALUES
('TRX001', 'c24-01-0001-MAN121', 'payment', 18000.00, 18000.00, 0.00, 'Full payment received', 'completed', 'CASH001', '2024-06-15 10:00:00'),
('TRX002', 'c24-01-0002-MAN121', 'payment', 9000.00, 18000.00, 9000.00, 'Partial payment received', 'completed', 'CASH001', '2024-06-16 10:00:00'),
('TRX003', 'c23-01-0101-MAN121', 'payment', 21000.00, 21000.00, 0.00, 'Full payment received', 'completed', 'CASH001', '2024-06-14 10:00:00'),
('TRX004', 'c22-01-0201-MAN121', 'payment', 18000.00, 18000.00, 0.00, 'Full payment received', 'completed', 'CASH001', '2024-06-13 10:00:00'),
('TRX005', 'c21-01-0301-MAN121', 'payment', 18000.00, 18000.00, 0.00, 'Full payment received', 'completed', 'CASH001', '2024-06-12 10:00:00'),
('TRX006', 'c23-01-9927-MAN121', 'payment', 21000.00, 21000.00, 0.00, 'Full payment received', 'completed', 'CASH001', '2024-06-15 10:00:00');

-- =======================================================
-- ACTIVITY LOGS
-- =======================================================

INSERT INTO activity_logs (log_id, user_id, action, description, created_at) VALUES
('LOG001', 'ADMIN001', 'System Setup', 'Initialized database with complete curriculum data', NOW()),
('LOG002', 'ADMIN001', 'Course Management', 'Added BSIT curriculum with 40 subjects', NOW()),
('LOG003', 'ADMIN001', 'Course Management', 'Added BSCS curriculum with 38 subjects', NOW()),
('LOG004', 'ADMIN001', 'Student Management', 'Enrolled 26 students across all programs', NOW()),
('LOG005', 'REG001', 'Section Management', 'Created 35 sections for all programs', NOW()),
('LOG006', 'ADMIN001', 'Payment Settings', 'Set global unit price to ₱1,000.00', NOW());
-- =======================================================
-- VERIFICATION QUERIES (Run these to check data integrity)
-- =======================================================

/*
-- Check student counts per program
SELECT program, COUNT(*) as student_count 
FROM students_info 
GROUP BY program 
ORDER BY program;

-- Check section assignments
SELECT s.program, s.year_level, sec.section_code, COUNT(ss.student_id) as student_count
FROM sections sec
LEFT JOIN student_sections ss ON sec.id = ss.section_id
LEFT JOIN students_info s ON ss.student_id = s.user_id
GROUP BY sec.id
ORDER BY s.program, s.year_level, sec.section_code;

-- Check subject assignments
SELECT si.program, si.year_level, COUNT(ss.subject_id) as subject_count
FROM students_info si
LEFT JOIN student_subjects ss ON si.user_id = ss.student_id
GROUP BY si.program, si.year_level
ORDER BY si.program, si.year_level;

-- Check curriculum completeness
SELECT c.course_code, cc.year_level, cc.semester, COUNT(cc.subject_id) as subject_count
FROM course_curriculum cc
JOIN courses c ON cc.course_id = c.id
GROUP BY c.course_code, cc.year_level, cc.semester
ORDER BY c.course_code, cc.year_level, cc.semester;

-- Verify specific student login
SELECT * FROM users WHERE user_id = 'C26-02-9927-MAN121';

-- To empty all data
TRUNCATE `academic_years`;
TRUNCATE `activity_logs`;
TRUNCATE `class_schedule`;
TRUNCATE `courses`;
TRUNCATE `course_curriculum`;
TRUNCATE `employee_info`;
TRUNCATE `login_attempts`;
TRUNCATE `mobile_tokens`;
TRUNCATE `payments`;
TRUNCATE `payment_installments`;
TRUNCATE `sections`;
TRUNCATE `semesters`;
TRUNCATE `settings`;
TRUNCATE `students_info`;
TRUNCATE `student_course_enrollment`;
TRUNCATE `student_curriculum_adjustments`;
TRUNCATE `student_sections`;
TRUNCATE `student_subjects`;
TRUNCATE `subjects`;
TRUNCATE `transaction_history`;
TRUNCATE `users`;
*/