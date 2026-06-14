<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['site_logged_in']) || $_SESSION['site_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}
?>