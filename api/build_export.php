<?php
// Builds firebase/barangay_survey_export.json, the file you import into the
// Firebase Realtime Database to get the same starting data the SQL used to
// create: one staff account, five residents, and one survey with its four
// questions and eleven choices.
//
// Usage:
//   php api/build_export.php            rebuild the JSON file
//   php api/build_export.php --push     rebuild it, then publish to Firebase
//   php api/build_export.php --verify   rebuild it and check it against the SQL
//
// The seed data below is the same data as the INSERT statements in
// database/barangay_survey.sql, which is kept as the reference for what the
// database should contain. `--verify` re-reads that SQL and compares it row
// for row, so the two cannot drift apart unnoticed.
//
// Why the shape is not the SQL's shape: Firebase has no joins, so everything
// that used to be looked up through a foreign key is nested under its
// parent. residents hold their own children, surveys hold their questions,
// questions hold their choices, and a response holds its answers. That is
// what removes the joins from the PHP.

require_once __DIR__ . "/../includes/firebase.php";

// Timestamps use "Y-m-d H:i:s", the same as a MySQL TIMESTAMP, because the
// dashboard compares them as strings against month boundaries.
$seed_staff_created    = "2026-05-01 08:00:00";
$seed_resident_created = array(
    "2026-05-12 09:15:00",
    "2026-06-03 10:40:00",
    "2026-07-19 14:05:00",
    "2026-08-24 11:20:00",
    "2026-09-28 16:45:00",
);
$seed_survey_created = "2026-06-15 09:00:00";

// The ten columns residents were registered with in the SQL, plus mobile_pin.
// Passwords are stored in plain text: the web password is the same value the
// web portal set at registration time, and is_first_login 1 forces a change on
// first login.
$seed_staff = array(
    array(
        "username"   => "admin",
        "full_name"  => "Barangay Health Staff",
        "email"      => "staff@example.com",
        "password"   => 'admin123',
        "role"       => "admin",
        "created_at" => $seed_staff_created,
    ),
);

$seed_residents = array(
    array(
        "resident_number" => "2026-0001", "first_name" => "Justin Lian", "last_name" => "Enriquez",
        "email" => "lianjustin91@gmail.com", "contact_number" => "09752509652",
        "address" => "Sulucan, Bocaue, Bulacan", "gender" => "Male",
        "password" => '111111',
        "is_first_login" => 1, "mobile_pin" => "2026-0001",
    ),
    array(
        "resident_number" => "2026-0002", "first_name" => "Cielo Marie", "last_name" => "Estolloso",
        "email" => "cieloestolloso09@gmail.com", "contact_number" => "09763008362",
        "address" => "Iba-Ibayo, Hagonoy, Bulacan", "gender" => "Female",
        "password" => '222222',
        "is_first_login" => 1, "mobile_pin" => "2026-0002",
    ),
    array(
        "resident_number" => "2026-0003", "first_name" => "Mary Pauleen", "last_name" => "Salvador",
        "email" => "pauleensalvador@gmail.com", "contact_number" => "09690640080",
        "address" => "Malolos, Bulacan", "gender" => "Female",
        "password" => '333333',
        "is_first_login" => 1, "mobile_pin" => "2026-0003",
    ),
    array(
        "resident_number" => "2026-0004", "first_name" => "Kylie Denise", "last_name" => "Marasigan",
        "email" => "kyliedenise12@gmail.com", "contact_number" => "09235476895",
        "address" => "San Isidro 1, Paombong, Bulacan", "gender" => "Female",
        "password" => '444444',
        "is_first_login" => 1, "mobile_pin" => "2026-0004",
    ),
    array(
        "resident_number" => "2026-0005", "first_name" => "Aaron Gabriel", "last_name" => "Ranes",
        "email" => "aarongabrielranes@gmail.com", "contact_number" => "09690924629",
        "address" => "Malis, Guiguinto, Bulacan", "gender" => "Male",
        "password" => '555555',
        "is_first_login" => 1, "mobile_pin" => "2026-0005",
    ),
);

// Every other residents column, at the state the SQL leaves it in: NULL,
// which Firebase cannot store, so it becomes an empty string.
$empty_resident_fields = array(
    "middle_name" => "", "extension_name" => "", "civil_status" => "", "birthday" => "",
    "age" => "", "occupation" => "", "employer" => "", "employer_address" => "",
    "father_name" => "", "mother_name" => "", "spouse_name" => "", "spouse_occupation" => "",
    "spouse_employer" => "", "reference1_name" => "", "reference1_signature" => "",
    "reference2_name" => "", "reference2_signature" => "", "photo" => "",
);

