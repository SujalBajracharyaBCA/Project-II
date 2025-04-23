<?php
// admin/voter_management.php
session_start();
require_once 'includes/db_connect.php'; // Ensure this path is correct

// --- Authentication & Authorization ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
    $_SESSION['login_message'] = "Access denied. Please log in as an administrator.";
    header("Location: login.php");
    exit();
}
$admin_id = $_SESSION['user_id']; // Get current admin's ID (useful for preventing self-modification if needed)
$admin_username = $_SESSION['username']; // For display

// --- Handle Actions (Activate/Deactivate) ---
$success_message = null;
$error_message = null;

// Retrieve potential messages from session (e.g., after redirection from action handling)
if (isset($_SESSION['message'])) {
    if ($_SESSION['message_status'] === 'success') {
        $success_message = $_SESSION['message'];
    } else {
        $error_message = $_SESSION['message'];
    }
    unset($_SESSION['message']);
    unset($_SESSION['message_status']);
}

// Check if an action (activate/deactivate) is requested via GET
if (isset($_GET['action']) && isset($_GET['user_id'])) {
    // **CSRF Protection Placeholder:**
    // In a real application, generate a token on page load and include it in the links:
    // On page load: $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    // In link: <a href="...?action=...&user_id=...&token=<?php echo $_SESSION['csrf_token']; ? >">...</a>
    // Check here: if (!isset($_GET['token']) || !hash_equals($_SESSION['csrf_token'], $_GET['token'])) { die('Invalid CSRF token'); }

    $action = $_GET['action'];
    $user_id_to_modify = filter_var($_GET['user_id'], FILTER_VALIDATE_INT);

    if ($user_id_to_modify) {
        $new_status = null;
        $action_verb = ''; // For success/error messages

        if ($action === 'activate') {
            $new_status = 1; // Represents TRUE in the database BOOLEAN/TINYINT(1) field
            $action_verb = 'activated';
        } elseif ($action === 'deactivate') {
            $new_status = 0; // Represents FALSE
            $action_verb = 'deactivated';
        }

        if ($new_status !== null) {
            // Prepare the UPDATE statement to change the IsActive status
            // Ensure we only modify users with the 'Voter' role
            $sql_update = "UPDATE Users SET IsActive = ? WHERE UserID = ? AND Role = 'Voter'";
            $stmt_update = $conn->prepare($sql_update);

            if ($stmt_update) {
                $stmt_update->bind_param("ii", $new_status, $user_id_to_modify);
                if ($stmt_update->execute()) {
                    // Check if any row was actually updated
                    if ($stmt_update->affected_rows > 0) {
                        $_SESSION['message'] = "Voter account successfully " . $action_verb . ".";
                        $_SESSION['message_status'] = "success";
                    } else {
                        // This could mean the user ID didn't exist, wasn't a voter, or the status was already set to the target value.
                        $_SESSION['message'] = "Voter not found, role mismatch, or status already set.";
                        $_SESSION['message_status'] = "error"; // Or potentially 'info'
                    }
                } else {
                    // Database execution error
                    $_SESSION['message'] = "Error updating voter status: " . $stmt_update->error;
                    $_SESSION['message_status'] = "error";
                    error_log("Execute failed (update voter status): " . $stmt_update->error); // Log detailed error
                }
                $stmt_update->close();
            } else {
                 // Database prepare statement error
                 $_SESSION['message'] = "Database error preparing update statement.";
                 $_SESSION['message_status'] = "error";
                 error_log("Prepare failed (update voter status): " . $conn->error); // Log detailed error
            }
        } else {
             // Invalid action parameter
             $_SESSION['message'] = "Invalid action specified.";
             $_SESSION['message_status'] = "error";
        }
    } else {
         // Invalid user ID parameter
         $_SESSION['message'] = "Invalid User ID specified.";
         $_SESSION['message_status'] = "error";
    }

    // Redirect back to the voter management page without the GET parameters
    // This prevents accidental re-submission if the user refreshes the page
    header("Location: voter_management.php");
    exit();
}


// --- Fetch Voter Data ---
$voters = [];
// Consider adding pagination for large numbers of voters in a real application
// Select all users with the 'Voter' role
$sql_fetch_voters = "SELECT UserID, Username, FullName, Email, RegistrationDate, IsActive FROM Users WHERE Role = 'Voter' ORDER BY RegistrationDate DESC";
$result_voters = $conn->query($sql_fetch_voters); // Using query() as there's no user input here

if ($result_voters) {
    while ($row = $result_voters->fetch_assoc()) {
        $voters[] = $row; // Add each voter's data to the array
    }
} else {
    // Assign error message if fetching fails, to be displayed later
    $error_message = "Error fetching voters: " . $conn->error;
    error_log($error_message); // Log the detailed error
}

