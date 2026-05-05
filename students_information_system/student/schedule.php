<?php
require_once '../init.php';
require_once '../theme.php';
require_once '../layout.php';

requireRole('student');

$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];

// Get student basic info
$stmt = $pdo->prepare("
    SELECT si.*, u.name, u.email, c.id as course_id, c.course_code, c.course_name
    FROM students_info si 
    JOIN users u ON si.user_id = u.user_id 
    LEFT JOIN courses c ON si.course_id = c.id
    WHERE si.user_id = ?
");
$stmt->execute([$user_id]);
$student_info = $stmt->fetch(PDO::FETCH_ASSOC);

// Get current sections for this student
$stmt = $pdo->prepare("
    SELECT s.id, s.section_code, s.program, s.year_level, s.semester, s.section_name, s.course_id
    FROM sections s
    JOIN student_sections ss ON s.id = ss.section_id
    WHERE ss.student_id = ? AND s.status = 'active'
");
$stmt->execute([$user_id]);
$current_sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get subjects from assigned sections (via subject_sections)
$all_subjects = [];
$section_subjects = [];

foreach ($current_sections as $section) {
    // Get subjects from this section only
    $stmt = $pdo->prepare("
        SELECT sub.*, subsec.section_id
        FROM subjects sub
        JOIN subject_sections subsec ON sub.id = subsec.subject_id
        WHERE subsec.section_id = ?
        ORDER BY sub.subject_code
    ");
    $stmt->execute([$section['id']]);
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $section_subjects[$section['id']] = [
        'section_info' => $section,
        'subjects' => $subjects
    ];
    
    foreach ($subjects as $subject) {
        if (!isset($all_subjects[$subject['id']])) {
            $all_subjects[$subject['id']] = $subject;
        }
    }
}

// Also get directly assigned subjects (if any)
$stmt = $pdo->prepare("
    SELECT s.* 
    FROM subjects s
    JOIN student_subjects ss ON s.id = ss.subject_id
    WHERE ss.student_id = ? AND ss.status = 'active'
");
$stmt->execute([$user_id]);
$direct_assigned = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($direct_assigned as $subject) {
    if (!isset($all_subjects[$subject['id']])) {
        $all_subjects[$subject['id']] = $subject;
        // Add to a special "direct" section if no section
        if (!isset($section_subjects['direct'])) {
            $section_subjects['direct'] = [
                'section_info' => [
                    'id' => 'direct',
                    'section_code' => 'Direct Assignment',
                    'program' => $student_info['program'] ?? 'N/A',
                    'year_level' => $student_info['year_level'] ?? 1,
                    'semester' => '1st',
                    'section_name' => 'Directly Assigned Subjects'
                ],
                'subjects' => []
            ];
        }
        $section_subjects['direct']['subjects'][] = $subject;
    }
}

// Calculate total units
$total_units = array_sum(array_column($all_subjects, 'units'));

// Build payment status for exam types
$payment_status = [];

// Get all exam payments for this student
$stmt = $pdo->prepare("
    SELECT description, payment_status, issued_date 
    FROM payments 
    WHERE student_id = ? 
    AND (payment_category = 'exam' OR description LIKE '%exam%' OR description LIKE '%prelim%' OR description LIKE '%midterm%')
    ORDER BY issued_date DESC
");
$stmt->execute([$user_id]);
$exam_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Determine which exam types are paid
$paid_exams = [];
foreach ($exam_payments as $payment) {
    $desc = strtolower($payment['description']);
    $status = $payment['payment_status'];
    if (strpos($desc, 'prelim') !== false) {
        $paid_exams['prelim'] = $status;
    }
    if (strpos($desc, 'midterm') !== false) {
        $paid_exams['midterm'] = $status;
    }
    if (strpos($desc, 'prefinals') !== false) {
        $paid_exams['prefinals'] = $status;
    }
    if (strpos($desc, 'final') !== false) {
        $paid_exams['finals'] = $status;
    }
}

// Apply to all subjects
foreach ($all_subjects as $subject) {
    $subject_id = $subject['id'];
    foreach (['prelim', 'midterm', 'prefinals', 'finals'] as $exam_type) {
        if (isset($paid_exams[$exam_type]) && ($paid_exams[$exam_type] === 'paid' || $paid_exams[$exam_type] === 'partial')) {
            $payment_status[$subject_id][$exam_type] = $paid_exams[$exam_type];
        } else {
            $payment_status[$subject_id][$exam_type] = 'unpaid';
        }
    }
}

// Get class schedule
$class_schedule = [];
try {
    $stmt = $pdo->prepare("
        SELECT cs.*, s.subject_code, s.subject_name, sec.section_code
        FROM class_schedule cs
        JOIN subjects s ON cs.subject_id = s.id
        JOIN sections sec ON cs.section_id = sec.id
        WHERE cs.section_id IN (
            SELECT section_id FROM student_sections WHERE student_id = ?
        )
        ORDER BY FIELD(cs.day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), cs.start_time
    ");
    $stmt->execute([$user_id]);
    $class_schedule = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $class_schedule = [];
}

// Organize schedule by day
$schedule_by_day = [
    'Monday' => [], 'Tuesday' => [], 'Wednesday' => [], 
    'Thursday' => [], 'Friday' => [], 'Saturday' => [], 'Sunday' => []
];
foreach ($class_schedule as $class) {
    $day = $class['day_of_week'];
    if (isset($schedule_by_day[$day])) {
        $schedule_by_day[$day][] = $class;
    }
}

renderPageStart('My Study Load & Schedule', 'student', 'schedule.php');
?>

<style>
.study-load-card {
    border-left: 4px solid #007bff;
    transition: all 0.3s ease;
}
.study-load-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.payment-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 5px;
    margin-top: 10px;
}
.schedule-slot {
    border-left: 3px solid #28a745;
    background: #f8f9fa;
    margin-bottom: 8px;
    padding: 10px;
    border-radius: 4px;
}
.section-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}
.info-card {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    color: white;
    border: none;
}
.summary-card {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    color: white;
    border: none;
}
.payment-status-badge {
    font-size: 10px;
    padding: 2px 6px;
}
.subject-badge {
    font-size: 12px;
    padding: 2px 6px;
}
</style>

<div class="container-fluid">
    <!-- Student Information Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card info-card shadow">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h3 class="card-title mb-1"><?php echo htmlspecialchars($student_info['name'] ?? 'Student'); ?></h3>
                            <p class="card-text mb-1">
                                <strong>Student ID:</strong> <?php echo htmlspecialchars($user_id); ?> | 
                                <strong>Program:</strong> <?php echo htmlspecialchars($student_info['program'] ?? 'Not Set'); ?> | 
                                <strong>Year Level:</strong> <?php echo $student_info['year_level'] ?? 'Not Set'; ?>
                            </p>
                            <?php if (!empty($student_info['course_code'])): ?>
                            <p class="card-text mb-0">
                                <strong>Course:</strong> <?php echo htmlspecialchars($student_info['course_code'] . ' - ' . ($student_info['course_name'] ?? '')); ?>
                            </p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="display-4 fw-bold"><?php echo $total_units; ?></div>
                            <p class="mb-0">Total Units</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Academic Summary -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card summary-card text-center shadow">
                <div class="card-body">
                    <div class="display-6 fw-bold"><?php echo count($current_sections); ?></div>
                    <p class="mb-0">Sections</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card summary-card text-center shadow">
                <div class="card-body">
                    <div class="display-6 fw-bold"><?php echo count($all_subjects); ?></div>
                    <p class="mb-0">Subjects</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card summary-card text-center shadow">
                <div class="card-body">
                    <div class="display-6 fw-bold"><?php echo $total_units; ?></div>
                    <p class="mb-0">Total Units</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card summary-card text-center shadow">
                <div class="card-body">
                    <div class="display-6 fw-bold"><?php echo $student_info['year_level'] ?? '-'; ?></div>
                    <p class="mb-0">Year Level</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Study Load by Section -->
        <div class="col-lg-8">
            <?php if (empty($section_subjects)): ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-book-open fa-3x text-muted mb-3"></i>
                        <h4>No Study Load Assigned</h4>
                        <p class="text-muted">You are not currently enrolled in any sections or subjects.</p>
                        <p class="text-muted small">Please contact your registrar for enrollment assistance.</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach($section_subjects as $section_id => $data): 
                    $section = $data['section_info'];
                    $subjects = $data['subjects'];
                    if (empty($subjects)) continue;
                ?>
                    <div class="card mb-4 shadow">
                        <div class="section-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h4 class="mb-1"><?php echo htmlspecialchars($section['section_code']); ?></h4>
                                    <p class="mb-0">
                                        <?php echo htmlspecialchars($section['program']); ?> - 
                                        Year <?php echo $section['year_level']; ?>
                                        <?php if (!empty($section['section_name'])): ?>
                                            | <?php echo htmlspecialchars($section['section_name']); ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-light text-dark fs-6">
                                        <?php 
                                        $section_units = array_sum(array_column($subjects, 'units'));
                                        echo $section_units . ' units';
                                        ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php foreach($subjects as $subject): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card study-load-card h-100">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h6 class="card-title mb-0 text-primary">
                                                    <?php echo htmlspecialchars($subject['subject_code']); ?>
                                                </h6>
                                                <span class="badge bg-primary subject-badge">
                                                    <?php echo $subject['units']; ?> units
                                                </span>
                                            </div>
                                            
                                            <h6 class="card-subtitle mb-2 text-dark">
                                                <?php echo htmlspecialchars($subject['subject_name']); ?>
                                            </h6>
                                            
                                            <?php if (!empty($subject['description'])): ?>
                                                <p class="text-muted small mb-2"><?php echo htmlspecialchars(substr($subject['description'], 0, 100)); ?></p>
                                            <?php endif; ?>

                                            <!-- Exam Payment Status -->
                                            <div class="mt-3">
                                                <h6 class="small text-muted mb-2">Exam Payment Status:</h6>
                                                <div class="payment-grid">
                                                    <?php 
                                                    $types = ['prelim', 'midterm', 'prefinals', 'finals'];
                                                    $type_labels = ['Prelim', 'Midterm', 'Prefinals', 'Finals'];
                                                    for($i = 0; $i < count($types); $i++):
                                                        $type = $types[$i];
                                                        $label = $type_labels[$i];
                                                        $status = isset($payment_status[$subject['id']][$type]) ? $payment_status[$subject['id']][$type] : 'unpaid';
                                                        $badge_class = match($status) {
                                                            'paid' => 'success',
                                                            'partial' => 'warning',
                                                            'unpaid' => 'danger',
                                                            default => 'secondary'
                                                        };
                                                    ?>
                                                        <div class="text-center">
                                                            <small class="d-block text-muted"><?php echo $label; ?></small>
                                                            <span class="badge bg-<?php echo $badge_class; ?> payment-status-badge w-100">
                                                                <?php echo ucfirst($status); ?>
                                                            </span>
                                                        </div>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Class Schedule & Quick Info -->
        <div class="col-lg-4">
            <!-- Class Schedule -->
            <div class="card shadow mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-calendar-alt me-2"></i>Weekly Schedule
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($class_schedule)): ?>
                        <div class="text-center py-3">
                            <i class="fas fa-clock fa-2x text-muted mb-2"></i>
                            <p class="text-muted mb-0">No class schedule available</p>
                            <p class="text-muted small mt-2">Schedule will appear once sections and subjects are assigned.</p>
                        </div>
                    <?php else: ?>
                        <div class="schedule-container" style="max-height: 500px; overflow-y: auto;">
                            <?php foreach($schedule_by_day as $day => $classes): ?>
                                <?php if (!empty($classes)): ?>
                                    <div class="mb-3">
                                        <h6 class="text-primary border-bottom pb-1"><?php echo $day; ?></h6>
                                        <?php foreach($classes as $class): ?>
                                            <div class="schedule-slot">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div class="flex-grow-1">
                                                        <strong class="d-block"><?php echo htmlspecialchars($class['subject_code']); ?></strong>
                                                        <small class="text-muted d-block"><?php echo htmlspecialchars($class['subject_name']); ?></small>
                                                        <small class="text-muted d-block">Room: <?php echo htmlspecialchars($class['room'] ?? 'TBA'); ?></small>
                                                    </div>
                                                    <div class="text-end ms-2">
                                                        <small class="text-primary fw-bold d-block">
                                                            <?php echo date('g:i A', strtotime($class['start_time'])); ?>
                                                        </small>
                                                        <small class="text-primary fw-bold d-block">
                                                            - <?php echo date('g:i A', strtotime($class['end_time'])); ?>
                                                        </small>
                                                        <small class="text-muted"><?php echo htmlspecialchars($class['section_code']); ?></small>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="card shadow">
                <div class="card-header bg-info text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-bar me-2"></i>Study Load Summary
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>Total Sections:</strong>
                        <span class="float-end"><?php echo count($current_sections); ?></span>
                    </div>
                    <div class="mb-3">
                        <strong>Total Subjects:</strong>
                        <span class="float-end"><?php echo count($all_subjects); ?></span>
                    </div>
                    <div class="mb-3">
                        <strong>Total Units:</strong>
                        <span class="float-end fw-bold text-primary"><?php echo $total_units; ?></span>
                    </div>
                    <div class="mb-3">
                        <strong>Student Status:</strong>
                        <span class="float-end">
                            <span class="badge bg-<?php echo ($student_info['student_type'] ?? 'regular') === 'regular' ? 'success' : 'warning'; ?>">
                                <?php echo ucfirst($student_info['student_type'] ?? 'regular'); ?>
                            </span>
                        </span>
                    </div>
                    <div class="mb-3">
                        <strong>Enrollment Status:</strong>
                        <span class="float-end">
                            <span class="badge bg-<?php echo ($student_info['enrollment_status'] ?? 'enrolled') === 'enrolled' ? 'success' : 'danger'; ?>">
                                <?php echo ucfirst($student_info['enrollment_status'] ?? 'Enrolled'); ?>
                            </span>
                        </span>
                    </div>
                    <hr>
                    <small class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        This study load reflects your current enrollment for the active semester.
                    </small>
                </div>
            </div>

            <!-- Payment Legend -->
            <div class="card shadow mt-4">
                <div class="card-header bg-secondary text-white">
                    <h6 class="card-title mb-0">Exam Payment Status Legend</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-column gap-2">
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="badge bg-success payment-status-badge">Paid</span>
                            <small class="text-muted ms-2">Exam fee fully paid</small>
                        </div>
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="badge bg-warning payment-status-badge">Partial</span>
                            <small class="text-muted ms-2">Partial payment made</small>
                        </div>
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="badge bg-danger payment-status-badge">Unpaid</span>
                            <small class="text-muted ms-2">Exam fee not yet paid</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php renderPageEnd(); ?>