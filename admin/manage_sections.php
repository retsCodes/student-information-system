<?php
require_once '../init.php';
require_once '../theme.php';
require_once '../layout.php';

requireRole('admin');

$pdo = getDBConnection();
$error = '';
$success = '';

// Handle section actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $action = $_POST['action'] ?? '';
        
        switch($action) {
            case 'add_section':
                $section_code = strtoupper(sanitizeInput($_POST['section_code'] ?? ''));
                $program = sanitizeInput($_POST['program'] ?? '');
                $year_level = intval($_POST['year_level'] ?? 0);
                $status = $_POST['status'] ?? 'active';
                
                if (empty($section_code)) {
                    $error = 'Section code is required.';
                } elseif (empty($program)) {
                    $error = 'Program is required.';
                } elseif ($year_level <= 0 || $year_level > 6) {
                    $error = 'Year level must be between 1 and 6.';
                } else {
                    // Check if section code already exists
                    $stmt = $pdo->prepare("SELECT id FROM sections WHERE section_code = ?");
                    $stmt->execute([$section_code]);
                    if ($stmt->fetch()) {
                        $error = 'Section code already exists.';
                    } else {
                        try {
                            $stmt = $pdo->prepare("INSERT INTO sections (section_code, user_id, year_level, program, status) VALUES (?, '[]', ?, ?, ?)");
                            $stmt->execute([$section_code, $year_level, $program, $status]);
                            
                            logActivity($_SESSION['user_id'], 'Section Created', "Created section: {$section_code} - {$program} Year {$year_level}");
                            $success = 'Section added successfully.';
                        } catch(Exception $e) {
                            $error = 'Failed to add section: ' . $e->getMessage();
                        }
                    }
                }
                break;
                
            case 'edit_section':
                $section_id = intval($_POST['section_id'] ?? 0);
                $program = sanitizeInput($_POST['program'] ?? '');
                $year_level = intval($_POST['year_level'] ?? 0);
                $status = $_POST['status'] ?? 'active';
                
                if ($section_id <= 0) {
                    $error = 'Invalid section ID.';
                } elseif (empty($program)) {
                    $error = 'Program is required.';
                } elseif ($year_level <= 0 || $year_level > 6) {
                    $error = 'Year level must be between 1 and 6.';
                } else {
                    try {
                        // Get old section info for logging
                        $stmt = $pdo->prepare("SELECT * FROM sections WHERE id = ?");
                        $stmt->execute([$section_id]);
                        $old_section = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        $stmt = $pdo->prepare("UPDATE sections SET program = ?, year_level = ?, status = ? WHERE id = ?");
                        $stmt->execute([$program, $year_level, $status, $section_id]);
                        
                        logActivity($_SESSION['user_id'], 'Section Updated', 
                                   "Updated section {$old_section['section_code']}: program from '{$old_section['program']}' to '{$program}', year level from {$old_section['year_level']} to {$year_level}");
                        $success = 'Section updated successfully.';
                    } catch(Exception $e) {
                        $error = 'Failed to update section: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'assign_subjects':
                $section_id = intval($_POST['section_id'] ?? 0);
                $subject_ids = $_POST['subject_ids'] ?? [];
                
                if ($section_id <= 0) {
                    $error = 'Invalid section ID.';
                } else {
                    // Get section info
                    $stmt = $pdo->prepare("SELECT * FROM sections WHERE id = ?");
                    $stmt->execute([$section_id]);
                    $section = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$section) {
                        $error = 'Section not found.';
                    } else {
                        try {
                            // First, remove this section from all subjects
                            $stmt = $pdo->prepare("SELECT id, sections FROM subjects WHERE JSON_CONTAINS(sections, JSON_QUOTE(?))");
                            $stmt->execute([$section['section_code']]);
                            $current_subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            foreach($current_subjects as $subject) {
                                $sections_array = json_decode($subject['sections'], true);
                                $sections_array = array_values(array_filter($sections_array, function($s) use ($section) {
                                    return $s !== $section['section_code'];
                                }));
                                
                                $stmt = $pdo->prepare("UPDATE subjects SET sections = ? WHERE id = ?");
                                $stmt->execute([json_encode($sections_array), $subject['id']]);
                            }
                            
                            // Now add this section to selected subjects
                            if (!empty($subject_ids)) {
                                $placeholders = str_repeat('?,', count($subject_ids) - 1) . '?';
                                $stmt = $pdo->prepare("SELECT id, subject_code, sections FROM subjects WHERE id IN ($placeholders)");
                                $stmt->execute($subject_ids);
                                $selected_subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                foreach($selected_subjects as $subject) {
                                    $sections_array = json_decode($subject['sections'] ?? '[]', true);
                                    if (!in_array($section['section_code'], $sections_array)) {
                                        $sections_array[] = $section['section_code'];
                                    }
                                    
                                    $stmt = $pdo->prepare("UPDATE subjects SET sections = ? WHERE id = ?");
                                    $stmt->execute([json_encode($sections_array), $subject['id']]);
                                }
                            }
                            
                            logActivity($_SESSION['user_id'], 'Section Subjects Updated', 
                                       "Updated subjects for section {$section['section_code']}: " . count($subject_ids) . " subjects assigned");
                            $success = 'Section subjects updated successfully.';
                        } catch(Exception $e) {
                            $error = 'Failed to update section subjects: ' . $e->getMessage();
                        }
                    }
                }
                break;
                
            case 'delete_section':
                $section_id = intval($_POST['section_id'] ?? 0);
                
                if ($section_id <= 0) {
                    $error = 'Invalid section ID.';
                } else {
                    // Get section info
                    $stmt = $pdo->prepare("SELECT * FROM sections WHERE id = ?");
                    $stmt->execute([$section_id]);
                    $section = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$section) {
                        $error = 'Section not found.';
                    } else {
                        // Check if section has students
                        $students = json_decode($section['user_id'] ?? '[]', true);
                        if (!empty($students)) {
                            $error = 'Cannot delete section: Section has enrolled students.';
                        } else {
                            try {
                                // Remove section from subjects
                                $stmt = $pdo->prepare("SELECT id, sections FROM subjects WHERE JSON_CONTAINS(sections, JSON_QUOTE(?))");
                                $stmt->execute([$section['section_code']]);
                                $subjects_with_section = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                foreach($subjects_with_section as $subject) {
                                    $sections_array = json_decode($subject['sections'], true);
                                    $sections_array = array_values(array_filter($sections_array, function($s) use ($section) {
                                        return $s !== $section['section_code'];
                                    }));
                                    
                                    $stmt = $pdo->prepare("UPDATE subjects SET sections = ? WHERE id = ?");
                                    $stmt->execute([json_encode($sections_array), $subject['id']]);
                                }
                                
                                // Delete section
                                $stmt = $pdo->prepare("DELETE FROM sections WHERE id = ?");
                                $stmt->execute([$section_id]);
                                
                                logActivity($_SESSION['user_id'], 'Section Deleted', "Deleted section: {$section['section_code']} - {$section['program']} Year {$section['year_level']}");
                                $success = 'Section deleted successfully.';
                            } catch(Exception $e) {
                                $error = 'Failed to delete section: ' . $e->getMessage();
                            }
                        }
                    }
                }
                break;
        }
    }
}

