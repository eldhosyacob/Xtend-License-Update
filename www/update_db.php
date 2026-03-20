<?php
declare(strict_types=1);
require_once('config/auth_check.php');
require_once('config/database.php');

set_time_limit(1200); 

// Restrict access to Administrator/Limited Access if needed
$userRole = $_SESSION['role'] ?? 'Limited Access';
if ($userRole !== 'Administrator') {
  // Assuming only Administrator can perform bulk updates
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
  @fclose($pipes[0]);
  $stdout = stream_get_contents($pipes[1]);
  $stderr = stream_get_contents($pipes[2]);
  @fclose($pipes[1]);
  @fclose($pipes[2]);
  $code = proc_close($proc);
  return [$code, (string) $stdout, (string) $stderr];
}

function find_svn_binary(): ?string
{
  $candidates = [
    'svn', // if on PATH
    'C:\\Program Files\\Subversion\\bin\\svn.exe',
    'C:\\Program Files\\SlikSvn\\bin\\svn.exe',
    'C:\\Program Files\\VisualSVN Server\\bin\\svn.exe',
    'C:\\Program Files\\TortoiseSVN\\bin\\svn.exe',
    'C:\\Program Files (x86)\\Subversion\\bin\\svn.exe',
    'C:\\Program Files (x86)\\SlikSvn\\bin\\svn.exe',
    'C:\\Program Files (x86)\\VisualSVN Server\\bin\\svn.exe',
    'C:\\Program Files (x86)\\TortoiseSVN\\bin\\svn.exe',
  ];
  foreach ($candidates as $bin) {
    [$code, $out, $err] = run_process([$bin, '--version', '--quiet']);
    if ($code === 0)
      return $bin;
  }
  return null;
}

