<?php
/**
 * generate_students.php
 * 
 * Generates realistic Filipino student data using large internal arrays (no API).
 * Creates students for BSIT, BSCS, BSBA, BSA with varying year levels,
 * enrollment statuses, and payment records.
 * 20% of students are marked as IRREGULAR (assigned to multiple sections)
 */

require_once '../init.php';
requireRole('admin');

$pdo = getDBConnection();

// Configuration
$students_per_course = 60;   // 60 per course = 240 total (about 48 irregular)
$start_year = 2021;
$current_year = date('Y');
$unit_price = 1000;

// Large arrays of Filipino first names (more than 200)
$first_names = [
    'Juan', 'Maria', 'Jose', 'Ana', 'Carlos', 'Luz', 'Miguel', 'Rosa', 'Antonio', 'Teresa',
    'Francisco', 'Isabel', 'Ramon', 'Concepcion', 'Manuel', 'Victoria', 'Jesus', 'Catalina',
    'Ricardo', 'Luisa', 'Andres', 'Marta', 'Fernando', 'Elena', 'Emilio', 'Guadalupe',
    'Alberto', 'Patricia', 'Rafael', 'Angelica', 'Enrique', 'Ofelia', 'Jaime', 'Socorro',
    'Guillermo', 'Cristina', 'Arturo', 'Mercedes', 'Benigno', 'Celia', 'Sergio', 'Luzviminda',
    'Rolando', 'Fe', 'Rogelio', 'Corazon', 'Dante', 'Perla', 'Roberto', 'Aurora', 'Marilou',
    'Jovito', 'Leticia', 'Ernesto', 'Lourdes', 'Reynaldo', 'Milagros', 'Gregorio', 'Visitacion',
    'Fernando', 'Gloria', 'Rodolfo', 'Nelia', 'Oscar', 'Rosalinda', 'Rene', 'Imelda', 'Dennis',
    'Lilia', 'Edwin', 'Allan', 'Evelyn', 'Bernardo', 'Randy', 'Rebecca', 'Joel', 'Rowena',
    'Roderick', 'Marissa', 'Jerome', 'Jennifer', 'Ronald', 'Catherine', 'Raymond', 'Michelle',
    'Albert', 'Shirley', 'Patrick', 'Vilma', 'Jonathan', 'Yolly', 'Michael', 'Marilyn',
    'Christopher', 'Lorna', 'Mark', 'Mila', 'Paul', 'Cecilia', 'Andrew', 'Angela', 'John Mark',
    'Mary Grace', 'Ian', 'Jocelyn', 'Christian', 'Gina', 'Kevin', 'Nora', 'Jayson', 'Maricel',
    'Ryan', 'Mary Ann', 'Joshua', 'Christine', 'Eduardo', 'Divina', 'Francis', 'Loida', 'Adrian',
    'Genevieve', 'Jerry', 'Beth', 'Elmer', 'Thelma', 'Lucio', 'Glenda', 'Ruel', 'Marlon', 'Lorena',
    'Rico', 'Gertrudes', 'Nestor', 'Remedios', 'Arnold', 'Zenaida', 'Danilo', 'Pilar', 'Renato',
    'Lydia', 'Ramoncito', 'Nenita', 'Edgardo', 'Violeta', 'Lito', 'Milagrosa', 'Noel', 'Ester',
    'Victor', 'Luzviminda', 'Rey', 'Glory', 'Randy', 'Nimfa', 'Ramil', 'Lerma', 'Rommel', 'Leticia'
];

