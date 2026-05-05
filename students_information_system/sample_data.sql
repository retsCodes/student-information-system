-- =======================================================
-- STUDENT INFORMATION SYSTEM - CORE SAMPLE DATA
-- Only courses: BSIT, BSCS, BSBA, BSA
-- No student data – use generate_students.php to add them
-- =======================================================

-- =======================================================
-- CREATE DEFAULT ADMIN USERS
-- =======================================================

INSERT INTO users (user_id, name, email, password, role, user_status, created_at) VALUES
('ADMIN001', 'Roy Impreial', 'admin@university.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active', '2022-01-15 09:00:00'),
('CASH001', 'John Reynald Cruz', 'cashier@university.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cashier', 'active', '2022-01-15 09:30:00'),
('REG001', 'Sarah Jane Martinez', 'registrar@university.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'registrar', 'active', '2022-01-15 10:00:00');

INSERT INTO employee_info (user_id, name, email, number, role, created_at) VALUES
('ADMIN001', 'Roy Imperial', 'admin@university.edu.ph', '09171234567', 'System Administrator', '2022-01-15 09:00:00'),
('CASH001', 'John Reynald Cruz', 'cashier@university.edu.ph', '09171234568', 'Head Cashier', '2022-01-15 09:30:00'),
('REG001', 'Sarah Jane Martinez', 'registrar@university.edu.ph', '09171234569', 'Registrar', '2022-01-15 10:00:00');

-- =======================================================
-- ACADEMIC YEARS
-- =======================================================

INSERT INTO academic_years (year_code, year_name, start_date, end_date, is_current, status, created_at) VALUES
('AY2022-2023', 'Academic Year 2022-2023', '2022-08-01', '2023-05-31', FALSE, 'inactive', '2022-03-15 08:00:00'),
('AY2023-2024', 'Academic Year 2023-2024', '2023-08-01', '2024-05-31', FALSE, 'inactive', '2023-03-15 08:00:00'),
('AY2024-2025', 'Academic Year 2024-2025', '2024-08-01', '2025-05-31', TRUE, 'active', '2024-03-15 08:00:00');

-- =======================================================
-- SEMESTERS
-- =======================================================

INSERT INTO semesters (academic_year_id, semester, start_date, end_date, enrollment_start, enrollment_end, payment_deadline, is_current, status, created_at) VALUES
(1, '1st', '2022-08-15', '2022-12-20', '2022-06-01', '2022-08-10', '2022-09-15', FALSE, 'inactive', '2022-03-15 09:00:00'),
(1, '2nd', '2023-01-08', '2023-05-15', '2022-11-01', '2023-01-05', '2023-02-15', FALSE, 'inactive', '2022-03-15 09:00:00'),
(1, 'summer', '2023-06-01', '2023-07-20', '2023-04-01', '2023-05-25', '2023-06-15', FALSE, 'inactive', '2022-03-15 09:00:00'),
(2, '1st', '2023-08-15', '2023-12-20', '2023-06-01', '2023-08-10', '2023-09-15', FALSE, 'inactive', '2023-03-15 09:00:00'),
(2, '2nd', '2024-01-08', '2024-05-15', '2023-11-01', '2024-01-05', '2024-02-15', FALSE, 'inactive', '2023-03-15 09:00:00'),
(2, 'summer', '2024-06-01', '2024-07-20', '2024-04-01', '2024-05-25', '2024-06-15', FALSE, 'inactive', '2023-03-15 09:00:00'),
(3, '1st', '2024-08-15', '2024-12-20', '2024-06-01', '2024-08-10', '2024-09-15', TRUE, 'active', '2024-03-15 09:00:00'),
(3, '2nd', '2025-01-08', '2025-05-15', '2024-11-01', '2025-01-05', '2025-02-15', FALSE, 'pending', '2024-03-15 09:00:00');

-- =======================================================
-- COURSES (only BSIT, BSCS, BSBA, BSA)
-- =======================================================

INSERT INTO courses (course_code, course_name, description, total_units, duration_years, status, created_at) VALUES
('BSIT', 'Bachelor of Science in Information Technology', 
 'A four-year program that prepares students for careers in software development, networking, database administration, cybersecurity, and other IT fields.',
 0, 4, 'active', '2022-01-15 10:00:00'),

('BSCS', 'Bachelor of Science in Computer Science', 
 'A four-year program focusing on the theoretical foundations of computation and information. Includes advanced study of algorithms, data structures, programming languages, software engineering, artificial intelligence, and machine learning.',
 0, 4, 'active', '2022-01-15 10:00:00'),

('BSBA', 'Bachelor of Science in Business Administration', 
 'A comprehensive four-year program covering management, marketing, finance, human resources, and entrepreneurship. Includes specializations in business analytics and strategic management.',
 0, 4, 'active', '2022-01-15 10:00:00'),

('BSA', 'Bachelor of Science in Accountancy', 
 'A four-year program preparing students for the Certified Public Accountant (CPA) licensure examination. Covers financial accounting, auditing, taxation, management advisory services, and business law.',
 0, 4, 'active', '2022-01-15 10:00:00');

-- =======================================================
-- SUBJECTS (only those needed for BSIT, BSCS, BSBA, BSA + General Education)
-- =======================================================

INSERT INTO subjects (subject_code, subject_name, units, program, year_level, semester, description, created_at) VALUES
-- General Education (shared)
('GE101', 'Understanding the Self', 3, 'General Education', 1, '1st', 'Exploration of personal identity, self-development, and interpersonal relationships.', '2022-01-15 11:00:00'),
('GE102', 'Readings in Philippine History', 3, 'General Education', 1, '1st', 'Critical analysis of Philippine historical events using primary sources.', '2022-01-15 11:00:00'),
('GE103', 'The Contemporary World', 3, 'General Education', 1, '1st', 'Study of globalization and its impact on economics, politics, culture, and society.', '2022-01-15 11:00:00'),
('GE104', 'Mathematics in the Modern World', 3, 'General Education', 1, '1st', 'Application of mathematical concepts in everyday life.', '2022-01-15 11:00:00'),
('GE105', 'Purposive Communication', 3, 'General Education', 1, '2nd', 'Development of effective communication skills for academic and professional contexts.', '2022-01-15 11:00:00'),
('GE106', 'Art Appreciation', 3, 'General Education', 1, '2nd', 'Introduction to the principles and elements of art.', '2022-01-15 11:00:00'),
('GE107', 'Science, Technology and Society', 3, 'General Education', 1, '2nd', 'Examination of the interrelationships between scientific discovery, technological innovation, and societal development.', '2022-01-15 11:00:00'),
('GE108', 'Ethics', 3, 'General Education', 1, '2nd', 'Study of moral principles, ethical frameworks, and their application to contemporary issues.', '2022-01-15 11:00:00'),
('GE109', 'Rizal Life and Works', 3, 'General Education', 2, '1st', 'Study of Dr. Jose Rizal''s life, literary works, and their significance to Philippine nationalism and identity.', '2022-01-15 11:00:00'),
('GE110', 'Understanding Diversity', 3, 'General Education', 2, '1st', 'Exploration of multiculturalism, gender studies, and inclusive practices in various social contexts.', '2022-01-15 11:00:00'),

-- Physical Education
('PE1', 'Physical Fitness and Wellness', 2, 'Physical Education', 1, '1st', 'Fundamentals of physical fitness, exercise principles, and wellness concepts.', '2022-01-15 11:00:00'),
('PE2', 'Rhythmic Activities', 2, 'Physical Education', 1, '2nd', 'Introduction to dance, rhythmic gymnastics, and movement education.', '2022-01-15 11:00:00'),
('PE3', 'Individual and Dual Sports', 2, 'Physical Education', 2, '1st', 'Skills and strategies in individual and dual sports like badminton, swimming, and athletics.', '2022-01-15 11:00:00'),
('PE4', 'Team Sports', 2, 'Physical Education', 2, '2nd', 'Team dynamics and strategies in sports like basketball, volleyball, and football.', '2022-01-15 11:00:00'),

-- NSTP
('NSTP1', 'CWTS/LTS/ROTC 1', 3, 'NSTP', 1, '1st', 'First semester of National Service Training Program focusing on civic welfare, literacy training, or military science.', '2022-01-15 11:00:00'),
('NSTP2', 'CWTS/LTS/ROTC 2', 3, 'NSTP', 1, '2nd', 'Second semester continuation of NSTP with community immersion and project implementation.', '2022-01-15 11:00:00'),

-- BSIT Subjects (full 4 years)
('IT101', 'Introduction to Computing', 3, 'BS Information Technology', 1, '1st', 'Fundamental concepts of computing, computer hardware, software, and information technology.', '2022-01-15 11:00:00'),
('IT102', 'Computer Programming 1', 3, 'BS Information Technology', 1, '1st', 'Introduction to programming using Python.', '2022-01-15 11:00:00'),
('IT103', 'Computer Programming 2', 3, 'BS Information Technology', 1, '2nd', 'Object-oriented programming concepts using Java.', '2022-01-15 11:00:00'),
('IT104', 'Discrete Mathematics', 3, 'BS Information Technology', 1, '2nd', 'Mathematical structures fundamental to computing.', '2022-01-15 11:00:00'),
('IT201', 'Data Structures and Algorithms', 3, 'BS Information Technology', 2, '1st', 'Efficient data organization and manipulation.', '2022-01-15 11:00:00'),
('IT202', 'Database Management Systems', 3, 'BS Information Technology', 2, '1st', 'Database design, SQL programming, normalization, and transaction management.', '2022-01-15 11:00:00'),
('IT203', 'Networking 1', 3, 'BS Information Technology', 2, '1st', 'Computer network fundamentals including OSI and TCP/IP models.', '2022-01-15 11:00:00'),
('IT204', 'Web Development', 3, 'BS Information Technology', 2, '2nd', 'Client-side and server-side web development using HTML5, CSS3, JavaScript, PHP, and MySQL.', '2022-01-15 11:00:00'),
('IT205', 'Software Engineering', 3, 'BS Information Technology', 2, '2nd', 'Software development methodologies, requirements analysis, design patterns, testing, and project management.', '2022-01-15 11:00:00'),
('IT206', 'Operating Systems', 3, 'BS Information Technology', 2, '2nd', 'Operating system concepts including process management, memory management, file systems, and security.', '2022-01-15 11:00:00'),
('IT301', 'Information Management', 3, 'BS Information Technology', 3, '1st', 'Advanced data management including data warehousing, data mining, and business intelligence.', '2022-01-15 11:00:00'),
('IT302', 'Networking 2', 3, 'BS Information Technology', 3, '1st', 'Advanced networking concepts including routing protocols, network security, and network administration.', '2022-01-15 11:00:00'),
('IT303', 'Mobile Development', 3, 'BS Information Technology', 3, '1st', 'Mobile application development for Android and iOS platforms using modern frameworks.', '2022-01-15 11:00:00'),
('IT304', 'System Integration and Architecture', 3, 'BS Information Technology', 3, '2nd', 'Integration of various IT systems, enterprise architecture frameworks, and API design.', '2022-01-15 11:00:00'),
('IT305', 'Information Assurance and Security', 3, 'BS Information Technology', 3, '2nd', 'Security principles, cryptography, network security, and cybersecurity best practices.', '2022-01-15 11:00:00'),
('IT306', 'Human-Computer Interaction', 3, 'BS Information Technology', 3, '2nd', 'UI/UX design principles, usability testing, and user-centered design methodologies.', '2022-01-15 11:00:00'),
('IT401', 'Capstone Project 1', 3, 'BS Information Technology', 4, '1st', 'Research and proposal development for IT project.', '2022-01-15 11:00:00'),
('IT402', 'Capstone Project 2', 3, 'BS Information Technology', 4, '2nd', 'Implementation of IT project, testing, documentation, and final defense.', '2022-01-15 11:00:00'),
('IT403', 'Practicum (Internship)', 6, 'BS Information Technology', 4, '1st', '486-hour on-the-job training in IT companies to gain practical industry experience.', '2022-01-15 11:00:00'),
('IT404', 'Emerging Technologies', 3, 'BS Information Technology', 4, '2nd', 'Study of latest trends in IT including cloud computing, IoT, AI, and blockchain.', '2022-01-15 11:00:00'),
('IT405', 'IT Elective 1: Network Security', 3, 'BS Information Technology', 4, '1st', 'Specialized study of network security protocols, firewalls, and intrusion detection systems.', '2022-01-15 11:00:00'),
('IT406', 'IT Elective 2: Data Analytics', 3, 'BS Information Technology', 4, '2nd', 'Introduction to data analytics tools and techniques using Python and R.', '2022-01-15 11:00:00'),

-- BSCS Subjects (full 4 years)
('CS101', 'Computer Science Orientation', 1, 'BS Computer Science', 1, '1st', 'Introduction to the computer science program, career paths, and academic expectations.', '2022-01-15 11:00:00'),
('CS102', 'Fundamentals of Programming', 3, 'BS Computer Science', 1, '1st', 'Programming fundamentals using Java.', '2022-01-15 11:00:00'),
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

-- BSBA Subjects (full 4 years)
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

-- BSA Subjects (full 4 years)
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
('ACC405', 'CPA Review', 6, 'BS Accountancy', 4, '2nd', 'Comprehensive review for the Certified Public Accountant licensure examination.', '2022-01-15 11:00:00');

-- =======================================================
-- COURSE CURRICULUM (BSIT, BSCS, BSBA, BSA)
-- =======================================================

-- BSIT Curriculum
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT (SELECT id FROM courses WHERE course_code = 'BSIT'), id, 1, '1st', 1, ROW_NUMBER() OVER (ORDER BY subject_code), '2022-01-15 12:00:00'
FROM subjects WHERE subject_code IN ('GE101','GE102','GE103','GE104','IT101','IT102','PE1','NSTP1');
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT (SELECT id FROM courses WHERE course_code = 'BSIT'), id, 1, '2nd', 1, ROW_NUMBER() OVER (ORDER BY subject_code), '2022-01-15 12:00:00'
FROM subjects WHERE subject_code IN ('GE105','GE106','GE107','GE108','IT103','IT104','PE2','NSTP2');
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT (SELECT id FROM courses WHERE course_code = 'BSIT'), id, 2, '1st', 1, ROW_NUMBER() OVER (ORDER BY subject_code), '2022-01-15 12:00:00'
FROM subjects WHERE subject_code IN ('GE109','IT201','IT202','IT203','PE3');
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT (SELECT id FROM courses WHERE course_code = 'BSIT'), id, 2, '2nd', 1, ROW_NUMBER() OVER (ORDER BY subject_code), '2022-01-15 12:00:00'
FROM subjects WHERE subject_code IN ('GE110','IT204','IT205','IT206','PE4');
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT (SELECT id FROM courses WHERE course_code = 'BSIT'), id, 3, '1st', 1, ROW_NUMBER() OVER (ORDER BY subject_code), '2022-01-15 12:00:00'
FROM subjects WHERE subject_code IN ('IT301','IT302','IT303');
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT (SELECT id FROM courses WHERE course_code = 'BSIT'), id, 3, '2nd', 1, ROW_NUMBER() OVER (ORDER BY subject_code), '2022-01-15 12:00:00'
FROM subjects WHERE subject_code IN ('IT304','IT305','IT306');
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT (SELECT id FROM courses WHERE course_code = 'BSIT'), id, 4, '1st', 1, ROW_NUMBER() OVER (ORDER BY subject_code), '2022-01-15 12:00:00'
FROM subjects WHERE subject_code IN ('IT401','IT403','IT405');
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT (SELECT id FROM courses WHERE course_code = 'BSIT'), id, 4, '2nd', 1, ROW_NUMBER() OVER (ORDER BY subject_code), '2022-01-15 12:00:00'
FROM subjects WHERE subject_code IN ('IT402','IT404','IT406');

-- BSCS Curriculum
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT (SELECT id FROM courses WHERE course_code = 'BSCS'), id, 1, '1st', 1, ROW_NUMBER() OVER (ORDER BY subject_code), '2022-01-15 12:00:00'
FROM subjects WHERE subject_code IN ('GE101','GE102','GE104','CS101','CS102','CS104','PE1','NSTP1');
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT (SELECT id FROM courses WHERE course_code = 'BSCS'), id, 1, '2nd', 1, ROW_NUMBER() OVER (ORDER BY subject_code), '2022-01-15 12:00:00'
FROM subjects WHERE subject_code IN ('GE105','GE107','GE108','CS103','CS105','PE2','NSTP2');
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT (SELECT id FROM courses WHERE course_code = 'BSCS'), id, 2, '1st', 1, ROW_NUMBER() OVER (ORDER BY subject_code), '2022-01-15 12:00:00'
FROM subjects WHERE subject_code IN ('GE106','CS201','CS202','CS203','PE3');
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT (SELECT id FROM courses WHERE course_code = 'BSCS'), id, 2, '2nd', 1, ROW_NUMBER() OVER (ORDER BY subject_code), '2022-01-15 12:00:00'
FROM subjects WHERE subject_code IN ('CS204','CS205','CS206','PE4');
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT (SELECT id FROM courses WHERE course_code = 'BSCS'), id, 3, '1st', 1, ROW_NUMBER() OVER (ORDER BY subject_code), '2022-01-15 12:00:00'
FROM subjects WHERE subject_code IN ('GE109','CS301','CS302','CS303');
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT (SELECT id FROM courses WHERE course_code = 'BSCS'), id, 3, '2nd', 1, ROW_NUMBER() OVER (ORDER BY subject_code), '2022-01-15 12:00:00'
FROM subjects WHERE subject_code IN ('GE110','CS304','CS305','CS306');
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT (SELECT id FROM courses WHERE course_code = 'BSCS'), id, 4, '1st', 1, ROW_NUMBER() OVER (ORDER BY subject_code), '2022-01-15 12:00:00'
FROM subjects WHERE subject_code IN ('CS401','CS402','CS403','CS405','CS407');
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT (SELECT id FROM courses WHERE course_code = 'BSCS'), id, 4, '2nd', 1, ROW_NUMBER() OVER (ORDER BY subject_code), '2022-01-15 12:00:00'
FROM subjects WHERE subject_code IN ('CS404','CS406','CS408');

-- BSBA Curriculum
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT (SELECT id FROM courses WHERE course_code = 'BSBA'), id, 1, '1st', 1, ROW_NUMBER() OVER (ORDER BY subject_code), '2022-01-15 12:00:00'
FROM subjects WHERE subject_code IN ('GE101','GE102','GE104','BA101','BA102','PE1','NSTP1');
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT (SELECT id FROM courses WHERE course_code = 'BSBA'), id, 1, '2nd', 1, ROW_NUMBER() OVER (ORDER BY subject_code), '2022-01-15 12:00:00'
FROM subjects WHERE subject_code IN ('GE105','GE106','GE108','BA103','BA104','PE2','NSTP2');
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT (SELECT id FROM courses WHERE course_code = 'BSBA'), id, 2, '1st', 1, ROW_NUMBER() OVER (ORDER BY subject_code), '2022-01-15 12:00:00'
FROM subjects WHERE subject_code IN ('GE109','BA201','BA202','PE3');
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT (SELECT id FROM courses WHERE course_code = 'BSBA'), id, 2, '2nd', 1, ROW_NUMBER() OVER (ORDER BY subject_code), '2022-01-15 12:00:00'
FROM subjects WHERE subject_code IN ('GE110','BA203','BA204','PE4');
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT (SELECT id FROM courses WHERE course_code = 'BSBA'), id, 3, '1st', 1, ROW_NUMBER() OVER (ORDER BY subject_code), '2022-01-15 12:00:00'
FROM subjects WHERE subject_code IN ('BA301','BA303');
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT (SELECT id FROM courses WHERE course_code = 'BSBA'), id, 3, '2nd', 1, ROW_NUMBER() OVER (ORDER BY subject_code), '2022-01-15 12:00:00'
FROM subjects WHERE subject_code IN ('BA302','BA304');
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT (SELECT id FROM courses WHERE course_code = 'BSBA'), id, 4, '1st', 1, ROW_NUMBER() OVER (ORDER BY subject_code), '2022-01-15 12:00:00'
FROM subjects WHERE subject_code IN ('BA401','BA403');
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT (SELECT id FROM courses WHERE course_code = 'BSBA'), id, 4, '2nd', 1, ROW_NUMBER() OVER (ORDER BY subject_code), '2022-01-15 12:00:00'
FROM subjects WHERE subject_code IN ('BA402','BA404');

-- BSA Curriculum
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT (SELECT id FROM courses WHERE course_code = 'BSA'), id, 1, '1st', 1, ROW_NUMBER() OVER (ORDER BY subject_code), '2022-01-15 12:00:00'
FROM subjects WHERE subject_code IN ('GE101','GE102','GE104','ACC101','ACC104','PE1','NSTP1');
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT (SELECT id FROM courses WHERE course_code = 'BSA'), id, 1, '2nd', 1, ROW_NUMBER() OVER (ORDER BY subject_code), '2022-01-15 12:00:00'
FROM subjects WHERE subject_code IN ('GE105','GE106','GE108','ACC102','ACC103','PE2','NSTP2');
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT (SELECT id FROM courses WHERE course_code = 'BSA'), id, 2, '1st', 1, ROW_NUMBER() OVER (ORDER BY subject_code), '2022-01-15 12:00:00'
FROM subjects WHERE subject_code IN ('GE109','ACC201','ACC203','PE3');
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT (SELECT id FROM courses WHERE course_code = 'BSA'), id, 2, '2nd', 1, ROW_NUMBER() OVER (ORDER BY subject_code), '2022-01-15 12:00:00'
FROM subjects WHERE subject_code IN ('GE110','ACC202','ACC204','PE4');
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT (SELECT id FROM courses WHERE course_code = 'BSA'), id, 3, '1st', 1, ROW_NUMBER() OVER (ORDER BY subject_code), '2022-01-15 12:00:00'
FROM subjects WHERE subject_code IN ('ACC301','ACC302');
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT (SELECT id FROM courses WHERE course_code = 'BSA'), id, 3, '2nd', 1, ROW_NUMBER() OVER (ORDER BY subject_code), '2022-01-15 12:00:00'
FROM subjects WHERE subject_code IN ('ACC303','ACC304');
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT (SELECT id FROM courses WHERE course_code = 'BSA'), id, 4, '1st', 1, ROW_NUMBER() OVER (ORDER BY subject_code), '2022-01-15 12:00:00'
FROM subjects WHERE subject_code IN ('ACC401','ACC402','ACC404');
INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required, order_index, created_at)
SELECT (SELECT id FROM courses WHERE course_code = 'BSA'), id, 4, '2nd', 1, ROW_NUMBER() OVER (ORDER BY subject_code), '2022-01-15 12:00:00'
FROM subjects WHERE subject_code IN ('ACC403','ACC405');

