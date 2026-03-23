<?php
// admin/reports.php - Financial Reports (Responsive, Clean 2025 version)
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

Auth::requireAdmin();

$db = db();

// ─── Date Range ────────────────────────────────────────────────
$today      = date('Y-m-d');
$end_date   = $today;
$start_date = date('Y-m-d', strtotime('-30 days'));

if (!empty($_GET['start_date'])) $start_date = $_GET['start_date'];
if (!empty($_GET['end_date']))   $end_date   = $_GET['end_date'];

// ─── Queries ───────────────────────────────────────────────────
$daily = $db->query("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as transaction_count,
        COALESCE(SUM(CASE WHEN type = 'wallet_funding' THEN amount ELSE 0 END), 0) as funding_total,
        COALESCE(SUM(CASE WHEN type != 'wallet_funding' AND status = 'success' THEN amount ELSE 0 END), 0) as spending_total,
        COALESCE(SUM(CASE WHEN type != 'wallet_funding' AND status = 'success' THEN (amount * 0.05) ELSE 0 END), 0) as revenue
    FROM transactions 
    WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date'
    GROUP BY DATE(created_at)
    ORDER BY date DESC
")->fetch_all(MYSQLI_ASSOC);

$by_type = $db->query("
    SELECT 
        type,
        COUNT(*) as count,
        COALESCE(SUM(amount), 0) as total,
        COALESCE(SUM(CASE WHEN status = 'success' THEN amount ELSE 0 END), 0) as successful
    FROM transactions 
    WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date'
    GROUP BY type
    ORDER BY count DESC
")->fetch_all(MYSQLI_ASSOC);

$top_users = $db->query("
    SELECT 
        u.id, u.username, u.email,
        COUNT(t.id) as transaction_count,
        COALESCE(SUM(t.amount), 0) as total_spent
    FROM users u
    JOIN transactions t ON u.id = t.user_id
    WHERE t.status = 'success' 
      AND DATE(t.created_at) BETWEEN '$start_date' AND '$end_date'
    GROUP BY u.id
    ORDER BY total_spent DESC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// ─── Totals ────────────────────────────────────────────────────
$total_transactions = array_sum(array_column($daily, 'transaction_count')) ?? 0;
$total_funding      = array_sum(array_column($daily, 'funding_total'))      ?? 0;
$total_spending     = array_sum(array_column($daily, 'spending_total'))     ?? 0;
$total_revenue      = array_sum(array_column($daily, 'revenue'))            ?? 0;

$pageTitle = 'Financial Reports';
include 'admin_header.php';
?>

<style>
/* ───────────────────────────────────────────────────────────────
   REPORTS PAGE - Responsive & Dashboard-like styling
   ─────────────────────────────────────────────────────────────── */

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.25rem;
    margin: 1.5rem 0 2.5rem;
}

.stat-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.07);
    padding: 1.4rem;
    display: flex;
    align-items: center;
    gap: 1.1rem;
    transition: transform 0.18s ease;
}

.stat-card:hover {
    transform: translateY(-3px);
}

.stat-icon {
    width: 58px;
    height: 58px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.55rem;
    flex-shrink: 0;
}

.stat-info h3 {
    margin: 0;
    font-size: 0.94rem;
    color: #6b7280;
    font-weight: 500;
}

.stat-info p {
    margin: 0.35rem 0 0;
    font-size: 1.68rem;
    font-weight: 700;
    color: #1f2937;
}

/* Charts layout ─ similar to dashboard */
.charts-row {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.chart-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.07);
    overflow: hidden;
}

.chart-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.chart-header h3 {
    margin: 0;
    font-size: 1.18rem;
    color: #1f2937;
}

.chart-body {
    padding: 1rem;
    height: 360px;
}

/* Filter form */
.filter-form {
    display: flex;
    flex-wrap: wrap;
    gap: 1.25rem;
    align-items: flex-end;
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.06);
    margin-bottom: 2rem;
}

.filter-form .form-group {
    flex: 1;
    min-width: 200px;
}

.filter-form label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: #374151;
}

.filter-form .btn {
    padding: 0.75rem 1.5rem;
    min-width: 140px;
}

/* Tables */
.table-responsive {
    overflow-x: auto;
}

.data-table {
    width: 100%;
    min-width: 680px;
    border-collapse: collapse;
}

.data-table th,
.data-table td {
    padding: 0.95rem 1.1rem;
    text-align: left;
}

.data-table th {
    background: #f8fafc;
    font-weight: 600;
    color: #1f2937;
    white-space: nowrap;
}

.data-table tr:nth-child(even) {
    background: #f9fafb;
}

