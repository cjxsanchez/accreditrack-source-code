<?php
require_once '../config/config.php';
require_once '../includes/mailer.php'; // Include Mailer Helper
requireAdmin();

$page_title = 'User Management';
$db = getDB();

$success = '';
$error = '';

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'approve_user':
                $user_id = $_POST['user_id'];
                
                // Fetch user email details BEFORE update
                $userQuery = "SELECT email, full_name FROM users WHERE id = ?";
                $uStmt = $db->prepare($userQuery);
                $uStmt->execute([$user_id]);
                $userData = $uStmt->fetch(PDO::FETCH_ASSOC);

                $query = "UPDATE users SET status = 'active' WHERE id = ?";
                $stmt = $db->prepare($query);
                if ($stmt->execute([$user_id])) {
                    $query = "INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$user_id, 'Account Approved', 'Your account has been approved. You can now login.', 'success']);
                    
                    // --- SEND EMAIL NOTIFICATION ---
                    if ($userData && !empty($userData['email'])) {
                        $subject = "AccrediTrack | Account Approved";
                        $body = "
                        <div style='font-family: Arial, sans-serif; color: #333;'>
                            <h2 style='color: #667eea;'>Account Approved</h2>
                            <p>Dear {$userData['full_name']},</p>
                            <p>We are pleased to inform you that your faculty account registration for <strong>AccrediTrack</strong> has been approved.</p>
                            <p>You may now log in to the system to view your assignments and begin uploading documents.</p>
                            <p><a href='" . SITE_URL . "index.php' style='background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Login Now</a></p>
                            <hr>
                            <small>This is an automated message from the AccrediTrack System.</small>
                        </div>";
                        
                        sendSystemEmail($userData['email'], $userData['full_name'], $subject, $body);
                    }
                    // --------------------------------

                    $success = 'User approved successfully!';
                }
                break;
                
            case 'reject_user':
                $user_id = $_POST['user_id'];
                $query = "DELETE FROM users WHERE id = ?";
                $stmt = $db->prepare($query);
                if ($stmt->execute([$user_id])) {
                    $success = 'User registration rejected!';
                }
                break;
                
            case 'add_user':
                $full_name = sanitize($_POST['full_name']);
                $email = sanitize($_POST['email']);
                $username = sanitize($_POST['username']);
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                
                $query = "SELECT COUNT(*) FROM users WHERE username = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$username]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('Username already exists!');
                }
                
                $query = "INSERT INTO users (username, email, password, full_name, role, status) VALUES (?, ?, ?, ?, 'staff', 'active')";
                $stmt = $db->prepare($query);
                if ($stmt->execute([$username, $email, $password, $full_name])) {
                    $new_user_id = $db->lastInsertId();
                    $query = "INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$new_user_id, 'Welcome', 'Your account has been created.', 'info']);
                    $success = 'User added successfully!';
                }
                break;
                
            case 'assign_faculty':
                $user_id = $_POST['user_id'];
                $area_id = $_POST['area_id'];
                
                // Validation logic
                $query = "SELECT assigned_faculty_id FROM areas WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$area_id]);
                $current = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($current && $current['assigned_faculty_id']) {
                    throw new Exception('Area already has assigned faculty!');
                }
                
                // Prevent duplicate assignment in same level
                $query = "SELECT COUNT(*) FROM areas a1 JOIN areas a2 ON a1.level_id = a2.level_id WHERE a1.id = ? AND a2.assigned_faculty_id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$area_id, $user_id]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('Faculty is already assigned to another area in this level!');
                }
                
                $query = "UPDATE areas SET assigned_faculty_id = ? WHERE id = ?";
                $stmt = $db->prepare($query);
                if ($stmt->execute([$user_id, $area_id])) {
                    // Notification logic
                    $query = "SELECT a.area_number, a.name, pr.name as p_name, l.level_type FROM areas a JOIN levels l ON a.level_id = l.id JOIN programs pr ON l.program_id = pr.id WHERE a.id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$area_id]);
                    $info = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $msg = "Assigned to {$info['area_number']}: {$info['name']} in {$info['p_name']} - {$info['level_type']}";
                    $nQuery = "INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)";
                    $nStmt = $db->prepare($nQuery);
                    $nStmt->execute([$user_id, 'New Assignment', $msg, 'info']);
                    
                    // --- SEND EMAIL NOTIFICATION ---
                    $userQuery = "SELECT email, full_name FROM users WHERE id = ?";
                    $uStmt = $db->prepare($userQuery);
                    $uStmt->execute([$user_id]);
                    $userData = $uStmt->fetch(PDO::FETCH_ASSOC);

                    if ($userData && !empty($userData['email'])) {
                        $subject = "AccrediTrack | New Area Assignment";
                        $body = "
                        <div style='font-family: Arial, sans-serif; color: #333;'>
                            <h2 style='color: #667eea;'>New Area Assignment</h2>
                            <p>Dear {$userData['full_name']},</p>
                            <p>You have been assigned to a new area for accreditation.</p>
                            <div style='background: #f8f9fa; padding: 15px; border-left: 4px solid #667eea; margin: 15px 0;'>
                                <p><strong>Program:</strong> {$info['p_name']}</p>
                                <p><strong>Level:</strong> {$info['level_type']}</p>
                                <p><strong>Area:</strong> Area {$info['area_number']} - {$info['name']}</p>
                            </div>
                            <p>Please log in to review the requirements and start your preparations.</p>
                            <p><a href='" . SITE_URL . "index.php' style='background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>View Assignment</a></p>
                            <hr>
                            <small>This is an automated message from the AccrediTrack System.</small>
                        </div>";
                        
                        sendSystemEmail($userData['email'], $userData['full_name'], $subject, $body);
                    }
                    // --------------------------------

                    $success = 'Faculty assigned successfully!';
                }
                break;
            
            // --- NEW FEATURE: UNASSIGN FACULTY ---
            case 'unassign_faculty':
                $area_id = $_POST['area_id'];
                
                // Check if area exists first
                $checkQuery = "SELECT area_number FROM areas WHERE id = ?";
                $stmt = $db->prepare($checkQuery);
                $stmt->execute([$area_id]);
                $areaInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($areaInfo) {
                    // Update area to remove faculty. 
                    // Note: This effectively resets the "progress" for the user regarding this area
                    // because the link is severed. Any logic regarding deleting uploads 
                    // should be handled here if your DB schema requires cascading deletes.
                    $query = "UPDATE areas SET assigned_faculty_id = NULL WHERE id = ?";
                    $stmt = $db->prepare($query);
                    if ($stmt->execute([$area_id])) {
                        $success = "Faculty unassigned from Area {$areaInfo['area_number']}. Progress has been reset.";
                    }
                }
                break;
            // -------------------------------------
                
            case 'toggle_status':
                $user_id = $_POST['user_id'];
                $new_status = $_POST['new_status'];
                
                if ($new_status === 'inactive') {
                    $uStmt = $db->prepare("UPDATE areas SET assigned_faculty_id = NULL WHERE assigned_faculty_id = ?");
                    $uStmt->execute([$user_id]);
                }
                
                $uStmt = $db->prepare("UPDATE users SET status = ? WHERE id = ?");
                if ($uStmt->execute([$new_status, $user_id])) {
                    $success = 'User status updated!';
                }
                break;
                
            case 'delete_user':
                $user_id = $_POST['user_id'];
                $dStmt = $db->prepare("UPDATE areas SET assigned_faculty_id = NULL WHERE assigned_faculty_id = ?");
                $dStmt->execute([$user_id]);
                
                $dStmt = $db->prepare("DELETE FROM users WHERE id = ?");
                if ($dStmt->execute([$user_id])) {
                    $success = 'User deleted successfully!';
                }
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// 1. Get Status Counts
$countQuery = "SELECT status, COUNT(*) as count FROM users WHERE role = 'staff' GROUP BY status";
$stmt = $db->prepare($countQuery);
$stmt->execute();
$statusCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$totalUsers = array_sum($statusCounts);
$activeCount = $statusCounts['active'] ?? 0;
$inactiveCount = $statusCounts['inactive'] ?? 0;
$pendingCount = $statusCounts['pending'] ?? 0;

