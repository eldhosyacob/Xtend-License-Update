<?php
declare(strict_types=1);
require_once('config/auth_check.php');


$uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'licenses';
if (!is_dir($uploadDir)) {
  @mkdir($uploadDir, 0775, true);
}

// Helpers
function h($value): string
{
  return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function array_get($array, array $path, $default = null)
{
  $cur = $array;
  foreach ($path as $key) {
    if (!is_array($cur) || !array_key_exists($key, $cur))
      return $default;
    $cur = $cur[$key];
  }
  return $cur;
}

function run_process(array $cmd): array
{
  $descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
  ];
  $proc = @proc_open($cmd, $descriptors, $pipes, null, null);
  if (!is_resource($proc)) {
    return [1, '', 'Failed to start process'];
  }
  fclose($pipes[0]);
  $stdout = stream_get_contents($pipes[1]);
  $stderr = stream_get_contents($pipes[2]);
  fclose($pipes[1]);
  fclose($pipes[2]);
  $code = proc_close($proc);
  return [$code, (string) $stdout, (string) $stderr];
}

function find_curl_binary(): ?string
{
  $candidates = [
    'curl', // PATH
    'C:\\Windows\\System32\\curl.exe',
    'C:\\Windows\\SysWOW64\\curl.exe',
  ];
  foreach ($candidates as $bin) {
    [$code, $out, $err] = run_process([$bin, '--version']);
    if ($code === 0)
      return $bin;
  }
  return null;
}

function find_svn_binary(?string $preferred = null): ?string
{
  $candidates = [];
  if ($preferred && $preferred !== '') {
    $candidates[] = $preferred;
  }
  $candidates = array_merge($candidates, [
    'svn', // if on PATH
    'C:\\Program Files\\Subversion\\bin\\svn.exe',
    'C:\\Program Files\\SlikSvn\\bin\\svn.exe',
    'C:\\Program Files\\VisualSVN Server\\bin\\svn.exe',
    'C:\\Program Files\\TortoiseSVN\\bin\\svn.exe',
    'C:\\Program Files (x86)\\Subversion\\bin\\svn.exe',
    'C:\\Program Files (x86)\\SlikSvn\\bin\\svn.exe',
    'C:\\Program Files (x86)\\VisualSVN Server\\bin\\svn.exe',
    'C:\\Program Files (x86)\\TortoiseSVN\\bin\\svn.exe',
  ]);
  foreach ($candidates as $bin) {
    [$code, $out, $err] = run_process([$bin, '--version', '--quiet']);
    if ($code === 0)
      return $bin;
  }
  return null;
}

// Handle actions
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$errors = [];
$loaded = null; // no longer used
$rawJson = '';  // no longer used
$prefill = null; // holds fetched JSON to prefill the form
$submitResult = null; // holds submission result data
$commentValue = isset($_POST['comment']) ? (string) $_POST['comment'] : '';
$testedByValue = isset($_POST['TestedBy']) ? (string) $_POST['TestedBy'] : '';
$editId = $_GET['edit_id'] ?? null;
$userRole = $_SESSION['role'] ?? 'Limited Access';
$mode = $_GET['mode'] ?? '';
$isViewMode = ($mode === 'view') || ($userRole !== 'Administrator' && $userRole !== 'Limited Access');

// Block POST requests for unauthorized roles
if ($method === 'POST' && $userRole !== 'Administrator' && $userRole !== 'Limited Access') {
  header('HTTP/1.1 403 Forbidden');
  die('Unauthorized access');
}

if ($method === 'GET' && $editId) {
  require_once('config/database.php');
  $db = getDatabaseConnection();
  if ($db) {
    try {
      $stmt = $db->prepare("SELECT * FROM license_details WHERE id = :id");
      $stmt->execute([':id' => $editId]);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($row) {
        // Restrict Limited Access users from viewing Sharekhan licenses
        if ($userRole === 'Limited Access' && $row['client_name'] === 'Sharekhan') {
          die('Unauthorized access: You are not allowed to view or edit Sharekhan licenses.');
        }

        $prefill = [
          // 'CreatedOn' => $row['created_on'],
          'CreatedOn' => $row['created_on']
            ? date('Ymd', strtotime($row['created_on']))
            : '',
          'ClientName' => $row['client_name'],
          'LocationName' => $row['location_name'],
          'LocationCode' => $row['location_code'],
          'BoardType' => $row['board_type'],
          'Licensee' => [
            'Name' => $row['licensee_name'],
            'Distributor' => $row['licensee_distributor'],
            'Dealer' => $row['licensee_dealer'],
            'Type' => $row['licensee_type'],
            // 'AMCTill' => $row['licensee_amctill'],
            // 'ValidTill' => $row['licensee_validtill'],
            'AMCTill' => $row['licensee_amctill']
              ? date('Ymd', strtotime($row['licensee_amctill']))
              : '',
            'ValidTill' => $row['licensee_validtill']
              ? date('Ymd', strtotime($row['licensee_validtill']))
              : '',
            'BillNo' => $row['licensee_billno'],
          ],
          'System' => [
            'Type' => $row['system_type'],
            'OS' => $row['system_os'],
            'IsVM' => $row['system_isvm'],
            'SerialID' => $row['system_serialid'],
            'UniqueID' => $row['system_uniqueid'],
            'BuildType' => $row['system_build_type'],
            'Debug' => $row['system_debug'],
            'IPSettings' => [
              'Type' => $row['system_ipsettings_type'],
              'IP' => $row['system_ipsettings_ip'],
              'Gateway' => $row['system_ipsettings_gateway'],
              'Dns' => $row['system_ipsettings_dns'],
            ],
            'Passwords' => [
              'System' => $row['system_passwords_system'],
              'Web' => $row['system_passwords_web'],
            ],
            'FetchUpdates' => $row['fetch_updates'],
            'InstallUpdates' => $row['install_updates'],
          ],
          'Engine' => [
            'Build' => $row['engine_build'],
            'GracePeriod' => $row['engine_graceperiod'],
            'MaxPorts' => (preg_match('/^M=(.*?),/', $row['engine_maxports'], $m) ? $m[1] : $row['engine_maxports']),
            'ValidStartTZ' => $row['engine_validstarttz'],
            'ValidEndTZ' => $row['engine_validendtz'],
            'ValidCountries' => $row['engine_validcountries'],
          ],
          'Hardware' => [
            'Analog' => [
              // '2303' => $row['hardware_analog2303'],
              // '2304' => $row['hardware_analog2304'],
              // Reconstruct the JSON structure using the saved device_ids
            ],
          ],
          'Centralization' => [
            'LiveStatusUrl' => $row['centralization_livestatusurl'],
            'LiveStatusUrlInterval' => $row['centralization_livestatusurlinterval'],
            'UploadFileUrl' => $row['centralization_uploadfileurl'],
            'UploadFileUrlInterval' => $row['centralization_uploadfileurlinterval'],
            'SettingsUrl' => $row['centralization_settingsurl'],
            'UserTrunkMappingUrl' => $row['centralization_usertrunkmappingurl'],
            'PhoneBookUrl' => $row['centralization_phonebookurl'],
          ],
          'device_id1' => $row['device_id1'],
          'ports_enabled_deviceid1' => $row['ports_enabled_deviceid1'],
          'device_id2' => $row['device_id2'],
          'ports_enabled_deviceid2' => $row['ports_enabled_deviceid2'],
          'device_id3' => $row['device_id3'],
          'ports_enabled_deviceid3' => $row['ports_enabled_deviceid3'],
          'device_id4' => $row['device_id4'],
          'ports_enabled_deviceid4' => $row['ports_enabled_deviceid4'],
          'device_id5' => $row['device_id5'],
          'ports_enabled_deviceid5' => $row['ports_enabled_deviceid5'],
          'device_id6' => $row['device_id6'],
          'ports_enabled_deviceid6' => $row['ports_enabled_deviceid6'],
          'Features' => [
            'Script' => $row['features_script'],
          ],
          'DeviceStatus' => $row['device_status'],
        ];
        $commentValue = $row['comment'];
        $testedByValue = $row['tested_by'] ?? '';


      }
    } catch (PDOException $e) {
      $errors[] = 'Database error: ' . $e->getMessage();
    }
  }
}

if ($method === 'POST' && $action === 'fetch_remote') {
  $url = trim((string) ($_POST['remote_url'] ?? ''));
  $username = trim((string) ($_POST['remote_user'] ?? ''));
  $password = (string) ($_POST['remote_pass'] ?? '');
  $userSvnBin = trim((string) ($_POST['remote_svn_bin'] ?? ''));

  if ($url === '') {
    $errors[] = 'Please provide a URL to fetch the JSON.';
  } else {
    $scheme = parse_url($url, PHP_URL_SCHEME);
    if (in_array(strtolower((string) $scheme), ['http', 'https'], true)) {
      $context = null;
      $headers = [];
      if ($username !== '') {
        $headers[] = 'Authorization: Basic ' . base64_encode($username . ':' . $password);
      }
      if ($headers) {
        $context = stream_context_create([
          'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headers),
            'timeout' => 20,
          ],
          'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
          ],
        ]);
      }
      $contents = @file_get_contents($url, false, $context);
      if ($contents === false || trim((string) $contents) === '') {
        $errors[] = 'Failed to fetch JSON from the provided URL.';
      } else {
        $data = json_decode((string) $contents, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
          $errors[] = 'Fetched content is not valid JSON.';
        } else {
          $prefill = $data;
        }
      }
    } else {
      // Fallback via svn cat for svn://, svn+ssh://, file://
      $svn = find_svn_binary($userSvnBin !== '' ? $userSvnBin : null);
      if ($svn === null) {
        $errors[] = 'svn command not found. Install TortoiseSVN (with command line tools) or Subversion CLI.';
      } else {
        $cmd = [$svn, 'cat', '--non-interactive'];
        // If using https with invalid certs, we could add: --trust-server-cert-failures=unknown-ca,cn-mismatch,expired,not-yet-valid,other
        if ($username !== '') {
          $cmd[] = '--username';
          $cmd[] = $username;
          $cmd[] = '--password';
          $cmd[] = $password;
        }
        $cmd[] = $url;
        [$code, $out, $err] = run_process($cmd);
        if ($code !== 0 || trim($out) === '') {
          $errors[] = 'svn cat failed. ' . ($err !== '' ? h($err) : 'Exit code ' . $code);
        } else {
          $data = json_decode($out, true);
          if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            $errors[] = 'Fetched content is not valid JSON (via svn cat).';
          } else {
            $prefill = $data;
          }
        }
      }
    }
  }
}

