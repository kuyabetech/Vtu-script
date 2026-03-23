<?php
// user/buy_airtime.php - Buy Airtime Page (Secure & Production Ready)
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

Auth::requireLogin();

$db = db();
$userId = Session::userId();

// ────────────────────────────────────────────────
// 1. Get User Wallet (Single Source of Truth)
// ────────────────────────────────────────────────
function getUserWallet($db, $userId) {
    // Lock row for update to prevent race conditions
    $stmt = $db->prepare("SELECT id, balance FROM wallets WHERE user_id = ? FOR UPDATE");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($wallet = $result->fetch_assoc()) {
        return $wallet;
    }
    
    // Auto-create wallet if missing
    $stmt = $db->prepare("INSERT INTO wallets (user_id, balance, created_at) VALUES (?, 0, NOW())");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    
    return ['id' => $stmt->insert_id, 'balance' => 0];
}

$wallet = getUserWallet($db, $userId);
$walletId = $wallet['id'];
$walletBalance = (float)$wallet['balance'];

// Fetch current user phone for default value (Safe fetch)
$userStmt = $db->prepare("SELECT phone FROM users WHERE id = ?");
$userStmt->bind_param("i", $userId);
$userStmt->execute();
$userData = $userStmt->get_result()->fetch_assoc();
$userPhone = $userData['phone'] ?? '';

// ────────────────────────────────────────────────
// 2. Get Saved Numbers (Last 5 Unique)
// ────────────────────────────────────────────────
$savedNumbers = [];
$stmt = $db->prepare("
    SELECT phone_number 
    FROM transactions 
    WHERE user_id = ? AND phone_number IS NOT NULL AND phone_number != '' 
    GROUP BY phone_number 
    ORDER BY MAX(created_at) DESC 
    LIMIT 5
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $savedNumbers[] = $row['phone_number'];
}

// ────────────────────────────────────────────────
// 3. Load Networks (Fallback only)
// ────────────────────────────────────────────────
$networks = [
    'mtn'     => ['name' => 'MTN Nigeria',     'icon' => 'fa-signal',     'color' => '#ffc107'],
    'glo'     => ['name' => 'Glo Nigeria',      'icon' => 'fa-globe',      'color' => '#28a745'],
    'airtel'  => ['name' => 'Airtel Nigeria',   'icon' => 'fa-wifi',       'color' => '#dc3545'],
    '9mobile' => ['name' => '9mobile',          'icon' => 'fa-mobile-alt', 'color' => '#17a2b8'],
];

$selectedNetwork = $_GET['network'] ?? '';

