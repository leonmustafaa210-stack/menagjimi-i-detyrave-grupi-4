<?php
session_start();

// Check if the user is already logged in
if (isset($_SESSION['user'])) {
    // Redirect to the dashboard if the user is logged in
    header('Location: Dashboard.php');
    exit;
}

// Redirect to the login page if the user is not logged in
header('Location: login.php');
exit;
