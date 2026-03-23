<?php
// user/profile.php - User Profile Page (Modern Responsive)
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

Auth::requireLogin();

$db = db();
$userId = Session::userId();

// Get user data
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get user statistics
$stmt = $db->prepare("SELECT COUNT(*) as total_transactions, COALESCE(SUM(amount), 0) as total_spent FROM transactions WHERE user_id = ? AND status = 'success'");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF
    if (!isset($_POST['csrf_token']) || !Session::verifyCSRF($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        
        $errors = [];
        
        if (!empty($phone) && !preg_match('/^[0-9]{11}$/', $phone)) {
            $errors[] = 'Phone number must be 11 digits';
        }
        
        // Check if phone is already taken by another user
        if ($phone && $phone != $user['phone']) {
            $stmt = $db->prepare("SELECT id FROM users WHERE phone = ? AND id != ?");
            $stmt->bind_param("si", $phone, $userId);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $errors[] = 'Phone number already registered by another user';
            }
        }
        
        if (empty($errors)) {
            $stmt = $db->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ?, address = ?, city = ?, state = ? WHERE id = ?");
            $stmt->bind_param("ssssssi", $first_name, $last_name, $phone, $address, $city, $state, $userId);
            
            if ($stmt->execute()) {
                Session::setSuccess('Profile updated successfully');
                redirect('user/profile.php');
            } else {
                Session::setError('Failed to update profile');
            }
        } else {
            foreach ($errors as $error) {
                Session::setError($error);
            }
        }
    }
    
    elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        $errors = [];
        
        if (empty($current_password)) {
            $errors[] = 'Current password is required';
        } elseif (!password_verify($current_password, $user['password'])) {
            $errors[] = 'Current password is incorrect';
        }
        
        if (empty($new_password)) {
            $errors[] = 'New password is required';
        } elseif (strlen($new_password) < 8) {
            $errors[] = 'Password must be at least 8 characters';
        } elseif (!preg_match('/[A-Z]/', $new_password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        } elseif (!preg_match('/[a-z]/', $new_password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        } elseif (!preg_match('/[0-9]/', $new_password)) {
            $errors[] = 'Password must contain at least one number';
        }
        
        if ($new_password !== $confirm_password) {
            $errors[] = 'Passwords do not match';
        }
        
        if (empty($errors)) {
            $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashedPassword, $userId);
            
            if ($stmt->execute()) {
                Session::setSuccess('Password changed successfully');
                redirect('user/profile.php');
            } else {
                Session::setError('Failed to change password');
            }
        } else {
            foreach ($errors as $error) {
                Session::setError($error);
            }
        }
    }
}

$pageTitle = 'My Profile';
include '../partials/user_header.php';
?>

<style>
/* ===== Modern Profile Page Styles ===== */

/* Content Header */
.content-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.content-header h2 {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: var(--dark);
    font-size: clamp(1.25rem, 4vw, 1.75rem);
}

.content-header h2 i {
    color: var(--primary);
    background: rgba(99, 102, 241, 0.1);
    padding: 0.75rem;
    border-radius: 50%;
    font-size: clamp(1rem, 3vw, 1.25rem);
}

/* Profile Header Card */
.profile-header-card {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-radius: var(--radius-xl);
    padding: 2rem;
    margin-bottom: 2rem;
    color: white;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(99, 102, 241, 0.3);
}

.profile-header-card::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, transparent 70%);
    transform: rotate(30deg);
}

.profile-header-content {
    display: flex;
    align-items: center;
    gap: 2rem;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
}

.profile-avatar-large {
    width: 100px;
    height: 100px;
    background: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    font-weight: 700;
    color: var(--primary);
    box-shadow: 0 10px 20px rgba(0,0,0,0.2);
    border: 4px solid rgba(255,255,255,0.3);
}

.profile-info-large h3 {
    font-size: 1.75rem;
    margin-bottom: 0.25rem;
    color: white;
}

