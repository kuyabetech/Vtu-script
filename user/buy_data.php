<?php
// user/buy_data.php - FIXED: wallet_id NULL Issue with Debug
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

Auth::requireLogin();

$db = db();
$userId = Session::userId();

// ────────────────────────────────────────────────
// 1. WALLET MANAGEMENT (With Debug & Fallback)
// ────────────────────────────────────────────────
function getUserWallet($db, $userId) {
    // First, try to get existing wallet
    $stmt = $db->prepare("SELECT id, balance FROM wallets WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($wallet = $result->fetch_assoc()) {
        return [
            'id' => (int)$wallet['id'], 
            'balance' => (float)$wallet['balance']
        ];
    }
    
    // Wallet doesn't exist - CREATE ONE
    $stmt = $db->prepare("INSERT INTO wallets (user_id, balance, created_at) VALUES (?, 0, NOW())");
    $stmt->bind_param("i", $userId);
    if (!$stmt->execute()) {
        throw new Exception('Failed to create wallet: ' . $stmt->error);
    }
    
    // Get the newly created wallet ID
    $walletId = $stmt->insert_id;
    
    // Verify it was created
    $stmt = $db->prepare("SELECT id, balance FROM wallets WHERE id = ?");
    $stmt->bind_param("i", $walletId);
    $stmt->execute();
    $wallet = $stmt->get_result()->fetch_assoc();
    
    if (!$wallet) {
        throw new Exception('Wallet created but could not retrieve it.');
    }
        return [
        'id' => (int)$wallet['id'], 
        'balance' => (float)$wallet['balance']
    ];
}

try {
    $wallet = getUserWallet($db, $userId);
    $walletId = $wallet['id'];
    $walletBalance = $wallet['balance'];
} catch (Exception $e) {
    Session::setError('Wallet error: ' . $e->getMessage());
    $walletId = null;
    $walletBalance = 0;
}

// DEBUG: Log wallet info (check your error_log)
error_log("DEBUG buy_data.php - UserID: $userId, WalletID: " . var_export($walletId, true) . ", Balance: $walletBalance");

// Get user phone for form pre-fill
$stmt = $db->prepare("SELECT phone FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$userData = $stmt->get_result()->fetch_assoc();
$userPhone = $userData['phone'] ?? '';

// ────────────────────────────────────────────────
// 2. LOAD NETWORKS
// ────────────────────────────────────────────────
$networks = [];
$networkDefaults = [
    'mtn'     => ['name' => 'MTN Data', 'icon' => 'fa-signal', 'color' => '#ffc107'],
    'glo'     => ['name' => 'Glo Data', 'icon' => 'fa-globe', 'color' => '#28a745'],
    'airtel'  => ['name' => 'Airtel Data', 'icon' => 'fa-wifi', 'color' => '#dc3545'],
    '9mobile' => ['name' => '9mobile Data', 'icon' => 'fa-mobile-alt', 'color' => '#17a2b8'],
];

$query = "SELECT DISTINCT network FROM service_variations 
          WHERE network IS NOT NULL AND network != '' AND is_active = 1 
          ORDER BY FIELD(network, 'mtn','glo','airtel','9mobile'), network";
$result = $db->query($query);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $code = strtolower($row['network']);
        if (isset($networkDefaults[$code])) {
            $networks[$code] = $networkDefaults[$code];
        }
    }
}if (empty($networks)) {
    $networks = $networkDefaults;
}

$selectedNetwork = $_GET['network'] ?? array_key_first($networks) ?: 'mtn';

// ────────────────────────────────────────────────
// 3. LOAD DATA PLANS
// ────────────────────────────────────────────────
$plans = [];
$stmt = $db->prepare("SELECT * FROM service_variations 
                      WHERE network = ? AND is_active = 1 
                      ORDER BY amount ASC");
$stmt->bind_param("s", $selectedNetwork);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $plans[] = [
        'id' => (int)$row['id'],
        'size' => $row['size'] ?: $row['name'],
        'price' => (float)($row['retail_price'] ?: $row['amount']),
        'validity' => $row['validity'] ?: '30 days',
        'variation_code' => $row['variation_code'] ?? '',
        'name' => $row['name'] ?: ($row['size'] . ' Bundle')
    ];
}

