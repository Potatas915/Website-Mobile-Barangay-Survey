<?php

require_once __DIR__ . "/../includes/functions.php";

require_staff_login();


/*
|--------------------------------------------------------------------------
| Get Resident ID
|--------------------------------------------------------------------------
*/

$resident_id =
    filter_input(
        INPUT_GET,
        "resident_id",
        FILTER_VALIDATE_INT
    );


if (
    $resident_id === false ||
    $resident_id === null ||
    $resident_id <= 0
) {

    redirect(
        "resident_management.php"
    );

}


/*
|--------------------------------------------------------------------------
| Load Resident
|--------------------------------------------------------------------------
*/

// One row out of the residents collection, by key.
$resident =
    fb_page(function () use ($resident_id) {
        return fb_get("residents/" . $resident_id);
    });


if (
    !is_array(
        $resident
    )
) {

    redirect(
        "resident_management.php"
    );

}


// The row carries its key as "id", and the old column name is what the
// templates below still use.
$resident["id"] = $resident_id;

$resident["resident_id"] = $resident_id;


/*
|--------------------------------------------------------------------------
| Resident Display Name
|--------------------------------------------------------------------------
*/

$display_name =
    full_resident_name(
        $resident
    );


/*
|--------------------------------------------------------------------------
| Resident Photo
|--------------------------------------------------------------------------
*/

$photo_url =
    resident_photo_url(
        $resident["photo"] ?? ""
    );


$initials =
    strtoupper(
        substr(
            $resident["first_name"] ?? "",
            0,
            1
        ) .
        substr(
            $resident["last_name"] ?? "",
            0,
            1
        )
    );


if (
    $initials === ""
) {

    $initials =
        "R";

}


/*
|--------------------------------------------------------------------------
| Parents
|--------------------------------------------------------------------------
|
| Parents are stored directly in the residents table.
|
|--------------------------------------------------------------------------
*/

$parents = [

    "father_name" =>
        $resident["father_name"] ?? "",

    "mother_name" =>
        $resident["mother_name"] ?? ""

];


/*
|--------------------------------------------------------------------------
| Spouse
|--------------------------------------------------------------------------
|
| Spouse information is stored directly in the residents table.
|
|--------------------------------------------------------------------------
*/

$spouse = [

    "spouse_name" =>
        $resident["spouse_name"] ?? "",

    "occupation" =>
        $resident["spouse_occupation"] ?? "",

    "employer" =>
        $resident["spouse_employer"] ?? ""

];


/*
|--------------------------------------------------------------------------
| Load Children
|--------------------------------------------------------------------------
*/

$children = [];


// Children live in a nested node of the resident, and the keys are already
// numeric in order, which is what ORDER BY child_id ASC did.
foreach (
    fb_rows(
        "residents/" . $resident_id . "/children"
    )
    as $child_id =>
    $child
) {

    $child["child_id"] = (string) $child_id;

    $children[] = $child;

}


/*
|--------------------------------------------------------------------------
| Character References
|--------------------------------------------------------------------------
|
| Character references are stored directly in residents.
|
|--------------------------------------------------------------------------
*/

$references = [

    [

        "reference_name" =>
            $resident["reference1_name"] ?? "",

        "signature" =>
            $resident["reference1_signature"] ?? "",

        "reference_order" =>
            1

    ],

    [

        "reference_name" =>
            $resident["reference2_name"] ?? "",

        "signature" =>
            $resident["reference2_signature"] ?? "",

        "reference_order" =>
            2

    ]

];


/*
|--------------------------------------------------------------------------
| Remove Completely Empty References
|--------------------------------------------------------------------------
*/

$references = array_values(
    array_filter(
        $references,
        function ($reference) {

            return
                trim(
                    (string) (
                        $reference["reference_name"] ?? ""
                    )
                ) !== ""
                ||
                trim(
                    (string) (
                        $reference["signature"] ?? ""
                    )
                ) !== "";

        }
    )
);


/*
|--------------------------------------------------------------------------
| Load Update History
|--------------------------------------------------------------------------
*/

$history = [];


foreach (
    fb_rows(
        "resident_update_history"
    )
    as $history_id =>
    $history_row
) {

    if (
        (string) ($history_row["resident_id"] ?? "")
        !==
        (string) $resident_id
    ) {

        continue;

    }

    $history_row["history_id"] = (string) $history_id;

    $history[] = $history_row;

}