.positive { color: #10b981; font-weight: 600; }
.negative { color: #ef4444; }

/* ─── Media Queries ────────────────────────────────────────────── */
@media (max-width: 1200px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .charts-row {
        grid-template-columns: 1fr;
    }
    .chart-body {
        height: 340px;
    }
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    .filter-form {
        flex-direction: column;
        align-items: stretch;
    }
    .filter-form .form-group,
    .filter-form .btn {
        width: 100%;
    }
    .chart-body {
        height: 320px;
    }
}

@media (max-width: 576px) {
    .stat-card {
        padding: 1.1rem;
    }
    .stat-icon {
        width: 52px;
        height: 52px;
        font-size: 1.4rem;
    }
    .stat-info p {
        font-size: 1.45rem;
    }
}
</style>

<div class="container-fluid">
    <!-- Header -->
    <div class="content-header mb-4">
        <h2><i class="fas fa-chart-bar"></i> Financial Reports</h2>
        <p>Overview of platform financial activity</p>
    </div>

    <!-- Date Filter -->
    <form method="GET" class="filter-form">
        <div class="form-group">
            <label>Start Date</label>
            <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date) ?>">
        </div>
        <div class="form-group">
            <label>End Date</label>
            <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date) ?>">
        </div>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-filter"></i> Filter
        </button>
    </form>

    <!-- Summary Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #6366f1, #8b5cf6);">
                <i class="fas fa-exchange-alt"></i>
            </div>
            <div class="stat-info">
                <h3>Total Transactions</h3>
                <p><?= number_format($total_transactions) ?></p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                <i class="fas fa-arrow-down"></i>
            </div>
            <div class="stat-info">
                <h3>Total Funding</h3>
                <p><?= format_money($total_funding) ?></p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                <i class="fas fa-arrow-up"></i>
            </div>
            <div class="stat-info">
                <h3>Total Spending</h3>
                <p><?= format_money($total_spending) ?></p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-info">
                <h3>Total Revenue</h3>
                <p><?= format_money($total_revenue) ?></p>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="charts-row">
        <div class="chart-card">
            <div class="chart-header">
                <i class="fas fa-calendar-alt"></i>
                <h3>Daily Summary</h3>
            </div>
            <div class="chart-body">
                <canvas id="dailyChart"></canvas>
            </div>
        </div>

        <div class="chart-card">
            <div class="chart-header">
                <i class="fas fa-pie-chart"></i>
                <h3>Revenue Distribution</h3>
            </div>
            <div class="chart-body">
                <canvas id="revenuePie"></canvas>
            </div>
        </div>
    </div>

    <!-- Tables Row -->
    <div class="row g-4 mb-4">
        <!-- By Type -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-list"></i> By Transaction Type</h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Count</th>
                                    <th>Total Amount</th>
                                    <th>Successful</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($by_type as $row): ?>
                                <tr>
                                    <td><?= ucfirst(htmlspecialchars($row['type'])) ?></td>
                                    <td><?= number_format($row['count']) ?></td>
                                    <td><?= format_money($row['total']) ?></td>
                                    <td><?= format_money($row['successful']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($by_type)): ?>
                                <tr><td colspan="4" class="text-center py-4 text-muted">No data</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Users -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-trophy"></i> Top 10 Users</h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>Transactions</th>
                                    <th>Total Spent</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_users as $user): ?>
                                <tr>
                                    <td><?= htmlspecialchars($user['username']) ?></td>
                                    <td><?= number_format($user['transaction_count']) ?></td>
                                    <td><?= format_money($user['total_spent']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($top_users)): ?>
                                <tr><td colspan="3" class="text-center py-4 text-muted">No transactions</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Daily Breakdown -->
    <div class="card mb-4">
        <div class="card-header">
            <h3><i class="fas fa-table"></i> Daily Breakdown</h3>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Tx Count</th>
                            <th>Funding</th>
                            <th>Spending</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($daily as $day): ?>
                        <tr>
                            <td><strong><?= date('M d, Y', strtotime($day['date'])) ?></strong></td>
                            <td><?= number_format($day['transaction_count']) ?></td>
                            <td><?= format_money($day['funding_total']) ?></td>
                            <td><?= format_money($day['spending_total']) ?></td>
                            <td class="<?= $day['revenue'] > 0 ? 'positive' : '' ?>">
                                <?= format_money($day['revenue']) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($daily)): ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">No transactions in selected period</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Export -->
    <div class="d-flex gap-3 justify-content-end">
        <button class="btn btn-outline-primary" onclick="exportToCSV()">
            <i class="fas fa-file-csv me-2"></i>Export CSV
        </button>
        <button class="btn btn-outline-secondary" onclick="window.print()">
            <i class="fas fa-print me-2"></i>Print
        </button>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// ─── Daily Line Chart ───────────────────────────────────────────
const dailyCtx = document.getElementById('dailyChart')?.getContext('2d');
if (dailyCtx) {
    new Chart(dailyCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode(array_column(array_reverse($daily), 'date')) ?>,
            datasets: [
                {
                    label: 'Funding',
                    data: <?= json_encode(array_column(array_reverse($daily), 'funding_total')) ?>,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16,185,129,0.12)',
                    tension: 0.35,
                    fill: true
                },
                {
                    label: 'Spending',
                    data: <?= json_encode(array_column(array_reverse($daily), 'spending_total')) ?>,
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245,158,11,0.12)',
                    tension: 0.35,
                    fill: true
                },
                {
                    label: 'Revenue',
                    data: <?= json_encode(array_column(array_reverse($daily), 'revenue')) ?>,
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99,102,241,0.12)',
                    tension: 0.35,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'top' } },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
}

// ─── Revenue Pie Chart ──────────────────────────────────────────
const pieCtx = document.getElementById('revenuePie')?.getContext('2d');
if (pieCtx) {
    new Chart(pieCtx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_map(fn($r) => ucfirst($r['type']), $by_type)) ?>,
            datasets: [{
                data: <?= json_encode(array_column($by_type, 'successful')) ?>,
                backgroundColor: [
                    '#10b981', '#3b82f6', '#f59e0b', '#8b5cf6', 
                    '#ec4899', '#6366f1', '#f97316', '#14b8a6'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });
}

// ─── CSV Export ─────────────────────────────────────────────────
function exportToCSV() {
    let csv = "Date,Transactions,Funding,Spending,Revenue\n";
    <?php foreach ($daily as $d): ?>
        csv += "<?= $d['date'] ?>,<?= $d['transaction_count'] ?>,<?= $d['funding_total'] ?>,<?= $d['spending_total'] ?>,<?= $d['revenue'] ?>\n";
    <?php endforeach; ?>
    
    if (csv === "Date,Transactions,Funding,Spending,Revenue\n") {
        alert("No data available to export.");
        return;
    }

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = `report-<?= $start_date ?>-to-<?= $end_date ?>.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php include 'admin_footer.php'; ?>