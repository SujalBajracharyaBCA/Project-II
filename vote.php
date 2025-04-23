<?php
// Start the session to access logged-in user data
session_start();

// --- Authentication Check ---
// Check if user is logged in and is a Voter
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Voter') {
    $_SESSION['login_message'] = "Please log in to vote.";
    header("Location: login.php");
    exit();
}

// Include the database connection file
require_once 'includes/db_connect.php';

// Get user information from session
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// --- Get Election ID from URL ---
if (!isset($_GET['election_id']) || !filter_var($_GET['election_id'], FILTER_VALIDATE_INT)) {
    // Invalid or missing election ID
    // Redirect to dashboard or show error
    $_SESSION['dashboard_message'] = "Invalid election specified."; // Create a session message for dashboard
    $_SESSION['dashboard_status'] = "error";
    header("Location: dashboard.php");
    exit();
}
$election_id = (int)$_GET['election_id'];

// --- Fetch Election Details and Eligibility ---
$election = null;
$candidates = [];
$error_message = null;
$can_vote = false;
$voting_method = '';

// Get current time
$now = date('Y-m-d H:i:s');

// Prepare SQL to get election details and check eligibility/voting status
$sql_check = "SELECT
                e.ElectionID, e.Title, e.Description, e.StartDate, e.EndDate, e.Status AS ElectionStatus, e.VotingMethod,
                ev.HasVoted
              FROM Elections e
              JOIN EligibleVoters ev ON e.ElectionID = ev.ElectionID
              WHERE e.ElectionID = ? AND ev.UserID = ?";

$stmt_check = $conn->prepare($sql_check);

if ($stmt_check) {
    $stmt_check->bind_param("ii", $election_id, $user_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows === 1) {
        $election = $result_check->fetch_assoc();
        $voting_method = $election['VotingMethod']; // Get the voting method

        // --- Validate Election Status and Voting Eligibility ---
        if ($election['ElectionStatus'] !== 'Active') {
            $error_message = "This election is not currently active.";
        } elseif ($now < $election['StartDate']) {
            $error_message = "Voting for this election has not started yet.";
        } elseif ($now > $election['EndDate']) {
            $error_message = "Voting for this election has ended.";
        } elseif ($election['HasVoted']) {
            $error_message = "You have already voted in this election.";
        } else {
            // All checks passed, user can vote
            $can_vote = true;

            // --- Fetch Candidates for this election ---
            $sql_candidates = "SELECT CandidateID, Name, Description FROM Candidates WHERE ElectionID = ? ORDER BY DisplayOrder, Name";
            $stmt_candidates = $conn->prepare($sql_candidates);
            if ($stmt_candidates) {
                $stmt_candidates->bind_param("i", $election_id);
                $stmt_candidates->execute();
                $result_candidates = $stmt_candidates->get_result();
                while ($row = $result_candidates->fetch_assoc()) {
                    $candidates[] = $row;
                }
                $stmt_candidates->close();

                if (empty($candidates)) {
                    $error_message = "No candidates found for this election.";
                    $can_vote = false; // Cannot vote if no candidates
                }
            } else {
                $error_message = "Could not retrieve candidate information.";
                $can_vote = false;
                // Log error: error_log("Prepare failed (fetch candidates): " . $conn->error);
            }
        }
    } else {
        // User is not eligible for this election or election doesn't exist
        $error_message = "You are not eligible to vote in this election, or the election does not exist.";
    }
    $stmt_check->close();
} else {
    // Handle prepare statement error
    $error_message = "Could not verify election eligibility. Please try again later.";
    // Log error: error_log("Prepare failed (check eligibility): " . $conn->error);
}

// Close DB connection only after all queries are done
$conn->close();

