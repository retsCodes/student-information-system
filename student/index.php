<?php
// Student directory index - Redirect unauthorized users
require_once '../init.php';

// Check if user is logged in and has student role
if (!checkSession() || $_SESSION['role'] !== 'student') {
    // User not logged in or not a student, redirect to main index
    redirect('/student\'s-information-system/index.php');
} else {
    // User is a student, redirect to student dashboard
    redirect('/student\'s-information-system/student/dashboard.php');
}
?>