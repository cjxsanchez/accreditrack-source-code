<?php
require_once '../config/config.php';
requireAdmin();

$page_title = 'Profile';

$database = new Database();
$db = $database->getConnection();

// Get user data
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_profile':
                $username = sanitize($_POST['username']); 
                $full_name = sanitize($_POST['full_name']);
                $email = sanitize($_POST['email']);
                
                // Check if username is taken (safety)
                $check = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                $check->execute([$username, $_SESSION['user_id']]);
                if($check->rowCount() > 0){
                    $error = 'Username already taken.';
                } else {
                    $query = "UPDATE users SET full_name = ?, email = ?, username = ? WHERE id = ?";
                    $stmt = $db->prepare($query);
                    if ($stmt->execute([$full_name, $email, $username, $_SESSION['user_id']])) {
                        $_SESSION['full_name'] = $full_name;
                        $success = 'Profile updated successfully.';
                        $user['full_name'] = $full_name;
                        $user['email'] = $email;
                        $user['username'] = $username;
                    } else {
                        $error = 'Failed to update profile.';
                    }
                }
                break;
                
            case 'change_password':
                $current_password = $_POST['current_password'];
                $new_password = $_POST['new_password'];
                $confirm_password = $_POST['confirm_password'];
                
                if (!password_verify($current_password, $user['password'])) {
                    $error = 'Current password is incorrect.';
                } elseif ($new_password !== $confirm_password) {
                    $error = 'New passwords do not match.';
                } elseif (strlen($new_password) < 6) {
                    $error = 'Password must be at least 6 characters long.';
                } else {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $query = "UPDATE users SET password = ? WHERE id = ?";
                    $stmt = $db->prepare($query);
                    if ($stmt->execute([$hashed_password, $_SESSION['user_id']])) {
                        $success = 'Password changed successfully.';
                    } else {
                        $error = 'Failed to change password.';
                    }
                }
                break;
        }
    }
}

function getInitials($name) {
    $words = explode(' ', trim($name));
    $initials = '';
    foreach ($words as $word) {
        if (!empty($word)) {
            $initials .= strtoupper(substr($word, 0, 1));
        }
    }
    return substr($initials, 0, 2);
}

$user_initials = getInitials($user['full_name']);

include '../includes/header.php';
?>

<style>
/* Modern Compact Profile Styles */
:root {
    --profile-bg: #ffffff;
    --profile-border: #e2e8f0;
    --profile-text: #1e293b;
    --profile-muted: #64748b;
    --brand-color: #4f46e5;
}

/* FIX: Force container to top to remove gap */
.container-fluid {
    padding-top: 0 !important;
}

.profile-container {
    /* Aggressive negative margin to pull content up to the yellow line */
    margin-top: -35px; 
}

/* Card Common Styles */
.modern-card {
    background: var(--profile-bg);
    border: 1px solid var(--profile-border);
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    margin-bottom: 20px;
    overflow: hidden;
}

.modern-card-header {
    padding: 15px 20px;
    border-bottom: 1px solid #f1f5f9;
    font-weight: 600;
    color: var(--profile-text);
    font-size: 0.95rem;
    display: flex;
    align-items: center;
}

.modern-card-body {
    padding: 20px;
}

/* Left Sidebar Profile Card */
.profile-hero {
    text-align: center;
    padding-bottom: 20px;
    border-bottom: 1px solid #f1f5f9;
    margin-bottom: 20px;
}

.avatar-circle {
    width: 80px;
    height: 80px;
    background: #eff6ff;
    color: var(--brand-color);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    font-weight: 700;
    margin: 0 auto 15px;
    border: 4px solid #fff;
    box-shadow: 0 0 0 1px var(--profile-border);
}

.user-name {
    font-weight: 700;
    font-size: 1.1rem;
    color: var(--profile-text);
    margin-bottom: 2px;
}

.user-role {
    font-size: 0.8rem;
    color: var(--profile-muted);
    background: #f1f5f9;
    padding: 2px 10px;
    border-radius: 12px;
    display: inline-block;
}

