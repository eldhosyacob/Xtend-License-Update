<?php
session_start();

echo "Port: " . $_SERVER['SERVER_PORT'] . "<br>";
echo "Session Name: " . session_name() . "<br>";
echo "Session ID: " . session_id() . "<br>";

echo "<pre>";
print_r($_SESSION);
echo "</pre>";