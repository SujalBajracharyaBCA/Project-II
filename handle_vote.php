<?php
// Start the session to access logged-in user data and store messages
session_start();

// --- Authentication Check ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Voter') {
    // Should not happen if vote.php is working, but good practice
    $_SESSION['login_message'] = "Authentication required to vote.";
    header("Location: ../login.php");
    exit();
}

// Include the database connection file
require_once 'db_connect.php'; // Adjust path if necessary

// Get user ID from session
$user_id = $_SESSION['user_id'];

// --- Check Request Method ---
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    // Redirect if accessed directly or via GET
    header("Location: ../dashboard.php");
    exit();
}

// --- Retrieve POST Data ---
$election_id = isset($_POST['election_id']) ? (int)$_POST['election_id'] : 0;
$vote_data_raw = isset($_POST['vote_data']) ? $_POST['vote_data'] : null; // Can be string (FPTP) or array (others)
$submitted_voting_method = isset($_POST['voting_method']) ? $_POST['voting_method'] : ''; // Get method submitted by form

// --- Basic Input Validation ---
if ($election_id <= 0 || $vote_data_raw === null) {
    $_SESSION['vote_message'] = "Invalid submission data.";
    $_SESSION['vote_status'] = "error";
    // Redirect back to the specific voting page if possible, otherwise dashboard
    header("Location: " . ($election_id > 0 ? "../vote.php?election_id=" . $election_id : "../dashboard.php"));
    exit();
}

// --- Database Transaction and Verification ---
$conn->begin_transaction(); // Start transaction

$error_message = null;
$election = null;
$candidates_map = []; // Store valid candidate IDs for validation: [id => true]

