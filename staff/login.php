<?php
require_once __DIR__ . "/../includes/functions.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"]);
    $password = $_POST["password"];

    fb_page(function () use ($username, $password, &$error) {

    $staff = fb_staff_by_username($username);

    // An unknown username and a wrong password get the same message, so the
    // form never reveals which usernames exist.

    // Passwords are stored in plain text, so this is a straight comparison.
    // hash_equals keeps it constant-time so a wrong password cannot be guessed
    // one character at a time by timing the response.
    if (!$staff || !hash_equals((string)($staff["password"] ?? ""), (string)$password)) {

        $error = "Incorrect username or password.";

        return;

    }

    $_SESSION["staff_id"] = $staff["id"];
    $_SESSION["staff_name"] = $staff["full_name"];
    $_SESSION["staff_role"] = $staff["role"];

    fb_insert("login_history", array(
        "user_type" => "staff",
        "user_id"   => $staff["id"],
        "login_time" => date("Y-m-d H:i:s"),
    ));

    redirect("dashboard.php");

});
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Staff Login</title>
<link rel="stylesheet" href="../assets/css/portal-login.css">
</head>

<body class="login-page is-staff">

<canvas id="portalBgCanvas" class="portal-bg-canvas" aria-hidden="true"></canvas>
<div class="portal-orb portal-orb-a" aria-hidden="true"></div>
<div class="portal-orb portal-orb-b" aria-hidden="true"></div>

<div class="portal-card-wrap">

    <a href="../index.php" class="portal-back-home">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        Back to home
    </a>

    <div class="portal-card">

        <div class="portal-card-badge is-staff">
            <span class="badge-ic">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="14" rx="2"/><path d="M8 21h8M12 18v3"/></svg>
            </span>
        </div>

        <span class="portal-badge-chip">Staff Portal</span>
        <h2>Log in to your account</h2>
        <p class="portal-subtitle">Enter your username and password to continue.</p>

        <?php if ($error): ?>
            <div class="portal-error">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v5M12 16h.01"/></svg>
                <?= e($error) ?>
            </div>
        <?php elseif (($_GET["reset"] ?? "") === "1"): ?>
            <div class="portal-success">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9 12l2 2 4-4"/></svg>
                Password updated. You can log in now.
            </div>
        <?php endif; ?>

        <form method="POST" class="portal-form">

            <div class="portal-input-group">
                <label for="username">Username</label>
                <span class="pig-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </span>
                <input
                    type="text"
                    id="username"
                    name="username"
                    placeholder="Username"
                    autocomplete="username"
                    required
                >
            </div>

            <div class="portal-input-group">
                <label for="password">Password</label>
                <span class="pig-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                </span>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Password"
                    autocomplete="current-password"
                    required
                >
                <button type="button" class="pig-toggle" data-target="password" aria-label="Show password" aria-pressed="false" onclick="togglePasswordField(this)">
                    <svg class="eye-on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
                    <svg class="eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a21.3 21.3 0 0 1 5.06-5.94M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 7 11 7a21.3 21.3 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/></svg>
                </button>
            </div>

            <div class="portal-forgot-row">
                <a href="forgot_password.php">Forgot password?</a>
            </div>

            <button type="submit" class="portal-submit">Log In</button>

        </form>

        <div class="portal-switch-note">
            Community resident? <a href="../resident/login.php">Go to resident login</a>
        </div>

    </div>

    <div class="portal-card-footer">&copy; <?= date("Y") ?> Barangay Health Center</div>

</div>

<script src="../assets/js/portal-bg.js"></script>
<script src="../assets/js/script.js"></script>

</body>
</html>