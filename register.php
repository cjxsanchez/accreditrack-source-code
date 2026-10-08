<?php
require_once 'config/config.php';

if (isLoggedIn()) {
    if (isAdmin()) {
        redirect('admin/dashboard.php');
    } else {
        redirect('staff/dashboard.php');
    }
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $full_name = sanitize($_POST['full_name']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "SELECT id FROM users WHERE username = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$username]);
        
        if ($stmt->rowCount() > 0) {
            $error = 'Username already exists.';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $query = "INSERT INTO users (username, email, full_name, password, role, status) VALUES (?, ?, ?, ?, 'staff', 'pending')";
            $stmt = $db->prepare($query);
            
            if ($stmt->execute([$username, $email, $full_name, $hashed_password])) {
                $success = 'Registration successful! Please wait for admin approval.';
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Registration - AccrediTrack</title>
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
            
            /* --- BACKGROUND IMAGE SETTINGS --- */
            background: url('assets/img/CICT.png') no-repeat center center fixed;
            background-size: cover;
            position: relative;
        }

        /* Dark Overlay Tint */
        body::before {
            content: "";
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(3, 26, 56, 0.5); /* Brand blue with 50% opacity */
            backdrop-filter: blur(3px);
            z-index: -1;
        }

        /* --- NEW: Corner Logos Styles --- */
        .corner-logo {
            position: absolute;
            top: 30px;
            width: 100px; /* Smaller size for corner logos */
            height: auto;
            z-index: 10; /* Ensure they are above the background overlay */
        }

        .logo-top-left {
            left: 30px;
        }

        .logo-top-right {
            right: 30px;
        }

        .register-wrapper {
            display: flex;
            background: white;
            border-radius: 24px;
            overflow: hidden;
            max-width: 1000px;
            width: 100%;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2); 
            min-height: 650px;
            position: relative;
            z-index: 1;
        }

        .register-left {
            flex: 1;
            background: linear-gradient(180deg, var(--brand-blue) 0%, #052145 100%);
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 60px 40px;
            text-align: center;
            position: relative;
            z-index: 1;
        }

        .register-left img {
            width: 180px;
            margin-bottom: 25px;
            box-shadow: 0 0 40px 30px var(--brand-blue);
            background-color: var(--brand-blue);
            border-radius: 50%;
        }

        .register-left h1 {
            font-size: 28px;
            font-weight: 700;
            color: var(--brand-gold);
            letter-spacing: -0.5px;
            margin-top: 15px;
            margin-bottom: 12px;
            z-index: 2;
        }

        .register-left p {
            font-size: 15px;
            font-weight: 300;
            line-height: 1.6;
            color: rgba(255, 255, 255, 0.85);
            max-width: 80%;
            z-index: 2;
        }

        .register-right {
            flex: 1.3;
            background: white;
            padding: 50px 60px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .register-right h2 {
            font-size: 26px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 10px;
            letter-spacing: -0.5px;
        }
        
        .register-sub {
            color: var(--text-light);
            font-size: 14px;
            margin-bottom: 30px;
        }

        .form-label {
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
            display: block;
        }

        .form-control {
            padding: 12px 16px;
            border-radius: 12px;
            border: 1px solid var(--input-border);
            background-color: var(--input-bg);
            font-size: 14px;
            transition: all 0.2s ease-in-out;
            color: var(--text-dark);
        }

        .form-control::placeholder {
            color: #9ca3af;
            font-weight: 400;
        }

        .form-control:focus {
            background-color: white;
            border-color: var(--brand-blue);
            box-shadow: 0 0 0 4px rgba(3, 26, 56, 0.08);
            outline: none;
        }

        .btn-register {
            background: var(--brand-blue);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 14px;
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

        .btn-register:hover {
            background: var(--brand-blue-light);
            transform: translateY(-1px);
            box-shadow: 0 6px 12px rgba(3, 26, 56, 0.15);
        }

        .register-footer {
            text-align: center;
            margin-top: 25px;
            font-size: 14px;
            color: var(--text-light);
        }

        .register-footer a {
            color: var(--brand-blue);
            font-weight: 600;
            text-decoration: none;
            transition: color 0.2s;
        }

        .register-footer a:hover {
            color: var(--brand-blue-light);
            text-decoration: underline;
        }

        .alert {
            border: none;
            border-radius: 12px;
            font-size: 14px;
            padding: 14px 16px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
        }

        .alert-success {
            background-color: #ecfdf5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .alert-danger {
            background-color: #fef2f2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }
        
        .alert i {
            margin-right: 10px;
            font-size: 16px;
        }

        @media (max-width: 900px) {
            /* --- NEW: Adjust corner logos on smaller screens --- */
            .corner-logo {
                width: 70px;
                top: 20px;
            }
            .logo-top-left { left: 20px; }
            .logo-top-right { right: 20px; }

            .register-wrapper {
                flex-direction: column;
                max-width: 500px;
            }
            .register-left {
                padding: 40px 20px;
                min-height: 220px;
            }
            .register-left img {
                width: 120px;
                box-shadow: 0 0 30px 20px var(--brand-blue);
            }
            .register-right {
                padding: 40px 30px;
            }
        }
    </style>
</head>
<body>
    <img src="assets/img/CatSU_logo.png" alt="Left Corner Logo" class="corner-logo logo-top-left">
    <img src="assets/images/CICT.png" alt="Right Corner Logo" class="corner-logo logo-top-right">

    <div class="register-wrapper">
        <div class="register-left">
            <img src="assets/img/accreditrack_official_logo.png" alt="AccrediTrack Logo">
            <h1>AccrediTrack</h1>
            <p>Join us to streamline your accreditation workflow efficiently.</p>
        </div>
        <div class="register-right">
            <div>
                <h2>Create Account</h2>
                <p class="register-sub">Please fill in your details to register as faculty.</p>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> 
                    <div><?php echo $success; ?></div>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> 
                    <div><?php echo $error; ?></div>
                </div>
            <?php endif; ?>

            <form method="POST">
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

                <button type="submit" class="btn-register">
                    <span>Register Account</span>
                    <i class="fas fa-arrow-right" style="font-size: 0.9em;"></i>
                </button>
            </form>

            <div class="register-footer">
                Already have an account? <a href="index.php">Sign In here</a>
            </div>
        </div>
    </div>
</body>
</html>