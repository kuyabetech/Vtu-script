<?php
// partials/user_header.php
// This header is for authenticated user pages

// Ensure user is logged in
if (!isset($user) || empty($user)) {
    $user = Auth::user();
}

$walletBalance = getUserBalance(Session::userId());
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' . SITE_NAME : 'Dashboard - ' . SITE_NAME; ?></title>
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Internal CSS for User Header -->
    <style>
        /* ===== CSS Variables ===== */
        :root {
            --primary: #6366f1;
            --primary-dark: #4f52e0;
            --primary-light: #818cf8;
            --secondary: #8b5cf6;
            --secondary-light: #a78bfa;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            
            --dark: #1f2937;
            --darker: #111827;
            --light: #f9fafb;
            --lighter: #ffffff;
            --gray: #6b7280;
            --gray-light: #e5e7eb;
            --gray-dark: #4b5563;
            
            --bg-color: #f3f4f6;
            --card-bg: #ffffff;
            
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            
            --radius-sm: 0.5rem;
            --radius: 0.75rem;
            --radius-lg: 1rem;
            --radius-xl: 1.5rem;
            
            --transition: all 0.3s ease;
            
            /* Layout */
            --header-height: 70px;
            --bottom-nav-height: 70px;
            --sidebar-width: 280px;
        }

        /* ===== Reset & Base ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background-color: var(--bg-color);
            color: var(--dark);
            line-height: 1.5;
            min-height: 100vh;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        /* ===== Container ===== */
        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        /* ===== Desktop Sidebar ===== */
        .desktop-sidebar {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--lighter) 0%, #fafafa 100%);
            border-right: 1px solid var(--gray-light);
            padding: 2rem 1rem;
            overflow-y: auto;
            display: none;
            z-index: 100;
            box-shadow: var(--shadow);
        }

        @media (min-width: 1024px) {
            .desktop-sidebar {
                display: block;
            }
            
            .main-content {
                margin-left: var(--sidebar-width);
            }
        }

        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 2.5rem;
            padding: 0 0.75rem;
        }

        .sidebar-logo i {
            font-size: 2rem;
            color: var(--primary);
        }

        .sidebar-logo span {
            font-size: 1.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .sidebar-menu {
            list-style: none;
        }

        .sidebar-menu-item {
            margin-bottom: 0.5rem;
        }

        .sidebar-menu-link {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.875rem 1rem;
            color: var(--gray-dark);
            text-decoration: none;
            border-radius: var(--radius-lg);
            transition: var(--transition);
            font-weight: 500;
        }

        .sidebar-menu-link i {
            width: 24px;
            font-size: 1.25rem;
            color: var(--gray);
            transition: var(--transition);
        }

        .sidebar-menu-link:hover {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));
            color: var(--primary);
        }

        .sidebar-menu-link:hover i {
            color: var(--primary);
        }

        .sidebar-menu-link.active {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
        }

        .sidebar-menu-link.active i {
            color: white;
        }

        .sidebar-divider {
            height: 1px;
            background: var(--gray-light);
            margin: 1.5rem 0;
        }

        .sidebar-heading {
            padding: 0 1rem;
            margin-bottom: 0.75rem;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--gray);
            font-weight: 600;
        }

        /* ===== Mobile Header ===== */
        .mobile-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 1rem;
            position: sticky;
            top: 0;
            z-index: 90;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: var(--shadow);
        }

        @media (min-width: 1024px) {
            .mobile-header {
                display: none;
            }
        }

        .mobile-header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .mobile-menu-btn {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            transition: var(--transition);
        }

        .mobile-menu-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .mobile-logo {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.25rem;
            font-weight: 600;
        }

        .mobile-logo i {
            font-size: 1.5rem;
        }

        .mobile-header-right {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .mobile-header-icon {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .mobile-header-icon:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .balance-badge {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            backdrop-filter: blur(4px);
        }

        .balance-badge i {
            font-size: 1rem;
        }

        /* ===== Mobile Menu ===== */
        .mobile-menu-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 200;
            display: none;
            backdrop-filter: blur(4px);
        }

        .mobile-menu-overlay.active {
            display: block;
        }

        .mobile-menu-panel {
            position: fixed;
            top: 0;
            left: -300px;
            bottom: 0;
            width: 280px;
            background: white;
            z-index: 201;
            transition: var(--transition);
            padding: 2rem 1rem;
            overflow-y: auto;
            box-shadow: var(--shadow-xl);
        }

        .mobile-menu-panel.active {
            left: 0;
        }

        .mobile-menu-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: 40px;
            height: 40px;
            background: var(--light);
            border: none;
            border-radius: 50%;
            font-size: 1.25rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray);
            transition: var(--transition);
        }

        .mobile-menu-close:hover {
            background: var(--gray-light);
            color: var(--dark);
        }

        .mobile-menu-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--gray-light);
        }

        .mobile-menu-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            font-weight: 600;
            box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
        }

        .mobile-menu-user h4 {
            margin-bottom: 0.25rem;
            color: var(--dark);
            font-size: 1rem;
        }

        .mobile-menu-user p {
            font-size: 0.875rem;
            color: var(--gray);
        }

        /* ===== User Dropdown ===== */
        .user-dropdown {
            position: relative;
            display: inline-block;
        }

        .user-dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background: white;
            min-width: 220px;
            box-shadow: var(--shadow-lg);
            border-radius: var(--radius-lg);
            z-index: 1000;
            margin-top: 10px;
            border: 1px solid var(--gray-light);
        }

        .user-dropdown:hover .user-dropdown-content {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .user-dropdown-content a {
            color: var(--dark);
            padding: 12px 16px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: var(--transition);
            font-size: 0.9rem;
        }

        .user-dropdown-content a:hover {
            background: var(--light);
            color: var(--primary);
        }

        .user-dropdown-content i {
            width: 20px;
            color: var(--primary);
            font-size: 1rem;
        }

        .user-dropdown-content .dropdown-divider {
            height: 1px;
            background: var(--gray-light);
            margin: 8px 0;
        }

        .user-dropdown-header {
            padding: 12px 16px;
            border-bottom: 1px solid var(--gray-light);
        }

        .user-dropdown-header strong {
            display: block;
            color: var(--dark);
            font-size: 0.95rem;
        }

        .user-dropdown-header small {
            color: var(--gray);
            font-size: 0.8rem;
        }

        /* ===== User Avatar ===== */
        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.2rem;
            text-transform: uppercase;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
        }

        /* ===== User Menu ===== */
        .user-menu {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .user-info {
            display: none;
            align-items: center;
            gap: 0.5rem;
            color: var(--gray-dark);
        }

        .user-info i {
            color: var(--primary);
        }

        @media (min-width: 1024px) {
            .user-info {
                display: flex;
            }
        }

        /* ===== Bottom Navigation ===== */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: var(--bottom-nav-height);
            background: white;
            border-top: 1px solid var(--gray-light);
            display: flex;
            justify-content: space-around;
            align-items: center;
            padding: 0 0.5rem;
            z-index: 100;
            box-shadow: 0 -4px 10px rgba(0, 0, 0, 0.05);
        }

        @media (min-width: 1024px) {
            .bottom-nav {
                display: none;
            }
        }

        .bottom-nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.25rem;
            color: var(--gray);
            text-decoration: none;
            font-size: 0.7rem;
            padding: 0.5rem 1rem;
            border-radius: var(--radius-lg);
            transition: var(--transition);
            flex: 1;
        }

        .bottom-nav-item i {
            font-size: 1.25rem;
        }

        .bottom-nav-item.active {
            color: var(--primary);
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));
        }

        .bottom-nav-item span {
            font-weight: 500;
        }

        /* ===== Main Content Area ===== */
        .main-content {
            min-height: 100vh;
            padding: 1rem 0 calc(var(--bottom-nav-height) + 1rem) 0;
        }

        @media (min-width: 1024px) {
            .main-content {
                padding: 1.5rem 2rem;
                padding-bottom: 1.5rem;
            }
        }

        /* ===== Cards ===== */
        .card {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: var(--transition);
        }

        .card:hover {
            box-shadow: var(--shadow-lg);
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .card-header h3 {
            margin-bottom: 0;
            font-size: 1.125rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--dark);
        }

        .card-header i {
            color: var(--primary);
            font-size: 1.25rem;
        }

        /* ===== Welcome Card ===== */
        .welcome-card {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-lg);
        }

        .welcome-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, transparent 70%);
            transform: rotate(30deg);
            pointer-events: none;
        }

        .welcome-card h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            position: relative;
            color: white;
        }

        .welcome-card p {
            opacity: 0.9;
            margin-bottom: 1.5rem;
            position: relative;
            color: rgba(255,255,255,0.9);
        }

        .welcome-stats {
            display: flex;
            gap: 2rem;
            position: relative;
            flex-wrap: wrap;
        }

        .welcome-stat {
            text-align: center;
            flex: 1;
            min-width: 80px;
        }

        .welcome-stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            display: block;
            margin-bottom: 0.25rem;
            color: white;
        }

        .welcome-stat-label {
            font-size: 0.75rem;
            opacity: 0.8;
            color: rgba(255,255,255,0.9);
        }

        /* ===== Quick Actions Grid ===== */
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.75rem;
            margin-bottom: 0.5rem;
        }

        @media (max-width: 480px) {
            .quick-actions-grid {
                grid-template-columns: repeat(4, 1fr);
                gap: 0.5rem;
            }
        }

        .quick-action-item {
            background: white;
            border-radius: var(--radius-lg);
            padding: 1rem 0.5rem;
            text-align: center;
            text-decoration: none;
            color: var(--dark);
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            border: 1px solid var(--gray-light);
        }

        .quick-action-item:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow);
            border-color: var(--primary);
        }

        .quick-action-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.5rem;
            color: var(--primary);
            font-size: 1.25rem;
            transition: var(--transition);
        }

        .quick-action-item:hover .quick-action-icon {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }

        .quick-action-item span {
            font-size: 0.75rem;
            font-weight: 500;
            display: block;
        }

        /* ===== Services Grid ===== */
        .services-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 0.5rem;
        }

        @media (min-width: 768px) {
            .services-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        .service-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 1.25rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            cursor: pointer;
            border: 1px solid var(--gray-light);
        }

        .service-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
            border-color: var(--primary);
        }

        .service-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
            box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
        }

        .service-info h4 {
            margin-bottom: 0.25rem;
            font-size: 1rem;
            color: var(--dark);
        }

        .service-info p {
            font-size: 0.75rem;
            color: var(--gray);
            margin: 0;
        }

        /* ===== Transaction List ===== */
        .transaction-list {
            background: white;
            border-radius: var(--radius-lg);
            overflow: hidden;
        }

        .transaction-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            border-bottom: 1px solid var(--gray-light);
            transition: var(--transition);
        }

        .transaction-item:last-child {
            border-bottom: none;
        }

        .transaction-item:hover {
            background: var(--light);
        }

        .transaction-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .transaction-icon {
            width: 40px;
            height: 40px;
            background: var(--light);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 1rem;
        }

        .transaction-details h4 {
            margin-bottom: 0.25rem;
            font-size: 0.95rem;
            color: var(--dark);
        }

        .transaction-details p {
            font-size: 0.75rem;
            color: var(--gray);
            margin: 0;
        }

        .transaction-amount {
            font-weight: 600;
            font-size: 1rem;
        }

        .transaction-amount.positive {
            color: var(--success);
        }

        .transaction-amount.negative {
            color: var(--danger);
        }

        /* ===== Badges ===== */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            margin-top: 0.25rem;
        }

        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-failed {
            background: #fee2e2;
            color: #991b1b;
        }

        /* ===== Buttons ===== */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            font-weight: 500;
            border-radius: var(--radius-lg);
            cursor: pointer;
            transition: var(--transition);
            border: none;
            outline: none;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(99, 102, 241, 0.4);
        }

        .btn-light {
            background: white;
            color: var(--primary);
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-light);
        }

        .btn-light:hover {
            background: var(--light);
            transform: translateY(-2px);
        }

        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        .btn-block {
            width: 100%;
        }

        /* ===== Alerts ===== */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: var(--radius-lg);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid var(--success);
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid var(--danger);
        }

        .alert i {
            font-size: 1.25rem;
        }

        /* ===== Empty State ===== */
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: var(--gray);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state p {
            margin-bottom: 1rem;
        }

        /* ===== Loading Spinner ===== */
        .spinner {
            width: 40px;
            height: 40px;
            border: 3px solid var(--gray-light);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 2rem auto;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* ===== Utilities ===== */
        .text-center {
            text-align: center;
        }

        .mt-2 {
            margin-top: 1rem;
        }

        .mb-2 {
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <!-- Desktop Sidebar -->
    <div class="desktop-sidebar">
        <div class="sidebar-logo">
            <i class="fas fa-bolt"></i>
            <span><?php echo SITE_NAME; ?></span>
        </div>
        
        <ul class="sidebar-menu">
            <li class="sidebar-menu-item">
                <a href="dashboard.php" class="sidebar-menu-link <?php echo $currentPage == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="sidebar-menu-item">
                <a href="wallet.php" class="sidebar-menu-link">
                    <i class="fas fa-wallet"></i>
                    <span>My Wallet</span>
                </a>
            </li>
            <li class="sidebar-menu-item">
                <a href="transactions.php" class="sidebar-menu-link">
                    <i class="fas fa-history"></i>
                    <span>Transactions</span>
                </a>
            </li>
            
            <div class="sidebar-divider"></div>
            <div class="sidebar-heading">Services</div>
            
            <li class="sidebar-menu-item">
                <a href="buy_airtime.php" class="sidebar-menu-link">
                    <i class="fas fa-phone-alt"></i>
                    <span>Airtime</span>
                </a>
            </li>
            <li class="sidebar-menu-item">
                <a href="buy_data.php" class="sidebar-menu-link">
                    <i class="fas fa-wifi"></i>
                    <span>Data Bundle</span>
                </a>
            </li>
            <li class="sidebar-menu-item">
                <a href="pay_electricity.php" class="sidebar-menu-link">
                    <i class="fas fa-bolt"></i>
                    <span>Electricity</span>
                </a>
            </li>
            <li class="sidebar-menu-item">
                <a href="pay_cable.php" class="sidebar-menu-link">
                    <i class="fas fa-tv"></i>
                    <span>Cable TV</span>
                </a>
            </li>
            
            <div class="sidebar-divider"></div>
            
            <li class="sidebar-menu-item">
                <a href="referrals.php" class="sidebar-menu-link">
                    <i class="fas fa-gift"></i>
                    <span>Referrals</span>
                </a>
            </li>
            <li class="sidebar-menu-item">
                <a href="profile.php" class="sidebar-menu-link">
                    <i class="fas fa-user-cog"></i>
                    <span>Profile</span>
                </a>
            </li>
            <li class="sidebar-menu-item">
                <a href="../auth/logout.php" class="sidebar-menu-link" style="color: var(--danger);">
                    <i class="fas fa-sign-out-alt" style="color: var(--danger);"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Mobile Header -->
    <div class="mobile-header">
        <div class="mobile-header-left">
            <button class="mobile-menu-btn" id="mobileMenuBtn">
                <i class="fas fa-bars"></i>
            </button>
            <div class="mobile-logo">
                <i class="fas fa-bolt"></i>
                <span><?php echo SITE_NAME; ?></span>
            </div>
        </div>
        <div class="mobile-header-right">
            <div class="balance-badge">
                <i class="fas fa-wallet"></i>
                <span><?php echo format_money($walletBalance); ?></span>
            </div>
            <div class="mobile-header-icon" onclick="location.href='notifications.php'">
                <i class="fas fa-bell"></i>
            </div>
        </div>
    </div>

    <!-- Mobile Menu Overlay -->
    <div class="mobile-menu-overlay" id="mobileMenuOverlay"></div>
    
    <!-- Mobile Menu Panel -->
    <div class="mobile-menu-panel" id="mobileMenuPanel">
        <button class="mobile-menu-close" id="mobileMenuClose">
            <i class="fas fa-times"></i>
        </button>
        
        <div class="mobile-menu-header">
            <div class="mobile-menu-avatar">
                <?php echo strtoupper(substr($user['first_name'] ?: $user['username'], 0, 1)); ?>
            </div>
            <div class="mobile-menu-user">
                <h4><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h4>
                <p><?php echo htmlspecialchars($user['email']); ?></p>
            </div>
        </div>
        
        <ul class="sidebar-menu">
            <li class="sidebar-menu-item">
                <a href="dashboard.php" class="sidebar-menu-link <?php echo $currentPage == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="sidebar-menu-item">
                <a href="wallet.php" class="sidebar-menu-link">
                    <i class="fas fa-wallet"></i>
                    <span>My Wallet</span>
                </a>
            </li>
            <li class="sidebar-menu-item">
                <a href="transactions.php" class="sidebar-menu-link">
                    <i class="fas fa-history"></i>
                    <span>Transactions</span>
                </a>
            </li>
            
            <div class="sidebar-divider"></div>
            <div class="sidebar-heading">Services</div>
            
            <li class="sidebar-menu-item">
                <a href="buy_airtime.php" class="sidebar-menu-link">
                    <i class="fas fa-phone-alt"></i>
                    <span>Airtime</span>
                </a>
            </li>
            <li class="sidebar-menu-item">
                <a href="buy_data.php" class="sidebar-menu-link">
                    <i class="fas fa-wifi"></i>
                    <span>Data Bundle</span>
                </a>
            </li>
            <li class="sidebar-menu-item">
                <a href="pay_electricity.php" class="sidebar-menu-link">
                    <i class="fas fa-bolt"></i>
                    <span>Electricity</span>
                </a>
            </li>
            <li class="sidebar-menu-item">
                <a href="pay_cable.php" class="sidebar-menu-link">
                    <i class="fas fa-tv"></i>
                    <span>Cable TV</span>
                </a>
            </li>
            
            <div class="sidebar-divider"></div>
            
            <li class="sidebar-menu-item">
                <a href="referrals.php" class="sidebar-menu-link">
                    <i class="fas fa-gift"></i>
                    <span>Referrals</span>
                </a>
            </li>
            <li class="sidebar-menu-item">
                <a href="profile.php" class="sidebar-menu-link">
                    <i class="fas fa-user-cog"></i>
                    <span>Profile</span>
                </a>
            </li>
            <li class="sidebar-menu-item">
                <a href="../auth/logout.php" class="sidebar-menu-link" style="color: var(--danger);">
                    <i class="fas fa-sign-out-alt" style="color: var(--danger);"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- JavaScript for Mobile Menu -->
    <script>
    // Mobile Menu Toggle
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const mobileMenuClose = document.getElementById('mobileMenuClose');
    const mobileMenuOverlay = document.getElementById('mobileMenuOverlay');
    const mobileMenuPanel = document.getElementById('mobileMenuPanel');

    if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', function() {
            mobileMenuPanel.classList.add('active');
            mobileMenuOverlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        });
    }

    if (mobileMenuClose) {
        mobileMenuClose.addEventListener('click', closeMobileMenu);
    }

    if (mobileMenuOverlay) {
        mobileMenuOverlay.addEventListener('click', closeMobileMenu);
    }

    function closeMobileMenu() {
        mobileMenuPanel.classList.remove('active');
        mobileMenuOverlay.classList.remove('active');
        document.body.style.overflow = '';
    }
    </script>