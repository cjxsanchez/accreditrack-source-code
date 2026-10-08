<?php
/**
 * Authentication Middleware
 * Handles user authentication and authorization
 */

class Auth {
    private $db;

    public function __construct() {
        $this->db = getDB();
    }

    /**
     * Authenticate user with username and password
     * @param string $username
     * @param string $password
     * @return array|false User data if successful, false otherwise
     */
    public function login($username, $password) {
        try {
            $query = "SELECT * FROM users WHERE username = ? AND status = 'active'";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && verifyPassword($password, $user['password'])) {
                // Update last login
                $updateQuery = "UPDATE users SET last_login = NOW() WHERE id = ?";
                $updateStmt = $this->db->prepare($updateQuery);
                $updateStmt->execute([$user['id']]);

                // Set session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['login_time'] = time();

                logMessage('User login successful', 'info', ['user_id' => $user['id'], 'username' => $username]);
                return $user;
            }

            logMessage('Login attempt failed', 'warning', ['username' => $username]);
            return false;

        } catch (Exception $e) {
            logMessage('Login error: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Register new user
     * @param string $username
     * @param string $email
     * @param string $password
     * @param string $full_name
     * @param string $role (admin or staff)
     * @return array|false New user data if successful, false otherwise
     */
    public function register($username, $email, $password, $full_name, $role = 'staff') {
        try {
            // Check if user exists
            $checkQuery = "SELECT id FROM users WHERE username = ? OR email = ?";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->execute([$username, $email]);

            if ($checkStmt->fetch()) {
                return false; // User already exists
            }

            // Hash password
            $hashedPassword = hashPassword($password);

            // Insert user
            $query = "INSERT INTO users (username, email, password, full_name, role, status, created_at) 
                     VALUES (?, ?, ?, ?, ?, 'active', NOW())";
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([$username, $email, $hashedPassword, $full_name, $role]);

            if ($result) {
                $userId = $this->db->lastInsertId();
                logMessage('User registered successfully', 'info', ['user_id' => $userId, 'username' => $username]);
                
                return [
                    'id' => $userId,
                    'username' => $username,
                    'email' => $email,
                    'full_name' => $full_name,
                    'role' => $role
                ];
            }

            return false;

        } catch (Exception $e) {
            logMessage('Registration error: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Get user by ID
     * @param int $userId
     * @return array|false
     */
    public function getUserById($userId) {
        try {
            $query = "SELECT id, username, email, full_name, role, status, created_at, last_login FROM users WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$userId]);
            return $stmt->fetch();

        } catch (Exception $e) {
            logMessage('Get user error: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Update user password
     * @param int $userId
     * @param string $newPassword
     * @return bool
     */
    public function updatePassword($userId, $newPassword) {
        try {
            $hashedPassword = hashPassword($newPassword);
            $query = "UPDATE users SET password = ? WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([$hashedPassword, $userId]);

            if ($result) {
                logMessage('Password updated', 'info', ['user_id' => $userId]);
            }

            return $result;

        } catch (Exception $e) {
            logMessage('Update password error: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Logout user
     * @return bool
     */
    public function logout() {
        $userId = $_SESSION['user_id'] ?? null;
        
        session_destroy();
        
        if ($userId) {
            logMessage('User logout successful', 'info', ['user_id' => $userId]);
        }
        
        return true;
    }

    /**
     * Check if user is admin
     * @param int $userId
     * @return bool
     */
    public function isAdmin($userId) {
        try {
            $query = "SELECT role FROM users WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            return $user && $user['role'] === 'admin';

        } catch (Exception $e) {
            logMessage('Is admin check error: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Check if user is staff
     * @param int $userId
     * @return bool
     */
    public function isStaff($userId) {
        try {
            $query = "SELECT role FROM users WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            return $user && $user['role'] === 'staff';

        } catch (Exception $e) {
            logMessage('Is staff check error: ' . $e->getMessage(), 'error');
            return false;
        }
    }
}

?>
