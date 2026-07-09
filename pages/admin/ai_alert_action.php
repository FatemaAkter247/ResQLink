<?php
session_start();
require_once __DIR__ . "/../../DB/db.php";
require_once __DIR__ . "/../../config/gemini_config.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

if ((int)$_SESSION['role_id'] !== 2) {
    echo json_encode(['error' => 'Access denied']);
    exit;
}

// Check if API key is configured (no format assumed — any non-empty, non-placeholder key is accepted)
$_geminiKeyRaw = defined('GEMINI_API_KEY') ? trim(GEMINI_API_KEY) : '';
$_placeholders = ['YOUR_GEMINI_API_KEY_HERE', 'PASTE_YOUR_GEMINI_API_KEY_HERE', 'your_api_key_here'];
if (empty($_geminiKeyRaw) || in_array($_geminiKeyRaw, $_placeholders, true)) {
    echo json_encode(['error' => 'Gemini API key is not configured. Please add your key to config/gemini_config.php.']);
    exit;
}
unset($_placeholders); // cleanup — keep key scoped to constants only

// Auto-create table if it doesn't exist
function createAiGeneratedAlertsTable($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS ai_generated_alerts (
      id INT AUTO_INCREMENT PRIMARY KEY,
      created_by INT DEFAULT NULL,
      alert_type VARCHAR(100) NOT NULL,
      location_text VARCHAR(255) NOT NULL,
      severity VARCHAR(50) NOT NULL,
      affected_area VARCHAR(255) DEFAULT NULL,
      shelter_name VARCHAR(150) DEFAULT NULL,
      emergency_contact VARCHAR(100) DEFAULT NULL,
      extra_notes TEXT DEFAULT NULL,
      message_en TEXT DEFAULT NULL,
      message_bn TEXT DEFAULT NULL,
      final_message_en TEXT DEFAULT NULL,
      final_message_bn TEXT DEFAULT NULL,
      status ENUM('draft','approved','published') DEFAULT 'draft',
      gemini_prompt TEXT DEFAULT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($sql)) {
        error_log("Failed to create ai_generated_alerts table: " . $conn->error);
        return false;
    }
    return true;
}

// Build Gemini prompt
function buildGeminiPrompt($data) {
    $prompt = "Generate official emergency alert messages in Bangla and English.
Use a calm, direct, public-safe tone.
Do not create panic.
Include alert type, location, severity, affected area, shelter name, emergency contact, and instruction if provided.
Keep each language version within 60 to 90 words.
Mention that people should follow university or disaster authority instructions.
Return only valid JSON:
{
\"bangla\": \"...\",
\"english\": \"...\"
}

Emergency Details:
- Alert Type: " . htmlspecialchars($data['alert_type']) . "
- Location: " . htmlspecialchars($data['location_text']) . "
- Severity: " . htmlspecialchars($data['severity']) . "
- Affected Area: " . htmlspecialchars($data['affected_area'] ?? 'Not specified') . "
- Shelter Name: " . htmlspecialchars($data['shelter_name'] ?? 'Not specified') . "
- Emergency Contact: " . htmlspecialchars($data['emergency_contact'] ?? 'Not specified') . "
- Extra Notes: " . htmlspecialchars($data['extra_notes'] ?? 'Not specified') . "
";
    
    return $prompt;
}

// Call Gemini API
function callGeminiApi($prompt) {
    $endpoint = GEMINI_API_ENDPOINT;
    $apiKey = GEMINI_API_KEY;
    
    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ]
    ];
    
    $ch = curl_init($endpoint . '?key=' . $apiKey);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        error_log("Gemini API curl error: " . $error);
        return ['error' => 'Failed to connect to AI service.'];
    }
    
    if ($httpCode !== 200) {
        $errorBody = json_decode($response, true);
        $errorMessage = $errorBody['error']['message'] ?? ('AI service returned HTTP ' . $httpCode);
        error_log("Gemini API returned HTTP code: " . $httpCode . " | Response: " . $response);
        return ['error' => 'AI service error: ' . $errorMessage];
    }
    
    return ['response' => $response];
}

// Parse Gemini response
function parseGeminiResponse($response) {
    $data = json_decode($response, true);
    
    if (!$data || !isset($data['candidates'][0]['content']['parts'][0]['text'])) {
        error_log("Invalid Gemini response structure");
        return ['error' => 'Invalid response from AI service.'];
    }
    
    $text = $data['candidates'][0]['content']['parts'][0]['text'];
    
    // Try to extract JSON from the response
    $jsonStart = strpos($text, '{');
    $jsonEnd = strrpos($text, '}');
    
    if ($jsonStart !== false && $jsonEnd !== false) {
        $jsonStr = substr($text, $jsonStart, $jsonEnd - $jsonStart + 1);
        $parsed = json_decode($jsonStr, true);
        
        if ($parsed && isset($parsed['bangla']) && isset($parsed['english'])) {
            return [
                'bangla' => $parsed['bangla'],
                'english' => $parsed['english']
            ];
        }
    }
    
    error_log("Could not parse JSON from Gemini response");
    return ['error' => 'Could not parse AI response.'];
}

