<?php
// admin/dashboard.php - Admin Dashboard
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

Auth::requireAdmin();

$db = db();

// Get statistics
$stats = [];

// Total users
$result = $db->query("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
$stats['total_users'] = $result->fetch_assoc()['total'];

// Total transactions today
$result = $db->query("SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total FROM transactions WHERE DATE(created_at) = CURDATE()");
$today = $result->fetch_assoc();
$stats['today_transactions'] = $today['count'];
$stats['today_revenue'] = $today['total'];

// Total transactions overall
$result = $db->query("SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total FROM transactions WHERE status = 'success'");
$stats['total_transactions'] = $result->fetch_assoc();

// Pending transactions
$result = $db->query("SELECT COUNT(*) as count FROM transactions WHERE status = 'pending'");
$stats['pending_transactions'] = $result->fetch_assoc()['count'];

// Total wallet balance
$result = $db->query("SELECT COALESCE(SUM(wallet_balance), 0) as total FROM users");
$stats['total_balance'] = $result->fetch_assoc()['total'];

// Recent users
$recent_users = $db->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

// Recent transactions
$recent_transactions = $db->query("SELECT t.*, u.username FROM transactions t JOIN users u ON t.user_id = u.id ORDER BY t.created_at DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Admin Dashboard';
include 'admin_header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="content-header">
        <h2><i class="fas fa-tachometer-alt"></i> Dashboard</h2>
        <p>Welcome back, <?php echo htmlspecialchars(Session::username()); ?>!</p>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid" style="grid-template-columns: repeat(4, 1fr);">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #6366f1, #8b5cf6);">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-info">
                <h3>Total Users</h3>
                <p><?php echo $stats['total_users']; ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                <i class="fas fa-exchange-alt"></i>
            </div>
            <div class="stat-info">
                <h3>Transactions Today</h3>
                <p><?php echo $stats['today_transactions']; ?></p>
                <small><?php echo format_money($stats['today_revenue']); ?></small>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-info">
                <h3>Pending</h3>
                <p><?php echo $stats['pending_transactions']; ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
                <i class="fas fa-wallet"></i>
            </div>
            <div class="stat-info">
                <h3>Total Balance</h3>
                <p><?php echo format_money($stats['total_balance']); ?></p>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
        <!-- Revenue Chart -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-chart-line"></i> Revenue (Last 7 Days)</h3>
            </div>
            <div style="height: 300px; display: flex; align-items: center; justify-content: center; background: var(--light); border-radius: var(--radius);">
                <canvas id="revenueChart" style="width: 100%; height: 100%;"></canvas>
            </div>
        </div>
        
        <!-- Transaction Distribution -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-chart-pie"></i> Transactions by Type</h3>
            </div>
            <div style="height: 300px; display: flex; align-items: center; justify-content: center; background: var(--light); border-radius: var(--radius);">
                <canvas id="typeChart" style="width: 100%; height: 100%;"></canvas>
            </div>
        </div>
    </div>

    <!-- Recent Users & Transactions -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
        <!-- Recent Users -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-user-plus"></i> Recent Users</h3>
                <a href="users.php" class="btn btn-light btn-small">View All</a>
            </div>
            
            <div class="transaction-list">
                <?php foreach ($recent_users as $user): ?>
                    <div class="transaction-item">
                        <div class="transaction-left">
                            <div class="transaction-icon" style="background: #dbeafe;">
                                <i class="fas fa-user" style="color: #3b82f6;"></i>
                            </div>
                            <div class="transaction-details">
                                <h4><?php echo htmlspecialchars($user['username']); ?></h4>
                                <p><?php echo $user['email']; ?> • <?php echo time_ago($user['created_at']); ?></p>
                            </div>
                        </div>
                        <span class="badge badge-<?php echo $user['status']; ?>">
                            <?php echo ucfirst($user['status']); ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Recent Transactions -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> Recent Transactions</h3>
                <a href="transactions.php" class="btn btn-light btn-small">View All</a>
            </div>
            
            <div class="transaction-list">
                <?php foreach ($recent_transactions as $t): ?>
                    <div class="transaction-item">
                        <div class="transaction-left">
                            <div class="transaction-icon">
                                <?php
                                $icon = 'fa-exchange-alt';
                                $color = '#6366f1';
                                if ($t['type'] == 'airtime') { $icon = 'fa-phone-alt'; $color = '#10b981'; }
                                elseif ($t['type'] == 'data') { $icon = 'fa-wifi'; $color = '#3b82f6'; }
                                elseif ($t['type'] == 'electricity') { $icon = 'fa-bolt'; $color = '#f59e0b'; }
                                elseif ($t['type'] == 'cable') { $icon = 'fa-tv'; $color = '#8b5cf6'; }
                                elseif ($t['type'] == 'wallet_funding') { $icon = 'fa-plus-circle'; $color = '#10b981'; }
                                ?>
                                <i class="fas <?php echo $icon; ?>" style="color: <?php echo $color; ?>;"></i>
                            </div>
                            <div class="transaction-details">
                                <h4><?php echo ucfirst($t['type']); ?> by <?php echo htmlspecialchars($t['username']); ?></h4>
                                <p><?php echo $t['transaction_id']; ?> • <?php echo time_ago($t['created_at']); ?></p>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <span class="transaction-amount <?php echo $t['type'] == 'wallet_funding' ? 'positive' : 'negative'; ?>">
                                <?php echo $t['type'] == 'wallet_funding' ? '+' : '-'; ?>
                                <?php echo format_money($t['amount']); ?>
                            </span>
                            <br>
                            <span class="badge badge-<?php echo $t['status']; ?>">
                                <?php echo ucfirst($t['status']); ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 1rem;">
            <a href="users.php?action=add" class="quick-action-item">
                <div class="quick-action-icon"><i class="fas fa-user-plus"></i></div>
                <span>Add User</span>
            </a>
            <a href="transactions.php" class="quick-action-item">
                <div class="quick-action-icon"><i class="fas fa-search"></i></div>
                <span>View Transactions</span>
            </a>
            <a href="api_settings.php" class="quick-action-item">
                <div class="quick-action-icon"><i class="fas fa-key"></i></div>
                <span>API Settings</span>
            </a>
            <a href="settings.php" class="quick-action-item">
                <div class="quick-action-icon"><i class="fas fa-cog"></i></div>
                <span>System Settings</span>
            </a>
            <a href="reports.php" class="quick-action-item">
                <div class="quick-action-icon"><i class="fas fa-file-alt"></i></div>
                <span>Reports</span>
            </a>
        </div>
    </div>
</div>

<style>
/* ==========================================================================
   DASHBOARD SPECIFIC STYLES
   ========================================================================== */

/* Additional Dashboard Styles */
.stats-grid {
    margin-bottom: 1.5rem;
}

.stat-card {
    position: relative;
    overflow: hidden;
    transition: transform var(--transition-fast), box-shadow var(--transition-fast);
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-lg);
}

.stat-card::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: inherit;
    opacity: 0.5;
}

