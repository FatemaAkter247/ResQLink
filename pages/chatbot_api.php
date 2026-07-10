<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    respond(false, 'Please login first.');
}

require_once __DIR__ . '/../DB/db.php';
require_once __DIR__ . '/../config/ai_config.php';

if (!isset($conn) || !$conn) {
    respond(false, 'Database connection failed.');
}

$payload = json_decode(file_get_contents('php://input'), true);

if (!is_array($payload)) {
    $payload = $_POST;
}

$message = trim((string)($payload['message'] ?? ''));
$csrf = (string)($payload['csrf'] ?? '');

if (!isset($_SESSION['chatbot_csrf']) || !hash_equals($_SESSION['chatbot_csrf'], $csrf)) {
    respond(false, 'Security check failed. Refresh the chatbot page and try again.');
}

if ($message === '') {
    respond(false, 'Please type your emergency message.');
}

if (mb_strlen($message, 'UTF-8') > 1000) {
    respond(false, 'Message is too long. Please keep it within 1000 characters.');
}

rateLimit();

$userId = (int)$_SESSION['user_id'];

try {
    $context = getSystemContext($conn);
    $aiResult = askAiEmergencyBot($message, $context);
} catch (Throwable $e) {
    $aiResult = fallbackReply($message, 'AI connection failed. ' . $e->getMessage());
}

$normalized = normalizeAiResult($aiResult, $message);
$requestId = null;

if ($normalized['should_create_request'] && $normalized['location'] !== '') {
    $requestId = createEmergencyRequest(
        $conn,
        $userId,
        $normalized['request_type'],
        $message,
        $normalized['location'],
        $normalized['priority']
    );

    if ($requestId) {
        $normalized['reply'] .= "\n\nEmergency request created successfully. Request ID: #" . $requestId . ".";
    } else {
        $normalized['reply'] .= "\n\nI detected an emergency request, but the database could not save it. Call 999 immediately.";
    }
} elseif ($normalized['should_create_request'] && $normalized['location'] === '') {
    $normalized['reply'] .= "\n\nPlease send your exact location or nearest landmark so I can create a rescue request.";
}

logChat($conn, $userId, $message, $normalized, $requestId);

respond(true, $normalized['reply'], [
    'intent' => $normalized['intent'],
    'disaster_type' => $normalized['disaster_type'],
    'priority' => $normalized['priority'],
    'emergency_request_id' => $requestId
]);

function askAiEmergencyBot(string $message, array $context): array
{
    $provider = strtolower((string)AI_PROVIDER);

    if ($provider === 'openai') {
        return callOpenAi($message, $context);
    }

    return callGemini($message, $context);
}