-- =======================================================
-- SECTIONS (only for BSIT, BSCS, BSBA, BSA)
-- =======================================================

INSERT INTO sections (section_code, section_name, year_level, program, status, semester, created_at) VALUES
('BSIT1A', 'BSIT 1 - Section A', 1, 'BS Information Technology', 'active', '1st', '2022-03-15 10:00:00'),
('BSIT1B', 'BSIT 1 - Section B', 1, 'BS Information Technology', 'active', '1st', '2022-03-15 10:00:00'),
('BSIT1C', 'BSIT 1 - Section C', 1, 'BS Information Technology', 'active', '1st', '2022-03-15 10:00:00'),
('BSIT2A', 'BSIT 2 - Section A', 2, 'BS Information Technology', 'active', '1st', '2022-03-15 10:00:00'),
('BSIT2B', 'BSIT 2 - Section B', 2, 'BS Information Technology', 'active', '1st', '2022-03-15 10:00:00'),
('BSIT3A', 'BSIT 3 - Section A', 3, 'BS Information Technology', 'active', '1st', '2022-03-15 10:00:00'),
('BSIT3B', 'BSIT 3 - Section B', 3, 'BS Information Technology', 'active', '1st', '2022-03-15 10:00:00'),
('BSIT4A', 'BSIT 4 - Section A', 4, 'BS Information Technology', 'active', '1st', '2022-03-15 10:00:00'),
('BSCS1A', 'BSCS 1 - Section A', 1, 'BS Computer Science', 'active', '1st', '2022-03-15 10:00:00'),
('BSCS1B', 'BSCS 1 - Section B', 1, 'BS Computer Science', 'active', '1st', '2022-03-15 10:00:00'),
('BSCS2A', 'BSCS 2 - Section A', 2, 'BS Computer Science', 'active', '1st', '2022-03-15 10:00:00'),
('BSCS2B', 'BSCS 2 - Section B', 2, 'BS Computer Science', 'active', '1st', '2022-03-15 10:00:00'),
('BSCS3A', 'BSCS 3 - Section A', 3, 'BS Computer Science', 'active', '1st', '2022-03-15 10:00:00'),
('BSCS4A', 'BSCS 4 - Section A', 4, 'BS Computer Science', 'active', '1st', '2022-03-15 10:00:00'),
('BSBA1A', 'BSBA 1 - Section A', 1, 'BS Business Administration', 'active', '1st', '2022-03-15 10:00:00'),
('BSBA1B', 'BSBA 1 - Section B', 1, 'BS Business Administration', 'active', '1st', '2022-03-15 10:00:00'),
('BSBA2A', 'BSBA 2 - Section A', 2, 'BS Business Administration', 'active', '1st', '2022-03-15 10:00:00'),
('BSBA2B', 'BSBA 2 - Section B', 2, 'BS Business Administration', 'active', '1st', '2022-03-15 10:00:00'),
('BSBA3A', 'BSBA 3 - Section A', 3, 'BS Business Administration', 'active', '1st', '2022-03-15 10:00:00'),
('BSBA4A', 'BSBA 4 - Section A', 4, 'BS Business Administration', 'active', '1st', '2022-03-15 10:00:00'),
('BSA1A', 'BSA 1 - Section A', 1, 'BS Accountancy', 'active', '1st', '2022-03-15 10:00:00'),
('BSA1B', 'BSA 1 - Section B', 1, 'BS Accountancy', 'active', '1st', '2022-03-15 10:00:00'),
('BSA2A', 'BSA 2 - Section A', 2, 'BS Accountancy', 'active', '1st', '2022-03-15 10:00:00'),
('BSA2B', 'BSA 2 - Section B', 2, 'BS Accountancy', 'active', '1st', '2022-03-15 10:00:00'),
('BSA3A', 'BSA 3 - Section A', 3, 'BS Accountancy', 'active', '1st', '2022-03-15 10:00:00'),
('BSA4A', 'BSA 4 - Section A', 4, 'BS Accountancy', 'active', '1st', '2022-03-15 10:00:00');

