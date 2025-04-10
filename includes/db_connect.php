<?php

define("DB_HOST", "localhost"); 
define("DB_USER", "st_alphonsus_user"); 
define("DB_PASS", "112233"); 
define("DB_NAME", "st_alphonsus_db"); 


$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($mysqli->connect_error) {
    error_log("Database Connection Error: " . $mysqli->connect_error);
    // Display a generic error message to the user
    die("Error: Could not connect to the database. Please try again later.");
}

if (!$mysqli->set_charset("utf8mb4")) {
    error_log("Error loading character set utf8mb4: " . $mysqli->error);
}
?>
