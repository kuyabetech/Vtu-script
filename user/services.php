<?php
// user/services.php - All Services Page (Database Driven with Categories Table)
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

Auth::requireLogin();

$db = db();
$userId = Session::userId();

// Get all active categories from categories table
$categories = [];
$query = "SELECT * FROM categories WHERE is_active = 1 ORDER BY display_order, name";
$result = $db->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $categories[$row['code']] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'code' => $row['code'],
            'icon' => $row['icon'],
            'color' => $row['color'],
            'description' => $row['description'],
            'items' => []
        ];
    }
}

// Get all active services from database with category info
$services = [];
$query = "SELECT s.*, 
          c.name as category_name, 
          c.icon as category_icon, 
          c.color as category_color,
          c.code as category_code
          FROM services s 
          LEFT JOIN categories c ON s.category_id = c.id 
          WHERE s.is_active = 1 
          ORDER BY c.display_order, s.name";
$result = $db->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $category_code = $row['category_code'] ?? 'uncategorized';
        
        // Initialize category if not exists
        if (!isset($services[$category_code])) {
            $services[$category_code] = [
                'id' => $row['category_id'],
                'title' => $row['category_name'] ?? ucfirst($category_code),
                'icon' => $row['category_icon'] ?? getCategoryIcon($category_code),
                'color' => $row['category_color'] ?? getCategoryColor($category_code),
                'items' => []
            ];
        }
        
        $services[$category_code]['items'][] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'code' => $row['code'],
            'description' => $row['description'] ?? getDefaultDescription($row['name']),
            'icon' => getServiceIcon($row['name'], $row['code']),
            'link' => getServiceLink($row['category_code'] ?? $row['category'], $row['code']),
            'min_amount' => $row['min_amount'],
            'max_amount' => $row['max_amount'],
            'commission_rate' => $row['commission_rate']
        ];
    }
}

