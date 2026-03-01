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

// Pagination settings
$per_page = 20;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $per_page;

// Build query for total count
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

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total 
                FROM users u
                LEFT JOIN students_info si ON u.user_id = si.user_id
                LEFT JOIN employee_info ei ON u.user_id = ei.user_id
                {$where_clause}";

$stmt = $pdo->prepare($count_query);
$stmt->execute($params);
$total_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_count / $per_page);

// Get users with additional info (with pagination)
$query = "SELECT u.*, 
                 COALESCE(si.program, ei.role) as additional_info,
                 COALESCE(si.year_level, '') as year_level,
                 si.student_type
          FROM users u
          LEFT JOIN students_info si ON u.user_id = si.user_id
          LEFT JOIN employee_info ei ON u.user_id = ei.user_id
          {$where_clause}
          ORDER BY u.created_at DESC
          LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($query);

// Bind pagination parameters
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

// Bind filter parameters
$param_index = 1;
foreach ($params as $param) {
    $stmt->bindValue($param_index++, $param);
}

$stmt->execute();
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

// Calculate starting number for pagination
$start_number = $offset + 1;

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
/* Modal specific styles */
.modal-subject-item {
    border: 1px solid #dee2e6;
    border-radius: 5px;
    padding: 10px;
    margin-bottom: 8px;
    transition: all 0.2s ease;
}
.modal-subject-item:hover {
    background-color: #f8f9fa;
}
.modal-subject-item.selected {
    border-color: #28a745;
    background-color: #f0fff4;
}
/* User details modal styles */
.user-details-modal .modal-xl {
    max-width: 1200px;
}
.user-details-card {
    border: none;
    border-radius: 10px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    transition: transform 0.2s;
}
.user-details-card:hover {
    transform: translateY(-2px);
}
.user-details-header {
    border-radius: 10px 10px 0 0;
    padding: 1.5rem;
}
.user-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    font-weight: bold;
    margin: 0 auto;
}
.details-table th {
    width: 30%;
    font-weight: 600;
}
.activity-log-item {
    border-left: 3px solid #007bff;
    padding-left: 15px;
    margin-bottom: 10px;
}
.activity-log-item:hover {
    background-color: #f8f9fa;
}
/* Pagination styles */
.pagination {
    margin-bottom: 0;
}
.page-item.active .page-link {
    background-color: #0d6efd;
    border-color: #0d6efd;
}
/* Table numbering column */
.table th:first-child, .table td:first-child {
    text-align: center;
    width: 60px;
    font-weight: 600;
}
/* Assessment modal styles */
#assessmentTable th {
    background-color: #f8f9fa;
    font-weight: 600;
}
#assessmentTable tfoot {
    background-color: #f8f9fa;
}
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Manage Users</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
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
                    <option value="registrar" <?php echo $role_filter === 'registrar' ? 'selected' : ''; ?>>Registrar</option>
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
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">User List</h5>
            <span class="text-muted">
                Showing <?php echo $start_number; ?>-<?php echo min($start_number + count($users) - 1, $total_count); ?> 
                of <?php echo $total_count; ?> users
                (Page <?php echo $page; ?> of <?php echo $total_pages; ?>)
            </span>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>No.</th>
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
                    <?php 
                    $counter = $start_number;
                    foreach($users as $user): 
                        // Prepare current assignment data for this student
                        $current_section_ids = $student_section_map[$user['user_id']] ?? [];
                        $current_subject_ids = $student_subjects_map[$user['user_id']] ?? [];
                        $student_type = $user['student_type'] ?? 'regular';
                    ?>
                    <tr>
                        <td><?php echo $counter++; ?></td>
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
                                <!-- View Details Button -->
                                <button class="btn btn-sm btn-outline-info" 
                                        onclick="viewUserDetails('<?php echo htmlspecialchars($user['user_id'], ENT_QUOTES); ?>')"
                                        title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                
                                <!-- Edit User -->
                                <button class="btn btn-sm btn-outline-warning" 
                                        onclick="openEditModal('<?php echo htmlspecialchars($user['user_id'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($user['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($user['email'], ENT_QUOTES); ?>', '<?php echo $user['role']; ?>', '<?php echo htmlspecialchars($user['additional_info'] ?? '', ENT_QUOTES); ?>', '<?php echo $user['year_level']; ?>')"
                                        title="Edit User">
                                    <i class="fas fa-edit"></i>
                                </button>

                                <!-- Assessment Button (Student only) -->
                                <?php if ($user['role'] === 'student'): ?>
                                    <button class="btn btn-sm btn-outline-info" 
                                            title="View Assessment"
                                            onclick="viewAssessment('<?php echo htmlspecialchars($user['user_id'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($user['name'], ENT_QUOTES); ?>')">
                                        <i class="fas fa-file-invoice-dollar"></i>
                                    </button>
                                <?php endif; ?>

                                <!-- Student-only buttons -->
                                <?php if ($user['role'] === 'student'): ?>
                                    <button class="btn btn-sm btn-outline-primary"
                                            title="Assign Sections & Subjects"
                                            data-bs-toggle="modal"
                                            data-bs-target="#assignModal"
                                            onclick="prepareAssignModal('<?php echo htmlspecialchars($user['user_id'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($user['name'], ENT_QUOTES); ?>', '<?php echo $student_type; ?>', <?php echo json_encode($current_section_ids); ?>, <?php echo json_encode($current_subject_ids); ?>)">
                                        <i class="fas fa-layer-group"></i>
                                    </button>
                                <?php endif; ?>

                                <!-- Lock/Unlock User -->
                                <button class="btn btn-sm btn-<?php echo $user['user_status'] === 'active' ? 'warning' : 'success'; ?>"
                                        onclick="toggleStatus('<?php echo htmlspecialchars($user['user_id'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($user['name'], ENT_QUOTES); ?>', '<?php echo $user['user_status']; ?>')"
                                        title="<?php echo $user['user_status'] === 'active' ? 'Lock User' : 'Unlock User'; ?>">
                                    <i class="fas fa-<?php echo $user['user_status'] === 'active' ? 'lock' : 'unlock'; ?>"></i>
                                </button>

                                <?php if ($user['user_id'] !== $_SESSION['user_id']): ?>
                                <!-- Delete User -->
                                <button class="btn btn-sm btn-outline-danger" 
                                        title="Delete User"
                                        onclick="deleteUser('<?php echo htmlspecialchars($user['user_id'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($user['name'], ENT_QUOTES); ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="10" class="text-center py-4">
                            <i class="fas fa-users fa-2x text-muted mb-3"></i>
                            <h5>No users found</h5>
                            <p class="text-muted">Try adjusting your filters or add a new user.</p>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center">
                <!-- Previous Page -->
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" 
                       href="?<?php 
                            $query_params = $_GET;
                            $query_params['page'] = $page - 1;
                            echo http_build_query($query_params);
                       ?>" 
                       aria-label="Previous">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
                
                <!-- Page Numbers -->
                <?php 
                // Show page numbers
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                for ($i = $start_page; $i <= $end_page; $i++): 
                ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                        <a class="page-link" 
                           href="?<?php 
                                $query_params = $_GET;
                                $query_params['page'] = $i;
                                echo http_build_query($query_params);
                           ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                <?php endfor; ?>
                
                <!-- Next Page -->
                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                    <a class="page-link" 
                       href="?<?php 
                            $query_params = $_GET;
                            $query_params['page'] = $page + 1;
                            echo http_build_query($query_params);
                       ?>" 
                       aria-label="Next">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<!-- Assessment View Modal -->
