<?php
// Shared Layout Functions
function renderHeader($title, $user_name, $user_role) {
    $navbar_theme = getNavbarTheme();
    return '
    <nav class="navbar navbar-expand-lg ' . $navbar_theme . ' border-bottom">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">
                <i class="fas fa-graduation-cap"></i> ' . $title . '
            </span>
            
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" 
                       data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="avatar-circle me-2">
                            ' . strtoupper(substr($user_name, 0, 1)) . '
                        </div>
                        ' . $user_name . '
                        <span class="badge bg-primary ms-2">' . ucfirst($user_role) . '</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="/student\'s-information-system/logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>
    
    <style>
    .avatar-circle {
        width: 35px;
        height: 35px;
        border-radius: 50%;
        background-color: #007bff;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 14px;
    }
    </style>';
}

function renderSidebar($role, $current_page = '') {
    $sidebar_theme = getSidebarTheme();
    
    $admin_menu = [
        'dashboard.php' => ['icon' => 'fas fa-tachometer-alt', 'text' => 'Dashboard'],
        'manage_users.php' => ['icon' => 'fas fa-users', 'text' => 'Manage Users'],
        'manage_subjects.php' => ['icon' => 'fas fa-book', 'text' => 'Manage Subjects'],
        'manage_sections.php' => ['icon' => 'fas fa-layer-group', 'text' => 'Manage Sections'],
        'manage_payments.php' => ['icon' => 'fas fa-money-bill-wave', 'text' => 'Manage Payments'],
        'logs.php' => ['icon' => 'fas fa-history', 'text' => 'Activity Logs'],
        'backup.php' => ['icon' => 'fas fa-database', 'text' => 'Backup System']
    ];
    
    $student_menu = [
        'dashboard.php' => ['icon' => 'fas fa-tachometer-alt', 'text' => 'Dashboard'],
        'payments.php' => ['icon' => 'fas fa-money-bill-wave', 'text' => 'My Payments'],
        'schedule.php' => ['icon' => 'fas fa-calendar-alt', 'text' => 'My Schedule'],
        'profile.php' => ['icon' => 'fas fa-user', 'text' => 'My Profile']
    ];
    
    $cashier_menu = [
        'dashboard.php' => ['icon' => 'fas fa-tachometer-alt', 'text' => 'Dashboard'],
        'payments.php' => ['icon' => 'fas fa-money-bill-wave', 'text' => 'Manage Payments'],
        'audit_log.php' => ['icon' => 'fas fa-clipboard-list', 'text' => 'Audit Log']
    ];
    
    $menu = [];
    switch($role) {
        case 'admin': $menu = $admin_menu; break;
        case 'student': $menu = $student_menu; break;
        case 'cashier': $menu = $cashier_menu; break;
    }
    
    $html = '<nav class="sidebar ' . $sidebar_theme . '" style="width: 250px; min-height: calc(100vh - 56px); position: fixed; top: 56px; left: 0; overflow-y: auto;">
        <div class="p-3">
            <ul class="nav flex-column">';
    
    foreach($menu as $page => $item) {
        $active = ($current_page === $page) ? 'active bg-primary text-white' : '';
        $html .= '<li class="nav-item mb-1">
            <a class="nav-link ' . $active . ' rounded" href="' . $page . '">
                <i class="' . $item['icon'] . ' me-2"></i>
                ' . $item['text'] . '
            </a>
        </li>';
    }
    
    $html .= '</ul></div></nav>';
    
    return $html;
}

function renderPageStart($title, $role, $current_page = '') {
    $user_name = $_SESSION['name'];
    $user_role = $_SESSION['role'];
    
    echo '<!DOCTYPE html>
    <html lang="en" ' . getThemeAttributes() . '>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . $title . ' - Student Information System</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        ' . getThemeCSS() . '
        <style>
        .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: calc(100vh - 56px);
        }
        .sidebar .nav-link:hover {
            background-color: rgba(0,123,255,0.1);
        }
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: relative !important;
                top: 0 !important;
                min-height: auto !important;
            }
            .main-content {
                margin-left: 0;
            }
        }
        </style>
    </head>
    <body class="' . getThemeClasses() . '">
        ' . renderHeader($title, $user_name, $user_role) . '
        ' . renderSidebar($role, $current_page) . '
        <div class="main-content">';
}

function renderPageEnd() {
    echo '        </div>
        ' . getThemeToggleButton() . '
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>';
}

function renderCard($title, $content, $footer = '', $class = '') {
    $card_theme = getCardTheme();
    return '
    <div class="card ' . $card_theme . ' ' . $class . '">
        <div class="card-header">
            <h5 class="card-title mb-0">' . $title . '</h5>
        </div>
        <div class="card-body">
            ' . $content . '
        </div>
        ' . ($footer ? '<div class="card-footer">' . $footer . '</div>' : '') . '
    </div>';
}

function renderStatsCard($title, $value, $icon, $color = 'primary') {
    return '
    <div class="card text-center">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col">
                    <i class="' . $icon . ' fa-2x text-' . $color . '"></i>
                </div>
                <div class="col">
                    <h3 class="mb-0">' . $value . '</h3>
                    <small class="text-muted">' . $title . '</small>
                </div>
            </div>
        </div>
    </div>';
}
?>