$selectedPlan = $_GET['plan'] ?? '';

// ────────────────────────────────────────────────
// 4. FORM PROCESSING (With wallet_id Validation)
// ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Session::verifyCSRF($_POST['csrf_token'] ?? '')) {
        Session::setError('Invalid CSRF token.');
    } else {
        $network = strtolower(trim($_POST['network'] ?? ''));
        $plan_id = intval($_POST['plan_id'] ?? 0);
        $phone = trim($_POST['phone'] ?? '');
        
        $errors = [];
        
        // ✅ CRITICAL: Validate wallet_id BEFORE proceeding
        if (!$walletId || !is_numeric($walletId) || $walletId <= 0) {
            error_log("CRITICAL ERROR: walletId is invalid - " . var_export($walletId, true));
            Session::setError('Wallet configuration error. Please contact support. (Code: W001)');
            $errors[] = 'Wallet error';
        }
                if (!isset($networks[$network])) {
            $errors[] = 'Invalid network.';
        }
        
        $stmt = $db->prepare("SELECT * FROM service_variations 
                              WHERE id = ? AND network = ? AND is_active = 1");
        $stmt->bind_param("is", $plan_id, $network);
        $stmt->execute();
        $plan = $stmt->get_result()->fetch_assoc();
        
        if (!$plan) {
            $errors[] = 'Invalid data plan.';
        } else {
            $amount = (float)($plan['retail_price'] ?: $plan['amount']);
            $plan_name = $plan['name'] ?: ($plan['size'] . ' - ' . ($plan['validity'] ?: '30 days'));
        }
        
        if (!preg_match('/^0[789][01]\d{8}$/', $phone)) {
            $errors[] = 'Invalid phone number.';
        }
        
        if ($walletBalance < $amount) {
            $errors[] = 'Insufficient balance. Current: ₦' . number_format($walletBalance, 2);
        }
        
        if (empty($errors)) {
            $reference = generate_reference('DATA');
            
            // DEBUG: Log before transaction
            error_log("DEBUG: Before Transaction - UserID: $userId, WalletID: $walletId, Amount: $amount, Reference: $reference");
            
            $db->begin_transaction();
            
            try {
                // Double-check wallet exists and lock it
                $stmt = $db->prepare("SELECT id, balance FROM wallets WHERE id = ? FOR UPDATE");
                $stmt->bind_param("i", $walletId);
                $stmt->execute();
                $walletCheck = $stmt->get_result()->fetch_assoc();
                
                if (!$walletCheck) {
                    throw new Exception('Wallet not found in database. ID: ' . $walletId);
                }
                
                $currentBalance = (float)$walletCheck['balance'];
                
                if ($currentBalance < $amount) {
                    throw new Exception('Insufficient funds. Balance: ₦' . $currentBalance);
                }
                                // Deduct
                $stmt = $db->prepare("UPDATE wallets SET balance = balance - ? WHERE id = ?");
                $stmt->bind_param("di", $amount, $walletId);
                $stmt->execute();
                
                $balanceAfter = $currentBalance - $amount;
                
                // ✅ INSERT with EXPLICIT wallet_id validation
                $status = 'pending';
                $total = $amount;
                $variation_code = $plan['variation_code'] ?? '';
                
                // Verify wallet_id one more time before INSERT
                if (!is_numeric($walletId) || $walletId <= 0) {
                    throw new Exception('Invalid wallet_id: ' . var_export($walletId, true));
                }
                
                $stmt = $db->prepare("INSERT INTO transactions (
                    user_id, wallet_id, transaction_id, type, amount, total, 
                    network, phone_number, data_plan, variation_code, status, created_at
                ) VALUES (?, ?, ?, 'data', ?, ?, ?, ?, ?, ?, ?, NOW())");
                
                // DEBUG: Log the values being bound
                error_log("DEBUG INSERT: user_id=$userId, wallet_id=$walletId, reference=$reference, amount=$amount");
                
                $bindResult = $stmt->bind_param(
                    "iisddsssss",
                    $userId,
                    $walletId,
                    $reference,
                    $amount,
                    $total,
                    $network,
                    $phone,
                    $plan_name,
                    $variation_code,
                    $status
                );
                
                if (!$bindResult) {
                    throw new Exception('Failed to bind parameters: ' . $stmt->error);
                }
                
                $execResult = $stmt->execute();
                
                if (!$execResult) {
                    throw new Exception('Failed to insert transaction: ' . $stmt->error);
                }
                
                $transactionId = $stmt->insert_id;                
                if (!$transactionId) {
                    throw new Exception('Transaction ID not generated.');
                }
                
                // Wallet transaction log
                $stmt = $db->prepare("INSERT INTO wallet_transactions (
                    user_id, reference, type, amount, balance_before, balance_after, status
                ) VALUES (?, ?, 'debit', ?, ?, ?, 'completed')");
                $stmt->bind_param("isddd", $userId, $reference, $amount, $currentBalance, $balanceAfter);
                $stmt->execute();
                
                // API Call
                $apiResult = processVTUDataRequest($network, $phone, $plan, $reference);
                
                if ($apiResult['status'] === 'success') {
                    $stmt = $db->prepare("UPDATE transactions SET status = 'success', updated_at = NOW() WHERE id = ?");
                    $stmt->bind_param("i", $transactionId);
                    $stmt->execute();
                    
                    $message = "✅ " . $plan_name . " sent to " . $phone;
                    
                    $stmt = $db->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?, 'transaction', 'Success', ?)");
                    $stmt->bind_param("is", $userId, $message);
                    $stmt->execute();
                    
                    $db->commit();
                    
                    error_log("DEBUG: Transaction SUCCESS - ID: $transactionId");
                    
                    Session::setSuccess($message);
                    redirect("transaction_details.php?id=$transactionId");
                    
                } else {
                    // Refund
                    $stmt = $db->prepare("UPDATE wallets SET balance = balance + ? WHERE id = ?");
                    $stmt->bind_param("di", $amount, $walletId);
                    $stmt->execute();
                    
                    $stmt = $db->prepare("UPDATE transactions SET status = 'failed', updated_at = NOW() WHERE id = ?");
                    $stmt->bind_param("i", $transactionId);
                    $stmt->execute();
                    
                    $db->commit();
                    
                    throw new Exception($apiResult['message'] ?? 'Delivery failed. Funds refunded.');
                }
                
            } catch (Exception $e) {
                $db->rollback();                error_log("DEBUG: Transaction FAILED - " . $e->getMessage());
                Session::setError("Transaction failed: " . htmlspecialchars($e->getMessage()));
            }
        } else {
            foreach ($errors as $err) {
                Session::setError($err);
            }
        }
    }
}

