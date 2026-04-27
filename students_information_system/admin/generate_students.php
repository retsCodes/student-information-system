<?php
/**
 * generate_students.php
 * Generates realistic Filipino student data with grades in student_course_completion table
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
    'BSIT' => 'Bachelor of Science in Information Technology',
    'BSCS' => 'Bachelor of Science in Computer Science',
    'BSBA' => 'Bachelor of Science in Business Administration',
    'BSA'  => 'Bachelor of Science in Accountancy'
];

$course_ids = [];
foreach ($courses as $code => $name) {
    $stmt = $pdo->prepare("SELECT id FROM courses WHERE course_code = ?");
    $stmt->execute([$code]);
    $course_ids[$code] = $stmt->fetchColumn();
    if (!$course_ids[$code]) {
        die("Course $code not found. Please import sample data first.");
    }
}

$sections_by_program_year = [];
$stmt = $pdo->query("SELECT id, section_code, program, year_level FROM sections WHERE status = 'active'");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $sections_by_program_year[$row['program']][$row['year_level']][] = $row['id'];
}

// Pre‑compute curriculum subjects
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

// Get all subjects for the entire curriculum (for irregular assignments)
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

$password_hash = password_hash('student123', PASSWORD_DEFAULT);
$generated = 0;
$name_index = 0;
$irregular_count = 0;

$pdo->beginTransaction();

try {
    foreach ($courses as $code => $program_name) {
        $course_id = $course_ids[$code];
        
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
            
            $course_num = ($code == 'BSIT') ? '01' : (($code == 'BSCS') ? '02' : (($code == 'BSBA') ? '03' : '04'));
            $random_num = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $user_id = "c{$enroll_year}-{$course_num}-{$random_num}-MAN121";
            $stmt = $pdo->prepare("SELECT 1 FROM users WHERE user_id = ?");
            while ($stmt->execute([$user_id]) && $stmt->fetch()) {
                $random_num = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                $user_id = "c{$enroll_year}-{$course_num}-{$random_num}-MAN121";
            }
            
            $email = strtolower(str_replace(' ', '.', $name)) . "@student.aclc.edu.ph";
            $orig_email = $email;
            $email_counter = 1;
            $stmt = $pdo->prepare("SELECT 1 FROM users WHERE email = ?");
            while ($stmt->execute([$email]) && $stmt->fetch()) {
                $email = str_replace('@', "$email_counter@", $orig_email);
                $email_counter++;
            }
            
            $stmt = $pdo->prepare("INSERT INTO users (user_id, name, email, password, role, user_status, created_at) 
                                   VALUES (?, ?, ?, ?, 'student', ?, ?)");
            $stmt->execute([$user_id, $name, $email, $password_hash, $user_status, $enrollment_date]);
            
            $address = "Blk " . rand(1,50) . " Lot " . rand(1,20) . ", " . 
                       ['Mabini','Rizal','Bonifacio','Luna','Aguinaldo'][array_rand(['Mabini','Rizal','Bonifacio','Luna','Aguinaldo'])] . 
                       " St., " . ['Manila','Quezon City','Makati','Pasig','Cebu','Davao'][array_rand(['Manila','Quezon City','Makati','Pasig','Cebu','Davao'])];
            $phone = '0917' . str_pad(rand(0,9999999), 7, '0', STR_PAD_LEFT);
            $student_status = ($enroll_year == $current_year) ? 'new' : 'old';
            
            $stmt = $pdo->prepare("INSERT INTO students_info (user_id, student_type, name, email, number, address, program, course_id, year_level, student_status, enrollment_status, status, enrollment_date, total_units, created_at)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())");
            $stmt->execute([$user_id, $student_type, $name, $email, $phone, $address, $program_name, $course_id, $year_level, $student_status, $enrollment_status, $user_status, $enrollment_date]);
            
            $current_semester = (rand(1,100) <= 50) ? '1st' : '2nd';
            $stmt = $pdo->prepare("INSERT INTO student_course_enrollment (student_id, course_id, enrollment_date, expected_graduation, current_year_level, current_semester, status, created_at)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$user_id, $course_id, $enrollment_date, $expected_graduation, $current_year_level, $current_semester, $sce_status]);
            
            // Get available sections for this student's program and year level
            $available_sections = $sections_by_program_year[$program_name][$year_level] ?? [];
            $section_ids_to_assign = [];
            
            if ($is_irregular && count($available_sections) >= 2) {
                $num_sections = min(rand(2, 3), count($available_sections));
                shuffle($available_sections);
                $section_ids_to_assign = array_slice($available_sections, 0, $num_sections);
                $irregular_count++;
            } elseif (!empty($available_sections)) {
                $section_ids_to_assign = [$available_sections[array_rand($available_sections)]];
            }
            
            // Track all subjects the student is taking (from sections + direct assignments)
            $student_subject_ids = [];
            
            foreach ($section_ids_to_assign as $section_id) {
                $stmt = $pdo->prepare("INSERT INTO student_sections (student_id, section_id, assigned_at) VALUES (?, ?, ?)");
                $stmt->execute([$user_id, $section_id, $enrollment_date]);
                
                // Get subjects from this section
                $stmt = $pdo->prepare("
                    SELECT subject_id FROM subject_sections WHERE section_id = ?
                ");
                $stmt->execute([$section_id]);
                $section_subjects = $stmt->fetchAll(PDO::FETCH_COLUMN);
                $student_subject_ids = array_merge($student_subject_ids, $section_subjects);
            }
            
            // For irregular students, add some extra subjects from different years
            if ($is_irregular) {
                $all_available_subjects = $all_subjects_by_program[$code];
                $extra_count = rand(1, 3);
                shuffle($all_available_subjects);
                
                for ($x = 0; $x < $extra_count && $x < count($all_available_subjects); $x++) {
                    $extra_subject = $all_available_subjects[$x];
                    if (!in_array($extra_subject['id'], $student_subject_ids)) {
                        // Find a section that offers this subject
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
            
            // GENERATE COMPLETION RECORDS FOR ALL SUBJECTS
            $academic_year_start = 2022;
            
            // First, mark all subjects from previous years as completed with passing grades
            for ($year = 1; $year < $year_level; $year++) {
                for ($sem = 1; $sem <= 2; $sem++) {
                    $semester_name = $sem == 1 ? '1st' : '2nd';
                    $subjects = $curriculum_cache[$code][$year][$semester_name]['subjects'] ?? [];
                    $academic_year = ($academic_year_start + $year - 1) . '-' . ($academic_year_start + $year);
                    $date_completed = date('Y-m-d', strtotime("$academic_year_start-" . ($sem == 1 ? '03-15' : '07-15')));
                    
                    foreach ($subjects as $subject) {
                        // Generate passing grade (1.0 to 3.0)
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
            
            // For current year, mark current semester subjects as 'in_progress'
            $current_academic_year = ($academic_year_start + $year_level - 1) . '-' . ($academic_year_start + $year_level);
            $subjects_to_mark = [];
            
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
            
            // For irregular students, also mark their extra subjects as in_progress
            if ($is_irregular) {
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
            
            // Calculate total units from subjects the student is taking
            $total_units = 0;
            foreach ($student_subject_ids as $subj_id) {
                $stmt = $pdo->prepare("SELECT units FROM subjects WHERE id = ?");
                $stmt->execute([$subj_id]);
                $total_units += $stmt->fetchColumn() ?: 0;
            }
            
            $stmt = $pdo->prepare("UPDATE students_info SET total_units = ? WHERE user_id = ?");
            $stmt->execute([$total_units, $user_id]);
            
            // Generate payment records for enrolled students
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
    echo "<h2 style='color:green'>Success! Generated $generated students.</h2>";
    echo "<p><strong>Irregular students created: $irregular_count</strong></p>";
    echo "<p>All regular students have passing grades (1.0 - 3.0) for completed subjects.</p>";
    echo "<a href='manage_users.php' class='btn btn-primary'>Go to Manage Users</a>";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "<h2 style='color:red'>Error: " . $e->getMessage() . "</h2>";
}
?>