// If no services in database, use defaults
if (empty($services)) {
    $services = [
        'airtime' => [
            'title' => 'Airtime',
            'icon' => 'fa-phone-alt',
            'color' => '#10b981',
            'items' => [
                ['name' => 'MTN Airtime', 'icon' => 'fa-signal', 'link' => 'buy_airtime.php?network=mtn', 'description' => 'Instant recharge'],
                ['name' => 'Glo Airtime', 'icon' => 'fa-globe', 'link' => 'buy_airtime.php?network=glo', 'description' => 'Best rates'],
                ['name' => 'Airtel Airtime', 'icon' => 'fa-wifi', 'link' => 'buy_airtime.php?network=airtel', 'description' => 'Fast delivery'],
                ['name' => '9mobile Airtime', 'icon' => 'fa-mobile-alt', 'link' => 'buy_airtime.php?network=9mobile', 'description' => 'Always reliable']
            ]
        ],
        'data' => [
            'title' => 'Data Bundles',
            'icon' => 'fa-wifi',
            'color' => '#3b82f6',
            'items' => [
                ['name' => 'MTN Data', 'icon' => 'fa-signal', 'link' => 'buy_data.php?network=mtn', 'description' => 'All plans'],
                ['name' => 'Glo Data', 'icon' => 'fa-globe', 'link' => 'buy_data.php?network=glo', 'description' => 'Best value'],
                ['name' => 'Airtel Data', 'icon' => 'fa-wifi', 'link' => 'buy_data.php?network=airtel', 'description' => 'Fast 4G'],
                ['name' => '9mobile Data', 'icon' => 'fa-mobile-alt', 'link' => 'buy_data.php?network=9mobile', 'description' => 'Affordable']
            ]
        ],
        'electricity' => [
            'title' => 'Electricity',
            'icon' => 'fa-bolt',
            'color' => '#f59e0b',
            'items' => [
                ['name' => 'IKEDC', 'icon' => 'fa-bolt', 'link' => 'pay_electricity.php?disco=ikedc', 'description' => 'Ikeja Electric'],
                ['name' => 'EKEDC', 'icon' => 'fa-bolt', 'link' => 'pay_electricity.php?disco=ekedc', 'description' => 'Eko Electric'],
                ['name' => 'AEDC', 'icon' => 'fa-bolt', 'link' => 'pay_electricity.php?disco=aedc', 'description' => 'Abuja Electric'],
                ['name' => 'PHED', 'icon' => 'fa-bolt', 'link' => 'pay_electricity.php?disco=phed', 'description' => 'Port Harcourt Electric']
            ]
        ],
        'cable' => [
            'title' => 'Cable TV',
            'icon' => 'fa-tv',
            'color' => '#ef4444',
            'items' => [
                ['name' => 'DStv', 'icon' => 'fa-satellite-dish', 'link' => 'pay_cable.php?provider=dstv', 'description' => 'All packages'],
                ['name' => 'GOtv', 'icon' => 'fa-tv', 'link' => 'pay_cable.php?provider=gotv', 'description' => 'Affordable'],
                ['name' => 'StarTimes', 'icon' => 'fa-star', 'link' => 'pay_cable.php?provider=startimes', 'description' => 'Great value'],
                ['name' => 'Showmax', 'icon' => 'fa-play', 'link' => 'pay_cable.php?provider=showmax', 'description' => 'Streaming']
            ]
        ],
        'exam' => [
            'title' => 'Exam Pins',
            'icon' => 'fa-graduation-cap',
            'color' => '#8b5cf6',
            'items' => [
                ['name' => 'WAEC PIN', 'icon' => 'fa-book', 'link' => 'buy_exam.php?type=waec', 'description' => 'West African Exams'],
                ['name' => 'NECO PIN', 'icon' => 'fa-book-open', 'link' => 'buy_exam.php?type=neco', 'description' => 'National Exams'],
                ['name' => 'JAMB PIN', 'icon' => 'fa-pencil-alt', 'link' => 'buy_exam.php?type=jamb', 'description' => 'UTME Registration'],
                ['name' => 'NABTEB', 'icon' => 'fa-calculator', 'link' => 'buy_exam.php?type=nabteb', 'description' => 'Technical Exams']
            ]
        ],
        'giftcard' => [
            'title' => 'Gift Cards',
            'icon' => 'fa-gift',
            'color' => '#ec4899',
            'items' => [
                ['name' => 'Amazon', 'icon' => 'fa-amazon', 'link' => 'giftcards.php?type=amazon', 'description' => 'Shop online'],
                ['name' => 'iTunes', 'icon' => 'fa-apple', 'link' => 'giftcards.php?type=itunes', 'description' => 'Apps & Music'],
                ['name' => 'Google Play', 'icon' => 'fa-google', 'link' => 'giftcards.php?type=google', 'description' => 'Android apps'],
                ['name' => 'Steam', 'icon' => 'fa-steam', 'link' => 'giftcards.php?type=steam', 'description' => 'Games']
            ]
        ]
    ];
}

// Helper functions for icons and colors
function getCategoryIcon($category) {
    $icons = [
        'airtime' => 'fa-phone-alt',
        'data' => 'fa-wifi',
        'electricity' => 'fa-bolt',
        'cable' => 'fa-tv',
        'exam' => 'fa-graduation-cap',
        'giftcard' => 'fa-gift',
        'insurance' => 'fa-shield-alt',
        'betting' => 'fa-gamepad',
        'education' => 'fa-book'
    ];
    return $icons[$category] ?? 'fa-cog';
}

function getCategoryColor($category) {
    $colors = [
        'airtime' => '#10b981',
        'data' => '#3b82f6',
        'electricity' => '#f59e0b',
        'cable' => '#ef4444',
        'exam' => '#8b5cf6',
        'giftcard' => '#ec4899',
        'insurance' => '#6366f1',
        'betting' => '#f97316',
        'education' => '#14b8a6'
    ];
    return $colors[$category] ?? '#6366f1';
}

