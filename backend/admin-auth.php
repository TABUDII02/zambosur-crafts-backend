<?php
/**
 * Admin Authentication Helper for ZamboSur Crafts Admin Panel
 */

require_once 'config.php';

/**
 * Start admin session safely
 */
function startAdminSession() {
    if (session_status() === PHP_SESSION_NONE) {
        // Set secure session cookie parameters if possible
        session_start();
    }
}

/**
 * Check if user is logged in as admin
 * @return bool
 */
function isAdminLoggedIn() {
    startAdminSession();
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

/**
 * Login admin
 * @param string $username
 * @param string $password
 * @return array
 */
function adminLogin($username, $password) {
    startAdminSession();
    
    if (empty($username) || empty($password)) {
        return ['success' => false, 'error' => 'Username and password required'];
    }
    
    $conn = getDBConnection();
    // Force UTF-8 to ensure symbols in hashes don't get mangled
    $conn->set_charset("utf8mb4");

    $stmt = $conn->prepare("SELECT id, username, password_hash FROM admins WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Use trim to remove any accidental whitespace from the database or the input
        $inputPassword = trim($password);
        $hashedPassword = trim($row['password_hash']);

        if (password_verify($inputPassword, $hashedPassword)) {
            $_SESSION['admin_id'] = $row['id'];
            $_SESSION['admin_username'] = $row['username'];
            $stmt->close();
            $conn->close();
            return ['success' => true, 'message' => 'Login successful'];
        } else {
            $stmt->close();
            $conn->close();
            return ['success' => false, 'error' => 'Invalid password'];
        }
    }
    
    $stmt->close();
    $conn->close();
    return ['success' => false, 'error' => 'Admin not found'];
}

/**
 * Logout admin
 */
function adminLogout() {
    startAdminSession();
    $_SESSION = array(); // Clear session variables
    session_destroy();
    return ['success' => true, 'message' => 'Logged out successfully'];
}