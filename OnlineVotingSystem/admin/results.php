<?php
// admin/results.php
session_start();
require_once 'includes/db_connect.php'; // Ensure this path is correct

// --- Authentication & Authorization ---
// Allow Admins. Consider allowing Voters too based on election settings later.
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['Admin', 'Voter'])) { // Example: Allow Voters too
    $_SESSION['login_message'] = "Access denied. Please log in.";
    // Determine redirect based on where the denial happens (e.g., trying to access admin link vs voter link)
    header("Location: " . ($_SESSION['user_role'] === 'Admin' ? 'login.php' : '../login.php'));
    exit();
}
$is_admin = ($_SESSION['user_role'] === 'Admin');
$username = $_SESSION['username']; // For display

// --- Get Election ID ---
if (!isset($_GET['election_id']) || !filter_var($_GET['election_id'], FILTER_VALIDATE_INT)) {
    $_SESSION['message'] = "Invalid or missing election ID.";
    $_SESSION['message_status'] = "error";
    header("Location: " . ($is_admin ? 'election_management.php' : '../dashboard.php')); // Redirect appropriately
    exit();
}
$election_id = (int)$_GET['election_id'];

// --- Fetch Election Details ---
$election = null;
$candidates = [];
$votes = [];
$results = []; // Final calculated results will be stored here
$error_message = null;
$total_votes_cast = 0; // Number of actual vote records
$total_eligible = 0;   // Total voters eligible for this election
$voted_count = 0;      // Number of unique eligible voters who cast a ballot

$sql_election = "SELECT ElectionID, Title, Description, Status, VotingMethod, EndDate FROM Elections WHERE ElectionID = ?";
$stmt_election = $conn->prepare($sql_election);

if ($stmt_election) {
    $stmt_election->bind_param("i", $election_id);
    $stmt_election->execute();
    $result_election = $stmt_election->get_result();
    if ($result_election->num_rows === 1) {
        $election = $result_election->fetch_assoc();

        // --- Authorization Check (Beyond basic login) ---
        // Check if the election is 'Closed' or if the user has permission to view results early (e.g., Admin)
        if ($election['Status'] !== 'Closed' && !$is_admin) {
            // Non-admins cannot view results until the election is officially closed
            $_SESSION['dashboard_message'] = "Results for this election are not yet available.";
            $_SESSION['dashboard_status'] = "info";
            header("Location: ../dashboard.php");
            exit();
        }
        // Future enhancement: Check election-specific result visibility settings (public, voters_only, private)

    } else {
        $error_message = "Election not found.";
    }
    $stmt_election->close();
} else {
    $error_message = "Database error fetching election details: " . $conn->error;
    error_log($error_message);
}

