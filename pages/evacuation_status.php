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

// shelters for dropdown
$shelters = $conn->query("
    SELECT id, shelter_name, address, city, total_capacity, current_occupancy, status
    FROM shelters
    WHERE status IN ('open', 'full')
    ORDER BY shelter_name ASC
");

// latest status for this user
$currentStatus = null;
$stmt = $conn->prepare("
    SELECT es.*, s.shelter_name, s.address, s.city
    FROM evacuation_status es
    LEFT JOIN shelters s ON es.shelter_id = s.id
    WHERE es.user_id = ?
    ORDER BY es.id DESC
    LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res && $res->num_rows > 0) {
    $currentStatus = $res->fetch_assoc();
}
$stmt->close();

// submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = trim($_POST['status'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $shelter_id = $_POST['shelter_id'] ?? '';

    $allowed = ['safe', 'evacuated', 'need_help'];

    if (!in_array($status, $allowed, true)) {
        $error = "Please select a valid status.";
    } elseif ($status === 'evacuated' && $shelter_id === '') {
        $error = "Please select a shelter if you are evacuated.";
    } else {
        $shelter_value = ($shelter_id === '') ? null : (int)$shelter_id;

        $insert = $conn->prepare("
            INSERT INTO evacuation_status (user_id, status, shelter_id, notes, updated_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $insert->bind_param("isis", $user_id, $status, $shelter_value, $notes);

        if ($insert->execute()) {
            $success = "Evacuation status saved successfully.";
        } else {
            $error = "Failed to save evacuation status.";
        }
        $insert->close();

        // reload latest
        $stmt = $conn->prepare("
            SELECT es.*, s.shelter_name, s.address, s.city
            FROM evacuation_status es
            LEFT JOIN shelters s ON es.shelter_id = s.id
            WHERE es.user_id = ?
            ORDER BY es.id DESC
            LIMIT 1
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $currentStatus = $res->fetch_assoc();
        }
        $stmt->close();
    }
}

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
$uc = $conn->query("SELECT COUNT(*) AS c FROM alert_notifications WHERE user_id = $user_id AND is_read = 0");
if ($uc && $ucr = $uc->fetch_assoc()) {
    $unread_count = (int)$ucr['c'];
}

// Status pill styling helper for the "Latest Status" panel
function evacStatusClass($status)
{
    $s = strtolower(trim($status));
    if ($s === 'safe') {
        return 'ev-safe';
    } elseif ($s === 'evacuated') {
        return 'ev-evac';
    } elseif ($s === 'need_help') {
        return 'ev-help';
    }
    return 'ev-default';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evacuation Status - ResQLink</title>

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
            background: var(--accent);
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

        /* ---------- Layout grid ---------- */
        .evac-grid {
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
            background: var(--accent-light);
            color: var(--accent);
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

        /* ---------- Latest status panel ---------- */
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .03em;
            color: #fff;
            margin-bottom: 18px;
        }

        .ev-safe { background: #15803d; }
        .ev-evac { background: #2563eb; }
        .ev-help { background: #b91c1c; }
        .ev-default { background: #6b7280; }

        .status-info {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .status-row {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 13px;
        }

        .status-row i {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            background: #f3f4f6;
            color: var(--muted);
            display: grid;
            place-items: center;
            flex-shrink: 0;
        }

        .status-row .label { color: var(--muted); font-weight: 600; min-width: 70px; }
        .status-row .value { font-weight: 700; }

        .no-status {
            text-align: center;
            color: var(--muted);
            padding: 20px 0;
        }

        .no-status i {
            font-size: 36px;
            color: var(--accent);
            margin-bottom: 10px;
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
            border-color: var(--accent);
            box-shadow: 0 0 0 0.2rem rgba(46, 125, 50, 0.20);
        }

        .form-text {
            font-size: 12px;
            color: var(--muted);
            margin-top: 5px;
        }

        .mb-3 { margin-bottom: 18px; }

        .btn-save {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 22px;
            border-radius: 10px;
            background: var(--accent);
            color: #fff;
            font-size: 14px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            transition: all .2s ease;
        }

        .btn-save:hover {
            background: var(--accent-dark);
            transform: translateY(-2px);
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
            .evac-grid { grid-template-columns: 1fr; }
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

        <a href="report_emergency.php" class="nav-link">
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

        <a href="evacuation_status.php" class="nav-link active">
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
                <h1>Evacuation Status</h1>
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
                <span class="ph-icon"><i class="fa-solid fa-person-walking-arrow-right"></i></span>
                Evacuation Status Tracking
            </h2>
            <a href="dashboard.php" class="back-btn">
                <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
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

        <div class="evac-grid">
            <!-- LEFT: Update form -->
            <div class="panel">
                <div class="panel-title">
                    <span class="pt-ico"><i class="fa-solid fa-pen-to-square"></i></span>
                    Update Your Status
                </div>
                <p class="panel-sub">Update your current situation during an emergency.</p>

                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Select Status</label>
                        <select name="status" id="statusSelect" class="form-select w-100" required>
                            <option value="">Choose status</option>
                            <option value="safe">Safe</option>
                            <option value="evacuated">Evacuated</option>
                            <option value="need_help">Need Help</option>
                        </select>
                    </div>

                    <div class="mb-3" id="shelterWrap">
                        <label class="form-label">Select Shelter</label>
                        <select name="shelter_id" class="form-select w-100">
                            <option value="">No shelter selected</option>
                            <?php if ($shelters): ?>
                                <?php while ($shelter = $shelters->fetch_assoc()): ?>
                                    <?php
                                    $available = (int)$shelter['total_capacity'] - (int)$shelter['current_occupancy'];
                                    if ($available < 0) $available = 0;
                                    ?>
                                    <option value="<?php echo (int)$shelter['id']; ?>">
                                        <?php echo htmlspecialchars($shelter['shelter_name'] . " - " . $shelter['city'] . " - Available: " . $available); ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                        <div class="form-text">Select a shelter only if you are evacuated.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control w-100" rows="4" placeholder="Add any extra details..."></textarea>
                    </div>

                    <button type="submit" class="btn-save">
                        <i class="fa-solid fa-floppy-disk"></i> Save Status
                    </button>
                </form>
            </div>

            <!-- RIGHT: Latest status -->
            <div class="panel">
                <div class="panel-title">
                    <span class="pt-ico"><i class="fa-solid fa-clock-rotate-left"></i></span>
                    Latest Status
                </div>
                <p class="panel-sub">Your most recent evacuation update.</p>

                <?php if ($currentStatus): ?>
                    <span class="status-pill <?php echo evacStatusClass($currentStatus['status']); ?>">
                        <i class="fa-solid fa-circle-dot"></i>
                        <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $currentStatus['status']))); ?>
                    </span>

                    <div class="status-info">
                        <?php if (!empty($currentStatus['shelter_name'])): ?>
                            <div class="status-row">
                                <i class="fa-solid fa-house-chimney"></i>
                                <span class="label">Shelter</span>
                                <span class="value"><?php echo htmlspecialchars($currentStatus['shelter_name']); ?></span>
                            </div>
                            <div class="status-row">
                                <i class="fa-solid fa-location-dot"></i>
                                <span class="label">Address</span>
                                <span class="value"><?php echo htmlspecialchars($currentStatus['address']); ?></span>
                            </div>
                            <div class="status-row">
                                <i class="fa-solid fa-city"></i>
                                <span class="label">City</span>
                                <span class="value"><?php echo htmlspecialchars($currentStatus['city'] ?? 'N/A'); ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($currentStatus['notes'])): ?>
                            <div class="status-row">
                                <i class="fa-solid fa-note-sticky"></i>
                                <span class="label">Notes</span>
                                <span class="value"><?php echo htmlspecialchars($currentStatus['notes']); ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="status-row">
                            <i class="fa-solid fa-clock"></i>
                            <span class="label">Updated</span>
                            <span class="value"><?php echo htmlspecialchars($currentStatus['updated_at']); ?></span>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="no-status">
                        <i class="fa-solid fa-circle-question"></i>
                        <p>No status set yet. Update your status using the form.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script>
const statusSelect = document.getElementById('statusSelect');
const shelterWrap = document.getElementById('shelterWrap');

function toggleShelterField() {
    shelterWrap.style.display = statusSelect.value === 'evacuated' ? 'block' : 'none';
}
toggleShelterField();
statusSelect.addEventListener('change', toggleShelterField);

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