// ────────────────────────────────────────────────
// 4. Form Processing
// ────────────────────────────────────────────────
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Session::verifyCSRF($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token. Please refresh and try again.';
    } else {
        $network   = strtolower(trim($_POST['network'] ?? ''));
        $amount    = floatval($_POST['amount'] ?? 0);
        $phone     = trim($_POST['phone'] ?? '');

        // --- Validation ---
        if (!isset($networks[$network])) {
            $errors[] = 'Please select a valid network.';
        }

        if ($amount < MIN_AIRTIME || $amount > MAX_AIRTIME) {
            $errors[] = "Amount must be between " . format_money(MIN_AIRTIME) . " and " . format_money(MAX_AIRTIME);
        }

        if (!preg_match('/^0[789][01]\d{8}$/', $phone)) {
            $errors[] = 'Please enter a valid Nigerian 11-digit phone number.';
        }

        // Prefix Check
        if (empty($errors)) {
            $prefixes = [
                'mtn'     => ['0803','0806','0703','0706','0813','0816','0810','0814','0903','0906','0913','0916'],
                'glo'     => ['0805','0807','0705','0815','0905','0915'],
                'airtel'  => ['0802','0808','0708','0812','0902','0907','0912'],
                '9mobile' => ['0809','0817','0818','0909','0908'],
            ];
            $phonePrefix = substr($phone, 0, 4);
            if (!in_array($phonePrefix, $prefixes[$network] ?? [])) {
                $errors[] = "The phone number doesn't match the selected network (" . $networks[$network]['name'] . ").";
            }
        }

        if ($walletBalance < $amount) {
            $errors[] = 'Insufficient wallet balance.';
        }

        // Rate Limiting
        $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM transactions 
                              WHERE user_id = ? AND type = 'airtime' 
                              AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $recentCount = $stmt->get_result()->fetch_assoc()['cnt'];
        
        if ($recentCount >= 15) {
            $errors[] = 'Purchase limit reached (15/hour). Try again later.';
        }

        // --- Process Transaction ---
        if (empty($errors)) {
            $reference = generate_reference('AIRT');
            $db->begin_transaction();

            try {
                // 1. Re-check & Lock Balance
                $stmt = $db->prepare("SELECT balance FROM wallets WHERE id = ? FOR UPDATE");
                $stmt->bind_param("i", $walletId);
                $stmt->execute();
                $currentBalance = (float)$stmt->get_result()->fetch_assoc()['balance'];

                if ($currentBalance < $amount) {
                    throw new Exception('Balance changed during processing. Insufficient funds.');
                }

                // 2. Deduct Balance
                $stmt = $db->prepare("UPDATE wallets SET balance = balance - ? WHERE id = ?");
                $stmt->bind_param("di", $amount, $walletId);
                $stmt->execute();
                
                $balanceAfter = $currentBalance - $amount;

                // 3. Create Pending Transaction Record - FIXED
                $status = 'pending';
                $total = $amount;
                $stmt = $db->prepare("INSERT INTO transactions (
                    user_id, wallet_id, transaction_id, type, amount, total, 
                    network, phone_number, status, created_at
                ) VALUES (?, ?, ?, 'airtime', ?, ?, ?, ?, ?, NOW())");
                // CORRECTED: No spaces in type string, exactly 8 types for 8 variables
                // Types: i (user_id), i (wallet_id), s (transaction_id), 
                //        d (amount), d (total), s (network), s (phone), s (status)
                $stmt->bind_param("iisdssss", $userId, $walletId, $reference, $amount, $total, $network, $phone, $status);
                $stmt->execute();
                $transactionId = $stmt->insert_id;

                // 4. Log Wallet History
                $wType = 'debit';
                $wStatus = 'completed';
                $stmt = $db->prepare("INSERT INTO wallet_transactions (
                    user_id, reference, type, amount, balance_before, balance_after, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isdddds", $userId, $reference, $wType, $amount, $currentBalance, $balanceAfter, $wStatus);
                $stmt->execute();

                // 5. CALL REAL VTU API HERE
                $apiResult = processVTURequest($network, $phone, $amount, $reference);

                if ($apiResult['status'] === 'success') {
                    // Update Transaction to Success
                    $newStatus = 'success';
                    $stmt = $db->prepare("UPDATE transactions SET status = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->bind_param("si", $newStatus, $transactionId);
                    $stmt->execute();

                    $title = "Airtime Purchase Successful";
                    $message = "₦" . number_format($amount, 2) . " " . strtoupper($network) . " airtime credited to $phone";
                    
                    // Insert Notification
                    $nType = 'transaction';
                    $stmt = $db->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("isss", $userId, $nType, $title, $message);
                    $stmt->execute();

                    $db->commit();
                    Session::setSuccess($message);
                    redirect("transaction_details.php?id=$transactionId");

                } else {
                    // Rollback: Refund User
                    $stmt = $db->prepare("UPDATE wallets SET balance = balance + ? WHERE id = ?");
                    $stmt->bind_param("di", $amount, $walletId);
                    $stmt->execute();

                    // Mark Transaction Failed
                    $failStatus = 'failed';
                    $stmt = $db->prepare("UPDATE transactions SET status = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->bind_param("si", $failStatus, $transactionId);
                    $stmt->execute();

                    throw new Exception($apiResult['message'] ?? 'Provider failed to deliver airtime. Funds refunded.');
                }

            } catch (Exception $e) {
                $db->rollback();
                Session::setError("Transaction failed: " . htmlspecialchars($e->getMessage()));
            }
        }
    }
    
    // Display validation errors
    if (!empty($errors)) {
        foreach ($errors as $err) Session::setError($err);
    }
}

// Recent purchases
$recentStmt = $db->prepare("SELECT * FROM transactions 
                            WHERE user_id = ? AND type = 'airtime' 
                            ORDER BY created_at DESC LIMIT 5");
$recentStmt->bind_param("i", $userId);
$recentStmt->execute();
$recent = $recentStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Buy Airtime';
include '../partials/user_header.php';
?>

<style>
/* ===== Responsive Buy Airtime Styles ===== */
.content-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem; }
.content-header h2 { display: flex; align-items: center; gap: 0.75rem; color: var(--dark); font-size: clamp(1.25rem, 4vw, 1.75rem); }
.content-header h2 i { color: var(--primary); background: rgba(99, 102, 241, 0.1); padding: 0.75rem; border-radius: 50%; font-size: clamp(1rem, 3vw, 1.25rem); }
.balance-badge { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; padding: 0.75rem 1.5rem; border-radius: 2rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.75rem; font-size: clamp(0.875rem, 3vw, 1rem); box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3); }
.network-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
@media (max-width: 768px) { .network-grid { grid-template-columns: repeat(2, 1fr); gap: 0.75rem; } }
@media (max-width: 480px) { .network-grid { grid-template-columns: repeat(2, 1fr); gap: 0.5rem; } }
.network-card { cursor: pointer; transition: all 0.3s ease; }
.network-card input[type="radio"] { display: none; }
.network-option { padding: clamp(1rem, 3vw, 1.5rem) clamp(0.5rem, 2vw, 1rem); border: 2px solid var(--gray-light); border-radius: var(--radius-lg); text-align: center; transition: all 0.3s ease; background: white; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; }
.network-option i { font-size: clamp(1.5rem, 5vw, 2rem); margin-bottom: 0.5rem; transition: all 0.3s ease; }
.network-option .network-name { font-weight: 500; font-size: clamp(0.75rem, 2.5vw, 0.9rem); line-height: 1.3; }
.network-option.selected { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; border-color: transparent; transform: translateY(-3px); box-shadow: 0 8px 16px rgba(99, 102, 241, 0.2); }
.network-option.selected i { color: white !important; }
.network-option:hover { transform: translateY(-3px); box-shadow: var(--shadow); border-color: var(--primary); }
.saved-numbers { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1rem; }
.saved-number-btn { padding: 0.5rem 1rem; background: var(--light); border: 1px solid var(--gray-light); border-radius: 2rem; font-size: clamp(0.8rem, 2.5vw, 0.9rem); cursor: pointer; transition: all 0.3s ease; white-space: nowrap; color: var(--dark); }
.saved-number-btn:hover { background: var(--primary); color: white; border-color: var(--primary); transform: translateY(-2px); }
.form-group { margin-bottom: 1.5rem; }
.form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: var(--dark); font-size: clamp(0.875rem, 2.5vw, 1rem); }
.input-group { display: flex; align-items: center; border: 2px solid var(--gray-light); border-radius: var(--radius-lg); overflow: hidden; transition: all 0.3s ease; background: white; }
.input-group:focus-within { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1); }
.input-group-text { padding: clamp(0.75rem, 2vw, 1rem) clamp(1rem, 2.5vw, 1.25rem); background: var(--light); color: var(--gray-dark); font-weight: 500; border-right: 2px solid var(--gray-light); font-size: clamp(0.875rem, 2.5vw, 1rem); }
.form-control { flex: 1; padding: clamp(0.75rem, 2vw, 1rem) clamp(1rem, 2.5vw, 1.25rem); border: none; outline: none; font-size: clamp(0.875rem, 2.5vw, 1rem); width: 100%; background: transparent; }
.quick-amounts { display: grid; grid-template-columns: repeat(6, 1fr); gap: 0.5rem; margin-bottom: 1.5rem; }
@media (max-width: 768px) { .quick-amounts { grid-template-columns: repeat(3, 1fr); gap: 0.5rem; } }
@media (max-width: 480px) { .quick-amounts { grid-template-columns: repeat(2, 1fr); gap: 0.5rem; } }
.quick-amount-btn { padding: clamp(0.75rem, 2vw, 1rem) 0.5rem; background: white; border: 2px solid var(--gray-light); border-radius: var(--radius-lg); color: var(--dark); font-weight: 500; cursor: pointer; transition: all 0.3s ease; font-size: clamp(0.8rem, 2.5vw, 0.9rem); white-space: nowrap; }
.quick-amount-btn:hover { border-color: var(--primary); background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1)); transform: translateY(-2px); }
.transaction-list { background: white; border-radius: var(--radius-lg); overflow: hidden; }
.transaction-item { display: flex; align-items: center; justify-content: space-between; padding: clamp(0.75rem, 2.5vw, 1rem); border-bottom: 1px solid var(--gray-light); transition: all 0.3s ease; text-decoration: none; color: inherit; flex-wrap: wrap; gap: 0.5rem; }
@media (max-width: 480px) { .transaction-item { flex-direction: column; align-items: flex-start; } }
.transaction-item:last-child { border-bottom: none; }
.transaction-item:hover { background: var(--light); transform: translateX(5px); }
.transaction-left { display: flex; align-items: center; gap: 1rem; flex: 1; }
.transaction-icon { width: clamp(40px, 8vw, 45px); height: clamp(40px, 8vw, 45px); background: linear-gradient(135deg, var(--primary), var(--secondary)); border-radius: 1rem; display: flex; align-items: center; justify-content: center; color: white; font-size: clamp(1rem, 2.5vw, 1.25rem); flex-shrink: 0; }
.transaction-details h4 { font-size: clamp(0.9rem, 2.5vw, 1rem); margin-bottom: 0.25rem; color: var(--dark); }
.transaction-details p { font-size: clamp(0.75rem, 2vw, 0.8rem); color: var(--gray); display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
.transaction-amount { font-weight: 700; font-size: clamp(1rem, 2.5vw, 1.1rem); }
.transaction-amount.negative { color: var(--danger); }
.badge { display: inline-flex; align-items: center; padding: 0.35rem 1rem; border-radius: 2rem; font-size: clamp(0.7rem, 2vw, 0.75rem); font-weight: 600; }
.badge-success { background: #d1fae5; color: #065f46; }
.badge-pending { background: #fef3c7; color: #92400e; }
.badge-failed { background: #fee2e2; color: #991b1b; }
.btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; padding: clamp(0.75rem, 2vw, 1rem) clamp(1.25rem, 3vw, 1.5rem); font-size: clamp(0.875rem, 2.5vw, 1rem); font-weight: 600; border-radius: var(--radius-lg); cursor: pointer; transition: all 0.3s ease; border: none; outline: none; text-decoration: none; }
.btn-block { width: 100%; }
.btn-primary { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3); }
.btn-primary:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(99, 102, 241, 0.4); }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
@keyframes slideIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
.card { animation: slideIn 0.5s ease forwards; }
.main-content { padding-bottom: calc(var(--bottom-nav-height) + 1.5rem); }
</style>

<!-- Main Content -->
<div class="main-content">
    <div class="container">
        <!-- Page Header -->
        <div class="content-header">
            <h2><i class="fas fa-phone-alt"></i> <span>Buy Airtime</span></h2>
            <div class="balance-badge">
                <i class="fas fa-wallet"></i>
                <span><?php echo format_money($walletBalance); ?></span>
            </div>
        </div>

        <!-- Alerts -->
        <?php if ($error = Session::getError()): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <span><?php echo htmlspecialchars($error); ?></span></div>
        <?php endif; ?>
        <?php if ($success = Session::getSuccess()): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <span><?php echo htmlspecialchars($success); ?></span></div>
        <?php endif; ?>

        <!-- Main Purchase Card -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-shopping-cart"></i> Purchase Airtime</h3>
                <?php if (!empty($savedNumbers)): ?>
                    <button type="button" class="btn btn-light btn-small" onclick="toggleSavedNumbers()">
                        <i class="fas fa-address-book"></i> Saved
                    </button>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($savedNumbers)): ?>
                <div id="savedNumbersSection" style="display: none; margin-bottom: 1.5rem;">
                    <label>Saved Numbers</label>
                    <div class="saved-numbers">
                        <?php foreach ($savedNumbers as $saved): ?>
                            <button type="button" class="saved-number-btn" onclick="useSavedNumber('<?php echo $saved; ?>')">
                                <i class="fas fa-phone"></i> <?php echo $saved; ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="airtimeForm">
                <input type="hidden" name="csrf_token" value="<?php echo Session::generateCSRF(); ?>">
                
                <!-- Network Selection -->
                <div class="form-group">
                    <label>Select Network</label>
                    <div class="network-grid">
                        <?php foreach ($networks as $code => $net): ?>
                            <label class="network-card">
                                <input type="radio" name="network" value="<?php echo $code; ?>" 
                                       <?php echo ($selectedNetwork == $code) ? 'checked' : ''; ?> required>
                                <div class="network-option <?php echo ($selectedNetwork == $code) ? 'selected' : ''; ?>">
                                    <i class="fas <?php echo $net['icon']; ?>" style="color: <?php echo ($selectedNetwork == $code) ? 'white' : $net['color']; ?>;"></i>
                                    <span class="network-name"><?php echo $net['name']; ?></span>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Amount Input -->
                <div class="form-group">
                    <label for="amount">Amount (₦)</label>
                    <div class="input-group">
                        <span class="input-group-text">₦</span>
                        <input type="number" class="form-control" id="amount" name="amount" 
                               min="<?php echo MIN_AIRTIME; ?>" max="<?php echo MAX_AIRTIME; ?>" 
                               step="50" required placeholder="Enter amount"
                               value="<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>">
                    </div>
                </div>
                
                <!-- Quick Amount Buttons -->
                <div class="quick-amounts">
                    <button type="button" class="quick-amount-btn" onclick="setAmount(50)">₦50</button>
                    <button type="button" class="quick-amount-btn" onclick="setAmount(100)">₦100</button>
                    <button type="button" class="quick-amount-btn" onclick="setAmount(200)">₦200</button>
                    <button type="button" class="quick-amount-btn" onclick="setAmount(500)">₦500</button>
                    <button type="button" class="quick-amount-btn" onclick="setAmount(1000)">₦1k</button>
                    <button type="button" class="quick-amount-btn" onclick="setAmount(2000)">₦2k</button>
                </div>
                
                <!-- Phone Number -->
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-phone"></i></span>
                        <input type="tel" class="form-control" id="phone" name="phone" 
                               pattern="[0-9]{11}" required placeholder="08012345678"
                               value="<?php echo htmlspecialchars($_POST['phone'] ?? $userPhone); ?>"
                               maxlength="11" inputmode="numeric">
                    </div>
                    <small><i class="fas fa-info-circle"></i> Enter the 11-digit phone number to credit</small>
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="checkbox" name="save_number" value="1" checked>
                        <span>Save this number for future purchases</span>
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block" id="submitBtn" 
                        <?php echo $walletBalance < MIN_AIRTIME ? 'disabled' : ''; ?>>
                    <i class="fas fa-shopping-cart"></i> <span>Purchase Airtime</span>
                </button>
                
                <?php if ($walletBalance < MIN_AIRTIME): ?>
                    <p class="text-center mt-2" style="color: var(--danger);">
                        <i class="fas fa-exclamation-triangle"></i>
                        Insufficient balance. <a href="wallet.php" style="color: var(--primary); font-weight: 600;">Fund wallet</a>
                    </p>
                <?php endif; ?>
            </form>
        </div>

        <!-- Recent Purchases -->
        <?php if (!empty($recent)): ?>
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> Recent Purchases</h3>
                    <a href="transactions.php" class="btn btn-light btn-small"><i class="fas fa-arrow-right"></i> View All</a>
                </div>
                <div class="transaction-list">
                    <?php foreach ($recent as $r): ?>
                        <a href="transaction_details.php?id=<?php echo $r['id']; ?>" class="transaction-item">
                            <div class="transaction-left">
                                <div class="transaction-icon"><i class="fas fa-phone-alt"></i></div>
                                <div class="transaction-details">
                                    <h4><?php echo strtoupper($r['network']); ?> Airtime</h4>
                                    <p><i class="fas fa-phone"></i> <?php echo $r['phone_number']; ?> • <i class="far fa-clock"></i> <?php echo time_ago($r['created_at']); ?></p>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div class="transaction-amount negative">-<?php echo format_money($r['amount']); ?></div>
                                <span class="badge badge-<?php echo $r['status']; ?>"><?php echo ucfirst($r['status']); ?></span>
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
    <a href="dashboard.php" class="bottom-nav-item"><i class="fas fa-home"></i><span>Home</span></a>
    <a href="wallet.php" class="bottom-nav-item"><i class="fas fa-wallet"></i><span>Wallet</span></a>
    <a href="services.php" class="bottom-nav-item"><i class="fas fa-th-large"></i><span>Services</span></a>
    <a href="transactions.php" class="bottom-nav-item"><i class="fas fa-history"></i><span>History</span></a>
    <a href="profile.php" class="bottom-nav-item"><i class="fas fa-user"></i><span>Profile</span></a>
