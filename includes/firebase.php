<?php
// Firebase Realtime Database access layer.
//
// Replaces the old mysqli connection. Everything the app used to get from
// SQL (COUNT, JOIN, GROUP BY, ORDER BY, LIMIT) is done in PHP here, on top
// of plain JSON read from one Firebase node.
//
// Nothing in this file talks to Firebase Auth: logins stay in PHP sessions
// and the bcrypt hashes live in the database under staff/ and residents/,
// exactly as they used to live in MySQL.

require_once __DIR__ . "/../config/firebase.php";

class FirebaseException extends RuntimeException {
}

// Every node fetched during this request, keyed by path. A page that reads
// the same node twenty times still makes one HTTP call. Writes clear it, so
// a value is never served stale after the app has changed it.
$GLOBALS["fb_cache"] = array();

function fb_forget() {
    $GLOBALS["fb_cache"] = array();
}

function fb_url($path) {
    $path = trim($path, "/");
    $url = $GLOBALS["firebase_database_url"] . "/" . $path . ".json";
    $token = trim((string)($GLOBALS["firebase_database_token"] ?? ""));
    if ($token !== "") {
        $url .= "?auth=" . rawurlencode($token);
    }
    return $url;
}

// One HTTP call. Returns the decoded body, or null where the path is empty
// (Firebase answers 204 with no body for some writes).
function fb_raw($method, $path, $body = null) {
    $url = fb_url($path);
    $json = $body === null ? null : json_encode($body);

    if (function_exists("curl_init")) {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
        ));
        if ($json !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
        }
        $raw    = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            throw new FirebaseException("Cannot reach Firebase: " . $error);
        }
    } else {
        // Fallback for hosts without the cURL extension.
        $opts = array("http" => array(
            "method"        => $method,
            "timeout"       => 20,
            "ignore_errors" => true,
            "header"        => "Content-Type: application/json",
        ));
        if ($json !== null) {
            $opts["http"]["content"] = $json;
        }
        $raw    = @file_get_contents($url, false, stream_context_create($opts));
        $status = isset($http_response_header[0]) ? (int)substr($http_response_header[0], -3) : 0;
        if ($raw === false) {
            throw new FirebaseException("Cannot reach Firebase at " . $url);
        }
    }

    if ($status >= 400) {
        throw new FirebaseException("Firebase rejected " . $method . " " . $path
            . " (HTTP $status): " . trim($raw));
    }

    return ($raw === "" || $raw === "null") ? null : fb_decode($raw);
}

// Firebase's REST API answers a node whose keys are the integers 1..n with a
// JSON *array* instead of an object, padded with a null at index 0:
//
//     PUT {"1":{"a":1},"2":{"b":2}}   ->   GET [null,{"a":1},{"b":2}]
//
// The stored data is fine, only the shape of the answer changes. Left alone
// that null becomes a phantom row, count() comes back one too high, and ids
// come back as 0-based array positions. It happens at every level of the
// answer, so the fix has to walk the whole thing. Firebase never stores a
// null (it deletes the key instead), so a leading null is always this padding
// and is safe to undo. Genuine 0-based lists are left alone.
function fb_decode($raw) {
    return fb_unpad(json_decode($raw, true));
}

function fb_unpad($data) {
    if (!is_array($data)) {
        return $data;
    }
    $keys  = array_keys($data);
    $count = count($data);
    if ($count > 0 && $keys === range(0, $count - 1) && $data[0] === null) {
        $rows = array();
        for ($i = 1; $i < $count; $i++) {
            $rows[$i] = fb_unpad($data[$i]);
        }
        return $rows;
    }
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $data[$key] = fb_unpad($value);
        }
    }
    return $data;
}

// Reads a node and returns whatever is there: an object as an array, a leaf
// (a counter, an index pointer) as its own scalar, a missing node as null.
// Use fb_node() when the answer is always a collection.
function fb_get($path) {
    return fb_raw("GET", $path);
}

