<?php
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

// Get pending counts for notifications
$database = new Database();
$db = $database->getConnection();

// Get pending documents count for admin
$pending_documents = 0;
$pending_users = 0;
$staff_unread_notifications = 0;

if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    $stmt = $db->query("SELECT COUNT(*) FROM documents WHERE status = 'pending'");
    $pending_documents = $stmt->fetchColumn();
    
    $stmt = $db->query("SELECT COUNT(*) FROM users WHERE status = 'pending'");
    $pending_users = $stmt->fetchColumn();
} elseif (isset($_SESSION['user_id'])) {
    // Get unread notifications for staff
    $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $staff_unread_notifications = $stmt->fetchColumn();
}

// Get current user info
if (isset($_SESSION['user_id'])) {
    $stmt = $db->prepare("SELECT full_name, email FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
    $user_initials = getUserInitials($current_user['full_name'] ?? $_SESSION['full_name'] ?? 'User');
} else {
    $current_user = ['full_name' => 'Guest'];
    $user_initials = 'G';
}

$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$search_api = $is_admin ? '../api/search.php' : '../api/staff_search.php';

// --- LOGIC TO HIDE SEARCH BAR ON SPECIFIC ADMIN PAGES ---
$current_page = basename($_SERVER['PHP_SELF']);
$admin_pages_no_search = [
    'validation.php', 
    'global_folders.php', 
    'user_management.php', 
    'reports.php', 
    'profile.php'
];

// Show search IF: (User is NOT admin) OR (Current page is NOT in the hidden list)
$show_search_bar = !$is_admin || !in_array($current_page, $admin_pages_no_search);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>AccrediTrack System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            /* Updated to Blue Palette to match admin/dashboard.php */
            --color-primary-600: #2563eb;
            --color-primary-800: #1e40af;
            
            --primary-color: #3b82f6; 
            --primary-gradient: linear-gradient(135deg, var(--color-primary-600) 0%, var(--color-primary-800) 100%);
            --success-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            
            /* Sidebar Variables */
            --sidebar-width: 260px;
            --sidebar-width-collapsed: 80px;
            --header-height: 80px;
        }
        
        * {
            font-family: 'Inter', sans-serif;
            box-sizing: border-box;
        }
        
        body {
            background-color: #f5f7fb;
            min-height: 100vh;
            margin: 0;
            padding: 0;
            transition: margin-left 0.3s ease; /* Smooth transition for content shift */
        }
        
        /* --- SIDEBAR STYLES --- */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--primary-gradient);
            padding: 0;
            z-index: 1000;
            overflow-y: inherit;
            overflow-x: hidden;
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

        /* Standard Sidebar Elements */
        .sidebar-brand {
            padding: 25px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            gap: 15px;
            white-space: nowrap;
            transition: all 0.3s ease;
        }
        
        .sidebar-logo-img {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            object-fit: cover;
            background: white; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            flex-shrink: 0;
        }
        
        .sidebar-brand-text {
            color: white;
            font-size: 19px;
            font-weight: 700;
            text-decoration: none;
            transition: opacity 0.2s ease;
        }
        
        .sidebar-menu {
            padding: 20px 15px;
            overflow-y: auto;
            height: calc(100vh - 100px);
        }

        .sidebar-menu::-webkit-scrollbar { width: 4px; }
        .sidebar-menu::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 2px; }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 12px 18px;
            font-size: 14px;
            font-weight: 500;
            color: rgba(255,255,255,0.85);
            text-decoration: none;
            border-radius: 12px;
            margin-bottom: 5px;
            transition: all 0.3s ease;
            position: relative;
            white-space: nowrap;
            overflow: hidden;
        }
        
        .sidebar-menu a:hover {
            background: rgba(255,255,255,0.15);
            color: white;
        }
        
        .sidebar-menu a.active {
            background: rgba(255,255,255,0.25);
            color: white;
            font-weight: 600;
            border-left: 4px solid white;
            padding-left: 14px;
        }
        
        .sidebar-menu a i {
            width: 22px;
            margin-right: 12px;
            font-size: 15px;
            flex-shrink: 0;
            text-align: center;
            transition: margin 0.3s ease;
        }
        
        .nav-text {
            transition: opacity 0.2s ease;
        }
        
        .sidebar-badge {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: #ff4757;
            color: white;
            font-size: 11px;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 20px;
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }
        
        /* Top Navigation */
        .top-nav {
            background: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 100;
            height: var(--header-height);
        }

        .top-nav-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        /* Hamburger Toggle Button */
        .btn-sidebar-toggle {
            background: transparent;
            border: none;
            font-size: 20px;
            color: #6c757d;
            cursor: pointer;
            padding: 8px;
            border-radius: 8px;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-sidebar-toggle:hover {
            background-color: #f3f4f6;
            color: var(--color-primary-600);
        }
        
        .page-title {
            font-size: 20px;
            font-weight: 700;
            color: #333;
            margin: 0;
        }
        
        .top-nav-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        /* Search Bar */
        .search-container {
            position: relative;
        }
        
        .search-container input {
            width: 250px;
            padding: 10px 15px 10px 42px;
            border: 1px solid #e9ecef;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #f8f9fa;
            outline: none;
        }
        
        .search-container input:focus {
            border-color: var(--color-primary-600);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.15);
            background: white;
        }
        
        .search-container .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 15px;
            pointer-events: none;
        }
        
        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            margin-top: 8px;
            max-height: 400px;
            overflow-y: auto;
            z-index: 1001;
            display: none;
            border: 1px solid #e9ecef;
        }
        
        .search-results.show {
            display: block;
        }
        
        .search-result-item {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            color: inherit;
        }
        
        .search-result-item:hover { background: #f8f9fa; }
        .search-result-item:last-child { border-bottom: none; }
        
        /* --- UPDATED NOTIFICATION STYLE (Matching Dashboard) --- */
        .notification-bell { 
            position: relative; 
            cursor: pointer; 
            padding: 12px; 
            border-radius: 12px; 
            transition: all 0.3s ease; 
            background: #f8f9fa; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            width: 45px; 
            height: 45px; 
        }
        
        .notification-bell:hover { 
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(37, 99, 235, 0.1) 100%); 
        }
        
        .notification-bell i { 
            font-size: 20px; 
            color: #666; 
        }
        
        .notification-badge { 
            position: absolute; 
            top: -2px; 
            right: -2px; 
            background-color: #ff4d4f; 
            color: white; 
            font-size: 10px; 
            font-weight: 700; 
            padding: 0; 
            border-radius: 50%; 
            min-width: 18px; 
            height: 18px;
            display: flex; 
            align-items: center; 
            justify-content: center;
            box-shadow: 0 2px 5px rgba(255, 77, 79, 0.4); 
            border: 2px solid white;
        }

        .notification-dropdown { 
            position: absolute; 
            top: calc(100% + 15px); 
            right: -10px; 
            width: 380px; 
            background: white; 
            border-radius: 12px; 
            box-shadow: 0 5px 30px rgba(0,0,0,0.15); 
            z-index: 1000; 
            display: none; 
            border: none; 
            overflow: hidden; 
        }
        
        .notification-dropdown.show { display: block; }
        
        .notification-header { 
            padding: 16px 20px; 
            border-bottom: 1px solid #f0f0f0; 
            background: white;
            display: flex; 
            justify-content: space-between; 
            align-items: center;
        }
        
        .notification-header h6 { 
            margin: 0; 
            font-weight: 700; 
            font-size: 16px; 
            color: #333; 
        }
        
        .mark-all-read { 
            font-size: 13px; 
            color: #3b82f6; 
            cursor: pointer; 
            font-weight: 500; 
            text-decoration: none; 
        }
        
        .mark-all-read:hover { text-decoration: underline; }

        .notification-list { 
            max-height: 400px; 
            overflow-y: auto; 
            padding: 0; 
        }
        
        .notification-list::-webkit-scrollbar { width: 6px; }
        .notification-list::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 3px; }

        .notification-item { 
            display: flex; 
            align-items: flex-start; 
            padding: 16px 20px; 
            border-bottom: 1px solid #f5f5f5; 
            cursor: pointer; 
            transition: background 0.2s ease; 
            text-decoration: none; 
            color: inherit; 
            background: white;
        }
        
        .notification-item:last-child { border-bottom: none; }
        .notification-item:hover { background: #f9fafb; }
        
        .notification-item .notif-icon { 
            width: 48px; 
            height: 48px; 
            border-radius: 12px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            margin-right: 16px; 
            flex-shrink: 0; 
            font-size: 20px;
        }
        
        .notification-item .notif-icon.document { background-color: #3b82f6; color: white; }
        .notification-item .notif-icon.user { background-color: #10b981; color: white; }
        .notification-item .notif-icon.approved { background-color: #10b981; color: white; }
        .notification-item .notif-icon.rejected { background-color: #ff4757; color: white; }
        
        .notification-content { flex: 1; min-width: 0; }
        
        .notification-title { 
            font-weight: 600; 
            font-size: 14px; 
            color: #1f2937; 
            margin-bottom: 4px; 
            line-height: 1.3; 
        }
        
        .notification-message { 
            font-size: 13px; 
            color: #6b7280; 
            margin-bottom: 6px; 
            line-height: 1.4; 
            display: -webkit-box; 
            -webkit-line-clamp: 2; 
            -webkit-box-orient: vertical; 
            overflow: hidden; 
        }
        
        .notification-time { font-size: 11px; color: #9ca3af; font-weight: 500; }
        .notification-empty { padding: 40px 20px; text-align: center; color: #6c757d; }
        /* --- END NOTIFICATION STYLE --- */

        /* Profile */
        .profile-dropdown { position: relative; }
        .profile-toggle { display: flex; align-items: center; gap: 12px; cursor: pointer; padding: 8px 15px; border-radius: 12px; transition: background 0.3s ease; }
        .profile-toggle:hover { background: #f0f0f0; }
        .topbar-avatar { width: 42px; height: 42px; border-radius: 12px; background: var(--primary-gradient); display: flex; align-items: center; justify-content: center; font-size: 15px; font-weight: 700; color: white; box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3); }
        .profile-info .name { font-weight: 600; font-size: 14px; color: #333; }
        .profile-info .role { font-size: 12px; color: #6c757d; }
        .profile-menu { position: absolute; top: 100%; right: 0; width: 200px; background: white; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.15); z-index: 1000; display: none; margin-top: 10px; overflow: hidden; }
        .profile-menu.show { display: block; }
        .profile-menu a { display: flex; align-items: center; padding: 12px 18px; color: #333; text-decoration: none; transition: background 0.2s ease; }
        .profile-menu a:hover { background: #f8f9fa; }
        .profile-menu a i { width: 20px; margin-right: 10px; color: #6c757d; }
        .profile-menu .logout { border-top: 1px solid #f0f0f0; color: #dc3545; }
        .profile-menu .logout i { color: #dc3545; }
        
        .content-area { padding: 30px; }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .search-container input { width: 180px; }
            .page-title { font-size: 18px; }
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
            <a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i><span class="nav-text">Dashboard</span>
            </a>
            <a href="assigned_areas.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'assigned_areas.php' ? 'active' : ''; ?>">
                <i class="fas fa-folder-open"></i><span class="nav-text">My Areas</span>
            </a>
            <a href="archives.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'archives.php' ? 'active' : ''; ?>">
                <i class="fas fa-archive"></i><span class="nav-text">Archives</span>
            </a>
            <a href="reports.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i><span class="nav-text">Reports</span>
            </a>
            
            <a href="notifications.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'active' : ''; ?>">
                <i class="fas fa-bell"></i><span class="nav-text">Notifications</span>
                <?php if ($staff_unread_notifications > 0): ?>
                    <span class="sidebar-badge"><?php echo $staff_unread_notifications; ?></span>
                <?php endif; ?>
            </a>
            <a href="profile.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
                <i class="fas fa-user"></i><span class="nav-text">Profile</span>
            </a>
            <hr style="border-color: rgba(255,255,255,0.2); margin: 15px 0;">
            <a href="../auth/logout.php">
                <i class="fas fa-sign-out-alt"></i><span class="nav-text">Logout</span>
            </a>
        </div>
        <?php endif; ?>
    </nav>
    
    <div class="main-content">
        <div class="top-nav">
            <div class="top-nav-left">
                <button id="sidebarToggle" class="btn-sidebar-toggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="page-title"><?php echo $page_title ?? 'Dashboard'; ?></h1>
            </div>
            
            <div class="top-nav-right">
                
                <?php if ($show_search_bar): ?>
                <div class="search-container">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="globalSearch" placeholder="Search areas, files, reports..." autocomplete="off">
                    <div class="search-results" id="searchResults"></div>
                </div>
                <?php endif; ?>

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
                            <h6>Notifications</h6>
                            <a href="#" class="mark-all-read" onclick="markAllAsRead(event)">Mark all as read</a>
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
                        <a href="profile.php">
                            <i class="fas fa-user"></i>My Profile
                        </a>
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                        <a href="reports.php">
                            <i class="fas fa-chart-bar"></i>Reports
                        </a>
                        <?php endif; ?>
                        <a href="../auth/logout.php" class="logout">
                            <i class="fas fa-sign-out-alt"></i>Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="content-area">

    <script>
    (function() { // Wrap in IIFE to prevent global variable collisions
        // Configuration
        const isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;
        const searchApi = '<?php echo $search_api; ?>';
        
        // Static Navigation Links for "Terms" search
        const staticPages = [
            { keywords: ['profile', 'account', 'me', 'user'], title: 'My Profile', url: 'profile.php', icon: 'fa-user', type: 'profile', desc: 'View your account details' },
            { keywords: ['archive', 'history', 'old'], title: 'Archives', url: 'archives.php', icon: 'fa-archive', type: 'archive', desc: 'View archived programs' },
            { keywords: ['report', 'analytics', 'stats', 'chart'], title: 'Reports', url: 'reports.php', icon: 'fa-chart-bar', type: 'report', desc: 'View accreditation reports' },
            { keywords: ['area', 'domain', 'standard'], title: 'My Areas', url: 'assigned_areas.php', icon: 'fa-layer-group', type: 'area', desc: 'View assigned areas' },
            { keywords: ['file', 'document', 'upload'], title: 'My Documents', url: 'assigned_areas.php', icon: 'fa-file-alt', type: 'document', desc: 'Manage uploaded documents' }
        ];

        // Initialize on DOM load
        document.addEventListener('DOMContentLoaded', function() {
            initSidebarToggle(); // Initialize the new sidebar logic
            initSearch();
            initNotifications();
            loadNotifications();
        });

        // ==================== SIDEBAR TOGGLE ====================
        function initSidebarToggle() {
            const toggleBtn = document.getElementById('sidebarToggle');
            const body = document.body;
            
            if (!toggleBtn) return;
            
            toggleBtn.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Toggle Class
                body.classList.toggle('sidebar-collapsed');
                
                // Persist State
                if (body.classList.contains('sidebar-collapsed')) {
                    localStorage.setItem('sidebarState', 'collapsed');
                } else {
                    localStorage.setItem('sidebarState', 'expanded');
                }
                
                // Optional: Trigger resize event for Charts.js to redraw if charts exist
                setTimeout(() => {
                    window.dispatchEvent(new Event('resize'));
                }, 300);
            });
        }
        
        // ==================== SEARCH FUNCTIONALITY ====================
        function initSearch() {
            const searchInput = document.getElementById('globalSearch');
            const searchResults = document.getElementById('searchResults');
            
            if (!searchInput) return;
            
            let searchTimeout;
            
            searchInput.addEventListener('input', function(e) {
                clearTimeout(searchTimeout);
                const query = e.target.value.trim();
                
                if (query.length < 2) {
                    searchResults.classList.remove('show');
                    return;
                }
                
                searchTimeout = setTimeout(() => performSearch(query), 300);
            });
            
            searchInput.addEventListener('focus', function() {
                if (this.value.trim().length >= 2) {
                    searchResults.classList.add('show');
                }
            });
            
            // Close search results when clicking outside
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.search-container')) {
                    searchResults.classList.remove('show');
                }
            });
        }
        
        function performSearch(query) {
            const searchResults = document.getElementById('searchResults');
            const lowerQuery = query.toLowerCase();
            
            // 1. Find matching static pages
            const staticResults = staticPages.filter(page => 
                page.keywords.some(k => k.includes(lowerQuery)) || 
                page.title.toLowerCase().includes(lowerQuery)
            );
            
            // 2. Fetch API results
            fetch(`${searchApi}?q=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    const apiResults = data.results || [];
                    const allResults = [...staticResults, ...apiResults]; // Combine both
                    displaySearchResults(allResults);
                })
                .catch(error => {
                    console.error('Search error:', error);
                    // Even if API fails, show static results if any
                    if (staticResults.length > 0) {
                        displaySearchResults(staticResults);
                    } else {
                        searchResults.innerHTML = '<div class="search-no-results"><i class="fas fa-exclamation-circle"></i><p>Error loading results</p></div>';
                        searchResults.classList.add('show');
                    }
                });
        }
        
        function displaySearchResults(results) {
            const searchResults = document.getElementById('searchResults');
            
            if (results.length === 0) {
                searchResults.innerHTML = '<div class="search-no-results"><i class="fas fa-search"></i><p>No results found</p></div>';
                searchResults.classList.add('show');
                return;
            }
            
            const iconClasses = {
                'program': 'icon-program', 'area': 'icon-area', 'user': 'icon-user',
                'document': 'icon-document', 'archive': 'icon-archive', 'profile': 'icon-profile', 'report': 'icon-report'
            };

            const iconMap = {
                'program': 'fa-folder', 'area': 'fa-layer-group', 'user': 'fa-user',
                'document': 'fa-file-alt', 'archive': 'fa-archive', 'profile': 'fa-user-circle', 'report': 'fa-chart-bar'
            };
            
            searchResults.innerHTML = results.map(result => {
                const type = result.type || 'document';
                const iconClass = iconClasses[type] || 'icon-document';
                const faIcon = result.icon || iconMap[type] || 'fa-file-alt'; // Use icon from static result or map
                
                return `
                    <a href="${result.url}" class="search-result-item">
                        <div class="search-result-icon ${iconClass}">
                            <i class="fas ${faIcon}"></i>
                        </div>
                        <div class="search-result-content">
                            <div class="search-result-title">${escapeHtml(result.title)}</div>
                            <div class="search-result-desc">${escapeHtml(result.description)}</div>
                        </div>
                    </a>
                `;
            }).join('');
            
            searchResults.classList.add('show');
        }
        
        // ==================== NOTIFICATION FUNCTIONALITY ====================
        function initNotifications() {
            const notificationBell = document.getElementById('notificationBell');
            const notificationDropdown = document.getElementById('notificationDropdown');
            
            if (!notificationBell) return;
            
            notificationBell.addEventListener('click', function(e) {
                e.stopPropagation();
                notificationDropdown.classList.toggle('show');
                if (notificationDropdown.classList.contains('show')) {
                    loadNotifications();
                }
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.notification-bell')) {
                    notificationDropdown.classList.remove('show');
                }
            });
        }
        
        function toggleNotifications() {
            const dropdown = document.getElementById('notificationDropdown');
            dropdown.classList.toggle('show');
            if (dropdown.classList.contains('show')) {
                loadNotifications();
            }
        }
        
        function loadNotifications() {
            const notificationList = document.getElementById('notificationList');
            
            if (isAdmin) {
                loadAdminNotifications();
            } else {
                loadStaffNotifications();
            }
        }
        
        function loadAdminNotifications() {
            const notificationList = document.getElementById('notificationList');
            
            // Fetch pending documents and pending users
            Promise.all([
                fetch('../api/get_pending_notifications.php').then(r => r.json())
            ]).then(([data]) => {
                if (data.success) {
                    displayAdminNotifications(data.notifications);
                    updateNotificationBadge(data.total_count);
                }
            }).catch(error => {
                console.error('Error loading notifications:', error);
                notificationList.innerHTML = '<div class="notification-empty"><i class="fas fa-exclamation-circle"></i><p>Error loading notifications</p></div>';
            });
        }
        
        function displayAdminNotifications(notifications) {
            const notificationList = document.getElementById('notificationList');
            
            if (!notifications || notifications.length === 0) {
                notificationList.innerHTML = '<div class="notification-empty"><i class="fas fa-bell-slash"></i><p>No new notifications</p></div>';
                return;
            }
            
            // Updated to match dashboard style structure
            notificationList.innerHTML = notifications.map(notif => {
                let iconClass = 'document';
                let iconName = 'fa-file-upload';
                let url = 'validation.php';
                
                if (notif.type === 'document_upload') {
                    iconClass = 'document';
                    iconName = 'fa-file-upload';
                    url = 'validation.php';
                } else if (notif.type === 'user_registration') {
                    iconClass = 'user';
                    iconName = 'fa-user-plus';
                    url = 'user_management.php#pending';
                }
                
                return `
                    <a href="${url}" class="notification-item ${notif.is_read ? '' : 'unread'}" onclick="markNotificationRead(${notif.id})">
                        <div class="notif-icon ${iconClass}">
                            <i class="fas ${iconName}"></i>
                        </div>
                        <div class="notification-content">
                            <div class="notification-title">${escapeHtml(notif.title)}</div>
                            <div class="notification-message">${escapeHtml(notif.message)}</div>
                            <div class="notification-time">${formatTimeAgo(notif.created_at)}</div>
                        </div>
                    </a>
                `;
            }).join('');
        }
        
        function loadStaffNotifications() {
            const notificationList = document.getElementById('notificationList');
            
            fetch('../api/notifications.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayStaffNotifications(data.notifications);
                        const unreadCount = data.notifications.filter(n => !n.is_read).length;
                        updateNotificationBadge(unreadCount);
                    }
                })
                .catch(error => {
                    console.error('Error loading notifications:', error);
                    notificationList.innerHTML = '<div class="notification-empty"><i class="fas fa-exclamation-circle"></i><p>Error loading notifications</p></div>';
                });
        }
        
        function displayStaffNotifications(notifications) {
            const notificationList = document.getElementById('notificationList');
            
            if (!notifications || notifications.length === 0) {
                notificationList.innerHTML = '<div class="notification-empty"><i class="fas fa-bell-slash"></i><p>No new notifications</p></div>';
                return;
            }
            
            // Updated to match dashboard style structure
            notificationList.innerHTML = notifications.map(notif => {
                let iconClass = 'document';
                let iconName = 'fa-file-alt';
                let url = 'assigned_areas.php';
                
                if (notif.type === 'document_approved' || (notif.title && notif.title.toLowerCase().includes('approved'))) {
                    iconClass = 'approved';
                    iconName = 'fa-check-circle';
                    url = 'assigned_areas.php';
                } else if (notif.type === 'document_rejected' || (notif.title && notif.title.toLowerCase().includes('rejected'))) {
                    iconClass = 'rejected';
                    iconName = 'fa-times-circle';
                    url = 'assigned_areas.php';
                }
                
                // If notification has document_id, link directly
                if (notif.document_id) {
                    url = `assigned_areas.php?doc=${notif.document_id}`;
                }
                
                return `
                    <a href="${url}" class="notification-item ${notif.is_read == 0 ? 'unread' : ''}" onclick="markNotificationRead(${notif.id})">
                        <div class="notif-icon ${iconClass}">
                            <i class="fas ${iconName}"></i>
                        </div>
                        <div class="notification-content">
                            <div class="notification-title">${escapeHtml(notif.title || 'Notification')}</div>
                            <div class="notification-message">${escapeHtml(notif.message || '')}</div>
                            <div class="notification-time">${formatTimeAgo(notif.created_at)}</div>
                        </div>
                    </a>
                `;
            }).join('');
        }
        
        function markNotificationRead(notificationId) {
            fetch('../api/notifications.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'mark_single_read', id: notificationId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadNotifications();
                }
            });
        }
        
        function markAllAsRead(event) {
            event.preventDefault();
            event.stopPropagation();
            
            fetch('../api/notifications.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'mark_read' })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadNotifications();
                    updateNotificationBadge(0);
                }
            });
        }
        
        function updateNotificationBadge(count) {
            const badge = document.getElementById('notifBadge');
            if (badge) {
                if (count > 0) {
                    badge.textContent = count;
                    badge.style.display = 'flex'; // Changed to flex to match CSS
                } else {
                    badge.style.display = 'none';
                }
            }
        }
        
        // ==================== PROFILE MENU ====================
        // Defined globally so HTML onclick works
        window.toggleProfileMenu = function() {
            const menu = document.getElementById('profileMenu');
            menu.classList.toggle('show');
        };
        
        // Close profile menu when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.profile-dropdown')) {
                const menu = document.getElementById('profileMenu');
                if (menu) menu.classList.remove('show');
            }
        });
        
        // ==================== UTILITY FUNCTIONS ====================
        function formatTimeAgo(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            const now = new Date();
            const seconds = Math.floor((now - date) / 1000);
            
            if (seconds < 60) return 'Just now';
            if (seconds < 3600) return Math.floor(seconds / 60) + 'm ago';
            if (seconds < 3600) return Math.floor(seconds / 3600) + 'h ago';
            if (seconds < 604800) return Math.floor(seconds / 86400) + 'd ago';
            return date.toLocaleDateString();
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
            return String(text).replace(/[&<>"']/g, m => map[m]);
        }
        
        // Expose functions globally for HTML access
        window.markNotificationRead = markNotificationRead;
        window.markAllAsRead = markAllAsRead;
        
    })(); // End IIFE
    </script>