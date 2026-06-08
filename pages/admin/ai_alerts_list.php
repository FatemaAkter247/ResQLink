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

// Build WHERE clause for filters
$where = ["1=1"];
$params = [];
$types = "";

// Filter by alert type
if (!empty($_GET['filter_alert_type'])) {
    $where[] = "alert_type LIKE ?";
    $params[] = "%" . $_GET['filter_alert_type'] . "%";
    $types .= "s";
}

// Filter by location
if (!empty($_GET['filter_location'])) {
    $where[] = "location_text LIKE ?";
    $params[] = "%" . $_GET['filter_location'] . "%";
    $types .= "s";
}

// Filter by status
if (!empty($_GET['filter_status'])) {
    $where[] = "status = ?";
    $params[] = $_GET['filter_status'];
    $types .= "s";
}

$whereClause = implode(" AND ", $where);

// Get filtered alerts
if (!empty($params)) {
    $stmt = $conn->prepare("SELECT * FROM ai_generated_alerts WHERE $whereClause ORDER BY id DESC");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $alerts = $stmt->get_result();
    $stmt->close();
} else {
    $alerts = $conn->query("SELECT * FROM ai_generated_alerts ORDER BY id DESC");
}

// Get unique values for filter dropdowns
$alertTypes = $conn->query("SELECT DISTINCT alert_type FROM ai_generated_alerts ORDER BY alert_type");
$locations = $conn->query("SELECT DISTINCT location_text FROM ai_generated_alerts ORDER BY location_text");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Alerts List - ResQLink</title>

    <link rel="stylesheet" href="../../css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .page-card {
            width: 100%;
            max-width: 1200px;
            margin: 60px auto;
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.18);
            padding: 2rem;
        }

        .page-title {
            color: #667eea;
            font-weight: 700;
            margin-bottom: 1.5rem;
        }

        .table {
            margin-top: 20px;
        }

        .table thead th {
            background-color: #667eea;
            color: white;
            border: none;
        }

        .table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .btn-purple {
            background-color: #667eea;
            border-color: #667eea;
            color: #fff;
        }

        .btn-purple:hover {
            background-color: #5a6fd6;
            border-color: #5a6fd6;
            color: #fff;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }

        .status-draft {
            background-color: #ffc107;
            color: #000;
        }

        .status-approved {
            background-color: #17a2b8;
            color: white;
        }

        .status-published {
            background-color: #28a745;
            color: white;
        }

        .severity-low {
            color: #28a745;
            font-weight: 600;
        }

        .severity-medium {
            color: #ffc107;
            font-weight: 600;
        }

        .severity-high {
            color: #fd7e14;
            font-weight: 600;
        }

        .severity-critical {
            color: #dc3545;
            font-weight: 600;
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="page-title mb-0">AI Generated Alerts</h2>
            <a href="ai_alert_generator.php" class="btn btn-purple">
                Create New AI Alert
            </a>
        </div>

        <!-- Filters -->
        <form method="GET" class="mb-4">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Filter by Alert Type</label>
                    <input type="text" name="filter_alert_type" class="form-control" 
                           value="<?php echo htmlspecialchars($_GET['filter_alert_type'] ?? ''); ?>"
                           placeholder="Search alert type...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Filter by Location</label>
                    <input type="text" name="filter_location" class="form-control" 
                           value="<?php echo htmlspecialchars($_GET['filter_location'] ?? ''); ?>"
                           placeholder="Search location...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Filter by Status</label>
                    <select name="filter_status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="draft" <?php echo (isset($_GET['filter_status']) && $_GET['filter_status'] === 'draft') ? 'selected' : ''; ?>>Draft</option>
                        <option value="approved" <?php echo (isset($_GET['filter_status']) && $_GET['filter_status'] === 'approved') ? 'selected' : ''; ?>>Approved</option>
                        <option value="published" <?php echo (isset($_GET['filter_status']) && $_GET['filter_status'] === 'published') ? 'selected' : ''; ?>>Published</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">Filter</button>
                    <a href="ai_alerts_list.php" class="btn btn-secondary">Clear</a>
                </div>
            </div>
        </form>

        <!-- Alerts Table -->
        <?php if ($alerts && $alerts->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Alert Type</th>
                            <th>Location</th>
                            <th>Severity</th>
                            <th>Status</th>
                            <th>Created At</th>
                            <th>View</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $alerts->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo (int)$row['id']; ?></td>
                                <td><?php echo htmlspecialchars($row['alert_type']); ?></td>
                                <td><?php echo htmlspecialchars($row['location_text']); ?></td>
                                <td class="severity-<?php echo htmlspecialchars($row['severity']); ?>">
                                    <?php echo htmlspecialchars(ucfirst($row['severity'])); ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo htmlspecialchars($row['status']); ?>">
                                        <?php echo htmlspecialchars(ucfirst($row['status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                                <td>
                                    <a href="ai_alert_view.php?id=<?php echo (int)$row['id']; ?>" 
                                       class="btn btn-sm btn-info">
                                        View
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                No AI-generated alerts found.
                <?php if (!empty($_GET['filter_alert_type']) || !empty($_GET['filter_location']) || !empty($_GET['filter_status'])): ?>
                    <a href="ai_alerts_list.php" class="alert-link">Clear filters</a> to see all alerts.
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="mt-4">
            <a href="../dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>
    </div>
</div>

</body>
</html>
