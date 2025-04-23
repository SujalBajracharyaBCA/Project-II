<?php
// Start the session to access logged-in user data
session_start();

// --- Authentication Check ---
// Check if user is logged in and is a Voter
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Voter') {
    // If not logged in or not a Voter, redirect to login page
    $_SESSION['login_message'] = "Please log in to access the voter dashboard.";
    header("Location: login.php");
    exit();
}

// Include the database connection file
require_once 'includes/db_connect.php';

// Get user information from session
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// --- Fetch Eligible Elections ---
// Get current time for comparison
$now = date('Y-m-d H:i:s');

// Prepare SQL to get elections the user is eligible for, along with election details and voting status
$sql = "SELECT
            e.ElectionID,
            e.Title,
            e.Description,
            e.StartDate,
            e.EndDate,
            e.Status AS ElectionStatus,
            ev.HasVoted
        FROM Elections e
        JOIN EligibleVoters ev ON e.ElectionID = ev.ElectionID
        WHERE ev.UserID = ?
        ORDER BY e.StartDate DESC"; // Order by start date, newest first

$stmt = $conn->prepare($sql);

$eligible_elections = []; // Initialize array to store election data

if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $eligible_elections[] = $row;
        }
    }
    $stmt->close();
} else {
    // Handle prepare statement error
    // Log error: error_log("Prepare failed (fetch elections): " . $conn->error);
    // Display a generic error message or handle appropriately
    $error_message = "Could not retrieve election information. Please try again later.";
}

$conn->close(); // Close the database connection
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voter Dashboard - Online Voting System</title>
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
                <span class="px-4 py-2 text-indigo-200">Welcome, <?php echo htmlspecialchars($username); ?>!</span>
                <a href="dashboard.php" class="px-4 py-2 rounded hover:bg-indigo-800 transition duration-300 font-semibold">Dashboard</a>
                <a href="includes/logout.php" class="px-4 py-2 rounded bg-red-500 hover:bg-red-600 transition duration-300">
                    <i class="fas fa-sign-out-alt mr-1"></i>Logout
                </a>
            </nav>
        </div>
    </header>

    <main class="container mx-auto px-6 py-12">
        <h2 class="text-3xl font-bold text-indigo-700 mb-6">Your Eligible Elections</h2>

        <?php if (isset($error_message)): ?>
            <div class='border border-red-400 bg-red-100 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>
                <span class='block sm:inline'><?php echo htmlspecialchars($error_message); ?></span>
            </div>
        <?php endif; ?>

        <?php if (empty($eligible_elections) && !isset($error_message)): ?>
            <div class="bg-white p-6 rounded-lg shadow border border-gray-200 text-center">
                <p class="text-gray-600 text-lg">You are not currently registered for any elections, or there are no active elections available for you.</p>
            </div>
        <?php else: ?>
            <div class="space-y-6">
                <?php foreach ($eligible_elections as $election): ?>
                    <?php
                        // Determine election status based on dates and DB status
                        $status_text = $election['ElectionStatus'];
                        $status_color = 'bg-gray-500'; // Default (Pending/Archived)
                        $can_vote = false;

                        if ($election['ElectionStatus'] === 'Active' && $now >= $election['StartDate'] && $now <= $election['EndDate']) {
                            $status_text = 'Active';
                            $status_color = 'bg-green-500';
                            if (!$election['HasVoted']) {
                                $can_vote = true; // Can vote if active and not already voted
                            }
                        } elseif ($election['ElectionStatus'] === 'Pending' && $now < $election['StartDate']) {
                            $status_text = 'Upcoming';
                            $status_color = 'bg-yellow-500';
                        } elseif ($election['ElectionStatus'] === 'Closed' || $now > $election['EndDate']) {
                            $status_text = 'Closed';
                            $status_color = 'bg-red-500';
                             // Ensure DB status reflects closed if end date passed
                             // (A background job or admin action should ideally update DB status)
                        }
                    ?>
                    <div class="bg-white p-6 rounded-lg shadow-md border border-gray-200 flex flex-col md:flex-row justify-between items-start md:items-center">
                        <div class="mb-4 md:mb-0 md:mr-6 flex-grow">
                            <h3 class="text-xl font-semibold text-gray-800 mb-1"><?php echo htmlspecialchars($election['Title']); ?></h3>
                            <p class="text-sm text-gray-500 mb-2">
                                <?php echo htmlspecialchars($election['Description'] ?? 'No description available.'); ?>
                            </p>
                            <p class="text-sm text-gray-600">
                                <i class="far fa-calendar-alt mr-1"></i>
                                Starts: <?php echo date('M j, Y g:i A', strtotime($election['StartDate'])); ?> |
                                Ends: <?php echo date('M j, Y g:i A', strtotime($election['EndDate'])); ?>
                            </p>
                        </div>
                        <div class="flex items-center space-x-4 flex-shrink-0">
                            <span class="text-sm font-medium text-white px-3 py-1 rounded-full <?php echo $status_color; ?>">
                                <?php echo $status_text; ?>
                            </span>
                            <?php if ($election['HasVoted']): ?>
                                <span class="text-green-600 font-semibold flex items-center">
                                    <i class="fas fa-check-circle mr-2"></i>Voted
                                </span>
                            <?php elseif ($can_vote): ?>
                                <a href="vote.php?election_id=<?php echo $election['ElectionID']; ?>"
                                   class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition duration-300 shadow text-sm">
                                    <i class="fas fa-vote-yea mr-1"></i>Cast Vote
                                </a>
                            <?php else: ?>
                                <span class="text-gray-400 italic text-sm">
                                    <?php echo ($status_text === 'Upcoming') ? 'Voting not started' : 'Voting closed'; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

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
