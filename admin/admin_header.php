<?php
// admin/admin_header.php - Admin Header
$currentPage = basename($_SERVER['PHP_SELF']);
$pageTitle = isset($pageTitle) ? $pageTitle : 'Dashboard';

// Security: Prevent session fixation and ensure admin access
if (!defined('SITE_NAME')) {
    header('HTTP/1.1 403 Forbidden');
    exit('Direct access forbidden');
}

// CSRF Token Generation (for forms)
if (function_exists('generateCSRFToken')) {
    $csrfToken = generateCSRFToken();
}

// Notification count (if needed)
$notificationCount = 0;
if (function_exists('getAdminNotificationCount')) {
    $notificationCount = getAdminNotificationCount();
}

// Get admin user details safely with error handling
$adminUsername = '';
$adminEmail = '';
$adminInitial = 'A';

if (class_exists('Session')) {
    try {
        $adminUsername = Session::username();
        $adminUsername = is_string($adminUsername) ? $adminUsername : '';
        
        // Check if userEmail method exists before calling
        if (method_exists('Session', 'userEmail')) {
            $adminEmail = Session::userEmail();
            $adminEmail = is_string($adminEmail) ? $adminEmail : '';
        } else {
            // Fallback: try to get email from session directly if available
            if (isset($_SESSION['user_email'])) {
                $adminEmail = $_SESSION['user_email'];
            } elseif (isset($_SESSION['email'])) {
                $adminEmail = $_SESSION['email'];
            }
        }
        
        // Get user initial safely
        if (!empty($adminUsername)) {
            $adminInitial = strtoupper(substr($adminUsername, 0, 1));
        } elseif (!empty($adminEmail)) {
            $adminInitial = strtoupper(substr($adminEmail, 0, 1));
        }
    } catch (Exception $e) {
        // Log error silently, use defaults
        error_log('Session error in admin header: ' . $e->getMessage());
        $adminUsername = 'Admin';
        $adminInitial = 'A';
    }
} else {
    // Fallback if Session class doesn't exist
    $adminUsername = isset($_SESSION['username']) ? $_SESSION['username'] : 'Admin';
    $adminEmail = isset($_SESSION['email']) ? $_SESSION['email'] : '';
    $adminInitial = !empty($adminUsername) ? strtoupper(substr($adminUsername, 0, 1)) : 'A';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?php echo htmlspecialchars($pageTitle . ' - ' . SITE_NAME . ' Admin'); ?></title>
    
    <!-- Favicon (optional) -->
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    
    <!-- Font Awesome 6 (local fallback optional) -->
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer">
    
    <style>
        /* ==========================================================================
           MODERN ADMIN PANEL CSS - COMPLETE REGENERATION
           ========================================================================== */

        /* ---------- CSS RESET & GLOBAL ---------- */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        :root {
            /* Primary Colors */
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --primary-light: #818cf8;
            --primary-soft: #eef2ff;
            
            /* Secondary Colors */
            --secondary: #8b5cf6;
            --secondary-dark: #7c3aed;
            --secondary-light: #a78bfa;
            
            /* Status Colors */
            --success: #10b981;
            --success-dark: #059669;
            --success-light: #d1fae5;
            --danger: #ef4444;
            --danger-dark: #dc2626;
            --danger-light: #fee2e2;
            --warning: #f59e0b;
            --warning-dark: #d97706;
            --warning-light: #fef3c7;
            --info: #3b82f6;
            --info-dark: #2563eb;
            --info-light: #dbeafe;
            
            /* Neutral Colors */
            --dark: #111827;
            --dark-soft: #1f2937;
            --gray-dark: #374151;
            --gray: #6b7280;
            --gray-light: #9ca3af;
            --gray-soft: #e5e7eb;
            --light: #f9fafb;
            --white: #ffffff;
            
            /* Gradients */
            --gradient-primary: linear-gradient(135deg, var(--primary), var(--secondary));
            --gradient-dark: linear-gradient(180deg, var(--dark), var(--dark-soft));
            
            /* Shadows */
            --shadow-xs: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            
            /* Border Radius */
            --radius-xs: 0.25rem;
            --radius-sm: 0.5rem;
            --radius-md: 0.75rem;
            --radius-lg: 1rem;
            --radius-xl: 1.5rem;
            --radius-full: 9999px;
            
            /* Layout */
            --sidebar-width: 280px;
            --header-height: 70px;
            --content-max-width: 1600px;
            
            /* Transitions */
            --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-base: 250ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-slow: 350ms cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: system-ui, -apple-system, 'Segoe UI', 'Roboto', 'Helvetica Neue', sans-serif;
            background: var(--light);
            color: var(--dark);
            line-height: 1.5;
            overflow-x: hidden;
        }

        /* ---------- TYPOGRAPHY ---------- */
        h1, h2, h3, h4, h5, h6 {
            font-weight: 600;
            line-height: 1.25;
        }

        /* ---------- SCROLLBAR ---------- */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--gray-soft);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--gray);
            border-radius: var(--radius-full);
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--gray-dark);
        }

        /* ---------- SIDEBAR ---------- */
        .admin-sidebar {
            width: var(--sidebar-width);
            background: var(--gradient-dark);
            backdrop-filter: blur(10px);
            color: var(--white);
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            overflow-y: auto;
            z-index: 1000;
            transition: transform var(--transition-base);
            box-shadow: var(--shadow-xl);
        }

        .admin-sidebar::-webkit-scrollbar {
            width: 4px;
        }

        .sidebar-header {
            padding: 1.75rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 0.5rem;
        }

        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 0.875rem;
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--white);
            text-decoration: none;
            transition: opacity var(--transition-fast);
        }

        .sidebar-logo:hover {
            opacity: 0.9;
            color: var(--white);
        }

        .sidebar-logo i {
            font-size: 1.75rem;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .sidebar-logo span {
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .sidebar-menu {
            padding: 0.5rem 0 1.5rem;
        }

        .sidebar-heading {
            padding: 0.875rem 1.5rem 0.5rem;
            font-size: 0.6875rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: rgba(255, 255, 255, 0.4);
            font-weight: 600;
        }

        .sidebar-item {
            display: flex;
            align-items: center;
            gap: 0.875rem;
            padding: 0.75rem 1.5rem;
            margin: 0.25rem 0.75rem;
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: all var(--transition-fast);
            border-radius: var(--radius-sm);
            position: relative;
        }

        .sidebar-item:hover {
            background: rgba(255, 255, 255, 0.1);
            color: var(--white);
            transform: translateX(4px);
        }

        .sidebar-item.active {
            background: linear-gradient(90deg, rgba(99, 102, 241, 0.2), transparent);
            color: var(--white);
            border-right: 3px solid var(--primary);
        }

        .sidebar-item i {
            width: 22px;
            font-size: 1.125rem;
            text-align: center;
        }

        .sidebar-badge {
            margin-left: auto;
            background: var(--danger);
            color: var(--white);
            font-size: 0.6875rem;
            padding: 0.125rem 0.5rem;
            border-radius: var(--radius-full);
            font-weight: 600;
        }

        /* ---------- MAIN CONTENT ---------- */
        .admin-main {
            flex: 1;
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            background: var(--light);
            transition: margin var(--transition-base);
        }

        /* ---------- TOP HEADER ---------- */
        .admin-top-header {
            height: var(--header-height);
            background: var(--white);
            backdrop-filter: blur(10px);
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
            position: sticky;
            top: 0;
            z-index: 99;
            border-bottom: 1px solid var(--gray-soft);
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1.25rem;
        }

        .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.25rem;
            cursor: pointer;
            color: var(--gray);
            padding: 0.5rem;
            border-radius: var(--radius-sm);
            transition: all var(--transition-fast);
        }

        .menu-toggle:hover {
            background: var(--light);
            color: var(--primary);
        }

        .page-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark);
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        /* Notifications */
        .notifications {
            position: relative;
        }

        .notifications-btn {
            background: none;
            border: none;
            font-size: 1.25rem;
            color: var(--gray);
            cursor: pointer;
            padding: 0.5rem;
            border-radius: var(--radius-sm);
            transition: all var(--transition-fast);
            position: relative;
        }

        .notifications-btn:hover {
            background: var(--light);
            color: var(--primary);
        }

        .notification-badge {
            position: absolute;
            top: 0;
            right: 0;
            background: var(--danger);
            color: var(--white);
            font-size: 0.6875rem;
            font-weight: 600;
            padding: 0.125rem 0.375rem;
            border-radius: var(--radius-full);
            transform: translate(25%, -25%);
        }

        /* Admin Profile */
        .admin-profile {
            display: flex;
            align-items: center;
            gap: 1rem;
            cursor: pointer;
            position: relative;
            padding: 0.5rem;
            border-radius: var(--radius-md);
            transition: background var(--transition-fast);
        }

        .admin-profile:hover {
            background: var(--light);
        }

        .admin-avatar {
            width: 42px;
            height: 42px;
            background: var(--gradient-primary);
            border-radius: var(--radius-full);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-weight: 700;
            font-size: 1rem;
            box-shadow: var(--shadow-sm);
        }

        .admin-info {
            display: none;
        }

        @media (min-width: 768px) {
            .admin-info {
                display: block;
            }
            .admin-info h4 {
                font-size: 0.875rem;
                font-weight: 600;
                margin-bottom: 0.125rem;
                color: var(--dark);
            }
            .admin-info p {
                font-size: 0.6875rem;
                color: var(--gray);
            }
        }

        /* Dropdown Menu */
        .admin-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            background: var(--white);
            min-width: 240px;
            box-shadow: var(--shadow-lg);
            border-radius: var(--radius-md);
            display: none;
            z-index: 1000;
            margin-top: 0.5rem;
            overflow: hidden;
            animation: fadeInUp 0.2s ease;
        }

        .admin-profile:hover .admin-dropdown,
        .admin-dropdown:hover {
            display: block;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .dropdown-header {
            padding: 1rem;
            background: var(--light);
            border-bottom: 1px solid var(--gray-soft);
        }

        .dropdown-header p {
            font-size: 0.75rem;
            color: var(--gray);
            margin-top: 0.25rem;
            word-break: break-word;
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: var(--dark);
            text-decoration: none;
            transition: background var(--transition-fast);
        }

        .dropdown-item:hover {
            background: var(--light);
        }

        .dropdown-item i {
            width: 20px;
            color: var(--primary);
            font-size: 0.875rem;
        }

        .dropdown-divider {
            height: 1px;
            background: var(--gray-soft);
            margin: 0.5rem 0;
        }

        .dropdown-footer {
            padding: 0.75rem 1rem;
            background: var(--light);
            border-top: 1px solid var(--gray-soft);
        }

        /* ---------- CONTENT AREA ---------- */
        .container-fluid {
            padding: 1.75rem 2rem;
            max-width: var(--content-max-width);
            margin: 0 auto;
        }

        .content-header {
            margin-bottom: 1.75rem;
        }

        .content-header h2 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.375rem;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .content-header p {
            color: var(--gray);
            font-size: 0.875rem;
        }

        /* ---------- CARDS ---------- */
        .card {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            margin-bottom: 1.5rem;
            overflow: hidden;
            transition: all var(--transition-base);
            border: 1px solid var(--gray-soft);
        }

        .card:hover {
            box-shadow: var(--shadow-md);
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--gray-soft);
            background: var(--white);
        }

        .card-header h3 {
            font-size: 1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-header h3 i {
            color: var(--primary);
            font-size: 1.125rem;
        }

        .card-body {
            padding: 1.5rem;
        }

        .card-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--gray-soft);
            background: var(--light);
        }

