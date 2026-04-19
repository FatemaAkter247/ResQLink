<?php
session_start();
require_once __DIR__ . "/../DB/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];

if (isset($_GET['read'])) {
    $nid = (int) $_GET['read'];
    $conn->query("UPDATE alert_notifications SET is_read = 1 WHERE id = $nid AND user_id = $user_id");
    header("Location: alerts.php");
    exit;
}

$sql = "
SELECT an.id AS notif_id, an.is_read,
       da.alert_type, da.location_text, da.severity, da.instructions, da.created_at
FROM alert_notifications an
JOIN disaster_alerts da ON an.alert_id = da.id
WHERE an.user_id = $user_id
AND da.status = 'published'
ORDER BY da.created_at DESC
";

$result = $conn->query($sql);

function getAlertBackground($alertType)
{
    $type = strtolower(trim($alertType));

    if (strpos($type, 'cyclone') !== false) {
        return '../uploads/default_alerts/cyclone.jpg';
    } elseif (strpos($type, 'fire') !== false) {
        return '../uploads/default_alerts/fire.jpg';
    } elseif (strpos($type, 'flood') !== false) {
        return '../uploads/default_alerts/flood.jpg';
    } elseif (strpos($type, 'earthquake') !== false) {
        return '../uploads/default_alerts/earthquake.jpg';
    } else {
        return '../uploads/default_alerts/default.jpg';
    }
}

function getAlertIcon($alertType)
{
    $type = strtolower(trim($alertType));

    if (strpos($type, 'cyclone') !== false) {
        return 'fa-solid fa-wind';
    } elseif (strpos($type, 'fire') !== false) {
        return 'fa-solid fa-fire';
    } elseif (strpos($type, 'flood') !== false) {
        return 'fa-solid fa-water';
    } elseif (strpos($type, 'earthquake') !== false) {
        return 'fa-solid fa-house-crack';
    } else {
        return 'fa-solid fa-triangle-exclamation';
    }
}

