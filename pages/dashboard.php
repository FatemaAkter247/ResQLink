<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once '../DB/db.php';

if (!isset($conn) || !$conn) {
    die("Database connection failed.");
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
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

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function getSingleValue($conn, $sql)
{
    $result = $conn->query($sql);

    if ($result && $row = $result->fetch_row()) {
        return $row[0];
    }

    return 0;
}

function getPreparedSingleValue($conn, $sql, $types = '', ...$params)
{
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return 0;
    }

    if ($types !== '' && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $row = $result->fetch_row()) {
        $stmt->close();
        return $row[0];
    }

    $stmt->close();
    return 0;
}

function getPreparedFirstValue($conn, $sql, $types = '', ...$params)
{
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return '';
    }

    if ($types !== '' && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $row = $result->fetch_row()) {
        $stmt->close();
        return $row[0];
    }

    $stmt->close();
    return '';
}

function getRows($conn, $sql)
{
    $rows = [];
    $result = $conn->query($sql);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }

    return $rows;
}

function getPreparedRows($conn, $sql, $types = '', ...$params)
{
    $rows = [];
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return $rows;
    }

    if ($types !== '' && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }

    $stmt->close();
    return $rows;
}

function severityColor($severity)
{
    switch (strtolower((string)$severity)) {
        case 'critical':
            return '#b91c1c';
        case 'high':
            return '#ea580c';
        case 'medium':
            return '#ca8a04';
        case 'low':
            return '#15803d';
        default:
            return '#4b5563';
    }
}

