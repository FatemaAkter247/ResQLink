<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . "/../DB/db.php";

if (empty($_SESSION['chatbot_csrf'])) {
    $_SESSION['chatbot_csrf'] = bin2hex(random_bytes(32));
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
$username_raw = $_SESSION['full_name'] ?? 'User';
$username = htmlspecialchars($username_raw, ENT_QUOTES, 'UTF-8');
$roleId = (int)($_SESSION['role_id'] ?? 1);
$initials = strtoupper(substr($username_raw, 0, 1));

$roleNames = [
    1 => 'Citizen',
    2 => 'Admin',
    3 => 'Rescue Team',
    4 => 'Government',
    5 => 'System Admin'
];
$roleName = $roleNames[$roleId] ?? 'User';

// Unread alerts count for sidebar badge
$unread_count = 0;
if (isset($conn) && $conn) {
    $uc = $conn->query("SELECT COUNT(*) AS c FROM alert_notifications WHERE user_id = $user_id AND is_read = 0");
    if ($uc && $ucr = $uc->fetch_assoc()) {
        $unread_count = (int)$ucr['c'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Help | ResQLink</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
            --sidebar-width: 265px;
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
        }

        a { color: inherit; }

        /* ---------- Sidebar ---------- */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--white);
            border-right: 1px solid var(--border);
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            z-index: 100;
            display: flex;
            flex-direction: column;
            transition: transform .25s ease;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 20px 22px;
            border-bottom: 1px solid var(--border);
            text-decoration: none;
        }

        .brand-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: var(--accent);
            color: #fff;
            display: grid;
            place-items: center;
        }

        .brand-name {
            font-size: 18px;
            font-weight: 800;
        }

        .brand-name span { color: var(--accent); }

        .sidebar-nav {
            flex: 1;
            padding: 16px 12px;
            overflow-y: auto;
        }

        .nav-label {
            display: block;
            padding: 8px 12px;
            color: var(--muted);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            margin-bottom: 3px;
            border-radius: 10px;
            color: var(--muted);
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
        }

        .nav-link:hover,
        .nav-link.active {
            background: var(--accent-light);
            color: var(--accent);
        }

        .nav-link i { width: 18px; text-align: center; }

        .nav-link .badge {
            margin-left: auto;
            background: var(--accent);
            color: #fff;
            font-size: 10px;
            padding: 2px 7px;
            border-radius: 20px;
        }

        .sidebar-footer {
            padding: 14px 12px;
            border-top: 1px solid var(--border);
        }

        .logout { color: #dc2626; }

        /* ---------- Main ---------- */
        .main {
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            min-height: 100vh;
        }

        .topbar {
            height: 66px;
            background: var(--white);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 28px;
            position: sticky;
            top: 0;
            z-index: 50;
        }

        .topbar-title h1 { font-size: 17px; font-weight: 800; }
        .topbar-title p { font-size: 12px; color: var(--muted); margin-top: 2px; }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .icon-btn {
            width: 38px;
            height: 38px;
            border: 1px solid var(--border);
            border-radius: 10px;
            display: grid;
            place-items: center;
            color: var(--muted);
            text-decoration: none;
            position: relative;
        }

        .icon-btn:hover {
            background: var(--accent-light);
            color: var(--accent);
            border-color: var(--accent);
        }

        .badge-dot {
            position: absolute;
            top: 7px;
            right: 7px;
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #dc2626;
            border: 1.5px solid #fff;
        }

        .user-chip {
            display: flex;
            align-items: center;
            gap: 9px;
            border: 1px solid var(--border);
            border-radius: 50px;
            padding: 5px 13px 5px 5px;
        }

        .avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--accent);
            color: #fff;
            display: grid;
            place-items: center;
            font-size: 13px;
            font-weight: 800;
        }

        .user-chip-name { font-size: 13px; font-weight: 800; }
        .user-chip-role { font-size: 11px; color: var(--muted); }

        .hamburger {
            display: none;
            background: none;
            border: none;
            color: var(--text);
            font-size: 20px;
            cursor: pointer;
        }

        .content { padding: 28px; }

        /* ---------- Chat layout ---------- */
        .chat-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 310px;
            gap: 20px;
            align-items: start;
        }

        .chatbot-panel {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .chatbot-header {
            display: flex;
            gap: 14px;
            align-items: center;
            padding: 22px;
            background: linear-gradient(135deg, var(--accent-dark), var(--accent));
            color: #fff;
        }

        .bot-avatar {
            width: 54px;
            height: 54px;
            border-radius: 16px;
            display: grid;
            place-items: center;
            background: rgba(255, 255, 255, .18);
            font-size: 26px;
            flex: 0 0 auto;
        }

        .chatbot-header h1 { font-size: 22px; font-weight: 800; margin-bottom: 4px; }
        .chatbot-header p { opacity: .9; font-size: 14px; }

        .safety-note {
            display: flex;
            gap: 10px;
            padding: 12px 18px;
            background: #fff7ed;
            color: #9a3412;
            border-bottom: 1px solid #fed7aa;
            font-size: 14px;
            line-height: 1.5;
        }

        .chat-window {
            height: 520px;
            overflow-y: auto;
            padding: 20px;
            background: linear-gradient(180deg, #f9fafb, #ffffff);
        }

        .message {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }

        .message.user { justify-content: flex-end; }

        .message-icon {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: var(--accent-light);
            color: var(--accent);
            flex: 0 0 auto;
        }

        .message.user .message-icon { display: none; }

        .message-bubble {
            max-width: 78%;
            padding: 13px 15px;
            border-radius: 16px;
            line-height: 1.55;
            font-size: 14px;
            white-space: pre-wrap;
        }

        .message.bot .message-bubble {
            background: #fff;
            border: 1px solid var(--border);
            border-top-left-radius: 5px;
            font-weight: 700;
        }

        .message.user .message-bubble {
            background: var(--accent);
            color: #fff;
            border-top-right-radius: 5px;
        }

        .quick-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            padding: 13px 18px;
            border-top: 1px solid var(--border);
        }

        .quick-buttons button {
            border: 1px solid var(--border);
            background: #fff;
            color: var(--text);
            border-radius: 999px;
            padding: 8px 12px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            font-family: inherit;
        }

        .quick-buttons button:hover {
            border-color: var(--accent);
            color: var(--accent);
            background: var(--accent-light);
        }

        .chat-form {
            display: flex;
            gap: 10px;
            padding: 16px 18px;
            border-top: 1px solid var(--border);
        }

        .chat-form input {
            flex: 1;
            border: 1px solid #d1d5db;
            border-radius: 14px;
            padding: 13px 14px;
            outline: none;
            font-size: 14px;
            font-family: inherit;
        }

        .chat-form input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(46, 125, 50, .16);
        }

        .chat-form button {
            width: 50px;
            border: 0;
            border-radius: 14px;
            background: var(--accent);
            color: #fff;
            font-size: 17px;
            cursor: pointer;
        }

        .chat-form button:hover { background: var(--accent-dark); }
        .chat-form button:disabled { opacity: .55; cursor: not-allowed; }

        /* ---------- Side panel ---------- */
        .chatbot-side {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .side-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: 0 1px 4px rgba(0, 0, 0, .06);
        }

        .user-card {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 18px;
        }

        .user-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: var(--accent);
            color: #fff;
            font-weight: 800;
        }

        .user-card h3, .info-card h3 { font-size: 16px; font-weight: 800; }
        .user-card p { margin-top: 2px; color: var(--muted); font-size: 13px; }

        .info-card { padding: 18px; }
        .info-card h3 { margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
        .info-card h3 i { color: var(--accent); }

        .info-card ul {
            padding-left: 20px;
            color: #4b5563;
            font-size: 14px;
            line-height: 1.8;
        }

        .call-999 {
            display: block;
            text-align: center;
            padding: 14px;
            text-decoration: none;
            color: #fff;
            background: #dc2626;
            font-weight: 800;
            border-radius: var(--radius);
            box-shadow: 0 1px 4px rgba(0, 0, 0, .06);
        }

        .call-999:hover { background: #b91c1c; color: #fff; }

        /* ---------- Typing ---------- */
        .typing-dots { display: inline-flex; gap: 4px; }

        .typing-dots span {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--muted);
            animation: typing 1s infinite;
        }

        .typing-dots span:nth-child(2) { animation-delay: .15s; }
        .typing-dots span:nth-child(3) { animation-delay: .3s; }

        @keyframes typing {
            50% { opacity: .35; transform: translateY(-2px); }
        }

        /* ---------- Sidebar overlay (mobile) ---------- */
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .45);
            z-index: 90;
        }

        .sidebar-overlay.open { display: block; }

        @media (max-width: 900px) {
            .chat-layout { grid-template-columns: 1fr; }
        }

        @media (max-width: 800px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main { margin-left: 0; width: 100%; }
            .hamburger { display: block; }
            .content { padding: 16px; }
            .topbar { padding: 0 16px; }
            .user-chip-role { display: none; }
        }
    </style>
