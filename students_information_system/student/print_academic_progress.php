<?php
require_once '../init.php';

$student_id = $_GET['student_id'] ?? $_SESSION['user_id'] ?? '';
$is_admin = ($_SESSION['role'] ?? '') === 'admin';

if (!$is_admin && $student_id != $_SESSION['user_id']) {
    die('Unauthorized access');
}

$pdo = getDBConnection();

// Get student info
$stmt = $pdo->prepare("
    SELECT si.*, u.name, u.email
    FROM students_info si
    JOIN users u ON si.user_id = u.user_id
    WHERE si.user_id = ?
");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// Get program and find matching course
$program = $student['program'] ?? '';
$course_id = null;

// Find course by program
$stmt = $pdo->prepare("SELECT id, course_code, course_name FROM courses WHERE course_code = ? OR course_name LIKE ?");
$stmt->execute([$program, "%$program%"]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if ($course) {
    $course_id = $course['id'];
} else {
    // Try to match by known course codes
    $course_map = [
        'BS Information Technology' => 'BSIT',
        'Information Technology' => 'BSIT',
        'BS Computer Science' => 'BSCS',
        'Computer Science' => 'BSCS',
        'BS Business Administration' => 'BSBA',
        'Business Administration' => 'BSBA',
        'BS Accountancy' => 'BSA',
        'Accountancy' => 'BSA'
    ];
    
    foreach ($course_map as $key => $code) {
        if (stripos($program, $key) !== false || stripos($program, $code) !== false) {
            $stmt = $pdo->prepare("SELECT id, course_code, course_name FROM courses WHERE course_code = ?");
            $stmt->execute([$code]);
            $course = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($course) {
                $course_id = $course['id'];
                break;
            }
        }
    }
}

// If still no course, get BSIT as default
if (!$course_id) {
    $stmt = $pdo->prepare("SELECT id FROM courses WHERE course_code = 'BSIT' LIMIT 1");
    $stmt->execute();
    $course_id = $stmt->fetchColumn();
}

// Get ALL curriculum subjects for this student's course
$all_subjects = [];
if ($course_id) {
    $stmt = $pdo->prepare("
        SELECT s.*, cc.year_level, cc.semester
        FROM course_curriculum cc
        JOIN subjects s ON cc.subject_id = s.id
        WHERE cc.course_id = ?
        ORDER BY cc.year_level, FIELD(cc.semester, '1st', '2nd'), s.subject_code
    ");
    $stmt->execute([$course_id]);
    $all_subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// If no curriculum found, get subjects by program
if (empty($all_subjects) && $program) {
    $stmt = $pdo->prepare("
        SELECT s.*, s.year_level, s.semester
        FROM subjects s
        WHERE s.program LIKE ? OR s.program = 'General Education'
        ORDER BY s.year_level, FIELD(s.semester, '1st', '2nd'), s.subject_code
    ");
    $stmt->execute(["%$program%"]);
    $all_subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get existing completion records
$stmt = $pdo->prepare("
    SELECT * FROM student_course_completion 
    WHERE student_id = ?
");
$stmt->execute([$student_id]);
$completions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Create a lookup map for completions
$completion_map = [];
foreach ($completions as $c) {
    $key = $c['subject_id'] . '_' . $c['year_level'] . '_' . $c['semester'];
    $completion_map[$key] = $c;
}

// Merge curriculum with completion data
$progress = [];
foreach ($all_subjects as $subject) {
    $year = $subject['year_level'];
    $semester = $subject['semester'];
    $key = $subject['id'] . '_' . $year . '_' . $semester;
    
    if (isset($completion_map[$key])) {
        $completion = $completion_map[$key];
        $subject['grade'] = $completion['grade'];
        $subject['date_completed'] = $completion['date_completed'];
        $subject['status'] = $completion['status'];
    } else {
        // Determine default status
        if ($year < ($student['year_level'] ?? 1)) {
            $subject['status'] = 'pending';
        } elseif ($year == ($student['year_level'] ?? 1)) {
            $subject['status'] = 'in_progress';
        } else {
            $subject['status'] = 'upcoming';
        }
        $subject['grade'] = null;
        $subject['date_completed'] = null;
    }
    
    $progress[$year][$semester][] = $subject;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Academic Progress - <?php echo htmlspecialchars($student['name']); ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .student-info { margin-bottom: 20px; border: 1px solid #ddd; padding: 10px; border-radius: 5px; }
        .year-section { margin-bottom: 30px; page-break-inside: avoid; }
        .year-title { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 10px; margin: 0; border-radius: 5px 5px 0 0; }
        .semester-title { background: #f0f0f0; padding: 8px 12px; margin: 10px 0 0 0; border-left: 4px solid #4CAF50; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .grade-pass { color: green; font-weight: bold; }
        .grade-fail { color: red; font-weight: bold; }
        .grade-inprogress { color: #ff9800; font-weight: bold; }
        .status-completed { color: green; }
        .status-in_progress { color: #ff9800; }
        .status-upcoming { color: #999; }
        .status-pending { color: #f44336; }
        .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #666; }
        @media print {
            .no-print { display: none; }
            body { margin: 0; padding: 0; }
            .year-title { background: #4CAF50 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
        button { padding: 10px 20px; margin-bottom: 20px; margin-right: 10px; cursor: pointer; background: #007bff; color: white; border: none; border-radius: 5px; }
        button:hover { background: #0056b3; }
        .text-center { text-align: center; }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()">🖨️ Print / Save as PDF</button>
        <button onclick="window.close()">Close</button>
    </div>
    
    <div class="header">
        <h2>ACLC College - Mandaue</h2>
        <h3>Academic Progress Report</h3>
    </div>
    
    <div class="student-info">
        <table style="width: 100%; border: none;">
            <tr><td style="border: none; width: 15%;"><strong>Name:</strong></td>
                <td style="border: none; width: 35%;"><?php echo htmlspecialchars($student['name']); ?></td>
                <td style="border: none; width: 15%;"><strong>Student ID:</strong></td>
                <td style="border: none; width: 35%;"><?php echo htmlspecialchars($student['user_id']); ?></span></td>
            </tr>
            <tr><td style="border: none;"><strong>Program:</strong></td>
                <td style="border: none;"><?php echo htmlspecialchars($student['program']); ?></td>
                <td style="border: none;"><strong>Year Level:</strong></td>
                <td style="border: none;"><?php echo $student['year_level']; ?></td>
            </tr>
            <tr><td style="border: none;"><strong>Student Type:</strong></td>
                <td style="border: none;"><?php echo ucfirst($student['student_type']); ?></td>
                <td style="border: none;"><strong>Status:</strong></td>
                <td style="border: none;"><?php echo ucfirst($student['enrollment_status']); ?></td>
            </tr>
        </table>
    </div>
    
    <?php for ($year = 1; $year <= 4; $year++): ?>
        <?php if (isset($progress[$year]) && !empty($progress[$year])): ?>
            <div class="year-section">
                <h4 class="year-title">YEAR <?php echo $year; ?></h4>
                <?php foreach (['1st', '2nd'] as $semester): ?>
                    <?php if (isset($progress[$year][$semester]) && count($progress[$year][$semester]) > 0): ?>
                        <h5 class="semester-title"><?php echo $semester; ?> Semester</h5>
                        <table>
                            <thead>
                                <tr>
                                    <th width="15%">Subject Code</th>
                                    <th width="40%">Subject Title</th>
                                    <th width="8%" class="text-center">Units</th>
                                    <th width="12%" class="text-center">Grade</th>
                                    <th width="15%" class="text-center">Date Completed</th>
                                    <th width="10%" class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($progress[$year][$semester] as $subject): 
                                    $grade = $subject['grade'];
                                    $grade_class = '';
                                    $status_text = '';
                                    
                                    if ($grade) {
                                        if ($grade <= 1.5) $grade_class = 'grade-pass';
                                        elseif ($grade <= 2.0) $grade_class = 'grade-pass';
                                        elseif ($grade <= 2.75) $grade_class = 'grade-pass';
                                        elseif ($grade == 3.0) $grade_class = 'grade-pass';
                                        elseif ($grade >= 5.0) $grade_class = 'grade-fail';
                                        $status_text = 'Completed';
                                    } else {
                                        $status_text = ucfirst(str_replace('_', ' ', $subject['status'] ?? 'pending'));
                                        $status_class = 'status-' . ($subject['status'] ?? 'pending');
                                    }
                                ?>
                                    <tr>
                                        <td><code><?php echo htmlspecialchars($subject['subject_code']); ?></code></td>
                                        <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                                        <td class="text-center"><?php echo $subject['units']; ?></td>
                                        <td class="text-center <?php echo $grade_class; ?>">
                                            <?php echo $grade ? number_format($grade, 2) : '—'; ?>
                                        </span></td>
                                        <td class="text-center">
                                            <?php echo $subject['date_completed'] ? date('M d, Y', strtotime($subject['date_completed'])) : '—'; ?>
                                        </span></td>
                                        <td class="text-center <?php echo $status_class ?? ''; ?>">
                                            <?php echo $status_text; ?>
                                        </span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endfor; ?>
    
    <div class="footer">
        <p>Generated on <?php echo date('F d, Y'); ?> at <?php echo date('g:i A'); ?></p>
        <p>This is an official academic progress report. For verification, contact the Registrar's Office.</p>
    </div>
</body>
</html>