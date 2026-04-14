<?php
session_start();
require_once __DIR__ . "/../DB/db.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: register.php");
    exit;
}

$full_name = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// 🔥 Always USER
$role_id = 1;

if ($full_name === '' || $email === '' || $password === '') {
    header("Location: register.php?error=Please fill all fields");
    exit;
}

// check email
$check = $conn->prepare("SELECT id FROM users WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    header("Location: register.php?error=Email already exists");
    exit;
}
$check->close();

// hash password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// insert
$stmt = $conn->prepare("
    INSERT INTO users (full_name, email, password, role_id)
    VALUES (?, ?, ?, ?)
");

$stmt->bind_param("sssi", $full_name, $email, $hashed_password, $role_id);

if ($stmt->execute()) {

    // ✅ Auto login user
    $_SESSION['user_id'] = $stmt->insert_id;
    $_SESSION['full_name'] = $full_name;
    $_SESSION['role_id'] = $role_id;

    // ✅ Go to homepage
    header("Location: ../index.php");
    exit;

} else {
    header("Location: register.php?error=Something went wrong");
    exit;
}
?>