function getServiceIcon($name, $code) {
    $name_lower = strtolower($name);
    $code_lower = strtolower($code);
    
    if (strpos($name_lower, 'mtn') !== false) return 'fa-signal';
    if (strpos($name_lower, 'glo') !== false) return 'fa-globe';
    if (strpos($name_lower, 'airtel') !== false) return 'fa-wifi';
    if (strpos($name_lower, '9mobile') !== false) return 'fa-mobile-alt';
    if (strpos($name_lower, 'dstv') !== false) return 'fa-satellite-dish';
    if (strpos($name_lower, 'gotv') !== false) return 'fa-tv';
    if (strpos($name_lower, 'startimes') !== false) return 'fa-star';
    if (strpos($name_lower, 'ikedc') !== false || strpos($code_lower, 'ikedc') !== false) return 'fa-bolt';
    if (strpos($name_lower, 'ekedc') !== false || strpos($code_lower, 'ekedc') !== false) return 'fa-bolt';
    if (strpos($name_lower, 'aedc') !== false || strpos($code_lower, 'aedc') !== false) return 'fa-bolt';
    if (strpos($name_lower, 'phed') !== false || strpos($code_lower, 'phed') !== false) return 'fa-bolt';
    if (strpos($name_lower, 'waec') !== false) return 'fa-book';
    if (strpos($name_lower, 'neco') !== false) return 'fa-book-open';
    if (strpos($name_lower, 'jamb') !== false) return 'fa-pencil-alt';
    if (strpos($name_lower, 'nabteb') !== false) return 'fa-calculator';
    if (strpos($name_lower, 'amazon') !== false) return 'fa-amazon';
    if (strpos($name_lower, 'itunes') !== false || strpos($name_lower, 'apple') !== false) return 'fa-apple';
    if (strpos($name_lower, 'google') !== false) return 'fa-google';
    if (strpos($name_lower, 'steam') !== false) return 'fa-steam';
    
    return 'fa-cog';
}

function getServiceLink($category, $code) {
    switch($category) {
        case 'airtime':
            return "buy_airtime.php?network=" . str_replace('_airtime', '', $code);
        case 'data':
            return "buy_data.php?network=" . str_replace('_data', '', $code);
        case 'electricity':
            return "pay_electricity.php?disco=" . $code;
        case 'cable':
            return "pay_cable.php?provider=" . $code;
        case 'exam':
            return "buy_exam.php?type=" . $code;
        case 'giftcard':
            return "giftcards.php?type=" . $code;
        default:
            return "#";
    }
}

function getDefaultDescription($name) {
    $name_lower = strtolower($name);
    
    if (strpos($name_lower, 'airtime') !== false) return 'Instant recharge';
    if (strpos($name_lower, 'data') !== false) return 'Best rates';
    if (strpos($name_lower, 'electric') !== false) return 'Prepaid & Postpaid';
    if (strpos($name_lower, 'cable') !== false) return 'All packages';
    if (strpos($name_lower, 'exam') !== false) return 'Purchase pins';
    if (strpos($name_lower, 'gift') !== false) return 'Shop online';
    
    return 'Available now';
}

$pageTitle = 'All Services';
include '../partials/user_header.php';
?>

<style>
/* ===== Responsive Services Page Styles ===== */

/* Content Header */
.content-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.content-header h2 {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: var(--dark);
    font-size: clamp(1.25rem, 4vw, 1.75rem);
}

.content-header h2 i {
    color: var(--primary);
    background: rgba(99, 102, 241, 0.1);
    padding: 0.75rem;
    border-radius: 50%;
    font-size: clamp(1rem, 3vw, 1.25rem);
}

.category-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1.25rem;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    border-radius: 2rem;
    font-size: 0.875rem;
    font-weight: 500;
    box-shadow: 0 4px 10px rgba(99, 102, 241, 0.2);
}

/* Category Cards */
.card {
    background: var(--card-bg);
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow);
    padding: clamp(1rem, 3vw, 1.5rem);
    margin-bottom: 1.5rem;
    transition: all 0.3s ease;
    border: 1px solid var(--gray-light);
}

.card:hover {
    box-shadow: var(--shadow-lg);
}

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--gray-light);
    flex-wrap: wrap;
    gap: 1rem;
}

.card-header h3 {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: clamp(1.1rem, 3vw, 1.35rem);
    color: var(--dark);
    margin: 0;
}

.card-header h3 i {
    font-size: clamp(1.2rem, 3vw, 1.5rem);
}

