<?php
session_start();
require_once __DIR__ . "/../../DB/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

if ($_SESSION['role_id'] != 2) {
    die("Access denied");
}

$msg = "";

// INSERT RESOURCE
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $name = $_POST['name'];
    $resource_type = $_POST['resource_type']; // Updated to 'resource_type'
    $quantity = (int)$_POST['quantity'];
    $unit = $_POST['unit'];
    $status = $_POST['status'];
    $created_by = $_SESSION['user_id']; // Automatically associate with the logged-in user

    $stmt = $conn->prepare("
        INSERT INTO emergency_resources 
        (resource_name, resource_type, quantity, unit, status, created_by)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param("ssissi", $name, $resource_type, $quantity, $unit, $status, $created_by);

    if ($stmt->execute()) {
        $msg = "Added successfully!";
    } else {
        $msg = "Error: " . $stmt->error;
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
    <title>Manage Resources</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body style="background:#f4f4f4;">

<div class="container mt-5">

    <h2 class="text-danger">Manage Emergency Resources</h2>

    <?php if ($msg): ?>
        <div class="alert alert-info"><?php echo $msg; ?></div>
    <?php endif; ?>

    <!-- Form to Add Resources -->
    <form method="POST" class="mb-4">
        <input name="name" class="form-control mb-2" placeholder="Resource Name" required>

        <select name="resource_type" class="form-control mb-2">
            <option>Food</option>
            <option>Water</option>
            <option>Medical</option>
            <option>Shelter</option>
            <option>Other</option>
        </select>

        <input name="quantity" type="number" class="form-control mb-2" placeholder="Quantity" required>
        <input name="unit" class="form-control mb-2" placeholder="Unit (e.g., liters, bags)" required>

        <select name="status" class="form-control mb-2">
            <option>Available</option>
            <option>Limited</option>
            <option>Unavailable</option>
        </select>

        <button class="btn btn-danger">Add Resource</button>
    </form>

    <h5>All Resources</h5>

    <?php while ($r = $data->fetch_assoc()): ?>
        <div class="card p-2 mb-2">
            <b><?php echo $r['resource_name']; ?></b> (<?php echo $r['resource_type']; ?>)
            <br>Qty: <?php echo $r['quantity']; ?> <?php echo $r['unit']; ?>
            <br>Status: <?php echo $r['status']; ?>
        </div>
    <?php endwhile; ?>

    <a href="../dashboard.php" class="btn btn-secondary mt-3">Back</a>

</div>

</body>
</html>