try {
    // 1. Re-verify Election Status, Dates, Eligibility, and Voting Status (within transaction)
    // Use SELECT ... FOR UPDATE on EligibleVoters to lock the row and prevent race conditions
    $sql_verify = "SELECT
                     e.ElectionID, e.Title, e.StartDate, e.EndDate, e.Status AS ElectionStatus, e.VotingMethod,
                     ev.HasVoted
                   FROM Elections e
                   JOIN EligibleVoters ev ON e.ElectionID = ev.ElectionID
                   WHERE e.ElectionID = ? AND ev.UserID = ?
                   FOR UPDATE"; // Lock the EligibleVoters row for this user/election

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

    // Check status, dates, and if already voted
    $now = date('Y-m-d H:i:s');
    if ($election['ElectionStatus'] !== 'Active') throw new Exception("Election is not active.");
    if ($now < $election['StartDate']) throw new Exception("Voting has not started yet.");
    if ($now > $election['EndDate']) throw new Exception("Voting has ended.");
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
            if (is_numeric($vote_data_raw) && isset($candidates_map[(int)$vote_data_raw])) {
                $vote_data_processed = (int)$vote_data_raw; // Store the single candidate ID
                $validation_passed = true;
            } else {
                 throw new Exception("Invalid selection for FPTP voting.");
            }
            break;

        case 'Approval':
            if (is_array($vote_data_raw)) {
                $approved_ids = [];
                foreach ($vote_data_raw as $cand_id) {
                    if (is_numeric($cand_id) && isset($candidates_map[(int)$cand_id])) {
                        $approved_ids[] = (int)$cand_id; // Add valid IDs
                    } else {
                         throw new Exception("Invalid candidate ID submitted for Approval voting.");
                    }
                }
                // Allow empty approval array (voting for no one)
                $vote_data_processed = json_encode($approved_ids); // Store as JSON array
                $validation_passed = true;
            } else if ($vote_data_raw === null) { // Handle case where no boxes were checked
                 $vote_data_processed = json_encode([]); // Store empty array
                 $validation_passed = true;
            } else {
                 throw new Exception("Invalid data format for Approval voting.");
            }
            break;

        case 'RCV':
        case 'STV':
        case 'Condorcet':
            if (is_array($vote_data_raw)) {
                $ranked_votes = [];
                $used_ranks = [];
                $max_rank = count($candidates_map);
                foreach ($vote_data_raw as $cand_id_str => $rank_str) {
                    // Skip empty ranks
                    if (trim($rank_str) === '') continue;

                    $cand_id = filter_var($cand_id_str, FILTER_VALIDATE_INT);
                    $rank = filter_var($rank_str, FILTER_VALIDATE_INT);

                    // Check if candidate ID is valid for this election
                    if ($cand_id === false || !isset($candidates_map[$cand_id])) {
                        throw new Exception("Invalid candidate ID ($cand_id_str) submitted for ranking.");
                    }
                    // Check if rank is a valid number within range
                    if ($rank === false || $rank < 1 || $rank > $max_rank) {
                        throw new Exception("Invalid rank ($rank_str) submitted. Must be between 1 and $max_rank.");
                    }
                    // Check if rank is unique
                    if (isset($used_ranks[$rank])) {
                         throw new Exception("Duplicate rank ($rank) submitted. Ranks must be unique.");
                    }

                    $ranked_votes[$cand_id] = $rank; // Store valid rank
                    $used_ranks[$rank] = true;
                }
                 // Allow empty ranking (voting for no one ranked)
                $vote_data_processed = json_encode($ranked_votes); // Store as JSON object {cand_id: rank, ...}
                $validation_passed = true;
            } else {
                 throw new Exception("Invalid data format for ranked voting.");
            }
            break;

         case 'Score':
             if (is_array($vote_data_raw)) {
                 $scored_votes = [];
                 $min_score = 0; // Define score range
                 $max_score = 10;
                 foreach ($vote_data_raw as $cand_id_str => $score_str) {
                     // Skip empty scores
                     if (trim($score_str) === '') continue;

                     $cand_id = filter_var($cand_id_str, FILTER_VALIDATE_INT);
                     $score = filter_var($score_str, FILTER_VALIDATE_INT); // Scores are usually integers

                     // Check if candidate ID is valid
                     if ($cand_id === false || !isset($candidates_map[$cand_id])) {
                         throw new Exception("Invalid candidate ID ($cand_id_str) submitted for scoring.");
                     }
                     // Check if score is valid number within range
                     if ($score === false || $score < $min_score || $score > $max_score) {
                         throw new Exception("Invalid score ($score_str) submitted. Must be between $min_score and $max_score.");
                     }
                     $scored_votes[$cand_id] = $score; // Store valid score
                 }
                 // Allow empty scoring (giving no scores)
                 $vote_data_processed = json_encode($scored_votes); // Store as JSON object {cand_id: score, ...}
                 $validation_passed = true;
             } else {
                  throw new Exception("Invalid data format for score voting.");
             }
             break;

        default:
            throw new Exception("Unsupported voting method encountered during submission.");
    }

    if (!$validation_passed || $vote_data_processed === null) {
         throw new Exception("Vote data validation failed.");
    }

    // 4. Generate Confirmation Code
    $confirmation_code = strtoupper(bin2hex(random_bytes(8))); // Example: 16-char hex code

    // 5. Insert the Vote into the Votes table
    $sql_insert_vote = "INSERT INTO Votes (ElectionID, VoterID, VoteData, ConfirmationCode, Timestamp) VALUES (?, ?, ?, ?, NOW())";
    $stmt_insert = $conn->prepare($sql_insert_vote);
    if (!$stmt_insert) throw new Exception("Database prepare error (insert vote): " . $conn->error);

    $stmt_insert->bind_param("iiss", $election_id, $user_id, $vote_data_processed, $confirmation_code);
    if (!$stmt_insert->execute()) throw new Exception("Database error inserting vote: " . $stmt_insert->error);
    $stmt_insert->close();

    // 6. Update the EligibleVoters table to mark as voted
    $sql_update_voted = "UPDATE EligibleVoters SET HasVoted = TRUE WHERE ElectionID = ? AND UserID = ?";
    $stmt_update = $conn->prepare($sql_update_voted);
    if (!$stmt_update) throw new Exception("Database prepare error (update status): " . $conn->error);

    $stmt_update->bind_param("ii", $election_id, $user_id);
    if (!$stmt_update->execute()) throw new Exception("Database error updating voting status: " . $stmt_update->error);

    // Check if the update was successful (optional but good)
    if ($stmt_update->affected_rows !== 1) {
        // This shouldn't happen if the initial SELECT FOR UPDATE worked, but as a safeguard:
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

    // Set session message to display on the voting page
    $_SESSION['vote_message'] = "Error submitting vote: " . $error_message;
    $_SESSION['vote_status'] = "error";

    // Redirect back to the specific voting page
    header("Location: ../vote.php?election_id=" . $election_id);
    exit();

} finally {
    // Ensure connection is closed even if exceptions occurred (though rollback handles most)
     if (isset($conn) && $conn instanceof mysqli && $conn->thread_id) {
       $conn->close();
     }
}

?>