function callGemini(string $message, array $context): array
{
    if (GEMINI_API_KEY === '' || GEMINI_API_KEY === 'PASTE_YOUR_GEMINI_API_KEY_HERE') {
        return fallbackReply($message, 'Gemini API key is missing. Add it in config/ai_config.php.');
    }

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode(GEMINI_MODEL) . ':generateContent?key=' . urlencode(GEMINI_API_KEY);

    $body = [
        'system_instruction' => [
            'parts' => [
                [
                    'text' => getSystemPrompt()
                ]
            ]
        ],
        'contents' => [
            [
                'role' => 'user',
                'parts' => [
                    [
                        'text' => buildUserPrompt($message, $context)
                    ]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => AI_TEMPERATURE,
            'maxOutputTokens' => AI_MAX_OUTPUT_TOKENS,
            'responseMimeType' => 'application/json',
            // gemini-flash-latest is a "thinking" model; disable thinking so the
            // token budget is spent on the answer, not internal reasoning
            // (otherwise the JSON reply gets truncated -> MAX_TOKENS).
            'thinkingConfig' => [
                'thinkingBudget' => 0
            ]
        ]
    ];

    $data = httpJson($url, $body, []);
    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

    return parseAiJson($text, $message);
}

function callOpenAi(string $message, array $context): array
{
    if (OPENAI_API_KEY === '' || OPENAI_API_KEY === 'PASTE_YOUR_OPENAI_API_KEY_HERE') {
        return fallbackReply($message, 'OpenAI API key is missing. Add it in config/ai_config.php.');
    }

    $url = 'https://api.openai.com/v1/responses';

    $body = [
        'model' => OPENAI_MODEL,
        'instructions' => getSystemPrompt(),
        'input' => buildUserPrompt($message, $context),
        'temperature' => AI_TEMPERATURE,
        'max_output_tokens' => AI_MAX_OUTPUT_TOKENS
    ];

    $data = httpJson($url, $body, [
        'Authorization: Bearer ' . OPENAI_API_KEY
    ]);

    $text = $data['output_text'] ?? '';

    if ($text === '' && isset($data['output']) && is_array($data['output'])) {
        foreach ($data['output'] as $item) {
            if (!isset($item['content']) || !is_array($item['content'])) {
                continue;
            }

            foreach ($item['content'] as $content) {
                if (isset($content['text'])) {
                    $text .= $content['text'];
                }
            }
        }
    }

    return parseAiJson($text, $message);
}

function getSystemPrompt(): string
{
    return "You are ResQBot, an emergency assistant inside ResQLink, a disaster management system in Bangladesh. "
        . "Give practical, calm, short, safe guidance for floods, cyclones, earthquakes, fire, medical emergencies, shelters, alerts, resources, and rescue requests. "
        . "Never claim you contacted police, hospital, fire service, or rescue team unless the JSON field should_create_request is true and the system later confirms request creation. "
        . "Always tell users to call 999 first for immediate life danger. "
        . "Use the database context when answering about alerts, shelters, or resources. "
        . "Format the reply value as short, numbered steps. Begin each step with its number and a period (1., 2., 3., ...), put every step on its own line separated by a newline, keep each step to one short clear sentence, and put the most urgent action first. "
        . "Return JSON only. No markdown symbols like * or #. No extra text. "
        . "JSON format: {\"reply\":\"string\",\"intent\":\"advice|alert|shelter|resource|rescue_request|medical_request|other\",\"disaster_type\":\"flood|cyclone|earthquake|fire|medical|general\",\"priority\":\"low|medium|high|critical\",\"request_type\":\"medical|rescue|food|transport|other\",\"location\":\"exact location if user gave one, otherwise empty string\",\"should_create_request\":true_or_false}";
}

function buildUserPrompt(string $message, array $context): string
{
    return "USER MESSAGE:\n" . $message . "\n\n"
        . "DATABASE CONTEXT:\n" . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n"
        . "TASK:\n"
        . "Analyze the user message. If they ask for latest alerts, shelters, or resources, answer using the context. "
        . "If they clearly need rescue, medical help, food, water, or transport and they gave a location, set should_create_request true. "
        . "If no location is given, ask for exact location and keep should_create_request false.";
}

function getSystemContext(mysqli $conn): array
{
    return [
        'latest_alerts' => getRows(
            $conn,
            "SELECT alert_type, location_text, severity, instructions, published_at
             FROM disaster_alerts
             WHERE status='published'
             ORDER BY COALESCE(published_at, created_at) DESC
             LIMIT 5"
        ),

        'open_shelters' => getRows(
            $conn,
            "SELECT shelter_name, address, city, total_capacity, current_occupancy
             FROM shelters
             WHERE status='open'
             ORDER BY (total_capacity - current_occupancy) DESC
             LIMIT 8"
        ),

        'available_resources' => getRows(
            $conn,
            "SELECT resource_name, resource_type, quantity, unit
             FROM emergency_resources
             WHERE status='available'
             AND quantity > 0
             ORDER BY quantity DESC
             LIMIT 8"
        )
    ];
}

function getRows(mysqli $conn, string $sql): array
{
    $rows = [];
    $result = $conn->query($sql);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }

    return $rows;
}

function createEmergencyRequest(
    mysqli $conn,
    int $userId,
    string $type,
    string $description,
    string $address,
    string $priority
): ?int {
    $type = in_array($type, ['medical', 'rescue', 'food', 'transport', 'other'], true) ? $type : 'other';
    $priority = in_array($priority, ['low', 'medium', 'high', 'critical'], true) ? $priority : 'high';

    $stmt = $conn->prepare(
        "INSERT INTO emergency_requests
        (created_by, request_type, description, address, priority, status, created_at)
        VALUES (?, ?, ?, ?, ?, 'pending', NOW())"
    );

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('issss', $userId, $type, $description, $address, $priority);

    if (!$stmt->execute()) {
        $stmt->close();
        return null;
    }

    $id = (int)$conn->insert_id;
    $stmt->close();

    return $id;
}

function logChat(mysqli $conn, int $userId, string $message, array $result, ?int $requestId): void
{
    $stmt = $conn->prepare(
        "INSERT INTO chatbot_messages
        (user_id, user_message, bot_reply, intent, disaster_type, priority, location_text, emergency_request_id, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
    );

    if (!$stmt) {
        return;
    }

    $reply = $result['reply'];
    $intent = $result['intent'];
    $disaster = $result['disaster_type'];
    $priority = $result['priority'];
    $location = $result['location'];

    $stmt->bind_param(
        'issssssi',
        $userId,
        $message,
        $reply,
        $intent,
        $disaster,
        $priority,
        $location,
        $requestId
    );

    $stmt->execute();
    $stmt->close();
}

function httpJson(string $url, array $body, array $headers): array
{
    $headers[] = 'Content-Type: application/json';
    $payload = json_encode($body, JSON_UNESCAPED_UNICODE);

    // Transient errors (overloaded / rate-limited / gateway) are retried with
    // a short backoff so brief demand spikes recover silently.
    $transientStatuses = [429, 500, 502, 503, 504];
    $maxAttempts = 3;
    $lastError = 'AI request failed.';

    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 30
        ]);

        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Transport-level failure (timeout, DNS, reset) — retry then give up.
        if ($raw === false || $error) {
            $lastError = 'AI request failed: ' . $error;
            if ($attempt < $maxAttempts) {
                usleep($attempt * 600 * 1000); // 0.6s, then 1.2s
                continue;
            }
            throw new RuntimeException($lastError);
        }

        $data = json_decode($raw, true);

        // Retriable server-side error (e.g. 503 model overloaded).
        if (in_array($status, $transientStatuses, true)) {
            $lastError = (is_array($data) && isset($data['error']['message']))
                ? $data['error']['message']
                : 'AI service is busy.';
            if ($attempt < $maxAttempts) {
                usleep($attempt * 600 * 1000); // 0.6s, then 1.2s
                continue;
            }
            throw new RuntimeException($lastError);
        }

        if (!is_array($data)) {
            throw new RuntimeException('AI returned invalid JSON response.');
        }

        if ($status < 200 || $status >= 300) {
            $msg = $data['error']['message'] ?? 'AI API error.';
            throw new RuntimeException($msg);
        }

        return $data;
    }

    throw new RuntimeException($lastError);
}

