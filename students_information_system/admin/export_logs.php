<?php
require_once '../init.php';
requireRole('admin');

$pdo = getDBConnection();
$format = $_GET['export'] ?? 'csv';

// Get filters (same as in logs.php)
$action_filter = $_GET['action'] ?? '';
$user_filter = $_GET['user'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';

// Build query conditions (same as logs.php)
$where_conditions = [];
$params = [];

if (!empty($action_filter)) {
    $where_conditions[] = "al.action LIKE ?";
    $params[] = "%{$action_filter}%";
}
if (!empty($user_filter)) {
    $where_conditions[] = "al.user_id = ?";
    $params[] = $user_filter;
}
if (!empty($date_from)) {
    $where_conditions[] = "DATE(al.created_at) >= ?";
    $params[] = $date_from;
}
if (!empty($date_to)) {
    $where_conditions[] = "DATE(al.created_at) <= ?";
    $params[] = $date_to;
}
if (!empty($search)) {
    $where_conditions[] = "(al.description LIKE ? OR al.action LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Fetch logs
$query = "SELECT al.*, u.name as user_name, u.role as user_role
          FROM activity_logs al
          LEFT JOIN users u ON al.user_id = u.user_id
          {$where_clause}
          ORDER BY al.created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($format === 'csv') {
    // Export as CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="activity_logs_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    // Add UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Headers
    fputcsv($output, ['Date', 'Time', 'User ID', 'User Name', 'Role', 'Action', 'Description']);
    
    // Data rows
    foreach ($logs as $log) {
        fputcsv($output, [
            date('Y-m-d', strtotime($log['created_at'])),
            date('H:i:s', strtotime($log['created_at'])),
            $log['user_id'],
            $log['user_name'] ?? 'Deleted User',
            $log['user_role'] ?? 'N/A',
            $log['action'],
            $log['description']
        ]);
    }
    fclose($output);
    exit;
    
} elseif ($format === 'pdf') {
    // Use a simple HTML-to-PDF approach (requires dompdf or similar)
    // For simplicity, we'll output an HTML page that can be printed to PDF via browser
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Activity Logs Export</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            h1 { text-align: center; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
            .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <h1>Activity Logs</h1>
        <p>Generated on: ' . date('Y-m-d H:i:s') . '</p>
        <p>Filters applied: ' . (!empty($action_filter) ? "Action: $action_filter " : '') . 
           (!empty($user_filter) ? "User: $user_filter " : '') .
           (!empty($date_from) ? "From: $date_from " : '') .
           (!empty($date_to) ? "To: $date_to " : '') .
           (!empty($search) ? "Search: $search " : '') . '</p>
        <table>
            <thead>
                <tr>
                    <th>Date</th><th>Time</th><th>User ID</th><th>User Name</th><th>Role</th><th>Action</th><th>Description</th>
                </tr>
            </thead>
            <tbody>';
    foreach ($logs as $log) {
        echo '<tr>
                <td>' . date('Y-m-d', strtotime($log['created_at'])) . '</td>
                <td>' . date('H:i:s', strtotime($log['created_at'])) . '</td>
                <td>' . htmlspecialchars($log['user_id']) . '</td>
                <td>' . htmlspecialchars($log['user_name'] ?? 'Deleted User') . '</td>
                <td>' . htmlspecialchars($log['user_role'] ?? 'N/A') . '</td>
                <td>' . htmlspecialchars($log['action']) . '</td>
                <td>' . nl2br(htmlspecialchars($log['description'])) . '</td>
            </tr>';
    }
    echo '</tbody>
        </table>
        <div class="footer">This is a system-generated report.</div>
        <script>window.print();</script>
    </body>
    </html>';
    exit;
}