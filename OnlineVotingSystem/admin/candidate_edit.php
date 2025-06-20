<?php
// admin/candidate_edit.php
session_start();
require_once 'includes/db_connect.php'; // Ensure this path is correct

// --- Authentication & Authorization ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
    $_SESSION['login_message'] = "Access denied. Please log in as an administrator.";
    header("Location: login.php");
    exit();
}
$admin_username = $_SESSION['username']; // For display

// --- Get Candidate ID and Election ID from URL ---
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $_SESSION['message'] = "Invalid or missing candidate ID.";
    $_SESSION['message_status'] = "error";
    header("Location: election_management.php"); // Or a more relevant page
    exit();
}
$candidate_id = (int)$_GET['id'];

if (!isset($_GET['election_id']) || !filter_var($_GET['election_id'], FILTER_VALIDATE_INT)) {
    $_SESSION['message'] = "Invalid or missing election ID for the candidate.";
    $_SESSION['message_status'] = "error";
    header("Location: election_management.php");
    exit();
}
$election_id = (int)$_GET['election_id'];

// --- Fetch Candidate Data ---
$candidate = null;
$error_message = null;

$sql_fetch_candidate = "SELECT Name, Description, DisplayOrder FROM Candidates WHERE CandidateID = ? AND ElectionID = ?";
$stmt_fetch_candidate = $conn->prepare($sql_fetch_candidate);

if ($stmt_fetch_candidate) {
    $stmt_fetch_candidate->bind_param("ii", $candidate_id, $election_id);
    $stmt_fetch_candidate->execute();
    $result_candidate = $stmt_fetch_candidate->get_result();
    if ($result_candidate->num_rows === 1) {
        $candidate = $result_candidate->fetch_assoc();
    } else {
        $_SESSION['message'] = "Candidate not found for the specified election.";
        $_SESSION['message_status'] = "error";
        // Redirect to candidate management for that election, or general election management
        header("Location: candidate_management.php?election_id=" . $election_id);
        exit();
    }
    $stmt_fetch_candidate->close();
} else {
    $error_message = "Database error fetching candidate details: " . $conn->error;
    error_log("Prepare failed (fetch candidate): " . $conn->error);
}

// --- Fetch Parent Election Details (to check if editing is allowed) ---
$election_title = "Unknown Election";
$election_status = "Unknown";
$can_manage_candidates = false; // Default to false

if (!$error_message) { // Proceed only if no error fetching candidate
    $stmt_election = $conn->prepare("SELECT Title, Status, StartDate FROM Elections WHERE ElectionID = ?");
    if ($stmt_election) {
        $stmt_election->bind_param("i", $election_id);
        $stmt_election->execute();
        $result_election = $stmt_election->get_result();
        if ($election_data = $result_election->fetch_assoc()) {
            $election_title = $election_data['Title'];
            $election_status = $election_data['Status'];
            $election_start_date = $election_data['StartDate'];

            // Determine if candidate management is allowed
            $now = date('Y-m-d H:i:s');
            if ($election_status === 'Pending' || ($election_status === 'Active' && $now < $election_start_date)) {
                 $can_manage_candidates = true;
            }
        } else {
            // This case should ideally be caught earlier if election_id was invalid
            $error_message = ($error_message ? $error_message . "<br>" : '') . "Parent election not found.";
        }
        $stmt_election->close();
    } else {
         $error_message = ($error_message ? $error_message . "<br>" : '') . "Error fetching parent election details: " . $conn->error;
         error_log("Prepare failed (fetch parent election for candidate edit): " . $conn->error);
    }
}


// --- Handle Session Messages (e.g., errors from handler) ---
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

