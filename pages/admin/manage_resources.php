<?php
session_start();
require_once __DIR__ . "/../../DB/db.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

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
    'resource_name' => '',
    'resource_type' => 'other',
    'quantity' => '',
    'unit' => 'unit',
    'status' => 'available'
];

/* ---------------- DELETE ---------------- */
if (isset($_GET['delete'])) {
    $delete_id = (int)($_GET['delete'] ?? 0);

    if ($delete_id > 0) {
        try {
            $conn->begin_transaction();

            // Delete dependent rows first (safe even if none exist)
            $stmtAlloc = $conn->prepare("DELETE FROM resource_allocations WHERE resource_id = ?");
            $stmtAlloc->bind_param("i", $delete_id);
            $stmtAlloc->execute();
            $stmtAlloc->close();

            // Delete main resource
            $stmt = $conn->prepare("DELETE FROM emergency_resources WHERE id = ?");
            $stmt->bind_param("i", $delete_id);
            $stmt->execute();
            $stmt->close();

            $conn->commit();

            header("Location: manage_resources.php?msg=deleted");
            exit;
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            $error = "Delete failed: " . $e->getMessage();
        }
    } else {
        $error = "Invalid resource ID.";
    }
}

/* ---------------- EDIT LOAD ---------------- */
if (isset($_GET['edit'])) {
    $edit_id = (int)($_GET['edit'] ?? 0);

    if ($edit_id > 0) {
        try {
            $stmt = $conn->prepare("SELECT * FROM emergency_resources WHERE id = ?");
            $stmt->bind_param("i", $edit_id);
            $stmt->execute();
            $resultEdit = $stmt->get_result();

            if ($resultEdit && $resultEdit->num_rows === 1) {
                $editData = $resultEdit->fetch_assoc();
                $editMode = true;
            } else {
                $error = "Resource not found.";
            }
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            $error = "Failed to load resource: " . $e->getMessage();
        }
    }
}

/* ---------------- ADD / UPDATE ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $created_by = (int)$_SESSION['user_id'];
    $resource_name = trim($_POST['resource_name'] ?? '');
    $resource_type = trim($_POST['resource_type'] ?? 'other');
    $quantity = (int)($_POST['quantity'] ?? 0);
    $unit = trim($_POST['unit'] ?? 'unit');
    $status = trim($_POST['status'] ?? 'available');

    $allowedTypes = ['food', 'medical', 'transport', 'shelter_kit', 'other'];
    $allowedStatuses = ['available', 'allocated', 'out_of_stock'];

    if ($resource_name === '' || $quantity < 0 || $unit === '') {
        $error = "Please fill all fields correctly.";
    } else {
        if (!in_array($resource_type, $allowedTypes, true)) {
            $resource_type = 'other';
        }

        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'available';
        }

        try {
            if ($id > 0) {
                $stmt = $conn->prepare("
                    UPDATE emergency_resources
                    SET resource_name = ?, resource_type = ?, quantity = ?, unit = ?, status = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->bind_param("ssissi", $resource_name, $resource_type, $quantity, $unit, $status, $id);
                $stmt->execute();
                $stmt->close();

                header("Location: manage_resources.php?msg=updated");
                exit;
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO emergency_resources
                    (created_by, resource_name, resource_type, quantity, unit, status, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->bind_param("ississ", $created_by, $resource_name, $resource_type, $quantity, $unit, $status);
                $stmt->execute();
                $stmt->close();

                header("Location: manage_resources.php?msg=added");
                exit;
            }
        } catch (mysqli_sql_exception $e) {
            $error = "Database operation failed: " . $e->getMessage();
        }
    }
}

/* ---------------- SUCCESS MSG ---------------- */
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'added') {
        $success = "Resource added successfully!";
    } elseif ($_GET['msg'] === 'updated') {
        $success = "Resource updated successfully!";
    } elseif ($_GET['msg'] === 'deleted') {
        $success = "Resource deleted successfully!";
    }
}