$seed_survey = array(
    "title"       => "Barangay Health Services Feedback",
    "description" => "Help us improve our health services by answering this short survey.",
    "created_by"  => 1,
    "start_date"  => "2026-07-01",
    "end_date"    => "2026-12-31",
    "status"      => "active",
    "created_at"  => $seed_survey_created,
);

$seed_questions = array(
    array(
        "question_text" => "How satisfied are you with the health center services?",
        "question_type" => "rating", "is_required" => 1, "question_order" => 1,
        "choices" => array("1 - Very Dissatisfied", "2 - Dissatisfied", "3 - Neutral",
                           "4 - Satisfied", "5 - Very Satisfied"),
    ),
    array(
        "question_text" => "Have you availed of a free check-up in the past 6 months?",
        "question_type" => "yes_no", "is_required" => 1, "question_order" => 2,
        "choices" => array("Yes", "No"),
    ),
    array(
        "question_text" => "Which service do you use most often?",
        "question_type" => "multiple_choice", "is_required" => 1, "question_order" => 3,
        "choices" => array("Consultation", "Vaccination", "Dental Checkup", "Maternal Care"),
    ),
    array(
        "question_text" => "Any suggestions to improve our services?",
        "question_type" => "short_answer", "is_required" => 0, "question_order" => 4,
        "choices" => array(),
    ),
);

// ---- Assemble the payload ----

function build_payload() {
    global $seed_staff, $seed_residents, $seed_survey, $seed_questions,
           $empty_resident_fields, $seed_resident_created;

    $staff = array();
    foreach ($seed_staff as $i => $row) {
        $staff[(string)($i + 1)] = $row;
    }

    $residents   = array();
    $lookup_rn   = array();
    foreach ($seed_residents as $i => $row) {
        $id = (string)($i + 1);
        $resident = array_merge(
            array("first_name" => "", "last_name" => "", "middle_name" => ""),
            $empty_resident_fields,
            array("status" => "active")
        );
        // Merge in a fixed order: identity, contact, personal, household,
        // references, credentials, then the children and timestamps.
        $resident = array_merge(
            array(
                "resident_number" => "", "first_name" => "", "last_name" => "",
                "middle_name" => "", "extension_name" => "",
            ),
            array("gender" => "", "civil_status" => ""),
            array(
                "email" => "", "contact_number" => "", "address" => "",
                "birthday" => "", "age" => "",
            ),
            array(
                "occupation" => "", "employer" => "", "employer_address" => "",
                "father_name" => "", "mother_name" => "",
            ),
            array(
                "spouse_name" => "", "spouse_occupation" => "", "spouse_employer" => "",
            ),
            array(
                "reference1_name" => "", "reference1_signature" => "",
                "reference2_name" => "", "reference2_signature" => "",
            ),
            array("photo" => "", "status" => "active"),
            array(
                "password" => "", "is_first_login" => 1, "mobile_pin" => "",
            ),
            array(
                "created_at" => $seed_resident_created[$i], "updated_at" => $seed_resident_created[$i],
                "children"   => array(),
            ),
            $row
        );
        $residents[$id] = $resident;
        $lookup_rn[$row["resident_number"]] = $id;
    }

    $questions = array();
    foreach ($seed_questions as $i => $q) {
        $choices = array();
        foreach ($q["choices"] as $j => $text) {
            $choices[(string)($j + 1)] = array(
                "choice_text"  => $text,
                "choice_order" => $j + 1,
            );
        }
        $questions[(string)($i + 1)] = array(
            "question_text"  => $q["question_text"],
            "question_type"  => $q["question_type"],
            "is_required"    => $q["is_required"],
            "question_order" => $q["question_order"],
            "choices"        => $choices,
        );
    }

    $survey = array_merge(
        array("title" => "", "description" => "", "created_by" => "",
              "start_date" => "", "end_date" => "", "status" => "active", "created_at" => ""),
        $seed_survey,
        array("questions" => $questions)
    );

    $lookup_username = array();
    foreach ($seed_staff as $i => $row) {
        $lookup_username[$row["username"]] = (string)($i + 1);
    }

    return array(
        "meta" => array(
            "schema_version"  => 1,
            "generated_by"    => "api/build_export.php",
            "source_of_truth" => "database/barangay_survey.sql",
            "database"        => trim($GLOBALS["firebase_database_url"], "/"),
            "notes"           => "Import this file at the ROOT of the Realtime Database. "
                               . "Nested children/questions/choices replace the old foreign keys, "
                               . "since Firebase has no joins. 'counters' stands in for MySQL's "
                               . "AUTO_INCREMENT and 'lookup' replaces the UNIQUE columns that "
                               . "logins read by key. Blank strings stand in for SQL NULL.",
        ),
        "staff"                    => $staff,
        "residents"                => $residents,
        "surveys"                  => array("1" => $survey),
        "responses"                => array(),
        "resident_update_history"  => array(),
        "login_history"            => array(),
        "counters"                 => array(
            "staff" => count($seed_staff),
            "residents" => count($seed_residents),
            "surveys" => 1,
            "resident_update_history" => 0,
            "login_history" => 0,
        ),
        "lookup" => array(
            "resident_number" => $lookup_rn,
            "staff_username"  => $lookup_username,
        ),
    );
}

