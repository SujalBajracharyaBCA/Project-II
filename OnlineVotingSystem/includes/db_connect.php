<?php

// --- Database Configuration ---
// Replace with your actual database credentials

// Hostname (usually 'localhost' for XAMPP)
$db_host = 'localhost';

// Database Username (usually 'root' for XAMPP)
$db_user = 'root';

// Database Password (usually empty for default XAMPP installation)
$db_pass = '';

// Database Name (the one you created, e.g., 'voting_system')
$db_name = 'voting_system';

// --- Establish Database Connection using MySQLi (Object-Oriented) ---

// Create connection object
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// --- Check Connection ---
if ($conn->connect_error) {
    // Connection failed. Stop script execution.
    // In a production environment, log the error instead of displaying it to the user.
    // error_log("Database Connection Failed: " . $conn->connect_error);
    die("Database connection failed. Please check configuration or contact support.");
}

// --- Set Character Set (Recommended) ---
// Set the character set to utf8mb4 for better Unicode support
if (!$conn->set_charset("utf8mb4")) {
    // Optional: Log error if setting charset fails
    // error_log("Error loading character set utf8mb4: " . $conn->error);
    // You might choose to proceed or die depending on requirements
    // die("Error setting database character set.");
    // For simplicity here, we'll just note it might fail silently if not supported.
}

// --- Connection Successful ---
// The $conn object is now available for use in any script that includes this file.
// Example usage in another file:
// require_once 'includes/db_connect.php';
// $result = $conn->query("SELECT * FROM Users");
// ...
// $conn->close(); // Remember to close the connection when done in the main script

?>