function h($value): string
{
  return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$message = '';
$results = [];
$status_type = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['repoType'])) {
  $repoType = $_POST['repoType'];
  if (in_array($repoType, ['sl', 'sw', 'dw'])) {
    $db = getDatabaseConnection();
    $svnBin = find_svn_binary();

    if ($svnBin && $db) {
      $svnDirUrl = "svn://src/xtendlic/{$repoType}/";
      [$lsCode, $lsOut, $lsErr] = run_process([$svnBin, 'ls', '--non-interactive', $svnDirUrl]);

      if ($lsCode === 0) {
        $svnSerials = [];
        foreach (explode("\n", $lsOut) as $line) {
          $line = trim($line);
          if ($line !== '' && substr($line, -1) === '/') {
            $svnSerials[] = rtrim($line, '/');
          }
        }

        $systemType = ($repoType === 'sl' || $repoType === 'sw') ? 'Standalone' : 'Desktop';
        $systemOs = ($repoType === 'sl') ? 'Linux' : 'Windows';

        $stmt = $db->prepare("SELECT * FROM license_details WHERE system_type = :sysType AND system_os = :sysOs AND system_serialid != '' AND system_uniqueid != ''");
        $stmt->execute([':sysType' => $systemType, ':sysOs' => $systemOs]);
        $dbRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $targetDir = __DIR__ . '/uploads/licenses';
        if (!is_dir($targetDir)) {
          @mkdir($targetDir, 0777, true);
        }

        $updatedCount = 0;

        foreach ($dbRows as $row) {
          $serialId = trim((string) $row['system_serialid']);
          $uniqueId = trim((string) $row['system_uniqueid']);

          if (in_array($serialId, $svnSerials)) {
            $svnUrl = "svn://src/xtendlic/{$repoType}/{$serialId}/{$uniqueId}";
            $tempJsonFile = $targetDir . '/data_' . uniqid() . '.json';

            $cmd = [$svnBin, 'export', '--force', '--non-interactive', $svnUrl, $tempJsonFile];
            [$code, $out, $err] = run_process($cmd);

            if ($code === 0 && file_exists($tempJsonFile)) {
              $jsonContent = file_get_contents($tempJsonFile);
              $jsonData = json_decode($jsonContent, true);

              if (is_array($jsonData)) {
                $devices = ['', '', '', '', '', ''];
                $ports = ['', '', '', '', '', ''];
                if (isset($jsonData['Hardware']['Analog']) && is_array($jsonData['Hardware']['Analog'])) {
                  $idx = 0;
                  foreach ($jsonData['Hardware']['Analog'] as $dId => $p) {
                    if ($idx > 5)
                      break;
                    $devices[$idx] = preg_replace('/_DUPLICATE_KEY_MARKER_.*$/', '', (string) $dId);
                    $ports[$idx] = (string) $p;
                    $idx++;
                  }
                }

                $updateStmt = $db->prepare("UPDATE license_details SET
                    client_name = :client_name, location_name = :location_name, location_code = :location_code, 
                    licensee_name = :licensee_name, licensee_distributor = :licensee_distributor, licensee_dealer = :licensee_dealer, 
                    licensee_type = :licensee_type, licensee_amctill = :licensee_amctill, licensee_validtill = :licensee_validtill, 
                    licensee_billno = :licensee_billno, system_type = :system_type, system_os = :system_os, 
                    system_isvm = :system_isvm, system_serialid = :system_serialid, system_uniqueid = :system_uniqueid, 
                    system_build_type = :system_build_type, system_debug = :system_debug, 
                    system_ipsettings_type = :system_ipsettings_type, system_ipsettings_ip = :system_ipsettings_ip,
                    system_ipsettings_gateway = :system_ipsettings_gateway, system_ipsettings_dns = :system_ipsettings_dns,
                    system_passwords_system = :system_passwords_system, system_passwords_web = :system_passwords_web, 
                    fetch_updates = :fetch_updates, install_updates = :install_updates,
                    engine_build = :engine_build, engine_graceperiod = :engine_graceperiod,
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
                    features_script = :features_script
                WHERE id = :id");

                $updateStmt->execute([
                  ':id' => $row['id'],
                  ':client_name' => $jsonData['Client']['ClientName'] ?? $jsonData['ClientName'] ?? '',
                  ':location_name' => $jsonData['Client']['LocationName'] ?? $jsonData['LocationName'] ?? '',
                  ':location_code' => $jsonData['Client']['LocationCode'] ?? $jsonData['LocationCode'] ?? '',
                  ':licensee_name' => $jsonData['Licensee']['Name'] ?? '',
                  ':licensee_distributor' => $jsonData['Licensee']['Distributor'] ?? '',
                  ':licensee_dealer' => $jsonData['Licensee']['Dealer'] ?? '',
                  ':licensee_type' => $jsonData['Licensee']['Type'] ?? '',
                  ':licensee_amctill' => $jsonData['Licensee']['AMCTill'] ?? '',
                  ':licensee_validtill' => $jsonData['Licensee']['ValidTill'] ?? '',
                  ':licensee_billno' => $jsonData['Licensee']['BillNo'] ?? '',
                  ':system_type' => $jsonData['System']['Type'] ?? '',
                  ':system_os' => $jsonData['System']['OS'] ?? '',
                  ':system_isvm' => (!empty($jsonData['System']['IsVM']) && strtolower((string) $jsonData['System']['IsVM']) !== 'false' && $jsonData['System']['IsVM'] !== 0) ? 1 : 0,
                  ':system_serialid' => $jsonData['System']['SerialID'] ?? '',
                  ':system_uniqueid' => $jsonData['System']['UniqueID'] ?? '',
                  ':system_build_type' => $jsonData['System']['BuildType'] ?? '',
                  ':system_debug' => $jsonData['System']['Debug'] ?? 0,
                  ':system_ipsettings_type' => $jsonData['System']['IPSettings']['Type'] ?? '',
                  ':system_ipsettings_ip' => $jsonData['System']['IPSettings']['IP'] ?? '',
                  ':system_ipsettings_gateway' => $jsonData['System']['IPSettings']['Gateway'] ?? '',
                  ':system_ipsettings_dns' => $jsonData['System']['IPSettings']['Dns'] ?? '',
                  ':system_passwords_system' => $jsonData['System']['Passwords']['System'] ?? '',
                  ':system_passwords_web' => $jsonData['System']['Passwords']['Web'] ?? '',
                  ':fetch_updates' => $jsonData['System']['FetchUpdates'] ?? 0,
                  ':install_updates' => $jsonData['System']['InstallUpdates'] ?? 0,
                  ':engine_build' => $jsonData['Engine']['Build'] ?? '',
                  ':engine_graceperiod' => $jsonData['Engine']['GracePeriod'] ?? 0,
                  ':engine_validstarttz' => $jsonData['Engine']['ValidStartTZ'] ?? 0,
                  ':engine_validendtz' => $jsonData['Engine']['ValidEndTZ'] ?? 0,
                  ':engine_validcountries' => $jsonData['Engine']['ValidCountries'] ?? 0,
                  ':device_id1' => $devices[0],
                  ':ports_enabled_deviceid1' => $ports[0],
                  ':device_id2' => $devices[1],
                  ':ports_enabled_deviceid2' => $ports[1],
                  ':device_id3' => $devices[2],
                  ':ports_enabled_deviceid3' => $ports[2],
                  ':device_id4' => $devices[3],
                  ':ports_enabled_deviceid4' => $ports[3],
                  ':device_id5' => $devices[4],
                  ':ports_enabled_deviceid5' => $ports[4],
                  ':device_id6' => $devices[5],
                  ':ports_enabled_deviceid6' => $ports[5],
                  ':centralization_livestatusurl' => $jsonData['Centralization']['LiveStatusUrl'] ?? '',
                  ':centralization_livestatusurlinterval' => $jsonData['Centralization']['LiveStatusUrlInterval'] ?? '',
                  ':centralization_uploadfileurl' => $jsonData['Centralization']['UploadFileUrl'] ?? '',
                  ':centralization_uploadfileurlinterval' => $jsonData['Centralization']['UploadFileUrlInterval'] ?? '',
                  ':centralization_settingsurl' => $jsonData['Centralization']['SettingsUrl'] ?? '',
                  ':centralization_usertrunkmappingurl' => $jsonData['Centralization']['UserTrunkMappingUrl'] ?? '',
                  ':centralization_phonebookurl' => $jsonData['Centralization']['PhoneBookUrl'] ?? '',
                  ':features_script' => $jsonData['Features']['Script'] ?? ''
                ]);

                $results[] = ["type" => "success", "msg" => "Serial ID {$serialId}: Successfully Synced."];
                $updatedCount++;
              } else {
                $results[] = ["type" => "error", "msg" => "Serial ID {$serialId}: Invalid JSON format."];
              }
              @unlink($tempJsonFile);
            } else {
              $results[] = ["type" => "error", "msg" => "Serial ID {$serialId}: SVN export failed."];
            }
          }
        }
        $message = "Sync Completed! Total updated: " . $updatedCount;
        $status_type = $updatedCount > 0 ? 'success' : 'info';
      } else {
        $message = "SVN List Error: " . htmlspecialchars($lsErr);
        $status_type = 'error';
      }
    } elseif (!$svnBin) {
      $message = "SVN binary not found on this server.";
      $status_type = 'error';
    }
  } else {
    $message = "Invalid selection.";
    $status_type = 'error';
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Database Update | Xtend License</title>
  <link rel="stylesheet" href="styles/common.css">
  <link rel="stylesheet" href="styles/header-sidebar.css">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

    :root {
      --primary: #3b82f6;
      --primary-dark: #2563eb;
      --bg: #f1f5f9;
      --card-bg: rgba(255, 255, 255, 0.95);
      --text-main: #1e293b;
      --text-muted: #64748b;
      --border: #e2e8f0;
      --success: #10b981;
      --error: #ef4444;
    }

    body {
      font-family: 'Inter', sans-serif;
      background: #f1f5f9;
      margin: 0;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      overflow-x: hidden;
    }

    .main-wrapper {
      margin-left: 240px;
      margin-top: 60px;
      min-height: calc(100vh - 60px);
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 40px 20px;
      box-sizing: border-box;
    }

    @media (max-width: 768px) {
      .main-wrapper {
        margin-left: 0;
        padding: 40px 15px;
      }
    }

    .glass-card {
      background: var(--card-bg);
      backdrop-filter: blur(8px);
      -webkit-backdrop-filter: blur(8px);
      border: 1px solid var(--border);
      border-radius: 20px;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 10px 15px -3px rgba(0, 0, 0, 0.1);
      width: 100%;
      max-width: 800px;
      padding: 40px;
      position: relative;
    }

    .header-section {
      text-align: center;
      margin-bottom: 40px;
    }

    .header-section h1 {
      font-size: 26px;
      font-weight: 700;
      color: var(--text-main);
      margin-bottom: 12px;
      letter-spacing: -0.025em;
    }

    .header-section p {
      color: var(--text-muted);
      font-size: 15px;
      line-height: 1.6;
    }

    .update-form {
      max-width: 450px;
      margin: 0 auto;
      display: flex;
      flex-direction: column;
      gap: 25px;
    }

    .form-group label {
      display: block;
      font-weight: 600;
      font-size: 14px;
      color: var(--text-main);
      margin-bottom: 10px;
    }

    .styled-select {
      width: 100%;
      padding: 14px 18px;
      border: 1px solid var(--border);
      border-radius: 12px;
      font-size: 15px;
      background: #fff;
      color: var(--text-main);
      transition: all 0.2s;
      appearance: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2394a3b8'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 16px center;
      background-size: 18px;
      text-align: center;
    }


    .styled-select:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
    }

    .btn-gradient {
      background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
      color: white;
      border: none;
      padding: 16px 32px;
      border-radius: 12px;
      font-weight: 600;
      font-size: 16px;
      cursor: pointer;
      transition: all 0.2s;
      box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 12px;
    }

    .btn-gradient:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 15px rgba(37, 99, 235, 0.3);
      filter: brightness(1.05);
    }

    .btn-gradient:active {
      transform: translateY(0);
    }

    .alert {
      padding: 16px 20px;
      border-radius: 14px;
      margin-top: 35px;
      font-weight: 500;
      font-size: 15px;
      display: flex;
      align-items: center;
      gap: 14px;
    }

    .alert-success {
      background: #f0fdf4;
      color: #166534;
      border: 1px solid #bbf7d0;
    }

    .alert-error {
      background: #fef2f2;
      color: #991b1b;
      border: 1px solid #fecaca;
    }

    .alert-info {
      background: #f0f9ff;
      color: #075985;
      border: 1px solid #bae6fd;
    }

    .results-timeline {
      margin-top: 45px;
      border-top: 1px solid var(--border);
      padding-top: 35px;
    }

    .results-timeline h3 {
      font-size: 17px;
      font-weight: 700;
      margin-bottom: 20px;
      color: var(--text-main);
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .log-container {
      max-height: 350px;
      overflow-y: auto;
      background: #f8fafc;
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 15px;
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .log-item {
      padding: 10px 15px;
      border-radius: 10px;
      font-size: 13.5px;
      background: #fff;
      border: 1px solid #f1f5f9;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .log-item.success {
      color: #15803d;
    }

    .log-item.error {
      color: #b91c1c;
    }

    .dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      display: inline-block;
    }

    .success .dot {
      background: var(--success);
      box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
    }

    .error .dot {
      background: var(--error);
      box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.1);
    }

    /* Loading Overlay */
    #loadingOverlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(255, 255, 255, 0.9);
      backdrop-filter: blur(8px);
      z-index: 9999;
      display: none;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      color: var(--text-main);
    }

    .spinner {
      width: 56px;
      height: 56px;
      border: 4px solid var(--border);
      border-top-color: var(--primary);
      border-radius: 50%;
      animation: spin 0.8s cubic-bezier(0.4, 0, 0.2, 1) infinite;
      margin-bottom: 24px;
    }

    @keyframes spin {
      to {
        transform: rotate(360deg);
      }
    }

    .loader-msg h2 {
      margin: 0;
      font-size: 20px;
      font-weight: 700;
      text-align: center;
    }

    .loader-msg p {
      color: var(--text-muted);
      margin: 8px 0 0;
      font-size: 14px;
    }

    /* Utility */
    .icon {
      width: 20px;
      height: 20px;
    }
  </style>
