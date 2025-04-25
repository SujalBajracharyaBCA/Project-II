<?php
// admin/includes/election_delete.php
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
     $_SESSION['message'] = "Invalid request method for delete.";
     $_SESSION['message_status'] = "error";
     header("Location: ../election_management.php");
     exit();
}

// --- CSRF Token Validation ---
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
     $_SESSION['message'] = "Invalid request (CSRF token mismatch). Delete action aborted.";
     $_SESSION['message_status'] = "error";
     header("Location: ../election_management.php");
     exit();
}

// --- Get Election ID from POST ---
if (!isset($_POST['id']) || !filter_var($_POST['id'], FILTER_VALIDATE_INT)) {
    $_SESSION['message'] = "Invalid or missing election ID for deletion.";
    $_SESSION['message_status'] = "error";
    header("Location: ../election_management.php");
    exit();
}
$election_id = (int)$_POST['id'];

// --- Check if Election Can Be Deleted ---
// Fetch status and check for votes (due to ON DELETE RESTRICT on Votes table)
$can_delete = false;
$has_votes = false;
$error_message = null;

$conn->begin_transaction(); // Use transaction for checks and delete

try {
    // Check status
    $sql_check_status = "SELECT Status FROM Elections WHERE ElectionID = ? FOR UPDATE"; // Lock row
    $stmt_check = $conn->prepare($sql_check_status);
    if(!$stmt_check) throw new Exception("DB prepare error (check status): " . $conn->error);
    $stmt_check->bind_param("i", $election_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    if ($election_db = $result_check->fetch_assoc()) {
        // Allow deletion only if not 'Active' (adjust logic as needed)
        // You might want stricter rules, e.g., only allow deleting 'Pending'
        if ($election_db['Status'] === 'Active') {
            throw new Exception("Cannot delete an active election. Close or archive it first.");
        }
        // Allow deletion for Pending, Closed, Archived (adjust as needed)
    } else {
         throw new Exception("Election not found.");
    }
    $stmt_check->close();

    // Check for existing votes (because Votes table has ON DELETE RESTRICT)
    $sql_check_votes = "SELECT COUNT(*) as vote_count FROM Votes WHERE ElectionID = ?";
    $stmt_votes = $conn->prepare($sql_check_votes);
     if(!$stmt_votes) throw new Exception("DB prepare error (check votes): " . $conn->error);
    $stmt_votes->bind_param("i", $election_id);
    $stmt_votes->execute();
    $result_votes = $stmt_votes->get_result();
    $vote_count = $result_votes->fetch_assoc()['vote_count'] ?? 0;
    $stmt_votes->close();

    if ($vote_count > 0) {
        throw new Exception("Cannot delete election because it has cast votes associated with it. Consider archiving instead.");
    }

    // If we reach here, deletion is allowed
    $can_delete = true;

    // --- Perform Deletion ---
    // Note: ON DELETE CASCADE will handle Candidates and EligibleVoters automatically
    $sql_delete = "DELETE FROM Elections WHERE ElectionID = ?";
    $stmt_delete = $conn->prepare($sql_delete);
    if(!$stmt_delete) throw new Exception("DB prepare error (delete): " . $conn->error);

    $stmt_delete->bind_param("i", $election_id);

    if ($stmt_delete->execute()) {
        if ($stmt_delete->affected_rows > 0) {
            // Deletion successful
            $conn->commit(); // Commit transaction
            $_SESSION['message'] = "Election (ID: $election_id) deleted successfully.";
            $_SESSION['message_status'] = "success";
             // Optional: Log audit trail
             // log_audit_action($conn, $_SESSION['user_id'], 'ELECTION_DELETE', "Deleted election ID: $election_id", $_SERVER['REMOTE_ADDR']);
        } else {
            // Should have been caught by the "not found" check earlier
             throw new Exception("Election not found or already deleted during check.");
        }
    } else {
        // Execution error (might be FK constraint if votes check failed somehow)
        throw new Exception("Error deleting election: " . $stmt_delete->error);
    }
    $stmt_delete->close();

} catch (Exception $e) {
    $conn->rollback(); // Rollback on any error
    $_SESSION['message'] = "Error: " . $e->getMessage();
    $_SESSION['message_status'] = "error";
    error_log("Election Delete Error (ID: $election_id): " . $e->getMessage());

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
