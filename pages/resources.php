<?php
session_start();
require_once __DIR__ . "/../DB/db.php";

// Public emergency info: viewing emergency resources must NOT require login.
// Logged-in users still get their personalised shell; guests get safe defaults.
$is_guest = !isset($_SESSION['user_id']);

$user_id = (int)($_SESSION['user_id'] ?? 0);

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

// Edit/Delete actions only for admin roles
$can_manage = ($role === 'admin' || $role === 'system_admin');

// Unread alerts count for sidebar badge
$unread_count = 0;
$uc = $conn->query("SELECT COUNT(*) AS c FROM alert_notifications WHERE user_id = $user_id AND is_read = 0");
if ($uc && $ucr = $uc->fetch_assoc()) {
    $unread_count = (int)$ucr['c'];
}

$result = $conn->query("SELECT * FROM emergency_resources ORDER BY id DESC");

function getResourceIcon($resourceType)
{
    $type = strtolower(trim($resourceType));

    if (strpos($type, 'water') !== false) {
        return 'fa-solid fa-bottle-water';
    } elseif (strpos($type, 'food') !== false) {
        return 'fa-solid fa-bowl-food';
    } elseif (strpos($type, 'medic') !== false || strpos($type, 'health') !== false) {
        return 'fa-solid fa-kit-medical';
    } elseif (strpos($type, 'cloth') !== false || strpos($type, 'blanket') !== false) {
        return 'fa-solid fa-shirt';
    } elseif (strpos($type, 'fuel') !== false) {
        return 'fa-solid fa-gas-pump';
    } elseif (strpos($type, 'tent') !== false || strpos($type, 'shelter') !== false) {
        return 'fa-solid fa-tent';
    } elseif (strpos($type, 'vehicle') !== false || strpos($type, 'transport') !== false) {
        return 'fa-solid fa-truck';
    } else {
        return 'fa-solid fa-boxes-stacked';
    }
}

