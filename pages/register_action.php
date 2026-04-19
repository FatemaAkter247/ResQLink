<?php
session_start();
require_once __DIR__ . "/../DB/db.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: register.php");
    exit;
}

$first_name = trim($_POST['first_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$full_name = trim($first_name . ' ' . $last_name);

$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$address = trim($_POST['address'] ?? '');
$role_id = (int)($_POST['role_id'] ?? 0);
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

if (
    $first_name === '' ||
    $last_name === '' ||
    $email === '' ||
    $phone === '' ||
    $password === '' ||
    $confirm_password === '' ||
    $role_id === 0
) {
    die("Please fill all required fields. <br><a href='register.php'>Go Back</a>");
}

if ($password !== $confirm_password) {
    die("Password and Confirm Password do not match. <br><a href='register.php'>Go Back</a>");
}

$check = $conn->prepare("SELECT id FROM users WHERE phone = ? OR email = ?");
$check->bind_param("ss", $phone, $email);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
    die("Phone or Email already exists. <br><a href='register.php'>Go Back</a>");
}
$check->close();

$password_hash = password_hash($password, PASSWORD_DEFAULT);

/*
    Your current project uses:
    role_id, full_name, phone, email, password_hash
*/
$stmt = $conn->prepare("
    INSERT INTO users (role_id, full_name, phone, email, password_hash)
    VALUES (?, ?, ?, ?, ?)
");
$stmt->bind_param("issss", $role_id, $full_name, $phone, $email, $password_hash);

if ($stmt->execute()) {
    $new_user_id = $stmt->insert_id;

    // Citizen role = 1 from your register form
    // register.php shows Citizen option value="1"
    if ($role_id === 1) {
        // Clear any previous logged-in user's session
        $_SESSION = [];

        // Create a fresh session id
        session_regenerate_id(true);

        // Set the NEW citizen's session exactly like login_action.php
        $_SESSION['user_id'] = $new_user_id;
        $_SESSION['role_id'] = $role_id;
        $_SESSION['full_name'] = $full_name;

        header("Location: dashboard.php");
        exit;
    } else {
        header("Location: login.php?registered=success");
        exit;
    }
} else {
    echo "Registration Failed: " . $conn->error;
}

$stmt->close();
$conn->close();
?>