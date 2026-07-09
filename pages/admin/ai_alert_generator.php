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

$success = "";
$error = "";

// Handle save action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    $data = [
        'alert_type' => trim($_POST['alert_type'] ?? ''),
        'location_text' => trim($_POST['location_text'] ?? ''),
        'severity' => trim($_POST['severity'] ?? ''),
        'affected_area' => trim($_POST['affected_area'] ?? ''),
        'shelter_name' => trim($_POST['shelter_name'] ?? ''),
        'emergency_contact' => trim($_POST['emergency_contact'] ?? ''),
        'extra_notes' => trim($_POST['extra_notes'] ?? ''),
        'message_en' => trim($_POST['message_en'] ?? ''),
        'message_bn' => trim($_POST['message_bn'] ?? ''),
        'final_message_en' => trim($_POST['final_message_en'] ?? ''),
        'final_message_bn' => trim($_POST['final_message_bn'] ?? ''),
        'status' => trim($_POST['status'] ?? 'draft'),
        'gemini_prompt' => trim($_POST['gemini_prompt'] ?? '')
    ];
    
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $baseUrl = $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
    $ch = curl_init($baseUrl . '/ai_alert_action.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['action' => 'save'] + $data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    
    // Forward the current session cookie so the action file knows the user is logged in
    if (isset($_COOKIE[session_name()])) {
        curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . $_COOKIE[session_name()]);
    }
    
    $response = curl_exec($ch);
    
    // If we want to debug the raw response in case it fails again:
    if (curl_errno($ch)) {
        $error = 'cURL Error: ' . curl_error($ch);
    }
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if ($result && isset($result['success'])) {
        $success = "Alert saved successfully!";
        // Clear form data after successful save
        $data = [
            'alert_type' => '',
            'location_text' => '',
            'severity' => 'medium',
            'affected_area' => '',
            'shelter_name' => '',
            'emergency_contact' => '',
            'extra_notes' => '',
            'message_en' => '',
            'message_bn' => '',
            'final_message_en' => '',
            'final_message_bn' => '',
            'status' => 'draft',
            'gemini_prompt' => ''
        ];
    } else {
        $error = $result['error'] ?? 'Failed to save alert.';
    }
}

