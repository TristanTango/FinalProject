<?php
// customer/menu.php
require_once '../includes/auth.php';   // Ensures user is logged in
require_once '../includes/config.php'; // Database connection

// Optional: check if they are actually a customer
if ($_SESSION['role'] !== 'customer') {
    header("Location: ../admin/dashboard.php");
    exit();
}

include '../includes/customer_header.php'; 
?>

<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="../css/customer.css">
    <title>Menu - Kings Cup</title>
</head>
<body>
    <h1>Kings Cup Menu</h1>
    
    <div class="menu-container">
        <?php
        $sql = "SELECT * FROM products WHERE status = 'available'";
        $result = mysqli_query($conn, $sql);

        if (mysqli_num_rows($result) > 0) {
            while($row = mysqli_fetch_assoc($result)) {
                echo "<div class='menu-item'>";
                echo "<h3>" . htmlspecialchars($row['item_name']) . "</h3>";
                echo "<p>" . htmlspecialchars($row['description']) . "</p>";
                echo "<span>₱" . number_format($row['price'], 2) . "</span>";
                echo "<form action='../ajax/addorder.php' method='POST'>";
                echo "<input type='hidden' name='product_id' value='".$row['id']."'>";
                echo "<button type='submit'>Add to Order</button>";
                echo "</form>";
                echo "</div>";
            }
        } else {
            echo "<p>No items available right now.</p>";
        }
        ?>
    </div>
</body>
</html>