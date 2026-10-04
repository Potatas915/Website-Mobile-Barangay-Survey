<?php

require_once __DIR__ . "/../includes/functions.php";

require_staff_login();


/*
|--------------------------------------------------------------------------
| Resident ID
|--------------------------------------------------------------------------
*/

$resident_id =
    (int) (
        $_GET["resident_id"]
        ?? $_POST["resident_id"]
        ?? 0
    );


$success = "";
$error = "";
$photo_error = "";
$child_error = "";


/*
|--------------------------------------------------------------------------
| Validate Resident ID
|--------------------------------------------------------------------------
*/

if ($resident_id <= 0) {

    redirect(
        "resident_management.php"
    );

}


/*
|--------------------------------------------------------------------------
| Helper: Get Resident
|--------------------------------------------------------------------------
*/

function get_edit_resident(
    $resident_id
) {

    // One row out of the residents collection, by key.
    $resident =
        fb_get("residents/" . $resident_id);


    if (!is_array($resident)) {

        return null;

    }


    $resident["resident_id"] = $resident_id;

    return $resident;

}


/*
|--------------------------------------------------------------------------
| Helper: Get Spouse
|--------------------------------------------------------------------------
|
| Spouse information is stored directly in residents.
|--------------------------------------------------------------------------
*/

function get_edit_spouse(
    $resident_id
) {

    $spouse = [

        "spouse_name" =>
            "",

        "occupation" =>
            "",

        "employer" =>
            ""

    ];


    $result =
        fb_get("residents/" . $resident_id);


    if (is_array($result)) {

        $spouse = [

            "spouse_name" =>
                $result["spouse_name"] ?? "",

            "occupation" =>
                $result["spouse_occupation"] ?? "",

            "employer" =>
                $result["spouse_employer"] ?? ""

        ];

    }


    return $spouse;

}


/*
|--------------------------------------------------------------------------
| Helper: Get Parents
|--------------------------------------------------------------------------
|
| Parent information is stored directly in residents.
|--------------------------------------------------------------------------
*/

function get_edit_parents(
    $resident_id
) {

    $parents = [

        "father_name" =>
            "",

        "mother_name" =>
            ""

    ];


    $result =
        fb_get("residents/" . $resident_id);


    if (is_array($result)) {

        $parents = [

            "father_name" =>
                $result["father_name"] ?? "",

            "mother_name" =>
                $result["mother_name"] ?? ""

        ];

    }


    return $parents;

}


/*
|--------------------------------------------------------------------------
| Helper: Get Children
|--------------------------------------------------------------------------
*/

function get_edit_children(
    $resident_id
) {

    $children = [];


    // Children live in a nested node of the resident, and the numeric keys
    // already come back in child_id order.
    foreach (
        fb_rows(
            "residents/" . $resident_id . "/children"
        )
        as $child_id =>
        $row
    ) {

        $children[] = [

            "child_id" =>
                (int) $child_id,

            "child_name" =>
                $row["child_name"] ?? "",

            "age" =>
                $row["age"] ?? ""

        ];

    }


    return $children;

}


/*
|--------------------------------------------------------------------------
| Helper: Get References
|--------------------------------------------------------------------------
|
| Character references are stored directly in residents.
|--------------------------------------------------------------------------
*/

function get_edit_references(
    $resident_id
) {

    $references = [];


    $result =
        fb_get("residents/" . $resident_id);


    if (is_array($result)) {

        if (
            !empty(
                $result["reference1_name"]
            ) ||
            !empty(
                $result["reference1_signature"]
            )
        ) {

            $references[] = [

                "reference_name" =>
                    $result["reference1_name"] ?? "",

                "signature" =>
                    $result["reference1_signature"] ?? "",

                "reference_order" =>
                    1

            ];

        }


        if (
            !empty(
                $result["reference2_name"]
            ) ||
            !empty(
                $result["reference2_signature"]
            )
        ) {

            $references[] = [

                "reference_name" =>
                    $result["reference2_name"] ?? "",

                "signature" =>
                    $result["reference2_signature"] ?? "",

                "reference_order" =>
                    2

            ];

        }

    }


    return $references;

}


/*
|--------------------------------------------------------------------------
| Helper: Build Complete Resident Snapshot
|--------------------------------------------------------------------------
|
| Password is intentionally excluded.
|--------------------------------------------------------------------------
*/