</head>

<body>

  <div id="loadingOverlay">
    <div class="spinner"></div>
    <div class="loader-msg">
      <h2>Syncing with Server</h2>
      <p>Updating database... please do not close this tab.</p>
    </div>
  </div>

  <div class="main-wrapper">
    <div class="glass-card">
      <div class="header-section">
        <h1>Sync With Server</h1>
        <!-- <p>Effortlessly fetch and merge the latest license configurations from the central repository into your local
          environment.</p> -->
      </div>

      <form class="update-form" id="syncForm" method="POST">
        <div class="form-group">
          <!-- <label for="repoType">Repository Target</label> -->
          <select name="repoType" id="repoType" class="styled-select" required>
            <option value="" disabled selected>Select system type</option>
            <option value="sl">Linux Standalone (SL)</option>
            <option value="sw">Windows Standalone (SW)</option>
            <option value="dw">Windows Desktop (DW)</option>
          </select>
        </div>
        <button type="submit" class="btn-gradient">
          <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
            </path>
          </svg>
          Update Database
        </button>
      </form>

      <?php if ($message): ?>
        <div class="alert alert-<?php echo $status_type; ?>">
          <?php if ($status_type === 'success'): ?>
            <svg class="icon" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd"
                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                clip-rule="evenodd"></path>
            </svg>
          <?php elseif ($status_type === 'error'): ?>
            <svg class="icon" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd"
                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                clip-rule="evenodd"></path>
            </svg>
          <?php else: ?>
            <svg class="icon" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd"
                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                clip-rule="evenodd"></path>
            </svg>
          <?php endif; ?>
          <?php echo h($message); ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($results)): ?>
        <div class="results-timeline">
          <h3>
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01">
              </path>
            </svg>
            Update Summary
          </h3>
          <div class="log-container">
            <?php foreach ($results as $res): ?>
              <div class="log-item <?php echo $res['type']; ?>">
                <span class="dot"></span>
                <span><?php echo h($res['msg']); ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
  <?php include 'components/header-sidebar.php'; ?>

  <script src="plugins/jquery-3.7.1.min.js"></script>
  <script>
    document.getElementById('syncForm').addEventListener('submit', function () {
      // Show overlay
      document.getElementById('loadingOverlay').style.display = 'flex';

      // Optional: Prevent double clicks
      const btn = this.querySelector('button');
      btn.disabled = true;
      btn.style.opacity = '0.7';
      btn.innerHTML = '<span class="spinner" style="width:16px; height:16px; border-width:2px; margin:0"></span> Processing...';
    });
  </script>
</body>

</html>