<div class="modal fade" id="assessmentModal" tabindex="-1" aria-labelledby="assessmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="assessmentModalLabel">
                    <i class="fas fa-file-invoice-dollar me-2"></i>Student Assessment
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Student Information -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="card-title mb-0">Student Information</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <p class="mb-1"><strong>Student ID:</strong> <span id="assessment_student_id"></span></p>
                                    </div>
                                    <div class="col-md-3">
                                        <p class="mb-1"><strong>Name:</strong> <span id="assessment_student_name"></span></p>
                                    </div>
                                    <div class="col-md-3">
                                        <p class="mb-1"><strong>Program:</strong> <span id="assessment_program"></span></p>
                                    </div>
                                    <div class="col-md-3">
                                        <p class="mb-1"><strong>Year Level:</strong> <span id="assessment_year_level"></span></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Assessment Details -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                <h6 class="card-title mb-0">Assessment Details</h6>
                                <div>
                                    <span class="badge bg-primary me-2" id="assessment_semester"></span>
                                    <span class="badge bg-secondary" id="assessment_school_year"></span>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover mb-0" id="assessmentTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Subject Code</th>
                                                <th>Subject Name</th>
                                                <th class="text-center">Units</th>
                                                <th class="text-end">Amount per Unit</th>
                                                <th class="text-end">Subject Total</th>
                                            </tr>
                                        </thead>
                                        <tbody id="assessmentTableBody">
                                            <!-- Subjects will be loaded here -->
                                        </tbody>
                                        <tfoot id="assessmentTableFooter">
                                            <!-- Summary will be loaded here -->
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Financial Summary -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="card-title mb-0">Financial Summary</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-6">
                                        <p class="mb-1"><strong>Total Units:</strong></p>
                                    </div>
                                    <div class="col-6 text-end">
                                        <p class="mb-1" id="total_units">0</p>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-6">
                                        <p class="mb-1"><strong>Tuition Fee:</strong></p>
                                    </div>
                                    <div class="col-6 text-end">
                                        <p class="mb-1" id="tuition_fee">₱0.00</p>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-6">
                                        <p class="mb-1"><strong>Miscellaneous Fees:</strong></p>
                                    </div>
                                    <div class="col-6 text-end">
                                        <p class="mb-1" id="misc_fees">₱0.00</p>
                                    </div>
                                </div>
                                <hr>
                                <div class="row">
                                    <div class="col-6">
                                        <p class="mb-1"><strong>Total Assessment:</strong></p>
                                    </div>
                                    <div class="col-6 text-end">
                                        <p class="mb-1 fw-bold" id="total_assessment">₱0.00</p>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-6">
                                        <p class="mb-1"><strong>Payments Made:</strong></p>
                                    </div>
                                    <div class="col-6 text-end">
                                        <p class="mb-1 text-success" id="payments_made">₱0.00</p>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-6">
                                        <p class="mb-1"><strong>Remaining Balance:</strong></p>
                                    </div>
                                    <div class="col-6 text-end">
                                        <p class="mb-1 fw-bold text-danger" id="remaining_balance">₱0.00</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="card-title mb-0">Payment History</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive" style="max-height: 200px; overflow-y: auto;">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Permit #</th>
                                                <th class="text-end">Amount</th>
                                                <th class="text-end">Balance</th>
                                            </tr>
                                        </thead>
                                        <tbody id="paymentHistoryBody">
                                            <!-- Payment history will be loaded here -->
                                        </tbody>
                                    </table>
                                </div>
                                <div class="mt-3 text-center" id="noPaymentHistory" style="display: none;">
                                    <p class="text-muted mb-0">No payment history found</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="printAssessment()">
                    <i class="fas fa-print me-2"></i>Print Assessment
                </button>
            </div>
        </div>
    </div>
