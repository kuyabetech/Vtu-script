<?php
// admin/admin/users.php - User Management
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

Auth::requireAdmin();

$db = db();
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !Session::verifyCSRF($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_user') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'user';
        $status = $_POST['status'] ?? 'active';
        
        // Validation
        $errors = [];
        
        $check = $db->query("SELECT id FROM users WHERE username = '$username'");
        if ($check->num_rows > 0) $errors[] = 'Username already exists';
        
        $check = $db->query("SELECT id FROM users WHERE email = '$email'");
        if ($check->num_rows > 0) $errors[] = 'Email already exists';
        
        $check = $db->query("SELECT id FROM users WHERE phone = '$phone'");
        if ($check->num_rows > 0) $errors[] = 'Phone already exists';
        
        if (empty($errors)) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $referral = strtoupper(substr(md5(uniqid()), 0, 8));
            
            $stmt = $db->prepare("INSERT INTO users (username, email, phone, password, role, status, referral_code) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssss", $username, $email, $phone, $hashed, $role, $status, $referral);
            
            if ($stmt->execute()) {
                $userId = $stmt->insert_id;
                $db->query("INSERT INTO wallets (user_id, balance) VALUES ($userId, 0)");
                Session::setSuccess('User added successfully');
            } else {
                Session::setError('Failed to add user');
            }
        } else {
            foreach ($errors as $e) Session::setError($e);
        }
        redirect('admin/users.php');
    }
    
    elseif ($action === 'update_user') {
        $id = (int)$_POST['id'];
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $role = $_POST['role'] ?? 'user';
        $status = $_POST['status'] ?? 'active';
        
        $stmt = $db->prepare("UPDATE users SET email = ?, phone = ?, role = ?, status = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $email, $phone, $role, $status, $id);
        
        if ($stmt->execute()) {
            Session::setSuccess('User updated successfully');
        } else {
            Session::setError('Failed to update user');
        }
        redirect('admin/users.php');
    }
    
    elseif ($action === 'delete_user') {
        $id = (int)$_POST['id'];
        if ($id != 1) { // Prevent deleting main admin
            $db->query("DELETE FROM users WHERE id = $id");
            Session::setSuccess('User deleted successfully');
        } else {
            Session::setError('Cannot delete main admin');
        }
        redirect('admin/users.php');
    }
}

