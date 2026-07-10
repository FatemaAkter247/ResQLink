<?php
session_start();
require_once __DIR__ . "/../DB/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$role_id = (int)($_SESSION['role_id'] ?? 0);

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$report_id = (int)($_GET['id'] ?? 0);
if ($report_id <= 0) {
    header("Location: missing_students.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT r.*, u.full_name AS reporter_name
    FROM missing_student_reports r
    JOIN users u ON u.id = r.reported_by
    WHERE r.id = ?
");
$stmt->bind_param("i", $report_id);
$stmt->execute();
$report = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$report) {
    header("Location: missing_students.php");
    exit;
}

$is_owner = ($report['reported_by'] === $user_id);
$is_admin = ($role_id === 2);

// Only the reporter, an admin, or (once approved/found) any logged-in user may view.
if (!$is_owner && !$is_admin && !in_array($report['status'], ['approved', 'found'], true)) {
    header("Location: missing_students.php");
    exit;
}

$success = '';
$error = '';

// Mark as Found (reporter or admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_found'])) {
    if (!$is_owner && !$is_admin) {
        $error = "You are not authorized to perform this action.";
    } elseif ($report['status'] !== 'approved') {
        $error = "Only an approved report can be marked as found.";
    } else {
        $upd = $conn->prepare("UPDATE missing_student_reports SET status = 'found', found_at = NOW() WHERE id = ?");
        $upd->bind_param("i", $report_id);
        if ($upd->execute()) {
            $success = "Great news! The student has been marked as Found.";
            $report['status'] = 'found';
            $report['found_at'] = date('Y-m-d H:i:s');
        } else {
            $error = "Something went wrong. Please try again.";
        }
        $upd->close();
    }
}

