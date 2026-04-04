<?php
session_start();
require_once __DIR__ . "/../DB/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$result = $conn->query("SELECT * FROM emergency_resources ORDER BY id DESC");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Emergency Resources</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body style="background:#f4f4f4;">

<div class="container mt-5">

    <h2 class="text-danger">Emergency Resources</h2>

    <?php if ($result && $result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
            <div class="card mb-3 p-3">
                <h5><?php echo $row['resource_name']; ?></h5>
                <p>Category: <?php echo $row['category']; ?></p>
                <p>Quantity: <?php echo $row['quantity']; ?></p>
                <p>Location: <?php echo $row['location']; ?></p>
                <p>Status: <?php echo $row['status']; ?></p>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="alert alert-warning">No resources found</div>
    <?php endif; ?>

    <a href="dashboard.php" class="btn btn-secondary">Back</a>

</div>

</body>
</html>