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

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    die("Invalid alert ID.");
}

// Get alert by ID
$stmt = $conn->prepare("SELECT * FROM ai_generated_alerts WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Alert not found.");
}

$alert = $result->fetch_assoc();
$stmt->close();

$success = "";
$error = "";

// Handle update action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $final_message_en = trim($_POST['final_message_en'] ?? '');
    $final_message_bn = trim($_POST['final_message_bn'] ?? '');
    $status = trim($_POST['status'] ?? 'draft');
    
    if (empty($final_message_en) || empty($final_message_bn)) {
        $error = "Please fill in both final messages.";
    } else {
        // Post to action file
        $ch = curl_init('ai_alert_action.php');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'action' => 'update',
            'id' => $id,
            'final_message_en' => $final_message_en,
            'final_message_bn' => $final_message_bn,
            'status' => $status
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if ($result && isset($result['success'])) {
            $success = "Alert updated successfully!";
            // Reload alert data
            $stmt = $conn->prepare("SELECT * FROM ai_generated_alerts WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $alert = $result->fetch_assoc();
            $stmt->close();
        } else {
            $error = $result['error'] ?? 'Failed to update alert.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View AI Alert - ResQLink</title>

    <link rel="stylesheet" href="../../css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            color: #667eea;
            font-weight: 700;
            margin-bottom: 1.5rem;
        }

        .section-title {
            color: #667eea;
            font-weight: 600;
            margin-top: 1.5rem;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #667eea;
        }

        .detail-row {
            margin-bottom: 1rem;
        }

        .detail-label {
            font-weight: 600;
            color: #555;
        }

        .detail-value {
            color: #333;
        }

        .message-box {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 1rem;
        }

        .original-message {
            background: #e9ecef;
            border-left-color: #6c757d;
        }

        .form-control:focus,
        .form-select:focus,
        textarea:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
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
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 600;
            display: inline-block;
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

        .copy-btn {
            position: absolute;
            top: 5px;
            right: 5px;
            font-size: 0.8em;
        }

        .textarea-wrapper {
            position: relative;
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
            <h2 class="page-title mb-0">View AI Alert #<?php echo (int)$alert['id']; ?></h2>
            <a href="ai_alerts_list.php" class="btn btn-secondary">Back to List</a>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Alert Details -->
        <h4 class="section-title">Alert Details</h4>
        <div class="row">
            <div class="col-md-6">
                <div class="detail-row">
                    <span class="detail-label">Alert Type:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($alert['alert_type']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Location:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($alert['location_text']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Severity:</span>
                    <span class="detail-value severity-<?php echo htmlspecialchars($alert['severity']); ?>">
                        <?php echo htmlspecialchars(ucfirst($alert['severity'])); ?>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Status:</span>
                    <span class="status-badge status-<?php echo htmlspecialchars($alert['status']); ?>">
                        <?php echo htmlspecialchars(ucfirst($alert['status'])); ?>
                    </span>
                </div>
            </div>
            <div class="col-md-6">
                <div class="detail-row">
                    <span class="detail-label">Affected Area:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($alert['affected_area'] ?? 'Not specified'); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Shelter Name:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($alert['shelter_name'] ?? 'Not specified'); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Emergency Contact:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($alert['emergency_contact'] ?? 'Not specified'); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Created At:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($alert['created_at']); ?></span>
                </div>
            </div>
        </div>

        <?php if (!empty($alert['extra_notes'])): ?>
            <div class="detail-row mt-3">
                <span class="detail-label">Extra Notes:</span>
                <div class="detail-value"><?php echo nl2br(htmlspecialchars($alert['extra_notes'])); ?></div>
            </div>
        <?php endif; ?>

        <!-- Original AI Messages -->
        <h4 class="section-title">Original AI-Generated Messages</h4>
        <div class="message-box original-message">
            <div class="mb-3">
                <label class="form-label fw-bold">English Message (Original)</label>
                <textarea class="form-control" rows="4" readonly><?php echo htmlspecialchars($alert['message_en'] ?? ''); ?></textarea>
            </div>
            <div class="mb-0">
                <label class="form-label fw-bold">Bangla Message (Original)</label>
                <textarea class="form-control" rows="4" readonly><?php echo htmlspecialchars($alert['message_bn'] ?? ''); ?></textarea>
            </div>
        </div>

        <!-- Update Form -->
        <h4 class="section-title">Update Final Messages & Status</h4>
        <form method="POST">
            <input type="hidden" name="action" value="update">

            <div class="mb-3">
                <label class="form-label fw-bold">Final English Message (Editable)</label>
                <div class="textarea-wrapper">
                    <textarea name="final_message_en" id="final_message_en" class="form-control" rows="5" required><?php echo htmlspecialchars($alert['final_message_en'] ?? ''); ?></textarea>
                    <button type="button" class="btn btn-sm btn-outline-secondary copy-btn" onclick="copyToClipboard('final_message_en')">
                        Copy
                    </button>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Final Bangla Message (Editable)</label>
                <div class="textarea-wrapper">
                    <textarea name="final_message_bn" id="final_message_bn" class="form-control" rows="5" required><?php echo htmlspecialchars($alert['final_message_bn'] ?? ''); ?></textarea>
                    <button type="button" class="btn btn-sm btn-outline-secondary copy-btn" onclick="copyToClipboard('final_message_bn')">
                        Copy
                    </button>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Status</label>
                <select name="status" class="form-select" required>
                    <option value="draft" <?php echo $alert['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                    <option value="approved" <?php echo $alert['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="published" <?php echo $alert['status'] === 'published' ? 'selected' : ''; ?>>Published</option>
                </select>
                <small class="text-muted">
                    Note: Admin must approve before publishing. No SMS or external publication will be sent automatically.
                </small>
            </div>

            <div class="d-flex gap-2 flex-wrap">
                <button type="submit" class="btn btn-purple">
                    Update Alert
                </button>
                <a href="ai_alerts_list.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
function copyToClipboard(textareaId) {
    const textarea = document.getElementById(textareaId);
    textarea.select();
    textarea.setSelectionRange(0, 99999); // For mobile devices
    
    try {
        navigator.clipboard.writeText(textarea.value).then(function() {
            alert('Copied to clipboard!');
        }, function(err) {
            // Fallback for older browsers
            document.execCommand('copy');
            alert('Copied to clipboard!');
        });
    } catch (err) {
        // Fallback for older browsers
        document.execCommand('copy');
        alert('Copied to clipboard!');
    }
}
</script>

</body>
</html>
