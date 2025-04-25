<?php
// dashboard.php (Voter Dashboard)
session_start();

// --- Authentication Check ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Voter') {
    $_SESSION['login_message'] = "Please log in to access the voter dashboard.";
    header("Location: login.php");
    exit();
}

// *** Define the application's timezone ***
$app_timezone_str = 'Asia/Kathmandu'; // Use your server/application timezone
date_default_timezone_set($app_timezone_str);

// Include the database connection file
require_once 'includes/db_connect.php';

// Get user information from session
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// --- Fetch Eligible Elections ---
// Get current time as a DateTime object in the application's timezone
try {
    $app_timezone = new DateTimeZone($app_timezone_str);
    $now_dt = new DateTime('now', $app_timezone);
} catch (Exception $e) {
    error_log("Error creating DateTime objects: " . $e->getMessage());
    // Handle error appropriately - perhaps show an error message and exit
    die("An error occurred processing time information. Please contact support.");
}

// Prepare SQL to get elections the user is eligible for
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
        ORDER BY FIELD(e.Status, 'Active', 'Pending', 'Closed', 'Archived'), e.StartDate DESC";

$stmt = $conn->prepare($sql);

$eligible_elections = [];
$error_message = null;

if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // Convert DB date strings into DateTime objects, assuming they are stored
            // relative to the application's timezone ($app_timezone).
            // If your DB stores UTC, you'd use a different timezone here for creation,
            // then potentially convert for comparison/display if needed.
            try {
                // Explicitly create DateTime objects using the application's timezone
                $row['StartDateDT'] = new DateTime($row['StartDate'], $app_timezone);
                $row['EndDateDT'] = new DateTime($row['EndDate'], $app_timezone);
            } catch (Exception $e) {
                error_log("Error parsing date for Election ID " . $row['ElectionID'] . ": " . $e->getMessage());
                $row['StartDateDT'] = null; // Mark as invalid if parsing fails
                $row['EndDateDT'] = null;
            }
            $eligible_elections[] = $row;
        }
    } else {
         $error_message = "Error fetching election results: " . $conn->error;
         error_log($error_message);
    }
    $stmt->close();
} else {
    $error_message = "Could not prepare election query. Please try again later.";
    error_log("Prepare failed (fetch elections): " . $conn->error);
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voter Dashboard - Online Voting System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style>
        body { font-family: 'Inter', sans-serif; }
        .status-badge { padding: 0.2rem 0.75rem; font-size: 0.75rem; font-weight: 600; border-radius: 9999px; display: inline-block; text-align: center; }
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

         <?php
         // Display messages from session (e.g., after voting)
         if (isset($_SESSION['dashboard_message'])) {
             $d_message = $_SESSION['dashboard_message'];
             $d_status = $_SESSION['dashboard_status'] ?? 'info';
             $d_bgColor = ($d_status === 'success') ? 'bg-green-100 border-green-400 text-green-700' : (($d_status === 'error') ? 'bg-red-100 border-red-400 text-red-700' : 'bg-blue-100 border-blue-400 text-blue-700');
             echo "<div class='border px-4 py-3 rounded relative mb-4 {$d_bgColor}' role='alert'>";
             echo "<span class='block sm:inline'>" . htmlspecialchars($d_message) . "</span>";
             echo "</div>";
             unset($_SESSION['dashboard_message']);
             unset($_SESSION['dashboard_status']);
         }
         ?>

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
                        // Determine Status, Message, and Votability
                        $status_text = $election['ElectionStatus']; // Default to DB status
                        $status_color = 'bg-gray-500 text-white'; // Default color
                        $can_vote = false; // Can the user vote right now?
                        $display_message = ''; // Message to display instead of button

                        $start_dt = $election['StartDateDT']; // DateTime object or null
                        $end_dt = $election['EndDateDT'];   // DateTime object or null

                        // --- Add Debug Output (View HTML Source) ---
                        // echo "\n";
                        // echo "\n";
                        // echo "\n";
                        // echo "\n";
                        // echo "\n";
                        // echo "\n";
                        // echo "\n";
                        // echo "\n";
                        // --- End Debug Output ---


                        if (!$start_dt || !$end_dt) {
                            // Handle case where dates are invalid in DB or couldn't be parsed
                            $status_text = 'Error';
                            $status_color = 'bg-black text-white';
                            $display_message = 'Invalid date configuration';
                        } elseif ($election['ElectionStatus'] === 'Closed') {
                            $status_text = 'Closed';
                            $status_color = 'bg-red-500 text-white';
                            $display_message = 'Voting closed';
                        } elseif ($election['ElectionStatus'] === 'Archived') {
                            $status_text = 'Archived';
                            $status_color = 'bg-purple-500 text-white';
                            $display_message = 'Election archived';
                        } elseif ($now_dt < $start_dt) {
                            // Current time is *before* the start date
                            $status_text = 'Upcoming';
                            $status_color = 'bg-yellow-500 text-black'; // Use text-black for better contrast on yellow
                            $display_message = 'Voting not started yet'; // More explicit message
                        } elseif ($now_dt > $end_dt) {
                            // Current time is *after* the end date
                            $status_text = 'Closed';
                            $status_color = 'bg-red-500 text-white';
                            $display_message = 'Voting has ended'; // More explicit message
                            // Note: Ideally, a background job should set the DB status to 'Closed'
                        } else {
                            // Current time is *within* the start and end date window
                            if ($election['ElectionStatus'] === 'Active') {
                                $status_text = 'Active';
                                $status_color = 'bg-green-500 text-white';
                                if ($election['HasVoted']) {
                                    $display_message = 'You have already voted';
                                } else {
                                    $can_vote = true; // All conditions met to vote!
                                }
                            } elseif ($election['ElectionStatus'] === 'Pending') {
                                // Election is within time window but not yet activated by admin
                                $status_text = 'Pending Activation';
                                $status_color = 'bg-blue-500 text-white';
                                $display_message = 'Waiting for admin activation';
                            } else {
                                // Handle any other unexpected DB status within the time window
                                $status_text = $election['ElectionStatus']; // Show the DB status
                                $display_message = 'Status: ' . $election['ElectionStatus'];
                            }
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
                                <?php echo ($start_dt) ? 'Starts: ' . $start_dt->format('M j, Y g:i A') : 'Invalid Start'; ?> |
                                <?php echo ($end_dt) ? 'Ends: ' . $end_dt->format('M j, Y g:i A') : 'Invalid End'; ?>
                            </p>
                        </div>
                        <div class="flex items-center space-x-4 flex-shrink-0">
                            <span class="status-badge <?php echo $status_color; ?>">
                                <?php echo htmlspecialchars($status_text); ?>
                            </span>
                            <?php if ($can_vote): ?>
                                <a href="vote.php?election_id=<?php echo $election['ElectionID']; ?>"
                                   class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition duration-300 shadow text-sm whitespace-nowrap">
                                    <i class="fas fa-vote-yea mr-1"></i>Cast Vote
                                </a>
                            <?php else: ?>
                                <span class="text-gray-500 italic text-sm whitespace-nowrap">
                                    <?php echo htmlspecialchars($display_message); // Display the reason why voting isn't possible ?>
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
