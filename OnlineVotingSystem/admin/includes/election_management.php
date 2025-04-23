<?php
// admin/election_management.php
session_start();

// --- Authentication and Authorization Check ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
    $_SESSION['login_message'] = "You must be logged in as an administrator to access this page.";
    header("Location: login.php");
    exit();
}

// Include the database connection file and any helper functions
require_once 'includes/db_connect.php';
// require_once 'includes/admin_helpers.php'; // Optional: For functions like display_status_badge

// Get admin information from session
$admin_id = $_SESSION['user_id'];
$admin_username = $_SESSION['username'];

// --- Fetch Election Data ---
$elections = [];
$error_message = null;

// Get current time for status determination (though DB status should be primary)
$now = date('Y-m-d H:i:s');

// SQL to fetch all elections, potentially joining with user who created it
// Order by creation date or start date, newest first
$sql = "SELECT
            e.ElectionID,
            e.Title,
            e.StartDate,
            e.EndDate,
            e.VotingMethod,
            e.Status,
            u.Username AS CreatedByAdmin
        FROM Elections e
        LEFT JOIN Users u ON e.CreatedByAdminID = u.UserID AND u.Role = 'Admin'
        ORDER BY e.CreatedAt DESC"; // Or ORDER BY e.StartDate DESC

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $elections[] = $row;
    }
} else {
    $error_message = "Error fetching elections: " . $conn->error;
    error_log($error_message); // Log the detailed error
}

// Function to determine display status and color (can be moved to helpers)
function get_status_info($status, $start_date, $end_date) {
    global $now; // Use the global current time variable
    $display_status = $status;
    $color_class = 'bg-gray-500'; // Default: Pending/Archived

    // Refine status based on dates if needed (DB status should ideally be accurate)
    if ($status === 'Pending' && $start_date > $now) {
        $display_status = 'Upcoming';
        $color_class = 'bg-yellow-500';
    } elseif ($status === 'Active' && $start_date <= $now && $end_date >= $now) {
        $display_status = 'Active';
        $color_class = 'bg-green-500';
    } elseif ($status === 'Active' && $end_date < $now) {
        // If DB status is Active but end date passed, show as Closed
        $display_status = 'Closed (Ended)';
        $color_class = 'bg-red-500';
        // Ideally, a background job would update Status to 'Closed' in DB
    } elseif ($status === 'Closed') {
        $display_status = 'Closed';
        $color_class = 'bg-red-500';
    } elseif ($status === 'Archived') {
         $display_status = 'Archived';
         $color_class = 'bg-purple-500';
    }

    return ['text' => $display_status, 'color' => $color_class];
}

