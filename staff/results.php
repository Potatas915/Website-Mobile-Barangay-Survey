<?php
require_once __DIR__ . "/../includes/functions.php";
require_staff_login();

fb_page(function () use (&$survey_list) {

    $survey_list = array();
    foreach (fb_rows("surveys") as $survey) {
        $survey["survey_id"] = $survey["id"];
        $survey_list[] = $survey;
    }

    // The old query ended with ORDER BY created_at DESC.
    usort($survey_list, function ($a, $b) {
        return strcmp((string)$b["created_at"], (string)$a["created_at"]);
    });
});

$selected_id = isset($_GET["survey_id"]) ? (int)$_GET["survey_id"] : (count($survey_list) ? $survey_list[0]["survey_id"] : 0);
$selected_title = "Survey Results";
foreach ($survey_list as $survey) {
    if ((int)$survey["survey_id"] === $selected_id) {
        $selected_title = $survey["title"];
        break;
    }
}

$questions = [];
$response_count = 0;
$respondents = [];

if ($selected_id) {
    fb_page(function () use ($selected_id, &$questions, &$response_count, &$respondents) {

        // Every response for this survey, read once and reused for the count,
        // the vote tallies and the respondent list.
        $responses = array();
        foreach (fb_rows("responses") as $response) {
            if ((string)($response["survey_id"] ?? "") === (string)$selected_id) {
                $responses[] = $response;
            }
        }
        $response_count = count($responses);

        $questions = [];
        foreach (fb_rows("surveys/" . $selected_id . "/questions") as $qid => $question) {
            $question["question_id"] = (string)$qid;

            $votes = array();
            $answers = array();

            foreach ($responses as $response) {
                $answer = $response["answers"][$qid] ?? null;
                if (!is_array($answer)) {
                    continue;
                }

                $choice_id = (string)($answer["choice_id"] ?? "");
                if ($choice_id !== "") {
                    $votes[$choice_id] = ($votes[$choice_id] ?? 0) + 1;
                }

                $answer_text = (string)($answer["answer_text"] ?? "");
                if ($answer_text !== "") {
                    $answers[] = array(
                        "answer_text"  => $answer_text,
                        "resident_name" => $response["resident_name"] ?? "",
                    );
                }
            }

            if (($question["question_type"] ?? "") === "short_answer") {
                // The old query kept rows where answer_text IS NOT NULL, which
                // is the same as keeping the non-empty ones.
                $question["answers"] = $answers;
            } else {

                // LEFT JOIN survey_choices, so every choice is listed even
                // when nobody picked it.
                $choice_stats = array();
                foreach (fb_rows("surveys/" . $selected_id . "/questions/" . $qid . "/choices") as $cid => $choice) {
                    $choice_stats[] = array(
                        "choice_text"   => $choice["choice_text"] ?? "",
                        "choice_order"  => $choice["choice_order"] ?? 0,
                        "votes"         => $votes[(string)$cid] ?? 0,
                    );
                }

                usort($choice_stats, function ($a, $b) {
                    return (int)$a["choice_order"] <=> (int)$b["choice_order"];
                });

                $question["choice_stats"] = $choice_stats;
                $question["total_votes"] = array_sum(array_column($choice_stats, "votes"));
            }

            $questions[] = $question;
        }

        // ORDER BY question_order
        usort($questions, function ($a, $b) {
            return (int)$a["question_order"] <=> (int)$b["question_order"];
        });

        // The resident_number and email columns came from a JOIN on
        // residents, so they are looked up here.
        $respondents = array();
        foreach ($responses as $response) {
            $resident_id = (string)($response["resident_id"] ?? "");
            $resident = $resident_id === "" ? null : fb_get("residents/" . $resident_id);

            $respondents[] = array(
                "response_id"     => $response["id"],
                "resident_id"     => $resident_id,
                "resident_name"   => $response["resident_name"] ?? "",
                "resident_number" => is_array($resident) ? ($resident["resident_number"] ?? "") : "",
                "email"           => is_array($resident) ? ($resident["email"] ?? "") : "",
                "submitted_at"    => $response["submitted_at"] ?? "",
            );
        }

        // The old query ended with ORDER BY submitted_at DESC.
        usort($respondents, function ($a, $b) {
            return strcmp((string)$b["submitted_at"], (string)$a["submitted_at"]);
        });
    });
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Survey Results</title>
<link rel="stylesheet" href="../assets/css/style.css">
<script>(function(){var t=localStorage.getItem("theme");if(t==="dark")document.documentElement.setAttribute("data-theme","dark");})();</script>
<script>(function(){try{if(localStorage.getItem("sidebarCollapsed")==="true")document.documentElement.setAttribute("data-sidebar","collapsed");}catch(e){}})();</script>
</head>
<body>
<?php $no_print_nav = true; include __DIR__ . "/../includes/staff_nav.php"; ?>
<div class="container">
<?php include __DIR__ . "/../includes/staff_topbar.php"; ?>
    <div class="card no-print">
        <div class="results-selector-row">
            <div class="selected-survey-title-wrap">
                <span class="selected-survey-label">Survey</span>
                <h2 class="selected-survey-title"><?= e($selected_title) ?></h2>
            </div>

            <form method="GET" class="survey-selector-form">
                <label for="survey_id">Select Survey</label>
                <select id="survey_id" name="survey_id" onchange="this.form.submit()">
                    <?php foreach ($survey_list as $s): ?>
                        <option value="<?= $s["survey_id"] ?>" <?= $s["survey_id"] == $selected_id ? "selected" : "" ?>>
                            <?= e($s["title"]) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <?php if ($selected_id): ?>
                <button type="button" class="btn btn-secondary respondents-btn" onclick="document.getElementById('respondentsModal').classList.add('open')">
                    View Respondents
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($selected_id): ?>
    <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h2>Results (<?= $response_count ?> responses)</h2>
            <button class="no-print" onclick="window.print()">Print Report</button>
        </div>

        <?php if ($response_count === 0): ?>
            <p class="muted-note" style="margin-top:6px;">This survey hasn't received any responses yet. Once residents start answering, results for each question will appear here automatically.</p>
        <?php else: ?>
        <?php foreach ($questions as $q): ?>
            <div class="card">
                <strong><?= e($q["question_text"]) ?></strong>

                <?php if ($q["question_type"] === "short_answer"): ?>
                    <?php if (count($q["answers"]) === 0): ?>
                        <p style="font-size:13px; color:#667;">No answers yet.</p>
                    <?php else: ?>
                        <ul style="margin-top:10px; padding-left:18px; font-size:14px;">
                            <?php foreach ($q["answers"] as $a): ?>
                                <li><?= e($a["answer_text"]) ?> <span style="color:#667; font-size:12px;">- <?= e($a["resident_name"]) ?></span></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                <?php else: ?>
                    <?php if ($q["total_votes"] === 0): ?>
                        <p style="font-size:13px; color:#667;">No responses yet for this question.</p>
                    <?php else: ?>
                    <div class="bar-chart">
                        <?php foreach ($q["choice_stats"] as $c):
                            $pct = $q["total_votes"] > 0 ? round(($c["votes"] / $q["total_votes"]) * 100) : 0;
                        ?>
                        <div class="bar-row">
                            <div style="width:160px;"><?= e($c["choice_text"]) ?></div>
                            <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%;"></div></div>
                            <div style="width:70px;"><?= $c["votes"] ?> (<?= $pct ?>%)</div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php else: ?>
        <div class="card"><p>No surveys yet.</p></div>
    <?php endif; ?>
</div>

<?php if ($selected_id): ?>
<div class="modal-overlay no-print" id="respondentsModal">
    <div class="modal-box">
        <span class="modal-close" onclick="document.getElementById('respondentsModal').classList.remove('open')">&times;</span>
        <h3>Respondents (<?= count($respondents) ?>)</h3>
        <?php if (count($respondents) === 0): ?>
            <p style="font-size:13px; color:#667;">No one has answered this survey yet.</p>
        <?php else: ?>
            <table>
                <tr><th>Name</th><th>Resident ID</th><th>Submitted</th></tr>
                <?php foreach ($respondents as $r): ?>
                <tr>
                    <td><?= e($r["resident_name"]) ?></td>
                    <td><?= e($r["resident_number"]) ?></td>
                    <td><?= e($r["submitted_at"]) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
<script src="../assets/js/script.js"></script>
</body>
</html>