-- =======================================================
-- SUBJECT_SECTIONS DATA (Populate subjects for all sections)
-- =======================================================

-- BSIT Sections (Year 1, 1st Semester)
INSERT INTO subject_sections (subject_id, section_id, is_auto_filled)
SELECT s.id, sec.id, 1
FROM subjects s, sections sec
WHERE sec.section_code IN ('BSIT1A', 'BSIT1B', 'BSIT1C')
AND s.subject_code IN ('GE101', 'GE102', 'GE103', 'GE104', 'IT101', 'IT102', 'PE1', 'NSTP1')
ON DUPLICATE KEY UPDATE is_auto_filled = 1;

-- BSIT Year 1, 2nd Semester
INSERT INTO subject_sections (subject_id, section_id, is_auto_filled)
SELECT s.id, sec.id, 1
FROM subjects s, sections sec
WHERE sec.section_code IN ('BSIT1A', 'BSIT1B', 'BSIT1C')
AND s.subject_code IN ('GE105', 'GE106', 'GE107', 'GE108', 'IT103', 'IT104', 'PE2', 'NSTP2')
ON DUPLICATE KEY UPDATE is_auto_filled = 1;

-- BSIT Year 2, 1st Semester
INSERT INTO subject_sections (subject_id, section_id, is_auto_filled)
SELECT s.id, sec.id, 1
FROM subjects s, sections sec
WHERE sec.section_code IN ('BSIT2A', 'BSIT2B')
AND s.subject_code IN ('GE109', 'IT201', 'IT202', 'IT203', 'PE3')
ON DUPLICATE KEY UPDATE is_auto_filled = 1;

