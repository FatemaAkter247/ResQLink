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
    'user_id' => '',
    'status' => 'safe',
    'shelter_id' => '',
    'notes' => ''
];

/* ---------------- DELETE ---------------- */
if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];

    $stmt = $conn->prepare("DELETE FROM evacuation_status WHERE id = ?");
    $stmt->bind_param("i", $delete_id);

    if ($stmt->execute()) {
        header("Location: manage_evacuation.php?msg=deleted");
        exit;
    } else {
        $error = "Failed to delete evacuation record.";
    }
    $stmt->close();
}

/* ---------------- EDIT LOAD ---------------- */
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];

    $stmt = $conn->prepare("SELECT * FROM evacuation_status WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $resultEdit = $stmt->get_result();

    if ($resultEdit && $resultEdit->num_rows === 1) {
        $editData = $resultEdit->fetch_assoc();
        $editMode = true;
    } else {
        $error = "Evacuation record not found.";
    }
    $stmt->close();
}

/* ---------------- ADD / UPDATE ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $user_id = (int)($_POST['user_id'] ?? 0);
    $status = trim($_POST['status'] ?? 'safe');
    $shelter_id = trim($_POST['shelter_id'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($user_id <= 0 || $status === '') {
        $error = "Please fill all required fields.";
    } else {
        $allowedStatuses = ['safe', 'evacuated', 'need_help'];
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'safe';
        }

        $shelter_id = ($shelter_id === '') ? null : (int)$shelter_id;

        if ($status !== 'evacuated') {
            $shelter_id = null;
        }

        if ($id > 0) {
            $stmt = $conn->prepare("
                UPDATE evacuation_status
                SET user_id = ?, status = ?, shelter_id = ?, notes = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param("isisi", $user_id, $status, $shelter_id, $notes, $id);

            if ($stmt->execute()) {
                header("Location: manage_evacuation.php?msg=updated");
                exit;
            } else {
                $error = "Failed to update evacuation record.";
            }
            $stmt->close();
        } else {
            $stmt = $conn->prepare("
                INSERT INTO evacuation_status (user_id, status, shelter_id, notes, updated_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param("isis", $user_id, $status, $shelter_id, $notes);

            if ($stmt->execute()) {
                header("Location: manage_evacuation.php?msg=added");
                exit;
            } else {
                $error = "Failed to add evacuation record.";
            }
            $stmt->close();
        }
    }
}

/* ---------------- SUCCESS MESSAGE ---------------- */
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'added') {
        $success = "Evacuation record added successfully!";
    } elseif ($_GET['msg'] === 'updated') {
        $success = "Evacuation record updated successfully!";
    } elseif ($_GET['msg'] === 'deleted') {
        $success = "Evacuation record deleted successfully!";
    }
}

/* ---------------- DATA FOR DROPDOWNS ---------------- */
$users = $conn->query("SELECT id, full_name, phone FROM users ORDER BY full_name ASC");
$shelters = $conn->query("SELECT id, shelter_name, city FROM shelters ORDER BY shelter_name ASC");

