<?php
session_start();

// --- Get Token from URL ---
$token = isset($_GET['token']) ? $_GET['token'] : '';
$error_message = null;
$token_valid = false;

if (empty($token)) {
    $error_message = "Invalid or missing password reset token.";
} else {
    // **Optional but Recommended: Basic Token Format Check**
    // Example: Check if it looks like a hex token of expected length
    if (!ctype_xdigit($token) || strlen($token) !== 64) { // 64 hex chars for 32 bytes
         $error_message = "Invalid token format.";
    } else {
        // Token format seems okay, proceed to check database in handler
        $token_valid = true;
    }
}

// If token is clearly invalid from the start, don't show the form
if (!$token_valid && $error_message === null) {
     $error_message = "Invalid password reset link.";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password - Online Voting System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style>
        body { font-family: 'Inter', sans-serif; }
        .validation-error { color: red; font-size: 0.875rem; margin-top: 0.25rem; }
    </style>
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
            <h2 class="text-3xl font-bold text-center text-indigo-700 mb-6">Set New Password</h2>

            <?php
            // Display error messages (e.g., if token invalid, or from handler)
            if ($error_message) {
                echo "<div class='border border-red-400 bg-red-100 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>";
                echo "<span class='block sm:inline'>" . htmlspecialchars($error_message) . "</span>";
                echo "</div>";
            }
            if (isset($_SESSION['reset_password_message'])) {
                 $message = $_SESSION['reset_password_message'];
                 $status = $_SESSION['reset_password_status'] ?? 'error';
                 $bgColor = ($status === 'success') ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700';
                 echo "<div class='border px-4 py-3 rounded relative mb-4 {$bgColor}' role='alert'>";
                 echo "<span class='block sm:inline'>" . htmlspecialchars($message) . "</span>";
                 echo "</div>";
                 unset($_SESSION['reset_password_message']);
                 unset($_SESSION['reset_password_status']);
            }
            ?>

            <?php if ($token_valid): // Only show form if token format is potentially valid ?>
            <form id="resetPasswordForm" action="includes/handle_reset_password.php" method="POST" onsubmit="return validateResetForm()">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                <div class="mb-4">
                    <label for="new_password" class="block text-gray-700 text-sm font-bold mb-2">New Password:</label>
                    <input type="password" id="new_password" name="new_password" required
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                           placeholder="Enter your new password">
                     <div id="passwordError" class="validation-error"></div>
                </div>

                <div class="mb-6">
                    <label for="confirm_new_password" class="block text-gray-700 text-sm font-bold mb-2">Confirm New Password:</label>
                    <input type="password" id="confirm_new_password" name="confirm_new_password" required
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                           placeholder="Confirm your new password">
                    <div id="confirmPasswordError" class="validation-error"></div>
                </div>

                <div class="flex items-center justify-between">
                    <button type="submit"
                            class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-4 rounded-lg focus:outline-none focus:shadow-outline transition duration-300 shadow-md text-lg">
                        <i class="fas fa-key mr-2"></i>Reset Password
                    </button>
                </div>
            </form>
            <?php else: ?>
                 <p class="text-center text-gray-600">
                     If you are having trouble, please request a new password reset link.
                     <a href="forgot_password.php" class="font-bold text-indigo-600 hover:text-indigo-800">Request Reset</a>
                 </p>
            <?php endif; ?>

             <p class="text-center text-gray-600 text-sm mt-6">
                Remembered your password?
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

    <?php if ($token_valid): ?>
    <script>
        // Client-side validation for password matching and length
        function validateResetForm() {
            const passwordInput = document.getElementById('new_password');
            const confirmInput = document.getElementById('confirm_new_password');
            const passwordError = document.getElementById('passwordError');
            const confirmError = document.getElementById('confirmPasswordError');
            let isValid = true;

            passwordError.textContent = '';
            confirmError.textContent = '';

            const password = passwordInput.value;
            const confirmPassword = confirmInput.value;

            if (password === '') {
                passwordError.textContent = 'New Password is required.';
                isValid = false;
            } else if (password.length < 6) { // Match length check from registration
                 passwordError.textContent = 'Password must be at least 6 characters long.';
                 isValid = false;
            }

            if (confirmPassword === '') {
                 confirmError.textContent = 'Please confirm your new password.';
                 isValid = false;
            } else if (password !== confirmPassword) {
                confirmError.textContent = 'Passwords do not match.';
                isValid = false;
            }

            return isValid;
        }
    </script>
    <?php endif; ?>

</body>
</html>
