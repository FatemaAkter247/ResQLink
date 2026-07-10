<?php
session_start();
require_once __DIR__ . "/../DB/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$error = "";
$success = false;

$insert = $conn->prepare("
    INSERT INTO evacuation_status (user_id, status, shelter_id, notes, updated_at)
    VALUES (?, 'need_help', NULL, 'Requested via Request Help button', NOW())
");
$insert->bind_param("i", $user_id);

if ($insert->execute()) {
    $success = true;
} else {
    $error = "We could not send your help request. Please try again.";
}
$insert->close();

// Session/user info for dashboard shell
$username_raw = $_SESSION['full_name'] ?? 'User';
$username = htmlspecialchars($username_raw, ENT_QUOTES, 'UTF-8');
$initials = strtoupper(substr($username_raw, 0, 1));

// Unread alerts count for sidebar badge
$unread_count = 0;
$uc = $conn->query("SELECT COUNT(*) AS c FROM alert_notifications WHERE user_id = $user_id AND is_read = 0");
if ($uc && $ucr = $uc->fetch_assoc()) {
    $unread_count = (int)$ucr['c'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Help - ResQLink</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --accent: #2e7d32;
            --accent-dark: #1b5e20;
            --accent-light: #e8f5e9;
            --bg: #f0f2f5;
            --white: #ffffff;
            --text: #1a1a2e;
            --muted: #6b7280;
            --border: #e5e7eb;
            --shadow: 0 4px 16px rgba(0, 0, 0, .10);
            --radius: 14px;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .confirm-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 40px 36px;
            max-width: 460px;
            width: 100%;
            text-align: center;
        }

        .confirm-icon {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            margin: 0 auto 20px;
            font-size: 30px;
            color: #fff;
        }

        .confirm-icon.success { background: var(--accent); }
        .confirm-icon.error { background: #b91c1c; }

        .confirm-card h1 {
            font-size: 20px;
            font-weight: 800;
            margin-bottom: 10px;
        }

        .confirm-card p {
            color: var(--muted);
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 26px;
        }

        .confirm-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 20px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            border: 1px solid var(--border);
            cursor: pointer;
        }

        .btn-primary {
            background: var(--accent);
            color: #fff;
            border-color: var(--accent);
        }

        .btn-primary:hover { background: var(--accent-dark); }

        .btn-secondary {
            background: var(--white);
            color: var(--text);
        }

        .btn-secondary:hover {
            background: var(--accent-light);
            color: var(--accent);
            border-color: var(--accent);
        }

        .user-note {
            margin-top: 22px;
            font-size: 12px;
            color: var(--muted);
        }
    </style>
</head>

<body>

<div class="confirm-card">
    <?php if ($success): ?>
        <div class="confirm-icon success">
            <i class="fa-solid fa-hand-holding-heart"></i>
        </div>
        <h1>Help Request Sent</h1>
        <p>
            Your request for help has been recorded and marked as <strong>Need Help</strong>.
            Emergency responders and administrators have been notified and will assist you as soon as possible.
        </p>
    <?php else: ?>
        <div class="confirm-icon error">
            <i class="fa-solid fa-circle-exclamation"></i>
        </div>
        <h1>Request Failed</h1>
        <p><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <div class="confirm-actions">
        <a href="evacuation_status.php" class="btn btn-primary">
            <i class="fa-solid fa-person-walking-arrow-right"></i> View My Status
        </a>
        <a href="dashboard.php" class="btn btn-secondary">
            <i class="fa-solid fa-gauge-high"></i> Back to Dashboard
        </a>
    </div>

    <div class="user-note">Logged in as <?php echo $username; ?></div>
</div>

</body>
</html>
