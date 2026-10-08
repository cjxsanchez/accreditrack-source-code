<?php
// --- 1. LOAD PHPMAILER MANUALLY ---
require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once 'config/config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if (isAdmin()) {
        redirect('admin/dashboard.php');
    } else {
        redirect('staff/dashboard.php');
    }
}

$login_error = '';
$register_error = '';
$register_success = '';
$forgot_success = ''; 
$active_view = 'login'; 

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $database = new Database();
    $db = $database->getConnection();

    // --- LOGIN LOGIC ---
    if (isset($_POST['action']) && $_POST['action'] === 'login') {
        $username = sanitize($_POST['username']);
        $password = $_POST['password'];
        
        $query = "SELECT * FROM users WHERE username = ? AND status = 'active'";
        $stmt = $db->prepare($query);
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            
            if ($user['role'] === 'admin') {
                redirect('admin/dashboard.php');
            } else {
                redirect('staff/dashboard.php');
            }
        } else {
            $login_error = 'Invalid username or password, or account is not active.';
        }
    } 
    
    // --- REGISTER LOGIC ---
    elseif (isset($_POST['action']) && $_POST['action'] === 'register') {
        $active_view = 'register'; 
        $username = sanitize($_POST['username']);
        $email = sanitize($_POST['email']);
        $full_name = sanitize($_POST['full_name']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        if ($password !== $confirm_password) {
            $register_error = 'Passwords do not match.';
        } else {
            $query = "SELECT id FROM users WHERE username = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$username]);
            
            if ($stmt->rowCount() > 0) {
                $register_error = 'Username already exists.';
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $query = "INSERT INTO users (username, email, full_name, password, role, status) VALUES (?, ?, ?, ?, 'staff', 'pending')";
                $stmt = $db->prepare($query);
                
                if ($stmt->execute([$username, $email, $full_name, $hashed_password])) {
                    $register_success = 'Registration successful! Please wait for admin approval.';
                } else {
                    $register_error = 'Registration failed. Please try again.';
                }
            }
        }
    }

    // --- FORGOT PASSWORD LOGIC ---
    elseif (isset($_POST['action']) && $_POST['action'] === 'forgot_password') {
        $active_view = 'forgot'; 
        $email = sanitize($_POST['email']);

        $query = "SELECT id, full_name FROM users WHERE email = ? AND status = 'active'";
        $stmt = $db->prepare($query);
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $token_hash = hash('sha256', $token);
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $update = "UPDATE users SET reset_token_hash = ?, reset_expires = ? WHERE id = ?";
            $ustmt = $db->prepare($update);
            
            if ($ustmt->execute([$token_hash, $expiry, $user['id']])) {
                
                // Determine Link
                // $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                // $host = $_SERVER['HTTP_HOST'];
                // $path = dirname($_SERVER['PHP_SELF']); 
                // $resetLink = "$protocol://$host$path/reset_password.php?token=$token";

                // Determine Link
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$path = dirname($_SERVER['PHP_SELF']);

// FIX: Convert Windows backslashes (\) to Web forward slashes (/)
$path = str_replace('\\', '/', $path);

// FIX: Remove trailing slash if it exists (prevents double //)
$path = rtrim($path, '/');

$resetLink = "$protocol://$host$path/reset_password.php?token=$token";

                // --- 2. SEND REAL EMAIL VIA PHPMAILER ---
                $mail = new PHPMailer(true);

                try {
                    // Server settings
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    // =========================================================
                    // TODO: EDIT THESE TWO LINES WITH YOUR DETAILS
                    $mail->Username   = 'johncarlsanchez06@gmail.com'; 
                    $mail->Password   = 'edeu wcpe exyk ncfg'; 
                    // =========================================================
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;

                    // Recipients
                    $mail->setFrom('no-reply@accreditrack.com', 'AccrediTrack System');
                    $mail->addAddress($email);

                    // Content
                    $mail->isHTML(true);
                    $mail->Subject = 'AccrediTrack Password Reset';
                    $mail->Body    = "
                        <div style='font-family: Arial, sans-serif; padding: 20px; color: #333;'>
                            <h2 style='color: #031A38;'>Password Reset Request</h2>
                            <p>Hello {$user['full_name']},</p>
                            <p>We received a request to reset your password. Click the button below to proceed:</p>
                            <p>
                                <a href='$resetLink' style='background-color: #031A38; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Reset Password</a>
                            </p>
                            <p>Or copy this link: <br> <small>$resetLink</small></p>
                            <p>This link expires in 1 hour.</p>
                        </div>
                    ";

                    $mail->send();
                    
                    // --- 3. SAFETY NET: LOG TO FILE TOO (Just in case network fails during demo) ---
                    $logMessage = "BACKUP LOG - To: $email | Link: $resetLink\n";
                    file_put_contents('email_debug.txt', $logMessage, FILE_APPEND);

                } catch (Exception $e) {
                    // If email fails, we still want the UI to look successful to the user
                    // But you can check error_log for details
                    error_log("Mailer Error: {$mail->ErrorInfo}");
                }
            }
        }

        // Always show success message for security
        $forgot_success = 'If an account exists with that email, we have sent password reset instructions.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AccrediTrack</title>
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

        /* Use 'fixed' to prevent background movement during scroll/animations */
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
            overflow-x: hidden;
        }

        /* Overlay */
        body::before {
            content: "";
            position: fixed; /* Fixed here too */
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(3, 26, 56, 0.5);
            backdrop-filter: blur(3px);
            z-index: -1;
        }

        /* Corner Logos */
        .corner-logo {
            position: absolute;
            top: 30px;
            width: 100px;
            height: auto;
            z-index: 10;
        }
        .logo-top-left { left: 30px; }
        .logo-top-right { right: 30px; }

        /* --- FLIP CONTAINER SETUP --- */
        .main-container {
            position: relative;
            width: 100%;
            max-width: 900px; 
            height: 620px; 
            perspective: 1000px;
            margin: 0 auto;
        }

        .flipper {
            position: relative;
            width: 100%;
            height: 100%;
            transition: transform 0.8s cubic-bezier(0.4, 0.2, 0.2, 1);
            transform-style: preserve-3d;
        }

        .main-container.show-register .flipper {
            transform: rotateY(180deg);
        }

        /* Common Face Styles */
        .form-panel {
            position: absolute; 
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            backface-visibility: hidden;
            -webkit-backface-visibility: hidden;
            border-radius: 20px; 
            background: white;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2); 
            display: flex;
            overflow: hidden;
        }

        /* Front Face */
        .login-face {
            z-index: 2;
            transform: rotateY(0deg);
        }

        /* Back Face */
        .register-face {
            transform: rotateY(180deg);
        }

        /* --- Internal Panel Layout --- */
        .panel-left {
            flex: 1;
            background: linear-gradient(180deg, var(--brand-blue) 0%, #052145 100%);
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 40px 30px; 
            text-align: center;
        }

        .panel-right {
            flex: 1.3;
            background: white;
            padding: 40px 50px; 
            display: flex;
            flex-direction: column;
            justify-content: center;
            overflow-y: auto; 
        }

        .panel-left img {
            width: 160px; 
            margin-bottom: 20px;
            box-shadow: 0 0 40px 30px var(--brand-blue);
            background-color: var(--brand-blue);
            border-radius: 50%;
        }

        .panel-left h1 {
            font-size: 26px; 
            font-weight: 700;
            color: var(--brand-gold);
            letter-spacing: -0.5px;
            margin: 15px 0 12px 0;
        }

        .panel-left p {
            font-size: 14px;
            font-weight: 300;
            line-height: 1.6;
            color: rgba(255, 255, 255, 0.85);
            max-width: 90%;
        }

        .panel-right h2 {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .panel-sub {
            color: var(--text-light);
            font-size: 13px;
            margin-bottom: 25px;
        }

        .form-label {
            font-size: 12px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 4px;
            display: block;
        }

        .form-control {
            padding: 10px 14px; 
            border-radius: 10px;
            border: 1px solid var(--input-border);
            background-color: var(--input-bg);
            font-size: 14px;
            transition: all 0.2s ease-in-out;
            color: var(--text-dark);
            width: 100%;
        }

        .form-control:focus {
            background-color: white;
            border-color: var(--brand-blue);
            box-shadow: 0 0 0 4px rgba(3, 26, 56, 0.08);
            outline: none;
        }

        .btn-action {
            background: var(--brand-blue);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            font-size: 14px;
            letter-spacing: 0.3px;
            margin-top: 10px;
            transition: all 0.2s ease;
            box-shadow: 0 4px 6px rgba(3, 26, 56, 0.1);
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-action:hover {
            background: var(--brand-blue-light);
            transform: translateY(-1px);
            box-shadow: 0 6px 12px rgba(3, 26, 56, 0.15);
        }

        .switch-text {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
            color: var(--text-light);
        }

        .switch-text a {
            color: var(--brand-blue);
            font-weight: 600;
            text-decoration: none;
            transition: color 0.2s;
            cursor: pointer;
        }

        .switch-text a:hover {
            color: var(--brand-blue-light);
            text-decoration: underline;
        }
        
        .forgot-link {
            display: block;
            text-align: right;
            font-size: 12px;
            color: var(--brand-blue);
            margin-top: 5px;
            text-decoration: none;
            font-weight: 500;
        }
        .forgot-link:hover {
            text-decoration: underline;
        }

        .alert {
            border: none;
            border-radius: 10px;
            font-size: 13px;
            padding: 12px 14px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            transition: opacity 0.5s ease-out; 
        }
        .alert-success { background-color: #ecfdf5; color: #065f46; border-left: 4px solid #10b981; }
        .alert-danger { background-color: #fef2f2; color: #991b1b; border-left: 4px solid #ef4444; }
        .alert i { margin-right: 10px; font-size: 16px; }

        /* Helpers for showing/hiding forms inside login face */
        .hidden-form { display: none; }
        .fade-in { animation: fadeIn 0.4s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        /* Mobile Responsive */
        @media (max-width: 900px) {
            .main-container { max-width: 500px; height: 800px; } 
            .form-panel { flex-direction: column; }
            .panel-left { padding: 30px 20px; flex: 0 0 auto; height: 250px; }
            .panel-left img { width: 100px; box-shadow: 0 0 30px 20px var(--brand-blue); }
            .panel-right { padding: 30px; flex: 1; overflow-y: visible; }
            .corner-logo { width: 70px; top: 20px; }
            .logo-top-left { left: 20px; }
            .logo-top-right { right: 20px; }
        }
    </style>
</head>
<body>
    <img src="assets/img/CatSU_logo.png" alt="Left Corner Logo" class="corner-logo logo-top-left">
    <img src="assets/images/CICT.png" alt="Right Corner Logo" class="corner-logo logo-top-right">

    <div class="main-container <?php echo ($active_view === 'register') ? 'show-register' : ''; ?>" id="flipContainer">
        <div class="flipper">
            
            <div class="form-panel login-face">
                <div class="panel-left">
                    <img src="assets/img/accreditrack_official_logo.png" alt="AccrediTrack Logo">
                    <h1>AccrediTrack</h1>
                    <p>Streamline your accreditation journey with precision and ease.</p>
                </div>
                <div class="panel-right">
                    
                    <div id="loginFormWrapper" class="<?php echo ($active_view === 'forgot') ? 'hidden-form' : ''; ?>">
                        <div>
                            <h2>Welcome!</h2>
                            <p class="panel-sub">Please enter your details to sign in.</p>
                        </div>
                        
                        <?php if ($login_error): ?>
                            <div class="alert alert-danger auto-dismiss">
                                <i class="fas fa-exclamation-circle"></i>
                                <div><?php echo $login_error; ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="login">
                            <div class="mb-4">
                                <label class="form-label">Username</label>
                                <input type="text" name="username" class="form-control" required placeholder="Enter your username">
                            </div>
                            <div class="mb-4">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" class="form-control" required placeholder="Enter your password">
                                <a href="#" class="forgot-link" onclick="toggleForgot(true); return false;">Forgot password?</a>
                            </div>
                            <button type="submit" class="btn-action">
                                Sign In <i class="fas fa-arrow-right ms-2" style="font-size: 0.9em;"></i>
                            </button>
                        </form>

                        <div class="switch-text">
                            Don't have an account? <a onclick="toggleFlip('register')">Create an account</a>
                        </div>
                    </div>

                    <div id="forgotFormWrapper" class="<?php echo ($active_view === 'forgot') ? 'fade-in' : 'hidden-form'; ?>">
                        <div>
                            <h2>Reset Password</h2>
                            <p class="panel-sub">Enter your email to receive a reset link.</p>
                        </div>

                        <?php if ($forgot_success): ?>
                            <div class="alert alert-success auto-dismiss">
                                <i class="fas fa-check-circle"></i>
                                <div><?php echo $forgot_success; ?></div>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <input type="hidden" name="action" value="forgot_password">
                            <div class="mb-4">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="email" class="form-control" required placeholder="name@example.com">
                            </div>
                            <button type="submit" class="btn-action">
                                Send Reset Link <i class="fas fa-paper-plane ms-2" style="font-size: 0.9em;"></i>
                            </button>
                        </form>

                        <div class="switch-text">
                            <a onclick="toggleForgot(false)">Back to Sign In</a>
                        </div>
                    </div>

                </div>
            </div>

            <div class="form-panel register-face">
                <div class="panel-left">
                    <img src="assets/img/accreditrack_official_logo.png" alt="AccrediTrack Logo">
                    <h1>AccrediTrack</h1>
                    <p>Join us to streamline your accreditation workflow efficiently.</p>
                </div>
                <div class="panel-right">
                    <div>
                        <h2>Create Account</h2>
                        <p class="panel-sub">Please fill in your details to register as faculty.</p>
                    </div>

                    <?php if ($register_success): ?>
                        <div class="alert alert-success auto-dismiss">
                            <i class="fas fa-check-circle"></i> 
                            <div><?php echo $register_success; ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($register_error): ?>
                        <div class="alert alert-danger auto-dismiss">
                            <i class="fas fa-exclamation-circle"></i> 
                            <div><?php echo $register_error; ?></div>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <input type="hidden" name="action" value="register">
                        <div class="row">
                            <div class="col-12 mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="full_name" class="form-control" placeholder="e.g. John Doe" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="email" class="form-control" placeholder="name@example.com" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" name="username" class="form-control" placeholder="Choose a username" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" class="form-control" placeholder="Create password" required>
                            </div>
                            <div class="col-md-6 mb-4">
                                <label class="form-label">Confirm Password</label>
                                <input type="password" name="confirm_password" class="form-control" placeholder="Confirm password" required>
                            </div>
                        </div>

                        <button type="submit" class="btn-action">
                            <span>Register Account</span>
                            <i class="fas fa-arrow-right" style="font-size: 0.9em;"></i>
                        </button>
                    </form>

                    <div class="switch-text">
                        Already have an account? <a onclick="toggleFlip('login')">Sign In here</a>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
        // Flip animation trigger
        function toggleFlip(direction) {
            const container = document.getElementById('flipContainer');
            
            // If switching to register, ensure login form is shown inside login-face
            if(direction === 'register') {
                 container.classList.add('show-register');
                 // Optional: Reset login face to login form after flip starts
                 setTimeout(() => toggleForgot(false), 300);
            } else {
                 container.classList.remove('show-register');
            }
        }

        // Toggle between Login Form and Forgot Password Form (Inside the Front Face)
        function toggleForgot(showForgot) {
            const loginWrapper = document.getElementById('loginFormWrapper');
            const forgotWrapper = document.getElementById('forgotFormWrapper');
            
            if (showForgot) {
                loginWrapper.style.display = 'none';
                forgotWrapper.style.display = 'block';
                forgotWrapper.classList.add('fade-in');
            } else {
                forgotWrapper.style.display = 'none';
                loginWrapper.style.display = 'block';
                loginWrapper.classList.add('fade-in');
            }
        }

        // Auto-dismiss alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                const alerts = document.querySelectorAll('.auto-dismiss');
                alerts.forEach(function(alert) {
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.remove();
                    }, 500); 
                });
            }, 5000); 
        });
    </script>
</body>
</html>