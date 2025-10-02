<?php
require_once '../init.php';
require_once '../theme.php';
require_once '../layout.php';

requireRole('admin');

$pdo = getDBConnection();
$error = '';
$success = '';

// Handle subject actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $action = $_POST['action'] ?? '';
        
        switch($action) {
            case 'add_subject':
                $subject_code = strtoupper(sanitizeInput($_POST['subject_code'] ?? ''));
                $subject_name = sanitizeInput($_POST['subject_name'] ?? '');
                $units = intval($_POST['units'] ?? 0);
                $description = sanitizeInput($_POST['description'] ?? '');
                
                if (empty($subject_code)) {
                    $error = 'Subject code is required.';
                } elseif (empty($subject_name)) {
                    $error = 'Subject name is required.';
                } elseif ($units <= 0) {
                    $error = 'Units must be greater than 0.';
                } else {
                    // Check if subject code already exists
                    $stmt = $pdo->prepare("SELECT id FROM subjects WHERE subject_code = ?");
                    $stmt->execute([$subject_code]);
                    if ($stmt->fetch()) {
                        $error = 'Subject code already exists.';
                    } else {
                        try {
                            $stmt = $pdo->prepare("INSERT INTO subjects (subject_code, subject_name, units, description, sections, exception, addition) VALUES (?, ?, ?, ?, '[]', '[]', '[]')");
                            $stmt->execute([$subject_code, $subject_name, $units, $description]);
                            
                            logActivity($_SESSION['user_id'], 'Subject Created', "Created subject: {$subject_code} - {$subject_name}");
                            $success = 'Subject added successfully.';
                        } catch(Exception $e) {
                            $error = 'Failed to add subject: ' . $e->getMessage();
                        }
                    }
                }
                break;
                
            case 'edit_subject':
                $subject_id = intval($_POST['subject_id'] ?? 0);
                $subject_name = sanitizeInput($_POST['subject_name'] ?? '');
                $units = intval($_POST['units'] ?? 0);
                $description = sanitizeInput($_POST['description'] ?? '');
                
                if ($subject_id <= 0) {
                    $error = 'Invalid subject ID.';
                } elseif (empty($subject_name)) {
                    $error = 'Subject name is required.';
                } elseif ($units <= 0) {
                    $error = 'Units must be greater than 0.';
                } else {
                    try {
                        // Get old subject info for logging
                        $stmt = $pdo->prepare("SELECT * FROM subjects WHERE id = ?");
                        $stmt->execute([$subject_id]);
                        $old_subject = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        $stmt = $pdo->prepare("UPDATE subjects SET subject_name = ?, units = ?, description = ? WHERE id = ?");
                        $stmt->execute([$subject_name, $units, $description, $subject_id]);
                        
                        logActivity($_SESSION['user_id'], 'Subject Updated', 
                                   "Updated subject {$old_subject['subject_code']}: name from '{$old_subject['subject_name']}' to '{$subject_name}', units from {$old_subject['units']} to {$units}");
                        $success = 'Subject updated successfully.';
                    } catch(Exception $e) {
                        $error = 'Failed to update subject: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'delete_subject':
                $subject_id = intval($_POST['subject_id'] ?? 0);
                
                if ($subject_id <= 0) {
                    $error = 'Invalid subject ID.';
                } else {
                    // Get subject info
                    $stmt = $pdo->prepare("SELECT * FROM subjects WHERE id = ?");
                    $stmt->execute([$subject_id]);
                    $subject = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$subject) {
                        $error = 'Subject not found.';
                    } else {
                        // Check if subject has remaining balances
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM payments p 
                                               JOIN students_info si ON p.student_id = si.user_id 
                                               WHERE p.description LIKE ? AND p.remaining_balance > 0");
                        $stmt->execute(["%{$subject['subject_code']}%"]);
                        $remaining_balances = $stmt->fetchColumn();
                        
                        // Check if subject is used in active sections
                        $sections = json_decode($subject['sections'] ?? '[]', true);
                        $active_sections = 0;
                        if (!empty($sections)) {
                            $placeholders = str_repeat('?,', count($sections) - 1) . '?';
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM sections WHERE section_code IN ($placeholders) AND status = 'active'");
                            $stmt->execute($sections);
                            $active_sections = $stmt->fetchColumn();
                        }
                        
                        if ($remaining_balances > 0) {
                            $error = 'Cannot delete subject: Students have remaining balances for this subject.';
                        } elseif ($active_sections > 0) {
                            $error = 'Cannot delete subject: Subject is currently used in active sections.';
                        } else {
                            try {
                                $stmt = $pdo->prepare("DELETE FROM subjects WHERE id = ?");
                                $stmt->execute([$subject_id]);
                                
                                logActivity($_SESSION['user_id'], 'Subject Deleted', "Deleted subject: {$subject['subject_code']} - {$subject['subject_name']}");
                                $success = 'Subject deleted successfully.';
                            } catch(Exception $e) {
                                $error = 'Failed to delete subject: ' . $e->getMessage();
                            }
                        }
                    }
                }
                break;
        }
    }
}

