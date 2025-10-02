<?php
// Student Information System - Initialization File
session_start();

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'student_info_tracker');

// Security configurations
define('SESSION_TIMEOUT', 1800); // 30 minutes
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutes

// Database connection
function getDBConnection() {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch(PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

// CSRF Token generation and validation
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Session management
function checkSession() {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    // Check session timeout
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        session_destroy();
        return false;
    }
    
    $_SESSION['last_activity'] = time();
    return true;
}

function requireAuth() {
    if (!checkSession()) {
        header('Location: /student\'s-information-system/login.php');
        exit();
    }
}

function requireRole($required_role) {
    requireAuth();
    if ($_SESSION['role'] !== $required_role) {
        header('Location: /student\'s-information-system/unauthorized.php');
        exit();
    }
}

// Password hashing
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Login attempts management
function checkLoginAttempts($user_id, $ip_address) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT attempts, locked_until FROM login_attempts WHERE (user_id = ? OR ip_address = ?) AND locked_until > NOW()");
    $stmt->execute([$user_id, $ip_address]);
    $result = $stmt->fetch();
    
    if ($result && $result['attempts'] >= MAX_LOGIN_ATTEMPTS) {
        return false; // Account is locked
    }
    return true;
}

function recordLoginAttempt($user_id, $ip_address, $success = false) {
    $pdo = getDBConnection();
    
    if ($success) {
        // Clear login attempts on successful login
        $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE user_id = ? OR ip_address = ?");
        $stmt->execute([$user_id, $ip_address]);
    } else {
        // Record failed attempt
        $stmt = $pdo->prepare("SELECT id, attempts FROM login_attempts WHERE user_id = ? OR ip_address = ?");
        $stmt->execute([$user_id, $ip_address]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $new_attempts = $existing['attempts'] + 1;
            $locked_until = ($new_attempts >= MAX_LOGIN_ATTEMPTS) ? date('Y-m-d H:i:s', time() + LOCKOUT_TIME) : null;
            
            $stmt = $pdo->prepare("UPDATE login_attempts SET attempts = ?, last_attempt = NOW(), locked_until = ? WHERE id = ?");
            $stmt->execute([$new_attempts, $locked_until, $existing['id']]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO login_attempts (user_id, ip_address, attempts) VALUES (?, ?, 1)");
            $stmt->execute([$user_id, $ip_address]);
        }
    }
}

// Activity logging
function logActivity($user_id, $action, $description = '') {
    $pdo = getDBConnection();
    $log_id = 'LOG' . date('YmdHis') . rand(100, 999);
    
    $stmt = $pdo->prepare("INSERT INTO activity_logs (log_id, user_id, action, description) VALUES (?, ?, ?, ?)");
    $stmt->execute([$log_id, $user_id, $action, $description]);
}

// Utility functions
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function generateStudentID() {
    $year = date('y');
    $month = date('m');
    $random = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
    return "C{$year}-{$month}-{$random}-MAN121";
}

function generatePermitNumber() {
    do {
        $permit = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT id FROM payments WHERE permit_number = ?");
        $stmt->execute([$permit]);
    } while ($stmt->fetch());
    
    return $permit;
}

function numberToWords($number) {
    $words = array(
        0 => 'zero', 1 => 'one', 2 => 'two', 3 => 'three', 4 => 'four', 5 => 'five',
        6 => 'six', 7 => 'seven', 8 => 'eight', 9 => 'nine', 10 => 'ten',
        11 => 'eleven', 12 => 'twelve', 13 => 'thirteen', 14 => 'fourteen', 15 => 'fifteen',
        16 => 'sixteen', 17 => 'seventeen', 18 => 'eighteen', 19 => 'nineteen', 20 => 'twenty',
        30 => 'thirty', 40 => 'forty', 50 => 'fifty', 60 => 'sixty', 70 => 'seventy',
        80 => 'eighty', 90 => 'ninety'
    );
    
    if ($number < 21) {
        return $words[$number];
    } elseif ($number < 100) {
        $tens = intval($number / 10) * 10;
        $units = $number % 10;
        return $words[$tens] . ($units ? ' ' . $words[$units] : '');
    } elseif ($number < 1000) {
        $hundreds = intval($number / 100);
        $remainder = $number % 100;
        return $words[$hundreds] . ' hundred' . ($remainder ? ' ' . numberToWords($remainder) : '');
    } elseif ($number < 1000000) {
        $thousands = intval($number / 1000);
        $remainder = $number % 1000;
        return numberToWords($thousands) . ' thousand' . ($remainder ? ' ' . numberToWords($remainder) : '');
    }
    
    return 'Number too large';
}

// Get user info
function getUserInfo($user_id) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getStudentInfo($user_id) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM students_info WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getEmployeeInfo($user_id) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM employee_info WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Update last active
function updateLastActive($user_id) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("UPDATE users SET last_active = NOW() WHERE user_id = ?");
    $stmt->execute([$user_id]);
}

// Error handling
function showError($message) {
    echo "<div class='alert alert-danger'>" . sanitizeInput($message) . "</div>";
}

function showSuccess($message) {
    echo "<div class='alert alert-success'>" . sanitizeInput($message) . "</div>";
}

// Redirect function
function redirect($url) {
    header("Location: $url");
    exit();
}
?>
