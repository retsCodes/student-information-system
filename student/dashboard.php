<?php
require_once '../init.php';
require_once '../theme.php';
require_once '../layout.php';

requireRole('student');

$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];

// Get student information
$stmt = $pdo->prepare("SELECT si.*, u.email as user_email 
                       FROM students_info si 
                       JOIN users u ON si.user_id = u.user_id 
                       WHERE si.user_id = ?");
$stmt->execute([$user_id]);
$student_info = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT 
                        COALESCE(COUNT(*), 0) as total_payments,
                        COALESCE(SUM(CASE WHEN payment_status IN ('unpaid', 'partial') THEN 1 ELSE 0 END), 0) as unpaid_count,
                        COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END), 0) as paid_count,
                        COALESCE(SUM(CASE WHEN payment_status = 'partial' THEN 1 ELSE 0 END), 0) as partial_count,
                        COALESCE(SUM(CASE 
                            WHEN payment_status = 'unpaid' THEN remaining_balance
                            WHEN payment_status = 'partial' THEN remaining_balance 
                            ELSE 0 
                        END), 0) as total_due,
                        COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN amount ELSE 0 END), 0) as total_paid
                       FROM payments 
                       WHERE student_id = ?");
$stmt->execute([$user_id]);
$payment_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get recent payments
$stmt = $pdo->prepare("SELECT p.*, u.name as issued_by_name 
                       FROM payments p 
                       JOIN users u ON p.issued_by = u.user_id 
                       WHERE p.student_id = ? 
                       ORDER BY p.issued_date DESC 
                       LIMIT 5");
$stmt->execute([$user_id]);
$recent_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get current subjects (from student_sections)
$stmt = $pdo->prepare("
    SELECT DISTINCT s.subject_code, s.subject_name, s.units, sec.section_code
    FROM student_sections ss
    JOIN sections sec ON ss.section_id = sec.id
    JOIN subject_sections subsec ON sec.id = subsec.section_id
    JOIN subjects s ON subsec.subject_id = s.id
    WHERE ss.student_id = ?
    LIMIT 5
");
$stmt->execute([$user_id]);
$current_subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get academic progress summary
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_subjects,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count,
        ROUND(AVG(CASE WHEN grade IS NOT NULL AND grade <= 3.0 THEN grade END), 2) as avg_grade
    FROM student_course_completion
    WHERE student_id = ?
");
$stmt->execute([$user_id]);
$academic_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get recent activities related to this student
$stmt = $pdo->prepare("SELECT al.*, u.name 
                       FROM activity_logs al 
                       JOIN users u ON al.user_id = u.user_id 
                       WHERE al.description LIKE ? OR al.user_id = ?
                       ORDER BY al.created_at DESC 
                       LIMIT 5");
$stmt->execute(["%{$user_id}%", $user_id]);
$recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

renderPageStart('Student Dashboard', 'student', 'dashboard.php');
?>

<!-- Welcome Section -->
<div class="row mb-4">
    <div class="col-12">
        <div class="alert alert-info">
            <h4 class="alert-heading">
                <i class="fas fa-graduation-cap"></i> Welcome back, <?php echo htmlspecialchars($student_info['name']); ?>!
            </h4>
            <p class="mb-0">
                <strong>Student ID:</strong> <?php echo htmlspecialchars($user_id); ?> | 
                <strong>Program:</strong> <?php echo htmlspecialchars($student_info['program'] ?? 'Not Set'); ?> | 
                <strong>Year Level:</strong> <?php echo $student_info['year_level'] ?? 'Not Set'; ?> |
                <strong>Status:</strong> <span class="badge bg-success"><?php echo ucfirst($student_info['enrollment_status']); ?></span>
            </p>
        </div>
    </div>
</div>

<!-- Statistics Cards - Financial -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <?php echo renderStatsCard('Total Payments', $payment_stats['total_payments'] ?? 0, 'fas fa-file-invoice-dollar', 'primary'); ?>
    </div>
    <div class="col-md-3 mb-3">
        <?php echo renderStatsCard('Due Payments', $payment_stats['unpaid_count'] ?? 0, 'fas fa-exclamation-triangle', 'warning'); ?>
    </div>
    <div class="col-md-3 mb-3">
        <?php echo renderStatsCard('Total Due', '₱' . number_format($payment_stats['total_due'] ?? 0, 2), 'fas fa-money-bill-wave', 'danger'); ?>
    </div>
    <div class="col-md-3 mb-3">
        <?php echo renderStatsCard('Paid Amount', '₱' . number_format($payment_stats['total_paid'] ?? 0, 2), 'fas fa-check-circle', 'success'); ?>
    </div>
</div>



<!-- Academic Progress Stats -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Academic Progress Overview</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3">
                        <div class="h2 text-primary"><?php echo $academic_stats['total_subjects'] ?? 0; ?></div>
                        <small>Total Subjects</small>
                    </div>
                    <div class="col-md-3">
                        <div class="h2 text-success"><?php echo $academic_stats['completed_count'] ?? 0; ?></div>
                        <small>Completed</small>
                    </div>
                    <div class="col-md-3">
                        <div class="h2 text-warning"><?php echo $academic_stats['in_progress_count'] ?? 0; ?></div>
                        <small>In Progress</small>
                    </div>
                    <div class="col-md-3">
                        <div class="h2 text-info"><?php echo $academic_stats['avg_grade'] ?? '—'; ?></div>
                        <small>Average Grade</small>
                    </div>
                </div>
                <div class="text-center mt-3">
                    <a href="academic_progress.php" class="btn btn-outline-primary">
                        <i class="fas fa-graduation-cap"></i> View Full Academic Progress
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- Important Notices -->
<?php if (($payment_stats['total_due'] ?? 0) > 0): ?>
<div class="row">
    <div class="col-12">
        <div class="alert alert-warning">
            <h5 class="alert-heading">
                <i class="fas fa-exclamation-triangle"></i> Payment Reminder
            </h5>
            <p>You have <strong><?php echo $payment_stats['unpaid_count']; ?></strong> payment(s) with outstanding balance 
               totaling <strong>₱<?php echo number_format($payment_stats['total_due'], 2); ?></strong>.</p>
            <hr>
            <p class="mb-0">
                <a href="payments.php" class="btn btn-warning">
                    <i class="fas fa-eye"></i> View Due Payments
                </a>
            </p>
        </div>
    </div>
</div>
<?php endif; ?>


<div class="row">
    <!-- Recent Payments -->
    <div class="col-md-6 mb-4">
        <?php
        $payments_content = '';
        if (empty($recent_payments)) {
            $payments_content = '<p class="text-muted">No payments found.</p>';
        } else {
            $payments_content = '<div class="list-group list-group-flush">';
            foreach($recent_payments as $payment) {
                $status_class = match($payment['payment_status']) {
                    'paid' => 'success',
                    'unpaid' => 'danger',
                    'partial' => 'warning',
                    default => 'secondary'
                };
                
                $payments_content .= '
                <div class="list-group-item border-0 px-0">
                    <div class="d-flex w-100 justify-content-between">
                        <h6 class="mb-1">₱' . number_format($payment['amount'], 2) . '</h6>
                        <small>' . date('M j, Y', strtotime($payment['issued_date'])) . '</small>
                    </div>
                    <p class="mb-1">' . htmlspecialchars($payment['description']) . '</p>
                    <div class="d-flex justify-content-between">
                        <small class="text-muted">Permit: ' . $payment['permit_number'] . '</small>
                        <span class="badge bg-' . $status_class . '">' . ucfirst($payment['payment_status']) . '</span>
                    </div>
                </div>';
            }
            $payments_content .= '</div>';
        }
        
        $payments_footer = '<a href="payments.php" class="btn btn-primary">View All Payments</a>';
        echo renderCard('Recent Payments', $payments_content, $payments_footer);
        ?>
    </div>
    
    <!-- Current Subjects -->
    <div class="col-md-6 mb-4">
        <?php
        $subjects_content = '';
        if (empty($current_subjects)) {
            $subjects_content = '<p class="text-muted">No subjects currently enrolled.</p>';
        } else {
            $subjects_content = '<div class="list-group list-group-flush">';
            foreach($current_subjects as $subject) {
                $subjects_content .= '
                <div class="list-group-item border-0 px-0">
                    <div class="d-flex w-100 justify-content-between">
                        <h6 class="mb-1">' . htmlspecialchars($subject['subject_code']) . '</h6>
                        <small>' . $subject['units'] . ' units</small>
                    </div>
                    <p class="mb-1">' . htmlspecialchars($subject['subject_name']) . '</p>
                    <small class="text-muted">Section: ' . htmlspecialchars($subject['section_code']) . '</small>
                </div>';
            }
            $subjects_content .= '</div>';
        }
        
        $subjects_footer = '<a href="academic_progress.php" class="btn btn-primary">View Full Study Load</a>';
        echo renderCard('Current Subjects', $subjects_content, $subjects_footer);
        ?>
    </div>
</div>

<div class="row">
    <!-- Recent Activities -->
    <div class="col-md-6 mb-4">
        <?php
        $activities_content = '';
        if (empty($recent_activities)) {
            $activities_content = '<p class="text-muted">No recent activities.</p>';
        } else {
            $activities_content = '<div class="list-group list-group-flush">';
            foreach($recent_activities as $activity) {
                $time_ago = date('M j, g:i A', strtotime($activity['created_at']));
                $activities_content .= '
                <div class="list-group-item border-0 px-0">
                    <div class="d-flex w-100 justify-content-between">
                        <h6 class="mb-1">' . htmlspecialchars($activity['action']) . '</h6>
                        <small>' . $time_ago . '</small>
                    </div>
                    <p class="mb-1">' . htmlspecialchars($activity['description']) . '</p>
                    <small class="text-muted">By: ' . htmlspecialchars($activity['name']) . '</small>
                </div>';
            }
            $activities_content .= '</div>';
        }
        
        echo renderCard('Recent Activities', $activities_content);
        ?>
    </div>
    
    <!-- Quick Actions -->
    <div class="col-md-6 mb-4">
        <?php
        $actions_content = '
        <div class="d-grid gap-2">
            <a href="payments.php" class="btn btn-outline-primary">
                <i class="fas fa-money-bill-wave"></i> View My Payments
            </a>
            <a href="academic_progress.php" class="btn btn-outline-info">
                <i class="fas fa-graduation-cap"></i> Academic Progress
            </a>
            <a href="schedule.php" class="btn btn-outline-secondary">
                <i class="fas fa-calendar-alt"></i> Class Schedule
            </a>
            <a href="profile.php" class="btn btn-outline-success">
                <i class="fas fa-user"></i> Update Profile
            </a>
        </div>';
        
        echo renderCard('Quick Actions', $actions_content);
        ?>
    </div>
</div>

<?php renderPageEnd(); ?>