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
                <form class="login-form">
                    <label for="username">Username</label>
                    <input id="username" type="text" placeholder="Enter your username">

                    <label for="password">Password</label>
                    <input id="password" type="password" placeholder="Enter your password">

                    <button type="submit">Sign In</button>
                </form>
                <p class="helper-text">Having trouble signing in? Contact your administrator.</p>
            </section>
            <aside class="login-illustration" aria-hidden="true">
                <div class="license-graphic">
                    <svg viewBox="0 0 320 200" role="img">
                        <title>License Illustration</title>
                        <rect x="15" y="25" width="290" height="150" rx="16" class="card-bg"/>
                        <rect x="35" y="50" width="120" height="20" rx="4" class="card-line"/>
                        <rect x="35" y="80" width="160" height="20" rx="4" class="card-line"/>
                        <rect x="35" y="110" width="180" height="20" rx="4" class="card-line"/>
                        <rect x="35" y="140" width="140" height="20" rx="4" class="card-line"/>
                        <circle cx="245" cy="130" r="32" class="card-stamp"/>
                        <path d="M222 95 l23 23 45-45" class="card-check"/>
                    </svg>
                    <!-- <p>Secure license management at a glance.</p> -->
                </div>
            </aside>
        </div>
    </div>
</body>
</html>