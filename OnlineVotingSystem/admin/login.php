<?php
// admin/login.php
// Start session to handle potential messages (e.g., login errors)
session_start();

// If an admin is already logged in, redirect them to the admin dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Admin') {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Online Voting System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-200 text-gray-800">

    <div class="min-h-screen flex items-center justify-center">
        <div class="w-full max-w-md bg-white p-8 rounded-lg shadow-xl border border-gray-300">
            <h2 class="text-3xl font-bold text-center text-indigo-700 mb-6">
                <i class="fas fa-user-shield mr-2"></i>Admin Login
            </h2>

            <?php
            // --- Display Login Messages ---
            if (isset($_SESSION['login_message'])) {
                $message = $_SESSION['login_message'];
                // Determine message type (could add a $_SESSION['login_status'] later)
                $message_class = 'border-red-400 bg-red-100 text-red-700'; // Default to error
                 // Display message if redirected after logout
                 if (isset($_GET['logout']) && $_GET['logout'] == 'success') {
                     $message = 'You have been successfully logged out.';
                     $message_class = 'border-green-400 bg-green-100 text-green-700';
                 }

                echo "<div class='border {$message_class} px-4 py-3 rounded relative mb-4' role='alert'>";
                echo "<span class='block sm:inline'>" . htmlspecialchars($message) . "</span>";
                echo "</div>";
                // Clear the message after displaying
                unset($_SESSION['login_message']);
            }
            // --- End Display Messages ---
            ?>

            <form id="adminLoginForm" action="includes/handle_login.php" method="POST">
                <div class="mb-4">
                    <label for="username" class="block text-gray-700 text-sm font-bold mb-2">Admin Username:</label>
                    <div class="relative">
                         <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                             <i class="fas fa-user text-gray-400"></i>
                         </span>
                         <input type="text" id="username" name="username" required
                                class="shadow appearance-none border rounded w-full py-2 pl-10 pr-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                placeholder="Enter admin username">
                    </div>
                </div>

                <div class="mb-6">
                    <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Password:</label>
                     <div class="relative">
                         <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                             <i class="fas fa-lock text-gray-400"></i>
                         </span>
                        <input type="password" id="password" name="password" required
                               class="shadow appearance-none border rounded w-full py-2 pl-10 pr-3 text-gray-700 mb-3 leading-tight focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                               placeholder="Enter password">
                     </div>
                    </div>

                <div class="flex items-center justify-between">
                    <button type="submit"
                            class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-4 rounded-lg focus:outline-none focus:shadow-outline transition duration-300 shadow-md text-lg">
                        <i class="fas fa-sign-in-alt mr-2"></i>Login
                    </button>
                </div>
            </form>

            <p class="text-center text-gray-600 text-sm mt-6">
                <a href="../index.php" class="text-indigo-600 hover:text-indigo-800">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Main Site
                </a>
            </p>
        </div>
    </div>

    <footer class="text-center text-gray-500 text-xs py-4 absolute bottom-0 w-full">
         &copy; <?php echo date("Y"); ?> Online Voting System. Admin Panel.
    </footer>

</body>
</html>