function build_edit_snapshot(
    $resident_id
) {

    $resident =
        get_edit_resident(
            $resident_id
        );


    if (!$resident) {

        return null;

    }


    $children =
        get_edit_children(
            $resident_id
        );


    $clean_children = [];


    foreach (
        $children
        as $child
    ) {

        $clean_children[] = [

            "child_name" =>
                $child["child_name"],

            "age" =>
                $child["age"]

        ];

    }


    $references =
        get_edit_references(
            $resident_id
        );


    $clean_references = [];


    foreach (
        $references
        as $reference
    ) {

        $clean_references[] = [

            "reference_name" =>
                $reference["reference_name"],

            "signature" =>
                $reference["signature"],

            "reference_order" =>
                $reference["reference_order"]

        ];

    }


    $spouse = [

        "spouse_name" =>
            $resident["spouse_name"] ?? "",

        "occupation" =>
            $resident["spouse_occupation"] ?? "",

        "employer" =>
            $resident["spouse_employer"] ?? ""

    ];


    $parents = [

        "father_name" =>
            $resident["father_name"] ?? "",

        "mother_name" =>
            $resident["mother_name"] ?? ""

    ];


    return [

        "personal_information" => [

            "resident_number" =>
                $resident["resident_number"],

            "first_name" =>
                $resident["first_name"],

            "middle_name" =>
                $resident["middle_name"],

            "last_name" =>
                $resident["last_name"],

            "extension_name" =>
                $resident["extension_name"],

            "gender" =>
                $resident["gender"] ?? null,

            "civil_status" =>
                $resident["civil_status"],

            "birthday" =>
                $resident["birthday"],

            "age" =>
                $resident["age"],

            "occupation" =>
                $resident["occupation"],

            "employer" =>
                $resident["employer"],

            "employer_address" =>
                $resident["employer_address"],

            "email" =>
                $resident["email"],

            "contact_number" =>
                $resident["contact_number"],

            "address" =>
                $resident["address"]

        ],


        "spouse" => (

            $spouse["spouse_name"] !== ""
            ||
            $spouse["occupation"] !== ""
            ||
            $spouse["employer"] !== ""

        )
            ? $spouse
            : null,


        "parents" => (

            $parents["father_name"] !== ""
            ||
            $parents["mother_name"] !== ""

        )
            ? $parents
            : null,


        "children" =>
            $clean_children,


        "references" =>
            $clean_references

    ];

}


/*
|--------------------------------------------------------------------------
| Helper: Save Update History
|--------------------------------------------------------------------------
*/

function save_edit_history(
    $resident_id,
    $old_snapshot,
    $new_snapshot
) {

    $old_json_compare =
        json_encode(
            $old_snapshot,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );


    $new_json_compare =
        json_encode(
            $new_snapshot,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );


    /*
    |--------------------------------------------------------------------------
    | Nothing changed
    |--------------------------------------------------------------------------
    */

    if (
        $old_json_compare ===
        $new_json_compare
    ) {

        return true;

    }


    $staff_id =
        isset(
            $_SESSION["staff_id"]
        )
            ? (int) $_SESSION["staff_id"]
            : null;


    $old_data =
        json_encode(
            $old_snapshot,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES |
            JSON_PRETTY_PRINT
        );


    $new_data =
        json_encode(
            $new_snapshot,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES |
            JSON_PRETTY_PRINT
        );


    fb_insert("resident_update_history", array(
        "resident_id"     => (string) $resident_id,
        "updated_by_type" => "staff",
        "updated_by_id"   => (int) $staff_id,
        "update_section"  => "complete_profile",
        "old_data"        => $old_data,
        "new_data"        => $new_data,
        "updated_at"      => date("Y-m-d H:i:s"),
    ));


    return true;

}


/*
|--------------------------------------------------------------------------
| Load Resident
|--------------------------------------------------------------------------
*/

$resident =
    get_edit_resident(
        $resident_id
    );


if (!$resident) {

    redirect(
        "resident_management.php"
    );

}


