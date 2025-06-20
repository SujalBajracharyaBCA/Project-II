<?php

session_start();
require_once 'includes/db_connect.php'; // Ensure this path is correct

// --- Authentication & Authorization ---
$is_admin = false;
$is_voter = false;
$username = 'Guest'; // Default

if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
    $username = $_SESSION['username'];
    if ($_SESSION['user_role'] === 'Admin') {
        $is_admin = true;
    } elseif ($_SESSION['user_role'] === 'Voter') {
        $is_voter = true;
    }
}

// --- Get Election ID ---
if (!isset($_GET['election_id']) || !filter_var($_GET['election_id'], FILTER_VALIDATE_INT)) {
    // Redirect to a sensible default page if ID is missing/invalid
    header("Location: index.php"); // Or dashboard.php if always requiring login
    exit();
}
$election_id = (int)$_GET['election_id'];

// --- Fetch Election Details ---
$election = null;
$candidates = [];
$votes = [];
$results = [];
$error_message = null;
$total_votes_cast = 0; // Count of vote records/ballots submitted
$total_eligible = 0;   // Count of users eligible for this election
$voted_count = 0;      // Count of unique users who actually voted

$sql_election = "SELECT ElectionID, Title, Description, Status, VotingMethod, EndDate, StartDate FROM Elections WHERE ElectionID = ?";
$stmt_election = $conn->prepare($sql_election);

if ($stmt_election) {
    $stmt_election->bind_param("i", $election_id);
    $stmt_election->execute();
    $result_election = $stmt_election->get_result();
    if ($result_election->num_rows === 1) {
        $election = $result_election->fetch_assoc();

        // --- Authorization Check (Beyond basic login) ---
        // Only Admins can view results before the election is 'Closed'
        if ($election['Status'] !== 'Closed' && !$is_admin) {
            $_SESSION['dashboard_message'] = "Results for this election are not yet available.";
            $_SESSION['dashboard_status'] = "info";
            // Redirect voter to their dashboard
            header("Location: dashboard.php");
            exit();
        }
        // Future enhancement: Add check for election-specific visibility settings if implemented

    } else {
        $error_message = "Election not found.";
    }
    $stmt_election->close();
} else {
    $error_message = "Database error fetching election details: " . $conn->error;
    error_log($error_message);
}