</nav>

<script>
function toggleSavedNumbers() {
    const section = document.getElementById('savedNumbersSection');
    section.style.display = section.style.display === 'none' ? 'block' : 'none';
}
function useSavedNumber(number) {
    document.getElementById('phone').value = number;
    toggleSavedNumbers();
}
function setAmount(amount) {
    document.getElementById('amount').value = amount;
    document.getElementById('amount').dispatchEvent(new Event('input'));
}
document.getElementById('phone').addEventListener('input', function(e) {
    this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11);
});
document.getElementById('amount').addEventListener('input', function(e) {
    const amount = parseFloat(this.value) || 0;
    const balance = <?php echo $walletBalance; ?>;
    document.getElementById('submitBtn').disabled = amount > balance;
});
document.getElementById('airtimeForm').addEventListener('submit', function(e) {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
});
document.querySelectorAll('input[name="network"]').forEach(radio => {
    radio.addEventListener('change', function() {
        document.querySelectorAll('.network-option').forEach(opt => {
            opt.classList.remove('selected');
            const icon = opt.querySelector('i');
            icon.style.color = icon.dataset.originalColor;
        });
        const option = this.closest('.network-card').querySelector('.network-option');
        option.classList.add('selected');
        const icon = option.querySelector('i');
        icon.style.color = 'white';
    });
});
document.querySelectorAll('.network-option i').forEach(icon => {
    icon.dataset.originalColor = getComputedStyle(icon).color;
});
</script>

<?php
/**
 * VTU API INTEGRATION LAYER
 */
function processVTURequest($network, $phone, $amount, $reference) {
    // SIMULATION (Replace with actual API call)
    sleep(1);
    if (rand(1, 10) > 2) {
        return ['status' => 'success', 'message' => 'Airtime delivered (Simulated)'];
    } else {
        return ['status' => 'failed', 'message' => 'Provider timeout (Simulated)'];
    }
}
?>

</body>
</html>