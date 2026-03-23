<?php
// includes/funding_functions.php - Manual Funding Functions

// Include required files
require_once __DIR__ . '/db_connection.php';
require_once __DIR__ . '/functions.php';

// Include settings if exists
if (file_exists(__DIR__ . '/settings.php')) {
    require_once __DIR__ . '/settings.php';
} else {
    // Fallback Settings class
    if (!class_exists('Settings')) {
        class Settings {
            private static $cache = [];
            private static $db = null;
            
            private static function getDB() {
                global $db;
                return $db;
            }
            
            public static function get($key, $default = null) {
                $db = self::getDB();
                if (isset(self::$cache[$key])) {
                    return self::$cache[$key];
                }
                
                $stmt = $db->prepare("SELECT `value` FROM `settings` WHERE `key` = ?");
                if ($stmt) {
                    $stmt->bind_param("s", $key);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($row = $result->fetch_assoc()) {
                        self::$cache[$key] = $row['value'];
                        return $row['value'];
                    }
                }
                return $default;
            }
            
            public static function isFundingEnabled() {
                return (bool)self::get('funding_enabled', true);
            }
        }
    }
}

class FundingManager {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
  public function createFundingRequest($userId, $amount, $paymentMethod, $paymentDetails = null) {
    // Validate amount using settings
    $minAmount = Settings::get('min_funding_amount', 100);
    $maxAmount = Settings::get('max_funding_amount', 1000000);

    if ($amount < $minAmount) {
        return ['success' => false, 'message' => "Minimum funding amount is ₦" . number_format($minAmount, 2)];
    }

    if ($amount > $maxAmount) {
        return ['success' => false, 'message' => "Maximum funding amount is ₦" . number_format($maxAmount, 2)];
    }

    if (!Settings::isFundingEnabled()) {
        return ['success' => false, 'message' => "Manual funding is currently disabled. Please try again later."];
    }

    // Generate reference
    $reference = generate_reference('FUND');

    // Calculate total (you can add fees here if needed)
    $total = $amount; // for now, total = amount

    // Insert funding request including total
    $stmt = $this->db->prepare("
        INSERT INTO funding_requests 
            (user_id, reference, amount, total, payment_method, payment_details, status) 
        VALUES (?, ?, ?, ?, ?, ?, 'pending')
    ");

    if (!$stmt) {
        return ['success' => false, 'message' => 'Database error: ' . $this->db->error];
    }

    $stmt->bind_param("isddss", $userId, $reference, $amount, $total, $paymentMethod, $paymentDetails);

    if ($stmt->execute()) {
        $requestId = $stmt->insert_id;

        // Auto-approve logic
        $autoApproveUnder = Settings::get('auto_approve_under', 0);
        if ($autoApproveUnder > 0 && $amount <= $autoApproveUnder) {
            return $this->approveFundingRequest($requestId, null, 'Auto-approved');
        }

        // Notify admin
        $this->notifyAdmin($userId, $amount, $reference);

        return [
            'success' => true,
            'message' => 'Funding request created successfully. Please upload payment proof.',
            'request_id' => $requestId,
            'reference' => $reference
        ];
    }

    return ['success' => false, 'message' => 'Failed to create funding request: ' . $stmt->error];
}
    
    /**
     * Approve a funding request
     */
    public function approveFundingRequest($requestId, $adminId, $notes = null) {
        $this->db->begin_transaction();
        
        try {
            // Get funding request details with lock
            $stmt = $this->db->prepare("
                SELECT fr.*, u.wallet_balance 
                FROM funding_requests fr 
                JOIN users u ON fr.user_id = u.id 
                WHERE fr.id = ? AND fr.status = 'pending'
                FOR UPDATE
            ");
            $stmt->bind_param("i", $requestId);
            $stmt->execute();
            $result = $stmt->get_result();
            $request = $result->fetch_assoc();
            
            if (!$request) {
                throw new Exception('Funding request not found or already processed');
            }
            
            // Update funding request status
            $stmt = $this->db->prepare("
                UPDATE funding_requests 
                SET status = 'approved', admin_notes = ?, approved_by = ?, approved_at = NOW() 
                WHERE id = ?
            ");
            $stmt->bind_param("sii", $notes, $adminId, $requestId);
            $stmt->execute();
            
            // Update user wallet balance
            $balanceBefore = floatval($request['wallet_balance']);
            $balanceAfter = $balanceBefore + floatval($request['amount']);
            
            $stmt = $this->db->prepare("
                UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?
            ");
            $stmt->bind_param("di", $request['amount'], $request['user_id']);
            $stmt->execute();
            
            // Record wallet transaction
            $stmt = $this->db->prepare("
                INSERT INTO wallet_transactions (
                    user_id, reference, type, amount, balance_before, balance_after, 
                    status, funding_request_id, description
                ) VALUES (?, ?, 'credit', ?, ?, ?, 'completed', ?, ?)
            ");
            $description = "Manual funding via " . $request['payment_method'] . " - Request #" . $request['reference'];
            $stmt->bind_param("isddsis", 
                $request['user_id'], 
                $request['reference'], 
                $request['amount'], 
                $balanceBefore, 
                $balanceAfter, 
                $requestId,
                $description
            );
            $stmt->execute();
            
            // Record in main transactions
            $stmt = $this->db->prepare("
                INSERT INTO transactions (
                    user_id, transaction_id, type, amount, total, status, description, created_at
                ) VALUES (?, ?, 'wallet_funding', ?, ?, 'success', ?, NOW())
            ");
            $stmt->bind_param("isdds", 
                $request['user_id'], 
                $request['reference'], 
                $request['amount'], 
                $request['amount'], 
                $description
            );
            $stmt->execute();
            
            // Create notification for user
            $title = "Wallet Funded Successfully";
            $message = "Your wallet has been funded with ₦" . number_format($request['amount'], 2) . 
                       ". Reference: " . $request['reference'];
            $stmt = $this->db->prepare("
                INSERT INTO notifications (user_id, type, title, message, created_at) 
                VALUES (?, 'wallet', ?, ?, NOW())
            ");
            $stmt->bind_param("iss", $request['user_id'], $title, $message);
            $stmt->execute();
            
            $this->db->commit();
            
            return ['success' => true, 'message' => 'Funding request approved successfully'];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Reject a funding request
     */
    public function rejectFundingRequest($requestId, $adminId, $reason) {
        $stmt = $this->db->prepare("
            UPDATE funding_requests 
            SET status = 'rejected', admin_notes = ?, approved_by = ?, approved_at = NOW() 
            WHERE id = ? AND status = 'pending'
        ");
        $stmt->bind_param("sii", $reason, $adminId, $requestId);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            // Get user info for notification
            $stmt = $this->db->prepare("
                SELECT user_id, reference, amount FROM funding_requests WHERE id = ?
            ");
            $stmt->bind_param("i", $requestId);
            $stmt->execute();
            $request = $stmt->get_result()->fetch_assoc();
            
            // Create notification for user
            $title = "Funding Request Rejected";
            $message = "Your funding request of ₦" . number_format($request['amount'], 2) . 
                       " (Ref: " . $request['reference'] . ") was rejected. Reason: " . $reason;
            $stmt = $this->db->prepare("
                INSERT INTO notifications (user_id, type, title, message, created_at) 
                VALUES (?, 'wallet', ?, ?, NOW())
            ");
            $stmt->bind_param("iss", $request['user_id'], $title, $message);
            $stmt->execute();
            
            return ['success' => true, 'message' => 'Funding request rejected'];
        }
        
        return ['success' => false, 'message' => 'Failed to reject request'];
    }
    
    /**
     * Upload payment proof
     */
    public function uploadPaymentProof($requestId, $file) {
        $uploadDir = dirname(dirname(__FILE__)) . '/uploads/payment_proofs/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'File upload failed'];
        }
        
        if ($file['size'] > $maxSize) {
            return ['success' => false, 'message' => 'File too large. Max 5MB'];
        }
        
        if (!in_array($file['type'], $allowedTypes)) {
            return ['success' => false, 'message' => 'Invalid file type. Allowed: JPG, PNG, PDF'];
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'proof_' . $requestId . '_' . time() . '.' . $extension;
        $filepath = $uploadDir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Check if payment_proofs table exists
            $stmt = $this->db->prepare("
                INSERT INTO payment_proofs (funding_request_id, file_path, file_name, file_type) 
                VALUES (?, ?, ?, ?)
            ");
            
            if (!$stmt) {
                return ['success' => false, 'message' => 'Database error: payment_proofs table may not exist'];
            }
            
            $stmt->bind_param("isss", $requestId, $filepath, $file['name'], $file['type']);
            
            if ($stmt->execute()) {
                // Update funding request with proof uploaded flag
                $stmt = $this->db->prepare("
                    UPDATE funding_requests SET admin_notes = CONCAT(IFNULL(admin_notes, ''), '\nProof uploaded: ', ?) 
                    WHERE id = ?
                ");
                $proofNote = $filename;
                $stmt->bind_param("si", $proofNote, $requestId);
                $stmt->execute();
                
                return ['success' => true, 'message' => 'Payment proof uploaded successfully'];
            }
        }
        
        return ['success' => false, 'message' => 'Failed to upload file'];
    }
    
    /**
     * Get user funding requests
     */
    public function getUserFundingRequests($userId, $limit = 10) {
        $stmt = $this->db->prepare("
            SELECT fr.*, 
                   (SELECT file_path FROM payment_proofs WHERE funding_request_id = fr.id LIMIT 1) as proof_path
            FROM funding_requests fr 
            WHERE fr.user_id = ? 
            ORDER BY fr.created_at DESC 
            LIMIT ?
        ");
        $stmt->bind_param("ii", $userId, $limit);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Get pending funding requests (for admin)
     */
    public function getPendingRequests($limit = 50) {
        $stmt = $this->db->prepare("
            SELECT fr.*, u.name, u.email, u.phone,
                   (SELECT file_path FROM payment_proofs WHERE funding_request_id = fr.id LIMIT 1) as proof_path
            FROM funding_requests fr 
            JOIN users u ON fr.user_id = u.id 
            WHERE fr.status = 'pending' 
            ORDER BY fr.created_at ASC 
            LIMIT ?
        ");
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Get all funding requests (for admin)
     */
    public function getAllFundingRequests($status = null, $limit = 50, $offset = 0) {
        $sql = "
            SELECT fr.*, u.name, u.email, u.phone,
                   (SELECT file_path FROM payment_proofs WHERE funding_request_id = fr.id LIMIT 1) as proof_path,
                   admin.name as admin_name
            FROM funding_requests fr 
            JOIN users u ON fr.user_id = u.id 
            LEFT JOIN users admin ON fr.approved_by = admin.id 
        ";
        
        $params = [];
        $types = "";
        
        if ($status) {
            $sql .= " WHERE fr.status = ? ";
            $params[] = $status;
            $types .= "s";
        }
        
        $sql .= " ORDER BY fr.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        
        $stmt = $this->db->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }
        
        return [];
    }
    
    /**
     * Get funding statistics
     */
    public function getFundingStats() {
        $stats = [
            'pending' => ['count' => 0, 'total' => 0],
            'approved_month' => ['count' => 0, 'total' => 0],
            'approved_total' => ['count' => 0, 'total' => 0]
        ];
        
        // Total pending amount
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total 
            FROM funding_requests WHERE status = 'pending'
        ");
        if ($stmt) {
            $stmt->execute();
            $stats['pending'] = $stmt->get_result()->fetch_assoc();
        }
        
        // Total approved this month
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total 
            FROM funding_requests 
            WHERE status = 'approved' 
            AND MONTH(approved_at) = MONTH(CURRENT_DATE())
            AND YEAR(approved_at) = YEAR(CURRENT_DATE())
        ");
        if ($stmt) {
            $stmt->execute();
            $stats['approved_month'] = $stmt->get_result()->fetch_assoc();
        }
        
        // Total all time
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total 
            FROM funding_requests WHERE status = 'approved'
        ");
        if ($stmt) {
            $stmt->execute();
            $stats['approved_total'] = $stmt->get_result()->fetch_assoc();
        }
        
        return $stats;
    }
    
    /**
     * Notify admin about new funding request
     */
    private function notifyAdmin($userId, $amount, $reference) {
        // Get user details
        $stmt = $this->db->prepare("SELECT name, email FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        
        // Check if notifications table has the required columns
        $stmt = $this->db->prepare("
            INSERT INTO notifications (user_id, type, title, message, created_at) 
            SELECT id, 'admin', ?, ?, NOW() 
            FROM users WHERE is_admin = 1
        ");
        $title = "New Funding Request";
        $message = "User {$user['name']} requested ₦" . number_format($amount, 2) . 
                   " (Ref: $reference). Please review.";
        $stmt->bind_param("ss", $title, $message);
        $stmt->execute();
    }
    
    /**
     * Cancel funding request
     */
    public function cancelFundingRequest($requestId, $userId) {
        $stmt = $this->db->prepare("
            UPDATE funding_requests 
            SET status = 'cancelled' 
            WHERE id = ? AND user_id = ? AND status = 'pending'
        ");
        $stmt->bind_param("ii", $requestId, $userId);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            return ['success' => true, 'message' => 'Funding request cancelled'];
        }
        
        return ['success' => false, 'message' => 'Unable to cancel request'];
    }
}
?>