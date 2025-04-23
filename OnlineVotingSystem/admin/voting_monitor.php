<?php
// admin/voting_monitor.php
session_start();
require_once 'includes/db_connect.php'; // Ensure this path is correct

// --- Authentication & Authorization ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
    $_SESSION['login_message'] = "Access denied. Please log in as an administrator.";
    header("Location: login.php");
    exit();
}
$admin_username = $_SESSION['username']; // For display

// --- Fetch Election Data with Vote Counts ---
$elections_data = [];
$error_message = null;
$now = date('Y-m-d H:i:s');

// SQL to fetch elections (Active or recently Closed) and calculate counts
// We need: Election details, Total Eligible Voters, Voters Who Have Voted, Total Votes Cast
$sql = "SELECT
            e.ElectionID,
            e.Title,
            e.StartDate,
            e.EndDate,
            e.Status,
            e.VotingMethod,
            (SELECT COUNT(*) FROM EligibleVoters ev WHERE ev.ElectionID = e.ElectionID) AS TotalEligible,
            (SELECT COUNT(*) FROM EligibleVoters ev WHERE ev.ElectionID = e.ElectionID AND ev.HasVoted = TRUE) AS VotedCount,
            (SELECT COUNT(*) FROM Votes v WHERE v.ElectionID = e.ElectionID) AS TotalVotesCast
        FROM Elections e
        WHERE e.Status IN ('Active', 'Closed') -- Show active and closed elections
        ORDER BY FIELD(e.Status, 'Active', 'Closed'), e.EndDate DESC"; // Show Active first, then Closed by end date

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Calculate Turnout Percentage
        $row['TurnoutPercentage'] = ($row['TotalEligible'] > 0)
                                     ? round(($row['VotedCount'] / $row['TotalEligible']) * 100, 2)
                                     : 0;
        $elections_data[] = $row;
    }
} else {
    $error_message = "Error fetching election monitoring data: " . $conn->error;
    error_log($error_message); // Log the detailed error
}

// --- Handle Session Messages (e.g., after closing an election) ---
$success_message = null;
if (isset($_SESSION['message'])) {
    if ($_SESSION['message_status'] === 'success') {
        $success_message = $_SESSION['message'];
    } else {
        $error_message = $error_message ? $error_message . "<br>" . $_SESSION['message'] : $_SESSION['message']; // Append or set error
    }
    unset($_SESSION['message']);
    unset($_SESSION['message_status']);
}

// Generate CSRF token if not already set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];


$conn->close(); // Close the connection

