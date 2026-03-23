<?php
// admin/admin/services.php - Service Management with Separate Category Table
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

Auth::requireAdmin();

$db = db();

// Create categories table if it doesn't exist
$db->query("
CREATE TABLE IF NOT EXISTS categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    code VARCHAR(50) NOT NULL UNIQUE,
    icon VARCHAR(50) DEFAULT 'fa-folder',
    color VARCHAR(20) DEFAULT '#6366f1',
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Check if category_id column exists in services table
$check_column = $db->query("SHOW COLUMNS FROM services LIKE 'category_id'");
if ($check_column->num_rows == 0) {
    // Add category_id column
    $db->query("ALTER TABLE services ADD COLUMN category_id INT NULL AFTER name");
    
    // Add foreign key constraint
    $db->query("ALTER TABLE services ADD FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL");
}

// Migrate existing category strings to categories table
$check_categories = $db->query("SELECT DISTINCT category FROM services WHERE category IS NOT NULL AND category != ''");
if ($check_categories && $check_categories->num_rows > 0) {
    while ($cat = $check_categories->fetch_assoc()) {
        $category_name = $cat['category'];
        
        // Check if category already exists in categories table
        $check_existing = $db->prepare("SELECT id FROM categories WHERE name = ? OR code = ?");
        $code_check = strtolower(preg_replace('/[^a-z0-9]+/', '_', $category_name));
        $check_existing->bind_param("ss", $category_name, $code_check);
        $check_existing->execute();
        $existing = $check_existing->get_result();
        
        if ($existing->num_rows == 0) {
            // Generate unique code
            $code = $code_check;
            $original_code = $code;
            $counter = 1;
            
            // Check if code exists and make it unique
            $code_exists = $db->prepare("SELECT id FROM categories WHERE code = ?");
            $code_exists->bind_param("s", $code);
            $code_exists->execute();
            while ($code_exists->get_result()->num_rows > 0) {
                $code = $original_code . '_' . $counter;
                $code_exists->bind_param("s", $code);
                $code_exists->execute();
                $counter++;
            }
            
            $icon = 'fa-folder';
            $color = '#6366f1';
            
            $insert_cat = $db->prepare("INSERT INTO categories (name, code, icon, color) VALUES (?, ?, ?, ?)");
            $insert_cat->bind_param("ssss", $category_name, $code, $icon, $color);
            $insert_cat->execute();
        }
    }
}

// Update services to link to categories
$db->query("
UPDATE services s 
SET s.category_id = (SELECT id FROM categories c WHERE c.name = s.category)
WHERE s.category IS NOT NULL AND s.category != '' AND s.category_id IS NULL
");

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF
    if (!isset($_POST['csrf_token'])) {
        Session::setError('CSRF token missing. Please refresh the page.');
        redirect('admin/services.php');
    }
    
    if (!Session::verifyCSRF($_POST['csrf_token'])) {
        Session::setError('Invalid CSRF token. Please refresh the page.');
        redirect('admin/services.php');
    }
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_service') {
        $name = $_POST['name'] ?? '';
        $code = $_POST['code'] ?? '';
        $category_id = intval($_POST['category_id'] ?? 0);
        $provider_id = intval($_POST['provider_id'] ?? 0);
        $min_amount = floatval($_POST['min_amount'] ?? 0);
        $max_amount = floatval($_POST['max_amount'] ?? 0);
        $commission_rate = floatval($_POST['commission_rate'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Validate required fields
        if (empty($name) || empty($code) || $category_id <= 0) {
            Session::setError('Name, code and category are required');
            redirect('admin/services.php');
        }
        
        $stmt = $db->prepare("INSERT INTO services (name, code, category_id, provider_id, min_amount, max_amount, commission_rate, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssiidddi", $name, $code, $category_id, $provider_id, $min_amount, $max_amount, $commission_rate, $is_active);
        
        if ($stmt->execute()) {
            Session::setSuccess('Service added successfully');
        } else {
            Session::setError('Failed to add service: ' . $db->error);
        }
        redirect('admin/services.php');
    }
    
    elseif ($action === 'update_service') {
        $id = intval($_POST['id'] ?? 0);
        $name = $_POST['name'] ?? '';
        $code = $_POST['code'] ?? '';
        $category_id = intval($_POST['category_id'] ?? 0);
        $provider_id = intval($_POST['provider_id'] ?? 0);
        $min_amount = floatval($_POST['min_amount'] ?? 0);
        $max_amount = floatval($_POST['max_amount'] ?? 0);
        $commission_rate = floatval($_POST['commission_rate'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if ($id <= 0) {
            Session::setError('Invalid service ID');
            redirect('admin/services.php');
        }
        
        $stmt = $db->prepare("UPDATE services SET name=?, code=?, category_id=?, provider_id=?, min_amount=?, max_amount=?, commission_rate=?, is_active=? WHERE id=?");
        $stmt->bind_param("ssiidddii", $name, $code, $category_id, $provider_id, $min_amount, $max_amount, $commission_rate, $is_active, $id);
        
        if ($stmt->execute()) {
            Session::setSuccess('Service updated successfully');
        } else {
            Session::setError('Failed to update service');
        }
        redirect('admin/services.php');
    }
    
    elseif ($action === 'delete_service') {
        $id = intval($_POST['id'] ?? 0);
        
        if ($id <= 0) {
            Session::setError('Invalid service ID');
            redirect('admin/services.php');
        }
        
        $stmt = $db->prepare("DELETE FROM services WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            Session::setSuccess('Service deleted successfully');
        } else {
            Session::setError('Failed to delete service');
        }
        redirect('admin/services.php');
    }
    
    elseif ($action === 'add_category') {
        $category_name = trim($_POST['category_name'] ?? '');
        $category_code = trim($_POST['category_code'] ?? '');
        $category_icon = $_POST['category_icon'] ?? 'fa-folder';
        $category_color = $_POST['category_color'] ?? '#6366f1';
        $category_description = $_POST['category_description'] ?? '';
        
        if (empty($category_name) || empty($category_code)) {
            Session::setError("Category name and code are required");
            redirect('admin/services.php');
        }
        
        // Trim and limit category name
        $category_name = substr($category_name, 0, 100);
        
        // Sanitize category code
        $category_code = preg_replace('/[^a-z0-9_]/', '', strtolower($category_code));
        $category_code = substr($category_code, 0, 50);
        
        if (empty($category_code)) {
            Session::setError("Invalid category code. Use only letters, numbers and underscores.");
            redirect('admin/services.php');
        }
        
        // Check if category already exists
        $check_stmt = $db->prepare("SELECT id FROM categories WHERE name = ? OR code = ?");
        $check_stmt->bind_param("ss", $category_name, $category_code);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            Session::setError("Category with this name or code already exists");
            redirect('admin/services.php');
        }
        
        // Get max display order
        $order_result = $db->query("SELECT MAX(display_order) as max_order FROM categories");
        $max_order = $order_result->fetch_assoc()['max_order'] ?? 0;
        $display_order = $max_order + 1;
        
        // Insert into categories table
        $insert_stmt = $db->prepare("INSERT INTO categories (name, code, icon, color, description, display_order, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
        $insert_stmt->bind_param("sssssi", $category_name, $category_code, $category_icon, $category_color, $category_description, $display_order);
        
        if ($insert_stmt->execute()) {
            $category_id = $insert_stmt->insert_id;
            
            // Create PHP file for the category
            $filename = "../user/" . $category_code . ".php";
            
            if (!file_exists($filename)) {
                $template = "<?php
// user/{$category_code}.php - {$category_name} Page
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

Auth::requireLogin();

\$db = db();
\$userId = Session::userId();
\$walletBalance = getUserBalance(\$userId);

// Get user's wallet ID
\$walletId = null;
\$stmt = \$db->prepare(\"SELECT id FROM wallets WHERE user_id = ?\");
\$stmt->bind_param(\"i\", \$userId);
\$stmt->execute();
\$result = \$stmt->get_result();
if (\$wallet = \$result->fetch_assoc()) {
    \$walletId = \$wallet['id'];
}

\$pageTitle = '{$category_name}';
include '../partials/user_header.php';
?>

<style>
/* Custom styles for {$category_name} page */
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
    color: {$category_color};
    background: rgba({$category_color}, 0.1);
    padding: 0.75rem;
    border-radius: 50%;
}

.balance-badge {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 2rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
}

.card {
    background: white;
    border-radius: 1.5rem;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    border: 1px solid var(--gray-light);
}

.card-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--gray-light);
}

.card-header h3 {
    margin: 0;
    font-size: 1.25rem;
    color: var(--dark);
}

.card-header i {
    color: {$category_color};
    font-size: 1.5rem;
}

.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: var(--gray);
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.5;
    color: {$category_color};
}

.empty-state h3 {
    font-size: 1.25rem;
    margin-bottom: 0.5rem;
    color: var(--dark);
}

.empty-state p {
    margin-bottom: 1.5rem;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    font-size: 1rem;
    font-weight: 500;
    border-radius: 1rem;
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

.main-content {
    padding-bottom: calc(var(--bottom-nav-height) + 1.5rem);
}

@media (max-width: 768px) {
    .content-header {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>

<div class=\"main-content\">
    <div class=\"container\">
        <div class=\"content-header\">
            <h2>
                <i class=\"fas {$category_icon}\"></i>
                <span>{$category_name}</span>
            </h2>
            <div class=\"balance-badge\">
                <i class=\"fas fa-wallet\"></i>
                <span><?php echo format_money(\$walletBalance); ?></span>
            </div>
        </div>
        
        <div class=\"card\">
            <div class=\"card-header\">
                <i class=\"fas {$category_icon}\"></i>
                <h3>{$category_name} Services</h3>
            </div>
            <div class=\"empty-state\">
                <i class=\"fas {$category_icon}\"></i>
                <h3>Coming Soon</h3>
                <p>Our {$category_name} services will be available shortly.</p>
                <a href=\"admin/services.php\" class=\"btn btn-primary\">
                    <i class=\"fas fa-arrow-left\"></i> Browse Other Services
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Bottom Navigation -->
<nav class=\"bottom-nav\">
    <a href=\"dashboard.php\" class=\"bottom-nav-item\">
        <i class=\"fas fa-home\"></i>
        <span>Home</span>
    </a>
    <a href=\"wallet.php\" class=\"bottom-nav-item\">
        <i class=\"fas fa-wallet\"></i>
        <span>Wallet</span>
    </a>
    <a href=\"admin/services.php\" class=\"bottom-nav-item\">
        <i class=\"fas fa-th-large\"></i>
        <span>Services</span>
    </a>
    <a href=\"transactions.php\" class=\"bottom-nav-item\">
        <i class=\"fas fa-history\"></i>
        <span>History</span>
    </a>
    <a href=\"profile.php\" class=\"bottom-nav-item\">
        <i class=\"fas fa-user\"></i>
        <span>Profile</span>
    </a>
</nav>

<script>
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

document.querySelectorAll('.card').forEach(el => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(20px)';
    el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
    observer.observe(el);
});
</script>

</body>
</html>";
                
                file_put_contents($filename, $template);
            }
            
            Session::setSuccess("Category '{$category_name}' created successfully with file: {$category_code}.php");
        } else {
            Session::setError("Failed to create category: " . $db->error);
        }
        redirect('admin/services.php');
    }
}

// Get all services with category and provider names
$services = $db->query("
    SELECT s.*, 
           c.name as category_name, 
           c.icon as category_icon, 
           c.color as category_color,
           p.name as provider_name 
    FROM services s 
    LEFT JOIN categories c ON s.category_id = c.id 
    LEFT JOIN api_providers p ON s.provider_id = p.id 
    ORDER BY c.display_order, s.name
")->fetch_all(MYSQLI_ASSOC);

// Get all categories with service counts
$categories = $db->query("
    SELECT c.*, 
           COUNT(s.id) as service_count 
    FROM categories c 
    LEFT JOIN services s ON c.id = s.category_id 
    GROUP BY c.id 
    ORDER BY c.display_order
")->fetch_all(MYSQLI_ASSOC);

// Get providers for dropdown
$providers = $db->query("SELECT id, name FROM api_providers WHERE is_active = 1 ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Generate CSRF token
$csrf_token = Session::generateCSRF();

$pageTitle = 'Services';
include 'admin_header.php';
?>

<!-- Keep all your existing HTML and JavaScript from your original file here -->
<style>
/* ===== Fully Responsive Admin Services Styles ===== */

:root {
    --primary: #6366f1;
    --primary-dark: #4f52e0;
    --primary-light: #818cf8;
    --secondary: #8b5cf6;
    --success: #10b981;
    --danger: #ef4444;
    --warning: #f59e0b;
    --info: #3b82f6;
    --dark: #1f2937;
    --gray: #6b7280;
    --gray-light: #e5e7eb;
    --light: #f9fafb;
    --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    --radius: 0.75rem;
    --radius-lg: 1rem;
    --radius-xl: 1.5rem;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
    background: var(--light);
    color: var(--dark);
    line-height: 1.5;
}

/* Container */
.container-fluid {
    max-width: 1400px;
    margin: 0 auto;
    padding: 1rem;
}

@media (min-width: 768px) {
    .container-fluid {
        padding: 1.5rem;
    }
}

@media (min-width: 1024px) {
    .container-fluid {
        padding: 2rem;
    }
}

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
    margin: 0;
}

.content-header h2 i {
    color: var(--primary);
    background: rgba(99, 102, 241, 0.1);
    padding: 0.75rem;
    border-radius: 50%;
    font-size: clamp(1rem, 3vw, 1.25rem);
}

.content-header .badge {
    font-size: clamp(0.75rem, 2vw, 0.875rem);
    padding: 0.5rem 1rem;
}

/* Action Buttons - Fully Responsive */
.action-buttons {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
    flex-wrap: wrap;
}

.action-buttons .btn {
    flex: 1 1 auto;
    min-width: 200px;
}

@media (max-width: 640px) {
    .action-buttons {
        flex-direction: column;
    }
    
    .action-buttons .btn {
        width: 100%;
        min-width: 100%;
    }
}

/* Button Styles */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    font-size: 0.95rem;
    font-weight: 500;
    border-radius: var(--radius);
    cursor: pointer;
    transition: all 0.3s ease;
    border: none;
    outline: none;
    text-decoration: none;
    white-space: nowrap;
    min-height: 44px;
}

@media (max-width: 768px) {
    .btn {
        padding: 0.75rem 1rem;
        font-size: 0.9rem;
    }
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
}

.btn-primary:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(99, 102, 241, 0.4);
}

.btn-success {
    background: var(--success);
    color: white;
}

.btn-success:hover {
    background: #0d9488;
    transform: translateY(-2px);
}

.btn-light {
    background: white;
    color: var(--dark);
    border: 1px solid var(--gray-light);
}

.btn-light:hover {
    background: var(--light);
    border-color: var(--primary);
    transform: translateY(-2px);
}

.btn-danger {
    background: var(--danger);
    color: white;
}

.btn-danger:hover {
    background: #dc2626;
    transform: translateY(-2px);
}

.btn-small {
    padding: 0.4rem 1rem;
    font-size: 0.85rem;
    min-height: 36px;
}

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Cards - Responsive */
.card {
    background: white;
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    padding: 1.5rem;
    margin-bottom: 2rem;
    border: 1px solid var(--gray-light);
    overflow: hidden;
}

@media (max-width: 640px) {
    .card {
        padding: 1rem;
    }
}

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--gray-light);
    flex-wrap: wrap;
    gap: 1rem;
}

.card-header h3 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: clamp(1.1rem, 3vw, 1.25rem);
    color: var(--dark);
    margin: 0;
}

.card-header h3 i {
    color: var(--primary);
}

/* Tables - Fully Responsive */
.table-responsive {
    overflow-x: auto;
    border-radius: var(--radius);
    margin: 0 -1rem;
}

@media (max-width: 768px) {
    .table-responsive {
        margin: 0;
    }
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 900px;
}

@media (max-width: 1024px) {
    .data-table {
        min-width: 800px;
    }
}

@media (max-width: 768px) {
    .data-table {
        min-width: 700px;
    }
}

.data-table th {
    background: var(--light);
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    color: var(--dark);
    font-size: 0.9rem;
    white-space: nowrap;
}

.data-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--gray-light);
    color: var(--dark);
}

