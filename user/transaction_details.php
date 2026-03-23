<?php
// user/transaction_details.php - Transaction Details Page
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

Auth::requireLogin();

$db = db();
$userId = Session::userId();

// Get transaction ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get transaction details
$stmt = $db->prepare("SELECT * FROM transactions WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $id, $userId);
$stmt->execute();
$transaction = $stmt->get_result()->fetch_assoc();

if (!$transaction) {
    Session::setError('Transaction not found');
    redirect('transactions.php');
}

$pageTitle = 'Transaction Details';
include '../partials/user_header.php';
?>

<!-- Main Content -->
<div class="main-content">
    <div class="container">
        <!-- Page Header -->
        <div class="content-header">
            <h2><i class="fas fa-file-invoice"></i> Transaction Details</h2>
            <a href="transactions.php" class="btn btn-light btn-small">
                <i class="fas fa-arrow-left"></i> Back to Transactions
            </a>
        </div>

        <!-- Transaction Details Card -->
        <div class="card">
            <div style="text-align: center; margin-bottom: 2rem;">
                <div style="width: 80px; height: 80px; background: linear-gradient(135deg, var(--primary), var(--secondary)); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                    <i class="fas <?php 
                        $icon = 'fa-exchange-alt';
                        if ($transaction['type'] == 'airtime') $icon = 'fa-phone-alt';
                        elseif ($transaction['type'] == 'data') $icon = 'fa-wifi';
                        elseif ($transaction['type'] == 'electricity') $icon = 'fa-bolt';
                        elseif ($transaction['type'] == 'cable') $icon = 'fa-tv';
                        elseif ($transaction['type'] == 'wallet_funding') $icon = 'fa-plus-circle';
                        echo $icon;
                    ?>" style="font-size: 2rem; color: white;"></i>
                </div>
                <h3><?php echo ucfirst($transaction['type']); ?> Transaction</h3>
                <span class="badge badge-<?php echo $transaction['status']; ?>" style="font-size: 1rem; padding: 0.5rem 1rem;">
                    <?php echo ucfirst($transaction['status']); ?>
                </span>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem;">
                <div>
                    <small style="color: var(--gray);">Transaction ID</small>
                    <p><strong><?php echo $transaction['transaction_id']; ?></strong></p>
                </div>
                
                <div>
                    <small style="color: var(--gray);">Reference</small>
                    <p><strong><?php echo $transaction['reference'] ?? 'N/A'; ?></strong></p>
                </div>
                
                <div>
                    <small style="color: var(--gray);">Amount</small>
                    <p><strong class="<?php echo $transaction['type'] == 'wallet_funding' ? 'positive' : 'negative'; ?>" style="font-size: 1.25rem;">
                        <?php echo $transaction['type'] == 'wallet_funding' ? '+' : '-'; ?>
                        <?php echo format_money($transaction['amount']); ?>
                    </strong></p>
                </div>
                
                <div>
                    <small style="color: var(--gray);">Fee</small>
                    <p><strong><?php echo format_money($transaction['fee'] ?? 0); ?></strong></p>
                </div>
                
                <div>
                    <small style="color: var(--gray);">Total</small>
                    <p><strong><?php echo format_money($transaction['total'] ?? $transaction['amount']); ?></strong></p>
                </div>
                
                <div>
                    <small style="color: var(--gray);">Date & Time</small>
                    <p><strong><?php echo date('M d, Y h:i A', strtotime($transaction['created_at'])); ?></strong></p>
                </div>
                
                <?php if ($transaction['network']): ?>
                    <div>
                        <small style="color: var(--gray);">Network</small>
                        <p><strong><?php echo strtoupper($transaction['network']); ?></strong></p>
                    </div>
                <?php endif; ?>
                
                <?php if ($transaction['phone_number']): ?>
                    <div>
                        <small style="color: var(--gray);">Phone Number</small>
                        <p><strong><?php echo $transaction['phone_number']; ?></strong></p>
                    </div>
                <?php endif; ?>
                
                <?php if ($transaction['meter_number']): ?>
                    <div>
                        <small style="color: var(--gray);">Meter Number</small>
                        <p><strong><?php echo $transaction['meter_number']; ?></strong></p>
                    </div>
                <?php endif; ?>
                
                <?php if ($transaction['smart_card']): ?>
                    <div>
                        <small style="color: var(--gray);">Smart Card Number</small>
                        <p><strong><?php echo $transaction['smart_card']; ?></strong></p>
                    </div>
                <?php endif; ?>
                
                <?php if ($transaction['data_plan']): ?>
                    <div>
                        <small style="color: var(--gray);">Data Plan</small>
                        <p><strong><?php echo $transaction['data_plan']; ?></strong></p>
                    </div>
                <?php endif; ?>
                
                <?php if ($transaction['token']): ?>
                    <div style="grid-column: span 2;">
                        <small style="color: var(--gray);">Token</small>
                        <p style="background: var(--light); padding: 1rem; border-radius: var(--radius); font-family: monospace;">
                            <strong><?php echo $transaction['token']; ?></strong>
                            <button class="btn btn-small btn-light" onclick="copyToken('<?php echo $transaction['token']; ?>')" style="margin-left: 1rem;">
                                <i class="fas fa-copy"></i> Copy
                            </button>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
            
            <hr style="margin: 2rem 0; border: none; border-top: 1px solid var(--gray-light);">
            
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button class="btn btn-light" onclick="window.print()">
                    <i class="fas fa-print"></i> Print Receipt
                </button>
                <a href="transactions.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
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
    <a href="services.php" class="bottom-nav-item">
        <i class="fas fa-th-large"></i>
        <span>Services</span>
    </a>
    <a href="transactions.php" class="bottom-nav-item active">
        <i class="fas fa-history"></i>
        <span>History</span>
    </a>
    <a href