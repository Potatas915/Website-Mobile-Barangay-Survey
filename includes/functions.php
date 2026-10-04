<?php
date_default_timezone_set("Asia/Manila");
session_start();
require_once __DIR__ . "/firebase.php";

// Redirect helper
function redirect($url) {
    header("Location: " . $url);
    exit();
}

// Guard: only logged-in residents can view this page
function require_resident_login() {
    if (!isset($_SESSION["resident_id"])) {
        redirect("../resident/login.php");
    }
}

// Guard: only logged-in staff can view this page
function require_staff_login() {
    if (!isset($_SESSION["staff_id"])) {
        redirect("../staff/login.php");
    }
}

// Escape output to prevent XSS when printing user data in HTML
function e($string) {
    return htmlspecialchars($string ?? "", ENT_QUOTES, "UTF-8");
}

// Backend guard: a survey start date can never be earlier than today,
// even if the date picker on the frontend gets bypassed.
function is_valid_start_date($start_date) {
    $today = date("Y-m-d");
    return $start_date >= $today;
}

// ---------------------------------------------------------
// Dashboard helpers: everything below reads real rows from the
// database (using created_at / submitted_at / start_date / end_date)
// so the "vs last month" trends and sparklines on the staff
// dashboard reflect actual data, not placeholders.
// ---------------------------------------------------------

// [start, end, label] for the calendar month that is $monthsAgo months
// before the current one. monthsAgo = 0 is the current (partial) month.
function month_bounds($monthsAgo) {
    $start = new DateTime("first day of -$monthsAgo month");
    $end = ($monthsAgo === 0) ? new DateTime() : new DateTime("last day of -$monthsAgo month");
    return [$start->format("Y-m-d 00:00:00"), $end->format("Y-m-d 23:59:59"), $start->format("M Y")];
}

// Percentage change between two counts, safe against division by zero.
function pct_change($current, $previous) {
    if ($previous <= 0) {
        return ["pct" => $current > 0 ? 100 : 0, "up" => $current >= $previous];
    }
    $pct = (($current - $previous) / $previous) * 100;
    return ["pct" => round(abs($pct), 1), "up" => $pct >= 0];
}

// ---------------------------------------------------------
// Rows: Firebase has no schema and fills in no defaults, so a row
// written by the site carries every column of the old table itself.
// That keeps a newly registered resident the same shape as the ones
// in the seed data, so pages never have to guess whether a key exists.
// ---------------------------------------------------------

// A residents row with the same defaults MySQL used to supply, with the
// given fields applied on top.
function blank_resident_row(array $overrides = array()) {
    $now = date("Y-m-d H:i:s");
    $row = array(
        "resident_number"     => "",
        "first_name"          => "",
        "last_name"           => "",
        "middle_name"         => "",
        "extension_name"      => "",
        "gender"              => "",
        "civil_status"        => "",
        "email"               => "",
        "contact_number"      => "",
        "address"             => "",
        "birthday"            => "",
        "age"                 => "",
        "occupation"          => "",
        "employer"            => "",
        "employer_address"    => "",
        "father_name"         => "",
        "mother_name"         => "",
        "spouse_name"         => "",
        "spouse_occupation"   => "",
        "spouse_employer"     => "",
        "reference1_name"     => "",
        "reference1_signature" => "",
        "reference2_name"     => "",
        "reference2_signature" => "",
        "photo"               => "",
        "status"              => "active",
        "password"            => "",
        "is_first_login"      => 1,
        "mobile_pin"          => "",
        "created_at"          => $now,
        "updated_at"          => $now,
    );
    return array_merge($row, $overrides);
}

// Writes a resident row and points lookup/resident_number at it, which is
// what the login page reads.
function fb_insert_resident(array $row) {
    $id = fb_insert("residents", $row);
    fb_index_set("resident_number", (string)$row["resident_number"], $id);
    return $id;
}

// Re-points lookup/resident_number after a Resident Number changes.
function fb_update_resident_number($old_number, $new_number, $id) {
    if ((string)$old_number !== (string)$new_number) {
        fb_index_remove("resident_number", $old_number);
    }
    fb_index_set("resident_number", $new_number, $id);
}

// ---------------------------------------------------------
// Act 5 - Set A helpers: resident personal information,
// photo upload, and formatting.
// ---------------------------------------------------------

