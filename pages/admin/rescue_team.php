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

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function missionBadgeClass($status)
{
    switch ($status) {
        case 'completed':
            return 'bg-success';
        case 'in_progress':
            return 'bg-primary';
        case 'en_route':
            return 'bg-info text-dark';
        case 'assigned':
            return 'bg-warning text-dark';
        case 'failed':
            return 'bg-danger';
        default:
            return 'bg-secondary';
    }
}

function priorityBadgeClass($priority)
{
    switch ($priority) {
        case 'critical':
            return 'bg-danger';
        case 'high':
            return 'bg-warning text-dark';
        case 'medium':
            return 'bg-info text-dark';
        default:
            return 'bg-secondary';
    }
}

/* =========================
   ASSIGN A PENDING REQUEST TO A RESCUE TEAM MEMBER
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_request'])) {
    $request_id = (int)($_POST['request_id'] ?? 0);
    $team_user_id = (int)($_POST['team_user_id'] ?? 0);

    if ($request_id <= 0 || $team_user_id <= 0) {
        $msg = "Please choose a rescue team member to assign.";
    } else {
        // Make sure a rescue_team user was actually selected
        $check = $conn->prepare("SELECT id FROM users WHERE id = ? AND role_id = 3 AND is_active = 1");
        $check->bind_param("i", $team_user_id);
        $check->execute();
        $valid_member = $check->get_result()->num_rows === 1;
        $check->close();

        if (!$valid_member) {
            $msg = "Invalid rescue team member.";
        } else {
            // Guard against double-assigning a request that's no longer pending
            $update = $conn->prepare("UPDATE emergency_requests SET status = 'assigned' WHERE id = ? AND status = 'pending'");
            $update->bind_param("i", $request_id);
            $update->execute();

            if ($update->affected_rows === 1) {
                $insert = $conn->prepare("
                    INSERT INTO rescue_missions (request_id, team_user_id, mission_status)
                    VALUES (?, ?, 'assigned')
                ");
                $insert->bind_param("ii", $request_id, $team_user_id);
                $insert->execute();
                $insert->close();
                $msg = "Request assigned successfully!";
            } else {
                $msg = "That request is no longer pending (already assigned or resolved).";
            }
            $update->close();
        }
    }
}

/* =========================
   ADMIN UPDATES A MISSION'S STATUS
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_mission'])) {
    $mission_id = (int)($_POST['mission_id'] ?? 0);
    $new_status = trim($_POST['mission_status'] ?? '');
    $allowed = ['assigned', 'en_route', 'in_progress', 'completed', 'failed'];

    if ($mission_id > 0 && in_array($new_status, $allowed, true)) {
        $stmt = $conn->prepare("
            UPDATE rescue_missions
            SET mission_status = ?,
                started_at = IF(? = 'en_route' AND started_at IS NULL, NOW(), started_at),
                completed_at = IF(? IN ('completed','failed'), NOW(), completed_at)
            WHERE id = ?
        ");
        $stmt->bind_param("sssi", $new_status, $new_status, $new_status, $mission_id);
        $stmt->execute();
        $stmt->close();

        // Get the linked request so we can reflect the outcome there too
        $find = $conn->prepare("SELECT request_id FROM rescue_missions WHERE id = ?");
        $find->bind_param("i", $mission_id);
        $find->execute();
        $row = $find->get_result()->fetch_assoc();
        $find->close();

        if ($row) {
            if ($new_status === 'completed') {
                $req = $conn->prepare("UPDATE emergency_requests SET status = 'resolved' WHERE id = ?");
                $req->bind_param("i", $row['request_id']);
                $req->execute();
                $req->close();
            } elseif ($new_status === 'failed') {
                $req = $conn->prepare("UPDATE emergency_requests SET status = 'pending' WHERE id = ?");
                $req->bind_param("i", $row['request_id']);
                $req->execute();
                $req->close();
            }
        }

        $msg = "Mission updated.";
    } else {
        $msg = "Invalid mission update.";
    }
}

/* =========================
   FETCH: PENDING REQUESTS (unassigned pool)
========================= */
$pending_requests = [];
$result = $conn->query("SELECT * FROM emergency_requests WHERE status = 'pending' ORDER BY FIELD(priority,'critical','high','medium','low'), created_at ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $pending_requests[] = $row;
    }
}

/* =========================
   FETCH: RESCUE TEAM ROSTER (with open mission count)
========================= */
$roster = [];
$result = $conn->query("
    SELECT u.id, u.full_name, u.phone, u.email,
           (SELECT COUNT(*) FROM rescue_missions rm
            WHERE rm.team_user_id = u.id AND rm.mission_status NOT IN ('completed','failed')) AS open_missions
    FROM users u
    WHERE u.role_id = 3 AND u.is_active = 1
    ORDER BY u.full_name ASC
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $roster[] = $row;
    }
}

/* =========================
   FETCH: ALL MISSIONS (with optional search)
========================= */
$search = trim($_GET['search'] ?? '');
$missions = [];

$sql = "
    SELECT rm.id, rm.mission_status, rm.started_at, rm.completed_at, rm.created_at,
           er.request_type, er.description, er.address, er.priority,
           u.full_name AS team_member
    FROM rescue_missions rm
    JOIN emergency_requests er ON rm.request_id = er.id
    JOIN users u ON rm.team_user_id = u.id
";

