<?php
// user/pay_cable.php - Pay Cable TV Bills Page
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

Auth::requireLogin();

$db = db();
$userId = Session::userId();
$walletBalance = getUserBalance($userId);

// Cable TV Providers
$providers = [
    'dstv' => [
        'name' => 'DStv',
        'icon' => 'fa-satellite-dish',
        'color' => '#ef4444',
        'packages' => [
            ['name' => 'DStv Padi', 'price' => 2500, 'channels' => '30+ channels'],
            ['name' => 'DStv Yanga', 'price' => 4200, 'channels' => '50+ channels'],
            ['name' => 'DStv Confam', 'price' => 6200, 'channels' => '80+ channels'],
            ['name' => 'DStv Asia', 'price' => 7500, 'channels' => 'Asian content'],
            ['name' => 'DStv Premium', 'price' => 18500, 'channels' => '150+ channels'],
            ['name' => 'DStv Compact', 'price' => 10500, 'channels' => '100+ channels']
        ]
    ],
    'gotv' => [
        'name' => 'GOtv',
        'icon' => 'fa-tv',
        'color' => '#f59e0b',
        'packages' => [
            ['name' => 'GOtv Smallie', 'price' => 1500, 'channels' => '20+ channels'],
            ['name' => 'GOtv Jinja', 'price' => 2500, 'channels' => '35+ channels'],
            ['name' => 'GOtv Max', 'price' => 3700, 'channels' => '50+ channels'],
            ['name' => 'GOtv Supa', 'price' => 5700, 'channels' => '70+ channels']
        ]
    ],
    'startimes' => [
        'name' => 'StarTimes',
        'icon' => 'fa-star',
        'color' => '#3b82f6',
        'packages' => [
            ['name' => 'StarTimes Nova', 'price' => 1500, 'channels' => '25+ channels'],
            ['name' => 'StarTimes Basic', 'price' => 2500, 'channels' => '40+ channels'],
            ['name' => 'StarTimes Classic', 'price' => 3700, 'channels' => '60+ channels'],
            ['name' => 'StarTimes Super', 'price' => 5700, 'channels' => '80+ channels']
        ]
    ],
    'showmax' => [
        'name' => 'Showmax',
        'icon' => 'fa-play',
        'color' => '#10b981',
        'packages' => [
            ['name' => 'Showmax Mobile', 'price' => 1200, 'channels' => 'Mobile only'],
            ['name' => 'Showmax Standard', 'price' => 2900, 'channels' => 'Full access']
        ]
    ]
];

$selectedProvider = $_GET['provider'] ?? 'dstv';
$smartCardVerified = false;
$customerName = '';

// Handle smart card verification
if (isset($_POST['verify_card'])) {
    $provider = $_POST['provider'] ?? '';
    $smart_card = $_POST['smart_card'] ?? '';
    
    // Simulate smart card verification (in production, call actual API)
    if (!empty($smart_card) && strlen($smart_card) >= 6) {
        $smartCardVerified = true;
        $customerName = 'Jane Doe'; // This would come from API
        Session::setSuccess('Smart card verified successfully');
    } else {
        Session::setError('Invalid smart card number');
    }
}

