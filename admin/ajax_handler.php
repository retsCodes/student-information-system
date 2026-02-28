<?php
/**
 * Consolidated AJAX Handler for Student Information System
 * Handles all AJAX requests from various modules
 */

require_once '../init.php';

// Set JSON header
header('Content-Type: application/json');

// Get the action
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Initialize response array
$response = ['success' => false, 'message' => 'Invalid action'];

try {
    $pdo = getDBConnection();
    
    switch ($action) {
        // =======================================================
        // COURSE & CURRICULUM RELATED ACTIONS
        // =======================================================
        
        case 'get_course_curriculum':
            $course_id = intval($_GET['course_id'] ?? 0);
            $response = getCourseCurriculum($pdo, $course_id);
            break;
            
        case 'load_curriculum':
            $course_id = intval($_GET['course_id'] ?? 0);
            $response = loadCurriculum($pdo, $course_id);
            break;
            
        case 'calculate_payment':
            $course_id = intval($_GET['course_id'] ?? 0);
            $response = calculatePayment($pdo, $course_id);
            break;
            
        case 'get_course_details':
            $course_id = intval($_GET['course_id'] ?? 0);
            $response = getCourseDetails($pdo, $course_id);
            break;

        case 'get_assessment_file':
            $student_id = sanitizeInput($_GET['student_id'] ?? $_SESSION['user_id'] ?? '');
            $response = getAssessmentFile($pdo, $student_id, $_SESSION);
            break;
            
        // =======================================================
        // USER MANAGEMENT RELATED ACTIONS
        // =======================================================
            
        case 'get_user_details':
            $user_id = sanitizeInput($_GET['user_id'] ?? '');
            $response = getUserDetails($pdo, $user_id);
            break;

        case 'get_student_info':
            $student_id = sanitizeInput($_GET['student_id'] ?? '');
            $student_info = getStudentInfo($student_id);
            
            if ($student_info) {
                $response = ['success' => true, 'student' => $student_info];
            } else {
                $response = ['success' => false, 'message' => 'Student not found'];
            }
            break;
            
        // =======================================================
        // SUBJECT & SECTION RELATED ACTIONS
        // =======================================================
            
        case 'get_subject_sections':
            $subject_id = intval($_GET['subject_id'] ?? 0);
            $response = getSubjectSections($pdo, $subject_id);
            break;
            
        case 'get_section_subjects':
            $section_id = intval($_GET['section_id'] ?? 0);
            $response = getSectionSubjects($pdo, $section_id);
            break;
            
        case 'get_section_info':
            $section_id = intval($_GET['section_id'] ?? 0);
            $response = getSectionInfo($pdo, $section_id);
            break;
            
        // =======================================================
        // PAYMENT RELATED ACTIONS
        // =======================================================
            
        case 'get_payment_summary':
            $student_id = sanitizeInput($_GET['student_id'] ?? '');
            $response = getPaymentSummary($pdo, $student_id);
            break;
            
        case 'calculate_student_balance':
            $student_id = sanitizeInput($_GET['student_id'] ?? '');
            $response = calculateStudentBalance($pdo, $student_id);
            break;
            
        case 'get_student_payments':
            $student_id = sanitizeInput($_GET['student_id'] ?? '');
            $limit = intval($_GET['limit'] ?? 10);
            $response = getStudentPayments($pdo, $student_id, $limit);
            break;
            
        // =======================================================
        // SETTINGS RELATED ACTIONS
        // =======================================================
            
        case 'get_settings':
            $category = $_GET['category'] ?? '';
            $response = getSettings($pdo, $category);
            break;
            
        case 'update_setting':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $response = ['success' => false, 'message' => 'Invalid request method'];
            } else {
                $name = sanitizeInput($_POST['name'] ?? '');
                $value = sanitizeInput($_POST['value'] ?? '');
                $response = updateSetting($pdo, $name, $value);
            }
            break;
            
        // =======================================================
        // REPORT & STATISTICS ACTIONS
        // =======================================================
            
        case 'get_course_statistics':
            $course_id = intval($_GET['course_id'] ?? 0);
            $response = getCourseStatistics($pdo, $course_id);
            break;
            
        case 'get_enrollment_stats':
            $response = getEnrollmentStats($pdo);
            break;
            
        case 'get_payment_stats':
            $start_date = $_GET['start_date'] ?? date('Y-m-01');
            $end_date = $_GET['end_date'] ?? date('Y-m-t');
            $response = getPaymentStats($pdo, $start_date, $end_date);
            break;
            
        default:
            $response = ['success' => false, 'message' => 'Unknown action'];
    }
    
} catch (Exception $e) {
    $response = ['success' => false, 'message' => 'Server error: ' . $e->getMessage()];
}

// Output JSON response
echo json_encode($response);
exit;

// =======================================================
// HELPER FUNCTIONS
// =======================================================

/**
 * Get course curriculum with details
 */
