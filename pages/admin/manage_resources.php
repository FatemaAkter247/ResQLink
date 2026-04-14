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

$msg = "";

function formatResourceType($type) {
    $map = [
        'food' => 'Food',
        'medical' => 'Medical',
        'transport' => 'Transport',
        'shelter_kit' => 'Shelter Kit',
        'other' => 'Other'
    ];
    return $map[$type] ?? ucfirst(str_replace('_', ' ', $type));
}

function formatStatus($status) {
    $map = [
        'available' => 'Available',
        'allocated' => 'Allocated',
        'out_of_stock' => 'Out of Stock'
    ];
    return $map[$status] ?? ucfirst(str_replace('_', ' ', $status));
}

// INSERT RESOURCE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $resource_type = trim($_POST['resource_type'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 0);
    $unit = trim($_POST['unit'] ?? '');
    $status = trim($_POST['status'] ?? '');
    $created_by = (int)$_SESSION['user_id'];

    $allowedTypes = ['food', 'medical', 'transport', 'shelter_kit', 'other'];
    $allowedStatuses = ['available', 'allocated', 'out_of_stock'];

    if ($name === '' || $quantity <= 0 || $unit === '') {
        $msg = "Please fill in all fields correctly.";
    } elseif (!in_array($resource_type, $allowedTypes, true)) {
        $msg = "Invalid resource type selected.";
    } elseif (!in_array($status, $allowedStatuses, true)) {
        $msg = "Invalid status selected.";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO emergency_resources
            (created_by, resource_name, resource_type, quantity, unit, status, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->bind_param("ississ", $created_by, $name, $resource_type, $quantity, $unit, $status);

        if ($stmt->execute()) {
            $msg = "Resource added successfully!";
        } else {
            $msg = "Error: " . $stmt->error;
        }

        $stmt->close();
    }
}

// FETCH ALL RESOURCES
$data = $conn->query("SELECT * FROM emergency_resources ORDER BY id DESC");
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
            max-width: 950px;
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

        .resource-box {
            background: #f8f9fa;
            border-left: 5px solid #dc3545;
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 14px;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }

        .status-badge {
            text-transform: uppercase;
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

        <h2 class="page-title">Manage Emergency Resources</h2>

        <?php if ($msg): ?>
            <div class="alert alert-info"><?php echo htmlspecialchars($msg); ?></div>
        <?php endif; ?>

        <form method="POST" class="mb-4">
            <div class="mb-3">
                <input name="name" class="form-control" placeholder="Resource Name" required>
            </div>

            <div class="mb-3">
                <select name="resource_type" class="form-select" required>
                    <option value="food">Food</option>
                    <option value="medical">Medical</option>
                    <option value="transport">Transport</option>
                    <option value="shelter_kit">Shelter Kit</option>
                    <option value="other">Other</option>
                </select>
            </div>

            <div class="mb-3">
                <input name="quantity" type="number" class="form-control" placeholder="Quantity" min="1" required>
            </div>

            <div class="mb-3">
                <input name="unit" class="form-control" placeholder="Unit (e.g., liters, bags, boxes)" required>
            </div>

            <div class="mb-3">
                <select name="status" class="form-select" required>
                    <option value="available">Available</option>
                    <option value="allocated">Allocated</option>
                    <option value="out_of_stock">Out of Stock</option>
                </select>
            </div>

            <button class="btn btn-danger">Add Resource</button>
        </form>

        <h4 class="mb-3">All Resources</h4>

        <?php if ($data && $data->num_rows > 0): ?>
            <?php while ($r = $data->fetch_assoc()): ?>
                <div class="resource-box">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h5 class="mb-1 text-danger"><?php echo htmlspecialchars($r['resource_name']); ?></h5>
                        <span class="badge bg-<?php echo $r['status'] === 'available' ? 'success' : ($r['status'] === 'allocated' ? 'warning text-dark' : 'danger'); ?> status-badge">
                            <?php echo htmlspecialchars(formatStatus($r['status'])); ?>
                        </span>
                    </div>

                    <p class="mb-1"><strong>Type:</strong> <?php echo htmlspecialchars(formatResourceType($r['resource_type'])); ?></p>
                    <p class="mb-1"><strong>Quantity:</strong> <?php echo (int)$r['quantity']; ?> <?php echo htmlspecialchars($r['unit']); ?></p>
                    <p class="mb-0"><strong>Updated At:</strong> <?php echo htmlspecialchars($r['updated_at']); ?></p>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="alert alert-warning">No resources found.</div>
        <?php endif; ?>

        <a href="../dashboard.php" class="btn btn-secondary mt-3">Back</a>

    </div>
</div>

</body>
</html>