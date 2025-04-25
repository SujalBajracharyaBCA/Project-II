<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms and Conditions - Online Voting System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style> body { font-family: 'Inter', sans-serif; } </style>
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
                 <a href="faq.php" class="px-4 py-2 rounded hover:bg-indigo-800 transition duration-300">FAQ</a>
            </nav>
        </div>
    </header>

    <main class="container mx-auto px-6 py-12">
        <div class="bg-white p-8 rounded-lg shadow-lg border border-gray-200 max-w-4xl mx-auto prose lg:prose-xl">
            <h2 class="text-3xl font-bold text-center text-indigo-700 mb-8">Terms and Conditions</h2>

            <p><strong>Last Updated:</strong> <?php echo date("F j, Y"); ?></p>

            <p>Please read these Terms and Conditions ("Terms", "Terms and Conditions") carefully before using the Online Voting System (the "Service") operated by [Your Organization/College Name/Project Team - Khagendra Malla & Sujal Bajracharya] ("us", "we", or "our").</p>

            <p>Your access to and use of the Service is conditioned upon your acceptance of and compliance with these Terms. These Terms apply to all visitors, users, and others who wish to access or use the Service.</p>

            <p>By accessing or using the Service you agree to be bound by these Terms. If you disagree with any part of the terms then you do not have permission to access the Service.</p>

            <h3>1. Eligibility</h3>
            <p>You must be an eligible voter as determined by the specific election rules set forth by the election administrators to use this Service for voting purposes. You may be required to register and provide accurate identification information.</p>

            <h3>2. User Accounts</h3>
            <p>When you create an account with us, you guarantee that the information you provide is accurate, complete, and current at all times. Inaccurate, incomplete, or obsolete information may result in the immediate termination of your account on the Service.</p>
            <p>You are responsible for maintaining the confidentiality of your account and password, including but not limited to the restriction of access to your computer and/or account. You agree to accept responsibility for any and all activities or actions that occur under your account and/or password.</p>

            <h3>3. Voting Process</h3>
            <p>You agree to use the voting system only for its intended purpose and in accordance with the rules of the specific election. You agree not to attempt to vote more than once per eligible contest, tamper with the voting process, or interfere with the operation of the Service.</p>
            <p>Votes cast are final and cannot be changed once submitted.</p>

            <h3>4. Intellectual Property</h3>
            <p>The Service and its original content, features, and functionality are and will remain the exclusive property of [Your Organization/College Name/Project Team] and its licensors. The Service is protected by copyright, trademark, and other laws.</p>

            <h3>5. Disclaimer of Warranties; Limitation of Liability</h3>
            <p>The Service is provided on an "AS IS" and "AS AVAILABLE" basis. We do not warrant that the service will function uninterrupted, secure, or available at any particular time or location; or that any errors or defects will be corrected.</p>
            <p>In no event shall [Your Organization/College Name/Project Team], nor its directors, employees, partners, agents, suppliers, or affiliates, be liable for any indirect, incidental, special, consequential or punitive damages resulting from your access to or use of, or inability to access or use the Service.</p>
            <p>*(Disclaimer: This is placeholder text. Consult with legal counsel for actual Terms and Conditions.)*</p>

            <h3>6. Governing Law</h3>
            <p>These Terms shall be governed and construed in accordance with the laws of Nepal, without regard to its conflict of law provisions.</p>

            <h3>7. Changes</h3>
            <p>We reserve the right, at our sole discretion, to modify or replace these Terms at any time. We will provide notice of any changes by posting the new Terms and Conditions on this page.</p>

            <h3>8. Contact Us</h3>
            <p>If you have any questions about these Terms, please contact us using the information provided on the FAQ page.</p>

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