// Close the database connection
$conn->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voter Management - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style>
        body { font-family: 'Inter', sans-serif; }
        /* Sidebar and responsive styles (same as dashboard.php / election_management.php) */
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
        th, td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid #e2e8f0; /* gray-200 */ vertical-align: middle;}
        th { background-color: #f8fafc; /* gray-50 */ font-weight: 600; /* font-semibold */ color: #4b5563; /* gray-600 */ }
        tbody tr:hover { background-color: #f9fafb; /* gray-50 */ }
        .action-btn {
            padding: 0.3rem 0.6rem;
            font-size: 0.8rem; /* Smaller font size */
            margin-right: 0.25rem; /* Space between buttons */
            border-radius: 0.375rem; /* rounded-md */
            transition: background-color 0.2s;
            display: inline-flex; /* Align icon and text */
            align-items: center;
            justify-content: center;
            text-decoration: none; /* Remove underline from links */
            color: white; /* White text for buttons */
            min-width: 80px; /* Ensure buttons have minimum width */
            text-align: center;
        }
        .status-badge {
             padding: 0.2rem 0.5rem;
             font-size: 0.75rem; /* text-xs */
             font-weight: 600; /* font-semibold */
             border-radius: 9999px; /* rounded-full */
             display: inline-block;
        }
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
                <a href="candidate_management.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700"><i class="fas fa-users mr-2 w-5 text-center"></i>Candidates</a>
                <a href="voter_management.php" class="block py-2.5 px-4 rounded transition duration-200 bg-indigo-600 font-semibold"><i class="fas fa-user-check mr-2 w-5 text-center"></i>Voters</a> <a href="results.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700"><i class="fas fa-chart-bar mr-2 w-5 text-center"></i>Results</a>
                <a href="user_management.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700"><i class="fas fa-user-cog mr-2 w-5 text-center"></i>Admin Users</a>
                <a href="audit_logs.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700"><i class="fas fa-history mr-2 w-5 text-center"></i>Audit Logs</a>
                <a href="settings.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700"><i class="fas fa-sliders-h mr-2 w-5 text-center"></i>Settings</a>
                <a href="includes/logout.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-red-600 mt-8 text-red-300 hover:text-white"><i class="fas fa-sign-out-alt mr-2 w-5 text-center"></i>Logout</a>
             </nav>
        </aside>

        <div class="content flex-1 p-6 md:p-10">
             <header class="bg-white shadow rounded-lg p-4 mb-6 flex justify-between items-center">
                 <button class="sidebar-toggle-open md:hidden text-gray-600 focus:outline-none"><i class="fas fa-bars text-xl"></i></button>
                 <h1 class="text-xl md:text-2xl font-semibold text-gray-700">Voter Management</h1>
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
                     <span class='block sm:inline'><?php echo htmlspecialchars($error_message); ?></span>
                 </div>
             <?php endif; ?>

             <div class="bg-white p-6 rounded-lg shadow border border-gray-200">
                 <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold text-gray-800">Registered Voters</h2>
                    </div>

                 <div class="overflow-x-auto">
                    <table class="min-w-full bg-white">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Registered</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                             <?php if (!empty($voters)): ?>
                                <?php foreach ($voters as $voter): ?>
                                    <tr>
                                        <td><?php echo $voter['UserID']; ?></td>
                                        <td class="font-medium text-gray-900"><?php echo htmlspecialchars($voter['Username']); ?></td>
                                        <td><?php echo htmlspecialchars($voter['FullName'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($voter['Email']); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($voter['RegistrationDate'])); ?></td>
                                        <td>
                                            <?php if ($voter['IsActive']): ?>
                                                <span class="status-badge bg-green-100 text-green-800">Active</span>
                                            <?php else: ?>
                                                <span class="status-badge bg-red-100 text-red-800">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($voter['IsActive']): ?>
                                                <a href="voter_management.php?action=deactivate&user_id=<?php echo $voter['UserID']; ?>"
                                                   class="action-btn bg-yellow-500 hover:bg-yellow-600"
                                                   title="Deactivate Voter"
                                                   onclick="return confirm('Are you sure you want to deactivate this voter account?');">
                                                   <i class="fas fa-user-times mr-1"></i> Deactivate
                                                </a>
                                            <?php else: ?>
                                                <a href="voter_management.php?action=activate&user_id=<?php echo $voter['UserID']; ?>"
                                                   class="action-btn bg-green-500 hover:bg-green-600"
                                                   title="Activate Voter"
                                                   onclick="return confirm('Are you sure you want to activate this voter account?');">
                                                   <i class="fas fa-user-check mr-1"></i> Activate
                                                </a>
                                            <?php endif; ?>
                                            </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-gray-500 py-4">No voters found.</td>
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