// Handle payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subscribe'])) {
    // Verify CSRF
    if (!isset($_POST['csrf_token']) || !Session::verifyCSRF($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }
    
    $provider = $_POST['provider'] ?? '';
    $smart_card = $_POST['smart_card'] ?? '';
    $package = intval($_POST['package'] ?? 0);
    $phone = $_POST['phone'] ?? '';
    
    $errors = [];
    
    if (!array_key_exists($provider, $providers)) {
        $errors[] = 'Please select a valid provider';
    }
    
    if (empty($smart_card) || strlen($smart_card) < 6) {
        $errors[] = 'Please enter a valid smart card number';
    }
    
    if (!isset($providers[$provider]['packages'][$package])) {
        $errors[] = 'Please select a valid package';
    }
    
    $selectedPackage = $providers[$provider]['packages'][$package];
    $amount = $selectedPackage['price'];
    
    if (!preg_match('/^[0-9]{11}$/', $phone)) {
        $errors[] = 'Please enter a valid 11-digit phone number';
    }
    
    if ($walletBalance < $amount) {
        $errors[] = 'Insufficient balance. Please fund your wallet.';
    }
    
    if (empty($errors)) {
        $reference = generate_reference('CBL');
        
        $db->begin_transaction();
        
        try {
            // Deduct from wallet
            $stmt = $db->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ? AND wallet_balance >= ?");
            $stmt->bind_param("dii", $amount, $userId, $amount);
            $stmt->execute();
            
            if ($stmt->affected_rows === 0) {
                throw new Exception('Failed to deduct from wallet');
            }
            
            // Record transaction
            $plan_name = $selectedPackage['name'];
            $stmt = $db->prepare("INSERT INTO transactions (user_id, transaction_id, type, amount, network, smart_card, data_plan, phone_number, status) VALUES (?, ?, 'cable', ?, ?, ?, ?, ?, 'success')");
            $stmt->bind_param("isdssss", $userId, $reference, $amount, $provider, $smart_card, $plan_name, $phone);
            $stmt->execute();
            $transactionId = $stmt->insert_id;
            
            // Record wallet transaction
            $stmt = $db->prepare("INSERT INTO wallet_transactions (user_id, reference, type, amount, balance_before, balance_after, status) VALUES (?, ?, 'debit', ?, ?, ?, 'completed')");
            $balance_before = $walletBalance;
            $balance_after = $walletBalance - $amount;
            $stmt->bind_param("isddd", $userId, $reference, $amount, $balance_before, $balance_after);
            $stmt->execute();
            
            // Create notification
            $title = "Cable TV Subscription Successful";
            $message = "You have successfully subscribed to " . $selectedPackage['name'] . " for smart card " . $smart_card;
            $stmt = $db->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?, 'transaction', ?, ?)");
            $stmt->bind_param("iss", $userId, $title, $message);
            $stmt->execute();
            
            $db->commit();
            
            Session::setSuccess('Cable TV subscription successful!');
            redirect('transaction_details.php?id=' . $transactionId);
            
        } catch (Exception $e) {
            $db->rollback();
            Session::setError('Transaction failed: ' . $e->getMessage());
        }
    } else {
        foreach ($errors as $error) {
            Session::setError($error);
        }
    }
}

// Get recent cable subscriptions
$stmt = $db->prepare("SELECT * FROM transactions WHERE user_id = ? AND type = 'cable' ORDER BY created_at DESC LIMIT 5");
$stmt->bind_param("i", $userId);
$stmt->execute();
$recent = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Pay Cable TV';
include '../partials/user_header.php';
?>

