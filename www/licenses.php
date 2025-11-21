<?php
declare(strict_types=1);
require_once('config/auth_check.php');

// Simple license JSON uploader and editor
// - Step 1: Upload JSON file
// - Step 2: Edit values in a generated form (all keys as inputs)
// - Step 3: Submit to save JSON on the server


// $rawDate = $val(['Licensee','ValidTill'], $defaults['Licensee']['ValidTill']); 
// $formatted = '';

// if (!empty($rawDate)) {
//     $formatted = date('Y-m-d', strtotime($rawDate)); // convert to Y-m-d
// }

// Configuration
$uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'licenses';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0775, true);
}

// Helpers
function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function array_get($array, array $path, $default = null) {
    $cur = $array;
    foreach ($path as $key) {
        if (!is_array($cur) || !array_key_exists($key, $cur)) return $default;
        $cur = $cur[$key];
    }
    return $cur;
}

function run_process(array $cmd): array {
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
    return [$code, (string)$stdout, (string)$stderr];
}

function find_curl_binary(): ?string {
    $candidates = [
        'curl', // PATH
        'C:\\Windows\\System32\\curl.exe',
        'C:\\Windows\\SysWOW64\\curl.exe',
    ];
    foreach ($candidates as $bin) {
        [$code, $out, $err] = run_process([$bin, '--version']);
        if ($code === 0) return $bin;
    }
    return null;
}