// Fetch sightings
$sightStmt = $conn->prepare("
    SELECT s.*, u.full_name AS sighter_name
    FROM missing_student_sightings s
    JOIN users u ON u.id = s.sighted_by
    WHERE s.report_id = ?
    ORDER BY s.sighted_at DESC
");
$sightStmt->bind_param("i", $report_id);
$sightStmt->execute();
$sightings = $sightStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$sightStmt->close();

function statusPillClass($status)
{
    switch ($status) {
        case 'approved': return ['bg' => '#fef3c7', 'fg' => '#92400e', 'label' => 'Missing - Active', 'icon' => 'fa-triangle-exclamation'];
        case 'found': return ['bg' => '#dcfce7', 'fg' => '#166534', 'label' => 'Found', 'icon' => 'fa-circle-check'];
        case 'pending': return ['bg' => '#e5e7eb', 'fg' => '#374151', 'label' => 'Pending Review', 'icon' => 'fa-hourglass-half'];
        case 'rejected': return ['bg' => '#fee2e2', 'fg' => '#991b1b', 'label' => 'Rejected', 'icon' => 'fa-circle-xmark'];
        default: return ['bg' => '#e5e7eb', 'fg' => '#374151', 'label' => ucfirst($status), 'icon' => 'fa-circle'];
    }
}
$pill = statusPillClass($report['status']);

// Session/user info for dashboard shell
$username_raw = $_SESSION['full_name'] ?? 'User';
$username = htmlspecialchars($username_raw, ENT_QUOTES, 'UTF-8');

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
    <title><?php echo e($report['student_name']); ?> - Missing Student - ResQLink</title>

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

        .content { padding: 28px; max-width: 1000px; }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 700;
            color: var(--muted);
            text-decoration: none;
            margin-bottom: 18px;
        }

        .back-btn:hover { color: var(--warn); }

        .flash-success, .flash-error {
            padding: 13px 18px;
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 600;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .flash-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .flash-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        .profile-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 22px;
        }

        .profile-head {
            background: var(--warn-light);
            padding: 26px;
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .profile-avatar {
            width: 76px;
            height: 76px;
            border-radius: 50%;
            background: var(--warn);
            color: #fff;
            display: grid;
            place-items: center;
            font-size: 28px;
            font-weight: 800;
            flex-shrink: 0;
        }

        .profile-name { font-size: 22px; font-weight: 800; margin-bottom: 4px; }
        .profile-sub { font-size: 13px; color: var(--muted); }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 11.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .03em;
            margin-left: auto;
        }

        .profile-body { padding: 24px; }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin-bottom: 20px;
        }

        .detail-item label {
            display: block;
            font-size: 10.5px;
            font-weight: 800;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .05em;
            margin-bottom: 4px;
        }

        .detail-item p { font-size: 14px; font-weight: 600; }

        .desc-box {
            background: var(--bg);
            border-radius: 10px;
            padding: 16px;
            font-size: 13.5px;
            line-height: 1.6;
            color: #374151;
            margin-bottom: 20px;
        }

        .action-row { display: flex; gap: 10px; flex-wrap: wrap; }

        .btn-found, .btn-sighting {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 20px;
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            text-decoration: none;
        }

        .btn-found { background: #166534; color: #fff; }
        .btn-found:hover { background: #14532d; }

        .btn-sighting { background: var(--warn); color: #fff; }
        .btn-sighting:hover { background: var(--warn-dark); color: #fff; }

        .section-title {
            font-size: 15px;
            font-weight: 800;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .sighting-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px 18px;
            margin-bottom: 12px;
        }

        .sighting-top {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            flex-wrap: wrap;
            gap: 6px;
        }

        .sighting-loc { font-size: 13.5px; font-weight: 700; }
        .sighting-time { font-size: 11.5px; color: var(--muted); }
        .sighting-notes { font-size: 13px; color: #374151; margin-bottom: 6px; }
        .sighting-by { font-size: 11.5px; color: var(--muted); }

        .empty-note {
            text-align: center;
            padding: 30px;
            color: var(--muted);
            font-size: 13px;
            background: var(--white);
            border: 1px dashed var(--border);
            border-radius: 12px;
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .45);
            z-index: 90;
        }

        .sidebar-overlay.open { display: block; }

        @media (max-width: 700px) {
            .detail-grid { grid-template-columns: 1fr; }
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
                <h1>Missing Student Details</h1>
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
        <a href="missing_students.php" class="back-btn">
            <i class="fa-solid fa-arrow-left"></i> Back to Missing Student Alerts
        </a>

        <?php if ($success): ?>
            <div class="flash-success"><i class="fa-solid fa-circle-check"></i> <?php echo e($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="flash-error"><i class="fa-solid fa-circle-exclamation"></i> <?php echo e($error); ?></div>
        <?php endif; ?>

        <div class="profile-card">
            <div class="profile-head">
                <div class="profile-avatar"><?php echo strtoupper(substr($report['student_name'], 0, 1)); ?></div>
                <div>
                    <div class="profile-name"><?php echo e($report['student_name']); ?></div>
                    <div class="profile-sub">
                        <?php echo $report['age'] ? e($report['age']) . ' years old' : 'Age unknown'; ?>
                        <?php if (!empty($report['gender'])): ?> · <?php echo e(ucfirst($report['gender'])); ?><?php endif; ?>
                        <?php if (!empty($report['student_id_number'])): ?> · ID: <?php echo e($report['student_id_number']); ?><?php endif; ?>
                    </div>
                </div>
                <span class="status-pill" style="background:<?php echo $pill['bg']; ?>; color:<?php echo $pill['fg']; ?>;">
                    <i class="fa-solid <?php echo $pill['icon']; ?>"></i> <?php echo e($pill['label']); ?>
                </span>
            </div>

            <div class="profile-body">
                <div class="detail-grid">
                    <div class="detail-item">
                        <label>Last Seen Location</label>
                        <p><?php echo e($report['last_seen_location']); ?></p>
                    </div>
                    <div class="detail-item">
                        <label>Last Seen At</label>
                        <p><?php echo e(date('M j, Y · H:i', strtotime($report['last_seen_at']))); ?></p>
                    </div>
                    <div class="detail-item">
                        <label>Reported By</label>
                        <p><?php echo e($report['reporter_name']); ?> (<?php echo e(ucfirst($report['reporter_relationship'])); ?>)</p>
                    </div>
                    <div class="detail-item">
                        <label>Reporter Contact</label>
                        <p><?php echo e($report['reporter_contact']); ?></p>
                    </div>
                    <?php if ($report['status'] === 'found' && $report['found_at']): ?>
                    <div class="detail-item">
                        <label>Found At</label>
                        <p><?php echo e(date('M j, Y · H:i', strtotime($report['found_at']))); ?></p>
                    </div>
                    <?php endif; ?>
                </div>

                <label style="display:block; font-size:10.5px; font-weight:800; color:var(--muted); text-transform:uppercase; letter-spacing:.05em; margin-bottom:6px;">Description</label>
                <div class="desc-box"><?php echo nl2br(e($report['description'])); ?></div>

                <div class="action-row">
                    <?php if ($report['status'] === 'approved'): ?>
                        <a href="submit_sighting.php?id=<?php echo (int)$report['id']; ?>" class="btn-sighting">
                            <i class="fa-solid fa-eye"></i> Report a Sighting
                        </a>
                        <?php if ($is_owner || $is_admin): ?>
                            <form method="POST" onsubmit="return confirm('Mark this student as Found? This will remove the alert from the active list.');">
                                <input type="hidden" name="mark_found" value="1">
                                <button type="submit" class="btn-found">
                                    <i class="fa-solid fa-circle-check"></i> Mark as Found
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php elseif ($report['status'] === 'pending'): ?>
                        <p style="font-size:13px; color:var(--muted);">This report is awaiting admin verification and is not yet public.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if (in_array($report['status'], ['approved', 'found'], true)): ?>
        <div class="section-title">
            <i class="fa-solid fa-eye" style="color:var(--warn);"></i> Sightings Reported (<?php echo count($sightings); ?>)
        </div>

        <?php if (empty($sightings)): ?>
            <div class="empty-note">No sightings have been reported yet.</div>
        <?php else: ?>
            <?php foreach ($sightings as $s): ?>
                <div class="sighting-card">
                    <div class="sighting-top">
                        <span class="sighting-loc"><i class="fa-solid fa-location-dot" style="color:var(--warn);"></i> <?php echo e($s['location']); ?></span>
                        <span class="sighting-time"><?php echo e(date('M j, Y · H:i', strtotime($s['sighted_at']))); ?></span>
                    </div>
                    <?php if (!empty($s['notes'])): ?>
                        <div class="sighting-notes"><?php echo nl2br(e($s['notes'])); ?></div>
                    <?php endif; ?>
                    <div class="sighting-by">Reported by <?php echo e($s['sighter_name']); ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
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
