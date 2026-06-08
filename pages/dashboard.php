<?php
session_start();

// ── Auth guard ────────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// ── DB connection ─────────────────────────────────────────────────────────────
require_once '../DB/db.php'; // must provide $conn

if (!isset($conn) || !$conn) {
    die("Database connection failed.");
}

$user_id  = (int) ($_SESSION['user_id'] ?? 0);
$username = htmlspecialchars($_SESSION['full_name'] ?? 'User');
$role_id  = (int) ($_SESSION['role_id'] ?? 0);

// ── Convert role_id to role name ──────────────────────────────────────────────
if ($role_id == 2) {
    $role = 'admin';
} elseif ($role_id == 5) {
    $role = 'system_admin';
} elseif ($role_id == 3) {
    $role = 'rescue_team';
} elseif ($role_id == 4) {
    $role = 'government';
} else {
    $role = 'citizen';
}

$initials = strtoupper(substr($username, 0, 1));

// ── Helper functions for mysqli ───────────────────────────────────────────────
function getSingleValue($conn, $sql)
{
    $result = $conn->query($sql);
    if ($result && $row = $result->fetch_row()) {
        return $row[0];
    }
    return 0;
}

function getPreparedSingleValue($conn, $sql, $types, ...$params)
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) return 0;

    if (!empty($types) && !empty($params)) {
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

function getPreparedFirstValue($conn, $sql, $types, ...$params)
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) return '';

    if (!empty($types) && !empty($params)) {
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

function getPreparedRows($conn, $sql, $types, ...$params)
{
    $rows = [];
    $stmt = $conn->prepare($sql);
    if (!$stmt) return $rows;

    if (!empty($types) && !empty($params)) {
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

// ── Live stats ────────────────────────────────────────────────────────────────
$stats = [];
$recent_alerts = [];
$my_missions = [];

if ($role === 'admin' || $role === 'system_admin') {

    $stats['alerts']    = getSingleValue($conn, "SELECT COUNT(*) FROM disaster_alerts WHERE status='published'");
    $stats['shelters']  = getSingleValue($conn, "SELECT COUNT(*) FROM shelters WHERE status='open'");
    $stats['resources'] = getSingleValue($conn, "SELECT COUNT(*) FROM emergency_resources WHERE status='available'");
    $stats['requests']  = getSingleValue($conn, "SELECT COUNT(*) FROM emergency_requests WHERE status='pending'");
    $stats['users']     = getSingleValue($conn, "SELECT COUNT(*) FROM users WHERE is_active=1");
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
         WHERE team_user_id=? AND mission_status NOT IN ('completed','failed')",
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

} else {

    $stats['alerts']   = getSingleValue($conn, "SELECT COUNT(*) FROM disaster_alerts WHERE status='published'");
    $stats['shelters'] = getSingleValue($conn, "SELECT COUNT(*) FROM shelters WHERE status='open'");

    $my_evac = getPreparedFirstValue(
        $conn,
        "SELECT status FROM evacuation_status WHERE user_id=? ORDER BY updated_at DESC LIMIT 1",
        "i",
        $user_id
    );
    $stats['my_evac'] = !empty($my_evac) ? $my_evac : 'Not set';

    $stats['my_requests'] = getPreparedSingleValue(
        $conn,
        "SELECT COUNT(*) FROM emergency_requests WHERE created_by=? AND status='pending'",
        "i",
        $user_id
    );

    $stats['unread_notifs'] = getPreparedSingleValue(
        $conn,
        "SELECT COUNT(*) FROM alert_notifications WHERE user_id=? AND is_read=0",
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

// ── Helpers ───────────────────────────────────────────────────────────────────
$role_labels = [
    'admin'        => 'Administrator',
    'system_admin' => 'System Admin',
    'rescue_team'  => 'Rescue Team',
    'citizen'      => 'Citizen',
    'government'   => 'Government',
];
$role_label = $role_labels[$role] ?? ucfirst($role);

function severityColor($s) {
    switch (strtolower((string)$s)) {
        case 'critical': return '#c62828';
        case 'high':     return '#e65100';
        case 'medium':   return '#f9a825';
        case 'low':      return '#2e7d32';
        default:         return '#555';
    }
}

function missionColor($s) {
    switch (strtolower((string)$s)) {
        case 'completed':   return '#2e7d32';
        case 'in_progress': return '#1565c0';
        case 'en_route':    return '#00838f';
        case 'assigned':    return '#f9a825';
        case 'failed':      return '#c62828';
        default:            return '#555';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Dashboard — ResQLink</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<style>
*, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }

:root {
    --accent:     #c62828;
    --accent-dk:  #8e0000;
    --accent-lt:  #ffebee;
    --sidebar-w:  265px;
    --topbar-h:   66px;
    --bg:         #f0f2f5;
    --white:      #ffffff;
    --text:       #1a1a2e;
    --muted:      #6b7280;
    --border:     #e5e7eb;
    --shadow-sm:  0 1px 4px rgba(0,0,0,.07);
    --shadow:     0 4px 16px rgba(0,0,0,.10);
    --radius:     14px;
}
<?php if ($role === 'rescue_team'): ?>
:root { --accent:#1565C0; --accent-dk:#0d47a1; --accent-lt:#e3f2fd; }
<?php elseif ($role === 'citizen'): ?>
:root { --accent:#2e7d32; --accent-dk:#1b5e20; --accent-lt:#e8f5e9; }
<?php endif; ?>

body { font-family:'Plus Jakarta Sans',sans-serif; background:var(--bg); color:var(--text); display:flex; min-height:100vh; }

/* ═══ SIDEBAR ═══ */
.sidebar {
    width:var(--sidebar-w); background:var(--white);
    border-right:1px solid var(--border);
    display:flex; flex-direction:column;
    position:fixed; top:0; left:0; height:100vh;
    z-index:200; transition:transform .28s ease;
}
.sidebar-brand {
    display:flex; align-items:center; gap:11px;
    padding:20px 22px; border-bottom:1px solid var(--border);
    text-decoration:none;
}
.brand-icon {
    width:40px; height:40px; background:var(--accent);
    border-radius:11px; display:grid; place-items:center;
    color:#fff; font-size:18px; flex-shrink:0;
}
.brand-name { font-size:18px; font-weight:800; color:var(--text); }
.brand-name span { color:var(--accent); }

.sidebar-nav { flex:1; padding:16px 12px; overflow-y:auto; }
.nav-label {
    font-size:10px; font-weight:700; letter-spacing:.09em;
    text-transform:uppercase; color:var(--muted);
    padding:4px 12px 8px; display:block;
}
.nav-link {
    display:flex; align-items:center; gap:13px;
    padding:10px 14px; border-radius:10px;
    color:var(--muted); font-size:14px; font-weight:500;
    text-decoration:none; margin-bottom:2px;
    transition:background .18s, color .18s;
}
.nav-link i { width:18px; text-align:center; font-size:15px; flex-shrink:0; }
.nav-link:hover { background:var(--accent-lt); color:var(--accent); }
.nav-link.active { background:var(--accent-lt); color:var(--accent); font-weight:700; }
.nav-link .badge {
    margin-left:auto; background:var(--accent); color:#fff;
    font-size:10px; font-weight:700; padding:2px 7px;
    border-radius:20px; min-width:20px; text-align:center;
}
.sidebar-footer { padding:14px 12px; border-top:1px solid var(--border); }
.nav-link.logout { color:#dc2626; }
.nav-link.logout:hover { background:#fff1f1; }

/* ═══ MAIN ═══ */
.main { margin-left:var(--sidebar-w); flex:1; display:flex; flex-direction:column; }

/* ── Topbar ── */
.topbar {
    height:var(--topbar-h); background:var(--white);
    border-bottom:1px solid var(--border);
    display:flex; align-items:center; justify-content:space-between;
    padding:0 28px; position:sticky; top:0; z-index:100;
}
.topbar-title h1 { font-size:16px; font-weight:700; }
.topbar-title p  { font-size:12px; color:var(--muted); margin-top:1px; }
.topbar-right { display:flex; align-items:center; gap:12px; }
.icon-btn {
    width:38px; height:38px; border-radius:9px;
    border:1px solid var(--border); background:var(--white);
    display:grid; place-items:center; color:var(--muted);
    font-size:14px; cursor:pointer; position:relative;
    transition:all .18s; text-decoration:none;
}
.icon-btn:hover { background:var(--accent-lt); color:var(--accent); border-color:var(--accent); }
.badge-dot {
    position:absolute; top:7px; right:7px;
    width:7px; height:7px; background:#c62828;
    border-radius:50%; border:1.5px solid #fff;
}
.user-chip {
    display:flex; align-items:center; gap:9px;
    padding:5px 13px 5px 5px; border:1px solid var(--border);
    border-radius:50px; cursor:pointer; transition:background .18s;
}
.user-chip:hover { background:var(--accent-lt); }
.avatar {
    width:32px; height:32px; border-radius:50%;
    background:var(--accent); color:#fff;
    font-size:13px; font-weight:800;
    display:grid; place-items:center; flex-shrink:0;
}
.user-chip-name { font-size:13px; font-weight:700; }
.user-chip-role { font-size:11px; color:var(--muted); }
.hamburger { display:none; background:none; border:none; font-size:20px; color:var(--text); cursor:pointer; padding:4px; }

/* ═══ CONTENT ═══ */
.content { padding:28px; flex:1; }

/* ── Banner ── */
.welcome-banner {
    background:linear-gradient(135deg, var(--accent-dk) 0%, var(--accent) 60%, #ef5350 100%);
    border-radius:var(--radius); padding:28px 32px;
    display:flex; align-items:center; justify-content:space-between;
    position:relative; overflow:hidden; margin-bottom:22px;
}
<?php if ($role === 'rescue_team'): ?>
.welcome-banner { background:linear-gradient(135deg,#0d47a1 0%,#1565C0 60%,#1976d2 100%); }
<?php elseif ($role === 'citizen'): ?>
.welcome-banner { background:linear-gradient(135deg,#1b5e20 0%,#2e7d32 60%,#388e3c 100%); }
<?php endif; ?>
.wb-orb1,.wb-orb2 { position:absolute; border-radius:50%; background:rgba(255,255,255,.07); pointer-events:none; }
.wb-orb1 { width:220px; height:220px; top:-60px; right:200px; }
.wb-orb2 { width:300px; height:300px; bottom:-90px; right:-40px; }
.wb-text { position:relative; z-index:1; }
.wb-badge {
    display:inline-flex; align-items:center; gap:6px;
    background:rgba(255,255,255,.2); color:#fff;
    font-size:11px; font-weight:700; letter-spacing:.06em;
    text-transform:uppercase; padding:4px 13px; border-radius:20px; margin-bottom:10px;
}
.wb-text h2 { font-size:24px; font-weight:800; color:#fff; margin-bottom:6px; }
.wb-text p  { font-size:13.5px; color:rgba(255,255,255,.82); max-width:440px; }
.wb-actions { position:relative; z-index:1; display:flex; flex-direction:column; gap:10px; align-items:flex-end; }
.wb-btn {
    display:inline-flex; align-items:center; gap:8px;
    background:#fff; color:var(--accent); border:none; border-radius:50px;
    padding:11px 22px; font-size:13.5px; font-weight:700;
    cursor:pointer; text-decoration:none;
    transition:transform .2s, box-shadow .2s; white-space:nowrap;
}
.wb-btn:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(0,0,0,.22); }
.wb-btn.outline {
    background:transparent; color:#fff;
    border:2px solid rgba(255,255,255,.55);
}
.wb-btn.outline:hover { background:rgba(255,255,255,.15); }

/* ── Stats ── */
.stats-grid {
    display:grid; grid-template-columns:repeat(auto-fill,minmax(188px,1fr));
    gap:14px; margin-bottom:24px;
}
.stat-card {
    background:var(--white); border-radius:var(--radius);
    padding:20px 22px; border:1px solid var(--border);
    box-shadow:var(--shadow-sm);
    display:flex; align-items:center; gap:16px;
    transition:transform .2s, box-shadow .2s;
}
.stat-card:hover { transform:translateY(-3px); box-shadow:var(--shadow); }
.stat-icon { width:50px; height:50px; border-radius:13px; display:grid; place-items:center; font-size:20px; flex-shrink:0; }
.stat-info p   { font-size:12px; color:var(--muted); margin-bottom:3px; font-weight:500; }
.stat-info h3  { font-size:26px; font-weight:800; line-height:1; }
.stat-info small { font-size:11px; color:var(--muted); margin-top:3px; display:block; font-style:italic; }

/* ── Section header ── */
.section-hd { display:flex; align-items:center; justify-content:space-between; margin-bottom:14px; }
.section-hd h2 { font-size:15px; font-weight:700; }
.see-all { font-size:13px; color:var(--accent); font-weight:600; text-decoration:none; display:flex; align-items:center; gap:5px; }
.see-all:hover { text-decoration:underline; }

/* ── Action cards ── */
.action-grid {
    display:grid; grid-template-columns:repeat(auto-fill,minmax(150px,1fr));
    gap:13px; margin-bottom:26px;
}
.action-card {
    background:var(--white); border-radius:var(--radius);
    padding:26px 14px 22px; text-align:center;
    text-decoration:none; color:var(--text);
    border:1px solid var(--border); box-shadow:var(--shadow-sm);
    display:flex; flex-direction:column; align-items:center; gap:12px;
    transition:transform .2s, box-shadow .2s;
}
.action-card:hover { transform:translateY(-4px); box-shadow:var(--shadow); }
.ac-icon { width:52px; height:52px; border-radius:50%; display:grid; place-items:center; font-size:20px; color:#fff; }
.action-card span { font-size:13px; font-weight:600; }

/* ── Two col ── */
.two-col { display:grid; grid-template-columns:1fr 1fr; gap:18px; margin-bottom:22px; }

/* ── Table card ── */
.table-card {
    background:var(--white); border-radius:var(--radius);
    border:1px solid var(--border); box-shadow:var(--shadow-sm); overflow:hidden;
}
.table-card table { width:100%; border-collapse:collapse; }
.table-card td, .table-card th { padding:11px 18px; text-align:left; font-size:13px; border-bottom:1px solid var(--border); }
.table-card th { font-weight:700; color:var(--muted); font-size:11px; text-transform:uppercase; letter-spacing:.05em; background:#fafafa; }
.table-card tr:last-child td { border-bottom:none; }
.table-card tr:hover td { background:#fafafa; }
.pill { display:inline-block; padding:3px 10px; border-radius:20px; font-size:11.5px; font-weight:700; color:#fff; }
.empty-row td { text-align:center; color:var(--muted); padding:24px; font-style:italic; }

/* ── Safety tip ── */
.tip-item { display:flex; gap:12px; align-items:flex-start; padding:16px 18px; border-bottom:1px solid var(--border); }
.tip-item:last-child { border-bottom:none; }
.tip-ico { width:34px; height:34px; border-radius:9px; display:grid; place-items:center; flex-shrink:0; font-size:14px; }
.tip-item strong { font-size:13px; display:block; margin-bottom:2px; }
.tip-item p { font-size:12px; color:var(--muted); }

/* ── Mobile overlay ── */
.sidebar-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:199; pointer-events:none; opacity:0; transition:opacity .28s; }
.sidebar-overlay.open { display:block; opacity:1; pointer-events:all; }

@media(max-width:768px) {
    .sidebar { transform:translateX(-100%); }
    .sidebar.open { transform:translateX(0); }
    .main { margin-left:0; }
    .hamburger { display:block; }
    .welcome-banner { flex-direction:column; gap:18px; }
    .wb-actions { align-items:flex-start; flex-direction:row; flex-wrap:wrap; }
    .content { padding:16px; }
    .topbar { padding:0 16px; }
    .two-col { grid-template-columns:1fr; }
    .user-chip-role { display:none; }
}
</style>
</head>
<body>

<div class="sidebar-overlay" id="overlay" onclick="closeSidebar()"></div>

<!-- ═══════════════ SIDEBAR ═══════════════ -->
<aside class="sidebar" id="sidebar">
    <a href="dashboard.php" class="sidebar-brand">
        <div class="brand-icon"><i class="fa-solid fa-shield-halved"></i></div>
        <span class="brand-name">ResQ<span>Link</span></span>
    </a>

    <nav class="sidebar-nav">
        <span class="nav-label">Main Menu</span>
        <a href="dashboard.php" class="nav-link active">
            <i class="fa-solid fa-gauge-high"></i> Dashboard
        </a>

        <?php if ($role === 'admin' || $role === 'system_admin'): ?>

            <span class="nav-label" style="margin-top:10px">Alerts</span>
            <a href="alerts.php" class="nav-link">
                <i class="fa-solid fa-bell"></i> View Alerts
                <?php if (($stats['alerts'] ?? 0) > 0): ?><span class="badge"><?= $stats['alerts'] ?></span><?php endif; ?>
            </a>
            <a href="admin/create_alert.php" class="nav-link">
                <i class="fa-solid fa-circle-plus"></i> Create Alert
            </a>
            <!-- Emergency Alert Generator feature start -->
            <a href="admin/ai_alert_generator.php" class="nav-link">
                <i class="fa-solid fa-robot"></i> AI Emergency Alert Generator
            </a>
            <!-- Emergency Alert Generator feature end -->

            <span class="nav-label" style="margin-top:10px">Management</span>
            <a href="admin/manage_shelters.php" class="nav-link">
                <i class="fa-solid fa-house-chimney"></i> Manage Shelters
            </a>
            <a href="admin/manage_resources.php" class="nav-link">
                <i class="fa-solid fa-boxes-stacked"></i> Manage Resources
            </a>
            <a href="admin/manage_evacuation.php" class="nav-link">
                <i class="fa-solid fa-person-walking-arrow-right"></i> Manage Evacuation
            </a>

        <?php elseif ($role === 'rescue_team'): ?>

            <span class="nav-label" style="margin-top:10px">Operations</span>
            <a href="alerts.php" class="nav-link">
                <i class="fa-solid fa-bell"></i> Active Alerts
                <?php if (($stats['alerts'] ?? 0) > 0): ?><span class="badge"><?= $stats['alerts'] ?></span><?php endif; ?>
            </a>
            <a href="#" class="nav-link">
                <i class="fa-solid fa-person-rifle"></i> My Missions
                <?php if (($stats['my_missions'] ?? 0) > 0): ?><span class="badge"><?= $stats['my_missions'] ?></span><?php endif; ?>
            </a>
            <a href="#" class="nav-link">
                <i class="fa-solid fa-triangle-exclamation"></i> Emergency Requests
                <?php if (($stats['pending_requests'] ?? 0) > 0): ?><span class="badge"><?= $stats['pending_requests'] ?></span><?php endif; ?>
            </a>

            <span class="nav-label" style="margin-top:10px">Resources</span>
            <a href="shelters.php" class="nav-link">
                <i class="fa-solid fa-house-chimney"></i> Shelters
            </a>
            <a href="resources.php" class="nav-link">
                <i class="fa-solid fa-boxes-stacked"></i> Resources
            </a>

        <?php else: ?>

            <span class="nav-label" style="margin-top:10px">Disaster Info</span>
            <a href="alerts.php" class="nav-link">
                <i class="fa-solid fa-bell"></i> Alerts
                <?php if (($stats['unread_notifs'] ?? 0) > 0): ?><span class="badge"><?= $stats['unread_notifs'] ?></span><?php endif; ?>
            </a>
            <a href="shelters.php" class="nav-link">
                <i class="fa-solid fa-house-chimney"></i> Find Shelter
            </a>
            <a href="resources.php" class="nav-link">
                <i class="fa-solid fa-boxes-stacked"></i> Resources
            </a>

            <span class="nav-label" style="margin-top:10px">My Status</span>
            <a href="evacuation_status.php" class="nav-link">
                <i class="fa-solid fa-person-walking-arrow-right"></i> Evacuation Status
            </a>
            <a href="#" class="nav-link">
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

<!-- ═══════════════ MAIN ═══════════════ -->
<div class="main">

    <header class="topbar">
        <div style="display:flex;align-items:center;gap:12px;">
            <button class="hamburger" onclick="openSidebar()"><i class="fa-solid fa-bars"></i></button>
            <div class="topbar-title">
                <h1>Dashboard</h1>
                <p><?= date('l, F j, Y') ?></p>
            </div>
        </div>
        <div class="topbar-right">
            <a href="alerts.php" class="icon-btn" title="Alerts">
                <i class="fa-solid fa-bell"></i>
                <?php if (($stats['alerts'] ?? 0) > 0): ?><span class="badge-dot"></span><?php endif; ?>
            </a>
            <div class="user-chip">
                <div class="avatar"><?= $initials ?></div>
                <div>
                    <div class="user-chip-name"><?= $username ?></div>
                    <div class="user-chip-role"><?= $role_label ?></div>
                </div>
            </div>
        </div>
    </header>

    <main class="content">

        <!-- ── WELCOME BANNER ── -->
        <div class="welcome-banner">
            <div class="wb-orb1"></div><div class="wb-orb2"></div>
            <div class="wb-text">
                <div class="wb-badge">
                    <i class="fa-solid fa-circle-dot" style="font-size:9px;"></i>
                    <?= $role_label ?> Dashboard
                </div>
                <h2>Welcome back, <?= $username ?>!</h2>
                <p>
                    <?php if ($role === 'admin' || $role === 'system_admin'): ?>
                        Oversee disaster alerts, shelters, resources, and evacuation operations across the system.
                    <?php elseif ($role === 'rescue_team'): ?>
                        Coordinate active rescue missions, monitor emergency requests, and track field operations.
                    <?php else: ?>
                        Stay informed about active alerts, locate nearby shelters, and update your evacuation status.
                    <?php endif; ?>
                </p>
            </div>
            <div class="wb-actions">
                <?php if ($role === 'admin' || $role === 'system_admin'): ?>
                    <a href="admin/create_alert.php" class="wb-btn"><i class="fa-solid fa-circle-plus"></i> Create Alert</a>
                    <a href="admin/manage_shelters.php" class="wb-btn outline"><i class="fa-solid fa-house-chimney"></i> Manage Shelters</a>
                <?php elseif ($role === 'rescue_team'): ?>
                    <a href="#" class="wb-btn"><i class="fa-solid fa-person-rifle"></i> My Missions</a>
                    <a href="alerts.php" class="wb-btn outline"><i class="fa-solid fa-bell"></i> Active Alerts</a>
                <?php else: ?>
                    <a href="alerts.php" class="wb-btn"><i class="fa-solid fa-bell"></i> View Alerts</a>
                    <a href="evacuation_status.php" class="wb-btn outline"><i class="fa-solid fa-person-walking-arrow-right"></i> My Status</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- ADMIN CONTENT -->
        <?php if ($role === 'admin' || $role === 'system_admin'): ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background:#fff1f1;color:#c62828;"><i class="fa-solid fa-bell"></i></div>
                <div class="stat-info"><p>Active Alerts</p><h3><?= $stats['alerts'] ?? 0 ?></h3></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#e3f2fd;color:#1565C0;"><i class="fa-solid fa-house-chimney"></i></div>
                <div class="stat-info"><p>Open Shelters</p><h3><?= $stats['shelters'] ?? 0 ?></h3></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#e0f7fa;color:#00838F;"><i class="fa-solid fa-boxes-stacked"></i></div>
                <div class="stat-info"><p>Resources Available</p><h3><?= $stats['resources'] ?? 0 ?></h3></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#fff8e1;color:#f9a825;"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <div class="stat-info"><p>Pending Requests</p><h3><?= $stats['requests'] ?? 0 ?></h3></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#f3e5f5;color:#6a1b9a;"><i class="fa-solid fa-users"></i></div>
                <div class="stat-info"><p>Registered Users</p><h3><?= $stats['users'] ?? 0 ?></h3></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#e8f5e9;color:#2e7d32;"><i class="fa-solid fa-person-walking-arrow-right"></i></div>
                <div class="stat-info"><p>Evacuated</p><h3><?= $stats['evacuated'] ?? 0 ?></h3></div>
            </div>
        </div>

        <div class="section-hd"><h2>Quick Actions</h2></div>
        <div class="action-grid">
            <a href="alerts.php" class="action-card">
                <div class="ac-icon" style="background:#c62828;"><i class="fa-solid fa-bell"></i></div><span>View Alerts</span>
            </a>
            <a href="admin/create_alert.php" class="action-card">
                <div class="ac-icon" style="background:#222;"><i class="fa-solid fa-circle-plus"></i></div><span>Create Alert</span>
            </a>
            <!-- Emergency Alert Generator feature start -->
            <a href="admin/ai_alert_generator.php" class="action-card">
                <div class="ac-icon" style="background:#667eea;"><i class="fa-solid fa-robot"></i></div><span>Generate Emergency Alert</span>
            </a>
            <a href="admin/ai_alerts_list.php" class="action-card">
                <div class="ac-icon" style="background:#764ba2;"><i class="fa-solid fa-list-check"></i></div><span>View AI Alerts</span>
            </a>
            <!-- Emergency Alert Generator feature end -->
            <a href="admin/manage_shelters.php" class="action-card">
                <div class="ac-icon" style="background:#1565C0;"><i class="fa-solid fa-house-chimney"></i></div><span>Manage Shelters</span>
            </a>
            <a href="admin/manage_resources.php" class="action-card">
                <div class="ac-icon" style="background:#00838F;"><i class="fa-solid fa-boxes-stacked"></i></div><span>Manage Resources</span>
            </a>
            <a href="admin/manage_evacuation.php" class="action-card">
                <div class="ac-icon" style="background:#558B2F;"><i class="fa-solid fa-person-walking-arrow-right"></i></div><span>Manage Evacuation</span>
            </a>
        </div>

        <div class="section-hd">
            <h2>Recent Published Alerts</h2>
            <a href="alerts.php" class="see-all">See all <i class="fa-solid fa-arrow-right"></i></a>
        </div>
        <div class="table-card">
            <table>
                <thead><tr><th>Type</th><th>Location</th><th>Severity</th><th>Published</th></tr></thead>
                <tbody>
                    <?php if (!empty($recent_alerts)): foreach ($recent_alerts as $a): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($a['alert_type']) ?></strong></td>
                        <td><?= htmlspecialchars($a['location_text']) ?></td>
                        <td><span class="pill" style="background:<?= severityColor($a['severity']) ?>"><?= ucfirst($a['severity']) ?></span></td>
                        <td><?= !empty($a['published_at']) ? date('M j, H:i', strtotime($a['published_at'])) : '—' ?></td>
                    </tr>
                    <?php endforeach; else: ?>
                        <tr class="empty-row"><td colspan="4">No active alerts at the moment.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- RESCUE TEAM CONTENT -->
        <?php elseif ($role === 'rescue_team'): ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background:#fff1f1;color:#c62828;"><i class="fa-solid fa-bell"></i></div>
                <div class="stat-info"><p>Active Alerts</p><h3><?= $stats['alerts'] ?? 0 ?></h3></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#e3f2fd;color:#1565C0;"><i class="fa-solid fa-person-rifle"></i></div>
                <div class="stat-info"><p>My Active Missions</p><h3><?= $stats['my_missions'] ?? 0 ?></h3></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#fff8e1;color:#f9a825;"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <div class="stat-info"><p>Pending Requests</p><h3><?= $stats['pending_requests'] ?? 0 ?></h3></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#e0f2f1;color:#00695c;"><i class="fa-solid fa-house-chimney"></i></div>
                <div class="stat-info"><p>Open Shelters</p><h3><?= $stats['shelters'] ?? 0 ?></h3></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#e0f7fa;color:#00838F;"><i class="fa-solid fa-boxes-stacked"></i></div>
                <div class="stat-info"><p>Available Resources</p><h3><?= $stats['resources'] ?? 0 ?></h3></div>
            </div>
        </div>

        <div class="section-hd"><h2>Quick Actions</h2></div>
        <div class="action-grid">
            <a href="alerts.php" class="action-card">
                <div class="ac-icon" style="background:#c62828;"><i class="fa-solid fa-bell"></i></div><span>Active Alerts</span>
            </a>
            <a href="#" class="action-card">
                <div class="ac-icon" style="background:#1565C0;"><i class="fa-solid fa-person-rifle"></i></div><span>My Missions</span>
            </a>
            <a href="#" class="action-card">
                <div class="ac-icon" style="background:#e65100;"><i class="fa-solid fa-triangle-exclamation"></i></div><span>View Requests</span>
            </a>
            <a href="shelters.php" class="action-card">
                <div class="ac-icon" style="background:#00838F;"><i class="fa-solid fa-house-chimney"></i></div><span>Shelters</span>
            </a>
            <a href="resources.php" class="action-card">
                <div class="ac-icon" style="background:#558B2F;"><i class="fa-solid fa-boxes-stacked"></i></div><span>Resources</span>
            </a>
        </div>

        <div class="two-col">
            <div>
                <div class="section-hd"><h2>My Recent Missions</h2></div>
                <div class="table-card">
                    <table>
                        <thead><tr><th>Request Type</th><th>Priority</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php if (!empty($my_missions)): foreach ($my_missions as $m): ?>
                            <tr>
                                <td><?= htmlspecialchars(ucfirst($m['request_type'])) ?></td>
                                <td><span class="pill" style="background:<?= severityColor($m['priority']) ?>"><?= ucfirst($m['priority']) ?></span></td>
                                <td><span class="pill" style="background:<?= missionColor($m['mission_status']) ?>"><?= ucwords(str_replace('_',' ',$m['mission_status'])) ?></span></td>
                            </tr>
                            <?php endforeach; else: ?>
                                <tr class="empty-row"><td colspan="3">No missions assigned yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div>
                <div class="section-hd">
                    <h2>Active Alerts</h2>
                    <a href="alerts.php" class="see-all">See all <i class="fa-solid fa-arrow-right"></i></a>
                </div>
                <div class="table-card">
                    <table>
                        <thead><tr><th>Type</th><th>Location</th><th>Severity</th></tr></thead>
                        <tbody>
                            <?php
                            $ar = getRows($conn, "
                                SELECT alert_type, severity, location_text
                                FROM disaster_alerts
                                WHERE status='published'
                                ORDER BY published_at DESC
                                LIMIT 5
                            ");
                            if (!empty($ar)): foreach ($ar as $a): ?>
                            <tr>
                                <td><?= htmlspecialchars($a['alert_type']) ?></td>
                                <td><?= htmlspecialchars($a['location_text']) ?></td>
                                <td><span class="pill" style="background:<?= severityColor($a['severity']) ?>"><?= ucfirst($a['severity']) ?></span></td>
                            </tr>
                            <?php endforeach; else: ?>
                                <tr class="empty-row"><td colspan="3">No active alerts.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- CITIZEN CONTENT -->
        <?php else: ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background:#fff1f1;color:#c62828;"><i class="fa-solid fa-bell"></i></div>
                <div class="stat-info"><p>Active Alerts</p><h3><?= $stats['alerts'] ?? 0 ?></h3><small>In your area</small></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#e3f2fd;color:#1565C0;"><i class="fa-solid fa-house-chimney"></i></div>
                <div class="stat-info"><p>Open Shelters</p><h3><?= $stats['shelters'] ?? 0 ?></h3><small>Available now</small></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#e8f5e9;color:#2e7d32;"><i class="fa-solid fa-person-walking-arrow-right"></i></div>
                <div class="stat-info"><p>My Evacuation</p><h3 style="font-size:17px;margin-top:4px;"><?= ucfirst(str_replace('_',' ',$stats['my_evac'] ?? 'Not set')) ?></h3></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#fff8e1;color:#f9a825;"><i class="fa-solid fa-hand-holding-heart"></i></div>
                <div class="stat-info"><p>My Help Requests</p><h3><?= $stats['my_requests'] ?? 0 ?></h3><small>Pending</small></div>
            </div>
            <?php if (($stats['unread_notifs'] ?? 0) > 0): ?>
            <div class="stat-card">
                <div class="stat-icon" style="background:#f3e5f5;color:#6a1b9a;"><i class="fa-solid fa-envelope"></i></div>
                <div class="stat-info"><p>Unread Notifications</p><h3><?= $stats['unread_notifs'] ?></h3></div>
            </div>
            <?php endif; ?>
        </div>

        <div class="section-hd"><h2>Quick Actions</h2></div>
        <div class="action-grid">
            <a href="alerts.php" class="action-card">
                <div class="ac-icon" style="background:#c62828;"><i class="fa-solid fa-bell"></i></div><span>View Alerts</span>
            </a>
            <a href="shelters.php" class="action-card">
                <div class="ac-icon" style="background:#1565C0;"><i class="fa-solid fa-house-chimney"></i></div><span>Find Shelter</span>
            </a>
            <a href="resources.php" class="action-card">
                <div class="ac-icon" style="background:#00838F;"><i class="fa-solid fa-boxes-stacked"></i></div><span>Resources</span>
            </a>
            <a href="evacuation_status.php" class="action-card">
                <div class="ac-icon" style="background:#558B2F;"><i class="fa-solid fa-person-walking-arrow-right"></i></div><span>Update Status</span>
            </a>
            <a href="#" class="action-card">
                <div class="ac-icon" style="background:#e65100;"><i class="fa-solid fa-hand-holding-heart"></i></div><span>Request Help</span>
            </a>
        </div>

        <div class="two-col">
            <div>
                <div class="section-hd">
                    <h2>Latest Alerts</h2>
                    <a href="alerts.php" class="see-all">See all <i class="fa-solid fa-arrow-right"></i></a>
                </div>
                <div class="table-card">
                    <table>
                        <thead><tr><th>Type</th><th>Severity</th><th>Date</th></tr></thead>
                        <tbody>
                            <?php if (!empty($recent_alerts)): foreach ($recent_alerts as $a): ?>
                            <tr>
                                <td><?= htmlspecialchars($a['alert_type']) ?></td>
                                <td><span class="pill" style="background:<?= severityColor($a['severity']) ?>"><?= ucfirst($a['severity']) ?></span></td>
                                <td><?= !empty($a['published_at']) ? date('M j', strtotime($a['published_at'])) : '—' ?></td>
                            </tr>
                            <?php endforeach; else: ?>
                                <tr class="empty-row"><td colspan="3">No active alerts.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div>
                <div class="section-hd"><h2>Safety Tips</h2></div>
                <div class="table-card">
                    <div class="tip-item">
                        <div class="tip-ico" style="background:#fff1f1;color:#c62828;"><i class="fa-solid fa-kit-medical"></i></div>
                        <div><strong>Keep an emergency kit ready</strong><p>Water, food, first-aid, torch & phone charger.</p></div>
                    </div>
                    <div class="tip-item">
                        <div class="tip-ico" style="background:#e3f2fd;color:#1565C0;"><i class="fa-solid fa-map-location-dot"></i></div>
                        <div><strong>Know your nearest shelter</strong><p>Check the shelter map before a disaster strikes.</p></div>
                    </div>
                    <div class="tip-item">
                        <div class="tip-ico" style="background:#e8f5e9;color:#2e7d32;"><i class="fa-solid fa-mobile-screen"></i></div>
                        <div><strong>Update your evacuation status</strong><p>Let rescue teams know you are safe.</p></div>
                    </div>
                    <div class="tip-item">
                        <div class="tip-ico" style="background:#fff8e1;color:#f9a825;"><i class="fa-solid fa-walkie-talkie"></i></div>
                        <div><strong>Follow official instructions</strong><p>Only trust alerts posted on ResQLink.</p></div>
                    </div>
                </div>
            </div>
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