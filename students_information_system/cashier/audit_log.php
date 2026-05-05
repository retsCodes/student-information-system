<?php
require_once '../init.php';
require_once '../theme.php';
require_once '../layout.php';

requireRole('cashier');

$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];

// Get filters
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';

// =======================================================
// PAGINATION SETUP
// =======================================================
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 20;

// Validate per_page values
$allowed_per_page = [10, 20, 50, 100];
if (!in_array($per_page, $allowed_per_page)) {
    $per_page = 20;
}

$offset = ($page - 1) * $per_page;

// Build WHERE conditions
$where_conditions = ["(al.user_id = ? OR al.description LIKE ?)"];
$params = [$user_id, "%{$user_id}%"];

if (!empty($date_from)) {
    $where_conditions[] = "DATE(al.created_at) >= ?";
    $params[] = $date_from;
}
if (!empty($date_to)) {
    $where_conditions[] = "DATE(al.created_at) <= ?";
    $params[] = $date_to;
}
if (!empty($search)) {
    $where_conditions[] = "(al.description LIKE ? OR al.action LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total
              FROM activity_logs al
              JOIN users u ON al.user_id = u.user_id
              {$where_clause}";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $per_page);

// Get paginated results
$query = "SELECT al.*, u.name as user_name, u.role as user_role
          FROM activity_logs al
          JOIN users u ON al.user_id = u.user_id
          {$where_clause}
          ORDER BY al.created_at DESC
          LIMIT " . intval($per_page) . " OFFSET " . intval($offset);

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistics
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM activity_logs WHERE user_id = ?");
$stmt->execute([$user_id]);
$stats['total_activities'] = $stmt->fetch()['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM activity_logs WHERE user_id = ? AND DATE(created_at) = CURDATE()");
$stmt->execute([$user_id]);
$stats['today_activities'] = $stmt->fetch()['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM payments WHERE issued_by = ?");
$stmt->execute([$user_id]);
$stats['total_payments'] = $stmt->fetch()['total'];

renderPageStart('My Audit Log', 'cashier', 'audit_log.php');
?>

<style>
.log-card {
    border-left: 4px solid #28a745;
    transition: all 0.3s ease;
    margin-bottom: 15px;
}
.log-card:hover {
    transform: translateX(5px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.log-card.other-user {
    border-left-color: #ffc107;
}
.pagination {
    flex-wrap: wrap;
    gap: 5px;
}
.pagination .page-item {
    margin: 2px;
}
.pagination .page-link {
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
}
@media (max-width: 768px) {
    .pagination {
        justify-content: center;
    }
    .pagination .page-link {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
    }
}
</style>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
    <h2>My Audit Log</h2>
    <div>
        <button class="btn btn-outline-secondary" onclick="refreshLogs()">
            <i class="fas fa-sync-alt"></i> Refresh
        </button>
    </div>
</div>

<!-- Statistics -->
<div class="stats-card-container mb-4">
    <?php echo renderStatsCard('Total Activities', number_format($stats['total_activities']), 'fas fa-list', 'primary'); ?>
    <?php echo renderStatsCard("Today's Activities", $stats['today_activities'], 'fas fa-calendar-day', 'success'); ?>
    <?php echo renderStatsCard('Payments Processed', number_format($stats['total_payments']), 'fas fa-money-bill-wave', 'info'); ?>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" id="filterForm" class="row g-3">
            <input type="hidden" name="page" value="1">
            <input type="hidden" name="per_page" id="per_page_input" value="<?php echo $per_page; ?>">
            <div class="col-md-3">
                <label class="form-label">Date From</label>
                <input type="date" class="form-control" name="date_from" id="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Date To</label>
                <input type="date" class="form-control" name="date_to" id="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <input type="text" class="form-control" name="search" id="search_input" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by action or description...">
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
        </form>
    </div>
</div>

<!-- Activity Logs Container -->
<div id="logsContainer">
    <!-- Per Page Selector - Top Right -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
        <div class="text-muted small mb-2 mb-md-0" id="showingInfo">
            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total_records); ?> of <?php echo $total_records; ?> logs
        </div>
        <div>
            <div class="d-flex align-items-center gap-2">
                <label class="text-muted small mb-0">Show:</label>
                <select class="form-select form-select-sm" id="per_page_select" style="width: auto;">
                    <?php foreach ([10, 20, 50, 100] as $option): ?>
                        <option value="<?php echo $option; ?>" <?php echo $per_page == $option ? 'selected' : ''; ?>><?php echo $option; ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="text-muted small">per page</span>
            </div>
        </div>
    </div>

    <?php if (empty($logs)): ?>
        <div class="card"><div class="card-body text-center py-5">
            <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
            <h5>No activities found</h5>
            <p class="text-muted">Try adjusting your filters</p>
        </div></div>
    <?php else: ?>
        <div id="logsList">
            <?php foreach($logs as $log): ?>
            <?php $is_my = ($log['user_id'] === $user_id); ?>
            <div class="card log-card <?php echo $is_my ? '' : 'other-user'; ?>">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <h6 class="mb-2"><?php echo htmlspecialchars($log['action']); ?></h6>
                            <div><?php echo nl2br(htmlspecialchars($log['description'])); ?></div>
                            <small class="text-muted">
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($log['user_name']); ?> (<?php echo ucfirst($log['user_role']); ?>)
                            </small>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="text-muted"><?php echo date('M j, Y', strtotime($log['created_at'])); ?></div>
                            <div class="text-muted"><?php echo date('g:i A', strtotime($log['created_at'])); ?></div>
                            <small class="text-muted">ID: <?php echo htmlspecialchars($log['log_id']); ?></small>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center" id="pagination">
                <!-- First page -->
                <?php if ($page > 1): ?>
                    <li class="page-item"><a class="page-link" href="#" data-page="1">&laquo;&laquo;</a></li>
                <?php else: ?>
                    <li class="page-item disabled"><span class="page-link">&laquo;&laquo;</span></li>
                <?php endif; ?>
                
                <!-- Previous -->
                <?php if ($page > 1): ?>
                    <li class="page-item"><a class="page-link" href="#" data-page="<?php echo $page - 1; ?>">&laquo;</a></li>
                <?php else: ?>
                    <li class="page-item disabled"><span class="page-link">&laquo;</span></li>
                <?php endif; ?>
                
                <!-- Page numbers -->
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                if ($start_page > 1): ?>
                    <li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>
                    <?php if ($start_page > 2): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <?php if ($i == $page): ?>
                        <li class="page-item active"><span class="page-link"><?php echo $i; ?></span></li>
                    <?php else: ?>
                        <li class="page-item"><a class="page-link" href="#" data-page="<?php echo $i; ?>"><?php echo $i; ?></a></li>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>
                    <li class="page-item"><a class="page-link" href="#" data-page="<?php echo $total_pages; ?>"><?php echo $total_pages; ?></a></li>
                <?php endif; ?>
                
                <!-- Next -->
                <?php if ($page < $total_pages): ?>
                    <li class="page-item"><a class="page-link" href="#" data-page="<?php echo $page + 1; ?>">&raquo;</a></li>
                <?php else: ?>
                    <li class="page-item disabled"><span class="page-link">&raquo;</span></li>
                <?php endif; ?>
                
                <!-- Last page -->
                <?php if ($page < $total_pages): ?>
                    <li class="page-item"><a class="page-link" href="#" data-page="<?php echo $total_pages; ?>">&raquo;&raquo;</a></li>
                <?php else: ?>
                    <li class="page-item disabled"><span class="page-link">&raquo;&raquo;</span></li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
async function loadLogs(page = 1, perPage = null, filters = {}) {
    try {
        showLoading();
        
        // Use current values if not specified
        const currentPerPage = perPage || document.getElementById('per_page_select')?.value || <?php echo $per_page; ?>;
        const dateFrom = filters.date_from || document.getElementById('date_from')?.value || '';
        const dateTo = filters.date_to || document.getElementById('date_to')?.value || '';
        const search = filters.search || document.getElementById('search_input')?.value || '';
        
        // Build URL with parameters
        const url = new URL(window.location.href);
        url.searchParams.set('page', page);
        url.searchParams.set('per_page', currentPerPage);
        if (dateFrom) url.searchParams.set('date_from', dateFrom);
        if (dateTo) url.searchParams.set('date_to', dateTo);
        if (search) url.searchParams.set('search', search);
        
        // Fetch data
        const response = await fetch(url.toString());
        const html = await response.text();
        
        // Parse the HTML response
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        
        // Extract the logs container content
        const newLogsContainer = doc.getElementById('logsContainer');
        if (newLogsContainer) {
            document.getElementById('logsContainer').innerHTML = newLogsContainer.innerHTML;
        }
        
        // Update URL without reloading
        window.history.pushState({}, '', url.toString());
        
        // Re-attach event listeners
        attachEventListeners();
        
    } catch (error) {
        console.error('Error loading logs:', error);
        showError('Failed to load logs. Please refresh the page.');
    } finally {
        hideLoading();
    }
}

// Function to load logs with AJAX (alternative method using fetch API)
async function loadLogsAjax(page = 1, perPage = null) {
    try {
        showLoading();
        
        const currentPerPage = perPage || document.getElementById('per_page_select')?.value || <?php echo $per_page; ?>;
        const dateFrom = document.getElementById('date_from')?.value || '';
        const dateTo = document.getElementById('date_to')?.value || '';
        const search = document.getElementById('search_input')?.value || '';
        
        const formData = new FormData();
        formData.append('action', 'get_paginated_audit_logs');
        formData.append('page', page);
        formData.append('per_page', currentPerPage);
        formData.append('date_from', dateFrom);
        formData.append('date_to', dateTo);
        formData.append('search', search);
        
        const response = await fetch('../admin/ajax_handler.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            renderLogs(data);
        } else {
            showError(data.message);
        }
    } catch (error) {
        console.error('Error loading logs:', error);
        showError('Failed to load logs');
    } finally {
        hideLoading();
    }
}

// Render logs from AJAX response
function renderLogs(data) {
    const container = document.getElementById('logsContainer');
    if (!container) return;
    
    // Build logs HTML
    let logsHtml = '';
    
    // Per page selector and info
    logsHtml += `
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
            <div class="text-muted small mb-2 mb-md-0">
                Showing ${data.pagination.showing_start} to ${data.pagination.showing_end} of ${data.pagination.total_records.toLocaleString()} logs
            </div>
            <div>
                <div class="d-flex align-items-center gap-2">
                    <label class="text-muted small mb-0">Show:</label>
                    <select class="form-select form-select-sm per-page-select" style="width: auto;">
                        ${[10, 20, 50, 100].map(opt => 
                            `<option value="${opt}" ${data.pagination.per_page == opt ? 'selected' : ''}>${opt}</option>`
                        ).join('')}
                    </select>
                    <span class="text-muted small">per page</span>
                </div>
            </div>
        </div>
    `;
    
    if (data.data.length === 0) {
        logsHtml += `
            <div class="card"><div class="card-body text-center py-5">
                <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                <h5>No activities found</h5>
                <p class="text-muted">Try adjusting your filters</p>
            </div></div>
        `;
    } else {
        logsHtml += `<div id="logsList">`;
        data.data.forEach(log => {
            const isMy = log.user_id === '<?php echo $user_id; ?>';
            logsHtml += `
                <div class="card log-card ${isMy ? '' : 'other-user'}">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <h6 class="mb-2">${escapeHtml(log.action)}</h6>
                                <div>${escapeHtml(log.description).replace(/\n/g, '<br>')}</div>
                                <small class="text-muted">
                                    <i class="fas fa-user"></i> ${escapeHtml(log.user_name)} (${escapeHtml(log.user_role)})
                                </small>
                            </div>
                            <div class="col-md-4 text-end">
                                <div class="text-muted">${new Date(log.created_at).toLocaleDateString('en-US', {month: 'short', day: 'numeric', year: 'numeric'})}</div>
                                <div class="text-muted">${new Date(log.created_at).toLocaleTimeString('en-US', {hour: '2-digit', minute: '2-digit'})}</div>
                                <small class="text-muted">ID: ${escapeHtml(log.log_id)}</small>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        logsHtml += `</div>`;
        
        // Add pagination
        logsHtml += renderPaginationHTML(data.pagination);
    }
    
    container.innerHTML = logsHtml;
    attachEventListeners();
}

// Render pagination HTML
function renderPaginationHTML(pagination) {
    if (pagination.total_pages <= 1) return '';
    
    let html = '<nav class="mt-4"><ul class="pagination justify-content-center">';
    
    // First
    if (pagination.current_page > 1) {
        html += `<li class="page-item"><a class="page-link" href="#" data-page="1">&laquo;&laquo;</a></li>`;
    } else {
        html += `<li class="page-item disabled"><span class="page-link">&laquo;&laquo;</span></li>`;
    }
    
    // Previous
    if (pagination.current_page > 1) {
        html += `<li class="page-item"><a class="page-link" href="#" data-page="${pagination.current_page - 1}">&laquo;</a></li>`;
    } else {
        html += `<li class="page-item disabled"><span class="page-link">&laquo;</span></li>`;
    }
    
    // Page numbers
    let start = Math.max(1, pagination.current_page - 2);
    let end = Math.min(pagination.total_pages, pagination.current_page + 2);
    
    if (start > 1) {
        html += `<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>`;
        if (start > 2) html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
    }
    
    for (let i = start; i <= end; i++) {
        const active = i === pagination.current_page ? 'active' : '';
        html += `<li class="page-item ${active}"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`;
    }
    
    if (end < pagination.total_pages) {
        if (end < pagination.total_pages - 1) html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        html += `<li class="page-item"><a class="page-link" href="#" data-page="${pagination.total_pages}">${pagination.total_pages}</a></li>`;
    }
    
    // Next
    if (pagination.current_page < pagination.total_pages) {
        html += `<li class="page-item"><a class="page-link" href="#" data-page="${pagination.current_page + 1}">&raquo;</a></li>`;
    } else {
        html += `<li class="page-item disabled"><span class="page-link">&raquo;</span></li>`;
    }
    
    // Last
    if (pagination.current_page < pagination.total_pages) {
        html += `<li class="page-item"><a class="page-link" href="#" data-page="${pagination.total_pages}">&raquo;&raquo;</a></li>`;
    } else {
        html += `<li class="page-item disabled"><span class="page-link">&raquo;&raquo;</span></li>`;
    }
    
    html += '</ul></nav>';
    return html;
}

// Helper function to escape HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Show loading indicator
function showLoading() {
    const container = document.getElementById('logsContainer');
    if (container) {
        container.style.opacity = '0.5';
        container.style.pointerEvents = 'none';
    }
}

// Hide loading indicator
function hideLoading() {
    const container = document.getElementById('logsContainer');
    if (container) {
        container.style.opacity = '1';
        container.style.pointerEvents = 'auto';
    }
}

// Show error message
function showError(message) {
    const container = document.getElementById('logsContainer');
    if (container) {
        container.innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i> ${escapeHtml(message)}
                <button class="btn btn-sm btn-outline-danger mt-2" onclick="location.reload()">Retry</button>
            </div>
        `;
    }
}

// Refresh logs
async function refreshLogs() {
    await loadLogs(1);
}

// Attach event listeners for pagination and filters
function attachEventListeners() {
    // Pagination links
    document.querySelectorAll('#pagination .page-link, .pagination .page-link').forEach(link => {
        link.addEventListener('click', async (e) => {
            e.preventDefault();
            const page = link.getAttribute('data-page');
            if (page) {
                await loadLogs(parseInt(page));
            }
        });
    });
    
    // Per page selector
    const perPageSelect = document.querySelector('#per_page_select, .per-page-select');
    if (perPageSelect) {
        perPageSelect.addEventListener('change', async (e) => {
            await loadLogs(1, parseInt(e.target.value));
        });
    }
    
    // Filter form
    const filterForm = document.getElementById('filterForm');
    if (filterForm) {
        filterForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            await loadLogs(1);
        });
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    attachEventListeners();
});

// Helper function to update URL parameters (for the existing payments page)
function updateQueryStringParameter(uri, key, value) {
    var re = new RegExp("([?&])" + key + "=.*?(&|$)", "i");
    var separator = uri.indexOf('?') !== -1 ? "&" : "?";
    if (uri.match(re)) {
        return uri.replace(re, '$1' + key + "=" + value + '$2');
    } else {
        return uri + separator + key + "=" + value;
    }
}
</script>

<?php renderPageEnd(); ?>