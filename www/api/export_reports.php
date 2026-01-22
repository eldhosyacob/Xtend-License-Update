<?php
session_start();

require_once('../config/database.php');

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo "Unauthorized";
    exit;
}

$db = getDatabaseConnection();

if (!$db) {
    echo "Database connection failed";
    exit;
}

try {
    // Get search parameters
    $serialId = isset($_GET['serial_id']) ? trim($_GET['serial_id']) : '';
    $uniqueId = isset($_GET['unique_id']) ? trim($_GET['unique_id']) : '';
    $locationCode = isset($_GET['location_code']) ? trim($_GET['location_code']) : '';
    $status = isset($_GET['status']) ? trim($_GET['status']) : '';
    $clientName = isset($_GET['client_name']) ? trim($_GET['client_name']) : '';
    $fromDate = isset($_GET['from_date']) ? trim($_GET['from_date']) : '';
    $toDate = isset($_GET['to_date']) ? trim($_GET['to_date']) : '';

    // Build WHERE clause
    $whereConditions = [];
    $params = [];

    if ($serialId !== '') {
        $whereConditions[] = "system_serialid LIKE :serial_id";
        $params[':serial_id'] = '%' . $serialId . '%';
    }

    if ($uniqueId !== '') {
        $whereConditions[] = "system_uniqueid LIKE :unique_id";
        $params[':unique_id'] = '%' . $uniqueId . '%';
    }

    if ($locationCode !== '') {
        $whereConditions[] = "location_code LIKE :location_code";
        $params[':location_code'] = '%' . $locationCode . '%';
    }

    if ($fromDate !== '' && $toDate !== '') {
        $fromDateClean = str_replace('-', '', $fromDate);
        $toDateClean = str_replace('-', '', $toDate);
        $whereConditions[] = "created_on >= :from_date AND created_on <= :to_date";
        $params[':from_date'] = $fromDateClean;
        $params[':to_date'] = $toDateClean;
    }

    if ($clientName !== '') {
        $whereConditions[] = "client_name = :client_name";
        $params[':client_name'] = $clientName;
    }

    if ($status !== '') {
        $today = date('Ymd');
        if ($status === 'active') {
            $whereConditions[] = "licensee_validtill >= :today";
            $params[':today'] = $today;
        } elseif ($status === 'expired') {
            $whereConditions[] = "(licensee_validtill < :today AND licensee_validtill != '' AND licensee_validtill IS NOT NULL)";
            $params[':today'] = $today;
        } elseif ($status === 'expiring') {
            $next30Days = date('Ymd', strtotime('+30 days'));
            $whereConditions[] = "(licensee_validtill >= :today AND licensee_validtill <= :next30Days)";
            $params[':today'] = $today;
            $params[':next30Days'] = $next30Days;
        }
    }

    // Filter by Device Status
    $deviceStatusVal = isset($_GET['device_status_val']) ? trim($_GET['device_status_val']) : '';
    if ($deviceStatusVal !== '') {
        $whereConditions[] = "COALESCE((SELECT status FROM device_status WHERE license_id = license_details.id ORDER BY id DESC LIMIT 1), device_status) = :device_status_val";
        $params[':device_status_val'] = $deviceStatusVal;
    }

    // Restrict Limited Access users from viewing Sharekhan clients
    $userRole = isset($_SESSION['role']) ? $_SESSION['role'] : '';
    if ($userRole === 'Limited Access') {
        $whereConditions[] = "client_name != 'Sharekhan'";
    }

    $whereSql = '';
    if (!empty($whereConditions)) {
        $whereSql = 'WHERE ' . implode(' AND ', $whereConditions);
    }

    // Fetch data for export
    $sql = "SELECT created_on, client_name, location_name, location_code, board_type, licensee_validtill, system_serialid, system_uniqueid, engine_maxports as total_ports, 
            device_id1, device_id2, device_id3, device_id4
            FROM license_details 
            $whereSql
            ORDER BY id DESC";

    $stmt = $db->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="report_export_' . date('Y-m-d_H-i-s') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');

    // Add CSV columns headers
    fputcsv($output, [
        'Sl No',
        'Created On',
        'Client Name',
        'Location Name',
        'Location Code',
        'Board Type',
        'License Validity',
        'Serial ID',
        'Unique ID',
        'Total Ports',
        'Device ID'
    ]);

    $cnt = 1;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Concatenate Device IDs
        $deviceIds = [];
        if (!empty($row['device_id1']))
            $deviceIds[] = $row['device_id1'];
        if (!empty($row['device_id2']))
            $deviceIds[] = $row['device_id2'];
        if (!empty($row['device_id3']))
            $deviceIds[] = $row['device_id3'];
        if (!empty($row['device_id4']))
            $deviceIds[] = $row['device_id4'];
        $deviceIdStr = implode(', ', $deviceIds);

        // Format Total Ports - extract M= value if present
        $totalPorts = $row['total_ports'];
        if (preg_match('/M=(\d+)/i', $totalPorts, $matches)) {
            $totalPorts = $matches[1];
        }

        fputcsv($output, [
            $cnt++,
            $row['created_on'],
            $row['client_name'],
            $row['location_name'],
            $row['location_code'],
            $row['board_type'],
            $row['licensee_validtill'],
            $row['system_serialid'],
            $row['system_uniqueid'],
            $totalPorts,
            $deviceIdStr
        ]);
    }

    fclose($output);

} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}
?>