if ($search !== '') {
    $sql .= " WHERE u.full_name LIKE ? OR er.request_type LIKE ? ORDER BY rm.created_at DESC";
    $stmt = $conn->prepare($sql);
    $like = "%$search%";
    $stmt->bind_param("ss", $like, $like);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $sql .= " ORDER BY rm.created_at DESC";
    $result = $conn->query($sql);
}

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $missions[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rescue Team Coordination</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body style="background:#f4f4f4;">
<div class="container mt-5 mb-5">

    <h2 class="mb-4 text-danger">🚑 Rescue Team Coordination</h2>

    <?php if ($msg): ?>
        <div class="alert alert-info"><?= e($msg) ?></div>
    <?php endif; ?>

    <!-- PENDING REQUESTS: ASSIGN TO A TEAM MEMBER -->
    <div class="card mb-4">
        <div class="card-header bg-danger text-white">
            Pending Emergency Requests (<?= count($pending_requests) ?>)
        </div>
        <div class="card-body">
            <?php if (empty($pending_requests)): ?>
                <div class="alert alert-warning mb-0">No pending emergency requests right now.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Type</th>
                                <th>Description</th>
                                <th>Address</th>
                                <th>Priority</th>
                                <th>Requested</th>
                                <th style="min-width:260px;">Assign To</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($pending_requests as $req): ?>
                            <tr>
                                <td><?= e(ucfirst($req['request_type'])) ?></td>
                                <td><?= e($req['description']) ?></td>
                                <td><?= e($req['address']) ?></td>
                                <td><span class="badge <?= priorityBadgeClass($req['priority']) ?>"><?= e(ucfirst($req['priority'])) ?></span></td>
                                <td><?= e(date('M j, H:i', strtotime($req['created_at']))) ?></td>
                                <td>
                                    <?php if (empty($roster)): ?>
                                        <span class="text-muted small">No active rescue team members</span>
                                    <?php else: ?>
                                        <form method="POST" class="d-flex gap-2">
                                            <input type="hidden" name="assign_request" value="1">
                                            <input type="hidden" name="request_id" value="<?= (int)$req['id'] ?>">
                                            <select name="team_user_id" class="form-select form-select-sm" required>
                                                <option value="">-- Select member --</option>
                                                <?php foreach ($roster as $member): ?>
                                                    <option value="<?= (int)$member['id'] ?>">
                                                        <?= e($member['full_name']) ?> (<?= (int)$member['open_missions'] ?> active)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button class="btn btn-sm btn-danger">Assign</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- RESCUE TEAM ROSTER -->
    <div class="card mb-4">
        <div class="card-header bg-dark text-white">Rescue Team Roster</div>
        <div class="card-body">
            <?php if (empty($roster)): ?>
                <div class="alert alert-warning mb-0">No rescue team members registered yet.</div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($roster as $member): ?>
                        <div class="col-md-4">
                            <div class="border rounded p-3 h-100">
                                <b><?= e($member['full_name']) ?></b>
                                <div class="small text-muted"><?= e($member['phone']) ?></div>
                                <div class="small text-muted"><?= e($member['email'] ?? '') ?></div>
                                <span class="badge bg-primary mt-2">
                                    <?= (int)$member['open_missions'] ?> active mission<?= $member['open_missions'] == 1 ? '' : 's' ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ALL MISSIONS -->
    <div class="card">
        <div class="card-header bg-secondary text-white">All Rescue Missions</div>
        <div class="card-body">
            <form method="GET" class="mb-3">
                <input type="text" name="search" class="form-control" placeholder="Search by team member or request type" value="<?= e($search) ?>">
            </form>

            <?php if (empty($missions)): ?>
                <div class="alert alert-warning mb-0">No missions found.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Team Member</th>
                                <th>Request</th>
                                <th>Priority</th>
                                <th>Address</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th style="min-width:220px;">Update</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($missions as $mission): ?>
                            <tr>
                                <td><?= e($mission['team_member']) ?></td>
                                <td><?= e(ucfirst($mission['request_type'])) ?><br><span class="small text-muted"><?= e($mission['description']) ?></span></td>
                                <td><span class="badge <?= priorityBadgeClass($mission['priority']) ?>"><?= e(ucfirst($mission['priority'])) ?></span></td>
                                <td><?= e($mission['address']) ?></td>
                                <td><span class="badge <?= missionBadgeClass($mission['mission_status']) ?>"><?= e(ucwords(str_replace('_', ' ', $mission['mission_status']))) ?></span></td>
                                <td><?= e(date('M j, H:i', strtotime($mission['created_at']))) ?></td>
                                <td>
                                    <form method="POST" class="d-flex gap-2">
                                        <input type="hidden" name="update_mission" value="1">
                                        <input type="hidden" name="mission_id" value="<?= (int)$mission['id'] ?>">
                                        <select name="mission_status" class="form-select form-select-sm">
                                            <?php foreach (['assigned', 'en_route', 'in_progress', 'completed', 'failed'] as $st): ?>
                                                <option value="<?= $st ?>" <?= $mission['mission_status'] === $st ? 'selected' : '' ?>>
                                                    <?= e(ucwords(str_replace('_', ' ', $st))) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button class="btn btn-sm btn-outline-dark">Save</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <a href="../dashboard.php" class="btn btn-dark mt-3">⬅ Back to Dashboard</a>

</div>
</body>
</html>
