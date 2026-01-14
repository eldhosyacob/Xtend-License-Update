<?php
require_once('../config/database.php');

session_start();

if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $licenseId = $_GET['license_id'] ?? null;

    if (!$licenseId) {
        http_response_code(400);
        echo json_encode(['error' => 'License ID is required']);
        exit;
    }

    $db = getDatabaseConnection();
    if ($db) {
        try {
            $stmt = $db->prepare("
                    SELECT 
                        id,
                        license_id,
                        status,
                        date,
                        user
                    FROM device_status
                    WHERE license_id = :license_id 
                    ORDER BY date ASC
                ");
            $stmt->execute([':license_id' => $licenseId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Format the timestamps
            foreach ($rows as &$row) {
                if ($row['date']) {
                    $row['formatted_date'] = date('M d, Y h:i A', strtotime($row['date']));
                }
            }

            echo json_encode($rows);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database query failed: ' . $e->getMessage()]);
        }
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>