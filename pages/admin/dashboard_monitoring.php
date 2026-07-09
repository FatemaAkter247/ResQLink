<?php
session_start();
require_once __DIR__ . "/../../DB/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

if ((int)$_SESSION['role_id'] !== 2) {
    die("Access denied");
}

// Fetch statistics
$total_users = $conn->query("SELECT COUNT(*) AS total FROM users")->fetch_assoc()['total'];
$total_resources = $conn->query("SELECT COUNT(*) AS total FROM emergency_resources")->fetch_assoc()['total'];
$total_shelters = $conn->query("SELECT COUNT(*) AS total FROM shelters")->fetch_assoc()['total'];
$active_alerts = $conn->query("SELECT COUNT(*) AS total FROM disaster_alerts WHERE status='published'")->fetch_assoc()['total'];
$total_teams = $conn->query("SELECT COUNT(*) AS total FROM rescue_teams")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Dashboard Monitoring</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body style="background:#f4f4f4;">
<div class="container mt-5">
    <h2 class="text-danger mb-4">Dashboard Monitoring</h2>

    <div class="row g-3">
        <div class="col-md-4">
            <div class="card p-3 text-center bg-light">
                <h5>Total Users</h5>
                <h3><?php echo $total_users; ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-3 text-center bg-light">
                <h5>Total Resources</h5>
                <h3><?php echo $total_resources; ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-3 text-center bg-light">
                <h5>Total Shelters</h5>
                <h3><?php echo $total_shelters; ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-3 text-center bg-light">
                <h5>Active Alerts</h5>
                <h3><?php echo $active_alerts; ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-3 text-center bg-light">
                <h5>Rescue Teams</h5>
                <h3><?php echo $total_teams; ?></h3>
            </div>
        </div>
    </div>

    <a href="../dashboard.php" class="btn btn-secondary mt-4">Back</a>
</div>
</body>
</html>