.profile-info-large p {
    color: rgba(255,255,255,0.9);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.profile-info-large p i {
    font-size: 1rem;
}

.profile-badge {
    background: rgba(255,255,255,0.2);
    padding: 0.35rem 1rem;
    border-radius: 2rem;
    font-size: 0.8rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    backdrop-filter: blur(5px);
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    border-radius: var(--radius-lg);
    padding: 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: var(--shadow);
    border: 1px solid var(--gray-light);
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-lg);
}

.stat-icon {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));
    border-radius: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    font-size: 1.25rem;
}

.stat-content h4 {
    font-size: 0.875rem;
    color: var(--gray);
    margin-bottom: 0.25rem;
}

.stat-content .stat-value {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--dark);
}

/* Cards */
.card {
    background: white;
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow);
    border: 1px solid var(--gray-light);
    margin-bottom: 1.5rem;
    overflow: hidden;
}

.card-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 2px solid var(--gray-light);
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.card-header h3 {
    font-size: 1.2rem;
    margin: 0;
    color: var(--dark);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.card-header h3 i {
    color: var(--primary);
}

.card-body {
    padding: 1.5rem;
}

/* Form Styles */
.form-row {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
    margin-bottom: 1rem;
}

@media (max-width: 640px) {
    .form-row {
        grid-template-columns: 1fr;
    }
}

.form-group {
    margin-bottom: 1.25rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: var(--dark);
    font-size: 0.9rem;
}

.input-group {
    display: flex;
    align-items: center;
    border: 2px solid var(--gray-light);
    border-radius: var(--radius-lg);
    overflow: hidden;
    transition: all 0.3s ease;
    background: white;
}

.input-group:focus-within {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.input-group-text {
    padding: 0.875rem 1.25rem;
    background: var(--light);
    color: var(--gray-dark);
    font-weight: 500;
    border-right: 2px solid var(--gray-light);
}

.form-control {
    flex: 1;
    padding: 0.875rem 1.25rem;
    border: none;
    outline: none;
    font-size: 1rem;
    background: transparent;
    width: 100%;
}

textarea.form-control {
    min-height: 100px;
    resize: vertical;
}

.form-text {
    font-size: 0.8rem;
    color: var(--gray);
    margin-top: 0.25rem;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

/* Referral Code Display */
.referral-code {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    padding: 1.5rem;
    border-radius: var(--radius-lg);
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
}

.referral-code-left h4 {
    color: white;
    margin-bottom: 0.25rem;
    font-size: 1rem;
    opacity: 0.9;
}

.referral-code-value {
    font-size: 1.5rem;
    font-weight: 700;
    letter-spacing: 2px;
    font-family: monospace;
}

.referral-code-right {
    display: flex;
    gap: 0.5rem;
}

/* Buttons */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.875rem 1.5rem;
    font-size: 1rem;
    font-weight: 500;
    border-radius: var(--radius-lg);
    cursor: pointer;
    transition: all 0.3s ease;
    border: none;
    outline: none;
    text-decoration: none;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(99, 102, 241, 0.4);
}

.btn-light {
    background: white;
    color: var(--primary);
    border: 2px solid var(--gray-light);
}

.btn-light:hover {
    background: var(--light);
    border-color: var(--primary);
    transform: translateY(-2px);
}

.btn-small {
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
}

.btn-block {
    width: 100%;
}

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Alerts */
.alert {
    padding: 1rem 1.25rem;
    border-radius: var(--radius-lg);
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    animation: slideIn 0.3s ease;
}

.alert-success {
    background: #d1fae5;
    color: #065f46;
    border-left: 4px solid var(--success);
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border-left: 4px solid var(--danger);
}

.alert i {
    font-size: 1.25rem;
}

/* Badge */
.badge {
    display: inline-flex;
    align-items: center;
    padding: 0.35rem 1rem;
    border-radius: 2rem;
    font-size: 0.8rem;
    font-weight: 600;
}

.badge-active {
    background: #d1fae5;
    color: #065f46;
}

/* Avatar */
.user-avatar {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 1.5rem;
    box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
}

/* Password Strength Meter */
.password-strength {
    margin-top: 0.5rem;
    height: 4px;
    background: var(--gray-light);
    border-radius: 2px;
    overflow: hidden;
}

.password-strength-bar {
    height: 100%;
    width: 0%;
    transition: all 0.3s ease;
}

.strength-weak {
    background: var(--danger);
    width: 25%;
}

.strength-fair {
    background: var(--warning);
    width: 50%;
}

.strength-good {
    background: var(--info);
    width: 75%;
}

.strength-strong {
    background: var(--success);
    width: 100%;
}

/* Animations */
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.card {
    animation: fadeInUp 0.5s ease forwards;
}

/* Main Content Padding */
.main-content {
    padding-bottom: calc(var(--bottom-nav-height) + 1.5rem);
}

/* Responsive */
@media (max-width: 768px) {
    .profile-header-content {
        flex-direction: column;
        text-align: center;
    }
    
    .profile-info-large {
        text-align: center;
    }
    
    .profile-info-large p {
        justify-content: center;
    }
    
    .referral-code {
        flex-direction: column;
        text-align: center;
    }
    
    .referral-code-right {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .card-header {
        flex-direction: column;
        align-items: flex-start;
    }
}

/* Touch-friendly */
@media (max-width: 768px) {
    .btn, 
    .input-group-text,
    .form-control {
        min-height: 48px;
    }
}
</style>

<!-- Main Content -->
<div class="main-content">
    <div class="container">
        <!-- Page Header -->
        <div class="content-header">
            <h2>
                <i class="fas fa-user-cog"></i>
                <span>My Profile</span>
            </h2>
        </div>

        <!-- Alerts -->
        <?php if ($error = Session::getError()): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>
        
        <?php if ($success = Session::getSuccess()): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>

        <!-- Profile Header Card -->
        <div class="profile-header-card">
            <div class="profile-header-content">
                <div class="profile-avatar-large">
                    <?php echo strtoupper(substr($user['first_name'] ?: $user['username'], 0, 1)); ?>
                </div>
                <div class="profile-info-large">
                    <h3><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h3>
                    <p>
                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?>
                        <span class="profile-badge">
                            <i class="fas fa-calendar"></i> Member since <?php echo date('M Y', strtotime($user['created_at'])); ?>
                        </span>
                    </p>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-exchange-alt"></i>
                </div>
                <div class="stat-content">
                    <h4>Transactions</h4>
                    <div class="stat-value"><?php echo number_format($stats['total_transactions'] ?? 0); ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-wallet"></i>
                </div>
                <div class="stat-content">
                    <h4>Total Spent</h4>
                    <div class="stat-value"><?php echo format_money($stats['total_spent'] ?? 0); ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-gift"></i>
                </div>
                <div class="stat-content">
                    <h4>Referrals</h4>
                    <div class="stat-value">0</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h4>Last Login</h4>
                    <div class="stat-value"><?php echo $user['last_login'] ? date('M d', strtotime($user['last_login'])) : 'N/A'; ?></div>
                </div>
            </div>
        </div>

        <!-- Referral Code Section -->
        <div class="referral-code">
            <div class="referral-code-left">
                <h4><i class="fas fa-gift"></i> Your Referral Code</h4>
                <div class="referral-code-value"><?php echo $user['referral_code']; ?></div>
            </div>
            <div class="referral-code-right">
                <button class="btn btn-light" onclick="copyToClipboard('<?php echo $user['referral_code']; ?>')">
                    <i class="fas fa-copy"></i> Copy Code
                </button>
                <a href="referrals.php" class="btn btn-light">
                    <i class="fas fa-users"></i> View Referrals
                </a>
            </div>
        </div>

        <!-- Profile Information Card -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-id-card"></i> Personal Information</h3>
                <span class="badge badge-active">
                    <i class="fas fa-check-circle"></i> <?php echo ucfirst($user['status']); ?>
                </span>
            </div>
            
            <div class="card-body">
                <form method="POST" id="profileForm">
                    <input type="hidden" name="csrf_token" value="<?php echo Session::generateCSRF(); ?>">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">
                                <i class="fas fa-user"></i> First Name
                            </label>
                            <input type="text" class="form-control" id="first_name" name="first_name" 
                                   value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>"
                                   placeholder="Enter your first name">
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name">
                                <i class="fas fa-user"></i> Last Name
                            </label>
                            <input type="text" class="form-control" id="last_name" name="last_name" 
                                   value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>"
                                   placeholder="Enter your last name">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="username">
                            <i class="fas fa-at"></i> Username
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user-tag"></i></span>
                            <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" readonly disabled style="background: var(--light);">
                        </div>
                        <div class="form-text">
                            <i class="fas fa-info-circle"></i> Username cannot be changed
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">
                            <i class="fas fa-envelope"></i> Email Address
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" class="form-control" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" readonly disabled style="background: var(--light);">
                        </div>
                        <div class="form-text">
                            <i class="fas fa-info-circle"></i> Email cannot be changed
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">
                            <i class="fas fa-phone"></i> Phone Number
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-phone-alt"></i></span>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                   placeholder="08012345678" maxlength="11" inputmode="numeric">
                        </div>
                        <div class="form-text">
                            <i class="fas fa-info-circle"></i> Enter 11-digit phone number
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="address">
                            <i class="fas fa-map-marker-alt"></i> Address
                        </label>
                        <textarea class="form-control" id="address" name="address" rows="2"
                                  placeholder="Enter your address"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="city">
                                <i class="fas fa-city"></i> City
                            </label>
                            <input type="text" class="form-control" id="city" name="city" 
                                   value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>"
                                   placeholder="Enter your city">
                        </div>
                        
                        <div class="form-group">
                            <label for="state">
                                <i class="fas fa-map"></i> State
                            </label>
                            <input type="text" class="form-control" id="state" name="state" 
                                   value="<?php echo htmlspecialchars($user['state'] ?? ''); ?>"
                                   placeholder="Enter your state">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block" id="updateProfileBtn">
                        <i class="fas fa-save"></i> Update Profile
                    </button>
                </form>
            </div>
        </div>

        <!-- Change Password Card -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-lock"></i> Change Password</h3>
            </div>
            
            <div class="card-body">
                <form method="POST" id="passwordForm">
                    <input type="hidden" name="csrf_token" value="<?php echo Session::generateCSRF(); ?>">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="form-group">
                        <label for="current_password">
                            <i class="fas fa-lock"></i> Current Password
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-key"></i></span>
                            <input type="password" class="form-control" id="current_password" name="current_password" 
                                   placeholder="Enter current password" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">
                            <i class="fas fa-lock"></i> New Password
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-key"></i></span>
                            <input type="password" class="form-control" id="new_password" name="new_password" 
                                   placeholder="Enter new password" required>
                        </div>
                        <div class="password-strength">
                            <div class="password-strength-bar" id="passwordStrength"></div>
                        </div>
                        <div class="form-text">
                            <i class="fas fa-info-circle"></i> Minimum 8 characters with uppercase, lowercase and number
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">
                            <i class="fas fa-check"></i> Confirm New Password
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-check-circle"></i></span>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                   placeholder="Confirm new password" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block" id="changePasswordBtn">
                        <i class="fas fa-sync-alt"></i> Change Password
                    </button>
                </form>
            </div>
        </div>

        <!-- Account Information Card -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-info-circle"></i> Account Information</h3>
            </div>
            
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem;">
                    <div>
                        <small style="color: var(--gray);">Member Since</small>
                        <p style="font-weight: 600;"><?php echo date('F d, Y', strtotime($user['created_at'])); ?></p>
                    </div>
                    <div>
                        <small style="color: var(--gray);">Last Login</small>
                        <p style="font-weight: 600;"><?php echo $user['last_login'] ? date('F d, Y h:i A', strtotime($user['last_login'])) : 'Never'; ?></p>
                    </div>
                    <div>
                        <small style="color: var(--gray);">Last IP</small>
                        <p style="font-weight: 600;"><?php echo $user['last_ip'] ?? 'N/A'; ?></p>
                    </div>
                    <div>
                        <small style="color: var(--gray);">Account Status</small>
                        <p><span class="badge badge-active"><?php echo ucfirst($user['status']); ?></span></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bottom Navigation -->
<nav class="bottom-nav">
    <a href="dashboard.php" class="bottom-nav-item">
        <i class="fas fa-home"></i>
        <span>Home</span>
    </a>
    <a href="wallet.php" class="bottom-nav-item">
        <i class="fas fa-wallet"></i>
        <span>Wallet</span>
    </a>
    <a href="services.php" class="bottom-nav-item">
        <i class="fas fa-th-large"></i>
        <span>Services</span>
    </a>
    <a href="transactions.php" class="bottom-nav-item">
        <i class="fas fa-history"></i>
        <span>History</span>
    </a>
    <a href="profile.php" class="bottom-nav-item active">
        <i class="fas fa-user"></i>
        <span>Profile</span>
    </a>
</nav>

<script>
// Copy to clipboard function
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showNotification('Referral code copied to clipboard!', 'success');
    }).catch(() => {
        // Fallback
        const textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        showNotification('Referral code copied to clipboard!', 'success');
    });
}

