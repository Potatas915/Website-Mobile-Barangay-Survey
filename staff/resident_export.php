<?php
require_once __DIR__ . "/../includes/functions.php";
require_staff_login();

fb_page(function () use (&$residents) {

    // The old query ended with ORDER BY last_name.
    $residents = fb_sort(fb_rows("residents"), "last_name");

});

header("Content-Type: text/csv; charset=utf-8");
header("Content-Disposition: attachment; filename=barangay_residents_" . date("Y-m-d") . ".csv");

$out = fopen("php://output", "w");
fputcsv($out, [
    "Resident Number", "First Name", "Middle Name", "Last Name", "Extension",
    "Civil Status", "Birthday", "Age", "Contact Number", "Email", "Address",
    "Occupation", "Employer", "Employer Address",
    "Father's Name", "Mother's Name",
    "Spouse Name", "Spouse Occupation", "Spouse Employer",
    "Reference 1", "Reference 2", "Last Updated"
]);

foreach ($residents as $r) {
    fputcsv($out, [
        $r["resident_number"] ?? "", $r["first_name"] ?? "", $r["middle_name"] ?? "", $r["last_name"] ?? "", $r["extension_name"] ?? "",
        $r["civil_status"] ?? "", $r["birthday"] ?? "", $r["age"] ?? "", $r["contact_number"] ?? "", $r["email"] ?? "", $r["address"] ?? "",
        $r["occupation"] ?? "", $r["employer"] ?? "", $r["employer_address"] ?? "",
        $r["father_name"] ?? "", $r["mother_name"] ?? "",
        $r["spouse_name"] ?? "", $r["spouse_occupation"] ?? "", $r["spouse_employer"] ?? "",
        $r["reference1_name"] ?? "", $r["reference2_name"] ?? "", $r["updated_at"] ?? ""
    ]);
}
fclose($out);
exit();