.stat-icon {
    transition: transform var(--transition-fast);
}

.stat-card:hover .stat-icon {
    transform: scale(1.05);
}

.stat-info small {
    display: block;
    margin-top: 0.25rem;
}

/* Transaction List Styles */
.transaction-list {
    max-height: 500px;
    overflow-y: auto;
}

.transaction-list::-webkit-scrollbar {
    width: 4px;
}

.transaction-list::-webkit-scrollbar-track {
    background: var(--gray-soft);
    border-radius: var(--radius-full);
}

.transaction-list::-webkit-scrollbar-thumb {
    background: var(--gray);
    border-radius: var(--radius-full);
}

.transaction-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem;
    border-bottom: 1px solid var(--gray-soft);
    transition: background var(--transition-fast);
}

.transaction-item:last-child {
    border-bottom: none;
}

.transaction-item:hover {
    background: var(--light);
}

.transaction-left {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex: 1;
}

.transaction-icon {
    width: 40px;
    height: 40px;
    background: var(--light);
    border-radius: var(--radius-full);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: transform var(--transition-fast);
}

.transaction-item:hover .transaction-icon {
    transform: scale(1.05);
}

.transaction-icon i {
    font-size: 1rem;
}

.transaction-details {
    flex: 1;
}

.transaction-details h4 {
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 0.25rem;
    color: var(--dark);
}

.transaction-details p {
    font-size: 0.75rem;
    color: var(--gray);
    margin-bottom: 0;
}

