<?php
// admin/profile.php - Admin Profile
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

Auth::requireAdmin();

$db = db();
$adminId = Session::userId();

// Get admin data
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $adminId);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !Session::verifyCSRF($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        
        // Check if email exists
        if ($email != $admin['email']) {
            $check = $db->query("SELECT id FROM users WHERE email = '$email' AND id != $adminId");
            if ($check->num_rows > 0) {
                Session::setError('Email already in use');
                redirect('profile.php');
            }
        }
        
        $stmt = $db->prepare("UPDATE users SET email = ?, phone = ?, first_name = ?, last_name = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $email, $phone, $first_name, $last_name, $adminId);
        
        if ($stmt->execute()) {
            Session::setSuccess('Profile updated successfully');
        } else {
            Session::setError('Failed to update profile');
        }
        redirect('profile.php');
    }
    
    elseif ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        
        if (!password_verify($current, $admin['password'])) {
            Session::setError('Current password is incorrect');
        } elseif (strlen($new) < 8) {
            Session::setError('Password must be at least 8 characters');
        } elseif ($new !== $confirm) {
            Session::setError('Passwords do not match');
        } else {
            $hashed = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed, $adminId);
            $stmt->execute();
            Session::setSuccess('Password changed successfully');
        }
        redirect('profile.php');
    }
}

$pageTitle = 'My Profile';
include 'admin_header.php';
?>

<div class="container-fluid">
    <div class="content-header">
        <h2><i class="fas fa-user-cog"></i> My Profile</h2>
    </div>

    <!-- Alerts -->
    <?php if ($error = Session::getError()): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>
    <?php if ($success = Session::getSuccess()): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
        <!-- Profile Info -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-id-card"></i> Profile Information</h3>
            </div>
            
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo Session::generateCSRF(); ?>">
                <input type="hidden" name="action" value="update_profile">
                
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($admin['first_name']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($admin['last_name']); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($admin['username']); ?>" readonly>
                </div>
                
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($admin['phone']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Role</label>
                    <input type="text" class="form-control" value="<?php echo ucfirst($admin['role']); ?>" readonly>
                </div>
                
                <button type="submit" class="btn btn-primary">Update Profile</button>
            </form>
        </div>
        
        <!-- Change Password -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-lock"></i> Change Password</h3>
            </div>
            
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo Session::generateCSRF(); ?>">
                <input type="hidden" name="action" value="change_password">
                
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
                
                <button type="submit" class="btn btn-primary">Change Password</button>
            </form>
            
            <hr style="margin: 2rem 0;">
            
            <h4>Session Information</h4>
            <div style="background: var(--light); padding: 1rem; border-radius: var(--radius);">
                <p><strong>Last Login:</strong> <?php echo $admin['last_login'] ? date('M d, Y h:i A', strtotime($admin['last_login'])) : 'Never'; ?></p>
                <p><strong>Last IP:</strong> <?php echo $admin['last_ip'] ?? 'N/A'; ?></p>
                <p><strong>Member Since:</strong> <?php echo date('M d, Y', strtotime($admin['created_at'])); ?></p>
            </div>
        </div>
    </div>
</div>

<?php include 'admin_footer.php'; ?>