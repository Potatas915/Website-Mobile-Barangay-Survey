<?php
require_once __DIR__ . "/../includes/functions.php";
require_staff_login();

$survey_id = isset($_GET["survey_id"]) ? (string)(int)$_GET["survey_id"] : "0";

$survey = fb_page(function () use ($survey_id) { return fb_get("surveys/" . $survey_id); });

if (!is_array($survey)) {
    redirect("survey_management.php");
}

$survey["survey_id"] = $survey_id;

$error = "";
$original_start_date = $survey["start_date"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $title = trim($_POST["title"]);
    $description = trim($_POST["description"]);
    $start_date = $_POST["start_date"];
    $end_date = $_POST["end_date"];
    $start_date_changed = $start_date !== $original_start_date;

    if ($title === "" || $start_date === "" || $end_date === "") {
        $error = "Please fill in the survey title and both dates.";
    } elseif ($start_date_changed && !is_valid_start_date($start_date)) {
        $error = "Start date cannot be earlier than today.";
    } elseif ($end_date < $start_date) {
        $error = "End date cannot be earlier than the start date.";
    } else {
        fb_page(function () use ($survey_id, $title, $description, $start_date, $end_date) {
            fb_update("surveys", $survey_id, array(
                "title" => $title,
                "description" => $description,
                "start_date" => $start_date,
                "end_date" => $end_date,
            ));
        });
        redirect("survey_management.php");
    }

    // keep the edited values on screen if validation failed
    $survey["title"] = $title;
    $survey["description"] = $description;
    $survey["start_date"] = $start_date;
    $survey["end_date"] = $end_date;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Survey</title>
<link rel="stylesheet" href="../assets/css/style.css">
<script>(function(){var t=localStorage.getItem("theme");if(t==="dark")document.documentElement.setAttribute("data-theme","dark");})();</script>
<script>(function(){try{if(localStorage.getItem("sidebarCollapsed")==="true")document.documentElement.setAttribute("data-sidebar","collapsed");}catch(e){}})();</script>
</head>
<body>
<?php include __DIR__ . "/../includes/staff_nav.php"; ?>
<div class="container">
<?php include __DIR__ . "/../includes/staff_topbar.php"; ?>
    <div class="card">
        <h2>Edit Survey</h2>
        <?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>

        <form method="POST">
            <label>Survey Title</label>
            <input type="text" name="title" required value="<?= e($survey["title"]) ?>">

            <label>Description</label>
            <textarea name="description"><?= e($survey["description"]) ?></textarea>

            <label>Start Date</label>
            <input type="date" name="start_date" id="start_date" required
                   min="<?= min(date("Y-m-d"), $original_start_date) ?>" value="<?= e($survey["start_date"]) ?>">

            <label>End Date</label>
            <input type="date" name="end_date" id="end_date" required
                   min="<?= date("Y-m-d") ?>" value="<?= e($survey["end_date"]) ?>">

            <button type="submit">Save Changes</button>
            <button type="button" class="btn btn-secondary" onclick="openConfirmModal({url: 'survey_management.php', title: 'Discard these changes?', message: 'Any edits you made to this survey will be lost.', confirmLabel: 'Discard', danger: true})">Cancel</button>
    
        </form>

        <p style="margin-top:16px; font-size:13px; color:#667;">
            To change questions for this survey, go to
            <a href="question_management.php?survey_id=<?= (int)$survey["survey_id"] ?>">Questions</a>
            from the Survey Management list.
        </p>




        
    </div>



    
</div>

<div class="modal-overlay" id="confirmModal">
    <div class="modal-box modal-sm">
        <div class="modal-icon" id="confirmModalIcon"></div>
        <h3 id="confirmModalTitle">Are you sure?</h3>
        <p class="modal-message" id="confirmModalMessage"></p>
        <div class="modal-actions">
            <button type="button" class="btn btn-secondary" onclick="closeConfirmModal()">Cancel</button>
            <button type="button" class="btn btn-danger" id="confirmModalConfirmBtn" onclick="proceedConfirmModal()">Confirm</button>
        </div>
    </div>
</div>





<script src="../assets/js/script.js"></script>
<script>
// Confirm before actually saving the survey's details
const editForm = document.querySelector("form");
editForm.addEventListener("submit", function (e) {
    e.preventDefault();
        openConfirmModal({
        title: "Save these changes?",
        message: "This will update the survey's title, description, and dates.",
        confirmLabel: "Save Changes",
        danger: false,
        confirmClass: "",
        onConfirm: () => editForm.submit()
    });
});

const startDateInput = document.getElementById("start_date");
const endDateInput = document.getElementById("end_date");
startDateInput.addEventListener("change", () => {
    endDateInput.min = startDateInput.value;
    if (endDateInput.value && endDateInput.value < startDateInput.value) {
        endDateInput.value = startDateInput.value;
    }
});
</script>
</body>
</html>