/*
|--------------------------------------------------------------------------
| Update Resident Information
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    &&
    isset(
        $_POST["update_resident"]
    )
) {

    $resident_number =
        trim(
            $_POST["resident_number"]
            ?? ""
        );


    $first_name =
        trim(
            $_POST["first_name"]
            ?? ""
        );


    $middle_name =
        trim(
            $_POST["middle_name"]
            ?? ""
        );


    $last_name =
        trim(
            $_POST["last_name"]
            ?? ""
        );


    $extension_name =
        trim(
            $_POST["extension_name"]
            ?? ""
        );


    $gender =
        trim(
            $_POST["gender"]
            ?? ""
        );


    $civil_status =
        trim(
            $_POST["civil_status"]
            ?? ""
        );


    $email =
        trim(
            $_POST["email"]
            ?? ""
        );


    $contact_number =
        trim(
            $_POST["contact_number"]
            ?? ""
        );


    $address =
        trim(
            $_POST["address"]
            ?? ""
        );


    $birthday =
        trim(
            $_POST["birthday"]
            ?? ""
        );


    $occupation =
        trim(
            $_POST["occupation"]
            ?? ""
        );


    $employer =
        trim(
            $_POST["employer"]
            ?? ""
        );


    $employer_address =
        trim(
            $_POST["employer_address"]
            ?? ""
        );


    $father_name =
        trim(
            $_POST["father_name"]
            ?? ""
        );


    $mother_name =
        trim(
            $_POST["mother_name"]
            ?? ""
        );


    $spouse_name =
        trim(
            $_POST["spouse_name"]
            ?? ""
        );


    $spouse_occupation =
        trim(
            $_POST["spouse_occupation"]
            ?? ""
        );


    $spouse_employer =
        trim(
            $_POST["spouse_employer"]
            ?? ""
        );


    $reference1_name =
        trim(
            $_POST["reference1_name"]
            ?? ""
        );


    $reference1_signature =
        trim(
            $_POST["reference1_signature"]
            ?? ""
        );


    $reference2_name =
        trim(
            $_POST["reference2_name"]
            ?? ""
        );


    $reference2_signature =
        trim(
            $_POST["reference2_signature"]
            ?? ""
        );


    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if (
        $resident_number === ""
        ||
        $first_name === ""
        ||
        $last_name === ""
    ) {

        $error =
            "Resident number, first name, and last name are required.";

    }


    elseif (
        !preg_match(
            "/^[A-Za-z0-9\-]+$/",
            $resident_number
        )
    ) {

        $error =
            "Resident number contains invalid characters.";

    }


    elseif (
        !preg_match(
            "/^[\p{L}\s.'-]+$/u",
            $first_name
        )
    ) {

        $error =
            "First name contains invalid characters.";

    }


    elseif (
        $middle_name !== ""
        &&
        !preg_match(
            "/^[\p{L}\s.'-]+$/u",
            $middle_name
        )
    ) {

        $error =
            "Middle name contains invalid characters.";

    }


    elseif (
        !preg_match(
            "/^[\p{L}\s.'-]+$/u",
            $last_name
        )
    ) {

        $error =
            "Last name contains invalid characters.";

    }


    elseif (
        $extension_name !== ""
        &&
        !preg_match(
            "/^[A-Za-z0-9\s.'-]+$/",
            $extension_name
        )
    ) {

        $error =
            "Extension name contains invalid characters.";

    }


    elseif (
        $gender !== ""
        &&
        !in_array(
            $gender,
            [
                "Male",
                "Female"
            ],
            true
        )
    ) {

        $error =
            "Please select a valid gender.";

    }


    elseif (
        $civil_status !== ""
        &&
        !in_array(
            $civil_status,
            [
                "Single",
                "Married",
                "Widowed",
                "Separated",
                "Divorced"
            ],
            true
        )
    ) {

        $error =
            "Please select a valid civil status.";

    }


    elseif (
        $email !== ""
        &&
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $error =
            "Please enter a valid email address.";

    }


    elseif (
        $contact_number !== ""
        &&
        !preg_match(
            "/^09[0-9]{9}$/",
            $contact_number
        )
    ) {

        $error =
            "Contact number must contain exactly 11 digits and start with 09.";

    }


    elseif (
        $birthday !== ""
        &&
        (
            !DateTime::createFromFormat(
                "Y-m-d",
                $birthday
            )
            ||
            $birthday >
            date("Y-m-d")
        )
    ) {

        $error =
            "Please enter a valid birthday.";

    }


    /*
    |--------------------------------------------------------------------------
    | Duplicate Resident Number
    |--------------------------------------------------------------------------
    */

    if (
        $error === ""
    ) {

        // The lookup/ index finds any resident holding this number; the id
        // check leaves the resident's own number alone.
        $duplicate =
            fb_resident_by_number(
                $resident_number
            );


        if (
            $duplicate !== null
            &&
            (string) ($duplicate["id"] ?? "")
            !==
            (string) $resident_id
        ) {

            $error =
                "Resident number is already used by another resident.";

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Continue only when validation passed
    |--------------------------------------------------------------------------
    */

    if (
        $error === ""
    ) {

        try {

            /*
            |--------------------------------------------------------------------------
            | BEFORE SNAPSHOT
            |--------------------------------------------------------------------------
            */

            $old_snapshot =
                build_edit_snapshot(
                    $resident_id
                );


            if (!$old_snapshot) {

                throw new Exception(
                    "Unable to load the resident's existing information."
                );

            }


            /*
            |--------------------------------------------------------------------------
            | Calculate Age
            |--------------------------------------------------------------------------
            */

            $age = null;


            if (
                $birthday !== ""
            ) {

                $birth_date =
                    new DateTime(
                        $birthday
                    );


                $today =
                    new DateTime();


                $age =
                    $birth_date
                        ->diff($today)
                        ->y;

            }


            /*
            |--------------------------------------------------------------------------
            | UPDATE RESIDENT
            |--------------------------------------------------------------------------
            |
            | Parents, spouse, and references are all stored directly in
            | residents according to the current Set A database. Empty fields
            | are written as "" instead of SQL NULL.
            |--------------------------------------------------------------------------
            */

            fb_update(
                "residents",
                $resident_id,
                array(
                    "resident_number"       => $resident_number,
                    "first_name"            => $first_name,
                    "middle_name"           => $middle_name,
                    "last_name"             => $last_name,
                    "extension_name"        => $extension_name,
                    "gender"                => $gender,
                    "civil_status"          => $civil_status,
                    "email"                 => $email,
                    "contact_number"        => $contact_number,
                    "address"               => $address,
                    "birthday"              => $birthday,
                    "age"                   => $age === null ? "" : $age,
                    "occupation"            => $occupation,
                    "employer"              => $employer,
                    "employer_address"      => $employer_address,
                    "father_name"           => $father_name,
                    "mother_name"           => $mother_name,
                    "spouse_name"           => $spouse_name,
                    "spouse_occupation"     => $spouse_occupation,
                    "spouse_employer"       => $spouse_employer,
                    "reference1_name"       => $reference1_name,
                    "reference1_signature"  => $reference1_signature,
                    "reference2_name"       => $reference2_name,
                    "reference2_signature"  => $reference2_signature,
                    "updated_at"            => date("Y-m-d H:i:s"),
                )
            );


            // lookup/ still points at the old number when staff renumbers a
            // resident, so move the pointer before anything reads it again.
            if (
                (string) $resident_number
                !==
                (string) ($resident["resident_number"] ?? "")
            ) {

                fb_update_resident_number(
                    $resident["resident_number"] ?? "",
                    $resident_number,
                    $resident_id
                );

            }


            /*
            |--------------------------------------------------------------------------
            | AFTER SNAPSHOT
            |--------------------------------------------------------------------------
            */

            $new_snapshot =
                build_edit_snapshot(
                    $resident_id
                );


            if (!$new_snapshot) {

                throw new Exception(
                    "Unable to create the updated resident snapshot."
                );

            }


            /*
            |--------------------------------------------------------------------------
            | Save History
            |--------------------------------------------------------------------------
            */

            save_edit_history(
                $resident_id,
                $old_snapshot,
                $new_snapshot
            );


            /*
            |--------------------------------------------------------------------------
            | Commit
            |--------------------------------------------------------------------------
            */



            $success =
                "Resident information updated successfully.";


            /*
            |--------------------------------------------------------------------------
            | Reload Resident
            |--------------------------------------------------------------------------
            */

            $resident =
                get_edit_resident(
                    $resident_id
                );

        }
        catch (
            Throwable $exception
        ) {



            $error =
                $exception->getMessage();


            /*
            |--------------------------------------------------------------------------
            | Hide technical database errors
            |--------------------------------------------------------------------------
            */

            if (
                $error === ""
                ||
                stripos(
                    $error,
                    "column"
                ) !== false
                ||
                stripos(
                    $error,
                    "table"
                ) !== false
                ||
                stripos(
                    $error,
                    "sql"
                ) !== false
                ||
                stripos(
                    $error,
                    "mysqli"
                ) !== false
            ) {

                $error =
                    "Unable to update resident. Please try again.";

            }

        }

    }

}


/*
|--------------------------------------------------------------------------
| Upload / Replace Photo
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    &&
    isset(
        $_POST["upload_photo"]
    )
) {

    [$new_photo, $upload_err] =
        save_resident_photo(
            $resident_id,
            $_FILES["photo"] ?? null
        );


    if ($upload_err) {

        $photo_error =
            $upload_err;

    }


    elseif (
        $new_photo
    ) {

        try {



            /*
            |--------------------------------------------------------------------------
            | Get old photo
            |--------------------------------------------------------------------------
            */

            $old_row =
                fb_get(
                    "residents/" . $resident_id
                );


            if (!is_array($old_row)) {

                throw new Exception(
                    "Unable to load current photo."
                );

            }


            $old_photo =
                $old_row["photo"]
                ?? null;


            /*
            |--------------------------------------------------------------------------
            | Update photo
            |--------------------------------------------------------------------------
            */

            fb_update(
                "residents",
                $resident_id,
                array(
                    "photo" => $new_photo
                )
            );


            fb_forget();


            /*
            |--------------------------------------------------------------------------
            | Save photo history
            |--------------------------------------------------------------------------
            */

            $staff_id =
                isset(
                    $_SESSION["staff_id"]
                )
                    ? (int)
                        $_SESSION["staff_id"]
                    : null;


            $photo_old_data =
                json_encode(
                    [
                        "photo" =>
                            $old_photo
                    ],
                    JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES |
                    JSON_PRETTY_PRINT
                );


            $photo_new_data =
                json_encode(
                    [
                        "photo" =>
                            $new_photo
                    ],
                    JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES |
                    JSON_PRETTY_PRINT
                );


            fb_insert(
                "resident_update_history",
                array(
                    "resident_id"     => (string) $resident_id,
                    "updated_by_type" => "staff",
                    "updated_by_id"   => (int) $staff_id,
                    "update_section"  => "photo",
                    "old_data"        => $photo_old_data,
                    "new_data"        => $photo_new_data,
                    "updated_at"      => date("Y-m-d H:i:s"),
                )
            );


            /*
            |--------------------------------------------------------------------------
            | Delete old physical file only after successful commit
            |--------------------------------------------------------------------------
            */

            if (
                $old_photo
            ) {

                delete_resident_photo(
                    $old_photo
                );

            }


            $success =
                "Photo updated successfully.";


            $resident =
                get_edit_resident(
                    $resident_id
                );

        }
        catch (
            Throwable $exception
        ) {



            /*
            |--------------------------------------------------------------------------
            | Delete newly uploaded file if database update failed
            |--------------------------------------------------------------------------
            */

            delete_resident_photo(
                $new_photo
            );


            $photo_error =
                "Unable to save photo. Please try again.";

        }

    }


    else {

        $photo_error =
            "Please choose a photo to upload.";

    }

}