.data-table tr:hover {
    background: var(--light);
}

.data-table td code {
    background: var(--light);
    padding: 0.25rem 0.5rem;
    border-radius: 0.5rem;
    font-size: 0.85rem;
    color: var(--primary);
}

/* Badges */
.badge {
    display: inline-flex;
    align-items: center;
    padding: 0.35rem 1rem;
    border-radius: 2rem;
    font-size: 0.8rem;
    font-weight: 600;
    white-space: nowrap;
}

.badge-info {
    background: #dbeafe;
    color: #1e40af;
}

.badge-success {
    background: #d1fae5;
    color: #065f46;
}

.badge-warning {
    background: #fef3c7;
    color: #92400e;
}

.badge-danger {
    background: #fee2e2;
    color: #991b1b;
}

/* Alerts */
.alert {
    padding: 1rem 1.5rem;
    border-radius: var(--radius);
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    animation: slideIn 0.3s ease;
    flex-wrap: wrap;
}

@media (max-width: 480px) {
    .alert {
        flex-direction: column;
        text-align: center;
        padding: 1rem;
    }
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

/* Modal - Fully Responsive */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    backdrop-filter: blur(4px);
}

.modal.active {
    display: flex;
}

.modal-content {
    background: white;
    border-radius: var(--radius-xl);
    padding: 2rem;
    max-width: 600px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    animation: modalSlideUp 0.3s ease;
    box-shadow: var(--shadow-lg);
}