// 2. Get Programs for Filter - EXCLUDE ARCHIVED PROGRAMS
$programQuery = "SELECT id, name, code FROM programs WHERE status != 'archived' ORDER BY name";
$stmt = $db->prepare($programQuery);
$stmt->execute();
$programs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Filters
$status_filter = $_GET['status'] ?? 'active';
$assignment_filter = $_GET['assignment'] ?? 'all';
$program_filter = $_GET['program'] ?? 'all';

// 4. Build Main Query
$query = "SELECT DISTINCT u.*, 
          (SELECT COUNT(*) FROM areas a WHERE a.assigned_faculty_id = u.id) as assigned_count
          FROM users u 
          WHERE u.role = 'staff'";

if ($status_filter !== 'all') {
    $query .= " AND u.status = :status";
}

// Add program filter if selected
if ($program_filter !== 'all') {
    // Verify the selected program is not archived
    $checkProgram = "SELECT COUNT(*) FROM programs WHERE id = ? AND status != 'archived'";
    $stmt = $db->prepare($checkProgram);
    $stmt->execute([$program_filter]);
    
    if ($stmt->fetchColumn() == 0) {
        $program_filter = 'all';
    } else {
        $query .= " AND EXISTS (
            SELECT 1 FROM areas a 
            JOIN levels l ON a.level_id = l.id 
            WHERE a.assigned_faculty_id = u.id 
            AND l.program_id = :program_id
        )";
    }
}

