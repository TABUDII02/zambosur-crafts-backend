<?php
require_once 'config.php';
$conn = getDBConnection();

$new_password = password_hash('admin123', PASSWORD_DEFAULT);
$username = 'admin';

$stmt = $conn->prepare("UPDATE admins SET password_hash = ? WHERE username = ?");
$stmt->bind_param("ss", $new_password, $username);

if ($stmt->execute()) {
    echo "Password successfully reset to: admin123";
} else {
    echo "Error: " . $stmt->error;
}
?>