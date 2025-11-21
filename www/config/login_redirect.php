<?php
// Include this file at the top of login.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['id']) && isset($_SESSION['username'])) {
    header('Location: dashboard.php');
    exit;
}
