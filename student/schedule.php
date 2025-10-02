<?php
require_once '../init.php';
require_once '../theme.php';
require_once '../layout.php';

requireRole('student');

$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];

// Get current semester sections for this student
$stmt = $pdo->prepare("SELECT s.*, sec.section_code, sec.year_level, sec.program
                       FROM subjects s
                       JOIN sections sec ON JSON_CONTAINS(s.sections, JSON_QUOTE(sec.section_code))
                       WHERE JSON_CONTAINS(sec.user_id, JSON_QUOTE(?)) 
                       AND sec.status = 'active'
                       ORDER BY s.subject_code");
$stmt->execute([$user_id]);
$current_subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get payment status for each subject (prelim, midterm, prefinals, finals)
$payment_types = ['prelim', 'midterm', 'prefinals', 'finals'];
$payment_status = [];

foreach($current_subjects as $subject) {
    foreach($payment_types as $type) {
        $stmt = $pdo->prepare("SELECT payment_status FROM payments 
                               WHERE student_id = ? 
                               AND description LIKE ? 
                               AND description LIKE ?
                               ORDER BY issued_date DESC 
                               LIMIT 1");
        $stmt->execute([$user_id, "%{$type}%", "%{$subject['subject_code']}%"]);
        $status = $stmt->fetchColumn();
        $payment_status[$subject['subject_code']][$type] = $status ?: 'unpaid';
    }
}

// Get previous sections (if any)
$stmt = $pdo->prepare("SELECT DISTINCT sec.section_code, sec.year_level, sec.program, sec.id as section_id
                       FROM sections sec 
                       WHERE JSON_CONTAINS(sec.user_id, JSON_QUOTE(?)) 
                       AND sec.status = 'inactive'
                       ORDER BY sec.year_level DESC");
$stmt->execute([$user_id]);
$previous_sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get student info for current section display
$stmt = $pdo->prepare("SELECT program, year_level, student_type FROM students_info WHERE user_id = ?");
$stmt->execute([$user_id]);
$student_info = $stmt->fetch(PDO::FETCH_ASSOC);

renderPageStart('My Schedule', 'student', 'schedule.php');
?>

<style>
.payment-status-badge {
    font-size: 0.7rem;
    padding: 2px 6px;
}
.subject-card {
    border-left: 4px solid #007bff;
    transition: all 0.3s ease;
}
.subject-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
.payment-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 5px;
    margin-top: 10px;
}
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>My Academic Schedule</h2>
    <div class="text-muted">
        <?php echo $student_info['student_type'] ? ucfirst($student_info['student_type']) . ' Student' : 'Student'; ?>
    </div>
</div>

<!-- Current Semester Info -->
<div class="row mb-4">
    <div class="col-12">
        <div class="alert alert-info">
            <h5 class="alert-heading">
                <i class="fas fa-calendar-alt"></i> Current Semester Information
            </h5>
            <div class="row">
                <div class="col-md-4">
                    <strong>Program:</strong> <?php echo htmlspecialchars($student_info['program'] ?? 'Not Set'); ?>
                </div>
                <div class="col-md-4">
                    <strong>Year Level:</strong> <?php echo $student_info['year_level'] ?? 'Not Set'; ?>
                </div>
                <div class="col-md-4">
                    <strong>Total Subjects:</strong> <?php echo count($current_subjects); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Current Subjects -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-book"></i> Current Subjects
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($current_subjects)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-book-open fa-3x text-muted mb-3"></i>
                        <h5>No subjects enrolled</h5>
                        <p class="text-muted">You are not currently enrolled in any subjects.</p>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach($current_subjects as $subject): ?>
                        <div class="col-md-6 mb-4">
                            <div class="card subject-card h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="card-title mb-0">
                                            <?php echo htmlspecialchars($subject['subject_code']); ?>
                                        </h6>
                                        <span class="badge bg-primary"><?php echo $subject['units']; ?> units</span>
                                    </div>
                                    
                                    <p class="card-text"><?php echo htmlspecialchars($subject['subject_name']); ?></p>
                                    
                                    <?php if ($subject['description']): ?>
                                        <p class="text-muted small"><?php echo htmlspecialchars($subject['description']); ?></p>
                                    <?php endif; ?>
                                    
                                    <div class="mt-3">
                                        <h6 class="small text-muted mb-2">Payment Status:</h6>
                                        <div class="payment-grid">
                                            <?php foreach($payment_types as $type): ?>
                                                <?php 
                                                $status = $payment_status[$subject['subject_code']][$type] ?? 'unpaid';
                                                $badge_class = match($status) {
                                                    'paid' => 'success',
                                                    'partial' => 'warning',
                                                    'unpaid' => 'danger',
                                                    default => 'secondary'
                                                };
                                                ?>
                                                <div class="text-center">
                                                    <small class="d-block text-muted"><?php echo ucfirst($type); ?></small>
                                                    <span class="badge bg-<?php echo $badge_class; ?> payment-status-badge">
                                                        <?php echo ucfirst($status); ?>
                                                    </span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer bg-light">
                                    <small class="text-muted">
                                        <i class="fas fa-layer-group"></i> Section: <?php echo htmlspecialchars($subject['section_code']); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Previous Sections -->
<?php if (!empty($previous_sections)): ?>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-history"></i> Previous Sections
                </h5>
            </div>
            <div class="card-body">
                <div class="accordion" id="previousSectionsAccordion">
                    <?php foreach($previous_sections as $index => $section): ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="heading<?php echo $index; ?>">
                            <button class="accordion-button collapsed" type="button" 
                                    data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $index; ?>" 
                                    aria-expanded="false" aria-controls="collapse<?php echo $index; ?>">
                                <?php echo htmlspecialchars($section['section_code']); ?> - 
                                <?php echo htmlspecialchars($section['program']); ?> 
                                (Year <?php echo $section['year_level']; ?>)
                            </button>
                        </h2>
                        <div id="collapse<?php echo $index; ?>" class="accordion-collapse collapse" 
                             aria-labelledby="heading<?php echo $index; ?>" data-bs-parent="#previousSectionsAccordion">
                            <div class="accordion-body">
                                <?php
                                // Get subjects for this previous section
                                $stmt = $pdo->prepare("SELECT s.* FROM subjects s 
                                                       WHERE JSON_CONTAINS(s.sections, JSON_QUOTE(?))");
                                $stmt->execute([$section['section_code']]);
                                $prev_subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                ?>
                                
                                <?php if (empty($prev_subjects)): ?>
                                    <p class="text-muted">No subjects found for this section.</p>
                                <?php else: ?>
                                    <div class="row">
                                        <?php foreach($prev_subjects as $prev_subject): ?>
                                        <div class="col-md-4 mb-3">
                                            <div class="card border-secondary">
                                                <div class="card-body p-3">
                                                    <h6 class="card-title"><?php echo htmlspecialchars($prev_subject['subject_code']); ?></h6>
                                                    <p class="card-text small"><?php echo htmlspecialchars($prev_subject['subject_name']); ?></p>
                                                    <span class="badge bg-secondary"><?php echo $prev_subject['units']; ?> units</span>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Payment Legend -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h6 class="card-title">Payment Status Legend:</h6>
                <div class="d-flex flex-wrap gap-3">
                    <div>
                        <span class="badge bg-success payment-status-badge">Paid</span>
                        <small class="text-muted ms-1">Fully paid</small>
                    </div>
                    <div>
                        <span class="badge bg-warning payment-status-badge">Partial</span>
                        <small class="text-muted ms-1">Partially paid</small>
                    </div>
                    <div>
                        <span class="badge bg-danger payment-status-badge">Unpaid</span>
                        <small class="text-muted ms-1">Not yet paid</small>
                    </div>
                </div>
                <hr>
                <small class="text-muted">
                    <strong>Note:</strong> Payment status shows your payment for each examination period (Prelim, Midterm, Prefinals, Finals). 
                    Contact the cashier or admin office for payment processing.
                </small>
            </div>
        </div>
    </div>
</div>

<?php renderPageEnd(); ?>