// ---- Check the payload against the SQL ----
// Reads the INSERT statements back out of database/barangay_survey.sql and
// compares them to what was just built, so "the same as the SQL" is a
// checked claim rather than a hope.

// Splits a SQL expression list on the commas that sit outside quotes, so an
// address like 'Iba-Ibayo, Hagonoy, Bulacan' stays in one piece.
function sql_split_top_level($s) {
    $parts = array();
    $cur   = "";
    $in    = false;
    for ($i = 0, $n = strlen($s); $i < $n; $i++) {
        $c = $s[$i];
        if ($c === "'") {
            if ($in && isset($s[$i + 1]) && $s[$i + 1] === "'") {
                $cur .= "$c'";
                $i++;
                continue;
            }
            $in = !$in;
            $cur .= $c;
            continue;
        }
        if ($c === "," && !$in) {
            $parts[] = trim($cur);
            $cur = "";
            continue;
        }
        $cur .= $c;
    }
    if (trim($cur) !== "") {
        $parts[] = trim($cur);
    }
    return $parts;
}

// One SQL expression: a quoted string, a number, NULL, or a bare column name
// (which means the SQL filled it in by looking another row up, not from a
// literal).
function sql_literal($expr) {
    $e = trim($expr);
    if ($e === "") {
        return null;
    }
    if (strcasecmp($e, "NULL") === 0) {
        return "";
    }
    if ($e[0] === "'") {
        return str_replace("''", "'", substr($e, 1, -1));
    }
    if (preg_match('/^-?\d+(\.\d+)?$/', $e)) {
        return strpos($e, ".") !== false ? (float)$e : (int)$e;
    }
    return null;
}

// Returns the value list of an INSERT ... SELECT, stopping at the FROM or
// WHERE that belongs to the outer query. A plain search for either keyword
// is not enough: these statements end in "WHERE NOT EXISTS ( SELECT 1 FROM
// ... )", so the scan has to ignore anything inside quotes or parentheses.
// Some of them (staff, residents, surveys) have no FROM at all and go
// straight to WHERE, which is why both are checked.
function sql_select_list($body) {
    $body = ltrim($body);
    if (stripos($body, "SELECT") !== 0) {
        return "";
    }
    $body   = substr($body, 6);
    $in     = false;
    $depth  = 0;
    $n      = strlen($body);
    for ($i = 0; $i < $n; $i++) {
        $c = $body[$i];
        if ($c === "'") {
            if ($in && isset($body[$i + 1]) && $body[$i + 1] === "'") {
                $i++;
                continue;
            }
            $in = !$in;
            continue;
        }
        if ($in) {
            continue;
        }
        if ($c === "(") {
            $depth++;
            continue;
        }
        if ($c === ")") {
            $depth--;
            continue;
        }
        if ($depth !== 0 || strtoupper($c) !== "F" && strtoupper($c) !== "W") {
            continue;
        }
        foreach (array("FROM", "WHERE") as $keyword) {
            if (stripos(substr($body, $i, strlen($keyword)), $keyword) !== 0) {
                continue;
            }
            $before = $i === 0 ? " " : $body[$i - 1];
            $after  = $body[$i + strlen($keyword)] ?? " ";
            if (!ctype_alnum($before) && !ctype_alnum($after) && $after !== "_") {
                return substr($body, 0, $i);
            }
        }
    }
    return $body;
}