-- BSIT Year 2, 2nd Semester
INSERT INTO subject_sections (subject_id, section_id, is_auto_filled)
SELECT s.id, sec.id, 1
FROM subjects s, sections sec
WHERE sec.section_code IN ('BSIT2A', 'BSIT2B')
AND s.subject_code IN ('GE110', 'IT204', 'IT205', 'IT206', 'PE4')
ON DUPLICATE KEY UPDATE is_auto_filled = 1;

-- BSIT Year 3, 1st Semester
INSERT INTO subject_sections (subject_id, section_id, is_auto_filled)
SELECT s.id, sec.id, 1
FROM subjects s, sections sec
WHERE sec.section_code IN ('BSIT3A', 'BSIT3B')
AND s.subject_code IN ('IT301', 'IT302', 'IT303')
ON DUPLICATE KEY UPDATE is_auto_filled = 1;

-- BSIT Year 3, 2nd Semester
INSERT INTO subject_sections (subject_id, section_id, is_auto_filled)
SELECT s.id, sec.id, 1
FROM subjects s, sections sec
WHERE sec.section_code IN ('BSIT3A', 'BSIT3B')
AND s.subject_code IN ('IT304', 'IT305', 'IT306')
ON DUPLICATE KEY UPDATE is_auto_filled = 1;