// Recent purchases
$stmt = $db->prepare("SELECT * FROM transactions WHERE user_id = ? AND type = 'data' ORDER BY created_at DESC LIMIT 5");
$stmt->bind_param("i", $userId);
$stmt->execute();
$recent = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Buy Data Bundle';
include '../partials/user_header.php';
?> 

<style>
/* ===== Buy Data Bundle Styles ===== */
.content-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem; }
.content-header h2 { display: flex; align-items: center; gap: 0.75rem; color: var(--dark); font-size: clamp(1.25rem, 4vw, 1.75rem); margin: 0; }
.content-header h2 i { color: var(--primary); background: rgba(99, 102, 241, 0.1); padding: 0.75rem; border-radius: 50%; }
.balance-badge { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; padding: 0.75rem 1.5rem; border-radius: 2rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.75rem; box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3); }

/* Network Tabs */
.network-tabs { display: flex; gap: 0.75rem; margin-bottom: 2rem; flex-wrap: wrap; }
.network-tabs .btn { flex: 1; min-width: 120px; padding: 1rem; border-radius: 1rem; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 0.5rem; transition: all 0.3s; border: 2px solid transparent; }
.network-tabs .btn:hover { transform: translateY(-2px); }
.network-tabs .btn.btn-primary { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; border-color: var(--primary); }
.network-tabs .btn.btn-light { background: white; color: var(--dark); border-color: var(--gray-light); }

