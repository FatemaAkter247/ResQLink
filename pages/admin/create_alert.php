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
$error = "";
$editMode = false;
$editData = [
    'id' => '',
    'alert_type' => '',
    'location_text' => '',
    'severity' => 'medium',
    'instructions' => '',
    'status' => 'published'
];

/* ---------------- DELETE ---------------- */
if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];

    $delNotif = $conn->prepare("DELETE FROM alert_notifications WHERE alert_id = ?");
    $delNotif->bind_param("i", $delete_id);
    $delNotif->execute();
    $delNotif->close();

    $delAlert = $conn->prepare("DELETE FROM disaster_alerts WHERE id = ?");
    $delAlert->bind_param("i", $delete_id);

    if ($delAlert->execute()) {
        header("Location: create_alert.php?msg=deleted");
        exit;
    } else {
        $error = "Failed to delete alert.";
    }
    $delAlert->close();
}

/* ---------------- EDIT LOAD ---------------- */
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];

    $stmt = $conn->prepare("SELECT * FROM disaster_alerts WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $resultEdit = $stmt->get_result();

    if ($resultEdit && $resultEdit->num_rows === 1) {
        $editData = $resultEdit->fetch_assoc();
        $editMode = true;
    } else {
        $error = "Alert not found.";
    }
    $stmt->close();
}

/* ---------------- CREATE / UPDATE ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $alert_id = (int)($_POST['alert_id'] ?? 0);
    $type = trim($_POST['alert_type'] ?? '');
    $location = trim($_POST['location_text'] ?? '');
    $severity = trim($_POST['severity'] ?? 'medium');
    $instructions = trim($_POST['instructions'] ?? '');
    $status = trim($_POST['status'] ?? 'published');
    $created_by = (int)$_SESSION['user_id'];

    if ($type === '' || $location === '' || $instructions === '') {
        $error = "Please fill in all fields.";
    } else {
        if ($alert_id > 0) {
            $stmt = $conn->prepare("
                UPDATE disaster_alerts
                SET alert_type = ?, location_text = ?, severity = ?, instructions = ?, status = ?
                WHERE id = ?
            ");
            $stmt->bind_param("sssssi", $type, $location, $severity, $instructions, $status, $alert_id);

            if ($stmt->execute()) {
                header("Location: create_alert.php?msg=updated");
                exit;
            } else {
                $error = "Failed to update alert.";
            }
            $stmt->close();
        } else {
            $stmt = $conn->prepare("
                INSERT INTO disaster_alerts
                (created_by, alert_type, location_text, severity, instructions, status, published_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param("isssss", $created_by, $type, $location, $severity, $instructions, $status);

            if ($stmt->execute()) {
                $alert_id = $stmt->insert_id;

                $users = $conn->query("SELECT id FROM users");
                while ($u = $users->fetch_assoc()) {
                    $uid = (int)$u['id'];

                    $n = $conn->prepare("
                        INSERT INTO alert_notifications (alert_id, user_id)
                        VALUES (?, ?)
                    ");
                    $n->bind_param("ii", $alert_id, $uid);
                    $n->execute();
                    $n->close();
                }

                header("Location: create_alert.php?msg=added");
                exit;
            } else {
                $error = "Failed to publish alert.";
            }
            $stmt->close();
        }
    }
}

/* ---------------- SUCCESS MSG ---------------- */
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'added') {
        $success = "Alert added successfully!";
    } elseif ($_GET['msg'] === 'updated') {
        $success = "Alert updated successfully!";
    } elseif ($_GET['msg'] === 'deleted') {
        $success = "Alert deleted successfully!";
    }
}

