<?php
// Shared Layout Functions
function renderHeader($title, $user_name, $user_role) {
    $navbar_theme = getNavbarTheme();
    
    // Get profile picture from session
    $profile_picture = $_SESSION['profile_picture'] ?? null;
    
    // Generate avatar HTML based on whether profile picture exists
    $avatar_html = '';
    if ($profile_picture && !empty($profile_picture) && file_exists('../uploads/profile_pictures/' . $profile_picture)) {
        // Show actual profile picture
        $avatar_html = '<img src="../uploads/profile_pictures/' . htmlspecialchars($profile_picture) . '" 
                        class="profile-picture-nav" 
                        alt="' . htmlspecialchars($user_name) . '">';
    } else {
        // Show initials as fallback
        $avatar_html = '<div class="avatar-circle me-2">' . strtoupper(substr($user_name, 0, 1)) . '</div>';
    }
    
    return '
    <nav class="navbar navbar-expand-lg ' . $navbar_theme . ' border-bottom fixed-top" style="z-index: 1030;">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">
                <i class="fas fa-graduation-cap"></i> ' . $title . '
            </span>
            
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" 
                       data-bs-toggle="dropdown" aria-expanded="false">
                        ' . $avatar_html . '
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
    
    .profile-picture-nav {
        width: 35px;
        height: 35px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #fff;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    </style>';
}

function renderSidebar($role, $current_page = '') {
    $sidebar_theme = getSidebarTheme();
    
    $admin_menu = [
        'dashboard.php' => ['icon' => 'fas fa-tachometer-alt', 'text' => 'Dashboard'],
        'manage_users.php' => ['icon' => 'fas fa-users', 'text' => 'Manage Users'],
        'manage_courses.php' => ['icon' => 'fas fa-book', 'text' => 'Course Management'],
        'manage_subjects.php' => ['icon' => 'fas fa-book-open', 'text' => 'Manage Subjects'],
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
    
    $html = '<nav class="sidebar ' . $sidebar_theme . '" style="width: 250px; height: calc(100vh - 56px); position: fixed; top: 56px; left: 0; overflow-y: auto; z-index: 1025;">
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
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
        <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        ' . getThemeCSS() . '
        <style>
        body {
            padding-top: 56px;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: calc(100vh - 56px);
        }
        .sidebar .nav-link:hover {
            background-color: rgba(0,123,255,0.1);
        }
        .subject-item {
            border-left: 4px solid #0d6efd;
        }
        .subject-item.adjusted {
            border-left-color: #ffc107;
        }
        .nav-tabs .nav-link.active {
            background-color: #fff;
            border-bottom-color: #fff;
        }
        .tab-content {
            background-color: #fff;
            border: 1px solid #dee2e6;
            border-top: none;
            padding: 20px;
            border-radius: 0 0 5px 5px;
        }
        
        /* STATS CARD STYLES - Unified for all pages */
        .stats-card-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
        }

        .stats-card {
            height: 100%;
            margin: 0 !important;
            border: 1px solid rgba(0,0,0,0.1);
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }

        .stats-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .stats-card .card-body {
            padding: 1.25rem !important;
            height: 100%;
            display: flex;
            align-items: center;
        }

        .stats-card .stats-icon {
            flex-shrink: 0;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .stats-card .stats-title {
            font-size: 0.875rem;
            line-height: 1.3;
            margin-bottom: 0.25rem;
            color: #6c757d !important;
            font-weight: 500;
        }

        .stats-card .stats-value {
            font-size: 1.75rem;
            line-height: 1.2;
            font-weight: 700;
            color: #212529;
            word-break: break-word;
            overflow-wrap: break-word;
            max-width: 100%;
        }

        /* Responsive adjustments for stats cards */
        @media (max-width: 1200px) {
            .stats-card-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .stats-card-container {
                grid-template-columns: 1fr;
            }
            
            .stats-card .stats-icon {
                width: 50px;
                height: 50px;
            }
            
            .stats-card .stats-value {
                font-size: 1.5rem;
            }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: relative !important;
                top: 0 !important;
                height: auto !important;
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
    <div class="card stats-card">
        <div class="card-body">
            <div class="d-flex align-items-center">
                <div class="stats-icon me-3">
                    <i class="' . $icon . ' fa-2x text-' . $color . '"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="text-muted small mb-1 stats-title">' . htmlspecialchars($title) . '</div>
                    <div class="h3 mb-0 stats-value">' . htmlspecialchars($value) . '</div>
                </div>
            </div>
        </div>
    </div>';
}   
// Add these helper functions to layout.php

function renderCourseCard($course) {
    $status_badge = $course['status'] === 'active' ? 'success' : 'secondary';
    $status_text = ucfirst($course['status']);
    
    return '
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h5 class="card-title mb-1">' . htmlspecialchars($course['course_code']) . ' - ' . htmlspecialchars($course['course_name']) . '</h5>
                    <p class="card-text text-muted mb-2">' . htmlspecialchars($course['description']) . '</p>
                    <div class="d-flex gap-2">
                        <span class="badge bg-info">' . $course['duration_years'] . ' years</span>
                        <span class="badge bg-secondary">' . $course['total_units'] . ' units</span>
                        <span class="badge bg-' . $status_badge . '">' . $status_text . '</span>
                    </div>
                </div>
                <div class="btn-group">
                    <button class="btn btn-sm btn-outline-primary btn-edit-course" 
                            data-id="' . $course['id'] . '"
                            data-code="' . htmlspecialchars($course['course_code']) . '"
                            data-name="' . htmlspecialchars($course['course_name']) . '"
                            data-desc="' . htmlspecialchars($course['description']) . '"
                            data-units="' . $course['total_units'] . '"
                            data-years="' . $course['duration_years'] . '"
                            data-status="' . $course['status'] . '">
                        <i class="fas fa-edit"></i>
                    </button>
                    <a href="manage_courses.php?tab=curriculum&course_id=' . $course['id'] . '" 
                       class="btn btn-sm btn-outline-info">
                        <i class="fas fa-book-open"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>';
}

function renderSubjectItem($subject, $showActions = true) {
    $isAdjusted = isset($subject['is_adjusted']) && $subject['is_adjusted'];
    
    return '
    <div class="card subject-item ' . ($isAdjusted ? 'adjusted' : '') . ' mb-2">
        <div class="card-body py-2">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <strong>' . htmlspecialchars($subject['subject_code']) . '</strong>
                    <br>
                    <small class="text-muted">' . htmlspecialchars($subject['subject_name']) . '</small>
                    <br>
                    <small>' . $subject['units'] . ' units</small>
                    ' . (isset($subject['year_level']) ? '<span class="badge bg-info ms-2">Year ' . $subject['year_level'] . ' - ' . $subject['semester'] . '</span>' : '') . '
                    ' . ($isAdjusted ? '<span class="badge bg-warning ms-2">Adjusted</span>' : '') . '
                </div>';
    
    if ($showActions) {
        echo '
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-primary btn-adjust-subject"
                            data-subject-id="' . $subject['id'] . '"
                            data-subject-name="' . htmlspecialchars($subject['subject_code'] . ' - ' . $subject['subject_name']) . '">
                        <i class="fas fa-edit"></i>
                    </button>
                </div>';
    }
    
    echo '
            </div>
        </div>
    </div>';
}

function renderStudentCourseInfo($student) {
    if (!isset($student['course_name'])) {
        return '<div class="alert alert-warning">No course assigned</div>';
    }
    
    return '
    <div class="alert alert-info">
        <strong>Current Course:</strong> ' . htmlspecialchars($student['course_name']) . '<br>
        <strong>Year Level:</strong> Year ' . $student['current_year_level'] . '<br>
        <strong>Enrollment Date:</strong> ' . date('M d, Y', strtotime($student['enrollment_date'])) . '
    </div>';
}

function renderCurriculumYear($year, $semesters) {
    $html = '
    <div class="card mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0">Year ' . $year . '</h5>
        </div>
        <div class="card-body">';
    
    foreach ($semesters as $semester => $subjects) {
        $html .= '
            <div class="mb-3">
                <h6 class="text-muted">' . ucfirst($semester) . ' Semester</h6>
                <div class="row">';
        
        foreach ($subjects as $subject) {
            $html .= '
                    <div class="col-md-6 mb-2">
                        ' . renderSubjectItem($subject, false) . '
                    </div>';
        }
        
        $html .= '
                </div>
            </div>';
    }
    
    $html .= '
        </div>
    </div>';
    
    return $html;
}
?> 