<?php
// admin/includes/handle_close_election.php
session_start();
require_once 'db_connect.php'; // Ensure this path is correct

// --- Authentication & Authorization ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
    $_SESSION['login_message'] = "Access denied.";
    header("Location: ../login.php");
    exit();
}

// --- CSRF Token Validation ---
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    $_SESSION['message'] = "Invalid request (CSRF token mismatch). Please try again.";
    $_SESSION['message_status'] = "error";
    header("Location: ../voting_monitor.php"); // Redirect back
    exit();
}


// --- Check Request Method and Input ---
if ($_SERVER["REQUEST_METHOD"] !== "POST" || !isset($_POST['election_id'])) {
    $_SESSION['message'] = "Invalid request method or missing data.";
    $_SESSION['message_status'] = "error";
    header("Location: ../voting_monitor.php");
    exit();
}

$election_id = filter_var($_POST['election_id'], FILTER_VALIDATE_INT);

if (!$election_id) {
    $_SESSION['message'] = "Invalid Election ID.";
    $_SESSION['message_status'] = "error";
    header("Location: ../voting_monitor.php");
    exit();
}

// --- Process Closing Election ---
$now = date('Y-m-d H:i:s');
$error_message = null;

// Begin transaction for atomicity
$conn->begin_transaction();

try {
    // 1. Verify the election exists, is Active, and its EndDate has passed
    $sql_verify = "SELECT Status, EndDate FROM Elections WHERE ElectionID = ? FOR UPDATE"; // Lock the row
    $stmt_verify = $conn->prepare($sql_verify);
    if (!$stmt_verify) throw new Exception("Database prepare error (verify): " . $conn->error);

    $stmt_verify->bind_param("i", $election_id);
    $stmt_verify->execute();
    $result_verify = $stmt_verify->get_result();

    if ($result_verify->num_rows !== 1) {
        throw new Exception("Election not found.");
    }

    $election = $result_verify->fetch_assoc();
    $stmt_verify->close();

    if ($election['Status'] !== 'Active') {
        throw new Exception("Election is not currently Active. Cannot close.");
    }
    if ($now <= $election['EndDate']) {
        throw new Exception("Election end date has not passed yet. Cannot close.");
    }

    // 2. Update the election status to 'Closed'
    $sql_update = "UPDATE Elections SET Status = 'Closed' WHERE ElectionID = ?";
    $stmt_update = $conn->prepare($sql_update);
    if (!$stmt_update) throw new Exception("Database prepare error (update): " . $conn->error);

    $stmt_update->bind_param("i", $election_id);
    if (!$stmt_update->execute()) {
        throw new Exception("Failed to update election status: " . $stmt_update->error);
    }

    // Check if the update affected a row
    if ($stmt_update->affected_rows !== 1) {
        // Should not happen if SELECT FOR UPDATE worked, but good check
        throw new Exception("Failed to update election status (no rows affected).");
    }
    $stmt_update->close();

    // 3. Optional: Add an entry to AuditLog
    // $admin_id = $_SESSION['user_id'];
    // $action_details = "Election ID $election_id manually closed.";
    // $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    // $sql_log = "INSERT INTO AuditLog (UserID, ActionType, Details, IPAddress) VALUES (?, 'ELECTION_CLOSED', ?, ?)";
    // $stmt_log = $conn->prepare($sql_log);
    // if ($stmt_log) {
    //     $stmt_log->bind_param("iss", $admin_id, $action_details, $ip_address);
    //     $stmt_log->execute(); // Execute but don't necessarily stop if logging fails
    //     $stmt_log->close();
    // } else {
    //     error_log("Failed to prepare audit log statement for closing election ID $election_id: " . $conn->error);
    // }


    // 4. Commit the transaction
    $conn->commit();

    // --- Success ---
    $_SESSION['message'] = "Election ID $election_id has been successfully closed.";
    $_SESSION['message_status'] = "success";

} catch (Exception $e) {
    // --- Error Handling ---
    $conn->rollback(); // Rollback on error
    $_SESSION['message'] = "Error closing election: " . $e->getMessage();
    $_SESSION['message_status'] = "error";
    error_log("Error closing election ID $election_id: " . $e->getMessage()); // Log detailed error

} finally {
    // Close connection if still open
    if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) {
        $conn->close();
    }
}

// Redirect back to the voting monitor page
header("Location: ../voting_monitor.php");
exit();
?>
