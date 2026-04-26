<?php
require_once '../init.php';
require_once '../theme.php';
require_once '../layout.php';

requireRole('registrar');

$pdo = getDBConnection();
$error = '';
$success = '';

// Get programs
$programs = $pdo->query("SELECT DISTINCT program FROM subjects WHERE program IS NOT NULL AND program != '' ORDER BY program")->fetchAll(PDO::FETCH_COLUMN);
if (empty($programs)) {
    $programs = ['BS Information Technology', 'BS Computer Science', 'BS Business Administration', 'BS Accountancy'];
}

// Get all active sections
$sections = $pdo->query("
    SELECT id, section_code, program, year_level,
           CONCAT(section_code, ' - ', program, ' (Year ', year_level, ')') AS section_label
    FROM sections WHERE status = 'active'
    ORDER BY year_level, section_code
")->fetchAll();

// Get all subjects
$subjects = $pdo->query("SELECT id, subject_code, subject_name, units FROM subjects ORDER BY subject_code")->fetchAll();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $action = $_POST['action'] ?? '';
        
        switch($action) {
            case 'enroll_student':
                $student_id = sanitizeInput($_POST['student_id'] ?? '');
                $section_ids = $_POST['section_ids'] ?? [];
                $subject_ids = $_POST['subject_ids'] ?? [];
                $program = sanitizeInput($_POST['program'] ?? '');
                $year_level = intval($_POST['year_level'] ?? 0);
                
                if (empty($student_id)) {
                    $error = 'Student ID is required.';
                } elseif (empty($section_ids)) {
                    $error = 'Please select at least one section.';
                } else {
                    try {
                        $pdo->beginTransaction();
                        
                        // Check if student exists, if not create basic record
                        $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ? AND role = 'student'");
                        $stmt->execute([$student_id]);
                        $student = $stmt->fetch();
                        
                        if (!$student) {
                            $error = 'Student not found. Please add the student first.';
                            $pdo->rollBack();
                        } else {
                            // Update students_info
                            $stmt = $pdo->prepare("UPDATE students_info SET program = ?, year_level = ?, enrollment_status = 'enrolled', enrollment_date = CURDATE() WHERE user_id = ?");
                            $stmt->execute([$program, $year_level, $student_id]);
                            
                            // Clear existing sections and subjects
                            $stmt = $pdo->prepare("DELETE FROM student_sections WHERE student_id = ?");
                            $stmt->execute([$student_id]);
                            
                            $stmt = $pdo->prepare("DELETE FROM student_subjects WHERE student_id = ?");
                            $stmt->execute([$student_id]);
                            
                            // Assign sections
                            $stmt = $pdo->prepare("INSERT INTO student_sections (student_id, section_id) VALUES (?, ?)");
                            foreach ($section_ids as $sid) {
                                $sid = intval($sid);
                                if ($sid > 0) {
                                    $stmt->execute([$student_id, $sid]);
                                }
                            }
                            
                            // Determine student type
                            $student_type = count($section_ids) > 1 ? 'irregular' : 'regular';
                            $stmt = $pdo->prepare("UPDATE students_info SET student_type = ? WHERE user_id = ?");
                            $stmt->execute([$student_type, $student_id]);
                            
                            // Assign subjects
                            if (!empty($subject_ids)) {
                                $assign_stmt = $pdo->prepare("
                                    INSERT INTO student_subjects (student_id, subject_id, section_id, assigned_by, reason, status)
                                    VALUES (?, ?, (SELECT section_id FROM subject_sections WHERE subject_id = ? LIMIT 1), ?, 'Enrolled by registrar', 'active')
                                ");
                                foreach ($subject_ids as $subj_id) {
                                    $subj_id = intval($subj_id);
                                    if ($subj_id > 0) {
                                        $assign_stmt->execute([$student_id, $subj_id, $subj_id, $_SESSION['user_id']]);
                                    }
                                }
                            }
                            
                            $pdo->commit();
                            logActivity($_SESSION['user_id'], 'Student Enrolled', "Enrolled student {$student_id} in " . count($section_ids) . " sections. Type: {$student_type}");
                            $success = "Student enrolled successfully. Student type: " . ucfirst($student_type);
                        }
                    } catch (Exception $e) {
                        if ($pdo->inTransaction()) $pdo->rollBack();
                        $error = 'Failed to enroll student: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'update_student_info':
                $student_id = sanitizeInput($_POST['student_id'] ?? '');
                $name = sanitizeInput($_POST['name'] ?? '');
                $email = sanitizeInput($_POST['email'] ?? '');
                $program = sanitizeInput($_POST['program'] ?? '');
                $year_level = intval($_POST['year_level'] ?? 0);
                
                try {
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE user_id = ?");
                    $stmt->execute([$name, $email, $student_id]);
                    
                    $stmt = $pdo->prepare("UPDATE students_info SET program = ?, year_level = ? WHERE user_id = ?");
                    $stmt->execute([$program, $year_level, $student_id]);
                    
                    logActivity($_SESSION['user_id'], 'Student Info Updated', "Updated information for student {$student_id}");
                    $success = "Student information updated successfully.";
                } catch (Exception $e) {
                    $error = 'Failed to update student: ' . $e->getMessage();
                }
                break;
                
            case 'update_grade':
                $student_id = sanitizeInput($_POST['student_id'] ?? '');
                $subject_id = intval($_POST['subject_id'] ?? 0);
                $grade = floatval($_POST['grade'] ?? 0);
                $year_level = intval($_POST['year_level'] ?? 0);
                $semester = $_POST['semester'] ?? '1st';
                $academic_year = $_POST['academic_year'] ?? '2024-2025';
                
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO student_course_completion 
                        (student_id, subject_id, year_level, semester, academic_year, grade, date_completed, status)
                        VALUES (?, ?, ?, ?, ?, ?, CURDATE(), 'completed')
                        ON DUPLICATE KEY UPDATE
                        grade = VALUES(grade),
                        date_completed = VALUES(date_completed),
                        status = 'completed'
                    ");
                    $stmt->execute([$student_id, $subject_id, $year_level, $semester, $academic_year, $grade]);
                    
                    logActivity($_SESSION['user_id'], 'Grade Updated', "Updated grade for student {$student_id} in subject {$subject_id}");
                    $success = "Grade updated successfully.";
                } catch (Exception $e) {
                    $error = 'Failed to update grade: ' . $e->getMessage();
                }
                break;
        }
    }
}

// Get filters
$program_filter = $_GET['program'] ?? '';
$year_filter = $_GET['year_level'] ?? '';
$search = $_GET['search'] ?? '';

$where = [];
$params = [];

if (!empty($program_filter)) {
    $where[] = "si.program = ?";
    $params[] = $program_filter;
}
if (!empty($year_filter)) {
    $where[] = "si.year_level = ?";
    $params[] = $year_filter;
}
if (!empty($search)) {
    $where[] = "(u.name LIKE ? OR u.email LIKE ? OR u.user_id LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) . ' AND u.role = "student"' : 'WHERE u.role = "student"';

// Pagination
$per_page = 20;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $per_page;

$count_sql = "SELECT COUNT(*) FROM users u JOIN students_info si ON u.user_id = si.user_id {$where_clause}";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetchColumn();
$total_pages = ceil($total_records / $per_page);

$sql = "SELECT u.*, si.program, si.year_level, si.student_type, si.enrollment_status, si.enrollment_date
        FROM users u
        JOIN students_info si ON u.user_id = si.user_id
        {$where_clause}
        ORDER BY u.created_at DESC
        LIMIT " . intval($per_page) . " OFFSET " . intval($offset);
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

// Map for student sections and subjects
$student_sections_map = [];
$student_subjects_map = [];

$stmt = $pdo->query("SELECT student_id, section_id FROM student_sections");
while ($row = $stmt->fetch()) {
    $student_sections_map[$row['student_id']][] = $row['section_id'];
}

$stmt = $pdo->query("
    SELECT ss.student_id, s.id, s.subject_code, s.subject_name, s.units, scc.grade, scc.status as completion_status
    FROM student_subjects ss
    JOIN subjects s ON ss.subject_id = s.id
    LEFT JOIN student_course_completion scc ON scc.student_id = ss.student_id AND scc.subject_id = ss.subject_id
    WHERE ss.status = 'active'
");
while ($row = $stmt->fetch()) {
    if (!isset($student_subjects_map[$row['student_id']])) {
        $student_subjects_map[$row['student_id']] = [];
    }
    $student_subjects_map[$row['student_id']][] = $row;
}

renderPageStart('Manage Students', 'registrar', 'manage_students.php');
?>

<style>
.student-card {
    transition: all 0.3s ease;
    border: 1px solid #e9ecef;
}
.student-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}
.section-badge {
    background: #e9ecef;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
}
.enrollment-modal .modal-xl {
    max-width: 1200px;
}
.section-checkbox-item {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 10px;
    transition: all 0.3s;
}
.section-checkbox-item:hover {
    border-color: #007bff;
    background: #f8f9fa;
}
.section-checkbox-item.selected {
    border-color: #28a745;
    background: #f0fff4;
}
.subject-item {
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 8px 12px;
    margin-bottom: 6px;
}
.subject-item.selected {
    border-color: #28a745;
    background: #f0fff4;
}
</style>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-user-graduate me-2"></i>Student Management</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#enrollStudentModal">
            <i class="fas fa-plus"></i> Enroll Student
        </button>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-header bg-light">
            <h6 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Students</h6>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Program</label>
                    <select class="form-select" name="program">
                        <option value="">All Programs</option>
                        <?php foreach ($programs as $prog): ?>
                            <option value="<?php echo htmlspecialchars($prog); ?>" <?php echo $program_filter === $prog ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($prog); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Year Level</label>
                    <select class="form-select" name="year_level">
                        <option value="">All Years</option>
                        <?php for ($i = 1; $i <= 4; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo $year_filter == $i ? 'selected' : ''; ?>>Year <?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Search</label>
                    <div class="input-group">
                        <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="ID, Name, or Email...">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
                        <a href="manage_students.php" class="btn btn-secondary"><i class="fas fa-undo"></i> Clear</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Students List -->
    <div class="row">
        <?php if (empty($students)): ?>
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <h5>No Students Found</h5>
                        <p class="text-muted">Click "Enroll Student" to add new students.</p>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($students as $student): 
                $student_sections = $student_sections_map[$student['user_id']] ?? [];
                $student_subjects = $student_subjects_map[$student['user_id']] ?? [];
            ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card student-card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h5 class="card-title mb-0"><?php echo htmlspecialchars($student['name']); ?></h5>
                                    <code class="text-muted small"><?php echo htmlspecialchars($student['user_id']); ?></code>
                                </div>
                                <span class="badge bg-<?php echo $student['student_type'] === 'regular' ? 'success' : 'warning'; ?>">
                                    <?php echo ucfirst($student['student_type']); ?>
                                </span>
                            </div>
                            
                            <div class="mb-3">
                                <div><i class="fas fa-graduation-cap text-muted me-2"></i><?php echo htmlspecialchars($student['program']); ?></div>
                                <div><i class="fas fa-calendar-alt text-muted me-2"></i>Year <?php echo $student['year_level']; ?></div>
                                <div><i class="fas fa-envelope text-muted me-2"></i><?php echo htmlspecialchars($student['email']); ?></div>
                                <div><i class="fas fa-flag-checkered text-muted me-2"></i><?php echo ucfirst($student['enrollment_status']); ?></div>
                            </div>
                            
                            <div class="mb-3">
                                <strong>Sections:</strong>
                                <div class="mt-1">
                                    <?php 
                                    $section_codes = [];
                                    if (!empty($student_sections)) {
                                        $placeholders = str_repeat('?,', count($student_sections) - 1) . '?';
                                        $stmt = $pdo->prepare("SELECT section_code FROM sections WHERE id IN ($placeholders)");
                                        $stmt->execute($student_sections);
                                        $section_codes = $stmt->fetchAll(PDO::FETCH_COLUMN);
                                    }
                                    if (!empty($section_codes)):
                                        foreach ($section_codes as $code):
                                    ?>
                                        <span class="section-badge d-inline-block me-1 mb-1"><?php echo htmlspecialchars($code); ?></span>
                                    <?php 
                                        endforeach;
                                    else:
                                    ?>
                                        <span class="text-muted">No sections assigned</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <strong>Subjects:</strong>
                                <div class="mt-1">
                                    <?php if (!empty($student_subjects)): ?>
                                        <?php foreach ($student_subjects as $subject): ?>
                                            <span class="badge bg-info me-1 mb-1"><?php echo htmlspecialchars($subject['subject_code']); ?></span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-muted">No subjects assigned</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent">
                            <div class="btn-group w-100">
                                <button class="btn btn-sm btn-outline-info" onclick="viewStudentDetails('<?php echo htmlspecialchars($student['user_id']); ?>')">
                                    <i class="fas fa-eye"></i> Details
                                </button>
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#enrollStudentModal" 
                                        onclick="prepareEnrollModal('<?php echo htmlspecialchars($student['user_id']); ?>', '<?php echo htmlspecialchars($student['name']); ?>', '<?php echo htmlspecialchars($student['program']); ?>', <?php echo $student['year_level']; ?>, <?php echo json_encode($student_sections); ?>)">
                                    <i class="fas fa-edit"></i> Enroll
                                </button>
                                <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#editGradesModal"
                                        onclick="prepareGradesModal('<?php echo htmlspecialchars($student['user_id']); ?>', '<?php echo htmlspecialchars($student['name']); ?>')">
                                    <i class="fas fa-chart-line"></i> Grades
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">&laquo;</a>
                </li>
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">&raquo;</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<!-- Enroll Student Modal -->
<div class="modal fade" id="enrollStudentModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form method="POST" id="enrollForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="enroll_student">
                <input type="hidden" name="student_id" id="enroll_student_id">
                
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-user-graduate me-2"></i>Student Enrollment</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="alert alert-info">
                                <strong>Student:</strong> <span id="enroll_student_name"></span><br>
                                <strong>Current Program:</strong> <span id="enroll_current_program"></span> | 
                                <strong>Year Level:</strong> <span id="enroll_current_year"></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Program</label>
                            <select class="form-select" name="program" id="enroll_program" required>
                                <option value="">Select Program</option>
                                <?php foreach ($programs as $prog): ?>
                                    <option value="<?php echo htmlspecialchars($prog); ?>"><?php echo htmlspecialchars($prog); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Year Level</label>
                            <select class="form-select" name="year_level" id="enroll_year_level" required>
                                <option value="">Select Year</option>
                                <?php for ($i = 1; $i <= 4; $i++): ?>
                                    <option value="<?php echo $i; ?>">Year <?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-lg-5 mb-3">
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0"><i class="fas fa-layer-group me-2"></i>Sections</h6>
                                </div>
                                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                                    <div id="sectionsList">
                                        <?php foreach ($sections as $section): ?>
                                            <div class="section-checkbox-item" data-section-id="<?php echo $section['id']; ?>">
                                                <div class="form-check">
                                                    <input class="form-check-input section-checkbox" type="checkbox" 
                                                           name="section_ids[]" value="<?php echo $section['id']; ?>" 
                                                           id="section_<?php echo $section['id']; ?>">
                                                    <label class="form-check-label fw-bold" for="section_<?php echo $section['id']; ?>">
                                                        <?php echo htmlspecialchars($section['section_label']); ?>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-7 mb-3">
                            <div class="card">
                                <div class="card-header bg-info text-white">
                                    <h6 class="mb-0"><i class="fas fa-book me-2"></i>Subjects</h6>
                                </div>
                                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                                    <div class="mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="selectAllSubjects">
                                            <label class="form-check-label fw-bold" for="selectAllSubjects">Select All Subjects from Selected Sections</label>
                                        </div>
                                    </div>
                                    <div id="subjectsContainer">
                                        <div class="text-center text-muted py-4">
                                            <i class="fas fa-book-open fa-2x mb-2"></i>
                                            <p>Select sections first to view subjects</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0">Enrollment Summary</h6>
                                </div>
                                <div class="card-body" id="enrollmentSummary">
                                    <p class="text-muted">No sections selected</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save Enrollment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Grades Modal -->
<div class="modal fade" id="editGradesModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="gradeForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="update_grade">
                <input type="hidden" name="student_id" id="grade_student_id">
                
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-chart-line me-2"></i>Manage Grades</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info mb-3" id="gradeStudentInfo"></div>
                    
                    <div class="mb-3">
                        <label class="form-label">Academic Year</label>
                        <input type="text" class="form-control" name="academic_year" value="2024-2025" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Subject</label>
                            <select class="form-select" name="subject_id" id="grade_subject_id" required>
                                <option value="">Select Subject</option>
                                <?php foreach ($subjects as $subject): ?>
                                    <option value="<?php echo $subject['id']; ?>"><?php echo htmlspecialchars($subject['subject_code'] . ' - ' . $subject['subject_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Year Level</label>
                            <select class="form-select" name="year_level" id="grade_year_level" required>
                                <option value="">Select Year</option>
                                <?php for ($i = 1; $i <= 4; $i++): ?>
                                    <option value="<?php echo $i; ?>">Year <?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Semester</label>
                            <select class="form-select" name="semester" required>
                                <option value="1st">1st Semester</option>
                                <option value="2nd">2nd Semester</option>
                                <option value="summer">Summer</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Grade</label>
                        <select class="form-select" name="grade" required>
                            <option value="">Select Grade</option>
                            <option value="1.00">1.00 - Excellent</option>
                            <option value="1.25">1.25 - Very Good</option>
                            <option value="1.50">1.50 - Good</option>
                            <option value="1.75">1.75 - Satisfactory</option>
                            <option value="2.00">2.00 - Fairly Satisfactory</option>
                            <option value="2.25">2.25 - Passing</option>
                            <option value="2.50">2.50 - Passing</option>
                            <option value="2.75">2.75 - Passing</option>
                            <option value="3.00">3.00 - Pass</option>
                            <option value="5.00">5.00 - Fail</option>
                            <option value="INC">INC - Incomplete</option>
                            <option value="W">W - Withdrawn</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-save me-2"></i>Save Grade</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Student Details Modal -->
<div class="modal fade" id="studentDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">Student Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="studentDetailsContent">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary"></div>
                    <p class="mt-2">Loading...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// Section-subjects mapping
const sectionSubjectsMap = <?php 
    $map = [];
    $stmt = $pdo->query("SELECT ss.section_id, s.id, s.subject_code, s.subject_name, s.units FROM subject_sections ss JOIN subjects s ON ss.subject_id = s.id");
    while ($row = $stmt->fetch()) {
        $map[$row['section_id']][] = $row;
    }
    echo json_encode($map);
?>;

let currentSectionsForModal = [];

function prepareEnrollModal(studentId, studentName, program, yearLevel, currentSections) {
    document.getElementById('enroll_student_id').value = studentId;
    document.getElementById('enroll_student_name').textContent = studentName;
    document.getElementById('enroll_current_program').textContent = program || 'Not set';
    document.getElementById('enroll_current_year').textContent = yearLevel || 'Not set';
    document.getElementById('enroll_program').value = program || '';
    document.getElementById('enroll_year_level').value = yearLevel || '';
    
    currentSectionsForModal = currentSections.map(id => id.toString());
    
    // Reset checkboxes
    document.querySelectorAll('.section-checkbox').forEach(cb => {
        cb.checked = currentSectionsForModal.includes(cb.value);
        const parent = cb.closest('.section-checkbox-item');
        if (parent) {
            if (cb.checked) parent.classList.add('selected');
            else parent.classList.remove('selected');
        }
    });
    
    loadSubjectsForModal();
}

function loadSubjectsForModal() {
    const selectedSections = Array.from(document.querySelectorAll('.section-checkbox:checked')).map(cb => cb.value);
    const container = document.getElementById('subjectsContainer');
    
    if (selectedSections.length === 0) {
        container.innerHTML = '<div class="text-center text-muted py-4"><i class="fas fa-book-open fa-2x mb-2"></i><p>Select sections first to view subjects</p></div>';
        updateSummary();
        return;
    }
    
    let html = '';
    let allSubjects = [];
    
    selectedSections.forEach(sectionId => {
        const subjects = sectionSubjectsMap[sectionId] || [];
        if (subjects.length > 0) {
            html += `<div class="mb-3"><h6 class="fw-bold text-primary mb-2">${sectionId}</h6>`;
            subjects.forEach(subject => {
                html += `
                    <div class="subject-item">
                        <div class="form-check">
                            <input class="form-check-input subject-checkbox" type="checkbox" 
                                   name="subject_ids[]" value="${subject.id}" id="subject_${subject.id}">
                            <label class="form-check-label fw-bold" for="subject_${subject.id}">
                                ${subject.subject_code}
                            </label>
                            <div class="text-muted small">${subject.subject_name} (${subject.units} units)</div>
                        </div>
                    </div>
                `;
                allSubjects.push(subject);
            });
            html += `</div>`;
        }
    });
    
    if (html === '') {
        html = '<div class="alert alert-warning">No subjects available for selected sections.</div>';
    }
    
    container.innerHTML = html;
    updateSummary();
}

function updateSummary() {
    const selectedSections = Array.from(document.querySelectorAll('.section-checkbox:checked')).length;
    const selectedSubjects = Array.from(document.querySelectorAll('.subject-checkbox:checked')).length;
    const studentType = selectedSections > 1 ? 'irregular' : 'regular';
    
    const summaryHtml = `
        <div class="row">
            <div class="col-md-4">
                <strong>Sections:</strong> <span class="badge bg-primary">${selectedSections}</span>
            </div>
            <div class="col-md-4">
                <strong>Subjects:</strong> <span class="badge bg-success">${selectedSubjects}</span>
            </div>
            <div class="col-md-4">
                <strong>Student Type:</strong> <span class="badge bg-${studentType === 'regular' ? 'success' : 'warning'}">${studentType}</span>
            </div>
        </div>
    `;
    document.getElementById('enrollmentSummary').innerHTML = summaryHtml;
}

function prepareGradesModal(studentId, studentName) {
    document.getElementById('grade_student_id').value = studentId;
    document.getElementById('gradeStudentInfo').innerHTML = `<strong>Student:</strong> ${studentName} (${studentId})`;
    document.getElementById('grade_subject_id').value = '';
    document.getElementById('grade_year_level').value = '';
    document.getElementById('gradeForm').reset();
}

function viewStudentDetails(studentId) {
    const modal = new bootstrap.Modal(document.getElementById('studentDetailsModal'));
    document.getElementById('studentDetailsContent').innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div><p>Loading...</p></div>';
    modal.show();
    
    fetch(`ajax_handler.php?action=get_student_details&student_id=${encodeURIComponent(studentId)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('studentDetailsContent').innerHTML = data.html;
            } else {
                document.getElementById('studentDetailsContent').innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
            }
        })
        .catch(error => {
            document.getElementById('studentDetailsContent').innerHTML = `<div class="alert alert-danger">Error loading details</div>`;
        });
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.section-checkbox').forEach(cb => {
        cb.addEventListener('change', function() {
            const parent = this.closest('.section-checkbox-item');
            if (parent) {
                if (this.checked) parent.classList.add('selected');
                else parent.classList.remove('selected');
            }
            loadSubjectsForModal();
        });
    });
    
    document.getElementById('selectAllSubjects')?.addEventListener('change', function() {
        document.querySelectorAll('.subject-checkbox').forEach(cb => cb.checked = this.checked);
        updateSummary();
    });
    
    // Auto-close modals on form submit success
    const enrollForm = document.getElementById('enrollForm');
    if (enrollForm) {
        enrollForm.addEventListener('submit', function() {
            setTimeout(() => {
                if (document.querySelector('.alert-success')) {
                    bootstrap.Modal.getInstance(document.getElementById('enrollStudentModal'))?.hide();
                }
            }, 1500);
        });
    }
});
</script>

<?php renderPageEnd(); ?>