// Get users
$search = isset($_GET['search']) ? $db->real_escape_string($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$where = $search ? "WHERE username LIKE '%$search%' OR email LIKE '%$search%' OR phone LIKE '%$search%'" : '';
$total = $db->query("SELECT COUNT(*) as count FROM users $where")->fetch_assoc()['count'];
$pages = ceil($total / $limit);

$users = $db->query("SELECT * FROM users $where ORDER BY id DESC LIMIT $offset, $limit")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Manage Users';
include 'admin_header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="content-header">
        <h2><i class="fas fa-users"></i> Manage Users</h2>
        <p>Manage and monitor all platform users</p>
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

    <!-- Search Section -->
    <div class="search-section">
        <div class="search-wrapper">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search by username, email or phone..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <button class="btn-add" onclick="showAddModal()">
                <i class="fas fa-plus"></i> Add New User
            </button>
        </div>
    </div>

    <!-- Table View (Desktop) -->
    <div class="table-container table-view">
        <div class="table-header">
            <h3>
                <i class="fas fa-users"></i>
                All Users
                <span class="text-muted" style="margin-left: 0.5rem;">(<?php echo $total; ?> total)</span>
            </h3>
        </div>
        
        <div class="table-responsive">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Balance</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($users) > 0): ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <div class="user-cell">
                                        <div class="user-avatar">
                                            <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                        </div>
                                        <div class="user-info">
                                            <span class="user-name"><?php echo htmlspecialchars($user['username']); ?></span>
                                            <span class="user-id">ID: <?php echo $user['id']; ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="email-cell">
                                    <i class="fas fa-envelope"></i>
                                    <?php echo htmlspecialchars($user['email']); ?>
                                </td>
                                <td class="phone-cell">
                                    <i class="fas fa-phone"></i>
                                    <?php echo htmlspecialchars($user['phone']); ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $user['role']; ?>">
                                        <i class="fas <?php echo $user['role'] == 'admin' ? 'fa-shield-alt' : 'fa-user'; ?>"></i>
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $user['status']; ?>">
                                        <i class="fas <?php echo $user['status'] == 'active' ? 'fa-check-circle' : ($user['status'] == 'pending' ? 'fa-clock' : 'fa-ban'); ?>"></i>
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td class="balance-cell">
                                    <?php echo format_money($user['wallet_balance']); ?>
                                </td>
                                <td class="date-cell">
                                    <i class="fas fa-calendar-alt"></i>
                                    <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-icon btn-edit" onclick='editUser(<?php echo json_encode($user); ?>)'>
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($user['id'] != 1): ?>
                                            <button class="btn-icon btn-delete" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo addslashes($user['username']); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <div class="empty-icon">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <h4>No users found</h4>
                                    <p>Click "Add New User" to create your first user</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($pages > 1): ?>
            <div class="pagination-wrapper">
                <div class="pagination-info">
                    Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total); ?> of <?php echo $total; ?> users
                </div>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
                           class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Card View (Mobile) -->
    <div class="user-cards-view" id="userCardsView">
        <?php if (count($users) > 0): ?>
            <?php foreach ($users as $user): ?>
                <div class="user-card">
                    <div class="user-card-header">
                        <div class="user-card-avatar">
                            <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                        </div>
                        <div class="user-card-info">
                            <div class="user-card-name"><?php echo htmlspecialchars($user['username']); ?></div>
                            <div class="user-card-id">ID: <?php echo $user['id']; ?></div>
                        </div>
                    </div>
                    
                    <div class="user-card-body">
                        <div class="user-card-field">
                            <span class="user-card-field-label">
                                <i class="fas fa-envelope"></i> Email
                            </span>
                            <span class="user-card-field-value"><?php echo htmlspecialchars($user['email']); ?></span>
                        </div>
                        <div class="user-card-field">
                            <span class="user-card-field-label">
                                <i class="fas fa-phone"></i> Phone
                            </span>
                            <span class="user-card-field-value"><?php echo htmlspecialchars($user['phone']); ?></span>
                        </div>
                        <div class="user-card-field">
                            <span class="user-card-field-label">
                                <i class="fas fa-tag"></i> Role
                            </span>
                            <span class="user-card-field-value">
                                <span class="badge badge-<?php echo $user['role']; ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </span>
                        </div>
                        <div class="user-card-field">
                            <span class="user-card-field-label">
                                <i class="fas fa-circle"></i> Status
                            </span>
                            <span class="user-card-field-value">
                                <span class="badge badge-<?php echo $user['status']; ?>">
                                    <?php echo ucfirst($user['status']); ?>
                                </span>
                            </span>
                        </div>
                        <div class="user-card-field">
                            <span class="user-card-field-label">
                                <i class="fas fa-wallet"></i> Balance
                            </span>
                            <span class="user-card-field-value"><?php echo format_money($user['wallet_balance']); ?></span>
                        </div>
                        <div class="user-card-field">
                            <span class="user-card-field-label">
                                <i class="fas fa-calendar"></i> Joined
                            </span>
                            <span class="user-card-field-value"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></span>
                        </div>
                    </div>
                    
                    <div class="user-card-actions">
                        <button class="btn-icon btn-edit" onclick='editUser(<?php echo json_encode($user); ?>)'>
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <?php if ($user['id'] != 1): ?>
                            <button class="btn-icon btn-delete" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo addslashes($user['username']); ?>')">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h4>No users found</h4>
                <p>Click "Add New User" to create your first user</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add User Modal -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>
                <i class="fas fa-user-plus"></i>
                Add New User
            </h3>
            <button type="button" class="modal-close" onclick="hideAddModal()">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo Session::generateCSRF(); ?>">
            <input type="hidden" name="action" value="add_user">
            
            <div class="modal-body">
                <div class="form-group">
                    <label>Username *</label>
                    <input type="text" name="username" class="form-control" placeholder="Enter username" required>
                </div>
                <div class="form-group">
                    <label>Email Address *</label>
                    <input type="email" name="email" class="form-control" placeholder="user@example.com" required>
                </div>
                <div class="form-group">
                    <label>Phone Number *</label>
                    <input type="text" name="phone" class="form-control" placeholder="+1234567890" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <div class="password-wrapper">
                        <input type="text" name="password" id="generatedPassword" class="form-control" value="<?php echo generate_string(8); ?>" required>
                        <button type="button" class="btn-copy" onclick="copyPassword()">
                            <i class="fas fa-copy"></i> Copy
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label>User Role</label>
                    <select name="role" class="form-control">
                        <option value="user">👤 Regular User</option>
                        <option value="admin">👑 Administrator</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Account Status</label>
                    <select name="status" class="form-control">
                        <option value="active">✅ Active</option>
                        <option value="suspended">⛔ Suspended</option>
                        <option value="pending">⏳ Pending</option>
                    </select>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-light" onclick="hideAddModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Create User</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>
                <i class="fas fa-user-edit"></i>
                Edit User
            </h3>
            <button type="button" class="modal-close" onclick="hideEditModal()">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo Session::generateCSRF(); ?>">
            <input type="hidden" name="action" value="update_user">
            <input type="hidden" name="id" id="edit_id">
            
            <div class="modal-body">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" id="edit_username" class="form-control" readonly>
                </div>
                <div class="form-group">
                    <label>Email Address *</label>
                    <input type="email" name="email" id="edit_email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Phone Number *</label>
                    <input type="text" name="phone" id="edit_phone" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>User Role</label>
                    <select name="role" id="edit_role" class="form-control">
                        <option value="user">👤 Regular User</option>
                        <option value="admin">👑 Administrator</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Account Status</label>
                    <select name="status" id="edit_status" class="form-control">
                        <option value="active">✅ Active</option>
                        <option value="suspended">⛔ Suspended</option>
                        <option value="pending">⏳ Pending</option>
                    </select>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-light" onclick="hideEditModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Form -->