$conn->close(); // Close the connection

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Candidate - <?php echo htmlspecialchars($candidate['Name'] ?? 'N/A'); ?></title>
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
        input[type="text"], input[type="number"], textarea {
            width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.375rem;
            box-shadow: inset 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }
        input:disabled, textarea:disabled { background-color: #f3f4f6; cursor: not-allowed; }
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
                <a href="candidate_management.php?election_id=<?php echo $election_id; ?>" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700"><i class="fas fa-users mr-2 w-5 text-center"></i>Candidates</a>
                <a href="voter_management.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700"><i class="fas fa-user-check mr-2 w-5 text-center"></i>Voters</a>
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
                <h1 class="text-xl md:text-2xl font-semibold text-gray-700">Edit Candidate</h1>
                <div>
                    <span class="text-gray-600 text-sm md:text-base">Election: <?php echo htmlspecialchars($election_title); ?> (ID: <?php echo $election_id; ?>)</span>
                </div>
            </header>

            <?php if ($error_message): ?>
                <div class='border px-4 py-3 rounded relative mb-4 bg-red-100 border-red-400 text-red-700' role='alert'>
                    <span class='block sm:inline'><?php echo $error_message; // May contain HTML <br> ?></span>
                </div>
            <?php endif; ?>

            <?php if (!$can_manage_candidates && $candidate): ?>
                 <div class='border px-4 py-3 rounded relative mb-4 bg-yellow-100 border-yellow-400 text-yellow-700' role='alert'>
                     <span class='block sm:inline'><i class="fas fa-exclamation-triangle mr-2"></i>Candidate details cannot be edited because the election has started or is closed.</span>
                 </div>
            <?php endif; ?>

            <?php if ($candidate): // Only show form if candidate data was fetched ?>
            <div class="bg-white p-6 rounded-lg shadow border border-gray-200 max-w-2xl">
                <form action="includes/handle_candidate_edit.php" method="POST" id="editCandidateForm">
                    <input type="hidden" name="candidate_id" value="<?php echo $candidate_id; ?>">
                    <input type="hidden" name="election_id" value="<?php echo $election_id; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                    <div class="mb-4">
                        <label for="name">Candidate Name:</label>
                        <input type="text" id="name" name="name" required
                               value="<?php echo htmlspecialchars($candidate['Name']); ?>"
                               <?php echo !$can_manage_candidates ? 'disabled' : ''; ?>>
                        <div id="nameError" class="validation-error"></div>
                    </div>

                    <div class="mb-4">
                        <label for="description">Description (Optional):</label>
                        <textarea id="description" name="description" <?php echo !$can_manage_candidates ? 'disabled' : ''; ?>><?php echo htmlspecialchars($candidate['Description']); ?></textarea>
                    </div>

                    <div class="mb-6">
                        <label for="display_order">Display Order (Optional, lower numbers first):</label>
                        <input type="number" id="display_order" name="display_order"
                               value="<?php echo htmlspecialchars($candidate['DisplayOrder']); ?>"
                               min="0" <?php echo !$can_manage_candidates ? 'disabled' : ''; ?>>
                    </div>

                    <hr class="my-6">

                    <div>
                        <button type="submit" <?php echo !$can_manage_candidates ? 'disabled' : ''; ?>>
                            <i class="fas fa-save mr-2"></i>Save Changes
                        </button>
                        <a href="candidate_management.php?election_id=<?php echo $election_id; ?>" class="ml-4 text-gray-600 hover:text-gray-800">Cancel</a>
                    </div>
                </form>
            </div>
            <?php elseif (!$error_message): // If $candidate is null but no DB error message was set (should be caught by redirect earlier) ?>
                 <p class="text-red-500">Candidate data could not be loaded.</p>
            <?php endif; ?>

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

        // Basic client-side validation (optional, server-side is crucial)
        const editCandidateForm = document.getElementById('editCandidateForm');
        if (editCandidateForm) {
            editCandidateForm.addEventListener('submit', function(event) {
                const nameInput = document.getElementById('name');
                const nameError = document.getElementById('nameError');
                let isValid = true;
                nameError.textContent = '';

                if (nameInput.value.trim() === '') {
                    nameError.textContent = 'Candidate Name is required.';
                    isValid = false;
                }
                // Add more client-side validation as needed

                if (!isValid) {
                    event.preventDefault(); // Stop form submission
                }
            });
        }
    </script>
</body>
</html>