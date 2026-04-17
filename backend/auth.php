<?php
// backend/auth.php
header("Cross-Origin-Opener-Policy: same-origin-allow-popups");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function handleSocialLogin($data) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $email = $data['email'] ?? '';
    $name = $data['name'] ?? 'Valued Customer';

    if (empty($email)) {
        echo json_encode(['success' => false, 'error' => 'Email is required']);
        return;
    }

    $conn = getDBConnection();
    
    // 1. Find the user in the 'customers' table by email
    $stmt = $conn->prepare("SELECT id, first_name FROM customers WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user) {
        // 2. If they exist, set the session ID your profile page expects
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['first_name'];
        $_SESSION['user_email'] = $email;
    } else {
        // 3. If they don't exist, you might want to create them first 
        // OR for your capstone demo, return an error that the account doesn't exist.
        echo json_encode(['success' => false, 'error' => 'No account found for this email.']);
        return;
    }

    echo json_encode([
        'success' => true, 
        'message' => 'Login successful',
        'user' => [
            'id' => $_SESSION['user_id'],
            'email' => $_SESSION['user_email'],
            'name' => $_SESSION['user_name']
        ]
    ]);
    $conn->close();
}