<?php
// admin/includes/handle_election_status.php
session_start();
require_once 'db_connect.php'; // Ensure this path is correct relative to this file

// --- Authentication & Authorization ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
    $_SESSION['login_message'] = "Access denied.";
    header("Location: ../login.php");
    exit();
}

// --- Check Request Method (MUST be POST) ---
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
     $_SESSION['message'] = "Invalid request method for status change.";
     $_SESSION['message_status'] = "error";
     header("Location: ../election_management.php");
     exit();
}

// --- CSRF Token Validation ---
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
     $_SESSION['message'] = "Invalid request (CSRF token mismatch). Action aborted.";
     $_SESSION['message_status'] = "error";
     header("Location: ../election_management.php");
     exit();
}

// --- Get Parameters ---
$election_id = isset($_POST['election_id']) ? filter_var($_POST['election_id'], FILTER_VALIDATE_INT) : 0;
$action = isset($_POST['action']) ? $_POST['action'] : ''; // 'activate' or 'close'

// --- Validate Input ---
if ($election_id <= 0 || !in_array($action, ['activate', 'close'])) {
     $_SESSION['message'] = "Invalid action or election ID.";
     $_SESSION['message_status'] = "error";
     header("Location: ../election_management.php");
     exit();
}

// --- Perform Action ---
$new_status = '';
$success_message = '';
$error_message = null;

$conn->begin_transaction();

try {
    // 1. Get current election details (Status, StartDate, EndDate) and lock row
    $sql_get_election = "SELECT Status, StartDate, EndDate FROM Elections WHERE ElectionID = ? FOR UPDATE";
    $stmt_get = $conn->prepare($sql_get_election);
    if (!$stmt_get) throw new Exception("DB prepare error (get status): " . $conn->error);

    $stmt_get->bind_param("i", $election_id);
    $stmt_get->execute();
    $result_get = $stmt_get->get_result();

    if (!($election_db = $result_get->fetch_assoc())) {
         throw new Exception("Election not found.");
    }
    $stmt_get->close();

    $current_status = $election_db['Status'];
    $start_date = $election_db['StartDate'];
    $end_date = $election_db['EndDate'];
    $now = date('Y-m-d H:i:s');

    // 2. Validate Action based on Current Status and Dates
    if ($action === 'activate') {
        if ($current_status !== 'Pending') {
            throw new Exception("Election cannot be activated. Current status is '$current_status'.");
        }
        // Optional: Prevent activation if end date is already past?
        // if ($end_date <= $now) {
        //    throw new Exception("Cannot activate an election whose end date has already passed.");
        // }
        $new_status = 'Active';
        $success_message = "Election (ID: $election_id) activated successfully.";

    } elseif ($action === 'close') {
        if ($current_status !== 'Active') {
             throw new Exception("Only active elections can be closed. Current status is '$current_status'.");
        }
        // Optional: Allow closing early? Or only after EndDate?
        // if ($end_date > $now) {
        //    // This is closing early - maybe require confirmation or different action?
        //    // For now, we allow closing if status is Active, regardless of EndDate.
        // }
        $new_status = 'Closed';
        $success_message = "Election (ID: $election_id) closed successfully.";
    }

    // 3. Update Status in Database
    $sql_update = "UPDATE Elections SET Status = ? WHERE ElectionID = ?";
    $stmt_update = $conn->prepare($sql_update);
    if (!$stmt_update) throw new Exception("DB prepare error (update status): " . $conn->error);

    $stmt_update->bind_param("si", $new_status, $election_id);
    if (!$stmt_update->execute()) {
        throw new Exception("Database error updating election status: " . $stmt_update->error);
    }

    if ($stmt_update->affected_rows > 0) {
        // Commit transaction
        $conn->commit();
        $_SESSION['message'] = $success_message;
        $_SESSION['message_status'] = "success";
        // Optional: Log audit trail
        // log_audit_action($conn, $_SESSION['user_id'], 'ELECTION_STATUS_CHANGE', "$action election ID: $election_id to $new_status", $_SERVER['REMOTE_ADDR']);

    } else {
         // Status might have already been changed by another process
         throw new Exception("Election status could not be updated (it might already be in the target state).");
    }
    $stmt_update->close();


} catch (Exception $e) {
    $conn->rollback(); // Rollback on any error
    $_SESSION['message'] = "Error changing status: " . $e->getMessage();
    $_SESSION['message_status'] = "error";
    error_log("Election Status Change Error (ID: $election_id, Action: $action): " . $e->getMessage());
} finally {
     // Ensure connection is closed
     if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) {
       $conn->close();
     }
}

// Redirect back to the election management page
header("Location: ../election_management.php");
exit();

?>
