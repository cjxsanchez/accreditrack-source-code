<?php
/**
 * Program View - Show Levels, Areas, Parameters, and Indicators with Folders
 * Updated with Modern Hamburger Sidebar & Top Nav
 */
require_once dirname(__DIR__) . '/config/config.php';
requireAdmin();

$db = getDB();
$program_id = $_GET['id'] ?? null;

if (!$program_id) {
    redirect('admin/programs.php');
}

// --- ADDED: Helper for User Initials (Required for Top Nav) ---
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

// --- ADDED: Fetch Current User Info (Required for Top Nav) ---
$stmt = $db->prepare("SELECT full_name, email FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$current_user = $stmt->fetch(PDO::FETCH_ASSOC);
$user_initials = getUserInitials($current_user['full_name'] ?? $_SESSION['full_name'] ?? 'User');

// Get program details
$query = "SELECT * FROM programs WHERE id = ? AND status = 'active'";
$stmt = $db->prepare($query);
$stmt->execute([$program_id]);
$program = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$program) {
    die('Program not found');
}

$page_title = 'Program: ' . $program['name'];

// Get all levels for this program
$query = "SELECT * FROM levels WHERE program_id = ? ORDER BY level_type";
$stmt = $db->prepare($query);
$stmt->execute([$program_id]);
$levels = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get area structure
$areaStructure = getAreaStructure();

