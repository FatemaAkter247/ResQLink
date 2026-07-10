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

$username_raw = $_SESSION['full_name'] ?? 'Admin';
$username = htmlspecialchars($username_raw, ENT_QUOTES, 'UTF-8');
$initials = strtoupper(substr($username_raw, 0, 1));

$success = "";
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'approved') {
        $success = "Report approved and published to Missing Student Alerts.";
    } elseif ($_GET['msg'] === 'rejected') {
        $success = "Report rejected.";
    } elseif ($_GET['msg'] === 'found') {
        $success = "Student marked as Found.";
    }
}

$filter = $_GET['status'] ?? 'pending';
$allowedFilters = ['pending', 'approved', 'rejected', 'found', 'all'];
if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'pending';
}

if ($filter === 'all') {
    $reports = $conn->query("
        SELECT r.*, u.full_name AS reporter_name
        FROM missing_student_reports r
        JOIN users u ON u.id = r.reported_by
        ORDER BY r.created_at DESC
    ");
} else {
    $stmt = $conn->prepare("
        SELECT r.*, u.full_name AS reporter_name
        FROM missing_student_reports r
        JOIN users u ON u.id = r.reported_by
        WHERE r.status = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->bind_param("s", $filter);
    $stmt->execute();
    $reports = $stmt->get_result();
}

$counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'found' => 0];
$countRes = $conn->query("SELECT status, COUNT(*) AS c FROM missing_student_reports GROUP BY status");
if ($countRes) {
    while ($row = $countRes->fetch_assoc()) {
        if (isset($counts[$row['status']])) {
            $counts[$row['status']] = (int)$row['c'];
        }
    }
}

function statusBadge($status)
{
    switch ($status) {
        case 'pending': return ['#e5e7eb', '#374151'];
        case 'approved': return ['#fef3c7', '#92400e'];
        case 'found': return ['#dcfce7', '#166534'];
        case 'rejected': return ['#fee2e2', '#991b1b'];
        default: return ['#e5e7eb', '#374151'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Missing Student Reports - ResQLink Admin</title>

    <link rel="stylesheet" href="../../css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        * { box-sizing: border-box; }

        :root {
            --accent: #c62828;
            --accent-dark: #8e0000;
            --accent-light: #ffebee;
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
            margin: 0;
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

        .topbar-title h1 { font-size: 17px; font-weight: 800; margin: 0; }
        .topbar-title p { font-size: 12px; color: var(--muted); margin-top: 2px; }

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

        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .45);
            z-index: 90;
        }

        .sidebar-overlay.open { display: block; }

        .page-card {
            width: 100%;
            max-width: 1150px;
            margin: 0 auto;
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 2rem;
        }

        .page-title { color: var(--accent); font-weight: 800; margin-bottom: 1.5rem; }

        .filter-tabs { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 20px; }

        .filter-tab {
            padding: 8px 16px;
            border-radius: 20px;
            border: 1px solid var(--border);
            font-size: 12.5px;
            font-weight: 700;
            text-decoration: none;
            color: var(--muted);
        }

        .filter-tab.active { background: var(--accent); color: #fff; border-color: var(--accent); }
        .filter-tab .count { margin-left: 4px; opacity: .8; }

        .record-box {
            background: var(--bg);
            border-left: 5px solid var(--warn);
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 14px;
        }

        .status-badge {
            display: inline-block;
            padding: 3px 11px;
            border-radius: 20px;
            font-size: 10.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .03em;
        }

        .btn-red {
            background-color: var(--accent);
            border-color: var(--accent);
            color: #fff;
        }

        .btn-red:hover {
            background-color: var(--accent-dark);
            border-color: var(--accent-dark);
            color: #fff;
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
    <a href="../dashboard.php" class="sidebar-brand">
        <div class="brand-icon">
            <i class="fa-solid fa-shield-halved"></i>
        </div>
        <span class="brand-name">ResQ<span>Link</span></span>
    </a>

    <nav class="sidebar-nav">
        <span class="nav-label">Main Menu</span>

        <a href="../dashboard.php" class="nav-link">
            <i class="fa-solid fa-gauge-high"></i> Dashboard
        </a>

        <a href="../chatbot.php" class="nav-link">
            <i class="fa-solid fa-robot"></i> AI Emergency Chatbot
        </a>

        <span class="nav-label">Alerts</span>

        <a href="../alerts.php" class="nav-link">
            <i class="fa-solid fa-bell"></i> View Alerts
        </a>

        <a href="create_alert.php" class="nav-link">
            <i class="fa-solid fa-circle-plus"></i> Create Alert
        </a>

        <span class="nav-label">Missing Students</span>

        <a href="missing_student_reports.php" class="nav-link active">
            <i class="fa-solid fa-user-magnifying-glass"></i> Missing Student Reports
            <?php if ($counts['pending'] > 0): ?>
                <span class="badge"><?php echo $counts['pending']; ?></span>
            <?php endif; ?>
        </a>

        <span class="nav-label">Management</span>

        <a href="manage_shelters.php" class="nav-link">
            <i class="fa-solid fa-house-chimney"></i> Manage Shelters
        </a>

        <a href="manage_resources.php" class="nav-link">
            <i class="fa-solid fa-boxes-stacked"></i> Manage Resources
        </a>

        <a href="manage_evacuation.php" class="nav-link">
            <i class="fa-solid fa-person-walking-arrow-right"></i> Manage Evacuation
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="../logout.php" class="nav-link logout">
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
                <h1>Missing Student Reports</h1>
                <p><?= date('l, F j, Y') ?></p>
            </div>
        </div>

        <div class="topbar-right">
            <div class="user-chip">
                <div class="avatar"><?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?></div>
                <div>
                    <div class="user-chip-name"><?= $username ?></div>
                    <div class="user-chip-role">Administrator</div>
                </div>
            </div>
        </div>
    </header>

    <main class="content">
        <div class="page-card">
            <h2 class="page-title">Missing Student Reports</h2>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <div class="filter-tabs">
                <a href="missing_student_reports.php?status=pending" class="filter-tab <?php echo $filter === 'pending' ? 'active' : ''; ?>">
                    Pending <span class="count">(<?php echo $counts['pending']; ?>)</span>
                </a>
                <a href="missing_student_reports.php?status=approved" class="filter-tab <?php echo $filter === 'approved' ? 'active' : ''; ?>">
                    Approved <span class="count">(<?php echo $counts['approved']; ?>)</span>
                </a>
                <a href="missing_student_reports.php?status=found" class="filter-tab <?php echo $filter === 'found' ? 'active' : ''; ?>">
                    Found <span class="count">(<?php echo $counts['found']; ?>)</span>
                </a>
                <a href="missing_student_reports.php?status=rejected" class="filter-tab <?php echo $filter === 'rejected' ? 'active' : ''; ?>">
                    Rejected <span class="count">(<?php echo $counts['rejected']; ?>)</span>
                </a>
                <a href="missing_student_reports.php?status=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                    All
                </a>
            </div>

            <?php if ($reports && $reports->num_rows > 0): ?>
                <?php while ($row = $reports->fetch_assoc()): ?>
                    <?php list($bg, $fg) = statusBadge($row['status']); ?>
                    <div class="record-box">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                            <div>
                                <h5 class="mb-1" style="color: var(--warn);">
                                    <?php echo htmlspecialchars($row['student_name']); ?>
                                    <span class="status-badge" style="background:<?php echo $bg; ?>; color:<?php echo $fg; ?>;">
                                        <?php echo htmlspecialchars(ucfirst($row['status'])); ?>
                                    </span>
                                </h5>
                                <p class="mb-1"><strong>Last Seen:</strong> <?php echo htmlspecialchars($row['last_seen_location']); ?> on <?php echo htmlspecialchars(date('M j, Y H:i', strtotime($row['last_seen_at']))); ?></p>
                                <p class="mb-1"><strong>Reported By:</strong> <?php echo htmlspecialchars($row['reporter_name']); ?> (<?php echo htmlspecialchars(ucfirst($row['reporter_relationship'])); ?>)</p>
                                <p class="mb-1"><strong>Contact:</strong> <?php echo htmlspecialchars($row['reporter_contact']); ?></p>
                                <p class="mb-0"><strong>Submitted:</strong> <?php echo htmlspecialchars(date('M j, Y H:i', strtotime($row['created_at']))); ?></p>
                                <?php if (!empty($row['review_notes'])): ?>
                                    <p class="mb-0 mt-1"><strong>Review Notes:</strong> <?php echo htmlspecialchars($row['review_notes']); ?></p>
                                <?php endif; ?>
                            </div>

                            <div class="d-flex gap-2">
                                <a href="review_missing_report.php?id=<?php echo (int)$row['id']; ?>" class="btn btn-red btn-sm">
                                    <?php echo $row['status'] === 'pending' ? 'Review' : 'View / Manage'; ?>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="alert alert-info">No reports found for this filter.</div>
            <?php endif; ?>
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