// Build JSON directly from a fixed schema form
function buildLicenseFromPost(array $post, $db = null): array
{
  $str = function ($v): string {
    return is_string($v) ? trim($v) : (string) $v;
  };
  $boolFrom = function ($v): bool {
    if (is_bool($v))
      return $v;
    $s = strtolower(trim((string) $v));
    return $s === 'true' || $s === '1' || $s === 'yes';
    // Note: "No" will return false (which is correct)
  };

  // Determine DeviceStatus
  $deviceStatus = $str($post['DeviceStatus'] ?? '');
  if ($deviceStatus === '' && $db !== null && !empty($post['edit_id'])) {
    try {
      // First try to get latest from history
      $stmt = $db->prepare("SELECT status FROM device_status WHERE license_id = :id ORDER BY id DESC LIMIT 1");
      $stmt->execute([':id' => $post['edit_id']]);
      if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $deviceStatus = $row['status'];
      } else {
        // Fallback to current main table status
        $stmt = $db->prepare("SELECT device_status FROM license_details WHERE id = :id");
        $stmt->execute([':id' => $post['edit_id']]);
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
          $deviceStatus = $row['device_status'];
        }
      }
    } catch (Exception $e) {
      // Ignore DB errors during build, fallback to empty
    }
  }

  $license = [
    'CreatedOn' => $str($post['CreatedOn'] ?? ''),
    'Client' => [
      'ClientName' => $str($post['ClientName'] ?? ''),
      'LocationName' => $str($post['LocationName'] ?? ''),
      'LocationCode' => $str($post['LocationCode'] ?? ''),
    ],
    'Licensee' => [
      'Name' => $str($post['Licensee']['Name'] ?? ''),
      'Distributor' => $str($post['Licensee']['Distributor'] ?? ''),
      'Dealer' => $str($post['Licensee']['Dealer'] ?? ''),
      'Type' => $str($post['Licensee']['Type'] ?? ''),
      'AMCTill' => $str($post['Licensee']['AMCTill'] ?? ''),
      'ValidTill' => $str($post['Licensee']['ValidTill'] ?? ''),
      'BillNo' => $str($post['Licensee']['BillNo'] ?? ''),
    ],
    'System' => [
      'Type' => $str($post['System']['Type'] ?? ''),
      'OS' => $str($post['System']['OS'] ?? ''),
      'IsVM' => isset($post['System']['IsVM']) ? $boolFrom($post['System']['IsVM']) : false,
      'SerialID' => $str($post['System']['SerialID'] ?? ''),
      'UniqueID' => $str($post['System']['UniqueID'] ?? ''),
      'BuildType' => $str($post['System']['BuildType'] ?? ''),
      'Debug' => (int) ($post['System']['Debug'] ?? 0),
      'IPSettings' => [
        'Type' => ($type = $str($post['System']['IPSettings']['Type'] ?? '')),
        'IP' => $type === 'DHCP' ? '' : $str($post['System']['IPSettings']['IP'] ?? ''),
        'Gateway' => $type === 'DHCP' ? '' : $str($post['System']['IPSettings']['Gateway'] ?? ''),
        'Dns' => $type === 'DHCP' ? '' : $str($post['System']['IPSettings']['Dns'] ?? ''),
      ],
      'Passwords' => [
        'System' => $str($post['System']['Passwords']['System'] ?? ''),
        'Web' => $str($post['System']['Passwords']['Web'] ?? ''),
      ],
      'FetchUpdates' => (int) ($post['System']['FetchUpdates'] ?? 0),
      'InstallUpdates' => (int) ($post['System']['InstallUpdates'] ?? 0),
    ],
    'Engine' => [
      'Build' => $str($post['Engine']['Build'] ?? ''),
      'GracePeriod' => (int) ($post['Engine']['GracePeriod'] ?? 0),
      'MaxPorts' => (($val = $str($post['Engine']['MaxPorts'] ?? '')) && strpos($val, 'M=') === 0 ? $val : "M={$val},*={$val}"),
      'ValidStartTZ' => (int) ($post['Engine']['ValidStartTZ'] ?? 0),
      'ValidEndTZ' => (int) ($post['Engine']['ValidEndTZ'] ?? 0),
      'ValidCountries' => (int) ($post['Engine']['ValidCountries'] ?? 0),
    ],
    'Hardware' => [
      'Analog' => [],
    ],
    'Centralization' => [
      'LiveStatusUrl' => ($postfix = function ($val, $suffix) use ($str) {
        $val = $str($val);
        if ($val === '')
          return '';
        if (strpos($val, $suffix) !== false)
          return $val;
        return rtrim($val, '/') . $suffix;
      })($post['Centralization']['LiveStatusUrl'] ?? '', '/UploadZip.xbs?UploadXmlData()'),
      'LiveStatusUrlInterval' => $str($post['Centralization']['LiveStatusUrlInterval'] ?? ''),
      'UploadFileUrl' => $postfix($post['Centralization']['UploadFileUrl'] ?? '', '/UploadZip.xbs?UploadWaveFileToHO()'),
      'UploadFileUrlInterval' => $str($post['Centralization']['UploadFileUrlInterval'] ?? ''),
      'SettingsUrl' => $postfix($post['Centralization']['SettingsUrl'] ?? '', '/VersionUpgradation.xbc?UpdateSettings'),
      'UserTrunkMappingUrl' => $postfix($post['Centralization']['UserTrunkMappingUrl'] ?? '', '/clientserver.xbc?GenerateStreamutmXML()'),
      'PhoneBookUrl' => $postfix($post['Centralization']['PhoneBookUrl'] ?? '', '/VersionUpgradation.xbc?UpdatePhoneBook()'),
    ],
    'Features' => [
      'Script' => $str($post['Features']['Script'] ?? ''),
    ],
    'DeviceStatus' => $deviceStatus,
  ];

  $devices = [
    ['id' => $str($post['device_id1'] ?? ''), 'ports' => $str($post['ports_enabled_deviceid1'] ?? '')],
    ['id' => $str($post['device_id2'] ?? ''), 'ports' => $str($post['ports_enabled_deviceid2'] ?? '')],
    ['id' => $str($post['device_id3'] ?? ''), 'ports' => $str($post['ports_enabled_deviceid3'] ?? '')],
    ['id' => $str($post['device_id4'] ?? ''), 'ports' => $str($post['ports_enabled_deviceid4'] ?? '')],
    ['id' => $str($post['device_id5'] ?? ''), 'ports' => $str($post['ports_enabled_deviceid5'] ?? '')],
    ['id' => $str($post['device_id6'] ?? ''), 'ports' => $str($post['ports_enabled_deviceid6'] ?? '')],
  ];

  foreach ($devices as $d) {
    if ($d['id'] === '')
      continue;
    $key = $d['id'];
    $counter = 0;
    // Find a unique key if it already exists
    while (isset($license['Hardware']['Analog'][$key])) {
      $counter++;
      $key = $d['id'] . '_DUPLICATE_KEY_MARKER_' . $counter . '_';
    }
    $license['Hardware']['Analog'][$key] = $d['ports'];
  }
  return $license;
}

if ($method === 'POST' && $action === 'generate') {
  $license = buildLicenseFromPost($_POST);
  $encoded = json_encode($license, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  $encoded = preg_replace('/_DUPLICATE_KEY_MARKER_\d+_\"/', '"', $encoded);
  if (isset($_POST['download']) && $_POST['download'] === '1') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="license.json"');
    echo $encoded;
    exit;
  }
  // Fall-through to page render with generated preview
}

