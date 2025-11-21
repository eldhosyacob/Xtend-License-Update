<?php
// Redirect to dashboard if already logged in
require_once('config/login_redirect.php');
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Xtend License | Login</title>
  <link rel="stylesheet" href="styles/login.css">
</head>

<body>
  <div class="login-page">
    <div class="login-card">
      <section class="login-section">
        <div class="login-header">
          <div class="login-brand">
            <img src="images/xtend-logo.png" alt="Xtend Logo">
            <div>
              <!-- <p class="brand-label">Xtend License Portal</p> -->
              <h3>Welcome Back!</h3>
            </div>
          </div>
          <!-- <p class="subtext">Manage licenses, track activations, and stay compliant.</p> -->
        </div>
        <form class="login-form" id="loginForm">
          <div class="error-message" id="errorMessage" style="display: none;"></div>

          <label for="username">Username</label>
          <input id="username" name="username" type="text" placeholder="Enter your username" minlength="3" required>

          <label for="password">Password</label>
          <input id="password" name="password" type="password" placeholder="Enter your password" minlength="5" required>

          <button type="submit" id="loginBtn">Sign In</button>
        </form>
        <p class="helper-text">Having trouble signing in? Contact your administrator.</p>
      </section>
      <aside class="login-illustration" aria-hidden="true">
        <div class="license-graphic">
          <svg viewBox="0 0 320 200" role="img">
            <title>License Illustration</title>
            <rect x="15" y="25" width="290" height="150" rx="16" class="card-bg" />
            <rect x="35" y="50" width="120" height="20" rx="4" class="card-line" />
            <rect x="35" y="80" width="160" height="20" rx="4" class="card-line" />
            <rect x="35" y="110" width="180" height="20" rx="4" class="card-line" />
            <rect x="35" y="140" width="140" height="20" rx="4" class="card-line" />
            <circle cx="245" cy="130" r="32" class="card-stamp" />
            <path d="M222 95 l23 23 45-45" class="card-check" />
          </svg>
          <!-- <p>Secure license management at a glance.</p> -->
        </div>
      </aside>
    </div>
  </div>

  <script src="plugins/jquery-3.7.1.min.js"></script>
  <script>
    $(document).ready(function() {
      $('#loginForm').on('submit', function(e) {
        e.preventDefault();

        const username = $('#username').val().trim();
        const password = $('#password').val();

        // Client-side validation
        if (username.length < 3) {
          showError('Username must be at least 3 characters');
          return;
        }

        if (password.length < 5) {
          showError('Password must be at least 5 characters');
          return;
        }

        // Disable button during request
        $('#loginBtn').prop('disabled', true).text('Signing In...');
        hideError();

        // AJAX request
        $.ajax({
          url: 'api/login.php',
          type: 'POST',
          dataType: 'json',
          data: {
            username: username,
            password: password
          },
          success: function(response) {
            console.log("login ajax 1:", response);
            if (response.success) {
              window.location.href = 'dashboard.php';
            } else {
              showError(response.message || 'Login failed. Please try again.');
              $('#loginBtn').prop('disabled', false).text('Sign In');
            }
          },
          error: function(xhr, status, error) {
            console.log("login ajax error:", xhr.responseText);
            showError('An error occurred. Please try again.');
            $('#loginBtn').prop('disabled', false).text('Sign In');
          }
        });
      });

      function showError(message) {
        $('#errorMessage').text(message).slideDown();
      }

      function hideError() {
        $('#errorMessage').slideUp();
      }
    });
  </script>
</body>

</html>