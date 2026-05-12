<?php

require_once __DIR__ . '/../includes/config.php';

if (!isset($conn)) {
    die('Database connection not available.');
}

// Already logged in? Skip login page
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = "";

if (isset($_POST['login'])) {

    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // Use prepared statement to prevent SQL injection
    $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE username = ? AND role = 'admin'");
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) > 0) {

        $user = mysqli_fetch_assoc($result);

        // Verify hashed password
        if (password_verify($password, $user['password'])) {

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role']    = $user['role'];

            header("Location: dashboard.php");
            exit();

        } else {
            $error = "Invalid username or password.";
        }

    } else {
        $error = "Invalid username or password.";
    }

}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login – King's Cup</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: #3B1F0F;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-wrap {
            background: #F0EDE8;
            border-radius: 16px;
            padding: 40px 36px;
            width: 100%;
            max-width: 380px;
            box-shadow: 0 8px 40px rgba(0,0,0,.35);
        }

        .brand {
            text-align: center;
            margin-bottom: 28px;
        }

        .brand-logo {
            width: 56px; height: 56px;
            background: #C8A96E;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-family: 'Playfair Display', serif;
            font-size: 24px;
            color: #3B1F0F;
            font-weight: 700;
            margin: 0 auto 12px;
        }

        .brand h1 {
            font-family: 'Playfair Display', serif;
            font-size: 22px;
            color: #2A1A0A;
        }

        .brand p {
            font-size: 13px;
            color: #A89282;
            margin-top: 3px;
        }

        .alert-error {
            background: #FEE2E2;
            color: #991B1B;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 13px;
            margin-bottom: 18px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #6B5744;
            margin-bottom: 6px;
        }

        input {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #E2D9CF;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'DM Sans', sans-serif;
            background: #fff;
            color: #2A1A0A;
            outline: none;
            transition: border-color .2s;
        }

        input:focus {
            border-color: #C8A96E;
        }

        .btn-login {
            width: 100%;
            padding: 12px;
            background: #3B1F0F;
            color: #C8A96E;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            font-family: 'DM Sans', sans-serif;
            cursor: pointer;
            margin-top: 8px;
            transition: background .2s;
        }

        .btn-login:hover {
            background: #C8A96E;
            color: #3B1F0F;
        }
    </style>
</head>
<body>

<div class="login-wrap">

    <div class="brand">
        <div class="brand-logo">K</div>
        <h1>King's Cup</h1>
        <p>Admin Dashboard Login</p>
    </div>

    <?php if ($error): ?>
        <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST">

        <div class="form-group">
            <label for="username">Username</label>
            <input type="text"
                   id="username"
                   name="username"
                   placeholder="Enter your username"
                   required
                   autocomplete="username">
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password"
                   id="password"
                   name="password"
                   placeholder="Enter your password"
                   required
                   autocomplete="current-password">
        </div>

        <button type="submit" name="login" class="btn-login">
            Login
        </button>

    </form>

</div>

</body>
</html>