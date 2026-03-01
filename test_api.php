<?php
// Student Information System - API Debug Test
// Place this in: student's-information-system/api_test.php

session_start();
header('Content-Type: application/json');

// Database connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=student_info_tracker", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    exit();
}

// Get action
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'test':
        echo json_encode([
            'success' => true,
            'message' => 'API is working!',
            'server_time' => date('Y-m-d H:i:s'),
            'session_id' => session_id()
        ]);
        break;
        
    case 'test_login':
        $user_id = $_POST['user_id'] ?? 'C26-02-9927-MAN121';
        $password = $_POST['password'] ?? 'rets123';
        
        // Check user
        $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ? AND role = 'student'");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Check password (plain text for demo)
            if ($password === $user['password']) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Login successful',
                    'user' => [
                        'user_id' => $user['user_id'],
                        'name' => $user['name'],
                        'email' => $user['email'],
                        'role' => $user['role']
                    ]
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Password incorrect'
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'User not found'
            ]);
        }
        break;
        
    case 'test_dashboard':
        $user_id = $_POST['user_id'] ?? 'C26-02-9927-MAN121';
        
        // Get student info
        $stmt = $pdo->prepare("SELECT * FROM students_info WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $student_info = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        
        echo json_encode([
            'success' => true,
            'message' => 'Dashboard test',
            'data' => [
                'student_info' => $student_info,
                'user' => [
                    'user_id' => $user_id,
                    'name' => 'Test Student'
                ]
            ]
        ]);
        break;
        
    default:
        echo json_encode([
            'success' => false,
            'message' => 'No action specified',
            'available_actions' => ['test', 'test_login', 'test_dashboard']
        ]);
}
?>