<?php
// admin/includes/handle_login.php

// Start the session to store user data and messages
session_start();

// Include the database connection file
// Assumes db_connect.php is in the same directory
require_once 'db_connect.php';

// --- Form Data Retrieval ---
// Check if the form was submitted using POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Retrieve data, trim username
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    // --- Server-Side Validation ---
    if (empty($username) || empty($password)) {
        // Set error message and redirect back to admin login
        $_SESSION['login_message'] = "Username and Password are required.";
        header("Location: ../login.php"); // Redirect back to the admin login page
        exit();
    }

    // --- Database Interaction ---
    // Prepare SQL statement to prevent SQL injection
    // Select user only if their Role is 'Admin'
    $sql = "SELECT UserID, Username, HashedPassword, Role, IsActive FROM Users WHERE Username = ? AND Role = 'Admin'";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result(); // Get the result set

        if ($result->num_rows === 1) {
            // Admin user found, fetch the data
            $user = $result->fetch_assoc();

            // --- Password Verification ---
            if (password_verify($password, $user['HashedPassword'])) {
                // Password is correct

                // --- Check if account is active ---
                // You might have an IsActive flag for admins too
                if ($user['IsActive']) { // Assuming Admins also have an IsActive flag
                    // Account is active, proceed with login

                    // Regenerate session ID for security (prevents session fixation)
                    session_regenerate_id(true);

                    // --- Store User Information in Session ---
                    // Use the same session variables as voter login for consistency,
                    // but the role check ensures only admins get here.
                    $_SESSION['user_id'] = $user['UserID'];
                    $_SESSION['username'] = $user['Username'];
                    $_SESSION['user_role'] = $user['Role']; // Should always be 'Admin' here

                    // --- Redirect to Admin Dashboard ---
                    header("Location: ../dashboard.php"); // Redirect to the admin dashboard
                    exit();

                } else {
                    // Account is inactive
                    $_SESSION['login_message'] = "Your administrator account is inactive.";
                    header("Location: ../login.php");
                    exit();
                }

            } else {
                // Password incorrect
                $_SESSION['login_message'] = "Invalid admin username or password.";
                header("Location: ../login.php");
                exit();
            }

        } else {
            // Admin user not found (or username exists but isn't an Admin)
            $_SESSION['login_message'] = "Invalid admin username or password.";
            header("Location: ../login.php");
            exit();
        }

        // Close statement
        $stmt->close();

    } else {
        // Database prepare statement error
        $_SESSION['login_message'] = "Login failed due to a server error. Please try again later.";
        // Log the detailed error for the admin/developer:
        // error_log("Admin Login Prepare failed: " . $conn->error);
        header("Location: ../login.php");
        exit();
    }

    // Close the database connection
    $conn->close();

} else {
    // If accessed directly without POST method, redirect to admin login page
    header("Location: ../login.php");
    exit();
}
?>
