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
        $stmt = $db->prepare("
                SELECT 
                    c.id,
                    c.license_id,
                    c.comment,
                    c.created_at,
                    c.commented_by,
                    u.full_name as user_name
                FROM comments c
                LEFT JOIN users u ON c.commented_by = u.full_name
                WHERE c.license_id = :license_id 
                ORDER BY c.created_at ASC
            ");
        $stmt->execute([':license_id' => $licenseId]);
        $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format the timestamps
        foreach ($comments as &$comment) {
            if ($comment['created_at']) {
                $comment['formatted_date'] = date('M d, Y h:i A', strtotime($comment['created_at']));
            }
        }

        echo json_encode($comments);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
