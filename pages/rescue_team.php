<?php
session_start();
require_once __DIR__ . "/../DB/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ((int)$_SESSION['role_id'] !== 3) {
    die("Access denied");
}

$user_id = (int)$_SESSION['user_id'];
$username = htmlspecialchars($_SESSION['full_name'] ?? 'User', ENT_QUOTES, 'UTF-8');
$msg = "";

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function missionColor($status)
{
    switch ($status) {
        case 'completed':
            return '#15803d';
        case 'in_progress':
            return '#2563eb';
        case 'en_route':
            return '#0891b2';
        case 'assigned':
            return '#ca8a04';
        case 'failed':
            return '#b91c1c';
        default:
            return '#4b5563';
    }
}

function priorityColor($priority)
{
    switch ($priority) {
        case 'critical':
            return '#b91c1c';
        case 'high':
            return '#ea580c';
        case 'medium':
            return '#ca8a04';
        default:
            return '#15803d';
    }
}

/* =========================
   ADVANCE / UPDATE MY OWN MISSION STATUS
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_mission'])) {
    $mission_id = (int)($_POST['mission_id'] ?? 0);
    $new_status = trim($_POST['mission_status'] ?? '');
    $allowed = ['en_route', 'in_progress', 'completed', 'failed'];

    if ($mission_id > 0 && in_array($new_status, $allowed, true)) {
        // Ownership check: only allow updating a mission that belongs to this rescue team member
        $stmt = $conn->prepare("
            UPDATE rescue_missions
            SET mission_status = ?,
                started_at = IF(? = 'en_route' AND started_at IS NULL, NOW(), started_at),
                completed_at = IF(? IN ('completed','failed'), NOW(), completed_at)
            WHERE id = ? AND team_user_id = ?
        ");
        $stmt->bind_param("sssii", $new_status, $new_status, $new_status, $mission_id, $user_id);
        $stmt->execute();
        $updated = $stmt->affected_rows === 1;
        $stmt->close();

        if ($updated) {
            $find = $conn->prepare("SELECT request_id FROM rescue_missions WHERE id = ? AND team_user_id = ?");
            $find->bind_param("ii", $mission_id, $user_id);
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
            $msg = "That mission could not be updated.";
        }
    } else {
        $msg = "Invalid mission update.";
    }
}

/* =========================
   SELF-CLAIM A PENDING EMERGENCY REQUEST
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['claim_request'])) {
    $request_id = (int)($_POST['request_id'] ?? 0);

    if ($request_id > 0) {
        $update = $conn->prepare("UPDATE emergency_requests SET status = 'assigned' WHERE id = ? AND status = 'pending'");
        $update->bind_param("i", $request_id);
        $update->execute();

        if ($update->affected_rows === 1) {
            $insert = $conn->prepare("
                INSERT INTO rescue_missions (request_id, team_user_id, mission_status)
                VALUES (?, ?, 'assigned')
            ");
            $insert->bind_param("ii", $request_id, $user_id);
            $insert->execute();
            $insert->close();
            $msg = "Mission claimed! Check 'My Missions' below.";
        } else {
            $msg = "Sorry, someone else already claimed that request.";
        }
        $update->close();
    }
}

$view = ($_GET['view'] ?? 'missions') === 'requests' ? 'requests' : 'missions';

/* =========================
   FETCH: MY MISSIONS
========================= */
$my_missions = [];
$stmt = $conn->prepare("
    SELECT rm.id, rm.mission_status, rm.created_at,
           er.request_type, er.description, er.address, er.priority
    FROM rescue_missions rm
    JOIN emergency_requests er ON rm.request_id = er.id
    WHERE rm.team_user_id = ?
    ORDER BY rm.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $my_missions[] = $row;
}
$stmt->close();

$open_missions_count = 0;
foreach ($my_missions as $m) {
    if (!in_array($m['mission_status'], ['completed', 'failed'], true)) {
        $open_missions_count++;
    }
}

