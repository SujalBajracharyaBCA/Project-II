<?php
// admin/includes/handle_election_create.php
session_start();
require_once 'db_connect.php'; // Ensure this path is correct

// --- Authentication & Authorization ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
    $_SESSION['login_message'] = "Access denied.";
    header("Location: ../login.php");
    exit();
}
$admin_id = $_SESSION['user_id']; // ID of the admin creating the election

// --- CSRF Token Validation ---
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    $_SESSION['message'] = "Invalid request (CSRF token mismatch). Please try again.";
    $_SESSION['message_status'] = "error";
    $_SESSION['form_data'] = $_POST; // Keep form data on CSRF error
    header("Location: ../election_create.php");
    exit();
}

// --- Check Request Method ---
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $_SESSION['message'] = "Invalid request method.";
    $_SESSION['message_status'] = "error";
    header("Location: ../election_create.php");
    exit();
}

// --- Retrieve and Sanitize Form Data ---
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? ''); // Allow empty description
$start_date_str = trim($_POST['start_date'] ?? '');
$end_date_str = trim($_POST['end_date'] ?? '');
$voting_method = trim($_POST['voting_method'] ?? '');

// --- Server-Side Validation ---
$errors = [];
$valid_voting_methods = ['FPTP', 'Approval', 'RCV', 'STV', 'Score', 'Condorcet']; // Keep in sync with form

if (empty($title)) {
    $errors[] = "Election Title is required.";
}
if (empty($start_date_str)) {
    $errors[] = "Start Date and Time are required.";
}
if (empty($end_date_str)) {
    $errors[] = "End Date and Time are required.";
}
if (empty($voting_method)) {
    $errors[] = "Voting Method is required.";
} elseif (!in_array($voting_method, $valid_voting_methods)) {
    $errors[] = "Invalid Voting Method selected.";
}

// Validate date formats and logic
$start_date = null;
$end_date = null;
$now_formatted = date('Y-m-d H:i:s'); // Use server time

if (!empty($start_date_str)) {
    $start_timestamp = strtotime($start_date_str);
    if ($start_timestamp === false) {
        $errors[] = "Invalid Start Date format.";
    } else {
        $start_date = date('Y-m-d H:i:s', $start_timestamp);
        // Optional: Check if start date is reasonably in the future/past
        // if ($start_date < $now_formatted) {
        //     $errors[] = "Start Date cannot be in the past.";
        // }
    }
}

if (!empty($end_date_str)) {
    $end_timestamp = strtotime($end_date_str);
    if ($end_timestamp === false) {
        $errors[] = "Invalid End Date format.";
    } else {
        $end_date = date('Y-m-d H:i:s', $end_timestamp);
    }
}

// Check if end date is after start date
if ($start_date && $end_date && $start_date >= $end_date) {
    $errors[] = "End Date must be after Start Date.";
}


// --- Process Creation or Report Errors ---
if (empty($errors)) {
    // All checks passed, proceed with inserting into database

    // Prepare SQL INSERT statement
    $sql_insert = "INSERT INTO Elections (Title, Description, StartDate, EndDate, VotingMethod, Status, CreatedByAdminID, CreatedAt)
                   VALUES (?, ?, ?, ?, ?, 'Pending', ?, NOW())";
    $stmt_insert = $conn->prepare($sql_insert);

    if ($stmt_insert) {
        $stmt_insert->bind_param("sssssi",
            $title,
            $description,
            $start_date, // Use formatted date string
            $end_date,   // Use formatted date string
            $voting_method,
            $admin_id
        );

        if ($stmt_insert->execute()) {
            // Insertion successful
            $new_election_id = $conn->insert_id; // Get the ID of the newly created election

            // Optional: Add Audit Log entry
            // $details = "Admin ID $admin_id created Election ID $new_election_id: '$title'";
            // log_audit_action($conn, $admin_id, 'ELECTION_CREATED', $details);

            $_SESSION['message'] = "Election '$title' created successfully!";
            $_SESSION['message_status'] = "success";
            // Regenerate CSRF token after successful POST
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            header("Location: ../election_management.php"); // Redirect to management page
            exit();

        } else {
            // Execution failed
            $_SESSION['message'] = "Failed to create election due to a database error: " . $stmt_insert->error;
            $_SESSION['message_status'] = "error";
            error_log("Execute failed (insert election): " . $stmt_insert->error);
        }
        $stmt_insert->close();
    } else {
        // Prepare statement failed
        $_SESSION['message'] = "Failed to create election due to a server error.";
        $_SESSION['message_status'] = "error";
        error_log("Prepare failed (insert election): " . $conn->error);
    }

} else {
    // Validation errors occurred
    $_SESSION['message'] = implode("<br>", $errors); // Show all errors
    $_SESSION['message_status'] = "error";
    // Store submitted values in session to repopulate form
    $_SESSION['form_data'] = $_POST;
}

// Close the database connection
$conn->close();

// Redirect back to the creation page if there were errors or DB issues
header("Location: ../election_create.php");
exit();

?>
