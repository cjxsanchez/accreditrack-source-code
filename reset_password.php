<?php
require_once 'config/config.php';

$message = '';
$messageType = ''; // 'success' or 'danger'
$valid_token = false;
$user_id = null;

// Get Token from URL
$token = $_GET['token'] ?? '';

if (!$token) {
    die("Invalid request.");
}

$database = new Database();
$db = $database->getConnection();

// Hash the token from URL to compare with DB
$token_hash = hash('sha256', $token);

// 1. Validate Token
$query = "SELECT id, full_name, reset_expires FROM users WHERE reset_token_hash = ?";
$stmt = $db->prepare($query);
$stmt->execute([$token_hash]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    // Check expiration
    if (strtotime($user['reset_expires']) > time()) {
        $valid_token = true;
        $user_id = $user['id'];
    } else {
        $message = "This password reset link has expired.";
        $messageType = 'danger';
    }
} else {
    $message = "Invalid password reset token.";
    $messageType = 'danger';
}

// 2. Handle Password Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $valid_token) {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password !== $confirm_password) {
        $message = "Passwords do not match.";
        $messageType = 'danger';
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Update password and Clear token
        $updateQuery = "UPDATE users SET password = ?, reset_token_hash = NULL, reset_expires = NULL WHERE id = ?";
        $updateStmt = $db->prepare($updateQuery);
        
        if ($updateStmt->execute([$hashed_password, $user_id])) {
            $message = "Password successfully updated! Redirecting to login...";
            $messageType = 'success';
            // Disable form
            $valid_token = false; 
            // Redirect after 3 seconds
            header("refresh:3;url=index.php");
        } else {
            $message = "Failed to update password. Please try again.";
            $messageType = 'danger';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password - AccrediTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand-blue: #031A38;
            --brand-blue-light: #0d2b52;
            --brand-gold: #f2c94c;
            --text-dark: #1f2937;
            --text-light: #6b7280;
            --input-bg: #f9fafb;
            --input-border: #e5e7eb;
        }
        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: url('assets/img/CICT.png') no-repeat center center fixed; 
            background-size: cover;
            position: relative;
        }
        body::before {
            content: "";
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(3, 26, 56, 0.5);
            backdrop-filter: blur(3px);
            z-index: -1;
        }
        .main-container {
            width: 100%;
            max-width: 450px; 
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            padding: 40px;
            text-align: center;
        }
        .logo-img {
            width: 80px;
            margin-bottom: 20px;
        }
        h2 { font-size: 24px; font-weight: 700; color: var(--text-dark); }
        .form-label { text-align: left; font-size: 12px; font-weight: 600; color: #374151; display: block; margin-bottom: 4px; }
        .form-control { padding: 10px 14px; border-radius: 10px; border: 1px solid var(--input-border); background-color: var(--input-bg); }
        .btn-action {
            background: var(--brand-blue); color: white; border: none; border-radius: 10px; padding: 12px;
            font-weight: 600; width: 100%; margin-top: 15px; transition: all 0.2s;
        }
        .btn-action:hover { background: var(--brand-blue-light); }
        .alert { border-radius: 10px; font-size: 14px; margin-bottom: 20px; }
        .alert-success { background-color: #ecfdf5; color: #065f46; border: 1px solid #10b981; }
        .alert-danger { background-color: #fef2f2; color: #991b1b; border: 1px solid #ef4444; }
    </style>
</head>
<body>

    <div class="main-container">
        <img src="assets/img/accreditrack_official_logo.png" alt="Logo" class="logo-img">
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
            <?php if ($messageType === 'danger'): ?>
                <a href="index.php" class="btn btn-outline-secondary w-100">Return to Login</a>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($valid_token): ?>
            <h2 class="mb-4">Set New Password</h2>
            <form method="POST">
                <div class="mb-3 text-start">
                    <label class="form-label">New Password</label>
                    <input type="password" name="password" class="form-control" required placeholder="Enter new password">
                </div>
                <div class="mb-3 text-start">
                    <label class="form-label">Confirm Password</label>
                    <input type="password" name="confirm_password" class="form-control" required placeholder="Confirm new password">
                </div>
                <button type="submit" class="btn-action">Reset Password</button>
            </form>
        <?php endif; ?>
    </div>

</body>
</html>