-- BSIT Year 4, 1st Semester
INSERT INTO subject_sections (subject_id, section_id, is_auto_filled)
SELECT s.id, sec.id, 1
FROM subjects s, sections sec
WHERE sec.section_code IN ('BSIT4A')
AND s.subject_code IN ('IT401', 'IT403', 'IT405')
ON DUPLICATE KEY UPDATE is_auto_filled = 1;

-- BSIT Year 4, 2nd Semester
INSERT INTO subject_sections (subject_id, section_id, is_auto_filled)
SELECT s.id, sec.id, 1
FROM subjects s, sections sec
WHERE sec.section_code IN ('BSIT4A')
AND s.subject_code IN ('IT402', 'IT404', 'IT406')
ON DUPLICATE KEY UPDATE is_auto_filled = 1;

-- BSCS Sections
INSERT INTO subject_sections (subject_id, section_id, is_auto_filled)
SELECT s.id, sec.id, 1
FROM subjects s, sections sec
WHERE sec.section_code IN ('BSCS1A', 'BSCS1B')
AND s.subject_code IN ('GE101', 'GE102', 'GE104', 'CS101', 'CS102', 'CS104', 'PE1', 'NSTP1')
ON DUPLICATE KEY UPDATE is_auto_filled = 1;

INSERT INTO subject_sections (subject_id, section_id, is_auto_filled)
SELECT s.id, sec.id, 1
FROM subjects s, sections sec
WHERE sec.section_code IN ('BSCS1A', 'BSCS1B')
AND s.subject_code IN ('GE105', 'GE107', 'GE108', 'CS103', 'CS105', 'PE2', 'NSTP2')
ON DUPLICATE KEY UPDATE is_auto_filled = 1;

INSERT INTO subject_sections (subject_id, section_id, is_auto_filled)
SELECT s.id, sec.id, 1
FROM subjects s, sections sec
WHERE sec.section_code IN ('BSCS2A', 'BSCS2B')
AND s.subject_code IN ('GE106', 'CS201', 'CS202', 'CS203', 'PE3')
ON DUPLICATE KEY UPDATE is_auto_filled = 1;

INSERT INTO subject_sections (subject_id, section_id, is_auto_filled)
SELECT s.id, sec.id, 1
FROM subjects s, sections sec
WHERE sec.section_code IN ('BSCS2A', 'BSCS2B')
AND s.subject_code IN ('CS204', 'CS205', 'CS206', 'PE4')
ON DUPLICATE KEY UPDATE is_auto_filled = 1;

INSERT INTO subject_sections (subject_id, section_id, is_auto_filled)
SELECT s.id, sec.id, 1
FROM subjects s, sections sec
WHERE sec.section_code IN ('BSCS3A')
AND s.subject_code IN ('GE109', 'CS301', 'CS302', 'CS303')
ON DUPLICATE KEY UPDATE is_auto_filled = 1;

INSERT INTO subject_sections (subject_id, section_id, is_auto_filled)
SELECT s.id, sec.id, 1
FROM subjects s, sections sec
WHERE sec.section_code IN ('BSCS3A')
AND s.subject_code IN ('GE110', 'CS304', 'CS305', 'CS306')
ON DUPLICATE KEY UPDATE is_auto_filled = 1;

INSERT INTO subject_sections (subject_id, section_id, is_auto_filled)
SELECT s.id, sec.id, 1
FROM subjects s, sections sec
WHERE sec.section_code IN ('BSCS4A')
AND s.subject_code IN ('CS401', 'CS402', 'CS403', 'CS405', 'CS407')
ON DUPLICATE KEY UPDATE is_auto_filled = 1;

INSERT INTO subject_sections (subject_id, section_id, is_auto_filled)
SELECT s.id, sec.id, 1
FROM subjects s, sections sec
WHERE sec.section_code IN ('BSCS4A')
AND s.subject_code IN ('CS404', 'CS406', 'CS408')
ON DUPLICATE KEY UPDATE is_auto_filled = 1;

-- BSBA Sections
INSERT INTO subject_sections (subject_id, section_id, is_auto_filled)
SELECT s.id, sec.id, 1
FROM subjects s, sections sec
WHERE sec.section_code IN ('BSBA1A', 'BSBA1B')
AND s.subject_code IN ('GE101', 'GE102', 'GE104', 'BA101', 'BA102', 'PE1', 'NSTP1')
ON DUPLICATE KEY UPDATE is_auto_filled = 1;

INSERT INTO subject_sections (subject_id, section_id, is_auto_filled)
SELECT s.id, sec.id, 1
FROM subjects s, sections sec
WHERE sec.section_code IN ('BSBA1A', 'BSBA1B')
AND s.subject_code IN ('GE105', 'GE106', 'GE108', 'BA103', 'BA104', 'PE2', 'NSTP2')
ON DUPLICATE KEY UPDATE is_auto_filled = 1;

INSERT INTO subject_sections (subject_id, section_id, is_auto_filled)
SELECT s.id, sec.id, 1
FROM subjects s, sections sec
WHERE sec.section_code IN ('BSBA2A', 'BSBA2B')
AND s.subject_code IN ('GE109', 'BA201', 'BA202', 'PE3')
ON DUPLICATE KEY UPDATE is_auto_filled = 1;

INSERT INTO subject_sections (subject_id, section_id, is_auto_filled)
SELECT s.id, sec.id, 1
FROM subjects s, sections sec
WHERE sec.section_code IN ('BSBA2A', 'BSBA2B')
AND s.subject_code IN ('GE110', 'BA203', 'BA204', 'PE4')
ON DUPLICATE KEY UPDATE is_auto_filled = 1;

