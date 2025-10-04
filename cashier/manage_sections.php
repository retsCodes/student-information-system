<?php
require_once '../init.php';
require_once '../theme.php';
require_once '../layout.php';

requireRole('cashier');

$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Get all sections
$stmt = $pdo->query("SELECT * FROM sections ORDER BY section_code");
$sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle adding student to section
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student'])) {
    $section_id = $_POST['section_id'];
    $student_id = $_POST['student_id'];
    
    // Get section details
    $stmt = $pdo->prepare("SELECT * FROM sections WHERE id = ?");
    $stmt->execute([$section_id]);
    $section = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get student details
    $stmt = $pdo->prepare("SELECT * FROM students_info WHERE user_id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($section && $student) {
        // Update section's user_id array
        $user_ids = !empty($section['user_id']) ? json_decode($section['user_id'], true) : [];
        if (!in_array($student_id, $user_ids)) {
            $user_ids[] = $student_id;
            
            $stmt = $pdo->prepare("UPDATE sections SET user_id = ? WHERE id = ?");
            $stmt->execute([json_encode($user_ids), $section_id]);
            
            // Log the activity
            logActivity($user_id, 'Added Student to Section', "Added student {$student['name']} to section {$section['section_code']}");
            
            $message = "Student {$student['name']} successfully added to section {$section['section_code']}.";
        } else {
            $error = "Student is already in this section.";
        }
    } else {
        $error = "Invalid section or student.";
    }
}

// Get all students for dropdown
$stmt = $pdo->query("SELECT si.*, u.user_id FROM students_info si JOIN users u ON si.user_id = u.user_id ORDER BY si.name");
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Page title and header
$pageTitle = "Manage Sections";
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'dashboard.php'],
    ['label' => 'Manage Sections', 'url' => '#', 'active' => true]
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
            <i class="fas fa-table me-1"></i>
            Section List
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="sectionsTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Section Code</th>
                            <th>Program</th>
                            <th>Year Level</th>
                            <th>Students</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sections as $section): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($section['section_code']); ?></td>
                                <td><?php echo htmlspecialchars($section['program'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($section['year_level'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php 
                                    if (!empty($section['user_id'])) {
                                        $user_ids = json_decode($section['user_id'], true);
                                        echo count($user_ids) . ' student(s)';
                                    } else {
                                        echo '<span class="text-muted">None</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $section['status'] === 'active' ? 'success' : 'danger'; ?>">
                                        <?php echo ucfirst(htmlspecialchars($section['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="section_details.php?id=<?php echo $section['id']; ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye me-1"></i> View
                                        </a>
                                        <button type="button" class="btn btn-sm btn-primary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#addStudentModal<?php echo $section['id']; ?>">
                                            <i class="fas fa-user-plus me-1"></i> Add Student
                                        </button>
                                    </div>
                                    
                                    <!-- Add Student Modal -->
                                    <div class="modal fade" id="addStudentModal<?php echo $section['id']; ?>" tabindex="-1" 
                                         aria-labelledby="addStudentModalLabel<?php echo $section['id']; ?>" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST" action="">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="addStudentModalLabel<?php echo $section['id']; ?>">
                                                            Add Student to <?php echo htmlspecialchars($section['section_code']); ?>
                                                        </h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="section_id" value="<?php echo $section['id']; ?>">
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
                                                        <button type="submit" name="add_student" class="btn btn-primary">Add Student</button>
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
    $('#sectionsTable').DataTable({
        responsive: true
    });
});
</script>

<?php include_once '../layout_footer.php'; ?>