<?php
require_once __DIR__ . "/../includes/functions.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $resident_number = trim($_POST["resident_number"]);
    $password = $_POST["password"];

    fb_page(function () use ($resident_number, $password, &$error) {

        $resident = fb_resident_by_number($resident_number);

        // An unknown Resident Number and a wrong password get the same
        // message, so the form never reveals which numbers exist.

        if (!$resident) {

            $error = "Incorrect resident number or password.";

            return;

        }


        if (($resident["status"] ?? "") === "archived") {

            $error =
                "This account has been deactivated. Please contact the barangay office.";

            return;

        }


        // Passwords are stored in plain text, so this is a straight
        // comparison. hash_equals keeps it constant-time so a wrong password
        // cannot be guessed one character at a time by timing the response.
        if (!hash_equals((string)($resident["password"] ?? ""), (string)$password)) {

            $error =
                "Incorrect resident number or password.";

            return;

        }


        $_SESSION["resident_id"] = $resident["id"];

        $_SESSION["resident_name"] =
            $resident["first_name"] . " " . $resident["last_name"];


        // Log the login

        fb_insert("login_history", array(
            "user_type" => "resident",
            "user_id"   => $resident["id"],
            "login_time" => date("Y-m-d H:i:s"),
        ));


        // Check if this is the resident's first login

        if ((int)($resident["is_first_login"] ?? 0) === 1) {

            redirect("change_password.php");

        }

        redirect("dashboard.php");

    });
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Resident Login</title>
<link rel="stylesheet" href="../assets/css/portal-login.css">
</head>

<body class="login-page">

<canvas id="portalBgCanvas" class="portal-bg-canvas" aria-hidden="true"></canvas>
<div class="portal-orb portal-orb-a" aria-hidden="true"></div>
<div class="portal-orb portal-orb-b" aria-hidden="true"></div>

<div class="portal-card-wrap">

    <a href="../index.php" class="portal-back-home">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        Back to home
    </a>

    <div class="portal-card">

        <div class="portal-card-badge">
            <span class="badge-ic">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </span>
        </div>

        <span class="portal-badge-chip">Resident Portal</span>
        <h2>Log in to your account</h2>
        <p class="portal-subtitle">Use your resident number to take surveys and stay updated on community programs. Your default password is the same as your resident number.</p>

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
                <label for="resident_number">Resident Number</label>
                <span class="pig-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </span>
                <input
                    type="text"
                    id="resident_number"
                    name="resident_number"
                    placeholder="Resident Number"
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
            Staff member? <a href="../staff/login.php">Go to staff login</a>
        </div>

    </div>

    <div class="portal-card-footer">&copy; <?= date("Y") ?> Barangay Health Center</div>

</div>

<script src="../assets/js/portal-bg.js"></script>
<script src="../assets/js/script.js"></script>

</body>
</html>