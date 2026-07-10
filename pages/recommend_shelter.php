<?php
session_start();
require_once '../DB/db.php';

// Public emergency feature: getting a shelter recommendation must NOT require
// login. No per-user data is read or written here, so guests are fully supported.

$username_raw = $_SESSION['full_name'] ?? 'User';
$username = htmlspecialchars($username_raw, ENT_QUOTES, 'UTF-8');
$initials = strtoupper(substr($username_raw, 0, 1));

$recommendations = [];
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $city = trim($_POST['city']);
    $people_count = (int)$_POST['people_count'];
    $medical_need = $_POST['medical_need'];
    $disaster_type = $_POST['disaster_type'];

    if (empty($city) || $people_count <= 0) {
        $error = "Please enter valid information.";
    } else {
        $sql = "SELECT * FROM shelters WHERE status = 'open'";
        $result = mysqli_query($conn, $sql);

        if ($result) {
            while ($shelter = mysqli_fetch_assoc($result)) {
                $capacity = (int)$shelter['total_capacity'];
                $occupied = (int)$shelter['current_occupancy'];
                $available = $capacity - $occupied;

                if ($capacity <= 0 || $available < $people_count) {
                    continue;
                }

                $score = 0;
                $reasons = [];

                if (strtolower($shelter['city']) == strtolower($city)) {
                    $score += 40;
                    $reasons[] = "Located in your selected city";
                } else {
                    $score += 10;
                    $reasons[] = "Available outside your selected city";
                }

                $occupancy_rate = ($occupied / $capacity) * 100;

                if ($occupancy_rate < 50) {
                    $score += 30;
                    $reasons[] = "Low occupancy level";
                } elseif ($occupancy_rate < 80) {
                    $score += 20;
                    $reasons[] = "Moderate occupancy level";
                } else {
                    $score += 10;
                    $reasons[] = "Limited space available";
                }

                if ($available >= ($people_count * 2)) {
                    $score += 20;
                    $reasons[] = "Enough space for your group";
                } else {
                    $score += 10;
                    $reasons[] = "Can support your group size";
                }

                if ($medical_need == "yes") {
                    $score += 10;
                    $reasons[] = "Medical need considered in recommendation";
                }

                if ($disaster_type == "Flood" || $disaster_type == "Cyclone") {
                    $score += 5;
                    $reasons[] = "Suitable for major disaster response";
                }

                $shelter['available_space'] = $available;
                $shelter['occupancy_rate'] = round($occupancy_rate, 2);
                $shelter['score'] = $score;
                $shelter['reasons'] = $reasons;

                $recommendations[] = $shelter;
            }

            usort($recommendations, function ($a, $b) {
                return $b['score'] <=> $a['score'];
            });
        } else {
            $error = "Database query failed.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Smart Shelter Recommendation | ResQLink</title>

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

a {
    color: inherit;
}

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

.brand-name span {
    color: var(--accent);
}

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

.nav-link i {
    width: 18px;
    text-align: center;
}

.sidebar-footer {
    padding: 14px 12px;
    border-top: 1px solid var(--border);
}

.logout {
    color: #dc2626;
}

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

.topbar-title h1 {
    font-size: 17px;
    font-weight: 800;
}

.topbar-title p {
    font-size: 12px;
    color: var(--muted);
    margin-top: 2px;
}

.topbar-right {
    display: flex;
    align-items: center;
    gap: 12px;
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

.user-chip-name {
    font-size: 13px;
    font-weight: 800;
}

.user-chip-role {
    font-size: 11px;
    color: var(--muted);
}

.hamburger {
    display: none;
    background: none;
    border: none;
    color: var(--text);
    font-size: 20px;
    cursor: pointer;
}

.content {
    padding: 28px;
}

.page-card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: 0 1px 4px rgba(0, 0, 0, .06);
    padding: 26px;
    max-width: 900px;
    margin: 0 auto 24px;
}

.page-card h2 {
    color: var(--accent);
    font-weight: 800;
    font-size: 20px;
    margin-bottom: 6px;
}

.page-card > p {
    color: var(--muted);
    font-size: 13px;
    margin-bottom: 18px;
}

label {
    display: block;
    font-size: 13px;
    font-weight: 700;
    margin-bottom: 6px;
    margin-top: 14px;
}

label:first-of-type {
    margin-top: 0;
}

input, select {
    width: 100%;
    padding: 11px 14px;
    border: 1px solid var(--border);
    border-radius: 10px;
    font-family: inherit;
    font-size: 14px;
    color: var(--text);
    background: var(--white);
}

input:focus, select:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px var(--accent-light);
}

.form-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 22px;
}