// ORDER BY updated_at DESC, history_id DESC
usort(
    $history,
    function ($a, $b) {

        $by_time =
            strcmp(
                (string) ($b["updated_at"] ?? ""),
                (string) ($a["updated_at"] ?? "")
            );

        return
            $by_time !== 0
                ? $by_time
                : (int) ($b["history_id"] ?? 0) <=> (int) ($a["history_id"] ?? 0);

    }
);


/*
|--------------------------------------------------------------------------
| Load Staff Names Used By History
|--------------------------------------------------------------------------
*/

$staff_names = [];


$staff_rows =
    fb_rows("staff");


foreach (
    $history
    as $history_row
) {

    if (
        ($history_row["updated_by_type"] ?? "")
        !==
        "staff"
    ) {

        continue;

    }


    $staff_id =
        (int) (
            $history_row["updated_by_id"] ?? 0
        );


    if (
        $staff_id <= 0 ||
        isset(
            $staff_names[$staff_id]
        )
    ) {

        continue;

    }


    // The staff collection is read once for the whole page and then matched
    // in memory.
    $staff_row =
        $staff_rows[(string) $staff_id] ?? null;


    if (
        $staff_row
    ) {

        $staff_names[
            $staff_id
        ] =
            !empty(
                $staff_row["full_name"]
            )
                ? $staff_row["full_name"]
                : (
                    $staff_row["username"]
                    ?? "Staff"
                );

    }
    else {

        $staff_names[
            $staff_id
        ] =
            "Staff";

    }

}


/*
|--------------------------------------------------------------------------
| History Helpers
|--------------------------------------------------------------------------
*/

function resident_history_escape(
    $value
) {

    return e(
        $value === null
            ? ""
            : (string) $value
    );

}


/*
|--------------------------------------------------------------------------
| Human-Readable History Field Names
|--------------------------------------------------------------------------
*/

function resident_history_field_label(
    $key
) {

    $labels = [

        "resident_number" =>
            "Resident Number",

        "first_name" =>
            "First Name",

        "middle_name" =>
            "Middle Name",

        "last_name" =>
            "Last Name",

        "extension_name" =>
            "Extension Name",

        "gender" =>
            "Gender",

        "civil_status" =>
            "Civil Status",

        "birthday" =>
            "Birthday",

        "age" =>
            "Age",

        "occupation" =>
            "Occupation",

        "employer" =>
            "Employer",

        "employer_address" =>
            "Employer Address",

        "email" =>
            "Email",

        "contact_number" =>
            "Contact Number",

        "address" =>
            "Address",

        "father_name" =>
            "Father's Name",

        "mother_name" =>
            "Mother's Name",

        "spouse_name" =>
            "Spouse Name",

        "spouse_occupation" =>
            "Spouse Occupation",

        "spouse_employer" =>
            "Spouse Employer",

        "child_name" =>
            "Child Name",

        "child_age" =>
            "Child Age",

        "reference_name" =>
            "Reference Name",

        "reference_order" =>
            "Reference Number",

        "reference_contact" =>
            "Reference Contact",

        "reference_address" =>
            "Reference Address",

        "contact" =>
            "Contact",

        "phone" =>
            "Phone",

        "signature" =>
            "Signature",

        "photo" =>
            "Photo",

        "password" =>
            "Password"

    ];


    if (
        isset(
            $labels[$key]
        )
    ) {

        return $labels[$key];

    }


    return ucwords(
        str_replace(
            "_",
            " ",
            (string) $key
        )
    );

}


/*
|--------------------------------------------------------------------------
| History Section Names
|--------------------------------------------------------------------------
*/

function resident_history_section_label(
    $section
) {

    $labels = [

        "complete_profile" =>
            "Complete Profile",

        "personal_information" =>
            "Personal Information",

        "spouse" =>
            "Spouse Information",

        "children" =>
            "Children Information",

        "parents" =>
            "Parents Information",

        "references" =>
            "Character References",

        "photo" =>
            "Passport-Size Photo",

        "password" =>
            "Password"

    ];


    if (
        isset(
            $labels[$section]
        )
    ) {

        return $labels[$section];

    }


    return resident_history_field_label(
        $section
    );

}