// Show notification
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type}`;
    notification.style.position = 'fixed';
    notification.style.top = '20px';
    notification.style.right = '20px';
    notification.style.zIndex = '9999';
    notification.style.maxWidth = '300px';
    notification.style.boxShadow = '0 4px 6px rgba(0,0,0,0.1)';
    notification.innerHTML = `
        <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-info-circle'}"></i>
        <span>${message}</span>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.transition = 'opacity 0.5s';
        notification.style.opacity = '0';
        setTimeout(() => notification.remove(), 500);
    }, 3000);
}

// Phone number formatting
document.getElementById('phone').addEventListener('input', function(e) {
    this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11);
});

// Password strength meter
document.getElementById('new_password').addEventListener('input', function() {
    const password = this.value;
    const strengthBar = document.getElementById('passwordStrength');
    
    let strength = 0;
    
    if (password.length >= 8) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[a-z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    
    strengthBar.className = 'password-strength-bar';
    
    if (strength <= 1) {
        strengthBar.classList.add('strength-weak');
    } else if (strength === 2) {
        strengthBar.classList.add('strength-fair');
    } else if (strength === 3) {
        strengthBar.classList.add('strength-good');
    } else if (strength === 4) {
        strengthBar.classList.add('strength-strong');
    }
});

// Form submission handling
document.getElementById('profileForm').addEventListener('submit', function(e) {
    const btn = document.getElementById('updateProfileBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
});

document.getElementById('passwordForm').addEventListener('submit', function(e) {
    const btn = document.getElementById('changePasswordBtn');
    const newPass = document.getElementById('new_password').value;
    const confirmPass = document.getElementById('confirm_password').value;
    
    if (newPass !== confirmPass) {
        e.preventDefault();
        alert('New password and confirm password do not match!');
        return false;
    }
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Changing...';
});

// Animation on scroll
const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
};

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.style.opacity = '1';
            entry.target.style.transform = 'translateY(0)';
        }
    });
}, observerOptions);

document.querySelectorAll('.card, .stat-card').forEach(el => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(20px)';
    el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
    observer.observe(el);
});

// Touch-friendly hover effects
if ('ontouchstart' in window) {
    document.querySelectorAll('.stat-card, .btn').forEach(el => {
        el.addEventListener('touchstart', function() {
            this.style.transform = 'scale(0.98)';
        });
        el.addEventListener('touchend', function() {
            this.style.transform = '';
        });
    });
}
</script>

</body>
</html>