<?php
// paystack_callback.php - Paystack Payment Callback Handler
require_once 'config.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
require_once 'includes/session.php';
require_once 'includes/paystack_config.php';
require_once 'includes/funding_functions.php';

$db = db();
$paystack = PaystackConfig::getInstance();
$fundingManager = new FundingManager($db);

// Get reference from query string
$reference = $_GET['reference'] ?? '';

if (empty($reference)) {
    die('Invalid transaction reference');
}

// Verify transaction
$verification = $paystack->verifyTransaction($reference);

if ($verification['success'] && $verification['status'] === 'success') {
    // Get transaction details
    $amount = $verification['amount'];
    $email = $verification['customer']['email'];
    $metadata = $verification['metadata'];
    
    // Find user by email or user_id from metadata
    $userId = null;
    if (isset($metadata['user_id'])) {
        $userId = $metadata['user_id'];
    } else {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $userId = $row['id'];
        }
    }
    
    if (!$userId) {
        die('User not found');
    }
    
    // Start transaction
    $db->begin_transaction();
    
    try {
        // Check if already processed
        $stmt = $db->prepare("SELECT id FROM funding_requests WHERE paystack_reference = ? AND status = 'approved'");
        $stmt->bind_param("s", $reference);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            // Already processed
            header('Location: user/wallet.php?success=payment_processed');
            exit;
        }
        
        // Create or update funding request
        $stmt = $db->prepare("
            SELECT id FROM funding_requests 
            WHERE paystack_reference = ? AND user_id = ?
        ");
        $stmt->bind_param("si", $reference, $userId);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        
        if ($existing) {
            $requestId = $existing['id'];
        } else {
            // Create new funding request
            $paymentMethod = 'paystack';
            $paymentDetails = "Paystack payment - Reference: $reference";
            
            $stmt = $db->prepare("
                INSERT INTO funding_requests (
                    user_id, reference, amount, payment_method, payment_details, 
                    payment_type, paystack_reference, status
                ) VALUES (?, ?, ?, ?, ?, 'paystack', ?, 'approved')
            ");
            $stmt->bind_param("isdsss", $userId, $reference, $amount, $paymentMethod, $paymentDetails, $reference);
            $stmt->execute();
            $requestId = $stmt->insert_id;
        }
        
        // Record Paystack transaction
        $stmt = $db->prepare("
            INSERT INTO paystack_transactions (reference, user_id, amount, status, paystack_data)
            VALUES (?, ?, ?, 'success', ?)
        ");
        $paystackData = json_encode($verification);
        $stmt->bind_param("sids", $reference, $userId, $amount, $paystackData);
        $stmt->execute();
        
        // Get current wallet balance
        $stmt = $db->prepare("SELECT wallet_balance FROM users WHERE id = ? FOR UPDATE");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $balanceBefore = floatval($user['wallet_balance']);
        $balanceAfter = $balanceBefore + $amount;
        
        // Update user wallet
        $stmt = $db->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
        $stmt->bind_param("di", $amount, $userId);
        $stmt->execute();
        
        // Record wallet transaction
        $stmt = $db->prepare("
            INSERT INTO wallet_transactions (
                user_id, reference, type, amount, balance_before, balance_after, 
                status, funding_request_id, paystack_reference, description
            ) VALUES (?, ?, 'credit', ?, ?, ?, 'completed', ?, ?, ?)
        ");
        $description = "Paystack funding - ₦" . number_format($amount, 2);
        $stmt->bind_param("isdddsis", 
            $userId, $reference, $amount, $balanceBefore, $balanceAfter, 
            $requestId, $reference, $description
        );
        $stmt->execute();
        
        // Record in main transactions
        $stmt = $db->prepare("
            INSERT INTO transactions (
                user_id, transaction_id, type, amount, total, status, description, created_at
            ) VALUES (?, ?, 'wallet_funding', ?, ?, 'success', ?, NOW())
        ");
        $stmt->bind_param("isdds", $userId, $reference, $amount, $amount, $description);
        $stmt->execute();
        
        // Update funding request status
        $stmt = $db->prepare("
            UPDATE funding_requests 
            SET status = 'approved', approved_at = NOW() 
            WHERE id = ?
        ");
        $stmt->bind_param("i", $requestId);
        $stmt->execute();
        
        // Create notification
        $title = "Wallet Funded via Paystack";
        $message = "Your wallet has been funded with ₦" . number_format($amount, 2) . 
                   " via Paystack. Reference: " . $reference;
        $stmt = $db->prepare("
            INSERT INTO notifications (user_id, type, title, message, created_at) 
            VALUES (?, 'wallet', ?, ?, NOW())
        ");
        $stmt->bind_param("iss", $userId, $title, $message);
        $stmt->execute();
        
        $db->commit();
        
        // Start session if not started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['success'] = "Payment successful! ₦" . number_format($amount, 2) . " added to your wallet.";
        
        // Redirect to wallet
        header('Location: user/wallet.php?payment=success');
        exit;
        
    } catch (Exception $e) {
        $db->rollback();
        error_log("Paystack callback error: " . $e->getMessage());
        header('Location: user/wallet.php?error=payment_failed');
        exit;
    }
} else {
    // Payment failed
    header('Location: user/wallet.php?error=payment_failed');
    exit;
}
?>