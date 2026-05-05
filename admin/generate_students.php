<?php
/**
 * generate_students.php
 * Generates realistic Filipino student data with grades in student_course_completion table
 * Run this after resetting the database
 */

require_once '../init.php';
requireRole('admin');

$pdo = getDBConnection();

$students_per_course = 60;
$start_year = 2021;
$current_year = date('Y');
$unit_price = 1000;

$first_names = [
    'Juan', 'Maria', 'Jose', 'Ana', 'Carlos', 'Luz', 'Miguel', 'Rosa', 'Antonio', 'Teresa',
    'Francisco', 'Isabel', 'Ramon', 'Concepcion', 'Manuel', 'Victoria', 'Jesus', 'Catalina',
    'Ricardo', 'Luisa', 'Andres', 'Marta', 'Fernando', 'Elena', 'Emilio', 'Guadalupe',
    'Alberto', 'Patricia', 'Rafael', 'Angelica', 'Enrique', 'Ofelia', 'Jaime', 'Socorro'
];

$last_names = [
    'Santos', 'Reyes', 'Cruz', 'Garcia', 'Mendoza', 'Lopez', 'Flores', 'Gonzales', 'Ramos', 'Fernandez',
    'Aguilar', 'Torres', 'Rivera', 'Morales', 'Castillo', 'Ortega', 'Delacruz', 'Romualdez', 'Villanueva',
    'Alejandro', 'Bautista', 'DeLeon', 'Magsaysay', 'Salazar', 'Paredes', 'Samson', 'Alvarez'
];

$full_names = [];
foreach ($first_names as $first) {
    foreach ($last_names as $last) {
        $full_names[] = ['first' => $first, 'last' => $last];
        if (count($full_names) >= 1000) break 2;
    }
}
shuffle($full_names);

$courses = [
    'BSIT' => 'BS Information Technology',
    'BSCS' => 'BS Computer Science',
    'BSBA' => 'BS Business Administration',
    'BSA'  => 'BS Accountancy'
];

$course_ids = [];
foreach ($courses as $code => $program_name) {
    $stmt = $pdo->prepare("SELECT id FROM courses WHERE course_code = ?");
    $stmt->execute([$code]);
    $course_ids[$code] = $stmt->fetchColumn();
    if (!$course_ids[$code]) {
        die("Course $code not found. Please import sample data first.");
    }
}

// Map program names to consistent format
$program_map = [
    'BS Information Technology' => 'BS Information Technology',
    'BS Computer Science' => 'BS Computer Science',
    'BS Business Administration' => 'BS Business Administration',
    'BS Accountancy' => 'BS Accountancy'
];

// =======================================================
// CREATE FIXED BSCS YEAR 1 SECTION WITH SPECIFIC STUDENTS
// =======================================================

echo "<h3>Creating BSCS Year 1 Section and Fixed Students...</h3>";

