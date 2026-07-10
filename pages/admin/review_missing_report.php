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

$admin_id = (int)$_SESSION['user_id'];
$username_raw = $_SESSION['full_name'] ?? 'Admin';
$username = htmlspecialchars($username_raw, ENT_QUOTES, 'UTF-8');
$initials = strtoupper(substr($username_raw, 0, 1));

$report_id = (int)($_GET['id'] ?? $_POST['report_id'] ?? 0);
if ($report_id <= 0) {
    header("Location: missing_student_reports.php");
    exit;
}

$error = "";

/* ---------------- ACTIONS ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve'])) {
        $notes = trim($_POST['review_notes'] ?? '');
        $stmt = $conn->prepare("
            UPDATE missing_student_reports
            SET status = 'approved', reviewed_by = ?, review_notes = ?, reviewed_at = NOW()
            WHERE id = ? AND status = 'pending'
        ");
        $stmt->bind_param("isi", $admin_id, $notes, $report_id);
        if ($stmt->execute()) {
            header("Location: missing_student_reports.php?msg=approved");
            exit;
        }
        $error = "Failed to approve report.";
        $stmt->close();
    } elseif (isset($_POST['reject'])) {
        $notes = trim($_POST['review_notes'] ?? '');
        if ($notes === '') {
            $error = "Please provide a reason for rejecting this report.";
        } else {
            $stmt = $conn->prepare("
                UPDATE missing_student_reports
                SET status = 'rejected', reviewed_by = ?, review_notes = ?, reviewed_at = NOW()
                WHERE id = ? AND status = 'pending'
            ");
            $stmt->bind_param("isi", $admin_id, $notes, $report_id);
            if ($stmt->execute()) {
                header("Location: missing_student_reports.php?msg=rejected");
                exit;
            }
            $error = "Failed to reject report.";
            $stmt->close();
        }
    } elseif (isset($_POST['mark_found'])) {
        $stmt = $conn->prepare("
            UPDATE missing_student_reports
            SET status = 'found', found_at = NOW()
            WHERE id = ? AND status = 'approved'
        ");
        $stmt->bind_param("i", $report_id);
        if ($stmt->execute()) {
            header("Location: missing_student_reports.php?msg=found");
            exit;
        }
        $error = "Failed to mark student as found.";
        $stmt->close();
    }
}

/* ---------------- LOAD REPORT ---------------- */
$stmt = $conn->prepare("
    SELECT r.*, u.full_name AS reporter_name, u.email AS reporter_email,
           rv.full_name AS reviewer_name
    FROM missing_student_reports r
    JOIN users u ON u.id = r.reported_by
    LEFT JOIN users rv ON rv.id = r.reviewed_by
    WHERE r.id = ?
");
$stmt->bind_param("i", $report_id);
$stmt->execute();
$report = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$report) {
    header("Location: missing_student_reports.php");
    exit;
}

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
list($badgeBg, $badgeFg) = statusBadge($report['status']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Report - ResQLink Admin</title>

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
            max-width: 900px;
            margin: 0 auto;
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 2rem;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 700;
            color: var(--muted);
            text-decoration: none;
            margin-bottom: 16px;
        }

        .back-link:hover { color: var(--accent); }

        .page-title {
            color: var(--warn);
            font-weight: 800;
            margin-bottom: .25rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 13px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .03em;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 14px;
            margin: 20px 0;
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

        .detail-item p { font-size: 14px; font-weight: 600; margin: 0; }

        .desc-box {
            background: var(--bg);
            border-radius: 10px;
            padding: 16px;
            font-size: 13.5px;
            line-height: 1.6;
            color: #374151;
            margin-bottom: 20px;
        }

        .sighting-card {
            background: var(--bg);
            border-radius: 10px;
            padding: 14px 16px;
            margin-bottom: 10px;
            font-size: 13px;
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

        .btn-found {
            background-color: #166534;
            border-color: #166534;
            color: #fff;
        }

        .btn-found:hover {
            background-color: #14532d;
            border-color: #14532d;
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
            .detail-grid { grid-template-columns: 1fr; }
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
                <h1>Review Missing Student Report</h1>
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
            <a href="missing_student_reports.php" class="back-link">
                <i class="fa-solid fa-arrow-left"></i> Back to Reports
            </a>

            <h2 class="page-title">
                <?php echo htmlspecialchars($report['student_name']); ?>
                <span class="status-badge" style="background:<?php echo $badgeBg; ?>; color:<?php echo $badgeFg; ?>;">
                    <?php echo htmlspecialchars(ucfirst($report['status'])); ?>
                </span>
            </h2>

            <?php if ($error): ?>
                <div class="alert alert-danger mt-3"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="detail-grid">
                <div class="detail-item">
                    <label>Student ID Number</label>
                    <p><?php echo $report['student_id_number'] ? htmlspecialchars($report['student_id_number']) : '—'; ?></p>
                </div>
                <div class="detail-item">
                    <label>Age / Gender</label>
                    <p><?php echo $report['age'] ? htmlspecialchars($report['age']) . ' yrs' : 'Unknown'; ?><?php echo $report['gender'] ? ' · ' . htmlspecialchars(ucfirst($report['gender'])) : ''; ?></p>
                </div>
                <div class="detail-item">
                    <label>Last Seen Location</label>
                    <p><?php echo htmlspecialchars($report['last_seen_location']); ?></p>
                </div>
                <div class="detail-item">
                    <label>Last Seen At</label>
                    <p><?php echo htmlspecialchars(date('M j, Y H:i', strtotime($report['last_seen_at']))); ?></p>
                </div>
                <div class="detail-item">
                    <label>Reported By</label>
                    <p><?php echo htmlspecialchars($report['reporter_name']); ?> (<?php echo htmlspecialchars(ucfirst($report['reporter_relationship'])); ?>)</p>
                </div>
                <div class="detail-item">
                    <label>Reporter Contact</label>
                    <p><?php echo htmlspecialchars($report['reporter_contact']); ?></p>
                </div>
                <?php if ($report['reviewer_name']): ?>
                <div class="detail-item">
                    <label>Reviewed By</label>
                    <p><?php echo htmlspecialchars($report['reviewer_name']); ?> on <?php echo htmlspecialchars(date('M j, Y H:i', strtotime($report['reviewed_at']))); ?></p>
                </div>
                <?php endif; ?>
                <?php if ($report['status'] === 'found' && $report['found_at']): ?>
                <div class="detail-item">
                    <label>Found At</label>
                    <p><?php echo htmlspecialchars(date('M j, Y H:i', strtotime($report['found_at']))); ?></p>
                </div>
                <?php endif; ?>
            </div>

            <label style="display:block; font-size:10.5px; font-weight:800; color:var(--muted); text-transform:uppercase; letter-spacing:.05em; margin-bottom:6px;">Description</label>
            <div class="desc-box"><?php echo nl2br(htmlspecialchars($report['description'])); ?></div>

            <?php if ($report['status'] === 'pending'): ?>
                <form method="POST">
                    <input type="hidden" name="report_id" value="<?php echo (int)$report['id']; ?>">
                    <div class="mb-3">
                        <label class="form-label">Review Notes <span class="text-muted">(required if rejecting)</span></label>
                        <textarea name="review_notes" class="form-control" rows="3" placeholder="Optional notes for approval, or reason for rejection"></textarea>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="submit" name="approve" value="1" class="btn btn-red">
                            <i class="fa-solid fa-check"></i> Approve &amp; Publish
                        </button>
                        <button type="submit" name="reject" value="1" class="btn btn-outline-danger"
                                onclick="return confirm('Reject this report?');">
                            <i class="fa-solid fa-xmark"></i> Reject
                        </button>
                    </div>
                </form>
            <?php elseif ($report['status'] === 'approved'): ?>
                <form method="POST" onsubmit="return confirm('Mark this student as Found? This will remove the alert from the active list.');">
                    <input type="hidden" name="report_id" value="<?php echo (int)$report['id']; ?>">
                    <button type="submit" name="mark_found" value="1" class="btn btn-found">
                        <i class="fa-solid fa-circle-check"></i> Mark as Found
                    </button>
                </form>
            <?php endif; ?>

            <?php if (!empty($report['review_notes'])): ?>
                <div class="alert alert-secondary mt-3">
                    <strong>Review Notes:</strong> <?php echo htmlspecialchars($report['review_notes']); ?>
                </div>
            <?php endif; ?>

            <?php if (in_array($report['status'], ['approved', 'found'], true)): ?>
                <hr class="my-4">
                <h5 class="mb-3">Sightings Reported (<?php echo count($sightings); ?>)</h5>
                <?php if (empty($sightings)): ?>
                    <p class="text-muted">No sightings reported yet.</p>
                <?php else: ?>
                    <?php foreach ($sightings as $s): ?>
                        <div class="sighting-card">
                            <strong><?php echo htmlspecialchars($s['location']); ?></strong>
                            — <?php echo htmlspecialchars(date('M j, Y H:i', strtotime($s['sighted_at']))); ?>
                            <?php if (!empty($s['notes'])): ?><br><?php echo nl2br(htmlspecialchars($s['notes'])); ?><?php endif; ?>
                            <br><span class="text-muted">Reported by <?php echo htmlspecialchars($s['sighter_name']); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
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
