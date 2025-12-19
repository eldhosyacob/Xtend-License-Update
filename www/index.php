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
  <link rel="shortcut icon" href="images/favicon.png" />
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
              <div class="welcome-text"
                style="font-size: 18px; font-weight: 650; background-color:conic-gradient(#553c9a, #ee4b2b, #00c2cb, #553c9a)">
                Welcome Back!</div>
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
        <div class="license-image">
          <img src="images/login-icon.png" alt="Login Icon">
          <!-- <svg viewBox="0 0 800 600" role="img" xmlns="http://www.w3.org/2000/svg">
            <title>Modern License Management</title>
            <defs>
              <filter id="softGlow" x="-20%" y="-20%" width="140%" height="140%">
                <feGaussianBlur stdDeviation="15" result="blur" />
                <feComposite in="SourceGraphic" in2="blur" operator="over" />
              </filter>
              <linearGradient id="serverGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" style="stop-color:#f8fafc;stop-opacity:0.9" />
                <stop offset="100%" style="stop-color:#e2e8f0;stop-opacity:0.8" />
              </linearGradient>
              <linearGradient id="blueGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" style="stop-color:#60a5fa;stop-opacity:1" />
                <stop offset="100%" style="stop-color:#3b82f6;stop-opacity:1" />
              </linearGradient>
            </defs>

            <path d="M400 500 L100 350 L400 200 L700 350 Z" fill="rgba(255,255,255,0.05)" />
            <path d="M400 520 L100 370 L400 220 L700 370 Z" fill="rgba(255,255,255,0.03)" />
            <g transform="translate(200, 280)">
              <path d="M0 40 L60 10 L120 40 L60 70 Z" fill="#e2e8f0" />
              <path d="M0 40 L60 70 V 170 L0 140 Z" fill="#cbd5e1" />
              <path d="M120 40 L60 70 V 170 L120 140 Z" fill="#94a3b8" />
              <circle cx="20" cy="60" r="3" fill="#10b981" />
              <circle cx="20" cy="75" r="3" fill="#10b981" />
              <circle cx="20" cy="90" r="3" fill="#ef4444" />
            </g>

            <g transform="translate(340, 220)">
              <path d="M60 0 L120 30 V 90 C 120 140, 60 170, 60 170 C 60 170, 0 140, 0 90 V 30 Z" fill="url(#blueGrad)"
                filter="url(#softGlow)" />
              <path d="M60 10 L110 35 V 85 C 110 125, 60 150, 60 150 C 60 150, 10 125, 10 85 V 35 Z"
                fill="rgba(255,255,255,0.2)" />
              <path d="M40 80 L55 95 L85 55" stroke="white" stroke-width="8" stroke-linecap="round"
                stroke-linejoin="round" fill="none" />
            </g>

            <g transform="translate(520, 300)">
              <rect x="0" y="0" width="80" height="60" rx="4" fill="rgba(255,255,255,0.9)" transform="skewY(-15)" />
              <rect x="10" y="10" width="60" height="5" rx="2" fill="#cbd5e1" transform="skewY(-15)" />
              <rect x="10" y="25" width="40" height="5" rx="2" fill="#cbd5e1" transform="skewY(-15)" />

              <g transform="translate(20, -40)">
                <rect x="0" y="0" width="80" height="60" rx="4" fill="rgba(255,255,255,0.9)" transform="skewY(-15)" />
                <rect x="10" y="10" width="60" height="5" rx="2" fill="#3b82f6" transform="skewY(-15)" />
                <rect x="10" y="25" width="40" height="5" rx="2" fill="#93c5fd" transform="skewY(-15)" />
              </g>
            </g>

            <path d="M260 300 Q 340 350 400 390" stroke="rgba(255,255,255,0.3)" stroke-width="2" stroke-dasharray="5,5"
              fill="none" />
            <path d="M540 320 Q 460 360 400 390" stroke="rgba(255,255,255,0.3)" stroke-width="2" stroke-dasharray="5,5"
              fill="none" />

            <circle cx="300" cy="320" r="4" fill="#fff">
              <animate attributeName="opacity" values="0;1;0" dur="2s" repeatCount="indefinite" />
            </circle>
            <circle cx="480" cy="340" r="3" fill="#fff">
              <animate attributeName="opacity" values="0;1;0" dur="2s" begin="1s" repeatCount="indefinite" />
            </circle>
          </svg> -->
        </div>
      </aside>
    </div>
  </div>

  <script src="plugins/jquery-3.7.1.min.js"></script>
  <script>
    $(document).ready(function () {
      // Check for URL parameters
      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.get('error') === 'session_expired') {
        showError('Your session has expired');
      }

      $('#loginForm').on('submit', function (e) {
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
          success: function (response) {
            console.log("login ajax 1:", response);
            if (response.success) {
              window.location.href = 'dashboard.php';
            } else {
              showError(response.message || 'Login failed. Please try again.');
              $('#loginBtn').prop('disabled', false).text('Sign In');
            }
          },
          error: function (xhr, status, error) {
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