// Check if BSCS1A section exists, if not create it
$stmt = $pdo->prepare("SELECT id FROM sections WHERE section_code = 'BSCS1A'");
$stmt->execute();
$bscs_section = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$bscs_section) {
    $stmt = $pdo->prepare("
        INSERT INTO sections (section_code, section_name, program, course_id, year_level, semester, status) 
        VALUES ('BSCS1A', 'BSCS 1 - Section A', 'BS Computer Science', ?, 1, '1st', 'active')
    ");
    $stmt->execute([$course_ids['BSCS']]);
    $bscs_section_id = $pdo->lastInsertId();
    echo "✓ Created BSCS1A section<br>";
} else {
    $bscs_section_id = $bscs_section['id'];
    echo "✓ BSCS1A section already exists<br>";
}

// Get BSCS course ID
$bscs_course_id = $course_ids['BSCS'];

// Fixed students for BSCS Year 1
$fixed_students = [
    ['user_id' => 'C24-02-0001-MAN121', 'name' => 'Juan Dela Cruz', 'email' => 'juan.delacruz@student.aclc.edu.ph'],
    ['user_id' => 'C24-02-0002-MAN121', 'name' => 'Maria Santos', 'email' => 'maria.santos@student.aclc.edu.ph'],
    ['user_id' => 'C24-02-0003-MAN121', 'name' => 'Jose Reyes', 'email' => 'jose.reyes@student.aclc.edu.ph'],
    ['user_id' => 'C24-02-0004-MAN121', 'name' => 'Ana Gonzales', 'email' => 'ana.gonzales@student.aclc.edu.ph'],
    ['user_id' => 'C24-02-0005-MAN121', 'name' => 'Carlos Mendoza', 'email' => 'carlos.mendoza@student.aclc.edu.ph']
];

$password_hash = password_hash('student123', PASSWORD_DEFAULT);
$enrollment_date = date('Y-m-d');

foreach ($fixed_students as $student) {
    // Check if user exists
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE user_id = ?");
    $stmt->execute([$student['user_id']]);
    
    if (!$stmt->fetch()) {
        // Create user
        $stmt = $pdo->prepare("
            INSERT INTO users (user_id, name, email, password, role, user_status, created_at) 
            VALUES (?, ?, ?, ?, 'student', 'active', NOW())
        ");
        $stmt->execute([$student['user_id'], $student['name'], $student['email'], $password_hash]);
        echo "✓ Created user: {$student['name']} ({$student['user_id']})<br>";
        
        // Create student info
        $stmt = $pdo->prepare("
            INSERT INTO students_info 
            (user_id, student_type, name, email, program, course_id, year_level, student_status, enrollment_status, status, enrollment_date, total_units, created_at)
            VALUES (?, 'regular', ?, ?, 'BS Computer Science', ?, 1, 'new', 'enrolled', 'active', ?, 0, NOW())
        ");
        $stmt->execute([$student['user_id'], $student['name'], $student['email'], $bscs_course_id, $enrollment_date]);
        echo "✓ Created student info: {$student['name']}<br>";
        
        // Create enrollment record
        $expected_graduation = date('Y-m-d', strtotime('+4 years'));
        $stmt = $pdo->prepare("
            INSERT INTO student_course_enrollment 
            (student_id, course_id, enrollment_date, expected_graduation, current_year_level, current_semester, status, created_at)
            VALUES (?, ?, ?, ?, 1, '1st', 'active', NOW())
        ");
        $stmt->execute([$student['user_id'], $bscs_course_id, $enrollment_date, $expected_graduation]);
    }
    
    // Assign student to section
    $stmt = $pdo->prepare("
        SELECT id FROM student_sections WHERE student_id = ? AND section_id = ?
    ");
    $stmt->execute([$student['user_id'], $bscs_section_id]);
    if (!$stmt->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO student_sections (student_id, section_id, assigned_at) VALUES (?, ?, NOW())");
        $stmt->execute([$student['user_id'], $bscs_section_id]);
        echo "✓ Assigned {$student['name']} to BSCS1A section<br>";
    }
}

echo "<hr>";

// =======================================================
// ENSURE SUBJECT_SECTIONS ARE POPULATED
// =======================================================

echo "<h3>Ensuring subject_sections are populated...</h3>";

// Check if subject_sections is empty
$stmt = $pdo->query("SELECT COUNT(*) FROM subject_sections");
$subject_sections_count = $stmt->fetchColumn();

if ($subject_sections_count == 0) {
    echo "Populating subject_sections from curriculum...<br>";
    
    // Get all sections
    $sections = $pdo->query("SELECT id, course_id, year_level, semester FROM sections WHERE status = 'active'")->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($sections as $section) {
        if ($section['course_id']) {
            // Get subjects from curriculum for this section's course, year level, and semester
            $stmt = $pdo->prepare("
                SELECT subject_id FROM course_curriculum 
                WHERE course_id = ? AND year_level = ? AND semester = ?
            ");
            $stmt->execute([$section['course_id'], $section['year_level'], $section['semester']]);
            $subjects = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($subjects as $subject_id) {
                $stmt = $pdo->prepare("INSERT IGNORE INTO subject_sections (subject_id, section_id, is_auto_filled) VALUES (?, ?, 1)");
                $stmt->execute([$subject_id, $section['id']]);
            }
            echo "✓ Added " . count($subjects) . " subjects to section {$section['id']}<br>";
        }
    }
} else {
    echo "subject_sections already has $subject_sections_count records<br>";
}

echo "<hr>";

// =======================================================
// GET SECTIONS MAPPING (Using consistent program names)
// =======================================================

$sections_by_program_year = [];
$stmt = $pdo->query("SELECT id, section_code, program, year_level FROM sections WHERE status = 'active'");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Use the program name as is from sections table
    $sections_by_program_year[$row['program']][$row['year_level']][] = $row['id'];
}

// Debug: Show available sections
echo "<h4>Available Sections for Assignment:</h4>";
foreach ($sections_by_program_year as $program => $years) {
    echo "<strong>$program:</strong> ";
    foreach ($years as $year => $section_ids) {
        echo "Year $year: " . count($section_ids) . " sections, ";
    }
    echo "<br>";
}

echo "<hr>";

// =======================================================
// PRE-COMPUTE CURRICULUM SUBJECTS
// =======================================================

$curriculum_cache = [];
foreach ($course_ids as $code => $cid) {
    for ($year = 1; $year <= 4; $year++) {
        for ($sem = 1; $sem <= 2; $sem++) {
            $semester_name = $sem == 1 ? '1st' : '2nd';
            $stmt = $pdo->prepare("
                SELECT s.id, s.subject_code, s.subject_name, s.units
                FROM course_curriculum cc
                JOIN subjects s ON cc.subject_id = s.id
                WHERE cc.course_id = ? AND cc.year_level = ? AND cc.semester = ?
            ");
            $stmt->execute([$cid, $year, $semester_name]);
            $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $curriculum_cache[$code][$year][$semester_name] = [
                'subjects' => $subjects,
                'total_units' => array_sum(array_column($subjects, 'units')),
                'subject_ids' => array_column($subjects, 'id')
            ];
        }
    }
}

// =======================================================
// GET ALL SUBJECTS FOR IRREGULAR ASSIGNMENTS
// =======================================================

$all_subjects_by_program = [];
foreach ($courses as $code => $program_name) {
    $stmt = $pdo->prepare("
        SELECT s.id, s.subject_code, s.subject_name, s.units, cc.year_level, cc.semester
        FROM course_curriculum cc
        JOIN subjects s ON cc.subject_id = s.id
        WHERE cc.course_id = ?
        ORDER BY cc.year_level, cc.semester
    ");
    $stmt->execute([$course_ids[$code]]);
    $all_subjects_by_program[$code] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// =======================================================
// GENERATE STUDENTS FOR EACH COURSE
// =======================================================

$password_hash = password_hash('student123', PASSWORD_DEFAULT);
$generated = 0;
$name_index = 0;
$irregular_count = 0;

echo "<h3>Generating random students...</h3>";

$pdo->beginTransaction();

try {
    foreach ($courses as $code => $program_name) {
        $course_id = $course_ids[$code];
        
        // Skip BSCS fixed students - we already added them
        if ($code == 'BSCS') {
            echo "Skipping BSCS random students (fixed students already added)<br>";
            continue;
        }
        
        for ($i = 0; $i < $students_per_course; $i++) {
            if ($name_index >= count($full_names)) {
                $name_index = 0;
                shuffle($full_names);
            }
            $name_data = $full_names[$name_index++];
            $name = $name_data['first'] . ' ' . $name_data['last'];
            
            $year_level = rand(1, 4);
            $enroll_year = $current_year - ($year_level - 1);
            $enroll_year = max($start_year, min($enroll_year, $current_year));
            $enrollment_date = date('Y-m-d', strtotime("$enroll_year-06-".rand(1,30)));
            $expected_graduation = date('Y-m-d', strtotime("+4 years", strtotime($enrollment_date)));
            
            $is_irregular = (rand(1,100) <= 20);
            $student_type = $is_irregular ? 'irregular' : 'regular';
            
            $enrollment_status = 'enrolled';
            $user_status = 'active';
            $sce_status = 'active';
            $current_year_level = $year_level;
            
            // Generate student ID
            $course_num = ($code == 'BSIT') ? '01' : (($code == 'BSBA') ? '03' : '04');
            $random_num = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $user_id = "c{$enroll_year}-{$course_num}-{$random_num}-MAN121";
            $stmt = $pdo->prepare("SELECT 1 FROM users WHERE user_id = ?");
            while ($stmt->execute([$user_id]) && $stmt->fetch()) {
                $random_num = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                $user_id = "c{$enroll_year}-{$course_num}-{$random_num}-MAN121";
            }
            
            // Generate email
            $email = strtolower(str_replace(' ', '.', $name)) . "@student.aclc.edu.ph";
            $orig_email = $email;
            $email_counter = 1;
            $stmt = $pdo->prepare("SELECT 1 FROM users WHERE email = ?");
            while ($stmt->execute([$email]) && $stmt->fetch()) {
                $email = str_replace('@', "$email_counter@", $orig_email);
                $email_counter++;
            }
            
            // Insert user
            $stmt = $pdo->prepare("INSERT INTO users (user_id, name, email, password, role, user_status, created_at) 
                                   VALUES (?, ?, ?, ?, 'student', ?, ?)");
            $stmt->execute([$user_id, $name, $email, $password_hash, $user_status, $enrollment_date]);
            
            // Generate address and phone
            $address = "Blk " . rand(1,50) . " Lot " . rand(1,20) . ", " . 
                       ['Mabini','Rizal','Bonifacio','Luna','Aguinaldo'][array_rand(['Mabini','Rizal','Bonifacio','Luna','Aguinaldo'])] . 
                       " St., " . ['Manila','Quezon City','Makati','Pasig','Cebu','Davao'][array_rand(['Manila','Quezon City','Makati','Pasig','Cebu','Davao'])];
            $phone = '0917' . str_pad(rand(0,9999999), 7, '0', STR_PAD_LEFT);
            $student_status = ($enroll_year == $current_year) ? 'new' : 'old';
            
            // Insert student info - Use the SAME program name format as sections
            $stmt = $pdo->prepare("INSERT INTO students_info (user_id, student_type, name, email, number, address, program, course_id, year_level, student_status, enrollment_status, status, enrollment_date, total_units, created_at)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())");
            $stmt->execute([$user_id, $student_type, $name, $email, $phone, $address, $program_name, $course_id, $year_level, $student_status, $enrollment_status, $user_status, $enrollment_date]);
            
            // Create enrollment record
            $current_semester = (rand(1,100) <= 50) ? '1st' : '2nd';
            $stmt = $pdo->prepare("INSERT INTO student_course_enrollment (student_id, course_id, enrollment_date, expected_graduation, current_year_level, current_semester, status, created_at)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$user_id, $course_id, $enrollment_date, $expected_graduation, $current_year_level, $current_semester, $sce_status]);
            
            // Assign sections - Use the consistent program name
            $available_sections = $sections_by_program_year[$program_name][$year_level] ?? [];
            
            // If no sections found by program name, try alternative matching
            if (empty($available_sections)) {
                // Try to find sections by course_id
                $stmt = $pdo->prepare("SELECT id FROM sections WHERE course_id = ? AND year_level = ? AND status = 'active'");
                $stmt->execute([$course_id, $year_level]);
                $available_sections = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }
            
            $section_ids_to_assign = [];
            
            if ($is_irregular && count($available_sections) >= 2) {
                $num_sections = min(rand(2, 3), count($available_sections));
                shuffle($available_sections);
                $section_ids_to_assign = array_slice($available_sections, 0, $num_sections);
                $irregular_count++;
            } elseif (!empty($available_sections)) {
                $section_ids_to_assign = [$available_sections[array_rand($available_sections)]];
            }
            
            if (empty($section_ids_to_assign)) {
                echo "Warning: No sections found for {$program_name} Year {$year_level}<br>";
            }
            
            $student_subject_ids = [];
            
            foreach ($section_ids_to_assign as $section_id) {
                $stmt = $pdo->prepare("INSERT INTO student_sections (student_id, section_id, assigned_at) VALUES (?, ?, ?)");
                $stmt->execute([$user_id, $section_id, $enrollment_date]);
                
                // Get subjects from this section
                $stmt = $pdo->prepare("SELECT subject_id FROM subject_sections WHERE section_id = ?");
                $stmt->execute([$section_id]);
                $section_subjects = $stmt->fetchAll(PDO::FETCH_COLUMN);
                $student_subject_ids = array_merge($student_subject_ids, $section_subjects);
            }
            
            // Handle irregular extra subjects
            if ($is_irregular && !empty($all_subjects_by_program[$code])) {
                $all_available_subjects = $all_subjects_by_program[$code];
                $extra_count = rand(1, 3);
                shuffle($all_available_subjects);
                
                for ($x = 0; $x < $extra_count && $x < count($all_available_subjects); $x++) {
                    $extra_subject = $all_available_subjects[$x];
                    if (!in_array($extra_subject['id'], $student_subject_ids)) {
                        // Find a section that offers this subject within the same program
                        $stmt = $pdo->prepare("
                            SELECT section_id FROM subject_sections ss
                            JOIN sections s ON ss.section_id = s.id
                            WHERE ss.subject_id = ? AND s.program = ?
                            LIMIT 1
                        ");
                        $stmt->execute([$extra_subject['id'], $program_name]);
                        $offering_section = $stmt->fetchColumn();
                        
                        if ($offering_section) {
                            $stmt = $pdo->prepare("
                                INSERT INTO student_subjects (student_id, subject_id, section_id, assigned_by, reason, status)
                                VALUES (?, ?, ?, 'ADMIN001', ?, 'active')
                            ");
                            $reason = "Irregular student - taking {$extra_subject['subject_code']} (Year {$extra_subject['year_level']})";
                            $stmt->execute([$user_id, $extra_subject['id'], $offering_section, $reason]);
                            $student_subject_ids[] = $extra_subject['id'];
                        }
                    }
                }
            }
            
            $student_subject_ids = array_unique($student_subject_ids);
            
            // Generate completion records
            $academic_year_start = 2022;
            
            // Mark previous years as completed
            for ($year = 1; $year < $year_level; $year++) {
                for ($sem = 1; $sem <= 2; $sem++) {
                    $semester_name = $sem == 1 ? '1st' : '2nd';
                    $subjects = $curriculum_cache[$code][$year][$semester_name]['subjects'] ?? [];
                    $academic_year = ($academic_year_start + $year - 1) . '-' . ($academic_year_start + $year);
                    $date_completed = date('Y-m-d', strtotime("$academic_year_start-" . ($sem == 1 ? '03-15' : '07-15')));
                    
                    foreach ($subjects as $subject) {
                        $grade_options = [1.0, 1.25, 1.5, 1.75, 2.0, 2.25, 2.5, 2.75, 3.0];
                        $grade = $grade_options[array_rand($grade_options)];
                        
                        $stmt = $pdo->prepare("
                            INSERT INTO student_course_completion 
                            (student_id, subject_id, year_level, semester, academic_year, grade, date_completed, status)
                            VALUES (?, ?, ?, ?, ?, ?, ?, 'completed')
                            ON DUPLICATE KEY UPDATE
                            grade = VALUES(grade),
                            date_completed = VALUES(date_completed),
                            status = 'completed'
                        ");
                        $stmt->execute([$user_id, $subject['id'], $year, $semester_name, $academic_year, $grade, $date_completed]);
                    }
                }
            }
            
            // Mark current year subjects as in_progress
            $current_academic_year = ($academic_year_start + $year_level - 1) . '-' . ($academic_year_start + $year_level);
            
            if ($year_level <= 4) {
                $current_subjects = $curriculum_cache[$code][$year_level][$current_semester]['subjects'] ?? [];
                foreach ($current_subjects as $subject) {
                    $stmt = $pdo->prepare("
                        INSERT INTO student_course_completion 
                        (student_id, subject_id, year_level, semester, academic_year, status)
                        VALUES (?, ?, ?, ?, ?, 'in_progress')
                        ON DUPLICATE KEY UPDATE
                        status = 'in_progress'
                    ");
                    $stmt->execute([$user_id, $subject['id'], $year_level, $current_semester, $current_academic_year]);
                }
            }
            
            // Mark irregular extra subjects as in_progress
            if ($is_irregular && !empty($extra_subjects)) {
                $stmt = $pdo->prepare("
                    SELECT DISTINCT s.* 
                    FROM student_subjects ss
                    JOIN subjects s ON ss.subject_id = s.id
                    WHERE ss.student_id = ?
                ");
                $stmt->execute([$user_id]);
                $extra_subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($extra_subjects as $subject) {
                    $subj_year = $subject['year_level'] ?? $year_level;
                    $subj_sem = $subject['semester'] ?? $current_semester;
                    $stmt = $pdo->prepare("
                        INSERT INTO student_course_completion 
                        (student_id, subject_id, year_level, semester, academic_year, status)
                        VALUES (?, ?, ?, ?, ?, 'in_progress')
                        ON DUPLICATE KEY UPDATE
                        status = 'in_progress'
                    ");
                    $stmt->execute([$user_id, $subject['id'], $subj_year, $subj_sem, $current_academic_year]);
                }
            }
            
            // Calculate total units
            $total_units = 0;
            foreach ($student_subject_ids as $subj_id) {
                $stmt = $pdo->prepare("SELECT units FROM subjects WHERE id = ?");
                $stmt->execute([$subj_id]);
                $total_units += $stmt->fetchColumn() ?: 0;
            }
            
            $stmt = $pdo->prepare("UPDATE students_info SET total_units = ? WHERE user_id = ?");
            $stmt->execute([$total_units, $user_id]);
            
            // Generate payment records
            $total_units_sem = $curriculum_cache[$code][$year_level][$current_semester]['total_units'] ?? 0;
            $tuition = $total_units_sem * $unit_price;
            
            if ($tuition > 0) {
                $rand_pay = rand(1,100);
                if ($rand_pay <= 30) {
                    $amount = $tuition;
                    $remaining = 0;
                    $status = 'paid';
                } elseif ($rand_pay <= 70) {
                    $amount = round($tuition * (0.3 + rand(10,60)/100), 2);
                    $remaining = round($tuition - $amount, 2);
                    $status = 'partial';
                } else {
                    $amount = 0;
                    $remaining = $tuition;
                    $status = 'unpaid';
                }
                $permit_number = 'PAY' . str_pad(rand(1,999999), 6, '0', STR_PAD_LEFT);
                $issued_date = date('Y-m-d', strtotime("+".rand(1,30)." days", strtotime($enrollment_date)));
                $amount_text = numberToWords($amount) . ' pesos';
                
                $stmt = $pdo->prepare("INSERT INTO payments (student_id, permit_number, amount, amount_text, remaining_balance, payment_status, description, issued_date, issued_by, school_year, payment_category, units, created_at)
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'CASH001', '2024-2025', 'tuition', ?, NOW())");
                $stmt->execute([$user_id, $permit_number, $amount, $amount_text, $remaining, $status, "Payment for {$current_semester} Semester AY 2024-2025", $issued_date, $total_units_sem]);
            }
            
            $generated++;
            if ($generated % 20 == 0) {
                echo "Generated $generated students... (Irregular: $irregular_count)<br>";
                flush();
            }
        }
    }
    
    // Update course total units
    foreach ($courses as $code => $name) {
        $stmt = $pdo->prepare("
            SELECT SUM(s.units) as total_units
            FROM course_curriculum cc
            JOIN subjects s ON cc.subject_id = s.id
            WHERE cc.course_id = ?
        ");
        $stmt->execute([$course_ids[$code]]);
        $total = $stmt->fetchColumn() ?: 0;
        $stmt = $pdo->prepare("UPDATE courses SET total_units = ? WHERE id = ?");
        $stmt->execute([$total, $course_ids[$code]]);
    }
    
    $pdo->commit();
    
    echo "<hr>";
    echo "<h2 style='color:green'>✅ Generation Complete!</h2>";
    echo "<ul>";
    echo "<li><strong>Fixed BSCS Year 1 Students:</strong> 5 students (Juan Dela Cruz, Maria Santos, Jose Reyes, Ana Gonzales, Carlos Mendoza)</li>";
    echo "<li><strong>BSCS Section:</strong> BSCS1A created with all 5 students assigned</li>";
    echo "<li><strong>Random Students Generated:</strong> " . ($generated) . " students</li>";
    echo "<li><strong>Irregular students created:</strong> $irregular_count</li>";
    echo "<li><strong>Total Students:</strong> " . ($generated + 5) . "</li>";
    echo "</ul>";
    echo "<p>All regular students have passing grades (1.0 - 3.0) for completed subjects.</p>";
    echo "<div class='mt-3'>";
    echo "<a href='manage_users.php' class='btn btn-primary'>Go to Manage Users</a> ";
    echo "<a href='../registrar/manage_students.php' class='btn btn-info'>Go to Registrar Dashboard</a>";
    echo "</div>";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "<h2 style='color:red'>❌ Error: " . $e->getMessage() . "</h2>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>