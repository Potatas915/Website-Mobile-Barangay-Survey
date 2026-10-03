<?php
/**
 * Import survey answers collected by the Android app into MySQL.
 *
 * The phone cannot reach MySQL, so it writes each submission to Firebase
 * under mobile_responses/{survey_id}/{resident_number}:
 *
 *   mobile_responses
 *   `- "1"
 *      `- "2026-0001"
 *         |- survey_id: "1"
 *         |- resident_number: "2026-0001"
 *         |- submitted_at: "Oct 2, 2026 5:04:11 PM"
 *         |- synced: false
 *         `- answers
 *            |- "1": "4"          <- choice_id for a rating question
 *            |- "2": "6"          <- choice_id for a yes/no question
 *            `- "4": "More nurses" <- free text for a short_answer question
 *
 * This script copies those into the `responses` and `survey_results` tables
 * so that staff/results.php and staff/reports.php count answers given on the
 * phone as well as on the web. Each submission is marked `synced: true`
 * afterwards, and re-running is safe: a submission that is already synced, or
 * that the resident already answered on the web, is skipped.
 *
 * Usage:
 *
 *   php api/sync_responses.php            show what would be imported
 *   php api/sync_responses.php --import   do the import
 *   php api/sync_responses.php --status   one line per submission
 *
 * Needs the Firebase token: set FIREBASE_DB_TOKEN or create
 * config/firebase.php returning the token string.
 */

require_once __DIR__ . "/../includes/functions.php";

const FIREBASE_DB_URL = "https://activity-7-d32c8-default-rtdb.firebaseio.com";
const FIREBASE_NODE = "mobile_responses";

/** Same lookup as export_to_firebase.php: env var, then config/firebase.php. */
function firebase_token() {
    $env = getenv("FIREBASE_DB_TOKEN");
    if ($env !== false && trim($env) !== "") {
        return trim($env);
    }

    $config = __DIR__ . "/../config/firebase.php";
    if (is_file($config)) {
        $token = include $config;
        if (is_string($token) && trim($token) !== "") {
            return trim($token);
        }
    }

    return "";
}

/** GETs a node from the Realtime Database and returns it decoded. */
function firebase_get($path, $token) {
    $url = FIREBASE_DB_URL . "/" . trim($path, "/") . ".json?auth=" . rawurlencode($token);

    if (function_exists("curl_init")) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
            CURLOPT_TIMEOUT => 30,
        ]);
        $reply = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error !== "") {
            return null;
        }
    } else {
        $context = stream_context_create(["http" => [
            "method" => "GET",
            "header" => "Content-Type: application/json\r\n",
            "timeout" => 30,
            "ignore_errors" => true,
        ]]);
        $reply = @file_get_contents($url, false, $context);
    }

    $data = json_decode((string)$reply, true);
    return is_array($data) ? $data : null;
}

/** PATCHes a value onto a node and returns true when Firebase accepted it. */
function firebase_patch($path, $value, $token) {
    $body = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $url = FIREBASE_DB_URL . "/" . trim($path, "/") . ".json?auth=" . rawurlencode($token);

    if (function_exists("curl_init")) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => "PATCH",
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
            CURLOPT_TIMEOUT => 30,
        ]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } else {
        $context = stream_context_create(["http" => [
            "method" => "PATCH",
            "header" => "Content-Type: application/json\r\n",
            "content" => $body,
            "timeout" => 30,
            "ignore_errors" => true,
        ]]);
        @file_get_contents($url, false, $context);
        $code = 0;
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match("#^HTTP/\S+\s+(\d+)#", $header, $m)) {
                $code = (int)$m[1];
            }
        }
    }

    return $code >= 200 && $code < 300;
}

