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

// Find course by program
$all_courses = $pdo->query("SELECT id, course_code, course_name, total_units FROM courses")->fetchAll(PDO::FETCH_ASSOC);

$course_id = null;
$course_code = null;
$course_name = null;
$course_total_units = 0;

foreach ($all_courses as $course) {
    if (stripos($program, $course['course_code']) !== false || stripos($course['course_name'], $program) !== false) {
        $course_id = $course['id'];
        $course_code = $course['course_code'];
        $course_name = $course['course_name'];
        $course_total_units = $course['total_units'];
        break;
    }
}

if (!$course_id && !empty($all_courses)) {
    $course = $all_courses[0];
    $course_id = $course['id'];
    $course_code = $course['course_code'];
    $course_name = $course['course_name'];
    $course_total_units = $course['total_units'];
}

// Get subjects from student's assigned sections
$assigned_subjects = [];
$stmt = $pdo->prepare("
    SELECT DISTINCT s.*, sec.year_level as section_year, sec.semester as section_semester, 
           sec.section_code, 'section' as source
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

// Get curriculum subjects
$curriculum_subjects = [];
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

// Create lookup for assigned subjects by year/semester
$assigned_map = [];
foreach ($assigned_subjects_list as $subj) {
    $year = $subj['section_year'] ?? $current_year_level;
    $sem = $subj['section_semester'] ?? '1st';
    $assigned_map[$year][$sem][$subj['id']] = $subj;
}

// Get grades/completion records
$stmt = $pdo->prepare("
    SELECT subject_id, grade, date_completed, status, year_level, semester
    FROM student_course_completion 
    WHERE student_id = ?
");
$stmt->execute([$user_id]);
$completions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$completion_map = [];
foreach ($completions as $c) {
    $key = $c['subject_id'] . '_' . ($c['year_level'] ?? '') . '_' . ($c['semester'] ?? '');
    $completion_map[$key] = $c;
}

// Build complete curriculum
$all_curriculum = [];
$overall_total_units = 0;

foreach ($curriculum_subjects as $subject) {
    $year = $subject['year_level'];
    $semester = $subject['semester'];
    $key = $subject['id'] . '_' . $year . '_' . $semester;
    
    $is_taking = isset($assigned_map[$year][$semester][$subject['id']]);
    
    if (isset($completion_map[$key])) {
        $completion = $completion_map[$key];
        $subject['grade'] = $completion['grade'];
        $subject['date_received'] = $completion['date_completed'];
        $subject['completion_status'] = $completion['status'];
        $subject['is_completed'] = true;
    } else {
        $subject['grade'] = null;
        $subject['date_received'] = null;
        $subject['is_completed'] = false;
        
        if ($is_taking) {
            $subject['completion_status'] = 'current';
        } elseif ($year < $current_year_level) {
            $subject['completion_status'] = 'missing';
        } else {
            $subject['completion_status'] = 'not_taken';
        }
    }
    
    $subject['is_taking'] = $is_taking;
    $overall_total_units += $subject['units'];
    
    $all_curriculum[$year][$semester][] = $subject;
}

// Add extra subjects for irregular students
if ($is_irregular) {
    $extra_subjects_map = [];
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
            $key = $subj['id'] . '_' . $year . '_' . $semester;
            
            if (isset($completion_map[$key])) {
                $completion = $completion_map[$key];
                $subj['grade'] = $completion['grade'];
                $subj['date_received'] = $completion['date_completed'];
                $subj['completion_status'] = $completion['status'];
                $subj['is_completed'] = true;
            } else {
                $subj['grade'] = null;
                $subj['date_received'] = null;
                $subj['completion_status'] = 'current';
                $subj['is_completed'] = false;
            }
            $subj['is_taking'] = true;
            $subj['is_extra'] = true;
            $overall_total_units += $subj['units'];
            $extra_subjects_map[$year][$semester][] = $subj;
        }
    }
    
    foreach ($extra_subjects_map as $year => $semesters) {
        foreach ($semesters as $semester => $subjects) {
            if (!isset($all_curriculum[$year][$semester])) {
                $all_curriculum[$year][$semester] = [];
            }
            $all_curriculum[$year][$semester] = array_merge($all_curriculum[$year][$semester], $subjects);
            usort($all_curriculum[$year][$semester], function($a, $b) {
                return strcmp($a['subject_code'], $b['subject_code']);
            });
        }
    }
}

