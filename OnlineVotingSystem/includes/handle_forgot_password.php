<?php
session_start();
require_once 'db_connect.php'; // Adjust path if necessary

// --- Check Request Method ---
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../forgot_password.php");
    exit();
}

// --- Get Email ---
$email = isset($_POST['email']) ? trim($_POST['email']) : '';

// --- Validate Email ---
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['forgot_password_message'] = "Please enter a valid email address.";
    $_SESSION['forgot_password_status'] = "error";
    header("Location: ../forgot_password.php");
    exit();
}

// --- Check if Email Exists in Users Table ---
$user_id = null;
$sql_find_user = "SELECT UserID FROM Users WHERE Email = ? AND IsActive = TRUE"; // Only allow active users
$stmt_find_user = $conn->prepare($sql_find_user);

if ($stmt_find_user) {
    $stmt_find_user->bind_param("s", $email);
    $stmt_find_user->execute();
    $result_find_user = $stmt_find_user->get_result();

    if ($result_find_user->num_rows === 1) {
        $user = $result_find_user->fetch_assoc();
        $user_id = $user['UserID'];
    }
    $stmt_find_user->close();
} else {
    // Database error during user lookup
    error_log("Prepare failed (find user by email): " . $conn->error);
    $_SESSION['forgot_password_message'] = "An error occurred. Please try again later.";
    $_SESSION['forgot_password_status'] = "error";
    header("Location: ../forgot_password.php");
    exit();
}

// --- Generate and Store Reset Token (If User Found) ---
if ($user_id) {
    // Generate a secure random token
    $token = bin2hex(random_bytes(32)); // 64 characters hex token
    $hashed_token = password_hash($token, PASSWORD_DEFAULT); // Hash the token before storing

    // Set token expiry time (e.g., 1 hour from now)
    $expires_at = date('Y-m-d H:i:s', time() + 3600); // Current time + 1 hour

    // **DATABASE MODIFICATION NEEDED:**
    // You need a table (e.g., `PasswordResets`) to store these tokens.
    // Example `PasswordResets` table structure:
    /*
    CREATE TABLE `PasswordResets` (
      `ResetID` INT AUTO_INCREMENT PRIMARY KEY,
      `UserID` INT NOT NULL,
      `HashedToken` VARCHAR(255) NOT NULL,
      `ExpiresAt` DATETIME NOT NULL,
      `IsUsed` BOOLEAN DEFAULT FALSE,
      `CreatedAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (`UserID`) REFERENCES `Users`(`UserID`) ON DELETE CASCADE ON UPDATE CASCADE,
      INDEX `idx_token` (`HashedToken`(10)), -- Index part of the hash
      INDEX `idx_userid` (`UserID`)
    ) ENGINE=InnoDB;
    */

    // --- Store the HASHED token in the database ---
    // First, potentially invalidate previous tokens for this user (optional)
    // $sql_invalidate = "UPDATE PasswordResets SET IsUsed = TRUE WHERE UserID = ? AND IsUsed = FALSE";
    // $stmt_invalidate = $conn->prepare($sql_invalidate);
    // if($stmt_invalidate) { $stmt_invalidate->bind_param("i", $user_id); $stmt_invalidate->execute(); $stmt_invalidate->close(); }

    // Insert the new token
    $sql_insert_token = "INSERT INTO PasswordResets (UserID, HashedToken, ExpiresAt) VALUES (?, ?, ?)";
    $stmt_insert_token = $conn->prepare($sql_insert_token);

    if ($stmt_insert_token) {
        $stmt_insert_token->bind_param("iss", $user_id, $hashed_token, $expires_at);

        if ($stmt_insert_token->execute()) {
            // --- Simulate Sending Email ---
            // In a real application, you would send an email containing a link
            // with the *plain* $token (not the hashed one).
            // Example link: https://yourdomain.com/reset_password.php?token=PLAIN_TOKEN_HERE

            // For this example, we'll just display a success message and maybe the token (FOR TESTING ONLY)
            $_SESSION['forgot_password_message'] = "If an account exists for this email, password reset instructions have been simulated. Please check your inbox (or see testing info below).";
            $_SESSION['forgot_password_status'] = "success";

            // --- FOR TESTING/DEMO ONLY - DO NOT SHOW TOKEN IN PRODUCTION ---
             $_SESSION['forgot_password_message'] .= "<br><small class='block mt-2 text-gray-600'><b>Testing Info:</b> Reset Link: <a href='../reset_password.php?token=" . urlencode($token) . "' class='text-blue-600 hover:underline'>Click Here</a> (Token: " . htmlspecialchars($token) . ")</small>";
            // --- END TESTING INFO ---

        } else {
            error_log("Execute failed (insert token): " . $stmt_insert_token->error);
            $_SESSION['forgot_password_message'] = "Could not process password reset request due to a database error.";
            $_SESSION['forgot_password_status'] = "error";
        }
        $stmt_insert_token->close();
    } else {
        error_log("Prepare failed (insert token): " . $conn->error);
        $_SESSION['forgot_password_message'] = "Could not process password reset request due to a server error.";
        $_SESSION['forgot_password_status'] = "error";
    }

} else {
    // User email not found or inactive - show a generic message for security
    // (Don't reveal if the email exists or not)
    $_SESSION['forgot_password_message'] = "If an account exists for this email, password reset instructions have been simulated. Please check your inbox.";
    $_SESSION['forgot_password_status'] = "success"; // Still show success to prevent email enumeration
}

$conn->close();
header("Location: ../forgot_password.php");
exit();

?>
