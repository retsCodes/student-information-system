<?php
require_once '../init.php';
require_once '../theme.php';
require_once '../layout.php';

requireRole('student');

$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];

// Get student basic info
$stmt = $pdo->prepare("
    SELECT si.*, u.name, u.email
    FROM students_info si 
    JOIN users u ON si.user_id = u.user_id 
    WHERE si.user_id = ?
");
$stmt->execute([$user_id]);
$student_info = $stmt->fetch(PDO::FETCH_ASSOC);

$program = $student_info['program'] ?? '';
$current_year_level = $student_info['year_level'] ?? 1;
$is_irregular = ($student_info['student_type'] ?? 'regular') === 'irregular';

// Get subjects from student's assigned sections (current enrollment)
$assigned_subjects = [];
$stmt = $pdo->prepare("
    SELECT DISTINCT s.*, sec.year_level as section_year, sec.semester as section_semester, 
           sec.section_code
    FROM student_sections ss
    JOIN sections sec ON ss.section_id = sec.id
    JOIN subject_sections subsec ON sec.id = subsec.section_id
    JOIN subjects s ON subsec.subject_id = s.id
    WHERE ss.student_id = ?
");
$stmt->execute([$user_id]);
$assigned_subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);



// Get directly assigned subjects
$direct_subjects = [];
$stmt = $pdo->prepare("
    SELECT DISTINCT s.*, 'direct' as source
    FROM student_subjects ss
    JOIN subjects s ON ss.subject_id = s.id
    WHERE ss.student_id = ? AND ss.status = 'active'
");
$stmt->execute([$user_id]);
$direct_subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Merge all subjects the student is taking
$all_assigned = array_merge($assigned_subjects, $direct_subjects);
$unique_assigned = [];
foreach ($all_assigned as $subj) {
    $key = $subj['id'];
    if (!isset($unique_assigned[$key])) {
        $unique_assigned[$key] = $subj;
    }
}
$assigned_subjects_list = array_values($unique_assigned);



// ========== GET COMPLETED SUBJECTS (OLD COURSE HISTORY) ==========
$completed_subjects = [];
try {
    $stmt = $pdo->prepare("
        SELECT scc.*, s.subject_code, s.subject_name, s.units
        FROM student_course_completion scc
        JOIN subjects s ON scc.subject_id = s.id
        WHERE scc.student_id = ?
        ORDER BY scc.year_level, scc.semester, scc.date_completed DESC
    ");
    $stmt->execute([$user_id]);
    $completed_subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $completed_subjects = [];
}

// ========== GET COURSE ENROLLMENT HISTORY ==========
$enrollment_history = [];
try {
    $stmt = $pdo->prepare("
        SELECT sce.*, c.course_code, c.course_name
        FROM student_course_enrollment sce
        LEFT JOIN courses c ON sce.course_id = c.id
        WHERE sce.student_id = ?
        ORDER BY sce.enrollment_date DESC
    ");
    $stmt->execute([$user_id]);
    $enrollment_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $enrollment_history = [];
}

// ========== GET TRANSFER HISTORY ==========
$transfer_history = [];
try {
    $stmt = $pdo->prepare("
        SELECT sct.*, u.name as approved_by_name
        FROM student_course_transfers sct
        LEFT JOIN users u ON sct.approved_by = u.user_id
        WHERE sct.student_id = ?
        ORDER BY sct.transfer_date DESC
    ");
    $stmt->execute([$user_id]);
    $transfer_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $transfer_history = [];
}

// ========== BUILD CURRICULUM (FULL 4 YEARS) ==========
// Get curriculum subjects for the student's program
$curriculum_subjects = [];

// First try to get from course_curriculum if course_id exists
$course_id = $student_info['course_id'] ?? null;
if ($course_id) {
    $stmt = $pdo->prepare("
        SELECT s.*, cc.year_level, cc.semester
        FROM course_curriculum cc
        JOIN subjects s ON cc.subject_id = s.id
        WHERE cc.course_id = ?
        ORDER BY cc.year_level, FIELD(cc.semester, '1st', '2nd'), s.subject_code
    ");
    $stmt->execute([$course_id]);
    $curriculum_subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// If no curriculum found, try by program name
if (empty($curriculum_subjects) && $program) {
    $stmt = $pdo->prepare("
        SELECT s.*, s.year_level, s.semester
        FROM subjects s
        WHERE s.program = ? OR s.program = 'General Education'
        ORDER BY s.year_level, FIELD(s.semester, '1st', '2nd'), s.subject_code
    ");
    $stmt->execute([$program]);
    $curriculum_subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Create lookup for assigned subjects by year/semester
$assigned_map = [];
foreach ($assigned_subjects_list as $subj) {
    $year = $subj['section_year'] ?? $current_year_level;
    $sem = $subj['section_semester'] ?? '1st';
    $assigned_map[$year][$sem][$subj['id']] = $subj;
}

// Create lookup for completed subjects
$completed_map = [];
foreach ($completed_subjects as $comp) {
    $key = $comp['subject_id'] . '_' . $comp['year_level'] . '_' . $comp['semester'];
    $completed_map[$key] = $comp;
}

// Build full curriculum array (Years 1-4)
$all_curriculum = [];
$overall_total_units = 0;

// Ensure we have years 1-4
for ($year = 1; $year <= 4; $year++) {
    $all_curriculum[$year] = ['1st' => [], '2nd' => []];
}

// Populate with curriculum subjects
foreach ($curriculum_subjects as $subject) {
    $year = $subject['year_level'];
    $semester = $subject['semester'];
    
    if ($year < 1 || $year > 4) continue;
    if (!in_array($semester, ['1st', '2nd'])) $semester = '1st';
    
    $key = $subject['id'] . '_' . $year . '_' . $semester;
    $is_taking = isset($assigned_map[$year][$semester][$subject['id']]);
    $is_completed = isset($completed_map[$key]);
    
    $subject['is_taking'] = $is_taking;
    $subject['is_completed'] = $is_completed;
    
    if ($is_completed) {
        $subject['grade'] = $completed_map[$key]['grade'];
        $subject['date_received'] = $completed_map[$key]['date_completed'];
        $subject['completion_status'] = 'completed';
    } elseif ($is_taking) {
        $subject['grade'] = null;
        $subject['date_received'] = null;
        $subject['completion_status'] = 'current';
    } else {
        $subject['grade'] = null;
        $subject['date_received'] = null;
        $subject['completion_status'] = 'not_taken';
    }
    
    $all_curriculum[$year][$semester][] = $subject;
    $overall_total_units += $subject['units'];
}

// Add extra subjects for irregular students (not in curriculum)
if ($is_irregular) {
    foreach ($assigned_subjects_list as $subj) {
        $found = false;
        foreach ($curriculum_subjects as $cs) {
            if ($cs['id'] == $subj['id']) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            $year = $subj['section_year'] ?? $current_year_level;
            $semester = $subj['section_semester'] ?? '1st';
            $subj['is_taking'] = true;
            $subj['is_completed'] = false;
            $subj['is_extra'] = true;
            $subj['completion_status'] = 'current';
            $subj['grade'] = null;
            $subj['date_received'] = null;
            $all_curriculum[$year][$semester][] = $subj;
            $overall_total_units += $subj['units'];
        }
    }
    
    // Sort each semester's subjects
    foreach ($all_curriculum as $year => $semesters) {
        foreach ($semesters as $semester => $subjects) {
            usort($all_curriculum[$year][$semester], function($a, $b) {
                return strcmp($a['subject_code'], $b['subject_code']);
            });
        }
    }
}

// Calculate semester totals
$semester_totals = [];
foreach ($all_curriculum as $year => $semesters) {
    foreach ($semesters as $semester => $subjects) {
        $semester_totals[$year][$semester] = array_sum(array_column($subjects, 'units'));
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
        WHERE cs.section_id IN (SELECT section_id FROM student_sections WHERE student_id = ?)
        ORDER BY FIELD(cs.day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), cs.start_time
    ");
    $stmt->execute([$user_id]);
    $class_schedule = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $class_schedule = [];
}

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

$completed_page = isset($_GET['completed_page']) ? max(1, intval($_GET['completed_page'])) : 1;
$completed_per_page = 10;
$completed_offset = ($completed_page - 1) * $completed_per_page;
$completed_total = count($completed_subjects);
$completed_pages = ceil($completed_total / $completed_per_page);
$completed_paginated = array_slice($completed_subjects, $completed_offset, $completed_per_page);

// Pagination for transfer history
$transfer_page = isset($_GET['transfer_page']) ? max(1, intval($_GET['transfer_page'])) : 1;
$transfer_per_page = 10;
$transfer_offset = ($transfer_page - 1) * $transfer_per_page;
$transfer_total = count($transfer_history);
$transfer_pages = ceil($transfer_total / $transfer_per_page);
$transfer_paginated = array_slice($transfer_history, $transfer_offset, $transfer_per_page);

// Pagination for enrollment history
$enrollment_page = isset($_GET['enrollment_page']) ? max(1, intval($_GET['enrollment_page'])) : 1;
$enrollment_per_page = 10;
$enrollment_offset = ($enrollment_page - 1) * $enrollment_per_page;
$enrollment_total = count($enrollment_history);
$enrollment_pages = ceil($enrollment_total / $enrollment_per_page);
$enrollment_paginated = array_slice($enrollment_history, $enrollment_offset, $enrollment_per_page);

renderPageStart('Academic Progress', 'student', 'academic_progress.php');
?>

<style>
.academic-progress-container {
    max-width: 1400px;
    margin: 0 auto;
}

.summary-stats {
    margin-bottom: 25px;
}

.stat-box {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px;
    padding: 15px;
    text-align: center;
    margin-bottom: 15px;
}

.stat-box h3 {
    font-size: 28px;
    margin: 0;
}

.stat-box small {
    opacity: 0.9;
}

.year-card {
    background: white;
    border-radius: 12px;
    margin-bottom: 25px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    overflow: hidden;
    page-break-inside: avoid;
    break-inside: avoid;
}

.year-header {
    padding: 15px 20px;
    background: #2c3e50;
    color: white;
}

.year-header.completed { background: #27ae60; }
.year-header.current { background: #f39c12; }
.year-header.upcoming { background: #7f8c8d; }

.semester-block {
    margin-bottom: 20px;
    border-bottom: 1px solid #eee;
}

.semester-header {
    padding: 10px 20px;
    background: #ecf0f1;
    font-weight: bold;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.semester-total {
    font-size: 12px;
    background: #3498db;
    color: white;
    padding: 3px 10px;
    border-radius: 20px;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th {
    background: #f8f9fa;
    padding: 12px;
    text-align: left;
    border-bottom: 2px solid #dee2e6;
}

td {
    padding: 10px 12px;
    border-bottom: 1px solid #eee;
}

tr.completed {
    background-color: #e8f5e9;
}

tr.current {
    background-color: #fff3e0;
}

tr.extra {
    background-color: #f3e5f5;
}

.grade-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

.grade-excellent { background: #27ae60; color: white; }
.grade-good { background: #3498db; color: white; }
.grade-average { background: #f39c12; color: white; }
.grade-pass { background: #95a5a6; color: white; }
.grade-fail { background: #e74c3c; color: white; }

.status-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: bold;
}

.status-completed { background: #27ae60; color: white; }
.status-current { background: #f39c12; color: white; }
.status-not-taken { background: #95a5a6; color: white; }

.extra-badge {
    background: #9b59b6;
    color: white;
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 4px;
    margin-left: 8px;
}

.legend {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    margin-top: 15px;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
}

.print-btn {
    background: #3498db;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
    margin-bottom: 20px;
    width: 100%;
}

.print-btn:hover {
    background: #2980b9;
}

.schedule-slot {
    border-left: 3px solid #28a745;
    background: #f8f9fa;
    margin-bottom: 8px;
    padding: 10px;
    border-radius: 4px;
}

.view-btn {
    font-size: 12px;
    padding: 2px 8px;
}

@media print {
    .no-print, .print-btn, .sidebar, nav, .summary-stats, .history-section {
        display: none !important;
    }
    .main-content {
        margin-left: 0 !important;
        padding: 0 !important;
    }
    .year-card {
        page-break-after: avoid;
        break-inside: avoid;
    }
}
</style>

<div class="row">
    <!-- MAIN CONTENT (Left - 9 columns) -->
    <div class="col-lg-9">
        <div class="academic-progress-container">
            <div class="card" style="border-radius: 10px; overflow: hidden;">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Academic Progress</h4>
                    <small><?php echo htmlspecialchars($program); ?> - Curriculum Overview</small>
                    <?php if ($is_irregular): ?>
                        <span class="badge bg-warning text-dark ms-2">Irregular Student</span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (empty($all_curriculum) || (empty($all_curriculum[1]['1st']) && empty($all_curriculum[1]['2nd']))): ?>
                        <div class="alert alert-warning text-center">
                            <h5>No Curriculum Found</h5>
                            <p>Please contact the administrator.</p>
                        </div>
                    <?php else: ?>
                        <?php for ($year = 1; $year <= 4; $year++): 
                            $year_status = $year < $current_year_level ? 'completed' : ($year == $current_year_level ? 'current' : 'upcoming');
                            $has_content = false;
                            foreach (['1st', '2nd'] as $sem) {
                                if (!empty($all_curriculum[$year][$sem])) $has_content = true;
                            }
                            if (!$has_content) continue;
                        ?>
                            <div class="year-card">
                                <div class="year-header <?php echo $year_status; ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0">
                                            YEAR <?php echo $year; ?>
                                            <?php if ($year_status == 'completed'): ?>
                                                <i class="fas fa-check-circle ms-2"></i>
                                            <?php elseif ($year_status == 'current'): ?>
                                                <i class="fas fa-book-open ms-2"></i>
                                            <?php else: ?>
                                                <i class="fas fa-clock ms-2"></i>
                                            <?php endif; ?>
                                        </h5>
                                    </div>
                                </div>
                                <div class="card-body p-0">
                                    <?php foreach (['1st', '2nd'] as $semester_name): 
                                        if (isset($all_curriculum[$year][$semester_name]) && !empty($all_curriculum[$year][$semester_name])):
                                            $semester_units = $semester_totals[$year][$semester_name] ?? 0;
                                    ?>
                                        <div class="semester-block">
                                            <div class="semester-header">
                                                <span><?php echo $semester_name; ?> Semester</span>
                                                <span class="semester-total"><?php echo $semester_units; ?> units</span>
                                            </div>
                                            <div style="overflow-x: auto;">
                                                <table class="table table-sm">
                                                    <thead>
                                                        <tr>
                                                            <th>Subject Code</th>
                                                            <th>Subject Title</th>
                                                            <th width="60" class="text-center">Units</th>
                                                            <th width="80" class="text-center">Grade</th>
                                                            <th width="100" class="text-center">Status</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($all_curriculum[$year][$semester_name] as $subject): 
                                                            $row_class = '';
                                                            if ($subject['is_completed'] ?? false) {
                                                                $row_class = 'completed';
                                                            } elseif (($subject['is_taking'] ?? false) && ($subject['completion_status'] ?? '') == 'current') {
                                                                $row_class = 'current';
                                                            } elseif (isset($subject['is_extra']) && $subject['is_extra']) {
                                                                $row_class = 'extra';
                                                            }
                                                            
                                                            $grade = $subject['grade'] ?? null;
                                                            $grade_class = '';
                                                            if ($grade) {
                                                                if ($grade <= 1.5) $grade_class = 'grade-excellent';
                                                                elseif ($grade <= 2.0) $grade_class = 'grade-good';
                                                                elseif ($grade <= 2.75) $grade_class = 'grade-average';
                                                                elseif ($grade == 3.0) $grade_class = 'grade-pass';
                                                                elseif ($grade >= 5.0) $grade_class = 'grade-fail';
                                                            }
                                                        ?>
                                                            <tr class="<?php echo $row_class; ?>">
                                                                <td>
                                                                    <code><?php echo htmlspecialchars($subject['subject_code']); ?></code>
                                                                    <?php if (isset($subject['is_extra']) && $subject['is_extra']): ?>
                                                                        <span class="extra-badge">Extra</span>
                                                                    <?php endif; ?>
                                                                 </span></td>
                                                                <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                                                                <td class="text-center"><?php echo $subject['units']; ?></td>
                                                                <td class="text-center">
                                                                    <?php if ($grade): ?>
                                                                        <span class="grade-badge <?php echo $grade_class; ?>">
                                                                            <?php echo number_format($grade, 2); ?>
                                                                        </span>
                                                                    <?php else: ?>
                                                                        —
                                                                    <?php endif; ?>
                                                                </span></td>
                                                                <td class="text-center">
                                                                    <?php if ($subject['is_completed'] ?? false): ?>
                                                                        <span class="status-badge status-completed">Completed</span>
                                                                    <?php elseif (($subject['is_taking'] ?? false) && ($subject['completion_status'] ?? '') == 'current'): ?>
                                                                        <span class="status-badge status-current">Enrolled</span>
                                                                    <?php else: ?>
                                                                        <span class="status-badge status-not-taken">Not Yet Taken</span>
                                                                    <?php endif; ?>
                                                                </span></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    <?php endif; endforeach; ?>
                                </div>
                            </div>
                        <?php endfor; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT SIDEBAR -->
    <div class="col-lg-3">
        <div class="summary-stats">
            <div class="stat-box">
                <h3><?php echo $overall_total_units; ?></h3>
                <small>Total Curriculum Units</small>
            </div>
            <div class="stat-box">
                <h3><?php echo count($assigned_subjects_list); ?></h3>
                <small>Currently Enrolled</small>
            </div>
            <div class="stat-box">
                <h3><?php echo $current_year_level; ?></h3>
                <small>Current Year Level</small>
            </div>
            <div class="stat-box">
                <h3><?php echo $is_irregular ? 'Irregular' : 'Regular'; ?></h3>
                <small>Student Type</small>
            </div>
        </div>

        <!-- Class Schedule Widget -->
        <div class="card shadow mb-3">
            <div class="card-header bg-primary text-white">
                <h6 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Weekly Schedule</h6>
            </div>
            <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                <?php if (empty($class_schedule)): ?>
                    <div class="text-center py-2 text-muted">No class schedule available</div>
                <?php else: ?>
                    <?php foreach($schedule_by_day as $day => $classes): if (!empty($classes)): ?>
                        <div class="mb-2">
                            <strong class="text-primary"><?php echo $day; ?></strong>
                            <?php foreach($classes as $class): ?>
                                <div class="schedule-slot small">
                                    <strong><?php echo htmlspecialchars($class['subject_code']); ?></strong>
                                    <div><?php echo date('g:i A', strtotime($class['start_time'])); ?> - <?php echo date('g:i A', strtotime($class['end_time'])); ?></div>
                                    <div class="text-muted"><?php echo htmlspecialchars($class['room'] ?? 'TBA'); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Legend -->
        <div class="card shadow">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0">Legend</h6>
            </div>
            <div class="card-body">
                <div class="legend">
                    <div class="legend-item"><span class="status-badge status-completed">Completed</span> <small>Completed</small></div>
                    <div class="legend-item"><span class="status-badge status-current">Enrolled</span> <small>Currently Enrolled</small></div>
                    <div class="legend-item"><span class="status-badge status-not-taken">Not Yet Taken</span> <small>Future Subjects</small></div>
                    <div class="legend-item"><span class="extra-badge">Extra</span> <small>Extra Subject</small></div>
                </div>
                <hr>
                <div class="legend">
                    <div class="legend-item"><span class="grade-badge grade-excellent">1.0-1.5</span> <small>Excellent</small></div>
                    <div class="legend-item"><span class="grade-badge grade-good">1.75-2.0</span> <small>Good</small></div>
                    <div class="legend-item"><span class="grade-badge grade-average">2.25-2.75</span> <small>Average</small></div>
                    <div class="legend-item"><span class="grade-badge grade-pass">3.0</span> <small>Pass</small></div>
                    <div class="legend-item"><span class="grade-badge grade-fail">5.0</span> <small>Fail</small></div>
                </div>
            </div>
        </div>
        
        <div class="no-print mt-3">
            <button class="print-btn" onclick="window.print()">
                <i class="fas fa-print"></i> Print Progress
            </button>
        </div>
    </div>
</div>

<!-- ========== ACADEMIC HISTORY SECTION (AT THE BOTTOM) ========== -->
<div class="row history-section mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-history me-2"></i>Academic History
                </h5>
            </div>
            <div class="card-body">
                
                <!-- Transfer History Button -->
                <?php if (!empty($transfer_history)): ?>
                <div class="mb-3">
                    <button class="btn btn-outline-warning" type="button" data-bs-toggle="collapse" data-bs-target="#transferHistoryCollapse" aria-expanded="false">
                        <i class="fas fa-exchange-alt me-2"></i>Course Transfer History (<?php echo count($transfer_history); ?>)
                    </button>
                    <div class="collapse mt-2" id="transferHistoryCollapse">
                        <div class="card card-body">
                            <div class="table-responsive">
                                <table class="table table-sm table-striped">
                                    <thead class="table-light">
                                        <tr>
                                            <th>From Program</th>
                                            <th>To Program</th>
                                            <th>Transfer Date</th>
                                            <th>Year Level</th>
                                            <th>Reason</th>
                                            <th>Approved By</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($transfer_history as $transfer): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($transfer['from_course_code'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($transfer['to_course_code']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($transfer['transfer_date'])); ?></td>
                                            <td>Year <?php echo $transfer['year_level_at_transfer']; ?></td>
                                            <td><?php echo htmlspecialchars($transfer['reason'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($transfer['approved_by_name'] ?? 'System'); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Enrollment History Button -->
                <?php if (!empty($enrollment_history)): ?>
                <div class="mb-3">
                    <button class="btn btn-outline-info" type="button" data-bs-toggle="collapse" data-bs-target="#enrollmentHistoryCollapse" aria-expanded="false">
                        <i class="fas fa-book-open me-2"></i>Course Enrollment History (<?php echo count($enrollment_history); ?>)
                    </button>
                    <div class="collapse mt-2" id="enrollmentHistoryCollapse">
                        <div class="card card-body">
                            <div class="table-responsive">
                                <table class="table table-sm table-striped">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Course</th>
                                            <th>Enrollment Date</th>
                                            <th>Graduation Date</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($enrollment_history as $enrollment): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($enrollment['course_code'] ?? 'N/A'); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($enrollment['course_name'] ?? ''); ?></small>
                                             </span></td>
                                            <td><?php echo date('M d, Y', strtotime($enrollment['enrollment_date'])); ?></td>
                                            <td><?php echo $enrollment['actual_graduation'] ? date('M d, Y', strtotime($enrollment['actual_graduation'])) : '—'; ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $enrollment['status'] === 'active' ? 'success' : 
                                                        ($enrollment['status'] === 'transferred' ? 'warning' : 'secondary'); 
                                                ?>">
                                                    <?php echo ucfirst($enrollment['status'] ?? 'Unknown'); ?>
                                                </span>
                                             </span></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Completed Subjects Button -->
                <?php if (!empty($completed_subjects)): ?><!-- Completed Subjects Table with Pagination -->
<div class="mb-3">
    <button class="btn btn-outline-success" type="button" data-bs-toggle="collapse" data-bs-target="#completedSubjectsCollapse" aria-expanded="false">
        <i class="fas fa-check-circle me-2"></i>Completed Subjects from Previous Courses (<?php echo $completed_total; ?>)
    </button>
    <div class="collapse mt-3" id="completedSubjectsCollapse">
        <div class="card card-body">
            <div class="table-responsive">
                <table class="table table-sm table-striped">
                    <thead class="table-light">
                        <tr>
                            <th>Subject Code</th>
                            <th>Subject Name</th>
                            <th>Units</th>
                            <th>Grade</th>
                            <th>Year/Semester</th>
                            <th>Date Completed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($completed_paginated)): ?>
                            <tr><td colspan="6" class="text-center text-muted">No completed subjects found</td></tr>
                        <?php else: ?>
                            <?php foreach ($completed_paginated as $subject): ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($subject['subject_code']); ?></code></td>
                                <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                                <td><?php echo $subject['units']; ?> units</span></td>
                                <td>
                                    <?php if ($subject['grade']): ?>
                                        <span class="grade-badge <?php 
                                            echo $subject['grade'] <= 1.5 ? 'grade-excellent' : 
                                                ($subject['grade'] <= 2.0 ? 'grade-good' : 
                                                ($subject['grade'] <= 2.75 ? 'grade-average' : 
                                                ($subject['grade'] == 3.0 ? 'grade-pass' : 'grade-fail'))); 
                                        ?>">
                                            <?php echo number_format($subject['grade'], 2); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                 </span></td>
                                <td>Year <?php echo $subject['year_level']; ?> / <?php echo ucfirst($subject['semester']); ?> Semester</span></td>
                                <td><?php echo $subject['date_completed'] ? date('M d, Y', strtotime($subject['date_completed'])) : '—'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($completed_pages > 1): ?>
                <div class="mt-3">
                    <?php 
                    $completed_params = $_GET;
                    unset($completed_params['completed_page']);
                    echo renderPagination($completed_page, $completed_pages, 'academic_progress.php', $completed_params);
                    ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
                <?php endif; ?>
                
                <?php if (empty($transfer_history) && empty($enrollment_history) && empty($completed_subjects)): ?>
                    <div class="alert alert-info text-center mb-0">
                        <i class="fas fa-info-circle me-2"></i>No previous academic history found.
                    </div>
                <?php endif; ?>
                
            </div>
        </div>
    </div>
</div>

<?php renderPageEnd(); ?>