INSERT INTO subject_sections (subject_id, section_id, is_auto_filled)
SELECT s.id, sec.id, 1
FROM subjects s, sections sec
WHERE sec.section_code IN ('BSBA3A')
AND s.subject_code IN ('BA301', 'BA303')
ON DUPLICATE KEY UPDATE is_auto_filled = 1;

INSERT INTO subject_sections (subject_id, section_id, is_auto_filled)
SELECT s.id, sec.id, 1
FROM subjects s, sections sec
WHERE sec.section_code IN ('BSBA3A')
AND s.subject_code IN ('BA302', 'BA304')
ON DUPLICATE KEY UPDATE is_auto_filled = 1;

INSERT INTO subject_sections (subject_id, section_id, is_auto_filled)
SELECT s.id, sec.id, 1
FROM subjects s, sections sec
WHERE sec.section_code IN ('BSBA4A')
AND s.subject_code IN ('BA401', 'BA403')
ON DUPLICATE KEY UPDATE is_auto_filled = 1;

INSERT INTO subject_sections (subject_id, section_id, is_auto_filled)
SELECT s.id, sec.id, 1
FROM subjects s, sections sec
WHERE sec.section_code IN ('BSBA4A')
AND s.subject_code IN ('BA402', 'BA404')
ON DUPLICATE KEY UPDATE is_auto_filled = 1;

-- BSA Sections
INSERT INTO subject_sections (subject_id, section_id, is_auto_filled)
SELECT s.id, sec.id, 1
FROM subjects s, sections sec
WHERE sec.section_code IN ('BSA1A', 'BSA1B')
AND s.subject_code IN ('GE101', 'GE102', 'GE104', 'ACC101', 'ACC104', 'PE1', 'NSTP1')
ON DUPLICATE KEY UPDATE is_auto_filled = 1;

INSERT INTO subject_sections (subject_id, section_id, is_auto_filled)
SELECT s.id, sec.id, 1
FROM subjects s, sections sec
WHERE sec.section_code IN ('BSA1A', 'BSA1B')
AND s.subject_code IN ('GE105', 'GE106', 'GE108', 'ACC102', 'ACC103', 'PE2', 'NSTP2')
ON DUPLICATE KEY UPDATE is_auto_filled = 1;

INSERT INTO subject_sections (subject_id, section_id, is_auto_filled)
SELECT s.id, sec.id, 1
FROM subjects s, sections sec
WHERE sec.section_code IN ('BSA2A', 'BSA2B')
AND s.subject_code IN ('GE109', 'ACC201', 'ACC203', 'PE3')
ON DUPLICATE KEY UPDATE is_auto_filled = 1;

INSERT INTO subject_sections (subject_id, section_id, is_auto_filled)
SELECT s.id, sec.id, 1
FROM subjects s, sections sec
WHERE sec.section_code IN ('BSA2A', 'BSA2B')
AND s.subject_code IN ('GE110', 'ACC202', 'ACC204', 'PE4')
ON DUPLICATE KEY UPDATE is_auto_filled = 1;

INSERT INTO subject_sections (subject_id, section_id, is_auto_filled)
SELECT s.id, sec.id, 1
FROM subjects s, sections sec
WHERE sec.section_code IN ('BSA3A')
AND s.subject_code IN ('ACC301', 'ACC302')
ON DUPLICATE KEY UPDATE is_auto_filled = 1;

INSERT INTO subject_sections (subject_id, section_id, is_auto_filled)
SELECT s.id, sec.id, 1
FROM subjects s, sections sec
WHERE sec.section_code IN ('BSA3A')
AND s.subject_code IN ('ACC303', 'ACC304')
ON DUPLICATE KEY UPDATE is_auto_filled = 1;

INSERT INTO subject_sections (subject_id, section_id, is_auto_filled)
SELECT s.id, sec.id, 1
FROM subjects s, sections sec
WHERE sec.section_code IN ('BSA4A')
AND s.subject_code IN ('ACC401', 'ACC402', 'ACC404')
ON DUPLICATE KEY UPDATE is_auto_filled = 1;

INSERT INTO subject_sections (subject_id, section_id, is_auto_filled)
SELECT s.id, sec.id, 1
FROM subjects s, sections sec
WHERE sec.section_code IN ('BSA4A')
AND s.subject_code IN ('ACC403', 'ACC405')
ON DUPLICATE KEY UPDATE is_auto_filled = 1;

-- =======================================================
-- CLASS SCHEDULES (Only for subjects assigned to sections)
-- =======================================================

-- BSIT1A Schedules
INSERT INTO class_schedule (subject_id, section_id, day_of_week, start_time, end_time, room, instructor) VALUES
((SELECT id FROM subjects WHERE subject_code = 'IT101'), (SELECT id FROM sections WHERE section_code = 'BSIT1A'), 'Monday', '08:00:00', '10:30:00', 'IT Lab 101', 'Prof. Juan Santos'),
((SELECT id FROM subjects WHERE subject_code = 'IT102'), (SELECT id FROM sections WHERE section_code = 'BSIT1A'), 'Tuesday', '08:00:00', '10:30:00', 'IT Lab 102', 'Prof. Maria Reyes'),
((SELECT id FROM subjects WHERE subject_code = 'GE101'), (SELECT id FROM sections WHERE section_code = 'BSIT1A'), 'Wednesday', '08:00:00', '10:30:00', 'Room 201', 'Dr. Jose Cruz'),
((SELECT id FROM subjects WHERE subject_code = 'GE102'), (SELECT id FROM sections WHERE section_code = 'BSIT1A'), 'Thursday', '10:30:00', '13:00:00', 'Room 202', 'Dr. Ana Lopez'),
((SELECT id FROM subjects WHERE subject_code = 'PE1'), (SELECT id FROM sections WHERE section_code = 'BSIT1A'), 'Friday', '13:00:00', '15:00:00', 'Gymnasium', 'Coach Robert'),
((SELECT id FROM subjects WHERE subject_code = 'NSTP1'), (SELECT id FROM sections WHERE section_code = 'BSIT1A'), 'Saturday', '08:00:00', '11:00:00', 'Room 301', 'Dr. Ramon Garcia');

-- BSIT1B Schedules
INSERT INTO class_schedule (subject_id, section_id, day_of_week, start_time, end_time, room, instructor) VALUES
((SELECT id FROM subjects WHERE subject_code = 'IT101'), (SELECT id FROM sections WHERE section_code = 'BSIT1B'), 'Monday', '10:30:00', '13:00:00', 'IT Lab 101', 'Prof. Juan Santos'),
((SELECT id FROM subjects WHERE subject_code = 'IT102'), (SELECT id FROM sections WHERE section_code = 'BSIT1B'), 'Tuesday', '10:30:00', '13:00:00', 'IT Lab 102', 'Prof. Maria Reyes'),
((SELECT id FROM subjects WHERE subject_code = 'GE101'), (SELECT id FROM sections WHERE section_code = 'BSIT1B'), 'Wednesday', '10:30:00', '13:00:00', 'Room 201', 'Dr. Jose Cruz'),
((SELECT id FROM subjects WHERE subject_code = 'GE102'), (SELECT id FROM sections WHERE section_code = 'BSIT1B'), 'Thursday', '13:00:00', '15:30:00', 'Room 202', 'Dr. Ana Lopez'),
((SELECT id FROM subjects WHERE subject_code = 'PE1'), (SELECT id FROM sections WHERE section_code = 'BSIT1B'), 'Friday', '15:00:00', '17:00:00', 'Gymnasium', 'Coach Robert'),
((SELECT id FROM subjects WHERE subject_code = 'NSTP1'), (SELECT id FROM sections WHERE section_code = 'BSIT1B'), 'Saturday', '08:00:00', '11:00:00', 'Room 301', 'Dr. Ramon Garcia');