$alerts = $conn->query("SELECT * FROM disaster_alerts ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Alert - ResQLink</title>

    <link rel="stylesheet" href="../../css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
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

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            margin: 0;
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
            margin: 0;
        }

        .topbar-title p {
            font-size: 12px;
            color: var(--muted);
            margin-top: 2px;
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

        .page-card {
            width: 100%;
            max-width: 1100px;
            margin: 0 auto;
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 2rem;
        }

        .page-title {
            color: var(--accent);
            font-weight: 800;
            margin-bottom: 1.5rem;
        }

        .form-control:focus,
        .form-select:focus,
        textarea:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 0.2rem rgba(198, 40, 40, 0.2);
        }

        .record-box {
            background: var(--bg);
            border-left: 5px solid var(--accent);
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 14px;
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

        <a href="create_alert.php" class="nav-link active">
            <i class="fa-solid fa-circle-plus"></i> Create Alert
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
                <h1><?php echo $editMode ? 'Update Alert' : 'Create Alert'; ?></h1>
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
            <h2 class="page-title"><?php echo $editMode ? 'Update Alert' : 'Create Disaster Alert'; ?></h2>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" class="mb-4">
                <input type="hidden" name="alert_id" value="<?php echo htmlspecialchars($editData['id']); ?>">

                <div class="mb-3">
                    <label class="form-label">Alert Type</label>
                    <input type="text" name="alert_type" class="form-control" required
                           value="<?php echo htmlspecialchars($editData['alert_type']); ?>"
                           placeholder="Flood, Fire, Cyclone...">
                </div>

                <div class="mb-3">
                    <label class="form-label">Location</label>
                    <input type="text" name="location_text" class="form-control" required
                           value="<?php echo htmlspecialchars($editData['location_text']); ?>"
                           placeholder="Enter affected location">
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Severity</label>
                        <select name="severity" class="form-select" required>
                            <option value="low" <?php echo $editData['severity'] === 'low' ? 'selected' : ''; ?>>Low</option>
                            <option value="medium" <?php echo $editData['severity'] === 'medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="high" <?php echo $editData['severity'] === 'high' ? 'selected' : ''; ?>>High</option>
                            <option value="critical" <?php echo $editData['severity'] === 'critical' ? 'selected' : ''; ?>>Critical</option>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select" required>
                            <option value="draft" <?php echo $editData['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                            <option value="published" <?php echo $editData['status'] === 'published' ? 'selected' : ''; ?>>Published</option>
                            <option value="archived" <?php echo $editData['status'] === 'archived' ? 'selected' : ''; ?>>Archived</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Instructions</label>
                    <textarea name="instructions" class="form-control" rows="5" required placeholder="Write alert instructions..."><?php echo htmlspecialchars($editData['instructions']); ?></textarea>
                </div>

                <div class="d-flex gap-2 flex-wrap">
                    <button type="submit" class="btn btn-red">
                        <?php echo $editMode ? 'Update Alert' : 'Publish Alert'; ?>
                    </button>

                    <?php if ($editMode): ?>
                        <a href="create_alert.php" class="btn btn-secondary">Cancel</a>
                    <?php else: ?>
                        <a href="../dashboard.php" class="btn btn-secondary">Back</a>
                    <?php endif; ?>
                </div>
            </form>

            <hr>

            <h4 class="mb-3">All Alerts</h4>

            <?php if ($alerts && $alerts->num_rows > 0): ?>
                <?php while ($row = $alerts->fetch_assoc()): ?>
                    <div class="record-box">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                            <div>
                                <h5 class="mb-1" style="color: var(--accent);"><?php echo htmlspecialchars($row['alert_type']); ?></h5>
                                <p class="mb-1"><strong>Location:</strong> <?php echo htmlspecialchars($row['location_text']); ?></p>
                                <p class="mb-1"><strong>Severity:</strong> <?php echo htmlspecialchars($row['severity']); ?></p>
                                <p class="mb-1"><strong>Status:</strong> <?php echo htmlspecialchars($row['status']); ?></p>
                                <p class="mb-1"><strong>Instructions:</strong> <?php echo htmlspecialchars($row['instructions']); ?></p>
                                <p class="mb-0"><strong>Created At:</strong> <?php echo htmlspecialchars($row['created_at']); ?></p>
                            </div>

                            <div class="d-flex gap-2">
                                <a href="create_alert.php?edit=<?php echo (int)$row['id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                                <a href="create_alert.php?delete=<?php echo (int)$row['id']; ?>"
                                   class="btn btn-danger btn-sm"
                                   onclick="return confirm('Are you sure you want to delete this alert?');">
                                   Delete
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="alert alert-info">No alerts found.</div>
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
