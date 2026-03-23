<?php
// admin/tickets.php - Support Tickets
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

Auth::requireAdmin();

$db = db();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (!isset($_POST['csrf_token']) || !Session::verifyCSRF($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }
    
    $ticket_id = intval($_POST['ticket_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    
    $stmt = $db->prepare("UPDATE support_tickets SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $ticket_id);
    $stmt->execute();
    
    Session::setSuccess('Ticket status updated');
    redirect('tickets.php');
}

// Handle reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply'])) {
    if (!isset($_POST['csrf_token']) || !Session::verifyCSRF($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }
    
    $ticket_id = intval($_POST['ticket_id'] ?? 0);
    $message = $_POST['message'] ?? '';
    $admin_id = Session::userId();
    
    $stmt = $db->prepare("INSERT INTO ticket_replies (ticket_id, user_id, message, is_staff_reply) VALUES (?, ?, ?, 1)");
    $stmt->bind_param("iis", $ticket_id, $admin_id, $message);
    $stmt->execute();
    
    // Update ticket status
    $db->query("UPDATE support_tickets SET status = 'open', updated_at = NOW() WHERE id = $ticket_id");
    
    Session::setSuccess('Reply sent successfully');
    redirect('tickets.php?view=' . $ticket_id);
}

// Get tickets
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

$where = [];
if ($status_filter) {
    $where[] = "t.status = '$status_filter'";
}
if ($search) {
    $where[] = "(t.subject LIKE '%$search%' OR t.ticket_id LIKE '%$search%' OR u.username LIKE '%$search%' OR u.email LIKE '%$search%')";
}

$where_clause = $where ? "WHERE " . implode(" AND ", $where) : "";

$tickets = $db->query("
    SELECT t.*, u.username, u.email 
    FROM support_tickets t 
    JOIN users u ON t.user_id = u.id 
    $where_clause 
    ORDER BY t.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

// Get single ticket for viewing
$view_ticket = null;
if (isset($_GET['view'])) {
    $id = intval($_GET['view']);
    $view_ticket = $db->query("
        SELECT t.*, u.username, u.email 
        FROM support_tickets t 
        JOIN users u ON t.user_id = u.id 
        WHERE t.id = $id
    ")->fetch_assoc();
    
    if ($view_ticket) {
        $replies = $db->query("
            SELECT r.*, u.username 
            FROM ticket_replies r 
            LEFT JOIN users u ON r.user_id = u.id 
            WHERE r.ticket_id = $id 
            ORDER BY r.created_at ASC
        ")->fetch_all(MYSQLI_ASSOC);
    }
}

$pageTitle = 'Support Tickets';
include 'admin_header.php';
?>

<div class="container-fluid">
    <div class="content-header">
        <h2><i class="fas fa-headset"></i> Support Tickets</h2>
    </div>

    <!-- Alerts -->
    <?php if ($error = Session::getError()): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>
    <?php if ($success = Session::getSuccess()): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <?php if ($view_ticket): ?>
        <!-- View Single Ticket -->
        <div style="margin-bottom: 1rem;">
            <a href="tickets.php" class="btn btn-light">
                <i class="fas fa-arrow-left"></i> Back to Tickets
            </a>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3>Ticket #<?php echo $view_ticket['ticket_id']; ?>: <?php echo htmlspecialchars($view_ticket['subject']); ?></h3>
                <span class="badge badge-<?php echo $view_ticket['status']; ?>">
                    <?php echo ucfirst($view_ticket['status']); ?>
                </span>
            </div>
            
            <div style="margin-bottom: 1rem;">
                <p><strong>From:</strong> <?php echo htmlspecialchars($view_ticket['username']); ?> (<?php echo $view_ticket['email']; ?>)</p>
                <p><strong>Category:</strong> <?php echo ucfirst($view_ticket['category']); ?></p>
                <p><strong>Priority:</strong> 
                    <span class="badge badge-<?php 
                        echo $view_ticket['priority'] == 'urgent' ? 'danger' : 
                            ($view_ticket['priority'] == 'high' ? 'warning' : 'info'); 
                    ?>">
                        <?php echo ucfirst($view_ticket['priority']); ?>
                    </span>
                </p>
                <p><strong>Created:</strong> <?php echo date('M d, Y h:i A', strtotime($view_ticket['created_at'])); ?></p>
            </div>
            
            <div style="background: var(--light); padding: 1rem; border-radius: var(--radius); margin-bottom: 2rem;">
                <p><?php echo nl2br(htmlspecialchars($view_ticket['message'])); ?></p>
            </div>
            
            <!-- Replies -->
            <?php if (!empty($replies)): ?>
                <h4 style="margin-bottom: 1rem;">Conversation</h4>
                <?php foreach ($replies as $reply): ?>
                    <div style="background: <?php echo $reply['is_staff_reply'] ? '#dbeafe' : '#f3f4f6'; ?>; padding: 1rem; border-radius: var(--radius); margin-bottom: 1rem;">
                        <p style="margin-bottom: 0.5rem;"><strong><?php echo $reply['is_staff_reply'] ? 'Support Team' : htmlspecialchars($reply['username']); ?></strong> 
                            <small style="color: var(--gray);">• <?php echo time_ago($reply['created_at']); ?></small>
                        </p>
                        <p><?php echo nl2br(htmlspecialchars($reply['message'])); ?></p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <!-- Reply Form -->
            <form method="POST" style="margin-top: 2rem;">
                <input type="hidden" name="csrf_token" value="<?php echo Session::generateCSRF(); ?>">
                <input type="hidden" name="ticket_id" value="<?php echo $view_ticket['id']; ?>">
                
                <div class="form-group">
                    <label>Reply Message</label>
                    <textarea name="message" class="form-control" rows="5" required placeholder="Type your reply..."></textarea>
                </div>
                
                <div style="display: flex; gap: 1rem;">
                    <button type="submit" name="reply" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Send Reply
                    </button>
                    
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?php echo Session::generateCSRF(); ?>">
                        <input type="hidden" name="ticket_id" value="<?php echo $view_ticket['id']; ?>">
                        <input type="hidden" name="status" value="closed">
                        <button type="submit" name="update_status" class="btn btn-light">
                            <i class="fas fa-check"></i> Close Ticket
                        </button>
                    </form>
                </div>
            </form>
        </div>
        
    <?php else: ?>
        <!-- Tickets List -->
        <div class="card">
            <div class="card-header">
                <h3>All Tickets</h3>
                <div style="display: flex; gap: 0.5rem;">
                    <a href="?status=open" class="btn btn-light btn-small">Open</a>
                    <a href="?status=closed" class="btn btn-light btn-small">Closed</a>
                    <a href="tickets.php" class="btn btn-primary btn-small">All</a>
                </div>
            </div>
            
            <!-- Search -->
            <form method="GET" style="margin-bottom: 1rem;">
                <div style="display: flex; gap: 0.5rem;">
                    <input type="text" name="search" class="form-control" placeholder="Search tickets..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                </div>
            </form>
            
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Ticket ID</th>
                            <th>User</th>
                            <th>Subject</th>
                            <th>Category</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tickets as $t): ?>
                            <tr>
                                <td><code><?php echo $t['ticket_id']; ?></code></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($t['username']); ?></strong>
                                    <br>
                                    <small><?php echo $t['email']; ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($t['subject']); ?></td>
                                <td><?php echo ucfirst($t['category']); ?></td>
                                <td>
                                    <span class="badge badge-<?php 
                                        echo $t['priority'] == 'urgent' ? 'danger' : 
                                            ($t['priority'] == 'high' ? 'warning' : 'info'); 
                                    ?>">
                                        <?php echo ucfirst($t['priority']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $t['status']; ?>">
                                        <?php echo ucfirst($t['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo time_ago($t['created_at']); ?></td>
                                <td>
                                    <a href="?view=<?php echo $t['id']; ?>" class="btn btn-light btn-small">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'admin_footer.php'; ?>