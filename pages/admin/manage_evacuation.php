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
                            <h5 class="text-danger mb-1"><?php echo htmlspecialchars($row['full_name'] ?? 'Unknown User'); ?></h5>
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
</div>

</body>
</html>