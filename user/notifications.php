<?php
// user/notifications.php - Notifications Page
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

Auth::requireLogin();

$db = db();
$userId = Session::userId();

// Mark all as read if requested
if (isset($_GET['mark_all_read'])) {
    $stmt = $db->prepare("UPDATE notifications SET is_read = TRUE, read_at = NOW() WHERE user_id = ? AND is_read = FALSE");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    redirect('notifications.php');
}

// Mark single as read
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $id = $_GET['mark_read'];
    $stmt = $db->prepare("UPDATE notifications SET is_read = TRUE, read_at = NOW() WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $userId);
    $stmt->execute();
    redirect('notifications.php');
}

// Get notifications
$stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Count unread
$stmt = $db->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = FALSE");
$stmt->bind_param("i", $userId);
$stmt->execute();
$unreadCount = $stmt->get_result()->fetch_assoc()['unread'];

$pageTitle = 'Notifications';
include '../partials/user_header.php';
?>

<!-- Main Content -->
<div class="main-content">
    <div class="container">
        <!-- Page Header -->
        <div class="content-header">
            <h2><i class="fas fa-bell"></i> Notifications</h2>
            <?php if ($unreadCount > 0): ?>
                <a href="?mark_all_read=1" class="btn btn-light btn-small">
                    <i class="fas fa-check-double"></i> Mark All as Read
                </a>
            <?php endif; ?>
        </div>

        <!-- Notifications List -->
        <div class="card">
            <?php if (empty($notifications)): ?>
                <div class="empty-state">
                    <i class="fas fa-bell-slash"></i>
                    <p>No notifications yet</p>
                </div>
            <?php else: ?>
                <div class="transaction-list">
                    <?php foreach ($notifications as $n): ?>
                        <div class="transaction-item" style="<?php echo !$n['is_read'] ? 'background: rgba(99, 102, 241, 0.05);' : ''; ?>">
                            <div class="transaction-left">
                                <div class="transaction-icon" style="<?php echo !$n['is_read'] ? 'background: var(--primary); color: white;' : ''; ?>">
                                    <i class="fas <?php 
                                        echo $n['type'] == 'transaction' ? 'fa-exchange-alt' : 
                                            ($n['type'] == 'promo' ? 'fa-tag' : 'fa-info-circle'); 
                                    ?>"></i>
                                </div>
                                <div class="transaction-details">
                                    <h4><?php echo htmlspecialchars($n['title']); ?></h4>
                                    <p><?php echo htmlspecialchars($n['message']); ?></p>
                                    <small style="color: var(--gray);">
                                        <?php echo time_ago($n['created_at']); ?>
                                    </small>
                                </div>
                            </div>
                            <?php if (!$n['is_read']): ?>
                                <a href="?mark_read=<?php echo $n['id']; ?>" class="btn btn-small btn-light">
                                    <i class="fas fa-check"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
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

</body>
</html>