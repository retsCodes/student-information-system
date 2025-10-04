<?php
require_once '../init.php';
require_once '../theme.php';
require_once '../layout.php';

requireRole('cashier');

$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle subject filtering
$filter = isset($_GET['filter']) ? $_GET['filter'] : '';
$program_filter = isset($_GET['program']) ? $_GET['program'] : '';
$year_filter = isset($_GET['year']) ? intval($_GET['year']) : 0;

// Get all subjects with filtering
$query = "SELECT * FROM subjects WHERE 1=1";
$params = [];

if (!empty($filter)) {
    $query .= " AND (subject_code LIKE ? OR subject_name LIKE ?)";
    $params[] = "%$filter%";
    $params[] = "%$filter%";
}

$query .= " ORDER BY subject_code";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all sections for dropdown
$stmt = $pdo->query("SELECT * FROM sections ORDER BY section_code");
$sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all programs for filtering
$stmt = $pdo->query("SELECT DISTINCT program FROM students_info WHERE program IS NOT NULL ORDER BY program");
$programs = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Handle subject assignment to section
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_to_section'])) {
    $subject_id = $_POST['subject_id'];
    $section_id = $_POST['section_id'];
    
    // Get subject and section details
    $stmt = $pdo->prepare("SELECT * FROM subjects WHERE id = ?");
    $stmt->execute([$subject_id]);
    $subject = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("SELECT * FROM sections WHERE id = ?");
    $stmt->execute([$section_id]);
    $section = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($subject && $section) {
        // Update subject's sections array
        $sections_array = !empty($subject['sections']) ? json_decode($subject['sections'], true) : [];
        if (!in_array($section['section_code'], $sections_array)) {
            $sections_array[] = $section['section_code'];
            
            $stmt = $pdo->prepare("UPDATE subjects SET sections = ? WHERE id = ?");
            $stmt->execute([json_encode($sections_array), $subject_id]);
            
            // Log the activity
            logActivity($user_id, 'Assigned Subject to Section', "Assigned {$subject['subject_code']} to section {$section['section_code']}");
            
            $message = "Subject {$subject['subject_code']} successfully assigned to section {$section['section_code']}.";
        } else {
            $error = "Subject is already assigned to this section.";
        }
    } else {
        $error = "Invalid subject or section.";
    }
}

// Handle subject assignment to student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_to_student'])) {
    $subject_id = $_POST['subject_id'];
    $student_id = $_POST['student_id'];
    
    // Get subject details
    $stmt = $pdo->prepare("SELECT * FROM subjects WHERE id = ?");
    $stmt->execute([$subject_id]);
    $subject = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get student details
    $stmt = $pdo->prepare("SELECT * FROM students_info WHERE user_id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($subject && $student) {
        // Update subject's addition array (for irregular students)
        $addition_array = !empty($subject['addition']) ? json_decode($subject['addition'], true) : [];
        if (!in_array($student_id, $addition_array)) {
            $addition_array[] = $student_id;
            
            $stmt = $pdo->prepare("UPDATE subjects SET addition = ? WHERE id = ?");
            $stmt->execute([json_encode($addition_array), $subject_id]);
            
            // Log the activity
            logActivity($user_id, 'Assigned Subject to Student', "Assigned {$subject['subject_code']} to student {$student['name']}");
            
            $message = "Subject {$subject['subject_code']} successfully assigned to student {$student['name']}.";
        } else {
            $error = "Subject is already assigned to this student.";
        }
    } else {
        $error = "Invalid subject or student.";
    }
}

// Get all students for dropdown
$stmt = $pdo->query("SELECT si.*, u.user_id FROM students_info si JOIN users u ON si.user_id = u.user_id ORDER BY si.name");
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Page title and header
$pageTitle = "Manage Subjects";
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'dashboard.php'],
    ['label' => 'Manage Subjects', 'url' => '#', 'active' => true]
];

