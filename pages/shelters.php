<?php
session_start();
require_once __DIR__ . "/../DB/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$result = $conn->query("SELECT * FROM shelters ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shelters - ResQLink</title>

    <link rel="stylesheet" href="../css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: linear-gradient(135deg, #dc3545 0%, #bb2d3b 100%);
            min-height: 100vh;
        }

        .container-box {
            max-width: 950px;
            margin: 60px auto;
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .shelter-card {
            border-left: 5px solid #dc3545;
            padding: 18px;
            margin-bottom: 15px;
            background: #f9f9f9;
            border-radius: 8px;
        }

        .status-badge {
            text-transform: uppercase;
        }
    </style>
</head>

<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="../index.php">
            <span class="badge bg-danger">ResQLink</span>
        </a>
    </div>
</nav>

<<div class="container-box">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <h2 class="text-danger mb-0">Available Shelters</h2>

    <a href="recommend_shelter.php" class="btn btn-danger">
        Smart Shelter Recommendation
    </a>
</div>
    

    <?php if ($result && $result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
            <?php
                $available = (int)$row['total_capacity'] - (int)$row['current_occupancy'];
                if ($available < 0) {
                    $available = 0;
                }
            ?>
            <div class="shelter-card">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                    <h5 class="mb-0"><?php echo htmlspecialchars($row['shelter_name']); ?></h5>
                    <span class="badge bg-<?php echo $row['status'] === 'full' ? 'danger' : ($row['status'] === 'closed' ? 'secondary' : 'success'); ?> status-badge">
                        <?php echo htmlspecialchars($row['status']); ?>
                    </span>
                </div>

                <p class="mb-1"><strong>Address:</strong> <?php echo htmlspecialchars($row['address']); ?></p>
                <p class="mb-1"><strong>City:</strong> <?php echo htmlspecialchars($row['city'] ?? 'N/A'); ?></p>
                <p class="mb-1"><strong>Total Capacity:</strong> <?php echo (int)$row['total_capacity']; ?></p>
                <p class="mb-1"><strong>Current Occupancy:</strong> <?php echo (int)$row['current_occupancy']; ?></p>
                <p class="mb-0"><strong>Available Space:</strong> <?php echo $available; ?></p>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="alert alert-info">No shelters found.</div>
    <?php endif; ?>

    <a href="dashboard.php" class="btn btn-secondary mt-3">Back</a>
</div>

</body>
</html>