function missionColor($status)
{
    switch (strtolower((string)$status)) {
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

$stats = [];
$recent_alerts = [];
$my_missions = [];

if ($role === 'admin' || $role === 'system_admin') {
    $stats['alerts'] = getSingleValue($conn, "SELECT COUNT(*) FROM disaster_alerts WHERE status='published'");
    $stats['shelters'] = getSingleValue($conn, "SELECT COUNT(*) FROM shelters WHERE status='open'");
    $stats['resources'] = getSingleValue($conn, "SELECT COUNT(*) FROM emergency_resources WHERE status='available'");
    $stats['requests'] = getSingleValue($conn, "SELECT COUNT(*) FROM emergency_requests WHERE status='pending'");
    $stats['users'] = getSingleValue($conn, "SELECT COUNT(*) FROM users WHERE is_active=1");
    $stats['evacuated'] = getSingleValue($conn, "SELECT COUNT(*) FROM evacuation_status WHERE status='evacuated'");

    $recent_alerts = getRows($conn, "
        SELECT alert_type, severity, location_text, published_at
        FROM disaster_alerts
        WHERE status='published'
        ORDER BY published_at DESC
        LIMIT 5
    ");
} elseif ($role === 'rescue_team') {
    $stats['alerts'] = getSingleValue($conn, "SELECT COUNT(*) FROM disaster_alerts WHERE status='published'");

    $stats['my_missions'] = getPreparedSingleValue(
        $conn,
        "SELECT COUNT(*) FROM rescue_missions
         WHERE team_user_id = ?
         AND mission_status NOT IN ('completed', 'failed')",
        "i",
        $user_id
    );

    $stats['shelters'] = getSingleValue($conn, "SELECT COUNT(*) FROM shelters WHERE status='open'");
    $stats['pending_requests'] = getSingleValue($conn, "SELECT COUNT(*) FROM emergency_requests WHERE status='pending'");
    $stats['resources'] = getSingleValue($conn, "SELECT COUNT(*) FROM emergency_resources WHERE status='available'");

    $my_missions = getPreparedRows(
        $conn,
        "SELECT rm.mission_status, er.request_type, er.address, er.priority
         FROM rescue_missions rm
         JOIN emergency_requests er ON rm.request_id = er.id
         WHERE rm.team_user_id = ?
         ORDER BY rm.created_at DESC
         LIMIT 5",
        "i",
        $user_id
    );

    $recent_alerts = getRows($conn, "
        SELECT alert_type, severity, location_text, published_at
        FROM disaster_alerts
        WHERE status='published'
        ORDER BY published_at DESC
        LIMIT 5
    ");
} else {
    $stats['alerts'] = getSingleValue($conn, "SELECT COUNT(*) FROM disaster_alerts WHERE status='published'");
    $stats['shelters'] = getSingleValue($conn, "SELECT COUNT(*) FROM shelters WHERE status='open'");

    $my_evac = getPreparedFirstValue(
        $conn,
        "SELECT status FROM evacuation_status WHERE user_id = ? ORDER BY updated_at DESC LIMIT 1",
        "i",
        $user_id
    );

    $stats['my_evac'] = $my_evac !== '' ? $my_evac : 'Not set';

    $stats['my_requests'] = getPreparedSingleValue(
        $conn,
        "SELECT COUNT(*) FROM emergency_requests WHERE created_by = ? AND status='pending'",
        "i",
        $user_id
    );

    $stats['unread_notifs'] = getPreparedSingleValue(
        $conn,
        "SELECT COUNT(*) FROM alert_notifications WHERE user_id = ? AND is_read = 0",
        "i",
        $user_id
    );

    $recent_alerts = getRows($conn, "
        SELECT alert_type, severity, location_text, published_at
        FROM disaster_alerts
        WHERE status='published'
        ORDER BY published_at DESC
        LIMIT 5
    ");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard | ResQLink</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

:root {
    --accent: #c62828;
    --accent-dark: #8e0000;
    --accent-light: #ffebee;
    --sidebar-width: 265px;
    --bg: #f0f2f5;
    --white: #ffffff;
    --text: #1a1a2e;
    --muted: #6b7280;
    --border: #e5e7eb;
    --shadow: 0 4px 16px rgba(0, 0, 0, .10);
    --radius: 14px;
}

<?php if ($role === 'rescue_team'): ?>
:root {
    --accent: #1565c0;
    --accent-dark: #0d47a1;
    --accent-light: #e3f2fd;
}
<?php elseif ($role === 'citizen'): ?>
:root {
    --accent: #2e7d32;
    --accent-dark: #1b5e20;
    --accent-light: #e8f5e9;
}
<?php endif; ?>

body {
    font-family: 'Plus Jakarta Sans', sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    display: flex;
}

a {
    color: inherit;
}

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

.brand-name span {
    color: var(--accent);
}

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

.nav-link i {
    width: 18px;
    text-align: center;
}

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

.logout {
    color: #dc2626;
}

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

.topbar-title h1 {
    font-size: 17px;
    font-weight: 800;
}

.topbar-title p {
    font-size: 12px;
    color: var(--muted);
    margin-top: 2px;
}

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

.user-chip-name {
    font-size: 13px;
    font-weight: 800;
}

.user-chip-role {
    font-size: 11px;
    color: var(--muted);
}

.hamburger {
    display: none;
    background: none;
    border: none;
    color: var(--text);
    font-size: 20px;
    cursor: pointer;
}

.content {
    padding: 28px;
}

.welcome-banner {
    background: linear-gradient(135deg, var(--accent-dark), var(--accent));
    color: #fff;
    border-radius: var(--radius);
    padding: 28px 32px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 22px;
    overflow: hidden;
    position: relative;
}

.welcome-banner::after {
    content: '';
    position: absolute;
    width: 260px;
    height: 260px;
    right: -80px;
    top: -80px;
    border-radius: 50%;
    background: rgba(255, 255, 255, .10);
}

.wb-text,
.wb-actions {
    position: relative;
    z-index: 1;
}

.wb-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(255, 255, 255, .20);
    padding: 5px 13px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: .06em;
    text-transform: uppercase;
    margin-bottom: 10px;
}

.wb-text h2 {
    font-size: 24px;
    font-weight: 800;
    margin-bottom: 6px;
}

.wb-text p {
    font-size: 14px;
    line-height: 1.5;
    opacity: .9;
    max-width: 500px;
}

.wb-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.wb-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 11px 18px;
    border-radius: 50px;
    background: #fff;
    color: var(--accent);
    font-size: 13px;
    font-weight: 800;
    text-decoration: none;
    white-space: nowrap;
}

.wb-btn.outline {
    background: transparent;
    color: #fff;
    border: 2px solid rgba(255, 255, 255, .55);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(185px, 1fr));
    gap: 14px;
    margin-bottom: 24px;
}

.stat-card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 20px;
    box-shadow: 0 1px 4px rgba(0, 0, 0, .06);
    display: flex;
    align-items: center;
    gap: 15px;
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 13px;
    display: grid;
    place-items: center;
    font-size: 20px;
}

.stat-info p {
    color: var(--muted);
    font-size: 12px;
    font-weight: 700;
    margin-bottom: 4px;
}

.stat-info h3 {
    font-size: 26px;
    line-height: 1;
    font-weight: 800;
}

.stat-info small {
    display: block;
    margin-top: 4px;
    color: var(--muted);
    font-size: 11px;
}

.section-hd {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 14px;
}

.section-hd h2 {
    font-size: 16px;
    font-weight: 800;
}

.see-all {
    color: var(--accent);
    text-decoration: none;
    font-size: 13px;
    font-weight: 800;
}

.action-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 13px;
    margin-bottom: 26px;
}

.action-card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: 0 1px 4px rgba(0, 0, 0, .06);
    min-height: 130px;
    text-decoration: none;
    color: var(--text);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 12px;
    padding: 18px;
    text-align: center;
    transition: transform .2s, box-shadow .2s;
}

