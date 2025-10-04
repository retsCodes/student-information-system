<?php
// Theme Toggle System
if (!isset($_SESSION)) {
    session_start();
}

// Handle theme toggle with persistence
if (isset($_POST['toggle_theme'])) {
    $pdo = getDBConnection();
    $new_theme = ($_SESSION['theme'] ?? 'light') === 'light' ? 'dark' : 'light';
    $_SESSION['theme'] = $new_theme;
    
    // If user is logged in, save theme preference to database
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("UPDATE users SET theme_preference = ? WHERE user_id = ?");
        $stmt->execute([$new_theme, $_SESSION['user_id']]);
    }
    
    // Also set a cookie for non-logged in users
    setcookie('theme_preference', $new_theme, time() + (86400 * 30), "/"); // 30 days
    
    header('Content-Type: application/json');
    echo json_encode(['theme' => $new_theme]);
    exit();
}

// Get current theme with persistence
function getCurrentTheme() {
    // If theme is already in session, use it
    if (isset($_SESSION['theme'])) {
        return $_SESSION['theme'];
    }
    
    // If user is logged in, try to get theme from database
    if (isset($_SESSION['user_id'])) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT theme_preference FROM users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['theme_preference']) {
            $_SESSION['theme'] = $result['theme_preference'];
            return $_SESSION['theme'];
        }
    }
    
    // If no theme in session or database, check cookie
    if (isset($_COOKIE['theme_preference'])) {
        $_SESSION['theme'] = $_COOKIE['theme_preference'];
        return $_SESSION['theme'];
    }
    
    // Default to light theme
    return 'light';
}

// Theme toggle button HTML
function getThemeToggleButton() {
    $currentTheme = getCurrentTheme();
    $icon = $currentTheme === 'light' ? 'fa-moon' : 'fa-sun';
    $nextTheme = $currentTheme === 'light' ? 'dark' : 'light';
    
    return '
    <button id="themeToggle" class="btn btn-outline-secondary position-fixed" 
            style="bottom: 20px; right: 20px; z-index: 1050; border-radius: 50%; width: 50px; height: 50px;"
            onclick="toggleTheme()" title="Switch to ' . $nextTheme . ' mode">
        <i class="fas ' . $icon . '"></i>
    </button>
    
    <script>
    function toggleTheme() {
        fetch(window.location.href, {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
            },
            body: "toggle_theme=1"
        })
        .then(response => response.json())
        .then(data => {
            location.reload();
        })
        .catch(error => {
            console.error("Error:", error);
        });
    }
    </script>';
}

// Get theme CSS classes
function getThemeClasses() {
    $theme = getCurrentTheme();
    if ($theme === 'dark') {
        return 'bg-dark text-light';
    }
    return 'bg-light text-dark';
}

// Get theme attributes for Bootstrap
function getThemeAttributes() {
    $theme = getCurrentTheme();
    if ($theme === 'dark') {
        return 'data-bs-theme="dark"';
    }
    return 'data-bs-theme="light"';
}

// Get navbar theme class
function getNavbarTheme() {
    $theme = getCurrentTheme();
    return $theme === 'dark' ? 'navbar-dark bg-dark' : 'navbar-light bg-light';
}

// Get sidebar theme class
function getSidebarTheme() {
    $theme = getCurrentTheme();
    return $theme === 'dark' ? 'bg-dark text-light' : 'bg-light text-dark';
}

// Get card theme class
function getCardTheme() {
    $theme = getCurrentTheme();
    return $theme === 'dark' ? 'bg-dark text-light border-secondary' : 'bg-white text-dark';
}

// Custom CSS for dark theme
function getThemeCSS() {
    $theme = getCurrentTheme();
    if ($theme === 'dark') {
        return '
        <style>
        body { background-color: #212529 !important; color: #fff !important; }
        .card { background-color: #343a40 !important; border-color: #495057 !important; }
        .table { color: #fff !important; }
        .table-striped > tbody > tr:nth-child(odd) > td,
        .table-striped > tbody > tr:nth-child(odd) > th {
            background-color: #495057;
        }
        .form-control, .form-select {
            background-color: #495057 !important;
            border-color: #6c757d !important;
            color: #fff !important;
        }
        .form-control:focus, .form-select:focus {
            background-color: #495057 !important;
            border-color: #0d6efd !important;
            color: #fff !important;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }
        .sidebar {
            background-color: #343a40 !important;
            border-right: 1px solid #495057 !important;
        }
        .sidebar .nav-link {
            color: #adb5bd !important;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: #fff !important;
            background-color: #495057 !important;
        }
        .alert-info {
            background-color: #0f4880 !important;
            border-color: #0a3d6b !important;
            color: #b8daff !important;
        }
        .alert-warning {
            background-color: #664d03 !important;
            border-color: #523e02 !important;
            color: #ffecb5 !important;
        }
        .alert-success {
            background-color: #0f5132 !important;
            border-color: #0a3622 !important;
            color: #d1e7dd !important;
        }
        .alert-danger {
            background-color: #842029 !important;
            border-color: #6a1a21 !important;
            color: #f8d7da !important;
        }
        </style>';
    }
    return '';
}
?>
