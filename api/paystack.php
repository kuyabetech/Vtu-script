<?php
// api/paystack.php - Paystack Payment Integration
require_once '../config.php';
require_once '../includes/db_connection.php';

class PaystackAPI {
    private $secret_key;
    private $public_key;
    private $api_url = 'https://api.paystack.co';
    
    public function __construct() {
        // Load from database or constants
        $this->secret_key = PAYSTACK_SECRET_KEY;
        $this->public_key = PAYSTACK_PUBLIC_KEY;
    }
    
    /**
     * Initialize payment
     */
    public function initializePayment($email, $amount, $reference, $callback_url) {
        $url = $this->api_url . '/transaction/initialize';
        
        $data = [
            'email' => $email,
            'amount' => $amount * 100, // Paystack uses kobo
            'reference' => $reference,
            'callback_url' => $callback_url
        ];
        
        $headers = [
            'Authorization: Bearer ' . $this->secret_key,
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    }
    
    /**
     * Verify payment
     */
    public function verifyPayment($reference) {
        $url = $this->api_url . '/transaction/verify/' . $reference;
        
        $headers = [
            'Authorization: Bearer ' . $this->secret_key
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    }
    
    /**
     * Get public key
     */
    public function getPublicKey() {
        return $this->public_key;
    }
}
?>