function getSeverityClass($severity)
{
    $severity = strtolower(trim($severity));

    switch ($severity) {
        case 'low':
            return 'bg-success';
        case 'medium':
            return 'bg-warning text-dark';
        case 'high':
            return 'bg-danger';
        case 'critical':
            return 'bg-dark';
        default:
            return 'bg-secondary';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Alerts - ResQLink</title>

    <link rel="stylesheet" href="../css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        body {
            background: linear-gradient(135deg, #dc3545 0%, #bb2d3b 100%);
            min-height: 100vh;
        }

        .container-box {
            max-width: 1000px;
            margin: 60px auto;
            background: rgba(255, 255, 255, 0.96);
            padding: 30px;
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .page-title {
            color: #dc3545;
            font-weight: 700;
            margin-bottom: 25px;
        }

        .alert-card {
            position: relative;
            overflow: hidden;
            border-radius: 14px;
            margin-bottom: 20px;
            min-height: 260px;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.18);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .alert-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 14px 30px rgba(0, 0, 0, 0.22);
        }

        .alert-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(to right, rgba(0, 0, 0, 0.75), rgba(0, 0, 0, 0.42));
        }

        .alert-content {
            position: relative;
            z-index: 2;
            color: #fff;
            padding: 24px;
        }

        .alert-title {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .alert-title-row {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .alert-icon {
            font-size: 1.35rem;
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.18);
            color: #ffd54f;
            box-shadow: 0 0 12px rgba(255, 213, 79, 0.35);
            backdrop-filter: blur(3px);
        }

        .unread-dot {
            width: 10px;
            height: 10px;
            background: #00ff88;
            border-radius: 50%;
            display: inline-block;
            box-shadow: 0 0 10px rgba(0, 255, 136, 0.8);
        }

        .alert-meta {
            font-size: 0.95rem;
            margin-bottom: 8px;
        }

        .alert-instructions {
            margin-top: 14px;
            font-size: 1rem;
            line-height: 1.6;
            background: rgba(255, 255, 255, 0.12);
            padding: 12px 14px;
            border-radius: 10px;
            backdrop-filter: blur(2px);
        }

        .alert-footer {
            margin-top: 16px;
        }

        .mark-btn {
            font-weight: 600;
        }

        .read-badge {
            background: rgba(255, 255, 255, 0.18);
            color: #fff;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
        }

        .icon-fire {
            color: #ff7043;
        }

        .icon-flood {
            color: #4fc3f7;
        }

        .icon-cyclone {
            color: #cfd8dc;
        }

        .icon-earthquake {
            color: #ffca28;
        }

        .icon-default {
            color: #ffd54f;
        }

        @media (max-width: 768px) {
            .container-box {
                margin: 25px 15px;
                padding: 20px;
            }

            .alert-card {
                min-height: 290px;
            }

            .alert-content {
                padding: 18px;
            }

            .alert-title {
                font-size: 1.15rem;
            }

            .alert-title-row {
                gap: 10px;
            }

            .alert-icon {
                width: 38px;
                height: 38px;
                font-size: 1.1rem;
            }
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

<div class="container-box">
    <h2 class="page-title">Disaster Alerts</h2>

    <?php if ($result && $result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
            <?php
                $bgImage = getAlertBackground($row['alert_type']);
                $iconClass = getAlertIcon($row['alert_type']);
                $severityClass = getSeverityClass($row['severity']);

                $iconColorClass = 'icon-default';
                $type = strtolower(trim($row['alert_type']));

                if (strpos($type, 'fire') !== false) {
                    $iconColorClass = 'icon-fire';
                } elseif (strpos($type, 'flood') !== false) {
                    $iconColorClass = 'icon-flood';
                } elseif (strpos($type, 'cyclone') !== false) {
                    $iconColorClass = 'icon-cyclone';
                } elseif (strpos($type, 'earthquake') !== false) {
                    $iconColorClass = 'icon-earthquake';
                }
            ?>

            <div class="alert-card" style="background-image: url('<?php echo htmlspecialchars($bgImage); ?>');">
                <div class="alert-overlay"></div>

                <div class="alert-content">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                        <div class="alert-title">
                            <div class="alert-title-row">
                                <span class="alert-icon">
                                    <i class="<?php echo htmlspecialchars($iconClass . ' ' . $iconColorClass); ?>"></i>
                                </span>

                                <?php if (!(int)$row['is_read']): ?>
                                    <span class="unread-dot"></span>
                                <?php endif; ?>

                                <span><?php echo htmlspecialchars($row['alert_type']); ?></span>
                            </div>
                        </div>

                        <span class="badge <?php echo $severityClass; ?>">
                            <?php echo htmlspecialchars(ucfirst($row['severity'])); ?>
                        </span>
                    </div>

                    <div class="alert-meta">
                        <strong>Location:</strong> <?php echo htmlspecialchars($row['location_text']); ?>
                    </div>

                    <div class="alert-meta">
                        <strong>Published At:</strong> <?php echo htmlspecialchars($row['created_at']); ?>
                    </div>

                    <div class="alert-instructions">
                        <strong>Instructions:</strong><br>
                        <?php echo nl2br(htmlspecialchars($row['instructions'])); ?>
                    </div>

                    <div class="alert-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <?php if ((int)$row['is_read']): ?>
                                <span class="read-badge">Already Read</span>
                            <?php endif; ?>
                        </div>

                        <div>
                            <?php if (!(int)$row['is_read']): ?>
                                <a href="?read=<?php echo (int)$row['notif_id']; ?>" class="btn btn-success btn-sm mark-btn">
                                    Mark as Read
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="alert alert-info">No published alerts found.</div>
    <?php endif; ?>

    <a href="dashboard.php" class="btn btn-secondary mt-2">Back</a>
</div>

</body>
</html>