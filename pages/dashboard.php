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

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: linear-gradient(135deg, #dc3545, #bb2d3b);
            min-height: 100vh;
        }

        .card-box {
            max-width: 1000px;
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
        }

        .admin-buttons a {
            width: 100%;
            margin-bottom: 10px;
        }

        .logout-center {
            text-align: center;
            margin-top: 30px;
        }
    </style>
</head>

<body>

<nav class="navbar navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="../index.php">ResQLink</a>
    </div>
</nav>

<div class="card-box">

<h2 class="title">Welcome, <?php echo htmlspecialchars($full_name); ?></h2>
<p>You are logged in as <b><?php echo $role_name; ?></b></p>

<hr>

<div class="row">

    <!-- LEFT SIDE -->
<div class="col-md-8">
    <div class="info-box">
        <h5>System Info</h5>
        <p class="mb-0">
            <?php if ($role_id == 2): ?>
                You can manage disaster alerts, shelters, resources, and evacuation tracking.
            <?php else: ?>
                Stay updated with alerts, find nearby shelters, and update your evacuation status.
            <?php endif; ?>
        </p>
    </div>
</div>

    <!-- RIGHT SIDE -->
    <div class="col-md-4">

        <?php if ($role_id == 2): ?>
        <!-- ADMIN BUTTONS -->
        <div class="admin-buttons">

            <a href="alerts.php" class="btn btn-warning">View Alert</a>

            <a href="admin/create_alert.php" class="btn btn-dark">Create Alert</a>

            <a href="admin/manage_shelters.php" class="btn btn-primary">Manage Shelters</a>

            <a href="admin/manage_resources.php" class="btn btn-info text-white">Manage Resources</a>

            <a href="admin/manage_evacuation.php" class="btn btn-secondary">Manage Evacuation</a>

        </div>

        <?php else: ?>
        <!-- USER BUTTONS -->
        <div class="d-flex flex-wrap gap-2">

            <a href="alerts.php" class="btn btn-warning">View Alert</a>

            <a href="shelters.php" class="btn btn-info">View Shelters</a>

            <a href="resources.php" class="btn btn-primary">Resources</a>

            <a href="evacuation_status.php" class="btn btn-success">Update Evacuation</a>

        </div>
        <?php endif; ?>

    </div>

</div>

<!-- ✅ LOGOUT CENTER BOTTOM -->
<div class="logout-center">
    <a href="logout.php" class="btn btn-danger px-5">Logout</a>
</div>

</div>

</body>
</html>