// Dynamic Counts Logic
function getDynamicRequirementCounts($db) {
    $counts = [];
    $romanNumerals = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X'];
    
    for ($i = 1; $i <= 10; $i++) {
        $areaRoman = $romanNumerals[$i - 1];
        $counts[$areaRoman] = [];
        
        $query = "SELECT DISTINCT parameter_letter FROM global_accreditation_tags WHERE area_number = ? AND is_active = TRUE";
        $stmt = $db->prepare($query);
        $stmt->execute([$i]);
        $params = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($params as $param) {
            $counts[$areaRoman][$param] = ['S' => 0, 'I' => 0, 'O' => 0];
            
            foreach (['S', 'I', 'O'] as $type) {
                $query = "SELECT COUNT(gr.id) as total
                          FROM global_requirements gr
                          JOIN global_accreditation_tags gat ON gr.global_folder_id = gat.id
                          WHERE gat.area_number = ? AND gat.parameter_letter = ? AND gat.indicator_type = ?
                          AND gr.is_active = TRUE AND gat.is_active = TRUE";
                $stmt = $db->prepare($query);
                $stmt->execute([$i, $param, $type]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $counts[$areaRoman][$param][$type] = (int)($result['total'] ?? 0);
            }
        }
    }
    return $counts;
}

$docRequirements = getDynamicRequirementCounts($db);

// Sidebar Pending Counts Logic
$pending_documents = 0;
$pending_users = 0;

$stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE status = 'pending'");
$stmt->execute();
$pending_documents = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE status = 'pending'");
$stmt->execute();
$pending_users = $stmt->fetchColumn();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo SITE_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
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
            --bg-body: #f3f4f6;
            --bg-card: #ffffff;
            --text-main: #111827;
            --text-secondary: #6b7280;
            --border-color: #e5e7eb;
            --border-hover: #d1d5db;
        }

        body {
            background-color: var(--bg-body);
            font-family: 'Inter', sans-serif;
            color: var(--text-main);
            /* Transition for content shift */
            transition: margin-left 0.3s ease;
        }

        /* --- Sidebar Styles --- */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--primary-gradient);
            padding: 0;
            z-index: 1000;
            /* Hide scrollbar visually but keep functionality */
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

        /* Sidebar Elements */
        .sidebar-brand {
            padding: 24px 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            text-decoration: none;
            white-space: nowrap;
            transition: all 0.3s ease;
        }

        .sidebar-logo {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            object-fit: cover;
            box-shadow: 0 4px 6px rgba(0,0,0,0.15);
            flex-shrink: 0;
        }

        .sidebar-brand-text {
            color: white;
            font-size: 20px;
            font-weight: 700;
            letter-spacing: -0.5px;
            transition: opacity 0.2s ease;
        }

        .sidebar-menu { padding: 20px 15px; overflow-y: auto; height: calc(100vh - 100px); }
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
            border-top-left-radius: 4px;
            border-bottom-left-radius: 4px;
        }

        .sidebar-menu a i { width: 22px; margin-right: 12px; font-size: 15px; flex-shrink: 0; text-align: center; transition: margin 0.3s ease; }
        .nav-text { transition: opacity 0.2s ease; }

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
            min-width: 22px;
            text-align: center;
        }

        /* --- Main Content Layout --- */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

        /* Top Navigation (Added) */
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
            height: 80px;
        }

        .top-nav-left { display: flex; align-items: center; gap: 15px; }
        .top-nav-right { display: flex; align-items: center; gap: 20px; }

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
        .btn-sidebar-toggle:hover { background-color: #f3f4f6; color: var(--color-primary-600); }

        .page-header-title { font-size: 20px; font-weight: 700; color: #111; margin: 0; }

        /* Profile Dropdown (Mini) */
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

        /* Content Area */
        .content-area { padding: 30px 40px; }

        .nav-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            color: var(--text-secondary);
            transition: all 0.2s;
            text-decoration: none;
        }

        .back-link:hover {
            background: white;
            border-color: var(--primary-color);
            color: var(--primary-color);
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }

        .page-meta h1 {
            font-size: 24px;
            font-weight: 700;
            margin: 0;
            color: var(--text-main);
        }

        .page-meta p {
            margin: 2px 0 0;
            color: var(--text-secondary);
            font-size: 14px;
        }

        /* --- Level Cards --- */
        .levels-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 24px;
        }

        .level-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 24px;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
        }

        .level-card:hover {
            border-color: #bfdbfe;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            transform: translateY(-2px);
        }

        .level-icon-wrapper {
            width: 48px;
            height: 48px;
            background: #eff6ff;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
            font-size: 20px;
            margin-bottom: 16px;
        }

        .level-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 8px;
        }

        .level-stats {
            font-size: 13px;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .action-arrow {
            margin-top: 20px;
            font-size: 13px;
            font-weight: 600;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 5px;
            opacity: 0.8;
            transition: opacity 0.2s;
        }
        
        .level-card:hover .action-arrow {
            opacity: 1;
            gap: 8px;
        }

        /* --- Modern Modals --- */
        .modal-content {
            border: none;
            border-radius: 20px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        .modal-header {
            background: #f9fafb;
            border-bottom: 1px solid var(--border-color);
            padding: 16px 24px;
            display: flex;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .modal-header .btn-close {
            margin: 0;
            padding: 0.5rem;
        }
        
        .modal-nav-btn {
            background: transparent;
            border: 1px solid transparent;
            color: var(--text-secondary);
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            transition: all 0.2s;
        }

        .modal-nav-btn:hover {
            background: white;
            border-color: var(--border-color);
            color: var(--text-main);
        }

        .modal-title {
            font-weight: 600;
            color: var(--text-main);
            font-size: 16px;
            flex: 1;
        }

        .modal-body {
            background: #ffffff;
            padding: 24px;
            max-height: 75vh;
            overflow-y: auto;
        }

        /* --- Area Grid --- */
        .area-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 16px;
        }

        .area-item {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .area-item:hover {
            border-color: var(--primary-color);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .area-number {
            font-size: 20px;
            font-weight: 800;
            color: var(--primary-color);
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .area-name {
            font-size: 14px;
            font-weight: 500;
            color: var(--text-main);
            line-height: 1.4;
        }

        /* --- Parameters List --- */
        .parameters-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .parameter-row {
            display: flex;
            align-items: center;
            padding: 16px 20px;
            border: 1px solid var(--border-color);
            margin-bottom: -1px;
            cursor: pointer;
            transition: background 0.2s;
        }

        .parameter-row:first-child { border-top-left-radius: 12px; border-top-right-radius: 12px; }
        .parameter-row:last-child { border-bottom-left-radius: 12px; border-bottom-right-radius: 12px; margin-bottom: 0; }

        .parameter-row:hover {
            background-color: #f9fafb;
            z-index: 1;
        }

        .param-letter {
            width: 32px;
            height: 32px;
            background: #eff6ff;
            color: var(--primary-color);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            margin-right: 16px;
            font-size: 14px;
        }

        .param-text {
            flex: 1;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-main);
        }

        /* --- Requirements & Folders --- */
        .folder-card {
            border: 1px solid var(--border-color);
            border-radius: 12px;
            margin-bottom: 16px;
        }

        .folder-header {
            background: #f9fafb;
            padding: 14px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            transition: background 0.2s;
            border-radius: 12px;
        }

        .folder-header:hover { background: #f3f4f6; }

        .folder-card.open .folder-header {
            border-bottom-left-radius: 0;
            border-bottom-right-radius: 0;
        }

        .folder-title { font-weight: 600; font-size: 14px; color: var(--text-main); display: flex; align-items: center; gap: 10px; }
        .folder-meta { font-size: 12px; color: var(--text-secondary); background: white; padding: 4px 10px; border-radius: 12px; border: 1px solid var(--border-color); }

        .folder-body { display: none; background: white; border-top: 1px solid var(--border-color); border-bottom-left-radius: 12px; border-bottom-right-radius: 12px; }
        .folder-body.show { display: block; }

        .req-item {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .req-item:last-child { border-bottom: none; }

        .req-details { flex: 1; padding-right: 20px; }
        .req-name { font-size: 13px; font-weight: 500; color: var(--text-main); margin-bottom: 4px; }
        .req-code { font-size: 11px; font-family: 'Monaco', 'Consolas', monospace; color: var(--text-secondary); background: #f3f4f6; padding: 2px 6px; border-radius: 4px; display: inline-block; }

        /* Status Pills */
        .status-pill {
            font-size: 11px;
            font-weight: 600;
            padding: 4px 12px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .status-pill.uploaded { background: #ecfdf5; color: #059669; border: 1px solid #d1fae5; }
        .status-pill.empty { background: #fef2f2; color: #dc2626; border: 1px solid #fee2e2; }

        /* Dropdown Tweaks */
        .dropdown-item { font-size: 13px; padding: 8px 16px; }
        .dropdown-item i { width: 16px; text-align: center; }

        @media (max-width: 768px) {
            .sidebar { width: 0; overflow: hidden; }
            .main-content { margin-left: 0; padding: 20px; }
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
            <img src="../assets/img/accreditrack_official_logo.png" alt="Logo" class="sidebar-logo">
            <span class="sidebar-brand-text">AccrediTrack</span>
        </div>
        
        <div class="sidebar-menu">
            <a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i><span class="nav-text">Dashboard</span>
            </a>
            <a href="programs.php" class="<?php echo in_array(basename($_SERVER['PHP_SELF']), ['programs.php', 'program_view.php']) ? 'active' : ''; ?>">
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
                <h1 class="page-header-title">Program Details</h1>
            </div>
            
            <div class="top-nav-right">
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
                        <a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i>Logout</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-area">
            <div class="nav-header">
                <a href="programs.php" class="back-link" title="Back to Programs">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div class="page-meta">
                    <h1><?php echo htmlspecialchars($program['name']); ?></h1>
                    <p>Code: <?php echo htmlspecialchars($program['code']); ?> &bull; Active Accreditation Cycle</p>
                </div>
            </div>

            <div class="levels-grid">
                <?php foreach ($levels as $level): ?>
                    <div class="level-card" onclick="openLevelAreas(<?php echo $level['id']; ?>, '<?php echo htmlspecialchars($level['level_type']); ?>')">
                        <div>
                            <div class="level-icon-wrapper">
                                <i class="fas fa-layer-group"></i>
                            </div>
                            <div class="level-title"><?php echo htmlspecialchars($level['level_type']); ?></div>
                            <div class="level-stats">
                                <i class="fas fa-th-large"></i> 10 Areas
                                <span class="mx-2">&bull;</span>
                                <i class="fas fa-list-ul"></i> Requirements
                            </div>
                        </div>
                        <div class="action-arrow">
                            View Details <i class="fas fa-arrow-right"></i>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="modal fade" id="areasModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="areasModalTitle">Select Area</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="area-grid" id="areasContainer"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="parametersModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="modal-nav-btn" onclick="backToAreas()">
                        <i class="fas fa-arrow-left"></i>
                    </button>
                    <h5 class="modal-title" id="parametersModalTitle">Parameters</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0"> 
                    <ul class="parameters-list" id="parametersContainer"></ul>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="indicatorsModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="modal-nav-btn" onclick="backToParameters()">
                        <i class="fas fa-arrow-left"></i>
                    </button>
                    <h5 class="modal-title" id="indicatorsModalTitle">Requirements</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body bg-light">
                    
                    <div id="indicatorsContainer" class="row g-3 mb-4">
                        </div>
                    
                    <div id="foldersContainer" style="display: none;">
                        <div class="d-flex align-items-center mb-3">
                            <button class="btn btn-sm btn-outline-secondary me-3" onclick="backToIndicators()">
                                <i class="fas fa-arrow-up me-1"></i> Back to Types
                            </button>
                            <h6 class="m-0 fw-bold text-secondary" id="foldersTitle">Folders</h6>
                        </div>
                        <div id="foldersList"></div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // --- Sidebar Logic ---
        document.addEventListener('DOMContentLoaded', function() {
            initSidebarToggle();
        });

        function initSidebarToggle() {
            const toggleBtn = document.getElementById('sidebarToggle');
            if (!toggleBtn) return;
            
            toggleBtn.addEventListener('click', function(e) {
                e.preventDefault();
                document.body.classList.toggle('sidebar-collapsed');
                if (document.body.classList.contains('sidebar-collapsed')) {
                    localStorage.setItem('sidebarState', 'collapsed');
                } else {
                    localStorage.setItem('sidebarState', 'expanded');
                }
            });
        }

        // --- Profile Menu Logic ---
        function toggleProfileMenu() {
            const menu = document.getElementById('profileMenu');
            menu.classList.toggle('show');
        }

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.profile-dropdown')) {
                const menu = document.getElementById('profileMenu');
                if (menu) menu.classList.remove('show');
            }
        });

        // --- Existing Page Logic ---
        const areaStructure = <?php echo json_encode($areaStructure); ?>;
        const docRequirements = <?php echo json_encode($docRequirements); ?>;

        // State Management
        let currentLevelId = null;
        let currentLevelType = null;
        let currentAreaNumber = null;
        let currentAreaName = null;
        let currentParameterLetter = null;
        let currentParameterName = null;
        let currentIndicatorType = null;

        // 1. Open Areas Modal
        function openLevelAreas(levelId, levelType) {
            currentLevelId = levelId;
            currentLevelType = levelType;
            
            document.getElementById('areasModalTitle').textContent = `${levelType} - Select Area`;
            
            let areasHtml = '';
            for (const [areaNum, areaData] of Object.entries(areaStructure)) {
                areasHtml += `
                    <div class="area-item" onclick="openAreaParameters('${areaNum}', '${escapeHtml(areaData.name)}')">
                        <div class="area-number">${areaNum}</div>
                        <div class="area-name">${areaData.name}</div>
                    </div>
                `;
            }
            
            document.getElementById('areasContainer').innerHTML = areasHtml;
            new bootstrap.Modal(document.getElementById('areasModal')).show();
        }

        // 2. Open Parameters Modal
        function openAreaParameters(areaNumber, areaName) {
            currentAreaNumber = areaNumber;
            currentAreaName = areaName;
            
            document.getElementById('parametersModalTitle').textContent = `Area ${areaNumber}: ${areaName}`;
            
            const parameters = areaStructure[areaNumber].parameters;
            let parametersHtml = '';
            
            for (const [paramLetter, paramName] of Object.entries(parameters)) {
                const safeParamName = paramName.replace(/'/g, "\\'");
                parametersHtml += `
                    <li class="parameter-row" onclick="openParameterIndicators('${areaNumber}', '${paramLetter}', '${safeParamName}')">
                        <div class="param-letter">${paramLetter}</div>
                        <div class="param-text">${escapeHtml(paramName)}</div>
                        <i class="fas fa-chevron-right text-muted small"></i>
                    </li>
                `;
            }
            
            document.getElementById('parametersContainer').innerHTML = parametersHtml;
            
            bootstrap.Modal.getInstance(document.getElementById('areasModal'))?.hide();
            new bootstrap.Modal(document.getElementById('parametersModal')).show();
        }

        // 3. Open Indicators (Requirement Types)
        function openParameterIndicators(areaNumber, parameterLetter, parameterName) {
            currentParameterLetter = parameterLetter;
            currentParameterName = parameterName;
            
            document.getElementById('indicatorsModalTitle').textContent = `${parameterName}`;
            
            // Reset Views
            document.getElementById('foldersContainer').style.display = 'none';
            document.getElementById('indicatorsContainer').style.display = 'flex'; // Flex row for grid
            
            const requirements = docRequirements[areaNumber]?.[parameterLetter] || {S: 0, I: 0, O: 0};
            
            const indicatorTypes = {
                'S': 'System - Inputs & Processes',
                'I': 'Implementation',
                'O': 'Outcome/s'
            };
            
            let indicatorsHtml = '';
            for (const [type, name] of Object.entries(indicatorTypes)) {
                const count = requirements[type] || 0;
                indicatorsHtml += `
                    <div class="col-md-4">
                        <div class="level-card h-100" onclick="loadIndicatorFolders('${areaNumber}', '${parameterLetter}', '${type}', '${escapeHtml(name)}')" style="border-style: dashed; background: #fff;">
                            <div class="d-flex align-items-center mb-3">
                                <span class="badge bg-primary me-2">${type}</span>
                                <small class="text-muted fw-bold">Requirements: ${count}</small>
                            </div>
                            <h6 class="fw-bold text-dark">${name}</h6>
                            <div class="mt-auto text-end text-primary small">Open <i class="fas fa-arrow-right ms-1"></i></div>
                        </div>
                    </div>
                `;
            }
            
            document.getElementById('indicatorsContainer').innerHTML = indicatorsHtml;
            
            bootstrap.Modal.getInstance(document.getElementById('parametersModal'))?.hide();
            new bootstrap.Modal(document.getElementById('indicatorsModal')).show();
        }

        // 4. Load Folders & Files
        function loadIndicatorFolders(areaNumber, parameterLetter, indicatorType, indicatorName) {
            currentIndicatorType = indicatorType;
            const romanToNum = {'I': 1, 'II': 2, 'III': 3, 'IV': 4, 'V': 5, 'VI': 6, 'VII': 7, 'VIII': 8, 'IX': 9, 'X': 10};
            const areaNumForApi = romanToNum[areaNumber] || areaNumber;

            // Update UI
            document.getElementById('foldersTitle').innerHTML = `${indicatorName}`;
            document.getElementById('indicatorsContainer').style.display = 'none';
            document.getElementById('foldersContainer').style.display = 'block';
            
            document.getElementById('foldersList').innerHTML = '<div class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin me-2"></i> Loading requirements...</div>';

            fetch(`../api/get_indicator_folders.php?level_id=${currentLevelId}&area_number=${areaNumForApi}&parameter_letter=${parameterLetter}&indicator_type=${indicatorType}`)
                .then(r => r.json())
                .then(data => {
                    if (data.status === 200 && data.data && data.data.length > 0) {
                        let html = '';
                        data.data.forEach((folder, idx) => {
                            const uploaded = folder.uploaded_count;
                            const total = folder.total_requirements;
                            const isComplete = uploaded >= total && total > 0;
                            
                            html += `
                                <div class="folder-card ${idx === 0 ? 'open' : ''}">
                                    <div class="folder-header" onclick="toggleFolder(${idx})">
                                        <div class="folder-title">
                                            <i class="fas fa-folder ${isComplete ? 'text-success' : 'text-warning'}"></i>
                                            ${escapeHtml(folder.folder_name)}
                                            <span class="folder-meta ms-2">${escapeHtml(folder.folder_code)}</span>
                                        </div>
                                        <span class="badge ${isComplete ? 'bg-success' : 'bg-secondary'} rounded-pill">
                                            ${uploaded}/${total} Uploaded
                                        </span>
                                    </div>
                                    <div class="folder-body" id="folder-content-${idx}">
                            `;
                            
                            if (folder.requirements && folder.requirements.length > 0) {
                                folder.requirements.forEach(req => {
                                    const hasDoc = req.document !== null;
                                    
                                    html += `
                                        <div class="req-item">
                                            <div class="req-details">
                                                <div class="req-name">${escapeHtml(req.requirement_name)}</div>
                                                <span class="req-code">${escapeHtml(req.requirement_code)}</span>
                                                ${hasDoc ? `<div class="small text-muted mt-1"><i class="fas fa-file-alt me-1"></i> ${escapeHtml(req.document.original_filename)}</div>` : ''}
                                            </div>
                                            <div class="text-end">
                                    `;
                                    
                                    if (hasDoc) {
                                        html += `
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="status-pill uploaded">
                                                    <i class="fas fa-check"></i> Uploaded
                                                </div>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-light border-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                        <i class="fas fa-ellipsis-v text-secondary"></i>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" style="min-width: 160px;">
                                                        <li>
                                                            <a class="dropdown-item" href="#" onclick="downloadDocument(${req.document.id}); return false;">
                                                                <i class="fas fa-download me-2 text-primary"></i> Download
                                                            </a>
                                                        </li>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li>
                                                            <a class="dropdown-item text-danger" href="#" onclick="deleteDocument(${req.document.id}); return false;">
                                                                <i class="fas fa-trash me-2"></i> Delete
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                        `;
                                    } else {
                                        html += `
                                            <div class="status-pill empty">
                                                <i class="fas fa-times"></i> Missing
                                            </div>
                                        `;
                                    }
                                    
                                    html += `</div></div>`;
                                });
                            } else {
                                html += `<div class="p-3 text-center text-muted small">No requirements defined.</div>`;
                            }
                            
                            html += `</div></div>`;
                        });
                        document.getElementById('foldersList').innerHTML = html;
                    } else {
                        document.getElementById('foldersList').innerHTML = `<div class="text-center p-4 text-muted border rounded bg-white">No folders found for this indicator.</div>`;
                    }
                })
                .catch(e => {
                    console.error(e);
                    document.getElementById('foldersList').innerHTML = `<div class="alert alert-danger">Failed to load data.</div>`;
                });
        }

        // Navigation Back Handlers
        function backToAreas() {
            bootstrap.Modal.getInstance(document.getElementById('parametersModal'))?.hide();
            openLevelAreas(currentLevelId, currentLevelType);
        }

        function backToParameters() {
            bootstrap.Modal.getInstance(document.getElementById('indicatorsModal'))?.hide();
            openAreaParameters(currentAreaNumber, currentAreaName);
        }

        function backToIndicators() {
            document.getElementById('foldersContainer').style.display = 'none';
            document.getElementById('indicatorsContainer').style.display = 'flex';
        }

        function toggleFolder(idx) {
            const el = document.getElementById(`folder-content-${idx}`);
            const card = el.parentElement;
            
            if (el.style.display === 'block') {
                el.style.display = 'none';
                card.classList.remove('open');
            } else {
                el.style.display = 'block';
                card.classList.add('open');
            }
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // View/Download/Delete functions
        function downloadDocument(docId) {
            window.location.href = `../api/get_document.php?id=${docId}&action=download`;
        }
        
        function deleteDocument(docId) {
            if(confirm('Are you sure you want to delete this document? This action cannot be undone.')) {
                fetch('../api/delete_document.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({document_id: docId})
                })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 200) {
                        // Reload the current folder view
                        loadIndicatorFolders(currentAreaNumber, currentParameterLetter, currentIndicatorType, document.getElementById('foldersTitle').textContent);
                    } else {
                        alert('Error: ' + (data.message || 'Failed to delete document'));
                    }
                })
                .catch(e => {
                    console.error('Error:', e);
                    alert('Error deleting document');
                });
            }
        }
    </script>
</body>
</html>