// --- Fetch Candidates and Votes if Election Found and Accessible ---
if ($election && !$error_message) {
    // Fetch Candidates
    // Store candidates keyed by ID for easy lookup
    $sql_candidates = "SELECT CandidateID, Name FROM Candidates WHERE ElectionID = ? ORDER BY DisplayOrder, Name";
    $stmt_candidates = $conn->prepare($sql_candidates);
    if ($stmt_candidates) {
        $stmt_candidates->bind_param("i", $election_id);
        $stmt_candidates->execute();
        $result_candidates = $stmt_candidates->get_result();
        while ($row = $result_candidates->fetch_assoc()) {
            $candidates[$row['CandidateID']] = $row;
        }
        $stmt_candidates->close();
    } else {
        $error_message = "Error fetching candidates: " . $conn->error;
        error_log($error_message);
    }

    // Fetch Raw Votes (VoteData is JSON string)
    // For ranked methods, VoteData would be like '["1", "3", "2"]' (candidate IDs)
    // For score methods, VoteData would be like '{"1":5, "2":3, "3":1}' (candidate ID => score)
    $sql_votes = "SELECT VoteID, VoteData, VoterID FROM Votes WHERE ElectionID = ?";
    $stmt_votes = $conn->prepare($sql_votes);
    if ($stmt_votes) {
        $stmt_votes->bind_param("i", $election_id);
        $stmt_votes->execute();
        $result_votes = $stmt_votes->get_result();
        $unique_voters = [];
        while ($row = $result_votes->fetch_assoc()) {
            $votes[] = $row;
            $unique_voters[$row['VoterID']] = true; // Track unique voters who cast a ballot
        }
        $total_votes_cast = count($votes); // Total number of vote records
        $voted_count = count($unique_voters); // Number of unique voters who actually cast a ballot
        $stmt_votes->close();
    } else {
        $error_message = "Error fetching votes: " . $conn->error;
        error_log($error_message);
    }

     // Fetch Turnout Info (Total Eligible Voters)
     $sql_turnout = "SELECT COUNT(*) AS TotalEligible FROM EligibleVoters WHERE ElectionID = ?";
    $stmt_turnout = $conn->prepare($sql_turnout);
     if ($stmt_turnout) {
         $stmt_turnout->bind_param("i", $election_id);
         $stmt_turnout->execute();
         $result_turnout = $stmt_turnout->get_result();
         $turnout_data = $result_turnout->fetch_assoc();
         $total_eligible = $turnout_data['TotalEligible'] ?? 0;
         $stmt_turnout->close();
     } else {
         $error_message = ($error_message ? $error_message . "<br>" : '') . "Error fetching turnout info: " . $conn->error;
         error_log("Error fetching turnout info: " . $conn->error);
     }


    // --- Helper Functions for Voting Method Calculations ---

    /**
     * Calculates results for First-Past-The-Post (FPTP) voting.
     * Each vote selects a single candidate. The candidate with the most votes wins.
     * @param array $raw_votes Array of vote records (each 'VoteData' is a single candidate ID).
     * @param array $candidates Associative array of candidates (ID => ['Name' => 'CandidateName']).
     * @param int $total_votes_cast Total number of vote records.
     * @return array Calculated results (candidate_id => ['name' => ..., 'votes' => ..., 'percentage' => ...]).
     */
    function calculateFPTPResults($raw_votes, $candidates, $total_votes_cast) {
        $results = [];
        // Initialize results with candidate names and zero votes
        foreach ($candidates as $id => $candidate) {
            $results[$id] = ['name' => $candidate['Name'], 'votes' => 0, 'percentage' => 0];
        }

        foreach ($raw_votes as $vote) {
            $candidate_id = (int)$vote['VoteData']; // FPTP stores just the ID
            if (isset($results[$candidate_id])) {
                $results[$candidate_id]['votes']++;
            }
        }

        // Calculate percentages
        if ($total_votes_cast > 0) {
            foreach ($results as $id => $data) {
                $results[$id]['percentage'] = round(($data['votes'] / $total_votes_cast) * 100, 2);
            }
        }
        // Sort by votes descending
        uasort($results, function ($a, $b) {
            return $b['votes'] <=> $a['votes'];
        });
        return $results;
    }

    /**
     * Calculates results for Approval voting.
     * Voters can approve of any number of candidates. The candidate with the most approvals wins.
     * @param array $raw_votes Array of vote records (each 'VoteData' is a JSON array of approved candidate IDs).
     * @param array $candidates Associative array of candidates (ID => ['Name' => 'CandidateName']).
     * @param int $voted_count The number of unique voters who cast a ballot.
     * @return array Calculated results (candidate_id => ['name' => ..., 'votes' => ..., 'percentage' => ...]).
     */
    function calculateApprovalResults($raw_votes, $candidates, $voted_count) {
        $results = [];
        foreach ($candidates as $id => $candidate) {
            $results[$id] = ['name' => $candidate['Name'], 'votes' => 0, 'percentage' => 0];
        }

        foreach ($raw_votes as $vote) {
            $approved_ids = json_decode($vote['VoteData'], true);
            if (is_array($approved_ids)) {
                foreach ($approved_ids as $candidate_id) {
                    if (isset($results[$candidate_id])) {
                        $results[$candidate_id]['votes']++; // 'votes' here means approvals
                    }
                }
            }
        }

        // Calculate percentages (based on total unique voters, not total approvals)
        if ($voted_count > 0) {
            foreach ($results as $id => $data) {
                $results[$id]['percentage'] = round(($data['votes'] / $voted_count) * 100, 2);
            }
        }
        // Sort by approvals descending
        uasort($results, function ($a, $b) {
            return $b['votes'] <=> $a['votes'];
        });
        return $results;
    }

    /**
     * Calculates results for Ranked Choice Voting (RCV), single-winner variant.
     * Ballots are ranked. If no candidate has a majority, the candidate with the fewest votes is eliminated,
     * and their votes are redistributed to the next valid preference until a majority is reached.
     * @param array $raw_votes Array of vote records (each 'VoteData' is a JSON array of ranked candidate IDs).
     * @param array $candidates Associative array of candidates (ID => ['Name' => 'CandidateName']).
     * @return array Calculated results (candidate_id => ['name' => ..., 'votes' => ..., 'percentage' => ...]).
     */
    function calculateRCVResults($raw_votes, $candidates) {
        $ballots = [];
        foreach ($raw_votes as $vote) {
            // Decode ranked vote data. Ensure it's an array of integers.
            $ranked_ballot = array_map('intval', json_decode($vote['VoteData'], true) ?? []);
            // Filter out invalid candidate IDs (those not in the current election)
            $valid_ballot = array_filter($ranked_ballot, fn($id) => isset($candidates[$id]));
            if (!empty($valid_ballot)) {
                $ballots[] = $valid_ballot;
            }
        }

        $active_candidates = array_keys($candidates);
        $total_valid_ballots = count($ballots);
        $results_summary = []; // To store results round by round

        if ($total_valid_ballots === 0 || empty($active_candidates)) {
            foreach ($candidates as $id => $candidate) {
                $results_summary[$id] = ['name' => $candidate['Name'], 'votes' => 0, 'percentage' => 0, 'eliminated' => false];
            }
            return $results_summary;
        }

        while (true) {
            $round_votes = [];
            foreach ($active_candidates as $cid) {
                $round_votes[$cid] = 0; // Initialize votes for this round
            }

            $current_active_ballots = 0; // Number of ballots still active in this round
            // Count first preferences for active candidates
            foreach ($ballots as $ballot) {
                foreach ($ballot as $preference_id) {
                    if (in_array($preference_id, $active_candidates)) {
                        $round_votes[$preference_id]++;
                        $current_active_ballots++;
                        break; // Count only the highest active preference on each ballot
                    }
                }
            }

            // Determine majority threshold
            $majority_threshold = floor($current_active_ballots / 2) + 1;
            $winner_found = false;

            // Check for winner
            foreach ($round_votes as $cid => $votes_count) {
                if ($votes_count >= $majority_threshold) {
                    // Winner found!
                    $results_summary = [];
                    foreach ($active_candidates as $ac_id) {
                        $results_summary[$ac_id] = [
                            'name' => $candidates[$ac_id]['Name'],
                            'votes' => $round_votes[$ac_id],
                            'percentage' => round(($round_votes[$ac_id] / $current_active_ballots) * 100, 2),
                            'eliminated' => false
                        ];
                    }
                    // Sort by votes descending for final presentation
                    uasort($results_summary, function ($a, $b) { return $b['votes'] <=> $a['votes']; });
                    return $results_summary;
                }
            }

            // If no winner, eliminate lowest candidate
            if (count($active_candidates) <= 1) {
                // If only one candidate left, they are the winner (or no winner if zero votes)
                $results_summary = [];
                if (!empty($active_candidates)) {
                    $final_winner_id = reset($active_candidates);
                    $results_summary[$final_winner_id] = [
                        'name' => $candidates[$final_winner_id]['Name'],
                        'votes' => $round_votes[$final_winner_id],
                        'percentage' => ($current_active_ballots > 0) ? round(($round_votes[$final_winner_id] / $current_active_ballots) * 100, 2) : 0,
                        'eliminated' => false
                    ];
                }
                foreach ($candidates as $id => $candidate) {
                    if (!isset($results_summary[$id])) {
                         $results_summary[$id] = ['name' => $candidate['Name'], 'votes' => 0, 'percentage' => 0, 'eliminated' => true];
                    }
                }
                uasort($results_summary, function ($a, $b) { return $b['votes'] <=> $a['votes']; });
                return $results_summary;
            }

            // Find candidate(s) to eliminate (lowest votes)
            $min_votes = min($round_votes);
            $candidates_to_eliminate = [];
            foreach ($round_votes as $cid => $votes_count) {
                if ($votes_count == $min_votes) {
                    $candidates_to_eliminate[] = $cid;
                }
            }

            // If there's a tie for lowest, eliminate all tied candidates (simplification, real RCV might have tie-breaking rules)
            foreach ($candidates_to_eliminate as $elim_cid) {
                // Mark candidate as eliminated for final result display
                if (!isset($results_summary[$elim_cid])) {
                    $results_summary[$elim_cid] = [
                        'name' => $candidates[$elim_cid]['Name'],
                        'votes' => $round_votes[$elim_cid],
                        'percentage' => ($current_active_ballots > 0) ? round(($round_votes[$elim_cid] / $current_active_ballots) * 100, 2) : 0,
                        'eliminated' => true
                    ];
                } else {
                    $results_summary[$elim_cid]['eliminated'] = true;
                }
                $active_candidates = array_diff($active_candidates, [$elim_cid]); // Remove from active list
            }

            // If no active candidates left (e.g., all eliminated due to ties and no majority reached),
            // this is an edge case, but we should break to prevent infinite loop.
            if (empty($active_candidates)) {
                 foreach ($candidates as $id => $candidate) {
                     if (!isset($results_summary[$id])) {
                         $results_summary[$id] = ['name' => $candidate['Name'], 'votes' => 0, 'percentage' => 0, 'eliminated' => true];
                     }
                 }
                uasort($results_summary, function ($a, $b) { return $b['votes'] <=> $a['votes']; });
                return $results_summary; // No clear winner by majority
            }
        }
    }

    /**
     * Calculates results for Single Transferable Vote (STV), simplified to a single-winner variant (effectively RCV).
     * Full multi-winner STV is significantly more complex and would require a dedicated library.
     * For simplicity, this function delegates to RCV logic.
     * @param array $raw_votes Array of vote records (each 'VoteData' is a JSON array of ranked candidate IDs).
     * @param array $candidates Associative array of candidates (ID => ['Name' => 'CandidateName']).
     * @return array Calculated results (candidate_id => ['name' => ..., 'votes' => ..., 'percentage' => ...]).
     */
    function calculateSTVResults($raw_votes, $candidates) {
        // NOTE: A full multi-winner STV implementation is highly complex and typically
        // requires fractional vote transfers and advanced quota calculations.
        // For the purpose of this single-winner context, we will treat it like RCV.
        // For a true STV, a dedicated election library would be necessary.
        return calculateRCVResults($raw_votes, $candidates);
    }


    /**
     * Calculates results for Score voting (also known as Range Voting or Utilitarian Voting).
     * Voters give each candidate a score (e.g., 0-5). The candidate with the highest total score wins.
     * @param array $raw_votes Array of vote records (each 'VoteData' is a JSON object of candidate ID => score).
     * @param array $candidates Associative array of candidates (ID => ['Name' => 'CandidateName']).
     * @param int $total_votes_cast Total number of vote records (for percentage base, if applicable).
     * @return array Calculated results (candidate_id => ['name' => ..., 'votes' => ..., 'percentage' => ...]).
     */
    function calculateScoreResults($raw_votes, $candidates, $total_votes_cast) {
        $results = [];
        foreach ($candidates as $id => $candidate) {
            $results[$id] = ['name' => $candidate['Name'], 'votes' => 0, 'percentage' => 0]; // 'votes' here will store total score
        }

        foreach ($raw_votes as $vote) {
            $scores = json_decode($vote['VoteData'], true);
            if (is_array($scores)) {
                foreach ($scores as $candidate_id_str => $score) {
                    $candidate_id = (int)$candidate_id_str;
                    if (isset($results[$candidate_id]) && is_numeric($score)) {
                        $results[$candidate_id]['votes'] += (int)$score; // Summing up scores
                    }
                }
            }
        }

        // Percentage calculation for score voting can be complex (e.g., % of max possible score)
        // Here, we'll calculate percentage relative to the highest score achieved by any candidate for simpler display.
        $max_total_score = 0;
        foreach ($results as $data) {
            if ($data['votes'] > $max_total_score) {
                $max_total_score = $data['votes'];
            }
        }

        if ($max_total_score > 0) {
            foreach ($results as $id => $data) {
                $results[$id]['percentage'] = round(($data['votes'] / $max_total_score) * 100, 2);
            }
        }

        // Sort by total score descending
        uasort($results, function ($a, $b) {
            return $b['votes'] <=> $a['votes'];
        });
        return $results;
    }

    /**
     * Calculates results for the Condorcet method.
     * Determines if a Condorcet winner exists (a candidate who wins every head-to-head comparison).
     * Returns the winner if found, otherwise notes that no clear winner exists.
     * @param array $raw_votes Array of vote records (each 'VoteData' is a JSON array of ranked candidate IDs).
     * @param array $candidates Associative array of candidates (ID => ['Name' => 'CandidateName']).
     * @return array Calculated results (candidate_id => ['name' => ..., 'votes' => ..., 'percentage' => ...], potentially with 'is_condorcet_winner' flag).
     */
    function calculateCondorcetResults($raw_votes, $candidates) {
        $ballots = [];
        foreach ($raw_votes as $vote) {
            // Decode ranked vote data. Ensure it's an array of integers.
            $ranked_ballot = array_map('intval', json_decode($vote['VoteData'], true) ?? []);
            // Filter out invalid candidate IDs (those not in the current election)
            $valid_ballot = array_filter($ranked_ballot, fn($id) => isset($candidates[$id]));
            if (!empty($valid_ballot)) {
                $ballots[] = $valid_ballot;
            }
        }

        $candidate_ids = array_keys($candidates);
        $num_candidates = count($candidate_ids);
        $pair_wins = []; // Store wins for each pair: [winner_id][loser_id] => count
        $candidate_results = []; // Initialize for final output

        foreach ($candidates as $id => $data) {
            $candidate_results[$id] = ['name' => $data['Name'], 'votes' => 0, 'percentage' => 0, 'is_condorcet_winner' => false, 'note' => ''];
        }

        if ($num_candidates < 2) {
             foreach ($candidate_results as $id => $res) {
                 $candidate_results[$id]['note'] = 'Not enough candidates for pairwise comparison.';
             }
            return $candidate_results;
        }

        // Initialize pairwise comparison matrix
        foreach ($candidate_ids as $c1) {
            foreach ($candidate_ids as $c2) {
                if ($c1 !== $c2) {
                    $pair_wins[$c1][$c2] = 0; // c1 beats c2
                }
            }
        }

        // Process each ballot for pairwise comparisons
        foreach ($ballots as $ballot) {
            // Create a temporary preference map for this ballot: preference_rank => candidate_id
            $preference_map = [];
            foreach ($ballot as $rank_idx => $cid) {
                $preference_map[$cid] = $rank_idx; // Lower index means higher preference
            }

            // Compare every pair of candidates on this ballot
            foreach ($candidate_ids as $c1) {
                foreach ($candidate_ids as $c2) {
                    if ($c1 === $c2 || !isset($preference_map[$c1]) || !isset($preference_map[$c2])) {
                        continue;
                    }

                    if ($preference_map[$c1] < $preference_map[$c2]) {
                        // c1 is preferred over c2 on this ballot
                        $pair_wins[$c1][$c2]++;
                    } else {
                        // c2 is preferred over c1 on this ballot
                        $pair_wins[$c2][$c1]++;
                    }
                }
            }
        }

        // Determine the Condorcet winner
        $condorcet_winner_id = null;
        foreach ($candidate_ids as $candidate_id) {
            $wins_all_pairwise = true;
            foreach ($candidate_ids as $opponent_id) {
                if ($candidate_id === $opponent_id) {
                    continue;
                }

                $candidate_vs_opponent = $pair_wins[$candidate_id][$opponent_id];
                $opponent_vs_candidate = $pair_wins[$opponent_id][$candidate_id];

                if ($candidate_vs_opponent <= $opponent_vs_candidate) {
                    // This candidate did not beat this opponent, or it was a tie
                    $wins_all_pairwise = false;
                    break;
                }
            }

            if ($wins_all_pairwise) {
                $condorcet_winner_id = $candidate_id;
                break; // Found the Condorcet winner
            }
        }

        if ($condorcet_winner_id !== null) {
            $candidate_results[$condorcet_winner_id]['is_condorcet_winner'] = true;
            // For Condorcet, 'votes' and 'percentage' are less meaningful in the traditional sense.
            // We can show the total number of ballots considered.
            foreach ($candidate_results as $id => $data) {
                $candidate_results[$id]['votes'] = ($id == $condorcet_winner_id) ? count($ballots) : 0; // Simply indicates "won with these many ballots"
                $candidate_results[$id]['percentage'] = ($id == $condorcet_winner_id && count($ballots) > 0) ? 100 : 0;
            }
            uasort($candidate_results, function ($a, $b) { return $b['is_condorcet_winner'] <=> $a['is_condorcet_winner']; });
        } else {
            // No Condorcet winner (cycle detected or ties)
            // For display, we might want to show pairwise wins or just state no winner.
            // For simplicity, we'll indicate no clear winner and show pairwise counts.
            // A more advanced display would show the full matrix or use a tie-breaking method (e.g., Schulze, Ranked Pairs).
            foreach ($candidate_results as $id => $data) {
                $candidate_results[$id]['note'] = 'No Condorcet winner found (cycle or ties).';
                // You could sum up pairwise wins for a 'score' if desired
                $total_pairwise_wins = 0;
                foreach ($candidate_ids as $opponent_id) {
                    if ($id !== $opponent_id && isset($pair_wins[$id][$opponent_id])) {
                        $total_pairwise_wins += $pair_wins[$id][$opponent_id];
                    }
                }
                $candidate_results[$id]['votes'] = $total_pairwise_wins; // Total pairwise wins
                // Percentage here is tricky; could be % of max possible pairwise wins
                // For now, will leave it at 0 if no winner.
                $candidate_results[$id]['percentage'] = 0;
            }
            uasort($candidate_results, function ($a, $b) { return $b['votes'] <=> $a['votes']; }); // Sort by pairwise wins
        }

        return $candidate_results;
    }


    // --- Calculate Results Based on Voting Method ---
    if (empty($error_message) && !empty($candidates)) {
        switch ($election['VotingMethod']) {
            case 'FPTP':
                $results = calculateFPTPResults($votes, $candidates, $total_votes_cast);
                break;

            case 'Approval':
                $results = calculateApprovalResults($votes, $candidates, $voted_count);
                break;

            case 'RCV':
                $results = calculateRCVResults($votes, $candidates);
                // RCV results array will contain 'eliminated' status, which can be used for display if needed
                break;

            case 'STV':
                // For single-winner scenarios, STV behaves like RCV.
                // A full multi-winner STV implementation is significantly more complex.
                $results = calculateSTVResults($votes, $candidates);
                break;

            case 'Score':
                $results = calculateScoreResults($votes, $candidates, $total_votes_cast);
                break;

            case 'Condorcet':
                $results = calculateCondorcetResults($votes, $candidates);
                // Condorcet results array might contain 'is_condorcet_winner' and 'note'
                break;

            default:
                $error_message = "Unknown or unsupported voting method: " . htmlspecialchars($election['VotingMethod']);
        }
    } elseif (empty($candidates)) {
         $error_message = "No candidates were found for this election, cannot calculate results.";
    }
}

