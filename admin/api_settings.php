<?php
// admin/api_settings.php - API Settings
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

Auth::requireAdmin();

$db = db();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !Session::verifyCSRF($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }
    
    $provider = $_POST['provider'] ?? '';
    
    if ($provider === 'vtpass') {
        $api_key = $_POST['api_key'] ?? '';
        $api_secret = $_POST['api_secret'] ?? '';
        $api_url = $_POST['api_url'] ?? 'https://api-service.vtpass.com/api';
        
        $stmt = $db->prepare("UPDATE api_providers SET api_key = ?, api_secret = ?, api_url = ? WHERE code = 'vtpass'");
        $stmt->bind_param("sss", $api_key, $api_secret, $api_url);
        $stmt->execute();
        
        Session::setSuccess('VTpass API settings updated');
        redirect('api_settings.php');
    }
    
    elseif ($provider === 'paystack') {
        $public_key = $_POST['public_key'] ?? '';
        $secret_key = $_POST['secret_key'] ?? '';
        
        // Save to settings table
        $db->query("UPDATE settings SET `value` = '$public_key' WHERE `key` = 'paystack_public_key'");
        $db->query("UPDATE settings SET `value` = '$secret_key' WHERE `key` = 'paystack_secret_key'");
        
        Session::setSuccess('Paystack settings updated');
        redirect('api_settings.php');
    }
    
    elseif ($provider === 'flutterwave') {
        $public_key = $_POST['public_key'] ?? '';
        $secret_key = $_POST['secret_key'] ?? '';
        $encryption_key = $_POST['encryption_key'] ?? '';
        
        $db->query("UPDATE settings SET `value` = '$public_key' WHERE `key` = 'flutterwave_public_key'");
        $db->query("UPDATE settings SET `value` = '$secret_key' WHERE `key` = 'flutterwave_secret_key'");
        $db->query("UPDATE settings SET `value` = '$encryption_key' WHERE `key` = 'flutterwave_encryption_key'");
        
        Session::setSuccess('Flutterwave settings updated');
        redirect('api_settings.php');
    }
}

// Get current settings
$vtpass = $db->query("SELECT * FROM api_providers WHERE code = 'vtpass'")->fetch_assoc();

$paystack_public = getSetting('paystack_public_key', '');
$paystack_secret = getSetting('paystack_secret_key', '');

$flutterwave_public = getSetting('flutterwave_public_key', '');
$flutterwave_secret = getSetting('flutterwave_secret_key', '');
$flutterwave_encryption = getSetting('flutterwave_encryption_key', '');

$pageTitle = 'API Settings';
include 'admin_header.php';
?>

<div class="container-fluid">
    <div class="content-header">
        <h2><i class="fas fa-key"></i> API Settings</h2>
    </div>

    <!-- Alerts -->
    <?php if ($error = Session::getError()): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>
    <?php if ($success = Session::getSuccess()): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <!-- VTpass Settings -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-exchange-alt"></i> VTpass API (VTU Services)</h3>
        </div>
        
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo Session::generateCSRF(); ?>">
            <input type="hidden" name="provider" value="vtpass">
            
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                <div class="form-group">
                    <label>API Key</label>
                    <input type="text" name="api_key" class="form-control" value="<?php echo htmlspecialchars($vtpass['api_key'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>API Secret</label>
                    <input type="text" name="api_secret" class="form-control" value="<?php echo htmlspecialchars($vtpass['api_secret'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label>API URL</label>
                <input type="url" name="api_url" class="form-control" value="<?php echo htmlspecialchars($vtpass['api_url'] ?? 'https://api-service.vtpass.com/api'); ?>">
            </div>
            
            <button type="submit" class="btn btn-primary">Save VTpass Settings</button>
        </form>
    </div>

    <!-- Paystack Settings -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-credit-card"></i> Paystack (Payment Gateway)</h3>
        </div>
        
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo Session::generateCSRF(); ?>">
            <input type="hidden" name="provider" value="paystack">
            
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                <div class="form-group">
                    <label>Public Key</label>
                    <input type="text" name="public_key" class="form-control" value="<?php echo htmlspecialchars($paystack_public); ?>">
                </div>
                <div class="form-group">
                    <label>Secret Key</label>
                    <input type="text" name="secret_key" class="form-control" value="<?php echo htmlspecialchars($paystack_secret); ?>">
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary">Save Paystack Settings</button>
        </form>
    </div>

    <!-- Flutterwave Settings -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-university"></i> Flutterwave (Payment Gateway)</h3>
        </div>
        
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo Session::generateCSRF(); ?>">
            <input type="hidden" name="provider" value="flutterwave">
            
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">
                <div class="form-group">
                    <label>Public Key</label>
                    <input type="text" name="public_key" class="form-control" value="<?php echo htmlspecialchars($flutterwave_public); ?>">
                </div>
                <div class="form-group">
                    <label>Secret Key</label>
                    <input type="text" name="secret_key" class="form-control" value="<?php echo htmlspecialchars($flutterwave_secret); ?>">
                </div>
                <div class="form-group">
                    <label>Encryption Key</label>
                    <input type="text" name="encryption_key" class="form-control" value="<?php echo htmlspecialchars($flutterwave_encryption); ?>">
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary">Save Flutterwave Settings</button>
        </form>
    </div>
</div>

<?php include 'admin_footer.php'; ?>