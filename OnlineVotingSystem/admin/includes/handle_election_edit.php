<?php
// admin/includes/handle_election_edit.php
session_start();
require_once 'db_connect.php'; // Ensure this path is correct relative to this file

// --- Authentication & Authorization ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
    // Redirect to admin login if not authorized
    $_SESSION['login_message'] = "Access denied.";
    header("Location: ../login.php"); // Adjust path back to admin login
    exit();
}

// --- Check Request Method ---
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    // Redirect if accessed directly or via GET
    $_SESSION['message'] = "Invalid request method.";
    $_SESSION['message_status'] = "error";
    header("Location: ../election_management.php"); // Redirect to management page
    exit();
}

// --- CSRF Token Validation ---
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    $_SESSION['message'] = "Invalid request (CSRF token mismatch). Please try again.";
    $_SESSION['message_status'] = "error";
    // Redirect back to the edit form or management page
    $election_id_redirect = isset($_POST['election_id']) ? (int)$_POST['election_id'] : 0;
    if ($election_id_redirect > 0) {
         header("Location: ../election_edit.php?id=" . $election_id_redirect);
    } else {
         header("Location: ../election_management.php");
    }
    exit();
}


// --- Retrieve and Sanitize Form Data ---
$election_id = isset($_POST['election_id']) ? filter_var($_POST['election_id'], FILTER_VALIDATE_INT) : 0;
$title = isset($_POST['title']) ? trim($_POST['title']) : '';
$description = isset($_POST['description']) ? trim($_POST['description']) : '';
$start_date_str = isset($_POST['start_date']) ? $_POST['start_date'] : '';
$end_date_str = isset($_POST['end_date']) ? $_POST['end_date'] : '';
$voting_method = isset($_POST['voting_method']) ? $_POST['voting_method'] : ''; // Voting method might be disabled on form, but check anyway

// --- Basic Validation ---
$errors = [];
if ($election_id <= 0) {
    $errors[] = "Invalid Election ID.";
}
if (empty($title)) {
    $errors[] = "Election Title is required.";
}
if (empty($start_date_str)) {
    $errors[] = "Start Date is required.";
}
if (empty($end_date_str)) {
    $errors[] = "End Date is required.";
}
// Validate date formats (basic check, more robust needed if supporting various formats)
$start_date = date('Y-m-d H:i:s', strtotime($start_date_str));
$end_date = date('Y-m-d H:i:s', strtotime($end_date_str));
if (!$start_date || $start_date != date('Y-m-d H:i:s', strtotime($start_date_str))) {
     $errors[] = "Invalid Start Date format.";
}
if (!$end_date || $end_date != date('Y-m-d H:i:s', strtotime($end_date_str))) {
     $errors[] = "Invalid End Date format.";
}
// Check if end date is after start date
if ($start_date && $end_date && $start_date >= $end_date) {
    $errors[] = "End Date must be after Start Date.";
}
// Validate voting method against allowed list (if it was editable)
$allowed_methods = ['FPTP', 'Approval', 'RCV', 'STV', 'Score', 'Condorcet'];
if (empty($voting_method) || !in_array($voting_method, $allowed_methods)) {
     // If method wasn't editable, this shouldn't fail unless tampered with.
     // If it *was* editable, this validation is crucial.
     // For now, assume it wasn't editable if election started.
     // $errors[] = "Invalid Voting Method selected.";
}


// --- Check if Editing is Permitted (Server-Side) ---
$can_edit_db = false;
if ($election_id > 0) {
    $sql_check_status = "SELECT Status, StartDate FROM Elections WHERE ElectionID = ?";
    $stmt_check = $conn->prepare($sql_check_status);
    if ($stmt_check) {
        $stmt_check->bind_param("i", $election_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        if ($election_db = $result_check->fetch_assoc()) {
            $now = date('Y-m-d H:i:s');
            // Allow editing only if Pending or Active but hasn't started
            if ($election_db['Status'] === 'Pending' || ($election_db['Status'] === 'Active' && $election_db['StartDate'] > $now)) {
                $can_edit_db = true;
            } else {
                 $errors[] = "This election cannot be edited because it is already active or closed.";
            }
        } else {
            $errors[] = "Election not found in database.";
        }
        $stmt_check->close();
    } else {
        $errors[] = "Database error checking election status.";
        error_log("Prepare failed (check status edit): " . $conn->error);
    }
}


// --- Process Update or Report Errors ---
if (empty($errors) && $can_edit_db) {
    // Prepare UPDATE statement
    // Note: We generally DON'T allow changing the VotingMethod after creation,
    // especially if candidates/voters are assigned. If you need to allow it,
    // ensure the form field wasn't disabled and add it to the SQL/bind_param.
    $sql_update = "UPDATE Elections SET Title = ?, Description = ?, StartDate = ?, EndDate = ? WHERE ElectionID = ?";
    $stmt_update = $conn->prepare($sql_update);

    if ($stmt_update) {
        // Bind parameters (s=string, i=integer) - Title, Desc, Start, End, ID
        $stmt_update->bind_param("ssssi", $title, $description, $start_date, $end_date, $election_id);

        if ($stmt_update->execute()) {
            // Success
            $_SESSION['message'] = "Election details updated successfully.";
            $_SESSION['message_status'] = "success";
            // Optional: Log audit trail
            // log_audit_action($conn, $_SESSION['user_id'], 'ELECTION_EDIT', "Edited election ID: $election_id", $_SERVER['REMOTE_ADDR']);
            header("Location: ../election_management.php"); // Redirect to management page
            exit();
        } else {
            // Execution error
            $_SESSION['message'] = "Error updating election: " . $stmt_update->error;
            $_SESSION['message_status'] = "error";
            error_log("Execute failed (update election): " . $stmt_update->error);
        }
        $stmt_update->close();
    } else {
        // Prepare statement error
        $_SESSION['message'] = "Database error preparing update statement.";
        $_SESSION['message_status'] = "error";
        error_log("Prepare failed (update election): " . $conn->error);
    }

} else {
    // Validation errors occurred or editing not allowed
    $_SESSION['message'] = implode("<br>", $errors);
    $_SESSION['message_status'] = "error";
    // Optional: Repopulate form data (more complex)
    // $_SESSION['form_data'] = $_POST;
}

// Close DB connection
$conn->close();

// Redirect back to the edit page if there were errors
header("Location: ../election_edit.php?id=" . $election_id);
exit();

?>
