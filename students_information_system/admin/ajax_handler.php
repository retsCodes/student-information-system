<?php
error_reporting(0);
ini_set('display_errors', 0);
ob_clean();
header('Content-Type: application/json');

require_once '../init.php';

// Set JSON header
header('Content-Type: application/json');

// Get the action
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Initialize response array
$response = ['success' => false, 'message' => 'Invalid action'];

try {
    $pdo = getDBConnection();

    switch ($action) {
        // =======================================================
        // COURSE & CURRICULUM RELATED ACTIONS
        // =======================================================

        case 'get_course_curriculum':
            $course_id = intval($_GET['course_id'] ?? 0);
            $response = getCourseCurriculum($pdo, $course_id);
            break;
        case 'load_curriculum':
            $course_id = intval($_GET['course_id'] ?? 0);
            if ($course_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid course ID']);
                exit;
            }

            try {
                $stmt = $pdo->prepare("
                        SELECT cc.id as curriculum_id, cc.course_id, cc.subject_id, cc.year_level, cc.semester, cc.is_required,
                               s.subject_code, s.subject_name, s.units, s.description
                        FROM course_curriculum cc
                        JOIN subjects s ON cc.subject_id = s.id
                        WHERE cc.course_id = ?
                        ORDER BY cc.year_level, cc.semester, s.subject_code
                    ");
                $stmt->execute([$course_id]);
                $curriculum_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode([
                    'success' => true,
                    'curriculum_data' => $curriculum_data,
                    'total_units' => array_sum(array_column($curriculum_data, 'units'))
                ]);
                exit;
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit;
            }
            break;

            // =======================================================
// AJAX PAGINATION ENDPOINTS
// =======================================================

case 'get_paginated_payments':
    // Require login
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    
    require_once '../includes/pagination_helper.php';
    
    $role = $_SESSION['role'] ?? '';
    $page = intval($_GET['page'] ?? 1);
    $per_page = intval($_GET['per_page'] ?? 25);
    
    $filters = [
        'status' => $_GET['status'] ?? '',
        'search' => $_GET['search'] ?? '',
        'date_from' => $_GET['date_from'] ?? '',
        'date_to' => $_GET['date_to'] ?? ''
    ];
    
    try {
        $pdo = getDBConnection();
        $pagination = new PaginationHelper($pdo);
        
        // Build WHERE based on role
        $where = "1=1";
        $params = [];
        
        if ($role === 'cashier') {
            $where .= " AND p.issued_by = ?";
            $params[] = $_SESSION['user_id'];
        } elseif ($role === 'student') {
            $where .= " AND p.student_id = ?";
            $params[] = $_SESSION['user_id'];
        }
        
        if (!empty($filters['status'])) {
            $where .= " AND p.payment_status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $where .= " AND (p.description LIKE ? OR p.permit_number LIKE ? OR si.name LIKE ? OR si.user_id LIKE ?)";
            $search_param = "%{$filters['search']}%";
            $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
        }
        
        if (!empty($filters['date_from'])) {
            $where .= " AND p.issued_date >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where .= " AND p.issued_date <= ?";
            $params[] = $filters['date_to'];
        }
        
        $results = $pagination
            ->setTable('payments p')
            ->setColumns('p.*, si.name as student_name, si.program, si.year_level, u.name as issued_by_name')
            ->setJoins('JOIN students_info si ON p.student_id = si.user_id JOIN users u ON p.issued_by = u.user_id')
            ->setWhere($where, $params)
            ->setOrderBy('ORDER BY p.issued_date DESC, p.id DESC')
            ->setPage($page)
            ->setPerPage($per_page)
            ->getResults();
        
        echo json_encode([
            'success' => true,
            'data' => $results['data'],
            'pagination' => $results['pagination']
        ]);
        exit;
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
    break;

case 'get_paginated_users':
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    
    require_once '../includes/pagination_helper.php';
    
    $page = intval($_GET['page'] ?? 1);
    $per_page = intval($_GET['per_page'] ?? 25);
    
    $filters = [
        'role' => $_GET['role'] ?? '',
        'status' => $_GET['status'] ?? '',
        'search' => $_GET['search'] ?? '',
        'program' => $_GET['program'] ?? '',
        'year_level' => $_GET['year_level'] ?? ''
    ];
    
    try {
        $pdo = getDBConnection();
        $results = getPaginatedUsers($pdo, $page, $per_page, $filters);
        
        echo json_encode([
            'success' => true,
            'data' => $results['data'],
            'pagination' => $results['pagination']
        ]);
        exit;
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
    break;

case 'get_paginated_study_load':
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    
    require_once '../includes/pagination_helper.php';
    
    $student_id = $_SESSION['user_id'];
    $page = intval($_GET['page'] ?? 1);
    $per_page = intval($_GET['per_page'] ?? 10);
    $year_filter = intval($_GET['year'] ?? 0);
    
    try {
        $pdo = getDBConnection();
        $pagination = new PaginationHelper($pdo);
        
        $where = "ss.student_id = ?";
        $params = [$student_id];
        
        if ($year_filter > 0) {
            $where .= " AND sec.year_level = ?";
            $params[] = $year_filter;
        }
        
        $results = $pagination
            ->setTable('student_sections ss')
            ->setColumns('DISTINCT s.*, sec.year_level as section_year, sec.semester as section_semester, sec.section_code')
            ->setJoins('JOIN sections sec ON ss.section_id = sec.id JOIN subject_sections subsec ON sec.id = subsec.section_id JOIN subjects s ON subsec.subject_id = s.id')
            ->setWhere($where, $params)
            ->setOrderBy('ORDER BY sec.year_level, sec.semester, s.subject_code')
            ->setPage($page)
            ->setPerPage($per_page)
            ->getResults();
        
        echo json_encode([
            'success' => true,
            'data' => $results['data'],
            'pagination' => $results['pagination']
        ]);
        exit;
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
    break;

case 'get_paginated_completions':
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    
    require_once '../includes/pagination_helper.php';
    
    $student_id = $_SESSION['user_id'];
    $page = intval($_GET['page'] ?? 1);
    $per_page = intval($_GET['per_page'] ?? 10);
    $year_filter = intval($_GET['year'] ?? 0);
    
    try {
        $pdo = getDBConnection();
        $pagination = new PaginationHelper($pdo);
        
        $where = "scc.student_id = ?";
        $params = [$student_id];
        
        if ($year_filter > 0) {
            $where .= " AND scc.year_level = ?";
            $params[] = $year_filter;
        }
        
        $results = $pagination
            ->setTable('student_course_completion scc')
            ->setColumns('scc.*, s.subject_code, s.subject_name, s.units')
            ->setJoins('JOIN subjects s ON scc.subject_id = s.id')
            ->setWhere($where, $params)
            ->setOrderBy('ORDER BY scc.year_level, scc.semester, scc.date_completed DESC')
            ->setPage($page)
            ->setPerPage($per_page)
            ->getResults();
        
        echo json_encode([
            'success' => true,
            'data' => $results['data'],
            'pagination' => $results['pagination']
        ]);
        exit;
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
    break;

        case 'calculate_payment':
            $course_id = intval($_GET['course_id'] ?? 0);
            $response = calculatePayment($pdo, $course_id);
            break;
        case 'recalc_student_units':
            $student_id = $_GET['student_id'] ?? '';
            if (empty($student_id)) {
                echo json_encode(['success' => false, 'message' => 'Student ID required']);
                exit;
            }
            try {
                // Sum units from subjects in student's sections or direct assignments
                $stmt = $pdo->prepare("
                        SELECT COALESCE(SUM(s.units), 0) as total_units
                        FROM (
                            SELECT subject_id FROM student_sections ss
                            JOIN subject_sections subsec ON ss.section_id = subsec.section_id
                            WHERE ss.student_id = ?
                            UNION
                            SELECT subject_id FROM student_subjects WHERE student_id = ? AND status = 'active'
                        ) AS all_subjects
                        JOIN subjects s ON all_subjects.subject_id = s.id
                    ");
                $stmt->execute([$student_id, $student_id]);
                $total_units = $stmt->fetchColumn();

                // Update students_info
                $stmt = $pdo->prepare("UPDATE students_info SET total_units = ? WHERE user_id = ?");
                $stmt->execute([$total_units, $student_id]);

                echo json_encode(['success' => true, 'total_units' => $total_units]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            case 'get_student_total_units':
                $student_id = $_GET['student_id'] ?? '';
            
                if (empty($student_id)) {
                    echo json_encode(['success' => false, 'message' => 'Student ID required']);
                    exit;
                }
            
                try {
                    $pdo = getDBConnection();
                    
                    $stmt = $pdo->prepare("
                        SELECT COALESCE(SUM(s.units), 0) as total_units,
                               COUNT(DISTINCT s.id) as total_subjects
                        FROM (
                            SELECT subject_id FROM student_sections ss
                            JOIN subject_sections subsec ON ss.section_id = subsec.section_id
                            WHERE ss.student_id = ?
                            UNION
                            SELECT subject_id FROM student_subjects WHERE student_id = ? AND status = 'active'
                        ) AS all_subjects
                        JOIN subjects s ON all_subjects.subject_id = s.id
                        WHERE s.units IS NOT NULL AND s.units > 0
                    ");
                    $stmt->execute([$student_id, $student_id]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $total_units = $result['total_units'] ?? 0;
                    $total_subjects = $result['total_subjects'] ?? 0;
                    
                    $stmt = $pdo->prepare("UPDATE students_info SET total_units = ? WHERE user_id = ?");
                    $stmt->execute([$total_units, $student_id]);
            
                    echo json_encode(['success' => true, 'total_units' => $total_units, 'total_subjects' => $total_subjects]);
                    exit;
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                    exit;
                }
                break;

        case 'get_course_details':
            $course_id = intval($_GET['course_id'] ?? 0);
            $response = getCourseDetails($pdo, $course_id);
            break;

            case 'get_paginated_audit_logs':
                if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
                    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                    exit;
                }
                
                $user_id = $_SESSION['user_id'];
                $page = intval($_POST['page'] ?? 1);
                $per_page = intval($_POST['per_page'] ?? 20);
                $date_from = $_POST['date_from'] ?? '';
                $date_to = $_POST['date_to'] ?? '';
                $search = $_POST['search'] ?? '';
                
                $allowed_per_page = [10, 20, 50, 100];
                if (!in_array($per_page, $allowed_per_page)) {
                    $per_page = 20;
                }
                
                $offset = ($page - 1) * $per_page;
                
                $where_conditions = ["(al.user_id = ? OR al.description LIKE ?)"];
                $params = [$user_id, "%{$user_id}%"];
                
                if (!empty($date_from)) {
                    $where_conditions[] = "DATE(al.created_at) >= ?";
                    $params[] = $date_from;
                }
                if (!empty($date_to)) {
                    $where_conditions[] = "DATE(al.created_at) <= ?";
                    $params[] = $date_to;
                }
                if (!empty($search)) {
                    $where_conditions[] = "(al.description LIKE ? OR al.action LIKE ?)";
                    $search_param = "%{$search}%";
                    $params[] = $search_param;
                    $params[] = $search_param;
                }
                
                $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
                
                // Get total count
                $count_sql = "SELECT COUNT(*) as total FROM activity_logs al JOIN users u ON al.user_id = u.user_id {$where_clause}";
                $stmt = $pdo->prepare($count_sql);
                $stmt->execute($params);
                $total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                $total_pages = ceil($total_records / $per_page);
                
                // Get data
                $query = "SELECT al.*, u.name as user_name, u.role as user_role
                          FROM activity_logs al
                          JOIN users u ON al.user_id = u.user_id
                          {$where_clause}
                          ORDER BY al.created_at DESC
                          LIMIT {$per_page} OFFSET {$offset}";
                
                $stmt = $pdo->prepare($query);
                $stmt->execute($params);
                $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'data' => $logs,
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $per_page,
                        'total_records' => $total_records,
                        'total_pages' => $total_pages,
                        'showing_start' => $offset + 1,
                        'showing_end' => min($offset + $per_page, $total_records)
                    ]
                ]);
                exit;
                break;

        case 'get_assessment_file':
            $student_id = sanitizeInput($_GET['student_id'] ?? $_SESSION['user_id'] ?? '');
            $response = getAssessmentFile($pdo, $student_id, $_SESSION);
            break;

        case 'get_available_subjects_for_curriculum':
            $course_id = intval($_GET['course_id'] ?? 0);
            if ($course_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid course ID']);
                exit;
            }
            try {
                $stmt = $pdo->prepare("
                        SELECT s.* FROM subjects s
                        WHERE s.id NOT IN (
                            SELECT subject_id FROM course_curriculum WHERE course_id = ?
                        )
                        ORDER BY s.subject_code
                    ");
                $stmt->execute([$course_id]);
                $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'subjects' => $subjects]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;

            

        case 'get_course_curriculum_full':
            $course_id = intval($_GET['course_id'] ?? 0);
            $response = getCourseCurriculumFull($pdo, $course_id);
            break;
        // Add these cases to your ajax_handler.php

        case 'auto_fill_curriculum':
            $course_id = intval($_POST['course_id'] ?? 0);
            $year_level = intval($_POST['year_level'] ?? 0);
            $semester = $_POST['semester'] ?? '';

            if ($course_id <= 0 || $year_level <= 0 || empty($semester)) {
                echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
                exit;
            }

            try {
                // Get subjects that match the program and year/semester
                $stmt = $pdo->prepare("
                    SELECT s.id 
                    FROM subjects s
                    JOIN courses c ON c.id = ?
                    WHERE (s.program = c.course_name OR s.program = 'General Education' OR s.program IS NULL)
                    AND s.year_level = ? 
                    AND s.semester = ?
                    AND s.id NOT IN (
                        SELECT subject_id FROM course_curriculum 
                        WHERE course_id = ? AND year_level = ? AND semester = ?
                    )
                ");
                $stmt->execute([$course_id, $year_level, $semester, $course_id, $year_level, $semester]);
                $subjects = $stmt->fetchAll(PDO::FETCH_COLUMN);

                $added = 0;
                foreach ($subjects as $subject_id) {
                    $stmt = $pdo->prepare("
                        INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required) 
                        VALUES (?, ?, ?, ?, 1)
                    ");
                    $stmt->execute([$course_id, $subject_id, $year_level, $semester]);
                    $added++;
                }

                // Recalculate total units
                recalculateCourseTotalUnits($pdo, $course_id);

                echo json_encode(['success' => true, 'added_count' => $added]);
                exit;

            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit;
            }
            break;
            case 'get_available_courses_for_transfer':
                $student_id = $_GET['student_id'] ?? '';
                
                try {
                    $pdo = getDBConnection();
                    
                    // Get current student's course to exclude it
                    $current_course_id = null;
                    if (!empty($student_id)) {
                        $stmt = $pdo->prepare("SELECT course_id FROM students_info WHERE user_id = ?");
                        $stmt->execute([$student_id]);
                        $current_course_id = $stmt->fetchColumn();
                    }
                    
                    // Get all active courses except current
                    if ($current_course_id) {
                        $stmt = $pdo->prepare("SELECT id, course_code, course_name FROM courses WHERE status = 'active' AND id != ? ORDER BY course_code");
                        $stmt->execute([$current_course_id]);
                    } else {
                        $stmt = $pdo->prepare("SELECT id, course_code, course_name FROM courses WHERE status = 'active' ORDER BY course_code");
                        $stmt->execute();
                    }
                    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    echo json_encode(['success' => true, 'courses' => $courses]);
                    exit;
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                    exit;
                }
                break;

                case 'upload_grades_csv':
                    // Check if user is admin or registrar
                    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'registrar'])) {
                        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                        exit;
                    }
                    
                    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                        echo json_encode(['success' => false, 'message' => 'Please select a valid CSV file']);
                        exit;
                    }
                    
                    $student_id = $_POST['student_id'] ?? '';
                    if (empty($student_id)) {
                        echo json_encode(['success' => false, 'message' => 'Student ID is required']);
                        exit;
                    }
                    
                    // Check file extension
                    $file_ext = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
                    if ($file_ext !== 'csv') {
                        echo json_encode(['success' => false, 'message' => 'Please upload a CSV file']);
                        exit;
                    }
                    
                    $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
                    if (!$file) {
                        echo json_encode(['success' => false, 'message' => 'Failed to open file']);
                        exit;
                    }
                    
                    // Read header row (skip it)
                    $headers = fgetcsv($file);
                    
                    $success_count = 0;
                    $error_count = 0;
                    $errors = [];
                    $row_number = 1;
                    
                    try {
                        $pdo->beginTransaction();
                        
                        while (($row = fgetcsv($file)) !== false) {
                            $row_number++;
                            
                            // Skip empty rows (all columns empty)
                            if (empty(array_filter($row))) {
                                continue;
                            }
                            
                            // Ensure we have at least 2 columns
                            $subject_code = trim($row[0] ?? '');
                            $grade_value = trim($row[1] ?? '');
                            $year_level = isset($row[2]) ? intval(trim($row[2])) : 1;
                            $semester = trim($row[3] ?? '1st');
                            $academic_year = trim($row[4] ?? date('Y') . '-' . (date('Y') + 1));
                            
                            // Validate required fields
                            if (empty($subject_code)) {
                                $errors[] = "Row {$row_number}: Subject code is required";
                                $error_count++;
                                continue;
                            }
                            
                            if (empty($grade_value)) {
                                $errors[] = "Row {$row_number}: Grade is required for subject '{$subject_code}'";
                                $error_count++;
                                continue;
                            }
                            
                            // Validate year level
                            if ($year_level < 1 || $year_level > 4) {
                                $errors[] = "Row {$row_number}: Invalid year level '{$year_level}'. Must be 1-4";
                                $error_count++;
                                continue;
                            }
                            
                            // Validate semester
                            if (!in_array($semester, ['1st', '2nd', 'summer'])) {
                                $errors[] = "Row {$row_number}: Invalid semester '{$semester}'. Use '1st', '2nd', or 'summer'";
                                $error_count++;
                                continue;
                            }
                            
                            // Get subject ID
                            $stmt = $pdo->prepare("SELECT id, units, subject_name FROM subjects WHERE subject_code = ?");
                            $stmt->execute([$subject_code]);
                            $subject = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if (!$subject) {
                                $errors[] = "Row {$row_number}: Subject code '{$subject_code}' not found in database";
                                $error_count++;
                                continue;
                            }
                            
                            // Convert grade to numeric
                            $grade_numeric = null;
                            $grade_map = [
                                '1.0' => 1.00, '1.00' => 1.00,
                                '1.25' => 1.25,
                                '1.5' => 1.50, '1.50' => 1.50,
                                '1.75' => 1.75,
                                '2.0' => 2.00, '2.00' => 2.00,
                                '2.25' => 2.25,
                                '2.5' => 2.50, '2.50' => 2.50,
                                '2.75' => 2.75,
                                '3.0' => 3.00, '3.00' => 3.00,
                                '5.0' => 5.00, '5.00' => 5.00,
                                'A' => 1.00, 'A+' => 1.00, 'A-' => 1.25,
                                'B' => 1.75, 'B+' => 1.50, 'B-' => 2.00,
                                'C' => 2.50, 'C+' => 2.25, 'C-' => 2.75,
                                'D' => 3.00,
                                'F' => 5.00,
                                'P' => 3.00, 'Pass' => 3.00,
                                'INC' => null, 'Incomplete' => null,
                                'W' => null, 'Withdrawn' => null
                            ];
                            
                            $grade_upper = strtoupper(trim($grade_value));
                            if (isset($grade_map[$grade_upper])) {
                                $grade_numeric = $grade_map[$grade_upper];
                            } elseif (is_numeric($grade_value)) {
                                $grade_numeric = floatval($grade_value);
                            } else {
                                $errors[] = "Row {$row_number}: Invalid grade format '{$grade_value}' for subject '{$subject_code}'";
                                $error_count++;
                                continue;
                            }
                            
                            // Check if grade record already exists
                            $stmt = $pdo->prepare("
                                SELECT id FROM student_course_completion 
                                WHERE student_id = ? AND subject_id = ? AND year_level = ? AND semester = ?
                            ");
                            $stmt->execute([$student_id, $subject['id'], $year_level, $semester]);
                            
                            if ($stmt->fetch()) {
                                // Update existing record
                                $stmt = $pdo->prepare("
                                    UPDATE student_course_completion 
                                    SET grade = ?, status = 'completed', date_completed = CURDATE()
                                    WHERE student_id = ? AND subject_id = ? AND year_level = ? AND semester = ?
                                ");
                                $stmt->execute([$grade_numeric, $student_id, $subject['id'], $year_level, $semester]);
                            } else {
                                // Insert new record
                                $stmt = $pdo->prepare("
                                    INSERT INTO student_course_completion 
                                    (student_id, subject_id, year_level, semester, academic_year, grade, status, date_completed)
                                    VALUES (?, ?, ?, ?, ?, ?, 'completed', CURDATE())
                                ");
                                $stmt->execute([$student_id, $subject['id'], $year_level, $semester, $academic_year, $grade_numeric]);
                            }
                            
                            $success_count++;
                        }
                        
                        fclose($file);
                        $pdo->commit();
                        
                        logActivity($_SESSION['user_id'], 'Grades Uploaded (CSV)', 
                            "Uploaded grades for student {$student_id}: {$success_count} successful, {$error_count} failed");
                        
                        echo json_encode([
                            'success' => true,
                            'message' => "Grades uploaded successfully!",
                            'details' => "✅ {$success_count} grades saved",
                            'errors' => $errors,
                            'success_count' => $success_count,
                            'error_count' => $error_count
                        ]);
                        
                    } catch (Exception $e) {
                        if ($pdo->inTransaction()) $pdo->rollBack();
                        fclose($file);
                        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                    }
                    exit;
                    break;
                    case 'upload_section_grades_csv':
                        // Check if user is admin
                        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
                            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                            exit;
                        }
                        
                        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                            echo json_encode(['success' => false, 'message' => 'Please select a valid CSV file']);
                            exit;
                        }
                        
                        $section_id = intval($_POST['section_id'] ?? 0);
                        if ($section_id <= 0) {
                            echo json_encode(['success' => false, 'message' => 'Section ID is required']);
                            exit;
                        }
                        
                        $file_ext = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
                        if ($file_ext !== 'csv') {
                            echo json_encode(['success' => false, 'message' => 'Please upload a CSV file']);
                            exit;
                        }
                        
                        $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
                        if (!$file) {
                            echo json_encode(['success' => false, 'message' => 'Failed to open file']);
                            exit;
                        }
                        
                        // Read header row
                        $headers = fgetcsv($file);
                        
                        $success_count = 0;
                        $error_count = 0;
                        $errors = [];
                        $row_number = 1;
                        
                        // Get section info
                        $stmt = $pdo->prepare("SELECT section_code, program, year_level FROM sections WHERE id = ?");
                        $stmt->execute([$section_id]);
                        $section_info = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if (!$section_info) {
                            echo json_encode(['success' => false, 'message' => 'Section not found']);
                            exit;
                        }
                        
                        try {
                            $pdo->beginTransaction();
                            
                            while (($row = fgetcsv($file)) !== false) {
                                $row_number++;
                                
                                if (empty(array_filter($row))) continue;
                                
                                // Expected columns: Student ID, Student Name, Subject Code, Grade, Subject Name, Year Level, Semester
                                $student_id = trim($row[0] ?? '');
                                $student_name = trim($row[1] ?? '');
                                $subject_code = trim($row[2] ?? '');
                                $grade_value = trim($row[3] ?? '');
                                $subject_name = trim($row[4] ?? '');
                                $year_level = isset($row[5]) ? intval(trim($row[5])) : $section_info['year_level'];
                                $semester = trim($row[6] ?? '1st');
                                $academic_year = trim($row[7] ?? date('Y') . '-' . (date('Y') + 1));
                                
                                if (empty($student_id)) {
                                    $errors[] = "Row {$row_number}: Student ID is required";
                                    $error_count++;
                                    continue;
                                }
                                
                                if (empty($subject_code)) {
                                    $errors[] = "Row {$row_number}: Subject code is required for student {$student_id}";
                                    $error_count++;
                                    continue;
                                }
                                
                                if (empty($grade_value)) {
                                    $errors[] = "Row {$row_number}: Grade is required for student {$student_id} - {$subject_code}";
                                    $error_count++;
                                    continue;
                                }
                                
                                // Verify student exists and is in this section
                                $stmt = $pdo->prepare("
                                    SELECT u.user_id, u.name 
                                    FROM users u
                                    JOIN student_sections ss ON u.user_id = ss.student_id
                                    WHERE u.user_id = ? AND ss.section_id = ? AND u.role = 'student'
                                ");
                                $stmt->execute([$student_id, $section_id]);
                                $student = $stmt->fetch(PDO::FETCH_ASSOC);
                                
                                if (!$student) {
                                    $errors[] = "Row {$row_number}: Student '{$student_id}' not found or not in this section";
                                    $error_count++;
                                    continue;
                                }
                                
                                // Get subject ID
                                $stmt = $pdo->prepare("SELECT id, units FROM subjects WHERE subject_code = ?");
                                $stmt->execute([$subject_code]);
                                $subject = $stmt->fetch(PDO::FETCH_ASSOC);
                                
                                if (!$subject) {
                                    $errors[] = "Row {$row_number}: Subject code '{$subject_code}' not found";
                                    $error_count++;
                                    continue;
                                }
                                
                                // Convert grade to numeric
                                $grade_numeric = null;
                                $grade_map = [
                                    '1.0' => 1.00, '1.00' => 1.00, '1.25' => 1.25,
                                    '1.5' => 1.50, '1.50' => 1.50, '1.75' => 1.75,
                                    '2.0' => 2.00, '2.00' => 2.00, '2.25' => 2.25,
                                    '2.5' => 2.50, '2.50' => 2.50, '2.75' => 2.75,
                                    '3.0' => 3.00, '3.00' => 3.00, '5.0' => 5.00, '5.00' => 5.00,
                                    'A' => 1.00, 'A-' => 1.25, 'B+' => 1.50, 'B' => 1.75, 'B-' => 2.00,
                                    'C+' => 2.25, 'C' => 2.50, 'C-' => 2.75, 'D' => 3.00, 'F' => 5.00,
                                    'P' => 3.00, 'INC' => null, 'W' => null
                                ];
                                
                                $grade_upper = strtoupper(trim($grade_value));
                                if (isset($grade_map[$grade_upper])) {
                                    $grade_numeric = $grade_map[$grade_upper];
                                } elseif (is_numeric($grade_value)) {
                                    $grade_numeric = floatval($grade_value);
                                } else {
                                    $errors[] = "Row {$row_number}: Invalid grade format '{$grade_value}' for student {$student_id} - {$subject_code}";
                                    $error_count++;
                                    continue;
                                }
                                
                                // Check if grade record exists
                                $stmt = $pdo->prepare("
                                    SELECT id FROM student_course_completion 
                                    WHERE student_id = ? AND subject_id = ? AND year_level = ? AND semester = ?
                                ");
                                $stmt->execute([$student_id, $subject['id'], $year_level, $semester]);
                                
                                if ($stmt->fetch()) {
                                    // Update existing record
                                    $stmt = $pdo->prepare("
                                        UPDATE student_course_completion 
                                        SET grade = ?, status = 'completed', date_completed = CURDATE()
                                        WHERE student_id = ? AND subject_id = ? AND year_level = ? AND semester = ?
                                    ");
                                    $stmt->execute([$grade_numeric, $student_id, $subject['id'], $year_level, $semester]);
                                } else {
                                    // Insert new record
                                    $stmt = $pdo->prepare("
                                        INSERT INTO student_course_completion 
                                        (student_id, subject_id, year_level, semester, academic_year, grade, status, date_completed)
                                        VALUES (?, ?, ?, ?, ?, ?, 'completed', CURDATE())
                                    ");
                                    $stmt->execute([$student_id, $subject['id'], $year_level, $semester, $academic_year, $grade_numeric]);
                                }
                                
                                $success_count++;
                            }
                            
                            fclose($file);
                            $pdo->commit();
                            
                            logActivity($_SESSION['user_id'], 'Section Grades Uploaded (CSV)', 
                                "Uploaded grades for section {$section_info['section_code']}: {$success_count} successful, {$error_count} failed");
                            
                            echo json_encode([
                                'success' => true,
                                'message' => "Grades uploaded successfully for section {$section_info['section_code']}!",
                                'details' => "✅ {$success_count} grades saved",
                                'errors' => $errors,
                                'success_count' => $success_count,
                                'error_count' => $error_count
                            ]);
                            
                        } catch (Exception $e) {
                            if ($pdo->inTransaction()) $pdo->rollBack();
                            fclose($file);
                            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                        }
                        exit;
                        break;

                        case 'upload_grades_bulk':
                            // Check if user is admin or registrar
                            if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'registrar'])) {
                                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                                exit;
                            }
                            
                            if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                                echo json_encode(['success' => false, 'message' => 'Please select a valid CSV file']);
                                exit;
                            }
                            
                            $upload_type = $_POST['upload_type'] ?? 'section'; // 'section', 'subject', or 'custom'
                            $section_id = intval($_POST['section_id'] ?? 0);
                            $subject_id = intval($_POST['subject_id'] ?? 0);
                            
                            $file_ext = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
                            if ($file_ext !== 'csv') {
                                echo json_encode(['success' => false, 'message' => 'Please upload a CSV file']);
                                exit;
                            }
                            
                            $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
                            if (!$file) {
                                echo json_encode(['success' => false, 'message' => 'Failed to open file']);
                                exit;
                            }
                            
                            // Read header row
                            $headers = fgetcsv($file);
                            
                            // Detect column mapping
                            $col_map = [
                                'student_id' => array_search('Student ID', $headers),
                                'student_name' => array_search('Student Name', $headers),
                                'subject_code' => array_search('Subject Code', $headers),
                                'grade' => array_search('Grade', $headers),
                                'subject_name' => array_search('Subject Name', $headers),
                                'year_level' => array_search('Year Level', $headers),
                                'semester' => array_search('Semester', $headers),
                                'academic_year' => array_search('Academic Year', $headers)
                            ];
                            
                            // If standard headers not found, try alternative naming
                            if ($col_map['student_id'] === false) $col_map['student_id'] = array_search('StudentID', $headers);
                            if ($col_map['student_id'] === false) $col_map['student_id'] = 0;
                            if ($col_map['student_name'] === false) $col_map['student_name'] = 1;
                            if ($col_map['subject_code'] === false) $col_map['subject_code'] = 2;
                            if ($col_map['grade'] === false) $col_map['grade'] = 3;
                            
                            $success_count = 0;
                            $error_count = 0;
                            $errors = [];
                            $row_number = 1;
                            
                            try {
                                $pdo->beginTransaction();
                                
                                // If uploading by subject, get subject info once
                                $subject_info = null;
                                if ($upload_type === 'subject' && $subject_id > 0) {
                                    $stmt = $pdo->prepare("SELECT id, subject_code, subject_name, units FROM subjects WHERE id = ?");
                                    $stmt->execute([$subject_id]);
                                    $subject_info = $stmt->fetch(PDO::FETCH_ASSOC);
                                    if (!$subject_info) {
                                        echo json_encode(['success' => false, 'message' => 'Subject not found']);
                                        exit;
                                    }
                                }
                                
                                // If uploading by section, get section info once
                                $section_info = null;
                                if ($upload_type === 'section' && $section_id > 0) {
                                    $stmt = $pdo->prepare("SELECT id, section_code, program, year_level FROM sections WHERE id = ?");
                                    $stmt->execute([$section_id]);
                                    $section_info = $stmt->fetch(PDO::FETCH_ASSOC);
                                    if (!$section_info) {
                                        echo json_encode(['success' => false, 'message' => 'Section not found']);
                                        exit;
                                    }
                                    
                                    // Get all students in this section for validation
                                    $stmt = $pdo->prepare("
                                        SELECT u.user_id, u.name 
                                        FROM student_sections ss
                                        JOIN users u ON ss.student_id = u.user_id
                                        WHERE ss.section_id = ?
                                    ");
                                    $stmt->execute([$section_id]);
                                    $section_students = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                                }
                                
                                while (($row = fgetcsv($file)) !== false) {
                                    $row_number++;
                                    
                                    if (empty(array_filter($row))) continue;
                                    
                                    // Extract data from row
                                    $student_id = trim($row[$col_map['student_id']] ?? '');
                                    $student_name = trim($row[$col_map['student_name']] ?? '');
                                    $subject_code = trim($row[$col_map['subject_code']] ?? '');
                                    $grade_value = trim($row[$col_map['grade']] ?? '');
                                    $subject_name = trim($row[$col_map['subject_name']] ?? '');
                                    $year_level = isset($row[$col_map['year_level']]) ? intval(trim($row[$col_map['year_level']])) : ($section_info['year_level'] ?? 1);
                                    $semester = trim($row[$col_map['semester']] ?? '1st');
                                    $academic_year = trim($row[$col_map['academic_year']] ?? date('Y') . '-' . (date('Y') + 1));
                                    
                                    if (empty($student_id)) {
                                        $errors[] = "Row {$row_number}: Student ID is required";
                                        $error_count++;
                                        continue;
                                    }
                                    
                                    if (empty($grade_value)) {
                                        $errors[] = "Row {$row_number}: Grade is required for student {$student_id}";
                                        $error_count++;
                                        continue;
                                    }
                                    
                                    // Get or validate subject
                                    if ($upload_type === 'subject' && $subject_info) {
                                        $subject_id_val = $subject_info['id'];
                                        $subject_code_val = $subject_info['subject_code'];
                                    } else {
                                        if (empty($subject_code)) {
                                            $errors[] = "Row {$row_number}: Subject code is required";
                                            $error_count++;
                                            continue;
                                        }
                                        $stmt = $pdo->prepare("SELECT id, subject_code, units FROM subjects WHERE subject_code = ?");
                                        $stmt->execute([$subject_code]);
                                        $subject = $stmt->fetch(PDO::FETCH_ASSOC);
                                        if (!$subject) {
                                            $errors[] = "Row {$row_number}: Subject code '{$subject_code}' not found";
                                            $error_count++;
                                            continue;
                                        }
                                        $subject_id_val = $subject['id'];
                                        $subject_code_val = $subject['subject_code'];
                                    }
                                    
                                    // Validate student for section upload
                                    if ($upload_type === 'section' && $section_info) {
                                        if (!isset($section_students[$student_id])) {
                                            $errors[] = "Row {$row_number}: Student '{$student_id}' is not in section {$section_info['section_code']}";
                                            $error_count++;
                                            continue;
                                        }
                                    } else {
                                        // Verify student exists
                                        $stmt = $pdo->prepare("SELECT user_id, name FROM users WHERE user_id = ? AND role = 'student'");
                                        $stmt->execute([$student_id]);
                                        $student = $stmt->fetch(PDO::FETCH_ASSOC);
                                        if (!$student) {
                                            $errors[] = "Row {$row_number}: Student '{$student_id}' not found";
                                            $error_count++;
                                            continue;
                                        }
                                    }
                                    
                                    // Convert grade to numeric
                                    $grade_numeric = null;
                                    $grade_map = [
                                        '1.0' => 1.00, '1.00' => 1.00, '1.25' => 1.25,
                                        '1.5' => 1.50, '1.50' => 1.50, '1.75' => 1.75,
                                        '2.0' => 2.00, '2.00' => 2.00, '2.25' => 2.25,
                                        '2.5' => 2.50, '2.50' => 2.50, '2.75' => 2.75,
                                        '3.0' => 3.00, '3.00' => 3.00, '5.0' => 5.00, '5.00' => 5.00,
                                        'A' => 1.00, 'A-' => 1.25, 'B+' => 1.50, 'B' => 1.75, 'B-' => 2.00,
                                        'C+' => 2.25, 'C' => 2.50, 'C-' => 2.75, 'D' => 3.00, 'F' => 5.00,
                                        'P' => 3.00, 'INC' => null, 'W' => null, 'DRP' => null
                                    ];
                                    
                                    $grade_upper = strtoupper(trim($grade_value));
                                    if (isset($grade_map[$grade_upper])) {
                                        $grade_numeric = $grade_map[$grade_upper];
                                    } elseif (is_numeric($grade_value)) {
                                        $grade_numeric = floatval($grade_value);
                                    } else {
                                        $errors[] = "Row {$row_number}: Invalid grade format '{$grade_value}' for student {$student_id}";
                                        $error_count++;
                                        continue;
                                    }
                                    
                                    // Check if grade record exists
                                    $stmt = $pdo->prepare("
                                        SELECT id FROM student_course_completion 
                                        WHERE student_id = ? AND subject_id = ? AND year_level = ? AND semester = ?
                                    ");
                                    $stmt->execute([$student_id, $subject_id_val, $year_level, $semester]);
                                    
                                    if ($stmt->fetch()) {
                                        // Update existing record
                                        $stmt = $pdo->prepare("
                                            UPDATE student_course_completion 
                                            SET grade = ?, status = 'completed', date_completed = CURDATE()
                                            WHERE student_id = ? AND subject_id = ? AND year_level = ? AND semester = ?
                                        ");
                                        $stmt->execute([$grade_numeric, $student_id, $subject_id_val, $year_level, $semester]);
                                    } else {
                                        // Insert new record
                                        $stmt = $pdo->prepare("
                                            INSERT INTO student_course_completion 
                                            (student_id, subject_id, year_level, semester, academic_year, grade, status, date_completed)
                                            VALUES (?, ?, ?, ?, ?, ?, 'completed', CURDATE())
                                        ");
                                        $stmt->execute([$student_id, $subject_id_val, $year_level, $semester, $academic_year, $grade_numeric]);
                                    }
                                    
                                    $success_count++;
                                }
                                
                                fclose($file);
                                $pdo->commit();
                                
                                $upload_type_name = '';
                                if ($upload_type === 'section' && $section_info) {
                                    $upload_type_name = "section {$section_info['section_code']}";
                                } elseif ($upload_type === 'subject' && $subject_info) {
                                    $upload_type_name = "subject {$subject_info['subject_code']} - {$subject_info['subject_name']}";
                                } else {
                                    $upload_type_name = "custom list";
                                }
                                
                                logActivity($_SESSION['user_id'], 'Grades Uploaded (Bulk)', 
                                    "Uploaded grades for {$upload_type_name}: {$success_count} successful, {$error_count} failed");
                                
                                echo json_encode([
                                    'success' => true,
                                    'message' => "Grades uploaded successfully for {$upload_type_name}!",
                                    'details' => "✅ {$success_count} grades saved",
                                    'errors' => $errors,
                                    'success_count' => $success_count,
                                    'error_count' => $error_count
                                ]);
                                
                            } catch (Exception $e) {
                                if ($pdo->inTransaction()) $pdo->rollBack();
                                fclose($file);
                                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                            }
                            exit;
                            break;

                        case 'get_students_by_section':
                            $section_id = intval($_GET['section_id'] ?? 0);
                            
                            if ($section_id <= 0) {
                                echo json_encode(['success' => false, 'message' => 'Invalid section ID']);
                                exit;
                            }
                            
                            try {
                                $stmt = $pdo->prepare("
                                    SELECT u.user_id, u.name, si.program, si.year_level
                                    FROM student_sections ss
                                    JOIN users u ON ss.student_id = u.user_id
                                    JOIN students_info si ON u.user_id = si.user_id
                                    WHERE ss.section_id = ? AND u.role = 'student'
                                    ORDER BY u.name
                                ");
                                $stmt->execute([$section_id]);
                                $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                echo json_encode(['success' => true, 'students' => $students]);
                                exit;
                            } catch (Exception $e) {
                                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                                exit;
                            }
                            break;

case 'get_transfer_curriculum_comparison':
    $student_id = $_GET['student_id'] ?? '';
    $course_id = intval($_GET['course_id'] ?? 0);
    
    if (empty($student_id) || $course_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit;
    }
    
    try {
        // Get student's completed subjects from current course
        $stmt = $pdo->prepare("
            SELECT scc.*, s.subject_code, s.subject_name, s.units
            FROM student_course_completion scc
            JOIN subjects s ON scc.subject_id = s.id
            WHERE scc.student_id = ? AND scc.status = 'completed'
        ");
        $stmt->execute([$student_id]);
        $completed_subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Also get previous completions
        $stmt = $pdo->prepare("
            SELECT spc.*, s.subject_code, s.subject_name, s.units
            FROM student_previous_completions spc
            JOIN subjects s ON spc.subject_id = s.id
            WHERE spc.student_id = ?
        ");
        $stmt->execute([$student_id]);
        $previous_completions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Merge all completed subjects
        $all_completed = array_merge($completed_subjects, $previous_completions);
        
        // Get new course curriculum
        $stmt = $pdo->prepare("
            SELECT cc.*, s.subject_code, s.subject_name, s.units
            FROM course_curriculum cc
            JOIN subjects s ON cc.subject_id = s.id
            WHERE cc.course_id = ?
        ");
        $stmt->execute([$course_id]);
        $new_curriculum = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Create lookup for new curriculum subjects by code and name
        $new_subjects_by_code = [];
        $new_subjects_by_name = [];
        foreach ($new_curriculum as $subject) {
            $new_subjects_by_code[strtoupper(trim($subject['subject_code']))] = $subject;
            $new_subjects_by_name[strtolower(trim($subject['subject_name']))] = $subject;
        }
        
        // Determine which subjects can be credited
        $creditable_subjects = [];
        $creditable_count = 0;
        
        foreach ($all_completed as $completed) {
            $can_credit = false;
            $equivalent = null;
            
            // Try to match by subject code
            $code_key = strtoupper(trim($completed['subject_code']));
            if (isset($new_subjects_by_code[$code_key])) {
                $can_credit = true;
                $equivalent = $new_subjects_by_code[$code_key];
            } 
            // Try to match by subject name
            else {
                $name_key = strtolower(trim($completed['subject_name']));
                foreach ($new_subjects_by_name as $name => $subject) {
                    // Check for similar names (basic matching)
                    if (strpos($name_key, strtolower(trim($subject['subject_name']))) !== false || 
                        strpos(strtolower(trim($subject['subject_name'])), $name_key) !== false) {
                        $can_credit = true;
                        $equivalent = $subject;
                        break;
                    }
                }
            }
            
            $creditable_subjects[] = [
                'completed_id' => $completed['subject_id'],
                'completed_code' => $completed['subject_code'],
                'completed_name' => $completed['subject_name'],
                'grade' => $completed['grade'],
                'completed_units' => $completed['units'],
                'equivalent_id' => $equivalent ? $equivalent['subject_id'] : null,
                'equivalent_code' => $equivalent ? $equivalent['subject_code'] : null,
                'equivalent_name' => $equivalent ? $equivalent['subject_name'] : null,
                'can_credit' => $can_credit
            ];
            
            if ($can_credit) $creditable_count++;
        }
        
        echo json_encode([
            'success' => true,
            'creditable_subjects' => $creditable_subjects,
            'completed_count' => count($all_completed),
            'creditable_count' => $creditable_count,
            'remaining_count' => count($new_curriculum) - $creditable_count
        ]);
        exit;
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
    break;
    
    case 'transfer_student':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method']);
            exit;
        }
        
        $student_id = sanitizeInput($_POST['student_id'] ?? '');
        $to_course_id = intval($_POST['to_course_id'] ?? 0);
        $year_level = intval($_POST['year_level'] ?? 1);
        $reason = sanitizeInput($_POST['reason'] ?? '');
        
        if (empty($student_id) || $to_course_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit;
        }
        
        try {
            $pdo->beginTransaction();
            
            // Get current student info
            $stmt = $pdo->prepare("SELECT program, year_level, course_id FROM students_info WHERE user_id = ?");
            $stmt->execute([$student_id]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get current course info
            $stmt = $pdo->prepare("SELECT id, course_code, course_name FROM courses WHERE id = ?");
            $stmt->execute([$student['course_id']]);
            $from_course = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get new course info
            $stmt = $pdo->prepare("SELECT id, course_code, course_name FROM courses WHERE id = ?");
            $stmt->execute([$to_course_id]);
            $to_course = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // 1. Save current subjects as completed records
            $stmt = $pdo->prepare("
                SELECT DISTINCT s.id, s.subject_code, s.subject_name, s.units, 
                       sec.year_level as section_year, sec.semester as section_semester 
                FROM student_sections ss 
                JOIN sections sec ON ss.section_id = sec.id 
                JOIN subject_sections subsec ON sec.id = subsec.section_id 
                JOIN subjects s ON subsec.subject_id = s.id 
                WHERE ss.student_id = ?
            ");
            $stmt->execute([$student_id]);
            $current_subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $academic_year_val = date('Y') . '-' . (date('Y') + 1);
            foreach ($current_subjects as $subject) {
                $stmt = $pdo->prepare("
                    INSERT INTO student_course_completion 
                    (student_id, subject_id, course_id, year_level, semester, academic_year, status, date_completed) 
                    VALUES (?, ?, ?, ?, ?, ?, 'completed', NOW()) 
                    ON DUPLICATE KEY UPDATE status = 'completed', date_completed = NOW()
                ");
                $stmt->execute([
                    $student_id, 
                    $subject['id'], 
                    $from_course['id'] ?? null, 
                    $subject['section_year'] ?? $student['year_level'], 
                    $subject['section_semester'] ?? '1st', 
                    $academic_year_val
                ]);
            }
            
            // 2. Mark current enrollment as transferred
            $stmt = $pdo->prepare("
                UPDATE student_course_enrollment 
                SET status = 'transferred', actual_graduation = NOW() 
                WHERE student_id = ? AND status = 'active'
            ");
            $stmt->execute([$student_id]);
            
            // 3. Record transfer
            $stmt = $pdo->prepare("
                INSERT INTO student_course_transfers 
                (student_id, from_course_id, to_course_id, from_course_code, to_course_code, 
                 from_course_name, to_course_name, transfer_date, reason, approved_by, year_level_at_transfer) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)
            ");
            $stmt->execute([
                $student_id, 
                $from_course['id'] ?? null, 
                $to_course_id, 
                $from_course['course_code'] ?? null, 
                $to_course['course_code'], 
                $from_course['course_name'] ?? null, 
                $to_course['course_name'], 
                $reason, 
                $_SESSION['user_id'], 
                $year_level
            ]);
            
            // 4. REMOVE all current sections
            $stmt = $pdo->prepare("DELETE FROM student_sections WHERE student_id = ?");
            $stmt->execute([$student_id]);
            
            // 5. Create new enrollment
            $stmt = $pdo->prepare("
                INSERT INTO student_course_enrollment 
                (student_id, course_id, enrollment_date, status, current_year_level) 
                VALUES (?, ?, NOW(), 'active', ?)
            ");
            $stmt->execute([$student_id, $to_course_id, $year_level]);
            
            // 6. UPDATE student info with NEW program
            $stmt = $pdo->prepare("
                UPDATE students_info 
                SET course_id = ?, 
                    program = ?, 
                    year_level = ?, 
                    student_type = 'irregular' 
                WHERE user_id = ?
            ");
            $stmt->execute([$to_course_id, $to_course['course_name'], $year_level, $student_id]);
            
            $pdo->commit();
            
            echo json_encode(['success' => true, 'message' => 'Student successfully transferred to ' . $to_course['course_name']]);
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
        break;

        case 'get_student_full_info':
            $student_id = $_GET['student_id'] ?? '';
            
            if (empty($student_id)) {
                echo json_encode(['success' => false, 'message' => 'Student ID required']);
                exit;
            }
            
            try {
                $pdo = getDBConnection();
                
                // Get student current info
                $stmt = $pdo->prepare("
                    SELECT program, year_level, course_id 
                    FROM students_info 
                    WHERE user_id = ?
                ");
                $stmt->execute([$student_id]);
                $student = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Check if student has transfer history
                $stmt = $pdo->prepare("
                    SELECT from_course_code, to_course_code, transfer_date 
                    FROM student_course_transfers 
                    WHERE student_id = ? 
                    ORDER BY transfer_date DESC 
                    LIMIT 1
                ");
                $stmt->execute([$student_id]);
                $last_transfer = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $has_transferred = !empty($last_transfer);
                
                echo json_encode([
                    'success' => true,
                    'current_program' => $student['program'] ?? '',
                    'year_level' => $student['year_level'] ?? 1,
                    'has_transferred' => $has_transferred,
                    'original_program' => $last_transfer['from_course_code'] ?? null,
                    'transfer_date' => $last_transfer['transfer_date'] ?? null
                ]);
                exit;
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit;
            }
            break;

    case 'get_student_current_course':
        $student_id = $_GET['student_id'] ?? '';
        
        if (empty($student_id)) {
            echo json_encode(['success' => false, 'message' => 'Student ID required']);
            exit;
        }
        
        try {
            $stmt = $pdo->prepare("
                SELECT course_id FROM students_info WHERE user_id = ?
            ");
            $stmt->execute([$student_id]);
            $course_id = $stmt->fetchColumn();
            
            echo json_encode(['success' => true, 'course_id' => $course_id]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
        break;

        case 'remove_curriculum':
            $curriculum_id = intval($_POST['curriculum_id'] ?? 0);
            $course_id = intval($_POST['course_id'] ?? 0);

            if ($curriculum_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid curriculum ID']);
                exit;
            }

            try {
                // Get course_id if not provided
                if ($course_id <= 0) {
                    $stmt = $pdo->prepare("SELECT course_id FROM course_curriculum WHERE id = ?");
                    $stmt->execute([$curriculum_id]);
                    $course_id = $stmt->fetchColumn();
                }

                // Delete the curriculum entry
                $stmt = $pdo->prepare("DELETE FROM course_curriculum WHERE id = ?");
                $stmt->execute([$curriculum_id]);

                if ($course_id > 0) {
                    // Recalculate total units for the course
                    $stmt = $pdo->prepare("SELECT SUM(s.units) as total_units 
                                               FROM course_curriculum cc 
                                               JOIN subjects s ON cc.subject_id = s.id 
                                               WHERE cc.course_id = ?");
                    $stmt->execute([$course_id]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $total_units = $result['total_units'] ?? 0;

                    $stmt = $pdo->prepare("UPDATE courses SET total_units = ? WHERE id = ?");
                    $stmt->execute([$total_units, $course_id]);
                }

                echo json_encode(['success' => true, 'message' => 'Subject removed from curriculum']);
                exit;

            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit;
            }
            break;
        case 'add_curriculum_ajax':
            $course_id = intval($_POST['course_id'] ?? 0);
            $subject_id = intval($_POST['subject_id'] ?? 0);
            $year_level = intval($_POST['year_level'] ?? 0);
            $semester = $_POST['semester'] ?? '';

            if ($course_id <= 0 || $subject_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid course or subject ID']);
                exit;
            }
            if ($year_level < 1 || $year_level > 4) {
                echo json_encode(['success' => false, 'message' => 'Year level must be between 1 and 4. Received: ' . $year_level]);
                exit;
            }
            if (!in_array($semester, ['1st', '2nd', 'summer'])) {
                echo json_encode(['success' => false, 'message' => 'Invalid semester. Received: ' . $semester]);
                exit;
            }

            try {
                $pdo = getDBConnection();

                // Check for duplicate
                $stmt = $pdo->prepare("SELECT id FROM course_curriculum WHERE course_id = ? AND subject_id = ? AND year_level = ? AND semester = ?");
                $stmt->execute([$course_id, $subject_id, $year_level, $semester]);
                if ($stmt->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'Subject already exists in this year/semester']);
                    exit;
                }

                // Insert
                $stmt = $pdo->prepare("INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required) VALUES (?, ?, ?, ?, 1)");
                $stmt->execute([$course_id, $subject_id, $year_level, $semester]);

                // Recalculate total units
                $stmt = $pdo->prepare("SELECT SUM(s.units) as total_units 
                                               FROM course_curriculum cc 
                                               JOIN subjects s ON cc.subject_id = s.id 
                                               WHERE cc.course_id = ?");
                $stmt->execute([$course_id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $total_units = $result['total_units'] ?? 0;

                $stmt = $pdo->prepare("UPDATE courses SET total_units = ? WHERE id = ?");
                $stmt->execute([$total_units, $course_id]);

                echo json_encode(['success' => true, 'message' => 'Subject added to Year ' . $year_level . ', ' . $semester . ' semester']);
                exit;
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                exit;
            }
            break;

        // =======================================================
        // USER MANAGEMENT RELATED ACTIONS
        // =======================================================

        case 'get_user_details':
            $user_id = sanitizeInput($_GET['user_id'] ?? '');
            $response = getUserDetails($pdo, $user_id);
            break;

            case 'get_student_info':
                $search = $_GET['search'] ?? '';
                $student_id = $_GET['student_id'] ?? '';
            
                if ($student_id) {
                    try {
                        $pdo = getDBConnection();
                        
                        $stmt = $pdo->prepare("
                            SELECT 
                                si.*,
                                u.name,
                                u.email,
                                u.user_status as status
                            FROM students_info si
                            JOIN users u ON si.user_id = u.user_id
                            WHERE si.user_id = ? AND u.role = 'student'
                        ");
                        $stmt->execute([$student_id]);
                        $student = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($student) {
                            $stmt = $pdo->prepare("
                                SELECT COALESCE(SUM(s.units), 0) as total_units,
                                       COUNT(DISTINCT s.id) as total_subjects
                                FROM (
                                    SELECT subject_id FROM student_sections ss
                                    JOIN subject_sections subsec ON ss.section_id = subsec.section_id
                                    WHERE ss.student_id = ?
                                    UNION
                                    SELECT subject_id FROM student_subjects WHERE student_id = ? AND status = 'active'
                                ) AS all_subjects
                                JOIN subjects s ON all_subjects.subject_id = s.id
                                WHERE s.units IS NOT NULL AND s.units > 0
                            ");
                            $stmt->execute([$student_id, $student_id]);
                            $result = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            $total_units = $result['total_units'] ?? 0;
                            $total_subjects = $result['total_subjects'] ?? 0;
                            
                            $student['total_units'] = $total_units;
                            $student['total_subjects'] = $total_subjects;
                            
                            $stmt = $pdo->prepare("UPDATE students_info SET total_units = ? WHERE user_id = ?");
                            $stmt->execute([$total_units, $student_id]);
                            
                            echo json_encode(['success' => true, 'student' => $student]);
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Student not found']);
                        }
                    } catch (Exception $e) {
                        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                    }
                    exit;
            
                } elseif ($search && strlen($search) >= 2) {
                    try {
                        $pdo = getDBConnection();
                        $stmt = $pdo->prepare("
                            SELECT u.user_id, u.name, si.program, si.year_level, si.total_units
                            FROM users u
                            JOIN students_info si ON u.user_id = si.user_id
                            WHERE u.role = 'student' 
                            AND (u.user_id LIKE ? OR u.name LIKE ?)
                            AND u.user_status = 'active'
                            LIMIT 20
                        ");
                        $search_param = "%$search%";
                        $stmt->execute([$search_param, $search_param]);
                        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        echo json_encode(['success' => true, 'students' => $students]);
                    } catch (Exception $e) {
                        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                    }
                    exit;
                }
            
                echo json_encode(['success' => false, 'message' => 'No search term provided or search too short']);
                exit;
                break;

                case 'get_student_grades':
                    $student_id = $_GET['student_id'] ?? '';
                    
                    if (empty($student_id)) {
                        echo json_encode(['success' => false, 'message' => 'Student ID required']);
                        exit;
                    }
                    
                    try {
                        $pdo = getDBConnection();
                        
                        // First, get student basic info and find their course_id
                        $stmt = $pdo->prepare("
                            SELECT si.*, u.name, u.email, c.id as course_id, c.course_code, c.course_name
                            FROM students_info si
                            JOIN users u ON si.user_id = u.user_id
                            LEFT JOIN courses c ON si.course_id = c.id
                            WHERE si.user_id = ?
                        ");
                        $stmt->execute([$student_id]);
                        $student = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if (!$student) {
                            echo json_encode(['success' => false, 'message' => 'Student not found']);
                            exit;
                        }
                        
                        // If no course_id, try to find by program name
                        $course_id = $student['course_id'];
                        if (!$course_id && $student['program']) {
                            $stmt = $pdo->prepare("SELECT id FROM courses WHERE course_name = ? OR course_code = ?");
                            $stmt->execute([$student['program'], $student['program']]);
                            $course = $stmt->fetch(PDO::FETCH_ASSOC);
                            if ($course) {
                                $course_id = $course['id'];
                            }
                        }
                        
                        // Get all curriculum subjects for this student's course
                        if ($course_id) {
                            $stmt = $pdo->prepare("
                                SELECT DISTINCT s.id, s.subject_code, s.subject_name, s.units, cc.year_level, cc.semester
                                FROM course_curriculum cc
                                JOIN subjects s ON cc.subject_id = s.id
                                WHERE cc.course_id = ?
                                ORDER BY cc.year_level, FIELD(cc.semester, '1st', '2nd', 'summer'), s.subject_code
                            ");
                            $stmt->execute([$course_id]);
                        } else {
                            // Fallback: get subjects by program name from subjects table
                            $stmt = $pdo->prepare("
                                SELECT DISTINCT s.id, s.subject_code, s.subject_name, s.units, s.year_level, s.semester
                                FROM subjects s
                                WHERE s.program = ? OR s.program = 'General Education'
                                ORDER BY s.year_level, FIELD(s.semester, '1st', '2nd', 'summer'), s.subject_code
                            ");
                            $stmt->execute([$student['program']]);
                        }
                        $curriculum = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        // If no curriculum found, show error but with empty data
                        if (empty($curriculum)) {
                            echo json_encode(['success' => true, 'grades' => [], 'student' => $student, 'message' => 'No curriculum found for this student']);
                            exit;
                        }
                        
                        // Get existing completion records
                        $stmt = $pdo->prepare("
                            SELECT * FROM student_course_completion 
                            WHERE student_id = ?
                        ");
                        $stmt->execute([$student_id]);
                        $completions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Merge curriculum with completion data
                        $grades = [];
                        foreach ($curriculum as $subject) {
                            $found = false;
                            foreach ($completions as $completion) {
                                if ($completion['subject_id'] == $subject['id'] && 
                                    $completion['year_level'] == $subject['year_level'] && 
                                    $completion['semester'] == $subject['semester']) {
                                    $grades[] = [
                                        'subject_id' => $subject['id'],
                                        'subject_code' => $subject['subject_code'],
                                        'subject_name' => $subject['subject_name'],
                                        'units' => $subject['units'],
                                        'year_level' => $subject['year_level'],
                                        'semester' => $subject['semester'],
                                        'grade' => $completion['grade'],
                                        'date_completed' => $completion['date_completed'],
                                        'status' => $completion['status']
                                    ];
                                    $found = true;
                                    break;
                                }
                            }
                            if (!$found) {
                                // Determine default status based on year level vs current student year
                                $default_status = 'upcoming';
                                if ($subject['year_level'] < $student['year_level']) {
                                    $default_status = 'pending';
                                } elseif ($subject['year_level'] == $student['year_level']) {
                                    $default_status = 'in_progress';
                                }
                                
                                $grades[] = [
                                    'subject_id' => $subject['id'],
                                    'subject_code' => $subject['subject_code'],
                                    'subject_name' => $subject['subject_name'],
                                    'units' => $subject['units'],
                                    'year_level' => $subject['year_level'],
                                    'semester' => $subject['semester'],
                                    'grade' => null,
                                    'date_completed' => null,
                                    'status' => $default_status
                                ];
                            }
                        }
                        
                        echo json_encode(['success' => true, 'grades' => $grades, 'student' => $student]);
                        exit;
                    } catch (Exception $e) {
                        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                        exit;
                    }
                    break;
                
                    case 'save_student_grades':
                        $data = json_decode(file_get_contents('php://input'), true);
                        $student_id = $data['student_id'] ?? '';
                        $grades = $data['grades'] ?? [];
                        
                        if (empty($student_id)) {
                            echo json_encode(['success' => false, 'message' => 'Student ID required']);
                            exit;
                        }
                        
                        try {
                            $pdo = getDBConnection();
                            $pdo->beginTransaction();
                            
                            foreach ($grades as $grade) {
                                $academic_year_start = 2022 + ($grade['year_level'] - 1);
                                $academic_year = $academic_year_start . '-' . ($academic_year_start + 1);
                                
                                $stmt = $pdo->prepare("
                                    INSERT INTO student_course_completion 
                                    (student_id, subject_id, year_level, semester, academic_year, grade, date_completed, status)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                                    ON DUPLICATE KEY UPDATE
                                    grade = VALUES(grade),
                                    date_completed = VALUES(date_completed),
                                    status = VALUES(status),
                                    updated_at = NOW()
                                ");
                                $stmt->execute([
                                    $student_id,
                                    $grade['subject_id'],
                                    $grade['year_level'],
                                    $grade['semester'],
                                    $academic_year,
                                    !empty($grade['grade']) ? $grade['grade'] : null,
                                    !empty($grade['date_completed']) ? $grade['date_completed'] : null,
                                    $grade['status']
                                ]);
                            }
                            
                            $pdo->commit();
                            echo json_encode(['success' => true, 'message' => 'Grades saved successfully']);
                            exit;
                        } catch (Exception $e) {
                            $pdo->rollBack();
                            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                            exit;
                        }
                        break;

        // =======================================================
        // SUBJECT & SECTION RELATED ACTIONS
        // =======================================================

        case 'get_subject_sections':
            $subject_id = intval($_GET['subject_id'] ?? 0);
            $response = getSubjectSections($pdo, $subject_id);
            break;

        case 'get_section_subjects':
            $section_id = intval($_GET['section_id'] ?? 0);
            $response = getSectionSubjects($pdo, $section_id);
            break;

        case 'get_section_info':
            $section_id = intval($_GET['section_id'] ?? 0);
            $response = getSectionInfo($pdo, $section_id);
            break;

        case 'get_subject_details':
            $subject_id = intval($_GET['subject_id'] ?? 0);
            $response = getSubjectDetails($pdo, $subject_id);
            break;

        case 'get_subject_statistics':
            $subject_id = intval($_GET['subject_id'] ?? 0);
            $response = getSubjectStatistics($pdo, $subject_id);
            break;

        case 'get_section_statistics':
            $section_id = intval($_GET['section_id'] ?? 0);
            $response = getSectionStatistics($pdo, $section_id);
            break;

        case 'bulk_assign_subjects':
            $section_id = intval($_POST['section_id'] ?? 0);
            $subject_ids = $_POST['subject_ids'] ?? [];

            if ($section_id <= 0 || empty($subject_ids)) {
                echo json_encode(['success' => false, 'message' => 'Invalid section or subjects']);
                exit;
            }

            try {
                $assigned = 0;
                foreach ($subject_ids as $subject_id) {
                    $stmt = $pdo->prepare("INSERT IGNORE INTO subject_sections (subject_id, section_id) VALUES (?, ?)");
                    $stmt->execute([$subject_id, $section_id]);
                    if ($stmt->rowCount() > 0)
                        $assigned++;
                }
                echo json_encode(['success' => true, 'assigned_count' => $assigned]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;

        // =======================================================
        // PAYMENT RELATED ACTIONS
        // =======================================================

        case 'get_payment_summary':
            $student_id = sanitizeInput($_GET['student_id'] ?? '');
            $response = getPaymentSummary($pdo, $student_id);
            break;

        case 'calculate_student_balance':
            $student_id = sanitizeInput($_GET['student_id'] ?? '');
            $response = calculateStudentBalance($pdo, $student_id);
            break;

        case 'get_student_payments':
            $student_id = sanitizeInput($_GET['student_id'] ?? '');
            $limit = intval($_GET['limit'] ?? 10);
            $response = getStudentPayments($pdo, $student_id, $limit);
            break;

        // =======================================================
        // SETTINGS RELATED ACTIONS
        // =======================================================

        case 'get_settings':
            $category = $_GET['category'] ?? '';
            $response = getSettings($pdo, $category);
            break;

        case 'update_setting':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $response = ['success' => false, 'message' => 'Invalid request method'];
            } else {
                $name = sanitizeInput($_POST['name'] ?? '');
                $value = sanitizeInput($_POST['value'] ?? '');
                $response = updateSetting($pdo, $name, $value);
            }
            break;

        // =======================================================
        // REPORT & STATISTICS ACTIONS
        // =======================================================

        case 'get_course_statistics':
            $course_id = intval($_GET['course_id'] ?? 0);
            $response = getCourseStatistics($pdo, $course_id);
            break;

        case 'get_enrollment_stats':
            $response = getEnrollmentStats($pdo);
            break;

        case 'get_payment_stats':
            $start_date = $_GET['start_date'] ?? date('Y-m-01');
            $end_date = $_GET['end_date'] ?? date('Y-m-t');
            $response = getPaymentStats($pdo, $start_date, $end_date);
            break;

        // =======================================================
        // LOGS MANAGEMENT RELATED ACTIONS
        // =======================================================

        case 'log_activity':
            if (isset($_POST['action_type']) && isset($_POST['description'])) {
                $userId = $_SESSION['user_id'] ?? 'SYSTEM';
                logActivity($userId, $_POST['action_type'], $_POST['description']);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Missing parameters']);
            }
            break;
        // =======================================================
        // COURSE MANAGEMENT ACTIONS
        // =======================================================

        case 'auto_fill_curriculum_semester':
            $course_id = intval($_GET['course_id'] ?? 0);
            $year_level = intval($_GET['year_level'] ?? 1);
            $semester = $_GET['semester'] ?? '1st';

            // Get subjects that should be in curriculum for this course/year/semester
            $stmt = $pdo->prepare("
                    SELECT id FROM subjects 
                    WHERE (program = (SELECT course_name FROM courses WHERE id = ?) OR program = 'General Education')
                    AND year_level = ? AND semester = ?
                ");
            $stmt->execute([$course_id, $year_level, $semester]);
            $subjects = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $added = 0;
            foreach ($subjects as $subject_id) {
                $stmt = $pdo->prepare("INSERT IGNORE INTO course_curriculum (course_id, subject_id, year_level, semester, is_required) VALUES (?, ?, ?, ?, 1)");
                $stmt->execute([$course_id, $subject_id, $year_level, $semester]);
                if ($stmt->rowCount() > 0)
                    $added++;
            }

            echo json_encode(['success' => true, 'added_count' => $added]);
            break;

        case 'auto_fill_curriculum_year':
            $course_id = intval($_GET['course_id'] ?? 0);
            $year_level = intval($_GET['year_level'] ?? 1);

            $semesters = ['1st', '2nd', 'summer'];
            $total_added = 0;

            foreach ($semesters as $semester) {
                $stmt = $pdo->prepare("
                        SELECT id FROM subjects 
                        WHERE (program = (SELECT course_name FROM courses WHERE id = ?) OR program = 'General Education')
                        AND year_level = ? AND semester = ?
                    ");
                $stmt->execute([$course_id, $year_level, $semester]);
                $subjects = $stmt->fetchAll(PDO::FETCH_COLUMN);

                foreach ($subjects as $subject_id) {
                    $stmt = $pdo->prepare("INSERT IGNORE INTO course_curriculum (course_id, subject_id, year_level, semester, is_required) VALUES (?, ?, ?, ?, 1)");
                    $stmt->execute([$course_id, $subject_id, $year_level, $semester]);
                    if ($stmt->rowCount() > 0)
                        $total_added++;
                }
            }

            echo json_encode(['success' => true, 'added_count' => $total_added]);
            break;

        case 'test':
            echo json_encode(['success' => true, 'message' => 'JSON is clean']);
            exit;

        case 'get_section_details':
            $section_id = intval($_GET['section_id'] ?? 0);
            $stmt = $pdo->prepare("
                        SELECT s.*, c.course_code, c.course_name 
                        FROM sections s
                        LEFT JOIN courses c ON s.course_id = c.id
                        WHERE s.id = ?
                    ");
            $stmt->execute([$section_id]);
            $section = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$section) {
                echo json_encode(['success' => false, 'message' => 'Section not found']);
                exit;
            } else {
                echo json_encode(['success' => true, 'section' => $section]);
                exit;
            }
            break;

        case 'get_section_assigned_subjects':
            $section_id = intval($_GET['section_id'] ?? 0);
            if ($section_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid section ID']);
                break;
            }
            $stmt = $pdo->prepare("
                            SELECT s.* FROM subject_sections ss
                            JOIN subjects s ON ss.subject_id = s.id
                            WHERE ss.section_id = ?
                            ORDER BY s.subject_code
                        ");
            $stmt->execute([$section_id]);
            $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'subjects' => $subjects]);
            exit;
            break;

        case 'get_available_subjects_for_section':
            $section_id = intval($_GET['section_id'] ?? 0);

            if ($section_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid section ID']);
                exit;
            }

            try {
                $pdo = getDBConnection();
                $stmt = $pdo->prepare("
                                SELECT s.id, s.subject_code, s.subject_name, s.units, s.program, s.year_level, s.semester
                                FROM subjects s
                                WHERE s.id NOT IN (
                                    SELECT subject_id FROM subject_sections WHERE section_id = ?
                                )
                                ORDER BY s.subject_code
                            ");
                $stmt->execute([$section_id]);
                $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'subjects' => $subjects]);
                exit;

            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit;
            }
            break;

        case 'get_student_subjects':
            $student_id = $_GET['student_id'] ?? '';
            if (empty($student_id)) {
                echo json_encode(['success' => false, 'message' => 'Student ID required']);
                exit;
            }
            try {
                $stmt = $pdo->prepare("
                                    SELECT DISTINCT s.id, s.subject_code, s.subject_name, s.units
                                    FROM subjects s
                                    WHERE s.id IN (
                                        SELECT subject_id FROM student_sections ss
                                        JOIN subject_sections subsec ON ss.section_id = subsec.section_id
                                        WHERE ss.student_id = ?
                                        UNION
                                        SELECT subject_id FROM student_subjects WHERE student_id = ? AND status = 'active'
                                    )
                                    ORDER BY s.subject_code
                                ");
                $stmt->execute([$student_id, $student_id]);
                $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'subjects' => $subjects]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;

        case 'get_section_schedules':
            $section_id = intval($_GET['section_id'] ?? 0);
            if ($section_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid section ID']);
                exit;
            }

            try {
                // Get schedules for this section
                $stmt = $pdo->prepare("
                                        SELECT cs.*, s.subject_code, s.subject_name
                                        FROM class_schedule cs
                                        JOIN subjects s ON cs.subject_id = s.id
                                        WHERE cs.section_id = ?
                                        ORDER BY FIELD(cs.day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), cs.start_time
                                    ");
                $stmt->execute([$section_id]);
                $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Get subjects assigned to this section (from subject_sections)
                $stmt = $pdo->prepare("
                                        SELECT s.id, s.subject_code, s.subject_name, s.units,
                                            CASE WHEN cs.id IS NOT NULL THEN 1 ELSE 0 END as has_schedule
                                        FROM subjects s
                                        JOIN subject_sections ss ON s.id = ss.subject_id
                                        LEFT JOIN class_schedule cs ON s.id = cs.subject_id AND cs.section_id = ss.section_id
                                        WHERE ss.section_id = ?
                                        GROUP BY s.id
                                        ORDER BY s.subject_code
                                    ");
                $stmt->execute([$section_id]);
                $available_subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode([
                    'success' => true,
                    'schedules' => $schedules,
                    'available_subjects' => $available_subjects
                ]);
                exit;
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit;
            }
            break;

        case 'add_schedule':
            $section_id = intval($_POST['section_id'] ?? 0);
            $subject_id = intval($_POST['subject_id'] ?? 0);
            $day_of_week = $_POST['day_of_week'] ?? '';
            $start_time = $_POST['start_time'] ?? '';
            $end_time = $_POST['end_time'] ?? '';
            $room = $_POST['room'] ?? 'TBA';

            if ($section_id <= 0 || $subject_id <= 0 || empty($day_of_week) || empty($start_time) || empty($end_time)) {
                echo json_encode(['success' => false, 'message' => 'Missing required fields']);
                exit;
            }

            try {
                // Check if schedule already exists for this subject and section
                $stmt = $pdo->prepare("SELECT id FROM class_schedule WHERE section_id = ? AND subject_id = ?");
                $stmt->execute([$section_id, $subject_id]);
                $existing = $stmt->fetch();

                if ($existing) {
                    // Update existing schedule
                    $stmt = $pdo->prepare("
                                                UPDATE class_schedule 
                                                SET day_of_week = ?, start_time = ?, end_time = ?, room = ?
                                                WHERE section_id = ? AND subject_id = ?
                                            ");
                    $stmt->execute([$day_of_week, $start_time, $end_time, $room, $section_id, $subject_id]);
                    echo json_encode(['success' => true, 'message' => 'Schedule updated successfully']);
                } else {
                    // Insert new schedule
                    $stmt = $pdo->prepare("
                                                INSERT INTO class_schedule (section_id, subject_id, day_of_week, start_time, end_time, room)
                                                VALUES (?, ?, ?, ?, ?, ?)
                                            ");
                    $stmt->execute([$section_id, $subject_id, $day_of_week, $start_time, $end_time, $room]);
                    echo json_encode(['success' => true, 'message' => 'Schedule added successfully']);
                }
                exit;
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit;
            }
            break;

        case 'delete_schedule':
            $schedule_id = intval($_POST['schedule_id'] ?? 0);
            if ($schedule_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid schedule ID']);
                exit;
            }
            try {
                $stmt = $pdo->prepare("DELETE FROM class_schedule WHERE id = ?");
                $stmt->execute([$schedule_id]);
                echo json_encode(['success' => true, 'message' => 'Schedule deleted']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;



        // Add this case for adding subject to section
        case 'add_subject_to_section':
            $subject_id = intval($_POST['subject_id'] ?? 0);
            $section_id = intval($_POST['section_id'] ?? 0);

            if ($subject_id <= 0 || $section_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid subject or section ID']);
                exit;
            }

            try {
                // Check if already exists
                $stmt = $pdo->prepare("SELECT id FROM subject_sections WHERE subject_id = ? AND section_id = ?");
                $stmt->execute([$subject_id, $section_id]);
                if ($stmt->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'Subject already assigned to this section']);
                    exit;
                }

                $stmt = $pdo->prepare("INSERT INTO subject_sections (subject_id, section_id, is_auto_filled) VALUES (?, ?, 0)");
                $stmt->execute([$subject_id, $section_id]);

                echo json_encode(['success' => true, 'message' => 'Subject added successfully']);
                exit;

            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit;
            }
            break;

        // Add this case for removing subject from section
        case 'remove_subject_from_section':
            $subject_id = intval($_POST['subject_id'] ?? 0);
            $section_id = intval($_POST['section_id'] ?? 0);

            if ($subject_id <= 0 || $section_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid subject or section ID']);
                exit;
            }

            try {
                $stmt = $pdo->prepare("DELETE FROM subject_sections WHERE subject_id = ? AND section_id = ?");
                $stmt->execute([$subject_id, $section_id]);

                echo json_encode(['success' => true, 'message' => 'Subject removed successfully']);
                exit;

            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit;
            }
            break;

        case 'auto_fill_section_subjects':
            $section_id = intval($_POST['section_id'] ?? 0);

            // Get section details
            $stmt = $pdo->prepare("SELECT course_id, year_level, semester FROM sections WHERE id = ?");
            $stmt->execute([$section_id]);
            $section = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$section['course_id']) {
                echo json_encode(['success' => false, 'message' => 'Section not linked to a course']);
                break;
            }

            // First, clear existing assignments
            $stmt = $pdo->prepare("DELETE FROM subject_sections WHERE section_id = ?");
            $stmt->execute([$section_id]);

            // Get subjects from curriculum
            $stmt = $pdo->prepare("
                    SELECT subject_id FROM course_curriculum 
                    WHERE course_id = ? AND year_level = ? AND semester = ?
                ");
            $stmt->execute([$section['course_id'], $section['year_level'], $section['semester']]);
            $subjects = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $added = 0;
            foreach ($subjects as $subject_id) {
                $stmt = $pdo->prepare("INSERT INTO subject_sections (subject_id, section_id, is_auto_filled) VALUES (?, ?, 1)");
                $stmt->execute([$subject_id, $section_id]);
                $added++;
            }

            echo json_encode(['success' => true, 'added_count' => $added, 'message' => "$added subjects auto-filled from curriculum"]);
            break;

        case 'get_students_for_section_assignment':
            $section_id = intval($_GET['section_id'] ?? 0);

            try {
                $stmt = $pdo->prepare("
                            SELECT u.user_id, u.name, si.program, si.year_level,
                                (SELECT section_code FROM sections s 
                                 JOIN student_sections ss ON s.id = ss.section_id 
                                 WHERE ss.student_id = u.user_id LIMIT 1) as current_section
                            FROM users u
                            JOIN students_info si ON u.user_id = si.user_id
                            WHERE u.role = 'student' AND u.user_status = 'active'
                            ORDER BY u.name
                        ");
                $stmt->execute();
                $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'students' => $students]);
                exit;

            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit;
            }
            break;

        case 'get_course_curriculum_by_year_semester':
            $course_id = intval($_GET['course_id'] ?? 0);
            $year_level = intval($_GET['year_level'] ?? 1);
            $semester = $_GET['semester'] ?? '1st';

            $stmt = $pdo->prepare("
                    SELECT s.*, cc.id as curriculum_id
                    FROM course_curriculum cc
                    JOIN subjects s ON cc.subject_id = s.id
                    WHERE cc.course_id = ? AND cc.year_level = ? AND cc.semester = ?
                    ORDER BY s.subject_code
                ");
            $stmt->execute([$course_id, $year_level, $semester]);
            $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $html = '';
            foreach ($subjects as $subject) {
                $price = $subject['units'] * getGlobalUnitPrice($pdo);
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($subject['subject_code']) . '</td>';
                $html .= '<td>' . htmlspecialchars($subject['subject_name']) . '</td>';
                $html .= '<td class="text-center">' . $subject['units'] . '</td>';
                $html .= '<td class="text-end">₱' . number_format($price, 2) . '</td>';
                $html .= '</tr>';
            }

            echo json_encode(['success' => true, 'html' => $html, 'count' => count($subjects)]);
            break;

        case 'get_student_current_section':
            $student_id = $_GET['student_id'] ?? '';

            $stmt = $pdo->prepare("
                    SELECT s.*, sec.section_code, sec.section_name, sec.year_level, c.course_code
                    FROM student_sections ss
                    JOIN sections sec ON ss.section_id = sec.id
                    LEFT JOIN courses c ON sec.course_id = c.id
                    WHERE ss.student_id = ?
                    ORDER BY ss.assigned_at DESC LIMIT 1
                ");
            $stmt->execute([$student_id]);
            $current_section = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'section' => $current_section]);
            break;

        case 'assign_student_to_section':
            $student_id = $_POST['student_id'] ?? '';
            $section_id = intval($_POST['section_id'] ?? 0);
            $replace_existing = isset($_POST['replace_existing']) && $_POST['replace_existing'] == '1';

            if (!$student_id || !$section_id) {
                echo json_encode(['success' => false, 'message' => 'Invalid student or section']);
                break;
            }

            try {
                $pdo->beginTransaction();

                if ($replace_existing) {
                    // Remove from all sections first
                    $stmt = $pdo->prepare("DELETE FROM student_sections WHERE student_id = ?");
                    $stmt->execute([$student_id]);
                } else {
                    // Check if already in a section
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM student_sections WHERE student_id = ?");
                    $stmt->execute([$student_id]);
                    if ($stmt->fetchColumn() > 0) {
                        echo json_encode(['success' => false, 'message' => 'Student already has a section. Use replace option.']);
                        $pdo->rollBack();
                        break;
                    }
                }

                // Add to new section
                $stmt = $pdo->prepare("INSERT INTO student_sections (student_id, section_id) VALUES (?, ?)");
                $stmt->execute([$student_id, $section_id]);

                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Student assigned successfully']);
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            break;

        case 'bulk_assign_students':
            $student_ids = $_POST['student_ids'] ?? [];
            $section_id = intval($_POST['section_id'] ?? 0);
            $replace_all = isset($_POST['replace_all']) && $_POST['replace_all'] == '1';

            if (empty($student_ids) || !$section_id) {
                echo json_encode(['success' => false, 'message' => 'Invalid students or section']);
                exit;
            }

            try {
                $pdo->beginTransaction();
                $success_count = 0;

                foreach ($student_ids as $student_id) {
                    if ($replace_all) {
                        // Remove from all sections
                        $stmt = $pdo->prepare("DELETE FROM student_sections WHERE student_id = ?");
                        $stmt->execute([$student_id]);
                    } else {
                        // For multiple sections: check if already assigned to this exact section
                        $stmt = $pdo->prepare("SELECT id FROM student_sections WHERE student_id = ? AND section_id = ?");
                        $stmt->execute([$student_id, $section_id]);
                        if ($stmt->fetch()) {
                            // Already in this section, skip to avoid duplicate
                            continue;
                        }
                        // Do NOT delete existing sections – allow multiple
                    }

                    // Assign to the new section
                    $stmt = $pdo->prepare("INSERT INTO student_sections (student_id, section_id) VALUES (?, ?)");
                    $stmt->execute([$student_id, $section_id]);
                    $success_count++;
                }

                $pdo->commit();
                echo json_encode(['success' => true, 'message' => "$success_count student(s) assigned successfully"]);
                exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                exit;
            }
            break;

        case 'get_curriculum_subjects_for_section':
            $section_id = intval($_GET['section_id'] ?? 0);
            $course_id = intval($_GET['course_id'] ?? 0);
            $year_level = intval($_GET['year_level'] ?? 1);
            $semester = $_GET['semester'] ?? '1st';

            if ($course_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Section not linked to a course']);
                exit;
            }

            try {
                // Get subjects from curriculum that are NOT already assigned
                $stmt = $pdo->prepare("
                            SELECT s.* 
                            FROM course_curriculum cc
                            JOIN subjects s ON cc.subject_id = s.id
                            WHERE cc.course_id = ? 
                            AND cc.year_level = ? 
                            AND cc.semester = ?
                            AND s.id NOT IN (
                                SELECT subject_id FROM subject_sections WHERE section_id = ?
                            )
                            ORDER BY s.subject_code
                        ");
                $stmt->execute([$course_id, $year_level, $semester, $section_id]);
                $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'subjects' => $subjects]);
                exit;

            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit;
            }
            break;

        case 'get_students_for_section_management':
            $section_id = intval($_GET['section_id'] ?? 0);

            try {
                $pdo = getDBConnection();

                // Get assigned students
                $stmt = $pdo->prepare("
                                SELECT u.user_id, u.name, si.program, si.year_level
                                FROM student_sections ss
                                JOIN users u ON ss.student_id = u.user_id
                                JOIN students_info si ON u.user_id = si.user_id
                                WHERE ss.section_id = ?
                                ORDER BY u.name
                            ");
                $stmt->execute([$section_id]);
                $assigned = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Get available students (not in this section)
                $stmt = $pdo->prepare("
                                SELECT u.user_id, u.name, si.program, si.year_level,
                                    (SELECT section_code FROM sections s 
                                     JOIN student_sections ss2 ON s.id = ss2.section_id 
                                     WHERE ss2.student_id = u.user_id LIMIT 1) as current_section
                                FROM users u
                                JOIN students_info si ON u.user_id = si.user_id
                                WHERE u.role = 'student' 
                                AND u.user_status = 'active'
                                AND u.user_id NOT IN (SELECT student_id FROM student_sections WHERE section_id = ?)
                                ORDER BY u.name
                            ");
                $stmt->execute([$section_id]);
                $available = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'assigned' => $assigned, 'available' => $available]);
                exit;

            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit;
            }
            break;

        case 'remove_student_from_section':
            $student_id = $_POST['student_id'] ?? '';
            $section_id = intval($_POST['section_id'] ?? 0);

            if (empty($student_id) || $section_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
                exit;
            }

            try {
                $stmt = $pdo->prepare("DELETE FROM student_sections WHERE student_id = ? AND section_id = ?");
                $stmt->execute([$student_id, $section_id]);
                echo json_encode(['success' => true]);
                exit;
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit;
            }
            break;

        // Get filtered subjects for section (from curriculum by default)
        case 'get_filtered_subjects_for_section':
            $section_id = intval($_GET['section_id'] ?? 0);
            $source = $_GET['source'] ?? 'curriculum';
            $course_id = intval($_GET['course_id'] ?? 0);
            $year_level = intval($_GET['year_level'] ?? 0);
            $semester = $_GET['semester'] ?? '';
            $program = $_GET['program'] ?? '';
            $search = $_GET['search'] ?? '';

            if ($section_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid section ID']);
                exit;
            }

            try {
                // If source is curriculum and we have course/year/semester, get from curriculum
                if ($source === 'curriculum' && $course_id > 0 && $year_level > 0 && !empty($semester)) {
                    $sql = "SELECT s.* FROM subjects s
                                            WHERE s.id IN (
                                                SELECT subject_id FROM course_curriculum 
                                                WHERE course_id = ? AND year_level = ? AND semester = ?
                                            )
                                            AND s.id NOT IN (SELECT subject_id FROM subject_sections WHERE section_id = ?)";
                    $params = [$course_id, $year_level, $semester, $section_id];
                } else {
                    // Get all subjects not assigned to this section
                    $sql = "SELECT s.* FROM subjects s
                                            WHERE s.id NOT IN (SELECT subject_id FROM subject_sections WHERE section_id = ?)";
                    $params = [$section_id];
                }

                // Apply program filter
                if (!empty($program)) {
                    $sql .= " AND (s.program = ? OR s.program IS NULL OR s.program = '')";
                    $params[] = $program;
                }

                // Apply year level filter (if not already filtered by curriculum)
                if ($year_level > 0 && $source !== 'curriculum') {
                    $sql .= " AND s.year_level = ?";
                    $params[] = $year_level;
                }

                // Apply semester filter (if not already filtered by curriculum)
                if (!empty($semester) && $source !== 'curriculum') {
                    $sql .= " AND s.semester = ?";
                    $params[] = $semester;
                }

                // Apply search filter
                if (!empty($search)) {
                    $sql .= " AND (s.subject_code LIKE ? OR s.subject_name LIKE ?)";
                    $params[] = "%$search%";
                    $params[] = "%$search%";
                }

                $sql .= " ORDER BY s.subject_code";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'subjects' => $subjects]);
                exit;

            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit;
            }
            break;

        // Get all subjects for section (no filter)
        case 'get_all_subjects_for_section':
            $section_id = intval($_GET['section_id'] ?? 0);

            try {
                $stmt = $pdo->prepare("
            SELECT s.* FROM subjects s
            WHERE s.id NOT IN (
                SELECT subject_id FROM subject_sections WHERE section_id = ?
            )
            ORDER BY s.subject_code
        ");
                $stmt->execute([$section_id]);
                $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'subjects' => $subjects]);
                exit;

            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit;
            }
            break;
        case 'get_subject_details_ajax':
            $subject_id = intval($_GET['subject_id'] ?? 0);

            try {
                $pdo = getDBConnection();
                $stmt = $pdo->prepare("SELECT * FROM subjects WHERE id = ?");
                $stmt->execute([$subject_id]);
                $subject = $stmt->fetch(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'subject' => $subject]);
                exit;

            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit;
            }
            break;

        // Add subject to section AJAX (no page refresh)
        case 'add_subject_to_section_ajax':
            $subject_id = intval($_POST['subject_id'] ?? 0);
            $section_id = intval($_POST['section_id'] ?? 0);

            if ($subject_id <= 0 || $section_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
                exit;
            }

            try {
                $stmt = $pdo->prepare("INSERT IGNORE INTO subject_sections (subject_id, section_id) VALUES (?, ?)");
                $stmt->execute([$subject_id, $section_id]);

                echo json_encode(['success' => true, 'message' => 'Subject added']);
                exit;

            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit;
            }
            break;

        // Remove subject from section AJAX (no page refresh)
        case 'remove_subject_from_section_ajax':
            $subject_id = intval($_POST['subject_id'] ?? 0);
            $section_id = intval($_POST['section_id'] ?? 0);

            if ($subject_id <= 0 || $section_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
                exit;
            }

            try {
                $stmt = $pdo->prepare("DELETE FROM subject_sections WHERE subject_id = ? AND section_id = ?");
                $stmt->execute([$subject_id, $section_id]);

                echo json_encode(['success' => true, 'message' => 'Subject removed']);
                exit;

            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit;
            }
            break;
        default:
            $response = ['success' => false, 'message' => 'Unknown action'];
            exit;
    }

} catch (Exception $e) {
    $response = ['success' => false, 'message' => 'Server error: ' . $e->getMessage()];
    exit;
}

// Output JSON response
echo json_encode($response);
exit;

// =======================================================
// HELPER FUNCTIONS
// =======================================================

/**
 * Get course curriculum with details
 */
function getCourseCurriculum($pdo, $course_id)
{
    if ($course_id <= 0) {
        return ['success' => false, 'message' => 'Invalid course ID'];
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
        $stmt->execute([$course_id]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$course) {
            return ['success' => false, 'message' => 'Course not found'];
        }

        $unit_price = getGlobalUnitPrice($pdo);

        $stmt = $pdo->prepare("SELECT cc.*, s.subject_code, s.subject_name, s.units, s.description
                               FROM course_curriculum cc
                               JOIN subjects s ON cc.subject_id = s.id
                               WHERE cc.course_id = ?
                               ORDER BY cc.year_level, cc.semester, s.subject_code");
        $stmt->execute([$course_id]);
        $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total_course_price = $course['total_units'] * $unit_price;
        $price_per_year = $total_course_price / $course['duration_years'];
        $price_per_semester = $price_per_year / 2;

        $curriculum = [];
        $total_units_in_curriculum = 0;

        foreach ($subjects as $subject) {
            $year = $subject['year_level'];
            $semester = $subject['semester'];
            if (!isset($curriculum[$year])) {
                $curriculum[$year] = [];
            }
            if (!isset($curriculum[$year][$semester])) {
                $curriculum[$year][$semester] = [];
            }
            $curriculum[$year][$semester][] = $subject;
            $total_units_in_curriculum += $subject['units'];
        }

        $units_match = $total_units_in_curriculum == $course['total_units'];

        ob_start();
        ?>
                <div class="row">
                    <div class="col-md-12">
                        <div class="card mb-3">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0">Course Information</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <p><strong>Course:</strong><br><?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?></p>
                                    </div>
                                    <div class="col-md-3">
                                        <p><strong>Duration:</strong><br><?php echo $course['duration_years']; ?> years</p>
                                    </div>
                                    <div class="col-md-3">
                                        <p><strong>Total Units:</strong><br>
                                            <?php echo $course['total_units']; ?> units
                                            <?php if (!$units_match): ?>
                                                    <br><small class="text-danger">(Curriculum: <?php echo $total_units_in_curriculum; ?> units)</small>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <div class="col-md-3">
                                        <p><strong>Unit Price:</strong><br>₱<?php echo number_format($unit_price, 2); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                
                        <div class="card mb-3">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0">Curriculum</h6>
                            </div>
                            <div class="card-body">
                                <?php if (empty($curriculum)): ?>
                                        <p class="text-muted">No subjects in curriculum yet.</p>
                                <?php else: ?>
                                        <?php for ($year = 1; $year <= $course['duration_years']; $year++): ?>
                                                <h5>Year <?php echo $year; ?></h5>
                                                <?php if (isset($curriculum[$year])): ?>
                                                        <?php for ($sem = 1; $sem <= 2; $sem++): ?>
                                                                <h6 class="mt-3">Semester <?php echo $sem; ?></h6>
                                                                <?php if (isset($curriculum[$year][$sem])): ?>
                                                                        <div class="table-responsive">
                                                                            <table class="table table-sm">
                                                                                <thead>
                                                                                    <tr>
                                                                                        <th>Subject Code</th>
                                                                                        <th>Subject Name</th>
                                                                                        <th>Units</th>
                                                                                        <th>Price</th>
                                                                                        <th>Actions</th>
                                                                                    </tr>
                                                                                </thead>
                                                                                <tbody>
                                                                                    <?php
                                                                                    $semester_units = 0;
                                                                                    $semester_price = 0;
                                                                                    foreach ($curriculum[$year][$sem] as $subject):
                                                                                        $subject_price = $subject['units'] * $unit_price;
                                                                                        $semester_units += $subject['units'];
                                                                                        $semester_price += $subject_price;
                                                                                        ?>
                                                                                        <tr>
                                                                                            <td><code><?php echo htmlspecialchars($subject['subject_code']); ?></code></td>
                                                                                            <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                                                                                            <td><span class="badge bg-primary"><?php echo $subject['units']; ?> units</span></td>
                                                                                            <td>₱<?php echo number_format($subject_price, 2); ?></td>
                                                                                            <td>
                                                                                                <button class="btn btn-sm btn-outline-danger" 
                                                                                                        onclick="removeCurriculumItem('<?php echo $subject['id']; ?>', '<?php echo htmlspecialchars($subject['subject_code']); ?>')">
                                                                                                    <i class="fas fa-trash"></i>
                                                                                                </button>
                                                                                            </td>
                                                                                        </tr>
                                                                                    <?php endforeach; ?>
                                                                                </tbody>
                                                                                <tfoot>
                                                                                    <tr>
                                                                                        <td colspan="2"><strong>Semester Total:</strong></td>
                                                                                        <td><span class="badge bg-info"><?php echo $semester_units; ?> units</span></td>
                                                                                        <td><strong>₱<?php echo number_format($semester_price, 2); ?></strong></td>
                                                                                        <td></td>
                                                                                    </tr>
                                                                                </tfoot>
                                                                            </table>
                                                                        </div>
                                                                <?php else: ?>
                                                                        <p class="text-muted">No subjects for this semester.</p>
                                                                <?php endif; ?>
                                                        <?php endfor; ?>
                                                <?php else: ?>
                                                        <p class="text-muted">No subjects for this year.</p>
                                                <?php endif; ?>
                                        <?php endfor; ?>
                            
                                        <div class="alert alert-info mt-3">
                                            <h6>Curriculum Summary</h6>
                                            <p><strong>Total Units in Curriculum:</strong> <?php echo $total_units_in_curriculum; ?> units</p>
                                            <p><strong>Total Curriculum Price:</strong> ₱<?php echo number_format($total_units_in_curriculum * $unit_price, 2); ?></p>
                                            <?php if (!$units_match): ?>
                                                    <p class="text-danger">
                                                        <i class="fas fa-exclamation-triangle"></i> 
                                                        Curriculum units (<?php echo $total_units_in_curriculum; ?>) don't match course total (<?php echo $course['total_units']; ?>)
                                                    </p>
                                            <?php endif; ?>
                                        </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
                $curriculum_html = ob_get_clean();

                return [
                    'success' => true,
                    'html' => $curriculum_html,
                    'course' => $course,
                    'unit_price' => $unit_price,
                    'total_units' => $total_units_in_curriculum,
                    'total_price' => $total_units_in_curriculum * $unit_price
                ];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Load curriculum for tabbed display
 */
function loadCurriculum($pdo, $course_id)
{
    if ($course_id <= 0) {
        return ['success' => false, 'message' => 'Invalid course ID'];
    }

    try {
        $stmt = $pdo->prepare("SELECT cc.*, s.subject_code, s.subject_name, s.units, s.description
                               FROM course_curriculum cc
                               JOIN subjects s ON cc.subject_id = s.id
                               WHERE cc.course_id = ?
                               ORDER BY cc.year_level, cc.semester, s.subject_code");
        $stmt->execute([$course_id]);
        $curriculum_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'success' => true,
            'curriculum_data' => $curriculum_data,
            'total_units' => array_sum(array_column($curriculum_data, 'units'))
        ];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Get full course curriculum with all details
 */
function getCourseCurriculumFull($pdo, $course_id)
{
    if ($course_id <= 0) {
        return ['success' => false, 'message' => 'Invalid course ID'];
    }

    try {
        $stmt = $pdo->prepare("
            SELECT cc.*, s.subject_code, s.subject_name, s.units, s.description
            FROM course_curriculum cc
            JOIN subjects s ON cc.subject_id = s.id
            WHERE cc.course_id = ?
            ORDER BY cc.year_level, cc.semester, cc.order_index
        ");
        $stmt->execute([$course_id]);
        $curriculum = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
        $stmt->execute([$course_id]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);

        $unit_price = getGlobalUnitPrice($pdo);

        $grouped = [];
        $total_units = 0;
        foreach ($curriculum as $item) {
            $year = $item['year_level'];
            $semester = $item['semester'];
            if (!isset($grouped[$year][$semester])) {
                $grouped[$year][$semester] = [];
            }
            $grouped[$year][$semester][] = $item;
            $total_units += $item['units'];
        }

        ob_start();
        ?>
                <div class="curriculum-view">
                    <?php for ($year = 1; $year <= ($course['duration_years'] ?? 4); $year++): ?>
                            <?php if (isset($grouped[$year])): ?>
                                    <div class="card mb-4">
                                        <div class="card-header bg-primary text-white">
                                            <h5 class="mb-0">Year <?php echo $year; ?></h5>
                                        </div>
                                        <div class="card-body">
                                            <?php foreach (['1st', '2nd', 'summer'] as $semester): ?>
                                                    <?php if (isset($grouped[$year][$semester])): ?>
                                                            <h6><?php echo ucfirst($semester); ?> Semester</h6>
                                                            <div class="table-responsive mb-3">
                                                                <table class="table table-sm table-bordered">
                                                                    <thead class="table-light">
                                                                        <tr>
                                                                            <th>Subject Code</th>
                                                                            <th>Subject Name</th>
                                                                            <th class="text-center">Units</th>
                                                                            <th class="text-end">Price</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php
                                                                        $semester_units = 0;
                                                                        $semester_price = 0;
                                                                        foreach ($grouped[$year][$semester] as $subject):
                                                                            $subject_price = $subject['units'] * $unit_price;
                                                                            $semester_units += $subject['units'];
                                                                            $semester_price += $subject_price;
                                                                            ?>
                                                                            <tr>
                                                                                <td><code><?php echo htmlspecialchars($subject['subject_code']); ?></code></td>
                                                                                <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                                                                                <td class="text-center"><?php echo $subject['units']; ?></td>
                                                                                <td class="text-end">₱<?php echo number_format($subject_price, 2); ?></td>
                                                                            </tr>
                                                                        <?php endforeach; ?>
                                                                    </tbody>
                                                                    <tfoot class="table-info">
                                                                        <tr>
                                                                            <td colspan="2"><strong>Semester Total:</strong></td>
                                                                            <td class="text-center"><strong><?php echo $semester_units; ?> units</strong></td>
                                                                            <td class="text-end"><strong>₱<?php echo number_format($semester_price, 2); ?></strong></td>
                                                                        </tr>
                                                                    </tfoot>
                                                                </table>
                                                            </div>
                                                    <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                            <?php endif; ?>
                    <?php endfor; ?>
            
                    <div class="alert alert-success">
                        <div class="row">
                            <div class="col-md-4">
                                <strong>Total Units:</strong> <?php echo $total_units; ?> units
                            </div>
                            <div class="col-md-4">
                                <strong>Total Tuition:</strong> ₱<?php echo number_format($total_units * $unit_price, 2); ?>
                            </div>
                            <div class="col-md-4">
                                <strong>Subjects:</strong> <?php echo count($curriculum); ?> subjects
                            </div>
                        </div>
                    </div>
                </div>
                <?php
                $html = ob_get_clean();

                return [
                    'success' => true,
                    'html' => $html,
                    'total_units' => $total_units,
                    'total_price' => $total_units * $unit_price,
                    'subject_count' => count($curriculum)
                ];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Calculate payment breakdown for a course
 */
function calculatePayment($pdo, $course_id)
{
    if ($course_id <= 0) {
        return ['success' => false, 'message' => 'Invalid course ID'];
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
        $stmt->execute([$course_id]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$course) {
            return ['success' => false, 'message' => 'Course not found'];
        }

        $unit_price = getGlobalUnitPrice($pdo);

        $total_price = $course['total_units'] * $unit_price;
        $price_per_year = $total_price / $course['duration_years'];
        $price_per_semester = $price_per_year / 2;
        $subjects_per_semester = $course['total_units'] / ($course['duration_years'] * 2);
        $price_per_subject = $unit_price * $subjects_per_semester;
        $price_per_exam = $price_per_semester / 3;

        ob_start();
        ?>
                <div class="calculation-row">
                    <span>Total Course Units:</span>
                    <span><strong><?php echo $course['total_units']; ?> units</strong></span>
                </div>
                <div class="calculation-row">
                    <span>Unit Price:</span>
                    <span><strong>₱<?php echo number_format($unit_price, 2); ?></strong></span>
                </div>
                <hr>
                <div class="calculation-row total">
                    <span>Full Course Total:</span>
                    <span><strong>₱<?php echo number_format($total_price, 2); ?></strong></span>
                </div>
                <hr>
                <div class="calculation-row">
                    <span>Per Year Payment:</span>
                    <span>₱<?php echo number_format($price_per_year, 2); ?> × <?php echo $course['duration_years']; ?> years</span>
                </div>
                <div class="calculation-row">
                    <span>Per Semester Payment:</span>
                    <span>₱<?php echo number_format($price_per_semester, 2); ?> × <?php echo $course['duration_years'] * 2; ?> semesters</span>
                </div>
                <div class="calculation-row">
                    <span>Per Exam Payment:</span>
                    <span>₱<?php echo number_format($price_per_exam, 2); ?> × <?php echo $course['duration_years'] * 6; ?> exams</span>
                </div>
                <div class="calculation-row">
                    <span>Estimated Per Subject:</span>
                    <span>₱<?php echo number_format($price_per_subject, 2); ?> × ~<?php echo round($subjects_per_semester, 1); ?> units/semester</span>
                </div>
                <?php
                $html = ob_get_clean();

                return [
                    'success' => true,
                    'html' => $html,
                    'calculations' => [
                        'total_units' => $course['total_units'],
                        'unit_price' => $unit_price,
                        'total_price' => $total_price,
                        'per_year' => $price_per_year,
                        'per_semester' => $price_per_semester,
                        'per_exam' => $price_per_exam,
                        'per_subject' => $price_per_subject
                    ]
                ];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Get course details
 */
function getCourseDetails($pdo, $course_id)
{
    if ($course_id <= 0) {
        return ['success' => false, 'message' => 'Invalid course ID'];
    }

    try {
        $stmt = $pdo->prepare("SELECT c.*, 
                               COUNT(DISTINCT cc.id) as subject_count,
                               COUNT(DISTINCT sce.student_id) as student_count
                               FROM courses c
                               LEFT JOIN course_curriculum cc ON c.id = cc.course_id
                               LEFT JOIN student_course_enrollment sce ON c.id = sce.course_id AND sce.status = 'active'
                               WHERE c.id = ?
                               GROUP BY c.id");
        $stmt->execute([$course_id]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$course) {
            return ['success' => false, 'message' => 'Course not found'];
        }

        $unit_price = getGlobalUnitPrice($pdo);

        return [
            'success' => true,
            'course' => $course,
            'unit_price' => $unit_price,
            'total_price' => $course['total_units'] * $unit_price
        ];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Get user details
 */
function getUserDetails($pdo, $user_id)
{
    if (empty($user_id)) {
        return ['success' => false, 'message' => 'Invalid user ID'];
    }

    try {
        $stmt = $pdo->prepare("
            SELECT u.*, 
                   COALESCE(si.program, ei.role) as additional_info,
                   COALESCE(si.year_level, '') as year_level,
                   si.student_type,
                   si.enrollment_status,
                   si.enrollment_date,
                   si.number as contact_number,
                   si.address
            FROM users u
            LEFT JOIN students_info si ON u.user_id = si.user_id
            LEFT JOIN employee_info ei ON u.user_id = ei.user_id
            WHERE u.user_id = ?
        ");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }

        $additional_info = [];

        if ($user['role'] === 'student') {
            $stmt = $pdo->prepare("
                SELECT s.section_code, s.program, s.year_level 
                FROM student_sections ss 
                JOIN sections s ON ss.section_id = s.id 
                WHERE ss.student_id = ?
            ");
            $stmt->execute([$user_id]);
            $additional_info['sections'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $pdo->prepare("
                SELECT sub.subject_code, sub.subject_name, sub.units
                FROM student_subjects ss 
                JOIN subjects sub ON ss.subject_id = sub.id 
                WHERE ss.student_id = ?
            ");
            $stmt->execute([$user_id]);
            $additional_info['subjects'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_payments,
                    SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_count,
                    SUM(CASE WHEN payment_status = 'unpaid' THEN 1 ELSE 0 END) as unpaid_count,
                    SUM(CASE WHEN payment_status = 'partial' THEN 1 ELSE 0 END) as partial_count,
                    SUM(CASE WHEN payment_status = 'unpaid' THEN amount ELSE 0 END) as unpaid_amount,
                    SUM(CASE WHEN payment_status = 'partial' THEN remaining_balance ELSE 0 END) as partial_amount,
                    SUM(amount) as total_amount,
                    SUM(CASE WHEN payment_status = 'paid' THEN amount ELSE 0 END) as paid_amount
                FROM payments 
                WHERE student_id = ?
            ");
            $stmt->execute([$user_id]);
            $additional_info['payment_summary'] = $stmt->fetch(PDO::FETCH_ASSOC);

        } else {
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_issued,
                    SUM(amount) as total_amount_issued,
                    COUNT(DISTINCT student_id) as unique_students_served
                FROM payments 
                WHERE issued_by = ?
            ");
            $stmt->execute([$user_id]);
            $additional_info['issuance_summary'] = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        $stmt = $pdo->prepare("
            SELECT action, description, created_at 
            FROM activity_logs 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        $stmt->execute([$user_id]);
        $additional_info['activity_log'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'success' => true,
            'user' => $user,
            'additional_info' => $additional_info
        ];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Get sections for a subject
 */
function getSubjectSections($pdo, $subject_id)
{
    if ($subject_id <= 0) {
        return ['success' => false, 'message' => 'Invalid subject ID'];
    }

    try {
        $stmt = $pdo->prepare("
            SELECT s.id, s.section_code, s.section_name, s.program, s.year_level, s.semester
            FROM subject_sections ss
            JOIN sections s ON ss.section_id = s.id
            WHERE ss.subject_id = ?
            ORDER BY s.section_code
        ");
        $stmt->execute([$subject_id]);
        $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

        ob_start();
        if (empty($sections)) {
            echo '<div class="text-muted">No sections assigned</div>';
        } else {
            echo '<ul class="list-unstyled">';
            foreach ($sections as $section) {
                echo '<li class="mb-2">';
                echo '<span class="badge bg-info me-1">' . htmlspecialchars($section['section_code']) . '</span>';
                echo ' - ' . htmlspecialchars($section['program']) . ' (Year ' . $section['year_level'] . ')';
                echo '</li>';
            }
            echo '</ul>';
        }
        $html = ob_get_clean();

        return [
            'success' => true,
            'html' => $html,
            'sections' => $sections
        ];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Get subjects for a section
 */
function getSectionSubjects($pdo, $section_id)
{
    if ($section_id <= 0) {
        return ['success' => false, 'message' => 'Invalid section ID'];
    }

    try {
        $stmt = $pdo->prepare("
            SELECT s.*
            FROM subject_sections ss
            JOIN subjects s ON ss.subject_id = s.id
            WHERE ss.section_id = ?
            ORDER BY s.subject_code
        ");
        $stmt->execute([$section_id]);
        $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $unit_price = getGlobalUnitPrice($pdo);

        ob_start();
        if (empty($subjects)) {
            echo '<div class="text-muted">No subjects assigned</div>';
        } else {
            echo '<div class="table-responsive">';
            echo '<table class="table table-sm">';
            echo '<thead><tr><th>Code</th><th>Subject Name</th><th>Units</th><th>Price</th></tr></thead>';
            echo '<tbody>';
            foreach ($subjects as $subject) {
                $price = $subject['units'] * $unit_price;
                echo '<tr>';
                echo '<td><code>' . htmlspecialchars($subject['subject_code']) . '</code></td>';
                echo '<td>' . htmlspecialchars($subject['subject_name']) . '</td>';
                echo '<td>' . $subject['units'] . ' units</span></td>';
                echo '<td>₱' . number_format($price, 2) . '</td>';
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
            echo '</div>';
        }
        $html = ob_get_clean();

        return [
            'success' => true,
            'html' => $html,
            'subjects' => $subjects
        ];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Get section information
 */
function getSectionInfo($pdo, $section_id)
{
    if ($section_id <= 0) {
        return ['success' => false, 'message' => 'Invalid section ID'];
    }

    try {
        // Get section details with proper column names
        $stmt = $pdo->prepare("
            SELECT s.*, c.course_code, c.course_name
            FROM sections s
            LEFT JOIN courses c ON s.course_id = c.id
            WHERE s.id = ?
        ");
        $stmt->execute([$section_id]);
        $section = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$section) {
            return ['success' => false, 'message' => 'Section not found'];
        }

        // Get students in this section
        $stmt = $pdo->prepare("
            SELECT u.user_id, u.name, u.email, si.student_type
            FROM student_sections ss
            JOIN users u ON ss.student_id = u.user_id
            LEFT JOIN students_info si ON u.user_id = si.user_id
            WHERE ss.section_id = ?
            ORDER BY u.name
        ");
        $stmt->execute([$section_id]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get subjects in this section
        $stmt = $pdo->prepare("
            SELECT s.id, s.subject_code, s.subject_name, s.units
            FROM subject_sections ss
            JOIN subjects s ON ss.subject_id = s.id
            WHERE ss.section_id = ?
            ORDER BY s.subject_code
        ");
        $stmt->execute([$section_id]);
        $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $unit_price = getGlobalUnitPrice($pdo);

        // Build HTML with proper null checks
        $html = '
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">' . htmlspecialchars($section['section_code'] ?? $section['section_code'] ?? '-') . '</h5>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <table class="table table-sm">
                            <tr><th>Section Code:</th><td>' . htmlspecialchars($section['section_code'] ?? '-') . '</td></tr>
                            <tr><th>Section Name:</th><td>' . htmlspecialchars($section['section_name'] ?? '-') . '</td></tr>
                            <tr><th>Program:</th><td>' . htmlspecialchars($section['program'] ?? '-') . '</td></tr>';

        if (!empty($section['course_code'])) {
            $html .= '<tr><th>Course:</th><td>' . htmlspecialchars($section['course_code']) . ' - ' . htmlspecialchars($section['course_name'] ?? '') . '</td></tr>';
        }

        $html .= '<tr><th>Year Level:</th><td>Year ' . ($section['year_level'] ?? '-') . '</td></tr>
                            <tr><th>Semester:</th><td>' . ucfirst($section['semester'] ?? '1st') . ' Semester</td></tr>
                            <tr><th>Status:</th><td><span class="badge bg-' . (($section['status'] ?? 'active') === 'active' ? 'success' : 'secondary') . '">' . ucfirst($section['status'] ?? 'active') . '</span></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <div class="alert alert-info">
                            <strong>Summary:</strong><br>
                            Students: ' . count($students) . '<br>
                            Subjects: ' . count($subjects) . '
                        </div>
                    </div>
                </div>
                
                <h6>Students in this section (' . count($students) . '):</h6>';

        if (empty($students)) {
            $html .= '<p class="text-muted">No students assigned to this section.</p>';
        } else {
            $html .= '<div class="table-responsive" style="max-height: 200px; overflow-y: auto;">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr><th>Student ID</th><th>Name</th><th>Email</th><th>Type</th></tr>
                            </thead>
                            <tbody>';
            foreach ($students as $student) {
                $html .= '<tr>
                            <td>' . htmlspecialchars($student['user_id']) . '</td>
                            <td>' . htmlspecialchars($student['name']) . '</td>
                            <td>' . htmlspecialchars($student['email']) . '</td>
                            <td><span class="badge bg-' . (($student['student_type'] ?? 'regular') === 'regular' ? 'success' : 'warning') . '">' . ucfirst($student['student_type'] ?? 'regular') . '</span></td>
                        </tr>';
            }
            $html .= '</tbody></table></div>';
        }

        $html .= '<h6 class="mt-3">Subjects offered (' . count($subjects) . '):</h6>';

        if (empty($subjects)) {
            $html .= '<p class="text-muted">No subjects assigned to this section.</p>';
        } else {
            $html .= '<div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr><th>Subject Code</th><th>Subject Name</th><th>Units</th><th>Price</th></tr>
                            </thead>
                            <tbody>';
            foreach ($subjects as $subject) {
                $price = $subject['units'] * $unit_price;
                $html .= '<tr>
                            <td>' . htmlspecialchars($subject['subject_code']) . '</td>
                            <td>' . htmlspecialchars($subject['subject_name']) . '</td>
                            <td>' . $subject['units'] . ' units</td>
                            <td>₱' . number_format($price, 2) . '</td>
                        </tr>';
            }
            $html .= '</tbody></table></div>';
        }

        $html .= '</div></div>';

        return [
            'success' => true,
            'html' => $html,
            'section' => $section,
            'students' => $students,
            'subjects' => $subjects
        ];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Get subject details
 */
function getSubjectDetails($pdo, $subject_id)
{
    if ($subject_id <= 0) {
        return ['success' => false, 'message' => 'Invalid subject ID'];
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM subjects WHERE id = ?");
        $stmt->execute([$subject_id]);
        $subject = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$subject) {
            return ['success' => false, 'message' => 'Subject not found'];
        }

        $stmt = $pdo->prepare("
            SELECT c.id, c.course_code, c.course_name, c.duration_years, cc.year_level, cc.semester
            FROM course_curriculum cc
            JOIN courses c ON cc.course_id = c.id
            WHERE cc.subject_id = ?
            ORDER BY c.course_code, cc.year_level, cc.semester
        ");
        $stmt->execute([$subject_id]);
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("
            SELECT s.id, s.section_code, s.section_name, s.program, s.year_level, s.semester
            FROM subject_sections ss
            JOIN sections s ON ss.section_id = s.id
            WHERE ss.subject_id = ?
            ORDER BY s.section_code
        ");
        $stmt->execute([$subject_id]);
        $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT ss.student_id) as student_count
            FROM subject_sections subsec
            JOIN student_sections ss ON subsec.section_id = ss.section_id
            WHERE subsec.subject_id = ?
        ");
        $stmt->execute([$subject_id]);
        $student_count = $stmt->fetchColumn() ?: 0;

        $unit_price = getGlobalUnitPrice($pdo);
        $subject_price = $subject['units'] * $unit_price;

        ob_start();
        ?>
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h5 class="card-title"><?php echo htmlspecialchars($subject['subject_name']); ?></h5>
                                <h6 class="card-subtitle mb-3 text-muted"><?php echo htmlspecialchars($subject['subject_code']); ?></h6>
                                <table class="table table-sm table-borderless">
                                    <tr><th width="30%">Units:</th><td><?php echo $subject['units']; ?> units</td></tr>
                                    <tr><th>Price:</th><td>₱<?php echo number_format($subject_price, 2); ?></td></tr>
                                    <tr><th>Program:</th><td><?php echo htmlspecialchars($subject['program'] ?? 'General'); ?></td></tr>
                                    <tr><th>Year Level:</th><td><?php echo $subject['year_level'] ? 'Year ' . $subject['year_level'] : 'N/A'; ?></td></tr>
                                    <tr><th>Semester:</th><td><?php echo $subject['semester'] ?? 'N/A'; ?></td></tr>
                                    <tr><th>Total Students:</th><td><?php echo $student_count; ?></td></tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <div class="alert alert-info">
                                    <strong>Description:</strong><br>
                                    <?php echo nl2br(htmlspecialchars($subject['description'] ?? 'No description available.')); ?>
                                </div>
                            </div>
                        </div>
                
                        <hr>
                
                        <h6>Courses using this subject:</h6>
                        <?php if (empty($courses)): ?>
                                <p class="text-muted">Not assigned to any course curriculum.</p>
                        <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped">
                                        <thead>
                                            <tr><th>Course Code</th><th>Course Name</th><th>Year Level</th><th>Semester</th></tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($courses as $course): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($course['course_code']); ?></td>
                                                        <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                                        <td>Year <?php echo $course['year_level']; ?></td>
                                                        <td><?php echo ucfirst($course['semester']); ?> Semester</td>
                                                    </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                        <?php endif; ?>
                
                        <h6 class="mt-3">Sections offering this subject:</h6>
                        <?php if (empty($sections)): ?>
                                <p class="text-muted">Not assigned to any section.</p>
                        <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped">
                                        <thead>
                                            <tr><th>Section Code</th><th>Section Name</th><th>Program</th><th>Year Level</th><th>Semester</th></tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($sections as $section): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($section['section_code']); ?></td>
                                                        <td><?php echo htmlspecialchars($section['section_name'] ?? $section['section_code']); ?></td>
                                                        <td><?php echo htmlspecialchars($section['program']); ?></td>
                                                        <td>Year <?php echo $section['year_level']; ?></td>
                                                        <td><?php echo ucfirst($section['semester']); ?> Semester</td>
                                                    </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php
                $html = ob_get_clean();

                return [
                    'success' => true,
                    'html' => $html,
                    'subject' => $subject,
                    'courses' => $courses,
                    'sections' => $sections,
                    'student_count' => $student_count,
                    'total_price' => $subject_price
                ];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Get subject statistics
 */
function getSubjectStatistics($pdo, $subject_id)
{
    if ($subject_id <= 0) {
        return ['success' => false, 'message' => 'Invalid subject ID'];
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM subjects WHERE id = ?");
        $stmt->execute([$subject_id]);
        $subject = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$subject) {
            return ['success' => false, 'message' => 'Subject not found'];
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) as course_count FROM course_curriculum WHERE subject_id = ?");
        $stmt->execute([$subject_id]);
        $course_count = $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) as section_count FROM subject_sections WHERE subject_id = ?");
        $stmt->execute([$subject_id]);
        $section_count = $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) as student_count FROM student_subjects WHERE subject_id = ?");
        $stmt->execute([$subject_id]);
        $student_count = $stmt->fetchColumn();

        return [
            'success' => true,
            'subject' => $subject,
            'course_count' => $course_count,
            'section_count' => $section_count,
            'student_count' => $student_count
        ];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Get section statistics
 */
function getSectionStatistics($pdo, $section_id)
{
    if ($section_id <= 0) {
        return ['success' => false, 'message' => 'Invalid section ID'];
    }

    try {
        $stmt = $pdo->prepare("
            SELECT s.*, c.course_code, c.course_name
            FROM sections s
            LEFT JOIN courses c ON s.course_id = c.id
            WHERE s.id = ?
        ");
        $stmt->execute([$section_id]);
        $section = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$section) {
            return ['success' => false, 'message' => 'Section not found'];
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) as student_count FROM student_sections WHERE section_id = ?");
        $stmt->execute([$section_id]);
        $student_count = $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) as subject_count FROM subject_sections WHERE section_id = ?");
        $stmt->execute([$section_id]);
        $subject_count = $stmt->fetchColumn();

        return [
            'success' => true,
            'section' => $section,
            'student_count' => $student_count,
            'subject_count' => $subject_count
        ];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Assign subject to section
 */
function assignSubjectToSection($pdo, $subject_id, $section_id)
{
    if ($subject_id <= 0 || $section_id <= 0) {
        return ['success' => false, 'message' => 'Invalid subject or section ID'];
    }

    try {
        $stmt = $pdo->prepare("SELECT id FROM subject_sections WHERE subject_id = ? AND section_id = ?");
        $stmt->execute([$subject_id, $section_id]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Subject already assigned to this section'];
        }

        $stmt = $pdo->prepare("INSERT INTO subject_sections (subject_id, section_id) VALUES (?, ?)");
        $stmt->execute([$subject_id, $section_id]);

        logActivity(
            $_SESSION['user_id'],
            'Subject Assigned to Section',
            "Assigned subject ID: {$subject_id} to section ID: {$section_id}"
        );

        return ['success' => true, 'message' => 'Subject assigned to section successfully'];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Remove subject from section
 */
function removeSubjectFromSection($pdo, $subject_id, $section_id)
{
    if ($subject_id <= 0 || $section_id <= 0) {
        return ['success' => false, 'message' => 'Invalid subject or section ID'];
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM subject_sections WHERE subject_id = ? AND section_id = ?");
        $stmt->execute([$subject_id, $section_id]);

        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'message' => 'Assignment not found'];
        }

        logActivity(
            $_SESSION['user_id'],
            'Subject Removed from Section',
            "Removed subject ID: {$subject_id} from section ID: {$section_id}"
        );

        return ['success' => true, 'message' => 'Subject removed from section successfully'];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Get payment summary for a student
 */
function getPaymentSummary($pdo, $student_id)
{
    if (empty($student_id)) {
        return ['success' => false, 'message' => 'Invalid student ID'];
    }

    try {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_payments,
                SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_count,
                SUM(CASE WHEN payment_status = 'unpaid' THEN 1 ELSE 0 END) as unpaid_count,
                SUM(CASE WHEN payment_status = 'partial' THEN 1 ELSE 0 END) as partial_count,
                SUM(CASE WHEN payment_status = 'unpaid' THEN amount ELSE 0 END) as unpaid_amount,
                SUM(CASE WHEN payment_status = 'partial' THEN remaining_balance ELSE 0 END) as partial_amount,
                SUM(amount) as total_amount,
                SUM(CASE WHEN payment_status = 'paid' THEN amount ELSE 0 END) as paid_amount,
                MIN(issued_date) as first_payment_date,
                MAX(issued_date) as last_payment_date
            FROM payments 
            WHERE student_id = ?
        ");
        $stmt->execute([$student_id]);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'success' => true,
            'summary' => $summary
        ];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Calculate student balance
 */
function calculateStudentBalance($pdo, $student_id)
{
    if (empty($student_id)) {
        return ['success' => false, 'message' => 'Invalid student ID'];
    }

    try {
        $unit_price = getGlobalUnitPrice($pdo);

        $stmt = $pdo->prepare("
            SELECT SUM(c.total_units * ?) as total_due
            FROM student_course_enrollment sce
            JOIN courses c ON sce.course_id = c.id
            WHERE sce.student_id = ? AND sce.status = 'active'
        ");
        $stmt->execute([$unit_price, $student_id]);
        $total_due = $stmt->fetchColumn() ?: 0;

        $stmt = $pdo->prepare("
            SELECT SUM(amount) as total_paid 
            FROM payments 
            WHERE student_id = ? AND payment_status = 'paid'
        ");
        $stmt->execute([$student_id]);
        $total_paid = $stmt->fetchColumn() ?: 0;

        $stmt = $pdo->prepare("
            SELECT SUM(remaining_balance) as total_pending 
            FROM payments 
            WHERE student_id = ? AND payment_status IN ('unpaid', 'partial')
        ");
        $stmt->execute([$student_id]);
        $total_pending = $stmt->fetchColumn() ?: 0;

        $balance = $total_due - $total_paid;

        return [
            'success' => true,
            'total_due' => $total_due,
            'total_paid' => $total_paid,
            'total_pending' => $total_pending,
            'balance' => $balance,
            'percentage_paid' => $total_due > 0 ? round(($total_paid / $total_due) * 100, 2) : 0
        ];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Get student payments
 */
function getStudentPayments($pdo, $student_id, $limit = 10)
{
    if (empty($student_id)) {
        return ['success' => false, 'message' => 'Invalid student ID'];
    }

    try {
        $stmt = $pdo->prepare("
            SELECT p.*, u.name as issued_by_name
            FROM payments p
            LEFT JOIN users u ON p.issued_by = u.user_id
            WHERE p.student_id = ?
            ORDER BY p.issued_date DESC
            LIMIT ?
        ");
        $stmt->execute([$student_id, $limit]);
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        ob_start();
        if (empty($payments)) {
            echo '<div class="alert alert-info">No payment records found.</div>';
        } else {
            echo '<div class="table-responsive">';
            echo '<table class="table table-sm table-striped">';
            echo '<thead>
                    <tr>
                        <th>Permit #</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Issued Date</th>
                        <th>Issued By</th>
                    </tr>
                  </thead>';
            echo '<tbody>';
            foreach ($payments as $payment) {
                $status_class = $payment['payment_status'] === 'paid' ? 'success' :
                    ($payment['payment_status'] === 'unpaid' ? 'danger' : 'warning');
                echo '<tr>';
                echo '<td><code>' . htmlspecialchars($payment['permit_number']) . '</code></td>';
                echo '<td>₱' . number_format($payment['amount'], 2) . '</td>';
                echo '<td><span class="badge bg-' . $status_class . '">' .
                    ucfirst($payment['payment_status']) . '</span></td>';
                echo '<td>' . date('M j, Y', strtotime($payment['issued_date'])) . '</td>';
                echo '<td>' . htmlspecialchars($payment['issued_by_name']) . '</td>';
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
            echo '</div>';
        }

        $html = ob_get_clean();

        return [
            'success' => true,
            'html' => $html,
            'payments' => $payments
        ];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Get settings
 */
function getSettings($pdo, $category = '')
{
    try {
        if ($category) {
            $stmt = $pdo->prepare("SELECT * FROM settings WHERE category = ? ORDER BY name");
            $stmt->execute([$category]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM settings ORDER BY category, name");
            $stmt->execute();
        }

        $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $grouped = [];
        foreach ($settings as $setting) {
            $grouped[$setting['category']][] = $setting;
        }

        return [
            'success' => true,
            'settings' => $settings,
            'grouped' => $grouped
        ];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Update setting
 */
function updateSetting($pdo, $name, $value)
{
    if (empty($name)) {
        return ['success' => false, 'message' => 'Setting name required'];
    }

    try {
        $stmt = $pdo->prepare("SELECT id, is_editable FROM settings WHERE name = ?");
        $stmt->execute([$name]);
        $setting = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$setting) {
            return ['success' => false, 'message' => 'Setting not found'];
        }

        if (!$setting['is_editable']) {
            return ['success' => false, 'message' => 'This setting cannot be edited'];
        }

        $stmt = $pdo->prepare("
            UPDATE settings 
            SET value = ?, updated_at = NOW(), updated_by = ? 
            WHERE name = ?
        ");
        $stmt->execute([$value, $_SESSION['user_id'], $name]);

        logActivity($_SESSION['user_id'], 'Setting Updated', "Updated setting: {$name} to {$value}");

        return ['success' => true, 'message' => 'Setting updated successfully'];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Get course statistics
 */
function getCourseStatistics($pdo, $course_id)
{
    if ($course_id <= 0) {
        return ['success' => false, 'message' => 'Invalid course ID'];
    }

    try {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_students,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_students,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_students,
                COUNT(CASE WHEN status = 'dropped' THEN 1 END) as dropped_students,
                AVG(current_year_level) as avg_year_level
            FROM student_course_enrollment 
            WHERE course_id = ?
        ");
        $stmt->execute([$course_id]);
        $enrollment_stats = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("
            SELECT current_year_level, COUNT(*) as count
            FROM student_course_enrollment 
            WHERE course_id = ? AND status = 'active'
            GROUP BY current_year_level
            ORDER BY current_year_level
        ");
        $stmt->execute([$course_id]);
        $year_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("
            SELECT 
                AVG(p.amount) as avg_payment,
                SUM(CASE WHEN p.payment_status = 'paid' THEN p.amount ELSE 0 END) as total_paid,
                SUM(CASE WHEN p.payment_status IN ('unpaid', 'partial') THEN p.remaining_balance ELSE 0 END) as total_outstanding
            FROM student_course_enrollment sce
            LEFT JOIN payments p ON sce.student_id = p.student_id
            WHERE sce.course_id = ? AND sce.status = 'active'
        ");
        $stmt->execute([$course_id]);
        $payment_stats = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'success' => true,
            'enrollment_stats' => $enrollment_stats,
            'year_distribution' => $year_distribution,
            'payment_stats' => $payment_stats
        ];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Get enrollment statistics
 */
function getEnrollmentStats($pdo)
{
    try {
        $stmt = $pdo->prepare("
            SELECT 
                (SELECT COUNT(*) FROM users WHERE role = 'student') as total_students,
                (SELECT COUNT(*) FROM users WHERE role = 'admin') as total_admins,
                (SELECT COUNT(*) FROM users WHERE role = 'cashier') as total_cashiers,
                (SELECT COUNT(*) FROM users WHERE role = 'registrar') as total_registrars
        ");
        $stmt->execute();
        $user_stats = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("
            SELECT 
                c.course_code,
                c.course_name,
                COUNT(sce.id) as enrolled_students,
                COUNT(CASE WHEN sce.status = 'active' THEN 1 END) as active_students
            FROM courses c
            LEFT JOIN student_course_enrollment sce ON c.id = sce.course_id
            WHERE c.status = 'active'
            GROUP BY c.id
            ORDER BY enrolled_students DESC
        ");
        $stmt->execute();
        $course_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("
            SELECT 
                DATE_FORMAT(enrollment_date, '%Y-%m') as month,
                COUNT(*) as new_enrollments
            FROM students_info 
            WHERE enrollment_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(enrollment_date, '%Y-%m')
            ORDER BY month
        ");
        $stmt->execute();
        $monthly_trend = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'success' => true,
            'user_stats' => $user_stats,
            'course_stats' => $course_stats,
            'monthly_trend' => $monthly_trend
        ];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Get payment statistics
 */
function getPaymentStats($pdo, $start_date, $end_date)
{
    try {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_transactions,
                SUM(amount) as total_amount,
                SUM(CASE WHEN payment_status = 'paid' THEN amount ELSE 0 END) as total_paid,
                SUM(CASE WHEN payment_status IN ('unpaid', 'partial') THEN remaining_balance ELSE 0 END) as total_outstanding,
                AVG(amount) as avg_transaction_amount
            FROM payments 
            WHERE issued_date BETWEEN ? AND ?
        ");
        $stmt->execute([$start_date, $end_date]);
        $payment_stats = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("
            SELECT 
                payment_status,
                COUNT(*) as count,
                SUM(amount) as total_amount
            FROM payments 
            WHERE issued_date BETWEEN ? AND ?
            GROUP BY payment_status
        ");
        $stmt->execute([$start_date, $end_date]);
        $status_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("
            SELECT 
                issued_date,
                COUNT(*) as transaction_count,
                SUM(amount) as daily_total
            FROM payments 
            WHERE issued_date BETWEEN ? AND ?
            GROUP BY issued_date
            ORDER BY issued_date
        ");
        $stmt->execute([$start_date, $end_date]);
        $daily_trend = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("
            SELECT 
                p.issued_by,
                u.name,
                COUNT(p.id) as transaction_count,
                SUM(p.amount) as total_issued
            FROM payments p
            JOIN users u ON p.issued_by = u.user_id
            WHERE p.issued_date BETWEEN ? AND ?
            GROUP BY p.issued_by
            ORDER BY total_issued DESC
            LIMIT 10
        ");
        $stmt->execute([$start_date, $end_date]);
        $top_cashiers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'success' => true,
            'payment_stats' => $payment_stats,
            'status_distribution' => $status_distribution,
            'daily_trend' => $daily_trend,
            'top_cashiers' => $top_cashiers
        ];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Helper function to get global unit price
 */
function getGlobalUnitPrice($pdo)
{
    try {
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE name = 'unit_price'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? floatval($result['value']) : 1000.00;
    } catch (Exception $e) {
        return 1000.00;
    }
}



/**
 * Get assessment file for a student
 */
function getAssessmentFile($pdo, $student_id, $session_data)
{
    try {
        if (!isset($session_data['user_id'])) {
            return ['success' => false, 'message' => 'Unauthorized access. Please login.'];
        }

        $currentUserId = $session_data['user_id'];
        $currentUserRole = $session_data['role'] ?? '';

        if (empty($student_id)) {
            if ($currentUserRole === 'student') {
                $student_id = $currentUserId;
            } else {
                return ['success' => false, 'message' => 'Student ID is required.'];
            }
        }

        $hasPermission = false;
        $viewerType = 'other';

        if ($currentUserRole === 'admin' || $currentUserRole === 'cashier' || $currentUserRole === 'registrar') {
            $hasPermission = true;
            $viewerType = $currentUserRole;
        } elseif ($currentUserRole === 'student' && $currentUserId === $student_id) {
            $hasPermission = true;
            $viewerType = 'self';
        }

        if (!$hasPermission) {
            return ['success' => false, 'message' => 'Insufficient permissions to view this assessment.'];
        }

        $stmt = $pdo->prepare("
            SELECT si.*, u.email, u.name as full_name, u.user_id, u.created_at, u.user_status
            FROM students_info si 
            JOIN users u ON si.user_id = u.user_id 
            WHERE si.user_id = ? AND u.role = 'student'
        ");
        $stmt->execute([$student_id]);
        $studentInfo = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$studentInfo) {
            return ['success' => false, 'message' => 'Student not found.'];
        }

        $currentMonth = date('n');
        $currentYear = date('Y');

        if ($currentMonth >= 6 && $currentMonth <= 10) {
            $currentSemester = '2nd Semester';
            $schoolYear = $currentYear . '-' . ($currentYear + 1);
        } elseif ($currentMonth >= 11 || $currentMonth <= 3) {
            $currentSemester = '1st Semester';
            $schoolYear = ($currentYear - 1) . '-' . $currentYear;
        } else {
            $currentSemester = 'Summer Semester';
            $schoolYear = ($currentYear - 1) . '-' . $currentYear;
        }

        $stmt = $pdo->prepare("
            SELECT DISTINCT s.* 
            FROM student_subjects ss
            JOIN subjects s ON ss.subject_id = s.id
            WHERE ss.student_id = ?
            ORDER BY s.subject_code
        ");
        $stmt->execute([$student_id]);
        $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $unitPrice = 1000;
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE name = 'unit_price'");
        $stmt->execute();
        $price = $stmt->fetchColumn();

        if ($price) {
            $unitPrice = floatval($price);
        }

        $miscFees = 5000;
        $program = strtolower($studentInfo['program'] ?? '');

        if ($program) {
            $stmt = $pdo->prepare("SELECT value FROM settings WHERE name = ?");
            $miscFeeKeys = ['misc_fees_' . $program, 'misc_fees', 'default_misc_fees'];

            foreach ($miscFeeKeys as $key) {
                $stmt->execute([$key]);
                $fee = $stmt->fetchColumn();
                if ($fee) {
                    $miscFees = floatval($fee);
                    break;
                }
            }
        }

        $otherFees = ['laboratory' => 1000, 'library' => 500, 'athletic' => 300, 'medical' => 200, 'student_organization' => 150];

        $stmt = $pdo->prepare("SELECT name, value FROM settings WHERE name LIKE 'fee_%'");
        $stmt->execute();
        $feeSettings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        foreach ($otherFees as $key => $defaultValue) {
            $settingKey = 'fee_' . $key;
            if (isset($feeSettings[$settingKey])) {
                $otherFees[$key] = floatval($feeSettings[$settingKey]);
            }
        }

        $totalOtherFees = array_sum($otherFees);

        $stmt = $pdo->prepare("
            SELECT p.permit_number, p.amount, p.remaining_balance, DATE_FORMAT(p.issued_date, '%Y-%m-%d') as issued_date,
                   p.payment_status, p.description, u.name as issued_by_name
            FROM payments p
            LEFT JOIN users u ON p.issued_by = u.user_id
            WHERE p.student_id = ?
            ORDER BY p.issued_date DESC
            LIMIT 10
        ");
        $stmt->execute([$student_id]);
        $paymentHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) as total_paid 
            FROM payments 
            WHERE student_id = ? AND payment_status IN ('paid', 'partial')
        ");
        $stmt->execute([$student_id]);
        $paymentsMade = floatval($stmt->fetchColumn());

        $totalUnits = 0;
        $tuitionFee = 0;
        $subjectDetails = [];

        foreach ($subjects as $subject) {
            $subjectUnits = intval($subject['units']);
            $subjectTuition = $subjectUnits * $unitPrice;

            $totalUnits += $subjectUnits;
            $tuitionFee += $subjectTuition;

            $subjectDetails[] = [
                'code' => $subject['subject_code'],
                'name' => $subject['subject_name'],
                'units' => $subjectUnits,
                'unit_price' => $unitPrice,
                'total' => $subjectTuition,
                'description' => $subject['description'] ?? ''
            ];
        }

        $totalFees = $miscFees + $totalOtherFees;
        $totalAssessment = $tuitionFee + $totalFees;
        $remainingBalance = $totalAssessment - $paymentsMade;

        $assessment_info = [
            'student_id' => $studentInfo['user_id'],
            'student_name' => $studentInfo['full_name'],
            'student_email' => $studentInfo['email'],
            'program' => $studentInfo['program'],
            'year_level' => $studentInfo['year_level'],
            'student_type' => $studentInfo['student_type'],
            'enrollment_status' => $studentInfo['enrollment_status'],
            'student_status' => $studentInfo['user_status']
        ];

        $academic_info = [
            'current_semester' => $currentSemester,
            'school_year' => $schoolYear,
            'assessment_date' => date('Y-m-d H:i:s')
        ];

        $fees_breakdown = [
            'tuition' => ['total_units' => $totalUnits, 'unit_price' => $unitPrice, 'total' => $tuitionFee],
            'miscellaneous_fees' => ['amount' => $miscFees, 'description' => 'Miscellaneous Fees'],
            'other_fees' => $otherFees,
            'total_fees' => $totalFees
        ];

        $financial_summary = [
            'total_tuition' => $tuitionFee,
            'total_fees' => $totalFees,
            'total_assessment' => $totalAssessment,
            'payments_made' => $paymentsMade,
            'remaining_balance' => $remainingBalance,
            'payment_status' => $remainingBalance <= 0 ? 'fully_paid' : ($paymentsMade > 0 ? 'partially_paid' : 'unpaid')
        ];

        $viewer_info = [
            'viewer_id' => $currentUserId,
            'viewer_role' => $currentUserRole,
            'viewer_type' => $viewerType,
            'can_print' => true,
            'can_pay' => ($currentUserRole === 'cashier' || $currentUserRole === 'admin')
        ];

        return [
            'success' => true,
            'assessment_info' => $assessment_info,
            'academic_info' => $academic_info,
            'subjects' => $subjectDetails,
            'fees_breakdown' => $fees_breakdown,
            'financial_summary' => $financial_summary,
            'payment_history' => $paymentHistory,
            'installments' => [],
            'viewer_info' => $viewer_info
        ];



    } catch (Exception $e) {
        error_log("Assessment Error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }

}