// Reads the INSERT statements back out of database/barangay_survey.sql.
// Each row carries the literal columns plus:
//   __lookup_col  the column the SQL filled in by lookup (survey_id,
//                 question_id), so the caller knows to skip it
//   __order       the question_order a survey_choices row belongs to
function sql_seed_rows($table) {
    $sql = file_get_contents(__DIR__ . "/../database/barangay_survey.sql");
    $rows = array();
    $pattern = '/INSERT INTO ' . preg_quote($table, '/') . ' \(([^)]*)\)(.*?);/s';
    if (!preg_match_all($pattern, $sql, $matches, PREG_SET_ORDER)) {
        return $rows;
    }
    foreach ($matches as $m) {
        $cols = preg_split('/\s*,\s*/', trim($m[1]));
        $body = preg_replace('/\s+/', ' ', $m[2]);
        $exprs = sql_split_top_level(sql_select_list($body));
        $row   = array();
        foreach ($exprs as $i => $expr) {
            $col  = isset($cols[$i]) ? $cols[$i] : "col" . ($i + 1);
            $val  = sql_literal($expr);
            if ($val === null) {
                $row["__lookup_col"] = $col;
                continue;
            }
            $row[$col] = $val;
        }
        if (preg_match('/WHERE\s+question_order\s*=\s*(\d+)/i', $body, $ord)) {
            $row["__order"] = (int)$ord[1];
        }
        $rows[] = $row;
    }
    return $rows;
}

// Compares only the columns that were literals in the SQL, skipping the ones
// resolved by lookup.
function compare_literal_columns($label, $sql_row, $export_row, array $skip = array()) {
    $problems = array();
    foreach ($sql_row as $col => $value) {
        if (strpos($col, "__") === 0 || in_array($col, $skip, true)) {
            continue;
        }
        if (!array_key_exists($col, $export_row)) {
            $problems[] = "$label.$col is in the SQL but not in the export";
        } elseif ((string)$export_row[$col] !== (string)$value) {
            $problems[] = "$label.$col: SQL '$value' vs export '" . $export_row[$col] . "'";
        }
    }
    return $problems;
}

function verify_against_sql($payload) {
    $problems = array();

    $sql_staff = sql_seed_rows("staff");
    if (count($sql_staff) !== count($payload["staff"])) {
        $problems[] = "staff: SQL has " . count($sql_staff) . " row(s), export has " . count($payload["staff"]);
    }
    foreach ($sql_staff as $i => $sql_row) {
        $id = (string)($i + 1);
        $problems = array_merge($problems,
            compare_literal_columns("staff.$id", $sql_row, $payload["staff"][$id]));
    }

    $sql_residents = sql_seed_rows("residents");
    if (count($sql_residents) !== count($payload["residents"])) {
        $problems[] = "residents: SQL has " . count($sql_residents)
                    . " row(s), export has " . count($payload["residents"]);
    }
    foreach ($sql_residents as $i => $sql_row) {
        $id = (string)($i + 1);
        $mine = $payload["residents"][$id];
        $problems = array_merge($problems,
            compare_literal_columns("residents.$id", $sql_row, $mine));
        if (($mine["mobile_pin"] ?? "") !== $sql_row["resident_number"]) {
            $problems[] = "residents.$id.mobile_pin should match the Resident Number";
        }
        if (($payload["lookup"]["resident_number"][$sql_row["resident_number"]] ?? "") !== $id) {
            $problems[] = "lookup.resident_number.{$sql_row['resident_number']} should point at $id";
        }
    }

    $sql_surveys = sql_seed_rows("surveys");
    if (count($sql_surveys) !== count($payload["surveys"])) {
        $problems[] = "surveys: SQL has " . count($sql_surveys)
                    . " row(s), export has " . count($payload["surveys"]);
    }
    foreach ($sql_surveys as $i => $sql_row) {
        $id = (string)($i + 1);
        $problems = array_merge($problems,
            compare_literal_columns("surveys.$id", $sql_row, $payload["surveys"][$id]));
    }

    // Questions are compared by order, because in the SQL they were attached
    // to the survey by its title.
    $export_questions = array();
    foreach ($payload["surveys"] as $survey) {
        foreach ($survey["questions"] as $qid => $q) {
            $q["id"] = $qid;
            $export_questions[(int)$q["question_order"]] = $q;
        }
    }
    $sql_questions = sql_seed_rows("survey_questions");
    if (count($sql_questions) !== count($export_questions)) {
        $problems[] = "survey_questions: SQL has " . count($sql_questions)
                    . " row(s), export has " . count($export_questions);
    }
    foreach ($sql_questions as $sql_row) {
        $order = (int)$sql_row["question_order"];
        if (!isset($export_questions[$order])) {
            $problems[] = "survey_questions: no question with question_order $order in the export";
            continue;
        }
        $problems = array_merge($problems,
            compare_literal_columns("survey_questions.$order", $sql_row, $export_questions[$order]));
    }

    // Choices in the SQL belong to the question with a matching
    // question_order, so they are grouped the same way before comparing.
    $export_choices = array();
    foreach ($payload["surveys"] as $survey) {
        foreach ($survey["questions"] as $qid => $q) {
            foreach ($q["choices"] as $cid => $c) {
                $export_choices[(int)$q["question_order"]][(int)$c["choice_order"]] = $c;
            }
        }
    }
    $sql_choices = sql_seed_rows("survey_choices");
    $sql_choice_count = count($sql_choices);
    foreach ($sql_choices as $sql_row) {
        $qorder = (int)($sql_row["__order"] ?? 0);
        $corder = (int)$sql_row["choice_order"];
        if (!isset($export_choices[$qorder][$corder])) {
            $problems[] = "survey_choices: no choice $corder under question_order $qorder in the export";
            continue;
        }
        $problems = array_merge($problems,
            compare_literal_columns("survey_choices.q$qorder.$corder", $sql_row,
                                    $export_choices[$qorder][$corder]));
    }
    $export_choice_count = 0;
    foreach ($export_choices as $group) {
        $export_choice_count += count($group);
    }
    if ($sql_choice_count !== $export_choice_count) {
        $problems[] = "survey_choices: SQL has $sql_choice_count row(s), export has $export_choice_count";
    }

    return $problems;
}