/* ---------------- LIST ALL RECORDS ---------------- */
$result = $conn->query("
    SELECT
        es.id,
        es.user_id,
        es.status,
        es.shelter_id,
        es.notes,
        es.updated_at,
        u.full_name,
        u.phone,
        u.email,
        s.shelter_name,
        s.address,
        s.city
    FROM evacuation_status es
    LEFT JOIN users u ON es.user_id = u.id
    LEFT JOIN shelters s ON es.shelter_id = s.id
    ORDER BY es.id DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Evacuation - ResQLink</title>

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

        <a href="create_alert.php" class="nav-link">
            <i class="fa-solid fa-circle-plus"></i> Create Alert
        </a>

        <span class="nav-label">Management</span>

        <a href="manage_shelters.php" class="nav-link">
            <i class="fa-solid fa-house-chimney"></i> Manage Shelters
        </a>

        <a href="manage_resources.php" class="nav-link">
            <i class="fa-solid fa-boxes-stacked"></i> Manage Resources
        </a>

        <a href="manage_evacuation.php" class="nav-link active">
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
                <h1><?php echo $editMode ? 'Update Evacuation Record' : 'Manage Evacuation'; ?></h1>
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
            <h2 class="page-title"><?php echo $editMode ? 'Update Evacuation Record' : 'Manage Evacuation'; ?></h2>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" class="mb-4">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($editData['id']); ?>">

                <div class="mb-3">
                    <label class="form-label">User</label>
                    <select name="user_id" class="form-select" required>
                        <option value="">Select user</option>
                        <?php
                        if ($users) {
                            while ($u = $users->fetch_assoc()):
                        ?>
                            <option value="<?php echo (int)$u['id']; ?>"
                                <?php echo ((string)$editData['user_id'] === (string)$u['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($u['full_name'] . ' (' . $u['phone'] . ')'); ?>
                            </option>
                        <?php
                            endwhile;
                        }
                        ?>
                    </select>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select" required>
                            <option value="safe" <?php echo $editData['status'] === 'safe' ? 'selected' : ''; ?>>Safe</option>
                            <option value="evacuated" <?php echo $editData['status'] === 'evacuated' ? 'selected' : ''; ?>>Evacuated</option>
                            <option value="need_help" <?php echo $editData['status'] === 'need_help' ? 'selected' : ''; ?>>Need Help</option>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Shelter (only if evacuated)</label>
                        <select name="shelter_id" class="form-select">
                            <option value="">No shelter selected</option>
                            <?php
                            if ($shelters) {
                                while ($s = $shelters->fetch_assoc()):
                            ?>
                                <option value="<?php echo (int)$s['id']; ?>"
                                    <?php echo ((string)$editData['shelter_id'] === (string)$s['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($s['shelter_name'] . ' - ' . $s['city']); ?>
                                </option>
                            <?php
                                endwhile;
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="4" placeholder="Write notes if needed..."><?php echo htmlspecialchars($editData['notes']); ?></textarea>
                </div>

                <div class="d-flex gap-2 flex-wrap">
                    <button type="submit" class="btn btn-red">
                        <?php echo $editMode ? 'Update Record' : 'Add Record'; ?>
                    </button>

                    <?php if ($editMode): ?>
                        <a href="manage_evacuation.php" class="btn btn-secondary">Cancel</a>
                    <?php else: ?>
                        <a href="../dashboard.php" class="btn btn-secondary">Back</a>
                    <?php endif; ?>
                </div>
            </form>

            <hr>

            <h4 class="mb-3">All Evacuation Records</h4>

            <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <div class="record-box">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                            <div>
                                <h5 class="mb-1" style="color: var(--accent);"><?php echo htmlspecialchars($row['full_name'] ?? 'Unknown User'); ?></h5>
                                <p class="mb-1"><strong>Status:</strong> <?php echo htmlspecialchars($row['status']); ?></p>
                                <p class="mb-1"><strong>Phone:</strong> <?php echo htmlspecialchars($row['phone'] ?? 'N/A'); ?></p>
                                <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($row['email'] ?? 'N/A'); ?></p>

                                <?php if (!empty($row['shelter_name'])): ?>
                                    <p class="mb-1"><strong>Shelter:</strong> <?php echo htmlspecialchars($row['shelter_name']); ?></p>
                                    <p class="mb-1"><strong>Address:</strong> <?php echo htmlspecialchars($row['address']); ?></p>
                                    <p class="mb-1"><strong>City:</strong> <?php echo htmlspecialchars($row['city']); ?></p>
                                <?php endif; ?>

                                <?php if (!empty($row['notes'])): ?>
                                    <p class="mb-1"><strong>Notes:</strong> <?php echo htmlspecialchars($row['notes']); ?></p>
                                <?php endif; ?>

                                <p class="mb-0"><strong>Updated At:</strong> <?php echo htmlspecialchars($row['updated_at']); ?></p>
                            </div>

                            <div class="d-flex gap-2">
                                <a href="manage_evacuation.php?edit=<?php echo (int)$row['id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                                <a href="manage_evacuation.php?delete=<?php echo (int)$row['id']; ?>"
                                   class="btn btn-danger btn-sm"
                                   onclick="return confirm('Are you sure you want to delete this evacuation record?');">
                                   Delete
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="alert alert-info">No evacuation records found.</div>
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
