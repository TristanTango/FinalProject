<?php

$conn = mysqli_connect(
    "localhost",
    "root",
    "010523",
    "kingscup_db"
);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

mysqli_set_charset($conn, 'utf8mb4');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>