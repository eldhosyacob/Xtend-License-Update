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

// Helper function to get POST value safely
function getPostVal($key, $default = '')
{
  return isset($_POST[$key]) ? trim($_POST[$key]) : $default;
}

// Helper function to get nested POST value safely
function getNestedPostVal($parent, $key, $default = '')
{
  return isset($_POST[$parent][$key]) ? trim($_POST[$parent][$key]) : $default;
}

try {
  file_put_contents(__DIR__ . '/../logs/debug_trace.txt', "Script started at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
  // Extract data from POST
  $created_on = getPostVal('CreatedOn');
  $client_name = getPostVal('ClientName');
  // Check permission for Limited Access users
  $userRole = $_SESSION['role'] ?? '';
  if ($userRole === 'Limited Access' && $client_name === 'Sharekhan') {
    echo json_encode([
      'success' => false,
      'message' => 'Unauthorized: You cannot create Sharekhan licenses'
    ]);
    exit;
  }

  $location_name = getPostVal('LocationName');
  $location_code = getPostVal('LocationCode');
  $board_type = getPostVal('BoardType', 'Lichee Pi');

  // Licensee
  $licensee_name = getNestedPostVal('Licensee', 'Name');
  $licensee_distributor = getNestedPostVal('Licensee', 'Distributor');
  $licensee_dealer = getNestedPostVal('Licensee', 'Dealer');
  $licensee_type = getNestedPostVal('Licensee', 'Type');
  $licensee_amctill = getNestedPostVal('Licensee', 'AMCTill');
  $licensee_validtill = getNestedPostVal('Licensee', 'ValidTill');
  $licensee_billno = getNestedPostVal('Licensee', 'BillNo');

  // System
  $system_type = getNestedPostVal('System', 'Type');
  $system_os = getNestedPostVal('System', 'OS');
  $system_isvm = getNestedPostVal('System', 'IsVM');
  $system_serialid = getNestedPostVal('System', 'SerialID');
  $system_uniqueid = getNestedPostVal('System', 'UniqueID');
  $system_build_type = getNestedPostVal('System', 'BuildType', 'sharekhan');
  $system_debug = getNestedPostVal('System', 'Debug', 0);
  $system_passwords_system = isset($_POST['System']['Passwords']['System']) ? trim($_POST['System']['Passwords']['System']) : '';
  $system_passwords_web = isset($_POST['System']['Passwords']['Web']) ? trim($_POST['System']['Passwords']['Web']) : '';
  $fetch_updates = isset($_POST['System']['FetchUpdates']) ? (int) $_POST['System']['FetchUpdates'] : 0;
  $install_updates = isset($_POST['System']['InstallUpdates']) ? (int) $_POST['System']['InstallUpdates'] : 0;

  // Engine
  $engine_build = getNestedPostVal('Engine', 'Build');
  $engine_graceperiod = getNestedPostVal('Engine', 'GracePeriod');
  $engine_maxports = getNestedPostVal('Engine', 'MaxPorts');
  $engine_validstarttz = getNestedPostVal('Engine', 'ValidStartTZ');
  $engine_validendtz = getNestedPostVal('Engine', 'ValidEndTZ');
  $engine_validcountries = getNestedPostVal('Engine', 'ValidCountries');

  // Hardware
  $device_id1 = getPostVal('device_id1');
  $ports_enabled_deviceid1 = getPostVal('ports_enabled_deviceid1');
  $device_id2 = getPostVal('device_id2');
  $ports_enabled_deviceid2 = getPostVal('ports_enabled_deviceid2');
  $device_id3 = getPostVal('device_id3');
  $ports_enabled_deviceid3 = getPostVal('ports_enabled_deviceid3');
  $device_id4 = getPostVal('device_id4');
  $ports_enabled_deviceid4 = getPostVal('ports_enabled_deviceid4');
  $device_id5 = getPostVal('device_id5');
  $ports_enabled_deviceid5 = getPostVal('ports_enabled_deviceid5');
  $device_id6 = getPostVal('device_id6');
  $ports_enabled_deviceid6 = getPostVal('ports_enabled_deviceid6');

  // Features
  $features_script = getNestedPostVal('Features', 'Script');

  // Centralization
  $centralization_livestatusurl = getNestedPostVal('Centralization', 'LiveStatusUrl');
  $centralization_livestatusurlinterval = getNestedPostVal('Centralization', 'LiveStatusUrlInterval');
  $centralization_uploadfileurl = getNestedPostVal('Centralization', 'UploadFileUrl');
  $centralization_uploadfileurlinterval = getNestedPostVal('Centralization', 'UploadFileUrlInterval');
  $centralization_settingsurl = getNestedPostVal('Centralization', 'SettingsUrl');
  $centralization_usertrunkmappingurl = getNestedPostVal('Centralization', 'UserTrunkMappingUrl');
  $centralization_phonebookurl = getNestedPostVal('Centralization', 'PhoneBookUrl');

  // DeviceStatus
  $device_status = getPostVal('DeviceStatus');
  $status_date_input = getPostVal('StatusDate');

  // Comment
  $comment = getPostVal('comment');
  $tested_by = getPostVal('TestedBy');

  // START LOGGING LOGIC: Fetch previous data to compare
  $logChanges = [];
  $isNewLicense = true;

  if (!empty($system_serialid)) {
    try {
      $stmtPrev = $db->prepare("SELECT * FROM license_details WHERE system_serialid = :serial_id ORDER BY id DESC LIMIT 1");
      $stmtPrev->execute([':serial_id' => $system_serialid]);
      $oldRow = $stmtPrev->fetch(PDO::FETCH_ASSOC);

      if ($oldRow) {
        $isNewLicense = false;

        // Array of fields to compare mapping: DB column => New Value variable
        $fieldsToCheck = [
          'client_name' => $client_name,
          'location_name' => $location_name,
          'location_code' => $location_code,
          'board_type' => $board_type,
          'licensee_name' => $licensee_name,
          'licensee_distributor' => $licensee_distributor,
          'licensee_dealer' => $licensee_dealer,
          'licensee_type' => $licensee_type,
          'licensee_amctill' => $licensee_amctill,
          'licensee_validtill' => $licensee_validtill,
          'licensee_billno' => $licensee_billno,
          'system_type' => $system_type,
          'system_os' => $system_os,
          'system_isvm' => $system_isvm,
          'system_uniqueid' => $system_uniqueid,
          'system_build_type' => $system_build_type,
          'system_debug' => $system_debug,
          'system_passwords_system' => $system_passwords_system,
          'system_passwords_web' => $system_passwords_web,
          'fetch_updates' => $fetch_updates,
          'install_updates' => $install_updates,
          'engine_build' => $engine_build,
          'engine_graceperiod' => $engine_graceperiod,
          'engine_maxports' => $engine_maxports,
          'engine_validstarttz' => $engine_validstarttz,
          'engine_validendtz' => $engine_validendtz,
          'engine_validcountries' => $engine_validcountries,
          'device_id1' => $device_id1,
          'ports_enabled_deviceid1' => $ports_enabled_deviceid1,
          'device_id2' => $device_id2,
          'ports_enabled_deviceid2' => $ports_enabled_deviceid2,
          'device_id3' => $device_id3,
          'ports_enabled_deviceid3' => $ports_enabled_deviceid3,
          'device_id4' => $device_id4,
          'ports_enabled_deviceid4' => $ports_enabled_deviceid4,
          'device_id5' => $device_id5,
          'ports_enabled_deviceid5' => $ports_enabled_deviceid5,
          'device_id6' => $device_id6,
          'ports_enabled_deviceid6' => $ports_enabled_deviceid6,
          'features_script' => $features_script,
          'device_status' => $device_status,
          'centralization_livestatusurl' => $centralization_livestatusurl,
          'centralization_livestatusurlinterval' => $centralization_livestatusurlinterval,
          'centralization_uploadfileurl' => $centralization_uploadfileurl,
          'centralization_uploadfileurlinterval' => $centralization_uploadfileurlinterval,
          'centralization_settingsurl' => $centralization_settingsurl,
          'centralization_usertrunkmappingurl' => $centralization_usertrunkmappingurl,
          'centralization_phonebookurl' => $centralization_phonebookurl,
          'comment' => $comment,
          'tested_by' => $tested_by
        ];

        foreach ($fieldsToCheck as $field => $newValue) {
          $oldValue = isset($oldRow[$field]) ? $oldRow[$field] : '';

          // Normalize for comparison
          $val1 = trim((string) $oldValue);
          $val2 = trim((string) $newValue);

          if ($val1 !== $val2) {
            $logChanges[] = "$field: '$val1' -> '$val2'";
          }
        }
      }
    } catch (Exception $e) {
      // Silently fail logging prep if DB issue, main flow proceeds
    }
  }
  // END LOGGING LOGIC

  // Prepare SQL statement
  $sql = "INSERT INTO license_details (
        created_on, 
        client_name, location_name, location_code, board_type,
        licensee_name, licensee_distributor, licensee_dealer, licensee_type, licensee_amctill, licensee_validtill, licensee_billno,
        system_type, system_os, system_isvm, system_serialid, system_uniqueid, system_build_type, system_debug,
        system_passwords_system, system_passwords_web, fetch_updates, install_updates,
        engine_build, engine_graceperiod, engine_maxports, engine_validstarttz, engine_validendtz, engine_validcountries,
        device_id1, ports_enabled_deviceid1, device_id2, ports_enabled_deviceid2, device_id3, ports_enabled_deviceid3, device_id4, ports_enabled_deviceid4,device_id5, ports_enabled_deviceid5, device_id6, ports_enabled_deviceid6,
        features_script, device_status,
        centralization_livestatusurl, centralization_livestatusurlinterval, centralization_uploadfileurl, centralization_uploadfileurlinterval, centralization_settingsurl, centralization_usertrunkmappingurl, centralization_phonebookurl,
        comment,
        tested_by
    ) VALUES (
        :created_on,
        :client_name, :location_name, :location_code, :board_type,
        :licensee_name, :licensee_distributor, :licensee_dealer, :licensee_type, :licensee_amctill, :licensee_validtill, :licensee_billno,
        :system_type, :system_os, :system_isvm, :system_serialid, :system_uniqueid, :system_build_type, :system_debug,
        :system_passwords_system, :system_passwords_web, :fetch_updates, :install_updates,
        :engine_build, :engine_graceperiod, :engine_maxports, :engine_validstarttz, :engine_validendtz, :engine_validcountries,
        :device_id1, :ports_enabled_deviceid1, :device_id2, :ports_enabled_deviceid2, :device_id3, :ports_enabled_deviceid3, :device_id4, :ports_enabled_deviceid4,:device_id5, :ports_enabled_deviceid5, :device_id6, :ports_enabled_deviceid6,
        :features_script, :device_status,
        :centralization_livestatusurl, :centralization_livestatusurlinterval, :centralization_uploadfileurl, :centralization_uploadfileurlinterval, :centralization_settingsurl, :centralization_usertrunkmappingurl, :centralization_phonebookurl,
        :comment,
        :tested_by
    )";

  $stmt = $db->prepare($sql);

  $stmt->execute([
    ':created_on' => $created_on,
    ':client_name' => $client_name,
    ':location_name' => $location_name,
    ':location_code' => $location_code,
    ':board_type' => $board_type,
    ':licensee_name' => $licensee_name,
    ':licensee_distributor' => $licensee_distributor,
    ':licensee_dealer' => $licensee_dealer,
    ':licensee_type' => $licensee_type,
    ':licensee_amctill' => $licensee_amctill,
    ':licensee_validtill' => $licensee_validtill,
    ':licensee_billno' => $licensee_billno,
    ':system_type' => $system_type,
    ':system_os' => $system_os,
    ':system_isvm' => $system_isvm,
    ':system_serialid' => $system_serialid,
    ':system_uniqueid' => $system_uniqueid,
    ':system_build_type' => $system_build_type,
    ':system_debug' => $system_debug,
    ':system_passwords_system' => $system_passwords_system,
    ':system_passwords_web' => $system_passwords_web,
    ':fetch_updates' => $fetch_updates,
    ':install_updates' => $install_updates,
    ':engine_build' => $engine_build,
    ':engine_graceperiod' => $engine_graceperiod,
    ':engine_maxports' => $engine_maxports,
    ':engine_validstarttz' => $engine_validstarttz,
    ':engine_validendtz' => $engine_validendtz,
    ':engine_validcountries' => $engine_validcountries,
    ':device_id1' => $device_id1,
    ':ports_enabled_deviceid1' => $ports_enabled_deviceid1,
    ':device_id2' => $device_id2,
    ':ports_enabled_deviceid2' => $ports_enabled_deviceid2,
    ':device_id3' => $device_id3,
    ':ports_enabled_deviceid3' => $ports_enabled_deviceid3,
    ':device_id4' => $device_id4,
    ':ports_enabled_deviceid4' => $ports_enabled_deviceid4,
    ':device_id5' => $device_id5,
    ':ports_enabled_deviceid5' => $ports_enabled_deviceid5,
    ':device_id6' => $device_id6,
    ':ports_enabled_deviceid6' => $ports_enabled_deviceid6,
    ':features_script' => $features_script,
    ':device_status' => $device_status,
    ':centralization_livestatusurl' => $centralization_livestatusurl,
    ':centralization_livestatusurlinterval' => $centralization_livestatusurlinterval,
    ':centralization_uploadfileurl' => $centralization_uploadfileurl,
    ':centralization_uploadfileurlinterval' => $centralization_uploadfileurlinterval,
    ':centralization_settingsurl' => $centralization_settingsurl,
    ':centralization_usertrunkmappingurl' => $centralization_usertrunkmappingurl,
    ':centralization_phonebookurl' => $centralization_phonebookurl,
    ':comment' => $comment,
    ':tested_by' => $tested_by
  ]);

  // Insert device status log
  $licenseId = $db->lastInsertId();
  // Only insert into device_status history if status and date are provided
  if ($licenseId && !empty($device_status) && !empty($status_date_input)) {
    try {
      $statusStmt = $db->prepare("
          INSERT INTO device_status (`license_id`, `status`, `date`, `user`) 
          VALUES (:license_id, :status, :date, :user)
      ");
      $statusStmt->execute([
        ':license_id' => $licenseId,
        ':status' => $device_status,
        ':date' => $status_date_input . ' ' . date('H:i:s'),
        ':user' => $_SESSION['full_name'] ?? 'Unknown'
      ]);
    } catch (PDOException $e) {
      // Log error but don't fail the request
      error_log('Device Status insert failed in API: ' . $e->getMessage());
    }
  }

  // LOG FILE WRITING
  file_put_contents(__DIR__ . '/../logs/debug_trace.txt', "Reaching Log Block\n", FILE_APPEND);
  try {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
      if (!mkdir($logDir, 0777, true)) {
        error_log("Failed to create log directory: " . $logDir);
      }
    }
    $logFile = $logDir . '/license_updates.log';

    $timestamp = date('Y-m-d H:i:s');
    $user = $_SESSION['full_name'] ?? 'Unknown User';
    $serial = !empty($system_serialid) ? $system_serialid : 'N/A';

    $logEntry = "[$timestamp] User: $user | Serial: $serial\n";

    if ($isNewLicense) {
      $logEntry .= "Action: Created New License Entry\n";
    } else {
      $logEntry .= "Action: Updated License Entry\n";
      if (empty($logChanges)) {
        $logEntry .= "Changes: No changes detected.\n";
      } else {
        $logEntry .= "Changes:\n";
        foreach ($logChanges as $change) {
          $logEntry .= " - $change\n";
        }
      }
    }
    $logEntry .= str_repeat("-", 50) . "\n";

    if (file_put_contents($logFile, $logEntry, FILE_APPEND) === false) {
      error_log("Failed to write to log file: " . $logFile);
    }

  } catch (Exception $e) {
    // Suppress logging errors so they don't break the response
    file_put_contents(__DIR__ . '/../logs/debug_trace.txt', "Logging Exception: " . $e->getMessage() . "\n", FILE_APPEND);
    error_log('Failed to write to license update log: ' . $e->getMessage());
  }

  echo json_encode([
    'success' => true,
    'message' => 'License details saved successfully'
  ]);

} catch (PDOException $e) {
  echo json_encode([
    'success' => false,
    'message' => 'Database error: ' . $e->getMessage()
  ]);
}
?>