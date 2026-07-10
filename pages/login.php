<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ResQLink</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --accent: #c62828;
            --accent-dark: #8e0000;
            --accent-light: #ffebee;
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
            flex-direction: column;
        }

        a { color: inherit; }

        /* ---------- Top bar ---------- */
        .topbar {
            height: 66px;
            background: var(--white);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 28px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
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

        .topbar-links {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .top-link {
            padding: 9px 18px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            color: var(--muted);
        }

        .top-link:hover {
            background: var(--accent-light);
            color: var(--accent);
        }

        .top-link.active {
            background: var(--accent);
            color: #fff;
        }

        /* ---------- Auth wrap ---------- */
        .auth-wrap {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        .auth-card {
            width: 100%;
            max-width: 440px;
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 36px 34px;
        }

        .auth-head {
            text-align: center;
            margin-bottom: 26px;
        }

        .auth-icon {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            background: var(--accent-light);
            color: var(--accent);
            display: grid;
            place-items: center;
            font-size: 22px;
            margin: 0 auto 14px;
        }

        .auth-head h1 {
            font-size: 20px;
            font-weight: 800;
        }

        .auth-head p {
            font-size: 13px;
            color: var(--muted);
            margin-top: 6px;
        }

        .flash {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 18px;
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .form-label {
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 6px;
            display: block;
        }

        .form-control {
            width: 100%;
            border-radius: 10px;
            border: 1px solid var(--border);
            padding: 11px 14px;
            font-size: 14px;
            font-family: inherit;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(198, 40, 40, 0.15);
        }

        .mb-3 { margin-bottom: 18px; }

        .btn-primary {
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 22px;
            border-radius: 10px;
            background: var(--accent);
            color: #fff;
            font-size: 14px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            transition: all .2s ease;
        }

        .btn-primary:hover {
            background: var(--accent-dark);
            transform: translateY(-2px);
        }

        .auth-footer {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
            color: var(--muted);
        }

        .auth-footer a {
            color: var(--accent);
            font-weight: 700;
            text-decoration: none;
        }

        .auth-footer a:hover { text-decoration: underline; }

        @media (max-width: 500px) {
            .topbar { padding: 0 16px; }
            .top-link span { display: none; }
        }
    </style>
</head>
<body>

<header class="topbar">
    <a href="../index.php" class="brand">
        <div class="brand-icon">
            <i class="fa-solid fa-shield-halved"></i>
        </div>
        <span class="brand-name">ResQ<span>Link</span></span>
    </a>

    <nav class="topbar-links">
        <a href="../index.php" class="top-link">Home</a>
        <a href="login.php" class="top-link active">Login</a>
        <a href="register.php" class="top-link">Register</a>
    </nav>
</header>

<div class="auth-wrap">
    <div class="auth-card">
        <div class="auth-head">
            <div class="auth-icon">
                <i class="fa-solid fa-right-to-bracket"></i>
            </div>
            <h1>Welcome Back</h1>
            <p>Log in to your ResQLink account</p>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="flash">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?php
                echo htmlspecialchars($_SESSION['error']);
                unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <form action="login_action.php" method="POST">
            <div class="mb-3">
                <label for="login_input" class="form-label">Email or Phone</label>
                <input type="text" class="form-control" id="login_input" name="login_input" placeholder="Enter your email or phone" required>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
            </div>

            <button type="submit" class="btn-primary">
                <i class="fa-solid fa-right-to-bracket"></i> Login
            </button>
        </form>

        <div class="auth-footer">
            <p>Don't have an account? <a href="register.php">Register here</a></p>
        </div>
    </div>
</div>

</body>
</html>