/* =========================
   FETCH: PENDING (UNCLAIMED) EMERGENCY REQUESTS
========================= */
$pending_requests = [];
$result = $conn->query("
    SELECT * FROM emergency_requests
    WHERE status = 'pending'
    ORDER BY FIELD(priority,'critical','high','medium','low'), created_at ASC
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $pending_requests[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rescue Team | ResQLink</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
:root {
    --accent: #1565c0;
    --accent-dark: #0d47a1;
    --accent-light: #e3f2fd;
    --sidebar-width: 265px;
    --bg: #f0f2f5;
    --white: #ffffff;
    --text: #1a1a2e;
    --muted: #6b7280;
    --border: #e5e7eb;
    --shadow: 0 4px 16px rgba(0,0,0,.10);
    --radius: 14px;
}
body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
a { color: inherit; }
.topbar {
    background: var(--white); border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
    padding: 18px 28px;
}
.topbar-title h1 { font-size: 20px; font-weight: 800; }
.topbar-title p { font-size: 13px; color: var(--muted); margin-top: 2px; }
.back-link {
    display: inline-flex; align-items: center; gap: 8px; text-decoration: none;
    color: var(--accent); font-weight: 700; font-size: 13px;
}
.content { padding: 28px; max-width: 1100px; margin: 0 auto; }
.tabs { display: flex; gap: 10px; margin-bottom: 20px; }
.tab {
    padding: 10px 20px; border-radius: 50px; font-weight: 700; font-size: 13px;
    text-decoration: none; color: var(--muted); background: var(--white); border: 1px solid var(--border);
}
.tab.active { background: var(--accent); color: #fff; border-color: var(--accent); }
.tab .badge {
    background: rgba(255,255,255,.3); border-radius: 20px; padding: 1px 8px; margin-left: 6px; font-size: 11px;
}
.tab:not(.active) .badge { background: var(--accent-light); color: var(--accent); }
.alert-box {
    background: var(--accent-light); color: var(--accent-dark); border-radius: 10px;
    padding: 12px 18px; margin-bottom: 18px; font-size: 14px; font-weight: 600;
}
.stats-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(185px, 1fr)); gap: 14px; margin-bottom: 24px; }
.stat-card {
    background: var(--white); border: 1px solid var(--border); border-radius: var(--radius);
    padding: 20px; box-shadow: 0 1px 4px rgba(0,0,0,.06); display: flex; align-items: center; gap: 15px;
}
.stat-icon { width: 50px; height: 50px; border-radius: 13px; display: grid; place-items: center; font-size: 20px; }
.stat-info p { color: var(--muted); font-size: 12px; font-weight: 700; margin-bottom: 4px; }
.stat-info h3 { font-size: 26px; line-height: 1; font-weight: 800; }
.card-list { display: flex; flex-direction: column; gap: 14px; }
.mission-card {
    background: var(--white); border: 1px solid var(--border); border-radius: var(--radius);
    padding: 18px 22px; box-shadow: 0 1px 4px rgba(0,0,0,.06);
    display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;
}
.mission-info h3 { font-size: 15px; font-weight: 800; margin-bottom: 6px; }
.mission-info p { font-size: 13px; color: var(--muted); margin-bottom: 3px; }
.pill { display: inline-block; padding: 3px 12px; border-radius: 20px; color: #fff; font-size: 11px; font-weight: 800; }
.mission-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.mission-actions form { display: flex; gap: 8px; }
select, .btn-action {
    border: 1px solid var(--border); border-radius: 8px; padding: 8px 12px; font-size: 13px; font-weight: 700;
    font-family: inherit; background: var(--white); color: var(--text);
}
.btn-action { background: var(--accent); color: #fff; border: none; cursor: pointer; }
.btn-action:hover { background: var(--accent-dark); }
.empty-state { text-align: center; padding: 50px 20px; color: var(--muted); }
.empty-state i { font-size: 34px; margin-bottom: 12px; display: block; }
</style>
</head>
<body>

<div class="topbar">
    <div class="topbar-title">
        <h1><i class="fa-solid fa-person-rifle"></i> Rescue Team Operations</h1>
        <p>Welcome, <?= $username ?> &middot; <?= date('l, F j, Y') ?></p>
    </div>
    <a href="dashboard.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>
</div>

<div class="content">

    <?php if ($msg): ?>
        <div class="alert-box"><?= e($msg) ?></div>
    <?php endif; ?>

    <section class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background:#e3f2fd; color:#1565c0;"><i class="fa-solid fa-person-rifle"></i></div>
            <div class="stat-info">
                <p>Active Missions</p>
                <h3><?= $open_missions_count ?></h3>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#fff8e1; color:#ca8a04;"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <div class="stat-info">
                <p>Unclaimed Requests</p>
                <h3><?= count($pending_requests) ?></h3>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#e8f5e9; color:#15803d;"><i class="fa-solid fa-list-check"></i></div>
            <div class="stat-info">
                <p>Total Missions</p>
                <h3><?= count($my_missions) ?></h3>
            </div>
        </div>
    </section>

    <div class="tabs">
        <a href="rescue_team.php?view=missions" class="tab <?= $view === 'missions' ? 'active' : '' ?>">
            <i class="fa-solid fa-person-rifle"></i> My Missions
            <span class="badge"><?= $open_missions_count ?></span>
        </a>
        <a href="rescue_team.php?view=requests" class="tab <?= $view === 'requests' ? 'active' : '' ?>">
            <i class="fa-solid fa-triangle-exclamation"></i> Emergency Requests
            <span class="badge"><?= count($pending_requests) ?></span>
        </a>
    </div>

    <?php if ($view === 'missions'): ?>

        <?php if (empty($my_missions)): ?>
            <div class="empty-state">
                <i class="fa-solid fa-inbox"></i>
                No missions assigned yet. Check the "Emergency Requests" tab to claim one.
            </div>
        <?php else: ?>
            <div class="card-list">
                <?php foreach ($my_missions as $mission): ?>
                    <div class="mission-card">
                        <div class="mission-info">
                            <h3><?= e(ucfirst($mission['request_type'])) ?> request</h3>
                            <p><i class="fa-solid fa-location-dot"></i> <?= e($mission['address']) ?></p>
                            <p><?= e($mission['description']) ?></p>
                            <p>
                                <span class="pill" style="background:<?= priorityColor($mission['priority']) ?>"><?= e(ucfirst($mission['priority'])) ?> priority</span>
                                <span class="pill" style="background:<?= missionColor($mission['mission_status']) ?>"><?= e(ucwords(str_replace('_', ' ', $mission['mission_status']))) ?></span>
                            </p>
                        </div>
                        <?php if (!in_array($mission['mission_status'], ['completed', 'failed'], true)): ?>
                            <div class="mission-actions">
                                <form method="POST">
                                    <input type="hidden" name="update_mission" value="1">
                                    <input type="hidden" name="mission_id" value="<?= (int)$mission['id'] ?>">
                                    <select name="mission_status">
                                        <?php foreach (['en_route' => 'En Route', 'in_progress' => 'In Progress', 'completed' => 'Completed', 'failed' => 'Failed'] as $val => $label): ?>
                                            <option value="<?= $val ?>" <?= $mission['mission_status'] === $val ? 'selected' : '' ?>><?= $label ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="btn-action" type="submit">Update</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php else: ?>

        <?php if (empty($pending_requests)): ?>
            <div class="empty-state">
                <i class="fa-solid fa-circle-check"></i>
                No unclaimed emergency requests right now.
            </div>
        <?php else: ?>
            <div class="card-list">
                <?php foreach ($pending_requests as $req): ?>
                    <div class="mission-card">
                        <div class="mission-info">
                            <h3><?= e(ucfirst($req['request_type'])) ?> request</h3>
                            <p><i class="fa-solid fa-location-dot"></i> <?= e($req['address']) ?></p>
                            <p><?= e($req['description']) ?></p>
                            <p>
                                <span class="pill" style="background:<?= priorityColor($req['priority']) ?>"><?= e(ucfirst($req['priority'])) ?> priority</span>
                                <span class="pill" style="background:#6b7280;">Pending &middot; <?= e(date('M j, H:i', strtotime($req['created_at']))) ?></span>
                            </p>
                        </div>
                        <div class="mission-actions">
                            <form method="POST" onsubmit="return confirm('Claim this mission?');">
                                <input type="hidden" name="claim_request" value="1">
                                <input type="hidden" name="request_id" value="<?= (int)$req['id'] ?>">
                                <button class="btn-action" type="submit"><i class="fa-solid fa-hand"></i> Claim Mission</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php endif; ?>

</div>
</body>
</html>