// Close the connection if done with DB operations for this page load
// $conn->close(); // Keep open if needed later on the page

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Election Management - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style>
        body { font-family: 'Inter', sans-serif; }
        /* Sidebar and responsive styles from dashboard */
        .sidebar { width: 250px; transition: transform 0.3s ease-in-out; }
        .content { margin-left: 250px; transition: margin-left 0.3s ease-in-out; }
        .sidebar-collapsed { transform: translateX(-100%); }
        .content-expanded { margin-left: 0; }
         @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); z-index: 40; }
            .content { margin-left: 0; }
            .sidebar-toggle { display: block; }
            .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); z-index: 30; }
            .sidebar.open + .overlay { display: block; }
        }
        @media (min-width: 769px) {
             .sidebar-toggle-close, .sidebar-toggle-open { display: none; }
        }
        /* Table specific styles */
        th, td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid #e2e8f0; /* gray-200 */ }
        th { background-color: #f8fafc; /* gray-50 */ font-weight: 600; /* font-semibold */ color: #4b5563; /* gray-600 */ }
        tbody tr:hover { background-color: #f9fafb; /* gray-50 */ }
        .action-btn { padding: 0.3rem 0.6rem; font-size: 0.8rem; margin-right: 0.25rem; border-radius: 0.375rem; /* rounded-md */ transition: background-color 0.2s; }
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
                <a href="dashboard.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700">
                    <i class="fas fa-tachometer-alt mr-2 w-5 text-center"></i>Dashboard
                </a>
                <a href="election_management.php" class="block py-2.5 px-4 rounded transition duration-200 bg-indigo-600 font-semibold">
                    <i class="fas fa-poll-h mr-2 w-5 text-center"></i>Elections
                </a>
                <a href="candidate_management.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700">
                    <i class="fas fa-users mr-2 w-5 text-center"></i>Candidates
                </a>
                <a href="voter_management.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700">
                    <i class="fas fa-user-check mr-2 w-5 text-center"></i>Voters
                </a>
                 <a href="results.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700">
                    <i class="fas fa-chart-bar mr-2 w-5 text-center"></i>Results
                </a>
                <a href="user_management.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700">
                    <i class="fas fa-user-cog mr-2 w-5 text-center"></i>Admin Users
                </a>
                <a href="audit_logs.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700">
                    <i class="fas fa-history mr-2 w-5 text-center"></i>Audit Logs
                </a>
                <a href="settings.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700">
                    <i class="fas fa-sliders-h mr-2 w-5 text-center"></i>Settings
                </a>
                <a href="includes/logout.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-red-600 mt-8 text-red-300 hover:text-white">
                    <i class="fas fa-sign-out-alt mr-2 w-5 text-center"></i>Logout
                </a>
            </nav>
        </aside>

        <div class="content flex-1 p-6 md:p-10">
             <header class="bg-white shadow rounded-lg p-4 mb-6 flex justify-between items-center">
                 <button class="sidebar-toggle-open md:hidden text-gray-600 focus:outline-none"><i class="fas fa-bars text-xl"></i></button>
                 <h1 class="text-xl md:text-2xl font-semibold text-gray-700">Election Management</h1>
                 <div>
                     <span class="text-gray-600 text-sm md:text-base">Welcome, <?php echo htmlspecialchars($admin_username); ?>!</span>
                 </div>
             </header>

            <div class="bg-white p-6 rounded-lg shadow border border-gray-200">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold text-gray-800">Manage Elections</h2>
                    <a href="election_create.php" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg transition duration-300 shadow text-sm">
                        <i class="fas fa-plus mr-1"></i> Create New Election
                    </a>
                </div>

                <?php
                if (isset($_SESSION['message'])) {
                    $message = $_SESSION['message'];
                    $status = $_SESSION['message_status'] ?? 'info'; // Default to info
                    $bgColor = ($status === 'success') ? 'bg-green-100 border-green-400 text-green-700' : (($status === 'error') ? 'bg-red-100 border-red-400 text-red-700' : 'bg-blue-100 border-blue-400 text-blue-700');
                    echo "<div class='border px-4 py-3 rounded relative mb-4 {$bgColor}' role='alert'>";
                    echo "<span class='block sm:inline'>" . htmlspecialchars($message) . "</span>";
                    echo "</div>";
                    unset($_SESSION['message']);
                    unset($_SESSION['message_status']);
                }
                if ($error_message) {
                     echo "<div class='border px-4 py-3 rounded relative mb-4 bg-red-100 border-red-400 text-red-700' role='alert'>";
                     echo "<span class='block sm:inline'>" . htmlspecialchars($error_message) . "</span>";
                     echo "</div>";
                }
                ?>

                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Status</th>
                                <th>Method</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($elections)): ?>
                                <?php foreach ($elections as $election): ?>
                                    <?php $status_info = get_status_info($election['Status'], $election['StartDate'], $election['EndDate']); ?>
                                    <tr>
                                        <td><?php echo $election['ElectionID']; ?></td>
                                        <td class="font-medium text-gray-900"><?php echo htmlspecialchars($election['Title']); ?></td>
                                        <td><?php echo date('M j, Y g:i A', strtotime($election['StartDate'])); ?></td>
                                        <td><?php echo date('M j, Y g:i A', strtotime($election['EndDate'])); ?></td>
                                        <td>
                                            <span class="text-xs font-semibold inline-block py-1 px-2 uppercase rounded-full text-white <?php echo $status_info['color']; ?>">
                                                <?php echo htmlspecialchars($status_info['text']); ?>
                                            </span>
                                        </td>
                                         <td><?php echo htmlspecialchars($election['VotingMethod']); ?></td>
                                        <td>
                                            <?php if ($election['Status'] === 'Pending' || $election['Status'] === 'Active' && $election['StartDate'] > $now): // Can edit if pending or upcoming ?>
                                                <a href="election_edit.php?id=<?php echo $election['ElectionID']; ?>" class="action-btn bg-yellow-500 hover:bg-yellow-600 text-white" title="Edit Election"><i class="fas fa-edit"></i></a>
                                            <?php endif; ?>
                                            <?php // Can manage candidates before election starts ?>
                                             <a href="candidate_management.php?election_id=<?php echo $election['ElectionID']; ?>" class="action-btn bg-blue-500 hover:bg-blue-600 text-white" title="Manage Candidates"><i class="fas fa-users"></i></a>
                                            <?php if ($election['Status'] === 'Closed' || $election['Status'] === 'Archived'): ?>
                                                <a href="results.php?election_id=<?php echo $election['ElectionID']; ?>" class="action-btn bg-purple-500 hover:bg-purple-600 text-white" title="View Results"><i class="fas fa-chart-bar"></i></a>
                                            <?php endif; ?>
                                             <?php if ($election['Status'] === 'Pending' || $election['Status'] === 'Closed' || $election['Status'] === 'Archived'): // Allow delete for non-active ?>
                                                 <a href="election_delete.php?id=<?php echo $election['ElectionID']; ?>"
                                                    class="action-btn bg-red-500 hover:bg-red-600 text-white"
                                                    title="Delete Election"
                                                    onclick="return confirm('Are you sure you want to delete this election? This action cannot be undone.');">
                                                     <i class="fas fa-trash-alt"></i>
                                                 </a>
                                             <?php endif; ?>
                                             </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-gray-500 py-4">No elections found.</td>
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
        // Sidebar toggle script from dashboard
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
        if (window.innerWidth < 768) sidebar.classList.add('-translate-x-full');
    </script>

</body>
</html>
