<?php
// admin/voter_eligibility.php
session_start();
require_once 'includes/db_connect.php'; // Ensure this path is correct

// --- Authentication & Authorization ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
    $_SESSION['login_message'] = "Access denied. Please log in as an administrator.";
    header("Location: login.php");
    exit();
}
$admin_username = $_SESSION['username']; // For display

// --- Get Election ID ---
if (!isset($_GET['election_id']) || !filter_var($_GET['election_id'], FILTER_VALIDATE_INT)) {
    $_SESSION['message'] = "Invalid or missing election ID.";
    $_SESSION['message_status'] = "error";
    header("Location: election_management.php");
    exit();
}
$election_id = (int)$_GET['election_id'];

// --- Fetch Election Details ---
$election_title = "Unknown Election";
$election_status = "Unknown";
$election_start_date = null;
$error_message = null;

$stmt_election = $conn->prepare("SELECT Title, Status, StartDate FROM Elections WHERE ElectionID = ?");
if ($stmt_election) {
    $stmt_election->bind_param("i", $election_id);
    $stmt_election->execute();
    $result_election = $stmt_election->get_result();
    if ($election_data = $result_election->fetch_assoc()) {
        $election_title = $election_data['Title'];
        $election_status = $election_data['Status'];
        $election_start_date = new DateTime($election_data['StartDate']);
    } else {
        $_SESSION['message'] = "Election not found.";
        $_SESSION['message_status'] = "error";
        header("Location: election_management.php");
        exit();
    }
    $stmt_election->close();
} else {
     $error_message = "Error fetching election details: " . $conn->error;
     error_log("Prepare failed (fetch election title): " . $conn->error);
     // Display error later
}

// --- Determine if Eligibility Can Be Managed ---
// Typically allowed only before the election starts or while pending
$can_manage_eligibility = false;
$now_dt = new DateTime();
if ($election_status === 'Pending' || ($election_status === 'Active' && $election_start_date > $now_dt)) {
     $can_manage_eligibility = true;
}

// --- Fetch All Registered Voters ---
$all_voters = [];
$sql_all_voters = "SELECT UserID, Username, FullName, Email FROM Users WHERE Role = 'Voter' AND IsActive = TRUE ORDER BY Username";
$result_all_voters = $conn->query($sql_all_voters);
if ($result_all_voters) {
    while ($row = $result_all_voters->fetch_assoc()) {
        $all_voters[$row['UserID']] = $row; // Key by UserID
    }
} else {
     $error_message = ($error_message ? $error_message."<br>" : '') . "Error fetching voters: " . $conn->error;
     error_log("Error fetching voters: " . $conn->error);
}

// --- Fetch Currently Eligible Voters for this Election ---
$eligible_voter_ids = [];
$sql_eligible = "SELECT UserID FROM EligibleVoters WHERE ElectionID = ?";
$stmt_eligible = $conn->prepare($sql_eligible);
if ($stmt_eligible) {
    $stmt_eligible->bind_param("i", $election_id);
    $stmt_eligible->execute();
    $result_eligible = $stmt_eligible->get_result();
    while ($row = $result_eligible->fetch_assoc()) {
        $eligible_voter_ids[$row['UserID']] = true; // Use UserID as key for quick lookup
    }
    $stmt_eligible->close();
} else {
    $error_message = ($error_message ? $error_message."<br>" : '') . "Error fetching eligibility: " . $conn->error;
    error_log("Prepare failed (fetch eligibility): " . $conn->error);
}


