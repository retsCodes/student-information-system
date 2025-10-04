<?php
require_once '../init.php';
require_once '../theme.php';
require_once '../layout.php';

requireRole('cashier');

$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];

// Add quick action links for new management features
$quick_actions = [
    [
        'title' => 'Manage Subjects',
        'icon' => 'fa-book',
        'description' => 'Assign subjects to sections and students',
        'url' => 'manage_subjects.php',
        'color' => 'primary'
    ],
    [
        'title' => 'Manage Sections',
        'icon' => 'fa-users',
        'description' => 'Assign students to sections',
        'url' => 'manage_sections.php',
        'color' => 'success'
    ],
    [
        'title' => 'Process Payments',
        'icon' => 'fa-money-bill-wave',
        'description' => 'Record student payments',
        'url' => 'payments.php',
        'color' => 'info'
    ],
    [
        'title' => 'Settings',
        'icon' => 'fa-cog',
        'description' => 'Manage your account settings',
        'url' => 'settings.php',
        'color' => 'secondary'
    ]
];

// Get statistics
$stats = [];

// Payments processed today
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM payments WHERE issued_by = ? AND DATE(issued_date) = CURDATE()");
$stmt->execute([$user_id]);
$stats['payments_today'] = $stmt->fetch()['total'];

// Total amount processed today
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE issued_by = ? AND DATE(issued_date) = CURDATE()");
$stmt->execute([$user_id]);
$stats['amount_today'] = $stmt->fetch()['total'];

// Total unpaid payments
$stmt = $pdo->query("SELECT COUNT(*) as total FROM payments WHERE payment_status = 'unpaid'");
$stats['unpaid_payments'] = $stmt->fetch()['total'];

