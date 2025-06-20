<?php
// Start the session to access logged-in user data and store messages
session_start();

// --- Authentication Check ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Voter') {
    $_SESSION['login_message'] = "Authentication required to vote.";
    header("Location: ../login.php");
    exit();
}

// *** Define the application's timezone ***
// This should match the timezone set in dashboard.php and vote.php
$app_timezone_str = 'Asia/Kathmandu'; // Use your server/application timezone
date_default_timezone_set($app_timezone_str);


// Include the database connection file
require_once 'db_connect.php'; // Adjust path if necessary

// Get user ID from session
$user_id = $_SESSION['user_id'];

// --- Check Request Method ---
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../dashboard.php");
    exit();
}

// --- Retrieve POST Data ---
$election_id = isset($_POST['election_id']) ? (int)$_POST['election_id'] : 0;
$vote_data_raw = isset($_POST['vote_data']) ? $_POST['vote_data'] : null;
$submitted_voting_method = isset($_POST['voting_method']) ? $_POST['voting_method'] : '';

// --- Basic Input Validation ---
if ($election_id <= 0) {
    $_SESSION['vote_message'] = "Invalid submission data (Missing Election ID).";
    $_SESSION['vote_status'] = "error";
    header("Location: ../dashboard.php"); // Redirect to dashboard if election ID is missing
    exit();
}


// --- Database Transaction and Verification ---
$conn->begin_transaction(); // Start transaction

$error_message = null;
$election = null;
$candidates_map = []; // Store valid candidate IDs for validation: [id => true]

