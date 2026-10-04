<?php

require_once __DIR__ . "/../includes/functions.php";

require_resident_login();


$resident_id =
    (string) $_SESSION["resident_id"];


$success = "";
$error = "";

$password_success = "";
$password_error = "";

$photo_success = "";
$photo_error = "";

$child_error = "";


/*
|--------------------------------------------------------------------------
| Helper: Get Resident
|--------------------------------------------------------------------------
*/

function get_profile_resident(
    $resident_id
) {

    $resident =
        fb_get(
            "residents/" .
            $resident_id
        );


    if (
        !is_array(
            $resident
        )
    ) {

        return null;

    }


    // Firebase keeps a field the site never wrote out of the row entirely,
    // where MySQL would have handed back NULL. Filling the blanks in here
    // means the form can read every field without testing each one.

    foreach (
        blank_resident_row()
        as $field =>
        $ignored
    ) {

        if (
            !array_key_exists(
                $field,
                $resident
            )
        ) {

            $resident[$field] = "";

        }

    }


    return $resident;

}


/*
|--------------------------------------------------------------------------
| Helper: Get Children As Array
|--------------------------------------------------------------------------
*/

function get_profile_children(
    $resident_id
) {

    $children = [];


    foreach (
        fb_rows(
            "residents/" .
            $resident_id .
            "/children"
        )
        as $child_id =>
        $row
    ) {

        $children[] = [

            "child_id" =>
                (int)$child_id,

            "child_name" =>
                $row["child_name"] ?? "",

            "age" =>
                $row["age"] ?? ""

        ];

    }


    // The old query ended with ORDER BY child_id ASC.
    usort(
        $children,
        function ($a, $b) {

            return $a["child_id"] <=>
                $b["child_id"];

        }
    );


    return $children;

}


/*
|--------------------------------------------------------------------------
| Helper: Build Resident Snapshot
|--------------------------------------------------------------------------
|
| IMPORTANT:
| Password is intentionally NOT included.
|
| The database history table stores:
|   old_data = BEFORE
|   new_data = AFTER
|
|--------------------------------------------------------------------------
*/

function build_profile_snapshot(
    $resident_id
) {

    $resident =
        get_profile_resident(
            $resident_id
        );


    if (!$resident) {

        return null;

    }


    $children =
        get_profile_children(
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


        "parents" => [

            "father_name" =>
                $resident["father_name"],

            "mother_name" =>
                $resident["mother_name"]

        ],


        "spouse" => [

            "spouse_name" =>
                $resident["spouse_name"],

            "occupation" =>
                $resident["spouse_occupation"],

            "employer" =>
                $resident["spouse_employer"]

        ],


        "references" => [

            [

                "reference_name" =>
                    $resident["reference1_name"],

                "signature" =>
                    $resident["reference1_signature"],

                "reference_order" =>
                    1

            ],

            [

                "reference_name" =>
                    $resident["reference2_name"],

                "signature" =>
                    $resident["reference2_signature"],

                "reference_order" =>
                    2

            ]

        ],


        "children" =>
            $clean_children

    ];

}


/*
|--------------------------------------------------------------------------
| Save Resident Update History
|--------------------------------------------------------------------------
*/

function save_profile_history(
    $resident_id,
    $old_snapshot,
    $new_snapshot,
    $section = "complete_profile",
    $updated_by_type = "resident",
    $updated_by_id = null
) {

    if (
        !$old_snapshot ||
        !$new_snapshot
    ) {

        return false;

    }


    $old_compare =
        json_encode(
            $old_snapshot,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );


    $new_compare =
        json_encode(
            $new_snapshot,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );


    /*
    |--------------------------------------------------------------------------
    | Do not create a history record when nothing changed.
    |--------------------------------------------------------------------------
    */

    if (
        $old_compare ===
        $new_compare
    ) {

        return true;

    }


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


    fb_insert(
        "resident_update_history",
        [

            "resident_id" =>
                (string)$resident_id,

            "updated_by_type" =>
                $updated_by_type,

            "updated_by_id" =>
                $updated_by_id === null
                    ? (string)$resident_id
                    : (string)$updated_by_id,

            "update_section" =>
                $section,

            "old_data" =>
                $old_data,

            "new_data" =>
                $new_data,

            "updated_at" =>
                date("Y-m-d H:i:s")

        ]
    );


    return true;

}


