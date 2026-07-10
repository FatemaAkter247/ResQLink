<?php
session_start();
require_once '../DB/db.php';

// Public emergency feature: getting a shelter recommendation must NOT require
// login. No per-user data is read or written here, so guests are fully supported.

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
<html>
<head>
    <title>Smart Shelter Recommendation</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f7fb;
            margin: 0;
            padding: 0;
        }

        .container {
            width: 85%;
            margin: 40px auto;
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 0 12px rgba(0,0,0,0.1);
        }

        h2 {
            color: #d32f2f;
        }

        input, select {
            width: 100%;
            padding: 12px;
            margin: 8px 0 15px;
            border: 1px solid #ccc;
            border-radius: 6px;
        }

        button, .back-btn {
            background: #d32f2f;
            color: white;
            padding: 12px 18px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            cursor: pointer;
        }

        .back-btn {
            background: #333;
            display: inline-block;
            margin-left: 10px;
        }

        .card {
            background: #f9f9f9;
            border-left: 6px solid #d32f2f;
            padding: 18px;
            margin-bottom: 15px;
            border-radius: 8px;
        }

        .best {
            background: #2e7d32;
            color: white;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 13px;
        }

        .error {
            color: red;
            margin-bottom: 15px;
        }

        .score {
            font-weight: bold;
            color: #d32f2f;
        }
    </style>
</head>

<body>

<div class="container">
    <h2>Smart Shelter Recommendation</h2>
    <p>Enter your emergency details. The system will suggest the most suitable shelter.</p>

    <?php if (!empty($error)) { ?>
        <p class="error"><?php echo htmlspecialchars($error); ?></p>
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

        <button type="submit">Recommend Shelter</button>
        <a href="dashboard.php" class="back-btn">Back to Dashboard</a>
    </form>

    <?php if ($_SERVER["REQUEST_METHOD"] == "POST") { ?>
        <h3>Recommended Shelters</h3>

        <?php if (count($recommendations) > 0) { ?>
            <?php foreach ($recommendations as $index => $shelter) { ?>
                <div class="card">
                    <h3>
                        <?php echo htmlspecialchars($shelter['shelter_name']); ?>

                        <?php if ($index == 0) { ?>
                            <span class="best">Best Match</span>
                        <?php } ?>
                    </h3>

                    <p><strong>Address:</strong> <?php echo htmlspecialchars($shelter['address']); ?></p>
                    <p><strong>City:</strong> <?php echo htmlspecialchars($shelter['city']); ?></p>
                    <p><strong>Total Capacity:</strong> <?php echo $shelter['total_capacity']; ?></p>
                    <p><strong>Current Occupancy:</strong> <?php echo $shelter['current_occupancy']; ?></p>
                    <p><strong>Available Space:</strong> <?php echo $shelter['available_space']; ?></p>
                    <p><strong>Occupancy Rate:</strong> <?php echo $shelter['occupancy_rate']; ?>%</p>
                    <p class="score">Recommendation Score: <?php echo $shelter['score']; ?></p>

                    <p><strong>Why recommended:</strong></p>
                    <ul>
                        <?php foreach ($shelter['reasons'] as $reason) { ?>
                            <li><?php echo htmlspecialchars($reason); ?></li>
                        <?php } ?>
                    </ul>
                </div>
            <?php } ?>
        <?php } else { ?>
            <p>No suitable shelter found for your request.</p>
        <?php } ?>
    <?php } ?>

</div>

</body>
</html>