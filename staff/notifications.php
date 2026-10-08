<?php
require_once '../config/config.php';
requireStaff();

$page_title = 'Notifications';

$database = new Database();
$db = $database->getConnection();

// Mark all notifications as read
if (isset($_POST['mark_all_read'])) {
    $query = "UPDATE notifications SET is_read = 1 WHERE user_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $success = 'All notifications marked as read.';
}

// Get all notifications for the current user
$query = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unread count
$unread_count = count(array_filter($notifications, function($n) { return !$n['is_read']; }));

include '../includes/header.php';
?>

<?php if (isset($success)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        Notifications 
                        <?php if ($unread_count > 0): ?>
                            <span class="badge bg-danger"><?php echo $unread_count; ?> unread</span>
                        <?php endif; ?>
                    </h5>
                    <?php if ($unread_count > 0): ?>
                        <form method="POST" class="d-inline">
                            <button type="submit" name="mark_all_read" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-check-double"></i> Mark All as Read
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($notifications)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-bell-slash fa-3x mb-3"></i>
                        <p>No notifications yet</p>
                    </div>
                <?php else: ?>
                    <div class="notification-list">
                        <?php foreach ($notifications as $notification): ?>
                            <div class="notification-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?> p-3 mb-3 border rounded">
                                <div class="d-flex align-items-start">
                                    <div class="notification-icon me-3">
                                        <?php
                                        $icon_class = 'fas fa-info-circle text-primary';
                                        switch ($notification['type']) {
                                            case 'upload':
                                                $icon_class = 'fas fa-upload text-info';
                                                break;
                                            case 'approval':
                                                $icon_class = 'fas fa-check-circle text-success';
                                                break;
                                            case 'rejection':
                                                $icon_class = 'fas fa-times-circle text-danger';
                                                break;
                                            case 'assignment':
                                                $icon_class = 'fas fa-user-plus text-warning';
                                                break;
                                            case 'announcement':
                                                $icon_class = 'fas fa-bullhorn text-primary';
                                                break;
                                        }
                                        ?>
                                        <i class="<?php echo $icon_class; ?> fa-lg"></i>
                                    </div>
                                    <div class="notification-content flex-grow-1">
                                        <h6 class="notification-title mb-2">
                                            <?php echo htmlspecialchars($notification['title']); ?>
                                            <?php if (!$notification['is_read']): ?>
                                                <span class="badge bg-primary ms-2">New</span>
                                            <?php endif; ?>
                                        </h6>
                                        <p class="notification-message mb-2">
                                            <?php echo nl2br(htmlspecialchars($notification['message'])); ?>
                                        </p>
                                        <small class="text-muted">
                                            <i class="fas fa-clock"></i>
                                            <?php echo date('F j, Y g:i A', strtotime($notification['created_at'])); ?>
                                        </small>
                                    </div>
                                    <div class="notification-actions">
                                        <span class="badge badge-<?php echo $notification['type'] == 'approval' ? 'success' : ($notification['type'] == 'rejection' ? 'danger' : 'primary'); ?>">
                                            <?php echo ucfirst($notification['type']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
