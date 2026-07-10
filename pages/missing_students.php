<?php
session_start();
require_once __DIR__ . "/../DB/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$view = ($_GET['view'] ?? 'active') === 'history' ? 'history' : 'active';

if ($view === 'history') {
    $result = $conn->query("
        SELECT id, student_name, age, gender, last_seen_location, last_seen_at, status, found_at, created_at
        FROM missing_student_reports
        WHERE status = 'found'
        ORDER BY found_at DESC
    ");
} else {
    $result = $conn->query("
        SELECT id, student_name, age, gender, last_seen_location, last_seen_at, status, found_at, created_at
        FROM missing_student_reports
        WHERE status = 'approved'
        ORDER BY created_at DESC
    ");
}

$reports = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $reports[] = $row;
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
    <title>Missing Student Alerts - ResQLink</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --accent: #2e7d32;
            --accent-dark: #1b5e20;
            --accent-light: #e8f5e9;
            --warn: #b45309;
            --warn-dark: #92400e;
            --warn-light: #fffbeb;
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

        .brand-name { font-size: 18px; font-weight: 800; }
        .brand-name span { color: var(--accent); }

        .sidebar-nav { flex: 1; padding: 16px 12px; overflow-y: auto; }

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

        .nav-link:hover, .nav-link.active {
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

        .sidebar-footer { padding: 14px 12px; border-top: 1px solid var(--border); }
        .logout { color: #dc2626; }

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

        .topbar-right { display: flex; align-items: center; gap: 12px; }

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
            background: var(--warn);
            color: #fff;
            display: grid;
            place-items: center;
            font-size: 16px;
        }

        .head-actions { display: flex; gap: 10px; flex-wrap: wrap; }

        .tab-btn, .back-btn, .cta-btn {
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

        .tab-btn:hover, .back-btn:hover {
            background: var(--warn-light);
            color: var(--warn-dark);
            border-color: var(--warn);
        }

        .tab-btn.active {
            background: var(--warn);
            color: #fff;
            border-color: var(--warn);
        }

        .cta-btn {
            background: var(--warn);
            color: #fff;
            border-color: var(--warn);
        }

        .cta-btn:hover { background: var(--warn-dark); color: #fff; }

        .grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
        }

        .card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: 0 1px 4px rgba(0, 0, 0, .06);
            overflow: hidden;
            text-decoration: none;
            color: var(--text);
            display: block;
            transition: transform .15s ease;
        }

        .card:hover { transform: translateY(-3px); }

        .card-top {
            background: var(--warn-light);
            padding: 18px;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .card.history .card-top { background: var(--accent-light); }

        .card-avatar {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: var(--warn);
            color: #fff;
            display: grid;
            place-items: center;
            font-size: 20px;
            font-weight: 800;
            flex-shrink: 0;
        }

        .card.history .card-avatar { background: var(--accent); }

        .card-name { font-size: 15px; font-weight: 800; margin-bottom: 2px; }
        .card-sub { font-size: 11.5px; color: var(--muted); }

        .card-body { padding: 16px 18px; }

        .card-row {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            font-size: 12.5px;
            color: var(--muted);
            margin-bottom: 8px;
        }

        .card-row i { width: 14px; color: var(--warn); margin-top: 2px; }
        .card.history .card-row i { color: var(--accent); }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 10.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .03em;
            background: #fef3c7;
            color: #92400e;
        }

        .card.history .status-pill { background: #dcfce7; color: #166534; }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--muted);
            background: var(--white);
            border: 1px dashed var(--border);
            border-radius: var(--radius);
        }

        .empty-state i { font-size: 44px; color: var(--warn); margin-bottom: 14px; }

        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .45);
            z-index: 90;
        }

        .sidebar-overlay.open { display: block; }

        @media (max-width: 1100px) {
            .grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 700px) {
            .grid { grid-template-columns: 1fr; }
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

        <a href="missing_students.php" class="nav-link active">
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

        <a href="chatbot.php" class="nav-link">
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
                <h1>Missing Student Alerts</h1>
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
                <span class="ph-icon"><i class="fa-solid fa-user-magnifying-glass"></i></span>
                Missing Student Alerts
            </h2>
            <div class="head-actions">
                <a href="missing_students.php?view=active" class="tab-btn <?php echo $view === 'active' ? 'active' : ''; ?>">
                    Active Alerts
                </a>
                <a href="missing_students.php?view=history" class="tab-btn <?php echo $view === 'history' ? 'active' : ''; ?>">
                    History (Found)
                </a>
                <a href="report_missing_student.php" class="cta-btn">
                    <i class="fa-solid fa-circle-plus"></i> Report Missing Student
                </a>
            </div>
        </div>

        <?php if (empty($reports)): ?>
            <div class="empty-state">
                <i class="fa-solid <?php echo $view === 'history' ? 'fa-circle-check' : 'fa-user-magnifying-glass'; ?>"></i>
                <p><?php echo $view === 'history' ? 'No students found and resolved yet.' : 'No active missing student alerts right now.'; ?></p>
            </div>
        <?php else: ?>
            <div class="grid">
                <?php foreach ($reports as $r): ?>
                    <a href="view_missing_student.php?id=<?php echo (int)$r['id']; ?>" class="card <?php echo $view === 'history' ? 'history' : ''; ?>">
                        <div class="card-top">
                            <div class="card-avatar"><?php echo strtoupper(substr($r['student_name'], 0, 1)); ?></div>
                            <div>
                                <div class="card-name"><?php echo e($r['student_name']); ?></div>
                                <div class="card-sub">
                                    <?php echo $r['age'] ? e($r['age']) . ' yrs' : 'Age unknown'; ?>
                                    <?php if (!empty($r['gender'])): ?> · <?php echo e(ucfirst($r['gender'])); ?><?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="card-row">
                                <i class="fa-solid fa-location-dot"></i>
                                <span>Last seen: <?php echo e($r['last_seen_location']); ?></span>
                            </div>
                            <div class="card-row">
                                <i class="fa-solid fa-clock"></i>
                                <span><?php echo e(date('M j, Y · H:i', strtotime($r['last_seen_at']))); ?></span>
                            </div>
                            <span class="status-pill">
                                <i class="fa-solid <?php echo $view === 'history' ? 'fa-circle-check' : 'fa-triangle-exclamation'; ?>"></i>
                                <?php echo $view === 'history' ? 'Found' : 'Missing'; ?>
                            </span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
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
