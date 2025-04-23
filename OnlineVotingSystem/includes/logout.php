<?php

session_start();

// Unset all session variables related to the user
$_SESSION = array(); // Clear the session array

// If using session cookies, delete the cookie as well
// Note: This will destroy the session, not just the session data!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, // Set expiry in the past
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finally, destroy the session.
session_destroy();

// Redirect to the admin login page with a success message
// Use a query parameter to indicate successful logout
header("Location: ../login.php?logout=success");
exit();
?>