<form method="POST" id="deleteForm" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?php echo Session::generateCSRF(); ?>">
    <input type="hidden" name="action" value="delete_user">
    <input type="hidden" name="id" id="delete_id">
</form>

<style>
/* ==========================================================================
   COMPLETE USERS PAGE STYLES
   ========================================================================== */

/* Alert Styles */
.alert {
    padding: 1rem 1.25rem;
    border-radius: 0.75rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    animation: slideIn 0.3s ease;
}

.alert-success {
    background: #d1fae5;
    color: #065f46;
    border-left: 4px solid #10b981;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border-left: 4px solid #ef4444;
}

/* Search Section */
.search-section {
    margin-bottom: 1.5rem;
}

.search-wrapper {
    display: flex;
    gap: 1rem;
    align-items: center;
    flex-wrap: wrap;
}

.search-box {
    flex: 1;
    position: relative;
    min-width: 280px;
}

.search-box i {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: #9ca3af;
}

.search-box input {
    width: 100%;
    padding: 0.75rem 1rem 0.75rem 2.5rem;
    border: 1.5px solid #e5e7eb;
    border-radius: 0.75rem;
    font-size: 0.875rem;
    transition: all 0.2s;
}

.search-box input:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.btn-add {
    padding: 0.75rem 1.5rem;
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    color: white;
    border: none;
    border-radius: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s;
}

.btn-add:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

/* Table Container */
.table-container {
    background: white;
    border-radius: 1rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    margin-bottom: 1.5rem;
}

.table-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid #e5e7eb;
    background: #f9fafb;
}