.transaction-amount {
    font-weight: 700;
    font-size: 0.9rem;
    display: inline-block;
}

.transaction-amount.positive {
    color: var(--success);
}

.transaction-amount.negative {
    color: var(--danger);
}

/* Quick Actions Styles */
.quick-action-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
    padding: 1.25rem 0.5rem;
    background: var(--white);
    border: 1px solid var(--gray-soft);
    border-radius: var(--radius-md);
    text-decoration: none;
    transition: all var(--transition-fast);
    text-align: center;
}

.quick-action-item:hover {
    transform: translateY(-3px);
    border-color: var(--primary);
    box-shadow: var(--shadow-md);
    text-decoration: none;
}

.quick-action-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, rgba(99,102,241,0.1), rgba(139,92,246,0.1));
    border-radius: var(--radius-full);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all var(--transition-fast);
}

.quick-action-item:hover .quick-action-icon {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
}

.quick-action-icon i {
    font-size: 1.25rem;
    color: var(--primary);
    transition: color var(--transition-fast);
}

.quick-action-item:hover .quick-action-icon i {
    color: white;
}

.quick-action-item span {
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--dark);
    transition: color var(--transition-fast);
}

.quick-action-item:hover span {
    color: var(--primary);
}

/* Chart Container Enhancements */
.card canvas {
    max-height: 100%;
    width: 100% !important;
}

/* Badge Styles Enhancement */
.badge {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.25rem 0.75rem;
    border-radius: var(--radius-full);
    font-size: 0.6875rem;
    font-weight: 600;
    line-height: 1;
}

.badge-active,
.badge-success {
    background: var(--success-light);
    color: var(--success-dark);
}

.badge-pending {
    background: var(--warning-light);
    color: var(--warning-dark);
}

.badge-failed,
.badge-suspended,
.badge-inactive {
    background: var(--danger-light);
    color: var(--danger-dark);
}

/* Card Header Button */
.btn-light {
    background: var(--light);
    color: var(--dark);
    border: 1px solid var(--gray-soft);
    transition: all var(--transition-fast);
}

.btn-light:hover {
    background: var(--white);
    border-color: var(--primary);
    color: var(--primary);
}

.btn-small {
    padding: 0.375rem 0.875rem;
    font-size: 0.75rem;
}

