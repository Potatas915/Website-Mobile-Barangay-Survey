<?php
require_once __DIR__ . "/../includes/functions.php";
require_staff_login();

$survey_id = (int)(isset($_GET["survey_id"]) ? $_GET["survey_id"] : (isset($_POST["survey_id"]) ? $_POST["survey_id"] : 0));

// A survey's questions can only be changed while it has zero responses,
// so past results always stay consistent with what was actually asked.
$response_count = 0;
foreach (fb_rows("responses") as $response) {
    if ((string)($response["survey_id"] ?? "") === (string)$survey_id) {
        $response_count++;
    }
}
$is_locked = $response_count > 0;

if ($is_locked) {
    die("Error: You cannot edit questions for a survey that already has responses.");
}

$survey = fb_get("surveys/" . $survey_id);
if (!is_array($survey)) redirect("survey_management.php");

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $questions_text = $_POST["question_text"] ?? [];
    $questions_type = $_POST["question_type"] ?? [];
    $questions_required = $_POST["question_required"] ?? [];
    $choices = $_POST["choices"] ?? [];

    $valid_types = ["multiple_choice", "yes_no", "rating", "short_answer"];

    if (count($questions_text) === 0) {
        $error = "Please add at least one question.";
    } else {

        // The old code wrapped this in a transaction because it inserted and
        // deleted questions one row at a time. Firebase writes the whole
        // questions subtree in a single PUT instead, so the survey is either
        // fully updated or not updated at all - no half-saved state.
        $payload = array();
        $order = 0;

        foreach ($questions_text as $index => $qtext) {
            $qtext = trim($qtext);
            if ($qtext === "") continue;

            $qtype = $questions_type[$index] ?? "";
            if (!in_array($qtype, $valid_types, true)) continue;

            // An unchecked checkbox is simply not posted, but anything that can
            // post this form may send an explicit 0, so check the value.
            $required = !empty($questions_required[$index]) && $questions_required[$index] !== "0" ? 1 : 0;
            $order++;

            // Question ids are numbered per survey, and this page is only
            // reachable while the survey has no responses, so nothing outside
            // this subtree can be holding on to the old ids.
            $question_id = (string)$order;

            $row = array(
                "question_id"    => $question_id,
                "survey_id"      => (string)$survey_id,
                "question_text"  => $qtext,
                "question_type"  => $qtype,
                "is_required"    => $required,
                "question_order" => $order,
            );

            if ($qtype === "yes_no") {
                $row["choices"] = array();
                foreach (["Yes", "No"] as $i => $label) {
                    $row["choices"][(string)($i + 1)] = array(
                        "choice_id"    => (string)($i + 1),
                        "question_id"  => $question_id,
                        "choice_text"  => $label,
                        "choice_order" => $i + 1,
                    );
                }
            } elseif ($qtype === "rating") {
                $labels = ["1 - Very Dissatisfied", "2 - Dissatisfied", "3 - Neutral", "4 - Satisfied", "5 - Very Satisfied"];
                $row["choices"] = array();
                foreach ($labels as $i => $label) {
                    $row["choices"][(string)($i + 1)] = array(
                        "choice_id"    => (string)($i + 1),
                        "question_id"  => $question_id,
                        "choice_text"  => $label,
                        "choice_order" => $i + 1,
                    );
                }
            } elseif ($qtype === "multiple_choice" && isset($choices[$index])) {
                $row["choices"] = array();
                $order_c = 0;
                foreach ($choices[$index] as $choice_text) {
                    $choice_text = trim($choice_text);
                    if ($choice_text === "") continue;
                    $order_c++;
                    $row["choices"][(string)$order_c] = array(
                        "choice_id"    => (string)$order_c,
                        "question_id"  => $question_id,
                        "choice_text"  => $choice_text,
                        "choice_order" => $order_c,
                    );
                }
            }

            $payload[$question_id] = $row;
        }

        if (count($payload) === 0) {
            $error = "Please add at least one question.";
        } else {
            $question_total = count($payload);
            fb_page(function () use ($survey_id, $payload, $question_total) {

                // One write replaces the whole subtree. Questions dropped from
                // the form simply never make it into $payload, which is how the
                // old "DELETE ... WHERE question_id NOT IN (...)" behaved.
                fb_set("surveys/" . $survey_id . "/questions", $payload);

                // Keep the global counter ahead of the ids we just used.
                $counter = (int)fb_get("counters/survey_questions");
                fb_set("counters/survey_questions", max($counter, $question_total));
            });
            redirect("question_management.php?survey_id=" . $survey_id . "&saved=1");
        }
    }
}

