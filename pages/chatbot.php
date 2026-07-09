<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if (empty($_SESSION['chatbot_csrf'])) {
    $_SESSION['chatbot_csrf'] = bin2hex(random_bytes(32));
}

$username = htmlspecialchars($_SESSION['full_name'] ?? 'User', ENT_QUOTES, 'UTF-8');
$roleId = (int)($_SESSION['role_id'] ?? 1);
$initials = strtoupper(substr($username, 0, 1));

$roleNames = [
    1 => 'Citizen',
    2 => 'Admin',
    3 => 'Rescue Team',
    4 => 'Government',
    5 => 'System Admin'
];

$roleName = $roleNames[$roleId] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Emergency Chatbot | ResQLink</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../css/chatbot.css">
</head>
<body>

<nav class="chatbot-topbar">
    <div class="brand-area">
        <a href="dashboard.php" class="brand-link">
            <span class="brand-badge">ResQLink</span>
        </a>
        <span class="page-title">AI Emergency Chatbot</span>
    </div>

    <div class="topbar-actions">
        <a href="dashboard.php" class="topbar-link">
            <i class="fa-solid fa-gauge-high"></i> Dashboard
        </a>

        <a href="alerts.php" class="topbar-link">
            <i class="fa-solid fa-bell"></i> Alerts
        </a>

        <a href="shelters.php" class="topbar-link">
            <i class="fa-solid fa-house-chimney"></i> Shelters
        </a>

        <a href="logout.php" class="topbar-link danger">
            <i class="fa-solid fa-right-from-bracket"></i> Logout
        </a>
    </div>
</nav>

<main class="chatbot-page">
    <section class="chatbot-panel">
        <div class="chatbot-header">
            <div class="bot-avatar">
                <i class="fa-solid fa-robot"></i>
            </div>

            <div>
                <h1>ResQBot</h1>
                <p>Real AI emergency assistant for disaster advice, alerts, shelters, resources, and rescue requests.</p>
            </div>
        </div>

        <div class="safety-note">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <span>
                For immediate danger, call <strong>999</strong> first. The chatbot supports decision-making, but official emergency services come first.
            </span>
        </div>

        <div class="chat-window" id="chatWindow">
            <div class="message bot">
                <div class="message-icon">
                    <i class="fa-solid fa-robot"></i>
                </div>

                <div class="message-bubble">
                    Hello <?= $username ?>. Tell me what happened and your location. Example: “I need rescue at Dhanmondi 27, Dhaka” or “Show nearest shelter in Khulna”.
                </div>
            </div>
        </div>

        <div class="quick-buttons">
            <button type="button" data-msg="Latest alerts">Latest alerts</button>
            <button type="button" data-msg="Nearest shelter in Dhaka">Nearest shelter</button>
            <button type="button" data-msg="Flood water is rising near my area. What should I do?">Flood advice</button>
            <button type="button" data-msg="I need rescue at Dhanmondi 27, Dhaka">Need rescue</button>
            <button type="button" data-msg="Need medical help for an injured person at Mirpur 10">Medical help</button>
        </div>

        <form class="chat-form" id="chatForm">
            <input
                type="text"
                id="chatInput"
                maxlength="1000"
                placeholder="Type your emergency message..."
                autocomplete="off"
                required
            >

            <button type="submit" id="sendBtn">
                <i class="fa-solid fa-paper-plane"></i>
            </button>
        </form>
    </section>

    <aside class="chatbot-side">
        <div class="user-card">
            <div class="user-avatar">
                <?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?>
            </div>

            <div>
                <h3><?= $username ?></h3>
                <p><?= htmlspecialchars($roleName, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>

        <div class="info-card">
            <h3><i class="fa-solid fa-lightbulb"></i> Best prompts</h3>

            <ul>
                <li>“Latest cyclone alerts”</li>
                <li>“Open shelters near Sylhet”</li>
                <li>“Need rescue at Uttara Sector 10”</li>
                <li>“What should I do during fire?”</li>
                <li>“Need food and water support”</li>
            </ul>
        </div>

        <a class="call-999" href="tel:999">
            <i class="fa-solid fa-phone"></i> Call 999
        </a>
    </aside>
</main>

<script>
window.RESQLINK_CHATBOT = {
    apiUrl: 'chatbot_api.php',
    csrf: <?= json_encode($_SESSION['chatbot_csrf']) ?>
};
</script>

<script src="../js/chatbot.js"></script>

</body>
</html>