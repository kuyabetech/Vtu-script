<?php
// admin/funding_requests.php - Admin Funding Management
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/funding_functions.php';
require_once '../includes/settings.php';

// Check if user is admin
if (!Session::isAdmin()) {
    redirect('../user/dashboard.php');
}

$db = db();
$adminId = Session::userId();
$fundingManager = new FundingManager($db);

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Session::verifyCSRF($_POST['csrf_token'] ?? '')) {
        Session::setError('Invalid CSRF token');
    } else {
        if (isset($_POST['approve_request'])) {
            $requestId = intval($_POST['request_id']);
            $notes = $_POST['admin_notes'] ?? '';
            $result = $fundingManager->approveFundingRequest($requestId, $adminId, $notes);
            Session::setSuccess($result['message']);
        } elseif (isset($_POST['reject_request'])) {
            $requestId = intval($_POST['request_id']);
            $reason = $_POST['rejection_reason'] ?? 'No reason provided';
            $result = $fundingManager->rejectFundingRequest($requestId, $adminId, $reason);
            Session::setSuccess($result['message']);
        } elseif (isset($_POST['update_settings'])) {
            // Update settings using the Settings class
            Settings::set('min_funding_amount', floatval($_POST['min_funding_amount']), $adminId);
            Settings::set('max_funding_amount', floatval($_POST['max_funding_amount']), $adminId);
            Settings::set('auto_approve_under', floatval($_POST['auto_approve_under']), $adminId);
            Settings::set('funding_enabled', isset($_POST['funding_enabled']) ? 1 : 0, $adminId);
            Settings::set('funding_instructions', $_POST['funding_instructions'], $adminId);
            Settings::set('bank_name', $_POST['bank_name'], $adminId);
            Settings::set('bank_account_number', $_POST['bank_account_number'], $adminId);
            Settings::set('bank_account_name', $_POST['bank_account_name'], $adminId);
            Settings::set('payment_note', $_POST['payment_note'], $adminId);
            
            Session::setSuccess('Settings updated successfully');
        }
    }
    redirect('funding_requests.php');
}

// Get data
$pendingRequests = $fundingManager->getPendingRequests(100);
$allRequests = $fundingManager->getAllFundingRequests(null, 100);
$stats = $fundingManager->getFundingStats();

// Get current settings
$minAmount = Settings::get('min_funding_amount', 100);
$maxAmount = Settings::get('max_funding_amount', 1000000);
$autoApprove = Settings::get('auto_approve_under', 0);
$fundingEnabled = Settings::get('funding_enabled', true);
$fundingInstructions = Settings::get('funding_instructions', '');
$bankName = Settings::get('bank_name', '');
$bankAccountNumber = Settings::get('bank_account_number', '');
$bankAccountName = Settings::get('bank_account_name', '');
$paymentNote = Settings::get('payment_note', '');

$pageTitle = 'Funding Requests - Admin';
include 'admin_header.php';
?>


<style>
.funding-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    padding: 1.5rem;
    border-radius: 1rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.stat-card h3 {
    font-size: 0.875rem;
    color: #6b7280;
    margin-bottom: 0.5rem;
}

.stat-card .value {
    font-size: 1.875rem;
    font-weight: bold;
    color: #1f2937;
}

.stat-card .small {
    font-size: 0.75rem;
    color: #6b7280;
    margin-top: 0.5rem;
}

.request-card {
    background: white;
    border-radius: 1rem;
    margin-bottom: 1rem;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.request-header {
    padding: 1rem;
    background: #f9fafb;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.request-body {
    padding: 1rem;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
}

.proof-preview {
    max-width: 200px;
    cursor: pointer;
    border-radius: 0.5rem;
}

.status-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 2rem;
    font-size: 0.75rem;
    font-weight: 600;
}