if ($method === 'POST' && $action === 'send') {
  require_once('config/database.php');
  $db = getDatabaseConnection();
  $license = buildLicenseFromPost($_POST, $db);
  $encoded = json_encode($license, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  $encoded = preg_replace('/_DUPLICATE_KEY_MARKER_\d+_\"/', '"', $encoded);
  // Write to a temp json file
  $tmpDir = $uploadDir;
  if (!is_dir($tmpDir)) {
    @mkdir($tmpDir, 0775, true);
  }
  $tmpFile = $tmpDir . DIRECTORY_SEPARATOR . 'default.json';
  @file_put_contents($tmpFile, $encoded);

  $curl = find_curl_binary();
  if ($curl === null) {
    $errors[] = 'curl not found. Ensure curl.exe is available on PATH or in C:\\Windows\\System32.';
  } else {
    $url = 'https://10.1.1.7/license.xqs?UploadFile()';
    $account = 'guest';
    $token = 'guest';
    $info = trim((string) ($_POST['comment'] ?? 'Comment'));
    if ($info === '') {
      $info = 'Comment';
    }

    // Build curl command: curl -k -X POST -F account_name=guest -F secret_token=guest -F licensefile=@default.json "URL" -F info="Comment"
    $cmd = [
      $curl,
      '-k',
      '-X',
      'POST',
      '-F',
      'account_name=' . $account,
      '-F',
      'secret_token=' . $token,
      // On Windows, @ needs absolute path
      '-F',
      'licensefile=@' . $tmpFile,
      '-F',
      'info=' . $info,
      $url,
    ];
    [$code, $out, $err] = run_process($cmd);

    // Parse compact message from server response
    $resultTag = '';
    $errorTag = '';
    if (preg_match('/<result>(.*?)<\/result>/is', $out, $m)) {
      $resultTag = trim($m[1]);
    }
    if (preg_match('/<error>(.*?)<\/error>/is', $out, $m)) {
      $errorTag = trim($m[1]);
    }

    // Only insert to database if resultTag is 'SUCCESS'
    if (strtoupper($resultTag) === 'SUCCESS') {
      require_once('config/database.php');
      $db = getDatabaseConnection();

      if ($db) {
        try {
          $getPostVal = function ($key, $default = '') {
            return isset($_POST[$key]) ? trim($_POST[$key]) : $default;
          };

          $getNestedPostVal = function ($parent, $key, $default = '') {
            return isset($_POST[$parent][$key]) ? trim($_POST[$parent][$key]) : $default;
          };

          $created_on = $getPostVal('CreatedOn');
          $client_name = $getPostVal('ClientName');
          $location_name = $getPostVal('LocationName');
          $location_code = $getPostVal('LocationCode');
          $board_type = $getPostVal('BoardType', 'Lichee Pi');
          $licensee_name = $getNestedPostVal('Licensee', 'Name');
          $licensee_distributor = $getNestedPostVal('Licensee', 'Distributor');
          $licensee_dealer = $getNestedPostVal('Licensee', 'Dealer');
          $licensee_type = $getNestedPostVal('Licensee', 'Type');
          $licensee_amctill = $getNestedPostVal('Licensee', 'AMCTill');
          $licensee_validtill = $getNestedPostVal('Licensee', 'ValidTill');
          $licensee_billno = $getNestedPostVal('Licensee', 'BillNo');
          $system_type = $getNestedPostVal('System', 'Type');
          $system_os = $getNestedPostVal('System', 'OS');
          $system_isvm = $getNestedPostVal('System', 'IsVM');
          $system_serialid = $getNestedPostVal('System', 'SerialID');
          $system_uniqueid = $getNestedPostVal('System', 'UniqueID');
          $system_build_type = $getNestedPostVal('System', 'BuildType');
          $system_debug = $getNestedPostVal('System', 'Debug', 0);
          $engine_build = $getNestedPostVal('Engine', 'Build');
          $engine_graceperiod = $getNestedPostVal('Engine', 'GracePeriod');
          $engine_maxports = $getNestedPostVal('Engine', 'MaxPorts');
          if (strpos($engine_maxports, 'M=') !== 0) {
            $engine_maxports = "M={$engine_maxports},*={$engine_maxports}";
          }
          $engine_validstarttz = $getNestedPostVal('Engine', 'ValidStartTZ');
          $engine_validendtz = $getNestedPostVal('Engine', 'ValidEndTZ');
          $engine_validcountries = $getNestedPostVal('Engine', 'ValidCountries');
          $device_id1 = $getPostVal('device_id1');
          $ports_enabled_deviceid1 = $getPostVal('ports_enabled_deviceid1');
          $device_id2 = $getPostVal('device_id2');
          $ports_enabled_deviceid2 = $getPostVal('ports_enabled_deviceid2');
          $device_id3 = $getPostVal('device_id3');
          $ports_enabled_deviceid3 = $getPostVal('ports_enabled_deviceid3');
          $device_id4 = $getPostVal('device_id4');
          $ports_enabled_deviceid4 = $getPostVal('ports_enabled_deviceid4');
          $device_id5 = $getPostVal('device_id5');
          $ports_enabled_deviceid5 = $getPostVal('ports_enabled_deviceid5');
          $device_id6 = $getPostVal('device_id6');
          $ports_enabled_deviceid6 = $getPostVal('ports_enabled_deviceid6');

          // New System IPSettings fields
          // Need to fix retrieval for deeper nested keys or just access $_POST directly
          $system_ipsettings_type = isset($_POST['System']['IPSettings']['Type']) ? trim($_POST['System']['IPSettings']['Type']) : '';
          $system_ipsettings_ip = ($system_ipsettings_type === 'DHCP') ? '' : (isset($_POST['System']['IPSettings']['IP']) ? trim($_POST['System']['IPSettings']['IP']) : '');
          $system_ipsettings_gateway = ($system_ipsettings_type === 'DHCP') ? '' : (isset($_POST['System']['IPSettings']['Gateway']) ? trim($_POST['System']['IPSettings']['Gateway']) : '');
          $system_ipsettings_dns = ($system_ipsettings_type === 'DHCP') ? '' : (isset($_POST['System']['IPSettings']['Dns']) ? trim($_POST['System']['IPSettings']['Dns']) : '');
          $system_passwords_system = isset($_POST['System']['Passwords']['System']) ? trim($_POST['System']['Passwords']['System']) : '';
          $system_passwords_web = isset($_POST['System']['Passwords']['Web']) ? trim($_POST['System']['Passwords']['Web']) : '';
          $fetch_updates = isset($_POST['System']['FetchUpdates']) ? (int) $_POST['System']['FetchUpdates'] : 0;
          $install_updates = isset($_POST['System']['InstallUpdates']) ? (int) $_POST['System']['InstallUpdates'] : 0;

          // New Centralization fields
          $centralization_livestatusurl = $license['Centralization']['LiveStatusUrl'];
          $centralization_livestatusurlinterval = $license['Centralization']['LiveStatusUrlInterval'];
          $centralization_uploadfileurl = $license['Centralization']['UploadFileUrl'];
          $centralization_uploadfileurlinterval = $license['Centralization']['UploadFileUrlInterval'];
          $centralization_settingsurl = $license['Centralization']['SettingsUrl'];
          $centralization_usertrunkmappingurl = $license['Centralization']['UserTrunkMappingUrl'];
          $centralization_phonebookurl = $license['Centralization']['PhoneBookUrl'];

          $features_script = $getNestedPostVal('Features', 'Script');
          $device_status = $getPostVal('DeviceStatus');
          $status_date_input = $getPostVal('StatusDate');
          $comment = $getPostVal('comment');
          $tested_by = $getPostVal('TestedBy');
          $editId = $_POST['edit_id'] ?? '';

          $params = [
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

            ':system_ipsettings_type' => $system_ipsettings_type,
            ':system_ipsettings_ip' => $system_ipsettings_ip,
            ':system_ipsettings_gateway' => $system_ipsettings_gateway,
            ':system_ipsettings_dns' => $system_ipsettings_dns,
            ':system_passwords_system' => $system_passwords_system,
            ':system_passwords_web' => $system_passwords_web,
            ':fetch_updates' => $fetch_updates,
            ':install_updates' => $install_updates,

            ':centralization_livestatusurl' => $centralization_livestatusurl,
            ':centralization_livestatusurlinterval' => $centralization_livestatusurlinterval,
            ':centralization_uploadfileurl' => $centralization_uploadfileurl,
            ':centralization_uploadfileurlinterval' => $centralization_uploadfileurlinterval,
            ':centralization_settingsurl' => $centralization_settingsurl,
            ':centralization_usertrunkmappingurl' => $centralization_usertrunkmappingurl,
            ':centralization_phonebookurl' => $centralization_phonebookurl,

            // ':hardware_analog2303' => $hardware_analog2303,
            // ':hardware_analog2304' => $hardware_analog2304,
            ':features_script' => $features_script,
            ':device_status' => ($device_status !== '' ? $device_status : ($prefill['DeviceStatus'] ?? '')),
            ':comment' => $comment,
            ':tested_by' => $tested_by,
            ':board_type' => $board_type
          ];

          if ($editId) {
            $sql = "UPDATE license_details SET
                              created_on = :created_on, client_name = :client_name, location_name = :location_name, location_code = :location_code, licensee_name = :licensee_name, licensee_distributor = :licensee_distributor,
                              licensee_dealer = :licensee_dealer, licensee_type = :licensee_type, licensee_amctill = :licensee_amctill,
                              licensee_validtill = :licensee_validtill, licensee_billno = :licensee_billno, system_type = :system_type,
                              system_os = :system_os, system_isvm = :system_isvm, system_serialid = :system_serialid,
                              system_uniqueid = :system_uniqueid, system_build_type = :system_build_type,
                              system_debug = :system_debug, 
                              board_type = :board_type,
                              system_ipsettings_type = :system_ipsettings_type, system_ipsettings_ip = :system_ipsettings_ip,
                              system_ipsettings_gateway = :system_ipsettings_gateway, system_ipsettings_dns = :system_ipsettings_dns,
                              system_passwords_system = :system_passwords_system, system_passwords_web = :system_passwords_web, 
                              fetch_updates = :fetch_updates, install_updates = :install_updates,
                              engine_build = :engine_build,
                              engine_graceperiod = :engine_graceperiod, engine_maxports = :engine_maxports,

                              engine_validstarttz = :engine_validstarttz, engine_validendtz = :engine_validendtz,
                              engine_validcountries = :engine_validcountries, 
                              device_id1 = :device_id1, ports_enabled_deviceid1 = :ports_enabled_deviceid1,
                              device_id2 = :device_id2, ports_enabled_deviceid2 = :ports_enabled_deviceid2,
                              device_id3 = :device_id3, ports_enabled_deviceid3 = :ports_enabled_deviceid3,
                              device_id4 = :device_id4, ports_enabled_deviceid4 = :ports_enabled_deviceid4,
                              device_id5 = :device_id5, ports_enabled_deviceid5 = :ports_enabled_deviceid5,
                              device_id6 = :device_id6, ports_enabled_deviceid6 = :ports_enabled_deviceid6,
                              centralization_livestatusurl = :centralization_livestatusurl, centralization_livestatusurlinterval = :centralization_livestatusurlinterval,
                              centralization_uploadfileurl = :centralization_uploadfileurl, centralization_uploadfileurlinterval = :centralization_uploadfileurlinterval,
                              centralization_settingsurl = :centralization_settingsurl, centralization_usertrunkmappingurl = :centralization_usertrunkmappingurl,
                              centralization_phonebookurl = :centralization_phonebookurl,
                              features_script = :features_script, 
                              device_status = :device_status, 
                              comment = :comment,
                              tested_by = :tested_by
                          WHERE id = :id";
            $params[':id'] = $editId;

            // Security check for Limited Access users during Edit
            if ($userRole === 'Limited Access') {
              // Check if the creating/updating to Sharekhan
              if ($client_name === 'Sharekhan') {
                throw new Exception("Unauthorized: You cannot set Client Name to Sharekhan.");
              }

              // If updating, check if the original record was Sharekhan (Double check)
              $checkStmt = $db->prepare("SELECT client_name FROM license_details WHERE id = :id");
              $checkStmt->execute([':id' => $editId]);
              $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
              if ($existing && $existing['client_name'] === 'Sharekhan') {
                throw new Exception("Unauthorized: You cannot edit Sharekhan licenses.");
              }
            }
          } else {
            // Security check for Limited Access users during Create
            if ($userRole === 'Limited Access' && $client_name === 'Sharekhan') {
              throw new Exception("Unauthorized: You cannot create Sharekhan licenses.");
            }

            $sql = "INSERT INTO license_details (
                              created_on, client_name, location_name, location_code, licensee_name, licensee_distributor, licensee_dealer, licensee_type, 
                              licensee_amctill, licensee_validtill, licensee_billno, system_type, system_os, 
                              system_isvm, system_serialid, system_uniqueid, system_build_type, system_debug, board_type,
                              system_ipsettings_type, system_ipsettings_ip, system_ipsettings_gateway, system_ipsettings_dns,
                              system_passwords_system, system_passwords_web, fetch_updates, install_updates,
                              engine_build, 
                              engine_graceperiod, engine_maxports, engine_validstarttz, engine_validendtz, 
                              engine_validcountries, device_id1, ports_enabled_deviceid1, device_id2, ports_enabled_deviceid2, device_id3, ports_enabled_deviceid3, device_id4, ports_enabled_deviceid4, device_id5, ports_enabled_deviceid5, device_id6, ports_enabled_deviceid6,
                              centralization_livestatusurl, centralization_livestatusurlinterval, centralization_uploadfileurl, centralization_uploadfileurlinterval, centralization_settingsurl, centralization_usertrunkmappingurl, centralization_phonebookurl,
                              features_script, device_status, comment, tested_by
                          ) VALUES (
                              :created_on, :client_name, :location_name, :location_code, :licensee_name, :licensee_distributor, :licensee_dealer, :licensee_type,
                              :licensee_amctill, :licensee_validtill, :licensee_billno, :system_type, :system_os,
                              :system_isvm, :system_serialid, :system_uniqueid, :system_build_type, :system_debug, :board_type,
                              :system_ipsettings_type, :system_ipsettings_ip, :system_ipsettings_gateway, :system_ipsettings_dns,
                              :system_passwords_system, :system_passwords_web, :fetch_updates, :install_updates,
                              :engine_build,
                              :engine_graceperiod, :engine_maxports, :engine_validstarttz, :engine_validendtz,
                              :engine_validcountries, :device_id1, :ports_enabled_deviceid1, :device_id2, :ports_enabled_deviceid2, :device_id3, :ports_enabled_deviceid3, :device_id4, :ports_enabled_deviceid4,:device_id5, :ports_enabled_deviceid5, :device_id6, :ports_enabled_deviceid6,
                              :centralization_livestatusurl, :centralization_livestatusurlinterval, :centralization_uploadfileurl, :centralization_uploadfileurlinterval, :centralization_settingsurl, :centralization_usertrunkmappingurl, :centralization_phonebookurl,
                              :features_script, :device_status, :comment, :tested_by
                          )";
          }

          $stmt = $db->prepare($sql);
          $stmt->execute($params);

          // Get the license ID (either from edit or last insert)
          $licenseId = $editId ? $editId : $db->lastInsertId();

          // Insert comment into comments table if comment is not empty
          if (!empty($comment) && $licenseId) {
            try {
              $commentStmt = $db->prepare("
                INSERT INTO comments (license_id, comment, commented_by, created_at) 
                VALUES (:license_id, :comment, :commented_by, NOW())
              ");
              $commentStmt->execute([
                ':license_id' => $licenseId,
                ':comment' => $comment,
                ':commented_by' => $_SESSION['full_name'] ?? 'Unknown'
              ]);
            } catch (PDOException $e) {
              error_log('Comment insert failed: ' . $e->getMessage());
            }
          }

          // Insert into device_status table only if values are provided
          // Insert into device_status table only if values are provided
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
              error_log('Device Status insert failed: ' . $e->getMessage());
            }
          }
        } catch (PDOException $e) {
          error_log('Database insert/update failed: ' . $e->getMessage());
        }
      }
    }

    // Store result data instead of outputting immediately
    $submitResult = [
      'resultTag' => $resultTag,
      'errorTag' => $errorTag,
      'encoded' => $encoded,
      'code' => $code,
      'out' => $out,
      'err' => $err,
      'comment' => $info,
    ];
  }
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Create License</title>
  <!-- <link rel="stylesheet" href="css/style.css">
    <link rel="shortcut icon" href="images/favicon.png" /> -->
  <link rel="stylesheet" href="styles/users.css">
  <link rel="shortcut icon" href="images/favicon.png" />
  <link rel="stylesheet" href="styles/header-sidebar.css">
  <link rel="stylesheet" href="styles/common.css">
  <link rel="stylesheet" href="styles/licenses.css">
  <style>
    /* Ensure page scrolls even if global CSS disables it */
    html,
    body {
      height: 100%;
    }

    body {
      overflow-y: auto !important;
    }
  </style>
