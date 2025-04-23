<?php
// admin/includes/handle_admin_user_action.php
session_start();
require_once 'db_connect.php'; // Ensure this path is correct

// --- Authentication & Authorization ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
    $_SESSION['login_message'] = "Access denied.";
    header("Location: ../login.php");
    exit();
}
$current_admin_id = $_SESSION['user_id']; // ID of the admin performing the action

// --- CSRF Token Validation ---
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    $_SESSION['message'] = "Invalid request (CSRF token mismatch). Please try again.";
    $_SESSION['message_status'] = "error";
    header("Location: ../user_management.php"); // Redirect back
    exit();
}

// --- Check Request Method and Input ---
if ($_SERVER["REQUEST_METHOD"] !== "POST" || !isset($_POST['action']) || !isset($_POST['user_id'])) {
    $_SESSION['message'] = "Invalid request method or missing data.";
    $_SESSION['message_status'] = "error";
    header("Location: ../user_management.php");
    exit();
}

$action = $_POST['action'];
$user_id_to_modify = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);

// --- Validate Input ---
if (!$user_id_to_modify) {
    $_SESSION['message'] = "Invalid User ID specified.";
    $_SESSION['message_status'] = "error";
    header("Location: ../user_management.php");
    exit();
}

// Prevent admin from modifying their own status
if ($user_id_to_modify === $current_admin_id) {
    $_SESSION['message'] = "You cannot change the status of your own account.";
    $_SESSION['message_status'] = "error";
    header("Location: ../user_management.php");
    exit();
}

// --- Determine New Status ---
$new_status = null;
$action_verb = '';

if ($action === 'activate') {
    $new_status = 1; // TRUE
    $action_verb = 'activated';
} elseif ($action === 'deactivate') {
    $new_status = 0; // FALSE
    $action_verb = 'deactivated';
} else {
    $_SESSION['message'] = "Invalid action specified.";
    $_SESSION['message_status'] = "error";
    header("Location: ../user_management.php");
    exit();
}

// --- Database Update ---
// Prepare the UPDATE statement to change IsActive status for the specified Admin user
$sql_update = "UPDATE Users SET IsActive = ? WHERE UserID = ? AND Role = 'Admin'";
$stmt_update = $conn->prepare($sql_update);

if ($stmt_update) {
    $stmt_update->bind_param("ii", $new_status, $user_id_to_modify);
    if ($stmt_update->execute()) {
        // Check if any row was actually updated
        if ($stmt_update->affected_rows > 0) {
            $_SESSION['message'] = "Admin account successfully " . $action_verb . ".";
            $_SESSION['message_status'] = "success";
             // Optional: Add Audit Log entry
             // $details = "Admin user ID $user_id_to_modify $action_verb by Admin ID $current_admin_id.";
             // log_audit_action($conn, $current_admin_id, 'ADMIN_STATUS_CHANGE', $details); // Assuming a helper function
        } else {
            // User ID might not exist, wasn't an admin, or status was already set
            $_SESSION['message'] = "Admin user not found or status already set.";
            $_SESSION['message_status'] = "error"; // Or 'info'
        }
    } else {
        // Database execution error
        $_SESSION['message'] = "Error updating admin status: " . $stmt_update->error;
        $_SESSION['message_status'] = "error";
        error_log("Execute failed (update admin status): UserID $user_id_to_modify - " . $stmt_update->error);
    }
    $stmt_update->close();
} else {
    // Database prepare statement error
    $_SESSION['message'] = "Database error preparing update statement.";
    $_SESSION['message_status'] = "error";
    error_log("Prepare failed (update admin status): " . $conn->error);
}

// Close the database connection
$conn->close();

// Redirect back to the user management page
header("Location: ../user_management.php");
exit();
?>