// --- Fetch Candidates, Votes, and Turnout if Election Found and Accessible ---
if ($election && !$error_message) {
    // Fetch Candidates
    $sql_candidates = "SELECT CandidateID, Name FROM Candidates WHERE ElectionID = ? ORDER BY DisplayOrder, Name";
    $stmt_candidates = $conn->prepare($sql_candidates);
    if ($stmt_candidates) {
        $stmt_candidates->bind_param("i", $election_id);
        $stmt_candidates->execute();
        $result_candidates = $stmt_candidates->get_result();
        while ($row = $result_candidates->fetch_assoc()) {
            $candidates[$row['CandidateID']] = $row; // Use ID as key
        }
        $stmt_candidates->close();
    } else {
        $error_message = ($error_message ? $error_message . "<br>" : '') . "Error fetching candidates: " . $conn->error;
        error_log("Error fetching candidates: " . $conn->error);
    }

    // Fetch Votes
    $sql_votes = "SELECT VoteID, VoteData FROM Votes WHERE ElectionID = ?";
    $stmt_votes = $conn->prepare($sql_votes);
    if ($stmt_votes) {
        $stmt_votes->bind_param("i", $election_id);
        $stmt_votes->execute();
        $result_votes = $stmt_votes->get_result();
        while ($row = $result_votes->fetch_assoc()) {
            $votes[] = $row;
        }
        $total_votes_cast = count($votes); // Total number of vote records submitted
        $stmt_votes->close();
    } else {
        $error_message = ($error_message ? $error_message . "<br>" : '') . "Error fetching votes: " . $conn->error;
        error_log("Error fetching votes: " . $conn->error);
    }

     // Fetch Turnout Info (Eligible vs Voted)
     $sql_turnout = "SELECT
                       COUNT(*) AS TotalEligible,
                       SUM(CASE WHEN HasVoted = TRUE THEN 1 ELSE 0 END) AS VotedCount
                   FROM EligibleVoters
                   WHERE ElectionID = ?";
    $stmt_turnout = $conn->prepare($sql_turnout);
     if ($stmt_turnout) {
         $stmt_turnout->bind_param("i", $election_id);
         $stmt_turnout->execute();
         $result_turnout = $stmt_turnout->get_result();
         $turnout_data = $result_turnout->fetch_assoc();
         $total_eligible = $turnout_data['TotalEligible'] ?? 0;
         $voted_count = $turnout_data['VotedCount'] ?? 0; // Number of unique voters who cast a ballot
         $stmt_turnout->close();
     } else {
         $error_message = ($error_message ? $error_message . "<br>" : '') . "Error fetching turnout info: " . $conn->error;
         error_log("Error fetching turnout info: " . $conn->error);
     }


    // --- Calculate Results Based on Voting Method ---
    if (empty($error_message) && !empty($candidates)) {
        $results = [];
        // Initialize results array for each candidate
        foreach ($candidates as $id => $candidate) {
            // Store more details if needed later (e.g., for complex methods)
            $results[$id] = ['id' => $id, 'name' => $candidate['Name'], 'votes' => 0, 'percentage' => 0];
        }

        switch ($election['VotingMethod']) {
            case 'FPTP':
                foreach ($votes as $vote) {
                    $candidate_id = (int)$vote['VoteData'];
                    if (isset($results[$candidate_id])) {
                        $results[$candidate_id]['votes']++;
                    }
                }
                // Calculate percentages based on total valid votes cast for FPTP
                if ($total_votes_cast > 0) {
                    foreach ($results as $id => $data) {
                        $results[$id]['percentage'] = round(($data['votes'] / $total_votes_cast) * 100, 2);
                    }
                }
                // Sort by votes descending for FPTP
                uasort($results, function ($a, $b) {
                    return $b['votes'] <=> $a['votes'];
                });
                break;

            case 'Approval':
                foreach ($votes as $vote) {
                    $approved_ids = json_decode($vote['VoteData'], true);
                    if (is_array($approved_ids)) {
                        foreach ($approved_ids as $candidate_id) {
                            if (isset($results[$candidate_id])) {
                                $results[$candidate_id]['votes']++; // 'votes' here means approvals
                            }
                        }
                    }
                }
                 // Calculate percentages for Approval based on total voters who cast a ballot
                 if ($voted_count > 0) {
                     foreach ($results as $id => $data) {
                         // Percentage of voters who approved this candidate
                         $results[$id]['percentage'] = round(($data['votes'] / $voted_count) * 100, 2);
                     }
                 }
                 // Sort by approvals descending
                 uasort($results, function ($a, $b) {
                     return $b['votes'] <=> $a['votes'];
                 });
                break;

            // --- Placeholder for other methods ---
            // Add calculation logic for RCV, STV, Score, Condorcet here
            // These will be more complex, involving parsing JSON, potentially multiple rounds (RCV/STV), summing scores, or pairwise comparisons.
            case 'RCV':
            case 'STV':
            case 'Score':
            case 'Condorcet':
                $error_message = ($error_message ? $error_message."<br>" : "") . "Result calculation for '" . htmlspecialchars($election['VotingMethod']) . "' voting is not yet implemented in this view.";
                // Clear results array as calculation is not done
                $results = [];
                break;

            default:
                $error_message = ($error_message ? $error_message."<br>" : "") . "Unknown or unsupported voting method: " . htmlspecialchars($election['VotingMethod']);
                $results = [];
        }
    } elseif (empty($candidates) && !$error_message) {
         $error_message = ($error_message ? $error_message."<br>" : "") . "No candidates were found for this election, cannot calculate results.";
         $results = [];
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
    <title>Election Results - <?php echo htmlspecialchars($election['Title'] ?? 'Not Found'); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script> <style>
        body { font-family: 'Inter', sans-serif; }
        /* Add styles from previous pages if needed (header, footer, etc.) */
        th, td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
        th { background-color: #f8fafc; font-weight: 600; color: #4b5563; }
        tbody tr:hover { background-color: #f9fafb; }
        tbody tr.winner-row { background-color: #ecfdf5; font-weight: 600; } /* Light green for winner */
        .winner-indicator { color: #10b981; /* green-500 */ margin-left: 0.5rem; }
        /* Progress bar styling */
        .percentage-bar-bg { background-color: #e5e7eb; border-radius: 0.375rem; overflow: hidden; height: 1.25rem; /* h-5 */ width: 150px; position: relative; }
        .percentage-bar-fill { background-color: #60a5fa; /* blue-400 */ height: 100%; transition: width 0.5s ease-in-out; }
        .percentage-text { position: absolute; left: 0.5rem; right: 0.5rem; top: 0; bottom: 0; color: #1f2937; font-size: 0.75rem; line-height: 1.25rem; text-align: center; font-weight: 500; }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-100 text-gray-800">

    <header class="bg-gradient-to-r from-blue-600 to-indigo-700 text-white shadow-md">
        <div class="container mx-auto px-6 py-4 flex justify-between items-center">
            <h1 class="text-2xl font-bold">
                <a href="index.php"><i class="fas fa-vote-yea mr-2"></i>Online Voting System</a>
            </h1>
            <nav>
                <?php if ($is_voter || $is_admin): ?>
                    <span class="px-4 py-2 text-indigo-200">Welcome, <?php echo htmlspecialchars($username); ?>!</span>
                    <a href="<?php echo $is_admin ? 'admin/dashboard.php' : 'dashboard.php'; ?>" class="px-4 py-2 rounded hover:bg-indigo-800 transition duration-300">Dashboard</a>
                    <a href="includes/logout.php" class="px-4 py-2 rounded bg-red-500 hover:bg-red-600 transition duration-300">
                        <i class="fas fa-sign-out-alt mr-1"></i>Logout
                    </a>
                <?php else: ?>
                    <a href="index.php" class="px-4 py-2 rounded hover:bg-indigo-800 transition duration-300">Home</a>
                    <a href="login.php" class="px-4 py-2 rounded hover:bg-indigo-800 transition duration-300">Login</a>
                    <a href="register.php" class="px-4 py-2 rounded hover:bg-indigo-800 transition duration-300">Register</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main class="container mx-auto px-6 py-12">

        <?php if ($error_message): ?>
            <div class="bg-white p-6 rounded-lg shadow border border-red-300 max-w-3xl mx-auto text-center">
                <h2 class="text-2xl font-bold text-red-700 mb-4">Error</h2>
                <p class="text-red-600"><?php echo $error_message; // Display error message ?></p>
                <a href="<?php echo $is_admin ? 'admin/election_management.php' : 'dashboard.php'; ?>" class="mt-4 inline-block bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition duration-300">
                    Go Back
                </a>
            </div>
        <?php elseif ($election): ?>
            <div class="bg-white p-6 rounded-lg shadow border border-gray-200 mb-6 max-w-4xl mx-auto">
                <h2 class="text-3xl font-bold text-center text-indigo-700 mb-3"><?php echo htmlspecialchars($election['Title']); ?> - Results</h2>
                <p class="text-center text-sm text-gray-500 mb-1">Method: <span class="font-medium"><?php echo htmlspecialchars($election['VotingMethod']); ?></span></p>
                <p class="text-center text-sm text-gray-500 mb-1">Status: <span class="font-medium <?php echo ($election['Status'] == 'Closed') ? 'text-red-600' : 'text-green-600'; ?>"><?php echo htmlspecialchars($election['Status']); ?></span></p>
                <p class="text-center text-sm text-gray-500 mb-4">Ended: <span class="font-medium"><?php echo date('M j, Y g:i A', strtotime($election['EndDate'])); ?></span></p>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center text-sm text-gray-700 pt-4 border-t">
                     <div>Eligible Voters<br><strong class="text-xl"><?php echo number_format($total_eligible); ?></strong></div>
                     <div>Voters Who Voted<br><strong class="text-xl"><?php echo number_format($voted_count); ?></strong></div>
                     <div>Turnout<br><strong class="text-xl"><?php echo $turnout_percentage; ?>%</strong></div>
                     <div>Total Votes Recorded<br><strong class="text-xl"><?php echo number_format($total_votes_cast); ?></strong></div>
                </div>
            </div>

            <div class="bg-white p-6 rounded-lg shadow border border-gray-200 mb-6 max-w-4xl mx-auto">
                <h3 class="text-xl font-semibold text-gray-800 mb-4">Results Summary</h3>
                 <?php if (!empty($results)): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Candidate</th>
                                    <th><?php echo ($election['VotingMethod'] == 'Approval') ? 'Approvals' : 'Votes'; ?></th>
                                    <th>Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $rank = 1;
                                $prev_votes = null;
                                $display_rank = 1;
                                $is_first = true; // Flag for winner row styling
                                foreach ($results as $id => $data):
                                    // Handle ties in rank display for FPTP/Approval
                                    if ($prev_votes !== null && $data['votes'] < $prev_votes) {
                                        $display_rank = $rank;
                                    }
                                    $row_class = ($display_rank == 1 && ($election['VotingMethod'] == 'FPTP' || $election['VotingMethod'] == 'Approval') && $is_first) ? 'winner-row' : '';
                                ?>
                                <tr class="<?php echo $row_class; ?>">
                                    <td class="text-center w-16"><?php echo $display_rank; ?></td>
                                    <td class="font-medium text-gray-900">
                                        <?php echo htmlspecialchars($data['name']); ?>
                                        <?php if ($display_rank == 1 && ($election['VotingMethod'] == 'FPTP' || $election['VotingMethod'] == 'Approval') && $is_first) echo '<i class="fas fa-crown winner-indicator" title="Winner/Highest Approval"></i>'; ?>
                                    </td>
                                    <td class="w-32"><?php echo number_format($data['votes']); ?></td>
                                    <td class="w-48">
                                        <div class="percentage-bar-bg" title="<?php echo $data['percentage']; ?>%">
                                            <div class="percentage-bar-fill" style="width: <?php echo $data['percentage']; ?>%;"></div>
                                            <div class="percentage-text"><?php echo $data['percentage']; ?>%</div>
                                        </div>
                                    </td>
                                </tr>
                                <?php
                                    $prev_votes = $data['votes'];
                                    $rank++;
                                    if ($display_rank == 1) $is_first = false; // Only style the first winner row in case of ties for simplicity
                                endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                 <?php elseif ($election['Status'] == 'Closed'): ?>
                     <p class="text-center text-gray-500 py-4">No votes were cast in this election, or results calculation is not available for this voting method.</p>
                 <?php else: ?>
                      <p class="text-center text-gray-500 py-4">Results are being processed or the election is not yet closed.</p>
                 <?php endif; ?>
             </div>

             <?php if (!empty($results) && ($election['VotingMethod'] == 'FPTP' || $election['VotingMethod'] == 'Approval')): ?>
             <div class="bg-white p-6 rounded-lg shadow border border-gray-200 max-w-4xl mx-auto">
                  <h3 class="text-xl font-semibold text-gray-800 mb-4">Results Chart</h3>
                  <div class="relative h-64 md:h-96">
                    <canvas id="resultsChart"></canvas>
                  </div>
             </div>
             <?php endif; ?>

        <?php else: ?>
             <div class="bg-white p-6 rounded-lg shadow border border-red-300 max-w-3xl mx-auto text-center">
                 <h2 class="text-2xl font-bold text-red-700 mb-4">Error</h2>
                 <p class="text-red-600">Could not load election data.</p>
                  <a href="<?php echo $is_admin ? 'admin/election_management.php' : 'dashboard.php'; ?>" class="mt-4 inline-block bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition duration-300">
                    Go Back
                </a>
             </div>
        <?php endif; ?>

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
         // Chart.js Implementation
         <?php if (!empty($results) && ($election['VotingMethod'] == 'FPTP' || $election['VotingMethod'] == 'Approval')): ?>
         const ctx = document.getElementById('resultsChart');
         if (ctx) {
             const chartData = {
                 // Use array_values to reset keys for JSON array
                 labels: <?php echo json_encode(array_values(array_column($results, 'name'))); ?>,
                 datasets: [{
                     label: '<?php echo ($election['VotingMethod'] == 'FPTP') ? 'Votes' : 'Approvals'; ?>',
                     data: <?php echo json_encode(array_values(array_column($results, 'votes'))); ?>,
                     backgroundColor: [ // Cycle through colors
                         'rgba(59, 130, 246, 0.7)', 'rgba(16, 185, 129, 0.7)',
                         'rgba(239, 68, 68, 0.7)',  'rgba(245, 158, 11, 0.7)',
                         'rgba(139, 92, 246, 0.7)', 'rgba(236, 72, 153, 0.7)',
                         'rgba(107, 114, 128, 0.7)' // Add more colors if needed
                     ],
                     borderColor: [
                         'rgba(59, 130, 246, 1)', 'rgba(16, 185, 129, 1)',
                         'rgba(239, 68, 68, 1)',  'rgba(245, 158, 11, 1)',
                         'rgba(139, 92, 246, 1)', 'rgba(236, 72, 153, 1)',
                         'rgba(107, 114, 128, 1)'
                     ],
                     borderWidth: 1
                 }]
             };

             new Chart(ctx, {
                 type: 'bar', // or 'pie', 'doughnut'
                 data: chartData,
                 options: {
                     indexAxis: 'y', // Horizontal bar chart might be better for many candidates
                     maintainAspectRatio: false, // Allow chart to fill container height
                     scales: {
                         x: { // Note: x-axis for horizontal bar
                             beginAtZero: true,
                             ticks: { precision: 0 } // Ensure whole numbers for vote counts
                         }
                     },
                     plugins: {
                         legend: { display: false }, // Hide legend if only one dataset
                         tooltip: {
                             callbacks: {
                                 label: function(context) {
                                     let label = context.dataset.label || '';
                                     if (label) { label += ': '; }
                                     if (context.parsed.x !== null) {
                                         label += context.parsed.x;
                                         // Find percentage from original data
                                         const candidateName = context.label;
                                         const resultData = <?php echo json_encode($results); ?>;
                                         for(const id in resultData) {
                                             if(resultData[id].name === candidateName) {
                                                 label += ` (${resultData[id].percentage}%)`;
                                                 break;
                                             }
                                         }
                                     }
                                     return label;
                                 }
                             }
                         }
                     }
                 }
             });
         }
         <?php endif; ?>
    </script>

</body>
</html>