// Recent students who paid
$stmt = $pdo->prepare("SELECT p.*, si.name as student_name, si.program, si.year_level
                       FROM payments p
                       JOIN students_info si ON p.student_id = si.user_id
                       WHERE p.issued_by = ? AND p.payment_status = 'paid'
                       ORDER BY p.updated_at DESC
                       LIMIT 10");
$stmt->execute([$user_id]);
$recent_paid_students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent payments processed by this cashier
$stmt = $pdo->prepare("SELECT p.*, si.name as student_name, si.program, si.year_level
                       FROM payments p
                       JOIN students_info si ON p.student_id = si.user_id
                       WHERE p.issued_by = ?
                       ORDER BY p.issued_date DESC, p.updated_at DESC
                       LIMIT 15");
$stmt->execute([$user_id]);
$recent_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Overdue payments (for reference)
$stmt = $pdo->query("SELECT COUNT(*) as total 
                     FROM payments p
                     JOIN students_info si ON p.student_id = si.user_id
                     WHERE p.payment_status = 'unpaid' 
                     AND p.issued_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
$stats['overdue_payments'] = $stmt->fetch()['total'];

renderPageStart('Cashier Dashboard', 'cashier', 'dashboard.php');
?>

<!-- Quick Action Cards -->
<div class="container-fluid px-4 mb-4">
    <div class="row">
        <?php foreach ($quick_actions as $action): ?>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-<?php echo $action['color']; ?> shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-<?php echo $action['color']; ?> text-uppercase mb-1">
                                    <?php echo htmlspecialchars($action['title']); ?>
                                </div>
                                <div class="h6 mb-0 text-gray-800">
                                    <?php echo htmlspecialchars($action['description']); ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas <?php echo $action['icon']; ?> fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                    <a href="<?php echo $action['url']; ?>" class="stretched-link"></a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="container-fluid px-4 mb-4">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-tools"></i> Quick Links</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 mb-2">
                    <a href="student_search.php" class="btn btn-outline-primary w-100">
                        <i class="fas fa-search"></i> Student Search
                    </a>
                </div>
                <div class="col-md-3 mb-2">
                    <a href="manage_subjects.php" class="btn btn-outline-warning w-100">
                        <i class="fas fa-book"></i> Manage Subjects
                    </a>
                </div>
                <div class="col-md-3 mb-2">
                    <a href="manage_sections.php" class="btn btn-outline-success w-100">
                        <i class="fas fa-users"></i> Manage Sections
                    </a>
                </div>
                <div class="col-md-3 mb-2">
                    <a href="../settings.php" class="btn btn-outline-secondary w-100">
                        <i class="fas fa-cog"></i> Settings
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-12">
        <div class="alert alert-info">
            <h4 class="alert-heading">
                <i class="fas fa-cash-register"></i> Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>!
            </h4>
            <p class="mb-0">Manage student payments efficiently and keep track of your daily transactions.</p>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <?php echo renderStatsCard('Payments Today', $stats['payments_today'], 'fas fa-file-invoice-dollar', 'primary'); ?>
    </div>
    <div class="col-md-3 mb-3">
        <?php echo renderStatsCard('Amount Today', '₱' . number_format($stats['amount_today'], 2), 'fas fa-money-bill-wave', 'success'); ?>
    </div>
    <div class="col-md-3 mb-3">
        <?php echo renderStatsCard('Total Unpaid', $stats['unpaid_payments'], 'fas fa-exclamation-triangle', 'warning'); ?>
    </div>
    <div class="col-md-3 mb-3">
        <?php echo renderStatsCard('Overdue (30+ days)', $stats['overdue_payments'], 'fas fa-clock', 'danger'); ?>
    </div>
</div>

<!-- Quick Actions -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-bolt"></i> Quick Actions
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="d-grid">
                            <a href="payments.php" class="btn btn-primary btn-lg">
                                <i class="fas fa-plus"></i> Process Payment
                            </a>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="d-grid">
                            <button class="btn btn-outline-primary btn-lg" data-bs-toggle="modal" data-bs-target="#searchStudentModal">
                                <i class="fas fa-search"></i> Search Student
                            </button>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="d-grid">
                            <a href="audit_log.php" class="btn btn-outline-secondary btn-lg">
                                <i class="fas fa-history"></i> View Audit Log
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Recent Payments -->
    <div class="col-md-8 mb-4">
        <?php
        $payments_content = '';
        if (empty($recent_payments)) {
            $payments_content = '<p class="text-muted">No payments processed yet.</p>';
        } else {
            $payments_content = '<div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>';
            
            foreach($recent_payments as $payment) {
                $status_class = match($payment['payment_status']) {
                    'paid' => 'success',
                    'unpaid' => 'danger',
                    'partial' => 'warning',
                    default => 'secondary'
                };
                
                $payments_content .= '
                <tr>
                    <td>
                        <strong>' . htmlspecialchars($payment['student_name']) . '</strong><br>
                        <small class="text-muted">' . htmlspecialchars($payment['student_id']) . '</small>
                    </td>
                    <td><strong>₱' . number_format($payment['amount'], 2) . '</strong></td>
                    <td><span class="badge bg-' . $status_class . '">' . ucfirst($payment['payment_status']) . '</span></td>
                    <td>' . date('M j', strtotime($payment['issued_date'])) . '</td>
                    <td>' . htmlspecialchars(substr($payment['description'], 0, 30)) . '...</td>
                </tr>';
            }
            
            $payments_content .= '</tbody></table></div>';
        }
        
        $payments_footer = '<a href="payments.php" class="btn btn-primary">Manage All Payments</a>';
        echo renderCard('Recent Payments', $payments_content, $payments_footer);
        ?>
    </div>
    
    <!-- Recent Paid Students -->
    <div class="col-md-4 mb-4">
        <?php
        $students_content = '';
        if (empty($recent_paid_students)) {
            $students_content = '<p class="text-muted">No recent payments.</p>';
        } else {
            $students_content = '<div class="list-group list-group-flush">';
            foreach($recent_paid_students as $payment) {
                $time_ago = date('M j, g:i A', strtotime($payment['updated_at']));
                $students_content .= '
                <div class="list-group-item border-0 px-0">
                    <div class="d-flex w-100 justify-content-between">
                        <h6 class="mb-1">' . htmlspecialchars($payment['student_name']) . '</h6>
                        <small>' . $time_ago . '</small>
                    </div>
                    <p class="mb-1">₱' . number_format($payment['amount'], 2) . '</p>
                    <small class="text-muted">' . htmlspecialchars($payment['student_id']) . '</small>
                </div>';
            }
            $students_content .= '</div>';
        }
        
        echo renderCard('Recently Paid Students', $students_content);
        ?>
    </div>
</div>

<!-- Search Student Modal -->
<div class="modal fade" id="searchStudentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Search Student</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="studentSearchForm">
                    <div class="mb-3">
                        <label for="searchInput" class="form-label">Search by Student ID, Name, or Email</label>
                        <input type="text" class="form-control" id="searchInput" placeholder="Enter search term...">
                    </div>
                    <button type="submit" class="btn btn-primary">Search</button>
                </form>
                
                <div id="searchResults" class="mt-4" style="display: none;">
                    <h6>Search Results:</h6>
                    <div id="resultsContainer"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('studentSearchForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const searchTerm = document.getElementById('searchInput').value.trim();
    
    if (searchTerm.length < 2) {
        alert('Please enter at least 2 characters to search.');
        return;
    }
    
    // Here you would normally make an AJAX call to search for students
    // For now, we'll show a placeholder message
    const resultsContainer = document.getElementById('resultsContainer');
    const searchResults = document.getElementById('searchResults');
    
    resultsContainer.innerHTML = '<div class="alert alert-info">Search functionality will be implemented in the payments page.</div>';
    searchResults.style.display = 'block';
});
</script>

<?php renderPageEnd(); ?>
