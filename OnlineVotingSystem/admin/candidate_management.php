<?php
// admin/candidate_management.php
session_start();
require_once 'includes/db_connect.php';

// --- Authentication & Authorization ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
    $_SESSION['login_message'] = "Access denied. Please log in as an administrator.";
    header("Location: login.php");
    exit();
}

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
$stmt_election = $conn->prepare("SELECT Title, Status, StartDate FROM Elections WHERE ElectionID = ?");
if ($stmt_election) {
    $stmt_election->bind_param("i", $election_id);
    $stmt_election->execute();
    $result_election = $stmt_election->get_result();
    if ($election_data = $result_election->fetch_assoc()) {
        $election_title = $election_data['Title'];
        $election_status = $election_data['Status'];
        $election_start_date = $election_data['StartDate'];
    } else {
        $_SESSION['message'] = "Election not found.";
        $_SESSION['message_status'] = "error";
        header("Location: election_management.php");
        exit();
    }
    $stmt_election->close();
} else {
     // Handle prepare error
     error_log("Prepare failed (fetch election title): " . $conn->error);
     $_SESSION['message'] = "Error fetching election details.";
     $_SESSION['message_status'] = "error";
     header("Location: election_management.php");
     exit();
}

// --- Determine if editing is allowed (e.g., only before election starts) ---
$can_manage_candidates = false;
$now = date('Y-m-d H:i:s');
if ($election_status === 'Pending' || ($election_status === 'Active' && $now < $election_start_date)) {
     $can_manage_candidates = true;
}


// --- Handle Form Submissions (Add/Edit/Delete) ---
$error_message = null;
$success_message = null;

// ADD Candidate
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_candidate']) && $can_manage_candidates) {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $display_order = filter_var($_POST['display_order'] ?? 0, FILTER_VALIDATE_INT);

    if (empty($name)) {
        $error_message = "Candidate name is required.";
    } else {
        $sql_insert = "INSERT INTO Candidates (ElectionID, Name, Description, DisplayOrder) VALUES (?, ?, ?, ?)";
        $stmt_insert = $conn->prepare($sql_insert);
        if ($stmt_insert) {
            $stmt_insert->bind_param("issi", $election_id, $name, $description, $display_order);
            if ($stmt_insert->execute()) {
                $success_message = "Candidate added successfully.";
            } else {
                $error_message = "Error adding candidate: " . $stmt_insert->error;
                error_log("Execute failed (add candidate): " . $stmt_insert->error);
            }
            $stmt_insert->close();
        } else {
            $error_message = "Database error preparing statement.";
            error_log("Prepare failed (add candidate): " . $conn->error);
        }
    }
}

// DELETE Candidate
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['candidate_id']) && $can_manage_candidates) {
    $candidate_id_to_delete = filter_var($_GET['candidate_id'], FILTER_VALIDATE_INT);
    if ($candidate_id_to_delete) {
        // Add CSRF token check here in a real application
        $sql_delete = "DELETE FROM Candidates WHERE CandidateID = ? AND ElectionID = ?"; // Ensure it belongs to the correct election
        $stmt_delete = $conn->prepare($sql_delete);
        if ($stmt_delete) {
            $stmt_delete->bind_param("ii", $candidate_id_to_delete, $election_id);
            if ($stmt_delete->execute()) {
                 // Check if any row was actually deleted
                 if ($stmt_delete->affected_rows > 0) {
                     $success_message = "Candidate deleted successfully.";
                 } else {
                      $error_message = "Candidate not found or already deleted.";
                 }
            } else {
                $error_message = "Error deleting candidate: " . $stmt_delete->error;
                error_log("Execute failed (delete candidate): " . $stmt_delete->error);
            }
            $stmt_delete->close();
        } else {
             $error_message = "Database error preparing delete statement.";
             error_log("Prepare failed (delete candidate): " . $conn->error);
        }
         // Redirect back to the same page without GET parameters to avoid accidental re-deletion
         header("Location: candidate_management.php?election_id=" . $election_id);
         exit();
    } else {
        $error_message = "Invalid candidate ID for deletion.";
    }
}

// EDIT Candidate - Requires a separate page/modal (candidate_edit.php)
// The link below would point to it.

