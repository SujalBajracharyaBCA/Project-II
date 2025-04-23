<?php
// admin/user_management.php
session_start();
require_once 'includes/db_connect.php'; // Ensure this path is correct

// --- Authentication & Authorization ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
    $_SESSION['login_message'] = "Access denied. Please log in as an administrator.";
    header("Location: login.php");
    exit();
}
$current_admin_id = $_SESSION['user_id']; // Get current admin's ID to prevent self-modification
$admin_username = $_SESSION['username']; // For display

// --- Handle Session Messages (e.g., after activate/deactivate) ---
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

// --- Fetch Admin User Data ---
$admin_users = [];
// Select all users with the 'Admin' role
$sql_fetch_admins = "SELECT UserID, Username, FullName, Email, RegistrationDate, IsActive FROM Users WHERE Role = 'Admin' ORDER BY RegistrationDate DESC";
$result_admins = $conn->query($sql_fetch_admins);

if ($result_admins) {
    while ($row = $result_admins->fetch_assoc()) {
        $admin_users[] = $row; // Add each admin's data to the array
    }
} else {
    $error_message = ($error_message ? $error_message . "<br>" : '') . "Error fetching admin users: " . $conn->error;
    error_log("Error fetching admin users: " . $conn->error); // Log the detailed error
}

// Generate CSRF token if not already set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$conn->close(); // Close the connection

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin User Management - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style>
        body { font-family: 'Inter', sans-serif; }
        /* Sidebar, responsive, table, button styles (copy from voter_management.php or other admin pages) */
        .sidebar { width: 250px; transition: transform 0.3s ease-in-out; }
        .content { margin-left: 250px; transition: margin-left 0.3s ease-in-out; }
        @media (max-width: 768px) { .sidebar { transform: translateX(-100%); } .sidebar.open { transform: translateX(0); z-index: 40; } .content { margin-left: 0; } .sidebar-toggle { display: block; } .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); z-index: 30; } .sidebar.open + .overlay { display: block; } }
        @media (min-width: 769px) { .sidebar-toggle-close, .sidebar-toggle-open { display: none; } }
        th, td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
        th { background-color: #f8fafc; font-weight: 600; color: #4b5563; }
        tbody tr:hover { background-color: #f9fafb; }
        .action-btn { padding: 0.3rem 0.6rem; font-size: 0.8rem; margin-right: 0.25rem; border-radius: 0.375rem; transition: background-color 0.2s; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; color: white; min-width: 90px; text-align: center; }
        .status-badge { padding: 0.2rem 0.5rem; font-size: 0.75rem; font-weight: 600; border-radius: 9999px; display: inline-block; }
        /* Add button style */
        .add-btn { background-color: #10b981; /* green-500 */ hover:bg-green-600; color: white; font-bold: py-2 px-4; border-radius: 0.375rem; transition: background-color 0.3s; text-decoration: none; display: inline-flex; align-items: center; }
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
                 <a href="voting_monitor.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700"><i class="fas fa-tv mr-2 w-5 text-center"></i>Voting Monitor</a>
                 <a href="results.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700"><i class="fas fa-chart-bar mr-2 w-5 text-center"></i>Results</a>
                 <a href="user_management.php" class="block py-2.5 px-4 rounded transition duration-200 bg-indigo-600 font-semibold"><i class="fas fa-user-cog mr-2 w-5 text-center"></i>Admin Users</a>
                 <a href="audit_logs.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700"><i class="fas fa-history mr-2 w-5 text-center"></i>Audit Logs</a>
                 <a href="settings.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700"><i class="fas fa-sliders-h mr-2 w-5 text-center"></i>Settings</a>
                 <a href="includes/logout.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-red-600 mt-8 text-red-300 hover:text-white"><i class="fas fa-sign-out-alt mr-2 w-5 text-center"></i>Logout</a>
            </nav>
        </aside>

        <div class="content flex-1 p-6 md:p-10">
            <header class="bg-white shadow rounded-lg p-4 mb-6 flex justify-between items-center">
                <button class="sidebar-toggle-open md:hidden text-gray-600 focus:outline-none"><i class="fas fa-bars text-xl"></i></button>
                <h1 class="text-xl md:text-2xl font-semibold text-gray-700">Admin User Management</h1>
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
                   <h2 class="text-xl font-semibold text-gray-800">Administrator Accounts</h2>
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
                            <?php if (!empty($admin_users)): ?>
                               <?php foreach ($admin_users as $admin): ?>
                                   <tr>
                                       <td><?php echo $admin['UserID']; ?></td>
                                       <td class="font-medium text-gray-900"><?php echo htmlspecialchars($admin['Username']); ?></td>
                                       <td><?php echo htmlspecialchars($admin['FullName'] ?? 'N/A'); ?></td>
                                       <td><?php echo htmlspecialchars($admin['Email']); ?></td>
                                       <td><?php echo date('M j, Y', strtotime($admin['RegistrationDate'])); ?></td>
                                       <td>
                                           <?php if ($admin['IsActive']): ?>
                                               <span class="status-badge bg-green-100 text-green-800">Active</span>
                                           <?php else: ?>
                                               <span class="status-badge bg-red-100 text-red-800">Inactive</span>
                                           <?php endif; ?>
                                       </td>
                                       <td>
                                           <?php if ($admin['UserID'] != $current_admin_id): // Prevent actions on self ?>
                                               <?php if ($admin['IsActive']): ?>
                                                   <form action="includes/handle_admin_user_action.php" method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="deactivate">
                                                        <input type="hidden" name="user_id" value="<?php echo $admin['UserID']; ?>">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                        <button type="submit"
                                                               class="action-btn bg-yellow-500 hover:bg-yellow-600"
                                                               title="Deactivate Admin"
                                                               onclick="return confirm('Are you sure you want to deactivate this admin account?');">
                                                           <i class="fas fa-user-times mr-1"></i> Deactivate
                                                        </button>
                                                   </form>
                                               <?php else: ?>
                                                    <form action="includes/handle_admin_user_action.php" method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="activate">
                                                        <input type="hidden" name="user_id" value="<?php echo $admin['UserID']; ?>">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                        <button type="submit"
                                                               class="action-btn bg-green-500 hover:bg-green-600"
                                                               title="Activate Admin"
                                                               onclick="return confirm('Are you sure you want to activate this admin account?');">
                                                           <i class="fas fa-user-check mr-1"></i> Activate
                                                        </button>
                                                   </form>
                                               <?php endif; ?>
                                                <?php else: ?>
                                               <span class="text-gray-400 italic text-sm">(Current User)</span>
                                           <?php endif; ?>
                                       </td>
                                   </tr>
                               <?php endforeach; ?>
                           <?php else: ?>
                               <tr>
                                   <td colspan="7" class="text-center text-gray-500 py-4">No administrator accounts found.</td>
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
        if (window.innerWidth < 768) { sidebar.classList.add('-translate-x-full'); }
    </script>
</body>
</html>