@media (max-width: 640px) {
    .modal-content {
        padding: 1.5rem;
        max-height: 85vh;
    }
}

@keyframes modalSlideUp {
    from {
        transform: translateY(50px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--gray-light);
    flex-wrap: wrap;
    gap: 1rem;
}

.modal-header h3 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: clamp(1.1rem, 4vw, 1.25rem);
    color: var(--dark);
    margin: 0;
}

.modal-header i {
    color: var(--primary);
    font-size: 1.5rem;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: var(--gray);
    transition: color 0.3s;
    padding: 0.5rem;
    min-width: 44px;
    min-height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-close:hover {
    color: var(--danger);
}

.modal-body {
    margin-bottom: 1.5rem;
}

.modal-footer {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    margin-top: 2rem;
    padding-top: 1rem;
    border-top: 2px solid var(--gray-light);
}

@media (max-width: 480px) {
    .modal-footer {
        flex-direction: column;
    }
    
    .modal-footer .btn {
        width: 100%;
    }
}

/* Forms - Responsive */
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

.form-control {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 2px solid var(--gray-light);
    border-radius: var(--radius);
    font-size: 0.95rem;
    transition: all 0.3s ease;
    background: white;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.form-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
    margin-bottom: 0.5rem;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 480px) {
    .form-row {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }
}

/* Color Picker */
.color-picker {
    width: 100%;
    height: 45px;
    padding: 0.25rem;
    border: 2px solid var(--gray-light);
    border-radius: var(--radius);
    cursor: pointer;
}

/* Icon Preview */
.icon-preview {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem;
    background: var(--light);
    border-radius: var(--radius);
    margin-top: 0.5rem;
    flex-wrap: wrap;
}

.icon-preview i {
    font-size: 1.5rem;
    color: var(--primary);
}

.icon-preview span {
    font-size: 0.9rem;
    color: var(--gray);
}

/* Categories Grid - Fully Responsive */
.categories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

@media (max-width: 640px) {
    .categories-grid {
        grid-template-columns: 1fr;
    }
}

.category-item {
    background: var(--light);
    padding: 1rem;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: space-between;
    border: 2px solid transparent;
    transition: all 0.3s ease;
    min-height: 60px;
}

.category-item:hover {
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: var(--shadow);
}

.category-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-width: 0;
    flex: 1;
}