.action-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow);
}

.ac-icon {
    width: 52px;
    height: 52px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    color: #fff;
    font-size: 20px;
}

.action-card span {
    font-size: 13px;
    font-weight: 800;
}

.two-col {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
}

.table-card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: 0 1px 4px rgba(0, 0, 0, .06);
    overflow: hidden;
}

.table-card table {
    width: 100%;
    border-collapse: collapse;
}

.table-card th,
.table-card td {
    padding: 12px 18px;
    text-align: left;
    border-bottom: 1px solid var(--border);
    font-size: 13px;
}

.table-card th {
    background: #fafafa;
    color: var(--muted);
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .05em;
}

.table-card tr:last-child td {
    border-bottom: none;
}

.pill {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 20px;
    color: #fff;
    font-size: 11px;
    font-weight: 800;
}

.empty-row td {
    color: var(--muted);
    text-align: center;
    font-style: italic;
    padding: 24px;
}

.tip-item {
    display: flex;
    gap: 12px;
    padding: 16px 18px;
    border-bottom: 1px solid var(--border);
}

.tip-item:last-child {
    border-bottom: none;
}

.tip-ico {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    display: grid;
    place-items: center;
    flex-shrink: 0;
}

.tip-item strong {
    display: block;
    font-size: 13px;
    margin-bottom: 2px;
}

.tip-item p {
    color: var(--muted);
    font-size: 12px;
}

.sidebar-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, .45);
    z-index: 90;
}

.sidebar-overlay.open {
    display: block;
}

