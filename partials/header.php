<?php
// partials/header.php
// This header is for public pages (home, about, contact, etc.)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo SITE_NAME; ?> - Instant Airtime, Data & Bill Payments in Nigeria">
    <meta name="keywords" content="VTU, airtime, data, electricity bills, cable TV, exam pins, Nigeria">
    <meta name="author" content="<?php echo SITE_NAME; ?>">
    
    <!-- Open Graph / Social Media Meta Tags -->
    <meta property="og:title" content="<?php echo SITE_NAME; ?> - <?php echo SITE_TAGLINE; ?>">
    <meta property="og:description" content="Pay all your bills instantly with the best rates in Nigeria. No hidden fees, no delays.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo SITE_URL; ?>">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo asset('images/favicon.png'); ?>">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Main CSS -->
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' . SITE_NAME : SITE_NAME . ' - ' . SITE_TAGLINE; ?></title>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="container">
            <!-- Logo -->
            <a href="<?php echo url('index.php'); ?>" class="logo">
                <i class="fas fa-bolt logo-icon"></i>
                <span class="logo-text"><?php echo SITE_NAME; ?></span>
            </a>
            
            <!-- Desktop Navigation -->
            <ul class="nav-links">
                <li><a href="<?php echo url('index.php#features'); ?>"><i class="fas fa-star"></i> Features</a></li>
                <li><a href="<?php echo url('index.php#services'); ?>"><i class="fas fa-cogs"></i> Services</a></li>
                <li><a href="<?php echo url('index.php#pricing'); ?>"><i class="fas fa-tags"></i> Pricing</a></li>
                <li><a href="<?php echo url('contact.php'); ?>"><i class="fas fa-envelope"></i> Contact</a></li>
            </ul>
            
            <!-- Desktop Action Buttons -->
            <div class="nav-buttons">
                <?php if (Session::isLoggedIn()): ?>
                    <a href="<?php echo url('user/dashboard.php'); ?>" class="btn btn-outline">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a href="<?php echo url('auth/logout.php'); ?>" class="btn btn-primary">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                <?php else: ?>
                    <a href="<?php echo url('auth/login.php'); ?>" class="btn btn-outline">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </a>
                    <a href="<?php echo url('auth/register.php'); ?>" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Sign Up
                    </a>
                <?php endif; ?>
            </div>
            
            <!-- Mobile Menu Button -->
            <button class="mobile-menu-btn" id="mobileMenuBtn">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        
        <!-- Mobile Menu (Hidden by default) -->
        <div class="mobile-menu" id="mobileMenu">
            <div class="container">
                <ul class="mobile-nav-links">
                    <li><a href="<?php echo url('index.php#features'); ?>"><i class="fas fa-star"></i> Features</a></li>
                    <li><a href="<?php echo url('index.php#services'); ?>"><i class="fas fa-cogs"></i> Services</a></li>
                    <li><a href="<?php echo url('index.php#pricing'); ?>"><i class="fas fa-tags"></i> Pricing</a></li>
                    <li><a href="<?php echo url('contact.php'); ?>"><i class="fas fa-envelope"></i> Contact</a></li>
                    
                    <?php if (Session::isLoggedIn()): ?>
                        <li class="mobile-divider"></li>
                        <li><a href="<?php echo url('user/dashboard.php'); ?>"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                        <li><a href="<?php echo url('user/wallet.php'); ?>"><i class="fas fa-wallet"></i> Wallet</a></li>
                        <li><a href="<?php echo url('user/transactions.php'); ?>"><i class="fas fa-history"></i> Transactions</a></li>
                        <li><a href="<?php echo url('user/profile.php'); ?>"><i class="fas fa-user"></i> Profile</a></li>
                        <li class="mobile-divider"></li>
                        <li><a href="<?php echo url('auth/logout.php'); ?>" class="text-danger"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    <?php else: ?>
                        <li class="mobile-divider"></li>
                        <li><a href="<?php echo url('auth/login.php'); ?>"><i class="fas fa-sign-in-alt"></i> Login</a></li>
                        <li><a href="<?php echo url('auth/register.php'); ?>"><i class="fas fa-user-plus"></i> Sign Up</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Page Content Start -->
    <main class="main-content">