function getCourseCurriculum($pdo, $course_id) {
    if ($course_id <= 0) {
        return ['success' => false, 'message' => 'Invalid course ID'];
    }
    
    try {
        // Get course details
        $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
        $stmt->execute([$course_id]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$course) {
            return ['success' => false, 'message' => 'Course not found'];
        }
        
        // Get global unit price
        $unit_price = getGlobalUnitPrice($pdo);
        
        // Get curriculum subjects grouped by year and semester
        $stmt = $pdo->prepare("SELECT cc.*, s.subject_code, s.subject_name, s.units, s.description
                               FROM course_curriculum cc
                               JOIN subjects s ON cc.subject_id = s.id
                               WHERE cc.course_id = ?
                               ORDER BY cc.year_level, cc.semester, s.subject_code");
        $stmt->execute([$course_id]);
        $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate totals
        $total_course_price = $course['total_units'] * $unit_price;
        $price_per_year = $total_course_price / $course['duration_years'];
        $price_per_semester = $price_per_year / 2;
        
        // Organize by year and semester
        $curriculum = [];
        $total_units_in_curriculum = 0;
        
        foreach ($subjects as $subject) {
            $year = $subject['year_level'];
            $semester = $subject['semester'];
            if (!isset($curriculum[$year])) {
                $curriculum[$year] = [];
            }
            if (!isset($curriculum[$year][$semester])) {
                $curriculum[$year][$semester] = [];
            }
            $curriculum[$year][$semester][] = $subject;
            $total_units_in_curriculum += $subject['units'];
        }
        
        // Check if curriculum matches course total units
        $units_match = $total_units_in_curriculum == $course['total_units'];
        
        // Generate HTML content
        ob_start();
        ?>
        <div class="row">
            <div class="col-md-12">
                <div class="card mb-3">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0">Course Information</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <p><strong>Course:</strong><br><?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?></p>
                            </div>
                            <div class="col-md-3">
                                <p><strong>Duration:</strong><br><?php echo $course['duration_years']; ?> years</p>
                            </div>
                            <div class="col-md-3">
                                <p><strong>Total Units:</strong><br>
                                    <?php echo $course['total_units']; ?> units
                                    <?php if (!$units_match): ?>
                                        <br><small class="text-danger">(Curriculum: <?php echo $total_units_in_curriculum; ?> units)</small>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="col-md-3">
                                <p><strong>Unit Price:</strong><br>₱<?php echo number_format($unit_price, 2); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card mb-3">
                    <div class="card-header bg-success text-white">
                        <h6 class="mb-0">Curriculum</h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($curriculum)): ?>
                            <p class="text-muted">No subjects in curriculum yet.</p>
                        <?php else: ?>
                            <?php for ($year = 1; $year <= $course['duration_years']; $year++): ?>
                                <h5>Year <?php echo $year; ?></h5>
                                <?php if (isset($curriculum[$year])): ?>
                                    <?php for ($sem = 1; $sem <= 2; $sem++): ?>
                                        <h6 class="mt-3">Semester <?php echo $sem; ?></h6>
                                        <?php if (isset($curriculum[$year][$sem])): ?>
                                            <div class="table-responsive">
                                                <table class="table table-sm">
                                                    <thead>
                                                        <tr>
                                                            <th>Subject Code</th>
                                                            <th>Subject Name</th>
                                                            <th>Units</th>
                                                            <th>Price</th>
                                                            <th>Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php 
                                                        $semester_units = 0;
                                                        $semester_price = 0;
                                                        foreach ($curriculum[$year][$sem] as $subject): 
                                                            $subject_price = $subject['units'] * $unit_price;
                                                            $semester_units += $subject['units'];
                                                            $semester_price += $subject_price;
                                                        ?>
                                                        <tr>
                                                            <td><code><?php echo htmlspecialchars($subject['subject_code']); ?></code></td>
                                                            <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                                                            <td><span class="badge bg-primary"><?php echo $subject['units']; ?> units</span></td>
                                                            <td>₱<?php echo number_format($subject_price, 2); ?></td>
                                                            <td>
                                                                <button class="btn btn-sm btn-outline-danger" 
                                                                        onclick="removeCurriculumItem('<?php echo $subject['id']; ?>', '<?php echo htmlspecialchars($subject['subject_code']); ?>')">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                    <tfoot>
                                                        <tr>
                                                            <td colspan="2"><strong>Semester Total:</strong></td>
                                                            <td><span class="badge bg-info"><?php echo $semester_units; ?> units</span></td>
                                                            <td><strong>₱<?php echo number_format($semester_price, 2); ?></strong></td>
                                                            <td></td>
                                                        </tr>
                                                    </tfoot>
                                                </table>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-muted">No subjects for this semester.</p>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                <?php else: ?>
                                    <p class="text-muted">No subjects for this year.</p>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <div class="alert alert-info mt-3">
                                <h6>Curriculum Summary</h6>
                                <p><strong>Total Units in Curriculum:</strong> <?php echo $total_units_in_curriculum; ?> units</p>
                                <p><strong>Total Curriculum Price:</strong> ₱<?php echo number_format($total_units_in_curriculum * $unit_price, 2); ?></p>
                                <?php if (!$units_match): ?>
                                    <p class="text-danger">
                                        <i class="fas fa-exclamation-triangle"></i> 
                                        Curriculum units (<?php echo $total_units_in_curriculum; ?>) don't match course total (<?php echo $course['total_units']; ?>)
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
        $curriculum_html = ob_get_clean();
        
        return [
            'success' => true,
            'html' => $curriculum_html,
            'course' => $course,
            'unit_price' => $unit_price,
            'total_units' => $total_units_in_curriculum,
            'total_price' => $total_units_in_curriculum * $unit_price
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Load curriculum for tabbed display
 */
function loadCurriculum($pdo, $course_id) {
    if ($course_id <= 0) {
        return ['success' => false, 'message' => 'Invalid course ID'];
    }
    
    try {
        // Get curriculum subjects grouped by year and semester
        $stmt = $pdo->prepare("SELECT cc.*, s.subject_code, s.subject_name, s.units, s.description
                               FROM course_curriculum cc
                               JOIN subjects s ON cc.subject_id = s.id
                               WHERE cc.course_id = ?
                               ORDER BY cc.year_level, cc.semester, s.subject_code");
        $stmt->execute([$course_id]);
        $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get course info
        $course_stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
        $course_stmt->execute([$course_id]);
        $course = $course_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get global unit price
        $unit_price = getGlobalUnitPrice($pdo);
        
        // Organize by year and semester
        $curriculum = [];
        $total_units = 0;
        foreach ($subjects as $subject) {
            $year = $subject['year_level'];
            $semester = $subject['semester'];
            if (!isset($curriculum[$year])) {
                $curriculum[$year] = [];
            }
            if (!isset($curriculum[$year][$semester])) {
                $curriculum[$year][$semester] = [];
            }
            $curriculum[$year][$semester][] = $subject;
            $total_units += $subject['units'];
        }
        
        // Generate HTML
        ob_start();
        
        if (empty($curriculum)) {
            echo '<div class="text-center text-muted py-4">
                    <i class="fas fa-book-open fa-3x mb-3"></i>
                    <h5>No subjects in curriculum</h5>
                    <p>Add subjects using the form on the left.</p>
                  </div>';
        } else {
            echo '<nav class="year-tabs">';
            echo '<div class="nav nav-tabs" id="curriculumYearTabs" role="tablist">';
            
            // Create year tabs
            for ($year = 1; $year <= ($course['duration_years'] ?? 4); $year++) {
                $active = $year === 1 ? 'active' : '';
                echo '<button class="nav-link ' . $active . '" id="year' . $year . '-tab" 
                         data-bs-toggle="tab" data-bs-target="#year' . $year . '" 
                         type="button" role="tab">
                        Year ' . $year . '
                      </button>';
            }
            
            echo '</div>';
            echo '</nav>';
            
            echo '<div class="tab-content mt-3" id="curriculumTabContent">';
            
            // Create year content
            for ($year = 1; $year <= ($course['duration_years'] ?? 4); $year++) {
                $active = $year === 1 ? 'show active' : '';
                echo '<div class="tab-pane fade ' . $active . '" id="year' . $year . '" role="tabpanel">';
                
                if (isset($curriculum[$year])) {
                    for ($sem = 1; $sem <= 2; $sem++) {
                        echo '<h6>Semester ' . $sem . '</h6>';
                        
                        if (isset($curriculum[$year][$sem])) {
                            echo '<div class="table-responsive">';
                            echo '<table class="table table-sm">';
                            echo '<thead><tr><th>Subject Code</th><th>Subject Name</th><th>Units</th><th>Price</th><th>Actions</th></tr></thead>';
                            echo '<tbody>';
                            
                            $semester_units = 0;
                            $semester_price = 0;
                            
                            foreach ($curriculum[$year][$sem] as $subject) {
                                $subject_price = $subject['units'] * $unit_price;
                                $semester_units += $subject['units'];
                                $semester_price += $subject_price;
                                
                                echo '<tr>';
                                echo '<td><code>' . htmlspecialchars($subject['subject_code']) . '</code></td>';
                                echo '<td>' . htmlspecialchars($subject['subject_name']) . '</td>';
                                echo '<td><span class="badge bg-primary">' . $subject['units'] . ' units</span></td>';
                                echo '<td>₱' . number_format($subject_price, 2) . '</td>';
                                echo '<td>';
                                echo '<button class="btn btn-sm btn-outline-danger" 
                                              onclick="removeCurriculumItem(' . $subject['id'] . ', \'' . htmlspecialchars($subject['subject_code']) . '\')">';
                                echo '<i class="fas fa-trash"></i>';
                                echo '</button>';
                                echo '</td>';
                                echo '</tr>';
                            }
                            
                            echo '</tbody>';
                            echo '<tfoot>';
                            echo '<tr><td colspan="2"><strong>Semester Total:</strong></td>';
                            echo '<td><span class="badge bg-info">' . $semester_units . ' units</span></td>';
                            echo '<td><strong>₱' . number_format($semester_price, 2) . '</strong></td>';
                            echo '<td></td></tr>';
                            echo '</tfoot>';
                            echo '</table>';
                            echo '</div>';
                        } else {
                            echo '<p class="text-muted">No subjects for this semester.</p>';
                        }
                        
                        if ($sem < 2) echo '<hr>';
                    }
                } else {
                    echo '<p class="text-muted">No subjects for this year.</p>';
                }
                
                echo '</div>';
            }
            
            echo '</div>';
            
            // Summary
            echo '<div class="alert alert-info mt-3">';
            echo '<h6><i class="fas fa-info-circle me-2"></i>Curriculum Summary</h6>';
            echo '<p><strong>Total Units in Curriculum:</strong> ' . $total_units . ' units</p>';
            echo '<p><strong>Total Curriculum Price:</strong> ₱' . number_format($total_units * $unit_price, 2) . '</p>';
            if ($course && $total_units != $course['total_units']) {
                echo '<p class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i> 
                      Curriculum units (' . $total_units . ') don\'t match course total (' . $course['total_units'] . ')</p>';
            }
            echo '</div>';
        }
        
        $html = ob_get_clean();
        
        return [
            'success' => true,
            'html' => $html,
            'total_units' => $total_units,
            'total_price' => $total_units * $unit_price
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Calculate payment breakdown for a course
 */
function calculatePayment($pdo, $course_id) {
    if ($course_id <= 0) {
        return ['success' => false, 'message' => 'Invalid course ID'];
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
        $stmt->execute([$course_id]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$course) {
            return ['success' => false, 'message' => 'Course not found'];
        }
        
        $unit_price = getGlobalUnitPrice($pdo);
        
        // Calculate totals
        $total_price = $course['total_units'] * $unit_price;
        $price_per_year = $total_price / $course['duration_years'];
        $price_per_semester = $price_per_year / 2;
        $subjects_per_semester = $course['total_units'] / ($course['duration_years'] * 2);
        $price_per_subject = $unit_price * $subjects_per_semester;
        $price_per_exam = $price_per_semester / 3; // Assuming 3 exams per semester
        
        // Generate HTML
        ob_start();
        ?>
        <div class="calculation-row">
            <span>Total Course Units:</span>
            <span><strong><?php echo $course['total_units']; ?> units</strong></span>
        </div>
        <div class="calculation-row">
            <span>Unit Price:</span>
            <span><strong>₱<?php echo number_format($unit_price, 2); ?></strong></span>
        </div>
        <hr>
        <div class="calculation-row total">
            <span>Full Course Total:</span>
            <span><strong>₱<?php echo number_format($total_price, 2); ?></strong></span>
        </div>
        <hr>
        <div class="calculation-row">
            <span>Per Year Payment:</span>
            <span>₱<?php echo number_format($price_per_year, 2); ?> × <?php echo $course['duration_years']; ?> years</span>
        </div>
        <div class="calculation-row">
            <span>Per Semester Payment:</span>
            <span>₱<?php echo number_format($price_per_semester, 2); ?> × <?php echo $course['duration_years'] * 2; ?> semesters</span>
        </div>
        <div class="calculation-row">
            <span>Per Exam Payment:</span>
            <span>₱<?php echo number_format($price_per_exam, 2); ?> × <?php echo $course['duration_years'] * 6; ?> exams</span>
        </div>
        <div class="calculation-row">
            <span>Estimated Per Subject:</span>
            <span>₱<?php echo number_format($price_per_subject, 2); ?> × ~<?php echo round($subjects_per_semester, 1); ?> units/semester</span>
        </div>
        <?php
        $html = ob_get_clean();
        
        return [
            'success' => true,
            'html' => $html,
            'calculations' => [
                'total_units' => $course['total_units'],
                'unit_price' => $unit_price,
                'total_price' => $total_price,
                'per_year' => $price_per_year,
                'per_semester' => $price_per_semester,
                'per_exam' => $price_per_exam,
                'per_subject' => $price_per_subject
            ]
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Get course details
 */
function getCourseDetails($pdo, $course_id) {
    if ($course_id <= 0) {
        return ['success' => false, 'message' => 'Invalid course ID'];
    }
    
    try {
        // Get course with statistics
        $stmt = $pdo->prepare("SELECT c.*, 
                               COUNT(DISTINCT cc.id) as subject_count,
                               COUNT(DISTINCT sce.student_id) as student_count
                               FROM courses c
                               LEFT JOIN course_curriculum cc ON c.id = cc.course_id
                               LEFT JOIN student_course_enrollment sce ON c.id = sce.course_id AND sce.status = 'active'
                               WHERE c.id = ?
                               GROUP BY c.id");
        $stmt->execute([$course_id]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$course) {
            return ['success' => false, 'message' => 'Course not found'];
        }
        
        // Get unit price
        $unit_price = getGlobalUnitPrice($pdo);
        
        return [
            'success' => true,
            'course' => $course,
            'unit_price' => $unit_price,
            'total_price' => $course['total_units'] * $unit_price
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Get user details - RETURNS DATA ARRAY, NOT HTML
 */
function getUserDetails($pdo, $user_id) {
    if (empty($user_id)) {
        return ['success' => false, 'message' => 'Invalid user ID'];
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT u.*, 
                   COALESCE(si.program, ei.role) as additional_info,
                   COALESCE(si.year_level, '') as year_level,
                   si.student_type,
                   si.enrollment_status,
                   si.enrollment_date,
                   si.number as contact_number,
                   si.address
            FROM users u
            LEFT JOIN students_info si ON u.user_id = si.user_id
            LEFT JOIN employee_info ei ON u.user_id = ei.user_id
            WHERE u.user_id = ?
        ");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }
        
        // Get additional info based on role
        $additional_info = [];
        
        if ($user['role'] === 'student') {
            // Get assigned sections
            $stmt = $pdo->prepare("
                SELECT s.section_code, s.program, s.year_level 
                FROM student_sections ss 
                JOIN sections s ON ss.section_id = s.id 
                WHERE ss.student_id = ?
            ");
            $stmt->execute([$user_id]);
            $additional_info['sections'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get enrolled subjects
            $stmt = $pdo->prepare("
                SELECT sub.subject_code, sub.subject_name, sub.units
                FROM student_subjects ss 
                JOIN subjects sub ON ss.subject_id = sub.id 
                WHERE ss.student_id = ?
            ");
            $stmt->execute([$user_id]);
            $additional_info['subjects'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get payment summary
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_payments,
                    SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_count,
                    SUM(CASE WHEN payment_status = 'unpaid' THEN 1 ELSE 0 END) as unpaid_count,
                    SUM(CASE WHEN payment_status = 'partial' THEN 1 ELSE 0 END) as partial_count,
                    SUM(CASE WHEN payment_status = 'unpaid' THEN amount ELSE 0 END) as unpaid_amount,
                    SUM(CASE WHEN payment_status = 'partial' THEN remaining_balance ELSE 0 END) as partial_amount,
                    SUM(amount) as total_amount,
                    SUM(CASE WHEN payment_status = 'paid' THEN amount ELSE 0 END) as paid_amount
                FROM payments 
                WHERE student_id = ?
            ");
            $stmt->execute([$user_id]);
            $additional_info['payment_summary'] = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } else {
            // For employees, get payment issuance summary
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_issued,
                    SUM(amount) as total_amount_issued,
                    COUNT(DISTINCT student_id) as unique_students_served
                FROM payments 
                WHERE issued_by = ?
            ");
            $stmt->execute([$user_id]);
            $additional_info['issuance_summary'] = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        // Get recent activity
        $stmt = $pdo->prepare("
            SELECT action, description, created_at 
            FROM activity_logs 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        $stmt->execute([$user_id]);
        $additional_info['activity_log'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'user' => $user,
            'additional_info' => $additional_info
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Get sections for a subject
 */
function getSubjectSections($pdo, $subject_id) {
    if ($subject_id <= 0) {
        return ['success' => false, 'message' => 'Invalid subject ID'];
    }
    
    try {
        $stmt = $pdo->prepare("SELECT sections FROM subjects WHERE id = ?");
        $stmt->execute([$subject_id]);
        $subject = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$subject) {
            return ['success' => false, 'message' => 'Subject not found'];
        }
        
        $sections = json_decode($subject['sections'] ?? '[]', true) ?: [];
        
        // Generate HTML
        ob_start();
        if (empty($sections)) {
            echo '<div class="text-muted">No sections assigned</div>';
        } else {
            echo '<ul class="list-unstyled">';
            foreach ($sections as $sectionCode) {
                echo '<li><span class="badge bg-info me-1">' . htmlspecialchars($sectionCode) . '</span></li>';
            }
            echo '</ul>';
        }
        
        $html = ob_get_clean();
        
        return [
            'success' => true,
            'html' => $html,
            'sections' => $sections
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Get subjects for a section - RETURNS DATA ARRAY, NOT HTML
 */
function getSectionSubjects($pdo, $section_id) {
    if ($section_id <= 0) {
        return ['success' => false, 'message' => 'Invalid section ID'];
    }
    
    try {
        // Get section info first
        $stmt = $pdo->prepare("SELECT section_code FROM sections WHERE id = ?");
        $stmt->execute([$section_id]);
        $section = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$section) {
            return ['success' => false, 'message' => 'Section not found'];
        }
        
        // Get subjects that contain this section
        $stmt = $pdo->prepare("
            SELECT id, subject_code, subject_name, units 
            FROM subjects 
            WHERE JSON_CONTAINS(sections, JSON_QUOTE(?)) 
            ORDER BY subject_code
        ");
        $stmt->execute([$section['section_code']]);
        $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Generate HTML
        ob_start();
        if (empty($subjects)) {
            echo '<div class="text-muted">No subjects assigned</div>';
        } else {
            echo '<ul class="list-unstyled">';
            foreach ($subjects as $subject) {
                echo '<li><span class="badge bg-success me-1">' . 
                     htmlspecialchars($subject['subject_code']) . '</span> - ' .
                     htmlspecialchars($subject['subject_name']) .
                     ' (' . $subject['units'] . ' units)</li>';
            }
            echo '</ul>';
        }
        
        $html = ob_get_clean();
        
        return [
            'success' => true,
            'html' => $html,
            'subjects' => $subjects,
            'section_code' => $section['section_code']
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Get section information
 */
function getSectionInfo($pdo, $section_id) {
    if ($section_id <= 0) {
        return ['success' => false, 'message' => 'Invalid section ID'];
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT s.*, 
                   (SELECT COUNT(*) FROM student_sections WHERE section_id = s.id) as student_count,
                   (SELECT COUNT(*) FROM subjects WHERE JSON_CONTAINS(sections, JSON_QUOTE(s.section_code))) as subject_count
            FROM sections s 
            WHERE id = ?
        ");
        $stmt->execute([$section_id]);
        $section = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$section) {
            return ['success' => false, 'message' => 'Section not found'];
        }
        
        // Generate HTML
        ob_start();
        ?>
        <div class="alert alert-info">
            <strong>Section Code:</strong> <?php echo htmlspecialchars($section['section_code']); ?><br>
            <strong>Program:</strong> <?php echo htmlspecialchars($section['program']); ?><br>
            <strong>Year Level:</strong> Year <?php echo $section['year_level']; ?><br>
            <strong>Students:</strong> <?php echo $section['student_count']; ?><br>
            <strong>Subjects:</strong> <?php echo $section['subject_count']; ?><br>
            <strong>Status:</strong> <span class="badge bg-<?php echo $section['status'] === 'active' ? 'success' : 'secondary'; ?>">
                <?php echo ucfirst($section['status']); ?>
            </span>
        </div>
        <?php
        $html = ob_get_clean();
        
        return [
            'success' => true,
            'html' => $html,
            'section' => $section
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Get payment summary for a student
 */
function getPaymentSummary($pdo, $student_id) {
    if (empty($student_id)) {
        return ['success' => false, 'message' => 'Invalid student ID'];
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_payments,
                SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_count,
                SUM(CASE WHEN payment_status = 'unpaid' THEN 1 ELSE 0 END) as unpaid_count,
                SUM(CASE WHEN payment_status = 'partial' THEN 1 ELSE 0 END) as partial_count,
                SUM(CASE WHEN payment_status = 'unpaid' THEN amount ELSE 0 END) as unpaid_amount,
                SUM(CASE WHEN payment_status = 'partial' THEN remaining_balance ELSE 0 END) as partial_amount,
                SUM(amount) as total_amount,
                SUM(CASE WHEN payment_status = 'paid' THEN amount ELSE 0 END) as paid_amount,
                MIN(issued_date) as first_payment_date,
                MAX(issued_date) as last_payment_date
            FROM payments 
            WHERE student_id = ?
        ");
        $stmt->execute([$student_id]);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'summary' => $summary
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Calculate student balance
 */
function calculateStudentBalance($pdo, $student_id) {
    if (empty($student_id)) {
        return ['success' => false, 'message' => 'Invalid student ID'];
    }
    
    try {
        // Get total due from student's course
        $stmt = $pdo->prepare("
            SELECT SUM(c.total_units * ?) as total_due
            FROM student_course_enrollment sce
            JOIN courses c ON sce.course_id = c.id
            WHERE sce.student_id = ? AND sce.status = 'active'
        ");
        
        $unit_price = getGlobalUnitPrice($pdo);
        $stmt->execute([$unit_price, $student_id]);
        $total_due = $stmt->fetchColumn() ?: 0;
        
        // Get total paid
        $stmt = $pdo->prepare("
            SELECT SUM(amount) as total_paid 
            FROM payments 
            WHERE student_id = ? AND payment_status = 'paid'
        ");
        $stmt->execute([$student_id]);
        $total_paid = $stmt->fetchColumn() ?: 0;
        
        // Get pending/partial payments
        $stmt = $pdo->prepare("
            SELECT SUM(remaining_balance) as total_pending 
            FROM payments 
            WHERE student_id = ? AND payment_status IN ('unpaid', 'partial')
        ");
        $stmt->execute([$student_id]);
        $total_pending = $stmt->fetchColumn() ?: 0;
        
        $balance = $total_due - $total_paid;
        
        return [
            'success' => true,
            'total_due' => $total_due,
            'total_paid' => $total_paid,
            'total_pending' => $total_pending,
            'balance' => $balance,
            'percentage_paid' => $total_due > 0 ? round(($total_paid / $total_due) * 100, 2) : 0
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Get student payments
 */
function getStudentPayments($pdo, $student_id, $limit = 10) {
    if (empty($student_id)) {
        return ['success' => false, 'message' => 'Invalid student ID'];
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, u.name as issued_by_name
            FROM payments p
            LEFT JOIN users u ON p.issued_by = u.user_id
            WHERE p.student_id = ?
            ORDER BY p.issued_date DESC
            LIMIT ?
        ");
        $stmt->execute([$student_id, $limit]);
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Generate HTML
        ob_start();
        if (empty($payments)) {
            echo '<div class="alert alert-info">No payment records found.</div>';
        } else {
            echo '<div class="table-responsive">';
            echo '<table class="table table-sm table-striped">';
            echo '<thead>
                    <tr>
                        <th>Permit #</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Issued Date</th>
                        <th>Issued By</th>
                    </tr>
                  </thead>';
            echo '<tbody>';
            foreach ($payments as $payment) {
                $status_class = $payment['payment_status'] === 'paid' ? 'success' : 
                               ($payment['payment_status'] === 'unpaid' ? 'danger' : 'warning');
                echo '<tr>';
                echo '<td><code>' . htmlspecialchars($payment['permit_number']) . '</code></td>';
                echo '<td>₱' . number_format($payment['amount'], 2) . '</td>';
                echo '<td><span class="badge bg-' . $status_class . '">' . 
                     ucfirst($payment['payment_status']) . '</span></td>';
                echo '<td>' . date('M j, Y', strtotime($payment['issued_date'])) . '</td>';
                echo '<td>' . htmlspecialchars($payment['issued_by_name']) . '</td>';
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
            echo '</div>';
        }
        
        $html = ob_get_clean();
        
        return [
            'success' => true,
            'html' => $html,
            'payments' => $payments
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Get settings
 */
function getSettings($pdo, $category = '') {
    try {
        if ($category) {
            $stmt = $pdo->prepare("SELECT * FROM settings WHERE category = ? ORDER BY name");
            $stmt->execute([$category]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM settings ORDER BY category, name");
            $stmt->execute();
        }
        
        $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Group by category
        $grouped = [];
        foreach ($settings as $setting) {
            $grouped[$setting['category']][] = $setting;
        }
        
        return [
            'success' => true,
            'settings' => $settings,
            'grouped' => $grouped
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Update setting
 */
function updateSetting($pdo, $name, $value) {
    if (empty($name)) {
        return ['success' => false, 'message' => 'Setting name required'];
    }
    
    try {
        // Check if setting exists
        $stmt = $pdo->prepare("SELECT id, is_editable FROM settings WHERE name = ?");
        $stmt->execute([$name]);
        $setting = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$setting) {
            return ['success' => false, 'message' => 'Setting not found'];
        }
        
        if (!$setting['is_editable']) {
            return ['success' => false, 'message' => 'This setting cannot be edited'];
        }
        
        // Update setting
        $stmt = $pdo->prepare("
            UPDATE settings 
            SET value = ?, updated_at = NOW(), updated_by = ? 
            WHERE name = ?
        ");
        $stmt->execute([$value, $_SESSION['user_id'], $name]);
        
        // Log activity
        logActivity($_SESSION['user_id'], 'Setting Updated', "Updated setting: {$name} to {$value}");
        
        return ['success' => true, 'message' => 'Setting updated successfully'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Get course statistics
 */
function getCourseStatistics($pdo, $course_id) {
    if ($course_id <= 0) {
        return ['success' => false, 'message' => 'Invalid course ID'];
    }
    
    try {
        // Get enrollment stats
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_students,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_students,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_students,
                COUNT(CASE WHEN status = 'dropped' THEN 1 END) as dropped_students,
                AVG(current_year_level) as avg_year_level
            FROM student_course_enrollment 
            WHERE course_id = ?
        ");
        $stmt->execute([$course_id]);
        $enrollment_stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get year level distribution
        $stmt = $pdo->prepare("
            SELECT current_year_level, COUNT(*) as count
            FROM student_course_enrollment 
            WHERE course_id = ? AND status = 'active'
            GROUP BY current_year_level
            ORDER BY current_year_level
        ");
        $stmt->execute([$course_id]);
        $year_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get payment statistics for enrolled students
        $stmt = $pdo->prepare("
            SELECT 
                AVG(p.amount) as avg_payment,
                SUM(CASE WHEN p.payment_status = 'paid' THEN p.amount ELSE 0 END) as total_paid,
                SUM(CASE WHEN p.payment_status IN ('unpaid', 'partial') THEN p.remaining_balance ELSE 0 END) as total_outstanding
            FROM student_course_enrollment sce
            LEFT JOIN payments p ON sce.student_id = p.student_id
            WHERE sce.course_id = ? AND sce.status = 'active'
        ");
        $stmt->execute([$course_id]);
        $payment_stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'enrollment_stats' => $enrollment_stats,
            'year_distribution' => $year_distribution,
            'payment_stats' => $payment_stats
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Get enrollment statistics
 */
function getEnrollmentStats($pdo) {
    try {
        // Overall enrollment stats
        $stmt = $pdo->prepare("
            SELECT 
                (SELECT COUNT(*) FROM users WHERE role = 'student') as total_students,
                (SELECT COUNT(*) FROM users WHERE role = 'admin') as total_admins,
                (SELECT COUNT(*) FROM users WHERE role = 'cashier') as total_cashiers,
                (SELECT COUNT(*) FROM users WHERE role = 'registrar') as total_registrars
        ");
        $stmt->execute();
        $user_stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Course enrollment stats
        $stmt = $pdo->prepare("
            SELECT 
                c.course_code,
                c.course_name,
                COUNT(sce.id) as enrolled_students,
                COUNT(CASE WHEN sce.status = 'active' THEN 1 END) as active_students
            FROM courses c
            LEFT JOIN student_course_enrollment sce ON c.id = sce.course_id
            WHERE c.status = 'active'
            GROUP BY c.id
            ORDER BY enrolled_students DESC
        ");
        $stmt->execute();
        $course_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Monthly enrollment trend
        $stmt = $pdo->prepare("
            SELECT 
                DATE_FORMAT(enrollment_date, '%Y-%m') as month,
                COUNT(*) as new_enrollments
            FROM students_info 
            WHERE enrollment_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(enrollment_date, '%Y-%m')
            ORDER BY month
        ");
        $stmt->execute();
        $monthly_trend = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'user_stats' => $user_stats,
            'course_stats' => $course_stats,
            'monthly_trend' => $monthly_trend
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Get payment statistics
 */
function getPaymentStats($pdo, $start_date, $end_date) {
    try {
        // Overall payment stats
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_transactions,
                SUM(amount) as total_amount,
                SUM(CASE WHEN payment_status = 'paid' THEN amount ELSE 0 END) as total_paid,
                SUM(CASE WHEN payment_status IN ('unpaid', 'partial') THEN remaining_balance ELSE 0 END) as total_outstanding,
                AVG(amount) as avg_transaction_amount
            FROM payments 
            WHERE issued_date BETWEEN ? AND ?
        ");
        $stmt->execute([$start_date, $end_date]);
        $payment_stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Payment status distribution
        $stmt = $pdo->prepare("
            SELECT 
                payment_status,
                COUNT(*) as count,
                SUM(amount) as total_amount
            FROM payments 
            WHERE issued_date BETWEEN ? AND ?
            GROUP BY payment_status
        ");
        $stmt->execute([$start_date, $end_date]);
        $status_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Daily payment trend
        $stmt = $pdo->prepare("
            SELECT 
                issued_date,
                COUNT(*) as transaction_count,
                SUM(amount) as daily_total
            FROM payments 
            WHERE issued_date BETWEEN ? AND ?
            GROUP BY issued_date
            ORDER BY issued_date
        ");
        $stmt->execute([$start_date, $end_date]);
        $daily_trend = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Top cashiers
        $stmt = $pdo->prepare("
            SELECT 
                p.issued_by,
                u.name,
                COUNT(p.id) as transaction_count,
                SUM(p.amount) as total_issued
            FROM payments p
            JOIN users u ON p.issued_by = u.user_id
            WHERE p.issued_date BETWEEN ? AND ?
            GROUP BY p.issued_by
            ORDER BY total_issued DESC
            LIMIT 10
        ");
        $stmt->execute([$start_date, $end_date]);
        $top_cashiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'payment_stats' => $payment_stats,
            'status_distribution' => $status_distribution,
            'daily_trend' => $daily_trend,
            'top_cashiers' => $top_cashiers
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Helper function to get global unit price
 */
function getGlobalUnitPrice($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE name = 'unit_price'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? floatval($result['value']) : 1000.00;
    } catch (Exception $e) {
        return 1000.00; // Default fallback
    }
}

/**
 * Get assessment file for a student - FIXED VERSION
 */
function getAssessmentFile($pdo, $student_id, $session_data) {
    try {
        // Check if user is logged in
        if (!isset($session_data['user_id'])) {
            return ['success' => false, 'message' => 'Unauthorized access. Please login.'];
        }

        // Get current user info - FIXED: Use 'role' not 'user_role'
        $currentUserId = $session_data['user_id'];
        $currentUserRole = $session_data['role'] ?? ''; // CHANGED: 'role' instead of 'user_role'
        
        // Debug log (remove in production)
        error_log("Assessment Debug: User ID = $currentUserId, Role = $currentUserRole, Student ID = $student_id");
        
        // Determine which student to fetch assessment for
        if (empty($student_id)) {
            if ($currentUserRole === 'student') {
                $student_id = $currentUserId;
            } else {
                return ['success' => false, 'message' => 'Student ID is required.'];
            }
        }

        // Check permissions - FIXED VERSION
        $hasPermission = false;
        $viewerType = 'other';
        
        // Admin, cashier, and registrar should always have permission
        if ($currentUserRole === 'admin' || $currentUserRole === 'cashier' || $currentUserRole === 'registrar') {
            $hasPermission = true;
            $viewerType = $currentUserRole;
            error_log("Permission granted: $currentUserRole");
        } 
        // Students can only view their own assessment
        elseif ($currentUserRole === 'student' && $currentUserId === $student_id) {
            $hasPermission = true;
            $viewerType = 'self';
            error_log("Permission granted: student viewing own assessment");
        }
        
        if (!$hasPermission) {
            error_log("Permission denied: User $currentUserId ($currentUserRole) trying to view $student_id");
            return ['success' => false, 'message' => 'Insufficient permissions to view this assessment.'];
        }

        // Get student information
        $stmt = $pdo->prepare("
            SELECT 
                si.*, 
                u.email, 
                u.name as full_name,
                u.user_id,
                u.created_at,
                u.user_status
            FROM students_info si 
            JOIN users u ON si.user_id = u.user_id 
            WHERE si.user_id = ? AND u.role = 'student'
        ");
        $stmt->execute([$student_id]);
        $studentInfo = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$studentInfo) {
            return ['success' => false, 'message' => 'Student not found.'];
        }

        // Get current school year and semester
        $currentMonth = date('n');
        $currentYear = date('Y');
        
        // Determine current semester based on month
        if ($currentMonth >= 6 && $currentMonth <= 10) {
            $currentSemester = '2nd Semester';
            $schoolYear = $currentYear . '-' . ($currentYear + 1);
        } elseif ($currentMonth >= 11 || $currentMonth <= 3) {
            $currentSemester = '1st Semester';
            $schoolYear = ($currentYear - 1) . '-' . $currentYear;
        } else {
            $currentSemester = 'Summer Semester';
            $schoolYear = ($currentYear - 1) . '-' . $currentYear;
        }

        // Get enrolled subjects for the student for current semester
        $stmt = $pdo->prepare("
            SELECT DISTINCT s.* 
            FROM student_subjects ss
            JOIN subjects s ON ss.subject_id = s.id
            WHERE ss.student_id = ?
            ORDER BY s.subject_code
        ");
        $stmt->execute([$student_id]);
        $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get unit price from settings
        $unitPrice = 1000; // Default unit price
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE name = 'unit_price'");
        $stmt->execute();
        $price = $stmt->fetchColumn();
        
        if ($price) {
            $unitPrice = floatval($price);
        }

        // Get miscellaneous fees from settings
        $miscFees = 5000; // Default
        $program = strtolower($studentInfo['program'] ?? '');
        
        if ($program) {
            $stmt = $pdo->prepare("SELECT value FROM settings WHERE name = ?");
            $miscFeeKeys = [
                'misc_fees_' . $program,
                'misc_fees',
                'default_misc_fees'
            ];
            
            foreach ($miscFeeKeys as $key) {
                $stmt->execute([$key]);
                $fee = $stmt->fetchColumn();
                if ($fee) {
                    $miscFees = floatval($fee);
                    break;
                }
            }
        }

        // Get other fees (laboratory, library, etc.)
        $otherFees = [
            'laboratory' => 1000,
            'library' => 500,
            'athletic' => 300,
            'medical' => 200,
            'student_organization' => 150
        ];
        
        // Get actual other fees from settings if available
        $stmt = $pdo->prepare("SELECT name, value FROM settings WHERE name LIKE 'fee_%'");
        $stmt->execute();
        $feeSettings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        foreach ($otherFees as $key => $defaultValue) {
            $settingKey = 'fee_' . $key;
            if (isset($feeSettings[$settingKey])) {
                $otherFees[$key] = floatval($feeSettings[$settingKey]);
            }
        }
        
        $totalOtherFees = array_sum($otherFees);

        // Get payment history for the student (current school year)
        $stmt = $pdo->prepare("
            SELECT 
                p.permit_number, 
                p.amount, 
                p.remaining_balance, 
                DATE_FORMAT(p.issued_date, '%Y-%m-%d') as issued_date,
                p.payment_status,
                p.description,
                u.name as issued_by_name
            FROM payments p
            LEFT JOIN users u ON p.issued_by = u.user_id
            WHERE p.student_id = ?
            ORDER BY p.issued_date DESC
            LIMIT 10
        ");
        $stmt->execute([$student_id]);
        $paymentHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate total payments made
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) as total_paid 
            FROM payments 
            WHERE student_id = ? 
            AND payment_status IN ('paid', 'partial')
        ");
        $stmt->execute([$student_id]);
        $paymentsMade = floatval($stmt->fetchColumn());

        // Calculate total units and tuition
        $totalUnits = 0;
        $tuitionFee = 0;
        $subjectDetails = [];
        
        foreach ($subjects as $subject) {
            $subjectUnits = intval($subject['units']);
            $subjectTuition = $subjectUnits * $unitPrice;
            
            $totalUnits += $subjectUnits;
            $tuitionFee += $subjectTuition;
            
            $subjectDetails[] = [
                'code' => $subject['subject_code'],
                'name' => $subject['subject_name'],
                'units' => $subjectUnits,
                'unit_price' => $unitPrice,
                'total' => $subjectTuition,
                'description' => $subject['description'] ?? ''
            ];
        }

        // Calculate total assessment
        $totalFees = $miscFees + $totalOtherFees;
        $totalAssessment = $tuitionFee + $totalFees;
        $remainingBalance = $totalAssessment - $paymentsMade;

        // Get payment schedule/installments if any
        $stmt = $pdo->prepare("
            SELECT 
                pi.installment_number,
                pi.amount,
                pi.due_date,
                pi.status,
                pi.paid_date
            FROM payment_installments pi
            JOIN payments p ON pi.payment_id = p.id
            WHERE p.student_id = ?
            ORDER BY pi.due_date
        ");
        $stmt->execute([$student_id]);
        $installments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Prepare assessment info
        $assessment_info = [
            'student_id' => $studentInfo['user_id'],
            'student_name' => $studentInfo['full_name'],
            'student_email' => $studentInfo['email'],
            'program' => $studentInfo['program'],
            'year_level' => $studentInfo['year_level'],
            'student_type' => $studentInfo['student_type'],
            'enrollment_status' => $studentInfo['enrollment_status'],
            'student_status' => $studentInfo['user_status']
        ];

        // Prepare academic info
        $academic_info = [
            'current_semester' => $currentSemester,
            'school_year' => $schoolYear,
            'assessment_date' => date('Y-m-d H:i:s')
        ];

        // Prepare fees breakdown
        $fees_breakdown = [
            'tuition' => [
                'total_units' => $totalUnits,
                'unit_price' => $unitPrice,
                'total' => $tuitionFee
            ],
            'miscellaneous_fees' => [
                'amount' => $miscFees,
                'description' => 'Miscellaneous Fees'
            ],
            'other_fees' => $otherFees,
            'total_fees' => $totalFees
        ];

        // Prepare financial summary
        $financial_summary = [
            'total_tuition' => $tuitionFee,
            'total_fees' => $totalFees,
            'total_assessment' => $totalAssessment,
            'payments_made' => $paymentsMade,
            'remaining_balance' => $remainingBalance,
            'payment_status' => $remainingBalance <= 0 ? 'fully_paid' : ($paymentsMade > 0 ? 'partially_paid' : 'unpaid')
        ];

        // Prepare viewer info
        $viewer_info = [
            'viewer_id' => $currentUserId,
            'viewer_role' => $currentUserRole,
            'viewer_type' => $viewerType,
            'can_print' => true,
            'can_pay' => ($currentUserRole === 'cashier' || $currentUserRole === 'admin')
        ];

        return [
            'success' => true,
            'assessment_info' => $assessment_info,
            'academic_info' => $academic_info,
            'subjects' => $subjectDetails,
            'fees_breakdown' => $fees_breakdown,
            'financial_summary' => $financial_summary,
            'payment_history' => $paymentHistory,
            'installments' => $installments,
            'viewer_info' => $viewer_info
        ];
        
    } catch (Exception $e) {
        error_log("Assessment Error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Helper function to generate user initials
 */
function getInitials($name) {
    $initials = '';
    $words = explode(' ', $name);
    foreach ($words as $word) {
        if (!empty($word)) {
            $initials .= strtoupper($word[0]);
        }
    }
    return substr($initials, 0, 2);
}