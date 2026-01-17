<?php
session_start();
header('Content-Type: application/json');

require_once('../config/database.php');

// Check login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$serialId = isset($input['serial_id']) ? trim($input['serial_id']) : '';

if ($serialId === '') {
    echo json_encode(['success' => false, 'message' => 'Serial ID is required']);
    exit;
}

$db = getDatabaseConnection();
if (!$db) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM license_details WHERE system_serialid = :serial_id");
    $stmt->execute([':serial_id' => $serialId]);
    $count = $stmt->fetchColumn();

    if ($count > 0) {
        echo json_encode(['success' => true, 'exists' => true, 'message' => 'Serial ID already exists']);
    } else {
        echo json_encode(['success' => true, 'exists' => false, 'message' => 'Serial ID is available']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