/* Empty State (if no data) */
.empty-state {
    text-align: center;
    padding: 3rem;
    color: var(--gray);
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-state p {
    font-size: 0.875rem;
    margin-bottom: 0;
}

/* Loading State for Charts */
.chart-loading {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: var(--gray);
}

/* Animation for Cards */
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

.stat-card,
.transaction-item,
.quick-action-item {
    animation: fadeInUp 0.4s ease-out forwards;
}

.stat-card:nth-child(1) { animation-delay: 0.05s; }
.stat-card:nth-child(2) { animation-delay: 0.1s; }
.stat-card:nth-child(3) { animation-delay: 0.15s; }
.stat-card:nth-child(4) { animation-delay: 0.2s; }

.transaction-item:nth-child(1) { animation-delay: 0.05s; }
.transaction-item:nth-child(2) { animation-delay: 0.1s; }
.transaction-item:nth-child(3) { animation-delay: 0.15s; }
.transaction-item:nth-child(4) { animation-delay: 0.2s; }
.transaction-item:nth-child(5) { animation-delay: 0.25s; }

/* ==========================================================================
   RESPONSIVE ADJUSTMENTS
   ========================================================================== */

@media (max-width: 1024px) {
    .stats-grid[style*="grid-template-columns: repeat(4, 1fr)"] {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 1rem;
    }
    
    [style*="grid-template-columns: 2fr 1fr"] {
        grid-template-columns: 1fr !important;
        gap: 1rem;
    }
    
    [style*="grid-template-columns: 1fr 1fr"] {
        grid-template-columns: 1fr !important;
        gap: 1rem;
    }
    
    [style*="grid-template-columns: repeat(5, 1fr)"] {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 1rem;
    }
    
    .transaction-item {
        flex-direction: column;
        text-align: center;
        gap: 0.75rem;
    }
    
    .transaction-left {
        flex-direction: column;
        text-align: center;
    }
    
    .transaction-details p {
        text-align: center;
    }
    
    [style*="text-align: right"] {
        text-align: center !important;
    }
}

@media (max-width: 768px) {
    .stats-grid[style*="grid-template-columns: repeat(4, 1fr)"] {
        grid-template-columns: 1fr !important;
    }
    
    [style*="grid-template-columns: repeat(5, 1fr)"] {
        grid-template-columns: 1fr !important;
    }
    
    .stat-card {
        flex-direction: row !important;
        text-align: left !important;
    }
    
    .stat-icon {
        margin: 0 !important;
    }
    
    .stat-info {
        margin-left: 1rem !important;
        text-align: left !important;
    }
    
    .transaction-item {
        flex-direction: row !important;
        text-align: left !important;
    }
    
    .transaction-left {
        flex-direction: row !important;
        text-align: left !important;
    }
    
    .transaction-details p {
        text-align: left !important;
    }
    
    [style*="text-align: right"] {
        text-align: right !important;
    }
    
    .quick-action-item {
        flex-direction: row !important;
        justify-content: center;
        gap: 1rem;
        padding: 1rem;
    }
    
    .quick-action-icon {
        width: 40px;
        height: 40px;
    }
    
    .card-header {
        flex-direction: row !important;
        justify-content: space-between;
        align-items: center;
    }
}

@media (max-width: 576px) {
    .stat-card {
        flex-direction: column !important;
        text-align: center !important;
    }
    
    .stat-info {
        margin-left: 0 !important;
        text-align: center !important;
    }
    
    .stat-icon {
        margin: 0 auto !important;
    }
    
    .transaction-item {
        flex-direction: column !important;
        text-align: center !important;
    }
    
    .transaction-left {
        flex-direction: column !important;
        text-align: center !important;
    }
    
    .transaction-details p {
        text-align: center !important;
    }
    
    [style*="text-align: right"] {
        text-align: center !important;
    }
    
    .card-header {
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 0.75rem;
    }
    
    .quick-action-item {
        flex-direction: column !important;
    }
}

/* Print Styles */
@media print {
    .stats-grid,
    [style*="grid-template-columns: 2fr 1fr"],
    [style*="grid-template-columns: 1fr 1fr"],
    [style*="grid-template-columns: repeat(5, 1fr)"] {
        display: block !important;
    }
    
    .stat-card,
    .card,
    .quick-action-item {
        break-inside: avoid;
        page-break-inside: avoid;
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }
    
    .btn,
    .quick-action-item,
    .transaction-item a {
        display: none !important;
    }
    
    canvas {
        max-width: 100% !important;
        height: auto !important;
    }
}

/* Hover Effects for Interactive Elements */
.card {
    transition: box-shadow var(--transition-fast);
}

.card:hover {
    box-shadow: var(--shadow-md);
}

/* Chart Tooltip Customization */
.chartjs-tooltip {
    background: var(--dark) !important;
    color: white !important;
    border-radius: var(--radius-sm) !important;
    padding: 0.5rem !important;
    font-size: 0.75rem !important;
}

/* Focus States for Accessibility */
.quick-action-item:focus,
.btn-light:focus,
.transaction-item:focus-within {
    outline: 2px solid var(--primary);
    outline-offset: 2px;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Revenue Chart
const ctx1 = document.getElementById('revenueChart').getContext('2d');
new Chart(ctx1, {
    type: 'line',
    data: {
        labels: ['Day 1', 'Day 2', 'Day 3', 'Day 4', 'Day 5', 'Day 6', 'Today'],
        datasets: [{
            label: 'Revenue (₦)',
            data: [12000, 19000, 15000, 25000, 22000, 30000, 28000],
            borderColor: '#6366f1',
            backgroundColor: 'rgba(99, 102, 241, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        }
    }
});

// Transaction Type Chart
const ctx2 = document.getElementById('typeChart').getContext('2d');
new Chart(ctx2, {
    type: 'doughnut',
    data: {
        labels: ['Airtime', 'Data', 'Electricity', 'Cable TV', 'Funding'],
        datasets: [{
            data: [30, 25, 20, 15, 10],
            backgroundColor: [
                '#10b981',
                '#3b82f6',
                '#f59e0b',
                '#8b5cf6',
                '#6366f1'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
</script>

<?php include 'admin_footer.php'; ?>