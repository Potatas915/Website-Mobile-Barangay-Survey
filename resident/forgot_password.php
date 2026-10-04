<?php
require_once __DIR__ . "/../includes/functions.php";

// Simple two-step reset flow: no outbound email is configured for this
// system, so identity is confirmed with resident number + the email on
// file instead of a mailed reset link. Once confirmed, the resident sets
// a new password right away.

$step = "verify";
$error = "";
$resident_id = $_SESSION["reset_resident_id"] ?? null;

if ($resident_id) {
    $step = "reset";
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "verify") {

    $resident_number = trim($_POST["resident_number"]);
    $email = trim($_POST["email"]);

    fb_page(function () use ($resident_number, $email, &$error, &$resident_id, &$step) {

        $resident = fb_resident_by_number($resident_number);

        // MySQL compared email with a case-insensitive collation, so keep
        // matching that way here.

        if ($resident && strcasecmp((string)($resident["email"] ?? ""), $email) === 0) {

            $_SESSION["reset_resident_id"] = $resident["id"];
            $resident_id = $resident["id"];
            $step = "reset";

        } else {

            $error = "We couldn't match that resident number and email.";

        }

    });

} elseif ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "reset") {

    if (!$resident_id) {
        redirect("forgot_password.php");
    }

    $new_password = $_POST["new_password"];
    $confirm_password = $_POST["confirm_password"];

    if (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters.";
        $step = "reset";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
        $step = "reset";
    } else {

        fb_page(function () use ($new_password, $resident_id, &$error, &$step) {

        $resident = fb_get("residents/" . $resident_id);

        if (is_array($resident)
            && hash_equals((string)($resident["password"] ?? ""), (string)$new_password)) {

            $error = "That's your current password. Please choose a different one.";
            $step = "reset";

            return;

        }

        fb_update("residents", $resident_id, array(
            "password"       => $new_password,
            "is_first_login" => 0,
        ));

        unset($_SESSION["reset_resident_id"]);
        redirect("login.php?reset=1");

    });
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password &mdash; Resident</title>
<link rel="stylesheet" href="../assets/css/portal-login.css">
</head>

<body class="login-page">

<canvas id="portalBgCanvas" class="portal-bg-canvas" aria-hidden="true"></canvas>
<div class="portal-orb portal-orb-a" aria-hidden="true"></div>
<div class="portal-orb portal-orb-b" aria-hidden="true"></div>

<div class="portal-card-wrap">

    <a href="login.php" class="portal-back-home">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        Back to login
    </a>

    <div class="portal-card">

        <div class="portal-card-badge">
            <span class="badge-ic">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
            </span>
        </div>

        <span class="portal-badge-chip">Resident Portal</span>

        <?php if ($step === "verify"): ?>

            <h2>Reset your password</h2>
            <p class="portal-subtitle">Confirm your resident number and the email on file.</p>

            <?php if ($error): ?>
                <div class="portal-error">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v5M12 16h.01"/></svg>
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="portal-form">
                <input type="hidden" name="action" value="verify">

                <div class="portal-input-group">
                    <label for="resident_number">Resident Number</label>
                    <span class="pig-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    </span>
                    <input type="text" id="resident_number" name="resident_number" placeholder="Resident Number" required>
                </div>

                <div class="portal-input-group">
                    <label for="email">Email</label>
                    <span class="pig-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/></svg>
                    </span>
                    <input type="email" id="email" name="email" placeholder="Email on file" required>
                </div>

                <button type="submit" class="portal-submit">Continue</button>
            </form>

        <?php else: ?>

            <h2>Set a new password</h2>
            <p class="portal-subtitle">Choose a new password for your account.</p>

            <?php if ($error): ?>
                <div class="portal-error">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v5M12 16h.01"/></svg>
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="portal-form">
                <input type="hidden" name="action" value="reset">

                <div class="portal-input-group">
                    <label for="new_password">New Password</label>
                    <span class="pig-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                    </span>
                    <input type="password" id="new_password" name="new_password" placeholder="New Password" required minlength="6">
                    <button type="button" class="pig-toggle" data-target="new_password" aria-label="Show password" aria-pressed="false" onclick="togglePasswordField(this)">
                        <svg class="eye-on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg class="eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a21.3 21.3 0 0 1 5.06-5.94M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 7 11 7a21.3 21.3 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/></svg>
                    </button>
                </div>

                <div class="portal-input-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <span class="pig-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                    </span>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm New Password" required minlength="6">
                    <button type="button" class="pig-toggle" data-target="confirm_password" aria-label="Show password" aria-pressed="false" onclick="togglePasswordField(this)">
                        <svg class="eye-on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg class="eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a21.3 21.3 0 0 1 5.06-5.94M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 7 11 7a21.3 21.3 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/></svg>
                    </button>
                </div>

                <button type="submit" class="portal-submit">Save Password</button>
            </form>

        <?php endif; ?>

        <div class="portal-switch-note">
            Remembered it? <a href="login.php">Back to login</a>
        </div>

    </div>

    <div class="portal-card-footer">&copy; <?= date("Y") ?> Barangay Health Center</div>

</div>

<script src="../assets/js/portal-bg.js"></script>
<script src="../assets/js/script.js"></script>

</body>
</html>