/* Data Lists */
.data-row {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid #f8fafc;
    font-size: 0.9rem;
}
.data-row:last-child { border-bottom: none; }
.data-label { color: var(--profile-muted); }
.data-value { font-weight: 500; color: var(--profile-text); }

/* Form Styles */
.form-label-sm {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--profile-muted);
    margin-bottom: 4px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.form-control-modern {
    border: 1px solid var(--profile-border);
    border-radius: 6px;
    padding: 8px 12px;
    font-size: 0.9rem;
    background-color: #f8fafc;
    transition: all 0.2s;
}

.form-control-modern:focus {
    background-color: #fff;
    border-color: var(--brand-color);
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}

.btn-save {
    background-color: var(--brand-color);
    color: white;
    border: none;
    padding: 8px 20px;
    border-radius: 6px;
    font-weight: 500;
    font-size: 0.9rem;
}

.btn-save:hover {
    background-color: #4338ca;
    color: white;
}
</style>

<div class="container-fluid profile-container">
    
    <?php if (isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert" style="font-size: 0.9rem;">
            <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert" style="font-size: 0.9rem;">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="modern-card h-100">
                <div class="modern-card-body">
                    <div class="profile-hero">
                        <div class="avatar-circle">
                            <?php echo $user_initials; ?>
                        </div>
                        <h4 class="user-name"><?php echo htmlspecialchars($user['full_name']); ?></h4>
                        <span class="user-role"><?php echo ucfirst($user['role']); ?></span>
                    </div>

                    <div class="pt-2">
                        <div class="data-row">
                            <span class="data-label"><i class="fas fa-fingerprint me-2"></i>User ID</span>
                            <span class="data-value">#<?php echo htmlspecialchars($user['id']); ?></span>
                        </div>
                        <div class="data-row">
                            <span class="data-label"><i class="far fa-envelope me-2"></i>Email</span>
                            <span class="data-value" style="font-size: 0.85rem;"><?php echo htmlspecialchars($user['email']); ?></span>
                        </div>
                        <div class="data-row">
                            <span class="data-label"><i class="far fa-calendar-alt me-2"></i>Joined</span>
                            <span class="data-value"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></span>
                        </div>
                        <div class="data-row">
                            <span class="data-label"><i class="far fa-clock me-2"></i>Status</span>
                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Active Now</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="modern-card">
                <div class="modern-card-header">
                    <i class="far fa-edit me-2"></i> Edit Personal Information
                </div>
                <div class="modern-card-body">
                    <form method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="action" value="update_profile">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="username" class="form-label-sm">Username</label>
                                <input type="text" class="form-control form-control-modern" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="full_name" class="form-label-sm">Full Name</label>
                                <input type="text" class="form-control form-control-modern" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                            </div>
                            <div class="col-12">
                                <label for="email" class="form-label-sm">Email Address</label>
                                <input type="email" class="form-control form-control-modern" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            <div class="col-12 text-end mt-4">
                                <button type="submit" class="btn btn-save">
                                    Save Changes
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="modern-card mb-0">
                <div class="modern-card-header">
                    <i class="fas fa-shield-alt me-2"></i> Security
                </div>
                <div class="modern-card-body">
                    <form method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="action" value="change_password">
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="current_password" class="form-label-sm">Current Password</label>
                                <input type="password" class="form-control form-control-modern" name="current_password" required>
                            </div>
                            <div class="col-md-6">
                                <label for="new_password" class="form-label-sm">New Password</label>
                                <input type="password" class="form-control form-control-modern" name="new_password" minlength="6" placeholder="Min 6 characters" required>
                            </div>
                            <div class="col-md-6">
                                <label for="confirm_password" class="form-label-sm">Confirm Password</label>
                                <input type="password" class="form-control form-control-modern" name="confirm_password" minlength="6" placeholder="Re-type password" required>
                            </div>
                            <div class="col-12 text-end mt-4">
                                <button type="submit" class="btn btn-outline-secondary btn-sm" style="padding: 8px 20px;">
                                    Update Password
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>