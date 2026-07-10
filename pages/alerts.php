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

// Unread count for sidebar badge
$unread_count = 0;
$uc = $conn->query("SELECT COUNT(*) AS c FROM alert_notifications WHERE user_id = $user_id AND is_read = 0");
if ($uc && $ucr = $uc->fetch_assoc()) {
    $unread_count = (int)$ucr['c'];
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

function getAlertBackground($alertType)
{
    $type = strtolower(trim($alertType));

    if (strpos($type, 'cyclone') !== false) {
        return '../uploads/default_alerts/cyclone.jpg';
    } elseif (strpos($type, 'fire') !== false) {
        return '../uploads/default_alerts/fire.jpg';
    } elseif (strpos($type, 'flood') !== false) {
        return '../uploads/default_alerts/flood.jpg';
    } elseif (strpos($type, 'earthquake') !== false) {
        return '../uploads/default_alerts/earthquake.jpg';
    } else {
        return '../uploads/default_alerts/default.jpg';
    }
}

function getAlertIcon($alertType)
{
    $type = strtolower(trim($alertType));

    if (strpos($type, 'cyclone') !== false) {
        return 'fa-solid fa-wind';
    } elseif (strpos($type, 'fire') !== false) {
        return 'fa-solid fa-fire';
    } elseif (strpos($type, 'flood') !== false) {
        return 'fa-solid fa-water';
    } elseif (strpos($type, 'earthquake') !== false) {
        return 'fa-solid fa-house-crack';
    } else {
        return 'fa-solid fa-triangle-exclamation';
    }
}

function getSeverityClass($severity)
{
    $severity = strtolower(trim($severity));

    switch ($severity) {
        case 'low':
            return 'bg-success';
        case 'medium':
            return 'bg-warning text-dark';
        case 'high':
            return 'bg-danger';
        case 'critical':
            return 'bg-dark';
        default:
            return 'bg-secondary';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Alerts - ResQLink</title>

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
            padding: 9px 18px;
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

        /* ---------- Alert Cards (photo backgrounds kept) ---------- */
        .alert-card {
            position: relative;
            overflow: hidden;
            border-radius: var(--radius);
            margin-bottom: 20px;
            min-height: 260px;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .alert-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 14px 30px rgba(0, 0, 0, 0.22);
        }

        .alert-overlay {
            position: absolute;
            inset: 0;
            background: transparent;
        }

        .alert-content {
            position: relative;
            z-index: 2;
            color: #fff;
            padding: 24px;
            text-shadow: 0 2px 6px rgba(0, 0, 0, 0.9), 0 0 3px rgba(0, 0, 0, 0.75);
        }

        .alert-title {
            font-size: 1.4rem;
            font-weight: 800;
            margin-bottom: 10px;
        }

        .alert-title-row {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .alert-icon {
            font-size: 1.35rem;
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.18);
            color: #ffd54f;
            box-shadow: 0 0 12px rgba(255, 213, 79, 0.35);
            backdrop-filter: blur(3px);
        }

        .unread-dot {
            width: 10px;
            height: 10px;
            background: #00ff88;
            border-radius: 50%;
            display: inline-block;
            box-shadow: 0 0 10px rgba(0, 255, 136, 0.8);
        }

        .alert-meta {
            font-size: 0.95rem;
            margin-bottom: 8px;
        }

        .alert-instructions {
            margin-top: 14px;
            font-size: 1rem;
            line-height: 1.6;
            background: rgba(0, 0, 0, 0.25);
            padding: 12px 14px;
            border-radius: 10px;
            backdrop-filter: blur(2px);
        }

        .alert-footer {
            margin-top: 16px;
        }

        .mark-btn { font-weight: 700; }

        .read-badge {
            background: rgba(255, 255, 255, 0.18);
            color: #fff;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
        }

        .icon-fire { color: #ff7043; }
        .icon-flood { color: #4fc3f7; }
        .icon-cyclone { color: #cfd8dc; }
        .icon-earthquake { color: #ffca28; }
        .icon-default { color: #ffd54f; }

        .empty-state {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 40px;
            text-align: center;
            color: var(--muted);
            box-shadow: 0 1px 4px rgba(0, 0, 0, .06);
        }

        .empty-state i {
            font-size: 40px;
            color: var(--accent);
            margin-bottom: 12px;
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

        <a href="alerts.php" class="nav-link active">
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
                <h1>Disaster Alerts</h1>
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
                <span class="ph-icon"><i class="fa-solid fa-bell"></i></span>
                Disaster Alerts
            </h2>
            <a href="dashboard.php" class="back-btn">
                <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <?php
                    $bgImage = getAlertBackground($row['alert_type']);
                    $iconClass = getAlertIcon($row['alert_type']);
                    $severityClass = getSeverityClass($row['severity']);

                    $iconColorClass = 'icon-default';
                    $type = strtolower(trim($row['alert_type']));

                    if (strpos($type, 'fire') !== false) {
                        $iconColorClass = 'icon-fire';
                    } elseif (strpos($type, 'flood') !== false) {
                        $iconColorClass = 'icon-flood';
                    } elseif (strpos($type, 'cyclone') !== false) {
                        $iconColorClass = 'icon-cyclone';
                    } elseif (strpos($type, 'earthquake') !== false) {
                        $iconColorClass = 'icon-earthquake';
                    }
                ?>

                <div class="alert-card" style="background-image: url('<?php echo htmlspecialchars($bgImage); ?>');">
                    <div class="alert-overlay"></div>

                    <div class="alert-content">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                            <div class="alert-title">
                                <div class="alert-title-row">
                                    <span class="alert-icon">
                                        <i class="<?php echo htmlspecialchars($iconClass . ' ' . $iconColorClass); ?>"></i>
                                    </span>

                                    <?php if (!(int)$row['is_read']): ?>
                                        <span class="unread-dot"></span>
                                    <?php endif; ?>

                                    <span><?php echo htmlspecialchars($row['alert_type']); ?></span>
                                </div>
                            </div>

                            <span class="badge <?php echo $severityClass; ?>">
                                <?php echo htmlspecialchars(ucfirst($row['severity'])); ?>
                            </span>
                        </div>

                        <div class="alert-meta">
                            <strong>Location:</strong> <?php echo htmlspecialchars($row['location_text']); ?>
                        </div>

                        <div class="alert-meta">
                            <strong>Published At:</strong> <?php echo htmlspecialchars($row['created_at']); ?>
                        </div>

                        <div class="alert-instructions">
                            <strong>Instructions:</strong><br>
                            <?php echo nl2br(htmlspecialchars($row['instructions'])); ?>
                        </div>

                        <div class="alert-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <?php if ((int)$row['is_read']): ?>
                                    <span class="read-badge">Already Read</span>
                                <?php endif; ?>
                            </div>

                            <div>
                                <?php if (!(int)$row['is_read']): ?>
                                    <a href="?read=<?php echo (int)$row['notif_id']; ?>" class="btn btn-success btn-sm mark-btn">
                                        Mark as Read
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fa-solid fa-bell-slash"></i>
                <p>No published alerts found.</p>
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
