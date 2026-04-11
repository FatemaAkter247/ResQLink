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

function getDefaultImage($type){
    $type = strtolower(trim($type));

    if (strpos($type, 'fire') !== false) return "fire.jpg";
    if (strpos($type, 'flood') !== false) return "flood.jpg";
    if (strpos($type, 'earthquake') !== false) return "earthquake.jpg";
    if (strpos($type, 'cyclone') !== false) return "cyclone.jpg";
    if (strpos($type, 'storm') !== false) return "cyclone.jpg";

    return "default.jpg";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $type = trim($_POST['alert_type'] ?? '');
    $location = trim($_POST['location_text'] ?? '');
    $severity = trim($_POST['severity'] ?? '');
    $instructions = trim($_POST['instructions'] ?? '');
    $created_by = (int)$_SESSION['user_id'];

    if ($type === '' || $location === '' || $instructions === '') {
        $error = "Please fill in all fields.";
    } else {
        $image_name = getDefaultImage($type);

        $stmt = $conn->prepare("
            INSERT INTO disaster_alerts 
            (created_by, alert_type, location_text, severity, instructions, image, status, published_at)
            VALUES (?, ?, ?, ?, ?, ?, 'published', NOW())
        ");

        $stmt->bind_param("isssss", $created_by, $type, $location, $severity, $instructions, $image_name);

        if ($stmt->execute()) {
            $alert_id = $stmt->insert_id;

            $users = $conn->query("SELECT id FROM users");
            while ($u = $users->fetch_assoc()) {
                $uid = (int)$u['id'];

                $n = $conn->prepare("
                    INSERT INTO alert_notifications (alert_id, user_id)
                    VALUES (?, ?)
                ");
                $n->bind_param("ii", $alert_id, $uid);
                $n->execute();
                $n->close();
            }

            $success = "Alert created successfully!";
        } else {
            $error = "Database error!";
        }

        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Create Alert</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body style="background:#dc3545;">

<div class="container mt-5 bg-white p-4 rounded">

    <h2 class="text-danger">Create Disaster Alert</h2>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST">

        <label class="form-label">Alert Type</label>
        <select name="alert_type" class="form-control mb-2" required>
            <option value="">Select Alert Type</option>
            <option value="fire">Fire</option>
            <option value="flood">Flood</option>
            <option value="earthquake">Earthquake</option>
            <option value="cyclone">Cyclone</option>
            <option value="storm">Storm</option>
            <option value="other">Other</option>
        </select>

        <label class="form-label">Location</label>
        <input name="location_text" class="form-control mb-2" placeholder="Location" required>

        <label class="form-label">Severity</label>
        <select name="severity" class="form-control mb-2">
            <option value="low">low</option>
            <option value="medium">medium</option>
            <option value="high">high</option>
            <option value="critical">critical</option>
        </select>

        <label class="form-label">Instructions</label>
        <textarea name="instructions" class="form-control mb-2" placeholder="Instructions" required></textarea>

        <button class="btn btn-danger">Create Alert</button>

    </form>

    <a href="../dashboard.php" class="btn btn-secondary mt-3">Back</a>

</div>

</body>
</html>