// Get filters
$year_filter = $_GET['year'] ?? '';
$program_filter = $_GET['program'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Build query
$where_conditions = [];
$params = [];

if (!empty($year_filter)) {
    $where_conditions[] = "year_level = ?";
    $params[] = $year_filter;
}

if (!empty($program_filter)) {
    $where_conditions[] = "program LIKE ?";
    $params[] = "%{$program_filter}%";
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get sections with student count
$query = "SELECT s.*, 
                 JSON_LENGTH(COALESCE(s.user_id, '[]')) as student_count
          FROM sections s
          {$where_clause}
          ORDER BY s.year_level, s.section_code";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get distinct programs for filter
$stmt = $pdo->query("SELECT DISTINCT program FROM sections ORDER BY program");
$programs = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get all subjects for assignment modal
$stmt = $pdo->query("SELECT id, subject_code, subject_name FROM subjects ORDER BY subject_code");
$all_subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

renderPageStart('Manage Sections', 'admin', 'manage_sections.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Manage Sections</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSectionModal">
        <i class="fas fa-plus"></i> Add Section
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

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="year" class="form-label">Year Level</label>
                <select class="form-select" id="year" name="year">
                    <option value="">All Years</option>
                    <?php for($i = 1; $i <= 6; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo $year_filter == $i ? 'selected' : ''; ?>>
                            Year <?php echo $i; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="program" class="form-label">Program</label>
                <select class="form-select" id="program" name="program">
                    <option value="">All Programs</option>
                    <?php foreach($programs as $program): ?>
                        <option value="<?php echo htmlspecialchars($program); ?>" 
                                <?php echo $program_filter === $program ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($program); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Filter
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Sections Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
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
                    <?php foreach($sections as $section): ?>
                    <tr>
                        <td><code><?php echo htmlspecialchars($section['section_code']); ?></code></td>
                        <td><?php echo htmlspecialchars($section['program']); ?></td>
                        <td><span class="badge bg-info">Year <?php echo $section['year_level']; ?></span></td>
                        <td>
                            <span class="badge bg-primary"><?php echo $section['student_count']; ?> students</span>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $section['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                <?php echo ucfirst($section['status']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="btn-group" role="group">
                                <a href="section_details.php?id=<?php echo $section['id']; ?>" class="btn btn-sm btn-outline-info">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <button class="btn btn-sm btn-outline-success" 
                                        onclick="assignSubjects(<?php echo $section['id']; ?>, '<?php echo htmlspecialchars($section['section_code'], ENT_QUOTES); ?>')"
                                        title="Assign Subjects">
                                    <i class="fas fa-book"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-warning" 
                                        onclick="editSection(<?php echo $section['id']; ?>, '<?php echo htmlspecialchars($section['program'], ENT_QUOTES); ?>', <?php echo $section['year_level']; ?>, '<?php echo $section['status']; ?>')">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" 
                                        onclick="deleteSection(<?php echo $section['id']; ?>, '<?php echo htmlspecialchars($section['section_code'], ENT_QUOTES); ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (empty($sections)): ?>
            <div class="text-center py-5">
                <i class="fas fa-layer-group fa-3x text-muted mb-3"></i>
                <h5>No sections found</h5>
                <p class="text-muted">No sections match your current filters.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Section Modal -->
<div class="modal fade" id="addSectionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Section</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="add_section">
                    
                    <div class="mb-3">
                        <label for="section_code" class="form-label">Section Code</label>
                        <input type="text" class="form-control" id="section_code" name="section_code" 
                               placeholder="e.g., BSIT-1A" style="text-transform: uppercase;" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="program" class="form-label">Program</label>
                        <input type="text" class="form-control" id="program" name="program" 
                               placeholder="e.g., Bachelor of Science in Information Technology" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="year_level" class="form-label">Year Level</label>
                        <select class="form-select" id="year_level" name="year_level" required>
                            <option value="">Select Year Level</option>
                            <?php for($i = 1; $i <= 6; $i++): ?>
                                <option value="<?php echo $i; ?>">Year <?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Section</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Section Modal -->
<div class="modal fade" id="editSectionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Section</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="edit_section">
                    <input type="hidden" name="section_id" id="edit_section_id">
                    
                    <div class="mb-3">
                        <label for="edit_program" class="form-label">Program</label>
                        <input type="text" class="form-control" id="edit_program" name="program" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_year_level" class="form-label">Year Level</label>
                        <select class="form-select" id="edit_year_level" name="year_level" required>
                            <?php for($i = 1; $i <= 6; $i++): ?>
                                <option value="<?php echo $i; ?>">Year <?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_status" class="form-label">Status</label>
                        <select class="form-select" id="edit_status" name="status" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Update Section</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Assign Subjects Modal -->
<div class="modal fade" id="assignSubjectsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Assign Subjects to Section</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="assign_subjects">
                    <input type="hidden" name="section_id" id="assign_section_id">
                    
                    <div class="alert alert-info">
                        <h6 class="alert-heading">Section: <span id="assign_section_name"></span></h6>
                        <p class="mb-0">Select the subjects that should be taught in this section. Previously selected subjects will be updated.</p>
                    </div>
                    
                    <?php if (empty($all_subjects)): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            No subjects available. Please create subjects first.
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <div class="col-12">
                                <h6>Available Subjects:</h6>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="select_all_subjects">
                                    <label class="form-check-label fw-bold" for="select_all_subjects">
                                        Select All
                                    </label>
                                </div>
                                <hr>
                                <div class="row">
                                    <?php foreach($all_subjects as $subject): ?>
                                    <div class="col-md-6 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input subject-checkbox" type="checkbox" 
                                                   name="subject_ids[]" value="<?php echo $subject['id']; ?>" 
                                                   id="subject_<?php echo $subject['id']; ?>">
                                            <label class="form-check-label" for="subject_<?php echo $subject['id']; ?>">
                                                <strong><?php echo htmlspecialchars($subject['subject_code']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($subject['subject_name']); ?></small>
                                            </label>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <?php if (!empty($all_subjects)): ?>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-book"></i> Assign Subjects
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editSection(id, program, yearLevel, status) {
    document.getElementById('edit_section_id').value = id;
    document.getElementById('edit_program').value = program;
    document.getElementById('edit_year_level').value = yearLevel;
    document.getElementById('edit_status').value = status;
    
    new bootstrap.Modal(document.getElementById('editSectionModal')).show();
}

function assignSubjects(sectionId, sectionCode) {
    document.getElementById('assign_section_id').value = sectionId;
    document.getElementById('assign_section_name').textContent = sectionCode;
    
    // Clear all checkboxes first
    document.querySelectorAll('.subject-checkbox').forEach(checkbox => {
        checkbox.checked = false;
    });
    document.getElementById('select_all_subjects').checked = false;
    
    // Load current subjects for this section
    fetch('get_section_subjects.php?section_id=' + sectionId)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.subjects) {
                data.subjects.forEach(subjectId => {
                    const checkbox = document.getElementById('subject_' + subjectId);
                    if (checkbox) {
                        checkbox.checked = true;
                    }
                });
            }
        })
        .catch(error => {
            console.error('Error loading section subjects:', error);
        });
    
    new bootstrap.Modal(document.getElementById('assignSubjectsModal')).show();
}

function deleteSection(id, code) {
    if (confirm(`Are you sure you want to delete section "${code}"? This action cannot be undone.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="delete_section">
            <input type="hidden" name="section_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Auto uppercase section code
document.getElementById('section_code').addEventListener('input', function() {
    this.value = this.value.toUpperCase();
});

// Select all subjects functionality
document.getElementById('select_all_subjects').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.subject-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
    });
});
</script>

<?php renderPageEnd(); ?>
