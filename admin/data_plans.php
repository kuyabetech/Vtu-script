<?php
// admin/admin/data_plans.php - Data Plans Management
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

Auth::requireAdmin();

$db = db();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !Session::verifyCSRF($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_plan') {
        $service_id = intval($_POST['service_id'] ?? 0);
        $variation_code = $_POST['variation_code'] ?? '';
        $name = $_POST['name'] ?? '';
        $amount = floatval($_POST['amount'] ?? 0);
        $wholesale_price = floatval($_POST['wholesale_price'] ?? 0);
        $retail_price = floatval($_POST['retail_price'] ?? 0);
        $validity = $_POST['validity'] ?? '';
        $size = $_POST['size'] ?? '';
        $network = $_POST['network'] ?? '';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $stmt = $db->prepare("INSERT INTO service_variations (service_id, variation_code, name, amount, wholesale_price, retail_price, validity, size, network, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issddssssi", $service_id, $variation_code, $name, $amount, $wholesale_price, $retail_price, $validity, $size, $network, $is_active);
        
        if ($stmt->execute()) {
            Session::setSuccess('Data plan added successfully');
        } else {
            Session::setError('Failed to add data plan');
        }
        redirect('admin/data_plans.php');
    }
    
    elseif ($action === 'update_plan') {
        $id = intval($_POST['id'] ?? 0);
        $service_id = intval($_POST['service_id'] ?? 0);
        $variation_code = $_POST['variation_code'] ?? '';
        $name = $_POST['name'] ?? '';
        $amount = floatval($_POST['amount'] ?? 0);
        $wholesale_price = floatval($_POST['wholesale_price'] ?? 0);
        $retail_price = floatval($_POST['retail_price'] ?? 0);
        $validity = $_POST['validity'] ?? '';
        $size = $_POST['size'] ?? '';
        $network = $_POST['network'] ?? '';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $stmt = $db->prepare("UPDATE service_variations SET service_id=?, variation_code=?, name=?, amount=?, wholesale_price=?, retail_price=?, validity=?, size=?, network=?, is_active=? WHERE id=?");
        $stmt->bind_param("issddssssii", $service_id, $variation_code, $name, $amount, $wholesale_price, $retail_price, $validity, $size, $network, $is_active, $id);
        
        if ($stmt->execute()) {
            Session::setSuccess('Data plan updated successfully');
        } else {
            Session::setError('Failed to update data plan');
        }
        redirect('admin/data_plans.php');
    }
    
    elseif ($action === 'delete_plan') {
        $id = intval($_POST['id'] ?? 0);
        $db->query("DELETE FROM service_variations WHERE id = $id");
        Session::setSuccess('Data plan deleted successfully');
        redirect('admin/data_plans.php');
    }
}