$conn->close(); // Close the connection

// Calculate turnout percentage
$turnout_percentage = ($total_eligible > 0) ? round(($voted_count / $total_eligible) * 100, 2) : 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Election Results - <?php echo htmlspecialchars($election['Title'] ?? 'N/A'); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        /* Sidebar, responsive, table styles (copy from other admin pages) */
        .sidebar { width: 250px; transition: transform 0.3s ease-in-out; }
        .content { margin-left: 250px; transition: margin-left 0.3s ease-in-out; }
        @media (max-width: 768px) { .sidebar { transform: translateX(-100%); } .sidebar.open { transform: translateX(0); z-index: 40; } .content { margin-left: 0; } .sidebar-toggle { display: block; } .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); z-index: 30; } .sidebar.open + .overlay { display: block; } }
        @media (min-width: 769px) { .sidebar-toggle-close, .sidebar-toggle-open { display: none; } }
        th, td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
        th { background-color: #f8fafc; font-weight: 600; color: #4b5563; }
        tbody tr:hover { background-color: #f9fafb; }
        tbody tr:first-child { background-color: #ecfdf5; /* Light green for winner? */ }
        .winner-indicator { color: #10b981; /* green-500 */ margin-left: 0.5rem; }
        .eliminated-candidate { text-decoration: line-through; color: #6b7280; } /* gray-500 */
        /* Progress bar for percentages */
        .percentage-bar-bg { background-color: #e5e7eb; border-radius: 0.375rem; overflow: hidden; height: 1.25rem; /* h-5 */ width: 150px; position: relative; }
        .percentage-bar-fill { background-color: #60a5fa; /* blue-400 */ height: 100%; transition: width 0.5s ease-in-out; }
        .percentage-text { position: absolute; left: 0.5rem; right: 0.5rem; top: 0; bottom: 0; color: #1f2937; font-size: 0.75rem; line-height: 1.25rem; text-align: center; font-weight: 500; }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="overlay md:hidden"></div>

    <div class="flex min-h-screen">
        <?php if ($is_admin): ?>
        <aside class="sidebar bg-gradient-to-b from-gray-800 to-gray-900 text-white p-6 fixed h-full shadow-lg md:translate-x-0 z-40">
            <div class="flex justify-between items-center mb-8">
                <h2 class="text-2xl font-bold"><i class="fas fa-cogs mr-2"></i>Admin Panel</h2>
                <button class="sidebar-toggle-close md:hidden text-white focus:outline-none"><i class="fas fa-times text-xl"></i></button>
            </div>
            <nav>
                <a href="dashboard.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700"><i class="fas fa-tachometer-alt mr-2 w-5 text-center"></i>Dashboard</a>
                <a href="election_management.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700"><i class="fas fa-poll-h mr-2 w-5 text-center"></i>Elections</a>
                <a href="voting_monitor.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700"><i class="fas fa-tv mr-2 w-5 text-center"></i>Voting Monitor</a>
                <a href="results.php" class="block py-2.5 px-4 rounded transition duration-200 bg-indigo-600 font-semibold"><i class="fas fa-chart-bar mr-2 w-5 text-center"></i>Results</a>
                <a href="user_management.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700"><i class="fas fa-user-cog mr-2 w-5 text-center"></i>Admin Users</a>
                <a href="audit_logs.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700"><i class="fas fa-history mr-2 w-5 text-center"></i>Audit Logs</a>
                <a href="settings.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700"><i class="fas fa-sliders-h mr-2 w-5 text-center"></i>Settings</a>
                <a href="includes/logout.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-red-600 mt-8 text-red-300 hover:text-white"><i class="fas fa-sign-out-alt mr-2 w-5 text-center"></i>Logout</a>
            </nav>
        </aside>
        <?php endif; ?>

        <div class="<?php echo $is_admin ? 'content' : ''; ?> flex-1 p-6 md:p-10">
            <header class="bg-white shadow rounded-lg p-4 mb-6 flex justify-between items-center">
                 <?php if ($is_admin): ?>
                 <button class="sidebar-toggle-open md:hidden text-gray-600 focus:outline-none"><i class="fas fa-bars text-xl"></i></button>
                 <?php else: ?>
                 <a href="../dashboard.php" class="text-indigo-600 hover:text-indigo-800"><i class="fas fa-arrow-left mr-2"></i>Back to Dashboard</a>
                 <?php endif; ?>
                 <h1 class="text-xl md:text-2xl font-semibold text-gray-700">Election Results</h1>
                 <div>
                     <span class="text-gray-600 text-sm md:text-base">Welcome, <?php echo htmlspecialchars($username); ?>!</span>
                      <?php if (!$is_admin): // Add logout for voters if they access this page ?>
                           <a href="../includes/logout.php" class="ml-4 px-3 py-1 rounded bg-red-500 hover:bg-red-600 text-white text-sm transition duration-300">
                               <i class="fas fa-sign-out-alt mr-1"></i>Logout
                           </a>
                       <?php endif; ?>
                 </div>
             </header>

            <?php if ($error_message): ?>
                <div class='border px-4 py-3 rounded relative mb-4 bg-red-100 border-red-400 text-red-700' role='alert'>
                    <span class='block sm:inline'><?php echo htmlspecialchars($error_message); ?></span>
                </div>
            <?php else: ?>
                <div class="bg-white p-6 rounded-lg shadow border border-gray-200 mb-6">
                    <h2 class="text-2xl font-semibold text-gray-800 mb-3"><?php echo htmlspecialchars($election['Title']); ?></h2>
                    <p class="text-sm text-gray-500 mb-1">Method: <span class="font-medium"><?php echo htmlspecialchars($election['VotingMethod']); ?></span></p>
                    <p class="text-sm text-gray-500 mb-1">Status: <span class="font-medium <?php echo ($election['Status'] == 'Closed') ? 'text-red-600' : 'text-green-600'; ?>"><?php echo htmlspecialchars($election['Status']); ?></span></p>
                    <p class="text-sm text-gray-500 mb-3">Ended: <span class="font-medium"><?php echo date('M j, Y g:i A', strtotime($election['EndDate'])); ?></span></p>

                    <div class="flex justify-between items-center text-sm text-gray-700 pt-3 border-t">
                         <span>Total Eligible Voters: <strong class="text-lg"><?php echo $total_eligible; ?></strong></span>
                         <span>Voters Who Voted: <strong class="text-lg"><?php echo $voted_count; ?></strong></span>
                         <span>Turnout: <strong class="text-lg"><?php echo $turnout_percentage; ?>%</strong></span>
                         <span>Total Votes Recorded: <strong class="text-lg"><?php echo $total_votes_cast; ?></strong></span>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-lg shadow border border-gray-200 mb-6">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4">Results Summary</h3>
                     <?php if (!empty($results)): ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full bg-white">
                                <thead>
                                    <tr>
                                        <th>Rank</th>
                                        <th>Candidate</th>
                                        <th>Votes / Score / Status</th>
                                        <th>Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $rank = 1;
                                    $prev_value = null; // Can be votes or score
                                    $display_rank = 1;

                                    // Sort results for consistent ranking display across methods
                                    // RCV/STV: 'eliminated' true means lower rank
                                    // Condorcet: 'is_condorcet_winner' true means highest rank
                                    // For score and FPTP/Approval: sort by 'votes' (which is actually score/approvals)
                                    $sorted_results = $results;
                                    uasort($sorted_results, function ($a, $b) use ($election) {
                                        if ($election['VotingMethod'] === 'RCV' || $election['VotingMethod'] === 'STV') {
                                            // Eliminated candidates come last, otherwise by votes
                                            if (isset($a['eliminated']) && isset($b['eliminated'])) {
                                                if ($a['eliminated'] && !$b['eliminated']) return 1;
                                                if (!$a['eliminated'] && $b['eliminated']) return -1;
                                            }
                                        } elseif ($election['VotingMethod'] === 'Condorcet') {
                                            // Condorcet winner first, then by pairwise wins if displayed, otherwise no clear order
                                            if (isset($a['is_condorcet_winner']) && $a['is_condorcet_winner']) return -1;
                                            if (isset($b['is_condorcet_winner']) && $b['is_condorcet_winner']) return 1;
                                            // If no winner, or for non-winners, sort by pairwise 'votes' (sum of pairwise wins)
                                            return $b['votes'] <=> $a['votes'];
                                        }
                                        // Default sort by votes (or score) descending
                                        return $b['votes'] <=> $a['votes'];
                                    });


                                    foreach ($sorted_results as $id => $data):
                                        // Handle ties in rank display (for methods where 'votes' represents a count/score)
                                        $current_value = $data['votes'] ?? 0;
                                        if ($election['VotingMethod'] !== 'Condorcet' && $election['VotingMethod'] !== 'RCV' && $election['VotingMethod'] !== 'STV') {
                                            if ($prev_value !== null && $current_value < $prev_value) {
                                                $display_rank = $rank;
                                            }
                                        } else {
                                            // For RCV/STV/Condorcet, rank might be conceptual or based on elimination order/winner status
                                            // Simple rank for now; could be improved with round-by-round RCV info
                                            // Condorcet winner gets rank 1, others follow, no ties by value
                                            if (isset($data['is_condorcet_winner']) && $data['is_condorcet_winner']) {
                                                $display_rank = 1;
                                            } elseif (isset($data['eliminated']) && $data['eliminated']) {
                                                $display_rank = 'Eliminated'; // Special rank for RCV
                                            } else {
                                                // For RCV/STV, if not eliminated, it's a "live" candidate, but final ranks are tricky
                                                // Just a simple counter for now
                                                $display_rank = $rank;
                                            }
                                        }
                                        $is_eliminated = ($election['VotingMethod'] === 'RCV' || $election['VotingMethod'] === 'STV') && ($data['eliminated'] ?? false);
                                        $is_condorcet_winner = ($election['VotingMethod'] === 'Condorcet') && ($data['is_condorcet_winner'] ?? false);
                                    ?>
                                    <tr class="<?php echo $is_eliminated ? 'bg-gray-50' : ''; ?>">
                                        <td class="text-center">
                                            <?php
                                            if ($is_condorcet_winner) {
                                                echo '<i class="fas fa-crown winner-indicator" title="Condorcet Winner"></i>';
                                            } else if ($is_eliminated) {
                                                echo 'N/A'; // Or a small icon for eliminated
                                            } else {
                                                echo $display_rank;
                                            }
                                            ?>
                                        </td>
                                        <td class="font-medium text-gray-900 <?php echo $is_eliminated ? 'eliminated-candidate' : ''; ?>">
                                            <?php echo htmlspecialchars($data['name']); ?>
                                            <?php if ($display_rank == 1 && ($election['VotingMethod'] == 'FPTP' || $election['VotingMethod'] == 'Approval' || $election['VotingMethod'] == 'Score')) echo '<i class="fas fa-trophy winner-indicator" title="Winner"></i>'; ?>
                                        </td>
                                        <td>
                                            <?php
                                            if ($election['VotingMethod'] === 'Score') {
                                                echo number_format($data['votes']) . ' (Score)';
                                            } elseif ($election['VotingMethod'] === 'RCV' || $election['VotingMethod'] === 'STV') {
                                                if ($is_eliminated) {
                                                    echo number_format($data['votes']) . ' (Eliminated)';
                                                } else {
                                                    echo number_format($data['votes']) . ' (Votes)'; // Live votes in last round for RCV
                                                }
                                            } elseif ($election['VotingMethod'] === 'Condorcet') {
                                                echo ($is_condorcet_winner ? 'Condorcet Winner' : ($data['note'] ?? ''));
                                                // Optionally, show pairwise wins for non-winners for Condorcet
                                                if (!$is_condorcet_winner && isset($data['votes'])) {
                                                    echo ' (' . number_format($data['votes']) . ' Pairwise Wins)';
                                                }
                                            } else {
                                                echo number_format($data['votes']);
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php if (($election['VotingMethod'] === 'FPTP' || $election['VotingMethod'] === 'Approval' || $election['VotingMethod'] === 'Score') || ($election['VotingMethod'] === 'RCV' || $election['VotingMethod'] === 'STV' && !$is_eliminated)): ?>
                                                <div class="percentage-bar-bg">
                                                    <div class="percentage-bar-fill" style="width: <?php echo $data['percentage']; ?>%;"></div>
                                                    <div class="percentage-text"><?php echo $data['percentage']; ?>%</div>
                                                </div>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php
                                        $prev_value = $current_value;
                                        $rank++;
                                    endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                     <?php elseif ($election['Status'] == 'Closed'): ?>
                         <p class="text-center text-gray-500 py-4">No votes were cast in this election.</p>
                     <?php else: ?>
                          <p class="text-center text-gray-500 py-4">Results are being processed or the election is not yet closed.</p>
                     <?php endif; ?>
                 </div>

                 <?php
                 // Only show Chart for methods where a simple bar chart makes sense
                 $chart_eligible_methods = ['FPTP', 'Approval', 'Score'];
                 if (!empty($results) && in_array($election['VotingMethod'], $chart_eligible_methods)):
                 ?>
                 <div class="bg-white p-6 rounded-lg shadow border border-gray-200">
                      <h3 class="text-xl font-semibold text-gray-800 mb-4">Results Chart</h3>
                      <canvas id="resultsChart"></canvas>
                 </div>
                 <?php endif; ?>

             <?php endif; // End check for error message ?>

            <footer class="text-center text-gray-500 text-sm mt-8">
                &copy; <?php echo date("Y"); ?> Online Voting System. Admin Panel.
            </footer>
        </div>
    </div>

    <script>
        // Sidebar toggle script (copy from dashboard.php or other admin pages if needed)
        <?php if ($is_admin): ?>
        const sidebar = document.querySelector('.sidebar');
        const openButton = document.querySelector('.sidebar-toggle-open');
        const closeButton = document.querySelector('.sidebar-toggle-close');
        const overlay = document.querySelector('.overlay');

        function openSidebar() { sidebar.classList.add('open'); sidebar.classList.remove('-translate-x-full'); if(overlay) overlay.style.display = 'block'; }
        function closeSidebar() { sidebar.classList.remove('open'); sidebar.classList.add('-translate-x-full'); if(overlay) overlay.style.display = 'none'; }

        if (openButton) openButton.addEventListener('click', openSidebar);
        if (closeButton) closeButton.addEventListener('click', closeSidebar);
        if (overlay) overlay.addEventListener('click', closeSidebar);
        window.addEventListener('resize', () => {
            if (window.innerWidth >= 768) { if (sidebar.classList.contains('open')) closeSidebar(); sidebar.classList.remove('-translate-x-full'); }
            else if (!sidebar.classList.contains('open')) sidebar.classList.add('-translate-x-full');
        });
        if (window.innerWidth < 768) { sidebar.classList.add('-translate-x-full'); }
        <?php endif; ?>

         // Chart.js Implementation (Optional)
         <?php
         // Only pass data to chart if the method is chart-eligible and results are not empty
         if (!empty($results) && in_array($election['VotingMethod'], $chart_eligible_methods)):
             $chart_labels = array_column($results, 'name');
             $chart_data_values = array_column($results, 'votes');
             $chart_label_text = '';
             switch ($election['VotingMethod']) {
                 case 'FPTP': $chart_label_text = 'Votes'; break;
                 case 'Approval': $chart_label_text = 'Approvals'; break;
                 case 'Score': $chart_label_text = 'Total Score'; break;
             }
         ?>
         const ctx = document.getElementById('resultsChart');
         if (ctx) {
             const chartData = {
                 labels: <?php echo json_encode($chart_labels); ?>,
                 datasets: [{
                     label: '<?php echo $chart_label_text; ?>',
                     data: <?php echo json_encode($chart_data_values); ?>,
                     backgroundColor: [ // Add more colors if needed
                         'rgba(59, 130, 246, 0.7)', // blue-500
                         'rgba(16, 185, 129, 0.7)', // green-500
                         'rgba(239, 68, 68, 0.7)',  // red-500
                         'rgba(245, 158, 11, 0.7)', // amber-500
                         'rgba(139, 92, 246, 0.7)', // violet-500
                         'rgba(236, 72, 153, 0.7)', // pink-500
                     ],
                     borderColor: [
                          'rgba(59, 130, 246, 1)',
                          'rgba(16, 185, 129, 1)',
                          'rgba(239, 68, 68, 1)',
                          'rgba(245, 158, 11, 1)',
                          'rgba(139, 92, 246, 1)',
                          'rgba(236, 72, 153, 1)',
                     ],
                     borderWidth: 1
                 }]
             };

             new Chart(ctx, {
                 type: 'bar', // or 'pie', 'doughnut'
                 data: chartData,
                 options: {
                     responsive: true,
                     maintainAspectRatio: false, // Allow canvas to resize freely
                     scales: {
                         y: {
                             beginAtZero: true,
                              ticks: {
                                 precision: 0 // Ensure whole numbers for vote counts/scores
                             }
                         }
                     },
                     plugins: {
                         legend: {
                             display: false // Hide legend if only one dataset
                         }
                     }
                 }
             });
         }
         <?php endif; ?>
    </script>
</body>
</html>
