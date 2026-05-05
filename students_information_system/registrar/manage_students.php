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
                        
                        $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ? AND role = 'student'");
                        $stmt->execute([$student_id]);
                        $student = $stmt->fetch();
                        
                        if (!$student) {
                            $error = 'Student not found. Please add the student first.';
                            $pdo->rollBack();
                        } else {
                            $stmt = $pdo->prepare("UPDATE students_info SET program = ?, year_level = ?, enrollment_status = 'enrolled', enrollment_date = CURDATE() WHERE user_id = ?");
                            $stmt->execute([$program, $year_level, $student_id]);
                            
                            $stmt = $pdo->prepare("DELETE FROM student_sections WHERE student_id = ?");
                            $stmt->execute([$student_id]);
                            
                            $stmt = $pdo->prepare("DELETE FROM student_subjects WHERE student_id = ?");
                            $stmt->execute([$student_id]);
                            
                            $stmt = $pdo->prepare("INSERT INTO student_sections (student_id, section_id) VALUES (?, ?)");
                            foreach ($section_ids as $sid) {
                                $sid = intval($sid);
                                if ($sid > 0) {
                                    $stmt->execute([$student_id, $sid]);
                                }
                            }
                            
                            $student_type = count($section_ids) > 1 ? 'irregular' : 'regular';
                            $stmt = $pdo->prepare("UPDATE students_info SET student_type = ? WHERE user_id = ?");
                            $stmt->execute([$student_type, $student_id]);
                            
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
$per_page = 15;
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

// Map for student sections
$student_sections_map = [];
$stmt = $pdo->query("SELECT student_id, section_id FROM student_sections");
while ($row = $stmt->fetch()) {
    $student_sections_map[$row['student_id']][] = $row['section_id'];
}

renderPageStart('Manage Students', 'registrar', 'manage_students.php');
?>

<style>
.student-table th {
    background-color: #f8f9fa;
    white-space: nowrap;
}
.student-table td {
    vertical-align: middle;
}
.student-table .actions {
    white-space: nowrap;
    width: 180px;
}
.section-badge {
    background: #e9ecef;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 11px;
    display: inline-block;
    margin: 2px;
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
.pagination {
    flex-wrap: wrap;
    gap: 5px;
}
.pagination .page-item {
    margin: 2px;
}
@media (max-width: 768px) {
    .pagination {
        justify-content: center;
    }
    .student-table {
        font-size: 13px;
    }
    .student-table .actions {
        white-space: normal;
    }
}
</style>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-user-graduate me-2"></i>Student Management</h2>
        <!-- Bulk Upload Button -->
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#bulkGradeUploadModal">
            <i class="fas fa-file-csv me-1"></i> Bulk Upload Grades
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
            <form method="GET" id="filterForm" class="row g-3">
                <input type="hidden" name="page" value="1">
                <div class="col-md-3">
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
                <div class="col-md-2">
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
                    <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="ID, Name, or Email...">
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1"><i class="fas fa-search"></i> Filter</button>
                        <a href="manage_students.php" class="btn btn-secondary"><i class="fas fa-undo"></i> Clear</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Students Table -->
    <div class="card">
        <div class="card-header bg-white">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Student List</h5>
                <div class="text-muted small">
                    Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total_records); ?> of <?php echo $total_records; ?> students
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover student-table mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Student ID</th>
                            <th>Name</th>
                            <th>Program</th>
                            <th>Year</th>
                            <th>Type</th>
                            <th>Sections</th>
                            <th>Status</th>
                            <th class="actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="studentsTableBody">
                        <?php 
                        $counter = $offset + 1;
                        foreach ($students as $student): 
                            $student_sections = $student_sections_map[$student['user_id']] ?? [];
                            $section_codes = [];
                            if (!empty($student_sections)) {
                                $placeholders = str_repeat('?,', count($student_sections) - 1) . '?';
                                $stmt = $pdo->prepare("SELECT section_code FROM sections WHERE id IN ($placeholders)");
                                $stmt->execute($student_sections);
                                $section_codes = $stmt->fetchAll(PDO::FETCH_COLUMN);
                            }
                        ?>
                            <tr>
                                <td class="text-center"><?php echo $counter++; ?></td>
                                <td><code><?php echo htmlspecialchars($student['user_id']); ?></code></td>
                                <td><strong><?php echo htmlspecialchars($student['name']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($student['email']); ?></small>
                                </span></td>
                                <td><?php echo htmlspecialchars($student['program']); ?></td>
                                <td><span class="badge bg-secondary">Year <?php echo $student['year_level']; ?></span></td>
                                <td>
                                    <span class="badge bg-<?php echo $student['student_type'] === 'regular' ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst($student['student_type']); ?>
                                    </span>
                                 </span></td>
                                <td>
                                    <?php if (!empty($section_codes)): ?>
                                        <?php foreach ($section_codes as $code): ?>
                                            <span class="section-badge"><?php echo htmlspecialchars($code); ?></span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                 </span></td>
                                <td>
                                    <span class="badge bg-<?php echo $student['enrollment_status'] === 'enrolled' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($student['enrollment_status']); ?>
                                    </span>
                                 </span></td>
                                <td class="actions">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-info" onclick="viewStudentDetails('<?php echo htmlspecialchars($student['user_id']); ?>')" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#enrollStudentModal" 
                                                onclick="prepareEnrollModal('<?php echo htmlspecialchars($student['user_id']); ?>', '<?php echo htmlspecialchars($student['name']); ?>', '<?php echo htmlspecialchars($student['program']); ?>', <?php echo $student['year_level']; ?>, <?php echo json_encode($student_sections); ?>)"
                                                title="Enroll/Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#editGradesModal"
                                                onclick="prepareGradesModal('<?php echo htmlspecialchars($student['user_id']); ?>', '<?php echo htmlspecialchars($student['name']); ?>')"
                                                title="Manage Grades">
                                            <i class="fas fa-chart-line"></i>
                                        </button>
                                    </div>
                                 </span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($students)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-5">
                                    <i class="fas fa-users fa-2x text-muted mb-3"></i>
                                    <p class="mb-0">No students found</p>
                                 </span></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($total_pages > 1): ?>
            <div class="card-footer bg-white">
                <nav>
                    <ul class="pagination justify-content-center mb-0">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=1&program=<?php echo urlencode($program_filter); ?>&year_level=<?php echo urlencode($year_filter); ?>&search=<?php echo urlencode($search); ?>">&laquo;&laquo;</a>
                        </li>
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&program=<?php echo urlencode($program_filter); ?>&year_level=<?php echo urlencode($year_filter); ?>&search=<?php echo urlencode($search); ?>">&laquo;</a>
                        </li>
                        <?php 
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        if ($start_page > 1): ?>
                            <li class="page-item"><a class="page-link" href="?page=1&program=<?php echo urlencode($program_filter); ?>&year_level=<?php echo urlencode($year_filter); ?>&search=<?php echo urlencode($search); ?>">1</a></li>
                            <?php if ($start_page > 2): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&program=<?php echo urlencode($program_filter); ?>&year_level=<?php echo urlencode($year_filter); ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                            <li class="page-item"><a class="page-link" href="?page=<?php echo $total_pages; ?>&program=<?php echo urlencode($program_filter); ?>&year_level=<?php echo urlencode($year_filter); ?>&search=<?php echo urlencode($search); ?>"><?php echo $total_pages; ?></a></li>
                        <?php endif; ?>
                        
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&program=<?php echo urlencode($program_filter); ?>&year_level=<?php echo urlencode($year_filter); ?>&search=<?php echo urlencode($search); ?>">&raquo;</a>
                        </li>
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $total_pages; ?>&program=<?php echo urlencode($program_filter); ?>&year_level=<?php echo urlencode($year_filter); ?>&search=<?php echo urlencode($search); ?>">&raquo;&raquo;</a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Bulk Grade Upload Modal -->
<div class="modal fade" id="bulkGradeUploadModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-file-csv me-2"></i>Bulk Grade Upload</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info small">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>CSV Format Instructions:</strong><br>
                    - <strong>Column A:</strong> Student ID<br>
                    - <strong>Column B:</strong> Student Name<br>
                    - <strong>Column C:</strong> Subject Code<br>
                    - <strong>Column D:</strong> Grade<br>
                    - <strong>Column E:</strong> Subject Name (optional)<br>
                    - <strong>Column F:</strong> Year Level (optional)<br>
                    - <strong>Column G:</strong> Semester (optional)<br>
                    
                    <div class="mt-2">
                        <button type="button" class="btn btn-sm btn-outline-info" id="downloadBsit1aTemplate">
                            <i class="fas fa-download me-1"></i> Download BSIT 1A Template
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="downloadGenericTemplate">
                            <i class="fas fa-download me-1"></i> Download Generic Template
                        </button>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Select Section (Optional)</label>
                        <select class="form-select" id="bulk_section_id">
                            <option value="">-- Select Section (optional) --</option>
                            <?php
                            $sections_list = $pdo->query("
                                SELECT s.id, s.section_code, s.program, s.year_level,
                                       CONCAT(s.section_code, ' - ', s.program, ' (Year ', s.year_level, ')') AS section_label
                                FROM sections s
                                WHERE s.status = 'active'
                                ORDER BY s.program, s.year_level, s.section_code
                            ")->fetchAll();
                            foreach ($sections_list as $sec):
                            ?>
                                <option value="<?php echo $sec['id']; ?>" data-code="<?php echo htmlspecialchars($sec['section_code']); ?>">
                                    <?php echo htmlspecialchars($sec['section_label']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Select section to filter template students</div>
                    </div>
                </div>
                
                <form id="bulkGradeUploadForm" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="upload_grades_bulk">
                    <input type="hidden" name="section_id" id="upload_section_id" value="0">
                    
                    <div class="row align-items-end">
                        <div class="col-md-8">
                            <label class="form-label">Select CSV File</label>
                            <input type="file" class="form-control" id="bulk_csv_file" name="csv_file" accept=".csv" required>
                            <div class="form-text">Maximum file size: 5MB. Only .csv files accepted.</div>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-success w-100" id="uploadGradesBtn">
                                <i class="fas fa-upload me-1"></i> Upload & Process
                            </button>
                        </div>
                    </div>
                    
                    <div id="bulkUploadProgress" style="display: none;" class="mt-3">
                        <div class="progress">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%"></div>
                        </div>
                        <p class="small text-muted mt-1" id="bulkUploadStatus">Processing...</p>
                    </div>
                </form>
                
                <div id="bulkUploadResult" class="mt-3" style="display: none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
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

<!-- Edit Grades Modal with CSV Upload -->
<div class="modal fade" id="editGradesModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-chart-line me-2"></i>Manage Student Grades</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="grade_student_id">
                <input type="hidden" id="grade_student_name">

                <div class="alert alert-info mb-3" id="gradeStudentInfo"></div>

                <!-- CSV Bulk Upload Section -->
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0 text-success"><i class="fas fa-file-csv me-2"></i>Bulk Grade Upload (CSV)</h6>
                        <button class="btn btn-sm btn-outline-success" type="button" data-bs-toggle="collapse" data-bs-target="#csvUploadCollapse">
                            <i class="fas fa-upload me-1"></i> Upload CSV File
                        </button>
                    </div>
                    
                    <div class="collapse" id="csvUploadCollapse">
                        <div class="card card-body bg-light">
                            <div class="alert alert-info small">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>CSV Format Instructions:</strong><br>
                                - <strong>Column A:</strong> Subject Code (e.g., IT101, CS201, GE101)<br>
                                - <strong>Column B:</strong> Grade (e.g., 1.25, 2.0, 3.0, 5.0, or letter grades A, B, C, D, F, P)<br>
                                - <strong>Column C:</strong> Year Level (1, 2, 3, 4) - Default: 1<br>
                                - <strong>Column D:</strong> Semester (1st, 2nd, summer) - Default: 1st<br>
                                - <strong>Column E:</strong> Academic Year (e.g., 2024-2025) - Optional<br>
                                <a href="#" id="downloadCsvTemplate" class="mt-2 d-inline-block">
                                    <i class="fas fa-download me-1"></i> Download CSV Template
                                </a>
                            </div>
                            
                            <form id="csvUploadForm" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="action" value="upload_grades_csv">
                                <input type="hidden" name="student_id" id="csv_student_id">
                                
                                <div class="row align-items-end">
                                    <div class="col-md-8">
                                        <label class="form-label">Select CSV File</label>
                                        <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv" required>
                                        <div class="form-text">Maximum file size: 5MB. Only .csv files accepted.</div>
                                    </div>
                                    <div class="col-md-4">
                                        <button type="submit" class="btn btn-success w-100" id="uploadCsvBtn">
                                            <i class="fas fa-upload me-1"></i> Upload & Process
                                        </button>
                                    </div>
                                </div>
                                
                                <div id="csvUploadProgress" style="display: none;" class="mt-3">
                                    <div class="progress">
                                        <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%"></div>
                                    </div>
                                    <p class="small text-muted mt-1" id="csvUploadStatus">Processing...</p>
                                </div>
                            </form>
                            
                            <div id="csvUploadResult" class="mt-3" style="display: none;"></div>
                        </div>
                    </div>
                </div>

                <ul class="nav nav-tabs" id="gradeYearTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="year1-tab" data-bs-toggle="tab" data-bs-target="#year1" type="button">Year 1</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="year2-tab" data-bs-toggle="tab" data-bs-target="#year2" type="button">Year 2</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="year3-tab" data-bs-toggle="tab" data-bs-target="#year3" type="button">Year 3</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="year4-tab" data-bs-toggle="tab" data-bs-target="#year4" type="button">Year 4</button>
                    </li>
                </ul>

                <div class="tab-content mt-3" id="gradeYearContent">
                    <div class="tab-pane fade show active" id="year1">
                        <div class="table-responsive">
                            <table class="table table-sm table-striped" id="grades-table-year1">
                                <thead class="table-light">
                                    <tr><th>Subject Code</th><th>Subject Name</th><th>Units</th><th>Semester</th><th>Grade</th><th>Date Completed</th><th>Status</th></tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="year2">
                        <div class="table-responsive">
                            <table class="table table-sm table-striped" id="grades-table-year2">
                                <thead class="table-light">
                                    <tr><th>Subject Code</th><th>Subject Name</th><th>Units</th><th>Semester</th><th>Grade</th><th>Date Completed</th><th>Status</th></tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="year3">
                        <div class="table-responsive">
                            <table class="table table-sm table-striped" id="grades-table-year3">
                                <thead class="table-light">
                                    <td><th>Subject Code</th><th>Subject Name</th><th>Units</th><th>Semester</th><th>Grade</th><th>Date Completed</th><th>Status</th></tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="year4">
                        <div class="table-responsive">
                            <table class="table table-sm table-striped" id="grades-table-year4">
                                <thead class="table-light">
                                    <tr><th>Subject Code</th><th>Subject Name</th><th>Units</th><th>Semester</th><th>Grade</th><th>Date Completed</th><th>Status</th></table>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success" onclick="saveAllGrades()">
                    <i class="fas fa-save"></i> Save All Changes
                </button>
            </div>
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
    selectedSections.forEach(sectionId => {
        const subjects = sectionSubjectsMap[sectionId] || [];
        if (subjects.length > 0) {
            html += `<div class="mb-3"><h6 class="fw-bold text-primary mb-2">Section ${sectionId}</h6>`;
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
    
    document.getElementById('enrollmentSummary').innerHTML = `
        <div class="row">
            <div class="col-md-4"><strong>Sections:</strong> <span class="badge bg-primary">${selectedSections}</span></div>
            <div class="col-md-4"><strong>Subjects:</strong> <span class="badge bg-success">${selectedSubjects}</span></div>
            <div class="col-md-4"><strong>Student Type:</strong> <span class="badge bg-${studentType === 'regular' ? 'success' : 'warning'}">${studentType}</span></div>
        </div>
    `;
}

function prepareGradesModal(studentId, studentName) {
    document.getElementById('grade_student_id').value = studentId;
    document.getElementById('grade_student_name').value = studentName;
    document.getElementById('gradeStudentInfo').innerHTML = `<strong>Student:</strong> ${studentName} (${studentId})`;
    document.getElementById('csv_student_id').value = studentId;
    
    // Load existing grades
    fetchGrades(studentId);
}

async function fetchGrades(studentId) {
    try {
        const response = await fetch(`ajax_handler.php?action=get_student_grades&student_id=${studentId}`);
        const data = await response.json();
        
        if (data.success && data.grades) {
            renderGradesTable(data.grades);
        }
    } catch (error) {
        console.error('Error fetching grades:', error);
    }
}

function renderGradesTable(grades) {
    for (let year = 1; year <= 4; year++) {
        const tbody = document.querySelector(`#grades-table-year${year} tbody`);
        if (tbody) tbody.innerHTML = '';
    }
    
    grades.forEach(grade => {
        const year = grade.year_level;
        const tbody = document.querySelector(`#grades-table-year${year} tbody`);
        if (tbody) {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td><code>${escapeHtml(grade.subject_code)}</code></td>
                <td>${escapeHtml(grade.subject_name)}<br><small class="text-muted">${grade.units} units</small></td>
                <td>${grade.units}</td>
                <td>${grade.semester}</td>
                <td>
                    <input type="number" class="form-control form-control-sm grade-input" 
                           data-subject-id="${grade.subject_id}"
                           data-year="${grade.year_level}"
                           data-semester="${grade.semester}"
                           value="${grade.grade || ''}" step="0.01" min="1.0" max="5.0"
                           style="width: 80px;" placeholder="—">
                </span></td>
                <td>
                    <input type="date" class="form-control form-control-sm date-input" 
                           value="${grade.date_completed || ''}" style="width: 130px;">
                </span></td>
                <td>
                    <select class="form-select form-select-sm status-select" style="width: 130px;">
                        <option value="completed" ${grade.status === 'completed' ? 'selected' : ''}>Completed</option>
                        <option value="in_progress" ${grade.status === 'in_progress' ? 'selected' : ''}>In Progress</option>
                        <option value="failed" ${grade.status === 'failed' ? 'selected' : ''}>Failed</option>
                        <option value="pending" ${grade.status === 'pending' ? 'selected' : ''}>Pending</option>
                    </select>
                </span></td>
            `;
            tbody.appendChild(row);
        }
    });
}

function saveAllGrades() {
    const studentId = document.getElementById('grade_student_id').value;
    const updates = [];
    
    for (let year = 1; year <= 4; year++) {
        const rows = document.querySelectorAll(`#grades-table-year${year} tbody tr`);
        rows.forEach(row => {
            const gradeInput = row.querySelector('.grade-input');
            const dateInput = row.querySelector('.date-input');
            const statusSelect = row.querySelector('.status-select');
            
            if (gradeInput && gradeInput.value) {
                updates.push({
                    subject_id: gradeInput.dataset.subjectId,
                    year_level: gradeInput.dataset.year,
                    semester: gradeInput.dataset.semester,
                    grade: parseFloat(gradeInput.value),
                    date_completed: dateInput ? dateInput.value : null,
                    status: statusSelect ? statusSelect.value : 'completed'
                });
            }
        });
    }
    
    if (updates.length === 0) {
        alert('No grades to save');
        return;
    }
    
    fetch('ajax_handler.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'save_student_grades',
            student_id: studentId,
            grades: updates
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Grades saved successfully!');
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to save grades');
    });
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

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// CSV Upload Functions
async function uploadGradesCSV(studentId) {
    const form = document.getElementById('csvUploadForm');
    const fileInput = document.getElementById('csv_file');
    const progressDiv = document.getElementById('csvUploadProgress');
    const progressBar = progressDiv.querySelector('.progress-bar');
    const statusText = document.getElementById('csvUploadStatus');
    const resultDiv = document.getElementById('csvUploadResult');
    const uploadBtn = document.getElementById('uploadCsvBtn');
    
    if (!fileInput.files.length) {
        alert('Please select a CSV file');
        return;
    }
    
    const file = fileInput.files[0];
    if (!file.name.toLowerCase().endsWith('.csv')) {
        alert('Please upload a valid CSV file');
        return;
    }
    
    if (file.size > 5 * 1024 * 1024) {
        alert('File size exceeds 5MB limit');
        return;
    }
    
    progressDiv.style.display = 'block';
    progressBar.style.width = '0%';
    statusText.textContent = 'Uploading file...';
    uploadBtn.disabled = true;
    resultDiv.style.display = 'none';
    
    const formData = new FormData(form);
    formData.set('student_id', studentId);
    
    let progress = 0;
    const interval = setInterval(() => {
        progress += 10;
        if (progress <= 90) {
            progressBar.style.width = progress + '%';
        }
    }, 200);
    
    try {
        const response = await fetch('../admin/ajax_handler.php', {
            method: 'POST',
            body: formData
        });
        
        clearInterval(interval);
        const result = await response.json();
        progressBar.style.width = '100%';
        
        if (result.success) {
            statusText.textContent = 'Processing complete!';
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = `
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <strong>Success!</strong> ${result.message}<br>
                    <small>${result.details || ''}</small>
                    ${result.errors && result.errors.length > 0 ? 
                        '<br><br><strong>Warnings/Errors:</strong><ul class="mb-0">' + 
                        result.errors.map(e => `<li class="small">${e}</li>`).join('') + '</ul>' : ''}
                </div>
            `;
            
            setTimeout(() => {
                const studentIdVal = document.getElementById('grade_student_id').value;
                if (studentIdVal) {
                    fetchGrades(studentIdVal);
                }
            }, 2000);
        } else {
            statusText.textContent = 'Upload failed';
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i><strong>Error!</strong> ${result.message}</div>`;
        }
    } catch (error) {
        console.error('Upload error:', error);
        statusText.textContent = 'Upload failed';
        resultDiv.style.display = 'block';
        resultDiv.innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i><strong>Error!</strong> Network error. Please try again.</div>`;
    } finally {
        setTimeout(() => {
            progressBar.style.width = '0%';
            progressDiv.style.display = 'none';
        }, 3000);
        uploadBtn.disabled = false;
        fileInput.value = '';
    }
}

function downloadCsvTemplate() {
    const headers = ['Subject Code', 'Grade', 'Year Level', 'Semester', 'Academic Year'];
    const sampleRows = [
        ['GE101', '', '1', '1st', '2024-2025'],
        ['GE102', '', '1', '1st', '2024-2025'],
        ['GE103', '', '1', '1st', '2024-2025'],
        ['GE104', '', '1', '1st', '2024-2025'],
        ['CS101', '', '1', '1st', '2024-2025'],
        ['CS102', '', '1', '1st', '2024-2025'],
        ['CS104', '', '1', '1st', '2024-2025'],
        ['PE1', '', '1', '1st', '2024-2025'],
        ['NSTP1', '', '1', '1st', '2024-2025']
    ];
    
    let csvContent = headers.join(',') + '\n';
    sampleRows.forEach(row => {
        csvContent += row.join(',') + '\n';
    });
    
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'grade_upload_template.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    
    alert('Template downloaded! Fill in the grades and upload.');
}

// ====================================================
// BULK GRADE UPLOAD FUNCTIONS
// ====================================================

// Download BSIT 1A template
document.getElementById('downloadBsit1aTemplate')?.addEventListener('click', function(e) {
    e.preventDefault();
    downloadBsit1aTemplate();
});

async function downloadBsit1aTemplate() {
    const sectionSelect = document.getElementById('bulk_section_id');
    const sectionId = sectionSelect.value;
    
    if (!sectionId) {
        alert('Please select a section first');
        return;
    }
    
    try {
        // Get students in the section
        const studentsResponse = await fetch(`ajax_handler.php?action=get_students_by_section&section_id=${sectionId}`);
        const studentsData = await studentsResponse.json();
        
        if (!studentsData.success || studentsData.students.length === 0) {
            alert('No students found in this section');
            return;
        }
        
        // Get subjects for the section
        const subjectsResponse = await fetch(`ajax_handler.php?action=get_section_assigned_subjects&section_id=${sectionId}`);
        const subjectsData = await subjectsResponse.json();
        
        const subjects = subjectsData.subjects || [];
        
        if (subjects.length === 0) {
            alert('No subjects assigned to this section');
            return;
        }
        
        const headers = ['Student ID', 'Student Name', 'Subject Code', 'Grade', 'Subject Name', 'Year Level', 'Semester'];
        const rows = [];
        
        studentsData.students.forEach(student => {
            subjects.forEach(subject => {
                rows.push([
                    student.user_id,
                    student.name,
                    subject.subject_code,
                    '',
                    subject.subject_name,
                    '1',
                    '1st'
                ]);
            });
        });
        
        let csvContent = headers.join(',') + '\n';
        rows.forEach(row => {
            csvContent += row.map(cell => `"${cell}"`).join(',') + '\n';
        });
        
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${sectionSelect.options[sectionSelect.selectedIndex].text}_grade_sheet.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        
        alert('Template downloaded! Fill in the grades and upload.');
    } catch (error) {
        console.error('Error:', error);
        alert('Error generating template');
    }
}

// Download generic template
document.getElementById('downloadGenericTemplate')?.addEventListener('click', function(e) {
    e.preventDefault();
    downloadGenericTemplate();
});

function downloadGenericTemplate() {
    const headers = ['Student ID', 'Student Name', 'Subject Code', 'Grade', 'Subject Name', 'Year Level', 'Semester'];
    const sampleRows = [
        ['C24-01-0001-MAN121', 'Juan Dela Cruz', 'GE101', '', 'Understanding the Self', '1', '1st'],
        ['C24-01-0002-MAN121', 'Maria Santos', 'CS101', '', 'Computer Programming 1', '1', '1st'],
        ['', '', '', '', '', '', '']
    ];
    
    let csvContent = headers.join(',') + '\n';
    sampleRows.forEach(row => {
        csvContent += row.map(cell => `"${cell}"`).join(',') + '\n';
    });
    
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'grade_upload_template.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    
    alert('Generic template downloaded!');
}

// Upload bulk grades
async function uploadBulkGrades() {
    const form = document.getElementById('bulkGradeUploadForm');
    const fileInput = document.getElementById('bulk_csv_file');
    const progressDiv = document.getElementById('bulkUploadProgress');
    const progressBar = progressDiv.querySelector('.progress-bar');
    const statusText = document.getElementById('bulkUploadStatus');
    const resultDiv = document.getElementById('bulkUploadResult');
    const uploadBtn = document.getElementById('uploadGradesBtn');
    
    if (!fileInput.files.length) {
        alert('Please select a CSV file');
        return;
    }
    
    const file = fileInput.files[0];
    if (!file.name.toLowerCase().endsWith('.csv')) {
        alert('Please upload a valid CSV file');
        return;
    }
    
    if (file.size > 5 * 1024 * 1024) {
        alert('File size exceeds 5MB limit');
        return;
    }
    
    progressDiv.style.display = 'block';
    progressBar.style.width = '0%';
    statusText.textContent = 'Uploading file...';
    uploadBtn.disabled = true;
    resultDiv.style.display = 'none';
    
    const formData = new FormData(form);
    
    let progress = 0;
    const interval = setInterval(() => {
        progress += 10;
        if (progress <= 90) {
            progressBar.style.width = progress + '%';
        }
    }, 200);
    
    try {
        const response = await fetch('../admin/ajax_handler.php', {
            method: 'POST',
            body: formData
        });
        
        clearInterval(interval);
        const result = await response.json();
        progressBar.style.width = '100%';
        
        if (result.success) {
            statusText.textContent = 'Processing complete!';
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = `
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <strong>Success!</strong> ${result.message}<br>
                    <small>${result.details || ''}</small>
                    ${result.errors && result.errors.length > 0 ? 
                        '<br><br><strong>Warnings/Errors:</strong><ul class="mb-0">' + 
                        result.errors.map(e => `<li class="small">${escapeHtml(e)}</li>`).join('') + '</ul>' : ''}
                </div>
            `;
            
            setTimeout(() => {
                fileInput.value = '';
                location.reload();
            }, 2000);
        } else {
            statusText.textContent = 'Upload failed';
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i><strong>Error!</strong> ${escapeHtml(result.message)}</div>`;
        }
    } catch (error) {
        console.error('Upload error:', error);
        statusText.textContent = 'Upload failed';
        resultDiv.style.display = 'block';
        resultDiv.innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i><strong>Error!</strong> Network error. Please try again.</div>`;
    } finally {
        setTimeout(() => {
            progressBar.style.width = '0%';
            progressDiv.style.display = 'none';
        }, 3000);
        uploadBtn.disabled = false;
    }
}

// Update section ID when selection changes
document.getElementById('bulk_section_id')?.addEventListener('change', function() {
    document.getElementById('upload_section_id').value = this.value;
});

// Event listeners
document.getElementById('bulkGradeUploadForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    uploadBulkGrades();
});

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
    
    document.getElementById('downloadCsvTemplate')?.addEventListener('click', function(e) {
        e.preventDefault();
        downloadCsvTemplate();
    });
    
    document.getElementById('csvUploadForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const studentId = document.getElementById('csv_student_id').value;
        if (!studentId) {
            alert('No student selected. Please close and reopen the grades modal.');
            return;
        }
        uploadGradesCSV(studentId);
    });
});
</script>

<?php renderPageEnd(); ?>