/*
|--------------------------------------------------------------------------
| Add Child
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    &&
    isset(
        $_POST["add_child"]
    )
) {

    $child_name =
        trim(
            $_POST["child_name"]
            ?? ""
        );


    $child_age =
        trim(
            $_POST["child_age"]
            ?? ""
        );


    if (
        $child_name === ""
    ) {

        $child_error =
            "Child's name is required.";

    }


    elseif (
        !preg_match(
            "/^[\p{L}\s.'-]+$/u",
            $child_name
        )
    ) {

        $child_error =
            "Child's name contains invalid characters.";

    }


    elseif (
        $child_age !== ""
        &&
        (
            !ctype_digit(
                $child_age
            )
            ||
            (int)$child_age < 0
            ||
            (int)$child_age > 120
        )
    ) {

        $child_error =
            "Child's age must be between 0 and 120.";

    }


    else {

        try {



            /*
            |--------------------------------------------------------------------------
            | BEFORE
            |--------------------------------------------------------------------------
            */

            $old_snapshot =
                build_edit_snapshot(
                    $resident_id
                );


            /*
            |--------------------------------------------------------------------------
            | Insert child
            |--------------------------------------------------------------------------
            */

            // Children are a nested node of the resident, so this is one write
            // inside the resident's own record.
            $child_id =
                fb_next_id(
                    "resident_children"
                );


            fb_raw(
                "PUT",
                "residents/" . $resident_id . "/children/" . $child_id,
                array(
                    "child_id"    => (string) $child_id,
                    "resident_id" => (string) $resident_id,
                    "child_name"  => $child_name,
                    "age"         => $child_age === ""
                        ? ""
                        : (int) $child_age,
                )
            );


            // Drop the read cache so the snapshot below sees the new child.
            fb_forget();


            /*
            |--------------------------------------------------------------------------
            | AFTER
            |--------------------------------------------------------------------------
            */

            $new_snapshot =
                build_edit_snapshot(
                    $resident_id
                );


            save_edit_history(
                $resident_id,
                $old_snapshot,
                $new_snapshot
            );




            $success =
                "Child added successfully.";

        }
        catch (
            Throwable $exception
        ) {



            $child_error =
                "Unable to add child.";

        }

    }

}


