<?php
// admin/logs.php - Activity Logs
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

Auth::requireAdmin();

$db = db();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// Get logs count
$total = $db->query("SELECT COUNT(*) as count FROM activity_logs")->fetch_assoc()['count'];
$pages = ceil($total / $limit);

// Get logs with user info
$logs = $db->query("
    SELECT l.*, u.username 
    FROM activity_logs l 
    LEFT JOIN users u ON l.user_id = u.id 
    ORDER BY l.id DESC 
    LIMIT $offset, $limit
")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Activity Logs';
include 'admin_header.php';
?>

<div class="container-fluid">
    <div class="content-header">
        <h2><i class="fas fa-history"></i> Activity Logs</h2>
        <p>Total records: <?php echo $total; ?></p>
    </div>

    <!-- Logs Table -->
    <div class="card">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Details</th>
                        <th>IP Address</th>
                        <th>Date/Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td>#<?php echo $log['id']; ?></td>
                            <td>
                                <?php if ($log['user_id']): ?>
                                    <a href="users.php?search=<?php echo $log['username']; ?>">
                                        <?php echo htmlspecialchars($log['username']); ?>
                                    </a>
                                <?php else: ?>
                                    <em>Guest</em>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge badge-info"><?php echo htmlspecialchars($log['action']); ?></span></td>
                            <td><?php echo htmlspecialchars($log['details'] ?? '-'); ?></td>
                            <td><code><?php echo $log['ip_address']; ?></code></td>
                            <td><?php echo date('M d, Y H:i:s', strtotime($log['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($pages > 1): ?>
            <div style="display: flex; justify-content: center; gap: 0.5rem; margin-top: 1rem;">
                <?php for ($i = 1; $i <= $pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>" 
                       class="btn btn-light btn-small <?php echo $i == $page ? 'active' : ''; ?>"
                       style="<?php echo $i == $page ? 'background: var(--primary); color: white;' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'admin_footer.php'; ?>