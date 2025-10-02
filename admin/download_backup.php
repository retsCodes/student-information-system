<?php
require_once '../init.php';

requireRole('admin');

$backup_file = sanitizeInput($_GET['file'] ?? '');

if (empty($backup_file)) {
    die('Invalid backup file.');
}

// Validate file name format for security
if (!preg_match('/^backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.sql$/', $backup_file)) {
    die('Invalid backup file format.');
}

$backup_path = __DIR__ . '/../backups/' . $backup_file;

if (!file_exists($backup_path) || !is_file($backup_path)) {
    die('Backup file not found.');
}

// Log the download
logActivity($_SESSION['user_id'], 'Backup Downloaded', "Downloaded backup file: {$backup_file}");

// Set headers for file download
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $backup_file . '"');
header('Content-Length: ' . filesize($backup_path));
header('Cache-Control: must-revalidate');
header('Pragma: public');

// Output file
readfile($backup_path);
exit();
?>