</div>

<!-- User Details Modal -->
<div class="modal fade user-details-modal" id="userDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-user me-2"></i><span id="modalUserName"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="userDetailsContent">
                    <!-- Content will be loaded via AJAX -->
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3">Loading user details...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="addUserForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="add_user">
                
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="addUserModalLabel">
                        <i class="fas fa-plus me-2"></i>Add New User
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="role_select" class="form-label">Role</label>
                            <select class="form-select" id="role_select" name="role" required onchange="toggleStudentFields()">
                                <option value="">Select Role</option>
                                <option value="admin">Admin</option>
                                <option value="cashier">Cashier</option>
                                <option value="student">Student</option>
                                <option value="registrar">Registrar</option>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" 
                                   minlength="6" required>
                            <div class="form-text">Min 6 chars</div>
                        </div>

                        <!-- Student-only fields (hidden unless role = student) -->
                        <div id="studentFields" style="display:none;">
                            <div class="col-md-6 mb-3">
                                <label for="program" class="form-label">Program</label>
                                <input type="text" class="form-control" id="program" name="program" placeholder="e.g., BSIT">
                            </div>
                            <div class="col-md-6 mb-3">
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
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="editUserForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="edit_user_id" id="edit_user_id">
                
                <div class="modal-header bg-warning">
                    <h5 class="modal-title" id="editUserModalLabel">
                        <i class="fas fa-edit me-2"></i>Edit User
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="edit_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="edit_email" name="email" required>
                        </div>

                        <!-- Student-only fields for editing -->
                        <div id="editStudentFields" style="display:none;">
                            <div class="col-md-6 mb-3">
                                <label for="edit_program" class="form-label">Program</label>
                                <input type="text" class="form-control" id="edit_program" name="program">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_year_level" class="form-label">Year Level</label>
                                <select class="form-select" id="edit_year_level" name="year_level">
                                    <option value="">Select Year Level</option>
                                    <?php for ($i = 1; $i <= 6; $i++): ?>
                                        <option value="<?php echo $i; ?>">Year <?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Reset Password section in Edit Form -->
                    <div class="row mt-3">
                        <div class="col-12">
                            <hr>
                            <h6>Reset Password</h6>
                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label for="edit_new_password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="edit_new_password" name="new_password" 
                                           minlength="6" placeholder="Leave blank to keep current">
                                    <div class="form-text">Minimum 6 characters</div>
                                </div>
                                <div class="col-md-4 mb-3 d-flex align-items-end">
                                    <button type="button" class="btn btn-info w-100" onclick="resetPasswordFromEdit()">
                                        <i class="fas fa-key"></i> Reset Password
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-save"></i> Update User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Assign Sections Modal -->
<div class="modal fade" id="assignModal" tabindex="-1" aria-labelledby="assignModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form method="POST" id="assignSectionForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="assign_section">
                <input type="hidden" name="student_id" id="assign_section_student_id">
                
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="assignModalLabel">
                        <i class="fas fa-layer-group me-2"></i>Assign Sections & Subjects
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
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
                                <div class="card-body p-0">
                                    <div class="checkbox-group" id="sectionsContainer" style="max-height: 400px; overflow-y: auto;">
                                        <?php foreach($sections as $section): ?>
                                            <div class="section-checkbox-item m-2">
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

                                    <div id="subjectsContainer" style="max-height: 300px; overflow-y: auto;">
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
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save me-2"></i>Save Assignments
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="manage_users.php">
                <div class="modal-header">
                    <h5 class="modal-title">Reset Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="reset_user_id" id="reset_user_id">
                    
                    <div class="alert alert-warning">
                        <h6 class="alert-heading">Reset Password for: <span id="reset_user_name"></span></h6>
                        <p class="mb-0">The user will need to use the new password to log in.</p>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" 
                               minlength="6" required>
                        <div class="form-text">Minimum 6 characters</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Reset Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Status Toggle Form (hidden) -->
<form method="POST" id="statusForm" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
    <input type="hidden" name="action" value="toggle_status">
    <input type="hidden" name="user_id" id="status_user_id">
</form>

<!-- Delete User Form (hidden) -->
<form method="POST" id="deleteForm" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
    <input type="hidden" name="action" value="delete_user">
    <input type="hidden" name="delete_user_id" id="delete_user_id">
</form>

<!-- Print Area (Hidden) -->
<div id="printArea" style="display: none;">
    <!-- Content will be generated for printing -->
</div>

<script>
// Preload data for section-subject mapping
const allSubjects = <?php echo json_encode($subjects); ?>;
const sectionSubjectsMap = <?php echo json_encode($sectionSubjectsMap); ?>;
const sectionDetailsMap = <?php echo json_encode($sections); ?>;
let currentSubjectSelections = [];
let currentSectionSelections = [];

