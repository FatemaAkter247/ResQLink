<?php
session_start();
require_once __DIR__ . "/../../DB/db.php";

// AUTH CHECK
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

if ((int)$_SESSION['role_id'] !== 2) {
    die("Access denied");
}

$msg = "";

/* =========================
   UPDATE STATUS
========================= */
if (isset($_GET['update_status'])) {
    $id = (int)$_GET['id'];
    $status = $_GET['status'];

    $stmt = $conn->prepare("UPDATE rescue_teams SET status=? WHERE id=?");
    $stmt->bind_param("si", $status, $id);
    $stmt->execute();
    $stmt->close();

    header("Location: rescue_team.php");
    exit;
}

/* =========================
   DELETE TEAM
========================= */
if (isset($_GET['delete'])) {
    $id = (int)$_GET['id'];

    $conn->query("DELETE FROM rescue_teams WHERE id=$id");

    header("Location: rescue_team.php");
    exit;
}

/* =========================
   INSERT TEAM
========================= */
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

/* =========================
   SEARCH + FETCH
========================= */
$search = $_GET['search'] ?? '';

if ($search != '') {
    $stmt = $conn->prepare("SELECT * FROM rescue_teams 
        WHERE team_name LIKE ? OR assigned_area LIKE ?
        ORDER BY id DESC");

    $like = "%$search%";
    $stmt->bind_param("ss", $like, $like);
    $stmt->execute();
    $teams = $stmt->get_result();
} else {
    $teams = $conn->query("SELECT * FROM rescue_teams ORDER BY id DESC");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Rescue Team Coordination</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body style="background:#f4f4f4;">

<div class="container mt-5">

    <h2 class="text-danger">🚑 Rescue Team Coordination</h2>

    <?php if ($msg): ?>
        <div class="alert alert-info"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>

    <!-- SEARCH -->
    <form method="GET" class="mb-3">
        <input type="text" name="search" class="form-control" placeholder="Search by team or area">
    </form>

    <!-- ADD TEAM FORM -->
    <form method="POST" class="mb-4">
        <input name="team_name" class="form-control mb-2" placeholder="Team Name" required>
        <input name="members" class="form-control mb-2" placeholder="Team Members (comma separated)" required>
        <input name="assigned_area" class="form-control mb-2" placeholder="Assigned Area" required>

        <select name="status" class="form-control mb-2">
            <option value="available">Available</option>
            <option value="on_mission">On Mission</option>
            <option value="unavailable">Unavailable</option>
        </select>

        <button class="btn btn-danger">Assign Team</button>
    </form>

    <!-- TEAM LIST -->
    <h5>All Rescue Teams</h5>

    <?php while ($team = $teams->fetch_assoc()): ?>
        <div class="card p-3 mb-3 shadow-sm">

            <h5><?php echo htmlspecialchars($team['team_name']); ?></h5>

            <p><b>Status:</b> <?php echo htmlspecialchars($team['status']); ?></p>
            <p><b>Members:</b> <?php echo htmlspecialchars($team['members']); ?></p>
            <p><b>Area:</b> <?php echo htmlspecialchars($team['assigned_area']); ?></p>

            <!-- ACTION BUTTONS -->
            <a href="?update_status=1&id=<?php echo $team['id']; ?>&status=available" 
               class="btn btn-success btn-sm">Available</a>

            <a href="?update_status=1&id=<?php echo $team['id']; ?>&status=on_mission" 
               class="btn btn-warning btn-sm">On Mission</a>

            <a href="?update_status=1&id=<?php echo $team['id']; ?>&status=unavailable" 
               class="btn btn-secondary btn-sm">Unavailable</a>

            <a href="?delete=1&id=<?php echo $team['id']; ?>" 
               class="btn btn-danger btn-sm"
               onclick="return confirm('Delete this team?')">
               Delete
            </a>

        </div>
    <?php endwhile; ?>

    <a href="../dashboard.php" class="btn btn-dark mt-3">⬅ Back to Dashboard</a>

</div>

</body>
</html>
