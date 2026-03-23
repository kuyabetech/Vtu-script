<?php
// includes/paystack_config.php - Paystack Payment Configuration
require_once __DIR__ . '/db_connection.php';
require_once __DIR__ . '/settings.php';

class PaystackConfig {
    private static $instance = null;
    private $secretKey;
    private $publicKey;
    private $mode;
    private $enabled;
    
    private function __construct() {
        $this->mode = Settings::get('paystack_mode', 'test');
        $this->enabled = Settings::get('paystack_enabled', true);
        
        if ($this->mode === 'live') {
            $this->secretKey = Settings::get('paystack_secret_key', '');
            $this->publicKey = Settings::get('paystack_public_key', '');
        } else {
            $this->secretKey = Settings::get('paystack_test_secret_key', '');
            $this->publicKey = Settings::get('paystack_test_public_key', '');
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function isEnabled() {
        return $this->enabled && !empty($this->secretKey) && !empty($this->publicKey);
    }
    
    public function getSecretKey() {
        return $this->secretKey;
    }
    
    public function getPublicKey() {
        return $this->publicKey;
    }
    
    public function getMode() {
        return $this->mode;
    }
    
    public function getCallbackUrl() {
        $callback = Settings::get('paystack_callback_url', '');
        if (empty($callback)) {
            $callback = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . 
                       $_SERVER['HTTP_HOST'] . 
                       dirname($_SERVER['SCRIPT_NAME']) . 
                       '/paystack_callback.php';
        }
        return $callback;
    }
    
    /**
     * Initialize Paystack transaction
     */
    public function initializeTransaction($email, $amount, $reference, $metadata = []) {
        $url = "https://api.paystack.co/transaction/initialize";
        
        $data = [
            'email' => $email,
            'amount' => $amount * 100, // Convert to kobo
            'reference' => $reference,
            'callback_url' => $this->getCallbackUrl(),
            'metadata' => $metadata
        ];
        
        $headers = [
            'Authorization: Bearer ' . $this->secretKey,
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            if ($result['status']) {
                return [
                    'success' => true,
                    'authorization_url' => $result['data']['authorization_url'],
                    'reference' => $result['data']['reference']
                ];
            }
        }
        
        return [
            'success' => false,
            'message' => 'Failed to initialize transaction'
        ];
    }
    
    /**
     * Verify Paystack transaction
     */
    public function verifyTransaction($reference) {
        $url = "https://api.paystack.co/transaction/verify/{$reference}";
        
        $headers = [
            'Authorization: Bearer ' . $this->secretKey,
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            if ($result['status']) {
                $data = $result['data'];
                return [
                    'success' => true,
                    'status' => $data['status'],
                    'amount' => $data['amount'] / 100,
                    'reference' => $data['reference'],
                    'customer' => $data['customer'],
                    'metadata' => $data['metadata']
                ];
            }
        }
        
        return [
            'success' => false,
            'message' => 'Verification failed'
        ];
    }
}
?>