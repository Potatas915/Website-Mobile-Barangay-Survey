<?php
require_once __DIR__ . "/../includes/functions.php";
require_resident_login();

$resident_id = $_SESSION["resident_id"];

// active surveys within date range, with a flag for whether this resident already answered
fb_page(function () use ($resident_id, &$surveys, &$lifetime_answered,
                       &$total_ever, &$completion_rate, &$profile_incomplete) {

    // The subquery that used to count this resident's answers per survey is a
    // lookup in the responses we already fetch for the totals below.
    $answered_by_survey = array();
    foreach (fb_rows("responses") as $response) {
        if ((string)($response["resident_id"] ?? "") === (string)$resident_id) {
            $lifetime_answered++;
            $survey_id = (string)($response["survey_id"] ?? "");
            $answered_by_survey[$survey_id] = ($answered_by_survey[$survey_id] ?? 0) + 1;
        }
    }

    $today = date("Y-m-d");

    $surveys = array();
    foreach (fb_rows("surveys") as $survey) {
        if (($survey["status"] ?? "") !== "active") {
            continue;
        }
        if ($today < $survey["start_date"] || $today > $survey["end_date"]) {
            continue;
        }
        $survey["survey_id"] = $survey["id"];
        $survey["already_answered"] = $answered_by_survey[(string)$survey["id"]] ?? 0;
        $surveys[] = $survey;
    }

    // MySQL ordered these by start_date DESC.
    usort($surveys, function ($a, $b) {
        return strcmp((string)$b["start_date"], (string)$a["start_date"]);
    });

    $total_ever = count(fb_rows("surveys"));
    $completion_rate = $total_ever > 0 ? round(($lifetime_answered / $total_ever) * 100, 1) : 0;

    // Nudge the resident to finish their personal information profile
    // (Act 5 - Set A) if the core fields are still blank.
    $resident = fb_get("residents/" . $resident_id);
    $profile_check = is_array($resident) ? $resident : array();
    $profile_incomplete = empty($profile_check["birthday"]) || empty($profile_check["civil_status"])
        || empty($profile_check["contact_number"]) || empty($profile_check["address"]) || empty($profile_check["occupation"]);
});

/* ---------------------------------------------------------
   Quick snapshot stats for this resident
--------------------------------------------------------- */
$available_count = count($surveys);
$answered_now = count(array_filter($surveys, fn($s) => $s["already_answered"] > 0));
$pending_count = $available_count - $answered_now;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Resident Dashboard</title>
<link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime(__DIR__ . "/../assets/css/style.css") ?>">
<script>(function(){var t=localStorage.getItem("theme");if(t==="dark")document.documentElement.setAttribute("data-theme","dark");})();</script>
</head>
<body>
<?php include __DIR__ . "/../includes/resident_nav.php"; ?>
<div class="container">
    <?php include __DIR__ . "/../includes/resident_topbar.php"; ?>

    <div class="welcome-header">
        <div>
            <h1>Welcome back, <?= e($_SESSION["resident_name"]) ?> <span class="wave">👋</span></h1>
            <p>Here's what's happening with your barangay health surveys.</p>
        </div>
    </div>

    <?php if ($profile_incomplete): ?>
    <div class="card" style="border-left:4px solid var(--blue-500); margin-bottom:18px;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
            <div>
                <strong>Your profile isn't complete yet.</strong>
                <p style="margin:4px 0 0; font-size:13px; color:#667;">Add your birthday, civil status, occupation, and other details so the health center has accurate records on file.</p>
            </div>
            <a class="btn btn-sm" href="profile.php">Complete My Profile</a>
        </div>
    </div>
    <?php endif; ?>

    <div class="stat-cards-grid">
        <div class="stat-card">
            <div class="stat-card-top">
                <span class="stat-ic teal"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></span>
                <span class="stat-label">Available Surveys</span>
            </div>
            <div class="stat-number"><?= number_format($available_count) ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-card-top">
                <span class="stat-ic green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg></span>
                <span class="stat-label">Answered</span>
            </div>
            <div class="stat-number"><?= number_format($answered_now) ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-card-top">
                <span class="stat-ic amber"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span>
                <span class="stat-label">Pending</span>
            </div>
            <div class="stat-number"><?= number_format($pending_count) ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-card-top">
                <span class="stat-ic teal"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></span>
                <span class="stat-label">Lifetime Participation</span>
            </div>
            <div class="stat-number"><?= $completion_rate ?>%</div>
        </div>
    </div>

    <div class="card">
        <h2>Available Surveys</h2>
        <?php if (count($surveys) === 0): ?>
            <p class="muted-note">There are no active surveys right now.</p>
        <?php endif; ?>
        <?php foreach ($surveys as $survey): ?>
            <div class="survey-list-item">
                <div>
                    <strong><?= e($survey["title"]) ?></strong><br>
                    <small><?= e($survey["description"]) ?></small><br>
                    <small>Open until <?= e($survey["end_date"]) ?></small>
                </div>
                <div>
                    <?php if ($survey["already_answered"] > 0): ?>
                        <span class="success" style="padding:6px 10px;">Answered</span>
                    <?php else: ?>
                        <a class="btn" href="survey_form.php?survey_id=<?= (int)$survey["survey_id"] ?>">Answer Survey</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<script src="../assets/js/script.js"></script>
</body>
</html>