.category-item-count {
    background: var(--light);
    padding: 0.35rem 1rem;
    border-radius: 2rem;
    font-size: 0.8rem;
    color: var(--gray);
    font-weight: 500;
}

/* Services Grid - Responsive */
.services-grid {
    display: grid;
    gap: 1rem;
}

/* Desktop - 4 columns */
@media (min-width: 1024px) {
    .services-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}

/* Tablet Landscape - 3 columns */
@media (min-width: 768px) and (max-width: 1023px) {
    .services-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

/* Tablet Portrait - 2 columns */
@media (min-width: 480px) and (max-width: 767px) {
    .services-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

/* Mobile - 1 column */
@media (max-width: 479px) {
    .services-grid {
        grid-template-columns: 1fr;
    }
}

/* Service Card */
.service-card {
    background: white;
    border-radius: var(--radius-lg);
    padding: clamp(1rem, 3vw, 1.25rem);
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: var(--shadow-sm);
    transition: all 0.3s ease;
    cursor: pointer;
    border: 2px solid transparent;
    height: 100%;
    position: relative;
    overflow: hidden;
}

.service-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s ease;
}

.service-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
    border-color: var(--primary);
}

.service-card:hover::before {
    left: 100%;
}

.service-icon {
    width: clamp(45px, 8vw, 55px);
    height: clamp(45px, 8vw, 55px);
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: clamp(1.2rem, 3vw, 1.4rem);
    box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
    flex-shrink: 0;
    transition: all 0.3s ease;
}

.service-card:hover .service-icon {
    transform: scale(1.1) rotate(5deg);
}

.service-info {
    flex: 1;
    min-width: 0;
}

