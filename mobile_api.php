<?php
// Student Information System - Mobile API
// NO SESSIONS - Using token-based authentication

// Security headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include initialization - but we won't use sessions
require_once 'init.php';

// DO NOT start session for API
if (session_status() === PHP_SESSION_ACTIVE) {
    session_abort(); // Don't use sessions
}

$response = ['success' => false, 'message' => '', 'data' => null];

try {
    // Get request data
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    $action = $input['action'] ?? $_GET['action'] ?? '';
    
    if (empty($action)) {
        throw new Exception('Action parameter is required');
    }
    
    $pdo = getDBConnection();
    
    switch ($action) {
        case 'login':
            $user_id = sanitizeInput($input['user_id'] ?? '');
            $password = $input['password'] ?? '';
            
            if (empty($user_id) || empty($password)) {
                throw new Exception('User ID and password required');
            }
            
            // Check login attempts
            if (!checkLoginAttempts($user_id, $_SERVER['REMOTE_ADDR'])) {
                throw new Exception('Too many failed login attempts. Please try again later.');
            }
            
            // Get user from users table
            $stmt = $pdo->prepare("
                SELECT u.*, si.program, si.year_level, si.enrollment_status 
                FROM users u 
                LEFT JOIN students_info si ON u.user_id = si.user_id 
                WHERE u.user_id = ? 
                AND u.role = 'student' 
                AND u.user_status = 'active'
            ");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                recordLoginAttempt($user_id, $_SERVER['REMOTE_ADDR'], false);
                throw new Exception('Student not found or account inactive');
            }
            
            // Check password
            if (!verifyPassword($password, $user['password'])) {
                // For compatibility with plain text demo
                if ($password !== $user['password']) {
                    recordLoginAttempt($user_id, $_SERVER['REMOTE_ADDR'], false);
                    throw new Exception('Invalid password');
                }
            }
            
            // Create token - store in database for persistence
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', time() + (7 * 24 * 60 * 60)); // 7 days
            
            // Store token in database
            $stmt = $pdo->prepare("
                INSERT INTO mobile_tokens (user_id, token, expires_at) 
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    token = VALUES(token),
                    expires_at = VALUES(expires_at),
                    updated_at = NOW()
            ");
            $stmt->execute([$user_id, $token, $expiry]);
            
            // Get complete student info
            $stmt = $pdo->prepare("SELECT * FROM students_info WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $student_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Prepare user data
            $userData = [
                'user_id' => $user['user_id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role']
            ];
            
            if ($student_info) {
                $userData['program'] = $student_info['program'] ?? null;
                $userData['year_level'] = $student_info['year_level'] ?? null;
                $userData['enrollment_status'] = $student_info['enrollment_status'] ?? 'enrolled';
                $userData['contact_number'] = $student_info['number'] ?? null;
                $userData['address'] = $student_info['address'] ?? null;
                $userData['student_type'] = $student_info['student_type'] ?? 'regular';
                $userData['student_id'] = $student_info['user_id']; // Add student ID
            }
            
            $response['success'] = true;
            $response['message'] = 'Login successful';
            $response['data'] = [
                'token' => $token,
                'user' => $userData,
                'expires_at' => $expiry
            ];
            
            // Record successful login
            recordLoginAttempt($user_id, $_SERVER['REMOTE_ADDR'], true);
            logActivity($user_id, 'Mobile Login', 'Logged in via mobile app');
            break;
            
        case 'dashboard':
            $user_id = sanitizeInput($input['user_id'] ?? '');
            $token = sanitizeInput($input['token'] ?? '');
            
            if (empty($user_id) || empty($token)) {
                throw new Exception('Authentication required');
            }
            
            // Verify token from database
            $stmt = $pdo->prepare("
                SELECT * FROM mobile_tokens 
                WHERE user_id = ? 
                AND token = ? 
                AND expires_at > NOW()
            ");
            $stmt->execute([$user_id, $token]);
            $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$tokenData) {
                throw new Exception('Invalid or expired session. Please login again.');
            }
            
            // Refresh token expiry (optional)
            $new_expiry = date('Y-m-d H:i:s', time() + (7 * 24 * 60 * 60));
            $stmt = $pdo->prepare("UPDATE mobile_tokens SET expires_at = ? WHERE id = ?");
            $stmt->execute([$new_expiry, $tokenData['id']]);
            
            // Get student info from users and students_info
            $stmt = $pdo->prepare("
                SELECT u.*, si.* 
                FROM users u 
                LEFT JOIN students_info si ON u.user_id = si.user_id 
                WHERE u.user_id = ? 
                AND u.role = 'student' 
                AND u.user_status = 'active'
            ");
            $stmt->execute([$user_id]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$student) {
                throw new Exception('Student account not found');
            }
            
            $dashboardData = [];
            
            // 1. User/Student Information
            $dashboardData['user'] = [
                'user_id' => $student['user_id'],
                'name' => $student['name'],
                'email' => $student['email'],
                'role' => $student['role']
            ];
            
            $dashboardData['student_info'] = [
                'program' => $student['program'] ?? null,
                'year_level' => $student['year_level'] ?? null,
                'enrollment_status' => $student['enrollment_status'] ?? 'enrolled',
                'contact_number' => $student['number'] ?? null,
                'address' => $student['address'] ?? null,
                'student_type' => $student['student_type'] ?? 'regular',
                'enrollment_date' => $student['enrollment_date'] ?? null,
                'total_units' => $student['total_units'] ?? 0
            ];
            
            // 2. Payment Statistics
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_payments,
                    SUM(CASE WHEN payment_status = 'unpaid' THEN 1 ELSE 0 END) as unpaid_count,
                    SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_count,
                    SUM(CASE WHEN payment_status = 'partial' THEN 1 ELSE 0 END) as partial_count,
                    COALESCE(SUM(CASE WHEN payment_status = 'unpaid' THEN amount ELSE 0 END), 0) as total_due,
                    COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN amount ELSE 0 END), 0) as total_paid,
                    COALESCE(SUM(CASE WHEN payment_status = 'partial' THEN amount ELSE 0 END), 0) as total_partial
                FROM payments 
                WHERE student_id = ? 
                AND payment_status IN ('paid', 'unpaid', 'partial')
            ");
            $stmt->execute([$user_id]);
            $paymentStats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $dashboardData['payment_stats'] = $paymentStats ?: [
                'total_payments' => 0,
                'unpaid_count' => 0,
                'paid_count' => 0,
                'partial_count' => 0,
                'total_due' => 0,
                'total_paid' => 0,
                'total_partial' => 0
            ];
            
            // 3. Recent Payments (last 5)
            $stmt = $pdo->prepare("
                SELECT 
                    p.id,
                    p.permit_number,
                    p.amount,
                    p.description,
                    p.payment_status,
                    p.issued_date,
                    p.issued_by,
                    p.payment_category,
                    p.units,
                    p.remaining_balance,
                    u.name as issued_by_name
                FROM payments p
                LEFT JOIN users u ON p.issued_by = u.user_id
                WHERE p.student_id = ?
                ORDER BY p.issued_date DESC, p.id DESC
                LIMIT 5
            ");
            $stmt->execute([$user_id]);
            $recentPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $dashboardData['recent_payments'] = array_map(function($payment) {
                return [
                    'id' => $payment['id'],
                    'permit_number' => $payment['permit_number'],
                    'amount' => (float) $payment['amount'],
                    'description' => $payment['description'] ?? '',
                    'payment_status' => $payment['payment_status'] ?? 'unpaid',
                    'issued_date' => $payment['issued_date'],
                    'issued_by' => $payment['issued_by_name'] ?? $payment['issued_by'],
                    'payment_type' => $payment['payment_type'] ?? 'regular',
                    'payment_category' => $payment['payment_category'] ?? 'other',
                    'units' => $payment['units'] ?? 0,
                    'remaining_balance' => $payment['remaining_balance'] ?? 0
                ];
            }, $recentPayments);
            
            // 4. Current Subjects
            $stmt = $pdo->prepare("
                SELECT 
                    subject_code,
                    subject_name,
                    units,
                    description as subject_description,
                    program,
                    year_level
                FROM subjects 
                WHERE program = ?
                AND year_level = ?
                ORDER BY subject_code
                LIMIT 10
            ");
            $stmt->execute([
                $dashboardData['student_info']['program'], 
                $dashboardData['student_info']['year_level']
            ]);
            $currentSubjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $dashboardData['current_subjects'] = array_map(function($subject) {
                return [
                    'subject_code' => $subject['subject_code'],
                    'subject_name' => $subject['subject_name'],
                    'units' => (int) $subject['units'],
                    'subject_description' => $subject['subject_description'],
                    'section_code' => 'TBA',
                    'program' => $subject['program'] ?? '',
                    'schedule' => 'Schedule not available',
                    'room' => 'TBA'
                ];
            }, $currentSubjects);
            
            // 5. Academic Summary
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_subjects,
                    SUM(units) as total_units
                FROM subjects 
                WHERE program = ?
                AND year_level = ?
            ");
            $stmt->execute([
                $dashboardData['student_info']['program'], 
                $dashboardData['student_info']['year_level']
            ]);
            $academicSummary = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $dashboardData['academic_summary'] = $academicSummary ?: [
                'total_subjects' => 0,
                'total_units' => 0
            ];
            
            // 6. Current Balance Summary
            $stmt = $pdo->prepare("
                SELECT 
                    COALESCE(SUM(CASE WHEN payment_status = 'unpaid' THEN amount ELSE 0 END), 0) as total_unpaid,
                    COALESCE(SUM(CASE WHEN payment_status = 'partial' THEN remaining_balance ELSE 0 END), 0) as total_partial_paid
                FROM payments 
                WHERE student_id = ?
                AND payment_status IN ('unpaid', 'partial')
            ");
            $stmt->execute([$user_id]);
            $balanceSummary = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $dashboardData['balance_summary'] = $balanceSummary ?: [
                'total_unpaid' => 0,
                'total_partial_paid' => 0
            ];
            
            $response['success'] = true;
            $response['message'] = 'Dashboard data retrieved successfully';
            $response['data'] = $dashboardData;
            
            logActivity($user_id, 'Dashboard Access', 'Accessed dashboard via mobile app');
            break;
            
            // Add after the 'dashboard' case and before 'logout' case

case 'get_payments':
    $user_id = sanitizeInput($input['user_id'] ?? '');
    $token = sanitizeInput($input['token'] ?? '');
    
    if (empty($user_id) || empty($token)) {
        throw new Exception('Authentication required');
    }
    
    // Verify token
    $stmt = $pdo->prepare("
        SELECT * FROM mobile_tokens 
        WHERE user_id = ? AND token = ? AND expires_at > NOW()
    ");
    $stmt->execute([$user_id, $token]);
    $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tokenData) {
        throw new Exception('Invalid or expired session');
    }
    
    // Get all payments
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            u.name as issued_by_name
        FROM payments p
        LEFT JOIN users u ON p.issued_by = u.user_id
        WHERE p.student_id = ?
        ORDER BY p.issued_date DESC
    ");
    $stmt->execute([$user_id]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $response['success'] = true;
    $response['data'] = array_map(function($payment) {
        return [
            'id' => $payment['id'],
            'permit_number' => $payment['permit_number'],
            'amount' => (float) $payment['amount'],
            'description' => $payment['description'] ?? '',
            'payment_status' => $payment['payment_status'] ?? 'unpaid',
            'issued_date' => $payment['issued_date'],
            'due_date' => $payment['due_date'] ?? null,
            'receipt_number' => $payment['receipt_number'] ?? null,
            'remaining_balance' => (float) ($payment['remaining_balance'] ?? 0),
            'payment_type' => $payment['payment_type'] ?? 'regular',
            'payment_category' => $payment['payment_category'] ?? 'other',
            'units' => (int) ($payment['units'] ?? 0),
            'issued_by' => $payment['issued_by_name'] ?? $payment['issued_by']
        ];
    }, $payments);
    break;
    
case 'get_schedule':
    $user_id = sanitizeInput($input['user_id'] ?? '');
    $token = sanitizeInput($input['token'] ?? '');
    
    if (empty($user_id) || empty($token)) {
        throw new Exception('Authentication required');
    }
    
    // Verify token
    $stmt = $pdo->prepare("
        SELECT * FROM mobile_tokens 
        WHERE user_id = ? AND token = ? AND expires_at > NOW()
    ");
    $stmt->execute([$user_id, $token]);
    $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tokenData) {
        throw new Exception('Invalid or expired session');
    }
    
    // Get student info first
    $stmt = $pdo->prepare("
        SELECT program, year_level, section 
        FROM students_info 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        throw new Exception('Student record not found');
    }
    
    // Get subjects based on program and year level
    $stmt = $pdo->prepare("
        SELECT 
            subject_code,
            subject_name,
            units,
            description,
            program,
            year_level
        FROM subjects 
        WHERE program = ? AND year_level = ?
        ORDER BY subject_code
    ");
    $stmt->execute([$student['program'], $student['year_level']]);
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $response['success'] = true;
    $response['data'] = array_map(function($subject) use ($student) {
        return [
            'subject_code' => $subject['subject_code'],
            'subject_name' => $subject['subject_name'],
            'units' => (int) $subject['units'],
            'description' => $subject['description'] ?? '',
            'program' => $subject['program'],
            'year_level' => $subject['year_level'],
            'section_code' => $student['section'] ?? 'N/A'
        ];
    }, $subjects);
    break;
    
case 'get_profile':
    $user_id = sanitizeInput($input['user_id'] ?? '');
    $token = sanitizeInput($input['token'] ?? '');
    
    if (empty($user_id) || empty($token)) {
        throw new Exception('Authentication required');
    }
    
    // Verify token
    $stmt = $pdo->prepare("
        SELECT * FROM mobile_tokens 
        WHERE user_id = ? AND token = ? AND expires_at > NOW()
    ");
    $stmt->execute([$user_id, $token]);
    $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tokenData) {
        throw new Exception('Invalid or expired session');
    }
    
    // Get user profile
    $stmt = $pdo->prepare("
        SELECT u.*, si.* 
        FROM users u 
        LEFT JOIN students_info si ON u.user_id = si.user_id 
        WHERE u.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('User not found');
    }
    
    $response['success'] = true;
    $response['user'] = [
        'user_id' => $user['user_id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
        'program' => $user['program'] ?? null,
        'year_level' => $user['year_level'] ?? null,
        'enrollment_status' => $user['enrollment_status'] ?? 'enrolled',
        'contact_number' => $user['number'] ?? null,
        'address' => $user['address'] ?? null,
        'student_type' => $user['student_type'] ?? 'regular',
        'total_units' => (int) ($user['total_units'] ?? 0)
    ];
    break;
    
        case 'logout':
            $user_id = sanitizeInput($input['user_id'] ?? '');
            $token = sanitizeInput($input['token'] ?? '');
            
            if (!empty($user_id) && !empty($token)) {
                // Delete token from database
                $stmt = $pdo->prepare("DELETE FROM mobile_tokens WHERE user_id = ? AND token = ?");
                $stmt->execute([$user_id, $token]);
                
                logActivity($user_id, 'Mobile Logout', 'Logged out from mobile app');
            }
            
            $response['success'] = true;
            $response['message'] = 'Logout successful';
            break;
            
        case 'test':
            $response['success'] = true;
            $response['message'] = 'API is working';
            $response['data'] = [
                'version' => '1.0',
                'timestamp' => date('Y-m-d H:i:s')
            ];
            break;
            
        default:
            throw new Exception('Invalid action: ' . $action);
            // Add this case after the 'dashboard' case in your mobile_api.php

case 'get_academic_progress':
    $user_id = sanitizeInput($input['user_id'] ?? '');
    $token = sanitizeInput($input['token'] ?? '');
    
    if (empty($user_id) || empty($token)) {
        throw new Exception('Authentication required');
    }
    
    // Verify token
    $stmt = $pdo->prepare("
        SELECT * FROM mobile_tokens 
        WHERE user_id = ? AND token = ? AND expires_at > NOW()
    ");
    $stmt->execute([$user_id, $token]);
    $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tokenData) {
        throw new Exception('Invalid or expired session');
    }
    
    // Get student info
    $stmt = $pdo->prepare("
        SELECT si.*, u.name, u.email
        FROM students_info si 
        JOIN users u ON si.user_id = u.user_id 
        WHERE si.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $student_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $program = $student_info['program'] ?? '';
    $current_year_level = $student_info['year_level'] ?? 1;
    $is_irregular = ($student_info['student_type'] ?? 'regular') === 'irregular';
    
    // Find course by program
    $stmt = $pdo->prepare("SELECT id, course_code, course_name, total_units FROM courses LIMIT 1");
    $stmt->execute();
    $course = $stmt->fetch(PDO::FETCH_ASSOC);
    $course_id = $course['id'] ?? null;
    
    // Get assigned subjects
    $stmt = $pdo->prepare("
        SELECT DISTINCT s.*, sec.year_level as section_year, sec.semester as section_semester
        FROM student_sections ss
        JOIN sections sec ON ss.section_id = sec.id
        JOIN subject_sections subsec ON sec.id = subsec.section_id
        JOIN subjects s ON subsec.subject_id = s.id
        WHERE ss.student_id = ?
    ");
    $stmt->execute([$user_id]);
    $assigned_subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get curriculum subjects
    $curriculum_subjects = [];
    if ($course_id) {
        $stmt = $pdo->prepare("
            SELECT s.*, cc.year_level, cc.semester
            FROM course_curriculum cc
            JOIN subjects s ON cc.subject_id = s.id
            WHERE cc.course_id = ?
            ORDER BY cc.year_level, FIELD(cc.semester, '1st', '2nd'), s.subject_code
        ");
        $stmt->execute([$course_id]);
        $curriculum_subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get grades/completion records
    $stmt = $pdo->prepare("
        SELECT subject_id, grade, date_completed, status, year_level, semester
        FROM student_course_completion 
        WHERE student_id = ?
    ");
    $stmt->execute([$user_id]);
    $completions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $completion_map = [];
    foreach ($completions as $c) {
        $key = $c['subject_id'] . '_' . ($c['year_level'] ?? '') . '_' . ($c['semester'] ?? '');
        $completion_map[$key] = $c;
    }
    
    // Build curriculum data
    $curriculum_data = [];
    $overall_total_units = 0;
    $completed_count = 0;
    $in_progress_count = 0;
    $total_grades = 0;
    $grade_count = 0;
    
    foreach ($curriculum_subjects as $subject) {
        $year = $subject['year_level'];
        $semester = $subject['semester'];
        $key = $subject['id'] . '_' . $year . '_' . $semester;
        
        $is_taking = false;
        foreach ($assigned_subjects as $assigned) {
            if ($assigned['id'] == $subject['id']) {
                $is_taking = true;
                break;
            }
        }
        
        if (isset($completion_map[$key])) {
            $completion = $completion_map[$key];
            $grade = $completion['grade'];
            $status = $completion['status'];
            $is_completed = true;
            if ($grade && $grade <= 3.0) {
                $total_grades += $grade;
                $grade_count++;
            }
            $completed_count++;
        } else {
            $grade = null;
            $status = $is_taking ? 'current' : 'not_taken';
            $is_completed = false;
            if ($status == 'current') {
                $in_progress_count++;
            }
        }
        
        $overall_total_units += $subject['units'];
        
        $curriculum_data[$year][$semester][] = [
            'subject_code' => $subject['subject_code'],
            'subject_name' => $subject['subject_name'],
            'units' => $subject['units'],
            'grade' => $grade,
            'status' => $status,
            'is_taking' => $is_taking,
            'is_completed' => $is_completed,
            'date_received' => $completion['date_completed'] ?? null
        ];
    }
    
    // Build response structure
    $years_data = [];
    for ($y = 1; $y <= 4; $y++) {
        if (!isset($curriculum_data[$y])) continue;
        
        $year_status = $y < $current_year_level ? 'completed' : ($y == $current_year_level ? 'current' : 'upcoming');
        $semesters_data = [];
        
        foreach (['1st', '2nd'] as $sem) {
            if (isset($curriculum_data[$y][$sem]) && !empty($curriculum_data[$y][$sem])) {
                $semester_units = array_sum(array_column($curriculum_data[$y][$sem], 'units'));
                $semesters_data[] = [
                    'semester' => $sem,
                    'total_units' => $semester_units,
                    'subjects' => $curriculum_data[$y][$sem]
                ];
            }
        }
        
        if (!empty($semesters_data)) {
            $years_data[] = [
                'year' => $y,
                'status' => $year_status,
                'semesters' => $semesters_data
            ];
        }
    }
    
    $avg_grade = $grade_count > 0 ? round($total_grades / $grade_count, 2) : null;
    
    $response['success'] = true;
    $response['data'] = [
        'total_subjects' => count($curriculum_subjects),
        'completed_count' => $completed_count,
        'in_progress_count' => $in_progress_count,
        'avg_grade' => $avg_grade,
        'total_units' => $overall_total_units,
        'student_type' => $is_irregular ? 'irregular' : 'regular',
        'curriculum' => $years_data
    ];
    break;

case 'get_full_schedule':
    $user_id = sanitizeInput($input['user_id'] ?? '');
    $token = sanitizeInput($input['token'] ?? '');
    
    if (empty($user_id) || empty($token)) {
        throw new Exception('Authentication required');
    }
    
    // Verify token
    $stmt = $pdo->prepare("
        SELECT * FROM mobile_tokens 
        WHERE user_id = ? AND token = ? AND expires_at > NOW()
    ");
    $stmt->execute([$user_id, $token]);
    $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tokenData) {
        throw new Exception('Invalid or expired session');
    }
    
    // Get class schedule with times
    $stmt = $pdo->prepare("
        SELECT cs.*, s.subject_code, s.subject_name, sec.section_code
        FROM class_schedule cs
        JOIN subjects s ON cs.subject_id = s.id
        JOIN sections sec ON cs.section_id = sec.id
        WHERE cs.section_id IN (
            SELECT section_id FROM student_sections WHERE student_id = ?
        )
        ORDER BY FIELD(cs.day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), cs.start_time
    ");
    $stmt->execute([$user_id]);
    $schedule = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $response['success'] = true;
    $response['data'] = array_map(function($class) {
        return [
            'subject_code' => $class['subject_code'],
            'subject_name' => $class['subject_name'],
            'day_of_week' => $class['day_of_week'],
            'start_time' => date('g:i A', strtotime($class['start_time'])),
            'end_time' => date('g:i A', strtotime($class['end_time'])),
            'room' => $class['room'] ?? 'TBA',
            'section_code' => $class['section_code']
        ];
    }, $schedule);
    break;
    
    }
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>