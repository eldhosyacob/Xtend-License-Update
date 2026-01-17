<?php
session_start();
header('Content-Type: application/json');

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

// Check if summary stats are requested
if (isset($_GET['summary']) && $_GET['summary'] === 'true') {
  try {
    $today = date('Ymd');
    $next30Days = date('Ymd', strtotime('+30 days'));

    // Prepare role-based condition
    $userRole = isset($_SESSION['role']) ? $_SESSION['role'] : '';
    $roleCondition = "";
    if ($userRole === 'Limited Access') {
      $roleCondition = " AND client_name != 'Sharekhan'";
    }

    // Active Licenses (valid_till >= today)
    $stmt = $db->prepare("SELECT COUNT(*) FROM license_details WHERE licensee_validtill >= :today" . $roleCondition);
    $stmt->bindValue(':today', $today);
    $stmt->execute();
    $activeCount = $stmt->fetchColumn();

    // Expired Licenses (valid_till < today)
    $stmt = $db->prepare("SELECT COUNT(*) FROM license_details WHERE licensee_validtill < :today AND licensee_validtill != '' AND licensee_validtill IS NOT NULL" . $roleCondition);
    $stmt->bindValue(':today', $today);
    $stmt->execute();
    $expiredCount = $stmt->fetchColumn();

    // Expiring Soon (today <= valid_till <= next30Days)
    $stmt = $db->prepare("SELECT COUNT(*) FROM license_details WHERE licensee_validtill >= :today AND licensee_validtill <= :next30Days" . $roleCondition);
    $stmt->bindValue(':today', $today);
    $stmt->bindValue(':next30Days', $next30Days);
    $stmt->execute();
    $expiringCount = $stmt->fetchColumn();

    // Total Licenses
    // Note: SELECT COUNT(*) FROM license_details WHERE 1=1 AND ...
    $totalSql = "SELECT COUNT(*) FROM license_details WHERE 1=1" . $roleCondition;
    $stmt = $db->query($totalSql);
    $totalCount = $stmt->fetchColumn();

    echo json_encode([
      'success' => true,
      'stats' => [
        'active_licenses' => $activeCount,
        'expired_licenses' => $expiredCount,
        'expiring_soon' => $expiringCount,
        'total_licenses' => $totalCount
      ]
    ]);
    exit;
  } catch (PDOException $e) {
    echo json_encode([
      'success' => false,
      'message' => 'Database error: ' . $e->getMessage()
    ]);
    exit;
  }
}

try {
  // Get pagination parameters
  $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
  $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
  $offset = ($page - 1) * $limit;

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

  // Restrict Limited Access users from viewing Sharekhan clients
  $userRole = isset($_SESSION['role']) ? $_SESSION['role'] : '';
  if ($userRole === 'Limited Access') {
    $whereConditions[] = "client_name != 'Sharekhan'";
  }

  $whereSql = '';
  if (!empty($whereConditions)) {
    $whereSql = 'WHERE ' . implode(' AND ', $whereConditions);
  }

  // Get total count
  $countStmt = $db->prepare("SELECT COUNT(*) FROM license_details $whereSql");
  foreach ($params as $key => $value) {
    $countStmt->bindValue($key, $value);
  }
  $countStmt->execute();
  $totalRecords = $countStmt->fetchColumn();

  // Fetch required fields with pagination
  $sql = "SELECT id, created_on, client_name, licensee_dealer, location_name, location_code, licensee_validtill, system_serialid, system_uniqueid, engine_graceperiod, 
            COALESCE(
                (SELECT status FROM device_status WHERE license_id = license_details.id ORDER BY id DESC LIMIT 1),
                device_status
            ) as device_status
            FROM license_details 
            $whereSql
            ORDER BY id DESC 
            LIMIT :limit OFFSET :offset";

  $stmt = $db->prepare($sql);
  $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
  $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
  foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
  }
  $stmt->execute();

  $licenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode([
    'success' => true,
    'data' => $licenses,
    'pagination' => [
      'current_page' => $page,
      'per_page' => $limit,
      'total_records' => $totalRecords,
      'total_pages' => ceil($totalRecords / $limit)
    ]
  ]);

} catch (PDOException $e) {
  echo json_encode([
    'success' => false,
    'message' => 'Database error: ' . $e->getMessage()
  ]);
}
?>