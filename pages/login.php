<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ResQLink</title>

    <link rel="stylesheet" href="../css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: linear-gradient(135deg, #dc3545 0%, #bb2d3b 100%);
            min-height: 100vh;
        }

        .login-card {
            max-width: 450px;
            margin: 80px auto;
            background: #fff;
            border-radius: 14px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .login-title {
            color: #dc3545;
            font-weight: 700;
            text-align: center;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand fw-bold" href="../index.php">
            <span class="badge bg-danger">ResQLink</span>
        </a>
    </div>
</nav>

<div class="container">
    <div class="login-card">
        <h2 class="login-title">Login</h2>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form action="login_action.php" method="POST">
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-danger w-100">Login</button>
        </form>

        <div class="text-center mt-3">
            <a href="register.php" class="text-decoration-none">Create account</a>
        </div>
    </div>
</div>

</body>
</html>