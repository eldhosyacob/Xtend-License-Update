<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Check if logout is confirmed
if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
  // Unset all session variables
  $_SESSION = array();

  // Destroy the session cookie
  if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
  }

  // Destroy the session
  session_destroy();

  // Redirect to login page
  header('Location: index.php');
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Logout Confirmation</title>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
      background-color: #f5f5f5;
    }

    /* Modal Styles */
    .modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.5);
    }

    .modal-content {
      background-color: #fefefe;
      margin: 15% auto;
      padding: 30px;
      border: 1px solid #888;
      width: 400px;
      border-radius: 12px;
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
      position: relative;
      text-align: center;
    }

    .modal-content h2 {
      margin-bottom: 20px;
      color: #333;
      font-size: 24px;
    }

    .modal-content p {
      margin-bottom: 30px;
      color: #666;
      font-size: 16px;
    }

    .modal-actions {
      display: flex;
      gap: 10px;
      justify-content: center;
    }

    .btn {
      padding: 10px 30px;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      font-size: 14px;
      font-weight: 500;
      transition: background-color 0.3s;
    }

    .btn-confirm {
      background-color: #dc3545;
      color: white;
    }

    .btn-confirm:hover {
      background-color: #c82333;
    }

    .btn-cancel {
      background-color: #6c757d;
      color: white;
    }

    .btn-cancel:hover {
      background-color: #5a6268;
    }
  </style>
</head>

<body>
  <!-- Logout Confirmation Modal -->
  <div id="logoutModal" class="modal">
    <div class="modal-content">
      <h2>Confirm Logout</h2>
      <p>Are you sure you want to logout?</p>
      <div class="modal-actions">
        <button class="btn btn-cancel" id="cancelBtn">Cancel</button>
        <button class="btn btn-confirm" id="confirmBtn">Logout</button>
      </div>
    </div>
  </div>

  <script>
    // Show modal on page load
    window.onload = function () {
      var modal = document.getElementById('logoutModal');
      var confirmBtn = document.getElementById('confirmBtn');
      var cancelBtn = document.getElementById('cancelBtn');

      // Show the modal
      modal.style.display = 'block';

      // Confirm logout
      confirmBtn.onclick = function () {
        window.location.href = 'logout.php?confirm=yes';
      };

      // Cancel logout
      cancelBtn.onclick = function () {
        window.history.back();
      };
    };
  </script>
</body>

</html>