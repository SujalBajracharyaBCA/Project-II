<?php
// Start the session to store user data and messages
session_start();

// Include the database connection file
// IMPORTANT: Requires db_connect.php in the same directory
require_once 'db_connect.php';

// --- Form Data Retrieval ---
// Check if the form was submitted using POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Retrieve data, trim username
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    // --- Server-Side Validation ---
    if (empty($username) || empty($password)) {
        // Set error message and redirect back
        $_SESSION['login_message'] = "Username and Password are required.";
        header("Location: ../login.php");
        exit();
    }

    // --- Database Interaction ---
    // Prepare SQL statement to prevent SQL injection
    $sql = "SELECT UserID, Username, HashedPassword, Role, IsActive FROM Users WHERE Username = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result(); // Get the result set

        if ($result->num_rows === 1) {
            // User found, fetch the data
            $user = $result->fetch_assoc();

            // --- Password Verification ---
            if (password_verify($password, $user['HashedPassword'])) {
                // Password is correct

                // --- Check if account is active ---
                if ($user['IsActive']) {
                    // Account is active, proceed with login

                    // Regenerate session ID for security (prevents session fixation)
                    session_regenerate_id(true);

                    // --- Store User Information in Session ---
                    $_SESSION['user_id'] = $user['UserID'];
                    $_SESSION['username'] = $user['Username'];
                    $_SESSION['user_role'] = $user['Role']; // Store the role ('Voter' or 'Admin')

                    // --- Redirect based on Role ---
                    if ($user['Role'] === 'Admin') {
                        // Redirect to Admin Dashboard
                        header("Location: ../admin/dashboard.php"); // Adjust path as needed
                        exit();
                    } else {
                        // Redirect to Voter Dashboard
                        header("Location: ../dashboard.php"); // Adjust path as needed
                        exit();
                    }

                } else {
                    // Account is inactive
                    $_SESSION['login_message'] = "Your account is inactive. Please contact support.";
                    header("Location: ../login.php");
                    exit();
                }

            } else {
                // Password incorrect
                $_SESSION['login_message'] = "Invalid username or password.";
                header("Location: ../login.php");
                exit();
            }

        } else {
            // User not found
            $_SESSION['login_message'] = "Invalid username or password.";
            header("Location: ../login.php");
            exit();
        }

        // Close statement
        $stmt->close();

    } else {
        // Database prepare statement error
        $_SESSION['login_message'] = "Login failed due to a server error. Please try again later.";
        // Log the detailed error: error_log("Prepare failed (login): " . $conn->error);
        header("Location: ../login.php");
        exit();
    }

    // Close the database connection
    $conn->close();

} else {
    // If accessed directly without POST method, redirect to login page
    header("Location: ../login.php");
    exit();
}
?>
