<?php
session_start();
require_once __DIR__ . "/../DB/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$success = "";
$error = "";

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function requestStatusClass($status)
{
    switch ($status) {
        case 'assigned':
            return 'st-assigned';
        case 'resolved':
            return 'st-resolved';
        case 'cancelled':
            return 'st-cancelled';
        default:
            return 'st-pending';
    }
}

function requestPriorityClass($priority)
{
    switch ($priority) {
        case 'critical':
            return 'pr-critical';
        case 'high':
            return 'pr-high';
        case 'medium':
            return 'pr-medium';
        default:
            return 'pr-low';
    }
}

$typeLabels = [
    'medical' => 'Medical / Injury / Illness',
    'rescue'  => 'Trapped / Need Rescue',
    'other'   => 'Other Campus Emergency',
];

// submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = trim($_POST['emergency_type'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $lifeThreatening = isset($_POST['life_threatening']);

    $allowedTypes = ['medical', 'rescue', 'other'];

    if (!in_array($type, $allowedTypes, true)) {
        $error = "Please select a valid emergency type.";
    } elseif ($address === '' || $description === '') {
        $error = "Please describe what happened and your exact location on campus.";
    } else {
        if ($lifeThreatening) {
            $priority = 'critical';
        } elseif ($type === 'other') {
            $priority = 'medium';
        } else {
            $priority = 'high';
        }

        $stmt = $conn->prepare("
            INSERT INTO emergency_requests (created_by, request_type, description, address, priority, status, created_at)
            VALUES (?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->bind_param("issss", $user_id, $type, $description, $address, $priority);

        if ($stmt->execute()) {
            $success = "Your emergency request has been sent. The nearest available rescue team will be notified immediately.";
        } else {
            $error = "Failed to send your request. If this is urgent, call 999 immediately.";
        }
        $stmt->close();
    }
}

// this user's recent requests
$myRequests = [];
$stmt = $conn->prepare("
    SELECT id, request_type, description, address, priority, status, created_at
    FROM emergency_requests
    WHERE created_by = ?
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $myRequests[] = $row;
}
$stmt->close();

// Session/user info for dashboard shell
$username_raw = $_SESSION['full_name'] ?? 'User';
$username = htmlspecialchars($username_raw, ENT_QUOTES, 'UTF-8');
$role_id = (int)($_SESSION['role_id'] ?? 0);

if ($role_id === 2) {
    $role = 'admin';
} elseif ($role_id === 5) {
    $role = 'system_admin';
} elseif ($role_id === 3) {
    $role = 'rescue_team';
} elseif ($role_id === 4) {
    $role = 'government';
} else {
    $role = 'citizen';
}

$role_labels = [
    'admin' => 'Administrator',
    'system_admin' => 'System Admin',
    'rescue_team' => 'Rescue Team',
    'government' => 'Government',
    'citizen' => 'Citizen'
];
$role_label = $role_labels[$role] ?? 'User';
$initials = strtoupper(substr($username_raw, 0, 1));

// Unread alerts count for sidebar badge
$unread_count = 0;
$uc = $conn->prepare("SELECT COUNT(*) AS c FROM alert_notifications WHERE user_id = ? AND is_read = 0");
$uc->bind_param("i", $user_id);
$uc->execute();
$ucRes = $uc->get_result();
if ($ucRes && $ucr = $ucRes->fetch_assoc()) {
    $unread_count = (int)$ucr['c'];
}
$uc->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Emergency - ResQLink</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --accent: #2e7d32;
            --accent-dark: #1b5e20;
            --accent-light: #e8f5e9;
            --danger: #dc2626;
            --danger-dark: #b91c1c;
            --sidebar-width: 265px;
            --bg: #f0f2f5;
            --white: #ffffff;
            --text: #1a1a2e;
            --muted: #6b7280;
            --border: #e5e7eb;
            --shadow: 0 4px 16px rgba(0, 0, 0, .10);
            --radius: 14px;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
        }

        a { color: inherit; }

        /* ---------- Sidebar ---------- */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--white);
            border-right: 1px solid var(--border);
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            z-index: 100;
            display: flex;
            flex-direction: column;
            transition: transform .25s ease;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 20px 22px;
            border-bottom: 1px solid var(--border);
            text-decoration: none;
        }

        .brand-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: var(--accent);
            color: #fff;
            display: grid;
            place-items: center;
        }

        .brand-name {
            font-size: 18px;
            font-weight: 800;
        }

        .brand-name span { color: var(--accent); }

        .sidebar-nav {
            flex: 1;
            padding: 16px 12px;
            overflow-y: auto;
        }

        .nav-label {
            display: block;
            padding: 8px 12px;
            color: var(--muted);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            margin-bottom: 3px;
            border-radius: 10px;
            color: var(--muted);
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
        }

        .nav-link:hover,
        .nav-link.active {
            background: var(--accent-light);
            color: var(--accent);
        }

        .nav-link i { width: 18px; text-align: center; }

        .nav-link .badge {
            margin-left: auto;
            background: var(--accent);
            color: #fff;
            font-size: 10px;
            padding: 2px 7px;
            border-radius: 20px;
        }

        .sidebar-footer {
            padding: 14px 12px;
            border-top: 1px solid var(--border);
        }

        .logout { color: #dc2626; }

        /* ---------- Main ---------- */
        .main {
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            min-height: 100vh;
        }

        .topbar {
            height: 66px;
            background: var(--white);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 28px;
            position: sticky;
            top: 0;
            z-index: 50;
        }

        .topbar-title h1 { font-size: 17px; font-weight: 800; }
        .topbar-title p { font-size: 12px; color: var(--muted); margin-top: 2px; }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .icon-btn {
            width: 38px;
            height: 38px;
            border: 1px solid var(--border);
            border-radius: 10px;
            display: grid;
            place-items: center;
            color: var(--muted);
            text-decoration: none;
            position: relative;
        }

        .icon-btn:hover {
            background: var(--accent-light);
            color: var(--accent);
            border-color: var(--accent);
        }

        .badge-dot {
            position: absolute;
            top: 7px;
            right: 7px;
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #dc2626;
            border: 1.5px solid #fff;
        }

        .user-chip {
            display: flex;
            align-items: center;
            gap: 9px;
            border: 1px solid var(--border);
            border-radius: 50px;
            padding: 5px 13px 5px 5px;
        }

        .avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--accent);
            color: #fff;
            display: grid;
            place-items: center;
            font-size: 13px;
            font-weight: 800;
        }

        .user-chip-name { font-size: 13px; font-weight: 800; }
        .user-chip-role { font-size: 11px; color: var(--muted); }

        .hamburger {
            display: none;
            background: none;
            border: none;
            color: var(--text);
            font-size: 20px;
            cursor: pointer;
        }

        .content { padding: 28px; }

        .page-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .page-head h2 {
            font-size: 20px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-head h2 .ph-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: var(--danger);
            color: #fff;
            display: grid;
            place-items: center;
            font-size: 16px;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 10px;
            background: var(--white);
            border: 1px solid var(--border);
            color: var(--text);
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
        }

        .back-btn:hover {
            background: var(--accent-light);
            color: var(--accent);
            border-color: var(--accent);
        }

        /* ---------- Call bar ---------- */
        .call-bar {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            margin-bottom: 20px;
        }

        .call-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 16px;
            border-radius: var(--radius);
            text-decoration: none;
            font-weight: 800;
            font-size: 15px;
            color: #fff;
            background: var(--danger);
            box-shadow: 0 1px 4px rgba(0, 0, 0, .08);
        }

        .call-btn i { font-size: 18px; }

        .call-btn:hover { background: var(--danger-dark); color: #fff; }

        .call-btn.secondary {
            background: #1f2937;
        }

        .call-btn.secondary:hover { background: #111827; }

        /* ---------- Layout grid ---------- */
        .sos-grid {
            display: grid;
            grid-template-columns: 1.1fr 1fr;
            gap: 20px;
            align-items: start;
        }

        .panel {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: 0 1px 4px rgba(0, 0, 0, .06);
            padding: 24px;
        }

        .panel-title {
            font-size: 16px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 6px;
        }

        .panel-title .pt-ico {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            background: #fef2f2;
            color: var(--danger);
            display: grid;
            place-items: center;
            font-size: 14px;
        }

        .panel-sub {
            color: var(--muted);
            font-size: 13px;
            margin-bottom: 20px;
        }

        /* ---------- Alerts ---------- */
        .flash {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 18px;
        }

        .flash-success {
            background: #e8f5e9;
            color: #15803d;
            border: 1px solid #a5d6a7;
        }

        .flash-error {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        /* ---------- Form ---------- */
        .form-label {
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 6px;
            display: block;
        }

        .form-select,
        .form-control {
            border-radius: 10px;
            border: 1px solid var(--border);
            padding: 11px 14px;
            font-size: 14px;
            font-family: inherit;
        }

        .form-select:focus,
        .form-control:focus {
            border-color: var(--danger);
            box-shadow: 0 0 0 0.2rem rgba(220, 38, 38, 0.15);
        }

        .mb-3 { margin-bottom: 18px; }

        .life-check {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 14px;
            border-radius: 10px;
            background: #fef2f2;
            border: 1px solid #fecaca;
        }

        .life-check input {
            width: 18px;
            height: 18px;
            accent-color: var(--danger);
        }

        .life-check label {
            font-size: 13px;
            font-weight: 700;
            color: var(--danger-dark);
        }

        .btn-sos {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 13px 22px;
            border-radius: 10px;
            background: var(--danger);
            color: #fff;
            font-size: 15px;
            font-weight: 800;
            border: none;
            cursor: pointer;
            transition: all .2s ease;
        }

        .btn-sos:hover {
            background: var(--danger-dark);
            transform: translateY(-2px);
        }

        /* ---------- Recent requests ---------- */
        .req-item {
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 14px;
            margin-bottom: 12px;
        }

        .req-item:last-child { margin-bottom: 0; }

        .req-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 8px;
        }

        .req-type { font-size: 14px; font-weight: 800; }

        .req-desc {
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 6px;
        }

        .req-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            font-size: 11px;
            color: var(--muted);
        }

        .pill {
            display: inline-flex;
            align-items: center;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .03em;
            color: #fff;
        }

        .st-pending { background: #ca8a04; }
        .st-assigned { background: #2563eb; }
        .st-resolved { background: #15803d; }
        .st-cancelled { background: #6b7280; }

        .pr-critical { background: #b91c1c; }
        .pr-high { background: #ea580c; }
        .pr-medium { background: #ca8a04; }
        .pr-low { background: #15803d; }

        .no-req {
            text-align: center;
            color: var(--muted);
            padding: 20px 0;
        }

        .no-req i {
            font-size: 36px;
            color: var(--danger);
            margin-bottom: 10px;
        }

        /* ---------- Sidebar overlay (mobile) ---------- */
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .45);
            z-index: 90;
        }

        .sidebar-overlay.open { display: block; }

        @media (max-width: 900px) {
            .sos-grid { grid-template-columns: 1fr; }
            .call-bar { grid-template-columns: 1fr; }
        }

        @media (max-width: 800px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main { margin-left: 0; width: 100%; }
            .hamburger { display: block; }
            .content { padding: 16px; }
            .topbar { padding: 0 16px; }
            .user-chip-role { display: none; }
        }
    </style>
</head>

<body>

<div class="sidebar-overlay" id="overlay" onclick="closeSidebar()"></div>

<aside class="sidebar" id="sidebar">
    <a href="dashboard.php" class="sidebar-brand">
        <div class="brand-icon">
            <i class="fa-solid fa-shield-halved"></i>
        </div>
        <span class="brand-name">ResQ<span>Link</span></span>
    </a>

    <nav class="sidebar-nav">
        <span class="nav-label">Main Menu</span>

        <a href="dashboard.php" class="nav-link">
            <i class="fa-solid fa-gauge-high"></i> Dashboard
        </a>

        <a href="chatbot.php" class="nav-link">
            <i class="fa-solid fa-robot"></i> AI Emergency Chatbot
        </a>

        <a href="report_emergency.php" class="nav-link active">
            <i class="fa-solid fa-truck-medical" style="color:#dc2626;"></i> Report Emergency
        </a>

        <span class="nav-label">Missing Students</span>

        <a href="missing_students.php" class="nav-link">
            <i class="fa-solid fa-user-magnifying-glass"></i> Missing Student Alerts
        </a>

        <a href="report_missing_student.php" class="nav-link">
            <i class="fa-solid fa-person-circle-question" style="color:#b45309;"></i> Report Missing Student
        </a>

        <span class="nav-label">Disaster Info</span>

        <a href="alerts.php" class="nav-link">
            <i class="fa-solid fa-bell"></i> Alerts
            <?php if ($unread_count > 0): ?>
                <span class="badge"><?php echo $unread_count; ?></span>
            <?php endif; ?>
        </a>

        <a href="shelters.php" class="nav-link">
            <i class="fa-solid fa-house-chimney"></i> Find Shelter
        </a>

        <a href="resources.php" class="nav-link">
            <i class="fa-solid fa-boxes-stacked"></i> Resources
        </a>

        <span class="nav-label">My Status</span>

        <a href="evacuation_status.php" class="nav-link">
            <i class="fa-solid fa-person-walking-arrow-right"></i> Evacuation Status
        </a>

        <a href="request_help.php" class="nav-link">
            <i class="fa-solid fa-hand-holding-heart"></i> Request Help
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="logout.php" class="nav-link logout">
            <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
        </a>
    </div>
</aside>

<div class="main">
    <header class="topbar">
        <div style="display:flex; align-items:center; gap:12px;">
            <button class="hamburger" onclick="openSidebar()">
                <i class="fa-solid fa-bars"></i>
            </button>

            <div class="topbar-title">
                <h1>Report Emergency</h1>
                <p><?php echo date('l, F j, Y'); ?></p>
            </div>
        </div>

        <div class="topbar-right">
            <a href="chatbot.php" class="icon-btn" title="AI Emergency Chatbot">
                <i class="fa-solid fa-robot"></i>
            </a>

            <a href="alerts.php" class="icon-btn" title="Alerts">
                <i class="fa-solid fa-bell"></i>
                <?php if ($unread_count > 0): ?>
                    <span class="badge-dot"></span>
                <?php endif; ?>
            </a>

            <div class="user-chip">
                <div class="avatar"><?php echo htmlspecialchars($initials); ?></div>
                <div>
                    <div class="user-chip-name"><?php echo $username; ?></div>
                    <div class="user-chip-role"><?php echo htmlspecialchars($role_label); ?></div>
                </div>
            </div>
        </div>
    </header>

    <main class="content">
        <div class="page-head">
            <h2>
                <span class="ph-icon"><i class="fa-solid fa-truck-medical"></i></span>
                Campus Emergency SOS
            </h2>
            <a href="dashboard.php" class="back-btn">
                <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <div class="call-bar">
            <a class="call-btn" href="tel:999">
                <i class="fa-solid fa-phone"></i> Call 999 (Emergency Hotline)
            </a>
            <a class="call-btn secondary" href="tel:+8800000000">
                <i class="fa-solid fa-phone-volume"></i> Call Campus Security (placeholder number)
            </a>
        </div>

        <?php if ($success): ?>
            <div class="flash flash-success">
                <i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="flash flash-error">
                <i class="fa-solid fa-circle-exclamation"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="sos-grid">
            <!-- LEFT: Report form -->
            <div class="panel">
                <div class="panel-title">
                    <span class="pt-ico"><i class="fa-solid fa-triangle-exclamation"></i></span>
                    Send an SOS Request
                </div>
                <p class="panel-sub">
                    For immediate life danger, call 999 first. This form also alerts the on-duty campus rescue team so help can be dispatched to your exact location.
                </p>

                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Emergency Type</label>
                        <select name="emergency_type" class="form-select w-100" required>
                            <option value="">Choose type</option>
                            <?php foreach ($typeLabels as $value => $label): ?>
                                <option value="<?php echo e($value); ?>"><?php echo e($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Exact Location on Campus</label>
                        <input type="text" name="address" class="form-control w-100" required
                               placeholder="e.g. Library 2nd Floor, Block C, near Room 305">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">What's happening?</label>
                        <textarea name="description" class="form-control w-100" rows="4" required
                                  placeholder="Briefly describe the injury/illness or situation..."></textarea>
                    </div>

                    <div class="mb-3 life-check">
                        <input type="checkbox" name="life_threatening" id="lifeThreatening" value="1">
                        <label for="lifeThreatening">This is life-threatening / needs help right now</label>
                    </div>

                    <button type="submit" class="btn-sos">
                        <i class="fa-solid fa-paper-plane"></i> Send SOS Request
                    </button>
                </form>
            </div>

            <!-- RIGHT: Recent requests -->
            <div class="panel">
                <div class="panel-title">
                    <span class="pt-ico"><i class="fa-solid fa-clock-rotate-left"></i></span>
                    My Recent Requests
                </div>
                <p class="panel-sub">Status of the emergency requests you've sent.</p>

                <?php if (!empty($myRequests)): ?>
                    <?php foreach ($myRequests as $req): ?>
                        <div class="req-item">
                            <div class="req-head">
                                <span class="req-type"><?php echo e($typeLabels[$req['request_type']] ?? ucfirst($req['request_type'])); ?></span>
                                <span class="pill <?php echo requestStatusClass($req['status']); ?>">
                                    <?php echo e(ucfirst($req['status'])); ?>
                                </span>
                            </div>
                            <div class="req-desc"><?php echo e($req['description']); ?></div>
                            <div class="req-meta">
                                <span><i class="fa-solid fa-location-dot"></i> <?php echo e($req['address']); ?></span>
                                <span class="pill <?php echo requestPriorityClass($req['priority']); ?>">
                                    <?php echo e(ucfirst($req['priority'])); ?>
                                </span>
                                <span><?php echo e($req['created_at']); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-req">
                        <i class="fa-solid fa-circle-check"></i>
                        <p>No emergency requests sent yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script>
function openSidebar() {
    document.getElementById('sidebar').classList.add('open');
    document.getElementById('overlay').classList.add('open');
}

function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('overlay').classList.remove('open');
}
</script>

</body>
</html>