$query .= " ORDER BY u.full_name ASC";

$stmt = $db->prepare($query);
if ($status_filter !== 'all') {
    $stmt->bindValue(':status', $status_filter);
}
if ($program_filter !== 'all') {
    $stmt->bindValue(':program_id', $program_filter);
}
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 5. Apply assignment filter
if ($assignment_filter !== 'all') {
    $users = array_filter($users, function($user) use ($assignment_filter) {
        if ($assignment_filter === 'assigned') {
            return $user['assigned_count'] > 0;
        } else {
            return $user['assigned_count'] == 0;
        }
    });
}

// 6. Batch Fetch Assignments - UPDATED TO INCLUDE area ID for unassigning
$userAssignments = [];
$programAssignments = []; 
if (!empty($users)) {
    $userIds = array_column($users, 'id');
    if (!empty($userIds)) {
        $placeholders = str_repeat('?,', count($userIds) - 1) . '?';
        
        // ADDED a.id as area_id to the SELECT
        $assignQuery = "SELECT a.id as area_id, a.assigned_faculty_id, a.area_number, pr.id as program_id, pr.name as program_name, pr.code as program_code, l.level_type 
                        FROM areas a 
                        JOIN levels l ON a.level_id = l.id 
                        JOIN programs pr ON l.program_id = pr.id 
                        WHERE a.assigned_faculty_id IN ($placeholders)
                        AND pr.status != 'archived'
                        ORDER BY pr.name, a.area_number";
        $stmt = $db->prepare($assignQuery);
        $stmt->execute($userIds);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $userAssignments[$row['assigned_faculty_id']][] = $row;
            if (!isset($programAssignments[$row['assigned_faculty_id']])) {
                $programAssignments[$row['assigned_faculty_id']] = [];
            }
            if (!in_array($row['program_id'], $programAssignments[$row['assigned_faculty_id']])) {
                $programAssignments[$row['assigned_faculty_id']][] = $row['program_id'];
            }
        }
    }
}

include '../includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
/* Layout Styles */
:root {
    --primary-color: #667eea;
    --secondary-color: #764ba2;
}

body { background: #f8f9fa; }

.page-header-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

/* Tabs styling */
.nav-tabs-custom {
    display: flex;
    gap: 10px;
    border-bottom: 2px solid #e9ecef;
    margin-bottom: 20px;
    padding-bottom: 0;
}
.nav-link-custom {
    background: none;
    border: none;
    padding: 10px 20px;
    color: #6c757d;
    font-weight: 600;
    position: relative;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 8px;
}
.nav-link-custom:hover { color: var(--primary-color); }
.nav-link-custom.active {
    color: var(--primary-color);
}
.nav-link-custom.active::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    width: 100%;
    height: 3px;
    background: var(--primary-color);
    border-radius: 3px 3px 0 0;
}

.count-badge {
    background: #e9ecef;
    color: #495057;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
}
.active .count-badge {
    background: var(--primary-color);
    color: white;
}
.count-badge.pending-alert {
    background: #ffc107;
    color: #000;
}

