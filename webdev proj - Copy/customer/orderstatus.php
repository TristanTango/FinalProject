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

$order_id = $_GET['order_id'];

$result = mysqli_query($conn, "SELECT * FROM orders WHERE id='$order_id'");
$order = mysqli_fetch_assoc($result);
?>


<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Products</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body class="bg-light">

<!-- NAVBAR -->
<nav class="navbar navbar-dark bg-dark">

    <div class="container-fluid">

        <a class="navbar-brand">
            Coffee Shop Admin
        </a>

        <a href="dashboard.php"
           class="btn btn-outline-light">

           Dashboard

        </a>

    </div>

</nav>

<!-- MAIN CONTAINER -->
<div class="container mt-5">

    <div class="row">

        <!-- ADD PRODUCT FORM -->
        <div class="col-md-4">

            <div class="card shadow">

                <div class="card-header bg-primary text-white">

                    <h4>Add Product</h4>

                </div>

                <div class="card-body">

                    <form method="POST">

                        <div class="mb-3">

                            <label>Product Name</label>

                            <input type="text"
                                   name="name"
                                   class="form-control"
                                   required>

                        </div>

                        <div class="mb-3">

                            <label>Price</label>

                            <input type="number"
                                   name="price"
                                   class="form-control"
                                   required>

                        </div>

                        <div class="mb-3">

                            <label>Stock</label>

                            <input type="number"
                                   name="stock"
                                   class="form-control"
                                   required>

                        </div>

                        <button type="submit"
                                name="add_product"
                                class="btn btn-success w-100">

                            Add Product

                        </button>

                    </form>

                </div>

            </div>

        </div>

        <!-- PRODUCT TABLE -->
        <div class="col-md-8">

            <div class="card shadow">

                <div class="card-header bg-dark text-white">

                    <h4>Product List</h4>

                </div>

                <div class="card-body">

                    <table class="table table-bordered table-hover">

                        <thead class="table-dark">

                            <tr>

                                <th>ID</th>
                                <th>Name</th>
                                <th>Price</th>
                                <th>Stock</th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php

                            $query = "SELECT * FROM products";
                            $result = mysqli_query($conn, $query);

                            while($row = mysqli_fetch_assoc($result)){

                            ?>

                            <tr>

                                <td><?php echo $row['id']; ?></td>

                                <td><?php echo $row['name']; ?></td>

                                <td>₱<?php echo $row['price']; ?></td>

                                <td><?php echo $row['stock']; ?></td>

                            </tr>

                            <?php } ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    </div>

</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>