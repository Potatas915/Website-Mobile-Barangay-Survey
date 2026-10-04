<?php
require_once __DIR__ . "/../includes/functions.php";
require_resident_login();

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $new_password = $_POST["new_password"];
    $confirm_password = $_POST["confirm_password"];

    if (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        fb_page(function () use ($new_password) {
            fb_update("residents", $_SESSION["resident_id"], array(
                "password"       => $new_password,
                "is_first_login" => 0,
            ));
        });
        redirect("dashboard.php");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Change Password</title>
<link rel="stylesheet" href="../assets/css/style.css">
<script>(function(){var t=localStorage.getItem("theme");if(t==="dark")document.documentElement.setAttribute("data-theme","dark");})();</script>
<script>(function(){try{if(localStorage.getItem("sidebarCollapsed")==="true")document.documentElement.setAttribute("data-sidebar","collapsed");}catch(e){}})();</script>
</head>
<body>
<div class="login-wrapper">
    <div class="login-box">
        <h2>Set a New Password</h2>
        <p>This is your first login. Please set a new password before continuing.</p>
        <?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>
        <form method="POST">
            <label>New Password</label>
            <input type="password" name="new_password" required minlength="6">
            <label>Confirm New Password</label>
            <input type="password" name="confirm_password" required minlength="6">
            <button type="submit">Save Password</button>
        </form>
    </div>
</div>
</body>
</html>