// --- Fetch Existing Candidates ---
$candidates = [];
$sql_fetch_candidates = "SELECT CandidateID, Name, Description, DisplayOrder FROM Candidates WHERE ElectionID = ? ORDER BY DisplayOrder, Name";
$stmt_fetch = $conn->prepare($sql_fetch_candidates);
if ($stmt_fetch) {
    $stmt_fetch->bind_param("i", $election_id);
    $stmt_fetch->execute();
    $result_candidates = $stmt_fetch->get_result();
    while ($row = $result_candidates->fetch_assoc()) {
        $candidates[] = $row;
    }
    $stmt_fetch->close();
} else {
    $error_message = "Error fetching candidates: " . $conn->error;
    error_log("Prepare failed (fetch candidates): " . $conn->error);
}

$conn->close(); // Close connection

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidate Management - <?php echo htmlspecialchars($election_title); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style>
        body { font-family: 'Inter', sans-serif; }
        /* Include sidebar styles from dashboard.php */
        .sidebar { width: 250px; transition: transform 0.3s ease-in-out; }
        .content { margin-left: 250px; transition: margin-left 0.3s ease-in-out; }
        @media (max-width: 768px) { .sidebar { transform: translateX(-100%); } .content { margin-left: 0; } /* etc. */ }
        th, td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background-color: #f8fafc; font-weight: 600; color: #4b5563; }
        tbody tr:hover { background-color: #f9fafb; }
        .action-btn { padding: 0.3rem 0.6rem; font-size: 0.8rem; margin-right: 0.25rem; border-radius: 0.375rem; transition: background-color 0.2s; }
        /* Basic form styling */
        label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #374151; }
        input[type="text"], input[type="number"], textarea {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            box-shadow: inset 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }
        textarea { min-height: 80px; }
        button[type="submit"] {
            padding: 0.6rem 1.2rem;
            background-color: #10b981; /* green-600 */
            color: white;
            font-weight: 600;
            border-radius: 0.375rem;
            border: none;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        button[type="submit"]:hover { background-color: #059669; /* green-700 */ }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="flex min-h-screen">
        <aside class="sidebar bg-gradient-to-b from-gray-800 to-gray-900 text-white p-6 fixed h-full shadow-lg md:translate-x-0 z-40">
             <div class="flex justify-between items-center mb-8"> <h2 class="text-2xl font-bold"><i class="fas fa-cogs mr-2"></i>Admin Panel</h2> </div>
            <nav>
                <a href="dashboard.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700"><i class="fas fa-tachometer-alt mr-2 w-5 text-center"></i>Dashboard</a>
                <a href="election_management.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700"><i class="fas fa-poll-h mr-2 w-5 text-center"></i>Elections</a>
                <a href="#" class="block py-2.5 px-4 rounded transition duration-200 bg-indigo-600 font-semibold"><i class="fas fa-users mr-2 w-5 text-center"></i>Candidates</a> <a href="voter_management.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700"><i class="fas fa-user-check mr-2 w-5 text-center"></i>Voters</a>
                <a href="results.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700"><i class="fas fa-chart-bar mr-2 w-5 text-center"></i>Results</a>
                <a href="user_management.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700"><i class="fas fa-user-cog mr-2 w-5 text-center"></i>Admin Users</a>
                <a href="audit_logs.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700"><i class="fas fa-history mr-2 w-5 text-center"></i>Audit Logs</a>
                <a href="settings.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-gray-700"><i class="fas fa-sliders-h mr-2 w-5 text-center"></i>Settings</a>
                <a href="includes/logout.php" class="block py-2.5 px-4 rounded transition duration-200 hover:bg-red-600 mt-8 text-red-300 hover:text-white"><i class="fas fa-sign-out-alt mr-2 w-5 text-center"></i>Logout</a>
            </nav>
        </aside>

        <div class="content flex-1 p-6 md:p-10">
             <header class="bg-white shadow rounded-lg p-4 mb-6 flex justify-between items-center">
                 <h1 class="text-xl md:text-2xl font-semibold text-gray-700">
                    Manage Candidates for: <?php echo htmlspecialchars($election_title); ?>
                 </h1>
                 <div><span class="text-gray-600 text-sm md:text-base"> Election ID: <?php echo $election_id; ?></span></div>
             </header>

             <?php if ($success_message): ?>
                 <div class='border px-4 py-3 rounded relative mb-4 bg-green-100 border-green-400 text-green-700' role='alert'>
                     <span class='block sm:inline'><?php echo htmlspecialchars($success_message); ?></span>
                 </div>
              <?php endif; ?>
              <?php if ($error_message): ?>
                 <div class='border px-4 py-3 rounded relative mb-4 bg-red-100 border-red-400 text-red-700' role='alert'>
                     <span class='block sm:inline'><?php echo htmlspecialchars($error_message); ?></span>
                 </div>
             <?php endif; ?>
             <?php if (!$can_manage_candidates): ?>
                  <div class='border px-4 py-3 rounded relative mb-4 bg-yellow-100 border-yellow-400 text-yellow-700' role='alert'>
                       <span class='block sm:inline'><i class="fas fa-exclamation-triangle mr-2"></i>Candidates can only be managed before the election starts. Editing is disabled.</span>
                  </div>
             <?php endif; ?>


            <div class="bg-white p-6 rounded-lg shadow border border-gray-200 mb-6">
                 <h2 class="text-xl font-semibold text-gray-800 mb-4">Existing Candidates</h2>
                 <div class="overflow-x-auto">
                    <table class="min-w-full bg-white">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Order</th>
                                <?php if ($can_manage_candidates): ?>
                                    <th>Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                             <?php if (!empty($candidates)): ?>
                                <?php foreach ($candidates as $candidate): ?>
                                    <tr>
                                        <td><?php echo $candidate['CandidateID']; ?></td>
                                        <td class="font-medium text-gray-900"><?php echo htmlspecialchars($candidate['Name']); ?></td>
                                        <td><?php echo nl2br(htmlspecialchars($candidate['Description'] ?? '')); ?></td>
                                        <td><?php echo $candidate['DisplayOrder']; ?></td>
                                        <?php if ($can_manage_candidates): ?>
                                            <td>
                                                <a href="candidate_edit.php?id=<?php echo $candidate['CandidateID']; ?>&election_id=<?php echo $election_id; ?>" class="action-btn bg-yellow-500 hover:bg-yellow-600 text-white" title="Edit Candidate"><i class="fas fa-edit"></i></a>
                                                <a href="candidate_management.php?action=delete&candidate_id=<?php echo $candidate['CandidateID']; ?>&election_id=<?php echo $election_id; ?>"
                                                   class="action-btn bg-red-500 hover:bg-red-600 text-white"
                                                   title="Delete Candidate"
                                                   onclick="return confirm('Are you sure you want to delete this candidate?');">
                                                   <i class="fas fa-trash-alt"></i>
                                                </a>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?php echo $can_manage_candidates ? 5 : 4; ?>" class="text-center text-gray-500 py-4">No candidates added yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                 </div>
             </div>

            <?php if ($can_manage_candidates): ?>
                 <div class="bg-white p-6 rounded-lg shadow border border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Add New Candidate</h2>
                     <form action="candidate_management.php?election_id=<?php echo $election_id; ?>" method="POST">
                         <input type="hidden" name="add_candidate" value="1">
                        <div class="mb-4">
                            <label for="name">Candidate Name:</label>
                            <input type="text" id="name" name="name" required>
                        </div>
                        <div class="mb-4">
                            <label for="description">Description (Optional):</label>
                            <textarea id="description" name="description"></textarea>
                        </div>
                        <div class="mb-4">
                            <label for="display_order">Display Order (Optional, lower numbers first):</label>
                            <input type="number" id="display_order" name="display_order" value="0" min="0">
                        </div>
                        <div>
                            <button type="submit"><i class="fas fa-plus mr-2"></i>Add Candidate</button>
                        </div>
                    </form>
                 </div>
             <?php endif; ?>


             <footer class="text-center text-gray-500 text-sm mt-8">
                 &copy; <?php echo date("Y"); ?> Online Voting System. Admin Panel.
             </footer>
        </div>
    </div>

    <script>
         // Sidebar toggle script from dashboard/election_management
         const sidebar = document.querySelector('.sidebar');
         const openButton = document.querySelector('.sidebar-toggle-open'); // Ensure these exist in your HTML
         const closeButton = document.querySelector('.sidebar-toggle-close');
         const overlay = document.querySelector('.overlay');

         function openSidebar() { /* ... */ }
         function closeSidebar() { /* ... */ }
         // Add event listeners as in previous files
    </script>
</body>
</html>