.service-info h4 {
    margin-bottom: 0.25rem;
    font-size: clamp(0.95rem, 2.5vw, 1rem);
    color: var(--dark);
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.service-info p {
    font-size: clamp(0.7rem, 2vw, 0.8rem);
    color: var(--gray);
    margin: 0;
    line-height: 1.4;
}

.service-badge {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    background: rgba(99, 102, 241, 0.1);
    color: var(--primary);
    padding: 0.2rem 0.5rem;
    border-radius: 1rem;
    font-size: 0.6rem;
    font-weight: 600;
}

/* Coming Soon Grid */
.coming-soon-grid {
    display: grid;
    gap: 1rem;
    margin-top: 0.5rem;
}

/* Desktop - 4 columns */
@media (min-width: 1024px) {
    .coming-soon-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}

/* Tablet Landscape - 3 columns */
@media (min-width: 768px) and (max-width: 1023px) {
    .coming-soon-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

/* Mobile Landscape - 2 columns */
@media (min-width: 480px) and (max-width: 767px) {
    .coming-soon-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

/* Mobile Portrait - 1 column */
@media (max-width: 479px) {
    .coming-soon-grid {
        grid-template-columns: 1fr;
    }
}

.coming-soon-item {
    text-align: center;
    padding: clamp(1rem, 3vw, 1.5rem);
    background: var(--light);
    border-radius: var(--radius-lg);
    opacity: 0.7;
    transition: all 0.3s ease;
    border: 2px dashed var(--gray-light);
    cursor: default;
}

.coming-soon-item:hover {
    opacity: 0.9;
    transform: translateY(-3px);
    border-color: var(--primary);
}

.coming-soon-item i {
    font-size: clamp(1.5rem, 5vw, 2rem);
    color: var(--primary);
    margin-bottom: 0.75rem;
}

.coming-soon-item h4 {
    font-size: clamp(0.95rem, 2.5vw, 1rem);
    margin-bottom: 0.25rem;
    color: var(--dark);
}

.coming-soon-item p {
    font-size: clamp(0.7rem, 2vw, 0.75rem);
    color: var(--gray);
    margin: 0;
}

/* Summary Card */
.summary-card {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    border-radius: var(--radius-xl);
    padding: 2rem;
    text-align: center;
    margin-top: 1.5rem;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}

.summary-item {
    text-align: center;
}

.summary-item i {
    font-size: 2rem;
    margin-bottom: 0.5rem;
    opacity: 0.9;
}

.summary-item h4 {
    color: white;
    font-size: 1.5rem;
    margin-bottom: 0.25rem;
}

.summary-item p {
    color: rgba(255,255,255,0.9);
    font-size: 0.875rem;
    margin: 0;
}

/* Loading Skeleton */
.skeleton {
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: shimmer 1.5s infinite;
}

@keyframes shimmer {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

/* Animations */
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

.card {
    animation: fadeInUp 0.5s ease forwards;
}

.card:nth-child(1) { animation-delay: 0.1s; }
.card:nth-child(2) { animation-delay: 0.2s; }
.card:nth-child(3) { animation-delay: 0.3s; }
.card:nth-child(4) { animation-delay: 0.4s; }
.card:nth-child(5) { animation-delay: 0.5s; }
.card:nth-child(6) { animation-delay: 0.6s; }

/* Touch-friendly targets */
@media (max-width: 768px) {
    .service-card {
        min-height: 70px;
    }
    
    .service-icon {
        width: 50px;
        height: 50px;
    }
}

/* Bottom Navigation */
.bottom-nav-item.active {
    color: var(--primary) !important;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));
}

.main-content {
    padding-bottom: calc(var(--bottom-nav-height) + 1rem);
}

/* Print styles */
@media print {
    .bottom-nav,
    .service-card::before {
        display: none;
    }
    
    .service-card {
        break-inside: avoid;
        page-break-inside: avoid;
        border: 1px solid #ddd;
    }
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    .service-card {
        background: #2d2d2d;
    }
    
    .service-info h4 {
        color: #fff;
    }
    
    .coming-soon-item {
        background: #363636;
    }
}

/* Reduced motion preference */
@media (prefers-reduced-motion: reduce) {
    .service-card,
    .coming-soon-item,
    .service-card::before,
    .card {
        animation: none;
        transition: none;
    }
}
</style>
<!-- Main Content -->
<div class="main-content">
    <div class="container">
        <!-- Page Header -->
        <div class="content-header">
            <h2>
                <i class="fas fa-th-large"></i>
                <span>All Services</span>
            </h2>
            <div class="category-badge">
                <i class="fas fa-layer-group"></i>
                <span><?php echo count($services); ?> Categories</span>
            </div>
        </div>

        <!-- Services Categories -->
        <?php if (empty($services)): ?>
            <div class="card" style="text-align: center; padding: 3rem;">
                <i class="fas fa-exclamation-circle" style="font-size: 3rem; color: var(--gray); margin-bottom: 1rem;"></i>
                <h3>No Services Available</h3>
                <p style="color: var(--gray);">Please check back later.</p>
            </div>
        <?php else: ?>
            <?php foreach ($services as $category_code => $service): ?>
                <?php if (!empty($service['items'])): ?>
                    <div class="card" data-category="<?php echo $category_code; ?>">
                        <div class="card-header">
                            <h3>
                                <i class="fas <?php echo $service['icon']; ?>" style="color: <?php echo $service['color']; ?>;"></i>
                                <?php echo $service['title']; ?>
                            </h3>
                            <span class="category-item-count">
                                <i class="fas fa-box"></i> <?php echo count($service['items']); ?> items
                            </span>
                        </div>
                        
                        <div class="services-grid">
                            <?php foreach ($service['items'] as $item): ?>
                                <div class="service-card" onclick="location.href='<?php echo $item['link']; ?>'">
                                    <?php if (!empty($item['commission_rate']) && $item['commission_rate'] > 0): ?>
                                        <span class="service-badge"><?php echo $item['commission_rate']; ?>%</span>
                                    <?php endif; ?>
                                    <div class="service-icon" style="background: linear-gradient(135deg, <?php echo $service['color']; ?>, <?php echo $service['color']; ?>dd);">
                                        <i class="fas <?php echo $item['icon']; ?>"></i>
                                    </div>
                                    <div class="service-info">
                                        <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                                        <p><?php echo htmlspecialchars($item['description']); ?></p>
                                        <?php if (!empty($item['min_amount']) && !empty($item['max_amount'])): ?>
                                            <small style="color: var(--gray); font-size: 0.65rem;">
                                                ₦<?php echo number_format($item['min_amount']); ?> - ₦<?php echo number_format($item['max_amount']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Coming Soon Section -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-clock" style="color: var(--gray);"></i> Coming Soon</h3>
                <span class="category-item-count">New Services</span>
            </div>
            
            <div class="coming-soon-grid">
                <div class="coming-soon-item">
                    <i class="fas fa-tint"></i>
                    <h4>Water Bills</h4>
                    <p>Lagos Water Corporation</p>
                </div>
                
                <div class="coming-soon-item">
                    <i class="fas fa-car"></i>
                    <h4>Vehicle Insurance</h4>
                    <p>Coming soon</p>
                </div>
                
                <div class="coming-soon-item">
                    <i class="fas fa-plane"></i>
                    <h4>Flight Tickets</h4>
                    <p>Coming soon</p>
                </div>
                
                <div class="coming-soon-item">
                    <i class="fas fa-gamepad"></i>
                    <h4>Betting Funding</h4>
                    <p>Coming soon</p>
                </div>
                
                <div class="coming-soon-item">
                    <i class="fas fa-train"></i>
                    <h4>Transportation</h4>
                    <p>Coming soon</p>
                </div>
                
                <div class="coming-soon-item">
                    <i class="fas fa-school"></i>
                    <h4>School Fees</h4>
                    <p>Coming soon</p>
                </div>
                
                <div class="coming-soon-item">
                    <i class="fas fa-heartbeat"></i>
                    <h4>Health Insurance</h4>
                    <p>Coming soon</p>
                </div>
                
                <div class="coming-soon-item">
                    <i class="fas fa-wifi"></i>
                    <h4>Internet Bills</h4>
                    <p>Coming soon</p>
                </div>
            </div>
        </div>
        
        <!-- Service Summary -->
        <?php 
        $totalServices = 0;
        $totalCategories = count($services);
        foreach ($services as $service) {
            $totalServices += count($service['items']);
        }
        ?>
        <div class="summary-card">
            <div class="summary-grid">
                <div class="summary-item">
                    <i class="fas fa-layer-group"></i>
                    <h4><?php echo $totalCategories; ?></h4>
                    <p>Categories</p>
                </div>
                <div class="summary-item">
                    <i class="fas fa-boxes"></i>
                    <h4><?php echo $totalServices; ?></h4>
                    <p>Services</p>
                </div>
                <div class="summary-item">
                    <i class="fas fa-clock"></i>
                    <h4>8</h4>
                    <p>Coming Soon</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bottom Navigation -->
<nav class="bottom-nav">
    <a href="dashboard.php" class="bottom-nav-item">
        <i class="fas fa-home"></i>
        <span>Home</span>
    </a>
    <a href="wallet.php" class="bottom-nav-item">
        <i class="fas fa-wallet"></i>
        <span>Wallet</span>
    </a>
    <a href="services.php" class="bottom-nav-item active">
        <i class="fas fa-th-large"></i>
        <span>Services</span>
    </a>
    <a href="transactions.php" class="bottom-nav-item">
        <i class="fas fa-history"></i>
        <span>History</span>
    </a>
    <a href="profile.php" class="bottom-nav-item">
        <i class="fas fa-user"></i>
        <span>Profile</span>
    </a>
</nav>

<script>
// Keep all your existing JavaScript from your original file
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

document.querySelectorAll('.service-card').forEach(el => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(20px)';
    el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
    observer.observe(el);
});

// Touch-friendly hover effects for mobile
if ('ontouchstart' in window) {
    document.querySelectorAll('.service-card, .coming-soon-item').forEach(el => {
        el.addEventListener('touchstart', function() {
            this.style.transform = 'scale(0.98)';
        });
        el.addEventListener('touchend', function() {
            this.style.transform = '';
        });
    });
}

// Lazy loading for service cards
document.addEventListener('DOMContentLoaded', function() {
    const serviceCards = document.querySelectorAll('.service-card');
    
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const card = entry.target;
                    observer.unobserve(card);
                }
            });
        });
        
        serviceCards.forEach(card => imageObserver.observe(card));
    }
});
</script>

</body>
</html>