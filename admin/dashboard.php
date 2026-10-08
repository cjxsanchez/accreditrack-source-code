<?php
/**
 * Admin Dashboard - Added Hamburger Menu & Mini Sidebar
 */
require_once dirname(__DIR__) . '/config/config.php';
requireAdmin();

$page_title = 'Dashboard';

// --- FIX: Get the PDO Connection Object ---
$database_instance = getDB(); 
if ($database_instance instanceof PDO) {
    $db = $database_instance;
} elseif (method_exists($database_instance, 'getConnection')) {
    $db = $database_instance->getConnection();
} else {
    // Fallback standard instantiation
    $database = new Database();
    $db = $database->getConnection();
}

// --- 1. Helper Functions ---
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

// --- 2. Data Retrieval ---
// User Info
$query = "SELECT full_name, email FROM users WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$current_user = $stmt->fetch(PDO::FETCH_ASSOC);
$user_initials = getUserInitials($current_user['full_name'] ?? $_SESSION['username'] ?? 'Admin');
$admin_name = $current_user['full_name'] ?? $_SESSION['username'] ?? 'Admin';

// Statistics Counts
$stats = [
    'faculty' => 0,
    'programs' => 0,
    'files' => 0,
    'admins' => 0
];

// Active Faculty
$stmt = $db->query("SELECT COUNT(*) FROM users WHERE role = 'staff' AND status = 'active'");
$stats['faculty'] = $stmt->fetchColumn();

// Active Programs
$stmt = $db->query("SELECT COUNT(*) FROM programs WHERE status = 'active'");
$stats['programs'] = $stmt->fetchColumn();

// Approved Documents
$stmt = $db->query("SELECT COUNT(*) FROM documents WHERE status = 'approved'");
$stats['files'] = $stmt->fetchColumn();

// Admins
$stmt = $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
$stats['admins'] = $stmt->fetchColumn();

// --- 3. NOTIFICATION LOGIC ---

// A. Calculate Counts
$stmt = $db->query("SELECT COUNT(*) FROM documents WHERE status = 'pending'");
$pending_documents = $stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM users WHERE status = 'pending'");
$pending_users = $stmt->fetchColumn();

// B. Fetch Detailed Data
$all_notifications = [];

// Fetch Detailed Pending Users
$stmt = $db->query("SELECT id, full_name, email, created_at FROM users WHERE status = 'pending' ORDER BY created_at DESC LIMIT 5");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $all_notifications[] = [
        'type' => 'user',
        'id' => $row['id'],
        'title' => 'New Faculty Registration',
        'message' => htmlspecialchars($row['full_name']) . ' (' . htmlspecialchars($row['email']) . ') is waiting for approval',
        'time' => $row['created_at'],
        'url' => 'user_management.php#pending'
    ];
}

// Fetch Detailed Pending Documents
$docQuery = "SELECT d.id, d.title, d.created_at, u.full_name, p.name as program_name
             FROM documents d 
             JOIN users u ON d.uploaded_by = u.id 
             LEFT JOIN indicators i ON d.indicator_id = i.id
             LEFT JOIN parameters pm ON i.parameter_id = pm.id
             LEFT JOIN areas a ON pm.area_id = a.id
             LEFT JOIN levels l ON a.level_id = l.id
             LEFT JOIN programs p ON l.program_id = p.id
             WHERE d.status = 'pending' ORDER BY d.created_at DESC LIMIT 5";
$stmt = $db->query($docQuery);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $progName = $row['program_name'] ?? 'System';
    $all_notifications[] = [
        'type' => 'document',
        'id' => $row['id'],
        'title' => 'New Document Upload',
        'message' => htmlspecialchars($row['full_name']) . ' uploaded "' . htmlspecialchars($row['title']) . '" for ' . htmlspecialchars($progName),
        'time' => $row['created_at'],
        'url' => 'validation.php'
    ];
}

// Sort notifications by time
usort($all_notifications, function($a, $b) {
    return strtotime($b['time']) - strtotime($a['time']);
});