// Function to determine display status and color (similar to election_management.php)
function get_status_info_monitor($status, $start_date, $end_date) {
    global $now;
    $display_status = $status;
    $color_class = 'bg-gray-500'; // Default

    if ($status === 'Active' && $start_date <= $now && $end_date >= $now) {
        $display_status = 'Active';
        $color_class = 'bg-green-500';
    } elseif ($status === 'Active' && $end_date < $now) {
        // DB Status is Active, but time has passed -> Needs Closing
        $display_status = 'Ended (Needs Closing)';
        $color_class = 'bg-yellow-600'; // Use yellow/orange to indicate action needed
    } elseif ($status === 'Closed') {
        $display_status = 'Closed';
        $color_class = 'bg-red-500';
    } // Add other statuses if needed (Pending, Archived)

    return ['text' => $display_status, 'color' => $color_class];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voting Monitor - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style>
        body { font-family: 'Inter', sans-serif; }
        /* Sidebar and responsive styles (copy from dashboard.php or other admin pages) */
        .sidebar { width: 250px; transition: transform 0.3s ease-in-out; }
        .content { margin-left: 250px; transition: margin-left 0.3s ease-in-out; }
        .sidebar-collapsed { transform: translateX(-100%); }
        .content-expanded { margin-left: 0; }
         @media (max-width: 768px) { /* Mobile styles */
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); z-index: 40; }
            .content { margin-left: 0; }
            .sidebar-toggle { display: block; }
            .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); z-index: 30; }
            .sidebar.open + .overlay { display: block; }
        }
        @media (min-width: 769px) { /* Desktop styles */
             .sidebar-toggle-close, .sidebar-toggle-open { display: none; }
        }
        /* Table styles */
        th, td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
        th { background-color: #f8fafc; font-weight: 600; color: #4b5563; }
        tbody tr:hover { background-color: #f9fafb; }
        .action-btn { padding: 0.3rem 0.6rem; font-size: 0.8rem; margin-right: 0.25rem; border-radius: 0.375rem; transition: background-color 0.2s; display: inline-flex; align-items: center; text-decoration: none; color: white; }
        .status-badge { padding: 0.2rem 0.5rem; font-size: 0.75rem; font-weight: 600; border-radius: 9999px; display: inline-block; color: white; }
        /* Progress bar styles */
        .progress-bar-bg { background-color: #e5e7eb; /* gray-200 */ border-radius: 0.375rem; /* rounded-md */ overflow: hidden; height: 1rem; /* h-4 */ width: 100px; }
        .progress-bar-fill { background-color: #3b82f6; /* blue-500 */ height: 100%; transition: width 0.3s ease-in-out; text-align: center; color: white; font-size: 0.7rem; line-height: 1rem; }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="overlay md:hidden"></div>

    <div class="flex min-h-screen">
        <aside class="sidebar bg-gradient-to-b from-gray-800 to-gray-900 text-white p-6 fixed h-full shadow-lg md:translate-x-0 z-40">
            <div class="flex justify-between items-center mb-8">
                <h2 class="text-2xl font-bold"><i class="fas fa-cogs mr-2"></i>Admin Panel</h2>
                <button class="sidebar-toggle-close md:hidden text-white focus:outline-none"><i class="fas fa-times text-xl"></i></button>
            </div>
            <nav>
                <a href="dashboard.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700"><i class="fas fa-tachometer-alt mr-2 w-5 text-center"></i>Dashboard</a>
                <a href="election_management.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700"><i class="fas fa-poll-h mr-2 w-5 text-center"></i>Elections</a>
                <a href="voting_monitor.php" class="block py-2.5 px-4 rounded transition duration-200 bg-indigo-600 font-semibold"><i class="fas fa-tv mr-2 w-5 text-center"></i>Voting Monitor</a>
                <a href="results.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700"><i class="fas fa-chart-bar mr-2 w-5 text-center"></i>Results</a>
                <a href="user_management.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700"><i class="fas fa-user-cog mr-2 w-5 text-center"></i>Admin Users</a>
                <a href="audit_logs.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700"><i class="fas fa-history mr-2 w-5 text-center"></i>Audit Logs</a>
                <a href="settings.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700"><i class="fas fa-sliders-h mr-2 w-5 text-center"></i>Settings</a>
                <a href="includes/logout.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-red-600 mt-8 text-red-300 hover:text-white"><i class="fas fa-sign-out-alt mr-2 w-5 text-center"></i>Logout</a>
            </nav>
        </aside>

        <div class="content flex-1 p-6 md:p-10">
            <header class="bg-white shadow rounded-lg p-4 mb-6 flex justify-between items-center">
                <button class="sidebar-toggle-open md:hidden text-gray-600 focus:outline-none"><i class="fas fa-bars text-xl"></i></button>
                <h1 class="text-xl md:text-2xl font-semibold text-gray-700">Voting Monitor</h1>
                <div>
                    <span class="text-gray-600 text-sm md:text-base">Welcome, <?php echo htmlspecialchars($admin_username); ?>!</span>
                </div>
            </header>

            <?php if ($success_message): ?>
                <div class='border px-4 py-3 rounded relative mb-4 bg-green-100 border-green-400 text-green-700' role='alert'>
                    <span class='block sm:inline'><?php echo htmlspecialchars($success_message); ?></span>
                </div>
             <?php endif; ?>
             <?php if ($error_message): ?>
                <div class='border px-4 py-3 rounded relative mb-4 bg-red-100 border-red-400 text-red-700' role='alert'>
                    <span class='block sm:inline'><?php echo $error_message; // Already contains HTML potentially, no htmlspecialchars ?></span>
                </div>
            <?php endif; ?>

            <div class="bg-white p-6 rounded-lg shadow border border-gray-200">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Election Progress</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Status</th>
                                <th>Eligible Voters</th>
                                <th>Votes Cast</th>
                                <th>Turnout</th>
                                <th>End Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($elections_data)): ?>
                                <?php foreach ($elections_data as $election): ?>
                                    <?php $status_info = get_status_info_monitor($election['Status'], $election['StartDate'], $election['EndDate']); ?>
                                    <tr>
                                        <td><?php echo $election['ElectionID']; ?></td>
                                        <td class="font-medium text-gray-900"><?php echo htmlspecialchars($election['Title']); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo $status_info['color']; ?>">
                                                <?php echo htmlspecialchars($status_info['text']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $election['TotalEligible']; ?></td>
                                        <td><?php echo $election['VotedCount']; ?> (<?php echo $election['TotalVotesCast']; ?> total votes)</td>
                                        <td>
                                            <div class="flex items-center">
                                                <div class="progress-bar-bg mr-2">
                                                    <div class="progress-bar-fill" style="width: <?php echo $election['TurnoutPercentage']; ?>%;" title="<?php echo $election['TurnoutPercentage']; ?>%">
                                                        <?php if ($election['TurnoutPercentage'] > 15) echo $election['TurnoutPercentage'] . '%'; ?>
                                                    </div>
                                                </div>
                                                 <?php if ($election['TurnoutPercentage'] <= 15) echo $election['TurnoutPercentage'] . '%'; ?>
                                            </div>
                                        </td>
                                        <td><?php echo date('M j, Y g:i A', strtotime($election['EndDate'])); ?></td>
                                        <td>
                                            <?php
                                            // Show "Close Election" button only if status is Active AND EndDate has passed
                                            if ($election['Status'] === 'Active' && $now > $election['EndDate']):
                                            ?>
                                                <form action="includes/handle_close_election.php" method="POST" style="display: inline;">
                                                    <input type="hidden" name="election_id" value="<?php echo $election['ElectionID']; ?>">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                    <button type="submit"
                                                            class="action-btn bg-red-500 hover:bg-red-600"
                                                            title="Close Election"
                                                            onclick="return confirm('Are you sure you want to close this election? This will finalize the voting period.');">
                                                        <i class="fas fa-lock mr-1"></i> Close
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <?php
                                            // Show "View Results" button if election is Closed
                                            if ($election['Status'] === 'Closed'):
                                            ?>
                                                <a href="results.php?election_id=<?php echo $election['ElectionID']; ?>"
                                                   class="action-btn bg-purple-500 hover:bg-purple-600"
                                                   title="View Results">
                                                    <i class="fas fa-chart-bar mr-1"></i> Results
                                                </a>
                                            <?php endif; ?>
                                            </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center text-gray-500 py-4">No active or recently closed elections found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <footer class="text-center text-gray-500 text-sm mt-8">
                &copy; <?php echo date("Y"); ?> Online Voting System. Admin Panel.
            </footer>
        </div>
    </div>

    <script>
        // Sidebar toggle script (copy from dashboard.php or other admin pages)
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
        // Initialize sidebar state based on screen size
        if (window.innerWidth < 768) { sidebar.classList.add('-translate-x-full'); }
    </script>
</body>
</html>