/*
|--------------------------------------------------------------------------
| Load Current Resident
|--------------------------------------------------------------------------
*/

$resident =
    fb_page(
        function () use ($resident_id) {

            return get_profile_resident(
                $resident_id
            );

        }
    );


if (!$resident) {

    redirect(
        "login.php"
    );

}


/*
|--------------------------------------------------------------------------
| Update Personal / Family / Reference Information
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    &&
    isset(
        $_POST["update_profile"]
    )
) {

    $first_name =
        trim(
            $_POST["first_name"]
            ??
            ""
        );


    $middle_name =
        trim(
            $_POST["middle_name"]
            ??
            ""
        );


    $last_name =
        trim(
            $_POST["last_name"]
            ??
            ""
        );


    $extension_name =
        trim(
            $_POST["extension_name"]
            ??
            ""
        );


    $civil_status =
        trim(
            $_POST["civil_status"]
            ??
            ""
        );


    $email =
        trim(
            $_POST["email"]
            ??
            ""
        );


    $contact_number =
        trim(
            $_POST["contact_number"]
            ??
            ""
        );


    $address =
        trim(
            $_POST["address"]
            ??
            ""
        );


    $birthday =
        trim(
            $_POST["birthday"]
            ??
            ""
        );


    $occupation =
        trim(
            $_POST["occupation"]
            ??
            ""
        );


    $employer =
        trim(
            $_POST["employer"]
            ??
            ""
        );


    $employer_address =
        trim(
            $_POST["employer_address"]
            ??
            ""
        );


    $father_name =
        trim(
            $_POST["father_name"]
            ??
            ""
        );


    $mother_name =
        trim(
            $_POST["mother_name"]
            ??
            ""
        );


    $spouse_name =
        trim(
            $_POST["spouse_name"]
            ??
            ""
        );


    $spouse_occupation =
        trim(
            $_POST["spouse_occupation"]
            ??
            ""
        );


    $spouse_employer =
        trim(
            $_POST["spouse_employer"]
            ??
            ""
        );


    $reference1_name =
        trim(
            $_POST["reference1_name"]
            ??
            ""
        );


    $reference1_signature =
        trim(
            $_POST["reference1_signature"]
            ??
            ""
        );


    $reference2_name =
        trim(
            $_POST["reference2_name"]
            ??
            ""
        );


    $reference2_signature =
        trim(
            $_POST["reference2_signature"]
            ??
            ""
        );


    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if (
        $first_name === ""
        ||
        $last_name === ""
    ) {

        $error =
            "First name and last name are required.";

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
    | Update Only When Validation Passed
    |--------------------------------------------------------------------------
    */

    if (
        $error === ""
    ) {

        try {

            /*
            |--------------------------------------------------------------------------
            | BEFORE
            |--------------------------------------------------------------------------
            */

            $old_snapshot =
                build_profile_snapshot(
                    $resident_id
                );


            /*
            |--------------------------------------------------------------------------
            | Calculate Age
            |--------------------------------------------------------------------------
            */

            $age =
                calculate_age(
                    $birthday
                );


            /*
            |--------------------------------------------------------------------------
            | UPDATE RESIDENT
            |--------------------------------------------------------------------------
            |
            | There is no transaction here: the resident row and the history
            | row are two separate writes, so a failure part way through can
            | leave one without the other. The history is written second and
            | reports itself as the failure, which is the safe order to retry
            | from.
            |
            |--------------------------------------------------------------------------
            */

            fb_update(
                "residents",
                $resident_id,
                [
                    "first_name" => $first_name,
                    "middle_name" => $middle_name,
                    "last_name" => $last_name,
                    "extension_name" => $extension_name,
                    "civil_status" => $civil_status,
                    "email" => $email,
                    "contact_number" => $contact_number,
                    "address" => $address,
                    "birthday" => $birthday,
                    "age" => $age === null ? "" : $age,
                    "occupation" => $occupation,
                    "employer" => $employer,
                    "employer_address" => $employer_address,
                    "father_name" => $father_name,
                    "mother_name" => $mother_name,
                    "spouse_name" => $spouse_name,
                    "spouse_occupation" => $spouse_occupation,
                    "spouse_employer" => $spouse_employer,
                    "reference1_name" => $reference1_name,
                    "reference1_signature" => $reference1_signature,
                    "reference2_name" => $reference2_name,
                    "reference2_signature" => $reference2_signature,
                    "updated_at" => date("Y-m-d H:i:s")
                ]
            );


            /*
            |--------------------------------------------------------------------------
            | AFTER
            |--------------------------------------------------------------------------
            */

            $new_snapshot =
                build_profile_snapshot(
                    $resident_id
                );


            /*
            |--------------------------------------------------------------------------
            | Save BEFORE / AFTER
            |--------------------------------------------------------------------------
            */

            save_profile_history(
                $resident_id,
                $old_snapshot,
                $new_snapshot,
                "complete_profile"
            );


            /*
            |--------------------------------------------------------------------------
            | Update Session Name
            |--------------------------------------------------------------------------
            */

            $_SESSION["resident_name"] =
                trim(
                    $first_name .
                    " " .
                    $last_name
                );


            $success =
                "Profile updated successfully.";


            /*
            |--------------------------------------------------------------------------
            | Reload Resident
            |--------------------------------------------------------------------------
            */

            $resident =
                get_profile_resident(
                    $resident_id
                );

        }
        catch (
            Throwable $exception
        ) {

            $error =
                "Unable to update profile. Please try again.";

        }

    }

}


