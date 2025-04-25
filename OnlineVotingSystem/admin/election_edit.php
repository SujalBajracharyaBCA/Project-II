<?php
// admin/election_edit.php
session_start();
require_once 'includes/db_connect.php'; // Ensure this path is correct

// --- Authentication & Authorization ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
    $_SESSION['login_message'] = "Access denied. Please log in as an administrator.";
    header("Location: login.php");
    exit();
}
$admin_username = $_SESSION['username']; // For display

// --- Get Election ID from URL ---
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $_SESSION['message'] = "Invalid or missing election ID.";
    $_SESSION['message_status'] = "error";
    header("Location: election_management.php");
    exit();
}
$election_id = (int)$_GET['id'];

// --- Fetch Election Data ---
$election = null;
$error_message = null;

$sql_fetch = "SELECT Title, Description, StartDate, EndDate, VotingMethod, Status FROM Elections WHERE ElectionID = ?";
$stmt_fetch = $conn->prepare($sql_fetch);

if ($stmt_fetch) {
    $stmt_fetch->bind_param("i", $election_id);
    $stmt_fetch->execute();
    $result_fetch = $stmt_fetch->get_result();
    if ($result_fetch->num_rows === 1) {
        $election = $result_fetch->fetch_assoc();
        // Format dates for datetime-local input (YYYY-MM-DDTHH:mm)
        $election['StartDateFormatted'] = date('Y-m-d\TH:i', strtotime($election['StartDate']));
        $election['EndDateFormatted'] = date('Y-m-d\TH:i', strtotime($election['EndDate']));
    } else {
        $_SESSION['message'] = "Election not found.";
        $_SESSION['message_status'] = "error";
        header("Location: election_management.php");
        exit();
    }
    $stmt_fetch->close();
} else {
    $error_message = "Database error fetching election details: " . $conn->error;
    error_log($error_message);
    // Display error on the page instead of redirecting immediately
}

// --- Determine if Editing is Allowed ---
$can_edit = false;
$now = date('Y-m-d H:i:s');
// Allow editing only if the election is 'Pending' or 'Active' but hasn't started yet.
// Adjust this logic based on specific requirements (e.g., allow editing description even if active).
if ($election && ($election['Status'] === 'Pending' || ($election['Status'] === 'Active' && $election['StartDate'] > $now))) {
    $can_edit = true;
}

// --- Handle Session Messages (e.g., errors from handler) ---
// Use different session keys for edit page errors if needed, or reuse general ones
if (isset($_SESSION['message']) && isset($_SESSION['message_status']) && $_SESSION['message_status'] === 'error') {
     $error_message = ($error_message ? $error_message . "<br>" : '') . $_SESSION['message'];
     unset($_SESSION['message']);
     unset($_SESSION['message_status']);
}

// --- CSRF Token Generation ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Define available voting methods
$voting_methods = ['FPTP', 'Approval', 'RCV', 'STV', 'Score', 'Condorcet'];

