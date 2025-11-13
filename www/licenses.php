<?php
// Simple license JSON uploader and editor
// - Step 1: Upload JSON file
// - Step 2: Edit values in a generated form (all keys as inputs)
// - Step 3: Submit to save JSON on the server

declare(strict_types=1);

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
    };
    $license = [
        'CreatedOn' => $str($post['CreatedOn'] ?? ''),
        'Licensee' => [
            'Name' => $str($post['Licensee']['Name'] ?? ''),
            'Distributor' => $str($post['Licensee']['Distributor'] ?? ''),
            'Dealer' => $str($post['Licensee']['Dealer'] ?? ''),
            'Type' => $str($post['Licensee']['Type'] ?? ''),
            'AMCTill' => $str($post['Licensee']['AMCTill'] ?? ''),
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
        $info = 'Comment';

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
        ];
    }
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
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
            <div style="margin-bottom:24px; border:1px solid #e5e7eb; border-radius:8px; padding:16px; background:#ffffff;">
                <h2 style="margin-bottom:16px; font-size:20px; font-weight:bold;">Result</h2>
                <?php
                    $resultTag = $submitResult['resultTag'];
                    $errorTag = $submitResult['errorTag'];
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
                            <div style="margin-top:4px;"><strong>Error:</strong> <?php echo h($errorTag); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <div style="margin-bottom:8px; font-weight:bold;">JSON sent</div>
                <pre style="background:#0f172a; color:#e2e8f0; padding:16px; border-radius:8px; overflow:auto; margin-bottom:16px;"><?php echo h($submitResult['encoded']); ?></pre>
                
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
                'CreatedOn' => '20250917',
                'Licensee' => [
                    'Name' => 'Xtend Technologies Pvt. Ltd.',
                    'Distributor' => '',
                    'Dealer' => '',
                    'Type' => 'Rental',
                    'AMCTill' => '20251001',
                    'BillNo' => 'XT25-1002',
                ],
                'System' => [
                    'Type' => 'Desktop',
                    'OS' => 'Windows',
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
        <form method="post" style="display:block; margin-bottom:24px; border:1px solid #e5e7eb; border-radius:8px; padding:16px; background:#f8fafd;">
            <input type="hidden" name="action" value="generate">
            <h3 style="margin:8px 0 12px 0;">General</h3>
            <div style="display:flex; gap:16px; flex-wrap:wrap;">
                <div>
                    <label style="display:block; font-weight:bold; margin-bottom:4px;">CreatedOn</label>
                    <input type="text" name="CreatedOn" value="<?php echo h($val(['CreatedOn'], $defaults['CreatedOn'])); ?>" style="padding:8px; width:220px;">
                </div>
            </div>
            <h3 style="margin:16px 0 12px 0;">Licensee</h3>
            <div style="display:flex; gap:16px; flex-wrap:wrap;">
                <div><label style="display:block; font-weight:bold; margin-bottom:4px;">Licensee[Name]</label><input type="text" name="Licensee[Name]" value="<?php echo h($val(['Licensee','Name'], $defaults['Licensee']['Name'])); ?>" style="padding:8px; width:280px;"></div>
                <div><label style="display:block; font-weight:bold; margin-bottom:4px;">Licensee[Distributor]</label><input type="text" name="Licensee[Distributor]" value="<?php echo h($val(['Licensee','Distributor'], $defaults['Licensee']['Distributor'])); ?>" style="padding:8px; width:220px;"></div>
                <div><label style="display:block; font-weight:bold; margin-bottom:4px;">Licensee[Dealer]</label><input type="text" name="Licensee[Dealer]" value="<?php echo h($val(['Licensee','Dealer'], $defaults['Licensee']['Dealer'])); ?>" style="padding:8px; width:220px;"></div>
                <div><label style="display:block; font-weight:bold; margin-bottom:4px;">Licensee[Type]</label><input type="text" name="Licensee[Type]" value="<?php echo h($val(['Licensee','Type'], $defaults['Licensee']['Type'])); ?>" style="padding:8px; width:180px;"></div>
                <div><label style="display:block; font-weight:bold; margin-bottom:4px;">Licensee[AMCTill]</label><input type="text" name="Licensee[AMCTill]" value="<?php echo h($val(['Licensee','AMCTill'], $defaults['Licensee']['AMCTill'])); ?>" style="padding:8px; width:180px;"></div>
                <div><label style="display:block; font-weight:bold; margin-bottom:4px;">Licensee[BillNo]</label><input type="text" name="Licensee[BillNo]" value="<?php echo h($val(['Licensee','BillNo'], $defaults['Licensee']['BillNo'])); ?>" style="padding:8px; width:180px;"></div>
            </div>
            <h3 style="margin:16px 0 12px 0;">System</h3>
            <div style="display:flex; gap:16px; flex-wrap:wrap;">
                <div><label style="display:block; font-weight:bold; margin-bottom:4px;">System[Type]</label><input type="text" name="System[Type]" value="<?php echo h($val(['System','Type'], $defaults['System']['Type'])); ?>" style="padding:8px; width:180px;"></div>
                <div><label style="display:block; font-weight:bold; margin-bottom:4px;">System[OS]</label><input type="text" name="System[OS]" value="<?php echo h($val(['System','OS'], $defaults['System']['OS'])); ?>" style="padding:8px; width:180px;"></div>
                <div><label style="display:block; font-weight:bold; margin-bottom:4px;">System[IsVM]</label>
                    <select name="System[IsVM]" style="padding:8px; width:120px;">
                        <?php $isvm = (string)$val(['System','IsVM'], $defaults['System']['IsVM']); ?>
                        <option value="false" <?php echo ($isvm === 'false' || $isvm === '0') ? 'selected' : ''; ?>>false</option>
                        <option value="true" <?php echo ($isvm === 'true' || $isvm === '1') ? 'selected' : ''; ?>>true</option>
                    </select>
                </div>
                <div><label style="display:block; font-weight:bold; margin-bottom:4px;">System[SerialID]</label><input type="text" name="System[SerialID]" value="<?php echo h($val(['System','SerialID'], $defaults['System']['SerialID'])); ?>" style="padding:8px; width:220px;"></div>
                <div><label style="display:block; font-weight:bold; margin-bottom:4px;">System[UniqueID]</label><input type="text" name="System[UniqueID]" value="<?php echo h($val(['System','UniqueID'], $defaults['System']['UniqueID'])); ?>" style="padding:8px; width:220px;"></div>
            </div>
            <h3 style="margin:16px 0 12px 0;">Engine</h3>
            <div style="display:flex; gap:16px; flex-wrap:wrap;">
                <div><label style="display:block; font-weight:bold; margin-bottom:4px;">Engine[Build]</label><input type="text" name="Engine[Build]" value="<?php echo h($val(['Engine','Build'], $defaults['Engine']['Build'])); ?>" style="padding:8px; width:220px;"></div>
                <div><label style="display:block; font-weight:bold; margin-bottom:4px;">Engine[GracePeriod]</label><input type="number" name="Engine[GracePeriod]" value="<?php echo h((string)$val(['Engine','GracePeriod'], $defaults['Engine']['GracePeriod'])); ?>" style="padding:8px; width:160px;"></div>
                <div><label style="display:block; font-weight:bold; margin-bottom:4px;">Engine[MaxPorts]</label><input type="text" name="Engine[MaxPorts]" value="<?php echo h($val(['Engine','MaxPorts'], $defaults['Engine']['MaxPorts'])); ?>" style="padding:8px; width:220px;"></div>
                <div><label style="display:block; font-weight:bold; margin-bottom:4px;">Engine[ValidStartTZ]</label><input type="number" name="Engine[ValidStartTZ]" value="<?php echo h((string)$val(['Engine','ValidStartTZ'], $defaults['Engine']['ValidStartTZ'])); ?>" style="padding:8px; width:160px;"></div>
                <div><label style="display:block; font-weight:bold; margin-bottom:4px;">Engine[ValidEndTZ]</label><input type="number" name="Engine[ValidEndTZ]" value="<?php echo h((string)$val(['Engine','ValidEndTZ'], $defaults['Engine']['ValidEndTZ'])); ?>" style="padding:8px; width:160px;"></div>
                <div><label style="display:block; font-weight:bold; margin-bottom:4px;">Engine[ValidCountries]</label><input type="number" name="Engine[ValidCountries]" value="<?php echo h((string)$val(['Engine','ValidCountries'], $defaults['Engine']['ValidCountries'])); ?>" style="padding:8px; width:160px;"></div>
            </div>
            <h3 style="margin:16px 0 12px 0;">Hardware → Analog</h3>
            <div style="display:flex; gap:16px; flex-wrap:wrap;">
                <?php foreach ($analogKeys as $k): $v = $val(['Hardware','Analog',$k], $defaults['Hardware']['Analog'][$k] ?? ''); ?>
                    <div>
                        <label style="display:block; font-weight:bold; margin-bottom:4px;">Hardware[Analog][<?php echo h($k); ?>]</label>
                        <input type="text" name="Hardware[Analog][<?php echo h($k); ?>]" value="<?php echo h($v); ?>" style="padding:8px; width:200px;">
                    </div>
                <?php endforeach; ?>
            </div>
            <div style="margin-top:16px;">
                <!-- <button type="submit" style="padding:10px 18px; background:#16a34a; color:white; border:none; border-radius:6px; cursor:pointer;">Generate JSON</button> -->
                <button type="submit" name="download" value="1" style="padding:10px 18px; background:#0ea5e9; color:white; border:none; border-radius:6px; cursor:pointer; margin-left:8px;">Download JSON</button>
                <button type="submit" name="action" value="send" style="padding:10px 18px; background:#f59e0b; color:white; border:none; border-radius:6px; cursor:pointer; margin-left:8px;">Submit to Server</button>
            </div>
        </form>
        <?php endif; ?>
    </div>
    <?php include 'components/header-sidebar.php'; ?>
</body>
</html>