// Computes a whole-number age from a Y-m-d birthday string.
// Returns null if no valid birthday is given.
function calculate_age($birthday) {
    if (!$birthday) return null;
    try {
        $bday = new DateTime($birthday);
        $today = new DateTime();
        if ($bday > $today) return null;
        return $today->diff($bday)->y;
    } catch (Exception $e) {
        return null;
    }
}

// Builds a resident's full name from name parts, skipping empty ones.
function full_resident_name($r) {
    $parts = [
        $r["first_name"] ?? "",
        $r["middle_name"] ?? "",
        $r["last_name"] ?? "",
    ];
    $name = trim(preg_replace("/\s+/", " ", implode(" ", array_filter($parts, fn($p) => trim((string)$p) !== ""))));
    if (!empty($r["extension_name"])) {
        $name .= " " . $r["extension_name"];
    }
    return $name;
}

// Folder (on disk) where resident photos are stored, and the folder's
// URL relative to any page under resident/ or staff/ (both are one
// level below the project root, so the same relative path works for
// either side of the portal).
define("RESIDENT_PHOTO_DIR", __DIR__ . "/../assets/uploads/residents/");
define("RESIDENT_PHOTO_URL", "../assets/uploads/residents/");

// Returns the browser-facing URL for a resident's photo, or null if
// they haven't uploaded one (callers can fall back to a placeholder).
function resident_photo_url($photo) {
    if (empty($photo)) return null;
    return RESIDENT_PHOTO_URL . rawurlencode($photo);
}

// Validates and stores an uploaded passport-size photo for a resident.
// Returns [filename, null] on success or [null, "error message"] on failure.
function save_resident_photo($resident_id, $file) {
    if (!isset($file) || $file["error"] === UPLOAD_ERR_NO_FILE) {
        return [null, null]; // nothing submitted, not an error
    }
    if ($file["error"] !== UPLOAD_ERR_OK) {
        return [null, "There was a problem uploading the photo. Please try again."];
    }

    $allowed = ["image/jpeg" => "jpg", "image/png" => "png", "image/webp" => "webp"];
    $mime = mime_content_type($file["tmp_name"]);
    if (!isset($allowed[$mime])) {
        return [null, "Photo must be a JPG, PNG, or WEBP image."];
    }
    if ($file["size"] > 3 * 1024 * 1024) {
        return [null, "Photo must be smaller than 3MB."];
    }

    if (!is_dir(RESIDENT_PHOTO_DIR)) {
        mkdir(RESIDENT_PHOTO_DIR, 0755, true);
    }

    $filename = "resident_" . (int)$resident_id . "_" . time() . "." . $allowed[$mime];
    if (!move_uploaded_file($file["tmp_name"], RESIDENT_PHOTO_DIR . $filename)) {
        return [null, "Unable to save the uploaded photo."];
    }

    return [$filename, null];
}

// Deletes a resident's stored photo file from disk (ignores missing files).
function delete_resident_photo($photo) {
    if (empty($photo)) return;
    $path = RESIDENT_PHOTO_DIR . $photo;
    if (is_file($path)) {
        @unlink($path);
    }
}

// Formats a Y-m-d date for display, or an em dash if empty.
function format_date_display($date) {
    if (empty($date)) return "&mdash;";
    try {
        return (new DateTime($date))->format("M d, Y");
    } catch (Exception $e) {
        return e($date);
    }
}

// Builds a small inline SVG sparkline from an array of numeric points.
function sparkline_svg($points, $width = 90, $height = 32, $color = "#1fae82") {
    $count = count($points);
    if ($count < 2) return "";
    $min = min($points);
    $max = max($points);
    $range = ($max - $min) ?: 1;
    $step = $width / ($count - 1);
    $coords = [];
    foreach ($points as $i => $p) {
        $x = round($i * $step, 1);
        $y = round($height - (($p - $min) / $range) * ($height - 4) - 2, 1);
        $coords[] = "$x,$y";
    }
    $lastX = ($count - 1) * $step;
    $lastY = round($height - (($points[$count - 1] - $min) / $range) * ($height - 4) - 2, 1);
    $polyline = implode(" ", $coords);
    return '<svg viewBox="0 0 ' . $width . ' ' . $height . '" class="sparkline" preserveAspectRatio="none">'
        . '<polyline points="' . $polyline . '" fill="none" stroke="' . $color . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>'
        . '<circle cx="' . $lastX . '" cy="' . $lastY . '" r="2.5" fill="' . $color . '"/>'
        . '</svg>';
}
?>  