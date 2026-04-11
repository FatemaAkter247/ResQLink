<?php
session_start();
require_once __DIR__ . "/../DB/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

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

$result = $conn->query("
    SELECT * FROM emergency_resources
    ORDER BY id DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emergency Resources - ResQLink</title>

    <link rel="stylesheet" href="../css/style.css">
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

        .status-badge {
            text-transform: uppercase;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
    <div class="container">
        <a href="../index.php" class="navbar-brand fw-bold">
            <span class="badge bg-danger">ResQLink</span>
        </a>
    </div>
</nav>

<div class="container">
    <div class="page-card">

        <h2 class="page-title">Emergency Resources</h2>

        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <div class="resource-box">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h4 class="mb-1 text-danger"><?php echo htmlspecialchars($row['resource_name']); ?></h4>
                        <span class="badge bg-<?php echo $row['status'] === 'available' ? 'success' : ($row['status'] === 'allocated' ? 'warning text-dark' : 'danger'); ?> status-badge">
                            <?php echo htmlspecialchars(formatStatus($row['status'])); ?>
                        </span>
                    </div>

                    <p class="mb-1"><strong>Type:</strong> <?php echo htmlspecialchars(formatResourceType($row['resource_type'])); ?></p>
                    <p class="mb-1"><strong>Quantity:</strong> <?php echo (int)$row['quantity']; ?> <?php echo htmlspecialchars($row['unit']); ?></p>
                    <p class="mb-0"><strong>Updated At:</strong> <?php echo htmlspecialchars($row['updated_at']); ?></p>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="alert alert-info">No resources available.</div>
        <?php endif; ?>

        <a href="dashboard.php" class="btn btn-secondary mt-3">Back</a>

    </div>
</div>

</body>
</html>