/* ==========================================================================
   MODERN STATS CARDS - COMPLETELY REGENERATED
   ========================================================================== */

/* Stats Grid Layout */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.25rem;
    margin-bottom: 2rem;
}

/* Individual Stat Card */
.stat-card {
    background: var(--white);
    border-radius: 1rem;
    padding: 1.25rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05), 0 1px 2px rgba(0, 0, 0, 0.1);
    transition: all 0.2s ease;
    border: 1px solid #e5e7eb;
    position: relative;
    overflow: hidden;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.02);
}

/* Card Accent Border */
.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: inherit;
}

/* Stat Icon */
.stat-icon {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
    flex-shrink: 0;
    transition: transform 0.2s ease;
}

.stat-card:hover .stat-icon {
    transform: scale(1.05);
}

/* Stat Info */
.stat-info {
    flex: 1;
    margin-left: 1rem;
}

.stat-info h3 {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #6b7280;
    margin-bottom: 0.5rem;
}

.stat-info p {
    font-size: 1.75rem;
    font-weight: 700;
    color: #111827;
    line-height: 1.2;
    margin-bottom: 0.25rem;
}

.stat-info small {
    font-size: 0.7rem;
    color: #6b7280;
    display: block;
}

/* Icon Colors - Will override inline styles */
.stat-card:nth-child(1) .stat-icon {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
}

