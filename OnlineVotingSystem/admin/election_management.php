<?php
// admin/election_management.php
session_start();

// *** Set the default timezone for PHP ***
// Ensure this matches your server and database timezone expectations
date_default_timezone_set('Asia/Kathmandu'); // Set to Nepal Time

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

// --- Generate CSRF token if not already set ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];


// --- Handle Session Messages ---
$success_message = null;
$error_message = null;
if (isset($_SESSION['message'])) {
    if ($_SESSION['message_status'] === 'success') {
        $success_message = $_SESSION['message'];
    } else {
        $error_message = $_SESSION['message'];
    }
    unset($_SESSION['message']);
    unset($_SESSION['message_status']);
}


// --- Fetch Election Data ---
$elections = [];
$fetch_error = null; // Use a different variable for fetch errors

// Get current time using the explicitly set timezone
$now_dt = new DateTime(); // Will use 'Asia/Kathmandu'
$now_str = $now_dt->format('Y-m-d H:i:s');

// SQL to fetch all elections
$sql = "SELECT
            e.ElectionID,
            e.Title,
            e.StartDate,
            e.EndDate,
            e.VotingMethod,
            e.Status,
            u.Username AS CreatedByAdmin
        FROM Elections e
        LEFT JOIN Users u ON e.CreatedByAdminID = u.UserID -- Assuming Users table exists
        ORDER BY e.StartDate DESC, e.CreatedAt DESC"; // Order by start date, then creation

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Parse dates into DateTime objects for reliable comparison
        // DateTime constructor uses the default timezone set above unless specified otherwise
        try {
             // It's safer to specify the timezone if you know the DB stores UTC or another specific zone
             // Example if DB stores UTC:
             // $row['StartDateDT'] = new DateTime($row['StartDate'], new DateTimeZone('UTC'));
             // $row['EndDateDT'] = new DateTime($row['EndDate'], new DateTimeZone('UTC'));
             // $row['StartDateDT']->setTimezone(new DateTimeZone('Asia/Kathmandu')); // Convert to local for display/comparison logic
             // $row['EndDateDT']->setTimezone(new DateTimeZone('Asia/Kathmandu'));

             // Assuming DB stores dates in the intended local timezone (or PHP's default is correctly set)
            $row['StartDateDT'] = new DateTime($row['StartDate']);
            $row['EndDateDT'] = new DateTime($row['EndDate']);
        } catch (Exception $e) {
             // Handle potential date parsing errors
             error_log("Error parsing date for Election ID " . $row['ElectionID'] . ": " . $e->getMessage());
             // Assign default or null values to prevent errors later
             $row['StartDateDT'] = null;
             $row['EndDateDT'] = null;
        }
        $elections[] = $row;
    }
} else {
    $fetch_error = "Error fetching elections: " . $conn->error;
    error_log($fetch_error); // Log the detailed error
}

// Function to determine display status and color
// Ensure DateTime objects are passed if available
function get_status_info($status, $start_date_dt, $end_date_dt, $now_dt) {
    $display_status = $status;
    $color_class = 'bg-gray-500'; // Default: Pending/Archived

    // Check if dates are valid DateTime objects before comparing
    if (!$start_date_dt || !$end_date_dt) {
         $display_status = 'Error (Date Invalid)';
         $color_class = 'bg-black';
         return ['text' => $display_status, 'color' => $color_class];
    }


    if ($status === 'Pending' && $start_date_dt > $now_dt) {
        $display_status = 'Upcoming';
        $color_class = 'bg-yellow-500';
    } elseif ($status === 'Pending' && $start_date_dt <= $now_dt) {
        $display_status = 'Ready to Activate'; // Indicate it can be activated
        $color_class = 'bg-blue-500';
    } elseif ($status === 'Active' && $end_date_dt >= $now_dt) {
        $display_status = 'Active';
        $color_class = 'bg-green-500';
    } elseif ($status === 'Active' && $end_date_dt < $now_dt) {
        // DB status is Active but end date passed
        $display_status = 'Ended (Needs Closing)';
        $color_class = 'bg-orange-500'; // Indicate action needed
    } elseif ($status === 'Closed') {
        $display_status = 'Closed';
        $color_class = 'bg-red-500';
    } elseif ($status === 'Archived') {
         $display_status = 'Archived';
         $color_class = 'bg-purple-500';
    }

    return ['text' => $display_status, 'color' => $color_class];
}