// Reads a node that is always a collection. A missing node, or a leaf where
// a collection was expected, comes back as an empty array so callers never
// have to test for null.
function fb_node($path) {
    $data = fb_get($path);
    return is_array($data) ? $data : array();
}

// Reads a collection and forgets it was cached. Use this after a write that
// changes a node you have already read this request.
function fb_get_fresh($path) {
    fb_forget();
    return fb_get($path);
}

// A collection as an array of rows keyed by Firebase key, so a row's id is
// $row["id"] rather than a separate id column. The id is a string because
// PHP turns a numeric JSON key back into an int, and the ids that come back
// from lookup/ are strings; keeping both as strings avoids surprises when
// comparing an id from a row with an id from an index.
function fb_rows($path) {
    if (!array_key_exists($path, $GLOBALS["fb_cache"])) {
        $GLOBALS["fb_cache"][$path] = fb_node($path);
    }
    $rows = array();
    foreach ($GLOBALS["fb_cache"][$path] as $key => $row) {
        if (is_array($row)) {
            $row["id"] = (string)$key;
            $rows[$key] = $row;
        }
    }
    return $rows;
}

function fb_set($path, $data) {
    fb_forget();
    return fb_raw("PUT", $path, $data);
}

// Merges into a node, leaving keys that are not mentioned alone.
function fb_patch($path, $data) {
    fb_forget();
    return fb_raw("PATCH", $path, $data);
}

function fb_remove($path) {
    fb_forget();
    return fb_raw("DELETE", $path);
}

// Firebase has no AUTO_INCREMENT. counters/<collection> holds the highest
// id handed out so far, so the website keeps the same numeric ids the SQL
// used. This is a read followed by a write, so two staff registering at the
// same instant could take the same id; with one staff account that cannot
// happen in practice.
function fb_next_id($collection) {
    $next = (int)fb_get("counters/" . $collection) + 1;
    fb_raw("PUT", "counters/" . $collection, $next);
    return $next;
}

function fb_insert($collection, $row) {
    $id = fb_next_id($collection);
    fb_raw("PUT", $collection . "/" . $id, $row);
    return $id;
}

// Writes only the given fields onto an existing row.
function fb_update($collection, $id, $fields) {
    fb_forget();
    return fb_raw("PATCH", $collection . "/" . $id, $fields);
}

function fb_delete($collection, $id) {
    fb_forget();
    return fb_raw("DELETE", $collection . "/" . $id);
}

// Replaces a row outright, which is how the old "DELETE then INSERT" edits
// of a whole profile become a single write.
function fb_replace($collection, $id, $row) {
    fb_forget();
    return fb_raw("PUT", $collection . "/" . $id, $row);
}

// ---- Queries built out of the rows we already fetched ----

// The old "SELECT ... FROM t WHERE col = ?" with no index behind it.
function fb_find($collection, $field, $value) {
    foreach (fb_rows($collection) as $key => $row) {
        if (isset($row[$field]) && (string)$row[$field] === (string)$value) {
            return $row;
        }
    }
    return null;
}

function fb_find_all($collection, $field, $value) {
    $out = array();
    foreach (fb_rows($collection) as $key => $row) {
        if (isset($row[$field]) && (string)$row[$field] === (string)$value) {
            $out[$key] = $row;
        }
    }
    return $out;
}

// The old "LIKE '%term%'" over a few searchable columns.
function fb_search($collection, array $fields, $term) {
    $term = trim((string)$term);
    if ($term === "") {
        return fb_rows($collection);
    }
    $out = array();
    foreach (fb_rows($collection) as $key => $row) {
        foreach ($fields as $field) {
            if (isset($row[$field]) && stripos((string)$row[$field], $term) !== false) {
                $out[$key] = $row;
                break;
            }
        }
    }
    return $out;
}

function fb_count($collection) {
    return count(fb_rows($collection));
}