</head>

<body>
  <div class="page-containers">
    <div class="heading">
      <?php
      if ($isViewMode && $editId) {
        echo 'VIEW LICENSE';
      } else {
        echo $editId ? 'UPDATE LICENSE' : 'APPLY LICENSE';
      }
      ?>
    </div>
    <?php if ($errors): ?>
      <div
        style="background:#fee2e2; color:#991b1b; border:1px solid #fecaca; padding:12px 16px; border-radius:8px; margin-bottom:16px;">
        <?php foreach ($errors as $e): ?>
          <div><?php echo h($e); ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($submitResult !== null): ?>
      <div
        style="margin: auto; margin-top:50px; width:800px; border:1px solid #e5e7eb; border-radius:8px; padding:25px; background:#ffffff;">
        <h2 style="margin-bottom:16px; font-size:20px; font-weight:bold;">Result</h2>
        <?php
        $resultTag = $submitResult['resultTag'];
        $errorTag = $submitResult['errorTag'];
        $sentComment = $submitResult['comment'] ?? '';
        if ($resultTag !== '' || $errorTag !== ''):
          $bg = ($resultTag === 'SUCCESS') ? '#ecfdf5' : '#fef2f2';
          $border = ($resultTag === 'SUCCESS') ? '#a7f3d0' : '#fecaca';
          $color = ($resultTag === 'SUCCESS') ? '#065f46' : '#7f1d1d';
          ?>
          <div
            style="margin-bottom:16px; padding:12px 14px; border:1px solid <?php echo h($border); ?>; background:<?php echo h($bg); ?>; color:<?php echo h($color); ?>; border-radius:8px;">
            <?php if ($resultTag !== ''): ?>
              <div><strong>Result:</strong> <?php echo h($resultTag); ?></div>
            <?php endif; ?>
            <?php if ($errorTag !== ''): ?>
              <?php
              // Extract only the error message after "-> "
              $displayError = $errorTag;
              if (preg_match('/->\s*(.+)$/', $errorTag, $matches)) {
                $displayError = trim($matches[1]);
              }
              ?>
              <div style="margin-top:4px;"><strong>Reason:</strong> <?php echo h($displayError); ?></div>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <!-- <?php if ($sentComment !== ''): ?>
                    <div style="margin-bottom:12px;"><strong>Comment sent:</strong> <?php echo h($sentComment); ?></div>
                <?php endif; ?> -->

        <!-- <div style="margin-bottom:8px; font-weight:bold;">JSON sent</div>
                <pre style="background:#0f172a; color:#e2e8f0; padding:16px; border-radius:8px; overflow:auto; margin-bottom:16px;"><?php echo h($submitResult['encoded']); ?></pre> -->

        <!-- <div style="margin-bottom:8px;">Exit code: <?php echo h((string) $submitResult['code']); ?></div> -->

        <div style="margin:8px 0; font-weight:bold;">Server Response:</div>
        <pre
          style="background:#0f172a; color:#e2e8f0; padding:16px; border-radius:8px; overflow:auto; margin-bottom:16px;"><?php echo h($submitResult['out']); ?></pre>

        <!-- <?php if ($submitResult['err'] !== ''): ?>
                    <div style="margin:8px 0; font-weight:bold;">stderr:</div>
                    <pre style="background:#111827; color:#fecaca; padding:16px; border-radius:8px; overflow:auto; margin-bottom:16px;"><?php echo h($submitResult['err']); ?></pre>
                <?php endif; ?> -->

        <div style="margin-top:24px; text-align:center;">
          <a href="licenses.php"
            style="display:inline-block; padding:5px 18px; background:#2563eb; color:white; border:none; border-radius:6px; cursor:pointer; text-decoration:none;">Back</a>
        </div>
      </div>
    <?php endif; ?>

    <?php
    // Defaults per provided JSON
    $createdOn = date('Ymd');
    $defaults = [
      'CreatedOn' => $createdOn, // Current date in YYYYMMDD format
      'ClientName' => 'Sharekhan',
      'LocationName' => '',
      'LocationCode' => '',
      'BoardType' => 'Lichee Pi',
      'Licensee' => [
        'Name' => 'Xtend Technologies Pvt. Ltd.',
        'Distributor' => 'Xtend Technologies Pvt. Ltd.',
        'Dealer' => 'SHAREKHAN-',
        'Type' => 'Rental',
        'AMCTill' => date('Ymd', strtotime($createdOn . ' +1 year')),
        'ValidTill' => '20260401',
        'BillNo' => 'NA',
      ],
      'System' => [
        'Type' => 'Standalone',
        'OS' => 'Linux',
        'IsVM' => 'false',
        'SerialID' => '',
        'UniqueID' => '',
        'BuildType' => 'sharekhan',
        'Debug' => 0,
        'IPSettings' => [
          'Type' => 'DHCP',
          'IP' => '10.20.40.121/8',
          'Gateway' => '10.100.100.1',
          'Dns' => '8.8.8.8',
        ],
        'Passwords' => [
          'System' => 'Xtend123',
          'Web' => 'admin',
        ],
      ],
      'Engine' => [
        'Build' => 'Xtend IVR',
        'GracePeriod' => 30,
        'MaxPorts' => '',
        'ValidStartTZ' => 330,
        'ValidEndTZ' => 330,
        'ValidCountries' => 65,
      ],
      'Hardware' => [
        'Analog' => [
          '' => '',
        ],
      ],
      'Centralization' => [
        'LiveStatusUrl' => '',
        'LiveStatusUrlInterval' => '120',
        'UploadFileUrl' => '',
        'UploadFileUrlInterval' => '120',
        'SettingsUrl' => '',
        'UserTrunkMappingUrl' => '',
        'PhoneBookUrl' => '',
      ],
      'Features' => [
        'Script' => '',
      ],
      'DeviceStatus' => 'Testing',
    ];

    // Merge values from prefill if available
    $val = function (array $path, $fallback) use ($prefill) {
      if (is_array($prefill)) {
        $got = array_get($prefill, $path, null);
        if ($got !== null)
          return $got;
      }
      return $fallback;
    };

    // Resolve Hardware → Analog keys dynamically (union of defaults and prefill)
    $analogFromPrefill = [];
    if (is_array($prefill)) {
      $analogFromPrefill = array_get($prefill, ['Hardware', 'Analog'], []);
      if (!is_array($analogFromPrefill))
        $analogFromPrefill = [];
    }
    // Determine default device IDs and Ports
    $d1_val = '';
    $p1_val = '';
    $d2_val = '';
    $p2_val = '';
    $d3_val = '';
    $p3_val = '';
    $d4_val = '';
    $p4_val = '';
    $d5_val = '';
    $p5_val = '';
    $d6_val = '';
    $p6_val = '';

    // Override from prefill (DB or Remote)
    if (is_array($prefill)) {
      // From DB Columns (if loading edit)
      if (isset($prefill['device_id1']))
        $d1_val = $prefill['device_id1'];
      if (isset($prefill['ports_enabled_deviceid1']))
        $p1_val = $prefill['ports_enabled_deviceid1'];
      if (isset($prefill['device_id2']))
        $d2_val = $prefill['device_id2'];
      if (isset($prefill['ports_enabled_deviceid2']))
        $p2_val = $prefill['ports_enabled_deviceid2'];
      if (isset($prefill['device_id3']))
        $d3_val = $prefill['device_id3'];
      if (isset($prefill['ports_enabled_deviceid3']))
        $p3_val = $prefill['ports_enabled_deviceid3'];
      if (isset($prefill['device_id4']))
        $d4_val = $prefill['device_id4'];
      if (isset($prefill['ports_enabled_deviceid4']))
        $p4_val = $prefill['ports_enabled_deviceid4'];
      if (isset($prefill['device_id5']))
        $d5_val = $prefill['device_id5'];
      if (isset($prefill['ports_enabled_deviceid5']))
        $p5_val = $prefill['ports_enabled_deviceid5'];
      if (isset($prefill['device_id6']))
        $d6_val = $prefill['device_id6'];
      if (isset($prefill['ports_enabled_deviceid6']))
        $p6_val = $prefill['ports_enabled_deviceid6'];

      // Fallback or override from Hardware structure (e.g. remote fetch calls or legacy structure)
      // If we fetched from remote JSON, we might have keys in Hardware->Analog
      // We map the first key to Device1, second to Device2 if they exist and DB columns were empty/null (not typical for edit, but typical for fetch)
      // Since we prioritize DB edit vs fetch, let's treat fetch as overriding defaults ONLY if we are not in 'edit mode' per se... 
      // But $prefill is used for both.
      // Let's assume if 'Hardware'->'Analog' has data and DB columns are not set (which they won't be if it's a fresh remote fetch), we map them.
      if (isset($prefill['Hardware']['Analog']) && is_array($prefill['Hardware']['Analog']) && !isset($prefill['device_id1'])) {
        $keys = array_keys($prefill['Hardware']['Analog']);
        if (isset($keys[0])) {
          $d1_val = $keys[0];
          $p1_val = $prefill['Hardware']['Analog'][$keys[0]];
        }
        if (isset($keys[1])) {
          $d2_val = $keys[1];
          $p2_val = $prefill['Hardware']['Analog'][$keys[1]];
        }
        if (isset($keys[2])) {
          $d3_val = $keys[2];
          $p3_val = $prefill['Hardware']['Analog'][$keys[2]];
        }
        if (isset($keys[3])) {
          $d4_val = $keys[3];
          $p4_val = $prefill['Hardware']['Analog'][$keys[3]];
        }
      }
    }
    ?>
    <?php if ($submitResult === null): ?>
      <form method="post" id="licenseForm"
        style="display:block; margin:0px 10px 24px 10px; border:1px solid #e5e7eb; border-radius:12px; padding:24px; background:#ffffff; box-shadow:0 1px 3px rgba(0,0,0,0.1);">

        <input type="hidden" name="edit_id" value="<?php echo h($editId); ?>">

        <!-- General Section -->
        <div style="margin-bottom:28px; padding-bottom:20px; border-bottom:2px solid #f1f5f9;">
          <h3
            style="margin:0 0 16px 0; font-size:18px; font-weight:600; color:#1e293b; padding-bottom:8px; border-bottom:1px solid #e2e8f0;">
            General</h3>
          <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(250px, 1fr)); gap:16px;">
            <div>
              <label
                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">CreatedOn</label>
              <input type="text" name="CreatedOn" required readonly
                value="<?php echo h($val(['CreatedOn'], date('Ymd'))); ?>"
                style="width:75%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; background-color: #f1f5f9; cursor: not-allowed; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
            </div>
            <div>
              <label
                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">ClientName</label>
              <select name="ClientName" required
                style="width:75%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; background:#ffffff; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box; cursor:pointer;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
                <?php $clientName = (string) $val(['ClientName'], $defaults['ClientName']); ?>
                <?php if ($userRole !== 'Limited Access' || $clientName === 'Sharekhan'): ?>
                  <option value="Sharekhan" <?php echo ($clientName === 'Sharekhan') ? 'selected' : ''; ?>>Sharekhan</option>
                <?php endif; ?>
                <option value="Other" <?php echo ($clientName === 'Other') ? 'selected' : ''; ?>>Other</option>
              </select>
            </div>
            <div>
              <label
                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">LocationName</label>
              <input type="text" name="LocationName" required
                value="<?php echo h($val(['LocationName'], $defaults['LocationName'])); ?>"
                style="width:75%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
            </div>
            <div>
              <label
                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">LocationCode</label>
              <input type="text" name="LocationCode" required
                value="<?php echo h($val(['LocationCode'], $defaults['LocationCode'])); ?>"
                style="width:75%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
            </div>
            <div>
              <label
                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">BoardType</label>
              <select name="BoardType" required
                style="width:75%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; background:#ffffff; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box; cursor:pointer;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
                <?php $boardType = (string) $val(['BoardType'], $defaults['BoardType']); ?>
                <option value="Lichee Pi" <?php echo ($boardType === 'Lichee Pi') ? 'selected' : ''; ?>>Lichee Pi</option>
                <option value="Tibbo" <?php echo ($boardType === 'Tibbo') ? 'selected' : ''; ?>>Tibbo</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Licensee Section -->
        <div style="margin-bottom:28px; padding-bottom:20px; border-bottom:2px solid #f1f5f9;">
          <h3
            style="margin:0 0 16px 0; font-size:18px; font-weight:600; color:#1e293b; padding-bottom:8px; border-bottom:1px solid #e2e8f0;">
            Licensee</h3>
          <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(250px, 1fr)); gap:16px;">
            <div>
              <label
                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Licensee[Name]</label>
              <input type="text" name="Licensee[Name]" required
                value="<?php echo h($val(['Licensee', 'Name'], $defaults['Licensee']['Name'])); ?>"
                style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
            </div>
            <div>
              <label
                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Licensee[Distributor]</label>
              <input type="text" name="Licensee[Distributor]" required
                value="<?php echo h($val(['Licensee', 'Distributor'], $defaults['Licensee']['Distributor'])); ?>"
                style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
            </div>
            <div>
              <label
                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Licensee[Dealer]</label>
              <input type="text" name="Licensee[Dealer]" required
                value="<?php echo h($val(['Licensee', 'Dealer'], $defaults['Licensee']['Dealer'])); ?>"
                style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
            </div>
            <div>
              <label
                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Licensee[Type]</label>
              <select name="Licensee[Type]" required
                style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; background:#ffffff; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box; cursor:pointer;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
                <?php $licenseeType = (string) $val(['Licensee', 'Type'], $defaults['Licensee']['Type']); ?>
                <option value="Rental" <?php echo ($licenseeType === 'Rental') ? 'selected' : ''; ?>>Rental</option>
                <option value="Purchase" <?php echo ($licenseeType === 'Purchase') ? 'selected' : ''; ?>>Purchase</option>
                <option value="Permanent" <?php echo ($licenseeType === 'Permanent') ? 'selected' : ''; ?>>Permanent
                </option>
              </select>
            </div>
            <div>
              <label
                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Licensee[AMCTill]</label>
              <input type="text" maxlength="8" name="Licensee[AMCTill]" required
                value="<?php echo h($val(['Licensee', 'AMCTill'], $defaults['Licensee']['AMCTill'])); ?>"
                style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
            </div>
            <div>
              <label
                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Licensee[ValidTill]</label>
              <input type="text" maxlength="8" name="Licensee[ValidTill]" required
                value="<?php echo h($val(['Licensee', 'ValidTill'], $defaults['Licensee']['ValidTill'])); ?>"
                placeholder="YYYYMMDD"
                style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
            </div>
            <div>
              <label
                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Licensee[BillNo]</label>
              <input type="text" name="Licensee[BillNo]" required
                value="<?php echo h($val(['Licensee', 'BillNo'], $defaults['Licensee']['BillNo'])); ?>"
                style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
            </div>
          </div>
        </div>

        <!-- System Section -->
        <div style="margin-bottom:28px; padding-bottom:20px; border-bottom:2px solid #f1f5f9;">
          <h3
            style="margin:0 0 16px 0; font-size:18px; font-weight:600; color:#1e293b; padding-bottom:8px; border-bottom:1px solid #e2e8f0;">
            System</h3>
          <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(250px, 1fr)); gap:16px;">
            <div>
              <label
                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">System[Type]</label>
              <select name="System[Type]" required
                style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; background:#ffffff; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box; cursor:pointer;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
                <?php $systemType = (string) $val(['System', 'Type'], $defaults['System']['Type']); ?>
                <option value="Desktop" <?php echo ($systemType === 'Desktop') ? 'selected' : ''; ?>>Desktop</option>
                <option value="Standalone" <?php echo ($systemType === 'Standalone') ? 'selected' : ''; ?>>Standalone
                </option>
              </select>
            </div>
            <div>
              <label
                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">System[OS]</label>
              <select name="System[OS]" required
                style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; background:#ffffff; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box; cursor:pointer;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
                <?php $systemOS = (string) $val(['System', 'OS'], $defaults['System']['OS']); ?>
                <option value="Windows" <?php echo ($systemOS === 'Windows') ? 'selected' : ''; ?>>Windows</option>
                <option value="Linux" <?php echo ($systemOS === 'Linux') ? 'selected' : ''; ?>>Linux</option>
              </select>
            </div>
            <div>
              <label
                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">System[IsVM]</label>
              <select name="System[IsVM]" required
                style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; background:#ffffff; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box; cursor:pointer;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
                <?php
                $isvm = (string) $val(['System', 'IsVM'], $defaults['System']['IsVM']);
                // Handle both old format (true/false) and new format (Yes/No)
                $isvmNormalized = strtolower($isvm);
                $isSelected = ($isvmNormalized === 'true' || $isvmNormalized === 'true' || $isvmNormalized === '1');
                ?>
                <option value="false" <?php echo !$isSelected ? 'selected' : ''; ?>>false</option>
                <option value="true" <?php echo $isSelected ? 'selected' : ''; ?>>true</option>
              </select>
            </div>
            <div>
              <label
                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">System[SerialID]</label>
              <div style="position:relative;">
                <input type="text" name="System[SerialID]" id="SystemSerialID" required
                  value="<?php echo h($val(['System', 'SerialID'], $defaults['System']['SerialID'])); ?>"
                  style="width:100%; padding:10px 36px 10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;"
                  onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                  onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
                <?php if (empty($editId)): ?>
                  <span onclick="checkSerialID()" title="Check availability"
                    style="position:absolute; right:10px; top:50%; transform:translateY(-50%); cursor:pointer; color:#3b82f6;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
                      <path fill-rule="evenodd" d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .908-.417A6 6 0 1 1 8 2v1z" />
                      <path
                        d="M8 4.466V.534a.25.25 0 0 1 .41-.192l2.36 1.966c.12.1.12.284 0 .384L8.41 4.658A.25.25 0 0 1 8 4.466z" />
                    </svg>
                  </span>
                <?php endif; ?>
              </div>
              <div id="serialIdCheckResult" style="font-size:12px; margin-top:4px; display:none;"></div>
            </div>
            <div>
              <label
                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">System[UniqueID]</label>
              <input type="text" name="System[UniqueID]" required
                value="<?php echo h($val(['System', 'UniqueID'], $defaults['System']['UniqueID'])); ?>"
                style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
            </div>


            <div>
              <label
                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">System[BuildType]</label>
              <input type="text" name="System[BuildType]" required
                value="<?php echo h($val(['System', 'BuildType'], $defaults['System']['BuildType'])); ?>"
                style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
            </div>

            <div>
              <label
                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">System[Debug]</label>
              <select name="System[Debug]" required
                style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; background:#ffffff; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box; cursor:pointer;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
                <?php $debugVal = (int) $val(['System', 'Debug'], $defaults['System']['Debug']); ?>
                <option value="0" <?php echo ($debugVal === 0) ? 'selected' : ''; ?>>0</option>
                <option value="1" <?php echo ($debugVal === 1) ? 'selected' : ''; ?>>1</option>
              </select>
            </div>

            <!-- System IPSettings Fields -->
            <div>
              <label
                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">System[IPSettings][Type]</label>
              <select name="System[IPSettings][Type]" required id="ipSettingsType" onchange="toggleIPSettings()"
                style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; background:#ffffff; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box; cursor:pointer;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
                <?php $ipType = (string) $val(['System', 'IPSettings', 'Type'], $defaults['System']['IPSettings']['Type']); ?>
                <option value="Static" <?php echo ($ipType === 'Static') ? 'selected' : ''; ?>>Static</option>
                <option value="DHCP" <?php echo ($ipType === 'DHCP') ? 'selected' : ''; ?>>DHCP</option>
              </select>
            </div>
            <div>
              <label
                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">System[IPSettings][IP]</label>
              <input type="text" name="System[IPSettings][IP]" required id="ipSettingsIP"
                value="<?php echo h($val(['System', 'IPSettings', 'IP'], $defaults['System']['IPSettings']['IP'])); ?>"
                style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
            </div>
            <div>
              <label
                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">System[IPSettings][Gateway]</label>
              <input type="text" name="System[IPSettings][Gateway]" required id="ipSettingsGateway"
                value="<?php echo h($val(['System', 'IPSettings', 'Gateway'], $defaults['System']['IPSettings']['Gateway'])); ?>"
                style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
            </div>
            <div>
              <label
                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">System[IPSettings][Dns]</label>
              <input type="text" name="System[IPSettings][Dns]" required id="ipSettingsDns"
                value="<?php echo h($val(['System', 'IPSettings', 'Dns'], $defaults['System']['IPSettings']['Dns'])); ?>"
                style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
            </div>
            <div>
              <label
                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">System[Passwords][System]</label>
              <input type="text" name="System[Passwords][System]" required
                value="<?php echo h($val(['System', 'Passwords', 'System'], $defaults['System']['Passwords']['System'])); ?>"
                style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
            </div>
            <div>
              <label
                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">System[Passwords][Web]</label>
              <input type="text" name="System[Passwords][Web]" required
                value="<?php echo h($val(['System', 'Passwords', 'Web'], $defaults['System']['Passwords']['Web'])); ?>"
                style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
            </div>
            <div>
              <label
                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">System[FetchUpdates]</label>
              <select name="System[FetchUpdates]" required
                style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; background:#ffffff; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box; cursor:pointer;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
                <?php $fetchUpd = (int) $val(['System', 'FetchUpdates'], $defaults['System']['FetchUpdates'] ?? 0); ?>
                <option value="0" <?php echo ($fetchUpd === 0) ? 'selected' : ''; ?>>0</option>
                <option value="1" <?php echo ($fetchUpd === 1) ? 'selected' : ''; ?>>1</option>
              </select>
            </div>
            <div>
              <label
                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">System[InstallUpdates]</label>
              <select name="System[InstallUpdates]" required
                style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; background:#ffffff; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box; cursor:pointer;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
                <?php $installUpd = (int) $val(['System', 'InstallUpdates'], $defaults['System']['InstallUpdates'] ?? 0); ?>
                <option value="0" <?php echo ($installUpd === 0) ? 'selected' : ''; ?>>0</option>
                <option value="1" <?php echo ($installUpd === 1) ? 'selected' : ''; ?>>1</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Engine Section -->
        <div style="margin-bottom:28px; padding-bottom:20px; border-bottom:2px solid #f1f5f9;">
          <h3
            style="margin:0 0 16px 0; font-size:18px; font-weight:600; color:#1e293b; padding-bottom:8px; border-bottom:1px solid #e2e8f0;">
            Engine</h3>
          <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(250px, 1fr)); gap:16px;">
            <div>
              <label
                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Engine[Build]</label>
              <input type="text" name="Engine[Build]" required
                value="<?php echo h($val(['Engine', 'Build'], $defaults['Engine']['Build'])); ?>"
                style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
            </div>
            <div>
              <label
                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Engine[GracePeriod]</label>
              <input type="number" name="Engine[GracePeriod]" required
                value="<?php echo h((string) $val(['Engine', 'GracePeriod'], $defaults['Engine']['GracePeriod'])); ?>"
                style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
            </div>
            <div>
              <label
                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Engine[MaxPorts]</label>
              <input type="text" name="Engine[MaxPorts]" required
                value="<?php echo h($val(['Engine', 'MaxPorts'], $defaults['Engine']['MaxPorts'])); ?>"
                style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
            </div>
            <div>
              <label
                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Engine[ValidStartTZ]</label>
              <input type="number" name="Engine[ValidStartTZ]" required
                value="<?php echo h((string) $val(['Engine', 'ValidStartTZ'], $defaults['Engine']['ValidStartTZ'])); ?>"
                style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
            </div>
            <div>
              <label
                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Engine[ValidEndTZ]</label>
              <input type="number" name="Engine[ValidEndTZ]" required
                value="<?php echo h((string) $val(['Engine', 'ValidEndTZ'], $defaults['Engine']['ValidEndTZ'])); ?>"
                style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
            </div>
            <div>
              <label
                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Engine[ValidCountries]</label>
              <input type="number" name="Engine[ValidCountries]" required
                value="<?php echo h((string) $val(['Engine', 'ValidCountries'], $defaults['Engine']['ValidCountries'])); ?>"
                style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
            </div>
          </div>
        </div>

        <!-- Hardware → Analog Section -->
        <div style="margin-bottom:28px; padding-bottom:20px; border-bottom:2px solid #f1f5f9;">
          <h3
            style="margin:0 0 16px 0; font-size:18px; font-weight:600; color:#1e293b; padding-bottom:8px; border-bottom:1px solid #e2e8f0;">
            Hardware</h3>
          <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:16px;">
            <!-- Device 1 -->
            <div>
              <label style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Device ID
                1</label>
              <input type="number" name="device_id1" required value="<?php echo h($d1_val); ?>"
                style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
            </div>
            <div>
              <label style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Device ID 1
                [PortsEnabled]</label>
              <input type="text" maxlength="16" required name="ports_enabled_deviceid1" id="ports_enabled_deviceid1"
                value="<?php echo h($p1_val); ?>"
                style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                onblur="validatePorts(this); this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
              <div id="error_ports_enabled_deviceid1"
                style="color:#ef4444; font-size:12px; margin-top:4px; display:none;">
                Must be exactly 16 characters of 0 and 1.
              </div>
            </div>
            <!-- <p></p> -->
            <!-- Device 2 -->
            <div>
              <label style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Device ID
                2</label>
              <input type="number" name="device_id2" value="<?php echo h($d2_val); ?>"
                style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
            </div>

            <div>
              <label style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Device ID 2
                [PortsEnabled]</label>
              <input type="text" maxlength="16" name="ports_enabled_deviceid2" id="ports_enabled_deviceid2"
                value="<?php echo h($p2_val); ?>"
                style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                onblur="validatePorts(this); this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
              <div id="error_ports_enabled_deviceid2"
                style="color:#ef4444; font-size:12px; margin-top:4px; display:none;">
                Must be exactly 16 characters of 0 and 1.
              </div>
            </div>

            <!-- Device 3 -->
            <div>
              <label style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Device ID
                3</label>
              <input type="number" name="device_id3" value="<?php echo h($d3_val); ?>"
                style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
            </div>

            <div>
              <label style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Device ID 3
                [PortsEnabled]</label>
              <input type="text" maxlength="16" name="ports_enabled_deviceid3" id="ports_enabled_deviceid3"
                value="<?php echo h($p3_val); ?>"
                style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                onblur="validatePorts(this); this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
              <div id="error_ports_enabled_deviceid3"
                style="color:#ef4444; font-size:12px; margin-top:4px; display:none;">
                Must be exactly 16 characters of 0 and 1.
              </div>
            </div>

            <!-- Device 4 -->
            <div>
              <label style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Device ID
                4</label>
              <input type="number" name="device_id4" value="<?php echo h($d4_val); ?>"
                style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
            </div>

            <div>
              <label style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Device ID 4
                [PortsEnabled]</label>
              <input type="text" maxlength="16" name="ports_enabled_deviceid4" id="ports_enabled_deviceid4"
                value="<?php echo h($p4_val); ?>"
                style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                onblur="validatePorts(this); this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
              <div id="error_ports_enabled_deviceid4"
                style="color:#ef4444; font-size:12px; margin-top:4px; display:none;">
                Must be exactly 16 characters of 0 and 1.
              </div>
            </div>

            <!-- Device 5 -->
            <div>
              <label style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Device ID
                5</label>
              <input type="number" name="device_id5" value="<?php echo h($d5_val); ?>"
                style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
            </div>

            <div>
              <label style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Device ID 5
                [PortsEnabled]</label>
              <input type="text" maxlength="16" name="ports_enabled_deviceid5" id="ports_enabled_deviceid5"
                value="<?php echo h($p5_val); ?>"
                style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                onblur="validatePorts(this); this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
              <div id="error_ports_enabled_deviceid5"
                style="color:#ef4444; font-size:12px; margin-top:4px; display:none;">
                Must be exactly 16 characters of 0 and 1.
              </div>
            </div>

            <!-- Device 6 -->
            <div>
              <label style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Device ID
                6</label>
              <input type="number" name="device_id6" value="<?php echo h($d6_val); ?>"
                style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
            </div>

            <div>
              <label style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Device ID 6
                [PortsEnabled]</label>
              <input type="text" maxlength="16" name="ports_enabled_deviceid6" id="ports_enabled_deviceid6"
                value="<?php echo h($p6_val); ?>"
                style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                onblur="validatePorts(this); this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
              <div id="error_ports_enabled_deviceid6"
                style="color:#ef4444; font-size:12px; margin-top:4px; display:none;">
                Must be exactly 16 characters of 0 and 1.
              </div>
            </div>
          </div>
        </div>

        <!-- Centralization Section -->
        <div style="margin-bottom:28px; padding-bottom:20px; border-bottom:2px solid #f1f5f9;">
          <h3
            style="margin:0 0 16px 0; font-size:18px; font-weight:600; color:#1e293b; padding-bottom:8px; border-bottom:1px solid #e2e8f0;">
            Centralization</h3>
          <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:16px;">
            <!-- IP/Domain Helper Field -->
            <div style="grid-column: 1 / -1;">
              <label
                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">IP/Domain</label>
              <div style="display:flex; align-items:center; gap:12px;">
                <input type="text" id="centralization_ip_domain" placeholder="Enter IP or Domain"
                  style="width:50%; max-width: 300px; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;"
                  onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                  onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">

                <label style="display:flex; align-items:center; cursor:pointer; gap:6px; user-select:none;">
                  <input type="checkbox" id="centralization_apply_checkbox" onchange="applyCentralizationIP()"
                    style="width:16px; height:16px; cursor:pointer;">
                  <span style="font-size:14px; color:#475569;">Apply for below fields</span>
                </label>
              </div>
            </div>
            <div>
              <label
                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Centralization[LiveStatusUrl]</label>
              <input type="text" name="Centralization[LiveStatusUrl]" required
                value="<?php echo h($val(['Centralization', 'LiveStatusUrl'], $defaults['Centralization']['LiveStatusUrl'])); ?>"
                style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
            </div>
            <div>
              <label
                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Centralization[LiveStatusUrlInterval]</label>
              <input type="number" name="Centralization[LiveStatusUrlInterval]"
                value="<?php echo h($val(['Centralization', 'LiveStatusUrlInterval'], $defaults['Centralization']['LiveStatusUrlInterval'])); ?>"
                style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
            </div>
            <div>
              <label
                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Centralization[UploadFileUrl]</label>
              <input type="text" name="Centralization[UploadFileUrl]" required
                value="<?php echo h($val(['Centralization', 'UploadFileUrl'], $defaults['Centralization']['UploadFileUrl'])); ?>"
                style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
            </div>
            <div>
              <label
                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Centralization[UploadFileUrlInterval]</label>
              <input type="number" name="Centralization[UploadFileUrlInterval]"
                value="<?php echo h($val(['Centralization', 'UploadFileUrlInterval'], $defaults['Centralization']['UploadFileUrlInterval'])); ?>"
                style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
            </div>
            <div>
              <label
                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Centralization[SettingsUrl]</label>
              <input type="text" name="Centralization[SettingsUrl]" required
                value="<?php echo h($val(['Centralization', 'SettingsUrl'], $defaults['Centralization']['SettingsUrl'])); ?>"
                style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
            </div>
            <div>
              <label
                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Centralization[UserTrunkMappingUrl]</label>
              <input type="text" name="Centralization[UserTrunkMappingUrl]" required
                value="<?php echo h($val(['Centralization', 'UserTrunkMappingUrl'], $defaults['Centralization']['UserTrunkMappingUrl'])); ?>"
                style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
            </div>
            <div>
              <label
                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Centralization[PhoneBookUrl]</label>
              <input type="text" name="Centralization[PhoneBookUrl]" required
                value="<?php echo h($val(['Centralization', 'PhoneBookUrl'], $defaults['Centralization']['PhoneBookUrl'])); ?>"
                style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
            </div>
          </div>
        </div>

        <!-- Features Section -->
        <div style="margin-bottom:28px; padding-bottom:20px; border-bottom:2px solid #f1f5f9;">
          <h3
            style="margin:0 0 16px 0; font-size:18px; font-weight:600; color:#1e293b; padding-bottom:8px; border-bottom:1px solid #e2e8f0;">
            Features</h3>
          <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(250px, 1fr)); gap:16px;">
            <div>
              <label
                style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Features[Script]</label>
              <input type="text" name="Features[Script]" required
                value="<?php echo h($val(['Features', 'Script'], $defaults['Features']['Script'])); ?>"
                style="width:25%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
            </div>
          </div>
        </div>

        <!-- Device Status Section -->
        <div style="margin-bottom:16px; display:flex; gap:16px; align-items:flex-end;">
          <div>
            <label
              style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">DeviceStatus</label>

            <div style="display:flex; align-items:center; gap:8px;">
              <select name="DeviceStatus" id="DeviceStatusSelect"
                style="padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; background:#ffffff; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box; min-width:180px;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
                <option value="" selected>Select Status</option>
                <option value="Testing">Testing</option>
                <option value="Ready For Dispatch">Ready For Dispatch</option>
                <option value="On Hold">On Hold</option>
                <option value="In Progress">In Progress</option>
                <option value="Installed">Installed</option>
                <option value="Serviced">Serviced</option>
                <option value="Replaced">Replaced</option>
              </select>
            </div>
          </div>
          <div>
            <label
              style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">StatusDate</label>
            <div style="display:flex; align-items:center; gap:8px;">
              <input type="date" name="StatusDate" id="StatusDateInput" value=""
                style="width:140px; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; background:#ffffff; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;"
                onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
                onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
              <?php if ($editId): ?>
                <div style="cursor:pointer; color:#64748b; display:flex; align-items:center;"
                  onclick="showDeviceStatusPopup()" title="View Device Status History">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                  </svg>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Comment Section -->
        <div style="margin-bottom:24px;">
          <label style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Comment</label>
          <div style="display:flex; align-items:center; gap:10px;">
            <input type="text" name="comment" value=""
              style="width:25%; max-width:500px; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;"
              onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
              onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
            <?php if ($editId): ?>
              <div style="cursor:pointer; color:#64748b; display:flex; align-items:center;" onclick="showCommentPopup()"
                title="View Previous Comments">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                  stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- TestedBy Section -->
        <div style="margin-bottom:24px;">
          <label
            style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">TestedBy</label>
          <input type="text" name="TestedBy" value="<?php echo h($testedByValue); ?>"
            style="width:25%; max-width:500px; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;"
            onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';"
            onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
        </div>

        <!-- Action Buttons -->
        <?php if (!$isViewMode): ?>
          <div
            style="margin-top:24px; padding-top:20px; border-top:2px solid #f1f5f9; display:flex; gap:12px; flex-wrap:wrap; justify-content:center;">
            <button type="submit" name="action" class="btn-submit"
              value="send"><?php echo $editId ? 'Update License' : 'Submit'; ?></button>
          </div>
        <?php endif; ?>
      </form>
    <?php endif; ?>
  </div>

  <!-- Comment Popup -->
  <div id="commentPopup"
    style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; justify-content:center; align-items:center;">
    <div
      style="background:white; padding:24px; border-radius:12px; max-width:800px; max-height: 60vh; width:90%; box-shadow:0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); display: flex; flex-direction: column;">
      <h3 style="margin:0 0 16px 0; font-size:18px; font-weight:600; color:#1e293b; flex-shrink: 0;">Comment History
      </h3>
      <div id="existingCommentText" style="margin-bottom:24px; overflow-y:auto; flex: 1; min-height: 0;">
      </div>
      <div style="text-align:right; flex-shrink: 0;">
        <button type="button" onclick="closeCommentPopup()"
          style="padding:8px 16px; background:#f16767; color:#fff; border:none; border-radius:6px; cursor:pointer; font-weight:600; transition:background-color 0.2s;">Close</button>
      </div>
    </div>
  </div>

  <!-- Device Status Popup -->
  <div id="deviceStatusPopup"
    style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; justify-content:center; align-items:center;">
    <div
      style="background:white; padding:24px; border-radius:12px; max-width:800px; max-height: 60vh; width:90%; box-shadow:0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); display: flex; flex-direction: column;">
      <h3 style="margin:0 0 16px 0; font-size:18px; font-weight:600; color:#1e293b; flex-shrink: 0;">Device Status
        History
      </h3>
      <div id="existingDeviceStatusText" style="margin-bottom:24px; overflow-y:auto; flex: 1; min-height: 0;">
      </div>
      <div style="text-align:right; flex-shrink: 0;">
        <button type="button" onclick="closeDeviceStatusPopup()"
          style="padding:8px 16px; background:#f16767; color:#fff; border:none; border-radius:6px; cursor:pointer; font-weight:600; transition:background-color 0.2s;">Close</button>
      </div>
    </div>
  </div>

  <?php include 'components/header-sidebar.php'; ?>
  <script>
    async function showCommentPopup() {
      const licenseId = '<?php echo h($editId); ?>';
      if (!licenseId) return;

      try {
        const response = await fetch(`api/comments.php?license_id=${licenseId}`);
        const comments = await response.json();

        let html = '';
        if (comments.length === 0) {
          html = '<p style="color:#94a3b8; font-style:italic; text-align:center; padding:20px;">No previous comments</p>';
        } else {
          html = `
            <table style="width:100%; border-collapse:collapse; font-size:14px;">
              <thead>
                <tr style="background:#f8fafc; border-bottom:2px solid #e2e8f0;">
                  <th style="padding:12px 8px; text-align:left; font-weight:600; color:#475569; width:50px;">Sl No</th>
                  <th style="padding:12px 8px; text-align:left; font-weight:600; color:#475569; width:150px;">Commented By</th>
                  <th style="padding:12px 8px; text-align:left; font-weight:600; color:#475569; width:150px;">Date/Time</th>
                  <th style="padding:12px 8px; text-align:left; font-weight:600; color:#475569;">Comment</th>
                </tr>
              </thead>
              <tbody>
          `;

          comments.forEach((comment, index) => {
            html += `
              <tr style="border-bottom:1px solid #e2e8f0;">
                <td style="padding:12px 8px; color:#64748b;">${index + 1}</td>
                <td style="padding:12px 8px; color:#1e293b; font-weight:500;">${comment.commented_by || 'Unknown'}</td>
                <td style="padding:12px 8px; color:#64748b; font-size:12px;">${comment.formatted_date || comment.created_at}</td>
                <td style="padding:12px 8px; color:#475569;">${comment.comment}</td>
              </tr>
            `;
          });

          html += `
              </tbody>
            </table>
          `;
        }

        document.getElementById('existingCommentText').innerHTML = html;
        document.getElementById('commentPopup').style.display = 'flex';
      } catch (error) {
        console.error('Error fetching comments:', error);
        document.getElementById('existingCommentText').innerHTML = '<p style="color:#ef4444; text-align:center; padding:20px;">Error loading comments</p>';
        document.getElementById('commentPopup').style.display = 'flex';
      }
    }

    function closeCommentPopup() {
      document.getElementById('commentPopup').style.display = 'none';
    }

    document.getElementById('commentPopup').addEventListener('click', function (e) {
      if (e.target === this) {
        closeCommentPopup();
      }
    });

    async function showDeviceStatusPopup() {
      const licenseId = '<?php echo h($editId); ?>';
      if (!licenseId) return;

      try {
        const response = await fetch(`api/device_status.php?license_id=${licenseId}`);
        const rows = await response.json();

        let html = '';
        if (rows.length === 0) {
          html = '<p style="color:#94a3b8; font-style:italic; text-align:center; padding:20px;">No previous status history</p>';
        } else {
          html = `
            <table style="width:100%; border-collapse:collapse; font-size:14px;">
              <thead>
                <tr style="background:#f8fafc; border-bottom:2px solid #e2e8f0;">
                  <th style="padding:12px 8px; text-align:left; font-weight:600; color:#475569; width:50px;">Sl No</th>
                  <th style="padding:12px 8px; text-align:left; font-weight:600; color:#475569; width:150px;">User</th>
                  <th style="padding:12px 8px; text-align:left; font-weight:600; color:#475569; width:150px;">Date/Time</th>
                  <th style="padding:12px 8px; text-align:left; font-weight:600; color:#475569;">Status</th>
                </tr>
              </thead>
              <tbody>
          `;

          rows.forEach((row, index) => {
            html += `
              <tr style="border-bottom:1px solid #e2e8f0;">
                <td style="padding:12px 8px; color:#64748b;">${index + 1}</td>
                <td style="padding:12px 8px; color:#1e293b; font-weight:500;">${row.user || 'Unknown'}</td>
                <td style="padding:12px 8px; color:#64748b; font-size:12px;">${row.formatted_date || row.date}</td>
                <td style="padding:12px 8px; color:#475569;">${row.status}</td>
              </tr>
            `;
          });

          html += `
              </tbody>
            </table>
          `;
        }

        document.getElementById('existingDeviceStatusText').innerHTML = html;
        document.getElementById('deviceStatusPopup').style.display = 'flex';
      } catch (error) {
        console.error('Error fetching device status:', error);
        document.getElementById('existingDeviceStatusText').innerHTML = '<p style="color:#ef4444; text-align:center; padding:20px;">Error loading status history</p>';
        document.getElementById('deviceStatusPopup').style.display = 'flex';
      }
    }

    function closeDeviceStatusPopup() {
      document.getElementById('deviceStatusPopup').style.display = 'none';
    }

    document.getElementById('deviceStatusPopup').addEventListener('click', function (e) {
      if (e.target === this) {
        closeDeviceStatusPopup();
      }
    });

    function applyCentralizationIP() {
      const ipInput = document.getElementById('centralization_ip_domain');
      const checkBox = document.getElementById('centralization_apply_checkbox');

      if (!checkBox || !checkBox.checked) return;

      const newIP = ipInput.value.trim();
      if (!newIP) {
        alert("Please enter an IP or Domain first.");
        checkBox.checked = false;
        return;
      }

      const urlInputs = [
        'Centralization[LiveStatusUrl]',
        'Centralization[UploadFileUrl]',
        'Centralization[SettingsUrl]',
        'Centralization[UserTrunkMappingUrl]',
        'Centralization[PhoneBookUrl]'
      ];

      urlInputs.forEach(name => {
        const input = document.querySelector(`input[name="${name}"]`);
        if (input) {
          input.value = newIP;
        }
      });

      // Uncheck to show it was a one-time action
      setTimeout(() => { checkBox.checked = false; }, 300);
    }

    function validatePorts(input) {
      const val = input.value.trim();
      const errorDiv = document.getElementById('error_' + input.id);
      const maxLen = input.maxLength || 8;

      // If empty, we consider it valid (optional) unless we want to enforce mandatory. 
      // User request strictly says "should have only 8 characters with 0 and 1".
      // Assuming if filled, it must be correct.
      if (val === '') {
        if (errorDiv) errorDiv.style.display = 'none';
        return true;
      }

      // Check for exactly maxLen characters consisting of 0 or 1
      const regex = new RegExp(`^[01]{${maxLen}}$`);
      if (!regex.test(val)) {
        if (errorDiv) {
          errorDiv.innerText = `Must be exactly ${maxLen} characters of 0 and 1.`;
          errorDiv.style.display = 'block';
        }
        return false;
      } else {
        if (errorDiv) errorDiv.style.display = 'none';
        return true;
      }
    }

    function toggleIPSettings() {
      const typeSelect = document.getElementById('ipSettingsType');
      const ipInput = document.getElementById('ipSettingsIP');
      const gatewayInput = document.getElementById('ipSettingsGateway');
      const dnsInput = document.getElementById('ipSettingsDns');

      if (typeSelect && ipInput && gatewayInput && dnsInput) {
        // Prevent logic execution if strict view mode
        const isViewMode = <?php echo $isViewMode ? 'true' : 'false'; ?>;
        if (isViewMode) return;

        const isDHCP = typeSelect.value === 'DHCP';
        if (isDHCP) {
          ipInput.value = '';
          gatewayInput.value = '';
          dnsInput.value = '';
          ipInput.disabled = true;
          gatewayInput.disabled = true;
          dnsInput.disabled = true;
          ipInput.style.backgroundColor = '#f1f5f9';
          gatewayInput.style.backgroundColor = '#f1f5f9';
          dnsInput.style.backgroundColor = '#f1f5f9';
        } else {
          ipInput.disabled = false;
          gatewayInput.disabled = false;
          dnsInput.disabled = false;
          ipInput.style.backgroundColor = '';
          gatewayInput.style.backgroundColor = '';
          dnsInput.style.backgroundColor = '';

          // Auto-fill defaults if empty when switching to Static
          if (ipInput.value === '') ipInput.value = '10.20.40.121/8';
          if (gatewayInput.value === '') gatewayInput.value = '10.100.100.1';
          if (dnsInput.value === '') dnsInput.value = '8.8.8.8';
        }
      }
    }



    document.addEventListener('DOMContentLoaded', function () {
      const isViewMode = <?php echo $isViewMode ? 'true' : 'false'; ?>;

      toggleIPSettings();

      if (isViewMode) {
        // Disable all form inputs
        const inputs = document.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
          input.disabled = true;
          input.setAttribute('readonly', 'readonly');
          input.style.backgroundColor = '#f1f5f9';
          input.style.cursor = 'not-allowed';
        });

        // Ensure IP Settings stay disabled even if toggle logic ran
        const ipType = document.getElementById('ipSettingsType');
        if (ipType) ipType.disabled = true;
      }

      const form = document.getElementById('licenseForm');
      if (form) {
        form.addEventListener('submit', function (e) {
          // Validate Ports before submission
          const p1 = document.getElementById('ports_enabled_deviceid1');
          const p2 = document.getElementById('ports_enabled_deviceid2');

          let valid1 = true;
          let valid2 = true;

          if (p1) valid1 = validatePorts(p1);
          if (p2) valid2 = validatePorts(p2);
          const p3 = document.getElementById('ports_enabled_deviceid3');
          const p4 = document.getElementById('ports_enabled_deviceid4');
          let valid3 = true;
          let valid4 = true;
          if (p3) valid3 = validatePorts(p3);
          if (p4) valid4 = validatePorts(p4);

          if (!valid1 || !valid2 || !valid3 || !valid4) {
            e.preventDefault();
            alert('Please correct the errors in Hardware PortsEnabled fields.');
            return;
          }

          const btn = e.submitter;
          // Check if the submitter is the 'Submit' button (name='action', value='send')
          if (btn && btn.name === 'action' && btn.value === 'send') {
            btn.innerText = 'Submitting...';
            // Disable the button after a microtask to ensure the form submission includes the button's value
            setTimeout(() => {
              btn.disabled = true;
              btn.style.opacity = '0.7';
              btn.style.cursor = 'not-allowed';
            }, 0);
          }
        });
      }
    }
    );
    function checkSerialID() {
      const input = document.getElementById('SystemSerialID');
      const resultDiv = document.getElementById('serialIdCheckResult');
      const serialId = input.value.trim();

      if (!serialId) {
        alert('Please enter a Serial ID');
        return;
      }

      resultDiv.style.display = 'none';
      resultDiv.className = '';

      fetch('api/check_serial.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ serial_id: serialId })
      })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            resultDiv.style.display = 'block';
            if (data.exists) {
              resultDiv.style.color = '#ef4444';
              resultDiv.textContent = 'Serial ID already exists.';
            } else {
              resultDiv.style.color = '#10b981';
              resultDiv.textContent = 'Serial ID is available.';
            }
          } else {
            alert('Error checking Serial ID: ' + (data.message || 'Unknown error'));
          }
        })
        .catch(error => {
          console.error('Error:', error);
          alert('An error occurred while checking Serial ID');
        });
    }
  </script>
</body>

</html>