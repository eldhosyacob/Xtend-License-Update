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
  // Extract data from POST
  $created_on = getPostVal('CreatedOn');
  $client_name = getPostVal('ClientName');
  $location_name = getPostVal('LocationName');
  $location_code = getPostVal('LocationCode');

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

  // Engine
  $engine_build = getNestedPostVal('Engine', 'Build');
  $engine_graceperiod = getNestedPostVal('Engine', 'GracePeriod');
  $engine_maxports = getNestedPostVal('Engine', 'MaxPorts');
  $engine_validstarttz = getNestedPostVal('Engine', 'ValidStartTZ');
  $engine_validendtz = getNestedPostVal('Engine', 'ValidEndTZ');
  $engine_validcountries = getNestedPostVal('Engine', 'ValidCountries');

  // Hardware
  // Assuming these are passed as Hardware[Analog][2303] etc.
  $hardware_analog2303 = isset($_POST['Hardware']['Analog']['2303']) ? $_POST['Hardware']['Analog']['2303'] : '';
  $hardware_analog2304 = isset($_POST['Hardware']['Analog']['2304']) ? $_POST['Hardware']['Analog']['2304'] : '';

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

  // Comment
  $comment = getPostVal('comment');

  // Prepare SQL statement
  $sql = "INSERT INTO license_details (
        created_on, 
        client_name, location_name, location_code, 
        licensee_name, licensee_distributor, licensee_dealer, licensee_type, licensee_amctill, licensee_validtill, licensee_billno,
        system_type, system_os, system_isvm, system_serialid, system_uniqueid, system_build_type, system_debug,
        engine_build, engine_graceperiod, engine_maxports, engine_validstarttz, engine_validendtz, engine_validcountries,
        hardware_analog2303, hardware_analog2304,
        features_script,
        centralization_livestatusurl, centralization_livestatusurlinterval, centralization_uploadfileurl, centralization_uploadfileurlinterval, centralization_settingsurl, centralization_usertrunkmappingurl, centralization_phonebookurl,
        comment
    ) VALUES (
        :created_on,
        :client_name, :location_name, :location_code,
        :licensee_name, :licensee_distributor, :licensee_dealer, :licensee_type, :licensee_amctill, :licensee_validtill, :licensee_billno,
        :system_type, :system_os, :system_isvm, :system_serialid, :system_uniqueid, :system_build_type, :system_debug,
        :engine_build, :engine_graceperiod, :engine_maxports, :engine_validstarttz, :engine_validendtz, :engine_validcountries,
        :hardware_analog2303, :hardware_analog2304,
        :features_script,
        :centralization_livestatusurl, :centralization_livestatusurlinterval, :centralization_uploadfileurl, :centralization_uploadfileurlinterval, :centralization_settingsurl, :centralization_usertrunkmappingurl, :centralization_phonebookurl,
        :comment
    )";

  $stmt = $db->prepare($sql);

  $stmt->execute([
    ':created_on' => $created_on,
    ':client_name' => $client_name,
    ':location_name' => $location_name,
    ':location_code' => $location_code,
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
    ':engine_build' => $engine_build,
    ':engine_graceperiod' => $engine_graceperiod,
    ':engine_maxports' => $engine_maxports,
    ':engine_validstarttz' => $engine_validstarttz,
    ':engine_validendtz' => $engine_validendtz,
    ':engine_validcountries' => $engine_validcountries,
    ':hardware_analog2303' => $hardware_analog2303,
    ':hardware_analog2304' => $hardware_analog2304,
    ':features_script' => $features_script,
    ':centralization_livestatusurl' => $centralization_livestatusurl,
    ':centralization_livestatusurlinterval' => $centralization_livestatusurlinterval,
    ':centralization_uploadfileurl' => $centralization_uploadfileurl,
    ':centralization_uploadfileurlinterval' => $centralization_uploadfileurlinterval,
    ':centralization_settingsurl' => $centralization_settingsurl,
    ':centralization_usertrunkmappingurl' => $centralization_usertrunkmappingurl,
    ':centralization_phonebookurl' => $centralization_phonebookurl,
    ':comment' => $comment
  ]);

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