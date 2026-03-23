<?php
// user/referrals.php - Referral Program Page
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

// Get referrals
$stmt = $db->prepare("SELECT r.*, u.username, u.first_name, u.last_name, u.created_at as joined_date 
                      FROM referrals r 
                      JOIN users u ON r.referred_id = u.id 
                      WHERE r.referrer_id = ? 
                      ORDER BY r.created_at DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$referrals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get referral earnings
$stmt = $db->prepare("SELECT COALESCE(SUM(commission_amount), 0) as total, 
                      COUNT(*) as count 
                      FROM referrals 
                      WHERE referrer_id = ? AND status = 'paid'");
$stmt->bind_param("i", $userId);
$stmt->execute();
$earnings = $stmt->get_result()->fetch_assoc();

$referralLink = SITE_URL . "/auth/register.php?ref=" . $user['referral_code'];

$pageTitle = 'Referrals';
include '../partials/user_header.php';
?>

<!-- Main Content -->
<div class="main-content">
    <div class="container">
        <!-- Page Header -->
        <div class="content-header">
            <h2><i class="fas fa-gift"></i> Referral Program</h2>
        </div>

        <!-- Stats Cards -->
        <div class="quick-actions-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 1.5rem;">
            <div class="quick-action-item" style="cursor: default;">
                <div class="quick-action-icon"><i class="fas fa-users"></i></div>
                <span>Total Referrals</span>
                <strong style="font-size: 1.5rem;"><?php echo count($referrals); ?></strong>
            </div>
            
            <div class="quick-action-item" style="cursor: default;">
                <div class="quick-action-icon"><i class="fas fa-money-bill-wave"></i></div>
                <span>Total Earnings</span>
                <strong style="font-size: 1.5rem;"><?php echo format_money($earnings['total']); ?></strong>
            </div>
            
            <div class="quick-action-item" style="cursor: default;">
                <div class="quick-action-icon"><i class="fas fa-percent"></i></div>
                <span>Commission Rate</span>
                <strong style="font-size: 1.5rem;"><?php echo REFERRAL_PERCENTAGE; ?>%</strong>
            </div>
        </div>

        <!-- Referral Link Card -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-share-alt"></i> Your Referral Link</h3>
            </div>
            
            <p style="margin-bottom: 1rem;">Share this link with friends and earn <?php echo REFERRAL_PERCENTAGE; ?>% commission on their transactions!</p>
            
            <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 200px;">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-link"></i></span>
                        <input type="text" class="form-control" id="referralLink" value="<?php echo $referralLink; ?>" readonly>
                    </div>
                </div>
                <button class="btn btn-primary" onclick="copyReferralLink()">
                    <i class="fas fa-copy"></i> Copy Link
                </button>
            </div>
            
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <button class="btn btn-light" onclick="shareViaWhatsApp()">
                    <i class="fab fa-whatsapp" style="color: #25D366;"></i> Share on WhatsApp
                </button>
                <button class="btn btn-light" onclick="shareViaTelegram()">
                    <i class="fab fa-telegram" style="color: #0088cc;"></i> Share on Telegram
                </button>
                <button class="btn btn-light" onclick="shareViaTwitter()">
                    <i class="fab fa-twitter" style="color: #1DA1F2;"></i> Share on Twitter
                </button>
                <button class="btn btn-light" onclick="shareViaFacebook()">
                    <i class="fab fa-facebook" style="color: #4267B2;"></i> Share on Facebook
                </button>
            </div>
        </div>

        <!-- How It Works -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-info-circle"></i> How It Works</h3>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem;">
                <div style="text-align: center;">
                    <div style="width: 60px; height: 60px; background: linear-gradient(135deg, var(--primary), var(--secondary)); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem; margin: 0 auto 1rem;">
                        1
                    </div>
                    <h4>Share Your Link</h4>
                    <p style="font-size: 0.875rem; color: var(--gray);">Share your unique referral link with friends</p>
                </div>
                
                <div style="text-align: center;">
                    <div style="width: 60px; height: 60px; background: linear-gradient(135deg, var(--primary), var(--secondary)); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem; margin: 0 auto 1rem;">
                        2
                    </div>
                    <h4>Friend Signs Up</h4>
                    <p style="font-size: 0.875rem; color: var(--gray);">They register using your referral link</p>
                </div>
                
                <div style="text-align: center;">
                    <div style="width: 60px; height: 60px; background: linear-gradient(135deg, var(--primary), var(--secondary)); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem; margin: 0 auto 1rem;">
                        3
                    </div>
                    <h4>Earn Commission</h4>
                    <p style="font-size: 0.875rem; color: var(--gray);">You earn <?php echo REFERRAL_PERCENTAGE; ?>% on all their transactions</p>
                </div>
            </div>
        </div>

        <!-- Referrals List -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-list"></i> Your Referrals (<?php echo count($referrals); ?>)</h3>
            </div>
            
            <?php if (empty($referrals)): ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <p>You haven't referred anyone yet</p>
                    <button class="btn btn-primary btn-small" onclick="copyReferralLink()">
                        <i class="fas fa-share"></i> Share Your Link
                    </button>
                </div>
            <?php else: ?>
                <div class="transaction-list">
                    <?php foreach ($referrals as $r): ?>
                        <div class="transaction-item">
                            <div class="transaction-left">
                                <div class="transaction-icon">
                                    <i class="fas fa-user-friends"></i>
                                </div>
                                <div class="transaction-details">
                                    <h4><?php echo htmlspecialchars($r['first_name'] . ' ' . $r['last_name']); ?></h4>
                                    <p>
                                        Username: <?php echo $r['username']; ?> • 
                                        Joined: <?php echo date('M d, Y', strtotime($r['joined_date'])); ?>
                                    </p>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <span class="transaction-amount positive">
                                    +<?php echo format_money($r['commission_amount']); ?>
                                </span>
                                <br>
                                <span class="badge badge-<?php echo $r['status']; ?>">
                                    <?php echo ucfirst($r['status']); ?>
                                </span>
                            </div>
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

<script>
function copyReferralLink() {
    const link = document.getElementById('referralLink');
    link.select();
    document.execCommand('copy');
    alert('Referral link copied to clipboard!');
}

function shareViaWhatsApp() {
    const link = document.getElementById('referralLink').value;
    const text = encodeURIComponent(`Join me on <?php echo SITE_NAME; ?> and enjoy the best VTU services! Use my referral link: ${link}`);
    window.open(`https://wa.me/?text=${text}`, '_blank');
}

function shareViaTelegram() {
    const link = document.getElementById('referralLink').value;
    const text = encodeURIComponent(`Join me on <?php echo SITE_NAME; ?> and enjoy the best VTU services! Use my referral link: ${link}`);
    window.open(`https://t.me/share/url?url=${link}&text=${text}`, '_blank');
}

function shareViaTwitter() {
    const link = document.getElementById('referralLink').value;
    const text = encodeURIComponent(`Join me on <?php echo SITE_NAME; ?> for instant airtime, data and bill payments!`);
    window.open(`https://twitter.com/intent/tweet?text=${text}&url=${link}`, '_blank');
}

function shareViaFacebook() {
    const link = document.getElementById('referralLink').value;
    window.open(`https://www.facebook.com/sharer/sharer.php?u=${link}`, '_blank');
}
</script>

</body>
</html>