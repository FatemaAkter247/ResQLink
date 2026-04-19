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
    'shelter_name' => '',
    'address' => '',
    'city' => '',
    'total_capacity' => '',
    'current_occupancy' => '',
    'status' => 'open'
];

/* ---------------- DELETE ---------------- */
if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];

    $stmt = $conn->prepare("DELETE FROM shelters WHERE id = ?");
    $stmt->bind_param("i", $delete_id);

    if ($stmt->execute()) {
        header("Location: manage_shelters.php?msg=deleted");
        exit;
    } else {
        $error = "Failed to delete shelter.";
    }
    $stmt->close();
}

/* ---------------- EDIT LOAD ---------------- */
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];

    $stmt = $conn->prepare("SELECT * FROM shelters WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $resultEdit = $stmt->get_result();

    if ($resultEdit && $resultEdit->num_rows === 1) {
        $editData = $resultEdit->fetch_assoc();
        $editMode = true;
    } else {
        $error = "Shelter not found.";
    }
    $stmt->close();
}

/* ---------------- ADD / UPDATE ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $created_by = (int)$_SESSION['user_id'];
    $shelter_name = trim($_POST['shelter_name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $total_capacity = (int)($_POST['total_capacity'] ?? 0);
    $current_occupancy = (int)($_POST['current_occupancy'] ?? 0);
    $status = trim($_POST['status'] ?? 'open');

    if ($shelter_name === '' || $address === '' || $city === '' || $total_capacity <= 0 || $current_occupancy < 0) {
        $error = "Please fill all fields correctly.";
    } elseif ($current_occupancy > $total_capacity) {
        $error = "Current occupancy cannot be greater than total capacity.";
    } else {
        if ($current_occupancy >= $total_capacity && $status !== 'closed') {
            $status = 'full';
        }

        if ($id > 0) {
            $stmt = $conn->prepare("
                UPDATE shelters
                SET shelter_name = ?, address = ?, city = ?, total_capacity = ?, current_occupancy = ?, status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param(
                "sssiisi",
                $shelter_name,
                $address,
                $city,
                $total_capacity,
                $current_occupancy,
                $status,
                $id
            );

            if ($stmt->execute()) {
                header("Location: manage_shelters.php?msg=updated");
                exit;
            } else {
                $error = "Failed to update shelter.";
            }
            $stmt->close();
        } else {
            $stmt = $conn->prepare("
                INSERT INTO shelters
                (created_by, shelter_name, address, city, total_capacity, current_occupancy, status, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param(
                "isssiis",
                $created_by,
                $shelter_name,
                $address,
                $city,
                $total_capacity,
                $current_occupancy,
                $status
            );

            if ($stmt->execute()) {
                header("Location: manage_shelters.php?msg=added");
                exit;
            } else {
                $error = "Failed to add shelter.";
            }
            $stmt->close();
        }
    }
}

/* ---------------- SUCCESS MSG ---------------- */
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'added') {
        $success = "Shelter added successfully!";
    } elseif ($_GET['msg'] === 'updated') {
        $success = "Shelter updated successfully!";
    } elseif ($_GET['msg'] === 'deleted') {
        $success = "Shelter deleted successfully!";
    }
}

$result = $conn->query("SELECT * FROM shelters ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Shelters - ResQLink</title>

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

        .shelter-box {
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
        <h2 class="page-title"><?php echo $editMode ? 'Update Shelter' : 'Manage Shelters'; ?></h2>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" class="mb-4">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($editData['id']); ?>">

            <div class="mb-3">
                <label class="form-label">Shelter Name</label>
                <input type="text" name="shelter_name" class="form-control" required
                       value="<?php echo htmlspecialchars($editData['shelter_name']); ?>"
                       placeholder="Enter shelter name">
            </div>

            <div class="mb-3">
                <label class="form-label">Address / Location</label>
                <input type="text" name="address" class="form-control" required
                       value="<?php echo htmlspecialchars($editData['address']); ?>"
                       placeholder="Enter shelter address">
            </div>

            <div class="mb-3">
                <label class="form-label">City</label>
                <input type="text" name="city" class="form-control" required
                       value="<?php echo htmlspecialchars($editData['city']); ?>"
                       placeholder="Enter city">
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Total Capacity</label>
                    <input type="number" name="total_capacity" class="form-control" min="1" required
                           value="<?php echo htmlspecialchars($editData['total_capacity']); ?>">
                </div>

                <div class="col-md-4 mb-3">
                    <label class="form-label">Current Occupancy</label>
                    <input type="number" name="current_occupancy" class="form-control" min="0" required
                           value="<?php echo htmlspecialchars($editData['current_occupancy']); ?>">
                </div>

                <div class="col-md-4 mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select" required>
                        <option value="open" <?php echo $editData['status'] === 'open' ? 'selected' : ''; ?>>Open</option>
                        <option value="full" <?php echo $editData['status'] === 'full' ? 'selected' : ''; ?>>Full</option>
                        <option value="closed" <?php echo $editData['status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
                    </select>
                </div>
            </div>

            <div class="d-flex gap-2 flex-wrap">
                <button type="submit" class="btn btn-red">
                    <?php echo $editMode ? 'Update Shelter' : 'Add Shelter'; ?>
                </button>

                <?php if ($editMode): ?>
                    <a href="manage_shelters.php" class="btn btn-secondary">Cancel</a>
                <?php else: ?>
                    <a href="../dashboard.php" class="btn btn-secondary">Back</a>
                <?php endif; ?>
            </div>
        </form>

        <hr>

        <h4 class="mb-3">All Shelters</h4>

        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <?php
                    $available = (int)$row['total_capacity'] - (int)$row['current_occupancy'];
                    if ($available < 0) {
                        $available = 0;
                    }
                ?>
                <div class="shelter-box">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                        <div>
                            <h5 class="mb-1 text-danger"><?php echo htmlspecialchars($row['shelter_name']); ?></h5>
                            <p class="mb-1"><strong>Address:</strong> <?php echo htmlspecialchars($row['address']); ?></p>
                            <p class="mb-1"><strong>City:</strong> <?php echo htmlspecialchars($row['city']); ?></p>
                            <p class="mb-1"><strong>Total Capacity:</strong> <?php echo (int)$row['total_capacity']; ?></p>
                            <p class="mb-1"><strong>Current Occupancy:</strong> <?php echo (int)$row['current_occupancy']; ?></p>
                            <p class="mb-1"><strong>Available Space:</strong> <?php echo $available; ?></p>
                            <p class="mb-0"><strong>Status:</strong> <?php echo htmlspecialchars($row['status']); ?></p>
                        </div>

                        <div class="d-flex gap-2">
                            <a href="manage_shelters.php?edit=<?php echo (int)$row['id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                            <a href="manage_shelters.php?delete=<?php echo (int)$row['id']; ?>"
                               class="btn btn-danger btn-sm"
                               onclick="return confirm('Are you sure you want to delete this shelter?');">
                               Delete
                            </a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="alert alert-info">No shelters added yet.</div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>