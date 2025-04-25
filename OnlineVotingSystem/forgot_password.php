<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Online Voting System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style> body { font-family: 'Inter', sans-serif; } </style>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-100 text-gray-800">

    <header class="bg-gradient-to-r from-blue-600 to-indigo-700 text-white shadow-md">
        <div class="container mx-auto px-6 py-4 flex justify-between items-center">
            <h1 class="text-2xl font-bold">
                 <a href="index.php"><i class="fas fa-vote-yea mr-2"></i>Online Voting System</a>
            </h1>
            <nav>
                <a href="index.php" class="px-4 py-2 rounded hover:bg-indigo-800 transition duration-300">Home</a>
                <a href="login.php" class="px-4 py-2 rounded hover:bg-indigo-800 transition duration-300">Login</a>
                <a href="register.php" class="px-4 py-2 rounded hover:bg-indigo-800 transition duration-300">Register</a>
            </nav>
        </div>
    </header>

    <main class="container mx-auto px-6 py-12 flex justify-center">
        <div class="w-full max-w-md bg-white p-8 rounded-lg shadow-lg border border-gray-200">
            <h2 class="text-3xl font-bold text-center text-indigo-700 mb-6">Reset Password</h2>
            <p class="text-center text-gray-600 mb-6">Enter the email address associated with your account, and we'll send instructions to reset your password.</p>

            <?php
            // Display messages from the handler script
            if (isset($_SESSION['forgot_password_message'])) {
                $message = $_SESSION['forgot_password_message'];
                $status = $_SESSION['forgot_password_status'] ?? 'error';
                $bgColor = ($status === 'success') ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700';
                echo "<div class='border px-4 py-3 rounded relative mb-4 {$bgColor}' role='alert'>";
                echo "<span class='block sm:inline'>" . htmlspecialchars($message) . "</span>";
                echo "</div>";
                unset($_SESSION['forgot_password_message']);
                unset($_SESSION['forgot_password_status']);
            }
            ?>

            <form id="forgotPasswordForm" action="includes/handle_forgot_password.php" method="POST">
                <div class="mb-6">
                    <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Email Address:</label>
                    <div class="relative">
                         <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                             <i class="fas fa-envelope text-gray-400"></i>
                         </span>
                        <input type="email" id="email" name="email" required
                               class="shadow appearance-none border rounded w-full py-2 pl-10 pr-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                               placeholder="Enter your registered email">
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <button type="submit"
                            class="w-full bg-orange-500 hover:bg-orange-600 text-white font-bold py-3 px-4 rounded-lg focus:outline-none focus:shadow-outline transition duration-300 shadow-md text-lg">
                        <i class="fas fa-paper-plane mr-2"></i>Send Reset Instructions
                    </button>
                </div>
            </form>

            <p class="text-center text-gray-600 text-sm mt-6">
                Remember your password?
                <a href="login.php" class="font-bold text-indigo-600 hover:text-indigo-800">
                    Login here
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
