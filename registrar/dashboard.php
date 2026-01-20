<?php
require_once '../init.php';
require_once '../theme.php';
require_once '../layout.php';

requireRole('registrar');

$pdo = getDBConnection();

// Get statistics
$stats = [];

// Total students
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'student'");
$stats['total_students'] = $stmt->fetch()['total'];

// Total courses
//$stmt = $pdo->query("SELECT COUNT(*) as total FROM courses WHERE status = 'active'");
//$stats['total_courses'] = $stmt->fetch()['total'];

// Total subjects
$stmt = $pdo->query("SELECT COUNT(*) as total FROM subjects");
$stats['total_subjects'] = $stmt->fetch()['total'];

// Students needing grade input
//$stmt = $pdo->query("SELECT COUNT(DISTINCT si.user_id) as total 
//                     FROM students_info si
//                     JOIN student_subjects ss ON si.user_id = ss.student_id
//                     LEFT JOIN academic_records ar ON si.user_id = ar.student_id AND ss.subject_id = ar.subject_id
//                     WHERE ar.record_id IS NULL AND si.status = 'active'");
//$stats['students_needing_grades'] = $stmt->fetch()['total'];

// Recent student grade entries
//$stmt = $pdo->query("SELECT ar.*, u.name as student_name, s.subject_code, s.subject_name,
//                     DATE_FORMAT(ar.updated_at, '%Y-%m-%d %H:%i') as updated_datetime
//                     FROM academic_records ar
//                     JOIN users u ON ar.student_id = u.user_id
//                     JOIN subjects s ON ar.subject_id = s.id
//                     ORDER BY ar.updated_at DESC 
//                     LIMIT 10");
//$recent_grades = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Students by year level
$stmt = $pdo->query("SELECT year_level, COUNT(*) as count FROM students_info WHERE status = 'active' GROUP BY year_level ORDER BY year_level");
$students_by_year = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Courses with most students
$stmt = $pdo->query("SELECT 
                     CASE 
                         WHEN program LIKE '%Information Technology%' THEN 'BSIT'
                         WHEN program LIKE '%Computer Science%' THEN 'BSCS'
                         WHEN program LIKE '%Information Systems%' THEN 'BSIS'
                         WHEN program LIKE '%Computer Engineering%' THEN 'BSCE'
                         WHEN program LIKE '%Business Administration%' THEN 'BSBA'
                         WHEN program LIKE '%Accountancy%' THEN 'BSA'
                         WHEN program LIKE '%Secondary Education%' THEN 'BSEd'
                         WHEN program LIKE '%Elementary Education%' THEN 'BEEd'
                         ELSE program 
                     END as program_code,
                     COUNT(*) as student_count
                     FROM students_info 
                     WHERE status = 'active'
                     GROUP BY program_code
                     ORDER BY student_count DESC 
                     LIMIT 6");
$program_enrollment = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent student-subject assignments
$stmt = $pdo->query("SELECT ss.*, u.name as student_name, s.subject_code, s.subject_name,
                     DATE_FORMAT(ss.assigned_at, '%Y-%m-%d %H:%i') as assigned_datetime
                     FROM student_subjects ss
                     JOIN users u ON ss.student_id = u.user_id
                     JOIN subjects s ON ss.subject_id = s.id
                     ORDER BY ss.assigned_at DESC 
                     LIMIT 10");
$recent_subject_assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if academic_records table exists, if not create a temporary view
$stmt = $pdo->query("SHOW TABLES LIKE 'academic_records'");
$academic_records_exists = $stmt->fetch();

if (!$academic_records_exists) {
    // Create a temporary table/view for demonstration
    $stats['students_needing_grades'] = 0;
    $recent_grades = [];
}

renderPageStart('Registrar Dashboard', 'registrar', 'dashboard.php');
?>

<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <?php echo renderStatsCard('Total Students', $stats['total_students'], 'fas fa-user-graduate', 'primary'); ?>
    </div>
    <div class="col-md-3 mb-3">
        <?php echo "Active Courses Shows Here"//renderStatsCard('Active Courses', $stats['total_courses'], 'fas fa-book', 'info'); ?>
    </div>
    <div class="col-md-3 mb-3">
        <?php echo renderStatsCard('Total Subjects', $stats['total_subjects'], 'fas fa-list-alt', 'success'); ?>
    </div>
    <div class="col-md-3 mb-3">
        <?php echo renderStatsCard('Need Grade Entry', $stats['students_needing_grades'], 'fas fa-edit', 'warning'); ?>
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-4">
        <?php
        // Year level distribution
        $year_labels = [];
        $year_data = [];
        $year_colors = [
            'rgb(54, 162, 235)', 'rgb(255, 99, 132)', 'rgb(255, 205, 86)',
            'rgb(75, 192, 192)', 'rgb(153, 102, 255)'
        ];
        
        foreach ($students_by_year as $index => $year) {
            $year_labels[] = 'Year ' . $year['year_level'];
            $year_data[] = $year['count'];
        }
        
        $year_chart_content = '
        <div class="chart-container" style="position: relative; height:250px;">
            <canvas id="yearLevelChart"></canvas>
        </div>
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            const ctx = document.getElementById("yearLevelChart").getContext("2d");
            const yearLevelChart = new Chart(ctx, {
                type: "bar",
                data: {
                    labels: ' . json_encode($year_labels) . ',
                    datasets: [{
                        label: "Students",
                        data: ' . json_encode($year_data) . ',
                        backgroundColor: ' . json_encode(array_slice($year_colors, 0, count($students_by_year))) . ',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: "Students by Year Level",
                            font: {
                                size: 14
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        });
        </script>';
        
        echo renderCard('Student Distribution by Year Level', $year_chart_content);
        ?>
    </div>
    
    <div class="col-md-6 mb-4">
        <?php
        // Program enrollment chart
        $program_labels = [];
        $program_data = [];
        $program_colors = [
            'rgb(54, 162, 235)', 'rgb(255, 99, 132)', 'rgb(255, 205, 86)',
            'rgb(75, 192, 192)', 'rgb(153, 102, 255)', 'rgb(201, 203, 207)'
        ];
        
        foreach ($program_enrollment as $index => $program) {
            $program_labels[] = $program['program_code'] . ' (' . $program['student_count'] . ')';
            $program_data[] = $program['student_count'];
        }
        
        $program_chart_content = '
        <div class="chart-container" style="position: relative; height:250px;">
            <canvas id="programEnrollmentChart"></canvas>
        </div>
        <div class="mt-3">
            <table class="table table-sm table-borderless">
                <tbody>';
        
        foreach ($program_enrollment as $index => $program) {
            $percentage = $stats['total_students'] > 0 ? ($program['student_count'] / $stats['total_students'] * 100) : 0;
            $program_chart_content .= '
                <tr>
                    <td><strong>' . htmlspecialchars($program['program_code']) . '</strong></td>
                    <td class="text-end">' . $program['student_count'] . ' students</td>
                    <td class="text-end">' . number_format($percentage, 1) . '%</td>
                </tr>';
        }
        
        $program_chart_content .= '
                </tbody>
            </table>
        </div>
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            const ctx2 = document.getElementById("programEnrollmentChart").getContext("2d");
            const programEnrollmentChart = new Chart(ctx2, {
                type: "pie",
                data: {
                    labels: ' . json_encode($program_labels) . ',
                    datasets: [{
                        data: ' . json_encode($program_data) . ',
                        backgroundColor: ' . json_encode(array_slice($program_colors, 0, count($program_enrollment))) . ',
                        borderWidth: 2,
                        borderColor: "#fff"
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: "bottom",
                            labels: {
                                boxWidth: 12
                            }
                        }
                    }
                }
            });
        });
        </script>';
        
        echo renderCard('Program Enrollment Distribution', $program_chart_content);
        ?>
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-4">
        <?php
        $grades_content = '
        <div class="table-responsive">
            <table class="table table-sm table-striped">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Subject</th>
                        <th>Grade</th>
                        <th>Status</th>
                        <th>Updated</th>
                    </tr>
                </thead>
                <tbody>';
        
        if (empty($recent_grades)) {
            $grades_content .= '
            <tr>
                <td colspan="5" class="text-center text-muted">No grade records found</td>
            </tr>';
        } else {
            foreach($recent_grades as $grade) {
                $student_name = htmlspecialchars($grade['student_name']);
                $subject_info = htmlspecialchars($grade['subject_code']) . ' - ' . htmlspecialchars(substr($grade['subject_name'], 0, 20));
                
                // Determine grade color
                $grade_value = $grade['grade'];
                $grade_color = 'primary';
                if ($grade_value >= 1.0 && $grade_value <= 1.5) {
                    $grade_color = 'success';
                } elseif ($grade_value >= 1.6 && $grade_value <= 2.5) {
                    $grade_color = 'warning';
                } elseif ($grade_value >= 2.6 && $grade_value <= 3.0) {
                    $grade_color = 'danger';
                } elseif ($grade_value == 5.0) {
                    $grade_color = 'dark';
                }
                
                // Truncate long names
                if (strlen($student_name) > 15) {
                    $student_name = substr($student_name, 0, 15) . '...';
                }
                
                $grades_content .= '
                <tr>
                    <td>
                        <div>
                            <strong>' . $student_name . '</strong>
                        </div>
                    </td>
                    <td><small>' . $subject_info . '</small></td>
                    <td><span class="badge bg-' . $grade_color . '">' . number_format($grade['grade'], 2) . '</span></td>
                    <td><span class="badge bg-info">' . ucfirst($grade['status']) . '</span></td>
                    <td><small>' . date('M j, g:i A', strtotime($grade['updated_datetime'])) . '</small></td>
                </tr>';
            }
        }
        
        $grades_content .= '
                </tbody>
            </table>
        </div>';
        
        $grades_footer = '<a href="manage_grades.php" class="btn btn-sm btn-outline-primary">Manage Grades</a>';
        
        echo renderCard('Recent Grade Entries', $grades_content, $grades_footer);
        ?>
    </div>
    
    <div class="col-md-6 mb-4">
        <?php
        $assignments_content = '
        <div class="table-responsive">
            <table class="table table-sm table-striped">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Subject</th>
                        <th>Assigned</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>';
        
        if (empty($recent_subject_assignments)) {
            $assignments_content .= '
            <tr>
                <td colspan="4" class="text-center text-muted">No recent subject assignments found</td>
            </tr>';
        } else {
            foreach($recent_subject_assignments as $assignment) {
                $student_name = htmlspecialchars($assignment['student_name']);
                $subject_info = htmlspecialchars($assignment['subject_code']);
                
                // Truncate long names
                if (strlen($student_name) > 15) {
                    $student_name = substr($student_name, 0, 15) . '...';
                }
                
                $assigned_date = date('M j, g:i A', strtotime($assignment['assigned_datetime']));
                
                $assignments_content .= '
                <tr>
                    <td>
                        <div>
                            <strong>' . $student_name . '</strong>
                        </div>
                    </td>
                    <td><small>' . $subject_info . '</small></td>
                    <td><small>' . $assigned_date . '</small></td>
                    <td>
                        <a href="manage_student_subjects.php?student_id=' . urlencode($assignment['student_id']) . '" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-edit"></i>
                        </a>
                    </td>
                </tr>';
            }
        }
        
        $assignments_content .= '
                </tbody>
            </table>
        </div>';
        
        $assignments_footer = '<div class="d-flex justify-content-between">
            <a href="manage_student_subjects.php" class="btn btn-sm btn-outline-primary">Manage Student Subjects</a>
            <a href="print_courses.php" class="btn btn-sm btn-outline-success">
                <i class="fas fa-print"></i> Print Courses
            </a>
        </div>';
        
        echo renderCard('Recent Subject Assignments', $assignments_content, $assignments_footer);
        ?>
    </div>
</div>

<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <a href="manage_grades.php" class="btn btn-outline-primary w-100 h-100 py-3">
                            <i class="fas fa-edit fa-2x mb-2"></i><br>
                            <strong>Manage Grades</strong>
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="manage_student_subjects.php" class="btn btn-outline-success w-100 h-100 py-3">
                            <i class="fas fa-book fa-2x mb-2"></i><br>
                            <strong>Manage Student Subjects</strong>
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="print_courses.php" class="btn btn-outline-info w-100 h-100 py-3">
                            <i class="fas fa-print fa-2x mb-2"></i><br>
                            <strong>Print Courses</strong>
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="academic_reports.php" class="btn btn-outline-warning w-100 h-100 py-3">
                            <i class="fas fa-chart-line fa-2x mb-2"></i><br>
                            <strong>Academic Reports</strong>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include Chart.js from CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<?php renderPageEnd(); ?>