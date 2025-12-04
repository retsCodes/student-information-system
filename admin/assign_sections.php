<?php
require_once '../init.php';
require_once '../theme.php';
require_once '../layout.php';

requireRole('admin');

$pdo = getDBConnection();
$error = '';
$success = '';

// Get student ID from query parameter
$student_id = $_GET['student_id'] ?? '';

if (empty($student_id)) {
    header('Location: manage_users.php');
    exit;
}

// Get student info
$stmt = $pdo->prepare("SELECT u.user_id, u.name, u.email, si.program, si.year_level, si.student_type 
                       FROM users u 
                       LEFT JOIN students_info si ON u.user_id = si.user_id 
                       WHERE u.user_id = ? AND u.role = 'student'");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    $error = 'Student not found.';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($student_id)) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $section_ids = $_POST['section_ids'] ?? [];
        $subject_ids = $_POST['subject_ids'] ?? [];
        
        if (empty($section_ids)) {
            $error = 'Please select at least one section.';
        } else {
            try {
                $pdo->beginTransaction();
                
                // Remove existing section mappings
                $stmt = $pdo->prepare("DELETE FROM student_sections WHERE student_id = ?");
                $stmt->execute([$student_id]);
                
                // Remove existing subject mappings
                $stmt = $pdo->prepare("DELETE FROM student_subjects WHERE student_id = ?");
                $stmt->execute([$student_id]);
                
                // Insert new section mappings
                $stmt = $pdo->prepare("INSERT INTO student_sections (student_id, section_id) VALUES (?, ?)");
                foreach ($section_ids as $section_id) {
                    $section_id = intval($section_id);
                    if ($section_id > 0) {
                        $stmt->execute([$student_id, $section_id]);
                    }
                }
                
                // Insert selected subjects
                if (!empty($subject_ids)) {
                    $stmt = $pdo->prepare("INSERT INTO student_subjects (student_id, subject_id) VALUES (?, ?)");
                    foreach ($subject_ids as $subject_id) {
                        $subject_id = intval($subject_id);
                        if ($subject_id > 0) {
                            $stmt->execute([$student_id, $subject_id]);
                        }
                    }
                }
                
                // Update student type (regular/irregular)
                $student_type = count($section_ids) > 1 ? 'irregular' : 'regular';
                $stmt = $pdo->prepare("UPDATE students_info SET student_type = ? WHERE user_id = ?");
                $stmt->execute([$student_type, $student_id]);
                
                $pdo->commit();
                
                logActivity($_SESSION['user_id'], 'Assign Sections/Subjects', 
                           "Assigned " . count($section_ids) . " sections and " . count($subject_ids) . " subjects to student {$student_id}");
                $success = 'Sections and subjects assigned successfully. Student marked as ' . $student_type . '.';
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Failed to assign sections and subjects: ' . $e->getMessage();
            }
        }
    }
}

