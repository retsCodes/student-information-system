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

// Total revenue collected
$stmt = $pdo->query("SELECT COALESCE(SUM(amount - remaining_balance), 0) as total FROM payments WHERE payment_status IN ('paid', 'partial')");
$stats['total_revenue'] = $stmt->fetch()['total'];

// Total outstanding balance
$stmt = $pdo->query("SELECT COALESCE(SUM(remaining_balance), 0) as total FROM payments");
$stats['total_outstanding'] = $stmt->fetch()['total'];

// Recent users with last login
$stmt = $pdo->query("SELECT u.user_id, u.name, u.role, u.last_active, u.user_status 
                     FROM users u 
                     ORDER BY u.last_active DESC 
                     LIMIT 10");
$recent_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Payment statistics for chart (last 7 days)
$stmt = $pdo->query("SELECT DATE(issued_date) as date, COUNT(*) as count, SUM(amount) as total,
                     SUM(CASE WHEN payment_status = 'paid' THEN amount ELSE 0 END) as paid_amount,
                     SUM(CASE WHEN payment_status = 'partial' THEN (amount - remaining_balance) ELSE 0 END) as partial_amount,
                     SUM(remaining_balance) as outstanding_amount
                     FROM payments 
                     WHERE issued_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                     GROUP BY DATE(issued_date)
                     ORDER BY date");
$payment_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Generate last 7 days data including days with no payments
$last_7_days = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $last_7_days[$date] = [
        'date' => $date,
        'count' => 0,
        'total' => 0,
        'paid_amount' => 0,
        'partial_amount' => 0,
        'outstanding_amount' => 0
    ];
}

// Merge with actual payment data
foreach ($payment_stats as $payment) {
    $date = $payment['date'];
    if (isset($last_7_days[$date])) {
        $last_7_days[$date] = $payment;
    }
}

$payment_stats = array_values($last_7_days);

// Payment status distribution with amounts
$stmt = $pdo->query("SELECT payment_status, COUNT(*) as count, 
                     COALESCE(SUM(amount), 0) as total_amount,
                     COALESCE(SUM(remaining_balance), 0) as remaining_total
                     FROM payments 
                     GROUP BY payment_status");
$payment_status_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Payment categories distribution
$stmt = $pdo->query("SELECT payment_category, COUNT(*) as count, 
                     COALESCE(SUM(amount), 0) as total_amount
                     FROM payments 
                     GROUP BY payment_category
                     ORDER BY total_amount DESC");
$payment_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Monthly payment trends (last 6 months)
$stmt = $pdo->query("SELECT 
                     DATE_FORMAT(issued_date, '%Y-%m') as month,
                     COUNT(*) as count,
                     COALESCE(SUM(amount), 0) as total_amount,
                     COALESCE(SUM(amount - remaining_balance), 0) as collected_amount
                     FROM payments 
                     WHERE issued_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                     GROUP BY DATE_FORMAT(issued_date, '%Y-%m')
                     ORDER BY month");
$monthly_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Generate last 6 months data including months with no payments
$last_6_months = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $last_6_months[$month] = [
        'month' => $month,
        'count' => 0,
        'total_amount' => 0,
        'collected_amount' => 0
    ];
}

// Merge with actual payment data
foreach ($monthly_stats as $monthly) {
    $month = $monthly['month'];
    if (isset($last_6_months[$month])) {
        $last_6_months[$month] = $monthly;
    }
}

$monthly_stats = array_values($last_6_months);

// Recent payments for table - FIXED: removed p.created_at reference
$stmt = $pdo->query("SELECT p.*, u.name as student_name 
                     FROM payments p
                     JOIN users u ON p.student_id = u.user_id
                     ORDER BY p.issued_date DESC 
                     LIMIT 8");
$recent_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent activities
$stmt = $pdo->query("SELECT al.*, u.name 
                     FROM activity_logs al 
                     JOIN users u ON al.user_id = u.user_id 
                     ORDER BY al.created_at DESC 
                     LIMIT 10");
$recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

renderPageStart('Admin Dashboard', 'admin', 'dashboard.php');
?>

<!-- Include Chart.js from CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <?php echo renderStatsCard('Total Users', $stats['total_users'], 'fas fa-users', 'primary'); ?>
    </div>
    <div class="col-md-3 mb-3">
        <?php echo renderStatsCard('Students', $stats['total_students'], 'fas fa-user-graduate', 'success'); ?>
    </div>
    <div class="col-md-2 mb-3">
        <?php echo renderStatsCard('Payments Today', $stats['payments_today'], 'fas fa-money-bill-wave', 'info'); ?>
    </div>
    <div class="col-md-2 mb-3">
        <?php echo renderStatsCard('Unpaid Bills', $stats['unpaid_payments'], 'fas fa-exclamation-triangle', 'warning'); ?>
    </div>
    <div class="col-md-2 mb-3">
        <?php echo renderStatsCard('Total Revenue', '₱' . number_format($stats['total_revenue'], 0), 'fas fa-chart-line', 'success'); ?>
    </div>
</div>

<div class="row">
    <div class="col-md-8 mb-4">
        <?php
        // Prepare daily chart data
        $daily_labels = [];
        $daily_count_data = [];
        $daily_total_data = [];
        $daily_paid_data = [];
        $daily_partial_data = [];
        
        foreach ($payment_stats as $stat) {
            $daily_labels[] = date('M j', strtotime($stat['date']));
            $daily_count_data[] = $stat['count'];
            $daily_total_data[] = $stat['total'];
            $daily_paid_data[] = $stat['paid_amount'];
            $daily_partial_data[] = $stat['partial_amount'];
        }
        
        $daily_chart_content = '
        <div class="chart-container" style="position: relative; height:300px;">
            <canvas id="dailyPaymentsChart"></canvas>
        </div>
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            const ctx = document.getElementById("dailyPaymentsChart").getContext("2d");
            const dailyPaymentsChart = new Chart(ctx, {
                type: "bar",
                data: {
                    labels: ' . json_encode($daily_labels) . ',
                    datasets: [{
                        label: "Number of Payments",
                        data: ' . json_encode($daily_count_data) . ',
                        backgroundColor: "rgba(54, 162, 235, 0.6)",
                        borderColor: "rgb(54, 162, 235)",
                        borderWidth: 1,
                        yAxisID: "y"
                    }, {
                        label: "Total Amount",
                        data: ' . json_encode($daily_total_data) . ',
                        borderColor: "rgb(255, 99, 132)",
                        backgroundColor: "rgba(255, 99, 132, 0.2)",
                        type: "line",
                        tension: 0.4,
                        yAxisID: "y1",
                        order: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: "index",
                        intersect: false,
                    },
                    plugins: {
                        title: {
                            display: true,
                            text: "Daily Payment Trends (Last 7 Days)",
                            font: {
                                size: 16
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || "";
                                    if (label) {
                                        label += ": ";
                                    }
                                    if (context.datasetIndex === 1) {
                                        label += "₱" + context.parsed.y.toLocaleString();
                                    } else {
                                        label += context.parsed.y + " payments";
                                    }
                                    return label;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            }
                        },
                        y: {
                            type: "linear",
                            display: true,
                            position: "left",
                            title: {
                                display: true,
                                text: "Number of Payments"
                            },
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        },
                        y1: {
                            type: "linear",
                            display: true,
                            position: "right",
                            title: {
                                display: true,
                                text: "Total Amount (PHP)"
                            },
                            beginAtZero: true,
                            grid: {
                                drawOnChartArea: false,
                            },
                            ticks: {
                                callback: function(value) {
                                    return "₱" + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        });
        </script>';
        
        echo renderCard('Daily Payment Statistics', $daily_chart_content);
        ?>
    </div>
    
    <div class="col-md-4 mb-4">
        <?php
        // Payment status distribution with amounts
        $status_labels = [];
        $status_count_data = [];
        $status_amount_data = [];
        $status_colors = [
            'paid' => 'rgb(75, 192, 192)',
            'unpaid' => 'rgb(255, 99, 132)',
            'partial' => 'rgb(255, 205, 86)'
        ];
        
        foreach ($payment_status_data as $status) {
            $status_labels[] = ucfirst($status['payment_status']) . ' (' . $status['count'] . ')';
            $status_count_data[] = $status['count'];
            $status_amount_data[] = $status['total_amount'];
        }
        
        $status_chart_content = '
        <div class="chart-container" style="position: relative; height:250px;">
            <canvas id="paymentStatusChart"></canvas>
        </div>
        <div class="mt-3">
            <table class="table table-sm table-borderless">
                <tbody>';
        
        foreach ($payment_status_data as $status) {
            $status_chart_content .= '
                <tr>
                    <td><span class="badge" style="background-color: ' . $status_colors[$status['payment_status']] . '">' . ucfirst($status['payment_status']) . '</span></td>
                    <td class="text-end">' . $status['count'] . '</td>
                    <td class="text-end">₱' . number_format($status['total_amount'], 0) . '</td>
                </tr>';
        }
        
        $status_chart_content .= '
                </tbody>
            </table>
        </div>
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            const ctx2 = document.getElementById("paymentStatusChart").getContext("2d");
            const paymentStatusChart = new Chart(ctx2, {
                type: "doughnut",
                data: {
                    labels: ' . json_encode($status_labels) . ',
                    datasets: [{
                        data: ' . json_encode($status_amount_data) . ',
                        backgroundColor: ["rgb(75, 192, 192)", "rgb(255, 99, 132)", "rgb(255, 205, 86)"],
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
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || "";
                                    const value = context.parsed || 0;
                                    return label + ": ₱" + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        });
        </script>';
        
        echo renderCard('Payment Status Distribution', $status_chart_content);
        ?>
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-4">
        <?php
        // Monthly trends chart
        $monthly_labels = [];
        $monthly_count_data = [];
        $monthly_amount_data = [];
        $monthly_collected_data = [];
        
        foreach ($monthly_stats as $stat) {
            $monthly_labels[] = date('M Y', strtotime($stat['month'] . '-01'));
            $monthly_count_data[] = $stat['count'];
            $monthly_amount_data[] = $stat['total_amount'];
            $monthly_collected_data[] = $stat['collected_amount'];
        }
        
        $monthly_chart_content = '
        <div class="chart-container" style="position: relative; height:250px;">
            <canvas id="monthlyTrendsChart"></canvas>
        </div>
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            const ctx3 = document.getElementById("monthlyTrendsChart").getContext("2d");
            const monthlyTrendsChart = new Chart(ctx3, {
                type: "line",
                data: {
                    labels: ' . json_encode($monthly_labels) . ',
                    datasets: [{
                        label: "Total Amount",
                        data: ' . json_encode($monthly_amount_data) . ',
                        borderColor: "rgb(255, 99, 132)",
                        backgroundColor: "rgba(255, 99, 132, 0.1)",
                        tension: 0.4,
                        fill: true
                    }, {
                        label: "Collected Amount",
                        data: ' . json_encode($monthly_collected_data) . ',
                        borderColor: "rgb(75, 192, 192)",
                        backgroundColor: "rgba(75, 192, 192, 0.1)",
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: "Monthly Payment Trends (Last 6 Months)",
                            font: {
                                size: 14
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ": ₱" + context.parsed.y.toLocaleString();
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return "₱" + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        });
        </script>';
        
        echo renderCard('Monthly Payment Trends', $monthly_chart_content);
        ?>
    </div>
    
    <div class="col-md-6 mb-4">
        <?php
        // Payment categories chart
        $category_labels = [];
        $category_data = [];
        $category_colors = [
            'rgb(54, 162, 235)', 'rgb(255, 99, 132)', 'rgb(255, 205, 86)', 
            'rgb(75, 192, 192)', 'rgb(153, 102, 255)', 'rgb(201, 203, 207)'
        ];
        
        foreach ($payment_categories as $index => $category) {
            $category_labels[] = ucfirst($category['payment_category']) . ' (' . $category['count'] . ')';
            $category_data[] = $category['total_amount'];
        }
        
        $category_chart_content = '
        <div class="chart-container" style="position: relative; height:250px;">
            <canvas id="paymentCategoriesChart"></canvas>
        </div>
        <div class="mt-3">
            <table class="table table-sm table-borderless">
                <tbody>';
        
        foreach ($payment_categories as $index => $category) {
            $percentage = $stats['total_revenue'] > 0 ? ($category['total_amount'] / $stats['total_revenue'] * 100) : 0;
            $category_chart_content .= '
                <tr>
                    <td><span class="badge" style="background-color: ' . $category_colors[$index] . '">' . ucfirst($category['payment_category']) . '</span></td>
                    <td class="text-end">' . $category['count'] . '</td>
                    <td class="text-end">₱' . number_format($category['total_amount'], 0) . '</td>
                    <td class="text-end">' . number_format($percentage, 1) . '%</td>
                </tr>';
        }
        
        $category_chart_content .= '
                </tbody>
            </table>
        </div>
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            const ctx4 = document.getElementById("paymentCategoriesChart").getContext("2d");
            const paymentCategoriesChart = new Chart(ctx4, {
                type: "pie",
                data: {
                    labels: ' . json_encode($category_labels) . ',
                    datasets: [{
                        data: ' . json_encode($category_data) . ',
                        backgroundColor: ' . json_encode(array_slice($category_colors, 0, count($payment_categories))) . ',
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
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || "";
                                    const value = context.parsed || 0;
                                    return label + ": ₱" + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        });
        </script>';
        
        echo renderCard('Payment Categories', $category_chart_content);
        ?>
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-4">
        <?php
        $payments_content = '
        <div class="table-responsive">
            <table class="table table-sm table-striped">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>';
        
        if (empty($recent_payments)) {
            $payments_content .= '
            <tr>
                <td colspan="4" class="text-center text-muted">No recent payments found</td>
            </tr>';
        } else {
            foreach($recent_payments as $payment) {
                $status_badge = $payment['payment_status'] === 'paid' ? 'success' : 
                               ($payment['payment_status'] === 'partial' ? 'warning' : 'danger');
                $status_text = ucfirst($payment['payment_status']);
                $payment_date = date('M j', strtotime($payment['issued_date']));
                $student_name = htmlspecialchars($payment['student_name']);
                
                // Truncate long names
                if (strlen($student_name) > 20) {
                    $student_name = substr($student_name, 0, 20) . '...';
                }
                
                $payments_content .= '
                <tr>
                    <td>
                        <div>
                            <strong>' . $student_name . '</strong>
                            <br>
                            <small class="text-muted">' . htmlspecialchars($payment['permit_number']) . '</small>
                        </div>
                    </td>
                    <td>
                        <strong>₱' . number_format($payment['amount'], 0) . '</strong>';
                
                if ($payment['payment_status'] === 'partial') {
                    $payments_content .= '<br><small class="text-muted">Balance: ₱' . number_format($payment['remaining_balance'], 0) . '</small>';
                }
                
                $payments_content .= '
                    </td>
                    <td><span class="badge bg-' . $status_badge . '">' . $status_text . '</span></td>
                    <td><small>' . $payment_date . '</small></td>
                </tr>';
            }
        }
        
        $payments_content .= '
                </tbody>
            </table>
        </div>';
        
        $payments_footer = '<a href="payments.php" class="btn btn-sm btn-outline-primary">View All Payments</a>';
        
        echo renderCard('Recent Payments', $payments_content, $payments_footer);
        ?>
    </div>
    
    <div class="col-md-6 mb-4">
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
                    </tr>
                </thead>
                <tbody>';
        
        if (empty($recent_users)) {
            $users_content .= '
            <tr>
                <td colspan="5" class="text-center text-muted">No users found</td>
            </tr>';
        } else {
            foreach($recent_users as $user) {
                $status_badge = $user['user_status'] === 'active' ? 'success' : 'danger';
                $status_text = ucfirst($user['user_status']);
                $last_active = $user['last_active'] ? date('M j, Y g:i A', strtotime($user['last_active'])) : 'Never';
                $role_badge = $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'cashier' ? 'warning' : 'info');
                
                $users_content .= '
                <tr>
                    <td><code>' . htmlspecialchars($user['user_id']) . '</code></td>
                    <td>' . htmlspecialchars($user['name']) . '</td>
                    <td><span class="badge bg-' . $role_badge . '">' . ucfirst($user['role']) . '</span></td>
                    <td><span class="badge bg-' . $status_badge . '">' . $status_text . '</span></td>
                    <td><small>' . $last_active . '</small></td>
                </tr>';
            }
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