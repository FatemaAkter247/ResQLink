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

$success = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = trim($_POST['alert_type'] ?? '');
    $location = trim($_POST['location_text'] ?? '');
    $severity = trim($_POST['severity'] ?? 'medium');
    $instructions = trim($_POST['instructions'] ?? '');
    $created_by = (int) $_SESSION['user_id'];

    if ($type === '' || $location === '' || $instructions === '') {
        $error = "Please fill in all fields.";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO disaster_alerts
            (created_by, alert_type, location_text, severity, instructions, status, published_at)
            VALUES (?, ?, ?, ?, ?, 'published', NOW())
        ");

        $stmt->bind_param("issss", $created_by, $type, $location, $severity, $instructions);

        if ($stmt->execute()) {
            $alert_id = $stmt->insert_id;

            $users = $conn->query("SELECT id FROM users");
            while ($u = $users->fetch_assoc()) {
                $uid = (int) $u['id'];

                $n = $conn->prepare("
                    INSERT INTO alert_notifications (alert_id, user_id)
                    VALUES (?, ?)
                ");
                $n->bind_param("ii", $alert_id, $uid);
                $n->execute();
                $n->close();
            }

            $success = "Alert published successfully!";
        } else {
            $error = "Failed to publish alert.";
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Create Alert - ResQLink</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: linear-gradient(135deg, #dc3545 0%, #bb2d3b 100%);
            min-height: 100vh;
        }

        .page-card {
            width: 100%;
            max-width: 700px;
            margin: 60px auto;
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.18);
            padding: 2rem;
        }

        .page-card h2 {
            color: #dc3545;
            font-weight: 700;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .form-control:focus,
        .form-select:focus,
        textarea:focus {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }

        .btn-publish {
            background-color: #dc3545;
            border-color: #dc3545;
            font-weight: 600;
        }

        .btn-publish:hover {
            background-color: #bb2d3b;
            border-color: #bb2d3b;
        }
    </style>
</head>

<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="../../index.php">
            <span class="badge bg-danger">ResQLink</span>
        </a>
    </div>
</nav>

<div class="container">
    <div class="page-card">
        <h2>Create Disaster Alert</h2>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Alert Type</label>
                <input type="text" name="alert_type" class="form-control" placeholder="Flood, Fire, Cyclone..." required>
            </div>

            <div class="mb-3">
                <label class="form-label">Location</label>
                <input type="text" name="location_text" class="form-control" placeholder="Enter affected location" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Severity</label>
                <select name="severity" class="form-select" required>
                    <option value="low">Low</option>
                    <option value="medium" selected>Medium</option>
                    <option value="high">High</option>
                    <option value="critical">Critical</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Instructions</label>
                <textarea name="instructions" class="form-control" rows="5" placeholder="Write alert instructions..." required></textarea>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-publish btn-danger">Publish Alert</button>
                <a href="../dashboard.php" class="btn btn-secondary">Back</a>
            </div>
        </form>
    </div>
</div>

</body>
</html>