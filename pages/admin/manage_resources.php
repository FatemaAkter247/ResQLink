<?php
session_start();
require_once __DIR__ . "/../../DB/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$message = "";
$edit_mode = false;
$edit_data = null;

/* ---------------- DELETE ---------------- */
if (isset($_GET['delete'])) {
    $delete_id = (int) $_GET['delete'];

    $stmt = $conn->prepare("DELETE FROM emergency_resources WHERE id = ?");
    $stmt->bind_param("i", $delete_id);

    if ($stmt->execute()) {
        header("Location: manage_resources.php?msg=deleted");
        exit;
    } else {
        $message = "Delete failed!";
    }
}

/* ---------------- EDIT LOAD ---------------- */
if (isset($_GET['edit'])) {
    $edit_id = (int) $_GET['edit'];

    $stmt = $conn->prepare("SELECT * FROM emergency_resources WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result_edit = $stmt->get_result();

    if ($result_edit->num_rows > 0) {
        $edit_data = $result_edit->fetch_assoc();
        $edit_mode = true;
    }
}

/* ---------------- ADD / UPDATE ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resource_name = trim($_POST['resource_name']);
    $resource_type = trim($_POST['resource_type']);
    $quantity      = (int) $_POST['quantity'];
    $unit          = trim($_POST['unit']);
    $status        = trim($_POST['status']);
    $created_by    = $_SESSION['user_id'];

    if (isset($_POST['update_id']) && !empty($_POST['update_id'])) {
        $update_id = (int) $_POST['update_id'];

        $stmt = $conn->prepare("UPDATE emergency_resources 
                                SET resource_name = ?, resource_type = ?, quantity = ?, unit = ?, status = ?, updated_at = NOW()
                                WHERE id = ?");
        $stmt->bind_param("ssissi", $resource_name, $resource_type, $quantity, $unit, $status, $update_id);

        if ($stmt->execute()) {
            header("Location: manage_resources.php?msg=updated");
            exit;
        } else {
            $message = "Update failed!";
        }
    } else {
        $stmt = $conn->prepare("INSERT INTO emergency_resources (created_by, resource_name, resource_type, quantity, unit, status, updated_at)
                                VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("ississ", $created_by, $resource_name, $resource_type, $quantity, $unit, $status);

        if ($stmt->execute()) {
            header("Location: manage_resources.php?msg=added");
            exit;
        } else {
            $message = "Insert failed!";
        }
    }
}

/* ---------------- MESSAGE ---------------- */
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'added') {
        $message = "Resource added successfully.";
    } elseif ($_GET['msg'] === 'updated') {
        $message = "Resource updated successfully.";
    } elseif ($_GET['msg'] === 'deleted') {
        $message = "Resource deleted successfully.";
    }
}

/* ---------------- FETCH ALL ---------------- */
$result = $conn->query("SELECT * FROM emergency_resources ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Resources</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body style="background:#f4f4f4;">

<div class="container mt-5">

    <h2 class="mb-4 text-danger">Manage Emergency Resources</h2>

    <?php if (!empty($message)): ?>
        <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <!-- Resource Form -->
    <div class="card mb-4">
        <div class="card-header bg-danger text-white">
            <?php echo $edit_mode ? 'Edit Resource' : 'Add New Resource'; ?>
        </div>
        <div class="card-body">
            <form method="POST">
                <?php if ($edit_mode): ?>
                    <input type="hidden" name="update_id" value="<?php echo $edit_data['id']; ?>">
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Resource Name</label>
                        <input type="text" name="resource_name" class="form-control"
                               value="<?php echo $edit_mode ? htmlspecialchars($edit_data['resource_name']) : ''; ?>" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Resource Type</label>
                        <input type="text" name="resource_type" class="form-control"
                               value="<?php echo $edit_mode ? htmlspecialchars($edit_data['resource_type']) : ''; ?>" required>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Quantity</label>
                        <input type="number" name="quantity" class="form-control"
                               value="<?php echo $edit_mode ? htmlspecialchars($edit_data['quantity']) : ''; ?>" required>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Unit</label>
                        <input type="text" name="unit" class="form-control"
                               value="<?php echo $edit_mode ? htmlspecialchars($edit_data['unit']) : ''; ?>" required>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control" required>
                            <option value="">Select Status</option>
                            <option value="available" <?php echo ($edit_mode && $edit_data['status'] === 'available') ? 'selected' : ''; ?>>Available</option>
                            <option value="unavailable" <?php echo ($edit_mode && $edit_data['status'] === 'unavailable') ? 'selected' : ''; ?>>Unavailable</option>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn btn-success">
                    <?php echo $edit_mode ? 'Update Resource' : 'Add Resource'; ?>
                </button>

                <?php if ($edit_mode): ?>
                    <a href="manage_resources.php" class="btn btn-secondary">Cancel</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Resource Table -->
    <div class="card">
        <div class="card-header bg-dark text-white">All Resources</div>
        <div class="card-body">
            <?php if ($result && $result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover align-middle">
                        <thead class="table-danger text-center">
                            <tr>
                                <th>ID</th>
                                <th>Resource Name</th>
                                <th>Type</th>
                                <th>Quantity</th>
                                <th>Unit</th>
                                <th>Status</th>
                                <th>Updated At</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr class="text-center">
                                    <td><?php echo $row['id']; ?></td>
                                    <td><?php echo htmlspecialchars($row['resource_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['resource_type']); ?></td>
                                    <td><?php echo htmlspecialchars($row['quantity']); ?></td>
                                    <td><?php echo htmlspecialchars($row['unit']); ?></td>
                                    <td><?php echo htmlspecialchars($row['status']); ?></td>
                                    <td><?php echo htmlspecialchars($row['updated_at']); ?></td>
                                    <td>
                                        <a href="manage_resources.php?edit=<?php echo $row['id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                                        <a href="manage_resources.php?delete=<?php echo $row['id']; ?>"
                                           class="btn btn-danger btn-sm"
                                           onclick="return confirm('Are you sure you want to delete this resource?');">
                                           Delete
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-warning mb-0">No resources found.</div>
            <?php endif; ?>
        </div>
    </div>

    <a href="../dashboard.php" class="btn btn-secondary mt-3">Back</a>

</div>

</body>
</html>