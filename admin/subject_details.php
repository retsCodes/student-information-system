<?php
require_once '../init.php';
require_once '../theme.php';
require_once '../layout.php';

requireRole('admin');

$pdo = getDBConnection();
$subject_id = intval($_GET['id'] ?? 0);

if ($subject_id <= 0) {
    redirect('manage_subjects.php');
}

// Get subject information
$stmt = $pdo->prepare("SELECT * FROM subjects WHERE id = ?");
$stmt->execute([$subject_id]);
$subject = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$subject) {
    redirect('manage_subjects.php');
}

// Get sections that contain this subject
$sections_data = json_decode($subject['sections'] ?? '[]', true);
$sections = [];
if (!empty($sections_data)) {
    $placeholders = str_repeat('?,', count($sections_data) - 1) . '?';
    $stmt = $pdo->prepare("SELECT * FROM sections WHERE section_code IN ($placeholders) ORDER BY year_level, section_code");
    $stmt->execute($sections_data);
    $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get students enrolled in this subject (through sections)
$students = [];
foreach($sections as $section) {
    $student_ids = json_decode($section['user_id'] ?? '[]', true);
    if (!empty($student_ids)) {
        $placeholders = str_repeat('?,', count($student_ids) - 1) . '?';
        $stmt = $pdo->prepare("SELECT si.*, u.user_status 
                               FROM students_info si 
                               JOIN users u ON si.user_id = u.user_id 
                               WHERE si.user_id IN ($placeholders) 
                               ORDER BY si.name");
        $stmt->execute($student_ids);
        $section_students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach($section_students as $student) {
            $student['section_code'] = $section['section_code'];
            $students[] = $student;
        }
    }
}

// Get irregular students (exception and addition)
$exceptions = json_decode($subject['exception'] ?? '[]', true);
$additions = json_decode($subject['addition'] ?? '[]', true);

$irregular_students = [];
$all_irregular = array_merge($exceptions, $additions);
if (!empty($all_irregular)) {
    $placeholders = str_repeat('?,', count($all_irregular) - 1) . '?';
    $stmt = $pdo->prepare("SELECT si.*, u.user_status 
                           FROM students_info si 
                           JOIN users u ON si.user_id = u.user_id 
                           WHERE si.user_id IN ($placeholders) 
                           ORDER BY si.name");
    $stmt->execute($all_irregular);
    $irregular_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($irregular_data as $student) {
        if (in_array($student['user_id'], $exceptions)) {
            $student['irregular_type'] = 'Exception (Finished)';
        } else {
            $student['irregular_type'] = 'Addition (Taking)';
        }
        $irregular_students[] = $student;
    }
}

// Get payment statistics for this subject
$stmt = $pdo->prepare("SELECT 
                        COUNT(*) as total_payments,
                        SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_count,
                        SUM(CASE WHEN payment_status = 'unpaid' THEN 1 ELSE 0 END) as unpaid_count,
                        SUM(CASE WHEN payment_status = 'partial' THEN 1 ELSE 0 END) as partial_count,
                        SUM(amount) as total_amount
                       FROM payments 
                       WHERE description LIKE ?");
$stmt->execute(["%{$subject['subject_code']}%"]);
$payment_stats = $stmt->fetch(PDO::FETCH_ASSOC);

renderPageStart('Subject Details: ' . $subject['subject_code'], 'admin', 'manage_subjects.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2><?php echo htmlspecialchars($subject['subject_code']); ?></h2>
        <p class="text-muted mb-0"><?php echo htmlspecialchars($subject['subject_name']); ?></p>
    </div>
    <a href="manage_subjects.php" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left"></i> Back to Subjects
    </a>
</div>

<!-- Subject Information -->
<div class="row mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-info-circle"></i> Subject Information
                </h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <th width="150">Subject Code:</th>
                        <td><code><?php echo htmlspecialchars($subject['subject_code']); ?></code></td>
                    </tr>
                    <tr>
                        <th>Subject Name:</th>
                        <td><strong><?php echo htmlspecialchars($subject['subject_name']); ?></strong></td>
                    </tr>
                    <tr>
                        <th>Units:</th>
                        <td><span class="badge bg-primary"><?php echo $subject['units']; ?> units</span></td>
                    </tr>
                    <tr>
                        <th>Description:</th>
                        <td><?php echo $subject['description'] ? htmlspecialchars($subject['description']) : '<em class="text-muted">No description provided</em>'; ?></td>
                    </tr>
                    <tr>
                        <th>Active Sections:</th>
                        <td><span class="badge bg-success"><?php echo count($sections); ?> sections</span></td>
                    </tr>
                    <tr>
                        <th>Total Students:</th>
                        <td><span class="badge bg-info"><?php echo count($students) + count($irregular_students); ?> students</span></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-pie"></i> Payment Statistics
                </h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6 mb-3">
                        <h4 class="text-success"><?php echo $payment_stats['paid_count']; ?></h4>
                        <small>Paid</small>
                    </div>
                    <div class="col-6 mb-3">
                        <h4 class="text-danger"><?php echo $payment_stats['unpaid_count']; ?></h4>
                        <small>Unpaid</small>
                    </div>
                    <div class="col-6">
                        <h4 class="text-warning"><?php echo $payment_stats['partial_count']; ?></h4>
                        <small>Partial</small>
                    </div>
                    <div class="col-6">
                        <h4 class="text-primary"><?php echo $payment_stats['total_payments']; ?></h4>
                        <small>Total</small>
                    </div>
                </div>
                <hr>
                <div class="text-center">
                    <h5>₱<?php echo number_format($payment_stats['total_amount'], 2); ?></h5>
                    <small class="text-muted">Total Amount</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Sections -->
<?php if (!empty($sections)): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-layer-group"></i> Sections Using This Subject
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Section Code</th>
                                <th>Program</th>
                                <th>Year Level</th>
                                <th>Students</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($sections as $section): ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($section['section_code']); ?></code></td>
                                <td><?php echo htmlspecialchars($section['program']); ?></td>
                                <td>Year <?php echo $section['year_level']; ?></td>
                                <td>
                                    <?php 
                                    $section_students = json_decode($section['user_id'] ?? '[]', true);
                                    echo count($section_students); 
                                    ?> students
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $section['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($section['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Regular Students -->
<?php if (!empty($students)): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-users"></i> Regular Students Enrolled
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Program</th>
                                <th>Year Level</th>
                                <th>Section</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($students as $student): ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($student['user_id']); ?></code></td>
                                <td><?php echo htmlspecialchars($student['name']); ?></td>
                                <td><?php echo htmlspecialchars($student['program'] ?? 'Not Set'); ?></td>
                                <td>Year <?php echo $student['year_level'] ?? 'Not Set'; ?></td>
                                <td><code><?php echo htmlspecialchars($student['section_code']); ?></code></td>
                                <td>
                                    <span class="badge bg-<?php echo $student['user_status'] === 'active' ? 'success' : 'danger'; ?>">
                                        <?php echo ucfirst($student['user_status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Irregular Students -->
<?php if (!empty($irregular_students)): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-user-graduate"></i> Irregular Students
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Program</th>
                                <th>Year Level</th>
                                <th>Type</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($irregular_students as $student): ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($student['user_id']); ?></code></td>
                                <td><?php echo htmlspecialchars($student['name']); ?></td>
                                <td><?php echo htmlspecialchars($student['program'] ?? 'Not Set'); ?></td>
                                <td>Year <?php echo $student['year_level'] ?? 'Not Set'; ?></td>
                                <td>
                                    <span class="badge bg-<?php echo strpos($student['irregular_type'], 'Exception') !== false ? 'warning' : 'info'; ?>">
                                        <?php echo $student['irregular_type']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $student['user_status'] === 'active' ? 'success' : 'danger'; ?>">
                                        <?php echo ucfirst($student['user_status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- No Data Messages -->
<?php if (empty($sections) && empty($students) && empty($irregular_students)): ?>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                <h5>No Students Enrolled</h5>
                <p class="text-muted">This subject is not currently assigned to any sections or students.</p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php renderPageEnd(); ?>
