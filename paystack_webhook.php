<?php
// paystack_webhook.php - Paystack Webhook Handler
require_once 'config.php';
require_once 'includes/db_connection.php';
require_once 'includes/functions.php';
require_once 'includes/paystack_config.php';
require_once 'includes/funding_functions.php';

// Verify webhook signature
$signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';
$payload = file_get_contents('php://input');

$paystack = PaystackConfig::getInstance();
$secretKey = $paystack->getSecretKey();

$computedSignature = hash_hmac('sha512', $payload, $secretKey);

if ($signature !== $computedSignature) {
    http_response_code(401);
    exit('Invalid signature');
}

// Process webhook
$event = json_decode($payload, true);

if ($event['event'] === 'charge.success') {
    $data = $event['data'];
    $reference = $data['reference'];
    $amount = $data['amount'] / 100;
    $email = $data['customer']['email'];
    $metadata = $data['metadata'] ?? [];
    
    $db = db();
    $fundingManager = new FundingManager($db);
    
    // Find user
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
    
    if ($userId) {
        // Process the funding (similar to callback)
        $db->begin_transaction();
        
        try {
            // Check if already processed
            $stmt = $db->prepare("SELECT id FROM funding_requests WHERE paystack_reference = ? AND status = 'approved'");
            $stmt->bind_param("s", $reference);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                http_response_code(200);
                exit('Already processed');
            }
            
            // Create funding request
            $stmt = $db->prepare("
                INSERT INTO funding_requests (
                    user_id, reference, amount, payment_method, payment_type, 
                    paystack_reference, status
                ) VALUES (?, ?, ?, 'paystack', 'paystack', ?, 'approved')
            ");
            $stmt->bind_param("isdss", $userId, $reference, $amount, $reference);
            $stmt->execute();
            $requestId = $stmt->insert_id;
            
            // Record Paystack transaction
            $stmt = $db->prepare("
                INSERT INTO paystack_transactions (reference, user_id, amount, status, paystack_data)
                VALUES (?, ?, ?, 'success', ?)
            ");
            $paystackData = json_encode($data);
            $stmt->bind_param("sids", $reference, $userId, $amount, $paystackData);
            $stmt->execute();
            
            // Get current balance
            $stmt = $db->prepare("SELECT wallet_balance FROM users WHERE id = ? FOR UPDATE");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $balanceBefore = floatval($user['wallet_balance']);
            $balanceAfter = $balanceBefore + $amount;
            
            // Update wallet
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
            
            $db->commit();
            
            http_response_code(200);
            echo 'Webhook processed successfully';
            exit;
            
        } catch (Exception $e) {
            $db->rollback();
            error_log("Webhook error: " . $e->getMessage());
            http_response_code(500);
            exit('Processing failed');
        }
    }
}

http_response_code(200);
echo 'Event ignored';
?>