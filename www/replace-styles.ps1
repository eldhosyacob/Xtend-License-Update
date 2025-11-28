# PowerShell script to replace inline styles with CSS classes in licenses.php

$filePath = "c:\xampp\htdocs\Xtend-License-Update\www\licenses.php"
$content = Get-Content $filePath -Raw

# Add license.css link if not present
if ($content -notmatch 'license\.css') {
    $content = $content -replace '(<link rel="stylesheet" href="styles/common\.css">)', '$1`r`n  <link rel="stylesheet" href="styles/license.css">'
}

# Replace error container
$content = $content -replace '<div style="background:#fee2e2; color:#991b1b; border:1px solid #fecaca; padding:12px 16px; border-radius:8px; margin-bottom:16px;">', '<div class="error-container">'

# Replace result container
$content = $content -replace '<div style="margin:150px 10px; border:1px solid #e5e7eb; border-radius:8px; padding:16px; background:#ffffff;">', '<div class="result-container">'
$content = $content -replace '<h2 style="margin-bottom:16px; font-size:20px; font-weight:bold;">Result</h2>', '<h2>Result</h2>'

# Replace result message
$content = $content -replace '<div style="margin-bottom:16px; padding:12px 14px; border:1px solid <\?php echo h\(\$border\); \?>; background:<\?php echo h\(\$bg\); \?>; color:<\?php echo h\(\$color\); \?>; border-radius:8px;">', '<div class="result-message <?php echo ($resultTag === ''SUCCESS'') ? ''success'' : ''error''; ?>">'

# Replace server response
$content = $content -replace '<div style="margin:8px 0; font-weight:bold;">Server Response:</div>', '<div class="server-response">Server Response:</div>'
$content = $content -replace '<pre\s+style="background:#0f172a; color:#e2e8f0; padding:16px; border-radius:8px; overflow:auto; margin-bottom:16px;">', '<pre class="server-response-pre">'

# Replace back button container
$content = $content -replace '<div style="margin-top:24px; text-align:center;">', '<div class="result-back-container">'
$content = $content -replace '<a href="licenses\.php"\s+style="display:inline-block; padding:5px 18px; background:#2563eb; color:white; border:none; border-radius:6px; cursor:pointer; text-decoration:none;">Back</a>', '<a href="licenses.php" class="btn-back">Back</a>'

# Replace form
$content = $content -replace '<form method="post"\s+style="[^"]*">', '<form method="post" class="license-form">'

# Replace form sections
$content = $content -replace '<div style="margin-bottom:28px; padding-bottom:20px; border-bottom:2px solid #f1f5f9;">', '<div class="form-section">'
$content = $content -replace '<h3\s+style="margin:0 0 16px 0; font-size:18px; font-weight:600; color:#1e293b; padding-bottom:8px; border-bottom:1px solid #e2e8f0;">', '<h3>'

# Replace form grids
$content = $content -replace '<div style="display:grid; grid-template-columns:repeat\(auto-fit, minmax\(250px, 1fr\)\); gap:16px;">', '<div class="form-grid">'
$content = $content -replace '<div style="display:grid; grid-template-columns:repeat\(auto-fit, minmax\(200px, 1fr\)\); gap:8px;">', '<div class="form-grid-hardware">'

# Replace labels and inputs - remove all inline styles from labels
$content = $content -replace '<label\s+style="[^"]*">', '<label>'

# Replace inputs - remove all inline styles and onfocus/onblur
$content = $content -replace '(<input[^>]*)\s+style="[^"]*"([^>]*onfocus="[^"]*"[^>]*onblur="[^"]*"[^>]*)>', '$1$2>'
$content = $content -replace '(<input[^>]*)\s+onfocus="[^"]*"', '$1'
$content = $content -replace '(<input[^>]*)\s+onblur="[^"]*"', '$1'
$content = $content -replace '(<input[^>]*)\s+style="[^"]*"', '$1'

# Replace selects
$content = $content -replace '(<select[^>]*)\s+style="[^"]*"([^>]*onfocus="[^"]*"[^>]*onblur="[^"]*"[^>]*)>', '$1$2>'
$content = $content -replace '(<select[^>]*)\s+onfocus="[^"]*"', '$1'
$content = $content -replace '(<select[^>]*)\s+onblur="[^"]*"', '$1'
$content = $content -replace '(<select[^>]*)\s+style="[^"]*"', '$1'

# Replace comment section
$content = $content -replace '<div style="margin-bottom:24px;">', '<div class="comment-section">'

# Replace action buttons container
$content = $content -replace '<div\s+style="margin-top:24px; padding-top:20px; border-top:2px solid #f1f5f9; display:flex; gap:12px; flex-wrap:wrap; justify-content:center;">', '<div class="form-actions">'

# Replace submit button
$content = $content -replace '(<button type="submit" name="action" value="send")\s+style="[^"]*"([^>]*onmouseover="[^"]*"[^>]*onmouseout="[^"]*"[^>]*onmousedown="[^"]*"[^>]*onmouseup="[^"]*"[^>]*)>', '$1 class="btn-submit"$2>'
$content = $content -replace '(<button[^>]*class="btn-submit"[^>]*)\s+onmouseover="[^"]*"', '$1'
$content = $content -replace '(<button[^>]*class="btn-submit"[^>]*)\s+onmouseout="[^"]*"', '$1'
$content = $content -replace '(<button[^>]*class="btn-submit"[^>]*)\s+onmousedown="[^"]*"', '$1'
$content = $content -replace '(<button[^>]*class="btn-submit"[^>]*)\s+onmouseup="[^"]*"', '$1'

# Save the file
Set-Content -Path $filePath -Value $content -NoNewline

Write-Host "File updated successfully!"
