<?php
require_once __DIR__ . "/../includes/functions.php";
require_staff_login();

// Reset a resident's password back to their resident number.
if (isset($_GET["reset_password"])) {
    $resident_id = (int)$_GET["reset_password"];

    $stmt = $conn->prepare("
        SELECT resident_number
        FROM residents
        WHERE resident_id = ?
    ");

    $stmt->bind_param("i", $resident_id);
    $stmt->execute();

    $row = $stmt->get_result()->fetch_assoc();

    if ($row) {
        $hashed = password_hash(
            $row["resident_number"],
            PASSWORD_DEFAULT
        );

        $update = $conn->prepare("
            UPDATE residents
            SET password = ?, is_first_login = 1
            WHERE resident_id = ?
        ");

        $update->bind_param(
            "si",
            $hashed,
            $resident_id
        );

        $update->execute();
    }

    redirect("resident_management.php?reset=1");
}


// Archive a resident: keep the record (and all their survey /
// update history) but hide them from the active list and block
// portal login, instead of deleting anything.
if (isset($_GET["archive"])) {
    $resident_id = (int)$_GET["archive"];

    $stmt = $conn->prepare("
        UPDATE residents
        SET status = 'archived'
        WHERE resident_id = ?
    ");

    $stmt->bind_param("i", $resident_id);
    $stmt->execute();

    redirect("resident_management.php?archived=1");
}


// Restore a previously archived resident back to active status.
if (isset($_GET["restore"])) {
    $resident_id = (int)$_GET["restore"];

    $stmt = $conn->prepare("
        UPDATE residents
        SET status = 'active'
        WHERE resident_id = ?
    ");

    $stmt->bind_param("i", $resident_id);
    $stmt->execute();

    redirect("resident_management.php?view=archived&restored=1");
}


$search = isset($_GET["search"])
    ? trim($_GET["search"])
    : "";

// Which list is showing: active residents (default) or archived ones.
$view = (isset($_GET["view"]) && $_GET["view"] === "archived")
    ? "archived"
    : "active";

$status_filter = $view === "archived" ? "archived" : "active";


if ($search !== "") {

    $like = "%" . $search . "%";

    $stmt = $conn->prepare("
        SELECT
            resident_id,
            resident_number,
            first_name,
            middle_name,
            last_name,
            extension_name,
            email,
            contact_number,
            updated_at
        FROM residents
        WHERE
            status = ?
            AND (
                resident_number LIKE ?
                OR first_name LIKE ?
                OR last_name LIKE ?
            )
        ORDER BY last_name
    ");

    $stmt->bind_param(
        "ssss",
        $status_filter,
        $like,
        $like,
        $like
    );

    $stmt->execute();

    $residents = $stmt->get_result();

} else {

    $stmt = $conn->prepare("
        SELECT
            resident_id,
            resident_number,
            first_name,
            middle_name,
            last_name,
            extension_name,
            email,
            contact_number,
            updated_at
        FROM residents
        WHERE status = ?
        ORDER BY last_name
    ");

    $stmt->bind_param("s", $status_filter);
    $stmt->execute();

    $residents = $stmt->get_result();
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<title>
    Resident Management
</title>

<link
    rel="stylesheet"
    href="../assets/css/style.css?v=<?= filemtime(__DIR__ . "/../assets/css/style.css") ?>"
>

<script>

(function () {

    var t =
        localStorage.getItem("theme");

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
| TOP HEADER ACTIONS ONLY
|--------------------------------------------------------------------------
|
| This styling affects ONLY the three buttons beside
| "Registered Residents".
|
| The action buttons inside each resident row are untouched.
|--------------------------------------------------------------------------
*/

.resident-page-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 20px;

    width: 100%;

    margin-bottom: 18px;

}


.resident-page-header h2 {

    margin: 0;

    flex-shrink: 0;

}


.resident-page-header .top-actions {

    display: flex;

    align-items: center;

    justify-content: flex-end;

    gap: 8px;

    flex-wrap: nowrap;

    white-space: nowrap;

    flex-shrink: 0;

}


.resident-page-header .top-actions .btn {

    white-space: nowrap;

    flex-shrink: 0;

}


/*
|--------------------------------------------------------------------------
| Responsive behavior
|--------------------------------------------------------------------------
*/

@media (max-width: 1050px) {

    .resident-page-header {

        align-items: flex-start;

        flex-direction: column;

    }

    .resident-page-header .top-actions {

        width: 100%;

        justify-content: flex-start;

        flex-wrap: wrap;

    }

}


@media (max-width: 600px) {

    .resident-page-header .top-actions {

        width: 100%;

        flex-direction: column;

        align-items: stretch;

    }

    .resident-page-header .top-actions .btn {

        width: 100%;

        text-align: center;

    }

}


/*
|--------------------------------------------------------------------------
| RESIDENT ROW ACTION BUTTONS (View / Edit / Reset Password / Archive)
|--------------------------------------------------------------------------
|
| Scoped to this page only via .resident-row-actions, so it can't
| affect the shared .table-actions grid used on other pages (which
| is sized for a 2-button layout). This row now has up to four
| buttons, so it wraps instead of forcing a fixed-width grid that
| the buttons no longer fit inside.
|--------------------------------------------------------------------------
*/

.resident-row-actions {

    display: flex;

    flex-wrap: wrap;

    align-items: center;

    gap: 8px;

    width: 100%;

    min-width: 220px;

    max-width: 320px;

}


.resident-row-actions .btn {

    width: auto;

    height: auto;

    margin: 0;

    padding: 6px 12px;

    font-size: 11.5px;

    text-align: center;

    white-space: nowrap;

    overflow: visible;

    text-overflow: unset;

    flex: 0 0 auto;

}


@media (max-width: 1100px) {

    .resident-row-actions {

        max-width: 100%;

    }

}


@media (max-width: 600px) {

    .resident-row-actions {

        flex-direction: column;

        align-items: stretch;

        min-width: 0;

    }

    .resident-row-actions .btn {

        width: 100%;

    }

}

</style>

</head>


<body>


<?php

include __DIR__ . "/../includes/staff_nav.php";

?>


<div class="container">


<?php

include __DIR__ . "/../includes/staff_topbar.php";

?>


<div class="card card-resident">


<!--
==========================================================================
REGISTERED RESIDENTS HEADER
==========================================================================
-->

<div class="resident-page-header">


    <h2>
        <?= $view === "archived"
            ? "Archived Residents"
            : "Registered Residents" ?>
    </h2>


    <!--
    ----------------------------------------------------------------------
    ONLY THESE THREE BUTTONS ARE BEING CHANGED
    ----------------------------------------------------------------------
    -->

    <div class="top-actions">

        <a
            class="btn"
            href="register.php"
        >
            + Register New Resident
        </a>

        <a
            class="btn"
            href="updated_records.php"
        >
            View Updated Records
        </a>


        <a
            class="btn"
            href="resident_export.php"
        >
            Export CSV
        </a>


        <?php if ($view === "archived"): ?>

            <a
                class="btn btn-secondary"
                href="resident_management.php"
            >
                Back to Active Residents
            </a>

        <?php else: ?>

            <a
                class="btn btn-secondary"
                href="resident_management.php?view=archived"
            >
                View Archived Residents
            </a>

        <?php endif; ?>

    </div>


</div>


<?php if (
    isset($_GET["reset"])
): ?>

    <div class="success">

        Resident's password has been
        reset to their Resident Number.

    </div>

<?php endif; ?>


<?php if (
    isset($_GET["archived"])
): ?>

    <div class="success">

        Resident has been archived. They
        will no longer appear in the active
        list and can't log in to the resident
        portal, but their record has been kept
        and can be restored anytime.

    </div>

<?php endif; ?>


<?php if (
    isset($_GET["restored"])
): ?>

    <div class="success">

        Resident has been restored and is
        active again.

    </div>

<?php endif; ?>


<form
    method="GET"
    style="margin-bottom:16px;"
>

    <?php if ($view === "archived"): ?>

        <input
            type="hidden"
            name="view"
            value="archived"
        >

    <?php endif; ?>

    <input
        type="text"
        name="search"
        placeholder="Search by name or resident number"
        value="<?= e($search) ?>"
    >

</form>


<div class="table-scroll">


<table>


<tr>

    <th>
        Resident Number
    </th>

    <th>
        Name
    </th>

    <th>
        Email
    </th>

    <th>
        Contact
    </th>

    <th>
        Last Updated
    </th>

    <th style="text-align: center;">
        Actions
    </th>

</tr>


<?php while (
    $r =
    $residents->fetch_assoc()
): ?>


<tr>


    <td>

        <?= e(
            $r["resident_number"]
        ) ?>

    </td>


    <td>

        <?= e(
            full_resident_name($r)
        ) ?>

    </td>


    <td>

        <?= e(
            $r["email"]
        ) ?>

    </td>


    <td>

        <?= e(
            $r["contact_number"]
        ) ?>

    </td>


    <td>

        <?=
            $r["updated_at"]
            ? e(
                date(
                    "M d, Y g:i A",
                    strtotime(
                        $r["updated_at"]
                    )
                )
            )
            : "&mdash;"
        ?>

    </td>


    <td>


        <!--
        ================================================================
        EXISTING RESIDENT ACTION BUTTONS
        ================================================================

        These are intentionally left exactly as they were.
        ================================================================
        -->


        <div class="table-actions">


            <a
                class="btn btn-sm btn-secondary"
                href="resident_view.php?resident_id=<?= (int)$r["resident_id"] ?>"
            >
                View
            </a>


            <a
                class="btn btn-sm btn-secondary"
                href="resident_edit.php?resident_id=<?= (int)$r["resident_id"] ?>"
            >
                Edit
            </a>


            <?php

            $resident_name_js =
                htmlspecialchars(
                    addslashes(
                        full_resident_name($r)
                    ),
                    ENT_QUOTES,
                    "UTF-8"
                );

            ?>


            <?php if ($view !== "archived"): ?>

                <button
                    type="button"
                    class="btn btn-sm btn-success-soft"
                    onclick="openConfirmModal({
                        url: 'resident_management.php?reset_password=<?= (int)$r["resident_id"] ?>',
                        title: 'Reset password?',
                        message: 'This will reset &quot;<?= $resident_name_js ?>&quot;\'s password back to their Resident Number and require them to change it on next login.',
                        confirmLabel: 'Reset Password',
                        danger: false
                    })"
                >
                    Reset Password
                </button>


                <button
                    type="button"
                    class="btn btn-sm btn-danger"
                    onclick="openConfirmModal({
                        url: 'resident_management.php?archive=<?= (int)$r["resident_id"] ?>',
                        title: 'Archive this resident?',
                        message: 'This will move &quot;<?= $resident_name_js ?>&quot; to the archived list and block their portal login. Their record is kept and can be restored anytime.',
                        confirmLabel: 'Archive',
                        danger: true
                    })"
                >
                    Archive
                </button>

            <?php else: ?>

                <button
                    type="button"
                    class="btn btn-sm btn-success-soft"
                    onclick="openConfirmModal({
                        url: 'resident_management.php?restore=<?= (int)$r["resident_id"] ?>',
                        title: 'Restore this resident?',
                        message: 'This will make &quot;<?= $resident_name_js ?>&quot; active again and restore their portal login access.',
                        confirmLabel: 'Restore',
                        danger: false
                    })"
                >
                    Restore
                </button>

            <?php endif; ?>


        </div>


    </td>


</tr>


<?php endwhile; ?>


</table>


</div>


</div>


</div>


<!--
==========================================================================
CONFIRMATION MODAL
==========================================================================
-->

<div
    class="modal-overlay"
    id="confirmModal"
>

    <div
        class="modal-box modal-sm"
    >

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


        <div
            class="modal-actions"
        >

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


</body>

</html>