// Create table
createAiGeneratedAlertsTable($conn);

// Handle actions
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'generate') {
    // Validate required fields
    $alert_type = trim($_POST['alert_type'] ?? '');
    $location_text = trim($_POST['location_text'] ?? '');
    $severity = trim($_POST['severity'] ?? '');
    
    if (empty($alert_type) || empty($location_text) || empty($severity)) {
        echo json_encode(['error' => 'Please fill in all required fields.']);
        exit;
    }
    
    $data = [
        'alert_type' => $alert_type,
        'location_text' => $location_text,
        'severity' => $severity,
        'affected_area' => trim($_POST['affected_area'] ?? ''),
        'shelter_name' => trim($_POST['shelter_name'] ?? ''),
        'emergency_contact' => trim($_POST['emergency_contact'] ?? ''),
        'extra_notes' => trim($_POST['extra_notes'] ?? '')
    ];
    
    $prompt = buildGeminiPrompt($data);
    $result = callGeminiApi($prompt);
    
    if (isset($result['error'])) {
        echo json_encode(['error' => $result['error']]);
        exit;
    }
    
    $parsed = parseGeminiResponse($result['response']);
    
    if (isset($parsed['error'])) {
        echo json_encode(['error' => $parsed['error']]);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'bangla' => $parsed['bangla'],
        'english' => $parsed['english']
    ]);
    exit;
}

if ($action === 'save') {
    // Validate required fields
    $alert_type = trim($_POST['alert_type'] ?? '');
    $location_text = trim($_POST['location_text'] ?? '');
    $severity = trim($_POST['severity'] ?? '');
    
    if (empty($alert_type) || empty($location_text) || empty($severity)) {
        echo json_encode(['error' => 'Please fill in all required fields.']);
        exit;
    }
    
    $created_by = (int)$_SESSION['user_id'];
    $affected_area = trim($_POST['affected_area'] ?? '');
    $shelter_name = trim($_POST['shelter_name'] ?? '');
    $emergency_contact = trim($_POST['emergency_contact'] ?? '');
    $extra_notes = trim($_POST['extra_notes'] ?? '');
    $message_en = trim($_POST['message_en'] ?? '');
    $message_bn = trim($_POST['message_bn'] ?? '');
    $final_message_en = trim($_POST['final_message_en'] ?? '');
    $final_message_bn = trim($_POST['final_message_bn'] ?? '');
    $status = trim($_POST['status'] ?? 'draft');
    $gemini_prompt = trim($_POST['gemini_prompt'] ?? '');
    
    $stmt = $conn->prepare("
        INSERT INTO ai_generated_alerts 
        (created_by, alert_type, location_text, severity, affected_area, shelter_name, emergency_contact, extra_notes, message_en, message_bn, final_message_en, final_message_bn, status, gemini_prompt)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    if (!$stmt) {
        error_log("Database Prepare Failed: " . $conn->error);
        echo json_encode(['error' => 'Database error (prepare failed). Check error logs.']);
        exit;
    }
    
    $stmt->bind_param("isssssssssssss", $created_by, $alert_type, $location_text, $severity, $affected_area, $shelter_name, $emergency_contact, $extra_notes, $message_en, $message_bn, $final_message_en, $final_message_bn, $status, $gemini_prompt);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'id' => $stmt->insert_id]);
    } else {
        $dbError = $stmt->error;
        error_log("Database Insert Failed: " . $dbError);
        echo json_encode(['error' => 'Failed to save alert. MySQL Error: ' . $dbError]);
    }
    
    $stmt->close();
    exit;
}

if ($action === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    
    if ($id <= 0) {
        echo json_encode(['error' => 'Invalid alert ID.']);
        exit;
    }
    
    $final_message_en = trim($_POST['final_message_en'] ?? '');
    $final_message_bn = trim($_POST['final_message_bn'] ?? '');
    $status = trim($_POST['status'] ?? 'draft');
    
    $stmt = $conn->prepare("
        UPDATE ai_generated_alerts
        SET final_message_en = ?, final_message_bn = ?, status = ?
        WHERE id = ?
    ");
    
    $stmt->bind_param("sssi", $final_message_en, $final_message_bn, $status, $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Failed to update alert.']);
    }
    
    $stmt->close();
    exit;
}

// If no action specified
echo json_encode(['error' => 'No action specified.']);
exit;