</head>
<body>

<div class="sidebar-overlay" id="overlay" onclick="closeSidebar()"></div>

<aside class="sidebar" id="sidebar">
    <a href="dashboard.php" class="sidebar-brand">
        <div class="brand-icon">
            <i class="fa-solid fa-shield-halved"></i>
        </div>
        <span class="brand-name">ResQ<span>Link</span></span>
    </a>

    <nav class="sidebar-nav">
        <span class="nav-label">Main Menu</span>

        <a href="dashboard.php" class="nav-link">
            <i class="fa-solid fa-gauge-high"></i> Dashboard
        </a>

        <a href="chatbot.php" class="nav-link">
            <i class="fa-solid fa-robot"></i> AI Emergency Chatbot
        </a>

        <span class="nav-label">Disaster Info</span>

        <a href="alerts.php" class="nav-link">
            <i class="fa-solid fa-bell"></i> Alerts
            <?php if ($unread_count > 0): ?>
                <span class="badge"><?php echo $unread_count; ?></span>
            <?php endif; ?>
        </a>

        <a href="shelters.php" class="nav-link">
            <i class="fa-solid fa-house-chimney"></i> Find Shelter
        </a>

        <a href="resources.php" class="nav-link">
            <i class="fa-solid fa-boxes-stacked"></i> Resources
        </a>

        <span class="nav-label">My Status</span>

        <a href="evacuation_status.php" class="nav-link">
            <i class="fa-solid fa-person-walking-arrow-right"></i> Evacuation Status
        </a>

        <a href="chatbot.php" class="nav-link active">
            <i class="fa-solid fa-hand-holding-heart"></i> Request Help
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="logout.php" class="nav-link logout">
            <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
        </a>
    </div>
