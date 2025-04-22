<?php
// Start session to handle potential messages (e.g., registration success/error)
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voter Registration - Online Voting System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" /> <style>
        /* Custom styles if needed */
        body {
            font-family: 'Inter', sans-serif; /* Using Inter font */
        }
        /* Style for validation messages */
        .validation-error {
            color: red;
            font-size: 0.875rem; /* text-sm */
            margin-top: 0.25rem; /* mt-1 */
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
                <a href="login.php" class="px-4 py-2 rounded hover:bg-indigo-800 transition duration-300">Login</a>
                <a href="register.php" class="px-4 py-2 rounded hover:bg-indigo-800 transition duration-300 font-semibold">Register</a>
                <a href="admin/login.php" class="px-4 py-2 rounded hover:bg-indigo-800 transition duration-300">Admin</a>
            </nav>
        </div>
    </header>

    <main class="container mx-auto px-6 py-12 flex justify-center">
        <div class="w-full max-w-lg bg-white p-8 rounded-lg shadow-lg border border-gray-200">
            <h2 class="text-3xl font-bold text-center text-indigo-700 mb-6">Voter Registration</h2>

            <?php
            // --- Display Registration Messages ---
            if (isset($_SESSION['registration_message'])) {
                $message = $_SESSION['registration_message'];
                $status = $_SESSION['registration_status'] ?? 'error'; // Default to error
                $bgColor = ($status === 'success') ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700';
                echo "<div class='border px-4 py-3 rounded relative mb-4 {$bgColor}' role='alert'>";
                echo "<span class='block sm:inline'>" . htmlspecialchars($message) . "</span>";
                echo "</div>";
                // Clear the message after displaying
                unset($_SESSION['registration_message']);
                unset($_SESSION['registration_status']);
            }
            // --- End Display Messages ---
            ?>

            <form id="registrationForm" action="includes/handle_register.php" method="POST" onsubmit="return validateForm()">
                <div class="mb-4">
                    <label for="fullname" class="block text-gray-700 text-sm font-bold mb-2">Full Name:</label>
                    <input type="text" id="fullname" name="fullname" required
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                           placeholder="Enter your full name">
                    <div id="fullnameError" class="validation-error"></div>
                </div>

                <div class="mb-4">
                    <label for="username" class="block text-gray-700 text-sm font-bold mb-2">Username:</label>
                    <input type="text" id="username" name="username" required
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                           placeholder="Choose a username">
                     <div id="usernameError" class="validation-error"></div>
                </div>

                <div class="mb-4">
                    <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Email Address:</label>
                    <input type="email" id="email" name="email" required
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                           placeholder="Enter your email">
                    <div id="emailError" class="validation-error"></div>
                </div>

                <div class="mb-4">
                    <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Password:</label>
                    <input type="password" id="password" name="password" required
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                           placeholder="Choose a password">
                    <div id="passwordError" class="validation-error"></div>
                </div>

                <div class="mb-6">
                    <label for="confirm_password" class="block text-gray-700 text-sm font-bold mb-2">Confirm Password:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                           placeholder="Confirm your password">
                    <div id="confirmPasswordError" class="validation-error"></div>
                </div>

                 <div class="flex items-center justify-between">
                    <button type="submit"
                            class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-4 rounded-lg focus:outline-none focus:shadow-outline transition duration-300 shadow-md text-lg">
                        <i class="fas fa-user-plus mr-2"></i>Register
                    </button>
                </div>
            </form>

            <p class="text-center text-gray-600 text-sm mt-6">
                Already have an account?
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

    <script>
        function validateForm() {
            // Clear previous errors
            document.getElementById('fullnameError').textContent = '';
            document.getElementById('usernameError').textContent = '';
            document.getElementById('emailError').textContent = '';
            document.getElementById('passwordError').textContent = '';
            document.getElementById('confirmPasswordError').textContent = '';

            let isValid = true;

            // Get form values
            const fullname = document.getElementById('fullname').value.trim();
            const username = document.getElementById('username').value.trim();
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            // Basic Required Field Validation
            if (fullname === '') {
                document.getElementById('fullnameError').textContent = 'Full Name is required.';
                isValid = false;
            }
            if (username === '') {
                document.getElementById('usernameError').textContent = 'Username is required.';
                isValid = false;
            }
            if (email === '') {
                document.getElementById('emailError').textContent = 'Email is required.';
                isValid = false;
            } else {
                 // Basic Email Format Check
                 const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                 if (!emailPattern.test(email)) {
                     document.getElementById('emailError').textContent = 'Please enter a valid email address.';
                     isValid = false;
                 }
            }
            if (password === '') {
                document.getElementById('passwordError').textContent = 'Password is required.';
                isValid = false;
            }
             if (confirmPassword === '') {
                document.getElementById('confirmPasswordError').textContent = 'Please confirm your password.';
                isValid = false;
            }

            // Password Match Validation
            if (password !== '' && confirmPassword !== '' && password !== confirmPassword) {
                document.getElementById('confirmPasswordError').textContent = 'Passwords do not match.';
                isValid = false;
            }

            // Optional: Add more complex password strength validation if needed

            return isValid; // Prevent form submission if validation fails
        }
    </script>

</body>
</html>
