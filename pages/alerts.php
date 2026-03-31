<?php
session_start();
require_once __DIR__ . "/../DB/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];

if (isset($_GET['read'])) {
    $nid = (int) $_GET['read'];
    $conn->query("UPDATE alert_notifications SET is_read = 1 WHERE id = $nid AND user_id = $user_id");
    header("Location: alerts.php");
    exit;
}

$sql = "
SELECT an.id AS notif_id, an.is_read,
       da.alert_type, da.location_text, da.severity, da.instructions, da.created_at
FROM alert_notifications an
JOIN disaster_alerts da ON an.alert_id = da.id
WHERE an.user_id = $user_id
AND da.status = 'published'
ORDER BY da.created_at DESC
";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Alerts - ResQLink</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: linear-gradient(135deg, #dc3545 0%, #bb2d3b 100%);
            min-height: 100vh;
        }

        .container-box {
            max-width: 900px;
            margin: 60px auto;
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .alert-card {
            border-left: 5px solid #dc3545;
            padding: 15px;
            margin-bottom: 15px;
            background: #f9f9f9;
            border-radius: 8px;
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

<div class="container-box">

    <h2 class="text-danger mb-4">Disaster Alerts</h2>

    <?php if ($result && $result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
            <div class="alert-card">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                    <b><?php echo htmlspecialchars($row['alert_type']); ?></b>
                    <span class="badge bg-danger"><?php echo htmlspecialchars($row['severity']); ?></span>
                </div>

                <p><b>Location:</b> <?php echo htmlspecialchars($row['location_text']); ?></p>
                <p><?php echo nl2br(htmlspecialchars($row['instructions'])); ?></p>

                <small><?php echo htmlspecialchars($row['created_at']); ?></small><br>

                <?php if (!$row['is_read']): ?>
                    <a href="?read=<?php echo $row['notif_id']; ?>" class="btn btn-success btn-sm mt-2">
                        Mark as Read
                    </a>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="alert alert-info">No published alerts found.</div>
    <?php endif; ?>

    <a href="dashboard.php" class="btn btn-secondary mt-3">Back</a>

</div>

</body>
</html>