-- BSIT2A Schedules
INSERT INTO class_schedule (subject_id, section_id, day_of_week, start_time, end_time, room, instructor) VALUES
((SELECT id FROM subjects WHERE subject_code = 'IT201'), (SELECT id FROM sections WHERE section_code = 'BSIT2A'), 'Monday', '08:00:00', '10:30:00', 'IT Lab 201', 'Prof. Carlos Mendoza'),
((SELECT id FROM subjects WHERE subject_code = 'IT202'), (SELECT id FROM sections WHERE section_code = 'BSIT2A'), 'Tuesday', '08:00:00', '10:30:00', 'IT Lab 202', 'Prof. Lisa Santos'),
((SELECT id FROM subjects WHERE subject_code = 'IT203'), (SELECT id FROM sections WHERE section_code = 'BSIT2A'), 'Wednesday', '08:00:00', '10:30:00', 'Net Lab', 'Engr. Mark Rivera'),
((SELECT id FROM subjects WHERE subject_code = 'GE109'), (SELECT id FROM sections WHERE section_code = 'BSIT2A'), 'Thursday', '08:00:00', '10:30:00', 'Room 205', 'Dr. Jose Cruz'),
((SELECT id FROM subjects WHERE subject_code = 'PE3'), (SELECT id FROM sections WHERE section_code = 'BSIT2A'), 'Friday', '13:00:00', '15:00:00', 'Gymnasium', 'Coach Robert');

-- BSIT2B Schedules
INSERT INTO class_schedule (subject_id, section_id, day_of_week, start_time, end_time, room, instructor) VALUES
((SELECT id FROM subjects WHERE subject_code = 'IT201'), (SELECT id FROM sections WHERE section_code = 'BSIT2B'), 'Monday', '10:30:00', '13:00:00', 'IT Lab 201', 'Prof. Carlos Mendoza'),
((SELECT id FROM subjects WHERE subject_code = 'IT202'), (SELECT id FROM sections WHERE section_code = 'BSIT2B'), 'Tuesday', '10:30:00', '13:00:00', 'IT Lab 202', 'Prof. Lisa Santos'),
((SELECT id FROM subjects WHERE subject_code = 'IT203'), (SELECT id FROM sections WHERE section_code = 'BSIT2B'), 'Wednesday', '10:30:00', '13:00:00', 'Net Lab', 'Engr. Mark Rivera'),
((SELECT id FROM subjects WHERE subject_code = 'GE109'), (SELECT id FROM sections WHERE section_code = 'BSIT2B'), 'Thursday', '10:30:00', '13:00:00', 'Room 205', 'Dr. Jose Cruz'),
((SELECT id FROM subjects WHERE subject_code = 'PE3'), (SELECT id FROM sections WHERE section_code = 'BSIT2B'), 'Friday', '15:00:00', '17:00:00', 'Gymnasium', 'Coach Robert');

-- BSIT3A Schedules
INSERT INTO class_schedule (subject_id, section_id, day_of_week, start_time, end_time, room, instructor) VALUES
((SELECT id FROM subjects WHERE subject_code = 'IT301'), (SELECT id FROM sections WHERE section_code = 'BSIT3A'), 'Monday', '08:00:00', '10:30:00', 'IT Lab 301', 'Prof. Antonio Dela Cruz'),
((SELECT id FROM subjects WHERE subject_code = 'IT302'), (SELECT id FROM sections WHERE section_code = 'BSIT3A'), 'Tuesday', '08:00:00', '10:30:00', 'IT Lab 302', 'Prof. Josephine Ramos'),
((SELECT id FROM subjects WHERE subject_code = 'IT303'), (SELECT id FROM sections WHERE section_code = 'BSIT3A'), 'Wednesday', '08:00:00', '10:30:00', 'Mobile Lab', 'Prof. Michael Santos');

-- BSIT4A Schedules
INSERT INTO class_schedule (subject_id, section_id, day_of_week, start_time, end_time, room, instructor) VALUES
((SELECT id FROM subjects WHERE subject_code = 'IT401'), (SELECT id FROM sections WHERE section_code = 'BSIT4A'), 'Monday', '08:00:00', '10:30:00', 'Capstone Lab', 'Prof. Richard Gomez'),
((SELECT id FROM subjects WHERE subject_code = 'IT403'), (SELECT id FROM sections WHERE section_code = 'BSIT4A'), 'Wednesday', '08:00:00', '17:00:00', 'Industry Partner', 'Industry Supervisor'),
((SELECT id FROM subjects WHERE subject_code = 'IT405'), (SELECT id FROM sections WHERE section_code = 'BSIT4A'), 'Friday', '08:00:00', '10:30:00', 'Security Lab', 'Prof. Grace Santos');

-- BSCS1A Schedules
INSERT INTO class_schedule (subject_id, section_id, day_of_week, start_time, end_time, room, instructor) VALUES
((SELECT id FROM subjects WHERE subject_code = 'CS102'), (SELECT id FROM sections WHERE section_code = 'BSCS1A'), 'Monday', '08:00:00', '10:30:00', 'CS Lab 101', 'Prof. Elena Torres'),
((SELECT id FROM subjects WHERE subject_code = 'CS104'), (SELECT id FROM sections WHERE section_code = 'BSCS1A'), 'Tuesday', '08:00:00', '10:30:00', 'Room 203', 'Dr. Michael Tan'),
((SELECT id FROM subjects WHERE subject_code = 'GE101'), (SELECT id FROM sections WHERE section_code = 'BSCS1A'), 'Wednesday', '08:00:00', '10:30:00', 'Room 201', 'Dr. Jose Cruz'),
((SELECT id FROM subjects WHERE subject_code = 'PE1'), (SELECT id FROM sections WHERE section_code = 'BSCS1A'), 'Thursday', '13:00:00', '15:00:00', 'Gymnasium', 'Coach Robert'),
((SELECT id FROM subjects WHERE subject_code = 'NSTP1'), (SELECT id FROM sections WHERE section_code = 'BSCS1A'), 'Saturday', '08:00:00', '11:00:00', 'Room 302', 'Dr. Leticia Ramos');