// Large arrays of Filipino last names (more than 200)
$last_names = [
    'Santos', 'Reyes', 'Cruz', 'Garcia', 'Mendoza', 'Lopez', 'Flores', 'Gonzales', 'Ramos', 'Fernandez',
    'Aguilar', 'Torres', 'Rivera', 'Morales', 'Castillo', 'Ortega', 'Delacruz', 'Romualdez', 'Villanueva',
    'Alejandro', 'Bautista', 'DeLeon', 'Magsaysay', 'Salazar', 'Paredes', 'Samson', 'Alvarez', 'Guevarra',
    'Valdez', 'Marquez', 'Luna', 'Dizon', 'Soriano', 'Velasco', 'Estrada', 'Francisco', 'Gomez', 'Hernandez',
    'Jimenez', 'Manaloto', 'Navarro', 'Ocampo', 'Pascual', 'Quinto', 'Romero', 'Salvador', 'Tan', 'Umali',
    'Vergara', 'Zamora', 'Cabrera', 'Villa', 'Aquino', 'Castro', 'Dela Rosa', 'Galang', 'Hilario', 'Ibañez',
    'Jacinto', 'Lazaro', 'Manansala', 'Natividad', 'Orozco', 'Panganiban', 'Quimson', 'Roxas', 'Sison',
    'Tolentino', 'Ubaldo', 'Ventura', 'Yap', 'Zulueta', 'Agbayani', 'Buenaventura', 'Cayanan', 'Dimagiba',
    'Escudero', 'Ferrer', 'Gatchalian', 'Hizon', 'Ilagan', 'Javier', 'Kalaw', 'Lansangan', 'Macapagal',
    'Nazareno', 'Ordonez', 'Pineda', 'Quiambao', 'Ramos', 'Sebastian', 'Tecson', 'Urbano', 'Velez', 'Ybanez',
    'Abad', 'Bello', 'Carpio', 'Domingo', 'Evangelista', 'Fajardo', 'Guerrero', 'Herrera', 'Infante', 'Joven',
    'Lacson', 'Magsino', 'Nicolas', 'Olivarez', 'Palma', 'Quezon', 'Robles', 'Sarmiento', 'Tupas', 'Uy'
];

// Combine into a pool of unique full names
$full_names = [];
foreach ($first_names as $first) {
    foreach ($last_names as $last) {
        $full_names[] = ['first' => $first, 'last' => $last];
        if (count($full_names) >= 3000) break 2;
    }
}
shuffle($full_names);

// Course mapping
$courses = [
    'BSIT' => 'BS Information Technology',
    'BSCS' => 'BS Computer Science',
    'BSBA' => 'BS Business Administration',
    'BSA'  => 'BS Accountancy'
];

// Get course IDs
$course_ids = [];
foreach ($courses as $code => $name) {
    $stmt = $pdo->prepare("SELECT id FROM courses WHERE course_code = ?");
    $stmt->execute([$code]);
    $course_ids[$code] = $stmt->fetchColumn();
    if (!$course_ids[$code]) {
        die("Course $code not found. Please import sample data first.");
    }
}

// Get all sections for each program/year level (for irregular assignments)
$sections_by_program_year = [];
$stmt = $pdo->query("SELECT id, section_code, program, year_level FROM sections WHERE status = 'active'");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $sections_by_program_year[$row['program']][$row['year_level']][] = $row['id'];
}