/* Plans Grid */
.plans-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; }
@media (max-width: 992px) { .plans-grid { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 768px) { .plans-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 480px) { .plans-grid { grid-template-columns: 1fr; } }

.plan-card input[type="radio"] { display: none; }
.plan-option { padding: 1.5rem 1rem; border: 2px solid var(--gray-light); border-radius: 1rem; text-align: center; transition: all 0.3s; background: white; cursor: pointer; }
.plan-option:hover { transform: translateY(-3px); box-shadow: 0 6px 12px rgba(0,0,0,0.1); border-color: var(--primary); }
.plan-option.selected { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; border-color: var(--primary); box-shadow: 0 8px 16px rgba(99, 102, 241, 0.2); }
.plan-option h4 { font-size: 1.25rem; font-weight: 700; margin-bottom: 0.5rem; }
.plan-option p { font-size: 0.875rem; margin-bottom: 0.5rem; opacity: 0.9; }
.plan-option .price { font-weight: 700; font-size: 1.25rem; }

/* Form Elements */
.form-group { margin-bottom: 2rem; }
.form-group label { display: block; margin-bottom: 0.75rem; font-weight: 600; color: var(--dark); }.input-group { display: flex; border: 2px solid var(--gray-light); border-radius: 1rem; overflow: hidden; background: white; transition: border-color 0.3s; }
.input-group:focus-within { border-color: var(--primary); }
.input-group-text { padding: 1rem 1.25rem; background: var(--light); color: var(--primary); font-weight: 600; border-right: 2px solid var(--gray-light); }
.form-control { flex: 1; padding: 1rem; border: none; font-size: 1rem; outline: none; width: 100%; }

/* Buttons */
.btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 1rem 1.5rem; font-weight: 600; border-radius: 1rem; cursor: pointer; border: none; transition: all 0.3s; text-decoration: none; }
.btn-block { width: 100%; }
.btn-primary { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3); }
.btn-primary:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(99, 102, 241, 0.4); }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-light { background: white; color: var(--dark); border: 1px solid var(--gray-light); }

