<?php
session_start();
require_once 'db_connect.php'; // Adjust path if necessary

// --- Check Request Method ---
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../login.php"); // Redirect if accessed directly
    exit();
}

// --- Retrieve POST Data ---
$token = isset($_POST['token']) ? $_POST['token'] : '';
$new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
$confirm_new_password = isset($_POST['confirm_new_password']) ? $_POST['confirm_new_password'] : '';

// --- Basic Input Validation ---
if (empty($token) || empty($new_password) || empty($confirm_new_password)) {
    $_SESSION['reset_password_message'] = "All fields are required.";
    $_SESSION['reset_password_status'] = "error";
    header("Location: ../reset_password.php?token=" . urlencode($token)); // Redirect back with token
    exit();
}

// Check password length
if (strlen($new_password) < 6) {
    $_SESSION['reset_password_message'] = "Password must be at least 6 characters long.";
    $_SESSION['reset_password_status'] = "error";
    header("Location: ../reset_password.php?token=" . urlencode($token));
    exit();
}

// Check if passwords match
if ($new_password !== $confirm_new_password) {
    $_SESSION['reset_password_message'] = "Passwords do not match.";
    $_SESSION['reset_password_status'] = "error";
    header("Location: ../reset_password.php?token=" . urlencode($token));
    exit();
}

// --- Validate Token and Update Password ---
$conn->begin_transaction(); // Start transaction

try {
    // 1. Find the HASHED token in the database that is not used and not expired
    // We need to iterate through potential tokens as we only have the plain token from the user
    $sql_find_token = "SELECT ResetID, UserID, HashedToken FROM PasswordResets
                       WHERE ExpiresAt > NOW() AND IsUsed = FALSE";
    $result_tokens = $conn->query($sql_find_token); // No user input, direct query okay

    $valid_token_found = false;
    $user_id = null;
    $reset_id = null;

    if ($result_tokens) {
        while ($row = $result_tokens->fetch_assoc()) {
            // Compare the user's plain token with the stored hash
            if (password_verify($token, $row['HashedToken'])) {
                // Match found!
                $valid_token_found = true;
                $user_id = $row['UserID'];
                $reset_id = $row['ResetID'];
                break; // Stop checking once found
            }
        }
        $result_tokens->free(); // Free result set
    } else {
         throw new Exception("Database error searching for token.");
    }


    if (!$valid_token_found) {
        throw new Exception("Invalid or expired password reset token.");
    }

    // 2. Hash the new password
    $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    // 3. Update the user's password in the Users table
    $sql_update_password = "UPDATE Users SET HashedPassword = ? WHERE UserID = ?";
    $stmt_update_pass = $conn->prepare($sql_update_password);
    if (!$stmt_update_pass) throw new Exception("Database prepare error (update password).");

    $stmt_update_pass->bind_param("si", $new_hashed_password, $user_id);
    if (!$stmt_update_pass->execute()) throw new Exception("Database error updating password.");
    // Check affected rows? Optional, user should exist if token was valid.
    $stmt_update_pass->close();

    // 4. Mark the token as used in the PasswordResets table
    $sql_mark_used = "UPDATE PasswordResets SET IsUsed = TRUE WHERE ResetID = ?";
    $stmt_mark_used = $conn->prepare($sql_mark_used);
    if (!$stmt_mark_used) throw new Exception("Database prepare error (mark token used).");

    $stmt_mark_used->bind_param("i", $reset_id);
    if (!$stmt_mark_used->execute()) throw new Exception("Database error marking token as used.");
    $stmt_mark_used->close();

    // 5. Commit the transaction
    $conn->commit();

    // --- Success ---
    $_SESSION['login_message'] = "Your password has been successfully reset. Please log in with your new password.";
    // Optional: Log out any existing sessions for this user for security
    // session_destroy(); // Or more targeted session cleanup if needed
    header("Location: ../login.php"); // Redirect to login page
    exit();

} catch (Exception $e) {
    // --- Error Handling ---
    $conn->rollback(); // Rollback transaction on error

    $_SESSION['reset_password_message'] = "Error resetting password: " . $e->getMessage();
    $_SESSION['reset_password_status'] = "error";

    // Redirect back to the reset page with the token
    header("Location: ../reset_password.php?token=" . urlencode($token));
    exit();

} finally {
    // Ensure connection is closed
     if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) {
       $conn->close();
     }
}

?>