function find_svn_binary(?string $preferred = null): ?string {
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
        if ($code === 0) return $bin;
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
$commentValue = isset($_POST['comment']) ? (string)$_POST['comment'] : 'Comment';

if ($method === 'POST' && $action === 'fetch_remote') {
    $url = trim((string)($_POST['remote_url'] ?? ''));
    $username = trim((string)($_POST['remote_user'] ?? ''));
    $password = (string)($_POST['remote_pass'] ?? '');
    $userSvnBin = trim((string)($_POST['remote_svn_bin'] ?? ''));

    if ($url === '') {
        $errors[] = 'Please provide a URL to fetch the JSON.';
    } else {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (in_array(strtolower((string)$scheme), ['http', 'https'], true)) {
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
            if ($contents === false || trim((string)$contents) === '') {
                $errors[] = 'Failed to fetch JSON from the provided URL.';
            } else {
                $data = json_decode((string)$contents, true);
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
function buildLicenseFromPost(array $post): array {
    $str = function($v): string {
        return is_string($v) ? trim($v) : (string)$v;
    };
    $boolFrom = function($v): bool {
        if (is_bool($v)) return $v;
        $s = strtolower(trim((string)$v));
        return $s === 'true' || $s === '1' || $s === 'yes';
        // Note: "No" will return false (which is correct)
    };
    $license = [
        'CreatedOn' => $str($post['CreatedOn'] ?? ''),
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
        ],
        'Engine' => [
            'Build' => $str($post['Engine']['Build'] ?? ''),
            'GracePeriod' => (int)($post['Engine']['GracePeriod'] ?? 0),
            'MaxPorts' => $str($post['Engine']['MaxPorts'] ?? ''),
            'ValidStartTZ' => (int)($post['Engine']['ValidStartTZ'] ?? 0),
            'ValidEndTZ' => (int)($post['Engine']['ValidEndTZ'] ?? 0),
            'ValidCountries' => (int)($post['Engine']['ValidCountries'] ?? 0),
        ],
        'Hardware' => [
            'Analog' => [
                // Allow arbitrary key pairs sent as Hardware[Analog][key]=value
            ],
        ],
        'Features' => [
            'Script' => new stdClass(), // empty object
        ],
    ];
    if (isset($post['Hardware']['Analog']) && is_array($post['Hardware']['Analog'])) {
        foreach ($post['Hardware']['Analog'] as $k => $v) {
            $license['Hardware']['Analog'][$str($k)] = $str($v);
        }
    }
    return $license;
}

if ($method === 'POST' && $action === 'generate') {
    $license = buildLicenseFromPost($_POST);
    $encoded = json_encode($license, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if (isset($_POST['download']) && $_POST['download'] === '1') {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="license.json"');
        echo $encoded;
        exit;
    }
    // Fall-through to page render with generated preview
}

if ($method === 'POST' && $action === 'send') {
    $license = buildLicenseFromPost($_POST);
    $encoded = json_encode($license, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
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
        $info = trim((string)($_POST['comment'] ?? 'Comment'));
        if ($info === '') {
            $info = 'Comment';
        }

        // Build curl command: curl -k -X POST -F account_name=guest -F secret_token=guest -F licensefile=@default.json "URL" -F info="Comment"
        $cmd = [
            $curl, '-k', '-X', 'POST',
            '-F', 'account_name=' . $account,
            '-F', 'secret_token=' . $token,
            // On Windows, @ needs absolute path
            '-F', 'licensefile=@' . $tmpFile,
            '-F', 'info=' . $info,
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
    <title>License JSON Upload</title>
    <!-- <link rel="stylesheet" href="css/style.css">
    <link rel="shortcut icon" href="images/favicon.png" /> -->
        <link rel="stylesheet" href="styles/users.css">
        <link rel="stylesheet" href="styles/header-sidebar.css">
        <link rel="stylesheet" href="styles/common.css">
    <style>
        /* Ensure page scrolls even if global CSS disables it */
        html, body { height: 100%; }
        body { overflow-y: auto !important; }
    </style>
</head>
<body>
    <div class="page-containers">
        <?php if ($errors): ?>
            <div style="background:#fee2e2; color:#991b1b; border:1px solid #fecaca; padding:12px 16px; border-radius:8px; margin-bottom:16px;">
                <?php foreach ($errors as $e): ?>
                    <div><?php echo h($e); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($submitResult !== null): ?>
            <div style="margin:150px 10px; border:1px solid #e5e7eb; border-radius:8px; padding:16px; background:#ffffff;">
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
                    <div style="margin-bottom:16px; padding:12px 14px; border:1px solid <?php echo h($border); ?>; background:<?php echo h($bg); ?>; color:<?php echo h($color); ?>; border-radius:8px;">
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
                
                <!-- <div style="margin-bottom:8px;">Exit code: <?php echo h((string)$submitResult['code']); ?></div> -->
                
                <div style="margin:8px 0; font-weight:bold;">Server Response:</div>
                <pre style="background:#0f172a; color:#e2e8f0; padding:16px; border-radius:8px; overflow:auto; margin-bottom:16px;"><?php echo h($submitResult['out']); ?></pre>
                
                <!-- <?php if ($submitResult['err'] !== ''): ?>
                    <div style="margin:8px 0; font-weight:bold;">stderr:</div>
                    <pre style="background:#111827; color:#fecaca; padding:16px; border-radius:8px; overflow:auto; margin-bottom:16px;"><?php echo h($submitResult['err']); ?></pre>
                <?php endif; ?> -->
                
                <div style="margin-top:24px; text-align:center;">
                    <a href="licenses.php" style="display:inline-block; padding:5px 18px; background:#2563eb; color:white; border:none; border-radius:6px; cursor:pointer; text-decoration:none;">Back</a>
                </div>
            </div>
        <?php endif; ?>

        <?php
            // Defaults per provided JSON
            $defaults = [
                'CreatedOn' => date('Ymd'), // Current date in YYYYMMDD format
                'Licensee' => [
                    'Name' => 'Xtend Technologies Pvt. Ltd.',
                    'Distributor' => '',
                    'Dealer' => '',
                    'Type' => 'Purchase',
                    'AMCTill' => '20251001',
                    'ValidTill' => '',
                    'BillNo' => 'XT25-1002',
                ],
                'System' => [
                    'Type' => 'Standalone',
                    'OS' => 'Linux',
                    'IsVM' => 'false',
                    'SerialID' => '',
                    'UniqueID' => '',
                ],
                'Engine' => [
                    'Build' => 'Xtend IVR',
                    'GracePeriod' => 30,
                    'MaxPorts' => 'M=4000,*=4000',
                    'ValidStartTZ' => 330,
                    'ValidEndTZ' => 330,
                    'ValidCountries' => 65,
                ],
                'Hardware' => [
                    'Analog' => [
                        '2303' => '11110000',
                        '2304' => '00001111',
                    ],
                ],
            ];

            // Merge values from prefill if available
            $val = function(array $path, $fallback) use ($prefill) {
                if (is_array($prefill)) {
                    $got = array_get($prefill, $path, null);
                    if ($got !== null) return $got;
                }
                return $fallback;
            };

            // Resolve Hardware → Analog keys dynamically (union of defaults and prefill)
            $analogFromPrefill = [];
            if (is_array($prefill)) {
                $analogFromPrefill = array_get($prefill, ['Hardware','Analog'], []);
                if (!is_array($analogFromPrefill)) $analogFromPrefill = [];
            }
            $analogKeys = array_unique(array_merge(array_keys($defaults['Hardware']['Analog']), array_keys($analogFromPrefill)));


        ?>
        <?php if ($submitResult === null): ?>
        <form method="post" style="display:block; margin-bottom:24px; border:1px solid #e5e7eb; border-radius:12px; padding:24px; background:#ffffff; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
            <input type="hidden" name="action" value="generate">
            
            <!-- General Section -->
            <div style="margin-bottom:28px; padding-bottom:20px; border-bottom:2px solid #f1f5f9;">
                <h3 style="margin:0 0 16px 0; font-size:18px; font-weight:600; color:#1e293b; padding-bottom:8px; border-bottom:1px solid #e2e8f0;">General</h3>
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(250px, 1fr)); gap:16px;">
                    <div>
                        <label style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">CreatedOn</label>
                        <input type="text" name="CreatedOn" value="<?php echo h($val(['CreatedOn'], date('Ymd'))); ?>" style="width:25%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;" onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';" onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
                    </div>
                </div>
            </div>
            
            <!-- Licensee Section -->
            <div style="margin-bottom:28px; padding-bottom:20px; border-bottom:2px solid #f1f5f9;">
                <h3 style="margin:0 0 16px 0; font-size:18px; font-weight:600; color:#1e293b; padding-bottom:8px; border-bottom:1px solid #e2e8f0;">Licensee</h3>
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(250px, 1fr)); gap:16px;">
                    <div>
                        <label style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Licensee[Name]</label>
                        <input type="text" name="Licensee[Name]" value="<?php echo h($val(['Licensee','Name'], $defaults['Licensee']['Name'])); ?>" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;" onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';" onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
                    </div>
                    <div>
                        <label style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Licensee[Distributor]</label>
                        <input type="text" name="Licensee[Distributor]" value="<?php echo h($val(['Licensee','Distributor'], $defaults['Licensee']['Distributor'])); ?>" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;" onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';" onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
                    </div>
                    <div>
                        <label style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Licensee[Dealer]</label>
                        <input type="text" name="Licensee[Dealer]" value="<?php echo h($val(['Licensee','Dealer'], $defaults['Licensee']['Dealer'])); ?>" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;" onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';" onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
                    </div>
                    <div>
                        <label style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Licensee[Type]</label>
                        <select name="Licensee[Type]" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; background:#ffffff; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box; cursor:pointer;" onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';" onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
                            <?php $licenseeType = (string)$val(['Licensee','Type'], $defaults['Licensee']['Type']); ?>
                            <option value="Rental" <?php echo ($licenseeType === 'Rental') ? 'selected' : ''; ?>>Rental</option>
                            <option value="Purchase" <?php echo ($licenseeType === 'Purchase') ? 'selected' : ''; ?>>Purchase</option>
                            <option value="Permanent" <?php echo ($licenseeType === 'Permanent') ? 'selected' : ''; ?>>Permanent</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Licensee[AMCTill]</label>
                        <input type="text" name="Licensee[AMCTill]" value="<?php echo h($val(['Licensee','AMCTill'], $defaults['Licensee']['AMCTill'])); ?>" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;" onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';" onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
                    </div>
                    <div>
                        <label style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Licensee[ValidTill]</label>
                        <input type="text" name="Licensee[ValidTill]" value="<?php echo h($val(['Licensee','ValidTill'], $defaults['Licensee']['ValidTill'])); ?>" placeholder="YYYYMMDD" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;" onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';" onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
                    </div>
                    <div>
                        <label style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Licensee[BillNo]</label>
                        <input type="text" name="Licensee[BillNo]" value="<?php echo h($val(['Licensee','BillNo'], $defaults['Licensee']['BillNo'])); ?>" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;" onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';" onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
                    </div>
                </div>
            </div>
            
            <!-- System Section -->
            <div style="margin-bottom:28px; padding-bottom:20px; border-bottom:2px solid #f1f5f9;">
                <h3 style="margin:0 0 16px 0; font-size:18px; font-weight:600; color:#1e293b; padding-bottom:8px; border-bottom:1px solid #e2e8f0;">System</h3>
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(250px, 1fr)); gap:16px;">
                    <div>
                        <label style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">System[Type]</label>
                        <select name="System[Type]" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; background:#ffffff; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box; cursor:pointer;" onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';" onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
                            <?php $systemType = (string)$val(['System','Type'], $defaults['System']['Type']); ?>
                            <option value="Desktop" <?php echo ($systemType === 'Desktop') ? 'selected' : ''; ?>>Desktop</option>
                            <option value="Standalone" <?php echo ($systemType === 'Standalone') ? 'selected' : ''; ?>>Standalone</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">System[OS]</label>
                        <select name="System[OS]" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; background:#ffffff; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box; cursor:pointer;" onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';" onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
                            <?php $systemOS = (string)$val(['System','OS'], $defaults['System']['OS']); ?>
                            <option value="Windows" <?php echo ($systemOS === 'Windows') ? 'selected' : ''; ?>>Windows</option>
                            <option value="Linux" <?php echo ($systemOS === 'Linux') ? 'selected' : ''; ?>>Linux</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">System[IsVM]</label>
                        <select name="System[IsVM]" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; background:#ffffff; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box; cursor:pointer;" onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';" onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
                            <?php 
                            $isvm = (string)$val(['System','IsVM'], $defaults['System']['IsVM']); 
                            // Handle both old format (true/false) and new format (Yes/No)
                            $isvmNormalized = strtolower($isvm);
                            $isSelected = ($isvmNormalized === 'true' || $isvmNormalized === 'true' || $isvmNormalized === '1');
                            ?>
                            <option value="false" <?php echo !$isSelected ? 'selected' : ''; ?>>false</option>
                            <option value="true" <?php echo $isSelected ? 'selected' : ''; ?>>true</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">System[SerialID]</label>
                        <input type="text" name="System[SerialID]" value="<?php echo h($val(['System','SerialID'], $defaults['System']['SerialID'])); ?>" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;" onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';" onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
                    </div>
                    <div>
                        <label style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">System[UniqueID]</label>
                        <input type="text" name="System[UniqueID]" value="<?php echo h($val(['System','UniqueID'], $defaults['System']['UniqueID'])); ?>" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;" onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';" onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
                    </div>
                </div>
            </div>
            
            <!-- Engine Section -->
            <div style="margin-bottom:28px; padding-bottom:20px; border-bottom:2px solid #f1f5f9;">
                <h3 style="margin:0 0 16px 0; font-size:18px; font-weight:600; color:#1e293b; padding-bottom:8px; border-bottom:1px solid #e2e8f0;">Engine</h3>
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(250px, 1fr)); gap:16px;">
                    <div>
                        <label style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Engine[Build]</label>
                        <input type="text" name="Engine[Build]" value="<?php echo h($val(['Engine','Build'], $defaults['Engine']['Build'])); ?>" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;" onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';" onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
                    </div>
                    <div>
                        <label style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Engine[GracePeriod]</label>
                        <input type="number" name="Engine[GracePeriod]" value="<?php echo h((string)$val(['Engine','GracePeriod'], $defaults['Engine']['GracePeriod'])); ?>" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;" onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';" onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
                    </div>
                    <div>
                        <label style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Engine[MaxPorts]</label>
                        <input type="text" name="Engine[MaxPorts]" value="<?php echo h($val(['Engine','MaxPorts'], $defaults['Engine']['MaxPorts'])); ?>" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;" onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';" onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
                    </div>
                    <div>
                        <label style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Engine[ValidStartTZ]</label>
                        <input type="number" name="Engine[ValidStartTZ]" value="<?php echo h((string)$val(['Engine','ValidStartTZ'], $defaults['Engine']['ValidStartTZ'])); ?>" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;" onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';" onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
                    </div>
                    <div>
                        <label style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Engine[ValidEndTZ]</label>
                        <input type="number" name="Engine[ValidEndTZ]" value="<?php echo h((string)$val(['Engine','ValidEndTZ'], $defaults['Engine']['ValidEndTZ'])); ?>" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;" onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';" onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
                    </div>
                    <div>
                        <label style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Engine[ValidCountries]</label>
                        <input type="number" name="Engine[ValidCountries]" value="<?php echo h((string)$val(['Engine','ValidCountries'], $defaults['Engine']['ValidCountries'])); ?>" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;" onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';" onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
                    </div>
                </div>
            </div>
            
            <!-- Hardware → Analog Section -->
            <div style="margin-bottom:28px; padding-bottom:20px; border-bottom:2px solid #f1f5f9;">
                <h3 style="margin:0 0 16px 0; font-size:18px; font-weight:600; color:#1e293b; padding-bottom:8px; border-bottom:1px solid #e2e8f0;">Hardware</h3>
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:8px;">
                    <?php foreach ($analogKeys as $k): $v = $val(['Hardware','Analog',$k], $defaults['Hardware']['Analog'][$k] ?? ''); ?>
                        <div>
                            <label style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Hardware[Analog][<?php echo h($k); ?>]</label>
                            <input type="text" name="Hardware[Analog][<?php echo h($k); ?>]" value="<?php echo h($v); ?>" style="width:50%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;" onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';" onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Comment Section -->
            <div style="margin-bottom:24px;">
                <label style="display:block; font-weight:500; margin-bottom:6px; color:#475569; font-size:14px;">Comment</label>
                <input type="text" name="comment" value="<?php echo h($commentValue); ?>" style="width:25%; max-width:500px; padding:10px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s; box-sizing:border-box;" onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59,130,246,0.1)';" onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
            </div>
            
            <!-- Action Buttons -->
            <div style="margin-top:24px; padding-top:20px; border-top:2px solid #f1f5f9; display:flex; gap:12px; flex-wrap:wrap; justify-content:center;">
                <button type="submit" name="download" value="1" style="padding:12px 24px; background:#0ea5e9; color:white; border:none; border-radius:6px; cursor:pointer; font-size:14px; font-weight:500; transition:background-color 0.2s, transform 0.1s; box-shadow:0 1px 2px rgba(0,0,0,0.1);" onmouseover="this.style.background='#0284c7'; this.style.transform='translateY(-1px)';" onmouseout="this.style.background='#0ea5e9'; this.style.transform='translateY(0)';" onmousedown="this.style.transform='translateY(0)';" onmouseup="this.style.transform='translateY(-1px)';">Download JSON</button>
                <button type="submit" name="action" value="send" style="padding:12px 24px; background:#f59e0b; color:white; border:none; border-radius:6px; cursor:pointer; font-size:14px; font-weight:500; transition:background-color 0.2s, transform 0.1s; box-shadow:0 1px 2px rgba(0,0,0,0.1);" onmouseover="this.style.background='#d97706'; this.style.transform='translateY(-1px)';" onmouseout="this.style.background='#f59e0b'; this.style.transform='translateY(0)';" onmousedown="this.style.transform='translateY(0)';" onmouseup="this.style.transform='translateY(-1px)';">Submit to Server</button>
            </div>
        </form>
        <?php endif; ?>
    </div>
    <?php include 'components/header-sidebar.php'; ?>
</body>
</html>


