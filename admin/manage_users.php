<?php
require_once '../init.php';
require_once '../theme.php';
require_once '../layout.php';

requireRole('admin');

$pdo = getDBConnection();
$error = '';
$success = '';

// Get programs for dropdown
$programs = $pdo->query("SELECT DISTINCT program FROM subjects WHERE program IS NOT NULL AND program != '' ORDER BY program")->fetchAll(PDO::FETCH_COLUMN);
if (empty($programs)) {
    $programs = ['BS Information Technology', 'BS Computer Science', 'BS Business Administration', 'BS Accountancy'];
}

// Program code mapping
$program_codes = [
    'BS Information Technology' => '01',
    'BS Computer Science' => '02',
    'BS Business Administration' => '03',
    'BS Accountancy' => '04'
];

// Handle POST actions
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
                    $current = $stmt->fetchColumn();
                    $new = ($current === 'active') ? 'locked' : 'active';
                    $stmt = $pdo->prepare("UPDATE users SET user_status = ? WHERE user_id = ?");
                    if ($stmt->execute([$new, $user_id])) {
                        logActivity($_SESSION['user_id'], 'User Status Changed', "Changed user {$user_id} status from {$current} to {$new}");
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
                $student_id = sanitizeInput($_POST['student_id'] ?? '');
                $program = sanitizeInput($_POST['program'] ?? '');
                $year_level = intval($_POST['year_level'] ?? 0);

                if (empty($name)) {
                    $error = 'Name is required.';
                } elseif (empty($email) || !validateEmail($email)) {
                    $error = 'Valid email is required.';
                } elseif (!in_array($role, ['admin', 'cashier', 'student', 'registrar'])) {
                    $error = 'Valid role is required.';
                } elseif (empty($password) || strlen($password) < 6) {
                    $error = 'Password must be at least 6 characters.';
                } else {
                    try {
                        if ($role === 'student') {
                            if (empty($student_id)) {
                                $error = 'Student ID is required.';
                            } elseif (!preg_match('/^C[0-9]{2}-[0-9]{2}-[0-9]{4}-MAN121$/', $student_id)) {
                                $error = 'Invalid Student ID format. Use: CYY-PP-NNNN-MAN121';
                            } else {
                                $stmt = $pdo->prepare("SELECT id FROM users WHERE user_id = ?");
                                $stmt->execute([$student_id]);
                                if ($stmt->fetch()) $error = 'Student ID already exists.';
                            }
                            if (empty($program)) $error = 'Program is required for student.';
                            $new_user_id = $student_id;
                        } else {
                            $prefix = strtoupper($role);
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = ?");
                            $stmt->execute([$role]);
                            $count = $stmt->fetchColumn() + 1;
                            $new_user_id = $prefix . str_pad($count, 3, '0', STR_PAD_LEFT);
                        }

                        if (empty($error)) {
                            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                            $stmt->execute([$email]);
                            if ($stmt->fetch()) {
                                $error = 'Email already exists.';
                            } else {
                                $hashed = hashPassword($password);
                                $pdo->beginTransaction();

                                $stmt = $pdo->prepare("INSERT INTO users (user_id, name, email, password, role, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                                $stmt->execute([$new_user_id, $name, $email, $hashed, $role]);

                                if ($role === 'student') {
                                    $stmt = $pdo->prepare("INSERT INTO students_info (user_id, name, email, program, year_level, enrollment_date, student_status, created_at) VALUES (?, ?, ?, ?, ?, NOW(), 'new', NOW())");
                                    $stmt->execute([$new_user_id, $name, $email, $program, $year_level]);
                                } else {
                                    $stmt = $pdo->prepare("INSERT INTO employee_info (user_id, name, email, role, created_at) VALUES (?, ?, ?, ?, NOW())");
                                    $stmt->execute([$new_user_id, $name, $email, ucfirst($role)]);
                                }

                                $pdo->commit();
                                logActivity($_SESSION['user_id'], 'User Created', "Created new {$role} user: {$new_user_id} - {$name}" . ($role === 'student' ? " | Program: {$program} | Year: {$year_level}" : ""));
                                $success = "User created successfully. User ID: {$new_user_id}";
                            }
                        }
                    } catch (Exception $e) {
                        if ($pdo->inTransaction()) $pdo->rollBack();
                        $error = "Failed to create user: " . $e->getMessage();
                    }
                }
                break;

            case 'assign_section':
                $student_id = sanitizeInput($_POST['student_id'] ?? '');
                $section_ids = $_POST['section_ids'] ?? [];
                $subject_ids = $_POST['subject_ids'] ?? [];

                if (empty($student_id)) {
                    $error = 'Please select a student.';
                } else {
                    try {
                        $pdo->beginTransaction();
                        
                        $stmt = $pdo->prepare("DELETE FROM student_sections WHERE student_id = ?");
                        $stmt->execute([$student_id]);
                        
                        $stmt = $pdo->prepare("INSERT INTO student_sections (student_id, section_id) VALUES (?, ?)");
                        foreach ($section_ids as $sid) {
                            $sid = intval($sid);
                            if ($sid > 0) $stmt->execute([$student_id, $sid]);
                        }
                        
                        $student_type = count($section_ids) > 1 ? 'irregular' : 'regular';
                        $stmt = $pdo->prepare("UPDATE students_info SET student_type = ? WHERE user_id = ?");
                        $stmt->execute([$student_type, $student_id]);
                        
                        $stmt = $pdo->prepare("DELETE FROM student_subjects WHERE student_id = ?");
                        $stmt->execute([$student_id]);
                        
                        if (!empty($subject_ids)) {
                            $stmt = $pdo->prepare("INSERT INTO student_subjects (student_id, subject_id, section_id, assigned_by, reason, status) 
                                                   VALUES (?, ?, (SELECT section_id FROM subject_sections WHERE subject_id = ? LIMIT 1), ?, 'Assigned via section/subject management', 'active')");
                            foreach ($subject_ids as $subj_id) {
                                $subj_id = intval($subj_id);
                                if ($subj_id > 0) {
                                    $stmt->execute([$student_id, $subj_id, $subj_id, $_SESSION['user_id']]);
                                }
                            }
                        }
                        
                        $pdo->commit();
                        
                        logActivity($_SESSION['user_id'], 'Sections Assigned', 
                                   "Assigned " . count($section_ids) . " sections and " . count($subject_ids) . " subjects to student {$student_id}. Student type: {$student_type}");
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
                $role = sanitizeInput($_POST['edit_user_role'] ?? '');
                $student_id = sanitizeInput($_POST['student_id'] ?? '');
                $program = sanitizeInput($_POST['program'] ?? '');
                $year_level = intval($_POST['year_level'] ?? 0);

                if (empty($edit_user_id) || empty($name) || empty($email) || !validateEmail($email)) {
                    $error = 'Invalid input.';
                } else {
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND user_id != ?");
                    $stmt->execute([$email, $edit_user_id]);
                    if ($stmt->fetch()) {
                        $error = 'Email is already used by another user.';
                    } else {
                        try {
                            $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
                            $stmt->execute([$edit_user_id]);
                            $old_user = $stmt->fetch();
                            if (!$old_user) throw new Exception('User not found.');

                            $pdo->beginTransaction();
                            $final_user_id = $edit_user_id;
                            $old_program = $old_year_level = null;

                            if ($role === 'student' && !empty($student_id)) {
                                if (!preg_match('/^C[0-9]{2}-[0-9]{2}-[0-9]{4}-MAN121$/', $student_id)) {
                                    throw new Exception('Invalid Student ID format.');
                                }
                                if ($student_id !== $edit_user_id) {
                                    $stmt = $pdo->prepare("SELECT id FROM users WHERE user_id = ?");
                                    $stmt->execute([$student_id]);
                                    if ($stmt->fetch()) throw new Exception('Student ID already exists.');
                                    $final_user_id = $student_id;
                                }
                                $stmt = $pdo->prepare("SELECT program, year_level FROM students_info WHERE user_id = ?");
                                $stmt->execute([$edit_user_id]);
                                $old_student = $stmt->fetch();
                                if ($old_student) {
                                    $old_program = $old_student['program'];
                                    $old_year_level = $old_student['year_level'];
                                }
                            }

                            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
                            if ($final_user_id !== $edit_user_id) {
                                $tables = [
                                    'users' => 'user_id',
                                    'students_info' => 'user_id',
                                    'employee_info' => 'user_id',
                                    'student_sections' => 'student_id',
                                    'student_subjects' => 'student_id',
                                    'student_course_enrollment' => 'student_id',
                                    'payments' => 'student_id',
                                    'transaction_history' => 'student_id',
                                    'activity_logs' => 'user_id'
                                ];
                                foreach ($tables as $table => $col) {
                                    $stmt = $pdo->prepare("UPDATE {$table} SET {$col} = ? WHERE {$col} = ?");
                                    $stmt->execute([$final_user_id, $edit_user_id]);
                                }
                            }
                            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE user_id = ?");
                            $stmt->execute([$name, $email, $final_user_id]);
                            if ($old_user['role'] === 'student') {
                                $stmt = $pdo->prepare("UPDATE students_info SET name = ?, email = ?, program = ?, year_level = ? WHERE user_id = ?");
                                $stmt->execute([$name, $email, $program, $year_level, $final_user_id]);
                            } else {
                                $stmt = $pdo->prepare("UPDATE employee_info SET name = ?, email = ? WHERE user_id = ?");
                                $stmt->execute([$name, $email, $final_user_id]);
                            }
                            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                            $pdo->commit();

                            $changes = [];
                            if ($old_user['name'] !== $name) $changes[] = "name: '{$old_user['name']}' → '{$name}'";
                            if ($old_user['email'] !== $email) $changes[] = "email: '{$old_user['email']}' → '{$email}'";
                            if ($final_user_id !== $edit_user_id) $changes[] = "user_id: '{$edit_user_id}' → '{$final_user_id}'";
                            if ($role === 'student') {
                                if ($old_program !== $program) $changes[] = "program: '{$old_program}' → '{$program}'";
                                if ($old_year_level != $year_level) $changes[] = "year_level: {$old_year_level} → {$year_level}";
                            }
                            logActivity($_SESSION['user_id'], 'User Updated', "Updated user: " . implode(", ", $changes));
                            $success = 'User updated successfully.' . ($final_user_id !== $edit_user_id ? " User ID changed to: {$final_user_id}" : '');
                        } catch (Exception $e) {
                            if ($pdo->inTransaction()) $pdo->rollBack();
                            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                            $error = 'Failed to update user: ' . $e->getMessage();
                        }
                    }
                }
                break;

            case 'delete_user':
                $delete_user_id = sanitizeInput($_POST['delete_user_id'] ?? '');
                if (empty($delete_user_id) || $delete_user_id === $_SESSION['user_id']) {
                    $error = 'Invalid user ID or cannot delete your own account.';
                } else {
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
                    $stmt->execute([$delete_user_id]);
                    $user = $stmt->fetch();
                    if (!$user) {
                        $error = 'User not found.';
                    } else {
                        try {
                            logActivity($_SESSION['user_id'], 'User Deleted', "Deleted user: {$delete_user_id} - {$user['name']} ({$user['role']})");
                            $pdo->beginTransaction();
                            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

                            if ($user['role'] === 'student') {
                                $pdo->prepare("DELETE FROM student_sections WHERE student_id = ?")->execute([$delete_user_id]);
                                $pdo->prepare("DELETE FROM student_subjects WHERE student_id = ?")->execute([$delete_user_id]);
                                $pdo->prepare("DELETE FROM student_course_enrollment WHERE student_id = ?")->execute([$delete_user_id]);
                                $pdo->prepare("DELETE FROM payments WHERE student_id = ?")->execute([$delete_user_id]);
                                $pdo->prepare("DELETE FROM transaction_history WHERE student_id = ?")->execute([$delete_user_id]);
                                $pdo->prepare("DELETE FROM students_info WHERE user_id = ?")->execute([$delete_user_id]);
                            } else {
                                $pdo->prepare("DELETE FROM payments WHERE issued_by = ?")->execute([$delete_user_id]);
                                $pdo->prepare("DELETE FROM transaction_history WHERE issued_by = ?")->execute([$delete_user_id]);
                                $pdo->prepare("DELETE FROM employee_info WHERE user_id = ?")->execute([$delete_user_id]);
                            }
                            $pdo->prepare("DELETE FROM activity_logs WHERE user_id = ? AND action != 'User Deleted'")->execute([$delete_user_id]);
                            $pdo->prepare("DELETE FROM users WHERE user_id = ?")->execute([$delete_user_id]);

                            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                            $pdo->commit();
                            $success = 'User deleted successfully.';
                        } catch (Exception $e) {
                            if ($pdo->inTransaction()) $pdo->rollBack();
                            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                            $error = 'Failed to delete user: ' . $e->getMessage();
                        }
                    }
                }
                break;

            case 'reset_password':
                $reset_user_id = sanitizeInput($_POST['reset_user_id'] ?? '');
                $new_password = $_POST['new_password'] ?? '';
                if (empty($reset_user_id) || strlen($new_password) < 6) {
                    $error = 'Invalid user ID or password too short.';
                } else {
                    try {
                        $hashed = hashPassword($new_password);
                        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                        $stmt->execute([$hashed, $reset_user_id]);
                        logActivity($_SESSION['user_id'], 'Password Reset', "Reset password for user: {$reset_user_id}");
                        $success = 'Password reset successfully.';
                    } catch (Exception $e) {
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
$program_filter = $_GET['program_filter'] ?? '';
$year_filter = $_GET['year_filter'] ?? '';
$student_type_filter = $_GET['student_type_filter'] ?? '';

$where = [];
$params = [];

if (!empty($role_filter)) {
    $where[] = "u.role = ?";
    $params[] = $role_filter;
}
if (!empty($status_filter)) {
    $where[] = "u.user_status = ?";
    $params[] = $status_filter;
}
if (!empty($program_filter)) {
    $where[] = "si.program = ?";
    $params[] = $program_filter;
}
if (!empty($year_filter)) {
    $where[] = "si.year_level = ?";
    $params[] = $year_filter;
}
if (!empty($student_type_filter)) {
    $where[] = "si.student_type = ?";
    $params[] = $student_type_filter;
}
if (!empty($search)) {
    $where[] = "(u.name LIKE ? OR u.email LIKE ? OR u.user_id LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}
$where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$per_page = 20;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $per_page;

$count_sql = "SELECT COUNT(*) FROM users u
              LEFT JOIN students_info si ON u.user_id = si.user_id
              LEFT JOIN employee_info ei ON u.user_id = ei.user_id
              {$where_clause}";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetchColumn();
$total_pages = ceil($total_records / $per_page);

$sql = "SELECT u.*,
               COALESCE(si.program, ei.role) as additional_info,
               COALESCE(si.year_level, '') as year_level,
               si.student_type,
               si.enrollment_status
        FROM users u
        LEFT JOIN students_info si ON u.user_id = si.user_id
        LEFT JOIN employee_info ei ON u.user_id = ei.user_id
        {$where_clause}
        ORDER BY u.created_at DESC
        LIMIT " . intval($per_page) . " OFFSET " . intval($offset);
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$sections = $pdo->query("
    SELECT id, section_code, program, year_level,
           CONCAT(section_code, ' - ', program, ' (Year ', year_level, ')') AS section_label
    FROM sections WHERE status = 'active'
")->fetchAll();

$subjects = $pdo->query("SELECT id, subject_code, subject_name, units, description FROM subjects ORDER BY subject_code")->fetchAll();

// Build section-subjects mapping
$sectionSubjectsMap = [];
$stmt = $pdo->query("
    SELECT ss.section_id, s.id, s.subject_code, s.subject_name, s.units, s.description
    FROM subject_sections ss
    JOIN subjects s ON ss.subject_id = s.id
");
while ($row = $stmt->fetch()) {
    $sectionSubjectsMap[$row['section_id']][] = [
        'id' => $row['id'],
        'subject_code' => $row['subject_code'],
        'subject_name' => $row['subject_name'],
        'units' => $row['units'],
        'description' => $row['description']
    ];
}

// student -> section map
$studentSectionMap = [];
$stmt = $pdo->query("SELECT student_id, section_id FROM student_sections");
while ($row = $stmt->fetch()) {
    $studentSectionMap[$row['student_id']][] = $row['section_id'];
}

// student -> subject map (from student_subjects)
$studentSubjectsMap = [];
$stmt = $pdo->query("SELECT student_id, subject_id FROM student_subjects WHERE status = 'active'");
while ($row = $stmt->fetch()) {
    if (!isset($studentSubjectsMap[$row['student_id']])) {
        $studentSubjectsMap[$row['student_id']] = [];
    }
    $studentSubjectsMap[$row['student_id']][] = $row['subject_id'];
}

$start_number = $offset + 1;
renderPageStart('Manage Users', 'admin', 'manage_users.php');
?>

<style>
.section-card { border: 2px solid #e9ecef; border-radius: 10px; transition: all 0.3s ease; margin-bottom: 20px; }
.section-card:hover { border-color: #007bff; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
.section-card.selected { border-color: #28a745; background-color: #f8fff9; }
.section-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 20px; border-radius: 8px 8px 0 0; }
.subject-list { padding: 20px; max-height: 400px; overflow-y: auto; }
.subject-item { border: 1px solid #e9ecef; border-radius: 5px; padding: 12px; margin-bottom: 10px; background: white; transition: all 0.3s ease; }
.subject-item:hover { border-color: #007bff; background: #f8f9fa; }
.subject-item.selected { border-color: #28a745; background: #f0fff4; }
.student-info-card { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; border: none; }
.checkbox-group { max-height: 300px; overflow-y: auto; }
.section-checkbox-item { border: 1px solid #dee2e6; border-radius: 5px; padding: 12px; margin-bottom: 10px; transition: all 0.3s ease; }
.section-checkbox-item:hover { border-color: #007bff; background: #f8f9fa; }
.section-checkbox-item.selected { border-color: #28a745; background: #f0fff4; }
.btn-group .btn { margin-right: 2px; }
.modal-subject-item { border: 1px solid #dee2e6; border-radius: 5px; padding: 10px; margin-bottom: 8px; transition: all 0.2s ease; }
.modal-subject-item:hover { background-color: #f8f9fa; }
.modal-subject-item.selected { border-color: #28a745; background-color: #f0fff4; }
.user-details-modal .modal-xl { max-width: 1200px; }
.user-details-card { border: none; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: transform 0.2s; }
.user-details-card:hover { transform: translateY(-2px); }
.user-details-header { border-radius: 10px 10px 0 0; padding: 1.5rem; }
.user-avatar { width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: bold; margin: 0 auto; }
.details-table th { width: 30%; font-weight: 600; }
.activity-log-item { border-left: 3px solid #007bff; padding-left: 15px; margin-bottom: 10px; }
.activity-log-item:hover { background-color: #f8f9fa; }
.pagination { margin-bottom: 0; }
.page-item.active .page-link { background-color: #0d6efd; border-color: #0d6efd; }
.table th:first-child, .table td:first-child { text-align: center; width: 60px; font-weight: 600; }
#assessmentTable th { background-color: #f8f9fa; font-weight: 600; }
#assessmentTable tfoot { background-color: #f8f9fa; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Manage Users</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal"><i class="fas fa-plus"></i> Add User</button>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-header bg-light"><h6 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Users</h6></div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-2">
                <label for="role" class="form-label">Role</label>
                <select class="form-select" id="role" name="role">
                    <option value="">All Roles</option>
                    <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="cashier" <?= $role_filter === 'cashier' ? 'selected' : '' ?>>Cashier</option>
                    <option value="student" <?= $role_filter === 'student' ? 'selected' : '' ?>>Student</option>
                    <option value="registrar" <?= $role_filter === 'registrar' ? 'selected' : '' ?>>Registrar</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All Status</option>
                    <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="locked" <?= $status_filter === 'locked' ? 'selected' : '' ?>>Locked</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="program_filter" class="form-label">Program (Student only)</label>
                <select class="form-select" id="program_filter" name="program_filter">
                    <option value="">All Programs</option>
                    <?php foreach($programs as $prog): ?>
                        <option value="<?= htmlspecialchars($prog) ?>" <?= ($_GET['program_filter'] ?? '') === $prog ? 'selected' : '' ?>><?= htmlspecialchars($prog) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="year_filter" class="form-label">Year Level</label>
                <select class="form-select" id="year_filter" name="year_filter">
                    <option value="">All Years</option>
                    <?php for($i=1;$i<=6;$i++): ?>
                        <option value="<?= $i ?>" <?= ($_GET['year_filter'] ?? '') == $i ? 'selected' : '' ?>>Year <?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="student_type_filter" class="form-label">Student Type</label>
                <select class="form-select" id="student_type_filter" name="student_type_filter">
                    <option value="">All Types</option>
                    <option value="regular" <?= ($_GET['student_type_filter'] ?? '') === 'regular' ? 'selected' : '' ?>>Regular</option>
                    <option value="irregular" <?= ($_GET['student_type_filter'] ?? '') === 'irregular' ? 'selected' : '' ?>>Irregular</option>
                </select>
            </div>
            <div class="col-md-12">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" class="form-control" id="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by Name, Email, or User ID...">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search me-1"></i> Filter</button>
                    <button type="button" class="btn btn-secondary" onclick="clearFilters()"><i class="fas fa-undo me-1"></i> Clear</button>
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
            <span class="text-muted">Showing <?= $start_number ?>-<?= min($start_number+count($users)-1, $total_records) ?> of <?= $total_records ?> users (Page <?= $page ?> of <?= $total_pages ?>)</span>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>No.</th><th>User ID</th><th>Name</th><th>Email</th><th>Role</th><th>Additional Info</th><th>Student Type</th><th>Status</th><th>Last Active</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php $counter = $start_number; foreach($users as $user): ?>
                    <?php 
                    $current_section_ids = $studentSectionMap[$user['user_id']] ?? [];
                    $current_subject_ids = $studentSubjectsMap[$user['user_id']] ?? [];
                    $student_type = $user['student_type'] ?? 'regular';
                    ?>
                    <tr>
                        <td><?= $counter++ ?></td>
                        <td><code><?= htmlspecialchars($user['user_id']) ?></code></td>
                        <td><?= htmlspecialchars($user['name']) ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td><span class="badge bg-info"><?= ucfirst($user['role']) ?></span></td>
                        <td>
                            <?php if($user['role'] === 'student' && $user['additional_info']): ?>
                                <?= htmlspecialchars($user['additional_info']) ?><?= $user['year_level'] ? ' - Year ' . $user['year_level'] : '' ?>
                            <?php elseif($user['additional_info']): ?>
                                <?= htmlspecialchars($user['additional_info']) ?>
                            <?php else: ?>-<?php endif; ?>
                        </td>
                        <td>
                            <?php if($user['role'] === 'student'): ?>
                                <span class="badge bg-<?= $student_type === 'regular' ? 'success' : 'warning' ?>"><?= ucfirst($student_type) ?></span>
                            <?php else: ?>-<?php endif; ?>
                        </td>
                        <td><span class="badge bg-<?= $user['user_status'] === 'active' ? 'success' : 'danger' ?>"><?= ucfirst($user['user_status']) ?></span></td>
                        <td><?= $user['last_active'] ? date('M j, Y g:i A', strtotime($user['last_active'])) : 'Never' ?></td>
                        <td>
                            <div class="btn-group">
                                <button class="btn btn-sm btn-outline-info" onclick="viewUserDetails('<?= htmlspecialchars($user['user_id']) ?>')"><i class="fas fa-eye"></i></button>
                                <button class="btn btn-sm btn-outline-warning" onclick="openEditModal('<?= htmlspecialchars($user['user_id']) ?>', '<?= htmlspecialchars($user['name']) ?>', '<?= htmlspecialchars($user['email']) ?>', '<?= $user['role'] ?>', '<?= htmlspecialchars($user['additional_info'] ?? '') ?>', '<?= $user['year_level'] ?>')"><i class="fas fa-edit"></i></button>
                                <?php if($user['role'] === 'student'): ?>
                                    <button class="btn btn-sm btn-outline-info" onclick="viewAssessment('<?= htmlspecialchars($user['user_id']) ?>', '<?= htmlspecialchars($user['name']) ?>')"><i class="fas fa-file-invoice-dollar"></i></button>
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#assignModal" onclick="prepareAssignModal('<?= htmlspecialchars($user['user_id']) ?>', '<?= htmlspecialchars($user['name']) ?>', '<?= $student_type ?>', <?= json_encode($current_section_ids) ?>, <?= json_encode($current_subject_ids) ?>)"><i class="fas fa-layer-group"></i></button>
                                <?php endif; ?>
                                <button class="btn btn-sm btn-<?= $user['user_status'] === 'active' ? 'warning' : 'success' ?>" onclick="toggleStatus('<?= htmlspecialchars($user['user_id']) ?>', '<?= htmlspecialchars($user['name']) ?>', '<?= $user['user_status'] ?>')"><i class="fas fa-<?= $user['user_status'] === 'active' ? 'lock' : 'unlock' ?>"></i></button>
                                <?php if($user['user_id'] !== $_SESSION['user_id']): ?>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteUser('<?= htmlspecialchars($user['user_id']) ?>', '<?= htmlspecialchars($user['name']) ?>')"><i class="fas fa-trash"></i></button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if(empty($users)): ?>
                    <tr><td colspan="10" class="text-center py-4"><i class="fas fa-users fa-2x text-muted mb-3"></i><h5>No users found</h5><p class="text-muted">Try adjusting your filters or add a new user.</p></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if($total_pages > 1): ?>
        <nav><ul class="pagination justify-content-center">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page-1])) ?>">&laquo;</a></li>
            <?php for($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                <li class="page-item <?= $i == $page ? 'active' : '' ?>"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a></li>
            <?php endfor; ?>
            <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page+1])) ?>">&raquo;</a></li>
        </ul></nav>
        <?php endif; ?>
    </div>
</div>

<!-- Assessment Modal -->
<div class="modal fade" id="assessmentModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-info text-white"><h5 class="modal-title"><i class="fas fa-file-invoice-dollar me-2"></i>Student Assessment</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row mb-4"><div class="col-12"><div class="card"><div class="card-header bg-light"><h6 class="card-title mb-0">Student Information</h6></div><div class="card-body"><div class="row"><div class="col-md-3"><strong>Student ID:</strong> <span id="assessment_student_id"></span></div><div class="col-md-3"><strong>Name:</strong> <span id="assessment_student_name"></span></div><div class="col-md-3"><strong>Program:</strong> <span id="assessment_program"></span></div><div class="col-md-3"><strong>Year Level:</strong> <span id="assessment_year_level"></span></div></div></div></div></div></div>
                <div class="row mb-4"><div class="col-12"><div class="card"><div class="card-header bg-light d-flex justify-content-between"><h6 class="card-title mb-0">Assessment Details</h6><div><span class="badge bg-primary me-2" id="assessment_semester"></span><span class="badge bg-secondary" id="assessment_school_year"></span></div></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-striped mb-0" id="assessmentTable"><thead class="table-light"><tr><th>Code</th><th>Subject Name</th><th class="text-center">Units</th><th class="text-end">Amount/Unit</th><th class="text-end">Total</th></tr></thead><tbody id="assessmentTableBody"></tbody><tfoot id="assessmentTableFooter"></tfoot></tr></div></div></div></div></div>
                <div class="row"><div class="col-md-6"><div class="card"><div class="card-header bg-light"><h6 class="card-title mb-0">Financial Summary</h6></div><div class="card-body"><div class="row"><div class="col-6">Total Units:</div><div class="col-6 text-end" id="total_units">0</div></div><div class="row"><div class="col-6">Tuition Fee:</div><div class="col-6 text-end" id="tuition_fee">₱0.00</div></div><div class="row"><div class="col-6">Miscellaneous Fees:</div><div class="col-6 text-end" id="misc_fees">₱0.00</div></div><hr><div class="row"><div class="col-6"><strong>Total Assessment:</strong></div><div class="col-6 text-end fw-bold" id="total_assessment">₱0.00</div></div><div class="row"><div class="col-6">Payments Made:</div><div class="col-6 text-end text-success" id="payments_made">₱0.00</div></div><div class="row"><div class="col-6"><strong>Remaining Balance:</strong></div><div class="col-6 text-end text-danger fw-bold" id="remaining_balance">₱0.00</div></div></div></div></div>
                    <div class="col-md-6"><div class="card"><div class="card-header bg-light"><h6 class="card-title mb-0">Payment History</h6></div><div class="card-body"><div class="table-responsive" style="max-height:200px"><table class="table table-sm"><thead><tr><th>Date</th><th>Permit #</th><th class="text-end">Amount</th><th class="text-end">Balance</th></tr></thead><tbody id="paymentHistoryBody"></tbody></table></div><div id="noPaymentHistory" class="text-center text-muted mt-3" style="display:none">No payment history found</div></div></div></div></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button type="button" class="btn btn-primary" onclick="printAssessment()"><i class="fas fa-print me-2"></i>Print Assessment</button></div>
        </div>
    </div>
</div>

<!-- User Details Modal -->
<div class="modal fade user-details-modal" id="userDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white"><h5 class="modal-title"><i class="fas fa-user me-2"></i><span id="modalUserName"></span></h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><div id="userDetailsContent"><div class="text-center py-5"><div class="spinner-border text-primary"></div><p class="mt-3">Loading user details...</p></div></div></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="addUserForm" onsubmit="return validateStudentID()">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>"><input type="hidden" name="action" value="add_user">
                <div class="modal-header bg-primary text-white"><h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add New User</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3"><label for="name" class="form-label">Full Name</label><input type="text" class="form-control" id="name" name="name" required></div>
                        <div class="col-md-6 mb-3"><label for="email" class="form-label">Email</label><input type="email" class="form-control" id="email" name="email" required></div>
                        <div class="col-md-6 mb-3"><label for="role_select" class="form-label">Role</label><select class="form-select" id="role_select" name="role" required onchange="toggleStudentFields()"><option value="">Select Role</option><option value="admin">Admin</option><option value="cashier">Cashier</option><option value="student">Student</option><option value="registrar">Registrar</option></select></div>
                        <div class="col-md-6 mb-3"><label for="password" class="form-label">Password</label><input type="password" class="form-control" id="password" name="password" minlength="6" required><div class="form-text">Min 6 chars</div></div>
                        <div id="studentFields" style="display:none;">
                            <div class="col-md-12 mb-3"><label class="form-label">Student ID</label><div class="row g-2"><div class="col-auto"><div class="input-group"><span class="input-group-text bg-light"><strong>C</strong></span><input type="text" class="form-control" id="student_year" placeholder="YY" maxlength="2" style="width:70px" oninput="updateStudentID()"></div></div><div class="col-auto">-</div><div class="col-auto"><input type="text" class="form-control" id="student_program_code" placeholder="PP" maxlength="2" style="width:70px" oninput="updateStudentIDFromCode()"></div><div class="col-auto">-</div><div class="col-auto"><input type="text" class="form-control" id="student_number" placeholder="NNNN" maxlength="4" style="width:100px" oninput="updateStudentID()"></div><div class="col-auto">-MAN121</div></div><input type="hidden" id="student_id" name="student_id"><div class="form-text mt-2">Format: <strong>CYY-PP-NNNN-MAN121</strong> | You can either enter the program code or select from dropdown below</div><div class="invalid-feedback" id="student_id_error">Invalid Student ID. Please fill all fields correctly.</div></div>
                            <div class="row"><div class="col-md-6 mb-3"><label for="program" class="form-label">Program</label><select class="form-select" id="program" name="program" onchange="updateProgramCodeFromSelect()"><option value="">Select Program (or enter code above)</option><?php foreach($programs as $p): ?><option value="<?= htmlspecialchars($p) ?>" data-code="<?= $program_codes[$p] ?? '00' ?>"><?= htmlspecialchars($p) ?> (Code: <?= $program_codes[$p] ?? '00' ?>)</option><?php endforeach; ?></select></div><div class="col-md-6 mb-3"><label for="year_level" class="form-label">Year Level</label><select class="form-select" id="year_level" name="year_level" required><option value="">Select Year Level</option><?php for($i=1;$i<=6;$i++): ?><option value="<?= $i ?>">Year <?= $i ?></option><?php endfor; ?></select></div></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add User</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="editUserForm">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>"><input type="hidden" name="action" value="edit_user"><input type="hidden" name="edit_user_id" id="edit_user_id"><input type="hidden" name="edit_user_role" id="edit_user_role">
                <div class="modal-header bg-warning"><h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="alert alert-info"><i class="fas fa-info-circle me-2"></i><strong>Note:</strong> Changing the User ID will update it across all system records.</div>
                    <div class="row"><div class="col-md-6 mb-3"><label for="edit_name" class="form-label">Full Name</label><input type="text" class="form-control" id="edit_name" name="name" required></div><div class="col-md-6 mb-3"><label for="edit_email" class="form-label">Email</label><input type="email" class="form-control" id="edit_email" name="email" required></div></div>
                    <div id="editStudentFields" style="display:none;">
                        <div class="col-md-12 mb-3"><label class="form-label">Student ID</label><div class="row g-2"><div class="col-auto"><div class="input-group"><span class="input-group-text bg-light"><strong>C</strong></span><input type="text" class="form-control" id="edit_student_year" placeholder="YY" maxlength="2" style="width:70px"></div></div><div class="col-auto">-</div><div class="col-auto"><input type="text" class="form-control" id="edit_student_program_code" placeholder="PP" maxlength="2" style="width:70px"></div><div class="col-auto">-</div><div class="col-auto"><input type="text" class="form-control" id="edit_student_number" placeholder="NNNN" maxlength="4" style="width:100px"></div><div class="col-auto">-MAN121</div></div><input type="hidden" id="edit_student_id" name="student_id"><div class="form-text mt-2">Format: <strong>CYY-PP-NNNN-MAN121</strong><br><span class="text-warning">Changing this will update the ID across all system records.</span></div></div>
                        <div class="row"><div class="col-md-6 mb-3"><label for="edit_program" class="form-label">Program</label><select class="form-select" id="edit_program" name="program"><option value="">Select Program</option><?php foreach($programs as $p): ?><option value="<?= htmlspecialchars($p) ?>" data-code="<?= $program_codes[$p] ?? '00' ?>"><?= htmlspecialchars($p) ?> (Code: <?= $program_codes[$p] ?? '00' ?>)</option><?php endforeach; ?></select></div><div class="col-md-6 mb-3"><label for="edit_year_level" class="form-label">Year Level</label><select class="form-select" id="edit_year_level" name="year_level"><option value="">Select Year Level</option><?php for($i=1;$i<=6;$i++): ?><option value="<?= $i ?>">Year <?= $i ?></option><?php endfor; ?></select></div></div>
                    </div>
                    <div class="row mt-3"><div class="col-12"><hr><h6>Reset Password</h6><div class="row"><div class="col-md-8 mb-3"><label for="edit_new_password" class="form-label">New Password</label><input type="password" class="form-control" id="edit_new_password" name="new_password" minlength="6" placeholder="Leave blank to keep current"><div class="form-text">Minimum 6 characters</div></div><div class="col-md-4 mb-3 d-flex align-items-end"><button type="button" class="btn btn-info w-100" onclick="resetPasswordFromEdit()"><i class="fas fa-key"></i> Reset Password</button></div></div></div></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-warning"><i class="fas fa-save"></i> Update User</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Assign Sections Modal -->
<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form method="POST" id="assignSectionForm">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>"><input type="hidden" name="action" value="assign_section"><input type="hidden" name="student_id" id="assign_section_student_id">
                <div class="modal-header bg-success text-white"><h5 class="modal-title"><i class="fas fa-layer-group me-2"></i>Assign Sections & Subjects</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="row mb-4"><div class="col-12"><div class="card student-info-card"><div class="card-body py-3"><div class="row"><div class="col-md-8"><h5 class="card-title mb-1" id="assign_student_name"></h5><p class="card-text mb-0"><strong>Student ID:</strong> <span id="assign_student_id"></span> | <strong>Current Status:</strong> <span id="assign_student_type"></span></p></div></div></div></div></div></div>
                    <div class="row">
                        <div class="col-lg-4"><div class="card"><div class="card-header bg-primary text-white"><h6 class="card-title mb-0"><i class="fas fa-layer-group me-2"></i>Select Sections</h6></div><div class="card-body p-0"><div class="checkbox-group" id="sectionsContainer" style="max-height:400px;overflow-y:auto"><?php foreach($sections as $section): ?><div class="section-checkbox-item m-2"><div class="form-check"><input class="form-check-input section-checkbox" type="checkbox" name="section_ids[]" value="<?= $section['id'] ?>" id="section_<?= $section['id'] ?>"><label class="form-check-label fw-bold" for="section_<?= $section['id'] ?>"><?= htmlspecialchars($section['section_label']) ?></label><div class="text-muted small mt-1">Subjects: <?= count($sectionSubjectsMap[$section['id']] ?? []) ?></div></div></div><?php endforeach; ?></div></div></div></div>
                        <div class="col-lg-8"><div class="card"><div class="card-header bg-info text-white"><h6 class="card-title mb-0"><i class="fas fa-book me-2"></i>Select Subjects from Chosen Sections</h6></div><div class="card-body"><div class="mb-3"><div class="form-check"><input class="form-check-input" type="checkbox" id="selectAllSubjects"><label class="form-check-label fw-bold" for="selectAllSubjects">Select All Subjects from Selected Sections</label></div></div><div id="subjectsContainer" style="max-height:300px;overflow-y:auto"><div class="text-center py-5 text-muted"><i class="fas fa-book-open fa-3x mb-3"></i><h5>No Sections Selected</h5><p>Please select sections from the left panel to view available subjects.</p></div></div></div></div><div class="card mt-4"><div class="card-body"><div class="row"><div class="col-md-6"><h6>Assignment Summary</h6><div id="summaryInfo"><p class="text-muted">Select sections and subjects to see summary</p></div></div></div></div></div></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-success"><i class="fas fa-save me-2"></i>Save Assignments</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>"><input type="hidden" name="action" value="reset_password"><input type="hidden" name="reset_user_id" id="reset_user_id">
                <div class="modal-header"><h5 class="modal-title">Reset Password</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body"><div class="alert alert-warning"><h6 class="alert-heading">Reset Password for: <span id="reset_user_name"></span></h6><p class="mb-0">The user will need to use the new password to log in.</p></div><div class="mb-3"><label for="new_password" class="form-label">New Password</label><input type="password" class="form-control" id="new_password" name="new_password" minlength="6" required><div class="form-text">Minimum 6 characters</div></div></div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-warning">Reset Password</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Hidden forms -->
<form method="POST" id="statusForm" style="display:none;"><input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>"><input type="hidden" name="action" value="toggle_status"><input type="hidden" name="user_id" id="status_user_id"></form>
<form method="POST" id="deleteForm" style="display:none;"><input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>"><input type="hidden" name="action" value="delete_user"><input type="hidden" name="delete_user_id" id="delete_user_id"></form>

<script>
const allSubjects = <?= json_encode($subjects) ?>;
const sectionSubjectsMap = <?= json_encode($sectionSubjectsMap) ?>;
const sectionDetailsMap = <?= json_encode($sections) ?>;
let currentSectionSelections = [];
let currentSubjectSelections = [];

function prepareAssignModal(studentId, studentName, studentType, currentSectionIds, currentSubjectIds) {
    document.getElementById('assign_section_student_id').value = studentId;
    document.getElementById('assign_student_id').textContent = studentId;
    document.getElementById('assign_student_name').textContent = studentName;
    document.getElementById('assign_student_type').innerHTML = `<span class="badge bg-${studentType === 'regular' ? 'success' : 'warning'}">${studentType}</span>`;
    
    currentSectionSelections = currentSectionIds.map(id => id.toString());
    currentSubjectSelections = currentSubjectIds ? currentSubjectIds.map(id => id.toString()) : [];
    
    document.querySelectorAll('.section-checkbox').forEach(checkbox => {
        const sectionId = checkbox.value;
        checkbox.checked = currentSectionSelections.includes(sectionId);
        const sectionItem = checkbox.closest('.section-checkbox-item');
        if (sectionItem) {
            if (checkbox.checked) {
                sectionItem.classList.add('selected');
            } else {
                sectionItem.classList.remove('selected');
            }
        }
    });
    
    loadSubjectsForSections();
}

function loadSubjectsForSections() {
    const selectedSections = Array.from(document.querySelectorAll('.section-checkbox:checked')).map(cb => cb.value);
    const subjectsContainer = document.getElementById('subjectsContainer');

    document.querySelectorAll('.section-checkbox-item').forEach(item => {
        const checkbox = item.querySelector('.section-checkbox');
        if (checkbox && checkbox.checked) {
            item.classList.add('selected');
        } else {
            item.classList.remove('selected');
        }
    });

    if (selectedSections.length === 0) {
        subjectsContainer.innerHTML = `<div class="text-center py-5 text-muted"><i class="fas fa-book-open fa-3x mb-3"></i><h5>No Sections Selected</h5><p>Please select sections from the left panel to view available subjects.</p></div>`;
        updateSummary();
        return;
    }

    let html = '';
    let hasSubjects = true;

    selectedSections.forEach(sectionId => {
        const section = sectionDetailsMap.find(s => s.id == sectionId);
        const subjectsInSection = sectionSubjectsMap[sectionId] || [];
        
        if (subjectsInSection.length > 0) {
            hasSubjects = true;
            html += `<div class="mb-3"><h6 class="fw-bold text-primary"><i class="fas fa-layer-group me-2"></i>${section ? section.section_label : 'Section ' + sectionId}<button type="button" class="btn btn-sm btn-outline-primary float-end select-all-section-btn" data-section-id="${sectionId}"><i class="fas fa-check-double me-1"></i>Select All</button></h6><div class="row">`;
            
            subjectsInSection.forEach(subject => {
                const isChecked = currentSubjectSelections.includes(subject.id.toString());
                html += `<div class="col-md-6 mb-2"><div class="modal-subject-item ${isChecked ? 'selected' : ''}"><div class="form-check"><input class="form-check-input subject-checkbox" type="checkbox" name="subject_ids[]" value="${subject.id}" id="subject_${subject.id}_${sectionId}" data-section-id="${sectionId}" ${isChecked ? 'checked' : ''}><label class="form-check-label fw-bold" for="subject_${subject.id}_${sectionId}">${subject.subject_code}</label><div class="text-muted small">${subject.subject_name}</div><div class="d-flex justify-content-between align-items-center mt-1"><span class="badge bg-primary">${subject.units} units</span>${subject.description ? `<button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="tooltip" title="${subject.description.replace(/'/g, "\\'")}"><i class="fas fa-info-circle"></i></button>` : ''}</div></div></div></div>`;
            });
            
            html += `</div></div><hr>`;
        }
    });

    if (!hasSubjects) {
        html = '<div class="alert alert-info">No subjects available for selected sections.</div>';
    }

    subjectsContainer.innerHTML = html;
    updateSummary();
    
    reinitializeEventListeners();
}

function updateSummary() {
    const selectedSections = Array.from(document.querySelectorAll('.section-checkbox:checked')).length;
    const selectedSubjects = Array.from(document.querySelectorAll('.subject-checkbox:checked')).length;
    const studentType = selectedSections > 1 ? 'irregular' : 'regular';
    const summaryHtml = selectedSections === 0 ? '<p class="text-muted">Select sections and subjects to see summary</p>' :
        `<div class="mb-2"><strong>Sections Selected:</strong> <span class="badge bg-primary">${selectedSections}</span></div>
         <div class="mb-2"><strong>Subjects Selected:</strong> <span class="badge bg-success">${selectedSubjects}</span></div>
         <div class="mb-2"><strong>Student Type:</strong> <span class="badge bg-${studentType === 'regular' ? 'success' : 'warning'}">${studentType}</span></div>`;
    document.getElementById('summaryInfo').innerHTML = summaryHtml;
}

function reinitializeEventListeners() {
    document.querySelectorAll('.subject-checkbox').forEach(checkbox => {
        checkbox.removeEventListener('change', handleSubjectChange);
        checkbox.addEventListener('change', handleSubjectChange);
    });
    
    document.querySelectorAll('.select-all-section-btn').forEach(button => {
        button.removeEventListener('click', handleSelectAll);
        button.addEventListener('click', handleSelectAll);
    });
}

function handleSubjectChange() {
    const subjectItem = this.closest('.modal-subject-item');
    if (subjectItem) {
        if (this.checked) {
            subjectItem.classList.add('selected');
        } else {
            subjectItem.classList.remove('selected');
        }
    }
    updateSummary();
}

function handleSelectAll(e) {
    const sectionId = this.dataset.sectionId;
    const sectionSubjects = document.querySelectorAll(`.subject-checkbox[data-section-id="${sectionId}"]`);
    const allChecked = Array.from(sectionSubjects).every(cb => cb.checked);
    sectionSubjects.forEach(cb => {
        cb.checked = !allChecked;
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
    updateSummary();
}
async function fetchAssessmentData(userId) {
    try {
        document.getElementById('assessmentTableBody').innerHTML = `<tr><td colspan="5" class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-2">Loading assessment details...</p></td></tr>`;
        
        const response = await fetch(`ajax_handler.php?action=get_assessment_file&student_id=${encodeURIComponent(userId)}`);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        const assessmentData = await response.json();
        if (!assessmentData.success) throw new Error(assessmentData.message || 'Failed to fetch assessment data');
        
        const studentInfo = assessmentData.assessment_info || {};
        const academicInfo = assessmentData.academic_info || {};
        const subjects = assessmentData.subjects || [];
        const financialSummary = assessmentData.financial_summary || {};
        
        document.getElementById('assessment_program').textContent = studentInfo.program || 'N/A';
        document.getElementById('assessment_year_level').textContent = studentInfo.year_level || 'N/A';
        document.getElementById('assessment_semester').textContent = academicInfo.current_semester || '1st Semester';
        document.getElementById('assessment_school_year').textContent = academicInfo.school_year || '2024-2025';
        
        const tableBody = document.getElementById('assessmentTableBody');
        tableBody.innerHTML = '';
        let totalUnits = 0, tuitionFee = 0;
        
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
            tableBody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-muted"><i class="fas fa-book fa-2x mb-3"></i><p>No subjects enrolled for this semester</p></td></tr>`;
        }
        
        const miscFees = assessmentData.fees_breakdown?.miscellaneous_fees?.amount || assessmentData.fees_breakdown?.total_fees || 0;
        const totalAssessment = financialSummary.total_assessment || (tuitionFee + miscFees);
        const paymentsMade = financialSummary.payments_made || 0;
        let remainingBalance = financialSummary.remaining_balance !== undefined ? financialSummary.remaining_balance : (totalAssessment - paymentsMade);
        
        document.getElementById('total_units').textContent = totalUnits;
        document.getElementById('tuition_fee').textContent = `₱${tuitionFee.toLocaleString('en-US', {minimumFractionDigits: 2})}`;
        document.getElementById('misc_fees').textContent = `₱${miscFees.toLocaleString('en-US', {minimumFractionDigits: 2})}`;
        document.getElementById('total_assessment').textContent = `₱${parseFloat(totalAssessment).toLocaleString('en-US', {minimumFractionDigits: 2})}`;
        document.getElementById('payments_made').textContent = `₱${parseFloat(paymentsMade).toLocaleString('en-US', {minimumFractionDigits: 2})}`;
        if (remainingBalance < 0) remainingBalance = 0;
        document.getElementById('remaining_balance').textContent = `₱${remainingBalance.toLocaleString('en-US', {minimumFractionDigits: 2})}`;
        
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
        document.getElementById('assessmentTableBody').innerHTML = `<tr><td colspan="5" class="text-center py-4 text-danger"><i class="fas fa-exclamation-triangle fa-2x mb-3"></i><p>Error loading assessment: ${error.message}</p><button onclick="fetchAssessmentData('${userId}')" class="btn btn-sm btn-primary mt-2">Retry</button></td></tr>`;
        throw error;
    }
}

function viewAssessment(userId, userName) {
    document.getElementById('assessment_student_id').textContent = userId;
    document.getElementById('assessment_student_name').textContent = userName;
    document.getElementById('total_units').textContent = '0';
    document.getElementById('tuition_fee').textContent = '₱0.00';
    document.getElementById('misc_fees').textContent = '₱0.00';
    document.getElementById('total_assessment').textContent = '₱0.00';
    document.getElementById('payments_made').textContent = '₱0.00';
    document.getElementById('remaining_balance').textContent = '₱0.00';
    document.getElementById('paymentHistoryBody').innerHTML = '';
    document.getElementById('noPaymentHistory').style.display = 'block';
    const modal = new bootstrap.Modal(document.getElementById('assessmentModal'));
    modal.show();
    fetchAssessmentData(userId).catch(err => console.error('Assessment fetch failed:', err));
}

function printAssessment() {
    const printWindow = window.open('', '_blank');
    const studentId = document.getElementById('assessment_student_id').textContent;
    const studentName = document.getElementById('assessment_student_name').textContent;
    const program = document.getElementById('assessment_program').textContent;
    const yearLevel = document.getElementById('assessment_year_level').textContent;
    const semester = document.getElementById('assessment_semester').textContent;
    const schoolYear = document.getElementById('assessment_school_year').textContent;
    
    const tableRows = Array.from(document.querySelectorAll('#assessmentTableBody tr')).map(row => {
        const cells = row.querySelectorAll('td');
        if (cells.length >= 5) {
            return {
                code: cells[0]?.textContent || '',
                name: cells[1]?.textContent || '',
                units: cells[2]?.textContent || '',
                unitPrice: cells[3]?.textContent || '',
                total: cells[4]?.textContent || ''
            };
        }
        return null;
    }).filter(row => row !== null);
    
    const totalUnits = document.getElementById('total_units').textContent;
    const tuitionFee = document.getElementById('tuition_fee').textContent;
    const miscFees = document.getElementById('misc_fees').textContent;
    const totalAssessment = document.getElementById('total_assessment').textContent;
    const paymentsMade = document.getElementById('payments_made').textContent;
    const remainingBalance = document.getElementById('remaining_balance').textContent;
    
    const printContent = `<!DOCTYPE html>
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
            @media print { body { margin: 0; } .no-print { display: none; } }
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
            <button onclick="window.print()" style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;">Print Document</button>
        </div>
        
        <script>
            window.onload = function() { window.print(); };
        <\/script>
    </body>
    </html>`;
    
    printWindow.document.write(printContent);
    printWindow.document.close();
}

function viewUserDetails(userId) {
    document.getElementById('modalUserName').textContent = 'Loading...';
    document.getElementById('userDetailsContent').innerHTML = `<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-3">Loading user details...</p></div>`;
    const modal = new bootstrap.Modal(document.getElementById('userDetailsModal'));
    modal.show();
    fetch(`ajax_handler.php?action=get_user_details&user_id=${encodeURIComponent(userId)}`).then(response => response.json()).then(data => {
        if (data.success) {
            const html = generateUserDetailsHTML(data.user, data.additional_info);
            document.getElementById('userDetailsContent').innerHTML = html;
            document.getElementById('modalUserName').textContent = data.user.name;
        } else {
            document.getElementById('userDetailsContent').innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> ${data.message}</div>`;
            document.getElementById('modalUserName').textContent = 'Error';
        }
    }).catch(error => {
        console.error('Error:', error);
        document.getElementById('userDetailsContent').innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Error loading user details</div>`;
        document.getElementById('modalUserName').textContent = 'Error';
    });
}

function generateUserDetailsHTML(user, additionalInfo) {
    user = user || {};
    additionalInfo = additionalInfo || {};
    function getInitials(name) { let initials = ''; const words = name.split(' '); for (const word of words) if (word.trim()) initials += word[0].toUpperCase(); return initials.substring(0, 2); }
    function formatDate(dateString) { if (!dateString) return 'Never'; const date = new Date(dateString); return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' }); }
    const statusBadgeColor = user.user_status === 'active' ? 'success' : 'danger';
    const studentTypeBadgeColor = user.student_type === 'regular' ? 'primary' : 'warning';
    const enrollmentBadgeColor = user.enrollment_status === 'enrolled' ? 'success' : (user.enrollment_status === 'dropped' ? 'danger' : 'info');
    let sectionsHTML = '';
    if (additionalInfo.sections && additionalInfo.sections.length > 0) {
        sectionsHTML = `<div class="mb-3"><strong>Assigned Sections (${additionalInfo.sections.length}):</strong><ul class="mt-2 mb-3">${additionalInfo.sections.map(section => `<li>${section.section_code} - ${section.program} (Year ${section.year_level})</li>`).join('')}</ul></div>`;
    } else {
        sectionsHTML = `<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> No section assigned</div>`;
    }
    let subjectsHTML = `<div class="alert alert-info"><i class="fas fa-info-circle"></i> No subjects enrolled (subjects are derived from sections)</div>`;
    let paymentSummaryHTML = '';
    if (additionalInfo.payment_summary && additionalInfo.payment_summary.total_payments > 0) {
        let unpaidAmount = parseFloat(additionalInfo.payment_summary.unpaid_amount) || 0;
        let partialAmount = parseFloat(additionalInfo.payment_summary.partial_amount) || 0;
        let outstandingBalance = unpaidAmount + partialAmount;
        if (outstandingBalance < 0) outstandingBalance = 0;
        
        paymentSummaryHTML = `<div class="row text-center"><div class="col-4 mb-3"><div class="border rounded p-2"><h6 class="text-primary">${additionalInfo.payment_summary.total_payments}</h6><small class="text-muted">Total</small></div></div><div class="col-4 mb-3"><div class="border rounded p-2"><h6 class="text-success">${additionalInfo.payment_summary.paid_count}</h6><small class="text-muted">Paid</small></div></div><div class="col-4 mb-3"><div class="border rounded p-2"><h6 class="text-danger">${additionalInfo.payment_summary.unpaid_count}</h6><small class="text-muted">Unpaid</small></div></div><div class="col-12"><div class="border rounded p-2"><h6 class="text-warning">₱${outstandingBalance.toLocaleString('en-US', {minimumFractionDigits: 2})}</h6><small class="text-muted">Outstanding Balance</small></div></div></div>`;
    } else {
        paymentSummaryHTML = `<div class="alert alert-info"><i class="fas fa-info-circle"></i> No payment records found.</div>`;
    }
    let issuanceSummaryHTML = '';
    if (additionalInfo.issuance_summary) {
        issuanceSummaryHTML = `<div class="row text-center"><div class="col-4"><div class="border rounded p-2"><h6 class="text-primary">${additionalInfo.issuance_summary.total_issued || 0}</h6><small class="text-muted">Total Issued</small></div></div><div class="col-4"><div class="border rounded p-2"><h6 class="text-success">₱${(additionalInfo.issuance_summary.total_amount_issued || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</h6><small class="text-muted">Total Amount</small></div></div><div class="col-4"><div class="border rounded p-2"><h6 class="text-info">${additionalInfo.issuance_summary.unique_students_served || 0}</h6><small class="text-muted">Students Served</small></div></div></div>`;
    }
    let activityLogHTML = '';
    if (additionalInfo.activity_log && additionalInfo.activity_log.length > 0) {
        activityLogHTML = additionalInfo.activity_log.map(activity => `<div class="activity-log-item mb-3"><div class="d-flex justify-content-between"><strong>${activity.action}</strong><small class="text-muted">${formatDate(activity.created_at)}</small></div><div class="text-muted small">${activity.description || ''}</div></div>`).join('');
    } else {
        activityLogHTML = `<div class="alert alert-info"><i class="fas fa-info-circle"></i> No recent activity found.</div>`;
    }
    let studentSpecificHTML = '';
    if (user.role === 'student') {
        studentSpecificHTML = `<div class="row"><div class="col-md-6 mb-4"><div class="card user-details-card"><div class="card-header bg-success text-white"><h6 class="card-title mb-0"><i class="fas fa-graduation-cap me-2"></i>Academic Information</h6></div><div class="card-body">${sectionsHTML}${subjectsHTML}</div></div></div><div class="col-md-6 mb-4"><div class="card user-details-card"><div class="card-header bg-warning text-dark"><h6 class="card-title mb-0"><i class="fas fa-money-bill-wave me-2"></i>Payment Summary</h6></div><div class="card-body">${paymentSummaryHTML}</div></div></div></div>`;
    } else {
        studentSpecificHTML = `<div class="row"><div class="col-md-12 mb-4"><div class="card user-details-card"><div class="card-header bg-secondary text-white"><h6 class="card-title mb-0"><i class="fas fa-chart-bar me-2"></i>Work Summary</h6></div><div class="card-body">${issuanceSummaryHTML}</div></div></div></div>`;
    }
    return `<div class="user-name" style="display:none;">${user.name}</div><div class="row"><div class="col-md-3 mb-4"><div class="card text-center user-details-card"><div class="card-body"><div class="user-avatar mb-3">${getInitials(user.name)}</div><h5 class="card-title">${user.name}</h5><h6 class="card-subtitle mb-2 text-muted"><code>${user.user_id}</code></h6><p class="card-text"><span class="badge bg-info">${user.role}</span><span class="badge bg-${statusBadgeColor} ms-1">${user.user_status}</span></p><div class="mt-3"><button class="btn btn-sm btn-warning w-100 mb-2" onclick="editUserFromDetails('${user.user_id}', '${user.name.replace(/'/g, "\\'")}', '${user.email.replace(/'/g, "\\'")}', '${user.role}', '${(user.additional_info || '').replace(/'/g, "\\'")}', '${user.year_level || ''}')"><i class="fas fa-edit"></i> Edit User</button>${user.user_id !== '<?php echo $_SESSION['user_id']; ?>' ? `<button class="btn btn-sm btn-info w-100 mb-2" onclick="resetPasswordFromDetails('${user.user_id}', '${user.name.replace(/'/g, "\\'")}')"><i class="fas fa-key"></i> Reset Password</button><button class="btn btn-sm btn-${user.user_status === 'active' ? 'warning' : 'success'} w-100 mb-2" onclick="toggleStatus('${user.user_id}', '${user.name.replace(/'/g, "\\'")}', '${user.user_status}')"><i class="fas fa-${user.user_status === 'active' ? 'lock' : 'unlock'}"></i> ${user.user_status === 'active' ? 'Lock' : 'Unlock'} User</button><button class="btn btn-sm btn-danger w-100" onclick="deleteUserFromDetails('${user.user_id}', '${user.name.replace(/'/g, "\\'")}')"><i class="fas fa-trash"></i> Delete User</button>` : ''}</div></div></div></div><div class="col-md-9"><div class="row"><div class="col-md-6 mb-4"><div class="card user-details-card"><div class="card-header bg-primary text-white"><h6 class="card-title mb-0"><i class="fas fa-info-circle me-2"></i>Basic Information</h6></div><div class="card-body"><table class="table table-sm details-table"><tr><th>Email:</th><td>${user.email}</td></tr>${user.role === 'student' ? `<tr><th>Program:</th><td>${user.additional_info || 'Not set'}</td></tr><tr><th>Year Level:</th><td>${user.year_level ? 'Year ' + user.year_level : 'Not Set'}</td></tr><tr><th>Student Type:</th><td><span class="badge bg-${studentTypeBadgeColor}">${user.student_type || 'Not set'}</span></td></tr><tr><th>Enrollment Status:</th><td><span class="badge bg-${enrollmentBadgeColor}">${user.enrollment_status || 'Not set'}</span></td></tr>${user.enrollment_date ? `<tr><th>Enrollment Date:</th><td>${formatDate(user.enrollment_date)}</td></tr>` : ''}${user.contact_number ? `<tr><th>Contact Number:</th><td>${user.contact_number}</td></tr>` : ''}${user.address ? `<tr><th>Address:</th><td>${user.address}</td></tr>` : ''}` : `<tr><th>Position:</th><td>${user.additional_info || 'Not set'}</td></tr>`}</table></div></div></div><div class="col-md-6 mb-4"><div class="card user-details-card"><div class="card-header bg-info text-white"><h6 class="card-title mb-0"><i class="fas fa-history me-2"></i>Account Activity</h6></div><div class="card-body"><table class="table table-sm details-table"><tr><th>Account Created:</th><td>${formatDate(user.created_at)}</td><tr><th>Last Active:</th><td>${formatDate(user.last_active)}</td><tr></table></div></div></div></div>${studentSpecificHTML}<div class="row"><div class="col-md-12"><div class="card user-details-card"><div class="card-header"><h6 class="card-title mb-0"><i class="fas fa-history me-2"></i>Recent Activity</h6></div><div class="card-body" style="max-height:200px;overflow-y:auto">${activityLogHTML}</div></div></div></div></div></div>`;
}

function editUserFromDetails(userId, userName, userEmail, userRole, program, yearLevel) {
    bootstrap.Modal.getInstance(document.getElementById('userDetailsModal')).hide();
    openEditModal(userId, userName, userEmail, userRole, program, yearLevel);
}

function resetPasswordFromDetails(userId, userName) {
    bootstrap.Modal.getInstance(document.getElementById('userDetailsModal')).hide();
    document.getElementById('reset_user_id').value = userId;
    document.getElementById('reset_user_name').textContent = userName;
    document.getElementById('new_password').value = '';
    new bootstrap.Modal(document.getElementById('resetPasswordModal')).show();
}

function deleteUserFromDetails(userId, userName) {
    if (confirm(`Are you sure you want to delete user "${userName}"? This action cannot be undone.`)) {
        bootstrap.Modal.getInstance(document.getElementById('userDetailsModal')).hide();
        document.getElementById('delete_user_id').value = userId;
        document.getElementById('deleteForm').submit();
    }
}

function openEditModal(userId, name, email, role, program, yearLevel) {
    document.getElementById('edit_user_id').value = userId;
    document.getElementById('edit_user_role').value = role;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_email').value = email;
    const editStudentFields = document.getElementById('editStudentFields');
    if (role === 'student') {
        editStudentFields.style.display = 'block';
        document.getElementById('edit_program').value = program || '';
        document.getElementById('edit_year_level').value = yearLevel || '';
        const idPattern = /^C?([0-9]{2})-([0-9]{2})-([0-9]{4})-MAN121$/i;
        const match = userId.match(idPattern);
        if (match) {
            const year = match[1], programCode = match[2], studentNumber = match[3];
            document.getElementById('edit_student_year').value = year;
            document.getElementById('edit_student_program_code').value = programCode;
            document.getElementById('edit_student_number').value = studentNumber;
            document.getElementById('edit_student_id').value = `C${year}-${programCode}-${studentNumber}-MAN121`;
            const programSelect = document.getElementById('edit_program');
            for (let i = 0; i < programSelect.options.length; i++) {
                const option = programSelect.options[i];
                if (option.getAttribute('data-code') === programCode) { programSelect.value = option.value; break; }
            }
        } else {
            document.getElementById('edit_student_year').value = '';
            document.getElementById('edit_student_program_code').value = '';
            document.getElementById('edit_student_number').value = '';
            document.getElementById('edit_student_id').value = userId;
        }
        const updateHiddenID = function() {
            const year = document.getElementById('edit_student_year').value;
            const programCode = document.getElementById('edit_student_program_code').value;
            const number = document.getElementById('edit_student_number').value;
            if (year && programCode && number) document.getElementById('edit_student_id').value = `C${year}-${programCode}-${number}-MAN121`;
        };
        document.getElementById('edit_student_year').oninput = updateHiddenID;
        document.getElementById('edit_student_program_code').oninput = updateHiddenID;
        document.getElementById('edit_student_number').oninput = updateHiddenID;
        document.getElementById('edit_student_program_code').onchange = function() {
            const code = this.value;
            const programSelect = document.getElementById('edit_program');
            for (let i = 0; i < programSelect.options.length; i++) if (programSelect.options[i].getAttribute('data-code') === code) { programSelect.value = programSelect.options[i].value; break; }
        };
        document.getElementById('edit_program').onchange = function() {
            const selectedOption = this.options[this.selectedIndex];
            const code = selectedOption.getAttribute('data-code');
            if (code) { document.getElementById('edit_student_program_code').value = code; updateHiddenID(); }
        };
    } else {
        editStudentFields.style.display = 'none';
    }
    document.getElementById('edit_new_password').value = '';
    new bootstrap.Modal(document.getElementById('editUserModal')).show();
}

function toggleStatus(userId, userName, currentStatus) {
    if (confirm(`Are you sure you want to ${currentStatus === 'active' ? 'lock' : 'unlock'} user ${userName}?`)) {
        document.getElementById('status_user_id').value = userId;
        document.getElementById('statusForm').submit();
    }
}

function deleteUser(userId, userName) {
    if (confirm(`Are you sure you want to delete user ${userName}? This action cannot be undone.`)) {
        document.getElementById('delete_user_id').value = userId;
        document.getElementById('deleteForm').submit();
    }
}

function resetPasswordFromEdit() {
    const userId = document.getElementById('edit_user_id').value;
    const newPassword = document.getElementById('edit_new_password').value;
    if (!newPassword) { alert('Please enter a new password.'); return; }
    if (newPassword.length < 6) { alert('Password must be at least 6 characters.'); return; }
    if (confirm('Are you sure you want to reset the password for this user?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `<input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="action" value="reset_password"><input type="hidden" name="reset_user_id" value="${userId}"><input type="hidden" name="new_password" value="${newPassword}">`;
        document.body.appendChild(form);
        form.submit();
    }
}

function toggleStudentFields() {
    const roleSelect = document.getElementById('role_select');
    const studentFields = document.getElementById('studentFields');
    if (roleSelect.value === 'student') {
        studentFields.style.display = 'block';
        document.getElementById('student_year').required = true;
        document.getElementById('student_program_code').required = true;
        document.getElementById('student_number').required = true;
        document.getElementById('program').required = true;
        document.getElementById('year_level').required = true;
        const currentYear = new Date().getFullYear().toString().slice(-2);
        if (!document.getElementById('student_year').value) { document.getElementById('student_year').value = currentYear; updateStudentID(); }
    } else {
        studentFields.style.display = 'none';
        document.getElementById('student_year').required = false;
        document.getElementById('student_program_code').required = false;
        document.getElementById('student_number').required = false;
        document.getElementById('program').required = false;
        document.getElementById('year_level').required = false;
    }
}

function updateStudentID() {
    const year = document.getElementById('student_year')?.value || '';
    const programCode = document.getElementById('student_program_code')?.value || '';
    const number = document.getElementById('student_number')?.value || '';
    let fullID = '';
    if (year || programCode || number) fullID = `C${year}-${programCode}-${number}-MAN121`;
    document.getElementById('student_id').value = fullID;
    validateStudentIDFormat();
}

function updateStudentIDFromCode() { updateStudentID(); updateProgramCodeFromCode(); }

function updateProgramCodeFromSelect() {
    const programSelect = document.getElementById('program');
    const selectedOption = programSelect.options[programSelect.selectedIndex];
    const programName = selectedOption.value;
    const programCode = { 'BS Information Technology':'01','BS Computer Science':'02','BS Business Administration':'03','BS Accountancy':'04' }[programName];
    if (programCode) {
        const programCodeInput = document.getElementById('student_program_code');
        programCodeInput.value = programCode;
        updateStudentID();
        programCodeInput.style.borderColor = '#28a745';
        programCodeInput.style.backgroundColor = '#f0fff4';
        setTimeout(() => { programCodeInput.style.borderColor = ''; programCodeInput.style.backgroundColor = ''; }, 500);
    }
}

function updateProgramCodeFromCode() {
    const programCode = document.getElementById('student_program_code')?.value || '';
    const programSelect = document.getElementById('program');
    const programCodeMap = { '01':'BS Information Technology','02':'BS Computer Science','03':'BS Business Administration','04':'BS Accountancy' };
    if (programCode && programCodeMap[programCode]) {
        const programName = programCodeMap[programCode];
        for (let i = 0; i < programSelect.options.length; i++) {
            const option = programSelect.options[i];
            if (option.value === programName) {
                programSelect.value = option.value;
                programSelect.style.borderColor = '#28a745';
                programSelect.style.backgroundColor = '#f0fff4';
                setTimeout(() => { programSelect.style.borderColor = ''; programSelect.style.backgroundColor = ''; }, 500);
                break;
            }
        }
    }
}

function validateStudentIDFormat() {
    const year = document.getElementById('student_year')?.value || '';
    const programCode = document.getElementById('student_program_code')?.value || '';
    const number = document.getElementById('student_number')?.value || '';
    const studentIdInput = document.getElementById('student_id');
    let isValid = true, errorMessage = '';
    if (year && !/^\d{2}$/.test(year)) { isValid = false; errorMessage = 'Year must be 2 digits (e.g., 24)'; }
    if (programCode && (!/^\d{2}$/.test(programCode) || parseInt(programCode) < 1 || parseInt(programCode) > 8)) { isValid = false; errorMessage = 'Program code must be 01-08'; }
    if (number && !/^\d{4}$/.test(number)) { isValid = false; errorMessage = 'Student number must be 4 digits (e.g., 0001)'; }
    if (year && programCode && number) { if (!/^C[0-9]{2}-[0-9]{2}-[0-9]{4}-MAN121$/.test(`C${year}-${programCode}-${number}-MAN121`)) { isValid = false; errorMessage = 'Invalid format. Use: CYY-PP-NNNN-MAN121'; } }
    if (!isValid && errorMessage) {
        studentIdInput.classList.add('is-invalid');
        document.getElementById('student_id_error').textContent = errorMessage;
        document.getElementById('student_id_error').style.display = 'block';
        return false;
    } else {
        studentIdInput.classList.remove('is-invalid');
        document.getElementById('student_id_error').style.display = 'none';
        return true;
    }
}

function validateStudentID() {
    const roleSelect = document.getElementById('role_select');
    if (roleSelect.value === 'student') {
        const year = document.getElementById('student_year')?.value || '';
        const programCode = document.getElementById('student_program_code')?.value || '';
        const number = document.getElementById('student_number')?.value || '';
        const program = document.getElementById('program').value;
        const yearLevel = document.getElementById('year_level').value;
        if (!year || !programCode || !number) { alert('Please fill all Student ID fields: Year, Program Code, and Student Number'); return false; }
        if (!/^\d{2}$/.test(year)) { alert('Year must be 2 digits (e.g., 24)'); return false; }
        if (!/^\d{2}$/.test(programCode) || parseInt(programCode) < 1 || parseInt(programCode) > 8) { alert('Program code must be 01-08'); return false; }
        if (!/^\d{4}$/.test(number)) { alert('Student number must be 4 digits (e.g., 0001)'); return false; }
        if (!program) { alert('Please select a program'); return false; }
        if (!yearLevel) { alert('Please select year level'); return false; }
    }
    return true;
}

function clearFilters() {
    fetch('ajax_handler.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'action=log_activity&action_type=Filters Cleared&description=User cleared all filters on manage users page' }).catch(console.error);
    window.location.href = window.location.pathname + '?manage_users.php';
}

document.addEventListener('DOMContentLoaded', function() {
    const currentYear = new Date().getFullYear().toString().slice(-2);
    const yearInput = document.getElementById('student_year');
    if (yearInput && !yearInput.value) yearInput.value = currentYear;
    document.querySelectorAll('.section-checkbox').forEach(checkbox => { checkbox.addEventListener('change', function() { const sectionItem = this.closest('.section-checkbox-item'); if (sectionItem) { if (this.checked) sectionItem.classList.add('selected'); else sectionItem.classList.remove('selected'); } loadSubjectsForSections(); }); });
    document.getElementById('selectAllSubjects')?.addEventListener('change', function() { document.querySelectorAll('.subject-checkbox').forEach(checkbox => { checkbox.checked = this.checked; const subjectItem = checkbox.closest('.modal-subject-item'); if (subjectItem) { if (checkbox.checked) subjectItem.classList.add('selected'); else subjectItem.classList.remove('selected'); } }); updateSummary(); });
    document.getElementById('assignModal')?.addEventListener('hidden.bs.modal', function () { document.getElementById('assignSectionForm').reset(); document.getElementById('subjectsContainer').innerHTML = `<div class="text-center py-5 text-muted"><i class="fas fa-book-open fa-3x mb-3"></i><h5>No Sections Selected</h5><p>Please select sections from the left panel to view available subjects.</p></div>`; document.getElementById('summaryInfo').innerHTML = '<p class="text-muted">Select sections and subjects to see summary</p>'; currentSectionSelections = []; });
    document.getElementById('addUserModal')?.addEventListener('hidden.bs.modal', function () { document.getElementById('addUserForm').reset(); document.getElementById('studentFields').style.display = 'none'; });
    document.getElementById('editUserModal')?.addEventListener('hidden.bs.modal', function () { document.getElementById('editUserForm').reset(); document.getElementById('editStudentFields').style.display = 'none'; });
    document.getElementById('assessmentModal')?.addEventListener('hidden.bs.modal', function () { document.getElementById('assessmentTableBody').innerHTML = ''; document.getElementById('paymentHistoryBody').innerHTML = ''; document.getElementById('noPaymentHistory').style.display = 'none'; });
});
</script>

<?php renderPageEnd(); ?>