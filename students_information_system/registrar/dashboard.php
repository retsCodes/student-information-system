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

// Active students
$stmt = $pdo->query("SELECT COUNT(*) as total FROM students_info WHERE enrollment_status = 'enrolled'");
$stats['active_students'] = $stmt->fetch()['total'];

// Total courses
$stmt = $pdo->query("SELECT COUNT(*) as total FROM courses WHERE status = 'active'");
$stats['total_courses'] = $stmt->fetch()['total'];

// Total sections
$stmt = $pdo->query("SELECT COUNT(*) as total FROM sections WHERE status = 'active'");
$stats['total_sections'] = $stmt->fetch()['total'];

// Total subjects
$stmt = $pdo->query("SELECT COUNT(*) as total FROM subjects");
$stats['total_subjects'] = $stmt->fetch()['total'];

// Students by program
$stmt = $pdo->query("
    SELECT si.program, COUNT(*) as count 
    FROM students_info si 
    WHERE si.enrollment_status = 'enrolled' 
    GROUP BY si.program
");
$students_by_program = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Students by year level
$stmt = $pdo->query("
    SELECT year_level, COUNT(*) as count 
    FROM students_info 
    WHERE enrollment_status = 'enrolled' 
    GROUP BY year_level 
    ORDER BY year_level
");
$students_by_year = $stmt->fetchAll(PDO::FETCH_ASSOC);

// List of enrollments
$stmt = $pdo->query("
    SELECT u.user_id, u.name, si.program, si.year_level, si.enrollment_date
    FROM users u
    JOIN students_info si ON u.user_id = si.user_id
    WHERE u.role = 'student' AND si.enrollment_status = 'enrolled'
    ORDER BY si.enrollment_date DESC
    LIMIT 10
");
$recent_enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Upcoming/current semester info
$stmt = $pdo->query("
    SELECT * FROM semesters 
    WHERE is_current = 1 
    LIMIT 1
");
$current_semester = $stmt->fetch(PDO::FETCH_ASSOC);

renderPageStart('Registrar Dashboard', 'registrar', 'dashboard.php');
?>

<style>
.stats-card {
    transition: transform 0.3s;
    border-radius: 12px;
    overflow: hidden;
}
.stats-card:hover {
    transform: translateY(-5px);
}
.stats-card .card-body {
    padding: 1.25rem;
}
.stats-number {
    font-size: 2rem;
    font-weight: bold;
    margin-bottom: 0;
}
.stats-label {
    color: #6c757d;
    font-size: 0.875rem;
}
</style>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-chalkboard-user me-2"></i>Registrar Dashboard</h2>
        <?php if ($current_semester): ?>
            <div class="alert alert-info mb-0 py-2">
                <i class="fas fa-calendar-alt me-2"></i>
                <strong><?php echo $current_semester['semester']; ?> Semester</strong> 
                (Active: <?php echo date('M j, Y', strtotime($current_semester['start_date'])); ?> - 
                <?php echo date('M j, Y', strtotime($current_semester['end_date'])); ?>)
            </div>
        <?php endif; ?>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card stats-card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stats-number"><?php echo number_format($stats['total_students']); ?></div>
                            <div class="stats-label">Total Students</div>
                        </div>
                        <div class="rounded-circle bg-primary bg-opacity-10 p-3">
                            <i class="fas fa-users fa-2x text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card stats-card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stats-number"><?php echo number_format($stats['active_students']); ?></div>
                            <div class="stats-label">Currently Enrolled</div>
                        </div>
                        <div class="rounded-circle bg-success bg-opacity-10 p-3">
                            <i class="fas fa-user-graduate fa-2x text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card stats-card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stats-number"><?php echo number_format($stats['total_courses']); ?></div>
                            <div class="stats-label">Active Courses</div>
                        </div>
                        <div class="rounded-circle bg-info bg-opacity-10 p-3">
                            <i class="fas fa-graduation-cap fa-2x text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card stats-card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stats-number"><?php echo number_format($stats['total_sections']); ?></div>
                            <div class="stats-label">Active Sections</div>
                        </div>
                        <div class="rounded-circle bg-warning bg-opacity-10 p-3">
                            <i class="fas fa-layer-group fa-2x text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Students by Program -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0"><i class="fas fa-chart-pie me-2"></i>Students by Program</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="position: relative; height: 250px;">
                        <canvas id="programChart"></canvas>
                    </div>
                    <div class="mt-3">
                        <table class="table table-sm">
                            <tbody>
                                <?php foreach ($students_by_program as $program): 
                                    $program_name = str_replace('Bachelor of Science in ', 'BS ', $program['program']);
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($program_name); ?></td>
                                    <td class="text-end"><strong><?php echo $program['count']; ?></strong> students</td>
                                    <td class="text-end text-muted small"><?php echo round(($program['count'] / max($stats['active_students'], 1)) * 100); ?>%</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Students by Year Level -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0"><i class="fas fa-chart-bar me-2"></i>Students by Year Level</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="position: relative; height: 250px;">
                        <canvas id="yearChart"></canvas>
                    </div>
                    <div class="mt-3">
                        <?php 
                        $year_labels = ['1st Year', '2nd Year', '3rd Year', '4th Year', '5th+ Year'];
                        $year_data = [];
                        for ($i = 1; $i <= 5; $i++) {
                            $found = false;
                            foreach ($students_by_year as $year) {
                                if ($year['year_level'] == $i) {
                                    $year_data[$i] = $year['count'];
                                    $found = true;
                                    break;
                                }
                            }
                            if (!$found) $year_data[$i] = 0;
                        }
                        ?>
                        <div class="d-flex justify-content-around">
                            <?php foreach ($year_data as $year => $count): ?>
                            <div class="text-center">
                                <h4 class="mb-0"><?php echo $count; ?></h4>
                                <small class="text-muted">Year <?php echo $year; ?></small>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Quick Actions -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <a href="manage_students.php" class="btn btn-outline-primary d-flex align-items-center justify-content-between w-100">
                                <span><i class="fas fa-user-graduate me-2"></i>Manage Students</span>
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="manage_courses.php" class="btn btn-outline-success d-flex align-items-center justify-content-between w-100">
                                <span><i class="fas fa-graduation-cap me-2"></i>Manage Courses</span>
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="manage_subjects.php" class="btn btn-outline-info d-flex align-items-center justify-content-between w-100">
                                <span><i class="fas fa-book me-2"></i>Manage Subjects</span>
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="manage_sections.php" class="btn btn-outline-warning d-flex align-items-center justify-content-between w-100">
                                <span><i class="fas fa-layer-group me-2"></i>Manage Sections</span>
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="enrollment.php" class="btn btn-outline-danger d-flex align-items-center justify-content-between w-100">
                                <span><i class="fas fa-pen-alt me-2"></i>Process Enrollment</span>
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="manage_grades.php" class="btn btn-outline-secondary d-flex align-items-center justify-content-between w-100">
                                <span><i class="fas fa-chart-line me-2"></i>Manage Grades</span>
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- List Enrollments -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class="fas fa-clock me-2"></i>List of Enrollments</h5>
                    <a href="manage_students.php" class="btn btn-sm btn-link">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>Program</th>
                                    <th>Year</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_enrollments as $student): ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars($student['user_id']); ?></code></td>
                                    <td><?php echo htmlspecialchars($student['name']); ?></td>
                                    <td><?php echo htmlspecialchars($student['program']); ?></td>
                                    <td><?php echo $student['year_level']; ?></td>
                                    <td><?php echo date('M j, Y', strtotime($student['enrollment_date'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($recent_enrollments)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No List of enrollments</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Program Chart
    const programCtx = document.getElementById('programChart').getContext('2d');
    const programLabels = <?php echo json_encode(array_map(function($p) {
        return str_replace('Bachelor of Science in ', 'BS ', $p['program']);
    }, $students_by_program)); ?>;
    const programCounts = <?php echo json_encode(array_column($students_by_program, 'count')); ?>;
    
    new Chart(programCtx, {
        type: 'doughnut',
        data: {
            labels: programLabels,
            datasets: [{
                data: programCounts,
                backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 12, font: { size: 11 } }
                }
            }
        }
    });

    // Year Level Chart
    const yearCtx = document.getElementById('yearChart').getContext('2d');
    new Chart(yearCtx, {
        type: 'bar',
        data: {
            labels: ['Year 1', 'Year 2', 'Year 3', 'Year 4', 'Year 5+'],
            datasets: [{
                data: <?php echo json_encode(array_values($year_data)); ?>,
                backgroundColor: '#4e73df',
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: (ctx) => `${ctx.raw} students` } }
            },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1 }, title: { display: true, text: 'Number of Students' } }
            }
        }
    });
});
</script>

<?php renderPageEnd(); ?>