/*
|--------------------------------------------------------------------------
| Decode History JSON
|--------------------------------------------------------------------------
*/

function resident_history_decode(
    $data
) {

    if (
        $data === null ||
        trim(
            (string) $data
        ) === ""
    ) {

        return null;

    }


    $decoded =
        json_decode(
            $data,
            true
        );


    if (
        json_last_error() !==
        JSON_ERROR_NONE
    ) {

        return null;

    }


    return $decoded;

}


/*
|--------------------------------------------------------------------------
| Detect Password Fields
|--------------------------------------------------------------------------
*/

function resident_history_is_password_key(
    $key
) {

    $key_lower =
        strtolower(
            (string) $key
        );


    return (
        $key_lower === "password" ||
        strpos(
            $key_lower,
            "password"
        ) !== false
    );

}


/*
|--------------------------------------------------------------------------
| Render Scalar History Value
|--------------------------------------------------------------------------
*/

function resident_history_scalar(
    $value,
    $key = ""
) {

    if (
        resident_history_is_password_key(
            $key
        )
    ) {

        return "Password was changed";

    }


    if (
        $value === null ||
        $value === ""
    ) {

        return "Not provided";

    }


    if (
        is_bool($value)
    ) {

        return $value
            ? "Yes"
            : "No";

    }


    return resident_history_escape(
        $value
    );

}


/*
|--------------------------------------------------------------------------
| Render History Value
|--------------------------------------------------------------------------
*/

