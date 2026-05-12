<?php
// includes/auth.php
session_start();

// If not logged in, boot them to login page
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Optional: Function to restrict pages to Admin only
function requireAdmin() {
    if ($_SESSION['role'] !== 'admin') {
        // If a customer tries to access an admin page, send them to the menu
        header("Location: ../customer/menu.php"); 
        exit();
    }
}
?>