// Get all subjects with section information
$stmt = $pdo->query("SELECT s.*, 
                     (SELECT COUNT(*) FROM sections sec WHERE JSON_CONTAINS(s.sections, JSON_QUOTE(sec.section_code)) AND sec.status = 'active') as active_sections
                     FROM subjects s 
                     ORDER BY s.subject_code");
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

renderPageStart('Manage Subjects', 'admin', 'manage_subjects.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Manage Subjects</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
        <i class="fas fa-plus"></i> Add Subject
    </button>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
    </div>
<?php endif; ?>

<!-- Subjects Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Subject Code</th>
                        <th>Subject Name</th>
                        <th>Units</th>
                        <th>Active Sections</th>
                        <th>Description</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($subjects as $subject): ?>
                    <tr>
                        <td><code><?php echo htmlspecialchars($subject['subject_code']); ?></code></td>
                        <td><strong><?php echo htmlspecialchars($subject['subject_name']); ?></strong></td>
                        <td><span class="badge bg-primary"><?php echo $subject['units']; ?> units</span></td>
                        <td>
                            <?php if ($subject['active_sections'] > 0): ?>
                                <span class="badge bg-success"><?php echo $subject['active_sections']; ?> active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">None</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($subject['description']): ?>
                                <span title="<?php echo htmlspecialchars($subject['description']); ?>">
                                    <?php echo htmlspecialchars(substr($subject['description'], 0, 50)); ?>
                                    <?php echo strlen($subject['description']) > 50 ? '...' : ''; ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">No description</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group" role="group">
                                <a href="subject_details.php?id=<?php echo $subject['id']; ?>" class="btn btn-sm btn-outline-info">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <button class="btn btn-sm btn-outline-warning" 
                                        onclick="editSubject(<?php echo $subject['id']; ?>, '<?php echo htmlspecialchars($subject['subject_name'], ENT_QUOTES); ?>', <?php echo $subject['units']; ?>, '<?php echo htmlspecialchars($subject['description'], ENT_QUOTES); ?>')">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" 
                                        onclick="deleteSubject(<?php echo $subject['id']; ?>, '<?php echo htmlspecialchars($subject['subject_code'], ENT_QUOTES); ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (empty($subjects)): ?>
            <div class="text-center py-5">
                <i class="fas fa-book fa-3x text-muted mb-3"></i>
                <h5>No subjects found</h5>
                <p class="text-muted">Start by adding your first subject.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Subject Modal -->
<div class="modal fade" id="addSubjectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Subject</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="add_subject">
                    
                    <div class="mb-3">
                        <label for="subject_code" class="form-label">Subject Code</label>
                        <input type="text" class="form-control" id="subject_code" name="subject_code" 
                               placeholder="e.g., MATH101" style="text-transform: uppercase;" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="subject_name" class="form-label">Subject Name</label>
                        <input type="text" class="form-control" id="subject_name" name="subject_name" 
                               placeholder="e.g., College Algebra" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="units" class="form-label">Units</label>
                        <input type="number" class="form-control" id="units" name="units" 
                               min="1" max="10" value="3" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description (Optional)</label>
                        <textarea class="form-control" id="description" name="description" rows="3" 
                                  placeholder="Brief description of the subject"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Subject</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Subject Modal -->
<div class="modal fade" id="editSubjectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Subject</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="edit_subject">
                    <input type="hidden" name="subject_id" id="edit_subject_id">
                    
                    <div class="mb-3">
                        <label for="edit_subject_name" class="form-label">Subject Name</label>
                        <input type="text" class="form-control" id="edit_subject_name" name="subject_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_units" class="form-label">Units</label>
                        <input type="number" class="form-control" id="edit_units" name="units" 
                               min="1" max="10" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description (Optional)</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Update Subject</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editSubject(id, name, units, description) {
    document.getElementById('edit_subject_id').value = id;
    document.getElementById('edit_subject_name').value = name;
    document.getElementById('edit_units').value = units;
    document.getElementById('edit_description').value = description;
    
    new bootstrap.Modal(document.getElementById('editSubjectModal')).show();
}

function deleteSubject(id, code) {
    if (confirm(`Are you sure you want to delete subject "${code}"? This action cannot be undone.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="delete_subject">
            <input type="hidden" name="subject_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Auto uppercase subject code
document.getElementById('subject_code').addEventListener('input', function() {
    this.value = this.value.toUpperCase();
});
</script>

<?php renderPageEnd(); ?>
