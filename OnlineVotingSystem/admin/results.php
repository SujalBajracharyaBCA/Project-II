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
$results = [];
$error_message = null;
$total_votes_cast = 0;
$total_eligible = 0;
$voted_count = 0;

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
        $error_message = "Error fetching candidates: " . $conn->error;
        error_log($error_message);
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
        $total_votes_cast = count($votes); // Total number of vote records
        $stmt_votes->close();
    } else {
        $error_message = "Error fetching votes: " . $conn->error;
        error_log($error_message);
    }

     // Fetch Turnout Info
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
            $results[$id] = ['name' => $candidate['Name'], 'votes' => 0, 'percentage' => 0];
        }

        switch ($election['VotingMethod']) {
            case 'FPTP':
                foreach ($votes as $vote) {
                    $candidate_id = (int)$vote['VoteData'];
                    if (isset($results[$candidate_id])) {
                        $results[$candidate_id]['votes']++;
                    }
                }
                // Calculate percentages for FPTP
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
                $total_approvals = 0; // Can be more than total votes cast
                foreach ($votes as $vote) {
                    $approved_ids = json_decode($vote['VoteData'], true);
                    if (is_array($approved_ids)) {
                        foreach ($approved_ids as $candidate_id) {
                            if (isset($results[$candidate_id])) {
                                $results[$candidate_id]['votes']++;
                                $total_approvals++;
                            }
                        }
                    }
                }
                 // Calculate percentages for Approval (based on total voters who cast a ballot)
                 if ($voted_count > 0) {
                     foreach ($results as $id => $data) {
                         $results[$id]['percentage'] = round(($data['votes'] / $voted_count) * 100, 2);
                     }
                 }
                 // Sort by approvals descending
                 uasort($results, function ($a, $b) {
                     return $b['votes'] <=> $a['votes'];
                 });
                break;

            // --- Placeholder for other methods ---
            case 'RCV':
            case 'STV':
            case 'Score':
            case 'Condorcet':
                $error_message = "Result calculation for '" . htmlspecialchars($election['VotingMethod']) . "' voting is not yet implemented in this view.";
                // In a real system, call specific calculation functions here.
                // These functions would parse the JSON VoteData and apply the respective counting rules.
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
                                        <th>Votes / Approvals</th>
                                        <th>Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $rank = 1;
                                    $prev_votes = null;
                                    $display_rank = 1;
                                    foreach ($results as $id => $data):
                                        // Handle ties in rank display for FPTP/Approval
                                        if ($prev_votes !== null && $data['votes'] < $prev_votes) {
                                            $display_rank = $rank;
                                        }
                                    ?>
                                    <tr>
                                        <td class="text-center"><?php echo $display_rank; ?></td>
                                        <td class="font-medium text-gray-900">
                                            <?php echo htmlspecialchars($data['name']); ?>
                                            <?php if ($display_rank == 1 && ($election['VotingMethod'] == 'FPTP' || $election['VotingMethod'] == 'Approval')) echo '<i class="fas fa-crown winner-indicator" title="Winner/Highest Approval"></i>'; ?>
                                        </td>
                                        <td><?php echo number_format($data['votes']); ?></td>
                                        <td>
                                            <div class="percentage-bar-bg">
                                                <div class="percentage-bar-fill" style="width: <?php echo $data['percentage']; ?>%;"></div>
                                                <div class="percentage-text"><?php echo $data['percentage']; ?>%</div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php
                                        $prev_votes = $data['votes'];
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

                 <?php if (!empty($results) && ($election['VotingMethod'] == 'FPTP' || $election['VotingMethod'] == 'Approval')): ?>
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
         <?php if (!empty($results) && ($election['VotingMethod'] == 'FPTP' || $election['VotingMethod'] == 'Approval')): ?>
         const ctx = document.getElementById('resultsChart');
         if (ctx) {
             const chartData = {
                 labels: <?php echo json_encode(array_column($results, 'name')); ?>,
                 datasets: [{
                     label: '<?php echo ($election['VotingMethod'] == 'FPTP') ? 'Votes' : 'Approvals'; ?>',
                     data: <?php echo json_encode(array_column($results, 'votes')); ?>,
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
                     scales: {
                         y: {
                             beginAtZero: true,
                              ticks: {
                                 precision: 0 // Ensure whole numbers for vote counts
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
