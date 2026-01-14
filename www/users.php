<?php
session_start();
require_once('config/database.php');

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
  header("Location: index.php");
  exit;
}

$db = getDatabaseConnection();
$users = [];
if ($db) {
  $stmt = $db->query("SELECT id, username, full_name, role FROM users ORDER BY id DESC");
  $users = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html>

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title>Users</title>
  <link rel="shortcut icon" href="images/favicon.png" />
  <link rel="stylesheet" href="styles/users.css">
  <link rel="stylesheet" href="styles/header-sidebar.css">
  <link rel="stylesheet" href="styles/common.css">
  <script src="plugins/jquery-3.7.1.min.js"></script>
</head>


<?php
$currentUserRole = $_SESSION['role'] ?? 'Limited Access';
$isAdmin = $currentUserRole === 'Administrator';
?>

<body>
  <div class="users-page-container page-containers">
    <div class="page-header-actions">
      <div class="gradient-text">User Management</div>
      <?php if ($isAdmin): ?>
        <button class="btn-primary" id="addUserBtn">Add User</button>
      <?php endif; ?>
    </div>

    <div class="users-table-container">
      <table class="users-table">
        <thead>
          <tr class="users-table-header" style="background-color: #f34134;">
            <th>Sl No</th>
            <th>Username</th>
            <th>Full Name</th>
            <th>Role</th>
            <?php if ($isAdmin): ?>
              <th>Actions</th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $index => $user): ?>
            <tr>
              <td><?php echo $index + 1; ?></td>
              <td><?php echo htmlspecialchars($user['username']); ?></td>
              <td><?php echo htmlspecialchars($user['full_name']); ?></td>
              <td><?php echo htmlspecialchars($user['role']); ?></td>
              <?php if ($isAdmin): ?>
                <td>
                  <button class="btn-edit" data-id="<?php echo $user['id']; ?>"
                    data-username="<?php echo htmlspecialchars($user['username']); ?>"
                    data-fullname="<?php echo htmlspecialchars($user['full_name']); ?>"
                    data-role="<?php echo htmlspecialchars($user['role']); ?>">
                    Edit
                  </button>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Add/Edit User Modal -->
  <div id="userModal" class="modal">
    <div class="modal-content">
      <span class="close">&times;</span>
      <h2 id="modalTitle" class="gradient-text">Add User</h2>
      <form id="userForm">
        <input type="hidden" id="userId" name="user_id">

        <div class="form-group">
          <label for="username">Username</label>
          <input type="text" id="username" name="username" minlength="3" required>
        </div>

        <div class="form-group">
          <label for="fullName">Full Name</label>
          <input type="text" id="fullName" name="full_name" required>
        </div>

        <div class="form-group">
          <label for="role">Role</label>
          <select id="role" name="role" required
            style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            <option value="Administrator">Administrator</option>
            <option value="Limited Access">Limited Access</option>
          </select>
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" minlength="5"
            placeholder="Leave blank to keep current password (for edit)">
        </div>

        <div class="form-actions">
          <button type="submit" class="btn-submit">Save User</button>
        </div>
      </form>
    </div>
  </div>

  <?php include 'components/header-sidebar.php'; ?>

  <script>
    $(document).ready(function () {
      var modal = $('#userModal');
      var span = $('.close');

      // Open modal for adding user
      $('#addUserBtn').on('click', function () {
        $('#modalTitle').text('Add New User');
        $('#userId').val('');
        $('#username').val('');
        $('#fullName').val('');
        $('#role').val('Limited Access');
        $('#password').val('');
        $('#password').attr('placeholder', 'Required for new users');
        $('#password').prop('required', true);
        modal.show();
      });

      // Open modal for editing user
      $('.btn-edit').on('click', function () {
        var id = $(this).data('id');
        var username = $(this).data('username');
        var fullname = $(this).data('fullname');
        var role = $(this).data('role');

        $('#modalTitle').text('Edit User');
        $('#userId').val(id);
        $('#username').val(username);
        $('#fullName').val(fullname);
        $('#role').val(role);
        $('#password').val('');
        $('#password').attr('placeholder', 'Leave blank to keep current password');
        $('#password').prop('required', false);
        modal.show();
      });

      // Close modal
      span.on('click', function () {
        modal.hide();
      });

      $(window).on('click', function (event) {
        if (event.target == modal[0]) {
          modal.hide();
        }
      });

      // Handle form submission
      $('#userForm').on('submit', function (e) {
        e.preventDefault();

        const username = $('#username').val().trim();
        const password = $('#password').val();
        const userId = $('#userId').val();

        // Client-side validation
        if (username.length < 3) {
          alert('Username must be at least 3 characters');
          return;
        }

        // Password validation (only if provided or if creating new user)
        if ((!userId && password.length < 5) || (userId && password.length > 0 && password.length < 5)) {
          alert('Password must be at least 5 characters');
          return;
        }

        const btn = $('.btn-submit');
        const originalText = btn.text();

        // Disable button during request
        btn.prop('disabled', true).text('Saving...');

        $.ajax({
          url: 'api/add_user.php',
          type: 'POST',
          data: $(this).serialize(),
          dataType: 'json',
          success: function (response) {
            if (response.success) {
              alert(response.message);
              location.reload();
            } else {
              alert(response.message || 'Operation failed');
              btn.prop('disabled', false).text(originalText);
            }
          },
          error: function (xhr, status, error) {
            console.log("Error:", xhr.responseText);
            alert('An error occurred. Please try again.');
            btn.prop('disabled', false).text(originalText);
          }
        });
      });

      // Check URL parameters for auto-opening edit modal (from settings button)
      const urlParams = new URLSearchParams(window.location.search);
      const editUserId = urlParams.get('edit');
      const editUsername = urlParams.get('username');
      const editFullname = urlParams.get('fullname');

      if (editUserId && editUsername) {
        // Auto-open edit modal with user data from URL
        $('#modalTitle').text('Edit User');
        $('#userId').val(editUserId);
        $('#username').val(editUsername);
        $('#fullName').val(editFullname || '');
        $('#password').val('');
        $('#password').attr('placeholder', 'Leave blank to keep current password');
        $('#password').prop('required', false);
        modal.show();

        // Clean up URL after opening modal (optional)
        window.history.replaceState({}, document.title, window.location.pathname);
      }
    });
  </script>

</body>

</html>