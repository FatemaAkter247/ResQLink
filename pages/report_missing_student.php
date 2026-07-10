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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_name = trim($_POST['student_name'] ?? '');
    $student_id_number = trim($_POST['student_id_number'] ?? '');
    $age = trim($_POST['age'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $last_seen_location = trim($_POST['last_seen_location'] ?? '');
    $last_seen_at = trim($_POST['last_seen_at'] ?? '');
    $reporter_relationship = trim($_POST['reporter_relationship'] ?? '');
    $reporter_contact = trim($_POST['reporter_contact'] ?? '');

    $allowedGenders = ['male', 'female', 'other'];
    $gender = in_array($gender, $allowedGenders, true) ? $gender : null;
    $age = ($age !== '' && ctype_digit($age)) ? (int)$age : null;

    if ($student_name === '' || $description === '' || $last_seen_location === ''
        || $last_seen_at === '' || $reporter_relationship === '' || $reporter_contact === '') {
        $error = "Please fill in all required fields.";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO missing_student_reports
            (reported_by, student_name, student_id_number, age, gender, description,
             last_seen_location, last_seen_at, reporter_relationship, reporter_contact, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->bind_param(
            "ississssss",
            $user_id,
            $student_name,
            $student_id_number,
            $age,
            $gender,
            $description,
            $last_seen_location,
            $last_seen_at,
            $reporter_relationship,
            $reporter_contact
        );

        if ($stmt->execute()) {
            $success = "Report submitted. An administrator will review it shortly — you can track its status below.";
        } else {
            $error = "Failed to submit the report. Please try again.";
        }
        $stmt->close();
    }
}

// this user's own submitted reports
$myReports = [];
$stmt = $conn->prepare("
    SELECT id, student_name, last_seen_location, last_seen_at, status, review_notes, created_at
    FROM missing_student_reports
    WHERE reported_by = ?
    ORDER BY created_at DESC
    LIMIT 8
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $myReports[] = $row;
}
$stmt->close();

function reportStatusClass($status)
{
    switch ($status) {
        case 'approved':
            return 'st-assigned';
        case 'found':
            return 'st-resolved';
        case 'rejected':
            return 'st-cancelled';
        default:
            return 'st-pending';
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
    <title>Report Missing Student - ResQLink</title>

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

        .info-bar {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 18px;
            border-radius: var(--radius);
            background: #fffbeb;
            border: 1px solid #fde68a;
            color: var(--warn-dark);
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 20px;
        }

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
            background: #fffbeb;
            color: var(--warn);
            display: grid;
            place-items: center;
            font-size: 14px;
        }

        .panel-sub { color: var(--muted); font-size: 13px; margin-bottom: 20px; }

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

        .flash-success { background: #e8f5e9; color: #15803d; border: 1px solid #a5d6a7; }
        .flash-error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

        .form-label { font-size: 13px; font-weight: 700; margin-bottom: 6px; display: block; }

        .form-select, .form-control {
            border-radius: 10px;
            border: 1px solid var(--border);
            padding: 11px 14px;
            font-size: 14px;
            font-family: inherit;
        }

        .form-select:focus, .form-control:focus {
            border-color: var(--warn);
            box-shadow: 0 0 0 0.2rem rgba(180, 83, 9, 0.15);
        }

        .mb-3 { margin-bottom: 18px; }
        .row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

        .btn-sos {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 13px 22px;
            border-radius: 10px;
            background: var(--warn);
            color: #fff;
            font-size: 15px;
            font-weight: 800;
            border: none;
            cursor: pointer;
            transition: all .2s ease;
        }

        .btn-sos:hover { background: var(--warn-dark); transform: translateY(-2px); }

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
        .req-desc { font-size: 13px; color: var(--muted); margin-bottom: 6px; }

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

        .no-req { text-align: center; color: var(--muted); padding: 20px 0; }
        .no-req i { font-size: 36px; color: var(--warn); margin-bottom: 10px; }

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
            .row-2 { grid-template-columns: 1fr; }
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

        <a href="report_missing_student.php" class="nav-link active">
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
                <h1>Report Missing Student</h1>
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
                <span class="ph-icon"><i class="fa-solid fa-person-circle-question"></i></span>
                Report a Missing Student
            </h2>
            <a href="missing_students.php" class="back-btn">
                <i class="fa-solid fa-arrow-left"></i> View Missing Student Alerts
            </a>
        </div>

        <div class="info-bar">
            <i class="fa-solid fa-circle-info"></i>
            Every report is reviewed by an administrator before it appears publicly on the Missing Student Alerts page.
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
            <div class="panel">
                <div class="panel-title">
                    <span class="pt-ico"><i class="fa-solid fa-person-circle-question"></i></span>
                    Missing Student Report
                </div>
                <p class="panel-sub">
                    Provide as much detail as possible to help identify and locate the student quickly.
                </p>

                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Student's Full Name</label>
                        <input type="text" name="student_name" class="form-control w-100" required
                               placeholder="e.g. Rahim Uddin">
                    </div>

                    <div class="row-2 mb-3">
                        <div>
                            <label class="form-label">Student ID (if known)</label>
                            <input type="text" name="student_id_number" class="form-control w-100"
                                   placeholder="e.g. 2021-CSE-045">
                        </div>
                        <div>
                            <label class="form-label">Age (if known)</label>
                            <input type="number" name="age" min="1" max="120" class="form-control w-100" placeholder="e.g. 20">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Gender</label>
                        <select name="gender" class="form-select w-100">
                            <option value="">Prefer not to specify</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Physical Description / What They Were Wearing</label>
                        <textarea name="description" class="form-control w-100" rows="3" required
                                  placeholder="Height, build, clothing, any identifying features..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Last Seen Location</label>
                        <input type="text" name="last_seen_location" class="form-control w-100" required
                               placeholder="e.g. Central Library, near the main gate">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Last Seen Date &amp; Time</label>
                        <input type="datetime-local" name="last_seen_at" class="form-control w-100" required>
                    </div>

                    <div class="row-2 mb-3">
                        <div>
                            <label class="form-label">Your Relationship to the Student</label>
                            <input type="text" name="reporter_relationship" class="form-control w-100" required
                                   placeholder="e.g. Friend, Classmate, Teacher">
                        </div>
                        <div>
                            <label class="form-label">Your Contact Number</label>
                            <input type="text" name="reporter_contact" class="form-control w-100" required
                                   placeholder="e.g. 01XXXXXXXXX">
                        </div>
                    </div>

                    <button type="submit" class="btn-sos">
                        <i class="fa-solid fa-paper-plane"></i> Submit Report
                    </button>
                </form>
            </div>

            <div class="panel">
                <div class="panel-title">
                    <span class="pt-ico"><i class="fa-solid fa-clock-rotate-left"></i></span>
                    My Submitted Reports
                </div>
                <p class="panel-sub">Track the review status of reports you've submitted.</p>

                <?php if (!empty($myReports)): ?>
                    <?php foreach ($myReports as $r): ?>
                        <div class="req-item">
                            <div class="req-head">
                                <span class="req-type"><?php echo e($r['student_name']); ?></span>
                                <span class="pill <?php echo reportStatusClass($r['status']); ?>">
                                    <?php echo e(ucfirst($r['status'])); ?>
                                </span>
                            </div>
                            <div class="req-desc">
                                <i class="fa-solid fa-location-dot"></i> <?php echo e($r['last_seen_location']); ?>
                                &nbsp;·&nbsp; Last seen <?php echo e(date('M j, Y H:i', strtotime($r['last_seen_at']))); ?>
                            </div>
                            <?php if (!empty($r['review_notes'])): ?>
                                <div class="req-meta"><i class="fa-solid fa-note-sticky"></i> <?php echo e($r['review_notes']); ?></div>
                            <?php endif; ?>
                            <div class="req-meta">
                                <span>Submitted <?php echo e($r['created_at']); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-req">
                        <i class="fa-solid fa-circle-check"></i>
                        <p>You haven't submitted any reports yet.</p>
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
