<?php
// Include this file at the top of pages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id']) || !isset($_SESSION['username'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}