try {
    $result = $conn->query("SELECT * FROM emergency_resources ORDER BY id DESC");
} catch (mysqli_sql_exception $e) {
    $result = false;
    $error = "Failed to fetch resources: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Resources - ResQLink</title>

    <link rel="stylesheet" href="../../css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: linear-gradient(135deg, #dc3545 0%, #bb2d3b 100%);
            min-height: 100vh;
        }

        .page-card {
            width: 100%;
            max-width: 1050px;
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
        .form-select:focus {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }

        .resource-box {
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
        <h2 class="page-title"><?php echo $editMode ? 'Update Resource' : 'Manage Emergency Resources'; ?></h2>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" class="mb-4">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($editData['id']); ?>">

            <div class="mb-3">
                <label class="form-label">Resource Name</label>
                <input type="text" name="resource_name" class="form-control" required
                       value="<?php echo htmlspecialchars($editData['resource_name']); ?>"
                       placeholder="Enter resource name">
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Resource Type</label>
                    <select name="resource_type" class="form-select" required>
                        <option value="food" <?php echo $editData['resource_type'] === 'food' ? 'selected' : ''; ?>>Food</option>
                        <option value="medical" <?php echo $editData['resource_type'] === 'medical' ? 'selected' : ''; ?>>Medical</option>
                        <option value="transport" <?php echo $editData['resource_type'] === 'transport' ? 'selected' : ''; ?>>Transport</option>
                        <option value="shelter_kit" <?php echo $editData['resource_type'] === 'shelter_kit' ? 'selected' : ''; ?>>Shelter Kit</option>
                        <option value="other" <?php echo $editData['resource_type'] === 'other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>

                <div class="col-md-4 mb-3">
                    <label class="form-label">Quantity</label>
                    <input type="number" name="quantity" class="form-control" min="0" required
                           value="<?php echo htmlspecialchars($editData['quantity']); ?>">
                </div>

                <div class="col-md-4 mb-3">
                    <label class="form-label">Unit</label>
                    <input type="text" name="unit" class="form-control" required
                           value="<?php echo htmlspecialchars($editData['unit']); ?>"
                           placeholder="e.g. packets, liters, boxes">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select" required>
                    <option value="available" <?php echo $editData['status'] === 'available' ? 'selected' : ''; ?>>Available</option>
                    <option value="allocated" <?php echo $editData['status'] === 'allocated' ? 'selected' : ''; ?>>Allocated</option>
                    <option value="out_of_stock" <?php echo $editData['status'] === 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
                </select>
            </div>

            <div class="d-flex gap-2 flex-wrap">
                <button type="submit" class="btn btn-red">
                    <?php echo $editMode ? 'Update Resource' : 'Add Resource'; ?>
                </button>

                <?php if ($editMode): ?>
                    <a href="manage_resources.php" class="btn btn-secondary">Cancel</a>
                <?php else: ?>
                    <a href="../dashboard.php" class="btn btn-secondary">Back</a>
                <?php endif; ?>
            </div>
        </form>

        <hr>

        <h4 class="mb-3">All Resources</h4>

        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <div class="resource-box">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                        <div>
                            <h5 class="mb-1 text-danger"><?php echo htmlspecialchars($row['resource_name']); ?></h5>
                            <p class="mb-1"><strong>Type:</strong> <?php echo htmlspecialchars($row['resource_type']); ?></p>
                            <p class="mb-1"><strong>Quantity:</strong> <?php echo (int)$row['quantity'] . ' ' . htmlspecialchars($row['unit']); ?></p>
                            <p class="mb-1"><strong>Status:</strong> <?php echo htmlspecialchars($row['status']); ?></p>
                            <p class="mb-0"><strong>Updated At:</strong> <?php echo htmlspecialchars($row['updated_at']); ?></p>
                        </div>

                        <div class="d-flex gap-2">
                            <a href="manage_resources.php?edit=<?php echo (int)$row['id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                            <a href="manage_resources.php?delete=<?php echo (int)$row['id']; ?>"
                               class="btn btn-danger btn-sm"
                               onclick="return confirm('Are you sure you want to delete this resource?');">
                               Delete
                            </a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="alert alert-info">No resources added yet.</div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>