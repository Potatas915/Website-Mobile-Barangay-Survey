<?php
require_once __DIR__ . "/../includes/functions.php";
require_resident_login();

$resident_id = (string)$_SESSION["resident_id"];
$survey_id = isset($_GET["survey_id"]) ? (int)$_GET["survey_id"] : 0;

$error = "";

fb_page(function () use ($resident_id, $survey_id, &$survey, &$questions, &$choices_by_question) {

    // prevent answering the same survey twice
    // (the old UNIQUE KEY on responses(survey_id, resident_id))
    foreach (fb_rows("responses") as $response) {
        if ((string)($response["survey_id"] ?? "") === (string)$survey_id
            && (string)($response["resident_id"] ?? "") === $resident_id) {
            redirect("dashboard.php");
        }
    }

    $survey = fb_get("surveys/" . $survey_id);
    if (!is_array($survey) || ($survey["status"] ?? "") !== "active") {
        redirect("dashboard.php");
    }

    $questions = [];
    $choices_by_question = [];

    foreach (fb_rows("surveys/" . $survey_id . "/questions") as $qid => $question) {
        $question["question_id"] = (string)$qid;

        $choices = [];
        foreach (fb_rows("surveys/" . $survey_id . "/questions/" . $qid . "/choices") as $cid => $choice) {
            $choice["choice_id"] = (string)$cid;
            $choices[] = $choice;
        }
        // The old query ended with ORDER BY choice_order.
        usort($choices, function ($a, $b) {
            return (int)$a["choice_order"] <=> (int)$b["choice_order"];
        });

        $questions[] = $question;
        $choices_by_question[(string)$qid] = $choices;
    }

    // The old query ended with ORDER BY question_order.
    usort($questions, function ($a, $b) {
        return (int)$a["question_order"] <=> (int)$b["question_order"];
    });

});

// handle submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        // Every answer is collected and checked before anything is written.
        // Firebase has no transactions, so validating up front is what keeps
        // a bad submission from leaving a half-filled response behind.
        $answers = [];

        foreach ($questions as $q) {
            $qid = $q["question_id"];
            $field = "q_" . $qid;

            if ($q["question_type"] === "short_answer") {
                $answers[$qid] = array(
                    "question_id" => $qid,
                    "choice_id"   => "",
                    "answer_text" => trim($_POST[$field] ?? ""),
                );
            } else {
                // multiple_choice, yes_no, rating all use a choice_id
                $posted = $_POST[$field] ?? "";

                if ($posted !== "") {
                    // Only accept a choice that really belongs to this
                    // question; the form is not the only thing that can post.
                    $valid = false;
                    foreach ($choices_by_question[$qid] as $choice) {
                        if ((string)$choice["choice_id"] === (string)$posted) {
                            $valid = true;
                            break;
                        }
                    }

                    if (!$valid) {
                        throw new Exception("Choice does not belong to this question.");
                    }

                    $answers[$qid] = array(
                        "question_id" => $qid,
                        "choice_id"   => (string)$posted,
                        "answer_text" => "",
                    );
                } elseif ((int)$q["is_required"] === 1) {
                    throw new Exception("Missing required answer.");
                }
            }
        }

        fb_insert("responses", array(
            "survey_id"     => (string)$survey_id,
            "resident_id"   => $resident_id,
            "resident_name" => $_SESSION["resident_name"],
            "submitted_at"  => date("Y-m-d H:i:s"),
            "answers"       => $answers,
        ));

        redirect("confirmation.php");
    } catch (Throwable $e) {
        // Firebase/network problems are not the resident's fault, so keep the
        // message for them specific instead of blaming the answers.
        $error = $e instanceof FirebaseException
            ? "The database could not be reached, so your answers were not saved. Please try again."
            : "Please answer all required questions.";
    }
}?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= e($survey["title"]) ?></title>
<link rel="stylesheet" href="../assets/css/style.css">
<script>(function(){var t=localStorage.getItem("theme");if(t==="dark")document.documentElement.setAttribute("data-theme","dark");})();</script>
<script>(function(){try{if(localStorage.getItem("sidebarCollapsed")==="true")document.documentElement.setAttribute("data-sidebar","collapsed");}catch(e){}})();</script>
</head>
<body>
<?php include __DIR__ . "/../includes/resident_nav.php"; ?>
<div class="container">
    <?php include __DIR__ . "/../includes/resident_topbar.php"; ?>

    <div class="welcome-header">
        <div>
            <h1><?= e($survey["title"]) ?></h1>
            <p><a href="dashboard.php" onclick="event.preventDefault(); openConfirmModal({url: 'dashboard.php', title: 'Leave this survey?', message: 'Your answers on this page have not been submitted and will be lost.', confirmLabel: 'Leave', danger: true})">&larr; Back to Dashboard</a></p>
        </div>
    </div>

    <div class="card">
        <p><?= e($survey["description"]) ?></p>
        <?php if (isset($error)): ?><div class="error"><?= e($error) ?></div><?php endif; ?>

        <form method="POST" onsubmit="return validateSurveyForm()">
            <?php foreach ($questions as $q): ?>
                <div class="card" data-required="<?= $q["is_required"] ?>">
    <label style="font-weight:bold;">
                    <label style="font-weight:bold;">
                        <?= e($q["question_text"]) ?>
                        <?php if ($q["is_required"]): ?><span style="color:#b13f3f;">*</span><?php endif; ?>
                    </label>

                    <?php if ($q["question_type"] === "short_answer"): ?>
                        <textarea name="q_<?= $q["question_id"] ?>"></textarea>

                    <?php elseif ($q["question_type"] === "yes_no"): ?>
                        <div class="choice-options">
                            <?php foreach ($choices_by_question[$q["question_id"]] as $c): ?>
                                <label>
                                    <input type="radio" name="q_<?= $q["question_id"] ?>" value="<?= $c["choice_id"] ?>">
                                    <?= e($c["choice_text"]) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>

                    <?php elseif ($q["question_type"] === "rating"): ?>
                        <div class="rating-options">
                            <?php foreach ($choices_by_question[$q["question_id"]] as $c): ?>
                                <label>
                                    <input type="radio" name="q_<?= $q["question_id"] ?>" value="<?= $c["choice_id"] ?>">
                                    <?= e($c["choice_text"]) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>

                    <?php else: // multiple_choice ?>
                        <div class="choice-options">
                            <?php foreach ($choices_by_question[$q["question_id"]] as $c): ?>
                                <label>
                                    <input type="radio" name="q_<?= $q["question_id"] ?>" value="<?= $c["choice_id"] ?>">
                                    <?= e($c["choice_text"]) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <button type="submit">Submit Survey</button>
        </form>
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
</body>
</html>