.status-pending { background: #fef3c7; color: #92400e; }
.status-approved { background: #d1fae5; color: #065f46; }
.status-rejected { background: #fee2e2; color: #991b1b; }
</style>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-money-bill-wave"></i> Funding Requests</h1>
        <p>Manage user wallet funding requests</p>
    </div>
    
    <!-- Stats -->
    <div class="funding-stats">
        <div class="stat-card">
            <h3>Pending Requests</h3>
            <div class="value"><?php echo $stats['pending']['count']; ?></div>
            <div class="small">₦<?php echo number_format($stats['pending']['total'], 2); ?> total</div>
        </div>
        <div class="stat-card">
            <h3>Approved (This Month)</h3>
            <div class="value"><?php echo $stats['approved_month']['count']; ?></div>
            <div class="small">₦<?php echo number_format($stats['approved_month']['total'], 2); ?></div>
        </div>
        <div class="stat-card">
            <h3>Total Approved</h3>
            <div class="value"><?php echo $stats['approved_total']['count']; ?></div>
            <div class="small">₦<?php echo number_format($stats['approved_total']['total'], 2); ?></div>
        </div>
        <div class="stat-card">
            <h3>Avg. Funding</h3>
            <div class="value">
                <?php 
                $avg = $stats['approved_total']['count'] > 0 ? 
                       $stats['approved_total']['total'] / $stats['approved_total']['count'] : 0;
                echo '₦' . number_format($avg, 2);
                ?>
            </div>
            <div class="small">per transaction</div>
        </div>
    </div>
    
    <!-- Tabs -->
    <div class="tabs" style="margin-bottom: 1.5rem;">
        <a href="?tab=pending" class="tab <?php echo (!isset($_GET['tab']) || $_GET['tab'] == 'pending') ? 'active' : ''; ?>">
            Pending (<?php echo count($pendingRequests); ?>)
        </a>
        <a href="?tab=all" class="tab <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'all') ? 'active' : ''; ?>">
            All Requests
        </a>
        <a href="?tab=settings" class="tab <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'settings') ? 'active' : ''; ?>">
            Settings
        </a>
    </div>
    
    <?php if (!isset($_GET['tab']) || $_GET['tab'] == 'pending'): ?>
        <!-- Pending Requests -->
        <h2>Pending Requests</h2>
        <?php if (empty($pendingRequests)): ?>
            <div class="empty-state">
                <i class="fas fa-check-circle"></i>
                <p>No pending funding requests</p>
            </div>
        <?php else: ?>
            <?php foreach ($pendingRequests as $request): ?>
                <div class="request-card">
                    <div class="request-header">
                        <div>
                            <strong><?php echo htmlspecialchars($request['name']); ?></strong>
                            <span class="status-badge status-pending" style="margin-left: 0.5rem;">Pending</span>
                        </div>
                        <div>
                            Ref: <?php echo $request['reference']; ?>
                        </div>
                    </div>
                    <div class="request-body">
                        <div>
                            <strong>Amount:</strong> ₦<?php echo number_format($request['amount'], 2); ?><br>
                            <strong>Payment Method:</strong> <?php echo strtoupper($request['payment_method']); ?><br>
                            <strong>Date:</strong> <?php echo date('M d, Y H:i', strtotime($request['created_at'])); ?>
                        </div>
                        <div>
                            <strong>User Details:</strong><br>
                            Email: <?php echo htmlspecialchars($request['email']); ?><br>
                            Phone: <?php echo htmlspecialchars($request['phone']); ?>
                        </div>
                        <?php if ($request['payment_details']): ?>
                            <div>
                                <strong>Payment Details:</strong><br>
                                <?php echo nl2br(htmlspecialchars($request['payment_details'])); ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($request['proof_path']): ?>
                            <div>
                                <strong>Payment Proof:</strong><br>
                                <img src="<?php echo $request['proof_path']; ?>" class="proof-preview" 
                                     onclick="viewProof('<?php echo $request['proof_path']; ?>')">
                            </div>
                        <?php endif; ?>
                    </div>
                    <div style="padding: 1rem; border-top: 1px solid #e5e7eb; display: flex; gap: 1rem;">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="csrf_token" value="<?php echo Session::generateCSRF(); ?>">
                            <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                            <textarea name="admin_notes" placeholder="Optional notes..." style="margin-bottom: 0.5rem; width: 100%;"></textarea>
                            <button type="submit" name="approve_request" class="btn btn-success" onclick="return confirm('Approve this funding request?')">
                                <i class="fas fa-check"></i> Approve & Fund Wallet
                            </button>
                        </form>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="csrf_token" value="<?php echo Session::generateCSRF(); ?>">
                            <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                            <input type="text" name="rejection_reason" placeholder="Rejection reason..." required style="margin-bottom: 0.5rem; width: 100%;">
                            <button type="submit" name="reject_request" class="btn btn-danger" onclick="return confirm('Reject this funding request?')">
                                <i class="fas fa-times"></i> Reject
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
    <?php elseif (isset($_GET['tab']) && $_GET['tab'] == 'all'): ?>
        <!-- All Requests -->
        <h2>All Funding Requests</h2>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>User</th>
                        <th>Reference</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th>Processed By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allRequests as $request): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($request['created_at'])); ?></td>
                            <td><?php echo htmlspecialchars($request['name']); ?></td>
                            <td><small><?php echo $request['reference']; ?></small></td>
                            <td>₦<?php echo number_format($request['amount'], 2); ?></td>
                            <td><?php echo strtoupper($request['payment_method']); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $request['status']; ?>">
                                    <?php echo ucfirst($request['status']); ?>
                                </span>
                            </td>
                            <td><?php echo $request['admin_name'] ?? '-'; ?></td>
                            <td>
                                <?php if ($request['proof_path']): ?>
                                    <button onclick="viewProof('<?php echo $request['proof_path']; ?>')" class="btn btn-sm btn-light">
                                        <i class="fas fa-eye"></i> Proof
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
    <?php elseif (isset($_GET['tab']) && $_GET['tab'] == 'settings'): ?>
        <!-- Settings -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-cog"></i> Funding Settings</h3>
            </div>
            
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo Session::generateCSRF(); ?>">
                
                <div class="form-group">
                    <label>Enable Manual Funding</label>
                    <label style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" name="funding_enabled" value="1" 
                               <?php echo getSetting('funding_enabled', 1) ? 'checked' : ''; ?>>
                        Enable funding requests
                    </label>
                </div>
                
                <div class="form-group">
                    <label>Minimum Funding Amount (₦)</label>
                    <input type="number" name="min_funding_amount" class="form-control" 
                           value="<?php echo getSetting('min_funding_amount', 100); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Maximum Funding Amount (₦)</label>
                    <input type="number" name="max_funding_amount" class="form-control" 
                           value="<?php echo getSetting('max_funding_amount', 1000000); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Auto-approve Under (₦)</label>
                    <input type="number" name="auto_approve_under" class="form-control" 
                           value="<?php echo getSetting('auto_approve_under', 0); ?>">
                    <small>Set to 0 to disable auto-approval</small>
                </div>
                
                <div class="form-group">
                    <label>Funding Instructions</label>
                    <textarea name="funding_instructions" class="form-control" rows="8"><?php 
                        echo htmlspecialchars(getSetting('funding_instructions', 
                            "Please make payment to:\n\nBank: Example Bank\nAccount Number: 1234567890\nAccount Name: VTU Platform\n\nAfter payment, upload your payment proof.")); 
                    ?></textarea>
                </div>
                
                <button type="submit" name="update_settings" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Settings
                </button>
            </form>
        </div>
    <?php endif; ?>
</div>

<script>
function viewProof(filePath) {
    const modal = document.createElement('div');
    modal.className = 'modal active';
    modal.innerHTML = `
        <div class="modal-content" style="max-width: 90%; max-height: 90%;">
            <div class="modal-header">
                <i class="fas fa-file-image"></i>
                <h3>Payment Proof</h3>
                <button onclick="this.closest('.modal').remove()" style="margin-left: auto; background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
            </div>
            <div class="modal-body" style="text-align: center;">
                ${filePath.endsWith('.pdf') ? 
                    `<iframe src="${filePath}" style="width: 100%; height: 70vh;"></iframe>` :
                    `<img src="${filePath}" style="max-width: 100%; max-height: 70vh;">`
                }
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    
    modal.addEventListener('click', function(e) {
        if (e.target === modal) modal.remove();
    });
}
</script>

<?php
function getSetting($key, $default = null) {
    global $db;
    $stmt = $db->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result ? $result['setting_value'] : $default;
}
?>

<?php include 'admin_footer.php'; ?>