/*
|--------------------------------------------------------------------------
| Remove Child
|--------------------------------------------------------------------------
*/

if (
    isset(
        $_GET["delete_child"]
    )
) {

    $child_id =
        (int)
        $_GET["delete_child"];


    if (
        $child_id > 0
    ) {

        try {



            /*
            |--------------------------------------------------------------------------
            | BEFORE
            |--------------------------------------------------------------------------
            */

            $old_snapshot =
                build_edit_snapshot(
                    $resident_id
                );


            /*
            |--------------------------------------------------------------------------
            | Delete only this resident's child
            |--------------------------------------------------------------------------
            */

            // Children live under the resident, so the resident_id check is
            // simply part of the path being deleted.
            $child_path =
                "residents/" . $resident_id . "/children/" . $child_id;


            if (
                !is_array(
                    fb_get($child_path)
                )
            ) {

                throw new Exception(
                    "Child record was not found."
                );

            }


            fb_remove($child_path);

            fb_forget();

            if (
                $deleted === 0
            ) {

                throw new Exception(
                    "Child record was not found."
                );

            }


            /*
            |--------------------------------------------------------------------------
            | AFTER
            |--------------------------------------------------------------------------
            */

            $new_snapshot =
                build_edit_snapshot(
                    $resident_id
                );


            save_edit_history(
                $resident_id,
                $old_snapshot,
                $new_snapshot
            );




            redirect(
                "resident_edit.php?resident_id=" .
                $resident_id
            );

        }
        catch (
            Throwable $exception
        ) {



            $child_error =
                "Unable to remove child.";

        }

    }

}


/*
|--------------------------------------------------------------------------
| Reload All Data
|--------------------------------------------------------------------------
*/

$resident =
    get_edit_resident(
        $resident_id
    );


if (!$resident) {

    redirect(
        "resident_management.php"
    );

}


$spouse =
    get_edit_spouse(
        $resident_id
    );


$parents =
    get_edit_parents(
        $resident_id
    );


$children =
    get_edit_children(
        $resident_id
    );


$references =
    get_edit_references(
        $resident_id
    );


/*
|--------------------------------------------------------------------------
| Photo / Initials
|--------------------------------------------------------------------------
*/

$photo_url =
    resident_photo_url(
        $resident["photo"]
        ?? ""
    );


