<?php
require_once '../init.php';
require_once '../theme.php';
require_once '../layout.php';

requireRole('admin');

$pdo = getDBConnection();
$error = '';
$success = '';

// Get global unit price
$stmt = $pdo->query("SELECT value FROM settings WHERE name = 'unit_price'");
$global_unit_price = $stmt->fetch(PDO::FETCH_ASSOC);
$global_unit_price = $global_unit_price ? floatval($global_unit_price['value']) : 1000.00;

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $action = $_POST['action'] ?? '';
        
        switch($action) {
            case 'add_course':
                $course_code = sanitizeInput($_POST['course_code'] ?? '');
                $course_name = sanitizeInput($_POST['course_name'] ?? '');
                $description = sanitizeInput($_POST['description'] ?? '');
                $total_units = intval($_POST['total_units'] ?? 0);
                $duration_years = intval($_POST['duration_years'] ?? 4);
                
                if (empty($course_code)) {
                    $error = 'Course code is required.';
                } elseif (empty($course_name)) {
                    $error = 'Course name is required.';
                } elseif ($total_units <= 0) {
                    $error = 'Total units must be greater than 0.';
                } elseif ($duration_years <= 0) {
                    $error = 'Duration must be at least 1 year.';
                } else {
                    try {
                        // Check if course code already exists
                        $stmt = $pdo->prepare("SELECT id FROM courses WHERE course_code = ?");
                        $stmt->execute([$course_code]);
                        if ($stmt->fetch()) {
                            $error = 'Course code already exists.';
                        } else {
                            $stmt = $pdo->prepare("INSERT INTO courses (course_code, course_name, description, total_units, duration_years) 
                                                  VALUES (?, ?, ?, ?, ?)");
                            $stmt->execute([$course_code, $course_name, $description, $total_units, $duration_years]);
                            
                            logActivity($_SESSION['user_id'], 'Course Created', 
                                       "Created course: {$course_code} - {$course_name}");
                            $success = "Course created successfully.";
                        }
                    } catch(Exception $e) {
                        $error = "Failed to create course: " . $e->getMessage();
                    }
                }
                break;
                
            case 'edit_course':
                $course_id = intval($_POST['course_id'] ?? 0);
                $course_code = sanitizeInput($_POST['course_code'] ?? '');
                $course_name = sanitizeInput($_POST['course_name'] ?? '');
                $description = sanitizeInput($_POST['description'] ?? '');
                $total_units = intval($_POST['total_units'] ?? 0);
                $duration_years = intval($_POST['duration_years'] ?? 4);
                $status = $_POST['status'] ?? 'active';
                
                if ($course_id <= 0) {
                    $error = 'Invalid course ID.';
                } elseif (empty($course_code)) {
                    $error = 'Course code is required.';
                } elseif (empty($course_name)) {
                    $error = 'Course name is required.';
                } elseif ($total_units <= 0) {
                    $error = 'Total units must be greater than 0.';
                } elseif ($duration_years <= 0) {
                    $error = 'Duration must be at least 1 year.';
                } else {
                    try {
                        // Check if course code already exists for another course
                        $stmt = $pdo->prepare("SELECT id FROM courses WHERE course_code = ? AND id != ?");
                        $stmt->execute([$course_code, $course_id]);
                        if ($stmt->fetch()) {
                            $error = 'Course code already exists for another course.';
                        } else {
                            $stmt = $pdo->prepare("UPDATE courses SET course_code = ?, course_name = ?, description = ?, 
                                                  total_units = ?, duration_years = ?, status = ? 
                                                  WHERE id = ?");
                            $stmt->execute([$course_code, $course_name, $description, $total_units, $duration_years, $status, $course_id]);
                            
                            logActivity($_SESSION['user_id'], 'Course Updated', 
                                       "Updated course ID: {$course_id}");
                            $success = 'Course updated successfully.';
                        }
                    } catch(Exception $e) {
                        $error = 'Failed to update course: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'delete_course':
                $course_id = intval($_POST['course_id'] ?? 0);
                
                if ($course_id <= 0) {
                    $error = 'Invalid course ID.';
                } else {
                    // Check if course has enrolled students
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM student_course_enrollment WHERE course_id = ? AND status = 'active'");
                    $stmt->execute([$course_id]);
                    $student_count = $stmt->fetchColumn();
                    
                    if ($student_count > 0) {
                        $error = 'Cannot delete course: There are enrolled students.';
                    } else {
                        try {
                            // Soft delete by updating status
                            $stmt = $pdo->prepare("UPDATE courses SET status = 'inactive' WHERE id = ?");
                            $stmt->execute([$course_id]);
                            
                            logActivity($_SESSION['user_id'], 'Course Deleted', "Deleted course ID: {$course_id}");
                            $success = 'Course deleted successfully.';
                        } catch(Exception $e) {
                            $error = 'Failed to delete course: ' . $e->getMessage();
                        }
                    }
                }
                break;
                
            case 'update_global_unit_price':
                $new_unit_price = floatval($_POST['unit_price'] ?? 0);
                
                if ($new_unit_price <= 0) {
                    $error = 'Unit price must be greater than 0.';
                } else {
                    try {
                        // Update or insert unit price setting
                        $stmt = $pdo->prepare("INSERT INTO settings (name, value) VALUES ('unit_price', ?) 
                                              ON DUPLICATE KEY UPDATE value = ?");
                        $stmt->execute([$new_unit_price, $new_unit_price]);
                        
                        $global_unit_price = $new_unit_price;
                        
                        logActivity($_SESSION['user_id'], 'Global Unit Price Updated', 
                                   "Updated global unit price to: {$new_unit_price}");
                        $success = 'Global unit price updated successfully.';
                    } catch(Exception $e) {
                        $error = 'Failed to update unit price: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'add_curriculum':
                $course_id = intval($_POST['course_id'] ?? 0);
                $subject_id = intval($_POST['subject_id'] ?? 0);
                $year_level = intval($_POST['year_level'] ?? 1);
                $semester = intval($_POST['semester'] ?? 1);
                
                if ($course_id <= 0) {
                    $error = 'Invalid course ID.';
                } elseif ($subject_id <= 0) {
                    $error = 'Invalid subject ID.';
                } else {
                    try {
                        // Check if subject already in curriculum
                        $stmt = $pdo->prepare("SELECT id FROM course_curriculum WHERE course_id = ? AND subject_id = ?");
                        $stmt->execute([$course_id, $subject_id]);
                        if ($stmt->fetch()) {
                            $error = 'Subject already exists in curriculum.';
                        } else {
                            $stmt = $pdo->prepare("INSERT INTO course_curriculum (course_id, subject_id, year_level, semester) 
                                                  VALUES (?, ?, ?, ?)");
                            $stmt->execute([$course_id, $subject_id, $year_level, $semester]);
                            
                            logActivity($_SESSION['user_id'], 'Curriculum Added', 
                                       "Added subject to curriculum for course ID: {$course_id}");
                            $success = 'Subject added to curriculum successfully.';
                        }
                    } catch(Exception $e) {
                        $error = 'Failed to add subject to curriculum: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'remove_curriculum':
                $curriculum_id = intval($_POST['curriculum_id'] ?? 0);
                
                if ($curriculum_id <= 0) {
                    $error = 'Invalid curriculum ID.';
                } else {
                    try {
                        $stmt = $pdo->prepare("DELETE FROM course_curriculum WHERE id = ?");
                        $stmt->execute([$curriculum_id]);
                        
                        logActivity($_SESSION['user_id'], 'Curriculum Removed', 
                                   "Removed subject from curriculum ID: {$curriculum_id}");
                        $success = 'Subject removed from curriculum successfully.';
                    } catch(Exception $e) {
                        $error = 'Failed to remove subject from curriculum: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'add_subject':
                $subject_code = sanitizeInput($_POST['subject_code'] ?? '');
                $subject_name = sanitizeInput($_POST['subject_name'] ?? '');
                $units = intval($_POST['units'] ?? 0);
                $description = sanitizeInput($_POST['description'] ?? '');
                $program = sanitizeInput($_POST['program'] ?? '');
                
                if (empty($subject_code)) {
                    $error = 'Subject code is required.';
                } elseif (empty($subject_name)) {
                    $error = 'Subject name is required.';
                } elseif ($units <= 0) {
                    $error = 'Units must be greater than 0.';
                } else {
                    try {
                        // Check if subject code already exists
                        $stmt = $pdo->prepare("SELECT id FROM subjects WHERE subject_code = ?");
                        $stmt->execute([$subject_code]);
                        if ($stmt->fetch()) {
                            $error = 'Subject code already exists.';
                        } else {
                            $stmt = $pdo->prepare("INSERT INTO subjects (subject_code, subject_name, units, description, program) 
                                                  VALUES (?, ?, ?, ?, ?)");
                            $stmt->execute([$subject_code, $subject_name, $units, $description, $program]);
                            
                            logActivity($_SESSION['user_id'], 'Subject Created', 
                                       "Created subject: {$subject_code} - {$subject_name}");
                            $success = "Subject created successfully.";
                        }
                    } catch(Exception $e) {
                        $error = "Failed to create subject: " . $e->getMessage();
                    }
                }
                break;
                
            case 'edit_subject':
                $subject_id = intval($_POST['subject_id'] ?? 0);
                $subject_code = sanitizeInput($_POST['subject_code'] ?? '');
                $subject_name = sanitizeInput($_POST['subject_name'] ?? '');
                $units = intval($_POST['units'] ?? 0);
                $description = sanitizeInput($_POST['description'] ?? '');
                $program = sanitizeInput($_POST['program'] ?? '');
                
                if ($subject_id <= 0) {
                    $error = 'Invalid subject ID.';
                } elseif (empty($subject_code)) {
                    $error = 'Subject code is required.';
                } elseif (empty($subject_name)) {
                    $error = 'Subject name is required.';
                } elseif ($units <= 0) {
                    $error = 'Units must be greater than 0.';
                } else {
                    try {
                        // Check if subject code already exists for another subject
                        $stmt = $pdo->prepare("SELECT id FROM subjects WHERE subject_code = ? AND id != ?");
                        $stmt->execute([$subject_code, $subject_id]);
                        if ($stmt->fetch()) {
                            $error = 'Subject code already exists for another subject.';
                        } else {
                            $stmt = $pdo->prepare("UPDATE subjects SET subject_code = ?, subject_name = ?, units = ?, 
                                                  description = ?, program = ? WHERE id = ?");
                            $stmt->execute([$subject_code, $subject_name, $units, $description, $program, $subject_id]);
                            
                            logActivity($_SESSION['user_id'], 'Subject Updated', 
                                       "Updated subject ID: {$subject_id}");
                            $success = 'Subject updated successfully.';
                        }
                    } catch(Exception $e) {
                        $error = 'Failed to update subject: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'delete_subject':
                $subject_id = intval($_POST['subject_id'] ?? 0);
                
                if ($subject_id <= 0) {
                    $error = 'Invalid subject ID.';
                } else {
                    // Check if subject is in any curriculum
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM course_curriculum WHERE subject_id = ?");
                    $stmt->execute([$subject_id]);
                    $curriculum_count = $stmt->fetchColumn();
                    
                    if ($curriculum_count > 0) {
                        $error = 'Cannot delete subject: Subject is used in course curriculums.';
                    } else {
                        try {
                            $stmt = $pdo->prepare("DELETE FROM subjects WHERE id = ?");
                            $stmt->execute([$subject_id]);
                            
                            logActivity($_SESSION['user_id'], 'Subject Deleted', "Deleted subject ID: {$subject_id}");
                            $success = 'Subject deleted successfully.';
                        } catch(Exception $e) {
                            $error = 'Failed to delete subject: ' . $e->getMessage();
                        }
                    }
                }
                break;
                
            case 'add_section':
                $section_code = sanitizeInput($_POST['section_code'] ?? '');
                $program = sanitizeInput($_POST['program'] ?? '');
                $year_level = intval($_POST['year_level'] ?? 1);
                
                if (empty($section_code)) {
                    $error = 'Section code is required.';
                } elseif (empty($program)) {
                    $error = 'Program is required.';
                } elseif ($year_level <= 0) {
                    $error = 'Year level must be greater than 0.';
                } else {
                    try {
                        // Check if section code already exists
                        $stmt = $pdo->prepare("SELECT id FROM sections WHERE section_code = ?");
                        $stmt->execute([$section_code]);
                        if ($stmt->fetch()) {
                            $error = 'Section code already exists.';
                        } else {
                            $stmt = $pdo->prepare("INSERT INTO sections (section_code, program, year_level) 
                                                  VALUES (?, ?, ?)");
                            $stmt->execute([$section_code, $program, $year_level]);
                            
                            logActivity($_SESSION['user_id'], 'Section Created', 
                                       "Created section: {$section_code} - {$program}");
                            $success = "Section created successfully.";
                        }
                    } catch(Exception $e) {
                        $error = "Failed to create section: " . $e->getMessage();
                    }
                }
                break;
                
            case 'edit_section':
                $section_id = intval($_POST['section_id'] ?? 0);
                $section_code = sanitizeInput($_POST['section_code'] ?? '');
                $program = sanitizeInput($_POST['program'] ?? '');
                $year_level = intval($_POST['year_level'] ?? 1);
                $status = $_POST['status'] ?? 'active';
                
                if ($section_id <= 0) {
                    $error = 'Invalid section ID.';
                } elseif (empty($section_code)) {
                    $error = 'Section code is required.';
                } elseif (empty($program)) {
                    $error = 'Program is required.';
                } elseif ($year_level <= 0) {
                    $error = 'Year level must be greater than 0.';
                } else {
                    try {
                        // Check if section code already exists for another section
                        $stmt = $pdo->prepare("SELECT id FROM sections WHERE section_code = ? AND id != ?");
                        $stmt->execute([$section_code, $section_id]);
                        if ($stmt->fetch()) {
                            $error = 'Section code already exists for another section.';
                        } else {
                            $stmt = $pdo->prepare("UPDATE sections SET section_code = ?, program = ?, year_level = ?, status = ? 
                                                  WHERE id = ?");
                            $stmt->execute([$section_code, $program, $year_level, $status, $section_id]);
                            
                            logActivity($_SESSION['user_id'], 'Section Updated', 
                                       "Updated section ID: {$section_id}");
                            $success = 'Section updated successfully.';
                        }
                    } catch(Exception $e) {
                        $error = 'Failed to update section: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'delete_section':
                $section_id = intval($_POST['section_id'] ?? 0);
                
                if ($section_id <= 0) {
                    $error = 'Invalid section ID.';
                } else {
                    // Check if section has students
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM student_sections WHERE section_id = ?");
                    $stmt->execute([$section_id]);
                    $student_count = $stmt->fetchColumn();
                    
                    if ($student_count > 0) {
                        $error = 'Cannot delete section: There are students assigned to this section.';
                    } else {
                        try {
                            $stmt = $pdo->prepare("DELETE FROM sections WHERE id = ?");
                            $stmt->execute([$section_id]);
                            
                            logActivity($_SESSION['user_id'], 'Section Deleted', "Deleted section ID: {$section_id}");
                            $success = 'Section deleted successfully.';
                        } catch(Exception $e) {
                            $error = 'Failed to delete section: ' . $e->getMessage();
                        }
                    }
                }
                break;
        }
    }
}

// Get all courses, subjects, and sections
$courses = $pdo->query("SELECT c.*, 
                        COUNT(DISTINCT cc.id) as subject_count,
                        COUNT(DISTINCT sce.student_id) as student_count
                        FROM courses c
                        LEFT JOIN course_curriculum cc ON c.id = cc.course_id
                        LEFT JOIN student_course_enrollment sce ON c.id = sce.course_id AND sce.status = 'active'
                        GROUP BY c.id
                        ORDER BY c.course_code")->fetchAll(PDO::FETCH_ASSOC);

$subjects = $pdo->query("SELECT s.* FROM subjects s ORDER BY s.subject_code")->fetchAll(PDO::FETCH_ASSOC);

$sections = $pdo->query("SELECT s.*, 
                         (SELECT COUNT(*) FROM student_sections ss WHERE ss.section_id = s.id) as student_count
                         FROM sections s 
                         ORDER BY s.year_level, s.section_code")->fetchAll(PDO::FETCH_ASSOC);

// Get all programs for dropdowns
$programs = $pdo->query("SELECT DISTINCT program FROM subjects WHERE program IS NOT NULL AND program != '' ORDER BY program")->fetchAll(PDO::FETCH_COLUMN);

renderPageStart('Manage Courses', 'admin', 'manage_courses.php');
?>

<style>
.nav-tabs .nav-link.active {
    background-color: #fff;
    border-bottom-color: #fff;
    font-weight: bold;
}
.tab-content {
    background-color: #fff;
    border: 1px solid #dee2e6;
    border-top: none;
    padding: 20px;
    border-radius: 0 0 5px 5px;
}
.payment-calculation {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    margin: 10px 0;
}
.calculation-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #dee2e6;
}
.calculation-row.total {
    font-weight: bold;
    border-top: 2px solid #007bff;
    border-bottom: none;
}
.year-tabs .nav-link {
    border-radius: 5px 5px 0 0;
    margin-right: 5px;
}
.year-tabs .nav-link.active {
    background-color: #0d6efd;
    color: white;
}
.unit-price-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
}
.unit-price-input {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: white;
}
.unit-price-input:focus {
    background: rgba(255, 255, 255, 0.2);
    border-color: rgba(255, 255, 255, 0.5);
    color: white;
    box-shadow: 0 0 0 0.25rem rgba(255, 255, 255, 0.25);
}
.table-actions {
    white-space: nowrap;
}
.table th:first-child, .table td:first-child {
    text-align: center;
    width: 60px;
}
</style>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-graduation-cap me-2"></i>Course & Subject Management</h2>
        <div>
            <!-- Global Unit Price Display & Update -->
            <div class="card unit-price-card mb-3">
                <div class="card-body py-2">
                    <div class="row align-items-center">
                        <div class="col-md-4">
                            <h6 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i>Global Unit Price</h6>
                        </div>
                        <div class="col-md-4">
                            <h4 class="mb-0">₱<?php echo number_format($global_unit_price, 2); ?></h4>
                        </div>
                        <div class="col-md-4">
                            <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#updateUnitPriceModal">
                                <i class="fas fa-edit me-1"></i> Change Price
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs Navigation -->
    <ul class="nav nav-tabs" id="managementTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="courses-tab" data-bs-toggle="tab" data-bs-target="#courses" type="button">
                <i class="fas fa-graduation-cap me-1"></i> Courses
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="subjects-tab" data-bs-toggle="tab" data-bs-target="#subjects" type="button">
                <i class="fas fa-book me-1"></i> Subjects
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="sections-tab" data-bs-toggle="tab" data-bs-target="#sections" type="button">
                <i class="fas fa-layer-group me-1"></i> Sections
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="curriculum-tab" data-bs-toggle="tab" data-bs-target="#curriculum" type="button">
                <i class="fas fa-clipboard-list me-1"></i> Curriculum
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="calculation-tab" data-bs-toggle="tab" data-bs-target="#calculation" type="button">
                <i class="fas fa-calculator me-1"></i> Payment Calculation
            </button>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content" id="managementTabsContent">
        <!-- Courses Tab -->
        <div class="tab-pane fade show active" id="courses" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5>Course Management</h5>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCourseModal">
                    <i class="fas fa-plus"></i> Add Course
                </button>
            </div>
            
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Course Code</th>
                            <th>Course Name</th>
                            <th>Description</th>
                            <th>Duration</th>
                            <th>Total Units</th>
                            <th>Total Price</th>
                            <th>Subjects</th>
                            <th>Students</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $counter = 1;
                        foreach($courses as $course): 
                            $total_price = $course['total_units'] * $global_unit_price;
                        ?>
                        <tr>
                            <td><?php echo $counter++; ?></td>
                            <td><code><?php echo htmlspecialchars($course['course_code']); ?></code></td>
                            <td><strong><?php echo htmlspecialchars($course['course_name']); ?></strong></td>
                            <td>
                                <?php 
                                if ($course['description']) {
                                    echo htmlspecialchars(substr($course['description'], 0, 50));
                                    if (strlen($course['description']) > 50) echo '...';
                                } else {
                                    echo '<span class="text-muted">No description</span>';
                                }
                                ?>
                            </td>
                            <td><?php echo $course['duration_years']; ?> years</td>
                            <td><?php echo $course['total_units']; ?> units</td>
                            <td><strong>₱<?php echo number_format($total_price, 2); ?></strong></td>
                            <td>
                                <span class="badge bg-info"><?php echo $course['subject_count']; ?> subjects</span>
                            </td>
                            <td>
                                <span class="badge bg-success"><?php echo $course['student_count']; ?> students</span>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $course['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($course['status']); ?>
                                </span>
                            </td>
                            <td class="table-actions">
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-warning btn-edit-course" 
                                            data-id="<?php echo $course['id']; ?>"
                                            data-code="<?php echo htmlspecialchars($course['course_code'], ENT_QUOTES); ?>"
                                            data-name="<?php echo htmlspecialchars($course['course_name'], ENT_QUOTES); ?>"
                                            data-desc="<?php echo htmlspecialchars($course['description'], ENT_QUOTES); ?>"
                                            data-units="<?php echo $course['total_units']; ?>"
                                            data-years="<?php echo $course['duration_years']; ?>"
                                            data-status="<?php echo $course['status']; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-outline-danger btn-delete-course" 
                                            data-id="<?php echo $course['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($course['course_code'], ENT_QUOTES); ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <button class="btn btn-outline-info btn-view-curriculum"
                                            data-id="<?php echo $course['id']; ?>"
                                            onclick="viewCourseCurriculum('<?php echo $course['id']; ?>')">
                                        <i class="fas fa-book-open"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($courses)): ?>
                        <tr>
                            <td colspan="11" class="text-center py-4">
                                <i class="fas fa-graduation-cap fa-2x text-muted mb-3"></i>
                                <h5>No courses found</h5>
                                <p class="text-muted">Add your first course to get started.</p>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Subjects Tab -->
        <div class="tab-pane fade" id="subjects" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5>Subject Management</h5>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
                    <i class="fas fa-plus"></i> Add Subject
                </button>
            </div>
            
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Subject Code</th>
                            <th>Subject Name</th>
                            <th>Units</th>
                            <th>Program</th>
                            <th>Description</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $counter = 1;
                        foreach($subjects as $subject): ?>
                        <tr>
                            <td><?php echo $counter++; ?></td>
                            <td><code><?php echo htmlspecialchars($subject['subject_code']); ?></code></td>
                            <td><strong><?php echo htmlspecialchars($subject['subject_name']); ?></strong></td>
                            <td><span class="badge bg-primary"><?php echo $subject['units']; ?> units</span></td>
                            <td>
                                <?php if ($subject['program']): ?>
                                    <span class="badge bg-info"><?php echo htmlspecialchars($subject['program']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                if ($subject['description']) {
                                    echo htmlspecialchars(substr($subject['description'], 0, 50));
                                    if (strlen($subject['description']) > 50) echo '...';
                                } else {
                                    echo '<span class="text-muted">No description</span>';
                                }
                                ?>
                            </td>
                            <td class="table-actions">
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-warning btn-edit-subject" 
                                            data-id="<?php echo $subject['id']; ?>"
                                            data-code="<?php echo htmlspecialchars($subject['subject_code'], ENT_QUOTES); ?>"
                                            data-name="<?php echo htmlspecialchars($subject['subject_name'], ENT_QUOTES); ?>"
                                            data-units="<?php echo $subject['units']; ?>"
                                            data-program="<?php echo htmlspecialchars($subject['program'] ?? '', ENT_QUOTES); ?>"
                                            data-desc="<?php echo htmlspecialchars($subject['description'] ?? '', ENT_QUOTES); ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-outline-danger btn-delete-subject" 
                                            data-id="<?php echo $subject['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($subject['subject_code'], ENT_QUOTES); ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($subjects)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4">
                                <i class="fas fa-book fa-2x text-muted mb-3"></i>
                                <h5>No subjects found</h5>
                                <p class="text-muted">Add your first subject to get started.</p>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Sections Tab -->
        <div class="tab-pane fade" id="sections" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5>Section Management</h5>
                <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#addSectionModal">
                    <i class="fas fa-plus"></i> Add Section
                </button>
            </div>
            
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Section Code</th>
                            <th>Program</th>
                            <th>Year Level</th>
                            <th>Students</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $counter = 1;
                        foreach($sections as $section): ?>
                        <tr>
                            <td><?php echo $counter++; ?></td>
                            <td><code><?php echo htmlspecialchars($section['section_code']); ?></code></td>
                            <td><?php echo htmlspecialchars($section['program']); ?></td>
                            <td><span class="badge bg-info">Year <?php echo $section['year_level']; ?></span></td>
                            <td>
                                <span class="badge bg-success"><?php echo $section['student_count']; ?> students</span>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $section['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($section['status']); ?>
                                </span>
                            </td>
                            <td class="table-actions">
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-warning btn-edit-section" 
                                            data-id="<?php echo $section['id']; ?>"
                                            data-code="<?php echo htmlspecialchars($section['section_code'], ENT_QUOTES); ?>"
                                            data-program="<?php echo htmlspecialchars($section['program'], ENT_QUOTES); ?>"
                                            data-year="<?php echo $section['year_level']; ?>"
                                            data-status="<?php echo $section['status']; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-outline-danger btn-delete-section" 
                                            data-id="<?php echo $section['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($section['section_code'], ENT_QUOTES); ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($sections)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4">
                                <i class="fas fa-layer-group fa-2x text-muted mb-3"></i>
                                <h5>No sections found</h5>
                                <p class="text-muted">Add your first section to get started.</p>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Curriculum Tab -->
        <div class="tab-pane fade" id="curriculum" role="tabpanel">
            <div class="row">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Select Course</h5>
                        </div>
                        <div class="card-body">
                            <select id="courseSelect" class="form-select">
                                <option value="">Select a course...</option>
                                <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['id']; ?>">
                                    <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <hr>
                            <button id="addSubjectBtn" class="btn btn-primary w-100" disabled>
                                <i class="fas fa-plus me-1"></i> Add Subject
                            </button>
                        </div>
                    </div>
                    
                    <!-- Add Subject to Curriculum Form -->
                    <div class="card mt-3" id="addSubjectFormCard" style="display: none;">
                        <div class="card-header">
                            <h6 class="mb-0">Add Subject to Curriculum</h6>
                        </div>
                        <div class="card-body">
                            <form id="addCurriculumForm">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="action" value="add_curriculum">
                                <input type="hidden" name="course_id" id="curriculum_course_id">
                                
                                <div class="mb-3">
                                    <label class="form-label">Subject</label>
                                    <select class="form-select" name="subject_id" required>
                                        <option value="">Select Subject</option>
                                        <?php foreach($subjects as $subject): ?>
                                            <option value="<?php echo $subject['id']; ?>">
                                                <?php echo htmlspecialchars($subject['subject_code'] . ' - ' . $subject['subject_name']); ?>
                                                (<?php echo $subject['units']; ?> units)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Year Level</label>
                                        <select class="form-select" name="year_level" required>
                                            <option value="1">Year 1</option>
                                            <option value="2">Year 2</option>
                                            <option value="3">Year 3</option>
                                            <option value="4">Year 4</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Semester</label>
                                        <select class="form-select" name="semester" required>
                                            <option value="1">Semester 1</option>
                                            <option value="2">Semester 2</option>
                                        </select>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="fas fa-plus"></i> Add to Curriculum
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0" id="curriculumTitle">Course Curriculum</h5>
                        </div>
                        <div class="card-body">
                            <div id="curriculumView">
                                <div class="text-center text-muted py-5">
                                    <i class="fas fa-book-open fa-3x mb-3"></i>
                                    <p>Select a course to view its curriculum</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Calculation Tab -->
        <div class="tab-pane fade" id="calculation" role="tabpanel">
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-calculator me-2"></i>Payment Calculation
                            </h5>
                        </div>
                        <div class="card-body">
                            <!-- Global Unit Price Info -->
                            <div class="alert alert-info">
                                <h6><i class="fas fa-info-circle me-2"></i>Global Unit Price: ₱<?php echo number_format($global_unit_price, 2); ?></h6>
                                <p class="mb-0">This price applies to all courses and subjects. Only administrators can change this.</p>
                            </div>
                            
                            <!-- Course Selection -->
                            <div class="mb-4">
                                <label class="form-label">Select Course to Calculate</label>
                                <select id="calculationCourseSelect" class="form-select">
                                    <option value="">Select a course...</option>
                                    <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>">
                                        <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Calculation Results -->
                            <div id="calculationResults" style="display: none;">
                                <h5>Payment Breakdown</h5>
                                <div class="payment-calculation">
                                    <div class="calculation-row">
                                        <span>Total Units:</span>
                                        <span id="calcTotalUnits">0 units</span>
                                    </div>
                                    <div class="calculation-row">
                                        <span>Unit Price:</span>
                                        <span>₱<span id="calcUnitPrice"><?php echo number_format($global_unit_price, 2); ?></span></span>
                                    </div>
                                    <div class="calculation-row total">
                                        <span>Full Course Total:</span>
                                        <span><strong>₱<span id="calcTotalPrice">0.00</span></strong></span>
                                    </div>
                                    <hr>
                                    <div class="calculation-row">
                                        <span>Per Year Payment:</span>
                                        <span>₱<span id="calcPerYear">0.00</span></span>
                                    </div>
                                    <div class="calculation-row">
                                        <span>Per Semester Payment:</span>
                                        <span>₱<span id="calcPerSemester">0.00</span></span>
                                    </div>
                                    <div class="calculation-row">
                                        <span>Per Exam Payment (3 exams/semester):</span>
                                        <span>₱<span id="calcPerExam">0.00</span></span>
                                    </div>
                                    <div class="calculation-row">
                                        <span>Per Subject (average):</span>
                                        <span>₱<span id="calcPerSubject">0.00</span></span>
                                    </div>
                                </div>
                                
                                <!-- Student Payment Simulation -->
                                <div class="mt-4">
                                    <h6>Student Payment Simulation</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Amount Already Paid</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">₱</span>
                                                    <input type="number" id="paidAmount" class="form-control" 
                                                           step="0.01" min="0" value="0">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">&nbsp;</label>
                                                <button class="btn btn-info w-100" onclick="calculateRemaining()">
                                                    <i class="fas fa-calculator me-1"></i> Calculate Remaining
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div id="remainingCalculation" style="display: none;">
                                        <div class="alert alert-warning">
                                            <div class="calculation-row">
                                                <span>Total Course Price:</span>
                                                <span>₱<span id="remainingTotal">0.00</span></span>
                                            </div>
                                            <div class="calculation-row">
                                                <span>Amount Paid:</span>
                                                <span class="text-success">₱<span id="remainingPaid">0.00</span></span>
                                            </div>
                                            <div class="calculation-row total">
                                                <span>Remaining Balance:</span>
                                                <span class="text-danger"><strong>₱<span id="remainingBalance">0.00</span></strong></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div id="noCourseSelected" class="text-center text-muted py-5">
                                <i class="fas fa-calculator fa-3x mb-3"></i>
                                <p>Select a course to see payment calculations</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h6 class="mb-0">Quick Calculations</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Calculate for Units</label>
                                <input type="number" id="quickUnits" class="form-control" 
                                       min="1" max="500" placeholder="Enter units" value="30">
                            </div>
                            <button class="btn btn-outline-success w-100 mb-3" onclick="quickCalculate()">
                                <i class="fas fa-bolt me-1"></i> Quick Calculate
                            </button>
                            
                            <div id="quickResults" class="payment-calculation" style="display: none;">
                                <div class="calculation-row">
                                    <span>Units:</span>
                                    <span><span id="quickUnitsDisplay">0</span> units</span>
                                </div>
                                <div class="calculation-row">
                                    <span>Unit Price:</span>
                                    <span>₱<?php echo number_format($global_unit_price, 2); ?></span>
                                </div>
                                <div class="calculation-row total">
                                    <span>Total:</span>
                                    <span><strong>₱<span id="quickTotal">0.00</span></strong></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mt-3">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0">Payment Tips</h6>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled mb-0">
                                <li class="mb-2"><i class="fas fa-lightbulb text-warning me-2"></i> Most students pay per semester</li>
                                <li class="mb-2"><i class="fas fa-lightbulb text-warning me-2"></i> Payment plans can be arranged</li>
                                <li class="mb-2"><i class="fas fa-lightbulb text-warning me-2"></i> Early payment discounts available</li>
                                <li><i class="fas fa-lightbulb text-warning me-2"></i> Installment options for 3-6 months</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
<!-- Add Course Modal -->
<div class="modal fade" id="addCourseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Course</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="addCourseForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="add_course">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="course_code" class="form-label">Course Code</label>
                        <input type="text" class="form-control" id="course_code" name="course_code" required>
                    </div>
                    <div class="mb-3">
                        <label for="course_name" class="form-label">Course Name</label>
                        <input type="text" class="form-control" id="course_name" name="course_name" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="total_units" class="form-label">Total Units</label>
                            <input type="number" class="form-control" id="total_units" name="total_units" 
                                   min="1" max="500" value="30" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="duration_years" class="form-label">Duration (Years)</label>
                            <select class="form-select" id="duration_years" name="duration_years" required>
                                <option value="1">1 Year</option>
                                <option value="2" selected>2 Years</option>
                                <option value="3">3 Years</option>
                                <option value="4">4 Years</option>
                                <option value="5">5 Years</option>
                            </select>
                        </div>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Unit Price: <strong>₱<?php echo number_format($global_unit_price, 2); ?></strong> (global setting)
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Course</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Course Modal -->
<div class="modal fade" id="editCourseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="editCourseForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="edit_course">
                <input type="hidden" name="course_id" id="edit_course_id">
                
                <div class="modal-header">
                    <h5 class="modal-title">Edit Course</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_course_code" class="form-label">Course Code</label>
                        <input type="text" class="form-control" id="edit_course_code" name="course_code" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_course_name" class="form-label">Course Name</label>
                        <input type="text" class="form-control" id="edit_course_name" name="course_name" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_total_units" class="form-label">Total Units</label>
                            <input type="number" class="form-control" id="edit_total_units" name="total_units" 
                                   min="1" max="500" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_duration_years" class="form-label">Duration (Years)</label>
                            <select class="form-select" id="edit_duration_years" name="duration_years" required>
                                <option value="1">1 Year</option>
                                <option value="2">2 Years</option>
                                <option value="3">3 Years</option>
                                <option value="4">4 Years</option>
                                <option value="5">5 Years</option>
                            </select>
                        </div>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Unit Price: <strong>₱<?php echo number_format($global_unit_price, 2); ?></strong> (global setting)
                    </div>
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="edit_status" class="form-label">Status</label>
                        <select class="form-select" id="edit_status" name="status" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Course</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Course Modal -->
<div class="modal fade" id="deleteCourseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Delete Course</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the course: <strong id="delete_course_name"></strong>?</p>
                <p class="text-danger"><small>This action cannot be undone.</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteCourse">Delete</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Subject Modal -->
<div class="modal fade" id="addSubjectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Subject</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="addSubjectForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="add_subject">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="subject_code" class="form-label">Subject Code</label>
                        <input type="text" class="form-control" id="subject_code" name="subject_code" required>
                    </div>
                    <div class="mb-3">
                        <label for="subject_name" class="form-label">Subject Name</label>
                        <input type="text" class="form-control" id="subject_name" name="subject_name" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="units" class="form-label">Units</label>
                            <input type="number" class="form-control" id="units" name="units" 
                                   min="1" max="10" value="3" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="program" class="form-label">Program (Optional)</label>
                            <input type="text" class="form-control" id="program" name="program" list="programList">
                            <datalist id="programList">
                                <?php foreach($programs as $program): ?>
                                <option value="<?php echo htmlspecialchars($program); ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Subject Price: <strong>₱<span id="subjectPrice">0.00</span></strong> (units × ₱<?php echo number_format($global_unit_price, 2); ?>)
                    </div>
                    <div class="mb-3">
                        <label for="subject_description" class="form-label">Description (Optional)</label>
                        <textarea class="form-control" id="subject_description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Subject</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Subject Modal -->
<div class="modal fade" id="editSubjectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="editSubjectForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="edit_subject">
                <input type="hidden" name="subject_id" id="edit_subject_id">
                
                <div class="modal-header">
                    <h5 class="modal-title">Edit Subject</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_subject_code" class="form-label">Subject Code</label>
                        <input type="text" class="form-control" id="edit_subject_code" name="subject_code" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_subject_name" class="form-label">Subject Name</label>
                        <input type="text" class="form-control" id="edit_subject_name" name="subject_name" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_units" class="form-label">Units</label>
                            <input type="number" class="form-control" id="edit_units" name="units" 
                                   min="1" max="10" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_program" class="form-label">Program (Optional)</label>
                            <input type="text" class="form-control" id="edit_program" name="program" list="programList">
                        </div>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Subject Price: <strong>₱<span id="editSubjectPrice">0.00</span></strong> (units × ₱<?php echo number_format($global_unit_price, 2); ?>)
                    </div>
                    <div class="mb-3">
                        <label for="edit_subject_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_subject_description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Subject</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Subject Modal -->
<div class="modal fade" id="deleteSubjectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Delete Subject</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the subject: <strong id="delete_subject_name"></strong>?</p>
                <p class="text-danger"><small>This action cannot be undone.</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteSubject">Delete</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Section Modal -->
<div class="modal fade" id="addSectionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Section</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="addSectionForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="add_section">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="section_code" class="form-label">Section Code</label>
                        <input type="text" class="form-control" id="section_code" name="section_code" required>
                    </div>
                    <div class="mb-3">
                        <label for="program" class="form-label">Program</label>
                        <input type="text" class="form-control" id="program" name="program" required>
                    </div>
                    <div class="mb-3">
                        <label for="year_level" class="form-label">Year Level</label>
                        <select class="form-select" id="year_level" name="year_level" required>
                            <option value="">Select Year Level</option>
                            <?php for($i = 1; $i <= 6; $i++): ?>
                                <option value="<?php echo $i; ?>">Year <?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Section</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Section Modal -->
<div class="modal fade" id="editSectionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="editSectionForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="edit_section">
                <input type="hidden" name="section_id" id="edit_section_id">
                
                <div class="modal-header">
                    <h5 class="modal-title">Edit Section</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_section_code" class="form-label">Section Code</label>
                        <input type="text" class="form-control" id="edit_section_code" name="section_code" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_program" class="form-label">Program</label>
                        <input type="text" class="form-control" id="edit_program" name="program" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_year_level" class="form-label">Year Level</label>
                        <select class="form-select" id="edit_year_level" name="year_level" required>
                            <?php for($i = 1; $i <= 6; $i++): ?>
                                <option value="<?php echo $i; ?>">Year <?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_section_status" class="form-label">Status</label>
                        <select class="form-select" id="edit_section_status" name="status" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Section</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Section Modal -->
<div class="modal fade" id="deleteSectionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Delete Section</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the section: <strong id="delete_section_name"></strong>?</p>
                <p class="text-danger"><small>This action cannot be undone.</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-danger" id="confirmDeleteSection">Delete</button>
            </div>
        </div>
    </div>
</div>

<!-- Update Unit Price Modal -->
<div class="modal fade" id="updateUnitPriceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="updateUnitPriceForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="update_global_unit_price">
                
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Update Global Unit Price</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <h6 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Important Note</h6>
                        <p class="mb-0">Changing the unit price will affect ALL payment calculations for ALL courses and subjects.</p>
                    </div>
                    
                    <div class="mb-3">
                        <label for="unit_price" class="form-label">New Unit Price</label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" class="form-control" id="unit_price" name="unit_price" 
                                   step="0.01" min="1" value="<?php echo $global_unit_price; ?>" required>
                        </div>
                        <div class="form-text">This is the price per unit for all courses and subjects.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Update Global Price</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Curriculum Modal -->
<div class="modal fade" id="viewCurriculumModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Course Curriculum</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="curriculumModalContent">
                    <!-- Will be loaded via AJAX -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Hidden forms for deletions -->
<form method="POST" id="deleteCourseForm" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
    <input type="hidden" name="action" value="delete_course">
    <input type="hidden" name="course_id" id="delete_course_id">
</form>

<form method="POST" id="deleteSubjectForm" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
    <input type="hidden" name="action" value="delete_subject">
    <input type="hidden" name="subject_id" id="delete_subject_id">
</form>

<form method="POST" id="deleteSectionForm" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
    <input type="hidden" name="action" value="delete_section">
    <input type="hidden" name="section_id" id="delete_section_id">
</form>

<form method="POST" id="deleteCurriculumForm" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
    <input type="hidden" name="action" value="remove_curriculum">
    <input type="hidden" name="curriculum_id" id="delete_curriculum_id">
</form>

<script>
const globalUnitPrice = <?php echo $global_unit_price; ?>;
const coursesData = <?php echo json_encode($courses); ?>;

// Edit course
document.querySelectorAll('.btn-edit-course').forEach(btn => {
    btn.addEventListener('click', function() {
        const courseId = this.dataset.id;
        const courseCode = this.dataset.code;
        const courseName = this.dataset.name;
        const description = this.dataset.desc;
        const totalUnits = this.dataset.units;
        const durationYears = this.dataset.years;
        const status = this.dataset.status;
        
        document.getElementById('edit_course_id').value = courseId;
        document.getElementById('edit_course_code').value = courseCode;
        document.getElementById('edit_course_name').value = courseName;
        document.getElementById('edit_description').value = description;
        document.getElementById('edit_total_units').value = totalUnits;
        document.getElementById('edit_duration_years').value = durationYears;
        document.getElementById('edit_status').value = status;
        
        new bootstrap.Modal(document.getElementById('editCourseModal')).show();
    });
});

// Delete course
document.querySelectorAll('.btn-delete-course').forEach(btn => {
    btn.addEventListener('click', function() {
        const courseId = this.dataset.id;
        const courseName = this.dataset.name;
        
        document.getElementById('delete_course_name').textContent = courseName;
        document.getElementById('confirmDeleteCourse').dataset.id = courseId;
        new bootstrap.Modal(document.getElementById('deleteCourseModal')).show();
    });
});

// Confirm delete course
document.getElementById('confirmDeleteCourse').addEventListener('click', function() {
    const courseId = this.dataset.id;
    document.getElementById('delete_course_id').value = courseId;
    document.getElementById('deleteCourseForm').submit();
});

// Edit subject
document.querySelectorAll('.btn-edit-subject').forEach(btn => {
    btn.addEventListener('click', function() {
        const subjectId = this.dataset.id;
        const subjectCode = this.dataset.code;
        const subjectName = this.dataset.name;
        const units = this.dataset.units;
        const program = this.dataset.program;
        const desc = this.dataset.desc;
        
        document.getElementById('edit_subject_id').value = subjectId;
        document.getElementById('edit_subject_code').value = subjectCode;
        document.getElementById('edit_subject_name').value = subjectName;
        document.getElementById('edit_units').value = units;
        document.getElementById('edit_program').value = program;
        document.getElementById('edit_subject_description').value = desc;
        
        // Calculate and display subject price
        const subjectPrice = units * globalUnitPrice;
        document.getElementById('editSubjectPrice').textContent = subjectPrice.toFixed(2);
        
        new bootstrap.Modal(document.getElementById('editSubjectModal')).show();
    });
});

// Delete subject
document.querySelectorAll('.btn-delete-subject').forEach(btn => {
    btn.addEventListener('click', function() {
        const subjectId = this.dataset.id;
        const subjectName = this.dataset.name;
        
        document.getElementById('delete_subject_name').textContent = subjectName;
        document.getElementById('confirmDeleteSubject').dataset.id = subjectId;
        new bootstrap.Modal(document.getElementById('deleteSubjectModal')).show();
    });
});

// Confirm delete subject
document.getElementById('confirmDeleteSubject').addEventListener('click', function() {
    const subjectId = this.dataset.id;
    document.getElementById('delete_subject_id').value = subjectId;
    document.getElementById('deleteSubjectForm').submit();
});

// Edit section
document.querySelectorAll('.btn-edit-section').forEach(btn => {
    btn.addEventListener('click', function() {
        const sectionId = this.dataset.id;
        const sectionCode = this.dataset.code;
        const program = this.dataset.program;
        const yearLevel = this.dataset.year;
        const status = this.dataset.status;
        
        document.getElementById('edit_section_id').value = sectionId;
        document.getElementById('edit_section_code').value = sectionCode;
        document.getElementById('edit_program').value = program;
        document.getElementById('edit_year_level').value = yearLevel;
        document.getElementById('edit_section_status').value = status;
        
        new bootstrap.Modal(document.getElementById('editSectionModal')).show();
    });
});

// Delete section
document.querySelectorAll('.btn-delete-section').forEach(btn => {
    btn.addEventListener('click', function() {
        const sectionId = this.dataset.id;
        const sectionName = this.dataset.name;
        
        document.getElementById('delete_section_name').textContent = sectionName;
        document.getElementById('confirmDeleteSection').dataset.id = sectionId;
        new bootstrap.Modal(document.getElementById('deleteSectionModal')).show();
    });
});

// Confirm delete section
document.getElementById('confirmDeleteSection').addEventListener('click', function() {
    const sectionId = this.dataset.id;
    document.getElementById('delete_section_id').value = sectionId;
    document.getElementById('deleteSectionForm').submit();
});

// View course curriculum
function viewCourseCurriculum(courseId) {
    fetch(`ajax_handler.php?action=get_course_curriculum&course_id=${courseId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('curriculumModalContent').innerHTML = html;
            new bootstrap.Modal(document.getElementById('viewCurriculumModal')).show();
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('curriculumModalContent').innerHTML = `
                <div class="alert alert-danger">
                    Error loading curriculum
                </div>
            `;
            new bootstrap.Modal(document.getElementById('viewCurriculumModal')).show();
        });
}

// Curriculum tab functionality
document.getElementById('courseSelect').addEventListener('change', function() {
    const courseId = this.value;
    const addSubjectBtn = document.getElementById('addSubjectBtn');
    
    if (courseId) {
        addSubjectBtn.disabled = false;
        document.getElementById('addSubjectFormCard').style.display = 'block';
        document.getElementById('curriculum_course_id').value = courseId;
        
        // Update curriculum title
        const selectedOption = this.options[this.selectedIndex];
        document.getElementById('curriculumTitle').textContent = 'Curriculum: ' + selectedOption.text;
        
        // Load curriculum
        loadCurriculum(courseId);
    } else {
        addSubjectBtn.disabled = true;
        document.getElementById('addSubjectFormCard').style.display = 'none';
        document.getElementById('curriculumTitle').textContent = 'Course Curriculum';
        document.getElementById('curriculumView').innerHTML = `
            <div class="text-center text-muted py-5">
                <i class="fas fa-book-open fa-3x mb-3"></i>
                <p>Select a course to view its curriculum</p>
            </div>
        `;
    }
});

function loadCurriculum(courseId) {
    fetch(`ajax_handler.php?action=_load_curriculum.php?course_id=${courseId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('curriculumView').innerHTML = html;
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('curriculumView').innerHTML = `
                <div class="alert alert-danger">
                    Error loading curriculum
                </div>
            `;
        });
}

// Add curriculum form
document.getElementById('addCurriculumForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('manage_courses.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Subject added to curriculum successfully!');
            const courseId = document.getElementById('curriculum_course_id').value;
            loadCurriculum(courseId);
            this.reset();
        } else {
            alert('Error: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    });
});

// Remove curriculum item
function removeCurriculumItem(curriculumId, subjectName) {
    if (confirm(`Remove "${subjectName}" from curriculum?`)) {
        document.getElementById('delete_curriculum_id').value = curriculumId;
        document.getElementById('deleteCurriculumForm').submit();
    }
}

// Payment calculation
document.getElementById('calculationCourseSelect').addEventListener('change', function() {
    const courseId = this.value;
    
    if (courseId) {
        // Find the selected course
        const course = coursesData.find(c => c.id == courseId);
        if (course) {
            calculateCoursePayment(course);
            document.getElementById('calculationResults').style.display = 'block';
            document.getElementById('noCourseSelected').style.display = 'none';
        }
    } else {
        document.getElementById('calculationResults').style.display = 'none';
        document.getElementById('noCourseSelected').style.display = 'block';
        document.getElementById('remainingCalculation').style.display = 'none';
    }
});

function calculateCoursePayment(course) {
    const totalUnits = parseInt(course.total_units);
    const durationYears = parseInt(course.duration_years);
    
    // Calculate various totals
    const totalPrice = totalUnits * globalUnitPrice;
    const perYear = totalPrice / durationYears;
    const perSemester = perYear / 2; // 2 semesters per year
    const perExam = perSemester / 3; // 3 exams per semester
    const perSubject = totalPrice / (course.subject_count || 1); // Average per subject
    
    // Update display
    document.getElementById('calcTotalUnits').textContent = totalUnits + ' units';
    document.getElementById('calcTotalPrice').textContent = totalPrice.toFixed(2);
    document.getElementById('calcPerYear').textContent = perYear.toFixed(2);
    document.getElementById('calcPerSemester').textContent = perSemester.toFixed(2);
    document.getElementById('calcPerExam').textContent = perExam.toFixed(2);
    document.getElementById('calcPerSubject').textContent = perSubject.toFixed(2);
    
    // Store course data for remaining calculation
    document.getElementById('calculationResults').dataset.courseId = course.id;
    document.getElementById('calculationResults').dataset.totalPrice = totalPrice;
    
    // Reset paid amount
    document.getElementById('paidAmount').value = 0;
    document.getElementById('remainingCalculation').style.display = 'none';
}

function calculateRemaining() {
    const totalPrice = parseFloat(document.getElementById('calculationResults').dataset.totalPrice);
    const paidAmount = parseFloat(document.getElementById('paidAmount').value) || 0;
    const remainingBalance = totalPrice - paidAmount;
    
    document.getElementById('remainingTotal').textContent = totalPrice.toFixed(2);
    document.getElementById('remainingPaid').textContent = paidAmount.toFixed(2);
    document.getElementById('remainingBalance').textContent = remainingBalance.toFixed(2);
    
    document.getElementById('remainingCalculation').style.display = 'block';
}

// Quick calculation
function quickCalculate() {
    const units = parseInt(document.getElementById('quickUnits').value) || 0;
    if (units > 0) {
        const total = units * globalUnitPrice;
        
        document.getElementById('quickUnitsDisplay').textContent = units;
        document.getElementById('quickTotal').textContent = total.toFixed(2);
        document.getElementById('quickResults').style.display = 'block';
    }
}

// Auto-calculate subject price when units change
document.getElementById('units')?.addEventListener('input', function() {
    const units = parseInt(this.value) || 0;
    const price = units * globalUnitPrice;
    document.getElementById('subjectPrice').textContent = price.toFixed(2);
});

document.getElementById('edit_units')?.addEventListener('input', function() {
    const units = parseInt(this.value) || 0;
    const price = units * globalUnitPrice;
    document.getElementById('editSubjectPrice').textContent = price.toFixed(2);
});

// Auto-uppercase for codes
document.querySelectorAll('input[name="course_code"], input[name="subject_code"], input[name="section_code"]').forEach(input => {
    input.addEventListener('input', function() {
        this.value = this.value.toUpperCase();
    });
});

// Initialize subject price on page load
document.addEventListener('DOMContentLoaded', function() {
    // Calculate initial subject price for add form
    const units = parseInt(document.getElementById('units')?.value) || 3;
    const price = units * globalUnitPrice;
    if (document.getElementById('subjectPrice')) {
        document.getElementById('subjectPrice').textContent = price.toFixed(2);
    }
    
    // Handle URL parameters for active tab
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');
    if (tabParam) {
        const tab = document.getElementById(tabParam + '-tab');
        if (tab) {
            tab.click();
        }
    }
    
    // Handle course_id parameter for curriculum tab
    const courseIdParam = urlParams.get('course_id');
    if (courseIdParam && document.getElementById('courseSelect')) {
        document.getElementById('courseSelect').value = courseIdParam;
        document.getElementById('courseSelect').dispatchEvent(new Event('change'));
    }
});
</script>

<?php renderPageEnd(); ?>