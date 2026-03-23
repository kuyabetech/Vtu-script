<?php
// api/vtpass.php - VTpass API Integration
require_once '../config.php';
require_once '../includes/db_connection.php';

class VTPassAPI {
    private $api_key;
    private $api_secret;
    private $api_url;
    private $db;
    
    public function __construct() {
        $this->db = db();
        
        // Load API credentials from database
        $result = $this->db->query("SELECT * FROM api_providers WHERE code = 'vtpass' AND is_active = 1");
        $provider = $result->fetch_assoc();
        
        if ($provider) {
            $this->api_key = $provider['api_key'];
            $this->api_secret = $provider['api_secret'];
            $this->api_url = $provider['api_url'];
        } else {
            // Fallback to constants
            $this->api_key = VTPASS_API_KEY;
            $this->api_secret = VTPASS_SECRET;
            $this->api_url = VTPASS_URL;
        }
    }
    
    /**
     * Make API request to VTpass
     */
    private function makeRequest($endpoint, $data = []) {
        $url = $this->api_url . '/' . $endpoint;
        
        $headers = [
            'Content-Type: application/json',
            'api-key: ' . $this->api_key,
            'secret-key: ' . $this->api_secret
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        // Log API request
        $this->logRequest($endpoint, $data, $response, $http_code, $error);
        
        if ($error) {
            return [
                'success' => false,
                'message' => 'API connection failed: ' . $error
            ];
        }
        
        $result = json_decode($response, true);
        
        return [
            'success' => ($http_code == 200 && isset($result['code']) && $result['code'] == '000'),
            'data' => $result,
            'message' => $result['response_description'] ?? 'Unknown response'
        ];
    }
    
    /**
     * Log API request for debugging
     */
    private function logRequest($endpoint, $request, $response, $status_code, $error) {
        $stmt = $this->db->prepare("
            INSERT INTO api_logs (provider, endpoint, request, response, status_code, error, created_at) 
            VALUES ('vtpass', ?, ?, ?, ?, ?, NOW())
        ");
        $request_json = json_encode($request);
        $response_json = is_string($response) ? $response : json_encode($response);
        $error_msg = $error ?: null;
        $stmt->bind_param("sssis", $endpoint, $request_json, $response_json, $status_code, $error_msg);
        $stmt->execute();
    }
    
    /**
     * Purchase airtime
     */
    public function purchaseAirtime($network, $amount, $phone, $reference) {
        $serviceID = $this->getNetworkCode($network);
        
        $data = [
            'request_id' => $reference,
            'serviceID' => $serviceID,
            'amount' => $amount,
            'phone' => $phone
        ];
        
        return $this->makeRequest('pay', $data);
    }
    
    /**
     * Purchase data bundle
     */
    public function purchaseData($network, $variation_code, $phone, $reference) {
        $serviceID = $this->getNetworkCode($network) . '-data';
        
        $data = [
            'request_id' => $reference,
            'serviceID' => $serviceID,
            'variation_code' => $variation_code,
            'phone' => $phone
        ];
        
        return $this->makeRequest('pay', $data);
    }
    
    /**
     * Pay electricity bill
     */
    public function payElectricity($disco, $meter_number, $meter_type, $amount, $phone, $reference) {
        $data = [
            'request_id' => $reference,
            'serviceID' => $disco,
            'billersCode' => $meter_number,
            'variation_code' => $meter_type,
            'amount' => $amount,
            'phone' => $phone
        ];
        
        return $this->makeRequest('pay', $data);
    }
    
    /**
     * Pay cable TV
     */
    public function payCableTV($provider, $smart_card, $package, $amount, $phone, $reference) {
        $data = [
            'request_id' => $reference,
            'serviceID' => $provider,
            'billersCode' => $smart_card,
            'variation_code' => $package,
            'amount' => $amount,
            'phone' => $phone
        ];
        
        return $this->makeRequest('pay', $data);
    }
    
    /**
     * Verify meter number
     */
    public function verifyMeter($disco, $meter_number, $meter_type) {
        $data = [
            'serviceID' => $disco,
            'billersCode' => $meter_number,
            'type' => $meter_type
        ];
        
        return $this->makeRequest('merchant-verify', $data);
    }
    
    /**
     * Verify smart card
     */
    public function verifySmartCard($provider, $smart_card) {
        $data = [
            'serviceID' => $provider,
            'billersCode' => $smart_card
        ];
        
        return $this->makeRequest('merchant-verify', $data);
    }
    
    /**
     * Get network code for VTpass
     */
    private function getNetworkCode($network) {
        $codes = [
            'mtn' => 'mtn',
            'glo' => 'glo',
            'airtel' => 'airtel',
            '9mobile' => 'etisalat'
        ];
        return $codes[strtolower($network)] ?? $network;
    }
    
    /**
     * Check transaction status
     */
    public function checkStatus($request_id) {
        $data = [
            'request_id' => $request_id
        ];
        
        return $this->makeRequest('requery', $data);
    }
    
    /**
     * Get wallet balance
     */
    public function getWalletBalance() {
        return $this->makeRequest('balance');
    }
    
    /**
     * Get data plans for a network
     */
    public function getDataPlans($network) {
        $serviceID = $this->getNetworkCode($network) . '-data';
        
        $data = [
            'serviceID' => $serviceID
        ];
        
        return $this->makeRequest('service-variations', $data);
    }
}
?>