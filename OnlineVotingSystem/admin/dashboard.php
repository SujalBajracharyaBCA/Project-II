<?php
    // admin/dashboard.php
    session_start();

    // --- Authentication and Authorization Check ---
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
        $_SESSION['login_message'] = "You must be logged in as an administrator to access this page.";
        header("Location: login.php");
        exit();
    }

    // Include the database connection file
    require_once 'includes/db_connect.php'; // Make sure this path is correct

    // Get admin information from session
    $admin_id = $_SESSION['user_id'];
    $admin_username = $_SESSION['username'];

    // --- Fetch Dashboard Data ---
    $active_elections_count = 0; // Default to 0
    $registered_voters_count = 0; // Default to 0
    $upcoming_elections_count = 0; // Default to 0

    // 1. Count Active Elections
    // **FIX:** Use MySQL's NOW() function for reliable time comparison based on DB server time.
    $sql_active = "SELECT COUNT(*) as count FROM Elections WHERE Status = 'Active' AND StartDate <= NOW() AND EndDate >= NOW()";
    $result_active = $conn->query($sql_active); // Use query() directly as there's no user input

    if ($result_active) {
        if ($row_active = $result_active->fetch_assoc()) {
            $active_elections_count = $row_active['count'];
        }
        $result_active->free(); // Free the result set
    } else {
         error_log("Error fetching active elections count: " . $conn->error); // Log error
         $active_elections_count = 'Error'; // Indicate an error occurred
    }


    // 2. Count Registered Voters
    $sql_voters = "SELECT COUNT(*) as count FROM Users WHERE Role = 'Voter'";
    $result_voters = $conn->query($sql_voters); // Simple query as no user input
    if ($result_voters) {
        if ($row_voters = $result_voters->fetch_assoc()) {
            $registered_voters_count = $row_voters['count'];
        }
        $result_voters->free();
    } else {
         error_log("Error fetching registered voters count: " . $conn->error); // Log error
         $registered_voters_count = 'Error';
    }


    // 3. Count Upcoming elections
    // **FIX:** Use MySQL's NOW() function here too for consistency.
    $sql_upcoming = "SELECT COUNT(*) as count FROM Elections WHERE Status = 'Pending' AND StartDate > NOW()";
    $result_upcoming = $conn->query($sql_upcoming); // Use query() directly

    if ($result_upcoming) {
        if ($row_upcoming = $result_upcoming->fetch_assoc()) {
            $upcoming_elections_count = $row_upcoming['count'];
        }
        $result_upcoming->free();
    } else {
         error_log("Error fetching upcoming elections count: " . $conn->error); // Log error
         $upcoming_elections_count = 'Error';
    }


    // Close the connection
    $conn->close();

    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Dashboard - Online Voting System</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
        <style>
            body { font-family: 'Inter', sans-serif; }
            /* Sidebar and responsive styles (same as before) */
            .sidebar { width: 250px; transition: transform 0.3s ease-in-out; }
            .content { margin-left: 250px; transition: margin-left 0.3s ease-in-out; }
            .sidebar-collapsed { transform: translateX(-100%); }
            .content-expanded { margin-left: 0; }

             @media (max-width: 768px) {
                .sidebar { transform: translateX(-100%); /* Hidden by default on mobile */ }
                .sidebar.open { transform: translateX(0); z-index: 40; /* Ensure it's above content */ }
                .content { margin-left: 0; }
                .sidebar-toggle { display: block; } /* Show toggle on small screens */
                .overlay { /* Optional overlay for mobile */
                    display: none;
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background-color: rgba(0, 0, 0, 0.5);
                    z-index: 30;
                }
                .sidebar.open + .overlay { display: block; }
            }
            @media (min-width: 769px) {
                 .sidebar-toggle-close { display: none; } /* Hide close button on larger screens */
                 .sidebar-toggle-open { display: none; } /* Hide open button on larger screens */
            }
        </style>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    </head>
    <body class="bg-gray-100">
        <div class="overlay md:hidden"></div>

        <div class="flex min-h-screen">
            <aside class="sidebar bg-gradient-to-b from-gray-800 to-gray-900 text-white p-6 fixed h-full shadow-lg md:translate-x-0 z-40">
                <div class="flex justify-between items-center mb-8">
                    <h2 class="text-2xl font-bold">
                        <i class="fas fa-cogs mr-2"></i>Admin Panel
                    </h2>
                     <button class="sidebar-toggle-close md:hidden text-white focus:outline-none">
                        <i class="fas fa-times text-xl"></i>
                     </button>
                </div>

                <nav>
                    <a href="dashboard.php" class="block py-2.5 px-4 rounded transition duration-200 bg-indigo-600 font-semibold">
                        <i class="fas fa-tachometer-alt mr-2 w-5 text-center"></i>Dashboard
                    </a>
                    <a href="election_management.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700">
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
                     <button class="sidebar-toggle-open md:hidden text-gray-600 focus:outline-none">
                         <i class="fas fa-bars text-xl"></i>
                     </button>
                     <h1 class="text-xl md:text-2xl font-semibold text-gray-700">Admin Dashboard</h1>
                     <div>
                         <span class="text-gray-600 text-sm md:text-base">Welcome, <?php echo htmlspecialchars($admin_username); ?>!</span>
                     </div>
                 </header>

                <div class="bg-white p-6 rounded-lg shadow border border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">System Overview</h2>
                    <p class="text-gray-600 mb-6">
                        Quick summary of the voting system status. Use the sidebar to manage different sections.
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                         <div class="bg-blue-100 border border-blue-300 p-4 rounded-lg shadow flex items-center space-x-3">
                             <i class="fas fa-vote-yea text-3xl text-blue-600"></i>
                             <div>
                                <h3 class="font-semibold text-blue-800">Active elections</h3>
                                <p class="text-3xl font-bold text-blue-700"><?php echo htmlspecialchars($active_elections_count); ?></p>
                             </div>
                         </div>
                         <div class="bg-green-100 border border-green-300 p-4 rounded-lg shadow flex items-center space-x-3">
                             <i class="fas fa-users text-3xl text-green-600"></i>
                             <div>
                                <h3 class="font-semibold text-green-800">Registered Voters</h3>
                                <p class="text-3xl font-bold text-green-700"><?php echo htmlspecialchars($registered_voters_count); ?></p>
                             </div>
                         </div>
                         <div class="bg-yellow-100 border border-yellow-300 p-4 rounded-lg shadow flex items-center space-x-3">
                              <i class="fas fa-calendar-alt text-3xl text-yellow-600"></i>
                             <div>
                                <h3 class="font-semibold text-yellow-800">Upcoming elections</h3>
                                <p class="text-3xl font-bold text-yellow-700"><?php echo htmlspecialchars($upcoming_elections_count); ?></p>
                             </div>
                         </div>
                     </div>
                </div>

                <div class="mt-6 bg-white p-6 rounded-lg shadow border border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Recent Activity / Quick Links</h2>
                    <p class="text-gray-500"> (Content to be added later - e.g., recent logins, links to create election, etc.) </p>
                </div>


                <footer class="text-center text-gray-500 text-sm mt-8">
                     &copy; <?php echo date("Y"); ?> Online Voting System. Admin Panel. Developed by Khagendra Malla & Sujal Bajracharya.
                 </footer>
            </div>
        </div>

        <script>
            const sidebar = document.querySelector('.sidebar');
            const content = document.querySelector('.content');
            const openButton = document.querySelector('.sidebar-toggle-open');
            const closeButton = document.querySelector('.sidebar-toggle-close');
            const overlay = document.querySelector('.overlay');

            function openSidebar() {
                sidebar.classList.add('open');
                sidebar.classList.remove('sidebar-collapsed');
                 sidebar.classList.remove('-translate-x-full');
                 if(overlay) overlay.style.display = 'block';
            }

            function closeSidebar() {
                sidebar.classList.remove('open');
                sidebar.classList.add('-translate-x-full');
                 if(overlay) overlay.style.display = 'none';
            }

            if (openButton) {
                openButton.addEventListener('click', openSidebar);
            }
            if (closeButton) {
                closeButton.addEventListener('click', closeSidebar);
            }
            if (overlay) {
                overlay.addEventListener('click', closeSidebar);
            }

             window.addEventListener('resize', () => {
                if (window.innerWidth >= 768 && sidebar.classList.contains('open')) {
                     closeSidebar();
                     sidebar.classList.remove('-translate-x-full');
                }
                 if (window.innerWidth < 768 && !sidebar.classList.contains('open')) {
                     sidebar.classList.add('-translate-x-full');
                 }
            });

             if (window.innerWidth < 768) {
                 sidebar.classList.add('-translate-x-full');
             }
        </script>

    </body>
    </html>
    