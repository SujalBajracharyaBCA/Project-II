<?php
// admin/settings.php
session_start();
require_once 'includes/db_connect.php'; // Ensure this path is correct

// --- Authentication & Authorization ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
    $_SESSION['login_message'] = "Access denied. Please log in as an administrator.";
    header("Location: login.php");
    exit();
}
$admin_username = $_SESSION['username']; // For display

// --- Placeholder for Loading Settings ---
// In a real implementation, load settings from a database table (e.g., `Settings`)
// or a configuration file.
$settings = [
    'site_name' => 'Online Voting System',
    'default_results_visibility' => 'private', // Example: 'public', 'private', 'voters_only'
    'allow_registration' => true,
    // Add more settings as needed
];
$error_message = null;
$success_message = null;

// --- Placeholder for Handling Form Submission ---
// This would typically be in a separate handler file (e.g., includes/handle_settings.php)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // --- CSRF Token Validation ---
    // if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    //     $error_message = "Invalid request (CSRF token mismatch).";
    // } else {
        // Process and save settings here...
        // Example:
        // $settings['site_name'] = trim($_POST['site_name'] ?? $settings['site_name']);
        // $settings['allow_registration'] = isset($_POST['allow_registration']);
        // ... save $settings to DB or file ...

        $success_message = "Settings updated successfully (Placeholder - Saving not implemented).";
        // Regenerate CSRF token after successful POST
        // $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    // }
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
    <title>System Settings - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style>
        body { font-family: 'Inter', sans-serif; }
        /* Sidebar, responsive, table styles (copy from other admin pages) */
        .sidebar { width: 250px; transition: transform 0.3s ease-in-out; }
        .content { margin-left: 250px; transition: margin-left 0.3s ease-in-out; }
        @media (max-width: 768px) { .sidebar { transform: translateX(-100%); } .sidebar.open { transform: translateX(0); z-index: 40; } .content { margin-left: 0; } .sidebar-toggle { display: block; } .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); z-index: 30; } .sidebar.open + .overlay { display: block; } }
        @media (min-width: 769px) { .sidebar-toggle-close, .sidebar-toggle-open { display: none; } }
        /* Form styles */
        label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #374151; }
        input[type="text"], select {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            box-shadow: inset 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }
        input[type="checkbox"] {
            height: 1rem; width: 1rem; margin-right: 0.5rem; border-radius: 0.25rem; border-color: #d1d5db;
        }
        button[type="submit"] {
            padding: 0.6rem 1.2rem;
            background-color: #10b981; /* green-600 */
            color: white;
            font-weight: 600;
            border-radius: 0.375rem;
            border: none;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        button[type="submit"]:hover { background-color: #059669; /* green-700 */ }
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
                <a href="audit_logs.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700"><i class="fas fa-history mr-2 w-5 text-center"></i>Audit Logs</a>
                <a href="settings.php" class="block py-2.5 px-4 rounded transition duration-200 bg-indigo-600 font-semibold"><i class="fas fa-sliders-h mr-2 w-5 text-center"></i>Settings</a>
                <a href="includes/logout.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-red-600 mt-8 text-red-300 hover:text-white"><i class="fas fa-sign-out-alt mr-2 w-5 text-center"></i>Logout</a>
            </nav>
        </aside>

        <div class="content flex-1 p-6 md:p-10">
            <header class="bg-white shadow rounded-lg p-4 mb-6 flex justify-between items-center">
                <button class="sidebar-toggle-open md:hidden text-gray-600 focus:outline-none"><i class="fas fa-bars text-xl"></i></button>
                <h1 class="text-xl md:text-2xl font-semibold text-gray-700">System Settings</h1>
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

            <div class="bg-white p-6 rounded-lg shadow border border-gray-200 max-w-2xl">
                <h2 class="text-xl font-semibold text-gray-800 mb-6">Configure System Parameters</h2>

                <form action="settings.php" method="POST"> <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                    <div class="mb-4">
                        <label for="site_name">Site Name:</label>
                        <input type="text" id="site_name" name="site_name" value="<?php echo htmlspecialchars($settings['site_name']); ?>">
                    </div>

                    <div class="mb-4">
                        <label for="default_results_visibility">Default Results Visibility:</label>
                        <select id="default_results_visibility" name="default_results_visibility">
                            <option value="private" <?php echo ($settings['default_results_visibility'] == 'private') ? 'selected' : ''; ?>>Private (Admin Only)</option>
                            <option value="voters_only" <?php echo ($settings['default_results_visibility'] == 'voters_only') ? 'selected' : ''; ?>>Voters Only</option>
                            <option value="public" <?php echo ($settings['default_results_visibility'] == 'public') ? 'selected' : ''; ?>>Public</option>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Controls who can see results by default after an election closes.</p>
                    </div>

                    <div class="mb-6">
                        <label for="allow_registration" class="inline-flex items-center">
                            <input type="checkbox" id="allow_registration" name="allow_registration" value="1" <?php echo ($settings['allow_registration']) ? 'checked' : ''; ?>>
                            <span class="ml-2">Allow New Voter Registration</span>
                        </label>
                        <p class="text-xs text-gray-500 mt-1">Enable or disable the public voter registration page.</p>
                    </div>

                    <hr class="my-6">

                    <div>
                        <button type="submit"><i class="fas fa-save mr-2"></i>Save Settings</button>
                        <span class="ml-4 text-sm text-gray-500 italic">(Saving is currently disabled in this placeholder)</span>
                    </div>
                </form>
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
