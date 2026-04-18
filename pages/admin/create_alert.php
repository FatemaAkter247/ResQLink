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

    <style>
        body {
            background: linear-gradient(135deg, #dc3545 0%, #bb2d3b 100%);
            min-height: 100vh;
        }

        .page-card {
            width: 100%;
            max-width: 1100px;
            margin: 60px auto;
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.18);
            padding: 2rem;
        }

        .page-title {
            color: #dc3545;
            font-weight: 700;
            margin-bottom: 1.5rem;
        }

        .form-control:focus,
        .form-select:focus,
        textarea:focus {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }

        .record-box {
            background: #f8f9fa;
            border-left: 5px solid #dc3545;
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 14px;
        }

        .btn-red {
            background-color: #dc3545;
            border-color: #dc3545;
            color: #fff;
        }

        .btn-red:hover {
            background-color: #bb2d3b;
            border-color: #bb2d3b;
            color: #fff;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="../../index.php">
            <span class="badge bg-danger">ResQLink</span>
        </a>
    </div>
</nav>

<div class="container">
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
                            <h5 class="text-danger mb-1"><?php echo htmlspecialchars($row['alert_type']); ?></h5>
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
</div>

</body>
</html>