$initials =
    strtoupper(
        substr(
            $resident["first_name"]
            ?? "",
            0,
            1
        )
        .
        substr(
            $resident["last_name"]
            ?? "",
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
| Civil Status Options
|--------------------------------------------------------------------------
*/

$civil_statuses = [

    "Single",
    "Married",
    "Widowed",
    "Separated",
    "Divorced"

];

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
    Edit Resident
</title>


<link
    rel="stylesheet"
    href="../assets/css/style.css?v=<?= filemtime(__DIR__ . "/../assets/css/style.css") ?>"
>


<script>

(function(){

    var t =
        localStorage.getItem(
            "theme"
        );


    if (
        t === "dark"
    ) {

        document.documentElement.setAttribute(
            "data-theme",
            "dark"
        );

    }

})();

</script>


<script>

(function(){

    try {

        if (
            localStorage.getItem(
                "sidebarCollapsed"
            )
            ===
            "true"
        ) {

            document.documentElement.setAttribute(
                "data-sidebar",
                "collapsed"
            );

        }

    }
    catch(e) {}

})();

</script>

</head>


<body>


<?php

include __DIR__ . "/../includes/staff_nav.php";

?>


<div class="container">


<?php

include __DIR__ . "/../includes/staff_topbar.php";

?>


<!-- =========================================================
     HEADER
     ========================================================= -->

<div class="welcome-header">

    <div>

        <h1>
            Edit Resident
        </h1>


        <p>

            <?= e(
                full_resident_name(
                    $resident
                )
            ) ?>

            &middot;

            <?= e(
                $resident[
                    "resident_number"
                ]
            ) ?>

        </p>

    </div>


    <div class="table-actions-res-edit">

        <a
            class="btn"
            href="resident_view.php?resident_id=<?= $resident_id ?>"
        >
            View / Print
        </a>


        <a
            class="btn"
            href="resident_management.php"
        >
            Back to List
        </a>

    </div>

</div>


<!-- =========================================================
     PROFILE PHOTO
     ========================================================= -->

<div class="card">

    <h2>
        Profile Photo
    </h2>


    <?php if (
        $photo_error
    ): ?>

        <div class="error">

            <?= e(
                $photo_error
            ) ?>

        </div>

    <?php endif; ?>


    <form
        method="POST"
        enctype="multipart/form-data"
        class="photo-uploader"
    >

        <input
            type="hidden"
            name="upload_photo"
            value="1"
        >


        <input
            type="hidden"
            name="resident_id"
            value="<?= $resident_id ?>"
        >


        <div class="avatar-lg">

            <?php if (
                $photo_url
            ): ?>

                <img
                    src="<?= e(
                        $photo_url
                    ) ?>"
                    alt="Resident photo"
                >

            <?php else: ?>

                <?= e(
                    $initials
                ) ?>

            <?php endif; ?>

        </div>


        <div>

            <input
                type="file"
                name="photo"
                accept="image/png, image/jpeg, image/webp"
            >


            <p
                style="
                    font-size:12px;
                    color:#667;
                    margin:6px 0 0;
                "
            >
                Passport-size photo.
                JPG, PNG, or WEBP, up to 3MB.
            </p>


            <button
                type="submit"
                class="btn btn-sm"
                style="margin-top:10px;"
            >
                Upload Photo
            </button>

        </div>

    </form>

</div>


<!-- =========================================================
     RESIDENT INFORMATION
     ========================================================= -->

<div class="card">

    <h2>
        Resident Information
    </h2>


    <?php if (
        $success
    ): ?>

        <div class="success">

            <?= e(
                $success
            ) ?>

        </div>

    <?php endif; ?>


    <?php if (
        $error
    ): ?>

        <div class="error">

            <?= e(
                $error
            ) ?>

        </div>

    <?php endif; ?>


    <form
        method="POST"
        id="residentEditForm"
    >

        <input
            type="hidden"
            name="update_resident"
            value="1"
        >


        <input
            type="hidden"
            name="resident_id"
            value="<?= $resident_id ?>"
        >


        <!-- =================================================
             PERSONAL INFORMATION
             ================================================= -->

        <div class="form-section-title">
            Personal Information
        </div>


        <div class="field-grid-2">


            <div class="register-field">

                <label>
                    Resident Number
                </label>


                <input
                    type="text"
                    name="resident_number"
                    required
                    value="<?= e(
                        $resident[
                            "resident_number"
                        ]
                    ) ?>"
                >

            </div>


            <div class="register-field">

                <label>
                    Civil Status
                </label>


                <select
                    name="civil_status"
                >

                    <option value="">
                        -- Select --
                    </option>


                    <?php foreach (
                        $civil_statuses
                        as $cs
                    ): ?>

                        <option
                            value="<?= e($cs) ?>"
                            <?= (
                                $resident[
                                    "civil_status"
                                ]
                                ===
                                $cs
                            )
                                ? "selected"
                                : ""
                            ?>
                        >

                            <?= e(
                                $cs
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="register-field">

                <label>
                    First Name
                </label>


                <input
                    type="text"
                    name="first_name"
                    required
                    value="<?= e(
                        $resident[
                            "first_name"
                        ]
                    ) ?>"
                >

            </div>


            <div class="register-field">

                <label>
                    Middle Name
                </label>


                <input
                    type="text"
                    name="middle_name"
                    value="<?= e(
                        $resident[
                            "middle_name"
                        ]
                    ) ?>"
                >

            </div>


            <div class="register-field">

                <label>
                    Last Name
                </label>


                <input
                    type="text"
                    name="last_name"
                    required
                    value="<?= e(
                        $resident[
                            "last_name"
                        ]
                    ) ?>"
                >

            </div>


            <div class="register-field">

                <label>
                    Extension Name
                </label>


                <input
                    type="text"
                    name="extension_name"
                    value="<?= e(
                        $resident[
                            "extension_name"
                        ]
                    ) ?>"
                    placeholder="Jr., Sr., III, etc."
                >

            </div>


            <div class="register-field">

                <label>
                    Gender
                </label>


                <select
                    name="gender"
                >

                    <option value="">
                        -- Select --
                    </option>


                    <option
                        value="Male"
                        <?= (
                            ($resident["gender"] ?? "")
                            ===
                            "Male"
                        )
                            ? "selected"
                            : ""
                        ?>
                    >
                        Male
                    </option>


                    <option
                        value="Female"
                        <?= (
                            ($resident["gender"] ?? "")
                            ===
                            "Female"
                        )
                            ? "selected"
                            : ""
                        ?>
                    >
                        Female
                    </option>

                </select>

            </div>


            <div class="register-field">

                <label>
                    Email
                </label>


                <input
                    type="email"
                    name="email"
                    value="<?= e(
                        $resident[
                            "email"
                        ]
                    ) ?>"
                >

            </div>


            <div class="register-field">

                <label>
                    Contact Number
                </label>


                <input
                    type="text"
                    name="contact_number"
                    maxlength="11"
                    inputmode="numeric"
                    value="<?= e(
                        $resident[
                            "contact_number"
                        ]
                    ) ?>"
                >

            </div>


            <div class="register-field">

                <label>
                    Birthday
                </label>


                <input
                    type="date"
                    name="birthday"
                    id="birthdayInput"
                    value="<?= e(
                        $resident[
                            "birthday"
                        ]
                    ) ?>"
                    max="<?= date(
                        "Y-m-d"
                    ) ?>"
                >

            </div>


            <div class="register-field">

                <label>
                    Age
                </label>


                <input
                    type="text"
                    id="ageDisplay"
                    value="<?=
                        $resident["age"] !== null
                            ? e(
                                $resident["age"]
                            )
                            : ""
                    ?>"
                    disabled
                    placeholder="Auto-computed from birthday"
                >

            </div>


            <div class="register-field">

                <label>
                    Occupation
                </label>


                <input
                    type="text"
                    name="occupation"
                    value="<?= e(
                        $resident[
                            "occupation"
                        ]
                    ) ?>"
                >

            </div>


            <div class="register-field">

                <label>
                    Employer
                </label>


                <input
                    type="text"
                    name="employer"
                    value="<?= e(
                        $resident[
                            "employer"
                        ]
                    ) ?>"
                >

            </div>


            <div class="register-field span-2">

                <label>
                    Employer Address
                </label>


                <input
                    type="text"
                    name="employer_address"
                    value="<?= e(
                        $resident[
                            "employer_address"
                        ]
                    ) ?>"
                >

            </div>


            <div class="register-field span-2">

                <label>
                    Address
                </label>


                <input
                    type="text"
                    name="address"
                    value="<?= e(
                        $resident[
                            "address"
                        ]
                    ) ?>"
                >

            </div>

        </div>


        <!-- =================================================
             PARENTS
             ================================================= -->

        <div class="form-section-title">
            Parents
        </div>


        <div class="field-grid-2">


            <div class="register-field">

                <label>
                    Father's Name
                </label>


                <input
                    type="text"
                    name="father_name"
                    value="<?= e(
                        $parents[
                            "father_name"
                        ]
                    ) ?>"
                >

            </div>


            <div class="register-field">

                <label>
                    Mother's Name
                </label>


                <input
                    type="text"
                    name="mother_name"
                    value="<?= e(
                        $parents[
                            "mother_name"
                        ]
                    ) ?>"
                >

            </div>

        </div>


        <!-- =================================================
             SPOUSE
             ================================================= -->

        <div class="form-section-title">
            Spouse Information
        </div>


        <div class="field-grid-2">


            <div class="register-field">

                <label>
                    Spouse Name
                </label>


                <input
                    type="text"
                    name="spouse_name"
                    value="<?= e(
                        $spouse[
                            "spouse_name"
                        ]
                    ) ?>"
                >

            </div>


            <div class="register-field">

                <label>
                    Occupation
                </label>


                <input
                    type="text"
                    name="spouse_occupation"
                    value="<?= e(
                        $spouse[
                            "occupation"
                        ]
                    ) ?>"
                >

            </div>


            <div class="register-field">

                <label>
                    Employer
                </label>


                <input
                    type="text"
                    name="spouse_employer"
                    value="<?= e(
                        $spouse[
                            "employer"
                        ]
                    ) ?>"
                >

            </div>

        </div>


        <!-- =================================================
             CHARACTER REFERENCES
             ================================================= -->

        <div class="form-section-title">
            Character References
        </div>


        <div class="field-grid-2">


            <div class="register-field">

                <label>
                    Reference 1 - Name
                </label>


                <input
                    type="text"
                    name="reference1_name"
                    value="<?= e(
                        $references[0][
                            "reference_name"
                        ]
                        ??
                        ""
                    ) ?>"
                >

            </div>


            <div class="register-field">

                <label>
                    Reference 1 - Signature
                </label>


                <input
                    type="text"
                    name="reference1_signature"
                    value="<?= e(
                        $references[0][
                            "signature"
                        ]
                        ??
                        ""
                    ) ?>"
                    placeholder="Optional"
                >

            </div>


            <div class="register-field">

                <label>
                    Reference 2 - Name
                </label>


                <input
                    type="text"
                    name="reference2_name"
                    value="<?= e(
                        $references[1][
                            "reference_name"
                        ]
                        ??
                        ""
                    ) ?>"
                >

            </div>


            <div class="register-field">

                <label>
                    Reference 2 - Signature
                </label>


                <input
                    type="text"
                    name="reference2_signature"
                    value="<?= e(
                        $references[1][
                            "signature"
                        ]
                        ??
                        ""
                    ) ?>"
                    placeholder="Optional"
                >

            </div>

        </div>


                <button
            type="submit"
            style="margin-top:20px;"
        >
            Save Changes
        </button>


        <button
            type="button"
            class="btn btn-secondary"
            onclick="openConfirmModal({
                url: 'resident_management.php',
                title: 'Discard these changes?',
                message: 'Any edits you made to this survey will be lost.',
                confirmLabel: 'Discard',
                danger: true
            })"
        >
            Cancel
        </button>

    </form>

