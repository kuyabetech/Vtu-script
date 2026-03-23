<?php
// admin/transactions.php - Regenerated 2026 version
// Uses ONLY 'transactions' table for history

require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

Auth::requireAdmin();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = db();

// ==============================
// AUTO-FIX wallet_id column in transactions (if needed)
// ==============================
try {
    $col_check = $db->query("
        SELECT COUNT(*) as cnt 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
          AND TABLE_NAME = 'transactions' 
          AND COLUMN_NAME = 'wallet_id'
    ");
    $col_exists = $col_check->fetch_assoc()['cnt'] ?? 0;

    if (!$col_exists) {
        $db->query("ALTER TABLE transactions ADD COLUMN wallet_id INT UNSIGNED NULL AFTER user_id");
        error_log("[AUTO-FIX] Added wallet_id column to transactions");
    } else {
        $null_check = $db->query("
            SELECT IS_NULLABLE 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
              AND TABLE_NAME = 'transactions' 
              AND COLUMN_NAME = 'wallet_id'
        ");
        $is_nullable = $null_check->fetch_assoc()['IS_NULLABLE'] ?? 'NO';

        if ($is_nullable === 'NO') {
            error_log("[AUTO-FIX] Making wallet_id nullable");
            $db->query("SET FOREIGN_KEY_CHECKS = 0");

            // Drop FK if exists
            $fk = $db->query("
                SELECT CONSTRAINT_NAME 
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'transactions'
                  AND COLUMN_NAME = 'wallet_id'
                  AND REFERENCED_TABLE_NAME IS NOT NULL
            ")->fetch_assoc();

            if ($fk) {
                $db->query("ALTER TABLE transactions DROP FOREIGN KEY `{$fk['CONSTRAINT_NAME']}`");
            }

            $db->query("ALTER TABLE transactions MODIFY wallet_id INT UNSIGNED NULL");

            // Re-add FK safely
            try {
                $db->query("
                    ALTER TABLE transactions 
                    ADD CONSTRAINT fk_tx_wallet 
                    FOREIGN KEY (wallet_id) REFERENCES wallets(id) ON DELETE SET NULL
                ");
            } catch (Exception $e) {
                error_log("FK re-add skipped: " . $e->getMessage());
            }

            $db->query("SET FOREIGN_KEY_CHECKS = 1");
        }
    }
} catch (Exception $e) {
    error_log("wallet_id auto-fix failed: " . $e->getMessage());
}

// ==============================
// HANDLE FUNDING APPROVE / REJECT
// ==============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!Session::verifyCSRF($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }

    $action     = $_POST['action'];
    $request_id = (int)($_POST['request_id'] ?? 0);

// ===== Approve funding =====
if ($action === 'approve_funding') {

    $stmt = $db->prepare("SELECT * FROM funding_requests WHERE id = ? AND status = 'pending'");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $request = $stmt->get_result()->fetch_assoc();

    if ($request) {
        $db->begin_transaction();

        try {
            // 1. Ensure wallet exists
            $stmt = $db->prepare("SELECT id FROM wallets WHERE user_id = ?");
            $stmt->bind_param("i", $request['user_id']);
            $stmt->execute();
            $wallet = $stmt->get_result()->fetch_assoc();

            if ($wallet) {
                $wallet_id = (int)$wallet['id'];
            } else {
                $stmt = $db->prepare("
                    INSERT INTO wallets (user_id, balance, created_at, updated_at)
                    VALUES (?, 0, NOW(), NOW())
                ");
                $stmt->bind_param("i", $request['user_id']);
                $stmt->execute();
                $wallet_id = (int)$stmt->insert_id;
            }

            if (!$wallet_id) {
                throw new Exception("Invalid wallet_id generated");
            }

            // 2. Update funding request
            $stmt = $db->prepare("
                UPDATE funding_requests 
                SET status = 'success', 
                    wallet_id = ?, 
                    updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->bind_param("ii", $wallet_id, $request_id);
            $stmt->execute();

            // 3. Credit user wallet in users table
            $stmt = $db->prepare("
                UPDATE users 
                SET wallet_balance = wallet_balance + ?, 
                    updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->bind_param("di", $request['amount'], $request['user_id']);
            $stmt->execute();

            // 4. CREDIT WALLETS TABLE (THIS WAS MISSING - FIXED)
            $stmt = $db->prepare("
                UPDATE wallets 
                SET balance = balance + ?, 
                    updated_at = NOW() 
                WHERE user_id = ?
            ");
            $stmt->bind_param("di", $request['amount'], $request['user_id']);
            $stmt->execute();
            
            // 5. Generate unique transaction reference
            $transaction_id = generate_reference('FUND');
            
            // 6. Insert into transactions table (FIXED)
            $stmt = $db->prepare("
                INSERT INTO transactions (
                    user_id, wallet_id, transaction_id, reference, type, 
                    amount, fee, total, status, created_at
                ) VALUES (?, ?, ?, ?, 'wallet_funding', ?, 0.00, ?, 'success', NOW())
            ");
            $stmt->bind_param(
                "iissdd",
                $request['user_id'],
                $wallet_id,
                $transaction_id,
                $request['reference'],
                $request['amount'],
                $request['amount']
            );
            $stmt->execute();

            // 7. Send notification
            $title   = "Wallet Funded Successfully";
            $message = "₦" . number_format($request['amount'], 2) . " approved & credited. Ref: " . $request['reference'];

            $stmt = $db->prepare("
                INSERT INTO notifications (user_id, type, title, message, created_at)
                VALUES (?, 'wallet', ?, ?, NOW())
            ");
            $stmt->bind_param("iss", $request['user_id'], $title, $message);
            $stmt->execute();

            $db->commit();

            Session::setSuccess('Funding approved and wallet credited successfully');

        } catch (Exception $e) {
            $db->rollback();
            error_log("Approval error: " . $e->getMessage());
            Session::setError('Failed to approve funding: ' . $e->getMessage());
        }

    } else {
        Session::setError('Funding request not found or already processed');
    }

    redirect('admin/transactions.php?tab=funding');
}
    if ($action === 'reject_funding') {
        $reason = trim($_POST['reason'] ?? 'No reason given');

        $stmt = $db->prepare("SELECT * FROM funding_requests WHERE id = ? AND status = 'pending'");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $req = $stmt->get_result()->fetch_assoc();

        if ($req) {
            $db->begin_transaction();
            try {
                $ustmt = $db->prepare("UPDATE funding_requests SET status = 'failed' WHERE id = ?");
                $ustmt->bind_param("i", $request_id);
                $ustmt->execute();

                $title = "Funding Rejected";
                $msg   = "₦" . number_format($req['amount'], 2) . " rejected.\nReason: $reason";
                $nstmt = $db->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?, 'error', ?, ?)");
                $nstmt->bind_param("iss", $req['user_id'], $title, $msg);
                $nstmt->execute();

                $db->commit();
                Session::setSuccess("Funding rejected");
            } catch (Exception $e) {
                $db->rollback();
                Session::setError("Rejection failed");
                error_log("Reject error: " . $e->getMessage());
            }
        } else {
            Session::setError("Request not found or already processed");
        }
        redirect('admin/transactions.php?tab=funding');
    }
}

// ==============================
// Fetch data
// ==============================
$tab = $_GET['tab'] ?? 'transactions';

// Transactions
$transactions = [];
try {
    $stmt = $db->prepare("
        SELECT t.*, u.username 
        FROM transactions t 
        JOIN users u ON t.user_id = u.id 
        ORDER BY t.created_at DESC 
        LIMIT 50
    ");
    $stmt->execute();
    $transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Transactions query failed: " . $e->getMessage());
}

// Pending Funding
$pendingFunding = [];
try {
    $pendingFunding = $db->query("
        SELECT fr.*, u.username 
        FROM funding_requests fr 
        JOIN users u ON fr.user_id = u.id 
        WHERE fr.status = 'pending' 
        ORDER BY fr.created_at DESC
    ")->fetch_all(MYSQLI_ASSOC) ?? [];
} catch (Exception $e) {
    error_log("Pending funding query failed");
}

// Funding History
$fundingHistory = [];
try {
    $fundingHistory = $db->query("
        SELECT fr.*, u.username 
        FROM funding_requests fr 
        JOIN users u ON fr.user_id = u.id 
        WHERE fr.status != 'pending' 
        ORDER BY fr.created_at DESC 
        LIMIT 50
    ")->fetch_all(MYSQLI_ASSOC) ?? [];
} catch (Exception $e) {
    error_log("Funding history query failed");
}

$pageTitle = 'Transactions & Funding';
include 'admin_header.php';
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark: #1f2937;
            --gray: #6b7280;
            --light: #f3f4f6;
            --bg: #f9fafb;
            --card: white;
            --radius: 0.75rem;
            --shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }

        [data-theme="dark"] {
            --bg: #111827;
            --card: #1f2937;
            --dark: #f3f4f6;
            --gray: #9ca3af;
            --light: #374151;
        }

        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: var(--bg);
            color: var(--dark);
            margin: 0;
            padding: 0;
        }

        .container-fluid {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1.5rem 1rem;
        }

        .content-header {
            margin-bottom: 2rem;
        }

        .content-header h2 {
            margin: 0;
            font-size: 1.875rem;
            font-weight: 700;
        }

        .tabs {
            display: flex;
            border-bottom: 2px solid var(--light);
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .tab {
            padding: 0.75rem 1.5rem;
            background: transparent;
            border: none;
            font-weight: 500;
            color: var(--gray);
            cursor: pointer;
            transition: all 0.2s;
            border-radius: var(--radius) var(--radius) 0 0;
        }

        .tab:hover {
            color: var(--primary);
            background: var(--light);
        }

        .tab.active {
            color: var(--primary);
            border-bottom: 3px solid var(--primary);
            background: var(--card);
        }

        .card {
            background: var(--card);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }

        .card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--light);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .table-responsive {
            overflow-x: auto;
        }

        table.data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th,
        .data-table td {
            padding: 1rem 1.25rem;
            text-align: left;
        }

        .data-table th {
            background: var(--light);
            font-weight: 600;
            color: var(--gray);
            white-space: nowrap;
        }

        .data-table tr:hover {
            background: rgba(99,102,241,0.05);
        }

        .badge {
            padding: 0.35em 0.75em;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-failed  { background: #fee2e2; color: #991b1b; }

        .funding-card {
            background: var(--card);
            border-radius: var(--radius);
            padding: 1.5rem;
            margin-bottom: 1rem;
            border: 1px solid var(--light);
        }

        .funding-amount {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
        }

        .btn {
            padding: 0.6rem 1.25rem;
            border-radius: var(--radius);
            border: none;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-success { background: var(--success); color: white; }
        .btn-danger   { background: var(--danger);  color: white; }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.6);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .modal.active { display: flex; }

        .modal-content {
            background: var(--card);
            border-radius: var(--radius);
            padding: 2rem;
            max-width: 500px;
            width: 100%;
        }

        textarea {
            width: 100%;
            min-height: 100px;
            padding: 0.75rem;
            border: 1px solid var(--light);
            border-radius: var(--radius);
            resize: vertical;
        }

        @media (max-width: 768px) {
            .tabs { flex-direction: column; }
            .tab { width: 100%; text-align: center; }
        }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="content-header">
        <h2>Transactions & Funding</h2>
    </div>

    <!-- Tabs -->
    <div class="tabs">
        <button class="tab <?php echo ($tab === 'transactions') ? 'active' : ''; ?>" 
                onclick="location.href='?tab=transactions'">Transactions</button>
        <button class="tab <?php echo ($tab === 'funding') ? 'active' : ''; ?>" 
                onclick="location.href='?tab=funding'">Funding Requests</button>
    </div>

    <!-- TRANSACTIONS TAB -->
    <?php if ($tab === 'transactions'): ?>
    <div class="card">
        <div class="card-header">
            <h3>All Transactions</h3>
            <span><?php echo count($transactions); ?> records</span>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                        <tr><td colspan="6" style="text-align:center;padding:3rem;">No transactions found</td></tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $t): ?>
                        <tr>
                            <td>#<?php echo $t['id']; ?></td>
                            <td><?php echo htmlspecialchars($t['username'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst($t['type'] ?? '')); ?></td>
                            <td><?php echo format_money($t['amount'] ?? 0); ?></td>
                            <td>
                                <span class="badge badge-<?php echo htmlspecialchars($t['status'] ?? 'pending'); ?>">
                                    <?php echo ucfirst($t['status'] ?? 'pending'); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y H:i', strtotime($t['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- FUNDING REQUESTS TAB -->
    <?php if ($tab === 'funding'): ?>
    <div class="card">
        <div class="card-header">
            <h3>Pending Manual Funding</h3>
            <span><?php echo count($pendingFunding); ?> awaiting approval</span>
        </div>

        <?php if (empty($pendingFunding)): ?>
            <div style="text-align:center; padding:3rem; color:#6b7280;">
                <i class="fas fa-check-circle" style="font-size:3.5rem; opacity:0.4;"></i><br><br>
                <strong>No pending requests</strong>
            </div>
        <?php else: ?>
            <?php foreach ($pendingFunding as $req): ?>
            <div class="funding-card">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
                    <div>
                        <div class="funding-amount"><?php echo format_money($req['amount']); ?></div>
                        <small>Ref: <?php echo htmlspecialchars($req['reference']); ?></small>
                    </div>
                    <span class="badge badge-pending">Pending</span>
                </div>

                <div style="margin:1.5rem 0; display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:1rem;">
                    <div><strong>User:</strong> <?php echo htmlspecialchars($req['username']); ?></div>
                    <div><strong>Bank:</strong> <?php echo htmlspecialchars($req['bank_name'] ?? '—'); ?></div>
                    <div><strong>Account:</strong> <?php echo htmlspecialchars($req['account_number'] ?? '—'); ?></div>
                    <div><strong>Submitted:</strong> <?php echo date('M d, Y H:i', strtotime($req['created_at'])); ?></div>
                </div>

                <?php if (!empty($req['deposit_slip'])): ?>
                <div style="margin:1rem 0;">
                    <strong>Proof:</strong><br>
                    <img src="<?php echo htmlspecialchars($req['deposit_slip']); ?>" 
                         style="max-width:240px; border-radius:8px; cursor:pointer;" 
                         onclick="window.open(this.src,'_blank')">
                </div>
                <?php endif; ?>

                <div style="display:flex; gap:1rem; margin-top:1.5rem; flex-wrap:wrap;">
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?php echo Session::generateCSRF(); ?>">
                        <input type="hidden" name="action" value="approve_funding">
                        <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check"></i> Approve
                        </button>
                    </form>

                    <button class="btn btn-danger" onclick="showReject(<?php echo $req['id']; ?>)">
                        <i class="fas fa-times"></i> Reject
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Rejected/Processed Funding -->
    <div class="card">
        <div class="card-header">
            <h3>Processed Funding Requests</h3>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>User</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Ref</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fundingHistory as $f): ?>
                    <tr>
                        <td><?php echo date('M d, H:i', strtotime($f['created_at'])); ?></td>
                        <td><?php echo htmlspecialchars($f['username']); ?></td>
                        <td><?php echo format_money($f['amount']); ?></td>
                        <td><span class="badge badge-<?php echo $f['status']; ?>">
                            <?php echo ucfirst($f['status']); ?>
                        </span></td>
                        <td><?php echo htmlspecialchars($f['reference']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Rejection Modal -->
<div id="rejectModal" class="modal">
    <div class="modal-content">
        <h3>Reject Funding Request</h3>
        <p>Reason for rejection:</p>
        <textarea id="rejectReason" rows="4" class="form-control" placeholder="Enter reason..."></textarea>
        <div style="margin-top:1.5rem; display:flex; gap:1rem; justify-content:flex-end;">
            <button class="btn btn-light" onclick="document.getElementById('rejectModal').classList.remove('active')">Cancel</button>
            <button class="btn btn-danger" onclick="submitReject()">Reject</button>
        </div>
    </div>
</div>

<script>
let currentRejectId = null;

function showReject(id) {
    currentRejectId = id;
    document.getElementById('rejectModal').classList.add('active');
    document.getElementById('rejectReason').value = '';
}

function submitReject() {
    const reason = document.getElementById('rejectReason').value.trim();
    if (!reason) {
        alert('Please enter a reason');
        return;
    }

    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="csrf_token" value="<?php echo Session::generateCSRF(); ?>">
        <input type="hidden" name="action" value="reject_funding">
        <input type="hidden" name="request_id" value="${currentRejectId}">
        <input type="hidden" name="reason" value="${reason.replace(/"/g, '&quot;')}">
    `;
    document.body.appendChild(form);
    form.submit();
}
</script>

<?php include 'admin_footer.php'; ?>
</body>
</html>