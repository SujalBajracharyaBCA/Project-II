<?php
// Start the session to store messages
session_start();

// Include the database connection file
// IMPORTANT: Create this file and ensure it establishes a mysqli connection named $conn
// Example db_connect.php:
/*
<?php
$db_host = 'localhost'; // Usually localhost for XAMPP
$db_user = 'root';      // Default XAMPP username
$db_pass = '';          // Default XAMPP password (often empty)
$db_name = 'voting_system'; // Your database name

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    // Don't output detailed errors in production
    die("Database connection failed. Please try again later.");
    // Log detailed error: error_log("Connection failed: " . $conn->connect_error);
}
// Set character set (optional but recommended)
$conn->set_charset("utf8mb4");
?>
*/
require_once 'db_connect.php'; // Adjust path if necessary

// --- Form Data Retrieval and Basic Sanitization ---
// Check if the form was submitted using POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Retrieve data, trim whitespace
    $fullname = isset($_POST['fullname']) ? trim($_POST['fullname']) : '';
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $email    = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : ''; // Don't trim password itself
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

    // --- Server-Side Validation ---
    $errors = []; // Array to hold validation errors

    // 1. Check for empty required fields
    if (empty($fullname)) {
        $errors[] = "Full Name is required.";
    }
    if (empty($username)) {
        $errors[] = "Username is required.";
    }
    if (empty($email)) {
        $errors[] = "Email is required.";
    }
    if (empty($password)) {
        $errors[] = "Password is required.";
    }
    if (empty($confirm_password)) {
        $errors[] = "Confirm Password is required.";
    }

    // 2. Validate email format
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    // 3. Check if passwords match
    if (!empty($password) && !empty($confirm_password) && $password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    // 4. Check password length (example: minimum 6 characters)
    if (!empty($password) && strlen($password) < 6) {
         $errors[] = "Password must be at least 6 characters long.";
    }

    // --- Database Checks (Only if basic validation passes) ---
    if (empty($errors)) {
        // Use prepared statements to prevent SQL injection

        // 5. Check if username already exists
        $sql_check_username = "SELECT UserID FROM Users WHERE Username = ?";
        $stmt_check_username = $conn->prepare($sql_check_username);

        if ($stmt_check_username) {
            $stmt_check_username->bind_param("s", $username);
            $stmt_check_username->execute();
            $stmt_check_username->store_result(); // Needed to check num_rows

            if ($stmt_check_username->num_rows > 0) {
                $errors[] = "Username already taken. Please choose another.";
            }
            $stmt_check_username->close();
        } else {
            // Handle prepare statement error
            $errors[] = "Database error checking username. Please try again.";
            // Log the detailed error: error_log("Prepare failed (check_username): " . $conn->error);
        }


        // 6. Check if email already exists
        $sql_check_email = "SELECT UserID FROM Users WHERE Email = ?";
        $stmt_check_email = $conn->prepare($sql_check_email);

         if ($stmt_check_email) {
            $stmt_check_email->bind_param("s", $email);
            $stmt_check_email->execute();
            $stmt_check_email->store_result();

            if ($stmt_check_email->num_rows > 0) {
                $errors[] = "Email address already registered. Please use a different email or login.";
            }
            $stmt_check_email->close();
        } else {
             // Handle prepare statement error
            $errors[] = "Database error checking email. Please try again.";
             // Log the detailed error: error_log("Prepare failed (check_email): " . $conn->error);
        }

    }

    // --- Process Registration or Report Errors ---
    if (empty($errors)) {
        // All checks passed, proceed with registration

        // 7. Hash the password securely
        $hashed_password = password_hash($password, PASSWORD_DEFAULT); // Or PASSWORD_BCRYPT

        // 8. Insert new user into the database (assuming default role is 'Voter')
        $sql_insert_user = "INSERT INTO Users (Username, HashedPassword, Email, FullName, Role) VALUES (?, ?, ?, ?, 'Voter')";
        $stmt_insert_user = $conn->prepare($sql_insert_user);

        if ($stmt_insert_user) {
            $stmt_insert_user->bind_param("ssss", $username, $hashed_password, $email, $fullname);

            if ($stmt_insert_user->execute()) {
                // Registration successful
                $_SESSION['registration_message'] = "Registration successful! You can now log in.";
                $_SESSION['registration_status'] = "success";
            } else {
                // Execution failed
                $_SESSION['registration_message'] = "Registration failed due to a database error. Please try again.";
                $_SESSION['registration_status'] = "error";
                 // Log the detailed error: error_log("Execute failed (insert_user): " . $stmt_insert_user->error);
            }
            $stmt_insert_user->close();
        } else {
             // Prepare statement failed
            $_SESSION['registration_message'] = "Registration failed due to a server error. Please try again.";
            $_SESSION['registration_status'] = "error";
            // Log the detailed error: error_log("Prepare failed (insert_user): " . $conn->error);
        }

    } else {
        // Validation errors occurred
        // Store the first error message (or concatenate all) in the session
        $_SESSION['registration_message'] = implode("<br>", $errors); // Show all errors
        // $_SESSION['registration_message'] = $errors[0]; // Or just show the first error
        $_SESSION['registration_status'] = "error";
        // Optional: Store submitted values in session to repopulate form (more advanced)
        // $_SESSION['form_data'] = $_POST;
    }

    // Close the database connection
    $conn->close();

    // Redirect back to the registration page
    header("Location: ../register.php"); // Redirect to the form page in the parent directory
    exit(); // Important to prevent further script execution after redirection

} else {
    // If accessed directly without POST method, redirect to registration page or homepage
    header("Location: ../register.php");
    exit();
}
?>