-- BSCS2A Schedules
INSERT INTO class_schedule (subject_id, section_id, day_of_week, start_time, end_time, room, instructor) VALUES
((SELECT id FROM subjects WHERE subject_code = 'CS201'), (SELECT id FROM sections WHERE section_code = 'BSCS2A'), 'Monday', '08:00:00', '10:30:00', 'CS Lab 201', 'Prof. Victor Perez'),
((SELECT id FROM subjects WHERE subject_code = 'CS202'), (SELECT id FROM sections WHERE section_code = 'BSCS2A'), 'Tuesday', '08:00:00', '10:30:00', 'CS Lab 202', 'Prof. Alma Gutierrez'),
((SELECT id FROM subjects WHERE subject_code = 'CS203'), (SELECT id FROM sections WHERE section_code = 'BSCS2A'), 'Wednesday', '08:00:00', '10:30:00', 'Room 204', 'Dr. Michael Tan');

-- BSBA1A Schedules
INSERT INTO class_schedule (subject_id, section_id, day_of_week, start_time, end_time, room, instructor) VALUES
((SELECT id FROM subjects WHERE subject_code = 'BA101'), (SELECT id FROM sections WHERE section_code = 'BSBA1A'), 'Monday', '08:00:00', '10:30:00', 'Room 401', 'Prof. Ricardo Gomez'),
((SELECT id FROM subjects WHERE subject_code = 'BA102'), (SELECT id FROM sections WHERE section_code = 'BSBA1A'), 'Tuesday', '08:00:00', '10:30:00', 'Room 402', 'Dr. Cynthia Villar'),
((SELECT id FROM subjects WHERE subject_code = 'GE101'), (SELECT id FROM sections WHERE section_code = 'BSBA1A'), 'Wednesday', '08:00:00', '10:30:00', 'Room 201', 'Dr. Jose Cruz'),
((SELECT id FROM subjects WHERE subject_code = 'PE1'), (SELECT id FROM sections WHERE section_code = 'BSBA1A'), 'Thursday', '13:00:00', '15:00:00', 'Gymnasium', 'Coach Robert'),
((SELECT id FROM subjects WHERE subject_code = 'NSTP1'), (SELECT id FROM sections WHERE section_code = 'BSBA1A'), 'Saturday', '08:00:00', '11:00:00', 'Room 303', 'Dr. Leticia Ramos');

-- BSBA2A Schedules
INSERT INTO class_schedule (subject_id, section_id, day_of_week, start_time, end_time, room, instructor) VALUES
((SELECT id FROM subjects WHERE subject_code = 'BA201'), (SELECT id FROM sections WHERE section_code = 'BSBA2A'), 'Monday', '08:00:00', '10:30:00', 'Room 401', 'Prof. Ricardo Gomez'),
((SELECT id FROM subjects WHERE subject_code = 'BA202'), (SELECT id FROM sections WHERE section_code = 'BSBA2A'), 'Tuesday', '08:00:00', '10:30:00', 'Room 402', 'Dr. Cynthia Villar'),
((SELECT id FROM subjects WHERE subject_code = 'GE109'), (SELECT id FROM sections WHERE section_code = 'BSBA2A'), 'Wednesday', '08:00:00', '10:30:00', 'Room 205', 'Dr. Jose Cruz');

-- BSA1A Schedules
INSERT INTO class_schedule (subject_id, section_id, day_of_week, start_time, end_time, room, instructor) VALUES
((SELECT id FROM subjects WHERE subject_code = 'ACC101'), (SELECT id FROM sections WHERE section_code = 'BSA1A'), 'Monday', '08:00:00', '10:30:00', 'Room 501', 'Prof. Ferdinand Cruz'),
((SELECT id FROM subjects WHERE subject_code = 'ACC104'), (SELECT id FROM sections WHERE section_code = 'BSA1A'), 'Tuesday', '08:00:00', '10:30:00', 'Room 502', 'Dr. Leni Robredo'),
((SELECT id FROM subjects WHERE subject_code = 'GE101'), (SELECT id FROM sections WHERE section_code = 'BSA1A'), 'Wednesday', '08:00:00', '10:30:00', 'Room 201', 'Dr. Jose Cruz'),
((SELECT id FROM subjects WHERE subject_code = 'PE1'), (SELECT id FROM sections WHERE section_code = 'BSA1A'), 'Thursday', '13:00:00', '15:00:00', 'Gymnasium', 'Coach Robert'),
((SELECT id FROM subjects WHERE subject_code = 'NSTP1'), (SELECT id FROM sections WHERE section_code = 'BSA1A'), 'Saturday', '08:00:00', '11:00:00', 'Room 304', 'Dr. Leticia Ramos');

-- BSA2A Schedules
INSERT INTO class_schedule (subject_id, section_id, day_of_week, start_time, end_time, room, instructor) VALUES
((SELECT id FROM subjects WHERE subject_code = 'ACC201'), (SELECT id FROM sections WHERE section_code = 'BSA2A'), 'Monday', '08:00:00', '10:30:00', 'Room 501', 'Prof. Ferdinand Cruz'),
((SELECT id FROM subjects WHERE subject_code = 'ACC203'), (SELECT id FROM sections WHERE section_code = 'BSA2A'), 'Tuesday', '08:00:00', '10:30:00', 'Room 502', 'Dr. Leni Robredo'),
((SELECT id FROM subjects WHERE subject_code = 'GE109'), (SELECT id FROM sections WHERE section_code = 'BSA2A'), 'Wednesday', '08:00:00', '10:30:00', 'Room 205', 'Dr. Jose Cruz');

-- =======================================================
-- SETTINGS
-- =======================================================

INSERT INTO settings (name, value, category, description, created_at) VALUES
('unit_price', '1000.00', 'payment', 'Price per unit for all courses', NOW()),
('school_name', 'ACLC Mandaue', 'general', 'Name of the educational institution', NOW()),
('school_address', 'Mandaue City, Cebu, Philippines', 'general', 'Physical address of the school', NOW()),
('school_email', 'info@aclc.edu.ph', 'general', 'Main email address', NOW()),
('school_phone', '(032) 123-4567', 'general', 'Contact phone number', NOW()),
('currency_symbol', '₱', 'payment', 'Currency symbol for display', NOW()),
('currency_code', 'PHP', 'payment', 'ISO currency code', NOW()),
('academic_year', '2024-2025', 'academic', 'Current academic year', NOW()),
('semester', '1st', 'academic', 'Current semester', NOW());

-- =======================================================
-- ACTIVITY LOGS
-- =======================================================

INSERT INTO activity_logs (log_id, user_id, action, description, created_at) VALUES
('LOG001', 'ADMIN001', 'System Setup', 'Initialized database with complete curriculum data (BSIT, BSCS, BSBA, BSA)', NOW()),
('LOG002', 'ADMIN001', 'Course Management', 'Added BSIT curriculum with full 4-year subjects', NOW()),
('LOG003', 'ADMIN001', 'Course Management', 'Added BSCS curriculum with full 4-year subjects', NOW()),
('LOG004', 'ADMIN001', 'Course Management', 'Added BSBA curriculum with full 4-year subjects', NOW()),
('LOG005', 'ADMIN001', 'Course Management', 'Added BSA curriculum with full 4-year subjects', NOW()),
('LOG006', 'REG001', 'Section Management', 'Created sections for all year levels and programs', NOW()),
('LOG007', 'ADMIN001', 'Payment Settings', 'Set global unit price to ₱1,000.00', NOW()),
('LOG008', 'ADMIN001', 'Schedule Management', 'Added class schedules for all sections', NOW());