/* Table Styling */
.table-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.04);
    overflow: hidden;
}
.table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    margin-bottom: 0;
}
.table th {
    background: #f8f9fa;
    padding: 16px 20px;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #6c757d;
    border-bottom: 1px solid #e9ecef;
    font-weight: 600;
}
.table td {
    padding: 16px 20px;
    vertical-align: middle;
    border-bottom: 1px solid #f1f3f5;
    transition: background 0.2s;
}
.table tr:hover td { background: #fafbff; }

/* User Identity Column */
.user-identity { display: flex; align-items: center; gap: 15px; }
.user-avatar {
    width: 40px; height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #e0e7ff 0%, #f3e8ff 100%);
    color: #4f46e5;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1rem;
}
.user-details h6 { margin: 0; font-size: 0.95rem; font-weight: 600; color: #343a40; }
.user-details span { font-size: 0.8rem; color: #868e96; }

/* Status Badges */
.status-dot {
    display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 6px;
}
.status-badge {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
}
.status-active { background: #d3f9d8; color: #2b8a3e; }
.status-active .status-dot { background: #2b8a3e; }
.status-inactive { background: #ffe3e3; color: #c92a2a; }
.status-inactive .status-dot { background: #c92a2a; }
.status-pending { background: #fff3bf; color: #e67700; }
.status-pending .status-dot { background: #e67700; }

/* Assignment Chips */
.assignment-cell { max-width: 300px; }
.area-chip {
    display: inline-block;
    background: #f1f3f5;
    border: 1px solid #dee2e6;
    color: #495057;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 500;
    margin: 2px;
    white-space: nowrap;
    position: relative;
    padding-right: 22px; /* Extra padding for the X */
}
.area-chip .remove-assign {
    position: absolute;
    right: 6px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 10px;
    color: #adb5bd;
    cursor: pointer;
    transition: color 0.2s;
    width: 12px;
    height: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}
.area-chip .remove-assign:hover {
    color: #fa5252;
    background: #fff5f5;
}

.area-chip.more {
    background: white;
    border: 1px dashed #adb5bd;
    color: #868e96;
    cursor: help;
    padding-right: 10px; /* Reset padding for 'more' chip */
}

/* Actions */
.btn-assign-row {
    padding: 6px 12px;
    background: var(--primary-color);
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 500;
    cursor: pointer;
    transition: transform 0.1s;
}
.btn-assign-row:hover { background: #5a67d8; transform: translateY(-1px); }

/* Filter Bar */
.filter-bar { 
    display: flex; 
    gap: 12px; 
    margin-bottom: 20px; 
    flex-wrap: wrap;
}
.filter-search { 
    flex: 1;
    min-width: 250px;
    position: relative; 
}
.filter-search input {
    width: 100%; 
    padding: 10px 15px 10px 35px;
    border: 1px solid #dee2e6; 
    border-radius: 8px;
    font-size: 0.9rem;
    transition: border-color 0.2s;
}
.filter-search input:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.1);
}
.filter-search i { 
    position: absolute; 
    left: 12px; 
    top: 50%; 
    transform: translateY(-50%); 
    color: #adb5bd; 
}
.filter-select { 
    padding: 10px 15px; 
    border-radius: 8px; 
    border: 1px solid #dee2e6; 
    color: #495057; 
    background: white;
    font-size: 0.9rem;
    cursor: pointer;
    min-width: 160px;
}
.filter-select:focus {
    outline: none;
    border-color: var(--primary-color);
}

/* Kebab Menu */
.kebab-menu { position: relative; display: inline-block; margin-left: 8px; }
.kebab-btn {
    background: none; border: none; color: #adb5bd; font-size: 1.2rem;
    cursor: pointer; padding: 0 8px; line-height: 1;
}
.kebab-btn:hover { color: #495057; }
.dropdown-menu {
    display: none;
    position: absolute;
    right: 0;
    top: 100%;
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    min-width: 160px;
    z-index: 100;
    padding: 5px 0;
    border: 1px solid #f1f3f5;
}
.dropdown-menu.show { display: block; }
.dropdown-item {
    display: block; width: 100%; text-align: left;
    padding: 8px 15px;
    background: none; border: none;
    color: #495057; font-size: 0.85rem;
    cursor: pointer;
    transition: background 0.1s;
}
.dropdown-item:hover { background: #f8f9fa; color: var(--primary-color); }
.dropdown-item.danger { color: #fa5252; }
.dropdown-item.danger:hover { background: #fff5f5; }

/* Custom Modal Styles (Independent of Bootstrap JS) */
.modal {
    display: none; /* Hidden by default */
    position: fixed;
    z-index: 1050;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.5);
    justify-content: center;
    align-items: center;
}
.modal.show {
    display: flex !important;
}
.modal-dialog {
    background-color: #fff;
    border-radius: 12px;
    width: 100%;
    max-width: 500px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    margin: 20px;
}
.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid #e9ecef;
}
.modal-body { padding: 20px; }
.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 15px 20px;
    border-top: 1px solid #e9ecef;
    background: #f8f9fa;
    border-radius: 0 0 12px 12px;
}
.btn-close {
    background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6c757d;
}

/* Loading Overlay */
.loading-overlay {
    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(255,255,255,0.8);
    display: flex; justify-content: center; align-items: center;
    z-index: 2000;
    visibility: hidden; opacity: 0; transition: opacity 0.2s;
}
.loading-overlay.active { visibility: visible; opacity: 1; }
.spinner { width: 40px; height: 40px; border: 4px solid #f3f3f3; border-top: 4px solid var(--primary-color); border-radius: 50%; animation: spin 1s linear infinite; }
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

/* Empty State */
.empty-state {
    padding: 40px 20px;
    text-align: center;
    color: #6c757d;
}
.empty-state i {
    font-size: 48px;
    margin-bottom: 16px;
    opacity: 0.5;
}

/* Program chips in user row */
.program-chip {
    display: inline-block;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 500;
    margin-right: 4px;
}

/* No results message */
.no-results-row {
    display: none;
}
.no-results-row td {
    text-align: center;
    padding: 40px !important;
    color: #6c757d;
}
.no-results-row td i {
    font-size: 32px;
    margin-bottom: 10px;
    opacity: 0.5;
}
</style>

<div class="loading-overlay" id="globalLoader">
    <div class="spinner"></div>
</div>

<div class="container-fluid pt-4">

    <div class="page-header-actions">
        <div>
            <h5 class="mb-1 fw-bold text-dark">Manage faculty accounts and assignments</h5>
        </div>
        <button class="btn btn-primary shadow-sm" onclick="openModal('addUserModal')">
            <i class="fas fa-plus me-2"></i> Add Faculty
        </button>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" onclick="this.parentElement.remove()">×</button>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" onclick="this.parentElement.remove()">×</button>
        </div>
    <?php endif; ?>

    <div class="nav-tabs-custom">
        <a href="?status=all&assignment=<?php echo $assignment_filter; ?>&program=<?php echo $program_filter; ?>" class="nav-link-custom <?php echo $status_filter == 'all' ? 'active' : ''; ?>">
            All Users <span class="count-badge"><?php echo $totalUsers; ?></span>
        </a>
        <a href="?status=active&assignment=<?php echo $assignment_filter; ?>&program=<?php echo $program_filter; ?>" class="nav-link-custom <?php echo $status_filter == 'active' ? 'active' : ''; ?>">
            Active <span class="count-badge"><?php echo $activeCount; ?></span>
        </a>
        <a href="?status=pending&assignment=<?php echo $assignment_filter; ?>&program=<?php echo $program_filter; ?>" class="nav-link-custom <?php echo $status_filter == 'pending' ? 'active' : ''; ?>">
            Pending <span class="count-badge <?php echo $pendingCount > 0 ? 'pending-alert' : ''; ?>"><?php echo $pendingCount; ?></span>
        </a>
        <a href="?status=inactive&assignment=<?php echo $assignment_filter; ?>&program=<?php echo $program_filter; ?>" class="nav-link-custom <?php echo $status_filter == 'inactive' ? 'active' : ''; ?>">
            Inactive <span class="count-badge"><?php echo $inactiveCount; ?></span>
        </a>
    </div>

    <div class="filter-bar">
        <div class="filter-search">
            <i class="fas fa-search"></i>
            <input type="text" id="userSearch" placeholder="Search by name, email or username...">
        </div>
        <select class="filter-select" id="programFilter" onchange="updateProgramFilter(this.value)">
            <option value="all" <?php echo $program_filter == 'all' ? 'selected' : ''; ?>>All Programs</option>
            <?php foreach ($programs as $program): ?>
                <option value="<?php echo $program['id']; ?>" <?php echo $program_filter == $program['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($program['name']); ?> (<?php echo htmlspecialchars($program['code']); ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <select class="filter-select" id="assignmentFilter" onchange="updateAssignmentFilter(this.value)">
            <option value="all" <?php echo $assignment_filter == 'all' ? 'selected' : ''; ?>>All Assignments</option>
            <option value="assigned" <?php echo $assignment_filter == 'assigned' ? 'selected' : ''; ?>>Assigned Only</option>
            <option value="unassigned" <?php echo $assignment_filter == 'unassigned' ? 'selected' : ''; ?>>Unassigned Only</option>
        </select>
    </div>

    <div class="table-card">
        <?php if (empty($users)): ?>
            <div class="empty-state py-5 text-center">
                <h5 class="text-muted">No users found</h5>
                <p class="text-muted small">Try adjusting your filters or search terms.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table" id="userTable">
                    <thead>
                        <tr>
                            <th width="30%">User</th>
                            <th width="15%">Status</th>
                            <th width="30%">Assignments</th>
                            <th width="25%" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="userTableBody">
                        <tr id="noResultsRow" class="no-results-row">
                            <td colspan="4">
                                <i class="fas fa-search"></i>
                                <h5 class="mt-2">No users found</h5>
                                <p class="text-muted small">Try adjusting your search terms</p>
                            </td>
                        </tr>
                        <?php foreach ($users as $user): ?>
                        <tr class="user-row" 
                            data-name="<?php echo htmlspecialchars(strtolower($user['full_name'])); ?>"
                            data-email="<?php echo htmlspecialchars(strtolower($user['email'])); ?>"
                            data-username="<?php echo htmlspecialchars(strtolower($user['username'])); ?>"
                            data-status="<?php echo htmlspecialchars($user['status']); ?>"
                            data-assigned="<?php echo ($user['assigned_count'] > 0) ? 'assigned' : 'unassigned'; ?>"
                            data-programs="<?php echo isset($programAssignments[$user['id']]) ? implode(',', $programAssignments[$user['id']]) : ''; ?>">
                            <td>
                                <div class="user-identity">
                                    <div class="user-avatar">
                                        <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                                    </div>
                                    <div class="user-details">
                                        <h6 class="search-name"><?php echo htmlspecialchars($user['full_name']); ?></h6>
                                        <span class="search-meta">
                                            <?php echo htmlspecialchars($user['email']); ?> <br> • 
                                            <?php echo htmlspecialchars($user['username']); ?>
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php 
                                $statusClass = $user['status'] == 'active' ? 'active' : ($user['status'] == 'pending' ? 'pending' : 'inactive');
                                ?>
                                <span class="status-badge status-<?php echo $statusClass; ?>">
                                    <span class="status-dot"></span> <?php echo ucfirst($user['status']); ?>
                                </span>
                            </td>
                            <td class="assignment-cell">
                                <?php 
                                $myAssignments = $userAssignments[$user['id']] ?? [];
                                if (empty($myAssignments)) {
                                    echo '<span class="text-muted small"><i class="fas fa-minus-circle me-1"></i> No areas assigned</span>';
                                } else {
                                    // Group by program first
                                    $assignmentsByProgram = [];
                                    foreach ($myAssignments as $assignment) {
                                        $programId = $assignment['program_id'];
                                        if (!isset($assignmentsByProgram[$programId])) {
                                            $assignmentsByProgram[$programId] = [
                                                'program_name' => $assignment['program_name'],
                                                'program_code' => $assignment['program_code'],
                                                'areas' => []
                                            ];
                                        }
                                        $assignmentsByProgram[$programId]['areas'][] = $assignment;
                                    }
                                    
                                    $displayCount = 0;
                                    foreach ($assignmentsByProgram as $program) {
                                        echo '<div class="mb-1">';
                                        echo '<span class="program-chip" title="' . htmlspecialchars($program['program_name']) . '">' . htmlspecialchars($program['program_code']) . '</span>';
                                        $areaCount = count($program['areas']);
                                        for($i = 0; $i < min($areaCount, 2); $i++) {
                                            $a = $program['areas'][$i];
                                            echo '<span class="area-chip" title="' . htmlspecialchars($a['level_type']) . '">';
                                            echo $a['area_number'];
                                            // ADDED: Close button for unassigning
                                            echo '<i class="fas fa-times remove-assign" onclick="unassignFaculty(' . $a['area_id'] . ', \'Area ' . $a['area_number'] . '\')"></i>';
                                            echo '</span>';
                                            $displayCount++;
                                        }
                                        if ($areaCount > 2) {
                                            $moreCount = $areaCount - 2;
                                            echo '<span class="area-chip more" title="' . $moreCount . ' more area(s)">+' . $moreCount . '</span>';
                                        }
                                        echo '</div>';
                                    }
                                }
                                ?>
                            </td>
                            <td class="text-end">
                                <?php if ($user['status'] == 'active'): ?>
                                    <button class="btn-assign-row me-1" onclick="assignFaculty(<?php echo $user['id']; ?>, '<?php echo addslashes($user['full_name']); ?>')">
                                        <i class="fas fa-plus-circle me-1"></i> Assign
                                    </button>
                                <?php elseif ($user['status'] == 'pending'): ?>
                                    <form method="POST" class="d-inline" onsubmit="showLoader()">
                                        <input type="hidden" name="action" value="approve_user">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button class="btn btn-success btn-sm"><i class="fas fa-check"></i> Approve</button>
                                    </form>
                                <?php endif; ?>

                                <div class="kebab-menu">
                                    <button class="kebab-btn" onclick="toggleDropdown(this)">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <?php if ($user['status'] == 'active'): ?>
                                            <form method="POST" onsubmit="return confirmAction(event, 'Deactivate this user? All assignments will be removed.', 'warning')">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <input type="hidden" name="new_status" value="inactive">
                                                <button type="submit" class="dropdown-item"><i class="fas fa-ban me-2"></i> Deactivate</button>
                                            </form>
                                        <?php elseif ($user['status'] == 'inactive'): ?>
                                            <form method="POST" onsubmit="return confirmAction(event, 'Activate this user?', 'info')">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <input type="hidden" name="new_status" value="active">
                                                <button type="submit" class="dropdown-item"><i class="fas fa-check-circle me-2"></i> Activate</button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <?php if ($user['status'] == 'pending'): ?>
                                            <form method="POST" onsubmit="return confirmAction(event, 'Reject this user registration?', 'error')">
                                                <input type="hidden" name="action" value="reject_user">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" class="dropdown-item danger"><i class="fas fa-times me-2"></i> Reject</button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <div style="border-top: 1px solid #eee; margin: 4px 0;"></div>
                                        <form method="POST" onsubmit="return confirmAction(event, 'Permanently delete this user? This cannot be undone.', 'error')">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="dropdown-item danger"><i class="fas fa-trash-alt me-2"></i> Delete User</button>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal" id="addUserModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white" style="border-radius: 12px 12px 0 0;">
                <h5 class="modal-title text-white"><i class="fas fa-user-plus me-2"></i>Add New Faculty</h5>
                <button type="button" class="btn-close" style="color:white; opacity:1;" onclick="closeModal('addUserModal')">×</button>
            </div>
            <form method="POST" onsubmit="showLoader()">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_user">
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required minlength="6">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addUserModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal" id="assignModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assign Faculty to Area</h5>
                <button type="button" class="btn-close" onclick="closeModal('assignModal')">×</button>
            </div>
            <form method="POST" id="assignForm" onsubmit="showLoader()">
                <div class="modal-body">
                    <input type="hidden" name="action" value="assign_faculty">
                    <input type="hidden" name="user_id" id="assign_user_id">
                    
                    <div class="alert alert-primary d-flex align-items-center mb-3">
                        <i class="fas fa-user-circle fa-2x me-3"></i>
                        <div>
                            <div class="small text-uppercase fw-bold opacity-75">Assigning</div>
                            <div id="assign_faculty_name" class="fw-bold"></div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Program</label>
                        <select class="form-select" id="assign_program" required onchange="loadAssignLevels()">
                            <option value="">Select Program...</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Level</label>
                        <select class="form-select" id="assign_level" required onchange="loadAssignAreas()" disabled>
                            <option value="">Select Level...</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Area</label>
                        <select name="area_id" class="form-select" id="assign_area" required disabled>
                            <option value="">Select Area...</option>
                        </select>
                        <div class="form-text">Only unassigned areas are listed.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('assignModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Assignment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Manual Modal Logic (Reverted to robust CSS toggle)
function openModal(id) {
    const modal = document.getElementById(id);
    modal.classList.add('show');
    // Ensure display is flex to center it (matches CSS)
    modal.style.display = 'flex';
}

function closeModal(id) {
    const modal = document.getElementById(id);
    modal.classList.remove('show');
    modal.style.display = 'none';
}

// Close modal if clicking outside content
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('show');
        event.target.style.display = 'none';
    }
}

// UI Interactions
function showLoader() {
    document.getElementById('globalLoader').classList.add('active');
}

// UPDATED: Confirm Action using SweetAlert2
function confirmAction(event, message, type = 'warning') {
    event.preventDefault();
    const form = event.target;
    
    // Customize based on message or passed type
    let confirmBtnColor = '#3085d6';
    let iconType = 'warning';
    
    // Determine color and icon based on keywords or type
    if(message.toLowerCase().includes('delete') || message.toLowerCase().includes('reject') || type === 'error') {
        confirmBtnColor = '#d33';
        iconType = 'warning'; // SweetAlert warning icon is better for confirmation than 'error'
    } else if (message.toLowerCase().includes('deactivate')) {
        confirmBtnColor = '#f0ad4e'; 
        iconType = 'warning';
    } else if (type === 'info') {
        iconType = 'info';
    }

    Swal.fire({
        title: 'Are you sure?',
        text: message,
        icon: iconType,
        showCancelButton: true,
        confirmButtonColor: confirmBtnColor,
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, proceed'
    }).then((result) => {
        if (result.isConfirmed) {
            showLoader();
            form.submit();
        }
    });
    
    return false;
}

// NEW FUNCTION: Handle Unassign Faculty
function unassignFaculty(areaId, areaName) {
    Swal.fire({
        title: 'Unassign ' + areaName + '?',
        text: "Warning: If the faculty is unassigned, all progress for this area will be gone! This cannot be undone.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, Unassign & Reset'
    }).then((result) => {
        if (result.isConfirmed) {
            showLoader();
            // Create a form programmatically to submit the POST request
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = ''; // Submit to current page
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'unassign_faculty';
            
            const areaInput = document.createElement('input');
            areaInput.type = 'hidden';
            areaInput.name = 'area_id';
            areaInput.value = areaId;
            
            form.appendChild(actionInput);
            form.appendChild(areaInput);
            document.body.appendChild(form);
            form.submit();
        }
    });
}

// Filter Update Functions
function updateProgramFilter(programId) {
    const url = new URL(window.location.href);
    url.searchParams.set('program', programId);
    window.location.href = url.toString();
}

function updateAssignmentFilter(assignment) {
    const url = new URL(window.location.href);
    url.searchParams.set('assignment', assignment);
    window.location.href = url.toString();
}

// Enhanced Search Functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('userSearch');
    const programFilter = document.getElementById('programFilter');
    const assignmentFilter = document.getElementById('assignmentFilter');
    const userRows = document.querySelectorAll('.user-row');
    const noResultsRow = document.getElementById('noResultsRow');
    
    if (!searchInput || userRows.length === 0) return;
    
    // Initialize search functionality
    searchInput.addEventListener('input', function() {
        filterUsers();
    });
    
    // Initialize filter functionality
    if (programFilter) {
        programFilter.addEventListener('change', function() {
            filterUsers();
        });
    }
    
    if (assignmentFilter) {
        assignmentFilter.addEventListener('change', function() {
            filterUsers();
        });
    }
    
    // Initial filter on page load
    filterUsers();
});

function filterUsers() {
    const searchInput = document.getElementById('userSearch');
    const programFilter = document.getElementById('programFilter');
    const assignmentFilter = document.getElementById('assignmentFilter');
    
    if (!searchInput) return;
    
    const searchTerm = searchInput.value.toLowerCase().trim();
    const selectedProgram = programFilter ? programFilter.value : 'all';
    const selectedAssignment = assignmentFilter ? assignmentFilter.value : 'all';
    
    const userRows = document.querySelectorAll('.user-row');
    const noResultsRow = document.getElementById('noResultsRow');
    
    let visibleCount = 0;
    
    userRows.forEach(row => {
        const name = row.getAttribute('data-name') || '';
        const email = row.getAttribute('data-email') || '';
        const username = row.getAttribute('data-username') || '';
        const userPrograms = row.getAttribute('data-programs') || '';
        const assignedStatus = row.getAttribute('data-assigned') || '';
        
        // Check search term
        let searchMatch = true;
        if (searchTerm) {
            searchMatch = name.includes(searchTerm) || 
                          email.includes(searchTerm) || 
                          username.includes(searchTerm);
        }
        
        // Check program filter
        let programMatch = true;
        if (selectedProgram !== 'all') {
            programMatch = userPrograms.includes(selectedProgram);
        }
        
        // Check assignment filter
        let assignmentMatch = true;
        if (selectedAssignment !== 'all') {
            assignmentMatch = assignedStatus === selectedAssignment;
        }
        
        // Show/hide row based on all filters
        if (searchMatch && programMatch && assignmentMatch) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    // Show/hide no results message
    if (noResultsRow) {
        if (visibleCount === 0 && userRows.length > 0) {
            noResultsRow.style.display = '';
        } else {
            noResultsRow.style.display = 'none';
        }
    }
}

// Dropdown Logic (Manual implementation)
function toggleDropdown(btn) {
    // Close all other dropdowns
    document.querySelectorAll('.dropdown-menu.show').forEach(m => {
        if(m !== btn.nextElementSibling) m.classList.remove('show');
    });
    // Toggle current
    btn.nextElementSibling.classList.toggle('show');
    event.stopPropagation();
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.kebab-menu')) {
        document.querySelectorAll('.dropdown-menu.show').forEach(m => m.classList.remove('show'));
    }
});

// Assignment Logic - Need to update the loadPrograms function to exclude archived programs
function assignFaculty(userId, facultyName) {
    document.getElementById('assign_user_id').value = userId;
    document.getElementById('assign_faculty_name').textContent = facultyName;
    document.getElementById('assign_program').value = '';
    
    const lvl = document.getElementById('assign_level');
    lvl.innerHTML = '<option value="">Select Level...</option>';
    lvl.disabled = true;
    
    const area = document.getElementById('assign_area');
    area.innerHTML = '<option value="">Select Area...</option>';
    area.disabled = true;
    
    loadPrograms();
    openModal('assignModal');
}

function loadPrograms() {
    const select = document.getElementById('assign_program');
    fetch('../api/get_programs.php?exclude_archived=1')
        .then(r => r.json())
        .then(data => {
            select.innerHTML = '<option value="">Select Program...</option>';
            if(data.success) {
                data.data.forEach(p => {
                    const opt = document.createElement('option');
                    opt.value = p.id;
                    opt.text = p.name;
                    select.appendChild(opt);
                });
            }
        });
}

function loadAssignLevels() {
    const pid = document.getElementById('assign_program').value;
    const select = document.getElementById('assign_level');
    const areaSelect = document.getElementById('assign_area');
    
    select.disabled = true;
    areaSelect.disabled = true;
    select.innerHTML = '<option>Loading...</option>';
    
    if(!pid) return;
    
    fetch(`../api/get_levels.php?program_id=${pid}`)
        .then(r => r.json())
        .then(data => {
            select.innerHTML = '<option value="">Select Level...</option>';
            if(data.success) {
                data.data.forEach(l => {
                    const opt = document.createElement('option');
                    opt.value = l.id;
                    opt.text = l.level_type;
                    select.appendChild(opt);
                });
                select.disabled = false;
            }
        });
}

function loadAssignAreas() {
    const lid = document.getElementById('assign_level').value;
    const select = document.getElementById('assign_area');
    
    select.disabled = true;
    select.innerHTML = '<option>Loading...</option>';
    
    if(!lid) return;
    
    fetch(`../api/get_unassigned_areas.php?level_id=${lid}`)
        .then(r => r.json())
        .then(data => {
            select.innerHTML = '<option value="">Select Area...</option>';
            if(data.success && data.data.length > 0) {
                data.data.forEach(a => {
                    const opt = document.createElement('option');
                    opt.value = a.id;
                    opt.text = `${a.area_number} - ${a.name}`;
                    select.appendChild(opt);
                });
                select.disabled = false;
            } else {
                select.innerHTML = '<option>No unassigned areas available</option>';
            }
        });
}
</script>

<?php include '../includes/footer.php'; ?>