// Sorts a set of rows by one field. $desc flips the order. Values are
// compared as numbers when both sides look numeric, which is what MySQL
// would have done for a numeric column.
function fb_sort(array $rows, $field, $desc = false) {
    $numeric = true;
    foreach ($rows as $row) {
        if (isset($row[$field]) && $row[$field] !== "" && !is_numeric($row[$field])) {
            $numeric = false;
            break;
        }
    }
    uasort($rows, function ($a, $b) use ($field, $numeric) {
        $x = $a[$field] ?? "";
        $y = $b[$field] ?? "";
        if ($numeric) {
            $x = $x === "" ? -INF : $x + 0;
            $y = $y === "" ? -INF : $y + 0;
            return $x <=> $y;
        }
        return strcasecmp((string)$x, (string)$y);
    });
    return $desc ? array_reverse($rows, true) : $rows;
}

// ---- Secondary indexes ----
// Firebase only looks up a child by its own key, so anything the old
// UNIQUE columns were used for gets a lookup/ map pointing at the row id.
// Login by Resident Number hits this on every attempt.

function fb_index_set($name, $key, $id) {
    fb_raw("PUT", "lookup/" . $name . "/" . rawurlencode($key), $id);
}

function fb_index_remove($name, $key) {
    fb_raw("DELETE", "lookup/" . $name . "/" . rawurlencode($key));
}

function fb_index_find($name, $key) {
    $id = fb_get("lookup/" . $name . "/" . rawurlencode($key));
    return is_scalar($id) ? (string)$id : null;
}

// Resident Number -> resident row. Falls back to a scan when the index has
// not been built (an imported database from before the index existed).
function fb_resident_by_number($resident_number) {
    $id = fb_index_find("resident_number", $resident_number);
    if ($id !== null) {
        $row = fb_get("residents/" . $id);
        if (is_array($row) && isset($row["resident_number"])) {
            $row["id"] = $id;
            return $row;
        }
    }
    foreach (fb_rows("residents") as $key => $row) {
        if ((string)($row["resident_number"] ?? "") === (string)$resident_number) {
            return $row;
        }
    }
    return null;
}

function fb_staff_by_username($username) {
    $id = fb_index_find("staff_username", $username);
    if ($id !== null) {
        $row = fb_get("staff/" . $id);
        if (is_array($row) && isset($row["username"])) {
            $row["id"] = $id;
            return $row;
        }
    }
    foreach (fb_rows("staff") as $key => $row) {
        if ((string)($row["username"] ?? "") === (string)$username) {
            return $row;
        }
    }
    return null;
}

// ---- Errors ----
// Renders a Firebase failure as a page instead of a blank screen or a PHP
// warning, then stops. Callers wrap page work in fb_page() so one unhandled
// read or write cannot take the whole page down mid-render.
function fb_page(callable $work) {
    try {
        return $work();
    } catch (FirebaseException $ex) {
        http_response_code(503);
        echo "<!DOCTYPE html><html lang=\"en\"><head><meta charset=\"UTF-8\">"
            . "<title>Cannot reach the database</title>"
            . "<style>body{font-family:system-ui,sans-serif;background:#f6f7f9;color:#1f2933;"
            . "display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0}"
            . ".box{max-width:34rem;padding:2rem;background:#fff;border-radius:12px;"
            . "box-shadow:0 1px 3px rgba(31,41,51,.15)}h1{font-size:1.25rem;margin:0 0 .5rem}"
            . "p{margin:0 0 .75rem;line-height:1.6;color:#52606d}"
            . "code{background:#f0f4f8;padding:.15rem .35rem;border-radius:4px;font-size:.85em}"
            . "</style></head><body><div class=\"box\"><h1>Cannot reach the database</h1>"
            . "<p>The site could not talk to Firebase, so nothing was loaded or saved. "
            . "Check that the database URL in <code>config/firebase.php</code> is right, that you "
            . "are online, and that <code>firebase/database.rules.json</code> has been published.</p>"
            . "<p>Details: " . htmlspecialchars($ex->getMessage(), ENT_QUOTES, "UTF-8") . "</p>"
            . "</div></body></html>";
        exit;
    }
}