</div>


<!-- =========================================================
     CHILDREN
     ========================================================= -->

<div class="card">

    <h2>
        Children
    </h2>


    <?php if (
        $child_error
    ): ?>

        <div class="error">

            <?= e(
                $child_error
            ) ?>

        </div>

    <?php endif; ?>


    <div class="table-scroll">

        <table>

            <tr>

                <th>
                    Child Name
                </th>


                <th>
                    Age
                </th>


                <th>
                    Action
                </th>

            </tr>


            <?php if (
                count($children) === 0
            ): ?>

                <tr>

                    <td
                        colspan="3"
                        style="
                            text-align:center;
                            color:#889;
                        "
                    >
                        No children on record.
                    </td>

                </tr>

            <?php else: ?>


                <?php foreach (
                    $children
                    as $c
                ): ?>

                    <tr>

                        <td>

                            <?= e(
                                $c[
                                    "child_name"
                                ]
                            ) ?>

                        </td>


                        <td>

                            <?=

                                $c["age"] !== null

                                    ? e(
                                        $c["age"]
                                    )

                                    : "&mdash;"

                            ?>

                        </td>


                        <td>

                            <a
                                href="resident_edit.php?resident_id=<?= $resident_id ?>&delete_child=<?= (int)$c["child_id"] ?>"
                                class="btn btn-sm btn-danger"
                                onclick="return confirm('Remove this child?');"
                            >
                                Remove
                            </a>

                        </td>

                    </tr>

                <?php endforeach; ?>


            <?php endif; ?>

        </table>

    </div>


    <form
        method="POST"
        class="children-add-row"
    >

        <input
            type="hidden"
            name="add_child"
            value="1"
        >


        <input
            type="hidden"
            name="resident_id"
            value="<?= $resident_id ?>"
        >


        <div class="register-field">

            <label>
                Child Name
            </label>


            <input
                type="text"
                name="child_name"
                placeholder="Full name"
                required
            >

        </div>


        <div class="register-field age-field">

            <label>
                Age
            </label>


            <input
                type="number"
                name="child_age"
                min="0"
                max="120"
            >

        </div>


        <button
            type="submit"
            class="btn btn-sm"
        >
            + Add Child
        </button>

    </form>