.table-header h3 {
    font-size: 1rem;
    font-weight: 600;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* Table */
.table-responsive {
    overflow-x: auto;
}

.modern-table {
    width: 100%;
    border-collapse: collapse;
}

.modern-table th {
    padding: 1rem 1.25rem;
    text-align: left;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    color: #6b7280;
    border-bottom: 1px solid #e5e7eb;
    background: #f9fafb;
}

.modern-table td {
    padding: 1rem 1.25rem;
    font-size: 0.875rem;
    border-bottom: 1px solid #f3f4f6;
}

.modern-table tbody tr:hover {
    background: #f9fafb;
}

/* User Cell */
.user-cell {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.user-avatar {
    width: 36px;
    height: 36px;
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    border-radius: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
}

.user-info {
    display: flex;
    flex-direction: column;
}

.user-name {
    font-weight: 600;
    color: #111827;
}

.user-id {
    font-size: 0.7rem;
    color: #9ca3af;
}

/* Badges */
.badge {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.7rem;
    font-weight: 600;
}

.badge-admin {
    background: #dbeafe;
    color: #1e40af;
}

.badge-user {
    background: #f3f4f6;
    color: #374151;
}

.badge-active {
    background: #d1fae5;
    color: #065f46;
}

.badge-pending {
    background: #fef3c7;
    color: #92400e;
}

.badge-suspended {
    background: #fee2e2;
    color: #991b1b;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 0.5rem;
}

.btn-icon {
    width: 32px;
    height: 32px;
    border-radius: 0.5rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-edit {
    background: #f3f4f6;
    color: #374151;
}

.btn-edit:hover {
    background: #6366f1;
    color: white;
}

.btn-delete {
    background: #fee2e2;
    color: #dc2626;
}

.btn-delete:hover {
    background: #dc2626;
    color: white;
}

/* Pagination */
.pagination-wrapper {
    padding: 1rem 1.5rem;
    border-top: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.pagination-info {
    font-size: 0.75rem;
    color: #6b7280;
}

.pagination {
    display: flex;
    gap: 0.375rem;
    flex-wrap: wrap;
}

.page-link {
    padding: 0.375rem 0.75rem;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    color: #374151;
    font-size: 0.75rem;
    text-decoration: none;
    transition: all 0.2s;
}

.page-link:hover {
    background: #f3f4f6;
    border-color: #6366f1;
    color: #6366f1;
}

.page-link.active {
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    border-color: transparent;
    color: white;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(4px);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: white;
    width: 90%;
    max-width: 500px;
    border-radius: 1rem;
    overflow: hidden;
    animation: slideUp 0.3s ease;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}

.modal-header {
    padding: 1.25rem 1.5rem;
    background: #f9fafb;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.modal-header h3 {
    font-size: 1.125rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0;
}

.modal-header h3 i {
    color: #6366f1;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #6b7280;
    transition: all 0.2s;
    line-height: 1;
}

.modal-close:hover {
    color: #ef4444;
    transform: rotate(90deg);
}

.modal-body {
    padding: 1.5rem;
    max-height: 60vh;
    overflow-y: auto;
}

.modal-footer {
    padding: 1rem 1.5rem;
    background: #f9fafb;
    border-top: 1px solid #e5e7eb;
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
}

/* Form Styles */
.form-group {
    margin-bottom: 1.25rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    font-size: 0.75rem;
    text-transform: uppercase;
    color: #6b7280;
}

.form-control {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 1.5px solid #e5e7eb;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    transition: all 0.2s;
}

.form-control:focus {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.form-control[readonly] {
    background: #f9fafb;
    cursor: default;
}

.password-wrapper {
    display: flex;
    gap: 0.5rem;
}

.password-wrapper .form-control {
    flex: 1;
}

.btn-copy {
    padding: 0 1rem;
    background: #f3f4f6;
    border: 1.5px solid #e5e7eb;
    border-radius: 0.5rem;
    cursor: pointer;
    font-size: 0.75rem;
    font-weight: 500;
    transition: all 0.2s;
    white-space: nowrap;
}

.btn-copy:hover {
    background: #6366f1;
    border-color: #6366f1;
    color: white;
}

.btn {
    padding: 0.625rem 1.25rem;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    border: none;
    transition: all 0.2s;
}

.btn-primary {
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.btn-light {
    background: #f3f4f6;
    color: #374151;
    border: 1px solid #e5e7eb;
}

.btn-light:hover {
    background: #e5e7eb;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem;
}

.empty-icon {
    width: 80px;
    height: 80px;
    background: #f3f4f6;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1rem;
}

.empty-icon i {
    font-size: 2rem;
    color: #9ca3af;
}

.empty-state h4 {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.empty-state p {
    font-size: 0.875rem;
    color: #6b7280;
}

/* Card View (Mobile) */
.user-cards-view {
    display: none;
    grid-template-columns: 1fr;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.user-card {
    background: white;
    border-radius: 1rem;
    border: 1px solid #e5e7eb;
    padding: 1rem;
    transition: all 0.2s;
}

.user-card:hover {
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.user-card-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #e5e7eb;
}

.user-card-avatar {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    border-radius: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 1.125rem;
}

.user-card-info {
    flex: 1;
}

.user-card-name {
    font-weight: 700;
    font-size: 1rem;
    margin-bottom: 0.25rem;
}

.user-card-id {
    font-size: 0.7rem;
    color: #9ca3af;
}

.user-card-body {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.user-card-field {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.user-card-field-label {
    font-size: 0.65rem;
    text-transform: uppercase;
    font-weight: 600;
    color: #6b7280;
}

.user-card-field-value {
    font-size: 0.875rem;
    color: #111827;
    font-weight: 500;
    word-break: break-word;
}

.user-card-actions {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
    padding-top: 0.75rem;
    border-top: 1px solid #e5e7eb;
}

/* Animations */
@keyframes slideUp {
    from {
        transform: translateY(30px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

@keyframes slideIn {
    from {
        transform: translateX(-20px);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

/* Responsive */
@media (max-width: 1024px) {
    .modern-table th:nth-child(4),
    .modern-table td:nth-child(4),
    .modern-table th:nth-child(7),
    .modern-table td:nth-child(7) {
        display: none;
    }
}

@media (max-width: 768px) {
    .modern-table th:nth-child(3),
    .modern-table td:nth-child(3) {
        display: none;
    }
    
    .search-wrapper {
        flex-direction: column;
        align-items: stretch;
    }
    
    .pagination-wrapper {
        flex-direction: column;
        text-align: center;
    }
}

@media (max-width: 640px) {
    .table-view {
        display: none;
    }
    
    .user-cards-view {
        display: grid;
    }
    
    .user-card-body {
        grid-template-columns: 1fr;
    }
    
    .user-card-field {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
    }
    
    .user-card-actions {
        flex-direction: column;
    }
    
    .user-card-actions .btn-icon {
        width: 100%;
        justify-content: center;
        padding: 0.5rem;
    }
}

.text-muted {
    color: #6b7280;
}
</style>

<script>
// Modal Functions
function showAddModal() {
    document.getElementById('addModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function hideAddModal() {
    document.getElementById('addModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

function editUser(user) {
    document.getElementById('edit_id').value = user.id;
    document.getElementById('edit_username').value = user.username;
    document.getElementById('edit_email').value = user.email;
    document.getElementById('edit_phone').value = user.phone;
    document.getElementById('edit_role').value = user.role;
    document.getElementById('edit_status').value = user.status;
    document.getElementById('editModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function hideEditModal() {
    document.getElementById('editModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

function deleteUser(id, username) {
    if (confirm('⚠️ Are you sure you want to delete user "' + username + '"?\n\nThis action cannot be undone!')) {
        document.getElementById('delete_id').value = id;
        document.getElementById('deleteForm').submit();
    }
}

function copyPassword() {
    const passwordInput = document.getElementById('generatedPassword');
    passwordInput.select();
    passwordInput.setSelectionRange(0, 99999);
    document.execCommand('copy');
    
    const copyBtn = document.querySelector('.btn-copy');
    const originalHTML = copyBtn.innerHTML;
    copyBtn.innerHTML = '<i class="fas fa-check"></i> Copied!';
    setTimeout(() => {
        copyBtn.innerHTML = originalHTML;
    }, 2000);
}

// Close modals on click outside
window.onclick = function(event) {
    const addModal = document.getElementById('addModal');
    const editModal = document.getElementById('editModal');
    
    if (event.target === addModal) {
        hideAddModal();
    }
    if (event.target === editModal) {
        hideEditModal();
    }
}

// Live search with debounce
let searchTimeout;
const searchInput = document.getElementById('searchInput');
if (searchInput) {
    searchInput.addEventListener('input', function(e) {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            const searchValue = e.target.value;
            const url = new URL(window.location.href);
            if (searchValue) {
                url.searchParams.set('search', searchValue);
            } else {
                url.searchParams.delete('search');
            }
            url.searchParams.set('page', '1');
            window.location.href = url.toString();
        }, 300);
    });
}

// Close modals with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        hideAddModal();
        hideEditModal();
    }
});
</script>

<?php include 'admin_footer.php'; ?>