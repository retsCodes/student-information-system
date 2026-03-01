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
                    p.payment_type,
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
    }
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>