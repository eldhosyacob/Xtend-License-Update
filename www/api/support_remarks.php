<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once('../config/database.php');

session_start();
if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = getDatabaseConnection();
if (!$db) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$licenseId = $_GET['license_id'] ?? '';

if (empty($licenseId)) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $db->prepare("SELECT remark, created_by, created_at FROM support_remarks WHERE license_id = :id ORDER BY created_at ASC");
    $stmt->execute([':id' => $licenseId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format dates
    foreach ($rows as &$row) {
        $row['formatted_date'] = date('d-M-Y h:i A', strtotime($row['created_at']));
    }

    echo json_encode($rows);
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    echo json_encode([]);
}
?>