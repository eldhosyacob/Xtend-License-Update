<?php
/**
 * INSTRUCTIONS FOR IMPLEMENTING COMMENTS FUNCTIONALITY
 * 
 * This file contains the code snippets you need to add to licenses.php
 * Follow the steps below to implement the comments functionality.
 */

// ============================================================================
// STEP 1: Add this code after line 466 in licenses.php
// (After: $stmt->execute($params);)
// ============================================================================
?>

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

<?php
// ============================================================================
// STEP 2: Replace the showCommentPopup() function (around line 994)
// Replace the entire function with this:
// ============================================================================
?>

async function showCommentPopup() {
const licenseId = '<?php echo h($editId); ?>';
if (!licenseId) return;

try {
const response = await fetch(`api/comments.php?license_id=${licenseId}`);
const comments = await response.json();

let html = '';
if (comments.length === 0) {
html = '<p style="color:#94a3b8; font-style:italic;">No previous comments</p>';
} else {
comments.forEach(comment => {
html += `
<div style="border-bottom:1px solid #e2e8f0; padding-bottom:12px; margin-bottom:12px;">
    <div style="font-weight:600; color:#1e293b; margin-bottom:4px;">${comment.commented_by || 'Unknown'}</div>
    <div style="font-size:12px; color:#64748b; margin-bottom:8px;">${comment.formatted_date || comment.created_at}</div>
    <div style="color:#475569;">${comment.comment}</div>
</div>
`;
});
}

document.getElementById('existingCommentText').innerHTML = html;
document.getElementById('commentPopup').style.display = 'flex';
} catch (error) {
console.error('Error fetching comments:', error);
document.getElementById('existingCommentText').innerText = 'Error loading comments';
document.getElementById('commentPopup').style.display = 'flex';
}
}

<?php
// ============================================================================
// STEP 3: Update the popup HTML (around line 982-984)
// Replace the <p> tag with this <div>:
// ============================================================================
?>

<div id="existingCommentText"
    style="color:#475569; font-size:14px; line-height:1.5; margin-bottom:24px; max-height:400px; overflow-y:auto;">
</div>

<?php
/**
 * SUMMARY:
 * 
 * 1. After the license is saved to license_details, also save the comment to the comments table
 * 2. Update the JavaScript function to fetch all comments from the API
 * 3. Change the popup container from <p> to <div> to support HTML content
 * 
 * The API (api/comments.php) is already complete and working.
 */
?>