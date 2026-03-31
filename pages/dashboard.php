<?php
session_start();
require_once __DIR__ . "/../DB/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$role_id = (int) $_SESSION['role_id'];

$count = 0;

if ($role_id != 2) {
    $q = $conn->query("
        SELECT COUNT(*) AS total
        FROM alert_notifications
        WHERE user_id = $user_id AND is_read = 0
    ");
    if ($q) {
        $count = (int) $q->fetch_assoc()['total'];
    }
}

$role_name = "User";
if ($role_id == 1) $role_name = "Citizen";
elseif ($role_id == 2) $role_name = "Admin";
elseif ($role_id == 3) $role_name = "Rescue Team";
elseif ($role_id == 4) $role_name = "Government";
elseif ($role_id == 5) $role_name = "System Admin";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Dashboard - ResQLink</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: linear-gradient(135deg, #dc3545 0%, #bb2d3b 100%);
            min-height: 100vh;
        }

        .card-box {
            max-width: 900px;
            margin: 60px auto;
            background: #fff;
            border-radius: 14px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .title {
            color: #dc3545;
            font-weight: bold;
        }

        .info-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            height: 100%;
        }

        .btn-area a {
            min-width: 160px;
        }
    </style>
</head>

<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="../index.php">
            <span class="badge bg-danger">ResQLink</span>
        </a>
    </div>
</nav>

<div class="card-box">

    <h2 class="title">Welcome, <?php echo htmlspecialchars($full_name); ?></h2>
    <p>You are logged in as <b><?php echo htmlspecialchars($role_name); ?></b></p>

    <hr>

    <div class="row mb-4 g-3">

        <?php if ($role_id != 2): ?>
        <div class="col-md-6">
            <div class="info-box">
                <h5>Unread Alerts</h5>
                <h3><?php echo $count; ?></h3>
            </div>
        </div>
        <?php endif; ?>

        <div class="col-md-6">
            <div class="info-box">
                <h5>System Info</h5>
                <p class="mb-0">
                    <?php if ($role_id == 2): ?>
                        You can create and manage disaster alerts for all users.
                    <?php else: ?>
                        Stay updated with disaster alerts and emergency notifications.
                    <?php endif; ?>
                </p>
            </div>
        </div>

    </div>

    <div class="btn-area d-flex flex-wrap gap-3">
        <a href="alerts.php" class="btn btn-warning">View Alert</a>

        <?php if ($role_id == 2): ?>
            <a href="admin/create_alert.php" class="btn btn-dark">Create Alert</a>
        <?php endif; ?>

        <a href="logout.php" class="btn btn-danger">Logout</a>
    </div>

</div>

</body>
</html>