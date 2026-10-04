<?php
require_once __DIR__ . "/../includes/functions.php";
require_staff_login();

// "Updated" = the record has been saved at least once since it was
// created (covers both resident self-service edits and staff edits).
fb_page(function () use (&$residents) {

    $residents = array();

    foreach (fb_rows("residents") as $resident) {
        // The old WHERE clause was updated_at > created_at.
        if (strcmp((string)($resident["updated_at"] ?? ""), (string)($resident["created_at"] ?? "")) > 0) {
            $resident["resident_id"] = $resident["id"];
            $residents[] = $resident;
        }
    }

    // The old query ended with ORDER BY updated_at DESC.
    usort($residents, function ($a, $b) {
        return strcmp((string)$b["updated_at"], (string)$a["updated_at"]);
    });
});
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Updated Records</title>
<link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime(__DIR__ . "/../assets/css/style.css") ?>">
<script>(function(){var t=localStorage.getItem("theme");if(t==="dark")document.documentElement.setAttribute("data-theme","dark");})();</script>
<script>(function(){try{if(localStorage.getItem("sidebarCollapsed")==="true")document.documentElement.setAttribute("data-sidebar","collapsed");}catch(e){}})();</script>
</head>
<body>
<?php include __DIR__ . "/../includes/staff_nav.php"; ?>
<div class="container">
<?php include __DIR__ . "/../includes/staff_topbar.php"; ?>
    <div class="welcome-header">
        <div>
            <h1>Updated Records</h1>
            <p>Residents whose personal information has changed since their account was created, most recent first.</p>
        </div>
        <a class="btn btn-secondary" href="resident_management.php">Back to Resident Management</a>
    </div>

    <div class="card">
        <div class="table-scroll">
        <table>
            <tr><th>Resident Number</th><th>Name</th><th>Email</th><th>Contact</th><th>Last Updated</th><th>Actions</th></tr>
            <?php if (!$residents): ?>
            <tr><td colspan="6" style="text-align:center; color:#889;">No resident records have been updated yet.</td></tr>
            <?php else: foreach ($residents as $r): ?>
            <tr>
                <td><?= e($r["resident_number"]) ?></td>
                <td><?= e(full_resident_name($r)) ?></td>
                <td><?= e($r["email"]) ?></td>
                <td><?= e($r["contact_number"]) ?></td>
                <td><?= e(date("M d, Y g:i A", strtotime($r["updated_at"]))) ?></td>
                <td>
                    <div class="table-actions">
                        <a class="btn btn-sm btn-secondary" href="resident_view.php?resident_id=<?= (int)$r["resident_id"] ?>">View</a>
                        <a class="btn btn-sm btn-secondary" href="resident_edit.php?resident_id=<?= (int)$r["resident_id"] ?>">Edit</a>
                    </div>
                </td>
            </tr>
            <?php endforeach; endif; ?>
        </table>
        </div>
    </div>
</div>
<script src="../assets/js/script.js"></script>
</body>
</html>
