<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Voting System - Homepage</title>
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
                <a href="login.php" class="px-4 py-2 rounded hover:bg-indigo-800 transition duration-300">Login</a>
                <a href="register.php" class="px-4 py-2 rounded hover:bg-indigo-800 transition duration-300">Register</a>
                <a href="admin/login.php" class="px-4 py-2 rounded hover:bg-indigo-800 transition duration-300">Admin</a>
                </nav>
        </div>
    </header>

    <main class="container mx-auto px-6 py-12">

        <section class="text-center bg-white p-10 rounded-lg shadow-lg mb-12 border border-gray-200">
            <h2 class="text-4xl font-bold text-indigo-700 mb-4">Welcome to the Secure Online Voting System</h2>
            <p class="text-lg text-gray-600 mb-8">
                Cast your vote easily and securely from anywhere. Your participation shapes the future!
            </p>
            <div class="space-x-4">
                <a href="login.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg transition duration-300 shadow-md text-lg">
                    <i class="fas fa-sign-in-alt mr-2"></i>Voter Login
                </a>
                <a href="register.php" class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-6 rounded-lg transition duration-300 shadow-md text-lg">
                    <i class="fas fa-user-plus mr-2"></i>Register to Vote
                </a>
            </div>
        </section>

        <section class="bg-white p-8 rounded-lg shadow-md border border-gray-200 mb-12">
            <h3 class="text-2xl font-semibold text-gray-700 mb-4 border-b pb-2">
                <i class="fas fa-calendar-alt mr-2 text-indigo-500"></i>Current Election Information
            </h3>
            <?php
                // --- PHP Placeholder for Dynamic Election Info ---
                // In a real application, you would fetch current/upcoming election details from the database here.
                // Example:
                // include 'includes/db_connect.php'; // Assuming you have a db connection file
                // $sql = "SELECT Title, StartDate, EndDate FROM Elections WHERE Status = 'Active' ORDER BY StartDate DESC LIMIT 1";
                // $result = $conn->query($sql);
                // if ($result && $result->num_rows > 0) {
                //     $election = $result->fetch_assoc();
                //     echo "<p class='text-gray-600 mb-2'><strong class='font-medium text-gray-800'>Election:</strong> " . htmlspecialchars($election['Title']) . "</p>";
                //     echo "<p class='text-gray-600 mb-2'><strong class='font-medium text-gray-800'>Starts:</strong> " . date('F j, Y, g:i a', strtotime($election['StartDate'])) . "</p>";
                //     echo "<p class='text-gray-600'><strong class='font-medium text-gray-800'>Ends:</strong> " . date('F j, Y, g:i a', strtotime($election['EndDate'])) . "</p>";
                // } else {
                    echo "<p class='text-gray-500 italic'>No active elections at the moment. Please check back later or log in to see eligible elections.</p>";
                // }
                // $conn->close(); // Close connection if opened
                // --- End PHP Placeholder ---
            ?>
        </section>

        <section class="grid md:grid-cols-3 gap-8 text-center">
            <div class="bg-white p-6 rounded-lg shadow border border-gray-200">
                <i class="fas fa-shield-alt text-4xl text-blue-600 mb-4"></i>
                <h4 class="text-xl font-semibold mb-2">Secure Voting</h4>
                <p class="text-gray-600">Utilizing modern security practices to protect your vote and identity.</p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow border border-gray-200">
                <i class="fas fa-laptop-house text-4xl text-green-600 mb-4"></i>
                <h4 class="text-xl font-semibold mb-2">Convenient Access</h4>
                <p class="text-gray-600">Vote from the comfort of your home or anywhere with internet access.</p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow border border-gray-200">
                <i class="fas fa-chart-line text-4xl text-purple-600 mb-4"></i>
                <h4 class="text-xl font-semibold mb-2">Transparent Results</h4>
                <p class="text-gray-600">Access election results promptly after the voting period closes (as permitted).</p>
            </div>
        </section>

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
