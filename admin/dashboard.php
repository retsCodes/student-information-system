<?php
require_once '../init.php';
require_once '../theme.php';
require_once '../layout.php';

requireRole('admin');

$pdo = getDBConnection();

// Get statistics
$stats = [];

// Total users
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
$stats['total_users'] = $stmt->fetch()['total'];

// Total students
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'student'");
$stats['total_students'] = $stmt->fetch()['total'];

// Total payments today
$stmt = $pdo->query("SELECT COUNT(*) as total FROM payments WHERE DATE(issued_date) = CURDATE()");
$stats['payments_today'] = $stmt->fetch()['total'];

// Total unpaid payments
$stmt = $pdo->query("SELECT COUNT(*) as total FROM payments WHERE payment_status = 'unpaid'");
$stats['unpaid_payments'] = $stmt->fetch()['total'];

// Recent users with last login
$stmt = $pdo->query("SELECT u.user_id, u.name, u.role, u.last_active, u.user_status 
                     FROM users u 
                     ORDER BY u.last_active DESC 
                     LIMIT 10");
$recent_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Payment statistics for chart (last 7 days)
$stmt = $pdo->query("SELECT DATE(issued_date) as date, COUNT(*) as count, SUM(amount) as total
                     FROM payments 
                     WHERE issued_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                     GROUP BY DATE(issued_date)
                     ORDER BY date");
$payment_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent activities
$stmt = $pdo->query("SELECT al.*, u.name 
                     FROM activity_logs al 
                     JOIN users u ON al.user_id = u.user_id 
                     ORDER BY al.created_at DESC 
                     LIMIT 10");
$recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

renderPageStart('Admin Dashboard', 'admin', 'dashboard.php');
?>

<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <?php echo renderStatsCard('Total Users', $stats['total_users'], 'fas fa-users', 'primary'); ?>
    </div>
    <div class="col-md-3 mb-3">
        <?php echo renderStatsCard('Students', $stats['total_students'], 'fas fa-user-graduate', 'success'); ?>
    </div>
    <div class="col-md-3 mb-3">
        <?php echo renderStatsCard('Payments Today', $stats['payments_today'], 'fas fa-money-bill-wave', 'info'); ?>
    </div>
    <div class="col-md-3 mb-3">
        <?php echo renderStatsCard('Unpaid Bills', $stats['unpaid_payments'], 'fas fa-exclamation-triangle', 'warning'); ?>
    </div>
</div>

<div class="row">
    <div class="col-md-8 mb-4">
        <?php
        $chart_content = '
        <canvas id="paymentsChart" width="400" height="200"></canvas>
        <script>
        const ctx = document.getElementById("paymentsChart").getContext("2d");
        const paymentsChart = new Chart(ctx, {
            type: "line",
            data: {
                labels: [' . implode(',', array_map(function($item) { 
                    return '"' . date('M j', strtotime($item['date'])) . '"'; 
                }, $payment_stats)) . '],
                datasets: [{
                    label: "Payments",
                    data: [' . implode(',', array_column($payment_stats, 'count')) . '],
                    borderColor: "rgb(75, 192, 192)",
                    backgroundColor: "rgba(75, 192, 192, 0.2)",
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: "Payment Trends (Last 7 Days)"
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        </script>';
        
        echo renderCard('Payment Statistics', $chart_content);
        ?>
    </div>
    
    <div class="col-md-4 mb-4">
        <?php
        $activities_content = '<div class="list-group list-group-flush">';
        foreach($recent_activities as $activity) {
            $time_ago = date('M j, g:i A', strtotime($activity['created_at']));
            $activities_content .= '
            <div class="list-group-item border-0 px-0">
                <div class="d-flex w-100 justify-content-between">
                    <h6 class="mb-1">' . htmlspecialchars($activity['action']) . '</h6>
                    <small>' . $time_ago . '</small>
                </div>
                <p class="mb-1"><strong>' . htmlspecialchars($activity['name']) . '</strong></p>
                <small class="text-muted">' . htmlspecialchars($activity['description']) . '</small>
            </div>';
        }
        $activities_content .= '</div>';
        
        $activities_footer = '<a href="logs.php" class="btn btn-sm btn-outline-primary">View All Logs</a>';
        
        echo renderCard('Recent Activities', $activities_content, $activities_footer);
        ?>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <?php
        $users_content = '
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Last Active</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach($recent_users as $user) {
            $status_badge = $user['user_status'] === 'active' ? 'success' : 'danger';
            $status_text = ucfirst($user['user_status']);
            $last_active = $user['last_active'] ? date('M j, Y g:i A', strtotime($user['last_active'])) : 'Never';
            
            $users_content .= '
            <tr>
                <td><code>' . htmlspecialchars($user['user_id']) . '</code></td>
                <td>' . htmlspecialchars($user['name']) . '</td>
                <td><span class="badge bg-info">' . ucfirst($user['role']) . '</span></td>
                <td><span class="badge bg-' . $status_badge . '">' . $status_text . '</span></td>
                <td>' . $last_active . '</td>
                <td>
                    <a href="manage_users.php?view=' . $user['user_id'] . '" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-eye"></i>
                    </a>
                </td>
            </tr>';
        }
        
        $users_content .= '
                </tbody>
            </table>
        </div>';
        
        $users_footer = '<a href="manage_users.php" class="btn btn-primary">Manage All Users</a>';
        
        echo renderCard('Recent Users', $users_content, $users_footer);
        ?>
    </div>
</div>

<?php renderPageEnd(); ?>