$conn->close(); // Close connection after fetching

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
        @media (max-width: 768px) { .sidebar { transform: translateX(-100%); } .sidebar.open { transform: translateX(0); z-index: 40; } .content { margin-left: 0; } .sidebar-toggle { display: block; } .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); z-index: 30; } .sidebar.open + .overlay { display: block; } }
        @media (min-width: 769px) { .sidebar-toggle-close, .sidebar-toggle-open { display: none; } }
        /* Table specific styles */
        th, td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid #e2e8f0; vertical-align: top; /* Align top for actions */ }
        th { background-color: #f8fafc; font-weight: 600; color: #4b5563; }
        tbody tr:hover { background-color: #f9fafb; }
        .action-btn { padding: 0.3rem 0.6rem; font-size: 0.8rem; margin-right: 0.25rem; margin-bottom: 0.25rem; border-radius: 0.375rem; transition: background-color 0.2s; display: inline-flex; align-items: center; text-decoration: none; color: white; border: none; cursor: pointer; }
        .actions-cell form { display: inline-block; margin: 0; padding: 0; } /* Style forms for actions */
        .status-badge { padding: 0.2rem 0.5rem; font-size: 0.75rem; font-weight: 600; border-radius: 9999px; display: inline-block; text-align: center; }
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
                <a href="election_management.php" class="block py-2.5 px-4 rounded transition duration-200 bg-indigo-600 font-semibold"><i class="fas fa-poll-h mr-2 w-5 text-center"></i>Elections</a>
                 <a href="voting_monitor.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700"><i class="fas fa-tv mr-2 w-5 text-center"></i>Voting Monitor</a>
                <a href="voter_management.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700"><i class="fas fa-user-check mr-2 w-5 text-center"></i>Voters</a>
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

                <?php if ($success_message): ?>
                    <div class='border px-4 py-3 rounded relative mb-4 bg-green-100 border-green-400 text-green-700' role='alert'>
                        <span class='block sm:inline'><?php echo htmlspecialchars($success_message); ?></span>
                    </div>
                 <?php endif; ?>
                 <?php if ($error_message): ?>
                    <div class='border px-4 py-3 rounded relative mb-4 bg-red-100 border-red-400 text-red-700' role='alert'>
                        <span class='block sm:inline'><?php echo htmlspecialchars($error_message); ?></span>
                    </div>
                <?php endif; ?>
                 <?php if ($fetch_error): ?>
                    <div class='border px-4 py-3 rounded relative mb-4 bg-red-100 border-red-400 text-red-700' role='alert'>
                        <span class='block sm:inline'><?php echo htmlspecialchars($fetch_error); ?></span>
                    </div>
                <?php endif; ?>


                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Dates</th>
                                <th>Status</th>
                                <th>Method</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($elections)): ?>
                                <?php foreach ($elections as $election): ?>
                                     <?php
                                         // Ensure dates were parsed correctly before using them
                                         $start_dt = $election['StartDateDT'];
                                         $end_dt = $election['EndDateDT'];
                                         $status_info = get_status_info($election['Status'], $start_dt, $end_dt, $now_dt);
                                     ?>
                                    <tr>
                                        <td><?php echo $election['ElectionID']; ?></td>
                                        <td class="font-medium text-gray-900"><?php echo htmlspecialchars($election['Title']); ?></td>
                                        <td class="text-xs">
                                             <?php if ($start_dt): ?>
                                                 Start: <?php echo $start_dt->format('M j, Y g:i A'); ?><br>
                                             <?php endif; ?>
                                             <?php if ($end_dt): ?>
                                                 End: <?php echo $end_dt->format('M j, Y g:i A'); ?>
                                             <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status-badge text-white <?php echo $status_info['color']; ?>">
                                                <?php echo htmlspecialchars($status_info['text']); ?>
                                            </span>
                                        </td>
                                         <td><?php echo htmlspecialchars($election['VotingMethod']); ?></td>
                                        <td class="actions-cell whitespace-nowrap">
                                            <?php // --- Action Buttons --- ?>

                                            <?php // Activate Button Logic ?>
                                            <?php if ($election['Status'] === 'Pending' && $start_dt && $start_dt <= $now_dt): ?>
                                                <form action="includes/handle_election_status.php" method="POST">
                                                    <input type="hidden" name="action" value="activate">
                                                    <input type="hidden" name="election_id" value="<?php echo $election['ElectionID']; ?>">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                    <button type="submit" class="action-btn bg-green-500 hover:bg-green-600" title="Activate Election" onclick="return confirm('Activate this election? Voters will be able to vote.');">
                                                        <i class="fas fa-play-circle mr-1"></i> Activate
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <?php // Close Button Logic ?>
                                            <?php if ($election['Status'] === 'Active' && $end_dt && $end_dt <= $now_dt): ?>
                                                 <form action="includes/handle_election_status.php" method="POST">
                                                    <input type="hidden" name="action" value="close">
                                                    <input type="hidden" name="election_id" value="<?php echo $election['ElectionID']; ?>">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                    <button type="submit" class="action-btn bg-red-500 hover:bg-red-600" title="Close Election" onclick="return confirm('Manually close this election? Voting will stop.');">
                                                        <i class="fas fa-stop-circle mr-1"></i> Close
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <?php // Edit Button Logic ?>
                                            <?php if ($election['Status'] === 'Pending' || ($election['Status'] === 'Active' && $start_dt && $start_dt > $now_dt)): ?>
                                                <a href="election_edit.php?id=<?php echo $election['ElectionID']; ?>" class="action-btn bg-yellow-500 hover:bg-yellow-600 text-white" title="Edit Election"><i class="fas fa-edit"></i> Edit</a>
                                            <?php endif; ?>

                                            <?php // Manage Candidates Button Logic (Allow before start) ?>
                                            <?php if ($election['Status'] === 'Pending' || ($election['Status'] === 'Active' && $start_dt && $start_dt > $now_dt)): ?>
                                                <a href="candidate_management.php?election_id=<?php echo $election['ElectionID']; ?>" class="action-btn bg-blue-500 hover:bg-blue-600 text-white" title="Manage Candidates"><i class="fas fa-users"></i> Candidates</a>
                                            <?php endif; ?>

                                            <?php // Manage Voters Button Logic (Allow before end?) ?>
                                            <?php if ($election['Status'] !== 'Closed' && $election['Status'] !== 'Archived'): // Example: Allow until closed ?>
                                                 <a href="voter_eligibility.php?election_id=<?php echo $election['ElectionID']; ?>" class="action-btn bg-cyan-500 hover:bg-cyan-600 text-white" title="Manage Eligible Voters"><i class="fas fa-user-check"></i> Voters</a>
                                            <?php endif; ?>

                                            <?php // View Results Button Logic ?>
                                            <?php if ($election['Status'] === 'Closed' || $election['Status'] === 'Archived'): ?>
                                                <a href="results.php?election_id=<?php echo $election['ElectionID']; ?>" class="action-btn bg-purple-500 hover:bg-purple-600 text-white" title="View Results"><i class="fas fa-chart-bar"></i> Results</a>
                                            <?php endif; ?>

                                             <?php // Delete Button Logic (Using POST Form) ?>
                                             <?php if ($election['Status'] !== 'Active'): // Allow delete for non-active ?>
                                                 <form action="includes/election_delete.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this election? This action CANNOT be undone and requires the election to have no votes.');">
                                                     <input type="hidden" name="id" value="<?php echo $election['ElectionID']; ?>">
                                                     <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                     <button type="submit" class="action-btn bg-red-600 hover:bg-red-700 text-white" title="Delete Election">
                                                          <i class="fas fa-trash-alt"></i> Delete
                                                     </button>
                                                 </form>
                                             <?php endif; ?>
                                         </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-gray-500 py-4">No elections found. <a href="election_create.php" class="text-indigo-600 hover:underline">Create one?</a></td>
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
