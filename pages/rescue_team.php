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

$msg = "";

// Assign Rescue Team
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $team_name = trim($_POST['team_name'] ?? '');
    $members = trim($_POST['members'] ?? '');
    $assigned_area = trim($_POST['assigned_area'] ?? '');
    $status = trim($_POST['status'] ?? 'available');
    $created_by = (int)$_SESSION['user_id'];

    if ($team_name === '' || $members === '' || $assigned_area === '') {
        $msg = "Please fill all fields.";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO rescue_teams
            (team_name, members, assigned_area, status, created_by)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssssi", $team_name, $members, $assigned_area, $status, $created_by);

        if ($stmt->execute()) {
            $msg = "Rescue team assigned successfully!";
        } else {
            $msg = "Error: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Fetch all rescue teams
$teams = $conn->query("SELECT * FROM rescue_teams ORDER BY id DESC");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Rescue Team Coordination</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body style="background:#f4f4f4;">
<div class="container mt-5">
    <h2 class="text-danger">Rescue Team Coordination</h2>

    <?php if ($msg): ?>
        <div class="alert alert-info"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>

    <form method="POST" class="mb-4">
        <input name="team_name" class="form-control mb-2" placeholder="Team Name" required>
        <input name="members" class="form-control mb-2" placeholder="Team Members (comma separated)" required>
        <input name="assigned_area" class="form-control mb-2" placeholder="Assigned Area" required>

        <select name="status" class="form-control mb-2">
            <option value="available" selected>Available</option>
            <option value="on_mission">On Mission</option>
            <option value="unavailable">Unavailable</option>
        </select>

        <button class="btn btn-danger">Assign Team</button>
    </form>

    <h5>All Rescue Teams</h5>
    <?php while ($team = $teams->fetch_assoc()): ?>
        <div class="card p-2 mb-2">
            <b><?php echo htmlspecialchars($team['team_name']); ?></b> (<?php echo htmlspecialchars($team['status']); ?>)
            <br>Members: <?php echo htmlspecialchars($team['members']); ?>
            <br>Area: <?php echo htmlspecialchars($team['assigned_area']); ?>
        </div>
    <?php endwhile; ?>

    <a href="../dashboard.php" class="btn btn-secondary mt-3">Back</a>
</div>
</body>
</html>