$total_notifications = $pending_documents + $pending_users;

// Active Programs List (For Dropdown)
$stmt = $db->query("SELECT id, name FROM programs WHERE status = 'active' ORDER BY name");
$programs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$search_api = '../api/search.php';
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --color-primary-600: #2563eb;
            --color-primary-800: #1e40af;
            --primary-gradient: linear-gradient(135deg, var(--color-primary-600) 0%, var(--color-primary-800) 100%);
            
            /* Sidebar Variables */
            --sidebar-width: 260px;
            --sidebar-width-collapsed: 80px;
        }
        
        * { font-family: 'Inter', sans-serif; box-sizing: border-box; }
        
        body { 
            background-color: #f5f7fb; 
            min-height: 100vh; 
            margin: 0; 
            padding: 0;
            transition: margin-left 0.3s ease; /* Smooth content shift */
        }
        
        /* --- SIDEBAR STYLES --- */
        .sidebar { 
            position: fixed; 
            top: 0; 
            left: 0; 
            width: var(--sidebar-width); 
            height: 100vh; 
            background: var(--primary-gradient); 
            z-index: 1000; 
            overflow-y: inherit;
            overflow-x: hidden;
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
        
        /* Sidebar Brand */
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

        .sidebar-brand-icon { width: 45px; height: 45px; background: rgba(255,255,255,0.2); border-radius: 12px; display: flex; align-items: center; justify-content: center; }
        .sidebar-brand-icon i { font-size: 22px; color: white; }
        
        .sidebar-brand-text { 
            color: white; font-size: 19px; font-weight: 700; text-decoration: none; 
            transition: opacity 0.2s ease;
        }
        
        /* Sidebar Menu */
        .sidebar-menu { padding: 20px 15px; overflow-y: auto; height: calc(100vh - 100px); }
        .sidebar-menu::-webkit-scrollbar { width: 4px; }
        .sidebar-menu::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 2px; }

        .sidebar-menu a { 
            display: flex; align-items: center; padding: 12px 18px; 
            font-size: 14px; font-weight: 500; color: rgba(255,255,255,0.85); 
            text-decoration: none; border-radius: 12px; margin-bottom: 5px; 
            transition: all 0.3s ease; position: relative; white-space: nowrap; overflow: hidden;
        }
        .sidebar-menu a:hover { background: rgba(255,255,255,0.15); color: white; }
        .sidebar-menu a.active { background: rgba(255,255,255,0.25); color: white; font-weight: 600; border-left: 4px solid white; padding-left: 14px; }
        
        .sidebar-menu a i { width: 22px; margin-right: 12px; font-size: 15px; flex-shrink: 0; text-align: center; transition: margin 0.3s ease; }
        
        .nav-text { transition: opacity 0.2s ease; }

        .sidebar-badge { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); background: #ff4757; color: white; font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 20px; }
        
        /* Main Content */
        .main-content { margin-left: var(--sidebar-width); min-height: 100vh; transition: margin-left 0.3s ease; }
        
        /* Top Nav */
        .top-nav { background: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); position: sticky; top: 0; z-index: 100; height: 80px; }
        
        .top-nav-left { display: flex; align-items: center; gap: 15px; }
        .page-title { font-size: 20px; font-weight: 700; color: #333; margin: 0; }
        .top-nav-right { display: flex; align-items: center; gap: 20px; }

        /* Hamburger Toggle Button */
        .btn-sidebar-toggle {
            background: transparent; border: none; font-size: 20px; color: #6c757d;
            cursor: pointer; padding: 8px; border-radius: 8px; transition: all 0.2s ease;
            display: flex; align-items: center; justify-content: center;
        }
        .btn-sidebar-toggle:hover { background-color: #f3f4f6; color: var(--color-primary-600); }

        /* Search Styles */
        .search-container { position: relative; }
        .search-container input {
            width: 250px;
            padding: 10px 15px 10px 42px; /* Left padding for icon */
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
            position: absolute; left: 15px; top: 50%; transform: translateY(-50%);
            color: #9ca3af; font-size: 15px; pointer-events: none;
        }

        .search-results { position: absolute; top: 100%; left: 0; right: 0; background: white; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.15); z-index: 1001; margin-top: 8px; max-height: 400px; overflow-y: auto; display: none; border: 1px solid #e9ecef; }
        .search-results.show { display: block; }
        
        /* Notifications - Screenshot Style */
        .notification-bell { position: relative; cursor: pointer; padding: 12px; border-radius: 12px; transition: all 0.3s ease; background: #f8f9fa; display: flex; align-items: center; justify-content: center; width: 45px; height: 45px; }
        .notification-bell:hover { background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(37, 99, 235, 0.1) 100%); }
        .notification-bell i { font-size: 20px; color: #666; }
        
        .notification-badge { 
            position: absolute; top: -2px; right: -2px; 
            background-color: #ff4d4f; color: white; font-size: 10px; font-weight: 700; 
            padding: 0; border-radius: 50%; min-width: 18px; height: 18px;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 2px 5px rgba(255, 77, 79, 0.4); border: 2px solid white;
        }

        .notification-dropdown { 
            position: absolute; top: calc(100% + 15px); right: -10px; width: 380px; 
            background: white; border-radius: 12px; box-shadow: 0 5px 30px rgba(0,0,0,0.15); 
            z-index: 1000; display: none; border: none; overflow: hidden; 
        }
        .notification-dropdown.show { display: block; }
        
        .notification-header { 
            padding: 16px 20px; border-bottom: 1px solid #f0f0f0; background: white;
            display: flex; justify-content: space-between; align-items: center;
        }
        .notification-header h6 { margin: 0; font-weight: 700; font-size: 16px; color: #333; }
        .mark-all-read { font-size: 13px; color: #3b82f6; cursor: pointer; font-weight: 500; text-decoration: none; }
        .mark-all-read:hover { text-decoration: underline; }

        .notification-list { max-height: 400px; overflow-y: auto; padding: 0; }
        .notification-list::-webkit-scrollbar { width: 6px; }
        .notification-list::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 3px; }

        .notification-item { 
            display: flex; align-items: flex-start; padding: 16px 20px; 
            border-bottom: 1px solid #f5f5f5; cursor: pointer; 
            transition: background 0.2s ease; text-decoration: none; color: inherit; background: white;
        }
        .notification-item:last-child { border-bottom: none; }
        .notification-item:hover { background: #f9fafb; }
        
        .notification-item .notif-icon { 
            width: 48px; height: 48px; border-radius: 12px; 
            display: flex; align-items: center; justify-content: center; 
            margin-right: 16px; flex-shrink: 0; font-size: 20px;
        }
        .notification-item .notif-icon.document { background-color: #3b82f6; color: white; }
        .notification-item .notif-icon.user { background-color: #10b981; color: white; }
        
        .notification-content { flex: 1; min-width: 0; }
        .notification-title { font-weight: 600; font-size: 14px; color: #1f2937; margin-bottom: 4px; line-height: 1.3; }
        .notification-message { font-size: 13px; color: #6b7280; margin-bottom: 6px; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .notification-time { font-size: 11px; color: #9ca3af; font-weight: 500; }
        .notification-empty { padding: 40px 20px; text-align: center; color: #6c757d; }

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

        /* Welcome Banner */
        .welcome-banner { background: var(--primary-gradient); padding: 30px; border-radius: 16px; margin-bottom: 32px; position: relative; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(37, 99, 235, 0.4); color: white; display: flex; justify-content: space-between; align-items: center; }
        .welcome-text h2 { margin: 0 0 8px 0; font-weight: 700; font-size: 26px; }
        .welcome-text p { margin: 0; opacity: 0.9; font-size: 15px; }
        .welcome-date { background: rgba(255, 255, 255, 0.2); padding: 8px 16px; border-radius: 8px; backdrop-filter: blur(5px); font-weight: 500; font-size: 14px; }
        .welcome-decoration { position: absolute; right: -20px; bottom: -20px; opacity: 0.1; font-size: 150px; transform: rotate(-15deg); }

        /* Stats & Charts */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px; margin-bottom: 32px; }
        .stat-card { background: white; padding: 24px; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); display: flex; align-items: center; gap: 20px; transition: all 0.25s ease; border: 1px solid #e2e8f0; height: 100%; }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); border-color: #bfdbfe; }
        .stat-icon { width: 64px; height: 64px; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; font-size: 20px; flex-shrink: 0; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .stat-icon.blue { background: var(--primary-gradient); }
        .stat-icon.green { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
        .stat-icon.cyan { background: linear-gradient(135deg, #60a5fa 0%, #4ade80 100%); }
        .stat-icon.yellow { background: linear-gradient(135deg, #eab308 0%, #a16207 100%); }
        .stat-content h3 { font-size: 24px; font-weight: 800; margin: 0; color: #0f172a; line-height: 1; }
        .stat-content p { margin: 8px 0 0 0; color: #475569; font-size: 14px; font-weight: 500; }
        
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); margin-bottom: 32px; border: 1px solid #e2e8f0; overflow: hidden; background: white; }
        .card-header { background: white; border: none; padding: 20px 24px; border-bottom: 1px solid #e2e8f0; }
        .card-header h3, .card-header h5 { margin: 0; font-weight: 700; color: #0f172a; font-size: 18px; display: flex; align-items: center; gap: 8px; }
        .card-body { padding: 24px; }
        .chart-container { position: relative; height: 300px; margin: 16px 0; }

        /* Progress Card */
        .overall-progress-card { background: var(--primary-gradient); color: white; padding: 24px; border-radius: 12px; margin-bottom: 24px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .overall-progress-card h4 { margin: 0 0 16px 0; font-size: 18px; font-weight: 700; display: flex; align-items: center; gap: 8px; }
        .overall-progress-card .progress { height: 24px; border-radius: 9999px; background: rgba(255,255,255,0.25); overflow: hidden; position: relative; margin-bottom: 12px; }
        .overall-progress-card .progress-bar { background: white; color: #1d4ed8; font-weight: 700; display: flex; align-items: center; justify-content: center; font-size: 14px; transition: width 1.5s cubic-bezier(0.34, 1.56, 0.64, 1); }
        .progress-stats { display: flex; justify-content: space-between; align-items: center; font-size: 14px; }
        .progress-completed { font-weight: 700; background: rgba(255,255,255,0.2); padding: 4px 12px; border-radius: 9999px; }

        .loading-state, .error-state { text-align: center; padding: 40px 24px; background: #f8fafc; border-radius: 12px; }
        .loading-spinner { width: 48px; height: 48px; border: 3px solid #e2e8f0; border-top-color: #3b82f6; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 16px auto; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .program-selection-message { background: #eff6ff; border-left: 4px solid #3b82f6; padding: 16px 20px; border-radius: 0 8px 8px 0; margin: 16px 0 24px 0; display: flex; align-items: center; gap: 12px; color: #1e40af; font-size: 14px; }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .search-container input { width: 180px; }
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
            <a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i><span class="nav-text">Dashboard</span>
            </a>
            <a href="programs.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'programs.php' ? 'active' : ''; ?>">
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
    </nav>

    <div class="main-content">
        <div class="top-nav">
            <div class="top-nav-left">
                <button id="sidebarToggle" class="btn-sidebar-toggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="page-title"><?php echo $page_title; ?></h1>
            </div>
            
            <div class="top-nav-right">
                <div class="search-container">
                     <i class="fas fa-search search-icon"></i>
                     <input type="text" id="searchInput" placeholder="Search programs, files..." class="form-control" onkeyup="performSearch(this.value)" autocomplete="off">
                     <div id="searchResults" class="search-results"></div>
                </div>

                <div class="notification-bell" id="notificationBell">
                    <i class="fas fa-bell"></i>
                    <?php if ($total_notifications > 0): ?>
                        <span class="notification-badge" id="notifBadge"><?php echo $total_notifications; ?></span>
                    <?php endif; ?>
                    <div class="notification-dropdown" id="notificationDropdown">
                        <div class="notification-header">
                            <h6>Notifications</h6>
                            <!-- <a href="#" class="mark-all-read" onclick="markAllAsRead(event)">Mark all as read</a> -->
                        </div>
                        <div class="notification-list" id="notificationList">
                            <?php if (empty($all_notifications)): ?>
                                <div class="notification-empty"><i class="fas fa-bell-slash"></i><p>No new notifications</p></div>
                            <?php else: ?>
                                <?php foreach ($all_notifications as $notif): ?>
                                    <a href="<?php echo $notif['url']; ?>" class="notification-item">
                                        <div class="notif-icon <?php echo $notif['type']; ?>">
                                            <i class="fas <?php echo $notif['type'] == 'user' ? 'fa-user-plus' : 'fa-file-upload'; ?>"></i>
                                        </div>
                                        <div class="notification-content">
                                            <div class="notification-title"><?php echo $notif['title']; ?></div>
                                            <div class="notification-message"><?php echo $notif['message']; ?></div>
                                            <div class="notification-time"><?php 
                                                $time = strtotime($notif['time']);
                                                $diff = time() - $time;
                                                if ($diff < 60) echo 'Just now';
                                                elseif ($diff < 3600) echo floor($diff/60) . 'm ago';
                                                elseif ($diff < 86400) echo floor($diff/3600) . 'h ago';
                                                else echo date('M d, Y', $time);
                                            ?></div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="profile-dropdown">
                    <div class="profile-toggle" onclick="toggleProfileMenu()">
                        <div class="topbar-avatar"><?php echo $user_initials; ?></div>
                        <div class="profile-info"><div class="name"><?php echo htmlspecialchars($admin_name); ?></div><div class="role"><?php echo ucfirst($_SESSION['role']); ?></div></div>
                        <i class="fas fa-chevron-down" style="color: #6c757d; font-size: 12px;"></i>
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
            <div class="welcome-banner">
                <div class="welcome-text">
                    <h2>Welcome back, <?php echo htmlspecialchars($admin_name); ?>!</h2>
                    <p>Here's what's happening in AccrediTrack today.</p>
                </div>
                <div class="text-end" style="z-index: 5;">
                    <div class="welcome-date"><i class="far fa-calendar-alt me-2"></i><?php echo date('l, F j, Y'); ?></div>
                    <div id="realTimeClock" style="font-size: 0.85rem; color: rgba(255,255,255,0.9); margin-top: 6px; font-weight: 600;"></div>
                </div>
                <i class="fas fa-award welcome-decoration"></i>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue"><i class="fas fa-users"></i></div>
                    <div class="stat-content"><h3><?php echo $stats['faculty']; ?></h3><p>Active Faculty</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green"><i class="fas fa-folder"></i></div>
                    <div class="stat-content"><h3><?php echo $stats['programs']; ?></h3><p>Programs</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon cyan"><i class="fas fa-file-lines"></i></div>
                    <div class="stat-content"><h3><?php echo $stats['files']; ?></h3><p>Approved Files</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon yellow"><i class="fas fa-user-shield"></i></div>
                    <div class="stat-content"><h3><?php echo $stats['admins']; ?></h3><p>Admin Users</p></div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3><i class="fas fa-chart-bar me-2"></i>Program Progress Tracking</h3></div>
                <div class="card-body">
                    <div class="program-selection-message" id="selectionMessage">
                        <i class="fas fa-info-circle"></i><span>Select a program and level to view detailed progress charts and statistics.</span>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label class="form-label">Select Program</label>
                            <select class="form-select" id="programSelect">
                                <option value="">-- Select Program --</option>
                                <?php foreach ($programs as $program): ?>
                                    <option value="<?php echo $program['id']; ?>"><?php echo htmlspecialchars($program['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label">Select Level</label>
                            <select class="form-select" id="levelSelect" disabled><option value="">-- Select Level --</option></select>
                        </div>
                    </div>

                    <div id="loadingState" class="loading-state" style="display: none;">
                        <div class="loading-spinner"></div><p class="text-muted">Loading progress data...</p>
                    </div>

                    <div id="errorState" class="error-state" style="display: none;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 30px; color: #cbd5e1; margin-bottom: 10px;"></i>
                        <h4>Unable to Load Data</h4>
                        <p>There was an error loading the progress data.</p>
                        <button class="btn btn-primary btn-sm" onclick="retryLoading()">Retry</button>
                    </div>

                    <div id="chartsSection" style="display: none;">
                        <div class="overall-progress-card">
                            <h4><i class="fas fa-trophy me-2"></i>Overall Program Progress</h4>
                            <div class="progress"><div class="progress-bar" role="progressbar" id="overallProgressBar" style="width: 0%">0%</div></div>
                            <div class="progress-stats"><span id="progressStats">0 / 0 requirements completed</span><span class="progress-completed" id="progressPercent">0%</span></div>
                        </div>

                        <div class="row">
                            <div class="col-lg-8 mb-4">
                                <div class="card h-100" style="margin-bottom: 0;">
                                    <div class="card-header"><h5><i class="fas fa-chart-bar me-2"></i>Area Progress (I - X)</h5></div>
                                    <div class="card-body"><div class="chart-container"><canvas id="barChart"></canvas></div></div>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="card h-100" style="margin-bottom: 0;">
                                    <div class="card-header"><h5><i class="fas fa-chart-pie me-2"></i>Overall Completion</h5></div>
                                    <div class="card-body"><div class="chart-container"><canvas id="pieChart"></canvas></div></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        </div> </div> <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // ==================== SEARCH FUNCTIONALITY ====================
    let searchTimeout;
    const searchApi = '<?php echo $search_api; ?>';

    function performSearch(query) {
        clearTimeout(searchTimeout);
        const resultsDiv = document.getElementById('searchResults');
        if (query.trim().length < 2) {
            resultsDiv.classList.remove('show'); resultsDiv.style.display = 'none'; return;
        }
        searchTimeout = setTimeout(() => {
            fetch(`${searchApi}?q=${encodeURIComponent(query)}`).then(res => res.json()).then(data => { displaySearchResults(data.results || []); }).catch(err => { console.error('Search error:', err); });
        }, 300);
    }
    
    function displaySearchResults(results) {
        const searchResults = document.getElementById('searchResults');
        if (results.length === 0) {
            searchResults.innerHTML = '<div class="search-no-results" style="padding:15px; text-align:center; color:#666;">No results found</div>';
        } else {
             const iconClasses = { 'fas fa-folder': 'program', 'fas fa-folder-open': 'area', 'fas fa-user': 'user', 'fas fa-file-alt': 'document' };
            searchResults.innerHTML = results.map(result => {
                const iconClass = iconClasses[result.icon] || 'document';
                return `<a href="${result.url}" class="notification-item" style="text-decoration:none;"><div class="notif-icon ${iconClass}"><i class="${result.icon}"></i></div><div class="notification-content"><div class="notification-title">${escapeHtml(result.title)}</div><div class="notification-message">${escapeHtml(result.description)}</div></div></a>`;
            }).join('');
        }
        searchResults.style.display = 'block'; searchResults.classList.add('show');
    }

    // ==================== NOTIFICATIONS, PROFILE & SIDEBAR ====================
    document.addEventListener('DOMContentLoaded', function() {
        const notificationBell = document.getElementById('notificationBell');
        const notificationDropdown = document.getElementById('notificationDropdown');
        if (notificationBell) {
            notificationBell.addEventListener('click', function(e) { e.stopPropagation(); notificationDropdown.classList.toggle('show'); });
        }
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.notification-bell')) { if(notificationDropdown) notificationDropdown.classList.remove('show'); }
            if (!e.target.closest('.profile-dropdown')) { const menu = document.getElementById('profileMenu'); if (menu) menu.classList.remove('show'); }
             if (!e.target.closest('.search-container')) { const searchResults = document.getElementById('searchResults'); if (searchResults) { searchResults.classList.remove('show'); searchResults.style.display = 'none'; } }
        });

        // Real-time Clock
        function updateClock() {
            const now = new Date();
            const options = { hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true };
            const clockEl = document.getElementById('realTimeClock');
            if(clockEl) clockEl.textContent = now.toLocaleTimeString('en-US', options);
        }
        setInterval(updateClock, 1000);
        updateClock(); // Initial call

        // SIDEBAR TOGGLE LOGIC
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
                
                // TRIGGER CHART RESIZE (Important for Chart.js in a resizing container)
                setTimeout(() => {
                    window.dispatchEvent(new Event('resize'));
                }, 300); // Matches CSS transition time
            });
        }
    });

    function toggleProfileMenu() { document.getElementById('profileMenu').classList.toggle('show'); }
    function escapeHtml(text) { if (!text) return ''; const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }; return String(text).replace(/[&<>"']/g, m => map[m]); }

    // ==================== CHART LOGIC (Fixed Animations) ====================
        let barChart, pieChart;
        let currentLevelId = null;

        document.getElementById('programSelect').addEventListener('change', function() {
            const programId = this.value;
            const levelSelect = document.getElementById('levelSelect');
            const chartsSection = document.getElementById('chartsSection');
            const selectionMessage = document.getElementById('selectionMessage');
            
            if (programId) {
                chartsSection.style.display = 'none'; selectionMessage.style.display = 'flex';
                fetch(`../api/get_levels.php?program_id=${programId}`).then(response => response.json()).then(data => {
                        levelSelect.innerHTML = '<option value="">-- Select Level --</option>';
                        if (data.success || data.status === 200) { (data.data || []).forEach(level => { levelSelect.innerHTML += `<option value="${level.id}">${level.name || level.level_type}</option>`; }); }
                        levelSelect.disabled = false;
                    }).catch(err => { console.error('Error:', err); levelSelect.disabled = false; });
            } else {
                levelSelect.innerHTML = '<option value="">-- Select Level --</option>'; levelSelect.disabled = true; chartsSection.style.display = 'none'; selectionMessage.style.display = 'flex';
            }
        });

        document.getElementById('levelSelect').addEventListener('change', function() {
            const levelId = this.value;
            currentLevelId = levelId;
            if (levelId) { showLoadingState(); fetchProgressData(levelId); } else { document.getElementById('chartsSection').style.display = 'none'; document.getElementById('selectionMessage').style.display = 'flex'; }
        });

        function fetchProgressData(levelId) {
            fetch(`../api/get_progress.php?level_id=${levelId}`).then(response => response.json()).then(data => {
                    hideLoadingState();
                    if (data.success) { hideErrorState(); displayCharts(data.data); } else { showErrorState(data.message || 'Failed to load data'); }
                }).catch(err => { console.error('Error:', err); hideLoadingState(); showErrorState('Network error.'); });
        }

        function displayCharts(data) {
            const chartsSection = document.getElementById('chartsSection');
            const selectionMessage = document.getElementById('selectionMessage');
            chartsSection.style.display = 'block'; selectionMessage.style.display = 'none';
            
            const areas = data.areas || [];
            const areaLabels = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X'];
            const percentages = [];
            let manualTotalApproved = 0, manualTotalRequired = 0;
            
            for(let i = 1; i <= 10; i++) {
                const foundArea = areas.find(a => parseInt(String(a.area_number).replace(/\D/g, '')) === i);
                if (foundArea) {
                    percentages.push(parseFloat(foundArea.progress || 0));
                    manualTotalApproved += parseInt(foundArea.approved || 0); manualTotalRequired += parseInt(foundArea.required || 0);
                } else { percentages.push(0); }
            }
            
            let overallProgress = manualTotalRequired > 0 ? Math.round((manualTotalApproved / manualTotalRequired) * 100) : 0;
            const progressBar = document.getElementById('overallProgressBar');
            progressBar.style.width = '0%'; progressBar.textContent = '0%';
            
            setTimeout(() => { progressBar.style.width = overallProgress + '%'; progressBar.textContent = overallProgress + '%'; }, 100);
            
            document.getElementById('progressStats').textContent = `${manualTotalApproved} / ${manualTotalRequired} requirements completed`;
            document.getElementById('progressPercent').textContent = overallProgress + '%';
            
            if (barChart) barChart.destroy();
            if (pieChart) pieChart.destroy();
            
            // Add a small delay to ensure container is rendered and prevent animation skipping
            setTimeout(() => {
                // Bar Chart
                const barCtx = document.getElementById('barChart').getContext('2d');
                barChart = new Chart(barCtx, {
                    type: 'bar',
                    data: {
                        labels: areaLabels.map(l => 'Area ' + l),
                        datasets: [{
                            label: 'Progress (%)',
                            data: percentages,
                            backgroundColor: percentages.map(p => {
                                if (p >= 80) return 'rgba(34, 197, 94, 0.8)';
                                if (p >= 50) return 'rgba(59, 130, 246, 0.8)';
                                if (p >= 20) return 'rgba(245, 158, 11, 0.8)';
                                return 'rgba(239, 68, 68, 0.8)';
                            }),
                            borderWidth: 1, borderRadius: 6
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        animations: {
                            y: {
                                duration: 2000,
                                easing: 'easeOutBounce',
                                delay: (context) => context.dataIndex * 200
                            }
                        },
                        scales: { y: { beginAtZero: true, max: 100, grid: { color: 'rgba(0,0,0,0.05)' } }, x: { grid: { display: false } } },
                        plugins: { legend: { display: false }, tooltip: { callbacks: { label: function(context) { return context.parsed.y + '%'; } } } }
                    }
                });
                
                // Pie Chart with Explicit Delay to Force Visual "Rise" & % Tooltip
                const remaining = Math.max(0, 100 - overallProgress);
                const pieCtx = document.getElementById('pieChart').getContext('2d');
                
                // Initialize with 0 to set start state
                pieChart = new Chart(pieCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Completed', 'Remaining'],
                        datasets: [{
                            data: [0, 100], // Start empty
                            backgroundColor: ['rgba(34, 197, 94, 0.8)', 'rgba(203, 213, 225, 0.8)'],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false, cutout: '70%',
                        animation: {
                            duration: 2000,
                            easing: 'easeOutQuart'
                        },
                        plugins: { 
                            legend: { display: true, position: 'bottom' },
                            tooltip: { 
                                callbacks: { 
                                    label: function(context) { 
                                        return context.label + ': ' + context.parsed + '%'; 
                                    } 
                                } 
                            }
                        }
                    }
                });

                // Trigger the "rising" animation
                setTimeout(() => {
                    pieChart.data.datasets[0].data = [overallProgress, remaining];
                    pieChart.update();
                }, 300);

            }, 50); // Small DOM yield
        }

        function showLoadingState() { document.getElementById('loadingState').style.display = 'flex'; document.getElementById('errorState').style.display = 'none'; document.getElementById('chartsSection').style.display = 'none'; document.getElementById('selectionMessage').style.display = 'none'; }
        function hideLoadingState() { document.getElementById('loadingState').style.display = 'none'; }
        function showErrorState(message) { document.getElementById('errorState').style.display = 'block'; document.getElementById('loadingState').style.display = 'none'; document.getElementById('chartsSection').style.display = 'none'; if (message) document.querySelector('#errorState p').textContent = message; }
        function hideErrorState() { document.getElementById('errorState').style.display = 'none'; }
        function retryLoading() { if (currentLevelId) { showLoadingState(); fetchProgressData(currentLevelId); } }
    </script>
</body>
</html>