/*
|--------------------------------------------------------------------------
| Upload / Replace Profile Photo
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
            $_FILES["photo"]
            ??
            null
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
            | BEFORE
            |--------------------------------------------------------------------------
            */

            $old_snapshot =
                build_profile_snapshot(
                    $resident_id
                );


            $old_photo =
                $resident["photo"] ?? "";


            /*
            |--------------------------------------------------------------------------
            | Update Photo
            |--------------------------------------------------------------------------
            */

            fb_update(
                "residents",
                $resident_id,
                [
                    "photo" => $new_photo,
                    "updated_at" => date("Y-m-d H:i:s")
                ]
            );


            /*
            |--------------------------------------------------------------------------
            | AFTER
            |--------------------------------------------------------------------------
            */

            $new_snapshot =
                build_profile_snapshot(
                    $resident_id
                );


            /*
            |--------------------------------------------------------------------------
            | Photo-specific history
            |--------------------------------------------------------------------------
            */

            $old_photo_data =
                json_encode(
                    [
                        "photo" =>
                            $old_photo
                    ],
                    JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES |
                    JSON_PRETTY_PRINT
                );


            $new_photo_data =
                json_encode(
                    [
                        "photo" =>
                            $new_photo
                    ],
                    JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES |
                    JSON_PRETTY_PRINT
                );


            if (
                $old_photo !==
                $new_photo
            ) {

                fb_insert(
                    "resident_update_history",
                    [
                        "resident_id" =>
                            (string)$resident_id,
                        "updated_by_type" =>
                            "resident",
                        "updated_by_id" =>
                            (string)$resident_id,
                        "update_section" =>
                            "photo",
                        "old_data" =>
                            $old_photo_data,
                        "new_data" =>
                            $new_photo_data,
                        "updated_at" =>
                            date("Y-m-d H:i:s")
                    ]
                );

            }


            /*
            |--------------------------------------------------------------------------
            | Delete old physical photo only after successful database update
            |--------------------------------------------------------------------------
            */

            if (
                $old_photo
            ) {

                delete_resident_photo(
                    $old_photo
                );

            }


            $photo_success =
                "Photo updated successfully.";


            $resident =
                get_profile_resident(
                    $resident_id
                );

        }
        catch (
            Throwable $exception
        ) {

            /*
            |--------------------------------------------------------------------------
            | Remove newly uploaded file if database update failed
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
| Add a Child
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
            ??
            ""
        );


    $child_age =
        trim(
            $_POST["child_age"]
            ??
            ""
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
                build_profile_snapshot(
                    $resident_id
                );


            /*
            |--------------------------------------------------------------------------
            | Insert Child
            |--------------------------------------------------------------------------
            |
            | Children live under their resident, so removing the resident
            | removes them too, which is what ON DELETE CASCADE did.
            |
            |--------------------------------------------------------------------------
            */

            fb_raw(
                "PUT",
                "residents/" .
                $resident_id .
                "/children/" .
                fb_next_id("resident_children"),
                [
                    "child_name" => $child_name,
                    "age" =>
                        $child_age === ""
                            ? ""
                            : (int)$child_age
                ]
            );

            fb_forget();


            /*
            |--------------------------------------------------------------------------
            | AFTER
            |--------------------------------------------------------------------------
            */

            $new_snapshot =
                build_profile_snapshot(
                    $resident_id
                );


            save_profile_history(
                $resident_id,
                $old_snapshot,
                $new_snapshot,
                "children"
            );


            $success =
                "Child added to your family information.";

        }
        catch (
            Throwable $exception
        ) {

            $child_error =
                "Unable to add child. Please try again.";

        }

    }

}


