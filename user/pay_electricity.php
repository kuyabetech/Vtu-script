<?php
// user/pay_electricity.php - Pay Electricity Bills Page
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

Auth::requireLogin();

$db = db();
$userId = Session::userId();
$walletBalance = getUserBalance($userId);

// Electricity Distribution Companies
$discos = [
    'ikedc' => [
        'name' => 'Ikeja Electric',
        'code' => 'IKEDC',
        'icon' => 'fa-bolt',
        'color' => '#f59e0b',
        'areas' => ['Ikeja', 'Maryland', 'Oshodi', 'Agege', 'Alimosho']
    ],
    'ekedc' => [
        'name' => 'Eko Electric',
        'code' => 'EKEDC',
        'icon' => 'fa-bolt',
        'color' => '#10b981',
        'areas' => ['Lagos Island', 'Apapa', 'Surulere', 'Victoria Island', 'Lekki']
    ],
    'aedc' => [
        'name' => 'Abuja Electric',
        'code' => 'AEDC',
        'icon' => 'fa-bolt',
        'color' => '#3b82f6',
        'areas' => ['Abuja', 'Suleja', 'Minna', 'Lokoja', 'Kogi']
    ],
    'phed' => [
        'name' => 'Port Harcourt Electric',
        'code' => 'PHED',
        'icon' => 'fa-bolt',
        'color' => '#ef4444',
        'areas' => ['Port Harcourt', 'Aba', 'Uyo', 'Calabar', 'Yenagoa']
    ],
    'bedc' => [
        'name' => 'Benin Electric',
        'code' => 'BEDC',
        'icon' => 'fa-bolt',
        'color' => '#8b5cf6',
        'areas' => ['Benin', 'Warri', 'Sapele', 'Asaba', 'Agbor']
    ],
    'ibedc' => [
        'name' => 'Ibadan Electric',
        'code' => 'IBEDC',
        'icon' => 'fa-bolt',
        'color' => '#ec4899',
        'areas' => ['Ibadan', 'Oyo', 'Ogbomoso', 'Ife', 'Ilesha']
    ]
];

$selectedDisco = $_GET['disco'] ?? 'ikedc';
$meterVerified = false;
$customerName = '';
$customerAddress = '';

// Handle meter verification
if (isset($_POST['verify_meter'])) {
    $disco = $_POST['disco'] ?? '';
    $meter_number = $_POST['meter_number'] ?? '';
    $meter_type = $_POST['meter_type'] ?? 'prepaid';
    
    // Simulate meter verification (in production, call actual API)
    if (!empty($meter_number) && strlen($meter_number) >= 6) {
        $meterVerified = true;
        $customerName = 'John Doe'; // This would come from API
        $customerAddress = '24, Example Street, Lagos';
        Session::setSuccess('Meter verified successfully');
    } else {
        Session::setError('Invalid meter number');
    }
}