// View Assessment 
async function fetchAssessmentData(userId) {
    try {
        // Show loading state
        document.getElementById('assessmentTableBody').innerHTML = `
            <tr>
                <td colspan="5" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading assessment details...</p>
                </td>
            </tr>
        `;
        
        // Fetch assessment data using the correct AJAX handler (UPDATED)
        const response = await fetch(`ajax_handler.php?action=get_assessment_file&student_id=${encodeURIComponent(userId)}`);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const assessmentData = await response.json();
        
        if (!assessmentData.success) {
            throw new Error(assessmentData.message || 'Failed to fetch assessment data');
        }
        
        // Populate student info
        const studentInfo = assessmentData.assessment_info || {};
        const academicInfo = assessmentData.academic_info || {};
        const subjects = assessmentData.subjects || [];
        const financialSummary = assessmentData.financial_summary || {};
        
        document.getElementById('assessment_program').textContent = studentInfo.program || 'N/A';
        document.getElementById('assessment_year_level').textContent = studentInfo.year_level || 'N/A';
        document.getElementById('assessment_semester').textContent = academicInfo.current_semester || '1st Semester';
        document.getElementById('assessment_school_year').textContent = academicInfo.school_year || '2024-2025';
        
        // Populate subjects table
        const tableBody = document.getElementById('assessmentTableBody');
        tableBody.innerHTML = '';
        
        let totalUnits = 0;
        let tuitionFee = 0;
        
        if (subjects.length > 0) {
            subjects.forEach(subject => {
                const unitPrice = subject.unit_price || 1000;
                const subjectTotal = subject.total || (subject.units * unitPrice);
                
                totalUnits += parseInt(subject.units) || 0;
                tuitionFee += subjectTotal;
                
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${subject.code || subject.subject_code || ''}</td>
                    <td>${subject.name || subject.subject_name || ''}</td>
                    <td class="text-center">${subject.units || 0}</td>
                    <td class="text-end">₱${unitPrice.toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                    <td class="text-end">₱${subjectTotal.toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                `;
                tableBody.appendChild(row);
            });
        } else {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center py-4 text-muted">
                        <i class="fas fa-book fa-2x mb-3"></i>
                        <p>No subjects enrolled for this semester</p>
                    </td>
                </tr>
            `;
        }
        
        // Calculate totals from the financial summary
        const miscFees = assessmentData.fees_breakdown?.miscellaneous_fees?.amount || 
                        assessmentData.fees_breakdown?.total_fees || 0;
        const totalAssessment = financialSummary.total_assessment || (tuitionFee + miscFees);
        const paymentsMade = financialSummary.payments_made || 0;
        const remainingBalance = financialSummary.remaining_balance || (totalAssessment - paymentsMade);
        
        // Update summary
        document.getElementById('total_units').textContent = totalUnits;
        document.getElementById('tuition_fee').textContent = `₱${tuitionFee.toLocaleString('en-US', {minimumFractionDigits: 2})}`;
        document.getElementById('misc_fees').textContent = `₱${miscFees.toLocaleString('en-US', {minimumFractionDigits: 2})}`;
        document.getElementById('total_assessment').textContent = `₱${totalAssessment.toLocaleString('en-US', {minimumFractionDigits: 2})}`;
        document.getElementById('payments_made').textContent = `₱${paymentsMade.toLocaleString('en-US', {minimumFractionDigits: 2})}`;
        document.getElementById('remaining_balance').textContent = `₱${remainingBalance.toLocaleString('en-US', {minimumFractionDigits: 2})}`;
        
        // Update payment history
        const paymentHistoryBody = document.getElementById('paymentHistoryBody');
        const noPaymentHistory = document.getElementById('noPaymentHistory');
        
        if (assessmentData.payment_history && assessmentData.payment_history.length > 0) {
            paymentHistoryBody.innerHTML = '';
            noPaymentHistory.style.display = 'none';
            
            assessmentData.payment_history.forEach(payment => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${payment.issued_date || ''}</td>
                    <td><small class="text-muted">${payment.permit_number || ''}</small></td>
                    <td class="text-end text-success">₱${parseFloat(payment.amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                    <td class="text-end">₱${parseFloat(payment.remaining_balance || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                `;
                paymentHistoryBody.appendChild(row);
            });
        } else {
            paymentHistoryBody.innerHTML = '';
            noPaymentHistory.style.display = 'block';
        }
        
        return assessmentData;
        
    } catch (error) {
        console.error('Error fetching assessment data:', error);
        document.getElementById('assessmentTableBody').innerHTML = `
            <tr>
                <td colspan="5" class="text-center py-4 text-danger">
                    <i class="fas fa-exclamation-triangle fa-2x mb-3"></i>
                    <p>Error loading assessment: ${error.message}</p>
                    <button onclick="fetchAssessmentData('${userId}')" class="btn btn-sm btn-primary mt-2">
                        Retry
                    </button>
                </td>
            </tr>
        `;
        throw error;
    }
}

// View Assessment - Main function
function viewAssessment(userId, userName) {
    // Reset modal content
    document.getElementById('assessment_student_id').textContent = userId;
    document.getElementById('assessment_student_name').textContent = userName;
    
    // Reset summary
    document.getElementById('total_units').textContent = '0';
    document.getElementById('tuition_fee').textContent = '₱0.00';
    document.getElementById('misc_fees').textContent = '₱0.00';
    document.getElementById('total_assessment').textContent = '₱0.00';
    document.getElementById('payments_made').textContent = '₱0.00';
    document.getElementById('remaining_balance').textContent = '₱0.00';
    
    // Clear payment history
    document.getElementById('paymentHistoryBody').innerHTML = '';
    document.getElementById('noPaymentHistory').style.display = 'block';
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('assessmentModal'));
    modal.show();
    
    // Fetch assessment data
    fetchAssessmentData(userId).catch(err => {
        console.error('Assessment fetch failed:', err);
    });
}

// Print Assessment
function printAssessment() {
    const printWindow = window.open('', '_blank');
    
    // Get assessment data for printing
    const studentId = document.getElementById('assessment_student_id').textContent;
    const studentName = document.getElementById('assessment_student_name').textContent;
    const program = document.getElementById('assessment_program').textContent;
    const yearLevel = document.getElementById('assessment_year_level').textContent;
    const semester = document.getElementById('assessment_semester').textContent;
    const schoolYear = document.getElementById('assessment_school_year').textContent;
    
    // Get table data
    const tableRows = Array.from(document.querySelectorAll('#assessmentTableBody tr')).map(row => {
        const cells = row.querySelectorAll('td');
        return {
            code: cells[0]?.textContent || '',
            name: cells[1]?.textContent || '',
            units: cells[2]?.textContent || '',
            unitPrice: cells[3]?.textContent || '',
            total: cells[4]?.textContent || ''
        };
    });
    
    // Get summary data
    const totalUnits = document.getElementById('total_units').textContent;
    const tuitionFee = document.getElementById('tuition_fee').textContent;
    const miscFees = document.getElementById('misc_fees').textContent;
    const totalAssessment = document.getElementById('total_assessment').textContent;
    const paymentsMade = document.getElementById('payments_made').textContent;
    const remainingBalance = document.getElementById('remaining_balance').textContent;
    
    // Generate print content
    const printContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Assessment - ${studentName}</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 20px; }
                .header h1 { margin: 0; color: #333; }
                .header h3 { margin: 5px 0; color: #666; }
                .student-info { margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 5px; }
                .student-info p { margin: 5px 0; }
                table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
                th { background-color: #f2f2f2; }
                .text-right { text-align: right; }
                .text-center { text-align: center; }
                .summary { margin-top: 30px; padding: 20px; border: 1px solid #ddd; background: #f9f9f9; }
                .summary-row { display: flex; justify-content: space-between; margin: 5px 0; }
                .total-row { font-weight: bold; font-size: 1.1em; border-top: 2px solid #333; padding-top: 10px; margin-top: 10px; }
                .footer { margin-top: 50px; text-align: center; color: #666; font-size: 0.9em; }
                @media print {
                    body { margin: 0; }
                    .no-print { display: none; }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>STUDENT ASSESSMENT</h1>
                <h3>${semester} ${schoolYear}</h3>
            </div>
            
            <div class="student-info">
                <p><strong>Student ID:</strong> ${studentId}</p>
                <p><strong>Name:</strong> ${studentName}</p>
                <p><strong>Program:</strong> ${program}</p>
                <p><strong>Year Level:</strong> ${yearLevel}</p>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Subject Code</th>
                        <th>Subject Name</th>
                        <th class="text-center">Units</th>
                        <th class="text-right">Amount per Unit</th>
                        <th class="text-right">Subject Total</th>
                    </tr>
                </thead>
                <tbody>
                    ${tableRows.map(row => `
                        <tr>
                            <td>${row.code}</td>
                            <td>${row.name}</td>
                            <td class="text-center">${row.units}</td>
                            <td class="text-right">${row.unitPrice}</td>
                            <td class="text-right">${row.total}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
            
            <div class="summary">
                <div class="summary-row">
                    <span><strong>Total Units:</strong></span>
                    <span>${totalUnits}</span>
                </div>
                <div class="summary-row">
                    <span><strong>Tuition Fee:</strong></span>
                    <span>${tuitionFee}</span>
                </div>
                <div class="summary-row">
                    <span><strong>Miscellaneous Fees:</strong></span>
                    <span>${miscFees}</span>
                </div>
                <div class="summary-row total-row">
                    <span><strong>Total Assessment:</strong></span>
                    <span>${totalAssessment}</span>
                </div>
                <div class="summary-row">
                    <span><strong>Payments Made:</strong></span>
                    <span class="text-success">${paymentsMade}</span>
                </div>
                <div class="summary-row total-row">
                    <span><strong>Remaining Balance:</strong></span>
                    <span class="text-danger">${remainingBalance}</span>
                </div>
            </div>
            
            <div class="footer">
                <p>Generated on: ${new Date().toLocaleDateString()} ${new Date().toLocaleTimeString()}</p>
                <p>This is a computer-generated document.</p>
            </div>
            
            <div class="no-print" style="margin-top: 20px; text-align: center;">
                <button onclick="window.print()" style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;">
                    Print Document
                </button>
            </div>
            
            <script>
                window.onload = function() {
                    // Auto-print when window loads
                    window.print();
                };
            <\/script>
        </body>
        </html>
    `;
    
    printWindow.document.write(printContent);
    printWindow.document.close();
}

// View User Details via AJAX 
function viewUserDetails(userId) {
    // Set modal title
    document.getElementById('modalUserName').textContent = 'Loading...';
    
    // Show loading spinner
    document.getElementById('userDetailsContent').innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-3">Loading user details...</p>
        </div>
    `;
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('userDetailsModal'));
    modal.show();
    
    // Fetch user details via AJAX
    fetch(`ajax_handler.php?action=get_user_details&user_id=${encodeURIComponent(userId)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Generate HTML from the data
                const html = generateUserDetailsHTML(data.user, data.additional_info);
                document.getElementById('userDetailsContent').innerHTML = html;
                document.getElementById('modalUserName').textContent = data.user.name;
            } else {
                document.getElementById('userDetailsContent').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> ${data.message}
                    </div>
                `;
                document.getElementById('modalUserName').textContent = 'Error';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('userDetailsContent').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> Error loading user details
                </div>
            `;
            document.getElementById('modalUserName').textContent = 'Error';
        });
}
// Generate HTML for user details modal
function generateUserDetailsHTML(user, additionalInfo) {
    // Helper function to get initials
    function getInitials(name) {
        let initials = '';
        const words = name.split(' ');
        for (const word of words) {
            if (word.trim()) {
                initials += word[0].toUpperCase();
            }
        }
        return initials.substring(0, 2);
    }

    // Format date
    function formatDate(dateString) {
        if (!dateString) return 'Never';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    // Determine badge colors
    const statusBadgeColor = user.user_status === 'active' ? 'success' : 'danger';
    const studentTypeBadgeColor = user.student_type === 'regular' ? 'primary' : 'warning';
    const enrollmentBadgeColor = user.enrollment_status === 'enrolled' ? 'success' : 
                                user.enrollment_status === 'dropped' ? 'danger' : 'info';

    // Generate sections HTML
    let sectionsHTML = '';
    if (additionalInfo.sections && additionalInfo.sections.length > 0) {
        sectionsHTML = `
            <div class="mb-3">
                <strong>Assigned Sections (${additionalInfo.sections.length}):</strong>
                <ul class="mt-2 mb-3">
                    ${additionalInfo.sections.map(section => `
                        <li>
                            ${section.section_code} - 
                            ${section.program} 
                            (Year ${section.year_level})
                        </li>
                    `).join('')}
                </ul>
            </div>
        `;
    } else {
        sectionsHTML = `
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> No section assigned
            </div>
        `;
    }

    // Generate subjects HTML
    let subjectsHTML = '';
    if (additionalInfo.subjects && additionalInfo.subjects.length > 0) {
        subjectsHTML = `
            <div>
                <strong>Enrolled Subjects (${additionalInfo.subjects.length}):</strong>
                <div class="table-responsive mt-2" style="max-height: 150px; overflow-y: auto;">
                    <table class="table table-sm table-striped">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Subject Name</th>
                                <th>Units</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${additionalInfo.subjects.map(subject => `
                                <tr>
                                    <td><code>${subject.subject_code}</code></td>
                                    <td>${subject.subject_name}</td>
                                    <td>${subject.units}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    } else {
        subjectsHTML = `
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> No subjects enrolled
            </div>
        `;
    }

    // Generate payment summary HTML
    let paymentSummaryHTML = '';
    if (additionalInfo.payment_summary && additionalInfo.payment_summary.total_payments > 0) {
        const unpaidAmount = additionalInfo.payment_summary.unpaid_amount || 0;
        const partialAmount = additionalInfo.payment_summary.partial_amount || 0;
        const outstandingBalance = unpaidAmount + partialAmount;
        
        paymentSummaryHTML = `
            <div class="row text-center">
                <div class="col-4 mb-3">
                    <div class="border rounded p-2">
                        <h6 class="text-primary">${additionalInfo.payment_summary.total_payments}</h6>
                        <small class="text-muted">Total</small>
                    </div>
                </div>
                <div class="col-4 mb-3">
                    <div class="border rounded p-2">
                        <h6 class="text-success">${additionalInfo.payment_summary.paid_count}</h6>
                        <small class="text-muted">Paid</small>
                    </div>
                </div>
                <div class="col-4 mb-3">
                    <div class="border rounded p-2">
                        <h6 class="text-danger">${additionalInfo.payment_summary.unpaid_count}</h6>
                        <small class="text-muted">Unpaid</small>
                    </div>
                </div>
                <div class="col-12">
                    <div class="border rounded p-2">
                        <h6 class="text-warning">₱${outstandingBalance.toLocaleString('en-US', {minimumFractionDigits: 2})}</h6>
                        <small class="text-muted">Outstanding Balance</small>
                    </div>
                </div>
            </div>
        `;
    } else {
        paymentSummaryHTML = `
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> No payment records found.
            </div>
        `;
    }

    // Generate issuance summary HTML (for employees)
    let issuanceSummaryHTML = '';
    if (additionalInfo.issuance_summary) {
        issuanceSummaryHTML = `
            <div class="row text-center">
                <div class="col-4">
                    <div class="border rounded p-2">
                        <h6 class="text-primary">${additionalInfo.issuance_summary.total_issued || 0}</h6>
                        <small class="text-muted">Total Issued</small>
                    </div>
                </div>
                <div class="col-4">
                    <div class="border rounded p-2">
                        <h6 class="text-success">₱${(additionalInfo.issuance_summary.total_amount_issued || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</h6>
                        <small class="text-muted">Total Amount</small>
                    </div>
                </div>
                <div class="col-4">
                    <div class="border rounded p-2">
                        <h6 class="text-info">${additionalInfo.issuance_summary.unique_students_served || 0}</h6>
                        <small class="text-muted">Students Served</small>
                    </div>
                </div>
            </div>
        `;
    }

    // Generate activity log HTML
    let activityLogHTML = '';
    if (additionalInfo.activity_log && additionalInfo.activity_log.length > 0) {
        activityLogHTML = additionalInfo.activity_log.map(activity => `
            <div class="activity-log-item mb-3">
                <div class="d-flex justify-content-between">
                    <strong>${activity.action}</strong>
                    <small class="text-muted">${formatDate(activity.created_at)}</small>
                </div>
                <div class="text-muted small">${activity.description || ''}</div>
            </div>
        `).join('');
    } else {
        activityLogHTML = `
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> No recent activity found.
            </div>
        `;
    }

    // Generate student-specific HTML
    let studentSpecificHTML = '';
    if (user.role === 'student') {
        studentSpecificHTML = `
            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="card user-details-card">
                        <div class="card-header bg-success text-white">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-graduation-cap me-2"></i>Academic Information
                            </h6>
                        </div>
                        <div class="card-body">
                            ${sectionsHTML}
                            ${subjectsHTML}
                        </div>
                    </div>
                </div>

                <div class="col-md-6 mb-4">
                    <div class="card user-details-card">
                        <div class="card-header bg-warning text-dark">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-money-bill-wave me-2"></i>Payment Summary
                            </h6>
                        </div>
                        <div class="card-body">
                            ${paymentSummaryHTML}
                        </div>
                    </div>
                </div>
            </div>
        `;
    } else {
        studentSpecificHTML = `
            <div class="row">
                <div class="col-md-12 mb-4">
                    <div class="card user-details-card">
                        <div class="card-header bg-secondary text-white">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-chart-bar me-2"></i>Work Summary
                            </h6>
                        </div>
                        <div class="card-body">
                            ${issuanceSummaryHTML}
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    // Main HTML structure
    return `
        <!-- User Details Content -->
        <div class="user-name" style="display: none;">${user.name}</div>

        <div class="row">
            <!-- User Avatar and Quick Info -->
            <div class="col-md-3 mb-4">
                <div class="card text-center user-details-card">
                    <div class="card-body">
                        <div class="user-avatar mb-3">
                            ${getInitials(user.name)}
                        </div>
                        <h5 class="card-title">${user.name}</h5>
                        <h6 class="card-subtitle mb-2 text-muted">
                            <code>${user.user_id}</code>
                        </h6>
                        <p class="card-text">
                            <span class="badge bg-info">${user.role}</span>
                            <span class="badge bg-${statusBadgeColor} ms-1">
                                ${user.user_status}
                            </span>
                        </p>
                        <div class="mt-3">
                            <button class="btn btn-sm btn-warning w-100 mb-2" 
                                    onclick="editUserFromDetails('${user.user_id}', '${user.name.replace(/'/g, "\\'")}', '${user.email.replace(/'/g, "\\'")}', '${user.role}', '${(user.additional_info || '').replace(/'/g, "\\'")}', '${user.year_level || ''}')">
                                <i class="fas fa-edit"></i> Edit User
                            </button>
                            ${user.user_id !== '<?php echo $_SESSION['user_id']; ?>' ? `
                            <button class="btn btn-sm btn-info w-100 mb-2" 
                                    onclick="resetPasswordFromDetails('${user.user_id}', '${user.name.replace(/'/g, "\\'")}')">
                                <i class="fas fa-key"></i> Reset Password
                            </button>
                            <button class="btn btn-sm btn-${user.user_status === 'active' ? 'warning' : 'success'} w-100 mb-2"
                                    onclick="toggleStatus('${user.user_id}', '${user.name.replace(/'/g, "\\'")}', '${user.user_status}')">
                                <i class="fas fa-${user.user_status === 'active' ? 'lock' : 'unlock'}"></i>
                                ${user.user_status === 'active' ? 'Lock' : 'Unlock'} User
                            </button>
                            <button class="btn btn-sm btn-danger w-100"
                                    onclick="deleteUserFromDetails('${user.user_id}', '${user.name.replace(/'/g, "\\'")}')">
                                <i class="fas fa-trash"></i> Delete User
                            </button>
                            ` : ''}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Basic Information -->
            <div class="col-md-9">
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <div class="card user-details-card">
                            <div class="card-header bg-primary text-white">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-info-circle me-2"></i>Basic Information
                                </h6>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm details-table">
                                    <tr>
                                        <th>Email:</th>
                                        <td>${user.email}</td>
                                    </tr>
                                    ${user.role === 'student' ? `
                                    <tr>
                                        <th>Program:</th>
                                        <td>${user.additional_info || 'Not set'}</td>
                                    </tr>
                                    <tr>
                                        <th>Year Level:</th>
                                        <td>${user.year_level ? 'Year ' + user.year_level : 'Not Set'}</td>
                                    </tr>
                                    <tr>
                                        <th>Student Type:</th>
                                        <td>
                                            <span class="badge bg-${studentTypeBadgeColor}">
                                                ${user.student_type || 'Not set'}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Enrollment Status:</th>
                                        <td>
                                            <span class="badge bg-${enrollmentBadgeColor}">
                                                ${user.enrollment_status || 'Not set'}
                                            </span>
                                        </td>
                                    </tr>
                                    ${user.enrollment_date ? `
                                    <tr>
                                        <th>Enrollment Date:</th>
                                        <td>${formatDate(user.enrollment_date)}</td>
                                    </tr>
                                    ` : ''}
                                    ${user.contact_number ? `
                                    <tr>
                                        <th>Contact Number:</th>
                                        <td>${user.contact_number}</td>
                                    </tr>
                                    ` : ''}
                                    ${user.address ? `
                                    <tr>
                                        <th>Address:</th>
                                        <td>${user.address}</td>
                                    </tr>
                                    ` : ''}
                                    ` : `
                                    <tr>
                                        <th>Position:</th>
                                        <td>${user.additional_info || 'Not set'}</td>
                                    </tr>
                                    `}
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 mb-4">
                        <div class="card user-details-card">
                            <div class="card-header bg-info text-white">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-history me-2"></i>Account Activity
                                </h6>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm details-table">
                                    <tr>
                                        <th>Account Created:</th>
                                        <td>${formatDate(user.created_at)}</td>
                                    </tr>
                                    <tr>
                                        <th>Last Active:</th>
                                        <td>
                                            ${formatDate(user.last_active)}
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                ${studentSpecificHTML}

                <!-- Activity Log -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="card user-details-card">
                            <div class="card-header">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-history me-2"></i>Recent Activity
                                </h6>
                            </div>
                            <div class="card-body" style="max-height: 200px; overflow-y: auto;">
                                ${activityLogHTML}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
}
// Edit user from details modal
function editUserFromDetails(userId, userName, userEmail, userRole, program, yearLevel) {
    // Close details modal
    bootstrap.Modal.getInstance(document.getElementById('userDetailsModal')).hide();
    
    // Open edit modal
    openEditModal(userId, userName, userEmail, userRole, program, yearLevel);
}

// Reset password from details modal
function resetPasswordFromDetails(userId, userName) {
    // Close details modal
    bootstrap.Modal.getInstance(document.getElementById('userDetailsModal')).hide();
    
    // Open reset password modal
    document.getElementById('reset_user_id').value = userId;
    document.getElementById('reset_user_name').textContent = userName;
    document.getElementById('new_password').value = '';
    
    new bootstrap.Modal(document.getElementById('resetPasswordModal')).show();
}

// Delete user from details modal
function deleteUserFromDetails(userId, userName) {
    if (confirm(`Are you sure you want to delete user "${userName}"? This action cannot be undone.`)) {
        // Close details modal
        bootstrap.Modal.getInstance(document.getElementById('userDetailsModal')).hide();
        
        // Submit delete form
        document.getElementById('delete_user_id').value = userId;
        document.getElementById('deleteForm').submit();
    }
}

// Toggle student fields in Add User form
function toggleStudentFields() {
    const roleSelect = document.getElementById('role_select');
    const studentFields = document.getElementById('studentFields');

    if (roleSelect.value === 'student') {
        studentFields.style.display = 'block';
    } else {
        studentFields.style.display = 'none';
    }
}

// Open Edit User Modal
function openEditModal(userId, name, email, role, program, yearLevel) {
    // Populate edit form fields
    document.getElementById('edit_user_id').value = userId;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_email').value = email;
    
    // Handle student-specific fields
    const editStudentFields = document.getElementById('editStudentFields');
    if (role === 'student') {
        editStudentFields.style.display = 'block';
        document.getElementById('edit_program').value = program || '';
        document.getElementById('edit_year_level').value = yearLevel || '';
    } else {
        editStudentFields.style.display = 'none';
    }
    
    // Clear password field
    document.getElementById('edit_new_password').value = '';
    
    // Show modal
    const editModal = new bootstrap.Modal(document.getElementById('editUserModal'));
    editModal.show();
}

// Toggle user status
function toggleStatus(userId, userName, currentStatus) {
    if (confirm(`Are you sure you want to ${currentStatus === 'active' ? 'lock' : 'unlock'} user ${userName}?`)) {
        document.getElementById('status_user_id').value = userId;
        document.getElementById('statusForm').submit();
    }
}

// Delete user
function deleteUser(userId, userName) {
    if (confirm(`Are you sure you want to delete user ${userName}? This action cannot be undone.`)) {
        document.getElementById('delete_user_id').value = userId;
        document.getElementById('deleteForm').submit();
    }
}

// Reset password from edit modal
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

// Prepare assign modal
function prepareAssignModal(studentId, studentName, studentType, currentSectionIds, currentSubjectIds) {
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
    let hasSubjects = true;

    selectedSections.forEach(sectionId => {
        const section = sectionDetailsMap.find(s => s.id == sectionId);
        const subjectsInSection = sectionSubjectsMap[sectionId] || [];
        
        if (subjectsInSection.length > 0) {
            hasSubjects = true;
            html += `
                <div class="mb-3">
                    <h6 class="fw-bold text-primary">
                        <i class="fas fa-layer-group me-2"></i>
                        ${section.section_label}
                        <button type="button" class="btn btn-sm btn-outline-primary float-end select-all-section-btn" 
                                data-section-id="${sectionId}">
                            <i class="fas fa-check-double me-1"></i>Select All
                        </button>
                    </h6>
                    <div class="row">
            `;
            
            subjectsInSection.forEach(subject => {
                const isChecked = currentSubjectSelections.includes(subject.id.toString()) ? 'checked' : '';
                html += `
                    <div class="col-md-6 mb-2">
                        <div class="modal-subject-item ${isChecked ? 'selected' : ''}">
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
                                <div class="d-flex justify-content-between align-items-center mt-1">
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
                <hr>
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
            const subjectItem = this.closest('.modal-subject-item');
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
                const subjectItem = cb.closest('.modal-subject-item');
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

// Event listeners for assign modal
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

    // Select all subjects in assign modal
    document.getElementById('selectAllSubjects').addEventListener('change', function() {
        document.querySelectorAll('.subject-checkbox').forEach(checkbox => {
            checkbox.checked = this.checked;
            // Update visual state
            const subjectItem = checkbox.closest('.modal-subject-item');
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

    // Reset assign modal when closed
    document.getElementById('assignModal').addEventListener('hidden.bs.modal', function () {
        document.getElementById('assignSectionForm').reset();
        document.getElementById('subjectsContainer').innerHTML = `
            <div class="text-center py-5 text-muted">
                <i class="fas fa-book-open fa-3x mb-3"></i>
                <h5>No Sections Selected</h5>
                <p>Please select sections from the left panel to view available subjects.</p>
            </div>
        `;
        document.getElementById('summaryInfo').innerHTML = '<p class="text-muted">Select sections and subjects to see summary</p>';
        currentSubjectSelections = [];
        currentSectionSelections = [];
    });

    // Reset add user modal when closed
    document.getElementById('addUserModal').addEventListener('hidden.bs.modal', function () {
        document.getElementById('addUserForm').reset();
        document.getElementById('studentFields').style.display = 'none';
    });

    // Reset edit user modal when closed
    document.getElementById('editUserModal').addEventListener('hidden.bs.modal', function () {
        document.getElementById('editUserForm').reset();
        document.getElementById('editStudentFields').style.display = 'none';
    });

    // Reset assessment modal when closed
    document.getElementById('assessmentModal').addEventListener('hidden.bs.modal', function () {
        document.getElementById('assessmentTableBody').innerHTML = '';
        document.getElementById('paymentHistoryBody').innerHTML = '';
        document.getElementById('noPaymentHistory').style.display = 'none';
    });
});
</script>

<?php renderPageEnd(); ?>