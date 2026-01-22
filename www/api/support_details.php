<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once('../config/database.php');

// Simple auth check
session_start();
if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$db = getDatabaseConnection();

if (!$db) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

if ($method === 'GET') {
    $searchTerm = isset($_GET['search_term']) ? trim($_GET['search_term']) : (isset($_GET['serial_id']) ? trim($_GET['serial_id']) : '');
    $searchType = isset($_GET['search_type']) ? trim($_GET['search_type']) : 'any';

    if (empty($searchTerm)) {
        echo json_encode(['success' => false, 'message' => 'Search term is required']);
        exit;
    }

    try {
        if ($searchType === 'serial_id') {
            $stmt = $db->prepare("SELECT * FROM license_details WHERE system_serialid = :term LIMIT 1");
        } elseif ($searchType === 'location_code') {
            $stmt = $db->prepare("SELECT * FROM license_details WHERE location_code = :term LIMIT 1");
        } else {
            // Fallback / Generic search
            $stmt = $db->prepare("SELECT * FROM license_details WHERE system_serialid = :term OR location_code = :term LIMIT 1");
        }

        $stmt->execute([':term' => $searchTerm]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data) {
            // Fetch latest status date from history
            $stmtHist = $db->prepare("SELECT date FROM device_status WHERE license_id = :id ORDER BY id DESC LIMIT 1");
            $stmtHist->execute([':id' => $data['id']]);
            $hist = $stmtHist->fetch(PDO::FETCH_ASSOC);
            $data['status_date'] = $hist ? $hist['date'] : '';

            // Fetch latest support remark
            $stmtRem = $db->prepare("SELECT remark FROM support_remarks WHERE license_id = :id ORDER BY created_at DESC LIMIT 1");
            $stmtRem->execute([':id' => $data['id']]);
            $remarkRow = $stmtRem->fetch(PDO::FETCH_ASSOC);
            $data['latest_support_remark'] = $remarkRow ? $remarkRow['remark'] : '';

            // Clean up some date fields for frontend (if needed)
            if (!empty($data['created_on']))
                $data['created_on'] = date('Y-m-d', strtotime($data['created_on']));
            if (!empty($data['licensee_amctill']))
                $data['licensee_amctill'] = date('Y-m-d', strtotime($data['licensee_amctill']));
            if (!empty($data['licensee_validtill']))
                $data['licensee_validtill'] = date('Y-m-d', strtotime($data['licensee_validtill']));

            echo json_encode(['success' => true, 'data' => $data]);
        } else {
            echo json_encode(['success' => false, 'message' => "Record not found for '{$searchTerm}'"]);
        }

    } catch (PDOException $e) {
        error_log("Database Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }

} elseif ($method === 'POST') {
    // Update specifields only
    $id = isset($_POST['id']) ? $_POST['id'] : '';

    // Allowed fields to update
    $deviceStatus = isset($_POST['DeviceStatus']) ? trim($_POST['DeviceStatus']) : '';
    $statusDate = isset($_POST['StatusDate']) ? trim($_POST['StatusDate']) : '';
    $testedBy = isset($_POST['TestedBy']) ? trim($_POST['TestedBy']) : '';
    $comment = isset($_POST['Comment']) ? trim($_POST['Comment']) : '';
    $supportRemarks = isset($_POST['SupportRemarks']) ? trim($_POST['SupportRemarks']) : '';

    $userFullName = $_SESSION['full_name'] ?? 'Unknown';

    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'License ID is missing']);
        exit;
    }

    try {
        $db->beginTransaction();

        // 1. Update license_details 
        // Only: DeviceStatus, TestedBy, Comment
        // NOTE: support_remarks is NOT saved to license_details as per requirement
        $sql = "UPDATE license_details SET 
                device_status = :device_status, 
                tested_by = :tested_by, 
                comment = :comment 
                WHERE id = :id";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':device_status' => $deviceStatus,
            ':tested_by' => $testedBy,
            ':comment' => $comment,
            ':id' => $id
        ]);

        // 2. Handle Device Status History
        if (!empty($deviceStatus) && !empty($statusDate)) {
            // Append current time to date if missing
            $fullDate = (strpos($statusDate, ':') === false) ? $statusDate . ' ' . date('H:i:s') : $statusDate;

            $statusStmt = $db->prepare("INSERT INTO device_status (`license_id`, `status`, `date`, `user`) VALUES (:license_id, :status, :date, :user)");
            $statusStmt->execute([
                ':license_id' => $id,
                ':status' => $deviceStatus,
                ':date' => $fullDate,
                ':user' => $userFullName
            ]);
        }

        // 3. Handle Comments History
        if (!empty($comment)) {
            // Logic: Usually we check if it changed, but let's assume save means adding a log if provided
            // However, reusing licenses.php logic, we log it.
            $commentStmt = $db->prepare("INSERT INTO comments (license_id, comment, commented_by, created_at) VALUES (:license_id, :comment, :commented_by, NOW())");
            $commentStmt->execute([
                ':license_id' => $id,
                ':comment' => $comment,
                ':commented_by' => $userFullName
            ]);
        }

        // 4. Handle Support Remarks History
        // "Create a new table... and save the details in to that table"
        if (!empty($supportRemarks)) {
            $remarkStmt = $db->prepare("INSERT INTO support_remarks (license_id, remark, created_by, created_at) VALUES (:license_id, :remark, :created_by, NOW())");
            $remarkStmt->execute([
                ':license_id' => $id,
                ':remark' => $supportRemarks,
                ':created_by' => $userFullName
            ]);
        }

        $db->commit();
        echo json_encode(['success' => true]);

    } catch (PDOException $e) {
        $db->rollBack();
        error_log("Update Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Update failed: ' . $e->getMessage()]);
    }

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>