// Handle payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_bill'])) {
    // Verify CSRF
    if (!isset($_POST['csrf_token']) || !Session::verifyCSRF($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }
    
    $disco = $_POST['disco'] ?? '';
    $meter_number = $_POST['meter_number'] ?? '';
    $meter_type = $_POST['meter_type'] ?? 'prepaid';
    $amount = floatval($_POST['amount'] ?? 0);
    $phone = $_POST['phone'] ?? '';
    
    $errors = [];
    
    if (!array_key_exists($disco, $discos)) {
        $errors[] = 'Please select a valid electricity provider';
    }
    
    if (empty($meter_number) || strlen($meter_number) < 6) {
        $errors[] = 'Please enter a valid meter number';
    }
    
    if ($amount < MIN_ELECTRICITY) {
        $errors[] = 'Minimum amount is ' . format_money(MIN_ELECTRICITY);
    }
    
    if (!preg_match('/^[0-9]{11}$/', $phone)) {
        $errors[] = 'Please enter a valid 11-digit phone number';
    }
    
    if ($walletBalance < $amount) {
        $errors[] = 'Insufficient balance. Please fund your wallet.';
    }
    
    if (empty($errors)) {
        $reference = generate_reference('ELEC');
        
        $db->begin_transaction();
        
        try {
            // Deduct from wallet
            $stmt = $db->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ? AND wallet_balance >= ?");
            $stmt->bind_param("dii", $amount, $userId, $amount);
            $stmt->execute();
            
            if ($stmt->affected_rows === 0) {
                throw new Exception('Failed to deduct from wallet');
            }
            
            // Generate token (in production, this would come from API)
            $token = implode('-', str_split(strtoupper(substr(md5(uniqid()), 0, 16)), 4));
            
            // Record transaction
            $stmt = $db->prepare("INSERT INTO transactions (user_id, transaction_id, type, amount, network, meter_number, token, phone_number, status) VALUES (?, ?, 'electricity', ?, ?, ?, ?, ?, 'success')");
            $stmt->bind_param("isdssss", $userId, $reference, $amount, $disco, $meter_number, $token, $phone);
            $stmt->execute();
            $transactionId = $stmt->insert_id;
            
            // Record wallet transaction
            $stmt = $db->prepare("INSERT INTO wallet_transactions (user_id, reference, type, amount, balance_before, balance_after, status) VALUES (?, ?, 'debit', ?, ?, ?, 'completed')");
            $balance_before = $walletBalance;
            $balance_after = $walletBalance - $amount;
            $stmt->bind_param("isddd", $userId, $reference, $amount, $balance_before, $balance_after);
            $stmt->execute();
            
            // Create notification
            $title = "Electricity Bill Payment Successful";
            $message = "You have successfully paid ₦" . number_format($amount, 2) . " for meter " . $meter_number;
            $stmt = $db->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?, 'transaction', ?, ?)");
            $stmt->bind_param("iss", $userId, $title, $message);
            $stmt->execute();
            
            $db->commit();
            
            Session::setSuccess('Electricity bill paid successfully! Token: ' . $token);
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

// Get recent electricity payments
$stmt = $db->prepare("SELECT * FROM transactions WHERE user_id = ? AND type = 'electricity' ORDER BY created_at DESC LIMIT 5");
$stmt->bind_param("i", $userId);
$stmt->execute();
$recent = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Pay Electricity';
include '../partials/user_header.php';
?>

<!-- Main Content -->
<div class="main-content">
    <div class="container">
        <!-- Page Header -->
        <div class="content-header">
            <h2><i class="fas fa-bolt"></i> Pay Electricity Bill</h2>
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

        <!-- Main Payment Card -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-shopping-cart"></i> Pay Electricity Bill</h3>
            </div>
            
            <form method="POST" id="electricityForm">
                <input type="hidden" name="csrf_token" value="<?php echo Session::generateCSRF(); ?>">
                
                <!-- Disco Selection -->
                <div class="form-group">
                    <label>Select Electricity Provider</label>
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">
                        <?php foreach ($discos as $code => $disco): ?>
                            <label class="disco-card" style="cursor: pointer;" onclick="selectDisco('<?php echo $code; ?>')">
                                <input type="radio" name="disco" value="<?php echo $code; ?>" 
                                       <?php echo ($selectedDisco == $code) ? 'checked' : ''; ?>
                                       style="display: none;" required>
                                <div class="disco-option" style="padding: 1.5rem 1rem; border: 2px solid var(--gray-light); border-radius: var(--radius-lg); text-align: center; transition: var(--transition); background: <?php echo ($selectedDisco == $code) ? 'linear-gradient(135deg, var(--primary), var(--secondary))' : 'white'; ?>; color: <?php echo ($selectedDisco == $code) ? 'white' : 'inherit'; ?>;">
                                    <i class="fas <?php echo $disco['icon']; ?>" style="font-size: 2rem; margin-bottom: 0.5rem; color: <?php echo ($selectedDisco == $code) ? 'white' : $disco['color']; ?>;"></i>
                                    <div style="font-weight: 500;"><?php echo $disco['name']; ?></div>
                                    <small><?php echo $disco['code']; ?></small>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Meter Type -->
                <div class="form-group">
                    <label>Meter Type</label>
                    <div style="display: flex; gap: 2rem;">
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input type="radio" name="meter_type" value="prepaid" checked> 
                            <i class="fas fa-microchip" style="color: var(--primary);"></i>
                            Prepaid Meter
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input type="radio" name="meter_type" value="postpaid"> 
                            <i class="fas fa-file-invoice" style="color: var(--primary);"></i>
                            Postpaid Meter
                        </label>
                    </div>
                </div>
                
                <!-- Meter Number and Verification -->
                <div class="form-group">
                    <label for="meter_number">Meter Number</label>
                    <div style="display: flex; gap: 0.5rem;">
                        <div style="flex: 1;">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-microchip"></i></span>
                                <input type="text" class="form-control" id="meter_number" name="meter_number" 
                                       required placeholder="Enter meter number"
                                       value="<?php echo htmlspecialchars($_POST['meter_number'] ?? ''); ?>">
                            </div>
                        </div>
                        <button type="submit" name="verify_meter" class="btn btn-light">
                            <i class="fas fa-check-circle"></i> Verify
                        </button>
                    </div>
                </div>
                
                <!-- Customer Info (shown after verification) -->
                <?php if ($meterVerified): ?>
                    <div style="background: var(--light); padding: 1rem; border-radius: var(--radius); margin-bottom: 1.5rem;">
                        <p><strong>Customer Name:</strong> <?php echo $customerName; ?></p>
                        <p><strong>Address:</strong> <?php echo $customerAddress; ?></p>
                    </div>
                <?php endif; ?>
                
                <!-- Amount Input -->
                <div class="form-group">
                    <label for="amount">Amount (₦)</label>
                    <div class="input-group">
                        <span class="input-group-text">₦</span>
                        <input type="number" class="form-control" id="amount" name="amount" 
                               min="<?php echo MIN_ELECTRICITY; ?>" step="100" required 
                               placeholder="Enter amount"
                               value="<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>">
                    </div>
                </div>
                
                <!-- Quick Amount Buttons -->
                <div class="quick-amounts">
                    <button type="button" class="quick-amount-btn" onclick="setAmount(1000)">₦1,000</button>
                    <button type="button" class="quick-amount-btn" onclick="setAmount(2000)">₦2,000</button>
                    <button type="button" class="quick-amount-btn" onclick="setAmount(5000)">₦5,000</button>
                    <button type="button" class="quick-amount-btn" onclick="setAmount(10000)">₦10,000</button>
                    <button type="button" class="quick-amount-btn" onclick="setAmount(20000)">₦20,000</button>
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
                <button type="submit" name="pay_bill" class="btn btn-primary btn-block" id="submitBtn" 
                        <?php echo $walletBalance < MIN_ELECTRICITY ? 'disabled' : ''; ?>>
                    <i class="fas fa-bolt"></i> Pay Electricity Bill
                </button>
                
                <?php if ($walletBalance < MIN_ELECTRICITY): ?>
                    <p style="text-align: center; margin-top: 1rem; color: var(--danger);">
                        <i class="fas fa-exclamation-triangle"></i>
                        Insufficient balance. <a href="wallet.php" style="color: var(--primary);">Fund your wallet</a>
                    </p>
                <?php endif; ?>
            </form>
        </div>

        <!-- Recent Payments -->
        <?php if (!empty($recent)): ?>
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> Recent Electricity Payments</h3>
                    <a href="transactions.php" class="btn btn-light btn-small">View All</a>
                </div>
                
                <div class="transaction-list">
                    <?php foreach ($recent as $r): ?>
                        <a href="transaction_details.php?id=<?php echo $r['id']; ?>" class="transaction-item" style="text-decoration: none;">
                            <div class="transaction-left">
                                <div class="transaction-icon">
                                    <i class="fas fa-bolt"></i>
                                </div>
                                <div class="transaction-details">
                                    <h4><?php echo strtoupper($r['network']); ?> Electricity</h4>
                                    <p>
                                        Meter: <?php echo $r['meter_number']; ?> • 
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
function selectDisco(disco) {
    window.location.href = 'pay_electricity.php?disco=' + disco;
}

function setAmount(amount) {
    document.getElementById('amount').value = amount;
}

// Form validation
document.getElementById('electricityForm').addEventListener('submit', function(e) {
    if (e.submitter && e.submitter.name === 'pay_bill') {
        const btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    }
});

// Phone number formatting
document.getElementById('phone').addEventListener('input', function(e) {
    this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11);
});
</script>

</body>
</html>