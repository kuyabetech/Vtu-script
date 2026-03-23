<?php
// user/transactions.php - Transaction History Page (Modern Responsive)
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

Auth::requireLogin();

$db = db();
$userId = Session::userId();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get total transactions count
$stmt = $db->prepare("SELECT COUNT(*) as total FROM transactions WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($total / $limit);

// Get transactions with pagination
$stmt = $db->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param("iii", $userId, $limit, $offset);
$stmt->execute();
$transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get summary by type
$stmt = $db->prepare("SELECT type, COUNT(*) as count, SUM(amount) as total FROM transactions WHERE user_id = ? AND status = 'success' GROUP BY type");
$stmt->bind_param("i", $userId);
$stmt->execute();
$summary = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Transactions';
include '../partials/user_header.php';
?>

<style>
/* ===== Modern Transactions Page Styles ===== */

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

/* Summary Cards - Modern Design */
.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.summary-card {
    background: white;
    border-radius: var(--radius-xl);
    padding: 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: var(--shadow);
    transition: all 0.3s ease;
    border: 1px solid var(--gray-light);
    position: relative;
    overflow: hidden;
}

.summary-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary), var(--secondary));
}

.summary-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-lg);
}

.summary-icon {
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

.summary-content {
    flex: 1;
}

.summary-content h4 {
    font-size: 0.875rem;
    color: var(--gray);
    margin-bottom: 0.25rem;
}

.summary-content .amount {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 0.25rem;
}

.summary-content small {
    font-size: 0.7rem;
    color: var(--gray);
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

/* Filter Bar - Modern */
.filter-bar {
    background: white;
    border-radius: var(--radius-xl);
    padding: 1.25rem;
    margin-bottom: 1.5rem;
    box-shadow: var(--shadow);
    border: 1px solid var(--gray-light);
}

.filter-grid {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    align-items: center;
}

.filter-search {
    flex: 1;
    min-width: 250px;
}

.filter-actions {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.filter-select {
    padding: 0.75rem 2rem 0.75rem 1rem;
    border: 2px solid var(--gray-light);
    border-radius: var(--radius-lg);
    font-size: 0.95rem;
    color: var(--dark);
    background: white;
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 0.75rem center;
    background-size: 1rem;
}

.filter-select:focus {
    outline: none;
    border-color: var(--primary);
}

/* Transactions Card */
.transactions-card {
    background: white;
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow);
    border: 1px solid var(--gray-light);
    overflow: hidden;
}

.transactions-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.25rem 1.5rem;
    border-bottom: 2px solid var(--gray-light);
    flex-wrap: wrap;
    gap: 1rem;
}

.transactions-header h3 {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 1.25rem;
    color: var(--dark);
    margin: 0;
}

.transactions-header h3 i {
    color: var(--primary);
}

.transaction-count {
    background: var(--light);
    padding: 0.5rem 1rem;
    border-radius: 2rem;
    font-size: 0.875rem;
    color: var(--gray);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* Transaction Items */
.transaction-list {
    padding: 1rem;
}

.transaction-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem;
    border-radius: var(--radius-lg);
    transition: all 0.3s ease;
    margin-bottom: 0.5rem;
    background: var(--light);
    border: 1px solid transparent;
    cursor: pointer;
    flex-wrap: wrap;
    gap: 1rem;
}

.transaction-item:last-child {
    margin-bottom: 0;
}

.transaction-item:hover {
    background: white;
    border-color: var(--primary);
    transform: translateX(5px);
    box-shadow: var(--shadow);
}

.transaction-left {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex: 1;
    min-width: 250px;
}

.transaction-icon {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-radius: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
    box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
}

.transaction-details {
    flex: 1;
}

.transaction-details h4 {
    font-size: 1rem;
    margin-bottom: 0.35rem;
    color: var(--dark);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.transaction-details p {
    font-size: 0.85rem;
    color: var(--gray);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.transaction-details p i {
    font-size: 0.75rem;
    color: var(--primary);
}

.transaction-right {
    text-align: right;
    min-width: 120px;
}

.transaction-amount {
    font-size: 1.1rem;
    font-weight: 700;
    display: block;
    margin-bottom: 0.25rem;
}

.transaction-amount.positive {
    color: var(--success);
}

.transaction-amount.negative {
    color: var(--danger);
}

/* Status Badges */
.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.35rem 1rem;
    border-radius: 2rem;
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.3px;
}

.status-badge.success {
    background: #d1fae5;
    color: #065f46;
}

.status-badge.pending {
    background: #fef3c7;
    color: #92400e;
}

.status-badge.failed {
    background: #fee2e2;
    color: #991b1b;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--gray);
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.5;
    color: var(--primary);
}

.empty-state h3 {
    font-size: 1.25rem;
    margin-bottom: 0.5rem;
    color: var(--dark);
}

.empty-state p {
    margin-bottom: 1.5rem;
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    margin-top: 2rem;
    flex-wrap: wrap;
}

.pagination-btn {
    min-width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 0.75rem;
    background: white;
    border: 2px solid var(--gray-light);
    border-radius: var(--radius-lg);
    color: var(--dark);
    font-weight: 500;
    text-decoration: none;
    transition: all 0.3s ease;
}

.pagination-btn:hover {
    border-color: var(--primary);
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));
}

