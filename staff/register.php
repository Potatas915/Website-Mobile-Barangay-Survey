<?php
require_once __DIR__ . "/../includes/functions.php";
require_staff_login();

$error = "";
$success = "";

// Auto-generate the next resident number in YYYY-NNNN format, based on
// the highest sequence number already registered for the current year.
function getNextResidentNumber($conn)
{
    $year = date("Y");

    $stmt = $conn->prepare(
        "SELECT resident_number FROM residents WHERE resident_number LIKE ?"
    );
    $like = $year . "-%";
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $result = $stmt->get_result();

    $max = 0;
    while ($row = $result->fetch_assoc()) {
        $number = trim((string) $row["resident_number"]);
        if (preg_match("/^" . $year . "-(\d+)$/", $number, $m)) {
            $seq = (int) $m[1];
            if ($seq > $max) {
                $max = $seq;
            }
        }
    }

    $next = $max + 1;
    return $year . "-" . str_pad((string) $next, 4, "0", STR_PAD_LEFT);
}

$suggestedResidentNumber = getNextResidentNumber($conn);

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Resident number is auto-generated server-side (not taken from the
    // form) so it can't be edited or spoofed by the client, and so two
    // staff registering at the same time can't collide.
    $resident_number = getNextResidentNumber($conn);
    $first_name = trim($_POST["first_name"]);
    $last_name = trim($_POST["last_name"]);
    $gender = trim($_POST["gender"] ?? "");
    $email = trim($_POST["email"]);
    $contact_number = trim($_POST["contact_number"]);
    $address = trim($_POST["address"]);

    if ($first_name === "" || $last_name === "") {
        $error = "First name and last name are required.";
    }
    elseif (!preg_match("/^[A-Za-z\s\-']+$/", $first_name)) {
        $error = "First name contains invalid characters.";
    }
    elseif (!preg_match("/^[A-Za-z\s\-']+$/", $last_name)) {
        $error = "Last name contains invalid characters.";
    }
    elseif ($gender !== "" && !in_array($gender, ["Male", "Female"], true)) {
        $error = "Please select a valid gender.";
    }
    elseif ($contact_number !== "" && !preg_match("/^\d{11}$/", $contact_number)) {
        $error = "Contact number must contain exactly 11 digits.";
    }
    else {

        // Try a few times in case another registration grabbed the same
        // auto-generated number a split second earlier.
        $inserted = false;
        $attempts = 0;

        while (!$inserted && $attempts < 5) {
            $attempts++;

            $check = $conn->prepare(
                "SELECT resident_id FROM residents WHERE resident_number = ?"
            );
            $check->bind_param("s", $resident_number);
            $check->execute();

            if ($check->get_result()->num_rows > 0) {
                $resident_number = getNextResidentNumber($conn);
                continue;
            }

            // Per the resident portal rules, the default password is
            // always the resident's own Resident Number, and they must
            // change it the first time they log in.
            $hashed = password_hash($resident_number, PASSWORD_DEFAULT);
            $gender_sql = $gender !== "" ? $gender : null;

            $stmt = $conn->prepare("
                INSERT INTO residents
                (
                    resident_number,
                    first_name,
                    last_name,
                    gender,
                    email,
                    contact_number,
                    address,
                    password,
                    is_first_login
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");

            $stmt->bind_param(
                "ssssssss",
                $resident_number,
                $first_name,
                $last_name,
                $gender_sql,
                $email,
                $contact_number,
                $address,
                $hashed
            );

            if ($stmt->execute()) {
                $inserted = true;
                $success = "Resident account created successfully. Default password is their Resident Number (" . $resident_number . ").";
                $resident_number = $first_name = $last_name = $gender = $email = $contact_number = $address = "";
            } else {
                break;
            }
        }

        if (!$inserted && $error === "") {
            $error = "Unable to create account. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Register New Resident</title>
<link rel="stylesheet" href="../assets/css/style.css">
<script>(function(){var t=localStorage.getItem("theme");if(t==="dark")document.documentElement.setAttribute("data-theme","dark");})();</script>
<script>(function(){try{if(localStorage.getItem("sidebarCollapsed")==="true")document.documentElement.setAttribute("data-sidebar","collapsed");}catch(e){}})();</script>
</head>
<body>
<?php include __DIR__ . "/../includes/staff_nav.php"; ?>
<div class="container">
<?php include __DIR__ . "/../includes/staff_topbar.php"; ?>
    <div class="card">
        <h2>Register New Resident</h2>

        <?php if ($error): ?>
            <div class="error"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success"><?= e($success) ?></div>

        <?php endif; ?>

        <form method="POST" class="register-form">

            <div class="register-form-grid">
                <div class="register-field">
                    <label>Resident Number</label>
                    <input type="text" readonly value="<?= isset($resident_number) && $resident_number !== "" ? e($resident_number) : e($suggestedResidentNumber) ?>">
                </div>

                <div class="register-field">
                    <label>First Name</label>
                    <input type="text" name="first_name" required pattern="[A-Za-z\s\-']+" value="<?= isset($first_name) ? e($first_name) : "" ?>">
                </div>

                <div class="register-field">
                    <label>Last Name</label>
                    <input type="text" name="last_name" required pattern="[A-Za-z\s\-']+" value="<?= isset($last_name) ? e($last_name) : "" ?>">
                </div>

                <div class="register-field">
                    <label>Gender</label>
                    <select name="gender">
                        <option value="">-- Select --</option>
                        <option value="Male" <?= isset($gender) && $gender === "Male" ? "selected" : "" ?>>Male</option>
                        <option value="Female" <?= isset($gender) && $gender === "Female" ? "selected" : "" ?>>Female</option>
                    </select>
                </div>

                <div class="register-field">
                    <label>Email</label>
                    <input type="email" name="email" value="<?= isset($email) ? e($email) : "" ?>">
                </div>

                <div class="register-field">
                    <label>Contact Number</label>
                    <input type="text" name="contact_number" maxlength="11" inputmode="numeric" pattern="[0-9]{11}" value="<?= isset($contact_number) ? e($contact_number) : "" ?>" oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,11)">
                </div>

                <div class="register-field">
                    <label>Address</label>
                    <input type="text" name="address" value="<?= isset($address) ? e($address) : "" ?>">
                </div>
            </div>

            <p style="font-size:12.5px; color:#667; margin:14px 0 0;">
                Resident Number is auto-generated based on the last one registered this year and cannot be edited.
                The resident's default password will be set to their Resident Number, and they'll be required to
                change it the first time they log in. Full personal, family, and contact details (spouse, children,
                references, photo, etc.) can be filled in afterward from the resident's own Profile page, or from
                <a href="resident_management.php">Resident Management &rarr; Edit</a>.
            </p>

            <button type="submit" style="margin-top:16px;">Register</button>
            <a href="resident_management.php" class="btn btn-secondary">Cancel</a>

        </form>

       

    </div>
</div>
<script src="../assets/js/script.js"></script>
</body>
</html>