@media (max-width: 800px) {
    .sidebar {
        transform: translateX(-100%);
    }

    .sidebar.open {
        transform: translateX(0);
    }

    .main {
        margin-left: 0;
        width: 100%;
    }

    .hamburger {
        display: block;
    }

    .content {
        padding: 16px;
    }

    .topbar {
        padding: 0 16px;
    }

    .user-chip-role {
        display: none;
    }

    .welcome-banner {
        flex-direction: column;
        align-items: flex-start;
    }

    .wb-actions {
        justify-content: flex-start;
    }

    .two-col {
        grid-template-columns: 1fr;
    }
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

        <a href="dashboard.php" class="nav-link active">
            <i class="fa-solid fa-gauge-high"></i> Dashboard
        </a>

        <a href="chatbot.php" class="nav-link">
            <i class="fa-solid fa-robot"></i> AI Emergency Chatbot
        </a>

        <?php if ($role === 'admin' || $role === 'system_admin'): ?>
            <span class="nav-label">Alerts</span>

            <a href="alerts.php" class="nav-link">
                <i class="fa-solid fa-bell"></i> View Alerts
                <?php if (($stats['alerts'] ?? 0) > 0): ?>
                    <span class="badge"><?= (int)$stats['alerts'] ?></span>
                <?php endif; ?>
            </a>

            <a href="admin/create_alert.php" class="nav-link">
                <i class="fa-solid fa-circle-plus"></i> Create Alert
            </a>

            <span class="nav-label">Management</span>

            <a href="admin/manage_shelters.php" class="nav-link">
                <i class="fa-solid fa-house-chimney"></i> Manage Shelters
            </a>

            <a href="admin/manage_resources.php" class="nav-link">
                <i class="fa-solid fa-boxes-stacked"></i> Manage Resources
            </a>

            <a href="admin/manage_evacuation.php" class="nav-link">
                <i class="fa-solid fa-person-walking-arrow-right"></i> Manage Evacuation
            </a>

            <a href="admin/rescue_team.php" class="nav-link">
                <i class="fa-solid fa-person-rifle"></i> Rescue Team
            </a>

        <?php elseif ($role === 'rescue_team'): ?>
            <span class="nav-label">Operations</span>

            <a href="alerts.php" class="nav-link">
                <i class="fa-solid fa-bell"></i> Active Alerts
                <?php if (($stats['alerts'] ?? 0) > 0): ?>
                    <span class="badge"><?= (int)$stats['alerts'] ?></span>
                <?php endif; ?>
            </a>

            <a href="rescue_team.php" class="nav-link">
                <i class="fa-solid fa-person-rifle"></i> My Missions
                <?php if (($stats['my_missions'] ?? 0) > 0): ?>
                    <span class="badge"><?= (int)$stats['my_missions'] ?></span>
                <?php endif; ?>
            </a>

            <a href="rescue_team.php?view=requests" class="nav-link">
                <i class="fa-solid fa-triangle-exclamation"></i> Emergency Requests
                <?php if (($stats['pending_requests'] ?? 0) > 0): ?>
                    <span class="badge"><?= (int)$stats['pending_requests'] ?></span>
                <?php endif; ?>
            </a>

            <span class="nav-label">Resources</span>

            <a href="shelters.php" class="nav-link">
                <i class="fa-solid fa-house-chimney"></i> Shelters
            </a>

            <a href="resources.php" class="nav-link">
                <i class="fa-solid fa-boxes-stacked"></i> Resources
            </a>

        <?php else: ?>
            <span class="nav-label">Disaster Info</span>

            <a href="alerts.php" class="nav-link">
                <i class="fa-solid fa-bell"></i> Alerts
                <?php if (($stats['unread_notifs'] ?? 0) > 0): ?>
                    <span class="badge"><?= (int)$stats['unread_notifs'] ?></span>
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
        <?php endif; ?>
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
                <h1>Dashboard</h1>
                <p><?= date('l, F j, Y') ?></p>
            </div>
        </div>

        <div class="topbar-right">
            <a href="chatbot.php" class="icon-btn" title="AI Emergency Chatbot">
                <i class="fa-solid fa-robot"></i>
            </a>

            <a href="alerts.php" class="icon-btn" title="Alerts">
                <i class="fa-solid fa-bell"></i>
                <?php if (($stats['alerts'] ?? 0) > 0): ?>
                    <span class="badge-dot"></span>
                <?php endif; ?>
            </a>

            <div class="user-chip">
                <div class="avatar"><?= e($initials) ?></div>
                <div>
                    <div class="user-chip-name"><?= $username ?></div>
                    <div class="user-chip-role"><?= e($role_label) ?></div>
                </div>
            </div>
        </div>
    </header>

    <main class="content">
        <section class="welcome-banner">
            <div class="wb-text">
                <div class="wb-badge">
                    <i class="fa-solid fa-circle-dot" style="font-size:9px;"></i>
                    <?= e($role_label) ?> Dashboard
                </div>

                <h2>Welcome back, <?= $username ?>!</h2>

                <p>
                    <?php if ($role === 'admin' || $role === 'system_admin'): ?>
                        Oversee disaster alerts, shelters, resources, evacuation operations, and AI-assisted emergency support.
                    <?php elseif ($role === 'rescue_team'): ?>
                        Coordinate missions, monitor emergency requests, and use AI guidance for faster field response.
                    <?php else: ?>
                        Stay informed, find shelters, update your evacuation status, and ask the AI chatbot for emergency help.
                    <?php endif; ?>
                </p>
            </div>

            <div class="wb-actions">
                <?php if ($role === 'admin' || $role === 'system_admin'): ?>
                    <a href="admin/create_alert.php" class="wb-btn">
                        <i class="fa-solid fa-circle-plus"></i> Create Alert
                    </a>

                    <a href="chatbot.php" class="wb-btn outline">
                        <i class="fa-solid fa-robot"></i> AI Chatbot
                    </a>
                <?php elseif ($role === 'rescue_team'): ?>
                    <a href="alerts.php" class="wb-btn">
                        <i class="fa-solid fa-bell"></i> Active Alerts
                    </a>

                    <a href="chatbot.php" class="wb-btn outline">
                        <i class="fa-solid fa-robot"></i> AI Chatbot
                    </a>
                <?php else: ?>
                    <a href="alerts.php" class="wb-btn">
                        <i class="fa-solid fa-bell"></i> View Alerts
                    </a>

                    <a href="chatbot.php" class="wb-btn outline">
                        <i class="fa-solid fa-robot"></i> Ask AI Chatbot
                    </a>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($role === 'admin' || $role === 'system_admin'): ?>
            <section class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background:#fff1f1; color:#c62828;">
                        <i class="fa-solid fa-bell"></i>
                    </div>
                    <div class="stat-info">
                        <p>Active Alerts</p>
                        <h3><?= (int)($stats['alerts'] ?? 0) ?></h3>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background:#e3f2fd; color:#1565c0;">
                        <i class="fa-solid fa-house-chimney"></i>
                    </div>
                    <div class="stat-info">
                        <p>Open Shelters</p>
                        <h3><?= (int)($stats['shelters'] ?? 0) ?></h3>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background:#e0f7fa; color:#00838f;">
                        <i class="fa-solid fa-boxes-stacked"></i>
                    </div>
                    <div class="stat-info">
                        <p>Resources</p>
                        <h3><?= (int)($stats['resources'] ?? 0) ?></h3>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background:#fff8e1; color:#ca8a04;">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <div class="stat-info">
                        <p>Pending Requests</p>
                        <h3><?= (int)($stats['requests'] ?? 0) ?></h3>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background:#f3e5f5; color:#6a1b9a;">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <p>Registered Users</p>
                        <h3><?= (int)($stats['users'] ?? 0) ?></h3>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background:#e8f5e9; color:#15803d;">
                        <i class="fa-solid fa-person-walking-arrow-right"></i>
                    </div>
                    <div class="stat-info">
                        <p>Evacuated</p>
                        <h3><?= (int)($stats['evacuated'] ?? 0) ?></h3>
                    </div>
                </div>
            </section>

            <div class="section-hd">
                <h2>Quick Actions</h2>
            </div>

            <section class="action-grid">
                <a href="chatbot.php" class="action-card">
                    <div class="ac-icon" style="background:#7c3aed;">
                        <i class="fa-solid fa-robot"></i>
                    </div>
                    <span>AI Chatbot</span>
                </a>

                <a href="alerts.php" class="action-card">
                    <div class="ac-icon" style="background:#c62828;">
                        <i class="fa-solid fa-bell"></i>
                    </div>
                    <span>View Alerts</span>
                </a>

                <a href="admin/create_alert.php" class="action-card">
                    <div class="ac-icon" style="background:#222;">
                        <i class="fa-solid fa-circle-plus"></i>
                    </div>
                    <span>Create Alert</span>
                </a>

                <a href="admin/manage_shelters.php" class="action-card">
                    <div class="ac-icon" style="background:#1565c0;">
                        <i class="fa-solid fa-house-chimney"></i>
                    </div>
                    <span>Manage Shelters</span>
                </a>

                <a href="admin/manage_resources.php" class="action-card">
                    <div class="ac-icon" style="background:#00838f;">
                        <i class="fa-solid fa-boxes-stacked"></i>
                    </div>
                    <span>Manage Resources</span>
                </a>

                <a href="admin/manage_evacuation.php" class="action-card">
                    <div class="ac-icon" style="background:#558b2f;">
                        <i class="fa-solid fa-person-walking-arrow-right"></i>
                    </div>
                    <span>Manage Evacuation</span>
                </a>

                <a href="admin/rescue_team.php" class="action-card">
                    <div class="ac-icon" style="background:#1565c0;">
                        <i class="fa-solid fa-person-rifle"></i>
                    </div>
                    <span>Rescue Team</span>
                </a>
            </section>

            <div class="section-hd">
                <h2>Recent Published Alerts</h2>
                <a href="alerts.php" class="see-all">
                    See all <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>

            <div class="table-card">
                <table>
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Location</th>
                            <th>Severity</th>
                            <th>Published</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($recent_alerts)): ?>
                        <?php foreach ($recent_alerts as $alert): ?>
                            <tr>
                                <td><strong><?= e($alert['alert_type']) ?></strong></td>
                                <td><?= e($alert['location_text']) ?></td>
                                <td>
                                    <span class="pill" style="background:<?= severityColor($alert['severity']) ?>">
                                        <?= e(ucfirst($alert['severity'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= !empty($alert['published_at']) ? e(date('M j, H:i', strtotime($alert['published_at']))) : 'N/A' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr class="empty-row">
                            <td colspan="4">No active alerts at the moment.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($role === 'rescue_team'): ?>
            <section class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background:#fff1f1; color:#c62828;">
                        <i class="fa-solid fa-bell"></i>
                    </div>
                    <div class="stat-info">
                        <p>Active Alerts</p>
                        <h3><?= (int)($stats['alerts'] ?? 0) ?></h3>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background:#e3f2fd; color:#1565c0;">
                        <i class="fa-solid fa-person-rifle"></i>
                    </div>
                    <div class="stat-info">
                        <p>My Missions</p>
                        <h3><?= (int)($stats['my_missions'] ?? 0) ?></h3>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background:#fff8e1; color:#ca8a04;">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <div class="stat-info">
                        <p>Pending Requests</p>
                        <h3><?= (int)($stats['pending_requests'] ?? 0) ?></h3>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background:#e0f2f1; color:#00695c;">
                        <i class="fa-solid fa-house-chimney"></i>
                    </div>
                    <div class="stat-info">
                        <p>Open Shelters</p>
                        <h3><?= (int)($stats['shelters'] ?? 0) ?></h3>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background:#e0f7fa; color:#00838f;">
                        <i class="fa-solid fa-boxes-stacked"></i>
                    </div>
                    <div class="stat-info">
                        <p>Resources</p>
                        <h3><?= (int)($stats['resources'] ?? 0) ?></h3>
                    </div>
                </div>
            </section>

            <div class="section-hd">
                <h2>Quick Actions</h2>
            </div>

            <section class="action-grid">
                <a href="chatbot.php" class="action-card">
                    <div class="ac-icon" style="background:#7c3aed;">
                        <i class="fa-solid fa-robot"></i>
                    </div>
                    <span>AI Chatbot</span>
                </a>

                <a href="alerts.php" class="action-card">
                    <div class="ac-icon" style="background:#c62828;">
                        <i class="fa-solid fa-bell"></i>
                    </div>
                    <span>Active Alerts</span>
                </a>

                <a href="rescue_team.php" class="action-card">
                    <div class="ac-icon" style="background:#1565c0;">
                        <i class="fa-solid fa-person-rifle"></i>
                    </div>
                    <span>My Missions</span>
                </a>

                <a href="rescue_team.php?view=requests" class="action-card">
                    <div class="ac-icon" style="background:#e65100;">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <span>View Requests</span>
                </a>

                <a href="shelters.php" class="action-card">
                    <div class="ac-icon" style="background:#00838f;">
                        <i class="fa-solid fa-house-chimney"></i>
                    </div>
                    <span>Shelters</span>
                </a>

                <a href="resources.php" class="action-card">
                    <div class="ac-icon" style="background:#558b2f;">
                        <i class="fa-solid fa-boxes-stacked"></i>
                    </div>
                    <span>Resources</span>
                </a>
            </section>

            <section class="two-col">
                <div>
                    <div class="section-hd">
                        <h2>My Recent Missions</h2>
                    </div>

                    <div class="table-card">
                        <table>
                            <thead>
                                <tr>
                                    <th>Request</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!empty($my_missions)): ?>
                                <?php foreach ($my_missions as $mission): ?>
                                    <tr>
                                        <td><?= e(ucfirst($mission['request_type'])) ?></td>
                                        <td>
                                            <span class="pill" style="background:<?= severityColor($mission['priority']) ?>">
                                                <?= e(ucfirst($mission['priority'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="pill" style="background:<?= missionColor($mission['mission_status']) ?>">
                                                <?= e(ucwords(str_replace('_', ' ', $mission['mission_status']))) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr class="empty-row">
                                    <td colspan="3">No missions assigned yet.</td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div>
                    <div class="section-hd">
                        <h2>Active Alerts</h2>
                        <a href="alerts.php" class="see-all">
                            See all <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>

                    <div class="table-card">
                        <table>
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Location</th>
                                    <th>Severity</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!empty($recent_alerts)): ?>
                                <?php foreach ($recent_alerts as $alert): ?>
                                    <tr>
                                        <td><?= e($alert['alert_type']) ?></td>
                                        <td><?= e($alert['location_text']) ?></td>
                                        <td>
                                            <span class="pill" style="background:<?= severityColor($alert['severity']) ?>">
                                                <?= e(ucfirst($alert['severity'])) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr class="empty-row">
                                    <td colspan="3">No active alerts.</td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

        <?php else: ?>
            <section class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background:#fff1f1; color:#c62828;">
                        <i class="fa-solid fa-bell"></i>
                    </div>
                    <div class="stat-info">
                        <p>Active Alerts</p>
                        <h3><?= (int)($stats['alerts'] ?? 0) ?></h3>
                        <small>In your area</small>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background:#e3f2fd; color:#1565c0;">
                        <i class="fa-solid fa-house-chimney"></i>
                    </div>
                    <div class="stat-info">
                        <p>Open Shelters</p>
                        <h3><?= (int)($stats['shelters'] ?? 0) ?></h3>
                        <small>Available now</small>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background:#e8f5e9; color:#15803d;">
                        <i class="fa-solid fa-person-walking-arrow-right"></i>
                    </div>
                    <div class="stat-info">
                        <p>My Evacuation</p>
                        <h3 style="font-size:17px; margin-top:4px;">
                            <?= e(ucfirst(str_replace('_', ' ', $stats['my_evac'] ?? 'Not set'))) ?>
                        </h3>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background:#fff8e1; color:#ca8a04;">
                        <i class="fa-solid fa-hand-holding-heart"></i>
                    </div>
                    <div class="stat-info">
                        <p>My Help Requests</p>
                        <h3><?= (int)($stats['my_requests'] ?? 0) ?></h3>
                        <small>Pending</small>
                    </div>
                </div>

                <?php if (($stats['unread_notifs'] ?? 0) > 0): ?>
                    <div class="stat-card">
                        <div class="stat-icon" style="background:#f3e5f5; color:#6a1b9a;">
                            <i class="fa-solid fa-envelope"></i>
                        </div>
                        <div class="stat-info">
                            <p>Unread Notifications</p>
                            <h3><?= (int)$stats['unread_notifs'] ?></h3>
                        </div>
                    </div>
                <?php endif; ?>
            </section>

            <div class="section-hd">
                <h2>Quick Actions</h2>
            </div>

            <section class="action-grid">
                <a href="chatbot.php" class="action-card">
                    <div class="ac-icon" style="background:#7c3aed;">
                        <i class="fa-solid fa-robot"></i>
                    </div>
                    <span>AI Chatbot</span>
                </a>

                <a href="alerts.php" class="action-card">
                    <div class="ac-icon" style="background:#c62828;">
                        <i class="fa-solid fa-bell"></i>
                    </div>
                    <span>View Alerts</span>
                </a>
         <a href="recommend_shelter.php" class="action-card">
    <div class="ac-icon" style="background:#6a1b9a;">
        <i class="fa-solid fa-wand-magic-sparkles"></i>
    </div>
    <span>Smart Shelter</span>
</a>

                <a href="shelters.php" class="action-card">
                    <div class="ac-icon" style="background:#1565c0;">
                        <i class="fa-solid fa-house-chimney"></i>
                    </div>
                    <span>Find Shelter</span>
                </a>

                <a href="resources.php" class="action-card">
                    <div class="ac-icon" style="background:#00838f;">
                        <i class="fa-solid fa-boxes-stacked"></i>
                    </div>
                    <span>Resources</span>
                </a>

                <a href="evacuation_status.php" class="action-card">
                    <div class="ac-icon" style="background:#558b2f;">
                        <i class="fa-solid fa-person-walking-arrow-right"></i>
                    </div>
                    <span>Update Status</span>
                </a>

                <a href="chatbot.php" class="action-card">
                    <div class="ac-icon" style="background:#e65100;">
                        <i class="fa-solid fa-hand-holding-heart"></i>
                    </div>
                    <span>Request Help</span>
                </a>
            </section>

            <section class="two-col">
                <div>
                    <div class="section-hd">
                        <h2>Latest Alerts</h2>
                        <a href="alerts.php" class="see-all">
                            See all <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>

                    <div class="table-card">
                        <table>
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Severity</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!empty($recent_alerts)): ?>
                                <?php foreach ($recent_alerts as $alert): ?>
                                    <tr>
                                        <td><?= e($alert['alert_type']) ?></td>
                                        <td>
                                            <span class="pill" style="background:<?= severityColor($alert['severity']) ?>">
                                                <?= e(ucfirst($alert['severity'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= !empty($alert['published_at']) ? e(date('M j', strtotime($alert['published_at']))) : 'N/A' ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr class="empty-row">
                                    <td colspan="3">No active alerts.</td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div>
                    <div class="section-hd">
                        <h2>Safety Tips</h2>
                    </div>

                    <div class="table-card">
                        <div class="tip-item">
                            <div class="tip-ico" style="background:#fff1f1; color:#c62828;">
                                <i class="fa-solid fa-kit-medical"></i>
                            </div>
                            <div>
                                <strong>Keep an emergency kit ready</strong>
                                <p>Water, food, first-aid, torch, and phone charger.</p>
                            </div>
                        </div>

                        <div class="tip-item">
                            <div class="tip-ico" style="background:#e3f2fd; color:#1565c0;">
                                <i class="fa-solid fa-map-location-dot"></i>
                            </div>
                            <div>
                                <strong>Know your nearest shelter</strong>
                                <p>Check shelter information before a disaster strikes.</p>
                            </div>
                        </div>

                        <div class="tip-item">
                            <div class="tip-ico" style="background:#e8f5e9; color:#15803d;">
                                <i class="fa-solid fa-mobile-screen"></i>
                            </div>
                            <div>
                                <strong>Update your evacuation status</strong>
                                <p>Let rescue teams know whether you are safe.</p>
                            </div>
                        </div>

                        <div class="tip-item">
                            <div class="tip-ico" style="background:#fff8e1; color:#ca8a04;">
                                <i class="fa-solid fa-robot"></i>
                            </div>
                            <div>
                                <strong>Use the AI chatbot</strong>
                                <p>Ask for shelter, flood, cyclone, fire, medical, or rescue guidance.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
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