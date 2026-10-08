<?php
require_once dirname(__DIR__) . '/config/config.php';
requireAdmin();

$page_title = 'Archives';

// Ensure DB connection
$database_instance = getDB(); 
if ($database_instance instanceof PDO) {
    $db = $database_instance;
} elseif (method_exists($database_instance, 'getConnection')) {
    $db = $database_instance->getConnection();
} else {
    $database = new Database();
    $db = $database->getConnection();
}

$success = '';
$error = '';

// --- USER & NOTIFICATION LOGIC ---
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

// Get pending counts
$pending_documents = 0;
$pending_users = 0;
$total_notifications = 0;

if ($_SESSION['role'] === 'admin') {
    $stmt = $db->query("SELECT COUNT(*) FROM documents WHERE status = 'pending'");
    $pending_documents = $stmt->fetchColumn();
    
    $stmt = $db->query("SELECT COUNT(*) FROM users WHERE status = 'pending'");
    $pending_users = $stmt->fetchColumn();

    $total_notifications = $pending_documents + $pending_users;
}

// Get current user info
$stmt = $db->prepare("SELECT full_name, email FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$current_user = $stmt->fetch(PDO::FETCH_ASSOC);
$user_initials = getUserInitials($current_user['full_name'] ?? $_SESSION['full_name'] ?? 'User');
$user_full_name = $current_user['full_name'] ?? $_SESSION['full_name'] ?? 'User';

// --- DATA RETRIEVAL ---
$query = "SELECT p.*, 
                 (SELECT COUNT(*) FROM levels WHERE program_id = p.id) as levels_count,
                 (SELECT COUNT(*) FROM documents d 
                  JOIN indicators i ON d.indicator_id = i.id 
                  JOIN parameters pr ON i.parameter_id = pr.id 
                  JOIN areas a ON pr.area_id = a.id 
                  JOIN levels l ON a.level_id = l.id 
                  WHERE l.program_id = p.id) as documents_count
          FROM programs p 
          WHERE p.status = 'archived' 
          ORDER BY p.updated_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$archived_programs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_archived = count($archived_programs);
$total_docs_archived = array_sum(array_column($archived_programs, 'documents_count'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            /* Standardized Blue Palette */
            --color-primary-600: #2563eb;
            --color-primary-800: #1e40af;
            --primary-color: #3b82f6; 
            --primary-gradient: linear-gradient(135deg, var(--color-primary-600) 0%, var(--color-primary-800) 100%);
            
            /* Sidebar Variables */
            --sidebar-width: 260px;
            --sidebar-width-collapsed: 80px;
            
            /* UI Colors */
            --text-secondary: #64748b;
            --bg-card: #ffffff;
            --border-card: #e2e8f0;
        }
        
        * { font-family: 'Inter', sans-serif; }
        body { 
            background-color: #f5f7fb; 
            min-height: 100vh;
            transition: margin-left 0.3s ease; 
        }
        
        /* --- Sidebar Styles --- */
        .sidebar { 
            position: fixed; top: 0; left: 0; width: var(--sidebar-width); height: 100vh; 
            background: var(--primary-gradient); padding: 0; z-index: 1000; 
            overflow-y: inherit; overflow-x: hidden; 
            box-shadow: 4px 0 20px rgba(0,0,0,0.1); 
            transition: width 0.3s ease;
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
        
        /* Sidebar Elements */
        .sidebar-brand { padding: 25px 20px; border-bottom: 1px solid rgba(255,255,255,0.1); display: flex; align-items: center; gap: 15px; white-space: nowrap; transition: all 0.3s ease; }
        
        .sidebar-logo-img { width: 45px; height: 45px; border-radius: 12px; object-fit: cover; background: white; box-shadow: 0 4px 6px rgba(0,0,0,0.1); flex-shrink: 0; }
        
        .sidebar-brand-text { color: white; font-size: 20px; font-weight: 800; text-decoration: none; letter-spacing: 0.5px; transition: opacity 0.2s ease; }
        
        .sidebar-brand-icon { width: 45px; height: 45px; background: rgba(255,255,255,0.2); border-radius: 12px; display: flex; align-items: center; justify-content: center; }
        .sidebar-brand-icon i { font-size: 22px; color: white; }

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
        .sidebar-menu a.active { background: rgba(255,255,255,0.25); color: white; font-weight: 600; border-left: 4px solid white; padding-left: 14px; border-top-left-radius: 4px; border-bottom-left-radius: 4px; }
        .sidebar-menu a i { width: 22px; margin-right: 12px; font-size: 15px; flex-shrink: 0; text-align: center; transition: margin 0.3s ease; }
        
        .nav-text { transition: opacity 0.2s ease; }
        .sidebar-badge { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); background: #ff4757; color: white; font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 20px; min-width: 22px; text-align: center; }

        /* Main Content & Nav */
        .main-content { margin-left: var(--sidebar-width); min-height: 100vh; transition: margin-left 0.3s ease; }
        
        .top-nav { 
            background: white; padding: 15px 30px; display: flex; justify-content: space-between; 
            align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); position: sticky; 
            top: 0; z-index: 100; height: 80px;
        }
        
        .top-nav-left { display: flex; align-items: center; gap: 15px; }
        .top-nav-right { display: flex; align-items: center; gap: 20px; }

        /* Hamburger Toggle Button */
        .btn-sidebar-toggle {
            background: transparent; border: none; font-size: 20px; color: #6c757d;
            cursor: pointer; padding: 8px; border-radius: 8px; transition: all 0.2s ease;
            display: flex; align-items: center; justify-content: center;
        }
        .btn-sidebar-toggle:hover { background-color: #f3f4f6; color: var(--color-primary-600); }

        .page-title { font-size: 24px; font-weight: 700; color: #1e293b; margin: 0; }

        /* Notification Styles */
        .notification-bell { position: relative; cursor: pointer; padding: 12px; border-radius: 12px; transition: all 0.3s ease; background: #f8f9fa; display: flex; align-items: center; justify-content: center; width: 45px; height: 45px; }
        .notification-bell:hover { background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(37, 99, 235, 0.1) 100%); }
        .notification-bell i { font-size: 20px; color: #666; }
        .notification-badge { position: absolute; top: -2px; right: -2px; background: linear-gradient(135deg, #ff4757 0%, #ff6b81 100%); color: white; font-size: 10px; font-weight: 700; padding: 0; border-radius: 50%; min-width: 18px; height: 18px; text-align: center; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 8px rgba(255, 71, 87, 0.4); border: 2px solid white; }
        .notification-dropdown { position: absolute; top: calc(100% + 15px); right: 0; width: 380px; background: white; border-radius: 16px; box-shadow: 0 15px 50px rgba(0,0,0,0.15); z-index: 1000; display: none; border: 1px solid #e9ecef; overflow: hidden; }
        .notification-dropdown.show { display: block; }
        .notification-header { padding: 18px 20px; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center; background: linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, rgba(37, 99, 235, 0.05) 100%); }
        .notification-header h6 { margin: 0; font-weight: 700; font-size: 16px; color: #333; }
        .notification-list { max-height: 380px; overflow-y: auto; }
        .notification-list::-webkit-scrollbar { width: 6px; }
        .notification-list::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 3px; }
        
        .notification-item { display: flex; align-items: flex-start; padding: 16px 20px; border-bottom: 1px solid #f0f0f0; cursor: pointer; transition: all 0.2s ease; text-decoration: none; color: inherit; }
        .notification-item:hover { background: #f8f9fa; }
        .notification-item.unread { background: linear-gradient(135deg, rgba(59, 130, 246, 0.08) 0%, rgba(37, 99, 235, 0.08) 100%); border-left: 3px solid #3b82f6; }
        .notification-item .notif-icon { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-right: 14px; flex-shrink: 0; color: white; }
        .notif-icon.document { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); }
        .notif-icon.user { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
        .notification-content { flex: 1; min-width: 0; }
        .notification-title { font-weight: 600; font-size: 14px; color: #333; margin-bottom: 4px; }
        .notification-message { font-size: 13px; color: #6c757d; margin-bottom: 6px; line-height: 1.4; }
        .notification-time { font-size: 11px; color: #adb5bd; }
        .notification-empty { padding: 40px 20px; text-align: center; color: #6c757d; }
        .mark-all-read { font-size: 12px; color: #3b82f6; cursor: pointer; font-weight: 600; }

        /* Profile Styles */
        .profile-dropdown { position: relative; }
        .profile-toggle { display: flex; align-items: center; gap: 12px; cursor: pointer; padding: 8px 15px; border-radius: 12px; transition: background 0.3s ease; }
        .profile-toggle:hover { background: #f0f0f0; }
        
        .topbar-avatar { 
            width: 45px; height: 45px; border-radius: 12px; 
            background: var(--primary-gradient); 
            display: flex; align-items: center; justify-content: center; 
            font-size: 16px; font-weight: 700; color: white; 
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
        }
        
        .profile-info { text-align: left; }
        .profile-info .name { font-weight: 700; font-size: 15px; color: #1e293b; }
        .profile-info .role { font-size: 13px; color: #64748b; font-weight: 500; }
        
        .profile-menu { position: absolute; top: calc(100% + 10px); right: 0; width: 200px; background: white; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.15); z-index: 1000; display: none; overflow: hidden; border: 1px solid #e2e8f0; }
        .profile-menu.show { display: block; }
        .profile-menu a { display: flex; align-items: center; padding: 12px 18px; color: #333; text-decoration: none; transition: background 0.2s ease; }
        .profile-menu a:hover { background: #f8f9fa; }
        .profile-menu a i { width: 20px; margin-right: 10px; color: #6c757d; }
        .profile-menu .logout { border-top: 1px solid #f0f0f0; color: #dc3545; }
        .profile-menu .logout i { color: #dc3545; }

        /* Content Area */
        .content-area { padding: 30px; max-width: 1400px; margin: 0 auto; }

        /* Stats Cards */
        .archive-stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .mini-stat-card { background: white; padding: 20px; border-radius: 12px; border: 1px solid var(--border-card); display: flex; align-items: center; gap: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .mini-stat-icon { width: 48px; height: 48px; border-radius: 10px; background: #f1f5f9; color: #64748b; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .mini-stat-info h4 { font-size: 24px; font-weight: 700; color: #1e293b; margin: 0; }
        .mini-stat-info span { font-size: 13px; color: #64748b; font-weight: 500; }

        /* Search Bar */
        .filter-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; gap: 20px; }
        .search-wrapper { position: relative; flex: 1; max-width: 400px; }
        .search-wrapper input { width: 100%; padding: 12px 15px 12px 45px; border-radius: 10px; border: 1px solid #cbd5e1; font-size: 14px; transition: all 0.2s; }
        .search-wrapper input:focus { border-color: var(--primary-color); outline: none; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
        .search-wrapper i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #94a3b8; }

        /* Archive Grid */
        .archive-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 24px; }
        
        .program-card { 
            background: white; border: 1px solid var(--border-card); border-radius: 16px; 
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); position: relative; overflow: hidden; display: flex; flex-direction: column; 
        }
        .program-card:hover { transform: translateY(-4px); box-shadow: 0 12px 24px -10px rgba(0, 0, 0, 0.08); border-color: #cbd5e1; }
        .card-status-strip { height: 6px; background: #cbd5e1; width: 100%; }
        .card-content { padding: 24px; flex: 1; display: flex; flex-direction: column; }
        .program-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
        .program-title { font-size: 16px; font-weight: 700; color: #334155; line-height: 1.4; margin-right: 10px; }
        .program-code { font-size: 11px; font-weight: 600; background: #f1f5f9; color: #64748b; padding: 4px 8px; border-radius: 6px; white-space: nowrap; letter-spacing: 0.5px; }
        .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 24px; background: #f8fafc; padding: 12px; border-radius: 8px; border: 1px solid #f1f5f9; }
        .meta-item { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #64748b; }
        .meta-item i { font-size: 14px; width: 16px; text-align: center; color: #94a3b8; }
        .meta-item span { font-weight: 600; color: #475569; }
        .archive-date { font-size: 12px; color: #94a3b8; margin-top: auto; margin-bottom: 20px; display: flex; align-items: center; gap: 6px; }
        
        .card-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: auto; }
        .btn-action { padding: 10px; border-radius: 8px; font-size: 13px; font-weight: 500; display: flex; align-items: center; justify-content: center; gap: 8px; cursor: pointer; transition: all 0.2s; border: none; }
        .btn-restore { background: #eff6ff; color: #2563eb; border: 1px solid transparent; }
        .btn-restore:hover { background: #2563eb; color: white; }
        .btn-delete { background: transparent; color: #64748b; border: 1px solid #e2e8f0; }
        .btn-delete:hover { background: #fef2f2; color: #ef4444; border-color: #fecaca; }

        .empty-state { text-align: center; padding: 80px 20px; background: white; border-radius: 16px; border: 2px dashed #e2e8f0; grid-column: 1 / -1; }
        .empty-icon-circle { width: 80px; height: 80px; background: #f1f5f9; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; }
        .empty-state i { font-size: 32px; color: #94a3b8; }
        .empty-state h3 { font-size: 18px; font-weight: 600; color: #334155; margin-bottom: 8px; }
        .empty-state p { color: #64748b; font-size: 14px; max-width: 400px; margin: 0 auto; }

        @media (max-width: 768px) {
            .sidebar { width: 0; overflow: hidden; }
            .main-content { margin-left: 0; }
            .top-nav { padding: 15px; }
            .top-nav-right { gap: 10px; }
            .profile-info { display: none; }
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
        
        <div class="sidebar-menu">
            <a href="dashboard.php">
                <i class="fas fa-home"></i><span class="nav-text">Dashboard</span>
            </a>
            <a href="programs.php">
                <i class="fas fa-folder"></i><span class="nav-text">Programs</span>
            </a>
            <a href="validation.php">
                <i class="fas fa-check-circle"></i><span class="nav-text">Validation</span>
                <?php if ($pending_documents > 0): ?>
                    <span class="sidebar-badge"><?php echo $pending_documents; ?></span>
                <?php endif; ?>
            </a>
            <a href="global_folders.php">
                <i class="fas fa-folder-tree"></i><span class="nav-text">Folders</span>
            </a>
            <a href="user_management.php">
                <i class="fas fa-users"></i><span class="nav-text">Users</span>
                <?php if ($pending_users > 0): ?>
                    <span class="sidebar-badge"><?php echo $pending_users; ?></span>
                <?php endif; ?>
            </a>
            <a href="archives.php" class="active">
                <i class="fas fa-archive"></i><span class="nav-text">Archives</span>
            </a>
            <a href="reports.php">
                <i class="fas fa-chart-bar"></i><span class="nav-text">Reports</span>
            </a>
            <a href="profile.php">
                <i class="fas fa-user"></i><span class="nav-text">Profile</span>
            </a>
            <hr style="border-color: rgba(255,255,255,0.2); margin: 15px 0;">
            <a href="../auth/logout.php">
                <i class="fas fa-sign-out-alt"></i><span class="nav-text">Logout</span>
            </a>
        </div>
    </nav>
    
    <div class="main-content">
        <div class="top-nav">
            <div class="top-nav-left">
                <button id="sidebarToggle" class="btn-sidebar-toggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="page-title">Archives</h1>
            </div>
            
            <div class="top-nav-right">
                <div class="notification-bell" id="notificationBell">
                    <i class="fas fa-bell"></i>
                    <?php if ($total_notifications > 0): ?>
                        <span class="notification-badge" id="notifBadge"><?php echo $total_notifications; ?></span>
                    <?php endif; ?>
                    
                    <div class="notification-dropdown" id="notificationDropdown">
                        <div class="notification-header">
                            <h6><i class="fas fa-bell me-2"></i>Notifications</h6>
                            <span class="mark-all-read" onclick="markAllAsRead(event)">Mark all as read</span>
                        </div>
                        <div class="notification-list" id="notificationList">
                            <div class="notification-empty">
                                <i class="fas fa-bell-slash"></i><p>Loading...</p>
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
                            <div class="name"><?php echo htmlspecialchars($user_full_name); ?></div>
                            <div class="role"><?php echo ucfirst($_SESSION['role']); ?></div>
                        </div>
                        <i class="fas fa-chevron-down" style="color: #64748b; font-size: 14px;"></i>
                    </div>
                    
                    <div class="profile-menu" id="profileMenu">
                        <a href="profile.php"><i class="fas fa-user"></i>My Profile</a>
                        <a href="reports.php"><i class="fas fa-chart-bar"></i>Reports</a>
                        <a href="../auth/logout.php" class="logout"><i class="fas fa-sign-out-alt"></i>Logout</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-area">
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="archive-stats-row">
                <div class="mini-stat-card">
                    <div class="mini-stat-icon"><i class="fas fa-archive"></i></div>
                    <div class="mini-stat-info">
                        <h4><?php echo $total_archived; ?></h4>
                        <span>Archived Programs</span>
                    </div>
                </div>
                <div class="mini-stat-card">
                    <div class="mini-stat-icon"><i class="fas fa-file-alt"></i></div>
                    <div class="mini-stat-info">
                        <h4><?php echo $total_docs_archived; ?></h4>
                        <span>Documents Stored</span>
                    </div>
                </div>
            </div>

            <div class="filter-bar">
                <div class="search-wrapper">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search archived programs by name or code..." onkeyup="searchPrograms()">
                </div>
                <a href="programs.php" class="btn btn-outline-secondary" style="border-radius: 10px; padding: 10px 20px;">
                    <i class="fas fa-arrow-left me-2"></i>Active Programs
                </a>
            </div>

            <div class="archive-grid" id="programsContainer">
                <?php if (empty($archived_programs)): ?>
                    <div class="empty-state">
                        <div class="empty-icon-circle">
                            <i class="fas fa-box-open"></i>
                        </div>
                        <h3>The archives are empty</h3>
                        <p>No programs have been archived yet. Go to the Programs page if you need to archive old curriculums.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($archived_programs as $program): ?>
                        <div class="program-card" id="program-<?php echo $program['id']; ?>">
                            <div class="card-status-strip"></div>
                            <div class="card-content">
                                <div class="program-header">
                                    <div class="program-title"><?php echo htmlspecialchars($program['name']); ?></div>
                                    <span class="program-code"><?php echo htmlspecialchars($program['code']); ?></span>
                                </div>
                                
                                <div class="meta-grid">
                                    <div class="meta-item">
                                        <i class="fas fa-layer-group"></i>
                                        <div><span><?php echo $program['levels_count']; ?></span> Levels</div>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-file-alt"></i>
                                        <div><span><?php echo $program['documents_count']; ?></span> Docs</div>
                                    </div>
                                </div>
                                
                                <div class="archive-date">
                                    <i class="far fa-clock"></i>
                                    Archived on <?php echo date('M d, Y', strtotime($program['updated_at'])); ?>
                                </div>
                                
                                <div class="card-actions">
                                    <button class="btn-action btn-restore" onclick="restoreProgram(<?php echo $program['id']; ?>)">
                                        <i class="fas fa-undo"></i> Restore
                                    </button>
                                    <button class="btn-action btn-delete" onclick="deleteProgram(<?php echo $program['id']; ?>)">
                                        <i class="fas fa-trash-alt"></i> Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // --- SIDEBAR TOGGLE ---
        document.addEventListener('DOMContentLoaded', function() {
            const toggleBtn = document.getElementById('sidebarToggle');
            if (toggleBtn) {
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
            
            initNotifications();
            loadNotifications();
        });

        // --- SEARCH FUNCTIONALITY ---
        function searchPrograms() {
            const input = document.getElementById('searchInput').value.toUpperCase();
            const cards = document.querySelectorAll('.program-card');
            
            cards.forEach(card => {
                const title = card.querySelector('.program-title').textContent.toUpperCase();
                const code = card.querySelector('.program-code').textContent.toUpperCase();
                
                if (title.includes(input) || code.includes(input)) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        // --- PROFILE & NOTIFICATIONS ---
        function toggleProfileMenu() {
            document.getElementById('profileMenu').classList.toggle('show');
        }

        // Close menus when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.profile-dropdown')) {
                document.getElementById('profileMenu').classList.remove('show');
            }
            if (!e.target.closest('.notification-bell')) {
                document.getElementById('notificationDropdown').classList.remove('show');
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

        // --- SWEETALERT ACTIONS ---
        function restoreProgram(programId) {
            Swal.fire({
                title: 'Restore Program?',
                text: "This program will be moved back to the active list.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3b82f6',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, Restore It',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Restoring...',
                        text: 'Please wait.',
                        allowOutsideClick: false,
                        didOpen: () => { Swal.showLoading(); }
                    });

                    fetch('../api/restore_program.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ program_id: programId })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Restored!', 'The program has been restored.', 'success')
                            .then(() => {
                                // Fade out and remove
                                const card = document.getElementById('program-' + programId);
                                card.style.transition = 'opacity 0.3s';
                                card.style.opacity = '0';
                                setTimeout(() => {
                                    card.remove();
                                    if(document.querySelectorAll('.program-card').length === 0) location.reload();
                                }, 300);
                            });
                        } else {
                            Swal.fire('Error', data.message, 'error');
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        Swal.fire('Error', 'Failed to process request.', 'error');
                    });
                }
            });
        }

        function deleteProgram(programId) {
            Swal.fire({
                title: 'Are you sure?',
                text: "This will permanently delete the program and ALL related documents. This cannot be undone!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, Delete Permanently'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Deleting...',
                        text: 'Please wait.',
                        allowOutsideClick: false,
                        didOpen: () => { Swal.showLoading(); }
                    });

                    fetch('../api/delete_program.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ program_id: programId })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Deleted!', 'The program has been permanently deleted.', 'success')
                            .then(() => {
                                const card = document.getElementById('program-' + programId);
                                card.style.transition = 'opacity 0.3s';
                                card.style.opacity = '0';
                                setTimeout(() => {
                                    card.remove();
                                    if(document.querySelectorAll('.program-card').length === 0) location.reload();
                                }, 300);
                            });
                        } else {
                            Swal.fire('Error', data.message, 'error');
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        Swal.fire('Error', 'Failed to delete program.', 'error');
                    });
                }
            });
        }
    </script>
</body>
</html>