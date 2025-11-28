<?php
session_start();
header('Content-Type: application/json');

// Include database configuration
require_once('../config/database.php');

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
  echo json_encode([
    'success' => false,
    'message' => 'Unauthorized'
  ]);
  exit;
}

$db = getDatabaseConnection();

if (!$db) {
  echo json_encode([
    'success' => false,
    'message' => 'Database connection failed'
  ]);
  exit;
}

$user_id = isset($_POST['user_id']) ? $_POST['user_id'] : null;
$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

// Validation
if (empty($username) || empty($full_name)) {
  echo json_encode([
    'success' => false,
    'message' => 'Username and Full Name are required'
  ]);
  exit;
}

if (strlen($username) < 3) {
  echo json_encode([
    'success' => false,
    'message' => 'Username must be at least 3 characters'
  ]);
  exit;
}

if ((!$user_id && strlen($password) < 5) || ($user_id && !empty($password) && strlen($password) < 5)) {
  echo json_encode([
    'success' => false,
    'message' => 'Password must be at least 5 characters'
  ]);
  exit;
}

// try {
if ($user_id) {
  // Update existing user
  if (!empty($password)) {
    // Update with password
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare("UPDATE users SET username = :username, full_name = :full_name, password_hash = :password_hash, updated_at = NOW(), created_at = created_at WHERE id = :id");
    $stmt->execute([
      'username' => $username,
      'full_name' => $full_name,
      'password_hash' => $password_hash,
      'id' => $user_id
    ]);
  } else {
    // Update without password
    $stmt = $db->prepare("UPDATE users SET username = :username, full_name = :full_name, updated_at = NOW(), created_at = created_at WHERE id = :id");
    $stmt->execute([
      'username' => $username,
      'full_name' => $full_name,
      'id' => $user_id
    ]);
  }
  $message = 'User updated successfully';
} else {
  // Create new user
  // Check if username already exists
  $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
  $stmt->execute(['username' => $username]);
  if ($stmt->fetchColumn() > 0) {
    echo json_encode([
      'success' => false,
      'message' => 'Username already exists'
    ]);
    exit;
  }

  $password_hash = password_hash($password, PASSWORD_DEFAULT);
  $stmt = $db->prepare("INSERT INTO users (username, full_name, password_hash) VALUES (:username, :full_name, :password_hash)");
  $stmt->execute([
    'username' => $username,
    'full_name' => $full_name,
    'password_hash' => $password_hash
  ]);
  $message = 'User added successfully';
}

echo json_encode([
  'success' => true,
  'message' => $message
]);

// } catch (PDOException $e) {
//   echo json_encode([
//     'success' => false,
//     'message' => 'Database error: ' . $e->getMessage()
//   ]);
// }
?>