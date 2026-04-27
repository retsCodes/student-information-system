<?php
require_once '../init.php';
require_once '../theme.php';
require_once '../layout.php';

requireRole('admin');

$pdo = getDBConnection();
$section_id = intval($_GET['id'] ?? 0);

if ($section_id <= 0) {
    redirect('manage_sections.php');
}

// Get section information
$stmt = $pdo->prepare("SELECT * FROM sections WHERE id = ?");
$stmt->execute([$section_id]);
$section = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$section) {
    redirect('manage_sections.php');
}

// Get students in this section
$student_ids = json_decode($section['user_id'] ?? '[]', true);
$students = [];
if (!empty($student_ids)) {
    $placeholders = str_repeat('?,', count($student_ids) - 1) . '?';
    $stmt = $pdo->prepare("SELECT si.*, u.user_status 
                           FROM students_info si 
                           JOIN users u ON si.user_id = u.user_id 
                           WHERE si.user_id IN ($placeholders) 
                           ORDER BY si.name");
    $stmt->execute($student_ids);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get subjects for this section
$stmt = $pdo->prepare("SELECT s.* FROM subjects s 
                       WHERE JSON_CONTAINS(s.sections, JSON_QUOTE(?))
                       ORDER BY s.subject_code");
$stmt->execute([$section['section_code']]);
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get payment statistics for students in this section
$payment_stats = ['total' => 0, 'paid' => 0, 'unpaid' => 0, 'partial' => 0];
if (!empty($student_ids)) {
    $placeholders = str_repeat('?,', count($student_ids) - 1) . '?';
    $stmt = $pdo->prepare("SELECT payment_status, COUNT(*) as count 
                           FROM payments 
                           WHERE student_id IN ($placeholders) 
                           GROUP BY payment_status");
    $stmt->execute($student_ids);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($results as $result) {
        $payment_stats[$result['payment_status']] = $result['count'];
        $payment_stats['total'] += $result['count'];
    }
}

renderPageStart('Section Details: ' . $section['section_code'], 'admin', 'manage_sections.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2><?php echo htmlspecialchars($section['section_code']); ?></h2>
        <p class="text-muted mb-0"><?php echo htmlspecialchars($section['program']); ?> - Year <?php echo $section['year_level']; ?></p>
    </div>
    <a href="manage_sections.php" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left"></i> Back to Sections
    </a>
</div>

<!-- Section Information -->
<div class="row mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-info-circle"></i> Section Information
                </h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <th width="150">Section Code:</th>
                        <td><code><?php echo htmlspecialchars($section['section_code']); ?></code></td>
                    </tr>
                    <tr>
                        <th>Program:</th>
                        <td><strong><?php echo htmlspecialchars($section['program']); ?></strong></td>
                    </tr>
                    <tr>
                        <th>Year Level:</th>
                        <td><span class="badge bg-info">Year <?php echo $section['year_level']; ?></span></td>
                    </tr>
                    <tr>
                        <th>Status:</th>
                        <td>
                            <span class="badge bg-<?php echo $section['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                <?php echo ucfirst($section['status']); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Total Students:</th>
                        <td><span class="badge bg-primary"><?php echo count($students); ?> students</span></td>
                    </tr>
                    <tr>
                        <th>Total Subjects:</th>
                        <td><span class="badge bg-success"><?php echo count($subjects); ?> subjects</span></td>
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
                        <h4 class="text-success"><?php echo $payment_stats['paid']; ?></h4>
                        <small>Paid</small>
                    </div>
                    <div class="col-6 mb-3">
                        <h4 class="text-danger"><?php echo $payment_stats['unpaid']; ?></h4>
                        <small>Unpaid</small>
                    </div>
                    <div class="col-6">
                        <h4 class="text-warning"><?php echo $payment_stats['partial']; ?></h4>
                        <small>Partial</small>
                    </div>
                    <div class="col-6">
                        <h4 class="text-primary"><?php echo $payment_stats['total']; ?></h4>
                        <small>Total</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Subjects -->
<?php if (!empty($subjects)): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-book"></i> Subjects in This Section
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Subject Code</th>
                                <th>Subject Name</th>
                                <th>Units</th>
                                <th>Description</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($subjects as $subject): ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($subject['subject_code']); ?></code></td>
                                <td><strong><?php echo htmlspecialchars($subject['subject_name']); ?></strong></td>
                                <td><span class="badge bg-primary"><?php echo $subject['units']; ?> units</span></td>
                                <td>
                                    <?php if ($subject['description']): ?>
                                        <?php echo htmlspecialchars(substr($subject['description'], 0, 50)); ?>
                                        <?php echo strlen($subject['description']) > 50 ? '...' : ''; ?>
                                    <?php else: ?>
                                        <span class="text-muted">No description</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="subject_details.php?id=<?php echo $subject['id']; ?>" class="btn btn-sm btn-outline-info">
                                        <i class="fas fa-eye"></i> View
                                    </a>
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

<!-- Students -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-users"></i> Students in This Section
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($students)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-user-graduate fa-3x text-muted mb-3"></i>
                        <h5>No students enrolled</h5>
                        <p class="text-muted">This section doesn't have any students yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Student Type</th>
                                    <th>Enrollment Status</th>
                                    <th>Account Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($students as $student): ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars($student['user_id']); ?></code></td>
                                    <td><strong><?php echo htmlspecialchars($student['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($student['email']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $student['student_type'] === 'regular' ? 'success' : 'warning'; ?>">
                                            <?php echo ucfirst($student['student_type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $student['enrollment_status'] === 'enrolled' ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst($student['enrollment_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $student['user_status'] === 'active' ? 'success' : 'danger'; ?>">
                                            <?php echo ucfirst($student['user_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="manage_users.php?view=<?php echo $student['user_id']; ?>" 
                                               class="btn btn-sm btn-outline-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="manage_payments.php?student=<?php echo $student['user_id']; ?>" 
                                               class="btn btn-sm btn-outline-success">
                                                <i class="fas fa-money-bill-wave"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php renderPageEnd(); ?>
