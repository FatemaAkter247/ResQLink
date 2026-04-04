<?php
session_start();
require_once __DIR__ . "/../DB/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$success = "";
$error = "";

// shelters for dropdown
$shelters = $conn->query("
    SELECT id, shelter_name, address, city, total_capacity, current_occupancy, status
    FROM shelters
    WHERE status IN ('open', 'full')
    ORDER BY shelter_name ASC
");

// latest status for this user
$currentStatus = null;
$stmt = $conn->prepare("
    SELECT es.*, s.shelter_name, s.address, s.city
    FROM evacuation_status es
    LEFT JOIN shelters s ON es.shelter_id = s.id
    WHERE es.user_id = ?
    ORDER BY es.id DESC
    LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res && $res->num_rows > 0) {
    $currentStatus = $res->fetch_assoc();
}
$stmt->close();

// submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = trim($_POST['status'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $shelter_id = $_POST['shelter_id'] ?? '';

    $allowed = ['safe', 'evacuated', 'need_help'];

    if (!in_array($status, $allowed, true)) {
        $error = "Please select a valid status.";
    } elseif ($status === 'evacuated' && $shelter_id === '') {
        $error = "Please select a shelter if you are evacuated.";
    } else {
        $shelter_value = ($shelter_id === '') ? null : (int)$shelter_id;

        $insert = $conn->prepare("
            INSERT INTO evacuation_status (user_id, status, shelter_id, notes, updated_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $insert->bind_param("isis", $user_id, $status, $shelter_value, $notes);

        if ($insert->execute()) {
            $success = "Evacuation status saved successfully.";
        } else {
            $error = "Failed to save evacuation status.";
        }
        $insert->close();

        // reload latest
        $stmt = $conn->prepare("
            SELECT es.*, s.shelter_name, s.address, s.city
            FROM evacuation_status es
            LEFT JOIN shelters s ON es.shelter_id = s.id
            WHERE es.user_id = ?
            ORDER BY es.id DESC
            LIMIT 1
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $currentStatus = $res->fetch_assoc();
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evacuation Status - ResQLink</title>

    <link rel="stylesheet" href="../css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: linear-gradient(135deg, #dc3545 0%, #bb2d3b 100%);
            min-height: 100vh;
        }

        .page-card {
            width: 100%;
            max-width: 900px;
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

        .info-box {
            background: #f8f9fa;
            border-left: 5px solid #dc3545;
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 20px;
        }

        .form-control:focus,
        .form-select:focus,
        textarea:focus {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
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

<div class="container">
    <div class="page-card">
        <h2 class="page-title">Evacuation Status Tracking</h2>
        <p class="text-muted">Update your current situation during an emergency.</p>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($currentStatus): ?>
            <div class="info-box">
                <h5>Latest Status</h5>
                <p class="mb-1"><strong>Status:</strong> <?php echo htmlspecialchars($currentStatus['status']); ?></p>

                <?php if (!empty($currentStatus['shelter_name'])): ?>
                    <p class="mb-1"><strong>Shelter:</strong> <?php echo htmlspecialchars($currentStatus['shelter_name']); ?></p>
                    <p class="mb-1"><strong>Address:</strong> <?php echo htmlspecialchars($currentStatus['address']); ?></p>
                    <p class="mb-1"><strong>City:</strong> <?php echo htmlspecialchars($currentStatus['city'] ?? 'N/A'); ?></p>
                <?php endif; ?>

                <?php if (!empty($currentStatus['notes'])): ?>
                    <p class="mb-1"><strong>Notes:</strong> <?php echo htmlspecialchars($currentStatus['notes']); ?></p>
                <?php endif; ?>

                <p class="mb-0"><strong>Last Updated:</strong> <?php echo htmlspecialchars($currentStatus['updated_at']); ?></p>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Select Status</label>
                <select name="status" id="statusSelect" class="form-select" required>
                    <option value="">Choose status</option>
                    <option value="safe">Safe</option>
                    <option value="evacuated">Evacuated</option>
                    <option value="need_help">Need Help</option>
                </select>
            </div>

            <div class="mb-3" id="shelterWrap">
                <label class="form-label">Select Shelter</label>
                <select name="shelter_id" class="form-select">
                    <option value="">No shelter selected</option>
                    <?php if ($shelters): ?>
                        <?php while ($shelter = $shelters->fetch_assoc()): ?>
                            <?php
                            $available = (int)$shelter['total_capacity'] - (int)$shelter['current_occupancy'];
                            if ($available < 0) $available = 0;
                            ?>
                            <option value="<?php echo (int)$shelter['id']; ?>">
                                <?php echo htmlspecialchars($shelter['shelter_name'] . " - " . $shelter['city'] . " - Available: " . $available); ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
                <div class="form-text">Select a shelter only if you are evacuated.</div>
            </div>

            <div class="mb-3">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="4" placeholder="Add any extra details..."></textarea>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-danger">Save Status</button>
                <a href="dashboard.php" class="btn btn-secondary">Back</a>
            </div>
        </form>
    </div>
</div>

<script>
const statusSelect = document.getElementById('statusSelect');
const shelterWrap = document.getElementById('shelterWrap');

function toggleShelterField() {
    shelterWrap.style.display = statusSelect.value === 'evacuated' ? 'block' : 'none';
}
toggleShelterField();
statusSelect.addEventListener('change', toggleShelterField);
</script>

</body>
</html>