/*
|--------------------------------------------------------------------------
| Remove a Child
|--------------------------------------------------------------------------
*/

if (
    isset(
        $_GET["delete_child"]
    )
) {

    $child_id =
        (string)(int)
        $_GET["delete_child"];


    if (
        $child_id !== "0"
    ) {

        try {

            /*
            |--------------------------------------------------------------------------
            | BEFORE
            |--------------------------------------------------------------------------
            */

            $old_snapshot =
                build_profile_snapshot(
                    $resident_id
                );


            /*
            |--------------------------------------------------------------------------
            | Delete only this resident's child
            |--------------------------------------------------------------------------
            |
            | The child key sits under this resident, so a child id that
            | belongs to somebody else simply is not there.
            |
            |--------------------------------------------------------------------------
            */

            if (
                !fb_get(
                    "residents/" .
                    $resident_id .
                    "/children/" .
                    $child_id
                )
            ) {

                throw new Exception(
                    "Child record was not found."
                );

            }


            fb_remove(
                "residents/" .
                $resident_id .
                "/children/" .
                $child_id
            );


            /*
            |--------------------------------------------------------------------------
            | AFTER
            |--------------------------------------------------------------------------
            */

            $new_snapshot =
                build_profile_snapshot(
                    $resident_id
                );


            save_profile_history(
                $resident_id,
                $old_snapshot,
                $new_snapshot,
                "children"
            );


            redirect(
                "profile.php"
            );

        }
        catch (
            Throwable $exception
        ) {

            $child_error =
                "Unable to remove child. Please try again.";

        }

    }

}


/*
|--------------------------------------------------------------------------
| Change Password
|--------------------------------------------------------------------------
|
| Password is intentionally NOT saved into resident_update_history.
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    &&
    isset(
        $_POST["change_password"]
    )
) {

    $current_password =
        $_POST["current_password"]
        ??
        "";


    $new_password =
        $_POST["new_password"]
        ??
        "";


    $confirm_password =
        $_POST["confirm_password"]
        ??
        "";


    $row =
        get_profile_resident(
            $resident_id
        );


    if (
        !$row
    ) {

        $password_error =
            "Resident account could not be found.";

    }


    elseif (
        !hash_equals(
            (string)($row["password"] ?? ""),
            (string)$current_password
        )
    ) {

        $password_error =
            "Current password is incorrect.";

    }


    elseif (
        strlen(
            $new_password
        ) < 6
    ) {

        $password_error =
            "New password must be at least 6 characters.";

    }


    elseif (
        $new_password !==
        $confirm_password
    ) {

        $password_error =
            "New passwords do not match.";

    }


    else {

        try {

            fb_update(
                "residents",
                $resident_id,
                [
                    "password" =>
                        $new_password,
                    "updated_at" =>
                        date("Y-m-d H:i:s")
                ]
            );


            $password_success =
                "Password changed successfully.";

        }
        catch (
            Throwable $exception
        ) {

            $password_error =
                "Unable to update password.";

        }

    }

}


/*
|--------------------------------------------------------------------------
| Reload Resident
|--------------------------------------------------------------------------
*/

