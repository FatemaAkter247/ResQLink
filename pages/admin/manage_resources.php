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

// INSERT
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $name = $_POST['name'];
    $category = $_POST['category'];
    $quantity = (int)$_POST['quantity'];
    $location = $_POST['location'];
    $contact = $_POST['contact'];
    $status = $_POST['status'];

    $stmt = $conn->prepare("
        INSERT INTO emergency_resources 
        (resource_name, category, quantity, location, contact, status)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param("ssisss", $name, $category, $quantity, $location, $contact, $status);

    if ($stmt->execute()) {
        $msg = "Added successfully!";
    } else {
        $msg = "Error: " . $stmt->error;
    }
}

// FETCH
$data = $conn->query("SELECT * FROM emergency_resources ORDER BY id DESC");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Resources</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body style="background:#f4f4f4;">

<div class="container mt-5">

    <h2 class="text-danger">Manage Resources</h2>

    <?php if ($msg): ?>
        <div class="alert alert-info"><?php echo $msg; ?></div>
    <?php endif; ?>

    <form method="POST" class="mb-4">
        <input name="name" class="form-control mb-2" placeholder="Name" required>

        <select name="category" class="form-control mb-2">
            <option>Food</option>
            <option>Water</option>
            <option>Medical</option>
            <option>Shelter</option>
            <option>Other</option>
        </select>

        <input name="quantity" type="number" class="form-control mb-2" placeholder="Quantity" required>
        <input name="location" class="form-control mb-2" placeholder="Location" required>
        <input name="contact" class="form-control mb-2" placeholder="Contact">

        <select name="status" class="form-control mb-2">
            <option>available</option>
            <option>limited</option>
            <option>unavailable</option>
        </select>

        <button class="btn btn-danger">Add Resource</button>
    </form>

    <h5>All Resources</h5>

    <?php while ($r = $data->fetch_assoc()): ?>
        <div class="card p-2 mb-2">
            <b><?php echo $r['resource_name']; ?></b>
            <br>Category: <?php echo $r['category']; ?>
            <br>Qty: <?php echo $r['quantity']; ?>
            <br>Status: <?php echo $r['status']; ?>
        </div>
    <?php endwhile; ?>

    <a href="../dashboard.php" class="btn btn-secondary mt-3">Back</a>

</div>

</body>
</html>