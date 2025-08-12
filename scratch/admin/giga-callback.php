<?php
// api/giga-callback.php - GigaPub S2S Callback Handler

// =============== CONFIGURATION ===============
$expected_secret = 'your_giga_secret_123'; // 🔑 Change this! Set in GigaPub dashboard
$reward_amount = 500; // SHIB or any unit
$enable_reward = true; // Set to false if you handle rewards elsewhere

// =============== VALIDATE REQUEST ===============
$secret = $_GET['secret'] ?? '';
if ($secret !== $expected_secret) {
    http_response_code(403);
    exit('Invalid secret');
}

$user_id = trim($_GET['user_id'] ?? '');
$ad_id = trim($_GET['ad_id'] ?? '');

if (empty($user_id) || empty($ad_id)) {
    http_response_code(400);
    exit('Missing parameters');
}

// Validate user_id is numeric
if (!is_numeric($user_id)) {
    error_log("Invalid user_id: $user_id");
    http_response_code(400);
    exit('Invalid user ID');
}

// =============== DATABASE CONNECTION ===============
$servername = "localhost";
$username = "kkiqkkiu_scratch";
$password = "kkiqkkiu_scratch";
$dbname = "kkiqkkiu_scratch";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    error_log("DB connection failed: " . $conn->connect_error);
    http_response_code(500);
    exit('Server error');
}

// Use UTF-8
$conn->set_charset("utf8mb4");

// =============== PREVENT DUPLICATES ===============
$stmt = $conn->prepare("SELECT id FROM gigapub_completions WHERE user_id = ? AND DATE(created_at) = CURDATE()");
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    http_response_code(500);
    exit('Server error');
}

$stmt->bind_param("s", $user_id);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    exit('Duplicate'); // User already completed an ad today
}
$stmt->close();

// =============== INSERT COMPLETION ===============
$stmt = $conn->prepare("INSERT INTO gigapub_completions (user_id, ad_id) VALUES (?, ?)");
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    http_response_code(500);
    exit('Server error');
}

$stmt->bind_param("ss", $user_id, $ad_id);

if ($stmt->execute()) {
    $stmt->close();

    // =============== OPTIONAL: REWARD USER ===============
    if ($enable_reward) {
        $update = $conn->prepare("UPDATE users SET balance = balance + ? WHERE user_id = ?");
        if ($update) {
            $update->bind_param("ds", $reward_amount, $user_id);
            $update->execute();
            $update->close();
        } else {
            error_log("Failed to prepare balance update for user $user_id");
        }
    }

    // Log success
    error_log("✅ GigaPub ad completed: user_id=$user_id, ad_id=$ad_id");

    // Respond OK
    header('Content-Type: text/plain');
    exit('OK');
} else {
    error_log("❌ Insert failed: " . $stmt->error);
    $stmt->close();
    http_response_code(500);
    exit('Save failed');
}

// Close connection
$conn->close();
?>