$resident =
    get_profile_resident(
        $resident_id
    );


/*
|--------------------------------------------------------------------------
| Load Children
|--------------------------------------------------------------------------
*/

$children =
    get_profile_children(
        $resident_id
    );


/*
|--------------------------------------------------------------------------
| Resident Initials / Photo
|--------------------------------------------------------------------------
*/

$resident_initials =
    strtoupper(
        substr(
            $resident["first_name"] ?? "",
            0,
            1
        )
        .
        substr(
            $resident["last_name"] ?? "",
            0,
            1
        )
    );


$photo_url =
    resident_photo_url(
        $resident["photo"] ?? ""
    );


$civil_statuses = [

    "Single",
    "Married",
    "Widowed",
    "Separated",
    "Divorced"

];


?><!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<title>
    My Profile
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


<body class="profile-page">


<?php

include __DIR__ . "/../includes/resident_nav.php";

?>


<div class="container">


<?php

include __DIR__ . "/../includes/resident_topbar.php";

?>


<div class="welcome-header">

    <div>

        <h1>
            My Profile
        </h1>


        <p>
            View and update your personal,
            family, and contact information.
        </p>

    </div>


    <button
        type="button"
        class="no-print"
        onclick="window.print()"
    >
        Print My Profile
    </button>

</div>


<!-- =========================================================
     PROFILE PHOTO
     ========================================================= -->

