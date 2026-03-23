<?php
// api/webhook.php - Handle payment webhooks
require_once '../config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

$db = db();

// Get webhook payload
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Log webhook
$log_stmt = $db->prepare("INSERT INTO api_logs (provider, endpoint, request, created_at) VALUES ('webhook', 'payment', ?, NOW())");
$log_stmt->bind_param("s", $input);
$log_stmt->execute();

// Verify signature (Paystack example)
$signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';
$secret = PAYSTACK_SECRET_KEY;

if ($signature && hash_hmac('sha512', $input, $secret) !== $signature) {
    http_response_code(401);
    exit('Invalid signature');
}

// Handle different events
if (isset($data['event'])) {
    switch ($data['event']) {
        case 'charge.success':
            // Payment successful
            $reference = $data['data']['reference'];
            $amount = $data['data']['amount'] / 100; // Convert from kobo
            $status = $data['data']['status'];
            
            if ($status == 'success') {
                // Find funding request
                $stmt = $db->prepare("SELECT * FROM funding_requests WHERE reference = ?");
                $stmt->bind_param("s", $reference);
                $stmt->execute();
                $funding = $stmt->get_result()->fetch_assoc();
                
                if ($funding && $funding['status'] == 'pending') {
                    // Update funding status
                    $stmt = $db->prepare("UPDATE funding_requests SET status = 'success', paid_at = NOW() WHERE reference = ?");
                    $stmt->bind_param("s", $reference);
                    $stmt->execute();
                    
                    // Credit user wallet
                    $stmt = $db->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
                    $stmt->bind_param("di", $amount, $funding['user_id']);
                    $stmt->execute();
                    
                    // Create transaction record
                    $transaction_id = generate_reference('FUND');
                    $stmt = $db->prepare("INSERT INTO transactions (user_id, transaction_id, type, amount, total, status) VALUES (?, ?, 'wallet_funding', ?, ?, 'success')");
                    $total = $amount;
                    $stmt->bind_param("isdd", $funding['user_id'], $transaction_id, $amount, $total);
                    $stmt->execute();
                    
                    // Create notification
                    $title = "Wallet Funded Successfully";
                    $message = "Your wallet has been funded with " . format_money($amount);
                    $stmt = $db->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?, 'wallet', ?, ?)");
                    $stmt->bind_param("iss", $funding['user_id'], $title, $message);
                    $stmt->execute();
                }
            }
            break;
            
        case 'charge.failed':
            // Payment failed
            $reference = $data['data']['reference'];
            
            $stmt = $db->prepare("UPDATE funding_requests SET status = 'failed' WHERE reference = ?");
            $stmt->bind_param("s", $reference);
            $stmt->execute();
            break;
    }
}

http_response_code(200);
echo 'Webhook processed';
?>