<?php
// Firebase Realtime Database connection settings.
//
// The database URL comes from the Firebase console: Realtime Database ->
// copy the "Database URL" at the top of the page. It must start with
// https:// and have no trailing slash.
//
// $firebase_database_token is only needed if you lock the database down
// (see firebase/database.rules.json). With the shipped open rules it can
// stay empty, because anonymous calls are already allowed. Never commit a
// real token: this file is served by Apache and is inside the project, so
// anyone with the project could read it.

$firebase_database_url = "https://web-to-mobile-2c582-default-rtdb.firebaseio.com";

$firebase_database_token = "";
?>