// Pre‑compute units per course/year/semester
$units_cache = [];
foreach ($course_ids as $code => $cid) {
    for ($year = 1; $year <= 4; $year++) {
        $stmt = $pdo->prepare("SELECT SUM(s.units) FROM course_curriculum cc
                               JOIN subjects s ON cc.subject_id = s.id
                               WHERE cc.course_id = ? AND cc.year_level = ? AND cc.semester = ?");
        $stmt->execute([$cid, $year, '1st']);
        $units_cache[$code][$year]['1st'] = (int) $stmt->fetchColumn();
        $stmt->execute([$cid, $year, '2nd']);
        $units_cache[$code][$year]['2nd'] = (int) $stmt->fetchColumn();
    }
}

$password_hash = password_hash('student123', PASSWORD_DEFAULT);
$generated = 0;
$name_index = 0;
$irregular_count = 0;

populateClassSchedules($pdo);
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
            $enroll_year = $current_year - ($year_level - 1) + rand(-1, 1);
            $enroll_year = max($start_year, min($enroll_year, $current_year));
            $enrollment_date = date('Y-m-d', strtotime("$enroll_year-06-".rand(1,30)));
            $expected_graduation = date('Y-m-d', strtotime("+4 years", strtotime($enrollment_date)));
            
            // 20% chance of being irregular student
            $is_irregular = (rand(1,100) <= 20);
            
            $student_type = $is_irregular ? 'irregular' : 'regular';
            $status_rand = rand(1,100);
            
            if ($status_rand <= 70) {
                $enrollment_status = 'enrolled';
                $user_status = 'active';
                $sce_status = 'active';
                $current_year_level = $year_level;
            } elseif ($status_rand <= 85) {
                $enrollment_status = 'dropped';
                $user_status = 'locked';
                $sce_status = 'dropped';
                $current_year_level = $year_level;
            } elseif ($status_rand <= 95) {
                $enrollment_status = 'graduated';
                $user_status = 'active';
                $sce_status = 'completed';
                $current_year_level = $year_level;
            } else {
                $enrollment_status = 'on_leave';
                $user_status = 'active';
                $sce_status = 'active';
                $current_year_level = $year_level;
            }
            
            // Unique user_id
            $course_num = ($code == 'BSIT') ? '01' : (($code == 'BSCS') ? '02' : (($code == 'BSBA') ? '03' : '04'));
            $random_num = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $user_id = "c{$enroll_year}-{$course_num}-{$random_num}-MAN121";
            $stmt = $pdo->prepare("SELECT 1 FROM users WHERE user_id = ?");
            while ($stmt->execute([$user_id]) && $stmt->fetch()) {
                $random_num = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                $user_id = "c{$enroll_year}-{$course_num}-{$random_num}-MAN121";
            }
            
            // Email
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
            
            // Insert students_info
            $address = "Blk " . rand(1,50) . " Lot " . rand(1,20) . ", " . 
                       ['Mabini','Rizal','Bonifacio','Luna','Aguinaldo'][array_rand(['Mabini','Rizal','Bonifacio','Luna','Aguinaldo'])] . 
                       " St., " . ['Manila','Quezon City','Makati','Pasig','Cebu','Davao'][array_rand(['Manila','Quezon City','Makati','Pasig','Cebu','Davao'])];
            $phone = '0917' . str_pad(rand(0,9999999), 7, '0', STR_PAD_LEFT);
            $student_status = ($enroll_year == $current_year) ? 'new' : 'old';
            
            $stmt = $pdo->prepare("INSERT INTO students_info (user_id, student_type, name, email, number, address, program, course_id, year_level, student_status, enrollment_status, status, enrollment_date, total_units, created_at)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())");
            $stmt->execute([$user_id, $student_type, $name, $email, $phone, $address, $program_name, $course_id, $year_level, $student_status, $enrollment_status, $user_status, $enrollment_date]);
            
            // student_course_enrollment
            $current_semester = (rand(1,100) <= 50) ? '1st' : '2nd';
            $stmt = $pdo->prepare("INSERT INTO student_course_enrollment (student_id, course_id, enrollment_date, expected_graduation, current_year_level, current_semester, status, created_at)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$user_id, $course_id, $enrollment_date, $expected_graduation, $current_year_level, $current_semester, $sce_status]);
            
            // =======================================================
            // ASSIGN SECTIONS (Irregular students get 2-3 sections)
            // =======================================================
            $section_ids_to_assign = [];
            $available_sections = $sections_by_program_year[$program_name][$year_level] ?? [];
            
            if ($is_irregular && count($available_sections) >= 2) {
                // Irregular: assign to 2-3 sections (but same year level)
                $num_sections = min(rand(2, 3), count($available_sections));
                shuffle($available_sections);
                $section_ids_to_assign = array_slice($available_sections, 0, $num_sections);
                $irregular_count++;
            } elseif (!empty($available_sections)) {
                // Regular: assign to 1 section
                $section_ids_to_assign = [$available_sections[array_rand($available_sections)]];
            }
            
            foreach ($section_ids_to_assign as $section_id) {
                $stmt = $pdo->prepare("INSERT INTO student_sections (student_id, section_id, assigned_at) VALUES (?, ?, ?)");
                $stmt->execute([$user_id, $section_id, $enrollment_date]);
            }
            
            // =======================================================
            // FOR IRREGULAR STUDENTS: Add individual subject assignments
            // (Example: irregular students take some subjects from higher/lower years)
            // =======================================================
            if ($is_irregular && $enrollment_status == 'enrolled') {
                // Get all subjects from curriculum for this program
                $stmt = $pdo->prepare("
                    SELECT DISTINCT s.id, s.subject_code, s.subject_name, s.units, s.year_level, s.semester
                    FROM course_curriculum cc
                    JOIN subjects s ON cc.subject_id = s.id
                    WHERE cc.course_id = ?
                    ORDER BY s.year_level, s.semester
                ");
                $stmt->execute([$course_id]);
                $all_subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Get subjects already covered by assigned sections
                $assigned_subject_ids = [];
                foreach ($section_ids_to_assign as $sec_id) {
                    $stmt = $pdo->prepare("SELECT subject_id FROM subject_sections WHERE section_id = ?");
                    $stmt->execute([$sec_id]);
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $assigned_subject_ids[] = $row['subject_id'];
                    }
                }
                $assigned_subject_ids = array_unique($assigned_subject_ids);
                
                // Find subjects NOT in assigned sections (for irregular students to add)
                $missing_subjects = array_filter($all_subjects, function($subj) use ($assigned_subject_ids) {
                    return !in_array($subj['id'], $assigned_subject_ids);
                });
                
                // Add 1-3 extra subjects that are not in their sections
                if (!empty($missing_subjects)) {
                    $extra_subjects = array_rand(array_values($missing_subjects), min(rand(1, 3), count($missing_subjects)));
                    if (!is_array($extra_subjects)) $extra_subjects = [$extra_subjects];
                    $missing_values = array_values($missing_subjects);
                    
                    foreach ($extra_subjects as $idx) {
                        $subject = $missing_values[$idx];
                        // Find a section that offers this subject (any year level)
                        $stmt = $pdo->prepare("
                            SELECT section_id FROM subject_sections ss
                            JOIN sections s ON ss.section_id = s.id
                            WHERE ss.subject_id = ? AND s.program = ?
                            LIMIT 1
                        ");
                        $stmt->execute([$subject['id'], $program_name]);
                        $offering_section = $stmt->fetchColumn();
                        
                        if ($offering_section) {
                            // Add direct subject assignment
                            $stmt = $pdo->prepare("
                                INSERT INTO student_subjects (student_id, subject_id, section_id, assigned_by, reason, status)
                                VALUES (?, ?, ?, 'ADMIN001', ?, 'active')
                            ");
                            $reason = "Irregular student - taking {$subject['subject_name']} (Year {$subject['year_level']}, {$subject['semester']})";
                            $stmt->execute([$user_id, $subject['id'], $offering_section, $reason]);
                        }
                    }
                }
            }
            
            // =======================================================
            // PAYMENTS
            // =======================================================
            $total_units_sem = $units_cache[$code][$year_level][$current_semester] ?? 0;
            $tuition = $total_units_sem * $unit_price;
            
            if ($tuition > 0 && $enrollment_status == 'enrolled') {
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
                
                // Second partial payment for some
                if ($status == 'partial' && rand(1,100) <= 40) {
                    $second_amount = round($remaining * (0.2 + rand(10,50)/100), 2);
                    $second_remaining = round($remaining - $second_amount, 2);
                    $second_permit = 'PAY' . str_pad(rand(1,999999), 6, '0', STR_PAD_LEFT);
                    $second_amount_text = numberToWords($second_amount) . ' pesos';
                    $stmt = $pdo->prepare("INSERT INTO payments (student_id, permit_number, amount, amount_text, remaining_balance, payment_status, description, issued_date, issued_by, school_year, payment_category, units, created_at)
                                           VALUES (?, ?, ?, ?, ?, 'partial', ?, ?, 'CASH001', '2024-2025', 'tuition', ?, NOW())");
                    $stmt->execute([$user_id, $second_permit, $second_amount, $second_amount_text, $second_remaining, "Additional payment", date('Y-m-d', strtotime("+".rand(10,60)." days", strtotime($issued_date))), $total_units_sem]);
                }
            }
            
            $generated++;
            if ($generated % 20 == 0) {
                echo "Generated $generated students... (Irregular: $irregular_count)<br>";
                flush();
            }
        }
    }
    
    $pdo->commit();
    echo "<h2 style='color:green'>Success! Generated $generated students.</h2>";
    echo "<p><strong>Irregular students created: $irregular_count</strong> (assigned to multiple sections with extra subjects)</p>";
    echo "<a href='manage_users.php'>Go to Manage Users</a>";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "<h2 style='color:red'>Error: " . $e->getMessage() . "</h2>";
}

// Function to populate class schedules for sections
function populateClassSchedules($pdo) {
    // First, check if schedules already exist
    $stmt = $pdo->query("SELECT COUNT(*) FROM class_schedule");
    if ($stmt->fetchColumn() > 0) {
        return; // Schedules already exist
    }
    
    $schedules = [
        // BSIT1A
        ['subject_code' => 'IT101', 'section_code' => 'BSIT1A', 'day' => 'Monday', 'start' => '08:00:00', 'end' => '10:30:00', 'room' => 'IT Lab 101', 'instructor' => 'Prof. Juan Santos'],
        ['subject_code' => 'IT102', 'section_code' => 'BSIT1A', 'day' => 'Tuesday', 'start' => '08:00:00', 'end' => '10:30:00', 'room' => 'IT Lab 102', 'instructor' => 'Prof. Maria Reyes'],
        ['subject_code' => 'GE101', 'section_code' => 'BSIT1A', 'day' => 'Wednesday', 'start' => '08:00:00', 'end' => '10:30:00', 'room' => 'Room 201', 'instructor' => 'Dr. Jose Cruz'],
        ['subject_code' => 'GE102', 'section_code' => 'BSIT1A', 'day' => 'Thursday', 'start' => '10:30:00', 'end' => '13:00:00', 'room' => 'Room 202', 'instructor' => 'Dr. Ana Lopez'],
        ['subject_code' => 'PE1', 'section_code' => 'BSIT1A', 'day' => 'Friday', 'start' => '13:00:00', 'end' => '15:00:00', 'room' => 'Gymnasium', 'instructor' => 'Coach Robert'],
        ['subject_code' => 'NSTP1', 'section_code' => 'BSIT1A', 'day' => 'Saturday', 'start' => '08:00:00', 'end' => '11:00:00', 'room' => 'Room 301', 'instructor' => 'Dr. Ramon Garcia'],
        
        // BSIT1B
        ['subject_code' => 'IT101', 'section_code' => 'BSIT1B', 'day' => 'Monday', 'start' => '10:30:00', 'end' => '13:00:00', 'room' => 'IT Lab 101', 'instructor' => 'Prof. Juan Santos'],
        ['subject_code' => 'IT102', 'section_code' => 'BSIT1B', 'day' => 'Tuesday', 'start' => '10:30:00', 'end' => '13:00:00', 'room' => 'IT Lab 102', 'instructor' => 'Prof. Maria Reyes'],
        ['subject_code' => 'GE101', 'section_code' => 'BSIT1B', 'day' => 'Wednesday', 'start' => '10:30:00', 'end' => '13:00:00', 'room' => 'Room 201', 'instructor' => 'Dr. Jose Cruz'],
        ['subject_code' => 'GE102', 'section_code' => 'BSIT1B', 'day' => 'Thursday', 'start' => '13:00:00', 'end' => '15:30:00', 'room' => 'Room 202', 'instructor' => 'Dr. Ana Lopez'],
        ['subject_code' => 'PE1', 'section_code' => 'BSIT1B', 'day' => 'Friday', 'start' => '15:00:00', 'end' => '17:00:00', 'room' => 'Gymnasium', 'instructor' => 'Coach Robert'],
        ['subject_code' => 'NSTP1', 'section_code' => 'BSIT1B', 'day' => 'Saturday', 'start' => '08:00:00', 'end' => '11:00:00', 'room' => 'Room 301', 'instructor' => 'Dr. Ramon Garcia'],
        
        // BSIT2A
        ['subject_code' => 'IT201', 'section_code' => 'BSIT2A', 'day' => 'Monday', 'start' => '08:00:00', 'end' => '10:30:00', 'room' => 'IT Lab 201', 'instructor' => 'Prof. Carlos Mendoza'],
        ['subject_code' => 'IT202', 'section_code' => 'BSIT2A', 'day' => 'Tuesday', 'start' => '08:00:00', 'end' => '10:30:00', 'room' => 'IT Lab 202', 'instructor' => 'Prof. Lisa Santos'],
        ['subject_code' => 'IT203', 'section_code' => 'BSIT2A', 'day' => 'Wednesday', 'start' => '08:00:00', 'end' => '10:30:00', 'room' => 'Net Lab', 'instructor' => 'Engr. Mark Rivera'],
        ['subject_code' => 'GE109', 'section_code' => 'BSIT2A', 'day' => 'Thursday', 'start' => '08:00:00', 'end' => '10:30:00', 'room' => 'Room 205', 'instructor' => 'Dr. Jose Cruz'],
        ['subject_code' => 'PE3', 'section_code' => 'BSIT2A', 'day' => 'Friday', 'start' => '13:00:00', 'end' => '15:00:00', 'room' => 'Gymnasium', 'instructor' => 'Coach Robert'],
        
        // BSIT2B
        ['subject_code' => 'IT201', 'section_code' => 'BSIT2B', 'day' => 'Monday', 'start' => '10:30:00', 'end' => '13:00:00', 'room' => 'IT Lab 201', 'instructor' => 'Prof. Carlos Mendoza'],
        ['subject_code' => 'IT202', 'section_code' => 'BSIT2B', 'd
        ay' => 'Tuesday', 'start' => '10:30:00', 'end' => '13:00:00', 'room' => 'IT Lab 202', 'instructor' => 'Prof. Lisa Santos'],
        ['subject_code' => 'IT203', 'section_code' => 'BSIT2B', 'day' => 'Wednesday', 'start' => '10:30:00', 'end' => '13:00:00', 'room' => 'Net Lab', 'instructor' => 'Engr. Mark Rivera'],
        ['subject_code' => 'GE109', 'section_code' => 'BSIT2B', 'day' => 'Thursday', 'start' => '10:30:00', 'end' => '13:00:00', 'room' => 'Room 205', 'instructor' => 'Dr. Jose Cruz'],
        ['subject_code' => 'PE3', 'section_code' => 'BSIT2B', 'day' => 'Friday', 'start' => '15:00:00', 'end' => '17:00:00', 'room' => 'Gymnasium', 'instructor' => 'Coach Robert'],
        
        // BSIT3A
        ['subject_code' => 'IT301', 'section_code' => 'BSIT3A', 'day' => 'Monday', 'start' => '08:00:00', 'end' => '10:30:00', 'room' => 'IT Lab 301', 'instructor' => 'Prof. Antonio Dela Cruz'],
        ['subject_code' => 'IT302', 'section_code' => 'BSIT3A', 'day' => 'Tuesday', 'start' => '08:00:00', 'end' => '10:30:00', 'room' => 'IT Lab 302', 'instructor' => 'Prof. Josephine Ramos'],
        ['subject_code' => 'IT303', 'section_code' => 'BSIT3A', 'day' => 'Wednesday', 'start' => '08:00:00', 'end' => '10:30:00', 'room' => 'Mobile Lab', 'instructor' => 'Prof. Michael Santos'],
        
        // BSIT4A
        ['subject_code' => 'IT401', 'section_code' => 'BSIT4A', 'day' => 'Monday', 'start' => '08:00:00', 'end' => '10:30:00', 'room' => 'Capstone Lab', 'instructor' => 'Prof. Richard Gomez'],
        ['subject_code' => 'IT403', 'section_code' => 'BSIT4A', 'day' => 'Wednesday', 'start' => '08:00:00', 'end' => '17:00:00', 'room' => 'Industry Partner', 'instructor' => 'Industry Supervisor'],
        ['subject_code' => 'IT405', 'section_code' => 'BSIT4A', 'day' => 'Friday', 'start' => '08:00:00', 'end' => '10:30:00', 'room' => 'Security Lab', 'instructor' => 'Prof. Grace Santos'],
        
        // BSCS1A
        ['subject_code' => 'CS102', 'section_code' => 'BSCS1A', 'day' => 'Monday', 'start' => '08:00:00', 'end' => '10:30:00', 'room' => 'CS Lab 101', 'instructor' => 'Prof. Elena Torres'],
        ['subject_code' => 'CS104', 'section_code' => 'BSCS1A', 'day' => 'Tuesday', 'start' => '08:00:00', 'end' => '10:30:00', 'room' => 'Room 203', 'instructor' => 'Dr. Michael Tan'],
        ['subject_code' => 'GE101', 'section_code' => 'BSCS1A', 'day' => 'Wednesday', 'start' => '08:00:00', 'end' => '10:30:00', 'room' => 'Room 201', 'instructor' => 'Dr. Jose Cruz'],
        ['subject_code' => 'PE1', 'section_code' => 'BSCS1A', 'day' => 'Thursday', 'start' => '13:00:00', 'end' => '15:00:00', 'room' => 'Gymnasium', 'instructor' => 'Coach Robert'],
        ['subject_code' => 'NSTP1', 'section_code' => 'BSCS1A', 'day' => 'Saturday', 'start' => '08:00:00', 'end' => '11:00:00', 'room' => 'Room 302', 'instructor' => 'Dr. Leticia Ramos'],
        
        // BSBA1A
        ['subject_code' => 'BA101', 'section_code' => 'BSBA1A', 'day' => 'Monday', 'start' => '08:00:00', 'end' => '10:30:00', 'room' => 'Room 401', 'instructor' => 'Prof. Ricardo Gomez'],
        ['subject_code' => 'BA102', 'section_code' => 'BSBA1A', 'day' => 'Tuesday', 'start' => '08:00:00', 'end' => '10:30:00', 'room' => 'Room 402', 'instructor' => 'Dr. Cynthia Villar'],
        ['subject_code' => 'GE101', 'section_code' => 'BSBA1A', 'day' => 'Wednesday', 'start' => '08:00:00', 'end' => '10:30:00', 'room' => 'Room 201', 'instructor' => 'Dr. Jose Cruz'],
        ['subject_code' => 'PE1', 'section_code' => 'BSBA1A', 'day' => 'Thursday', 'start' => '13:00:00', 'end' => '15:00:00', 'room' => 'Gymnasium', 'instructor' => 'Coach Robert'],
        ['subject_code' => 'NSTP1', 'section_code' => 'BSBA1A', 'day' => 'Saturday', 'start' => '08:00:00', 'end' => '11:00:00', 'room' => 'Room 303', 'instructor' => 'Dr. Leticia Ramos'],
        
        // BSA1A
        ['subject_code' => 'ACC101', 'section_code' => 'BSA1A', 'day' => 'Monday', 'start' => '08:00:00', 'end' => '10:30:00', 'room' => 'Room 501', 'instructor' => 'Prof. Ferdinand Cruz'],
        ['subject_code' => 'ACC104', 'section_code' => 'BSA1A', 'day' => 'Tuesday', 'start' => '08:00:00', 'end' => '10:30:00', 'room' => 'Room 502', 'instructor' => 'Dr. Leni Robredo'],
        ['subject_code' => 'GE101', 'section_code' => 'BSA1A', 'day' => 'Wednesday', 'start' => '08:00:00', 'end' => '10:30:00', 'room' => 'Room 201', 'instructor' => 'Dr. Jose Cruz'],
        ['subject_code' => 'PE1', 'section_code' => 'BSA1A', 'day' => 'Thursday', 'start' => '13:00:00', 'end' => '15:00:00', 'room' => 'Gymnasium', 'instructor' => 'Coach Robert'],
        ['subject_code' => 'NSTP1', 'section_code' => 'BSA1A', 'day' => 'Saturday', 'start' => '08:00:00', 'end' => '11:00:00', 'room' => 'Room 304', 'instructor' => 'Dr. Leticia Ramos'],
    ];
    
    foreach ($schedules as $sched) {
        $stmt = $pdo->prepare("
            INSERT INTO class_schedule (subject_id, section_id, day_of_week, start_time, end_time, room, instructor)
            SELECT s.id, sec.id, ?, ?, ?, ?, ?
            FROM subjects s, sections sec
            WHERE s.subject_code = ? AND sec.section_code = ?
            ON DUPLICATE KEY UPDATE day_of_week = VALUES(day_of_week), start_time = VALUES(start_time), end_time = VALUES(end_time)
        ");
        $stmt->execute([
            $sched['day'], $sched['start'], $sched['end'], 
            $sched['room'], $sched['instructor'],
            $sched['subject_code'], $sched['section_code']
        ]);
    }
}
?>