// Get all active sections
$sections = $pdo->query("
    SELECT id, section_code, section_name, program, year_level, 
           CONCAT(section_code, ' - ', program, ' (Year ', year_level, ')') AS section_label 
    FROM sections 
    WHERE status = 'active'
    ORDER BY program, year_level, section_code
")->fetchAll(PDO::FETCH_ASSOC);

// Get all subjects
$subjects = $pdo->query("SELECT id, subject_code, subject_name, units, description FROM subjects ORDER BY subject_code")->fetchAll(PDO::FETCH_ASSOC);

// Build section-subjects mapping
$sectionSubjectsMap = [];
$subjectDetailsMap = [];

foreach ($subjects as $subject) {
    $subjectDetailsMap[$subject['id']] = $subject;
    
    $stmt = $pdo->prepare("SELECT sections FROM subjects WHERE id = ?");
    $stmt->execute([$subject['id']]);
    $sections_json = $stmt->fetchColumn();
    
    if ($sections_json) {
        $section_codes = json_decode($sections_json, true) ?? [];
        foreach ($section_codes as $section_code) {
            foreach ($sections as $section) {
                if ($section['section_code'] === $section_code) {
                    $sectionSubjectsMap[$section['id']][] = $subject['id'];
                    break;
                }
            }
        }
    }
}

// Get current assignments
$current_section_ids = [];
$current_subject_ids = [];

if ($student_id) {
    $stmt = $pdo->prepare("SELECT section_id FROM student_sections WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $current_section_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $stmt = $pdo->prepare("SELECT subject_id FROM student_subjects WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $current_subject_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

renderPageStart('Assign Sections & Subjects', 'admin', 'assign_sections.php');
?>

<style>
.section-card {
    border: 2px solid #e9ecef;
    border-radius: 10px;
    transition: all 0.3s ease;
    margin-bottom: 20px;
}
.section-card:hover {
    border-color: #007bff;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.section-card.selected {
    border-color: #28a745;
    background-color: #f8fff9;
}
.section-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 15px 20px;
    border-radius: 8px 8px 0 0;
    cursor: pointer;
}
.subject-list {
    padding: 20px;
    max-height: 400px;
    overflow-y: auto;
}
.subject-item {
    border: 1px solid #e9ecef;
    border-radius: 5px;
    padding: 12px;
    margin-bottom: 10px;
    background: white;
    transition: all 0.3s ease;
}
.subject-item:hover {
    border-color: #007bff;
    background: #f8f9fa;
}
.subject-item.selected {
    border-color: #28a745;
    background: #f0fff4;
}
.select-all-section {
    background: #f8f9fa;
    padding: 10px 15px;
    border-bottom: 1px solid #dee2e6;
    font-weight: bold;
}
.student-info-card {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    color: white;
    border: none;
}
.checkbox-group {
    max-height: 300px;
    overflow-y: auto;
}
</style>

<div class="container-fluid">
    <!-- Student Information -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card student-info-card shadow">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h3 class="card-title mb-1"><?php echo htmlspecialchars($student['name']); ?></h3>
                            <p class="card-text mb-1">
                                <strong>Student ID:</strong> <?php echo htmlspecialchars($student_id); ?> | 
                                <strong>Program:</strong> <?php echo htmlspecialchars($student['program'] ?? 'Not Set'); ?> | 
                                <strong>Year Level:</strong> <?php echo $student['year_level'] ?? 'Not Set'; ?>
                            </p>
                            <p class="card-text mb-0">
                                <strong>Current Status:</strong> 
                                <span class="badge bg-<?php echo ($student['student_type'] ?? 'regular') === 'regular' ? 'success' : 'warning'; ?>">
                                    <?php echo ucfirst($student['student_type'] ?? 'regular'); ?> Student
                                </span>
                            </p>
                        </div>
                        <div class="col-md-4 text-end">
                            <a href="manage_users.php" class="btn btn-light">
                                <i class="fas fa-arrow-left me-2"></i>Back to Users
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
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

    <form method="POST" id="assignForm">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        
        <div class="row">
            <!-- Sections Selection -->
            <div class="col-lg-4">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-layer-group me-2"></i>Select Sections
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="selectAllSections">
                                <label class="form-check-label fw-bold" for="selectAllSections">
                                    Select All Sections
                                </label>
                            </div>
                        </div>
                        
                        <div class="checkbox-group">
                            <?php foreach($sections as $section): ?>
                                <div class="form-check mb-3 p-3 border rounded">
                                    <input class="form-check-input section-checkbox" 
                                           type="checkbox" 
                                           name="section_ids[]" 
                                           value="<?php echo $section['id']; ?>" 
                                           id="section_<?php echo $section['id']; ?>"
                                           <?php echo in_array($section['id'], $current_section_ids) ? 'checked' : ''; ?>
                                           data-section-id="<?php echo $section['id']; ?>">
                                    <label class="form-check-label fw-bold" for="section_<?php echo $section['id']; ?>">
                                        <?php echo htmlspecialchars($section['section_label']); ?>
                                    </label>
                                    <?php if (!empty($section['section_name'])): ?>
                                        <div class="text-muted small">
                                            <?php echo htmlspecialchars($section['section_name']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="text-muted small mt-1">
                                        Subjects: <?php echo count($sectionSubjectsMap[$section['id']] ?? []); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if (empty($sections)): ?>
                            <div class="text-center py-4 text-muted">
                                <i class="fas fa-layer-group fa-2x mb-2"></i>
                                <p>No sections available</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Subjects Selection -->
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header bg-success text-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-book me-2"></i>Select Subjects from Chosen Sections
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="selectAllSubjects">
                                <label class="form-check-label fw-bold" for="selectAllSubjects">
                                    Select All Subjects from Selected Sections
                                </label>
                            </div>
                        </div>

                        <div id="subjectsContainer">
                            <?php if (empty($current_section_ids)): ?>
                                <div class="text-center py-5 text-muted">
                                    <i class="fas fa-book-open fa-3x mb-3"></i>
                                    <h5>No Sections Selected</h5>
                                    <p>Please select sections from the left panel to view available subjects.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach($current_section_ids as $section_id): 
                                    $section = array_filter($sections, fn($s) => $s['id'] == $section_id);
                                    $section = reset($section);
                                    $section_subjects = $sectionSubjectsMap[$section_id] ?? [];
                                ?>
                                    <?php if (!empty($section_subjects)): ?>
                                        <div class="section-card selected">
                                            <div class="section-header">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <h6 class="mb-0">
                                                        <i class="fas fa-layer-group me-2"></i>
                                                        <?php echo htmlspecialchars($section['section_label']); ?>
                                                    </h6>
                                                    <div>
                                                        <button type="button" class="btn btn-sm btn-light select-all-section-btn" 
                                                                data-section-id="<?php echo $section_id; ?>">
                                                            <i class="fas fa-check-double me-1"></i>Select All
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="subject-list">
                                                <div class="row">
                                                    <?php foreach($section_subjects as $subject_id): 
                                                        $subject = $subjectDetailsMap[$subject_id] ?? null;
                                                        if (!$subject) continue;
                                                    ?>
                                                        <div class="col-md-6 mb-3">
                                                            <div class="subject-item <?php echo in_array($subject_id, $current_subject_ids) ? 'selected' : ''; ?>">
                                                                <div class="form-check">
                                                                    <input class="form-check-input subject-checkbox" 
                                                                           type="checkbox" 
                                                                           name="subject_ids[]" 
                                                                           value="<?php echo $subject_id; ?>" 
                                                                           id="subject_<?php echo $subject_id; ?>_<?php echo $section_id; ?>"
                                                                           data-section-id="<?php echo $section_id; ?>"
                                                                           <?php echo in_array($subject_id, $current_subject_ids) ? 'checked' : ''; ?>>
                                                                    <label class="form-check-label fw-bold" for="subject_<?php echo $subject_id; ?>_<?php echo $section_id; ?>">
                                                                        <?php echo htmlspecialchars($subject['subject_code']); ?>
                                                                    </label>
                                                                    <div class="text-muted small">
                                                                        <?php echo htmlspecialchars($subject['subject_name']); ?>
                                                                    </div>
                                                                    <div class="d-flex justify-content-between align-items-center mt-2">
                                                                        <span class="badge bg-primary"><?php echo $subject['units']; ?> units</span>
                                                                        <?php if ($subject['description']): ?>
                                                                            <button type="button" class="btn btn-sm btn-outline-info" 
                                                                                    data-bs-toggle="tooltip" 
                                                                                    title="<?php echo htmlspecialchars($subject['description']); ?>">
                                                                                <i class="fas fa-info-circle"></i>
                                                                            </button>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            No subjects available for <?php echo htmlspecialchars($section['section_label']); ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Summary and Actions -->
                <div class="card shadow mt-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Assignment Summary</h6>
                                <div id="summaryInfo">
                                    <p class="text-muted">Select sections and subjects to see summary</p>
                                </div>
                            </div>
                            <div class="col-md-6 text-end">
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <a href="manage_users.php" class="btn btn-secondary me-2">
                                        <i class="fas fa-times me-2"></i>Cancel
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Save Assignments
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sectionCheckboxes = document.querySelectorAll('.section-checkbox');
    const subjectCheckboxes = document.querySelectorAll('.subject-checkbox');
    const selectAllSections = document.getElementById('selectAllSections');
    const selectAllSubjects = document.getElementById('selectAllSubjects');
    const subjectsContainer = document.getElementById('subjectsContainer');
    const summaryInfo = document.getElementById('summaryInfo');

    // Load subjects when sections are selected
    function loadSubjectsForSections() {
        const selectedSections = Array.from(sectionCheckboxes).filter(cb => cb.checked).map(cb => cb.value);
        
        if (selectedSections.length === 0) {
            subjectsContainer.innerHTML = `
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-book-open fa-3x mb-3"></i>
                    <h5>No Sections Selected</h5>
                    <p>Please select sections from the left panel to view available subjects.</p>
                </div>
            `;
            updateSummary();
            return;
        }

        // Show loading state
        subjectsContainer.innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Loading subjects...</p>
            </div>
        `;

        // Simulate loading (in real implementation, this would be an AJAX call)
        setTimeout(() => {
            const formData = new FormData();
            formData.append('student_id', '<?php echo $student_id; ?>');
            formData.append('section_ids', JSON.stringify(selectedSections));
            formData.append('current_subject_ids', JSON.stringify(<?php echo json_encode($current_subject_ids); ?>));

            fetch('get_subjects_for_sections.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                subjectsContainer.innerHTML = html;
                reinitializeEventListeners();
                updateSummary();
            })
            .catch(error => {
                subjectsContainer.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Error loading subjects. Please try again.
                    </div>
                `;
            });
        }, 500);
    }

    function reinitializeEventListeners() {
        // Re-attach event listeners to new subject checkboxes
        document.querySelectorAll('.subject-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', updateSummary);
        });

        // Re-attach event listeners to select all section buttons
        document.querySelectorAll('.select-all-section-btn').forEach(button => {
            button.addEventListener('click', function() {
                const sectionId = this.dataset.sectionId;
                const sectionSubjects = document.querySelectorAll(`.subject-checkbox[data-section-id="${sectionId}"]`);
                const allChecked = Array.from(sectionSubjects).every(cb => cb.checked);
                
                sectionSubjects.forEach(cb => {
                    cb.checked = !allChecked;
                    cb.dispatchEvent(new Event('change'));
                });
            });
        });

        // Initialize tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }

    function updateSummary() {
        const selectedSections = Array.from(sectionCheckboxes).filter(cb => cb.checked);
        const selectedSubjects = Array.from(document.querySelectorAll('.subject-checkbox:checked'));
        
        const sectionsCount = selectedSections.length;
        const subjectsCount = selectedSubjects.length;
        
        let studentType = 'regular';
        if (sectionsCount > 1) {
            studentType = 'irregular';
        }
        
        let summaryHTML = '';
        if (sectionsCount === 0) {
            summaryHTML = '<p class="text-muted">Select sections and subjects to see summary</p>';
        } else {
            summaryHTML = `
                <div class="mb-2">
                    <strong>Sections Selected:</strong> 
                    <span class="badge bg-primary">${sectionsCount}</span>
                </div>
                <div class="mb-2">
                    <strong>Subjects Selected:</strong> 
                    <span class="badge bg-success">${subjectsCount}</span>
                </div>
                <div class="mb-2">
                    <strong>Student Type:</strong> 
                    <span class="badge bg-${studentType === 'regular' ? 'success' : 'warning'}">${studentType}</span>
                </div>
            `;
        }
        
        summaryInfo.innerHTML = summaryHTML;
    }

    // Event listeners
    sectionCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            loadSubjectsForSections();
            updateSummary();
        });
    });

    selectAllSections.addEventListener('change', function() {
        sectionCheckboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        loadSubjectsForSections();
        updateSummary();
    });

    selectAllSubjects.addEventListener('change', function() {
        document.querySelectorAll('.subject-checkbox').forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        updateSummary();
    });

    // Initial load
    updateSummary();
    
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<?php renderPageEnd(); ?>