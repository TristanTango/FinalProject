<?php
include '../config/db.php';

$conn = mysqli_connect(
    "localhost",
    "root",
    "010523",
    "kingscup_db"
);

if(!$conn){
    die("Connection Failed");
}

session_start();


if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $product_id = $_POST['product_id'];
    $price = $_POST['price'];
    $qty = $_POST['qty'];

    // COMPUTE TOTAL
    $total = $price * $qty;

    // SAVE ORDER
    $query = "INSERT INTO orders (total) VALUES ('$total')";
    mysqli_query($conn, $query);

    $order_id = mysqli_insert_id($conn);

    // REDIRECT TO STATUS PAGE
    header("Location: order_status.php?order_id=$order_id");
    exit();
}
?>