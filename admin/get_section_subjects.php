<?php
require_once '../init.php';

requireRole('admin');

header('Content-Type: application/json');

$section_id = intval($_GET['section_id'] ?? 0);

if ($section_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid section ID']);
    exit();
}

try {
    $pdo = getDBConnection();
    
    // Get section info
    $stmt = $pdo->prepare("SELECT section_code FROM sections WHERE id = ?");
    $stmt->execute([$section_id]);
    $section = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$section) {
        echo json_encode(['success' => false, 'error' => 'Section not found']);
        exit();
    }
    
    // Get subjects that contain this section
    $stmt = $pdo->prepare("SELECT id FROM subjects WHERE JSON_CONTAINS(sections, JSON_QUOTE(?))");
    $stmt->execute([$section['section_code']]);
    $subject_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode(['success' => true, 'subjects' => $subject_ids]);
    
} catch(Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
