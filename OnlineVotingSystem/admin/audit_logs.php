<?php
// admin/audit_logs.php
session_start();
require_once 'includes/db_connect.php'; // Ensure this path is correct

// --- Authentication & Authorization ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
    $_SESSION['login_message'] = "Access denied. Please log in as an administrator.";
    header("Location: login.php");
    exit();
}
$admin_username = $_SESSION['username']; // For display

// --- Pagination Setup ---
$results_per_page = 25; // Number of logs per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page); // Ensure page is at least 1
$offset = ($page - 1) * $results_per_page;

// --- Fetch Total Number of Logs (for pagination) ---
$total_logs_sql = "SELECT COUNT(*) FROM AuditLog";
$total_result = $conn->query($total_logs_sql);
$total_logs = $total_result ? $total_result->fetch_row()[0] : 0;
$total_pages = ceil($total_logs / $results_per_page);

// --- Fetch Audit Log Data with Pagination ---
$audit_logs = [];
$error_message = null;

// SQL to fetch logs, joining with Users table to get username
$sql = "SELECT
            al.LogID,
            al.Timestamp,
            al.UserID,
            u.Username, -- Get username from Users table
            al.ActionType,
            al.Details,
            al.IPAddress
        FROM AuditLog al
        LEFT JOIN Users u ON al.UserID = u.UserID -- LEFT JOIN in case UserID is NULL or user deleted
        ORDER BY al.Timestamp DESC -- Show newest logs first
        LIMIT ? OFFSET ?"; // Add LIMIT and OFFSET for pagination

$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("ii", $results_per_page, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $audit_logs[] = $row;
        }
    } else {
        $error_message = "Error fetching audit logs: " . $conn->error;
        error_log($error_message);
    }
    $stmt->close();
} else {
     $error_message = "Database error preparing statement for audit logs: " . $conn->error;
     error_log($error_message);
}

$conn->close(); // Close the connection

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style>
        body { font-family: 'Inter', sans-serif; }
        /* Sidebar, responsive, table styles (copy from other admin pages) */
        .sidebar { width: 250px; transition: transform 0.3s ease-in-out; }
        .content { margin-left: 250px; transition: margin-left 0.3s ease-in-out; }
        @media (max-width: 768px) { .sidebar { transform: translateX(-100%); } .sidebar.open { transform: translateX(0); z-index: 40; } .content { margin-left: 0; } .sidebar-toggle { display: block; } .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); z-index: 30; } .sidebar.open + .overlay { display: block; } }
        @media (min-width: 769px) { .sidebar-toggle-close, .sidebar-toggle-open { display: none; } }
        th, td { padding: 0.5rem 0.75rem; /* Slightly smaller padding */ text-align: left; border-bottom: 1px solid #e2e8f0; vertical-align: middle; font-size: 0.875rem; /* text-sm */ }
        th { background-color: #f8fafc; font-weight: 600; color: #4b5563; }
        tbody tr:hover { background-color: #f9fafb; }
        .details-cell { max-width: 300px; /* Limit width of details */ white-space: normal; /* Allow wrapping */ word-break: break-word; }
        /* Pagination styles */
        .pagination a, .pagination span { display: inline-block; padding: 0.5rem 0.75rem; margin: 0 0.125rem; border: 1px solid #d1d5db; border-radius: 0.25rem; text-decoration: none; color: #374151; }
        .pagination a:hover { background-color: #f3f4f6; }
        .pagination .active { background-color: #4f46e5; /* indigo-600 */ color: white; border-color: #4f46e5; }
        .pagination .disabled { color: #9ca3af; background-color: #f9fafb; cursor: not-allowed; }
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
                <a href="user_management.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700"><i class="fas fa-user-cog mr-2 w-5 text-center"></i>Admin Users</a>
                <a href="audit_logs.php" class="block py-2.5 px-4 rounded transition duration-200 bg-indigo-600 font-semibold"><i class="fas fa-history mr-2 w-5 text-center"></i>Audit Logs</a>
                <a href="settings.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700"><i class="fas fa-sliders-h mr-2 w-5 text-center"></i>Settings</a>
                <a href="includes/logout.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-red-600 mt-8 text-red-300 hover:text-white"><i class="fas fa-sign-out-alt mr-2 w-5 text-center"></i>Logout</a>
            </nav>
        </aside>

        <div class="content flex-1 p-6 md:p-10">
            <header class="bg-white shadow rounded-lg p-4 mb-6 flex justify-between items-center">
                <button class="sidebar-toggle-open md:hidden text-gray-600 focus:outline-none"><i class="fas fa-bars text-xl"></i></button>
                <h1 class="text-xl md:text-2xl font-semibold text-gray-700">System Audit Logs</h1>
                <div>
                    <span class="text-gray-600 text-sm md:text-base">Welcome, <?php echo htmlspecialchars($admin_username); ?>!</span>
                </div>
            </header>

            <?php if ($error_message): ?>
                <div class='border px-4 py-3 rounded relative mb-4 bg-red-100 border-red-400 text-red-700' role='alert'>
                    <span class='block sm:inline'><?php echo htmlspecialchars($error_message); ?></span>
                </div>
            <?php endif; ?>

            <div class="bg-white p-6 rounded-lg shadow border border-gray-200">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Activity Log</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white">
                        <thead>
                            <tr>
                                <th>Log ID</th>
                                <th>Timestamp</th>
                                <th>User</th>
                                <th>Action Type</th>
                                <th>Details</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($audit_logs)): ?>
                                <?php foreach ($audit_logs as $log): ?>
                                    <tr>
                                        <td><?php echo $log['LogID']; ?></td>
                                        <td><?php echo date('Y-m-d H:i:s', strtotime($log['Timestamp'])); ?></td>
                                        <td>
                                            <?php echo $log['UserID'] ? htmlspecialchars($log['Username'] ?? 'ID: ' . $log['UserID']) : '<span class="italic text-gray-500">System</span>'; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($log['ActionType']); ?></td>
                                        <td class="details-cell"><?php echo nl2br(htmlspecialchars($log['Details'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars($log['IPAddress'] ?? 'N/A'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-gray-500 py-4">No audit logs found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 1): ?>
                    <div class="pagination mt-6 flex justify-center">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>">&laquo; Prev</a>
                        <?php else: ?>
                            <span class="disabled">&laquo; Prev</span>
                        <?php endif; ?>

                        <?php
                        // Simple pagination links (can be made more sophisticated)
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);

                        if ($start_page > 1) echo '<a href="?page=1">1</a>';
                        if ($start_page > 2) echo '<span>...</span>';

                        for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <a href="?page=<?php echo $i; ?>" class="<?php echo ($i == $page) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>

                        if ($end_page < $total_pages - 1) echo '<span>...</span>';
                        if ($end_page < $total_pages) echo '<a href="?page='.$total_pages.'">'.$total_pages.'</a>';

                        ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>">Next &raquo;</a>
                        <?php else: ?>
                            <span class="disabled">Next &raquo;</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

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