.btn-primary {
    background: var(--accent);
    color: #fff;
    padding: 11px 20px;
    border: none;
    border-radius: 50px;
    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-primary:hover {
    background: var(--accent-dark);
}

.btn-secondary {
    background: var(--bg);
    color: var(--text);
    padding: 11px 20px;
    border: 1px solid var(--border);
    border-radius: 50px;
    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-secondary:hover {
    background: var(--accent-light);
    color: var(--accent);
    border-color: var(--accent);
}

.error-box {
    background: #fdecea;
    color: #b91c1c;
    border: 1px solid #f5c2c0;
    border-radius: 10px;
    padding: 12px 16px;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 16px;
}

.section-hd {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 14px;
    max-width: 900px;
    margin-left: auto;
    margin-right: auto;
}

.section-hd h2 {
    font-size: 16px;
    font-weight: 800;
}

.results-wrap {
    max-width: 900px;
    margin: 0 auto;
}

.shelter-card {
    background: var(--white);
    border: 1px solid var(--border);
    border-left: 5px solid var(--accent);
    border-radius: var(--radius);
    box-shadow: 0 1px 4px rgba(0, 0, 0, .06);
    padding: 20px;
    margin-bottom: 14px;
}

.shelter-card h3 {
    font-size: 16px;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
}

.best-pill {
    background: var(--accent);
    color: #fff;
    padding: 3px 12px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .04em;
}

.shelter-meta {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 10px 16px;
    margin-bottom: 12px;
}

.shelter-meta div {
    font-size: 13px;
}

.shelter-meta strong {
    display: block;
    font-size: 11px;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: .04em;
    font-weight: 800;
    margin-bottom: 2px;
}

.score-pill {
    display: inline-block;
    font-weight: 800;
    color: var(--accent);
    background: var(--accent-light);
    padding: 4px 12px;
    border-radius: 30px;
    font-size: 13px;
    margin-bottom: 12px;
}

.reasons-title {
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: var(--muted);
    margin-bottom: 6px;
}

.reasons-list {
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.reasons-list li {
    font-size: 13px;
    display: flex;
    align-items: flex-start;
    gap: 8px;
}

.reasons-list li::before {
    content: '\f00c';
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
    color: var(--accent);
    font-size: 11px;
    margin-top: 3px;
}

.empty-state {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: 0 1px 4px rgba(0, 0, 0, .06);
    padding: 30px;
    text-align: center;
    color: var(--muted);
    font-size: 14px;
}

.sidebar-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, .45);
    z-index: 90;
}

.sidebar-overlay.open {
    display: block;
}

@media (max-width: 800px) {
    .sidebar {
        transform: translateX(-100%);
    }

    .sidebar.open {
        transform: translateX(0);
    }

    .main {
        margin-left: 0;
        width: 100%;
    }

    .hamburger {
        display: block;
    }

    .content {
        padding: 16px;
    }

    .topbar {
        padding: 0 16px;
    }

    .user-chip-role {
        display: none;
    }
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
        </a>

        <a href="shelters.php" class="nav-link">
            <i class="fa-solid fa-house-chimney"></i> Find Shelter
        </a>

        <a href="recommend_shelter.php" class="nav-link active">
            <i class="fa-solid fa-wand-magic-sparkles"></i> Smart Shelter
        </a>

        <a href="resources.php" class="nav-link">
            <i class="fa-solid fa-boxes-stacked"></i> Resources
        </a>

        <span class="nav-label">My Status</span>

        <a href="evacuation_status.php" class="nav-link">
            <i class="fa-solid fa-person-walking-arrow-right"></i> Evacuation Status
        </a>

        <a href="request_help.php" class="nav-link">
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
                <h1>Smart Shelter Recommendation</h1>
                <p><?= date('l, F j, Y') ?></p>
            </div>
        </div>

        <div class="topbar-right">
            <div class="user-chip">
                <div class="avatar"><?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?></div>
                <div>
                    <div class="user-chip-name"><?= $username ?></div>
                    <div class="user-chip-role">Citizen</div>
                </div>
            </div>
        </div>
    </header>

    <main class="content">
        <div class="page-card">
            <h2>Smart Shelter Recommendation</h2>
            <p>Enter your emergency details. The system will suggest the most suitable shelter.</p>

            <?php if (!empty($error)) { ?>
                <div class="error-box"><?php echo htmlspecialchars($error); ?></div>
            <?php } ?>

            <form method="POST">
                <label>Your City</label>
                <input type="text" name="city" placeholder="Example: Dhaka" required>

                <label>Number of People</label>
                <input type="number" name="people_count" min="1" required>

                <label>Disaster Type</label>
                <select name="disaster_type" required>
                    <option value="">Select Disaster</option>
                    <option value="Flood">Flood</option>
                    <option value="Cyclone">Cyclone</option>
                    <option value="Earthquake">Earthquake</option>
                    <option value="Fire">Fire</option>
                </select>

                <label>Medical Support Needed?</label>
                <select name="medical_need" required>
                    <option value="no">No</option>
                    <option value="yes">Yes</option>
                </select>

                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> Recommend Shelter
                    </button>
                    <a href="dashboard.php" class="btn-secondary">
                        <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </form>
        </div>

        <?php if ($_SERVER["REQUEST_METHOD"] == "POST") { ?>
            <div class="section-hd">
                <h2>Recommended Shelters</h2>
            </div>

            <div class="results-wrap">
                <?php if (count($recommendations) > 0) { ?>
                    <?php foreach ($recommendations as $index => $shelter) { ?>
                        <div class="shelter-card">
                            <h3>
                                <?php echo htmlspecialchars($shelter['shelter_name']); ?>

                                <?php if ($index == 0) { ?>
                                    <span class="best-pill">Best Match</span>
                                <?php } ?>
                            </h3>

                            <div class="shelter-meta">
                                <div>
                                    <strong>Address</strong>
                                    <?php echo htmlspecialchars($shelter['address']); ?>
                                </div>
                                <div>
                                    <strong>City</strong>
                                    <?php echo htmlspecialchars($shelter['city']); ?>
                                </div>
                                <div>
                                    <strong>Total Capacity</strong>
                                    <?php echo $shelter['total_capacity']; ?>
                                </div>
                                <div>
                                    <strong>Current Occupancy</strong>
                                    <?php echo $shelter['current_occupancy']; ?>
                                </div>
                                <div>
                                    <strong>Available Space</strong>
                                    <?php echo $shelter['available_space']; ?>
                                </div>
                                <div>
                                    <strong>Occupancy Rate</strong>
                                    <?php echo $shelter['occupancy_rate']; ?>%
                                </div>
                            </div>

                            <span class="score-pill">Recommendation Score: <?php echo $shelter['score']; ?></span>

                            <p class="reasons-title">Why recommended</p>
                            <ul class="reasons-list">
                                <?php foreach ($shelter['reasons'] as $reason) { ?>
                                    <li><?php echo htmlspecialchars($reason); ?></li>
                                <?php } ?>
                            </ul>
                        </div>
                    <?php } ?>
                <?php } else { ?>
                    <div class="empty-state">No suitable shelter found for your request.</div>
                <?php } ?>
            </div>
        <?php } ?>
    </main>
</div>

<script>
function openSidebar() {
    document.getElementById('sidebar').classList.add('open');
    document.getElementById('overlay').classList.add('open');
}

function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('overlay').classList.remove('open');
}
</script>

</body>
</html>
