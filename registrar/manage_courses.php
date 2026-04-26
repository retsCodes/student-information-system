<?php

require_once '../init.php';
require_once '../theme.php';
require_once '../layout.php';

requireRole('registrar');

$pdo = getDBConnection();
$error = '';
$success = '';

// Get global unit price
$stmt = $pdo->query("SELECT value FROM settings WHERE name = 'unit_price'");
$global_unit_price = $stmt->fetch(PDO::FETCH_ASSOC);
$global_unit_price = $global_unit_price ? floatval($global_unit_price['value']) : 1000.00;

// Helper function to recalculate course total units
function recalculateCourseTotalUnits($pdo, $course_id) {
    $stmt = $pdo->prepare("SELECT SUM(s.units) as total_units 
                           FROM course_curriculum cc 
                           JOIN subjects s ON cc.subject_id = s.id 
                           WHERE cc.course_id = ?");
    $stmt->execute([$course_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_units = $result['total_units'] ?? 0;
    
    $stmt = $pdo->prepare("UPDATE courses SET total_units = ? WHERE id = ?");
    $stmt->execute([$total_units, $course_id]);
    
    return $total_units;
}


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
                $duration_years = intval($_POST['duration_years'] ?? 4);
                $status = $_POST['status'] ?? 'active';
                
                $errors = [];
                if (empty($course_code)) $errors[] = 'Course code is required.';
                if (empty($course_name)) $errors[] = 'Course name is required.';
                if ($duration_years <= 0) $errors[] = 'Duration must be at least 1 year.';
                
                if (!empty($errors)) {
                    $error = implode(' ', $errors);
                } else {
                    try {
                        $stmt = $pdo->prepare("SELECT id FROM courses WHERE course_code = ?");
                        $stmt->execute([$course_code]);
                        if ($stmt->fetch()) {
                            $error = 'Course code already exists.';
                        } else {
                            $stmt = $pdo->prepare("INSERT INTO courses (course_code, course_name, description, total_units, duration_years, status) 
                                                  VALUES (?, ?, ?, 0, ?, ?)");
                            $stmt->execute([$course_code, $course_name, $description, $duration_years, $status]);
                            
                            logActivity($_SESSION['user_id'], 'Course Created', 
                                       "Created course: {$course_code} - {$course_name}");
                            $success = "Course created successfully. Total units will be calculated from curriculum.";
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
                $duration_years = intval($_POST['duration_years'] ?? 4);
                $status = $_POST['status'] ?? 'active';
                
                $errors = [];
                if ($course_id <= 0) $errors[] = 'Invalid course ID.';
                if (empty($course_code)) $errors[] = 'Course code is required.';
                if (empty($course_name)) $errors[] = 'Course name is required.';
                if ($duration_years <= 0) $errors[] = 'Duration must be at least 1 year.';
                
                if (!empty($errors)) {
                    $error = implode(' ', $errors);
                } else {
                    try {
                        $stmt = $pdo->prepare("SELECT id FROM courses WHERE course_code = ? AND id != ?");
                        $stmt->execute([$course_code, $course_id]);
                        if ($stmt->fetch()) {
                            $error = 'Course code already exists for another course.';
                        } else {
                            $stmt = $pdo->prepare("UPDATE courses SET course_code = ?, course_name = ?, description = ?, 
                                                  duration_years = ?, status = ? WHERE id = ?");
                            $stmt->execute([$course_code, $course_name, $description, $duration_years, $status, $course_id]);
                            
                            recalculateCourseTotalUnits($pdo, $course_id);
                            
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
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM student_course_enrollment WHERE course_id = ? AND status = 'active'");
                    $stmt->execute([$course_id]);
                    $student_count = $stmt->fetchColumn();
                    
                    if ($student_count > 0) {
                        $error = 'Cannot delete course: There are enrolled students.';
                    } else {
                        try {
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
                $semester = $_POST['semester'] ?? '1st';
                
                $errors = [];
                if ($course_id <= 0) $errors[] = 'Invalid course ID.';
                if ($subject_id <= 0) $errors[] = 'Invalid subject ID.';
                
                if (!empty($errors)) {
                    $error = implode(' ', $errors);
                } else {
                    try {
                        $stmt = $pdo->prepare("SELECT id FROM course_curriculum WHERE course_id = ? AND subject_id = ? AND year_level = ? AND semester = ?");
                        $stmt->execute([$course_id, $subject_id, $year_level, $semester]);
                        if ($stmt->fetch()) {
                            $error = 'Subject already exists in curriculum for this year and semester.';
                        } else {
                            $stmt = $pdo->prepare("INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, is_required) 
                                                  VALUES (?, ?, ?, ?, 1)");
                            $stmt->execute([$course_id, $subject_id, $year_level, $semester]);
                            
                            recalculateCourseTotalUnits($pdo, $course_id);
                            
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
                    $course_id = intval($_POST['course_id'] ?? 0);
                    
                    if ($curriculum_id <= 0) {
                        echo json_encode(['success' => false, 'message' => 'Invalid curriculum ID']);
                        exit;
                    }
                    
                
            case 'add_subject':
                $subject_code = sanitizeInput($_POST['subject_code'] ?? '');
                $subject_name = sanitizeInput($_POST['subject_name'] ?? '');
                $units = intval($_POST['units'] ?? 0);
                $description = sanitizeInput($_POST['description'] ?? '');
                $program = sanitizeInput($_POST['program'] ?? '');
                $year_level = intval($_POST['subject_year_level'] ?? 1);
                $semester = $_POST['subject_semester'] ?? '1st';
                
                $errors = [];
                if (empty($subject_code)) $errors[] = 'Subject code is required.';
                if (empty($subject_name)) $errors[] = 'Subject name is required.';
                if ($units <= 0) $errors[] = 'Units must be greater than 0.';
                
                if (!empty($errors)) {
                    $error = implode(' ', $errors);
                } else {
                    try {
                        $stmt = $pdo->prepare("SELECT id FROM subjects WHERE subject_code = ?");
                        $stmt->execute([$subject_code]);
                        if ($stmt->fetch()) {
                            $error = 'Subject code already exists.';
                        } else {
                            $stmt = $pdo->prepare("INSERT INTO subjects (subject_code, subject_name, units, description, program, year_level, semester) 
                                                  VALUES (?, ?, ?, ?, ?, ?, ?)");
                            $stmt->execute([$subject_code, $subject_name, $units, $description, $program, $year_level, $semester]);
                            
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
                $year_level = intval($_POST['subject_year_level'] ?? 1);
                $semester = $_POST['subject_semester'] ?? '1st';
                
                $errors = [];
                if ($subject_id <= 0) $errors[] = 'Invalid subject ID.';
                if (empty($subject_code)) $errors[] = 'Subject code is required.';
                if (empty($subject_name)) $errors[] = 'Subject name is required.';
                if ($units <= 0) $errors[] = 'Units must be greater than 0.';
                
                if (!empty($errors)) {
                    $error = implode(' ', $errors);
                } else {
                    try {
                        $stmt = $pdo->prepare("SELECT id FROM subjects WHERE subject_code = ? AND id != ?");
                        $stmt->execute([$subject_code, $subject_id]);
                        if ($stmt->fetch()) {
                            $error = 'Subject code already exists for another subject.';
                        } else {
                            $old_units = $pdo->prepare("SELECT units FROM subjects WHERE id = ?");
                            $old_units->execute([$subject_id]);
                            $old_units_value = $old_units->fetchColumn();
                            
                            $stmt = $pdo->prepare("UPDATE subjects SET subject_code = ?, subject_name = ?, units = ?, 
                                                  description = ?, program = ?, year_level = ?, semester = ? WHERE id = ?");
                            $stmt->execute([$subject_code, $subject_name, $units, $description, $program, $year_level, $semester, $subject_id]);
                            
                            if ($old_units_value != $units) {
                                $stmt = $pdo->prepare("SELECT DISTINCT course_id FROM course_curriculum WHERE subject_id = ?");
                                $stmt->execute([$subject_id]);
                                $courses = $stmt->fetchAll(PDO::FETCH_COLUMN);
                                foreach ($courses as $course_id) {
                                    recalculateCourseTotalUnits($pdo, $course_id);
                                }
                            }
                            
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
                $section_name = sanitizeInput($_POST['section_name'] ?? '');
                $program = sanitizeInput($_POST['program'] ?? '');
                $course_id = intval($_POST['course_id'] ?? 0);
                $year_level = intval($_POST['year_level'] ?? 1);
                $semester = $_POST['section_semester'] ?? '1st';
                
                $errors = [];
                if (empty($section_code)) $errors[] = 'Section code is required.';
                if (empty($program)) $errors[] = 'Program is required.';
                if ($year_level <= 0) $errors[] = 'Year level must be greater than 0.';
                
                if (!empty($errors)) {
                    $error = implode(' ', $errors);
                } else {
                    try {
                        $stmt = $pdo->prepare("SELECT id FROM sections WHERE section_code = ?");
                        $stmt->execute([$section_code]);
                        if ($stmt->fetch()) {
                            $error = 'Section code already exists.';
                        } else {
                            $stmt = $pdo->prepare("INSERT INTO sections (section_code, section_name, program, course_id, year_level, semester, status) 
                                                VALUES (?, ?, ?, ?, ?, ?, 'active')");
                            $stmt->execute([$section_code, $section_name, $program, $course_id ?: null, $year_level, $semester]);
                            
                            $new_section_id = $pdo->lastInsertId();
                            
                            logActivity($_SESSION['user_id'], 'Section Created', 
                                    "Created section: {$section_code} - {$program}");
                            
                            // Return JSON for AJAX response
                            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                                echo json_encode(['success' => true, 'section_id' => $new_section_id, 'section_code' => $section_code, 'message' => 'Section created successfully']);
                                exit;
                            } else {
                                $success = "Section created successfully.";
                            }
                        }
                    } catch(Exception $e) {
                        $error = "Failed to create section: " . $e->getMessage();
                    }
                }
                break;
                
            case 'edit_section':
                $section_id = intval($_POST['section_id'] ?? 0);
                $section_code = sanitizeInput($_POST['section_code'] ?? '');
                $section_name = sanitizeInput($_POST['section_name'] ?? '');
                $program = sanitizeInput($_POST['program'] ?? '');
                $course_id = intval($_POST['course_id'] ?? 0);
                $year_level = intval($_POST['year_level'] ?? 1);
                $semester = $_POST['section_semester'] ?? '1st';
                $status = $_POST['status'] ?? 'active';
                
                $errors = [];
                if ($section_id <= 0) $errors[] = 'Invalid section ID.';
                if (empty($section_code)) $errors[] = 'Section code is required.';
                if (empty($program)) $errors[] = 'Program is required.';
                if ($year_level <= 0) $errors[] = 'Year level must be greater than 0.';
                
                if (!empty($errors)) {
                    $error = implode(' ', $errors);
                } else {
                    try {
                        $stmt = $pdo->prepare("SELECT id FROM sections WHERE section_code = ? AND id != ?");
                        $stmt->execute([$section_code, $section_id]);
                        if ($stmt->fetch()) {
                            $error = 'Section code already exists for another section.';
                        } else {
                            $stmt = $pdo->prepare("UPDATE sections SET section_code = ?, section_name = ?, program = ?, course_id = ?, year_level = ?, semester = ?, status = ? WHERE id = ?");
                            $stmt->execute([$section_code, $section_name, $program, $course_id ?: null, $year_level, $semester, $status, $section_id]);
                            
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

                // Add this case inside your POST switch statement
                case 'add_subject_to_section':
                    $subject_id = intval($_POST['subject_id'] ?? 0);
                    $section_id = intval($_POST['section_id'] ?? 0);
                    
                    if ($subject_id > 0 && $section_id > 0) {
                        try {
                            $stmt = $pdo->prepare("INSERT IGNORE INTO subject_sections (subject_id, section_id) VALUES (?, ?)");
                            $stmt->execute([$subject_id, $section_id]);
                            $success = "Subject added to section successfully!";
                        } catch(Exception $e) {
                            $error = "Failed to add subject: " . $e->getMessage();
                        }
                    }
                    break;

                case 'remove_subject_from_section':
                    $subject_id = intval($_POST['subject_id'] ?? 0);
                    $section_id = intval($_POST['section_id'] ?? 0);
                    
                    if ($subject_id > 0 && $section_id > 0) {
                        try {
                            $stmt = $pdo->prepare("DELETE FROM subject_sections WHERE subject_id = ? AND section_id = ?");
                            $stmt->execute([$subject_id, $section_id]);
                            $success = "Subject removed from section successfully!";
                        } catch(Exception $e) {
                            $error = "Failed to remove subject: " . $e->getMessage();
                        }
                    }
                    break;
                    
        }
    }
}

// Get all courses with calculated totals
$courses = $pdo->query("SELECT c.*, 
                        COUNT(DISTINCT cc.id) as subject_count,
                        COUNT(DISTINCT sce.student_id) as student_count
                        FROM courses c
                        LEFT JOIN course_curriculum cc ON c.id = cc.course_id
                        LEFT JOIN student_course_enrollment sce ON c.id = sce.course_id AND sce.status = 'active'
                        GROUP BY c.id
                        ORDER BY c.course_code")->fetchAll(PDO::FETCH_ASSOC);

$subjects = $pdo->query("SELECT s.* FROM subjects s ORDER BY s.subject_code")->fetchAll(PDO::FETCH_ASSOC);

$sections = $pdo->query("
    SELECT s.*, c.course_code, c.course_name,
        (SELECT COUNT(*) FROM student_sections ss WHERE ss.section_id = s.id) as student_count,
        (SELECT COUNT(*) FROM subject_sections ss WHERE ss.section_id = s.id) as subject_count
    FROM sections s
    LEFT JOIN courses c ON s.course_id = c.id
    ORDER BY s.year_level, s.section_code
")->fetchAll(PDO::FETCH_ASSOC);

$programs = $pdo->query("SELECT DISTINCT program FROM subjects WHERE program IS NOT NULL AND program != '' ORDER BY program")->fetchAll(PDO::FETCH_COLUMN);
if (empty($programs)) {
    $programs = ['BS Information Technology', 'BS Computer Science', 'BS Business Administration', 'BS Accountancy', 'BS Criminology', 'BS Psychology', 'BS Secondary Education', 'Associate in Computer Technology'];
}

$all_courses = $pdo->query("SELECT id, course_code, course_name FROM courses WHERE status = 'active' ORDER BY course_code")->fetchAll(PDO::FETCH_ASSOC);

renderPageStart('Manage Courses', 'registrar', 'manage_courses.php');
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
.table-actions {
    white-space: nowrap;
}
.table th:first-child, .table td:first-child {
    text-align: center;
    width: 60px;
}
.curriculum-summary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 20px;
}
.curriculum-card {
    border-left: 4px solid #007bff;
    margin-bottom: 15px;
}
.curriculum-card .card-header {
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    padding: 10px 15px;
}
.curriculum-card .card-body {
    padding: 15px;
}
.is-invalid {
    border-color: #dc3545 !important;
}
.invalid-feedback {
    display: block;
    color: #dc3545;
    font-size: 0.875em;
    margin-top: 0.25rem;
}
.required-field::after {
    content: "*";
    color: #dc3545;
    margin-left: 4px;
}
/* Make assign modal footer sticky */
#assignStudentsDynamicModal .modal-dialog {
    height: 80vh;
    display: flex;
    flex-direction: column;
    margin: 1.75rem auto;
}
#assignStudentsDynamicModal .modal-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
#assignStudentsDynamicModal .modal-body {
    flex: 1;
    overflow-y: auto;
}
#assignStudentsDynamicModal .modal-footer {
    flex-shrink: 0;
}
</style>

<div class="container-fluid mt-4">
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-graduation-cap me-2"></i>Course & Subject Management</h2>
        <div>
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
                            <td><?php echo $course['duration_years']; ?> years</span></td>
                            <td><span class="badge bg-primary"><?php echo $course['total_units']; ?> units</span></td>
                            <td><strong>₱<?php echo number_format($total_price, 2); ?></strong></td>
                            <td><span class="badge bg-info"><?php echo $course['subject_count']; ?> subjects</span></td>
                            <td><span class="badge bg-success"><?php echo $course['student_count']; ?> students</span></td>
                            <td><span class="badge bg-<?php echo $course['status'] === 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($course['status']); ?></span></td>
                            <td class="table-actions">
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-warning edit-course-btn" 
                                            data-id="<?php echo $course['id']; ?>"
                                            data-code="<?php echo htmlspecialchars($course['course_code'], ENT_QUOTES); ?>"
                                            data-name="<?php echo htmlspecialchars($course['course_name'], ENT_QUOTES); ?>"
                                            data-desc="<?php echo htmlspecialchars($course['description'], ENT_QUOTES); ?>"
                                            data-years="<?php echo $course['duration_years']; ?>"
                                            data-status="<?php echo $course['status']; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-outline-danger delete-course-btn" 
                                            data-id="<?php echo $course['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($course['course_code'], ENT_QUOTES); ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <button class="btn btn-outline-info view-course-details-btn" data-id="<?php echo $course['id']; ?>">
                                        <i class="fas fa-info-circle"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
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
                            <th>Year Level</th>
                            <th>Semester</th>
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
                            <td><?php if ($subject['program']): ?><span class="badge bg-info"><?php echo htmlspecialchars($subject['program']); ?></span><?php else: ?><span class="text-muted">-</span><?php endif; ?></td>
                            <td><?php echo $subject['year_level'] ? 'Year ' . $subject['year_level'] : '-'; ?></td>
                            <td><?php echo $subject['semester'] ?? '-'; ?></td>
                            <td><?php echo htmlspecialchars(substr($subject['description'] ?? '', 0, 50)); ?><?php echo strlen($subject['description'] ?? '') > 50 ? '...' : ''; ?></td>
                            <td class="table-actions">
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-warning edit-subject-btn" 
                                            data-id="<?php echo $subject['id']; ?>"
                                            data-code="<?php echo htmlspecialchars($subject['subject_code'], ENT_QUOTES); ?>"
                                            data-name="<?php echo htmlspecialchars($subject['subject_name'], ENT_QUOTES); ?>"
                                            data-units="<?php echo $subject['units']; ?>"
                                            data-program="<?php echo htmlspecialchars($subject['program'] ?? '', ENT_QUOTES); ?>"
                                            data-year="<?php echo $subject['year_level']; ?>"
                                            data-semester="<?php echo $subject['semester']; ?>"
                                            data-desc="<?php echo htmlspecialchars($subject['description'] ?? '', ENT_QUOTES); ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-outline-danger delete-subject-btn" 
                                            data-id="<?php echo $subject['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($subject['subject_code'], ENT_QUOTES); ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <button class="btn btn-outline-info view-subject-details-btn" data-id="<?php echo $subject['id']; ?>">
                                        <i class="fas fa-info-circle"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
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
                            <th>Section Name</th>
                            <th>Program</th>
                            <th>Course</th>
                            <th>Year Level</th>
                            <th>Semester</th>
                            <th>Subjects</th>
                            <th>Students</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $counter = 1;
                        foreach($sections as $section): 
                        ?>
                        <tr>
                            <td><?php echo $counter++; ?></td>
                            <td><code><?php echo htmlspecialchars($section['section_code']); ?></code></td>
                            <td><?php echo htmlspecialchars($section['section_name'] ?? $section['section_code']); ?></td>
                            <td><?php echo htmlspecialchars($section['program']); ?></td>
                            <td><?php echo $section['course_code'] ? htmlspecialchars($section['course_code']) : '<span class="text-muted">Not linked</span>'; ?></td>
                            <td><span class="badge bg-info">Year <?php echo $section['year_level']; ?></span></td>
                            <td><?php echo $section['semester'] ?? '1st'; ?></span></td>
                            <td><span class="badge bg-primary"><?php echo $section['subject_count']; ?> subjects</span></td>
                            <td><span class="badge bg-success"><?php echo $section['student_count']; ?> students</span></span></td>
                            <td><span class="badge bg-<?php echo $section['status'] === 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($section['status']); ?></span></td>
                            <td class="table-actions">
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-warning edit-section-btn" 
                                            data-id="<?php echo $section['id']; ?>"
                                            data-code="<?php echo htmlspecialchars($section['section_code'], ENT_QUOTES); ?>"
                                            data-name="<?php echo htmlspecialchars($section['section_name'] ?? '', ENT_QUOTES); ?>"
                                            data-program="<?php echo htmlspecialchars($section['program'], ENT_QUOTES); ?>"
                                            data-course-id="<?php echo $section['course_id']; ?>"
                                            data-year="<?php echo $section['year_level']; ?>"
                                            data-semester="<?php echo $section['semester']; ?>"
                                            data-status="<?php echo $section['status']; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-outline-danger delete-section-btn" 
                                            data-id="<?php echo $section['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($section['section_code'], ENT_QUOTES); ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <button class="btn btn-outline-primary manage-subjects-btn" data-id="<?php echo $section['id']; ?>" data-code="<?php echo htmlspecialchars($section['section_code']); ?>">
                                        <i class="fas fa-book"></i> Subjects
                                    </button>
                                    <button class="btn btn-outline-success assign-students-btn" 
                                            data-id="<?php echo $section['id']; ?>"
                                            data-code="<?php echo htmlspecialchars($section['section_code'], ENT_QUOTES); ?>"
                                            data-name="<?php echo htmlspecialchars($section['section_name'] ?? '', ENT_QUOTES); ?>"
                                            data-program="<?php echo htmlspecialchars($section['program'], ENT_QUOTES); ?>"
                                            data-year="<?php echo $section['year_level']; ?>">
                                        <i class="fas fa-user-plus"></i> Students
                                    </button>
                                    <button class="btn btn-sm btn-outline-info schedule-section-btn" 
                                            data-id="<?php echo $section['id']; ?>"
                                            data-code="<?php echo htmlspecialchars($section['section_code']); ?>"
                                            title="Manage Schedule">
                                        <i class="fas fa-calendar-alt"></i>
                                    </button>
                                    <button class="btn btn-outline-info view-section-details-btn" data-id="<?php echo $section['id']; ?>">
                                        <i class="fas fa-info-circle"></i>
                                    </button>
                                </div>
                            </div>
                        </td>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

<!-- Curriculum Tab -->
<div class="tab-pane fade" id="curriculum" role="tabpanel">
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Course Curriculum Manager</h5>
                <button class="btn btn-light btn-sm" onclick="loadCurriculumForManagement()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label>Select Course</label>
                        <select id="curriculumCourseSelect" class="form-select" onchange="loadCurriculumForManagement()">
                            <option value="">-- Select Course --</option>
                            <?php foreach($courses as $course): ?>
                            <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> 
                            <strong>Note:</strong> Subjects defined here will be available for auto-filling when managing sections.
                        </div>
                    </div>
                </div>
                
                <div id="curriculumManagementView">
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-book-open fa-3x mb-3"></i>
                        <p>Select a course to manage its curriculum</p>
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
                            <h5 class="mb-0"><i class="fas fa-calculator me-2"></i>Payment Calculation</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <h6><i class="fas fa-info-circle me-2"></i>Global Unit Price: ₱<?php echo number_format($global_unit_price, 2); ?></h6>
                                <p class="mb-0">This price applies to all courses and subjects. Only administrators can change this.</p>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label required-field">Select Course to Calculate</label>
                                <select id="calculationCourseSelect" class="form-select">
                                    <option value="">-- Select a course --</option>
                                    <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>" data-units="<?php echo $course['total_units']; ?>">
                                        <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
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
                                
                                <div class="mt-4">
                                    <h6>Student Payment Simulation</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Amount Already Paid</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">₱</span>
                                                    <input type="number" id="paidAmount" class="form-control" step="0.01" min="0" value="0">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">&nbsp;</label>
                                                <button class="btn btn-info w-100" id="calculateRemainingBtn">
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
                                <input type="number" id="quickUnits" class="form-control" min="1" max="500" placeholder="Enter units" value="30">
                            </div>
                            <button class="btn btn-outline-success w-100 mb-3" id="quickCalculateBtn">
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

<!-- =======================================================
    MODALS (All modals remain the same as your original)
======================================================= -->

<!-- Add Course Modal -->
<div class="modal fade" id="addCourseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Course</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="addCourseForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="add_course">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="course_code" class="form-label required-field">Course Code</label>
                        <input type="text" class="form-control" id="course_code" name="course_code" required>
                        <div class="invalid-feedback">Course code is required.</div>
                    </div>
                    <div class="mb-3">
                        <label for="course_name" class="form-label required-field">Course Name</label>
                        <input type="text" class="form-control" id="course_name" name="course_name" required>
                        <div class="invalid-feedback">Course name is required.</div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="duration_years" class="form-label required-field">Duration (Years)</label>
                            <select class="form-select" id="duration_years" name="duration_years" required>
                                <option value="1">1 Year</option>
                                <option value="2" selected>2 Years</option>
                                <option value="3">3 Years</option>
                                <option value="4">4 Years</option>
                                <option value="5">5 Years</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Unit Price: <strong>₱<?php echo number_format($global_unit_price, 2); ?></strong> (global setting)<br>
                        <small>Total units will be automatically calculated from the curriculum subjects.</small>
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
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="edit_course">
                <input type="hidden" name="course_id" id="edit_course_id_field">
                <div class="modal-header">
                    <h5 class="modal-title" id="editCourseModalTitle">Edit Course</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_course_code_field" class="form-label required-field">Course Code</label>
                        <input type="text" name="course_code" id="edit_course_code_field" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_course_name_field" class="form-label required-field">Course Name</label>
                        <input type="text" name="course_name" id="edit_course_name_field" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_duration_years_field" class="form-label required-field">Duration (Years)</label>
                        <select name="duration_years" id="edit_duration_years_field" class="form-select">
                            <option value="1">1 Year</option>
                            <option value="2">2 Years</option>
                            <option value="3">3 Years</option>
                            <option value="4">4 Years</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_course_status_field" class="form-label">Status</label>
                        <select name="status" id="edit_course_status_field" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_course_description_field" class="form-label">Description</label>
                        <textarea name="description" id="edit_course_description_field" class="form-control" rows="3" placeholder="Course description"></textarea>
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

<!-- Edit Subject Modal - Fixed with labels -->
<div class="modal fade" id="editSubjectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="edit_subject">
                <input type="hidden" name="subject_id" id="edit_subject_id_field">
                <div class="modal-header">
                    <h5 class="modal-title" id="editSubjectModalTitle">Edit Subject</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_subject_code_field" class="form-label required-field">Subject Code</label>
                        <input type="text" name="subject_code" id="edit_subject_code_field" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_subject_name_field" class="form-label required-field">Subject Name</label>
                        <input type="text" name="subject_name" id="edit_subject_name_field" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_subject_units_field" class="form-label required-field">Units</label>
                        <input type="number" name="units" id="edit_subject_units_field" class="form-control" min="1" max="10" required>
                        <div class="form-text">Price per unit: ₱<?php echo number_format($global_unit_price, 2); ?></div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_subject_program_field" class="form-label">Program</label>
                        <select name="program" id="edit_subject_program_field" class="form-select">
                            <option value="">-- Select Program --</option>
                            <?php foreach($programs as $p): ?>
                            <option value="<?php echo htmlspecialchars($p); ?>"><?php echo htmlspecialchars($p); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_subject_description_field" class="form-label">Description</label>
                        <textarea name="description" id="edit_subject_description_field" class="form-control" rows="3" placeholder="Optional description"></textarea>
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

<!-- Edit Section Modal -->
<div class="modal fade" id="editSectionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="edit_section">
                <input type="hidden" name="section_id" id="edit_section_id_field">
                <div class="modal-header">
                    <h5 class="modal-title" id="editSectionModalTitle">Edit Section</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_section_code_field" class="form-label required-field">Section Code</label>
                        <input type="text" name="section_code" id="edit_section_code_field" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_section_program_field" class="form-label required-field">Program</label>
                        <select name="program" id="edit_section_program_field" class="form-select" required>
                            <option value="BS Information Technology">BS Information Technology</option>
                            <option value="BS Computer Science">BS Computer Science</option>
                            <option value="BS Business Administration">BS Business Administration</option>
                            <option value="BS Accountancy">BS Accountancy</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_section_year_field" class="form-label required-field">Year Level</label>
                        <select name="year_level" id="edit_section_year_field" class="form-select" required>
                            <?php for($i=1;$i<=4;$i++): ?>
                            <option value="<?php echo $i; ?>">Year <?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_section_semester_field" class="form-label">Semester</label>
                        <select name="section_semester" id="edit_section_semester_field" class="form-select">
                            <option value="1st">1st Semester</option>
                            <option value="2nd">2nd Semester</option>
                            <option value="summer">Summer</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_section_status_field" class="form-label">Status</label>
                        <select name="status" id="edit_section_status_field" class="form-select">
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
            <form method="POST" id="addSubjectForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="add_subject">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="subject_code" class="form-label required-field">Subject Code</label>
                        <input type="text" class="form-control" id="subject_code" name="subject_code" required>
                        <div class="invalid-feedback">Subject code is required.</div>
                    </div>
                    <div class="mb-3">
                        <label for="subject_name" class="form-label required-field">Subject Name</label>
                        <input type="text" class="form-control" id="subject_name" name="subject_name" required>
                        <div class="invalid-feedback">Subject name is required.</div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="units" class="form-label required-field">Units</label>
                            <input type="number" class="form-control" id="units" name="units" min="1" max="10" value="3" required>
                            <div class="invalid-feedback">Units must be between 1 and 10.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="program" class="form-label">Program</label>
                            <select class="form-select" id="program" name="program">
                                <option value="">-- Select Program --</option>
                                <?php foreach($programs as $program): ?>
                                <option value="<?php echo htmlspecialchars($program); ?>"><?php echo htmlspecialchars($program); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="subject_year_level" class="form-label">Year Level</label>
                            <select class="form-select" id="subject_year_level" name="subject_year_level">
                                <option value="">-- N/A --</option>
                                <option value="1">Year 1</option>
                                <option value="2">Year 2</option>
                                <option value="3">Year 3</option>
                                <option value="4">Year 4</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="subject_semester" class="form-label">Semester</label>
                            <select class="form-select" id="subject_semester" name="subject_semester">
                                <option value="">-- N/A --</option>
                                <option value="1st">1st Semester</option>
                                <option value="2nd">2nd Semester</option>
                                <option value="summer">Summer</option>
                            </select>
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
            <form method="POST" id="addSectionForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="add_section">
                
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Tip:</strong> Select a course first to auto-fill program and generate section code.
                    </div>
                    
                    <div class="mb-3">
                        <label for="section_course_id" class="form-label">Select Course (Optional - for autofill)</label>
                        <select class="form-select" id="section_course_id" name="course_id">
                            <option value="">-- Select a course to autofill --</option>
                            <?php foreach($all_courses as $course): ?>
                            <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="section_code" class="form-label required-field">Section Code</label>
                        <input type="text" class="form-control" id="section_code" name="section_code" required>
                        <div class="invalid-feedback">Section code is required.</div>
                        <div class="form-text">Will be auto-generated if you select a course above</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="section_name" class="form-label">Section Name (Optional)</label>
                        <input type="text" class="form-control" id="section_name" name="section_name" placeholder="e.g., BSIT 1-A">
                    </div>
                    
                    <div class="mb-3">
                        <label for="program" class="form-label required-field">Program</label>
                        <select class="form-select" id="program" name="program" required>
                            <option value="">-- Select Program --</option>
                            <?php foreach($programs as $program): ?>
                            <option value="<?php echo htmlspecialchars($program); ?>"><?php echo htmlspecialchars($program); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Program is required.</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="year_level" class="form-label required-field">Year Level</label>
                            <select class="form-select" id="year_level" name="year_level" required>
                                <option value="">-- Select Year Level --</option>
                                <?php for($i = 1; $i <= 6; $i++): ?>
                                    <option value="<?php echo $i; ?>">Year <?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                            <div class="invalid-feedback">Year level is required.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="section_semester" class="form-label">Semester</label>
                            <select class="form-select" id="section_semester" name="section_semester">
                                <option value="1st">1st Semester</option>
                                <option value="2nd">2nd Semester</option>
                                <option value="summer">Summer</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Section</button>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="addSubjectsAfterCreate" value="1">
                            <label class="form-check-label" for="addSubjectsAfterCreate">
                                <i class="fas fa-book me-1"></i> Add subjects to this section after creation
                            </label>
                            <small class="form-text text-muted d-block">Check this to open the subject manager immediately after creating the section</small>
                        </div>
                    </div>
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
            <form method="POST" id="updateUnitPriceForm" novalidate>
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
                        <label for="unit_price" class="form-label required-field">New Unit Price</label>
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" class="form-control" id="unit_price" name="unit_price" step="0.01" min="1" value="<?php echo $global_unit_price; ?>" required>
                        </div>
                        <div class="invalid-feedback">Unit price must be greater than 0.</div>
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

<!-- View Section Details Modal -->
<div class="modal fade" id="viewSectionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">Section Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="sectionModalContent"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Manage Section Subjects Modal -->
<div class="modal fade" id="manageSectionSubjectsModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Manage Section Subjects</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="sectionSubjectsModalBody" style="max-height: 70vh; overflow-y: auto;">
                <div class="text-center">Loading...</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- View Course Details Modal -->
<div class="modal fade" id="viewCourseDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Course Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="courseDetailsContent"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Manage Section Schedule Modal -->
<div class="modal fade" id="manageScheduleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-calendar-alt me-2"></i>Manage Section Schedule</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="schedule_section_id">
                <div class="alert alert-info" id="schedule_section_info"></div>
                
                <!-- Current Schedules -->
                <h6>Current Schedules</h6>
                <div id="scheduleList" class="mb-4">
                    <div class="text-center py-3">Loading schedules...</div>
                </div>
                
                <!-- Add New Schedule -->
                <hr>
                <h6>Add New Schedule</h6>
                <div class="alert alert-warning" id="scheduleWarning" style="display: none;">
                    <i class="fas fa-exclamation-triangle"></i> 
                    <span id="scheduleWarningText"></span>
                </div>
                <div class="row g-2">
                    <div class="col-md-4">
                        <label class="form-label">Subject <span class="text-danger">*</span></label>
                        <select id="schedule_subject_id" class="form-select" required onchange="checkSubjectScheduleStatus()">
                            <option value="">-- Select Subject --</option>
                        </select>
                        <div class="form-text" id="subjectScheduleStatus"></div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Day <span class="text-danger">*</span></label>
                        <select id="schedule_day" class="form-select" required>
                            <option value="">-- Day --</option>
                            <option value="Monday">Monday</option>
                            <option value="Tuesday">Tuesday</option>
                            <option value="Wednesday">Wednesday</option>
                            <option value="Thursday">Thursday</option>
                            <option value="Friday">Friday</option>
                            <option value="Saturday">Saturday</option>
                            <option value="Sunday">Sunday</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Start Time <span class="text-danger">*</span></label>
                        <input type="time" id="schedule_start" class="form-control" step="60">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">End Time <span class="text-danger">*</span></label>
                        <input type="time" id="schedule_end" class="form-control" step="60">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Room</label>
                        <input type="text" id="schedule_room" class="form-control" placeholder="e.g., Room 101">
                    </div>
                    <div class="col-md-12 mt-2">
                        <button class="btn btn-success" onclick="addSchedule()">
                            <i class="fas fa-plus"></i> Add Schedule
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- View Subject Details Modal -->
<div class="modal fade" id="viewSubjectDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Subject Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="subjectDetailsContent"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Assign Students to Section Modal -->
<div class="modal fade" id="assignStudentsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Assign Students to Section</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="assignSectionInfo" class="alert alert-info mb-3"></div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="replaceExistingCheckbox">
                            <label class="form-check-label" for="replaceExistingCheckbox">
                                Replace existing section assignments
                            </label>
                            <small class="form-text text-muted d-block">Warning: This will remove students from their current sections</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="input-group">
                            <input type="text" class="form-control" id="studentSearchInput" placeholder="Search students...">
                            <button class="btn btn-outline-secondary" type="button" id="searchStudentsBtn">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-striped table-hover" id="studentTable">
                        <thead>
                            <tr>
                                <th width="50"><input type="checkbox" id="selectAllStudents"></th>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Program</th>
                                <th>Year Level</th>
                                <th>Current Section</th>
                            </tr>
                        </thead>
                        <tbody id="studentTableBody">
                            <tr><td colspan="6" class="text-center">Loading students...</span></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <div id="warningAlert" class="alert alert-warning me-auto mb-0" style="display: none;">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <span id="warningMessage"></span>
                </div>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmAssignStudentsBtn">
                    <i class="fas fa-user-plus me-1"></i> Assign Selected
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Manage Section Students Modal -->
<div class="modal fade" id="manageSectionStudentsModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Manage Section Students</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="sectionStudentsModalBody">
                <div class="text-center">Loading...</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Warning Confirmation Modal -->
<div class="modal fade" id="warningConfirmationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Warning: Students Already Have Sections</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>The following students are already assigned to sections:</p>
                <ul id="conflictStudentList"></ul>
                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" id="bulkReplaceCheckbox">
                    <label class="form-check-label" for="bulkReplaceCheckbox">
                        Remove all selected students from their current sections and assign to new section
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" id="confirmBulkReplaceBtn">Proceed with Replacement</button>
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
// ====================================================
// ALL JAVASCRIPT FUNCTIONS
// ====================================================

let globalUnitPrice = <?php echo $global_unit_price; ?>;
let coursesData = <?php echo json_encode($courses); ?>;
let allStudentsData = [];
let currentSectionForAssignment = null;
let currentSectionIdForSubjects = null;
let currentSectionCodeForSubjects = null;
let availableSubjectsData = [];

// Global variable for section subjects management
let currentSectionData = null;

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ====================================================
// VIEW FUNCTIONS
// ====================================================

function viewCourseDetails(courseId) {
    const course = coursesData.find(c => c.id == courseId);
    if (!course) return;
    const totalPrice = course.total_units * globalUnitPrice;
    const html = `<div class="card"><div class="card-body">
        <h5>${escapeHtml(course.course_name)}</h5>
        <p><strong>Code:</strong> ${escapeHtml(course.course_code)}</p>
        <p><strong>Duration:</strong> ${course.duration_years} years</p>
        <p><strong>Total Units:</strong> ${course.total_units} units</p>
        <p><strong>Total Price:</strong> ₱${totalPrice.toFixed(2)}</p>
        <p><strong>Status:</strong> ${course.status}</p>
        ${course.description ? `<p><strong>Description:</strong><br>${escapeHtml(course.description)}</p>` : ''}
    </div></div>`;
    document.getElementById('courseDetailsContent').innerHTML = html;
    new bootstrap.Modal(document.getElementById('viewCourseDetailsModal')).show();
}

function viewSubjectDetails(subjectId) {
    fetch(`ajax_handler.php?action=get_subject_details&subject_id=${subjectId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('subjectDetailsContent').innerHTML = data.html;
                new bootstrap.Modal(document.getElementById('viewSubjectDetailsModal')).show();
            } else {
                showMessage('Error: ' + data.message);
            }
        })
        .catch(error => console.error('Error loading subject details:', error));
}

function viewSectionDetails(sectionId) {
    fetch(`ajax_handler.php?action=get_section_info&section_id=${sectionId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('sectionModalContent').innerHTML = data.html;
                new bootstrap.Modal(document.getElementById('viewSectionModal')).show();
            } else {
                showMessage('Error: ' + data.message);
            }
        })
        .catch(error => console.error('Error loading section details:', error));
}
// ====================================================
// SECTION SUBJECT MANAGEMENT 
// ====================================================

function manageSectionSubjects(sectionId, sectionCode) {
    currentSectionData = { id: sectionId, code: sectionCode };
    let modal = new bootstrap.Modal(document.getElementById('manageSectionSubjectsModal'));
    let body = document.getElementById('sectionSubjectsModalBody');
    body.innerHTML = '<div class="text-center">Loading section data...</div>';
    modal.show();

    fetch(`ajax_handler.php?action=get_section_details&section_id=${sectionId}`)
        .then(response => response.json())
        .then(sectionData => {
            if (sectionData.success) {
                currentSectionData = { ...currentSectionData, ...sectionData.section };
                buildSectionSubjectsUI();
            } else {
                body.innerHTML = '<div class="alert alert-danger">Failed to load section details.</div>';
            }
        })
        .catch(err => {
            console.error(err);
            body.innerHTML = '<div class="alert alert-danger">Network error.</div>';
        });
}

// This is the main UI builder – also aliased as renderSectionSubjectsModal
function buildSectionSubjectsUI() {
    let body = document.getElementById('sectionSubjectsModalBody');
    let section = currentSectionData;

    let filterCardHtml = `
        <div class="card mb-4">
            <div class="card-header bg-light">
                <h6 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Subjects</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Course</label>
                        <select id="filterCourse" class="form-select">
                            <option value="">All Courses</option>
                            <?php foreach($all_courses as $c): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['course_code']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Year Level</label>
                        <select id="filterYear" class="form-select">
                            <option value="">All Years</option>
                            <?php for($i=1;$i<=6;$i++): ?>
                            <option value="<?php echo $i; ?>">Year <?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Semester</label>
                        <select id="filterSemester" class="form-select">
                            <option value="">All Semesters</option>
                            <option value="1st">1st Semester</option>
                            <option value="2nd">2nd Semester</option>
                            <option value="summer">Summer</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Program</label>
                        <select id="filterProgram" class="form-select">
                            <option value="">All Programs</option>
                            <?php foreach($programs as $p): ?>
                            <option value="<?php echo htmlspecialchars($p); ?>"><?php echo htmlspecialchars($p); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Source</label>
                        <select id="filterSource" class="form-select">
                            <option value="curriculum" selected>From Curriculum</option>
                            <option value="all">All Subjects</option>
                        </select>
                    </div>
                    <div class="col-md-12 mt-3">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" id="subjectSearch" class="form-control" placeholder="Search by Subject Code or Name...">
                            <button type="button" id="applyFiltersBtn" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Apply Filters
                            </button>
                            <button type="button" id="clearFiltersBtn" class="btn btn-secondary">
                                <i class="fas fa-undo"></i> Clear
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        Assigned Subjects (<span id="assignedCount">0</span>)
                    </div>
                    <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                        <div id="assignedSubjectsList"></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        Available Subjects (<span id="availableCount">0</span>)
                    </div>
                    <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                        <div id="availableSubjectsList"></div>
                    </div>
                </div>
            </div>
        </div>
    `;

    body.innerHTML = filterCardHtml;

    if (section.course_id) document.getElementById('filterCourse').value = section.course_id;
    if (section.year_level) document.getElementById('filterYear').value = section.year_level;
    if (section.semester) document.getElementById('filterSemester').value = section.semester;
    if (section.program) document.getElementById('filterProgram').value = section.program;

    loadAssignedSubjects();
    loadAvailableSubjects();

    document.getElementById('applyFiltersBtn').addEventListener('click', () => loadAvailableSubjects());
    document.getElementById('clearFiltersBtn').addEventListener('click', clearFilters);
    document.getElementById('subjectSearch').addEventListener('keypress', (e) => {
        if (e.key === 'Enter') loadAvailableSubjects();
    });
}

// Alias for backward compatibility
function renderSectionSubjectsModal() {
    buildSectionSubjectsUI();
}

function loadAssignedSubjects() {
    fetch(`ajax_handler.php?action=get_section_assigned_subjects&section_id=${currentSectionData.id}`)
        .then(response => response.json())
        .then(data => {
            let container = document.getElementById('assignedSubjectsList');
            if (data.subjects && data.subjects.length > 0) {
                let html = `<table class="table table-sm table-striped"><thead><tr><th>Code</th><th>Name</th><th>Units</th><th>Action</th></tr></thead><tbody>`;
                data.subjects.forEach(s => {
                    html += `<tr id="assigned-${s.id}">
                                <td><code>${escapeHtml(s.subject_code)}</code></td>
                                <td>${escapeHtml(s.subject_name)}</td>
                                <td>${s.units}</td>
                                <td><button class="btn btn-sm btn-danger" onclick="removeSubjectFromSectionAJAX(${s.id})"><i class="fas fa-trash"></i> Remove</button></td>
                             </tr>`;
                });
                html += `</tbody></table>`;
                container.innerHTML = html;
                document.getElementById('assignedCount').innerText = data.subjects.length;
            } else {
                container.innerHTML = '<p class="text-muted">No subjects assigned.</p>';
                document.getElementById('assignedCount').innerText = '0';
            }
        })
        .catch(error => {
            console.error('Error loading assigned subjects:', error);
            document.getElementById('assignedSubjectsList').innerHTML = '<p class="text-danger">Error loading assigned subjects.</p>';
        });
}

function loadAvailableSubjects() {
    let courseId = document.getElementById('filterCourse').value;
    let yearLevel = document.getElementById('filterYear').value;
    let semester = document.getElementById('filterSemester').value;
    let program = document.getElementById('filterProgram').value;
    let source = document.getElementById('filterSource').value;
    let searchTerm = document.getElementById('subjectSearch').value;

    let params = new URLSearchParams();
    params.append('action', 'get_filtered_subjects_for_section');
    params.append('section_id', currentSectionData.id);
    params.append('source', source);
    if (courseId) params.append('course_id', courseId);
    if (yearLevel) params.append('year_level', yearLevel);
    if (semester) params.append('semester', semester);
    if (program) params.append('program', program);
    if (searchTerm) params.append('search', searchTerm);

    fetch(`ajax_handler.php?${params.toString()}`)
        .then(response => response.json())
        .then(data => {
            let container = document.getElementById('availableSubjectsList');
            if (data.subjects && data.subjects.length > 0) {
                let html = `
                    <div class="mb-2">
                        <label class="form-check">
                            <input type="checkbox" id="selectAllAvailableSubjects" class="form-check-input"> 
                            <strong>Select All</strong>
                        </label>
                    </div>
                    <table class="table table-sm table-striped">
                        <thead>
                            <tr>
                                <th width="40">Select</th>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Year</th>
                                <th>Sem</th>
                                <th>Units</th>
                            </tr>
                        </thead>
                        <tbody>
                `;
                data.subjects.forEach(s => {
                    html += `<tr>
                        <td><input type="checkbox" class="subject-checkbox" value="${s.id}"></td>
                        <td><code>${escapeHtml(s.subject_code)}</code></td>
                        <td>${escapeHtml(s.subject_name)}</td>
                        <td>${s.year_level ? 'Y' + s.year_level : '-'}</td>
                        <td>${s.semester || '-'}</td>
                        <td>${s.units}</td>
                    </tr>`;
                });
                html += `
                        </tbody>
                    </table>
                    <div class="mt-2">
                        <button class="btn btn-success btn-sm" id="bulkAssignSubjectsBtn">
                            <i class="fas fa-plus-circle"></i> Assign Selected Subjects
                        </button>
                    </div>
                `;
                container.innerHTML = html;
                document.getElementById('availableCount').innerText = data.subjects.length;
                
                // Select all functionality
                document.getElementById('selectAllAvailableSubjects')?.addEventListener('change', (e) => {
                    document.querySelectorAll('.subject-checkbox').forEach(cb => cb.checked = e.target.checked);
                });
                
                // Bulk assign button
                document.getElementById('bulkAssignSubjectsBtn')?.addEventListener('click', () => {
                    const selected = Array.from(document.querySelectorAll('.subject-checkbox:checked')).map(cb => cb.value);
                    if (selected.length === 0) {
                        showMessage('Please select at least one subject.');
                        return;
                    }
                    if (confirm(`Assign ${selected.length} subject(s) to this section?`)) {
                        bulkAssignSubjects(selected);
                    }
                });
            } else {
                container.innerHTML = '<p class="text-muted">No subjects match your filters.</p>';
                document.getElementById('availableCount').innerText = '0';
            }
        })
        .catch(error => {
            console.error('Error loading available subjects:', error);
            document.getElementById('availableSubjectsList').innerHTML = '<p class="text-danger">Error loading subjects.</p>';
        });
}

function bulkAssignSubjects(subjectIds) {
    let formData = new FormData();
    formData.append('action', 'bulk_assign_subjects');
    formData.append('section_id', currentSectionData.id);
    subjectIds.forEach(id => formData.append('subject_ids[]', id));
    
    fetch('ajax_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage(`✅ ${data.assigned_count} subject(s) assigned successfully!`);
            loadAssignedSubjects();
            loadAvailableSubjects();
        } else {
            showMessage('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('Error assigning subjects');
    });
}
function bulkAssignSubjects(subjectIds) {
    let formData = new FormData();
    formData.append('action', 'bulk_assign_subjects');
    formData.append('section_id', currentSectionData.id);
    subjectIds.forEach(id => formData.append('subject_ids[]', id));
    
    fetch('ajax_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage(`✅ ${data.assigned_count} subject(s) assigned successfully!`);
            loadAssignedSubjects();
            loadAvailableSubjects();
        } else {
            showMessage('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('Error assigning subjects');
    });
}
function clearFilters() {
    document.getElementById('filterCourse').value = currentSectionData.course_id || '';
    document.getElementById('filterYear').value = currentSectionData.year_level || '';
    document.getElementById('filterSemester').value = currentSectionData.semester || '1st';
    document.getElementById('filterProgram').value = currentSectionData.program || '';
    document.getElementById('filterSource').value = 'curriculum';
    document.getElementById('subjectSearch').value = '';
    loadAvailableSubjects();
}

function addSubjectToSectionAJAX(subjectId) {
    let formData = new FormData();
    formData.append('action', 'add_subject_to_section_ajax');
    formData.append('subject_id', subjectId);
    formData.append('section_id', currentSectionData.id);

    fetch('ajax_handler.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadAssignedSubjects();
                loadAvailableSubjects();
            } else {
                showToast('Error: ' + data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error adding subject', 'danger');
        });
}

function removeSubjectFromSectionAJAX(subjectId) {
    if (!confirm('Remove this subject from section?')) return;
    let formData = new FormData();
    formData.append('action', 'remove_subject_from_section_ajax');
    formData.append('subject_id', subjectId);
    formData.append('section_id', currentSectionData.id);

    fetch('ajax_handler.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadAssignedSubjects();
                loadAvailableSubjects();
                showToast('✅ Subject removed successfully!', 'success');
            } else {
                showToast('Error: ' + data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error removing subject', 'danger');
        });
}
function showToast(message, type = 'success') {
    // Simple alert for now, but you can implement a nice toast
    showMessage(message);
}
// ====================================================
// STUDENT ASSIGNMENT
// ====================================================

function openAssignStudentsModal(sectionId, sectionCode, sectionName, program, yearLevel) {
    currentSectionForAssignment = { id: sectionId, code: sectionCode, name: sectionName, program: program, yearLevel: yearLevel };
    
    let modalHtml = `
        <div class="modal-header bg-primary text-white">
            <h5 class="modal-title">Assign Students to Section: ${escapeHtml(sectionCode)}</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body" style="max-height: 60vh; overflow-y: auto;">
            <div class="alert alert-info mb-3">
                <strong>Section:</strong> ${escapeHtml(sectionCode)} - ${escapeHtml(sectionName || '')}<br>
                <small>Program: ${escapeHtml(program)} | Year Level: Year ${yearLevel}</small>
            </div>
            <div class="row mb-3">
                <div class="col-md-6">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="replaceExistingCheckbox">
                        <label class="form-check-label" for="replaceExistingCheckbox">
                            Allow multiple sections (irregular students)
                        </label>
                    </div>
                </div>
                <div class="col-md-6">
                    <input type="text" id="studentSearchInput" class="form-control" placeholder="Search by ID or Name...">
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr><th width="50"><input type="checkbox" id="selectAllStudents"></th><th>ID</th><th>Name</th><th>Program</th><th>Year</th><th>Current Section</th></tr>
                    </thead>
                    <tbody id="studentTableBody"><tr><td colspan="6" class="text-center">Loading...</td></tr></tbody>
                </table>
            </div>
            <div id="warningAlert" class="alert alert-warning mt-2" style="display:none"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" id="confirmAssignStudentsBtn">Assign Selected Students</button>
        </div>
    `;
    
    let modalDiv = document.createElement('div');
    modalDiv.className = 'modal fade';
    modalDiv.id = 'assignStudentsDynamicModal';
    modalDiv.innerHTML = `<div class="modal-dialog modal-lg">${modalHtml}</div>`;
    document.body.appendChild(modalDiv);
    let modal = new bootstrap.Modal(modalDiv);
    modal.show();
    
    loadStudentsForAssignmentDynamic();
    
    modalDiv.querySelector('#selectAllStudents')?.addEventListener('change', function(e) {
        modalDiv.querySelectorAll('.student-checkbox').forEach(cb => cb.checked = e.target.checked);
    });
    modalDiv.querySelector('#studentSearchInput')?.addEventListener('input', function() {
        filterStudentTableDynamic(modalDiv);
    });
    modalDiv.querySelector('#confirmAssignStudentsBtn')?.addEventListener('click', function() {
        confirmAssignStudentsDynamic(modalDiv, modal);
    });
    modalDiv.addEventListener('hidden.bs.modal', () => modalDiv.remove());
}
function loadStudentsForAssignmentDynamic() {
    let modalDiv = document.querySelector('#assignStudentsDynamicModal');
    if (!modalDiv) return;
    
    fetch(`ajax_handler.php?action=get_students_for_section_assignment&section_id=${currentSectionForAssignment.id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                allStudentsData = data.students || [];
                renderStudentTableDynamic(modalDiv, allStudentsData);
            } else {
                modalDiv.querySelector('#studentTableBody').innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error loading students</span></td></tr>';
            }
        })
        .catch(error => console.error('Error loading students:', error));
}

function renderStudentTableDynamic(modalDiv, students) {
    const tbody = modalDiv.querySelector('#studentTableBody');
    if (!students || students.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center">No students found</span></td></tr>';
        return;
    }
    
    tbody.innerHTML = students.map(student => `
        <tr>
            <td><input type="checkbox" class="student-checkbox" value="${escapeHtml(student.user_id)}" data-current-section="${escapeHtml(student.current_section || '')}"></td>
            <td><code>${escapeHtml(student.user_id)}</code></td>
            <td><strong>${escapeHtml(student.name)}</strong></td>
            <td>${escapeHtml(student.program || '-')}</td>
            <td>${student.year_level ? 'Year ' + student.year_level : '-'}</td>
            <td>${student.current_section ? '<span class="badge bg-warning">' + escapeHtml(student.current_section) + '</span>' : '<span class="badge bg-success">No section</span>'}</td>
        </tr>
    `).join('');
    
    modalDiv.querySelectorAll('.student-checkbox').forEach(cb => {
        cb.onchange = () => checkForWarningsDynamic(modalDiv);
    });
}

function checkForWarningsDynamic(modalDiv) {
    const selected = modalDiv.querySelectorAll('.student-checkbox:checked');
    const withSections = Array.from(selected).filter(cb => cb.dataset.currentSection);
    const warningDiv = modalDiv.querySelector('#warningAlert');
    const warningMsg = modalDiv.querySelector('#warningMessage');
    const replaceCheckbox = modalDiv.querySelector('#replaceExistingCheckbox');
    
    if (withSections.length > 0 && !replaceCheckbox.checked) {
        // Build message with section names
        let studentList = [];
        withSections.forEach(cb => {
            let row = cb.closest('tr');
            let studentName = row.querySelector('td:nth-child(3) strong')?.innerText || 'Unknown';
            let currentSection = cb.dataset.currentSection;
            studentList.push(`${studentName} (currently in ${currentSection})`);
        });
        warningMsg.innerHTML = `⚠️ ${withSections.length} selected student(s) already have sections:<br>${studentList.join('<br>')}<br><br>Check "Allow multiple sections (for irregular students)" to proceed.`;
        warningDiv.style.display = 'block';
    } else {
        warningDiv.style.display = 'none';
    }
}

function filterStudentTableDynamic(modalDiv) {
    const searchTerm = modalDiv.querySelector('#studentSearchInput').value.toLowerCase();
    if (!searchTerm) {
        renderStudentTableDynamic(modalDiv, allStudentsData);
        return;
    }
    const filtered = allStudentsData.filter(student => 
        student.user_id.toLowerCase().includes(searchTerm) || 
        student.name.toLowerCase().includes(searchTerm)
    );
    renderStudentTableDynamic(modalDiv, filtered);
}

function confirmAssignStudentsDynamic(modalDiv, modal) {
    const selected = Array.from(modalDiv.querySelectorAll('.student-checkbox:checked')).map(cb => cb.value);
    const allowMultiple = modalDiv.querySelector('#replaceExistingCheckbox').checked;
    
    if (selected.length === 0) {
        showMessage('Please select at least one student.');
        return;
    }
    
    // If not allowing multiple sections, check for conflicts and confirm
    if (!allowMultiple) {
        const withExistingSections = Array.from(modalDiv.querySelectorAll('.student-checkbox:checked'))
            .filter(cb => cb.dataset.currentSection);
        if (withExistingSections.length > 0) {
            let sectionNames = withExistingSections.map(cb => `${cb.closest('tr').querySelector('td:nth-child(3) strong')?.innerText || 'Unknown'} (${cb.dataset.currentSection})`).join('\n');
            if (!confirm(`The following students already have sections:\n${sectionNames}\n\nAssigning them will REPLACE their current section (they will be removed from their old section).\n\nContinue?`)) {
                return;
            }
        }
    }
    
    const confirmBtn = modalDiv.querySelector('#confirmAssignStudentsBtn');
    confirmBtn.disabled = true;
    confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Assigning...';
    
    const formData = new FormData();
    formData.append('action', 'bulk_assign_students');
    formData.append('section_id', currentSectionForAssignment.id);
    // If allowMultiple is true, we send replace_all=0 (add without removing existing)
    // If false, we send replace_all=1 (replace existing)
    formData.append('replace_all', allowMultiple ? '0' : '1');
    selected.forEach(id => formData.append('student_ids[]', id));
    
    fetch('ajax_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage(`✅ Successfully assigned ${selected.length} student(s) to ${currentSectionForAssignment.code}`);
            modal.hide();
            // Reload to update section counts
            location.reload();
        } else if (data.has_conflicts) {
            // If conflicts and we didn't replace, offer to replace now
            if (confirm(`${data.message}. Do you want to replace their existing sections?`)) {
                // Retry with replace_all = 1
                formData.set('replace_all', '1');
                fetch('ajax_handler.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(retryData => {
                    if (retryData.success) {
                        showMessage(`✅ Successfully assigned ${selected.length} student(s) with replacement.`);
                        modal.hide();
                        location.reload();
                    } else {
                        showMessage('❌ Error: ' + retryData.message);
                    }
                });
            }
        } else {
            showMessage('❌ Error: ' + data.message);
        }
        confirmBtn.disabled = false;
        confirmBtn.innerHTML = '<i class="fas fa-user-plus me-1"></i> Assign Selected Students';
    })
    .catch(error => {
        console.error('Error assigning students:', error);
        showMessage('❌ Error: ' + error.message);
        confirmBtn.disabled = false;
        confirmBtn.innerHTML = '<i class="fas fa-user-plus me-1"></i> Assign Selected Students';
    });
}

function loadStudentsForAssignment() {
    fetch(`ajax_handler.php?action=get_students_for_section_assignment&section_id=${currentSectionForAssignment.id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                allStudentsData = data.students || [];
                renderStudentTable(allStudentsData);
            } else {
                document.getElementById('studentTableBody').innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error loading students</span></td></tr>';
            }
        })
        .catch(error => console.error('Error loading students:', error));
}

let currentSectionSubjects = [];

function manageSectionSchedule(sectionId, sectionCode) {
    document.getElementById('schedule_section_id').value = sectionId;
    document.getElementById('schedule_section_info').innerHTML = `<strong>Section:</strong> ${sectionCode}`;
    
    loadSchedules(sectionId);
    const modal = new bootstrap.Modal(document.getElementById('manageScheduleModal'));
    modal.show();
}

function loadSchedules(sectionId) {
    fetch(`ajax_handler.php?action=get_section_schedules&section_id=${sectionId}`)
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('scheduleList');
            const subjectSelect = document.getElementById('schedule_subject_id');
            
            if (data.success) {
                // Store available subjects for later use
                currentSectionSubjects = data.available_subjects || [];
                
                // Populate subject dropdown (only show subjects without schedule)
                subjectSelect.innerHTML = '<option value="">-- Select Subject --</option>';
                let hasUnscheduledSubjects = false;
                data.available_subjects.forEach(subject => {
                    if (subject.has_schedule == 0) {
                        subjectSelect.innerHTML += `<option value="${subject.id}" data-units="${subject.units}">${subject.subject_code} - ${subject.subject_name} (${subject.units} units)</option>`;
                        hasUnscheduledSubjects = true;
                    }
                });
                
                if (!hasUnscheduledSubjects) {
                    subjectSelect.innerHTML = '<option value="">-- All subjects have schedules --</option>';
                    document.getElementById('scheduleWarning').style.display = 'block';
                    document.getElementById('scheduleWarningText').innerHTML = 'All subjects assigned to this section already have schedules.';
                } else {
                    document.getElementById('scheduleWarning').style.display = 'none';
                }
                
                // Display current schedules
                if (data.schedules && data.schedules.length > 0) {
                    let html = '<table class="table table-sm table-striped"><thead><tr><th>Subject</th><th>Day</th><th>Time</th><th>Room</th><th>Action</th></tr></thead><tbody>';
                    data.schedules.forEach(sched => {
                        html += `<tr id="schedule-row-${sched.id}">
                                    <td><strong>${sched.subject_code}</strong><br><small class="text-muted">${sched.subject_name}</small></td>
                                    <td>${sched.day_of_week}</td>
                                    <td>${sched.start_time.substring(0,5)} - ${sched.end_time.substring(0,5)}</span></td>
                                    <td>${sched.room || 'TBA'}</span></td>
                                    <td><button class="btn btn-sm btn-danger" onclick="deleteSchedule(${sched.id})"><i class="fas fa-trash"></i> Remove</button></td>
                                </tr>`;
                    });
                    html += '</tbody></table>';
                    container.innerHTML = html;
                } else {
                    container.innerHTML = '<div class="alert alert-info">No schedules set for this section.</div>';
                }
            } else {
                container.innerHTML = '<div class="alert alert-danger">Error loading schedules.</div>';
                subjectSelect.innerHTML = '<option value="">-- Error loading subjects --</option>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('scheduleList').innerHTML = '<div class="alert alert-danger">Error loading schedules.</div>';
        });
}

function checkSubjectScheduleStatus() {
    const subjectId = document.getElementById('schedule_subject_id').value;
    const statusDiv = document.getElementById('subjectScheduleStatus');
    
    if (subjectId) {
        const subject = currentSectionSubjects.find(s => s.id == subjectId);
        if (subject && subject.has_schedule == 1) {
            statusDiv.innerHTML = '<span class="text-warning"><i class="fas fa-exclamation-triangle"></i> This subject already has a schedule. Adding a new schedule will replace the existing one.</span>';
        } else {
            statusDiv.innerHTML = '<span class="text-success"><i class="fas fa-check-circle"></i> This subject does not have a schedule yet.</span>';
        }
    } else {
        statusDiv.innerHTML = '';
    }
}

function addSchedule() {
    const sectionId = document.getElementById('schedule_section_id').value;
    const subjectId = document.getElementById('schedule_subject_id').value;
    const day = document.getElementById('schedule_day').value;
    const startTime = document.getElementById('schedule_start').value;
    const endTime = document.getElementById('schedule_end').value;
    const room = document.getElementById('schedule_room').value;
    
    if (!subjectId) {
        alert('Please select a subject.');
        return;
    }
    if (!day) {
        alert('Please select a day.');
        return;
    }
    if (!startTime) {
        alert('Please enter start time.');
        return;
    }
    if (!endTime) {
        alert('Please enter end time.');
        return;
    }
    if (startTime >= endTime) {
        alert('End time must be after start time.');
        return;
    }
    
    // Check if subject already has schedule (update instead of insert)
    const subject = currentSectionSubjects.find(s => s.id == subjectId);
    let isUpdate = subject && subject.has_schedule == 1;
    
    let confirmMsg = isUpdate 
        ? `This subject already has a schedule. Do you want to REPLACE it with the new schedule?`
        : `Add schedule for this subject?`;
    
    if (!confirm(confirmMsg)) return;
    
    const formData = new FormData();
    formData.append('action', 'add_schedule');
    formData.append('section_id', sectionId);
    formData.append('subject_id', subjectId);
    formData.append('day_of_week', day);
    formData.append('start_time', startTime);
    formData.append('end_time', endTime);
    formData.append('room', room);
    formData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');
    
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
    
    fetch('ajax_handler.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(isUpdate ? 'Schedule updated successfully!' : 'Schedule added successfully!');
                loadSchedules(sectionId);
                document.getElementById('schedule_subject_id').value = '';
                document.getElementById('schedule_day').value = '';
                document.getElementById('schedule_start').value = '';
                document.getElementById('schedule_end').value = '';
                document.getElementById('schedule_room').value = '';
                document.getElementById('subjectScheduleStatus').innerHTML = '';
            } else {
                alert('Error: ' + data.message);
            }
            btn.disabled = false;
            btn.innerHTML = originalText;
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Network error. Please try again.');
            btn.disabled = false;
            btn.innerHTML = originalText;
        });
}

function deleteSchedule(scheduleId) {
    if (!confirm('Remove this schedule? This action cannot be undone.')) return;
    
    const formData = new FormData();
    formData.append('action', 'delete_schedule');
    formData.append('schedule_id', scheduleId);
    formData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');
    
    fetch('ajax_handler.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const row = document.getElementById(`schedule-row-${scheduleId}`);
                if (row) row.remove();
                alert('Schedule removed successfully.');
                // Reload to refresh subject dropdown
                const sectionId = document.getElementById('schedule_section_id').value;
                loadSchedules(sectionId);
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Network error. Please try again.');
        });
}

document.addEventListener('click', function(e) {
    if (e.target.closest('.schedule-section-btn')) {
        const btn = e.target.closest('.schedule-section-btn');
        manageSectionSchedule(btn.dataset.id, btn.dataset.code);
    }
});

function renderStudentTable(students) {
    const tbody = document.getElementById('studentTableBody');
    if (!students || students.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center">No students found</span></td></tr>';
        return;
    }
    
    tbody.innerHTML = students.map(student => `
        <tr>
            <td><input type="checkbox" class="student-checkbox" value="${escapeHtml(student.user_id)}" data-current-section="${escapeHtml(student.current_section || '')}"></span></td>
            <td><code>${escapeHtml(student.user_id)}</code></span></td>
            <td><strong>${escapeHtml(student.name)}</strong></span></td>
            <td>${escapeHtml(student.program || '-')}</span></span></td>
            <td>${student.year_level ? 'Year ' + student.year_level : '-'}</span></span></span></td>
            <td>${student.current_section ? '<span class="badge bg-warning">' + escapeHtml(student.current_section) + '</span>' : '<span class="badge bg-success">No section</span>'}</span></span></span></td>
        </tr>
    `).join('');
    
    document.getElementById('selectAllStudents').onchange = function() {
        document.querySelectorAll('.student-checkbox').forEach(cb => cb.checked = this.checked);
        checkForWarnings();
    };
    
    document.querySelectorAll('.student-checkbox').forEach(cb => {
        cb.onchange = () => checkForWarnings();
    });
}

function checkForWarnings() {
    const selected = document.querySelectorAll('.student-checkbox:checked');
    const withSections = Array.from(selected).filter(cb => cb.dataset.currentSection);
    const warningDiv = document.getElementById('warningAlert');
    const warningMsg = document.getElementById('warningMessage');
    const replaceCheckbox = document.getElementById('replaceExistingCheckbox');
    
    if (withSections.length > 0 && !replaceCheckbox.checked) {
        warningMsg.innerHTML = `${withSections.length} selected student(s) already have sections. Check "Replace existing section assignments" to proceed.`;
        warningDiv.style.display = 'block';
    } else {
        warningDiv.style.display = 'none';
    }
}

function filterStudentTable() {
    const searchTerm = document.getElementById('studentSearchInput').value.toLowerCase();
    if (!searchTerm) {
        renderStudentTable(allStudentsData);
        return;
    }
    const filtered = allStudentsData.filter(student => 
        student.user_id.toLowerCase().includes(searchTerm) || 
        student.name.toLowerCase().includes(searchTerm)
    );
    renderStudentTable(filtered);
}

function confirmAssignStudents() {
    const selected = Array.from(document.querySelectorAll('.student-checkbox:checked')).map(cb => cb.value);
    const replaceExisting = document.getElementById('replaceExistingCheckbox').checked;
    
    if (selected.length === 0) {
        showMessage('Please select at least one student.');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'bulk_assign_students');
    formData.append('section_id', currentSectionForAssignment.id);
    formData.append('replace_all', replaceExisting ? '1' : '0');
    selected.forEach(id => formData.append('student_ids[]', id));
    
    fetch('ajax_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage(`✅ Successfully assigned ${selected.length} student(s) to ${currentSectionForAssignment.code}`);
            bootstrap.Modal.getInstance(document.getElementById('assignStudentsModal')).hide();
            location.reload();
        } else if (data.has_conflicts) {
            showConflictWarning(data.conflict_students);
        } else {
            showMessage('❌ Error: ' + data.message);
        }
    })
    .catch(error => console.error('Error assigning students:', error));
}

function showConflictWarning(conflictStudents) {
    const conflictList = document.getElementById('conflictStudentList');
    conflictList.innerHTML = conflictStudents.map(id => `<li>${escapeHtml(id)}</li>`).join('');
    const warningModal = new bootstrap.Modal(document.getElementById('warningConfirmationModal'));
    warningModal.show();
}

function proceedWithBulkReplace() {
    const selected = Array.from(document.querySelectorAll('.student-checkbox:checked')).map(cb => cb.value);
    
    const formData = new FormData();
    formData.append('action', 'bulk_assign_students');
    formData.append('section_id', currentSectionForAssignment.id);
    formData.append('replace_all', '1');
    selected.forEach(id => formData.append('student_ids[]', id));
    
    fetch('ajax_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage(`✅ Successfully assigned ${selected.length} student(s) to ${currentSectionForAssignment.code}`);
            bootstrap.Modal.getInstance(document.getElementById('warningConfirmationModal')).hide();
            bootstrap.Modal.getInstance(document.getElementById('assignStudentsModal')).hide();
            location.reload();
        } else {
            showMessage('❌ Error: ' + data.message);
        }
    });
}

// ====================================================
// CURRICULUM MANAGEMENT FUNCTIONS
// ====================================================
function loadCurriculumForManagement() {
    let courseId = document.getElementById('curriculumCourseSelect').value;
    if (!courseId) {
        document.getElementById('curriculumManagementView').innerHTML = '<div class="text-center text-muted py-5"><i class="fas fa-book-open fa-3x mb-3"></i><p>Select a course to manage its curriculum</p></div>';
        return;
    }
    
    document.getElementById('curriculumManagementView').innerHTML = '<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x"></i><p>Loading curriculum...</p></div>';
    
    fetch(`ajax_handler.php?action=load_curriculum&course_id=${courseId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.curriculum_data) {
                let html = '';
                let years = {};
                
                // Group by year and semester
                data.curriculum_data.forEach(item => {
                    if (!years[item.year_level]) years[item.year_level] = {};
                    if (!years[item.year_level][item.semester]) years[item.year_level][item.semester] = [];
                    years[item.year_level][item.semester].push(item);
                });
                
                // Display by year
                for (let year = 1; year <= 4; year++) {
                    if (years[year] && Object.keys(years[year]).length > 0) {
                        html += `<div class="card mb-3"><div class="card-header bg-primary text-white">Year ${year}</div><div class="card-body">`;
                        
                        for (let sem of ['1st', '2nd', 'summer']) {
                            if (years[year][sem] && years[year][sem].length > 0) {
                                html += `<h6 class="mt-2">${sem} Semester</h6>
                                        <div class="table-responsive mb-3">
                                            <table class="table table-sm table-striped">
                                                <thead class="table-light">
                                                    <tr><th>Code</th><th>Subject Name</th><th>Units</th><th style="width:80px">Action</th></tr>
                                                </thead>
                                                <tbody>`;
                                
                                years[year][sem].forEach(subject => {
                                    html += `<tr id="curriculum-row-${subject.curriculum_id}">
                                                <td><code>${escapeHtml(subject.subject_code)}</code></td>
                                                <td>${escapeHtml(subject.subject_name)}</strong></td>
                                                <td><span class="badge bg-primary">${subject.units} units</span></td>
                                                <td>
                                                    <button class="btn btn-sm btn-danger remove-curriculum-item" 
                                                        data-id="${subject.curriculum_id}" 
                                                        data-course="${courseId}"
                                                        data-name="${escapeHtml(subject.subject_code)}">
                                                        <i class="fas fa-trash"></i> Remove
                                                    </button>
                                                </td>
                                            </tr>`;
                                });
                                html += `</tbody></table></div>`;
                            }
                        }
                        html += `</div></div>`;
                    }
                }
                
                if (html === '') {
                    html = '<div class="alert alert-info">No subjects in curriculum. Click "Add Subject" to add subjects.</div>';
                }
                
                html += `<div class="text-center mt-3">
                            <button class="btn btn-success" id="openAddCurriculumModalBtn" data-course="${courseId}">
                                <i class="fas fa-plus"></i> Add Subject to Curriculum
                            </button>
                        </div>`;
                
                document.getElementById('curriculumManagementView').innerHTML = html;
                
                // Attach remove handlers
                document.querySelectorAll('.remove-curriculum-item').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const curriculumId = this.dataset.id;
                        const courseId = this.dataset.course;
                        const subjectName = this.dataset.name;
                        if (confirm(`Remove "${subjectName}" from curriculum?`)) {
                            removeCurriculumItem(curriculumId, courseId);
                        }
                    });
                });
                
                // Attach add button handler
                document.getElementById('openAddCurriculumModalBtn').addEventListener('click', function() {
                    showAddSubjectToCurriculumModal(courseId);
                });
                
            } else {
                document.getElementById('curriculumManagementView').innerHTML = `<div class="alert alert-danger">Error loading curriculum: ${data.message || 'Unknown error'}</div>`;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('curriculumManagementView').innerHTML = '<div class="alert alert-danger">Error loading curriculum. Please refresh the page.</div>';
        });
}

function manageSectionStudents(sectionId, sectionCode, program, yearLevel) {
    let modal = new bootstrap.Modal(document.getElementById('manageSectionStudentsModal'));
    let body = document.getElementById('sectionStudentsModalBody');
    body.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading students...</div>';
    modal.show();
    
    fetch(`ajax_handler.php?action=get_students_for_section_management&section_id=${sectionId}&program=${encodeURIComponent(program)}&year_level=${yearLevel}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let html = `
                    <div class="alert alert-info">
                        <strong>Section:</strong> ${escapeHtml(sectionCode)} | 
                        <strong>Program:</strong> ${escapeHtml(program)} |
                        <strong>Year Level:</strong> ${yearLevel}
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-success text-white">
                                    Assigned Students (${data.assigned?.length || 0})
                                </div>
                                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                                    <div id="assignedStudentsList">
                `;
                
                if (data.assigned && data.assigned.length > 0) {
                    html += `<table class="table table-sm table-striped">
                                <thead><tr><th>ID</th><th>Name</th><th>Program</th><th>Action</th></tr></thead>
                                <tbody>`;
                    data.assigned.forEach(s => {
                        html += `
                            <tr id="assigned-student-${escapeHtml(s.user_id)}">
                                <td><code>${escapeHtml(s.user_id)}</code></td>
                                <td>${escapeHtml(s.name)}</strong></td>
                                <td>${escapeHtml(s.program || '-')}</td>
                                <td>
                                    <button class="btn btn-sm btn-danger" onclick="removeStudentFromSectionStay('${s.user_id}', ${sectionId})">
                                        <i class="fas fa-user-minus"></i> Remove
                                    </button>
                                </td>
                            </tr>
                        `;
                    });
                    html += `</tbody></table>`;
                } else {
                    html += `<p class="text-muted">No students assigned.</p>`;
                }
                
                html += `
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    Available Students (${data.available?.length || 0})
                                    <div class="float-end">
                                        <label class="text-white me-2">Show only students without sections:</label>
                                        <input type="checkbox" id="showOnlyUnsigned" onchange="filterAvailableStudents(${sectionId})">
                                    </div>
                                </div>
                                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                                    <div class="mb-2">
                                        <input type="text" id="searchAvailableStudents" class="form-control" placeholder="Search students...">
                                    </div>
                                    <div id="availableStudentsList">
                `;
                
                if (data.available && data.available.length > 0) {
                    html += `<table class="table table-sm table-striped" id="availableStudentsTable">
                                <thead><tr>
                                    <th width="40"><input type="checkbox" id="selectAllAvailable"></th>
                                    <th>ID</th><th>Name</th><th>Program</th><th>Year</th><th>Current Section</th>
                                </tr></thead>
                                <tbody>`;
                    data.available.forEach(s => {
                        let hasSection = s.current_section ? 'yes' : 'no';
                        html += `
                            <tr class="student-row" data-has-section="${hasSection}">
                                <td><input type="checkbox" class="student-checkbox" value="${escapeHtml(s.user_id)}"></td>
                                <td><code>${escapeHtml(s.user_id)}</code></td>
                                <td>${escapeHtml(s.name)}</strong></td>
                                <td>${escapeHtml(s.program || '-')}</td>
                                <td>${s.year_level ? 'Year ' + s.year_level : '-'}</td>
                                <td>${s.current_section ? '<span class="badge bg-warning">' + escapeHtml(s.current_section) + '</span>' : '<span class="badge bg-success">No section</span>'}</td>
                            </tr>
                        `;
                    });
                    html += `</tbody></table>
                            <div class="mt-3">
                                <button class="btn btn-primary w-100" onclick="bulkAssignStudentsStay(${sectionId})">
                                    <i class="fas fa-user-plus"></i> Assign Selected Students
                                </button>
                            </div>`;
                } else {
                    html += `<p class="text-muted">No available students.</p>`;
                }
                
                html += `
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                body.innerHTML = html;
                
                document.getElementById('selectAllAvailable')?.addEventListener('change', function() {
                    document.querySelectorAll('.student-checkbox').forEach(cb => cb.checked = this.checked);
                });
                
                document.getElementById('searchAvailableStudents')?.addEventListener('input', function() {
                    let term = this.value.toLowerCase();
                    document.querySelectorAll('#availableStudentsTable .student-row').forEach(row => {
                        row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
                    });
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            body.innerHTML = '<div class="alert alert-danger">Error loading students.</div>';
        });
}

function filterAvailableStudents(sectionId) {
    let showOnlyUnsigned = document.getElementById('showOnlyUnsigned').checked;
    let rows = document.querySelectorAll('#availableStudentsTable .student-row');
    rows.forEach(row => {
        let hasSection = row.dataset.hasSection === 'yes';
        if (showOnlyUnsigned && hasSection) {
            row.style.display = 'none';
        } else {
            row.style.display = '';
        }
    });
}

function bulkAssignStudentsStay(sectionId) {
    let selected = Array.from(document.querySelectorAll('.student-checkbox:checked')).map(cb => cb.value);
    if (selected.length === 0) {
        alert('Please select at least one student.');
        return;
    }
    
    let formData = new FormData();
    formData.append('action', 'bulk_assign_students');
    formData.append('section_id', sectionId);
    formData.append('replace_all', '0');
    selected.forEach(id => formData.append('student_ids[]', id));
    
    fetch('ajax_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(`✅ ${selected.length} student(s) assigned!`);
            let sectionCode = document.querySelector('#manageSectionStudentsModal .alert-info strong')?.textContent || '';
            manageSectionStudents(sectionId, sectionCode, '', '');
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function removeStudentFromSectionStay(studentId, sectionId) {
    if (!confirm('Remove this student from section?')) return;
    
    let formData = new FormData();
    formData.append('action', 'remove_student_from_section');
    formData.append('student_id', studentId);
    formData.append('section_id', sectionId);
    
    fetch('ajax_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            let row = document.getElementById(`assigned-student-${studentId}`);
            if (row) row.remove();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function removeCurriculumItem(curriculumId, courseId) {
    fetch('ajax_handler.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=remove_curriculum&curriculum_id=${curriculumId}&course_id=${courseId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const row = document.getElementById(`curriculum-row-${curriculumId}`);
            if (row) row.remove();
            alert('✅ Subject removed from curriculum');
            loadCurriculumForManagement(); // Refresh the view
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error removing subject');
    });
}
function showAddSubjectToCurriculumModal(courseId) {
    fetch(`ajax_handler.php?action=get_available_subjects_for_curriculum&course_id=${courseId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.subjects && data.subjects.length > 0) {
                let modalHtml = `
                    <div class="modal fade" id="addCurriculumModal" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header bg-primary text-white">
                                    <h5 class="modal-title">Add Subject to Curriculum</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label">Subject <span class="text-danger">*</span></label>
                                        <select id="modalCurriculumSubjectSelect" class="form-select" required>
                                            <option value="">-- Select Subject --</option>
                                            ${data.subjects.map(s => `<option value="${s.id}">${escapeHtml(s.subject_code)} - ${escapeHtml(s.subject_name)} (${s.units} units)</option>`).join('')}
                                        </select>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Year Level <span class="text-danger">*</span></label>
                                            <select id="modalCurriculumYearSelect" class="form-select" required>
                                                <option value="1" selected>Year 1</option>
                                                <option value="2">Year 2</option>
                                                <option value="3">Year 3</option>
                                                <option value="4">Year 4</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Semester <span class="text-danger">*</span></label>
                                            <select id="modalCurriculumSemesterSelect" class="form-select" required>
                                                <option value="1st" selected>1st Semester</option>
                                                <option value="2nd">2nd Semester</option>
                                                <option value="summer">Summer</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div id="modalFormError" class="alert alert-danger mt-2" style="display: none;"></div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="button" class="btn btn-primary" id="modalSaveCurriculumSubjectBtn">Add to Curriculum</button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                const existingModal = document.getElementById('addCurriculumModal');
                if (existingModal) existingModal.remove();
                document.body.insertAdjacentHTML('beforeend', modalHtml);
                const modalElement = document.getElementById('addCurriculumModal');
                const modal = new bootstrap.Modal(modalElement);
                modal.show();
                
                // Get elements with modal-specific IDs
                const subjectSelect = document.getElementById('modalCurriculumSubjectSelect');
                const yearSelect = document.getElementById('modalCurriculumYearSelect');
                const semesterSelect = document.getElementById('modalCurriculumSemesterSelect');
                const errorDiv = document.getElementById('modalFormError');
                
                // Clear validation on change
                subjectSelect.addEventListener('change', () => subjectSelect.classList.remove('is-invalid'));
                yearSelect.addEventListener('change', () => yearSelect.classList.remove('is-invalid'));
                semesterSelect.addEventListener('change', () => semesterSelect.classList.remove('is-invalid'));
                
                document.getElementById('modalSaveCurriculumSubjectBtn').onclick = function() {
                    // Reset validation
                    subjectSelect.classList.remove('is-invalid');
                    yearSelect.classList.remove('is-invalid');
                    semesterSelect.classList.remove('is-invalid');
                    errorDiv.style.display = 'none';
                    
                    const subjectId = subjectSelect.value;
                    let yearLevel = yearSelect.value;
                    const semester = semesterSelect.value;
                    
                    console.log('Selected values - Subject:', subjectId, 'Year:', yearLevel, 'Semester:', semester);
                    
                    if (!subjectId) {
                        errorDiv.innerHTML = 'Please select a subject from the list';
                        errorDiv.style.display = 'block';
                        return;
                    }
                    if (!yearLevel) {
                        errorDiv.innerHTML = '⚠️ Please select a YEAR LEVEL from the dropdown (Year 1, 2, 3, or 4)';
                        errorDiv.style.display = 'block';
                        yearSelect.focus();
                        return;
                    }
                    if (!semester) {
                        errorDiv.innerHTML = '⚠️ Please select a SEMESTER from the dropdown (1st, 2nd, or Summer)';
                        errorDiv.style.display = 'block';
                        semesterSelect.focus();
                        return;
                    }
                    
                    yearLevel = parseInt(yearLevel, 10);
                    
                    console.log('Sending:', { subjectId, yearLevel, semester, courseId });
                    
                    const btn = this;
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
                    
                    const formData = new FormData();
                    formData.append('action', 'add_curriculum_ajax');
                    formData.append('course_id', courseId);
                    formData.append('subject_id', subjectId);
                    formData.append('year_level', yearLevel);
                    formData.append('semester', semester);
                    
                    fetch('ajax_handler.php', { method: 'POST', body: formData })
                        .then(response => response.json())
                        .then(result => {
                            if (result.success) {
                                errorDiv.className = 'alert alert-success mt-2';
                                errorDiv.innerHTML = result.message || 'Subject added successfully!';
                                errorDiv.style.display = 'block';
                                setTimeout(() => {
                                    modal.hide();
                                    modalElement.remove();
                                    loadCurriculumForManagement();
                                }, 1200);
                            } else {
                                errorDiv.className = 'alert alert-danger mt-2';
                                errorDiv.innerHTML = result.message || 'Failed to add subject.';
                                errorDiv.style.display = 'block';
                                btn.disabled = false;
                                btn.innerHTML = 'Add to Curriculum';
                            }
                        })
                        .catch(err => {
                            errorDiv.className = 'alert alert-danger mt-2';
                            errorDiv.innerHTML = 'Network error: ' + err.message;
                            errorDiv.style.display = 'block';
                            btn.disabled = false;
                            btn.innerHTML = 'Add to Curriculum';
                        });
                };
                
                modalElement.addEventListener('hidden.bs.modal', () => modalElement.remove());
            } else {
                alert('No available subjects to add.');
            }
        })
        .catch(err => alert('Error loading subjects: ' + err.message));
}

function renderSectionSubjectsModal() {
    buildSectionSubjectsUI();
}

function filterAvailableStudents(sectionId) {
    let showOnlyUnsigned = document.getElementById('showOnlyUnsigned').checked;
    let rows = document.querySelectorAll('#availableStudentsTable .student-row');
    rows.forEach(row => {
        let hasSection = row.dataset.hasSection === 'yes';
        if (showOnlyUnsigned && hasSection) {
            row.style.display = 'none';
        } else {
            row.style.display = '';
        }
    });
}

function bulkAssignStudentsStay(sectionId) {
    let selected = Array.from(document.querySelectorAll('.student-checkbox:checked')).map(cb => cb.value);
    if (selected.length === 0) {
        showMessage('Please select at least one student.');
        return;
    }
    
    let formData = new FormData();
    formData.append('action', 'bulk_assign_students');
    formData.append('section_id', sectionId);
    formData.append('replace_all', '0');
    selected.forEach(id => formData.append('student_ids[]', id));
    
    fetch('ajax_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage(`✅ ${selected.length} student(s) assigned!`);
            let sectionCode = document.querySelector('#manageSectionStudentsModal .alert-info strong')?.textContent || '';
            manageSectionStudents(sectionId, sectionCode, '', '');
        } else {
            showMessage('Error: ' + data.message);
        }
    });
}

function removeStudentFromSectionStay(studentId, sectionId) {
    if (!confirm('Remove this student from section?')) return;
    
    let formData = new FormData();
    formData.append('action', 'remove_student_from_section');
    formData.append('student_id', studentId);
    formData.append('section_id', sectionId);
    
    fetch('ajax_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            let row = document.getElementById(`assigned-student-${studentId}`);
            if (row) row.remove();
        } else {
            showMessage('Error: ' + data.message);
        }
    });
}

// ====================================================
// PAYMENT CALCULATION FUNCTIONS
// ====================================================

function calculatePaymentBreakdown() {
    const courseId = document.getElementById('calculationCourseSelect').value;
    if (courseId) {
        const course = coursesData.find(c => c.id == courseId);
        if (course) {
            const totalPrice = course.total_units * globalUnitPrice;
            document.getElementById('calcTotalUnits').textContent = course.total_units + ' units';
            document.getElementById('calcTotalPrice').textContent = totalPrice.toFixed(2);
            document.getElementById('calcPerYear').textContent = (totalPrice / course.duration_years).toFixed(2);
            document.getElementById('calcPerSemester').textContent = (totalPrice / course.duration_years / 2).toFixed(2);
            document.getElementById('calculationResults').style.display = 'block';
            document.getElementById('noCourseSelected').style.display = 'none';
        }
    } else {
        document.getElementById('calculationResults').style.display = 'none';
        document.getElementById('noCourseSelected').style.display = 'block';
    }
}

function calculateRemainingPayment() {
    const courseId = document.getElementById('calculationCourseSelect').value;
    const paidAmount = parseFloat(document.getElementById('paidAmount').value) || 0;
    
    if (courseId) {
        const course = coursesData.find(c => c.id == courseId);
        if (course) {
            const totalPrice = course.total_units * globalUnitPrice;
            const remaining = totalPrice - paidAmount;
            
            document.getElementById('remainingTotal').textContent = totalPrice.toFixed(2);
            document.getElementById('remainingPaid').textContent = paidAmount.toFixed(2);
            document.getElementById('remainingBalance').textContent = remaining.toFixed(2);
            document.getElementById('remainingCalculation').style.display = 'block';
        }
    }
}

function quickCalculate() {
    const units = parseInt(document.getElementById('quickUnits').value) || 0;
    if (units > 0) {
        document.getElementById('quickTotal').textContent = (units * globalUnitPrice).toFixed(2);
        document.getElementById('quickResults').style.display = 'block';
    }
}

// ====================================================
// SECTION AUTOFILL
// ====================================================

function onCourseSelectForSection(courseId, isEdit = false) {
    if (!courseId) return;
    
    fetch(`ajax_handler.php?action=get_course_details&course_id=${courseId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.course) {
                const programMap = {
                    'BSIT': 'BS Information Technology',
                    'BSCS': 'BS Computer Science',
                    'BSBA': 'BS Business Administration',
                    'BSA': 'BS Accountancy',
                    'BSCrim': 'BS Criminology',
                    'BSPsych': 'BS Psychology',
                    'ACT': 'Associate in Computer Technology'
                };
                const program = programMap[data.course.course_code] || '';
                
                if (!isEdit) {
                    const yearLevel = document.getElementById('year_level')?.value || 1;
                    const sectionLetter = String.fromCharCode(64 + (Math.floor(Math.random() * 3) + 1));
                    const suggestedCode = `${data.course.course_code}${yearLevel}${sectionLetter}`;
                    if (document.getElementById('section_code') && !document.getElementById('section_code').value) {
                        document.getElementById('section_code').value = suggestedCode;
                    }
                    if (document.getElementById('section_name') && !document.getElementById('section_name').value) {
                        document.getElementById('section_name').value = `${data.course.course_name} - Year ${yearLevel} Section ${sectionLetter}`;
                    }
                }
                if (document.getElementById('program') && program && !document.getElementById('program').value) {
                    document.getElementById('program').value = program;
                }
                if (document.getElementById('edit_program') && program && !document.getElementById('edit_program').value) {
                    document.getElementById('edit_program').value = program;
                }
            }
        })
        .catch(error => console.error('Error loading course details:', error));
}

// ====================================================
// AUTO-UPPERCASE FOR CODES
// ====================================================

function setupAutoUppercase() {
    document.querySelectorAll('input[name="course_code"], input[name="subject_code"], input[name="section_code"]').forEach(input => {
        input.addEventListener('input', function() { this.value = this.value.toUpperCase(); });
    });
}

// ====================================================
// FORM VALIDATION & PRICE PREVIEW
// ====================================================

function setupPricePreviews() {
    const unitsInput = document.getElementById('units');
    if (unitsInput) {
        unitsInput.addEventListener('input', function() {
            const units = parseInt(this.value) || 0;
            const price = units * globalUnitPrice;
            document.getElementById('subjectPrice').textContent = price.toFixed(2);
        });
    }
    
    const editUnitsInput = document.getElementById('edit_subject_units_field');
    if (editUnitsInput) {
        editUnitsInput.addEventListener('input', function() {
            const units = parseInt(this.value) || 0;
            const price = units * globalUnitPrice;
            const priceSpan = document.getElementById('editSubjectPrice');
            if (priceSpan) priceSpan.textContent = price.toFixed(2);
        });
    }
}

function showMessage(message, type = 'success') {
    // Create a temporary alert replacement with Bootstrap alert
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3" role="alert" style="z-index: 9999; min-width: 300px;">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    const container = document.createElement('div');
    container.innerHTML = alertHtml;
    document.body.appendChild(container.firstChild);
    setTimeout(() => {
        const alert = document.querySelector('.alert.position-fixed');
        if (alert) alert.remove();
    }, 3000);
}
// ====================================================
// EVENT DELEGATION
// ====================================================
document.addEventListener('click', function(e) {
    if (e.target.closest('.edit-course-btn')) {
        const btn = e.target.closest('.edit-course-btn');
        document.getElementById('edit_course_id_field').value = btn.dataset.id;
        document.getElementById('edit_course_code_field').value = btn.dataset.code;
        document.getElementById('edit_course_name_field').value = btn.dataset.name;
        document.getElementById('edit_course_description_field').value = btn.dataset.desc;
        document.getElementById('edit_duration_years_field').value = btn.dataset.years;
        document.getElementById('edit_course_status_field').value = btn.dataset.status;
        new bootstrap.Modal(document.getElementById('editCourseModal')).show();
    }
    
    else if (e.target.closest('.delete-course-btn')) {
        const btn = e.target.closest('.delete-course-btn');
        document.getElementById('delete_course_name').textContent = btn.dataset.name;
        document.getElementById('confirmDeleteCourse').dataset.id = btn.dataset.id;
        new bootstrap.Modal(document.getElementById('deleteCourseModal')).show();
    }
    
    else if (e.target.closest('.view-course-details-btn')) {
        const btn = e.target.closest('.view-course-details-btn');
        viewCourseDetails(btn.dataset.id);
    }
    
    else if (e.target.closest('.edit-subject-btn')) {
        const btn = e.target.closest('.edit-subject-btn');
        document.getElementById('edit_subject_id_field').value = btn.dataset.id;
        document.getElementById('edit_subject_code_field').value = btn.dataset.code;
        document.getElementById('edit_subject_name_field').value = btn.dataset.name;
        document.getElementById('edit_subject_units_field').value = btn.dataset.units;
        document.getElementById('edit_subject_program_field').value = btn.dataset.program;
        document.getElementById('edit_subject_description_field').value = btn.dataset.desc;
        document.getElementById('editSubjectModalTitle').innerHTML = `Edit Subject: ${btn.dataset.code}`;
        new bootstrap.Modal(document.getElementById('editSubjectModal')).show();
    }
    
    else if (e.target.closest('.delete-subject-btn')) {
        const btn = e.target.closest('.delete-subject-btn');
        document.getElementById('delete_subject_name').textContent = btn.dataset.name;
        document.getElementById('confirmDeleteSubject').dataset.id = btn.dataset.id;
        new bootstrap.Modal(document.getElementById('deleteSubjectModal')).show();
    }
    
    else if (e.target.closest('.view-subject-details-btn')) {
        const btn = e.target.closest('.view-subject-details-btn');
        viewSubjectDetails(btn.dataset.id);
    }
    
else if (e.target.closest('.edit-section-btn')) {
    const btn = e.target.closest('.edit-section-btn');
    document.getElementById('edit_section_id_field').value = btn.dataset.id;
    document.getElementById('edit_section_code_field').value = btn.dataset.code;
    document.getElementById('edit_section_program_field').value = btn.dataset.program;
    document.getElementById('edit_section_year_field').value = btn.dataset.year;
    document.getElementById('edit_section_status_field').value = btn.dataset.status;
    if (document.getElementById('edit_section_semester_field')) {
        document.getElementById('edit_section_semester_field').value = btn.dataset.semester || '1st';
    }
    document.getElementById('editSectionModalTitle').innerHTML = `Edit Section: ${btn.dataset.code}`;
    new bootstrap.Modal(document.getElementById('editSectionModal')).show();
}
    
    else if (e.target.closest('.delete-section-btn')) {
        const btn = e.target.closest('.delete-section-btn');
        document.getElementById('delete_section_name').textContent = btn.dataset.name;
        document.getElementById('confirmDeleteSection').dataset.id = btn.dataset.id;
        new bootstrap.Modal(document.getElementById('deleteSectionModal')).show();
    }
    
    else if (e.target.closest('.view-section-details-btn')) {
        const btn = e.target.closest('.view-section-details-btn');
        viewSectionDetails(btn.dataset.id);
    }
    
    else if (e.target.closest('.manage-subjects-btn')) {
        const btn = e.target.closest('.manage-subjects-btn');
        manageSectionSubjects(btn.dataset.id, btn.dataset.code);
    }
    
    else if (e.target.closest('.assign-students-btn')) {
        const btn = e.target.closest('.assign-students-btn');
        manageSectionStudents(btn.dataset.id, btn.dataset.code, btn.dataset.program, btn.dataset.year);
    }
    
    else if (e.target.closest('#confirmDeleteCourse')) {
        const btn = e.target.closest('#confirmDeleteCourse');
        document.getElementById('delete_course_id').value = btn.dataset.id;
        document.getElementById('deleteCourseForm').submit();
    }
    
    else if (e.target.closest('#confirmDeleteSubject')) {
        const btn = e.target.closest('#confirmDeleteSubject');
        document.getElementById('delete_subject_id').value = btn.dataset.id;
        document.getElementById('deleteSubjectForm').submit();
    }
    
    else if (e.target.closest('#confirmDeleteSection')) {
        const btn = e.target.closest('#confirmDeleteSection');
        document.getElementById('delete_section_id').value = btn.dataset.id;
        document.getElementById('deleteSectionForm').submit();
    }
});

// ====================================================
// DOMContentLoaded EVENT
// ====================================================

document.addEventListener('DOMContentLoaded', function() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    setupAutoUppercase();
    setupPricePreviews();
    
    const calculationCourseSelect = document.getElementById('calculationCourseSelect');
    if (calculationCourseSelect) {
        calculationCourseSelect.addEventListener('change', calculatePaymentBreakdown);
    }
    
    const calculateRemainingBtn = document.getElementById('calculateRemainingBtn');
    if (calculateRemainingBtn) {
        calculateRemainingBtn.addEventListener('click', calculateRemainingPayment);
    }
    
    const quickCalculateBtn = document.getElementById('quickCalculateBtn');
    if (quickCalculateBtn) {
        quickCalculateBtn.addEventListener('click', quickCalculate);
    }
    
    const sectionCourseSelect = document.getElementById('section_course_id');
    if (sectionCourseSelect) {
        sectionCourseSelect.addEventListener('change', function() {
            onCourseSelectForSection(this.value, false);
        });
    }
    
    const yearLevelSelect = document.getElementById('year_level');
    if (yearLevelSelect) {
        yearLevelSelect.addEventListener('change', function() {
            const courseId = document.getElementById('section_course_id')?.value;
            if (courseId) onCourseSelectForSection(courseId, false);
        });
    }
    
    const studentSearchInput = document.getElementById('studentSearchInput');
    if (studentSearchInput) {
        studentSearchInput.addEventListener('input', function() {
            setTimeout(filterStudentTable, 300);
        });
    }
    
    const replaceExistingCheckbox = document.getElementById('replaceExistingCheckbox');
    if (replaceExistingCheckbox) {
        replaceExistingCheckbox.addEventListener('change', checkForWarnings);
    }
    
    const selectAllStudents = document.getElementById('selectAllStudents');
    if (selectAllStudents) {
        selectAllStudents.addEventListener('change', function() {
            document.querySelectorAll('.student-checkbox').forEach(cb => cb.checked = this.checked);
            checkForWarnings();
        });
    }
    
});
</script>

<?php renderPageEnd(); ?>