<div class="card">

    <h2>
        Profile Photo
    </h2>


    <?php if (
        $photo_success
    ): ?>

        <div class="success">

            <?= e(
                $photo_success
            ) ?>

        </div>

    <?php endif; ?>


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


        <div class="avatar-lg">

            <?php if (
                $photo_url
            ): ?>

                <img
                    src="<?= e(
                        $photo_url
                    ) ?>"
                    alt="Profile photo"
                >

            <?php else: ?>

                <?= e(
                    $resident_initials
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
                class="btn btn-sm no-print"
                style="margin-top:10px;"
            >
                Upload Photo
            </button>

        </div>

    </form>

</div>


<!-- =========================================================
     PERSONAL & FAMILY INFORMATION
     ========================================================= -->

<div class="card">

    <h2>
        Personal &amp; Family Information
    </h2>


    <div
        id="profileFeedback"
        aria-live="polite"
    >

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

    </div>


    <form
        method="POST"
        id="profileForm"
    >

        <input
            type="hidden"
            name="update_profile"
            value="1"
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
                    value="<?= e(
                        $resident["resident_number"]
                    ) ?>"
                    disabled
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
                    Last Name
                </label>


                <input
                    type="text"
                    name="last_name"
                    required
                    value="<?= e(
                        $resident["last_name"]
                    ) ?>"
                >

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
                        $resident["first_name"]
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
                        $resident["middle_name"]
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
                        $resident["extension_name"]
                    ) ?>"
                >

            </div>


            <div class="register-field">

                <label>
                    Email
                </label>


                <input
                    type="email"
                    name="email"
                    value="<?= e(
                        $resident["email"]
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
                    value="<?= e(
                        $resident["contact_number"]
                    ) ?>"
                    maxlength="11"
                    inputmode="numeric"
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
                        $resident["birthday"]
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
                        $resident["occupation"]
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
                        $resident["employer"]
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
                        $resident["employer_address"]
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
                        $resident["address"]
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
                        $resident["father_name"]
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
                        $resident["mother_name"]
                    ) ?>"
                >

            </div>

        </div>


        <!-- =================================================
             SPOUSE
             ================================================= -->

        <div class="form-section-title">

            Spouse Information

            <small
                style="
                    font-weight:400;
                    text-transform:none;
                "
            >
                (if applicable)
            </small>

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
                        $resident["spouse_name"]
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
                        $resident["spouse_occupation"]
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
                        $resident["spouse_employer"]
                    ) ?>"
                >

            </div>

        </div>


        <!-- =================================================
             REFERENCES
             ================================================= -->

        <div class="form-section-title">

            Character References

            <small
                style="
                    font-weight:400;
                    text-transform:none;
                "
            >
                (signature optional)
            </small>

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
                        $resident["reference1_name"]
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
                        $resident[
                            "reference1_signature"
                        ]
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
                        $resident["reference2_name"]
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
                        $resident[
                            "reference2_signature"
                        ]
                    ) ?>"
                    placeholder="Optional"
                >

            </div>

        </div>


        <div
            class="profile-form-actions"
        >

            <button
                type="submit"
                class="no-print"
            >
                Save Changes
            </button>

        </div>

    </form>


    <!-- =================================================
         CHILDREN
         ================================================= -->

    <div
        class="form-section-title no-print"
    >
        Children
    </div>


    <div
        class="children-table-wrap no-print"
    >

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

                    <th
                        class="no-print"
                    >
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
                            No children added yet.
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
                                    $c["child_name"]
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


                            <td
                                class="no-print"
                            >

                                <a
                                    href="profile.php?delete_child=<?= (int)$c["child_id"] ?>"
                                    class="btn btn-sm btn-danger"
                                    onclick="return confirm('Remove <?= e(addslashes($c["child_name"])) ?> from your children list?');"
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


    <!-- =================================================
         PRINT-ONLY CHILDREN
         ================================================= -->

    <div
        class="print-only"
        style="display:none;"
    >

        <div class="form-section-title">
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

                <tr>

                    <th>
                        Child Name
                    </th>

                    <th>
                        Age
                    </th>

                </tr>


                <?php foreach (
                    $children
                    as $c
                ): ?>

                    <tr>

                        <td>
                            <?= e(
                                $c["child_name"]
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

                    </tr>

                <?php endforeach; ?>

            </table>

        <?php endif; ?>

    </div>

</div>


<!-- =========================================================
     CHANGE PASSWORD
     ========================================================= -->

<div class="card">

    <h2>
        Change Password
    </h2>


    <div
        id="passwordFeedback"
        aria-live="polite"
    >

        <?php if (
            $password_success
        ): ?>

            <div class="success">

                <?= e(
                    $password_success
                ) ?>

            </div>

        <?php endif; ?>


        <?php if (
            $password_error
        ): ?>

            <div class="error">

                <?= e(
                    $password_error
                ) ?>

            </div>

        <?php endif; ?>

    </div>


    <form
        method="POST"
        id="passwordForm"
        class="profile-form"
    >

        <input
            type="hidden"
            name="change_password"
            value="1"
        >


        <div
            class="profile-fields password-fields"
        >

            <div class="profile-field">

                <label>
                    Current Password
                </label>


                <input
                    type="password"
                    name="current_password"
                    required
                >

            </div>


            <div class="profile-field">

                <label>
                    New Password
                </label>


                <input
                    type="password"
                    name="new_password"
                    required
                    minlength="6"
                >

            </div>


            <div class="profile-field">

                <label>
                    Confirm New Password
                </label>


                <input
                    type="password"
                    name="confirm_password"
                    required
                    minlength="6"
                >

            </div>

        </div>


        <div
            class="profile-form-actions"
        >

            <button
                type="submit"
                id="updatePasswordBtn"
            >
                Update Password
            </button>

        </div>

    </form>

</div>


</div>


<!-- =========================================================
     PASSWORD CONFIRMATION MODAL
     ========================================================= -->

<div
    class="modal-overlay"
    id="profileConfirmModal"
    aria-hidden="true"
>

    <div
        class="modal-box modal-sm"
        role="dialog"
        aria-modal="true"
        aria-labelledby="profileConfirmTitle"
    >

        <div
            class="modal-icon danger"
            id="profileConfirmIcon"
        >

            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
            >

                <polyline
                    points="20 6 9 17 4 12"
                ></polyline>

            </svg>

        </div>


        <h3
            id="profileConfirmTitle"
        >
            Confirm Password Change
        </h3>


        <p
            class="modal-message"
            id="profileConfirmMessage"
        ></p>


        <div
            class="modal-actions"
        >

            <button
                type="button"
                class="btn btn-secondary"
                id="profileConfirmCancel"
            >
                Cancel
            </button>


            <button
                type="button"
                class="btn btn-danger"
                id="profileConfirmProceed"
            >
                Confirm
            </button>

        </div>

    </div>

</div>


<script src="../assets/js/script.js"></script>


<script>

document.addEventListener(
    "DOMContentLoaded",
    function () {

        /*
        |--------------------------------------------------------------------------
        | Auto-compute displayed age
        |--------------------------------------------------------------------------
        */

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
                !birthdayInput ||
                !ageDisplay
            ) {

                return;

            }


            if (
                !birthdayInput.value
            ) {

                ageDisplay.value =
                    "";

                return;

            }


            const b =
                new Date(
                    birthdayInput.value
                );


            const today =
                new Date();


            let age =
                today.getFullYear()
                -
                b.getFullYear();


            const m =
                today.getMonth()
                -
                b.getMonth();


            if (
                m < 0
                ||
                (
                    m === 0
                    &&
                    today.getDate()
                    <
                    b.getDate()
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

        }


        /*
        |--------------------------------------------------------------------------
        | Password Confirmation Modal
        |--------------------------------------------------------------------------
        */

        const passwordForm =
            document.getElementById(
                "passwordForm"
            );


        const modal =
            document.getElementById(
                "profileConfirmModal"
            );


        const modalMessage =
            document.getElementById(
                "profileConfirmMessage"
            );


        const modalCancel =
            document.getElementById(
                "profileConfirmCancel"
            );


        const modalProceed =
            document.getElementById(
                "profileConfirmProceed"
            );


        function showFeedback(
            targetId,
            message,
            type
        ) {

            const target =
                document.getElementById(
                    targetId
                );


            if (
                target
            ) {

                target.innerHTML =
                    '<div class="' +
                    type +
                    '">' +
                    message +
                    '</div>';

            }

        }


        if (
            passwordForm &&
            modal
        ) {

            passwordForm.addEventListener(
                "submit",
                function(event) {

                    event.preventDefault();


                    if (
                        !passwordForm.checkValidity()
                    ) {

                        passwordForm.reportValidity();

                        return;

                    }


                    const newPassword =
                        passwordForm.querySelector(
                            "input[name='new_password']"
                        ).value;


                    const confirmPassword =
                        passwordForm.querySelector(
                            "input[name='confirm_password']"
                        ).value;


                    if (
                        newPassword !==
                        confirmPassword
                    ) {

                        showFeedback(
                            "passwordFeedback",
                            "New passwords do not match.",
                            "error"
                        );

                        return;

                    }


                    modalMessage.textContent =
                        "Are you sure you want to change your password? This action will update your account password.";


                    modal.classList.add(
                        "open"
                    );


                    modal.setAttribute(
                        "aria-hidden",
                        "false"
                    );

                }
            );


            modalCancel.addEventListener(
                "click",
                function() {

                    modal.classList.remove(
                        "open"
                    );


                    modal.setAttribute(
                        "aria-hidden",
                        "true"
                    );


                    showFeedback(
                        "passwordFeedback",
                        "Password change cancelled.",
                        "success"
                    );

                }
            );


            modalProceed.addEventListener(
                "click",
                function() {

                    modal.classList.remove(
                        "open"
                    );


                    modal.setAttribute(
                        "aria-hidden",
                        "true"
                    );


                    passwordForm.submit();

                }
            );


            modal.addEventListener(
                "click",
                function(event) {

                    if (
                        event.target ===
                        modal
                    ) {

                        modal.classList.remove(
                            "open"
                        );


                        modal.setAttribute(
                            "aria-hidden",
                            "true"
                        );

                    }

                }
            );

        }

    }
);

</script>


</body>

</html>