include_once '../layout_header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4"><?php echo $pageTitle; ?></h1>
    
    <?php include_once '../breadcrumbs.php'; ?>
    
    <?php if (!empty($message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-filter me-1"></i>
            Filter Subjects
        </div>
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-4">
                    <label for="filter" class="form-label">Search</label>
                    <input type="text" class="form-control" id="filter" name="filter" 
                           value="<?php echo htmlspecialchars($filter); ?>" 
                           placeholder="Subject code or name">
                </div>
                <div class="col-md-4">
                    <label for="program" class="form-label">Program</label>
                    <select class="form-select" id="program" name="program">
                        <option value="">All Programs</option>
                        <?php foreach ($programs as $program): ?>
                            <option value="<?php echo htmlspecialchars($program); ?>" 
                                    <?php echo $program_filter === $program ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($program); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="year" class="form-label">Year Level</label>
                    <select class="form-select" id="year" name="year">
                        <option value="0">All Years</option>
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo $year_filter === $i ? 'selected' : ''; ?>>
                                Year <?php echo $i; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-1"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-table me-1"></i>
            Subject List
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="subjectsTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Units</th>
                            <th>Sections</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subjects as $subject): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($subject['subject_code']); ?></td>
                                <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                                <td><?php echo htmlspecialchars($subject['units']); ?></td>
                                <td>
                                    <?php 
                                    if (!empty($subject['sections'])) {
                                        $section_codes = json_decode($subject['sections'], true);
                                        echo htmlspecialchars(implode(', ', $section_codes));
                                    } else {
                                        echo '<span class="text-muted">None</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-sm btn-primary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#assignSectionModal<?php echo $subject['id']; ?>">
                                            <i class="fas fa-users me-1"></i> Assign to Section
                                        </button>
                                        <button type="button" class="btn btn-sm btn-success" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#assignStudentModal<?php echo $subject['id']; ?>">
                                            <i class="fas fa-user-graduate me-1"></i> Assign to Student
                                        </button>
                                    </div>
                                    
                                    <!-- Assign to Section Modal -->
                                    <div class="modal fade" id="assignSectionModal<?php echo $subject['id']; ?>" tabindex="-1" 
                                         aria-labelledby="assignSectionModalLabel<?php echo $subject['id']; ?>" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST" action="">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="assignSectionModalLabel<?php echo $subject['id']; ?>">
                                                            Assign <?php echo htmlspecialchars($subject['subject_code']); ?> to Section
                                                        </h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="subject_id" value="<?php echo $subject['id']; ?>">
                                                        <div class="mb-3">
                                                            <label for="section_id" class="form-label">Select Section</label>
                                                            <select class="form-select" id="section_id" name="section_id" required>
                                                                <option value="">-- Select Section --</option>
                                                                <?php foreach ($sections as $section): ?>
                                                                    <option value="<?php echo $section['id']; ?>">
                                                                        <?php echo htmlspecialchars($section['section_code']); ?> 
                                                                        (<?php echo htmlspecialchars($section['program'] ?? 'N/A'); ?> - 
                                                                        Year <?php echo htmlspecialchars($section['year_level'] ?? 'N/A'); ?>)
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" name="assign_to_section" class="btn btn-primary">Assign</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Assign to Student Modal -->
                                    <div class="modal fade" id="assignStudentModal<?php echo $subject['id']; ?>" tabindex="-1" 
                                         aria-labelledby="assignStudentModalLabel<?php echo $subject['id']; ?>" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST" action="">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="assignStudentModalLabel<?php echo $subject['id']; ?>">
                                                            Assign <?php echo htmlspecialchars($subject['subject_code']); ?> to Student
                                                        </h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="subject_id" value="<?php echo $subject['id']; ?>">
                                                        <div class="mb-3">
                                                            <label for="student_id" class="form-label">Select Student</label>
                                                            <select class="form-select" id="student_id" name="student_id" required>
                                                                <option value="">-- Select Student --</option>
                                                                <?php foreach ($students as $student): ?>
                                                                    <option value="<?php echo $student['user_id']; ?>">
                                                                        <?php echo htmlspecialchars($student['user_id']); ?> - 
                                                                        <?php echo htmlspecialchars($student['name']); ?>
                                                                        (<?php echo htmlspecialchars($student['program'] ?? 'N/A'); ?> - 
                                                                        Year <?php echo htmlspecialchars($student['year_level'] ?? 'N/A'); ?>)
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" name="assign_to_student" class="btn btn-primary">Assign</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#subjectsTable').DataTable({
        responsive: true
    });
});
</script>

<?php include_once '../layout_footer.php'; ?>