function parseAiJson(string $text, string $originalMessage): array
{
    $text = trim($text);
    $text = preg_replace('/^```json\s*/i', '', $text);
    $text = preg_replace('/^```\s*/', '', $text);
    $text = preg_replace('/\s*```$/', '', $text);

    $data = json_decode($text, true);

    if (!is_array($data)) {
        return fallbackReply($originalMessage, 'AI response was not readable. Using safe emergency guidance.');
    }

    return $data;
}

function normalizeAiResult(array $data, string $message): array
{
    $intentList = ['advice', 'alert', 'shelter', 'resource', 'rescue_request', 'medical_request', 'other'];
    $disasterList = ['flood', 'cyclone', 'earthquake', 'fire', 'medical', 'general'];
    $priorityList = ['low', 'medium', 'high', 'critical'];
    $requestTypeList = ['medical', 'rescue', 'food', 'transport', 'other'];

    $reply = trim((string)($data['reply'] ?? ''));

    if ($reply === '') {
        $reply = 'I can help with emergency guidance. If there is immediate danger, call 999 now and send your exact location.';
    }

    $intent = (string)($data['intent'] ?? 'advice');
    $disaster = (string)($data['disaster_type'] ?? 'general');
    $priority = (string)($data['priority'] ?? 'medium');
    $requestType = (string)($data['request_type'] ?? 'other');
    $location = trim((string)($data['location'] ?? ''));
    $shouldCreate = filter_var($data['should_create_request'] ?? false, FILTER_VALIDATE_BOOLEAN);

    if (!in_array($intent, $intentList, true)) {
        $intent = 'advice';
    }

    if (!in_array($disaster, $disasterList, true)) {
        $disaster = 'general';
    }

    if (!in_array($priority, $priorityList, true)) {
        $priority = 'medium';
    }

    if (!in_array($requestType, $requestTypeList, true)) {
        $requestType = 'other';
    }

    if ($shouldCreate && $location === '') {
        $location = extractLocationFallback($message);
    }

    return [
        'reply' => $reply,
        'intent' => $intent,
        'disaster_type' => $disaster,
        'priority' => $priority,
        'request_type' => $requestType,
        'location' => mb_substr($location, 0, 255, 'UTF-8'),
        'should_create_request' => $shouldCreate
    ];
}