// json_encode writes an empty PHP array as [], but every array in the
// payload is a Firebase node rather than a list, and an empty node should be
// written as {} so the file matches what the database will hold.
function fb_empty_nodes_as_objects($value) {
    if (!is_array($value)) {
        return $value;
    }
    if ($value === array()) {
        return new stdClass();
    }
    $out = array();
    foreach ($value as $key => $item) {
        $out[$key] = fb_empty_nodes_as_objects($item);
    }
    return $out;
}

// ---- Write, verify, push ----

$payload = build_payload();
$out_dir  = __DIR__ . "/../firebase";
$out_file = $out_dir . "/barangay_survey_export.json";

if (!is_dir($out_dir)) {
    mkdir($out_dir, 0755, true);
}

$json = json_encode(fb_empty_nodes_as_objects($payload),
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($json === false) {
    fwrite(STDERR, "Could not encode the export: " . json_last_error_msg() . PHP_EOL);
    exit(1);
}
file_put_contents($out_file, $json . PHP_EOL);

$rows = count($payload["staff"]) + count($payload["residents"]) + count($payload["surveys"]);
echo "Wrote firebase/barangay_survey_export.json" . PHP_EOL;
echo "  " . count($payload["staff"]) . " staff, " . count($payload["residents"]) . " residents, "
   . count($payload["surveys"]) . " survey(s), "
   . count($payload["surveys"][1]["questions"]) . " questions, "
   . count($payload["surveys"][1]["questions"][1]["choices"]) + 2 + 4 . " choices" . PHP_EOL;
echo "  import it at the ROOT of " . $GLOBALS["firebase_database_url"] . PHP_EOL;

$verify = in_array("--verify", $argv, true);
$push   = in_array("--push", $argv, true);

if ($verify || !$push) {
    $problems = verify_against_sql($payload);
    if ($problems) {
        echo PHP_EOL . "Does NOT match database/barangay_survey.sql:" . PHP_EOL;
        foreach ($problems as $p) {
            echo "  - $p" . PHP_EOL;
        }
        exit(1);
    }
    echo "  checked against database/barangay_survey.sql: same rows, same values" . PHP_EOL;
}

if ($push) {
    // Publishes each top-level node. This overwrites whatever is there,
    // including anything the site has collected, so sync or export first if
    // that matters.
    echo PHP_EOL . "Pushing to Firebase..." . PHP_EOL;
    $nodes = array("staff", "residents", "surveys", "responses",
                   "resident_update_history", "login_history", "counters", "lookup", "meta");
    foreach ($nodes as $node) {
        fb_set($node, $payload[$node]);
        echo "  $node" . PHP_EOL;
    }
    echo "Done. " . ($rows ? "Database seeded." : "") . PHP_EOL;
}
