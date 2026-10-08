<?php
require_once dirname(__DIR__) . '/config/config.php';
requireAdmin();

// --- LOGIC FROM HEADER (Session, Notifications, User Info) ---
function getUserInitials($name) {
    $words = explode(' ', trim($name));
    $initials = '';
    foreach ($words as $word) {
        if (!empty($word)) {
            $initials .= strtoupper(substr($word, 0, 1));
        }
    }
    return substr($initials, 0, 2);
}

$db = getDB();

// Get pending counts for notifications (Server-side initial load)
$pending_documents = 0;
$pending_users = 0;
$staff_unread_notifications = 0;

if ($_SESSION['role'] === 'admin') {
    $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE status = 'pending'");
    $stmt->execute();
    $pending_documents = $stmt->fetchColumn();
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE status = 'pending'");
    $stmt->execute();
    $pending_users = $stmt->fetchColumn();
} else {
    $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $staff_unread_notifications = $stmt->fetchColumn();
}

// Get current user info
$stmt = $db->prepare("SELECT full_name, email FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$current_user = $stmt->fetch(PDO::FETCH_ASSOC);
$user_initials = getUserInitials($current_user['full_name'] ?? $_SESSION['full_name'] ?? 'User');

$search_api = $_SESSION['role'] === 'admin' ? '../api/search.php' : '../api/staff_search.php';
$is_admin = $_SESSION['role'] === 'admin';

// --- PAGE SPECIFIC LOGIC (Programs Management) ---
$page_title = 'Programs Management';
$success = '';
$error = '';

// Handle add program form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'add_program') {
        $name = sanitize($_POST['name']);
        $code = sanitize($_POST['code']);
        $description = sanitize($_POST['description'] ?? '');
        
        try {
            $query = "INSERT INTO programs (name, code, description) VALUES (?, ?, ?)";
            $stmt = $db->prepare($query);
            if ($stmt->execute([$name, $code, $description])) {
                $success = 'Program added successfully!';
            } else {
                $error = 'Failed to add program.';
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Get all active programs
$query = "SELECT p.*, COUNT(DISTINCT l.id) as level_count 
          FROM programs p 
          LEFT JOIN levels l ON p.id = l.program_id 
          WHERE p.status = 'active' 
          GROUP BY p.id 
          ORDER BY p.name";
$stmt = $db->prepare($query);
$stmt->execute();
$programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - AccrediTrack System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* --- GLOBAL VARIABLES & SIDEBAR STYLES (MATCHING DASHBOARD) --- */
        :root {
            --color-primary-600: #2563eb;
            --color-primary-800: #1e40af;
            --primary-color: #3b82f6; 
            --primary-gradient: linear-gradient(135deg, var(--color-primary-600) 0%, var(--color-primary-800) 100%);
            --success-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            
            /* Sidebar Variables */
            --sidebar-width: 260px;
            --sidebar-width-collapsed: 80px;
            
            /* Modern UI Variables */
            --bg-body: #f5f7fb;
            --text-main: #111827;
            --text-secondary: #6b7280;
            --card-border: #f3f4f6;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px -1px rgba(0, 0, 0, 0.02);
            --card-shadow-hover: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.02);
        }
        
        * { font-family: 'Inter', sans-serif; }
        body { 
            background-color: var(--bg-body); 
            min-height: 100vh; 
            color: var(--text-main); 
            transition: margin-left 0.3s ease; /* Smooth content shift */
        }
        
        /* --- SIDEBAR STYLES --- */
        .sidebar {
            position: fixed; top: 0; left: 0; width: var(--sidebar-width); height: 100vh;
            background: var(--primary-gradient); padding: 0; z-index: 1000;
            overflow-y: inherit; overflow-x: hidden;
            box-shadow: 4px 0 20px rgba(0,0,0,0.1);
            transition: width 0.3s ease; /* Smooth width transition */
        }

        /* Collapsed State Logic */
        body.sidebar-collapsed {
            --sidebar-width: var(--sidebar-width-collapsed);
        }
        body.sidebar-collapsed .sidebar-brand-text,
        body.sidebar-collapsed .nav-text {
            opacity: 0;
            pointer-events: none;
            display: none;
        }
        body.sidebar-collapsed .sidebar-brand {
            justify-content: center;
            padding: 25px 0;
        }
        body.sidebar-collapsed .sidebar-menu {
            padding: 20px 10px;
        }
        body.sidebar-collapsed .sidebar-menu a {
            justify-content: center;
            padding: 15px 0;
        }
        body.sidebar-collapsed .sidebar-menu a i {
            margin-right: 0;
            font-size: 20px;
        }
        body.sidebar-collapsed .sidebar-menu a.active {
            border-left: none;
            border-radius: 12px;
            padding-left: 0;
        }
        body.sidebar-collapsed .sidebar-badge {
            display: none; 
        }

        /* Sidebar Standard Elements */
        .sidebar-brand {
            padding: 25px 20px; border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex; align-items: center; gap: 15px;
            white-space: nowrap; transition: all 0.3s ease;
        }
        
        .sidebar-logo-img {
            width: 45px; height: 45px; border-radius: 12px; object-fit: cover;
            background: white; box-shadow: 0 4px 6px rgba(0,0,0,0.1); flex-shrink: 0;
        }

        .sidebar-brand-icon {
            width: 45px; height: 45px; background: rgba(255,255,255,0.2);
            border-radius: 12px; display: flex; align-items: center; justify-content: center;
        }
        .sidebar-brand-icon i { font-size: 22px; color: white; }
        
        .sidebar-brand-text { 
            color: white; font-size: 20px; font-weight: 800; letter-spacing: 0.5px; 
            text-decoration: none; transition: opacity 0.2s ease;
        }
        
        .sidebar-menu { padding: 20px 15px; overflow-y: auto; height: calc(100vh - 100px); }
        .sidebar-menu::-webkit-scrollbar { width: 4px; }
        .sidebar-menu::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 2px; }

        .sidebar-menu a {
            display: flex; align-items: center; padding: 12px 18px; font-size: 14px;
            font-weight: 500; color: rgba(255,255,255,0.85); text-decoration: none;
            border-radius: 12px; margin-bottom: 5px; transition: all 0.3s ease; 
            position: relative; white-space: nowrap; overflow: hidden;
        }
        .sidebar-menu a:hover { background: rgba(255,255,255,0.15); color: white; }
        .sidebar-menu a.active {
            background: rgba(255,255,255,0.25); color: white; font-weight: 600;
            border-left: 4px solid white; padding-left: 14px;
            border-top-left-radius: 4px; border-bottom-left-radius: 4px;
        }
        .sidebar-menu a i { width: 22px; margin-right: 12px; font-size: 15px; flex-shrink: 0; text-align: center; transition: margin 0.3s ease; }
        .nav-text { transition: opacity 0.2s ease; }

        .sidebar-badge {
            position: absolute; right: 15px; top: 50%; transform: translateY(-50%);
            background: #ff4757; color: white; font-size: 11px; font-weight: 600;
            padding: 3px 8px; border-radius: 20px; min-width: 22px; text-align: center;
        }

        /* Main Content Wrapper */
        .main-content { margin-left: var(--sidebar-width); padding: 0; min-height: 100vh; transition: margin-left 0.3s ease; }

        /* Top Navigation */
        .top-nav {
            background: white; padding: 15px 30px; display: flex; justify-content: space-between;
            align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); position: sticky;
            top: 0; z-index: 100; height: 80px;
        }

        .top-nav-left { display: flex; align-items: center; gap: 15px; }

        /* Hamburger Toggle Button */
        .btn-sidebar-toggle {
            background: transparent; border: none; font-size: 20px; color: #6c757d;
            cursor: pointer; padding: 8px; border-radius: 8px; transition: all 0.2s ease;
            display: flex; align-items: center; justify-content: center;
        }
        .btn-sidebar-toggle:hover { background-color: #f3f4f6; color: var(--color-primary-600); }

        .page-title { font-size: 24px; font-weight: 700; color: #111; margin: 0; }
        .top-nav-right { display: flex; align-items: center; gap: 20px; }
        
        /* Notifications & Profile */
        .notification-bell {
            position: relative; cursor: pointer; padding: 12px; border-radius: 12px;
            transition: all 0.3s ease; background: #f8f9fa; display: flex; align-items: center; justify-content: center; width: 45px; height: 45px;
        }
        .notification-bell:hover { background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(37, 99, 235, 0.1) 100%); }
        .notification-bell i { font-size: 20px; color: #666; }
        .notification-badge {
            position: absolute; top: -2px; right: -2px; background: linear-gradient(135deg, #ff4757 0%, #ff6b81 100%);
            color: white; font-size: 10px; font-weight: 700; padding: 0; border-radius: 50%;
            min-width: 18px; height: 18px; text-align: center; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 8px rgba(255, 71, 87, 0.4); border: 2px solid white;
        }
        .notification-dropdown {
            position: absolute; top: calc(100% + 15px); right: 0; width: 380px;
            background: white; border-radius: 16px; box-shadow: 0 15px 50px rgba(0,0,0,0.15);
            z-index: 1000; display: none; border: 1px solid #e9ecef; overflow: hidden;
        }
        .notification-dropdown.show { display: block; }
        
        .notification-header {
            padding: 18px 20px; border-bottom: 1px solid #f0f0f0;
            display: flex; justify-content: space-between; align-items: center;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, rgba(37, 99, 235, 0.05) 100%);
        }
        .notification-header h6 { margin: 0; font-weight: 700; font-size: 16px; color: #333; }
        
        .mark-all-read {
            font-size: 12px; color: #3b82f6; cursor: pointer; font-weight: 600; transition: color 0.2s ease;
        }
        .mark-all-read:hover { color: #2563eb; }
        
        .notification-list { max-height: 380px; overflow-y: auto; }
        .notification-list::-webkit-scrollbar { width: 6px; }
        .notification-list::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 3px; }
        
        .notification-item {
            display: flex; align-items: flex-start; padding: 16px 20px;
            border-bottom: 1px solid #f0f0f0; cursor: pointer; transition: all 0.2s ease;
            text-decoration: none; color: inherit;
        }
        .notification-item:hover { background: #f8f9fa; }
        .notification-item.unread {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.08) 0%, rgba(37, 99, 235, 0.08) 100%);
            border-left: 3px solid #3b82f6;
        }
        
        .notification-item .notif-icon {
            width: 44px; height: 44px; border-radius: 12px; display: flex;
            align-items: center; justify-content: center; margin-right: 14px; flex-shrink: 0;
        }
        .notification-item .notif-icon.document { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: white; }
        .notification-item .notif-icon.user { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; }
        
        .notification-content { flex: 1; min-width: 0; }
        .notification-title { font-weight: 600; font-size: 14px; color: #333; margin-bottom: 4px; }
        .notification-message { font-size: 13px; color: #6c757d; margin-bottom: 6px; line-height: 1.4; }
        .notification-time { font-size: 11px; color: #adb5bd; }
        
        .notification-empty { padding: 40px 20px; text-align: center; color: #6c757d; }
        .notification-empty i { font-size: 50px; margin-bottom: 15px; opacity: 0.3; }
        
        .profile-dropdown { position: relative; }
        .profile-toggle {
            display: flex; align-items: center; gap: 12px; cursor: pointer;
            padding: 8px 15px; border-radius: 12px; transition: background 0.3s ease;
        }
        .profile-toggle:hover { background: #f0f0f0; }
        .topbar-avatar {
            width: 42px; height: 42px; border-radius: 12px; background: var(--primary-gradient);
            display: flex; align-items: center; justify-content: center; font-size: 15px;
            font-weight: 700; color: white; box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
        }
        .profile-info .name { font-weight: 600; font-size: 14px; color: #333; }
        .profile-info .role { font-size: 12px; color: #6c757d; }
        .profile-menu {
            position: absolute; top: 100%; right: 0; width: 200px; background: white;
            border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.15); z-index: 1000;
            display: none; margin-top: 10px; overflow: hidden;
        }
        .profile-menu.show { display: block; }
        .profile-menu a {
            display: flex; align-items: center; padding: 12px 18px; color: #333;
            text-decoration: none; transition: background 0.2s ease;
        }
        .profile-menu a:hover { background: #f8f9fa; }

        /* --- PAGE SPECIFIC RE-DESIGN --- */
        
        .content-area { padding: 30px; max-width: 1600px; margin: 0 auto; }

        /* Header Section */
        .page-header-actions {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }
        .header-title-group h2 { font-size: 1.25rem; font-weight: 700; margin: 0; color: var(--text-main); }
        .header-title-group p { margin: 4px 0 0; color: var(--text-secondary); font-size: 0.875rem; }

        /* Action Buttons & Search Wrapper */
        .header-actions-group {
            display: flex; align-items: center; gap: 15px;
        }

        /* Specific Program Search Bar */
        .program-search {
            position: relative;
        }
        .program-search input {
            padding: 10px 15px 10px 40px;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            font-size: 0.9rem;
            width: 280px;
            transition: all 0.2s;
        }
        .program-search input:focus {
            outline: none;
            border-color: var(--color-primary-600);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        .program-search i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 0.9rem;
        }

        .btn-add-program {
            background: var(--color-primary-600); color: white; padding: 10px 20px;
            border-radius: 12px; border: none; font-weight: 500; font-size: 14px;
            display: flex; align-items: center; gap: 8px; transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }
        .btn-add-program:hover {
            background: var(--color-primary-800); transform: translateY(-1px);
            box-shadow: 0 6px 15px rgba(37, 99, 235, 0.3);
        }

        /* Programs Grid */
        .programs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 24px;
        }

        /* Elegant Card Design */
        .program-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            border: 1px solid var(--card-border);
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
        }
        
        .program-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--card-shadow-hover);
            border-color: rgba(37, 99, 235, 0.2);
        }

        .program-icon-wrapper {
            width: 48px; height: 48px;
            border-radius: 12px;
            background: #eff6ff;
            color: var(--color-primary-600);
            display: flex; align-items: center; justify-content: center;
            font-size: 20px;
            margin-bottom: 20px;
        }

        .program-content { flex: 1; }

        .program-name {
            font-size: 1.1rem; font-weight: 700; color: var(--text-main);
            margin-bottom: 6px; line-height: 1.4;
        }

        .program-code {
            font-size: 0.75rem; font-weight: 600; text-transform: uppercase;
            letter-spacing: 0.5px; color: var(--color-primary-600);
            background: rgba(37, 99, 235, 0.08); padding: 4px 10px;
            border-radius: 6px; display: inline-block; margin-bottom: 12px;
        }

        .program-desc {
            font-size: 0.875rem; color: var(--text-secondary);
            margin-bottom: 20px; line-height: 1.5;
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
        }

        .program-meta {
            display: flex; align-items: center; gap: 15px;
            margin-bottom: 24px; padding-bottom: 20px;
            border-bottom: 1px solid #f3f4f6;
        }

        .meta-item { display: flex; align-items: center; gap: 6px; font-size: 0.85rem; color: var(--text-secondary); }
        .meta-item i { color: #9ca3af; }

        /* refined buttons */
        .card-actions {
            display: flex; align-items: center; gap: 10px;
        }

        .btn-view-levels {
            flex: 1;
            background: white; border: 1px solid #e5e7eb;
            color: var(--text-main); padding: 10px;
            border-radius: 10px; font-size: 0.9rem; font-weight: 600;
            text-align: center; text-decoration: none;
            transition: all 0.2s;
        }
        .btn-view-levels:hover {
            border-color: var(--color-primary-600);
            color: var(--color-primary-600);
            background: #eff6ff;
        }

        .btn-action-icon {
            width: 40px; height: 40px;
            border-radius: 10px; border: 1px solid transparent;
            background: transparent;
            display: flex; align-items: center; justify-content: center;
            color: #9ca3af; transition: all 0.2s; cursor: pointer;
        }
        
        .btn-action-icon:hover { background: #f9fafb; color: var(--text-main); }
        
        .btn-action-icon.add-level:hover { color: var(--color-primary-600); background: #eff6ff; }
        .btn-action-icon.archive:hover { color: #ef4444; background: #fef2f2; }

        /* Modal Refinement */
        .modal-content { border-radius: 20px; border: none; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); }
        .modal-header { border-bottom: 1px solid #f3f4f6; padding: 24px; border-radius: 20px 20px 0 0; background: white; }
        .modal-title { font-weight: 700; color: var(--text-main); }
        .modal-body { padding: 24px; }
        .modal-footer { border-top: 1px solid #f3f4f6; padding: 20px 24px; }
        .form-label { font-size: 0.875rem; font-weight: 600; color: #374151; margin-bottom: 6px; }
        .form-control, .form-select {
            padding: 12px 16px; border-radius: 10px; border: 1px solid #e5e7eb;
            font-size: 0.95rem; transition: all 0.2s;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--color-primary-600); box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }

        /* Alerts */
        .alert { border-radius: 12px; border: none; font-size: 0.9rem; padding: 16px 20px; }
        .alert-info-custom { background: white; border: 1px dashed #cbd5e1; color: #64748b; text-align: center; padding: 40px; }

        /* Responsive */
        @media (max-width: 768px) {
            .search-container input { width: 180px; }
            .programs-grid { grid-template-columns: 1fr; }
            .header-actions-group { width: 100%; flex-direction: column; align-items: stretch; }
            .program-search input { width: 100%; }
        }
    </style>
</head>
<body>
    <script>
        if (localStorage.getItem('sidebarState') === 'collapsed') {
            document.body.classList.add('sidebar-collapsed');
        }
    </script>

    <nav class="sidebar">
        <div class="sidebar-brand">
            <img src="../assets/img/accreditrack_official_logo.png" alt="Logo" class="sidebar-logo-img" onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
            <div class="sidebar-brand-icon" style="display:none; width:45px; height:45px; background:rgba(255,255,255,0.2); border-radius:12px; align-items:center; justify-content:center;">
                <i class="fas fa-graduation-cap" style="color:white; font-size:22px;"></i>
            </div>
            <a href="dashboard.php" class="sidebar-brand-text">AccrediTrack</a>
        </div>
        
        <?php if ($_SESSION['role'] === 'admin'): ?>
        <div class="sidebar-menu">
            <a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i><span class="nav-text">Dashboard</span>
            </a>
            <a href="programs.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'programs.php' || basename($_SERVER['PHP_SELF']) == 'program_view.php' ? 'active' : ''; ?>">
                <i class="fas fa-folder"></i><span class="nav-text">Programs</span>
            </a>
            <a href="validation.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'validation.php' ? 'active' : ''; ?>">
                <i class="fas fa-check-circle"></i><span class="nav-text">Validation</span>
                <?php if ($pending_documents > 0): ?>
                    <span class="sidebar-badge"><?php echo $pending_documents; ?></span>
                <?php endif; ?>
            </a>
            <a href="global_folders.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'global_folders.php' ? 'active' : ''; ?>">
                <i class="fas fa-folder-tree"></i><span class="nav-text">Folders</span>
            </a>
            <a href="user_management.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'user_management.php' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i><span class="nav-text">Users</span>
                <?php if ($pending_users > 0): ?>
                    <span class="sidebar-badge"><?php echo $pending_users; ?></span>
                <?php endif; ?>
            </a>
            
            <a href="archives.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'archives.php' ? 'active' : ''; ?>">
                <i class="fas fa-archive"></i><span class="nav-text">Archives</span>
            </a>
            <a href="reports.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i><span class="nav-text">Reports</span>
            </a>
            <a href="profile.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
                <i class="fas fa-user"></i><span class="nav-text">Profile</span>
            </a>
            <hr style="border-color: rgba(255,255,255,0.2); margin: 15px 0;">
            <a href="../auth/logout.php">
                <i class="fas fa-sign-out-alt"></i><span class="nav-text">Logout</span>
            </a>
        </div>
        <?php else: ?>
        <div class="sidebar-menu">
            </div>
        <?php endif; ?>
    </nav>
    
    <div class="main-content">
        <div class="top-nav">
            <div class="top-nav-left">
                <button id="sidebarToggle" class="btn-sidebar-toggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="page-title">Programs</h1>
            </div>
            
            <div class="top-nav-right">
                <div class="notification-bell" id="notificationBell">
                    <i class="fas fa-bell"></i>
                    <?php 
                    $total_notifications = $is_admin ? ($pending_documents + $pending_users) : $staff_unread_notifications;
                    if ($total_notifications > 0): 
                    ?>
                        <span class="notification-badge" id="notifBadge"><?php echo $total_notifications; ?></span>
                    <?php endif; ?>
                    
                    <div class="notification-dropdown" id="notificationDropdown">
                        <div class="notification-header">
                            <h6><i class="fas fa-bell me-2"></i>Notifications</h6>
                            <span class="mark-all-read" onclick="markAllAsRead(event)">Mark all as read</span>
                        </div>
                        <div class="notification-list" id="notificationList">
                            <div class="notification-empty">
                                <i class="fas fa-bell-slash"></i>
                                <p>Loading notifications...</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="profile-dropdown">
                    <div class="profile-toggle" onclick="toggleProfileMenu()">
                        <div class="topbar-avatar">
                            <?php echo $user_initials; ?>
                        </div>
                        <div class="profile-info">
                            <div class="name"><?php echo htmlspecialchars($current_user['full_name'] ?? $_SESSION['full_name'] ?? 'User'); ?></div>
                            <div class="role"><?php echo ucfirst($_SESSION['role']); ?></div>
                        </div>
                        <i class="fas fa-chevron-down" style="color: #6c757d; font-size: 12px;"></i>
                    </div>
                    
                    <div class="profile-menu" id="profileMenu">
                        <a href="profile.php"><i class="fas fa-user"></i>My Profile</a>
                        <a href="../auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i>Logout</a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="content-area">
            
            <div class="page-header-actions">
                <div class="header-title-group">
                    <h2>Programs Management</h2>
                    <p>Manage academic programs and accreditation levels</p>
                </div>
                
                <div class="header-actions-group">
                    <div class="program-search">
                        <i class="fas fa-search"></i>
                        <input type="text" id="programSearchInput" placeholder="Search programs or codes...">
                    </div>

                    <button class="btn-add-program" data-bs-toggle="modal" data-bs-target="#addProgramModal">
                        <i class="fas fa-plus"></i>
                        <span>New Program</span>
                    </button>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (empty($programs)): ?>
                <div class="alert-info-custom">
                    <div style="font-size: 48px; margin-bottom: 20px; opacity: 0.3;">
                        <i class="fas fa-folder-open"></i>
                    </div>
                    <h4>No Programs Yet</h4>
                    <p class="mb-4">Create your first academic program to start tracking accreditation.</p>
                    <button class="btn-add-program" style="margin: 0 auto;" data-bs-toggle="modal" data-bs-target="#addProgramModal">
                        Create Program
                    </button>
                </div>
            <?php else: ?>
                <div class="programs-grid" id="programsGrid">
                    <?php foreach ($programs as $program): ?>
                        <div class="program-card">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="program-icon-wrapper">
                                    <i class="fas fa-graduation-cap"></i>
                                </div>
                                <span class="program-code"><?php echo htmlspecialchars($program['code']); ?></span>
                            </div>
                            
                            <div class="program-content">
                                <h3 class="program-name"><?php echo htmlspecialchars($program['name']); ?></h3>
                                <div class="program-desc">
                                    <?php echo !empty($program['description']) ? htmlspecialchars($program['description']) : 'No description provided for this program.'; ?>
                                </div>
                                
                                <div class="program-meta">
                                    <div class="meta-item">
                                        <i class="fas fa-layer-group"></i>
                                        <strong><?php echo $program['level_count']; ?></strong> Levels
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-calendar-alt"></i>
                                        <span>Active</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card-actions">
                                <a href="program_view.php?id=<?php echo $program['id']; ?>" class="btn-view-levels">
                                    View Levels
                                </a>
                                <button class="btn-action-icon add-level" title="Add Level" onclick="openAddLevelModal(<?php echo $program['id']; ?>)">
                                    <i class="fas fa-plus"></i>
                                </button>
                                <button class="btn-action-icon archive" title="Archive Program" onclick="archiveProgram(<?php echo $program['id']; ?>)">
                                    <i class="fas fa-archive"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div id="noResultsMessage" class="alert-info-custom mt-4" style="display: none;">
                    <div style="font-size: 32px; margin-bottom: 15px; opacity: 0.3;">
                        <i class="fas fa-search"></i>
                    </div>
                    <h5>No programs found</h5>
                    <p class="mb-0 text-muted">Try adjusting your search query.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="modal fade" id="addProgramModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Program</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_program">
                        <div class="mb-3">
                            <label class="form-label">Program Name</label>
                            <input type="text" name="name" class="form-control" placeholder="e.g. Bachelor of Science in Information Technology" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Program Code</label>
                            <input type="text" name="code" class="form-control" placeholder="e.g. BSIT" required>
                        </div>
                        <div class="mb-0">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Brief description of the program..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" style="background: var(--color-primary-600);">Create Program</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addLevelModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Accreditation Level</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addLevelForm">
                    <div class="modal-body">
                        <input type="hidden" name="program_id" id="programIdInput">
                        <div class="mb-3">
                            <label class="form-label">Level Type</label>
                            <select name="level_type" id="levelTypeInput" class="form-select" required>
                                <option value="">Select Level...</option>
                                <option value="Level 1">Level 1</option>
                                <option value="Level 2">Level 2</option>
                                <option value="Preliminary">Preliminary</option>
                            </select>
                        </div>
                        <div class="alert alert-info" style="font-size: 0.85rem; margin-bottom: 0;">
                            <i class="fas fa-info-circle me-1"></i>
                            This will automatically generate the standard 10 areas with their parameters and indicators.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" style="background: var(--color-primary-600);">Create Level</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Configuration
    const isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;
    const searchApi = '<?php echo $search_api; ?>';
    
    // Initialize on DOM load
    document.addEventListener('DOMContentLoaded', function() {
        initSidebarToggle(); // Initialize Sidebar Toggle Logic
        initNotifications();
        loadNotifications();
    });

    // ==================== SIDEBAR TOGGLE ====================
    function initSidebarToggle() {
        const toggleBtn = document.getElementById('sidebarToggle');
        
        if (!toggleBtn) return;
        
        toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            document.body.classList.toggle('sidebar-collapsed');
            
            // Persist State
            if (document.body.classList.contains('sidebar-collapsed')) {
                localStorage.setItem('sidebarState', 'collapsed');
            } else {
                localStorage.setItem('sidebarState', 'expanded');
            }
        });
    }

    // Toggle Profile Menu
    function toggleProfileMenu() {
        const menu = document.getElementById('profileMenu');
        menu.classList.toggle('show');
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.profile-dropdown')) {
            const menu = document.getElementById('profileMenu');
            if (menu) menu.classList.remove('show');
        }
        if (!e.target.closest('.notification-bell')) {
            const notif = document.getElementById('notificationDropdown');
            if (notif) notif.classList.remove('show');
        }
    });

    // --- NOTIFICATION LOGIC ---
    function initNotifications() {
        const bell = document.getElementById('notificationBell');
        if(bell) {
            bell.addEventListener('click', function(e) {
                e.stopPropagation();
                document.getElementById('notificationDropdown').classList.toggle('show');
                if (document.getElementById('notificationDropdown').classList.contains('show')) {
                    loadNotifications();
                }
            });
        }
    }

    function loadNotifications() {
        // Load admin notifications
        fetch('../api/get_pending_notifications.php')
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    displayNotifications(data.notifications);
                    updateBadge(data.total_count);
                }
            })
            .catch(err => console.error('Error:', err));
    }

    function displayNotifications(notifs) {
        const list = document.getElementById('notificationList');
        if (!notifs || notifs.length === 0) {
            list.innerHTML = '<div class="notification-empty"><i class="fas fa-bell-slash"></i><p>No new notifications</p></div>';
            return;
        }
        
        list.innerHTML = notifs.map(n => {
            let iconClass = 'document', iconName = 'fa-file-alt', url = 'validation.php';
            if(n.type === 'user_registration') { iconClass = 'user'; iconName = 'fa-user-plus'; url = 'user_management.php#pending'; }
            else if(n.type === 'document_upload') { iconClass = 'document'; iconName = 'fa-file-upload'; url = 'validation.php'; }
            
            return `
            <a href="${url}" class="notification-item ${n.is_read ? '' : 'unread'}">
                <div class="notif-icon ${iconClass}"><i class="fas ${iconName}"></i></div>
                <div class="notification-content">
                    <div class="notification-title">${escapeHtml(n.title)}</div>
                    <div class="notification-message">${escapeHtml(n.message)}</div>
                    <div class="notification-time">${formatTimeAgo(n.created_at)}</div>
                </div>
            </a>`;
        }).join('');
    }

    function updateBadge(count) {
        const badge = document.getElementById('notifBadge');
        if(badge) {
            if(count > 0) { badge.textContent = count; badge.style.display = 'block'; }
            else { badge.style.display = 'none'; }
        }
    }

    function markAllAsRead(e) {
        e.preventDefault(); e.stopPropagation();
        fetch('../api/notifications.php', { method: 'POST', body: JSON.stringify({action: 'mark_read'}) })
        .then(r => r.json()).then(d => { if(d.success) loadNotifications(); });
    }

    // --- UTILS ---
    function formatTimeAgo(dateString) {
        const date = new Date(dateString);
        const seconds = Math.floor((new Date() - date) / 1000);
        if (seconds < 60) return 'Just now';
        if (seconds < 3600) return Math.floor(seconds/60) + 'm ago';
        if (seconds < 86400) return Math.floor(seconds/3600) + 'h ago';
        return date.toLocaleDateString();
    }
    function escapeHtml(text) { return text ? String(text).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','\'':'&#39;','"':'&quot;'}[m])) : ''; }

    // --- Page Specific Scripts ---
    
    // Search Filter Script
    document.getElementById('programSearchInput').addEventListener('keyup', function() {
        const searchValue = this.value.toLowerCase().trim();
        const programs = document.querySelectorAll('.program-card');
        const noResultsMsg = document.getElementById('noResultsMessage');
        let visibleCount = 0;
        
        programs.forEach(card => {
            const name = card.querySelector('.program-name').textContent.toLowerCase();
            const code = card.querySelector('.program-code').textContent.toLowerCase();
            
            if (name.includes(searchValue) || code.includes(searchValue)) {
                card.style.display = ''; // Show
                visibleCount++;
            } else {
                card.style.display = 'none'; // Hide
            }
        });

        // Show/Hide "No Results" message
        if (visibleCount === 0 && programs.length > 0) {
            noResultsMsg.style.display = 'block';
        } else {
            noResultsMsg.style.display = 'none';
        }
    });

    function openAddLevelModal(programId) {
        document.getElementById('programIdInput').value = programId;
        document.getElementById('levelTypeInput').value = '';
        new bootstrap.Modal(document.getElementById('addLevelModal')).show();
    }

    // Handle Add Level Form Submission
    document.getElementById('addLevelForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const programId = document.getElementById('programIdInput').value;
        const levelType = document.getElementById('levelTypeInput').value;
        
        if (!programId || !levelType) {
            alert('Please select a level type');
            return;
        }
        
        const button = this.querySelector('button[type="submit"]');
        const originalText = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creating...';
        
        fetch('../api/add_level.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ program_id: programId, level_type: levelType })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
                button.disabled = false;
                button.innerHTML = originalText;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error adding level');
            button.disabled = false;
            button.innerHTML = originalText;
        });
    });

    // SweetAlert Archive Program
    function archiveProgram(programId) {
        Swal.fire({
            title: 'Archive Program?',
            text: "This program will be moved to the Archives section. You can restore it later.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, archive it!'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('../api/archive_program.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ program_id: programId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire(
                            'Archived!',
                            'The program has been archived.',
                            'success'
                        ).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire(
                            'Error!',
                            'Failed to archive: ' + (data.message || 'Unknown error'),
                            'error'
                        );
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire('Error!', 'Something went wrong.', 'error');
                });
            }
        });
    }
    </script>
</body>
</html>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>