function getResourceStatusClass($status)
{
    $status = strtolower(trim($status));

    if (strpos($status, 'available') !== false) {
        return 'st-open';
    } elseif (strpos($status, 'low') !== false) {
        return 'st-low';
    } elseif (strpos($status, 'out') !== false || strpos($status, 'unavailable') !== false || strpos($status, 'depleted') !== false) {
        return 'st-full';
    } else {
        return 'st-closed';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resources - ResQLink</title>

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

        /* ---------- Resource Cards ---------- */
        .resources-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 18px;
        }

        .resource-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-top: 4px solid var(--accent);
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: 0 1px 4px rgba(0, 0, 0, .06);
            transition: transform .2s, box-shadow .2s;
            display: flex;
            flex-direction: column;
        }

        .resource-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow);
        }

        .resource-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 16px;
        }

        .resource-name {
            font-size: 16px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .resource-name .rs-ico {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: var(--accent-light);
            color: var(--accent);
            display: grid;
            place-items: center;
            font-size: 18px;
            flex-shrink: 0;
        }

        .resource-name .rn-text small {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .04em;
            margin-top: 2px;
        }

        .status-badge {
            text-transform: uppercase;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .05em;
            padding: 5px 11px;
            border-radius: 20px;
            color: #fff;
            white-space: nowrap;
        }

        .st-open { background: #15803d; }
        .st-low { background: #ca8a04; }
        .st-full { background: #b91c1c; }
        .st-closed { background: #6b7280; }

        .resource-info {
            display: flex;
            flex-direction: column;
            gap: 9px;
        }

        .info-row {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
        }

        .info-row i {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            background: #f3f4f6;
            color: var(--muted);
            display: grid;
            place-items: center;
            flex-shrink: 0;
        }

        .info-row .label { color: var(--muted); font-weight: 600; }
        .info-row .value { font-weight: 700; margin-left: auto; }

        .qty-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 14px;
            padding: 8px 16px;
            border-radius: 12px;
            background: var(--accent-light);
            color: var(--accent-dark);
            font-size: 15px;
            font-weight: 800;
            align-self: flex-start;
        }

        .card-actions {
            display: flex;
            gap: 8px;
            margin-top: 16px;
            padding-top: 14px;
            border-top: 1px solid var(--border);
        }

        .btn-edit, .btn-del {
            flex: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 9px;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
        }

        .btn-edit { background: #fff8e1; color: #ca8a04; border: 1px solid #fde68a; }
        .btn-edit:hover { background: #ca8a04; color: #fff; }

        .btn-del { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .btn-del:hover { background: #dc2626; color: #fff; }

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
            .resources-grid { grid-template-columns: 1fr; }
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

        <a href="resources.php" class="nav-link active">
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
        <?php if ($is_guest): ?>
        <a href="login.php" class="nav-link logout">
            <i class="fa-solid fa-right-to-bracket"></i> Login
        </a>
        <?php else: ?>
        <a href="logout.php" class="nav-link logout">
            <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
        </a>
        <?php endif; ?>
    </div>
</aside>

<div class="main">
    <header class="topbar">
        <div style="display:flex; align-items:center; gap:12px;">
            <button class="hamburger" onclick="openSidebar()">
                <i class="fa-solid fa-bars"></i>
            </button>

            <div class="topbar-title">
                <h1>Resources</h1>
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
                <span class="ph-icon"><i class="fa-solid fa-boxes-stacked"></i></span>
                Emergency Resources
            </h2>
            <a href="dashboard.php" class="back-btn">
                <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <?php if ($result && $result->num_rows > 0): ?>
            <div class="resources-grid">
                <?php while ($row = $result->fetch_assoc()): ?>
                    <?php
                        $resIcon = getResourceIcon($row['resource_type']);
                        $statusClass = getResourceStatusClass($row['status']);
                    ?>
                    <div class="resource-card">
                        <div class="resource-head">
                            <div class="resource-name">
                                <span class="rs-ico"><i class="<?php echo $resIcon; ?>"></i></span>
                                <span class="rn-text">
                                    <?php echo htmlspecialchars($row['resource_name']); ?>
                                    <small><?php echo htmlspecialchars($row['resource_type']); ?></small>
                                </span>
                            </div>
                            <span class="status-badge <?php echo $statusClass; ?>">
                                <?php echo htmlspecialchars($row['status']); ?>
                            </span>
                        </div>

                        <div class="resource-info">
                            <div class="info-row">
                                <i class="fa-solid fa-ruler"></i>
                                <span class="label">Unit</span>
                                <span class="value"><?php echo htmlspecialchars($row['unit']); ?></span>
                            </div>

                            <div class="info-row">
                                <i class="fa-solid fa-clock-rotate-left"></i>
                                <span class="label">Updated At</span>
                                <span class="value"><?php echo htmlspecialchars($row['updated_at']); ?></span>
                            </div>

                            <div class="info-row">
                                <i class="fa-solid fa-hashtag"></i>
                                <span class="label">Resource ID</span>
                                <span class="value">#<?php echo (int)$row['id']; ?></span>
                            </div>
                        </div>

                        <span class="qty-pill">
                            <i class="fa-solid fa-cubes-stacked"></i>
                            <?php echo htmlspecialchars($row['quantity']); ?> <?php echo htmlspecialchars($row['unit']); ?>
                        </span>

                        <?php if ($can_manage): ?>
                            <div class="card-actions">
                                <a href="edit_resource.php?id=<?php echo (int)$row['id']; ?>" class="btn-edit">
                                    <i class="fa-solid fa-pen"></i> Edit
                                </a>
                                <a href="delete_resource.php?id=<?php echo (int)$row['id']; ?>"
                                   class="btn-del"
                                   onclick="return confirm('Are you sure you want to delete this resource?');">
                                    <i class="fa-solid fa-trash"></i> Delete
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fa-solid fa-box-open"></i>
                <p>No resources found.</p>
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
