<?php
// user/dashboard.php - OPay Inspired Dashboard
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

Auth::requireLogin();

$db = db();
$userId = Session::userId();

// Get user data
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get wallet balance
$walletBalance = getUserBalance($userId);

// Get recent transactions
$stmt = $db->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->bind_param("i", $userId);
$stmt->execute();
$transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get statistics
$stmt = $db->prepare("SELECT COUNT(*) as total, COALESCE(SUM(amount), 0) as spent FROM transactions WHERE user_id = ? AND status = 'success'");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

$pageTitle = 'Dashboard';

// Include the user header
include '../partials/user_header.php';
?>

<!-- Main Content -->
<div class="main-content">
    <div class="container">
        <!-- Welcome Card -->
        <div class="welcome-card">
            <h3>Welcome back, <?php echo htmlspecialchars($user['first_name'] ?: $user['username']); ?>! 👋</h3>
            <p>Here's what's happening with your account today</p>
            <div class="welcome-stats">
                <div class="welcome-stat">
                    <span class="welcome-stat-value"><?php echo format_money($walletBalance); ?></span>
                    <span class="welcome-stat-label">Balance</span>
                </div>
                <div class="welcome-stat">
                    <span class="welcome-stat-value"><?php echo $stats['total'] ?? 0; ?></span>
                    <span class="welcome-stat-label">Transactions</span>
                </div>
                <div class="welcome-stat">
                    <span class="welcome-stat-value"><?php echo format_money($stats['spent'] ?? 0); ?></span>
                    <span class="welcome-stat-label">Total Spent</span>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                <a href="wallet.php" class="btn btn-light btn-small">
                    <i class="fas fa-plus"></i> Fund Wallet
                </a>
            </div>
            
            <div class="quick-actions-grid">
                <a href="buy_airtime.php" class="quick-action-item">
                    <div class="quick-action-icon"><i class="fas fa-phone-alt"></i></div>
                    <span>Airtime</span>
                </a>
                <a href="buy_data.php" class="quick-action-item">
                    <div class="quick-action-icon"><i class="fas fa-wifi"></i></div>
                    <span>Data</span>
                </a>
                <a href="pay_electricity.php" class="quick-action-item">
                    <div class="quick-action-icon"><i class="fas fa-bolt"></i></div>
                    <span>Electricity</span>
                </a>
                <a href="pay_cable.php" class="quick-action-item">
                    <div class="quick-action-icon"><i class="fas fa-tv"></i></div>
                    <span>Cable TV</span>
                </a>
            </div>
        </div>

        <!-- Popular Services -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-star"></i> Popular Services</h3>
                <a href="services.php" class="btn btn-light btn-small">View All</a>
            </div>
            
            <div class="services-grid">
                <div class="service-card" onclick="location.href='buy_airtime.php?network=mtn'">
                    <div class="service-icon"><i class="fas fa-signal"></i></div>
                    <div class="service-info">
                        <h4>MTN Airtime</h4>
                        <p>Instant recharge</p>
                    </div>
                </div>
                
                <div class="service-card" onclick="location.href='buy_data.php?network=mtn'">
                    <div class="service-icon"><i class="fas fa-wifi"></i></div>
                    <div class="service-info">
                        <h4>MTN Data</h4>
                        <p>Best rates</p>
                    </div>
                </div>
                
                <div class="service-card" onclick="location.href='pay_electricity.php?disco=ikedc'">
                    <div class="service-icon"><i class="fas fa-bolt"></i></div>
                    <div class="service-info">
                        <h4>IKEDC</h4>
                        <p>Prepaid meter</p>
                    </div>
                </div>
                
                <div class="service-card" onclick="location.href='pay_cable.php?provider=dstv'">
                    <div class="service-icon"><i class="fas fa-tv"></i></div>
                    <div class="service-info">
                        <h4>DStv</h4>
                        <p>All packages</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> Recent Transactions</h3>
                <a href="transactions.php" class="btn btn-light btn-small">View All</a>
            </div>
            
            <?php if (empty($transactions)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>No transactions yet</p>
                    <a href="buy_airtime.php" class="btn btn-primary btn-small">
                        <i class="fas fa-shopping-cart"></i> Make your first purchase
                    </a>
                </div>
            <?php else: ?>
                <div class="transaction-list">
                    <?php foreach ($transactions as $t): ?>
                        <div class="transaction-item">
                            <div class="transaction-left">
                                <div class="transaction-icon">
                                    <?php
                                    $icon = 'fa-exchange-alt';
                                    if ($t['type'] == 'airtime') $icon = 'fa-phone-alt';
                                    elseif ($t['type'] == 'data') $icon = 'fa-wifi';
                                    elseif ($t['type'] == 'electricity') $icon = 'fa-bolt';
                                    elseif ($t['type'] == 'cable') $icon = 'fa-tv';
                                    elseif ($t['type'] == 'wallet_funding') $icon = 'fa-plus-circle';
                                    ?>
                                    <i class="fas <?php echo $icon; ?>"></i>
                                </div>
                                <div class="transaction-details">
                                    <h4><?php echo ucfirst($t['type']); ?></h4>
                                    <p>
                                        <?php 
                                        if ($t['phone_number']) echo $t['phone_number'];
                                        elseif ($t['meter_number']) echo $t['meter_number'];
                                        elseif ($t['smart_card']) echo $t['smart_card'];
                                        else echo 'Transaction';
                                        ?>
                                        • <?php echo time_ago($t['created_at']); ?>
                                    </p>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <span class="transaction-amount <?php echo $t['type'] == 'wallet_funding' ? 'positive' : 'negative'; ?>">
                                    <?php echo $t['type'] == 'wallet_funding' ? '+' : '-'; ?>
                                    <?php echo format_money($t['amount']); ?>
                                </span>
                                <br>
                                <span class="badge badge-<?php echo $t['status']; ?>">
                                    <?php echo ucfirst($t['status']); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Bottom Navigation (Mobile) -->
<nav class="bottom-nav">
    <a href="dashboard.php" class="bottom-nav-item active">
        <i class="fas fa-home"></i>
        <span>Home</span>
    </a>
    <a href="wallet.php" class="bottom-nav-item">
        <i class="fas fa-wallet"></i>
        <span>Wallet</span>
    </a>
    <a href="services.php" class="bottom-nav-item">
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

document.querySelectorAll('.card').forEach(card => {
    card.style.opacity = '0';
    card.style.transform = 'translateY(20px)';
    card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
    observer.observe(card);
});

// Auto-hide alerts
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        alert.style.transition = 'opacity 0.5s';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 500);
    });
}, 5000);
</script>

</body>
</html>