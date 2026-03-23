<?php
// user/wallet.php - Updated to use 'transactions' table instead of 'wallet_transactions'

require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

Auth::requireLogin();

$db = db();
$userId = Session::userId();

/**
 * ==============================
 * ENSURE WALLET EXISTS (Reusable Logic)
 * ==============================
 */
function getOrCreateWallet($db, $userId) {
    $stmt = $db->prepare("SELECT id FROM wallets WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $wallet = $stmt->get_result()->fetch_assoc();

    if ($wallet) {
        return (int)$wallet['id'];
    }

    $stmt = $db->prepare("
        INSERT INTO wallets (user_id, balance, created_at, updated_at)
        VALUES (?, 0, NOW(), NOW())
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();

    return (int)$stmt->insert_id;
}

$wallet_id = getOrCreateWallet($db, $userId);

/**
 * ==============================
 * USER + WALLET DATA
 * ==============================
 */
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$walletBalance = getUserBalance($userId);

/**
 * ==============================
 * TRANSACTION HISTORY - Now using 'transactions' table
 * ==============================
 */
$stmt = $db->prepare("
    SELECT * FROM transactions 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/**
 * ==============================
 * HANDLE MANUAL FUNDING
 * ==============================
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_manual'])) {

    if (!isset($_POST['csrf_token']) || !Session::verifyCSRF($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }

    $amount = (float)($_POST['amount'] ?? 0);
    $bank_name = trim($_POST['bank_name'] ?? '');
    $account_name = trim($_POST['account_name'] ?? '');
    $account_number = trim($_POST['account_number'] ?? '');
    $reference = 'MANUAL_' . time() . '_' . rand(1000, 9999);

    $errors = [];

    if ($amount < 100) $errors[] = 'Minimum amount is ₦100';
    if (!$bank_name) $errors[] = 'Bank name required';
    if (!$account_name) $errors[] = 'Account name required';
    if (!$account_number) $errors[] = 'Account number required';

    /**
     * ==============================
     * FILE UPLOAD
     * ==============================
     */
    $slip_path = '';

    if (isset($_FILES['deposit_slip']) && $_FILES['deposit_slip']['error'] === 0) {

        $upload_dir = '../uploads/slips/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        $ext = strtolower(pathinfo($_FILES['deposit_slip']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];

        if (!in_array($ext, $allowed)) {
            $errors[] = 'Invalid file type';
        } else {
            $filename = $reference . '.' . $ext;
            $filepath = $upload_dir . $filename;

            if (move_uploaded_file($_FILES['deposit_slip']['tmp_name'], $filepath)) {
                $slip_path = str_replace('../', '/', $filepath);
            } else {
                $errors[] = 'Upload failed';
            }
        }
    } else {
        $errors[] = 'Deposit slip required';
    }

    /**
     * ==============================
     * INSERT FUNDING REQUEST
     * ==============================
     */
    if (empty($errors)) {
        try {

            if (!$wallet_id || $wallet_id <= 0) {
                throw new Exception("Invalid wallet_id");
            }

            $stmt = $db->prepare("
                INSERT INTO funding_requests (
                    user_id, wallet_id, reference, amount, payment_method,
                    bank_name, account_name, account_number, deposit_slip,
                    status, ip_address, user_agent, created_at
                ) VALUES (?, ?, ?, ?, 'manual', ?, ?, ?, ?, 'pending', ?, ?, NOW())
            ");

            $ip = getUserIP();
            $agent = getUserAgent();

            $stmt->bind_param(
                "iisissssss",
                $userId,
                $wallet_id,
                $reference,
                $amount,
                $bank_name,
                $account_name,
                $account_number,
                $slip_path,
                $ip,
                $agent
            );

            $stmt->execute();

            Session::setSuccess("Funding request submitted! Ref: $reference");

        } catch (Exception $e) {
            error_log("Funding Error: " . $e->getMessage());
            Session::setError("Failed to submit request");
        }

    } else {
        foreach ($errors as $err) {
            Session::setError($err);
        }
    }

    redirect('user/wallet.php');
}

$pageTitle = 'My Wallet';
include '../partials/user_header.php';
?>

<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

.container { max-width: 1200px; margin: 0 auto; padding: 0 15px; }

.welcome-card {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    padding: 2rem;
    border-radius: 1rem;
    margin-bottom: 1.5rem;
}

.welcome-stats {
    display: flex;
    gap: 2rem;
    margin-top: 1rem;
}

.welcome-stat-value { font-size: 1.5rem; font-weight: bold; }
.welcome-stat-label { font-size: 0.875rem; opacity: 0.8; }

.card {
    background: white;
    border-radius: 1rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.card-header {
    border-bottom: 2px solid #e5e7eb;
    padding-bottom: 1rem;
    margin-bottom: 1rem;
}

.card-header h3 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1.25rem;
}

.form-group { margin-bottom: 1rem; }
.form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; }

.form-control {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    font-size: 1rem;
}

.form-control:focus { outline: none; border-color: #6366f1; }

.btn {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 0.5rem;
    font-size: 1rem;
    cursor: pointer;
    font-weight: 500;
}

.btn-primary {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    width: 100%;
}

.btn-primary:hover { opacity: 0.9; transform: translateY(-1px); }

.alert {
    padding: 1rem;
    border-radius: 0.5rem;
    margin-bottom: 1rem;
}

.alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
.alert-error { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }

.transaction-item {
    display: flex;
    justify-content: space-between;
    padding: 1rem;
    border-bottom: 1px solid #e5e7eb;
}

.transaction-amount.positive { color: #10b981; font-weight: bold; }
.transaction-amount.negative { color: #ef4444; font-weight: bold; }

.badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.75rem;
    font-weight: 500;
}

.badge-pending { background: #fef3c7; color: #92400e; }
.badge-success { background: #d1fae5; color: #065f46; }

.quick-amounts {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}

.quick-amount-btn {
    padding: 0.5rem 1rem;
    background: #f3f4f6;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    cursor: pointer;
}

.quick-amount-btn:hover { background: #e5e7eb; }

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

@media (max-width: 640px) { .form-row { grid-template-columns: 1fr; } }

.file-upload {
    border: 2px dashed #e5e7eb;
    padding: 1.5rem;
    text-align: center;
    border-radius: 0.5rem;
    cursor: pointer;
}

.file-upload:hover { border-color: #6366f1; background: rgba(99,102,241,0.05); }

.funding-options {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.funding-option {
    flex: 1;
    padding: 1rem;
    text-align: center;
    background: white;
    border-radius: 0.75rem;
    cursor: pointer;
    border: 2px solid #e5e7eb;
}

.funding-option.active {
    border-color: #6366f1;
    background: linear-gradient(135deg, rgba(99,102,241,0.05), rgba(139,92,246,0.05));
}

.funding-option i { font-size: 1.5rem; color: #6366f1; margin-bottom: 0.5rem; }
.funding-option h4 { margin-bottom: 0.25rem; }

.payment-panel { display: none; }
.payment-panel.active { display: block; }

.bank-details {
    background: linear-gradient(135deg, #1e3a8a, #1e40af);
    color: white;
    padding: 1.5rem;
    border-radius: 0.75rem;
    margin-bottom: 1.5rem;
}

.bank-details p {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.5rem;
    flex-wrap: wrap;
}

.copy-btn {
    background: rgba(255,255,255,0.2);
    border: none;
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 0.5rem;
    cursor: pointer;
}

.bottom-nav {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: white;
    display: flex;
    justify-content: space-around;
    padding: 0.75rem;
    border-top: 1px solid #e5e7eb;
}

.bottom-nav-item {
    text-align: center;
    color: #6b7280;
    text-decoration: none;
}

.bottom-nav-item.active { color: #6366f1; }

.main-content { padding-bottom: 80px; }
</style>

<div class="main-content">
    <div class="container">
        <!-- Alerts -->
        <?php if ($error = Session::getError()): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success = Session::getSuccess()): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Wallet Balance -->
        <div class="welcome-card">
            <h2>Wallet Balance</h2>
            <div class="welcome-stats">
                <div>
                    <div class="welcome-stat-value"><?php echo format_money($walletBalance); ?></div>
                    <div class="welcome-stat-label">Current Balance</div>
                </div>
                <div>
                    <div class="welcome-stat-value">₦0</div>
                    <div class="welcome-stat-label">Total Funded</div>
                </div>
                <div>
                    <div class="welcome-stat-value">₦0</div>
                    <div class="welcome-stat-label">Total Spent</div>
                </div>
            </div>
        </div>

        <!-- Funding Options -->
        <div class="funding-options">
            <div class="funding-option active" onclick="selectOption('card')" id="cardOption">
                <i class="fas fa-credit-card"></i>
                <h4>Card Payment</h4>
                <small>Instant via Paystack</small>
            </div>
            <div class="funding-option" onclick="selectOption('bank')" id="bankOption">
                <i class="fas fa-university"></i>
                <h4>Bank Transfer</h4>
                <small>Manual approval</small>
            </div>
        </div>

        <!-- Card Payment Panel -->
        <div id="cardPanel" class="payment-panel active">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-credit-card"></i> Card Payment</h3>
                </div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo Session::generateCSRF(); ?>">
                    <div class="form-group">
                        <label>Amount (₦)</label>
                        <input type="number" name="amount" class="form-control" min="100" required>
                    </div>
                    <div class="quick-amounts">
                        <button type="button" class="quick-amount-btn" onclick="setAmount(this, 1000)">₦1,000</button>
                        <button type="button" class="quick-amount-btn" onclick="setAmount(this, 5000)">₦5,000</button>
                        <button type="button" class="quick-amount-btn" onclick="setAmount(this, 10000)">₦10,000</button>
                    </div>
                    <button type="submit" name="fund_wallet" class="btn btn-primary">Pay with Card</button>
                </form>
            </div>
        </div>

        <!-- Bank Transfer Panel -->
        <div id="bankPanel" class="payment-panel">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-university"></i> Bank Transfer</h3>
                </div>
                
                <!-- Bank Details -->
                <div class="bank-details">
                    <h4>Our Bank Account</h4>
                    <p><span>Bank Name:</span> <strong>Example Bank</strong> <button class="copy-btn" onclick="copyText('Example Bank')">Copy</button></p>
                    <p><span>Account Name:</span> <strong><?php echo SITE_NAME; ?></strong> <button class="copy-btn" onclick="copyText('<?php echo SITE_NAME; ?>')">Copy</button></p>
                    <p><span>Account Number:</span> <strong>1234567890</strong> <button class="copy-btn" onclick="copyText('1234567890')">Copy</button></p>
                </div>
                
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo Session::generateCSRF(); ?>">
                    <input type="hidden" name="submit_manual" value="1">
                    
                    <div class="form-group">
                        <label>Amount (₦)</label>
                        <input type="number" name="amount" class="form-control" min="100" required id="manualAmount">
                    </div>
                    
                    <div class="quick-amounts">
                        <button type="button" class="quick-amount-btn" onclick="setAmount(this, 1000, 'manualAmount')">₦1,000</button>
                        <button type="button" class="quick-amount-btn" onclick="setAmount(this, 5000, 'manualAmount')">₦5,000</button>
                        <button type="button" class="quick-amount-btn" onclick="setAmount(this, 10000, 'manualAmount')">₦10,000</button>
                    </div>
                    
                    <div class="form-group">
                        <label>Your Bank Name</label>
                        <input type="text" name="bank_name" class="form-control" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Account Name</label>
                            <input type="text" name="account_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Account Number</label>
                            <input type="text" name="account_number" class="form-control" required maxlength="10">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Deposit Slip/Proof of Payment</label>
                        <div class="file-upload" onclick="document.getElementById('deposit_slip').click()">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p>Click to upload deposit slip</p>
                            <input type="file" id="deposit_slip" name="deposit_slip" accept="image/*,.pdf" style="display:none">
                        </div>
                        <div id="fileName" style="margin-top: 0.5rem; font-size: 0.875rem; color: #6366f1;"></div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Submit Request</button>
                </form>
            </div>
        </div>

        <!-- Recent Transactions - Updated to use 'transactions' table -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> Recent Transactions</h3>
            </div>
            <?php if (empty($history)): ?>
                <div style="text-align:center; padding:3rem; color:#6b7280;">
                    <i class="fas fa-wallet" style="font-size:3rem; opacity:0.4; margin-bottom:1rem;"></i><br>
                    <strong>No transactions yet</strong><br>
                    <small>Fund your wallet or make a purchase to see activity here</small>
                </div>
            <?php else: ?>
                <?php foreach ($history as $h): ?>
                    <div class="transaction-item">
                        <div>
                            <strong>
                                <?php 
                                // Adjust display based on transaction type
                                $displayType = ucfirst($h['type'] ?? 'Unknown');
                                if (in_array($h['type'] ?? '', ['wallet_funding', 'credit'])) {
                                    echo 'Credited';
                                } elseif (in_array($h['type'] ?? '', ['airtime','data','electricity','cable','withdrawal'])) {
                                    echo 'Debited';
                                } else {
                                    echo $displayType;
                                }
                                ?>
                            </strong>
                            <br>
                            <small><?php echo date('M d, H:i', strtotime($h['created_at'])); ?></small>
                        </div>
                        <div class="transaction-amount <?php echo (in_array($h['type'] ?? '', ['wallet_funding','credit','referral_bonus','commission'])) ? 'positive' : 'negative'; ?>">
                            <?php 
                            $sign = (in_array($h['type'] ?? '', ['wallet_funding','credit','referral_bonus','commission'])) ? '+' : '-';
                            echo $sign . ' ' . format_money($h['amount'] ?? 0); 
                            ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Bottom Navigation -->
<nav class="bottom-nav">
    <a href="dashboard.php" class="bottom-nav-item"><i class="fas fa-home"></i><span>Home</span></a>
    <a href="user/wallet.php" class="bottom-nav-item active"><i class="fas fa-wallet"></i><span>Wallet</span></a>
    <a href="services.php" class="bottom-nav-item"><i class="fas fa-th-large"></i><span>Services</span></a>
    <a href="transactions.php" class="bottom-nav-item"><i class="fas fa-history"></i><span>History</span></a>
    <a href="profile.php" class="bottom-nav-item"><i class="fas fa-user"></i><span>Profile</span></a>
</nav>

<script>
function selectOption(option) {
    if (option === 'card') {
        document.getElementById('cardOption').classList.add('active');
        document.getElementById('bankOption').classList.remove('active');
        document.getElementById('cardPanel').classList.add('active');
        document.getElementById('bankPanel').classList.remove('active');
    } else {
        document.getElementById('bankOption').classList.add('active');
        document.getElementById('cardOption').classList.remove('active');
        document.getElementById('bankPanel').classList.add('active');
        document.getElementById('cardPanel').classList.remove('active');
    }
}

function setAmount(btn, amount, inputId = 'amount') {
    document.querySelector(`[name="\( {inputId}"], # \){inputId}`).value = amount;
}

function copyText(text) {
    navigator.clipboard.writeText(text);
    alert('Copied: ' + text);
}

document.getElementById('deposit_slip')?.addEventListener('change', function() {
    document.getElementById('fileName').textContent = this.files[0]?.name || '';
});

document.querySelectorAll('input[name="account_number"]').forEach(input => {
    input.addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);
    });
});
</script>

</body>
</html>