/** Caches question metadata so each submission does not re-query it. */
function load_questions($conn) {
    $questions = [];
    $result = $conn->query("SELECT * FROM survey_questions ORDER BY survey_id, question_order");
    while ($q = $result->fetch_assoc()) {
        $questions[(int)$q["question_id"]] = $q;
    }

    $choices = [];
    $result = $conn->query("SELECT question_id, choice_id FROM survey_choices");
    while ($c = $result->fetch_assoc()) {
        $choices[(int)$c["question_id"]][(int)$c["choice_id"]] = true;
    }

    foreach ($questions as $id => $q) {
        $questions[$id]["choice_ids"] = $choices[$id] ?? [];
    }

    return $questions;
}

/**
 * Trims free text to the 255 characters the answer_text column allows.
 *
 * mbstring is not guaranteed to be enabled, so fall back to substr() and
 * then drop any trailing partial UTF-8 sequence rather than letting a
 * multi-byte character be cut in half.
 */
function truncate_answer($text, $limit = 255) {
    $text = (string)$text;

    if (function_exists("mb_substr")) {
        return mb_substr($text, 0, $limit);
    }

    if (strlen($text) <= $limit) {
        return $text;
    }

    $cut = substr($text, 0, $limit);

    // Walk back off any continuation bytes (10xxxxxx) so we never end in the
    // middle of a multi-byte character.
    while ($cut !== "" && (ord($cut[strlen($cut) - 1]) & 0xC0) === 0x80) {
        $cut = substr($cut, 0, -1);
    }

    return $cut;
}

/**
 * Turns one answer from the app into either a choice_id or free text.
 *
 * The question type decides, not the value: multiple_choice, yes_no and
 * rating all store a choice_id, short_answer stores text. A choice_id that
 * does not belong to the question is rejected rather than stored, so a
 * hand-crafted Firebase write cannot invent survey results.
 *
 * Returns [choice_id, answer_text] or null when the answer is unusable.
 */
function resolve_answer($question, $value) {
    if ($value === null) {
        return null;
    }

    $value = is_string($value) ? trim($value) : $value;

    if ($question["question_type"] === "short_answer") {
        if ($value === "" || $value === null) {
            return null;
        }
        return [null, truncate_answer($value)];
    }

    if ($value === "" || !is_numeric($value)) {
        return null;
    }

    $choice_id = (int)$value;
    if (!isset($question["choice_ids"][$choice_id])) {
        return null;
    }

    return [$choice_id, null];
}

// ---------------------------------------------------------------------------
// Entry point
// ---------------------------------------------------------------------------

$mode = (php_sapi_name() === "cli" && isset($argv[1])) ? $argv[1] : "--dry-run";
$doImport = ($mode === "--import");
$statusOnly = ($mode === "--status");

$token = firebase_token();
if ($token === "") {
    fwrite(STDERR, "No Firebase token. Set FIREBASE_DB_TOKEN or create config/firebase.php.\n");
    exit(1);
}

echo "Reading " . FIREBASE_NODE . " from Firebase...\n";
$submissions = firebase_get(FIREBASE_NODE, $token);

if (!$submissions) {
    echo "Nothing to import (no submissions, or the node is empty).\n";
    exit(0);
}

$questions = load_questions($conn);
$imported = 0;
$skipped = 0;