// Get all data plans with service names
$plans = $db->query("
    SELECT v.*, s.name as service_name, s.code as service_code 
    FROM service_variations v 
    JOIN services s ON v.service_id = s.id 
    WHERE s.category = 'data'
    ORDER BY s.name, v.amount
")->fetch_all(MYSQLI_ASSOC);

// Get data services for dropdown
$services = $db->query("SELECT id, name, code FROM services WHERE category = 'data' AND is_active = 1")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Data Plans';
include 'admin_header.php';
?>

<div class="container-fluid">
    <div class="content-header">
        <h2><i class="fas fa-wifi"></i> Data Plans Management</h2>
    </div>

    <!-- Alerts -->
    <?php if ($error = Session::getError()): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>
    <?php if ($success = Session::getSuccess()): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <!-- Add Plan Button -->
    <div style="margin-bottom: 1rem;">
        <button class="btn btn-primary" onclick="showAddModal()">
            <i class="fas fa-plus"></i> Add New Data Plan
        </button>
    </div>

    <!-- Plans Table -->
    <div class="card">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Network</th>
                        <th>Plan Name</th>
                        <th>Size</th>
                        <th>Validity</th>
                        <th>Cost Price</th>
                        <th>Selling Price</th>
                        <th>Profit</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($plans as $p): ?>
                        <?php $profit = $p['retail_price'] - $p['wholesale_price']; ?>
                        <tr>
                            <td>#<?php echo $p['id']; ?></td>
                            <td><span class="badge" style="background: <?php 
                                echo $p['network'] == 'mtn' ? '#ffc107' : 
                                    ($p['network'] == 'glo' ? '#28a745' : 
                                    ($p['network'] == 'airtel' ? '#dc3545' : '#17a2b8')); ?>; color: white;">
                                <?php echo strtoupper($p['network']); ?>
                            </span></td>
                            <td><?php echo htmlspecialchars($p['name']); ?></td>
                            <td><?php echo $p['size']; ?></td>
                            <td><?php echo $p['validity']; ?></td>
                            <td><?php echo format_money($p['wholesale_price']); ?></td>
                            <td><?php echo format_money($p['retail_price']); ?></td>
                            <td class="<?php echo $profit > 0 ? 'positive' : 'negative'; ?>">
                                <?php echo format_money($profit); ?>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $p['is_active'] ? 'success' : 'failed'; ?>">
                                    <?php echo $p['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-light btn-small" onclick="editPlan(<?php echo htmlspecialchars(json_encode($p)); ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-danger btn-small" onclick="deletePlan(<?php echo $p['id']; ?>, '<?php echo addslashes($p['name']); ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div id="planModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; width: 700px; border-radius: var(--radius); padding: 2rem; max-height: 90vh; overflow-y: auto;">
        <h3 id="modalTitle" style="margin-bottom: 1rem;">Add Data Plan</h3>
        <form method="POST" id="planForm">
            <input type="hidden" name="csrf_token" value="<?php echo Session::generateCSRF(); ?>">
            <input type="hidden" name="action" id="formAction" value="add_plan">
            <input type="hidden" name="id" id="planId">
            
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                <div class="form-group">
                    <label>Service</label>
                    <select name="service_id" id="planService" class="form-control" required>
                        <option value="">Select Service</option>
                        <?php foreach ($services as $s): ?>
                            <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Network</label>
                    <select name="network" id="planNetwork" class="form-control" required>
                        <option value="">Select Network</option>
                        <option value="mtn">MTN</option>
                        <option value="glo">Glo</option>
                        <option value="airtel">Airtel</option>
                        <option value="9mobile">9mobile</option>
                    </select>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                <div class="form-group">
                    <label>Variation Code</label>
                    <input type="text" name="variation_code" id="planCode" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Plan Name</label>
                    <input type="text" name="name" id="planName" class="form-control" required>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">
                <div class="form-group">
                    <label>Data Size</label>
                    <input type="text" name="size" id="planSize" class="form-control" placeholder="e.g., 1GB" required>
                </div>
                
                <div class="form-group">
                    <label>Validity</label>
                    <input type="text" name="validity" id="planValidity" class="form-control" placeholder="e.g., 30 days" required>
                </div>
                
                <div class="form-group">
                    <label>Amount (₦)</label>
                    <input type="number" name="amount" id="planAmount" class="form-control" step="0.01" required>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                <div class="form-group">
                    <label>Wholesale Price (₦)</label>
                    <input type="number" name="wholesale_price" id="planWholesale" class="form-control" step="0.01" required>
                </div>
                
                <div class="form-group">
                    <label>Retail Price (₦)</label>
                    <input type="number" name="retail_price" id="planRetail" class="form-control" step="0.01" required>
                </div>
            </div>
            
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 0.5rem;">
                    <input type="checkbox" name="is_active" id="planActive" checked>
                    <span>Active</span>
                </label>
            </div>
            
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button type="button" class="btn btn-light" onclick="hideModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Plan</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Form -->
<form method="POST" id="deleteForm" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?php echo Session::generateCSRF(); ?>">
    <input type="hidden" name="action" value="delete_plan">
    <input type="hidden" name="id" id="deleteId">
</form>

<script>
function showAddModal() {
    document.getElementById('modalTitle').textContent = 'Add New Data Plan';
    document.getElementById('formAction').value = 'add_plan';
    document.getElementById('planId').value = '';
    document.getElementById('planService').value = '';
    document.getElementById('planNetwork').value = '';
    document.getElementById('planCode').value = '';
    document.getElementById('planName').value = '';
    document.getElementById('planSize').value = '';
    document.getElementById('planValidity').value = '';
    document.getElementById('planAmount').value = '';
    document.getElementById('planWholesale').value = '';
    document.getElementById('planRetail').value = '';
    document.getElementById('planActive').checked = true;
    document.getElementById('planModal').style.display = 'flex';
}

function editPlan(plan) {
    document.getElementById('modalTitle').textContent = 'Edit Data Plan';
    document.getElementById('formAction').value = 'update_plan';
    document.getElementById('planId').value = plan.id;
    document.getElementById('planService').value = plan.service_id;
    document.getElementById('planNetwork').value = plan.network;
    document.getElementById('planCode').value = plan.variation_code;
    document.getElementById('planName').value = plan.name;
    document.getElementById('planSize').value = plan.size;
    document.getElementById('planValidity').value = plan.validity;
    document.getElementById('planAmount').value = plan.amount;
    document.getElementById('planWholesale').value = plan.wholesale_price;
    document.getElementById('planRetail').value = plan.retail_price;
    document.getElementById('planActive').checked = plan.is_active == 1;
    document.getElementById('planModal').style.display = 'flex';
}

function hideModal() {
    document.getElementById('planModal').style.display = 'none';
}

function deletePlan(id, name) {
    if (confirm('Are you sure you want to delete plan: ' + name + '?')) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteForm').submit();
    }
}

window.onclick = function(event) {
    const modal = document.getElementById('planModal');
    if (event.target == modal) {
        hideModal();
    }
}
</script>

<?php include 'admin_footer.php'; ?>