// --- Handle Session Messages ---
$success_message = null;
if (isset($_SESSION['message'])) {
    if ($_SESSION['message_status'] === 'success') {
        $success_message = $_SESSION['message'];
    } else {
        // Append session error to any existing DB errors
        $error_message = ($error_message ? $error_message."<br>" : '') . $_SESSION['message'];
    }
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
    <title>Manage Voter Eligibility - <?php echo htmlspecialchars($election_title); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style>
        body { font-family: 'Inter', sans-serif; }
        /* Sidebar, responsive, table styles (copy from other admin pages) */
        .sidebar { width: 250px; transition: transform 0.3s ease-in-out; }
        .content { margin-left: 250px; transition: margin-left 0.3s ease-in-out; }
        @media (max-width: 768px) { .sidebar { transform: translateX(-100%); } .sidebar.open { transform: translateX(0); z-index: 40; } .content { margin-left: 0; } .sidebar-toggle { display: block; } .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); z-index: 30; } .sidebar.open + .overlay { display: block; } }
        @media (min-width: 769px) { .sidebar-toggle-close, .sidebar-toggle-open { display: none; } }
        th, td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
        th { background-color: #f8fafc; font-weight: 600; color: #4b5563; }
        tbody tr:hover { background-color: #f9fafb; }
        .status-badge { padding: 0.2rem 0.5rem; font-size: 0.75rem; font-weight: 600; border-radius: 9999px; display: inline-block; }
        .action-button { padding: 0.5rem 1rem; font-size: 0.875rem; border-radius: 0.375rem; transition: background-color 0.2s; display: inline-flex; align-items: center; text-decoration: none; color: white; border: none; cursor: pointer; }
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
                <a href="voter_management.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700"><i class="fas fa-user-check mr-2 w-5 text-center"></i>Voters</a>
                 <a href="user_management.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700"><i class="fas fa-user-cog mr-2 w-5 text-center"></i>Admin Users</a>
                 <a href="audit_logs.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700"><i class="fas fa-history mr-2 w-5 text-center"></i>Audit Logs</a>
                 <a href="settings.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700"><i class="fas fa-sliders-h mr-2 w-5 text-center"></i>Settings</a>
                 <a href="includes/logout.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-red-600 mt-8 text-red-300 hover:text-white"><i class="fas fa-sign-out-alt mr-2 w-5 text-center"></i>Logout</a>
            </nav>
        </aside>

        <div class="content flex-1 p-6 md:p-10">
             <header class="bg-white shadow rounded-lg p-4 mb-6 flex justify-between items-center">
                 <button class="sidebar-toggle-open md:hidden text-gray-600 focus:outline-none"><i class="fas fa-bars text-xl"></i></button>
                 <h1 class="text-xl md:text-2xl font-semibold text-gray-700">
                    Manage Eligibility: <?php echo htmlspecialchars($election_title); ?>
                 </h1>
                 <div>
                     <span class="text-gray-600 text-sm md:text-base">Welcome, <?php echo htmlspecialchars($admin_username); ?>!</span>
                 </div>
             </header>

             <?php if ($success_message): ?>
                 <div class='border px-4 py-3 rounded relative mb-4 bg-green-100 border-green-400 text-green-700' role='alert'>
                     <span class='block sm:inline'><?php echo htmlspecialchars($success_message); ?></span>
                 </div>
              <?php endif; ?>
              <?php if ($error_message): ?>
                 <div class='border px-4 py-3 rounded relative mb-4 bg-red-100 border-red-400 text-red-700' role='alert'>
                     <span class='block sm:inline'><?php echo $error_message; // May contain HTML <br> ?></span>
                 </div>
             <?php endif; ?>
             <?php if (!$can_manage_eligibility && !$error_message): ?>
                  <div class='border px-4 py-3 rounded relative mb-4 bg-yellow-100 border-yellow-400 text-yellow-700' role='alert'>
                       <span class='block sm:inline'><i class="fas fa-exclamation-triangle mr-2"></i>Voter eligibility can only be managed before the election starts. Modifications are disabled.</span>
                  </div>
             <?php endif; ?>

            <div class="bg-white p-6 rounded-lg shadow border border-gray-200">
                <form action="includes/handle_voter_eligibility.php" method="POST" id="eligibilityForm">
                    <input type="hidden" name="election_id" value="<?php echo $election_id; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-semibold text-gray-800">Registered Voters</h2>
                        <?php if ($can_manage_eligibility): ?>
                        <div class="space-x-2">
                             <button type="submit" name="action" value="add" class="action-button bg-green-500 hover:bg-green-600">
                                 <i class="fas fa-user-plus mr-2"></i> Add Selected to Election
                             </button>
                             <button type="submit" name="action" value="remove" class="action-button bg-red-500 hover:bg-red-600" onclick="return confirm('Are you sure you want to remove eligibility for the selected voters? Voters who have already voted cannot be removed.');">
                                 <i class="fas fa-user-minus mr-2"></i> Remove Selected from Election
                             </button>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white">
                            <thead>
                                <tr>
                                    <?php if ($can_manage_eligibility): ?>
                                    <th class="w-10"><input type="checkbox" id="selectAllCheckbox" title="Select/Deselect All"></th>
                                    <?php endif; ?>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Full Name</th>
                                    <th>Email</th>
                                    <th>Eligibility Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($all_voters)): ?>
                                    <?php foreach ($all_voters as $voter_id => $voter): ?>
                                        <?php $is_eligible = isset($eligible_voter_ids[$voter_id]); ?>
                                        <tr>
                                            <?php if ($can_manage_eligibility): ?>
                                            <td>
                                                <input type="checkbox" name="voter_ids[]" value="<?php echo $voter_id; ?>" class="voter-checkbox">
                                            </td>
                                            <?php endif; ?>
                                            <td><?php echo $voter['UserID']; ?></td>
                                            <td class="font-medium text-gray-900"><?php echo htmlspecialchars($voter['Username']); ?></td>
                                            <td><?php echo htmlspecialchars($voter['FullName'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($voter['Email']); ?></td>
                                            <td>
                                                <?php if ($is_eligible): ?>
                                                    <span class="status-badge bg-green-100 text-green-800">Eligible</span>
                                                <?php else: ?>
                                                    <span class="status-badge bg-gray-100 text-gray-800">Not Eligible</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="<?php echo $can_manage_eligibility ? 6 : 5; ?>" class="text-center text-gray-500 py-4">No registered voters found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                     <?php if ($can_manage_eligibility && !empty($all_voters)): ?>
                     <div class="mt-4 flex justify-end space-x-2">
                          <button type="submit" name="action" value="add" class="action-button bg-green-500 hover:bg-green-600">
                              <i class="fas fa-user-plus mr-2"></i> Add Selected to Election
                          </button>
                          <button type="submit" name="action" value="remove" class="action-button bg-red-500 hover:bg-red-600" onclick="return confirm('Are you sure you want to remove eligibility for the selected voters? Voters who have already voted cannot be removed.');">
                              <i class="fas fa-user-minus mr-2"></i> Remove Selected from Election
                          </button>
                     </div>
                     <?php endif; ?>
                </form>
            </div>

            <footer class="text-center text-gray-500 text-sm mt-8">
                 &copy; <?php echo date("Y"); ?> Online Voting System. Admin Panel.
             </footer>
        </div>
    </div>

    <script>
        // Sidebar toggle script
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

        // Select All Checkbox functionality
        const selectAllCheckbox = document.getElementById('selectAllCheckbox');
        const voterCheckboxes = document.querySelectorAll('.voter-checkbox');

        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                voterCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
            });

            // Optional: Uncheck "Select All" if any individual box is unchecked
            voterCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    if (!this.checked) {
                        selectAllCheckbox.checked = false;
                    } else {
                        // Check if all are checked now
                        let allChecked = true;
                        voterCheckboxes.forEach(cb => {
                            if (!cb.checked) allChecked = false;
                        });
                        selectAllCheckbox.checked = allChecked;
                    }
                });
            });
        }
    </script>
</body>
</html>
