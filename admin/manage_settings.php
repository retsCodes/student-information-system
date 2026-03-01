<?php
require_once '../init.php';
require_once '../theme.php';
require_once '../layout.php';

requireRole('admin');

$pdo = getDBConnection();
$error = '';
$success = '';

// Handle setting updates
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'update_settings') {
            try {
                $pdo->beginTransaction();
                
                foreach ($_POST['settings'] as $name => $value) {
                    $stmt = $pdo->prepare("
                        UPDATE settings 
                        SET value = ?, updated_at = NOW(), updated_by = ? 
                        WHERE name = ? AND is_editable = TRUE
                    ");
                    $stmt->execute([$value, $_SESSION['user_id'], $name]);
                }
                
                $pdo->commit();
                $success = 'Settings updated successfully.';
                logActivity($_SESSION['user_id'], 'Settings Updated', 'Updated multiple settings');
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Failed to update settings: ' . $e->getMessage();
            }
        }
    }
}

// Get all settings grouped by category
$stmt = $pdo->query("
    SELECT * FROM settings 
    ORDER BY category, name
");
$all_settings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by category
$settings_by_category = [];
foreach ($all_settings as $setting) {
    $settings_by_category[$setting['category']][] = $setting;
}

renderPageStart('Manage Settings', 'admin', 'manage_settings.php');
?>

<style>
.settings-category {
    margin-bottom: 30px;
}
.setting-card {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 10px;
    background: white;
}
.setting-card.disabled {
    background: #f8f9fa;
    color: #6c757d;
}
.setting-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}
.setting-name {
    font-weight: 600;
    color: #495057;
}
.setting-category-badge {
    font-size: 0.8em;
    padding: 2px 8px;
    border-radius: 10px;
}
</style>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-cog me-2"></i>System Settings</h2>
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

    <form method="POST" id="settingsForm">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="action" value="update_settings">
        
        <?php foreach ($settings_by_category as $category => $settings): ?>
        <div class="settings-category">
            <div class="card mb-3">
                <div class="card-header bg-<?php 
                    echo $category === 'payment' ? 'primary' : 
                         ($category === 'academic' ? 'success' : 
                         ($category === 'general' ? 'info' : 'secondary')); 
                ?> text-white">
                    <h5 class="mb-0">
                        <?php echo ucfirst($category); ?> Settings
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($settings as $setting): ?>
                        <div class="col-md-6 mb-3">
                            <div class="setting-card <?php echo !$setting['is_editable'] ? 'disabled' : ''; ?>">
                                <div class="setting-header">
                                    <div class="setting-name"><?php echo htmlspecialchars($setting['name']); ?></div>
                                    <span class="badge setting-category-badge bg-light text-dark">
                                        <?php echo htmlspecialchars($setting['category']); ?>
                                    </span>
                                </div>
                                
                                <?php if ($setting['description']): ?>
                                <div class="text-muted small mb-2">
                                    <?php echo htmlspecialchars($setting['description']); ?>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($setting['is_editable']): ?>
                                    <?php if (strpos($setting['name'], 'price') !== false || strpos($setting['name'], 'fee') !== false || strpos($setting['name'], 'amount') !== false): ?>
                                    <div class="input-group">
                                        <span class="input-group-text">₱</span>
                                        <input type="number" 
                                               name="settings[<?php echo htmlspecialchars($setting['name']); ?>]" 
                                               value="<?php echo htmlspecialchars($setting['value']); ?>" 
                                               class="form-control" 
                                               step="0.01" 
                                               min="0"
                                               required>
                                    </div>
                                    <?php else: ?>
                                    <input type="text" 
                                           name="settings[<?php echo htmlspecialchars($setting['name']); ?>]" 
                                           value="<?php echo htmlspecialchars($setting['value']); ?>" 
                                           class="form-control" 
                                           <?php echo $setting['is_editable'] ? '' : 'readonly'; ?>>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <input type="text" 
                                           value="<?php echo htmlspecialchars($setting['value']); ?>" 
                                           class="form-control" 
                                           readonly>
                                    <small class="text-muted">This setting cannot be edited</small>
                                <?php endif; ?>
                                
                                <?php if ($setting['updated_at']): ?>
                                <div class="text-end mt-2">
                                    <small class="text-muted">
                                        Last updated: <?php echo date('M j, Y', strtotime($setting['updated_at'])); ?>
                                    </small>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary" onclick="resetForm()">
                        <i class="fas fa-undo"></i> Reset Changes
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save All Settings
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
function resetForm() {
    if (confirm('Reset all changes? This will revert all settings to their current values.')) {
        document.getElementById('settingsForm').reset();
    }
}

// Auto-save feature (optional)
let autoSaveTimer;
const form = document.getElementById('settingsForm');
const inputs = form.querySelectorAll('input, select, textarea');

inputs.forEach(input => {
    input.addEventListener('change', function() {
        clearTimeout(autoSaveTimer);
        autoSaveTimer = setTimeout(() => {
            // Optional: Implement auto-save here
        }, 1000);
    });
});
</script>

<?php renderPageEnd(); ?>