</aside>

<div class="main">
    <header class="topbar">
        <div style="display:flex; align-items:center; gap:12px;">
            <button class="hamburger" onclick="openSidebar()">
                <i class="fa-solid fa-bars"></i>
            </button>

            <div class="topbar-title">
                <h1>Request Help</h1>
                <p><?php echo date('l, F j, Y'); ?></p>
            </div>
        </div>

        <div class="topbar-right">
            <a href="alerts.php" class="icon-btn" title="Alerts">
                <i class="fa-solid fa-bell"></i>
                <?php if ($unread_count > 0): ?>
                    <span class="badge-dot"></span>
                <?php endif; ?>
            </a>

            <div class="user-chip">
                <div class="avatar"><?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?></div>
                <div>
                    <div class="user-chip-name"><?php echo $username; ?></div>
                    <div class="user-chip-role"><?php echo htmlspecialchars($roleName, ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
            </div>
        </div>
    </header>

    <main class="content">
        <div class="chat-layout">
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
                            Hello <?= $username ?>. Tell me what happened and your location. Example: "I need rescue at Dhanmondi 27, Dhaka" or "Show nearest shelter in Khulna".
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
                <div class="side-card user-card">
                    <div class="user-avatar">
                        <?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?>
                    </div>

                    <div>
                        <h3><?= $username ?></h3>
                        <p><?= htmlspecialchars($roleName, ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                </div>

                <div class="side-card info-card">
                    <h3><i class="fa-solid fa-lightbulb"></i> Best prompts</h3>

                    <ul>
                        <li>"Latest cyclone alerts"</li>
                        <li>"Open shelters near Sylhet"</li>
                        <li>"Need rescue at Uttara Sector 10"</li>
                        <li>"What should I do during fire?"</li>
                        <li>"Need food and water support"</li>
                    </ul>
                </div>

                <a class="call-999" href="tel:999">
                    <i class="fa-solid fa-phone"></i> Call 999
                </a>
            </aside>
        </div>
    </main>
</div>

<script>
window.RESQLINK_CHATBOT = {
    apiUrl: 'chatbot_api.php',
    csrf: <?= json_encode($_SESSION['chatbot_csrf']) ?>
};

function openSidebar() {
    document.getElementById('sidebar').classList.add('open');
    document.getElementById('overlay').classList.add('open');
}

function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('overlay').classList.remove('open');
}
</script>

<script src="../js/chatbot.js"></script>

</body>
</html>