// Initialize form data
$formData = [
    'alert_type' => $data['alert_type'] ?? '',
    'location_text' => $data['location_text'] ?? '',
    'severity' => $data['severity'] ?? 'medium',
    'affected_area' => $data['affected_area'] ?? '',
    'shelter_name' => $data['shelter_name'] ?? '',
    'emergency_contact' => $data['emergency_contact'] ?? '',
    'extra_notes' => $data['extra_notes'] ?? '',
    'message_en' => $data['message_en'] ?? '',
    'message_bn' => $data['message_bn'] ?? '',
    'final_message_en' => $data['final_message_en'] ?? '',
    'final_message_bn' => $data['final_message_bn'] ?? '',
    'status' => $data['status'] ?? 'draft',
    'gemini_prompt' => $data['gemini_prompt'] ?? ''
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Alert Generator - ResQLink</title>

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

        .generated-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            display: none;
        }

        .generated-section.show {
            display: block;
        }

        .loading-spinner {
            display: none;
            text-align: center;
            padding: 20px;
        }

        .loading-spinner.show {
            display: block;
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
        <h2 class="page-title">AI Alert Generator</h2>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form id="alertForm" method="POST">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="gemini_prompt" id="gemini_prompt" value="<?php echo htmlspecialchars($formData['gemini_prompt']); ?>">

            <div class="mb-3">
                <label class="form-label">Alert Type / Disaster Type</label>
                <input type="text" name="alert_type" class="form-control" required
                       value="<?php echo htmlspecialchars($formData['alert_type']); ?>"
                       placeholder="Flood, Fire, Cyclone...">
            </div>

            <div class="mb-3">
                <label class="form-label">Location</label>
                <input type="text" name="location_text" class="form-control" required
                       value="<?php echo htmlspecialchars($formData['location_text']); ?>"
                       placeholder="Enter affected location">
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Severity</label>
                    <select name="severity" class="form-select" required>
                        <option value="low" <?php echo $formData['severity'] === 'low' ? 'selected' : ''; ?>>Low</option>
                        <option value="medium" <?php echo $formData['severity'] === 'medium' ? 'selected' : ''; ?>>Medium</option>
                        <option value="high" <?php echo $formData['severity'] === 'high' ? 'selected' : ''; ?>>High</option>
                        <option value="critical" <?php echo $formData['severity'] === 'critical' ? 'selected' : ''; ?>>Critical</option>
                    </select>
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select" required>
                        <option value="draft" <?php echo $formData['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="approved" <?php echo $formData['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="published" <?php echo $formData['status'] === 'published' ? 'selected' : ''; ?>>Published</option>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Affected Area</label>
                <input type="text" name="affected_area" class="form-control"
                       value="<?php echo htmlspecialchars($formData['affected_area']); ?>"
                       placeholder="Specific affected area">
            </div>

            <div class="mb-3">
                <label class="form-label">Shelter Name</label>
                <input type="text" name="shelter_name" class="form-control"
                       value="<?php echo htmlspecialchars($formData['shelter_name']); ?>"
                       placeholder="Nearest shelter name">
            </div>

            <div class="mb-3">
                <label class="form-label">Emergency Contact</label>
                <input type="text" name="emergency_contact" class="form-control"
                       value="<?php echo htmlspecialchars($formData['emergency_contact']); ?>"
                       placeholder="Emergency contact number">
            </div>

            <div class="mb-3">
                <label class="form-label">Extra Notes</label>
                <textarea name="extra_notes" class="form-control" rows="3" placeholder="Additional instructions or notes..."><?php echo htmlspecialchars($formData['extra_notes']); ?></textarea>
            </div>

            <div class="mb-3">
                <button type="button" id="generateBtn" class="btn btn-purple">
                    Generate AI Alert
                </button>
            </div>

            <div class="loading-spinner" id="loadingSpinner">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Generating AI alert messages...</p>
            </div>

            <div class="generated-section" id="generatedSection">
                <h4 class="mb-3">Generated Messages (Editable)</h4>
                
                <div class="mb-3">
                    <label class="form-label">English Message</label>
                    <textarea name="message_en" id="message_en" class="form-control" rows="5"><?php echo htmlspecialchars($formData['message_en']); ?></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Bangla Message</label>
                    <textarea name="message_bn" id="message_bn" class="form-control" rows="5"><?php echo htmlspecialchars($formData['message_bn']); ?></textarea>
                </div>

                <h4 class="mb-3">Final Edited Messages</h4>
                
                <div class="mb-3">
                    <label class="form-label">Final English Message</label>
                    <textarea name="final_message_en" id="final_message_en" class="form-control" rows="5"><?php echo htmlspecialchars($formData['final_message_en']); ?></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Final Bangla Message</label>
                    <textarea name="final_message_bn" id="final_message_bn" class="form-control" rows="5"><?php echo htmlspecialchars($formData['final_message_bn']); ?></textarea>
                </div>

                <div class="d-flex gap-2 flex-wrap">
                    <button type="submit" class="btn btn-success">
                        Save Alert
                    </button>
                    <a href="ai_alert_generator.php" class="btn btn-secondary">Clear Form</a>
                    <a href="../dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('generateBtn').addEventListener('click', function() {
    const form = document.getElementById('alertForm');
    const formData = new FormData(form);
    
    // Validate required fields
    const alertType = formData.get('alert_type').trim();
    const location = formData.get('location_text').trim();
    const severity = formData.get('severity').trim();
    
    if (!alertType || !location || !severity) {
        alert('Please fill in Alert Type, Location, and Severity.');
        return;
    }
    
    // Show loading spinner
    document.getElementById('loadingSpinner').classList.add('show');
    document.getElementById('generatedSection').classList.remove('show');
    
    // Prepare data for API call
    const apiData = {
        action: 'generate',
        alert_type: alertType,
        location_text: location,
        severity: severity,
        affected_area: formData.get('affected_area').trim(),
        shelter_name: formData.get('shelter_name').trim(),
        emergency_contact: formData.get('emergency_contact').trim(),
        extra_notes: formData.get('extra_notes').trim()
    };
    
    fetch('ai_alert_action.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams(apiData)
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('loadingSpinner').classList.remove('show');
        
        if (data.success) {
            // Populate generated messages
            document.getElementById('message_en').value = data.english;
            document.getElementById('message_bn').value = data.bangla;
            document.getElementById('final_message_en').value = data.english;
            document.getElementById('final_message_bn').value = data.bangla;
            
            // Show generated section
            document.getElementById('generatedSection').classList.add('show');
        } else {
            alert('Error: ' + (data.error || 'Failed to generate alert.'));
        }
    })
    .catch(error => {
        document.getElementById('loadingSpinner').classList.remove('show');
        alert('Error: Failed to connect to server.');
        console.error('Error:', error);
    });
});

// Copy generated to final when user edits generated
document.getElementById('message_en').addEventListener('input', function() {
    document.getElementById('final_message_en').value = this.value;
});

document.getElementById('message_bn').addEventListener('input', function() {
    document.getElementById('final_message_bn').value = this.value;
});
</script>

</body>
</html>
