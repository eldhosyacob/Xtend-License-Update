<?php
// Include this file at the top of pages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id']) || !isset($_SESSION['username'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Check for single session enforcement
require_once __DIR__ . '/database.php';
$db = getDatabaseConnection();

if ($db) {
    try {
        $stmt = $db->prepare("SELECT current_session_id FROM users WHERE id = :id");
        $stmt->execute(['id' => $_SESSION['id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result && $result['current_session_id'] !== session_id()) {
            // Session mismatch - another login occurred
            session_destroy();
            header('Location: index.php?error=session_expired');
            exit;
        }
    } catch (PDOException $e) {
        // Evaluate if this should be a hard failure or just log it
        error_log("Session check error: " . $e->getMessage());
    }
}
