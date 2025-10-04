<?php
require_once '../init.php';
require_once '../theme.php';
require_once '../layout.php';

requireRole('cashier');

$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];
$message = '';
$error = '';
$student = null;
$payments = [];
$sections = [];
$subjects = [];

// Handle student search
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['student_id'])) {
    $search_term = trim($_GET['student_id']);
    
    if (!empty($search_term)) {
        // Search for student by ID or partial ID
        $stmt = $pdo->prepare("SELECT si.*, u.user_status 
                              FROM students_info si 
                              JOIN users u ON si.user_id = u.user_id 
                              WHERE si.user_id LIKE ? OR si.name LIKE ?
                              LIMIT 10");
        $stmt->execute(["%$search_term%", "%$search_term%"]);
        $search_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // If only one result, get detailed information
        if (count($search_results) === 1) {
            $student = $search_results[0];
            $student_id = $student['user_id'];
            
            // Get payment information
            $stmt = $pdo->prepare("SELECT * FROM payments WHERE student_id = ? ORDER BY issued_date DESC");
            $stmt->execute([$student_id]);
            $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get section information
            $stmt = $pdo->query("SELECT * FROM sections");
            $all_sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($all_sections as $section) {
                $user_ids = !empty($section['user_id']) ? json_decode($section['user_id'], true) : [];
                if (in_array($student_id, $user_ids)) {
                    $sections[] = $section;
                }
            }
            
            // Get subject information
            $stmt = $pdo->query("SELECT * FROM subjects");
            $all_subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($all_subjects as $subject) {
                $addition = !empty($subject['addition']) ? json_decode($subject['addition'], true) : [];
                $exception = !empty($subject['exception']) ? json_decode($subject['exception'], true) : [];
                
                // Check if student is directly assigned to this subject
                if (in_array($student_id, $addition)) {
                    $subjects[] = $subject;
                    continue;
                }
                
                // Check if student is in a section that has this subject
                $subject_sections = !empty($subject['sections']) ? json_decode($subject['sections'], true) : [];
                foreach ($sections as $section) {
                    if (in_array($section['section_code'], $subject_sections)) {
                        // Check if student is not in exceptions
                        if (!in_array($student_id, $exception)) {
                            $subjects[] = $subject;
                            break;
                        }
                    }
                }
            }
        }
    }
}

// Page title and header
$pageTitle = "Student Search";
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => 'dashboard.php'],
    ['label' => 'Student Search', 'url' => '#', 'active' => true]
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
            <i class="fas fa-search me-1"></i>
            Search Student
        </div>
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-8">
                    <label for="student_id" class="form-label">Student ID or Name</label>
                    <input type="text" class="form-control" id="student_id" name="student_id" 
                           value="<?php echo isset($_GET['student_id']) ? htmlspecialchars($_GET['student_id']) : ''; ?>" 
                           placeholder="Enter student ID (e.g., C12-34-5678-MAN121) or name" required>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-1"></i> Search
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <?php if (isset($_GET['student_id']) && empty($student) && !empty($search_results)): ?>
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-list me-1"></i>
                Search Results
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Program</th>
                                <th>Year Level</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($search_results as $result): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($result['user_id']); ?></td>
                                    <td><?php echo htmlspecialchars($result['name']); ?></td>
                                    <td><?php echo htmlspecialchars($result['program'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($result['year_level'] ?? 'N/A'); ?></td>
                                    <td>
                                        <a href="?student_id=<?php echo urlencode($result['user_id']); ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye me-1"></i> View Details
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php elseif ($student): ?>
        <div class="row">
            <div class="col-xl-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-user me-1"></i>
                        Student Information
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <?php if (!empty($student['profile_picture'])): ?>
                                <img src="<?php echo htmlspecialchars($student['profile_picture']); ?>" 
                                     class="img-fluid rounded-circle" style="width: 150px; height: 150px; object-fit: cover;" 
                                     alt="Profile Picture">
                            <?php else: ?>
                                <div class="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center mx-auto" 
                                     style="width: 150px; height: 150px; font-size: 3rem;">
                                    <i class="fas fa-user"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <h5 class="text-center mb-3"><?php echo htmlspecialchars($student['name']); ?></h5>
                        
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <strong>Student ID:</strong>
                                <span><?php echo htmlspecialchars($student['user_id']); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <strong>Status:</strong>
                                <span class="badge bg-<?php echo $student['user_status'] === 'active' ? 'success' : 'danger'; ?>">
                                    <?php echo ucfirst(htmlspecialchars($student['user_status'])); ?>
                                </span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <strong>Student Type:</strong>
                                <span><?php echo ucfirst(htmlspecialchars($student['student_type'])); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <strong>Program:</strong>
                                <span><?php echo htmlspecialchars($student['program'] ?? 'N/A'); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <strong>Year Level:</strong>
                                <span><?php echo htmlspecialchars($student['year_level'] ?? 'N/A'); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <strong>Student Status:</strong>
                                <span><?php echo ucfirst(htmlspecialchars($student['student_status'])); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <strong>Enrollment Status:</strong>
                                <span><?php echo ucfirst(htmlspecialchars($student['enrollment_status'])); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <strong>Enrollment Date:</strong>
                                <span><?php echo htmlspecialchars($student['enrollment_date'] ?? 'N/A'); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <strong>Email:</strong>
                                <span><?php echo htmlspecialchars($student['email']); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <strong>Phone:</strong>
                                <span><?php echo htmlspecialchars($student['number'] ?? 'N/A'); ?></span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-money-bill-wave me-1"></i>
                        Payment Information
                    </div>
                    <div class="card-body">
                        <?php if (empty($payments)): ?>
                            <div class="alert alert-info">No payment records found for this student.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>Permit #</th>
                                            <th>Amount</th>
                                            <th>Remaining</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                            <th>School Year</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($payments as $payment): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($payment['permit_number']); ?></td>
                                                <td>₱<?php echo number_format($payment['amount'], 2); ?></td>
                                                <td>₱<?php echo number_format($payment['remaining_balance'], 2); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $payment['payment_status'] === 'paid' ? 'success' : 
                                                            ($payment['payment_status'] === 'partial' ? 'warning' : 'danger'); 
                                                    ?>">
                                                        <?php echo ucfirst(htmlspecialchars($payment['payment_status'])); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($payment['issued_date']); ?></td>
                                                <td><?php echo htmlspecialchars($payment['school_year'] ?? 'N/A'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-users me-1"></i>
                                Sections
                            </div>
                            <div class="card-body">
                                <?php if (empty($sections)): ?>
                                    <div class="alert alert-info">No sections found for this student.</div>
                                <?php else: ?>
                                    <ul class="list-group">
                                        <?php foreach ($sections as $section): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <?php echo htmlspecialchars($section['section_code']); ?>
                                                <span class="badge bg-primary rounded-pill">
                                                    Year <?php echo htmlspecialchars($section['year_level'] ?? 'N/A'); ?>
                                                </span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-book me-1"></i>
                                Subjects
                            </div>
                            <div class="card-body">
                                <?php if (empty($subjects)): ?>
                                    <div class="alert alert-info">No subjects found for this student.</div>
                                <?php else: ?>
                                    <ul class="list-group">
                                        <?php foreach ($subjects as $subject): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <?php echo htmlspecialchars($subject['subject_code']); ?>
                                                <span class="badge bg-info rounded-pill">
                                                    <?php echo htmlspecialchars($subject['units']); ?> units
                                                </span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php elseif (isset($_GET['student_id'])): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-1"></i>
            No students found matching your search criteria.
        </div>
    <?php endif; ?>
</div>

<script>
$(document).ready(function() {
    // Add autocomplete for student search
    $("#student_id").on("keyup", function() {
        var value = $(this).val().toLowerCase();
        if (value.length >= 2) {
            $.ajax({
                url: "get_students.php",
                method: "POST",
                data: { search: value },
                dataType: "json",
                success: function(data) {
                    // Implement autocomplete with the returned data
                    // This requires additional JavaScript and CSS
                }
            });
        }
    });
});
</script>

<?php include_once '../layout_footer.php'; ?>