try {
    // Get current time as a DateTime object in the application's timezone
    // Moved inside try block to ensure timezone object is available
    $app_timezone = new DateTimeZone($app_timezone_str);
    $now_dt = new DateTime('now', $app_timezone);

    // 1. Re-verify Election Status, Dates, Eligibility, and Voting Status (within transaction)
    // Use SELECT ... FOR UPDATE on EligibleVoters to lock the row and prevent race conditions
    $sql_verify = "SELECT
                     e.ElectionID, e.Title, e.StartDate, e.EndDate, e.Status AS ElectionStatus, e.VotingMethod,
                     ev.HasVoted
                   FROM Elections e
                   JOIN EligibleVoters ev ON e.ElectionID = ev.ElectionID
                   WHERE e.ElectionID = ? AND ev.UserID = ?
                   FOR UPDATE"; // Lock the EligibleVoters row

    $stmt_verify = $conn->prepare($sql_verify);
    if (!$stmt_verify) throw new Exception("Database prepare error (verify): " . $conn->error);

    $stmt_verify->bind_param("ii", $election_id, $user_id);
    $stmt_verify->execute();
    $result_verify = $stmt_verify->get_result();

    if ($result_verify->num_rows !== 1) {
        throw new Exception("Eligibility error: You are not eligible or the election does not exist.");
    }

    $election = $result_verify->fetch_assoc();
    $stmt_verify->close(); // Close statement early

    // Convert DB date strings into DateTime objects using the application's timezone
    $start_dt = null;
    $end_dt = null;
    if ($election['StartDate']) {
        $start_dt = new DateTime($election['StartDate'], $app_timezone);
    }
    if ($election['EndDate']) {
        $end_dt = new DateTime($election['EndDate'], $app_timezone);
    }

    // Check status, dates, and if already voted using DateTime objects
    if (!$start_dt || !$end_dt) throw new Exception("Invalid date configuration for the election.");
    if ($election['ElectionStatus'] !== 'Active') throw new Exception("Election is not active.");
    // *** Use DateTime object comparison ***
    if ($now_dt < $start_dt) throw new Exception("Voting has not started yet.");
    // *** Use DateTime object comparison ***
    if ($now_dt > $end_dt) throw new Exception("Voting has ended.");
    if ($election['HasVoted']) throw new Exception("You have already submitted your vote for this election.");
    if ($election['VotingMethod'] !== $submitted_voting_method) throw new Exception("Voting method mismatch."); // Sanity check


    // 2. Fetch valid Candidate IDs for this election (for validation)
    $sql_candidates = "SELECT CandidateID FROM Candidates WHERE ElectionID = ?";
    $stmt_cand = $conn->prepare($sql_candidates);
    if (!$stmt_cand) throw new Exception("Database prepare error (candidates): " . $conn->error);
    $stmt_cand->bind_param("i", $election_id);
    $stmt_cand->execute();
    $result_cand = $stmt_cand->get_result();
    while ($row = $result_cand->fetch_assoc()) {
        $candidates_map[$row['CandidateID']] = true; // Store valid IDs
    }
    $stmt_cand->close();
    if (empty($candidates_map)) throw new Exception("No candidates configured for this election.");


    // 3. Validate and Process Vote Data based on Method
    $vote_data_processed = null;
    $validation_passed = false;

    switch ($election['VotingMethod']) {
        case 'FPTP':
            if ($vote_data_raw !== null && is_numeric($vote_data_raw) && isset($candidates_map[(int)$vote_data_raw])) {
                $vote_data_processed = (int)$vote_data_raw; // Store the single candidate ID as string/number
                $validation_passed = true;
            } else {
                 throw new Exception("Invalid selection for FPTP voting.");
            }
            break;

        case 'Approval':
             $approved_ids = []; // Initialize empty array
             if (is_array($vote_data_raw)) { // If checkboxes were submitted (even if none checked, $_POST['vote_data'] might exist as empty array)
                 foreach ($vote_data_raw as $cand_id) {
                     if (is_numeric($cand_id) && isset($candidates_map[(int)$cand_id])) {
                         $approved_ids[] = (int)$cand_id; // Add valid IDs
                     } else {
                          throw new Exception("Invalid candidate ID submitted for Approval voting.");
                     }
                 }
                 $vote_data_processed = json_encode($approved_ids); // Store as JSON array (can be empty [])
                 $validation_passed = true;
             } else if ($vote_data_raw === null) { // Explicitly handle case where the 'vote_data' key isn't sent at all (no boxes checked)
                  $vote_data_processed = json_encode([]); // Store empty array
                  $validation_passed = true;
             } else {
                  throw new Exception("Invalid data format for Approval voting.");
             }
             break;

        case 'RCV':
        case 'STV':
        case 'Condorcet':
            if (is_array($vote_data_raw) && !empty($vote_data_raw)) { // Must be an array and not empty
                $ranked_votes = [];
                $used_ranks = [];
                $max_rank = count($candidates_map); // Max rank is the number of candidates

                // First, convert string keys from POST (candidate ID strings) to integers and collect all entered ranks
                $submitted_ranks = [];
                foreach ($vote_data_raw as $cand_id_str => $rank_str) {
                    $cand_id = filter_var($cand_id_str, FILTER_VALIDATE_INT);
                    $rank = filter_var($rank_str, FILTER_VALIDATE_INT);

                    // Skip empty rank entries, but ensure at least one valid rank is submitted later
                    if (trim((string)$rank_str) === '') {
                        continue;
                    }

                    if ($cand_id === false || !isset($candidates_map[$cand_id])) {
                        throw new Exception("Invalid candidate ID ($cand_id_str) submitted for ranking.");
                    }
                    if ($rank === false || $rank < 1 || $rank > $max_rank) {
                        throw new Exception("Invalid rank ($rank_str) submitted for candidate ($cand_id). Must be between 1 and $max_rank.");
                    }
                    if (isset($used_ranks[$rank])) {
                        throw new Exception("Duplicate rank ($rank) submitted. Ranks must be unique.");
                    }

                    $submitted_ranks[$cand_id] = $rank;
                    $used_ranks[$rank] = true;
                }

                if (empty($submitted_ranks)) {
                    throw new Exception("Please rank at least one candidate for ranked voting.");
                }

                // Sort the ranked votes by rank to store them in a consistent order
                asort($submitted_ranks); // Sort by value (rank)
                $ordered_candidate_ids = array_keys($submitted_ranks); // Get candidate IDs in ranked order

                // Store as a JSON array of candidate IDs in ranked order
                $vote_data_processed = json_encode($ordered_candidate_ids);
                $validation_passed = true;
            } else {
                 throw new Exception("Invalid data format or no candidates ranked for ranked voting.");
            }
            break;

         case 'Score':
             if (is_array($vote_data_raw) && !empty($vote_data_raw)) { // Must be an array and not empty
                 $scored_votes = [];
                 $min_score = 0; $max_score = 10; // Define score range as per vote.php frontend
                 $has_at_least_one_score = false;

                 foreach ($vote_data_raw as $cand_id_str => $score_str) {
                     // Skip empty score entries
                     if (trim((string)$score_str) === '') {
                         continue;
                     }

                     $cand_id = filter_var($cand_id_str, FILTER_VALIDATE_INT);
                     $score = filter_var($score_str, FILTER_VALIDATE_INT);

                     if ($cand_id === false || !isset($candidates_map[$cand_id])) {
                         throw new Exception("Invalid candidate ID ($cand_id_str) submitted for scoring.");
                     }
                     if ($score === false || $score < $min_score || $score > $max_score) {
                         throw new Exception("Invalid score ($score_str) submitted for candidate ($cand_id). Must be between $min_score and $max_score.");
                     }
                     
                     $scored_votes[$cand_id] = $score;
                     $has_at_least_one_score = true;
                 }

                 if (!$has_at_least_one_score) {
                     throw new Exception("Please assign a score to at least one candidate for score voting.");
                 }

                 $vote_data_processed = json_encode($scored_votes); // Store as JSON object (candidate_id: score)
                 $validation_passed = true;
             } else {
                  throw new Exception("Invalid data format or no candidates scored for score voting.");
             }
             break;

        default:
            throw new Exception("Unsupported voting method encountered during submission.");
    }

    // Ensure vote_data is set (even if empty JSON array/object for some methods)
    if (!$validation_passed || $vote_data_processed === null) {
         throw new Exception("Vote data validation failed or processing error.");
    }

    // 4. Generate Confirmation Code
    $confirmation_code = strtoupper(bin2hex(random_bytes(8))); // Example: 16-char hex code

    // 5. Insert the Vote into the Votes table
    $sql_insert_vote = "INSERT INTO Votes (ElectionID, VoterID, VoteData, ConfirmationCode, Timestamp) VALUES (?, ?, ?, ?, NOW())";
    $stmt_insert = $conn->prepare($sql_insert_vote);
    if (!$stmt_insert) throw new Exception("Database prepare error (insert vote): " . $conn->error);

    // VoteData is always stored as TEXT (string or JSON string)
    $stmt_insert->bind_param("iiss", $election_id, $user_id, $vote_data_processed, $confirmation_code);
    if (!$stmt_insert->execute()) throw new Exception("Database error inserting vote: " . $stmt_insert->error);
    $stmt_insert->close();

    // 6. Update the EligibleVoters table to mark as voted
    $sql_update_voted = "UPDATE EligibleVoters SET HasVoted = TRUE WHERE ElectionID = ? AND UserID = ?";
    $stmt_update = $conn->prepare($sql_update_voted);
    if (!$stmt_update) throw new Exception("Database prepare error (update status): " . $conn->error);

    $stmt_update->bind_param("ii", $election_id, $user_id);
    if (!$stmt_update->execute()) throw new Exception("Database error updating voting status: " . $stmt_update->error);

    if ($stmt_update->affected_rows !== 1) {
        throw new Exception("Failed to update voting status. Vote may not have been fully recorded.");
    }
    $stmt_update->close();

    // 7. Commit the transaction
    $conn->commit();

    // --- Success ---
    $_SESSION['dashboard_message'] = "Your vote has been successfully submitted! Your confirmation code is: " . $confirmation_code;
    $_SESSION['dashboard_status'] = "success";
    header("Location: ../dashboard.php");
    exit();

} catch (Exception $e) {
    // --- Error Handling ---
    $conn->rollback(); // Rollback transaction on any error

    $error_message = $e->getMessage(); // Get the specific error message
    error_log("Vote submission error for UserID $user_id, ElectionID $election_id: $error_message"); // Log the error

    // Set session message to display on the voting page
    $_SESSION['vote_message'] = "Error submitting vote: " . $error_message;
    $_SESSION['vote_status'] = "error";

    // Redirect back to the specific voting page
    header("Location: ../vote.php?election_id=" . $election_id);
    exit();

} finally {
    // Ensure connection is closed
     if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) {
       $conn->close();
     }
}

?>
