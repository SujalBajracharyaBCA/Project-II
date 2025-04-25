<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQ - Online Voting System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style>
        body { font-family: 'Inter', sans-serif; }
        details > summary { list-style: none; cursor: pointer; font-weight: 600; /* semibold */ padding: 0.75rem 0; /* py-3 */ border-bottom: 1px solid #e5e7eb; /* gray-200 */ }
        details > summary::-webkit-details-marker { display: none; } /* Hide default marker */
        details > summary::before { /* Custom marker */
            content: '\f078'; /* Font Awesome chevron-down */
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            margin-right: 0.75rem; /* mr-3 */
            transition: transform 0.2s ease-in-out;
            display: inline-block;
        }
        details[open] > summary::before {
            transform: rotate(180deg);
        }
        details div { padding: 1rem 0; /* py-4 */ border-bottom: 1px solid #e5e7eb; /* gray-200 */ color: #4b5563; /* gray-600 */ }
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
                 <?php if (isset($_SESSION['user_id'])): ?>
                     <span class="px-4 py-2 text-indigo-200">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</span>
                     <a href="<?php echo ($_SESSION['user_role'] === 'Admin') ? 'admin/dashboard.php' : 'dashboard.php'; ?>" class="px-4 py-2 rounded hover:bg-indigo-800 transition duration-300">Dashboard</a>
                     <a href="includes/logout.php" class="px-4 py-2 rounded bg-red-500 hover:bg-red-600 transition duration-300">Logout</a>
                 <?php else: ?>
                    <a href="index.php" class="px-4 py-2 rounded hover:bg-indigo-800 transition duration-300">Home</a>
                    <a href="login.php" class="px-4 py-2 rounded hover:bg-indigo-800 transition duration-300">Login</a>
                    <a href="register.php" class="px-4 py-2 rounded hover:bg-indigo-800 transition duration-300">Register</a>
                 <?php endif; ?>
                 <a href="faq.php" class="px-4 py-2 rounded hover:bg-indigo-800 transition duration-300 font-semibold">FAQ</a>
            </nav>
        </div>
    </header>

    <main class="container mx-auto px-6 py-12">
        <div class="bg-white p-8 rounded-lg shadow-lg border border-gray-200 max-w-4xl mx-auto">
            <h2 class="text-3xl font-bold text-center text-indigo-700 mb-8">Frequently Asked Questions (FAQ)</h2>

            <div class="space-y-2">
                <details>
                    <summary>How do I register to vote?</summary>
                    <div>
                        <p>Click on the "Register" link in the navigation bar or on the homepage. Fill out the required information, including your full name, username, email, and password. Ensure your password meets the minimum security requirements. After submission, you may need to verify your email address (if implemented).</p>
                    </div>
                </details>

                <details>
                    <summary>I forgot my password. What should I do?</summary>
                    <div>
                        <p>Click on the "Forgot Password?" link on the login page. Enter the email address associated with your account. If an account exists for that email, instructions on how to reset your password will be sent (or displayed for testing purposes in this demo).</p>
                    </div>
                </details>

                <details>
                    <summary>How do I cast my vote?</summary>
                    <div>
                        <p>First, log in to your account using your username and password. On your dashboard, you will see a list of elections you are eligible for. If an election is active and you haven't voted yet, click the "Cast Vote" button. Follow the instructions on the voting page, which will vary depending on the voting method (e.g., select one candidate, rank candidates, approve candidates). Review your selections carefully before clicking "Submit Vote".</p>
                    </div>
                </details>

                 <details>
                    <summary>Can I change my vote after submitting it?</summary>
                    <div>
                        <p>No, once your vote is submitted, it cannot be changed or recalled. Please review your choices carefully before confirming your submission.</p>
                    </div>
                </details>

                 <details>
                    <summary>Is my vote anonymous?</summary>
                    <div>
                        <p>The system is designed to separate your identity from your vote data to maintain anonymity during the tallying process. While the system records *that* you have voted (to prevent double voting), your specific choices are handled securely. Please refer to the Privacy Policy for more details.</p>
                        <p class="mt-2">*(Note: The exact level of anonymity depends on the specific implementation details, especially how vote data is stored and processed.)*</p>
                    </div>
                </details>

                 <details>
                    <summary>When can I see the election results?</summary>
                    <div>
                        <p>Election results are typically made available after the official voting period has ended and the election status is marked as "Closed". Depending on the election settings, results might be public, visible only to logged-in voters, or restricted to administrators. Check the Results page or your dashboard after the election end date.</p>
                    </div>
                </details>

                 <details>
                    <summary>Who developed this system?</summary>
                    <div>
                        <p>This Online Voting System was developed by Khagendra Malla & Sujal Bajracharya as part of their academic project.</p>
                    </div>
                </details>

                <details>
                    <summary>Who should I contact if I have problems?</summary>
                    <div>
                        <p>If you encounter technical difficulties or have questions not answered here, please contact the election administrators or the system support team.</p>
                        <p class="mt-2"><strong>Support Contact (Example):</strong></p>
                        <ul class="list-disc list-inside ml-4">
                            <li>Email: support@onlevoting-example.com</li>
                            <li>Phone: +977-1-xxxxxx (Replace with actual contact if available)</li>
                            <li>Project Supervisors: Ananda Adhikari, [Department Name], Academia International College</li>
                        </ul>
                    </div>
                </details>
            </div>

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
