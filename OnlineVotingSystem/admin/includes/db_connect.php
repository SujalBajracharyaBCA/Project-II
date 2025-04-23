<?php
// admin/includes/db_connect.php

// --- Database Configuration ---
// Replace with your actual database credentials
$db_host = 'localhost';     // Often 'localhost' or an IP address
$db_user = 'root';          // Your database username
$db_pass = '';              // Your database password
$db_name = 'voting_system'; // The name of your voting system database

// --- Establish Connection ---
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// --- Check Connection ---
if ($conn->connect_error) {
    // In a production environment, log the error instead of displaying it
    // error_log("Database Connection Failed: " . $conn->connect_error);

    // Display a generic error message to the user
    // You might want to redirect to an error page or show a message on the current page
    die("Database connection failed. Please contact the administrator or try again later.");
}

// --- Set Character Set (Recommended) ---
// Ensures proper handling of different characters
if (!$conn->set_charset("utf8mb4")) {
    // Log error if setting charset fails
    // error_log("Error loading character set utf8mb4: " . $conn->error);
    // Optionally, you could proceed without it, but it's better to fix the issue
}

// The $conn variable is now ready to be used by other PHP scripts that include this file.
?>
