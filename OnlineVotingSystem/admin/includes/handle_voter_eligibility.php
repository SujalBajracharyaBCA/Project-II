<?php
// admin/includes/handle_voter_eligibility.php
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
     $_SESSION['message'] = "Invalid request method for eligibility update.";
     $_SESSION['message_status'] = "error";
     header("Location: ../election_management.php");
     exit();
}

// --- CSRF Token Validation ---
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
     $_SESSION['message'] = "Invalid request (CSRF token mismatch). Action aborted.";
     $_SESSION['message_status'] = "error";
     header("Location: ../election_management.php"); // Redirect to general management
     exit();
}

// --- Get Parameters ---
$election_id = isset($_POST['election_id']) ? filter_var($_POST['election_id'], FILTER_VALIDATE_INT) : 0;
$action = isset($_POST['action']) ? $_POST['action'] : ''; // 'add' or 'remove'
$voter_ids = isset($_POST['voter_ids']) && is_array($_POST['voter_ids']) ? $_POST['voter_ids'] : [];

// --- Validate Input ---
if ($election_id <= 0 || !in_array($action, ['add', 'remove'])) {
     $_SESSION['message'] = "Invalid action or election ID.";
     $_SESSION['message_status'] = "error";
     header("Location: ../election_management.php");
     exit();
}

// Sanitize voter IDs to ensure they are integers
$sanitized_voter_ids = [];
foreach ($voter_ids as $id) {
    if (filter_var($id, FILTER_VALIDATE_INT)) {
        $sanitized_voter_ids[] = (int)$id;
    }
}

if (empty($sanitized_voter_ids)) {
     $_SESSION['message'] = "No voters were selected for the action.";
     $_SESSION['message_status'] = "info"; // Use info or warning instead of error
     header("Location: ../voter_eligibility.php?election_id=" . $election_id);
     exit();
}

// --- Check if Eligibility Management is Allowed (Server-Side) ---
$can_manage_db = false;
$error_message = null;

$sql_check_status = "SELECT Status, StartDate FROM Elections WHERE ElectionID = ?";
$stmt_check = $conn->prepare($sql_check_status);
if ($stmt_check) {
    $stmt_check->bind_param("i", $election_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    if ($election_db = $result_check->fetch_assoc()) {
        $now = date('Y-m-d H:i:s');
        // Allow management only if Pending or Active but hasn't started
        if ($election_db['Status'] === 'Pending' || ($election_db['Status'] === 'Active' && $election_db['StartDate'] > $now)) {
            $can_manage_db = true;
        } else {
             $error_message = "Eligibility cannot be modified because the election has started or is closed/archived.";
        }
    } else {
        $error_message = "Election not found in database.";
    }
    $stmt_check->close();
} else {
    $error_message = "Database error checking election status.";
    error_log("Prepare failed (check status eligibility): " . $conn->error);
}

if (!$can_manage_db) {
     $_SESSION['message'] = $error_message ?? "Eligibility management is not allowed for this election.";
     $_SESSION['message_status'] = "error";
     header("Location: ../voter_eligibility.php?election_id=" . $election_id);
     exit();
}


// --- Perform Action (Add or Remove) within a Transaction ---
$conn->begin_transaction();
$success_count = 0;
$fail_count = 0;
$skipped_count = 0; // For removals if voter already voted

try {
    if ($action === 'add') {
        // Use INSERT IGNORE to avoid errors if a voter is already eligible
        $sql_add = "INSERT IGNORE INTO EligibleVoters (ElectionID, UserID, HasVoted) VALUES (?, ?, FALSE)";
        $stmt_add = $conn->prepare($sql_add);
        if (!$stmt_add) throw new Exception("DB prepare error (add eligibility): " . $conn->error);

        foreach ($sanitized_voter_ids as $voter_id) {
            $stmt_add->bind_param("ii", $election_id, $voter_id);
            if ($stmt_add->execute()) {
                // Check affected_rows: 1 means added, 0 means ignored (already existed)
                if ($stmt_add->affected_rows > 0) {
                    $success_count++;
                }
            } else {
                $fail_count++;
                // Log specific error if needed: error_log("Failed to add voter $voter_id: " . $stmt_add->error);
            }
        }
        $stmt_add->close();
        $_SESSION['message'] = "$success_count voter(s) added successfully to election ID $election_id.";
        if($fail_count > 0) $_SESSION['message'] .= " $fail_count failed.";
        $_SESSION['message_status'] = ($fail_count == 0) ? "success" : "warning";


    } elseif ($action === 'remove') {
        // IMPORTANT: Check if the voter has already voted before removing eligibility
        $sql_remove = "DELETE FROM EligibleVoters WHERE ElectionID = ? AND UserID = ? AND HasVoted = FALSE"; // Only remove if HasVoted is FALSE
        $stmt_remove = $conn->prepare($sql_remove);
        if (!$stmt_remove) throw new Exception("DB prepare error (remove eligibility): " . $conn->error);

        foreach ($sanitized_voter_ids as $voter_id) {
            $stmt_remove->bind_param("ii", $election_id, $voter_id);
            if ($stmt_remove->execute()) {
                 // Check affected_rows: 1 means removed, 0 means not removed (either didn't exist or HasVoted=TRUE)
                if ($stmt_remove->affected_rows > 0) {
                    $success_count++;
                } else {
                    // Check if they existed but had voted
                    $sql_check_voted = "SELECT COUNT(*) FROM EligibleVoters WHERE ElectionID = ? AND UserID = ? AND HasVoted = TRUE";
                    $stmt_check_voted = $conn->prepare($sql_check_voted);
                    if($stmt_check_voted){
                        $stmt_check_voted->bind_param("ii", $election_id, $voter_id);
                        $stmt_check_voted->execute();
                        if($stmt_check_voted->get_result()->fetch_row()[0] > 0){
                            $skipped_count++; // Count as skipped because they voted
                        } else {
                             $fail_count++; // Failed for other reason (e.g., wasn't eligible)
                        }
                        $stmt_check_voted->close();
                    } else {
                        $fail_count++; // Error checking vote status
                    }
                }
            } else {
                $fail_count++;
                // Log specific error: error_log("Failed to remove voter $voter_id: " . $stmt_remove->error);
            }
        }
        $stmt_remove->close();

        $_SESSION['message'] = "$success_count voter(s) removed successfully from election ID $election_id.";
        if($skipped_count > 0) $_SESSION['message'] .= " $skipped_count could not be removed because they already voted.";
        if($fail_count > 0) $_SESSION['message'] .= " $fail_count failed (or were not eligible).";
        $_SESSION['message_status'] = ($fail_count == 0 && $skipped_count == 0) ? "success" : "warning";

    }

    // Commit the transaction if everything seemed okay (even with ignored inserts/skips)
    $conn->commit();
     // Optional: Log audit trail
     // log_audit_action($conn, $_SESSION['user_id'], 'ELIGIBILITY_CHANGE', "$action $success_count voters for election ID: $election_id", $_SERVER['REMOTE_ADDR']);


} catch (Exception $e) {
    $conn->rollback(); // Rollback on any error
    $_SESSION['message'] = "Error updating eligibility: " . $e->getMessage();
    $_SESSION['message_status'] = "error";
    error_log("Eligibility Update Error (Election: $election_id, Action: $action): " . $e->getMessage());
} finally {
     // Ensure connection is closed
     if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) {
       $conn->close();
     }
}

// Redirect back to the eligibility management page
header("Location: ../voter_eligibility.php?election_id=" . $election_id);
exit();

?>