$conn->close(); // Close the connection

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Election - <?php echo htmlspecialchars($election['Title'] ?? 'N/A'); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style>
        body { font-family: 'Inter', sans-serif; }
        /* Sidebar, responsive styles (copy from other admin pages) */
        .sidebar { width: 250px; transition: transform 0.3s ease-in-out; }
        .content { margin-left: 250px; transition: margin-left 0.3s ease-in-out; }
        @media (max-width: 768px) { .sidebar { transform: translateX(-100%); } .sidebar.open { transform: translateX(0); z-index: 40; } .content { margin-left: 0; } .sidebar-toggle { display: block; } .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); z-index: 30; } .sidebar.open + .overlay { display: block; } }
        @media (min-width: 769px) { .sidebar-toggle-close, .sidebar-toggle-open { display: none; } }
        /* Form styles */
        label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #374151; }
        input[type="text"], input[type="datetime-local"], select, textarea {
            width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.375rem;
            box-shadow: inset 0 1px 2px 0 rgba(0, 0, 0, 0.05); background-color: #fff;
        }
        input:disabled, select:disabled, textarea:disabled { background-color: #f3f4f6; cursor: not-allowed; }
        textarea { min-height: 100px; }
        button[type="submit"] {
            padding: 0.6rem 1.2rem; background-color: #f59e0b; /* amber-500 */ color: white;
            font-weight: 600; border-radius: 0.375rem; border: none; cursor: pointer; transition: background-color 0.2s;
        }
        button[type="submit"]:hover:not(:disabled) { background-color: #d97706; /* amber-600 */ }
        button:disabled { background-color: #9ca3af; cursor: not-allowed; }
        .validation-error { color: #dc2626; font-size: 0.875rem; margin-top: 0.25rem; }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="overlay md:hidden"></div>

    <div class="flex min-h-screen">
        <aside class="sidebar bg-gradient-to-b from-gray-800 to-gray-900 text-white p-6 fixed h-full shadow-lg md:translate-x-0 z-40">
             <div class="flex justify-between items-center mb-8">
                 <h2 class="text-2xl font-bold"><i class="fas fa-cogs mr-2"></i>Admin Panel</h2>
                 <button class="sidebar-toggle-close md:hidden text-white focus:outline-none"><i class="fas fa-times text-xl"></i></button>
             </div>
            <nav>
                <a href="dashboard.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700"><i class="fas fa-tachometer-alt mr-2 w-5 text-center"></i>Dashboard</a>
                <a href="election_management.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700"><i class="fas fa-poll-h mr-2 w-5 text-center"></i>Elections</a>
                 <a href="voting_monitor.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700"><i class="fas fa-tv mr-2 w-5 text-center"></i>Voting Monitor</a>
                 <a href="results.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700"><i class="fas fa-chart-bar mr-2 w-5 text-center"></i>Results</a>
                 <a href="user_management.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700"><i class="fas fa-user-cog mr-2 w-5 text-center"></i>Admin Users</a>
                 <a href="audit_logs.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700"><i class="fas fa-history mr-2 w-5 text-center"></i>Audit Logs</a>
                 <a href="settings.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700"><i class="fas fa-sliders-h mr-2 w-5 text-center"></i>Settings</a>
                 <a href="includes/logout.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-red-600 mt-8 text-red-300 hover:text-white"><i class="fas fa-sign-out-alt mr-2 w-5 text-center"></i>Logout</a>
            </nav>
        </aside>

        <div class="content flex-1 p-6 md:p-10">
            <header class="bg-white shadow rounded-lg p-4 mb-6 flex justify-between items-center">
                <button class="sidebar-toggle-open md:hidden text-gray-600 focus:outline-none"><i class="fas fa-bars text-xl"></i></button>
                <h1 class="text-xl md:text-2xl font-semibold text-gray-700">Edit Election</h1>
                <div>
                    <span class="text-gray-600 text-sm md:text-base">Welcome, <?php echo htmlspecialchars($admin_username); ?>!</span>
                </div>
            </header>

            <?php if ($error_message): ?>
                <div class='border px-4 py-3 rounded relative mb-4 bg-red-100 border-red-400 text-red-700' role='alert'>
                    <span class='block sm:inline'><?php echo $error_message; // May contain HTML <br> ?></span>
                </div>
            <?php endif; ?>

            <?php if (!$can_edit && $election): ?>
                 <div class='border px-4 py-3 rounded relative mb-4 bg-yellow-100 border-yellow-400 text-yellow-700' role='alert'>
                     <span class='block sm:inline'><i class="fas fa-exclamation-triangle mr-2"></i>This election cannot be edited because it is already active or closed.</span>
                 </div>
            <?php endif; ?>

            <?php if ($election): // Only show form if election data was fetched ?>
            <div class="bg-white p-6 rounded-lg shadow border border-gray-200 max-w-2xl">
                <form action="includes/handle_election_edit.php" method="POST" id="editElectionForm" onsubmit="return validateDates()">
                    <input type="hidden" name="election_id" value="<?php echo $election_id; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                    <div class="mb-4">
                        <label for="title">Election Title:</label>
                        <input type="text" id="title" name="title" required
                               value="<?php echo htmlspecialchars($election['Title']); ?>"
                               <?php echo !$can_edit ? 'disabled' : ''; ?>>
                        <div id="titleError" class="validation-error"></div>
                    </div>

                    <div class="mb-4">
                        <label for="description">Description (Optional):</label>
                        <textarea id="description" name="description" <?php echo !$can_edit ? 'disabled' : ''; ?>><?php echo htmlspecialchars($election['Description']); ?></textarea>
                    </div>

                    <div class="mb-4">
                        <label for="start_date">Start Date and Time:</label>
                        <input type="datetime-local" id="start_date" name="start_date" required
                               value="<?php echo htmlspecialchars($election['StartDateFormatted']); ?>"
                               <?php echo !$can_edit ? 'disabled' : ''; ?>>
                        <div id="startDateError" class="validation-error"></div>
                    </div>

                    <div class="mb-4">
                        <label for="end_date">End Date and Time:</label>
                        <input type="datetime-local" id="end_date" name="end_date" required
                               value="<?php echo htmlspecialchars($election['EndDateFormatted']); ?>"
                               <?php echo !$can_edit ? 'disabled' : ''; ?>>
                        <div id="endDateError" class="validation-error"></div>
                    </div>

                    <div class="mb-6">
                        <label for="voting_method">Voting Method:</label>
                        <select id="voting_method" name="voting_method" required <?php echo !$can_edit ? 'disabled' : ''; ?>>
                            <option value="" disabled>-- Select Method --</option>
                            <?php foreach ($voting_methods as $method): ?>
                                <option value="<?php echo $method; ?>" <?php echo ($election['VotingMethod'] == $method) ? 'selected' : ''; ?>>
                                    <?php echo $method; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                         <div id="methodError" class="validation-error"></div>
                         <?php if (!$can_edit): ?>
                            <p class="text-xs text-gray-500 mt-1">Voting method cannot be changed after creation or start.</p>
                         <?php endif; ?>
                    </div>

                    <hr class="my-6">

                    <div>
                        <button type="submit" <?php echo !$can_edit ? 'disabled' : ''; ?>>
                            <i class="fas fa-save mr-2"></i>Save Changes
                        </button>
                        <a href="election_management.php" class="ml-4 text-gray-600 hover:text-gray-800">Cancel</a>
                    </div>
                </form>
            </div>
            <?php endif; // End check if election data exists ?>

            <footer class="text-center text-gray-500 text-sm mt-8">
                &copy; <?php echo date("Y"); ?> Online Voting System. Admin Panel.
            </footer>
        </div>
    </div>

    <script>
        // Sidebar toggle script (copy from dashboard.php or other admin pages)
        const sidebar = document.querySelector('.sidebar');
        const openButton = document.querySelector('.sidebar-toggle-open');
        const closeButton = document.querySelector('.sidebar-toggle-close');
        const overlay = document.querySelector('.overlay');

        function openSidebar() { sidebar.classList.add('open'); sidebar.classList.remove('-translate-x-full'); if(overlay) overlay.style.display = 'block'; }
        function closeSidebar() { sidebar.classList.remove('open'); sidebar.classList.add('-translate-x-full'); if(overlay) overlay.style.display = 'none'; }

        if (openButton) openButton.addEventListener('click', openSidebar);
        if (closeButton) closeButton.addEventListener('click', closeSidebar);
        if (overlay) overlay.addEventListener('click', closeSidebar);
        window.addEventListener('resize', () => {
            if (window.innerWidth >= 768) { if (sidebar.classList.contains('open')) closeSidebar(); sidebar.classList.remove('-translate-x-full'); }
            else if (!sidebar.classList.contains('open')) sidebar.classList.add('-translate-x-full');
        });
        if (window.innerWidth < 768) { sidebar.classList.add('-translate-x-full'); }

        // Client-side date validation (similar to create form)
        function validateDates() {
            const startDateInput = document.getElementById('start_date');
            const endDateInput = document.getElementById('end_date');
            const startDateError = document.getElementById('startDateError');
            const endDateError = document.getElementById('endDateError');
            const titleError = document.getElementById('titleError');
            const methodError = document.getElementById('methodError');

            const title = document.getElementById('title').value.trim();
            const method = document.getElementById('voting_method').value;

            let isValid = true;
            startDateError.textContent = '';
            endDateError.textContent = '';
            titleError.textContent = '';
            methodError.textContent = '';

            if (title === '') {
                 titleError.textContent = 'Election Title is required.';
                 isValid = false;
            }
             if (method === '') {
                 methodError.textContent = 'Please select a voting method.';
                 isValid = false;
             }

            const startDate = startDateInput.value;
            const endDate = endDateInput.value;
            // Note: Comparing with 'now' might be less critical here if server-side handles preventing edits after start.
            // const now = new Date().toISOString().slice(0, 16);

            if (!startDate) {
                 startDateError.textContent = 'Start Date is required.';
                 isValid = false;
            }
            if (!endDate) {
                endDateError.textContent = 'End Date is required.';
                isValid = false;
            }
            if (startDate && endDate && startDate >= endDate) {
                endDateError.textContent = 'End Date must be after Start Date.';
                isValid = false;
            }

            return isValid;
        }
    </script>
</body>
</html>