.pagination-btn.active {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-color: transparent;
    color: white;
    box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
}

.pagination-btn.disabled {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

/* Responsive Design */
@media (max-width: 768px) {
    .filter-grid {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-search {
        width: 100%;
    }
    
    .filter-actions {
        width: 100%;
    }
    
    .filter-select {
        flex: 1;
    }
    
    .transaction-item {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .transaction-left {
        width: 100%;
    }
    
    .transaction-right {
        width: 100%;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .transactions-header {
        flex-direction: column;
        align-items: flex-start;
    }
}

@media (max-width: 480px) {
    .summary-grid {
        grid-template-columns: 1fr;
    }
    
    .filter-actions {
        flex-direction: column;
    }
    
    .transaction-details h4 {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
    }
    
    .transaction-details p {
        flex-wrap: wrap;
    }
}

/* Animation */
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.transaction-item {
    animation: slideIn 0.3s ease forwards;
}

.transaction-item:nth-child(1) { animation-delay: 0.05s; }
.transaction-item:nth-child(2) { animation-delay: 0.1s; }
.transaction-item:nth-child(3) { animation-delay: 0.15s; }
.transaction-item:nth-child(4) { animation-delay: 0.2s; }
.transaction-item:nth-child(5) { animation-delay: 0.25s; }
.transaction-item:nth-child(6) { animation-delay: 0.3s; }
.transaction-item:nth-child(7) { animation-delay: 0.35s; }
.transaction-item:nth-child(8) { animation-delay: 0.4s; }
.transaction-item:nth-child(9) { animation-delay: 0.45s; }
.transaction-item:nth-child(10) { animation-delay: 0.5s; }

/* Loading Skeleton */
.skeleton {
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: shimmer 1.5s infinite;
}

@keyframes shimmer {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

/* Touch-friendly */
@media (max-width: 768px) {
    .transaction-item {
        min-height: 70px;
    }
    
    .pagination-btn {
        min-width: 44px;
        height: 44px;
    }
}
</style>

<!-- Main Content -->
<div class="main-content">
    <div class="container">
        <!-- Page Header -->
        <div class="content-header">
            <h2>
                <i class="fas fa-history"></i>
                <span>Transaction History</span>
            </h2>
            <div class="transaction-count">
                <i class="fas fa-list"></i>
                <span><?php echo $total; ?> Total Transactions</span>
            </div>
        </div>

        <!-- Summary Cards -->
        <?php if (!empty($summary)): ?>
            <div class="summary-grid">
                <?php foreach ($summary as $s): ?>
                    <div class="summary-card">
                        <div class="summary-icon">
                            <?php
                            $icon = 'fa-exchange-alt';
                            if ($s['type'] == 'airtime') $icon = 'fa-phone-alt';
                            elseif ($s['type'] == 'data') $icon = 'fa-wifi';
                            elseif ($s['type'] == 'electricity') $icon = 'fa-bolt';
                            elseif ($s['type'] == 'cable') $icon = 'fa-tv';
                            elseif ($s['type'] == 'wallet_funding') $icon = 'fa-plus-circle';
                            ?>
                            <i class="fas <?php echo $icon; ?>"></i>
                        </div>
                        <div class="summary-content">
                            <h4><?php echo ucfirst($s['type']); ?></h4>
                            <div class="amount"><?php echo format_money($s['total']); ?></div>
                            <small>
                                <i class="fas fa-exchange-alt"></i>
                                <?php echo $s['count']; ?> transactions
                            </small>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <div class="filter-grid">
                <div class="filter-search">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control" id="searchInput" placeholder="Search by reference, phone, meter...">
                    </div>
                </div>
                <div class="filter-actions">
                    <select class="filter-select" id="typeFilter">
                        <option value="">All Types</option>
                        <option value="airtime">Airtime</option>
                        <option value="data">Data</option>
                        <option value="electricity">Electricity</option>
                        <option value="cable">Cable TV</option>
                        <option value="wallet_funding">Wallet Funding</option>
                    </select>
                    <select class="filter-select" id="statusFilter">
                        <option value="">All Status</option>
                        <option value="success">Success</option>
                        <option value="pending">Pending</option>
                        <option value="failed">Failed</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Transactions List -->
        <div class="transactions-card">
            <div class="transactions-header">
                <h3>
                    <i class="fas fa-list"></i>
                    All Transactions
                </h3>
                <?php if (!empty($transactions)): ?>
                    <span class="transaction-count">
                        <i class="fas fa-layer-group"></i>
                        Page <?php echo $page; ?> of <?php echo $totalPages; ?>
                    </span>
                <?php endif; ?>
            </div>
            
            <?php if (empty($transactions)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>No Transactions Yet</h3>
                    <p>Your transaction history will appear here once you make your first purchase.</p>
                    <a href="buy_airtime.php" class="btn btn-primary">
                        <i class="fas fa-shopping-cart"></i> Make Your First Purchase
                    </a>
                </div>
            <?php else: ?>
                <div class="transaction-list" id="transactionList">
                    <?php foreach ($transactions as $t): ?>
                        <a href="transaction_details.php?id=<?php echo $t['id']; ?>" class="transaction-item" data-type="<?php echo $t['type']; ?>" data-status="<?php echo $t['status']; ?>">
                            <div class="transaction-left">
                                <div class="transaction-icon">
                                    <?php
                                    $icon = 'fa-exchange-alt';
                                    if ($t['type'] == 'airtime') $icon = 'fa-phone-alt';
                                    elseif ($t['type'] == 'data') $icon = 'fa-wifi';
                                    elseif ($t['type'] == 'electricity') $icon = 'fa-bolt';
                                    elseif ($t['type'] == 'cable') $icon = 'fa-tv';
                                    elseif ($t['type'] == 'wallet_funding') $icon = 'fa-plus-circle';
                                    ?>
                                    <i class="fas <?php echo $icon; ?>"></i>
                                </div>
                                <div class="transaction-details">
                                    <h4>
                                        <?php echo ucfirst($t['type']); ?>
                                        <?php if ($t['network']): ?>
                                            <span style="color: var(--primary);">• <?php echo strtoupper($t['network']); ?></span>
                                        <?php endif; ?>
                                    </h4>
                                    <p>
                                        <i class="fas fa-hashtag"></i> <?php echo substr($t['transaction_id'], 0, 12); ?>...
                                        <?php if ($t['phone_number']): ?>
                                            <span>•</span>
                                            <i class="fas fa-phone"></i> <?php echo $t['phone_number']; ?>
                                        <?php elseif ($t['meter_number']): ?>
                                            <span>•</span>
                                            <i class="fas fa-bolt"></i> <?php echo $t['meter_number']; ?>
                                        <?php elseif ($t['smart_card']): ?>
                                            <span>•</span>
                                            <i class="fas fa-tv"></i> <?php echo $t['smart_card']; ?>
                                        <?php endif; ?>
                                        <span>•</span>
                                        <i class="far fa-clock"></i> <?php echo time_ago($t['created_at']); ?>
                                    </p>
                                </div>
                            </div>
                            <div class="transaction-right">
                                <span class="transaction-amount <?php echo $t['type'] == 'wallet_funding' ? 'positive' : 'negative'; ?>">
                                    <?php echo $t['type'] == 'wallet_funding' ? '+' : '-'; ?>
                                    <?php echo format_money($t['amount']); ?>
                                </span>
                                <span class="status-badge <?php echo $t['status']; ?>">
                                    <?php echo ucfirst($t['status']); ?>
                                </span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div style="padding: 1.5rem; border-top: 2px solid var(--gray-light);">
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page-1; ?>" class="pagination-btn">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php else: ?>
                                <span class="pagination-btn disabled">
                                    <i class="fas fa-chevron-left"></i>
                                </span>
                            <?php endif; ?>
                            
                            <?php
                            $start = max(1, $page - 2);
                            $end = min($totalPages, $page + 2);
                            
                            if ($start > 1) {
                                echo '<a href="?page=1" class="pagination-btn">1</a>';
                                if ($start > 2) {
                                    echo '<span class="pagination-btn disabled">...</span>';
                                }
                            }
                            
                            for ($i = $start; $i <= $end; $i++): ?>
                                <a href="?page=<?php echo $i; ?>" class="pagination-btn <?php echo $i == $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor;
                            
                            if ($end < $totalPages) {
                                if ($end < $totalPages - 1) {
                                    echo '<span class="pagination-btn disabled">...</span>';
                                }
                                echo '<a href="?page=' . $totalPages . '" class="pagination-btn">' . $totalPages . '</a>';
                            }
                            ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?php echo $page+1; ?>" class="pagination-btn">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php else: ?>
                                <span class="pagination-btn disabled">
                                    <i class="fas fa-chevron-right"></i>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
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
    <a href="transactions.php" class="bottom-nav-item active">
        <i class="fas fa-history"></i>
        <span>History</span>
    </a>
    <a href="profile.php" class="bottom-nav-item">
        <i class="fas fa-user"></i>
        <span>Profile</span>
    </a>
</nav>

<script>
// Filter functionality
const searchInput = document.getElementById('searchInput');
const typeFilter = document.getElementById('typeFilter');
const statusFilter = document.getElementById('statusFilter');
const transactionItems = document.querySelectorAll('.transaction-item');

function filterTransactions() {
    const searchTerm = searchInput.value.toLowerCase();
    const type = typeFilter.value;
    const status = statusFilter.value;
    let visibleCount = 0;
    
    transactionItems.forEach(item => {
        const text = item.textContent.toLowerCase();
        const itemType = item.dataset.type;
        const itemStatus = item.dataset.status;
        
        let show = true;
        
        if (searchTerm && !text.includes(searchTerm)) {
            show = false;
        }
        
        if (type && itemType !== type) {
            show = false;
        }
        
        if (status && itemStatus !== status) {
            show = false;
        }
        
        item.style.display = show ? 'flex' : 'none';
        if (show) visibleCount++;
    });
    
    // Show empty state if no items visible
    const emptyState = document.querySelector('.empty-state');
    if (emptyState) {
        if (visibleCount === 0 && transactionItems.length > 0) {
            if (!document.getElementById('noResults')) {
                const noResults = document.createElement('div');
                noResults.id = 'noResults';
                noResults.className = 'empty-state';
                noResults.innerHTML = `
                    <i class="fas fa-search"></i>
                    <h3>No Matching Transactions</h3>
                    <p>Try adjusting your search or filters</p>
                `;
                document.querySelector('.transaction-list').appendChild(noResults);
            }
        } else {
            const noResults = document.getElementById('noResults');
            if (noResults) noResults.remove();
        }
    }
}

// Debounce search input
let searchTimeout;
searchInput.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(filterTransactions, 300);
});

typeFilter.addEventListener('change', filterTransactions);
statusFilter.addEventListener('change', filterTransactions);

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

document.querySelectorAll('.summary-card').forEach(el => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(20px)';
    el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
    observer.observe(el);
});

// Touch-friendly hover effects for mobile
if ('ontouchstart' in window) {
    document.querySelectorAll('.transaction-item, .summary-card').forEach(el => {
        el.addEventListener('touchstart', function() {
            this.style.transform = 'scale(0.98)';
        });
        el.addEventListener('touchend', function() {
            this.style.transform = '';
        });
    });
}

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    // Add any initialization code here
});
</script>

</body>
</html>