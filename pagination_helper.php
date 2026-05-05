<?php
/**
 * PaginationHelper.php
 * Centralized pagination system for the entire application
 * 
 * Usage:
 * $pagination = new PaginationHelper($pdo);
 * $results = $pagination->setTable('payments p')
 *                       ->setColumns('p.*, si.name as student_name')
 *                       ->setJoins('JOIN students_info si ON p.student_id = si.user_id')
 *                       ->setWhere('p.issued_by = ?', [$user_id])
 *                       ->setOrderBy('ORDER BY p.issued_date DESC')
 *                       ->setPage($page)
 *                       ->setPerPage(25)
 *                       ->getResults();
 */

class PaginationHelper {
    private $pdo;
    private $table;
    private $columns = '*';
    private $joins = '';
    private $where = '';
    private $params = [];
    private $order_by = '';
    private $group_by = '';
    private $page = 1;
    private $per_page = 25;
    private $total_records = 0;
    private $total_pages = 0;
    private $allowed_per_page = [10, 25, 50, 100, 200];
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Set the main table name (required)
     */
    public function setTable($table) {
        $this->table = $table;
        return $this;
    }
    
    /**
     * Set columns to select (default: *)
     */
    public function setColumns($columns) {
        $this->columns = $columns;
        return $this;
    }
    
    /**
     * Set JOIN clauses
     */
    public function setJoins($joins) {
        $this->joins = $joins;
        return $this;
    }
    
    /**
     * Set WHERE conditions
     * @param string $where WHERE clause without the word "WHERE"
     * @param array $params Parameters for prepared statement
     */
    public function setWhere($where, $params = []) {
        $this->where = $where ? "WHERE $where" : '';
        $this->params = $params;
        return $this;
    }
    
    /**
     * Set ORDER BY clause
     */
    public function setOrderBy($order_by) {
        $this->order_by = $order_by;
        return $this;
    }
    
    /**
     * Set GROUP BY clause
     */
    public function setGroupBy($group_by) {
        $this->group_by = $group_by;
        return $this;
    }
    
    /**
     * Set current page number
     */
    public function setPage($page) {
        $this->page = max(1, intval($page));
        return $this;
    }
    
    /**
     * Set items per page
     */
    public function setPerPage($per_page, $allowed = null) {
        if ($allowed !== null) {
            $this->allowed_per_page = $allowed;
        }
        $per_page = intval($per_page);
        $this->per_page = in_array($per_page, $this->allowed_per_page) ? $per_page : 25;
        return $this;
    }
    
    /**
     * Get total records count
     */
    private function getTotalCount() {
        $sql = "SELECT COUNT(*) as total FROM {$this->table} {$this->joins} {$this->where} {$this->group_by}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return intval($result['total']);
    }
    
