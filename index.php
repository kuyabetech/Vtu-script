<?php
// index.php - Professional Landing Page (Database Driven)
require_once 'config.php';
require_once 'includes/session.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

$db = db();

// Get all settings from database
$settings = [];
$result = $db->query("SELECT `key`, `value` FROM settings");
while ($row = $result->fetch_assoc()) {
    $settings[$row['key']] = $row['value'];
}

// Get active services from database
$services = [];
$result = $db->query("SELECT * FROM services WHERE is_active = 1 ORDER BY category, name");
while ($row = $result->fetch_assoc()) {
    $category = $row['category'];
    if (!isset($services[$category])) {
        $services[$category] = [
            'name' => ucfirst($category),
            'items' => []
        ];
    }
    $services[$category]['items'][] = $row;
}

// Get stats from database
$stats = [];
$result = $db->query("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
$stats['users'] = $result->fetch_assoc()['total'];

$result = $db->query("SELECT COUNT(*) as total FROM transactions WHERE status = 'success'");
$stats['transactions'] = $result->fetch_assoc()['total'];

// Site settings with defaults
$site_name = $settings['site_name'] ?? SITE_NAME;
$site_tagline = $settings['site_tagline'] ?? 'Pay Bills Instantly';
$site_email = $settings['site_email'] ?? SITE_EMAIL;
$site_phone = $settings['site_phone'] ?? SITE_PHONE;
$hero_title = $settings['hero_title'] ?? 'Instant Airtime, Data & Bill Payments';
$hero_subtitle = $settings['hero_subtitle'] ?? 'Pay all your bills instantly with the best rates in Nigeria. No hidden fees, no delays.';
$features_title = $settings['features_title'] ?? 'Everything You Need in One Platform';
$features_subtitle = $settings['features_subtitle'] ?? 'We provide the fastest and most reliable VTU services at the best rates';
$services_title = $settings['services_title'] ?? 'What We Offer';
$services_subtitle = $settings['services_subtitle'] ?? 'Choose from a wide range of services at competitive prices';
$cta_title = $settings['cta_title'] ?? 'Ready to Get Started?';
$cta_subtitle = $settings['cta_subtitle'] ?? 'Join thousands of happy customers using our platform for their daily transactions';
$footer_text = $settings['footer_text'] ?? 'Your trusted platform for instant airtime, data, and bill payments. Fast, secure, and reliable.';

// Redirect if logged in
if (Session::isLoggedIn()) {
    if (Session::isAdmin()) {
        redirect('admin/dashboard.php');
    } else {
        redirect('user/dashboard.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $site_name; ?> - <?php echo $site_tagline; ?></title>
    <meta name="description" content="<?php echo $hero_subtitle; ?>">
    <meta name="keywords" content="VTU, airtime, data, electricity bills, cable TV, exam pins, Nigeria">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* ===== Professional VTU Platform Styles ===== */
        
        /* CSS Variables */
        :root {
            --primary: <?php echo $settings['primary_color'] ?? '#6366f1'; ?>;
            --primary-dark: <?php echo $settings['primary_dark'] ?? '#4f52e0'; ?>;
            --primary-light: <?php echo $settings['primary_light'] ?? '#818cf8'; ?>;
            --secondary: <?php echo $settings['secondary_color'] ?? '#8b5cf6'; ?>;
            --secondary-light: <?php echo $settings['secondary_light'] ?? '#a78bfa'; ?>;
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
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            --radius-sm: 0.5rem;
            --radius: 0.75rem;
            --radius-lg: 1rem;
            --radius-xl: 1.5rem;
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: var(--lighter);
            color: var(--dark);
            line-height: 1.6;
        }

        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        /* ===== Navbar ===== */
        .navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: var(--shadow-sm);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            padding: 1rem 0;
        }

        .navbar .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            font-size: 1.5rem;
            font-weight: 700;
        }

        .logo-icon {
            font-size: 1.8rem;
            color: var(--primary);
        }

        .logo-text {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
            list-style: none;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--gray-dark);
            font-weight: 500;
            transition: var(--transition);
        }

        .nav-links a:hover {
            color: var(--primary);
        }

        .nav-buttons {
            display: flex;
            gap: 1rem;
        }

        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--dark);
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
            border-radius: var(--radius);
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            border: none;
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

        .btn-outline {
            background: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
        }

        .btn-outline:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
        }

        .btn-large {
            padding: 1rem 2rem;
            font-size: 1.125rem;
        }

        /* ===== Hero Section ===== */
        .hero {
            background: linear-gradient(135deg, <?php echo $settings['hero_gradient_start'] ?? '#667eea'; ?> 0%, <?php echo $settings['hero_gradient_end'] ?? '#764ba2'; ?> 100%);
            color: white;
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 8rem 0 4rem;
        }

        .hero-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
        }

        .hero-content h1 {
            font-size: 3.5rem;
            margin-bottom: 1.5rem;
            line-height: 1.2;
        }

        .hero-subtitle {
            font-size: 1.25rem;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 2rem;
        }

        .hero-stats {
            display: flex;
            gap: 3rem;
            margin-bottom: 2.5rem;
        }

        .stat-value {
            display: block;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.875rem;
        }

        .hero-buttons {
            display: flex;
            gap: 1rem;
        }

        .hero-buttons .btn-outline {
            border-color: white;
            color: white;
        }

        .hero-buttons .btn-outline:hover {
            background: white;
            color: var(--primary);
        }

        /* Phone Mockup */
        .phone-mockup {
            width: 300px;
            height: 600px;
            background: var(--darker);
            border-radius: 40px;
            padding: 10px;
            box-shadow: var(--shadow-xl);
            margin: 0 auto;
            position: relative;
        }

        .phone-mockup::before {
            content: '';
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 150px;
            height: 30px;
            background: var(--darker);
            border-radius: 0 0 20px 20px;
        }

        .phone-screen {
            width: 100%;
            height: 100%;
            background: var(--lighter);
            border-radius: 30px;
            overflow: hidden;
        }

        .app-preview {
            padding: 1rem;
        }

        .preview-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 1rem;
            text-align: center;
        }

        .preview-services {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.5rem;
        }

        .service-item {
            background: var(--light);
            padding: 0.75rem;
            border-radius: var(--radius);
            text-align: center;
            font-size: 0.875rem;
            color: var(--dark);
        }

        /* ===== Sections ===== */
        .features, .services, .how-it-works {
            padding: 5rem 0;
        }

        .section-header {
            text-align: center;
            max-width: 600px;
            margin: 0 auto 3rem;
        }

        .section-tag {
            display: inline-block;
            padding: 0.25rem 1rem;
            background: linear-gradient(135deg, var(--primary-light), var(--secondary));
            color: white;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 1rem;
        }

        .section-header h2 {
            color: var(--dark);
            margin-bottom: 1rem;
            font-size: 2.5rem;
        }

        .section-header p {
            color: var(--gray);
            font-size: 1.125rem;
        }

        /* Features Grid */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
        }

        .feature-card {
            background: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            text-align: center;
            transition: var(--transition);
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .feature-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
        }

        .feature-card h3 {
            margin-bottom: 0.5rem;
        }

        .feature-card p {
            color: var(--gray);
        }

        /* Services Section */
        .services {
            background: linear-gradient(135deg, <?php echo $settings['services_gradient_start'] ?? '#667eea'; ?> 0%, <?php echo $settings['services_gradient_end'] ?? '#764ba2'; ?> 100%);
            color: white;
        }

        .services .section-header h2,
        .services .section-header p {
            color: white;
        }

        .services .section-header p {
            color: rgba(255, 255, 255, 0.9);
        }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
        }

        .service-card {
            background: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            text-align: center;
            transition: var(--transition);
            cursor: pointer;
        }

        .service-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
        }

        .service-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .service-card h3 {
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .service-card p {
            color: var(--gray);
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }

        .service-price {
            display: inline-block;
            padding: 0.25rem 1rem;
            background: linear-gradient(135deg, var(--primary-light), var(--secondary));
            color: white;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        /* How It Works */
        .how-it-works {
            background: var(--lighter);
        }

        .steps {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 3rem;
            margin-top: 3rem;
        }

        .step {
            text-align: center;
            position: relative;
        }

        .step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 3rem;
            right: -1.5rem;
            width: 3rem;
            height: 2px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }

        .step-number {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            margin: 0 auto 1rem;
        }

        .step-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .step h3 {
            margin-bottom: 0.5rem;
        }

        .step p {
            color: var(--gray);
        }

        /* CTA Section */
        .cta {
            padding: 5rem 0;
            background: linear-gradient(135deg, <?php echo $settings['cta_gradient_start'] ?? '#667eea'; ?> 0%, <?php echo $settings['cta_gradient_end'] ?? '#764ba2'; ?> 100%);
            text-align: center;
        }

        .cta-content {
            max-width: 600px;
            margin: 0 auto;
        }

        .cta h2 {
            color: white;
            margin-bottom: 1rem;
        }

        .cta p {
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 2rem;
            font-size: 1.125rem;
        }

        .cta .btn-primary {
            background: white;
            color: var(--primary);
        }

        .cta .btn-primary:hover {
            background: var(--light);
            transform: translateY(-2px);
        }

        /* Footer */
        .footer {
            background: var(--darker);
            color: white;
            padding: 4rem 0 2rem;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 3rem;
            margin-bottom: 3rem;
        }

        .footer-logo {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .footer-about p {
            color: var(--gray-light);
            margin-bottom: 1.5rem;
        }

        .social-links {
            display: flex;
            gap: 1rem;
        }

        .social-link {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            transition: var(--transition);
        }

        .social-link:hover {
            background: var(--primary);
            transform: translateY(-2px);
        }

        .footer-links h4,
        .footer-contact h4 {
            color: white;
            margin-bottom: 1.5rem;
        }

        .footer-links ul {
            list-style: none;
        }

        .footer-links li {
            margin-bottom: 0.75rem;
        }

        .footer-links a {
            color: var(--gray-light);
            text-decoration: none;
            transition: var(--transition);
        }

        .footer-links a:hover {
            color: white;
            padding-left: 0.5rem;
        }

        .footer-contact p {
            color: var(--gray-light);
            margin-bottom: 0.75rem;
        }

        .footer-bottom {
            padding-top: 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
            color: var(--gray-light);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .hero-grid {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .hero-stats {
                justify-content: center;
            }

            .hero-buttons {
                justify-content: center;
            }

            .steps {
                grid-template-columns: 1fr;
                gap: 2rem;
            }

            .step:not(:last-child)::after {
                display: none;
            }

            .footer-grid {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
        }

        @media (max-width: 768px) {
            .nav-links,
            .nav-buttons {
                display: none;
            }

            .mobile-menu-btn {
                display: block;
            }

            .hero-content h1 {
                font-size: 2.5rem;
            }

            .services-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .container {
                padding: 0 1rem;
            }
        }

        @media (max-width: 480px) {
            .hero-stats {
                flex-direction: column;
                gap: 1rem;
                align-items: center;
            }

            .hero-buttons {
                flex-direction: column;
            }

            .services-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="container">
            <a href="index.php" class="logo">
                <i class="fas fa-bolt logo-icon"></i>
                <span class="logo-text"><?php echo $site_name; ?></span>
            </a>
            
            <ul class="nav-links">
                <li><a href="#features"><i class="fas fa-star"></i> Features</a></li>
                <li><a href="#services"><i class="fas fa-cogs"></i> Services</a></li>
                <li><a href="#how-it-works"><i class="fas fa-magic"></i> How It Works</a></li>
                <li><a href="#contact"><i class="fas fa-envelope"></i> Contact</a></li>
            </ul>
            
            <div class="nav-buttons">
                <a href="auth/login.php" class="btn btn-outline"><i class="fas fa-sign-in-alt"></i> Login</a>
                <a href="auth/register.php" class="btn btn-primary"><i class="fas fa-user-plus"></i> Get Started</a>
            </div>
            
            <button class="mobile-menu-btn"><i class="fas fa-bars"></i></button>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="hero-grid">
                <div class="hero-content">
                    <h1><?php echo $hero_title; ?></h1>
                    <p class="hero-subtitle"><?php echo $hero_subtitle; ?></p>
                    
                    <div class="hero-stats">
                        <div class="stat">
                            <span class="stat-value"><?php echo number_format($stats['users']); ?>+</span>
                            <span class="stat-label"><i class="fas fa-users"></i> Happy Customers</span>
                        </div>
                        <div class="stat">
                            <span class="stat-value"><?php echo number_format($stats['transactions']); ?>+</span>
                            <span class="stat-label"><i class="fas fa-exchange-alt"></i> Transactions</span>
                        </div>
                        <div class="stat">
                            <span class="stat-value">24/7</span>
                            <span class="stat-label"><i class="fas fa-headset"></i> Support</span>
                        </div>
                    </div>
                    
                    <div class="hero-buttons">
                        <a href="auth/register.php" class="btn btn-primary btn-large"><i class="fas fa-user-plus"></i> Create Free Account</a>
                        <a href="#how-it-works" class="btn btn-outline btn-large">How It Works <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
                
                <div class="hero-image">
                    <div class="phone-mockup">
                        <div class="phone-screen">
                            <div class="app-preview">
                                <div class="preview-header">
                                    <i class="fas fa-wallet"></i> Balance: ₦5,000
                                </div>
                                <div class="preview-services">
                                    <div class="service-item"><i class="fas fa-phone-alt"></i> Airtime</div>
                                    <div class="service-item"><i class="fas fa-wifi"></i> Data</div>
                                    <div class="service-item"><i class="fas fa-bolt"></i> Electricity</div>
                                    <div class="service-item"><i class="fas fa-tv"></i> Cable TV</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features">
        <div class="container">
            <div class="section-header">
                <span class="section-tag"><i class="fas fa-star"></i> Why Choose Us</span>
                <h2><?php echo $features_title; ?></h2>
                <p><?php echo $features_subtitle; ?></p>
            </div>
            
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-bolt"></i></div>
                    <h3>Instant Delivery</h3>
                    <p>Get your airtime, data, and bill payments delivered within seconds</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-naira-sign"></i></div>
                    <h3>Best Rates</h3>
                    <p>Save up to 5% on every transaction compared to other platforms</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-shield-alt"></i></div>
                    <h3>Secure Payments</h3>
                    <p>Your transactions are encrypted and protected by industry standards</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-headset"></i></div>
                    <h3>24/7 Support</h3>
                    <p>Our customer support team is always ready to help you</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section id="services" class="services">
        <div class="container">
            <div class="section-header">
                <span class="section-tag"><i class="fas fa-cogs"></i> Our Services</span>
                <h2><?php echo $services_title; ?></h2>
                <p><?php echo $services_subtitle; ?></p>
            </div>
            
            <div class="services-grid">
                <?php 
                $displayed_services = [];
                foreach ($services as $category => $service_data):
                    foreach ($service_data['items'] as $item):
                        if (count($displayed_services) >= 6) break 2;
                        $icon = 'fa-circle';
                        if ($item['category'] == 'airtime') $icon = 'fa-phone-alt';
                        elseif ($item['category'] == 'data') $icon = 'fa-wifi';
                        elseif ($item['category'] == 'electricity') $icon = 'fa-bolt';
                        elseif ($item['category'] == 'cable') $icon = 'fa-tv';
                        elseif ($item['category'] == 'exam') $icon = 'fa-graduation-cap';
                        elseif ($item['category'] == 'giftcard') $icon = 'fa-gift';
                ?>
                <div class="service-card" onclick="location.href='user/<?php echo $item['code']; ?>.php'">
                    <div class="service-icon"><i class="fas <?php echo $icon; ?>"></i></div>
                    <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                    <p><?php echo htmlspecialchars($item['description'] ?? ucfirst($item['category']) . ' services'); ?></p>
                    <?php if ($item['min_amount'] > 0): ?>
                        <div class="service-price">From ₦<?php echo number_format($item['min_amount']); ?></div>
                    <?php else: ?>
                        <div class="service-price">Available</div>
                    <?php endif; ?>
                </div>
                <?php 
                    $displayed_services[] = $item;
                    endforeach;
                endforeach; 
                ?>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section id="how-it-works" class="how-it-works">
        <div class="container">
            <div class="section-header">
                <span class="section-tag"><i class="fas fa-magic"></i> Simple Process</span>
                <h2>How It Works</h2>
                <p>Get started in three simple steps</p>
            </div>
            
            <div class="steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <div class="step-icon"><i class="fas fa-user-plus"></i></div>
                    <h3>Create Account</h3>
                    <p>Sign up for free in less than a minute</p>
                </div>
                
                <div class="step">
                    <div class="step-number">2</div>
                    <div class="step-icon"><i class="fas fa-wallet"></i></div>
                    <h3>Fund Wallet</h3>
                    <p>Add money via bank transfer or card</p>
                </div>
                
                <div class="step">
                    <div class="step-number">3</div>
                    <div class="step-icon"><i class="fas fa-check-circle"></i></div>
                    <h3>Pay Bills</h3>
                    <p>Choose service and pay instantly</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta">
        <div class="container">
            <div class="cta-content">
                <h2><?php echo $cta_title; ?></h2>
                <p><?php echo $cta_subtitle; ?></p>
                <a href="auth/register.php" class="btn btn-primary btn-large"><i class="fas fa-rocket"></i> Create Free Account →</a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer" id="contact">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-about">
                    <div class="footer-logo"><i class="fas fa-bolt"></i> <?php echo $site_name; ?></div>
                    <p><?php echo $footer_text; ?></p>
                    <div class="social-links">
                        <a href="#" class="social-link"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                
                <div class="footer-links">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="#features"><i class="fas fa-chevron-right"></i> Features</a></li>
                        <li><a href="#services"><i class="fas fa-chevron-right"></i> Services</a></li>
                        <li><a href="#how-it-works"><i class="fas fa-chevron-right"></i> How It Works</a></li>
                        <li><a href="faq.php"><i class="fas fa-chevron-right"></i> FAQ</a></li>
                    </ul>
                </div>
                
                <div class="footer-links">
                    <h4>Support</h4>
                    <ul>
                        <li><a href="contact.php"><i class="fas fa-chevron-right"></i> Help Center</a></li>
                        <li><a href="contact.php"><i class="fas fa-chevron-right"></i> Contact Us</a></li>
                        <li><a href="privacy.php"><i class="fas fa-chevron-right"></i> Privacy Policy</a></li>
                        <li><a href="terms.php"><i class="fas fa-chevron-right"></i> Terms of Service</a></li>
                    </ul>
                </div>
                
                <div class="footer-contact">
                    <h4>Contact Us</h4>
                    <p><i class="fas fa-envelope"></i> <?php echo $site_email; ?></p>
                    <p><i class="fas fa-phone-alt"></i> <?php echo $site_phone; ?></p>
                    <p><i class="fas fa-map-marker-alt"></i> Lagos, Nigeria</p>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> <?php echo $site_name; ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="assets/js/main.js"></script>
    <script>
        // Mobile menu toggle
        const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
        const navLinks = document.querySelector('.nav-links');
        const navButtons = document.querySelector('.nav-buttons');
        
        if (mobileMenuBtn) {
            mobileMenuBtn.addEventListener('click', function() {
                if (navLinks) navLinks.classList.toggle('show');
                if (navButtons) navButtons.classList.toggle('show');
                const icon = this.querySelector('i');
                if (icon) {
                    if (navLinks && navLinks.classList.contains('show')) {
                        icon.classList.remove('fa-bars');
                        icon.classList.add('fa-times');
                    } else {
                        icon.classList.remove('fa-times');
                        icon.classList.add('fa-bars');
                    }
                }
            });
        }
        
        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if (href === '#') return;
                const target = document.querySelector(href);
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            });
        });
        
        // Animation on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);
        
        document.querySelectorAll('.feature-card, .service-card, .step').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            observer.observe(el);
        });
    </script>
</body>
</html>