.stat-card:nth-child(2) .stat-icon {
    background: linear-gradient(135deg, #10b981, #059669);
}

.stat-card:nth-child(3) .stat-icon {
    background: linear-gradient(135deg, #f59e0b, #d97706);
}

.stat-card:nth-child(4) .stat-icon {
    background: linear-gradient(135deg, #ef4444, #dc2626);
}

/* Responsive Design */
@media (max-width: 1200px) {
    .stats-grid {
        gap: 1rem;
    }
    
    .stat-info p {
        font-size: 1.5rem;
    }
    
    .stat-icon {
        width: 48px;
        height: 48px;
        font-size: 1.25rem;
    }
}

@media (max-width: 1024px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
    }
}

@media (max-width: 640px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .stat-card {
        padding: 1rem;
    }
    
    .stat-info p {
        font-size: 1.5rem;
    }
    
    .stat-icon {
        width: 44px;
        height: 44px;
        font-size: 1.125rem;
    }
    
    .stat-info h3 {
        font-size: 0.7rem;
    }
}

/* Animation */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.stat-card {
    animation: fadeInUp 0.3s ease-out forwards;
}

.stat-card:nth-child(1) { animation-delay: 0.05s; }
.stat-card:nth-child(2) { animation-delay: 0.1s; }
.stat-card:nth-child(3) { animation-delay: 0.15s; }
.stat-card:nth-child(4) { animation-delay: 0.2s; }

        /* ---------- TABLES ---------- */
        .table-responsive {
            overflow-x: auto;
            margin: -1px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            text-align: left;
            padding: 1rem 1.25rem;
            background: var(--light);
            font-weight: 600;
            font-size: 0.8125rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--gray);
            border-bottom: 1px solid var(--gray-soft);
        }

        .data-table td {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--gray-soft);
            font-size: 0.875rem;
        }

        .data-table tr:last-child td {
            border-bottom: none;
        }

        .data-table tr:hover td {
            background: var(--light);
        }

        /* ---------- BUTTONS ---------- */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.625rem 1.25rem;
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            border: none;
            text-decoration: none;
            transition: all var(--transition-fast);
            line-height: 1;
        }

        .btn-primary {
            background: var(--gradient-primary);
            color: var(--white);
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-secondary {
            background: var(--gray-soft);
            color: var(--dark);
        }

        .btn-secondary:hover {
            background: var(--gray-light);
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--gray-soft);
            color: var(--dark);
        }

        .btn-outline:hover {
            background: var(--light);
            border-color: var(--primary);
            color: var(--primary);
        }

        .btn-danger {
            background: var(--danger);
            color: var(--white);
        }

        .btn-danger:hover {
            background: var(--danger-dark);
        }

        .btn-sm {
            padding: 0.375rem 0.875rem;
            font-size: 0.75rem;
        }

        .btn-icon {
            padding: 0.5rem;
            border-radius: var(--radius-sm);
        }

        /* ---------- BADGES ---------- */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-full);
            font-size: 0.6875rem;
            font-weight: 600;
            line-height: 1;
        }

        .badge-success {
            background: var(--success-light);
            color: var(--success-dark);
        }

        .badge-warning {
            background: var(--warning-light);
            color: var(--warning-dark);
        }

        .badge-danger {
            background: var(--danger-light);
            color: var(--danger-dark);
        }

        .badge-info {
            background: var(--info-light);
            color: var(--info-dark);
        }

        /* ---------- FORMS ---------- */
        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            font-size: 0.875rem;
            color: var(--dark);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--gray-soft);
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            transition: all var(--transition-fast);
            background: var(--white);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        /* ---------- ALERTS ---------- */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background: var(--success-light);
            color: var(--success-dark);
            border-left: 3px solid var(--success);
        }

        .alert-danger {
            background: var(--danger-light);
            color: var(--danger-dark);
            border-left: 3px solid var(--danger);
        }

        .alert-warning {
            background: var(--warning-light);
            color: var(--warning-dark);
            border-left: 3px solid var(--warning);
        }

        /* ---------- LOADING SPINNER ---------- */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .spinner {
            width: 48px;
            height: 48px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* ---------- RESPONSIVE DESIGN ---------- */
        @media (max-width: 1024px) {
            :root {
                --sidebar-width: 280px;
            }
            
            .admin-sidebar {
                transform: translateX(-100%);
            }
            
            .admin-sidebar.show {
                transform: translateX(0);
            }
            
            .admin-main {
                margin-left: 0;
            }
            
            .menu-toggle {
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr) !important;
            }
        }

        @media (max-width: 768px) {
            .container-fluid {
                padding: 1.25rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr !important;
                gap: 1rem;
            }
            
            .stat-card {
                padding: 1.25rem;
            }
            
            .stat-info p {
                font-size: 1.5rem;
            }
            
            .content-header h2 {
                font-size: 1.5rem;
            }
            
            .admin-top-header {
                padding: 0 1.25rem;
            }
            
            .card-header {
                flex-direction: column;
                gap: 0.75rem;
                align-items: flex-start;
            }
            
            .table-responsive {
                margin: 0 -1rem;
                padding: 0 1rem;
            }
            
            .data-table {
                min-width: 650px;
            }
        }

        @media (max-width: 480px) {
            .container-fluid {
                padding: 1rem;
            }
            
            .stat-card {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }
            
            .stat-icon {
                width: 64px;
                height: 64px;
                font-size: 1.75rem;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .admin-info {
                display: none;
            }
            
            .header-right {
                gap: 0.75rem;
            }
        }

        /* ---------- UTILITY CLASSES ---------- */
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .mt-1 { margin-top: 0.5rem; }
        .mt-2 { margin-top: 1rem; }
        .mt-3 { margin-top: 1.5rem; }
        .mb-1 { margin-bottom: 0.5rem; }
        .mb-2 { margin-bottom: 1rem; }
        .mb-3 { margin-bottom: 1.5rem; }
        .mx-auto { margin-left: auto; margin-right: auto; }
        
        /* Print Styles */
        @media print {
            .admin-sidebar,
            .admin-top-header,
            .btn {
                display: none !important;
            }
            .admin-main {
                margin-left: 0 !important;
            }
            .container-fluid {
                padding: 0 !important;
            }
        }

        /* No JavaScript Fallback */
        .no-js-alert {
            background: var(--warning-light);
            color: var(--warning-dark);
            padding: 0.75rem 1rem;
            text-align: center;
            font-size: 0.875rem;
            border-bottom: 1px solid var(--warning);
        }
    </style>
</head>
<body>
    <!-- No JavaScript Fallback -->
    <noscript>
        <div class="no-js-alert">
            <i class="fas fa-exclamation-triangle"></i> 
            JavaScript is recommended for the best admin panel experience. Some features may not work properly.
        </div>
    </noscript>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="spinner"></div>
    </div>

    <!-- Sidebar -->
    <div class="admin-sidebar" id="adminSidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="sidebar-logo">
                <i class="fas fa-bolt"></i>
                <span><?php echo htmlspecialchars(SITE_NAME); ?></span>
            </a>
        </div>
        
        <div class="sidebar-menu">
            <div class="sidebar-heading">Main</div>
            <a href="dashboard.php" class="sidebar-item <?php echo $currentPage == 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="users.php" class="sidebar-item <?php echo $currentPage == 'users.php' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                <span>Users</span>
            </a>
            <a href="transactions.php" class="sidebar-item <?php echo $currentPage == 'transactions.php' ? 'active' : ''; ?>">
                <i class="fas fa-exchange-alt"></i>
                <span>Transactions</span>
            </a>
            
            <div class="sidebar-heading">Services</div>
            <a href="services.php" class="sidebar-item <?php echo $currentPage == 'services.php' ? 'active' : ''; ?>">
                <i class="fas fa-cogs"></i>
                <span>Services</span>
            </a>
            <a href="data_plans.php" class="sidebar-item <?php echo $currentPage == 'data_plans.php' ? 'active' : ''; ?>">
                <i class="fas fa-wifi"></i>
                <span>Data Plans</span>
            </a>
            <a href="cable_packages.php" class="sidebar-item <?php echo $currentPage == 'cable_packages.php' ? 'active' : ''; ?>">
                <i class="fas fa-tv"></i>
                <span>Cable Packages</span>
            </a>
            
            <div class="sidebar-heading">Configuration</div>
            <a href="api_settings.php" class="sidebar-item <?php echo $currentPage == 'api_settings.php' ? 'active' : ''; ?>">
                <i class="fas fa-key"></i>
                <span>API Settings</span>
            </a>
            <a href="settings.php" class="sidebar-item <?php echo $currentPage == 'settings.php' ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i>
                <span>System Settings</span>
            </a>
            <a href="reports.php" class="sidebar-item <?php echo $currentPage == 'reports.php' ? 'active' : ''; ?>">
                <i class="fas fa-file-alt"></i>
                <span>Reports</span>
            </a>
            
            <div class="sidebar-heading">Support</div>
            <a href="tickets.php" class="sidebar-item <?php echo $currentPage == 'tickets.php' ? 'active' : ''; ?>">
                <i class="fas fa-headset"></i>
                <span>Support Tickets</span>
                <?php if ($notificationCount > 0): ?>
                    <span class="sidebar-badge"><?php echo $notificationCount; ?></span>
                <?php endif; ?>
            </a>
            <a href="logs.php" class="sidebar-item <?php echo $currentPage == 'logs.php' ? 'active' : ''; ?>">
                <i class="fas fa-history"></i>
                <span>Activity Logs</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="admin-main">
        <!-- Top Header -->
        <div class="admin-top-header">
            <div class="header-left">
                <button class="menu-toggle" id="menuToggle" aria-label="Toggle Menu">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="page-title"><?php echo htmlspecialchars($pageTitle); ?></div>
            </div>
            
            <div class="header-right">
                <!-- Notifications -->
                <div class="notifications">
                    <button class="notifications-btn" id="notificationsBtn" aria-label="Notifications">
                        <i class="fas fa-bell"></i>
                        <?php if ($notificationCount > 0): ?>
                            <span class="notification-badge"><?php echo $notificationCount; ?></span>
                        <?php endif; ?>
                    </button>
                </div>
                
                <!-- Admin Profile -->
                <div class="admin-profile" id="adminProfile">
                    <div class="admin-avatar">
                        <?php echo htmlspecialchars($adminInitial); ?>
                    </div>
                    <div class="admin-info">
                        <h4><?php echo htmlspecialchars($adminUsername); ?></h4>
                        <p>Administrator</p>
                    </div>
                    
                    <div class="admin-dropdown">
                        <div class="dropdown-header">
                            <strong><?php echo htmlspecialchars($adminUsername); ?></strong>
                            <?php if (!empty($adminEmail)): ?>
                                <p><?php echo htmlspecialchars($adminEmail); ?></p>
                            <?php endif; ?>
                        </div>
                        <a href="profile.php" class="dropdown-item">
                            <i class="fas fa-user-circle"></i> 
                            <span>My Profile</span>
                        </a>
                        <a href="settings.php" class="dropdown-item">
                            <i class="fas fa-sliders-h"></i> 
                            <span>Account Settings</span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="../user/dashboard.php" class="dropdown-item" target="_blank">
                            <i class="fas fa-external-link-alt"></i> 
                            <span>View Site</span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <div class="dropdown-footer">
                            <a href="../auth/logout.php" class="dropdown-item" style="color: var(--danger);">
                                <i class="fas fa-sign-out-alt"></i> 
                                <span>Logout</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
            // ==========================================================================
            // COMPLETE ADMIN PANEL JAVASCRIPT
            // ==========================================================================
            
            (function() {
                'use strict';
                
                // DOM Elements
                const menuToggle = document.getElementById('menuToggle');
                const adminSidebar = document.getElementById('adminSidebar');
                const loadingOverlay = document.getElementById('loadingOverlay');
                
                // Sidebar Toggle Functionality
                if (menuToggle && adminSidebar) {
                    menuToggle.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        adminSidebar.classList.toggle('show');
                    });
                }
                
                // Close sidebar when clicking outside on mobile
                document.addEventListener('click', function(event) {
                    if (window.innerWidth <= 1024 && adminSidebar && menuToggle) {
                        if (!adminSidebar.contains(event.target) && !menuToggle.contains(event.target)) {
                            adminSidebar.classList.remove('show');
                        }
                    }
                });
                
                // Close sidebar when window is resized above mobile breakpoint
                window.addEventListener('resize', function() {
                    if (window.innerWidth > 1024 && adminSidebar) {
                        adminSidebar.classList.remove('show');
                    }
                });
                
                // Loading Overlay Functions
                window.showLoading = function() {
                    if (loadingOverlay) {
                        loadingOverlay.style.display = 'flex';
                    }
                };
                
                window.hideLoading = function() {
                    if (loadingOverlay) {
                        loadingOverlay.style.display = 'none';
                    }
                };
                
                // Auto-hide loading after page load
                window.addEventListener('load', function() {
                    setTimeout(function() {
                        if (loadingOverlay) {
                            loadingOverlay.style.display = 'none';
                        }
                    }, 500);
                });
                
                // Session Activity Tracker (optional - only if needed)
                let sessionTimeout;
                const SESSION_WARNING_TIME = 25 * 60 * 1000; // 25 minutes
                
                function resetSessionTimer() {
                    if (sessionTimeout) {
                        clearTimeout(sessionTimeout);
                    }
                    
                    sessionTimeout = setTimeout(function() {
                        if (confirm('Your session is about to expire in 5 minutes due to inactivity. Click OK to stay logged in.')) {
                            // Refresh session via AJAX if endpoint exists
                            if (typeof fetch === 'function') {
                                fetch('../api/refresh-session.php', {
                                    method: 'POST',
                                    credentials: 'same-origin',
                                    headers: {
                                        'X-Requested-With': 'XMLHttpRequest'
                                    }
                                }).then(function(response) {
                                    if (response.ok) {
                                        resetSessionTimer();
                                    }
                                }).catch(function() {
                                    // Silently fail - session will expire normally
                                });
                            }
                        }
                    }, SESSION_WARNING_TIME);
                }
                
                // Only initialize session timer if we're not on a page that might conflict
                if (typeof window !== 'undefined' && !window.disableSessionTimer) {
                    const activityEvents = ['click', 'mousemove', 'keypress', 'scroll', 'touchstart'];
                    activityEvents.forEach(function(event) {
                        document.addEventListener(event, resetSessionTimer);
                    });
                    resetSessionTimer();
                }
                
                // Keyboard Shortcuts (optional)
                document.addEventListener('keydown', function(e) {
                    // Alt + D - Dashboard
                    if (e.altKey && e.key === 'd') {
                        e.preventDefault();
                        window.location.href = 'dashboard.php';
                    }
                    // Alt + U - Users
                    if (e.altKey && e.key === 'u') {
                        e.preventDefault();
                        window.location.href = 'users.php';
                    }
                    // Alt + T - Transactions
                    if (e.altKey && e.key === 't') {
                        e.preventDefault();
                        window.location.href = 'transactions.php';
                    }
                    // Alt + S - Settings
                    if (e.altKey && e.key === 's') {
                        e.preventDefault();
                        window.location.href = 'settings.php';
                    }
                    // Escape - Close sidebar
                    if (e.key === 'Escape' && window.innerWidth <= 1024 && adminSidebar && adminSidebar.classList.contains('show')) {
                        adminSidebar.classList.remove('show');
                    }
                });
                
                // CSRF Token Helper (for AJAX requests)
                window.getCSRFToken = function() {
                    const metaTag = document.querySelector('meta[name="csrf-token"]');
                    return metaTag ? metaTag.getAttribute('content') : '';
                };
                
                // Auto-add CSRF token to all AJAX requests if fetch is available
                if (typeof fetch === 'function' && window.getCSRFToken) {
                    const originalFetch = window.fetch;
                    window.fetch = function() {
                        const csrfToken = window.getCSRFToken();
                        if (csrfToken && arguments[1] && arguments[1].method && ['POST', 'PUT', 'DELETE', 'PATCH'].includes(arguments[1].method.toUpperCase())) {
                            arguments[1].headers = arguments[1].headers || {};
                            arguments[1].headers['X-CSRF-Token'] = csrfToken;
                        }
                        return originalFetch.apply(this, arguments);
                    };
                }
                
                // Notifications placeholder
                const notificationsBtn = document.getElementById('notificationsBtn');
                if (notificationsBtn) {
                    notificationsBtn.addEventListener('click', function() {
                        // Future notification panel implementation
                        console.log('Notifications clicked');
                    });
                }
                
                // Prevent accidental navigation on unsaved forms
                let formChanged = false;
                const forms = document.querySelectorAll('form');
                forms.forEach(function(form) {
                    form.addEventListener('change', function() {
                        formChanged = true;
                    });
                    
                    form.addEventListener('submit', function() {
                        formChanged = false;
                    });
                });
                
                window.addEventListener('beforeunload', function(e) {
                    if (formChanged) {
                        e.preventDefault();
                        e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
                        return e.returnValue;
                    }
                });
                
                // Console warning for development (remove in production)
                if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
                    console.log('%c⚠️ Admin Panel Loaded', 'color: #f59e0b; font-size: 12px;');
                }
            })();
        </script>
        
        <?php if (isset($csrfToken) && $csrfToken): ?>
        <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken); ?>">
        <?php endif; ?>