function fallbackReply(string $message, string $note = ''): array
{
    $lower = mb_strtolower($message, 'UTF-8');
    $priority = 'medium';
    $disaster = 'general';
    $intent = 'advice';
    $requestType = 'other';
    $shouldCreate = false;

    if (str_contains($lower, 'fire') || str_contains($lower, 'smoke')) {
        $disaster = 'fire';
        $priority = 'high';
        $reply = 'Fire safety: leave the building, stay low under smoke, do not use elevators, and call 999. Send your exact location if you need rescue.';
    } elseif (str_contains($lower, 'flood') || str_contains($lower, 'water')) {
        $disaster = 'flood';
        $priority = 'high';
        $reply = 'Flood safety: move to higher ground, avoid floodwater, turn off electricity if safe, and keep phone, medicine, water, and documents with you.';
    } elseif (str_contains($lower, 'earthquake') || str_contains($lower, 'collapsed')) {
        $disaster = 'earthquake';
        $priority = 'high';
        $reply = 'Earthquake safety: drop, cover, and hold during shaking. After shaking stops, evacuate carefully. Do not use elevators.';
    } elseif (str_contains($lower, 'cyclone') || str_contains($lower, 'storm')) {
        $disaster = 'cyclone';
        $priority = 'high';
        $reply = 'Cyclone safety: move to a strong building or shelter, stay away from windows, keep emergency supplies ready, and follow official alerts.';
    } else {
        $reply = 'Tell me what happened and your location. For immediate life danger, call 999 now.';
    }

    if (str_contains($lower, 'rescue') || str_contains($lower, 'help me') || str_contains($lower, 'trapped')) {
        $intent = 'rescue_request';
        $requestType = 'rescue';
        $priority = 'critical';
        $shouldCreate = true;
    }

    if ($note !== '') {
        $reply = $note . "\n\n" . $reply;
    }

    return [
        'reply' => $reply,
        'intent' => $intent,
        'disaster_type' => $disaster,
        'priority' => $priority,
        'request_type' => $requestType,
        'location' => extractLocationFallback($message),
        'should_create_request' => $shouldCreate
    ];
}

function extractLocationFallback(string $message): string
{
    if (preg_match('/(?:at|in|near|location is|address is)\s+(.{3,120})/i', $message, $m)) {
        return trim($m[1], " .,!?");
    }

    return '';
}

function rateLimit(): void
{
    $now = time();

    $_SESSION['chatbot_rate'] = $_SESSION['chatbot_rate'] ?? [];

    $_SESSION['chatbot_rate'] = array_values(array_filter(
        $_SESSION['chatbot_rate'],
        fn($t) => ($now - (int)$t) < 60
    ));

    if (count($_SESSION['chatbot_rate']) >= 20) {
        respond(false, 'Too many messages. Please wait one minute and try again.');
    }

    $_SESSION['chatbot_rate'][] = $now;
}

function respond(bool $ok, string $reply, array $extra = []): void
{
    echo json_encode(
        array_merge(['ok' => $ok, 'reply' => $reply], $extra),
        JSON_UNESCAPED_UNICODE
    );

    exit;
}