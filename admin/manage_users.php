<?php
require_once '../init.php';
require_once '../theme.php';
require_once '../layout.php';

requireRole('admin');

$pdo = getDBConnection();
$error = '';
$success = '';

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $action = $_POST['action'] ?? '';
        $user_id = $_POST['user_id'] ?? '';
        
        switch($action) {
            case 'toggle_status':
                if (!empty($user_id)) {
                    $stmt = $pdo->prepare("SELECT user_status FROM users WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    $current_status = $stmt->fetchColumn();
                    
                    $new_status = ($current_status === 'active') ? 'locked' : 'active';
                    
                    $stmt = $pdo->prepare("UPDATE users SET user_status = ? WHERE user_id = ?");
                    if ($stmt->execute([$new_status, $user_id])) {
                        logActivity($_SESSION['user_id'], 'User Status Change', 
                                   "Changed user {$user_id} status from {$current_status} to {$new_status}");
                        $success = "User status updated successfully.";
                    } else {
                        $error = "Failed to update user status.";
                    }
                }
                break;
                
            case 'add_user':
                $name = sanitizeInput($_POST['name'] ?? '');
                $email = sanitizeInput($_POST['email'] ?? '');
                $role = $_POST['role'] ?? '';
                $password = $_POST['password'] ?? '';
                
                // Student-only fields
                $program = sanitizeInput($_POST['program'] ?? '');
                $year_level = intval($_POST['year_level'] ?? 0);
                
                if (empty($name)) {
                    $error = 'Name is required.';
                } elseif (empty($email) || !validateEmail($email)) {
                    $error = 'Valid email is required.';
                } elseif (empty($role) || !in_array($role, ['admin', 'cashier', 'student', 'registrar'])) {
                    $error = 'Valid role is required.';
                } elseif (empty($password) || strlen($password) < 6) {
                    $error = 'Password must be at least 6 characters.';
                } else {
                    try {
                        // Generate user ID based on role
                        if ($role === 'student') {
                            $new_user_id = generateStudentID();
                        } else {
                            $prefix = strtoupper($role);
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = ?");
                            $stmt->execute([$role]);
                            $count = $stmt->fetchColumn() + 1;
                            $new_user_id = $prefix . str_pad($count, 3, '0', STR_PAD_LEFT);
                        }
                        
                        // Check if email already exists
                        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                        $stmt->execute([$email]);
                        if ($stmt->fetch()) {
                            $error = 'Email already exists.';
                        } else {
                            $hashed_password = hashPassword($password);
                            
                            // Insert user
                            $stmt = $pdo->prepare("INSERT INTO users (user_id, name, email, password, role) VALUES (?, ?, ?, ?, ?)");
                            $stmt->execute([$new_user_id, $name, $email, $hashed_password, $role]);
                            
                            // Insert additional info based on role
                            if ($role === 'student') {
                                // Save program and year_level (may be empty)
                                $stmt = $pdo->prepare("INSERT INTO students_info (user_id, name, email, program, year_level) VALUES (?, ?, ?, ?, ?)");
                                $stmt->execute([$new_user_id, $name, $email, $program, $year_level]);
                            } else {
                                $stmt = $pdo->prepare("INSERT INTO employee_info (user_id, name, email, role) VALUES (?, ?, ?, ?)");
                                $stmt->execute([$new_user_id, $name, $email, ucfirst($role)]);
                            }
                            
                            logActivity($_SESSION['user_id'], 'User Created', 
                                       "Created new {$role} user: {$new_user_id} - {$name}");
                            $success = "User created successfully. User ID: {$new_user_id}";
                            
                            // Clear the form after successful submission
                            echo '<script>document.addEventListener("DOMContentLoaded", function() { resetAddUserForm(); });</script>';
                        }
                    } catch(Exception $e) {
                        $error = "Failed to create user: " . $e->getMessage();
                    }
                }
                break;
                
            case 'assign_section':
                $student_id = sanitizeInput($_POST['student_id'] ?? '');
                $section_ids = $_POST['section_ids'] ?? [];
                $subject_ids = $_POST['subject_ids'] ?? [];
                
                if (empty($student_id) || empty($section_ids)) {
                    $error = 'Please select a student and at least one section.';
                } else {
                    try {
                        $pdo->beginTransaction();
                        
                        // Remove existing section mappings
                        $stmt = $pdo->prepare("DELETE FROM student_sections WHERE student_id = ?");
                        $stmt->execute([$student_id]);
                        
                        // Remove existing subject mappings
                        $stmt = $pdo->prepare("DELETE FROM student_subjects WHERE student_id = ?");
                        $stmt->execute([$student_id]);
                        
                        // Insert new section mappings
                        $stmt = $pdo->prepare("INSERT INTO student_sections (student_id, section_id) VALUES (?, ?)");
                        foreach ($section_ids as $section_id) {
                            $section_id = intval($section_id);
                            if ($section_id > 0) {
                                $stmt->execute([$student_id, $section_id]);
                            }
                        }
                        
                        // Insert selected subjects
                        if (!empty($subject_ids)) {
                            $stmt = $pdo->prepare("INSERT INTO student_subjects (student_id, subject_id) VALUES (?, ?)");
                            foreach ($subject_ids as $subject_id) {
                                $subject_id = intval($subject_id);
                                if ($subject_id > 0) {
                                    $stmt->execute([$student_id, $subject_id]);
                                }
                            }
                        }
                        
                        // Update student type (regular/irregular)
                        $student_type = count($section_ids) > 1 ? 'irregular' : 'regular';
                        $stmt = $pdo->prepare("UPDATE students_info SET student_type = ? WHERE user_id = ?");
                        $stmt->execute([$student_type, $student_id]);
                        
                        $pdo->commit();
                        
                        logActivity($_SESSION['user_id'], 'Assign Section', 
                                   "Assigned " . count($section_ids) . " sections and " . count($subject_ids) . " subjects to student {$student_id}");
                        $success = 'Sections and subjects assigned successfully. Student marked as ' . $student_type . '.';
                        
                        // Hide assign form after success
                        echo '<script>document.addEventListener("DOMContentLoaded", function() { cancelAssign(); });</script>';
                        
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $error = 'Failed to assign sections: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'edit_user':
                $edit_user_id = sanitizeInput($_POST['edit_user_id'] ?? '');
                $name = sanitizeInput($_POST['name'] ?? '');
                $email = sanitizeInput($_POST['email'] ?? '');
                
                if (empty($edit_user_id)) {
                    $error = 'Invalid user ID.';
                } elseif (empty($name)) {
                    $error = 'Name is required.';
                } elseif (empty($email) || !validateEmail($email)) {
                    $error = 'Valid email is required.';
                } else {
                    // Check if email is already used by another user
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND user_id != ?");
                    $stmt->execute([$email, $edit_user_id]);
                    if ($stmt->fetch()) {
                        $error = 'Email is already used by another user.';
                    } else {
                        try {
                            // Get old user info for logging
                            $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
                            $stmt->execute([$edit_user_id]);
                            $old_user = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            // Update users table
                            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE user_id = ?");
                            $stmt->execute([$name, $email, $edit_user_id]);
                            
                            // Update additional info based on role
                            if ($old_user['role'] === 'student') {
                                // If program/year were sent (optional), update them
                                $program = sanitizeInput($_POST['program'] ?? '');
                                $year_level = intval($_POST['year_level'] ?? 0);
                                $stmt = $pdo->prepare("UPDATE students_info SET name = ?, email = ?, program = ?, year_level = ? WHERE user_id = ?");
                                $stmt->execute([$name, $email, $program, $year_level, $edit_user_id]);
                            } else {
                                $stmt = $pdo->prepare("UPDATE employee_info SET name = ?, email = ? WHERE user_id = ?");
                                $stmt->execute([$name, $email, $edit_user_id]);
                            }
                            
                            logActivity($_SESSION['user_id'], 'User Updated', 
                                       "Updated user {$edit_user_id}: name from '{$old_user['name']}' to '{$name}', email from '{$old_user['email']}' to '{$email}'");
                            $success = 'User updated successfully.';
                            
                            // Hide edit form after successful update
                            echo '<script>document.addEventListener("DOMContentLoaded", function() { cancelEdit(); });</script>';
                        } catch(Exception $e) {
                            $error = 'Failed to update user: ' . $e->getMessage();
                        }
                    }
                }
                break;
                
            case 'delete_user':
                $delete_user_id = sanitizeInput($_POST['delete_user_id'] ?? '');
                
                if (empty($delete_user_id)) {
                    $error = 'Invalid user ID.';
                } elseif ($delete_user_id === $_SESSION['user_id']) {
                    $error = 'Cannot delete your own account.';
                } else {
                    // Get user info
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
                    $stmt->execute([$delete_user_id]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$user) {
                        $error = 'User not found.';
                    } else {
                        // Check if user has payments (for students) or issued payments (for cashiers/admins)
                        if ($user['role'] === 'student') {
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE student_id = ?");
                            $stmt->execute([$delete_user_id]);
                            $payment_count = $stmt->fetchColumn();
                            
                            if ($payment_count > 0) {
                                $error = 'Cannot delete student: Student has payment records.';
                            }
                        } else {
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE issued_by = ?");
                            $stmt->execute([$delete_user_id]);
                            $issued_count = $stmt->fetchColumn();
                            
                            if ($issued_count > 0) {
                                $error = 'Cannot delete user: User has issued payment records.';
                            }
                        }
                        
                        if (empty($error)) {
                            try {
                                // Delete user (CASCADE will handle related tables)
                                $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
                                $stmt->execute([$delete_user_id]);
                                
                                logActivity($_SESSION['user_id'], 'User Deleted', "Deleted user: {$delete_user_id} - {$user['name']} ({$user['role']})");
                                $success = 'User deleted successfully.';
                            } catch(Exception $e) {
                                $error = 'Failed to delete user: ' . $e->getMessage();
                            }
                        }
                    }
                }
                break;
                
            case 'reset_password':
                $reset_user_id = sanitizeInput($_POST['reset_user_id'] ?? '');
                $new_password = $_POST['new_password'] ?? '';
                
                if (empty($reset_user_id)) {
                    $error = 'Invalid user ID.';
                } elseif (empty($new_password) || strlen($new_password) < 6) {
                    $error = 'Password must be at least 6 characters.';
                } else {
                    try {
                        $hashed_password = hashPassword($new_password);
                        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                        $stmt->execute([$hashed_password, $reset_user_id]);
                        
                        logActivity($_SESSION['user_id'], 'Password Reset', "Admin reset password for user: {$reset_user_id}");
                        $success = 'Password reset successfully.';
                    } catch(Exception $e) {
                        $error = 'Failed to reset password: ' . $e->getMessage();
                    }
                }
                break;
        }
    }
}

// Get filters
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$where_conditions = [];
$params = [];

if (!empty($role_filter)) {
    $where_conditions[] = "u.role = ?";
    $params[] = $role_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "u.user_status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $where_conditions[] = "(u.name LIKE ? OR u.email LIKE ? OR u.user_id LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get users with additional info
$query = "SELECT u.*, 
                 COALESCE(si.program, ei.role) as additional_info,
                 COALESCE(si.year_level, '') as year_level,
                 si.student_type
          FROM users u
          LEFT JOIN students_info si ON u.user_id = si.user_id
          LEFT JOIN employee_info ei ON u.user_id = ei.user_id
          {$where_clause}
          ORDER BY u.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Load sections, subjects and current assignments
$sections = $pdo->query("
    SELECT id, section_code, program, year_level, 
           CONCAT(section_code, ' - ', program, ' (Year ', year_level, ')') AS section_label 
    FROM sections
    WHERE status = 'active'
")->fetchAll(PDO::FETCH_ASSOC);

$subjects = $pdo->query("SELECT id, subject_code, subject_name, units, description FROM subjects ORDER BY subject_code")->fetchAll(PDO::FETCH_ASSOC);

// Build section-subjects mapping
$sectionSubjectsMap = [];
$subjectDetailsMap = [];

foreach ($subjects as $subject) {
    $subjectDetailsMap[$subject['id']] = $subject;
    
    $stmt = $pdo->prepare("SELECT sections FROM subjects WHERE id = ?");
    $stmt->execute([$subject['id']]);
    $sections_json = $stmt->fetchColumn();
    
    if ($sections_json) {
        $section_codes = json_decode($sections_json, true) ?? [];
        foreach ($section_codes as $section_code) {
            foreach ($sections as $section_data) {
                if ($section_data['section_code'] === $section_code) {
                    $sectionSubjectsMap[$section_data['id']][] = $subject;
                    break;
                }
            }
        }
    }
}

// student -> section map (multiple sections per student)
$student_section_map = [];
$stmt = $pdo->query("SELECT student_id, section_id FROM student_sections");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $student_section_map[$r['student_id']][] = $r['section_id'];
}

// student -> [subject_id,...] map
$student_subjects_map = [];
$stmt = $pdo->query("SELECT student_id, subject_id FROM student_subjects");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $student_subjects_map[$r['student_id']][] = $r['subject_id'];
}

renderPageStart('Manage Users', 'admin', 'manage_users.php');
?>

<style>
.section-card {
    border: 2px solid #e9ecef;
    border-radius: 10px;
    transition: all 0.3s ease;
    margin-bottom: 20px;
}
.section-card:hover {
    border-color: #007bff;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.section-card.selected {
    border-color: #28a745;
    background-color: #f8fff9;
}
.section-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 15px 20px;
    border-radius: 8px 8px 0 0;
}
.subject-list {
    padding: 20px;
    max-height: 400px;
    overflow-y: auto;
}
.subject-item {
    border: 1px solid #e9ecef;
    border-radius: 5px;
    padding: 12px;
    margin-bottom: 10px;
    background: white;
    transition: all 0.3s ease;
}
.subject-item:hover {
    border-color: #007bff;
    background: #f8f9fa;
}
.subject-item.selected {
    border-color: #28a745;
    background: #f0fff4;
}
.student-info-card {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    color: white;
    border: none;
}
.checkbox-group {
    max-height: 300px;
    overflow-y: auto;
}
.assign-section-card {
    border-left: 4px solid #28a745;
}
.section-checkbox-item {
    border: 1px solid #dee2e6;
    border-radius: 5px;
    padding: 12px;
    margin-bottom: 10px;
    transition: all 0.3s ease;
}
.section-checkbox-item:hover {
    border-color: #007bff;
    background: #f8f9fa;
}
.section-checkbox-item.selected {
    border-color: #28a745;
    background: #f0fff4;
}
.btn-group .btn {
    margin-right: 2px;
}
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Manage Users</h2>
    <button class="btn btn-primary" onclick="toggleAddUserForm()">
        <i class="fas fa-plus"></i> Add User
    </button>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
    </div>
<?php endif; ?>

<!-- Add User Form (Hidden by default) -->
<div class="card mb-4" id="addUserCard" style="display: none;">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            <i class="fas fa-plus me-2"></i>Add New User
        </h5>
        <button type="button" class="btn-close" onclick="toggleAddUserForm()"></button>
    </div>
    <div class="card-body">
        <form method="POST" id="addUserForm">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="add_user">
            
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label for="name" class="form-label">Full Name</label>
                    <input type="text" class="form-control" id="name" name="name" required>
                </div>
                
                <div class="col-md-3 mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
                
                <div class="col-md-2 mb-3">
                    <label for="role_select" class="form-label">Role</label>
                    <select class="form-select" id="role_select" name="role" required>
                        <option value="">Select Role</option>
                        <option value="admin">Admin</option>
                        <option value="cashier">Cashier</option>
                        <option value="student">Student</option>
                        <option value="registrar">Registrar</option>
                    </select>
                </div>

                <div class="col-md-2 mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" 
                           minlength="6" required>
                    <div class="form-text">Min 6 chars</div>
                </div>

                <div class="col-md-2 mb-3">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add User
                        </button>
                    </div>
                </div>
            </div>

            <!-- Student-only fields (hidden unless role = student) -->
            <div id="studentFields" style="display:none;">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="program" class="form-label">Program</label>
                        <input type="text" class="form-control" id="program" name="program" placeholder="e.g., BSIT">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="year_level" class="form-label">Year Level</label>
                        <select class="form-select" id="year_level" name="year_level">
                            <option value="">Select Year Level</option>
                            <?php for ($i = 1; $i <= 6; $i++): ?>
                                <option value="<?php echo $i; ?>">Year <?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Form (Hidden by default) -->
<div class="card mb-4" id="editUserCard" style="display: none;">
    <div class="card-header bg-warning d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            <i class="fas fa-edit me-2"></i>Edit User
        </h5>
        <button type="button" class="btn-close" onclick="cancelEdit()"></button>
    </div>
    <div class="card-body">
        <form method="POST" id="editUserForm">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="edit_user">
            <input type="hidden" name="edit_user_id" id="edit_user_id">
            
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label for="edit_name" class="form-label">Full Name</label>
                    <input type="text" class="form-control" id="edit_name" name="name" required>
                </div>
                
                <div class="col-md-3 mb-3">
                    <label for="edit_email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="edit_email" name="email" required>
                </div>

                <!-- Student-only fields for editing -->
                <div id="editStudentFields" style="display:none;">
                    <div class="col-md-2 mb-3">
                        <label for="edit_program" class="form-label">Program</label>
                        <input type="text" class="form-control" id="edit_program" name="program">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label for="edit_year_level" class="form-label">Year Level</label>
                        <select class="form-select" id="edit_year_level" name="year_level">
                            <option value="">Select Year Level</option>
                            <?php for ($i = 1; $i <= 6; $i++): ?>
                                <option value="<?php echo $i; ?>">Year <?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <div class="col-md-2 mb-3">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-save"></i> Update User
                        </button>
                    </div>
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid">
                        <button type="button" class="btn btn-secondary" onclick="cancelEdit()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </div>
            </div>

            <!-- Reset Password section in Edit Form -->
            <div class="row mt-3">
                <div class="col-12">
                    <hr>
                    <h6>Reset Password</h6>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="edit_new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="edit_new_password" name="new_password" 
                                   minlength="6" placeholder="Leave blank to keep current">
                            <div class="form-text">Minimum 6 characters</div>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="button" class="btn btn-info" onclick="resetPasswordFromEdit()">
                                    <i class="fas fa-key"></i> Reset Password
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Assign Sections & Subjects Form (Hidden by default) -->
<div class="card mb-4 assign-section-card" id="assignSectionCard" style="display: none;">
    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            <i class="fas fa-layer-group me-2"></i>Assign Sections & Subjects
        </h5>
        <button type="button" class="btn-close btn-close-white" onclick="cancelAssign()"></button>
    </div>
    <div class="card-body">
        <form method="POST" id="assignSectionForm">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="assign_section">
            <input type="hidden" name="student_id" id="assign_section_student_id">
            
            <!-- Student Information -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card student-info-card">
                        <div class="card-body py-3">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h5 class="card-title mb-1" id="assign_student_name"></h5>
                                    <p class="card-text mb-0">
                                        <strong>Student ID:</strong> <span id="assign_student_id"></span> | 
                                        <strong>Current Status:</strong> <span id="assign_student_type"></span>
                                    </p>
                                </div>
                                <div class="col-md-4 text-end">
                                    <button type="button" class="btn btn-light" onclick="cancelAssign()">
                                        <i class="fas fa-times me-2"></i>Cancel
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Sections Selection -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-layer-group me-2"></i>Select Sections
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="checkbox-group" id="sectionsContainer">
                                <?php foreach($sections as $section): ?>
                                    <div class="section-checkbox-item">
                                        <div class="form-check">
                                            <input class="form-check-input section-checkbox" 
                                                   type="checkbox" 
                                                   name="section_ids[]" 
                                                   value="<?php echo $section['id']; ?>" 
                                                   id="section_<?php echo $section['id']; ?>"
                                                   data-section-id="<?php echo $section['id']; ?>">
                                            <label class="form-check-label fw-bold" for="section_<?php echo $section['id']; ?>">
                                                <?php echo htmlspecialchars($section['section_label']); ?>
                                            </label>
                                            <div class="text-muted small mt-1">
                                                Subjects: <?php echo count($sectionSubjectsMap[$section['id']] ?? []); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Subjects Selection -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-book me-2"></i>Select Subjects from Chosen Sections
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="selectAllSubjects">
                                    <label class="form-check-label fw-bold" for="selectAllSubjects">
                                        Select All Subjects from Selected Sections
                                    </label>
                                </div>
                            </div>

                            <div id="subjectsContainer">
                                <div class="text-center py-5 text-muted">
                                    <i class="fas fa-book-open fa-3x mb-3"></i>
                                    <h5>No Sections Selected</h5>
                                    <p>Please select sections from the left panel to view available subjects.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Summary and Actions -->
                    <div class="card mt-4">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Assignment Summary</h6>
                                    <div id="summaryInfo">
                                        <p class="text-muted">Select sections and subjects to see summary</p>
                                    </div>
                                </div>
                                <div class="col-md-6 text-end">
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-save me-2"></i>Save Assignments
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="role" class="form-label">Role</label>
                <select class="form-select" id="role" name="role">
                    <option value="">All Roles</option>
                    <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="cashier" <?php echo $role_filter === 'cashier' ? 'selected' : ''; ?>>Cashier</option>
                    <option value="student" <?php echo $role_filter === 'student' ? 'selected' : ''; ?>>Student</option>
                    <option value="student" <?php echo $role_filter === 'registrar' ? 'selected' : ''; ?>>Registrar</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="locked" <?php echo $status_filter === 'locked' ? 'selected' : ''; ?>>Locked</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Name, Email, or User ID">
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Filter
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Users Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Additional Info</th>
                        <th>Student Type</th>
                        <th>Status</th>
                        <th>Last Active</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($users as $user): 
                        // Prepare current assignment data for this student
                        $current_section_ids = $student_section_map[$user['user_id']] ?? [];
                        $current_subject_ids = $student_subjects_map[$user['user_id']] ?? [];
                        $student_type = $user['student_type'] ?? 'regular';
                    ?>
                    <tr>
                        <td><code><?php echo htmlspecialchars($user['user_id']); ?></code></td>
                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><span class="badge bg-info"><?php echo ucfirst($user['role']); ?></span></td>
                        <td>
                            <?php 
                            if ($user['role'] === 'student' && $user['additional_info']) {
                                echo htmlspecialchars($user['additional_info']);
                                if ($user['year_level']) {
                                    echo ' - Year ' . $user['year_level'];
                                }
                            } elseif ($user['additional_info']) {
                                echo htmlspecialchars($user['additional_info']);
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td>
                            <?php if ($user['role'] === 'student'): ?>
                                <span class="badge bg-<?php echo $student_type === 'regular' ? 'success' : 'warning'; ?>">
                                    <?php echo ucfirst($student_type); ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $user['user_status'] === 'active' ? 'success' : 'danger'; ?>">
                                <?php echo ucfirst($user['user_status']); ?>
                            </span>
                        </td>
                        <td>
                            <?php echo $user['last_active'] ? date('M j, Y g:i A', strtotime($user['last_active'])) : 'Never'; ?>
                        </td>
                        <td>
                            <div class="btn-group" role="group">
                                <!-- View Details -->
                                <a href="user_details.php?user_id=<?php echo $user['user_id']; ?>" 
                                   class="btn btn-sm btn-outline-info" 
                                   title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                
                                <!-- Edit User -->
                                <button class="btn btn-sm btn-outline-warning" 
                                        onclick="editUser('<?php echo htmlspecialchars($user['user_id'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($user['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($user['email'], ENT_QUOTES); ?>', '<?php echo $user['role']; ?>', '<?php echo htmlspecialchars($user['additional_info'] ?? '', ENT_QUOTES); ?>', '<?php echo $user['year_level']; ?>')"
                                        title="Edit User">
                                    <i class="fas fa-edit"></i>
                                </button>

                                <!-- Student-only buttons -->
                                <?php if ($user['role'] === 'student'): ?>
                                    <button class="btn btn-sm btn-outline-primary"
                                            title="Assign Sections & Subjects"
                                            onclick="openAssignSection('<?php echo htmlspecialchars($user['user_id'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($user['name'], ENT_QUOTES); ?>', '<?php echo $student_type; ?>', <?php echo json_encode($current_section_ids); ?>, <?php echo json_encode($current_subject_ids); ?>)">
                                        <i class="fas fa-layer-group"></i>
                                    </button>
                                <?php endif; ?>

                                <!-- Lock/Unlock User - No Popup -->
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-<?php echo $user['user_status'] === 'active' ? 'warning' : 'success'; ?>"
                                            title="<?php echo $user['user_status'] === 'active' ? 'Lock User' : 'Unlock User'; ?>"
                                            onclick="return confirm('Are you sure you want to <?php echo $user['user_status'] === 'active' ? 'lock' : 'unlock'; ?> user <?php echo htmlspecialchars($user['name']); ?>?')">
                                        <i class="fas fa-<?php echo $user['user_status'] === 'active' ? 'lock' : 'unlock'; ?>"></i>
                                    </button>
                                </form>

                                <?php if ($user['user_id'] !== $_SESSION['user_id']): ?>
                                <!-- Delete User - No Popup -->
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="delete_user_id" value="<?php echo $user['user_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" 
                                            title="Delete User"
                                            onclick="return confirm('Are you sure you want to delete user <?php echo htmlspecialchars($user['name']); ?>? This action cannot be undone.')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Preload data for section-subject mapping
const allSubjects = <?php echo json_encode($subjects); ?>;
const sectionSubjectsMap = <?php echo json_encode($sectionSubjectsMap); ?>;
const sectionDetailsMap = <?php echo json_encode($sections); ?>;
let currentSubjectSelections = [];
let currentSectionSelections = [];

// Toggle student fields in Add User form
document.addEventListener('DOMContentLoaded', function() {
    const roleSelect = document.getElementById('role_select');
    const studentFields = document.getElementById('studentFields');

    if (roleSelect) {
        roleSelect.addEventListener('change', function() {
            if (this.value === 'student') {
                studentFields.style.display = 'block';
            } else {
                studentFields.style.display = 'none';
            }
        });
    }
});

// Toggle Add User form visibility
function toggleAddUserForm() {
    const addUserCard = document.getElementById('addUserCard');
    if (addUserCard.style.display === 'none') {
        addUserCard.style.display = 'block';
        // Hide other forms
        document.getElementById('editUserCard').style.display = 'none';
        document.getElementById('assignSectionCard').style.display = 'none';
        // Scroll to add form
        addUserCard.scrollIntoView({ behavior: 'smooth' });
    } else {
        addUserCard.style.display = 'none';
    }
}

// Reset Add User form
function resetAddUserForm() {
    document.getElementById('addUserForm').reset();
    document.getElementById('studentFields').style.display = 'none';
    document.getElementById('addUserCard').style.display = 'none';
}

// Open Assign Section form
function openAssignSection(studentId, studentName, studentType, currentSectionIds, currentSubjectIds) {
    // Hide other forms
    document.getElementById('addUserCard').style.display = 'none';
    document.getElementById('editUserCard').style.display = 'none';
    
    // Show assign form
    const assignCard = document.getElementById('assignSectionCard');
    assignCard.style.display = 'block';
    
    // Set student info
    document.getElementById('assign_section_student_id').value = studentId;
    document.getElementById('assign_student_id').textContent = studentId;
    document.getElementById('assign_student_name').textContent = studentName;
    document.getElementById('assign_student_type').innerHTML = `<span class="badge bg-${studentType === 'regular' ? 'success' : 'warning'}">${studentType}</span>`;
    
    // Store current selections - convert to strings for comparison
    currentSubjectSelections = currentSubjectIds.map(id => id.toString());
    currentSectionSelections = currentSectionIds.map(id => id.toString());
    
    // Preselect current sections
    document.querySelectorAll('.section-checkbox').forEach(checkbox => {
        const sectionId = checkbox.value;
        checkbox.checked = currentSectionSelections.includes(sectionId);
        
        // Update visual state
        const sectionItem = checkbox.closest('.section-checkbox-item');
        if (sectionItem) {
            if (checkbox.checked) {
                sectionItem.classList.add('selected');
            } else {
                sectionItem.classList.remove('selected');
            }
        }
    });
    
    // Load subjects for current sections
    loadSubjectsForSections();
    
    // Scroll to assign form
    assignCard.scrollIntoView({ behavior: 'smooth' });
}

function loadSubjectsForSections() {
    const selectedSections = Array.from(document.querySelectorAll('.section-checkbox:checked')).map(cb => cb.value);
    const subjectsContainer = document.getElementById('subjectsContainer');

    // Update section visual states
    document.querySelectorAll('.section-checkbox-item').forEach(item => {
        const checkbox = item.querySelector('.section-checkbox');
        if (checkbox.checked) {
            item.classList.add('selected');
        } else {
            item.classList.remove('selected');
        }
    });

    if (selectedSections.length === 0) {
        subjectsContainer.innerHTML = `
            <div class="text-center py-5 text-muted">
                <i class="fas fa-book-open fa-3x mb-3"></i>
                <h5>No Sections Selected</h5>
                <p>Please select sections from the left panel to view available subjects.</p>
            </div>
        `;
        updateSummary();
        return;
    }

    // Build subjects grouped by section
    let html = '';
    let hasSubjects = false;

    selectedSections.forEach(sectionId => {
        const section = sectionDetailsMap.find(s => s.id == sectionId);
        const subjectsInSection = sectionSubjectsMap[sectionId] || [];
        
        if (subjectsInSection.length > 0) {
            hasSubjects = true;
            html += `
                <div class="section-card mb-4">
                    <div class="section-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">
                                <i class="fas fa-layer-group me-2"></i>
                                ${section.section_label}
                            </h6>
                            <div>
                                <button type="button" class="btn btn-sm btn-light select-all-section-btn" 
                                        data-section-id="${sectionId}">
                                    <i class="fas fa-check-double me-1"></i>Select All
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="subject-list">
                        <div class="row">
            `;
            
            subjectsInSection.forEach(subject => {
                const isChecked = currentSubjectSelections.includes(subject.id.toString()) ? 'checked' : '';
                html += `
                    <div class="col-md-6 mb-3">
                        <div class="subject-item ${isChecked ? 'selected' : ''}">
                            <div class="form-check">
                                <input class="form-check-input subject-checkbox" 
                                       type="checkbox" 
                                       name="subject_ids[]" 
                                       value="${subject.id}" 
                                       id="subject_${subject.id}_${sectionId}"
                                       data-section-id="${sectionId}"
                                       ${isChecked}>
                                <label class="form-check-label fw-bold" for="subject_${subject.id}_${sectionId}">
                                    ${subject.subject_code}
                                </label>
                                <div class="text-muted small">
                                    ${subject.subject_name}
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <span class="badge bg-primary">${subject.units} units</span>
                                    ${subject.description ? 
                                        `<button type="button" class="btn btn-sm btn-outline-info" 
                                                data-bs-toggle="tooltip" 
                                                title="${subject.description}">
                                            <i class="fas fa-info-circle"></i>
                                        </button>` : ''}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += `
                        </div>
                    </div>
                </div>
            `;
        }
    });

    if (!hasSubjects) {
        html = '<div class="alert alert-info">No subjects available for selected sections.</div>';
    }

    subjectsContainer.innerHTML = html;
    updateSummary();
    
    // Reattach event listeners
    reinitializeEventListeners();
}

function reinitializeEventListeners() {
    // Subject checkbox change events
    document.querySelectorAll('.subject-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            // Update visual state
            const subjectItem = this.closest('.subject-item');
            if (subjectItem) {
                if (this.checked) {
                    subjectItem.classList.add('selected');
                } else {
                    subjectItem.classList.remove('selected');
                }
            }
            updateSummary();
        });
    });

    // Select all section buttons
    document.querySelectorAll('.select-all-section-btn').forEach(button => {
        button.addEventListener('click', function() {
            const sectionId = this.dataset.sectionId;
            const sectionSubjects = document.querySelectorAll(`.subject-checkbox[data-section-id="${sectionId}"]`);
            const allChecked = Array.from(sectionSubjects).every(cb => cb.checked);
            
            sectionSubjects.forEach(cb => {
                cb.checked = !allChecked;
                // Update visual state
                const subjectItem = cb.closest('.subject-item');
                if (subjectItem) {
                    if (cb.checked) {
                        subjectItem.classList.add('selected');
                    } else {
                        subjectItem.classList.remove('selected');
                    }
                }
                cb.dispatchEvent(new Event('change'));
            });
        });
    });

    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

function updateSummary() {
    const selectedSections = Array.from(document.querySelectorAll('.section-checkbox:checked'));
    const selectedSubjects = Array.from(document.querySelectorAll('.subject-checkbox:checked'));
    
    const sectionsCount = selectedSections.length;
    const subjectsCount = selectedSubjects.length;
    
    let studentType = 'regular';
    if (sectionsCount > 1) {
        studentType = 'irregular';
    }
    
    let summaryHTML = '';
    if (sectionsCount === 0) {
        summaryHTML = '<p class="text-muted">Select sections and subjects to see summary</p>';
    } else {
        summaryHTML = `
            <div class="mb-2">
                <strong>Sections Selected:</strong> 
                <span class="badge bg-primary">${sectionsCount}</span>
            </div>
            <div class="mb-2">
                <strong>Subjects Selected:</strong> 
                <span class="badge bg-success">${subjectsCount}</span>
            </div>
            <div class="mb-2">
                <strong>Student Type:</strong> 
                <span class="badge bg-${studentType === 'regular' ? 'success' : 'warning'}">${studentType}</span>
            </div>
        `;
    }
    
    document.getElementById('summaryInfo').innerHTML = summaryHTML;
}

function cancelAssign() {
    document.getElementById('assignSectionCard').style.display = 'none';
    document.getElementById('assignSectionForm').reset();
    document.getElementById('subjectsContainer').innerHTML = `
        <div class="text-center py-5 text-muted">
            <i class="fas fa-book-open fa-3x mb-3"></i>
            <h5>No Sections Selected</h5>
            <p>Please select sections from the left panel to view available subjects.</p>
        </div>
    `;
    document.getElementById('summaryInfo').innerHTML = '<p class="text-muted">Select sections and subjects to see summary</p>';
}

function editUser(userId, name, email, role, program, yearLevel) {
    // Show edit form and hide other forms
    document.getElementById('editUserCard').style.display = 'block';
    document.getElementById('addUserCard').style.display = 'none';
    document.getElementById('assignSectionCard').style.display = 'none';
    
    // Populate edit form fields
    document.getElementById('edit_user_id').value = userId;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_email').value = email;
    
    // Handle student-specific fields
    const editStudentFields = document.getElementBzyId('editStudentFields');
    if (role === 'student') {
        editStudentFields.style.display = 'block';
        document.getElementById('edit_program').value = program || '';
        document.getElementById('edit_year_level').value = yearLevel || '';
    } else {
        editStudentFields.style.display = 'none';
    }
    
    // Clear password field
    document.getElementById('edit_new_password').value = '';
    
    // Scroll to edit form
    document.getElementById('editUserCard').scrollIntoView({ behavior: 'smooth' });
}

function cancelEdit() {
    document.getElementById('editUserCard').style.display = 'none';
    document.getElementById('edit_user_id').value = '';
    document.getElementById('edit_name').value = '';
    document.getElementById('edit_email').value = '';
    document.getElementById('edit_program').value = '';
    document.getElementById('edit_year_level').value = '';
    document.getElementById('editStudentFields').style.display = 'none';
    document.getElementById('edit_new_password').value = '';
}

function resetPasswordFromEdit() {
    const userId = document.getElementById('edit_user_id').value;
    const newPassword = document.getElementById('edit_new_password').value;
    
    if (!newPassword) {
        alert('Please enter a new password.');
        return;
    }
    
    if (newPassword.length < 6) {
        alert('Password must be at least 6 characters.');
        return;
    }
    
    if (confirm('Are you sure you want to reset the password for this user?')) {
        // Create a form and submit it
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="reset_user_id" value="${userId}">
            <input type="hidden" name="new_password" value="${newPassword}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Section selection event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Section checkbox change events
    document.querySelectorAll('.section-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            // Update visual state
            const sectionItem = this.closest('.section-checkbox-item');
            if (sectionItem) {
                if (this.checked) {
                    sectionItem.classList.add('selected');
                } else {
                    sectionItem.classList.remove('selected');
                }
            }
            loadSubjectsForSections();
        });
    });

    // Select all subjects
    document.getElementById('selectAllSubjects').addEventListener('change', function() {
        document.querySelectorAll('.subject-checkbox').forEach(checkbox => {
            checkbox.checked = this.checked;
            // Update visual state
            const subjectItem = checkbox.closest('.subject-item');
            if (subjectItem) {
                if (checkbox.checked) {
                    subjectItem.classList.add('selected');
                } else {
                    subjectItem.classList.remove('selected');
                }
            }
        });
        updateSummary();
    });
});
</script>

<?php renderPageEnd(); ?>