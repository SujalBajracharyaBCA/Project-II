<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - Online Voting System</title>
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
            <h2 class="text-3xl font-bold text-center text-indigo-700 mb-8">Privacy Policy</h2>

            <p><strong>Last Updated:</strong> <?php echo date("F j, Y"); ?></p>

            <p>[Your Organization/College Name/Project Team - Khagendra Malla & Sujal Bajracharya] ("us", "we", or "our") operates the Online Voting System (the "Service"). This page informs you of our policies regarding the collection, use, and disclosure of personal data when you use our Service and the choices you have associated with that data.</p>
            <p>*(Disclaimer: This is placeholder text. Consult with legal counsel for an actual Privacy Policy.)*</p>

            <h3>1. Information Collection and Use</h3>
            <p>We collect several different types of information for various purposes to provide and improve our Service to you.</p>
            <h4>Types of Data Collected</h4>
            <ul>
                <li><strong>Personal Data:</strong> While using our Service, we may ask you to provide us with certain personally identifiable information that can be used to contact or identify you ("Personal Data"). Personally identifiable information may include, but is not limited to: Email address, Full Name, Username, Unique Identifier (e.g., Voter ID, if applicable).</li>
                <li><strong>Usage Data:</strong> We may also collect information on how the Service is accessed and used ("Usage Data"). This Usage Data may include information such as your computer's Internet Protocol address (e.g. IP address), browser type, browser version, the pages of our Service that you visit, the time and date of your visit, the time spent on those pages, unique device identifiers and other diagnostic data. (Specify if you actually collect this).</li>
                <li><strong>Voting Data:</strong> When you cast a vote, the system records your selections. We employ measures designed to separate your vote selections from your personal identity during the tallying process to maintain voter anonymity relative to the vote content. However, the system necessarily records *that* you have voted to prevent double voting.</li>
            </ul>

            <h3>2. Use of Data</h3>
            <p>We use the collected data for various purposes:</p>
            <ul>
                <li>To provide and maintain our Service</li>
                <li>To manage your registration and authenticate you as a voter</li>
                <li>To allow you to participate in elections for which you are eligible</li>
                <li>To prevent fraudulent activity, such as double voting</li>
                <li>To tally votes and determine election results</li>
                <li>To notify you about changes to our Service (if applicable)</li>
                <li>To provide customer support (if applicable)</li>
                <li>To monitor the usage of our Service (if applicable)</li>
                <li>To detect, prevent and address technical issues</li>
            </ul>

            <h3>3. Data Security</h3>
            <p>The security of your data is important to us. We use appropriate technical and organizational measures to protect your Personal Data and voting information against unauthorized access, alteration, disclosure, or destruction. These measures include password hashing, use of HTTPS, and secure database practices. However, remember that no method of transmission over the Internet or method of electronic storage is 100% secure.</p>

             <h3>4. Vote Anonymity</h3>
             <p>We strive to protect the anonymity of your vote choices. The system is designed such that administrators tallying the votes cannot link a specific vote choice back to an individual voter's identity. The record of *who* voted is maintained separately from the record of *how* they voted for tallying purposes.</p>
             <p>*(Note: Clearly explain the actual mechanism used if possible and its limitations.)*</p>

            <h3>5. Data Retention</h3>
            <p>We will retain your Personal Data only for as long as is necessary for the purposes set out in this Privacy Policy and to comply with our legal obligations (if any). Voting data may be retained as required by election regulations or for auditing purposes, potentially in an anonymized or aggregated form.</p>

            <h3>6. Disclosure Of Data</h3>
            <p>We will not disclose your Personal Data or individual voting data except as required by law or specific election regulations.</p>

            <h3>7. Changes To This Privacy Policy</h3>
            <p>We may update our Privacy Policy from time to time. We will notify you of any changes by posting the new Privacy Policy on this page.</p>

            <h3>8. Contact Us</h3>
            <p>If you have any questions about this Privacy Policy, please contact us using the information provided on the FAQ page.</p>

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