    /**
     * Execute query and get paginated results
     */
    public function getResults() {
        // Get total count
        $this->total_records = $this->getTotalCount();
        $this->total_pages = ceil($this->total_records / $this->per_page);
        
        // Ensure page is within bounds
        if ($this->page > $this->total_pages && $this->total_pages > 0) {
            $this->page = $this->total_pages;
        }
        
        $offset = ($this->page - 1) * $this->per_page;
        
        // Build data query
        $sql = "SELECT {$this->columns} FROM {$this->table} {$this->joins} {$this->where} {$this->group_by} {$this->order_by} LIMIT {$this->per_page} OFFSET {$offset}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'data' => $data,
            'pagination' => $this->getPaginationInfo()
        ];
    }
    
    /**
     * Get pagination information array
     */
    public function getPaginationInfo() {
        return [
            'current_page' => $this->page,
            'per_page' => $this->per_page,
            'total_records' => $this->total_records,
            'total_pages' => $this->total_pages,
            'offset' => ($this->page - 1) * $this->per_page,
            'showing_start' => $this->total_records > 0 ? ($this->page - 1) * $this->per_page + 1 : 0,
            'showing_end' => min($this->page * $this->per_page, $this->total_records),
            'has_previous' => $this->page > 1,
            'has_next' => $this->page < $this->total_pages,
            'previous_page' => $this->page - 1,
            'next_page' => $this->page + 1,
            'first_page' => 1,
            'last_page' => $this->total_pages
        ];
    }
    
    /**
     * Render pagination HTML
     */
    public function renderPagination($base_url = '', $query_params = []) {
        if ($this->total_pages <= 1) {
            return '';
        }
        
        // Remove pagination params from query string
        unset($query_params['page']);
        unset($query_params['per_page']);
        $query_string = !empty($query_params) ? '&' . http_build_query($query_params) : '';
        
        $html = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center flex-wrap">';
        
        // First page
        if ($this->page > 1) {
            $html .= '<li class="page-item">
                        <a class="page-link" href="' . $base_url . '?page=1&per_page=' . $this->per_page . $query_string . '" data-page="1" data-per-page="' . $this->per_page . '">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                      </li>';
        } else {
            $html .= '<li class="page-item disabled"><span class="page-link"><i class="fas fa-angle-double-left"></i></span></li>';
        }
        
        // Previous button
        if ($this->page > 1) {
            $html .= '<li class="page-item">
                        <a class="page-link" href="' . $base_url . '?page=' . ($this->page - 1) . '&per_page=' . $this->per_page . $query_string . '" data-page="' . ($this->page - 1) . '" data-per-page="' . $this->per_page . '">
                            &laquo;
                        </a>
                      </li>';
        } else {
            $html .= '<li class="page-item disabled"><span class="page-link">&laquo;</span></li>';
        }
        
        // Page numbers (show up to 5 pages)
        $start_page = max(1, $this->page - 2);
        $end_page = min($this->total_pages, $this->page + 2);
        
        if ($start_page > 1) {
            $html .= '<li class="page-item"><a class="page-link" href="' . $base_url . '?page=1&per_page=' . $this->per_page . $query_string . '" data-page="1" data-per-page="' . $this->per_page . '">1</a></li>';
            if ($start_page > 2) {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }
        
        for ($i = $start_page; $i <= $end_page; $i++) {
            $active = ($i == $this->page) ? 'active' : '';
            $html .= '<li class="page-item ' . $active . '">
                        <a class="page-link" href="' . $base_url . '?page=' . $i . '&per_page=' . $this->per_page . $query_string . '" data-page="' . $i . '" data-per-page="' . $this->per_page . '">
                            ' . $i . '
                        </a>
                      </li>';
        }
        
        if ($end_page < $this->total_pages) {
            if ($end_page < $this->total_pages - 1) {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            $html .= '<li class="page-item"><a class="page-link" href="' . $base_url . '?page=' . $this->total_pages . '&per_page=' . $this->per_page . $query_string . '" data-page="' . $this->total_pages . '" data-per-page="' . $this->per_page . '">' . $this->total_pages . '</a></li>';
        }
        
        // Next button
        if ($this->page < $this->total_pages) {
            $html .= '<li class="page-item">
                        <a class="page-link" href="' . $base_url . '?page=' . ($this->page + 1) . '&per_page=' . $this->per_page . $query_string . '" data-page="' . ($this->page + 1) . '" data-per-page="' . $this->per_page . '">
                            &raquo;
                        </a>
                      </li>';
        } else {
            $html .= '<li class="page-item disabled"><span class="page-link">&raquo;</span></li>';
        }
        
        // Last page
        if ($this->page < $this->total_pages) {
            $html .= '<li class="page-item">
                        <a class="page-link" href="' . $base_url . '?page=' . $this->total_pages . '&per_page=' . $this->per_page . $query_string . '" data-page="' . $this->total_pages . '" data-per-page="' . $this->per_page . '">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                      </li>';
        } else {
            $html .= '<li class="page-item disabled"><span class="page-link"><i class="fas fa-angle-double-right"></i></span></li>';
        }
        
        $html .= '</ul></nav>';
        
        return $html;
    }
    
    /**
     * Render per-page selector dropdown
     */
    public function renderPerPageSelector($base_url = '', $query_params = []) {
        // Remove pagination params from query string
        unset($query_params['page']);
        unset($query_params['per_page']);
        $query_string = !empty($query_params) ? '&' . http_build_query($query_params) : '';
        
        $html = '<div class="d-flex align-items-center gap-2">';
        $html .= '<label class="text-muted small mb-0">Show:</label>';
        $html .= '<select class="form-select form-select-sm per-page-select" style="width: auto;" data-base-url="' . $base_url . '" data-query-string="' . $query_string . '">';
        
        foreach ($this->allowed_per_page as $option) {
            $selected = ($this->per_page == $option) ? 'selected' : '';
            $html .= '<option value="' . $option . '" ' . $selected . '>' . $option . '</option>';
        }
        
        $html .= '</select>';
        $html .= '<span class="text-muted small">per page</span>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render complete pagination bar with info and per-page selector
     */
    public function renderFullPagination($base_url = '', $query_params = []) {
        if ($this->total_records <= 0) {
            return '';
        }
        
        $info = $this->getPaginationInfo();
        
        $html = '<div class="row align-items-center mt-3 g-2">';
        $html .= '<div class="col-md-4 mb-2 mb-md-0">';
        $html .= '<div class="text-muted small">';
        $html .= 'Showing ' . $info['showing_start'] . ' to ' . $info['showing_end'] . ' of ' . number_format($info['total_records']) . ' entries';
        $html .= '</div>';
        $html .= '</div>';
        
        $html .= '<div class="col-md-4 mb-2 mb-md-0 d-flex justify-content-center">';
        $html .= $this->renderPagination($base_url, $query_params);
        $html .= '</div>';
        
        $html .= '<div class="col-md-4 d-flex justify-content-md-end">';
        $html .= $this->renderPerPageSelector($base_url, $query_params);
        $html .= '</div>';
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Get JSON response for AJAX requests
     */
    public function getJsonResponse($base_url = '', $query_params = []) {
        $results = $this->getResults();
        return [
            'success' => true,
            'data' => $results['data'],
            'pagination' => $results['pagination'],
            'html' => [
                'pagination' => $this->renderPagination($base_url, $query_params),
                'info' => $this->getPaginationInfoHTML()
            ]
        ];
    }
    
    /**
     * Get pagination info HTML
     */
    private function getPaginationInfoHTML() {
        $info = $this->getPaginationInfo();
        if ($this->total_records <= 0) {
            return '<div class="text-muted small">No records found</div>';
        }
        return '<div class="text-muted small">Showing ' . $info['showing_start'] . ' to ' . $info['showing_end'] . ' of ' . number_format($info['total_records']) . ' entries</div>';
    }
}

/**
 * Helper function to quickly get paginated payments for cashier
 */
function getPaginatedPayments($pdo, $cashier_id, $page = 1, $per_page = 25, $filters = []) {
    $pagination = new PaginationHelper($pdo);
    
    $where = "p.issued_by = ?";
    $params = [$cashier_id];
    
    if (!empty($filters['status'])) {
        $where .= " AND p.payment_status = ?";
        $params[] = $filters['status'];
    }
    
    if (!empty($filters['search'])) {
        $where .= " AND (p.description LIKE ? OR p.permit_number LIKE ? OR si.name LIKE ? OR si.user_id LIKE ?)";
        $search_param = "%{$filters['search']}%";
        $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    }
    
    if (!empty($filters['date_from'])) {
        $where .= " AND p.issued_date >= ?";
        $params[] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $where .= " AND p.issued_date <= ?";
        $params[] = $filters['date_to'];
    }
    
    return $pagination
        ->setTable('payments p')
        ->setColumns('p.*, si.name as student_name, si.program, si.year_level')
        ->setJoins('JOIN students_info si ON p.student_id = si.user_id')
        ->setWhere($where, $params)
        ->setOrderBy('ORDER BY p.issued_date DESC, p.id DESC')
        ->setPage($page)
        ->setPerPage($per_page)
        ->getResults();
}

/**
 * Helper function to get paginated users for admin
 */
function getPaginatedUsers($pdo, $page = 1, $per_page = 25, $filters = []) {
    $pagination = new PaginationHelper($pdo);
    
    $where = "1=1";
    $params = [];
    
    if (!empty($filters['role'])) {
        $where .= " AND u.role = ?";
        $params[] = $filters['role'];
    }
    
    if (!empty($filters['status'])) {
        $where .= " AND u.user_status = ?";
        $params[] = $filters['status'];
    }
    
    if (!empty($filters['search'])) {
        $where .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.user_id LIKE ?)";
        $search_param = "%{$filters['search']}%";
        $params = array_merge($params, [$search_param, $search_param, $search_param]);
    }
    
    if (!empty($filters['program'])) {
        $where .= " AND si.program = ?";
        $params[] = $filters['program'];
    }
    
    if (!empty($filters['year_level'])) {
        $where .= " AND si.year_level = ?";
        $params[] = $filters['year_level'];
    }
    
    return $pagination
        ->setTable('users u')
        ->setColumns('u.*, COALESCE(si.program, ei.role) as additional_info, si.year_level, si.student_type, si.enrollment_status')
        ->setJoins('LEFT JOIN students_info si ON u.user_id = si.user_id LEFT JOIN employee_info ei ON u.user_id = ei.user_id')
        ->setWhere($where, $params)
        ->setOrderBy('ORDER BY u.created_at DESC')
        ->setPage($page)
        ->setPerPage($per_page)
        ->getResults();
}
?>