ksort($all_curriculum);

// Calculate semester totals
$semester_totals = [];
foreach ($all_curriculum as $year => $semesters) {
    foreach ($semesters as $semester => $subjects) {
        $semester_totals[$year][$semester] = array_sum(array_column($subjects, 'units'));
    }
}

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
.status-missing { background: #e74c3c; color: white; }

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

@media print {
    .no-print, .print-btn, .sidebar, nav, .summary-stats, .card.shadow {
        display: none !important;
    }
    .main-content {
        margin-left: 0 !important;
        padding: 0 !important;
    }
    .year-card {
        page-break-after: always;
        break-after: page;
        margin-bottom: 0;
        box-shadow: none;
    }
    .year-card:last-child {
        page-break-after: auto;
        break-after: auto;
    }
    .year-header {
        background: #2c3e50 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .status-badge, .grade-badge, .extra-badge {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    tr.completed, tr.current, tr.extra {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    body {
        margin: 0;
        padding: 0;
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
                    <small><?php echo htmlspecialchars($course_name ?? $course_code ?? 'Curriculum'); ?> - Curriculum Overview</small>
                    <?php if ($is_irregular): ?>
                        <span class="badge bg-warning text-dark ms-2">Irregular Student</span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (empty($all_curriculum)): ?>
                        <div class="alert alert-warning text-center">
                            <h5>No Curriculum Found</h5>
                            <p>Please contact the administrator.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($all_curriculum as $year => $semesters): 
                            $year_status = $year < $current_year_level ? 'completed' : ($year == $current_year_level ? 'current' : 'upcoming');
                            $academic_year = (2022 + $year - 1) . '-' . (2023 + $year - 1);
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
                                        <span class="badge bg-light text-dark"><?php echo $academic_year; ?></span>
                                    </div>
                                </div>
                                <div class="card-body p-0">
                                    <?php foreach (['1st', '2nd'] as $semester_name): 
                                        if (isset($semesters[$semester_name]) && !empty($semesters[$semester_name])):
                                            $semester_units = array_sum(array_column($semesters[$semester_name], 'units'));
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
                                                            <th width="100" class="text-center">Date</th>
                                                            <th width="100" class="text-center">Status</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($semesters[$semester_name] as $subject): 
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
                                                                    <?php echo $subject['date_received'] ? date('M Y', strtotime($subject['date_received'])) : '—'; ?>
                                                                </span></td>
                                                                <td class="text-center">
                                                                    <?php if ($grade): ?>
                                                                        <span class="status-badge status-completed">Completed</span>
                                                                    <?php elseif (($subject['is_taking'] ?? false) && ($subject['completion_status'] ?? '') == 'current'): ?>
                                                                        <span class="status-badge status-current">Enrolled</span>
                                                                    <?php elseif (($subject['completion_status'] ?? '') == 'missing'): ?>
                                                                        <span class="status-badge status-missing">No Grade</span>
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
                        <?php endforeach; ?>
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
                <small>Total Units</small>
            </div>
            <div class="stat-box">
                <h3><?php echo count($assigned_subjects_list); ?></h3>
                <small>Subjects Enrolled</small>
            </div>
            <div class="stat-box">
                <h3><?php echo $current_year_level; ?></h3>
                <small>Year Level</small>
            </div>
            <div class="stat-box">
                <h3><?php echo $is_irregular ? 'Irregular' : 'Regular'; ?></h3>
                <small>Student Type</small>
            </div>
        </div>

        <div class="card shadow">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0">Legend</h6>
            </div>
            <div class="card-body">
                <div class="legend">
                    <div class="legend-item"><span class="status-badge status-completed">Completed</span> <small>Completed</small></div>
                    <div class="legend-item"><span class="status-badge status-current">Enrolled</span> <small>Currently Enrolled</small></div>
                    <div class="legend-item"><span class="status-badge status-not-taken">Not Yet Taken</span> <small>Future Subjects</small></div>
                    <div class="legend-item"><span class="status-badge status-missing">No Grade</span> <small>Missing Grade</small></div>
                    <div class="legend-item"><span class="extra-badge">Extra</span> <small>Extra Subject (Irregular)</small></div>
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

<?php renderPageEnd(); ?>