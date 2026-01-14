<?php
session_start();
header('Content-Type: application/json');

// Include database configuration
require_once('../config/database.php');

$username = $_POST['username'];
$password = $_POST['password'];

$db = getDatabaseConnection();

if (!$db) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
    exit;
}


$stmt = $db->prepare("SELECT id, username, password_hash, full_name, role FROM users WHERE username = :username LIMIT 1");
$stmt->execute(['username' => $username]);
$user = $stmt->fetch();

if (!$user) {
    echo json_encode([
        'success' => false,
        'message' => 'User does not exist'
    ]);
    exit;
}

if (password_verify($password, $user['password_hash'])) {
    // Prevent session fixation
    session_regenerate_id(true);
    $new_session_id = session_id();

    // Update user's current_session_id in database
    $updateStmt = $db->prepare("UPDATE users SET current_session_id = :session_id WHERE id = :id");
    $updateStmt->execute([
        'session_id' => $new_session_id,
        'id' => $user['id']
    ]);

    $_SESSION['id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['logged_in'] = true;

    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'data' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'role' => $user['role']
        ]
    ]);
} else {
    // Password_hash is incorrect
    echo json_encode([
        'success' => false,
        'message' => 'Incorrect Username or Password'
    ]);
}

?>