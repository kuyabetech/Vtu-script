<?php
// includes/sms.php - SMS sending via Termii
require_once __DIR__ . '/../config.php';

class SMS {
    
    /**
     * Send SMS using Termii API
     */
    public static function send($to, $message) {
        $api_key = $_ENV['TERMII_API_KEY'] ?? '';
        $sender_id = $_ENV['TERMII_SENDER_ID'] ?? 'VTUAlert';
        
        $data = [
            'api_key' => $api_key,
            'to' => $to,
            'from' => $sender_id,
            'sms' => $message,
            'type' => 'plain',
            'channel' => 'generic'
        ];
        
        $url = 'https://api.termii.com/api/sms/send';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $result = json_decode($response, true);
        $success = ($http_code == 200 && isset($result['message_id']));
        
        // Log SMS
        self::logSMS($to, $message, $success ? 'sent' : 'failed', $response);
        
        return $success;
    }
    
    /**
     * Send transaction notification
     */
    public static function sendTransactionAlert($phone, $type, $amount, $status) {
        $message = SITE_NAME . ": Your $type transaction of " . format_money($amount) . " is $status.";
        return self::send($phone, $message);
    }
    
    /**
     * Send wallet alert
     */
    public static function sendWalletAlert($phone, $amount, $balance, $type) {
        $action = $type == 'credit' ? 'credited to' : 'debited from';
        $message = SITE_NAME . ": ₦" . number_format($amount, 2) . " $action your wallet. New balance: " . format_money($balance);
        return self::send($phone, $message);
    }
    
    /**
     * Send verification code
     */
    public static function sendVerificationCode($phone, $code) {
        $message = "Your " . SITE_NAME . " verification code is: $code";
        return self::send($phone, $message);
    }
    
    /**
     * Log SMS to database
     */
    private static function logSMS($phone, $message, $status, $response) {
        require_once __DIR__ . '/db_connection.php';
        
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("INSERT INTO sms_queue (phone, message, status, provider_response, sent_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssss", $phone, $message, $status, $response);
        $stmt->execute();
    }
}
?>