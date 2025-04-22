<?php
// Start session to handle potential messages (e.g., login errors)
session_start();

// If user is already logged in, redirect them to the dashboard
// (We'll implement the dashboard and this check later)
/*
if (isset($_SESSION['user_id']) && $_SESSION['user_role'] === 'Voter') {
    header("Location: dashboard.php");
    exit();
}
*/
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voter Login - Online Voting System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" /> <style>
        /* Custom styles if needed */
        body {
            font-family: 'Inter', sans-serif; /* Using Inter font */
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-100 text-gray-800">

    <header class="bg-gradient-to-r from-blue-600 to-indigo-700 text-white shadow-md">
        <div class="container mx-auto px-6 py-4 flex justify-between items-center">
            <h1 class="text-2xl font-bold">
                <i class="fas fa-vote-yea mr-2"></i>Online Voting System
            </h1>
            <nav>
                <a href="index.php" class="px-4 py-2 rounded hover:bg-indigo-800 transition duration-300">Home</a>
                <a href="login.php" class="px-4 py-2 rounded hover:bg-indigo-800 transition duration-300 font-semibold">Login</a>
                <a href="register.php" class="px-4 py-2 rounded hover:bg-indigo-800 transition duration-300">Register</a>
                <a href="admin/login.php" class="px-4 py-2 rounded hover:bg-indigo-800 transition duration-300">Admin</a>
            </nav>
        </div>
    </header>

    <main class="container mx-auto px-6 py-12 flex justify-center">
        <div class="w-full max-w-md bg-white p-8 rounded-lg shadow-lg border border-gray-200">
            <h2 class="text-3xl font-bold text-center text-indigo-700 mb-6">Voter Login</h2>

            <?php
            // --- Display Login Messages ---
            if (isset($_SESSION['login_message'])) {
                $message = $_SESSION['login_message'];
                // Login messages are usually errors
                echo "<div class='border border-red-400 bg-red-100 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>";
                echo "<span class='block sm:inline'>" . htmlspecialchars($message) . "</span>";
                echo "</div>";
                // Clear the message after displaying
                unset($_SESSION['login_message']);
            }
             // Display message if redirected after logout
             if (isset($_GET['logout']) && $_GET['logout'] == 'success') {
                 echo "<div class='border border-green-400 bg-green-100 text-green-700 px-4 py-3 rounded relative mb-4' role='alert'>";
                 echo "<span class='block sm:inline'>You have been successfully logged out.</span>";
                 echo "</div>";
             }
             // Display message if registration was successful
             if (isset($_SESSION['registration_status']) && $_SESSION['registration_status'] === 'success') {
                 echo "<div class='border border-green-400 bg-green-100 text-green-700 px-4 py-3 rounded relative mb-4' role='alert'>";
                 echo "<span class='block sm:inline'>" . htmlspecialchars($_SESSION['registration_message']) . "</span>";
                 echo "</div>";
                 unset($_SESSION['registration_message']);
                 unset($_SESSION['registration_status']);
             }
            // --- End Display Messages ---
            ?>

            <form id="loginForm" action="includes/handle_login.php" method="POST">
                <div class="mb-4">
                    <label for="username" class="block text-gray-700 text-sm font-bold mb-2">Username:</label>
                    <input type="text" id="username" name="username" required
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                           placeholder="Enter your username">
                </div>

                <div class="mb-6">
                    <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Password:</label>
                    <input type="password" id="password" name="password" required
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 mb-3 leading-tight focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                           placeholder="Enter your password">
                    <a href="forgot_password.php" class="inline-block align-baseline text-sm text-indigo-600 hover:text-indigo-800">
                         Forgot Password?
                     </a>
                </div>

                <div class="flex items-center justify-between">
                    <button type="submit"
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg focus:outline-none focus:shadow-outline transition duration-300 shadow-md text-lg">
                        <i class="fas fa-sign-in-alt mr-2"></i>Login
                    </button>
                </div>
            </form>

            <p class="text-center text-gray-600 text-sm mt-6">
                Don't have an account?
                <a href="register.php" class="font-bold text-green-600 hover:text-green-800">
                    Register here
                </a>
            </p>
        </div>
    </main>

    <footer class="bg-gray-800 text-gray-300 py-6 mt-12">
        <div class="container mx-auto px-6 text-center">
             <p>&copy; <?php echo date("Y"); ?> Online Voting System. Developed by Khagendra Malla & Sujal Bajracharya.</p>
             <div class="mt-2">
                 <a href="terms.php" class="text-gray-400 hover:text-white px-2">Terms & Conditions</a> |
                 <a href="privacy.php" class="text-gray-400 hover:text-white px-2">Privacy Policy</a> |
                 <a href="faq.php" class="text-gray-400 hover:text-white px-2">FAQ</a>
             </div>
        </div>
    </footer>

</body>
</html>