function resident_history_render_value(
    $value,
    $parent_key = ""
) {

    /*
    |--------------------------------------------------------------------------
    | Scalar
    |--------------------------------------------------------------------------
    */

    if (
        !is_array($value)
    ) {

        return resident_history_scalar(
            $value,
            $parent_key
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Empty Array
    |--------------------------------------------------------------------------
    */

    if (
        count($value) === 0
    ) {

        return "Not provided";

    }


    /*
    |--------------------------------------------------------------------------
    | Numeric Array
    |--------------------------------------------------------------------------
    */

    if (
        array_keys($value) ===
        range(
            0,
            count($value) - 1
        )
    ) {

        $html =
            '<div class="history-list">';


        $number =
            1;


        foreach (
            $value
            as $item
        ) {

            $html .=
                '<div class="history-list-item">';


            $html .=
                '<div class="history-list-number">' .
                $number .
                '</div>';


            $html .=
                '<div class="history-list-content">';


            if (
                is_array($item)
            ) {

                foreach (
                    $item
                    as $key =>
                    $child_value
                ) {

                    if (
                        resident_history_is_password_key(
                            $key
                        )
                    ) {

                        $child_value =
                            "Password was changed";

                    }


                    $html .=
                        '<div class="history-field-row">';


                    $html .=
                        '<div class="history-field-label">' .
                        resident_history_escape(
                            resident_history_field_label(
                                $key
                            )
                        ) .
                        '</div>';


                    $html .=
                        '<div class="history-field-value">' .
                        resident_history_render_value(
                            $child_value,
                            $key
                        ) .
                        '</div>';


                    $html .=
                        '</div>';

                }

            }
            else {

                $html .=
                    resident_history_render_value(
                        $item
                    );

            }


            $html .=
                '</div>';


            $html .=
                '</div>';


            $number++;

        }


        $html .=
            '</div>';


        return $html;

    }


    /*
    |--------------------------------------------------------------------------
    | Associative Array
    |--------------------------------------------------------------------------
    */

    $html =
        '<div class="history-detail-group">';


    foreach (
        $value
        as $key =>
        $child_value
    ) {

        if (
            resident_history_is_password_key(
                $key
            )
        ) {

            $child_value =
                "Password was changed";

        }


        if (
            is_array($child_value)
        ) {

            $html .=
                '<div class="history-subsection">';


            $html .=
                '<div class="history-subsection-title">' .
                resident_history_escape(
                    resident_history_section_label(
                        $key
                    )
                ) .
                '</div>';


            $html .=
                '<div class="history-subsection-content">';


            $html .=
                resident_history_render_value(
                    $child_value,
                    $key
                );


            $html .=
                '</div>';


            $html .=
                '</div>';

        }
        else {

            $html .=
                '<div class="history-field-row">';


            $html .=
                '<div class="history-field-label">' .
                resident_history_escape(
                    resident_history_field_label(
                        $key
                    )
                ) .
                '</div>';


            $html .=
                '<div class="history-field-value">' .
                resident_history_render_value(
                    $child_value,
                    $key
                ) .
                '</div>';


            $html .=
                '</div>';

        }

    }


    $html .=
        '</div>';


    return $html;

}


/*
|--------------------------------------------------------------------------
| Render Complete History Data
|--------------------------------------------------------------------------
*/

function resident_history_render_data(
    $data
) {

    $decoded =
        resident_history_decode(
            $data
        );


    if (
        $decoded === null
    ) {

        return
            '<div class="history-empty-data">' .
            'No information recorded.' .
            '</div>';

    }


    return
        '<div class="history-data-container">' .
        resident_history_render_value(
            $decoded
        ) .
        '</div>';

}

?>
<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    Resident Profile -
    <?= e($display_name) ?>
</title>


<link
    rel="stylesheet"
    href="../assets/css/style.css?v=<?= filemtime(__DIR__ . "/../assets/css/style.css") ?>"
>


<script>

(function () {

    var theme =
        localStorage.getItem("theme");

    if (
        theme === "dark"
    ) {

        document.documentElement.setAttribute(
            "data-theme",
            "dark"
        );

    }

})();

</script>


<script>

(function () {

    try {

        if (
            localStorage.getItem(
                "sidebarCollapsed"
            ) === "true"
        ) {

            document.documentElement.setAttribute(
                "data-sidebar",
                "collapsed"
            );

        }

    }
    catch (e) {}

})();

</script>


<style>

/*
|--------------------------------------------------------------------------
| Update History
|--------------------------------------------------------------------------
*/

.resident-update-history {

    margin-top: 24px;

}


.resident-update-history-header {

    display: flex;

    justify-content: space-between;

    align-items: flex-start;

    gap: 16px;

    padding: 20px;

    border-bottom:
        1px solid
        rgba(0, 0, 0, .08);

}


.resident-update-history-header h2 {

    margin: 0 0 5px;

    font-size: 20px;

}


.resident-update-history-header p {

    margin: 0;

    color: #667;

    font-size: 13px;

}


/*
|--------------------------------------------------------------------------
| History Entry
|--------------------------------------------------------------------------
*/

.resident-history-entry {

    margin: 16px;

    border:
        1px solid
        rgba(0, 0, 0, .10);

    border-radius: 10px;

    overflow: hidden;

}


.resident-history-entry:last-child {

    margin-bottom: 0;

}


.resident-history-entry-header {

    display: flex;

    justify-content: space-between;

    align-items: flex-start;

    gap: 16px;

    padding: 14px 16px;

    border-bottom:
        1px solid
        rgba(0, 0, 0, .08);

}


.resident-history-entry-section {

    font-weight: 700;

    font-size: 14px;

}


.resident-history-entry-meta {

    margin-top: 4px;

    color: #667;

    font-size: 12px;

}


.resident-history-entry-date {

    white-space: nowrap;

    color: #667;

    font-size: 12px;

}


/*
|--------------------------------------------------------------------------
| Previous / Updated Grid
|--------------------------------------------------------------------------
*/

.resident-history-change-grid {

    display: grid;

    grid-template-columns:
        minmax(0, 1fr)
        minmax(0, 1fr);

}


.resident-history-change-panel {

    min-width: 0;

}


.resident-history-change-panel
+
.resident-history-change-panel {

    border-left:
        1px solid
        rgba(0, 0, 0, .10);

}


.resident-history-change-title {

    padding: 11px 15px;

    font-weight: 700;

    font-size: 13px;

    background:
        rgba(0, 0, 0, .035);

    border-bottom:
        1px solid
        rgba(0, 0, 0, .08);

}


.resident-history-change-body {

    padding: 14px;

}


/*
|--------------------------------------------------------------------------
| History Data
|--------------------------------------------------------------------------
*/

.history-data-container {

    width: 100%;

}


.history-detail-group {

    width: 100%;

}


.history-field-row {

    display: grid;

    grid-template-columns:
        minmax(120px, .75fr)
        minmax(0, 1.25fr);

    gap: 12px;

    padding: 8px 0;

    border-bottom:
        1px solid
        rgba(0, 0, 0, .07);

}


.history-field-row:last-child {

    border-bottom: 0;

}


.history-field-label {

    font-weight: 600;

    font-size: 12px;

    color: #667;

}


.history-field-value {

    min-width: 0;

    font-size: 13px;

    word-break: break-word;

}


.history-subsection {

    margin-bottom: 12px;

}


.history-subsection:last-child {

    margin-bottom: 0;

}


.history-subsection-title {

    margin-bottom: 6px;

    font-size: 12px;

    font-weight: 700;

}


.history-subsection-content {

    padding-left: 8px;

}


.history-list {

    display: flex;

    flex-direction: column;

    gap: 10px;

}


.history-list-item {

    display: flex;

    gap: 10px;

    padding: 10px;

    border:
        1px solid
        rgba(0, 0, 0, .08);

    border-radius: 8px;

}


.history-list-number {

    flex: 0 0 auto;

    width: 24px;

    height: 24px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 50%;

    background:
        rgba(0, 0, 0, .06);

    font-size: 11px;

    font-weight: 700;

}


.history-list-content {

    flex: 1;

    min-width: 0;

}


.history-empty-data {

    color: #667;

    font-size: 13px;

}


/*
|--------------------------------------------------------------------------
| Dark Mode
|--------------------------------------------------------------------------
*/

[data-theme="dark"]
.resident-history-entry {

    border-color:
        rgba(255, 255, 255, .13);

}


[data-theme="dark"]
.resident-history-entry-header {

    border-color:
        rgba(255, 255, 255, .10);

}


[data-theme="dark"]
.resident-history-change-panel
+
.resident-history-change-panel {

    border-color:
        rgba(255, 255, 255, .10);

}


[data-theme="dark"]
.resident-history-change-title {

    background:
        rgba(255, 255, 255, .05);

    border-color:
        rgba(255, 255, 255, .08);

}


[data-theme="dark"]
.history-field-row {

    border-color:
        rgba(255, 255, 255, .08);

}


[data-theme="dark"]
.history-list-item {

    border-color:
        rgba(255, 255, 255, .10);

}


[data-theme="dark"]
.history-list-number {

    background:
        rgba(255, 255, 255, .10);

}


/*
|--------------------------------------------------------------------------
| Print
|--------------------------------------------------------------------------
*/

@media print {

    .resident-update-history {

        break-before: page;

    }


    .resident-history-entry {

        break-inside: avoid;

        border:
            1px solid #999 !important;

    }


    .resident-history-change-grid {

        grid-template-columns:
            1fr 1fr;

    }


    .resident-history-change-panel
    +
    .resident-history-change-panel {

        border-left:
            1px solid #999 !important;

    }


    .resident-history-change-title {

        background:
            #eeeeee !important;

        color:
            #000000 !important;

    }

}


/*
|--------------------------------------------------------------------------
| Mobile
|--------------------------------------------------------------------------
*/

@media (max-width: 760px) {

    .resident-history-entry-header {

        flex-direction: column;

        gap: 8px;

    }


    .resident-history-entry-date {

        white-space: normal;

    }


    .resident-history-change-grid {

        grid-template-columns:
            1fr;

    }


    .resident-history-change-panel
    +
    .resident-history-change-panel {

        border-left: 0;

        border-top:
            1px solid
            rgba(0, 0, 0, .10);

    }


    .history-field-row {

        grid-template-columns:
            1fr;

        gap: 3px;

    }

}

</style>

</head>


<body>


<?php

$no_print_nav = true;

include __DIR__ . "/../includes/staff_nav.php";

?>


<div class="container">


<?php

include __DIR__ . "/../includes/staff_topbar.php";

?>


<!--
============================================================================
RESIDENT PROFILE HEADER
============================================================================
-->

<div
    class="welcome-header no-print"
>

    <div>

        <h1>
            Resident Profile
        </h1>


        <p>

            Read-only record for
            <?= e($display_name) ?>.

        </p>

    </div>


    <div
        class="table-actions-res"
    >

        <a
            class="btn"
            href="resident_edit.php?resident_id=<?= $resident_id ?>"
        >
            Edit Record
        </a>


        <a
            class="btn"
            href="resident_management.php"
        >
            Back to List
        </a>


        <button
            type="button"
            class="btn"
            onclick="window.print()"
        >
            Print / Export as PDF
        </button>

    </div>

</div>


<!--
============================================================================
RESIDENT PROFILE
============================================================================
-->

<div
    class="card print-sheet"
>


    <div
        class="print-header"
    >

        <h1>
            Barangay Health Center -
            Resident Personal Information
        </h1>


        <p>
            Printed
            <?= date("F j, Y, g:i A") ?>
        </p>

    </div>


    <!--
    ------------------------------------------------------------------------
    Resident Photo / Identity
    ------------------------------------------------------------------------
    -->

    <div
        class="photo-uploader"
        style="margin-bottom:16px;"
    >

        <div
            class="avatar-lg"
        >

            <?php if (
                $photo_url
            ): ?>

                <img
                    src="<?= e($photo_url) ?>"
                    alt="Resident photo"
                >

            <?php else: ?>

                <?= e($initials) ?>

            <?php endif; ?>

        </div>


        <div>

            <strong
                style="font-size:16px;"
            >
                <?= e(
                    $display_name
                ) ?>
            </strong>


            <br>


            <span
                style="
                    color:#667;
                    font-size:13px;
                "
            >

                Resident Number:

                <?= e(
                    $resident[
                        "resident_number"
                    ]
                ) ?>

            </span>

        </div>

    </div>


    <!--
    ------------------------------------------------------------------------
    Personal Information
    ------------------------------------------------------------------------
    -->

    <div
        class="form-section-title"
    >
        Personal Information
    </div>


    <div
        class="print-info-grid"
    >

        <div class="info-row">

            <strong>
                First Name
            </strong>

            <span>
                <?= e(
                    $resident["first_name"] ?? ""
                ) ?: "—" ?>
            </span>

        </div>


        <div class="info-row">

            <strong>
                Middle Name
            </strong>

            <span>
                <?= e(
                    $resident["middle_name"] ?? ""
                ) ?: "—" ?>
            </span>

        </div>


        <div class="info-row">

            <strong>
                Last Name
            </strong>

            <span>
                <?= e(
                    $resident["last_name"] ?? ""
                ) ?: "—" ?>
            </span>

        </div>


        <div class="info-row">

            <strong>
                Extension Name
            </strong>

            <span>
                <?= e(
                    $resident["extension_name"] ?? ""
                ) ?: "—" ?>
            </span>

        </div>


        <div class="info-row">

            <strong>
                Gender
            </strong>

            <span>
                <?= e(
                    $resident["gender"] ?? ""
                ) ?: "—" ?>
            </span>

        </div>


        <div class="info-row">

            <strong>
                Civil Status
            </strong>

            <span>
                <?= e(
                    $resident["civil_status"] ?? ""
                ) ?: "—" ?>
            </span>

        </div>


        <div class="info-row">

            <strong>
                Birthday
            </strong>

            <span>

                <?= format_date_display(
                    $resident["birthday"] ?? null
                ) ?>

            </span>

        </div>


        <div class="info-row">

            <strong>
                Age
            </strong>

            <span>
                <?= $resident["age"] !== null &&
                    $resident["age"] !== ""
                    ? e($resident["age"])
                    : "—"
                ?>
            </span>

        </div>


        <div class="info-row">

            <strong>
                Occupation
            </strong>

            <span>
                <?= e(
                    $resident["occupation"] ?? ""
                ) ?: "—" ?>
            </span>

        </div>


        <div class="info-row">

            <strong>
                Employer
            </strong>

            <span>
                <?= e(
                    $resident["employer"] ?? ""
                ) ?: "—" ?>
            </span>

        </div>


        <div class="info-row">

            <strong>
                Employer Address
            </strong>

            <span>
                <?= e(
                    $resident["employer_address"] ?? ""
                ) ?: "—" ?>
            </span>

        </div>


        <div class="info-row">

            <strong>
                Email
            </strong>

            <span>
                <?= e(
                    $resident["email"] ?? ""
                ) ?: "—" ?>
            </span>

        </div>


        <div class="info-row">

            <strong>
                Contact Number
            </strong>

            <span>
                <?= e(
                    $resident["contact_number"] ?? ""
                ) ?: "—" ?>
            </span>

        </div>


        <div
            class="info-row"
            style="grid-column:1/-1;"
        >

            <strong>
                Address
            </strong>

            <span>
                <?= e(
                    $resident["address"] ?? ""
                ) ?: "—" ?>
            </span>

        </div>

    </div>


    <!--
    ------------------------------------------------------------------------
    Parents
    ------------------------------------------------------------------------
    -->

    <div
        class="form-section-title"
    >
        Parents
    </div>


    <div
        class="print-info-grid"
    >

        <div class="info-row">

            <strong>
                Father's Name
            </strong>

            <span>

                <?= e(
                    $parents["father_name"]
                ) ?: "—" ?>

            </span>

        </div>


        <div class="info-row">

            <strong>
                Mother's Name
            </strong>

            <span>

                <?= e(
                    $parents["mother_name"]
                ) ?: "—" ?>

            </span>

        </div>

    </div>


    <!--
    ------------------------------------------------------------------------
    Spouse
    ------------------------------------------------------------------------
    -->

    <div
        class="form-section-title"
    >
        Spouse Information
    </div>


    <div
        class="print-info-grid"
    >

        <div class="info-row">

            <strong>
                Spouse Name
            </strong>

            <span>

                <?= e(
                    $spouse["spouse_name"]
                ) ?: "—" ?>

            </span>

        </div>


        <div class="info-row">

            <strong>
                Occupation
            </strong>

            <span>

                <?= e(
                    $spouse["occupation"]
                ) ?: "—" ?>

            </span>

        </div>


        <div class="info-row">

            <strong>
                Employer
            </strong>

            <span>

                <?= e(
                    $spouse["employer"]
                ) ?: "—" ?>

            </span>

        </div>

    </div>


    <!--
    ------------------------------------------------------------------------
    Children
    ------------------------------------------------------------------------
    -->

    <div
        class="form-section-title"
    >
        Children
    </div>


    <?php if (
        count($children) === 0
    ): ?>

        <p
            style="
                font-size:13px;
                color:#667;
            "
        >
            No children on record.
        </p>

    <?php else: ?>

        <table>

            <thead>

                <tr>

                    <th>
                        Child Name
                    </th>

                    <th>
                        Age
                    </th>

                </tr>

            </thead>


            <tbody>

            <?php foreach (
                $children
                as $child
            ): ?>

                <tr>

                    <td>

                        <?= e(
                            $child["child_name"]
                        ) ?>

                    </td>


                    <td>

                        <?= $child["age"] !== null
                            ? e(
                                $child["age"]
                            )
                            : "—"
                        ?>

                    </td>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

    <?php endif; ?>


    <!--
    ------------------------------------------------------------------------
    Character References
    ------------------------------------------------------------------------
    -->

    <div
        class="form-section-title"
    >
        Character References
    </div>


    <?php if (
        count($references) === 0
    ): ?>

        <p
            style="
                font-size:13px;
                color:#667;
            "
        >
            No character references on record.
        </p>

    <?php else: ?>

        <div
            class="print-info-grid"
        >

            <?php foreach (
                $references
                as $reference
            ): ?>

                <div class="info-row">

                    <strong>

                        Reference
                        <?= e(
                            $reference[
                                "reference_order"
                            ]
                        ) ?>

                    </strong>

                    <span>

                        <?= e(
                            $reference[
                                "reference_name"
                            ]
                        ) ?: "—" ?>

                    </span>

                </div>


                <?php if (
                    !empty(
                        $reference[
                            "signature"
                        ]
                    )
                ): ?>

                    <div class="info-row">

                        <strong>
                            Signature
                        </strong>

                        <span>

                            <?= e(
                                $reference[
                                    "signature"
                                ]
                            ) ?>

                        </span>

                    </div>

                <?php endif; ?>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>


    <!--
    ------------------------------------------------------------------------
    Record Info
    ------------------------------------------------------------------------
    -->

    <div
        class="form-section-title"
    >
        Record Info
    </div>


    <div
        class="print-info-grid"
    >

        <div class="info-row">

            <strong>
                Account Created
            </strong>

            <span>

                <?= format_date_display(
                    $resident["created_at"]
                        ? substr(
                            $resident["created_at"],
                            0,
                            10
                        )
                        : null
                ) ?>

            </span>

        </div>


        <div class="info-row">

            <strong>
                Last Updated
            </strong>

            <span>

                <?= $resident["updated_at"]
                    ? e(
                        date(
                            "M d, Y g:i A",
                            strtotime(
                                $resident["updated_at"]
                            )
                        )
                    )
                    : "—"
                ?>

            </span>

        </div>

    </div>


</div>


<!--
============================================================================
UPDATE HISTORY
============================================================================
-->

<div
    class="resident-update-history"
>


    <div
        class="card"
    >


        <div
            class="resident-update-history-header"
        >

            <div>

                <h2>
                    Update History
                </h2>

                <p>
                    Previous and updated information recorded for this resident.
                </p>

            </div>

        </div>


        <?php if (
            count($history) === 0
        ): ?>


            <div
                class="resident-history-empty"
                style="
                    padding:20px;
                    text-align:center;
                    color:#667;
                    font-size:13px;
                "
            >

                No update history has been recorded
                for this resident.

            </div>


        <?php else: ?>


            <?php foreach (
                $history
                as $history_row
            ): ?>


                <?php

                /*
                 * Determine actor.
                 */

                if (
                    ($history_row["updated_by_type"] ?? "")
                    ===
                    "staff"
                ) {

                    $staff_id =
                        (int) (
                            $history_row[
                                "updated_by_id"
                            ] ?? 0
                        );


                    $actor_name =
                        $staff_names[
                            $staff_id
                        ] ?? "Staff";


                    $actor_details =
                        $staff_id > 0
                            ? "Staff ID: " .
                              $staff_id
                            : "Staff";

                }
                else {

                    $actor_name =
                        "Resident";


                    $resident_actor_id =
                        (int) (
                            $history_row[
                                "updated_by_id"
                            ] ?? 0
                        );


                    $actor_details =
                        $resident_actor_id > 0
                            ? "Resident ID: " .
                              $resident_actor_id
                            : "Resident";

                }


                /*
                 * Section.
                 */

                $history_section =
                    resident_history_section_label(
                        $history_row[
                            "update_section"
                        ] ?? ""
                    );


                /*
                 * Date.
                 */

                $history_date =
                    !empty(
                        $history_row[
                            "updated_at"
                        ]
                    )
                        ? date(
                            "M d, Y h:i A",
                            strtotime(
                                $history_row[
                                    "updated_at"
                                ]
                            )
                        )
                        : "Date unavailable";

                ?>


                <div
                    class="resident-history-entry"
                >


                    <!--
                    ------------------------------------------------------------
                    History Header
                    ------------------------------------------------------------
                    -->

                    <div
                        class="resident-history-entry-header"
                    >

                        <div>

                            <div
                                class="resident-history-entry-section"
                            >

                                <?= resident_history_escape(
                                    $history_section
                                ) ?>

                            </div>


                            <div
                                class="resident-history-entry-meta"
                            >

                                Updated by

                                <strong>
                                    <?= resident_history_escape(
                                        $actor_name
                                    ) ?>
                                </strong>

                                ·

                                <?= resident_history_escape(
                                    $actor_details
                                ) ?>

                            </div>

                        </div>


                        <div
                            class="resident-history-entry-date"
                        >

                            <?= resident_history_escape(
                                $history_date
                            ) ?>

                        </div>

                    </div>


                    <!--
                    ------------------------------------------------------------
                    Previous / Updated Information
                    ------------------------------------------------------------
                    -->

                    <div
                        class="resident-history-change-grid"
                    >


                        <div
                            class="resident-history-change-panel"
                        >

                            <div
                                class="resident-history-change-title"
                            >
                                Previous Information
                            </div>


                            <div
                                class="resident-history-change-body"
                            >

                                <?= resident_history_render_data(
                                    $history_row[
                                        "old_data"
                                    ]
                                ) ?>

                            </div>

                        </div>


                        <div
                            class="resident-history-change-panel"
                        >

                            <div
                                class="resident-history-change-title"
                            >
                                Updated Information
                            </div>


                            <div
                                class="resident-history-change-body"
                            >

                                <?= resident_history_render_data(
                                    $history_row[
                                        "new_data"
                                    ]
                                ) ?>

                            </div>

                        </div>


                    </div>


                </div>


            <?php endforeach; ?>


        <?php endif; ?>


    </div>

</div>


</div>


<script
    src="../assets/js/script.js"
></script>


<?php if (
    isset(
        $_GET["print"]
    ) &&
    $_GET["print"] === "1"
): ?>

<script>

window.addEventListener(
    "load",
    function () {

        window.print();

    }
);

</script>

<?php endif; ?>


</body>

</html>