/* Transactions */
.transaction-list { display: flex; flex-direction: column; gap: 0.75rem; }
.transaction-item { display: flex; align-items: center; justify-content: space-between; padding: 1rem; background: var(--light); border-radius: 1rem; text-decoration: none; color: inherit; transition: all 0.3s; }
.transaction-item:hover { background: rgba(99, 102, 241, 0.05); transform: translateX(5px); }
.transaction-left { display: flex; align-items: center; gap: 1rem; }
.transaction-icon { width: 50px; height: 50px; background: linear-gradient(135deg, var(--primary), var(--secondary)); border-radius: 1rem; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.25rem; }
.transaction-details h4 { font-weight: 600; margin-bottom: 0.25rem; font-size: 0.95rem; }
.transaction-details p { color: var(--gray); font-size: 0.875rem; display: flex; align-items: center; gap: 0.5rem; margin: 0; }
.transaction-amount { font-weight: 700; font-size: 1.25rem; color: var(--danger); }
.badge { padding: 0.35rem 1rem; border-radius: 2rem; font-size: 0.75rem; font-weight: 600; }
.badge-success { background: #d1fae5; color: #065f46; }
.badge-pending { background: #fef3c7; color: #92400e; }
.badge-failed { background: #fee2e2; color: #991b1b; }

/* Alerts */
.alert { padding: 1rem; border-radius: 1rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; }
.alert-error { background: #fee2e2; color: #991b1b; }
.alert-success { background: #d1fae5; color: #065f46; }

/* Card */
.card { background: white; border-radius: 1.5rem; overflow: hidden; margin-bottom: 1.5rem; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
.card-header { background: linear-gradient(135deg, #f8f9fa, #ffffff); padding: 1.5rem; border-bottom: 2px solid var(--gray-light); }
.card-header h3 { display: flex; align-items: center; gap: 0.5rem; margin: 0; color: var(--dark); }

/* Animations */
@keyframes slideIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
.card { animation: slideIn 0.5s ease forwards; }

/* Bottom Nav */
.bottom-nav { position: fixed; bottom: 0; left: 0; right: 0; background: white; border-top: 1px solid var(--gray-light); padding: 0.5rem; display: flex; justify-content: space-around; z-index: 100; box-shadow: 0 -4px 10px rgba(0,0,0,0.05); }
.bottom-nav-item { display: flex; flex-direction: column; align-items: center; color: var(--gray); text-decoration: none; padding: 0.5rem; border-radius: 1rem; transition: all 0.3s; }
.bottom-nav-item.active, .bottom-nav-item:hover { color: var(--primary); background: rgba(99, 102, 241, 0.1); }
.bottom-nav-item i { font-size: 1.25rem; margin-bottom: 0.25rem; }
.bottom-nav-item span { font-size: 0.7rem; font-weight: 500; }

.main-content { padding-bottom: 80px; }
</style>
<!-- Main Content -->
<div class="main-content">
    <div class="container">
        <!-- Page Header -->
        <div class="content-header">
            <h2><i class="fas fa-wifi"></i> Buy Data Bundle</h2>
            <div class="balance-badge">
                <i class="fas fa-wallet"></i>
                <span><?php echo format_money($walletBalance); ?></span>
            </div>
        </div>

        <!-- Alerts -->
        <?php if ($error = Session::getError()): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>
        
        <?php if ($success = Session::getSuccess()): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>

        <!-- Main Purchase Card -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-shopping-cart"></i> Purchase Data Bundle</h3>
            </div>
            
            <div style="padding: 2rem;">
                <!-- Network Tabs -->
                <div class="network-tabs">
                    <?php foreach ($networks as $code => $net): ?>
                        <button class="btn <?php echo $selectedNetwork == $code ? 'btn-primary' : 'btn-light'; ?>" 
                                onclick="switchNetwork('<?php echo $code; ?>')">
                            <i class="fas <?php echo $net['icon']; ?>"></i>
                            <span><?php echo $net['name']; ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
                
                <form method="POST" id="dataForm">
                    <input type="hidden" name="csrf_token" value="<?php echo Session::generateCSRF(); ?>">
                    <input type="hidden" name="network" id="selectedNetwork" value="<?php echo $selectedNetwork; ?>">
                    <input type="hidden" name="plan_id" id="selectedPlanId" value="<?php echo $selectedPlan; ?>">                    
                    <!-- Data Plans Grid -->
                    <div class="form-group">
                        <label><i class="fas fa-layer-group" style="color: var(--primary); margin-right: 0.5rem;"></i> Select Data Plan</label>
                        <div class="plans-grid" id="plansGrid">
                            <?php if (empty($plans)): ?>
                                <p style="grid-column: 1/-1; text-align: center; color: var(--gray);">No data plans available for <?php echo ucfirst($selectedNetwork); ?>.</p>
                            <?php else: ?>
                                <?php foreach ($plans as $plan): ?>
                                    <label class="plan-card">
                                        <input type="radio" name="plan_radio" value="<?php echo $plan['id']; ?>" 
                                               <?php echo ($selectedPlan == $plan['id']) ? 'checked' : ''; ?>
                                               style="display: none;"
                                               onchange="selectPlan(<?php echo $plan['id']; ?>)">
                                        <div class="plan-option <?php echo ($selectedPlan == $plan['id']) ? 'selected' : ''; ?>" 
                                             data-plan-id="<?php echo $plan['id']; ?>">
                                            <h4><?php echo htmlspecialchars($plan['size']); ?></h4>
                                            <p><i class="far fa-clock"></i> <?php echo htmlspecialchars($plan['validity']); ?></p>
                                            <p class="price"><?php echo format_money($plan['price']); ?></p>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Phone Number -->
                    <div class="form-group">
                        <label for="phone"><i class="fas fa-phone-alt" style="color: var(--primary); margin-right: 0.5rem;"></i> Phone Number</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-phone"></i></span>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   pattern="[0-9]{11}" required placeholder="08012345678"
                                   value="<?php echo htmlspecialchars($_POST['phone'] ?? $userPhone); ?>"
                                   maxlength="11" inputmode="numeric">
                        </div>
                        <small style="display: block; margin-top: 0.5rem; color: var(--gray);">
                            <i class="fas fa-info-circle"></i> Enter the 11-digit phone number to credit
                        </small>
                    </div>
                    
                    <!-- Submit Button -->
                    <button type="submit" class="btn btn-primary btn-block" id="submitBtn" 
                            <?php echo $walletBalance < 50 ? 'disabled' : ''; ?>>
                        <i class="fas fa-shopping-cart"></i> Purchase Data Bundle
                    </button>
                    
                    <?php if ($walletBalance < 50): ?>
                        <p style="text-align: center; margin-top: 1.5rem; padding: 1rem; background: #fee2e2; color: #991b1b; border-radius: 1rem;">
                            <i class="fas fa-exclamation-triangle"></i>                            Insufficient balance. <a href="wallet.php" style="color: var(--primary); font-weight: 600;">Fund your wallet</a>
                        </p>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Recent Purchases -->
        <?php if (!empty($recent)): ?>
            <div class="card">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <h3><i class="fas fa-history"></i> Recent Data Purchases</h3>
                    <a href="transactions.php" class="btn btn-light" style="padding: 0.5rem 1rem; font-size: 0.875rem;">
                        <i class="fas fa-arrow-right"></i> View All
                    </a>
                </div>
                
                <div style="padding: 1.5rem;">
                    <div class="transaction-list">
                        <?php foreach ($recent as $r): ?>
                            <a href="transaction_details.php?id=<?php echo $r['id']; ?>" class="transaction-item">
                                <div class="transaction-left">
                                    <div class="transaction-icon"><i class="fas fa-wifi"></i></div>
                                    <div class="transaction-details">
                                        <h4><?php echo strtoupper($r['network']); ?> Data - <?php echo htmlspecialchars($r['data_plan']); ?></h4>
                                        <p><i class="fas fa-phone"></i> <?php echo $r['phone_number']; ?> • <i class="far fa-clock"></i> <?php echo time_ago($r['created_at']); ?></p>
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <span class="transaction-amount">-<?php echo format_money($r['amount']); ?></span>
                                    <span class="badge badge-<?php echo $r['status']; ?>"><?php echo ucfirst($r['status']); ?></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Bottom Navigation -->
<nav class="bottom-nav">
    <a href="dashboard.php" class="bottom-nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
        <i class="fas fa-home"></i><span>Home</span>
    </a>
    <a href="wallet.php" class="bottom-nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'wallet.php' ? 'active' : ''; ?>">
        <i class="fas fa-wallet"></i><span>Wallet</span>
    </a>
    <a href="services.php" class="bottom-nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'services.php' ? 'active' : ''; ?>">        <i class="fas fa-th-large"></i><span>Services</span>
    </a>
    <a href="transactions.php" class="bottom-nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'transactions.php' ? 'active' : ''; ?>">
        <i class="fas fa-history"></i><span>History</span>
    </a>
    <a href="profile.php" class="bottom-nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
        <i class="fas fa-user"></i><span>Profile</span>
    </a>
</nav>

<script>
function switchNetwork(network) {
    window.location.href = 'buy_data.php?network=' + network;
}

function selectPlan(planId) {
    document.getElementById('selectedPlanId').value = planId;
    
    document.querySelectorAll('.plan-option').forEach(opt => {
        opt.classList.remove('selected');
        if (opt.dataset.planId == planId) {
            opt.classList.add('selected');
        }
    });
}

// Phone number formatting
document.getElementById('phone').addEventListener('input', function(e) {
    this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11);
});

// Live balance check for submit button
document.getElementById('dataForm').addEventListener('input', function() {
    const planId = document.getElementById('selectedPlanId').value;
    const planCard = document.querySelector('.plan-option[data-plan-id="' + planId + '"]');
    if (planCard) {
        const priceText = planCard.querySelector('.price').textContent.replace(/[^0-9.]/g, '');
        const price = parseFloat(priceText) || 0;
        const balance = <?php echo $walletBalance; ?>;
        document.getElementById('submitBtn').disabled = price > balance;
    }
});

// Form submission
document.getElementById('dataForm').addEventListener('submit', function(e) {
    const planId = document.getElementById('selectedPlanId').value;
    const phone = document.getElementById('phone').value;
    
    if (!planId) {
        e.preventDefault();        alert('Please select a data plan');
        return false;
    }
    
    if (!/^[0-9]{11}$/.test(phone)) {
        e.preventDefault();
        alert('Please enter a valid 11-digit phone number');
        return false;
    }
    
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
});

// Auto-select first plan on load
document.addEventListener('DOMContentLoaded', function() {
    const selectedPlan = '<?php echo $selectedPlan; ?>';
    if (!selectedPlan) {
        const firstPlan = document.querySelector('.plan-option');
        if (firstPlan) {
            firstPlan.closest('input[type="radio"]').checked = true;
            selectPlan(firstPlan.dataset.planId);
        }
    }
});
</script>

<?php
/**
 * VTU Data API Integration Function
 * Replace the simulation with your actual provider API call
 * Providers: VTpass, ClubKonnect, Reloadly, SurePay, etc.
 */
function processVTUDataRequest($network, $phone, $plan, $reference) {
    /* 
    // ═══════════════════════════════════════════════════
    // EXAMPLE: VTpass API Integration (Uncomment & configure)
    // ═══════════════════════════════════════════════════
    
    $apiKey = 'YOUR_VTPASS_API_KEY';
    $publicKey = 'YOUR_VTPASS_PUBLIC_KEY';
    
    $url = "https://vtpass.com/api/pay";
    
    $postData = [
        'serviceID' => $network . '-data',  // e.g., 'mtn-data', 'airtel-data'
        'billersCode' => $phone,
        'variation_code' => $plan['variation_code'],
        'amount' => $plan['price'],        'phone' => $phone,
        'request_id' => $reference
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($postData),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . $apiKey,
            'publickey: ' . $publicKey,
            'Content-Type: application/x-www-form-urlencoded'
        ],
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        return ['status' => 'failed', 'message' => 'Connection error: ' . $curlError];
    }
    
    $result = json_decode($response, true);
    
    // VTpass success code is '000'
    if ($httpCode == 200 && isset($result['code']) && $result['code'] == '000') {
        return ['status' => 'success', 'message' => 'Data delivered successfully'];
    }
    
    return [
        'status' => 'failed', 
        'message' => $result['response_description'] ?? $result['error'] ?? 'API request failed'
    ];
    
    // ═══════════════════════════════════════════════════
    // END VTpass Example
    // ═══════════════════════════════════════════════════
    */
    
    // 🧪 SIMULATION MODE (Remove this block in production)
    // Simulates 90% success rate for testing
    sleep(1); // Simulate API delay
    
    if (rand(1, 10) <= 9) {
        return ['status' => 'success', 'message' => 'Data bundle delivered (simulated)'];    } else {
        return ['status' => 'failed', 'message' => 'Provider timeout (simulated)'];
    }
}
?>

</body>
</html>