</div>


</div>


<!-- =========================================================
     CONFIRMATION MODAL
     =========================================================
     
     IMPORTANT:
     This modal is intentionally OUTSIDE all cards and forms.
     Keeping it directly under <body> allows the fixed overlay
     and backdrop-filter to cover the entire viewport.
     ========================================================= -->

<div
    class="modal-overlay"
    id="confirmModal"
>

    <div class="modal-box modal-sm">

        <div
            class="modal-icon"
            id="confirmModalIcon"
        ></div>


        <h3
            id="confirmModalTitle"
        >
            Are you sure?
        </h3>


        <p
            class="modal-message"
            id="confirmModalMessage"
        ></p>


        <div class="modal-actions">

            <button
                type="button"
                class="btn btn-secondary"
                onclick="closeConfirmModal()"
            >
                Cancel
            </button>


            <button
                type="button"
                class="btn btn-danger"
                id="confirmModalConfirmBtn"
                onclick="proceedConfirmModal()"
            >
                Confirm
            </button>

        </div>

    </div>

</div>


<script src="../assets/js/script.js"></script>

<script>
// Confirm before actually saving the resident's information
const residentEditForm = document.getElementById("residentEditForm");
if (residentEditForm) {
    residentEditForm.addEventListener("submit", function (e) {
        e.preventDefault();
        openConfirmModal({
            title: "Save these changes?",
            message: "This will update the resident's information.",
            confirmLabel: "Save Changes",
            danger: false,
            confirmClass: "",
            onConfirm: () => residentEditForm.submit()
        });
    });
}
</script>

<script>

document.addEventListener(
    "DOMContentLoaded",
    function () {

        const birthdayInput =
            document.getElementById(
                "birthdayInput"
            );


        const ageDisplay =
            document.getElementById(
                "ageDisplay"
            );


        function recomputeAge() {

            if (
                !birthdayInput.value
            ) {

                ageDisplay.value =
                    "";

                return;

            }


            const birthDate =
                new Date(
                    birthdayInput.value
                );


            const today =
                new Date();


            let age =
                today.getFullYear()
                -
                birthDate.getFullYear();


            const monthDifference =
                today.getMonth()
                -
                birthDate.getMonth();


            if (
                monthDifference < 0
                ||
                (
                    monthDifference === 0
                    &&
                    today.getDate()
                    <
                    birthDate.getDate()
                )
            ) {

                age--;

            }


            ageDisplay.value =
                age >= 0
                    ? age
                    : "";

        }


        if (
            birthdayInput
        ) {

            birthdayInput.addEventListener(
                "change",
                recomputeAge
            );


            birthdayInput.addEventListener(
                "input",
                recomputeAge
            );

        }

    }
);

</script>


</body>

</html>