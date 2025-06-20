<?php
// admin/includes/handle_candidate_edit.php
session_start();
require_once 'db_connect.php'; // Ensure this path is correct relative to this file

// --- Authentication & Authorization ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
    // Set a generic message if the session is completely lost, or redirect to login
    $_SESSION['message'] = "Access denied. Please log in as an administrator.";
    $_SESSION['message_status'] = "error";
    // Redirect to login if session is invalid, otherwise back to a safe page
    header("Location: ../login.php");
    exit();
}

// --- Check Request Method (MUST be POST) ---
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $_SESSION['message'] = "Invalid request method.";
    $_SESSION['message_status'] = "error";
    // Redirect back to election management as a default if the origin is unknown
    header("Location: ../election_management.php");
    exit();
}

// --- CSRF Token Validation ---
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    $_SESSION['message'] = "Invalid request (CSRF token mismatch). Action aborted.";
    $_SESSION['message_status'] = "error";
    // Determine a safe redirect, possibly to the specific candidate management page if election_id is available
    $redirect_url = "../election_management.php";
    if (isset($_POST['election_id']) && filter_var($_POST['election_id'], FILTER_VALIDATE_INT)) {
        $redirect_url = "../candidate_management.php?election_id=" . (int)$_POST['election_id'];
    }
    header("Location: " . $redirect_url);
    exit();
}

// --- Get and Validate Parameters ---
$candidate_id = isset($_POST['candidate_id']) ? filter_var($_POST['candidate_id'], FILTER_VALIDATE_INT) : 0;
$election_id = isset($_POST['election_id']) ? filter_var($_POST['election_id'], FILTER_VALIDATE_INT) : 0;
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$description = isset($_POST['description']) ? trim($_POST['description']) : '';
// Ensure display_order is an integer, default to 0 if empty or invalid
$display_order_raw = $_POST['display_order'] ?? '0';
$display_order = filter_var($display_order_raw, FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
if ($display_order === false || $display_order < 0) { // filter_var returns false on failure
    $display_order = 0; // Default to 0 if invalid or negative
}


// --- Basic Input Validation ---
if ($candidate_id <= 0 || $election_id <= 0) {
    $_SESSION['message'] = "Invalid candidate or election ID provided.";
    $_SESSION['message_status'] = "error";
    header("Location: ../election_management.php"); // General redirect if IDs are bad
    exit();
}

if (empty($name)) {
    $_SESSION['message'] = "Candidate Name is required.";
    $_SESSION['message_status'] = "error";
    // Redirect back to the edit page for this specific candidate
    header("Location: ../candidate_edit.php?id=" . $candidate_id . "&election_id=" . $election_id);
    exit();
}
if (strlen($name) > 150) {
    $_SESSION['message'] = "Candidate Name is too long (maximum 150 characters).";
    $_SESSION['message_status'] = "error";
    header("Location: ../candidate_edit.php?id=" . $candidate_id . "&election_id=" . $election_id);
    exit();
}
// Description can be long, but you might want to set a reasonable limit if TEXT type has practical limits in your display or DB.


// --- Check if Election Allows Candidate Management ---
$can_manage_candidates = false;
$stmt_check_election = $conn->prepare("SELECT Status, StartDate FROM Elections WHERE ElectionID = ?");
if ($stmt_check_election) {
    $stmt_check_election->bind_param("i", $election_id);
    $stmt_check_election->execute();
    $result_check_election = $stmt_check_election->get_result();
    if ($election_details = $result_check_election->fetch_assoc()) {
        $now = date('Y-m-d H:i:s');
        if ($election_details['Status'] === 'Pending' || ($election_details['Status'] === 'Active' && $now < $election_details['StartDate'])) {
            $can_manage_candidates = true;
        }
    }
    $stmt_check_election->close();
} else {
    $_SESSION['message'] = "Error verifying election status: " . $conn->error;
    $_SESSION['message_status'] = "error";
    error_log("Prepare failed (check election for candidate edit): " . $conn->error);
    header("Location: ../candidate_management.php?election_id=" . $election_id);
    exit();
}

if (!$can_manage_candidates) {
    $_SESSION['message'] = "Cannot edit candidate because the election has already started or is closed.";
    $_SESSION['message_status'] = "error";
    header("Location: ../candidate_management.php?election_id=" . $election_id);
    exit();
}

// --- Update Candidate in Database ---
$sql_update = "UPDATE Candidates SET Name = ?, Description = ?, DisplayOrder = ? WHERE CandidateID = ? AND ElectionID = ?";
$stmt_update = $conn->prepare($sql_update);

if ($stmt_update) {
    $stmt_update->bind_param("ssiii", $name, $description, $display_order, $candidate_id, $election_id);
    if ($stmt_update->execute()) {
        if ($stmt_update->affected_rows > 0) {
            $_SESSION['message'] = "Candidate details updated successfully.";
            $_SESSION['message_status'] = "success";
        } else {
            // No rows affected could mean the data was the same, or candidate_id/election_id didn't match (though earlier checks should catch this)
            $_SESSION['message'] = "No changes were made to the candidate details (or candidate not found).";
            $_SESSION['message_status'] = "info"; // Use 'info' if no actual error, but no change
        }
    } else {
        $_SESSION['message'] = "Error updating candidate: " . $stmt_update->error;
        $_SESSION['message_status'] = "error";
        error_log("Execute failed (update candidate): " . $stmt_update->error);
    }
    $stmt_update->close();
} else {
    $_SESSION['message'] = "Database error preparing update statement: " . $conn->error;
    $_SESSION['message_status'] = "error";
    error_log("Prepare failed (update candidate): " . $conn->error);
}

$conn->close();

// --- Regenerate CSRF token after successful POST processing ---
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// --- Redirect back to candidate management page for the specific election ---
header("Location: ../candidate_management.php?election_id=" . $election_id);
exit();
?>