foreach ($submissions as $surveyKey => $byResident) {
    if (!is_array($byResident)) {
        continue;
    }

    $survey_id = (int)$surveyKey;
    $surveyStmt = $conn->prepare("SELECT title FROM surveys WHERE survey_id = ?");
    $surveyStmt->bind_param("i", $survey_id);
    $surveyStmt->execute();
    $survey = $surveyStmt->get_result()->fetch_assoc();

    if (!$survey) {
        echo "  survey {$survey_id}: no such survey, skipping\n";
        continue;
    }

    foreach ($byResident as $residentNumber => $submission) {
        $label = $survey["title"] . " / " . $residentNumber;

        if (!is_array($submission)) {
            continue;
        }

        if (!empty($submission["synced"])) {
            if ($statusOnly) {
                echo "  [synced]   {$label}\n";
            }
            $skipped++;
            continue;
        }

        // Resolve the resident from the Resident Number the app logged in with.
        $residentStmt = $conn->prepare(
            "SELECT resident_id, first_name, last_name, middle_name, status FROM residents WHERE resident_number = ?"
        );
        $residentStmt->bind_param("s", $residentNumber);
        $residentStmt->execute();
        $resident = $residentStmt->get_result()->fetch_assoc();

        if (!$resident) {
            echo "  [unknown]  {$label}: no such Resident Number in MySQL, skipping\n";
            $skipped++;
            continue;
        }

        if (($resident["status"] ?? "active") !== "active") {
            echo "  [archived] {$label}: resident is archived, skipping\n";
            $skipped++;
            continue;
        }

        // The app is only allowed to write answers to questions that exist.
        $answers = is_array($submission["answers"] ?? null) ? $submission["answers"] : [];
        $rows = [];
        foreach ($answers as $questionKey => $value) {
            $questionId = (int)$questionKey;

            // Guard against a submission that claims a question from another survey.
            if (!isset($questions[$questionId]) || (int)$questions[$questionId]["survey_id"] !== $survey_id) {
                continue;
            }

            $resolved = resolve_answer($questions[$questionId], $value);
            if ($resolved === null) {
                continue;
            }

            $rows[] = [
                "question_id" => $questionId,
                "choice_id" => $resolved[0],
                "answer_text" => $resolved[1],
            ];
        }

        if (count($rows) === 0) {
            echo "  [empty]    {$label}: no usable answers\n";
            $skipped++;
            continue;
        }

        if (!$doImport) {
            echo "  [ready]    {$label}: " . count($rows) . " answer(s) -> "
                . "responses + survey_results\n";
            $imported++;
            continue;
        }

        // One submission per resident per survey is enforced by a UNIQUE key,
        // so check first and let them answer on the phone only if they have
        // not already answered on the web.
        $exists = $conn->prepare("SELECT response_id FROM responses WHERE survey_id = ? AND resident_id = ?");
        $exists->bind_param("ii", $survey_id, $resident["resident_id"]);
        $exists->execute();

        if ($exists->get_result()->num_rows > 0) {
            echo "  [done]     {$label}: already answered on the web, marking synced\n";
            firebase_patch(
                FIREBASE_NODE . "/" . $surveyKey . "/" . rawurlencode((string)$residentNumber),
                ["synced" => true, "synced_at" => date("Y-m-d H:i:s")],
                $token
            );
            $skipped++;
            continue;
        }

        $conn->begin_transaction();
        try {
            // bind_param() takes its arguments by reference, so the name has
            // to live in a variable first. The name always comes from MySQL,
            // never from whatever the app happened to send.
            $residentName = full_resident_name($resident);

            $nameStmt = $conn->prepare("INSERT INTO responses (survey_id, resident_id, resident_name) VALUES (?, ?, ?)");
            $nameStmt->bind_param("iis", $survey_id, $resident["resident_id"], $residentName);
            $nameStmt->execute();
            $response_id = $conn->insert_id;

            $resultStmt = $conn->prepare("INSERT INTO survey_results (response_id, question_id, choice_id, answer_text) VALUES (?, ?, ?, ?)");
            foreach ($rows as $row) {
                $resultStmt->bind_param("iiis", $response_id, $row["question_id"], $row["choice_id"], $row["answer_text"]);
                $resultStmt->execute();
            }

            $conn->commit();

            firebase_patch(
                FIREBASE_NODE . "/" . $surveyKey . "/" . rawurlencode((string)$residentNumber),
                ["synced" => true, "synced_at" => date("Y-m-d H:i:s"), "response_id" => $response_id],
                $token
            );

            echo "  [imported] {$label}: " . count($rows) . " answer(s)\n";
            $imported++;
        } catch (Exception $e) {
            $conn->rollback();
            echo "  [failed]   {$label}: " . $e->getMessage() . "\n";
            $skipped++;
        }
    }
}

echo "\n" . ($doImport ? "Imported: " : "Ready to import: ") . $imported
    . ", skipped: " . $skipped . "\n";

if (!$doImport && $imported > 0) {
    echo "Nothing was changed. Re-run with --import to write these into MySQL.\n";
}
