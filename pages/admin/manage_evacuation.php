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

$statusFilter = trim($_GET['status'] ?? '');

$sql = "
    SELECT
        es.id,
        es.user_id,
        es.status,
        es.notes,
        es.updated_at,
        u.full_name,
        u.phone,
        u.email,
        s.shelter_name,
        s.address,
        s.city
    FROM evacuation_status es
    INNER JOIN (
        SELECT user_id, MAX(id) AS latest_id
        FROM evacuation_status
        GROUP BY user_id
    ) latest ON es.id = latest.latest_id
    INNER JOIN users u ON es.user_id = u.id
    LEFT JOIN shelters s ON es.shelter_id = s.id
";

$params = [];
$types = "";

if ($statusFilter !== '' && in_array($statusFilter, ['safe', 'evacuated', 'need_help'], true)) {
    $sql .= " WHERE es.status = ? ";
    $params[] = $statusFilter;
    $types .= "s";
}

$sql .= " ORDER BY es.updated_at DESC, es.id DESC ";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$summary = [
    'safe' => 0,
    'evacuated' => 0,
    'need_help' => 0
];

$summaryRes = $conn->query("
    SELECT status, COUNT(*) AS total
    FROM (
        SELECT es.*
        FROM evacuation_status es
        INNER JOIN (
            SELECT user_id, MAX(id) AS latest_id
            FROM evacuation_status
            GROUP BY user_id
        ) latest ON es.id = latest.latest_id
    ) latest_status
    GROUP BY status
");

if ($summaryRes) {
    while ($row = $summaryRes->fetch_assoc()) {
        $summary[$row['status']] = (int)$row['total'];
    }
}
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
        }

        .summary-box {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 18px;
            text-align: center;
        }

        .summary-box h3 {
            color: #dc3545;
            margin-bottom: 0;
        }

        .record-box {
            background: #f8f9fa;
            border-left: 5px solid #dc3545;
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 14px;
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
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
            <div>
                <h2 class="page-title mb-1">Evacuation Monitoring</h2>
                <p class="text-muted mb-0">Monitor the latest evacuation status of all users.</p>
            </div>
            <a href="../dashboard.php" class="btn btn-secondary">Back</a>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="summary-box">
                    <p class="mb-1">Safe</p>
                    <h3><?php echo $summary['safe']; ?></h3>
                </div>
            </div>
            <div class="col-md-4">
                <div class="summary-box">
                    <p class="mb-1">Evacuated</p>
                    <h3><?php echo $summary['evacuated']; ?></h3>
                </div>
            </div>
            <div class="col-md-4">
                <div class="summary-box">
                    <p class="mb-1">Need Help</p>
                    <h3><?php echo $summary['need_help']; ?></h3>
                </div>
            </div>
        </div>

        <form method="GET" class="row g-2 mb-4">
            <div class="col-md-4">
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="safe" <?php echo $statusFilter === 'safe' ? 'selected' : ''; ?>>Safe</option>
                    <option value="evacuated" <?php echo $statusFilter === 'evacuated' ? 'selected' : ''; ?>>Evacuated</option>
                    <option value="need_help" <?php echo $statusFilter === 'need_help' ? 'selected' : ''; ?>>Need Help</option>
                </select>
            </div>
            <div class="col-md-auto">
                <button class="btn btn-danger">Filter</button>
            </div>
            <div class="col-md-auto">
                <a href="manage_evacuation.php" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>

        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <div class="record-box">
                    <h5 class="text-danger"><?php echo htmlspecialchars($row['full_name']); ?></h5>
                    <p class="mb-1"><strong>Status:</strong> <?php echo htmlspecialchars($row['status']); ?></p>
                    <p class="mb-1"><strong>Phone:</strong> <?php echo htmlspecialchars($row['phone']); ?></p>
                    <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($row['email'] ?? 'N/A'); ?></p>

                    <?php if (!empty($row['shelter_name'])): ?>
                        <p class="mb-1"><strong>Shelter:</strong> <?php echo htmlspecialchars($row['shelter_name']); ?></p>
                        <p class="mb-1"><strong>Address:</strong> <?php echo htmlspecialchars($row['address']); ?></p>
                        <p class="mb-1"><strong>City:</strong> <?php echo htmlspecialchars($row['city'] ?? 'N/A'); ?></p>
                    <?php endif; ?>

                    <?php if (!empty($row['notes'])): ?>
                        <p class="mb-1"><strong>Notes:</strong> <?php echo htmlspecialchars($row['notes']); ?></p>
                    <?php endif; ?>

                    <p class="mb-0"><strong>Last Updated:</strong> <?php echo htmlspecialchars($row['updated_at']); ?></p>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="alert alert-info">No evacuation records found.</div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>