// If there was an error preventing voting, redirect back to dashboard with message
if (!$can_vote && $error_message) {
     $_SESSION['dashboard_message'] = $error_message;
     $_SESSION['dashboard_status'] = "error";
     header("Location: dashboard.php");
     exit();
} elseif (!$can_vote) { // Catchall if somehow $can_vote is false without specific error
    $_SESSION['dashboard_message'] = "You cannot vote in this election at this time.";
    $_SESSION['dashboard_status'] = "error";
    header("Location: dashboard.php");
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cast Your Vote - <?php echo htmlspecialchars($election['Title'] ?? 'Election'); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style>
        body { font-family: 'Inter', sans-serif; }
        /* Style for candidate cards or list items */
        .candidate-item {
            border: 1px solid #e2e8f0; /* gray-200 */
            padding: 1rem; /* p-4 */
            margin-bottom: 1rem; /* mb-4 */
            border-radius: 0.5rem; /* rounded-lg */
            background-color: #ffffff; /* bg-white */
            display: flex;
            align-items: center;
        }
        .candidate-item input {
            margin-right: 1rem; /* mr-4 */
            height: 1.25rem; /* h-5 */
            width: 1.25rem; /* w-5 */
        }
        .candidate-details {
            flex-grow: 1;
        }
        .candidate-name {
            font-weight: 600; /* font-semibold */
            color: #1f2937; /* gray-800 */
        }
        .candidate-desc {
            font-size: 0.875rem; /* text-sm */
            color: #6b7280; /* gray-500 */
            margin-top: 0.25rem; /* mt-1 */
        }
        /* Styles for Ranked Choice (if implemented) */
        .rank-input {
            width: 3rem; /* w-12 */
            text-align: center;
            margin-right: 1rem;
            border: 1px solid #ccc;
            padding: 0.25rem;
            border-radius: 0.25rem;
        }
        .validation-error { color: red; font-size: 0.875rem; margin-top: 0.25rem; }

    </style>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-100 text-gray-800">

    <header class="bg-gradient-to-r from-blue-600 to-indigo-700 text-white shadow-md">
        <div class="container mx-auto px-6 py-4 flex justify-between items-center">
            <h1 class="text-2xl font-bold">
                <i class="fas fa-vote-yea mr-2"></i>Online Voting System
            </h1>
            <nav>
                 <span class="px-4 py-2 text-indigo-200">Welcome, <?php echo htmlspecialchars($username); ?>!</span>
                 <a href="dashboard.php" class="px-4 py-2 rounded hover:bg-indigo-800 transition duration-300">Dashboard</a>
                 <a href="includes/logout.php" class="px-4 py-2 rounded bg-red-500 hover:bg-red-600 transition duration-300">
                     <i class="fas fa-sign-out-alt mr-1"></i>Logout
                 </a>
            </nav>
        </div>
    </header>

    <main class="container mx-auto px-6 py-12">
        <div class="bg-white p-8 rounded-lg shadow-lg border border-gray-200 max-w-3xl mx-auto">
            <h2 class="text-3xl font-bold text-center text-indigo-700 mb-2"><?php echo htmlspecialchars($election['Title']); ?></h2>
            <p class="text-center text-gray-600 mb-6"><?php echo htmlspecialchars($election['Description'] ?? ''); ?></p>
            <p class="text-center text-sm text-red-600 mb-6">Voting ends: <?php echo date('M j, Y g:i A', strtotime($election['EndDate'])); ?></p>

            <hr class="my-6">

            <h3 class="text-xl font-semibold text-gray-700 mb-4">Candidates:</h3>
            <p class="text-sm text-gray-500 mb-4">
                <?php
                // Instructions based on voting method
                switch ($voting_method) {
                    case 'FPTP':
                        echo "Please select ONE candidate.";
                        break;
                    case 'Approval':
                        echo "Please select ALL candidates you approve of.";
                        break;
                    case 'RCV':
                    case 'STV':
                        echo "Please rank the candidates in order of preference (1 for highest). Use unique numbers.";
                        break;
                    case 'Score':
                         echo "Please assign a score to each candidate (e.g., 0-10, higher is better).";
                         break;
                    case 'Condorcet':
                         echo "Please rank the candidates in order of preference (1 for highest)."; // Often uses ranking
                         break;
                    default:
                        echo "Please make your selection(s).";
                }
                ?>
            </p>

            <?php
            // Display potential errors from submission handler
            if (isset($_SESSION['vote_message'])) {
                $v_message = $_SESSION['vote_message'];
                $v_status = $_SESSION['vote_status'] ?? 'error';
                $v_bgColor = ($v_status === 'success') ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700';
                echo "<div class='border px-4 py-3 rounded relative mb-4 {$v_bgColor}' role='alert'>";
                echo "<span class='block sm:inline'>" . htmlspecialchars($v_message) . "</span>";
                echo "</div>";
                unset($_SESSION['vote_message']);
                unset($_SESSION['vote_status']);
            }
            ?>

            <form id="voteForm" action="includes/handle_vote.php" method="POST" onsubmit="return validateVoteForm()">
                <input type="hidden" name="election_id" value="<?php echo $election_id; ?>">
                <input type="hidden" name="voting_method" value="<?php echo htmlspecialchars($voting_method); ?>">

                <div id="candidatesList" class="space-y-4 mb-6">
                    <?php foreach ($candidates as $candidate): ?>
                        <div class="candidate-item">
                            <?php
                            // --- Generate Input based on Voting Method ---
                            switch ($voting_method) {
                                case 'FPTP': // First-Past-The-Post: Radio buttons
                                    echo '<input type="radio" id="candidate_' . $candidate['CandidateID'] . '" name="vote_data" value="' . $candidate['CandidateID'] . '" required>';
                                    break;

                                case 'Approval': // Approval Voting: Checkboxes
                                    // Name uses array syntax `vote_data[]`
                                    echo '<input type="checkbox" id="candidate_' . $candidate['CandidateID'] . '" name="vote_data[]" value="' . $candidate['CandidateID'] . '">';
                                    break;

                                case 'RCV': // Ranked Choice Voting: Number inputs (simple version)
                                case 'STV':
                                case 'Condorcet': // Often uses ranking
                                    // Name uses array syntax `vote_data[candidate_id]`
                                    echo '<input type="number" min="1" max="' . count($candidates) . '" id="candidate_' . $candidate['CandidateID'] . '" name="vote_data[' . $candidate['CandidateID'] . ']" class="rank-input">';
                                    break;

                                case 'Score': // Score Voting: Number inputs (e.g., 0-10)
                                     // Name uses array syntax `vote_data[candidate_id]`
                                     echo '<input type="number" min="0" max="10" id="candidate_' . $candidate['CandidateID'] . '" name="vote_data[' . $candidate['CandidateID'] . ']" class="rank-input" placeholder="0-10">';
                                     break;

                                default: // Fallback or unsupported method
                                    echo '<span class="text-red-500 mr-4">Voting method not supported for UI</span>';
                            }
                            ?>
                            <label for="candidate_<?php echo $candidate['CandidateID']; ?>" class="candidate-details cursor-pointer">
                                <div class="candidate-name"><?php echo htmlspecialchars($candidate['Name']); ?></div>
                                <?php if (!empty($candidate['Description'])): ?>
                                    <div class="candidate-desc"><?php echo htmlspecialchars($candidate['Description']); ?></div>
                                <?php endif; ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>

                 <div id="voteError" class="validation-error mb-4"></div>

                <div class="text-center">
                    <button type="submit"
                            class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-8 rounded-lg focus:outline-none focus:shadow-outline transition duration-300 shadow-md text-lg">
                        <i class="fas fa-check-to-slot mr-2"></i>Submit Vote
                    </button>
                </div>
            </form>

        </div>
    </main>

    <footer class="bg-gray-800 text-gray-300 py-6 mt-12">
        <div class="container mx-auto px-6 text-center">
             <p>&copy; <?php echo date("Y"); ?> Online Voting System. Developed by Khagendra Malla & Sujal Bajracharya.</p>
             <div class="mt-2">
                 <a href="terms.php" class="text-gray-400 hover:text-white px-2">Terms & Conditions</a> |
                 <a href="privacy.php" class="text-gray-400 hover:text-white px-2">Privacy Policy</a> |
                 <a href="faq.php" class="text-gray-400 hover:text-white px-2">FAQ</a>
             </div>
        </div>
    </footer>

    <script>
        function validateVoteForm() {
            const form = document.getElementById('voteForm');
            const votingMethod = '<?php echo $voting_method; ?>'; // Get method from PHP
            const errorDiv = document.getElementById('voteError');
            errorDiv.textContent = ''; // Clear previous errors
            let isValid = true;

            switch (votingMethod) {
                case 'FPTP':
                    // Check if any radio button is selected
                    const fptpSelected = form.querySelector('input[name="vote_data"]:checked');
                    if (!fptpSelected) {
                        errorDiv.textContent = 'Please select one candidate to vote for.';
                        isValid = false;
                    }
                    break;

                case 'Approval':
                    // Check if at least one checkbox is selected (optional validation, depends on rules)
                    const approvalSelected = form.querySelectorAll('input[name="vote_data[]"]:checked');
                    // Example: Require at least one approval
                    /*
                    if (approvalSelected.length === 0) {
                        errorDiv.textContent = 'Please approve at least one candidate.';
                        isValid = false;
                    }
                    */
                    // Often, approving zero candidates is allowed, so validation might not be needed here.
                    break;

                case 'RCV':
                case 'STV':
                case 'Condorcet':
                    // Validate ranking inputs (ensure numbers, check for duplicates, check range)
                    const rankInputs = form.querySelectorAll('input[name^="vote_data["]');
                    const ranks = new Set();
                    let hasEmpty = false;
                    let hasDuplicate = false;
                    let outOfRange = false;
                    const maxRank = rankInputs.length;

                    rankInputs.forEach(input => {
                        const rankValue = input.value.trim();
                        if (rankValue === '') {
                            // Allow empty ranks (partial ranking) - depends on rules
                            // If empty ranks are NOT allowed, set hasEmpty = true;
                        } else {
                            const rankNum = parseInt(rankValue, 10);
                            if (isNaN(rankNum) || rankNum < 1 || rankNum > maxRank) {
                                outOfRange = true;
                            } else if (ranks.has(rankNum)) {
                                hasDuplicate = true;
                            } else {
                                ranks.add(rankNum);
                            }
                        }
                    });

                    // Example: Require ranking at least one candidate
                    if (ranks.size === 0 && !hasEmpty) { // Adjust if empty ranks are disallowed
                         // errorDiv.textContent = 'Please rank at least one candidate.';
                         // isValid = false;
                         // Often allowed, so commented out
                    }
                    if (outOfRange) {
                        errorDiv.textContent = `Ranks must be numbers between 1 and ${maxRank}.`;
                        isValid = false;
                    }
                    if (hasDuplicate) {
                        errorDiv.textContent = 'Please use unique ranks for each candidate you rank.';
                        isValid = false;
                    }
                    // Add check for hasEmpty if empty ranks are disallowed
                    break;

                case 'Score':
                     // Validate score inputs (ensure numbers, check range)
                     const scoreInputs = form.querySelectorAll('input[name^="vote_data["]');
                     let scoreOutOfRange = false;
                     scoreInputs.forEach(input => {
                         const scoreValue = input.value.trim();
                         if (scoreValue !== '') { // Only validate if a score is entered
                            const scoreNum = parseInt(scoreValue, 10);
                            if (isNaN(scoreNum) || scoreNum < 0 || scoreNum > 10) { // Assuming 0-10 range
                                scoreOutOfRange = true;
                            }
                         }
                     });
                     if (scoreOutOfRange) {
                         errorDiv.textContent = 'Scores must be numbers between 0 and 10.';
                         isValid = false;
                     }
                     break;

                default:
                    // No specific validation for unknown types on client-side
                    break;
            }

            // Confirmation dialog before submitting
            if (isValid) {
                return confirm('Are you sure you want to submit your vote? This action cannot be undone.');
            }

            return isValid; // Prevent submission if validation failed
        }
    </script>

</body>
</html>