.category-info i {
    font-size: 1.25rem;
    color: var(--primary);
    flex-shrink: 0;
}

.category-info span {
    font-weight: 500;
    color: var(--dark);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.category-count {
    background: white;
    padding: 0.25rem 0.75rem;
    border-radius: 2rem;
    font-size: 0.8rem;
    color: var(--gray);
    white-space: nowrap;
    margin-left: 0.5rem;
}

/* Action Cell */
.actions-cell {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

@media (max-width: 480px) {
    .actions-cell {
        flex-direction: column;
    }
    
    .actions-cell .btn {
        width: 100%;
    }
}

/* Touch-friendly targets */
@media (max-width: 768px) {
    .btn, 
    .modal-close,
    .category-item,
    .form-control {
        min-height: 48px;
    }
    
    .btn-small {
        min-height: 40px;
    }
}

/* Animations */
@keyframes slideIn {
    from {
        transform: translateY(-20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.fade-in {
    animation: fadeIn 0.5s ease;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: var(--gray);
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-state p {
    margin-bottom: 1rem;
    font-size: 1rem;
}

/* Loading Spinner */
.fa-spinner {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Responsive Typography */
@media (max-width: 480px) {
    .data-table td {
        font-size: 0.85rem;
    }
    
    .badge {
        font-size: 0.7rem;
        padding: 0.25rem 0.75rem;
    }
}

/* Print Styles */
@media print {
    .btn,
    .modal,
    .action-buttons {
        display: none;
    }
}/* ────────────────────────────────────────────────
   Add / replace these rules at the END of your <style> block
   ──────────────────────────────────────────────── */

/* Better modal sizing on small screens */
@media (max-width: 480px) {
    .modal-content {
        margin: 0 8px;
        padding: 1.25rem 1rem;
        max-height: 95vh;
        border-radius: 12px;
    }
    
    .modal-header h3 {
        font-size: 1.1rem;
    }
    
    .modal-footer {
        flex-direction: column-reverse;
        gap: 0.75rem;
    }
    
    .modal-footer .btn {
        width: 100%;
        padding: 0.85rem;
    }
}

/* ─── Forms become stacked on small & medium screens ─── */
@media (max-width: 840px) {
    .form-row {
        grid-template-columns: 1fr !important;
        gap: 1.1rem;
    }
}

/* Make inputs & selects taller/touch-friendly */
@media (max-width: 640px) {
    .form-control,
    .form-control[type="color"],
    select.form-control {
        padding: 0.9rem 1rem;
        font-size: 0.97rem;
        min-height: 52px;
    }
    
    .form-group label {
        font-size: 0.94rem;
    }
}

/* ─── Services table mobile optimizations ─── */
@media (max-width: 960px) {
    .data-table {
        min-width: 680px;          /* reduced from 700–900 */
    }
}

@media (max-width: 640px) {
    .data-table {
        min-width: 580px;
    }
    
    .data-table th,
    .data-table td {
        padding: 0.8rem 0.6rem;
        font-size: 0.84rem;
    }
    
    .actions-cell {
        flex-direction: column;
        gap: 0.5rem;
        align-items: flex-start;
    }
    
    .actions-cell .btn {
        width: 100%;
        justify-content: center;
        min-height: 42px;
        font-size: 0.88rem;
    }
}

/* Better category card spacing & size */
@media (max-width: 520px) {
    .categories-grid {
        grid-template-columns: 1fr !important;
        gap: 0.9rem;
    }
    
    .category-item {
        padding: 1rem 1.2rem;
    }
    
    .category-info i {
        font-size: 1.4rem;
    }
}

/* Action buttons at top */
@media (max-width: 500px) {
    .action-buttons {
        flex-direction: column !important;
        gap: 0.8rem;
    }
    
    .action-buttons .btn {
        width: 100%;
        padding: 0.9rem 1.2rem;
        font-size: 0.96rem;
    }
}

/* Make sure content doesn't get too narrow */
@media (max-width: 360px) {
    .container-fluid {
        padding-left: 10px;
        padding-right: 10px;
    }
    
    .card {
        padding: 1rem 0.9rem;
    }
}

/* ────────────────────────────────────────────────
   Add / replace these rules at the END of your <style> block
   ──────────────────────────────────────────────── */

/* Better modal sizing on small screens */
@media (max-width: 480px) {
    .modal-content {
        margin: 0 8px;
        padding: 1.25rem 1rem;
        max-height: 95vh;
        border-radius: 12px;
    }
    
    .modal-header h3 {
        font-size: 1.1rem;
    }
    
    .modal-footer {
        flex-direction: column-reverse;
        gap: 0.75rem;
    }
    
    .modal-footer .btn {
        width: 100%;
        padding: 0.85rem;
    }
}

/* ─── Forms become stacked on small & medium screens ─── */
@media (max-width: 840px) {
    .form-row {
        grid-template-columns: 1fr !important;
        gap: 1.1rem;
    }
}

/* Make inputs & selects taller/touch-friendly */
@media (max-width: 640px) {
    .form-control,
    .form-control[type="color"],
    select.form-control {
        padding: 0.9rem 1rem;
        font-size: 0.97rem;
        min-height: 52px;
    }
    
    .form-group label {
        font-size: 0.94rem;
    }
}

/* ─── Services table mobile optimizations ─── */
@media (max-width: 960px) {
    .data-table {
        min-width: 680px;          /* reduced from 700–900 */
    }
}

@media (max-width: 640px) {
    .data-table {
        min-width: 580px;
    }
    
    .data-table th,
    .data-table td {
        padding: 0.8rem 0.6rem;
        font-size: 0.84rem;
    }
    
    .actions-cell {
        flex-direction: column;
        gap: 0.5rem;
        align-items: flex-start;
    }
    
    .actions-cell .btn {
        width: 100%;
        justify-content: center;
        min-height: 42px;
        font-size: 0.88rem;
    }
}

/* Better category card spacing & size */
@media (max-width: 520px) {
    .categories-grid {
        grid-template-columns: 1fr !important;
        gap: 0.9rem;
    }
    
    .category-item {
        padding: 1rem 1.2rem;
    }
    
    .category-info i {
        font-size: 1.4rem;
    }
}

/* Action buttons at top */
@media (max-width: 500px) {
    .action-buttons {
        flex-direction: column !important;
        gap: 0.8rem;
    }
    
    .action-buttons .btn {
        width: 100%;
        padding: 0.9rem 1.2rem;
        font-size: 0.96rem;
    }
}

/* Make sure content doesn't get too narrow */
@media (max-width: 360px) {
    .container-fluid {
        padding-left: 10px;
        padding-right: 10px;
    }
    
    .card {
        padding: 1rem 0.9rem;
    }
}
</style>


<div class="container-fluid">
    <div class="content-header">
        <h2>
            <i class="fas fa-cogs"></i>
            <span>Service Management</span>
        </h2>
        <span class="badge badge-info"><?php echo count($services); ?> Total Services</span>
    </div>

    <!-- Alerts -->
    <?php if ($error = Session::getError()): ?>
        <div class="alert alert-error fade-in">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
    <?php endif; ?>
    
    <?php if ($success = Session::getSuccess()): ?>
        <div class="alert alert-success fade-in">
            <i class="fas fa-check-circle"></i>
            <span><?php echo htmlspecialchars($success); ?></span>
        </div>
    <?php endif; ?>

    <!-- Action Buttons -->
    <div class="action-buttons">
        <button class="btn btn-primary" onclick="showAddModal()">
            <i class="fas fa-plus"></i> Add New Service
        </button>
        <button class="btn btn-success" onclick="showCategoryModal()">
            <i class="fas fa-folder-plus"></i> Add New Category
        </button>
    </div>

    <!-- Categories Section -->
    <?php if (!empty($categories)): ?>
        <div class="card fade-in">
            <div class="card-header">
                <h3><i class="fas fa-tags"></i> Categories</h3>
                <span class="badge badge-info"><?php echo count($categories); ?> Categories</span>
            </div>
            <div class="categories-grid">
                <?php foreach ($categories as $cat): ?>
                    <div class="category-item">
                        <div class="category-info">
                            <i class="fas <?php echo $cat['icon']; ?>" style="color: <?php echo $cat['color']; ?>;"></i>
                            <span><?php echo htmlspecialchars($cat['name']); ?></span>
                        </div>
                        <div class="category-actions">
                            <span class="category-count"><?php echo $cat['service_count']; ?> services</span>
                            <span class="badge badge-<?php echo $cat['is_active'] ? 'success' : 'danger'; ?>" style="margin-left: 0.5rem;">
                                <?php echo $cat['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Services Table -->
    <div class="card fade-in">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> All Services</h3>
            <span class="badge badge-info"><?php echo count($services); ?> Items</span>
        </div>
        
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Code</th>
                        <th>Category</th>
                        <th>Provider</th>
                        <th>Min (₦)</th>
                        <th>Max (₦)</th>
                        <th>Commission</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($services)): ?>
                        <tr>
                            <td colspan="10" class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <p>No services found. Click "Add New Service" to create one.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($services as $s): ?>
                            <tr>
                                <td><strong>#<?php echo $s['id']; ?></strong></td>
                                <td><?php echo htmlspecialchars($s['name']); ?></td>
                                <td><code><?php echo $s['code']; ?></code></td>
                                <td>
                                    <?php if ($s['category_id']): ?>
                                        <span class="badge" style="background: <?php echo $s['category_color'] . '20'; ?>; color: <?php echo $s['category_color']; ?>;">
                                            <i class="fas <?php echo $s['category_icon']; ?>"></i>
                                            <?php echo htmlspecialchars($s['category_name']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge" style="background: #e5e7eb; color: #1f2937;">No Category</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $s['provider_name'] ?? '<span class="badge" style="background: #f3f4f6; color: #6b7280;">None</span>'; ?></td>
                                <td><?php echo format_money($s['min_amount']); ?></td>
                                <td><?php echo format_money($s['max_amount']); ?></td>
                                <td>
                                    <span class="badge" style="background: #dbeafe; color: #1e40af;">
                                        <?php echo $s['commission_rate']; ?>%
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $s['is_active'] ? 'success' : 'danger'; ?>">
                                        <?php echo $s['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td class="actions-cell">
                                    <button class="btn btn-light btn-small" onclick='editService(<?php echo json_encode($s); ?>)'>
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-danger btn-small" onclick="deleteService(<?php echo $s['id']; ?>, '<?php echo addslashes($s['name']); ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Service Modal -->
<div id="serviceModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">
                <i class="fas fa-plus-circle"></i>
                <span>Add New Service</span>
            </h3>
            <button class="modal-close" onclick="hideModal('serviceModal')">&times;</button>
        </div>
        
        <form method="POST" id="serviceForm">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" id="formAction" value="add_service">
            <input type="hidden" name="id" id="serviceId">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="serviceName">Service Name *</label>
                    <input type="text" class="form-control" id="serviceName" name="name" required 
                           placeholder="e.g., MTN Airtime">
                </div>
                
                <div class="form-group">
                    <label for="serviceCode">Service Code *</label>
                    <input type="text" class="form-control" id="serviceCode" name="code" required 
                           placeholder="e.g., mtn_airtime">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="serviceCategory">Category *</label>
                    <select class="form-control" id="serviceCategory" name="category_id" required>
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" style="color: <?php echo $cat['color']; ?>;">
                                <i class="fas <?php echo $cat['icon']; ?>"></i>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="serviceProvider">Provider</label>
                    <select class="form-control" id="serviceProvider" name="provider_id">
                        <option value="0">None</option>
                        <?php foreach ($providers as $p): ?>
                            <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="serviceMin">Min Amount (₦)</label>
                    <input type="number" class="form-control" id="serviceMin" name="min_amount" 
                           step="0.01" min="0" value="0" placeholder="0.00">
                </div>
                
                <div class="form-group">
                    <label for="serviceMax">Max Amount (₦)</label>
                    <input type="number" class="form-control" id="serviceMax" name="max_amount" 
                           step="0.01" min="0" value="0" placeholder="0.00">
                </div>
                
                <div class="form-group">
                    <label for="serviceCommission">Commission (%)</label>
                    <input type="number" class="form-control" id="serviceCommission" name="commission_rate" 
                           step="0.1" min="0" max="100" value="0" placeholder="0.0">
                </div>
            </div>
            
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="checkbox" name="is_active" id="serviceActive" checked>
                    <span>Active</span>
                </label>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-light" onclick="hideModal('serviceModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" id="serviceSubmitBtn">Save Service</button>
            </div>
        </form>
    </div>
</div>

<!-- Category Modal -->
<div id="categoryModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>
                <i class="fas fa-folder-plus"></i>
                <span>Add New Category</span>
            </h3>
            <button class="modal-close" onclick="hideModal('categoryModal')">&times;</button>
        </div>
        
        <form method="POST" id="categoryForm">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="add_category">
            
            <div class="form-group">
                <label for="categoryName">Category Name *</label>
                <input type="text" class="form-control" id="categoryName" name="category_name" required 
                       placeholder="e.g., Internet Services" onkeyup="updateCategoryCode()">
                <small style="color: var(--gray);">This will be displayed in the services page</small>
            </div>
            
            <div class="form-group">
                <label for="categoryCode">Category Code (Filename) *</label>
                <input type="text" class="form-control" id="categoryCode" name="category_code" required 
                       placeholder="e.g., internet_services" onkeyup="updateFilenamePreview()">
                <small style="color: var(--gray);">This will be used as the PHP filename: <span id="filenamePreview">internet_admin/services.php</span></small>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="categoryIcon">Icon Class</label>
                    <input type="text" class="form-control" id="categoryIcon" name="category_icon" 
                           value="fa-folder" placeholder="e.g., fa-globe" onkeyup="updateIconPreview()">
                    <div class="icon-preview">
                        <i class="fas" id="iconPreview"></i>
                        <span>Preview: <span id="iconPreviewText">fa-folder</span></span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="categoryColor">Color</label>
                    <input type="color" class="color-picker" id="categoryColor" name="category_color" 
                           value="#6366f1">
                </div>
            </div>
            
            <div class="form-group">
                <label for="categoryDescription">Description</label>
                <textarea class="form-control" id="categoryDescription" name="category_description" rows="3" 
                          placeholder="Brief description of this category"></textarea>
            </div>
            
            <div class="alert alert-info" style="margin-top: 1rem;">
                <i class="fas fa-info-circle"></i>
                <span>This will create a new PHP file: <strong id="fullPathPreview">../user/internet_admin/services.php</strong></span>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-light" onclick="hideModal('categoryModal')">Cancel</button>
                <button type="submit" class="btn btn-success" id="categorySubmitBtn">Create Category & File</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Form -->
<form method="POST" id="deleteForm" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="action" value="delete_service">
    <input type="hidden" name="id" id="deleteId">
</form>

<script>
// Store CSRF token for JavaScript use
const csrfToken = '<?php echo $csrf_token; ?>';

// Modal functions
function showAddModal() {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus-circle"></i> Add New Service';
    document.getElementById('formAction').value = 'add_service';
    document.getElementById('serviceId').value = '';
    document.getElementById('serviceName').value = '';
    document.getElementById('serviceCode').value = '';
    document.getElementById('serviceCategory').value = '';
    document.getElementById('serviceProvider').value = '0';
    document.getElementById('serviceMin').value = '0';
    document.getElementById('serviceMax').value = '0';
    document.getElementById('serviceCommission').value = '0';
    document.getElementById('serviceActive').checked = true;
    document.getElementById('serviceModal').classList.add('active');
}

function showCategoryModal() {
    document.getElementById('categoryName').value = '';
    document.getElementById('categoryCode').value = '';
    document.getElementById('categoryIcon').value = 'fa-folder';
    document.getElementById('categoryColor').value = '#6366f1';
    document.getElementById('categoryDescription').value = '';
    updateIconPreview();
    updateFilenamePreview();
    document.getElementById('categoryModal').classList.add('active');
}

function hideModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

function editService(service) {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Service';
    document.getElementById('formAction').value = 'update_service';
    document.getElementById('serviceId').value = service.id;
    document.getElementById('serviceName').value = service.name;
    document.getElementById('serviceCode').value = service.code;
    document.getElementById('serviceCategory').value = service.category_id || '';
    document.getElementById('serviceProvider').value = service.provider_id || '0';
    document.getElementById('serviceMin').value = service.min_amount;
    document.getElementById('serviceMax').value = service.max_amount;
    document.getElementById('serviceCommission').value = service.commission_rate;
    document.getElementById('serviceActive').checked = service.is_active == 1;
    document.getElementById('serviceModal').classList.add('active');
}

function deleteService(id, name) {
    if (confirm('Are you sure you want to delete service: ' + name + '?')) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteForm').submit();
    }
}

// Category functions
function updateCategoryCode() {
    const name = document.getElementById('categoryName').value;
    if (name) {
        const code = name.toLowerCase()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_|_$/g, '');
        document.getElementById('categoryCode').value = code;
        updateFilenamePreview();
    }
}

function updateFilenamePreview() {
    const code = document.getElementById('categoryCode').value || 'internet_services';
    const cleanCode = code.toLowerCase().replace(/[^a-z0-9_]/g, '');
    document.getElementById('filenamePreview').textContent = cleanCode + '.php';
    document.getElementById('fullPathPreview').textContent = '../user/' + cleanCode + '.php';
}

function updateIconPreview() {
    const iconClass = document.getElementById('categoryIcon').value;
    const preview = document.getElementById('iconPreview');
    preview.className = 'fas ' + iconClass;
    document.getElementById('iconPreviewText').textContent = iconClass;
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('active');
    }
}

// Initialize icon preview
updateIconPreview();

// Form submission handling with loading states
document.getElementById('serviceForm')?.addEventListener('submit', function(e) {
    const btn = document.getElementById('serviceSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
});

document.getElementById('categoryForm')?.addEventListener('submit', function(e) {
    const btn = document.getElementById('categorySubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';
});

// Auto-hide alerts after 5 seconds
setTimeout(function() {
    document.querySelectorAll('.alert').forEach(function(alert) {
        alert.style.transition = 'opacity 0.5s';
        alert.style.opacity = '0';
        setTimeout(function() {
            if (alert.parentNode) {
                alert.remove();
            }
        }, 500);
    });
}, 5000);

// Add touch-friendly hover effects for mobile
if ('ontouchstart' in window) {
    document.querySelectorAll('.btn, .category-item').forEach(el => {
        el.addEventListener('touchstart', function() {
            this.style.transform = 'scale(0.98)';
        });
        el.addEventListener('touchend', function() {
            this.style.transform = '';
        });
    });
}

// Add keyboard support for modals
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal.active').forEach(modal => {
            modal.classList.remove('active');
        });
    }
});

// Responsive table handling
function checkTableOverflow() {
    const tables = document.querySelectorAll('.table-responsive');
    tables.forEach(table => {
        if (table.scrollWidth > table.clientWidth) {
            table.style.border = '2px solid var(--primary)';
        } else {
            table.style.border = 'none';
        }
    });
}

window.addEventListener('resize', function() {
    let resizeTimer;
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(checkTableOverflow, 250);
});

// Run on load
document.addEventListener('DOMContentLoaded', function() {
    checkTableOverflow();
});
</script>

<?php include 'admin_footer.php'; ?>