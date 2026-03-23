<?php
// admin/settings.php - System Settings
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
    
    $settings = $_POST['settings'] ?? [];
    
    foreach ($settings as $key => $value) {
        $value = $db->real_escape_string($value);
        $db->query("INSERT INTO settings (`key`, `value`) VALUES ('$key', '$value') ON DUPLICATE KEY UPDATE `value` = '$value'");
    }
    
    Session::setSuccess('Settings updated successfully');
    redirect('settings.php');
}

// Get current settings
$settings = [];
$result = $db->query("SELECT * FROM settings ORDER BY `key`");
while ($row = $result->fetch_assoc()) {
    $settings[$row['key']] = $row['value'];
}

$pageTitle = 'System Settings';
include 'admin_header.php';
?>

<div class="container-fluid">
    <div class-content-header>
        <h2><i class="fas fa-cog"></i> System Settings</h2>
    </div>

    <!-- Alerts -->
    <?php if ($error = Session::getError()): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>
    <?php if ($success = Session::getSuccess()): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo Session::generateCSRF(); ?>">
        
        <!-- General Settings -->
        <div class="card">
            <div class="card-header">
                <h3>General Settings</h3>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                <div class="form-group">
                    <label>Site Name</label>
                    <input type="text" name="settings[site_name]" class="form-control" value="<?php echo htmlspecialchars($settings['site_name'] ?? SITE_NAME); ?>">
                </div>
                
                <div class="form-group">
                    <label>Site Email</label>
                    <input type="email" name="settings[site_email]" class="form-control" value="<?php echo htmlspecialchars($settings['site_email'] ?? SITE_EMAIL); ?>">
                </div>
                
                <div class="form-group">
                    <label>Site Phone</label>
                    <input type="text" name="settings[site_phone]" class="form-control" value="<?php echo htmlspecialchars($settings['site_phone'] ?? SITE_PHONE); ?>">
                </div>
                
                <div class="form-group">
                    <label>Currency Symbol</label>
                    <input type="text" name="settings[currency]" class="form-control" value="<?php echo htmlspecialchars($settings['currency'] ?? CURRENCY); ?>">
                </div>
            </div>
        </div>
        
        <!-- Transaction Limits -->
        <div class="card">
            <div class="card-header">
                <h3>Transaction Limits</h3>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">
                <div class="form-group">
                    <label>Min Airtime (₦)</label>
                    <input type="number" name="settings[min_airtime]" class="form-control" value="<?php echo $settings['min_airtime'] ?? MIN_AIRTIME; ?>">
                </div>
                
                <div class="form-group">
                    <label>Max Airtime (₦)</label>
                    <input type="number" name="settings[max_airtime]" class="form-control" value="<?php echo $settings['max_airtime'] ?? MAX_AIRTIME; ?>">
                </div>
                
                <div class="form-group">
                    <label>Min Electricity (₦)</label>
                    <input type="number" name="settings[min_electricity]" class="form-control" value="<?php echo $settings['min_electricity'] ?? MIN_ELECTRICITY; ?>">
                </div>
            </div>
        </div>
        
        <!-- Referral Settings -->
        <div class="card">
            <div class="card-header">
                <h3>Referral Settings</h3>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                <div class="form-group">
                    <label>Referral Bonus (₦)</label>
                    <input type="number" name="settings[referral_bonus]" class="form-control" value="<?php echo $settings['referral_bonus'] ?? REFERRAL_BONUS; ?>">
                </div>
                
                <div class="form-group">
                    <label>Referral Percentage (%)</label>
                    <input type="number" name="settings[referral_percentage]" class="form-control" value="<?php echo $settings['referral_percentage'] ?? REFERRAL_PERCENTAGE; ?>" step="0.1">
                </div>
            </div>
        </div>
        
        <!-- Security Settings -->
        <div class="card">
            <div class="card-header">
                <h3>Security Settings</h3>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                <div class="form-group">
                    <label>Session Lifetime (minutes)</label>
                    <input type="number" name="settings[session_lifetime]" class="form-control" value="<?php echo $settings['session_lifetime'] ?? (SESSION_LIFETIME/60); ?>">
                </div>
                
                <div class="form-group">
                    <label>Max Login Attempts</label>
                    <input type="number" name="settings[max_login_attempts]" class="form-control" value="<?php echo $settings['max_login_attempts'] ?? 5; ?>">
                </div>
            </div>
        </div>
        
        <button type="submit" class="btn btn-primary">Save All Settings</button>
    </form>
</div>

<?php include 'admin_footer.php'; ?>