fb_page(function () use ($survey_id, &$questions) {

    $questions = array();

    // ORDER BY question_order
    $rows = fb_rows("surveys/" . $survey_id . "/questions");
    uasort($rows, function ($a, $b) {
        return (int)($a["question_order"] ?? 0) <=> (int)($b["question_order"] ?? 0);
    });

    foreach ($rows as $qid => $q) {
        $q["question_id"] = (string)$qid;
        $q["choices"] = [];

        if (($q["question_type"] ?? "") === "multiple_choice") {

            $choice_rows = fb_rows("surveys/" . $survey_id . "/questions/" . $qid . "/choices");
            uasort($choice_rows, function ($a, $b) {
                return (int)($a["choice_order"] ?? 0) <=> (int)($b["choice_order"] ?? 0);
            });

            foreach ($choice_rows as $choice) {
                $q["choices"][] = $choice["choice_text"] ?? "";
            }
        }

        $questions[] = $q;
    }
});

$type_labels = [
    "multiple_choice" => "Multiple Choice",
    "yes_no" => "Yes / No",
    "rating" => "Rating Scale",
    "short_answer" => "Short Answer"
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Question Management</title>
<link rel="stylesheet" href="../assets/css/style.css">
<script>(function(){var t=localStorage.getItem("theme");if(t==="dark")document.documentElement.setAttribute("data-theme","dark");})();</script>
<script>(function(){try{if(localStorage.getItem("sidebarCollapsed")==="true")document.documentElement.setAttribute("data-sidebar","collapsed");}catch(e){}})();</script>
</head>
<body>
<?php include __DIR__ . "/../includes/staff_nav.php"; ?>
<div class="container">
<?php include __DIR__ . "/../includes/staff_topbar.php"; ?>
    <div class="card">
        <h2>Questions for: <?= e($survey["title"]) ?></h2>

        <?php if (isset($_GET["saved"])): ?>
            <div class="success" style="margin-bottom:16px; color:#1fae82;">Questions saved successfully.</div>
        <?php endif; ?>
        <?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>

        <p style="margin-bottom:16px; font-size:13px; color:#667;">This survey has no responses yet, so its questions are unlocked: edit the text, type, or choices below, add new questions, or remove ones you no longer need. Once someone submits a response, questions will lock automatically to keep results consistent.</p>

        <form method="POST" action="question_management.php?survey_id=<?= $survey_id ?>">
            <input type="hidden" name="survey_id" value="<?= $survey_id ?>">
            <div id="questions-wrapper"></div>
            <button type="button" class="btn btn-secondary" onclick="addQuestion()">+ Add Question</button>
            <br><br>
            <button type="submit">Save Questions</button>
            <button type="button" class="btn btn-secondary" onclick="openConfirmModal({url: 'survey_management.php', title: 'Discard these changes?', message: 'Any edits you made to these questions will be lost.', confirmLabel: 'Discard', danger: true})">Cancel</button>
        </form>
    </div>
</div>

<template id="question-template">
        <div class="card question-block">
        <input type="hidden" class="q-id-input" value="">

        <label>Question Text</label>
        <input type="text" class="q-text-input" required>

        <label>Question Type</label>
        <select class="q-type-select">
            <option value="multiple_choice">Multiple Choice</option>
            <option value="yes_no">Yes / No</option>
            <option value="rating">Rating Scale (1-5)</option>
            <option value="short_answer">Short Answer</option>
        </select>

        <label><input type="checkbox" class="q-required-check" style="width:auto;" checked> Required</label>

        <div class="q-choices-wrapper" style="margin-top:10px;">
            <label>Choices (multiple choice only)</label>
            <div class="choice-inputs">
                <input type="text" class="choice-input" placeholder="Choice 1">
                <input type="text" class="choice-input" style="margin-top:6px;" placeholder="Choice 2">
            </div>
            <button type="button" class="btn btn-secondary add-choice-btn" style="padding:6px 10px; font-size:12px;">+ Add Choice</button>
        </div>

        <button type="button" class="btn btn-danger remove-question-btn" style="padding:6px 10px; font-size:12px;">Remove Question</button>
    </div>
</template>

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
let questionIndex = 0;

function addQuestion(existing) {
    const template = document.getElementById("question-template");
    const clone = template.content.cloneNode(true);
    const block = clone.querySelector(".question-block");
    const index = questionIndex++;

    const idInput = block.querySelector(".q-id-input");
    idInput.name = "question_id[" + index + "]";

    const textInput = block.querySelector(".q-text-input");
    textInput.name = "question_text[" + index + "]";

    const typeSelect = block.querySelector(".q-type-select");
    typeSelect.name = "question_type[" + index + "]";

    const requiredCheck = block.querySelector(".q-required-check");
    requiredCheck.name = "question_required[" + index + "]";

    if (existing) {
        idInput.value = existing.id;
        textInput.value = existing.text;
        typeSelect.value = existing.type;
        requiredCheck.checked = !!existing.required;
    }

    const choicesWrapper = block.querySelector(".q-choices-wrapper");
    const choiceInputsContainer = block.querySelector(".choice-inputs");

    function nameChoiceInputs() {
        choiceInputsContainer.querySelectorAll(".choice-input").forEach(input => {
            input.name = "choices[" + index + "][]";
        });
    }

    if (existing && existing.type === "multiple_choice" && existing.choices && existing.choices.length > 0) {
        choiceInputsContainer.innerHTML = "";
        existing.choices.forEach((choiceText, i) => {
            const input = document.createElement("input");
            input.type = "text";
            input.className = "choice-input";
            if (i > 0) input.style.marginTop = "6px";
            input.placeholder = "Choice " + (i + 1);
            input.value = choiceText;
            choiceInputsContainer.appendChild(input);
        });
    }
    nameChoiceInputs();

    choicesWrapper.style.display = typeSelect.value === "multiple_choice" ? "block" : "none";
    typeSelect.addEventListener("change", () => {
        choicesWrapper.style.display = typeSelect.value === "multiple_choice" ? "block" : "none";
    });

    block.querySelector(".add-choice-btn").addEventListener("click", () => {
        const count = choiceInputsContainer.querySelectorAll(".choice-input").length;
        const input = document.createElement("input");
        input.type = "text";
        input.className = "choice-input";
        input.style.marginTop = "6px";
        input.placeholder = "Choice " + (count + 1);
        input.name = "choices[" + index + "][]";
        choiceInputsContainer.appendChild(input);
    });

    block.querySelector(".remove-question-btn").addEventListener("click", () => {
        block.remove();
    });

    document.getElementById("questions-wrapper").appendChild(clone);
}

// Pre-fill the form with the survey's current questions so they can be edited in place.
const existingQuestions = <?= json_encode(array_map(function ($q) {
    return [
        "id" => (int)$q["question_id"],
        "text" => $q["question_text"],
        "type" => $q["question_type"],
        "required" => (int)$q["is_required"],
        "choices" => $q["choices"]
    ];
}, $questions), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

if (existingQuestions.length > 0) {
    existingQuestions.forEach(q => addQuestion(q));
} else {
    addQuestion();
}

// Confirm before actually saving the questions
const questionsForm = document.querySelector("form");
questionsForm.addEventListener("submit", function (e) {
    e.preventDefault();
        openConfirmModal({
        title: "Save these questions?",
        message: "This will update the questions residents see for this survey.",
        confirmLabel: "Save Questions",
        danger: false,
        confirmClass: "",
        onConfirm: () => questionsForm.submit()
    });
});
</script>
</body>
</html>