<!-- Main Content -->
<div class="main-content">
    <div class="container">
        <!-- Page Header -->
        <div class="content-header">
            <h2><i class="fas fa-tv"></i> Pay Cable TV</h2>
            <div class="balance-badge" style="background: var(--primary);">
                <i class="fas fa-wallet"></i>
                <span><?php echo format_money($walletBalance); ?></span>
            </div>
        </div>

        <!-- Alerts -->
        <?php if ($error = Session::getError()): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success = Session::getSuccess()): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <!-- Main Subscription Card -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-shopping-cart"></i> Cable TV Subscription</h3>
            </div>
            
            <form method="POST" id="cableForm">
                <input type="hidden" name="csrf_token" value="<?php echo Session::generateCSRF(); ?>">
                
                <!-- Provider Selection -->
                <div class="form-group">
                    <label>Select Provider</label>
                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem;">
                        <?php foreach ($providers as $code => $provider): ?>
                            <label class="provider-card" style="cursor: pointer;" onclick="selectProvider('<?php echo $code; ?>')">
                                <input type="radio" name="provider" value="<?php echo $code; ?>" 
                                       <?php echo ($selectedProvider == $code) ? 'checked' : ''; ?>
                                       style="display: none;" required>
                                <div class="provider-option" style="padding: 1.5rem 1rem; border: 2px solid var(--gray-light); border-radius: var(--radius-lg); text-align: center; transition: var(--transition); background: <?php echo ($selectedProvider == $code) ? 'linear-gradient(135deg, var(--primary), var(--secondary))' : 'white'; ?>; color: <?php echo ($selectedProvider == $code) ? 'white' : 'inherit'; ?>;">
                                    <i class="fas <?php echo $provider['icon']; ?>" style="font-size: 2rem; margin-bottom: 0.5rem; color: <?php echo ($selectedProvider == $code) ? 'white' : $provider['color']; ?>;"></i>
                                    <div style="font-weight: 500;"><?php echo $provider['name']; ?></div>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Smart Card Number and Verification -->
                <div class="form-group">
                    <label for="smart_card">Smart Card Number</label>
                    <div style="display: flex; gap: 0.5rem;">
                        <div style="flex: 1;">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                <input type="text" class="form-control" id="smart_card" name="smart_card" 
                                       required placeholder="Enter smart card number"
                                       value="<?php echo htmlspecialchars($_POST['smart_card'] ?? ''); ?>">
                            </div>
                        </div>
                        <button type="submit" name="verify_card" class="btn btn-light">
                            <i class="fas fa-check-circle"></i> Verify
                        </button>
                    </div>
                </div>
                
                <!-- Customer Info (shown after verification) -->
                <?php if ($smartCardVerified): ?>
                    <div style="background: var(--light); padding: 1rem; border-radius: var(--radius); margin-bottom: 1.5rem;">
                        <p><strong>Customer Name:</strong> <?php echo $customerName; ?></p>
                    </div>
                <?php endif; ?>
                
                <!-- Package Selection -->
                <div class="form-group">
                    <label>Select Package</label>
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;" id="packagesGrid">
                        <?php foreach ($providers[$selectedProvider]['packages'] as $index => $pkg): ?>
                            <label class="package-card" style="cursor: pointer;" onclick="selectPackage(<?php echo $index; ?>)">
                                <input type="radio" name="package" value="<?php echo $index; ?>" 
                                       <?php echo ($index == 0) ? 'checked' : ''; ?>
                                       style="display: none;">
                                <div class="package-option" style="padding: 1.5rem 1rem; border: 2px solid var(--gray-light); border-radius: var(--radius-lg); text-align: center; transition: var(--transition);">
                                    <h4 style="font-size: 1.1rem; margin-bottom: 0.5rem;"><?php echo $pkg['name']; ?></h4>
                                    <p style="font-size: 0.875rem; color: var(--gray); margin-bottom: 0.5rem;"><?php echo $pkg['channels']; ?></p>
                                    <p style="font-weight: 700; color: var(--primary); font-size: 1.25rem;">
                                        <?php echo format_money($pkg['price']); ?>
                                    </p>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Phone Number -->
                <div class="form-group">
                    <label for="phone">Phone Number (for receipt)</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-phone"></i></span>
                        <input type="tel" class="form-control" id="phone" name="phone" 
                               pattern="[0-9]{11}" required placeholder="08012345678"
                               value="<?php echo htmlspecialchars($_POST['phone'] ?? $user['phone']); ?>"
                               maxlength="11">
                    </div>
                </div>
                
                <!-- Submit Button -->
                <button type="submit" name="subscribe" class="btn btn-primary btn-block" id="submitBtn" 
                        <?php echo $walletBalance < 1000 ? 'disabled' : ''; ?>>
                    <i class="fas fa-check-circle"></i> Subscribe Now
                </button>
                
                <?php if ($walletBalance < 1000): ?>
                    <p style="text-align: center; margin-top: 1rem; color: var(--danger);">
                        <i class="fas fa-exclamation-triangle"></i>
                        Insufficient balance. <a href="wallet.php" style="color: var(--primary);">Fund your wallet</a>
                    </p>
                <?php endif; ?>
            </form>
        </div>

        <!-- Recent Subscriptions -->
        <?php if (!empty($recent)): ?>
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> Recent Subscriptions</h3>
                    <a href="transactions.php" class="btn btn-light btn-small">View All</a>
                </div>
                
                <div class="transaction-list">
                    <?php foreach ($recent as $r): ?>
                        <a href="transaction_details.php?id=<?php echo $r['id']; ?>" class="transaction-item" style="text-decoration: none;">
                            <div class="transaction-left">
                                <div class="transaction-icon">
                                    <i class="fas fa-tv"></i>
                                </div>
                                <div class="transaction-details">
                                    <h4><?php echo strtoupper($r['network']); ?> - <?php echo $r['data_plan']; ?></h4>
                                    <p>
                                        Smart Card: <?php echo $r['smart_card']; ?> • 
                                        <?php echo time_ago($r['created_at']); ?>
                                    </p>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <span class="transaction-amount negative">-<?php echo format_money($r['amount']); ?></span>
                                <br>
                                <span class="badge badge-<?php echo $r['status']; ?>">
                                    <?php echo ucfirst($r['status']); ?>
                                </span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
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
function selectProvider(provider) {
    window.location.href = 'pay_cable.php?provider=' + provider;
}

function selectPackage(index) {
    // Update UI
    document.querySelectorAll('.package-option').forEach((opt, i) => {
        if (i === index) {
            opt.style.background = 'linear-gradient(135deg, var(--primary), var(--secondary))';
            opt.style.color = 'white';
            opt.querySelector('p:last-child').style.color = 'white';
            opt.querySelector('p').style.color = 'rgba(255,255,255,0.9)';
        } else {
            opt.style.background = 'white';
            opt.style.color = 'inherit';
            opt.querySelector('p:last-child').style.color = 'var(--primary)';
            opt.querySelector('p').style.color = 'var(--gray)';
        }
    });
}

// Form validation
document.getElementById('cableForm').addEventListener('submit', function(e) {
    if (e.submitter && e.submitter.name === 'subscribe') {
        const btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    }
});

// Phone number formatting
document.getElementById('phone').addEventListener('input', function(e) {
    this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11);
});

// Initialize package styling
setTimeout(() => {
    selectPackage(0);
}, 100);
</script>

</body>
</html>